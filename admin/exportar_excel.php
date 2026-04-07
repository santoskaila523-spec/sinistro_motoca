<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

include "../config/db.php";
require "../config/placas_motoca.php";

function numeroRegistro(array $row): string
{
    if (!empty($row['numero_registro'])) {
        return (string)$row['numero_registro'];
    }

    $ano = '0000';
    $ts = isset($row['criado_em']) ? strtotime((string)$row['criado_em']) : false;
    if ($ts !== false) {
        $ano = date('Y', $ts);
    }

    return $ano . str_pad((string)((int)($row['id'] ?? 0)), 6, '0', STR_PAD_LEFT);
}

function montarFiltroBaseAdminExport(mysqli $conn, string $baseSelecionada, array $basesPermitidas): ?string
{
    if (!isset($basesPermitidas[$baseSelecionada])) {
        return null;
    }

    $configBase = $basesPermitidas[$baseSelecionada];
    $cidadeBase = $conn->real_escape_string((string)$configBase['cidade']);
    $estadoBase = $conn->real_escape_string((string)$configBase['estado']);
    $condicoes = [
        "LOWER(cidade) = '{$cidadeBase}'",
        "LOWER(estado) = '{$estadoBase}'",
    ];

    $placasBase = array_map(
        static fn(string $placa): string => strtolower($placa),
        placasMotocaPorBase($baseSelecionada)
    );

    if ($placasBase !== []) {
        $placasEscapadas = array_map(
            static fn(string $placa): string => "'" . $conn->real_escape_string($placa) . "'",
            $placasBase
        );
        $condicoes[] = "LOWER(placa_motoca) IN (" . implode(', ', $placasEscapadas) . ")";
    }

    return '(' . implode(' OR ', $condicoes) . ')';
}

/* Cabeçalhos Excel */
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=sinistros_motoca.xls");
header("Pragma: no-cache");
header("Expires: 0");

/* Monta filtros (MESMA lógica do painel) */
$where = [];
$params = [];
$types = '';
$baseSelecionada = (string)($_GET['base'] ?? '');
$basesPermitidas = [
    'sao_paulo' => [
        'cidade' => 'sao paulo',
        'estado' => 'sp',
    ],
    'salvador' => [
        'cidade' => 'salvador',
        'estado' => 'ba',
    ],
];

if (!empty($_GET['data_inicio'])) {
    $where[] = "DATE(criado_em) >= ?";
    $params[] = $_GET['data_inicio'];
    $types .= 's';
}

if (!empty($_GET['data_fim'])) {
    $where[] = "DATE(criado_em) <= ?";
    $params[] = $_GET['data_fim'];
    $types .= 's';
}

if (isset($basesPermitidas[$baseSelecionada])) {
    $whereBase = montarFiltroBaseAdminExport($conn, $baseSelecionada, $basesPermitidas);
    if ($whereBase !== null) {
        $where[] = $whereBase;
    }
}

$status_permitidos = ['em_andamento', 'finalizado', 'processo_juridico', 'arquivado'];
if (!empty($_GET['status']) && in_array($_GET['status'], $status_permitidos, true)) {
    $where[] = "status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

$sql = "SELECT * FROM sinistros";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY criado_em DESC";

$stmt = $conn->prepare($sql);
if ($stmt && $params) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    exit('Erro ao preparar exportação.');
}

/* Tabela Excel */
echo "<table border='1'>";
echo "<tr>
        <th>Registro</th>
        <th>Tipo</th>
        <th>Nome</th>
        <th>Telefone</th>
        <th>Email</th>
        <th>Placa MOTOCA</th>
        <th>Placa Terceiro</th>
        <th>Data/Hora</th>
        <th>CEP</th>
        <th>Logradouro</th>
        <th>Bairro</th>
        <th>Cidade</th>
        <th>Estado</th>
        <th>Sentido Via</th>
        <th>Ponto Referência</th>
        <th>Ocorrência</th>
        <th>Via</th>
        <th>Vítimas</th>
        <th>Qtd Vítimas</th>
        <th>Responsável</th>
        <th>Status</th>
        <th>Relato</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    $registro = htmlspecialchars(numeroRegistro($row), ENT_QUOTES, 'UTF-8');
    $tipo = htmlspecialchars($row['tipo_formulario'] ?? '', ENT_QUOTES, 'UTF-8');
    $nome = htmlspecialchars($row['nome'] ?? '', ENT_QUOTES, 'UTF-8');
    $telefone = htmlspecialchars($row['telefone'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $placa_motoca = htmlspecialchars($row['placa_motoca'] ?? '', ENT_QUOTES, 'UTF-8');
    $placa_terceiro = htmlspecialchars($row['placa_terceiro'] ?? '', ENT_QUOTES, 'UTF-8');
    $data_hora = htmlspecialchars($row['data_hora'] ?? '', ENT_QUOTES, 'UTF-8');
    $cep = htmlspecialchars($row['cep'] ?? '', ENT_QUOTES, 'UTF-8');
    $logradouro = htmlspecialchars($row['logradouro'] ?? '', ENT_QUOTES, 'UTF-8');
    $bairro = htmlspecialchars($row['bairro'] ?? '', ENT_QUOTES, 'UTF-8');
    $cidade = htmlspecialchars($row['cidade'] ?? '', ENT_QUOTES, 'UTF-8');
    $estado = htmlspecialchars($row['estado'] ?? '', ENT_QUOTES, 'UTF-8');
    $sentido_via = htmlspecialchars($row['sentido_via'] ?? '', ENT_QUOTES, 'UTF-8');
    $ponto_referencia = htmlspecialchars($row['ponto_referencia'] ?? '', ENT_QUOTES, 'UTF-8');
    $tipo_ocorrencia = htmlspecialchars($row['tipo_ocorrencia'] ?? '', ENT_QUOTES, 'UTF-8');
    $situacao_via = htmlspecialchars($row['situacao_via'] ?? '', ENT_QUOTES, 'UTF-8');
    $vitimas = htmlspecialchars($row['vitimas'] ?? '', ENT_QUOTES, 'UTF-8');
    $qtd_vitimas = htmlspecialchars((string)($row['qtd_vitimas'] ?? ''), ENT_QUOTES, 'UTF-8');
    $responsavel = htmlspecialchars($row['responsavel'] ?? '', ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8');
    $relato = htmlspecialchars(str_replace(["\n","\r"]," ",$row['relato'] ?? ''), ENT_QUOTES, 'UTF-8');

    echo "<tr>
            <td>{$registro}</td>
            <td>{$tipo}</td>
            <td>{$nome}</td>
            <td>{$telefone}</td>
            <td>{$email}</td>
            <td>{$placa_motoca}</td>
            <td>{$placa_terceiro}</td>
            <td>{$data_hora}</td>
            <td>{$cep}</td>
            <td>{$logradouro}</td>
            <td>{$bairro}</td>
            <td>{$cidade}</td>
            <td>{$estado}</td>
            <td>{$sentido_via}</td>
            <td>{$ponto_referencia}</td>
            <td>{$tipo_ocorrencia}</td>
            <td>{$situacao_via}</td>
            <td>{$vitimas}</td>
            <td>{$qtd_vitimas}</td>
            <td>{$responsavel}</td>
            <td>{$status}</td>
            <td>{$relato}</td>
          </tr>";
}

echo "</table>";
exit;

