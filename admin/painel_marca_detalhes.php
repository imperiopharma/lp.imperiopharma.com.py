<?php
/***************************************************************
 * painel_marca_detalhes.php
 *
 * - Exibe dados de uma marca (via GET['brand_id'])
 * - Lista seus produtos em tabela
 * - Insere novo produto, edita e exclui individualmente
 * - **Novo**: permite editar em massa (price, promo_price, cost)
 *   de todos os produtos e salvar de uma só vez.
 ***************************************************************/

include 'cabecalho.php';

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
} catch(Exception $e) {
    die("Erro ao conectar BD: ".$e->getMessage());
}

// 2) Verifica se brand_id foi passado
if (!isset($_GET['brand_id']) || intval($_GET['brand_id'])<=0) {
    die("Marca não especificada ou inválida.");
}
$brandId = intval($_GET['brand_id']);

// 3) Se houver POST, tratamos as ações
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $acao = $_POST['acao'] ?? '';

    // 3.1) Atualização em massa (price/promo/cost)
    if ($acao==='mass_update') {
        // Recebe array com dados: $_POST['prod'][id_prod][price, promo_price, cost]
        $prodUpdates = $_POST['prod'] ?? [];
        if (!is_array($prodUpdates) || empty($prodUpdates)) {
            echo "<div style='color:red;font-weight:bold;'>Nenhum produto para atualizar.</div>";
        } else {
            // Percorre todos os IDs
            $contSucesso = 0;
            try {
                // Podemos usar transação p/ atomicidade
                $pdo->beginTransaction();
                foreach($prodUpdates as $prodId => $campos) {
                    $prodId = intval($prodId);
                    if ($prodId<=0) continue; // ignora lixos

                    $novoPrice = floatval($campos['price'] ?? 0);
                    $novoPromo = floatval($campos['promo_price'] ?? 0);
                    $novoCost  = floatval($campos['cost'] ?? 0);

                    // Update
                    $sqlU = "UPDATE products
                             SET price=:p, promo_price=:pm, cost=:c
                             WHERE id=:id
                             LIMIT 1";
                    $stmU = $pdo->prepare($sqlU);
                    $stmU->execute([
                        ':p'  => $novoPrice,
                        ':pm' => $novoPromo,
                        ':c'  => $novoCost,
                        ':id' => $prodId
                    ]);
                    $contSucesso++;
                }
                $pdo->commit();

                echo "<div style='color:green;font-weight:bold; margin:8px 0;'>
                        Atualizações realizadas em {$contSucesso} produto(s).
                      </div>";
            } catch(Exception $e) {
                $pdo->rollBack();
                echo "<div style='color:red;font-weight:bold;'>Erro ao atualizar em massa: ".
                     $e->getMessage()."</div>";
            }
        }
    }

    // 3.2) Excluir produto individual
    elseif ($acao==='excluir_produto') {
        $idProd = intval($_POST['id'] ?? 0);
        if ($idProd>0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM products WHERE id=:id LIMIT 1");
                $stmtDel->execute([':id'=>$idProd]);
                echo "<div style='color:green;font-weight:bold;'>Produto #{$idProd} excluído!</div>";
            } catch(Exception $e) {
                echo "<div style='color:red;font-weight:bold;'>Erro ao excluir: ".$e->getMessage()."</div>";
            }
        }
    }

    // 3.3) Inserir produto
    elseif ($acao==='inserir_produto') {
        $brandIdProd = intval($_POST['brand_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $price  = floatval($_POST['price']  ?? 0);
        $promo  = floatval($_POST['promo_price'] ?? 0);
        $cost   = floatval($_POST['cost']   ?? 0);
        $cat    = trim($_POST['category']   ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $imgUrl = trim($_POST['image_url'] ?? '');

        if ($brandIdProd<=0 || $name==='' ) {
            echo "<div style='color:red;font-weight:bold;'>Dados obrigatórios faltando.</div>";
        } else {
            try {
                $sqlIns = "INSERT INTO products
                           (brand_id, name, description, price, promo_price, cost, 
                            category, active, image_url)
                           VALUES
                           (:b, :n, :ds, :pr, :pm, :ct, :c, :ac, :img)";
                $stmIns = $pdo->prepare($sqlIns);
                $stmIns->execute([
                  ':b'   => $brandIdProd,
                  ':n'   => $name,
                  ':ds'  => $desc,
                  ':pr'  => $price,
                  ':pm'  => $promo,
                  ':ct'  => $cost,
                  ':c'   => $cat,
                  ':ac'  => $active,
                  ':img' => $imgUrl
                ]);
                echo "<div style='color:green;font-weight:bold;'>Produto inserido!</div>";
            } catch(Exception $e) {
                echo "<div style='color:red;font-weight:bold;'>Erro ao inserir: ".$e->getMessage()."</div>";
            }
        }
    }

    // 3.4) Editar produto individual
    elseif ($acao==='editar_produto') {
        $idProd = intval($_POST['id'] ?? 0);

        $brandIdProd = intval($_POST['brand_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $price  = floatval($_POST['price']  ?? 0);
        $promo  = floatval($_POST['promo_price'] ?? 0);
        $cost   = floatval($_POST['cost']   ?? 0);
        $cat    = trim($_POST['category']   ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $imgUrl = trim($_POST['image_url'] ?? '');

        if ($idProd>0 && $brandIdProd>0 && $name!=='') {
            try {
                $sqlUp = "UPDATE products
                          SET brand_id=:b, name=:n, description=:ds,
                              price=:pr, promo_price=:pm, cost=:ct,
                              category=:c, active=:ac, image_url=:img
                          WHERE id=:id
                          LIMIT 1";
                $stmUp = $pdo->prepare($sqlUp);
                $stmUp->execute([
                  ':b'   => $brandIdProd,
                  ':n'   => $name,
                  ':ds'  => $desc,
                  ':pr'  => $price,
                  ':pm'  => $promo,
                  ':ct'  => $cost,
                  ':c'   => $cat,
                  ':ac'  => $active,
                  ':img' => $imgUrl,
                  ':id'  => $idProd
                ]);
                echo "<div style='color:green;font-weight:bold;'>Produto #{$idProd} editado!</div>";
            } catch(Exception $e) {
                echo "<div style='color:red;font-weight:bold;'>Erro ao editar: ".$e->getMessage()."</div>";
            }
        }
    }
}

// 4) Buscar dados da marca
try {
    $stmtM = $pdo->prepare("SELECT * FROM brands WHERE id=:id");
    $stmtM->execute([':id' => $brandId]);
    $marca = $stmtM->fetch(PDO::FETCH_ASSOC);
    if (!$marca) {
        die("Marca #{$brandId} não encontrada no BD.");
    }
} catch(Exception $e) {
    die("Erro ao buscar marca: ".$e->getMessage());
}

// 5) Buscar produtos dessa marca
$produtos = [];
try {
    $stmtP = $pdo->prepare("SELECT * FROM products WHERE brand_id=:b ORDER BY id DESC");
    $stmtP->execute([':b'=>$brandId]);
    $produtos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    echo "<div style='color:red;font-weight:bold;'>Erro ao buscar produtos: ".$e->getMessage()."</div>";
}

// 6) Buscar lista de todas as marcas (para o <select> do form)
$marcasDisponiveis = [];
try {
    $stmtMar = $pdo->query("SELECT id, name FROM brands ORDER BY name");
    $marcasDisponiveis = $stmtMar->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "<div style='color:red;font-weight:bold;'>Erro ao buscar outras marcas: ".$e->getMessage()."</div>";
}

// ----------------------------------------------------------------
// EXIBIÇÃO
// ----------------------------------------------------------------
?>
<h2>Detalhes da Marca #<?= $marca['id'] ?> - <?= htmlspecialchars($marca['name']) ?></h2>

<?php if (!empty($marca['banner'])): ?>
  <p><strong>Banner:</strong></p>
  <img src="<?= htmlspecialchars($marca['banner']) ?>" style="max-width:300px; margin-bottom:10px;">
<?php endif; ?>

<?php if (!empty($marca['btn_image'])): ?>
  <p><strong>Botão (btn_image):</strong></p>
  <img src="<?= htmlspecialchars($marca['btn_image']) ?>" style="max-width:300px;">
<?php endif; ?>

<hr style="margin:1rem 0;">

<h3>Editar Preços em Massa</h3>
<p>Altere <strong>Price</strong>, <strong>Promo</strong> e <strong>Cost</strong> de vários produtos ao mesmo tempo e clique em <em>Salvar Alterações</em> ao final. Será pedido uma confirmação.</p>

<?php if (empty($produtos)): ?>
  <p style="color:#555;">Esta marca não tem produtos cadastrados ainda.</p>
<?php else: ?>

<!-- FORM para EDIÇÃO EM MASSA -->
<form method="POST" 
      onsubmit="return confirm('Deseja realmente salvar TODAS as alterações?');"
      style="margin-bottom:2rem; background:#f9f9f9; padding:8px; border:1px solid #ccc; border-radius:6px;"
>
  <input type="hidden" name="acao" value="mass_update">

  <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; max-width:1000px;">
    <thead style="background:#ddd;">
      <tr>
        <th>ID</th>
        <th>Nome</th>
        <th>Price (R$)</th>
        <th>Promo (R$)</th>
        <th>Cost (R$)</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($produtos as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td>
          <input type="number" step="0.01"
                 name="prod[<?= $p['id'] ?>][price]"
                 value="<?= floatval($p['price']) ?>"
                 style="width:80px;">
        </td>
        <td>
          <input type="number" step="0.01"
                 name="prod[<?= $p['id'] ?>][promo_price]"
                 value="<?= floatval($p['promo_price']) ?>"
                 style="width:80px;">
        </td>
        <td>
          <input type="number" step="0.01"
                 name="prod[<?= $p['id'] ?>][cost]"
                 value="<?= floatval($p['cost']) ?>"
                 style="width:80px;">
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <button type="submit" 
          style="margin-top:10px; padding:8px 14px; font-weight:bold; background:#28a745; color:#fff; border:none; border-radius:4px; cursor:pointer;">
    Salvar Alterações
  </button>
</form>

<?php endif; ?>


<h3>Produtos desta Marca</h3>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; max-width:1000px;">
  <thead style="background:#ddd;">
    <tr>
      <th>ID</th>
      <th>Nome</th>
      <th>Preço (price)</th>
      <th>Promo</th>
      <th>Custo</th>
      <th>Categoria</th>
      <th>Ativo?</th>
      <th>Imagem</th>
      <th>Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($produtos)): ?>
      <tr>
        <td colspan="9" style="text-align:center;">Nenhum produto cadastrado.</td>
      </tr>
    <?php else: ?>
      <?php foreach($produtos as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td>R$ <?= number_format($p['price'], 2, ',', '.') ?></td>
          <td>
            <?php if($p['promo_price']>0): ?>
              R$ <?= number_format($p['promo_price'], 2, ',', '.') ?>
            <?php else: ?>
              --
            <?php endif; ?>
          </td>
          <td>R$ <?= number_format($p['cost'], 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($p['category']) ?></td>
          <td><?= $p['active'] ? 'Sim' : 'Não' ?></td>
          <td>
            <?php if(!empty($p['image_url'])): ?>
              <img src="<?= htmlspecialchars($p['image_url']) ?>"
                   style="max-width:80px; max-height:60px;">
            <?php else: ?>
              --
            <?php endif; ?>
          </td>
          <td>
            <!-- Excluir -->
            <form method="POST" style="display:inline;">
              <input type="hidden" name="acao" value="excluir_produto">
              <input type="hidden" name="id"   value="<?= $p['id'] ?>">
              <button type="submit"
                      onclick="return confirm('Excluir produto #<?= $p['id'] ?>?');"
                      style="background:#c33; color:#fff; padding:4px 8px; border:none; border-radius:4px; cursor:pointer;">
                Excluir
              </button>
            </form>

            <!-- Editar Individual -->
            <button type="button"
                    onclick="preencherEdicaoProduto(
                      <?= $p['id'] ?>,
                      <?= $p['brand_id'] ?>,
                      '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>',
                      '<?= htmlspecialchars($p['description'], ENT_QUOTES) ?>',
                      <?= floatval($p['price']) ?>,
                      <?= floatval($p['promo_price']) ?>,
                      <?= floatval($p['cost']) ?>,
                      '<?= htmlspecialchars($p['category'], ENT_QUOTES) ?>',
                      <?= $p['active'] ? 1 : 0 ?>,
                      '<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>'
                    )"
                    style="margin-left:5px; background:#0066cc; color:#fff; padding:4px 8px; border:none; border-radius:4px; cursor:pointer;">
              Editar
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<hr style="margin:1.5rem 0;">

<!-- FORM: Inserir/Editar Produto Individual -->
<div style="background:#f1f1f1; padding:1rem; border:1px solid #ccc; border-radius:6px; max-width:500px;">
  <h4 id="tituloFormProd">Inserir Novo Produto</h4>
  <form method="POST" id="formProdIndividual">
    <input type="hidden" name="acao" value="inserir_produto" id="acaoProdHidden">
    <input type="hidden" name="id"   value=""              id="idProdHidden">

    <label>Marca (brand_id):</label><br>
    <select name="brand_id" id="brandIdProd" required style="margin-bottom:6px;">
      <option value="">-- Selecione --</option>
      <?php foreach($marcasDisponiveis as $mb): ?>
        <option value="<?= $mb['id'] ?>">
          <?= htmlspecialchars($mb['name']) ?> (ID <?= $mb['id'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <br>

    <label>Nome do Produto:</label><br>
    <input type="text" name="name" id="nameProd" required style="width:100%; margin-bottom:6px;"><br>

    <label>Descrição:</label><br>
    <textarea name="description" rows="2" id="descProd" style="width:100%; margin-bottom:6px;"></textarea><br>

    <label>Preço (price):</label><br>
    <input type="number" step="0.01" name="price" id="priceProd" required style="margin-bottom:6px;"><br>

    <label>Preço Promo (promo_price):</label><br>
    <input type="number" step="0.01" name="promo_price" id="promoProd" style="margin-bottom:6px;"><br>

    <label>Custo (cost):</label><br>
    <input type="number" step="0.01" name="cost" id="costProd" style="margin-bottom:6px;"><br>

    <label>Categoria (category):</label><br>
    <select name="category" id="catProd" style="margin-bottom:6px;">
      <option value="">-- Selecione --</option>
      <option value="Produtos Injetáveis">Produtos Injetáveis</option>
      <option value="Produtos Orais">Produtos Orais</option>
      <option value="Combos">Combos</option>
      <option value="Produtos Mix">Produtos Mix</option>
    </select>
    <br>

    <label>Ativo?</label>
    <input type="checkbox" name="active" id="activeProd" value="1">
    <span>Sim</span>
    <br><br>

    <label>Imagem (URL):</label><br>
    <input type="text" name="image_url" id="imgProd" style="width:100%; margin-bottom:8px;"><br>

    <button type="submit" style="background:#007bff; color:#fff; padding:6px 10px; border:none; border-radius:4px; cursor:pointer;">
      Salvar Produto
    </button>
    <button type="button"
            onclick="resetFormProduto()"
            style="background:#aaa; margin-left:6px; padding:6px 10px; border:none; border-radius:4px; cursor:pointer;">
      Cancelar Edição
    </button>
  </form>
</div>

<p style="margin-top:1rem;">
  <a href="painel_marcas_list.php"
     style="background:#555; color:#fff; padding:8px 12px; border-radius:4px; text-decoration:none;">
    Voltar à Lista de Marcas
  </a>
</p>

<script>
// Preencher form para EDITAR produto individual
function preencherEdicaoProduto(id, bId, nm, desc, pr, pm, ct, cat, act, img) {
  document.getElementById('tituloFormProd').textContent = "Editar Produto #" + id;
  document.getElementById('acaoProdHidden').value       = "editar_produto";
  document.getElementById('idProdHidden').value         = id;

  document.getElementById('brandIdProd').value = bId || '';
  document.getElementById('nameProd').value    = nm;
  document.getElementById('descProd').value    = desc;
  document.getElementById('priceProd').value   = pr;
  document.getElementById('promoProd').value   = pm;
  document.getElementById('costProd').value    = ct;
  document.getElementById('catProd').value     = cat;
  document.getElementById('activeProd').checked= (act===1);
  document.getElementById('imgProd').value     = img;

  // Rolagem até o form
  document.getElementById('formProdIndividual').scrollIntoView({ behavior: 'smooth' });
}

// Resetar form para "Inserir Novo Produto"
function resetFormProduto() {
  document.getElementById('tituloFormProd').textContent = "Inserir Novo Produto";
  document.getElementById('acaoProdHidden').value       = "inserir_produto";
  document.getElementById('idProdHidden').value         = "";

  document.getElementById('brandIdProd').value = "";
  document.getElementById('nameProd').value    = "";
  document.getElementById('descProd').value    = "";
  document.getElementById('priceProd').value   = "";
  document.getElementById('promoProd').value   = "";
  document.getElementById('costProd').value    = "";
  document.getElementById('catProd').value     = "";
  document.getElementById('activeProd').checked= false;
  document.getElementById('imgProd').value     = "";
}
</script>

<?php
include 'rodape.php';
?>
