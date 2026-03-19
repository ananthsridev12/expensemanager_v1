<?php

declare(strict_types=1);

use Config\Database;
use Models\CreditCard;
use Models\Loan;
use Models\Transaction;

class ApiTransactionService
{
    private Transaction $transactionModel;
    private CreditCard $creditCardModel;
    private Loan $loanModel;
    private PDO $pdo;

    public function __construct(Database $database)
    {
        $this->transactionModel = new Transaction($database);
        $this->creditCardModel = new CreditCard($database);
        $this->loanModel = new Loan($database);
        $this->pdo = $database->connect();
    }

    public function create(array $input): array
    {
        $transactionDate = (string) ($input['transaction_date'] ?? date('Y-m-d'));
        $transactionType = (string) ($input['transaction_type'] ?? 'expense');
        $amount = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0;
        $accountType = (string) ($input['account_type'] ?? 'savings');
        $accountId = (int) ($input['account_id'] ?? 0);

        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than zero.'];
        }
        if ($accountId <= 0) {
            return ['success' => false, 'message' => 'account_id is required.'];
        }
        if (!$this->isValidDate($transactionDate)) {
            return ['success' => false, 'message' => 'transaction_date must be YYYY-MM-DD.'];
        }

        $categoryId = !empty($input['category_id']) ? (int) $input['category_id'] : null;
        $subcategoryId = !empty($input['subcategory_id']) ? (int) $input['subcategory_id'] : null;
        $notes = !empty($input['notes']) ? (string) $input['notes'] : null;

        if ($transactionType === 'transfer') {
            $toAccountId = (int) ($input['to_account_id'] ?? 0);
            $toAccountType = (string) ($input['to_account_type'] ?? 'savings');
            if ($toAccountId <= 0) {
                return ['success' => false, 'message' => 'to_account_id is required for transfer.'];
            }
            if ($toAccountId === $accountId && $toAccountType === $accountType) {
                return ['success' => false, 'message' => 'From and to account cannot be same.'];
            }

            $this->pdo->beginTransaction();
            try {
                $fromCreated = $this->transactionModel->create([
                    'transaction_date' => $transactionDate,
                    'account_type' => $accountType,
                    'account_id' => $this->resolveAccountId($accountType, $accountId),
                    'transaction_type' => 'expense',
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategoryId,
                    'amount' => $amount,
                    'reference_type' => $this->resolveReferenceType($accountType, 'transfer'),
                    'reference_id' => $this->resolveReferenceId($accountType, $accountId, $toAccountId),
                    'notes' => $notes ?? 'Transfer out',
                ]);
                $fromId = $fromCreated ? (int) $this->pdo->lastInsertId() : 0;
                $toCreated = $this->transactionModel->create([
                    'transaction_date' => $transactionDate,
                    'account_type' => $toAccountType,
                    'account_id' => $this->resolveAccountId($toAccountType, $toAccountId),
                    'transaction_type' => 'income',
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategoryId,
                    'amount' => $amount,
                    'reference_type' => $this->resolveReferenceType($toAccountType, 'transfer'),
                    'reference_id' => $this->resolveReferenceId($toAccountType, $toAccountId, $accountId),
                    'notes' => $notes ?? 'Transfer in',
                ]);
                if (!$fromCreated || !$toCreated) {
                    throw new RuntimeException('Transfer insert failed.');
                }
                $toId = (int) $this->pdo->lastInsertId();

                $this->applyDebtDelta($accountType, $accountId, 'expense', $amount);
                $this->applyDebtDelta($toAccountType, $toAccountId, 'income', $amount);

                $this->pdo->commit();
                return [
                    'success' => true,
                    'data' => [
                        'status' => 'created',
                        'type' => 'transfer',
                        'from_transaction' => $fromId > 0 ? $this->findTransactionById($fromId) : null,
                        'to_transaction' => $toId > 0 ? $this->findTransactionById($toId) : null,
                    ],
                ];
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'message' => $exception->getMessage()];
            }
        }

        $created = $this->transactionModel->create([
            'transaction_date' => $transactionDate,
            'account_type' => $accountType,
            'account_id' => $this->resolveAccountId($accountType, $accountId),
            'transaction_type' => $transactionType,
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'amount' => $amount,
            'reference_type' => $this->resolveReferenceType($accountType, null),
            'reference_id' => $this->resolveReferenceId($accountType, $accountId, null),
            'notes' => $notes,
        ]);
        if (!$created) {
            return ['success' => false, 'message' => 'Failed to create transaction.'];
        }
        $transactionId = (int) $this->pdo->lastInsertId();

        $this->applyDebtDelta($accountType, $accountId, $transactionType, $amount);

        return [
            'success' => true,
            'data' => [
                'status' => 'created',
                'type' => 'single',
                'transaction' => $transactionId > 0 ? $this->findTransactionById($transactionId) : null,
            ],
        ];
    }

    private function applyDebtDelta(string $accountType, int $accountId, string $transactionType, float $amount): void
    {
        if ($accountType === 'credit_card') {
            $this->creditCardModel->applyTransactionMovementByAccount($accountId, $transactionType, $amount);
            return;
        }
        if ($accountType === 'loan') {
            $this->loanModel->applyTransactionMovement($accountId, $transactionType, $amount);
        }
    }

    private function resolveAccountId(string $accountType, int $accountId): ?int
    {
        return $accountType === 'loan' ? null : ($accountId > 0 ? $accountId : null);
    }

    private function resolveReferenceType(string $accountType, ?string $default): ?string
    {
        if ($accountType === 'loan') {
            return 'loan';
        }
        if ($accountType === 'credit_card') {
            return 'credit_card';
        }
        return $default;
    }

    private function resolveReferenceId(string $accountType, int $accountId, ?int $fallback): ?int
    {
        if ($accountType === 'loan' || $accountType === 'credit_card') {
            return $accountId > 0 ? $accountId : null;
        }
        return $fallback;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = date_create_from_format('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function findTransactionById(int $id): ?array
    {
        $sql = <<<SQL
SELECT
    t.id,
    t.transaction_date,
    t.transaction_type,
    t.amount,
    COALESCE(c.name, 'Uncategorized') AS category_name,
    COALESCE(sc.name, '') AS subcategory_name,
    CASE
        WHEN t.account_type = 'loan' THEN CONCAT('Loan - ', l.loan_name)
        WHEN t.account_type = 'lending' THEN CONCAT('Lending - ', ct.name)
        WHEN t.account_type = 'rental' THEN 'Rental'
        ELSE CONCAT(COALESCE(a.bank_name, '-'), ' - ', COALESCE(a.account_name, '-'))
    END AS account_display,
    COALESCE(t.notes, '') AS notes
FROM transactions t
LEFT JOIN categories c ON c.id = t.category_id
LEFT JOIN subcategories sc ON sc.id = t.subcategory_id
LEFT JOIN accounts a ON a.id = t.account_id
LEFT JOIN loans l ON (t.account_type = 'loan' AND t.reference_type = 'loan' AND l.id = t.reference_id)
LEFT JOIN lending_records lr ON (t.reference_type = 'lending' AND lr.id = t.reference_id)
LEFT JOIN contacts ct ON ct.id = lr.contact_id
WHERE t.id = :id
LIMIT 1
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
