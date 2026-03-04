<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\ProductRepository;
use App\Support\Html;
use PDO;

class TagController
{
    private ?PDO $pdo;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        $tags = [];

        if ($this->pdo && $status !== 'trash') {
            $repo = new ProductRepository($this->pdo);
            $tags = $repo->listTags();
        }

        View::render('tags/list', [
            'tags' => $tags,
            'status' => $status,
            'connectionError' => $this->connectionError,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Tags',
        ]);
    }

    public function form(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $notice = null;
        $noticeType = 'info';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($name === '') {
                $notice = 'Informe um nome para simular a criação de tag.';
                $noticeType = 'error';
            } else {
                $notice = 'As tags são aplicadas no cadastro de produto. Use "' . $name . '" nos produtos desejados.';
                $noticeType = 'success';
            }
        }

        View::render('tags/form', [
            'name' => $name,
            'notice' => $notice,
            'noticeType' => $noticeType,
            'connectionError' => $this->connectionError,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Nova Tag',
        ]);
    }
}

