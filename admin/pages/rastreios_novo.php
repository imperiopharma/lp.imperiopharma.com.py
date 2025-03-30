<?php
/**
 * Sistema de Rastreios - Império Pharma
 * Versão: 2.0
 * 
 * Sistema integrado para gerenciamento de códigos de rastreio
 * com suporte a importação de dados e envio de notificações via WhatsApp
 */

// Requisições necessárias
require_once(__DIR__ . '/../inc/config.php');

// Verificar se há as colunas shipped e shipped_at na tabela de pedidos
$hasShippedColumn = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM orders LIKE 'shipped'");
    if ($check && $check->rowCount() > 0) {
        $hasShippedColumn = true;
    }
} catch(Exception $e) {
    $hasShippedColumn = false;
}

// Funções auxiliares
function normalize_str($str) {
    if (!$str) return '';
    $s = mb_strtoupper($str, 'UTF-8');
    $map = [
        '/[ÁÀÂÃÄ]/u' => 'A',
        '/[ÉÈÊË]/u'  => 'E',
        '/[ÍÌÎÏ]/u'  => 'I',
        '/[ÓÒÔÕÖ]/u' => 'O',
        '/[ÚÙÛÜ]/u'  => 'U',
        '/[Ç]/u'     => 'C'
    ];
    foreach ($map as $rgx => $rep) {
        $s = preg_replace($rgx, $rep, $s);
    }
    $s = preg_replace('/[^A-Z0-9 ]+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

function similar_score($a, $b) {
    if (!$a || !$b) return 0.0;
    similar_text($a, $b, $pct);
    return $pct;
}

function cepClean($cep) {
    return substr(preg_replace('/\D/', '', $cep ?? ''), 0, 8);
}

// Função para encontrar pedidos com base nos dados informados
function matchAllOrders($pdo, $nome, $cidade, $uf, $cep) {
    $cp = cepClean($cep);
    if (!$cp) return [];

    $sql = "SELECT * FROM orders
            WHERE REPLACE(cep, '-', '') = :c
            ORDER BY id DESC
            LIMIT 200";
    $stm = $pdo->prepare($sql);
    $stm->execute([':c' => $cp]);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return [];

    $nomeUf = normalize_str($nome . ' ' . $uf);
    $cityUf = normalize_str($cidade . ' ' . $uf);

    $matches = [];
    foreach ($rows as $r) {
        $dbName  = normalize_str(($r['customer_name'] ?? '') . ' ' . ($r['state'] ?? ''));
        $dbCity  = normalize_str(($r['city'] ?? '') . ' ' . ($r['state'] ?? ''));
        $scoreNome = similar_score($nomeUf, $dbName);
        $scoreCity = similar_score($cityUf, $dbCity);
        $avg       = ($scoreNome + $scoreCity) / 2.0;

        if ($scoreNome >= 30 && $scoreCity >= 30 && $avg >= 60) {
            $r['score_nome']   = $scoreNome;
            $r['score_cidade'] = $scoreCity;
            $r['score_total']  = $avg;
            $matches[] = $r;
        }
    }
    
    // Ordenar por score (maior primeiro)
    usort($matches, function($a, $b) {
        return $b['score_total'] <=> $a['score_total'];
    });
    
    return $matches;
}

// Função para encontrar pedidos apenas pelo CEP
function matchOrdersByCEP($pdo, $cep) {
    $cp = cepClean($cep);
    if (!$cp) return [];
    
    $sql = "SELECT * FROM orders
            WHERE REPLACE(cep, '-', '') = :c
            ORDER BY id DESC
            LIMIT 200";
    $stm = $pdo->prepare($sql);
    $stm->execute([':c' => $cp]);
    return $stm->fetchAll(PDO::FETCH_ASSOC);
}

// Processar linha da planilha
function parsePlanilhaLine($line) {
    if (stripos($line, 'Objeto') === 0) return null;
    $cols = preg_split('/\t|\s{2,}/', trim($line));
    if (count($cols) < 11) return null;
    return [
        'raw'    => $line,
        'objeto' => trim($cols[0]),
        'codigo' => trim($cols[1]),
        'nome'   => trim($cols[2]),
        'cidade' => trim($cols[8]),
        'uf'     => trim($cols[9]),
        'cep'    => trim($cols[10])
    ];
}

// Processar blocos de texto
function parseBlocosTexto($txt) {
    $ret = [];
    $pieces = explode('------', $txt);
    foreach ($pieces as $bk) {
        $bk = trim($bk);
        if ($bk === '') continue;

        $nome=''; $cid=''; $uf=''; $cp=''; $obj=''; $cod='';
        $lines = preg_split('/\r?\n/', $bk);
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if (preg_match('/^NOME:\s*(.*)$/i', $ln, $m)) {
                $nome = $m[1];
            } elseif (preg_match('/^CIDADE:\s*(.*)$/i', $ln, $m)) {
                $tmp = $m[1];
                if (strpos($tmp, '-') !== false) {
                    list($cid, $uf) = array_map('trim', explode('-', $tmp));
                } else {
                    $cid = trim($tmp);
                }
            } elseif (preg_match('/^CEP:\s*(.*)$/i', $ln, $m)) {
                $cp = $m[1];
            } elseif (preg_match('/^OBJETO:\s*(.*)$/i', $ln, $m)) {
                $obj = $m[1];
            } elseif (preg_match('/^CODIGO:\s*(.*)$/i', $ln, $m)) {
                $cod = $m[1];
            }
        }
        $ret[] = [
            'raw'    => $bk,
            'objeto' => $obj,
            'codigo' => $cod,
            'nome'   => $nome,
            'cidade' => $cid,
            'uf'     => $uf,
            'cep'    => $cp
        ];
    }
    return $ret;
}

// Função para enviar mensagem WhatsApp
function enviarMensagemWhatsApp($phone, $message) {
    $token = "1741243040070-789f20d337e5e8d6c95621ba5f5807f8"; // Token padrão - idealmente buscar da configuração
    $url   = "https://api-whatsapp.wascript.com.br/api/enviar-texto/{$token}";

    $phone = preg_replace('/\D/', '', $phone);
    if (substr($phone, 0, 2) !== '55') {
        $phone = '55' . $phone;
    }
    $payload = [
        "phone"   => $phone,
        "message" => $message
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Registrar log se a tabela existir
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'whatsapp_logs'");
        if ($check && $check->rowCount() > 0) {
            $logStmt = $pdo->prepare("INSERT INTO whatsapp_logs (order_id, phone, message, http_code, response, created_at) 
                                   VALUES (:oid, :ph, :msg, :hc, :resp, NOW())");
            $logStmt->execute([
                ':oid'  => $orderId,
                ':ph'   => $phone,
                ':msg'  => $message,
                ':hc'   => $httpCode,
                ':resp' => $response
            ]);
        }
    } catch (Exception $e) {
        // Apenas ignorar erros de log
    }

    return [
        'httpCode' => $httpCode,
        'response' => $response
    ];
}

// Definir a visualização padrão ou a solicitada
$view = isset($_GET['view']) ? $_GET['view'] : 'list_unmatched';

// PROCESSAMENTO DE AÇÕES

// 1. Processamento de envio de WhatsApp
if ($view === 'enviar_whatsapp') {
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Consulta SQL incluindo shipped condicionalmente
    $sqlShipped = $hasShippedColumn ? ", shipped" : "";
    $stmt = $pdo->prepare("SELECT phone, customer_name, tracking_code $sqlShipped FROM orders WHERE id=? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }

    // Verificar se já foi enviado (se a coluna existir)
    if ($hasShippedColumn && !empty($order['shipped'])) {
        echo json_encode(['success' => false, 'message' => 'Mensagem já enviada para este pedido']);
        exit;
    }

    $phone    = $order['phone'] ?? '';
    $nome     = $order['customer_name'] ?? '';
    $tracking = trim($order['tracking_code'] ?? '');
    
    if (empty($phone) || empty($tracking)) {
        echo json_encode(['success' => false, 'message' => 'Telefone ou tracking não informado']);
        exit;
    }

    $trackingBase = trim($_GET['base'] ?? '');
    $msg  = "📦 *Rastreamento do Seu Pedido* 📦\n\n";
    $msg .= "Olá {$nome},\n\n";
    $msg .= "Segue abaixo o seu código de rastreamento:\n";
    $msg .= "*{$tracking}*\n";
    
    if (!empty($trackingBase)) {
        $msg .= "Você pode acompanhar o status do seu pedido acessando:\n{$trackingBase}\n\n";
    } else {
        $msg .= "\n";
    }
    
    $msg .= "Agradecemos por sua preferência e confiança.\n";
    $msg .= "Atenciosamente,\nEquipe Império Pharma";

    $res = enviarMensagemWhatsApp($phone, $msg);

    if ($res['httpCode'] === 200) {
        // Marca como enviado se a coluna existir
        if ($hasShippedColumn) {
            $upd = $pdo->prepare("UPDATE orders SET shipped=1, shipped_at=NOW() WHERE id=? LIMIT 1");
            $upd->execute([$orderId]);
        }
        echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso!']);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Falha ao enviar (HTTP ' . $res['httpCode'] . ')',
            'response'=> $res['response']
        ]);
    }
    exit;
}

// 2. Desfazer associação de tracking
if ($view === 'undo') {
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId > 0) {
        try {
            if ($hasShippedColumn) {
                $stmt = $pdo->prepare("UPDATE orders SET tracking_code='', shipped=0 WHERE id=? LIMIT 1");
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET tracking_code='' WHERE id=? LIMIT 1");
            }
            $stmt->execute([$orderId]);
        } catch (Exception $e) {
            // Registrar erro se necessário
        }
    }
    header("Location: index.php?page=rastreios_novo&view=list_matched");
    exit;
}

// 3. Editar tracking
if ($view === 'edit') {
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) {
        echo "<p>ID inválido.</p>";
        exit;
    }
    
    try {
        $stm = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
        $stm->execute([$orderId]);
        $order = $stm->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            echo "<p>Pedido não encontrado!</p>";
            exit;
        }
    } catch (Exception $e) {
        echo "<p>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newTrack = trim($_POST['tracking_code'] ?? '');
        try {
            $up = $pdo->prepare("UPDATE orders SET tracking_code=? WHERE id=? LIMIT 1");
            $up->execute([$newTrack, $orderId]);
            header("Location: index.php?page=rastreios_novo&view=list_matched");
            exit;
        } catch (Exception $e) {
            $errMsg = $e->getMessage();
        }
    }
    
    require_once(__DIR__ . '/../inc/cabecalho.php');
?>
<div class="container my-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Editar Rastreio do Pedido #<?= (int)$orderId ?></h5>
        </div>
        <div class="card-body">
            <?php if (isset($errMsg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errMsg) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Tracking Atual:</label>
                    <input type="text" class="form-control"
                           value="<?= htmlspecialchars($order['tracking_code'] ?? '') ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Novo Tracking (vazio p/ remover):</label>
                    <input type="text" name="tracking_code" class="form-control"
                           value="<?= htmlspecialchars($order['tracking_code'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="index.php?page=rastreios_novo&view=list_matched" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<?php
    require_once(__DIR__ . '/../inc/rodape.php');
    exit;
}

// 4. Atualizar mensagens admin_comments para pedidos
if ($view === 'update_messages' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $messages = $_POST['messages'] ?? [];
    $countUpdated = 0;
    
    foreach ($messages as $orderId => $msg) {
        $orderId = (int)$orderId;
        $msg = trim($msg);
        if ($orderId > 0 && $msg !== '') {
            try {
                // Buscar comentários existentes
                $stmGet = $pdo->prepare("SELECT admin_comments FROM orders WHERE id=? LIMIT 1");
                $stmGet->execute([$orderId]);
                $old = '';
                if ($row = $stmGet->fetch(PDO::FETCH_ASSOC)) {
                    $old = $row['admin_comments'] ?? '';
                }
                
                // Adicionar nova mensagem
                $sep = $old ? "\n" : "";
                $new = $old . $sep . $msg;
                
                // Atualizar comentários
                $stmUpd = $pdo->prepare("UPDATE orders SET admin_comments=? WHERE id=? LIMIT 1");
                $stmUpd->execute([$new, $orderId]);
                $countUpdated++;
            } catch (Exception $e) {
                // Ignorar erros individuais
            }
        }
    }
    
    echo "<div class='alert alert-success'>Foram atualizadas mensagens em {$countUpdated} pedidos.</div>";
    echo "<p><a href='index.php?page=rastreios_novo&view=list_unmatched' class='btn btn-secondary'>Voltar</a></p>";
    exit;
}

// 5. Lista de pedidos sem rastreio
if ($view === 'list_unmatched') {
    $status = trim($_GET['status'] ?? '');
    $nome   = trim($_GET['nome'] ?? '');
    $where  = ["(tracking_code IS NULL OR tracking_code='')"];
    $params = [];

    if ($status !== '') {
        $where[] = "status = :st";
        $params[':st'] = $status;
    }
    if ($nome !== '') {
        $where[] = "customer_name LIKE :nm";
        $params[':nm'] = "%$nome%";
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT id, customer_name, phone, email, address, number, complement,
                   city, state, cep, final_value, status, created_at
            FROM orders
            $whereSql
            ORDER BY id DESC
            LIMIT 200";
    try {
        $stm = $pdo->prepare($sql);
        $stm->execute($params);
        $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rows = [];
        $errorMsg = $e->getMessage();
    }
    
    require_once(__DIR__ . '/../inc/cabecalho.php');
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Pedidos Sem Rastreio</h2>
        <div>
            <a href="index.php?page=rastreios_novo&view=list_matched" class="btn btn-success me-2">
                <i class="fas fa-check-circle me-1"></i> Ver Pedidos Com Rastreio
            </a>
            <a href="index.php?page=rastreios_novo&view=import" class="btn btn-primary">
                <i class="fas fa-file-import me-1"></i> Importar Rastreios
            </a>
        </div>
    </div>

    <?php if (isset($errorMsg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-1"></i> Erro ao buscar pedidos: <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="rastreios_novo">
                <input type="hidden" name="view" value="list_unmatched">
                
                <div class="col-md-3">
                    <label for="st" class="form-label">Status:</label>
                    <select name="status" id="st" class="form-select">
                        <option value="">-- Todos --</option>
                        <?php
                            $possibleSt = ['PENDENTE','CONFIRMADO','EM PROCESSO','CONCLUIDO','CANCELADO'];
                            foreach ($possibleSt as $ps) {
                                $sel = ($status === $ps) ? 'selected' : '';
                                echo "<option value='$ps' $sel>$ps</option>";
                            }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="nm" class="form-label">Nome do Cliente:</label>
                    <input type="text" name="nome" id="nm"
                           class="form-control"
                           value="<?= htmlspecialchars($nome) ?>"
                           placeholder="Digite o nome para filtrar...">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="index.php?page=rastreios_novo&view=update_messages">
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Lista de Pedidos</h5>
                <span class="badge bg-primary"><?= count($rows) ?> pedidos encontrados</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#ID</th>
                                <th>Cliente</th>
                                <th>Cidade/UF</th>
                                <th>Valor Final</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                                <th width="25%">Mensagem p/ Cliente</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="far fa-clipboard fs-4 d-block mb-2"></i>
                                        Nenhum pedido encontrado
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $od):
                                $pid = (int)$od['id'];
                                $cuf = htmlspecialchars($od['city'].'/'.$od['state']);
                                $fv  = number_format($od['final_value'] ?? 0, 2, ',', '.');
                            ?>
                            <tr>
                                <td><?= $pid ?></td>
                                <td>
                                    <div><?= htmlspecialchars($od['customer_name'] ?? '') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($od['phone'] ?? '') ?></small>
                                </td>
                                <td><?= $cuf ?></td>
                                <td>R$ <?= $fv ?></td>
                                <td>
                                    <?php 
                                        $statusClass = '';
                                        switch ($od['status']) {
                                            case 'PENDENTE': $statusClass = 'bg-warning text-dark'; break;
                                            case 'CONFIRMADO': $statusClass = 'bg-primary'; break;
                                            case 'EM PROCESSO': $statusClass = 'bg-info'; break;
                                            case 'CONCLUIDO': $statusClass = 'bg-success'; break;
                                            case 'CANCELADO': $statusClass = 'bg-danger'; break;
                                            default: $statusClass = 'bg-secondary';
                                        }
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($od['status'] ?? '') ?></span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($od['created_at'])) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-secondary mb-1" onclick="toggleDetails(<?= $pid ?>)">
                                        <i class="fas fa-info-circle me-1"></i> Detalhes
                                    </button>
                                    <a href="index.php?page=rastreios_novo&view=edit&id=<?= $pid ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-pen me-1"></i> Editar Tracking
                                    </a>
                                </td>
                                <td>
                                    <textarea name="messages[<?= $pid ?>]" rows="2" class="form-control"
                                              placeholder="Insira mensagem para o cliente..."></textarea>
                                </td>
                            </tr>
                            <tr id="details_<?= $pid ?>" class="details-row">
                                <td colspan="8">
                                    <div class="card card-body bg-light">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-2">Dados do Cliente</h6>
                                                <ul class="list-unstyled mb-0">
                                                    <li><strong>Telefone:</strong> <?= htmlspecialchars($od['phone'] ?? '') ?></li>
                                                    <li><strong>Email:</strong> <?= htmlspecialchars($od['email'] ?? '') ?></li>
                                                    <li><strong>Endereço:</strong> <?= htmlspecialchars($od['address'] ?? '') ?>, <?= htmlspecialchars($od['number'] ?? '') ?></li>
                                                    <li><strong>Complemento:</strong> <?= htmlspecialchars($od['complement'] ?? '') ?></li>
                                                    <li><strong>CEP:</strong> <?= htmlspecialchars($od['cep'] ?? '') ?></li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-2">Dados do Pedido</h6>
                                                <ul class="list-unstyled mb-0">
                                                    <li><strong>Valor Final:</strong> R$ <?= $fv ?></li>
                                                    <li><strong>Status:</strong> <?= htmlspecialchars($od['status'] ?? '') ?></li>
                                                    <li><strong>Data Criação:</strong> <?= date('d/m/Y H:i', strtotime($od['created_at'])) ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Salvar Mensagens
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details_' + id);
    if (row) {
        row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
    }
}
</script>
<?php
    require_once(__DIR__ . '/../inc/rodape.php');
    exit;
}

// 6. Lista de pedidos com rastreio
if ($view === 'list_matched') {
    $status  = trim($_GET['status'] ?? '');
    $nome    = trim($_GET['nome'] ?? '');
    $pageNum = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($pageNum < 1) $pageNum = 1;
    $limit  = 50;  // Número de registros por página
    $offset = ($pageNum - 1) * $limit;

    $where = ["(tracking_code IS NOT NULL AND tracking_code <> '')"];
    $params = [];
    if ($status !== '') {
        $where[] = "status = :st";
        $params[':st'] = $status;
    }
    if ($nome !== '') {
        $where[] = "customer_name LIKE :nm";
        $params[':nm'] = "%$nome%";
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Contagem total para paginação
    $countSql = "SELECT COUNT(*) FROM orders $whereSql";
    try {
        $stmCount = $pdo->prepare($countSql);
        $stmCount->execute($params);
        $totalRows = (int)$stmCount->fetchColumn();
        $totalPages = ceil($totalRows / $limit);
    } catch (Exception $e) {
        $totalRows = 0;
        $totalPages = 1;
        $errorMsg = $e->getMessage();
    }

    // Busca paginada
    $sqlFields = $hasShippedColumn ? ", shipped, shipped_at" : "";
    $sql = "SELECT id, customer_name, phone, tracking_code, city, state, 
                   final_value, updated_at, created_at $sqlFields
            FROM orders
            $whereSql
            ORDER BY updated_at DESC, id DESC
            LIMIT :limit OFFSET :offset";
            
    try {
        $stm = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stm->bindValue($k, $v);
        }
        $stm->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stm->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stm->execute();
        $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rows = [];
        $errorMsg = $e->getMessage();
    }

    $hoje = date('Y-m-d');
    
    require_once(__DIR__ . '/../inc/cabecalho.php');
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Pedidos Com Rastreio</h2>
        <div>
            <a href="index.php?page=rastreios_novo&view=list_unmatched" class="btn btn-secondary me-2">
                <i class="fas fa-list me-1"></i> Ver Pedidos Sem Rastreio
            </a>
            <a href="index.php?page=rastreios_novo&view=import" class="btn btn-primary">
                <i class="fas fa-file-import me-1"></i> Importar Rastreios
            </a>
        </div>
    </div>

    <?php if (isset($errorMsg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-1"></i> Erro ao buscar pedidos: <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="rastreios_novo">
                <input type="hidden" name="view" value="list_matched">
                
                <div class="col-md-3">
                    <label for="st2" class="form-label">Status:</label>
                    <select name="status" id="st2" class="form-select">
                        <option value="">-- Todos --</option>
                        <?php
                            $possibleSt = ['PENDENTE','CONFIRMADO','EM PROCESSO','CONCLUIDO','CANCELADO'];
                            foreach ($possibleSt as $ps) {
                                $sel = ($status === $ps) ? 'selected' : '';
                                echo "<option value='$ps' $sel>$ps</option>";
                            }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="nm2" class="form-label">Nome do Cliente:</label>
                    <input type="text" name="nome" id="nm2"
                           class="form-control"
                           value="<?= htmlspecialchars($nome) ?>"
                           placeholder="Digite o nome para filtrar...">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Lista de Pedidos com Rastreio</h5>
            <span class="badge bg-primary"><?= count($rows) ?> de <?= $totalRows ?> pedidos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#ID</th>
                            <th>Cliente</th>
                            <th>Cidade/UF</th>
                            <th>Valor</th>
                            <th>Código Rastreio</th>
                            <th>Atualizado</th>
                            <th>Status Envio</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="far fa-clipboard fs-4 d-block mb-2"></i>
                                    Nenhum pedido encontrado
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $od):
                            $pid = (int)$od['id'];
                            $cuf = htmlspecialchars($od['city'] . '/' . $od['state']);
                            $fv  = number_format($od['final_value'] ?? 0, 2, ',', '.');
                            $trk = htmlspecialchars($od['tracking_code'] ?? '');
                            $upd = ($od['updated_at'] ?? $od['created_at'] ?? '');
                            $isToday = (date('Y-m-d', strtotime($upd)) === $hoje);
                            $rowClass = $isToday ? 'table-success' : '';
                            $shipped = isset($od['shipped']) ? (int)$od['shipped'] : 0;
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= $pid ?></td>
                            <td>
                                <div><?= htmlspecialchars($od['customer_name'] ?? '') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($od['phone'] ?? '') ?></small>
                            </td>
                            <td><?= $cuf ?></td>
                            <td>R$ <?= $fv ?></td>
                            <td><code><?= $trk ?></code></td>
                            <td><?= date('d/m/Y H:i', strtotime($upd)) ?></td>
                            <td>
                                <?php if ($hasShippedColumn && $shipped): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i> WhatsApp Enviado
                                    </span>
                                    <?php if (isset($od['shipped_at'])): ?>
                                        <div class="small text-muted mt-1">
                                            <?= date('d/m/Y H:i', strtotime($od['shipped_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-clock me-1"></i> Não Enviado
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?page=rastreios_novo&view=edit&id=<?= $pid ?>" class="btn btn-primary">
                                            <i class="fas fa-edit me-1"></i> Editar
                                        </a>
                                        <a href="index.php?page=rastreios_novo&view=undo&id=<?= $pid ?>" class="btn btn-danger"
                                           onclick="return confirm('Deseja remover o tracking deste pedido?');">
                                            <i class="fas fa-trash me-1"></i> Remover
                                        </a>
                                    </div>
                                    
                                    <?php if (!$hasShippedColumn || !$shipped): ?>
                                        <div class="mt-2">
                                            <select id="tracking_base_<?= $pid ?>" class="form-select form-select-sm d-inline-block" style="width:65%;">
                                                <option value="https://melhorrastreio.com.br/">Melhor Rastreio</option>
                                                <option value="https://conecta.log.br/rastreio.php">Onlog</option>
                                                <option value="https://www.loggi.com/rastreador/">Loggi</option>
                                                <option value="">Sem link</option>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-success" onclick="enviarWhatsApp(<?= $pid ?>)">
                                                <i class="fab fa-whatsapp me-1"></i> Enviar
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Paginação">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($pageNum > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=rastreios_novo&view=list_matched&p=1&status=<?= urlencode($status) ?>&nome=<?= urlencode($nome) ?>" aria-label="Primeira">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=rastreios_novo&view=list_matched&p=<?= $pageNum - 1 ?>&status=<?= urlencode($status) ?>&nome=<?= urlencode($nome) ?>" aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php 
                        // Mostrar no máximo 5 páginas
                        $startPage = max(1, $pageNum - 2);
                        $endPage = min($totalPages, $pageNum + 2);
                        
                        if ($startPage > 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $pageNum) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=rastreios_novo&view=list_matched&p=<?= $i ?>&status=<?= urlencode($status) ?>&nome=<?= urlencode($nome) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    } ?>
                    
                    <?php if ($pageNum < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=rastreios_novo&view=list_matched&p=<?= $pageNum + 1 ?>&status=<?= urlencode($status) ?>&nome=<?= urlencode($nome) ?>" aria-label="Próxima">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=rastreios_novo&view=list_matched&p=<?= $totalPages ?>&status=<?= urlencode($status) ?>&nome=<?= urlencode($nome) ?>" aria-label="Última">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function enviarWhatsApp(orderId) {
    const selectElem = document.getElementById('tracking_base_' + orderId);
    const baseLink = selectElem ? selectElem.value : "https://melhorrastreio.com.br/";
    
    if (confirm("Deseja enviar o código de rastreio via WhatsApp?\n\nLink: " + baseLink + "\n\nConfirmar envio?")) {
        // Mostrar loading
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        
        fetch('index.php?page=rastreios_novo&view=enviar_whatsapp&id=' + orderId + '&base=' + encodeURIComponent(baseLink))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Mensagem enviada com sucesso!");
                    window.location.reload();
                } else {
                    alert("Erro ao enviar mensagem: " + data.message);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                alert("Erro na requisição: " + error);
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    }
}

function toggleDetails(id) {
    const row = document.getElementById('details_' + id);
    if (row) {
        row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
    }
}
</script>
<?php
    require_once(__DIR__ . '/../inc/rodape.php');
    exit;
}

// 7. Tela de importação de rastreios
if ($view === 'import') {
    // Caso o formulário tenha sido submetido
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['textoBruto'])) {
        $texto = trim($_POST['textoBruto'] ?? '');
        $formato = trim($_POST['formato'] ?? 'planilha');
        
        if (empty($texto)) {
            $importError = "Texto vazio. Por favor, insira algum conteúdo.";
        } else {
            // Processar texto de acordo com o formato
            $arrOK = [];
            $arrFail = [];
            
            if ($formato === 'bloco') {
                $blocos = parseBlocosTexto($texto);
                foreach ($blocos as $bk) {
                    if (empty($bk['cep']) || empty($bk['codigo'])) {
                        $arrFail[] = $bk;
                        continue;
                    }
                    
                    $matches = matchAllOrders($pdo, $bk['nome'], $bk['cidade'], $bk['uf'], $bk['cep']);
                    if (empty($matches)) {
                        $arrFail[] = $bk;
                    } else {
                        $bk['matches'] = $matches;
                        $arrOK[] = $bk;
                    }
                }
            } elseif ($formato === 'novopar') {
                $lines = preg_split('/\r?\n/', $texto);
                $nonEmpty = array_values(array_filter($lines, function($line) {
                    return trim($line) !== '';
                }));
                
                if (count($nonEmpty) % 2 !== 0) {
                    $importError = "Número de linhas inválido. Cada registro deve ter 2 linhas (CEP e Código).";
                } else {
                    for ($i = 0; $i < count($nonEmpty); $i += 2) {
                        $cep = trim($nonEmpty[$i]);
                        $codigo = trim($nonEmpty[$i+1]);
                        $cepLimpo = cepClean($cep);
                        
                        if (strlen($cepLimpo) !== 8 || strlen($codigo) < 5) {
                            $arrFail[] = ['raw' => "$cep\n$codigo"];
                            continue;
                        }
                        
                        $matches = matchOrdersByCEP($pdo, $cepLimpo);
                        if (empty($matches)) {
                            $arrFail[] = ['raw' => "$cep\n$codigo"];
                        } else {
                            $arrOK[] = [
                                'raw'    => "$cep\n$codigo",
                                'cep'    => $cepLimpo,
                                'codigo' => $codigo,
                                'matches'=> $matches
                            ];
                        }
                    }
                }
            } else {
                // Formato planilha (padrão)
                $lines = preg_split('/\r?\n/', $texto);
                foreach ($lines as $ln) {
                    $ln = trim($ln);
                    if ($ln === '' || stripos($ln, 'Objeto') === 0) continue;
                    
                    $p = parsePlanilhaLine($ln);
                    if (!$p || empty($p['cep']) || empty($p['codigo'])) {
                        $arrFail[] = ['raw' => $ln];
                        continue;
                    }
                    
                    $matches = matchAllOrders($pdo, $p['nome'], $p['cidade'], $p['uf'], $p['cep']);
                    if (empty($matches)) {
                        $arrFail[] = $p;
                    } else {
                        $p['matches'] = $matches;
                        $arrOK[] = $p;
                    }
                }
            }
            
            // Se processou com sucesso, exibe a tela de resultados
            require_once(__DIR__ . '/../inc/cabecalho.php');
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Resultado da Importação de Rastreios</h2>
        <a href="index.php?page=rastreios_novo&view=import" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>
    
    <form method="POST" action="index.php?page=rastreios_novo&view=save_import_results">
        <?php if (empty($arrOK)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-1"></i> Nenhum pedido correspondente encontrado nos dados importados.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-1"></i> Foram encontrados <strong><?= count($arrOK) ?></strong> registros com correspondências potenciais.
            </div>
            
            <?php foreach ($arrOK as $i => $item):
                $raw = $item['raw'] ?? '';
                $cep = $item['cep'] ?? '';
                $cod = $item['codigo'] ?? '';
                $matches = $item['matches'] ?? [];
            ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Registro #<?= $i+1 ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Dados do Rastreio</h6>
                            <div class="mb-2">
                                <strong>CEP:</strong> <?= htmlspecialchars($cep) ?>
                            </div>
                            <div class="mb-2">
                                <strong>Código de Rastreio:</strong> <?= htmlspecialchars($cod) ?>
                            </div>
                            <div class="bg-light p-2 mt-2">
                                <pre class="mb-0" style="white-space:pre-wrap; font-size:0.8rem;"><?= htmlspecialchars($raw) ?></pre>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Pedidos Possíveis</h6>
                            <?php if (!empty($matches)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Selecionar</th>
                                                <th>#ID</th>
                                                <th>Cliente</th>
                                                <th>CEP</th>
                                                <?php if (isset($matches[0]['score_total'])): ?>
                                                <th>Score</th>
                                                <?php endif; ?>
                                                <th>Detalhes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($matches as $j => $match): 
                                                $pid = (int)$match['id'];
                                                $score = isset($match['score_total']) ? round($match['score_total'], 1) : null;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" 
                                                               name="import[<?= $i ?>][matches]" 
                                                               id="match_<?= $i ?>_<?= $j ?>" 
                                                               value="<?= $pid ?>" 
                                                               <?= $j === 0 ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="match_<?= $i ?>_<?= $j ?>"></label>
                                                    </div>
                                                </td>
                                                <td><?= $pid ?></td>
                                                <td><?= htmlspecialchars($match['customer_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($match['cep'] ?? '') ?></td>
                                                <?php if (isset($score)): ?>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-<?= $score >= 80 ? 'success' : ($score >= 60 ? 'primary' : 'warning') ?>"
                                                             role="progressbar" style="width: <?= $score ?>%;" 
                                                             aria-valuenow="<?= $score ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?= $score ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php endif; ?>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="toggleMatchDetails('details_<?= $i ?>_<?= $j ?>')">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                    <div id="details_<?= $i ?>_<?= $j ?>" class="match-details" style="display:none;">
                                                        <div class="card mt-2">
                                                            <div class="card-body">
                                                                <dl class="row mb-0">
                                                                    <dt class="col-sm-4">Cliente:</dt>
                                                                    <dd class="col-sm-8"><?= htmlspecialchars($match['customer_name'] ?? '') ?></dd>
                                                                    
                                                                    <dt class="col-sm-4">Telefone:</dt>
                                                                    <dd class="col-sm-8"><?= htmlspecialchars($match['phone'] ?? '') ?></dd>
                                                                    
                                                                    <dt class="col-sm-4">Endereço:</dt>
                                                                    <dd class="col-sm-8">
                                                                        <?= htmlspecialchars($match['address'] ?? '') ?>, 
                                                                        <?= htmlspecialchars($match['number'] ?? '') ?>,
                                                                        <?= htmlspecialchars($match['city'] ?? '') ?>/<?= htmlspecialchars($match['state'] ?? '') ?>
                                                                    </dd>
                                                                    
                                                                    <dt class="col-sm-4">CEP:</dt>
                                                                    <dd class="col-sm-8"><?= htmlspecialchars($match['cep'] ?? '') ?></dd>
                                                                    
                                                                    <dt class="col-sm-4">Status:</dt>
                                                                    <dd class="col-sm-8"><?= htmlspecialchars($match['status'] ?? '') ?></dd>
                                                                    
                                                                    <dt class="col-sm-4">Valor:</dt>
                                                                    <dd class="col-sm-8">R$ <?= number_format($match['final_value'] ?? 0, 2, ',', '.') ?></dd>
                                                                    
                                                                    <?php if (isset($match['score_nome'])): ?>
                                                                    <dt class="col-sm-4">Score Nome:</dt>
                                                                    <dd class="col-sm-8"><?= round($match['score_nome'], 1) ?>%</dd>
                                                                    
                                                                    <dt class="col-sm-4">Score Cidade:</dt>
                                                                    <dd class="col-sm-8"><?= round($match['score_cidade'], 1) ?>%</dd>
                                                                    <?php endif; ?>
                                                                </dl>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Nenhum pedido correspondente encontrado.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="comment_<?= $i ?>" class="form-label">Mensagem para admin_comments (opcional):</label>
                                <textarea id="comment_<?= $i ?>" name="import[<?= $i ?>][msg]" class="form-control" rows="2" 
                                          placeholder="Observação ou mensagem para o cliente..."></textarea>
                            </div>
                            
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" 
                                       name="import[<?= $i ?>][confirm]" 
                                       id="confirm_<?= $i ?>" 
                                       value="1" checked>
                                <label class="form-check-label" for="confirm_<?= $i ?>">
                                    Confirmar associação deste rastreio
                                </label>
                            </div>
                            
                            <input type="hidden" name="import[<?= $i ?>][codigo]" value="<?= htmlspecialchars($cod) ?>">
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="mb-4">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Salvar Todas as Associações
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($arrFail)): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Registros Sem Correspondência (<?= count($arrFail) ?>)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Os seguintes registros não puderam ser associados a pedidos:</p>
                    <div class="list-group">
                        <?php foreach ($arrFail as $fail): 
                            $rawText = $fail['raw'] ?? (is_array($fail) ? json_encode($fail) : $fail);
                        ?>
                            <div class="list-group-item">
                                <pre class="mb-0" style="white-space:pre-wrap; font-size:0.8rem;"><?= htmlspecialchars($rawText) ?></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleMatchDetails(id) {
    const element = document.getElementById(id);
    if (element) {
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
}
</script>
<?php
            require_once(__DIR__ . '/../inc/rodape.php');
            exit;
        }
    }
    
    // Formulário de importação
    require_once(__DIR__ . '/../inc/cabecalho.php');
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Importar Códigos de Rastreio</h2>
        <div>
            <a href="index.php?page=rastreios_novo&view=list_unmatched" class="btn btn-secondary me-2">
                <i class="fas fa-list me-1"></i> Pedidos Sem Rastreio
            </a>
            <a href="index.php?page=rastreios_novo&view=list_matched" class="btn btn-secondary">
                <i class="fas fa-check-circle me-1"></i> Pedidos Com Rastreio
            </a>
        </div>
    </div>
    
    <?php if (isset($importError)): ?>
        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($importError) ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Enviar Dados para Importação</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="index.php?page=rastreios_novo&view=import">
                <div class="mb-3">
                    <label for="formato" class="form-label">Formato dos Dados:</label>
                    <select name="formato" id="formato" class="form-select">
                        <option value="planilha">Planilha (linhas com Objeto, Codigo, Nome, etc.)</option>
                        <option value="bloco">Bloco (NOME:, CIDADE:, CEP:, CODIGO:...)</option>
                        <option value="novopar">Novo Formato (linha 1: CEP, linha 2: Código de Rastreio)</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="textoBruto" class="form-label">Texto com Dados de Rastreio:</label>
                    <textarea name="textoBruto" id="textoBruto" rows="10" class="form-control" placeholder="Cole os dados aqui..." required></textarea>
                </div>
                
                <div class="mb-3">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Exemplos de Formatos Aceitos</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>1. Formato Planilha:</strong>
                                <pre class="bg-light p-2 mt-1" style="font-size: 0.8rem;">Objeto  Codigo        Nome               ...   Cidade    UF  CEP
BR926374817BR  ABC1234  João da Silva     ...   São Paulo  SP  01310-200
BR827363526BR  DEF5678  Maria Oliveira    ...   Rio        RJ  22041-901</pre>
                            </div>
                            
                            <div class="mb-3">
                                <strong>2. Formato Bloco:</strong>
                                <pre class="bg-light p-2 mt-1" style="font-size: 0.8rem;">NOME: João da Silva
CIDADE: São Paulo - SP
CEP: 01310-200
OBJETO: BR926374817BR
CODIGO: ABC1234
------
NOME: Maria Oliveira
CIDADE: Rio - RJ
CEP: 22041-901
OBJETO: BR827363526BR
CODIGO: DEF5678</pre>
                            </div>
                            
                            <div>
                                <strong>3. Formato Novo (Pares de CEP e Código):</strong>
                                <pre class="bg-light p-2 mt-1" style="font-size: 0.8rem;">01310-200
ABC1234
22041-901
DEF5678</pre>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-file-import me-1"></i> Processar Importação
                </button>
            </form>
        </div>
    </div>
</div>
<?php
    require_once(__DIR__ . '/../inc/rodape.php');
    exit;
}

// 8. Salvar resultados da importação
if ($view === 'save_import_results' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $importData = $_POST['import'] ?? [];
    $countSaved = 0;
    $errors = [];
    
    // Preparar statements
    $updateTracking = $pdo->prepare("UPDATE orders SET tracking_code = ? WHERE id = ? LIMIT 1");
    $getComments = $pdo->prepare("SELECT admin_comments FROM orders WHERE id = ? LIMIT 1");
    $updateComments = $pdo->prepare("UPDATE orders SET admin_comments = ? WHERE id = ? LIMIT 1");
    
    // Processar cada item da importação
    foreach ($importData as $index => $item) {
        // Verificar se está confirmado
        $isConfirmed = isset($item['confirm']) && $item['confirm'] == '1';
        if (!$isConfirmed) {
            continue;
        }
        
        // Pegar ID do pedido e código de rastreio
        $orderId = isset($item['matches']) ? (int)$item['matches'] : 0;
        $trackingCode = trim($item['codigo'] ?? '');
        
        if ($orderId <= 0 || empty($trackingCode)) {
            $errors[] = "Item #" . ($index + 1) . ": ID de pedido inválido ou código de rastreio vazio.";
            continue;
        }
        
        // Atualizar tracking
        try {
            $updateTracking->execute([$trackingCode, $orderId]);
            $countSaved++;
            
            // Se há mensagem, atualizar admin_comments
            $message = trim($item['msg'] ?? '');
            if (!empty($message)) {
                // Busca comentários existentes
                $getComments->execute([$orderId]);
                $existingComments = $getComments->fetchColumn() ?: '';
                
                // Adiciona nova mensagem
                $separator = !empty($existingComments) ? "\n" : '';
                $newComments = $existingComments . $separator . $message;
                
                // Atualiza comentários
                $updateComments->execute([$newComments, $orderId]);
            }
        } catch (Exception $e) {
            $errors[] = "Item #" . ($index + 1) . ": Erro ao salvar - " . $e->getMessage();
        }
    }
    
    // Redirecionar com mensagem
    $redirectUrl = "index.php?page=rastreios_novo&view=list_matched";
    
    if ($countSaved > 0) {
        $_SESSION['rastreio_msg'] = "Foram atualizados códigos de rastreio para {$countSaved} pedidos com sucesso!";
        $_SESSION['rastreio_msg_type'] = "success";
    } else {
        $_SESSION['rastreio_msg'] = "Nenhum código de rastreio foi salvo. Verifique os dados e tente novamente.";
        $_SESSION['rastreio_msg_type'] = "warning";
    }
    
    if (!empty($errors)) {
        $_SESSION['rastreio_errors'] = $errors;
    }
    
    header("Location: " . $redirectUrl);
    exit;
}

// View padrão - redirecionar para lista de pedidos sem rastreio
header("Location: index.php?page=rastreios_novo&view=list_unmatched");
exit;
?>