<?php

require __DIR__ . '/bootstrap.php';

use App\Support\Auth;

Auth::logout();

$redirect = $_GET['redirect'] ?? 'login.php';

if (stripos((string) $redirect, 'http') === 0 || strpos((string) $redirect, "\n") !== false || strpos((string) $redirect, '//') === 0) {
    $redirect = 'login.php';
}

header('Location: ' . $redirect);
exit;
