<?php

function tabelaAuditoriaSinistroExiste(mysqli $conn): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $resultado = $conn->query("SHOW TABLES LIKE 'sinistro_historico'");
    $cache = $resultado instanceof mysqli_result && $resultado->num_rows > 0;

    if ($resultado instanceof mysqli_result) {
        $resultado->free();
    }

    return $cache;
}

function registrarAuditoriaSinistro(
    mysqli $conn,
    int $sinistroId,
    string $acao,
    ?string $valorAnterior = null,
    ?string $valorNovo = null,
    ?string $observacao = null,
    ?string $adminUsuario = null
): void {
    if ($sinistroId <= 0 || $acao === '' || !tabelaAuditoriaSinistroExiste($conn)) {
        return;
    }

    $adminUsuario = trim((string)$adminUsuario);
    if ($adminUsuario === '') {
        $adminUsuario = null;
    }

    $stmt = $conn->prepare(
        "INSERT INTO sinistro_historico (
            sinistro_id,
            acao,
            valor_anterior,
            valor_novo,
            observacao,
            admin_usuario,
            criado_em
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "isssss",
        $sinistroId,
        $acao,
        $valorAnterior,
        $valorNovo,
        $observacao,
        $adminUsuario
    );
    $stmt->execute();
    $stmt->close();
}

function listarAuditoriaSinistro(mysqli $conn, int $sinistroId, int $limite = 50): array
{
    if ($sinistroId <= 0 || !tabelaAuditoriaSinistroExiste($conn)) {
        return [];
    }

    $limite = max(1, min($limite, 200));
    $sql = "SELECT acao, valor_anterior, valor_novo, observacao, admin_usuario, criado_em
            FROM sinistro_historico
            WHERE sinistro_id = ?
            ORDER BY criado_em DESC, id DESC
            LIMIT {$limite}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $sinistroId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $itens = $resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return is_array($itens) ? $itens : [];
}

function formatarAcaoAuditoria(string $acao): string
{
    return match ($acao) {
        'abertura_sinistro' => 'Abertura do sinistro',
        'alteracao_status' => 'Alteracao de status',
        'exclusao_sinistro' => 'Exclusao do sinistro',
        default => ucfirst(str_replace('_', ' ', $acao)),
    };
}
