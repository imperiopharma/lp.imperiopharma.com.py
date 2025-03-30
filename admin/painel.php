<?php
/*******************************************************
 * painel.php
 * 
 * Exibe:
 *   - Métricas Gerais (histórico)
 *   - Métricas Diárias (dia atual)
 *   - Links para (Marcas e Produtos, Financeiro, e Lista de Pedidos)
 *   - Botão para ir diretamente à Lista de Pedidos
 *******************************************************/

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

} catch (Exception $e) {
    die("Erro ao conectar no BD: " . $e->getMessage());
}

/********************************************************
 * 1) Métricas Gerais (histórico completo)
 ********************************************************/
$totalPedidos   = 0;
$somaVendas     = 0.0; // sum final_value
$somaCusto      = 0.0; // sum cost_total
$lucroEstimado  = 0.0;
$totalProdutos  = 0;
$totalMarcas    = 0;

try {
    // Pedidos
    $stmtP = $pdo->query("
      SELECT 
        COUNT(*) AS c,
        COALESCE(SUM(final_value),0) AS sumFinal,
        COALESCE(SUM(cost_total),0)  AS sumCost
      FROM orders
    ");
    if ($rowP = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $totalPedidos  = (int)$rowP['c'];
        $somaVendas    = (float)$rowP['sumFinal'];
        $somaCusto     = (float)$rowP['sumCost'];
        $lucroEstimado = $somaVendas - $somaCusto;
    }

    // Produtos
    $stmtProd = $pdo->query("SELECT COUNT(*) AS c FROM products");
    if ($rowProd = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
        $totalProdutos = (int)$rowProd['c'];
    }

    // Marcas
    $stmtB = $pdo->query("SELECT COUNT(*) AS c FROM brands");
    if ($rowB = $stmtB->fetch(PDO::FETCH_ASSOC)) {
        $totalMarcas = (int)$rowB['c'];
    }

} catch (Exception $e) {
    // echo "Erro nas métricas gerais: ".$e->getMessage();
}

/********************************************************
 * 2) Métricas Diárias (somente hoje)
 ********************************************************/
$hojePed     = 0;
$hojeVendas  = 0.0;
$hojeCusto   = 0.0;
$hojeLucro   = 0.0;

try {
    // Pedidos do dia
    $stmtHoje = $pdo->query("
      SELECT
        COUNT(*) AS c,
        COALESCE(SUM(final_value),0) AS sumFinal,
        COALESCE(SUM(cost_total),0)  AS sumCost
      FROM orders
      WHERE DATE(created_at) = CURDATE()
    ");
    if ($rowH = $stmtHoje->fetch(PDO::FETCH_ASSOC)) {
        $hojePed    = (int)$rowH['c'];
        $hojeVendas = (float)$rowH['sumFinal'];
        $hojeCusto  = (float)$rowH['sumCost'];
        $hojeLucro  = $hojeVendas - $hojeCusto;
    }
} catch (Exception $e) {
    // echo "Erro nas métricas diárias: ".$e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <title>Painel - Métricas Gerais e Diárias</title>
  <style>
    /* RESET */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background-color: #f0f0f2;
      color: #333;
      margin: 0;
      padding: 0;
    }
    header {
      background-color: #333;
      color: #fff;
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
      padding: 20px;
      min-height: 80vh;
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
      border-radius: 6px;
    }
    h2 {
      font-size: 1.3rem;
      margin-bottom: 15px;
      color: #222;
    }
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }
    .stat-card {
      background: #fafafa;
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 12px;
      text-align: center;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .stat-card h3 {
      margin-bottom: 8px;
      font-size: 1rem;
      color: #333;
    }
    .stat-card p {
      font-size: 1.2rem;
      font-weight: bold;
      color: #007BFF;
      margin: 0;
    }
    .links-menu {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 16px;
      margin-top: 30px;
    }
    .menu-item {
      display: block;
      background: #007bff;
      color: #fff;
      text-decoration: none;
      padding: 14px;
      text-align: center;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 500;
      transition: background-color 0.3s;
    }
    .menu-item:hover {
      background-color: #0056b3;
    }
    .pedido-button {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 16px;
      background: #28a745;
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 600;
      transition: background 0.2s;
    }
    .pedido-button:hover {
      background: #218838;
    }

    footer {
      background: #333;
      color: #fff;
      text-align: center;
      padding: 14px;
      margin-top: 20px;
    }
  </style>
</head>
<body>

<header>
  <h1>Painel Administrativo</h1>
</header>

<div class="container">
  <!-- Sessão de métricas gerais -->
  <h2>Métricas Gerais (Histórico Completo)</h2>
  <div class="stats-row">
    <div class="stat-card">
      <h3>Pedidos (Total)</h3>
      <p><?= $totalPedidos ?></p>
    </div>
    <div class="stat-card">
      <h3>Receita Total</h3>
      <p>R$ <?= number_format($somaVendas,2,',','.') ?></p>
    </div>
    <div class="stat-card">
      <h3>Custo Total</h3>
      <p>R$ <?= number_format($somaCusto,2,',','.') ?></p>
    </div>
    <div class="stat-card">
      <h3>Lucro Estimado</h3>
      <p>R$ <?= number_format($lucroEstimado,2,',','.') ?></p>
    </div>
    <div class="stat-card">
      <h3>Produtos</h3>
      <p><?= $totalProdutos ?></p>
    </div>
    <div class="stat-card">
      <h3>Marcas</h3>
      <p><?= $totalMarcas ?></p>
    </div>
  </div>

  <!-- Sessão de métricas do dia -->
  <h2>Métricas do Dia (<?= date('d/m/Y') ?>)</h2>
  <div class="stats-row">
    <div class="stat-card">
      <h3>Pedidos Hoje</h3>
      <p><?= $hojePed ?></p>
    </div>
    <div class="stat-card">
      <h3>Receita Hoje</h3>
      <p>R$ <?= number_format($hojeVendas,2,',','.') ?></p>
    </div>
    <div class="stat-card">
      <h3>Custo Hoje</h3>
      <p>R$ <?= number_format($hojeCusto,2,',','.') ?></p>
    </div>
    <div class="stat-card">
      <h3>Lucro Hoje</h3>
      <p>R$ <?= number_format($hojeLucro,2,',','.') ?></p>
    </div>
  </div>

  <!-- Links para outros painéis -->
  <h2>Acesso Rápido</h2>
  <div class="links-menu">
    <a href="marcas_e_produtos.php" class="menu-item">Marcas e Produtos</a>
    <a href="painel_financeiro.php" class="menu-item">Painel Financeiro</a>
  </div>

  <!-- Botão para levar até a lista de pedidos (index.php?tab=pedidos) -->
  <a href="index.php?tab=pedidos" class="pedido-button">
    Ir para Lista de Pedidos
  </a>

</div>

<footer>
  <p>&copy; <?= date('Y') ?> - Painel Administrativo</p>
</footer>

</body>
</html>
