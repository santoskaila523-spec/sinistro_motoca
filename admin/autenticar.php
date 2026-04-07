<?php
session_start();
include "../config/db.php";
require "../config/admin_permissoes.php";
require "../config/app_log.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$usuario = trim((string)($_POST['usuario'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($usuario === '' || $senha === '') {
    appLogTentativaLoginAdmin($usuario, false, ['motivo' => 'credenciais_vazias']);
    header("Location: login.php?erro=1");
    exit;
}

$sql = $conn->prepare("SELECT * FROM admins WHERE usuario = ? LIMIT 1");
if (!$sql) {
    appLogTentativaLoginAdmin($usuario, false, ['motivo' => 'falha_prepare']);
    header("Location: login.php?erro=1");
    exit;
}

$sql->bind_param("s", $usuario);
if (!$sql->execute()) {
    $sql->close();
    appLogTentativaLoginAdmin($usuario, false, ['motivo' => 'falha_execute']);
    header("Location: login.php?erro=1");
    exit;
}

$result = $sql->get_result();
if ($result->num_rows === 1) {
    $admin = $result->fetch_assoc();

    if (password_verify($senha, (string)$admin['senha'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logado'] = true;
        $_SESSION['admin_usuario'] = (string)$admin['usuario'];
        $_SESSION['admin_perfil'] = normalizarPerfilAdmin((string)($admin['perfil'] ?? 'diretor'));
        $_SESSION['admin_login_em'] = time();
        $_SESSION['admin_ultima_atividade'] = time();
        $_SESSION['admin_ultimo_regenerate'] = time();
        appLogTentativaLoginAdmin($usuario, true, ['perfil' => $_SESSION['admin_perfil']]);
        $sql->close();
        header("Location: painel.php");
        exit;
    }
}

$sql->close();
appLogTentativaLoginAdmin($usuario, false, ['motivo' => 'senha_invalida']);
header("Location: login.php?erro=1");
exit;
