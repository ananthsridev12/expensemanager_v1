<?php

namespace Models;

use DateInterval;
use DateTime;
use PDO;

class Loan extends BaseModel
{
    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM loans ORDER BY start_date DESC');

        return $stmt->fetchAll();
    }

    public function linkToLending(int $loanId, ?int $lendingId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE loans SET linked_lending_id = :lending_id WHERE id = :loan_id'
        );
        return $stmt->execute([
            ':lending_id' => $lendingId > 0 ? $lendingId : null,
            ':loan_id'    => $loanId,
        ]);
    }

    public function getLinkedPairs(): array
    {
        $sql = <<<SQL
SELECT
    l.id                        AS loan_id,
    l.loan_name,
    l.outstanding_principal     AS loan_outstanding,
    l.linked_lending_id,
    lr.id                       AS lending_id,
    lr.principal_amount         AS lending_principal,
    c.name                      AS contact_name,
    COALESCE((
        SELECT SUM(s.principal_component + s.interest_component)
        FROM loan_emi_schedule s
        WHERE s.loan_id = l.id AND s.status = 'paid'
    ), 0)                       AS total_emi_paid,
    COALESCE((
        SELECT SUM(lrp.amount)
        FROM lending_repayments lrp
        WHERE lrp.lending_record_id = lr.id
    ), 0)                       AS total_recovered,
    GREATEST(0, lr.principal_amount - COALESCE((
        SELECT SUM(lrp.amount)
        FROM lending_repayments lrp
        WHERE lrp.lending_record_id = lr.id
    ), 0))                      AS lending_outstanding
FROM loans l
JOIN lending_records lr ON lr.id = l.linked_lending_id
JOIN contacts c ON c.id = lr.contact_id
WHERE l.linked_lending_id IS NOT NULL
ORDER BY l.start_date DESC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllLendingOptions(): array
    {
        $sql = <<<SQL
SELECT
    lr.id,
    c.name AS contact_name,
    lr.principal_amount,
    GREATEST(0, lr.principal_amount - COALESCE((
        SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id
    ), 0)) AS outstanding_amount
FROM lending_records lr
JOIN contacts c ON c.id = lr.contact_id
WHERE lr.status = 'ongoing'
ORDER BY c.name ASC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markEmiPaid(array $input): bool
    {
        $emiId  = (int) ($input['emi_id']  ?? 0);
        $loanId = (int) ($input['loan_id'] ?? 0);
        $paymentDate  = !empty($input['payment_date']) ? (string) $input['payment_date'] : date('Y-m-d');
        $accountToken = (string) ($input['payment_account'] ?? '');

        if ($emiId <= 0 || $loanId <= 0 || $accountToken === '' || strpos($accountToken, ':') === false) {
            return false;
        }

        [$accountType, $accountIdRaw] = explode(':', $accountToken, 2);
        $accountId = (int) $accountIdRaw;
        $allowedTypes = ['savings', 'current', 'cash', 'wallet', 'other'];
        if ($accountId <= 0 || !in_array($accountType, $allowedTypes, true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT s.*, l.loan_name FROM loan_emi_schedule s
             JOIN loans l ON l.id = s.loan_id
             WHERE s.id = :emi_id AND s.loan_id = :loan_id AND s.status != \'paid\'
             LIMIT 1'
        );
        $stmt->execute([':emi_id' => $emiId, ':loan_id' => $loanId]);
        $emi = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emi) {
            return false;
        }

        $totalAmount      = (float) $emi['principal_component'] + (float) $emi['interest_component'];
        $principalComponent = (float) $emi['principal_component'];
        $loanName         = (string) ($emi['loan_name'] ?? 'Loan #' . $loanId);

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE loan_emi_schedule SET status = \'paid\' WHERE id = :id')
                ->execute([':id' => $emiId]);

            $this->db->prepare(
                'UPDATE loans
                 SET outstanding_principal = GREATEST(0, outstanding_principal - :amount),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :loan_id'
            )->execute([':amount' => $principalComponent, ':loan_id' => $loanId]);

            $this->db->prepare(
                'INSERT INTO transactions
                    (transaction_date, account_type, account_id, transaction_type, amount, reference_type, reference_id, notes)
                 VALUES
                    (:date, :account_type, :account_id, \'expense\', :amount, \'loan\', :loan_id, :notes)'
            )->execute([
                ':date'         => $paymentDate,
                ':account_type' => $accountType,
                ':account_id'   => $accountId,
                ':amount'       => $totalAmount,
                ':loan_id'      => $loanId,
                ':notes'        => 'EMI payment — ' . $loanName,
            ]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function getUpcomingEmis(int $limit = 5): array
    {
        $sql = <<<SQL
SELECT
    s.*, l.loan_name, l.interest_rate
FROM loan_emi_schedule s
JOIN loans l ON l.id = s.loan_id
WHERE s.status != 'paid'
ORDER BY s.emi_date ASC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $input): ?int
    {
        $principal = max(0, (float) ($input['principal_amount'] ?? 0));
        $interestRate = (float) ($input['interest_rate'] ?? 0);
        $tenure = max(1, (int) ($input['tenure_months'] ?? 12));
        $processingFee = (float) ($input['processing_fee'] ?? 0);
        $gstRate = (float) ($input['gst'] ?? 0);
        $startDate = $input['start_date'] ?? date('Y-m-d');
        $repaymentType = ($input['repayment_type'] ?? 'emi') === 'interest_only' ? 'interest_only' : 'emi';

        $emiAmount = $repaymentType === 'interest_only'
            ? $this->calculateMonthlyInterestOnlyAmount($principal, $interestRate)
            : $this->calculateMonthlyEmi($principal, $interestRate, $tenure);

        $sql = 'INSERT INTO loans (loan_type, loan_name, principal_amount, interest_rate, tenure_months, emi_amount, processing_fee, gst, repayment_type, start_date, outstanding_principal) VALUES (:loan_type, :loan_name, :principal_amount, :interest_rate, :tenure_months, :emi_amount, :processing_fee, :gst, :repayment_type, :start_date, :outstanding_principal)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':loan_type' => $input['loan_type'] ?? 'personal',
            ':loan_name' => trim($input['loan_name'] ?? 'Untitled Loan'),
            ':principal_amount' => $principal,
            ':interest_rate' => $interestRate,
            ':tenure_months' => $tenure,
            ':emi_amount' => $emiAmount,
            ':processing_fee' => $processingFee,
            ':gst' => $gstRate,
            ':repayment_type' => $repaymentType,
            ':start_date' => $startDate,
            ':outstanding_principal' => $principal,
        ]);

        $loanId = (int) $this->db->lastInsertId();
        $this->createEmiSchedule($loanId, $principal, $interestRate, $tenure, $emiAmount, $startDate, $repaymentType, $processingFee, $gstRate);
        $this->createDisbursementTransfer($loanId, trim($input['loan_name'] ?? 'Untitled Loan'), $principal, $startDate, (string) ($input['disbursement_account'] ?? ''));

        return $loanId;
    }

    public function createExisting(array $input): ?int
    {
        $originalPrincipal = max(0, (float) ($input['principal_amount'] ?? 0));
        $outstanding = max(0, (float) ($input['outstanding_principal'] ?? $originalPrincipal));
        $interestRate = (float) ($input['interest_rate'] ?? 0);
        $remainingTenure = max(1, (int) ($input['remaining_tenure_months'] ?? 1));
        $repaymentType = ($input['repayment_type'] ?? 'emi') === 'interest_only' ? 'interest_only' : 'emi';
        $nextEmiDate = $input['next_emi_date'] ?? date('Y-m-d');
        $startDate = $input['start_date'] ?? date('Y-m-d');

        // Use provided EMI or calculate from outstanding + remaining tenure
        $emiAmount = !empty($input['emi_amount']) && (float) $input['emi_amount'] > 0
            ? (float) $input['emi_amount']
            : ($repaymentType === 'interest_only'
                ? $this->calculateMonthlyInterestOnlyAmount($outstanding, $interestRate)
                : $this->calculateMonthlyEmi($outstanding, $interestRate, $remainingTenure));

        $sql = 'INSERT INTO loans (loan_type, loan_name, principal_amount, interest_rate, tenure_months, emi_amount, processing_fee, gst, repayment_type, start_date, outstanding_principal) VALUES (:loan_type, :loan_name, :principal_amount, :interest_rate, :tenure_months, :emi_amount, :processing_fee, :gst, :repayment_type, :start_date, :outstanding_principal)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':loan_type' => $input['loan_type'] ?? 'personal',
            ':loan_name' => trim($input['loan_name'] ?? 'Untitled Loan'),
            ':principal_amount' => $originalPrincipal > 0 ? $originalPrincipal : $outstanding,
            ':interest_rate' => $interestRate,
            ':tenure_months' => $remainingTenure,
            ':emi_amount' => $emiAmount,
            ':processing_fee' => 0,
            ':gst' => 0,
            ':repayment_type' => $repaymentType,
            ':start_date' => $startDate,
            ':outstanding_principal' => $outstanding,
        ]);

        $loanId = (int) $this->db->lastInsertId();

        // Generate remaining EMI schedule from outstanding balance and next EMI date
        $this->createEmiSchedule($loanId, $outstanding, $interestRate, $remainingTenure, $emiAmount, $nextEmiDate, $repaymentType, 0, 0);

        return $loanId;
    }

    public function applyTransactionMovement(int $loanId, string $transactionType, float $amount): void
    {
        if ($loanId <= 0 || $amount <= 0) {
            return;
        }

        if ($transactionType === 'expense') {
            $stmt = $this->db->prepare(
                'UPDATE loans
                 SET outstanding_principal = outstanding_principal + :amount,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :loan_id'
            );
            $stmt->execute([
                ':amount' => $amount,
                ':loan_id' => $loanId,
            ]);
            return;
        }

        if ($transactionType === 'income') {
            $stmt = $this->db->prepare(
                'UPDATE loans
                 SET outstanding_principal = GREATEST(0, outstanding_principal - :amount),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :loan_id'
            );
            $stmt->execute([
                ':amount' => $amount,
                ':loan_id' => $loanId,
            ]);
        }
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM loans WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $name = trim((string) ($input['loan_name'] ?? ''));
        if ($name === '') return false;
        $stmt = $this->db->prepare(
            'UPDATE loans SET loan_name=:loan_name, loan_type=:loan_type, interest_rate=:interest_rate, emi_amount=:emi_amount, outstanding_principal=:outstanding_principal WHERE id=:id'
        );
        return $stmt->execute([
            ':loan_name' => $name,
            ':loan_type' => $input['loan_type'] ?? 'personal',
            ':interest_rate' => (float) ($input['interest_rate'] ?? 0),
            ':emi_amount' => (float) ($input['emi_amount'] ?? 0),
            ':outstanding_principal' => (float) ($input['outstanding_principal'] ?? 0),
            ':id' => $id,
        ]);
    }

    private function createDisbursementTransfer(
        int $loanId,
        string $loanName,
        float $principal,
        string $transferDate,
        string $accountToken
    ): void {
        if ($loanId <= 0 || $principal <= 0 || $accountToken === '' || strpos($accountToken, ':') === false) {
            return;
        }

        [$accountType, $accountIdRaw] = explode(':', $accountToken, 2);
        $accountId = (int) $accountIdRaw;
        $allowedTypes = ['savings', 'current', 'cash', 'other'];

        if ($accountId <= 0 || !in_array($accountType, $allowedTypes, true)) {
            return;
        }

        $notes = 'Loan disbursal - ' . $loanName;
        $stmt = $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, amount, reference_type, reference_id, notes)
             VALUES (:transaction_date, :account_type, :account_id, :transaction_type, :amount, :reference_type, :reference_id, :notes)'
        );

        // Loan ledger side (debt increases).
        $stmt->execute([
            ':transaction_date' => $transferDate,
            ':account_type' => 'loan',
            ':account_id' => null,
            ':transaction_type' => 'expense',
            ':amount' => $principal,
            ':reference_type' => 'loan',
            ':reference_id' => $loanId,
            ':notes' => $notes,
        ]);

        // Destination account side (cash/bank increases).
        $stmt->execute([
            ':transaction_date' => $transferDate,
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':transaction_type' => 'income',
            ':amount' => $principal,
            ':reference_type' => 'loan',
            ':reference_id' => $loanId,
            ':notes' => $notes,
        ]);
    }

    private function calculateMonthlyEmi(float $principal, float $annualRate, int $months): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0.0;
        }

        $monthlyRate = $annualRate / 12 / 100;
        if ($monthlyRate == 0.0) {
            return $principal / $months;
        }

        $numerator = $principal * $monthlyRate * pow(1 + $monthlyRate, $months);
        $denominator = pow(1 + $monthlyRate, $months) - 1;

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    private function calculateMonthlyInterestOnlyAmount(float $principal, float $annualRate): float
    {
        if ($principal <= 0) {
            return 0.0;
        }

        return $principal * ($annualRate / 12 / 100);
    }

    private function createEmiSchedule(
        int $loanId,
        float $principal,
        float $annualRate,
        int $tenure,
        float $emiAmount,
        string $startDate,
        string $repaymentType,
        float $processingFee,
        float $gstRate
    ): void
    {
        if ($tenure <= 0) {
            return;
        }

        $monthlyRate = $annualRate / 12 / 100;
        $processingFeeGst = round($processingFee * ($gstRate / 100), 2);
        $balance = $principal;
        $date = new DateTime($startDate);

        $stmt = $this->db->prepare('INSERT INTO loan_emi_schedule (loan_id, emi_date, principal_component, interest_component, status) VALUES (:loan_id, :emi_date, :principal_component, :interest_component, :status)');

        for ($month = 1; $month <= $tenure; $month++) {
            if ($repaymentType === 'interest_only') {
                $interestComponent = $principal * $monthlyRate;
                $principalComponent = $month === $tenure ? $balance : 0.0;
            } else {
                $interestComponent = $balance * $monthlyRate;
                $principalComponent = $emiAmount - $interestComponent;
                if ($month === $tenure) {
                    $principalComponent = $balance;
                }
            }

            if ($month === 1 && ($processingFee > 0 || $processingFeeGst > 0)) {
                // Processing fee and GST are one-time charges, not monthly interest GST.
                $interestComponent += $processingFee + $processingFeeGst;
            }

            $emiDate = $date->format('Y-m-d');
            $stmt->execute([
                ':loan_id' => $loanId,
                ':emi_date' => $emiDate,
                ':principal_component' => max(0, $principalComponent),
                ':interest_component' => max(0, $interestComponent),
                ':status' => 'pending',
            ]);

            $balance -= $principalComponent;
            $date->add(new DateInterval('P1M'));
        }
    }
}
