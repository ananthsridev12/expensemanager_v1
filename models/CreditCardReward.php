<?php

namespace Models;

use PDO;

class CreditCardReward extends BaseModel
{
    public function getCards(): array
    {
        $sql = <<<SQL
SELECT
    cc.id,
    cc.bank_name,
    cc.card_name,
    cc.points_balance,
    cc.account_id
FROM credit_cards cc
ORDER BY cc.created_at DESC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createRedemption(array $input, bool $useTransaction = true): bool
    {
        $cardId = (int) ($input['credit_card_id'] ?? 0);
        $points = max(0.0, (float) ($input['points_redeemed'] ?? 0));
        $rate = max(0.0, (float) ($input['rate_per_point'] ?? 0));
        $cashValue = max(0.0, (float) ($input['cash_value'] ?? ($points * $rate)));
        $date = $input['redemption_date'] ?? date('Y-m-d');
        $depositAccountId = !empty($input['deposit_account_id']) ? (int) $input['deposit_account_id'] : null;
        $depositAccountType = $input['deposit_account_type'] ?? 'savings';
        $transactionId = !empty($input['transaction_id']) ? (int) $input['transaction_id'] : null;
        $notes = $input['notes'] ?? null;

        if ($cardId <= 0 || $points <= 0 || $cashValue <= 0 || $depositAccountId === null) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT points_balance FROM credit_cards WHERE id = :id');
        $stmt->execute([':id' => $cardId]);
        $balance = (float) ($stmt->fetchColumn() ?? 0.0);
        if ($points > $balance) {
            return false;
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $inserted = $this->createRedemptionRecord([
                'credit_card_id' => $cardId,
                'points_redeemed' => $points,
                'rate_per_point' => $rate,
                'cash_value' => $cashValue,
                'redemption_date' => $date,
                'deposit_account_id' => $depositAccountId,
                'deposit_account_type' => $depositAccountType,
                'transaction_id' => $transactionId,
                'notes' => $notes,
            ]);

            if (!$inserted) {
                throw new \RuntimeException('Failed to insert reward redemption.');
            }

            if ($useTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (\Throwable $exception) {
            if ($useTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function createRedemptionRecord(array $input): bool
    {
        $insert = $this->db->prepare(
            'INSERT INTO credit_card_rewards
             (credit_card_id, points_redeemed, rate_per_point, cash_value, redemption_date, deposit_account_id, deposit_account_type, transaction_id, notes)
             VALUES
             (:credit_card_id, :points_redeemed, :rate_per_point, :cash_value, :redemption_date, :deposit_account_id, :deposit_account_type, :transaction_id, :notes)'
        );
        $inserted = $insert->execute([
            ':credit_card_id' => (int) $input['credit_card_id'],
            ':points_redeemed' => (float) $input['points_redeemed'],
            ':rate_per_point' => (float) $input['rate_per_point'],
            ':cash_value' => (float) $input['cash_value'],
            ':redemption_date' => $input['redemption_date'],
            ':deposit_account_id' => (int) $input['deposit_account_id'],
            ':deposit_account_type' => (string) $input['deposit_account_type'],
            ':transaction_id' => !empty($input['transaction_id']) ? (int) $input['transaction_id'] : null,
            ':notes' => $input['notes'] ?? null,
        ]);

        if (!$inserted) {
            return false;
        }

        $update = $this->db->prepare(
            'UPDATE credit_cards
             SET points_balance = GREATEST(0, points_balance - :points)
             WHERE id = :id'
        );
        return $update->execute([
            ':points' => (float) $input['points_redeemed'],
            ':id' => (int) $input['credit_card_id'],
        ]);
    }
}
