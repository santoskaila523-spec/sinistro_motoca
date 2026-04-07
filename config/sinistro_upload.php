<?php

function proximoIndiceArquivo(string $destinoDir, string $prefixo): int
{
    $prefixoLimpo = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($prefixo));
    if ($prefixoLimpo === '') {
        $prefixoLimpo = 'arquivo';
    }

    $maior = 0;
    foreach (glob(rtrim($destinoDir, '/\\') . DIRECTORY_SEPARATOR . $prefixoLimpo . '_*.*') ?: [] as $arquivo) {
        $nome = basename($arquivo);
        if (preg_match('/^' . preg_quote($prefixoLimpo, '/') . '_(\d+)\./', $nome, $match)) {
            $maior = max($maior, (int)$match[1]);
        }
    }

    return $maior + 1;
}

function salvarArquivoUpload(string $campo, string $destinoDir, string $prefixo = ''): void
{
    if (empty($_FILES[$campo]['name']) || !isset($_FILES[$campo]['tmp_name'])) {
        return;
    }

    if (($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        if (function_exists('appLogErro')) {
            appLogErro('upload_arquivo_falhou', ['campo' => $campo, 'motivo' => 'upload_error']);
        }
        return;
    }

    $tmp = (string)$_FILES[$campo]['tmp_name'];
    $nomeOriginal = (string)$_FILES[$campo]['name'];
    $tamanho = (int)($_FILES[$campo]['size'] ?? 0);
    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'mp4', 'mov', 'avi', 'webm', 'mkv'];
    $maxBytes = in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)
        ? 50 * 1024 * 1024
        : 10 * 1024 * 1024;

    if (
        !$ext ||
        !in_array($ext, $permitidas, true) ||
        $tamanho <= 0 ||
        $tamanho > $maxBytes ||
        !is_uploaded_file($tmp)
    ) {
        if (function_exists('appLogErro')) {
            appLogErro('upload_arquivo_rejeitado', [
                'campo' => $campo,
                'extensao' => $ext,
                'tamanho' => $tamanho,
            ]);
        }
        return;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $mimesPermitidos = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime', 'video/mp4'],
        'avi' => ['video/x-msvideo', 'video/avi', 'application/octet-stream'],
        'webm' => ['video/webm'],
        'mkv' => ['video/x-matroska', 'application/octet-stream'],
    ];

    if ($mime === '' || !in_array($mime, $mimesPermitidos[$ext] ?? [], true)) {
        if (function_exists('appLogErro')) {
            appLogErro('upload_arquivo_mime_invalido', [
                'campo' => $campo,
                'extensao' => $ext,
                'mime' => $mime,
            ]);
        }
        return;
    }

    $prefixoLimpo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefixo);
    $prefixoLimpo = trim((string)$prefixoLimpo, '_');
    if ($prefixoLimpo === '') {
        $prefixoLimpo = 'arquivo';
    }

    $indice = proximoIndiceArquivo($destinoDir, $prefixoLimpo);
    $nomeSeguro = $prefixoLimpo . '_' . str_pad((string)$indice, 2, '0', STR_PAD_LEFT) . '.' . $ext;
    $destino = rtrim($destinoDir, '/\\') . DIRECTORY_SEPARATOR . $nomeSeguro;

    if (!move_uploaded_file($tmp, $destino) && function_exists('appLogErro')) {
        appLogErro('upload_arquivo_move_falhou', [
            'campo' => $campo,
            'destino' => $destino,
        ]);
    }
}

function salvarArquivosMultiplos(string $campo, string $destinoDir, string $prefixo = ''): void
{
    if (empty($_FILES[$campo]['name']) || !is_array($_FILES[$campo]['name'])) {
        return;
    }

    $total = count($_FILES[$campo]['name']);
    for ($i = 0; $i < $total; $i++) {
        if (empty($_FILES[$campo]['name'][$i])) {
            continue;
        }

        $_FILES[$campo . '_item'] = [
            'name' => $_FILES[$campo]['name'][$i],
            'type' => $_FILES[$campo]['type'][$i] ?? '',
            'tmp_name' => $_FILES[$campo]['tmp_name'][$i],
            'error' => $_FILES[$campo]['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES[$campo]['size'][$i] ?? 0,
        ];
        salvarArquivoUpload($campo . '_item', $destinoDir, $prefixo);
    }

    unset($_FILES[$campo . '_item']);
}

function salvarAssinaturaBase64(string $conteudo, string $destinoDir, string $nomeArquivo = 'assinatura_condutor.png'): void
{
    if (!preg_match('#^data:image/png;base64,(.+)$#', $conteudo, $matches)) {
        if (function_exists('appLogErro')) {
            appLogErro('assinatura_invalida', ['motivo' => 'formato_invalido']);
        }
        return;
    }

    $binario = base64_decode(str_replace(' ', '+', $matches[1]), true);
    if ($binario === false || $binario === '') {
        if (function_exists('appLogErro')) {
            appLogErro('assinatura_invalida', ['motivo' => 'base64_invalido']);
        }
        return;
    }

    file_put_contents(rtrim($destinoDir, "\\/") . DIRECTORY_SEPARATOR . $nomeArquivo, $binario);
}
