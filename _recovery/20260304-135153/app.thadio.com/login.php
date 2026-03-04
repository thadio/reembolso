<?php

require __DIR__ . '/bootstrap.php';

use App\Core\View;
use App\Support\Auth;
use App\Support\Html;

[$pdo, $connectionError] = bootstrapPdo();

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$email = '';

$redirectParam = $_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php';
$redirect = sanitizeRedirect($redirectParam);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    [$ok, $error] = Auth::attempt($pdo, $email, $password);
    if ($ok) {
        header('Location: ' . $redirect);
        exit;
    }

    if ($error) {
        $errors[] = $error;
    }
}

View::render('auth/login', [
    'errors' => $errors,
    'email' => $email,
    'redirect' => $redirect,
    'connectionError' => $connectionError,
    'esc' => [Html::class, 'esc'],
], [
    'title' => 'Entrar',
    'layout' => __DIR__ . '/app/Views/auth-layout.php',
]);

function sanitizeRedirect(?string $target): string
{
    $clean = $target ? trim($target) : '';

    if ($clean === '' || stripos($clean, 'http') === 0 || strpos($clean, '//') === 0) {
        return 'index.php';
    }

    if (strpos($clean, "\n") !== false || strpos($clean, "\r") !== false) {
        return 'index.php';
    }

    return $clean === 'login.php' ? 'index.php' : $clean;
}
