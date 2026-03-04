<?php

require __DIR__ . '/bootstrap.php';

use App\Core\View;
use App\Repositories\UserRepository;
use App\Support\Html;
use App\Support\Input;

[$pdo, $connectionError] = bootstrapPdo();

$errors = [];
$success = '';
$token = '';
$tokenValid = false;

if ($connectionError) {
    $errors[] = 'Erro ao conectar ao banco: ' . $connectionError;
}

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));

if ($token === '' && empty($errors)) {
    $errors[] = 'Token inválido.';
}

$user = null;
if (empty($errors)) {
    $repo = new UserRepository($pdo);
    $user = $repo->findByResetToken($token);
    if (!$user) {
        $errors[] = 'Token inválido ou já utilizado.';
    } else {
        $expiresAt = $user->resetExpiresAt ? strtotime($user->resetExpiresAt) : null;
        if ($expiresAt && time() > $expiresAt) {
            $errors[] = 'Token expirado. Solicite um novo link.';
        } else {
            $tokenValid = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $input = Input::trimStrings($_POST);
    $password = (string) ($input['password'] ?? '');
    $confirm = (string) ($input['confirmPassword'] ?? '');

    if ($password === '') {
        $errors[] = 'Senha é obrigatória.';
    }
    if ($password !== $confirm) {
        $errors[] = 'As senhas não conferem.';
    }

    if (empty($errors) && $user && $user->id) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $repo->resetPassword((int) $user->id, $hash);
            $success = 'Senha alterada com sucesso. Você já pode entrar.';
            $tokenValid = false;
        } catch (Throwable $e) {
            $errors[] = 'Não foi possível atualizar a senha. Tente novamente.';
        }
    }
}

View::render('auth/reset', [
    'errors' => $errors,
    'success' => $success,
    'token' => $token,
    'tokenValid' => $tokenValid,
    'esc' => [Html::class, 'esc'],
], [
    'title' => 'Redefinir senha',
    'layout' => __DIR__ . '/app/Views/auth-layout.php',
]);
