<?php
/***************************************************************
 * painel_produtos.php
 * 
 * Página unificada para CRUD (inserir, editar, excluir) de:
 *   - Marcas (tabela brands)
 *   - Produtos (tabela products)
 * 
 * Agora com listagem agrupada por marca para melhor manutenção,
 * mantendo o link da imagem ao editar, e não rolando ao topo 
 * após "Salvar".
 ***************************************************************/

// 1. Inclui o cabeçalho (HTML + menu + <main>)
include 'cabecalho.php';

/***************************************************************
 * 2. Conexão com o Banco de Dados
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
    die("<div class='alert alert-perigo'>Erro ao conectar ao BD: " 
         . htmlspecialchars($e->getMessage()) . "</div>");
}

/***************************************************************
 * 3. Decide qual seção exibir (marcas ou produtos) via ?secao=
 ***************************************************************/
$secao = isset($_GET['secao']) ? $_GET['secao'] : 'marcas';  
// Padrão = 'marcas'

/***************************************************************
 * 4. Trata Ações de Formulário (Inserir, Editar, Excluir)
 ***************************************************************/

// 4.1. Ações para Marcas
if ($secao === 'marcas' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao     = $_POST['acao'] ?? '';
    $id       = intval($_POST['id'] ?? 0);
    $slug     = trim($_POST['slug'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $brandType= trim($_POST['brand_type'] ?? '');
    $banner   = trim($_POST['banner'] ?? '');
    $btnImage = trim($_POST['btn_image'] ?? '');

    if ($acao === 'inserir_marca') {
        // Inserir nova marca
        try {
            $sqlIns = "INSERT INTO brands (slug, name, brand_type, banner, btn_image)
                       VALUES (:slug, :nm, :btype, :ban, :btn)";
            $stmt = $pdo->prepare($sqlIns);
            $stmt->execute([
                ':slug'  => $slug,
                ':nm'    => $name,
                ':btype' => $brandType,
                ':ban'   => $banner,
                ':btn'   => $btnImage
            ]);
            echo "<div class='alert alert-sucesso'>Marca inserida com sucesso!</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao inserir marca: " 
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } elseif ($acao === 'editar_marca' && $id > 0) {
        // Editar marca existente
        try {
            $sqlUp = "UPDATE brands
                      SET slug=:s, name=:nm, brand_type=:btype,
                          banner=:ban, btn_image=:btn
                      WHERE id=:id";
            $stmtU = $pdo->prepare($sqlUp);
            $stmtU->execute([
                ':s'     => $slug,
                ':nm'    => $name,
                ':btype' => $brandType,
                ':ban'   => $banner,
                ':btn'   => $btnImage,
                ':id'    => $id
            ]);
            echo "<div class='alert alert-sucesso'>Marca #{$id} atualizada com sucesso!</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao editar marca: " 
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } elseif ($acao === 'excluir_marca' && $id > 0) {
        // Excluir marca
        try {
            $stmtD = $pdo->prepare("DELETE FROM brands WHERE id=:id");
            $stmtD->execute([':id'=>$id]);
            echo "<div class='alert alert-sucesso'>Marca #{$id} excluída com sucesso!</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao excluir marca: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// 4.2. Ações para Produtos
if ($secao === 'produtos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao     = $_POST['acao'] ?? '';
    $id       = intval($_POST['id'] ?? 0);
    $brandId  = intval($_POST['brand_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $price    = floatval($_POST['price'] ?? 0);
    $promo    = floatval($_POST['promo_price'] ?? 0);
    $cost     = floatval($_POST['cost'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $active   = isset($_POST['active']) ? 1 : 0;
    $imageUrl = trim($_POST['image_url'] ?? '');

    if ($acao === 'inserir_produto') {
        // Inserir novo produto
        try {
            $sqlInsP = "INSERT INTO products
                (brand_id, name, description, price, promo_price, cost,
                 category, active, image_url)
                VALUES
                (:bId, :nm, :ds, :pr, :pp, :ct, :cat, :act, :img)";
            $stmtP = $pdo->prepare($sqlInsP);
            $stmtP->execute([
                ':bId'  => $brandId,
                ':nm'   => $name,
                ':ds'   => $desc,
                ':pr'   => $price,
                ':pp'   => $promo,
                ':ct'   => $cost,
                ':cat'  => $category,
                ':act'  => $active,
                ':img'  => $imageUrl
            ]);
            echo "<div class='alert alert-sucesso'>Produto inserido com sucesso!</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao inserir produto: " 
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } elseif ($acao === 'editar_produto' && $id > 0) {
        // Editar produto
        try {
            $sqlUpP = "UPDATE products
                       SET brand_id=:bId, name=:nm, description=:ds, 
                           price=:pr, promo_price=:pp, cost=:ct,
                           category=:cat, active=:act, image_url=:img
                       WHERE id=:id";
            $stmtUpP = $pdo->prepare($sqlUpP);
            $stmtUpP->execute([
                ':bId' => $brandId,
                ':nm'  => $name,
                ':ds'  => $desc,
                ':pr'  => $price,
                ':pp'  => $promo,
                ':ct'  => $cost,
                ':cat' => $category,
                ':act' => $active,
                ':img' => $imageUrl,
                ':id'  => $id
            ]);
            echo "<div class='alert alert-sucesso'>Produto #{$id} atualizado com sucesso!</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao editar produto: " 
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } elseif ($acao === 'excluir_produto' && $id > 0) {
        // Excluir produto
        try {
            $stmtDelP = $pdo->prepare("DELETE FROM products WHERE id=:id");
            $stmtDelP->execute([':id'=>$id]);
            echo "<div class='alert alert-sucesso'>Produto #{$id} excluído com sucesso!</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-perigo'>Erro ao excluir produto: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

/***************************************************************
 * 5. Busca de Marcas e Produtos (Listagem Agrupada)
 ***************************************************************/

// 5.1. Lista de marcas (para popular <select> de produtos)
$marcasDisponiveis = [];
try {
    $stmtMarcas = $pdo->query("SELECT id, name FROM brands ORDER BY name");
    $marcasDisponiveis = $stmtMarcas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<div class='alert alert-perigo'>Erro ao buscar marcas: "
         . htmlspecialchars($e->getMessage()) . "</div>";
}

// 5.2. Seção MARCAS: lista todas as marcas
$listaMarcas = [];
if ($secao === 'marcas') {
    try {
        $stmtM = $pdo->query("SELECT * FROM brands ORDER BY id DESC");
        $listaMarcas = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "<div class='alert alert-perigo'>Erro ao listar marcas: "
             . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 5.3. Seção PRODUTOS: agrupar produtos por brand_id
$produtosAgrupados = [];
if ($secao === 'produtos') {
    try {
        $sqlP = "
          SELECT 
            p.*,
            b.id AS bid,
            b.name AS brand_name
          FROM products p
          LEFT JOIN brands b ON p.brand_id = b.id
          ORDER BY b.name ASC, p.id DESC
        ";
        $stmtProds = $pdo->query($sqlP);
        $allProds  = $stmtProds->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allProds as $prod) {
            $bId = $prod['brand_id'] ?? 0;
            if (!isset($produtosAgrupados[$bId])) {
                $produtosAgrupados[$bId] = [
                    'brand_name' => $prod['brand_name'] ?? 'Sem Marca',
                    'lista'      => []
                ];
            }
            $produtosAgrupados[$bId]['lista'][] = $prod;
        }

    } catch (Exception $e) {
        echo "<div class='alert alert-perigo'>Erro ao listar produtos: " 
             . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

/***************************************************************
 * 6. Exibe Botões de Navegação Interna (Marcas / Produtos)
 ***************************************************************/
?>
<h2 class="painel-subtitulo">Marcas &amp; Produtos</h2>

<div style="margin-bottom: 1rem;">
  <a href="?secao=marcas" class="btn btn-secundario" style="margin-right: 0.5rem;">
    Gerenciar Marcas
  </a>
  <a href="?secao=produtos" class="btn btn-secundario">
    Gerenciar Produtos
  </a>
</div>

<?php
/***************************************************************
 * 7. Exibir Seção de MARCAS
 ***************************************************************/
if ($secao === 'marcas'):
?>
<!-- LISTA DE MARCAS -->
<div class="painel-card">
  <h3>Lista de Marcas</h3>
  <table class="painel-table">
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
      <?php if (empty($listaMarcas)): ?>
        <tr>
          <td colspan="7" style="text-align:center;">Nenhuma marca cadastrada.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($listaMarcas as $m): ?>
          <tr>
            <td><?= $m['id'] ?></td>
            <td><?= htmlspecialchars($m['slug']) ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['brand_type']) ?></td>
            <td>
              <?php if (!empty($m['banner'])): ?>
                <img src="<?= htmlspecialchars($m['banner']) ?>" alt="banner"
                     style="max-width:120px; max-height:60px;">
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($m['btn_image'])): ?>
                <img src="<?= htmlspecialchars($m['btn_image']) ?>" alt="btn"
                     style="max-width:120px; max-height:60px;">
              <?php endif; ?>
            </td>
            <td>
              <!-- Excluir -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="acao" value="excluir_marca">
                <input type="hidden" name="id"   value="<?= $m['id'] ?>">
                <button type="submit" class="btn btn-perigo btn-sm"
                        onclick="return confirm('Excluir marca #<?= $m['id'] ?>?');">
                  Excluir
                </button>
              </form>
              <!-- Editar -->
              <button type="button" class="btn btn-primario btn-sm"
                onclick="preencherEdicaoMarca(
                  <?= $m['id'] ?>,
                  '<?= htmlspecialchars($m['slug'], ENT_QUOTES) ?>',
                  '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>',
                  '<?= htmlspecialchars($m['brand_type'], ENT_QUOTES) ?>',
                  '<?= htmlspecialchars($m['banner'], ENT_QUOTES) ?>',
                  '<?= htmlspecialchars($m['btn_image'], ENT_QUOTES) ?>'
                )">
                Editar
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- FORMULÁRIO MARCA -->
<div class="painel-card" id="formMarcaCard">
  <h3 id="tituloFormMarca">Inserir Nova Marca</h3>
  <form method="POST" class="painel-form" onsubmit="scrollToForm(event)">
    <input type="hidden" name="acao" value="inserir_marca" id="acaoMarcaHidden">
    <input type="hidden" name="id"   value=""              id="idMarcaHidden">

    <label>Slug:</label>
    <input type="text" name="slug" id="slugMarca" placeholder="ex: landerlan" required>

    <label>Nome:</label>
    <input type="text" name="name" id="nameMarca" placeholder="ex: Landerlan" required>

    <label>Tipo (brand_type):</label>
    <select name="brand_type" id="brandTypeMarca">
      <option value="">-- Selecione --</option>
      <option value="Marcas Importadas">Marcas Importadas</option>
      <option value="Marcas Premium">Marcas Premium</option>
      <option value="Marcas Nacionais">Marcas Nacionais</option>
      <option value="Diversos">Diversos</option>
    </select>

    <label>Banner URL:</label>
    <input type="text" name="banner" id="bannerMarca" placeholder="http://...">

    <label>Imagem do Botão (btn_image):</label>
    <input type="text" name="btn_image" id="btnImageMarca" placeholder="URL...">

    <button type="submit" class="btn btn-primario">Salvar Marca</button>
    <button type="button" class="btn btn-secundario" onclick="resetFormMarca()">
      Cancelar Edição
    </button>
  </form>
</div>

<script>
function preencherEdicaoMarca(id, slug, name, btype, banner, btnImg) {
  document.getElementById('tituloFormMarca').textContent = "Editar Marca #" + id;
  document.getElementById('acaoMarcaHidden').value       = "editar_marca";
  document.getElementById('idMarcaHidden').value         = id;

  document.getElementById('slugMarca').value       = slug;
  document.getElementById('nameMarca').value       = name;
  document.getElementById('brandTypeMarca').value  = btype;
  document.getElementById('bannerMarca').value     = banner;
  document.getElementById('btnImageMarca').value   = btnImg;

  // Rola somente ao clicar em Editar
  document.getElementById('formMarcaCard').scrollIntoView({ behavior: 'smooth' });
}

function resetFormMarca() {
  document.getElementById('tituloFormMarca').textContent = "Inserir Nova Marca";
  document.getElementById('acaoMarcaHidden').value       = "inserir_marca";
  document.getElementById('idMarcaHidden').value         = "";

  document.getElementById('slugMarca').value             = "";
  document.getElementById('nameMarca').value             = "";
  document.getElementById('brandTypeMarca').value        = "";
  document.getElementById('bannerMarca').value           = "";
  document.getElementById('btnImageMarca').value         = "";
}

// Antes, rolaria para o topo na hora de Salvar. Agora pode remover se não quiser rolar
function scrollToForm(ev) {
  // Se quiser rolar pro topo, mantenha. Senão, comente:
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php
/***************************************************************
 * 8. Exibir Seção de PRODUTOS
 ***************************************************************/
elseif ($secao === 'produtos'):
?>
<div class="painel-card">
  <h3>Produtos - Agrupados por Marca</h3>
  <?php if (empty($produtosAgrupados)): ?>
    <div class="alert alert-info">Nenhum produto cadastrado.</div>
  <?php else: ?>
    <?php foreach($produtosAgrupados as $brandId => $info): ?>
      <h4 style="margin-top:1.2rem; margin-bottom:0.5rem; border-bottom:1px solid #ccc;">
        Marca: <?= htmlspecialchars($info['brand_name']) ?> (ID <?= $brandId ?>)
      </h4>
      <div class="table-responsive">
        <table class="painel-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Preço</th>
              <th>Promo</th>
              <th>Custo</th>
              <th>Categoria</th>
              <th>Ativo?</th>
              <th>Imagem</th>
              <th style="min-width:140px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($info['lista'])): ?>
              <tr>
                <td colspan="9" style="text-align:center;">Nenhum produto desta marca.</td>
              </tr>
            <?php else: ?>
              <?php foreach($info['lista'] as $p):
                $promoVal = ($p['promo_price'] > 0)
                  ? ('R$ ' . number_format($p['promo_price'],2,',','.'))
                  : '--';
              ?>
              <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td>R$ <?= number_format($p['price'],2,',','.') ?></td>
                <td><?= $promoVal ?></td>
                <td>R$ <?= number_format($p['cost'],2,',','.') ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td><?= $p['active'] ? 'Sim' : 'Não' ?></td>
                <td>
                  <?php if(!empty($p['image_url'])): ?>
                    <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="prod"
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
                    <button type="submit" class="btn btn-perigo btn-sm"
                            onclick="return confirm('Excluir produto #<?= $p['id'] ?>?');">
                      Excluir
                    </button>
                  </form>
                  <!-- Editar -->
                  <button
                    type="button"
                    class="btn btn-primario btn-sm"
                    onclick="preencherEdicaoProduto(
                      <?= $p['id'] ?>,
                      <?= $p['brand_id'] ?>,
                      '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>',
                      '<?= htmlspecialchars($p['description'], ENT_QUOTES) ?>',
                      <?= floatval($p['price']) ?>,
                      <?= floatval($p['promo_price']) ?>,
                      <?= floatval($p['cost']) ?>,
                      '<?= htmlspecialchars($p['category'], ENT_QUOTES) ?>',
                      <?= $p['active'] ?>,
                      '<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>'
                    )"
                  >
                    Editar
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- FORMULARIO PRODUTO -->
<div class="painel-card" id="cardFormProd">
  <h3 id="tituloFormProd">Inserir Novo Produto</h3>
  <form method="POST" class="painel-form" onsubmit="scrollToProduto(event)">
    <input type="hidden" name="acao" value="inserir_produto" id="acaoProdHidden">
    <input type="hidden" name="id"   value=""              id="idProdHidden">

    <label>Marca (brand_id):</label>
    <select name="brand_id" id="brandIdProd" required>
      <option value="">-- Selecione --</option>
      <?php foreach ($marcasDisponiveis as $mb): ?>
        <option value="<?= $mb['id'] ?>"><?= htmlspecialchars($mb['name']) ?> (ID <?= $mb['id'] ?>)</option>
      <?php endforeach; ?>
    </select>

    <label>Nome do Produto:</label>
    <input type="text" name="name" id="nameProd" placeholder="Ex: Landerlan Injetável" required>

    <label>Descrição:</label>
    <textarea name="description" rows="3" id="descProd"></textarea>

    <label>Preço (price):</label>
    <input type="number" step="0.01" name="price" id="priceProd" placeholder="Ex: 99.90" required>

    <label>Preço Promocional (promo_price):</label>
    <input type="number" step="0.01" name="promo_price" id="promoProd" placeholder="Ex: 79.90">

    <label>Custo (cost):</label>
    <input type="number" step="0.01" name="cost" id="costProd" placeholder="Custo interno (ex: 50.00)">

    <label>Categoria (category):</label>
    <select name="category" id="catProd">
      <option value="">-- Selecione --</option>
      <option value="Produtos Injetáveis">Produtos Injetáveis</option>
      <option value="Produtos Orais">Produtos Orais</option>
      <option value="Combos">Combos</option>
      <option value="Produtos Mix">Produtos Mix</option>
    </select>

    <label>Ativo?</label>
    <input type="checkbox" name="active" id="activeProd" value="1">
    <span style="margin-left:0.4rem;">Sim</span>

    <label>Imagem (URL):</label>
    <input type="text" name="image_url" id="imgProd" placeholder="URL da miniatura">

    <button type="submit" class="btn btn-primario">Salvar Produto</button>
    <button type="button" class="btn btn-secundario" onclick="resetFormProduto()">
      Cancelar Edição
    </button>
  </form>
</div>

<script>
function preencherEdicaoProduto(id, bId, nm, desc, pr, promo, ct, cat, act, img) {
  console.log("Editar Produto #", id, " | image_url =", img);

  document.getElementById('tituloFormProd').textContent = "Editar Produto #" + id;
  document.getElementById('acaoProdHidden').value       = "editar_produto";
  document.getElementById('idProdHidden').value         = id;

  document.getElementById('brandIdProd').value  = bId;
  document.getElementById('nameProd').value     = nm;
  document.getElementById('descProd').value     = desc;
  document.getElementById('priceProd').value    = pr;
  document.getElementById('promoProd').value    = promo;
  document.getElementById('costProd').value     = ct;
  document.getElementById('catProd').value      = cat;
  document.getElementById('activeProd').checked = (act === 1);

  // Mantém a URL de imagem
  document.getElementById('imgProd').value      = img;

  // Só rola ao form no "Editar"
  document.getElementById('cardFormProd').scrollIntoView({ behavior: 'smooth' });
}

function resetFormProduto() {
  document.getElementById('tituloFormProd').textContent = "Inserir Novo Produto";
  document.getElementById('acaoProdHidden').value       = "inserir_produto";
  document.getElementById('idProdHidden').value         = "";

  document.getElementById('brandIdProd').value  = "";
  document.getElementById('nameProd').value     = "";
  document.getElementById('descProd').value     = "";
  document.getElementById('priceProd').value    = "";
  document.getElementById('promoProd').value    = "";
  document.getElementById('costProd').value     = "";
  document.getElementById('catProd').value      = "";
  document.getElementById('activeProd').checked = false;
  document.getElementById('imgProd').value      = "";
}

function scrollToProduto(event) {
  // Se quiser rolar pro topo ao salvar, deixe. Senão, comente:
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php
/***************************************************************
 * 9. Fim do if $secao === 'produtos'
 ***************************************************************/
endif;

/***************************************************************
 * 10. Inclui Rodapé (fecha <main> e HTML final)
 ***************************************************************/
include 'rodape.php';
