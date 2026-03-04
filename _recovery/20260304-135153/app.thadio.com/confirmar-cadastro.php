<?php

require __DIR__ . '/bootstrap.php';

use App\Core\View;
use App\Repositories\UserRepository;
use App\Support\Html;

[$pdo, $connectionError] = bootstrapPdo();

$errors = [];
$success = '';
$token = trim((string) ($_GET['token'] ?? ''));

if ($connectionError) {
    $errors[] = 'Erro ao conectar ao banco: ' . $connectionError;
}

if ($token === '') {
    $errors[] = 'Token inválido.';
}

if (empty($errors)) {
    $repo = new UserRepository($pdo);
    $user = $repo->findByVerificationToken($token);
    if (!$user) {
        $errors[] = 'Token inválido ou já utilizado.';
    } else {
        $expiresAt = $user->verificationExpiresAt ? strtotime($user->verificationExpiresAt) : null;
        if ($expiresAt && time() > $expiresAt) {
            $errors[] = 'Token expirado. Solicite um novo envio ao administrador.';
        } else {
            $repo->activateByToken($token);
            $success = 'Cadastro validado com sucesso. Você já pode acessar o painel.';
        }
    }
}

View::render('auth/verify', [
    'errors' => $errors,
    'success' => $success,
    'esc' => [Html::class, 'esc'],
], [
    'title' => 'Validar cadastro',
    'layout' => __DIR__ . '/app/Views/auth-layout.php',
]);
