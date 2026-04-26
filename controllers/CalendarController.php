<?php

namespace Controllers;

use Models\Analytics;

class CalendarController extends BaseController
{
    public function index(): string
    {
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
}
