<?php
ini_set('display_errors',1); error_reporting(E_ALL);
echo "ping 1<br>";
require_once dirname(__DIR__, 2) . '/config.php';
echo "ping 2 ROOT_PATH=".(defined('ROOT_PATH')?ROOT_PATH:'(sem ROOT_PATH)')."<br>";
require_once ROOT_PATH . '/system/config/connect.php';
echo "ping 3 DB OK<br>";
if (file_exists(ROOT_PATH . '/system/includes/autenticacao.php')) {
  require_once ROOT_PATH . '/system/includes/autenticacao.php';
  echo "ping 4 AUTH OK<br>";
} else {
  echo "ping 4 AUTH NAO ENCONTRADO<br>";
}
echo "ping fim<br>";
?>