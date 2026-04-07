<?php
session_start();
$sucesso = $_SESSION['sinistro_sucesso'] ?? null;
unset($_SESSION['sinistro_sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Sinistro registrado com sucesso</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --motoca-yellow: #f5e500;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top, rgba(245, 229, 0, 0.12), transparent 28%),
                linear-gradient(180deg, #050505 0%, #000 60%, #080808 100%);
            font-family: "Segoe UI", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            padding: 20px;
        }

        .success-container {
            max-width: 620px;
            width: 100%;
            padding: 42px 30px;
            text-align: center;
            background: #0d0d0d;
            border: 2px solid rgba(245, 229, 0, 0.65);
            border-radius: 28px;
            box-shadow: 0 24px 45px rgba(0, 0, 0, .6);
        }

        h1 {
            color: var(--motoca-yellow);
            font-size: 28px;
            margin-bottom: 14px;
        }

        .descricao {
            font-size: 15px;
            line-height: 1.7;
            color: #ddd;
            margin-bottom: 24px;
        }

        .protocolo {
            margin-bottom: 24px;
            padding: 22px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(245, 229, 0, .16) 0%, rgba(245, 229, 0, .08) 100%);
            border: 1px solid rgba(245, 229, 0, .45);
        }

        .protocolo strong {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            color: #fff6b0;
            text-transform: uppercase;
            letter-spacing: .12em;
        }

        .protocolo span {
            display: block;
            color: var(--motoca-yellow);
            font-size: 32px;
            font-weight: 700;
            letter-spacing: .08em;
        }

        .proximos-passos {
            margin-bottom: 26px;
            padding: 20px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, .09);
            background: rgba(255, 255, 255, .03);
            text-align: left;
        }

        .proximos-passos h2 {
            font-size: 18px;
            color: var(--motoca-yellow);
            margin-bottom: 12px;
        }

        .proximos-passos ul {
            margin: 0;
            padding-left: 18px;
            color: #dcdcdc;
        }

        .proximos-passos li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .proximos-passos li:last-child {
            margin-bottom: 0;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 30px;
            border: 2px solid var(--motoca-yellow);
            color: var(--motoca-yellow);
            text-decoration: none;
            font-weight: 700;
            transition: .3s;
        }

        .btn:hover {
            background: var(--motoca-yellow);
            color: #000;
        }

        .btn-secondary {
            border-color: rgba(255, 255, 255, .25);
            color: #fff;
        }

        .btn-secondary:hover {
            border-color: var(--motoca-yellow);
            background: rgba(245, 229, 0, .12);
            color: var(--motoca-yellow);
        }
    </style>
</head>
<body>
    <div class="success-container">
        <img src="assets/img/logo-site-3d.png" alt="Motoca" style="display:block;width:250px;max-width:100%;margin:0 auto 18px;filter:brightness(1.08) contrast(1.04) saturate(1.45) hue-rotate(3deg) drop-shadow(0 6px 14px rgba(245, 229, 0, .16));">
        <div style="display:inline-block;margin-bottom:14px;padding:7px 12px;border-radius:999px;background:rgba(245,229,0,.12);border:1px solid rgba(245,229,0,.24);color:#f5e500;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Envio concluído</div>
        <h1>Sinistro registrado com sucesso!</h1>

        <p class="descricao">
            Recebemos suas informações e o registro já foi encaminhado para análise da equipe responsável.
        </p>

        <p class="descricao" style="font-size: 13px; color: #bdbdbd; margin-top: -10px;">
            Guarde o protocolo abaixo. Ele confirma que o formulário foi enviado com sucesso.
        </p>

        <?php if (is_array($sucesso) && !empty($sucesso['numero_registro'])): ?>
            <div class="protocolo">
                <strong>Protocolo do sinistro</strong>
                <span><?= htmlspecialchars((string)$sucesso['numero_registro'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <div class="proximos-passos">
            <h2>Próximos passos</h2>
            <ul>
                <li>Seu envio foi registrado e vinculado ao protocolo acima.</li>
                <li>Nossa equipe fará a análise das informações e dos anexos enviados.</li>
                <li>Se necessário, entraremos em contato pelos dados informados no registro.</li>
            </ul>
        </div>

        <div class="actions">
            <a href="index.php" class="btn">Voltar para o início</a>
        </div>
    </div>
</body>
</html>

