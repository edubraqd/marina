<?php

declare(strict_types=1);

// Funções utilitárias para checkout seguro via API (Mercado Pago)

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/user_store.php';
require_once __DIR__ . '/onboarding_mailer.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/meta_pixel.php';

const MPAGO_LOG_FILE       = __DIR__ . '/../storage/mpago.log';
const MPAGO_PROCESSED_FILE = __DIR__ . '/../storage/mpago_processed.log';

/**
 * Configuração base (tokens vindos de variáveis de ambiente).
 *
 * - MP_ACCESS_TOKEN   (obrigatório para criar preferências/consultar pagamentos)
 * - MP_PUBLIC_KEY     (opcional, útil se usar SDK/JS)
 * - MP_WEBHOOK_SECRET (opcional, se a assinatura do webhook estiver ativa)
 * - APP_URL           (base absoluta das URLs de retorno/notificação)
 *
 * @return array<string,string>
 */
function mpago_config(): array
{
    $base = function_exists('nf_base_url') ? nf_base_url() : getenv('APP_URL');

    // fallback seguro para produção (evita localhost em callbacks do MP)
    if (!$base) {
        $base = 'https://nutremfit.com.br';
    }

    return [
        'access_token'   => trim((string) getenv('MP_ACCESS_TOKEN')),
        'public_key'     => trim((string) getenv('MP_PUBLIC_KEY')),
        'webhook_secret' => trim((string) getenv('MP_WEBHOOK_SECRET')),
        'client_id'      => trim((string) getenv('MP_CLIENT_ID')),
        'client_secret'  => trim((string) getenv('MP_CLIENT_SECRET')),
        'app_url'        => rtrim($base ?: 'http://localhost/nfit3', '/'),
    ];
}

/**
 * Catálogo de planos disponíveis no checkout do Mercado Pago.
 *
 * @return array<string,array<string,mixed>>
 */
function mpago_plan_catalog(): array
{
    $catalog = [];
    try {
        $res = db()->query('SELECT slug, name, description, price_month, billing_cycle FROM plans WHERE is_active = 1');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $slug = mb_strtolower(trim($row['slug'] ?? ''));
                if ($slug === '' || $slug === 'teste') continue;
                $catalog[$slug] = [
                    'slug'        => $slug,
                    'db_plan'     => $slug,
                    'name'        => $row['name'] ?? ucfirst($slug),
                    'amount'      => (float) ($row['price_month'] ?? 0),
                    'description' => $row['description'] ?? '',
                    'cycle'       => $row['billing_cycle'] ?? 'monthly',
                ];
            }
        }
    } catch (Throwable $e) {
        // silencioso; fallback abaixo
    }

    // fallback padrão se nada vier do banco
    if (empty($catalog)) {
        $catalog = [
            'essencial' => [
                'slug'       => 'essencial',
                'db_plan'    => 'essencial',
                'name'       => 'Mensal - Plano starter',
                'amount'     => 189.90,
                'description'=> 'Mensal — Plano starter. Atualização mediante renovação e pagamento mensal sem fidelidade.',
                'cycle'      => 'monthly',
            ],
            'performance' => [
                'slug'       => 'performance',
                'db_plan'    => 'performance',
                'name'       => 'Semestral — Plano contínuo',
                'amount'     => 169.90,
                'description'=> 'Semestral — Plano contínuo (mais vendido). Ajustes programados a cada 30 dias por 6 meses.',
                'cycle'      => 'monthly', // controle de expiração segue mensal; assinatura segue recorrência interna
            ],
            'vip' => [
                'slug'       => 'vip',
                'db_plan'    => 'vip',
                'name'       => 'Anual - Plano completo',
                'amount'     => 149.90,
                'description'=> 'Anual — Plano completo. Ajustes programados a cada 30 dias por 12 meses com máxima economia.',
                'cycle'      => 'monthly',
            ],
        ];
    }

    // força descrições alinhadas ao site para os planos principais
    $descOverrides = [
        'essencial'  => 'Mensal — Plano starter. Atualização mediante renovação e pagamento mensal sem fidelidade.',
        'performance'=> 'Semestral — Plano contínuo (mais vendido). Ajustes programados a cada 30 dias por 6 meses.',
        'vip'        => 'Anual — Plano completo. Ajustes programados a cada 30 dias por 12 meses com máxima economia.',
        'semestral'  => 'Semestral — Plano contínuo (mais vendido). Ajustes programados a cada 30 dias por 6 meses.',
        'anual'      => 'Anual — Plano completo. Ajustes programados a cada 30 dias por 12 meses com máxima economia.',
    ];
    $priceOverrides = [
        'essencial'   => 189.90,
        'performance' => 169.90,
        'vip'         => 149.90,
        'semestral'   => 169.90,
        'anual'       => 149.90,
    ];
    $nameOverrides = [
        'essencial'   => 'Mensal - Plano starter',
        'performance' => 'Semestral — Plano contínuo',
        'vip'         => 'Anual - Plano completo',
        'semestral'   => 'Semestral — Plano contínuo',
        'anual'       => 'Anual - Plano completo',
    ];
    $cycleOverrides = [
        'essencial'   => 'monthly',
        'performance' => 'semiannual',
        'vip'         => 'yearly',
        'semestral'   => 'semiannual',
        'anual'       => 'yearly',
    ];
    foreach ($descOverrides as $slug => $desc) {
        if (isset($catalog[$slug])) {
            $catalog[$slug]['description'] = $desc;
            if (isset($priceOverrides[$slug])) {
                $catalog[$slug]['amount'] = (float) $priceOverrides[$slug];
            }
            if (isset($nameOverrides[$slug])) {
                $catalog[$slug]['name'] = $nameOverrides[$slug];
            }
            if (isset($cycleOverrides[$slug])) {
                $catalog[$slug]['cycle'] = $cycleOverrides[$slug];
            }
        }
    }

    // fallback: se o slug for diferente mas o nome indicar "semestral" ou "anual",
    // força o valor mensal correto para exibição/checkout.
    foreach ($catalog as $slug => $plan) {
        if (isset($priceOverrides[$slug])) {
            continue;
        }
        $name = mb_strtolower((string) ($plan['name'] ?? ''));
        $desc = mb_strtolower((string) ($plan['description'] ?? ''));
        $hay  = $slug . ' ' . $name . ' ' . $desc;
        if ($hay === '') {
            continue;
        }
        if (
            strpos($hay, 'semestral') !== false
            || strpos($hay, '6 meses') !== false
            || strpos($hay, '6 mês') !== false
        ) {
            $catalog[$slug]['amount'] = 169.90;
            $catalog[$slug]['cycle'] = 'semiannual';
            continue;
        }
        if (
            strpos($hay, 'anual') !== false
            || strpos($hay, '12 meses') !== false
            || strpos($hay, '12 mês') !== false
        ) {
            $catalog[$slug]['amount'] = 149.90;
            $catalog[$slug]['cycle'] = 'yearly';
            continue;
        }
        if (
            strpos($hay, 'trimestral') !== false
            || strpos($hay, '3 meses') !== false
            || strpos($hay, '3 mês') !== false
        ) {
            $catalog[$slug]['cycle'] = 'quarterly';
        }
    }
    

    return $catalog;
}

/**
 * Recupera dados de um plano pelo slug, com fallback para essencial.
 *
 * @param string $slug
 * @return array<string,mixed>
 */
function mpago_plan(string $slug): array
{
    $catalog = mpago_plan_catalog();
    $slug = strtolower(trim($slug));
    if (isset($catalog[$slug])) {
        return $catalog[$slug];
    }
    if (isset($catalog['essencial'])) {
        return $catalog['essencial'];
    }
    // fallback para primeiro plano disponível
    return reset($catalog);
}

/**
 * Retorna a duração do plano em meses para cálculo de cobrança.
 */
function mpago_plan_duration_months(array $plan): int
{
    $cycle = strtolower(trim((string) ($plan['cycle'] ?? '')));
    switch ($cycle) {
        case 'yearly':
            return 12;
        case 'semiannual':
            return 6;
        case 'quarterly':
            return 3;
        case 'monthly':
        case 'oneoff':
            return 1;
    }

    $slug = mb_strtolower(trim((string) ($plan['slug'] ?? '')));
    $name = mb_strtolower(trim((string) ($plan['name'] ?? '')));
    $desc = mb_strtolower(trim((string) ($plan['description'] ?? '')));
    $hay  = $slug . ' ' . $name . ' ' . $desc;

    if (strpos($hay, 'semestral') !== false || $slug === 'performance') {
        return 6;
    }
    if (strpos($hay, 'anual') !== false || $slug === 'vip') {
        return 12;
    }
    if (strpos($hay, 'trimestral') !== false) {
        return 3;
    }

    return 1;
}

/**
 * Calcula o valor total a cobrar no checkout (mensal * duração).
 */
function mpago_plan_charge_amount(array $plan): float
{
    $months = mpago_plan_duration_months($plan);
    $amount = (float) ($plan['amount'] ?? 0);
    return round($amount * max(1, $months), 2);
}

/**
 * Chamada HTTP para a API do Mercado Pago.
 *
 * @param string               $method  GET/POST
 * @param string               $url
 * @param array<string,mixed>|null $payload
 * @param string               $accessToken
 * @return array{ok:bool,status:int,data:array<string,mixed>|null,error:?string,raw:?string}
 */
function mpago_request(string $method, string $url, ?array $payload, string $accessToken): array
{
    $headers = [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json",
    ];

    $body = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw   = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    $data = null;
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $decoded;
        }
    }

    $ok = ($http >= 200 && $http < 300) && $data !== null;

    return [
        'ok'     => $ok,
        'status' => (int) $http,
        'data'   => $data,
        'error'  => $error,
        'raw'    => is_string($raw) ? $raw : null,
    ];
}

/**
 * Cria uma preferência de pagamento (Checkout Pro) no MP.
 *
 * @param array<string,mixed> $plan
 * @param array<string,string> $payer
 * @param string $returnUrl
 * @param string $notificationUrl
 * @param string $accessToken
 * @return array{ok:bool,init_point:?string,sandbox_init_point:?string,error:?string,payload:array<string,mixed>|null}
 */
function mpago_create_preference(array $plan, array $payer, string $returnUrl, string $notificationUrl, string $accessToken): array
{
    $payload = [
        'items' => [[
            'id'            => $plan['slug'],
            'title'         => $plan['name'],
            'description'   => $plan['description'],
            'quantity'      => 1,
            'currency_id'   => 'BRL',
            'unit_price'    => (float) $plan['amount'],
        ]],
        'payer' => [
            'name'  => $payer['name'] ?? '',
            'email' => $payer['email'] ?? '',
        ],
        'binary_mode'     => true, // apenas aprovado ou recusado
        'payment_methods' => [
            // prioriza exibir PIX (QR) no Checkout Pro; o cliente pode mudar para outras opções se quiser
            'default_payment_method_id' => 'pix',
        ],
        'statement_descriptor' => 'NUTREMFIT',
        'notification_url' => $notificationUrl,
        'back_urls' => [
            'success' => $returnUrl,
            'pending' => $returnUrl,
            'failure' => $returnUrl,
        ],
        'auto_return' => 'approved',
        'metadata' => [
            'plan'      => $plan['slug'],
            'db_plan'   => $plan['db_plan'],
            'cycle'     => $plan['cycle'],
            'is_test'   => !empty($plan['is_test']),
            'email'     => $payer['email'] ?? '',
            'name'      => $payer['name'] ?? '',
        ],
        'external_reference' => sprintf('NFIT-%s-%s', strtoupper($plan['slug']), date('YmdHis')),
    ];

    if (!empty($payer['phone'])) {
        $payload['payer']['phone'] = ['number' => $payer['phone']];
    }

    $response = mpago_request('POST', 'https://api.mercadopago.com/checkout/preferences', $payload, $accessToken);

    return [
        'ok'                 => $response['ok'],
        'init_point'         => $response['data']['init_point'] ?? null,
        'sandbox_init_point' => $response['data']['sandbox_init_point'] ?? null,
        'error'              => $response['error'] ?? ($response['data']['message'] ?? null),
        'payload'            => $response['data'] ?? null,
    ];
}

/**
 * Busca um pagamento por ID na API do MP.
 */
function mpago_fetch_payment(string $paymentId, string $accessToken): ?array
{
    if ($paymentId === '') {
        return null;
    }

    $res = mpago_request('GET', "https://api.mercadopago.com/v1/payments/{$paymentId}", null, $accessToken);
    return $res['ok'] ? ($res['data'] ?? null) : null;
}

/**
 * Registra eventos do fluxo de pagamento.
 *
 * @param array<string,mixed> $data
 */
function mpago_log(string $type, array $data): void
{
    $logLine = json_encode(
        [
            'ts'   => date('c'),
            'type' => $type,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE
    );

    @file_put_contents(MPAGO_LOG_FILE, $logLine . PHP_EOL, FILE_APPEND);
}

/**
 * Evita processar o mesmo pagamento mais de uma vez.
 */
function mpago_mark_processed(string $paymentId): void
{
    @file_put_contents(MPAGO_PROCESSED_FILE, $paymentId . PHP_EOL, FILE_APPEND);
}

function mpago_is_processed(string $paymentId): bool
{
    if (!file_exists(MPAGO_PROCESSED_FILE)) {
        return false;
    }

    $lines = file(MPAGO_PROCESSED_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $lines ? in_array($paymentId, $lines, true) : false;
}

/**
 * Processa um pagamento aprovado: cria/atualiza usuário e dispara e-mail.
 *
 * @param array<string,mixed> $payment
 * @return array{processed:bool,reason:?string,user?:array<string,mixed>}
 */
function mpago_process_payment(array $payment): array
{
    $paymentId = (string) ($payment['id'] ?? '');
    if ($paymentId === '') {
        return ['processed' => false, 'reason' => 'missing_id'];
    }

    if (mpago_is_processed($paymentId)) {
        return ['processed' => false, 'reason' => 'already_processed'];
    }

    if (($payment['status'] ?? '') !== 'approved') {
        return ['processed' => false, 'reason' => 'not_approved'];
    }

    $metadata = $payment['metadata'] ?? [];
    $payer = $payment['payer'] ?? [];
    $additional = $payment['additional_info']['payer'] ?? [];

    // Melhor cobertura para e-mail vindo do checkout Pro/PIX
    $email = trim(
        $metadata['email']
        ?? ($payer['email'] ?? '')
        ?? ($additional['email'] ?? '')
    );
    if ($email === '') {
        mpago_log('payment.missing_email', [
            'id' => $paymentId,
            'payer' => $payer,
            'additional_payer' => $additional,
            'metadata' => $metadata,
        ]);
        return ['processed' => false, 'reason' => 'missing_email'];
    }

    $name = trim(
        $metadata['name']
        ?? (($payer['first_name'] ?? '') . ' ' . ($payer['last_name'] ?? ''))
        ?? ($additional['first_name'] ?? '')
    );

    $dbPlan = $metadata['db_plan'] ?? ($metadata['plan'] ?? 'essencial');
    $planInfo = mpago_plan($dbPlan);
    $planLabel = $metadata['plan'] ?? $dbPlan;
    $normalizedPlan = $planInfo['db_plan'] ?? 'essencial';

    mpago_log('payment.approved', [
        'id'     => $paymentId,
        'email'  => $email,
        'plan'   => $dbPlan,
        'amount' => $payment['transaction_amount'] ?? null,
    ]);

    $result = user_store_provision($email, $normalizedPlan, $name);
    $userId = (int) ($result['user']['id'] ?? 0);
    $planId = mpago_get_plan_id($normalizedPlan);
    $expiresAt = null;

    if ($userId > 0 && $planId !== null) {
        $sub = mpago_upsert_subscription($userId, $planId, $planInfo['cycle'] ?? 'monthly', $payment);
        $expiresAt = $sub['expires_at'] ?? null;
    }

    if (!empty($result['password'])) {
        send_onboarding_email($email, $name, $result['password'], $planLabel, $expiresAt);
    }

    // Confirmação de pagamento para o aluno (mesmo se já tiver senha)
    $amount = (float) ($payment['transaction_amount'] ?? 0);
    $currency = strtoupper((string) ($payment['currency_id'] ?? 'BRL'));
    $paidAt = $payment['date_approved'] ?? $payment['date_created'] ?? null;
    send_payment_confirmation_email($email, $name, $planLabel, $amount, $currency, $paidAt);

    $amount = (float) ($payment['transaction_amount'] ?? 0);
    $currency = strtoupper((string) ($payment['currency_id'] ?? 'BRL'));
    $paidAt = $payment['date_approved'] ?? $payment['date_created'] ?? null;
    $paidAtFmt = $paidAt ? date('d/m/Y H:i', strtotime((string) $paidAt)) : date('d/m/Y H:i');
    $adminLines = [
        'Evento: pagamento aprovado (Mercado Pago)',
        'Pagamento ID: ' . $paymentId,
        'E-mail: ' . $email,
        'Nome: ' . ($name ?: 'n/d'),
        'Plano: ' . $planLabel,
        'Valor: ' . number_format($amount, 2, ',', '.') . ' ' . $currency,
        'Aprovado em: ' . $paidAtFmt,
    ];
    if (!empty($metadata['is_test'])) {
        $adminLines[] = 'Marcação: pagamento de teste';
    }
    send_admin_notification(
        sprintf('Nova compra aprovada | %s', strtoupper($planLabel)),
        $adminLines
    );

    if (meta_capi_enabled()) {
        $planSlug = $planInfo['slug'] ?? ($metadata['plan'] ?? $dbPlan);
        $phone = '';
        if (!empty($metadata['phone'])) {
            $phone = (string) $metadata['phone'];
        } elseif (!empty($payer['phone']['number'])) {
            $phone = (string) $payer['phone']['number'];
        } elseif (!empty($payer['phone'])) {
            $phone = is_array($payer['phone']) ? (string) ($payer['phone']['number'] ?? '') : (string) $payer['phone'];
        }

        $metaCfg = meta_pixel_config();
        $eventSourceUrl = meta_event_source_url(($metaCfg['app_url'] ?? '') . '/payment-return');
        $purchaseValue = (float) ($amount ?? 0);
        $purchaseCurrency = $currency ?: 'BRL';

        meta_capi_send_event(
            'Purchase',
            [
                'email' => $email,
                'phone' => $phone,
                'name'  => $name,
            ],
            [
                'currency' => $purchaseCurrency,
                'value' => $purchaseValue,
                'content_ids' => [$planSlug],
                'content_name' => $planLabel,
                'content_type' => 'product',
                'contents' => [[
                    'id' => $planSlug,
                    'quantity' => 1,
                    'item_price' => $purchaseValue,
                ]],
                'order_id' => $paymentId,
            ],
            'mp_' . $paymentId,
            $eventSourceUrl
        );
    }

    mpago_mark_processed($paymentId);

    return [
        'processed' => true,
        'user'      => $result['user'] ?? null,
    ];
}

/**
 * Busca o último pagamento aprovado pelo e-mail do pagador (Mercado Pago Search).
 */
function mpago_find_last_approved_payment_by_email(string $email, string $accessToken): ?array
{
    $email = mb_strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    $query = http_build_query([
        'status'   => 'approved',
        'limit'    => 1,
        'sort'     => 'date_created',
        'criteria' => 'desc',
        'email'    => $email,
    ]);

    $res = mpago_request('GET', "https://api.mercadopago.com/v1/payments/search?{$query}", null, $accessToken);
    if (!$res['ok']) {
        mpago_log('error.payment_search', ['email' => $email, 'status' => $res['status'], 'err' => $res['error'] ?? null]);
        return null;
    }

    $results = $res['data']['results'] ?? [];
    return isset($results[0]) ? $results[0] : null;
}

/**
 * Resolve informações do plano a partir do pagamento (metadata).
 *
 * @return array<string,mixed>
 */
function mpago_plan_from_payment(array $payment): array
{
    $metadata = $payment['metadata'] ?? [];
    $planSlug = $metadata['db_plan'] ?? ($metadata['plan'] ?? 'essencial');
    $cycle    = $metadata['cycle'] ?? ($metadata['billing_cycle'] ?? 'monthly');
    $planInfo = mpago_plan($planSlug);
    $planInfo['cycle'] = $planInfo['cycle'] ?? $cycle;
    return $planInfo;
}

/**
 * Busca info do plano por ID.
 *
 * @return array<string,mixed>|null
 */
function mpago_get_plan_by_id(int $planId): ?array
{
    try {
        $stmt = db()->prepare('SELECT id, slug, name, billing_cycle FROM plans WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    } catch (Throwable $e) {
        mpago_log('error.plan_fetch_by_id', ['plan_id' => $planId, 'err' => $e->getMessage()]);
        return null;
    }
}

/**
 * Busca o ID do plano no banco a partir do slug.
 */
function mpago_get_plan_id(string $slug): ?int
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id FROM plans WHERE slug = ? LIMIT 1');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? (int) $row['id'] : null;
    } catch (Throwable $e) {
        mpago_log('error.plan_lookup', ['slug' => $slug, 'err' => $e->getMessage()]);
        return null;
    }
}

/**
 * Calcula a data de expiração conforme ciclo.
 */
function mpago_calculate_expiration(string $cycle): string
{
    $cycle = strtolower($cycle);
    $now = new DateTimeImmutable('now');

    switch ($cycle) {
        case 'yearly':
            $expires = $now->modify('+1 year');
            break;
        case 'semiannual':
            $expires = $now->modify('+6 months');
            break;
        case 'quarterly':
            $expires = $now->modify('+3 months');
            break;
        case 'oneoff':
            $expires = $now->modify('+7 days'); // teste simbólico de integração
            break;
        case 'monthly':
        default:
            $expires = $now->modify('+1 month');
            break;
    }

    return $expires->format('Y-m-d H:i:s');
}

/**
 * Cria/atualiza assinatura e registra o pagamento no log.
 *
 * @param array<string,mixed> $payment
 * @return array<string,mixed>
 */
function mpago_upsert_subscription(int $userId, int $planId, string $cycle, array $payment): array
{
    $expiresAt = mpago_calculate_expiration($cycle);
    $payloadJson = json_encode($payment, JSON_UNESCAPED_UNICODE);
    $providerId  = (string) ($payment['id'] ?? '');
    $amount      = (float) ($payment['transaction_amount'] ?? 0);
    $currency    = (string) ($payment['currency_id'] ?? 'BRL');

    $subscriptionId = null;

    try {
        $conn = db();

        // busca subs existente
        $stmt = $conn->prepare('SELECT id FROM subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $subscriptionId = (int) $row['id'];
            $stmt = $conn->prepare('UPDATE subscriptions SET plan_id = ?, status = "active", started_at = NOW(), expires_at = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('isi', $planId, $expiresAt, $subscriptionId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare('INSERT INTO subscriptions (user_id, plan_id, status, started_at, expires_at, meta) VALUES (?,?, "active", NOW(), ?, JSON_OBJECT("last_payment_id", ?))');
            $stmt->bind_param('iiss', $userId, $planId, $expiresAt, $providerId);
            $stmt->execute();
            $subscriptionId = $stmt->insert_id ?: null;
            $stmt->close();
        }

        if ($subscriptionId) {
            $stmt = $conn->prepare('INSERT INTO payment_logs (subscription_id, provider, provider_id, amount, currency, status, paid_at, payload) VALUES (?, "mercadopago", ?, ?, ?, "paid", NOW(), ?)');
            $stmt->bind_param('isdss', $subscriptionId, $providerId, $amount, $currency, $payloadJson);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        mpago_log('error.subscription', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'err'     => $e->getMessage(),
        ]);
    }

    return [
        'subscription_id' => $subscriptionId,
        'expires_at'      => $expiresAt,
    ];
}
