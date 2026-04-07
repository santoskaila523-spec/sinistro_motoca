<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

require "../config/db.php";
require "../config/admin_auditoria.php";
require "../config/analise_sinistro_ia.php";
require "../config/documento_texto.php";
require "../config/sinistro_vinculo.php";
date_default_timezone_set('America/Sao_Paulo');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function campo($label, $valor) {
    if (!empty($valor)) {
        echo "<div><strong>" . e($label) . "</strong><span>" . e($valor) . "</span></div>";
    }
}

function nomeArquivoExibicao(string $nomeArquivo): string {
    $ext = pathinfo($nomeArquivo, PATHINFO_EXTENSION);
    $base = pathinfo($nomeArquivo, PATHINFO_FILENAME);

    // Remove sufixos técnicos (uniqid/token) mantidos apenas para evitar colisão no disco.
    $base = preg_replace('/_([a-f0-9]{13}(?:\.[a-f0-9]{8})?)$/i', '', $base);
    $base = preg_replace('/_[A-Za-z0-9]{16,}$/', '', $base);
    $base = trim(str_replace('_', ' ', $base));

    if ($ext !== '') {
        return $base . '.' . strtolower($ext);
    }

    return $base;
}

function rotuloArquivoAmigavel(string $nomeArquivo): string {
    $base = strtolower(pathinfo($nomeArquivo, PATHINFO_FILENAME));

    if (strpos($base, 'procuracao_') === 0 || $base === 'procuracao') {
        return 'Procuração';
    }
    if (strpos($base, 'crlv_') === 0 || $base === 'crlv') {
        return 'CRLV';
    }
    if (strpos($base, 'cnh_') === 0 || $base === 'cnh') {
        return 'CNH';
    }
    if (strpos($base, 'bo_') === 0 || $base === 'bo') {
        return 'Boletim de Ocorrência (BO)';
    }
    if (strpos($base, 'documento_representacao_seguradora_') === 0 || $base === 'documento_representacao_seguradora') {
        return 'Documento de representação da seguradora';
    }
    if (strpos($base, 'documento_oficial_procurador_') === 0 || $base === 'documento_oficial_procurador') {
        return 'Documento oficial do procurador';
    }
    if (strpos($base, 'outros_documentos_') === 0) {
        return 'Outros documentos';
    }
    if (strpos($base, 'orcamento_') === 0 || $base === 'orcamento') {
        return 'Orçamento';
    }
    if (strpos($base, 'comprovante_conserto_') === 0 || $base === 'comprovante_conserto') {
        return 'Comprovante de conserto';
    }
    if (strpos($base, 'camera_local_') === 0) {
        return 'Câmera do local';
    }
    if (strpos($base, 'local_acidente_') === 0) {
        return 'Local do acidente';
    }
    if (strpos($base, '360_frente_') === 0) {
        return '360 Frente';
    }
    if (strpos($base, '360_traseira_') === 0) {
        return '360 Traseira';
    }
    if (strpos($base, '360_lado_esq_') === 0) {
        return '360 Lado esquerdo';
    }
    if (strpos($base, '360_lado_dir_') === 0) {
        return '360 Lado direito';
    }
    if (strpos($base, 'assinatura_condutor') === 0) {
        return 'Assinatura do condutor';
    }
    if (strpos($base, 'assinatura') === 0) {
        return 'Assinatura';
    }

    return 'Anexo';
}

function numeroRegistro(array $row): string {
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

function formatarDataHora(?string $valor): string {
    if (!$valor) {
        return '';
    }

    $timestamp = strtotime($valor);
    if ($timestamp === false) {
        return '';
    }

    return date('d/m/Y H:i', $timestamp);
}

function formatarPerfilTerceiro(?string $valor): string {
    return match ((string)$valor) {
        'proprietario' => 'Proprietário',
        'procurador' => html_entity_decode('Procurador do propriet&aacute;rio', ENT_QUOTES, 'UTF-8'),
        'curador_tutela' => 'Curador/Tutor',
        'seguradora_associacao_coperativa' => html_entity_decode('Seguradora/Associa&ccedil;&atilde;o/Cooperativa', ENT_QUOTES, 'UTF-8'),
        'nenhuma_das_opcoes_anteriores' => 'Nenhuma das opções anteriores',
        default => ucfirst(str_replace('_', ' ', (string)$valor)),
    };
}

function formatarAcaoHistorico(string $acao): string
{
    return match ($acao) {
        'abertura_sinistro' => 'Abertura do sinistro',
        'alteracao_status' => 'Alteração de status',
        'exclusao_sinistro' => 'Exclusão do sinistro',
        'atualizacao_financeira' => 'Atualização financeira',
        default => ucfirst(str_replace('_', ' ', $acao)),
    };
}

function formatarMoedaBr($valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function percentualBarraFinanceira(?float $valor, float $referencia): float
{
    if ($valor === null || $valor <= 0 || $referencia <= 0) {
        return 0;
    }

    $percentual = ($valor / $referencia) * 100;
    return max(8, min(100, $percentual));
}

$registroParam = trim((string)($_GET['registro'] ?? ''));
$idParam = (int)($_GET['id'] ?? 0);

$sinistro = null;
if ($registroParam !== '') {
    $stmtSinistro = $conn->prepare("SELECT * FROM sinistros WHERE numero_registro = ? LIMIT 1");
    if ($stmtSinistro) {
        $stmtSinistro->bind_param("s", $registroParam);
        $stmtSinistro->execute();
        $sinistro = $stmtSinistro->get_result()->fetch_assoc();
        $stmtSinistro->close();
    }

    if (!$sinistro) {
        die("Sinistro não encontrado.");
    }
}

if (!$sinistro && $idParam > 0) {
    $stmtSinistro = $conn->prepare("SELECT * FROM sinistros WHERE id = ? LIMIT 1");
    if ($stmtSinistro) {
        $stmtSinistro->bind_param("i", $idParam);
        $stmtSinistro->execute();
        $sinistro = $stmtSinistro->get_result()->fetch_assoc();
        $stmtSinistro->close();
    }
}

if (!$sinistro) {
    die("Sinistro não encontrado.");
}

$id = (int)$sinistro['id'];
$registroAtual = numeroRegistro($sinistro);
$historicoSinistro = listarAuditoriaSinistro($conn, $id, 30);
$sinistrosRelacionados = sinistroListarRelacionados($conn, $sinistro);
$lancouFranquiaAtual = sinistroTabelaTemColuna($conn, 'lancou_franquia') ? (string)($sinistro['lancou_franquia'] ?? '') : '';
$valorFranquiaAtual = sinistroTabelaTemColuna($conn, 'valor_franquia') && isset($sinistro['valor_franquia']) && $sinistro['valor_franquia'] !== null ? (float)$sinistro['valor_franquia'] : null;
$valorIndenizacaoAtual = sinistroTabelaTemColuna($conn, 'valor_indenizacao') && isset($sinistro['valor_indenizacao']) && $sinistro['valor_indenizacao'] !== null ? (float)$sinistro['valor_indenizacao'] : null;
$referenciaFinanceira = max((float)($valorFranquiaAtual ?? 0), (float)($valorIndenizacaoAtual ?? 0), 1);

/* Pastas */
$pasta = "../uploads/sinistros/$id/";
$arquivos = is_dir($pasta) ? glob($pasta . "*") : [];

/* Separa fotos */
$fotos = [];
$anexosPdf = [];
$videos = [];
$fotosCamera = [];
$anexosPdfCamera = [];
$videosCamera = [];
$assinatura = '';
$assinaturaCondutor = '';
$fotos360 = [];
$anexosPdf360 = [];
$videos360 = [];
$outros = [];

foreach ($arquivos as $arq) {
    $nomeArquivo = basename($arq);
    $eh360 = preg_match('/^360_(frente|traseira|lado_esq|lado_dir)_/i', $nomeArquivo) === 1;
    $ehCameraLocal = preg_match('/^camera_local_/i', $nomeArquivo) === 1;
    $ehAssinatura = preg_match('/^assinatura(?:[_-].+)?\.(png|jpg|jpeg|webp)$/i', $nomeArquivo) === 1;
    $ehAssinaturaCondutor = preg_match('/^assinatura_condutor(?:[_-].+)?\.(png|jpg|jpeg|webp)$/i', $nomeArquivo) === 1;
    $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
    if ($eh360 && in_array($ext, ['jpg','jpeg','png','webp'])) {
        $fotos360[] = $arq;
    } elseif ($eh360 && $ext === 'pdf') {
        $anexosPdf360[] = $arq;
    } elseif ($eh360 && in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
        $videos360[] = $arq;
    } elseif ($ehCameraLocal && in_array($ext, ['jpg','jpeg','png','webp'])) {
        $fotosCamera[] = $arq;
    } elseif ($ehCameraLocal && $ext === 'pdf') {
        $anexosPdfCamera[] = $arq;
    } elseif ($ehCameraLocal && in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
        $videosCamera[] = $arq;
    } elseif ($ehAssinaturaCondutor) {
        $assinaturaCondutor = $arq;
    } elseif ($ehAssinatura) {
        $assinatura = $arq;
    } elseif (in_array($ext, ['jpg','jpeg','png','webp'])) {
        $fotos[] = $arq;
    } elseif ($ext === 'pdf') {
        $anexosPdf[] = $arq;
    } elseif (in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
        $videos[] = $arq;
    } else {
        $outros[] = $arq;
    }
}

$nomesArquivos = array_map(static fn($arq) => strtolower(basename((string)$arq)), $arquivos);
$arquivoBoletim = localizarArquivoBoletim($arquivos);
$leituraBoletim = [
    'ok' => false,
    'texto' => '',
    'origem' => '',
    'erro' => $arquivoBoletim ? 'Arquivo do BO localizado, mas sem leitura automatica.' : 'Nenhum BO localizado entre os anexos.',
];

if ($arquivoBoletim !== null && strtolower(pathinfo($arquivoBoletim, PATHINFO_EXTENSION)) === 'pdf') {
    $leituraBoletim = extrairTextoPdf($arquivoBoletim);
} elseif ($arquivoBoletim !== null) {
    $leituraBoletim = extrairEvidenciaArquivo($arquivoBoletim);
}

$arquivoImagemAnalise = $fotosCamera[0] ?? $fotos[0] ?? $fotos360[0] ?? null;
$leituraImagem = [
    'ok' => false,
    'texto' => '',
    'origem' => 'imagem',
    'erro' => $arquivoImagemAnalise ? 'Imagem localizada, aguardando OCR.' : 'Nenhuma imagem prioritaria localizada para leitura automatica.',
    'metadados' => [],
];
if ($arquivoImagemAnalise !== null) {
    $leituraImagem = extrairEvidenciaArquivo($arquivoImagemAnalise);
}

$arquivoVideoAnalise = $videosCamera[0] ?? $videos[0] ?? $videos360[0] ?? null;
$leituraVideo = [
    'ok' => false,
    'texto' => '',
    'origem' => 'video',
    'erro' => $arquivoVideoAnalise ? 'Video localizado, aguardando pipeline de leitura.' : 'Nenhum video localizado para leitura automatica.',
    'metadados' => [],
];
if ($arquivoVideoAnalise !== null) {
    $leituraVideo = extrairEvidenciaArquivo($arquivoVideoAnalise);
}

$arquivosPorCategoriaAnalise = [
    'fotos' => count($fotos),
    'fotos_camera' => count($fotosCamera),
    'fotos_360' => count($fotos360),
    'anexos_pdf' => count($anexosPdf) + count($anexosPdfCamera) + count($anexosPdf360),
    'tem_bo' => $arquivoBoletim !== null,
    'tem_cnh' => in_array('cnh_01.jpeg', $nomesArquivos, true) || preg_grep('/^cnh_/i', $nomesArquivos),
    'tem_crlv' => in_array('crlv_01.jpeg', $nomesArquivos, true) || preg_grep('/^crlv_/i', $nomesArquivos),
    'leitura_bo_ok' => (bool)($leituraBoletim['ok'] ?? false),
    'origem_boletim' => (string)($leituraBoletim['origem'] ?? ''),
    'erro_boletim' => (string)($leituraBoletim['erro'] ?? ''),
    'texto_boletim' => (string)($leituraBoletim['texto'] ?? ''),
    'leitura_imagem_ok' => (bool)($leituraImagem['ok'] ?? false),
    'erro_imagem' => (string)($leituraImagem['erro'] ?? ''),
    'texto_imagem' => (string)($leituraImagem['texto'] ?? ''),
    'metadados_imagem' => $leituraImagem['metadados'] ?? [],
    'leitura_video_ok' => (bool)($leituraVideo['ok'] ?? false),
    'erro_video' => (string)($leituraVideo['erro'] ?? ''),
    'texto_video' => (string)($leituraVideo['texto'] ?? ''),
    'metadados_video' => $leituraVideo['metadados'] ?? [],
];

$analiseAssistida = gerarAnaliseAssistidaSinistro($sinistro, $arquivosPorCategoriaAnalise);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Relatório de Sinistro</title>

<style>
body {
    background:#111;
    font-family:Segoe UI, Arial;
    color:#000;
}

.folha {
    max-width:900px;
    margin:40px auto;
    background:#fff;
    padding:50px 60px;
    box-shadow:0 0 30px rgba(0,0,0,.6);
    position:relative;
}

.folha::before {
    content:"";
    position:absolute;
    inset:0;
    background:url("../assets/img/logo-site-3d.png") no-repeat center;
    background-size:280px;
    opacity:.05;
}

.conteudo { position:relative; z-index:2; }

h1 {
    text-align:center;
    border-bottom:3px solid #f5e500;
    padding-bottom:12px;
    margin-bottom:35px;
}

.section { margin-bottom:35px; }

.section-title {
    font-weight:700;
    border-left:5px solid #f5e500;
    padding-left:12px;
    margin-bottom:18px;
}

.grid {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
}

.grid div {
    font-size:14px;
}

.grid strong {
    display:block;
    font-size:12px;
    color:#555;
    margin-bottom:2px;
}

.status-select {
    padding:12px 18px;
    border-radius:30px;
    border:2px solid #f5e500;
    font-size:15px;
    outline:none;
}

.btn {
    background:#f5e500;
    color:#000;
    border:none;
    padding:10px 22px;
    font-weight:600;
    border-radius:6px;
    cursor:pointer;
}

.btn:hover {
    filter:brightness(.9);
}

.fotos {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
}

.fotos img {
    width:100%;
    height:auto;
    object-fit:contain;
    background:#111;
    border-radius:6px;
    border:1px solid #ccc;
}

.assinatura-img {
    display:block;
    max-width:100%;
    width:100%;
    height:100%;
    object-fit:contain;
    object-position:center bottom;
    background:transparent;
    border:0;
    border-radius:0;
    padding:0;
    transform:translateY(14px);
}

.painel-assinatura {
    width: 45%;
    margin-bottom: 22px;
}

.assinaturas-editor {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: flex-start;
}

.assinaturas-editor .espaco-assinatura {
    width: 45%;
}

.assinatura-box-admin {
    margin-top: 10px;
    border: 2px solid #f5e500;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
}

.assinatura-canvas-admin {
    display: block;
    width: 100%;
    height: 180px;
    background: #fff;
    touch-action: none;
    cursor: crosshair;
}

.assinatura-acoes-admin {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 12px;
}

.videos {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
}

.videos video {
    width:100%;
    max-height:260px;
    border-radius:6px;
    border:1px solid #ccc;
    background:#000;
}

.video-item {
    display:flex;
    flex-direction:column;
    gap:8px;
}

.video-acoes {
    display:flex;
    justify-content:flex-end;
}

.btn-video {
    display:inline-block;
    text-decoration:none;
    background:#f5e500;
    color:#000;
    border:none;
    padding:8px 14px;
    font-weight:600;
    border-radius:6px;
    font-size:13px;
}

.btn-video:hover {
    filter:brightness(.9);
}

.lista-anexos li {
    margin-bottom:8px;
    font-size:14px;
}

.acoes {
    display:flex;
    justify-content:space-between;
    margin-top:45px;
}

.rodape {
    margin-top:60px;
    font-size:13px;
}

.rodape .linha {
    border-top:2px solid #f5e500;
    margin-bottom:30px;
}

.assinaturas {
    display:flex;
    justify-content:center;
    gap:0;
    text-align:center;
}

.assinatura-coluna {
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:flex-end;
    flex:1 1 0;
    min-height:132px;
}

.assinatura-area {
    width:100%;
    max-width:340px;
    min-height:146px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:flex-end;
    margin-bottom:6px;
}

.assinatura-conteudo {
    width:100%;
    height:118px;
    display:flex;
    align-items:flex-end;
    justify-content:center;
    overflow:hidden;
}

.assinatura-linha {
    width:100%;
    max-width:340px;
    border-bottom:2px solid #2b2b2b;
    height:0;
    margin-top:6px;
}

.assinatura-legenda {
    font-size:15px;
    line-height:1.2;
    min-width:340px;
}

.info-rodape {
    text-align:center;
    margin-top:25px;
    color:#777;
}

.analise-badge {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:210px;
    padding:10px 14px;
    border-radius:999px;
    font-size:13px;
    font-weight:700;
    text-align:center;
}

.analise-badge.terceiro {
    background:#dff7e7;
    color:#17653a;
}

.analise-badge.motoca {
    background:#fde3e3;
    color:#9a2525;
}

.analise-badge.compartilhada {
    background:#fff1cf;
    color:#8a5b00;
}

.analise-badge.neutra {
    background:#ececec;
    color:#4f4f4f;
}

.analise-externa {
    border:1px solid #d7dde7;
    background:linear-gradient(180deg, #f7fafc 0%, #eef3f8 100%);
    border-radius:22px;
    padding:24px 26px;
}

.auditoria-assistida {
    border:1px solid #d7dde7;
    background:linear-gradient(180deg, #fffdf2 0%, #f7f3d8 100%);
    border-radius:22px;
    padding:24px 26px;
}

.auditoria-assistida h2 {
    margin:0 0 10px;
    font-size:28px;
}

.auditoria-grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
    margin:18px 0 20px;
}

.auditoria-card {
    background:#fff;
    border-radius:14px;
    border:1px solid #eadf97;
    padding:14px 16px;
}

.auditoria-card strong {
    display:block;
    font-size:12px;
    color:#7a6900;
    margin-bottom:4px;
}

.auditoria-lista {
    margin:8px 0 0;
    padding-left:18px;
}

.auditoria-lista li {
    margin-bottom:8px;
}

.auditoria-pre {
    margin-top:18px;
    padding:16px 18px;
    border-radius:16px;
    background:#fff;
    border:1px solid #eadf97;
    white-space:pre-wrap;
    line-height:1.55;
    font-family:inherit;
}

.analise-externa-topo {
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:flex-start;
}

.analise-externa h2 {
    margin:0;
    font-size:28px;
}

.analise-externa p {
    line-height:1.5;
}

.analise-externa-meta {
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
    margin:18px 0 20px;
}

.analise-externa-card {
    background:#fff;
    border-radius:14px;
    border:1px solid #d8e2ee;
    padding:14px 16px;
}

.analise-externa-card strong {
    display:block;
    font-size:12px;
    color:#526174;
    margin-bottom:4px;
}

.analise-externa-lista {
    margin:8px 0 0;
    padding-left:18px;
}

.analise-externa-lista li {
    margin-bottom:8px;
}

.analise-externa-acoes {
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
    margin-top:14px;
}

.analise-externa-msg {
    padding:12px 14px;
    border-radius:12px;
    margin-top:14px;
    font-size:14px;
}

.analise-externa-msg.ok {
    background:#e7f8ee;
    color:#18633a;
    border:1px solid #b8e3c6;
}

.analise-externa-msg.erro {
    background:#fdecec;
    color:#972828;
    border:1px solid #efc0c0;
}

.analise-externa-disclaimer {
    margin-top:18px;
    font-size:13px;
    color:#5f6a77;
}

.relacionados-grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
}

.relacionado-card {
    background:#fff;
    border:1px solid #e2e2e2;
    border-radius:16px;
    padding:16px 18px;
}

.relacionado-card h3 {
    margin:0 0 8px;
    font-size:18px;
}

.relacionado-card p {
    margin:6px 0;
}

@media (max-width: 720px) {
    .acoes,
    .assinaturas-editor,
    .assinaturas {
        flex-direction:column;
    }

    .analise-badge,
    .painel-assinatura,
    .assinaturas-editor .espaco-assinatura {
        width:100%;
        min-width:0;
    }

    .analise-externa-meta,
    .auditoria-grid,
    .relacionados-grid,
    .grid,
    .fotos,
    .videos {
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<div class="folha">
<div class="conteudo">

<h1>Relatório de Sinistro</h1>

<div class="section">
<div class="section-title">Dados do Sinistro</div>
<div class="grid">
<?php
campo('Registro', numeroRegistro($sinistro));
campo('Data do sinistro', formatarDataHora($sinistro['data_hora'] ?? null));
campo('Data do relatório', date('d/m/Y H:i'));
campo('Nome', $sinistro['nome']);
campo('Telefone', $sinistro['telefone']);
campo('E-mail', $sinistro['email']);
campo('Placa Motoca', $sinistro['placa_motoca']);
campo('Placa Terceiro', $sinistro['placa_terceiro']);
campo('Tipo de Formulário', ucfirst($sinistro['tipo_formulario']));
campo('CEP', $sinistro['cep']);
campo('Logradouro', $sinistro['logradouro']);
campo('Bairro', $sinistro['bairro']);
campo('Cidade', $sinistro['cidade']);
campo('Estado', $sinistro['estado']);
campo('Sentido da Via', $sinistro['sentido_via']);
campo('Ponto de Referência', $sinistro['ponto_referencia']);
campo('Tipo de Ocorrência', $sinistro['tipo_ocorrencia']);
campo('Situação da Via', $sinistro['situacao_via']);
campo('Houve Vítimas', ucfirst($sinistro['vitimas']));
campo('Qtd. Vítimas', $sinistro['qtd_vitimas']);
if (sinistroTabelaTemColuna($conn, 'informacoes_vitima')) {
    campo('Informações da vítima', $sinistro['informacoes_vitima'] ?? '');
}
if (sinistroTabelaTemColuna($conn, 'perfil_terceiro')) {
    campo('Perfil do terceiro', formatarPerfilTerceiro($sinistro['perfil_terceiro'] ?? ''));
}
if (sinistroTabelaTemColuna($conn, 'possui_midias_acidente')) {
    campo('Possui fotos/vídeos do acidente', ucfirst((string)($sinistro['possui_midias_acidente'] ?? '')));
}
if (sinistroTabelaTemColuna($conn, 'realizou_bo')) {
    campo('Realizou BO', ucfirst((string)($sinistro['realizou_bo'] ?? '')));
}
if (sinistroTabelaTemColuna($conn, 'ja_houve_conserto')) {
    campo('Já houve conserto do veículo', ucfirst((string)($sinistro['ja_houve_conserto'] ?? '')));
}
if (sinistroTabelaTemColuna($conn, 'ja_realizou_orcamento')) {
    campo('Já realizou orçamento', ucfirst((string)($sinistro['ja_realizou_orcamento'] ?? '')));
}
if (sinistroTabelaTemColuna($conn, 'seguradora_nome')) {
    campo('Seguradora/Associação/Cooperativa', $sinistro['seguradora_nome'] ?? '');
}
if (sinistroTabelaTemColuna($conn, 'seguradora_representando')) {
    campo('Quem está representando', $sinistro['seguradora_representando'] ?? '');
}
if (sinistroTabelaTemColuna($conn, 'motivo_contato_seguradora')) {
    campo('Motivo do contato', $sinistro['motivo_contato_seguradora'] ?? '');
}
if (sinistroTabelaTemColuna($conn, 'canal_codigo_validacao')) {
    campo('Canal do código de validação', strtoupper((string)($sinistro['canal_codigo_validacao'] ?? '')));
}
if (sinistroTabelaTemColuna($conn, 'destino_codigo_validacao')) {
    campo('Destino do código', $sinistro['destino_codigo_validacao'] ?? '');
}
if (sinistroTabelaTemColuna($conn, 'codigo_validacao')) {
    campo('Código de validação', $sinistro['codigo_validacao'] ?? '');
}
campo('Status Atual', ucfirst(str_replace('_',' ', $sinistro['status'])));
?>
</div>
</div>

<?php if (!empty($sinistrosRelacionados)): ?>
<div class="section">
<div class="section-title">Formulários do Mesmo Sinistro</div>
<div class="relacionados-grid">
    <?php foreach ($sinistrosRelacionados as $relacionado): ?>
    <?php $registroRelacionado = !empty($relacionado['numero_registro']) ? (string)$relacionado['numero_registro'] : numeroRegistro($relacionado); ?>
    <div class="relacionado-card">
        <h3><?= e(ucfirst((string)($relacionado['tipo_formulario'] ?? 'formulário'))) ?></h3>
        <p><strong>Registro:</strong> <?= e($registroRelacionado) ?></p>
        <p><strong>Nome:</strong> <?= e((string)($relacionado['nome'] ?? '')) ?></p>
        <p><strong>Data:</strong> <?= e(formatarDataHora((string)($relacionado['data_hora'] ?? ''))) ?></p>
        <p><strong>Status:</strong> <?= e(ucfirst(str_replace('_', ' ', (string)($relacionado['status'] ?? '')))) ?></p>
        <p><strong>Placa Motoca:</strong> <?= e((string)($relacionado['placa_motoca'] ?? '')) ?></p>
        <?php if (!empty($relacionado['placa_terceiro'])): ?>
        <p><strong>Placa Terceiro:</strong> <?= e((string)$relacionado['placa_terceiro']) ?></p>
        <?php endif; ?>
        <p><strong>Relato:</strong> <?= e(mb_substr(trim((string)($relacionado['relato'] ?? '')), 0, 280, 'UTF-8')) ?><?= mb_strlen(trim((string)($relacionado['relato'] ?? '')), 'UTF-8') > 280 ? '...' : '' ?></p>
        <?php if ((int)($relacionado['id'] ?? 0) !== $id): ?>
        <p><a class="btn-video" href="visualizar.php?id=<?= (int)($relacionado['id'] ?? 0) ?>">Abrir este formulario</a></p>
        <?php else: ?>
        <p><strong>Formulário atual em análise.</strong></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<div class="section">
<div class="section-title">Relatório Assistido Para Auditoria</div>
<div class="auditoria-assistida">
    <h2><?= e((string)($analiseAssistida['conclusao'] ?? 'Análise assistida não disponível')) ?></h2>
    <p style="margin:0;line-height:1.6;"><?= e((string)($analiseAssistida['resumo'] ?? '')) ?></p>

    <div class="auditoria-grid">
        <div class="auditoria-card">
            <strong>Confiança estimada</strong>
            <span><?= e((string)($analiseAssistida['confianca'] ?? 0)) ?>%</span>
        </div>
        <div class="auditoria-card">
            <strong>Fontes avaliadas</strong>
            <span><?= e((string)(count((array)($analiseAssistida['evidencias_multimodais'] ?? [])))) ?> evidências catalogadas</span>
        </div>
    </div>

    <?php if (!empty($analiseAssistida['fundamentos_ctb'])): ?>
    <strong>Fundamentos técnicos sugeridos do CTB</strong>
    <ul class="auditoria-lista">
        <?php foreach ((array)$analiseAssistida['fundamentos_ctb'] as $item): ?>
        <li>
            <strong><?= e((string)($item['artigo'] ?? '')) ?> - <?= e((string)($item['titulo'] ?? '')) ?></strong><br>
            <?= e((string)($item['resumo'] ?? '')) ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (!empty($analiseAssistida['evidencias_multimodais'])): ?>
    <strong>Evidências por fonte</strong>
    <ul class="auditoria-lista">
        <?php foreach ((array)$analiseAssistida['evidencias_multimodais'] as $item): ?>
        <li><?= e((string)($item['fonte'] ?? '')) ?>: <?= e((string)($item['resumo'] ?? '')) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (!empty($analiseAssistida['pendencias'])): ?>
    <strong>Pontos de atenção para auditoria</strong>
    <ul class="auditoria-lista">
        <?php foreach ((array)$analiseAssistida['pendencias'] as $item): ?>
        <li><?= e((string)$item) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (!empty($analiseAssistida['relatorio_auditoria'])): ?>
    <div class="auditoria-pre"><?= e((string)$analiseAssistida['relatorio_auditoria']) ?></div>
    <?php endif; ?>
</div>
</div>

<div class="section">
<div class="section-title">Alterar Status</div>

<select id="status" class="status-select">
    <option value="em_andamento" <?= $sinistro['status']=='em_andamento'?'selected':'' ?>>Em andamento</option>
    <option value="finalizado" <?= $sinistro['status']=='finalizado'?'selected':'' ?>>Finalizado</option>
    <option value="processo_juridico" <?= $sinistro['status']=='processo_juridico'?'selected':'' ?>>Processo Jurídico</option>
    <option value="arquivado" <?= $sinistro['status']=='arquivado'?'selected':'' ?>>Arquivado</option>
</select>

<div style="margin-top:12px;">
    <label for="observacao_status" style="display:block;font-weight:700;margin-bottom:6px;">Observação interna da alteração</label>
    <textarea id="observacao_status" style="width:100%;min-height:90px;padding:10px;border:1px solid #d8d8d8;border-radius:10px;" placeholder="Ex: Cliente apresentou novo documento, análise concluída, caso enviado ao jurídico..."></textarea>
</div>

<button class="btn" onclick="salvarStatus()">Salvar</button>
<span id="msgStatus" style="margin-left:15px;"></span>

</div>

<?php if ($historicoSinistro): ?>
<div class="section">
<div class="section-title">Histórico do Sinistro</div>
<div class="grid">
<?php foreach ($historicoSinistro as $itemHistorico): ?>
<div>
    <strong><?= e(formatarAcaoHistorico((string)($itemHistorico['acao'] ?? ''))) ?></strong>
    <span>
        <?= e(formatarDataHora((string)($itemHistorico['criado_em'] ?? ''))) ?>
        <?php if (!empty($itemHistorico['admin_usuario'])): ?>
            | por <?= e((string)$itemHistorico['admin_usuario']) ?>
        <?php endif; ?>
        <?php if (!empty($itemHistorico['valor_anterior']) || !empty($itemHistorico['valor_novo'])): ?>
            <br>De: <?= e((string)($itemHistorico['valor_anterior'] ?? '-')) ?> | Para: <?= e((string)($itemHistorico['valor_novo'] ?? '-')) ?>
        <?php endif; ?>
        <?php if (!empty($itemHistorico['observacao'])): ?>
            <br><?= e((string)$itemHistorico['observacao']) ?>
        <?php endif; ?>
    </span>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<div class="section">
<div class="section-title">Relato</div>
<p><?= nl2br(e($sinistro['relato'])) ?></p>
</div>

<?php if ($fotos): ?>
<div class="section">
<div class="section-title">Fotos</div>
<div class="fotos">
<?php foreach ($fotos as $foto): ?>
<?php $nomeFoto = basename($foto); ?>
<img src="<?= e("../uploads/sinistros/$id/" . rawurlencode($nomeFoto)) ?>">
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php if ($fotosCamera || $anexosPdfCamera || $videosCamera): ?>
<div class="section">
<div class="section-title">Câmera do local</div>
<?php if ($fotosCamera): ?>
<div class="fotos">
<?php foreach ($fotosCamera as $foto): ?>
<?php $nomeFoto = basename($foto); ?>
<img src="<?= e("../uploads/sinistros/$id/" . rawurlencode($nomeFoto)) ?>">
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($videosCamera): ?>
<div class="videos">
<?php foreach ($videosCamera as $video): ?>
<?php $nomeVideo = basename($video); ?>
<?php $urlVideo = "../uploads/sinistros/$id/" . rawurlencode($nomeVideo); ?>
<div class="video-item">
    <video controls preload="metadata">
        <source src="<?= e($urlVideo) ?>">
        Seu navegador não suporta vídeo HTML5.
    </video>
    <div class="video-acoes">
        <a class="btn-video" href="<?= e($urlVideo) ?>" download="<?= e($nomeVideo) ?>">Baixar</a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($anexosPdfCamera): ?>
<ul class="lista-anexos">
<?php foreach ($anexosPdfCamera as $arq): ?>
<?php $nome = basename($arq); $nomeExibicao = nomeArquivoExibicao($nome); $rotulo = rotuloArquivoAmigavel($nome); ?>
<li><?= e($rotulo) ?>: <?= e($nomeExibicao) ?> - <a href="<?= e("../uploads/sinistros/$id/" . rawurlencode($nome)) ?>" target="_blank" rel="noopener noreferrer">Abrir</a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($fotos360 || $anexosPdf360 || $videos360): ?>
<div class="section">
<div class="section-title">Anexos 360 do Veículo</div>
<?php if ($fotos360): ?>
<div class="fotos">
<?php foreach ($fotos360 as $foto): ?>
<?php $nomeFoto = basename($foto); ?>
<img src="<?= e("../uploads/sinistros/$id/" . rawurlencode($nomeFoto)) ?>">
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($anexosPdf360): ?>
<ul class="lista-anexos">
<?php foreach ($anexosPdf360 as $arq): ?>
<?php $nome = basename($arq); $nomeExibicao = nomeArquivoExibicao($nome); $rotulo = rotuloArquivoAmigavel($nome); ?>
<li><?= e($rotulo) ?>: <?= e($nomeExibicao) ?> - <a href="<?= e("../uploads/sinistros/$id/" . rawurlencode($nome)) ?>" target="_blank" rel="noopener noreferrer">Abrir</a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($videos360): ?>
<div class="videos">
<?php foreach ($videos360 as $video): ?>
<?php $nomeVideo = basename($video); ?>
<?php $urlVideo = "../uploads/sinistros/$id/" . rawurlencode($nomeVideo); ?>
<div class="video-item">
    <video controls preload="metadata">
        <source src="<?= e($urlVideo) ?>">
        Seu navegador não suporta vídeo HTML5.
    </video>
    <div class="video-acoes">
        <a class="btn-video" href="<?= e($urlVideo) ?>" download="<?= e($nomeVideo) ?>">Baixar</a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($videos): ?>
<div class="section">
<div class="section-title">Vídeos</div>
<div class="videos">
<?php foreach ($videos as $video): ?>
<?php $nomeVideo = basename($video); ?>
<?php $urlVideo = "../uploads/sinistros/$id/" . rawurlencode($nomeVideo); ?>
<div class="video-item">
    <video controls preload="metadata">
        <source src="<?= e($urlVideo) ?>">
        Seu navegador não suporta vídeo HTML5.
    </video>
    <div class="video-acoes">
        <a class="btn-video" href="<?= e($urlVideo) ?>" download="<?= e($nomeVideo) ?>">Baixar</a>
    </div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php if ($anexosPdf): ?>
<div class="section">
<div class="section-title">Anexos PDF</div>
<ul class="lista-anexos">
<?php foreach ($anexosPdf as $arq): ?>
<?php $nome = basename($arq); $nomeExibicao = nomeArquivoExibicao($nome); $rotulo = rotuloArquivoAmigavel($nome); ?>
<li><?= e($rotulo) ?>: <?= e($nomeExibicao) ?> - <a href="<?= e("../uploads/sinistros/$id/" . rawurlencode($nome)) ?>" target="_blank" rel="noopener noreferrer">Abrir</a></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if ($outros): ?>
<div class="section">
<div class="section-title">Anexos</div>
<ul class="lista-anexos">
<?php foreach ($outros as $arq): ?>
<?php $nome = basename($arq); $nomeExibicao = nomeArquivoExibicao($nome); $rotulo = rotuloArquivoAmigavel($nome); ?>
<li><?= e($rotulo) ?>: <?= e($nomeExibicao) ?> - <a href="<?= e("../uploads/sinistros/$id/" . rawurlencode($nome)) ?>" target="_blank" rel="noopener noreferrer">Abrir</a></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<div class="rodape">
<div class="linha"></div>

<div class="assinaturas">
<div class="assinatura-coluna">
<div class="assinatura-area">
<div class="assinatura-conteudo">
<?php $assinaturaExibida = $assinaturaCondutor !== '' ? $assinaturaCondutor : $assinatura; ?>
<?php if ($assinaturaExibida !== ''): ?>
<?php $nomeAssinatura = basename($assinaturaExibida); ?>
<img class="assinatura-img" src="<?= e("../uploads/sinistros/$id/" . rawurlencode($nomeAssinatura)) ?>" alt="Assinatura do condutor">
<?php endif; ?>
</div>
<div class="assinatura-linha"></div>
</div>
<div class="assinatura-legenda"><?= ($sinistro['tipo_formulario'] ?? '') === 'locatario' ? 'Assinatura do locatário' : 'Assinatura do terceiro' ?></div>
</div>
</div>

<div class="info-rodape">
Documento gerado em <?= date('d/m/Y H:i') ?><br>
MOTOCA - Uso interno
</div>
</div>

<div class="acoes">
<a href="pdf.php?registro=<?= rawurlencode($registroAtual) ?>&download=1" class="btn">Baixar PDF</a>
<a href="javascript:history.back()" class="btn">Voltar</a>
</div>

</div>
</div>

<script>
function salvarStatus() {
    const status = document.getElementById("status").value;
    const observacao = document.getElementById("observacao_status").value.trim();
    const msg = document.getElementById("msgStatus");
    const statusComMotivoObrigatorio = ["processo_juridico", "arquivado"];

    if (statusComMotivoObrigatorio.includes(status) && observacao === "") {
        msg.textContent = "Informe o motivo antes de salvar esse status.";
        msg.style.color = "red";
        document.getElementById("observacao_status").focus();
        return;
    }

    msg.textContent = "Salvando...";
    msg.style.color = "#555";

    fetch("status_ajax.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id: <?= $id ?>,
            status: status,
            observacao: observacao,
            csrf_token: "<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        })
    })
    .then(async r => {
        let resp;

        try {
            resp = await r.json();
        } catch (erro) {
            throw new Error("Resposta inválida do servidor");
        }

        if (!r.ok) {
            throw new Error(resp && resp.msg ? resp.msg : "Erro ao salvar");
        }

        return resp;
    })
    .then(resp => {
        if (resp.ok) {
            msg.textContent = resp.msg || "Status atualizado";
            msg.style.color = "green";
            document.getElementById("observacao_status").value = "";

            localStorage.setItem("statusAtualizado", "1");
        } else {
            msg.textContent = resp.msg || "Erro ao salvar";
            msg.style.color = "red";
        }
    })
    .catch(erro => {
        msg.textContent = erro.message || "Erro ao salvar";
        msg.style.color = "red";
    });
}

</script>

</body>
</html>



