<?php
$host = "localhost";
$user = "root";
$pass = ""; // no XAMPP normalmente e vazio
$db   = "sinistro_motoca"; // TEM QUE EXISTIR

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erro de conexao: " . $conn->connect_error);
}
