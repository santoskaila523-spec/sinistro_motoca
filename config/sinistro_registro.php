<?php

function gerarNumeroRegistro(mysqli $conn, ?int $timestampBase = null): string
{
    $ano = date('Y', $timestampBase ?? time());

    $stmt = $conn->prepare("SELECT 1 FROM sinistros WHERE numero_registro = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException("Erro ao preparar gerador de registro: " . $conn->error);
    }

    for ($tentativa = 0; $tentativa < 100; $tentativa++) {
        $aleatorio = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $numeroRegistro = $ano . $aleatorio;

        $stmt->bind_param("s", $numeroRegistro);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;

        if (!$existe) {
            $stmt->close();
            return $numeroRegistro;
        }
    }

    $stmt->close();
    throw new RuntimeException("Nao foi possivel gerar um numero de registro unico.");
}
