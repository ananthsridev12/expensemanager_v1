<?php

namespace Models;

use PDO;

class Transaction extends BaseModel
{
    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM transactions');

        return (int) $stmt->fetchColumn();
    }

    public function getRecent(int $limit = 5): array
    {
        $sql = <<<SQL
SELECT
    t.*,
    CASE
        WHEN t.account_type = 'loan' THEN 'Loan'
        WHEN t.account_type = 'lending' THEN 'Lending'
        WHEN t.account_type = 'rental' THEN 'Rental'
        WHEN a.account_type = 'credit_card' THEN COALESCE(cc.bank_name, a.bank_name)
        ELSE a.bank_name
    END AS bank_name,
    CASE
        WHEN t.account_type = 'loan' THEN l.loan_name
        WHEN t.account_type = 'lending' THEN ct.name
        WHEN t.account_type = 'rental' THEN CONCAT(COALESCE(rt_tenant.name, 'Tenant'), ' / ', COALESCE(rt_property.property_name, 'Property'))
        WHEN a.account_type = 'credit_card' THEN COALESCE(cc.card_name, a.account_name)
        ELSE a.account_name
    END AS account_name,
    pm.name AS payment_method_name,
    ct_tx.name AS contact_name,
    ps_parent.name AS purchase_parent_name,
    ps_child.name AS purchase_source_name,
    c.name AS category_name,
    sc.name AS subcategory_name
FROM transactions t
LEFT JOIN categories c ON c.id = t.category_id
LEFT JOIN subcategories sc ON sc.id = t.subcategory_id
LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
LEFT JOIN contacts ct_tx ON ct_tx.id = t.contact_id
LEFT JOIN purchase_sources ps_child ON ps_child.id = t.purchase_source_id
LEFT JOIN purchase_sources ps_parent ON ps_parent.id = ps_child.parent_id
LEFT JOIN accounts a ON a.id = t.account_id
LEFT JOIN credit_cards cc ON cc.account_id = a.id
LEFT JOIN loans l ON l.id = CASE
    WHEN t.account_type = 'loan' AND t.reference_type = 'loan' THEN t.reference_id
    ELSE NULL
END
LEFT JOIN lending_records lr ON lr.id = CASE
    WHEN t.reference_type = 'lending' THEN t.reference_id
    ELSE NULL
END
LEFT JOIN contacts ct ON ct.id = lr.contact_id
LEFT JOIN rental_transactions rt ON rt.id = CASE
    WHEN t.reference_type = 'rental' THEN t.reference_id
    ELSE NULL
END
LEFT JOIN rental_contracts rtc ON rtc.id = rt.contract_id
LEFT JOIN tenants rt_tenant ON rt_tenant.id = rtc.tenant_id
LEFT JOIN properties rt_property ON rt_property.id = rtc.property_id
ORDER BY t.transaction_date DESC, t.created_at DESC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getFiltered(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['account_id'])) {
            $where[] = 't.account_id = :account_id';
            $params[':account_id'] = $filters['account_id'];
        }
        if (($filters['category_id'] ?? null) === 'uncategorized') {
            $where[] = 't.category_id IS NULL';
        } elseif (!empty($filters['category_id'])) {
            $where[] = 't.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }
        if (($filters['subcategory_id'] ?? null) === 'unspecified') {
            $where[] = 't.subcategory_id IS NULL';
        } elseif (!empty($filters['subcategory_id'])) {
            $where[] = 't.subcategory_id = :subcategory_id';
            $params[':subcategory_id'] = $filters['subcategory_id'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 't.transaction_date BETWEEN :start_date AND :end_date';
            $params[':start_date'] = $filters['start_date'];
            $params[':end_date'] = $filters['end_date'];
        } elseif (!empty($filters['start_date'])) {
            $where[] = 't.transaction_date >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        } elseif (!empty($filters['end_date'])) {
            $where[] = 't.transaction_date <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }

        $sql = <<<SQL
SELECT
    t.id,
    t.transaction_date,
    t.transaction_type,
    t.account_type,
    t.amount,
    t.notes,
    c.name AS category_name,
    sc.name AS subcategory_name,
    CASE
        WHEN t.account_type = 'loan' THEN CONCAT('Loan - ', l.loan_name)
        WHEN t.account_type = 'lending' THEN CONCAT('Lending - ', ct.name)
        WHEN t.account_type = 'rental' THEN 'Rental'
        ELSE CONCAT(COALESCE(a.bank_name, '-'), ' - ', COALESCE(a.account_name, '-'))
    END AS account_display,
    pm.name AS payment_method_name,
    ct_tx.name AS contact_name,
    COALESCE(ps_child.name, ps_parent.name, '') AS purchase_source_name
FROM transactions t
LEFT JOIN categories c ON c.id = t.category_id
LEFT JOIN subcategories sc ON sc.id = t.subcategory_id
LEFT JOIN accounts a ON a.id = t.account_id
LEFT JOIN credit_cards cc ON cc.account_id = a.id
LEFT JOIN loans l ON l.id = CASE
    WHEN t.account_type = 'loan' AND t.reference_type = 'loan' THEN t.reference_id
    ELSE NULL
END
LEFT JOIN lending_records lr ON lr.id = CASE
    WHEN t.reference_type = 'lending' THEN t.reference_id
    ELSE NULL
END
LEFT JOIN contacts ct ON ct.id = lr.contact_id
LEFT JOIN rental_transactions rt ON rt.id = CASE
    WHEN t.reference_type = 'rental' THEN t.reference_id
    ELSE NULL
END
LEFT JOIN rental_contracts rtc ON rtc.id = rt.contract_id
LEFT JOIN tenants rt_tenant ON rt_tenant.id = rtc.tenant_id
LEFT JOIN properties rt_property ON rt_property.id = rtc.property_id
LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
LEFT JOIN contacts ct_tx ON ct_tx.id = t.contact_id
LEFT JOIN purchase_sources ps_child ON ps_child.id = t.purchase_source_id
LEFT JOIN purchase_sources ps_parent ON ps_parent.id = ps_child.parent_id
WHERE
    %s
ORDER BY t.transaction_date DESC, t.created_at DESC
SQL;

        $stmt = $this->db->prepare(sprintf($sql, implode(' AND ', $where)));
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $input): int
    {
        $sql = 'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, category_id, subcategory_id, payment_method_id, contact_id, purchase_source_id, amount, reference_type, reference_id, notes) VALUES (:transaction_date, :account_type, :account_id, :transaction_type, :category_id, :subcategory_id, :payment_method_id, :contact_id, :purchase_source_id, :amount, :reference_type, :reference_id, :notes)';
        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([
            ':transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
            ':account_type' => $input['account_type'] ?? 'bank',
            ':account_id' => !empty($input['account_id']) ? (int) $input['account_id'] : null,
            ':transaction_type' => $input['transaction_type'] ?? 'expense',
            ':category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
            ':subcategory_id' => !empty($input['subcategory_id']) ? (int) $input['subcategory_id'] : null,
            ':payment_method_id' => !empty($input['payment_method_id']) ? (int) $input['payment_method_id'] : null,
            ':contact_id' => !empty($input['contact_id']) ? (int) $input['contact_id'] : null,
            ':purchase_source_id' => !empty($input['purchase_source_id']) ? (int) $input['purchase_source_id'] : null,
            ':amount' => is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.00,
            ':reference_type' => $input['reference_type'] ?? null,
            ':reference_id' => !empty($input['reference_id']) ? (int) $input['reference_id'] : null,
            ':notes' => $input['notes'] ?? null,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM transactions WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function deleteByReference(string $refType, int $refId): void
    {
        $stmt = $this->db->prepare('DELETE FROM transactions WHERE reference_type = :ref_type AND reference_id = :ref_id');
        $stmt->execute([':ref_type' => $refType, ':ref_id' => $refId]);
    }

    public function referenceExists(string $refType, int $refId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM transactions WHERE reference_type = :ref_type AND reference_id = :ref_id LIMIT 1');
        $stmt->execute([':ref_type' => $refType, ':ref_id' => $refId]);
        return (bool) $stmt->fetchColumn();
    }

    public function getTotalsByType(): array
    {
        $stmt = $this->db->query('SELECT transaction_type, SUM(amount) AS total FROM transactions GROUP BY transaction_type');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['transaction_type']] = (float) $row['total'];
        }

        return $result;
    }

    public function getById(int $id): ?array
    {
        $sql = <<<SQL
SELECT
    t.*,
    c.name AS category_name,
    sc.name AS subcategory_name
FROM transactions t
LEFT JOIN categories c ON c.id = t.category_id
LEFT JOIN subcategories sc ON sc.id = t.subcategory_id
WHERE t.id = :id
LIMIT 1
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $stmt = $this->db->prepare(
            'UPDATE transactions SET transaction_date=:transaction_date, amount=:amount, category_id=:category_id, subcategory_id=:subcategory_id, payment_method_id=:payment_method_id, contact_id=:contact_id, notes=:notes WHERE id=:id'
        );
        return $stmt->execute([
            ':transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
            ':amount' => is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.00,
            ':category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
            ':subcategory_id' => !empty($input['subcategory_id']) ? (int) $input['subcategory_id'] : null,
            ':payment_method_id' => !empty($input['payment_method_id']) ? (int) $input['payment_method_id'] : null,
            ':contact_id' => !empty($input['contact_id']) ? (int) $input['contact_id'] : null,
            ':notes' => $input['notes'] ?? null,
            ':id' => $id,
        ]);
    }
}
