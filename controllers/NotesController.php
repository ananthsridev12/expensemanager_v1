<?php

namespace Controllers;

use Models\Note;

class NotesController extends BaseController
{
    private Note $noteModel;

    public function __construct()
    {
        parent::__construct();
        $this->noteModel = new Note($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';

            if ($form === 'note_save') {
                $id      = (int) ($_POST['note_id'] ?? 0);
                $title   = trim($_POST['title']   ?? '');
                $content = trim($_POST['content'] ?? '');

                if ($content !== '') {
                    if ($id > 0) {
                        $this->noteModel->update($id, $title, $content);
                    } else {
                        $this->noteModel->save($title, $content);
                    }
                }

                header('Location: ?module=notes');
                exit;
            }

            if ($form === 'note_delete') {
                $id = (int) ($_POST['note_id'] ?? 0);
                if ($id > 0) {
                    $this->noteModel->delete($id);
                }
                header('Location: ?module=notes');
                exit;
            }
        }

        $editNote = null;
        if (!empty($_GET['edit'])) {
            $editNote = $this->noteModel->getById((int) $_GET['edit']);
        }

        return $this->render('notes/index.php', [
            'notes'    => $this->noteModel->getAll(),
            'editNote' => $editNote,
        ]);
    }
}
