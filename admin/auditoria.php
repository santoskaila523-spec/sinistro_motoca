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

$adminUsuarioLogado = (string)($_SESSION['admin_usuario'] ?? '');
$adminPerfilLogado = normalizarPerfilAdmin((string)($_SESSION['admin_perfil'] ?? 'diretor'));
$podeGerenciarUsuarios = in_array($adminUsuarioLogado, $usuarios_gerenciam_admins ?? [], true);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$ttl = (int)($ttl_autorizacao_usuarios ?? 900);
$ttlMinutos = (int)ceil(max(60, $ttl) / 60);

if (!adminPodeAcessarAuditoria($adminPerfilLogado)) {
    http_response_code(403);
    exit("Acesso negado. Seu perfil não está autorizado para acessar a auditoria.");
}

function autorizacaoAuditoriaValida(string $usuario): bool
{
    return autorizacaoSensivelValida('autorizacao_auditoria', $usuario);
}

$erroAutorizacao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'autorizar_auditoria') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $erroAutorizacao = 'Token inválido. Recarregue a página.';
    } else {
        $senha = (string)($_POST['senha_autorizacao'] ?? '');
        $stmt = $conn->prepare("SELECT senha FROM admins WHERE usuario = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $adminUsuarioLogado);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($senha, (string)$row['senha'])) {
                $_SESSION['autorizacao_auditoria'] = [
                    'usuario' => $adminUsuarioLogado,
                    'ate' => time() + max(60, $ttl),
                ];
                header("Location: auditoria.php");
                exit;
            }
        }
        $erroAutorizacao = 'Senha inválida.';
    }
}

if (!autorizacaoAuditoriaValida($adminUsuarioLogado)) {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Autorização | Auditoria</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>
    :root{--bg:#0f1014;--card:#171922;--border:#2a2e3b;--text:#f4f6fb;--muted:#9aa3b2;--accent:#f5e500;--danger:#ff8a8a}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px;font-family:"Segoe UI",Arial,sans-serif;color:var(--text);background:radial-gradient(1200px 500px at 120% -20%, rgba(255, 212, 0, 0.12), transparent 60%),linear-gradient(180deg, #0f1014 0%, #0d0f13 100%)}.box{max-width:460px;margin:72px auto;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:22px;box-shadow:0 18px 50px rgba(0,0,0,.35)}.tag{display:inline-block;font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:#111;background:var(--accent);padding:4px 8px;border-radius:999px;font-weight:700;margin-bottom:10px}h1{margin:0 0 10px;font-size:24px}p{margin:0 0 12px;color:var(--muted);line-height:1.45}.conta{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin-bottom:12px;color:var(--text)}label{display:block;margin-bottom:7px;color:#cfd6e3;font-size:13px;font-weight:600}input{width:100%;background:#0f121a;color:var(--text);border:1px solid #323748;border-radius:10px;padding:11px 12px;margin-bottom:12px}input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(255,212,0,.18)}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{border:0;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;background:var(--accent);color:#111}.btn-link{display:inline-flex;align-items:center;padding:10px 14px;border-radius:10px;border:1px solid var(--border);color:var(--text);text-decoration:none;background:rgba(255,255,255,.02)}.erro{color:var(--danger);margin:0 0 12px;background:rgba(170,34,34,.16);border:1px solid rgba(170,34,34,.45);border-radius:10px;padding:9px 10px}
    </style></head><body><div class="box"><span class="tag">Área restrita</span><h1>Confirmar acesso à auditoria</h1><p>Somente perfis autorizados podem acessar esta tela. Confirme sua senha para continuar.</p><div class="conta">Conta logada: <strong>' . htmlspecialchars($adminUsuarioLogado, ENT_QUOTES, 'UTF-8') . '</strong><br>Perfil: <strong>' . htmlspecialchars(perfilAdminRotulo($adminPerfilLogado), ENT_QUOTES, 'UTF-8') . '</strong><br>Validade desta autorização: ' . (int)$ttlMinutos . ' min</div>';
    if ($erroAutorizacao !== '') {
        echo '<div class="erro">' . htmlspecialchars($erroAutorizacao, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '<form method="POST"><input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="acao" value="autorizar_auditoria"><label>Digite sua senha para entrar na auditoria</label><input type="password" name="senha_autorizacao" required><div class="actions"><button class="btn" type="submit">Autorizar</button><a class="btn-link" href="painel.php">Voltar</a></div></form></div></body></html>';
    exit;
}

if (!tabelaAuditoriaSinistroExiste($conn)) {
    die("A tabela de auditoria ainda não foi criada. Execute a migration de histórico antes de usar esta tela.");
}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function numeroRegistroExibicao(array $row): string
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

function formatarDataHoraAuditoria(?string $valor): string
{
    if (!$valor) {
        return '-';
    }

    $timestamp = strtotime($valor);
    if ($timestamp === false) {
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}

function formatarStatusAuditoria(?string $status): string
{
    $status = trim((string)$status);
    if ($status === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', $status));
}

function urlAuditoria(array $override = []): string
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
    return $query === '' ? 'auditoria.php' : 'auditoria.php?' . $query;
}

$registroBusca = preg_replace('/\D/', '', (string)($_GET['registro'] ?? ''));
$acaoBusca = trim((string)($_GET['acao'] ?? ''));
$usuarioBusca = trim((string)($_GET['usuario'] ?? ''));
$statusAnteriorBusca = trim((string)($_GET['status_anterior'] ?? ''));
$statusNovoBusca = trim((string)($_GET['status_novo'] ?? ''));
$dataInicio = (string)($_GET['data_inicio'] ?? '');
$dataFim = (string)($_GET['data_fim'] ?? '');

$acoesPermitidas = [
    '' => 'Todas',
    'abertura_sinistro' => 'Abertura do sinistro',
    'alteracao_status' => 'Alteração de status',
    'exclusao_sinistro' => 'Exclusão do sinistro',
];

$statusPermitidos = [
    '' => 'Todos',
    'em_andamento' => 'Em andamento',
    'finalizado' => 'Finalizado',
    'processo_juridico' => 'Processo jurídico',
    'arquivado' => 'Arquivado',
];

$where = [];

if ($registroBusca !== '') {
    $registroEsc = $conn->real_escape_string($registroBusca);
    $where[] = "COALESCE(s.numero_registro, '') LIKE '%{$registroEsc}%'";
}

if ($acaoBusca !== '' && isset($acoesPermitidas[$acaoBusca])) {
    $acaoEsc = $conn->real_escape_string($acaoBusca);
    $where[] = "h.acao = '{$acaoEsc}'";
}

if ($usuarioBusca !== '') {
    $usuarioEsc = $conn->real_escape_string($usuarioBusca);
    $where[] = "COALESCE(h.admin_usuario, '') LIKE '%{$usuarioEsc}%'";
}

if ($statusAnteriorBusca !== '' && isset($statusPermitidos[$statusAnteriorBusca])) {
    $statusAnteriorEsc = $conn->real_escape_string($statusAnteriorBusca);
    $where[] = "COALESCE(h.valor_anterior, '') = '{$statusAnteriorEsc}'";
}

if ($statusNovoBusca !== '' && isset($statusPermitidos[$statusNovoBusca])) {
    $statusNovoEsc = $conn->real_escape_string($statusNovoBusca);
    $where[] = "COALESCE(h.valor_novo, '') = '{$statusNovoEsc}'";
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
    $where[] = "DATE(h.criado_em) >= '" . $conn->real_escape_string($dataInicio) . "'";
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    $where[] = "DATE(h.criado_em) <= '" . $conn->real_escape_string($dataFim) . "'";
}

$paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));
$itensPorPagina = 30;
$offset = ($paginaAtual - 1) * $itensPorPagina;

$sqlBase = "
    FROM sinistro_historico h
    LEFT JOIN sinistros s ON s.id = h.sinistro_id
";

if ($where) {
    $sqlBase .= " WHERE " . implode(" AND ", $where);
}

$sqlCount = "SELECT COUNT(*) t " . $sqlBase;
$totalRegistros = (int)($conn->query($sqlCount)->fetch_assoc()['t'] ?? 0);
$totalPaginas = max(1, (int)ceil($totalRegistros / $itensPorPagina));
if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
    $offset = ($paginaAtual - 1) * $itensPorPagina;
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
        s.status,
        s.criado_em AS sinistro_criado_em
    " . $sqlBase . "
    ORDER BY h.criado_em DESC, h.id DESC
    LIMIT {$itensPorPagina} OFFSET {$offset}
";
$result = $conn->query($sql);
if (!$result) {
    die("Erro ao carregar a auditoria.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Auditoria | MOTOCA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
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

.menu-lateral a:hover,
.menu-lateral a.ativo {
    background: #FFFF00;
    color: #000;
}

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

.filtro {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: end;
    justify-content: center;
    margin-bottom: 25px;
    padding: 14px;
    border: 1px solid #222;
    border-radius: 12px;
    background: #0d0d0d;
}

.filtro-campo {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filtro-campo label {
    font-size: 12px;
    color: #aaa;
}

.filtro-controle {
    min-width: 180px;
    background: #111;
    color: #f5f5f5;
    border: 1px solid #3a3a3a;
    border-radius: 10px;
    padding: 10px 12px;
}

.filtro-controle:focus {
    outline: none;
    border-color: #f5e500;
    box-shadow: 0 0 0 3px rgba(245, 229, 0, .16);
}

.filtro-acoes {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-primario,
.btn-secundario {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 10px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}

.btn-primario {
    background: #FFFF00;
    color: #000;
    border: none;
}

.btn-secundario {
    background: #111;
    color: #f5f5f5;
    border: 1px solid #3a3a3a;
}

.badge-acao,
.badge-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #333;
    white-space: nowrap;
}

.badge-acao.abertura_sinistro {
    background: rgba(0, 230, 118, .12);
    color: #00E676;
    border-color: rgba(0, 230, 118, .28);
}

.badge-acao.alteracao_status {
    background: rgba(255, 212, 0, .12);
    color: #FFD400;
    border-color: rgba(255, 212, 0, .28);
}

.badge-acao.exclusao_sinistro {
    background: rgba(255, 77, 77, .12);
    color: #ff7b7b;
    border-color: rgba(255, 77, 77, .28);
}

.badge-status.em_andamento {
    background: rgba(255, 212, 0, .12);
    color: #FFD400;
}

.badge-status.finalizado {
    background: rgba(0, 230, 118, .12);
    color: #00E676;
}

.badge-status.processo_juridico {
    background: rgba(255, 112, 67, .12);
    color: #FF7043;
}

.badge-status.arquivado {
    background: rgba(158, 158, 158, .12);
    color: #d0d0d0;
}

.tabela-auditoria .col-observacao {
    text-align: left;
    min-width: 260px;
}

.tabela-auditoria .col-registro a {
    color: #FFD400;
    text-decoration: none;
}

.tabela-auditoria .col-registro a:hover {
    text-decoration: underline;
}

.vazio {
    text-align: center;
    color: #bdbdbd;
    padding: 24px 12px;
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
    gap: 8px;
    flex-wrap: wrap;
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
}
</style>
</head>
<body class="admin-page">
<button class="menu-btn" onclick="toggleMenu()">&#9776;</button>

<nav class="menu-lateral" id="menu">
    <a href="painel.php">Painel</a>
    <a href="grafico.php">Gráfico</a>
    <a href="auditoria.php" class="ativo">Auditoria</a>
    <?php if ($podeGerenciarUsuarios): ?>
    <a href="usuarios.php">Usuários</a>
    <?php endif; ?>
    <a href="teste_email.php">Validação de E-mail</a>
    <a href="logout.php">Sair</a>
</nav>

<div class="overlay" id="overlay" onclick="toggleMenu()"></div>

<div class="container">
    <img src="../assets/img/logo-site-3d.png" class="logo logo-site" alt="Motoca">
    <h1>Auditoria do Sistema</h1>

    <form method="GET" class="filtro">
        <div class="filtro-campo">
            <label>Registro</label>
            <input class="filtro-controle" type="text" name="registro" value="<?= e($registroBusca) ?>" placeholder="Ex: 2026000123" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
        </div>
        <div class="filtro-campo">
            <label>Ação</label>
            <select class="filtro-controle" name="acao">
                <?php foreach ($acoesPermitidas as $valorAcao => $rotuloAcao): ?>
                    <option value="<?= e($valorAcao) ?>" <?= $acaoBusca === $valorAcao ? 'selected' : '' ?>><?= e($rotuloAcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-campo">
            <label>Usuário admin</label>
            <input class="filtro-controle" type="text" name="usuario" value="<?= e($usuarioBusca) ?>" placeholder="Ex: admin">
        </div>
        <div class="filtro-campo">
            <label>Status anterior</label>
            <select class="filtro-controle" name="status_anterior">
                <?php foreach ($statusPermitidos as $valorStatus => $rotuloStatus): ?>
                    <option value="<?= e($valorStatus) ?>" <?= $statusAnteriorBusca === $valorStatus ? 'selected' : '' ?>><?= e($rotuloStatus) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-campo">
            <label>Status novo</label>
            <select class="filtro-controle" name="status_novo">
                <?php foreach ($statusPermitidos as $valorStatus => $rotuloStatus): ?>
                    <option value="<?= e($valorStatus) ?>" <?= $statusNovoBusca === $valorStatus ? 'selected' : '' ?>><?= e($rotuloStatus) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-campo">
            <label>Data início</label>
            <input class="filtro-controle" type="date" name="data_inicio" value="<?= e($dataInicio) ?>">
        </div>
        <div class="filtro-campo">
            <label>Data final</label>
            <input class="filtro-controle" type="date" name="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="filtro-acoes">
            <button class="btn-primario" type="submit">Filtrar</button>
            <a class="btn-secundario" href="<?= e('exportar_auditoria_excel.php?' . http_build_query(array_filter([
                'registro' => $registroBusca,
                'acao' => $acaoBusca,
                'usuario' => $usuarioBusca,
                'status_anterior' => $statusAnteriorBusca,
                'status_novo' => $statusNovoBusca,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
            ], static fn($valor) => $valor !== ''))) ?>">Exportar Excel</a>
            <a class="btn-secundario" href="auditoria.php">Limpar filtros</a>
            <a class="btn-secundario" href="logout.php">Sair</a>
        </div>
    </form>

    <table class="tabela tabela-auditoria">
        <thead>
            <tr>
                <th>Data</th>
                <th>Registro</th>
                <th>Ação</th>
                <th>Usuário</th>
                <th>Antes</th>
                <th>Depois</th>
                <th>Observação</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="7" class="vazio">Nenhum evento de auditoria encontrado com os filtros aplicados.</td>
            </tr>
            <?php endif; ?>

            <?php while ($row = $result->fetch_assoc()): ?>
            <?php $registro = numeroRegistroExibicao($row); ?>
            <tr>
                <td><?= e(formatarDataHoraAuditoria((string)($row['criado_em'] ?? ''))) ?></td>
                <td class="col-registro">
                    <a href="visualizar.php?registro=<?= rawurlencode($registro) ?>"><?= e($registro) ?></a>
                    <?php if (!empty($row['nome'])): ?>
                        <br><small><?= e((string)$row['nome']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge-acao <?= e((string)($row['acao'] ?? '')) ?>">
                        <?= e(formatarAcaoAuditoria((string)($row['acao'] ?? ''))) ?>
                    </span>
                </td>
                <td><?= e((string)($row['admin_usuario'] ?? '-')) ?></td>
                <td>
                    <?php if (!empty($row['valor_anterior'])): ?>
                        <span class="badge-status <?= e((string)$row['valor_anterior']) ?>"><?= e(formatarStatusAuditoria((string)$row['valor_anterior'])) ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($row['valor_novo'])): ?>
                        <span class="badge-status <?= e((string)$row['valor_novo']) ?>"><?= e(formatarStatusAuditoria((string)$row['valor_novo'])) ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="col-observacao"><?= e((string)($row['observacao'] ?? '-')) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="paginacao">
        <div class="paginacao-info">
            <?= (int)$totalRegistros ?> evento(s) encontrado(s) | Página <?= (int)$paginaAtual ?> de <?= (int)$totalPaginas ?>
        </div>
        <div class="paginacao-links">
            <?php if ($paginaAtual > 1): ?>
                <a class="paginacao-link" href="<?= e(urlAuditoria(['pagina' => $paginaAtual - 1])) ?>">Anterior</a>
            <?php endif; ?>

            <?php for ($pagina = max(1, $paginaAtual - 2); $pagina <= min($totalPaginas, $paginaAtual + 2); $pagina++): ?>
                <a class="paginacao-link <?= $pagina === $paginaAtual ? 'ativo' : '' ?>" href="<?= e(urlAuditoria(['pagina' => $pagina])) ?>"><?= (int)$pagina ?></a>
            <?php endfor; ?>

            <?php if ($paginaAtual < $totalPaginas): ?>
                <a class="paginacao-link" href="<?= e(urlAuditoria(['pagina' => $paginaAtual + 1])) ?>">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleMenu() {
    document.getElementById("menu").classList.toggle("active");
    document.getElementById("overlay").classList.toggle("active");
}
</script>
</body>
</html>
