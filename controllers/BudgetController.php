<?php

namespace Controllers;

use Models\Budget;
use Models\Category;

class BudgetController extends BaseController
{
    private Budget   $budgetModel;
    private Category $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->budgetModel   = new Budget($this->database);
        $this->categoryModel = new Category($this->database);
    }

    public function index(): string
    {
        $selectedMonth = max(1, min(12, (int) ($_GET['bm'] ?? date('n')) ?: (int) date('n')));
        $selectedYear  = (int) ($_GET['by'] ?? date('Y')) ?: (int) date('Y');
        if ($selectedYear < 2000 || $selectedYear > 2099) {
            $selectedYear = (int) date('Y');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';
            $pm   = (int) ($_POST['month'] ?? $selectedMonth);
            $py   = (int) ($_POST['year']  ?? $selectedYear);

            if ($form === 'budget_save_all') {
                $inputs  = $_POST['budget'] ?? [];
                $catRows = $this->categoryModel->getAllWithSubcategories();
                $catMap  = [];
                foreach ($catRows as $c) { $catMap[$c['id']] = $c['name']; }

                $budgets = [];
                foreach ($inputs as $catId => $amount) {
                    $budgets[(int)$catId] = [
                        'amount' => (float) str_replace(',', '', $amount),
                        'name'   => ($catMap[(int)$catId] ?? 'Budget') . ' — ' . date('M Y', mktime(0,0,0,$pm,1,$py)),
                    ];
                }
                $this->budgetModel->saveAllForMonth($budgets, $pm, $py);

            } elseif ($form === 'budget_copy_last') {
                $fromMonth = $pm === 1 ? 12 : $pm - 1;
                $fromYear  = $pm === 1 ? $py - 1 : $py;
                $this->budgetModel->copyFromMonth($fromMonth, $fromYear, $pm, $py);
            }

            header('Location: ?module=budget&bm=' . $pm . '&by=' . $py);
            exit;
        }

        $categories    = $this->budgetModel->getAllCategoriesWithBudgets($selectedMonth, $selectedYear);
        $threeMonthAvg = $this->budgetModel->getThreeMonthAverage();
        $trendData     = $this->budgetModel->getTrendData(6);

        $totalBudgeted = array_sum(array_column(
            array_filter($categories, fn($c) => (float)$c['budget_amount'] > 0),
            'budget_amount'
        ));
        $totalSpent = array_sum(array_column(
            array_filter($categories, fn($c) => (float)$c['budget_amount'] > 0),
            'spent'
        ));

        // Prev/next month
        $prevMonth = $selectedMonth === 1 ? 12 : $selectedMonth - 1;
        $prevYear  = $selectedMonth === 1 ? $selectedYear - 1 : $selectedYear;
        $nextMonth = $selectedMonth === 12 ? 1 : $selectedMonth + 1;
        $nextYear  = $selectedMonth === 12 ? $selectedYear + 1 : $selectedYear;

        return $this->render('budget/index.php', [
            'categories'    => $categories,
            'threeMonthAvg' => $threeMonthAvg,
            'trendData'     => $trendData,
            'selectedMonth' => $selectedMonth,
            'selectedYear'  => $selectedYear,
            'prevMonth'     => $prevMonth,
            'prevYear'      => $prevYear,
            'nextMonth'     => $nextMonth,
            'nextYear'      => $nextYear,
            'totalBudgeted' => $totalBudgeted,
            'totalSpent'    => $totalSpent,
        ]);
    }
}
