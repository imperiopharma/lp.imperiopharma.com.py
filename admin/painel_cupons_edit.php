<?php
// painel_cupons_edit.php
// Formulário para criar ou editar um cupom na tabela `coupons`.

include 'cabecalho.php';  // Seu cabeçalho padrão do painel

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
    die("Erro ao conectar ao BD: " . $e->getMessage());
}

// ------------------------------------------------
// 1. Verificar se é edição (se existir ?id=123)
$cupomId = 0;
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $cupomId = (int)$_GET['id'];
}

// ------------------------------------------------
// 2. Processar o POST (salvar / atualizar)
$erro = "";
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pegar campos do formulário
    $code          = trim($_POST['code'] ?? '');
    $discountType  = trim($_POST['discount_type'] ?? '');
    $discountValue = floatval($_POST['discount_value'] ?? 0);
    $validFrom     = trim($_POST['valid_from'] ?? '');
    $validUntil    = trim($_POST['valid_until'] ?? '');
    $active        = isset($_POST['active']) ? 1 : 0;
    $description   = trim($_POST['description'] ?? '');

    // Exemplo de validações simples
    if ($code === '') {
        $erro = "Código do cupom não pode ficar vazio.";
    } elseif ($discountType === '') {
        $erro = "Tipo de desconto não selecionado.";
    } else {
        try {
            if ($cupomId > 0) {
                // UPDATE
                $sqlUpdate = "
                  UPDATE coupons
                  SET
                    code = :code,
                    discount_type = :dtype,
                    discount_value= :dvalue,
                    valid_from= :vfrom,
                    valid_until= :vuntil,
                    active= :active,
                    description= :desc
                  WHERE id = :id
                ";
                $stmtUp = $pdo->prepare($sqlUpdate);
                $stmtUp->execute([
                    ':code'  => $code,
                    ':dtype' => $discountType,
                    ':dvalue'=> $discountValue,
                    ':vfrom' => ($validFrom  !== '' ? $validFrom  : null),
                    ':vuntil'=> ($validUntil !== '' ? $validUntil : null),
                    ':active'=> $active,
                    ':desc'  => $description,
                    ':id'    => $cupomId
                ]);

                $sucesso = "Cupom atualizado com sucesso!";
            } else {
                // INSERT
                $sqlInsert = "
                  INSERT INTO coupons
                    (code, discount_type, discount_value, valid_from, valid_until, active, description)
                  VALUES
                    (:code, :dtype, :dvalue, :vfrom, :vuntil, :active, :desc)
                ";
                $stmtIns = $pdo->prepare($sqlInsert);
                $stmtIns->execute([
                    ':code'  => $code,
                    ':dtype' => $discountType,
                    ':dvalue'=> $discountValue,
                    ':vfrom' => ($validFrom  !== '' ? $validFrom  : null),
                    ':vuntil'=> ($validUntil !== '' ? $validUntil : null),
                    ':active'=> $active,
                    ':desc'  => $description
                ]);

                $cupomId = $pdo->lastInsertId(); // Se quiser redirecionar ou algo
                $sucesso = "Cupom criado com sucesso!";
            }
        } catch (Exception $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// ------------------------------------------------
// 3. Se for edição, buscar dados existentes do cupom
$cupom = [
    'id' => 0,
    'code' => '',
    'discount_type' => 'FIXO', // Exemplo de default
    'discount_value'=> 0.00,
    'valid_from' => '',
    'valid_until'=> '',
    'active' => 1,
    'description' => ''
];

if ($cupomId > 0 && $erro === '') {
    // Buscar do BD
    $stmtC = $pdo->prepare("SELECT * FROM coupons WHERE id=? LIMIT 1");
    $stmtC->execute([$cupomId]);
    $cupomBD = $stmtC->fetch(PDO::FETCH_ASSOC);
    if ($cupomBD) {
        $cupom = $cupomBD;
    } else {
        $erro = "Cupom ID #{$cupomId} não encontrado.";
    }
}

// ------------------------------------------------
?>
<h2><?= ($cupomId > 0 ? 'Editar Cupom' : 'Criar Novo Cupom') ?></h2>

<?php if ($erro): ?>
  <div style="color: red; font-weight: bold; margin: 8px 0;">
    <?= htmlspecialchars($erro) ?>
  </div>
<?php endif; ?>

<?php if ($sucesso): ?>
  <div style="color: green; font-weight: bold; margin: 8px 0;">
    <?= htmlspecialchars($sucesso) ?>
  </div>
<?php endif; ?>

<!-- Formulário -->
<form method="POST" style="max-width: 600px;">

  <label for="code" style="display:block; margin-top:10px; font-weight:bold;">
    Código do Cupom:
  </label>
  <input type="text"
         name="code"
         id="code"
         value="<?= htmlspecialchars($cupom['code'] ?? '') ?>"
         style="width:100%; padding:6px;"
         required
  />

  <label for="discount_type" style="display:block; margin-top:10px; font-weight:bold;">
    Tipo de Desconto:
  </label>
  <select name="discount_type" id="discount_type" style="width:100%; padding:6px;">
    <option value="FIXO"
      <?= ($cupom['discount_type'] === 'FIXO' ? 'selected' : '') ?>>
      Valor Fixo (Ex: R$ 10)
    </option>
    <option value="PORCENT"
      <?= ($cupom['discount_type'] === 'PORCENT' ? 'selected' : '') ?>>
      Porcentagem (Ex: 10%)
    </option>
    <option value="FRETE"
      <?= ($cupom['discount_type'] === 'FRETE' ? 'selected' : '') ?>>
      Frete Grátis
    </option>
  </select>

  <label for="discount_value" style="display:block; margin-top:10px; font-weight:bold;">
    Valor (ex.: "10" se for R$10, ou "15" se for 15%):
  </label>
  <input type="number"
         step="0.01"
         name="discount_value"
         id="discount_value"
         value="<?= number_format($cupom['discount_value'],2,'.','') ?>"
         style="width:100%; padding:6px;"
  />

  <label for="valid_from" style="display:block; margin-top:10px; font-weight:bold;">
    Válido A Partir De:
  </label>
  <input type="date"
         name="valid_from"
         id="valid_from"
         value="<?= ($cupom['valid_from']) ? htmlspecialchars(substr($cupom['valid_from'], 0, 10)) : '' ?>"
         style="width:100%; padding:6px;"
  />

  <label for="valid_until" style="display:block; margin-top:10px; font-weight:bold;">
    Válido Até:
  </label>
  <input type="date"
         name="valid_until"
         id="valid_until"
         value="<?= ($cupom['valid_until']) ? htmlspecialchars(substr($cupom['valid_until'], 0, 10)) : '' ?>"
         style="width:100%; padding:6px;"
  />

  <div style="margin-top:10px;">
    <label style="font-weight:bold;">
      <input type="checkbox" name="active" value="1"
        <?= ($cupom['active'] == 1 ? 'checked' : '') ?>
      />
      Cupom Ativo?
    </label>
  </div>

  <label for="description" style="display:block; margin-top:10px; font-weight:bold;">
    Descrição (opcional):
  </label>
  <textarea name="description"
            id="description"
            style="width:100%; padding:6px; height:60px;"><?= htmlspecialchars($cupom['description'] ?? '') ?></textarea>

  <div style="margin-top:12px;">
    <button type="submit" style="padding:8px 14px; background:#28a745; color:#fff; border:none; border-radius:4px;">
      <?= ($cupomId > 0 ? 'Salvar Alterações' : 'Criar Cupom') ?>
    </button>
    <a href="painel_cupons.php" 
       style="margin-left:12px; text-decoration:none; color:#333; font-weight:bold;">
      Voltar
    </a>
  </div>
</form>

<?php
include 'rodape.php';
