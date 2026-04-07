<?php

function localizarPdfToText(): ?string
{
    $candidatos = [
        'C:\\Program Files\\Git\\mingw64\\bin\\pdftotext.exe',
        'C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe',
        'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
        'pdftotext',
    ];

    foreach ($candidatos as $caminho) {
        if ($caminho === 'pdftotext') {
            return $caminho;
        }

        if (is_file($caminho)) {
            return $caminho;
        }
    }

    return null;
}

function localizarTesseract(): ?string
{
    $candidatos = [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        'tesseract',
    ];

    foreach ($candidatos as $caminho) {
        if ($caminho === 'tesseract') {
            return $caminho;
        }

        if (is_file($caminho)) {
            return $caminho;
        }
    }

    return null;
}

function localizarFfmpeg(): ?string
{
    $candidatos = [
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
        'ffmpeg',
    ];

    foreach ($candidatos as $caminho) {
        if ($caminho === 'ffmpeg') {
            return $caminho;
        }

        if (is_file($caminho)) {
            return $caminho;
        }
    }

    return null;
}

function extrairTextoPdf(string $arquivoPdf): array
{
    if (!is_file($arquivoPdf)) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'pdf',
            'erro' => 'Arquivo PDF nao encontrado.',
        ];
    }

    $binario = localizarPdfToText();
    if ($binario === null) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'pdf',
            'erro' => 'pdftotext nao disponivel no servidor.',
        ];
    }

    $arquivoSaida = tempnam(sys_get_temp_dir(), 'bo_txt_');
    if ($arquivoSaida === false) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'pdf',
            'erro' => 'Nao foi possivel criar arquivo temporario.',
        ];
    }

    $comando = escapeshellarg($binario)
        . ' -layout -enc UTF-8 '
        . escapeshellarg($arquivoPdf) . ' '
        . escapeshellarg($arquivoSaida) . ' 2>&1';

    $saida = [];
    $codigo = 1;
    @exec($comando, $saida, $codigo);

    $texto = '';
    if (is_file($arquivoSaida)) {
        $conteudo = @file_get_contents($arquivoSaida);
        if ($conteudo !== false) {
            $texto = trim((string)$conteudo);
        }
        @unlink($arquivoSaida);
    }

    if ($codigo !== 0 || $texto === '') {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'pdf',
            'erro' => 'Nao foi possivel extrair texto do PDF do boletim.',
            'detalhe' => trim(implode("\n", $saida)),
        ];
    }

    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

    return [
        'ok' => true,
        'texto' => trim($texto),
        'origem' => 'pdf',
        'erro' => '',
    ];
}

function localizarArquivoBoletim(array $arquivos): ?string
{
    foreach ($arquivos as $arquivo) {
        $nome = strtolower(basename((string)$arquivo));
        if (str_starts_with($nome, 'bo_') || $nome === 'bo.pdf') {
            return (string)$arquivo;
        }
    }

    return null;
}

function resumirTextoLeitura(string $texto, int $limite = 700): string
{
    $texto = trim((string)(preg_replace('/\s+/u', ' ', $texto) ?? $texto));
    if ($texto === '') {
        return '';
    }

    return mb_substr($texto, 0, $limite, 'UTF-8');
}

function extrairMetadadosImagem(string $arquivoImagem): array
{
    $metadados = [];

    $tamanho = @getimagesize($arquivoImagem);
    if (is_array($tamanho)) {
        $metadados[] = 'Dimensoes: ' . (int)$tamanho[0] . 'x' . (int)$tamanho[1];
    }

    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($arquivoImagem, null, true);
        if (is_array($exif)) {
            $dataFoto = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? '';
            if (is_string($dataFoto) && trim($dataFoto) !== '') {
                $metadados[] = 'Data da captura: ' . trim($dataFoto);
            }

            $gpsLat = $exif['GPS']['GPSLatitude'] ?? null;
            $gpsLng = $exif['GPS']['GPSLongitude'] ?? null;
            if ($gpsLat && $gpsLng) {
                $metadados[] = 'Imagem com metadados GPS disponiveis';
            }
        }
    }

    return $metadados;
}

function extrairTextoImagem(string $arquivoImagem): array
{
    if (!is_file($arquivoImagem)) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'imagem',
            'erro' => 'Arquivo de imagem nao encontrado.',
            'metadados' => [],
        ];
    }

    $metadados = extrairMetadadosImagem($arquivoImagem);
    $binario = localizarTesseract();
    if ($binario === null) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'imagem',
            'erro' => 'OCR para imagem indisponivel no servidor (tesseract nao encontrado).',
            'metadados' => $metadados,
        ];
    }

    $arquivoBase = tempnam(sys_get_temp_dir(), 'img_ocr_');
    if ($arquivoBase === false) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'imagem',
            'erro' => 'Nao foi possivel criar arquivo temporario para OCR.',
            'metadados' => $metadados,
        ];
    }

    @unlink($arquivoBase);
    $comando = escapeshellarg($binario) . ' '
        . escapeshellarg($arquivoImagem) . ' '
        . escapeshellarg($arquivoBase) . ' -l por 2>&1';

    $saida = [];
    $codigo = 1;
    @exec($comando, $saida, $codigo);

    $arquivoTxt = $arquivoBase . '.txt';
    $texto = is_file($arquivoTxt) ? trim((string)@file_get_contents($arquivoTxt)) : '';
    if (is_file($arquivoTxt)) {
        @unlink($arquivoTxt);
    }

    if ($codigo !== 0 || $texto === '') {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'imagem',
            'erro' => 'Nao foi possivel extrair texto da imagem.',
            'metadados' => $metadados,
            'detalhe' => trim(implode("\n", $saida)),
        ];
    }

    return [
        'ok' => true,
        'texto' => trim((string)(preg_replace('/\s+/u', ' ', $texto) ?? $texto)),
        'origem' => 'imagem',
        'erro' => '',
        'metadados' => $metadados,
    ];
}

function extrairResumoVideo(string $arquivoVideo): array
{
    if (!is_file($arquivoVideo)) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'video',
            'erro' => 'Arquivo de video nao encontrado.',
            'metadados' => [],
        ];
    }

    $metadados = ['Arquivo de video localizado para analise complementar.'];
    $binario = localizarFfmpeg();
    if ($binario === null) {
        return [
            'ok' => false,
            'texto' => '',
            'origem' => 'video',
            'erro' => 'Leitura automatica de video indisponivel no servidor (ffmpeg nao encontrado).',
            'metadados' => $metadados,
        ];
    }

    return [
        'ok' => false,
        'texto' => '',
        'origem' => 'video',
        'erro' => 'Pipeline de leitura de frame de video ainda nao foi habilitado nesta etapa.',
        'metadados' => $metadados,
    ];
}

function extrairEvidenciaArquivo(string $arquivo): array
{
    $ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));

    if ($ext === 'pdf') {
        return extrairTextoPdf($arquivo);
    }

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return extrairTextoImagem($arquivo);
    }

    if (in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)) {
        return extrairResumoVideo($arquivo);
    }

    return [
        'ok' => false,
        'texto' => '',
        'origem' => 'arquivo',
        'erro' => 'Formato de arquivo nao suportado para leitura automatica.',
        'metadados' => [],
    ];
}
