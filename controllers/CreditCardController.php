<?php

namespace Controllers;

use Models\CreditCard;

class CreditCardController extends BaseController
{
    private CreditCard $creditCardModel;

    public function __construct()
    {
        parent::__construct();
        $this->creditCardModel = new CreditCard($this->database);
    }

    public function index(): string
    {
        $cards = $this->creditCardModel->getAll();
        $summary = $this->creditCardModel->getSummary();

        return $this->render('credit_cards/index.php', [
            'cards' => $cards,
            'summary' => $summary,
        ]);
    }
}
