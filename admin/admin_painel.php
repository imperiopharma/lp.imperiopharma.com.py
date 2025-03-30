<?php
/**************************************************************
 * admin_painel.php
 * 
 * Página principal do painel de administração.
 * - Exibe métricas gerais (histórico) e do dia (diárias)
 * - Links para Marcas e Produtos, Pedidos, Financeiro, etc.
 **************************************************************/

// ======== CONFIGURAÇÃO BD ========
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

// ======== CALCULAR MÉTRICAS GERAIS (histórico) ========
$totalPedidos  = 0;
$somaVendas    = 0.0;
$somaCusto     = 0.0;
$lucroEstimado = 0.0;
$totalProdutos = 0;
$totalMarcas   = 0;

try {
    // 1) Pedidos (histórico)
    $stmtP = $pdo->query("
        SELECT 
          COUNT(*) AS c,
          COALESCE(SUM(final_value),0) AS sumFinal,
          COALESCE(SUM(cost_total),0)  AS sumCost
        FROM orders
    ");
    $rowP = $stmtP->fetch(PDO::FETCH_ASSOC);
    if ($rowP) {
        $totalPedidos  = (int)$rowP['c'];
        $somaVendas    = (float)$rowP['sumFinal'];
        $somaCusto     = (float)$rowP['sumCost'];
        $lucroEstimado = $somaVendas - $somaCusto;
    }

    // 2) Produtos
    $stmtProd = $pdo->query("SELECT COUNT(*) AS c FROM products");
    $rowProd  = $stmtProd->fetch(PDO::FETCH_ASSOC);
    if ($rowProd) {
        $totalProdutos = (int)$rowProd['c'];
    }

    // 3) Marcas
    $stmtB = $pdo->query("SELECT COUNT(*) AS c FROM brands");
    $rowB  = $stmtB->fetch(PDO::FETCH_ASSOC);
    if ($rowB) {
        $totalMarcas = (int)$rowB['c'];
    }

} catch (Exception $e) {
    // Se falhar, deixa zeros mesmo.
}

// ======== CALCULAR MÉTRICAS DIÁRIAS (hoje) ========
$hojePed    = 0;
$hojeVendas = 0.0;
$hojeCusto  = 0.0;
$hojeLucro  = 0.0;

try {
    $stmtHoje = $pdo->query("
        SELECT
          COUNT(*) AS c,
          COALESCE(SUM(final_value),0) AS sumFinal,
          COALESCE(SUM(cost_total),0)  AS sumCost
        FROM orders
        WHERE DATE(created_at) = CURDATE()
    ");
    $rowH = $stmtHoje->fetch(PDO::FETCH_ASSOC);
    if ($rowH) {
        $hojePed    = (int)$rowH['c'];
        $hojeVendas = (float)$rowH['sumFinal'];
        $hojeCusto  = (float)$rowH['sumCost'];
        $hojeLucro  = $hojeVendas - $hojeCusto;
    }

} catch (Exception $e) {
    // Em caso de erro, deixa zero
}

// ======== CAPTURAR “ABA” VIA GET ?tab= (opcional) ========
// Exemplo: se você quiser trocar o conteúdo principal
$aba = $_GET['tab'] ?? 'dashboard'; 
// “dashboard” será a default
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <title>Admin Painel - Império Pharma</title>
  <style>
    /* Reset básico */
    * {
      margin: 0; padding: 0; box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background: #f7f8fc;
      color: #333;
      margin: 0;
      padding: 0;
    }

    header {
      background: #333;
      color: #fff;
      padding: 16px;
    }
    header h1 {
      font-size: 1.6rem;
      margin: 0;
    }

    nav {
      background: #444;
      padding: 8px;
    }
    nav a {
      color: #fff;
      text-decoration: none;
      margin-right: 12px;
      font-weight: 500;
      transition: background 0.3s;
      padding: 6px 8px;
      border-radius: 4px;
    }
    nav a:hover {
      background: #666;
    }

    .container {
      max-width: 1100px;
      margin: 20px auto;
      background: #fff;
      padding: 20px;
      border-radius: 6px;
      min-height: 70vh;
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    }

    h2 {
      font-size: 1.3rem;
      margin-bottom: 12px;
      color: #333;
      border-bottom: 1px solid #ddd;
      padding-bottom: 6px;
    }
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px,1fr));
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

    .section-links {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
      gap: 16px;
    }
    .section-links a {
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
    .section-links a:hover {
      background-color: #0056b3;
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
  <h1>Admin - Império Pharma</h1>
</header>

<!-- Menu Superior (simples) -->
<nav>
  <a href="?tab=dashboard">Dashboard</a>
  <a href="?tab=pedidos">Pedidos</a>
  <a href="?tab=marcas">Marcas & Produtos</a>
  <a href="?tab=financeiro">Financeiro</a>
</nav>

<div class="container">

<?php
// Decidir qual “seção” mostrar, dependendo de ?tab=
switch ($aba) {

  // 1) Dashboard (métricas gerais e do dia)
  case 'dashboard':
  default:
    ?>
    <h2>Dashboard - Métricas Gerais</h2>

    <!-- Métricas Históricas -->
    <div class="stats-row">
      <div class="stat-card">
        <h3>Pedidos (Total)</h3>
        <p><?= $totalPedidos ?></p>
      </div>
      <div class="stat-card">
        <h3>Receita Total</h3>
        <p>R$ <?= number_format($somaVendas, 2, ',', '.') ?></p>
      </div>
      <div class="stat-card">
        <h3>Custo Total</h3>
        <p>R$ <?= number_format($somaCusto, 2, ',', '.') ?></p>
      </div>
      <div class="stat-card">
        <h3>Lucro Estimado</h3>
        <p>R$ <?= number_format($lucroEstimado, 2, ',', '.') ?></p>
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

    <h2>Métricas do Dia (<?= date('d/m/Y') ?>)</h2>
    <div class="stats-row">
      <div class="stat-card">
        <h3>Pedidos Hoje</h3>
        <p><?= $hojePed ?></p>
      </div>
      <div class="stat-card">
        <h3>Receita Hoje</h3>
        <p>R$ <?= number_format($hojeVendas, 2, ',', '.') ?></p>
      </div>
      <div class="stat-card">
        <h3>Custo Hoje</h3>
        <p>R$ <?= number_format($hojeCusto, 2, ',', '.') ?></p>
      </div>
      <div class="stat-card">
        <h3>Lucro Hoje</h3>
        <p>R$ <?= number_format($hojeLucro, 2, ',', '.') ?></p>
      </div>
    </div>

    <h2>Acesso Rápido</h2>
    <div class="section-links">
      <a href="?tab=pedidos">Ver Pedidos</a>
      <a href="?tab=marcas">Marcas & Produtos</a>
      <a href="?tab=financeiro">Painel Financeiro</a>
      <!-- etc. -->
    </div>

    <?php
  break;


  // 2) Pedidos
  case 'pedidos':
    // Você pode incluir um arquivo tipo “pedido_list.php”
    // ou simplesmente exibir a lógica aqui:
    include __DIR__ . '/index.php'; 
    // (caso seu index.php seja a “Lista de Pedidos”)
  break;


  // 3) Marcas & Produtos
  case 'marcas':
    // Você pode usar o “marcas_e_produtos.php” unificado:
    include __DIR__ . '/marcas_e_produtos.php';
  break;


  // 4) Painel Financeiro
  case 'financeiro':
    // Exemplo: Incluir “painel_financeiro.php”
    include __DIR__ . '/painel_financeiro.php';
  break;
}
?>

</div> <!-- /container -->

<footer>
  <p>&copy; <?= date('Y') ?> - Painel Administrativo Império Pharma</p>
</footer>

</body>
</html>
