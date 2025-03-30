<?php
/********************************************************
 * validarCupom.php
 * 
 * Recebe (via POST):
 *   - cupom: string com o código do cupom
 *   - carrinhoJson: JSON dos itens do carrinho
 *       (id, nome, preco, quantidade, marca, categoria)
 * 
 * Responde em JSON:
 *   { "valido": bool, "descontoCalculado": float, "mensagem": "string" }
 ********************************************************/

// Ajuste se necessário
header('Content-Type: application/json; charset=utf-8');

// 1) Verifica se foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
      'valido' => false,
      'descontoCalculado' => 0,
      'mensagem' => 'Método inválido (use POST).'
    ]);
    exit;
}

// 2) Pega os dados
$cupom = trim($_POST['cupom'] ?? '');
$carrinhoJson = $_POST['carrinhoJson'] ?? '[]';

$carrinho = json_decode($carrinhoJson, true);
if (!is_array($carrinho)) {
    // Falha ao decodificar JSON
    echo json_encode([
      'valido' => false,
      'descontoCalculado' => 0,
      'mensagem' => 'Carrinho inválido (JSON).'
    ]);
    exit;
}
if ($cupom === '') {
    echo json_encode([
      'valido' => false,
      'descontoCalculado' => 0,
      'mensagem' => 'Nenhum cupom informado.'
    ]);
    exit;
}

// 3) Conexão BD
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
    echo json_encode([
      'valido' => false,
      'descontoCalculado' => 0,
      'mensagem' => 'Erro ao conectar no BD: '.$e->getMessage()
    ]);
    exit;
}

// 4) Busca o cupom no BD
try {
    $sqlCupom = "SELECT * FROM coupons WHERE code=:cd AND active=1 LIMIT 1";
    $stmtCup = $pdo->prepare($sqlCupom);
    $stmtCup->execute([':cd' => $cupom]);
    $cupRow = $stmtCup->fetch(PDO::FETCH_ASSOC);

    if (!$cupRow) {
        echo json_encode([
          'valido' => false,
          'descontoCalculado' => 0,
          'mensagem' => 'Cupom não encontrado ou inativo.'
        ]);
        exit;
    }

    // Lê dados do cupom
    $cupomId       = (int)$cupRow['id'];
    $discountType  = $cupRow['discount_type'];  // 'FIXO' ou 'PORCENT'
    $discountValue = (float)$cupRow['discount_value'];
    $appliesTo     = $cupRow['applies_to'];     // 'TODAS','MARCA','CATEGORIA','MARCA_E_CATEGORIA'
    $maxDiscount   = isset($cupRow['max_discount']) ? (float)$cupRow['max_discount'] : null;

    // Carrega as brands associadas
    $stmtCB = $pdo->prepare("SELECT brand_id FROM coupon_brands WHERE coupon_id=?");
    $stmtCB->execute([$cupomId]);
    $brandsCoupon = $stmtCB->fetchAll(PDO::FETCH_COLUMN, 0); // ex.: [4,5,...]

    // Carrega as categories associadas (coluna "category_name")
    $stmtCat = $pdo->prepare("SELECT category_name FROM coupon_categories WHERE coupon_id=?");
    $stmtCat->execute([$cupomId]);
    $catsCoupon = $stmtCat->fetchAll(PDO::FETCH_COLUMN, 0);  // ex.: ['Produtos Injetáveis','Combos']

} catch (Exception $e) {
    echo json_encode([
      'valido' => false,
      'descontoCalculado' => 0,
      'mensagem' => 'Erro ao buscar cupom: '.$e->getMessage()
    ]);
    exit;
}

// 5) Percorrer carrinho para calcular valor elegível
$totalCarrinho = 0;
$valorElegivel = 0; // soma do subtotal de itens que se enquadram no cupom

foreach ($carrinho as $prod) {
    $qtd   = (int)($prod['quantidade'] ?? 0);
    $pItem = (float)($prod['preco'] ?? 0);
    $nomeMarca = trim($prod['marca'] ?? '');
    $nomeCategoria = trim($prod['categoria'] ?? '');

    // 5a) Descobrir brand_id real no BD, procurando pela brand name:
    $brandId = 0;
    if ($nomeMarca !== '') {
        try {
            $sqlFindB = "SELECT id FROM brands WHERE name=:nm LIMIT 1";
            $stB = $pdo->prepare($sqlFindB);
            $stB->execute([':nm' => $nomeMarca]);
            $found = $stB->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $brandId = (int)$found['id'];
            }
        } catch (Exception $exBrand) {
            // Se der erro, brandId=0 => item não é elegível se cupom for MARCA
        }
    }

    $subtotalItem = $qtd * $pItem;
    $totalCarrinho += $subtotalItem;

    // 5b) Verifica se esse item se encaixa no "applies_to"
    $eligivel = false;
    switch ($appliesTo) {
        case 'TODAS':
            $eligivel = true;
            break;

        case 'MARCA':
            // checa se brandId está em $brandsCoupon
            if (in_array($brandId, $brandsCoupon)) {
                $eligivel = true;
            }
            break;

        case 'CATEGORIA':
            // checa se $nomeCategoria está em $catsCoupon
            if (in_array($nomeCategoria, $catsCoupon)) {
                $eligivel = true;
            }
            break;

        case 'MARCA_E_CATEGORIA':
            // item precisa ter brandId contido em $brandsCoupon
            // E $nomeCategoria contido em $catsCoupon
            if (in_array($brandId, $brandsCoupon) && in_array($nomeCategoria, $catsCoupon)) {
                $eligivel = true;
            }
            break;
    }

    if ($eligivel) {
       $valorElegivel += $subtotalItem;
    }
}

// 6) Calcula valor do desconto
$descontoCalculado = 0;
if ($valorElegivel > 0) {
    if ($discountType === 'FIXO') {
        $descontoCalculado = $discountValue;
        if ($descontoCalculado > $valorElegivel) {
            $descontoCalculado = $valorElegivel;
        }
    } else {
        // PORCENT => discountValue%
        $descontoCalculado = ($valorElegivel * ($discountValue / 100));
    }
    // se houver max_discount, limitamos
    if (!is_null($maxDiscount) && $descontoCalculado > $maxDiscount) {
        $descontoCalculado = $maxDiscount;
    }
}

// Evita negativo
if ($descontoCalculado < 0) {
    $descontoCalculado = 0;
}

// 7) Retorna JSON
echo json_encode([
  'valido' => true,
  'descontoCalculado' => $descontoCalculado,
  'mensagem' => 'Cupom aplicado com sucesso!'
]);
exit;
