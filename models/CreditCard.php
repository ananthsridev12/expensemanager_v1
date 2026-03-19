<?php

namespace Models;

use PDO;

class CreditCard extends BaseModel
{
    public function getAll(): array
    {
        $sql = <<<SQL
SELECT
    cc.*,
    a.id AS account_id,
    a.account_type,
    a.account_name
FROM credit_cards cc
LEFT JOIN accounts a ON a.id = cc.account_id
ORDER BY cc.created_at DESC
SQL;
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $input): bool
    {
        return false;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM credit_cards WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByAccountId(int $accountId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM credit_cards WHERE account_id = :account_id LIMIT 1');
        $stmt->execute([':account_id' => $accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function hasEmiPlanTables(): bool
    {
        return $this->tableExists('credit_card_emi_plans') && $this->tableExists('credit_card_emi_schedule');
    }

    public function getEmiPlans(): array
    {
        if (!$this->hasEmiPlanTables()) {
            return [];
        }

        $sql = <<<SQL
SELECT
    p.*,
    c.bank_name,
    c.card_name
FROM credit_card_emi_plans p
JOIN credit_cards c ON c.id = p.credit_card_id
ORDER BY p.status = 'active' DESC, p.next_due_date ASC, p.created_at DESC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUpcomingSchedule(int $limit = 25): array
    {
        if (!$this->hasEmiPlanTables()) {
            return [];
        }

        $sql = <<<SQL
SELECT
    s.*,
    p.plan_name,
    p.status AS plan_status,
    c.bank_name,
    c.card_name
FROM credit_card_emi_schedule s
JOIN credit_card_emi_plans p ON p.id = s.emi_plan_id
JOIN credit_cards c ON c.id = p.credit_card_id
WHERE s.status IN ('pending', 'upcoming')
ORDER BY s.due_date ASC, s.installment_no ASC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createEmiPlan(array $input): bool
    {
        if (!$this->hasEmiPlanTables()) {
            return false;
        }

        $cardId = (int) ($input['credit_card_id'] ?? 0);
        $accountId = (int) ($input['account_id'] ?? 0);
        $planName = trim((string) ($input['plan_name'] ?? ''));
        $principalAmount = max(0, (float) ($input['principal_amount'] ?? 0));
        $outstandingPrincipal = max(0, (float) ($input['outstanding_principal'] ?? $principalAmount));
        $interestRate = max(0, (float) ($input['interest_rate'] ?? 0));
        $tenureMonths = max(0, (int) ($input['tenure_months'] ?? 0));
        $emiAmount = max(0, (float) ($input['emi_amount'] ?? 0));
        $processingFee = max(0, (float) ($input['processing_fee'] ?? 0));
        $gstRate = max(0, (float) ($input['gst_rate'] ?? 0));
        $startDate = !empty($input['start_date']) ? $input['start_date'] : date('Y-m-d');
        $nextDueDate = !empty($input['next_due_date']) ? $input['next_due_date'] : $startDate;
        $totalEmis = max(1, (int) ($input['total_emis'] ?? ($tenureMonths > 0 ? $tenureMonths : 1)));
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($cardId <= 0 || $planName === '' || $principalAmount <= 0 || $outstandingPrincipal <= 0) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $insertPlan = $this->db->prepare(
                'INSERT INTO credit_card_emi_plans
                (credit_card_id, plan_name, principal_amount, outstanding_principal, interest_rate, tenure_months, emi_amount, processing_fee, gst_rate, start_date, next_due_date, total_emis, paid_emis, status, notes)
                VALUES
                (:credit_card_id, :plan_name, :principal_amount, :outstanding_principal, :interest_rate, :tenure_months, :emi_amount, :processing_fee, :gst_rate, :start_date, :next_due_date, :total_emis, :paid_emis, :status, :notes)'
            );
            $insertPlan->execute([
                ':credit_card_id' => $cardId,
                ':plan_name' => $planName,
                ':principal_amount' => $principalAmount,
                ':outstanding_principal' => $outstandingPrincipal,
                ':interest_rate' => $interestRate,
                ':tenure_months' => $tenureMonths,
                ':emi_amount' => $emiAmount,
                ':processing_fee' => $processingFee,
                ':gst_rate' => $gstRate,
                ':start_date' => $startDate,
                ':next_due_date' => $nextDueDate,
                ':total_emis' => $totalEmis,
                ':paid_emis' => 0,
                ':status' => 'active',
                ':notes' => $notes !== '' ? $notes : null,
            ]);

            $planId = (int) $this->db->lastInsertId();
            $this->generateEmiSchedule($planId, $outstandingPrincipal, $interestRate, $emiAmount, $processingFee, $gstRate, $nextDueDate, $totalEmis);

            $updateCard = $this->db->prepare(
                'UPDATE credit_cards
                 SET outstanding_principal = outstanding_principal + :pending_principal,
                     outstanding_balance = outstanding_balance + :pending_principal,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :card_id'
            );
            $updateCard->execute([
                ':pending_principal' => $outstandingPrincipal,
                ':card_id' => $cardId,
            ]);

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            return false;
        }
    }

    public function createEmiPlanFromTransaction(array $input): array
    {
        if (!$this->hasEmiPlanTables()) {
            return ['success' => false];
        }

        $cardId = (int) ($input['credit_card_id'] ?? 0);
        $planName = trim((string) ($input['plan_name'] ?? ''));
        $principalAmount = max(0.0, (float) ($input['principal_amount'] ?? 0));
        $interestRate = max(0.0, (float) ($input['interest_rate'] ?? 0));
        $totalEmis = max(1, (int) ($input['total_emis'] ?? 1));
        $emiFirstDate = !empty($input['emi_date']) ? $input['emi_date'] : null;
        $processingFee = max(0.0, (float) ($input['processing_fee'] ?? 0));
        $gstRate = max(0.0, (float) ($input['gst_rate'] ?? 0));
        $purchaseDate = !empty($input['transaction_date']) ? $input['transaction_date'] : date('Y-m-d');
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($cardId <= 0 && $accountId > 0) {
            $card = $this->getByAccountId($accountId);
            $cardId = (int) ($card['id'] ?? 0);
        }

        if ($cardId <= 0 || $planName === '' || $principalAmount <= 0 || !$emiFirstDate) {
            return ['success' => false];
        }

        $card = $this->getById($cardId);
        if (!$card) {
            return ['success' => false];
        }

        $this->db->beginTransaction();
        try {
            $insertPlan = $this->db->prepare(
                'INSERT INTO credit_card_emi_plans
                (credit_card_id, plan_name, principal_amount, outstanding_principal, interest_rate, tenure_months, emi_amount, processing_fee, gst_rate, start_date, next_due_date, total_emis, paid_emis, status, notes)
                VALUES
                (:credit_card_id, :plan_name, :principal_amount, :outstanding_principal, :interest_rate, :tenure_months, :emi_amount, :processing_fee, :gst_rate, :start_date, :next_due_date, :total_emis, :paid_emis, :status, :notes)'
            );
            $insertPlan->execute([
                ':credit_card_id' => $cardId,
                ':plan_name' => $planName,
                ':principal_amount' => $principalAmount,
                ':outstanding_principal' => $principalAmount,
                ':interest_rate' => $interestRate,
                ':tenure_months' => $totalEmis,
                ':emi_amount' => 0.00,
                ':processing_fee' => $processingFee,
                ':gst_rate' => $gstRate,
                ':start_date' => $purchaseDate,
                ':next_due_date' => $emiFirstDate,
                ':total_emis' => $totalEmis,
                ':paid_emis' => 0,
                ':status' => 'active',
                ':notes' => $notes !== '' ? $notes : null,
            ]);

            $planId = (int) $this->db->lastInsertId();
            $schedule = $this->generateEmiScheduleForPlan($planId, $principalAmount, $interestRate, $gstRate, $emiFirstDate, $totalEmis);

            $firstInstallmentInterest = (float) ($schedule['first_interest_component'] ?? 0.0);
            $firstInstallmentInterestGst = (float) ($schedule['first_interest_gst'] ?? 0.0);
            $cycleEndDate = $this->getCycleEndDate($purchaseDate, (int) $card['billing_date']);
            $firstInCurrentCycle = strtotime($emiFirstDate) <= strtotime($cycleEndDate);

            $processingGst = round($processingFee * ($gstRate / 100), 2);
            // Outstanding balance includes purchase principal immediately; limit logic still stays principal-only.
            $statementIncrease = $principalAmount + $processingFee + $processingGst;
            if ($firstInCurrentCycle) {
                // Add only current-cycle interest/GST components to avoid principal double counting.
                $statementIncrease += $firstInstallmentInterest + $firstInstallmentInterestGst;
            }

            $updateCard = $this->db->prepare(
                'UPDATE credit_cards
                 SET outstanding_principal = outstanding_principal + :principal_amount,
                     outstanding_balance = outstanding_balance + :statement_increase,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :card_id'
            );
            $updateCard->execute([
                ':principal_amount' => $principalAmount,
                ':statement_increase' => round($statementIncrease, 2),
                ':card_id' => $cardId,
            ]);

            $this->db->commit();
            return [
                'success' => true,
                'plan_id' => $planId,
                'statement_increase' => round($statementIncrease, 2),
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            return ['success' => false];
        }
    }

    private function generateEmiScheduleForPlan(
        int $planId,
        float $principalAmount,
        float $interestRate,
        float $gstRate,
        string $firstDueDate,
        int $totalEmis
    ): array {
        $remainingPrincipal = $principalAmount;
        $monthlyRate = $interestRate / 1200;
        $firstTotalDue = 0.0;
        $firstInterest = 0.0;
        $firstInterestGst = 0.0;

        $insertSchedule = $this->db->prepare(
            'INSERT INTO credit_card_emi_schedule
            (emi_plan_id, installment_no, due_date, opening_principal, principal_component, interest_component, processing_fee, gst_amount, total_due, status)
            VALUES
            (:emi_plan_id, :installment_no, :due_date, :opening_principal, :principal_component, :interest_component, :processing_fee, :gst_amount, :total_due, :status)'
        );

        for ($i = 1; $i <= $totalEmis; $i++) {
            $openingPrincipal = $remainingPrincipal;
            $interestComponent = round($openingPrincipal * $monthlyRate, 2);
            $principalComponent = round($openingPrincipal / ($totalEmis - $i + 1), 2);
            if ($i === $totalEmis) {
                $principalComponent = round($remainingPrincipal, 2);
            }

            $gstOnInterest = round($interestComponent * ($gstRate / 100), 2);
            $totalDue = round($principalComponent + $interestComponent + $gstOnInterest, 2);
            $dueDate = date('Y-m-d', strtotime($firstDueDate . ' +' . ($i - 1) . ' month'));
            $status = strtotime($dueDate) < strtotime(date('Y-m-d')) ? 'pending' : 'upcoming';

            if ($i === 1) {
                $firstTotalDue = $totalDue;
                $firstInterest = $interestComponent;
                $firstInterestGst = $gstOnInterest;
            }

            $insertSchedule->execute([
                ':emi_plan_id' => $planId,
                ':installment_no' => $i,
                ':due_date' => $dueDate,
                ':opening_principal' => round($openingPrincipal, 2),
                ':principal_component' => $principalComponent,
                ':interest_component' => $interestComponent,
                ':processing_fee' => 0.00,
                ':gst_amount' => $gstOnInterest,
                ':total_due' => $totalDue,
                ':status' => $status,
            ]);

            $remainingPrincipal = round(max(0.0, $remainingPrincipal - $principalComponent), 2);
        }

        $this->db->prepare('UPDATE credit_card_emi_plans SET emi_amount = :emi_amount WHERE id = :id')
            ->execute([
                ':emi_amount' => $firstTotalDue,
                ':id' => $planId,
            ]);

        return [
            'first_total_due' => $firstTotalDue,
            'first_interest_component' => $firstInterest,
            'first_interest_gst' => $firstInterestGst,
        ];
    }

    private function getCycleEndDate(string $referenceDate, int $billingDay): string
    {
        $billingDay = max(1, min(28, $billingDay));
        $monthStart = date('Y-m-01', strtotime($referenceDate));
        $billingThisMonth = date('Y-m-d', strtotime($monthStart . ' +' . ($billingDay - 1) . ' days'));
        if (strtotime($referenceDate) <= strtotime($billingThisMonth)) {
            return $billingThisMonth;
        }

        $nextMonthStart = date('Y-m-01', strtotime($monthStart . ' +1 month'));
        return date('Y-m-d', strtotime($nextMonthStart . ' +' . ($billingDay - 1) . ' days'));
    }

    private function generateEmiSchedule(
        int $planId,
        float $outstandingPrincipal,
        float $interestRate,
        float $emiAmount,
        float $processingFee,
        float $gstRate,
        string $firstDueDate,
        int $installments
    ): void {
        $remainingPrincipal = $outstandingPrincipal;
        $monthlyRate = $interestRate / 1200;
        $insertSchedule = $this->db->prepare(
            'INSERT INTO credit_card_emi_schedule
            (emi_plan_id, installment_no, due_date, opening_principal, principal_component, interest_component, processing_fee, gst_amount, total_due, status)
            VALUES
            (:emi_plan_id, :installment_no, :due_date, :opening_principal, :principal_component, :interest_component, :processing_fee, :gst_amount, :total_due, :status)'
        );

        for ($i = 1; $i <= $installments; $i++) {
            $openingPrincipal = $remainingPrincipal;
            $interestComponent = round($openingPrincipal * $monthlyRate, 2);
            $feeComponent = $i === 1 ? $processingFee : 0.00;
            $gstComponent = round(($interestComponent + $feeComponent) * ($gstRate / 100), 2);

            if ($emiAmount > 0) {
                $principalComponent = max(0, round($emiAmount - $interestComponent - $feeComponent - $gstComponent, 2));
            } else {
                $principalComponent = round($openingPrincipal / ($installments - $i + 1), 2);
                $emiAmount = round($principalComponent + $interestComponent + $feeComponent + $gstComponent, 2);
            }

            if ($i === $installments || $principalComponent > $remainingPrincipal) {
                $principalComponent = round($remainingPrincipal, 2);
            }

            $totalDue = round($principalComponent + $interestComponent + $feeComponent + $gstComponent, 2);
            $dueDate = date('Y-m-d', strtotime($firstDueDate . ' +' . ($i - 1) . ' month'));

            $insertSchedule->execute([
                ':emi_plan_id' => $planId,
                ':installment_no' => $i,
                ':due_date' => $dueDate,
                ':opening_principal' => round($openingPrincipal, 2),
                ':principal_component' => $principalComponent,
                ':interest_component' => $interestComponent,
                ':processing_fee' => $feeComponent,
                ':gst_amount' => $gstComponent,
                ':total_due' => $totalDue,
                ':status' => 'pending',
            ]);

            $remainingPrincipal = round(max(0, $remainingPrincipal - $principalComponent), 2);
        }
    }

    public function settleBill(int $cardId, string $paymentDate, string $mode): array
    {
        if (!$this->hasEmiPlanTables() || $cardId <= 0) {
            return ['installments_paid' => 0, 'principal_paid' => 0.0, 'charges_paid' => 0.0, 'total_paid' => 0.0];
        }

        $mode = $mode === 'outstanding' ? 'outstanding' : 'total_due';

        $this->db->beginTransaction();
        try {
            $dueFilter = $mode === 'total_due' ? 'AND s.due_date <= :payment_date' : '';
            $scheduleSql = <<<SQL
SELECT
    s.id,
    s.emi_plan_id,
    s.principal_component,
    s.interest_component,
    s.processing_fee,
    s.gst_amount,
    s.total_due
FROM credit_card_emi_schedule s
JOIN credit_card_emi_plans p ON p.id = s.emi_plan_id
WHERE p.credit_card_id = :card_id
  AND s.status IN ('pending', 'upcoming')
  {$dueFilter}
ORDER BY s.due_date ASC, s.installment_no ASC
SQL;
            $scheduleStmt = $this->db->prepare($scheduleSql);
            $scheduleStmt->bindValue(':card_id', $cardId, PDO::PARAM_INT);
            if ($mode === 'total_due') {
                $scheduleStmt->bindValue(':payment_date', $paymentDate);
            }
            $scheduleStmt->execute();
            $scheduleRows = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($scheduleRows)) {
                $this->db->rollBack();
                return ['installments_paid' => 0, 'principal_paid' => 0.0, 'charges_paid' => 0.0, 'total_paid' => 0.0];
            }

            $markPaidStmt = $this->db->prepare('UPDATE credit_card_emi_schedule SET status = :status WHERE id = :id');
            $updatePlanStmt = $this->db->prepare(
                'UPDATE credit_card_emi_plans
                 SET outstanding_principal = :outstanding_principal,
                     paid_emis = :paid_emis,
                     next_due_date = :next_due_date,
                     status = :status,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );

            $principalPaid = 0.0;
            $chargesPaid = 0.0;
            $touchedPlans = [];

            foreach ($scheduleRows as $row) {
                $principalComponent = (float) $row['principal_component'];
                $chargesComponent = (float) $row['interest_component'] + (float) $row['processing_fee'] + (float) $row['gst_amount'];

                $principalPaid += $principalComponent;
                $chargesPaid += $chargesComponent;
                $touchedPlans[(int) $row['emi_plan_id']] = true;

                $markPaidStmt->execute([
                    ':status' => 'paid',
                    ':id' => (int) $row['id'],
                ]);
            }

            foreach (array_keys($touchedPlans) as $planId) {
                $planStmt = $this->db->prepare('SELECT outstanding_principal, paid_emis FROM credit_card_emi_plans WHERE id = :id LIMIT 1');
                $planStmt->execute([':id' => $planId]);
                $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
                if (!$plan) {
                    continue;
                }

                $principalPaidForPlanStmt = $this->db->prepare(
                    'SELECT COALESCE(SUM(principal_component), 0) AS paid_principal
                     FROM credit_card_emi_schedule
                     WHERE emi_plan_id = :plan_id AND status = :status'
                );
                $principalPaidForPlanStmt->execute([
                    ':plan_id' => $planId,
                    ':status' => 'paid',
                ]);
                $paidPrincipal = (float) ($principalPaidForPlanStmt->fetch(PDO::FETCH_ASSOC)['paid_principal'] ?? 0.0);

                $planBaseStmt = $this->db->prepare('SELECT principal_amount FROM credit_card_emi_plans WHERE id = :id');
                $planBaseStmt->execute([':id' => $planId]);
                $principalAmount = (float) ($planBaseStmt->fetch(PDO::FETCH_ASSOC)['principal_amount'] ?? 0.0);

                $newOutstanding = max(0.0, round($principalAmount - $paidPrincipal, 2));

                $paidCountStmt = $this->db->prepare(
                    'SELECT COUNT(*) AS cnt FROM credit_card_emi_schedule WHERE emi_plan_id = :plan_id AND status = :status'
                );
                $paidCountStmt->execute([
                    ':plan_id' => $planId,
                    ':status' => 'paid',
                ]);
                $paidEmis = (int) ($paidCountStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

                $nextDueStmt = $this->db->prepare(
                    'SELECT due_date FROM credit_card_emi_schedule WHERE emi_plan_id = :plan_id AND status IN (\'pending\', \'upcoming\') ORDER BY due_date ASC, installment_no ASC LIMIT 1'
                );
                $nextDueStmt->execute([':plan_id' => $planId]);
                $nextDueDate = $nextDueStmt->fetch(PDO::FETCH_ASSOC)['due_date'] ?? null;
                $planStatus = $newOutstanding <= 0.0 ? 'closed' : 'active';

                $updatePlanStmt->execute([
                    ':outstanding_principal' => $newOutstanding,
                    ':paid_emis' => $paidEmis,
                    ':next_due_date' => $nextDueDate,
                    ':status' => $planStatus,
                    ':id' => $planId,
                ]);
            }

            $updateCardStmt = $this->db->prepare(
                'UPDATE credit_cards
                 SET outstanding_principal = GREATEST(0, outstanding_principal - :principal_paid),
                     outstanding_balance = GREATEST(0, outstanding_balance - :principal_paid),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :card_id'
            );
            $updateCardStmt->execute([
                ':principal_paid' => $principalPaid,
                ':card_id' => $cardId,
            ]);

            $this->db->commit();
            return [
                'installments_paid' => count($scheduleRows),
                'principal_paid' => round($principalPaid, 2),
                'charges_paid' => round($chargesPaid, 2),
                'total_paid' => round($principalPaid + $chargesPaid, 2),
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            return ['installments_paid' => 0, 'principal_paid' => 0.0, 'charges_paid' => 0.0, 'total_paid' => 0.0];
        }
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $tableName]);
        return (bool) $stmt->fetchColumn();
    }

    public function getSummary(): array
    {
        $sql = 'SELECT COUNT(*) AS count_cards, COALESCE(SUM(credit_limit), 0) AS total_limit, COALESCE(SUM(outstanding_balance), 0) AS total_outstanding FROM credit_cards';
        $stmt = $this->db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'count' => (int) $row['count_cards'],
            'total_limit' => (float) $row['total_limit'],
            'total_outstanding' => (float) $row['total_outstanding'],
        ];
    }

    public function applyTransactionMovement(int $cardId, string $transactionType, float $amount): void
    {
        if ($cardId <= 0 || $amount <= 0) {
            return;
        }

        if ($transactionType === 'expense') {
            $stmt = $this->db->prepare(
                'UPDATE credit_cards
                 SET outstanding_principal = outstanding_principal + :amount,
                     outstanding_balance = outstanding_balance + :amount,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :card_id'
            );
            $stmt->execute([
                ':amount' => $amount,
                ':card_id' => $cardId,
            ]);
            return;
        }

        if ($transactionType === 'income') {
            $stmt = $this->db->prepare(
                'UPDATE credit_cards
                 SET outstanding_principal = GREATEST(0, outstanding_principal - :amount),
                     outstanding_balance = GREATEST(0, outstanding_balance - :amount),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :card_id'
            );
            $stmt->execute([
                ':amount' => $amount,
                ':card_id' => $cardId,
            ]);
        }
    }

    public function applyTransactionMovementByAccount(int $accountId, string $transactionType, float $amount): void
    {
        if ($accountId <= 0) {
            return;
        }

        $card = $this->getByAccountId($accountId);
        if (!$card) {
            return;
        }

        $this->applyTransactionMovement((int) $card['id'], $transactionType, $amount);
    }

    public function getStatementSnapshots(?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');
        $cards = $this->getAll();
        $snapshots = [];

        foreach ($cards as $card) {
            $billingDay = max(1, min(28, (int) ($card['billing_date'] ?? 1)));
            [$cycleStart, $cycleEnd] = $this->getBillingCycleRange($asOfDate, $billingDay);

            $nonEmiSpendStmt = $this->db->prepare(
                'SELECT COALESCE(SUM(amount), 0) AS total
                 FROM transactions
                 WHERE account_id = :account_id
                   AND transaction_type = :transaction_type
                   AND transaction_date BETWEEN :cycle_start AND :cycle_end
                   AND (reference_type IS NULL OR reference_type <> :emi_ref)'
            );
            $nonEmiSpendStmt->execute([
                ':account_id' => (int) ($card['account_id'] ?? 0),
                ':transaction_type' => 'expense',
                ':cycle_start' => $cycleStart,
                ':cycle_end' => $cycleEnd,
                ':emi_ref' => 'credit_card_emi_plan',
            ]);
            $nonEmiSpend = (float) ($nonEmiSpendStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.0);

            $emiDue = 0.0;
            if ($this->hasEmiPlanTables()) {
                $emiDueStmt = $this->db->prepare(
                    'SELECT COALESCE(SUM(s.total_due), 0) AS total
                     FROM credit_card_emi_schedule s
                     JOIN credit_card_emi_plans p ON p.id = s.emi_plan_id
                     WHERE p.credit_card_id = :credit_card_id
                       AND s.status IN (\'pending\', \'upcoming\')
                       AND s.due_date BETWEEN :cycle_start AND :cycle_end'
                );
                $emiDueStmt->execute([
                    ':credit_card_id' => (int) $card['id'],
                    ':cycle_start' => $cycleStart,
                    ':cycle_end' => $cycleEnd,
                ]);
                $emiDue = (float) ($emiDueStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.0);
            }

            $principalUsed = (float) ($card['outstanding_principal'] ?? 0.0);
            $creditLimit = (float) ($card['credit_limit'] ?? 0.0);
            $availableLimit = max(0.0, round($creditLimit - $principalUsed, 2));

            $snapshots[] = [
                'credit_card_id' => (int) $card['id'],
                'account_id' => (int) ($card['account_id'] ?? 0),
                'bank_name' => $card['bank_name'] ?? '',
                'card_name' => $card['card_name'] ?? '',
                'cycle_start' => $cycleStart,
                'cycle_end' => $cycleEnd,
                'statement_spend_non_emi' => round($nonEmiSpend, 2),
                'statement_emi_due' => round($emiDue, 2),
                'statement_total_due' => round($nonEmiSpend + $emiDue, 2),
                'outstanding_balance' => (float) ($card['outstanding_balance'] ?? 0.0),
                'outstanding_principal' => $principalUsed,
                'available_limit' => $availableLimit,
            ];
        }

        return $snapshots;
    }

    private function getBillingCycleRange(string $asOfDate, int $billingDay): array
    {
        $billingDay = max(1, min(28, $billingDay));
        $monthStart = date('Y-m-01', strtotime($asOfDate));
        $billingThisMonth = date('Y-m-d', strtotime($monthStart . ' +' . ($billingDay - 1) . ' days'));

        if (strtotime($asOfDate) <= strtotime($billingThisMonth)) {
            $cycleEnd = $billingThisMonth;
            $previousMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));
            $cycleStart = date('Y-m-d', strtotime($previousMonthStart . ' +' . $billingDay . ' days'));
            return [$cycleStart, $cycleEnd];
        }

        $cycleStart = date('Y-m-d', strtotime($monthStart . ' +' . $billingDay . ' days'));
        $nextMonthStart = date('Y-m-01', strtotime($monthStart . ' +1 month'));
        $cycleEnd = date('Y-m-d', strtotime($nextMonthStart . ' +' . ($billingDay - 1) . ' days'));
        return [$cycleStart, $cycleEnd];
    }
}
