<?php
// NUNCA deixe espaços antes do <?php

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config.php';
}

/**
 * Retorna a árvore de menu para a área e posição informadas.
 *
 * @param string $area     'back' ou 'front'
 * @param string $position 'sidebar' ou 'topbar'
 * @return array
 */
function mozart_get_menu(string $area = 'back', string $position = 'sidebar'): array
{
    static $cache = [];

    $cacheKey = $area . ':' . $position;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $allMenus   = mozart_collect_menus_from_manifests();
    $userCaps   = mozart_get_current_user_capabilities();
    $filtered   = [];

    // Filtra por área/posição
    foreach ($allMenus as $item) {
        $itemArea     = $item['area']     ?? 'back';
        $itemPosition = $item['position'] ?? 'sidebar';

        if ($itemArea !== $area || $itemPosition !== $position) {
            continue;
        }

        $filtered[] = $item;
    }

    // Filtra por capabilities (RBAC)
    $filtered = mozart_filter_menu_tree_by_capabilities($filtered, $userCaps);

    // Ordena os ROOT por "order"
    usort($filtered, 'mozart_menu_sort');

    $cache[$cacheKey] = $filtered;
    return $filtered;
}

/**
 * Lê todos os manifest.php de modules/* e junta o campo 'menus'
 */
function mozart_collect_menus_from_manifests(): array
{
    $modulesDir = ROOT_PATH . '/modules';
    $items      = [];

    foreach (glob($modulesDir . '/*/manifest.php') as $manifestFile) {
        $manifest = include $manifestFile;
        if (!is_array($manifest)) {
            continue;
        }

        $slug   = $manifest['slug'] ?? basename(dirname($manifestFile));
        $menus  = $manifest['menus'] ?? [];

        if (!is_array($menus)) {
            continue;
        }

        foreach ($menus as $menuItem) {
            // Normaliza e anexa metadados do módulo
            $menuItem['module'] = $slug;
            $items[] = mozart_normalize_menu_item($menuItem);
        }
    }

    return $items;
}

/**
 * Normaliza campos padrão do item de menu
 */
function mozart_normalize_menu_item(array $item): array
{
    if (!isset($item['id'])) {
        // gera id básico se não tiver
        $item['id'] = 'menu_' . md5(($item['label'] ?? 'item') . random_int(1, 999999));
    }

    $item['label']        = $item['label']        ?? 'Item';
    $item['icon']         = $item['icon']         ?? '';
    $item['url']          = $item['url']          ?? '#';
    $item['area']         = $item['area']         ?? 'back';
    $item['position']     = $item['position']     ?? 'sidebar';
    $item['order']        = $item['order']        ?? 0;
    $item['capabilities'] = $item['capabilities'] ?? [];
    $item['children']     = $item['children']     ?? [];

    // Normaliza filhos recursivamente
    if (is_array($item['children']) && $item['children']) {
        $normalizedChildren = [];
        foreach ($item['children'] as $child) {
            $normalizedChildren[] = mozart_normalize_menu_item($child);
        }
        $item['children'] = $normalizedChildren;
    }

    return $item;
}

/**
 * Devolve capabilities do usuário logado.
 * Integre isso com seu RBAC / sessão depois.
 */
function mozart_get_current_user_capabilities(): array
{
    // EXEMPLO: integrar depois com seu sistema de perfis/papéis
    if (!empty($_SESSION['mozart_capabilities']) && is_array($_SESSION['mozart_capabilities'])) {
        return $_SESSION['mozart_capabilities'];
    }

    // Por enquanto, libera tudo
    return ['*'];
}

/**
 * Verifica se usuário possui as capabilities necessárias.
 *
 * Suporta wildcard no que o usuário possui, exemplo:
 *   usuário tem:  ['whatsapp:*']
 *   requerido:     ['whatsapp:messages:read']
 *   => true
 */
function mozart_user_has_caps(array $userCaps, array $requiredCaps): bool
{
    if (!$requiredCaps) {
        return true;
    }

    if (in_array('*', $userCaps, true)) {
        return true;
    }

    foreach ($requiredCaps as $required) {
        // Se usuário tiver exatamente a cap
        if (in_array($required, $userCaps, true)) {
            return true;
        }

        // Testa wildcards do usuário
        foreach ($userCaps as $uCap) {
            if (substr($uCap, -1) === '*') {
                $prefix = rtrim($uCap, '*');
                if (str_starts_with($required, $prefix)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Filtra recursivamente a árvore de menu pelas capabilities do usuário
 */
function mozart_filter_menu_tree_by_capabilities(array $items, array $userCaps): array
{
    $out = [];

    foreach ($items as $item) {
        $required  = $item['capabilities'] ?? [];
        $visible   = mozart_user_has_caps($userCaps, $required);
        $children  = $item['children'] ?? [];

        if ($children) {
            $children = mozart_filter_menu_tree_by_capabilities($children, $userCaps);
            $item['children'] = $children;

            // Se não tem permissão explícita no pai, mas tem filhos visíveis,
            // mantém o pai apenas como agrupador
            if (!$visible && $children) {
                $visible = true;
            }
        }

        // Item divisor: só passa se tiver filhos visíveis ao redor ou você preferir sempre exibir
        if (!empty($item['divider'])) {
            $out[] = $item;
            continue;
        }

        if ($visible) {
            $out[] = $item;
        }
    }

    // Ordena irmãos por "order"
    usort($out, 'mozart_menu_sort');

    return $out;
}

/**
 * Ordenação por campo "order"
 */
function mozart_menu_sort(array $a, array $b): int
{
    $oa = $a['order'] ?? 0;
    $ob = $b['order'] ?? 0;

    return $oa <=> $ob;
}
