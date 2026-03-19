<?php

namespace Controllers;

use Models\Analytics;

class AnalyticsController extends BaseController
{
    private Analytics $analyticsModel;

    public function __construct()
    {
        parent::__construct();
        $this->analyticsModel = new Analytics($this->database);
    }

    public function index(): string
    {
        $startDate = (string) ($_GET['start_date'] ?? date('Y-m-01'));
        $endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
        if (!$this->isValidDate($startDate)) {
            $startDate = date('Y-m-01');
        }
        if (!$this->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        $summary = $this->analyticsModel->getSummary($startDate, $endDate);
        $earningsSummary = $this->analyticsModel->getEarningsSummary($startDate, $endDate);
        $earningsBySubcategory = $this->analyticsModel->getEarningsBySubcategory($startDate, $endDate);
        $expensesByCategory = $this->analyticsModel->getExpensesByCategory($startDate, $endDate);
        $incomeByCategory = $this->analyticsModel->getIncomeByCategory($startDate, $endDate);
        $monthlyTrend = $this->analyticsModel->getMonthlyIncomeVsExpense(12);

        return $this->render('analytics/index.php', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $summary,
            'earningsSummary' => $earningsSummary,
            'earningsBySubcategory' => $earningsBySubcategory,
            'expensesByCategory' => $expensesByCategory,
            'incomeByCategory' => $incomeByCategory,
            'monthlyTrend' => $monthlyTrend,
        ]);
    }

    private function isValidDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsed = date_create_from_format('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
