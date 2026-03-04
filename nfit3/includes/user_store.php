<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/database.php';

/**
 * Garante a existência de um administrador padrão a partir das variáveis de ambiente.
 * Use NF_ADMIN_EMAIL e NF_ADMIN_PASS para configurar.
 */
function user_store_seed_admin_from_env(): void
{
    $email = mb_strtolower(trim((string) getenv('NF_ADMIN_EMAIL')));
    $pass  = (string) getenv('NF_ADMIN_PASS');
    if ($email === '' || $pass === '') {
        return;
    }

    $admin = user_store_find($email);
    if ($admin) {
        // caso já exista, garante que continua como admin
        if (($admin['role'] ?? 'student') !== 'admin') {
            user_store_update_fields($email, ['role' => 'admin']);
        }
        return;
    }

    $passwordHash = password_hash($pass, PASSWORD_DEFAULT);
    $plan = 'vip';
    $role = 'admin';

    try {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, plan, role, password_hash, created_at) VALUES (?,?,?,?,?,NOW())'
        );
        $name = 'Administrador';
        $stmt->bind_param('sssss', $name, $email, $plan, $role, $passwordHash);
        $stmt->execute();
        $stmt->close();
        app_log('seed_admin_created', ['email' => $email]);
    } catch (Throwable $e) {
        app_log('seed_admin_error', ['email' => $email, 'err' => $e->getMessage()]);
    }
}

/**
 * Normaliza a linha retornada pelo banco (decodifica JSON etc).
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function user_store_normalize(array $row): array
{
    if (isset($row['email'])) {
        $row['email'] = mb_strtolower($row['email']);
    }

    if (isset($row['preferences']) && $row['preferences']) {
        $decoded = json_decode((string) $row['preferences'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $row['preferences'] = $decoded;
        }
    }

    return $row;
}

/**
 * @return array<int,array<string,mixed>>
 */
function user_store_all(): array
{
    $result = db()->query('SELECT * FROM users ORDER BY id ASC');
    if (!$result) {
        return [];
    }

    $users = [];
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $users[] = user_store_normalize($row);
    }

    return $users;
}

function user_store_find(string $email): ?array
{
    $email = mb_strtolower(trim($email));
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ? user_store_normalize($user) : null;
}

function user_store_touch_login(string $email): void
{
    user_store_update_fields($email, ['last_login_at' => date('Y-m-d H:i:s')]);
}

function user_store_update_password(string $email, string $newPassword): void
{
    user_store_update_fields($email, [
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
    ]);
}

/**
 * @param array<string,mixed> $fields
 */
function user_store_update_fields(string $email, array $fields): ?array
{
    if (empty($fields)) {
        return user_store_find($email);
    }

    $email = mb_strtolower(trim($email));
    $columns = [];
    $types = '';
    $values = [];

    foreach ($fields as $column => $value) {
        if ($column === 'preferences' && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $columns[] = sprintf('`%s` = ?', $column);
        $values[] = $value;
        $types .= 's';
    }

    $values[] = $email;
    $types .= 's';

    $sql = sprintf('UPDATE users SET %s WHERE email = ?', implode(', ', $columns));
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();

    return user_store_find($email);
}

function user_store_delete(string $email): void
{
    $email = mb_strtolower(trim($email));
    $stmt = db()->prepare('DELETE FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

/**
 * @return array{user: array<string,mixed>, password: ?string}
 */
function user_store_provision(string $email, string $plan, string $name = '', ?string $role = null): array
{
    $email = mb_strtolower(trim($email));
    $plan = trim($plan);
    $name = trim($name);
    $role = $role !== null ? trim($role) : null;

    $existing = user_store_find($email);
    if ($existing) {
        $fields = ['plan' => $plan ?: ($existing['plan'] ?? 'essencial')];
        if ($name !== '') {
            $fields['name'] = $name;
        }
        if ($role !== null && $role !== '') {
            $fields['role'] = $role;
        }
        $user = user_store_update_fields($email, $fields);
        return ['user' => $user ?? $existing, 'password' => null];
    }

    $password = bin2hex(random_bytes(4));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $roleToUse = $role && $role !== '' ? $role : 'student';
    $stmt = db()->prepare(
        'INSERT INTO users (name, email, plan, role, password_hash, created_at) VALUES (?,?,?,?,?,NOW())'
    );
    $stmt->bind_param('sssss', $name, $email, $plan, $roleToUse, $passwordHash);
    $stmt->execute();
    $stmt->close();

    $user = user_store_find($email);

    return ['user' => $user, 'password' => $password];
}

function user_store_authenticate(string $email, string $password): ?array
{
    $user = user_store_find($email);
    if (!$user) {
        return null;
    }

    return password_verify($password, $user['password_hash'] ?? '') ? $user : null;
}

/**
 * Verifica se o usuário tem uma assinatura ativa (não expirada).
 */
function user_store_has_active_subscription(int $userId): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM subscriptions WHERE user_id = ? AND status = "active" AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $found = $res && $res->fetch_row();
        $stmt->close();
        return (bool) $found;
    } catch (Throwable $e) {
        error_log('Erro ao verificar assinatura: ' . $e->getMessage());
        return false;
    }
}

/**
 * Recupera a última assinatura do usuário (mais recente).
 *
 * @return array<string,mixed>|null
 */
function user_store_last_subscription(int $userId): ?array
{
    try {
        $stmt = db()->prepare('SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('Erro ao obter assinatura: ' . $e->getMessage());
        return null;
    }
}

/**
 * Retorna o ID do plano pelo slug, ou o primeiro plano disponível se não encontrar.
 */
function user_store_plan_id_by_slug(string $slug): ?int
{
    $slug = trim($slug);
    try {
        $stmt = db()->prepare('SELECT id FROM plans WHERE slug = ? LIMIT 1');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }
        // fallback: primeiro plano cadastrado
        $fallback = db()->query('SELECT id FROM plans ORDER BY id ASC LIMIT 1');
        if ($fallback) {
            $row = $fallback->fetch_assoc();
            if ($row && isset($row['id'])) {
                return (int) $row['id'];
            }
        }
    } catch (Throwable $e) {
        error_log('Erro ao buscar plano: ' . $e->getMessage());
    }
    return null;
}

/**
 * Garante que o aluno tenha uma assinatura ativa (cria/reativa manualmente).
 * Retorna true se conseguiu criar/atualizar, false caso contrário.
 */
function user_store_ensure_active_subscription(int $userId, string $planSlug): bool
{
    $planId = user_store_plan_id_by_slug($planSlug);
    if (!$planId) {
        return false;
    }
    try {
        // já existe ativa?
        $stmt = db()->prepare('SELECT id FROM subscriptions WHERE user_id = ? AND status = "active" LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row && isset($row['id'])) {
            $subId = (int) $row['id'];
            $stmt = db()->prepare('UPDATE subscriptions SET plan_id = ?, expires_at = NULL, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('ii', $planId, $subId);
            $stmt->execute();
            $stmt->close();
            return true;
        }

        $stmt = db()->prepare('INSERT INTO subscriptions (user_id, plan_id, status, started_at, expires_at, meta) VALUES (?, ?, "active", NOW(), NULL, JSON_OBJECT("source","manual"))');
        $stmt->bind_param('ii', $userId, $planId);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Throwable $e) {
        error_log('Erro ao garantir assinatura: ' . $e->getMessage());
        return false;
    }
}
