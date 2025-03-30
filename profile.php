<?php
session_start();

// Verifica se está logado
if (!isset($_SESSION['customer_id'])) {
    // Se não estiver logado, redireciona pro login
    header("Location: login.php");
    exit;
}

// Conexão com BD
$dbHost = 'localhost';
$dbName = 'imperiopharma_loja_db';
$dbUser = 'imperiopharma_loja_user';
$dbPass = 'Miguel22446688';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Erro ao conectar: " . $e->getMessage();
    exit;
}

// Busca dados do cliente
$cid = $_SESSION['customer_id'];
$sqlCli = "SELECT nome, email, points FROM customers WHERE id=:cid LIMIT 1";
$stmt = $pdo->prepare($sqlCli);
$stmt->execute([':cid' => $cid]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Se não achou cliente
if (!$cliente) {
    echo "<h2>Cliente não encontrado!</h2>";
    exit;
}

// Buscar pedidos do cliente
$sqlOrders = "
  SELECT
    id, 
    final_value,
    total,
    cost_total,
    shipping_value,
    discount_value,
    card_fee_value,
    insurance_value,
    points_earned,
    status,
    admin_comments,
    created_at,
    updated_at
  FROM orders
  WHERE customer_id=:cid
  ORDER BY created_at DESC
";
$stO = $pdo->prepare($sqlOrders);
$stO->execute([':cid'=>$cid]);
$pedidos = $stO->fetchAll(PDO::FETCH_ASSOC);

// Para cada pedido, buscar itens (inclusive combo_details)
foreach ($pedidos as &$pd) {
    $orderId = $pd['id'];
    $sqlItems = "
      SELECT
        product_id,
        product_name,
        brand,
        quantity,
        price,
        cost,
        subtotal,
        cost_subtotal,
        combo_details
      FROM order_items
      WHERE order_id=:oid
      ORDER BY id ASC
    ";
    $stI = $pdo->prepare($sqlItems);
    $stI->execute([':oid'=>$orderId]);
    $itens = $stI->fetchAll(PDO::FETCH_ASSOC);

    // Decodificar combo_details se existir
    foreach ($itens as &$it) {
        if (!empty($it['combo_details'])) {
            $it['combo_details'] = json_decode($it['combo_details'], true);
        } else {
            $it['combo_details'] = null;
        }
    }
    unset($it);

    $pd['items'] = $itens;
}
unset($pd);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meu Perfil - Império Pharma</title>
  <style>
    * {
      margin: 0; padding: 0; box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      margin: 0;
    }
    .container {
      max-width: 800px;
      margin: 20px auto;
      background: #fff;
      padding: 20px;
      border-radius: 6px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    h1 {
      text-align: center;
      margin-bottom: 1rem;
      color: #0c1f3f;
    }
    .perfil-dados {
      background: #f9f9f9;
      border: 1px solid #eee;
      border-radius: 4px;
      padding: 16px;
      margin-bottom: 20px;
    }
    .perfil-dados h2 {
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
      color: #0055a4;
    }
    .perfil-dados p {
      margin: 4px 0;
      font-size: 0.95rem;
      color: #333;
    }
    .pedidos-container {
      margin-top: 1.5rem;
    }
    .pedido-card {
      background: #fafafa;
      border: 1px solid #ddd;
      border-radius: 4px;
      padding: 12px;
      margin-bottom: 1rem;
    }
    .pedido-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
    }
    .pedido-header h3 {
      font-size: 1rem;
      margin: 0;
      color: #444;
    }
    .pedido-header .status-badge {
      font-weight: bold;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.85rem;
      color: #fff;
      margin-left: 6px;
    }
    .status-pendente     { background: #f0ad4e; }
    .status-pago         { background: #28a745; }
    .status-envio        { background: #17a2b8; }
    .status-cancelado    { background: #dc3545; }
    .status-outro        { background: #6c757d; }

    .pedido-detalhes {
      display: none; /* começa oculto, clique pra expandir */
      margin-top: 0.75rem;
      border-top: 1px solid #ccc;
      padding-top: 10px;
      font-size: 0.92rem;
    }
    .pedido-resumo-financeiro {
      background: #fff;
      border: 1px solid #eee;
      border-radius: 4px;
      padding: 8px;
      margin-top: 8px;
    }
    .pedido-itens ul {
      list-style: none;
      margin: 0; padding: 0;
    }
    .pedido-itens li {
      margin-bottom: 6px;
      border-bottom: 1px dotted #ccc;
      padding-bottom: 4px;
    }
    .combo-subitens {
      font-style: italic;
      color: #555;
      margin-left: 10px;
      display: block;
    }
    .admin-comments {
      background: #fff0f0;
      border: 1px solid #f7c2c2;
      padding: 8px;
      margin-top: 8px;
      border-radius: 4px;
      color: #b33b3b;
    }
    .links-footer {
      text-align: center;
      margin-top: 20px;
    }
    .links-footer a {
      margin: 0 10px;
      text-decoration: none;
      color: #0055a4;
      font-weight: 600;
    }
  </style>
</head>
<body>

<div class="container">
  <h1>Meu Perfil - Império Pharma</h1>

  <div class="perfil-dados">
    <h2>Dados do Cliente</h2>
    <p><strong>Nome:</strong> <?= htmlspecialchars($cliente['nome'] ?? '') ?></p>
    <p><strong>E-mail:</strong> <?= htmlspecialchars($cliente['email'] ?? '') ?></p>
    <p><strong>Pontos:</strong> <?= (int)($cliente['points'] ?? 0) ?></p>
  </div>

  <div class="pedidos-container">
    <h2>Meus Pedidos</h2>
    <?php if (empty($pedidos)): ?>
      <p>Nenhum pedido encontrado no momento.</p>
    <?php else: ?>
      <?php foreach ($pedidos as $pd): ?>
        <?php
          // Montar classes pro status
          $statusSlug = strtolower($pd['status']);
          if (!in_array($statusSlug, ['pendente','pago','enviado','em trânsito','cancelado'])) {
              $statusSlug = 'outro';
          } elseif ($statusSlug === 'em trânsito' || $statusSlug === 'enviado') {
              $statusSlug = 'envio';
          }
        ?>
        <div class="pedido-card">
          <div class="pedido-header" onclick="toggleDetalhes(<?= $pd['id'] ?>)">
            <h3>
              Pedido #<?= $pd['id'] ?> 
              - Valor: R$ <?= number_format($pd['final_value'], 2, ',', '.') ?>
            </h3>
            <span class="status-badge status-<?= $statusSlug ?>">
              <?= htmlspecialchars($pd['status']) ?>
            </span>
          </div>

          <div id="detalhes-<?= $pd['id'] ?>" class="pedido-detalhes">
            <p><strong>Criado em:</strong> <?= $pd['created_at'] ?></p>
            <?php if (!empty($pd['updated_at'])): ?>
              <p><strong>Atualizado em:</strong> <?= $pd['updated_at'] ?></p>
            <?php endif; ?>

            <!-- Itens do pedido -->
            <div class="pedido-itens">
              <h4>Itens</h4>
              <ul>
                <?php foreach ($pd['items'] as $it): ?>
                  <li>
                    <strong><?= htmlspecialchars($it['product_name']) ?></strong>
                    (<?= htmlspecialchars($it['brand'] ?? '---') ?>) 
                    - Qtd: <?= (int)$it['quantity'] ?> 
                    <br>
                    <em>
                      Valor Unit: R$ <?= number_format($it['price'], 2, ',', '.') ?>
                    </em>
                    <?php if (!empty($it['combo_details']) && is_array($it['combo_details'])): ?>
                      <!-- Subitens do combo -->
                      <span class="combo-subitens">
                        Subitens do combo:
                        <?= implode(', ', array_map(function($c){
                             return $c['nome'] ?? '???';
                           }, $it['combo_details'])) ?>
                      </span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- Resumo Financeiro -->
            <div class="pedido-resumo-financeiro">
              <p><strong>Subtotal (sem desconto):</strong> R$ <?= number_format($pd['total'], 2, ',', '.') ?></p>
              <?php if ((float)$pd['discount_value'] > 0): ?>
                <p><strong>Desconto:</strong> R$ <?= number_format($pd['discount_value'], 2, ',', '.') ?></p>
              <?php endif; ?>
              <p><strong>Frete:</strong> R$ <?= number_format($pd['shipping_value'], 2, ',', '.') ?></p>
              <?php if ((float)$pd['card_fee_value'] > 0): ?>
                <p><strong>Taxa Cartão:</strong> R$ <?= number_format($pd['card_fee_value'], 2, ',', '.') ?></p>
              <?php endif; ?>
              <?php if ((float)$pd['insurance_value'] > 0): ?>
                <p><strong>Seguro:</strong> R$ <?= number_format($pd['insurance_value'], 2, ',', '.') ?></p>
              <?php endif; ?>
              <p>
                <strong>Valor Final:</strong> 
                R$ <?= number_format($pd['final_value'], 2, ',', '.') ?>
              </p>
              <p>
                <strong>Pontos Ganhos:</strong> 
                <?= (int)$pd['points_earned'] ?>
              </p>
            </div>

            <!-- Observações do Admin -->
            <?php if (!empty($pd['admin_comments'])): ?>
              <div class="admin-comments">
                <strong>Observações do Administrador:</strong><br>
                <div style="margin-top:4px; white-space: pre-wrap;">
                  <?= htmlspecialchars($pd['admin_comments']) ?>
                </div>
              </div>
            <?php endif; ?>

          </div> <!-- /.pedido-detalhes -->
        </div> <!-- /.pedido-card -->
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="links-footer">
    <a href="index.html">Voltar à Loja</a> |
    <a href="logout.php">Sair</a>
  </div>
</div>

<script>
function toggleDetalhes(orderId) {
  const detalhesDiv = document.getElementById('detalhes-' + orderId);
  if (!detalhesDiv) return;
  if (detalhesDiv.style.display === 'none' || !detalhesDiv.style.display) {
    detalhesDiv.style.display = 'block';
  } else {
    detalhesDiv.style.display = 'none';
  }
}
</script>
</body>
</html>
