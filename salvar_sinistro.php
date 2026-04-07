<?php
session_start();
require "config/db.php";
require "config/admin_auditoria.php";
require "config/app_log.php";
require "config/placas_motoca.php";
require "config/sinistro_registro.php";
require "config/sinistro_upload.php";
require "config/sinistro_vinculo.php";
require "config/email_transport.php";

const PRAZO_RETORNO_DIAS = 30;
const VALIDACAO_EMAIL_ATIVA = false;

/* GARANTE POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

function redirecionarComErroFormulario(string $mensagem, ?string $tipoFormulario = null): void
{
    $_SESSION['sinistro_form_erro'] = $mensagem;

    $destino = match ($tipoFormulario) {
        'terceiro' => 'terceiro.php',
        'locatario' => 'locatario.php',
        default => 'index.php',
    };

    header("Location: " . $destino);
    exit;
}

/* DADOS */
$tipo_formulario = $_POST['tipo_formulario'] ?? null;
$nome         = trim($_POST['nome'] ?? '');
$telefone     = trim($_POST['telefone'] ?? '');
$email        = trim($_POST['email'] ?? '');
$placa_motoca = strtoupper(trim($_POST['placa_motoca'] ?? ''));
$placa_motoca_validacao = strtoupper(trim($_POST['placa_motoca_validacao'] ?? ''));
$placa_terceiro = strtoupper(trim($_POST['placa_terceiro'] ?? ''));
$placa_terceiro_seguradora = strtoupper(trim($_POST['placa_terceiro_seguradora'] ?? ''));

$data_hora    = $_POST['data_hora'] ?? '';
$cepLocal     = trim((string)($_POST['cep_local'] ?? ''));
$logradouroLocal = trim((string)($_POST['logradouro_local'] ?? ''));
$bairroLocal  = trim((string)($_POST['bairro_local'] ?? ''));
$numeroLocal  = trim((string)($_POST['numero_local'] ?? ''));
$sentidoViaLocal = trim((string)($_POST['sentido_via_local'] ?? ''));
$pontoReferenciaLocal = trim((string)($_POST['ponto_referencia_local'] ?? ''));
$assinaturaCondutor = trim((string)($_POST['assinatura_condutor'] ?? ''));
$cidadeInput  = trim((string)($_POST['cidade'] ?? ''));
$estadoInput  = trim((string)($_POST['estado'] ?? ''));
$baseLocal    = trim((string)($_POST['base_local'] ?? ''));
$relato       = trim($_POST['relato'] ?? '');

$tipo_ocorrencia = $_POST['tipo_ocorrencia'] ?? '';
$situacao_via    = $_POST['situacao_via'] ?? '';
$houve_vitimas   = $_POST['houve_vitimas'] ?? 'nao';
$qtd_vitimas     = $_POST['qtd_vitimas'] ?? null;
$informacoesVitima = trim((string)($_POST['informacoes_vitima'] ?? ''));
$possuiMidiasAcidente = trim((string)($_POST['possui_midias_acidente'] ?? ''));
$realizouBo = trim((string)($_POST['realizou_bo'] ?? ''));
$jaRealizouOrcamento = trim((string)($_POST['ja_realizou_orcamento'] ?? ''));
$jaHouveConserto = trim((string)($_POST['ja_houve_conserto'] ?? ''));
$perfil          = trim((string)($_POST['perfil'] ?? ''));
$tipo_termo_curatela = trim((string)($_POST['tipo_termo_curatela'] ?? ''));
$canalCodigoValidacao = trim((string)($_POST['canal_codigo_validacao'] ?? ''));
$destinoCodigoValidacao = trim((string)($_POST['destino_codigo_validacao'] ?? ''));
$codigoValidacaoDigitado = preg_replace('/\D+/', '', (string)($_POST['codigo_validacao_digitado'] ?? ''));
$seguradoraNome = trim((string)($_POST['seguradora_nome'] ?? ''));
$seguradoraRepresentando = trim((string)($_POST['seguradora_representando'] ?? ''));
$motivoContatoSeguradora = trim((string)($_POST['motivo_contato_seguradora'] ?? ''));
$responsavel = ($tipo_formulario === 'terceiro' && $perfil !== '') ? $perfil : (string)$tipo_formulario;

if ($placa_motoca === '' && $placa_motoca_validacao !== '') {
    $placa_motoca = $placa_motoca_validacao;
}

if ($placa_terceiro === '' && $placa_terceiro_seguradora !== '') {
    $placa_terceiro = $placa_terceiro_seguradora;
}

$ehFluxoSeguradora = $tipo_formulario === 'terceiro' && $perfil === 'seguradora_associacao_coperativa';

if ($ehFluxoSeguradora) {
    if ($nome === '' && $seguradoraNome !== '') {
        $nome = $seguradoraNome;
    }
    if ($relato === '' && $motivoContatoSeguradora !== '') {
        $relato = $motivoContatoSeguradora;
    }
    if ($data_hora === '') {
        $data_hora = date('Y-m-d\TH:i');
    }
    if ($logradouroLocal === '') {
        $logradouroLocal = 'Contato registrado por seguradora/associação/cooperativa';
    }
}

if ($canalCodigoValidacao === 'email' && $destinoCodigoValidacao !== '') {
    $email = $destinoCodigoValidacao;
}

if ($tipo_formulario === 'terceiro' && $jaHouveConserto === 'sim') {
    $jaRealizouOrcamento = '';
}

/* VALIDAÇÕES */
if (!$ehFluxoSeguradora && (
    empty($nome) || empty($telefone) || empty($email) ||
    empty($placa_motoca) || empty($data_hora) || empty($logradouroLocal)
)) {
    redirecionarComErroFormulario("Preencha os campos obrigatórios para continuar.", $tipo_formulario);
}

if ($ehFluxoSeguradora && (
    empty($seguradoraNome) || empty($placa_motoca) || empty($placa_terceiro) ||
    empty($seguradoraRepresentando) || empty($motivoContatoSeguradora)
)) {
    redirecionarComErroFormulario("Preencha os campos obrigatórios do fluxo de seguradora/associação/cooperativa.", $tipo_formulario);
}

if (!placaMotocaValida($placa_motoca)) {
    redirecionarComErroFormulario("A placa informada não pertence à MOTOCA. Verifique e tente novamente.", $tipo_formulario);
}

$timestampSinistro = strtotime($data_hora);
if ($timestampSinistro === false) {
    redirecionarComErroFormulario("Informe uma data e horário válidos para o sinistro.", $tipo_formulario);
}

if ($timestampSinistro > time()) {
    redirecionarComErroFormulario("A data do sinistro não pode ser futura.", $tipo_formulario);
}

$minimoRelato = ($tipo_formulario === 'terceiro') ? 150 : 80;
$tamanhoRelato = function_exists('mb_strlen')
    ? mb_strlen($relato, 'UTF-8')
    : strlen($relato);
if (!$ehFluxoSeguradora && $tamanhoRelato < $minimoRelato) {
    redirecionarComErroFormulario("O relato deve conter no mínimo {$minimoRelato} caracteres.", $tipo_formulario);
}

if (!$tipo_formulario || !in_array($tipo_formulario, ['locatario', 'terceiro'], true)) {
    redirecionarComErroFormulario("Tipo de formulário não identificado.", $tipo_formulario);
}

if (empty($_POST['aceite'])) {
    redirecionarComErroFormulario("É necessário aceitar a declaração para enviar o formulário.", $tipo_formulario);
}

if ($assinaturaCondutor === '' || strpos($assinaturaCondutor, 'data:image/png;base64,') !== 0) {
    redirecionarComErroFormulario("A assinatura do condutor não foi informada.", $tipo_formulario);
}

if ($numeroLocal !== '') {
    $sufixoNumero = 'Nº aprox.: ' . $numeroLocal;

    if ($logradouroLocal === '') {
        $logradouroLocal = $sufixoNumero;
    } elseif (stripos($logradouroLocal, $numeroLocal) === false) {
        $logradouroLocal .= ' - ' . $sufixoNumero;
    }
}

if (
    $tipo_formulario === 'terceiro' &&
    in_array($perfil, ['nenhuma_das_opcoes_anteriores'], true)
) {
    redirecionarComErroFormulario("Para prosseguir, é necessário se enquadrar em um dos perfis permitidos.", $tipo_formulario);
}

if ($tipo_formulario === 'terceiro' && $perfil === 'procurador') {
    $arquivoProcuracao = $_FILES['documento_procuracao']['name'] ?? '';
    $arquivoDocumentoFoto = $_FILES['documento_oficial_foto']['name'] ?? '';
    $arquivoDocumentoProcurador = $_FILES['documento_oficial_procurador']['name'] ?? '';
    if ($arquivoProcuracao === '' || $arquivoDocumentoFoto === '' || $arquivoDocumentoProcurador === '') {
        redirecionarComErroFormulario("Para perfil Procurador do proprietário, anexe a procuração, o documento oficial com foto do proprietário e o documento oficial com foto do procurador.", $tipo_formulario);
    }
}

if ($tipo_formulario === 'terceiro' && $perfil === 'curador_tutela') {
    $arquivoLaudoCuratela = $_FILES['documento_laudo_curatela']['name'] ?? '';
    $arquivoTermoCuratela = $_FILES['documento_termo_curatela']['name'] ?? '';
    if ($tipo_termo_curatela === '') {
        redirecionarComErroFormulario("Selecione o tipo de termo legal para Curador/Tutor.", $tipo_formulario);
    }
    if ($arquivoLaudoCuratela === '' || $arquivoTermoCuratela === '') {
        redirecionarComErroFormulario("Para perfil Curador/Tutor, anexe o laudo e o termo legal.", $tipo_formulario);
    }
}

if ($houve_vitimas === 'sim' && $informacoesVitima === '') {
    redirecionarComErroFormulario("Informe os dados da vítima para continuar.", $tipo_formulario);
}

if ($possuiMidiasAcidente === 'sim') {
    $midiasLocais = $_FILES['fotos_local']['name'] ?? [];
    $midiasTerceiro = $_FILES['fotos']['name'] ?? [];
    $temMidia = false;

    if (is_array($midiasLocais) && array_filter($midiasLocais)) {
        $temMidia = true;
    }
    if (is_array($midiasTerceiro) && array_filter($midiasTerceiro)) {
        $temMidia = true;
    }

    if (!$temMidia) {
        redirecionarComErroFormulario("Informe ao menos uma foto ou vídeo do acidente para continuar.", $tipo_formulario);
    }
}

if ($tipo_formulario === 'locatario' && $realizouBo === 'sim' && empty($_FILES['bo']['name'])) {
    redirecionarComErroFormulario("Anexe o arquivo do BO para continuar.", $tipo_formulario);
}

if ($tipo_formulario === 'terceiro' && $jaHouveConserto === 'nao' && $jaRealizouOrcamento === '') {
    redirecionarComErroFormulario("Informe se já realizou orçamento para continuar.", $tipo_formulario);
}

if ($tipo_formulario === 'terceiro' && $jaHouveConserto === 'nao' && $jaRealizouOrcamento === 'sim') {
    $orcamentos = [
        $_FILES['orcamento_1']['name'] ?? '',
        $_FILES['orcamento_2']['name'] ?? '',
        $_FILES['orcamento_3']['name'] ?? '',
    ];

    if (count(array_filter($orcamentos, static fn($valor) => (string)$valor !== '')) === 0) {
        redirecionarComErroFormulario("Anexe ao menos um orçamento para continuar.", $tipo_formulario);
    }
}

if ($tipo_formulario === 'terceiro' && $jaHouveConserto === 'sim' && empty($_FILES['comprovante_conserto']['name'])) {
    redirecionarComErroFormulario("Anexe o comprovante ou nota fiscal do conserto para continuar.", $tipo_formulario);
}

if ($ehFluxoSeguradora && empty($_FILES['documento_representacao_seguradora']['name'])) {
    redirecionarComErroFormulario("Anexe o documento que comprove a representação para continuar.", $tipo_formulario);
}

if ($ehFluxoSeguradora && isset($_FILES['outros_documentos_seguradora']['name']) && is_array($_FILES['outros_documentos_seguradora']['name'])) {
    $quantidadeOutrosDocumentos = count(array_filter(
        $_FILES['outros_documentos_seguradora']['name'],
        static fn($valor) => trim((string)$valor) !== ''
    ));
    if ($quantidadeOutrosDocumentos > 10) {
        redirecionarComErroFormulario("Envie no máximo 10 outros documentos no fluxo de seguradora/associação/cooperativa.", $tipo_formulario);
    }
}

$codigoValidacao = '';

if (VALIDACAO_EMAIL_ATIVA) {
    if ($canalCodigoValidacao !== 'email') {
        redirecionarComErroFormulario("O código de validação está disponível somente por e-mail.", $tipo_formulario);
    }

    if ($destinoCodigoValidacao === '') {
        redirecionarComErroFormulario("Informe o destino para receber o código de validação.", $tipo_formulario);
    }

    if (!filter_var($destinoCodigoValidacao, FILTER_VALIDATE_EMAIL)) {
        redirecionarComErroFormulario("Informe um e-mail válido para receber o código.", $tipo_formulario);
    }

    $codigoValidacaoSessao = $_SESSION['sinistro_codigo_validacao'] ?? null;
    $destinoCodigoNormalizado = function_exists('mb_strtolower')
        ? mb_strtolower($destinoCodigoValidacao, 'UTF-8')
        : strtolower($destinoCodigoValidacao);

    if ($codigoValidacaoDigitado === '') {
        redirecionarComErroFormulario("Informe o código recebido por e-mail para concluir o envio.", $tipo_formulario);
    }

    if (!is_array($codigoValidacaoSessao)) {
        redirecionarComErroFormulario("Envie o código para o seu e-mail antes de concluir o formulário.", $tipo_formulario);
    }

    if (($codigoValidacaoSessao['tipo_formulario'] ?? '') !== $tipo_formulario) {
        redirecionarComErroFormulario("O código informado não corresponde a este formulário.", $tipo_formulario);
    }

    if (($codigoValidacaoSessao['canal'] ?? '') !== $canalCodigoValidacao) {
        redirecionarComErroFormulario("O código informado não corresponde ao canal selecionado.", $tipo_formulario);
    }

    if (($codigoValidacaoSessao['destino'] ?? '') !== $destinoCodigoNormalizado) {
        redirecionarComErroFormulario("O código informado não corresponde ao destino selecionado.", $tipo_formulario);
    }

    if ((int)($codigoValidacaoSessao['expira_em'] ?? 0) < time()) {
        unset($_SESSION['sinistro_codigo_validacao']);
        redirecionarComErroFormulario("O código informado expirou. Solicite um novo código por e-mail.", $tipo_formulario);
    }

    if (($codigoValidacaoSessao['codigo'] ?? '') !== $codigoValidacaoDigitado) {
        redirecionarComErroFormulario("O código informado é inválido. Confira o e-mail e tente novamente.", $tipo_formulario);
    }

    $codigoValidacao = (string)$codigoValidacaoSessao['codigo'];
} else {
    $canalCodigoValidacao = '';
    $destinoCodigoValidacao = '';
    $codigoValidacaoDigitado = '';
    unset($_SESSION['sinistro_codigo_validacao']);
}


function carregarConfigEmail(): array {
    $padrao = [
        'transport' => 'smtp',
        'from_email' => 'no-reply@motoca.local',
        'from_name' => 'MOTOCA',
        'reply_to' => 'suporte@motoca.local',
        'smtp' => [
            'host' => 'localhost',
            'port' => 587,
            'secure' => 'tls', // tls | ssl | none
            'username' => '',
            'password' => '',
            'auth' => true,
            'timeout' => 20,
            'helo' => 'localhost',
        ],
    ];

    $arquivo = __DIR__ . '/config/email.php';
    if (!is_file($arquivo)) {
        return $padrao;
    }

    $config = require $arquivo;
    if (!is_array($config)) {
        return $padrao;
    }

    $merged = array_merge($padrao, $config);
    $merged['smtp'] = array_merge($padrao['smtp'], is_array($config['smtp'] ?? null) ? $config['smtp'] : []);

    // Permite sobrescrever via variáveis de ambiente sem gravar segredo no repositório.
    $merged['smtp']['host'] = getenv('MOTOCA_SMTP_HOST') ?: $merged['smtp']['host'];
    $merged['smtp']['port'] = (int)(getenv('MOTOCA_SMTP_PORT') ?: $merged['smtp']['port']);
    $merged['smtp']['secure'] = getenv('MOTOCA_SMTP_SECURE') ?: $merged['smtp']['secure'];
    $merged['smtp']['username'] = getenv('MOTOCA_SMTP_USER') ?: $merged['smtp']['username'];
    $merged['smtp']['password'] = getenv('MOTOCA_SMTP_PASS') ?: $merged['smtp']['password'];
    $merged['smtp']['helo'] = getenv('MOTOCA_SMTP_HELO') ?: $merged['smtp']['helo'];

    return $merged;
}

function registrarLogEmail(string $mensagem): void {
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
    $dir = __DIR__ . '/logs';
    $arquivo = $dir . '/email_smtp.log';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    @file_put_contents($arquivo, $linha, FILE_APPEND);
    error_log($mensagem);
}

function smtpLerResposta($socket): array {
    $resposta = '';
    while (!feof($socket)) {
        $linha = fgets($socket, 515);
        if ($linha === false) {
            break;
        }
        $resposta .= $linha;
        if (strlen($linha) >= 4 && $linha[3] === ' ') {
            break;
        }
    }

    $codigo = (int)substr($resposta, 0, 3);
    return [$codigo, trim($resposta)];
}

function smtpEnviarComando($socket, string $comando, array $codigosEsperados): array {
    fwrite($socket, $comando . "\r\n");
    [$codigo, $resposta] = smtpLerResposta($socket);
    if (!in_array($codigo, $codigosEsperados, true)) {
        return [false, $resposta];
    }
    return [true, $resposta];
}

function smtpEnviarEmail(array $config, string $destinatario, string $assunto, string $html): bool {
    $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
    $host = (string)($smtp['host'] ?? '');
    $port = (int)($smtp['port'] ?? 0);
    $secure = strtolower((string)($smtp['secure'] ?? 'none'));
    $username = (string)($smtp['username'] ?? '');
    $password = (string)($smtp['password'] ?? '');
    $auth = (bool)($smtp['auth'] ?? true);
    $timeout = (int)($smtp['timeout'] ?? 20);
    $helo = (string)($smtp['helo'] ?? 'localhost');

    if ($host === '' || $port <= 0) {
        registrarLogEmail("SMTP inválido: host/porta ausentes.");
        return false;
    }

    $fromName = str_replace(["\r", "\n"], '', (string)($config['from_name'] ?? 'MOTOCA'));
    $fromEmail = str_replace(["\r", "\n"], '', (string)($config['from_email'] ?? 'no-reply@motoca.local'));
    $replyTo = str_replace(["\r", "\n"], '', (string)($config['reply_to'] ?? $fromEmail));
    $toEmail = str_replace(["\r", "\n"], '', $destinatario);

    $prefixo = ($secure === 'ssl') ? 'ssl://' : '';
    $alvo = $prefixo . $host . ':' . $port;

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($alvo, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        registrarLogEmail("Falha conexão SMTP ({$alvo}): [{$errno}] {$errstr}");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    [$codigoInicial, $respostaInicial] = smtpLerResposta($socket);
    if ($codigoInicial !== 220) {
        fclose($socket);
        registrarLogEmail("SMTP sem saudação 220: {$respostaInicial}");
        return false;
    }

    [$okEhlo, $respEhlo] = smtpEnviarComando($socket, "EHLO {$helo}", [250]);
    if (!$okEhlo) {
        fclose($socket);
        registrarLogEmail("SMTP EHLO falhou: {$respEhlo}");
        return false;
    }

    if ($secure === 'tls') {
        [$okStartTls, $respStartTls] = smtpEnviarComando($socket, "STARTTLS", [220]);
        if (!$okStartTls) {
            fclose($socket);
            registrarLogEmail("SMTP STARTTLS falhou: {$respStartTls}");
            return false;
        }

        $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            fclose($socket);
            registrarLogEmail("SMTP não conseguiu habilitar TLS.");
            return false;
        }

        [$okEhloTls, $respEhloTls] = smtpEnviarComando($socket, "EHLO {$helo}", [250]);
        if (!$okEhloTls) {
            fclose($socket);
            registrarLogEmail("SMTP EHLO pós-TLS falhou: {$respEhloTls}");
            return false;
        }
    }

    if ($auth) {
        [$okAuth, $respAuth] = smtpEnviarComando($socket, "AUTH LOGIN", [334]);
        if (!$okAuth) {
            fclose($socket);
            registrarLogEmail("SMTP AUTH LOGIN falhou: {$respAuth}");
            return false;
        }

        [$okUser, $respUser] = smtpEnviarComando($socket, base64_encode($username), [334]);
        if (!$okUser) {
            fclose($socket);
            registrarLogEmail("SMTP usuário falhou: {$respUser}");
            return false;
        }

        [$okPass, $respPass] = smtpEnviarComando($socket, base64_encode($password), [235]);
        if (!$okPass) {
            fclose($socket);
            registrarLogEmail("SMTP senha falhou: {$respPass}");
            return false;
        }
    }

    [$okMailFrom, $respMailFrom] = smtpEnviarComando($socket, "MAIL FROM:<{$fromEmail}>", [250]);
    if (!$okMailFrom) {
        fclose($socket);
        registrarLogEmail("SMTP MAIL FROM falhou: {$respMailFrom}");
        return false;
    }

    [$okRcpt, $respRcpt] = smtpEnviarComando($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
    if (!$okRcpt) {
        fclose($socket);
        registrarLogEmail("SMTP RCPT TO falhou: {$respRcpt}");
        return false;
    }

    [$okData, $respData] = smtpEnviarComando($socket, "DATA", [354]);
    if (!$okData) {
        fclose($socket);
        registrarLogEmail("SMTP DATA falhou: {$respData}");
        return false;
    }

    $assuntoCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $headers = [];
    $headers[] = "Date: " . date(DATE_RFC2822);
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    $headers[] = "To: <{$toEmail}>";
    $headers[] = "Reply-To: {$replyTo}";
    $headers[] = "Subject: {$assuntoCodificado}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";

    // Dot-stuffing obrigatório no SMTP para linhas iniciadas com ponto.
    $corpo = preg_replace("/\r\n\./", "\r\n..", str_replace(["\r\n", "\r", "\n"], "\r\n", $html));
    $mensagem = implode("\r\n", $headers) . "\r\n\r\n" . $corpo . "\r\n.";

    fwrite($socket, $mensagem . "\r\n");
    [$codigoEnvio, $respEnvio] = smtpLerResposta($socket);
    if ($codigoEnvio !== 250) {
        fclose($socket);
        registrarLogEmail("SMTP envio falhou: {$respEnvio}");
        return false;
    }

    smtpEnviarComando($socket, "QUIT", [221]);
    fclose($socket);
    registrarLogEmail("SMTP envio OK para {$toEmail}");
    return true;
}

function enviarEmailConfirmacaoRegistro(
    string $destinatario,
    string $nome,
    string $numeroRegistro,
    string $placaMotoca,
    string $dataHoraSinistro,
    string $tipoFormulario,
    string $codigoValidacao = '',
    string $canalCodigo = ''
): array {
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Destinatário inválido.'];
    }

    $tipoLegivel = ($tipoFormulario === 'terceiro') ? 'terceiro' : 'locatário';
    $assunto = "Confirmacao de registro de sinistro - " . $numeroRegistro;

    if ($tipoFormulario === 'terceiro') {
        $mensagemHtml = montarHtmlComprovanteTerceiroEmail(
            $nome,
            $numeroRegistro,
            $placaMotoca,
            $dataHoraSinistro,
            PRAZO_RETORNO_DIAS
        );
    } else {
        $nomeSeguro = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $registroSeguro = htmlspecialchars($numeroRegistro, ENT_QUOTES, 'UTF-8');
        $placaSegura = htmlspecialchars($placaMotoca, ENT_QUOTES, 'UTF-8');
        $dataSegura = htmlspecialchars($dataHoraSinistro, ENT_QUOTES, 'UTF-8');

        $mensagemHtml = "
            <html>
            <body style=\"font-family: Arial, sans-serif; color: #111;\">
                <h2 style=\"margin-bottom: 8px;\">Registro de sinistro recebido</h2>
                <p>Olá, {$nomeSeguro}.</p>
                <p>Seu registro ({$tipoLegivel}) foi enviado com sucesso.</p>
                <p><strong>Número de registro:</strong> {$registroSeguro}</p>
                <p><strong>Placa MOTOCA:</strong> {$placaSegura}<br>
                   <strong>Data/Hora informada:</strong> {$dataSegura}</p>
                <p>Guarde este número para referência no atendimento.</p>
                <hr style=\"border:0;border-top:1px solid #ddd;\">
                <p style=\"font-size: 12px; color: #666;\">MOTOCA - Confirmação automática</p>
            </body>
            </html>
        ";
    }

    return emailTransportEnviarHtml($destinatario, $assunto, $mensagemHtml);
}

function formatarDataHoraComprovante(string $dataHora): string
{
    $timestamp = strtotime($dataHora);
    if ($timestamp === false) {
        return $dataHora;
    }

    return date('d/m/Y \a\s H:i', $timestamp);
}

function obterLogoWatermarkDataUri(): string
{
    static $dataUri = null;

    if ($dataUri !== null) {
        return $dataUri;
    }

    $arquivo = __DIR__ . '/assets/img/logo.png';
    if (!is_file($arquivo)) {
        $dataUri = '';
        return $dataUri;
    }

    $binario = @file_get_contents($arquivo);
    if ($binario === false || $binario === '') {
        $dataUri = '';
        return $dataUri;
    }

    $dataUri = 'data:image/png;base64,' . base64_encode($binario);
    return $dataUri;
}

function montarHtmlComprovanteTerceiroEmail(
    string $nome,
    string $numeroRegistro,
    string $placaMotoca,
    string $dataHoraSinistro,
    int $prazoRetornoDias,
    string $codigoValidacao = '',
    string $canalCodigo = ''
): string {
    $nomeSeguro = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $registroSeguro = htmlspecialchars($numeroRegistro, ENT_QUOTES, 'UTF-8');
    $placaSegura = htmlspecialchars($placaMotoca, ENT_QUOTES, 'UTF-8');
    $dataSegura = htmlspecialchars(formatarDataHoraComprovante($dataHoraSinistro), ENT_QUOTES, 'UTF-8');
    $logoDataUri = obterLogoWatermarkDataUri();
    $logoSegura = htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8');
    $watermarkHtml = $logoSegura !== ''
        ? '<img src="' . $logoSegura . '" alt="" style="position:absolute;top:50%;left:50%;width:300px;max-width:66%;transform:translate(-50%,-50%);opacity:.16;filter:grayscale(1) brightness(1.28);pointer-events:none;">'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Registro</title>
</head>
<body style="margin:0;padding:32px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:720px;margin:0 auto;background:linear-gradient(135deg,#111111 0%,#1b1b1b 100%);border-radius:28px;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.24);">
        <div style="position:relative;padding:36px 36px 28px;background:linear-gradient(135deg,#141414 0%,#232323 100%);">
            {$watermarkHtml}
            <div style="position:relative;z-index:1;">
                <div style="display:inline-block;padding:8px 14px;border-radius:999px;background:rgba(245,229,0,.14);border:1px solid rgba(245,229,0,.32);color:#f5e500;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
                    Comprovante de Registro
                </div>
                <h1 style="margin:18px 0 10px;font-size:30px;line-height:1.15;color:#ffffff;">Recebemos o seu aviso de sinistro</h1>
                <p style="margin:0;color:#d1d5db;font-size:16px;line-height:1.7;">
                    Olá, {$nomeSeguro}. Este é o comprovante do registro feito como terceiro. Guarde estas informações para acompanhar o atendimento.
                </p>
            </div>
        </div>
        <div style="padding:30px 36px 18px;background:#ffffff;">
            <div style="margin-bottom:22px;padding:22px;border-radius:22px;background:linear-gradient(135deg,#fffbd1 0%,#fff3a0 100%);border:1px solid #f0df69;">
                <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#5b5400;margin-bottom:10px;">Numero de registro</div>
                <div style="font-size:34px;line-height:1.1;font-weight:800;letter-spacing:.06em;color:#111111;">{$registroSeguro}</div>
            </div>
            <table role="presentation" style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:50%;padding:0 12px 16px 0;vertical-align:top;">
                        <div style="padding:18px;border:1px solid #e5e7eb;border-radius:18px;background:#fafafa;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">Nome</div>
                            <div style="font-size:18px;font-weight:700;color:#111827;">{$nomeSeguro}</div>
                        </div>
                    </td>
                    <td style="width:50%;padding:0 0 16px 12px;vertical-align:top;">
                        <div style="padding:18px;border:1px solid #e5e7eb;border-radius:18px;background:#fafafa;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">Placa MOTOCA</div>
                            <div style="font-size:18px;font-weight:800;letter-spacing:.08em;color:#111827;">{$placaSegura}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:0 0 16px 0;">
                        <div style="padding:18px;border:1px solid #e5e7eb;border-radius:18px;background:#fafafa;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">Data e hora informadas</div>
                            <div style="font-size:18px;font-weight:700;color:#111827;">{$dataSegura}</div>
                        </div>
                    </td>
                </tr>
            </table>
            <div style="margin-top:6px;padding:22px;border-radius:22px;background:linear-gradient(135deg,#111111 0%,#1f2937 100%);color:#ffffff;">
                <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#f5e500;margin-bottom:10px;">Prazo de retorno</div>
                <p style="margin:0;color:#d1d5db;font-size:15px;line-height:1.6;">
                    Nossa equipe realizará a análise da ocorrência e retornará em até {$prazoRetornoDias} dias por meio do canal de e-mail informado no cadastro.
                </p>
            </div>
        </div>
        <div style="padding:0 36px 30px;background:#ffffff;">
            <p style="margin:0;padding-top:6px;font-size:12px;line-height:1.7;color:#6b7280;">
                Este comprovante foi gerado automaticamente pela MOTOCA. Em caso de dúvida, tenha o número de registro em mãos no atendimento.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

function normalizarLocalidadeTexto(string $valor): string
{
    $valor = trim($valor);
    if ($valor === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        $valor = mb_strtolower($valor, 'UTF-8');
        $valor = mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8');
    } else {
        $valor = ucwords(strtolower($valor));
    }

    return preg_replace('/\s+/', ' ', $valor) ?? $valor;
}

/* TRATA LOCAL -> cidade / estado */
$cidade = normalizarLocalidadeTexto($cidadeInput);
$estado = strtoupper($estadoInput);

if ($cidade === '' || $estado === '') {
    $basesPermitidas = [
        'sao_paulo' => ['cidade' => 'Sao Paulo', 'estado' => 'SP'],
        'salvador' => ['cidade' => 'Salvador', 'estado' => 'BA'],
    ];

    if (isset($basesPermitidas[$baseLocal])) {
        $cidade = $basesPermitidas[$baseLocal]['cidade'];
        $estado = $basesPermitidas[$baseLocal]['estado'];
    } elseif (strpos($logradouroLocal, '-') !== false) {
        $partesLocal = array_map('trim', explode('-', $logradouroLocal));
        $estadoExtraido = array_pop($partesLocal);
        $cidadeExtraida = array_pop($partesLocal);

        $cidade = normalizarLocalidadeTexto((string)$cidadeExtraida);
        $estado = strtoupper((string)$estadoExtraido);
    }
}

if ($cidade === '') {
    $cidade = normalizarLocalidadeTexto($logradouroLocal);
}

if ($estado === '') {
    $estado = null;
}

/* VITIMAS (campo correto do banco é 'vitimas') */
$vitimas = ($houve_vitimas === 'sim') ? 'sim' : 'nao';
if ($vitimas !== 'sim') {
    $qtd_vitimas = null;
}

$tsRegistro = strtotime($data_hora);
if ($tsRegistro === false) {
    $tsRegistro = time();
}
try {
    $numero_registro = gerarNumeroRegistro($conn, $tsRegistro);
} catch (RuntimeException $erro) {
    appLogErro('geracao_registro_falhou', ['mensagem' => $erro->getMessage()]);
    redirecionarComErroFormulario("Não foi possível gerar um número de registro agora. Tente novamente.", $tipo_formulario);
}

$vinculoSinistro = sinistroPrepararVinculo($conn, [
    'tipo_formulario' => $tipo_formulario,
    'placa_motoca' => $placa_motoca,
    'placa_terceiro' => $placa_terceiro,
    'data_hora' => $data_hora,
    'cidade' => (string)$cidade,
]);

/* INSERT COMPATIVEL COM COLUNAS OPCIONAIS DE VINCULO */
$colunas = [
    'numero_registro',
    'tipo_formulario',
    'nome',
    'telefone',
    'email',
    'placa_motoca',
    'placa_terceiro',
    'data_hora',
    'cep',
    'logradouro',
    'bairro',
    'sentido_via',
    'ponto_referencia',
    'cidade',
    'estado',
    'tipo_ocorrencia',
    'situacao_via',
    'vitimas',
    'qtd_vitimas',
    'relato',
    'responsavel',
    'status',
];

$valores = [
    $numero_registro,
    $tipo_formulario,
    $nome,
    $telefone,
    $email,
    $placa_motoca,
    $placa_terceiro,
    $data_hora,
    $cepLocal,
    $logradouroLocal,
    $bairroLocal,
    $sentidoViaLocal,
    $pontoReferenciaLocal,
    $cidade,
    $estado,
    $tipo_ocorrencia,
    $situacao_via,
    $vitimas,
    $qtd_vitimas,
    $relato,
    $responsavel,
    'em_andamento',
];

if (sinistroTabelaTemColuna($conn, 'grupo_sinistro')) {
    $colunas[] = 'grupo_sinistro';
    $valores[] = $vinculoSinistro['grupo_sinistro'];
}

if (sinistroTabelaTemColuna($conn, 'sinistro_origem_id')) {
    $colunas[] = 'sinistro_origem_id';
    $valores[] = $vinculoSinistro['sinistro_origem_id'];
}

$camposOpcionais = [
    'perfil_terceiro' => $perfil !== '' ? $perfil : null,
    'informacoes_vitima' => $informacoesVitima !== '' ? $informacoesVitima : null,
    'possui_midias_acidente' => $possuiMidiasAcidente !== '' ? $possuiMidiasAcidente : null,
    'realizou_bo' => $realizouBo !== '' ? $realizouBo : null,
    'ja_realizou_orcamento' => $jaRealizouOrcamento !== '' ? $jaRealizouOrcamento : null,
    'ja_houve_conserto' => $jaHouveConserto !== '' ? $jaHouveConserto : null,
    'canal_codigo_validacao' => $canalCodigoValidacao,
    'destino_codigo_validacao' => $destinoCodigoValidacao,
    'codigo_validacao' => $codigoValidacao,
    'seguradora_nome' => $seguradoraNome !== '' ? $seguradoraNome : null,
    'seguradora_representando' => $seguradoraRepresentando !== '' ? $seguradoraRepresentando : null,
    'motivo_contato_seguradora' => $motivoContatoSeguradora !== '' ? $motivoContatoSeguradora : null,
];

foreach ($camposOpcionais as $colunaOpcional => $valorOpcional) {
    if (sinistroTabelaTemColuna($conn, $colunaOpcional)) {
        $colunas[] = $colunaOpcional;
        $valores[] = $valorOpcional;
    }
}

$placeholders = implode(', ', array_fill(0, count($colunas), '?'));
$sql = "INSERT INTO sinistros (" . implode(",\n    ", $colunas) . ",\n    criado_em\n) VALUES (" . $placeholders . ", NOW())";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    appLogErro('sinistro_prepare_falhou', ['erro' => $conn->error]);
    redirecionarComErroFormulario("Não foi possível preparar o salvamento do formulário.", $tipo_formulario);
}

sinistroBindDynamicParams($stmt, str_repeat('s', count($valores)), $valores);

if (!$stmt->execute()) {
    appLogErro('sinistro_execute_falhou', ['erro' => $stmt->error]);
    redirecionarComErroFormulario("Não foi possível salvar o formulário neste momento.", $tipo_formulario);
}

/* ID */
$id_sinistro = $stmt->insert_id;

registrarAuditoriaSinistro(
    $conn,
    $id_sinistro,
    'abertura_sinistro',
    null,
    'em_andamento',
    'Sinistro aberto pelo formulário público.',
    (string)$tipo_formulario
);
appLogEvento('sinistro_aberto', [
    'sinistro_id' => $id_sinistro,
    'numero_registro' => $numero_registro,
    'tipo_formulario' => $tipo_formulario,
    'placa_motoca' => $placa_motoca,
    'grupo_sinistro' => $vinculoSinistro['grupo_sinistro'],
    'sinistro_relacionado_id' => $vinculoSinistro['sinistro_relacionado']['id'] ?? null,
]);

/* UPLOADS */
$pasta = "uploads/sinistros/$id_sinistro/";
if (!is_dir($pasta)) {
    mkdir($pasta, 0755, true);
}

$camposSimples = [
    'foto_frente' => '360_frente',
    'foto_traseira' => '360_traseira',
    'foto_lado_esq' => '360_lado_esq',
    'foto_lado_dir' => '360_lado_dir',
    'documento_procuracao' => 'procuracao',
    'documento_oficial_foto' => 'documento_oficial_foto',
    'documento_oficial_procurador' => 'documento_oficial_procurador',
    'documento_laudo_curatela' => 'laudo_curatela',
    'documento_termo_curatela' => 'termo_curatela',
    'documento_representacao_seguradora' => 'documento_representacao_seguradora',
    'bo' => 'bo',
    'cnh' => 'cnh',
    'crlv' => 'crlv',
    'orcamento_1' => 'orcamento',
    'orcamento_2' => 'orcamento',
    'orcamento_3' => 'orcamento',
    'comprovante_conserto' => 'comprovante_conserto',
];
foreach ($camposSimples as $campo => $prefixo) {
    salvarArquivoUpload($campo, $pasta, $prefixo);
}

salvarArquivosMultiplos('fotos', $pasta, 'local_acidente');
salvarArquivosMultiplos('fotos_local', $pasta, 'local_acidente');
salvarArquivosMultiplos('fotos_camera', $pasta, 'camera_local');
salvarArquivosMultiplos('outros_documentos_seguradora', $pasta, 'outros_documentos');
salvarAssinaturaBase64($assinaturaCondutor, $pasta);

$emailCodigoEnviado = false;
if (VALIDACAO_EMAIL_ATIVA && in_array($tipo_formulario, ['locatario', 'terceiro'], true) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    [$enviado, $mensagemEnvioEmail] = enviarEmailConfirmacaoRegistro(
        $email,
        $nome,
        $numero_registro,
        $placa_motoca,
        $data_hora,
        (string)$tipo_formulario
    );

    if (!$enviado) {
        registrarLogEmail("Falha ao enviar e-mail de confirmação: tipo={$tipo_formulario} | {$email} | registro {$numero_registro} | {$mensagemEnvioEmail}");
        appLogErro('email_confirmacao_falhou', [
            'sinistro_id' => $id_sinistro,
            'numero_registro' => $numero_registro,
            'email' => $email,
            'tipo_formulario' => $tipo_formulario,
            'mensagem' => $mensagemEnvioEmail,
        ]);
    } else {
        if ($canalCodigoValidacao === 'email' && stripos($mensagemEnvioEmail, 'desativado') === false) {
            $emailCodigoEnviado = true;
        }
        appLogEvento('email_confirmacao_enviado', [
            'sinistro_id' => $id_sinistro,
            'numero_registro' => $numero_registro,
            'email' => $email,
            'mensagem' => $mensagemEnvioEmail,
        ]);
    }
}

$_SESSION['sinistro_sucesso'] = [
    'numero_registro' => $numero_registro,
    'nome' => $nome,
    'placa_motoca' => $placa_motoca,
    'tipo_formulario' => (string)$tipo_formulario,
    'prazo_retorno_dias' => PRAZO_RETORNO_DIAS,
    'destino_codigo_validacao' => $destinoCodigoValidacao,
    'codigo_enviado_por_email' => $emailCodigoEnviado,
];

unset($_SESSION['sinistro_comprovante_preview']);

if (VALIDACAO_EMAIL_ATIVA) {
    unset($_SESSION['sinistro_codigo_validacao']);
}

/* REDIRECIONA */
session_write_close();
header("Location: sucesso.php");
exit;
