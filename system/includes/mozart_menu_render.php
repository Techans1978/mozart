<?php
// NUNCA deixe espaços acima deste <?php

/**
 * Renderiza a árvore completa de menus no sidebar.
 */
function renderSidebarMenu(array $items): void
{
    foreach ($items as $item) {
        renderSidebarItem($item);
    }
}

/**
 * Detecta se o item deve ser marcado como ativo.
 */
function mozart_menu_is_active(string $url): bool
{
    if ($url === '#' || trim($url) === '') {
        return false;
    }

    $current = $_SERVER['REQUEST_URI'] ?? '';
    $urlPath = parse_url($url, PHP_URL_PATH);

    return $urlPath && str_contains($current, $urlPath);
}

/**
 * Renderiza um item individual, podendo ter submenus.
 */
function renderSidebarItem(array $item, int $depth = 1): void
{
    // Divider
    if (!empty($item['divider'])) {
        echo '<li class="nav-divider"></li>';
        return;
    }

    $label      = $item['label'] ?? 'Item';
    $icon       = $item['icon'] ?? '';
    $url        = $item['url'] ?? '#';
    $children   = $item['children'] ?? [];
    $hasChild   = !empty($children);
    $active     = mozart_menu_is_active($url);
    $activeClass = $active ? 'active' : '';

    // UL de children conforme nível
    $ulClass = match ($depth) {
        1       => 'nav nav-second-level',
        2       => 'nav nav-third-level',
        default => 'nav nav-level-' . $depth,
    };

    echo '<li class="' . $activeClass . '">';

    echo '<a href="' . htmlspecialchars($url) . '">';

    // Ícone (SVG ou font)
    if ($icon) {
        if (str_contains($icon, 'ti ') || str_contains($icon, 'fa ')) {
            echo '<i class="' . $icon . '"></i> ';
        } else {
            echo '<i class="' . $icon . '"></i> ';
        }
    }

    echo htmlspecialchars($label);

    if ($hasChild) {
        echo ' <span class="fa arrow"></span>';
    }

    echo '</a>';

    // Renderiza filhos
    if ($hasChild) {
        echo '<ul class="' . $ulClass . '">';
        foreach ($children as $child) {
            renderSidebarItem($child, $depth + 1);
        }
        echo '</ul>';
    }

    echo '</li>';
}
