<?php
/***************************************************************
 * painel_detalhe_pedido.php
 *
 * Exibe detalhes de um pedido, e gera uma MENSAGEM INTERNA
 * baseada no CUSTO ATUAL (buscando da tabela products).
 ***************************************************************/

// 1) Conexão com BD
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
    die("<p>Erro ao conectar no BD: " . $e->getMessage() . "</p>");
}

// 2) Obter o ID do pedido via GET
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    die("<p>Pedido inválido ou não informado!</p>");
}

// 3) Se houver POST, atualizar status, admin_comments ou pontos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 3.1) Atualizar status
    if (isset($_POST['novo_status'])) {
        $novoStatus = trim($_POST['novo_status']);
        $stmtUpd = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmtUpd->execute([$novoStatus, $orderId]);
    }

    // 3.2) Append em admin_comments
    if (isset($_POST['admin_comments'])) {
        $novaMsg = trim($_POST['admin_comments']);

        // Buscar o texto atual
        $stmtOld = $pdo->prepare("SELECT admin_comments FROM orders WHERE id=?");
        $stmtOld->execute([$orderId]);
        $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);
        $oldTxt = $oldRow ? $oldRow['admin_comments'] : '';

        // Concatena com nova linha
        $textoFinal = $oldTxt . "\n" . $novaMsg;

        // Salva
        $stmtMsg = $pdo->prepare("UPDATE orders SET admin_comments=? WHERE id=?");
        $stmtMsg->execute([$textoFinal, $orderId]);
    }

    // 3.3) Ajustar pontos do cliente (pontos_valor)
    if (isset($_POST['pontos_valor'])) {
        $pontosValor = (int)$_POST['pontos_valor'];

        $stmtCid = $pdo->prepare("SELECT customer_id FROM orders WHERE id=?");
        $stmtCid->execute([$orderId]);
        $rowCid = $stmtCid->fetch(PDO::FETCH_ASSOC);

        if ($rowCid) {
            $clienteId = (int)$rowCid['customer_id'];
            if ($clienteId > 0 && $pontosValor !== 0) {
                $stmtPts = $pdo->prepare("
                   UPDATE customers
                     SET points = points + :p
                   WHERE id=:cid
                ");
                $stmtPts->execute([
                    ':p'   => $pontosValor,
                    ':cid' => $clienteId
                ]);
            }
        }
    }

    // Redireciona para evitar reenvio
    header("Location: painel_detalhe_pedido.php?id={$orderId}");
    exit;
}

// 4) Carregar dados do pedido
$stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id=?");
$stmtOrder->execute([$orderId]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die("<p>Pedido não encontrado!</p>");
}

// 5) Carregar itens do pedido (JOIN com products para obter cost atual)
$stmtItems = $pdo->prepare("
  SELECT
    oi.id AS order_item_id,
    oi.product_id,
    oi.product_name,
    oi.brand,
    oi.quantity,
    oi.price,
    oi.subtotal,
    p.cost AS cost_atual
  FROM order_items oi
  LEFT JOIN products p ON oi.product_id = p.id
  WHERE oi.order_id = ?
");
$stmtItems->execute([$orderId]);
$itens = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// 6) Buscar pontos atuais do cliente
$customerId   = (int)($order['customer_id'] ?? 0);
$pontosAtuais = 0;
if ($customerId > 0) {
    $stmtCli = $pdo->prepare("SELECT points FROM customers WHERE id=? LIMIT 1");
    $stmtCli->execute([$customerId]);
    $rowCli = $stmtCli->fetch(PDO::FETCH_ASSOC);
    if ($rowCli) {
        $pontosAtuais = (int)$rowCli['points'];
    }
}

// 7) Cálculos de venda e custo
//    - $vendaTotalItens = soma de (subtotal) => preço de venda * quantidade
//    - $custoTotalItens = soma de (cost_atual * quantidade) => recalculado aqui
$vendaTotalItens = 0.0;
$custoTotalItens = 0.0;

foreach ($itens as &$it) {
    // Soma de vendas
    $vendaTotalItens += floatval($it['subtotal']);

    // Calcular custo atualizado (cost_atual * quantidade)
    $costAtual  = floatval($it['cost_atual']);
    $qtd        = intval($it['quantity']);
    $subtotalCustoAtual = $costAtual * $qtd;

    // Armazenar esse subtotal de custo para exibir
    $it['cost_subtotal_atual'] = $subtotalCustoAtual;

    // Somar ao custo total dos itens
    $custoTotalItens += $subtotalCustoAtual;
}
unset($it);

// Montar outros valores
$freteOriginal = floatval($order['shipping_value']);
$finalValue    = floatval($order['final_value']); // valor total de venda c/ frete + extras

// custo do frete = (freteOriginal - 5), min 0
$freteParaCusto = $freteOriginal - 5;
if ($freteParaCusto < 0) {
    $freteParaCusto = 0;
}
$custoTotalPedido = $custoTotalItens + $freteParaCusto;
$lucroEstimado    = $finalValue - $custoTotalPedido;

// contagem total de produtos
$qtdTotalProdutos = 0;
foreach ($itens as $it) {
    $qtdTotalProdutos += (int)$it['quantity'];
}

// Data, comprovante
$dataCriacao    = $order['created_at']     ?? '';
$comprovanteUrl = $order['comprovante_url']?? '';

// ----------------------------------------------------------------------------
// 8) MENSAGEM INTERNA (somente custos!)
//   Agora usando os custos recalculados (cost_subtotal_atual) em vez do cost_subtotal salvo.
// ----------------------------------------------------------------------------
$tipoFrete   = $order['shipping_type'] ?? '---';
$freteCusto  = $freteParaCusto;
$totalCusto  = $custoTotalPedido; // soma dos custos + frete calculado

// 1) Bruno Big + (tipoFrete)
$msgInterna  = "Bruno Big\n";
$msgInterna .= "({$tipoFrete})\n\n";

// 2) Dados do cliente
$nome   = $order['customer_name'] ?? '';
$cpf    = $order['cpf']           ?? '';
$email  = $order['email']         ?? '';
$fone   = $order['phone']         ?? '';

$end    = $order['address']       ?? '';
$num    = $order['number']        ?? '';
$compl  = $order['complement']    ?? '';
$bair   = $order['neighborhood']  ?? '';
$cid    = $order['city']          ?? '';
$est    = $order['state']         ?? '';
$cep    = $order['cep']           ?? '';

$msgInterna .= "Nome: {$nome}\n";
$msgInterna .= "CPF: {$cpf}\n";
$msgInterna .= "E-mail: {$email}\n";
$msgInterna .= "Telefone: {$fone}\n";
$msgInterna .= "Endereço: {$end}\n";
$msgInterna .= "Número: {$num}\n";
$msgInterna .= "Complemento: {$compl}\n";
$msgInterna .= "CEP: {$cep}\n";
$msgInterna .= "Bairro: {$bair}\n";
$msgInterna .= "Cidade: {$cid}\n";
$msgInterna .= "Estado: {$est}\n\n";

// FRETE (custo)
$msgInterna .= "FRETE: " . number_format($freteCusto, 2, ',', '.') . "\n\n";

// PRODUTOS (custo recalculado)
$msgInterna .= "PRODUTOS:\n";
foreach ($itens as $it) {
    $qtd       = (int)$it['quantity'];
    $nomeProd  = $it['product_name'] ?? '';
    $marca     = $it['brand']        ?? '';
    $cSub      = floatval($it['cost_subtotal_atual']); // custo recalculado

    $linha = "{$qtd}x {$nomeProd}";
    if (!empty($marca)) {
        $linha .= " ({$marca})";
    }
    $linha .= "  " . number_format($cSub, 2, ',', '.'); // sem "R$"

    $msgInterna .= $linha . "\n";
}

// TOTAL (somatório de custo recalculado + $freteParaCusto)
$msgInterna .= "\nTOTAL: " . number_format($totalCusto, 2, ',', '.') . "\n";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pedido #<?= $orderId ?> - Detalhes</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      max-width: 1200px;
      margin: 0 auto;
      padding: 10px;
      background: #f9f9fc;
    }
    .top-bar {
      background: #333;
      color: #fff;
      padding: 10px;
      margin-bottom: 10px;
    }
    .top-bar a {
      color: #fff;
      text-decoration: none;
      font-weight: bold;
      margin-right: 12px;
    }
    .top-bar a:hover {
      text-decoration: underline;
    }
    h1 {
      font-size: 1.6rem;
      border-bottom: 2px solid #ccc;
      padding-bottom: 5px;
      margin-bottom: 15px;
    }
    .section-block {
      background: #fff;
      border: 1px solid #ddd;
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 6px;
    }
    .section-block h2 {
      font-size: 1.2rem;
      margin-bottom: 10px;
      border-bottom: 1px solid #eee;
      padding-bottom: 5px;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-top: 10px;
    }
    .info-item {
      background: #fafafa;
      padding: 8px;
      border: 1px solid #eee;
      border-radius: 4px;
      line-height: 1.4;
      font-size: 0.95rem;
    }
    .info-item strong {
      display: inline-block;
      width: 130px;
      color: #333;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      margin: 10px 0;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px;
      font-size: 0.9rem;
      vertical-align: middle;
    }
    th {
      background-color: #eee;
    }
    .status-form {
      margin-top: 10px;
      text-align: center;
    }
    .status-form select {
      padding: 6px;
      margin-right: 5px;
      font-size: 0.9rem;
    }
    .status-form button {
      padding: 6px 12px;
      background: #1e73be;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9rem;
    }
    .status-form button:hover {
      background: #145c90;
    }
    .copy-area {
      width: 100%;
      min-height: 160px;
      font-family: 'Courier New', monospace;
      padding: 6px;
      resize: vertical;
    }
    .btn-copy {
      padding: 8px 14px;
      background: #444;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-top: 5px;
    }
    .btn-copy:hover {
      background-color: #333;
    }
    .comprovante-link {
      display: inline-block;
      padding: 8px 12px;
      background: #6e88e8;
      color: #fff;
      text-decoration: none;
      border-radius: 4px;
      font-size: 0.9rem;
      transition: background 0.2s;
    }
    .comprovante-link:hover {
      background: #5a73d5;
    }
    /* admin_comments com quebras de linha */
    .admin-historico {
      background:#fafafa;
      border:1px solid #ccc;
      border-radius:4px;
      padding:6px;
      margin-top:4px;
      max-width:400px;
      white-space:pre-wrap; 
      text-align:left;
    }
    @media (max-width: 700px) {
      .info-grid {
        grid-template-columns: 1fr;
      }
      .info-item strong {
        width: 100px;
      }
    }
  </style>
</head>
<body>

<!-- Barra Superior -->
<div class="top-bar">
  <a href="index.php">&laquo; Voltar para a página principal</a>
</div>

<h1>Detalhes do Pedido #<?= $orderId ?></h1>

<!-- Bloco: Informações do Pedido -->
<div class="section-block">
  <h2>Informações do Pedido</h2>
  <div class="info-grid">
    <div class="info-item">
      <strong>Status:</strong>
      <?= htmlspecialchars($order['status']) ?>
    </div>
    <div class="info-item">
      <strong>Data Criação:</strong>
      <?= htmlspecialchars($dataCriacao) ?: '--' ?>
    </div>
    <div class="info-item">
      <strong>Qtd Total de Prod.:</strong>
      <?= $qtdTotalProdutos ?>
    </div>
    <div class="info-item">
      <strong>Frete Original:</strong>
      R$ <?= number_format($freteOriginal,2,',','.') ?>
      <small>(-5 no custo)</small>
    </div>
  </div>

  <!-- Form de Status e admin_comments -->
  <div class="status-form" style="margin-top:1rem;">
    <!-- Atualizar status -->
    <form method="POST" style="margin-bottom:0.75rem;"
          onsubmit="return confirm('Alterar status do pedido?');">
      <label>Novo Status:</label>
      <?php
        // Ajustar conforme seus status disponíveis
        $statusList = ['PENDENTE','CONFIRMADO','EM PROCESSO','CANCELADO'];
      ?>
      <select name="novo_status">
        <?php foreach ($statusList as $st):
          $selected = ($order['status'] === $st) ? 'selected' : '';
        ?>
          <option value="<?= $st ?>" <?= $selected ?>><?= $st ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Atualizar</button>
    </form>

    <!-- Append admin_comments -->
    <form method="POST" onsubmit="return confirm('Salvar nova mensagem (append)?');">
      <label for="admin_comments" style="display:block; font-weight:bold; margin:6px 0;">
        Mensagem (admin_comments) [append]:
      </label>
      <textarea name="admin_comments"
                id="admin_comments"
                rows="3"
                style="width:100%; max-width:400px; margin-bottom:4px;"
      ></textarea>
      <button type="submit">Adicionar Mensagem</button>
    </form>

    <!-- Histórico -->
    <div style="margin-top:1rem; text-align:left;">
      <label><strong>Histórico de Mensagens:</strong></label>
      <div class="admin-historico">
        <?= nl2br(htmlspecialchars($order['admin_comments'] ?? '')) ?>
      </div>
    </div>
  </div>
</div>

<!-- Ajustar Pontos -->
<div class="section-block">
  <h2>Ajustar Pontos do Cliente</h2>
  <p>Pontuação atual: <strong><?= $pontosAtuais ?></strong></p>
  <p style="margin:6px 0;">
    Digite um valor (positivo/negativo) p/ adicionar ou remover pontos.
  </p>
  <form method="POST" onsubmit="return confirm('Deseja ajustar os pontos do cliente?');">
    <label for="pontos_valor">Valor de Ajuste:</label>
    <input type="number" name="pontos_valor" id="pontos_valor"
           value="0" style="width:80px; text-align:center; margin-right:6px;"/>
    <button type="submit" style="background:#7f5; color:#000;">
      Atualizar Pontos
    </button>
  </form>
</div>

<!-- Dados do Cliente -->
<div class="section-block">
  <h2>Dados do Cliente</h2>
  <div class="info-grid">
    <div class="info-item">
      <strong>Nome:</strong> <?= htmlspecialchars($order['customer_name']) ?>
    </div>
    <div class="info-item">
      <strong>CPF:</strong> <?= htmlspecialchars($order['cpf']) ?>
    </div>
    <div class="info-item">
      <strong>Telefone:</strong> <?= htmlspecialchars($order['phone']) ?>
    </div>
    <div class="info-item">
      <strong>E-mail:</strong> <?= htmlspecialchars($order['email']) ?>
    </div>
    <div class="info-item">
      <strong>Endereço:</strong> <?= htmlspecialchars($order['address']) ?>
    </div>
    <div class="info-item">
      <strong>Número:</strong> <?= htmlspecialchars($order['number']) ?>
    </div>
    <div class="info-item">
      <strong>Complemento:</strong> <?= htmlspecialchars($order['complement']) ?>
    </div>
    <div class="info-item">
      <strong>Bairro:</strong> <?= htmlspecialchars($order['neighborhood']) ?>
    </div>
    <div class="info-item">
      <strong>Cidade/Estado:</strong>
      <?= htmlspecialchars($order['city']) ?>/<?= htmlspecialchars($order['state']) ?>
    </div>
    <div class="info-item">
      <strong>CEP:</strong> <?= htmlspecialchars($order['cep']) ?>
    </div>
  </div>
</div>

<!-- Itens do Pedido (Custo) -->
<div class="section-block">
  <h2>Itens (Custo)</h2>
  <table>
    <thead>
      <tr>
        <th>ID Item</th>
        <th>Produto</th>
        <th>Marca</th>
        <th>Qtd</th>
        <th>Cost</th>
        <th>Cost Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($itens as $it): ?>
      <tr>
        <td><?= (int)$it['order_item_id'] ?></td>
        <td><?= htmlspecialchars($it['product_name']) ?></td>
        <td><?= htmlspecialchars($it['brand']) ?></td>
        <td><?= (int)$it['quantity'] ?></td>
        <td>R$ <?= number_format($it['cost_atual'],2,',','.') ?></td>
        <td>R$ <?= number_format($it['cost_subtotal_atual'],2,',','.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Comprovante de Pagamento -->
<div class="section-block">
  <h2>Comprovante de Pagamento</h2>
  <?php if (!empty($order['comprovante_url'])): ?>
    <p>
      <a href="<?= htmlspecialchars($order['comprovante_url']) ?>"
         target="_blank"
         class="comprovante-link">
        Ver Comprovante
      </a>
    </p>
  <?php else: ?>
    <p>Nenhum comprovante disponível.</p>
  <?php endif; ?>
</div>

<!-- Métricas e Relatórios (Venda vs Custo) -->
<?php
$descontoValue  = floatval($order['discount_value']  ?? 0);
$cardFeeValue   = floatval($order['card_fee_value']   ?? 0);
$insuranceValue = floatval($order['insurance_value']  ?? 0);

// Já temos $finalValue calculado lá em cima
?>
<div class="section-block">
  <h2>Métricas e Relatórios</h2>
  <div class="info-grid">
    <div class="info-item">
      <strong>Venda Itens (s/ frete):</strong>
      R$ <?= number_format($vendaTotalItens,2,',','.') ?>
    </div>
    <div class="info-item">
      <strong>Frete (Cobrado):</strong>
      R$ <?= number_format($freteOriginal,2,',','.') ?>
    </div>
    <div class="info-item">
      <strong>Venda Total:</strong>
      R$ <?= number_format($finalValue,2,',','.') ?>
    </div>

    <div class="info-item">
      <strong>Custo Itens:</strong>
      R$ <?= number_format($custoTotalItens,2,',','.') ?>
    </div>
    <div class="info-item">
      <strong>Custo Frete:</strong>
      R$ <?= number_format($freteParaCusto,2,',','.') ?>
      <small>(FreteOriginal - 5)</small>
    </div>
    <div class="info-item">
      <strong>Custo Total:</strong>
      R$ <?= number_format($custoTotalPedido,2,',','.') ?>
    </div>
    <div class="info-item">
      <strong>Lucro Estimado:</strong>
      R$ <?= number_format($lucroEstimado,2,',','.') ?>
    </div>

    <?php if ($descontoValue > 0): ?>
      <div class="info-item">
        <strong>Desconto:</strong>
        R$ <?= number_format($descontoValue,2,',','.') ?>
      </div>
    <?php endif; ?>

    <?php if ($cardFeeValue > 0): ?>
      <div class="info-item">
        <strong>Acrésc. Cartão:</strong>
        R$ <?= number_format($cardFeeValue,2,',','.') ?>
      </div>
    <?php endif; ?>

    <?php if ($insuranceValue > 0): ?>
      <div class="info-item">
        <strong>Seguro:</strong>
        R$ <?= number_format($insuranceValue,2,',','.') ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Mensagem Interna c/ COST -->
<div class="section-block">
  <h2>Mensagem Interna (Somente Custo)</h2>
  <textarea id="msgInterna" class="copy-area"><?= htmlspecialchars($msgInterna) ?></textarea>
  <br/>
  <button class="btn-copy" onclick="copiarMensagem()">Copiar Mensagem</button>
</div>

<script>
function copiarMensagem() {
  const ta = document.getElementById('msgInterna');
  ta.select();
  document.execCommand('copy');
  alert('Mensagem interna copiada!');
}
</script>

<hr/>
<p style="text-align:center; margin-top: 10px;">
  <a href="index.php" style="text-decoration:none; color:#333; font-weight:600;">
    &laquo; Voltar à página principal
  </a>
</p>
</body>
</html>
