<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/user_store.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Garante que o admin padrão exista a partir de variáveis de ambiente
user_store_seed_admin_from_env();

/**
 * Garante que o usuário está autenticado na Área do Aluno.
 *
 * @return array<string,mixed>
 */
function area_guard_require_login(): array
{
    $loginUrl = function_exists('nf_url') ? nf_url('/area-login') : '/area-login.php';
    $areaUrl = function_exists('nf_url') ? nf_url('/area') : '/area.php';

    if (!isset($_SESSION['user_email'])) {
        app_log('area_guard: session without user_email', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        header('Location: ' . $loginUrl);
        exit;
    }

    $user = user_store_find($_SESSION['user_email']);
    if (!$user) {
        app_log('area_guard: user not found in db', ['email' => $_SESSION['user_email']]);
        session_destroy();
        header('Location: ' . $loginUrl);
        exit;
    }

    if (($user['role'] ?? 'student') !== 'admin') {
        $plan = strtolower(trim((string) ($user['plan'] ?? '')));
        $isFreePlan = in_array($plan, ['gratuito', 'free'], true);
        $userId = (int) ($user['id'] ?? 0);
        $hasActiveSubscription = $userId > 0 ? user_store_has_active_subscription($userId) : false;

        // Alunos criados manualmente podem nǜo ter assinatura gravada; garante ativa se nunca houve uma.
        if (!$isFreePlan && $userId > 0 && !$hasActiveSubscription) {
            $lastSubscription = user_store_last_subscription($userId);
            if (!$lastSubscription) {
                $planSlug = $plan !== '' ? $plan : 'essencial';
                $created = user_store_ensure_active_subscription($userId, $planSlug);
                app_log('area_guard: autoprovision_subscription', [
                    'user_id' => $userId,
                    'email' => $user['email'] ?? '',
                    'plan' => $planSlug,
                    'created' => $created,
                ]);
                if ($created) {
                    $hasActiveSubscription = user_store_has_active_subscription($userId);
                }
            }
        }

        if (!$isFreePlan && $userId > 0 && !$hasActiveSubscription) {
            app_log('area_guard: subscription inactive or expired', ['user_id' => $userId, 'email' => $user['email']]);
            session_destroy();
            header('Location: ' . $loginUrl . (strpos($loginUrl, '?') !== false ? '&' : '?') . 'expired=1');
            exit;
        }

        $preferences = is_array($user['preferences'] ?? null) ? $user['preferences'] : [];
        $skipForms = !empty($preferences['skip_forms']);
        $initialFormDone = $skipForms || !empty($preferences['initial_form_completed']);
        if (!$initialFormDone) {
            $currentScript = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
            $allowedDuringOnboarding = [
                'formulario-inicial.php',
                'formulario-inicial',
                'area-logout.php',
                'area-logout',
            ];
            if (!in_array($currentScript, $allowedDuringOnboarding, true)) {
                $formUrl = function_exists('nf_url') ? nf_url('/formulario-inicial') : '/formulario-inicial.php';
                header('Location: ' . $formUrl);
                exit;
            }
        }
    }

    return $user;
}

/**
 * Exige que o usuário logado seja administrador.
 *
 * @param array<string,mixed> $user
 */
function area_guard_require_admin(array $user): void
{
    if (($user['role'] ?? 'student') !== 'admin') {
        header('Location: ' . (function_exists('nf_url') ? nf_url('/area') : '/area.php'));
        exit;
    }
}
