<?php

function analiseSinistroNormalizarTexto(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = [
        'Ã¡' => 'a', 'Ã ' => 'a', 'Ã£' => 'a', 'Ã¢' => 'a',
        'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e',
        'Ã­' => 'i', 'Ã¬' => 'i', 'Ã®' => 'i',
        'Ã³' => 'o', 'Ã²' => 'o', 'Ãµ' => 'o', 'Ã´' => 'o',
        'Ãº' => 'u', 'Ã¹' => 'u', 'Ã»' => 'u',
        'Ã§' => 'c',
    ];

    return strtr($texto, $mapa);
}

function analiseSinistroEncontrarPadroes(string $texto, array $regras): array
{
    $encontrados = [];

    foreach ($regras as $rotulo => $padroes) {
        foreach ($padroes as $padrao) {
            if (str_contains($texto, $padrao)) {
                $encontrados[] = $rotulo;
                break;
            }
        }
    }

    return $encontrados;
}

function analiseSinistroReferenciasCtb(): array
{
    return [
        [
            'artigo' => 'CTB art. 34',
            'titulo' => 'Manobra sem perigo',
            'resumo' => 'Antes de executar manobra, o condutor deve verificar se consegue fazê-la sem risco para quem segue, precede ou cruza a trajetória.',
            'gatilhos' => ['mudou de faixa', 'trocou de faixa', 'entrou na minha faixa', 'fechou a moto', 'conversao', 'virou', 'retorno', 'retornou'],
        ],
        [
            'artigo' => 'CTB art. 35',
            'titulo' => 'Sinalização de deslocamento lateral',
            'resumo' => 'Mudanças laterais, conversões e retornos exigem sinalização clara e antecedente suficiente.',
            'gatilhos' => ['sem sinalizar', 'nao sinalizou', 'mudou de faixa', 'trocou de faixa', 'conversao', 'retorno'],
        ],
        [
            'artigo' => 'CTB art. 36',
            'titulo' => 'Ingresso em via',
            'resumo' => 'Quem ingressa na via vindo de lote, garagem, posto ou acesso similar deve ceder passagem aos veículos e pedestres que já transitam nela.',
            'gatilhos' => ['saindo da garagem', 'saindo do posto', 'saiu do estacionamento', 'ingressou na via', 'veio de um lote', 'saiu do imovel'],
        ],
        [
            'artigo' => 'CTB art. 38',
            'titulo' => 'Mudança de direção e conversão',
            'resumo' => 'Ao entrar à direita ou à esquerda, o condutor deve posicionar corretamente o veículo e ceder passagem conforme a dinâmica da manobra.',
            'gatilhos' => ['conversao a direita', 'conversao a esquerda', 'virou a direita', 'virou a esquerda', 'dobrou', 'mudanca de direcao'],
        ],
        [
            'artigo' => 'CTB art. 43',
            'titulo' => 'Velocidade e redução segura',
            'resumo' => 'A velocidade e sua redução precisam respeitar as condições da via, clima, fluxo e segurança dos demais usuários.',
            'gatilhos' => ['freou bruscamente', 'alta velocidade', 'correndo', 'rapido demais', 'nao conseguiu frear', 'reduziu bruscamente'],
        ],
        [
            'artigo' => 'CTB art. 44',
            'titulo' => 'Prudência em cruzamentos',
            'resumo' => 'Ao se aproximar de cruzamento, o condutor deve adotar prudência especial e velocidade moderada.',
            'gatilhos' => ['cruzamento', 'esquina', 'rotatoria', 'intersecao', 'entroncamento'],
        ],
        [
            'artigo' => 'CTB art. 45',
            'titulo' => 'Obstrução de interseção',
            'resumo' => 'Mesmo com sinal favorável, o condutor não deve avançar em interseção se houver risco de ficar parado obstruindo o cruzamento.',
            'gatilhos' => ['fechou o cruzamento', 'ficou parado no cruzamento', 'obstruiu a passagem', 'travou a intersecao'],
        ],
        [
            'artigo' => 'CTB art. 29, III',
            'titulo' => 'Preferência de passagem em fluxos que se cruzam',
            'resumo' => 'A preferência em cruzamentos depende da sinalização, da rodovia, da rotatória ou, na falta disso, da regra de prioridade aplicável ao caso.',
            'gatilhos' => ['preferencial', 'preferencia', 'rotatoria', 'veio pela direita', 'nao respeitou a preferencial'],
        ],
        [
            'artigo' => 'CTB art. 29, IX e X',
            'titulo' => 'Ultrapassagem com cautela',
            'resumo' => 'Ultrapassagens exigem observância da faixa correta e verificação prévia das condições de segurança.',
            'gatilhos' => ['ultrapassagem', 'ultrapassou', 'passou pela esquerda', 'passou pela direita'],
        ],
    ];
}

function analiseSinistroSugerirFundamentosCtb(string $textoAnalise): array
{
    $fundamentos = [];

    foreach (analiseSinistroReferenciasCtb() as $referencia) {
        foreach ($referencia['gatilhos'] as $gatilho) {
            if (str_contains($textoAnalise, $gatilho)) {
                $fundamentos[] = [
                    'artigo' => (string)$referencia['artigo'],
                    'titulo' => (string)$referencia['titulo'],
                    'resumo' => (string)$referencia['resumo'],
                    'gatilho' => (string)$gatilho,
                ];
                break;
            }
        }
    }

    return $fundamentos;
}

function analiseSinistroMontarEvidenciasMultimodais(array $arquivosPorCategoria, string $textoBoletim, string $textoImagem, string $textoVideo): array
{
    $evidencias = [];

    if (trim($textoBoletim) !== '') {
        $evidencias[] = [
            'fonte' => 'BO',
            'status' => 'texto_extraido',
            'resumo' => mb_substr(trim($textoBoletim), 0, 400, 'UTF-8'),
        ];
    } elseif (!empty($arquivosPorCategoria['tem_bo'])) {
        $evidencias[] = [
            'fonte' => 'BO',
            'status' => 'anexo_sem_leitura',
            'resumo' => trim((string)($arquivosPorCategoria['erro_boletim'] ?? 'BO localizado, mas sem leitura automatizada.')),
        ];
    }

    if (trim($textoImagem) !== '') {
        $evidencias[] = [
            'fonte' => 'Imagem',
            'status' => 'ocr_extraido',
            'resumo' => mb_substr(trim($textoImagem), 0, 400, 'UTF-8'),
        ];
    } elseif ((bool)($arquivosPorCategoria['leitura_imagem_ok'] ?? false) === false && trim((string)($arquivosPorCategoria['erro_imagem'] ?? '')) !== '') {
        $evidencias[] = [
            'fonte' => 'Imagem',
            'status' => 'sem_leitura',
            'resumo' => trim((string)$arquivosPorCategoria['erro_imagem']),
        ];
    }

    if (trim($textoVideo) !== '') {
        $evidencias[] = [
            'fonte' => 'Vídeo',
            'status' => 'resumo_extraido',
            'resumo' => mb_substr(trim($textoVideo), 0, 400, 'UTF-8'),
        ];
    } elseif (trim((string)($arquivosPorCategoria['erro_video'] ?? '')) !== '') {
        $evidencias[] = [
            'fonte' => 'Vídeo',
            'status' => 'pipeline_pendente',
            'resumo' => trim((string)$arquivosPorCategoria['erro_video']),
        ];
    }

    $metadadosImagem = is_array($arquivosPorCategoria['metadados_imagem'] ?? null) ? $arquivosPorCategoria['metadados_imagem'] : [];
    if ($metadadosImagem) {
        $evidencias[] = [
            'fonte' => 'Metadados de imagem',
            'status' => 'coletados',
            'resumo' => implode(' | ', array_slice($metadadosImagem, 0, 4)),
        ];
    }

    $metadadosVideo = is_array($arquivosPorCategoria['metadados_video'] ?? null) ? $arquivosPorCategoria['metadados_video'] : [];
    if ($metadadosVideo) {
        $evidencias[] = [
            'fonte' => 'Metadados de vídeo',
            'status' => 'coletados',
            'resumo' => implode(' | ', array_slice($metadadosVideo, 0, 4)),
        ];
    }

    return $evidencias;
}

function analiseSinistroMontarRelatorioAuditoria(array $sinistro, array $analise): string
{
    $linhas = [];
    $linhas[] = 'Relatório assistido para auditoria do sinistro ' . (string)($sinistro['numero_registro'] ?? $sinistro['id'] ?? '');
    $linhas[] = 'Conclusão preliminar: ' . (string)($analise['conclusao'] ?? 'Indeterminada') . '.';
    $linhas[] = 'Resumo executivo: ' . (string)($analise['resumo'] ?? '');

    if (!empty($analise['fundamentos_ctb'])) {
        $fundamentos = array_map(
            static fn(array $item): string => (string)$item['artigo'] . ' - ' . (string)$item['titulo'],
            array_slice((array)$analise['fundamentos_ctb'], 0, 4)
        );
        $linhas[] = 'Possíveis fundamentos do CTB a conferir na auditoria: ' . implode('; ', $fundamentos) . '.';
    }

    if (!empty($analise['evidencias_multimodais'])) {
        $evidencias = array_map(
            static fn(array $item): string => (string)$item['fonte'] . ': ' . (string)$item['status'],
            array_slice((array)$analise['evidencias_multimodais'], 0, 5)
        );
        $linhas[] = 'Fontes avaliadas: ' . implode('; ', $evidencias) . '.';
    }

    if (!empty($analise['pendencias'])) {
        $linhas[] = 'Pendências para auditoria: ' . implode(' | ', array_slice((array)$analise['pendencias'], 0, 5)) . '.';
    }

    $linhas[] = 'Observação: trata-se de apoio assistido, dependente de conferência humana, documental e eventualmente jurídica.';

    return implode("\n", $linhas);
}

function gerarAnaliseAssistidaSinistro(array $sinistro, array $arquivosPorCategoria = []): array
{
    $relato = trim((string)($sinistro['relato'] ?? ''));
    $tipoOcorrencia = trim((string)($sinistro['tipo_ocorrencia'] ?? ''));
    $situacaoVia = trim((string)($sinistro['situacao_via'] ?? ''));
    $logradouro = trim((string)($sinistro['logradouro'] ?? ''));
    $bairro = trim((string)($sinistro['bairro'] ?? ''));
    $cidade = trim((string)($sinistro['cidade'] ?? ''));
    $estado = trim((string)($sinistro['estado'] ?? ''));
    $sentidoVia = trim((string)($sinistro['sentido_via'] ?? ''));
    $textoBoletim = trim((string)($arquivosPorCategoria['texto_boletim'] ?? ''));
    $textoImagem = trim((string)($arquivosPorCategoria['texto_imagem'] ?? ''));
    $textoVideo = trim((string)($arquivosPorCategoria['texto_video'] ?? ''));

    $textoAnalise = analiseSinistroNormalizarTexto(
        implode(' | ', array_filter([$relato, $tipoOcorrencia, $situacaoVia, $logradouro, $bairro, $cidade, $estado, $sentidoVia, $textoBoletim, $textoImagem, $textoVideo]))
    );

    $indiciosTerceiro = [
        'terceiro avançou preferencial' => ['invadiu a preferencial', 'avancou a preferencial', 'nao respeitou a preferencial'],
        'terceiro avançou sinal' => ['avancou o sinal', 'furou o sinal', 'nao respeitou o sinal'],
        'terceiro bateu na traseira' => ['bateu na traseira', 'colidiu na traseira', 'atingiu a traseira'],
        'terceiro mudou de faixa sem segurança' => ['mudou de faixa', 'trocou de faixa', 'entrou na minha faixa', 'fechou a moto'],
        'terceiro abriu porta ou fez conversão brusca' => ['abriu a porta', 'conversao brusca', 'entrou de uma vez', 'virou sem sinalizar'],
        'motoca estava parada ou em fluxo regular' => ['eu estava parado', 'moto parada', 'aguardando no semaforo', 'trafegava normalmente'],
    ];

    $indiciosMotoca = [
        'motoca perdeu controle' => ['perdi o controle', 'derrapei sozinho', 'queda sozinho', 'cai sozinho'],
        'motoca bateu na traseira' => ['bati na traseira', 'colidi na traseira', 'atingi a traseira'],
        'motoca avançou sinal ou preferencial' => ['avancei o sinal', 'avancei a preferencial', 'nao respeitei a preferencial'],
        'motoca mudou de faixa sem segurança' => ['mudei de faixa', 'troquei de faixa', 'entrei na faixa'],
        'motoca admite falta de atenção' => ['nao vi o veiculo', 'nao percebi o veiculo', 'me distrai'],
    ];

    $fatoresCompartilhados = [
        'condições adversas' => ['chuva', 'pista molhada', 'oleo na pista', 'baixa visibilidade', 'neblina'],
        'via crítica' => ['buraco', 'semaforo apagado', 'obra na pista', 'desvio'],
    ];

    $achadosTerceiro = analiseSinistroEncontrarPadroes($textoAnalise, $indiciosTerceiro);
    $achadosMotoca = analiseSinistroEncontrarPadroes($textoAnalise, $indiciosMotoca);
    $achadosCompartilhados = analiseSinistroEncontrarPadroes($textoAnalise, $fatoresCompartilhados);
    $fundamentosCtb = analiseSinistroSugerirFundamentosCtb($textoAnalise);

    $pontuacaoTerceiro = count($achadosTerceiro) * 2;
    $pontuacaoMotoca = count($achadosMotoca) * 2;
    $pontuacaoCompartilhada = count($achadosCompartilhados);

    $fotos = (int)($arquivosPorCategoria['fotos'] ?? 0);
    $fotosCamera = (int)($arquivosPorCategoria['fotos_camera'] ?? 0);
    $fotos360 = (int)($arquivosPorCategoria['fotos_360'] ?? 0);
    $anexosPdf = (int)($arquivosPorCategoria['anexos_pdf'] ?? 0);
    $boletimOcorrencia = (bool)($arquivosPorCategoria['tem_bo'] ?? false);
    $temCnh = (bool)($arquivosPorCategoria['tem_cnh'] ?? false);
    $temCrlv = (bool)($arquivosPorCategoria['tem_crlv'] ?? false);
    $leituraBoOk = (bool)($arquivosPorCategoria['leitura_bo_ok'] ?? false);
    $origemBoletim = trim((string)($arquivosPorCategoria['origem_boletim'] ?? ''));
    $erroBoletim = trim((string)($arquivosPorCategoria['erro_boletim'] ?? ''));
    $leituraImagemOk = (bool)($arquivosPorCategoria['leitura_imagem_ok'] ?? false);
    $leituraVideoOk = (bool)($arquivosPorCategoria['leitura_video_ok'] ?? false);
    $erroImagem = trim((string)($arquivosPorCategoria['erro_imagem'] ?? ''));
    $erroVideo = trim((string)($arquivosPorCategoria['erro_video'] ?? ''));
    $metadadosImagem = is_array($arquivosPorCategoria['metadados_imagem'] ?? null) ? $arquivosPorCategoria['metadados_imagem'] : [];
    $metadadosVideo = is_array($arquivosPorCategoria['metadados_video'] ?? null) ? $arquivosPorCategoria['metadados_video'] : [];

    $baseConfianca = 30;
    $baseConfianca += min(20, $fotos * 2);
    $baseConfianca += min(10, $fotosCamera * 3);
    $baseConfianca += min(10, $fotos360 * 2);
    $baseConfianca += min(10, $anexosPdf * 2);
    $baseConfianca += $boletimOcorrencia ? 8 : 0;
    $baseConfianca += $leituraBoOk ? 10 : 0;
    $baseConfianca += $leituraImagemOk ? 6 : 0;
    $baseConfianca += $leituraVideoOk ? 6 : 0;
    $baseConfianca += min(10, count($fundamentosCtb) * 2);
    $baseConfianca += $temCnh ? 4 : 0;
    $baseConfianca += $temCrlv ? 4 : 0;
    $baseConfianca = max(15, min(94, $baseConfianca));

    $conclusao = 'Indeterminada';
    $classe = 'neutra';
    $resumo = 'Os elementos atuais ainda não permitem sugerir uma culpabilidade predominante com segurança.';

    if ($pontuacaoTerceiro >= $pontuacaoMotoca + 2) {
        $conclusao = 'Há indícios de culpa predominante do terceiro';
        $classe = 'terceiro';
        $resumo = 'Relato, anexos e marcadores textuais sugerem, em tese, conduta do terceiro como principal fator do evento.';
    } elseif ($pontuacaoMotoca >= $pontuacaoTerceiro + 2) {
        $conclusao = 'Há indícios de culpa predominante do condutor da Motoca';
        $classe = 'motoca';
        $resumo = 'Os elementos disponíveis sugerem, em tese, conduta do condutor da Motoca como fator predominante do evento.';
    } elseif (($pontuacaoTerceiro > 0 || $pontuacaoMotoca > 0 || $pontuacaoCompartilhada > 0) && abs($pontuacaoTerceiro - $pontuacaoMotoca) <= 1) {
        $conclusao = 'Há indícios de culpa concorrente ou compartilhada';
        $classe = 'compartilhada';
        $resumo = 'Há sinais de contribuição de mais de uma parte ou de necessidade de prova complementar para auditoria.';
    }

    $evidencias = [];
    foreach ($achadosTerceiro as $item) {
        $evidencias[] = 'Indicador a favor de responsabilidade do terceiro: ' . $item . '.';
    }
    foreach ($achadosMotoca as $item) {
        $evidencias[] = 'Indicador a favor de responsabilidade do condutor da Motoca: ' . $item . '.';
    }
    foreach ($achadosCompartilhados as $item) {
        $evidencias[] = 'Fator de contexto que exige cautela: ' . $item . '.';
    }
    foreach ($fundamentosCtb as $fundamento) {
        $evidencias[] = 'Possível enquadramento técnico: ' . $fundamento['artigo'] . ' - ' . $fundamento['titulo'] . '.';
    }

    if (!$evidencias) {
        $evidencias[] = 'Não foram encontrados marcadores textuais fortes para apontar uma versão predominante.';
    }

    $pendencias = [];
    if (!$boletimOcorrencia) {
        $pendencias[] = 'Sem boletim de ocorrência identificado entre os anexos.';
    }
    if ($fotos + $fotosCamera + $fotos360 < 3) {
        $pendencias[] = 'Baixa quantidade de imagens para sustentar a dinâmica do sinistro.';
    }
    if ($logradouro === '' || $cidade === '' || $estado === '') {
        $pendencias[] = 'Local do ocorrido incompleto para contextualização mais segura.';
    }
    if (!$temCnh) {
        $pendencias[] = 'CNH não identificada entre os documentos anexos.';
    }
    if (!$temCrlv) {
        $pendencias[] = 'CRLV não identificado entre os documentos anexos.';
    }
    if ($boletimOcorrencia && !$leituraBoOk) {
        $pendencias[] = 'BO localizado, mas sem leitura automatizada do conteúdo.';
    }
    if (!$leituraImagemOk && $erroImagem !== '') {
        $pendencias[] = $erroImagem;
    }
    if (!$leituraVideoOk && $erroVideo !== '') {
        $pendencias[] = $erroVideo;
    }
    if (!$fundamentosCtb) {
        $pendencias[] = 'Nenhum fundamento do CTB foi sugerido automaticamente; a auditoria deve revisar manualmente a dinâmica narrada.';
    }

    $evidenciasMultimodais = analiseSinistroMontarEvidenciasMultimodais(
        array_merge($arquivosPorCategoria, [
            'metadados_imagem' => $metadadosImagem,
            'metadados_video' => $metadadosVideo,
        ]),
        $textoBoletim,
        $textoImagem,
        $textoVideo
    );

    $resultado = [
        'conclusao' => $conclusao,
        'classe' => $classe,
        'resumo' => $resumo,
        'confianca' => $baseConfianca,
        'evidencias' => $evidencias,
        'pendencias' => $pendencias,
        'fundamentos_ctb' => $fundamentosCtb,
        'evidencias_multimodais' => $evidenciasMultimodais,
        'boletim_lido' => $leituraBoOk,
        'origem_boletim' => $origemBoletim,
        'erro_boletim' => $erroBoletim,
        'resumo_boletim' => $textoBoletim !== '' ? mb_substr($textoBoletim, 0, 700, 'UTF-8') : '',
        'imagem_lida' => $leituraImagemOk,
        'video_lido' => $leituraVideoOk,
        'erro_imagem' => $erroImagem,
        'erro_video' => $erroVideo,
        'resumo_imagem' => $textoImagem !== '' ? mb_substr($textoImagem, 0, 700, 'UTF-8') : '',
        'resumo_video' => $textoVideo !== '' ? mb_substr($textoVideo, 0, 700, 'UTF-8') : '',
        'metadados_imagem' => $metadadosImagem,
        'metadados_video' => $metadadosVideo,
        'disclaimer' => 'Análise assistida experimental. Serve apenas como apoio inicial interno e não substitui avaliação humana, jurídica, pericial ou de auditoria.',
    ];

    $resultado['relatorio_auditoria'] = analiseSinistroMontarRelatorioAuditoria($sinistro, $resultado);

    return $resultado;
}
