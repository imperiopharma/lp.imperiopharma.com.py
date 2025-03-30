<?php
// /admin/historico_caixa.php

session_start();
// Se tiver cabeçalho: include 'admin_header.php';

$dbHost = 'localhost';
$dbName = 'imperiopharma_loja_db';
$dbUser = 'imperiopharma_loja_user';
$dbPass = 'Miguel22446688';

try {
    $pdo = new PDO(
      "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
      $dbUser,
      $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
    die("Erro BD: " . $e->getMessage());
}

// Carrega os fechamentos
$stmt = $pdo->query("
  SELECT 
    id,
    fechamento_date,
    DATE_FORMAT(fechamento_datetime, '%d/%m/%Y %H:%i') AS fechado_em,
    pedidos, receita, custo, lucro,
    obs
  FROM daily_closures
  ORDER BY fechamento_date DESC, id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <title>Histórico de Fechamentos</title>
  <style>
    body { font-family:Arial,sans-serif; max-width:1000px; margin:0 auto; padding:10px; background:#f7f7f7; }
    h1 { font-size:1.4rem; margin-bottom:15px; }
    table { border-collapse:collapse; width:100%; margin-top:10px; }
    th, td { border:1px solid #ccc; padding:8px; }
    th { background:#eee; }
    .nav-top a {
      margin-right:10px; padding:6px 10px; background:#555; color:#fff; border-radius:4px;
      text-decoration:none;
    }
    .nav-top a:hover { background:#333; }
  </style>
</head>
<body>

<div class="nav-top">
  <a href="dashboard.php">&laquo; Dashboard</a>
  <a href="movimentacao_diaria.php">Movimentação de Hoje</a>
</div>

<h1>Histórico de Fechamentos Diários</h1>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Data (Fechamento)</th>
      <th>Horário</th>
      <th>Pedidos</th>
      <th>Receita (R$)</th>
      <th>Custo (R$)</th>
      <th>Lucro (R$)</th>
      <th>Obs</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="8" style="text-align:center;">Nenhum fechamento encontrado.</td></tr>
  <?php else: ?>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= $r['fechamento_date'] ?></td>
        <td><?= $r['fechado_em'] ?></td>
        <td><?= $r['pedidos'] ?></td>
        <td><?= number_format($r['receita'],2,',','.') ?></td>
        <td><?= number_format($r['custo'],2,',','.') ?></td>
        <td><?= number_format($r['lucro'],2,',','.') ?></td>
        <td><?= htmlspecialchars($r['obs']) ?></td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>

</body>
</html>
