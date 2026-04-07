<?php
/*
|------------------------------------------------------------
| Contas autorizadas a entrar em admin/usuarios.php
|------------------------------------------------------------
| Use os mesmos nomes do campo "usuario" da tabela admins.
*/
$usuarios_gerenciam_admins = [
    'admin',
    'motoca',
];

/*
|------------------------------------------------------------
| Tempo de autorizacao da tela (em segundos)
|------------------------------------------------------------
*/
$ttl_autorizacao_usuarios = 900; // 15 minutos

/*
|------------------------------------------------------------
| Perfis do admin e permissões
|------------------------------------------------------------
*/
function perfisAdminDisponiveis(): array
{
    return [
        'atendente' => 'Atendente',
        'supervisor' => 'Supervisor',
        'gerente' => 'Gerente',
        'diretor' => 'Diretor',
    ];
}

function normalizarPerfilAdmin(?string $perfil): string
{
    $perfil = strtolower(trim((string)$perfil));
    $perfis = perfisAdminDisponiveis();

    if (!isset($perfis[$perfil])) {
        return 'diretor';
    }

    return $perfil;
}

function perfilAdminRotulo(?string $perfil): string
{
    $perfil = normalizarPerfilAdmin($perfil);
    return perfisAdminDisponiveis()[$perfil] ?? 'Diretor';
}

function perfisComAcessoAuditoria(): array
{
    return ['supervisor', 'gerente', 'diretor'];
}

function adminPodeAcessarAuditoria(?string $perfil): bool
{
    return in_array(normalizarPerfilAdmin($perfil), perfisComAcessoAuditoria(), true);
}

function perfisComAcessoExclusao(): array
{
    return ['supervisor', 'gerente', 'diretor'];
}

function adminPodeExcluirSinistro(?string $perfil): bool
{
    return in_array(normalizarPerfilAdmin($perfil), perfisComAcessoExclusao(), true);
}

function autorizacaoSensivelValida(string $chave, string $usuario): bool
{
    $dados = $_SESSION[$chave] ?? null;
    if (!is_array($dados)) {
        return false;
    }

    return (string)($dados['usuario'] ?? '') === $usuario
        && (int)($dados['ate'] ?? 0) >= time();
}
