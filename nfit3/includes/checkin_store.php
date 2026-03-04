<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/user_store.php';

/**
 * @return array<int,array<string,mixed>>
 */
function checkin_store_for_user(string $email): array
{
    $user = user_store_find($email);
    if (!$user) {
        return [];
    }

    $stmt = db()->prepare('SELECT * FROM checkins WHERE user_id = ? ORDER BY submitted_at ASC');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $entries = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $entries;
}

/**
 * @param array<string,string> $payload
 */
function checkin_store_add(string $email, array $payload): array
{
    $user = user_store_find($email);
    if (!$user) {
        throw new RuntimeException('Usuário não encontrado para registrar check-in.');
    }

    $energy = (int) ($payload['energy'] ?? 0);
    $weight = trim($payload['weight'] ?? '');
    $routine = (int) ($payload['routine'] ?? 0);
    $notes = trim($payload['notes'] ?? '');

    $stmt = db()->prepare(
        'INSERT INTO checkins (user_id, energy, weight, routine, notes, submitted_at) VALUES (?,?,?,?,?,NOW())'
    );
    $stmt->bind_param('iisis', $user['id'], $energy, $weight, $routine, $notes);
    $stmt->execute();
    $insertedId = $stmt->insert_id;
    $stmt->close();

    $fetch = db()->prepare('SELECT * FROM checkins WHERE id = ? LIMIT 1');
    $fetch->bind_param('i', $insertedId);
    $fetch->execute();
    $result = $fetch->get_result();
    $record = $result ? $result->fetch_assoc() : null;
    $fetch->close();

    return $record ?? [];
}
