<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * Gera a mesma “Mensagem Interna (Somente Custo)” do pedidos.php?action=detail
 * - Faz a conta do frete com -5 no custo
 * - Carrega os itens, soma cost_atual
 */
function gerarMensagemInterna($pdo, $orderId) {
    // 1) Carrega o pedido
    $stmO = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $stmO->execute([$orderId]);
    $order = $stmO->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return "Pedido #{$orderId} não encontrado.";
    }

    // 2) Carrega itens + custo atual (products.cost)
    $sqlItems = "
      SELECT oi.*, p.cost AS cost_atual
      FROM order_items oi
      LEFT JOIN products p ON oi.product_id=p.id
      WHERE oi.order_id=?
    ";
    $stmI = $pdo->prepare($sqlItems);
    $stmI->execute([$orderId]);
    $itens = $stmI->fetchAll(PDO::FETCH_ASSOC);

    // 3) Soma custo total
    $custo = 0;
    foreach ($itens as $it) {
        $qtd      = (int)($it['quantity'] ?? 1);
        $costUnit = (float)($it['cost_atual'] ?? 0);
        $custo   += ($costUnit * $qtd);
    }

    // 4) Ajuste do frete (exemplo: -5)
    $freteOriginal  = (float)($order['shipping_value'] ?? 0);
    $tipoFrete      = $order['shipping_type'] ?? '';
    $freteParaCusto = $freteOriginal - 5;
    if ($freteParaCusto < 0) {
        $freteParaCusto = 0;
    }
    $custoPedido = $custo + $freteParaCusto;

    // 5) Montar o texto igual ao detail
    // (Exatamente como no "Mensagem Interna (Somente Custo)")
    $msg  = "Bruno Big\n($tipoFrete)\n\n";
    $msg .= "Nome: ".($order['customer_name'] ?? '')."\n";
    $msg .= "CPF: ".($order['cpf'] ?? '')."\n";
    $msg .= "E-mail: ".($order['email'] ?? '')."\n";
    $msg .= "Telefone: ".($order['phone'] ?? '')."\n";
    $msg .= "Endereço: ".($order['address'] ?? '')."\n";
    $msg .= "Número: ".($order['number'] ?? '')."\n";
    $msg .= "Complemento: ".($order['complement'] ?? '')."\n";
    $msg .= "CEP: ".($order['cep'] ?? '')."\n";
    $msg .= "Bairro: ".($order['neighborhood'] ?? '')."\n";
    $msg .= "Cidade: ".($order['city'] ?? '')."\n";
    $msg .= "Estado: ".($order['state'] ?? '')."\n\n";
    $msg .= "FRETE: ".number_format($freteParaCusto,2,',','.')."\n\n";

    $msg .= "PRODUTOS:\n";
    foreach ($itens as $ix) {
        $qtd  = (int)   ($ix['quantity'] ?? 1);
        $nm   = (string)($ix['product_name'] ?? '');
        $br   = (string)($ix['brand'] ?? '');
        $cUni = (float) ($ix['cost_atual'] ?? 0);
        $subC = $cUni * $qtd;

        $linha = "{$qtd}x {$nm}";
        if ($br) {
            $linha .= " ({$br})";
        }
        $linha .= " => ".number_format($subC,2,',','.');
        $msg   .= $linha."\n";
    }
    $msg .= "\nTOTAL: ".number_format($custoPedido,2,',','.')."\n";

    return $msg;
}

/**
 * Envia a mensagem via Wascript (POST /api/enviar-texto/{token})
 */
function enviarMensagemWascript($phone, $message) {
    // Seu token exato
    $token = '1741243040070-789f20d337e5e8d6c95621ba5f5807f8';

    // Endpoint
    $url = "https://api-whatsapp.wascript.com.br/api/enviar-texto/{$token}";

    // Corpo JSON
    $payload = [
        'phone'   => $phone,   // Ex: '554799976114'
        'message' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    // Log local, se quiser ver no error_log
    error_log("Wascript => HTTP=$httpCode, err=$err, resp=$resp");

    if($httpCode===200 && !$err) {
        $jsonResp = @json_decode($resp, true);
        if(isset($jsonResp['success']) && $jsonResp['success']===true) {
            return true;
        }
    }
    return false;
}

// --------------------------------------------------------------------------------
// -------------- SEU CÓDIGO ORIGINAL, APÓS CRIAR O PEDIDO E ITENS ----------------
// --------------------------------------------------------------------------------

if (!isset($_SESSION['customer_id'])) {
    http_response_code(403);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário não está logado.'
    ]);
    exit;
}

$dbHost = 'localhost';
$dbName = 'imperiopharma_loja_db';
$dbUser = 'imperiopharma_loja_user';
$dbPass = 'Miguel22446688';

try {
    // 1) Conexão PDO
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2) Ler dados do POST (JSON)
    $dadosJson = $_POST['dadosJson'] ?? '';
    $data = json_decode($dadosJson, true);
    if (!$data) {
        throw new Exception("JSON inválido ou ausente em 'dadosJson'.");
    }

    $customerId = $_SESSION['customer_id'];

    // 3) Extrair dados do cliente
    $cliente = $data['cliente'] ?? [];
    $customerName  = $cliente['nome']        ?? '';
    $cpf           = $cliente['cpf']         ?? '';
    $phone         = $cliente['cel']         ?? '';
    $email         = $cliente['email']       ?? '';
    $address       = $cliente['endereco']    ?? '';
    $number        = $cliente['numero']      ?? '';
    $complement    = $cliente['complemento'] ?? '';
    $neighborhood  = $cliente['bairro']      ?? '';
    $city          = $cliente['cidade']      ?? '';
    $state         = $cliente['estado']      ?? '';
    $cep           = $cliente['cep']         ?? '';

    // 4) Frete e Pagamento
    $shippingType  = $data['shipping_type'] ?? '';
    $shippingValue = (float)($data['freteValor'] ?? 0);
    $paymentMethod = $data['pagamento']     ?? '';

    // 5) Descontos / Acréscimos
    $discountValue  = (float)($data['desconto']  ?? 0);
    $cardFeeValue   = (float)($data['acrescimoCartao'] ?? 0);
    $insuranceValue = (float)($data['seguro']    ?? 0);

    // 6) Processar carrinho
    $carrinho  = $data['carrinho'] ?? [];
    $subtotal  = 0;
    $costTotal = 0;

    $pdo->beginTransaction();

    foreach ($carrinho as &$item) {
        $price = (float)($item['preco'] ?? 0);
        $qtd   = (int)($item['quantidade'] ?? 1);
        $subtotal += ($price * $qtd);

        $stmtC = $pdo->prepare("SELECT cost FROM products WHERE id=?");
        $stmtC->execute([$item['id']]);
        $rC = $stmtC->fetch(PDO::FETCH_ASSOC);
        $costUnit = $rC ? (float)$rC['cost'] : 0.0;

        $costTotal += ($costUnit * $qtd);
        $item['cost'] = $costUnit;
    }
    unset($item);

    $total = ($subtotal - $discountValue) + $shippingValue + $cardFeeValue;
    $finalValue = $total + $insuranceValue;

    // 7) Inserir na tabela orders
    $sqlOrder = "
      INSERT INTO orders(
        customer_id,
        customer_name, cpf, phone, email,
        address, number, complement,
        neighborhood, city, state, cep,
        shipping_type, shipping_value, payment_method,
        discount_value, card_fee_value, insurance_value,
        total, cost_total, final_value,
        status
      ) VALUES(
        :customer_id,
        :customer_name, :cpf, :phone, :email,
        :address, :number, :complement,
        :neighborhood, :city, :state, :cep,
        :shipping_type, :shipping_value, :payment_method,
        :discount_value, :card_fee_value, :insurance_value,
        :total, :cost_total, :final_value,
        'PENDENTE'
      )
    ";
    $stmtO = $pdo->prepare($sqlOrder);
    $stmtO->execute([
        ':customer_id'     => $customerId,
        ':customer_name'   => $customerName,
        ':cpf'             => $cpf,
        ':phone'           => $phone,
        ':email'           => $email,
        ':address'         => $address,
        ':number'          => $number,
        ':complement'      => $complement,
        ':neighborhood'    => $neighborhood,
        ':city'            => $city,
        ':state'           => $state,
        ':cep'             => $cep,
        ':shipping_type'   => $shippingType,
        ':shipping_value'  => $shippingValue,
        ':payment_method'  => $paymentMethod,
        ':discount_value'  => $discountValue,
        ':card_fee_value'  => $cardFeeValue,
        ':insurance_value' => $insuranceValue,
        ':total'           => $total,
        ':cost_total'      => $costTotal,
        ':final_value'     => $finalValue
    ]);
    $orderId = $pdo->lastInsertId();

    // 8) Inserir itens
    $sqlItem = "
      INSERT INTO order_items(
        order_id, product_id, product_name, brand,
        quantity, price, cost, subtotal, cost_subtotal,
        combo_details
      ) VALUES(
        :order_id, :product_id, :product_name, :brand,
        :quantity, :price, :cost, :subtotal, :cost_subtotal,
        :combo_details
      )
    ";
    $stmtI = $pdo->prepare($sqlItem);

    foreach ($carrinho as $it) {
        $pId   = (int)($it['id'] ?? 0);
        $pName = (string)($it['nome'] ?? '');
        $brand = (string)($it['marca']??'');
        $qtd   = (int)  ($it['quantidade']??1);
        $prc   = (float)($it['preco']??0);
        $cst   = (float)($it['cost']??0);

        $subPrice = $prc * $qtd;
        $subCost  = $cst * $qtd;

        $comboJson = null;
        if (isset($it['comboDetails']) && is_array($it['comboDetails'])) {
            $comboJson = json_encode($it['comboDetails'], JSON_UNESCAPED_UNICODE);
        } elseif (isset($it['comboItems']) && is_array($it['comboItems']) && count($it['comboItems'])>0) {
            $comboJson = json_encode(['subitems'=>$it['comboItems']], JSON_UNESCAPED_UNICODE);
        }

        $stmtI->execute([
            ':order_id'      => $orderId,
            ':product_id'    => $pId,
            ':product_name'  => $pName,
            ':brand'         => $brand,
            ':quantity'      => $qtd,
            ':price'         => $prc,
            ':cost'          => $cst,
            ':subtotal'      => $subPrice,
            ':cost_subtotal' => $subCost,
            ':combo_details' => $comboJson
        ]);
    }

    // 9) Pontos
    $pointsEarned = floor($finalValue / 50);
    if ($pointsEarned > 0) {
        $pdo->prepare("UPDATE orders SET points_earned=? WHERE id=?")
            ->execute([$pointsEarned, $orderId]);
        $pdo->prepare("UPDATE customers SET points=points+? WHERE id=?")
            ->execute([$pointsEarned, $customerId]);
    }

    // 10) Upload comprovante
    $comprovanteUrl = '';
    if (isset($_FILES['comprovanteFile']) && $_FILES['comprovanteFile']['error']===UPLOAD_ERR_OK) {
        $nomeOrig = $_FILES['comprovanteFile']['name'];
        $tmpPath  = $_FILES['comprovanteFile']['tmp_name'];
        $ext = pathinfo($nomeOrig, PATHINFO_EXTENSION);

        $novoNome = 'comp_'.$orderId.'_'.time().'.'.$ext;
        $destPath = __DIR__.'/uploads/'.$novoNome;
        if(!move_uploaded_file($tmpPath, $destPath)) {
            throw new Exception("Falha ao mover o comprovante para /uploads/.");
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $comprovanteUrl = $protocol.$host.'/uploads/'.$novoNome;

        $pdo->prepare("UPDATE orders SET comprovante_url=? WHERE id=?")
            ->execute([$comprovanteUrl, $orderId]);
    }

    // 11) Commit
    $pdo->commit();

    // 12) Enviar a “Mensagem Interna (Somente Custo)” IGUAL ao detail
    $msgInterna = gerarMensagemInterna($pdo, $orderId);

    // Se existir comprovante, adiciona o link no final do texto
    if (!empty($comprovanteUrl)) {
        $msgInterna .= "\n\nComprovante: ".$comprovanteUrl;
    }

    // Manda para o administrador
    $numeroAdm = '351932356037';
    enviarMensagemWascript($numeroAdm, $msgInterna);

    // 13) Retorno final
    echo json_encode([
        'sucesso' => true,
        'pedidoId' => $orderId,
        'comprovanteUrl' => $comprovanteUrl
    ]);

} catch (Exception $e) {
    if($pdo->inTransaction()){
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}
