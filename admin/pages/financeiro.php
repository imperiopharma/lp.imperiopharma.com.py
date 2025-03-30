<?php
/**************************************************************
 * admin/pages/financeiro.php
 *
 * Painel Financeiro Avançado com vária seções (via ?action=...):
 *   - ?action=movimentacao => Movimentação (intervalo)
 *   - ?action=caixa       => Caixa Diário (fechar dia manual)
 *   - ?action=fechamentos => Histórico (daily_closings)
 *   - ?action=relatorio   => Relatório Avançado (ex.: por categoria, top 10)
 *   - ?action=comparar    => Comparar Períodos (A vs B)
 **************************************************************/

// Supondo que seu config.php ou algo similar já traga a conexão PDO em $pdo
// Caso contrário, inclua manualmente:
// require_once __DIR__ . '/../inc/config.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : 'movimentacao';

// ------------------------------------------------
// 1) Menu Interno / Navegação
// ------------------------------------------------
?>
<h2 class="mb-3">Painel Financeiro - Avançado</h2>
<nav class="mb-4">
  <!-- Podemos usar .btn-group do Bootstrap, ou apenas links estilizados -->
  <a href="index.php?page=financeiro&action=movimentacao"
     class="btn btn-outline-primary btn-sm me-2 <?= ($action==='movimentacao'?'active-link':'') ?>">
    Movimentação
  </a>
  <a href="index.php?page=financeiro&action=caixa"
     class="btn btn-outline-primary btn-sm me-2 <?= ($action==='caixa'?'active-link':'') ?>">
    Caixa Diário
  </a>
  <a href="index.php?page=financeiro&action=fechamentos"
     class="btn btn-outline-primary btn-sm me-2 <?= ($action==='fechamentos'?'active-link':'') ?>">
    Fechamentos
  </a>
  <a href="index.php?page=financeiro&action=relatorio"
     class="btn btn-outline-primary btn-sm me-2 <?= ($action==='relatorio'?'active-link':'') ?>">
    Relatório
  </a>
  <a href="index.php?page=financeiro&action=comparar"
     class="btn btn-outline-primary btn-sm me-2 <?= ($action==='comparar'?'active-link':'') ?>">
    Comparar
  </a>
</nav>

<?php
// ------------------------------------------------
// 2) Switch para exibir cada sub-seção
// ------------------------------------------------

switch ($action) {

  // =============================
  // (A) Movimentação (Intervalo)
  // =============================
  case 'movimentacao':
  default:
    ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Movimentação (por Intervalo)</h5>
      </div>
      <div class="card-body">
        <?php
        $hoje = date('Y-m-d');
        $ini  = isset($_GET['ini']) ? trim($_GET['ini']) : date('Y-m-01');
        $fim  = isset($_GET['fim']) ? trim($_GET['fim']) : $hoje;
        ?>
        <form method="GET" class="row g-3 align-items-end mb-3">
          <input type="hidden" name="page" value="financeiro">
          <input type="hidden" name="action" value="movimentacao">

          <div class="col-auto">
            <label for="ini" class="form-label mb-0"><strong>Data Inicial</strong></label>
            <input type="date" class="form-control form-control-sm" name="ini"
                   id="ini" value="<?= htmlspecialchars($ini) ?>">
          </div>
          <div class="col-auto">
            <label for="fim" class="form-label mb-0"><strong>Data Final</strong></label>
            <input type="date" class="form-control form-control-sm" name="fim"
                   id="fim" value="<?= htmlspecialchars($fim) ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
          </div>
        </form>

        <?php
        // Valida datas
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
          try {
            // Pedidos não cancelados no intervalo
            $sql = "
              SELECT
                COUNT(*) AS totalPed,
                COALESCE(SUM(final_value), 0) AS sumRev,
                COALESCE(SUM(cost_total), 0)  AS sumCst
              FROM orders
              WHERE DATE(created_at) BETWEEN :i AND :f
                AND status <> 'CANCELADO'
            ";
            $stm = $pdo->prepare($sql);
            $stm->execute([':i'=>$ini, ':f'=>$fim]);
            $rowSum = $stm->fetch(PDO::FETCH_ASSOC);

            $nPed = (int)($rowSum['totalPed'] ?? 0);
            $sRev = (float)($rowSum['sumRev']  ?? 0);
            $sCst = (float)($rowSum['sumCst']  ?? 0);
            $sLuc = $sRev - $sCst;

            ?>
            <div class="alert alert-info">
              <p class="mb-1"><strong>Intervalo:</strong> 
                <?= date('d/m/Y', strtotime($ini)) ?> até 
                <?= date('d/m/Y', strtotime($fim)) ?>
              </p>
              <p class="mb-1"><strong>Pedidos:</strong> <?= $nPed ?></p>
              <p class="mb-1"><strong>Receita:</strong> R$ <?= number_format($sRev,2,',','.') ?></p>
              <p class="mb-1"><strong>Custo:</strong> R$ <?= number_format($sCst,2,',','.') ?></p>
              <p class="mb-0"><strong>Lucro:</strong> R$ <?= number_format($sLuc,2,',','.') ?></p>
            </div>
            <?php

            // Listar pedidos do intervalo
            $sql2 = "
              SELECT
                id, customer_name, status,
                final_value, cost_total,
                created_at
              FROM orders
              WHERE DATE(created_at) BETWEEN :i AND :f
                AND status <> 'CANCELADO'
              ORDER BY created_at ASC
            ";
            $st2 = $pdo->prepare($sql2);
            $st2->execute([':i'=>$ini, ':f'=>$fim]);
            $lst = $st2->fetchAll(PDO::FETCH_ASSOC);

            if (empty($lst)) {
              echo "<p>Nenhum pedido no intervalo.</p>";
            } else {
              ?>
              <hr>
              <h6>Pedidos Encontrados</h6>
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead class="table-light">
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
                    <?php
                    foreach ($lst as $pp) {
                      $fv = (float)$pp['final_value'];
                      $ct = (float)$pp['cost_total'];
                      $lc = $fv - $ct;
                      ?>
                      <tr>
                        <td><?= $pp['id'] ?></td>
                        <td><?= htmlspecialchars($pp['customer_name']) ?></td>
                        <td><?= htmlspecialchars($pp['status']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($pp['created_at'])) ?></td>
                        <td><?= number_format($fv,2,',','.') ?></td>
                        <td><?= number_format($ct,2,',','.') ?></td>
                        <td><?= number_format($lc,2,',','.') ?></td>
                      </tr>
                      <?php
                    }
                    ?>
                  </tbody>
                </table>
              </div>
              <?php
            }
          } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Erro ao buscar movimentação: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
          }
        } else {
          echo "<div class='alert alert-warning'>Selecione um intervalo de datas válido.</div>";
        }
        ?>
      </div>
    </div>
    <?php
    break;

  // =============================
  // (B) Caixa Diário
  // =============================
  case 'caixa':
    ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Caixa Diário (Hoje)</h5>
      </div>
      <div class="card-body">
        <?php
        // Se POST => fecharDia
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'fecharDia') {
          $hoje = date('Y-m-d');
          try {
            // Verifica se já existe daily_closings do dia
            $ck = $pdo->prepare("SELECT id FROM daily_closings WHERE closing_date = ?");
            $ck->execute([$hoje]);
            $ex = $ck->fetch(PDO::FETCH_ASSOC);

            if ($ex) {
              echo "<div class='alert alert-info'>
                      Dia $hoje já havia sido fechado (#{$ex['id']}).
                    </div>";
            } else {
              // Somar
              $stS = $pdo->prepare("
                SELECT
                  COUNT(*) AS qtd,
                  COALESCE(SUM(final_value), 0) AS sumRev,
                  COALESCE(SUM(cost_total), 0)  AS sumCst
                FROM orders
                WHERE DATE(created_at) = :d
                  AND status <> 'CANCELADO'
                  AND closed = 0
              ");
              $stS->execute([':d' => $hoje]);
              $rx = $stS->fetch(PDO::FETCH_ASSOC);

              $qtd  = (int)($rx['qtd'] ?? 0);
              $sRev = (float)($rx['sumRev'] ?? 0);
              $sCst = (float)($rx['sumCst'] ?? 0);
              $sLuc = $sRev - $sCst;

              // Insert em daily_closings
              $pdo->prepare("
                INSERT INTO daily_closings
                  (closing_date, total_orders, total_revenue,
                   total_cost, total_profit, created_at)
                VALUES
                  (?, ?, ?, ?, ?, NOW())
              ")->execute([$hoje, $qtd, $sRev, $sCst, $sLuc]);

              // Marcar pedidos como closed=1
              $pdo->exec("
                UPDATE orders
                SET closed = 1
                WHERE DATE(created_at) = '$hoje'
                  AND status <> 'CANCELADO'
              ");

              echo "<div class='alert alert-success'>
                      Fechamento do dia $hoje realizado com sucesso!
                    </div>";
            }
          } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Erro ao fechar dia: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
          }
        }

        // Listar pedidos de hoje
        $hoje = date('Y-m-d');
        try {
          $stH = $pdo->prepare("
            SELECT id, customer_name, final_value, cost_total,
                   (final_value - cost_total) AS lucro,
                   status, created_at
            FROM orders
            WHERE DATE(created_at) = :d
              AND status <> 'CANCELADO'
              AND closed = 0
            ORDER BY created_at DESC
          ");
          $stH->execute([':d' => $hoje]);
          $hojePed = $stH->fetchAll(PDO::FETCH_ASSOC);

          $sumV = 0;
          $sumC = 0;
          foreach ($hojePed as $hp) {
            $sumV += (float)$hp['final_value'];
            $sumC += (float)$hp['cost_total'];
          }
          $sumL = $sumV - $sumC;

          ?>
          <p><strong>Data de Hoje:</strong> <?= date('d/m/Y') ?></p>
          <p><strong>Pedidos (abertos) Hoje:</strong> <?= count($hojePed) ?></p>
          <p><strong>Receita:</strong> R$ <?= number_format($sumV,2,',','.') ?></p>
          <p><strong>Custo:</strong> R$ <?= number_format($sumC,2,',','.') ?></p>
          <p><strong>Lucro:</strong> R$ <?= number_format($sumL,2,',','.') ?></p>

          <form method="POST" class="mt-3" onsubmit="return confirm('Fechar dia de hoje?');">
            <input type="hidden" name="acao" value="fecharDia">
            <button type="submit" class="btn btn-danger btn-sm">
              Fechar Dia (<?= date('d/m/Y') ?>)
            </button>
          </form>

          <?php
          if (empty($hojePed)) {
            echo "<p class='mt-3'>Nenhum pedido aberto hoje.</p>";
          } else {
            ?>
            <hr>
            <h6>Pedidos Abertos Hoje</h6>
            <div class="table-responsive mt-2">
              <table class="table table-bordered table-striped">
                <thead class="table-light">
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
                  <?php
                  foreach ($hojePed as $hp) {
                    $fv = (float)$hp['final_value'];
                    $ct = (float)$hp['cost_total'];
                    $lc = $fv - $ct;
                    ?>
                    <tr>
                      <td><?= $hp['id'] ?></td>
                      <td><?= htmlspecialchars($hp['customer_name']) ?></td>
                      <td><?= htmlspecialchars($hp['status']) ?></td>
                      <td><?= date('d/m/Y H:i', strtotime($hp['created_at'])) ?></td>
                      <td><?= number_format($fv,2,',','.') ?></td>
                      <td><?= number_format($ct,2,',','.') ?></td>
                      <td><?= number_format($lc,2,',','.') ?></td>
                    </tr>
                    <?php
                  }
                  ?>
                </tbody>
              </table>
            </div>
            <?php
          }
        } catch (Exception $e) {
          echo "<div class='alert alert-danger'>Erro ao listar pedidos de hoje: "
               . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
      </div>
    </div>
    <?php
    break;

  // =============================
  // (C) Fechamentos (Histórico)
  // =============================
  case 'fechamentos':
    ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Histórico de Fechamentos (daily_closings)</h5>
      </div>
      <div class="card-body">
        <?php
        $di = isset($_GET['di']) ? trim($_GET['di']) : date('Y-01-01');
        $df = isset($_GET['df']) ? trim($_GET['df']) : date('Y-m-d');
        ?>
        <form method="GET" class="row g-3 align-items-end mb-3">
          <input type="hidden" name="page" value="financeiro">
          <input type="hidden" name="action" value="fechamentos">

          <div class="col-auto">
            <label for="di" class="form-label mb-0"><strong>Data Inicial</strong></label>
            <input type="date" class="form-control form-control-sm" name="di" id="di"
                   value="<?= htmlspecialchars($di) ?>">
          </div>
          <div class="col-auto">
            <label for="df" class="form-label mb-0"><strong>Data Final</strong></label>
            <input type="date" class="form-control form-control-sm" name="df" id="df"
                   value="<?= htmlspecialchars($df) ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
          </div>
        </form>

        <?php
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$di) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$df)) {
          try {
            $stF = $pdo->prepare("
              SELECT *
              FROM daily_closings
              WHERE closing_date BETWEEN :i AND :f
              ORDER BY closing_date DESC
            ");
            $stF->execute([':i'=>$di, ':f'=>$df]);
            $rows = $stF->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
              echo "<p>Nenhum fechamento no período.</p>";
            } else {
              // soma
              $sumO=0; $sumR=0; $sumC=0; $sumP=0;
              foreach($rows as $rw){
                $sumO += (int)$rw['total_orders'];
                $sumR += (float)$rw['total_revenue'];
                $sumC += (float)$rw['total_cost'];
                $sumP += (float)$rw['total_profit'];
              }
              ?>
              <div class="alert alert-info">
                <p class="mb-1"><strong>Pedidos Somados:</strong> <?= $sumO ?></p>
                <p class="mb-1"><strong>Receita:</strong> R$ <?= number_format($sumR,2,',','.') ?></p>
                <p class="mb-1"><strong>Custo:</strong> R$ <?= number_format($sumC,2,',','.') ?></p>
                <p class="mb-0"><strong>Lucro:</strong> R$ <?= number_format($sumP,2,',','.') ?></p>
              </div>
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead class="table-light">
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
                  <?php
                  foreach($rows as $fc) {
                    ?>
                    <tr>
                      <td><?= $fc['id'] ?></td>
                      <td><?= $fc['closing_date'] ?></td>
                      <td><?= $fc['total_orders'] ?></td>
                      <td><?= number_format($fc['total_revenue'],2,',','.') ?></td>
                      <td><?= number_format($fc['total_cost'],2,',','.') ?></td>
                      <td><?= number_format($fc['total_profit'],2,',','.') ?></td>
                      <td><?= $fc['created_at'] ?></td>
                      <td><?= $fc['updated_at'] ?></td>
                    </tr>
                    <?php
                  }
                  ?>
                  </tbody>
                </table>
              </div>
              <?php
            }
          } catch(Exception $e) {
            echo "<div class='alert alert-danger'>Erro ao buscar fechamentos: "
                 . htmlspecialchars($e->getMessage())."</div>";
          }
        } else {
          echo "<div class='alert alert-warning'>Intervalo de datas inválido.</div>";
        }
        ?>
      </div>
    </div>
    <?php
    break;

  // =============================
  // (D) Relatório Avançado
  // =============================
  case 'relatorio':
    ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Relatório Avançado</h5>
      </div>
      <div class="card-body">
        <p>
          Exemplos de relatórios possíveis:
          <ul>
            <li>Vendas por categoria</li>
            <li>Top 10 produtos</li>
            <li>Gráficos de Receita x Lucro por dia</li>
          </ul>
        </p>
        <?php
        $d1 = isset($_GET['d1']) ? trim($_GET['d1']) : date('Y-m-01');
        $d2 = isset($_GET['d2']) ? trim($_GET['d2']) : date('Y-m-d');
        ?>
        <form method="GET" class="row g-3 align-items-end mb-3">
          <input type="hidden" name="page" value="financeiro">
          <input type="hidden" name="action" value="relatorio">

          <div class="col-auto">
            <label for="d1" class="form-label mb-0"><strong>Data Inicial</strong></label>
            <input type="date" class="form-control form-control-sm" name="d1" id="d1"
                   value="<?= htmlspecialchars($d1) ?>">
          </div>
          <div class="col-auto">
            <label for="d2" class="form-label mb-0"><strong>Data Final</strong></label>
            <input type="date" class="form-control form-control-sm" name="d2" id="d2"
                   value="<?= htmlspecialchars($d2) ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
          </div>
        </form>

        <?php
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$d1) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$d2)) {
          try {
            // Vendas por categoria
            $sqlCat = "
              SELECT
                p.category AS cat,
                SUM(oi.quantity) AS totalQty,
                COALESCE(SUM(oi.subtotal), 0) AS totalSub
              FROM order_items oi
              JOIN orders o   ON oi.order_id = o.id
              JOIN products p ON oi.product_id = p.id
              WHERE DATE(o.created_at) BETWEEN :i AND :f
                AND o.status <> 'CANCELADO'
              GROUP BY p.category
              ORDER BY totalSub DESC
            ";
            $stCat = $pdo->prepare($sqlCat);
            $stCat->execute([':i'=>$d1, ':f'=>$d2]);
            $cats = $stCat->fetchAll(PDO::FETCH_ASSOC);

            ?>
            <div class="alert alert-info">
              <strong>Período:</strong> 
              <?= date('d/m/Y', strtotime($d1)) ?> até 
              <?= date('d/m/Y', strtotime($d2)) ?>
            </div>
            <?php

            if (empty($cats)) {
              echo "<p>Nenhum resultado de vendas por categoria.</p>";
            } else {
              ?>
              <h6>Vendas por Categoria</h6>
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead class="table-light">
                    <tr>
                      <th>Categoria</th>
                      <th>Quantidade Vendida</th>
                      <th>Subtotal (R$)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    foreach ($cats as $c) {
                      $catNm = $c['cat'] ?: 'SEM CATEGORIA';
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($catNm) ?></td>
                        <td><?= $c['totalQty'] ?></td>
                        <td><?= number_format($c['totalSub'],2,',','.') ?></td>
                      </tr>
                      <?php
                    }
                    ?>
                  </tbody>
                </table>
              </div>
              <?php
            }

            echo "<hr><h6>Top 10 Produtos</h6>";
            // Query top 10 produtos
            $sqlTop = "
              SELECT
                oi.product_name AS nome,
                SUM(oi.quantity) AS qtdVendida,
                SUM(oi.subtotal) AS fat
              FROM order_items oi
              JOIN orders o ON oi.order_id = o.id
              WHERE DATE(o.created_at) BETWEEN :i AND :f
                AND o.status <> 'CANCELADO'
              GROUP BY oi.product_id
              ORDER BY fat DESC
              LIMIT 10
            ";
            $stmTop = $pdo->prepare($sqlTop);
            $stmTop->execute([':i'=>$d1, ':f'=>$d2]);
            $tops = $stmTop->fetchAll(PDO::FETCH_ASSOC);

            if (empty($tops)) {
              echo "<p>Nenhum produto vendido nesse período.</p>";
            } else {
              ?>
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead class="table-light">
                    <tr>
                      <th>Produto</th>
                      <th>Qtd</th>
                      <th>Faturamento (R$)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    foreach ($tops as $tp) {
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($tp['nome']) ?></td>
                        <td><?= $tp['qtdVendida'] ?></td>
                        <td><?= number_format($tp['fat'],2,',','.') ?></td>
                      </tr>
                      <?php
                    }
                    ?>
                  </tbody>
                </table>
              </div>
              <?php
            }
          } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Erro ao gerar Relatório: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
          }
        } else {
          echo "<div class='alert alert-warning'>Datas inválidas. Preencha corretamente.</div>";
        }
        ?>
      </div>
    </div>
    <?php
    break;

  // =============================
  // (E) Comparar Períodos
  // =============================
  case 'comparar':
    ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Comparar Períodos (A vs B)</h5>
      </div>
      <div class="card-body">
        <p>Compare receita, custo e lucro entre dois intervalos de datas.</p>
        <?php
        $ca1 = isset($_GET['ca1']) ? trim($_GET['ca1']) : date('Y-m-01');
        $ca2 = isset($_GET['ca2']) ? trim($_GET['ca2']) : date('Y-m-d');
        $cb1 = isset($_GET['cb1']) ? trim($_GET['cb1']) : '';
        $cb2 = isset($_GET['cb2']) ? trim($_GET['cb2']) : '';
        ?>
        <form method="GET" class="row g-3 align-items-end mb-3">
          <input type="hidden" name="page" value="financeiro">
          <input type="hidden" name="action" value="comparar">

          <div class="col-12">
            <h6>Período A</h6>
          </div>
          <div class="col-auto">
            <label for="ca1" class="form-label mb-0"><strong>De</strong></label>
            <input type="date" class="form-control form-control-sm" name="ca1" id="ca1"
                   value="<?= htmlspecialchars($ca1) ?>">
          </div>
          <div class="col-auto">
            <label for="ca2" class="form-label mb-0"><strong>Até</strong></label>
            <input type="date" class="form-control form-control-sm" name="ca2" id="ca2"
                   value="<?= htmlspecialchars($ca2) ?>">
          </div>

          <div class="col-12 mt-3">
            <h6>Período B</h6>
          </div>
          <div class="col-auto">
            <label for="cb1" class="form-label mb-0"><strong>De</strong></label>
            <input type="date" class="form-control form-control-sm" name="cb1" id="cb1"
                   value="<?= htmlspecialchars($cb1) ?>">
          </div>
          <div class="col-auto">
            <label for="cb2" class="form-label mb-0"><strong>Até</strong></label>
            <input type="date" class="form-control form-control-sm" name="cb2" id="cb2"
                   value="<?= htmlspecialchars($cb2) ?>">
          </div>

          <div class="col-12 mt-3">
            <button type="submit" class="btn btn-primary btn-sm">Comparar</button>
          </div>
        </form>

        <?php
        // Função auxiliar
        function sumar($pdo, $start, $end) {
          $sqlX = "
            SELECT
              COUNT(*) AS ped,
              COALESCE(SUM(final_value), 0) AS rev,
              COALESCE(SUM(cost_total), 0) AS cst
            FROM orders
            WHERE DATE(created_at) BETWEEN :i AND :f
              AND status <> 'CANCELADO'
          ";
          $stX = $pdo->prepare($sqlX);
          $stX->execute([':i'=>$start, ':f'=>$end]);
          $rX = $stX->fetch(PDO::FETCH_ASSOC);

          $ped = (int)($rX['ped'] ?? 0);
          $rev = (float)($rX['rev'] ?? 0);
          $cst = (float)($rX['cst'] ?? 0);
          $luc = $rev - $cst;
          return [$ped, $rev, $cst, $luc];
        }

        // Se Período B estiver preenchido, comparar
        if (
          preg_match('/^\d{4}-\d{2}-\d{2}$/',$ca1) &&
          preg_match('/^\d{4}-\d{2}-\d{2}$/',$ca2) &&
          preg_match('/^\d{4}-\d{2}-\d{2}$/',$cb1) &&
          preg_match('/^\d{4}-\d{2}-\d{2}$/',$cb2)
        ) {
          try {
            list($pedA, $revA, $cstA, $lucA) = sumar($pdo, $ca1, $ca2);
            list($pedB, $revB, $cstB, $lucB) = sumar($pdo, $cb1, $cb2);

            ?>
            <div class="border p-3 mt-2" style="background:#fafafa;">
              <h6>Período A: 
                <?= date('d/m/Y',strtotime($ca1)) ?> a 
                <?= date('d/m/Y',strtotime($ca2)) ?>
              </h6>
              <p class="mb-1">
                Pedidos: <?= $pedA ?> <br>
                Receita: R$ <?= number_format($revA,2,',','.') ?><br>
                Custo:   R$ <?= number_format($cstA,2,',','.') ?><br>
                Lucro:   R$ <?= number_format($lucA,2,',','.') ?>
              </p>

              <h6>Período B: 
                <?= date('d/m/Y',strtotime($cb1)) ?> a 
                <?= date('d/m/Y',strtotime($cb2)) ?>
              </h6>
              <p class="mb-1">
                Pedidos: <?= $pedB ?> <br>
                Receita: R$ <?= number_format($revB,2,',','.') ?><br>
                Custo:   R$ <?= number_format($cstB,2,',','.') ?><br>
                Lucro:   R$ <?= number_format($lucB,2,',','.') ?>
              </p>
              <hr>
              <?php
              // Variação (A vs B)
              function varPct($valA, $valB) {
                // (A - B)/B * 100
                if ($valB===0) {
                  if ($valA===0) return '0%';
                  return '∞%';
                }
                $pct = (($valA - $valB)/$valB)*100;
                return number_format($pct,1,',','.') . '%';
              }

              $vPed = varPct($pedA, $pedB);
              $vRev = varPct($revA, $revB);
              $vLuc = varPct($lucA, $lucB);
              ?>
              <h6>Variação (A vs B)</h6>
              <p>
                Pedidos: <?= $vPed ?><br>
                Receita: <?= $vRev ?><br>
                Lucro:   <?= $vLuc ?>
              </p>
            </div>
            <?php
          } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Erro ao comparar períodos: "
                 . htmlspecialchars($e->getMessage()) . "</div>";
          }
        } else {
          echo "<div class='alert alert-warning'>Preencha as datas de ambos períodos A e B.</div>";
        }
        ?>
      </div>
    </div>
    <?php
    break;
}
// FIM SWITCH
