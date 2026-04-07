<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/email_transport.php';

$cfg = emailTransportCarregarConfig();
$msg = '';
$ok = null;
$transportAtual = strtolower((string)($cfg['transport'] ?? 'disabled'));
$envioDesativado = in_array($transportAtual, ['disabled', 'off', 'none'], true);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $to = trim((string)($_POST['email_destino'] ?? ''));

    if (!hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
        $ok = false;
        $msg = 'Sessão inválida. Recarregue a página e tente novamente.';
    } elseif ($envioDesativado) {
        $ok = false;
        $msg = 'O envio de e-mail está desativado na configuração atual.';
    } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $ok = false;
        $msg = 'Informe um e-mail válido.';
    } else {
        $assunto = 'Validação de envio - MOTOCA';
        $html = '<p>Validação de envio executada em ' . date('d/m/Y H:i:s') . '.</p>'
            . '<p><strong>Transporte:</strong> ' . htmlspecialchars($transportAtual, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Remetente:</strong> ' . htmlspecialchars((string)($cfg['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
        [$ok, $msg] = emailTransportEnviarHtml($to, $assunto, $html);
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de E-mail</title>
    <style>
        body { font-family: Arial, sans-serif; background:#111; color:#fff; padding:32px; }
        .box { max-width: 640px; margin: 0 auto; background:#1b1b1b; padding:24px; border-radius:12px; border:1px solid #333; }
        input, button { width:100%; padding:12px; border-radius:8px; border:1px solid #444; margin-top:10px; }
        button { background:#f5e500; color:#000; font-weight:700; cursor:pointer; border:none; }
        button:disabled, input:disabled { opacity:.6; cursor:not-allowed; }
        .ok { color:#86efac; }
        .err { color:#fca5a5; }
        .meta { color:#d1d5db; font-size:14px; margin-top:12px; line-height:1.5; }
        code, pre { background:#0f0f0f; padding:2px 4px; border-radius:4px; }
        a { color:#f5e500; }
    </style>
</head>
<body>
<div class="box">
    <h1>Validação de E-mail</h1>
    <p>Valida a configuração de <code>config/email.php</code> usando o mesmo transporte do formulário.</p>
    <p class="meta">
        Transporte atual: <strong><?= htmlspecialchars((string)($cfg['transport'] ?? 'graph'), ENT_QUOTES, 'UTF-8') ?></strong><br>
        Remetente: <strong><?= htmlspecialchars((string)($cfg['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
    </p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <label for="email_destino">E-mail de destino</label>
        <input id="email_destino" name="email_destino" type="email" required placeholder="voce@dominio.com" <?= $envioDesativado ? 'disabled' : '' ?>>
        <button type="submit" <?= $envioDesativado ? 'disabled' : '' ?>>Enviar validação</button>
    </form>
    <?php if ($ok === true): ?><p class="ok"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($ok === false): ?><p class="err"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <p class="meta">
        Para Microsoft Graph, preencha em <code>config/email.php</code>:
        <code>tenant_id</code>, <code>client_id</code>, <code>client_secret</code> e, se necessário, <code>mailbox_user_id</code>.
    </p>
    <?php if ($envioDesativado): ?>
        <p class="meta">O transporte de e-mail está desativado no momento. Enquanto isso, nenhum envio será realizado.</p>
    <?php endif; ?>
    <p><a href="painel.php">Voltar ao painel</a></p>
</div>
</body>
</html>
