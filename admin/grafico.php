<?php
session_start();
require "../config/admin_session.php";
adminSessionInit();
if (!isset($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

require "../config/db.php";

$andamento = $conn->query(
    "SELECT COUNT(*) total FROM sinistros WHERE status='em_andamento'"
)->fetch_assoc()['total'];

$finalizado = $conn->query(
    "SELECT COUNT(*) total FROM sinistros WHERE status='finalizado'"
)->fetch_assoc()['total'];

$juridico = $conn->query(
    "SELECT COUNT(*) total FROM sinistros WHERE status='processo_juridico'"
)->fetch_assoc()['total'];

$arquivado = $conn->query(
    "SELECT COUNT(*) total FROM sinistros WHERE status='arquivado'"
)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Status dos Sinistros | MOTOCA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/admin.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="admin-page">

<div class="container">

    <img src="../assets/img/logo-site-3d.png" class="logo logo-site" alt="Motoca">

    <h1>Status dos Sinistros</h1>

    <div class="grafico-container">
        <canvas id="graficoStatus"></canvas>
    </div>

    <div style="text-align:center; margin-top:20px;">
        <a href="painel.php" style="
            color:#FFFF00;
            text-decoration:none;
            font-weight:600;
        ">
            &larr; Voltar ao painel
        </a>
    </div>

</div>

<script>
const ctx = document.getElementById('graficoStatus');

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: [
            'Em andamento',
            'Finalizado',
            'Processo Jurídico',
            'Arquivado'
        ],
        datasets: [{
            data: [
                <?= $andamento ?>,
                <?= $finalizado ?>,
                <?= $juridico ?>,
                <?= $arquivado ?>
            ],
            backgroundColor: [
                '#FFD400',
                '#2ecc71',
                '#ff7043',
                '#9aa0a6'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    color: '#fff',
                    padding: 14,
                    boxWidth: 14
                }
            }
        },
        onClick: (evt, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const statusMap = [
                    'em_andamento',
                    'finalizado',
                    'processo_juridico',
                    'arquivado'
                ];
                window.location.href =
                    `painel.php?status=${statusMap[index]}`;
            }
        }
    }
});
</script>

</body>
</html>



