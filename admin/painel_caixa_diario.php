<?php
// /admin/painel_caixa_diario.php
// Painel que mostra, em tempo real, as vendas do dia, total de vendas, custo e lucro.

// CONFIG DB
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

    // Opcionalmente, forçar time_zone
    // Obs: se seu servidor já está em America/Sao_Paulo, pode omitir.
    $pdo->exec("SET time_zone='America/Sao_Paulo'");

} catch (Exception $e) {
    die("Erro BD: " . $e->getMessage());
}

// ------------------------------------------------------
// 1) Se usuário clicar em "Fechar Dia", inserimos em daily_closings
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'fecharDia') {
    // Precisamos agrupar as vendas do dia e inserir um record em daily_closings
    // Pegamos data de hoje (AAAA-MM-DD):
    $hoje = date('Y-m-d'); 

    // Checamos se já existe um fechamento para hoje
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as c FROM daily_closings WHERE closing_date=:dt");
    $stmtCheck->execute([':dt' => $hoje]);
    $rowCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if ($rowCheck && $rowCheck['c'] > 0) {
        $mensagem = "O dia de hoje ($hoje) já foi fechado anteriormente!";
    } else {
        // Buscar sum de final_value, sum de cost_total, count(*)
        // dos pedidos de hoje que não estejam cancelados
        $sqlSum = "SELECT
                     COUNT(*) as total_orders,
                     IFNULL(SUM(final_value), 0) as total_vendas,
                     IFNULL(SUM(cost_total), 0) as total_custo
                   FROM orders
                   WHERE DATE(created_at) = :dt
                     AND status != 'CANCELADO'
                     AND closed=0";
        $stmtSum = $pdo->prepare($sqlSum);
        $stmtSum->execute([':dt' => $hoje]);
        $sums = $stmtSum->fetch(PDO::FETCH_ASSOC);

        $totalOrders  = (int)$sums['total_orders'];
        $totalVendas  = (float)$sums['total_vendas'];
        $totalCusto   = (float)$sums['total_custo'];
        $totalLucro   = $totalVendas - $totalCusto;

        // Insert em daily_closings
        $sqlIns = "INSERT INTO daily_closings
                   (closing_date, total_orders, total_revenue, total_cost, total_profit, created_at)
                   VALUES
                   (:cd, :ord, :rev, :cst, :prf, NOW())";
        $stmtIns = $pdo->prepare($sqlIns);
        $stmtIns->execute([
            ':cd'  => $hoje,
            ':ord' => $totalOrders,
            ':rev' => $totalVendas,
            ':cst' => $totalCusto,
            ':prf' => $totalLucro
        ]);

        // Opcionalmente, poderíamos marcar orders de hoje como "closed=1" se quiser
        // $pdo->exec("UPDATE orders SET closed=1 WHERE DATE(created_at)='$hoje' AND status!='CANCELADO'");

        $mensagem = "Fechamento do dia $hoje realizado com sucesso!";
    }
}

// ------------------------------------------------------
// 2) Buscar pedidos de hoje para exibir (excluindo CANCELADO e closed=1, se quiser)
$hoje = date('Y-m-d');
$sqlHoje = "SELECT
              id, customer_name, final_value, cost_total,
              (final_value - cost_total) as lucro,
              status,
              created_at
            FROM orders
            WHERE DATE(created_at) = :dt
              AND status != 'CANCELADO'
              AND closed=0
            ORDER BY created_at DESC";
$stmtHoje = $pdo->prepare($sqlHoje);
$stmtHoje->execute([':dt' => $hoje]);
$listaHoje = $stmtHoje->fetchAll(PDO::FETCH_ASSOC);

// Calcula sum total, sum custo, sum lucro, count
$totalOrders  = 0;
$totalVendas  = 0;
$totalCusto   = 0;
foreach ($listaHoje as $lin) {
    $totalOrders++;
    $totalVendas += (float)$lin['final_value'];
    $totalCusto  += (float)$lin['cost_total'];
}
$totalLucro = $totalVendas - $totalCusto;

// ------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <title>Painel de Caixa Diário</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <!-- Auto-refresh a cada 30s -->
  <meta http-equiv="refresh" content="30" />
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #fafafa;
      margin: 0; 
      padding: 0;
    }
    header {
      background: #333;
      color: #fff;
      text-align: center;
      padding: 16px;
    }
    header h1 {
      margin: 0;
      font-size: 1.6rem;
    }
    .container {
      max-width: 1000px;
      margin: 20px auto;
      background: #fff;
      padding: 15px 20px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .nav {
      margin-bottom: 15px;
    }
    .nav a {
      display: inline-block;
      margin-right: 12px;
      text-decoration: none;
      color: #007BFF;
      font-weight: 500;
    }
    .mensagem {
      background: #ffe;
      border: 1px solid #eed;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-size: 0.95rem;
    }
    h2 {
      font-size: 1.2rem;
      border-bottom: 2px solid #eee;
      padding-bottom: 5px;
      margin-top: 0;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 10px;
      margin-bottom: 15px;
    }
    .stat-box {
      background: #f9f9f9;
      border: 1px solid #ddd;
      padding: 10px;
      text-align: center;
      border-radius: 6px;
    }
    .stat-box h3 {
      font-size: 1rem;
      margin-bottom: 5px;
    }
    .stat-box p {
      font-size: 1.1rem;
      color: #333;
      font-weight: bold;
    }
    form.fechar-dia-form {
      margin-bottom: 15px;
      text-align: right;
    }
    form.fechar-dia-form button {
      background: #d14836;
      color: #fff;
      border: none;
      padding: 8px 14px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9rem;
    }
    form.fechar-dia-form button:hover {
      background: #b33c2c;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px;
      font-size: 0.9rem;
    }
    th {
      background: #eee;
    }
    @media (max-width: 600px) {
      .stats {
        grid-template-columns: 1fr 1fr;
      }
      table {
        font-size: 0.8rem;
      }
    }
  </style>
</head>
<body>
<header>
  <h1>Caixa Diário (Atualizado a cada 30s)</h1>
</header>

<div class="container">
  <div class="nav">
    <a href="dashboard.php">&laquo; Dashboard</a>
  </div>

  <?php if (!empty($mensagem)): ?>
    <div class="mensagem">
      <?= htmlspecialchars($mensagem) ?>
    </div>
  <?php endif; ?>

  <h2>Resumo de Hoje (<?= date('d/m/Y') ?>)</h2>
  <div class="stats">
    <div class="stat-box">
      <h3>Pedidos (QTD)</h3>
      <p><?= $totalOrders ?></p>
    </div>
    <div class="stat-box">
      <h3>Vendas (R$)</h3>
      <p><?= number_format($totalVendas, 2, ',', '.') ?></p>
    </div>
    <div class="stat-box">
      <h3>Custo (R$)</h3>
      <p><?= number_format($totalCusto, 2, ',', '.') ?></p>
    </div>
    <div class="stat-box">
      <h3>Lucro (R$)</h3>
      <p><?= number_format($totalLucro, 2, ',', '.') ?></p>
    </div>
  </div>

  <form method="POST" class="fechar-dia-form" onsubmit="return confirm('Tem certeza que deseja fechar o dia? Não será mais editável.');">
    <input type="hidden" name="acao" value="fecharDia" />
    <button type="submit">Fechar Dia (<?= date('d/m/Y') ?>)</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Cliente</th>
        <th>Status</th>
        <th>Data/Hora</th>
        <th>Venda (R$)</th>
        <th>Custo (R$)</th>
        <th>Lucro (R$)</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($listaHoje && count($listaHoje)>0): ?>
      <?php foreach ($listaHoje as $pedido): ?>
      <tr>
        <td><?= $pedido['id'] ?></td>
        <td><?= htmlspecialchars($pedido['customer_name']) ?></td>
        <td><?= htmlspecialchars($pedido['status']) ?></td>
        <td><?= date('d/m/Y H:i:s', strtotime($pedido['created_at'])) ?></td>
        <td><?= number_format($pedido['final_value'],2,',','.') ?></td>
        <td><?= number_format($pedido['cost_total'],2,',','.') ?></td>
        <td><?= number_format($pedido['lucro'],2,',','.') ?></td>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="7" style="text-align:center;">Nenhum pedido registrado hoje.</td>
      </tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
