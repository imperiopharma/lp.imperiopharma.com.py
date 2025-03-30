<?php
/**
 * gerarFechamento.php
 *
 * Script responsável por consolidar as vendas do dia (ou data arbitrária)
 * na tabela daily_closings, calculando total de pedidos, receita, custo e lucro.
 */

// CONFIG DO BANCO
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

    // Verifica se foi passado "?data=YYYY-MM-DD" na URL
    // senão pega a data atual
    $dataFechamento = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

    // 1) Buscar pedidos do dia, excluindo CANCELADOS
    // Obs: ajuste conforme lógica de status
    $sql = "SELECT 
                COUNT(*) AS qtd,
                COALESCE(SUM(final_value),0) AS sum_revenue,
                COALESCE(SUM(cost_total),0)  AS sum_cost
            FROM orders
            WHERE DATE(created_at) = :data
              AND status <> 'CANCELADO'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':data' => $dataFechamento]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $qtdPedidos   = (int) $row['qtd'];
    $totalReceita = (float) $row['sum_revenue'];
    $totalCusto   = (float) $row['sum_cost'];
    $totalLucro   = $totalReceita - $totalCusto;

    // 2) Verifica se daily_closings já tem registro para esta data
    $sqlCk = "SELECT id FROM daily_closings WHERE closing_date = :cdate";
    $stmtCk = $pdo->prepare($sqlCk);
    $stmtCk->execute([':cdate' => $dataFechamento]);
    $existe = $stmtCk->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        // UPDATE
        $sqlUp = "UPDATE daily_closings
                  SET total_orders = :o,
                      total_revenue= :r,
                      total_cost   = :ct,
                      total_profit = :p,
                      updated_at   = NOW()
                  WHERE id = :id";
        $stmtUp = $pdo->prepare($sqlUp);
        $stmtUp->execute([
            ':o'  => $qtdPedidos,
            ':r'  => $totalReceita,
            ':ct' => $totalCusto,
            ':p'  => $totalLucro,
            ':id' => $existe['id']
        ]);
    } else {
        // INSERT
        $sqlIns = "INSERT INTO daily_closings
          (closing_date, total_orders, total_revenue, total_cost, total_profit, created_at)
          VALUES
          (:cd, :o, :r, :ct, :p, NOW())";
        $stmtIns = $pdo->prepare($sqlIns);
        $stmtIns->execute([
          ':cd'=> $dataFechamento,
          ':o' => $qtdPedidos,
          ':r' => $totalReceita,
          ':ct'=> $totalCusto,
          ':p' => $totalLucro
        ]);
    }

    echo "<h2>Fechamento diário de <em>{$dataFechamento}</em> gerado/atualizado com sucesso!</h2>";
    echo "<p>Pedidos: {$qtdPedidos}, Receitas: {$totalReceita}, Custo: {$totalCusto}, Lucro: {$totalLucro}</p>";
    echo "<p><a href='painel_vendas.php'>Ir para Painel de Vendas</a></p>";

} catch (Exception $e) {
    die("Erro ao gerar fechamento: " . $e->getMessage());
}
