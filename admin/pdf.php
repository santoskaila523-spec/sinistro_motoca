<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    exit("Acesso negado");
}

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config/db.php";
date_default_timezone_set('America/Sao_Paulo');

use Dompdf\Dompdf;
use Dompdf\Options;

$registroParam = trim((string)($_GET['registro'] ?? ''));

$sinistro = null;
if ($registroParam !== '') {
    $stmtSinistro = $conn->prepare("SELECT * FROM sinistros WHERE numero_registro = ? LIMIT 1");
    if ($stmtSinistro) {
        $stmtSinistro->bind_param("s", $registroParam);
        $stmtSinistro->execute();
        $sinistro = $stmtSinistro->get_result()->fetch_assoc();
        $stmtSinistro->close();
    }
}

if (!$sinistro) {
    exit("Sinistro não encontrado");
}

$id = (int)$sinistro['id'];
$forcarDownload = isset($_GET['download']) ? (string)($_GET['download']) !== '0' : true;
$cachePath = __DIR__ . "/../uploads/sinistros/{$id}/relatorio_sinistro.pdf";

if (is_file($cachePath)) {
    $cacheMtime = (int)(filemtime($cachePath) ?: 0);
    $cacheOk = $cacheMtime > 0;
    $dirCache = dirname($cachePath);

    if ($cacheOk && is_dir($dirCache)) {
        foreach (glob($dirCache . "/*") as $arquivo) {
            if (basename((string)$arquivo) === basename($cachePath)) {
                continue;
            }
            $mtime = (int)(filemtime($arquivo) ?: 0);
            if ($mtime > $cacheMtime) {
                $cacheOk = false;
                break;
            }
        }
    }

    if ($cacheOk) {
        $pdfBinario = (string)file_get_contents($cachePath);
        if (headers_sent($arquivo, $linha)) {
            exit("Falha ao enviar PDF: cabeçalhos já enviados em {$arquivo}:{$linha}");
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($forcarDownload ? 'attachment' : 'inline') . '; filename="sinistro_' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdfBinario));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdfBinario;
        exit;
    }
}

function pdfEsc($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function caminhoImagemPdf(string $path): string
{
    $real = realpath($path);
    if ($real === false || !is_file($real)) {
        return '';
    }

    $mime = mime_content_type($real);
    if (!is_string($mime) || strpos($mime, 'image/') !== 0) {
        return '';
    }

    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp') && function_exists('imagepng')) {
        $resource = @imagecreatefromwebp($real);
        if ($resource !== false) {
            ob_start();
            imagepng($resource);
            $conteudoConvertido = ob_get_clean();

            if ($conteudoConvertido !== false && $conteudoConvertido !== '') {
                return 'data:image/png;base64,' . base64_encode($conteudoConvertido);
            }
        }
    }

    $conteudo = @file_get_contents($real);
    if ($conteudo === false) {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode($conteudo);
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

function pdfPerfilTerceiroLegivel(?string $valor): string
{
    return match ((string)$valor) {
        'proprietario' => 'Proprietário',
        'procurador' => html_entity_decode('Procurador do propriet&aacute;rio', ENT_QUOTES, 'UTF-8'),
        'curador_tutela' => 'Curador/Tutor',
        'seguradora_associacao_coperativa' => html_entity_decode('Seguradora/Associa&ccedil;&atilde;o/Cooperativa', ENT_QUOTES, 'UTF-8'),
        'nenhuma_das_opcoes_anteriores' => 'Nenhuma das opções anteriores',
        default => ucfirst(str_replace('_', ' ', (string)$valor)),
    };
}

function nomeAmigavelAnexo(string $arquivo): string
{
    $nome = pathinfo($arquivo, PATHINFO_FILENAME);
    $nome = preg_replace('/_\d+$/', '', $nome);

    $mapa = [
        'procuracao' => 'Procuração',
        'documento_oficial_foto' => 'Documento oficial com foto',
        'documento_oficial_procurador' => 'Documento oficial do procurador',
        'laudo_curatela' => 'Laudo de curatela',
        'termo_curatela' => 'Termo de curatela',
        'bo' => 'Boletim de ocorrência',
        'cnh' => 'CNH',
        'crlv' => 'CRLV',
        'documento_representacao_seguradora' => 'Documento de representação da seguradora',
        'outros_documentos' => 'Outros documentos',
        'orcamento' => 'Orçamento',
        'comprovante_conserto' => 'Comprovante de conserto',
        'local_acidente' => 'Foto do local do acidente',
        'camera_local' => 'Mídia da câmera do local',
        '360_frente' => 'Foto 360 da frente',
        '360_traseira' => 'Foto 360 da traseira',
        '360_lado_esq' => 'Foto 360 do lado esquerdo',
        '360_lado_dir' => 'Foto 360 do lado direito',
        'assinatura_condutor' => 'Assinatura do condutor',
        'assinatura' => 'Assinatura',
    ];

    return $mapa[$nome] ?? str_replace('_', ' ', ucfirst($nome));
}

$logoPath = __DIR__ . "/../assets/img/logo.png";
$logo = caminhoImagemPdf($logoPath);

$fotos = [];
$fotos360 = [];
$fotosCamera = [];
$assinaturaCondutor = '';
$anexosPdf = [];
$anexosPdf360 = [];
$anexosPdfCamera = [];
$anexosVideo = [];
$anexosVideo360 = [];
$anexosVideoCamera = [];
$pasta = realpath(__DIR__ . "/../uploads/sinistros/$id");

if ($pasta) {
    $arquivos = glob($pasta . "/*");
    sort($arquivos, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($arquivos as $file) {
        $nomeArquivo = basename($file);
        $eh360 = preg_match('/^360_(frente|traseira|lado_esq|lado_dir)_/i', $nomeArquivo) === 1;
        $ehCameraLocal = preg_match('/^camera_local_/i', $nomeArquivo) === 1;
        $ehAssinatura = preg_match('/^assinatura(?:[_-].+)?\.(png|jpg|jpeg|webp)$/i', $nomeArquivo) === 1;
        $ehAssinaturaCondutor = preg_match('/^assinatura_condutor(?:[_-].+)?\.(png|jpg|jpeg|webp)$/i', $nomeArquivo) === 1;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $imagemPdf = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? caminhoImagemPdf($file) : '';

        if ($eh360 && $imagemPdf !== '') {
            $fotos360[] = $imagemPdf;
        } elseif ($eh360 && $ext === 'pdf') {
            $anexosPdf360[] = basename($file);
        } elseif ($eh360 && in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)) {
            $anexosVideo360[] = basename($file);
        } elseif ($ehCameraLocal && $imagemPdf !== '') {
            $fotosCamera[] = $imagemPdf;
        } elseif ($ehCameraLocal && $ext === 'pdf') {
            $anexosPdfCamera[] = basename($file);
        } elseif ($ehCameraLocal && in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)) {
            $anexosVideoCamera[] = basename($file);
        } elseif ($ehAssinaturaCondutor || $ehAssinatura) {
            $assinaturaCondutor = caminhoImagemPdf($file);
        } elseif ($imagemPdf !== '') {
            $fotos[] = $imagemPdf;
        } elseif ($ext === 'pdf') {
            $anexosPdf[] = basename($file);
        } elseif (in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)) {
            $anexosVideo[] = basename($file);
        }
    }
}

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dataRelatorio = date('d/m/Y H:i');
$dataSinistroFormatado = '';
if (!empty($sinistro['data_hora'])) {
    $tsSinistro = strtotime((string)$sinistro['data_hora']);
    $dataSinistroFormatado = $tsSinistro ? date('d/m/Y H:i', $tsSinistro) : (string)$sinistro['data_hora'];
}

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 40px 50px; }
body {
    font-family: DejaVu Sans;
    font-size: 12px;
    color: #000;
}
.watermark {
    position: fixed;
    top: 140px;
    left: 90px;
    width: 470px;
    opacity: 0.10;
    z-index: 0;
}
.watermark img {
    width: 100%;
}
.content {
    position: relative;
    z-index: 1;
}
h1 {
    text-align: center;
    border-bottom: 2px solid #f5e500;
    padding-bottom: 8px;
    margin-bottom: 22px;
}
.section {
    margin-bottom: 18px;
}
.section-title {
    font-weight: bold;
    border-left: 4px solid #f5e500;
    padding-left: 8px;
    margin-bottom: 8px;
}
table { width: 100%; }
td { padding: 6px; vertical-align: top; }
.fotos {
    width: 100%;
}
.fotos td {
    width: 50%;
    padding: 6px;
    text-align: center;
    background: #000;
}
.fotos img {
    width: 85mm;
    height: auto;
    max-height: 120mm;
    background: #000;
    border: 1px solid #ccc;
}
.linha {
    border-top: 2px solid #f5e500;
    margin: 30px 0;
}
.assinaturas td {
    width: 100%;
    text-align: center;
}
.lista-anexos {
    margin: 0;
    padding-left: 18px;
}
.lista-anexos li {
    margin-bottom: 5px;
    word-break: break-word;
}
</style>
</head>
<body>
<div class="watermark"><img src="' . $logo . '"></div>
<div class="content">
<h1>Relatório de Sinistro</h1>
<div class="section">
<div class="section-title">Dados do Sinistro</div>
<table>
<tr><td><b>Data do sinistro:</b> ' . pdfEsc($dataSinistroFormatado) . '</td><td><b>Data do relat&oacute;rio:</b> ' . pdfEsc($dataRelatorio) . '</td></tr>
<tr><td><b>Número do registro:</b> ' . pdfEsc(numeroRegistro($sinistro)) . '</td><td><b>Status:</b> ' . pdfEsc(ucfirst(str_replace("_", " ", (string)$sinistro['status']))) . '</td></tr>
<tr><td><b>Nome:</b> ' . pdfEsc($sinistro['nome']) . '</td><td><b>Telefone:</b> ' . pdfEsc($sinistro['telefone']) . '</td></tr>
<tr><td><b>E-mail:</b> ' . pdfEsc($sinistro['email']) . '</td><td><b>Placa da motoca:</b> ' . pdfEsc($sinistro['placa_motoca']) . '</td></tr>
<tr><td><b>Placa do terceiro:</b> ' . pdfEsc($sinistro['placa_terceiro']) . '</td><td><b>Tipo de formulário:</b> ' . pdfEsc(ucfirst((string)$sinistro['tipo_formulario'])) . '</td></tr>
</table>
</div>
<div class="section">
<div class="section-title">Dados complementares</div>
<table>
<tr><td><b>Houve vítimas:</b> ' . pdfEsc($sinistro['vitimas'] ?? '') . '</td><td><b>Qtd. de vítimas:</b> ' . pdfEsc($sinistro['qtd_vitimas'] ?? '') . '</td></tr>
<tr><td><b>Informações da vítima:</b> ' . pdfEsc($sinistro['informacoes_vitima'] ?? '') . '</td><td><b>Possui fotos/vídeos do acidente:</b> ' . pdfEsc($sinistro['possui_midias_acidente'] ?? '') . '</td></tr>
<tr><td><b>Realizou BO:</b> ' . pdfEsc($sinistro['realizou_bo'] ?? '') . '</td><td><b>Já houve conserto:</b> ' . pdfEsc($sinistro['ja_houve_conserto'] ?? '') . '</td></tr>
<tr><td><b>Já realizou orçamento:</b> ' . pdfEsc($sinistro['ja_realizou_orcamento'] ?? '') . '</td><td><b>Perfil do terceiro:</b> ' . pdfEsc($sinistro['perfil_terceiro'] ?? '') . '</td></tr>
<tr><td><b>Seguradora/Associação/Cooperativa:</b> ' . pdfEsc($sinistro['seguradora_nome'] ?? '') . '</td><td><b>Quem está representando:</b> ' . pdfEsc($sinistro['seguradora_representando'] ?? '') . '</td></tr>
<tr><td><b>Motivo do contato:</b> ' . pdfEsc($sinistro['motivo_contato_seguradora'] ?? '') . '</td><td><b>Código de validação:</b> ' . pdfEsc($sinistro['codigo_validacao'] ?? '') . '</td></tr>
</table>
</div>
<div class="section">
<div class="section-title">Relato</div>
<p>' . nl2br(pdfEsc($sinistro['relato'])) . '</p>
</div>';

if ($fotos) {
    $html .= '<div class="section"><div class="section-title">Fotos</div><table class="fotos"><tr>';
    $i = 0;
    foreach ($fotos as $foto) {
        if ($i > 0 && $i % 2 === 0) {
            $html .= '</tr><tr>';
        }
        $html .= '<td><img src="' . $foto . '"></td>';
        $i++;
    }
    $html .= '</tr></table></div>';
}

if ($fotosCamera || $anexosPdfCamera || $anexosVideoCamera) {
    $html .= '<div class="section"><div class="section-title">Câmera do local</div>';

    if ($fotosCamera) {
        $html .= '<table class="fotos"><tr>';
        $i = 0;
        foreach ($fotosCamera as $fotoCamera) {
            if ($i > 0 && $i % 2 === 0) {
                $html .= '</tr><tr>';
            }
            $html .= '<td><img src="' . $fotoCamera . '"></td>';
            $i++;
        }
        $html .= '</tr></table>';
    }

    if ($anexosPdfCamera) {
        $html .= '<ul class="lista-anexos">';
        foreach ($anexosPdfCamera as $nomePdfCamera) {
            $html .= '<li>' . pdfEsc(nomeAmigavelAnexo((string)$nomePdfCamera)) . ' (' . pdfEsc((string)$nomePdfCamera) . ')</li>';
        }
        $html .= '</ul>';
    }

    if ($anexosVideoCamera) {
        $html .= '<ul class="lista-anexos">';
        foreach ($anexosVideoCamera as $nomeVideoCamera) {
            $html .= '<li>' . pdfEsc(nomeAmigavelAnexo((string)$nomeVideoCamera)) . ' (' . pdfEsc((string)$nomeVideoCamera) . ')</li>';
        }
        $html .= '</ul>';
    }

    $html .= '</div>';
}

if ($fotos360 || $anexosPdf360 || $anexosVideo360) {
    $html .= '<div class="section"><div class="section-title">Anexos 360 do veículo</div>';

    if ($fotos360) {
        $html .= '<table class="fotos"><tr>';
        $i = 0;
        foreach ($fotos360 as $foto360) {
            if ($i > 0 && $i % 2 === 0) {
                $html .= '</tr><tr>';
            }
            $html .= '<td><img src="' . $foto360 . '"></td>';
            $i++;
        }
        $html .= '</tr></table>';
    }

    if ($anexosPdf360) {
        $html .= '<ul class="lista-anexos">';
        foreach ($anexosPdf360 as $nomePdf360) {
            $html .= '<li>' . pdfEsc(nomeAmigavelAnexo((string)$nomePdf360)) . ' (' . pdfEsc((string)$nomePdf360) . ')</li>';
        }
        $html .= '</ul>';
    }

    if ($anexosVideo360) {
        $html .= '<ul class="lista-anexos">';
        foreach ($anexosVideo360 as $nomeVideo360) {
            $html .= '<li>' . pdfEsc(nomeAmigavelAnexo((string)$nomeVideo360)) . ' (' . pdfEsc((string)$nomeVideo360) . ')</li>';
        }
        $html .= '</ul>';
    }

    $html .= '</div>';
}

if ($anexosPdf) {
    $html .= '<div class="section"><div class="section-title">Anexos em PDF</div><ul class="lista-anexos">';
    foreach ($anexosPdf as $nomePdf) {
        $html .= '<li>' . pdfEsc(nomeAmigavelAnexo((string)$nomePdf)) . ' (' . pdfEsc((string)$nomePdf) . ')</li>';
    }
    $html .= '</ul></div>';
}

if ($anexosVideo) {
    $html .= '<div class="section"><div class="section-title">Anexos de vídeo</div><ul class="lista-anexos">';
    foreach ($anexosVideo as $nomeVideo) {
        $html .= '<li>' . pdfEsc(nomeAmigavelAnexo((string)$nomeVideo)) . ' (' . pdfEsc((string)$nomeVideo) . ')</li>';
    }
    $html .= '</ul></div>';
}

$legendaAssinatura = (($sinistro['tipo_formulario'] ?? '') === 'locatario') ? 'Assinatura do locatário' : 'Assinatura do terceiro';
$assinaturaHtml = '_________________________<br>' . $legendaAssinatura;
if ($assinaturaCondutor !== '') {
    $assinaturaHtml = '<div style="height:29mm;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;"><div style="width:78mm;height:22mm;display:flex;align-items:flex-end;justify-content:center;overflow:hidden;"><img src="' . $assinaturaCondutor . '" style="width:78mm;height:22mm;object-fit:contain;object-position:center bottom;transform:translateY(2.8mm);background:#fff;"></div><div style="width:78mm;border-bottom:1px solid #000;margin-top:1.5mm;"></div></div><div style="margin-top:3mm;">' . pdfEsc($legendaAssinatura) . '</div>';
} else {
    $assinaturaHtml = '<div style="height:29mm;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;"><div style="width:78mm;height:22mm;"></div><div style="width:78mm;border-bottom:1px solid #000;margin-top:1.5mm;"></div></div><div style="margin-top:3mm;">' . pdfEsc($legendaAssinatura) . '</div>';
}

$html .= '
<div class="linha"></div>
<table class="assinaturas">
<tr>
<td>' . $assinaturaHtml . '</td>
</tr>
</table>
<p style="text-align:center;font-size:11px;">
Documento gerado em ' . date('d/m/Y H:i:s') . ' - Uso interno
</p>
</div>
</body>
</html>';

$mapaMojibake = [
    "\xC3\x83\xC2\xA0" => "à",
    "\xC3\x83\xC2\xA1" => "á",
    "\xC3\x83\xC2\xA2" => "â",
    "\xC3\x83\xC2\xA3" => "ã",
    "\xC3\x83\xC2\xA7" => "ç",
    "\xC3\x83\xC2\xA8" => "è",
    "\xC3\x83\xC2\xA9" => "é",
    "\xC3\x83\xC2\xAA" => "ê",
    "\xC3\x83\xC2\xAD" => "í",
    "\xC3\x83\xC2\xB3" => "ó",
    "\xC3\x83\xC2\xB4" => "ô",
    "\xC3\x83\xC2\xB5" => "õ",
    "\xC3\x83\xC2\xBA" => "ú",
    "\xC3\x83\xC2\xBC" => "ü",
];
$html = strtr($html, $mapaMojibake);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

$nomeArquivo = "sinistro_$id.pdf";
$pdfBinario = $dompdf->output();

@file_put_contents($cachePath, $pdfBinario);

if (headers_sent($arquivo, $linha)) {
    exit("Falha ao enviar PDF: cabeçalhos já enviados em {$arquivo}:{$linha}");
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($forcarDownload ? 'attachment' : 'inline') . '; filename="' . $nomeArquivo . '"');
header('Content-Length: ' . strlen($pdfBinario));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdfBinario;
exit;

