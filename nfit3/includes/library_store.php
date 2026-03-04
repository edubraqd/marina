<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/training_library.php';

const NF_LIBRARY_FILE = __DIR__ . '/../storage/library_links.json';

/**
 * Carrega a lista de links cadastrados.
 *
 * @return array<int,array{name:string,link:string,type:string}>
 */
function library_store_load(): array
{
    if (!file_exists(NF_LIBRARY_FILE)) {
        return [];
    }

    $data = file_get_contents(NF_LIBRARY_FILE);
    if ($data === false || $data === '') {
        return [];
    }

    $decoded = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [];
    }

    $clean = [];
    foreach ($decoded as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        $link = trim((string) ($row['link'] ?? ''));
        $type = trim((string) ($row['type'] ?? 'geral'));
        if ($type === '') {
            $type = 'geral';
        }
        if ($name !== '' && $link !== '') {
            $clean[] = ['name' => $name, 'link' => $link, 'type' => $type];
        }
    }

    return $clean;
}

/**
 * Salva a lista de links cadastrados.
 *
 * @param array<int,array{name:string,link:string,type:string}> $rows
 */
function library_store_save(array $rows): void
{
    $payload = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    @file_put_contents(NF_LIBRARY_FILE, $payload);
}

/**
 * Adiciona um novo link.
 */
function library_store_add(string $name, string $link, string $type = 'geral'): void
{
    $name = trim($name);
    $link = trim($link);
    $type = $type !== '' ? trim($type) : 'geral';
    if ($name === '' || $link === '') {
        return;
    }
    $rows = library_store_load();
    $rows[] = ['name' => $name, 'link' => $link, 'type' => $type];
    library_store_save($rows);
}

/**
 * Remove um link pelo índice.
 */
function library_store_delete(int $index): void
{
    $rows = library_store_load();
    if (!isset($rows[$index])) {
        return;
    }
    unset($rows[$index]);
    library_store_save(array_values($rows));
}

/**
 * Atualiza um item existente.
 */
function library_store_update(int $index, string $name, string $link, string $type = 'geral'): void
{
    $rows = library_store_load();
    if (!isset($rows[$index])) {
        return;
    }
    $name = trim($name);
    $link = trim($link);
    $type = trim($type) ?: 'geral';
    if ($name === '' || $link === '') {
        return;
    }
    $rows[$index] = ['name' => $name, 'link' => $link, 'type' => $type];
    library_store_save($rows);
}

/**
 * Seed opcional com a biblioteca padrão (se vazio).
 */
function library_store_seed_defaults(): void
{
    $rows = library_store_load();
    if (!empty($rows)) {
        return;
    }
    $defaults = training_library_default();
    $rows = [];
    foreach ($defaults as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        $link = trim((string) ($item['video_url'] ?? ''));
        if ($name && $link) {
            $rows[] = ['name' => $name, 'link' => $link, 'type' => 'geral'];
        }
    }
    library_store_save($rows);
}
