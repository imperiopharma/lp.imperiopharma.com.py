<?php
// /admin/dashboard.php

$dbHost = 'localhost';
$dbName = 'imperiopharma_loja_db';
$dbUser = 'imperiopharma_loja_user';
$dbPass = 'Miguel22446688';

$totalPedidos     = 0;
$totalProdutos    = 0;
$totalMarcas      = 0;
$totalFechamentos = 0;

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Se quiser forçar horário do Brasil:
    // date_default_timezone_set('America/Sao_Paulo');
    // ou: $pdo->exec("SET time_zone='-03:00'"); // depende da config do servidor

    // Exemplo de contagem de pedidos
    $stmtP = $pdo->query("SELECT COUNT(*) AS c FROM orders");
    if ($rowP = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $totalPedidos = (int)$rowP['c'];
    }

    // Contagem de produtos
    $stmtProd = $pdo->query("SELECT COUNT(*) AS c FROM products");
    if ($rowProd = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
        $totalProdutos = (int)$rowProd['c'];
    }

    // Contagem de marcas
    $stmtB = $pdo->query("SELECT COUNT(*) AS c FROM brands");
    if ($rowB = $stmtB->fetch(PDO::FETCH_ASSOC)) {
        $totalMarcas = (int)$rowB['c'];
    }

    // Contagem de daily_closings (fechamentos)
    $stmtF = $pdo->query("SELECT COUNT(*) AS c FROM daily_closings");
    if ($rowF = $stmtF->fetch(PDO::FETCH_ASSOC)) {
        $totalFechamentos = (int)$rowF['c'];
    }

} catch (Exception $e) {
    // Em caso de erro, podemos manter zero ou exibir algo:
    // echo "Erro BD: ", $e->getMessage();
    // ou apenas seguir...
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <title>Admin - Painel de Controle</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    /* Reset básico */
    * {
      margin: 0; 
      padding: 0; 
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background: #f0f0f0;
      margin: 0;
      padding: 0;
    }

    /* Barra de navegação fixa no topo */
    .top-nav {
      position: fixed;
      top: 0; 
      left: 0; 
      right: 0;
      background-color: #333;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 20px;
      z-index: 999;
    }
    .top-nav h1 {
      font-size: 1.6rem;
      margin: 0;
    }
    .top-nav a {
      text-decoration: none;
      color: #fff;
      font-size: 0.95rem;
      padding: 6px 10px;
      border-radius: 4px;
      transition: background-color 0.3s;
    }
    .top-nav a:hover {
      background-color: #444;
    }

    /* Container principal do dashboard */
    .dashboard-container {
      max-width: 960px;
      margin: 80px auto 30px auto; /* 80px para compensar header fixo */
      padding: 10px;
    }

    /* Estatísticas rápidas (cards) */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 10px;
      margin-bottom: 20px;
    }
    .stat-card {
      background: #fff;
      border-radius: 6px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
      padding: 16px;
      text-align: center;
      transition: transform 0.2s;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 3px 9px rgba(0,0,0,0.2);
    }
    .stat-card h3 {
      font-size: 1.1rem;
      margin-bottom: 8px;
      color: #333;
    }
    .stat-card p {
      font-size: 1.4rem;
      font-weight: bold;
      color: #007BFF;
      margin: 0;
    }

    /* Menu principal de botões */
    .admin-menu {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 16px;
    }
    .menu-item {
      text-decoration: none;
      background-color: #007BFF;
      color: #fff;
      padding: 14px 16px;
      font-size: 1rem;
      font-weight: 500;
      text-align: center;
      border-radius: 6px;
      transition: background-color 0.3s;
    }
    .menu-item:hover {
      background-color: #0056b3;
    }

    /* Rodapé */
    footer {
      text-align: center;
      margin: 20px 0;
      color: #777;
      font-size: 0.85rem;
    }

    /* Responsividade */
    @media (max-width: 600px) {
      .admin-menu {
        grid-template-columns: 1fr;
      }
      .stats-row {
        grid-template-columns: 1fr;
      }
      .top-nav h1 {
        font-size: 1.3rem;
      }
    }
  </style>
</head>
<body>

<!-- Barra de navegação fixa -->
<div class="top-nav">
  <h1>Painel de Administração</h1>
  <!-- Links ou botões de acesso rápido -->
  <div style="display: flex; gap: 8px;">
    <a href="dashboard.php">Início</a>
    <a href="index.php">Pedidos</a>
    <a href="painel_caixa_diario.php">Caixa Diário</a>
  </div>
</div>

<div class="dashboard-container">
  <!-- Cards de estatísticas rápidas -->
  <div class="stats-row">
    <div class="stat-card">
      <h3>Pedidos</h3>
      <p><?= $totalPedidos ?></p>
    </div>
    <div class="stat-card">
      <h3>Produtos</h3>
      <p><?= $totalProdutos ?></p>
    </div>
    <div class="stat-card">
      <h3>Marcas</h3>
      <p><?= $totalMarcas ?></p>
    </div>
    <div class="stat-card">
      <h3>Fechamentos</h3>
      <p><?= $totalFechamentos ?></p>
    </div>
  </div>

  <!-- Menu principal de navegação -->
  <div class="admin-menu">
    <!-- Ajuste conforme seus arquivos existentes -->
    <a class="menu-item" href="index.php">Lista de Pedidos</a>
    <a class="menu-item" href="marcas.php">Gerenciar Marcas</a>
    <a class="menu-item" href="produtos.php">Gerenciar Produtos</a>

    <a class="menu-item" href="painel_caixa_diario.php">Caixa Diário (Atual)</a>
    <a class="menu-item" href="painel_vendas.php">Painel de Vendas (Fechamentos)</a>
    <a class="menu-item" href="gerarFechamento.php">Gerar Fechamento Manual</a>
    <a class="menu-item" href="movimentacao_diaria.php">Movimentação Diária</a>
    <a class="menu-item" href="historico_caixa.php">Histórico de Caixa</a>

    <a class="menu-item" href="rastreio_inserir_em_massa.php">Rastreio: Inserir em Massa</a>
    <a class="menu-item" href="rastreio_pendentes.php">Rastreio: Pendentes</a>
    <a class="menu-item" href="rastreio_enviar_email.php">Rastreio: Enviar E-mail</a>
    <a class="menu-item" href="mensagem_templates.php">Templates de Mensagem</a>
  </div>
</div>

<footer>
  <p>&copy; <?= date('Y') ?> - Painel de Controle Império Pharma</p>
</footer>

</body>
</html>
