<?php

namespace Models;

use PDO;

class Rental extends BaseModel
{
    public function createProperty(array $input): bool
    {
        $sql = 'INSERT INTO properties (property_name, address, monthly_rent, security_deposit) VALUES (:property_name, :address, :monthly_rent, :security_deposit)';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':property_name' => trim($input['property_name'] ?? 'Untitled Property'),
            ':address' => $input['address'] ?? null,
            ':monthly_rent' => (float) ($input['monthly_rent'] ?? 0),
            ':security_deposit' => (float) ($input['security_deposit'] ?? 0),
        ]);
    }

    public function createTenant(array $input): bool
    {
        $contactId = (int) ($input['contact_id'] ?? 0);
        if ($contactId <= 0) {
            return false;
        }

        $existing = $this->db->prepare('SELECT id FROM tenants WHERE contact_id = :contact_id LIMIT 1');
        $existing->execute([':contact_id' => $contactId]);
        if ($existing->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }

        $contactStmt = $this->db->prepare('SELECT name, mobile, email, address FROM contacts WHERE id = :id LIMIT 1');
        $contactStmt->execute([':id' => $contactId]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            return false;
        }

        $sql = 'INSERT INTO tenants (contact_id, name, mobile, email, id_proof, address) VALUES (:contact_id, :name, :mobile, :email, :id_proof, :address)';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':contact_id' => $contactId,
            ':name' => trim((string) ($contact['name'] ?? 'Tenant')),
            ':mobile' => $contact['mobile'] ?? null,
            ':email' => $contact['email'] ?? null,
            ':id_proof' => $input['tenant_id_proof'] ?? null,
            ':address' => $contact['address'] ?? null,
        ]);
    }

    public function createContract(array $input): bool
    {
        $sql = 'INSERT INTO rental_contracts (property_id, tenant_id, start_date, end_date, deposit_amount, rent_amount) VALUES (:property_id, :tenant_id, :start_date, :end_date, :deposit_amount, :rent_amount)';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':property_id' => (int) ($input['property_id'] ?? 0),
            ':tenant_id' => (int) ($input['tenant_id'] ?? 0),
            ':start_date' => $input['start_date'] ?? date('Y-m-d'),
            ':end_date' => $input['end_date'] ?? null,
            ':deposit_amount' => (float) ($input['deposit_amount'] ?? 0),
            ':rent_amount' => (float) ($input['rent_amount'] ?? 0),
        ]);
    }

    public function createContractWithId(array $input): int
    {
        $sql = 'INSERT INTO rental_contracts (property_id, tenant_id, start_date, end_date, deposit_amount, rent_amount) VALUES (:property_id, :tenant_id, :start_date, :end_date, :deposit_amount, :rent_amount)';
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':property_id' => (int) ($input['property_id'] ?? 0),
            ':tenant_id' => (int) ($input['tenant_id'] ?? 0),
            ':start_date' => $input['start_date'] ?? date('Y-m-d'),
            ':end_date' => $input['end_date'] ?? null,
            ':deposit_amount' => (float) ($input['deposit_amount'] ?? 0),
            ':rent_amount' => (float) ($input['rent_amount'] ?? 0),
        ]);
        return $result ? (int) $this->db->lastInsertId() : 0;
    }

    public function recordPayment(array $input): bool
    {
        $sql = 'INSERT INTO rental_transactions (contract_id, rent_month, due_date, paid_amount, payment_status, notes) VALUES (:contract_id, :rent_month, :due_date, :paid_amount, :payment_status, :notes)';
        $stmt = $this->db->prepare($sql);

        $created = $stmt->execute([
            ':contract_id' => (int) ($input['contract_id'] ?? 0),
            ':rent_month' => $input['rent_month'] ?? date('Y-m-01'),
            ':due_date' => $input['due_date'] ?? date('Y-m-d'),
            ':paid_amount' => (float) ($input['paid_amount'] ?? 0),
            ':payment_status' => $input['payment_status'] ?? 'pending',
            ':notes' => $input['notes'] ?? null,
        ]);

        if (!$created) {
            return false;
        }

        $rentalTxId = (int) $this->db->lastInsertId();
        $this->createLedgerTransactions(
            $rentalTxId,
            (int) ($input['contract_id'] ?? 0),
            (float) ($input['paid_amount'] ?? 0),
            (string) ($input['due_date'] ?? date('Y-m-d')),
            (string) ($input['deposit_account'] ?? ''),
            (string) ($input['notes'] ?? '')
        );
        return true;
    }

    public function getProperties(): array
    {
        $stmt = $this->db->query('SELECT * FROM properties ORDER BY created_at DESC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTenants(): array
    {
        $stmt = $this->db->query('SELECT * FROM tenants ORDER BY created_at DESC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getContacts(): array
    {
        $sql = "SELECT id, name, mobile, email FROM contacts WHERE contact_type IN ('tenant', 'both', 'other') ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getContracts(): array
    {
        $sql = <<<SQL
SELECT
    rc.*, p.property_name, t.name AS tenant_name
FROM rental_contracts rc
LEFT JOIN properties p ON p.id = rc.property_id
LEFT JOIN tenants t ON t.id = rc.tenant_id
ORDER BY rc.start_date DESC
SQL;
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTransactions(): array
    {
        $sql = <<<SQL
SELECT
    rt.*, rc.property_id, p.property_name, t.name AS tenant_name
FROM rental_transactions rt
LEFT JOIN rental_contracts rc ON rc.id = rt.contract_id
LEFT JOIN properties p ON p.id = rc.property_id
LEFT JOIN tenants t ON t.id = rc.tenant_id
ORDER BY rt.due_date DESC
SQL;
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(): array
    {
        $sql = 'SELECT (SELECT COUNT(*) FROM properties) AS properties, (SELECT COUNT(*) FROM tenants) AS tenants, (SELECT COUNT(*) FROM rental_contracts) AS contracts';
        $stmt = $this->db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'properties' => (int) $row['properties'],
            'tenants' => (int) $row['tenants'],
            'contracts' => (int) $row['contracts'],
        ];
    }

    public function getUpcomingRent(int $limit = 5): array
    {
        $sql = <<<SQL
SELECT
    rt.*, p.property_name, t.name AS tenant_name
FROM rental_transactions rt
LEFT JOIN rental_contracts rc ON rc.id = rt.contract_id
LEFT JOIN properties p ON p.id = rc.property_id
LEFT JOIN tenants t ON t.id = rc.tenant_id
WHERE rt.payment_status IN ('pending','partial','overdue')
ORDER BY rt.due_date ASC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPropertyById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getTenantById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateProperty(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $name = trim((string) ($input['property_name'] ?? ''));
        if ($name === '') return false;
        $stmt = $this->db->prepare(
            'UPDATE properties SET property_name=:property_name, address=:address, monthly_rent=:monthly_rent, security_deposit=:security_deposit WHERE id=:id'
        );
        return $stmt->execute([
            ':property_name' => $name,
            ':address' => $input['address'] ?? null,
            ':monthly_rent' => (float) ($input['monthly_rent'] ?? 0),
            ':security_deposit' => (float) ($input['security_deposit'] ?? 0),
            ':id' => $id,
        ]);
    }

    public function updateTenant(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') return false;
        $stmt = $this->db->prepare(
            'UPDATE tenants SET name=:name, mobile=:mobile, email=:email, address=:address WHERE id=:id'
        );
        return $stmt->execute([
            ':name' => $name,
            ':mobile' => $input['mobile'] ?? null,
            ':email' => $input['email'] ?? null,
            ':address' => $input['address'] ?? null,
            ':id' => $id,
        ]);
    }

    private function createLedgerTransactions(
        int $rentalTransactionId,
        int $contractId,
        float $paidAmount,
        string $paymentDate,
        string $depositAccount,
        string $notes
    ): void {
        if ($rentalTransactionId <= 0 || $contractId <= 0 || $paidAmount <= 0 || $depositAccount === '' || strpos($depositAccount, ':') === false) {
            return;
        }

        [$accountType, $accountIdRaw] = explode(':', $depositAccount, 2);
        $accountId = (int) $accountIdRaw;
        $allowedTypes = ['savings', 'current', 'cash', 'other'];
        if ($accountId <= 0 || !in_array($accountType, $allowedTypes, true)) {
            return;
        }

        $contractStmt = $this->db->prepare(
            'SELECT p.property_name, t.name AS tenant_name
             FROM rental_contracts rc
             LEFT JOIN properties p ON p.id = rc.property_id
             LEFT JOIN tenants t ON t.id = rc.tenant_id
             WHERE rc.id = :id
             LIMIT 1'
        );
        $contractStmt->execute([':id' => $contractId]);
        $contract = $contractStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $entryNote = $notes !== '' ? $notes : ('Rent received: ' . ($contract['property_name'] ?? 'Property') . ' / ' . ($contract['tenant_name'] ?? 'Tenant'));

        $stmt = $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, amount, reference_type, reference_id, notes)
             VALUES (:transaction_date, :account_type, :account_id, :transaction_type, :amount, :reference_type, :reference_id, :notes)'
        );

        // Money received into selected account.
        $stmt->execute([
            ':transaction_date' => $paymentDate,
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':transaction_type' => 'income',
            ':amount' => $paidAmount,
            ':reference_type' => 'rental',
            ':reference_id' => $rentalTransactionId,
            ':notes' => $entryNote,
        ]);

        // Rental income stream entry.
        $stmt->execute([
            ':transaction_date' => $paymentDate,
            ':account_type' => 'rental',
            ':account_id' => null,
            ':transaction_type' => 'expense',
            ':amount' => $paidAmount,
            ':reference_type' => 'rental',
            ':reference_id' => $rentalTransactionId,
            ':notes' => $entryNote,
        ]);
    }
}
