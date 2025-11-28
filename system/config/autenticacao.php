<?php
// NUNCA deixe espaços/linhas acima deste <?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Aqui assumo que ROOT_PATH e BASE_URL vêm do config.php
// Se não vierem, garante que o config foi incluído ANTES deste arquivo.

// 1) Loader dos manifests
require_once ROOT_PATH . '/system/includes/manifest/manifest_loader.php';
mozart_manifest_bootstrap();

/**
 * 2) Função de proteção de login (como você já usava)
 */
function proteger_pagina() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

function usuario_tem_capabilities(array $requiredCaps): bool {
    if (empty($requiredCaps)) {
        return true;
    }

    // superadmin vê tudo
    if (!empty($_SESSION['is_superadmin'])) {
        return true;
    }

    $userPerms = $_SESSION['user_perms_map'] ?? [];

    if (!$userPerms) {
        return false;
    }

    // wildcard global
    if (!empty($userPerms['*'])) {
        return true;
    }

    // Se pelo menos UMA das requiredCaps estiver permitida, OK
    foreach ($requiredCaps as $cap) {
        if (!empty($userPerms[$cap])) {
            return true;
        }
    }

    return false;
}


/**
 * 4) Tela de acesso negado (simples, depois estilizamos)
 */
function mozart_acesso_negado() {
    http_response_code(403);
    echo '<h1>Acesso negado</h1>';
    echo '<p>Você não tem permissão para acessar esta funcionalidade.</p>';
    exit;
}

// 5) Primeiro: garante que o usuário está logado
proteger_pagina();

// 5) Primeiro: garante que o usuário está logado
proteger_pagina();

// SUPERADMIN DE HOMOLOG (apenas enquanto estamos desenvolvendo)
// Se ainda não houver mapa de permissões na sessão, libera tudo.
if (!isset($_SESSION['user_perms_map'])) {
    $_SESSION['is_superadmin']   = true;
    $_SESSION['user_perms_map'] = ['*' => true];
}


// 6) Depois: aplica RBAC por rota (exceto páginas públicas)
$currentPath = $_SERVER['SCRIPT_NAME'] ?? ''; 
// Ex.: /modules/helpdesk/pages/tickets_listar.php

// Páginas que NÃO devem passar pelo RBAC (só login/esqueci senha, etc.)
$rbac_skip = [
    '/pages/login.php',
    '/pages/esqueci_senha.php',
    // coloque aqui qualquer outra página pública
];

if (!in_array($currentPath, $rbac_skip, true)) {

    $routeMeta = mozart_get_route_meta($currentPath);

    if ($routeMeta && !empty($routeMeta['requires'])) {
        $requiredCaps = (array) $routeMeta['requires'];

        if (!usuario_tem_capabilities($requiredCaps)) {
            mozart_acesso_negado();
        }
    }
}

function mozart_get_current_user_capabilities(): array {
    // 1) se tiver level_id, carrega caps do nível (acl_level_caps)
    if (!empty($_SESSION['user_level_id'])) {
        return mozart_load_caps_from_level($_SESSION['user_level_id']);
    }

    // 2) fallback antigo: se nivel_acesso = bigboss => tudo
    if (!empty($_SESSION['user_nivel']) && $_SESSION['user_nivel'] === 'bigboss') {
        return ['*'];
    }

    // 3) outros enums (admin/gerente/usuario) mapeados para pacotes padrão
    // ou apenas ['front:*'] etc.
    return [];
}
