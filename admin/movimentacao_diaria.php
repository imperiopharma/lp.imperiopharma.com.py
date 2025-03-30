<?php
// /admin/movimentacao_diaria.php

$dbHost = 'localhost';
$dbName = 'imperiopharma_loja_db';
$dbUser = 'imperiopharma_loja_user';
$dbPass = 'Miguel22446688';

date_default_timezone_set('America/Sao_Paulo'); // Para exibir datas/hora em pt-BR

// Filtro: por padrão, traz o dia atual se nada for passado
$dataFiltro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

// Conexão PDO
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Preparar “início do dia” e “fim do dia”
    $dataIni = $dataFiltro . ' 00:00:00';
    $dataFim = $dataFiltro . ' 23:59:59';

    // Buscar pedidos desse dia
    // assumindo que a tabela orders.created_at seja datetime
    $sql = "SELECT id,
                   created_at,
                   final_value,
                   cost_total,
                   (final_value - cost_total) AS lucro,
                   customer_name,
                   status
            FROM orders
            WHERE created_at BETWEEN :ini AND :fim
            ORDER BY created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ini' => $dataIni,
        ':fim' => $dataFim
    ]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular métricas
    $totalPedidos = 0;
    $somaReceita  = 0.0;
    $somaCusto    = 0.0;
    $somaLucro    = 0.0;

    foreach ($pedidos as $p) {
        $totalPedidos++;
        $somaReceita += floatval($p['final_value']);
        $somaCusto   += floatval($p['cost_total']);
        $somaLucro   += floatval($p['lucro']);
    }

} catch (Exception $e) {
    die("Erro no banco: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <title>Movimentação Diária</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    /* RESET BÁSICO */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background: #f3f3f8;
      margin: 0;
      padding: 0;
    }
    /* Top Nav (fixa) */
    .top-nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      background-color: #333;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 20px;
      z-index: 999;
    }
    .top-nav h1 {
      font-size: 1.4rem;
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

    .container {
      max-width: 950px;
      margin: 70px auto 20px auto; /* Compensa espaço do header fixo */
      padding: 10px 15px;
    }

    .section-block {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 15px;
      margin-bottom: 20px;
    }
    .section-block h2 {
      font-size: 1.2rem;
      margin-bottom: 10px;
      border-bottom: 1px solid #eee;
      padding-bottom: 5px;
    }

    /* Filtro de data + botão */
    .filtro-data {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 15px;
      flex-wrap: wrap;
    }
    .filtro-data label {
      font-weight: 500;
    }
    .filtro-data input[type="date"] {
      padding: 5px;
      font-size: 0.95rem;
    }
    .filtro-data button {
      background: #007BFF;
      border: none;
      color: #fff;
      padding: 6px 12px;
      font-size: 0.9rem;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .filtro-data button:hover {
      background: #0056b3;
    }

    /* Cartão de métricas (totais do dia) */
    .totais-periodo {
      background: #fefefe;
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 10px;
      margin-bottom: 10px;
      font-size: 0.95rem;
    }
    .totais-periodo h3 {
      margin-bottom: 8px;
      font-size: 1rem;
      color: #333;
    }
    .totais-periodo p {
      margin-bottom: 4px;
      color: #555;
    }
    .btn-fechar-dia {
      display: inline-block;
      margin-top: 8px;
      padding: 8px 14px;
      background: #444;
      color: #fff;
      text-decoration: none;
      border-radius: 4px;
      transition: background 0.2s;
    }
    .btn-fechar-dia:hover {
      background: #222;
    }

    /* Tabela de pedidos */
    table {
      width: 100%;
      border-collapse: collapse;
    }
    table thead th {
      background: #eee;
      border: 1px solid #ccc;
      padding: 8px;
      font-size: 0.9rem;
      text-align: left;
    }
    table tbody td {
      border: 1px solid #ccc;
      padding: 8px;
      font-size: 0.9rem;
    }
    table tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    .status-pill {
      display: inline-block;
      padding: 3px 6px;
      border-radius: 4px;
      color: #fff;
      font-size: 0.8rem;
    }
    .status-pendente {
      background: #d9534f;
    }
    .status-pago {
      background: #5cb85c;
    }
    .status-enviado {
      background: #5bc0de;
    }
    .status-concluido {
      background: #0275d8;
    }
    .status-cancelado {
      background: #999;
    }

    /* Responsivo */
    @media (max-width: 600px) {
      .filtro-data {
        flex-direction: column;
        align-items: flex-start;
      }
      .filtro-data button {
        margin-top: 8px;
      }
    }
  </style>
</head>
<body>

<!-- Barra de navegação superior (fixa) -->
<div class="top-nav">
  <h1>Movimentação Diária</h1>
  <div style="display:flex; gap:8px;">
    <a href="dashboard.php">Dashboard</a>
    <a href="index.php">Pedidos</a>
    <a href="painel_vendas.php">Fechamentos</a>
  </div>
</div>

<div class="container">

  <div class="section-block">
    <h2>Movimentação Diária</h2>

    <!-- Formulário de filtro de data -->
    <form class="filtro-data" method="GET" onsubmit="return filtrarData()">
      <label for="data">Selecione a Data:</label>
      <input type="date" name="data" id="data" value="<?= htmlspecialchars($dataFiltro) ?>">
      <button type="submit">Filtrar</button>
    </form>

    <!-- Cartão de Totais do Dia -->
    <div class="totais-periodo" id="divTotais">
      <h3>Totais do Dia (<?= date('d/m/Y', strtotime($dataFiltro)) ?>):</h3>
      <p>Pedidos: <strong><?= $totalPedidos ?></strong></p>
      <p>Receita (Final Value): <strong>R$ <?= number_format($somaReceita, 2, ',', '.') ?></strong></p>
      <p>Custo: <strong>R$ <?= number_format($somaCusto, 2, ',', '.') ?></strong></p>
      <p>Lucro: <strong>R$ <?= number_format($somaLucro, 2, ',', '.') ?></strong></p>

      <!-- Link/btn para gerar fechamento do dia -->
      <a href="gerarFechamento.php?data=<?= urlencode($dataFiltro) ?>"
         class="btn-fechar-dia"
         title="Gerar fechamento do dia selecionado">
         Gerar Fechamento de Hoje
      </a>
    </div>

    <!-- Tabela de Pedidos do Dia -->
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Data/Hora</th>
          <th>Cliente</th>
          <th>Status</th>
          <th>Receita</th>
          <th>Custo</th>
          <th>Lucro</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tbodyPedidos">
        <?php if (count($pedidos) === 0): ?>
          <tr>
            <td colspan="8" style="text-align:center;">Nenhum pedido encontrado neste dia.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($pedidos as $ped): ?>
            <?php
              $dt = date('d/m/Y H:i:s', strtotime($ped['created_at']));
              $st = strtolower($ped['status']);
              $classStatus = 'status-pill';
              if ($st === 'pendente')   $classStatus .= ' status-pendente';
              if ($st === 'pago')       $classStatus .= ' status-pago';
              if ($st === 'enviado')    $classStatus .= ' status-enviado';
              if ($st === 'concluido')  $classStatus .= ' status-concluido';
              if ($st === 'cancelado')  $classStatus .= ' status-cancelado';
            ?>
            <tr>
              <td><?= $ped['id'] ?></td>
              <td><?= $dt ?></td>
              <td><?= htmlspecialchars($ped['customer_name']) ?></td>
              <td><span class="<?= $classStatus ?>"><?= htmlspecialchars($ped['status']) ?></span></td>
              <td>R$ <?= number_format($ped['final_value'], 2, ',', '.') ?></td>
              <td>R$ <?= number_format($ped['cost_total'], 2, ',', '.') ?></td>
              <td>R$ <?= number_format($ped['lucro'], 2, ',', '.') ?></td>
              <td>
                <a href="pedido_detalhe.php?id=<?= $ped['id'] ?>"
                   style="text-decoration:none; color: #007BFF;">
                  Ver Detalhes
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Ao submeter o formulário, apenas deixamos rolar (GET).
function filtrarData() {
  // se quiser fazer validações:
  // ex.: if (!document.getElementById('data').value) { ... }
  return true;
}

// Atualização automática a cada 30s
setInterval(() => {
  // Recarrega a página com os mesmos parâmetros
  window.location.reload();
}, 30000); // 30.000 ms

</script>
</body>
</html>
