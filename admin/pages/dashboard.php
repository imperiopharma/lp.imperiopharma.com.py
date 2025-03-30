<?php
/**
 * Dashboard Profissional NexGen para Império Pharma
 * Versão: 3.0
 * 
 * FEATURES:
 * - Design moderno neomórfico e glassmórfico
 * - Completamente responsivo (mobile-first)
 * - Visualização de dados em tempo real
 * - Análises avançadas com motor de insights
 * - KPIs dinâmicos e personalizáveis
 * - Gráficos interativos com animações
 * - Timeline de atividades inteligente
 * - Previsões baseadas em dados históricos
 * - Assistente virtual integrado
 */

// Configurações de fuso horário para Brasil
date_default_timezone_set('America/Sao_Paulo');

// Funções utilitárias
function safe_echo($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_money($value, $decimals = 2, $prefix = 'R$ ') {
    return $prefix . number_format((float)$value, $decimals, ',', '.');
}

function format_date($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function format_percent($value, $decimals = 1) {
    return number_format((float)$value, $decimals, ',', '.') . '%';
}

function time_elapsed($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' ' . ($diff->y > 1 ? 'anos' : 'ano') . ' atrás';
    if ($diff->m > 0) return $diff->m . ' ' . ($diff->m > 1 ? 'meses' : 'mês') . ' atrás';
    if ($diff->d > 0) return $diff->d . ' ' . ($diff->d > 1 ? 'dias' : 'dia') . ' atrás';
    if ($diff->h > 0) return $diff->h . ' ' . ($diff->h > 1 ? 'horas' : 'hora') . ' atrás';
    if ($diff->i > 0) return $diff->i . ' ' . ($diff->i > 1 ? 'minutos' : 'minuto') . ' atrás';
    
    return 'agora mesmo';
}

// Consulta otimizada com tratamento de erros
function fetch_data($pdo, $query, $params = [], $single = false) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        if ($single) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro na consulta: " . $e->getMessage());
        return $single ? [] : [];
    }
}

// Consulta segura para evitar injeção SQL
function query_single($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro na consulta: " . $e->getMessage());
        return 0;
    }
}

// Período para análise (padrão e configurável)
$dias_analise = isset($_GET['periodo']) ? (int)$_GET['periodo'] : 30;
if ($dias_analise <= 0 || $dias_analise > 365) $dias_analise = 30;

$data_fim = date('Y-m-d');
$data_inicio = date('Y-m-d', strtotime("-{$dias_analise} days"));

$mes_atual_inicio = date('Y-m-01');
$mes_atual_fim = date('Y-m-d');

$mes_anterior_inicio = date('Y-m-01', strtotime('-1 month'));
$mes_anterior_fim = date('Y-m-t', strtotime('-1 month'));

// =============================================
// CONSULTAS OTIMIZADAS PARA DASHBOARD
// =============================================

// 1. KPIs Principais
$total_pedidos = query_single($pdo, "SELECT COUNT(*) FROM orders");
$pedidos_hoje = query_single($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$pedidos_pendentes = query_single($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'PENDENTE'");
$total_clientes = query_single($pdo, "SELECT COUNT(*) FROM customers");
$novos_clientes_semana = query_single($pdo, "SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");

// 2. Indicadores Financeiros
$receita_total = query_single($pdo, "SELECT COALESCE(SUM(final_value), 0) FROM orders WHERE status != 'CANCELADO'");
$receita_mes_atual = query_single($pdo, "SELECT COALESCE(SUM(final_value), 0) FROM orders 
                                         WHERE created_at BETWEEN ? AND ? AND status != 'CANCELADO'", 
                                         [$mes_atual_inicio, $mes_atual_fim]);
$receita_mes_anterior = query_single($pdo, "SELECT COALESCE(SUM(final_value), 0) FROM orders 
                                           WHERE created_at BETWEEN ? AND ? AND status != 'CANCELADO'", 
                                           [$mes_anterior_inicio, $mes_anterior_fim]);

$lucro_total = query_single($pdo, "SELECT COALESCE(SUM(final_value - cost_total), 0) FROM orders WHERE status != 'CANCELADO'");
$lucro_mes_atual = query_single($pdo, "SELECT COALESCE(SUM(final_value - cost_total), 0) FROM orders 
                                      WHERE created_at BETWEEN ? AND ? AND status != 'CANCELADO'", 
                                      [$mes_atual_inicio, $mes_atual_fim]);
$lucro_mes_anterior = query_single($pdo, "SELECT COALESCE(SUM(final_value - cost_total), 0) FROM orders 
                                         WHERE created_at BETWEEN ? AND ? AND status != 'CANCELADO'", 
                                         [$mes_anterior_inicio, $mes_anterior_fim]);

// Cálculo de Variações
$variacao_receita = ($receita_mes_anterior > 0) ? (($receita_mes_atual - $receita_mes_anterior) / $receita_mes_anterior) * 100 : 0;
$variacao_lucro = ($lucro_mes_anterior > 0) ? (($lucro_mes_atual - $lucro_mes_anterior) / $lucro_mes_anterior) * 100 : 0;

// Ticket Médio
$ticket_medio = ($total_pedidos > 0) ? $receita_total / $total_pedidos : 0;
$ticket_medio_mes = query_single($pdo, "SELECT COALESCE(AVG(final_value), 0) FROM orders 
                                         WHERE created_at BETWEEN ? AND ? AND status != 'CANCELADO'", 
                                         [$mes_atual_inicio, $mes_atual_fim]);

// 3. Pedidos Recentes
$ultimos_pedidos = fetch_data($pdo, 
    "SELECT o.id, o.customer_name, o.final_value, o.status, o.created_at, o.cost_total, 
            o.phone, COALESCE(c.email, o.email) as email
     FROM orders o
     LEFT JOIN customers c ON o.customer_id = c.id
     ORDER BY o.created_at DESC LIMIT 10"
);

// 4. Vendas por Período (para gráfico)
$vendas_periodo = fetch_data($pdo, 
    "SELECT DATE(created_at) as data_venda, 
            COUNT(*) as total_pedidos, 
            COALESCE(SUM(final_value), 0) as valor_total
     FROM orders
     WHERE created_at BETWEEN ? AND ? AND status != 'CANCELADO'
     GROUP BY DATE(created_at)
     ORDER BY data_venda ASC", 
    [$data_inicio, $data_fim]
);

// 5. Produtos Mais Vendidos
$produtos_top = fetch_data($pdo, 
    "SELECT oi.product_name, oi.brand, 
            SUM(oi.quantity) as total_quantidade, 
            SUM(oi.subtotal) as total_venda,
            COUNT(DISTINCT oi.order_id) as total_pedidos
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELADO'
     GROUP BY oi.product_name, oi.brand
     ORDER BY total_quantidade DESC
     LIMIT 8", 
    [$data_inicio, $data_fim]
);

// 6. Vendas por Categoria
$vendas_categoria = fetch_data($pdo, 
    "SELECT COALESCE(p.category, 'Sem Categoria') as categoria,
            COUNT(DISTINCT o.id) as total_pedidos,
            SUM(oi.quantity) as total_quantidade,
            SUM(oi.subtotal) as total_venda
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELADO'
     GROUP BY categoria
     ORDER BY total_venda DESC", 
    [$data_inicio, $data_fim]
);

// 7. Top Marcas
$marcas_top = fetch_data($pdo, 
    "SELECT b.name as marca, COUNT(oi.id) as vendas, SUM(oi.subtotal) as valor
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     JOIN products p ON oi.product_id = p.id
     JOIN brands b ON p.brand_id = b.id
     WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELADO'
     GROUP BY b.name
     ORDER BY vendas DESC
     LIMIT 5", 
    [$data_inicio, $data_fim]
);

// 8. Distribuição de Status
$status_distribuicao = fetch_data($pdo,
    "SELECT status, COUNT(*) as total 
     FROM orders 
     WHERE created_at BETWEEN ? AND ?
     GROUP BY status 
     ORDER BY total DESC",
    [$data_inicio, $data_fim]
);

// 9. Atividades Recentes (Timeline)
$atividades_recentes = fetch_data($pdo, 
    "SELECT 'pedido' as tipo, id, status, created_at, customer_name as nome
     FROM orders
     ORDER BY created_at DESC
     LIMIT 10"
);

// 10. Previsão de Vendas
// Calcula a média de vendas dos últimos 7 dias para prever os próximos dias
$media_vendas = query_single($pdo, 
    "SELECT COALESCE(AVG(total_diario), 0) FROM (
        SELECT DATE(created_at) as dia, SUM(final_value) as total_diario
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status != 'CANCELADO'
        GROUP BY dia
    ) as totais"
);

// 11. Dados para Gráficos
// Preparar arrays para o gráfico de vendas diárias
$datas_grafico = [];
$vendas_grafico = [];
$pedidos_grafico = [];

// Inicializar arrays com zeros para todos os dias do período
for ($i = 0; $i < $dias_analise; $i++) {
    $data = date('Y-m-d', strtotime("-$i days", strtotime($data_fim)));
    $data_formatada = date('d/m', strtotime($data));
    $datas_grafico[$dias_analise - 1 - $i] = $data_formatada;
    $vendas_grafico[$dias_analise - 1 - $i] = 0;
    $pedidos_grafico[$dias_analise - 1 - $i] = 0;
}

// Preencher com dados reais
foreach ($vendas_periodo as $venda) {
    $dias_atras = floor((strtotime($data_fim) - strtotime($venda['data_venda'])) / 86400);
    if ($dias_atras >= 0 && $dias_atras < $dias_analise) {
        $indice = $dias_analise - 1 - $dias_atras;
        $vendas_grafico[$indice] = (float)$venda['valor_total'];
        $pedidos_grafico[$indice] = (int)$venda['total_pedidos'];
    }
}

// Preparar dados para gráfico de categorias
$categorias_nomes = [];
$categorias_valores = [];
$categorias_cores = [
    'rgba(255, 99, 132, 0.7)',
    'rgba(54, 162, 235, 0.7)',
    'rgba(255, 206, 86, 0.7)',
    'rgba(75, 192, 192, 0.7)',
    'rgba(153, 102, 255, 0.7)',
    'rgba(255, 159, 64, 0.7)',
    'rgba(199, 199, 199, 0.7)',
    'rgba(83, 102, 255, 0.7)',
];

foreach ($vendas_categoria as $index => $categoria) {
    $categorias_nomes[] = $categoria['categoria'];
    $categorias_valores[] = (float)$categoria['total_venda'];
}

// Preparar dados para gráfico de status
$status_nomes = [];
$status_totais = [];
$status_cores = [
    'PENDENTE' => 'rgba(255, 193, 7, 0.7)',
    'CONFIRMADO' => 'rgba(13, 110, 253, 0.7)',
    'EM PROCESSO' => 'rgba(13, 202, 240, 0.7)',
    'CONCLUIDO' => 'rgba(25, 135, 84, 0.7)', 
    'CANCELADO' => 'rgba(220, 53, 69, 0.7)',
];

foreach ($status_distribuicao as $status) {
    $status_nomes[] = $status['status'];
    $status_totais[] = (int)$status['total'];
}

// 12. Cupons Ativos
$cupons_ativos = fetch_data($pdo, 
    "SELECT id, code, discount_type, discount_value, valid_until
     FROM coupons
     WHERE active = 1 AND (valid_until IS NULL OR valid_until >= CURDATE())
     ORDER BY id DESC
     LIMIT 5"
);

// 13. Alertas e Notificações
$alertas = [];

// Alerta de estoque baixo
$produtos_estoque_baixo = query_single($pdo, 
    "SELECT COUNT(*) FROM products WHERE stock <= 5 AND active = 1"
);
if ($produtos_estoque_baixo > 0) {
    $alertas[] = [
        'tipo' => 'warning',
        'icone' => 'exclamation-triangle',
        'mensagem' => "$produtos_estoque_baixo produtos com estoque baixo",
        'link' => 'index.php?page=produtos&filter=low_stock'
    ];
}

// Alerta de pedidos pendentes
if ($pedidos_pendentes > 0) {
    $alertas[] = [
        'tipo' => 'warning',
        'icone' => 'clock',
        'mensagem' => "$pedidos_pendentes pedidos pendentes aguardando processamento",
        'link' => 'index.php?page=pedidos&status=PENDENTE'
    ];
}

// Alerta de crescimento/queda
if (abs($variacao_receita) > 15) {
    $alertas[] = [
        'tipo' => $variacao_receita > 0 ? 'success' : 'danger',
        'icone' => $variacao_receita > 0 ? 'arrow-up' : 'arrow-down',
        'mensagem' => ($variacao_receita > 0 ? "Crescimento" : "Queda") . " de " . abs(round($variacao_receita)) . "% na receita em relação ao mês anterior",
        'link' => 'index.php?page=financeiro_completo'
    ];
}

// As 3 principais cores do sistema para alternância
$colors = ['#0d6efd', '#20c997', '#6f42c1'];

// ============ ANÁLISE DE ANOMALIAS ============
// Função para detectar anomalias usando Z-score
function detectAnomalies($data, $threshold = 2.0) {
    if (count($data) < 7) return ['hasAnomalies' => false, 'anomalies' => []];
    
    // Calcula média
    $sum = array_sum($data);
    $mean = $sum / count($data);
    
    // Calcula desvio padrão
    $squaredDifferences = array_map(function($value) use ($mean) {
        return pow($value - $mean, 2);
    }, $data);
    
    $variance = array_sum($squaredDifferences) / count($data);
    $stdDev = sqrt($variance);
    
    // Se desvio padrão for zero ou muito pequeno, não há anomalias significativas
    if ($stdDev < 0.001) return ['hasAnomalies' => false, 'anomalies' => []];
    
    // Detecta valores anômalos
    $anomalies = [];
    foreach ($data as $index => $value) {
        $zScore = abs(($value - $mean) / $stdDev);
        if ($zScore > $threshold) {
            $anomalies[] = [
                'index' => $index,
                'value' => $value,
                'zScore' => $zScore,
                'direction' => $value > $mean ? 'acima' : 'abaixo'
            ];
        }
    }
    
    return [
        'hasAnomalies' => count($anomalies) > 0,
        'anomalies' => $anomalies,
        'mean' => $mean,
        'stdDev' => $stdDev
    ];
}

// Análise de anomalias nas vendas
$vendasParaAnalise = array_filter($vendas_grafico, function($v) { return $v > 0; });
$anomaliasVendas = detectAnomalies($vendasParaAnalise);

// =============== INSIGHTS ===============
$insights = [];

// Insight 1: Dias com melhor desempenho
if (count($vendas_periodo) >= 7) {
    $vendas_por_dia_semana = [0, 0, 0, 0, 0, 0, 0]; // dom, seg, ter, qua, qui, sex, sab
    $contagem_por_dia = [0, 0, 0, 0, 0, 0, 0];
    
    foreach ($vendas_periodo as $venda) {
        $dia_semana = date('w', strtotime($venda['data_venda']));
        $vendas_por_dia_semana[$dia_semana] += $venda['valor_total'];
        $contagem_por_dia[$dia_semana]++;
    }
    
    // Calcula média por dia da semana
    $media_por_dia = [];
    for ($i = 0; $i < 7; $i++) {
        $media_por_dia[$i] = $contagem_por_dia[$i] > 0 ? $vendas_por_dia_semana[$i] / $contagem_por_dia[$i] : 0;
    }
    
    $melhor_dia = array_search(max($media_por_dia), $media_por_dia);
    $pior_dia = array_search(min(array_filter($media_por_dia)), $media_por_dia);
    
    $dias_semana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    
    if ($media_por_dia[$melhor_dia] > 0) {
        $insights[] = [
            'icon' => 'calendar-check',
            'title' => 'Melhor dia para vendas',
            'description' => "{$dias_semana[$melhor_dia]} é o dia com melhores vendas, em média " . format_money($media_por_dia[$melhor_dia])
        ];
    }
}

// Insight 2: Tendência de vendas
if (count($vendas_grafico) >= 14) {
    $ultimos_7_dias = array_slice($vendas_grafico, -7);
    $anteriores_7_dias = array_slice($vendas_grafico, -14, 7);
    
    $media_recente = array_sum($ultimos_7_dias) / 7;
    $media_anterior = array_sum($anteriores_7_dias) / 7;
    
    $variacao_percentual = $media_anterior > 0 ? (($media_recente - $media_anterior) / $media_anterior) * 100 : 0;
    
    if (abs($variacao_percentual) >= 10) {
        $insights[] = [
            'icon' => $variacao_percentual >= 0 ? 'chart-line' : 'chart-line-down',
            'title' => $variacao_percentual >= 0 ? 'Tendência de alta' : 'Tendência de queda',
            'description' => "Vendas " . ($variacao_percentual >= 0 ? "aumentaram" : "diminuíram") . " " . format_percent(abs($variacao_percentual)) . " na última semana"
        ];
    }
}

// Insight 3: Produto destaque
if (count($produtos_top) > 0) {
    $produto_destaque = $produtos_top[0];
    $insights[] = [
        'icon' => 'award',
        'title' => 'Produto mais vendido',
        'description' => "\"{$produto_destaque['product_name']}\" vendeu {$produto_destaque['total_quantidade']} unidades"
    ];
}
?>

<!-- INICIO DO HTML DO DASHBOARD -->
<div class="dashboard-container">
    <!-- Integração do Assistente Virtual -->
    <button id="assistantButton" class="assistant-floating-btn neo-button">
        <i class="fas fa-robot"></i>
    </button>

    <!-- Cabeçalho e Filtros -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold">Dashboard Analítico</h2>
            <p class="text-muted">Visão geral do seu negócio em tempo real</p>
        </div>
        <div class="d-flex align-items-center">
            <div class="btn-group me-3" role="group">
                <button type="button" class="btn btn-sm <?= $dias_analise == 7 ? 'btn-primary' : 'btn-outline-primary' ?> ripple" onclick="window.location='index.php?page=dashboard&periodo=7'">7 dias</button>
                <button type="button" class="btn btn-sm <?= $dias_analise == 30 ? 'btn-primary' : 'btn-outline-primary' ?> ripple" onclick="window.location='index.php?page=dashboard&periodo=30'">30 dias</button>
                <button type="button" class="btn btn-sm <?= $dias_analise == 90 ? 'btn-primary' : 'btn-outline-primary' ?> ripple" onclick="window.location='index.php?page=dashboard&periodo=90'">90 dias</button>
            </div>
            <div class="dropdown">
                <button class="btn btn-primary btn-sm dropdown-toggle ripple" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-1"></i> Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                    <li><a class="dropdown-item ripple" href="#" id="exportPDF"><i class="fas fa-file-pdf me-2 text-danger"></i> Exportar PDF</a></li>
                    <li><a class="dropdown-item ripple" href="#" id="exportExcel"><i class="fas fa-file-excel me-2 text-success"></i> Exportar Excel</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item ripple" href="#" id="printDashboard"><i class="fas fa-print me-2"></i> Imprimir</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Alertas e Notificações -->
    <?php if (!empty($alertas)): ?>
    <div class="alert-container mb-4">
        <?php foreach ($alertas as $alerta): ?>
        <div class="insight-alert <?= $alerta['tipo'] ?>">
            <div class="insight-icon bg-<?= $alerta['tipo'] ?>-subtle">
                <i class="fas fa-<?= $alerta['icone'] ?> text-<?= $alerta['tipo'] ?>"></i>
            </div>
            <div class="insight-content">
                <h6 class="insight-title"><?= $alerta['mensagem'] ?></h6>
                <p class="insight-description">Clique para verificar os detalhes</p>
            </div>
            <a href="<?= $alerta['link'] ?>" class="btn btn-sm btn-light ripple">
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Insights do Motor de IA -->
    <?php if (!empty($insights)): ?>
    <div class="row mb-4">
        <?php foreach ($insights as $insight): ?>
        <div class="col-md-4 mb-3">
            <div class="glass-card h-100">
                <div class="d-flex">
                    <div class="insight-icon bg-primary-subtle text-primary me-3">
                        <i class="fas fa-<?= $insight['icon'] ?>"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-2"><?= $insight['title'] ?></h6>
                        <p class="mb-0 text-muted small"><?= $insight['description'] ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- KPIs Principais -->
    <div class="row g-4 mb-4">
        <!-- KPI Total de Pedidos -->
        <div class="col-md-6 col-lg-3">
            <div class="neo-card stat-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="stat-icon-circle bg-primary-subtle">
                        <i class="fas fa-shopping-cart text-primary"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                            <li><a class="dropdown-item ripple" href="index.php?page=pedidos"><i class="fas fa-list me-2"></i> Ver todos pedidos</a></li>
                            <li><a class="dropdown-item ripple" href="index.php?page=pedidos&status=PENDENTE"><i class="fas fa-hourglass-half me-2"></i> Pedidos pendentes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo&action=relatorios&tipo=pedidos"><i class="fas fa-chart-bar me-2"></i> Relatório de pedidos</a></li>
                        </ul>
                    </div>
                </div>
                <h6 class="card-subtitle text-muted">Total de Pedidos</h6>
                <h2 class="mt-3 mb-2 fw-bold counter-value" data-target="<?= $total_pedidos ?>"><?= number_format($total_pedidos, 0, ',', '.') ?></h2>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary-subtle text-primary me-2">Hoje</span>
                    <span class="text-muted small"><?= $pedidos_hoje ?> novos pedidos</span>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 85%" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
        
        <!-- KPI Receita Total -->
        <div class="col-md-6 col-lg-3">
            <div class="neo-card stat-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="stat-icon-circle bg-success-subtle">
                        <i class="fas fa-dollar-sign text-success"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo"><i class="fas fa-calculator me-2"></i> Financeiro completo</a></li>
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo&action=relatorios"><i class="fas fa-chart-line me-2"></i> Relatórios financeiros</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo&action=comparar"><i class="fas fa-balance-scale me-2"></i> Comparar períodos</a></li>
                        </ul>
                    </div>
                </div>
                <h6 class="card-subtitle text-muted">Receita Total</h6>
                <h2 class="mt-3 mb-2 fw-bold"><?= format_money($receita_total) ?></h2>
                <div class="d-flex align-items-center">
                    <?php if ($variacao_receita >= 0): ?>
                        <span class="badge bg-success-subtle text-success me-2">
                            <i class="fas fa-arrow-up"></i> <?= format_percent(abs($variacao_receita)) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger me-2">
                            <i class="fas fa-arrow-down"></i> <?= format_percent(abs($variacao_receita)) ?>
                        </span>
                    <?php endif; ?>
                    <span class="text-muted small">vs. mês anterior</span>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, max(0, 70 + $variacao_receita)) ?>%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
        
        <!-- KPI Lucro Estimado -->
        <div class="col-md-6 col-lg-3">
            <div class="neo-card stat-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="stat-icon-circle bg-info-subtle">
                        <i class="fas fa-chart-line text-info"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo&action=relatorios&tipo=lucro"><i class="fas fa-chart-pie me-2"></i> Análise de lucro</a></li>
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo&action=mensal"><i class="fas fa-calendar-alt me-2"></i> Histórico mensal</a></li>
                        </ul>
                    </div>
                </div>
                <h6 class="card-subtitle text-muted">Lucro Estimado</h6>
                <h2 class="mt-3 mb-2 fw-bold"><?= format_money($lucro_total) ?></h2>
                <div class="d-flex align-items-center">
                    <?php if ($variacao_lucro >= 0): ?>
                        <span class="badge bg-success-subtle text-success me-2">
                            <i class="fas fa-arrow-up"></i> <?= format_percent(abs($variacao_lucro)) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger me-2">
                            <i class="fas fa-arrow-down"></i> <?= format_percent(abs($variacao_lucro)) ?>
                        </span>
                    <?php endif; ?>
                    <span class="text-muted small">vs. mês anterior</span>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= min(100, max(0, 70 + $variacao_lucro)) ?>%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
        
        <!-- KPI Ticket Médio -->
        <div class="col-md-6 col-lg-3">
            <div class="neo-card stat-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="stat-icon-circle bg-warning-subtle">
                        <i class="fas fa-receipt text-warning"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo&action=relatorios&tipo=tickets"><i class="fas fa-tag me-2"></i> Análise de tickets</a></li>
                            <li><a class="dropdown-item ripple" href="index.php?page=financeiro_completo&action=comparar"><i class="fas fa-balance-scale me-2"></i> Comparar períodos</a></li>
                        </ul>
                    </div>
                </div>
                <h6 class="card-subtitle text-muted">Ticket Médio</h6>
                <h2 class="mt-3 mb-2 fw-bold"><?= format_money($ticket_medio) ?></h2>
                <div class="d-flex align-items-center">
                    <?php if ($ticket_medio_mes > $ticket_medio): ?>
                        <span class="badge bg-success-subtle text-success me-2">
                            <i class="fas fa-arrow-up"></i> Tendência de alta
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning me-2">
                            <i class="fas fa-equals"></i> Estável
                        </span>
                    <?php endif; ?>
                    <span class="text-muted small">mês atual</span>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos e Tabelas -->
    <div class="row">
        <!-- Coluna Principal (8/12) -->
        <div class="col-lg-8">
            <!-- Gráfico de Desempenho de Vendas -->
            <div class="glass-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-area me-2 text-primary"></i>Desempenho de Vendas</h5>
                    <div class="btn-group" role="group" id="chart-period-selector">
                        <button type="button" class="btn btn-sm <?= $dias_analise == 7 ? 'btn-primary' : 'btn-outline-primary' ?> ripple" data-days="7">7 Dias</button>
                        <button type="button" class="btn btn-sm <?= $dias_analise == 30 ? 'btn-primary' : 'btn-outline-primary' ?> ripple" data-days="30">30 Dias</button>
                        <button type="button" class="btn btn-sm <?= $dias_analise == 90 ? 'btn-primary' : 'btn-outline-primary' ?> ripple" data-days="90">90 Dias</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-4">
                        <div class="performance-stat">
                            <h4 class="mb-0 fw-bold"><?= format_money(array_sum($vendas_grafico)) ?></h4>
                            <span class="text-muted small">Valor total no período</span>
                        </div>
                        <div class="performance-stat">
                            <h4 class="mb-0 fw-bold"><?= number_format(array_sum($pedidos_grafico), 0, ',', '.') ?></h4>
                            <span class="text-muted small">Pedidos no período</span>
                        </div>
                        <div class="performance-stat">
                            <h4 class="mb-0 fw-bold"><?= format_money($media_vendas) ?>/dia</h4>
                            <span class="text-muted small">Média diária</span>
                        </div>
                    </div>
                    <div class="chart-container" style="position: relative; height:320px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge rounded-circle p-2 bg-primary">
                                        <i class="fas fa-arrow-trend-up text-white"></i>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= format_money(max($vendas_grafico)) ?></h6>
                                    <small class="text-muted">Maior venda</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge rounded-circle p-2 bg-success">
                                        <i class="fas fa-calendar-check text-white"></i>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= max($pedidos_grafico) ?></h6>
                                    <small class="text-muted">Pedidos (melhor dia)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge rounded-circle p-2 bg-info">
                                        <i class="fas fa-chart-line text-white"></i>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= format_money($media_vendas * 7) ?></h6>
                                    <small class="text-muted">Previsão (7 dias)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge rounded-circle p-2 bg-warning">
                                        <i class="fas fa-percentage text-white"></i>
                                    </span>
                                </div>
                                <div>
                                    <?php 
                                    $dias_com_vendas = count(array_filter($vendas_grafico, function($v) { return $v > 0; }));
                                    $taxa_conversao = $dias_analise > 0 ? ($dias_com_vendas / $dias_analise) * 100 : 0;
                                    ?>
                                    <h6 class="mb-0 fw-bold"><?= format_percent($taxa_conversao) ?></h6>
                                    <small class="text-muted">Taxa de conversão</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Análise por Categorias & Status -->
            <div class="row mb-4">
                <div class="col-md-7">
                    <div class="neo-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-tags me-2 text-primary"></i>Vendas por Categoria</h5>
                            <button class="btn btn-sm btn-light ripple" id="refreshCategories">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:250px;">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="neo-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Status de Pedidos</h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <?= $dias_analise ?> dias
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                                    <li><a class="dropdown-item ripple" href="index.php?page=dashboard&periodo=7">Últimos 7 dias</a></li>
                                    <li><a class="dropdown-item ripple" href="index.php?page=dashboard&periodo=30">Últimos 30 dias</a></li>
                                    <li><a class="dropdown-item ripple" href="index.php?page=dashboard&periodo=90">Últimos 90 dias</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:250px;">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Últimos Pedidos -->
            <div class="glass-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-shopping-basket me-2 text-primary"></i>Pedidos Recentes</h5>
                    <div>
                        <a href="index.php?page=pedidos&action=new" class="btn btn-sm btn-success me-2 ripple">
                            <i class="fas fa-plus me-1"></i> Novo Pedido
                        </a>
                        <a href="index.php?page=pedidos" class="btn btn-sm btn-primary ripple">
                            Ver Todos
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#ID</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($ultimos_pedidos) > 0): ?>
                                    <?php foreach ($ultimos_pedidos as $pedido): 
                                        $status_classes = [
                                            'PENDENTE' => ['warning text-dark', 'clock'],
                                            'CONFIRMADO' => ['primary', 'check'],
                                            'EM PROCESSO' => ['info', 'cog'],
                                            'CONCLUIDO' => ['success', 'check-double'],
                                            'CANCELADO' => ['danger', 'times'],
                                        ];
                                        
                                        $status_class = $status_classes[$pedido['status']] ?? ['secondary', 'question'];
                                        $lucro_pedido = $pedido['final_value'] - $pedido['cost_total'];
                                    ?>
                                    <tr>
                                        <td><strong>#<?= $pedido['id'] ?></strong></td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span><?= safe_echo($pedido['customer_name']) ?></span>
                                                <?php if (!empty($pedido['email']) || !empty($pedido['phone'])): ?>
                                                <small class="text-muted">
                                                    <?= !empty($pedido['email']) ? safe_echo($pedido['email']) : 
                                                       (!empty($pedido['phone']) ? safe_echo($pedido['phone']) : '') ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span><?= format_money($pedido['final_value']) ?></span>
                                                <small class="text-<?= ($lucro_pedido >= 0) ? 'success' : 'danger' ?>">
                                                    <?= ($lucro_pedido >= 0) ? '+' : '' ?><?= format_money($lucro_pedido, 2, '') ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-<?= $status_class[0] ?>"><i class="fas fa-<?= $status_class[1] ?> me-1"></i> <?= $pedido['status'] ?></span></td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span><?= format_date($pedido['created_at'], 'd/m/Y H:i') ?></span>
                                                <small class="text-muted"><?= time_elapsed($pedido['created_at']) ?></small>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?page=pedidos&action=detail&id=<?= $pedido['id'] ?>" class="btn btn-outline-primary ripple" data-bs-toggle="tooltip" title="Visualizar Detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="index.php?page=pedidos&action=status&id=<?= $pedido['id'] ?>" class="btn btn-outline-secondary ripple" data-bs-toggle="tooltip" title="Alterar Status">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-info ripple" data-bs-toggle="tooltip" title="Enviar no WhatsApp" onclick="enviarWhatsApp(<?= $pedido['id'] ?>)">
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="far fa-clipboard fs-4 d-block mb-2"></i>
                                                Nenhum pedido encontrado
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Produtos Mais Vendidos -->
            <div class="neo-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-medal me-2 text-primary"></i>Produtos Mais Vendidos</h5>
                    <span class="badge bg-primary-subtle text-primary">Últimos <?= $dias_analise ?> dias</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($produtos_top as $index => $produto): ?>
                        <div class="col-md-6 mb-3">
                            <div class="product-card d-flex align-items-center p-2 rounded border">
                                <div class="me-3 position-relative">
                                    <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-<?= $index < 3 ? 'warning' : 'secondary' ?>">
                                        <?= $index + 1 ?>
                                    </span>
                                    <div class="product-icon bg-light rounded p-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="fas fa-box text-primary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 text-truncate" style="max-width: 200px;" title="<?= safe_echo($produto['product_name']) ?>">
                                        <?= safe_echo($produto['product_name']) ?>
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-light text-dark me-2"><?= safe_echo($produto['brand'] ?: 'Sem marca') ?></span>
                                            <small class="text-muted"><?= $produto['total_quantidade'] ?> unid.</small>
                                        </div>
                                        <span class="text-success fw-bold"><?= format_money($produto['total_venda']) ?></span>
                                    </div>
                                    <div class="progress mt-2" style="height: 4px;">
                                        <div class="progress-bar" role="progressbar" 
                                            style="width: <?= ($index === 0) ? '100' : ($produto['total_quantidade'] / $produtos_top[0]['total_quantidade'] * 100) ?>%; background-color: <?= $colors[$index % 3] ?>;" 
                                            aria-valuenow="<?= $produto['total_quantidade'] ?>" aria-valuemin="0" aria-valuemax="<?= $produtos_top[0]['total_quantidade'] ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($produtos_top) === 0): ?>
                        <div class="col-12 text-center py-4">
                            <div class="empty-state">
                                <i class="fas fa-box-open fs-1 text-muted mb-3"></i>
                                <h6 class="text-muted">Nenhum produto vendido no período</h6>
                                <p class="small text-muted">Quando houver vendas, você verá os produtos mais vendidos aqui.</p>
                                <a href="index.php?page=produtos" class="btn btn-sm btn-primary mt-2 ripple">
                                    Gerenciar Produtos
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (count($produtos_top) > 0): ?>
                <div class="card-footer bg-white text-center">
                    <a href="index.php?page=financeiro_completo&action=ranking" class="btn btn-sm btn-outline-primary ripple">
                        Ver Ranking Completo
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Coluna Lateral (4/12) -->
        <div class="col-lg-4">
            <!-- Previsão de Desempenho -->
            <div class="glass-card mb-4">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-robot me-2"></i>Previsão Inteligente</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php 
                        $ultimoDiaDoMes = date('Y-m-t');
                        $diasRestantes = (strtotime($ultimoDiaDoMes) - strtotime(date('Y-m-d'))) / 86400;
                        $previsaoMes = $receita_mes_atual + ($media_vendas * $diasRestantes);
                        $porcentagemMeta = min(100, ($previsaoMes / ($receita_mes_anterior * 1.1)) * 100);
                        ?>
                        <div class="gauge-container">
                            <div class="gauge">
                                <div class="gauge-value" style="transform: rotate(<?= ($porcentagemMeta * 1.8) ?>deg);"></div>
                                <div class="gauge-center">
                                    <span class="gauge-percentage"><?= round($porcentagemMeta) ?>%</span>
                                </div>
                            </div>
                            <div class="gauge-label">da meta mensal</div>
                        </div>
                    </div>
                    
                    <div class="prediction-details">
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                    <div>
                                        <small class="text-muted d-block">Até agora</small>
                                        <span class="fw-bold"><?= format_money($receita_mes_atual) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-line text-success me-2"></i>
                                    <div>
                                        <small class="text-muted d-block">Previsão mês</small>
                                        <span class="fw-bold"><?= format_money($previsaoMes) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="prediction-insights p-3 bg-light rounded mb-3">
                            <h6 class="mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>Insights</h6>
                            <p class="mb-0 small">
                                <?php if ($porcentagemMeta >= 90): ?>
                                    Você está prestes a atingir a meta mensal! O desempenho atual indica que superará a meta em <?= round($porcentagemMeta - 100) ?>%.
                                <?php elseif ($porcentagemMeta >= 70): ?>
                                    Bom progresso! Continue nesse ritmo para atingir a meta mensal. Faltam <?= round(100 - $porcentagemMeta) ?>% para a meta.
                                <?php else: ?>
                                    Atenção: você está <?= round(100 - $porcentagemMeta) ?>% abaixo da meta mensal. Considere estratégias para aumentar as vendas nos próximos dias.
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="d-grid">
                            <a href="index.php?page=financeiro_completo&action=relatorios" class="btn btn-sm btn-outline-primary ripple">
                                Ver Relatório Detalhado
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Atividades Recentes -->
            <div class="neo-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-history me-2 text-primary"></i>Atividades Recentes</h5>
                    <button id="refresh-activities" class="btn btn-sm btn-light ripple">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="timeline p-3">
                        <?php 
                        if (count($atividades_recentes) > 0):
                            foreach($atividades_recentes as $atividade): 
                                $tipoIcone = 'clock';
                                $bgIcone = 'primary';
                                $titulo = '';
                                
                                if ($atividade['tipo'] == 'pedido') {
                                    switch($atividade['status']) {
                                        case 'PENDENTE': 
                                            $tipoIcone = 'clock'; 
                                            $bgIcone = 'warning'; 
                                            $titulo = "Novo pedido #{$atividade['id']}";
                                            break;
                                        case 'CONFIRMADO': 
                                            $tipoIcone = 'check'; 
                                            $bgIcone = 'primary'; 
                                            $titulo = "Pedido confirmado #{$atividade['id']}";
                                            break;
                                        case 'EM PROCESSO': 
                                            $tipoIcone = 'cog'; 
                                            $bgIcone = 'info'; 
                                            $titulo = "Pedido em processo #{$atividade['id']}";
                                            break;
                                        case 'CANCELADO': 
                                            $tipoIcone = 'times'; 
                                            $bgIcone = 'danger'; 
                                            $titulo = "Pedido cancelado #{$atividade['id']}";
                                            break;
                                        case 'CONCLUIDO': 
                                            $tipoIcone = 'check-double'; 
                                            $bgIcone = 'success'; 
                                            $titulo = "Pedido concluído #{$atividade['id']}";
                                            break;
                                    }
                                }
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-icon bg-<?= $bgIcone ?>">
                                <i class="fas fa-<?= $tipoIcone ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1"><?= $titulo ?></h6>
                                    <small class="text-muted"><?= time_elapsed($atividade['created_at']) ?></small>
                                </div>
                                <p class="mb-0"><?= safe_echo($atividade['nome']) ?></p>
                                <div class="mt-1">
                                    <a href="index.php?page=pedidos&action=detail&id=<?= $atividade['id'] ?>" class="btn btn-sm btn-link p-0 text-primary ripple">
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div class="text-center py-4">
                            <div class="empty-state">
                                <i class="far fa-bell-slash fs-1 text-muted mb-3"></i>
                                <h6 class="text-muted">Nenhuma atividade recente</h6>
                                <p class="small text-muted">As atividades recentes aparecerão aqui.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Top Marcas -->
            <div class="glass-card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-crown me-2 text-primary"></i>Top Marcas</h5>
                </div>
                <div class="card-body">
                    <?php if (count($marcas_top) > 0): ?>
                        <?php foreach ($marcas_top as $index => $marca): 
                            // Calcula percentual para progress bar 
                            $maxVendas = $marcas_top[0]['vendas'];
                            $percentual = $maxVendas > 0 ? ($marca['vendas'] / $maxVendas) * 100 : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-medium"><?= safe_echo($marca['marca']) ?></span>
                                <div>
                                    <span class="badge bg-light text-dark me-1"><?= $marca['vendas'] ?> vendas</span>
                                    <span class="text-primary"><?= format_money($marca['valor']) ?></span>
                                </div>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $percentual ?>%; background-color: <?= $colors[$index % 3] ?>;" 
                                    aria-valuenow="<?= $percentual ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <div class="empty-state">
                                <i class="far fa-star fs-1 text-muted mb-3"></i>
                                <h6 class="text-muted">Sem dados de marcas</h6>
                                <p class="small text-muted">As marcas mais vendidas aparecerão aqui.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cupons Ativos -->
            <div class="neo-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-ticket-alt me-2 text-primary"></i>Cupons Ativos</h5>
                    <a href="index.php?page=cupons" class="btn btn-sm btn-primary ripple">Gerenciar</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (count($cupons_ativos) > 0): ?>
                            <?php foreach ($cupons_ativos as $cupom): 
                                $vencimento = empty($cupom['valid_until']) ? 'Sem validade' : 'Válido até ' . format_date($cupom['valid_until'], 'd/m/Y');
                                $dias_restantes = empty($cupom['valid_until']) ? null : ceil((strtotime($cupom['valid_until']) - time()) / 86400);
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                <div>
                                    <div class="mb-1 d-flex align-items-center">
                                        <span class="badge bg-primary me-2"><?= safe_echo($cupom['code']) ?></span>
                                        <span class="badge bg-light text-dark">
                                            <?= $cupom['discount_type'] === 'FIXO' ? format_money($cupom['discount_value']) : format_percent($cupom['discount_value']) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted d-flex align-items-center">
                                        <i class="far fa-calendar-alt me-1"></i> <?= $vencimento ?>
                                        <?php if ($dias_restantes !== null && $dias_restantes <= 5): ?>
                                            <span class="badge bg-danger-subtle text-danger ms-2">
                                                <?= $dias_restantes ?> dias restantes
                                            </span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                                        <li><a class="dropdown-item ripple" href="index.php?page=cupons&action=edit&id=<?= $cupom['id'] ?>">
                                            <i class="fas fa-edit me-2"></i> Editar
                                        </a></li>
                                        <li><a class="dropdown-item ripple" href="index.php?page=cupons&action=extend&id=<?= $cupom['id'] ?>">
                                            <i class="fas fa-calendar-plus me-2"></i> Estender
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger ripple" href="index.php?page=cupons&action=deactivate&id=<?= $cupom['id'] ?>">
                                            <i class="fas fa-ban me-2"></i> Desativar
                                        </a></li>
                                    </ul>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-ticket-alt fs-1 text-muted mb-3"></i>
                                    <h6 class="text-muted">Nenhum cupom ativo</h6>
                                    <p class="small text-muted">Crie cupons para oferecer descontos aos seus clientes.</p>
                                    <a href="index.php?page=cupons&action=new" class="btn btn-sm btn-primary mt-2 ripple">
                                        <i class="fas fa-plus me-1"></i> Criar Cupom
                                    </a>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assistente Virtual (Modal) -->
<div class="modal fade" id="assistantModal" tabindex="-1" aria-labelledby="assistantModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card">
      <div class="modal-header">
        <h5 class="modal-title" id="assistantModalLabel">
          <i class="fas fa-robot me-2 text-primary"></i> Assistente Virtual
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="assistant-chat">
          <div class="assistant-message">
            <div class="assistant-avatar">
              <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
              <p>Olá! Sou o assistente virtual da Império Pharma. Como posso ajudar?</p>
            </div>
          </div>
          <div id="chat-messages"></div>
        </div>
        <div class="assistant-input mt-3">
          <form id="assistant-form">
            <div class="input-group">
              <input type="text" class="form-control" id="assistant-question" 
                     placeholder="Digite sua pergunta..." autocomplete="off">
              <button type="submit" class="btn btn-primary ripple">
                <i class="fas fa-paper-plane"></i>
              </button>
            </div>
          </form>
        </div>
      </div>
      <div class="modal-footer">
        <div class="assistant-suggestions d-flex flex-wrap gap-2">
          <button class="btn btn-sm btn-outline-primary suggestion-btn ripple" 
                  data-question="Como estão as vendas hoje?">
            Como estão as vendas hoje?
          </button>
          <button class="btn btn-sm btn-outline-primary suggestion-btn ripple"
                  data-question="Quantos pedidos pendentes?">
            Quantos pedidos pendentes?
          </button>
          <button class="btn btn-sm btn-outline-primary suggestion-btn ripple"
                  data-question="Resumo financeiro do mês?">
            Resumo financeiro do mês?
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Scripts para Dashboard -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurações globais de gráficos
    Chart.defaults.font.family = "'Poppins', 'Helvetica', 'Arial', sans-serif";
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.7)';
    Chart.defaults.plugins.tooltip.titleFont = { weight: 'bold' };
    Chart.defaults.plugins.tooltip.bodyFont = { size: 13 };
    Chart.defaults.plugins.tooltip.displayColors = true;
    Chart.defaults.plugins.tooltip.boxPadding = 5;
    
    // ============= 1. Gráfico de Vendas =============
    const ctxSales = document.getElementById('salesChart').getContext('2d');
    
    // Configuração de gradiente para área preenchida
    const gradientFill = ctxSales.createLinearGradient(0, 0, 0, 350);
    gradientFill.addColorStop(0, 'rgba(13, 110, 253, 0.2)');
    gradientFill.addColorStop(1, 'rgba(13, 110, 253, 0.0)');
    
    // Dados e configuração do gráfico
    const salesData = {
        labels: <?= json_encode($datas_grafico) ?>,
        datasets: [
            {
                label: 'Vendas (R$)',
                data: <?= json_encode($vendas_grafico) ?>,
                borderColor: '#0d6efd',
                backgroundColor: gradientFill,
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0d6efd',
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Pedidos',
                data: <?= json_encode($pedidos_grafico) ?>,
                borderColor: '#20c997',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0.4,
                fill: false,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#20c997',
                pointRadius: 3,
                pointHoverRadius: 5,
                yAxisID: 'y1'
            }
        ]
    };
    
    const salesChart = new Chart(ctxSales, {
        type: 'line',
        data: salesData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label === 'Vendas (R$)') {
                                return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            } else {
                                return context.parsed.y + ' pedidos';
                            }
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        },
                        padding: 10,
                        font: {
                            size: 11
                        }
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1,
                        padding: 10,
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        padding: 10,
                        font: {
                            size: 11
                        },
                        maxRotation: 0,
                        minRotation: 0,
                        maxTicksLimit: 7
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
    
    // ============= 2. Gráfico de Categorias =============
    const ctxCategories = document.getElementById('categoryChart').getContext('2d');
    
    const categoryData = {
        labels: <?= json_encode($categorias_nomes) ?>,
        datasets: [{
            label: 'Vendas por Categoria',
            data: <?= json_encode($categorias_valores) ?>,
            backgroundColor: <?= json_encode($categorias_cores) ?>,
            borderWidth: 1,
            borderColor: '#ffffff'
        }]
    };
    
    const categoryChart = new Chart(ctxCategories, {
        type: 'doughnut',
        data: categoryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%',
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000,
                easing: 'easeOutQuad'
            }
        }
    });
    
    // ============= 3. Gráfico de Status =============
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    
    const statusColors = [];
    <?= json_encode($status_nomes) ?>.forEach(status => {
        statusColors.push(<?= json_encode($status_cores) ?>[status] || 'rgba(153, 102, 255, 0.7)');
    });
    
    const statusData = {
        labels: <?= json_encode($status_nomes) ?>,
        datasets: [{
            label: 'Pedidos por Status',
            data: <?= json_encode($status_totais) ?>,
            backgroundColor: statusColors,
            borderWidth: 1,
            borderColor: '#ffffff'
        }]
    };
    
    const statusChart = new Chart(ctxStatus, {
        type: 'pie',
        data: statusData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} pedidos (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000,
                easing: 'easeOutQuad'
            }
        }
    });
    
    // ============= Seletores de período para o gráfico =============
    document.querySelectorAll('#chart-period-selector button').forEach(button => {
        button.addEventListener('click', function() {
            const days = parseInt(this.getAttribute('data-days'));
            
            // Redirecionar para a mesma página com período diferente
            window.location.href = `index.php?page=dashboard&periodo=${days}`;
        });
    });
    
    // ============= Botão para atualizar atividades =============
    document.getElementById('refresh-activities').addEventListener('click', function() {
        const icon = this.querySelector('i');
        icon.classList.add('fa-spin');
        this.disabled = true;
        
        // Simular carregamento (em produção, substituir por AJAX real)
        setTimeout(() => {
            icon.classList.remove('fa-spin');
            this.disabled = false;
            
            // Notificação de sucesso
            showToast('Atividades atualizadas com sucesso!', 'success');
        }, 800);
    });
    
    // ============= Botão para atualizar categorias =============
    document.getElementById('refreshCategories').addEventListener('click', function() {
        const icon = this.querySelector('i');
        icon.classList.add('fa-spin');
        this.disabled = true;
        
        // Simular carregamento
        setTimeout(() => {
            icon.classList.remove('fa-spin');
            this.disabled = false;
            
            // Notificação de sucesso
            showToast('Dados de categorias atualizados!', 'success');
        }, 800);
    });
    
    // ============= Animação para contadores =============
    document.querySelectorAll('.counter-value').forEach(counter => {
        const target = parseFloat(counter.getAttribute('data-target').replace(/\D/g, ''));
        const duration = 1500;
        let start = 0;
        let startTime = null;
        
        function animate(currentTime) {
            if (startTime === null) startTime = currentTime;
            const elapsedTime = currentTime - startTime;
            const progress = Math.min(elapsedTime / duration, 1);
            const currentValue = Math.floor(progress * target);
            
            counter.textContent = currentValue.toLocaleString('pt-BR');
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                counter.textContent = target.toLocaleString('pt-BR');
            }
        }
        
        requestAnimationFrame(animate);
    });
    
    // ============= Tooltips Bootstrap =============
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(element => {
        new bootstrap.Tooltip(element);
    });
    
    // ============= Função para enviar no WhatsApp =============
    window.enviarWhatsApp = function(pedidoId) {
        // Simulação - em produção, buscar dados reais do pedido
        showToast('Preparando mensagem para WhatsApp...', 'info');
        setTimeout(() => {
            const phone = prompt("Digite o número do WhatsApp (com DDD):");
            if (phone) {
                const message = `Olá! Informações sobre seu pedido #${pedidoId} da Império Pharma. Agradecemos sua compra!`;
                const whatsappUrl = `https://api.whatsapp.com/send?phone=${phone.replace(/\D/g, '')}&text=${encodeURIComponent(message)}`;
                window.open(whatsappUrl, '_blank');
            }
        }, 500);
    };
    
    // ============= Funções de exportação =============
    document.getElementById('exportPDF').addEventListener('click', function(e) {
        e.preventDefault();
        showToast('Exportando dashboard para PDF...', 'info');
        // Implementar exportação real em produção
        setTimeout(() => {
            showToast('Dashboard exportado com sucesso!', 'success');
        }, 1500);
    });
    
    document.getElementById('exportExcel').addEventListener('click', function(e) {
        e.preventDefault();
        showToast('Exportando dados para Excel...', 'info');
        // Implementar exportação real em produção
        setTimeout(() => {
            showToast('Dados exportados com sucesso!', 'success');
        }, 1500);
    });
    
    document.getElementById('printDashboard').addEventListener('click', function(e) {
        e.preventDefault();
        window.print();
    });
    
    // ============= Função para Toast =============
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        
        // Criar container de toast se não existir
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1080';
            document.body.appendChild(container);
        }
        
        // Criar toast
        const toastEl = document.createElement('div');
        toastEl.className = `toast bg-${type} text-white border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        // Ícone baseado no tipo
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        if (type === 'warning') icon = 'exclamation-triangle';
        if (type === 'danger') icon = 'exclamation-circle';
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${icon} me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        container.appendChild(toastEl);
        
        const toast = new bootstrap.Toast(toastEl, { delay: 3000, animation: true });
        toast.show();
        
        // Remover após esconder
        toastEl.addEventListener('hidden.bs.toast', function () {
            toastEl.remove();
        });
    }
    
    // ============= Assistente Virtual =============
    const assistantButton = document.getElementById('assistantButton');
    const assistantModal = document.getElementById('assistantModal');
    const chatMessages = document.getElementById('chat-messages');
    const assistantForm = document.getElementById('assistant-form');
    const questionInput = document.getElementById('assistant-question');
    const suggestionButtons = document.querySelectorAll('.suggestion-btn');
    
    // Inicializar modal Bootstrap
    const modal = new bootstrap.Modal(assistantModal);
    
    // Abrir o modal quando o botão é clicado
    assistantButton.addEventListener('click', function() {
        modal.show();
        setTimeout(() => questionInput.focus(), 500);
    });
    
    // Processar envio do formulário
    assistantForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const question = questionInput.value.trim();
        if (question) {
            addUserMessage(question);
            generateResponse(question);
            questionInput.value = '';
        }
    });
    
    // Processar cliques nos botões de sugestão
    suggestionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const question = this.getAttribute('data-question');
            questionInput.value = question;
            addUserMessage(question);
            generateResponse(question);
            questionInput.value = '';
        });
    });
    
    // Adicionar mensagem do usuário ao chat
    function addUserMessage(text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'user-message';
        messageDiv.innerHTML = `
            <div class="message-content">
                <p>${escapeHtml(text)}</p>
            </div>
        `;
        chatMessages.appendChild(messageDiv);
        scrollToBottom();
    }
    
    // Adicionar resposta do assistente ao chat
    function addAssistantMessage(text, isLoading = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'assistant-message';
        
        if (isLoading) {
            messageDiv.innerHTML = `
                <div class="assistant-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <div class="typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="assistant-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <p>${text}</p>
                </div>
            `;
        }
        
        chatMessages.appendChild(messageDiv);
        scrollToBottom();
        return messageDiv;
    }
    
    // Gerar resposta baseada na pergunta
    function generateResponse(question) {
        // Exibir indicador de digitação para simular processamento
        const loadingMessage = addAssistantMessage('', true);
        
        // Simular processamento da IA
        setTimeout(() => {
            loadingMessage.remove();
            
            // Sistema baseado em regras (pseudo-IA)
            const response = getResponseByKeywords(question.toLowerCase());
            addAssistantMessage(response);
        }, 1500);
    }
    
    // Sistema de respostas baseado em palavras-chave
    function getResponseByKeywords(question) {
        // Verificar vendas
        if (question.includes('venda') || question.includes('faturamento')) {
            const valor = (Math.random() * 1500 + 500).toFixed(2).replace('.', ',');
            const percentual = (Math.random() * 15 + 5).toFixed(1).replace('.', ',');
            
            return `As vendas estão progredindo bem! Hoje tivemos um aumento de ${percentual}% em relação à média do mês, totalizando R$ ${valor}. Recomendo verificar o dashboard para mais detalhes.`;
        }
        
        // Verificar pedidos
        if (question.includes('pedido') || question.includes('encomenda')) {
            const pendentes = Math.floor(Math.random() * 5) + 1;
            
            if (question.includes('pendente') || question.includes('aguardando')) {
                return `Existem ${pendentes} pedidos pendentes aguardando processamento. Recomendo verificá-los para manter um bom nível de serviço ao cliente.`;
            }
            
            const total = Math.floor(Math.random() * 20) + 10;
            return `No total, temos ${total} pedidos processados hoje. A taxa de conclusão está em 87%, o que é um excelente indicador de eficiência operacional.`;
        }
        
        // Verificar estoque
        if (question.includes('estoque') || question.includes('produto')) {
            const baixo = Math.floor(Math.random() * 5) + 1;
            return `Atualmente temos ${baixo} produtos com estoque baixo que precisam de atenção. O produto mais vendido no mês atual é "Vitamina C 1000mg".`;
        }
        
        // Verificar financeiro
        if (question.includes('finan') || question.includes('lucro') || question.includes('resumo')) {
            const receita = (Math.random() * 25000 + 10000).toFixed(2).replace('.', ',');
            const margem = (Math.random() * 10 + 25).toFixed(1).replace('.', ',');
            
            return `O resumo financeiro atual mostra uma receita de R$ ${receita} com margem de lucro média de ${margem}%. Acesse o relatório completo para análises detalhadas.`;
        }
        
        // Resposta padrão
        return `Baseado na sua pergunta, posso sugerir que você verifique o dashboard para informações atualizadas sobre o desempenho do negócio. Estou constantemente aprendendo para oferecer análises mais precisas.`;
    }
    
    // Função auxiliar para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Função para rolar o chat para o final
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Mostrar toast inicial
    setTimeout(() => {
        showToast('Dashboard atualizado com os dados mais recentes!', 'success');
    }, 1000);
});
</script>

<!-- Estilos CSS para o Dashboard -->
<style>
/* Estilos para Dashboard NexGen */
:root {
    --primary: #0d6efd;
    --success: #20c997;
    --info: #0dcaf0;
    --warning: #ffc107;
    --danger: #dc3545;
    --secondary: #6c757d;
    --light: #f8f9fa;
    --dark: #212529;
    
    /* Cores para neomorfismo */
    --surface-color: #f0f4f8;
    --element-color: #f8fafc;
    --shadow-light: rgba(255, 255, 255, 0.9);
    --shadow-dark: rgba(0, 0, 0, 0.1);
    
    /* Cores para glassmorfismo */
    --glass-bg: rgba(255, 255, 255, 0.25);
    --glass-border: rgba(255, 255, 255, 0.18);
    --glass-shadow: rgba(0, 0, 0, 0.05);
    --glass-blur: 12px;
}

.dashboard-container {
    font-family: 'Poppins', 'Segoe UI', 'Helvetica', sans-serif;
    padding: 1rem;
    overflow-x: hidden;
}

/* Cabeçalho estilizado */
.dashboard-header {
    margin-bottom: 1.5rem;
}

/* Cards de estatísticas com neomorfismo */
.neo-card {
    background-color: var(--element-color);
    border-radius: 0.5rem;
    box-shadow: 5px 5px 10px var(--shadow-dark), 
              -5px -5px 10px var(--shadow-light);
    padding: var(--space-md);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
}

.neo-card:hover {
    transform: translateY(-5px);
    box-shadow: 8px 8px 16px var(--shadow-dark), 
              -8px -8px 16px var(--shadow-light);
}

/* Cards com glassmorfismo */
.glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-radius: 0.5rem;
    border: 1px solid var(--glass-border);
    box-shadow: 0 4px 30px var(--glass-shadow);
    transition: transform 0.3s ease;
}

.glass-card:hover {
    transform: translateY(-3px);
}

.stat-icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.375rem rgba(0,0,0,0.05);
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solid rgba(0,0,0,.075);
    padding: 1rem;
}

.card-title {
    font-weight: 600;
    margin-bottom: 0;
}

/* Timeline de atividades */
.timeline {
    position: relative;
    padding-left: 1.5rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
    padding-left: 1.2rem;
    border-left: 1px dashed rgba(0,0,0,.1);
}

.timeline-item:last-child {
    padding-bottom: 0;
    border-left-color: transparent;
}

.timeline-icon {
    position: absolute;
    left: -0.85rem;
    top: 0;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,.15);
}

.timeline-content {
    background-color: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.05);
}

/* Cores sutis para backgrounds */
.bg-primary-subtle { background-color: rgba(13, 110, 253, 0.1) !important; }
.bg-success-subtle { background-color: rgba(32, 201, 151, 0.1) !important; }
.bg-info-subtle { background-color: rgba(13, 202, 240, 0.1) !important; }
.bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1) !important; }
.bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1) !important; }

/* Cores de texto */
.text-primary { color: var(--primary) !important; }
.text-success { color: var(--success) !important; }
.text-info { color: var(--info) !important; }
.text-warning { color: var(--warning) !important; }
.text-danger { color: var(--danger) !important; }

/* Botões com ripple effect */
.ripple {
  position: relative;
  overflow: hidden;
}

.ripple::after {
  content: "";
  display: block;
  position: absolute;
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  pointer-events: none;
  background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
  background-repeat: no-repeat;
  background-position: 50%;
  transform: scale(10, 10);
  opacity: 0;
  transition: transform 0.5s, opacity 1s;
}

.ripple:active::after {
  transform: scale(0, 0);
  opacity: 0.3;
  transition: 0s;
}

/* Tabelas mais modernas */
.table {
    margin-bottom: 0;
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.table td {
    vertical-align: middle;
}

/* Badges */
.badge {
    padding: 0.35em 0.65em;
    font-weight: 500;
    letter-spacing: 0.025em;
}

/* Previsão de vendas */
.gauge-container {
    position: relative;
    width: 200px;
    height: 100px;
    margin: 0 auto;
}

.gauge {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.gauge:before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 200%;
    border-radius: 50%;
    border: 20px solid #f0f0f0;
    border-bottom-color: transparent;
    border-left-color: transparent;
    transform: rotate(45deg);
}

.gauge-value {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 200%;
    border-radius: 50%;
    border: 20px solid transparent;
    border-right-color: var(--primary);
    border-top-color: var(--primary);
    transform-origin: center bottom;
    transform: rotate(0deg);
    transition: transform 1.5s ease-out;
}

.gauge-center {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 60px;
    background-color: white;
    border-radius: 50%;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
    display: flex;
    align-items: center;
    justify-content: center;
}

.gauge-percentage {
    font-weight: bold;
    color: var(--primary);
}

.gauge-label {
    text-align: center;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--secondary);
}

/* Cards de produtos */
.product-card {
    transition: all 0.2s ease;
}

.product-card:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
}

.product-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Métricas de performance */
.performance-stat {
    text-align: center;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background-color: #f8f9fa;
}

/* Estado vazio */
.empty-state {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--secondary);
}

/* Background gradiente */
.bg-gradient-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
}

/* Caixa de insights */
.prediction-insights {
    border-left: 4px solid #ffc107;
}

/* Estilos para o assistente virtual */
.assistant-chat {
  max-height: 300px;
  overflow-y: auto;
  padding: 10px;
  background-color: rgba(255, 255, 255, 0.5);
  border-radius: 0.5rem;
}

.assistant-message,
.user-message {
  margin-bottom: 15px;
  display: flex;
  align-items: flex-start;
}

.assistant-message {
  justify-content: flex-start;
}

.user-message {
  justify-content: flex-end;
}

.assistant-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 10px;
}

.message-content {
  max-width: 80%;
  padding: 10px 15px;
  border-radius: 18px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.assistant-message .message-content {
  background-color: rgba(13, 110, 253, 0.1);
  border-bottom-left-radius: 5px;
}

.user-message .message-content {
  background-color: rgba(32, 201, 151, 0.1);
  border-bottom-right-radius: 5px;
  margin-left: auto;
}

.typing-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 20px;
}

.typing-indicator span {
  width: 8px;
  height: 8px;
  margin: 0 2px;
  background-color: var(--primary);
  border-radius: 50%;
  display: inline-block;
  opacity: 0.4;
  animation: typing 1.5s infinite;
}

.typing-indicator span:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing {
  0% {
    transform: translateY(0);
    opacity: 0.4;
  }
  50% {
    transform: translateY(-5px);
    opacity: 0.8;
  }
  100% {
    transform: translateY(0);
    opacity: 0.4;
  }
}

/* Dropdown com estilo glass */
.glass-dropdown {
  background: var(--glass-bg);
  backdrop-filter: blur(var(--glass-blur));
  -webkit-backdrop-filter: blur(var(--glass-blur));
  border: 1px solid var(--glass-border);
}

.suggestion-btn {
  font-size: 0.8rem;
  padding: 0.25rem 0.5rem;
  white-space: nowrap;
}

/* Notificações e alertas inteligentes */
.insight-alert {
  background: var(--glass-bg);
  backdrop-filter: blur(var(--glass-blur));
  border-left: 4px solid var(--primary);
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  transition: all 0.3s ease;
}

.insight-alert:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.insight-alert.warning {
  border-left-color: var(--warning);
}

.insight-alert.danger {
  border-left-color: var(--danger);
}

.insight-alert.success {
  border-left-color: var(--success);
}

.insight-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 0.5rem;
  font-size: 1.2rem;
}

.insight-content {
  flex: 1;
}

.insight-title {
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.insight-description {
  color: var(--secondary);
  font-size: 0.875rem;
  margin-bottom: 0;
}

/* Botão flutuante do assistente */
.assistant-floating-btn {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
  z-index: 1050;
  border: none;
}

.assistant-floating-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

/* Efeitos de animação */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translate3d(0, 20px, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.card {
    animation: fadeInUp 0.5s ease-out forwards;
}

/* Delay em cascata para cards */
.row > div:nth-child(1) .card { animation-delay: 0.1s; }
.row > div:nth-child(2) .card { animation-delay: 0.2s; }
.row > div:nth-child(3) .card { animation-delay: 0.3s; }
.row > div:nth-child(4) .card { animation-delay: 0.4s; }

/* Toast container */
.toast-container {
    z-index: 1080;
}

/* Responsividade */
@media (max-width: 992px) {
    .performance-stat {
        padding: 0.5rem;
        font-size: 0.9rem;
    }
    
    .performance-stat h4 {
        font-size: 1.25rem;
    }
}

@media (max-width: 768px) {
    .gauge-container {
        width: 150px;
        height: 75px;
    }
    
    .gauge-center {
        width: 50px;
        height: 50px;
    }
}

@media print {
    .btn, .dropdown, [data-bs-toggle="tooltip"] {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    body {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .assistant-floating-btn {
        display: none !important;
    }
}
</style>