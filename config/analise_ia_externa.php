<?php

require_once __DIR__ . '/app_log.php';

function analiseIaExternaNormalizarBool($valor, bool $padrao = true): bool
{
    if ($valor === null || $valor === '') {
        return $padrao;
    }

    if (is_bool($valor)) {
        return $valor;
    }

    $texto = strtolower(trim((string)$valor));
    return !in_array($texto, ['0', 'false', 'off', 'no', 'nao'], true);
}

function analiseIaExternaCarregarConfig(): array
{
    $config = [
        'enabled' => true,
        'base_url' => 'https://api.openai.com/v1',
        'api_key' => '',
        'model' => '',
        'timeout' => 60,
        'max_images' => 3,
        'provider_label' => 'IA externa',
    ];

    $arquivoLocal = __DIR__ . '/analise_ia_externa.local.php';
    if (is_file($arquivoLocal)) {
        $configLocal = require $arquivoLocal;
        if (is_array($configLocal)) {
            $config = array_merge($config, $configLocal);
        }
    }

    $config['enabled'] = analiseIaExternaNormalizarBool(
        getenv('MOTOCA_AI_ENABLED'),
        analiseIaExternaNormalizarBool($config['enabled'] ?? true)
    );
    $config['base_url'] = rtrim((string)(getenv('MOTOCA_AI_BASE_URL') ?: $config['base_url']), '/');
    $config['api_key'] = (string)(getenv('MOTOCA_AI_API_KEY') ?: $config['api_key']);
    $config['model'] = (string)(getenv('MOTOCA_AI_MODEL') ?: $config['model']);
    $config['timeout'] = (int)(getenv('MOTOCA_AI_TIMEOUT') ?: $config['timeout']);
    $config['max_images'] = max(0, (int)(getenv('MOTOCA_AI_MAX_IMAGES') ?: $config['max_images']));
    $config['provider_label'] = (string)(getenv('MOTOCA_AI_PROVIDER_LABEL') ?: $config['provider_label']);

    return $config;
}

function analiseIaExternaDisponivel(array $config): bool
{
    return !empty($config['enabled']) && trim((string)($config['api_key'] ?? '')) !== '' && trim((string)($config['model'] ?? '')) !== '';
}

function analiseIaExternaMensagemConfiguracao(array $config): string
{
    if (empty($config['enabled'])) {
        return 'IA externa desativada por configuração.';
    }

    if (trim((string)($config['api_key'] ?? '')) === '') {
        return 'Defina a variável de ambiente MOTOCA_AI_API_KEY para habilitar a IA externa.';
    }

    if (trim((string)($config['model'] ?? '')) === '') {
        return 'Defina a variável de ambiente MOTOCA_AI_MODEL para habilitar a IA externa.';
    }

    return '';
}

function analiseIaExternaDirCache(): string
{
    $dir = dirname(__DIR__) . '/logs/ai_analises';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function analiseIaExternaCachePath(int $sinistroId): string
{
    return analiseIaExternaDirCache() . '/sinistro_' . $sinistroId . '.json';
}

function analiseIaExternaCorrigirTextoMojibake(string $texto): string
{
    $atual = $texto;

    for ($i = 0; $i < 3; $i++) {
        if (!preg_match('/Ã|Â|�/u', $atual)) {
            break;
        }

        $latin1 = @iconv('UTF-8', 'Windows-1252//IGNORE', $atual);
        if (!is_string($latin1) || $latin1 === '') {
            break;
        }

        $corrigido = @mb_convert_encoding($latin1, 'UTF-8', 'Windows-1252');
        if (!is_string($corrigido) || $corrigido === '' || $corrigido === $atual) {
            break;
        }

        $atual = $corrigido;
    }

    return $atual;
}

function analiseIaExternaCorrigirEstruturaMojibake(mixed $valor): mixed
{
    if (is_array($valor)) {
        $corrigido = [];
        foreach ($valor as $chave => $item) {
            $corrigido[$chave] = analiseIaExternaCorrigirEstruturaMojibake($item);
        }
        return $corrigido;
    }

    if (is_string($valor)) {
        return analiseIaExternaCorrigirTextoMojibake($valor);
    }

    return $valor;
}

function analiseIaExternaLerCache(int $sinistroId): ?array
{
    $arquivo = analiseIaExternaCachePath($sinistroId);
    if (!is_file($arquivo)) {
        return null;
    }

    $json = @file_get_contents($arquivo);
    if (!is_string($json) || trim($json) === '') {
        return null;
    }

    $dados = json_decode($json, true);
    return is_array($dados) ? analiseIaExternaCorrigirEstruturaMojibake($dados) : null;
}

function analiseIaExternaSalvarCache(int $sinistroId, array $payload): void
{
    @file_put_contents(
        analiseIaExternaCachePath($sinistroId),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}

function analiseIaExternaLimitarTexto(string $texto, int $limite = 6000): string
{
    $texto = trim((string)(preg_replace('/\s+/u', ' ', $texto) ?? $texto));
    if (mb_strlen($texto, 'UTF-8') <= $limite) {
        return $texto;
    }

    return mb_substr($texto, 0, $limite, 'UTF-8');
}

function analiseIaExternaCriarDataUrlImagem(string $arquivo, int $larguraMaxima = 1400, int $qualidade = 78): ?string
{
    if (!is_file($arquivo)) {
        return null;
    }

    $binario = @file_get_contents($arquivo);
    if ($binario === false) {
        return null;
    }

    $imagem = @imagecreatefromstring($binario);
    if (!$imagem) {
        $mime = mime_content_type($arquivo) ?: 'application/octet-stream';
        return 'data:' . $mime . ';base64,' . base64_encode($binario);
    }

    $largura = imagesx($imagem);
    $altura = imagesy($imagem);
    if ($largura <= 0 || $altura <= 0) {
        imagedestroy($imagem);
        return null;
    }

    $destino = $imagem;
    if ($largura > $larguraMaxima) {
        $novaLargura = $larguraMaxima;
        $novaAltura = (int)round(($altura / $largura) * $novaLargura);
        $redimensionada = imagecreatetruecolor($novaLargura, max(1, $novaAltura));
        imagecopyresampled($redimensionada, $imagem, 0, 0, 0, 0, $novaLargura, max(1, $novaAltura), $largura, $altura);
        $destino = $redimensionada;
    }

    ob_start();
    imagejpeg($destino, null, $qualidade);
    $jpeg = (string)ob_get_clean();

    if ($destino !== $imagem) {
        imagedestroy($destino);
    }
    imagedestroy($imagem);

    if ($jpeg === '') {
        return null;
    }

    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
}

function analiseIaExternaExtrairJsonResposta(string $conteudo): array
{
    $conteudo = trim(analiseIaExternaCorrigirTextoMojibake($conteudo));
    if ($conteudo === '') {
        return [];
    }

    $json = json_decode($conteudo, true);
    if (is_array($json)) {
        return $json;
    }

    if (preg_match('/\{.*\}/s', $conteudo, $match)) {
        $json = json_decode($match[0], true);
        if (is_array($json)) {
            return $json;
        }
    }

    return [];
}

function analiseIaExternaNormalizarResposta(array $dados, array $meta = []): array
{
    $status = (string)($dados['status'] ?? 'indeterminada');
    $mapaStatus = [
        'culpa_terceiro' => 'Há indícios de culpa predominante do terceiro',
        'culpa_motoca' => 'Há indícios de culpa predominante do condutor da Motoca',
        'culpa_compartilhada' => 'Há indícios de culpa concorrente ou compartilhada',
        'indeterminada' => 'Indícios insuficientes para concluir culpabilidade predominante',
    ];

    $classe = match ($status) {
        'culpa_terceiro' => 'terceiro',
        'culpa_motoca' => 'motoca',
        'culpa_compartilhada' => 'compartilhada',
        default => 'neutra',
    };

    $limparLista = static function ($valor): array {
        if (!is_array($valor)) {
            return [];
        }

        $saida = [];
        foreach ($valor as $item) {
            $texto = trim((string)$item);
            if ($texto !== '') {
                $saida[] = $texto;
            }
        }
        return array_values(array_slice($saida, 0, 6));
    };

    return [
        'status' => $status,
        'titulo' => $mapaStatus[$status] ?? $mapaStatus['indeterminada'],
        'classe' => $classe,
        'confianca' => max(0, min(100, (int)($dados['confianca'] ?? 0))),
        'resumo_executivo' => trim((string)($dados['resumo_executivo'] ?? '')),
        'parecer_auditoria' => trim((string)($dados['parecer_auditoria'] ?? '')),
        'fundamentos' => $limparLista($dados['fundamentos'] ?? []),
        'enquadramentos_ctb' => $limparLista($dados['enquadramentos_ctb'] ?? []),
        'evidencias_multimodais' => $limparLista($dados['evidencias_multimodais'] ?? []),
        'contradicoes' => $limparLista($dados['contradicoes'] ?? []),
        'pendencias' => $limparLista($dados['pendencias'] ?? []),
        'proximos_passos' => $limparLista($dados['proximos_passos'] ?? []),
        'disclaimer' => trim((string)($dados['disclaimer'] ?? 'Análise preliminar de apoio. Não substitui decisão humana, jurídica ou pericial.')),
        'modelo' => (string)($meta['modelo'] ?? ''),
        'provider_label' => (string)($meta['provider_label'] ?? 'IA externa'),
        'gerado_em' => date('c'),
        'origem' => 'api_externa',
    ];
}

function analiseIaExternaExtrairTextoMensagem(array $json): string
{
    $conteudo = analiseIaExternaCorrigirTextoMojibake((string)($json['choices'][0]['message']['content'] ?? ''));
    if ($conteudo !== '') {
        return $conteudo;
    }

    $partes = $json['choices'][0]['message']['content'] ?? null;
    if (is_array($partes)) {
        $texto = '';
        foreach ($partes as $parte) {
            if (($parte['type'] ?? '') === 'text') {
                $texto .= analiseIaExternaCorrigirTextoMojibake((string)($parte['text'] ?? ''));
            }
        }
        return $texto;
    }

    return '';
}

function analiseIaExternaGerar(array $config, array $dadosAnalise, array $imagens = []): array
{
    if (!analiseIaExternaDisponivel($config)) {
        return [false, analiseIaExternaMensagemConfiguracao($config), null];
    }

    $resumoCaso = [
        'registro' => (string)($dadosAnalise['registro'] ?? ''),
        'grupo_sinistro' => (string)($dadosAnalise['grupo_sinistro'] ?? ''),
        'data_hora' => (string)($dadosAnalise['data_hora'] ?? ''),
        'tipo_formulario' => (string)($dadosAnalise['tipo_formulario'] ?? ''),
        'tipo_ocorrencia' => (string)($dadosAnalise['tipo_ocorrencia'] ?? ''),
        'situacao_via' => (string)($dadosAnalise['situacao_via'] ?? ''),
        'local' => [
            'logradouro' => (string)($dadosAnalise['logradouro'] ?? ''),
            'bairro' => (string)($dadosAnalise['bairro'] ?? ''),
            'cidade' => (string)($dadosAnalise['cidade'] ?? ''),
            'estado' => (string)($dadosAnalise['estado'] ?? ''),
            'sentido_via' => (string)($dadosAnalise['sentido_via'] ?? ''),
            'ponto_referencia' => (string)($dadosAnalise['ponto_referencia'] ?? ''),
        ],
        'envolvidos' => [
            'placa_motoca' => (string)($dadosAnalise['placa_motoca'] ?? ''),
            'placa_terceiro' => (string)($dadosAnalise['placa_terceiro'] ?? ''),
        ],
        'relato' => analiseIaExternaLimitarTexto((string)($dadosAnalise['relato'] ?? ''), 5000),
        'boletim' => analiseIaExternaLimitarTexto((string)($dadosAnalise['texto_boletim'] ?? ''), 4000),
        'imagem_ocr' => analiseIaExternaLimitarTexto((string)($dadosAnalise['texto_imagem'] ?? ''), 2000),
        'video_resumo' => analiseIaExternaLimitarTexto((string)($dadosAnalise['texto_video'] ?? ''), 1500),
        'metadados_imagem' => $dadosAnalise['metadados_imagem'] ?? [],
        'metadados_video' => $dadosAnalise['metadados_video'] ?? [],
        'fundamentos_ctb_sugeridos' => $dadosAnalise['fundamentos_ctb_sugeridos'] ?? [],
        'relatorio_auditoria_base' => analiseIaExternaLimitarTexto((string)($dadosAnalise['relatorio_auditoria_base'] ?? ''), 3000),
        'arquivos' => $dadosAnalise['arquivos'] ?? [],
        'formularios_relacionados' => $dadosAnalise['formularios_relacionados'] ?? [],
    ];

    $promptSistema = 'Você analisa sinistros de trânsito para triagem interna. '
        . 'Seu objetivo é sugerir a suposta dinâmica do acidente, a culpabilidade inicial e um relatório útil para auditoria interna, com cautela. '
        . 'Pode haver mais de um formulário para o mesmo evento, especialmente um do locatário e outro do terceiro. '
        . 'Quando houver formulários relacionados, compare as versões, destaque convergências e contradições, e trate tudo como um único sinistro com relatos distintos. '
        . 'Cruze relato, BO, OCR de imagem, resumo de vídeo, metadados e fundamentos sugeridos do CTB apenas como referências técnicas preliminares, sem afirmar culpa definitiva. '
        . 'Não invente fatos. Se a prova estiver fraca, diga que é indeterminada. '
        . 'Responda exclusivamente em JSON válido com as chaves: '
        . 'status, confianca, resumo_executivo, parecer_auditoria, fundamentos, enquadramentos_ctb, evidencias_multimodais, contradicoes, pendencias, proximos_passos, disclaimer. '
        . 'status deve ser um entre: culpa_terceiro, culpa_motoca, culpa_compartilhada, indeterminada.';

    $conteudoUsuario = [
        [
            'type' => 'text',
            'text' => 'Analise o caso abaixo e produza um parecer bem elaborado, objetivo e interno.' . "\n\n"
                . json_encode($resumoCaso, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ],
    ];

    foreach ($imagens as $imagem) {
        if (!empty($imagem['data_url'])) {
            $conteudoUsuario[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => (string)$imagem['data_url'],
                    'detail' => 'auto',
                ],
            ];
        }
    }

    $payload = [
        'model' => (string)$config['model'],
        'temperature' => 0.2,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $promptSistema],
            ['role' => 'user', 'content' => $conteudoUsuario],
        ],
    ];

    $ch = curl_init((string)$config['base_url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . (string)$config['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => (int)$config['timeout'],
        CURLOPT_CONNECTTIMEOUT => min(20, (int)$config['timeout']),
    ]);

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $statusHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($erroCurl !== '') {
        appLogErro('analise_ia_externa_curl', ['erro' => $erroCurl]);
        return [false, 'Falha de comunicação com a IA externa: ' . $erroCurl, null];
    }

    $json = json_decode((string)$resposta, true);
    if ($statusHttp < 200 || $statusHttp >= 300) {
        $mensagem = trim((string)($json['error']['message'] ?? 'Erro HTTP ' . $statusHttp . ' ao consultar a IA externa.'));
        appLogErro('analise_ia_externa_http', ['status' => $statusHttp, 'resposta' => (string)$resposta]);
        return [false, $mensagem, null];
    }

    $texto = is_array($json) ? analiseIaExternaExtrairTextoMensagem($json) : '';
    $dados = analiseIaExternaExtrairJsonResposta($texto);
    if (!$dados) {
        appLogErro('analise_ia_externa_json', ['resposta' => (string)$resposta]);
        return [false, 'A IA externa respondeu, mas não retornou JSON válido para montar o parecer.', null];
    }

    $normalizado = analiseIaExternaNormalizarResposta($dados, [
        'modelo' => (string)$config['model'],
        'provider_label' => (string)$config['provider_label'],
    ]);

    appLogEvento('analise_ia_externa_gerada', [
        'modelo' => (string)$config['model'],
        'status' => $normalizado['status'],
        'confianca' => $normalizado['confianca'],
    ]);

    return [true, 'Análise externa gerada com sucesso.', $normalizado];
}
