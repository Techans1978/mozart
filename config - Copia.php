<?php
// Caminho da subpasta do sistema
define('APP_SUBDIR', '');

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
$subdir = trim(APP_SUBDIR, DIRECTORY_SEPARATOR);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $docRoot . DIRECTORY_SEPARATOR . $subdir);
}

define('MOZART_DATA_PATH', '/home/mozarth1super/data_files');

// Caminho base da aplicação (URL)
define('BASE_URL', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . APP_SUBDIR);

// Nome do sistema (opcional)
define('APP_NAME', 'Mozart - Orquestrador Empresarial');

// Linguagem do sistema (opcional)
define('SITE_LANG', 'pt_BR');

// Codificação de Linguagem (opcional)
define('SITE_UTF', 'utf-8');

// Meta Description
define('SITE_DESCR', 'Mozart – Orquestrador Empresarial integra processos, sistemas e pessoas, trazendo eficiência, agilidade e controle para sua empresa.');

// Meta Autor
define('SITE_AUTOR', 'Sistemas e Digital Grupo ABC');

// Zona de tempo
date_default_timezone_set('America/Sao_Paulo');

// Iniciar sessão globalmente (se ainda não iniciado)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
