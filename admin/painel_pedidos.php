<?php
/***************************************************************
 * painel_pedidos.php
 * 
 * Página responsável por exibir a lista de pedidos do painel
 * administrativo da Império Pharma. Também oferece opção de
 * filtrar por status ou nome de cliente, e linka para a página
 * de detalhe do pedido (painel_detalhe_pedido.php).
 ***************************************************************/

// Inclui o cabeçalho (HTML inicial + menu)
include 'cabecalho.php'; 

/***************************************************************
 * 1. CONFIGURAÇÃO DE BANCO DE DADOS
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
    die("Erro ao conectar no BD: " . $e->getMessage());
}

/***************************************************************
 * 2. TRATAMENTO DE FILTROS (OPCIONAL)
 ***************************************************************/
// Exemplo de possíveis filtros: status, nome do cliente
$statusFiltro = isset($_GET['status']) ? trim($_GET['status']) : '';
$nomeFiltro   = isset($_GET['nome'])   ? trim($_GET['nome'])   : '';

$whereClauses = [];
$params       = [];

// Se o usuário filtrar por status (PENDENTE, PAGO, etc.)
if ($statusFiltro !== '') {
    $whereClauses[] = "status = :statusFiltro";
    $params[':statusFiltro'] = $statusFiltro;
}

// Se o usuário filtrar por nome do cliente
if ($nomeFiltro !== '') {
    $whereClauses[] = "customer_name LIKE :nomeFiltro";
    $params[':nomeFiltro'] = "%$nomeFiltro%";
}

// Monta o WHERE dinâmico
$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

/***************************************************************
 * 3. CONSULTA DOS PEDIDOS
 ***************************************************************/
$orders = [];
try {
    $sql = "
      SELECT
        id,
        customer_name,
        final_value,
        cost_total,
        status,
        created_at
      FROM orders
      $whereSQL
      ORDER BY id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro ao buscar pedidos: " . $e->getMessage());
}

/***************************************************************
 * 4. EXIBIÇÃO DA LISTA (HTML)
 ***************************************************************/
?>
<h2>Lista de Pedidos</h2>

<!-- Formulário de Filtro (opcional) -->
<form method="GET" style="margin-bottom: 1rem;">
  <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
    <!-- Campo para filtrar por status -->
    <div>
      <label>Status:</label>
      <select name="status">
        <option value="">-- Todos --</option>
        <option value="PENDENTE"   <?= ($statusFiltro==='PENDENTE'   ? 'selected' : '') ?>>PENDENTE</option>
        <option value="PAGO"       <?= ($statusFiltro==='PAGO'       ? 'selected' : '') ?>>PAGO</option>
        <option value="ENVIADO"    <?= ($statusFiltro==='ENVIADO'    ? 'selected' : '') ?>>ENVIADO</option>
        <option value="CONCLUIDO"  <?= ($statusFiltro==='CONCLUIDO'  ? 'selected' : '') ?>>CONCLUIDO</option>
        <option value="CANCELADO"  <?= ($statusFiltro==='CANCELADO'  ? 'selected' : '') ?>>CANCELADO</option>
      </select>
    </div>

    <!-- Campo para filtrar por nome do cliente -->
    <div>
      <label>Nome do Cliente:</label>
      <input type="text" name="nome" placeholder="Ex: João" value="<?= htmlspecialchars($nomeFiltro) ?>">
    </div>

    <!-- Botão de aplicar filtro -->
    <div style="display: flex; align-items: flex-end;">
      <button type="submit" class="btn btn-primario">Filtrar</button>
    </div>
  </div>
</form>

<!-- Tabela de pedidos -->
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Cliente</th>
      <th>Valor Final (R$)</th>
      <th>Custo (R$)</th>
      <th>Status</th>
      <th>Data/Hora</th>
      <th>Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($orders)): ?>
      <tr>
        <td colspan="7" style="text-align: center;">
          Nenhum pedido encontrado.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($orders as $ord): ?>
        <tr>
          <td><?= $ord['id'] ?></td>
          <td><?= htmlspecialchars($ord['customer_name']) ?></td>
          <td><?= number_format($ord['final_value'], 2, ',', '.') ?></td>
          <td><?= number_format($ord['cost_total'], 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($ord['status']) ?></td>
          <td><?= date('d/m/Y H:i:s', strtotime($ord['created_at'])) ?></td>
          <td>
            <!-- Link para ver detalhes do pedido -->
            <a href="painel_detalhe_pedido.php?id=<?= $ord['id'] ?>" class="btn btn-primario">
              Ver Detalhes
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php
// Inclui o rodapé (fecha o <main> e </body></html>)
include 'rodape.php';
