<?php
// /admin/produtos.php

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

    // Carregar lista de MARCAS (para <select brand_id>)
    $stmtBrands = $pdo->query("SELECT * FROM brands ORDER BY name");
    $brands = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

    // Se for inserir/editar
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $action = $_POST['action'] ?? '';

      if ($action === 'add') {
        // Inserir novo produto
        $brand_id    = intval($_POST['brand_id']);
        $name        = $_POST['name']        ?? '';
        $description = $_POST['description'] ?? '';
        $price       = floatval($_POST['price'] ?? 0);
        $promo_price = floatval($_POST['promo_price'] ?? 0);  // (NOVO) Promo price
        $cost        = floatval($_POST['cost']  ?? 0);
        $category    = $_POST['category']    ?? '';
        $active      = isset($_POST['active']) ? 1 : 0;
        $image_url   = $_POST['image_url']   ?? '';

        // Agora incluímos também "promo_price" na inserção
        $stmtAdd = $pdo->prepare("
            INSERT INTO products 
              (brand_id, name, description, price, promo_price, cost, category, active, image_url)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmtAdd->execute([
            $brand_id, $name, $description,
            $price, $promo_price, $cost,
            $category, $active, $image_url
        ]);

      } elseif ($action === 'edit') {
        // Editar produto
        $id          = intval($_POST['id']);
        $brand_id    = intval($_POST['brand_id']);
        $name        = $_POST['name']        ?? '';
        $description = $_POST['description'] ?? '';
        $price       = floatval($_POST['price'] ?? 0);
        $promo_price = floatval($_POST['promo_price'] ?? 0); // (NOVO)
        $cost        = floatval($_POST['cost']  ?? 0);
        $category    = $_POST['category']    ?? '';
        $active      = isset($_POST['active']) ? 1 : 0;
        $image_url   = $_POST['image_url']   ?? '';

        // Incluímos "promo_price" no UPDATE
        $stmtEdit = $pdo->prepare("
            UPDATE products
            SET brand_id=?, name=?, description=?, price=?, promo_price=?, cost=?,
                category=?, active=?, image_url=?
            WHERE id=?
        ");
        $stmtEdit->execute([
            $brand_id, $name, $description,
            $price, $promo_price, $cost,
            $category, $active, $image_url,
            $id
        ]);

      } elseif ($action === 'delete') {
        // Excluir produto
        $id = intval($_POST['id']);
        $stmtDel = $pdo->prepare("DELETE FROM products WHERE id=?");
        $stmtDel->execute([$id]);
      }

      // Redirecionar para evitar re-envio
      header("Location: produtos.php");
      exit;
    }

    // Se for editar (GET ?edit=ID)
    $editData = null;
    if (isset($_GET['edit'])) {
      $editId = intval($_GET['edit']);
      $stmtGet = $pdo->prepare("SELECT * FROM products WHERE id=?");
      $stmtGet->execute([$editId]);
      $editData = $stmtGet->fetch(PDO::FETCH_ASSOC);
    }

    // Listar todos os produtos
    $stmtList = $pdo->query("
        SELECT p.*, b.name AS brand_name
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        ORDER BY p.id DESC
    ");
    $produtos = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro no banco: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin - Produtos</title>
  <style>
    table { border-collapse: collapse; width: 90%; margin: 10px auto; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background-color: #eee; }
    .btn-excluir {
      background-color: #e23b3b;
      color: #fff;
      padding: 4px 8px;
      border: none;
      cursor: pointer;
    }
    .btn-editar {
      background-color: #39d178;
      color: #fff;
      padding: 4px 8px;
      border: none;
      cursor: pointer;
    }
    .img-thumb {
      max-height: 40px;
      border: 1px solid #ccc;
      border-radius: 3px;
      object-fit: contain;
    }
  </style>
</head>
<body>
  <h1>CRUD - Produtos</h1>
  <p>
    <a href="dashboard.php">Voltar ao Dashboard</a> |
    <a href="index.php">Lista de Pedidos</a>
  </p>

  <table>
    <tr>
      <th>ID</th>
      <th>Marca (brand_id)</th>
      <th>Nome</th>
      <th>Preço</th>
      <th>Promo</th> <!-- (NOVO) exibir promo_price -->
      <th>Cost</th>
      <th>Categoria</th>
      <th>Ativo?</th>
      <th>Imagem</th>
      <th>Ações</th>
    </tr>
    <?php foreach($produtos as $p):
      $promoVal = (isset($p['promo_price']) && $p['promo_price'] > 0)
                    ? number_format($p['promo_price'], 2, ',', '.')
                    : '0,00';
    ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td>
          <?= htmlspecialchars($p['brand_name']) ?>
          (ID <?= $p['brand_id'] ?>)
        </td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td>R$ <?= number_format($p['price'], 2, ',', '.') ?></td>
        <td>R$ <?= $promoVal ?></td>
        <td>R$ <?= number_format($p['cost'], 2, ',', '.') ?></td>
        <td><?= htmlspecialchars($p['category']) ?></td>
        <td><?= $p['active'] ? 'Sim' : 'Não' ?></td>
        <td>
          <?php if(!empty($p['image_url'])): ?>
            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="img" class="img-thumb">
          <?php endif; ?>
        </td>
        <td>
          <a class="btn-editar" href="?edit=<?= $p['id'] ?>">Editar</a>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir produto #<?= $p['id'] ?>?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn-excluir">Excluir</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <hr/>
  <?php if($editData): ?>
    <!-- Formulário de Edição -->
    <h2>Editar Produto #<?= $editData['id'] ?></h2>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= $editData['id'] ?>">

      <label>Marca (brand_id):</label><br/>
      <select name="brand_id">
        <option value="">-- Selecione --</option>
        <?php foreach($brands as $b): ?>
          <option value="<?= $b['id'] ?>"
            <?= ($b['id'] == $editData['brand_id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['name']) ?> (ID <?= $b['id'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <br/><br/>

      <label>Nome:</label><br/>
      <input name="name" style="width:300px"
             value="<?= htmlspecialchars($editData['name']) ?>" required>
      <br/><br/>

      <label>Descrição:</label><br/>
      <textarea name="description" rows="3" cols="40"><?= htmlspecialchars($editData['description']) ?></textarea>
      <br/><br/>

      <label>Preço (price):</label><br/>
      <input name="price" type="number" step="0.01" value="<?= $editData['price'] ?>">
      <br/><br/>

      <!-- NOVO: Campo promo_price -->
      <label>Preço Promocional (promo_price):</label><br/>
      <input name="promo_price" type="number" step="0.01"
             value="<?= isset($editData['promo_price']) ? $editData['promo_price'] : '0' ?>">
      <br/><br/>

      <label>Custo (Cost):</label><br/>
      <input name="cost" type="number" step="0.01" value="<?= $editData['cost'] ?>">
      <br/><br/>

      <label>Categoria:</label><br/>
      <select name="category">
        <option value="">-- Selecione --</option>
        <option value="Produtos Injetáveis"
          <?= ($editData['category'] === 'Produtos Injetáveis' ? 'selected' : '') ?>>
          Produtos Injetáveis
        </option>
        <option value="Produtos Orais"
          <?= ($editData['category'] === 'Produtos Orais' ? 'selected' : '') ?>>
          Produtos Orais
        </option>
        <option value="Combos"
          <?= ($editData['category'] === 'Combos' ? 'selected' : '') ?>>
          Combos
        </option>
        <option value="Produtos Mix"
          <?= ($editData['category'] === 'Produtos Mix' ? 'selected' : '') ?>>
          Produtos Mix
        </option>
      </select>
      <br/><br/>

      <label>Ativo?</label>
      <input type="checkbox" name="active" <?= ($editData['active'] ? 'checked' : '') ?>>
      <br/><br/>

      <label>Imagem (miniatura) do Produto (image_url):</label><br/>
      <input type="text" name="image_url"
             placeholder="URL da imagem do produto"
             value="<?= htmlspecialchars($editData['image_url'] ?? '') ?>">
      <br/><br/>

      <button type="submit">Salvar Alterações</button>
    </form>

  <?php else: ?>
    <!-- Formulário de Inserção -->
    <h2>Inserir Novo Produto</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">

      <label>Marca (brand_id):</label><br/>
      <select name="brand_id">
        <option value="">-- Selecione --</option>
        <?php foreach($brands as $b): ?>
          <option value="<?= $b['id'] ?>">
            <?= htmlspecialchars($b['name']) ?> (ID <?= $b['id'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <br/><br/>

      <label>Nome:</label><br/>
      <input name="name" style="width:300px"
             placeholder="Ex: Landerlan Injetável"
             required>
      <br/><br/>

      <label>Descrição:</label><br/>
      <textarea name="description" rows="3" cols="40"></textarea>
      <br/><br/>

      <label>Preço (price):</label><br/>
      <input name="price" type="number" step="0.01" placeholder="Ex: 99.90">
      <br/><br/>

      <!-- NOVO: Campo promo_price -->
      <label>Preço Promocional (promo_price):</label><br/>
      <input name="promo_price" type="number" step="0.01" placeholder="Ex: 79.90">
      <br/><br/>

      <label>Custo (Cost):</label><br/>
      <input name="cost" type="number" step="0.01" placeholder="Custo interno">
      <br/><br/>

      <label>Categoria:</label><br/>
      <select name="category">
        <option value="">-- Selecione --</option>
        <option value="Produtos Injetáveis">Produtos Injetáveis</option>
        <option value="Produtos Orais">Produtos Orais</option>
        <option value="Combos">Combos</option>
        <option value="Produtos Mix">Produtos Mix</option>
      </select>
      <br/><br/>

      <label>Ativo?</label>
      <input type="checkbox" name="active"> Sim
      <br/><br/>

      <label>Imagem (miniatura) do Produto (image_url):</label><br/>
      <input type="text" name="image_url" placeholder="URL da miniatura do produto">
      <br/><br/>

      <button type="submit">Inserir Produto</button>
    </form>
  <?php endif; ?>
</body>
</html>
