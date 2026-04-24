<?php

namespace Controllers;

use Models\Category;

class CategoryController extends BaseController
{
    private Category $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->categoryModel = new Category($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'toggle_analytics_exclude') {
            $id = (int) ($_POST['category_id'] ?? 0);
            $this->categoryModel->toggleExcludeFromAnalytics($id);
            header('Content-Type: application/json');
            return json_encode(['ok' => true]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['form']) && $_POST['form'] === 'category') {
                $this->categoryModel->createCategory(
                    $_POST['name'] ?? '',
                    $_POST['type'] ?? 'expense',
                    isset($_POST['is_fuel']) && $_POST['is_fuel'] === '1'
                );
            }

            if (isset($_POST['form']) && $_POST['form'] === 'subcategory') {
                $this->categoryModel->createSubcategory((int) ($_POST['category_id'] ?? 0), $_POST['name'] ?? '');
            }

            if (isset($_POST['form']) && $_POST['form'] === 'category_update') {
                $this->categoryModel->updateCategory($_POST);
            }

            if (isset($_POST['form']) && $_POST['form'] === 'subcategory_update') {
                $this->categoryModel->updateSubcategory($_POST);
            }

            header('Location: ?module=categories');
            exit;
        }

        $editCategory = null;
        if (!empty($_GET['edit_cat'])) {
            $editCategory = $this->categoryModel->getCategoryById((int) $_GET['edit_cat']);
        }

        $editSubcategory = null;
        if (!empty($_GET['edit_sub'])) {
            $editSubcategory = $this->categoryModel->getSubcategoryById((int) $_GET['edit_sub']);
        }

        $categories = $this->categoryModel->getAllWithSubcategories();

        return $this->render('categories/index.php', [
            'categories' => $categories,
            'editCategory' => $editCategory,
            'editSubcategory' => $editSubcategory,
        ]);
    }
}
