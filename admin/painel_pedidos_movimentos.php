<?php
/***************************************************************
 * painel_pedidos_movimentados.php
 * 
 * Exibe pedidos filtrados pelo período de movimentação
 * (updated_at), status e nome do cliente. Permite também
 * clicar nos cabeçalhos da tabela para ordenar as colunas
 * em ASC ou DESC. 
 * 
 * NÃO modifica seu painel_pedidos.php original.
 ***************************************************************/

// Inclui seu cabeçalho/padrão HTML + menu
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
 * 2. TRATAMENTO DE FILTROS: data inicial, data final, status, nome
 ***************************************************************/

// Data inicial/final para filtrar updated_at
$startDate    = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate      = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';

// Filtro de status
$statusFiltro = isset($_GET['status'])     ? trim($_GET['status'])     : '';

// Filtro opcional de nome do cliente
$nomeFiltro   = isset($_GET['nome'])       ? trim($_GET['nome'])       : '';

// Monta as condições e parâmetros
$whereClauses = [];
$params       = [];

// Se vier data inicial
if ($startDate !== '') {
    // ">= data_inicial 00:00:00"
    $whereClauses[] = "updated_at >= :startDate";
    $params[':startDate'] = $startDate . ' 00:00:00';
}

// Se vier data final
if ($endDate !== '') {
    // "<= data_final 23:59:59"
    $whereClauses[] = "updated_at <= :endDate";
    $params[':endDate'] = $endDate . ' 23:59:59';
}

// Se vier status
if ($statusFiltro !== '') {
    $whereClauses[] = "status = :statusFiltro";
    $params[':statusFiltro'] = $statusFiltro;
}

// Se vier nome do cliente
if ($nomeFiltro !== '') {
    $whereClauses[] = "customer_name LIKE :nomeFiltro";
    $params[':nomeFiltro'] = "%$nomeFiltro%";
}

// Monta o WHERE
$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

/***************************************************************
 * 3. ORDENAR POR COLUNA - DINÂMICO
 ***************************************************************/

// Possíveis colunas permitidas (para evitar SQL injection)
$colunasPermitidas = [
    'id', 
    'customer_name',
    'final_value',
    'status',
    'created_at',
    'updated_at'
];

// Coluna padrão = updated_at
$ordenar  = isset($_GET['ordenar']) ? trim($_GET['ordenar']) : 'updated_at';
$direcao  = isset($_GET['direcao']) ? trim($_GET['direcao']) : 'DESC';

// Valida se $ordenar está na lista permitida
if (!in_array($ordenar, $colunasPermitidas)) {
    $ordenar = 'updated_at'; // fallback
}
// Valida se $direcao é ASC ou DESC
$direcao = strtoupper($direcao);
if ($direcao !== 'ASC' && $direcao !== 'DESC') {
    $direcao = 'DESC'; // fallback
}

$orderSQL = "ORDER BY $ordenar $direcao";

/***************************************************************
 * 4. CONSULTA DOS PEDIDOS (movimentados no período)
 ***************************************************************/
$orders = [];
try {
    $sql = "
      SELECT
        id,
        customer_name,
        final_value,
        status,
        created_at,
        updated_at
      FROM orders
      $whereSQL
      $orderSQL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro ao buscar pedidos: " . $e->getMessage());
}

/***************************************************************
 * 5. FUNÇÃO QUE GERA O LINK DE ORDENAR (cabeçalho clicável)
 ***************************************************************/
function linkOrdenar($coluna, $rotulo, $ordenarAtual, $direcaoAtual, $extraQuery = [])
{
    // Se a coluna atual for a mesma do GET[ordenar], invertendo a direção
    if ($coluna === $ordenarAtual) {
        $novaDirecao = ($direcaoAtual === 'ASC') ? 'DESC' : 'ASC';
    } else {
        // Se for outra coluna, volta pra DESC
        $novaDirecao = 'DESC';
    }
    // Monta a query com os parâmetros extras
    $queryParams = array_merge($_GET, $extraQuery); 
    $queryParams['ordenar'] = $coluna;
    $queryParams['direcao'] = $novaDirecao;

    $url = $_SERVER['PHP_SELF'] . '?' . http_build_query($queryParams);

    // Se for a coluna atual, mostra uma setinha ▲ ou ▼
    $seta = '';
    if ($coluna === $ordenarAtual) {
        $seta = ($direcaoAtual === 'ASC') ? '▲' : '▼';
    }

    return "<a href=\"$url\" style=\"text-decoration:none;\">$rotulo $seta</a>";
}

/***************************************************************
 * 6. EXIBIÇÃO DA LISTA (HTML)
 ***************************************************************/
?>
<h2>Pedidos Movimentados (por Período + Ordenação)</h2>

<!-- Formulário de Filtro -->
<form method="GET" style="margin-bottom: 1rem;">
  <div style="display: flex; gap: 1rem; flex-wrap: wrap;">

    <div>
      <label>Data Inicial (YYYY-MM-DD):</label><br>
      <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
    </div>

    <div>
      <label>Data Final (YYYY-MM-DD):</label><br>
      <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
    </div>

    <div>
      <label>Status:</label><br>
      <select name="status">
        <option value="">-- Todos --</option>
        <option value="PENDENTE"   <?= ($statusFiltro==='PENDENTE'   ? 'selected' : '') ?>>PENDENTE</option>
        <option value="PAGO"       <?= ($statusFiltro==='PAGO'       ? 'selected' : '') ?>>PAGO</option>
        <option value="ENVIADO"    <?= ($statusFiltro==='ENVIADO'    ? 'selected' : '') ?>>ENVIADO</option>
        <option value="CONCLUIDO"  <?= ($statusFiltro==='CONCLUIDO'  ? 'selected' : '') ?>>CONCLUIDO</option>
        <option value="CANCELADO"  <?= ($statusFiltro==='CANCELADO'  ? 'selected' : '') ?>>CANCELADO</option>
      </select>
    </div>

    <div>
      <label>Nome do Cliente:</label><br>
      <input type="text" name="nome" placeholder="Ex: João" value="<?= htmlspecialchars($nomeFiltro) ?>">
    </div>

    <div style="display: flex; align-items: flex-end;">
      <button type="submit" class="btn btn-primario">Filtrar</button>
    </div>

  </div>
</form>

<!-- Tabela de pedidos -->
<table>
  <thead>
    <tr>
      <th>
        <?= linkOrdenar('id', 'ID', $ordenar, $direcao) ?>
      </th>
      <th>
        <?= linkOrdenar('customer_name', 'Cliente', $ordenar, $direcao) ?>
      </th>
      <th>
        <?= linkOrdenar('final_value', 'Valor Final (R$)', $ordenar, $direcao) ?>
      </th>
      <th>
        <?= linkOrdenar('status', 'Status', $ordenar, $direcao) ?>
      </th>
      <th>
        <?= linkOrdenar('created_at', 'Criação', $ordenar, $direcao) ?>
      </th>
      <th>
        <?= linkOrdenar('updated_at', 'Última Movimentação', $ordenar, $direcao) ?>
      </th>
      <th>Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($orders)): ?>
      <tr>
        <td colspan="7" style="text-align: center;">
          Nenhum pedido encontrado nesse período/filtro.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($orders as $ord): ?>
        <tr>
          <td><?= $ord['id'] ?></td>
          <td><?= htmlspecialchars($ord['customer_name']) ?></td>
          <td><?= number_format($ord['final_value'], 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($ord['status']) ?></td>
          <td><?= date('d/m/Y H:i:s', strtotime($ord['created_at'])) ?></td>
          <td><?= date('d/m/Y H:i:s', strtotime($ord['updated_at'])) ?></td>
          <td>
            <!-- Link para detalhes -->
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
// Inclui rodapé
include 'rodape.php';
