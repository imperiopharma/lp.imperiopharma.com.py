<?php
/*****************************************************
 * ARQUIVO UNICO: MARCAS + PRODUTOS
 * - Conexão BD
 * - CRUD de brands e products
 * - Layout e CSS em um só lugar
 * - Tabs em JS puro
 * - Agora com campo promo_price (Preço Promocional)
 *****************************************************/

// ----- [CONFIGURAR BD] -----
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
    die("Erro na conexão: " . $e->getMessage());
}

/*****************************************************
 * TRATAR POST: brand vs product
 *****************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $which  = $_POST['which']  ?? '';  // "brand" ou "product"
    $action = $_POST['action'] ?? '';

    // ----- CRUD para MARCAS -----
    if ($which === 'brand') {
        if ($action === 'add') {
            $slug       = $_POST['slug']        ?? '';
            $name       = $_POST['name']        ?? '';
            $brand_type = $_POST['brand_type']  ?? '';
            $banner     = $_POST['banner']      ?? '';
            $btn_image  = $_POST['btn_image']   ?? '';

            $stmt = $pdo->prepare("
              INSERT INTO brands (slug, name, brand_type, banner, btn_image)
              VALUES (?,?,?,?,?)
            ");
            $stmt->execute([$slug, $name, $brand_type, $banner, $btn_image]);

        } elseif ($action === 'edit') {
            $id         = intval($_POST['id'] ?? 0);
            $slug       = $_POST['slug']        ?? '';
            $name       = $_POST['name']        ?? '';
            $brand_type = $_POST['brand_type']  ?? '';
            $banner     = $_POST['banner']      ?? '';
            $btn_image  = $_POST['btn_image']   ?? '';

            $stmt = $pdo->prepare("
              UPDATE brands
              SET slug=?, name=?, brand_type=?, banner=?, btn_image=?
              WHERE id=?
            ");
            $stmt->execute([$slug, $name, $brand_type, $banner, $btn_image, $id]);

        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM brands WHERE id=?");
            $stmt->execute([$id]);
        }
        // Opcional: header("Location: marcas_e_produtos.php#tabMarcas"); exit;
    }

    // ----- CRUD para PRODUTOS -----
    if ($which === 'product') {
        if ($action === 'add') {
            $brand_id    = intval($_POST['brand_id']    ?? 0);
            $name        = $_POST['name']        ?? '';
            $description = $_POST['description'] ?? '';
            $price       = floatval($_POST['price']       ?? 0);
            $promo_price = floatval($_POST['promo_price'] ?? 0); // NOVO: preço promocional
            $cost        = floatval($_POST['cost']        ?? 0);
            $category    = $_POST['category']    ?? '';
            $active      = isset($_POST['active']) ? 1 : 0;
            $image_url   = $_POST['image_url']   ?? '';

            $stmt = $pdo->prepare("
              INSERT INTO products
                (brand_id, name, description,
                 price, promo_price, cost,
                 category, active, image_url)
              VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $brand_id, $name, $description,
                $price, $promo_price, $cost,
                $category, $active, $image_url
            ]);

        } elseif ($action === 'edit') {
            $id          = intval($_POST['id'] ?? 0);
            $brand_id    = intval($_POST['brand_id'] ?? 0);
            $name        = $_POST['name']        ?? '';
            $description = $_POST['description'] ?? '';
            $price       = floatval($_POST['price']       ?? 0);
            $promo_price = floatval($_POST['promo_price'] ?? 0);
            $cost        = floatval($_POST['cost']        ?? 0);
            $category    = $_POST['category']    ?? '';
            $active      = isset($_POST['active']) ? 1 : 0;
            $image_url   = $_POST['image_url']   ?? '';

            $stmt = $pdo->prepare("
              UPDATE products
              SET brand_id=?, name=?, description=?,
                  price=?, promo_price=?, cost=?,
                  category=?, active=?, image_url=?
              WHERE id=?
            ");
            $stmt->execute([
                $brand_id, $name, $description,
                $price, $promo_price, $cost,
                $category, $active, $image_url,
                $id
            ]);

        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
            $stmt->execute([$id]);
        }
        // Opcional: header("Location: marcas_e_produtos.php#tabProdutos"); exit;
    }
}

/*****************************************************
 * CARREGAR MARCAS e PRODUTOS
 *****************************************************/
// 1) Carregar Marcas
$stmtBrands = $pdo->query("SELECT * FROM brands ORDER BY id DESC");
$marcas = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

// Se for edição de alguma marca (GET ?edit_brand=ID)
$editMarca = null;
if (isset($_GET['edit_brand'])) {
    $editIdB = intval($_GET['edit_brand']);
    $stmtB = $pdo->prepare("SELECT * FROM brands WHERE id=?");
    $stmtB->execute([$editIdB]);
    $editMarca = $stmtB->fetch(PDO::FETCH_ASSOC);
}

// 2) Carregar Produtos
$stmtProd = $pdo->query("
  SELECT p.*,
         b.name AS brand_name
  FROM products p
  LEFT JOIN brands b ON p.brand_id = b.id
  ORDER BY b.name ASC, p.name ASC
");
$allProducts = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Se for edição de algum produto (GET ?edit_product=ID)
$editProduto = null;
if (isset($_GET['edit_product'])) {
    $editIdP = intval($_GET['edit_product']);
    $stmtP = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmtP->execute([$editIdP]);
    $editProduto = $stmtP->fetch(PDO::FETCH_ASSOC);
}

// Agrupar $allProducts por brand_name
$groupedProducts = [];
foreach ($allProducts as $prod) {
    $bName = $prod['brand_name'] ?? 'SEM MARCA';
    if (!isset($groupedProducts[$bName])) {
        $groupedProducts[$bName] = [];
    }
    $groupedProducts[$bName][] = $prod;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <title>Gerenciar Marcas & Produtos</title>
  <style>
    /* ========= RESET BÁSICO ========= */
    * {
      margin: 0; 
      padding: 0; 
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      color: #333;
      padding: 10px;
    }

    h1 {
      font-size: 1.6rem;
      margin-bottom: 15px;
      color: #333;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    h2 {
      font-size: 1.3rem;
      margin: 20px 0 10px 0;
      color: #222;
    }
    h3 {
      font-size: 1.15rem;
      margin: 18px 0 8px 0;
    }
    h4 {
      font-size: 1rem;
      margin: 18px 0 8px 0;
      color: #333;
    }

    .tab-nav {
      display: flex;
      margin-bottom: 15px;
      gap: 5px;
    }
    .tab-nav button {
      background: #ddd;
      border: 1px solid #ccc;
      padding: 8px 12px;
      cursor: pointer;
      border-radius: 4px 4px 0 0;
      font-weight: 600;
      transition: background 0.2s;
    }
    .tab-nav button.active {
      background: #fff;
      border-bottom: 1px solid #fff;
    }
    .tab-content {
      background: #fff;
      border: 1px solid #ccc;
      border-radius: 0 4px 4px 4px;
      padding: 15px;
    }
    .tab-pane {
      display: none;
    }
    .tab-pane.active {
      display: block;
    }

    .table-container {
      width: 100%;
      overflow-x: auto;
      margin-bottom: 15px;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      min-width: 600px;
      background: #fff;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px 10px;
      font-size: 0.9rem;
      vertical-align: middle;
    }
    th {
      background: #f9f9f9;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.8rem;
      color: #555;
    }
    tr:nth-child(even) {
      background: #fafafa;
    }

    .btn {
      display: inline-block;
      padding: 6px 10px;
      border: none;
      border-radius: 4px;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
      margin: 2px;
    }
    .btn:hover {
      opacity: 0.9;
    }
    .btn-success {
      background-color: #28a745;
      color: #fff;
    }
    .btn-danger {
      background-color: #dc3545;
      color: #fff;
    }
    .btn-primary {
      background-color: #007bff;
      color: #fff;
    }

    .form-grid {
      display: grid;
      gap: 10px;
    }
    .form-grid label {
      font-size: 0.9rem;
      font-weight: 600;
      margin-bottom: 3px;
    }
    .form-grid input[type="text"],
    .form-grid input[type="number"],
    .form-grid textarea,
    .form-grid select {
      width: 100%;
      padding: 8px;
      font-size: 0.9rem;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    .img-thumb {
      max-height: 50px;
      border: 1px solid #ccc;
      border-radius: 4px;
      object-fit: contain;
    }

    .brand-block {
      margin-bottom: 20px;
    }
    .brand-block h4 {
      margin-top: 10px;
      font-weight: 600;
    }

    @media (max-width: 600px) {
      body {
        padding: 5px;
      }
      .tab-nav button {
        font-size: 0.8rem;
        padding: 6px;
      }
      table {
        min-width: 420px;
      }
      td, th {
        padding: 6px;
      }
      .btn {
        font-size: 0.8rem;
        padding: 5px 8px;
      }
      .form-grid input, .form-grid select, .form-grid textarea {
        font-size: 0.85rem;
      }
    }
  </style>
</head>
<body>

<h1>Gerenciar Marcas & Produtos</h1>

<!-- Nav de Abas -->
<div class="tab-nav">
  <button class="tab-btn active" data-target="tabMarcas">Marcas</button>
  <button class="tab-btn" data-target="tabProdutos">Produtos</button>
</div>

<div class="tab-content">
  <!-- ABA MARCAS -->
  <div class="tab-pane active" id="tabMarcas">
    <h2>Lista de Marcas</h2>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Slug</th>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Banner</th>
            <th>Botão (btn_image)</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$marcas): ?>
            <tr>
              <td colspan="7" style="text-align:center;">Nenhuma marca cadastrada.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($marcas as $m): ?>
              <tr>
                <td><?= $m['id'] ?></td>
                <td><?= htmlspecialchars($m['slug']) ?></td>
                <td><?= htmlspecialchars($m['name']) ?></td>
                <td><?= htmlspecialchars($m['brand_type']) ?></td>
                <td>
                  <?php if($m['banner']): ?>
                    <img src="<?= htmlspecialchars($m['banner']) ?>" class="img-thumb" alt="banner">
                  <?php endif; ?>
                </td>
                <td>
                  <?php if(!empty($m['btn_image'])): ?>
                    <img src="<?= htmlspecialchars($m['btn_image']) ?>" class="img-thumb" alt="btn_image">
                  <?php endif; ?>
                </td>
                <td>
                  <a href="?edit_brand=<?= $m['id'] ?>#tabMarcas" class="btn btn-success">Editar</a>
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Excluir marca #<?= $m['id'] ?>?');">
                    <input type="hidden" name="which"  value="brand">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= $m['id'] ?>">
                    <button type="submit" class="btn btn-danger">
                      Excluir
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <hr/>

    <!-- Form de Inserção OU de Edição de Marca -->
    <?php if ($editMarca): ?>
      <h3>Editar Marca #<?= $editMarca['id'] ?></h3>
      <form method="POST" class="form-grid">
        <input type="hidden" name="which"  value="brand">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     value="<?= $editMarca['id'] ?>">

        <label>Slug:</label>
        <input type="text" name="slug"
               value="<?= htmlspecialchars($editMarca['slug']) ?>"
               required>

        <label>Nome:</label>
        <input type="text" name="name"
               value="<?= htmlspecialchars($editMarca['name']) ?>"
               required>

        <label>Tipo:</label>
        <select name="brand_type">
          <option value="">-- Selecione --</option>
          <option value="Marcas Importadas"
            <?= ($editMarca['brand_type'] === 'Marcas Importadas' ? 'selected' : '') ?>>
            Marcas Importadas
          </option>
          <option value="Marcas Premium"
            <?= ($editMarca['brand_type'] === 'Marcas Premium' ? 'selected' : '') ?>>
            Marcas Premium
          </option>
          <option value="Marcas Nacionais"
            <?= ($editMarca['brand_type'] === 'Marcas Nacionais' ? 'selected' : '') ?>>
            Marcas Nacionais
          </option>
          <option value="Diversos"
            <?= ($editMarca['brand_type'] === 'Diversos' ? 'selected' : '') ?>>
            Diversos
          </option>
        </select>

        <label>Banner URL:</label>
        <input type="text" name="banner"
               value="<?= htmlspecialchars($editMarca['banner']) ?>">

        <label>Imagem do Botão (btn_image):</label>
        <input type="text" name="btn_image"
               value="<?= htmlspecialchars($editMarca['btn_image'] ?? '') ?>">

        <button type="submit" class="btn btn-primary">
          Salvar Alterações
        </button>
      </form>

    <?php else: ?>
      <h3>Inserir Nova Marca</h3>
      <form method="POST" class="form-grid">
        <input type="hidden" name="which"  value="brand">
        <input type="hidden" name="action" value="add">

        <label>Slug:</label>
        <input type="text" name="slug"
               placeholder="ex: landerlan"
               required>

        <label>Nome:</label>
        <input type="text" name="name"
               placeholder="ex: Landerlan"
               required>

        <label>Tipo:</label>
        <select name="brand_type">
          <option value="">-- Selecione --</option>
          <option value="Marcas Importadas">Marcas Importadas</option>
          <option value="Marcas Premium">Marcas Premium</option>
          <option value="Marcas Nacionais">Marcas Nacionais</option>
          <option value="Diversos">Diversos</option>
        </select>

        <label>Banner URL:</label>
        <input type="text" name="banner"
               placeholder="http://...">

        <label>Imagem do Botão (btn_image):</label>
        <input type="text" name="btn_image"
               placeholder="URL para imagem menor">

        <button type="submit" class="btn btn-primary">
          Inserir Marca
        </button>
      </form>
    <?php endif; ?>
  </div><!-- /tabMarcas -->


  <!-- ABA PRODUTOS -->
  <div class="tab-pane" id="tabProdutos">
    <h2>Lista de Produtos</h2>

    <?php if (!$allProducts): ?>
      <p>Nenhum produto cadastrado.</p>
    <?php else: ?>
      <?php foreach ($groupedProducts as $brandName => $lista): ?>
        <div class="brand-block">
          <h4>Marca: <?= htmlspecialchars($brandName) ?></h4>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nome</th>
                  <th>Preço</th>
                  <th>Promo</th> <!-- NOVO: Preço Promocional -->
                  <th>Cost</th>
                  <th>Categoria</th>
                  <th>Ativo?</th>
                  <th>Imagem</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lista as $p):
                  $promoVal = (isset($p['promo_price']) && $p['promo_price'] > 0)
                                ? number_format($p['promo_price'], 2, ',', '.')
                                : '0,00';
                ?>
                  <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>R$ <?= number_format($p['price'], 2, ',', '.') ?></td>
                    <td>R$ <?= $promoVal ?></td>
                    <td>R$ <?= number_format($p['cost'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($p['category']) ?></td>
                    <td><?= ($p['active']) ? 'Sim' : 'Não' ?></td>
                    <td>
                      <?php if(!empty($p['image_url'])): ?>
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" class="img-thumb" alt="prod">
                      <?php endif; ?>
                    </td>
                    <td>
                      <a href="?edit_product=<?= $p['id'] ?>#tabProdutos" class="btn btn-success">Editar</a>
                      <form method="POST" style="display:inline;"
                            onsubmit="return confirm('Excluir produto #<?= $p['id'] ?>?');">
                        <input type="hidden" name="which"  value="product">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id"     value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                          Excluir
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div><!-- /brand-block -->
      <?php endforeach; ?>
    <?php endif; ?>

    <hr/>

    <!-- Formulário para Editar OU Inserir Produto -->
    <?php if ($editProduto): ?>
      <h3>Editar Produto #<?= $editProduto['id'] ?></h3>
      <form method="POST" class="form-grid">
        <input type="hidden" name="which"  value="product">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     value="<?= $editProduto['id'] ?>">

        <label>Marca (brand_id):</label>
        <select name="brand_id">
          <option value="">-- Selecione --</option>
          <?php foreach($marcas as $b): ?>
            <option value="<?= $b['id'] ?>"
              <?= ($b['id'] == $editProduto['brand_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?> (ID <?= $b['id'] ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <label>Nome:</label>
        <input type="text" name="name" required
               value="<?= htmlspecialchars($editProduto['name']) ?>">

        <label>Descrição:</label>
        <textarea name="description" rows="3"><?= htmlspecialchars($editProduto['description']) ?></textarea>

        <label>Preço (price):</label>
        <input type="number" step="0.01" name="price"
               value="<?= $editProduto['price'] ?>">

        <!-- NOVO: promo_price -->
        <label>Preço Promocional (promo_price):</label>
        <input type="number" step="0.01" name="promo_price"
               value="<?= isset($editProduto['promo_price']) ? $editProduto['promo_price'] : '0' ?>">

        <label>Custo (cost):</label>
        <input type="number" step="0.01" name="cost"
               value="<?= $editProduto['cost'] ?>">

        <label>Categoria:</label>
        <select name="category">
          <option value="">-- Selecione --</option>
          <option value="Produtos Injetáveis"
            <?= ($editProduto['category'] === 'Produtos Injetáveis' ? 'selected' : '') ?>>
            Produtos Injetáveis
          </option>
          <option value="Produtos Orais"
            <?= ($editProduto['category'] === 'Produtos Orais' ? 'selected' : '') ?>>
            Produtos Orais
          </option>
          <option value="Combos"
            <?= ($editProduto['category'] === 'Combos' ? 'selected' : '') ?>>
            Combos
          </option>
          <option value="Produtos Mix"
            <?= ($editProduto['category'] === 'Produtos Mix' ? 'selected' : '') ?>>
            Produtos Mix
          </option>
        </select>

        <label>Ativo?</label>
        <input type="checkbox" name="active" <?= ($editProduto['active'] ? 'checked' : '') ?>> Sim

        <label>Imagem (URL):</label>
        <input type="text" name="image_url"
               value="<?= htmlspecialchars($editProduto['image_url'] ?? '') ?>">

        <button type="submit" class="btn btn-primary">
          Salvar Alterações
        </button>
      </form>

    <?php else: ?>
      <h3>Inserir Novo Produto</h3>
      <form method="POST" class="form-grid">
        <input type="hidden" name="which"  value="product">
        <input type="hidden" name="action" value="add">

        <label>Marca (brand_id):</label>
        <select name="brand_id">
          <option value="">-- Selecione --</option>
          <?php foreach($marcas as $b): ?>
            <option value="<?= $b['id'] ?>">
              <?= htmlspecialchars($b['name']) ?> (ID <?= $b['id'] ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <label>Nome:</label>
        <input type="text" name="name" required
               placeholder="Ex: Landerlan Injetável">

        <label>Descrição:</label>
        <textarea name="description" rows="3"></textarea>

        <label>Preço (price):</label>
        <input type="number" step="0.01" name="price" placeholder="Ex: 99.90">

        <!-- NOVO: promo_price -->
        <label>Preço Promocional (promo_price):</label>
        <input type="number" step="0.01" name="promo_price" placeholder="Ex: 79.90">

        <label>Custo (cost):</label>
        <input type="number" step="0.01" name="cost" placeholder="Custo interno">

        <label>Categoria:</label>
        <select name="category">
          <option value="">-- Selecione --</option>
          <option value="Produtos Injetáveis">Produtos Injetáveis</option>
          <option value="Produtos Orais">Produtos Orais</option>
          <option value="Combos">Combos</option>
          <option value="Produtos Mix">Produtos Mix</option>
        </select>

        <label>Ativo?</label>
        <input type="checkbox" name="active"> Sim

        <label>Imagem (URL):</label>
        <input type="text" name="image_url"
               placeholder="URL da miniatura do produto">

        <button type="submit" class="btn btn-primary">
          Inserir Produto
        </button>
      </form>
    <?php endif; ?>
  </div><!-- /tabProdutos -->
</div><!-- /.tab-content -->

<script>
/***************************************************************
 * SCRIPT PARA ALTERNAR ABAS (SEM RECARREGAR)
 * - Cada botão tem .tab-btn e data-target (tabMarcas, tabProdutos)
 * - Cada seção (tab-pane) tem ID correspondente
 **************************************************************/
const tabButtons = document.querySelectorAll('.tab-btn');
const tabPanes   = document.querySelectorAll('.tab-pane');

function showTab(tabId) {
  tabButtons.forEach(btn => btn.classList.remove('active'));
  tabPanes.forEach(p => p.classList.remove('active'));

  const paneAlvo = document.getElementById(tabId);
  if (paneAlvo) {
    paneAlvo.classList.add('active');
  }
  const btnAlvo = [...tabButtons].find(bt => bt.dataset.target === tabId);
  if (btnAlvo) {
    btnAlvo.classList.add('active');
  }
}

tabButtons.forEach(button => {
  button.addEventListener('click', () => {
    const target = button.getAttribute('data-target');
    showTab(target);
  });
});

// Se tiver hash na URL, ex: #tabProdutos, abre direto
window.addEventListener('load', () => {
  const hash = window.location.hash.replace('#','');
  if (hash) {
    showTab(hash);
  }
});
</script>

</body>
</html>
