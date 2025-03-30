<?php
/***************************************************************
 * painel_marcas_editar.php
 * 
 * Exibe 1 marca por vez — ou formulário vazio para inserir.
 * 
 * Ação = POST => Inserir/Edita de fato.
 ***************************************************************/

// SEU CABEÇALHO
include 'cabecalho.php';

/***************************************************************
 * CONEXÃO COM BD (mesmos dados)
 ***************************************************************/
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
    die("<div class='alert alert-perigo'>Erro ao conectar BD: "
         . htmlspecialchars($e->getMessage()) . "</div>");
}

/***************************************************************
 * SE POST => SALVAR
 ***************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = intval($_POST['id'] ?? 0);
    $slug       = trim($_POST['slug'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $brandType  = trim($_POST['brand_type'] ?? '');
    $banner     = trim($_POST['banner'] ?? '');
    $btnImage   = trim($_POST['btn_image'] ?? '');

    if ($id === 0) {
        // INSERIR
        try {
            $sqlI = "INSERT INTO brands (slug, name, brand_type, banner, btn_image)
                     VALUES (:s, :n, :t, :b, :btn)";
            $stmt = $pdo->prepare($sqlI);
            $stmt->execute([
                ':s'   => $slug,
                ':n'   => $name,
                ':t'   => $brandType,
                ':b'   => $banner,
                ':btn' => $btnImage
            ]);
            $newId = $pdo->lastInsertId();
            echo "<div class='alert alert-sucesso'>Marca inserida! ID #{$newId}</div>";
            // Mantém $id = $newId se quiser recarregar o form com dados. 
            $id = (int)$newId;
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao inserir: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        // EDITAR
        try {
            $sqlU = "UPDATE brands
                     SET slug=:s, name=:n, brand_type=:t, banner=:b, btn_image=:bi
                     WHERE id=:id";
            $stmtU = $pdo->prepare($sqlU);
            $stmtU->execute([
                ':s'  => $slug,
                ':n'  => $name,
                ':t'  => $brandType,
                ':b'  => $banner,
                ':bi' => $btnImage,
                ':id' => $id
            ]);
            echo "<div class='alert alert-sucesso'>Marca #{$id} editada com sucesso!</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao editar marca: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

/***************************************************************
 * SE GET => CARREGAR DADOS PARA EXIBIR
 ***************************************************************/
$marca = [
    'id'         => 0,
    'slug'       => '',
    'name'       => '',
    'brand_type' => '',
    'banner'     => '',
    'btn_image'  => ''
];

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $idGet = intval($_GET['id']);
    try {
        $sqlSel = "SELECT * FROM brands WHERE id=:id";
        $stmtS  = $pdo->prepare($sqlSel);
        $stmtS->execute([':id'=>$idGet]);
        $row = $stmtS->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $marca = $row;
        } else {
            echo "<div class='alert alert-perigo'>Marca não encontrada!</div>";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-perigo'>Erro ao buscar marca: "
             . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Agora $marca contém os dados (ou vazio se for nova)
?>

<h2>
  <?php if ($marca['id'] == 0): ?>
    Inserir Nova Marca
  <?php else: ?>
    Editar Marca #<?= $marca['id'] ?>
  <?php endif; ?>
</h2>

<form method="POST" action="">
  <input type="hidden" name="id" value="<?= (int)$marca['id'] ?>">

  <label>Slug:</label>
  <input type="text" name="slug"
         value="<?= htmlspecialchars($marca['slug']) ?>" required>

  <label>Nome:</label>
  <input type="text" name="name"
         value="<?= htmlspecialchars($marca['name']) ?>" required>

  <label>Tipo (brand_type):</label>
  <select name="brand_type">
    <option value="">-- Selecione --</option>
    <option value="Marcas Importadas"
      <?= ($marca['brand_type'] === 'Marcas Importadas' ? 'selected' : '') ?>>
      Marcas Importadas
    </option>
    <option value="Marcas Premium"
      <?= ($marca['brand_type'] === 'Marcas Premium' ? 'selected' : '') ?>>
      Marcas Premium
    </option>
    <option value="Marcas Nacionais"
      <?= ($marca['brand_type'] === 'Marcas Nacionais' ? 'selected' : '') ?>>
      Marcas Nacionais
    </option>
    <option value="Diversos"
      <?= ($marca['brand_type'] === 'Diversos' ? 'selected' : '') ?>>
      Diversos
    </option>
  </select>

  <label>Banner URL:</label>
  <input type="text" name="banner"
         value="<?= htmlspecialchars($marca['banner']) ?>">

  <label>Imagem do Botão (btn_image):</label>
  <input type="text" name="btn_image"
         value="<?= htmlspecialchars($marca['btn_image']) ?>">

  <button type="submit" class="btn btn-primario">
    <?php if ($marca['id'] == 0): ?>
      Inserir
    <?php else: ?>
      Salvar Alterações
    <?php endif; ?>
  </button>
</form>

<!-- Opcional: Exibir preview do Banner e Botão se quiser -->
<?php if (!empty($marca['banner'])): ?>
  <p><strong>Preview Banner:</strong></p>
  <img src="<?= htmlspecialchars($marca['banner']) ?>"
       style="max-width:200px; border:1px solid #ccc;">
<?php endif; ?>

<?php if (!empty($marca['btn_image'])): ?>
  <p><strong>Preview Botão:</strong></p>
  <img src="<?= htmlspecialchars($marca['btn_image']) ?>"
       style="max-width:200px; border:1px solid #ccc;">
<?php endif; ?>

<p style="margin-top:1rem;">
  <a href="painel_marcas_list.php" class="btn btn-secundario">
    Voltar à lista de Marcas
  </a>
</p>

<?php
include 'rodape.php';
