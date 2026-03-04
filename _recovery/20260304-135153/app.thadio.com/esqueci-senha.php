<?php

require __DIR__ . '/bootstrap.php';

use App\Core\View;
use App\Repositories\UserRepository;
use App\Support\Html;
use App\Support\Input;
use App\Support\Mailer;

[$pdo, $connectionError] = bootstrapPdo();

$errors = [];
$success = '';
$email = '';

if ($connectionError) {
    $errors[] = 'Erro ao conectar ao banco: ' . $connectionError;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = Input::trimStrings($_POST);
    $email = (string) ($input['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }

    if (empty($errors)) {
        $repo = new UserRepository($pdo);
        $user = $repo->findByEmail($email);

        if ($user && $user->status === 'ativo') {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $errors[] = 'Não foi possível gerar o link de redefinição.';
            }

            if (empty($errors)) {
                $expiresAt = resetExpiry();
                try {
                    $repo->requestPasswordReset((int) $user->id, $token, $expiresAt);
                } catch (Throwable $e) {
                    $errors[] = 'Não foi possível salvar o pedido de redefinição.';
                }
            }

            if (empty($errors)) {
                if (!sendResetEmail($user, $token, $expiresAt)) {
                    $errors[] = 'Não foi possível enviar o e-mail de redefinição agora.';
                }
            }
        }

        if (empty($errors)) {
            $success = 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir sua senha.';
        }
    }
}

View::render('auth/forgot', [
    'errors' => $errors,
    'success' => $success,
    'email' => $email,
    'esc' => [Html::class, 'esc'],
], [
    'title' => 'Esqueci minha senha',
    'layout' => __DIR__ . '/app/Views/auth-layout.php',
]);

function resetExpiry(int $hours = 2): string
{
    return date('Y-m-d H:i:s', time() + ($hours * 3600));
}

function sendResetEmail($user, string $token, string $expiresAt): bool
{
    if (!$user || !$token) {
        return false;
    }

    $baseUrl = trim((string) (getenv('APP_BASE_URL') ?: ''));
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    $baseUrl = rtrim($baseUrl, '/');
    $link = $baseUrl . '/redefinir-senha.php?token=' . urlencode($token);
    $expiresLabel = date('d/m/Y H:i', strtotime($expiresAt));

    $subject = 'Redefinição de senha';
    $html = '<p>Olá ' . Html::esc($user->fullName) . ',</p>'
        . '<p>Recebemos sua solicitação para redefinir a senha. Use o link abaixo:</p>'
        . '<p><a href="' . Html::esc($link) . '">' . Html::esc($link) . '</a></p>'
        . '<p>Este link expira em ' . Html::esc($expiresLabel) . '.</p>'
        . '<p>Se você não solicitou, ignore este e-mail.</p>';

    return Mailer::send($user->email, $subject, $html);
}
