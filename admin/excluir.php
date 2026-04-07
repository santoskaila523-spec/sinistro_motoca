<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

include "../config/db.php";
require "../config/admin_permissoes.php";
require "../config/admin_auditoria.php";
require "../config/app_log.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: painel.php");
    exit;
}

$token = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit("Token CSRF inválido.");
}

$adminPerfil = normalizarPerfilAdmin((string)($_SESSION['admin_perfil'] ?? 'diretor'));
if (!adminPodeExcluirSinistro($adminPerfil)) {
    http_response_code(403);
    exit("Acesso negado. Seu perfil não tem permissão para excluir sinistros.");
}

$registroParam = trim((string)($_POST['registro'] ?? ''));
$id = 0;
$adminUsuario = (string)($_SESSION['admin_usuario'] ?? '');
$statusAnterior = null;

if ($registroParam !== '') {
    $stmt = $conn->prepare("SELECT id, status FROM sinistros WHERE numero_registro = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $registroParam);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $id = (int)($row['id'] ?? 0);
        $statusAnterior = isset($row['status']) ? (string)$row['status'] : null;
    }
}

if ($id <= 0) {
    die("Sinistro não encontrado.");
}

$del = $conn->prepare("DELETE FROM sinistros WHERE id = ?");
if ($del) {
    registrarAuditoriaSinistro(
        $conn,
        $id,
        'exclusao_sinistro',
        $statusAnterior,
        null,
        'Sinistro excluído pelo painel administrativo.',
        $adminUsuario
    );
    appLogEvento('sinistro_excluido', [
        'sinistro_id' => $id,
        'registro' => $registroParam,
        'admin_usuario' => $adminUsuario,
    ]);
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
}

$pasta = "../uploads/sinistros/$id/";
if (is_dir($pasta)) {
    $arquivos = glob("$pasta/*");
    if ($arquivos !== false) {
        array_map('unlink', $arquivos);
    }
    rmdir($pasta);
}

header("Location: painel.php");
exit;
