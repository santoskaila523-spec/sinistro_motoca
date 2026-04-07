<?php
require_once __DIR__ . '/config/db.php';

$protocolo = preg_replace('/\D/', '', (string)($_GET['protocolo'] ?? ''));
$erro = '';
$resultado = null;

if ($protocolo !== '') {
    $stmt = $conn->prepare("SELECT numero_registro, status, tipo_formulario, data_hora, cidade FROM sinistros WHERE numero_registro = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $protocolo);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $resultado = $res ? $res->fetch_assoc() : null;
            if (!$resultado) {
                $erro = "Protocolo não encontrado.";
            }
        } else {
            $erro = "Não foi possível consultar o protocolo agora. Tente novamente.";
        }
        $stmt->close();
    } else {
        $erro = "Não foi possível consultar o protocolo agora. Tente novamente.";
    }
}

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function formatarStatus($status) {
    $texto = str_replace("_", " ", (string)$status);
    return mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
}

function formatarDataHora($valor) {
    if (!$valor) return '';
    $ts = strtotime((string)$valor);
    if (!$ts) return (string)$valor;
    return date('d/m/Y H:i', $ts);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Consultar Protocolo | MOTOCA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/consulta.css">
</head>
<body class="consulta-page">
    <div class="container">
        <img src="assets/img/logo-site-3d.png" class="logo logo-site" alt="Motoca">

        <div class="hero">
            <span class="eyebrow">Consulta</span>
            <h1>Consultar protocolo</h1>
            <p>Informe o número de protocolo para visualizar o status do registro.</p>
        </div>

        <form class="consulta-form" method="get" action="consulta.php" autocomplete="off">
            <label for="protocolo">Número do protocolo</label>
            <input
                type="text"
                id="protocolo"
                name="protocolo"
                placeholder="Ex: 2026000123"
                maxlength="10"
                value="<?= h($protocolo) ?>"
                inputmode="numeric"
                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                required
            >
            <button type="submit">Consultar</button>
        </form>

        <?php if ($erro !== ''): ?>
            <div class="resultado resultado-erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <?php if ($resultado): ?>
            <?php
            $statusAtual = strtolower(trim((string)($resultado['status'] ?? '')));
            $rotuloFinal = in_array($statusAtual, ['processo_juridico', 'arquivado'], true) ? 'Encerrado' : 'Concluído';
            $etapas = [
                'Registro enviado',
                'Em análise',
                $rotuloFinal,
            ];
            $etapaAtual = 0;
            if ($statusAtual === 'em_andamento') {
                $etapaAtual = 1;
            } elseif (in_array($statusAtual, ['finalizado', 'processo_juridico', 'arquivado'], true)) {
                $etapaAtual = 2;
            }
            ?>
            <div class="resultado">
                <div class="resultado-header">
                    <h2>Resultado da consulta</h2>
                    <span class="badge"><?= h($resultado['numero_registro']) ?></span>
                </div>
                <div class="timeline" role="list">
                    <?php foreach ($etapas as $indice => $rotulo): ?>
                        <?php
                        $classe = '';
                        if ($indice < $etapaAtual) {
                            $classe = 'is-done';
                        } elseif ($indice === $etapaAtual) {
                            $classe = 'is-active';
                        }
                        ?>
                        <div class="timeline-step <?= $classe ?>" role="listitem">
                            <span class="timeline-dot" aria-hidden="true"></span>
                            <span class="timeline-label"><?= h($rotulo) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="resultado-grid">
                    <div>
                        <strong>Status</strong>
                        <span><?= h(formatarStatus($resultado['status'] ?? '')) ?></span>
                    </div>
                    <div>
                        <strong>Tipo de registro</strong>
                        <span><?= h(ucfirst((string)($resultado['tipo_formulario'] ?? ''))) ?></span>
                    </div>
                    <div>
                        <strong>Data e horário</strong>
                        <span><?= h(formatarDataHora($resultado['data_hora'] ?? '')) ?></span>
                    </div>
                    <div>
                        <strong>Cidade</strong>
                        <span><?= h($resultado['cidade'] ?? '-') ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <a href="index.php" class="voltar">Voltar para o início</a>
    </div>
</body>
</html>
