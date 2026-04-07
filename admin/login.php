<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login Administrativo | MOTOCA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body class="bg-admin">

<div class="login-box">
    <img src="../assets/img/logo-site-3d.png" class="login-logo" alt="Motoca">

    <h2>Login</h2>

    <form method="POST" action="autenticar.php">
        <input type="text" name="usuario" placeholder="Usuário" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit">Entrar</button>
    </form>
</div>

</body>
</html>
