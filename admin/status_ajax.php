<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
header("Content-Type: application/json");

if (!isset($_SESSION['admin_logado'])) {
    echo json_encode(["ok"=>false]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["ok"=>false]);
    exit;
}

require "../config/db.php";
require "../config/admin_auditoria.php";
require "../config/app_log.php";
require "../config/sinistro_vinculo.php";

function normalizarValorMonetario(?string $valor): ?string
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    $valor = preg_replace('/[^\d,.\-]/', '', $valor);
    if ($valor === null || $valor === '') {
        return null;
    }

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    if (!is_numeric($valor)) {
        return null;
    }

    return number_format((float)$valor, 2, '.', '');
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    echo json_encode(["ok"=>false]);
    exit;
}

$csrf = (string)($data['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    echo json_encode(["ok"=>false]);
    exit;
}

$id = intval($data['id'] ?? 0);
$acao = (string)($data['acao'] ?? 'salvar_status');
$status = $data['status'] ?? '';
$observacao = trim((string)($data['observacao'] ?? ''));
$lancouFranquia = strtolower(trim((string)($data['lancou_franquia'] ?? '')));
$valorFranquia = normalizarValorMonetario($data['valor_franquia'] ?? null);
$valorIndenizacao = normalizarValorMonetario($data['valor_indenizacao'] ?? null);
$adminUsuario = (string)($_SESSION['admin_usuario'] ?? '');

if (mb_strlen($observacao) > 500) {
    $observacao = mb_substr($observacao, 0, 500);
}

$permitidos = [
    'em_andamento',
    'finalizado',
    'processo_juridico',
    'arquivado'
];
$statusComMotivoObrigatorio = [
    'processo_juridico',
    'arquivado',
];

if (!$id || !in_array($status, $permitidos, true)) {
    echo json_encode(["ok"=>false]);
    exit;
}

if (in_array($status, $statusComMotivoObrigatorio, true) && $observacao === '') {
    echo json_encode([
        "ok" => false,
        "msg" => "Informe o motivo ao definir o status como " . str_replace('_', ' ', $status) . "."
    ]);
    exit;
}

if ($lancouFranquia !== '' && !in_array($lancouFranquia, ['sim', 'nao'], true)) {
    echo json_encode(["ok" => false, "msg" => "Selecione corretamente se houve lançamento de franquia."]);
    exit;
}

if ($lancouFranquia === 'sim' && $valorFranquia === null) {
    echo json_encode(["ok" => false, "msg" => "Informe o valor da franquia."]);
    exit;
}

if ($lancouFranquia === 'nao') {
    $valorFranquia = null;
}

if (($data['valor_indenizacao'] ?? '') !== '' && $valorIndenizacao === null) {
    echo json_encode(["ok" => false, "msg" => "Informe um valor de indenização válido."]);
    exit;
}

$statusAnterior = null;
$franquiaAnterior = null;
$valorFranquiaAnterior = null;
$valorIndenizacaoAnterior = null;
$colunasConsulta = ['status'];
if (sinistroTabelaTemColuna($conn, 'lancou_franquia')) {
    $colunasConsulta[] = 'lancou_franquia';
}
if (sinistroTabelaTemColuna($conn, 'valor_franquia')) {
    $colunasConsulta[] = 'valor_franquia';
}
if (sinistroTabelaTemColuna($conn, 'valor_indenizacao')) {
    $colunasConsulta[] = 'valor_indenizacao';
}
$stmtAtual = $conn->prepare("SELECT " . implode(', ', $colunasConsulta) . " FROM sinistros WHERE id = ? LIMIT 1");
if ($stmtAtual) {
    $stmtAtual->bind_param("i", $id);
    $stmtAtual->execute();
    $rowAtual = $stmtAtual->get_result()->fetch_assoc();
    $stmtAtual->close();
    $statusAnterior = isset($rowAtual['status']) ? (string)$rowAtual['status'] : null;
    $franquiaAnterior = isset($rowAtual['lancou_franquia']) ? (string)$rowAtual['lancou_franquia'] : null;
    $valorFranquiaAnterior = isset($rowAtual['valor_franquia']) && $rowAtual['valor_franquia'] !== null ? number_format((float)$rowAtual['valor_franquia'], 2, '.', '') : null;
    $valorIndenizacaoAnterior = isset($rowAtual['valor_indenizacao']) && $rowAtual['valor_indenizacao'] !== null ? number_format((float)$rowAtual['valor_indenizacao'], 2, '.', '') : null;
}

if ($statusAnterior === null) {
    echo json_encode(["ok"=>false, "msg"=>"Sinistro não encontrado."]);
    exit;
}

/* Compatibilidade: só aplica ciclo de prazo se as colunas existirem */
$temPrazoInicio = $conn->query("SHOW COLUMNS FROM sinistros LIKE 'prazo_andamento_inicio'");
$temPrazoLimite = $conn->query("SHOW COLUMNS FROM sinistros LIKE 'prazo_limite_dias'");
$usaCicloPrazo = ($temPrazoInicio && $temPrazoInicio->num_rows > 0) &&
                 ($temPrazoLimite && $temPrazoLimite->num_rows > 0);

if ($usaCicloPrazo) {
    if ($status === 'em_andamento') {
        // Novo ciclo: após atualização manual mantendo/reabrindo em andamento, passa a alertar em 10 dias.
        $stmt = $conn->prepare("
            UPDATE sinistros
               SET status = ?,
                   prazo_andamento_inicio = NOW(),
                   prazo_limite_dias = 10
             WHERE id = ?
        ");
    } else {
        // Ao sair de andamento, o prazo some.
        $stmt = $conn->prepare("
            UPDATE sinistros
               SET status = ?,
                   prazo_andamento_inicio = NULL,
                   prazo_limite_dias = NULL
             WHERE id = ?
        ");
    }
} else {
    $stmt = $conn->prepare("UPDATE sinistros SET status=? WHERE id=?");
}

if (!$stmt) {
    echo json_encode(["ok"=>false]);
    exit;
}

$stmt->bind_param("si", $status, $id);
$ok = $stmt->execute();

if (!$ok) {
    echo json_encode(["ok"=>false]);
    exit;
}

$mensagens = [];

if ($statusAnterior !== $status || $observacao !== '') {
    registrarAuditoriaSinistro(
        $conn,
        $id,
        'alteracao_status',
        $statusAnterior,
        (string)$status,
        $observacao !== '' ? $observacao : 'Status atualizado pelo painel administrativo.',
        $adminUsuario
    );
    appLogEvento('status_sinistro_atualizado', [
        'sinistro_id' => $id,
        'status_anterior' => $statusAnterior,
        'status_novo' => (string)$status,
        'admin_usuario' => $adminUsuario,
    ]);
    $mensagens[] = "Status atualizado com sucesso.";
}

if (
    sinistroTabelaTemColuna($conn, 'lancou_franquia') &&
    sinistroTabelaTemColuna($conn, 'valor_franquia') &&
    sinistroTabelaTemColuna($conn, 'valor_indenizacao')
) {
    $stmtFinanceiro = $conn->prepare("
        UPDATE sinistros
           SET lancou_franquia = ?,
               valor_franquia = ?,
               valor_indenizacao = ?
         WHERE id = ?
    ");

    if (!$stmtFinanceiro) {
        echo json_encode(["ok" => false, "msg" => "Nao foi possivel salvar os dados de franquia e indenizacao."]);
        exit;
    }

    $lancouFranquiaDb = $lancouFranquia !== '' ? $lancouFranquia : null;
    $stmtFinanceiro->bind_param("sssi", $lancouFranquiaDb, $valorFranquia, $valorIndenizacao, $id);
    $okFinanceiro = $stmtFinanceiro->execute();
    $stmtFinanceiro->close();

    if (!$okFinanceiro) {
        echo json_encode(["ok" => false, "msg" => "Nao foi possivel salvar os dados de franquia e indenizacao."]);
        exit;
    }

    $houveMudancaFinanceira =
        $franquiaAnterior !== $lancouFranquiaDb ||
        $valorFranquiaAnterior !== $valorFranquia ||
        $valorIndenizacaoAnterior !== $valorIndenizacao;

    if ($houveMudancaFinanceira) {
        $valorAnteriorResumo = trim(implode(' | ', array_filter([
            $franquiaAnterior !== null && $franquiaAnterior !== '' ? 'Franquia lancada: ' . $franquiaAnterior : null,
            $valorFranquiaAnterior !== null ? 'Valor franquia: ' . $valorFranquiaAnterior : null,
            $valorIndenizacaoAnterior !== null ? 'Indenizacao: ' . $valorIndenizacaoAnterior : null,
        ])));
        $valorNovoResumo = trim(implode(' | ', array_filter([
            $lancouFranquiaDb !== null && $lancouFranquiaDb !== '' ? 'Franquia lancada: ' . $lancouFranquiaDb : null,
            $valorFranquia !== null ? 'Valor franquia: ' . $valorFranquia : null,
            $valorIndenizacao !== null ? 'Indenizacao: ' . $valorIndenizacao : null,
        ])));

        registrarAuditoriaSinistro(
            $conn,
            $id,
            'atualizacao_financeira',
            $valorAnteriorResumo !== '' ? $valorAnteriorResumo : null,
            $valorNovoResumo !== '' ? $valorNovoResumo : null,
            'Dados financeiros do sinistro atualizados.',
            $adminUsuario
        );

        appLogEvento('financeiro_sinistro_atualizado', [
            'sinistro_id' => $id,
            'lancou_franquia' => $lancouFranquiaDb,
            'valor_franquia' => $valorFranquia,
            'valor_indenizacao' => $valorIndenizacao,
            'admin_usuario' => $adminUsuario,
        ]);
        $mensagens[] = "Dados financeiros atualizados com sucesso.";
    }
}

if ($mensagens === []) {
    $mensagens[] = "Nenhuma alteracao foi realizada.";
}

echo json_encode(["ok"=>true, "msg"=>implode(' ', $mensagens)]);


