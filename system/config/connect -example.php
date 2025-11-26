<?php
$servername = "localhost";
$username = "user_bd";
$password = "senha_bd";
$dbname = "nome_bd";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Erro na conexão com o banco: " . $conn->connect_error);
}
?>