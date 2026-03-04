<?php
session_start();
session_unset();
session_destroy();

// limpa cookie de sessão no cliente (quando aplicável)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// redireciona para a tela de login
require_once __DIR__ . '/includes/bootstrap.php';
header('Location: ' . (function_exists('nf_url') ? nf_url('/area-login') : '/area-login.php'));
exit;
