<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

require "../config/db.php";
require "../config/admin_permissoes.php";
require "../config/admin_auditoria.php";

if (!tabelaAuditoriaSinistroExiste($conn)) {
    exit('A tabela de auditoria ainda não foi criada.');
}

$adminPerfilLogado = normalizarPerfilAdmin((string)($_SESSION['admin_perfil'] ?? 'diretor'));
if (!adminPodeAcessarAuditoria($adminPerfilLogado)) {
    http_response_code(403);
    exit('Acesso negado.');
}

if (!autorizacaoSensivelValida('autorizacao_auditoria', (string)($_SESSION['admin_usuario'] ?? ''))) {
    http_response_code(403);
    exit('Autorização da auditoria expirada. Reabra a tela da auditoria e confirme sua senha novamente.');
}

function eExcel($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function numeroRegistroExibicaoExcel(array $row): string
{
    if (!empty($row['numero_registro'])) {
        return (string)$row['numero_registro'];
    }

    $ano = '0000';
    $ts = isset($row['sinistro_criado_em']) ? strtotime((string)$row['sinistro_criado_em']) : false;
    if ($ts !== false) {
        $ano = date('Y', $ts);
    }

    return $ano . str_pad((string)((int)($row['sinistro_id'] ?? 0)), 6, '0', STR_PAD_LEFT);
}

$registroBusca = preg_replace('/\D/', '', (string)($_GET['registro'] ?? ''));
$acaoBusca = trim((string)($_GET['acao'] ?? ''));
$usuarioBusca = trim((string)($_GET['usuario'] ?? ''));
$statusAnteriorBusca = trim((string)($_GET['status_anterior'] ?? ''));
$statusNovoBusca = trim((string)($_GET['status_novo'] ?? ''));
$dataInicio = (string)($_GET['data_inicio'] ?? '');
$dataFim = (string)($_GET['data_fim'] ?? '');

$acoesPermitidas = [
    'abertura_sinistro',
    'alteracao_status',
    'exclusao_sinistro',
];

$statusPermitidos = [
    'em_andamento',
    'finalizado',
    'processo_juridico',
    'arquivado',
];

$where = [];
$sqlBase = "
    FROM sinistro_historico h
    LEFT JOIN sinistros s ON s.id = h.sinistro_id
";

if ($registroBusca !== '') {
    $registroEsc = $conn->real_escape_string($registroBusca);
    $where[] = "COALESCE(s.numero_registro, '') LIKE '%{$registroEsc}%'";
}

if ($acaoBusca !== '' && in_array($acaoBusca, $acoesPermitidas, true)) {
    $acaoEsc = $conn->real_escape_string($acaoBusca);
    $where[] = "h.acao = '{$acaoEsc}'";
}

if ($usuarioBusca !== '') {
    $usuarioEsc = $conn->real_escape_string($usuarioBusca);
    $where[] = "COALESCE(h.admin_usuario, '') LIKE '%{$usuarioEsc}%'";
}

if ($statusAnteriorBusca !== '' && in_array($statusAnteriorBusca, $statusPermitidos, true)) {
    $statusAnteriorEsc = $conn->real_escape_string($statusAnteriorBusca);
    $where[] = "COALESCE(h.valor_anterior, '') = '{$statusAnteriorEsc}'";
}

if ($statusNovoBusca !== '' && in_array($statusNovoBusca, $statusPermitidos, true)) {
    $statusNovoEsc = $conn->real_escape_string($statusNovoBusca);
    $where[] = "COALESCE(h.valor_novo, '') = '{$statusNovoEsc}'";
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
    $where[] = "DATE(h.criado_em) >= '" . $conn->real_escape_string($dataInicio) . "'";
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    $where[] = "DATE(h.criado_em) <= '" . $conn->real_escape_string($dataFim) . "'";
}

if ($where) {
    $sqlBase .= " WHERE " . implode(" AND ", $where);
}

$sql = "
    SELECT
        h.id,
        h.sinistro_id,
        h.acao,
        h.valor_anterior,
        h.valor_novo,
        h.observacao,
        h.admin_usuario,
        h.criado_em,
        s.numero_registro,
        s.nome,
        s.criado_em AS sinistro_criado_em
    " . $sqlBase . "
    ORDER BY h.criado_em DESC, h.id DESC
";

$result = $conn->query($sql);
if (!$result) {
    exit('Erro ao exportar auditoria.');
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=auditoria_sinistros.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
        <th>Data</th>
        <th>Registro</th>
        <th>Nome</th>
        <th>Ação</th>
        <th>Usuário Admin</th>
        <th>Valor Anterior</th>
        <th>Valor Novo</th>
        <th>Observação</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    $data = eExcel((string)($row['criado_em'] ?? ''));
    $registro = eExcel(numeroRegistroExibicaoExcel($row));
    $nome = eExcel((string)($row['nome'] ?? ''));
    $acao = eExcel(formatarAcaoAuditoria((string)($row['acao'] ?? '')));
    $usuario = eExcel((string)($row['admin_usuario'] ?? ''));
    $valorAnterior = eExcel((string)($row['valor_anterior'] ?? ''));
    $valorNovo = eExcel((string)($row['valor_novo'] ?? ''));
    $observacao = eExcel((string)($row['observacao'] ?? ''));

    echo "<tr>
            <td>{$data}</td>
            <td>{$registro}</td>
            <td>{$nome}</td>
            <td>{$acao}</td>
            <td>{$usuario}</td>
            <td>{$valorAnterior}</td>
            <td>{$valorNovo}</td>
            <td>{$observacao}</td>
          </tr>";
}

echo "</table>";
exit;
