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
require "../config/placas_motoca.php";

$adminUsuarioLogado = (string)($_SESSION['admin_usuario'] ?? '');
$adminPerfilLogado = normalizarPerfilAdmin((string)($_SESSION['admin_perfil'] ?? 'diretor'));
$podeGerenciarUsuarios = in_array($adminUsuarioLogado, $usuarios_gerenciam_admins ?? [], true);
$podeAcessarAuditoria = adminPodeAcessarAuditoria($adminPerfilLogado);
$podeExcluirSinistro = adminPodeExcluirSinistro($adminPerfilLogado);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$temPrazoInicio = $conn->query("SHOW COLUMNS FROM sinistros LIKE 'prazo_andamento_inicio'");
$temPrazoLimite = $conn->query("SHOW COLUMNS FROM sinistros LIKE 'prazo_limite_dias'");
$usaCicloPrazo = ($temPrazoInicio && $temPrazoInicio->num_rows > 0) &&
                 ($temPrazoLimite && $temPrazoLimite->num_rows > 0);

function montarFiltroBaseAdmin(mysqli $conn, string $baseSelecionada, array $basesPermitidas): ?string
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

/* ===== CONTADORES ===== */
$total = $conn->query("SELECT COUNT(*) t FROM sinistros")->fetch_assoc()['t'];
$andamento = $conn->query("SELECT COUNT(*) t FROM sinistros WHERE status='em_andamento'")->fetch_assoc()['t'];
$finalizado = $conn->query("SELECT COUNT(*) t FROM sinistros WHERE status='finalizado'")->fetch_assoc()['t'];
$processo_juridico = $conn->query("SELECT COUNT(*) t FROM sinistros WHERE status='processo_juridico'")->fetch_assoc()['t'];
$arquivado = $conn->query("SELECT COUNT(*) t FROM sinistros WHERE status='arquivado'")->fetch_assoc()['t'];
$sinistrosMesAtual = $conn->query(
    "SELECT COUNT(*) t FROM sinistros WHERE YEAR(criado_em) = YEAR(CURDATE()) AND MONTH(criado_em) = MONTH(CURDATE())"
)->fetch_assoc()['t'];
$placasMesAtual = $conn->query(
    "SELECT COUNT(DISTINCT NULLIF(UPPER(REPLACE(COALESCE(placa_motoca, ''), '-', '')), '')) t FROM sinistros WHERE YEAR(criado_em) = YEAR(CURDATE()) AND MONTH(criado_em) = MONTH(CURDATE())"
)->fetch_assoc()['t'];
$placasBanco = [];
$placasBancoResult = $conn->query(
    "SELECT placa FROM (
        SELECT UPPER(TRIM(placa_motoca)) AS placa FROM sinistros
        UNION
        SELECT UPPER(TRIM(placa_terceiro)) AS placa FROM sinistros
    ) placas
    WHERE placa IS NOT NULL AND placa <> ''
    ORDER BY placa ASC"
);
if ($placasBancoResult instanceof mysqli_result) {
    while ($placaRow = $placasBancoResult->fetch_assoc()) {
        $placasBanco[] = (string)$placaRow['placa'];
    }
}
$rotuloMesAtual = date('m/Y');

/* ===== FILTRO ===== */
$where = [];
$baseSelecionada = (string)($_GET['base'] ?? '');
$registroBusca = preg_replace('/\D/', '', (string)($_GET['registro'] ?? ''));
$placaBusca = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($_GET['placa'] ?? '')));
$dataInicioFiltro = (string)($_GET['data_inicio'] ?? date('Y-m-01'));
$dataFimFiltro = (string)($_GET['data_fim'] ?? date('Y-m-t'));
$basesPermitidas = [
    'sao_paulo' => [
        'label' => 'São Paulo',
        'cidade' => 'sao paulo',
        'estado' => 'sp',
    ],
    'salvador' => [
        'label' => 'Salvador',
        'cidade' => 'salvador',
        'estado' => 'ba',
    ],
];

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicioFiltro)) {
    $where[] = "DATE(criado_em) >= '{$conn->real_escape_string($dataInicioFiltro)}'";
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFimFiltro)) {
    $where[] = "DATE(criado_em) <= '{$conn->real_escape_string($dataFimFiltro)}'";
}

if (isset($basesPermitidas[$baseSelecionada])) {
    $whereBase = montarFiltroBaseAdmin($conn, $baseSelecionada, $basesPermitidas);
    if ($whereBase !== null) {
        $where[] = $whereBase;
    }
}

if ($registroBusca !== '') {
    $registroLike = $conn->real_escape_string($registroBusca);
    $where[] = "COALESCE(numero_registro, '') LIKE '%{$registroLike}%'";
}

if ($placaBusca !== '') {
    $placaLike = $conn->real_escape_string($placaBusca);
    $where[] = "(UPPER(REPLACE(COALESCE(placa_motoca, ''), '-', '')) LIKE '%{$placaLike}%' OR UPPER(REPLACE(COALESCE(placa_terceiro, ''), '-', '')) LIKE '%{$placaLike}%')";
}

if (!empty($_GET['somente_atualizar']) && $_GET['somente_atualizar'] === '1') {
    if ($usaCicloPrazo) {
        $where[] = "status = 'em_andamento' AND (TIMESTAMPDIFF(DAY, COALESCE(prazo_andamento_inicio, criado_em), NOW()) + 1) > COALESCE(prazo_limite_dias, 15)";
    } else {
        $where[] = "status = 'em_andamento' AND (TIMESTAMPDIFF(DAY, criado_em, NOW()) + 1) > 15";
    }
}

$status_permitidos = ['em_andamento', 'finalizado', 'processo_juridico', 'arquivado'];
if (!empty($_GET['status']) && in_array($_GET['status'], $status_permitidos, true)) {
    $status = $conn->real_escape_string($_GET['status']);
    $where[] = "status = '$status'";
}

$paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));
$itensPorPagina = 25;
$offset = ($paginaAtual - 1) * $itensPorPagina;

$sqlCount = "SELECT COUNT(*) t FROM sinistros";
if ($where) {
    $sqlCount .= " WHERE " . implode(" AND ", $where);
}
$totalFiltrado = (int)($conn->query($sqlCount)->fetch_assoc()['t'] ?? 0);
$totalPaginas = max(1, (int)ceil($totalFiltrado / $itensPorPagina));
if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
    $offset = ($paginaAtual - 1) * $itensPorPagina;
}

$sql = "SELECT * FROM sinistros";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY criado_em DESC LIMIT {$itensPorPagina} OFFSET {$offset}";

$result = $conn->query($sql);
if (!$result) {
    die("Erro ao carregar lista de sinistros.");
}

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

function urlPainelComFiltros(array $override = []): string
{
    $params = $_GET;
    foreach ($override as $chave => $valor) {
        if ($valor === null || $valor === '') {
            unset($params[$chave]);
        } else {
            $params[$chave] = $valor;
        }
    }

    $query = http_build_query($params);
    return $query === '' ? 'painel.php' : 'painel.php?' . $query;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Painel Admin | MOTOCA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/admin.css">


<style>
/* ===== BOTAO MENU ===== */
.menu-btn {
    position: fixed;
    top: 20px;
    left: 20px;
    background: transparent;
    border: 2px solid #FFFF00;
    color: #FFFF00;
    font-size: 22px;
    padding: 6px 14px;
    border-radius: 10px;
    cursor: pointer;
    z-index: 1001;
}

/* ===== MENU LATERAL ===== */
.menu-lateral {
    position: fixed;
    top: 0;
    left: -240px;
    width: 240px;
    height: 100%;
    background: #111;
    padding-top: 90px;
    transition: .3s;
    z-index: 1000;
}

.menu-lateral.active {
    left: 0;
}

.menu-lateral a {
    display: block;
    padding: 16px 24px;
    color: #FFFF00;
    text-decoration: none;
    font-weight: 600;
}

.menu-lateral a:hover {
    background: #FFFF00;
    color: #000;
}

/* ===== OVERLAY ===== */
.overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 999;
}

.overlay.active {
    display: block;
}

/* ===== FILTRO ===== */
.filtro {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: center;
    margin-bottom: 25px;
    padding: 12px;
    border: 1px solid #222;
    border-radius: 12px;
    background: #0d0d0d;
}

.filtro .filtro-campo {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filtro .filtro-campo label {
    font-size: 12px;
    color: #aaa;
}

.filtro-acoes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.btn-secundario {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 12px;
    border: 1px solid #4b4b4b;
    color: #f5f5f5;
    text-decoration: none;
    background: #161616;
    transition: border-color .2s ease, transform .2s ease;
}

.btn-secundario:hover {
    border-color: #f5e500;
    transform: translateY(-1px);
}

.filtro .controle-wrap,
.filtro .placa-campo {
    position: relative;
}

.filtro .filtro-controle,
.filtro .placa-input {
    background: linear-gradient(180deg, #161616 0%, #0e0e0e 100%);
    color: #f5f5f5;
    border: 1px solid #4b4b4b;
    padding: 10px 42px 10px 14px;
    border-radius: 12px;
    min-width: 210px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.filtro .filtro-controle {
    min-width: 170px;
}

.filtro .filtro-controle::placeholder,
.filtro .placa-input::placeholder {
    color: #8f8f8f;
}

.filtro .filtro-controle:focus,
.filtro .placa-input:focus {
    outline: none;
    border-color: #f5e500;
    box-shadow: 0 0 0 3px rgba(245, 229, 0, .16);
}

.filtro select.filtro-controle {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
}

.filtro input[type="date"].filtro-controle::-webkit-calendar-picker-indicator {
    filter: invert(87%) sepia(46%) saturate(1711%) hue-rotate(10deg) brightness(102%) contrast(96%);
    cursor: pointer;
}

.filtro .controle-wrap::after,
.filtro .placa-campo::after {
    content: "";
    position: absolute;
    right: 14px;
    top: 50%;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-top: 7px solid #f5e500;
    transform: translateY(-10%);
    pointer-events: none;
    opacity: .9;
}

.filtro .controle-wrap.controle-data::after {
    display: none;
}

.filtro .controle-wrap.controle-base::after {
    display: none;
}

.filtro .base-trigger {
    width: 100%;
    text-align: left;
    cursor: pointer;
}

.filtro .base-trigger-label {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.paginacao {
    margin-top: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.paginacao-info {
    color: #bdbdbd;
    font-size: 14px;
}

.paginacao-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.paginacao-link {
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid #333;
    color: #f5f5f5;
    text-decoration: none;
    background: #111;
}

.paginacao-link.ativo {
    border-color: #f5e500;
    color: #000;
    background: #f5e500;
    font-weight: 700;
}

.tabela-vazia {
    text-align: center;
    color: #bdbdbd;
    padding: 24px 12px;
}

.filtro .base-caret {
    position: absolute;
    right: 14px;
    top: 50%;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-top: 7px solid #f5e500;
    transform: translateY(-10%);
    pointer-events: none;
    opacity: .9;
    transition: transform .18s ease;
}

.filtro .controle-wrap.controle-base.is-open .base-caret {
    transform: translateY(-10%) rotate(180deg);
}

.filtro .base-opcoes {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    margin: 0;
    padding: 8px;
    list-style: none;
    background: rgba(10, 10, 10, .98);
    border: 1px solid rgba(245, 229, 0, .22);
    border-radius: 14px;
    box-shadow: 0 18px 45px rgba(0, 0, 0, .45);
    z-index: 25;
    display: none;
}

.filtro .controle-wrap.controle-base.is-open .base-opcoes {
    display: block;
}

.filtro .base-opcao {
    padding: 10px 12px;
    border-radius: 10px;
    color: #f5f5f5;
    cursor: pointer;
    font-weight: 600;
    transition: background .18s ease, color .18s ease;
}

.filtro .base-opcao:hover,
.filtro .base-opcao.is-active {
    background: rgba(245, 229, 0, .14);
    color: #fff28a;
}

.filtro .placa-sugestoes {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    margin: 0;
    padding: 8px;
    list-style: none;
    background: rgba(10, 10, 10, .98);
    border: 1px solid rgba(245, 229, 0, .22);
    border-radius: 14px;
    box-shadow: 0 18px 45px rgba(0, 0, 0, .45);
    max-height: 260px;
    overflow-y: auto;
    z-index: 25;
    display: none;
}

.filtro .placa-sugestoes.is-open {
    display: block;
}

.filtro .placa-sugestoes::-webkit-scrollbar {
    width: 10px;
}

.filtro .placa-sugestoes::-webkit-scrollbar-thumb {
    background: rgba(245, 229, 0, .35);
    border-radius: 999px;
}

.filtro .placa-sugestao {
    padding: 10px 12px;
    border-radius: 10px;
    color: #f5f5f5;
    cursor: pointer;
    font-weight: 600;
    letter-spacing: 0.04em;
    transition: background .18s ease, color .18s ease;
}

.filtro .placa-sugestao:hover,
.filtro .placa-sugestao.is-active {
    background: rgba(245, 229, 0, .14);
    color: #fff28a;
}

.filtro .placa-vazio {
    padding: 10px 12px;
    color: #8a8a8a;
    font-size: 13px;
}

.filtro select {
    background: #111;
    color: #fff;
    border: 1px solid #333;
    padding: 8px 12px;
    border-radius: 10px;
    min-width: 170px;
}

.toggle-atualizar {
    display: inline-flex;
    align-items: center;
    align-self: flex-end;
    gap: 8px;
    padding: 8px 12px;
    margin-bottom: 1px;
    border: 1px solid #444;
    border-radius: 999px;
    background: #111;
    color: #d6d6d6;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
}

.toggle-atualizar input {
    accent-color: #f5e500;
    margin: 0;
    width: 16px;
    height: 16px;
    display: block;
    flex: 0 0 auto;
}

.toggle-atualizar.ativo {
    border-color: #f5e500;
    color: #f5e500;
    background: rgba(255, 212, 0, 0.08);
}

.btn-icon {
    background: #FFFF00;
    color: #000;
    border: 0;
    padding: 10px 16px;
    align-self: flex-end;
    margin-bottom: 1px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
}

.prazo-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #3a3a3a;
}

.prazo-normal {
    color: #FFFF00;
    border-color: rgba(255,212,0,.45);
    background: rgba(255,212,0,.1);
}

.prazo-atualizar {
    color: #ff4d4d;
    border-color: rgba(255,77,77,.65);
    background: rgba(255,77,77,.12);
    animation: pulseAtualizar 1.2s ease-in-out infinite;
}

a.prazo-atualizar {
    text-decoration: none;
}

@keyframes pulseAtualizar {
    0% { box-shadow: 0 0 0 0 rgba(255,77,77,.35); }
    70% { box-shadow: 0 0 0 8px rgba(255,77,77,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,77,77,0); }
}
</style>
</head>

<script>
if (localStorage.getItem("statusAtualizado") === "1") {
    localStorage.removeItem("statusAtualizado");
    location.reload();
}
</script>

<body class="admin-page">

<button class="menu-btn" onclick="toggleMenu()">&#9776;</button>

<nav class="menu-lateral" id="menu">
    <a href="grafico.php">Gráfico</a>
    <?php if ($podeGerenciarUsuarios): ?>
    <a href="usuarios.php">Usuários</a>
    <?php endif; ?>
    <?php if ($podeAcessarAuditoria): ?>
    <a href="auditoria.php">Auditoria</a>
    <?php endif; ?>
    <a href="teste_email.php">Validação de E-mail</a>
    <a href="logout.php">Sair</a>
</nav>

<div class="overlay" id="overlay" onclick="toggleMenu()"></div>

<div class="container">

    <img src="../assets/img/logo-site-3d.png" class="logo logo-site" alt="Motoca">

    <h1>Painel de Sinistros</h1>

    <div class="contexto-admin">
        <p>Conta logada: <strong><?= htmlspecialchars($adminUsuarioLogado, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <span class="perfil-badge">Perfil: <?= htmlspecialchars(perfilAdminRotulo($adminPerfilLogado), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="dashboard">
        <div class="card"><h3>Total</h3><span><?= $total ?></span></div>
        <div class="card"><h3>No mês</h3><span><?= $sinistrosMesAtual ?></span><small><?= htmlspecialchars($rotuloMesAtual, ENT_QUOTES, 'UTF-8') ?></small></div>
        <div class="card"><h3>Placas no mês</h3><span><?= $placasMesAtual ?></span><small>com sinistro</small></div>
        <div class="card andamento"><h3>Em andamento</h3><span><?= $andamento ?></span></div>
        <div class="card finalizado"><h3>Finalizados</h3><span><?= $finalizado ?></span></div>
        <div class="card processo_juridico"><h3>Jurídico</h3><span><?= $processo_juridico ?></span></div>
        <div class="card arquivado"><h3>Arquivados</h3><span><?= $arquivado ?></span></div>
    </div>

    <form method="GET" class="filtro">
        <div class="filtro-campo">
            <label>Registro</label>
            <div class="controle-wrap">
                <input
                    class="filtro-controle"
                    type="text"
                    name="registro"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="Ex: 2026000123"
                    value="<?= htmlspecialchars($registroBusca, ENT_QUOTES, 'UTF-8') ?>"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                >
            </div>
        </div>
        <div class="filtro-campo">
            <label>Data início</label>
            <div class="controle-wrap controle-data">
                <input class="filtro-controle" type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicioFiltro, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        <div class="filtro-campo">
            <label>Data final</label>
            <div class="controle-wrap controle-data">
                <input class="filtro-controle" type="date" name="data_fim" value="<?= htmlspecialchars($dataFimFiltro, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        <div class="filtro-campo">
            <label>Base</label>
            <div class="controle-wrap controle-base" id="base-dropdown">
            <input type="hidden" name="base" id="base-input" value="<?= htmlspecialchars($baseSelecionada, ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="filtro-controle base-trigger" id="base-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span class="base-trigger-label" id="base-trigger-label">
                    <?= htmlspecialchars($basesPermitidas[$baseSelecionada]['label'] ?? 'Todas', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </button>
            <span class="base-caret" aria-hidden="true"></span>
            <ul class="base-opcoes" id="base-opcoes" role="listbox" aria-label="Bases">
                <li class="base-opcao <?= $baseSelecionada === '' ? 'is-active' : '' ?>" data-value="">Todas</li>
                <?php foreach ($basesPermitidas as $valorBase => $baseConfig): ?>
                    <li class="base-opcao <?= $baseSelecionada === $valorBase ? 'is-active' : '' ?>" data-value="<?= htmlspecialchars($valorBase, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($baseConfig['label'], ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            </div>
        </div>
        <div class="filtro-campo">
            <label>Placa</label>
            <div class="placa-campo">
            <input
                id="placa-input"
                class="placa-input"
                type="text"
                name="placa"
                maxlength="8"
                autocomplete="off"
                placeholder="EX: KJD8F21"
                value="<?= htmlspecialchars($placaBusca, ENT_QUOTES, 'UTF-8') ?>"
                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g, '')"
            >
            <ul id="placa-sugestoes" class="placa-sugestoes" role="listbox" aria-label="Sugestões de placa"></ul>
            </div>
        </div>
        <label class="toggle-atualizar <?= (!empty($_GET['somente_atualizar']) && $_GET['somente_atualizar'] === '1') ? 'ativo' : '' ?>">
            <input type="checkbox" id="somente_atualizar" name="somente_atualizar" value="1" <?= (!empty($_GET['somente_atualizar']) && $_GET['somente_atualizar'] === '1') ? 'checked' : '' ?>>
            <span>Somente ATUALIZAR</span>
        </label>
        <div class="filtro-acoes">
            <button class="btn-icon" type="submit">Filtrar</button>
            <a class="btn-secundario" href="painel.php">Limpar filtros</a>
        </div>
    </form>

    <table class="tabela">
    <thead>
        <tr>
            <th>Registro</th>
            <th>Tipo</th>
            <th>Nome</th>
            <th>Placa</th>
            <th>Status</th>
            <th>Prazo</th>
            <th>Ação</th>
        </tr>
    </thead>

<tbody>
<?php if ($result->num_rows === 0): ?>
<tr>
    <td colspan="7" class="tabela-vazia">
        <div class="estado-vazio">
            <strong>Nenhum sinistro encontrado</strong>
            <span>Revise os filtros aplicados ou limpe a busca para visualizar novamente todos os registros.</span>
        </div>
    </td>
</tr>
<?php endif; ?>
<?php while ($row = $result->fetch_assoc()): ?>
<?php $registroUrl = rawurlencode(numeroRegistro($row)); ?>
<?php
$linhaEmAtencao = false;
$statusAtualLinha = (string)($row['status'] ?? '');
if ($statusAtualLinha === 'em_andamento') {
    $limiteDiasLinha = isset($row['prazo_limite_dias']) && (int)$row['prazo_limite_dias'] > 0 ? (int)$row['prazo_limite_dias'] : 15;
    $basePrazoLinha = (string)($row['prazo_andamento_inicio'] ?? '');
    if ($basePrazoLinha === '') {
        $basePrazoLinha = (string)($row['criado_em'] ?? '');
    }

    if ($basePrazoLinha !== '') {
        $inicioPrazoLinha = new DateTime($basePrazoLinha);
        $agoraLinha = new DateTime();
        $diasLinha = (int)$inicioPrazoLinha->diff($agoraLinha)->days + 1;
        $linhaEmAtencao = $diasLinha > $limiteDiasLinha;
    }
}
?>
<tr class="<?= $linhaEmAtencao ? 'linha-atencao' : '' ?>">
    <td><?= htmlspecialchars(numeroRegistro($row), ENT_QUOTES, 'UTF-8') ?></td>

    <td><?= htmlspecialchars(ucfirst((string)$row['tipo_formulario']), ENT_QUOTES, 'UTF-8') ?></td>

    <td><?= htmlspecialchars((string)$row['nome'], ENT_QUOTES, 'UTF-8') ?></td>

    <td><?= htmlspecialchars(strtoupper((string)$row['placa_motoca']), ENT_QUOTES, 'UTF-8') ?></td>

    <td>
        <span class="status <?= htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(ucfirst(str_replace("_", " ", (string)$row['status'])), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </td>

    <td>
        <?php
        if ($statusAtualLinha === 'em_andamento') {
            if (!empty($basePrazoLinha ?? '')) {
                if ($linhaEmAtencao) {
                    ?>
                    <a href="visualizar.php?registro=<?= $registroUrl ?>" class="prazo-badge prazo-atualizar">ATUALIZAR</a>
                    <?php
                } else {
                    ?>
                    <span class="prazo-badge prazo-normal">Dia <?= (int)$diasLinha ?>/<?= (int)$limiteDiasLinha ?></span>
                    <?php
                }
            }
        }
        ?>
    </td>

    <td class="acoes">
        <a href="visualizar.php?registro=<?= $registroUrl ?>" class="acao-ver">Ver</a>
        <?php if ($podeExcluirSinistro): ?>
            <span class="separador">|</span>
            <form method="POST" action="excluir.php" style="display:inline;" onsubmit="return confirm('Deseja realmente apagar este sinistro?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="registro" value="<?= htmlspecialchars(numeroRegistro($row), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="acao-apagar" style="background:none;border:none;padding:0;cursor:pointer;">Apagar</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<div class="paginacao">
    <div class="paginacao-info">
        <?= (int)$totalFiltrado ?> registro(s) encontrado(s) | Página <?= (int)$paginaAtual ?> de <?= (int)$totalPaginas ?>
    </div>
    <div class="paginacao-links">
        <?php if ($paginaAtual > 1): ?>
            <a class="paginacao-link" href="<?= htmlspecialchars(urlPainelComFiltros(['pagina' => $paginaAtual - 1]), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
        <?php endif; ?>

        <?php for ($pagina = max(1, $paginaAtual - 2); $pagina <= min($totalPaginas, $paginaAtual + 2); $pagina++): ?>
            <a
                class="paginacao-link <?= $pagina === $paginaAtual ? 'ativo' : '' ?>"
                href="<?= htmlspecialchars(urlPainelComFiltros(['pagina' => $pagina]), ENT_QUOTES, 'UTF-8') ?>"
            ><?= (int)$pagina ?></a>
        <?php endfor; ?>

        <?php if ($paginaAtual < $totalPaginas): ?>
            <a class="paginacao-link" href="<?= htmlspecialchars(urlPainelComFiltros(['pagina' => $paginaAtual + 1]), ENT_QUOTES, 'UTF-8') ?>">Próxima</a>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
function toggleMenu() {
    document.getElementById("menu").classList.toggle("active");
    document.getElementById("overlay").classList.toggle("active");
}

const placasBanco = <?= json_encode(array_values($placasBanco), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const baseDropdown = document.getElementById("base-dropdown");
const baseInput = document.getElementById("base-input");
const baseTrigger = document.getElementById("base-trigger");
const baseTriggerLabel = document.getElementById("base-trigger-label");
const baseOpcoes = document.getElementById("base-opcoes");
const placaInput = document.getElementById("placa-input");
const placaSugestoes = document.getElementById("placa-sugestoes");
let indiceSugestaoAtiva = -1;

function abrirBaseDropdown() {
    if (!baseDropdown || !baseTrigger) return;
    baseDropdown.classList.add("is-open");
    baseTrigger.setAttribute("aria-expanded", "true");
}

function fecharBaseDropdown() {
    if (!baseDropdown || !baseTrigger) return;
    baseDropdown.classList.remove("is-open");
    baseTrigger.setAttribute("aria-expanded", "false");
}

function selecionarBase(valor, rotulo) {
    if (!baseInput || !baseTriggerLabel || !baseOpcoes) return;
    baseInput.value = valor;
    baseTriggerLabel.textContent = rotulo;
    baseOpcoes.querySelectorAll(".base-opcao").forEach((opcao) => {
        opcao.classList.toggle("is-active", opcao.dataset.value === valor);
    });
    fecharBaseDropdown();
}

function normalizarPlacaValor(valor) {
    return (valor || "").toUpperCase().replace(/[^A-Z0-9-]/g, "");
}

function fecharSugestoesPlaca() {
    if (!placaSugestoes) return;
    placaSugestoes.classList.remove("is-open");
    placaSugestoes.innerHTML = "";
    indiceSugestaoAtiva = -1;
}

function aplicarSugestaoPlaca(valor) {
    if (!placaInput) return;
    placaInput.value = normalizarPlacaValor(valor);
    fecharSugestoesPlaca();
    placaInput.focus();
}

function renderizarSugestoesPlaca() {
    if (!placaInput || !placaSugestoes) return;

    const termo = normalizarPlacaValor(placaInput.value);
    if (termo.length === 0) {
        fecharSugestoesPlaca();
        return;
    }

    const sugestoes = placasBanco.filter((placa) => placa.includes(termo)).slice(0, 12);
    placaSugestoes.innerHTML = "";
    indiceSugestaoAtiva = -1;

    if (sugestoes.length === 0) {
        const vazio = document.createElement("li");
        vazio.className = "placa-vazio";
        vazio.textContent = "Nenhuma placa encontrada";
        placaSugestoes.appendChild(vazio);
        placaSugestoes.classList.add("is-open");
        return;
    }

    sugestoes.forEach((placa, indice) => {
        const item = document.createElement("li");
        item.className = "placa-sugestao";
        item.setAttribute("role", "option");
        item.textContent = placa;
        item.addEventListener("mousedown", (event) => {
            event.preventDefault();
            aplicarSugestaoPlaca(placa);
        });
        item.addEventListener("mouseenter", () => {
            indiceSugestaoAtiva = indice;
            atualizarSugestaoAtiva();
        });
        placaSugestoes.appendChild(item);
    });

    placaSugestoes.classList.add("is-open");
}

function atualizarSugestaoAtiva() {
    if (!placaSugestoes) return;
    const itens = placaSugestoes.querySelectorAll(".placa-sugestao");
    itens.forEach((item, indice) => {
        item.classList.toggle("is-active", indice === indiceSugestaoAtiva);
    });
}

if (placaInput && placaSugestoes) {
    placaInput.addEventListener("focus", renderizarSugestoesPlaca);
    placaInput.addEventListener("input", renderizarSugestoesPlaca);
    placaInput.addEventListener("keydown", (event) => {
        const itens = placaSugestoes.querySelectorAll(".placa-sugestao");
        if (!placaSugestoes.classList.contains("is-open") || itens.length === 0) {
            return;
        }

        if (event.key === "ArrowDown") {
            event.preventDefault();
            indiceSugestaoAtiva = Math.min(indiceSugestaoAtiva + 1, itens.length - 1);
            atualizarSugestaoAtiva();
            return;
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();
            indiceSugestaoAtiva = Math.max(indiceSugestaoAtiva - 1, 0);
            atualizarSugestaoAtiva();
            return;
        }

        if (event.key === "Enter" && indiceSugestaoAtiva >= 0) {
            event.preventDefault();
            aplicarSugestaoPlaca(itens[indiceSugestaoAtiva].textContent || "");
            return;
        }

        if (event.key === "Escape") {
            fecharSugestoesPlaca();
        }
    });

    placaInput.addEventListener("blur", () => {
        window.setTimeout(fecharSugestoesPlaca, 120);
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".placa-campo")) {
            fecharSugestoesPlaca();
        }
    });
}

if (baseDropdown && baseInput && baseTrigger && baseTriggerLabel && baseOpcoes) {
    baseTrigger.addEventListener("click", () => {
        if (baseDropdown.classList.contains("is-open")) {
            fecharBaseDropdown();
        } else {
            abrirBaseDropdown();
        }
    });

    baseOpcoes.querySelectorAll(".base-opcao").forEach((opcao) => {
        opcao.addEventListener("click", () => {
            selecionarBase(opcao.dataset.value || "", opcao.textContent || "Todas");
        });
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest("#base-dropdown")) {
            fecharBaseDropdown();
        }
    });
}
</script>

</body>
</html>


