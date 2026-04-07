<?php

function sinistroTabelaTemColuna(mysqli $conn, string $coluna): bool
{
    static $cache = [];
    if (array_key_exists($coluna, $cache)) {
        return $cache[$coluna];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'sinistros'
          AND column_name = ?
        LIMIT 1
    ");
    if (!$stmt) {
        $cache[$coluna] = false;
        return false;
    }

    $stmt->bind_param("s", $coluna);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cache[$coluna] = $resultado && $resultado->num_rows > 0;
    $stmt->close();

    return $cache[$coluna];
}

function sinistroNormalizarPlaca(string $placa): string
{
    return strtoupper((string)preg_replace('/[^A-Z0-9]/', '', $placa));
}

function sinistroGerarGrupoId(): string
{
    return 'GS' . date('ymdHis') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function sinistroBindDynamicParams(mysqli_stmt $stmt, string $types, array $values): bool
{
    $refs = [];
    foreach ($values as $indice => $valor) {
        $refs[$indice] = &$values[$indice];
    }

    return $stmt->bind_param($types, ...$refs);
}

function sinistroEncontrarRelacionado(mysqli $conn, array $dados): ?array
{
    $placaMotoca = sinistroNormalizarPlaca((string)($dados['placa_motoca'] ?? ''));
    $placaTerceiro = sinistroNormalizarPlaca((string)($dados['placa_terceiro'] ?? ''));
    $dataHora = (string)($dados['data_hora'] ?? '');
    $cidade = trim((string)($dados['cidade'] ?? ''));
    $tipoFormulario = trim((string)($dados['tipo_formulario'] ?? ''));

    if ($placaMotoca === '' || $dataHora === '') {
        return null;
    }

    $sql = "
        SELECT id, numero_registro, tipo_formulario, data_hora, cidade, placa_motoca, placa_terceiro, grupo_sinistro, sinistro_origem_id
        FROM sinistros
        WHERE UPPER(REPLACE(COALESCE(placa_motoca, ''), '-', '')) = ?
          AND DATE(data_hora) = DATE(?)
    ";

    $tipos = 'ss';
    $params = [$placaMotoca, $dataHora];

    if ($cidade !== '') {
        $sql .= " AND LOWER(COALESCE(cidade, '')) = LOWER(?)";
        $tipos .= 's';
        $params[] = $cidade;
    }

    $prioridadePlacaTerceiro = $placaTerceiro;
    $prioridadeTipo = $tipoFormulario === 'locatario' ? 'terceiro' : ($tipoFormulario === 'terceiro' ? 'locatario' : '');

    $sql .= "
        ORDER BY
            CASE
                WHEN ? <> '' AND UPPER(REPLACE(COALESCE(placa_terceiro, ''), '-', '')) = ? THEN 0
                WHEN ? <> '' AND UPPER(REPLACE(COALESCE(placa_terceiro, ''), '-', '')) <> '' THEN 1
                ELSE 2
            END,
            CASE
                WHEN ? <> '' AND tipo_formulario = ? THEN 0
                ELSE 1
            END,
            ABS(TIMESTAMPDIFF(MINUTE, data_hora, ?)) ASC,
            id ASC
        LIMIT 1
    ";
    $tipos .= 'ssssss';
    $params[] = $prioridadePlacaTerceiro;
    $params[] = $prioridadePlacaTerceiro;
    $params[] = $prioridadePlacaTerceiro;
    $params[] = $prioridadeTipo;
    $params[] = $prioridadeTipo;
    $params[] = $dataHora;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    sinistroBindDynamicParams($stmt, $tipos, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

function sinistroPrepararVinculo(mysqli $conn, array $dados): array
{
    $suportaGrupo = sinistroTabelaTemColuna($conn, 'grupo_sinistro');
    $suportaOrigem = sinistroTabelaTemColuna($conn, 'sinistro_origem_id');

    if (!$suportaGrupo && !$suportaOrigem) {
        return [
            'grupo_sinistro' => null,
            'sinistro_origem_id' => null,
            'sinistro_relacionado' => null,
        ];
    }

    $relacionado = sinistroEncontrarRelacionado($conn, $dados);
    $grupo = null;
    $origemId = null;

    if ($relacionado) {
        $grupo = trim((string)($relacionado['grupo_sinistro'] ?? ''));
        if ($grupo === '') {
            $grupo = sinistroGerarGrupoId();
            if ($suportaGrupo) {
                $stmtUpdate = $conn->prepare("UPDATE sinistros SET grupo_sinistro = ? WHERE id = ?");
                if ($stmtUpdate) {
                    $idRelacionado = (int)$relacionado['id'];
                    $stmtUpdate->bind_param("si", $grupo, $idRelacionado);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
            }
        }

        if ($suportaOrigem) {
            $origemId = (int)($relacionado['sinistro_origem_id'] ?? 0);
            if ($origemId <= 0) {
                $origemId = (int)$relacionado['id'];
            }
        }
    } elseif ($suportaGrupo) {
        $grupo = sinistroGerarGrupoId();
    }

    return [
        'grupo_sinistro' => $grupo,
        'sinistro_origem_id' => $origemId,
        'sinistro_relacionado' => $relacionado,
    ];
}

function sinistroListarRelacionados(mysqli $conn, array $sinistroAtual): array
{
    $grupo = trim((string)($sinistroAtual['grupo_sinistro'] ?? ''));
    if ($grupo === '' || !sinistroTabelaTemColuna($conn, 'grupo_sinistro')) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT id, numero_registro, tipo_formulario, nome, placa_motoca, placa_terceiro, data_hora, relato, status
        FROM sinistros
        WHERE grupo_sinistro = ?
        ORDER BY data_hora ASC, id ASC
    ");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("s", $grupo);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $itens = [];
    while ($row = $resultado->fetch_assoc()) {
        $itens[] = $row;
    }
    $stmt->close();

    return $itens;
}
