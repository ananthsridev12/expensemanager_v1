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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';

            if ($form === 'budget') {
                $this->budgetModel->create($_POST);
            } elseif ($form === 'budget_update') {
                $this->budgetModel->update($_POST);
            } elseif ($form === 'budget_delete') {
                $this->budgetModel->delete((int) ($_POST['id'] ?? 0));
            }

            $qs = http_build_query([
                'module' => 'budget',
                'bm'     => $_POST['month']  ?? date('n'),
                'by'     => $_POST['year']   ?? date('Y'),
            ]);
            header('Location: ?' . $qs);
            exit;
        }

        $selectedMonth = max(1, min(12, (int) filter_input(INPUT_GET, 'bm', FILTER_SANITIZE_NUMBER_INT) ?: (int) date('n')));
        $selectedYear  = (int) filter_input(INPUT_GET, 'by', FILTER_SANITIZE_NUMBER_INT) ?: (int) date('Y');
        if ($selectedYear < 2000 || $selectedYear > 2099) {
            $selectedYear = (int) date('Y');
        }

        $editId     = (int) filter_input(INPUT_GET, 'edit', FILTER_SANITIZE_NUMBER_INT);
        $editBudget = $editId > 0 ? $this->budgetModel->getById($editId) : null;

        $budgets    = $this->budgetModel->getForMonth($selectedMonth, $selectedYear);
        $categories = $this->categoryModel->getCategoryList();

        $totalBudgeted = array_sum(array_column($budgets, 'amount'));
        $totalSpent    = array_sum(array_column($budgets, 'spent'));
        $overCount     = count(array_filter($budgets, fn($b) => (float)$b['spent'] >= (float)$b['amount']));
        $remaining     = max(0, $totalBudgeted - $totalSpent);

        return $this->render('budget/index.php', [
            'budgets'        => $budgets,
            'categories'     => $categories,
            'selectedMonth'  => $selectedMonth,
            'selectedYear'   => $selectedYear,
            'editBudget'     => $editBudget,
            'totalBudgeted'  => $totalBudgeted,
            'totalSpent'     => $totalSpent,
            'remaining'      => $remaining,
            'overCount'      => $overCount,
        ]);
    }
}
