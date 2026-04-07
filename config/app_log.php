<?php

function appLogEscrever(string $canal, array $dados): void
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $payload = [
        'timestamp' => date('c'),
        'canal' => $canal,
    ] + $dados;

    @file_put_contents(
        $dir . '/' . $canal . '.log',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );
}

function appLogEvento(string $evento, array $contexto = []): void
{
    appLogEscrever('app_eventos', [
        'evento' => $evento,
        'contexto' => $contexto,
    ]);
}

function appLogErro(string $evento, array $contexto = []): void
{
    appLogEscrever('app_erros', [
        'evento' => $evento,
        'contexto' => $contexto,
    ]);
}

function appLogTentativaLoginAdmin(string $usuario, bool $sucesso, array $contexto = []): void
{
    appLogEscrever('admin_login', [
        'evento' => 'tentativa_login_admin',
        'usuario' => $usuario,
        'sucesso' => $sucesso,
        'contexto' => $contexto,
    ]);
}
