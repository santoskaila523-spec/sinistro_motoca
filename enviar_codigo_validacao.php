<?php
session_start();

require __DIR__ . '/config/email_transport.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$destino = trim((string)($_POST['destino'] ?? ''));
$tipoFormulario = trim((string)($_POST['tipo_formulario'] ?? ''));

if (!in_array($tipoFormulario, ['locatario', 'terceiro'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Tipo de formulário inválido.']);
    exit;
}

if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Informe um e-mail válido para receber o código.']);
    exit;
}

$agora = time();
$controle = $_SESSION['codigo_validacao_envio_controle'] ?? [];
$destinoNormalizado = function_exists('mb_strtolower') ? mb_strtolower($destino, 'UTF-8') : strtolower($destino);
$chaveControle = 'email:' . $destinoNormalizado;
$ultimoEnvio = (int)($controle[$chaveControle] ?? 0);

if ($ultimoEnvio > 0 && ($agora - $ultimoEnvio) < 60) {
    $segundosRestantes = 60 - ($agora - $ultimoEnvio);
    http_response_code(429);
    echo json_encode(['ok' => false, 'msg' => 'Aguarde ' . $segundosRestantes . 's para reenviar o código.']);
    exit;
}

$codigo = (string)random_int(100000, 999999);
$assunto = 'Código de validação - MOTOCA';
$tipoLegivel = $tipoFormulario === 'terceiro' ? 'terceiro' : 'locatário';

$html = '
    <html>
    <body style="font-family: Arial, sans-serif; color: #111;">
        <h2 style="margin-bottom: 8px;">Código de validação</h2>
        <p>Recebemos uma solicitação de validação do formulário de sinistro (' . htmlspecialchars($tipoLegivel, ENT_QUOTES, 'UTF-8') . ').</p>
        <p>Use o código abaixo para concluir o envio:</p>
        <div style="margin: 18px 0; padding: 18px; border-radius: 16px; background: #fff7bf; border: 1px solid #f0df69; font-size: 28px; font-weight: 800; letter-spacing: .18em; text-align: center;">'
            . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') .
        '</div>
        <p>Esse código expira em 15 minutos.</p>
        <p style="font-size: 12px; color: #666;">MOTOCA - Validação automática</p>
    </body>
    </html>
';

[$ok, $mensagem] = emailTransportEnviarHtml($destino, $assunto, $html);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $mensagem !== '' ? $mensagem : 'Não foi possível enviar o código agora.']);
    exit;
}

$_SESSION['sinistro_codigo_validacao'] = [
    'codigo' => $codigo,
    'canal' => 'email',
    'destino' => $destinoNormalizado,
    'tipo_formulario' => $tipoFormulario,
    'expira_em' => $agora + (15 * 60),
];

$controle[$chaveControle] = $agora;
$_SESSION['codigo_validacao_envio_controle'] = $controle;

echo json_encode([
    'ok' => true,
    'msg' => 'Código enviado para o e-mail informado.',
]);
