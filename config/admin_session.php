<?php

const ADMIN_SESSION_TIMEOUT = 1800;
const ADMIN_SESSION_REGENERATE = 300;

function adminSessionInit(): void
{
    if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
        return;
    }

    $agora = time();
    $ultimaAtividade = (int)($_SESSION['admin_ultima_atividade'] ?? 0);
    if ($ultimaAtividade > 0 && ($agora - $ultimaAtividade) > ADMIN_SESSION_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        session_start();
        return;
    }

    $ultimoRegen = (int)($_SESSION['admin_ultimo_regenerate'] ?? 0);
    if ($ultimoRegen === 0 || ($agora - $ultimoRegen) > ADMIN_SESSION_REGENERATE) {
        session_regenerate_id(true);
        $_SESSION['admin_ultimo_regenerate'] = $agora;
    }

    $_SESSION['admin_ultima_atividade'] = $agora;
}
