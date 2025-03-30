<?php
// /admin/marcas.php

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

    // Se for inserir/editar
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            // Inserir nova marca
            $slug       = $_POST['slug']        ?? '';
            $name       = $_POST['name']        ?? '';
            $brand_type = $_POST['brand_type']  ?? '';
            $banner     = $_POST['banner']      ?? '';
            $btn_image  = $_POST['btn_image']   ?? '';

            // Ajustamos para inserir também btn_image
            $stmtAdd = $pdo->prepare("INSERT INTO brands (slug, name, brand_type, banner, btn_image)
                                      VALUES (?,?,?,?,?)");
            $stmtAdd->execute([$slug, $name, $brand_type, $banner, $btn_image]);

        } elseif ($action === 'edit') {
            // Editar marca
            $id         = intval($_POST['id']   ?? 0);
            $slug       = $_POST['slug']        ?? '';
            $name       = $_POST['name']        ?? '';
            $brand_type = $_POST['brand_type']  ?? '';
            $banner     = $_POST['banner']      ?? '';
            $btn_image  = $_POST['btn_image']   ?? '';

            // Ajustamos para atualizar também btn_image
            $stmtEdit = $pdo->prepare("UPDATE brands
                                       SET slug=?, name=?, brand_type=?, banner=?, btn_image=?
                                       WHERE id=?");
            $stmtEdit->execute([$slug, $name, $brand_type, $banner, $btn_image, $id]);

        } elseif ($action === 'delete') {
            // Excluir marca
            $id = intval($_POST['id']);
            $stmtDel = $pdo->prepare("DELETE FROM brands WHERE id=?");
            $stmtDel->execute([$id]);
        }

        // Redireciona para evitar re-envio de formulário
        header("Location: marcas.php");
        exit;
    }

    // Se for editar (GET ?edit=ID)
    $editData = null;
    if (isset($_GET['edit'])) {
        $editId = intval($_GET['edit']);
        $stmtGet = $pdo->prepare("SELECT * FROM brands WHERE id=?");
        $stmtGet->execute([$editId]);
        $editData = $stmtGet->fetch(PDO::FETCH_ASSOC);
    }

    // Listar todas as marcas
    $stmtList = $pdo->query("SELECT * FROM brands ORDER BY id DESC");
    $marcas = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro no banco: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin - Marcas</title>
  <style>
    table {
      border-collapse: collapse;
      width: 90%;
      margin: 10px auto;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px;
    }
    th {
      background-color: #eee;
    }
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
    .form-section {
      max-width: 600px;
      margin: 20px auto;
      padding: 10px;
      border: 1px solid #ccc;
    }
    .form-section label {
      display: block;
      margin-top: 8px;
    }
    .form-section input[type="text"] {
      width: 100%;
      padding: 6px;
      margin-bottom: 6px;
    }
    .form-section select {
      padding: 6px;
      margin-bottom: 6px;
      width: 100%;
    }
    img.thumb {
      max-height: 40px;
      border: 1px solid #ccc;
      border-radius: 3px;
    }
  </style>
</head>
<body>
  <h1>CRUD - Marcas</h1>
  <p><a href="dashboard.php">Voltar ao Dashboard</a> | <a href="index.php">Lista de Pedidos</a></p>

  <table>
    <tr>
      <th>ID</th>
      <th>Slug</th>
      <th>Nome</th>
      <th>Tipo</th>
      <th>Banner</th>
      <th>Botão (btn_image)</th>
      <th>Ações</th>
    </tr>
    <?php foreach($marcas as $m): ?>
    <tr>
      <td><?= $m['id'] ?></td>
      <td><?= htmlspecialchars($m['slug']) ?></td>
      <td><?= htmlspecialchars($m['name']) ?></td>
      <td><?= htmlspecialchars($m['brand_type']) ?></td>
      <td>
        <?php if($m['banner']): ?>
          <img src="<?= htmlspecialchars($m['banner']) ?>" alt="banner" class="thumb">
        <?php endif; ?>
      </td>
      <td>
        <?php if(!empty($m['btn_image'])): ?>
          <img src="<?= htmlspecialchars($m['btn_image']) ?>" alt="btn_image" class="thumb">
        <?php endif; ?>
      </td>
      <td>
        <a class="btn-editar" href="?edit=<?= $m['id'] ?>">Editar</a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir marca #<?= $m['id'] ?>?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $m['id'] ?>">
          <button type="submit" class="btn-excluir">Excluir</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <hr/>

  <?php if($editData): ?>
    <!-- Formulário de Edição -->
    <div class="form-section">
      <h2>Editar Marca #<?= $editData['id'] ?></h2>
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= $editData['id'] ?>">

        <label>Slug:</label>
        <input type="text" name="slug" value="<?= htmlspecialchars($editData['slug']) ?>" required>

        <label>Nome:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($editData['name']) ?>" required>

        <label>Tipo:</label>
        <!-- SELECT FIXO -->
        <select name="brand_type">
          <option value="">-- Selecione --</option>
          <option value="Marcas Importadas"
            <?= ($editData['brand_type'] === 'Marcas Importadas' ? 'selected' : '') ?>>
            Marcas Importadas
          </option>
          <option value="Marcas Premium"
            <?= ($editData['brand_type'] === 'Marcas Premium' ? 'selected' : '') ?>>
            Marcas Premium
          </option>
          <option value="Marcas Nacionais"
            <?= ($editData['brand_type'] === 'Marcas Nacionais' ? 'selected' : '') ?>>
            Marcas Nacionais
          </option>
          <option value="Diversos"
            <?= ($editData['brand_type'] === 'Diversos' ? 'selected' : '') ?>>
            Diversos
          </option>
        </select>

        <label>Banner URL:</label>
        <input type="text" name="banner" value="<?= htmlspecialchars($editData['banner']) ?>">

        <label>Imagem do Botão (btn_image):</label>
        <input type="text" name="btn_image" placeholder="URL para imagem menor" value="<?= htmlspecialchars($editData['btn_image'] ?? '') ?>">

        <br/><br/>
        <button type="submit">Salvar Alterações</button>
      </form>
    </div>

  <?php else: ?>
    <!-- Formulário de Inserção -->
    <div class="form-section">
      <h2>Inserir Nova Marca</h2>
      <form method="POST">
        <input type="hidden" name="action" value="add">

        <label>Slug:</label>
        <input type="text" name="slug" placeholder="ex: landerlan" required>

        <label>Nome:</label>
        <input type="text" name="name" placeholder="ex: Landerlan" required>

        <label>Tipo:</label>
        <!-- SELECT FIXO -->
        <select name="brand_type">
          <option value="">-- Selecione --</option>
          <option value="Marcas Importadas">Marcas Importadas</option>
          <option value="Marcas Premium">Marcas Premium</option>
          <option value="Marcas Nacionais">Marcas Nacionais</option>
          <option value="Diversos">Diversos</option>
        </select>

        <label>Banner URL:</label>
        <input type="text" name="banner" placeholder="http://...">

        <label>Imagem do Botão (btn_image):</label>
        <input type="text" name="btn_image" placeholder="URL para imagem menor">

        <br/><br/>
        <button type="submit">Inserir Marca</button>
      </form>
    </div>
  <?php endif; ?>
</body>
</html>
