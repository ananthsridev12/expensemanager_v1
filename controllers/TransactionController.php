<?php

namespace Controllers;

use Models\Account;
use Models\Category;
use Models\Contact;
use Models\CreditCard;
use Models\CreditCardReward;
use Models\Lending;
use Models\Loan;
use Models\PaymentMethod;
use Models\PurchaseSource;
use Models\Rental;
use Models\Transaction;
use Models\Investment;

class TransactionController extends BaseController
{
    private Transaction $transactionModel;
    private Account $accountModel;
    private Category $categoryModel;
    private CreditCard $creditCardModel;
    private CreditCardReward $rewardModel;
    private Lending $lendingModel;
    private Loan $loanModel;
    private PaymentMethod $paymentMethodModel;
    private PurchaseSource $purchaseSourceModel;
    private Contact $contactModel;
    private Rental $rentalModel;
    private Investment $investmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->transactionModel = new Transaction($this->database);
        $this->accountModel = new Account($this->database);
        $this->categoryModel = new Category($this->database);
        $this->creditCardModel = new CreditCard($this->database);
        $this->rewardModel = new CreditCardReward($this->database);
        $this->lendingModel = new Lending($this->database);
        $this->loanModel = new Loan($this->database);
        $this->paymentMethodModel = new PaymentMethod($this->database);
        $this->purchaseSourceModel = new PurchaseSource($this->database);
        $this->contactModel = new Contact($this->database);
        $this->rentalModel = new Rental($this->database);
        $this->investmentModel = new Investment($this->database);
    }

    public function index(): string
    {
        if (($_GET['action'] ?? '') === 'contact_search') {
            header('Content-Type: application/json');
            return json_encode(
                $this->contactModel->search((string) ($_GET['q'] ?? ''), 20)
            );
        }

        if (($_GET['action'] ?? '') === 'quick_add_form') {
            header('Content-Type: text/html; charset=utf-8');
            return $this->renderPartial('transactions/quick_add.php', [
                'accounts'           => $this->accountModel->getList(),
                'loans'              => $this->loanModel->getAll(),
                'categories'         => $this->categoryModel->getAllWithSubcategories(),
                'paymentMethods'     => $this->paymentMethodModel->getAll(),
                'purchaseChildren'   => $this->purchaseSourceModel->getChildren(),
                'openLendingRecords' => $this->lendingModel->getOpenRecords(),
                'rentalContracts'    => $this->rentalModel->getContracts(),
                'rentalProperties'   => $this->rentalModel->getProperties(),
                'rentalTenants'      => $this->rentalModel->getTenants(),
                'investments'        => $this->investmentModel->getAll(),
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'set_default_account') {
            $id = (int) ($_POST['account_id'] ?? 0);
            if ($id > 0) {
                $this->accountModel->setDefault($id);
            }
            header('Location: ?module=transactions');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'transaction') {
            $this->handleTransaction($_POST);
            $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
            // Only allow relative URLs to prevent open-redirect
            if ($redirectTo !== '' && strpos($redirectTo, '/') === 0) {
                header('Location: ' . $redirectTo);
            } else {
                header('Location: ?module=transactions');
            }
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'transaction_update') {
            $this->transactionModel->update($_POST);
            header('Location: ?module=transactions');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'transaction_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                // Delete companion fuel surcharge transactions first
                $this->transactionModel->deleteByReference('fuel_surcharge', $id);
                $this->transactionModel->deleteByReference('fuel_surcharge_refund', $id);
                $this->transactionModel->delete($id);
            }
            header('Location: ?module=transactions');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'transaction_import') {
            $result = $this->handleTransactionImport($_FILES['transaction_file'] ?? null);
            $query = http_build_query([
                'module' => 'transactions',
                'imported' => $result['imported'],
                'failed' => $result['failed'],
            ]);
            header('Location: ?' . $query);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'reward_redemption') {
            $this->handleRewardRedemption($_POST);
            header('Location: ?module=transactions');
            exit;
        }

        if (($_GET['action'] ?? '') === 'export') {
            $filters = $this->collectFilters();
            $rows = $this->transactionModel->getFiltered($filters);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="transactions.csv"');
            echo "Date,Type,Account,Category,Subcategory,Amount,Payment Method,Contact,Purchased From,Notes\n";
            foreach ($rows as $row) {
                $line = [
                    $row['transaction_date'],
                    ucfirst($row['transaction_type']),
                    $row['account_display'],
                    $row['category_name'] ?? 'Uncategorized',
                    $row['subcategory_name'] ?? '',
                    $row['amount'],
                    $row['payment_method_name'] ?? '',
                    $row['contact_name'] ?? '',
                    $row['purchase_source_name'] ?? '',
                    str_replace(['"', "\n"], ['""', ' '], $row['notes'] ?? ''),
                ];
                echo '"' . implode('","', $line) . '"' . "\n";
            }
            exit;
        }

        $editTransaction = null;
        if (!empty($_GET['edit'])) {
            $editTransaction = $this->transactionModel->getById((int) $_GET['edit']);
        }

        $filters = $this->collectFilters();
        $accounts = $this->accountModel->getList();
        $loans = $this->loanModel->getAll();
        $categories = $this->categoryModel->getAllWithSubcategories();
        $paymentMethods = $this->paymentMethodModel->getAll();
        $purchaseChildren = $this->purchaseSourceModel->getChildren();
        $creditCards = $this->creditCardModel->getAll();
        $openLendingRecords = $this->lendingModel->getOpenRecords();
        $rentalContracts = $this->rentalModel->getContracts();
        $rentalProperties = $this->rentalModel->getProperties();
        $rentalTenants = $this->rentalModel->getTenants();
        $investments = $this->investmentModel->getAll();
        $recentTransactions = $this->transactionModel->getFiltered($filters);
        $totalsByType = $this->transactionModel->getTotalsByType();

        return $this->render('transactions/index.php', [
            'accounts' => $accounts,
            'loans' => $loans,
            'categories' => $categories,
            'filters' => $filters,
            'paymentMethods' => $paymentMethods,
            'purchaseChildren' => $purchaseChildren,
            'creditCards' => $creditCards,
            'openLendingRecords' => $openLendingRecords,
            'rentalContracts' => $rentalContracts,
            'rentalProperties' => $rentalProperties,
            'rentalTenants' => $rentalTenants,
            'investments' => $investments,
            'recentTransactions' => $recentTransactions,
            'totalsByType' => $totalsByType,
            'imported' => isset($_GET['imported']) ? (int) $_GET['imported'] : null,
            'failed' => isset($_GET['failed']) ? (int) $_GET['failed'] : null,
            'editTransaction' => $editTransaction,
        ]);
    }

    private function handleTransactionImport($file): array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['imported' => 0, 'failed' => 1];
        }

        $tmpFile = (string) ($file['tmp_name'] ?? '');
        if ($tmpFile === '' || !is_uploaded_file($tmpFile)) {
            return ['imported' => 0, 'failed' => 1];
        }

        $handle = fopen($tmpFile, 'r');
        if ($handle === false) {
            return ['imported' => 0, 'failed' => 1];
        }

        $header = fgetcsv($handle);
        if (!is_array($header) || empty($header)) {
            fclose($handle);
            return ['imported' => 0, 'failed' => 1];
        }

        $columns = [];
        foreach ($header as $index => $name) {
            $columns[strtolower(trim((string) $name))] = $index;
        }

        $imported = 0;
        $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $input = $this->buildCsvTransactionInput($columns, $row);
            if ($input === null) {
                $failed++;
                continue;
            }

            $beforeCount = $this->transactionModel->countAll();
            $this->handleTransaction($input);
            $afterCount = $this->transactionModel->countAll();
            if ($afterCount > $beforeCount) {
                $imported++;
            } else {
                $failed++;
            }
        }

        fclose($handle);
        return ['imported' => $imported, 'failed' => $failed];
    }

    private function buildCsvTransactionInput(array $columns, array $row): ?array
    {
        $date = $this->csvValue($columns, $row, 'transaction_date');
        $type = strtolower((string) $this->csvValue($columns, $row, 'transaction_type'));
        $amountRaw = (string) $this->csvValue($columns, $row, 'amount');
        $amount = (float) str_replace([',', ' '], '', $amountRaw);

        $accountToken = (string) $this->csvValue($columns, $row, 'account_token');
        if ($accountToken === '') {
            $accountId = (string) $this->csvValue($columns, $row, 'account_id');
            $accountType = (string) $this->csvValue($columns, $row, 'account_type');
            if ($accountId !== '') {
                $accountToken = ($accountType !== '' ? $accountType : 'savings') . ':' . $accountId;
            }
        }

        if ($date === '' || $amount <= 0 || $accountToken === '' || !in_array($type, ['income', 'expense', 'transfer'], true)) {
            return null;
        }

        $input = [
            'form' => 'transaction',
            'transaction_date' => $date,
            'account_id' => $accountToken,
            'transaction_type' => $type,
            'amount' => $amount,
            'category_id' => $this->csvValue($columns, $row, 'category_id'),
            'subcategory_id' => $this->csvValue($columns, $row, 'subcategory_id'),
            'payment_method_id' => $this->csvValue($columns, $row, 'payment_method_id'),
            'new_payment_method' => $this->csvValue($columns, $row, 'payment_method_name'),
            'contact_id' => $this->csvValue($columns, $row, 'contact_id'),
            'purchase_parent_id' => $this->csvValue($columns, $row, 'purchase_parent_id'),
            'new_purchase_parent' => $this->csvValue($columns, $row, 'purchase_parent_name'),
            'purchase_source_id' => $this->csvValue($columns, $row, 'purchase_source_id'),
            'new_purchase_source' => $this->csvValue($columns, $row, 'purchase_source_name'),
            'notes' => $this->csvValue($columns, $row, 'notes'),
            'reference_type' => $this->csvValue($columns, $row, 'reference_type'),
            'reference_id' => $this->csvValue($columns, $row, 'reference_id'),
        ];

        if ($type === 'transfer') {
            $toToken = (string) $this->csvValue($columns, $row, 'transfer_to_account_token');
            if ($toToken === '') {
                $toId = (string) $this->csvValue($columns, $row, 'transfer_to_account_id');
                $toType = (string) $this->csvValue($columns, $row, 'transfer_to_account_type');
                if ($toId !== '') {
                    $toToken = ($toType !== '' ? $toType : 'savings') . ':' . $toId;
                }
            }
            $input['transfer_to_account_id'] = $toToken;
        }

        return $input;
    }

    private function csvValue(array $columns, array $row, string $name): string
    {
        if (!isset($columns[$name])) {
            return '';
        }
        $index = $columns[$name];
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function handleTransaction(array $input): void
    {
        $transactionType = $input['transaction_type'] ?? 'expense';
        $amount = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0;
        [$fromType, $fromId] = $this->parseAccountToken($input['account_id'] ?? '');

        if ($amount <= 0 || $fromId <= 0) {
            return;
        }

        // Resolve inline-created category/subcategory
        $resolvedCategoryId = $this->resolveCategoryId($input);
        if ($resolvedCategoryId !== null) {
            $input['category_id'] = $resolvedCategoryId;
        }
        $resolvedSubcategoryId = $this->resolveSubcategoryId($input, $resolvedCategoryId ?? (!empty($input['category_id']) ? (int)$input['category_id'] : null));
        if ($resolvedSubcategoryId !== null) {
            $input['subcategory_id'] = $resolvedSubcategoryId;
        }

        $paymentMethodId = $this->resolvePaymentMethodId($input);
        $contactId = !empty($input['contact_id']) ? (int) $input['contact_id'] : null;
        $purchaseSourceId = $this->resolvePurchaseSourceId($input);

        if ($transactionType === 'transfer') {
            $transferTarget = (string) ($input['transfer_target'] ?? 'account');
            if ($transferTarget === 'lending') {
                $this->handleTransferToLending($input, $fromType, $fromId);
                return;
            }
            if ($transferTarget === 'rental') {
                $this->handleTransferToRental($input, $fromType, $fromId);
                return;
            }
            if ($transferTarget === 'investment') {
                $this->handleTransferToInvestment($input, $fromType, $fromId);
                return;
            }

            [$toType, $toId] = $this->parseAccountToken($input['transfer_to_account_id'] ?? '');

            if ($toId > 0 && !($fromType === $toType && $fromId === $toId)) {
                $baseData = [
                    'transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
                    'category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
                    'subcategory_id' => !empty($input['subcategory_id']) ? (int) $input['subcategory_id'] : null,
                    'payment_method_id' => $paymentMethodId,
                    'contact_id' => $contactId,
                    'purchase_source_id' => $purchaseSourceId,
                    'notes' => $input['notes'] ?? 'Account transfer',
                    'reference_type' => 'transfer',
                ];

                $this->transactionModel->create(array_merge($baseData, [
                    'account_type' => $fromType,
                    'account_id' => $this->resolveTransactionAccountId($fromType, $fromId),
                    'transaction_type' => 'expense',
                    'amount' => $amount,
                    'reference_type' => $this->resolveReferenceType($fromType, 'transfer'),
                    'reference_id' => $this->resolveReferenceId($fromType, $fromId, $toId),
                ]));
                $this->applyDebtDelta($fromType, $fromId, 'expense', $amount);

                $this->transactionModel->create(array_merge($baseData, [
                    'account_type' => $toType,
                    'account_id' => $this->resolveTransactionAccountId($toType, $toId),
                    'transaction_type' => 'income',
                    'amount' => $amount,
                    'reference_type' => $this->resolveReferenceType($toType, 'transfer'),
                    'reference_id' => $this->resolveReferenceId($toType, $toId, $fromId),
                    'notes' => 'Transfer from account ' . $fromId,
                ]));
                $this->applyDebtDelta($toType, $toId, 'income', $amount);
            }
        } else {
            $isEmiPurchase = ($input['is_emi_purchase'] ?? 'no') === 'yes';
            $isCreditCardEmiExpense = $fromType === 'credit_card'
                && $transactionType === 'expense'
                && $isEmiPurchase
                && !empty($input['emi_name'])
                && !empty($input['emi_date'])
                && !empty($input['total_emis']);

            if ($isCreditCardEmiExpense) {
                $emiResult = $this->creditCardModel->createEmiPlanFromTransaction([
                    'account_id' => $fromId,
                    'plan_name' => $input['emi_name'] ?? '',
                    'principal_amount' => $amount,
                    'interest_rate' => $input['interest_rate'] ?? 0,
                    'total_emis' => $input['total_emis'] ?? 1,
                    'emi_date' => $input['emi_date'] ?? null,
                    'processing_fee' => $input['processing_fee'] ?? 0,
                    'gst_rate' => $input['gst_rate'] ?? 0,
                    'transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
                    'notes' => $input['notes'] ?? null,
                ]);

                if (!($emiResult['success'] ?? false)) {
                    return;
                }

                $this->transactionModel->create(array_merge($input, [
                    'account_type' => 'credit_card',
                    'account_id' => $fromId,
                    'transaction_type' => 'expense',
                    'payment_method_id' => $paymentMethodId,
                    'contact_id' => $contactId,
                    'purchase_source_id' => $purchaseSourceId,
                    'reference_type' => 'credit_card_emi_plan',
                    'reference_id' => (int) ($emiResult['plan_id'] ?? 0),
                ]));
                return;
            }

            $groupSpend = ($input['group_spend'] ?? 'no') === 'yes';
            $groupShare = is_numeric($input['group_share_amount'] ?? null) ? (float) $input['group_share_amount'] : 0.0;
            $groupSpendAllowed = in_array($fromType, ['savings', 'current', 'cash', 'wallet', 'other', 'credit_card'], true);
            if (
                $transactionType === 'expense'
                && $groupSpend
                && $groupSpendAllowed
                && $groupShare > 0
                && $groupShare < $amount
                && !empty($contactId)
            ) {
                $this->transactionModel->create(array_merge($input, [
                    'account_type' => $fromType,
                    'account_id' => $this->resolveTransactionAccountId($fromType, $fromId),
                    'transaction_type' => 'expense',
                    'amount' => $groupShare,
                    'payment_method_id' => $paymentMethodId,
                    'contact_id' => $contactId,
                    'purchase_source_id' => $purchaseSourceId,
                    'reference_type' => $this->resolveReferenceType($fromType, $input['reference_type'] ?? null),
                    'reference_id' => $this->resolveReferenceId($fromType, $fromId, !empty($input['reference_id']) ? (int) $input['reference_id'] : null),
                ]));
                $this->applyDebtDelta($fromType, $fromId, 'expense', $groupShare);

                $remainder = round($amount - $groupShare, 2);
                if ($remainder > 0) {
                    $notes = trim((string) ($input['notes'] ?? ''));
                    $this->lendingModel->create([
                        'contact_id' => $contactId,
                        'principal_amount' => $remainder,
                        'interest_rate' => 0,
                        'lending_date' => $input['transaction_date'] ?? date('Y-m-d'),
                        'funding_account' => (string) ($input['account_id'] ?? ''),
                        'notes' => $notes !== '' ? $notes . ' (Group spend split)' : 'Group spend split',
                    ]);
                }
                return;
            }

            $txId = $this->transactionModel->create(array_merge($input, [
                'account_type' => $fromType,
                'account_id' => $this->resolveTransactionAccountId($fromType, $fromId),
                'payment_method_id' => $paymentMethodId,
                'contact_id' => $contactId,
                'purchase_source_id' => $purchaseSourceId,
                'reference_type' => $this->resolveReferenceType($fromType, $input['reference_type'] ?? null),
                'reference_id' => $this->resolveReferenceId($fromType, $fromId, !empty($input['reference_id']) ? (int) $input['reference_id'] : null),
            ]));
            $this->applyDebtDelta($fromType, $fromId, $transactionType, $amount);

            // Auto-create fuel surcharge transactions if applicable
            if ($txId > 0 && $transactionType === 'expense' && $fromType === 'credit_card') {
                $this->applyFuelSurcharge($txId, $fromId, $amount, $input);
            }
        }
    }

    private function parseAccountToken(string $token): array
    {
        if (strpos($token, ':') === false) {
            return ['savings', (int) $token];
        }

        [$type, $id] = explode(':', $token, 2);
        $allowedTypes = ['savings', 'current', 'credit_card', 'cash', 'wallet', 'other', 'loan'];
        $normalizedType = in_array($type, $allowedTypes, true) ? $type : 'savings';
        return [$normalizedType, (int) $id];
    }

    private function applyFuelSurcharge(int $txId, int $accountId, float $amount, array $input): void
    {
        $categoryId = !empty($input['category_id']) ? (int) $input['category_id'] : 0;
        if ($categoryId <= 0) {
            return;
        }

        $category = $this->categoryModel->getCategoryById($categoryId);
        if (!$category || empty($category['is_fuel'])) {
            return;
        }

        $card = $this->creditCardModel->getByAccountId($accountId);
        if (!$card) {
            return;
        }

        $rate      = (float) ($card['fuel_surcharge_rate'] ?? 1.0);
        $minRefund = (float) ($card['fuel_surcharge_min_refund'] ?? 400.0);
        $surcharge = round($amount * $rate / 100, 2);
        $gst       = round($surcharge * 0.18, 2);
        $total     = round($surcharge + $gst, 2);

        if ($total <= 0) {
            return;
        }

        $date = $input['transaction_date'] ?? date('Y-m-d');

        // Surcharge expense (surcharge + GST)
        $this->transactionModel->create([
            'transaction_date' => $date,
            'account_type'     => 'credit_card',
            'account_id'       => $accountId,
            'transaction_type' => 'expense',
            'amount'           => $total,
            'reference_type'   => 'fuel_surcharge',
            'reference_id'     => $txId,
            'notes'            => 'Fuel surcharge: ' . $rate . '% + 18% GST = ' . $total,
        ]);

        // Refund income (surcharge only, GST not refunded) if spend >= min
        if ($amount >= $minRefund && $surcharge > 0) {
            $this->transactionModel->create([
                'transaction_date' => $date,
                'account_type'     => 'credit_card',
                'account_id'       => $accountId,
                'transaction_type' => 'income',
                'amount'           => $surcharge,
                'reference_type'   => 'fuel_surcharge_refund',
                'reference_id'     => $txId,
                'notes'            => 'Fuel surcharge refund: ' . $rate . '% of ' . $amount,
            ]);
        }
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

    private function resolveTransactionAccountId(string $accountType, int $accountId): ?int
    {
        if ($accountType === 'loan') {
            return null;
        }

        return $accountId > 0 ? $accountId : null;
    }

    private function resolveReferenceType(string $accountType, ?string $defaultType): ?string
    {
        if ($accountType === 'loan') {
            return 'loan';
        }

        return $defaultType;
    }

    private function resolveReferenceId(string $accountType, int $accountId, ?int $fallbackId): ?int
    {
        if ($accountType === 'loan') {
            return $accountId > 0 ? $accountId : null;
        }

        return $fallbackId;
    }

    private function resolvePaymentMethodId(array $input): ?int
    {
        $paymentMethodId = !empty($input['payment_method_id']) ? (int) $input['payment_method_id'] : 0;
        if ($paymentMethodId > 0) {
            return $paymentMethodId;
        }

        $customName = trim((string) ($input['new_payment_method'] ?? ''));
        if ($customName === '') {
            return null;
        }

        return $this->paymentMethodModel->findOrCreate($customName);
    }

    private function resolvePurchaseSourceId(array $input): ?int
    {
        $sourceId = !empty($input['purchase_source_id']) ? (int) $input['purchase_source_id'] : 0;
        if ($sourceId > 0) {
            return $sourceId;
        }

        $customChild = trim((string) ($input['new_purchase_source'] ?? ''));
        if ($customChild === '') {
            return null;
        }

        $parentId = !empty($input['purchase_parent_id']) ? (int) $input['purchase_parent_id'] : 0;
        if ($parentId <= 0) {
            $customParent = trim((string) ($input['new_purchase_parent'] ?? ''));
            if ($customParent !== '') {
                $parentId = (int) ($this->purchaseSourceModel->findOrCreateParent($customParent) ?? 0);
            }
        }
        if ($parentId <= 0) {
            $parentId = (int) ($this->purchaseSourceModel->findOrCreateParent('Other') ?? 0);
        }
        if ($parentId <= 0) {
            return null;
        }

        return $this->purchaseSourceModel->findOrCreateChild($parentId, $customChild);
    }

    private function resolveCategoryId(array $input): ?int
    {
        $categoryId = !empty($input['category_id']) ? (int) $input['category_id'] : 0;
        if ($categoryId > 0) {
            return $categoryId;
        }

        $newName = trim((string) ($input['new_category_name'] ?? ''));
        if ($newName === '') {
            return null;
        }

        $type = (string) ($input['new_category_type'] ?? 'expense');
        if (!in_array($type, ['income', 'expense', 'transfer'], true)) {
            $type = 'expense';
        }

        $newId = $this->categoryModel->createCategory($newName, $type);
        return $newId > 0 ? $newId : null;
    }

    private function resolveSubcategoryId(array $input, ?int $categoryId): ?int
    {
        $subcategoryId = !empty($input['subcategory_id']) ? (int) $input['subcategory_id'] : 0;
        if ($subcategoryId > 0) {
            return $subcategoryId;
        }

        $newName = trim((string) ($input['new_subcategory_name'] ?? ''));
        if ($newName === '' || !$categoryId) {
            return null;
        }

        $newId = $this->categoryModel->createSubcategory($categoryId, $newName);
        return $newId > 0 ? $newId : null;
    }

    private function handleRewardRedemption(array $input): bool
    {
        $cardId = (int) ($input['credit_card_id'] ?? 0);
        $points = max(0.0, (float) ($input['points_redeemed'] ?? 0));
        $rate = max(0.0, (float) ($input['rate_per_point'] ?? 0));
        $cashValue = is_numeric($input['cash_value'] ?? null)
            ? max(0.0, (float) $input['cash_value'])
            : round($points * $rate, 2);
        $date = !empty($input['redemption_date']) ? (string) $input['redemption_date'] : date('Y-m-d');
        $depositToken = (string) ($input['deposit_account_id'] ?? '');
        [$depositType, $depositId] = $this->parseAccountToken($depositToken);
        $allowedDepositTypes = ['savings', 'current', 'cash', 'wallet', 'other'];

        if ($cardId <= 0 || $points <= 0 || $cashValue <= 0 || $depositId <= 0 || !in_array($depositType, $allowedDepositTypes, true)) {
            return false;
        }

        $card = $this->creditCardModel->getById($cardId);
        if (!$card) {
            return false;
        }
        $balance = (float) ($card['points_balance'] ?? 0.0);
        if ($points > $balance) {
            return false;
        }

        $categoryId = !empty($input['category_id']) ? (int) $input['category_id'] : null;
        $subcategoryId = !empty($input['subcategory_id']) ? (int) $input['subcategory_id'] : null;
        $notes = trim((string) ($input['notes'] ?? ''));
        $noteLabel = 'Reward points redemption - ' . ($card['card_name'] ?? 'Card');
        $finalNotes = $notes !== '' ? $notes . ' | ' . $noteLabel : $noteLabel;

        $pdo = $this->database->connect();
        $pdo->beginTransaction();
        try {
            $created = $this->transactionModel->create([
                'transaction_date' => $date,
                'account_type' => $depositType,
                'account_id' => $depositId,
                'transaction_type' => 'income',
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'amount' => $cashValue,
                'reference_type' => 'credit_card_reward',
                'reference_id' => $cardId,
                'notes' => $finalNotes,
            ]);
            if (!$created) {
                throw new \RuntimeException('Failed to create redemption transaction.');
            }

            $transactionId = (int) $pdo->lastInsertId();
            $saved = $this->rewardModel->createRedemptionRecord([
                'credit_card_id' => $cardId,
                'points_redeemed' => $points,
                'rate_per_point' => $rate,
                'cash_value' => $cashValue,
                'redemption_date' => $date,
                'deposit_account_id' => $depositId,
                'deposit_account_type' => $depositType,
                'transaction_id' => $transactionId,
                'notes' => $notes !== '' ? $notes : null,
            ]);
            if (!$saved) {
                throw new \RuntimeException('Failed to save redemption record.');
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    private function handleTransferToLending(array $input, string $fromType, int $fromId): void
    {
        $mode = (string) ($input['lending_mode'] ?? 'new');
        $fundingToken = ($fromId > 0 ? $fromType . ':' . $fromId : '');
        $amount = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0;
        $date = $input['transaction_date'] ?? date('Y-m-d');

        if ($mode === 'repayment') {
            $this->lendingModel->recordRepayment([
                'lending_record_id' => $input['lending_record_id'] ?? null,
                'repayment_amount' => $amount,
                'repayment_date' => $date,
                'deposit_account' => $fundingToken,
                'notes' => $input['lending_notes'] ?? null,
            ]);
            return;
        }

        if ($mode === 'topup') {
            $this->lendingModel->topUp([
                'lending_record_id' => $input['lending_record_id'] ?? null,
                'amount' => $amount,
                'topup_date' => $date,
                'funding_account' => $fundingToken,
                'notes' => $input['lending_notes'] ?? null,
            ]);
            return;
        }

        $this->lendingModel->create([
            'contact_id' => !empty($input['contact_id']) ? (int) $input['contact_id'] : null,
            'principal_amount' => $amount,
            'interest_rate' => $input['lending_interest_rate'] ?? null,
            'lending_date' => $date,
            'funding_account' => $fundingToken,
            'due_date' => !empty($input['lending_due_date']) ? $input['lending_due_date'] : null,
            'total_repaid' => 0,
            'status' => 'ongoing',
            'notes' => $input['lending_notes'] ?? null,
        ]);
    }

    private function handleTransferToRental(array $input, string $fromType, int $fromId): void
    {
        $fundingToken = ($fromId > 0 ? $fromType . ':' . $fromId : '');
        $mode = (string) ($input['rental_mode'] ?? 'existing');
        $amount = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0;
        $date = $input['transaction_date'] ?? date('Y-m-d');

        $paymentData = [
            'rent_month' => $input['rental_rent_month'] ?? date('Y-m-01'),
            'due_date' => !empty($input['rental_due_date']) ? $input['rental_due_date'] : $date,
            'paid_amount' => $amount,
            'deposit_account' => $fundingToken,
            'payment_status' => $input['rental_status'] ?? 'paid',
            'notes' => $input['rental_notes'] ?? null,
        ];

        if ($mode === 'new_contract') {
            $propertyId = (int) ($input['rental_property_id'] ?? 0);
            $tenantId = (int) ($input['rental_tenant_id'] ?? 0);
            if ($propertyId > 0 && $tenantId > 0) {
                $contractId = $this->rentalModel->createContractWithId([
                    'property_id' => $propertyId,
                    'tenant_id' => $tenantId,
                    'start_date' => $input['rental_contract_start'] ?? $date,
                    'end_date' => $input['rental_contract_end'] ?? null,
                    'rent_amount' => $input['rental_contract_rent'] ?? $amount,
                    'deposit_amount' => $input['rental_contract_deposit'] ?? 0,
                ]);
                if ($contractId > 0) {
                    $this->rentalModel->recordPayment(array_merge($paymentData, ['contract_id' => $contractId]));
                }
            }
            return;
        }

        $this->rentalModel->recordPayment(array_merge($paymentData, [
            'contract_id' => $input['rental_contract_id'] ?? null,
        ]));
    }

    private function handleTransferToInvestment(array $input, string $fromType, int $fromId): void
    {
        $fundingToken = ($fromId > 0 ? $fromType . ':' . $fromId : '');
        $mode = (string) ($input['investment_mode'] ?? 'existing');
        $investmentId = (int) ($input['investment_id'] ?? 0);

        if ($mode === 'new') {
            $investmentId = $this->investmentModel->createWithId([
                'type' => $input['investment_type'] ?? null,
                'name' => $input['investment_name'] ?? null,
                'notes' => $input['investment_notes'] ?? null,
            ]);
        }

        if ($investmentId <= 0) {
            return;
        }

        $this->investmentModel->createTransactionWithLedger([
            'investment_id' => $investmentId,
            'transaction_type' => $input['investment_tx_type'] ?? 'buy',
            'amount' => is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0,
            'units' => $input['investment_units'] ?? null,
            'transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
            'notes' => $input['investment_tx_notes'] ?? null,
        ], $fundingToken);
    }

    private function collectFilters(): array
    {
        $start = isset($_GET['start_date']) ? trim((string) $_GET['start_date']) : '';
        $end = isset($_GET['end_date']) ? trim((string) $_GET['end_date']) : '';

        return [
            'account_id' => !empty($_GET['account_id']) ? (int) $_GET['account_id'] : null,
            'category_id' => $_GET['category_id'] === 'uncategorized' ? 'uncategorized' : (!empty($_GET['category_id']) ? (int) $_GET['category_id'] : null),
            'subcategory_id' => $_GET['subcategory_id'] === 'unspecified' ? 'unspecified' : (!empty($_GET['subcategory_id']) ? (int) $_GET['subcategory_id'] : null),
            'start_date' => $start !== '' ? $start : null,
            'end_date' => $end !== '' ? $end : null,
        ];
    }
}
