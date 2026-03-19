<?php

namespace Controllers;

use Models\Account;
use Models\Investment;
use Models\SipSchedule;

class SipController extends BaseController
{
    private SipSchedule $sipModel;
    private Investment $investmentModel;
    private Account $accountModel;

    public function __construct()
    {
        parent::__construct();
        $this->sipModel = new SipSchedule($this->database);
        $this->investmentModel = new Investment($this->database);
        $this->accountModel = new Account($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sip') {
            $this->sipModel->create($_POST);
            header('Location: ?module=sip');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sip_update') {
            $this->sipModel->update($_POST);
            header('Location: ?module=sip');
            exit;
        }

        $editSip = null;
        if (!empty($_GET['edit'])) {
            $editSip = $this->sipModel->getById((int) $_GET['edit']);
        }

        $schedules = $this->sipModel->getAll();
        $upcoming = $this->sipModel->getUpcoming(5);
        $investments = $this->investmentModel->getAll();
        $accounts = $this->accountModel->getList();

        return $this->render('sip/index.php', [
            'schedules' => $schedules,
            'upcoming' => $upcoming,
            'investments' => $investments,
            'accounts' => $accounts,
            'editSip' => $editSip,
        ]);
    }
}
