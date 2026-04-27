<?php

namespace Controllers;

use Models\Analytics;
use Models\Transaction;

class CalendarController extends BaseController
{
    public function index(): string
    {
        if (isset($_GET['action']) && $_GET['action'] === 'day_detail') {
            return $this->dayDetail();
        }

        $year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
        $month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
        $month = max(1, min(12, $month));
        $year  = max(2000, min(2100, $year));

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $analyticsModel = new Analytics($this->database);
        $dailyData      = $analyticsModel->getDailyNetFlow($startDate, $endDate);

        $monthIncome  = array_sum(array_column($dailyData, 'total_income'));
        $monthExpense = array_sum(array_column($dailyData, 'total_expense'));

        return $this->render('calendar/index.php', [
            'year'         => $year,
            'month'        => $month,
            'startDate'    => $startDate,
            'endDate'      => $endDate,
            'dailyData'    => $dailyData,
            'monthIncome'  => $monthIncome,
            'monthExpense' => $monthExpense,
        ]);
    }

    private function dayDetail(): string
    {
        $raw  = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : date('Y-m-d');

        $txModel = new Transaction($this->database);
        $rows    = $txModel->getFiltered(['start_date' => $date, 'end_date' => $date]);

        $income  = 0.0;
        $expense = 0.0;
        foreach ($rows as $r) {
            if ($r['transaction_type'] === 'income')  $income  += (float) $r['amount'];
            if ($r['transaction_type'] === 'expense') $expense += (float) $r['amount'];
        }

        header('Content-Type: application/json');
        return json_encode([
            'date'         => $date,
            'total_income' => $income,
            'total_expense'=> $expense,
            'transactions' => $rows,
        ]);
    }
}
