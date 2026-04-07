<?php

function emailTransportCarregarConfig(): array {
    $padrao = [
        'transport' => 'graph',
        'from_email' => 'no-reply@motoca.local',
        'from_name' => 'MOTOCA',
        'reply_to' => 'suporte@motoca.local',
        'smtp' => [
            'host' => 'localhost',
            'port' => 587,
            'secure' => 'tls',
            'username' => '',
            'password' => '',
            'auth' => true,
            'timeout' => 20,
            'helo' => 'localhost',
        ],
        'graph' => [
            'tenant_id' => '',
            'client_id' => '',
            'client_secret' => '',
            'mailbox_user_id' => '',
            'scope' => 'https://graph.microsoft.com/.default',
            'base_url' => 'https://graph.microsoft.com/v1.0',
            'timeout' => 20,
        ],
    ];

    $arquivo = __DIR__ . '/email.php';
    $config = is_file($arquivo) ? require $arquivo : [];
    if (!is_array($config)) {
        $config = [];
    }

    $merged = array_merge($padrao, $config);
    $merged['smtp'] = array_merge($padrao['smtp'], is_array($config['smtp'] ?? null) ? $config['smtp'] : []);
    $merged['graph'] = array_merge($padrao['graph'], is_array($config['graph'] ?? null) ? $config['graph'] : []);

    $merged['transport'] = getenv('MOTOCA_EMAIL_TRANSPORT') ?: $merged['transport'];

    $merged['smtp']['host'] = getenv('MOTOCA_SMTP_HOST') ?: $merged['smtp']['host'];
    $merged['smtp']['port'] = (int)(getenv('MOTOCA_SMTP_PORT') ?: $merged['smtp']['port']);
    $merged['smtp']['secure'] = getenv('MOTOCA_SMTP_SECURE') ?: $merged['smtp']['secure'];
    $merged['smtp']['username'] = getenv('MOTOCA_SMTP_USER') ?: $merged['smtp']['username'];
    $merged['smtp']['password'] = getenv('MOTOCA_SMTP_PASS') ?: $merged['smtp']['password'];
    $merged['smtp']['helo'] = getenv('MOTOCA_SMTP_HELO') ?: $merged['smtp']['helo'];

    $merged['graph']['tenant_id'] = getenv('MOTOCA_GRAPH_TENANT_ID') ?: $merged['graph']['tenant_id'];
    $merged['graph']['client_id'] = getenv('MOTOCA_GRAPH_CLIENT_ID') ?: $merged['graph']['client_id'];
    $merged['graph']['client_secret'] = getenv('MOTOCA_GRAPH_CLIENT_SECRET') ?: $merged['graph']['client_secret'];
    $merged['graph']['mailbox_user_id'] = getenv('MOTOCA_GRAPH_MAILBOX_USER_ID') ?: $merged['graph']['mailbox_user_id'];
    $merged['graph']['scope'] = getenv('MOTOCA_GRAPH_SCOPE') ?: $merged['graph']['scope'];
    $merged['graph']['base_url'] = getenv('MOTOCA_GRAPH_BASE_URL') ?: $merged['graph']['base_url'];

    return $merged;
}

function emailTransportRegistrarLog(string $mensagem): void {
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
    $dir = dirname(__DIR__) . '/logs';
    $arquivo = $dir . '/email_smtp.log';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    @file_put_contents($arquivo, $linha, FILE_APPEND);
    error_log($mensagem);
}

function emailTransportCurl(string $url, array $headers, $body, int $timeout): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, (string)$response, (string)$error];
}

function emailTransportErroHttp(string $fallback, string $body): string {
    $json = json_decode($body, true);
    $mensagem = trim((string)($json['error_description'] ?? ($json['error']['message'] ?? '')));
    return $mensagem !== '' ? $mensagem : $fallback;
}

function emailTransportObterTokenGraph(array $config): array {
    $graph = is_array($config['graph'] ?? null) ? $config['graph'] : [];
    $tenantId = trim((string)($graph['tenant_id'] ?? ''));
    $clientId = trim((string)($graph['client_id'] ?? ''));
    $clientSecret = (string)($graph['client_secret'] ?? '');
    $scope = trim((string)($graph['scope'] ?? 'https://graph.microsoft.com/.default'));
    $timeout = (int)($graph['timeout'] ?? 20);

    if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
        return [false, 'Configuração Graph incompleta: tenant_id, client_id e client_secret são obrigatórios.'];
    }

    $url = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
    [$status, $body, $error] = emailTransportCurl(
        $url,
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'client_id' => $clientId,
            'scope' => $scope,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]),
        $timeout
    );

    if ($error !== '') {
        return [false, 'Falha ao obter token Graph: ' . $error];
    }

    if ($status < 200 || $status >= 300) {
        return [false, emailTransportErroHttp('Falha ao obter token Graph.', $body)];
    }

    $json = json_decode($body, true);
    $token = (string)($json['access_token'] ?? '');
    if ($token === '') {
        return [false, 'Resposta do token Graph sem access_token.'];
    }

    return [true, $token];
}

function emailTransportEnviarGraph(array $config, string $destinatario, string $assunto, string $html): array {
    [$okToken, $tokenOuErro] = emailTransportObterTokenGraph($config);
    if (!$okToken) {
        emailTransportRegistrarLog('Graph token falhou: ' . $tokenOuErro);
        return [false, $tokenOuErro];
    }

    $graph = is_array($config['graph'] ?? null) ? $config['graph'] : [];
    $baseUrl = rtrim((string)($graph['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
    $mailbox = trim((string)($graph['mailbox_user_id'] ?? ''));
    if ($mailbox === '') {
        $mailbox = trim((string)($config['from_email'] ?? ''));
    }
    if ($mailbox === '') {
        return [false, 'Configuração Graph incompleta: mailbox_user_id ou from_email é obrigatório.'];
    }

    $payload = [
        'message' => [
            'subject' => $assunto,
            'body' => [
                'contentType' => 'HTML',
                'content' => $html,
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $destinatario,
                    ],
                ],
            ],
            'replyTo' => [
                [
                    'emailAddress' => [
                        'address' => (string)($config['reply_to'] ?? $config['from_email']),
                        'name' => (string)($config['from_name'] ?? 'MOTOCA'),
                    ],
                ],
            ],
        ],
        'saveToSentItems' => true,
    ];

    [$status, $body, $error] = emailTransportCurl(
        $baseUrl . '/users/' . rawurlencode($mailbox) . '/sendMail',
        [
            'Authorization: Bearer ' . $tokenOuErro,
            'Content-Type: application/json',
        ],
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (int)($graph['timeout'] ?? 20)
    );

    if ($error !== '') {
        emailTransportRegistrarLog('Graph sendMail erro cURL: ' . $error);
        return [false, 'Falha na requisição Graph: ' . $error];
    }

    if ($status !== 202) {
        $mensagem = emailTransportErroHttp('Graph sendMail retornou erro HTTP ' . $status . '.', $body);
        emailTransportRegistrarLog('Graph sendMail falhou: ' . $mensagem);
        return [false, $mensagem];
    }

    emailTransportRegistrarLog('Graph sendMail OK para ' . $destinatario);
    return [true, 'E-mail enviado via Microsoft Graph.'];
}

function emailTransportSmtpLerResposta($socket): array {
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

    return [(int)substr($resposta, 0, 3), trim($resposta)];
}

function emailTransportSmtpComando($socket, string $comando, array $codigosEsperados): array {
    fwrite($socket, $comando . "\r\n");
    [$codigo, $resposta] = emailTransportSmtpLerResposta($socket);
    return [in_array($codigo, $codigosEsperados, true), $resposta];
}

function emailTransportEnviarSmtp(array $config, string $destinatario, string $assunto, string $html): array {
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
        return [false, 'SMTP inválido: host/porta ausentes.'];
    }

    $fromName = str_replace(["\r", "\n"], '', (string)($config['from_name'] ?? 'MOTOCA'));
    $fromEmail = str_replace(["\r", "\n"], '', (string)($config['from_email'] ?? 'no-reply@motoca.local'));
    $replyTo = str_replace(["\r", "\n"], '', (string)($config['reply_to'] ?? $fromEmail));
    $toEmail = str_replace(["\r", "\n"], '', $destinatario);

    $alvo = (($secure === 'ssl') ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($alvo, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return [false, "Falha conexão SMTP ({$alvo}): [{$errno}] {$errstr}"];
    }

    stream_set_timeout($socket, $timeout);

    [$codigoInicial, $respostaInicial] = emailTransportSmtpLerResposta($socket);
    if ($codigoInicial !== 220) {
        fclose($socket);
        return [false, 'SMTP sem saudação 220: ' . $respostaInicial];
    }

    [$okEhlo, $respEhlo] = emailTransportSmtpComando($socket, "EHLO {$helo}", [250]);
    if (!$okEhlo) {
        fclose($socket);
        return [false, 'SMTP EHLO falhou: ' . $respEhlo];
    }

    if ($secure === 'tls') {
        [$okStartTls, $respStartTls] = emailTransportSmtpComando($socket, 'STARTTLS', [220]);
        if (!$okStartTls) {
            fclose($socket);
            return [false, 'SMTP STARTTLS falhou: ' . $respStartTls];
        }

        if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            fclose($socket);
            return [false, 'SMTP não conseguiu habilitar TLS.'];
        }

        [$okEhloTls, $respEhloTls] = emailTransportSmtpComando($socket, "EHLO {$helo}", [250]);
        if (!$okEhloTls) {
            fclose($socket);
            return [false, 'SMTP EHLO pós-TLS falhou: ' . $respEhloTls];
        }
    }

    if ($auth) {
        [$okAuth, $respAuth] = emailTransportSmtpComando($socket, 'AUTH LOGIN', [334]);
        if (!$okAuth) {
            fclose($socket);
            return [false, 'SMTP AUTH LOGIN falhou: ' . $respAuth];
        }

        [$okUser, $respUser] = emailTransportSmtpComando($socket, base64_encode($username), [334]);
        if (!$okUser) {
            fclose($socket);
            return [false, 'SMTP usuário falhou: ' . $respUser];
        }

        [$okPass, $respPass] = emailTransportSmtpComando($socket, base64_encode($password), [235]);
        if (!$okPass) {
            fclose($socket);
            return [false, 'SMTP senha falhou: ' . $respPass];
        }
    }

    [$okMailFrom, $respMailFrom] = emailTransportSmtpComando($socket, "MAIL FROM:<{$fromEmail}>", [250]);
    if (!$okMailFrom) {
        fclose($socket);
        return [false, 'SMTP MAIL FROM falhou: ' . $respMailFrom];
    }

    [$okRcpt, $respRcpt] = emailTransportSmtpComando($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
    if (!$okRcpt) {
        fclose($socket);
        return [false, 'SMTP RCPT TO falhou: ' . $respRcpt];
    }

    [$okData, $respData] = emailTransportSmtpComando($socket, 'DATA', [354]);
    if (!$okData) {
        fclose($socket);
        return [false, 'SMTP DATA falhou: ' . $respData];
    }

    $headers = [];
    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'To: <' . $toEmail . '>';
    $headers[] = 'Reply-To: ' . $replyTo;
    $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($assunto) . '?=';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $corpo = preg_replace("/\r\n\./", "\r\n..", str_replace(["\r\n", "\r", "\n"], "\r\n", $html));
    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $corpo . "\r\n.\r\n");
    [$codigoEnvio, $respEnvio] = emailTransportSmtpLerResposta($socket);
    emailTransportSmtpComando($socket, 'QUIT', [221]);
    fclose($socket);

    if ($codigoEnvio !== 250) {
        return [false, 'SMTP envio falhou: ' . $respEnvio];
    }

    return [true, 'E-mail enviado via SMTP.'];
}

function emailTransportEnviarHtml(string $destinatario, string $assunto, string $html): array {
    $config = emailTransportCarregarConfig();

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Destinatário inválido.'];
    }

    $transport = strtolower((string)($config['transport'] ?? 'graph'));

    if ($transport === 'disabled' || $transport === 'off' || $transport === 'none') {
        emailTransportRegistrarLog('Envio de e-mail desativado. Destinatário ignorado: ' . $destinatario);
        return [true, 'Envio de e-mail desativado na configuração.'];
    }

    if ($transport === 'mail') {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . ($config['from_name'] ?? 'MOTOCA') . ' <' . ($config['from_email'] ?? 'no-reply@motoca.local') . '>';
        $headers[] = 'Reply-To: ' . ($config['reply_to'] ?? ($config['from_email'] ?? 'no-reply@motoca.local'));
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $ok = mail($destinatario, $assunto, $html, implode("\r\n", $headers));
        if (!$ok) {
            emailTransportRegistrarLog('Falha no transporte mail() para ' . $destinatario);
            return [false, 'Falha no transporte mail().'];
        }

        emailTransportRegistrarLog('mail() envio OK para ' . $destinatario);
        return [true, 'E-mail enviado via mail().'];
    }

    if ($transport === 'smtp') {
        [$ok, $mensagem] = emailTransportEnviarSmtp($config, $destinatario, $assunto, $html);
        emailTransportRegistrarLog(($ok ? 'SMTP OK: ' : 'SMTP ERRO: ') . $mensagem);
        return [$ok, $mensagem];
    }

    [$ok, $mensagem] = emailTransportEnviarGraph($config, $destinatario, $assunto, $html);
    return [$ok, $mensagem];
}
