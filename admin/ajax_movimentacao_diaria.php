<?php
// /admin/ajax_movimentacao_diaria.php

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

// Filtrar pedidos do dia de HOJE que closed=0
$hoje = date('Y-m-d');

$stmt = $pdo->prepare("
  SELECT id, customer_name, final_value, cost_total, status,
         DATE_FORMAT(created_at, '%H:%i:%s') AS hora
  FROM orders
  WHERE DATE(created_at) = :hoje
    AND closed = 0
  ORDER BY created_at ASC
");
$stmt->execute([':hoje' => $hoje]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$somaVenda = 0; 
$somaCusto = 0;
foreach ($pedidos as $p) {
  $somaVenda += floatval($p['final_value']);
  $somaCusto += floatval($p['cost_total']);
}
$lucro = $somaVenda - $somaCusto;
?>
<div style="margin-bottom:10px;">
  <strong>Pedidos de Hoje (ainda não fechados):</strong> <?= count($pedidos) ?><br/>
  <strong>Receita (parcial):</strong> R$ <?= number_format($somaVenda,2,',','.') ?><br/>
  <strong>Custo (parcial):</strong> R$ <?= number_format($somaCusto,2,',','.') ?><br/>
  <strong>Lucro (parcial):</strong> R$ <?= number_format($lucro,2,',','.') ?>
</div>

<table style="width:100%; border-collapse:collapse;">
  <thead>
    <tr style="background:#eee;">
      <th style="border:1px solid #ccc; padding:5px;">ID</th>
      <th style="border:1px solid #ccc; padding:5px;">Hora</th>
      <th style="border:1px solid #ccc; padding:5px;">Cliente</th>
      <th style="border:1px solid #ccc; padding:5px;">Status</th>
      <th style="border:1px solid #ccc; padding:5px;">Venda (R$)</th>
      <th style="border:1px solid #ccc; padding:5px;">Custo (R$)</th>
      <th style="border:1px solid #ccc; padding:5px;">Lucro</th>
      <th style="border:1px solid #ccc; padding:5px;">Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$pedidos): ?>
    <tr>
      <td colspan="8" style="border:1px solid #ccc; padding:5px; text-align:center;">
        Nenhum pedido aberto hoje.
      </td>
    </tr>
    <?php else: ?>
      <?php foreach($pedidos as $pd): 
        $v = floatval($pd['final_value']);
        $c = floatval($pd['cost_total']);
        $l = $v - $c;
      ?>
      <tr>
        <td style="border:1px solid #ccc; padding:5px;"><?= $pd['id'] ?></td>
        <td style="border:1px solid #ccc; padding:5px;"><?= $pd['hora'] ?></td>
        <td style="border:1px solid #ccc; padding:5px;"><?= htmlspecialchars($pd['customer_name']) ?></td>
        <td style="border:1px solid #ccc; padding:5px;"><?= htmlspecialchars($pd['status']) ?></td>
        <td style="border:1px solid #ccc; padding:5px;"><?= number_format($v,2,',','.') ?></td>
        <td style="border:1px solid #ccc; padding:5px;"><?= number_format($c,2,',','.') ?></td>
        <td style="border:1px solid #ccc; padding:5px;"><?= number_format($l,2,',','.') ?></td>
        <td style="border:1px solid #ccc; padding:5px;">
          <a href="pedido_detalhe.php?id=<?= $pd['id'] ?>">Ver</a>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
