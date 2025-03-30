<?php
/**************************************************************
 * admin/pages/cupons.php
 *
 * GERENCIAMENTO DE CUPONS (AVANÇADO)
 *   - ?action=list => Lista cupons
 *   - ?action=edit => Criar/editar cupom
 *   - ?delete=ID   => Excluir cupom
 *
 * TABELA `coupons` precisa ter colunas (exemplo):
 *   id (PK), code, discount_type (ENUM('FIXO','PORCENT','FRETE')),
 *   discount_value (DECIMAL(10,2)),
 *   max_discount (DECIMAL(10,2)),
 *   min_purchase (DECIMAL(10,2)),
 *   usage_limit INT,
 *   usage_count INT,
 *   usage_per_user INT,
 *   restrict_first_purchase TINYINT(1),
 *   stackable TINYINT(1),
 *   valid_from DATE/DateTime,
 *   valid_until DATE/DateTime,
 *   active TINYINT(1),
 *   description TEXT,
 *   ...
 *
 * Tabelas extras p/ restrições:
 *   coupon_brands(coupon_id, brand_id)
 *   coupon_categories(coupon_id, category_name)
 **************************************************************/

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';

switch ($action) {

  //=========================================================
  // 1) LISTAR CUPONS
  //=========================================================
  case 'list':
  default:

    // Se ?delete=ID => excluir
    if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
      $delId = (int)$_GET['delete'];
      try {
        // 1) Deletar da tabela coupons
        $stmtDel = $pdo->prepare("DELETE FROM coupons WHERE id=? LIMIT 1");
        $stmtDel->execute([$delId]);

        // 2) Deletar relacionamentos em coupon_brands e coupon_categories
        $pdo->prepare("DELETE FROM coupon_brands WHERE coupon_id=?")->execute([$delId]);
        $pdo->prepare("DELETE FROM coupon_categories WHERE coupon_id=?")->execute([$delId]);

        echo "<div class='alert alert-sucesso'>Cupom #{$delId} excluído com sucesso!</div>";
      } catch (Exception $e) {
        echo "<div class='alert alert-perigo'>Erro ao excluir: "
             . htmlspecialchars($e->getMessage()) . "</div>";
      }
    }

    // Carregar lista de cupons
    try {
      $sqlCup = "SELECT * FROM coupons ORDER BY id DESC";
      $stmCup = $pdo->query($sqlCup);
      $listaCup = $stmCup->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      echo "<div class='alert alert-perigo'>Erro ao listar cupons: "
           . htmlspecialchars($e->getMessage()) . "</div>";
      $listaCup = [];
    }

    ?>
    <h2>Gerenciamento de Cupons (Avançado)</h2>
    <p style="margin-bottom:1rem;">
      <a href="index.php?page=cupons&action=edit" class="btn btn-primario">
        Criar Novo Cupom
      </a>
    </p>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Código</th>
          <th>Tipo</th>
          <th>Valor</th>
          <th>MaxDesc</th>
          <th>MinComp</th>
          <th>LimiteUso</th>
          <th>Usado</th>
          <th>Ativo?</th>
          <th>Val.Ini</th>
          <th>Val.Fim</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listaCup)): ?>
          <tr>
            <td colspan="12" style="text-align:center;">Nenhum cupom cadastrado.</td>
          </tr>
        <?php else:
          foreach ($listaCup as $cup):
            ?>
            <tr>
              <td><?= $cup['id'] ?></td>
              <td><?= htmlspecialchars($cup['code']) ?></td>
              <td><?= htmlspecialchars($cup['discount_type']) ?></td>
              <td><?= number_format($cup['discount_value'],2,',','.') ?></td>
              <td><?= number_format($cup['max_discount'],2,',','.') ?></td>
              <td><?= number_format($cup['min_purchase'],2,',','.') ?></td>
              <td><?= (int)$cup['usage_limit'] ?></td>
              <td><?= (int)$cup['usage_count'] ?></td>
              <td><?= ($cup['active'] ? 'Sim':'Não') ?></td>
              <td>
                <?php
                  if (!empty($cup['valid_from'])) {
                    // Se for datetime, troque 'd/m/Y' p/ 'd/m/Y H:i'
                    echo date('d/m/Y', strtotime($cup['valid_from']));
                  } else { echo '--'; }
                ?>
              </td>
              <td>
                <?php
                  if (!empty($cup['valid_until'])) {
                    echo date('d/m/Y', strtotime($cup['valid_until']));
                  } else { echo '--'; }
                ?>
              </td>
              <td>
                <a href="index.php?page=cupons&action=edit&id=<?= $cup['id'] ?>"
                   class="btn btn-primario btn-sm"
                   style="margin-right:6px;">
                  Editar
                </a>
                <a href="index.php?page=cupons&action=list&delete=<?= $cup['id'] ?>"
                   class="btn btn-perigo btn-sm"
                   onclick="return confirm('Excluir cupom #<?= $cup['id'] ?>?');">
                  Excluir
                </a>
              </td>
            </tr>
          <?php endforeach;
        endif; ?>
      </tbody>
    </table>
    <?php
  break; // end case list


  //=========================================================
  // 2) CRIAR / EDITAR CUPOM
  //=========================================================
  case 'edit':

    // Carregar lista de marcas (brand_id, name) se quiser associar
    $brandsAll = [];
    try {
      $stB = $pdo->query("SELECT id,name FROM brands ORDER BY name");
      $brandsAll = $stB->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      // se falhar, segue sem
    }

    // Se ID>0 => EDIÇÃO
    $cupId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    // Defaults
    $cupom = [
      'id' => 0,
      'code' => '',
      'discount_type' => 'FIXO',
      'discount_value' => 0.00,
      'max_discount' => 0.00,
      'min_purchase' => 0.00,
      'usage_limit' => 0,
      'usage_count' => 0,
      'usage_per_user' => 0,
      'restrict_first_purchase' => 0,
      'stackable' => 0,
      'valid_from' => '',
      'valid_until'=> '',
      'active' => 1,
      'description' => '',
    ];
    $cupomMarcas = [];
    $cupomCats   = [];

    if ($cupId>0) {
      // Carrega
      try {
        $stCup = $pdo->prepare("SELECT * FROM coupons WHERE id=? LIMIT 1");
        $stCup->execute([$cupId]);
        $rowCup = $stCup->fetch(PDO::FETCH_ASSOC);
        if (!$rowCup) {
          echo "<div class='alert alert-perigo'>Cupom #$cupId não encontrado!</div>";
          return;
        }
        $cupom = $rowCup;
      } catch (Exception $e) {
        echo "<div class='alert alert-perigo'>Erro ao buscar cupom: "
             . htmlspecialchars($e->getMessage()) . "</div>";
        return;
      }

      // Carregar brand_ids
      try {
        $stCb = $pdo->prepare("SELECT brand_id FROM coupon_brands WHERE coupon_id=?");
        $stCb->execute([$cupId]);
        $rowsB = $stCb->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsB as $rb) {
          $cupomMarcas[] = (int)$rb['brand_id'];
        }
      } catch (Exception $e) {}

      // Carregar categories
      try {
        $stCc = $pdo->prepare("SELECT category_name FROM coupon_categories WHERE coupon_id=?");
        $stCc->execute([$cupId]);
        $rowsC = $stCc->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsC as $rc) {
          $cupomCats[] = $rc['category_name'];
        }
      } catch (Exception $e) {}
    }

    // Se POST => SALVAR
    if ($_SERVER['REQUEST_METHOD']==='POST') {
      $idForm = (int)($_POST['id'] ?? 0);
      $code   = trim($_POST['code'] ?? '');
      $dtype  = trim($_POST['discount_type'] ?? 'FIXO');
      $dval   = floatval($_POST['discount_value'] ?? 0);
      $maxd   = floatval($_POST['max_discount']   ?? 0);
      $minp   = floatval($_POST['min_purchase']   ?? 0);
      $ulim   = (int)($_POST['usage_limit']   ?? 0);
      $ucnt   = (int)($_POST['usage_count']   ?? 0);
      $uper   = (int)($_POST['usage_per_user']?? 0);
      $rfp    = isset($_POST['restrict_first_purchase']) ? 1 : 0;
      $stk    = isset($_POST['stackable']) ? 1 : 0;
      $vfrom  = trim($_POST['valid_from']  ?? '');
      $vuntil = trim($_POST['valid_until'] ?? '');
      $act    = isset($_POST['active']) ? 1 : 0;
      $desc   = trim($_POST['description'] ?? '');

      // Marcas associadas
      $arrBrands = $_POST['brands_assoc'] ?? [];

      // Categorias => separar por linhas
      $arrCats = [];
      if (!empty($_POST['categories_assoc'])) {
        $catText = trim($_POST['categories_assoc']);
        $lines = preg_split('/\r\n|\r|\n/', $catText);
        foreach ($lines as $cl) {
          $cl = trim($cl);
          if ($cl!=='') {
            $arrCats[] = $cl;
          }
        }
      }

      if ($code==='') {
        echo "<div class='alert alert-perigo'>Código do cupom não pode ser vazio!</div>";
      } else {
        try {
          if ($idForm>0) {
            // UPDATE
            $sqlUp = "UPDATE coupons
                      SET code=:c, discount_type=:dt, discount_value=:dv,
                          max_discount=:md, min_purchase=:mp,
                          usage_limit=:ul, usage_count=:uc, usage_per_user=:up,
                          restrict_first_purchase=:rf, stackable=:sk,
                          valid_from=:vf, valid_until=:vu,
                          active=:ac, description=:ds
                      WHERE id=:id";
            $stUp = $pdo->prepare($sqlUp);
            $stUp->execute([
              ':c' => $code,
              ':dt'=> $dtype,
              ':dv'=> $dval,
              ':md'=> $maxd,
              ':mp'=> $minp,
              ':ul'=> $ulim,
              ':uc'=> $ucnt,
              ':up'=> $uper,
              ':rf'=> $rfp,
              ':sk'=> $stk,
              ':vf'=> ($vfrom!==''?$vfrom:null),
              ':vu'=> ($vuntil!==''?$vuntil:null),
              ':ac'=> $act,
              ':ds'=> $desc,
              ':id'=> $idForm
            ]);

            // Remove assoc e reinsere
            $pdo->prepare("DELETE FROM coupon_brands WHERE coupon_id=?")->execute([$idForm]);
            $pdo->prepare("DELETE FROM coupon_categories WHERE coupon_id=?")->execute([$idForm]);
            // Reinsere brands
            foreach ($arrBrands as $bid) {
              if (ctype_digit($bid)) {
                $pdo->prepare("
                  INSERT INTO coupon_brands (coupon_id, brand_id)
                  VALUES (?,?)
                ")->execute([$idForm,(int)$bid]);
              }
            }
            // Reinsere cats
            foreach ($arrCats as $ct) {
              $pdo->prepare("
                INSERT INTO coupon_categories (coupon_id, category_name)
                VALUES (?,?)
              ")->execute([$idForm, $ct]);
            }

            echo "<div class='alert alert-sucesso'>Cupom #$idForm atualizado com sucesso!</div>";

            // Atualizar arrays p/ exibir
            $cupom['code']=$code;
            $cupom['discount_type']=$dtype;
            $cupom['discount_value']=$dval;
            $cupom['max_discount']=$maxd;
            $cupom['min_purchase']=$minp;
            $cupom['usage_limit']=$ulim;
            $cupom['usage_count']=$ucnt;
            $cupom['usage_per_user']=$uper;
            $cupom['restrict_first_purchase']=$rfp;
            $cupom['stackable']=$stk;
            $cupom['valid_from']=$vfrom;
            $cupom['valid_until']=$vuntil;
            $cupom['active']=$act;
            $cupom['description']=$desc;
            $cupomMarcas = array_map('intval',$arrBrands);
            $cupomCats   = $arrCats;
          } else {
            // INSERT
            $sqlIn = "INSERT INTO coupons
              (code, discount_type, discount_value,
               max_discount, min_purchase,
               usage_limit, usage_count, usage_per_user,
               restrict_first_purchase, stackable,
               valid_from, valid_until,
               active, description)
              VALUES
              (:c, :dt, :dv,
               :md, :mp,
               :ul, :uc, :up,
               :rf, :sk,
               :vf, :vu,
               :ac, :ds)";
            $stIn = $pdo->prepare($sqlIn);
            $stIn->execute([
              ':c' => $code,
              ':dt'=> $dtype,
              ':dv'=> $dval,
              ':md'=> $maxd,
              ':mp'=> $minp,
              ':ul'=> $ulim,
              ':uc'=> $ucnt,
              ':up'=> $uper,
              ':rf'=> $rfp,
              ':sk'=> $stk,
              ':vf'=> ($vfrom!==''?$vfrom:null),
              ':vu'=> ($vuntil!==''?$vuntil:null),
              ':ac'=> $act,
              ':ds'=> $desc
            ]);
            $newId = $pdo->lastInsertId();

            // Associar brands
            foreach ($arrBrands as $bid) {
              if (ctype_digit($bid)) {
                $pdo->prepare("
                  INSERT INTO coupon_brands (coupon_id, brand_id)
                  VALUES (?,?)
                ")->execute([$newId,(int)$bid]);
              }
            }
            // Associar cats
            foreach ($arrCats as $ct) {
              $pdo->prepare("
                INSERT INTO coupon_categories (coupon_id, category_name)
                VALUES (?,?)
              ")->execute([$newId, $ct]);
            }

            echo "<div class='alert alert-sucesso'>
                    Cupom criado com sucesso! ID #$newId
                  </div>";

            // Carregar no form p/ exibir
            $cupId = $newId;
            $cupom['id'] = $newId;
            $cupom['code']=$code;
            $cupom['discount_type']=$dtype;
            $cupom['discount_value']=$dval;
            $cupom['max_discount']=$maxd;
            $cupom['min_purchase']=$minp;
            $cupom['usage_limit']=$ulim;
            $cupom['usage_count']=$ucnt;
            $cupom['usage_per_user']=$uper;
            $cupom['restrict_first_purchase']=$rfp;
            $cupom['stackable']=$stk;
            $cupom['valid_from']=$vfrom;
            $cupom['valid_until']=$vuntil;
            $cupom['active']=$act;
            $cupom['description']=$desc;
            $cupomMarcas = array_map('intval',$arrBrands);
            $cupomCats   = $arrCats;
          }
        } catch (Exception $e) {
          echo "<div class='alert alert-perigo'>Erro ao salvar: "
               . htmlspecialchars($e->getMessage()) . "</div>";
        }
      }
    }

    // Formulário
    ?>
    <h2><?=($cupId>0?"Editar Cupom #$cupId":"Criar Novo Cupom")?></h2>
    <form method="POST" style="max-width:650px;">
      <input type="hidden" name="id" value="<?= (int)$cupom['id'] ?>">

      <label>Código do Cupom:</label><br>
      <input type="text" name="code" required
             value="<?= htmlspecialchars($cupom['code']) ?>"
             style="width:100%; margin-bottom:0.5rem;">

      <label>Tipo de Desconto:</label><br>
      <select name="discount_type" style="margin-bottom:0.5rem;">
        <option value="FIXO"    <?=($cupom['discount_type']==='FIXO'?'selected':'')?>>
          Fixo (R$)
        </option>
        <option value="PORCENT" <?=($cupom['discount_type']==='PORCENT'?'selected':'')?>>
          Porcentagem (%)
        </option>
        <option value="FRETE"   <?=($cupom['discount_type']==='FRETE'?'selected':'')?>>
          Frete Grátis
        </option>
      </select>

      <label>Valor do Desconto (ex.: "10.00" p/ R$10 ou "15.00" p/ 15%):</label><br>
      <input type="number" step="0.01" name="discount_value"
             value="<?= number_format($cupom['discount_value'],2,'.','') ?>"
             style="width:100%; margin-bottom:0.5rem;">

      <label>Desconto Máximo (max_discount):</label><br>
      <input type="number" step="0.01" name="max_discount"
             value="<?= number_format($cupom['max_discount'],2,'.','') ?>"
             style="width:100%; margin-bottom:0.5rem;">

      <label>Compra Mínima (min_purchase):</label><br>
      <input type="number" step="0.01" name="min_purchase"
             value="<?= number_format($cupom['min_purchase'],2,'.','') ?>"
             style="width:100%; margin-bottom:0.5rem;">

      <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem; flex-wrap:wrap;">
        <div>
          <label>Limite de Uso (usage_limit):</label><br>
          <input type="number" name="usage_limit"
                 value="<?= (int)$cupom['usage_limit'] ?>"
                 style="width:90px;">
        </div>
        <div>
          <label>Já Usado (usage_count):</label><br>
          <input type="number" name="usage_count"
                 value="<?= (int)$cupom['usage_count'] ?>"
                 style="width:90px;">
        </div>
        <div>
          <label>Uso p/ Usuário (usage_per_user):</label><br>
          <input type="number" name="usage_per_user"
                 value="<?= (int)$cupom['usage_per_user'] ?>"
                 style="width:90px;">
        </div>
      </div>

      <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem; flex-wrap:wrap;">
        <div>
          <label>
            <input type="checkbox" name="restrict_first_purchase" value="1"
              <?=($cupom['restrict_first_purchase']?'checked':'')?>>
            Somente 1ª compra?
          </label>
        </div>
        <div>
          <label>
            <input type="checkbox" name="stackable" value="1"
              <?=($cupom['stackable']?'checked':'')?>>
            Cupom empilhável?
          </label>
        </div>
      </div>

      <div style="display:flex; gap:0.5rem; margin-bottom:0.8rem; flex-wrap:wrap;">
        <div>
          <label>Válido de (valid_from):</label><br>
          <input type="date" name="valid_from"
                 value="<?= substr($cupom['valid_from'],0,10) ?>"
                 style="width:140px;">
        </div>
        <div>
          <label>Válido até (valid_until):</label><br>
          <input type="date" name="valid_until"
                 value="<?= substr($cupom['valid_until'],0,10) ?>"
                 style="width:140px;">
        </div>
      </div>

      <div style="margin-bottom:0.8rem;">
        <label>
          <input type="checkbox" name="active" value="1"
            <?=($cupom['active']?'checked':'')?>>
          Cupom Ativo?
        </label>
      </div>

      <label>Descrição / Observações:</label><br>
      <textarea name="description" rows="3" style="width:100%; margin-bottom:0.8rem;">
        <?= htmlspecialchars($cupom['description']) ?>
      </textarea>

      <!-- Selecionar Marcas associadas -->
      <label>Marcas Associadas (opcional):</label><br>
      <select name="brands_assoc[]" multiple
              style="width:100%; height:80px; margin-bottom:0.8rem;">
        <option value="">(Nenhuma)</option>
        <?php
          foreach ($brandsAll as $b) {
            $bid = (int)$b['id'];
            $sel = (in_array($bid, $cupomMarcas)?'selected':'');
            echo "<option value='$bid' $sel>" . htmlspecialchars($b['name']) . "</option>";
          }
        ?>
      </select>

      <!-- Categorias: 1 por linha -->
      <?php
        $catLines = implode("\n", $cupomCats);
      ?>
      <label>Categorias Associadas (uma por linha, opc.):</label><br>
      <textarea name="categories_assoc" rows="3" style="width:100%; margin-bottom:1rem;">
        <?= htmlspecialchars($catLines) ?>
      </textarea>

      <button type="submit" class="btn btn-primario">
        <?=($cupId>0?'Salvar Alterações':'Criar Cupom')?>
      </button>
      <a href="index.php?page=cupons" class="btn btn-secundario"
         style="margin-left:0.5rem;">
        Voltar
      </a>
    </form>
    <?php
  break; // end case edit

} // end switch
