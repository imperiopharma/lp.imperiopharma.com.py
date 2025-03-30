<?php
/**
 * Sistema Financeiro Completo - Império Pharma
 * 
 * FUNCIONALIDADES:
 * - Dashboard financeiro com indicadores
 * - Movimentação financeira por período
 * - Caixa diário com fechamento
 * - Histórico de fechamentos
 * - Relatórios avançados
 * - Comparativo de períodos
 * - Ranking de produtos e marcas
 * - Análises mensais
 * - Gestão de despesas e receitas manuais
 * - CRUD de fechamentos
 */

// Definir action pelo GET
$action = isset($_GET['action']) ? trim($_GET['action']) : 'movimentacao';

// Verificar se existe função para formatar moeda, se não, criar
if (!function_exists('formatCurrency')) {
    function formatCurrency($value, $decimals = 2) {
        return number_format($value, $decimals, ',', '.');
    }
}

// Função para formatar datas
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Função para obter datas padrão de filtros
function getDefaultDates($period = 'month') {
    switch ($period) {
        case 'today':
            return [date('Y-m-d'), date('Y-m-d')];
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return [$yesterday, $yesterday];
        case 'week':
            return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')];
        case 'month':
        default:
            return [date('Y-m-01'), date('Y-m-d')];
        case 'year':
            return [date('Y-01-01'), date('Y-m-d')];
    }
}

// Processamento de operações via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Fechar dia
    if (isset($_POST['fechar_dia'])) {
        $hoje = date('Y-m-d');
        try {
            // Verificar se já existe fechamento para hoje
            $checkStmt = $pdo->prepare("SELECT id FROM daily_closings WHERE closing_date = ?");
            $checkStmt->execute([$hoje]);
            $existingClosing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingClosing) {
                setFlashMessage("O dia $hoje já foi fechado anteriormente.", 'warning', 'Atenção');
            } else {
                // Buscar dados de pedidos do dia
                $ordersStmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) AS total_orders,
                        COALESCE(SUM(final_value), 0) AS total_revenue,
                        COALESCE(SUM(cost_total), 0) AS total_cost
                    FROM orders
                    WHERE DATE(created_at) = ?
                      AND status <> 'CANCELADO'
                      AND closed = 0
                ");
                $ordersStmt->execute([$hoje]);
                $ordersData = $ordersStmt->fetch(PDO::FETCH_ASSOC);
                
                // Calcular lucro
                $totalOrders = (int)$ordersData['total_orders'];
                $totalRevenue = (float)$ordersData['total_revenue'];
                $totalCost = (float)$ordersData['total_cost'];
                $totalProfit = $totalRevenue - $totalCost;
                
                // Incluir despesas e receitas manuais
                $expensesStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) AS total
                    FROM financial_transactions
                    WHERE DATE(transaction_date) = ?
                      AND type = 'expense'
                ");
                $expensesStmt->execute([$hoje]);
                $totalExpenses = (float)$expensesStmt->fetchColumn();
                
                $incomeStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) AS total
                    FROM financial_transactions
                    WHERE DATE(transaction_date) = ?
                      AND type = 'income'
                ");
                $incomeStmt->execute([$hoje]);
                $totalIncome = (float)$incomeStmt->fetchColumn();
                
                // Ajustar totais
                $totalRevenue += $totalIncome;
                $totalCost += $totalExpenses;
                $totalProfit = $totalRevenue - $totalCost;
                
                // Registrar o fechamento
                $pdo->beginTransaction();
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO daily_closings 
                        (closing_date, total_orders, total_revenue, total_cost, total_profit, created_at)
                    VALUES 
                        (?, ?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([$hoje, $totalOrders, $totalRevenue, $totalCost, $totalProfit]);
                
                // Marcar pedidos como fechados
                $updateStmt = $pdo->prepare("
                    UPDATE orders 
                    SET closed = 1 
                    WHERE DATE(created_at) = ?
                      AND status <> 'CANCELADO'
                      AND closed = 0
                ");
                $updateStmt->execute([$hoje]);
                
                // Marcar transações como fechadas
                $updateTransactionsStmt = $pdo->prepare("
                    UPDATE financial_transactions 
                    SET closed = 1 
                    WHERE DATE(transaction_date) = ?
                      AND closed = 0
                ");
                $updateTransactionsStmt->execute([$hoje]);
                
                $pdo->commit();
                
                setFlashMessage("Fechamento do dia $hoje realizado com sucesso!", 'success', 'Sucesso');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao fechar dia: " . $e->getMessage(), 'danger', 'Erro');
        }
        
        // Redirecionamento
        header("Location: index.php?page=financeiro_completo&action=caixa");
        exit;
    }
    
    // Adicionar transação manual
    else if (isset($_POST['add_transaction'])) {
        $date = $_POST['transaction_date'];
        $type = $_POST['transaction_type'];
        $amount = str_replace(['.', ','], ['', '.'], $_POST['amount']);
        $description = $_POST['description'];
        $category = $_POST['category'];
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO financial_transactions 
                    (transaction_date, type, amount, description, category, created_at) 
                VALUES 
                    (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$date, $type, $amount, $description, $category]);
            
            $pdo->commit();
            setFlashMessage("Transação adicionada com sucesso!", 'success', 'Sucesso');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao adicionar transação: " . $e->getMessage(), 'danger', 'Erro');
        }
        
        // Redirecionamento
        header("Location: index.php?page=financeiro_completo&action=caixa");
        exit;
    }
    
    // Atualizar fechamento
    else if (isset($_POST['update_closing'])) {
        $id = (int)$_POST['closing_id'];
        $date = $_POST['closing_date'];
        $orders = (int)$_POST['total_orders'];
        $revenue = str_replace(['.', ','], ['', '.'], $_POST['total_revenue']);
        $cost = str_replace(['.', ','], ['', '.'], $_POST['total_cost']);
        $profit = $revenue - $cost;
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE daily_closings 
                SET closing_date = ?, 
                    total_orders = ?, 
                    total_revenue = ?, 
                    total_cost = ?, 
                    total_profit = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$date, $orders, $revenue, $cost, $profit, $id]);
            
            $pdo->commit();
            setFlashMessage("Fechamento #$id atualizado com sucesso!", 'success', 'Sucesso');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao atualizar fechamento: " . $e->getMessage(), 'danger', 'Erro');
        }
        
        // Redirecionamento
        header("Location: index.php?page=financeiro_completo&action=crud_fechamentos");
        exit;
    }
    
    // Remover fechamento
    else if (isset($_POST['delete_closing'])) {
        $id = (int)$_POST['closing_id'];
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("DELETE FROM daily_closings WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            setFlashMessage("Fechamento #$id removido com sucesso!", 'success', 'Sucesso');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao remover fechamento: " . $e->getMessage(), 'danger', 'Erro');
        }
        
        // Redirecionamento
        header("Location: index.php?page=financeiro_completo&action=crud_fechamentos");
        exit;
    }
}

// Header estilizado para o sistema financeiro
?>
<div class="bg-primary bg-gradient text-white p-4 mb-4 rounded shadow-sm">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-chart-line me-2"></i> Sistema Financeiro Completo</h2>
            <div class="d-flex">
                <a href="#" data-bs-toggle="modal" data-bs-target="#transactionModal" class="btn btn-light me-2">
                    <i class="fas fa-plus me-1"></i> Adicionar Transação
                </a>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-file-export me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" id="exportPdf"><i class="fas fa-file-pdf me-2 text-danger"></i> Exportar PDF</a></li>
                        <li><a class="dropdown-item" href="#" id="exportExcel"><i class="fas fa-file-excel me-2 text-success"></i> Exportar Excel</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navegação do sistema financeiro -->
<div class="mb-4">
    <div class="nav nav-tabs">
        <a href="index.php?page=financeiro_completo&action=movimentacao" 
           class="nav-item nav-link <?= $action === 'movimentacao' ? 'active' : '' ?>">
            <i class="fas fa-exchange-alt me-1"></i> Movimentação
        </a>
        <a href="index.php?page=financeiro_completo&action=caixa" 
           class="nav-item nav-link <?= $action === 'caixa' ? 'active' : '' ?>">
            <i class="fas fa-cash-register me-1"></i> Caixa Diário
        </a>
        <a href="index.php?page=financeiro_completo&action=fechamentos" 
           class="nav-item nav-link <?= $action === 'fechamentos' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check me-1"></i> Fechamentos
        </a>
        <a href="index.php?page=financeiro_completo&action=relatorios" 
           class="nav-item nav-link <?= $action === 'relatorios' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie me-1"></i> Relatórios
        </a>
        <a href="index.php?page=financeiro_completo&action=comparar" 
           class="nav-item nav-link <?= $action === 'comparar' ? 'active' : '' ?>">
            <i class="fas fa-balance-scale me-1"></i> Comparar
        </a>
        <a href="index.php?page=financeiro_completo&action=ranking" 
           class="nav-item nav-link <?= $action === 'ranking' ? 'active' : '' ?>">
            <i class="fas fa-trophy me-1"></i> Ranking
        </a>
        <a href="index.php?page=financeiro_completo&action=mensal" 
           class="nav-item nav-link <?= $action === 'mensal' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt me-1"></i> Mensal
        </a>
        <a href="index.php?page=financeiro_completo&action=crud_fechamentos" 
           class="nav-item nav-link <?= $action === 'crud_fechamentos' ? 'active' : '' ?>">
            <i class="fas fa-edit me-1"></i> Editar Fechamentos
        </a>
    </div>
</div>

<?php
// Tratar cada aba
switch ($action) {
    // ====================================================
    // (A) MOVIMENTAÇÃO (INTERVALO)
    // ====================================================
    case 'movimentacao':
    default:
        // Parâmetros do filtro
        list($defaultStart, $defaultEnd) = getDefaultDates('month');
        $ini = isset($_GET['ini']) ? $_GET['ini'] : $defaultStart;
        $fim = isset($_GET['fim']) ? $_GET['fim'] : $defaultEnd;
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Movimentação Financeira (Intervalo)</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end mb-3">
                    <input type="hidden" name="page" value="financeiro_completo">
                    <input type="hidden" name="action" value="movimentacao">
                    <div class="col-auto">
                        <label for="ini" class="form-label mb-0"><strong>Data Inicial</strong></label>
                        <input type="date" class="form-control form-control-sm" name="ini" id="ini"
                               value="<?= htmlspecialchars($ini) ?>">
                    </div>
                    <div class="col-auto">
                        <label for="fim" class="form-label mb-0"><strong>Data Final</strong></label>
                        <input type="date" class="form-control form-control-sm" name="fim" id="fim"
                               value="<?= htmlspecialchars($fim) ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-auto">
                        <!-- Filtros rápidos -->
                        <div class="btn-group btn-group-sm">
                            <a href="index.php?page=financeiro_completo&action=movimentacao&ini=<?= date('Y-m-d') ?>&fim=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Hoje</a>
                            <a href="index.php?page=financeiro_completo&action=movimentacao&ini=<?= date('Y-m-d', strtotime('yesterday')) ?>&fim=<?= date('Y-m-d', strtotime('yesterday')) ?>" class="btn btn-outline-secondary">Ontem</a>
                            <a href="index.php?page=financeiro_completo&action=movimentacao&ini=<?= date('Y-m-d', strtotime('monday this week')) ?>&fim=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Esta Semana</a>
                            <a href="index.php?page=financeiro_completo&action=movimentacao&ini=<?= date('Y-m-01') ?>&fim=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Este Mês</a>
                            <a href="index.php?page=financeiro_completo&action=movimentacao&ini=<?= date('Y-01-01') ?>&fim=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Este Ano</a>
                        </div>
                    </div>
                </form>
                
                <?php
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
                    try {
                        // Dados de pedidos no período
                        $orderStmt = $pdo->prepare("
                            SELECT
                                COUNT(*) AS total_orders,
                                COALESCE(SUM(final_value), 0) AS total_revenue,
                                COALESCE(SUM(cost_total), 0) AS total_cost
                            FROM orders
                            WHERE DATE(created_at) BETWEEN :start AND :end
                                AND status <> 'CANCELADO'
                        ");
                        $orderStmt->execute([':start' => $ini, ':end' => $fim]);
                        $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Dados de transações manuais no período
                        $incomeStmt = $pdo->prepare("
                            SELECT COALESCE(SUM(amount), 0) AS total
                            FROM financial_transactions
                            WHERE DATE(transaction_date) BETWEEN :start AND :end
                                AND type = 'income'
                        ");
                        $incomeStmt->execute([':start' => $ini, ':end' => $fim]);
                        $additionalIncome = (float)$incomeStmt->fetchColumn();
                        
                        $expenseStmt = $pdo->prepare("
                            SELECT COALESCE(SUM(amount), 0) AS total
                            FROM financial_transactions
                            WHERE DATE(transaction_date) BETWEEN :start AND :end
                                AND type = 'expense'
                        ");
                        $expenseStmt->execute([':start' => $ini, ':end' => $fim]);
                        $additionalExpense = (float)$expenseStmt->fetchColumn();
                        
                        // Totais
                        $totalPedidos = (int)$orderData['total_orders'];
                        $totalReceita = (float)$orderData['total_revenue'] + $additionalIncome;
                        $totalCusto = (float)$orderData['total_cost'] + $additionalExpense;
                        $totalLucro = $totalReceita - $totalCusto;
                        $margemLucro = $totalReceita > 0 ? ($totalLucro / $totalReceita) * 100 : 0;
                        ?>
                        
                        <h5 class="mb-3 border-bottom pb-2">Resumo do Período</h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Pedidos</h6>
                                        <h3 class="mb-0"><?= $totalPedidos ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Receita Total</h6>
                                        <h3 class="mb-0 text-primary">R$ <?= formatCurrency($totalReceita) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Custos Totais</h6>
                                        <h3 class="mb-0 text-danger">R$ <?= formatCurrency($totalCusto) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Lucro</h6>
                                        <h3 class="mb-0 <?= $totalLucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                            R$ <?= formatCurrency($totalLucro) ?>
                                        </h3>
                                        <span class="small <?= $totalLucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                            Margem: <?= formatCurrency($margemLucro, 1) ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mb-3 border-bottom pb-2">Detalhes de Vendas</h5>
                        
                        <?php
                        // CORRIGIDO: Listar pedidos - removido o LIMIT que poderia estar limitando os resultados
                        $pedidosStmt = $pdo->prepare("
                            SELECT
                                id, customer_name, status,
                                final_value, cost_total,
                                created_at
                            FROM orders
                            WHERE DATE(created_at) BETWEEN :start AND :end
                                AND status <> 'CANCELADO'
                            ORDER BY created_at DESC
                        ");
                        $pedidosStmt->execute([':start' => $ini, ':end' => $fim]);
                        $pedidos = $pedidosStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($pedidos)) {
                            echo "<div class='alert alert-info'>Nenhum pedido encontrado no período selecionado.</div>";
                        } else {
                            ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Status</th>
                                            <th>Data/Hora</th>
                                            <th>Receita (R$)</th>
                                            <th>Custo (R$)</th>
                                            <th>Lucro (R$)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido): 
                                            $lucro = $pedido['final_value'] - $pedido['cost_total'];
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="index.php?page=pedidos&action=detail&id=<?= $pedido['id'] ?>" class="text-primary">
                                                    #<?= $pedido['id'] ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($pedido['customer_name']) ?></td>
                                            <td>
                                                <span class="badge <?= getStatusBadgeClass($pedido['status']) ?>">
                                                    <?= $pedido['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= formatDate($pedido['created_at'], 'd/m/Y H:i') ?></td>
                                            <td><?= formatCurrency($pedido['final_value']) ?></td>
                                            <td><?= formatCurrency($pedido['cost_total']) ?></td>
                                            <td class="<?= $lucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= formatCurrency($lucro) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                        }
                        
                        // Listar transações manuais
                        $transacoesStmt = $pdo->prepare("
                            SELECT *
                            FROM financial_transactions
                            WHERE DATE(transaction_date) BETWEEN :start AND :end
                            ORDER BY transaction_date DESC
                        ");
                        $transacoesStmt->execute([':start' => $ini, ':end' => $fim]);
                        $transacoes = $transacoesStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($transacoes)):
                        ?>
                        <h5 class="mt-4 mb-3 border-bottom pb-2">Transações Manuais</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Categoria</th>
                                        <th>Descrição</th>
                                        <th>Valor (R$)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transacoes as $transacao): ?>
                                    <tr>
                                        <td>#<?= $transacao['id'] ?></td>
                                        <td><?= formatDate($transacao['transaction_date']) ?></td>
                                        <td>
                                            <span class="badge <?= $transacao['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $transacao['type'] == 'income' ? 'Receita' : 'Despesa' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($transacao['category']) ?></td>
                                        <td><?= htmlspecialchars($transacao['description']) ?></td>
                                        <td class="<?= $transacao['type'] == 'income' ? 'text-success' : 'text-danger' ?>">
                                            <?= ($transacao['type'] == 'income' ? '+' : '-') ?> 
                                            R$ <?= formatCurrency($transacao['amount']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                    <?php
                    } catch (PDOException $e) {
                        echo "<div class='alert alert-danger'>Erro ao consultar dados: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                } else {
                    echo "<div class='alert alert-warning'>Por favor, selecione datas válidas.</div>";
                }
                ?>
            </div>
        </div>
        <?php
        break;
    
    // ====================================================
    // (B) CAIXA DIÁRIO
    // ====================================================
    case 'caixa':
        // Data atual
        $hoje = date('Y-m-d');
        
        // Verificar se já existe fechamento para hoje
        $fechamentoExistente = false;
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM daily_closings WHERE closing_date = ?");
            $checkStmt->execute([$hoje]);
            $fechamentoExistente = $checkStmt->fetch(PDO::FETCH_ASSOC) ? true : false;
        } catch (PDOException $e) {
            // Silenciar erro, assume que não existe
        }
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Caixa Diário (Hoje)</h5>
                
                <?php if (!$fechamentoExistente): ?>
                <form method="POST" onsubmit="return confirm('Tem certeza que deseja fechar o caixa do dia de hoje?');">
                    <button type="submit" name="fechar_dia" class="btn btn-danger">
                        <i class="fas fa-lock me-1"></i> Fechar Caixa de Hoje
                    </button>
                </form>
                <?php else: ?>
                <div class="badge bg-success p-2">
                    <i class="fas fa-check-circle me-1"></i> Caixa já fechado
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php
                try {
                    // CORRIGIDO: Buscar pedidos de hoje sem limitação 
                    $pedidosStmt = $pdo->prepare("
                        SELECT
                            id, customer_name, status,
                            final_value, cost_total,
                            created_at
                        FROM orders
                        WHERE DATE(created_at) = ?
                            AND status <> 'CANCELADO'
                        ORDER BY created_at DESC
                    ");
                    $pedidosStmt->execute([$hoje]);
                    $pedidos = $pedidosStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Buscar transações manuais de hoje
                    $transacoesStmt = $pdo->prepare("
                        SELECT *
                        FROM financial_transactions
                        WHERE DATE(transaction_date) = ?
                        ORDER BY created_at DESC
                    ");
                    $transacoesStmt->execute([$hoje]);
                    $transacoes = $transacoesStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calcular totais
                    $totalPedidos = count($pedidos);
                    $totalReceita = 0;
                    $totalCusto = 0;
                    
                    foreach ($pedidos as $pedido) {
                        $totalReceita += (float)$pedido['final_value'];
                        $totalCusto += (float)$pedido['cost_total'];
                    }
                    
                    foreach ($transacoes as $transacao) {
                        if ($transacao['type'] == 'income') {
                            $totalReceita += (float)$transacao['amount'];
                        } else {
                            $totalCusto += (float)$transacao['amount'];
                        }
                    }
                    
                    $totalLucro = $totalReceita - $totalCusto;
                    ?>
                    
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Data:</strong> <?= formatDate($hoje) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Pedidos:</strong> <?= $totalPedidos ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Status do Caixa:</strong>
                                <?php if ($fechamentoExistente): ?>
                                <span class="badge bg-success">Fechado</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Aberto</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 text-end">
                                <a href="#" data-bs-toggle="modal" data-bs-target="#transactionModal" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i> Adicionar Transação
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card h-100 bg-primary text-white">
                                <div class="card-body text-center">
                                    <h6 class="mb-2">Receita</h6>
                                    <h3 class="mb-0">R$ <?= formatCurrency($totalReceita) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 bg-danger text-white">
                                <div class="card-body text-center">
                                    <h6 class="mb-2">Despesas</h6>
                                    <h3 class="mb-0">R$ <?= formatCurrency($totalCusto) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 <?= $totalLucro >= 0 ? 'bg-success' : 'bg-dark' ?> text-white">
                                <div class="card-body text-center">
                                    <h6 class="mb-2">Lucro Líquido</h6>
                                    <h3 class="mb-0">R$ <?= formatCurrency($totalLucro) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <ul class="nav nav-tabs" id="dailyTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                                <i class="fas fa-shopping-cart me-1"></i> Pedidos <span class="badge bg-secondary"><?= count($pedidos) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                                <i class="fas fa-money-bill-wave me-1"></i> Transações Manuais <span class="badge bg-secondary"><?= count($transacoes) ?></span>
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0 rounded-bottom" id="dailyTabContent">
                        <!-- Tab Pedidos -->
                        <div class="tab-pane fade show active" id="orders" role="tabpanel">
                            <?php if (empty($pedidos)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Nenhum pedido registrado hoje.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Status</th>
                                            <th>Hora</th>
                                            <th>Receita (R$)</th>
                                            <th>Custo (R$)</th>
                                            <th>Lucro (R$)</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido):
                                            $lucro = $pedido['final_value'] - $pedido['cost_total'];
                                        ?>
                                        <tr>
                                            <td>#<?= $pedido['id'] ?></td>
                                            <td><?= htmlspecialchars($pedido['customer_name']) ?></td>
                                            <td>
                                                <span class="badge <?= getStatusBadgeClass($pedido['status']) ?>">
                                                    <?= $pedido['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= date('H:i', strtotime($pedido['created_at'])) ?></td>
                                            <td><?= formatCurrency($pedido['final_value']) ?></td>
                                            <td><?= formatCurrency($pedido['cost_total']) ?></td>
                                            <td class="<?= $lucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= formatCurrency($lucro) ?>
                                            </td>
                                            <td>
                                                <a href="index.php?page=pedidos&action=detail&id=<?= $pedido['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light fw-bold">
                                            <td colspan="4" class="text-end">Totais:</td>
                                            <td><?= formatCurrency($totalReceita) ?></td>
                                            <td><?= formatCurrency($totalCusto) ?></td>
                                            <td class="<?= $totalLucro >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($totalLucro) ?></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Tab Transações Manuais -->
                        <div class="tab-pane fade" id="transactions" role="tabpanel">
                            <?php if (empty($transacoes)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Nenhuma transação manual registrada hoje.
                                <a href="#" data-bs-toggle="modal" data-bs-target="#transactionModal" class="alert-link">Adicionar uma transação</a>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tipo</th>
                                            <th>Categoria</th>
                                            <th>Descrição</th>
                                            <th>Valor (R$)</th>
                                            <th>Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transacoes as $transacao): ?>
                                        <tr>
                                            <td>#<?= $transacao['id'] ?></td>
                                            <td>
                                                <span class="badge <?= $transacao['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $transacao['type'] == 'income' ? 'Receita' : 'Despesa' ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($transacao['category']) ?></td>
                                            <td><?= htmlspecialchars($transacao['description']) ?></td>
                                            <td class="<?= $transacao['type'] == 'income' ? 'text-success' : 'text-danger' ?>">
                                                <?= ($transacao['type'] == 'income' ? '+' : '-') ?> 
                                                R$ <?= formatCurrency($transacao['amount']) ?>
                                            </td>
                                            <td><?= date('H:i', strtotime($transacao['created_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Erro ao consultar dados: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>
        <?php
        break;
    
    // ====================================================
    // (C) FECHAMENTOS (HISTÓRICO)
    // ====================================================
    case 'fechamentos':
        // Parâmetros de filtro
        list($defaultStart, $defaultEnd) = getDefaultDates('year');
        $inicio = isset($_GET['inicio']) ? $_GET['inicio'] : $defaultStart;
        $fim = isset($_GET['fim']) ? $_GET['fim'] : $defaultEnd;
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Histórico de Fechamentos</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end mb-3">
                    <input type="hidden" name="page" value="financeiro_completo">
                    <input type="hidden" name="action" value="fechamentos">
                    <div class="col-auto">
                        <label for="inicio" class="form-label mb-0"><strong>Data Inicial</strong></label>
                        <input type="date" class="form-control form-control-sm" name="inicio" id="inicio"
                               value="<?= htmlspecialchars($inicio) ?>">
                    </div>
                    <div class="col-auto">
                        <label for="fim" class="form-label mb-0"><strong>Data Final</strong></label>
                        <input type="date" class="form-control form-control-sm" name="fim" id="fim"
                               value="<?= htmlspecialchars($fim) ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </form>
                
                <?php
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
                    try {
                        // Buscar fechamentos no período
                        $stmt = $pdo->prepare("
                            SELECT *
                            FROM daily_closings
                            WHERE closing_date BETWEEN :inicio AND :fim
                            ORDER BY closing_date DESC
                        ");
                        $stmt->execute([':inicio' => $inicio, ':fim' => $fim]);
                        $fechamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Calcular totais
                        $totalPedidos = 0;
                        $totalReceita = 0;
                        $totalCusto = 0;
                        $totalLucro = 0;
                        
                        foreach ($fechamentos as $fechamento) {
                            $totalPedidos += (int)$fechamento['total_orders'];
                            $totalReceita += (float)$fechamento['total_revenue'];
                            $totalCusto += (float)$fechamento['total_cost'];
                            $totalLucro += (float)$fechamento['total_profit'];
                        }
                        
                        if (empty($fechamentos)) {
                            echo "<div class='alert alert-info'>Nenhum fechamento encontrado no período selecionado.</div>";
                        } else {
                            ?>
                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Período:</strong> <?= formatDate($inicio) ?> até <?= formatDate($fim) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Fechamentos:</strong> <?= count($fechamentos) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Pedidos:</strong> <?= $totalPedidos ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Lucro Total:</strong> R$ <?= formatCurrency($totalLucro) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Data</th>
                                            <th>Pedidos</th>
                                            <th>Receita (R$)</th>
                                            <th>Custo (R$)</th>
                                            <th>Lucro (R$)</th>
                                            <th>Criado em</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fechamentos as $fechamento): 
                                            $lucro = $fechamento['total_profit'];
                                        ?>
                                        <tr>
                                            <td>#<?= $fechamento['id'] ?></td>
                                            <td><?= formatDate($fechamento['closing_date']) ?></td>
                                            <td><?= $fechamento['total_orders'] ?></td>
                                            <td><?= formatCurrency($fechamento['total_revenue']) ?></td>
                                            <td><?= formatCurrency($fechamento['total_cost']) ?></td>
                                            <td class="<?= $lucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= formatCurrency($lucro) ?>
                                            </td>
                                            <td><?= formatDate($fechamento['created_at'], 'd/m/Y H:i') ?></td>
                                            <td>
                                                <a href="index.php?page=financeiro_completo&action=crud_fechamentos&id=<?= $fechamento['id'] ?>" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light fw-bold">
                                            <td colspan="2" class="text-end">Totais:</td>
                                            <td><?= $totalPedidos ?></td>
                                            <td><?= formatCurrency($totalReceita) ?></td>
                                            <td><?= formatCurrency($totalCusto) ?></td>
                                            <td class="<?= $totalLucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= formatCurrency($totalLucro) ?>
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <?php
                        }
                    } catch (PDOException $e) {
                        echo "<div class='alert alert-danger'>Erro ao consultar fechamentos: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                } else {
                    echo "<div class='alert alert-warning'>Por favor, selecione datas válidas.</div>";
                }
                ?>
            </div>
        </div>
        <?php
        break;
    
    // ====================================================
    // (D) RELATÓRIOS AVANÇADOS
    // ====================================================
    case 'relatorios':
        // Parâmetros de filtro
        list($defaultStart, $defaultEnd) = getDefaultDates('month');
        $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : $defaultStart;
        $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : $defaultEnd;
        $tipoRelatorio = isset($_GET['tipo']) ? $_GET['tipo'] : 'categorias';
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Relatórios Avançados</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="page" value="financeiro_completo">
                    <input type="hidden" name="action" value="relatorios">
                    
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo de Relatório</label>
                        <select name="tipo" id="tipo" class="form-select">
                            <option value="categorias" <?= $tipoRelatorio == 'categorias' ? 'selected' : '' ?>>Vendas por Categoria</option>
                            <option value="produtos" <?= $tipoRelatorio == 'produtos' ? 'selected' : '' ?>>Top Produtos</option>
                            <option value="marcas" <?= $tipoRelatorio == 'marcas' ? 'selected' : '' ?>>Top Marcas</option>
                            <option value="clientes" <?= $tipoRelatorio == 'clientes' ? 'selected' : '' ?>>Top Clientes</option>
                            <option value="despesas" <?= $tipoRelatorio == 'despesas' ? 'selected' : '' ?>>Análise de Despesas</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" name="data_inicio" id="data_inicio"
                               value="<?= htmlspecialchars($dataInicio) ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Data Final</label>
                        <input type="date" class="form-control" name="data_fim" id="data_fim"
                               value="<?= htmlspecialchars($dataFim) ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-file-alt me-1"></i> Gerar Relatório
                        </button>
                    </div>
                </form>
                
                <?php
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
                    echo '<div class="border p-3 mb-4 bg-light rounded">';
                    echo '<h5 class="border-bottom pb-2 mb-3">Relatório: ' . ucfirst($tipoRelatorio) . '</h5>';
                    
                    try {
                        switch ($tipoRelatorio) {
                            // Relatório por Categorias
                            case 'categorias':
                                $stmt = $pdo->prepare("
                                    SELECT
                                        p.category AS categoria,
                                        COUNT(DISTINCT o.id) AS num_pedidos,
                                        SUM(oi.quantity) AS quantidade,
                                        SUM(oi.subtotal) AS receita,
                                        SUM(oi.quantity * oi.cost) AS custo,
                                        SUM(oi.subtotal - (oi.quantity * oi.cost)) AS lucro
                                    FROM order_items oi
                                    JOIN orders o ON oi.order_id = o.id
                                    LEFT JOIN products p ON oi.product_id = p.id
                                    WHERE DATE(o.created_at) BETWEEN :inicio AND :fim
                                        AND o.status <> 'CANCELADO'
                                    GROUP BY p.category
                                    ORDER BY receita DESC
                                ");
                                $stmt->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
                                $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Dados para o gráfico
                                $labelsCat = [];
                                $datasetsCat = [[], [], []]; // Receita, Custo, Lucro
                                
                                foreach ($categorias as $cat) {
                                    $nomeCat = empty($cat['categoria']) ? 'Sem Categoria' : $cat['categoria'];
                                    $labelsCat[] = $nomeCat;
                                    $datasetsCat[0][] = round($cat['receita'], 2);
                                    $datasetsCat[1][] = round($cat['custo'], 2);
                                    $datasetsCat[2][] = round($cat['lucro'], 2);
                                }
                                
                                // Mostrar gráfico e tabela
                                ?>
                                <div class="row mb-4">
                                    <div class="col-lg-8">
                                        <canvas id="chartCategorias" height="300"></canvas>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="card border-0 h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <h6 class="text-center mb-4">Distribuição por Categoria</h6>
                                                <canvas id="pieCategories" height="220"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Categoria</th>
                                                <th>Pedidos</th>
                                                <th>Qtd. Vendida</th>
                                                <th>Receita (R$)</th>
                                                <th>Custo (R$)</th>
                                                <th>Lucro (R$)</th>
                                                <th>Margem (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totPedidos = 0;
                                            $totQtd = 0;
                                            $totReceita = 0;
                                            $totCusto = 0;
                                            $totLucro = 0;
                                            
                                            foreach ($categorias as $cat): 
                                                $cat_nome = empty($cat['categoria']) ? 'Sem Categoria' : $cat['categoria'];
                                                $margem = $cat['receita'] > 0 ? ($cat['lucro'] / $cat['receita']) * 100 : 0;
                                                
                                                $totPedidos += $cat['num_pedidos'];
                                                $totQtd += $cat['quantidade'];
                                                $totReceita += $cat['receita'];
                                                $totCusto += $cat['custo'];
                                                $totLucro += $cat['lucro'];
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($cat_nome) ?></td>
                                                <td><?= $cat['num_pedidos'] ?></td>
                                                <td><?= $cat['quantidade'] ?></td>
                                                <td><?= formatCurrency($cat['receita']) ?></td>
                                                <td><?= formatCurrency($cat['custo']) ?></td>
                                                <td class="<?= $cat['lucro'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($cat['lucro']) ?>
                                                </td>
                                                <td class="<?= $margem >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($margem, 1) ?>%
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-light fw-bold">
                                            <tr>
                                                <td>TOTAL</td>
                                                <td><?= $totPedidos ?></td>
                                                <td><?= $totQtd ?></td>
                                                <td><?= formatCurrency($totReceita) ?></td>
                                                <td><?= formatCurrency($totCusto) ?></td>
                                                <td class="<?= $totLucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($totLucro) ?>
                                                </td>
                                                <td class="<?= $totReceita > 0 && ($totLucro / $totReceita) * 100 >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= $totReceita > 0 ? formatCurrency(($totLucro / $totReceita) * 100, 1) : '0,0' ?>%
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Gráfico de barras por categorias
                                    const ctxCat = document.getElementById('chartCategorias').getContext('2d');
                                    new Chart(ctxCat, {
                                        type: 'bar',
                                        data: {
                                            labels: <?= json_encode($labelsCat) ?>,
                                            datasets: [
                                                {
                                                    label: 'Receita',
                                                    data: <?= json_encode($datasetsCat[0]) ?>,
                                                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                                    borderColor: 'rgba(54, 162, 235, 1)',
                                                    borderWidth: 1
                                                },
                                                {
                                                    label: 'Custo',
                                                    data: <?= json_encode($datasetsCat[1]) ?>,
                                                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                                    borderColor: 'rgba(255, 99, 132, 1)',
                                                    borderWidth: 1
                                                },
                                                {
                                                    label: 'Lucro',
                                                    data: <?= json_encode($datasetsCat[2]) ?>,
                                                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                                                    borderColor: 'rgba(75, 192, 192, 1)',
                                                    borderWidth: 1
                                                }
                                            ]
                                        },
                                        options: {
                                            responsive: true,
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    ticks: {
                                                        callback: function(value) {
                                                            return 'R$ ' + value.toLocaleString('pt-BR', {
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 2
                                                            });
                                                        }
                                                    }
                                                }
                                            },
                                            plugins: {
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(context) {
                                                            return context.dataset.label + ': R$ ' + context.raw.toLocaleString('pt-BR', {
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 2
                                                            });
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    });
                                    
                                    // Gráfico de pizza para distribuição
                                    const ctxPie = document.getElementById('pieCategories').getContext('2d');
                                    new Chart(ctxPie, {
                                        type: 'pie',
                                        data: {
                                            labels: <?= json_encode($labelsCat) ?>,
                                            datasets: [{
                                                data: <?= json_encode($datasetsCat[0]) ?>,
                                                backgroundColor: [
                                                    'rgba(255, 99, 132, 0.7)',
                                                    'rgba(54, 162, 235, 0.7)',
                                                    'rgba(255, 206, 86, 0.7)',
                                                    'rgba(75, 192, 192, 0.7)',
                                                    'rgba(153, 102, 255, 0.7)',
                                                    'rgba(255, 159, 64, 0.7)',
                                                    'rgba(199, 199, 199, 0.7)',
                                                    'rgba(83, 102, 255, 0.7)',
                                                    'rgba(40, 159, 64, 0.7)',
                                                    'rgba(210, 99, 132, 0.7)'
                                                ],
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            plugins: {
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(context) {
                                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                            const value = context.raw;
                                                            const percentage = ((value / total) * 100).toFixed(1);
                                                            return context.label + ': R$ ' + value.toLocaleString('pt-BR', {
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 2
                                                            }) + ' (' + percentage + '%)';
                                                        }
                                                    }
                                                },
                                                legend: {
                                                    position: 'bottom'
                                                }
                                            }
                                        }
                                    });
                                });
                                </script>
                                <?php
                                break;
                                
                            // Relatório de Top Produtos
                            case 'produtos':
                                // CORRIGIDO: Removido o GROUP BY para mostrar corretamente todos os produtos
                                $stmt = $pdo->prepare("
                                    SELECT
                                        oi.product_name AS produto,
                                        oi.brand AS marca,
                                        COUNT(DISTINCT oi.order_id) AS num_pedidos,
                                        SUM(oi.quantity) AS quantidade,
                                        SUM(oi.subtotal) AS receita,
                                        SUM(oi.quantity * oi.cost) AS custo,
                                        SUM(oi.subtotal - (oi.quantity * oi.cost)) AS lucro
                                    FROM order_items oi
                                    JOIN orders o ON oi.order_id = o.id
                                    WHERE DATE(o.created_at) BETWEEN :inicio AND :fim
                                        AND o.status <> 'CANCELADO'
                                    GROUP BY oi.product_name, oi.brand
                                    ORDER BY quantidade DESC
                                    LIMIT 20
                                ");
                                $stmt->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
                                $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Dados para o gráfico
                                $labelsProd = [];
                                $datasetsProd = [];
                                
                                foreach ($produtos as $key => $prod) {
                                    if ($key < 10) { // Limitar a 10 produtos no gráfico
                                        $labelsProd[] = $prod['produto'];
                                        $datasetsProd[] = $prod['quantidade'];
                                    }
                                }
                                ?>
                                
                                <div class="row mb-4">
                                    <div class="col-lg-12">
                                        <canvas id="chartProdutos" height="250"></canvas>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Produto</th>
                                                <th>Marca</th>
                                                <th>Qtd. Vendida</th>
                                                <th>Pedidos</th>
                                                <th>Receita (R$)</th>
                                                <th>Custo (R$)</th>
                                                <th>Lucro (R$)</th>
                                                <th>Margem (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rank = 1;
                                            foreach ($produtos as $prod): 
                                                $margem = $prod['receita'] > 0 ? ($prod['lucro'] / $prod['receita']) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td><?= $rank++ ?></td>
                                                <td><?= htmlspecialchars($prod['produto']) ?></td>
                                                <td><?= htmlspecialchars($prod['marca'] ?? '-') ?></td>
                                                <td><?= $prod['quantidade'] ?></td>
                                                <td><?= $prod['num_pedidos'] ?></td>
                                                <td><?= formatCurrency($prod['receita']) ?></td>
                                                <td><?= formatCurrency($prod['custo']) ?></td>
                                                <td class="<?= $prod['lucro'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($prod['lucro']) ?>
                                                </td>
                                                <td class="<?= $margem >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($margem, 1) ?>%
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Gráfico de barras horizontais para produtos
                                    const ctxProd = document.getElementById('chartProdutos').getContext('2d');
                                    new Chart(ctxProd, {
                                        type: 'bar',
                                        data: {
                                            labels: <?= json_encode($labelsProd) ?>,
                                            datasets: [{
                                                label: 'Quantidade Vendida',
                                                data: <?= json_encode($datasetsProd) ?>,
                                                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                                                borderColor: 'rgba(75, 192, 192, 1)',
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            indexAxis: 'y',
                                            responsive: true,
                                            plugins: {
                                                legend: {
                                                    position: 'top',
                                                },
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(context) {
                                                            return context.dataset.label + ': ' + context.raw;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    });
                                });
                                </script>
                                <?php
                                break;
                                
                            // Relatório de Top Marcas
                            case 'marcas':
                                // CORRIGIDO: Implementação da visualização de marcas
                                $stmt = $pdo->prepare("
                                    SELECT
                                        b.name AS nomeMarca,
                                        COUNT(DISTINCT o.id) AS num_pedidos,
                                        SUM(oi.quantity) AS quantidade,
                                        SUM(oi.subtotal) AS receita,
                                        SUM(oi.quantity * oi.cost) AS custo,
                                        SUM(oi.subtotal - (oi.quantity * oi.cost)) AS lucro
                                    FROM order_items oi
                                    JOIN orders o ON oi.order_id = o.id
                                    LEFT JOIN products p ON oi.product_id = p.id
                                    LEFT JOIN brands b ON p.brand_id = b.id OR oi.brand = b.name
                                    WHERE DATE(o.created_at) BETWEEN :inicio AND :fim
                                        AND o.status <> 'CANCELADO'
                                    GROUP BY b.name
                                    ORDER BY quantidade DESC
                                    LIMIT 20
                                ");
                                $stmt->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
                                $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($marcas)) {
                                    // Preparar dados para gráfico
                                    $labelsMarcas = [];
                                    $datasetsMarcas = [];
                                    
                                    foreach ($marcas as $key => $marca) {
                                        if ($key < 10 && !empty($marca['nomeMarca'])) {
                                            $labelsMarcas[] = $marca['nomeMarca'];
                                            $datasetsMarcas[] = $marca['quantidade'];
                                        }
                                    }
                                    ?>
                                    
                                    <div class="row mb-4">
                                        <div class="col-lg-12">
                                            <canvas id="chartMarcas" height="250"></canvas>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Marca</th>
                                                    <th>Qtd. Vendida</th>
                                                    <th>Pedidos</th>
                                                    <th>Receita (R$)</th>
                                                    <th>Custo (R$)</th>
                                                    <th>Lucro (R$)</th>
                                                    <th>Margem (%)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $rank = 1;
                                                foreach ($marcas as $marca): 
                                                    if (empty($marca['nomeMarca'])) continue;
                                                    $margem = $marca['receita'] > 0 ? ($marca['lucro'] / $marca['receita']) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td><?= $rank++ ?></td>
                                                    <td><?= htmlspecialchars($marca['nomeMarca']) ?></td>
                                                    <td><?= $marca['quantidade'] ?></td>
                                                    <td><?= $marca['num_pedidos'] ?></td>
                                                    <td><?= formatCurrency($marca['receita']) ?></td>
                                                    <td><?= formatCurrency($marca['custo']) ?></td>
                                                    <td class="<?= $marca['lucro'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        <?= formatCurrency($marca['lucro']) ?>
                                                    </td>
                                                    <td class="<?= $margem >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        <?= formatCurrency($margem, 1) ?>%
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // Gráfico de barras para marcas
                                        const ctxMarca = document.getElementById('chartMarcas').getContext('2d');
                                        new Chart(ctxMarca, {
                                            type: 'bar',
                                            data: {
                                                labels: <?= json_encode($labelsMarcas) ?>,
                                                datasets: [{
                                                    label: 'Quantidade Vendida',
                                                    data: <?= json_encode($datasetsMarcas) ?>,
                                                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                                                    borderColor: 'rgba(153, 102, 255, 1)',
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                scales: {
                                                    y: {
                                                        beginAtZero: true
                                                    }
                                                }
                                            }
                                        });
                                    });
                                    </script>
                                    <?php
                                } else {
                                    echo "<div class='alert alert-info'>Nenhuma marca encontrada no período selecionado.</div>";
                                }
                                break;
                                
                            // Relatório de Top Clientes
                            case 'clientes':
                                // CORRIGIDO: Implementação da visualização de clientes
                                $stmt = $pdo->prepare("
                                    SELECT
                                        customer_name AS nome,
                                        COUNT(id) AS num_pedidos,
                                        SUM(final_value) AS total_gasto,
                                        SUM(cost_total) AS total_custo,
                                        SUM(final_value - cost_total) AS total_lucro
                                    FROM orders
                                    WHERE DATE(created_at) BETWEEN :inicio AND :fim
                                        AND status <> 'CANCELADO'
                                    GROUP BY customer_name
                                    ORDER BY total_gasto DESC
                                    LIMIT 20
                                ");
                                $stmt->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
                                $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($clientes)) {
                                    // Preparar dados para gráfico
                                    $labelsClientes = [];
                                    $datasetsClientes = [];
                                    
                                    foreach ($clientes as $key => $cliente) {
                                        if ($key < 10) {
                                            $labelsClientes[] = $cliente['nome'];
                                            $datasetsClientes[] = $cliente['total_gasto'];
                                        }
                                    }
                                    ?>
                                    
                                    <div class="row mb-4">
                                        <div class="col-lg-12">
                                            <canvas id="chartClientes" height="250"></canvas>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Nome do Cliente</th>
                                                    <th>Pedidos</th>
                                                    <th>Total Gasto (R$)</th>
                                                    <th>Lucro Gerado (R$)</th>
                                                    <th>Ticket Médio (R$)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $rank = 1;
                                                foreach ($clientes as $cliente):
                                                    $ticketMedio = $cliente['num_pedidos'] > 0 ? 
                                                        $cliente['total_gasto'] / $cliente['num_pedidos'] : 0;
                                                ?>
                                                <tr>
                                                    <td><?= $rank++ ?></td>
                                                    <td><?= htmlspecialchars($cliente['nome']) ?></td>
                                                    <td><?= $cliente['num_pedidos'] ?></td>
                                                    <td><?= formatCurrency($cliente['total_gasto']) ?></td>
                                                    <td class="<?= $cliente['total_lucro'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        <?= formatCurrency($cliente['total_lucro']) ?>
                                                    </td>
                                                    <td><?= formatCurrency($ticketMedio) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // Gráfico para clientes
                                        const ctxCliente = document.getElementById('chartClientes').getContext('2d');
                                        new Chart(ctxCliente, {
                                            type: 'bar',
                                            data: {
                                                labels: <?= json_encode($labelsClientes) ?>,
                                                datasets: [{
                                                    label: 'Total Gasto (R$)',
                                                    data: <?= json_encode($datasetsClientes) ?>,
                                                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                                                    borderColor: 'rgba(255, 159, 64, 1)',
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                scales: {
                                                    y: {
                                                        beginAtZero: true,
                                                        ticks: {
                                                            callback: function(value) {
                                                                return 'R$ ' + value.toLocaleString('pt-BR');
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    });
                                    </script>
                                    <?php
                                } else {
                                    echo "<div class='alert alert-info'>Nenhum cliente encontrado no período selecionado.</div>";
                                }
                                break;
                                
                            // Análise de Despesas
                            case 'despesas':
                                // CORRIGIDO: Implementação da análise de despesas
                                $stmt = $pdo->prepare("
                                    SELECT
                                        category AS categoria,
                                        COUNT(*) AS quantidade,
                                        SUM(amount) AS total
                                    FROM financial_transactions
                                    WHERE DATE(transaction_date) BETWEEN :inicio AND :fim
                                        AND type = 'expense'
                                    GROUP BY category
                                    ORDER BY total DESC
                                ");
                                $stmt->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);
                                $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($despesas)) {
                                    // Preparar dados para gráfico
                                    $labelsDespesas = [];
                                    $datasetsDespesas = [];
                                    
                                    foreach ($despesas as $despesa) {
                                        $labelsDespesas[] = $despesa['categoria'] ?: 'Sem categoria';
                                        $datasetsDespesas[] = $despesa['total'];
                                    }
                                    ?>
                                    
                                    <div class="row mb-4">
                                        <div class="col-lg-6">
                                            <canvas id="chartDespesas" height="300"></canvas>
                                        </div>
                                        <div class="col-lg-6">
                                            <canvas id="pieDespesas" height="300"></canvas>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Categoria</th>
                                                    <th>Quantidade</th>
                                                    <th>Total (R$)</th>
                                                    <th>% do Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $totalGeral = array_sum(array_column($despesas, 'total'));
                                                foreach ($despesas as $despesa):
                                                    $percentual = $totalGeral > 0 ? ($despesa['total'] / $totalGeral) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($despesa['categoria'] ?: 'Sem categoria') ?></td>
                                                    <td><?= $despesa['quantidade'] ?></td>
                                                    <td class="text-danger">R$ <?= formatCurrency($despesa['total']) ?></td>
                                                    <td><?= formatCurrency($percentual, 1) ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-light fw-bold">
                                                <tr>
                                                    <td>TOTAL</td>
                                                    <td><?= array_sum(array_column($despesas, 'quantidade')) ?></td>
                                                    <td class="text-danger">R$ <?= formatCurrency($totalGeral) ?></td>
                                                    <td>100,0%</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // Gráfico de barras para despesas
                                        const ctxDesp = document.getElementById('chartDespesas').getContext('2d');
                                        new Chart(ctxDesp, {
                                            type: 'bar',
                                            data: {
                                                labels: <?= json_encode($labelsDespesas) ?>,
                                                datasets: [{
                                                    label: 'Total por Categoria (R$)',
                                                    data: <?= json_encode($datasetsDespesas) ?>,
                                                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                                    borderColor: 'rgba(255, 99, 132, 1)',
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                scales: {
                                                    y: {
                                                        beginAtZero: true,
                                                        ticks: {
                                                            callback: function(value) {
                                                                return 'R$ ' + value.toLocaleString('pt-BR');
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                        
                                        // Gráfico de pizza para despesas
                                        const ctxPieDespesas = document.getElementById('pieDespesas').getContext('2d');
                                        new Chart(ctxPieDespesas, {
                                            type: 'pie',
                                            data: {
                                                labels: <?= json_encode($labelsDespesas) ?>,
                                                datasets: [{
                                                    data: <?= json_encode($datasetsDespesas) ?>,
                                                    backgroundColor: [
                                                        'rgba(255, 99, 132, 0.7)',
                                                        'rgba(54, 162, 235, 0.7)',
                                                        'rgba(255, 206, 86, 0.7)',
                                                        'rgba(75, 192, 192, 0.7)',
                                                        'rgba(153, 102, 255, 0.7)',
                                                        'rgba(255, 159, 64, 0.7)',
                                                        'rgba(199, 199, 199, 0.7)',
                                                        'rgba(83, 102, 255, 0.7)'
                                                    ],
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                plugins: {
                                                    legend: {
                                                        position: 'right'
                                                    },
                                                    tooltip: {
                                                        callbacks: {
                                                            label: function(context) {
                                                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                                const value = context.raw;
                                                                const percentage = ((value / total) * 100).toFixed(1);
                                                                return context.label + ': R$ ' + value.toLocaleString('pt-BR', {
                                                                    minimumFractionDigits: 2,
                                                                    maximumFractionDigits: 2
                                                                }) + ' (' + percentage + '%)';
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    });
                                    </script>
                                    <?php
                                } else {
                                    echo "<div class='alert alert-info'>Nenhuma despesa encontrada no período selecionado.</div>";
                                }
                                break;
                        }
                    } catch (PDOException $e) {
                        echo "<div class='alert alert-danger'>Erro ao gerar relatório: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                    
                    echo '</div>'; // Fim do container de relatório
                    
                } else {
                    echo "<div class='alert alert-warning'>Por favor, selecione datas válidas para gerar o relatório.</div>";
                }
                ?>
            </div>
        </div>
        <?php
        break;
        
    // ====================================================
    // (E) COMPARAR PERÍODOS
    // ====================================================
    case 'comparar':
        // Implementação básica de comparação de períodos
        list($defaultStartA, $defaultEndA) = getDefaultDates('month');
        $startDateA = isset($_GET['startA']) ? $_GET['startA'] : $defaultStartA;
        $endDateA = isset($_GET['endA']) ? $_GET['endA'] : $defaultEndA;
        
        // Período B (mês anterior por padrão)
        $defaultStartB = date('Y-m-d', strtotime('-1 month', strtotime($defaultStartA)));
        $defaultEndB = date('Y-m-d', strtotime('-1 month', strtotime($defaultEndA)));
        $startDateB = isset($_GET['startB']) ? $_GET['startB'] : $defaultStartB;
        $endDateB = isset($_GET['endB']) ? $_GET['endB'] : $defaultEndB;
        ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Comparar Períodos</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <input type="hidden" name="page" value="financeiro_completo">
                    <input type="hidden" name="action" value="comparar">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Período A</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="startA" class="form-label">Data Inicial</label>
                                                <input type="date" class="form-control" id="startA" name="startA" value="<?= htmlspecialchars($startDateA) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="endA" class="form-label">Data Final</label>
                                                <input type="date" class="form-control" id="endA" name="endA" value="<?= htmlspecialchars($endDateA) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Período B</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="startB" class="form-label">Data Inicial</label>
                                                <input type="date" class="form-control" id="startB" name="startB" value="<?= htmlspecialchars($startDateB) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="endB" class="form-label">Data Final</label>
                                                <input type="date" class="form-control" id="endB" name="endB" value="<?= htmlspecialchars($endDateB) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-bar me-1"></i> Comparar Períodos
                        </button>
                    </div>
                </form>
                
                <?php
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateA) && 
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateA) && 
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateB) && 
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateB)) {
                    
                    try {
                        // Função para buscar dados de um período
                        function getPeriodData($pdo, $start, $end) {
                            // Buscar dados de pedidos
                            $orderStmt = $pdo->prepare("
                                SELECT
                                    COUNT(*) AS total_orders,
                                    COALESCE(SUM(final_value), 0) AS total_revenue,
                                    COALESCE(SUM(cost_total), 0) AS total_cost
                                FROM orders
                                WHERE DATE(created_at) BETWEEN :start AND :end
                                    AND status <> 'CANCELADO'
                            ");
                            $orderStmt->execute([':start' => $start, ':end' => $end]);
                            $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Dados de transações manuais
                            $incomeStmt = $pdo->prepare("
                                SELECT COALESCE(SUM(amount), 0) AS total
                                FROM financial_transactions
                                WHERE DATE(transaction_date) BETWEEN :start AND :end
                                    AND type = 'income'
                            ");
                            $incomeStmt->execute([':start' => $start, ':end' => $end]);
                            $additionalIncome = (float)$incomeStmt->fetchColumn();
                            
                            $expenseStmt = $pdo->prepare("
                                SELECT COALESCE(SUM(amount), 0) AS total
                                FROM financial_transactions
                                WHERE DATE(transaction_date) BETWEEN :start AND :end
                                    AND type = 'expense'
                            ");
                            $expenseStmt->execute([':start' => $start, ':end' => $end]);
                            $additionalExpense = (float)$expenseStmt->fetchColumn();
                            
                            // Calcular dias do período
                            $days = (strtotime($end) - strtotime($start)) / (60 * 60 * 24) + 1;
                            
                            // Totais
                            $totalPedidos = (int)$orderData['total_orders'];
                            $totalReceita = (float)$orderData['total_revenue'] + $additionalIncome;
                            $totalCusto = (float)$orderData['total_cost'] + $additionalExpense;
                            $totalLucro = $totalReceita - $totalCusto;
                            $margemLucro = $totalReceita > 0 ? ($totalLucro / $totalReceita) * 100 : 0;
                            
                            return [
                                'dias' => $days,
                                'pedidos' => $totalPedidos,
                                'receita' => $totalReceita,
                                'custo' => $totalCusto,
                                'lucro' => $totalLucro,
                                'margem' => $margemLucro,
                                'media_diaria' => $days > 0 ? $totalReceita / $days : 0
                            ];
                        }
                        
                        $dataA = getPeriodData($pdo, $startDateA, $endDateA);
                        $dataB = getPeriodData($pdo, $startDateB, $endDateB);
                        
                        // Calcular variações percentuais
                        $varPedidos = $dataB['pedidos'] > 0 ? (($dataA['pedidos'] - $dataB['pedidos']) / $dataB['pedidos']) * 100 : 0;
                        $varReceita = $dataB['receita'] > 0 ? (($dataA['receita'] - $dataB['receita']) / $dataB['receita']) * 100 : 0;
                        $varCusto = $dataB['custo'] > 0 ? (($dataA['custo'] - $dataB['custo']) / $dataB['custo']) * 100 : 0;
                        $varLucro = $dataB['lucro'] > 0 ? (($dataA['lucro'] - $dataB['lucro']) / $dataB['lucro']) * 100 : 0;
                        $varMargem = $dataB['margem'] > 0 ? (($dataA['margem'] - $dataB['margem']) / $dataB['margem']) * 100 : 0;
                        $varMedia = $dataB['media_diaria'] > 0 ? (($dataA['media_diaria'] - $dataB['media_diaria']) / $dataB['media_diaria']) * 100 : 0;
                        
                        ?>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <canvas id="compareChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Métrica</th>
                                                <th>Período A<br><small><?= formatDate($startDateA) ?> a <?= formatDate($endDateA) ?></small></th>
                                                <th>Período B<br><small><?= formatDate($startDateB) ?> a <?= formatDate($endDateB) ?></small></th>
                                                <th>Variação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Dias</td>
                                                <td><?= (int)$dataA['dias'] ?></td>
                                                <td><?= (int)$dataB['dias'] ?></td>
                                                <td>-</td>
                                            </tr>
                                            <tr>
                                                <td>Pedidos</td>
                                                <td><?= $dataA['pedidos'] ?></td>
                                                <td><?= $dataB['pedidos'] ?></td>
                                                <td class="<?= $varPedidos >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($varPedidos, 1) ?>%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Receita Total</td>
                                                <td>R$ <?= formatCurrency($dataA['receita']) ?></td>
                                                <td>R$ <?= formatCurrency($dataB['receita']) ?></td>
                                                <td class="<?= $varReceita >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($varReceita, 1) ?>%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Custo Total</td>
                                                <td>R$ <?= formatCurrency($dataA['custo']) ?></td>
                                                <td>R$ <?= formatCurrency($dataB['custo']) ?></td>
                                                <td class="<?= $varCusto <= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($varCusto, 1) ?>%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Lucro</td>
                                                <td>R$ <?= formatCurrency($dataA['lucro']) ?></td>
                                                <td>R$ <?= formatCurrency($dataB['lucro']) ?></td>
                                                <td class="<?= $varLucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($varLucro, 1) ?>%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Margem de Lucro</td>
                                                <td><?= formatCurrency($dataA['margem'], 1) ?>%</td>
                                                <td><?= formatCurrency($dataB['margem'], 1) ?>%</td>
                                                <td class="<?= $varMargem >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($varMargem, 1) ?>%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Média Diária</td>
                                                <td>R$ <?= formatCurrency($dataA['media_diaria']) ?></td>
                                                <td>R$ <?= formatCurrency($dataB['media_diaria']) ?></td>
                                                <td class="<?= $varMedia >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= formatCurrency($varMedia, 1) ?>%
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('compareChart').getContext('2d');
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: ['Pedidos', 'Receita', 'Custo', 'Lucro'],
                                    datasets: [
                                        {
                                            label: 'Período A',
                                            data: [
                                                <?= $dataA['pedidos'] ?>, 
                                                <?= $dataA['receita'] ?>, 
                                                <?= $dataA['custo'] ?>, 
                                                <?= $dataA['lucro'] ?>
                                            ],
                                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                            borderColor: 'rgba(54, 162, 235, 1)',
                                            borderWidth: 1
                                        },
                                        {
                                            label: 'Período B',
                                            data: [
                                                <?= $dataB['pedidos'] ?>, 
                                                <?= $dataB['receita'] ?>, 
                                                <?= $dataB['custo'] ?>, 
                                                <?= $dataB['lucro'] ?>
                                            ],
                                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                            borderColor: 'rgba(255, 99, 132, 1)',
                                            borderWidth: 1
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                callback: function(value) {
                                                    if (value >= 1000) {
                                                        return 'R$ ' + (value / 1000).toFixed(1) + 'k';
                                                    }
                                                    return 'R$ ' + value;
                                                }
                                            }
                                        }
                                    },
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    const index = context.dataIndex;
                                                    const value = context.raw;
                                                    
                                                    if (index === 0) { // Pedidos
                                                        return context.dataset.label + ': ' + value;
                                                    } else { // Valores monetários
                                                        return context.dataset.label + ': R$ ' + value.toLocaleString('pt-BR', {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2
                                                        });
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                        </script>
                        <?php
                    } catch (PDOException $e) {
                        echo "<div class='alert alert-danger'>Erro ao comparar períodos: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
                ?>
            </div>
        </div>
        <?php
        break;
        
    // ====================================================
    // (F) RANKING
    // ====================================================
    case 'ranking':
        // Implementação do ranking
        $periodoRanking = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
        $tipoRanking = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
        
        // Determinar datas baseado no período selecionado
        switch ($periodoRanking) {
            case 'hoje':
                $dataInicioRanking = date('Y-m-d');
                $dataFimRanking = date('Y-m-d');
                break;
            case 'semana':
                $dataInicioRanking = date('Y-m-d', strtotime('monday this week'));
                $dataFimRanking = date('Y-m-d');
                break;
            case 'mes':
                $dataInicioRanking = date('Y-m-01');
                $dataFimRanking = date('Y-m-d');
                break;
            case 'ano':
                $dataInicioRanking = date('Y-01-01');
                $dataFimRanking = date('Y-m-d');
                break;
            default:
                $dataInicioRanking = date('Y-m-01');
                $dataFimRanking = date('Y-m-d');
        }
        ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Ranking de Produtos e Marcas</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="page" value="financeiro_completo">
                    <input type="hidden" name="action" value="ranking">
                    
                    <div class="col-md-6">
                        <label for="periodo" class="form-label">Período</label>
                        <select name="periodo" id="periodo" class="form-select">
                            <option value="hoje" <?= $periodoRanking == 'hoje' ? 'selected' : '' ?>>Hoje</option>
                            <option value="semana" <?= $periodoRanking == 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                            <option value="mes" <?= $periodoRanking == 'mes' ? 'selected' : '' ?>>Este Mês</option>
                            <option value="ano" <?= $periodoRanking == 'ano' ? 'selected' : '' ?>>Este Ano</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="tipo" class="form-label">Tipo de Ranking</label>
                        <select name="tipo" id="tipo" class="form-select">
                            <option value="todos" <?= $tipoRanking == 'todos' ? 'selected' : '' ?>>Todos os Produtos</option>
                            <option value="orais" <?= $tipoRanking == 'orais' ? 'selected' : '' ?>>Produtos Orais</option>
                            <option value="injetaveis" <?= $tipoRanking == 'injetaveis' ? 'selected' : '' ?>>Produtos Injetáveis</option>
                            <option value="marcas" <?= $tipoRanking == 'marcas' ? 'selected' : '' ?>>Marcas</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-trophy me-1"></i> Gerar Ranking
                        </button>
                    </div>
                </form>
                
                <?php
                try {
                    echo '<div class="alert alert-info">';
                    echo '<strong>Período: </strong>';
                    switch ($periodoRanking) {
                        case 'hoje':
                            echo 'Hoje (' . formatDate($dataInicioRanking) . ')';
                            break;
                        case 'semana':
                            echo 'Esta semana (' . formatDate($dataInicioRanking) . ' até ' . formatDate($dataFimRanking) . ')';
                            break;
                        case 'mes':
                            echo 'Este mês (' . formatDate($dataInicioRanking) . ' até ' . formatDate($dataFimRanking) . ')';
                            break;
                        case 'ano':
                            echo 'Este ano (' . formatDate($dataInicioRanking) . ' até ' . formatDate($dataFimRanking) . ')';
                            break;
                    }
                    echo '</div>';
                    
                    // Diferente SQL para cada tipo de ranking
                    switch ($tipoRanking) {
                        case 'orais':
                            $stmt = $pdo->prepare("
                                SELECT
                                    oi.product_name AS nome,
                                    oi.brand AS marca,
                                    COUNT(DISTINCT o.id) AS num_pedidos,
                                    SUM(oi.quantity) AS quantidade,
                                    SUM(oi.subtotal) AS receita,
                                    SUM(oi.quantity * oi.cost) AS custo,
                                    SUM(oi.subtotal - (oi.quantity * oi.cost)) AS lucro
                                FROM order_items oi
                                JOIN orders o ON oi.order_id = o.id
                                JOIN products p ON oi.product_id = p.id
                                WHERE DATE(o.created_at) BETWEEN :inicio AND :fim
                                    AND o.status <> 'CANCELADO'
                                    AND p.category = 'Oral'
                                GROUP BY oi.product_name, oi.brand
                                ORDER BY quantidade DESC
                                LIMIT 20
                            ");
                            $stmt->execute([':inicio' => $dataInicioRanking, ':fim' => $dataFimRanking]);
                            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<h5 class="mb-3">Top Produtos Orais</h5>';
                            
                            if (empty($produtos)) {
                                echo "<div class='alert alert-warning'>Nenhum produto oral vendido no período selecionado.</div>";
                            } else {
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-striped table-bordered">';
                                echo '<thead class="table-light">';
                                echo '<tr>';
                                echo '<th>#</th>';
                                echo '<th>Produto</th>';
                                echo '<th>Marca</th>';
                                echo '<th>Quantidade</th>';
                                echo '<th>Receita (R$)</th>';
                                echo '<th>Lucro (R$)</th>';
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';
                                
                                $rank = 1;
                                foreach ($produtos as $produto) {
                                    echo '<tr>';
                                    echo '<td>' . $rank++ . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['nome']) . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['marca'] ?? '-') . '</td>';
                                    echo '<td>' . $produto['quantidade'] . '</td>';
                                    echo '<td>R$ ' . formatCurrency($produto['receita']) . '</td>';
                                    echo '<td class="' . ($produto['lucro'] >= 0 ? 'text-success' : 'text-danger') . '">';
                                    echo 'R$ ' . formatCurrency($produto['lucro']) . '</td>';
                                    echo '</tr>';
                                }
                                
                                echo '</tbody>';
                                echo '</table>';
                                echo '</div>';
                            }
                            break;
                        
                        case 'injetaveis':
                            $stmt = $pdo->prepare("
                                SELECT
                                    oi.product_name AS nome,
                                    oi.brand AS marca,
                                    COUNT(DISTINCT o.id) AS num_pedidos,
                                    SUM(oi.quantity) AS quantidade,
                                    SUM(oi.subtotal) AS receita,
                                    SUM(oi.quantity * oi.cost) AS custo,
                                    SUM(oi.subtotal - (oi.quantity * oi.cost)) AS lucro
                                FROM order_items oi
                                JOIN orders o ON oi.order_id = o.id
                                JOIN products p ON oi.product_id = p.id
                                WHERE DATE(o.created_at) BETWEEN :inicio AND :fim
                                    AND o.status <> 'CANCELADO'
                                    AND p.category = 'Injetavel'
                                GROUP BY oi.product_name, oi.brand
                                ORDER BY quantidade DESC
                                LIMIT 20
                            ");
                            $stmt->execute([':inicio' => $dataInicioRanking, ':fim' => $dataFimRanking]);
                            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<h5 class="mb-3">Top Produtos Injetáveis</h5>';
                            
                            if (empty($produtos)) {
                                echo "<div class='alert alert-warning'>Nenhum produto injetável vendido no período selecionado.</div>";
                            } else {
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-striped table-bordered">';
                                echo '<thead class="table-light">';
                                echo '<tr>';
                                echo '<th>#</th>';
                                echo '<th>Produto</th>';
                                echo '<th>Marca</th>';
                                echo '<th>Quantidade</th>';
                                echo '<th>Receita (R$)</th>';
                                echo '<th>Lucro (R$)</th>';
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';
                                
                                $rank = 1;
                                foreach ($produtos as $produto) {
                                    echo '<tr>';
                                    echo '<td>' . $rank++ . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['nome']) . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['marca'] ?? '-') . '</td>';
                                    echo '<td>' . $produto['quantidade'] . '</td>';
                                    echo '<td>R$ ' . formatCurrency($produto['receita']) . '</td>';
                                    echo '<td class="' . ($produto['lucro'] >= 0 ? 'text-success' : 'text-danger') . '">';
                                    echo 'R$ ' . formatCurrency($produto['lucro']) . '</td>';
                                    echo '</tr>';
                                }
                                
                                echo '</tbody>';
                                echo '</table>';
                                echo '</div>';
                            }
                            break;
                        
                        case 'marcas':
                            $stmt = $pdo->prepare("
                                SELECT
                                    b.name AS nome_marca,
                                    COUNT(DISTINCT o.id) AS num_pedidos,
                                    SUM(oi.quantity) AS quantidade,
                                    SUM(oi.subtotal) AS receita,
                                    SUM(oi.quantity * oi.cost) AS custo,
                                    SUM(oi.subtotal - (oi.quantity * oi.cost)) AS lucro
                                FROM order_items oi
                                JOIN orders o ON oi.order_id = o.id
                                LEFT JOIN products p ON oi.product_id = p.id
                                LEFT JOIN brands b ON p.brand_id = b.id OR (oi.brand = b.name AND b.name IS NOT NULL)
                                WHERE DATE(o.created_at) BETWEEN :inicio AND :fim
                                    AND o.status <> 'CANCELADO'
                                    AND b.name IS NOT NULL
                                GROUP BY b.name
                                ORDER BY quantidade DESC
                                LIMIT 20
                            ");
                            $stmt->execute([':inicio' => $dataInicioRanking, ':fim' => $dataFimRanking]);
                            $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<h5 class="mb-3">Top Marcas</h5>';
                            
                            if (empty($marcas)) {
                                echo "<div class='alert alert-warning'>Nenhuma marca vendida no período selecionado.</div>";
                            } else {
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-striped table-bordered">';
                                echo '<thead class="table-light">';
                                echo '<tr>';
                                echo '<th>#</th>';
                                echo '<th>Marca</th>';
                                echo '<th>Pedidos</th>';
                                echo '<th>Quantidade</th>';
                                echo '<th>Receita (R$)</th>';
                                echo '<th>Lucro (R$)</th>';
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';
                                
                                $rank = 1;
                                foreach ($marcas as $marca) {
                                    echo '<tr>';
                                    echo '<td>' . $rank++ . '</td>';
                                    echo '<td>' . htmlspecialchars($marca['nome_marca']) . '</td>';
                                    echo '<td>' . $marca['num_pedidos'] . '</td>';
                                    echo '<td>' . $marca['quantidade'] . '</td>';
                                    echo '<td>R$ ' . formatCurrency($marca['receita']) . '</td>';
                                    echo '<td class="' . ($marca['lucro'] >= 0 ? 'text-success' : 'text-danger') . '">';
                                    echo 'R$ ' . formatCurrency($marca['lucro']) . '</td>';
                                    echo '</tr>';
                                }
                                
                                echo '</tbody>';
                                echo '</table>';
                                echo '</div>';
                            }
                            break;
                        
                        case 'todos':
                        default:
                            // CORRIGIDO: Consulta que mostra todos os produtos sem limitar
                            $stmt = $pdo->prepare("
                                SELECT
                                    oi.product_name AS nome,
                                    oi.brand AS marca,
                                    COUNT(DISTINCT o.id) AS num_pedidos,
                                    SUM(oi.quantity) AS quantidade,
                                    SUM(oi.subtotal) AS receita,
                                    SUM(oi.quantity * oi.cost) AS custo,
                                    SUM(oi.subtotal - (oi.quantity * oi.cost)) AS lucro
                                FROM order_items oi
                                JOIN orders o ON oi.order_id = o.id
                                WHERE DATE(o.created_at) BETWEEN :inicio AND :fim
                                    AND o.status <> 'CANCELADO'
                                GROUP BY oi.product_name, oi.brand
                                ORDER BY quantidade DESC
                                LIMIT 20
                            ");
                            $stmt->execute([':inicio' => $dataInicioRanking, ':fim' => $dataFimRanking]);
                            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<h5 class="mb-3">Top Produtos (Todos)</h5>';
                            
                            if (empty($produtos)) {
                                echo "<div class='alert alert-warning'>Nenhum produto vendido no período selecionado.</div>";
                            } else {
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-striped table-bordered">';
                                echo '<thead class="table-light">';
                                echo '<tr>';
                                echo '<th>#</th>';
                                echo '<th>Produto</th>';
                                echo '<th>Marca</th>';
                                echo '<th>Quantidade</th>';
                                echo '<th>Receita (R$)</th>';
                                echo '<th>Lucro (R$)</th>';
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';
                                
                                $rank = 1;
                                foreach ($produtos as $produto) {
                                    echo '<tr>';
                                    echo '<td>' . $rank++ . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['nome']) . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['marca'] ?? '-') . '</td>';
                                    echo '<td>' . $produto['quantidade'] . '</td>';
                                    echo '<td>R$ ' . formatCurrency($produto['receita']) . '</td>';
                                    echo '<td class="' . ($produto['lucro'] >= 0 ? 'text-success' : 'text-danger') . '">';
                                    echo 'R$ ' . formatCurrency($produto['lucro']) . '</td>';
                                    echo '</tr>';
                                }
                                
                                echo '</tbody>';
                                echo '</table>';
                                echo '</div>';
                            }
                            break;
                    }
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Erro ao gerar ranking: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>
        <?php
        break;
        
    // ====================================================
    // (G) MENSAL
    // ====================================================
    case 'mensal':
        $anoAtual = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
        ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Análises Mensais</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <input type="hidden" name="page" value="financeiro_completo">
                    <input type="hidden" name="action" value="mensal">
                    
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label for="ano" class="form-label">Ano</label>
                            <select name="ano" id="ano" class="form-select">
                                <?php for ($i = date('Y'); $i >= date('Y')-3; $i--): ?>
                                <option value="<?= $i ?>" <?= $i == $anoAtual ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php
                try {
                    // Buscar dados mensais para o ano selecionado
                    $dadosMensais = [];
                    
                    // Inicializar array com todos os meses zerados
                    for ($mes = 1; $mes <= 12; $mes++) {
                        $mesNome = date('F', mktime(0, 0, 0, $mes, 1, $anoAtual));
                        $dadosMensais[$mes] = [
                            'mes_nome' => $mesNome,
                            'pedidos' => 0,
                            'receita' => 0,
                            'custo' => 0,
                            'lucro' => 0
                        ];
                    }
                    
                    // Obter dados de pedidos por mês
                    $stmtPedidos = $pdo->prepare("
                        SELECT 
                            MONTH(created_at) AS mes,
                            COUNT(*) AS total_pedidos,
                            SUM(final_value) AS total_receita,
                            SUM(cost_total) AS total_custo,
                            SUM(final_value - cost_total) AS total_lucro
                        FROM orders
                        WHERE YEAR(created_at) = ?
                            AND status <> 'CANCELADO'
                        GROUP BY MONTH(created_at)
                        ORDER BY MONTH(created_at)
                    ");
                    $stmtPedidos->execute([$anoAtual]);
                    $resultadosPedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Preencher array de dados mensais com resultados reais
                    foreach ($resultadosPedidos as $resultado) {
                        $mes = (int)$resultado['mes'];
                        $dadosMensais[$mes]['pedidos'] = (int)$resultado['total_pedidos'];
                        $dadosMensais[$mes]['receita'] = (float)$resultado['total_receita'];
                        $dadosMensais[$mes]['custo'] = (float)$resultado['total_custo'];
                        $dadosMensais[$mes]['lucro'] = (float)$resultado['total_lucro'];
                    }
                    
                    // Obter dados de transações financeiras manuais por mês
                    $stmtTransacoes = $pdo->prepare("
                        SELECT 
                            MONTH(transaction_date) AS mes,
                            type,
                            SUM(amount) AS total
                        FROM financial_transactions
                        WHERE YEAR(transaction_date) = ?
                        GROUP BY MONTH(transaction_date), type
                        ORDER BY MONTH(transaction_date)
                    ");
                    $stmtTransacoes->execute([$anoAtual]);
                    $resultadosTransacoes = $stmtTransacoes->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Adicionar transações aos dados mensais
                    foreach ($resultadosTransacoes as $resultado) {
                        $mes = (int)$resultado['mes'];
                        if ($resultado['type'] == 'income') {
                            $dadosMensais[$mes]['receita'] += (float)$resultado['total'];
                            $dadosMensais[$mes]['lucro'] += (float)$resultado['total'];
                        } else { // expense
                            $dadosMensais[$mes]['custo'] += (float)$resultado['total'];
                            $dadosMensais[$mes]['lucro'] -= (float)$resultado['total'];
                        }
                    }
                    
                    // Preparar dados para o gráfico
                    $labelsGrafico = [];
                    $datasetsReceita = [];
                    $datasetsCusto = [];
                    $datasetsLucro = [];
                    
                    foreach ($dadosMensais as $mes => $dados) {
                        $labelsGrafico[] = date('M', mktime(0, 0, 0, $mes, 1, $anoAtual));
                        $datasetsReceita[] = round($dados['receita'], 2);
                        $datasetsCusto[] = round($dados['custo'], 2);
                        $datasetsLucro[] = round($dados['lucro'], 2);
                    }
                    ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <canvas id="chartMensal" height="250"></canvas>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Mês</th>
                                    <th>Pedidos</th>
                                    <th>Receita (R$)</th>
                                    <th>Custo (R$)</th>
                                    <th>Lucro (R$)</th>
                                    <th>Margem (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalAnualPedidos = 0;
                                $totalAnualReceita = 0;
                                $totalAnualCusto = 0;
                                $totalAnualLucro = 0;
                                
                                foreach ($dadosMensais as $mes => $dados): 
                                    $margem = $dados['receita'] > 0 ? ($dados['lucro'] / $dados['receita']) * 100 : 0;
                                    
                                    $totalAnualPedidos += $dados['pedidos'];
                                    $totalAnualReceita += $dados['receita'];
                                    $totalAnualCusto += $dados['custo'];
                                    $totalAnualLucro += $dados['lucro'];
                                ?>
                                <tr>
                                    <td><?= date('F', mktime(0, 0, 0, $mes, 1, $anoAtual)) ?></td>
                                    <td><?= $dados['pedidos'] ?></td>
                                    <td>R$ <?= formatCurrency($dados['receita']) ?></td>
                                    <td>R$ <?= formatCurrency($dados['custo']) ?></td>
                                    <td class="<?= $dados['lucro'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        R$ <?= formatCurrency($dados['lucro']) ?>
                                    </td>
                                    <td class="<?= $margem >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= formatCurrency($margem, 1) ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>TOTAL ANUAL</td>
                                    <td><?= $totalAnualPedidos ?></td>
                                    <td>R$ <?= formatCurrency($totalAnualReceita) ?></td>
                                    <td>R$ <?= formatCurrency($totalAnualCusto) ?></td>
                                    <td class="<?= $totalAnualLucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                        R$ <?= formatCurrency($totalAnualLucro) ?>
                                    </td>
                                    <td class="<?= $totalAnualReceita > 0 && ($totalAnualLucro / $totalAnualReceita) * 100 >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $totalAnualReceita > 0 ? formatCurrency(($totalAnualLucro / $totalAnualReceita) * 100, 1) : '0,0' ?>%
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('chartMensal').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?= json_encode($labelsGrafico) ?>,
                                datasets: [
                                    {
                                        label: 'Receita',
                                        data: <?= json_encode($datasetsReceita) ?>,
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        borderWidth: 2,
                                        fill: false,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Custo',
                                        data: <?= json_encode($datasetsCusto) ?>,
                                        borderColor: 'rgba(255, 99, 132, 1)',
                                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                        borderWidth: 2,
                                        fill: false,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Lucro',
                                        data: <?= json_encode($datasetsLucro) ?>,
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        borderWidth: 2,
                                        fill: false,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Desempenho Mensal - <?= $anoAtual ?>'
                                    },
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                label += 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                });
                                                return label;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return 'R$ ' + value.toLocaleString('pt-BR', {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0
                                                });
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    });
                    </script>
                    <?php
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Erro ao gerar relatório mensal: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>
        <?php
        break;
        
    // ====================================================
    // (H) CRUD DE FECHAMENTOS
    // ====================================================
    case 'crud_fechamentos':
        // Verificar se temos um ID específico para editar
        $fechamentoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $fechamento = null;
        
        if ($fechamentoId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM daily_closings WHERE id = ?");
                $stmt->execute([$fechamentoId]);
                $fechamento = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo "<div class='alert alert-danger'>Erro ao carregar fechamento: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= $fechamento ? 'Editar Fechamento #' . $fechamento['id'] : 'Gerenciar Fechamentos' ?></h5>
            </div>
            <div class="card-body">
                <?php if ($fechamento): ?>
                <!-- Formulário de Edição -->
                <form method="POST" action="index.php?page=financeiro_completo&action=crud_fechamentos">
                    <input type="hidden" name="update_closing" value="1">
                    <input type="hidden" name="closing_id" value="<?= $fechamento['id'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="closing_date" class="form-label">Data do Fechamento</label>
                                <input type="date" class="form-control" id="closing_date" name="closing_date" value="<?= $fechamento['closing_date'] ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="total_orders" class="form-label">Total de Pedidos</label>
                                <input type="number" class="form-control" id="total_orders" name="total_orders" value="<?= $fechamento['total_orders'] ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="total_revenue" class="form-label">Receita Total (R$)</label>
                                <input type="text" class="form-control" id="total_revenue" name="total_revenue" value="<?= formatCurrency($fechamento['total_revenue']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="total_cost" class="form-label">Custo Total (R$)</label>
                                <input type="text" class="form-control" id="total_cost" name="total_cost" value="<?= formatCurrency($fechamento['total_cost']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="total_profit" class="form-label">Lucro (R$)</label>
                                <input type="text" class="form-control" id="total_profit" disabled value="<?= formatCurrency($fechamento['total_profit']) ?>">
                                <small class="text-muted">Calculado automaticamente</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-save me-1"></i> Salvar Alterações
                            </button>
                            <a href="index.php?page=financeiro_completo&action=crud_fechamentos" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php else: ?>
                <!-- Lista de Fechamentos -->
                <div class="mb-3">
                    <h6>Gerenciar Fechamentos Diários</h6>
                    <p class="text-muted">Lista dos últimos fechamentos realizados. Você pode editar ou remover um fechamento conforme necessário.</p>
                </div>
                
                <?php
                try {
                    $stmtFechamentos = $pdo->query("
                        SELECT * FROM daily_closings 
                        ORDER BY closing_date DESC 
                        LIMIT 30
                    ");
                    $fechamentos = $stmtFechamentos->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($fechamentos)) {
                        echo "<div class='alert alert-info'>Nenhum fechamento encontrado.</div>";
                    } else {
                        ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Data</th>
                                        <th>Pedidos</th>
                                        <th>Receita (R$)</th>
                                        <th>Custo (R$)</th>
                                        <th>Lucro (R$)</th>
                                        <th>Criado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fechamentos as $f): ?>
                                    <tr>
                                        <td>#<?= $f['id'] ?></td>
                                        <td><?= formatDate($f['closing_date']) ?></td>
                                        <td><?= $f['total_orders'] ?></td>
                                        <td>R$ <?= formatCurrency($f['total_revenue']) ?></td>
                                        <td>R$ <?= formatCurrency($f['total_cost']) ?></td>
                                        <td class="<?= $f['total_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            R$ <?= formatCurrency($f['total_profit']) ?>
                                        </td>
                                        <td><?= formatDate($f['created_at'], 'd/m/Y H:i') ?></td>
                                        <td>
                                            <a href="index.php?page=financeiro_completo&action=crud_fechamentos&id=<?= $f['id'] ?>" class="btn btn-sm btn-primary me-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $f['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <!-- Modal de Confirmação -->
                                            <div class="modal fade" id="deleteModal<?= $f['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirmar Exclusão</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Tem certeza que deseja excluir o fechamento #<?= $f['id'] ?> de <?= formatDate($f['closing_date']) ?>?</p>
                                                            <p class="text-danger"><strong>Atenção:</strong> Esta ação não pode ser desfeita.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <form method="POST" action="index.php?page=financeiro_completo&action=crud_fechamentos">
                                                                <input type="hidden" name="delete_closing" value="1">
                                                                <input type="hidden" name="closing_id" value="<?= $f['id'] ?>">
                                                                <button type="submit" class="btn btn-danger">Excluir</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Erro ao listar fechamentos: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        break;
}

// Modal para adicionar transação
?>
<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Transação Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="transaction_date" class="form-label">Data da Transação</label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Transação</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="transaction_type" id="tipo_income" value="income" checked>
                            <label class="form-check-label" for="tipo_income">
                                <i class="fas fa-plus-circle text-success"></i> Receita (Entrada)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="transaction_type" id="tipo_expense" value="expense">
                            <label class="form-check-label" for="tipo_expense">
                                <i class="fas fa-minus-circle text-danger"></i> Despesa (Saída)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Valor (R$)</label>
                        <input type="text" class="form-control" id="amount" name="amount" 
                               placeholder="0,00" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Categoria</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="" disabled selected>Selecione uma categoria</option>
                            <optgroup label="Receitas" id="income_categories">
                                <option value="Vendas">Vendas</option>
                                <option value="Serviços">Serviços</option>
                                <option value="Comissões">Comissões</option>
                                <option value="Reembolsos">Reembolsos</option>
                                <option value="Outras Receitas">Outras Receitas</option>
                            </optgroup>
                            <optgroup label="Despesas" id="expense_categories">
                                <option value="Fornecedores">Fornecedores</option>
                                <option value="Aluguel">Aluguel</option>
                                <option value="Salários">Salários</option>
                                <option value="Impostos">Impostos</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Equipamentos">Equipamentos</option>
                                <option value="Manutenção">Manutenção</option>
                                <option value="Transporte">Transporte</option>
                                <option value="Outras Despesas">Outras Despesas</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Descreva a transação..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="add_transaction" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para valores monetários
    const amountInput = document.getElementById('amount');
    if (amountInput) {
        IMask(amountInput, {
            mask: Number,
            scale: 2,
            signed: false,
            thousandsSeparator: '.',
            padFractionalZeros: true,
            normalizeZeros: true,
            radix: ','
        });
    }
    
    // Máscaras para formulário de edição de fechamento
    const totalRevenueInput = document.getElementById('total_revenue');
    const totalCostInput = document.getElementById('total_cost');
    const totalProfitInput = document.getElementById('total_profit');
    
    if (totalRevenueInput && totalCostInput && totalProfitInput) {
        // Aplicar máscaras
        const maskRevenue = IMask(totalRevenueInput, {
            mask: Number,
            scale: 2,
            signed: false,
            thousandsSeparator: '.',
            padFractionalZeros: true,
            normalizeZeros: true,
            radix: ','
        });
        
        const maskCost = IMask(totalCostInput, {
            mask: Number,
            scale: 2,
            signed: false,
            thousandsSeparator: '.',
            padFractionalZeros: true,
            normalizeZeros: true,
            radix: ','
        });
        
        // Calcular lucro em tempo real
        const updateProfit = function() {
            const revenue = parseFloat(totalRevenueInput.value.replace(/\./g, '').replace(',', '.')) || 0;
            const cost = parseFloat(totalCostInput.value.replace(/\./g, '').replace(',', '.')) || 0;
            const profit = revenue - cost;
            
            totalProfitInput.value = profit.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Atualizar classe para colorir
            if (profit >= 0) {
                totalProfitInput.classList.remove('text-danger');
                totalProfitInput.classList.add('text-success');
            } else {
                totalProfitInput.classList.remove('text-success');
                totalProfitInput.classList.add('text-danger');
            }
        };
        
        totalRevenueInput.addEventListener('input', updateProfit);
        totalCostInput.addEventListener('input', updateProfit);
    }
    
    // Toggle categorias baseado no tipo de transação
    const tipoIncome = document.getElementById('tipo_income');
    const tipoExpense = document.getElementById('tipo_expense');
    const incomeCats = document.getElementById('income_categories');
    const expenseCats = document.getElementById('expense_categories');
    
    function updateCategories() {
        if (tipoIncome.checked) {
            incomeCats.style.display = '';
            expenseCats.style.display = 'none';
            document.querySelector('#income_categories option').selected = true;
        } else {
            incomeCats.style.display = 'none';
            expenseCats.style.display = '';
            document.querySelector('#expense_categories option').selected = true;
        }
    }
    
    if (tipoIncome && tipoExpense) {
        tipoIncome.addEventListener('change', updateCategories);
        tipoExpense.addEventListener('change', updateCategories);
        updateCategories(); // Inicializar
    }
});

// Função para classe de badge de status
function getStatusBadgeClass(status) {
    switch (status) {
        case 'PENDENTE': return 'bg-warning text-dark';
        case 'CONFIRMADO': return 'bg-primary';
        case 'EM PROCESSO': return 'bg-info';
        case 'CONCLUIDO': return 'bg-success';
        case 'CANCELADO': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
</script>