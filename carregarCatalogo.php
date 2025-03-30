<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * carregarCatalogo.php
 *
 * Objetivo: Montar um array $catalogo que agrupa cada marca (por slug)
 * e, dentro dela, as categorias e os produtos ativos.
 *
 * Agora, além dos dados anteriores, também retornamos:
 *   - stock: indica a identificação do estoque (ex.: 1, 2, 3, etc.)
 *   - stock_message: mensagem personalizada para o estoque,
 *     que pode ser usada para informar condições especiais (ex.: "Esta marca possui frete especial pois seus produtos saem de um centro de distribuição exclusivo.")
 *
 * Exemplo de resultado JSON:
 * {
 *   "king-pharma": {
 *     "btn_image": "king-btn.png",
 *     "banner": "king-banner.jpg",
 *     "nome": "King Pharma",
 *     "brand_type": "Marcas Nacionais",
 *     "separate_shipping": 1,
 *     "stock": 1,
 *     "stock_message": "As marcas deste estoque podem ser enviadas juntas no mesmo frete.",
 *     "categorias": {
 *       "Testosterona": [
 *         {
 *           "id": 10,
 *           "nome": "Cypionato",
 *           "descricao": "...",
 *           "preco": 150.0,
 *           "promo_price": 99.0,
 *           "imagem": "..."
 *         }
 *       ],
 *       ...
 *     }
 *   },
 *   ...
 * }
 */

// CONFIGURAÇÃO DO BANCO DE DADOS
$dbHost = 'localhost';
$dbName = 'imperiopharma_loja_db';
$dbUser = 'imperiopharma_loja_user';
$dbPass = 'Miguel22446688';

try {
    // 1) Conexão PDO
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2) Buscar todas as marcas, ordenando por sort_order (e depois por nome)
    //    Se desejar filtrar somente marcas ativas, adicione uma cláusula WHERE (por exemplo, se existir uma coluna "active").
    $sqlBrands = "
        SELECT
            slug,
            name,
            brand_type,
            banner,
            btn_image,
            separate_shipping,
            stock,
            stock_message,
            sort_order
        FROM brands
        ORDER BY sort_order ASC, name ASC
    ";
    $stmtB = $pdo->query($sqlBrands);
    $brandsData = $stmtB->fetchAll(PDO::FETCH_ASSOC);

    // 3) Montar o array base do catálogo, usando o slug como chave
    $catalogo = [];
    foreach ($brandsData as $b) {
        // Se o slug estiver vazio, define 'sem-slug'
        $slug = !empty($b['slug']) ? $b['slug'] : 'sem-slug';
        // Se o brand_type estiver vazio, define como 'Diversos'
        $tipoMarca = !empty($b['brand_type']) ? $b['brand_type'] : 'Diversos';

        $catalogo[$slug] = [
            'btn_image'         => $b['btn_image']         ?? '',
            'banner'            => $b['banner']            ?? '',
            'nome'              => $b['name']              ?? 'Marca Desconhecida',
            'brand_type'        => $tipoMarca,
            'separate_shipping' => (int)($b['separate_shipping'] ?? 0),
            'stock'             => (int)($b['stock'] ?? 0),
            'stock_message'     => $b['stock_message']     ?? '',
            'categorias'        => []
        ];
    }

    // 4) Buscar os produtos ativos
    //    Ajuste a cláusula WHERE conforme necessário (por exemplo, para filtrar marcas ou produtos inativos).
    $sqlProducts = "
        SELECT
            p.id AS product_id,
            p.name AS product_name,
            p.description,
            p.price,
            p.promo_price,
            p.category,
            p.image_url,
            b.slug AS brand_slug,
            b.name AS brand_name,
            b.sort_order
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.active = 1
        ORDER BY b.sort_order ASC, p.category ASC, p.name ASC
    ";
    $stmtP = $pdo->query($sqlProducts);
    $prodData = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // 5) Inserir os produtos dentro do catálogo, agrupando-os por categoria dentro de cada marca
    foreach ($prodData as $row) {
        $brandSlug = !empty($row['brand_slug']) ? $row['brand_slug'] : 'sem-marca';
        $category  = !empty($row['category']) ? $row['category'] : 'Sem Categoria';

        // Se a marca não existir no catálogo, cria um registro básico para ela
        if (!isset($catalogo[$brandSlug])) {
            $catalogo[$brandSlug] = [
                'btn_image'         => '',
                'banner'            => '',
                'nome'              => $row['brand_name'] ?? 'Marca Sem Nome',
                'brand_type'        => 'Diversos',
                'separate_shipping' => 0,
                'stock'             => 0,
                'stock_message'     => '',
                'categorias'        => []
            ];
        }

        // Se a categoria não existir, cria o array para ela
        if (!isset($catalogo[$brandSlug]['categorias'][$category])) {
            $catalogo[$brandSlug]['categorias'][$category] = [];
        }

        // Monta o objeto do produto
        $produto = [
            'id'          => (int)$row['product_id'],
            'nome'        => $row['product_name']  ?? '',
            'descricao'   => $row['description']   ?? '',
            'preco'       => (float)$row['price'],
            'promo_price' => (float)($row['promo_price'] ?? 0),
            'imagem'      => !empty($row['image_url'])
                             ? $row['image_url']
                             : 'https://via.placeholder.com/120x80?text=' . urlencode($row['product_name'] ?? 'NoName')
        ];

        // Adiciona o produto à respectiva categoria
        $catalogo[$brandSlug]['categorias'][$category][] = $produto;
    }

    // 6) Retorna o catálogo em formato JSON
    echo json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro'     => true,
        'mensagem' => $e->getMessage()
    ]);
}
?>
