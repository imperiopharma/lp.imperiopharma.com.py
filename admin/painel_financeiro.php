<?php
/***************************************************************
 * painel_financeiro.php (Versão Avançada)
 *
 * Recursos:
 *  1) Movimentação Diária: exibe pedidos do dia ou de um intervalo 
 *     personalizado, com soma de venda, custo e lucro.
 *  2) Caixa Diário: fechar o dia, inserir em daily_closings, 
 *     permitindo a marcação de orders como "closed=1".
 *  3) Fechamentos (daily_closings): lista todos, com filtro de datas.
 *  4) Histórico de Caixa: pode ser similar ao de Fechamentos 
 *     ou exibir outro tipo de registro.
 *  5) Gerar Fechamento Manual de qualquer data.
 *  6) Relatórios (exemplo): relatório de vendas, se desejar 
 *     extrair dados de daily_closings ou orders.
 ***************************************************************/

// 1. Inclui o cabeçalho
include 'cabecalho.php';

/***************************************************************
 * 2. Conexão com Banco
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
    die("<p>Erro ao conectar ao BD: " . $e->getMessage() . "</p>");
}

/***************************************************************
 * 3. Seção atual via ?secao=movimentacao|caixa|fechamentos|...
 ***************************************************************/
$secao = $_GET['secao'] ?? 'movimentacao';

/***************************************************************
 * 4. Menu interno para trocar de secções
 ***************************************************************/
?>
<h2>Financeiro - Movimentações, Caixa e Fechamentos (Versão Avançada)</h2>

<div style="margin-bottom: 1rem;">
  <a href="?secao=movimentacao" class="btn btn-secundario">Movimentação</a>
  <a href="?secao=caixa"        class="btn btn-secundario">Caixa Diário</a>
  <a href="?secao=fechamentos"  class="btn btn-secundario">Fechamentos</a>
  <a href="?secao=historico"    class="btn btn-secundario">Histórico de Caixa</a>
  <a href="?secao=gerar"        class="btn btn-secundario">Gerar Fechamento</a>
  <a href="?secao=relatorios"   class="btn btn-secundario">Relatórios</a>
</div>

<?php
/***************************************************************
 * 5. MOVIMENTAÇÃO (com filtro de datas)
 ***************************************************************/
if ($secao === 'movimentacao'):
?>
<div class="card">
  <h3>Movimentação - Pedidos em um Intervalo de Datas</h3>

  <!-- FORM de filtro por intervalo (data inicial, data final) -->
  <form method="GET" style="margin-bottom: 1rem;">
    <input type="hidden" name="secao" value="movimentacao">

    <label>Data Inicial:</label>
    <input type="date" name="dataIni" value="<?= htmlspecialchars($_GET['dataIni'] ?? date('Y-m-01')) ?>">
    
    <label style="margin-left:1rem;">Data Final:</label>
    <input type="date" name="dataFim" value="<?= htmlspecialchars($_GET['dataFim'] ?? date('Y-m-d')) ?>">

    <button type="submit" class="btn btn-primario" style="margin-left:1rem;">
      Filtrar
    </button>
  </form>

  <?php
    // Data inicial e final
    $dataIni = $_GET['dataIni'] ?? date('Y-m-01');
    $dataFim = $_GET['dataFim'] ?? date('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
      echo "<div class='alert alert-perigo'>Datas inválidas! Formato esperado: YYYY-MM-DD</div>";
    } else {
      try {
          $sqlMov = "
            SELECT
              id,
              customer_name,
              final_value,
              cost_total,
              status,
              created_at
            FROM orders
            WHERE DATE(created_at) BETWEEN :ini AND :fim
            ORDER BY created_at ASC
          ";
          $stmtMov = $pdo->prepare($sqlMov);
          $stmtMov->execute([':ini' => $dataIni, ':fim' => $dataFim]);
          $pedidos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

          $somaVenda = 0;
          $somaCusto = 0;
          foreach($pedidos as $pd) {
              $somaVenda += floatval($pd['final_value']);
              $somaCusto += floatval($pd['cost_total']);
          }
          $lucro = $somaVenda - $somaCusto;

          echo "<p><strong>Período:</strong> "
               . date('d/m/Y', strtotime($dataIni)) 
               . " até " . date('d/m/Y', strtotime($dataFim)) . "</p>";
          echo "<p><strong>Pedidos:</strong> " . count($pedidos) . "</p>";
          echo "<p><strong>Receita:</strong> R$ " . number_format($somaVenda,2,',','.') . "</p>";
          echo "<p><strong>Custo:</strong> R$ " . number_format($somaCusto,2,',','.') . "</p>";
          echo "<p><strong>Lucro:</strong> R$ " . number_format($lucro,2,',','.') . "</p>";

      } catch(Exception $e) {
          echo "<div class='alert alert-perigo'>Erro ao buscar movimentação: "
               . htmlspecialchars($e->getMessage()) . "</div>";
          $pedidos = [];
      }
      ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Cliente</th>
            <th>Status</th>
            <th>Data/Hora</th>
            <th>Venda (R$)</th>
            <th>Custo (R$)</th>
            <th>Lucro (R$)</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($pedidos)): ?>
          <tr><td colspan="7" style="text-align:center;">Nenhum pedido no intervalo.</td></tr>
        <?php else: ?>
          <?php foreach($pedidos as $p):
            $v = floatval($p['final_value']);
            $c = floatval($p['cost_total']);
            $l = $v - $c;
          ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td><?= htmlspecialchars($p['customer_name']) ?></td>
            <td><?= htmlspecialchars($p['status']) ?></td>
            <td><?= date('d/m/Y H:i:s', strtotime($p['created_at'])) ?></td>
            <td>R$ <?= number_format($v,2,',','.') ?></td>
            <td>R$ <?= number_format($c,2,',','.') ?></td>
            <td>R$ <?= number_format($l,2,',','.') ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
      <?php
    }
  ?>
</div>

<?php
/***************************************************************
 * 6. CAIXA DIÁRIO
 ***************************************************************/
elseif ($secao === 'caixa'):
?>
<div class="card">
  <h3>Caixa Diário (Fechar Dia)</h3>
  <p>
    Aqui você vê os pedidos de hoje (ou de uma data específica),
    pode fechar o dia e inserir em daily_closings automaticamente.
  </p>
  <!-- Exemplo: Mesmo conceito do snippet anterior 
       com TOT Pedidos, TOT Vendas, TOT Custo etc. -->
  <?php
    $dataHoje = date('Y-m-d');

    // Se for POST "fecharDia"
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao']) && $_POST['acao']==='fecharDia') {
        // Checar se já existe daily_closings para $dataHoje etc.
        // (Mesmo código do snippet anterior)
        try {
            $sqlSum = "
              SELECT
                COUNT(*) as qtd,
                COALESCE(SUM(final_value),0) as totVenda,
                COALESCE(SUM(cost_total),0) as totCusto
              FROM orders
              WHERE DATE(created_at)=:dt
                AND status!='CANCELADO'
                AND closed=0
            ";
            $stmtSum = $pdo->prepare($sqlSum);
            $stmtSum->execute([':dt' => $dataHoje]);
            $rowSum = $stmtSum->fetch(PDO::FETCH_ASSOC);

            $qtdPed   = (int)$rowSum['qtd'];
            $totVenda = (float)$rowSum['totVenda'];
            $totCusto = (float)$rowSum['totCusto'];
            $totLucro = $totVenda - $totCusto;

            // Checa daily_closings
            $stmtChk = $pdo->prepare("SELECT id FROM daily_closings WHERE closing_date=:d");
            $stmtChk->execute([':d'=>$dataHoje]);
            $existe = $stmtChk->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                echo "<div class='alert alert-info'>
                        Dia $dataHoje já fechado anteriormente!
                      </div>";
            } else {
                // Insert
                $insSql = "
                  INSERT INTO daily_closings
                    (closing_date, total_orders, total_revenue, total_cost, total_profit, created_at)
                  VALUES
                    (:cd, :o, :r, :ct, :pf, NOW())
                ";
                $stmtI = $pdo->prepare($insSql);
                $stmtI->execute([
                  ':cd'=>$dataHoje,
                  ':o' =>$qtdPed,
                  ':r' =>$totVenda,
                  ':ct'=>$totCusto,
                  ':pf'=>$totLucro
                ]);

                // Marcar pedidos como closed=1
                $pdo->exec("UPDATE orders SET closed=1 WHERE DATE(created_at)='$dataHoje' AND status!='CANCELADO'");
                echo "<div class='alert alert-sucesso'>Fechamento do dia $dataHoje realizado!</div>";
            }
        } catch(Exception $fe) {
            echo "<div class='alert alert-perigo'>Erro ao fechar dia: "
                 . htmlspecialchars($fe->getMessage()) . "</div>";
        }
    }

    // Buscar pedidos do dia
    try {
        $sqlPedHoje = "
          SELECT
            id,
            customer_name,
            final_value,
            cost_total,
            status,
            created_at
          FROM orders
          WHERE DATE(created_at)=:dt
            AND status!='CANCELADO'
            AND closed=0
          ORDER BY created_at DESC
        ";
        $stmtPH = $pdo->prepare($sqlPedHoje);
        $stmtPH->execute([':dt'=>$dataHoje]);
        $lista = $stmtPH->fetchAll(PDO::FETCH_ASSOC);

        $sumVenda = 0;
        $sumCusto = 0;
        foreach ($lista as $li) {
          $sumVenda += floatval($li['final_value']);
          $sumCusto += floatval($li['cost_total']);
        }
        $sumLucro = $sumVenda - $sumCusto;
        echo "<p><strong>Pedidos Hoje (Abertos):</strong> ".count($lista)."</p>";
        echo "<p><strong>Receita:</strong> R$ ".number_format($sumVenda,2,',','.')."</p>";
        echo "<p><strong>Custo:</strong> R$ ".number_format($sumCusto,2,',','.')."</p>";
        echo "<p><strong>Lucro:</strong> R$ ".number_format($sumLucro,2,',','.')."</p>";
    } catch(Exception $e) {
        echo "<div class='alert alert-perigo'>Erro ao buscar pedidos dia: "
             . htmlspecialchars($e->getMessage()) . "</div>";
        $lista=[];
    }
  ?>
  <form method="POST" onsubmit="return confirm('Fechar o dia de hoje?');" style="margin-top:1rem;">
    <input type="hidden" name="acao" value="fecharDia">
    <button type="submit" class="btn btn-perigo">
      Fechar Dia (<?= date('d/m/Y') ?>)
    </button>
  </form>

  <!-- Exibe tabela de pedidos do dia -->
  <table style="margin-top:1rem;">
    <thead>
      <tr>
        <th>ID</th>
        <th>Cliente</th>
        <th>Status</th>
        <th>Data/Hora</th>
        <th>Venda</th>
        <th>Custo</th>
        <th>Lucro</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($lista)): ?>
        <tr><td colspan="7" style="text-align:center;">Nenhum pedido aberto hoje.</td></tr>
      <?php else: ?>
        <?php foreach($lista as $lx):
          $v=floatval($lx['final_value']);
          $c=floatval($lx['cost_total']);
          $l=$v-$c;
        ?>
        <tr>
          <td><?= $lx['id'] ?></td>
          <td><?= htmlspecialchars($lx['customer_name']) ?></td>
          <td><?= htmlspecialchars($lx['status']) ?></td>
          <td><?= date('d/m/Y H:i:s', strtotime($lx['created_at'])) ?></td>
          <td>R$ <?= number_format($v,2,',','.') ?></td>
          <td>R$ <?= number_format($c,2,',','.') ?></td>
          <td>R$ <?= number_format($l,2,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
/***************************************************************
 * 7. FECHAMENTOS
 ***************************************************************/
elseif ($secao === 'fechamentos'):
?>
<div class="card">
  <h3>Fechamentos (daily_closings)</h3>
  <!-- Filtro por data inicial e final (opcional) -->
  <form method="GET" style="margin-bottom:1rem;">
    <input type="hidden" name="secao" value="fechamentos">
    <label>Data Inicial:</label>
    <input type="date" name="iniF" value="<?= htmlspecialchars($_GET['iniF'] ?? date('Y-m-01')) ?>">
    <label style="margin-left:1rem;">Data Final:</label>
    <input type="date" name="fimF" value="<?= htmlspecialchars($_GET['fimF'] ?? date('Y-m-d')) ?>">
    <button type="submit" class="btn btn-primario" style="margin-left:1rem;">Filtrar</button>
  </form>
  <?php
    $iniF = $_GET['iniF'] ?? date('Y-m-01');
    $fimF = $_GET['fimF'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $iniF) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fimF)) {
      echo "<div class='alert alert-perigo'>Intervalo inválido!</div>";
    } else {
      try {
          $sqlF = "
            SELECT
              id,
              closing_date,
              total_orders,
              total_revenue,
              total_cost,
              total_profit,
              created_at,
              updated_at
            FROM daily_closings
            WHERE closing_date BETWEEN :i AND :f
            ORDER BY closing_date DESC
          ";
          $stmtF = $pdo->prepare($sqlF);
          $stmtF->execute([':i'=>$iniF, ':f'=>$fimF]);
          $fechs = $stmtF->fetchAll(PDO::FETCH_ASSOC);

          echo "<p>Exibindo fechamentos de ".date('d/m/Y', strtotime($iniF))
               ." até ".date('d/m/Y', strtotime($fimF))."</p>";
      } catch(Exception $e) {
          echo "<div class='alert alert-perigo'>Erro ao buscar fechamentos: "
               . htmlspecialchars($e->getMessage()) . "</div>";
          $fechs = [];
      }
      ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Data</th>
            <th>Pedidos</th>
            <th>Receita</th>
            <th>Custo</th>
            <th>Lucro</th>
            <th>Criado em</th>
            <th>Atualizado em</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($fechs)): ?>
            <tr><td colspan="8" style="text-align:center;">Nenhum registro.</td></tr>
          <?php else: ?>
            <?php foreach($fechs as $fc): ?>
            <tr>
              <td><?= $fc['id'] ?></td>
              <td><?= $fc['closing_date'] ?></td>
              <td><?= $fc['total_orders'] ?></td>
              <td>R$ <?= number_format($fc['total_revenue'],2,',','.') ?></td>
              <td>R$ <?= number_format($fc['total_cost'],2,',','.') ?></td>
              <td>R$ <?= number_format($fc['total_profit'],2,',','.') ?></td>
              <td><?= $fc['created_at'] ?></td>
              <td><?= $fc['updated_at'] ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <?php
    }
  ?>
</div>

<?php
/***************************************************************
 * 8. HISTÓRICO DE CAIXA
 ***************************************************************/
elseif ($secao === 'historico'):
?>
<div class="card">
  <h3>Histórico de Caixa</h3>
  <p>Aqui pode ser parecido com “fechamentos”, mas 
     exibindo outra visão ou dados extras (obs, etc.).</p>
  <?php
    try {
        $stmtH = $pdo->query("
          SELECT id, closing_date, total_orders, total_revenue,
                 total_cost, total_profit, created_at, updated_at
          FROM daily_closings
          ORDER BY closing_date DESC, id DESC
        ");
        $histCx = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        echo "<div class='alert alert-perigo'>Erro ao buscar histórico: "
             . htmlspecialchars($e->getMessage()) . "</div>";
        $histCx = [];
    }
  ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Data</th>
        <th>Pedidos</th>
        <th>Receita</th>
        <th>Custo</th>
        <th>Lucro</th>
        <th>Criado</th>
        <th>Atualizado</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($histCx)): ?>
        <tr><td colspan="8" style="text-align:center;">Nenhum histórico.</td></tr>
      <?php else: ?>
        <?php foreach($histCx as $h): ?>
        <tr>
          <td><?= $h['id'] ?></td>
          <td><?= $h['closing_date'] ?></td>
          <td><?= $h['total_orders'] ?></td>
          <td>R$ <?= number_format($h['total_revenue'],2,',','.') ?></td>
          <td>R$ <?= number_format($h['total_cost'],2,',','.') ?></td>
          <td>R$ <?= number_format($h['total_profit'],2,',','.') ?></td>
          <td><?= $h['created_at'] ?></td>
          <td><?= $h['updated_at'] ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
/***************************************************************
 * 9. GERAR FECHAMENTO (Manual)
 ***************************************************************/
elseif ($secao === 'gerar'):
?>
<div class="card">
  <h3>Gerar/Atualizar Fechamento Manual</h3>
  <p>Selecione uma data (YYYY-MM-DD) para recalcular daily_closings.</p>
  <form method="GET" style="margin-bottom:1rem;">
    <input type="hidden" name="secao" value="gerar">
    <label>Data:</label>
    <input type="date" name="dataFech" value="<?= htmlspecialchars($_GET['dataFech'] ?? '') ?>" required>
    <button type="submit" class="btn btn-primario">Gerar Fechamento</button>
  </form>
  <?php
    if (isset($_GET['dataFech'])) {
        $dataFech = $_GET['dataFech'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFech)) {
            echo "<div class='alert alert-perigo'>Data inválida!</div>";
        } else {
            try {
                $sqlGF = "
                  SELECT
                    COUNT(*) AS qtd,
                    COALESCE(SUM(final_value),0) AS sum_rev,
                    COALESCE(SUM(cost_total),0)  AS sum_ct
                  FROM orders
                  WHERE DATE(created_at)=:df
                    AND status!='CANCELADO'
                ";
                $stmtGF = $pdo->prepare($sqlGF);
                $stmtGF->execute([':df'=>$dataFech]);
                $rowGF = $stmtGF->fetch(PDO::FETCH_ASSOC);

                if ($rowGF) {
                    $qtdP = (int)$rowGF['qtd'];
                    $ttR  = (float)$rowGF['sum_rev'];
                    $ttC  = (float)$rowGF['sum_ct'];
                    $luc  = $ttR - $ttC;

                    // Check se existe daily_closings p/ data
                    $stmtChk = $pdo->prepare("SELECT id FROM daily_closings WHERE closing_date=:cd");
                    $stmtChk->execute([':cd'=>$dataFech]);
                    $exEx = $stmtChk->fetch(PDO::FETCH_ASSOC);

                    if ($exEx) {
                        // Update
                        $updSql = "
                          UPDATE daily_closings
                          SET total_orders=:o,
                              total_revenue=:r,
                              total_cost=:c,
                              total_profit=:p,
                              updated_at=NOW()
                          WHERE id=:id
                        ";
                        $stmtUp = $pdo->prepare($updSql);
                        $stmtUp->execute([
                            ':o'=>$qtdP,
                            ':r'=>$ttR,
                            ':c'=>$ttC,
                            ':p'=>$luc,
                            ':id'=>$exEx['id']
                        ]);
                        echo "<div class='alert alert-sucesso'>
                                Fechamento para $dataFech atualizado com sucesso!
                              </div>";
                    } else {
                        // Insert
                        $insS = "
                          INSERT INTO daily_closings
                            (closing_date, total_orders, total_revenue, total_cost, total_profit, created_at)
                          VALUES
                            (:cd, :o, :r, :c, :p, NOW())
                        ";
                        $stmtIns = $pdo->prepare($insS);
                        $stmtIns->execute([
                            ':cd'=>$dataFech,
                            ':o'=>$qtdP,
                            ':r'=>$ttR,
                            ':c'=>$ttC,
                            ':p'=>$luc
                        ]);
                        echo "<div class='alert alert-sucesso'>
                                Fechamento para $dataFech gerado com sucesso!
                              </div>";
                    }
                } else {
                    echo "<div class='alert alert-info'>Nenhum pedido nessa data.</div>";
                }
            } catch(Exception $ger) {
                echo "<div class='alert alert-perigo'>Erro ao gerar fechamento: "
                     . htmlspecialchars($ger->getMessage()) . "</div>";
            }
        }
    }
  ?>
</div>

<?php
/***************************************************************
 * 10. RELATÓRIOS (Exemplo)
 ***************************************************************/
elseif ($secao === 'relatorios'):
?>
<div class="card">
  <h3>Relatórios de Vendas (Exemplo)</h3>
  <p>Selecione um intervalo para ver a soma de daily_closings 
     ou Orders agregados, etc.</p>

  <form method="GET" style="margin-bottom:1rem;">
    <input type="hidden" name="secao" value="relatorios">
    <label>De:</label>
    <input type="date" name="iniR" value="<?= htmlspecialchars($_GET['iniR'] ?? date('Y-01-01')) ?>">
    <label>Até:</label>
    <input type="date" name="fimR" value="<?= htmlspecialchars($_GET['fimR'] ?? date('Y-m-d')) ?>">
    <button type="submit" class="btn btn-primario">Gerar Relatório</button>
  </form>

  <?php
    $iniR = $_GET['iniR'] ?? date('Y-01-01');
    $fimR = $_GET['fimR'] ?? date('Y-m-d');

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $iniR) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fimR)) {
        // Exemplo: agregar daily_closings
        try {
            $sqlRel = "
              SELECT 
                COUNT(*) AS dias,
                COALESCE(SUM(total_orders),0)  AS somaPedidos,
                COALESCE(SUM(total_revenue),0) AS somaReceita,
                COALESCE(SUM(total_cost),0)    AS somaCusto,
                COALESCE(SUM(total_profit),0)  AS somaLucro
              FROM daily_closings
              WHERE closing_date BETWEEN :ir AND :fr
            ";
            $stmtRel = $pdo->prepare($sqlRel);
            $stmtRel->execute([':ir'=>$iniR, ':fr'=>$fimR]);
            $rel = $stmtRel->fetch(PDO::FETCH_ASSOC);

            echo "<p><strong>Dias Fechados:</strong> " . $rel['dias'] . "</p>";
            echo "<p><strong>Total de Pedidos:</strong> " . $rel['somaPedidos'] . "</p>";
            echo "<p><strong>Receita:</strong> R$ " . number_format($rel['somaReceita'],2,',','.') . "</p>";
            echo "<p><strong>Custo:</strong> R$ " . number_format($rel['somaCusto'],2,',','.') . "</p>";
            echo "<p><strong>Lucro:</strong> R$ " . number_format($rel['somaLucro'],2,',','.') . "</p>";

        } catch(Exception $rErr) {
            echo "<div class='alert alert-perigo'>Erro ao gerar relatório: "
                 . htmlspecialchars($rErr->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-perigo'>Intervalo de datas inválido.</div>";
    }
  ?>
</div>

<?php
/***************************************************************
 * 11. Fechamento default
 ***************************************************************/
else:
  echo "<p>Seção não encontrada.</p>";
endif;

/***************************************************************
 * 12. Inclui Rodapé
 ***************************************************************/
include 'rodape.php';
