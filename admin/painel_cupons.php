<?php
// painel_cupons.php
// Exibe lista de cupons + links para criar/editar/excluir

include 'cabecalho.php';  // Seu cabeçalho padrão do admin (HTML)

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

// 1) Excluir Cupom (se vier ?excluir=ID)
if (isset($_GET['excluir']) && ctype_digit($_GET['excluir'])) {
    $cupomId = (int)$_GET['excluir'];

    // Deletar da tabela 'coupons'
    $stmtDel = $pdo->prepare("DELETE FROM coupons WHERE id=? LIMIT 1");
    $stmtDel->execute([$cupomId]);

    // Se quiser remover relacionamentos em coupon_brands/coupon_categories
    // $stmtDelB = $pdo->prepare("DELETE FROM coupon_brands WHERE coupon_id=?");
    // $stmtDelB->execute([$cupomId]);

    // $stmtDelC = $pdo->prepare("DELETE FROM coupon_categories WHERE coupon_id=?");
    // $stmtDelC->execute([$cupomId]);

    header("Location: painel_cupons.php");
    exit;
}

// 2) Buscar cupons (SELECT * FROM coupons)
$sql = "SELECT * FROM coupons ORDER BY id DESC";
$stmt = $pdo->query($sql);
$listaCupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<h2>Gerenciamento de Cupons</h2>

<!-- Botão para criar novo cupom -->
<p style="margin-bottom: 1rem;">
  <a href="painel_cupons_edit.php"
     class="btn btn-primario"
     style="
        text-decoration: none;
        padding: 8px 12px;
        background: #1e73be;
        color: #fff;
        border-radius: 4px;
     ">
    Criar Novo Cupom
  </a>
</p>

<!-- Tabela de cupons -->
<table border="1" cellpadding="6" cellspacing="0" style="width: 100%; max-width: 1000px;">
  <thead style="background: #f5f5f5;">
    <tr>
      <th>ID</th>
      <th>Código</th>
      <th>Tipo</th>
      <th>Valor</th>
      <th>Val. Inicial</th>
      <th>Val. Final</th>
      <th>Ativo?</th>
      <th>Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($listaCupons)): ?>
      <tr>
        <td colspan="8" style="text-align:center;">
          Nenhum cupom cadastrado.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($listaCupons as $cup): ?>
        <tr>
          <td><?= $cup['id'] ?></td>
          <td><?= htmlspecialchars($cup['code']) ?></td>
          <td><?= htmlspecialchars($cup['discount_type']) ?></td>
          <td>
            <?= number_format($cup['discount_value'], 2, ',', '.') ?>
          </td>
          <td>
            <?php
              if (!empty($cup['valid_from'])) {
                echo date('d/m/Y', strtotime($cup['valid_from']));
              } else {
                echo '--';
              }
            ?>
          </td>
          <td>
            <?php
              if (!empty($cup['valid_until'])) {
                echo date('d/m/Y', strtotime($cup['valid_until']));
              } else {
                echo '--';
              }
            ?>
          </td>
          <td><?= ($cup['active'] == 1) ? 'Sim' : 'Não' ?></td>
          <td>
            <!-- Editar -->
            <a href="painel_cupons_edit.php?id=<?= $cup['id'] ?>"
               style="margin-right:10px; color:blue; text-decoration:none;">
              Editar
            </a>
            <!-- Excluir -->
            <a href="painel_cupons.php?excluir=<?= $cup['id'] ?>"
               style="color:red; text-decoration:none;"
               onclick="return confirm('Deseja excluir este cupom?');">
              Excluir
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php
include 'rodape.php';
