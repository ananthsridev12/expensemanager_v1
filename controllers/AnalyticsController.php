<?php

namespace Controllers;

use Models\Analytics;
use Models\Category;
use Models\PurchaseSource;

class AnalyticsController extends BaseController
{
    private Analytics $analyticsModel;
    private Category $categoryModel;
    private PurchaseSource $purchaseSourceModel;

    public function __construct()
    {
        parent::__construct();
        $this->analyticsModel = new Analytics($this->database);
        $this->categoryModel = new Category($this->database);
        $this->purchaseSourceModel = new PurchaseSource($this->database);
    }

    public function index(): string
    {
        $startDate = (string) ($_GET['start_date'] ?? date('Y-m-01'));
        $endDate   = (string) ($_GET['end_date']   ?? date('Y-m-d'));
        if (!$this->isValidDate($startDate)) $startDate = date('Y-m-01');
        if (!$this->isValidDate($endDate))   $endDate   = date('Y-m-d');

        $drilldownFilters = [
            'start_date'          => $startDate,
            'end_date'            => $endDate,
            'tx_type'             => (string) ($_GET['tx_type'] ?? ''),
            'category_ids'        => array_values(array_filter(array_map('intval', (array) ($_GET['category_id']        ?? [])))),
            'subcategory_ids'     => array_values(array_filter(array_map('intval', (array) ($_GET['subcategory_id']     ?? [])))),
            'purchase_source_ids' => array_values(array_filter(array_map('intval', (array) ($_GET['purchase_source_id'] ?? [])))),
        ];

        $summary                = $this->analyticsModel->getSummary($startDate, $endDate);
        $earningsSummary        = $this->analyticsModel->getEarningsSummary($startDate, $endDate);
        $earningsBySubcategory  = $this->analyticsModel->getEarningsBySubcategory($startDate, $endDate);
        $expensesByCategory     = $this->analyticsModel->getExpensesByCategory($startDate, $endDate);
        $expensesBySubcategory  = $this->analyticsModel->getExpensesBySubcategory($startDate, $endDate);
        $incomeByCategory       = $this->analyticsModel->getIncomeByCategory($startDate, $endDate);
        $monthlyTrend         = $this->analyticsModel->getMonthlyIncomeVsExpense(12);
        $accountWiseExpense   = $this->analyticsModel->getAccountWiseExpense($startDate, $endDate);
        $dayOfWeekSpend       = $this->analyticsModel->getDayOfWeekSpend($startDate, $endDate);
        $dailyExpenses        = $this->analyticsModel->getDailyExpenses($startDate, $endDate);
        $drilldown            = $this->analyticsModel->getDrilldown($drilldownFilters);
        $categoriesWithSubs   = $this->categoryModel->getAllWithSubcategories();
        $purchaseSources      = $this->purchaseSourceModel->getChildren();
        $excludedCategories   = array_values(array_filter($categoriesWithSubs, fn($c) => !empty($c['exclude_from_analytics'])));

        return $this->render('analytics/index.php', [
            'startDate'             => $startDate,
            'endDate'               => $endDate,
            'drilldownFilters'      => $drilldownFilters,
            'summary'               => $summary,
            'earningsSummary'       => $earningsSummary,
            'earningsBySubcategory' => $earningsBySubcategory,
            'expensesByCategory'    => $expensesByCategory,
            'expensesBySubcategory' => $expensesBySubcategory,
            'incomeByCategory'      => $incomeByCategory,
            'monthlyTrend'         => $monthlyTrend,
            'accountWiseExpense'   => $accountWiseExpense,
            'dayOfWeekSpend'       => $dayOfWeekSpend,
            'dailyExpenses'        => $dailyExpenses,
            'drilldown'            => $drilldown,
            'categoriesWithSubs'   => $categoriesWithSubs,
            'purchaseSources'      => $purchaseSources,
            'excludedCategories'   => $excludedCategories,
        ]);
    }

    private function isValidDate(string $date): bool
    {
        if ($date === '') return false;
        $parsed = date_create_from_format('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
