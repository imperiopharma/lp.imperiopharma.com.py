<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * getPerfil.php (versão final com melhorias)
 *
 * Retorna os dados do cliente logado:
 *  - nome, email, pontos
 *  - todos os pedidos (id, valores, status, datas, admin_comments)
 *  - items de cada pedido, inclusive combo_details
 */

// 1) Exige login (customer_id na sessão)
if (!isset($_SESSION['customer_id'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário não está logado.'
    ]);
    exit;
}

// 2) Conexão PDO
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

} catch (PDOException $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao conectar: ' . $e->getMessage()
    ]);
    exit;
}

// 3) Identifica o cliente logado
$cid = $_SESSION['customer_id'];

// 4) Buscar dados básicos do cliente (nome, email, points)
$sqlCli = "
    SELECT nome, email, points
    FROM customers
    WHERE id = ?
    LIMIT 1
";
$stmtCli = $pdo->prepare($sqlCli);
$stmtCli->execute([$cid]);
$cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Cliente não encontrado.'
    ]);
    exit;
}

// 5) Buscar pedidos do cliente (tabela `orders`)
//    Incluindo admin_comments e colunas de frete/seguro/etc.
$sqlPed = "
  SELECT
    id,
    customer_name,
    cpf,
    phone,
    email,
    address,
    number,
    complement,
    neighborhood,
    city,
    state,
    cep,

    shipping_value,
    discount_value,
    card_fee_value,
    insurance_value,
    total,
    cost_total,
    final_value,
    points_earned,
    status,
    admin_comments,
    created_at,
    updated_at

  FROM orders
  WHERE customer_id = ?
  ORDER BY created_at DESC
";
$stmtPed = $pdo->prepare($sqlPed);
$stmtPed->execute([$cid]);
$rows = $stmtPed->fetchAll(PDO::FETCH_ASSOC);

// 6) Para cada pedido, buscar itens (order_items)
//    Incluindo combo_details para exibir subitens de combos
$pedidos = [];
foreach ($rows as $pedido) {
    $orderId = (int) $pedido['id'];

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
      WHERE order_id = ?
      ORDER BY id ASC
    ";
    $stmtI = $pdo->prepare($sqlItems);
    $stmtI->execute([$orderId]);
    $itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // Decodificar combo_details se existir
    foreach ($itens as &$it) {
        if (!empty($it['combo_details'])) {
            $it['combo_details'] = json_decode($it['combo_details'], true);
        } else {
            $it['combo_details'] = null;
        }
    }
    unset($it);

    $pedido['items'] = $itens;
    $pedidos[] = $pedido;
}

// 7) Pega o último pedido (pois DESC)
$ultimoPedido = (count($pedidos) > 0) ? $pedidos[0] : null;

// 8) Resposta JSON
echo json_encode([
    'sucesso'       => true,
    'nome'          => $cliente['nome'],
    'email'         => $cliente['email'],
    'points'        => (int)$cliente['points'],
    'ultimo_pedido' => $ultimoPedido,
    'pedidos'       => $pedidos
], JSON_UNESCAPED_UNICODE);

