<?php

// Verifica se o usuário está logado
function proteger_pagina() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/pages/login.php");
        exit();
    }
}
?>