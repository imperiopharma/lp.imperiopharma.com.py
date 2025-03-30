<?php
/**
 * painel_vendas.php
 *
 * Exibe a lista de daily_closings com opção de filtrar datas.
 */

// CONFIG
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

    // Filtro de data:
    $hoje = date('Y-m-d');
    $dataInicial = isset($_GET['di']) ? $_GET['di'] : date('Y-m-01');
    $dataFinal   = isset($_GET['df']) ? $_GET['df'] : $hoje;

    // Traz daily_closings dentro do range:
    $sql = "SELECT * 
            FROM daily_closings
            WHERE closing_date BETWEEN :start AND :end
            ORDER BY closing_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $dataInicial, ':end' => $dataFinal]);
    $fechamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resumo:
    $sumOrders  = 0;
    $sumRevenue = 0.0;
    $sumCost    = 0.0;
    $sumProfit  = 0.0;

    foreach ($fechamentos as $fc) {
        $sumOrders  += (int)$fc['total_orders'];
        $sumRevenue += (float)$fc['total_revenue'];
        $sumCost    += (float)$fc['total_cost'];
        $sumProfit  += (float)$fc['total_profit'];
    }

} catch (Exception $e) {
    die("Erro no Painel de Vendas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Painel de Vendas</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      max-width: 1200px;
      margin: 10px auto;
      background: #f7f7f9;
      padding: 10px;
    }
    h1 {
      font-size: 1.6rem;
      border-bottom: 2px solid #ccc;
      padding-bottom: 5px;
    }
    form {
      background: #fff;
      padding: 10px;
      border: 1px solid #ddd;
      margin-bottom: 15px;
      border-radius: 6px;
    }
    .form-filtro label {
      margin-right: 10px;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      background: #fff;
      margin-bottom: 15px;
      border: 1px solid #ddd;
    }
    th, td {
      border: 1px solid #ddd;
      padding: 8px;
      font-size: 0.9rem;
    }
    th {
      background-color: #f0f0f2;
    }
    .resumo-bloco {
      background: #fff;
      border: 1px solid #ddd;
      padding: 10px;
      border-radius: 6px;
    }
    .resumo-item {
      margin: 5px 0;
    }
    .btn-filtro {
      padding: 8px 14px;
      border: none;
      background: #333;
      color: #fff;
      cursor: pointer;
      border-radius: 4px;
    }
    .btn-filtro:hover {
      background: #222;
    }
  </style>
</head>
<body>

<h1>Painel de Vendas (Fechamento Diário)</h1>

<form method="GET" class="form-filtro">
  <label>Data Inicial:
    <input type="date" name="di" value="<?= htmlspecialchars($dataInicial) ?>">
  </label>
  <label>Data Final:
    <input type="date" name="df" value="<?= htmlspecialchars($dataFinal) ?>">
  </label>
  <button type="submit" class="btn-filtro">Filtrar</button>
</form>

<table>
  <tr>
    <th>Data</th>
    <th>#Pedidos</th>
    <th>Receita</th>
    <th>Custo</th>
    <th>Lucro</th>
    <th>Criado em</th>
    <th>Atualizado em</th>
  </tr>
  <?php foreach ($fechamentos as $fc): ?>
    <tr>
      <td><?= htmlspecialchars($fc['closing_date']) ?></td>
      <td><?= (int)$fc['total_orders'] ?></td>
      <td>R$ <?= number_format($fc['total_revenue'],2,',','.') ?></td>
      <td>R$ <?= number_format($fc['total_cost'],2,',','.') ?></td>
      <td>R$ <?= number_format($fc['total_profit'],2,',','.') ?></td>
      <td><?= $fc['created_at'] ?></td>
      <td><?= $fc['updated_at'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="resumo-bloco">
  <h2>Totais do Período:</h2>
  <p class="resumo-item">
    <strong>Pedidos:</strong> <?= $sumOrders ?>
  </p>
  <p class="resumo-item">
    <strong>Receita:</strong> R$ <?= number_format($sumRevenue,2,',','.') ?>
  </p>
  <p class="resumo-item">
    <strong>Custo:</strong> R$ <?= number_format($sumCost,2,',','.') ?>
  </p>
  <p class="resumo-item">
    <strong>Lucro:</strong> R$ <?= number_format($sumProfit,2,',','.') ?>
  </p>
</div>

<p>
  <a href="gerarFechamento.php" style="text-decoration:none; color:#444;">
    Gerar Fechamento de Hoje
  </a>
</p>

</body>
</html>
