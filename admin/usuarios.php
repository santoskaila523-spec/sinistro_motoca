<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

require "../config/db.php";
require "../config/admin_permissoes.php";

$adminUsuarioLogado = (string)($_SESSION['admin_usuario'] ?? '');
$autorizados = $usuarios_gerenciam_admins ?? [];
$ttl = (int)($ttl_autorizacao_usuarios ?? 900);
$ttlMinutos = (int)ceil(max(60, $ttl) / 60);

if (!in_array($adminUsuarioLogado, $autorizados, true)) {
    http_response_code(403);
    echo "Acesso negado. Sua conta não está autorizada para gerenciar usuários.";
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function redirecionarComMensagem(string $tipo, string $msg): void {
    header("Location: usuarios.php?$tipo=" . urlencode($msg));
    exit;
}

function autorizacaoTelaValida(string $usuario): bool {
    return autorizacaoSensivelValida('autorizacao_usuarios', $usuario);
}

$erroAutorizacao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'autorizar_tela') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $erroAutorizacao = 'Token inválido. Recarregue a página.';
    } else {
        $senha = (string)($_POST['senha_autorizacao'] ?? '');
        $stmt = $conn->prepare("SELECT senha FROM admins WHERE usuario = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $adminUsuarioLogado);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($senha, (string)$row['senha'])) {
                $_SESSION['autorizacao_usuarios'] = [
                    'usuario' => $adminUsuarioLogado,
                    'ate' => time() + max(60, $ttl),
                ];
                header("Location: usuarios.php");
                exit;
            }
        }
        $erroAutorizacao = 'Senha inválida.';
    }
}

$autorizadoNaTela = autorizacaoTelaValida($adminUsuarioLogado);

if (!$autorizadoNaTela):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Autorização | Usuários Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
    --bg: #0f1014;
    --card: #171922;
    --border: #2a2e3b;
    --text: #f4f6fb;
    --muted: #9aa3b2;
    --accent: #f5e500;
    --danger: #ff8a8a;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    min-height: 100vh;
    padding: 24px;
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--text);
    background:
        radial-gradient(1200px 500px at 120% -20%, rgba(255, 212, 0, 0.12), transparent 60%),
        linear-gradient(180deg, #0f1014 0%, #0d0f13 100%);
}
.box {
    max-width: 460px;
    margin: 72px auto;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.35);
}
.tag {
    display: inline-block;
    font-size: 11px;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #111;
    background: var(--accent);
    padding: 4px 8px;
    border-radius: 999px;
    font-weight: 700;
    margin-bottom: 10px;
}
h1 {
    margin: 0 0 10px;
    font-size: 24px;
}
p {
    margin: 0 0 12px;
    color: var(--muted);
    line-height: 1.45;
}
.conta {
    background: rgba(255,255,255,.03);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 12px;
    color: var(--text);
}
label {
    display: block;
    margin-bottom: 7px;
    color: #cfd6e3;
    font-size: 13px;
    font-weight: 600;
}
input {
    width: 100%;
    background: #0f121a;
    color: var(--text);
    border: 1px solid #323748;
    border-radius: 10px;
    padding: 11px 12px;
    margin-bottom: 12px;
}
select {
    width: 100%;
    background: #0f121a;
    color: var(--text);
    border: 1px solid #323748;
    border-radius: 10px;
    padding: 10px 11px;
    margin-bottom: 10px;
}
select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(255, 212, 0, 0.16);
}
input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(255, 212, 0, 0.18);
}
.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.btn {
    border: 0;
    border-radius: 10px;
    padding: 10px 14px;
    font-weight: 700;
    cursor: pointer;
    background: var(--accent);
    color: #111;
}
.btn-link {
    display: inline-flex;
    align-items: center;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    color: var(--text);
    text-decoration: none;
    background: rgba(255,255,255,.02);
}
.erro {
    color: var(--danger);
    margin: 0 0 12px;
    background: rgba(170, 34, 34, 0.16);
    border: 1px solid rgba(170, 34, 34, 0.45);
    border-radius: 10px;
    padding: 9px 10px;
}
</style>
</head>
<body>
<div class="box">
    <span class="tag">Área restrita</span>
    <h1>Confirmar autorização</h1>
    <p>Confirme sua senha para abrir o gerenciamento de usuários.</p>
    <div class="conta">Conta logada: <strong><?= htmlspecialchars($adminUsuarioLogado, ENT_QUOTES, 'UTF-8') ?></strong><br>Validade desta autorização: <?= (int)$ttlMinutos ?> min</div>
    <?php if ($erroAutorizacao !== ''): ?>
    <div class="erro"><?= htmlspecialchars($erroAutorizacao, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="acao" value="autorizar_tela">
        <label>Digite sua senha para entrar no gerenciamento</label>
        <input type="password" name="senha_autorizacao" required>
        <div class="actions">
            <button class="btn" type="submit">Autorizar</button>
            <a class="btn-link" href="painel.php">Voltar</a>
        </div>
    </form>
</div>
</body>
</html>
<?php
exit;
endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        redirecionarComMensagem('erro', 'Token inválido. Recarregue a página.');
    }

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $usuarioNovo = trim((string)($_POST['usuario_novo'] ?? ''));
        $senhaNova = (string)($_POST['senha_nova'] ?? '');
        $perfilNovo = normalizarPerfilAdmin((string)($_POST['perfil_novo'] ?? 'diretor'));

        if ($usuarioNovo === '' || strlen($usuarioNovo) < 3) {
            redirecionarComMensagem('erro', 'Usuário deve ter pelo menos 3 caracteres.');
        }
        if (strlen($senhaNova) < 6) {
            redirecionarComMensagem('erro', 'Senha deve ter pelo menos 6 caracteres.');
        }

        $check = $conn->prepare("SELECT id FROM admins WHERE usuario = ? LIMIT 1");
        if (!$check) {
            redirecionarComMensagem('erro', 'Erro no banco ao validar usuário.');
        }
        $check->bind_param("s", $usuarioNovo);
        $check->execute();
        $jaExiste = $check->get_result()->num_rows > 0;
        $check->close();

        if ($jaExiste) {
            redirecionarComMensagem('erro', 'Usuário já existe.');
        }

        $hash = password_hash($senhaNova, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO admins (usuario, perfil, senha) VALUES (?, ?, ?)");
        if (!$ins) {
            redirecionarComMensagem('erro', 'Erro no banco ao criar usuário.');
        }
        $ins->bind_param("sss", $usuarioNovo, $perfilNovo, $hash);
        $ok = $ins->execute();
        $ins->close();

        if (!$ok) {
            redirecionarComMensagem('erro', 'Não foi possível criar o usuário.');
        }

        redirecionarComMensagem('ok', 'Usuário criado com sucesso.');
    }

    if ($acao === 'redefinir_senha') {
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        $novaSenha = (string)($_POST['nova_senha'] ?? '');

        if ($idUsuario <= 0) {
            redirecionarComMensagem('erro', 'Usuário inválido.');
        }
        if (strlen($novaSenha) < 6) {
            redirecionarComMensagem('erro', 'Nova senha deve ter pelo menos 6 caracteres.');
        }

        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE admins SET senha = ? WHERE id = ?");
        if (!$upd) {
            redirecionarComMensagem('erro', 'Erro no banco ao redefinir senha.');
        }
        $upd->bind_param("si", $hash, $idUsuario);
        $ok = $upd->execute();
        $linhas = $upd->affected_rows;
        $upd->close();

        if (!$ok || $linhas < 1) {
            redirecionarComMensagem('erro', 'Não foi possível redefinir a senha.');
        }

        redirecionarComMensagem('ok', 'Senha redefinida com sucesso.');
    }

    if ($acao === 'alterar_perfil') {
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        $perfilNovo = normalizarPerfilAdmin((string)($_POST['perfil_admin'] ?? 'diretor'));

        if ($idUsuario <= 0) {
            redirecionarComMensagem('erro', 'Usuário inválido.');
        }

        $updPerfil = $conn->prepare("UPDATE admins SET perfil = ? WHERE id = ?");
        if (!$updPerfil) {
            redirecionarComMensagem('erro', 'Erro no banco ao alterar perfil.');
        }
        $updPerfil->bind_param("si", $perfilNovo, $idUsuario);
        $ok = $updPerfil->execute();
        $updPerfil->close();

        if (!$ok) {
            redirecionarComMensagem('erro', 'Não foi possível alterar o perfil.');
        }

        if ($adminUsuarioLogado !== '') {
            $stmtUsuarioAtual = $conn->prepare("SELECT usuario FROM admins WHERE id = ? LIMIT 1");
            if ($stmtUsuarioAtual) {
                $stmtUsuarioAtual->bind_param("i", $idUsuario);
                $stmtUsuarioAtual->execute();
                $usuarioAtualizado = $stmtUsuarioAtual->get_result()->fetch_assoc();
                $stmtUsuarioAtual->close();

                if ((string)($usuarioAtualizado['usuario'] ?? '') === $adminUsuarioLogado) {
                    $_SESSION['admin_perfil'] = $perfilNovo;
                }
            }
        }

        redirecionarComMensagem('ok', 'Perfil atualizado com sucesso.');
    }

    if ($acao === 'excluir') {
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);

        if ($idUsuario <= 0) {
            redirecionarComMensagem('erro', 'Usuário inválido.');
        }

        $atual = $conn->prepare("SELECT id FROM admins WHERE usuario = ? LIMIT 1");
        if (!$atual) {
            redirecionarComMensagem('erro', 'Erro no banco ao validar usuário logado.');
        }
        $atual->bind_param("s", $adminUsuarioLogado);
        $atual->execute();
        $resAtual = $atual->get_result()->fetch_assoc();
        $atual->close();

        $idAtual = (int)($resAtual['id'] ?? 0);
        if ($idAtual > 0 && $idAtual === $idUsuario) {
            redirecionarComMensagem('erro', 'Você não pode excluir seu próprio usuário.');
        }

        $del = $conn->prepare("DELETE FROM admins WHERE id = ?");
        if (!$del) {
            redirecionarComMensagem('erro', 'Erro no banco ao excluir usuário.');
        }
        $del->bind_param("i", $idUsuario);
        $ok = $del->execute();
        $linhas = $del->affected_rows;
        $del->close();

        if (!$ok || $linhas < 1) {
            redirecionarComMensagem('erro', 'Não foi possível excluir o usuário.');
        }

        redirecionarComMensagem('ok', 'Usuário excluído com sucesso.');
    }
}

$erroPagina = '';
$usuarios = [];

$q = $conn->query("SELECT id, usuario, COALESCE(perfil, 'diretor') AS perfil, criado_em FROM admins ORDER BY id DESC");
if (!$q) {
    $erroPagina = "A tabela 'admins' não foi encontrada ou está inválida.";
} else {
    while ($row = $q->fetch_assoc()) {
        $usuarios[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Usuários Admin | MOTOCA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
    --bg: #0f1014;
    --card: #171922;
    --card-alt: #141722;
    --border: #2a2e3b;
    --text: #f4f6fb;
    --muted: #9aa3b2;
    --accent: #f5e500;
    --danger: #c93c3c;
    --success: #4caf70;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    color: var(--text);
    font-family: "Segoe UI", Arial, sans-serif;
    background:
        radial-gradient(1200px 500px at 120% -20%, rgba(255, 212, 0, 0.12), transparent 60%),
        linear-gradient(180deg, #0f1014 0%, #0d0f13 100%);
    padding: 24px;
}
.wrap { max-width: 1120px; margin: 0 auto; }
.header {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
}
.header h1 {
    margin: 0;
    font-size: 28px;
}
.header p {
    margin: 6px 0 0;
    color: var(--muted);
}
.topo { display: flex; gap: 10px; flex-wrap: wrap; }
.btn {
    display: inline-block;
    background: var(--accent);
    color: #111;
    text-decoration: none;
    border: 0;
    border-radius: 10px;
    padding: 10px 14px;
    font-weight: 700;
    cursor: pointer;
    transition: transform .15s ease;
}
.btn:hover { transform: translateY(-1px); }
.btn-sec {
    background: rgba(255,255,255,.05);
    color: var(--text);
    border: 1px solid var(--border);
}
.btn-del {
    background: rgba(201, 60, 60, 0.2);
    color: #ffd7d7;
    border: 1px solid rgba(201, 60, 60, 0.5);
}
.msg {
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 10px;
    border: 1px solid;
}
.msg-ok {
    color: #b5f4c8;
    background: rgba(76, 175, 112, .16);
    border-color: rgba(76, 175, 112, .45);
}
.msg-erro {
    color: #ffd1d1;
    background: rgba(201, 60, 60, .16);
    border-color: rgba(201, 60, 60, .45);
}
.grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 14px;
}
.card {
    background: linear-gradient(180deg, var(--card) 0%, var(--card-alt) 100%);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
}
.card h2 {
    margin: 0 0 12px;
    font-size: 18px;
}
label {
    display: block;
    font-size: 13px;
    color: #cfd6e3;
    margin-bottom: 6px;
    font-weight: 600;
}
input {
    width: 100%;
    background: #0f121a;
    color: var(--text);
    border: 1px solid #323748;
    border-radius: 10px;
    padding: 10px 11px;
    margin-bottom: 10px;
}
input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(255, 212, 0, 0.16);
}
.table-wrap {
    overflow: auto;
    border-radius: 10px;
    border: 1px solid #222839;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}
th, td {
    border-bottom: 1px solid #242b3d;
    padding: 10px 8px;
    text-align: left;
    vertical-align: top;
    font-size: 14px;
}
th {
    color: var(--accent);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: #151926;
    position: sticky;
    top: 0;
}
.acao-form {
    display: inline-flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.acao-form input {
    max-width: 180px;
    margin: 0;
}
.muted {
    color: var(--muted);
    font-size: 13px;
}
@media (max-width: 900px) {
    .grid { grid-template-columns: 1fr; }
    .header h1 { font-size: 24px; }
}
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div>
            <h1>Gerenciar Usuários Admin</h1>
            <p>Conta atual: <strong><?= htmlspecialchars($adminUsuarioLogado, ENT_QUOTES, 'UTF-8') ?></strong> | Perfil: <strong><?= htmlspecialchars(perfilAdminRotulo((string)($_SESSION['admin_perfil'] ?? 'diretor')), ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
        <div class="topo">
            <a class="btn" href="painel.php">Voltar ao Painel</a>
            <a class="btn btn-sec" href="logout.php">Sair</a>
        </div>
    </div>

    <?php if (!empty($_GET['ok'])): ?>
    <div class="msg msg-ok"><?= htmlspecialchars((string)$_GET['ok'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($_GET['erro'])): ?>
    <div class="msg msg-erro"><?= htmlspecialchars((string)$_GET['erro'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($erroPagina !== ''): ?>
    <div class="msg msg-erro"><?= htmlspecialchars($erroPagina, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>Criar Usuário</h2>
            <p class="muted">Crie novos acessos para o admin.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="acao" value="criar">
                <label>Usuário</label>
                <input type="text" name="usuario_novo" required>
                <label>Perfil</label>
                <select name="perfil_novo" required>
                    <?php foreach (perfisAdminDisponiveis() as $valorPerfil => $rotuloPerfil): ?>
                    <option value="<?= htmlspecialchars($valorPerfil, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rotuloPerfil, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Senha</label>
                <input type="password" name="senha_nova" required>
                <button class="btn" type="submit">Criar</button>
            </form>
        </div>

        <div class="card">
            <h2>Usuários Cadastrados</h2>
            <p class="muted">Redefina senha ou exclua usuários existentes.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuário</th>
                            <th>Perfil</th>
                            <th>Criado em</th>
                            <th>Alterar perfil</th>
                            <th>Redefinir senha</th>
                            <th>Excluir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$usuarios): ?>
                        <tr><td colspan="7">Nenhum usuário encontrado.</td></tr>
                        <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><?= htmlspecialchars((string)$u['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(perfilAdminRotulo((string)($u['perfil'] ?? 'diretor')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$u['criado_em'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <form method="POST" class="acao-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="acao" value="alterar_perfil">
                                    <input type="hidden" name="id_usuario" value="<?= (int)$u['id'] ?>">
                                    <select name="perfil_admin">
                                        <?php foreach (perfisAdminDisponiveis() as $valorPerfil => $rotuloPerfil): ?>
                                        <option value="<?= htmlspecialchars($valorPerfil, ENT_QUOTES, 'UTF-8') ?>" <?= normalizarPerfilAdmin((string)($u['perfil'] ?? 'diretor')) === $valorPerfil ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rotuloPerfil, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sec" type="submit">Salvar</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" class="acao-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="acao" value="redefinir_senha">
                                    <input type="hidden" name="id_usuario" value="<?= (int)$u['id'] ?>">
                                    <input type="password" name="nova_senha" placeholder="Nova senha" required>
                                    <button class="btn btn-sec" type="submit">Salvar</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Confirmar exclusão deste usuário?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id_usuario" value="<?= (int)$u['id'] ?>">
                                    <button class="btn btn-del" type="submit">Excluir</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>


