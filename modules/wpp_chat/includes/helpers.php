<?php
// public/modules/wpp_chat/includes/helpers.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function wpp_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function wpp_csrf_token() {
    if (empty($_SESSION['wpp_csrf'])) {
        $_SESSION['wpp_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['wpp_csrf'];
}

function wpp_csrf_check($token) {
    return isset($_SESSION['wpp_csrf']) && hash_equals($_SESSION['wpp_csrf'], $token);
}

function wpp_redirect($url) {
    header('Location: '.$url);
    exit;
}

function wpp_flash($key, $msg = null) {
    if ($msg === null) {
        if (!empty($_SESSION['wpp_flash'][$key])) {
            $m = $_SESSION['wpp_flash'][$key];
            unset($_SESSION['wpp_flash'][$key]);
            return $m;
        }
        return null;
    }
    $_SESSION['wpp_flash'][$key] = $msg;
}

function wpp_paginate($page, $per_page, $total) {
    $page = max(1, (int)$page);
    $pages = max(1, (int)ceil($total / $per_page));
    $page = min($page, $pages);
    $offset = ($page - 1) * $per_page;
    return compact('page','pages','per_page','total','offset');
}

/**
 * Normaliza número de telefone para padrão internacional (bem simplificado).
 * Ajuste conforme sua regra (ex: sempre +55...).
 */
function wpp_normalize_phone($phone) {
    $digits = preg_replace('/\D+/', '', $phone);
    if (substr($digits, 0, 2) === '55') {
        return '+'.$digits;
    }
    // Assumir Brasil se tiver 10-11 dígitos
    if (strlen($digits) >= 10 && strlen($digits) <= 11) {
        return '+55'.$digits;
    }
    return '+'.$digits;
}

// Busca configuração da instância no banco
if (!function_exists('wpp_get_instance_config')) {
    /**
     * Retorna o array da instância (linha da tabela moz_wpp_instance)
     * ou null se não existir / não estiver ativa.
     */
    function wpp_get_instance_config(mysqli $conn, int $id): ?array
    {
        $sql = "SELECT *
                  FROM moz_wpp_instance
                 WHERE id = ?
                   AND ativo = 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            return null;
        }

        $res = $stmt->get_result();
        if (!$res) {
            return null;
        }

        $row = $res->fetch_assoc();
        return $row ?: null;
    }
}

