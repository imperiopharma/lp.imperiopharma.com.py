<?php
/***************************************************************
 * painel_marcas_list.php
 *
 * Lista simples de todas as marcas. Ao clicar em "Ver Detalhes",
 * abre a página painel_marca_detalhes.php?brand_id=XX
 ***************************************************************/

// Seu cabeçalho (HTML + <body> etc.)
include 'cabecalho.php';

/***************************************************************
 * CONEXÃO COM O BANCO DE DADOS
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
 * LISTAR MARCAS
 ***************************************************************/
$marcas = [];
try {
    $stmt = $pdo->query("SELECT id, slug, name, brand_type FROM brands ORDER BY id DESC");
    $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<div class='alert alert-perigo'>Erro ao listar marcas: "
         . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
<h2 class="painel-subtitulo">Lista de Marcas</h2>

<div class="painel-card">
  <table class="painel-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Slug</th>
        <th>Nome</th>
        <th>Tipo</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($marcas)): ?>
        <tr>
          <td colspan="5" style="text-align:center;">Nenhuma marca cadastrada.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($marcas as $m): ?>
          <tr>
            <td><?= $m['id'] ?></td>
            <td><?= htmlspecialchars($m['slug']) ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['brand_type']) ?></td>
            <td>
              <!-- Link "Ver Detalhes", passando brand_id -->
              <a href="painel_marca_detalhes.php?brand_id=<?= $m['id'] ?>"
                 class="btn btn-primario btn-sm">
                Ver Detalhes
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Se quiser linkar para inserir nova marca (outra lógica) -->
<!--
<p>
  <a href="painel_marcas_editar.php" class="btn btn-primario">
    Inserir Nova Marca
  </a>
</p>
-->

<?php
// Seu rodapé
include 'rodape.php';
