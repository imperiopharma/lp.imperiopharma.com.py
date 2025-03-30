<?php
/****************************************************************************
 * Sistema Avançado de Gestão de Pedidos - Império Pharma
 * 
 * FUNCIONALIDADES:
 * - Listagem completa com filtros avançados
 * - Detalhes e alteração de pedidos
 * - Modificação de itens e valores
 * - Observações internas para equipe
 * - Exclusão de pedidos com atualização de métricas
 * - Adição de pedidos manuais e integração financeira
 */

// Função para determinar a classe de status (movida para escopo global)
function getStatusClass($status) {
    // Normaliza o status para comparação
    $status = strtoupper(trim($status));
    
    switch ($status) {
        case 'PENDENTE': 
            return 'bg-warning text-dark';
        case 'CONFIRMADO': 
            return 'bg-primary text-white';
        case 'EM PROCESSO': 
            return 'bg-info text-white';
        case 'CONCLUIDO': 
        case 'CONCLUÍDO': // Versão com acento
            return 'bg-success text-white';
        case 'CANCELADO': 
            return 'bg-danger text-white';
        default: 
            return 'bg-secondary text-white';
    }
}

// Função para obter ícone de status
function getStatusIcon($status) {
    $status = strtoupper(trim($status));
    
    switch ($status) {
        case 'PENDENTE':
            return '<i class="fas fa-clock"></i>';
        case 'CONFIRMADO':
            return '<i class="fas fa-check-circle"></i>';
        case 'EM PROCESSO':
            return '<i class="fas fa-cog"></i>';
        case 'CONCLUIDO':
        case 'CONCLUÍDO':
            return '<i class="fas fa-check-double"></i>';
        case 'CANCELADO':
            return '<i class="fas fa-times-circle"></i>';
        default:
            return '<i class="fas fa-question-circle"></i>';
    }
}

// Obter parâmetros de filtro
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$nome = isset($_GET['nome']) ? trim($_GET['nome']) : '';
$dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
$dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Tratamento de ações via POST (operações de banco)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Atualizar status
    if (isset($_POST['update_status'])) {
        $order_id = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $oldStatus = $_POST['old_status'];
        
        try {
            $pdo->beginTransaction();
            
            // Atualiza status do pedido
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $order_id]);
            
            // Se o status mudou para CANCELADO, atualizar métricas
            if ($status == 'CANCELADO' && $oldStatus != 'CANCELADO') {
                // Marcar pedido para não ser contabilizado nas métricas
                $stmt = $pdo->prepare("UPDATE orders SET closed = 0 WHERE id = ?");
                $stmt->execute([$order_id]);
            }
            
            $pdo->commit();
            setFlashMessage("Status do pedido #$order_id atualizado para $status.", "success", "Sucesso");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao atualizar status: " . $e->getMessage(), "danger", "Erro");
        }
        
        // Redireciona de volta para detalhes do pedido
        header("Location: index.php?page=pedidos&action=detail&id=$order_id");
        exit;
    }
    
    // Atualizar rastreio
    else if (isset($_POST['update_tracking'])) {
        $order_id = (int)$_POST['order_id'];
        $tracking = $_POST['tracking_code'];
        
        try {
            $stmt = $pdo->prepare("UPDATE orders SET tracking_code = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$tracking, $order_id]);
            setFlashMessage("Código de rastreio atualizado.", "success", "Sucesso");
        } catch (PDOException $e) {
            setFlashMessage("Erro ao atualizar rastreio: " . $e->getMessage(), "danger", "Erro");
        }
        
        header("Location: index.php?page=pedidos&action=detail&id=$order_id");
        exit;
    }
    
    // Atualizar comentários administrativos
    else if (isset($_POST['update_comments'])) {
        $order_id = (int)$_POST['order_id'];
        $comments = $_POST['admin_comments'];
        
        try {
            $stmt = $pdo->prepare("UPDATE orders SET admin_comments = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$comments, $order_id]);
            setFlashMessage("Observações administrativas atualizadas.", "success", "Sucesso");
        } catch (PDOException $e) {
            setFlashMessage("Erro ao salvar observações: " . $e->getMessage(), "danger", "Erro");
        }
        
        header("Location: index.php?page=pedidos&action=detail&id=$order_id");
        exit;
    }
    
    // Atualizar valor do pedido
    else if (isset($_POST['update_values'])) {
        $order_id = (int)$_POST['order_id'];
        $final_value = (float)str_replace(',', '.', $_POST['final_value']);
        $cost_total = (float)str_replace(',', '.', $_POST['cost_total']);
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET final_value = ?, 
                    cost_total = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$final_value, $cost_total, $order_id]);
            
            $pdo->commit();
            setFlashMessage("Valores do pedido atualizados com sucesso.", "success", "Sucesso");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao atualizar valores: " . $e->getMessage(), "danger", "Erro");
        }
        
        header("Location: index.php?page=pedidos&action=detail&id=$order_id");
        exit;
    }
    
    // Adicionar item ao pedido
    else if (isset($_POST['add_item'])) {
        $order_id = (int)$_POST['order_id'];
        $product_id = (int)$_POST['product_id'];
        $product_name = $_POST['product_name'];
        $brand = $_POST['brand'];
        $quantity = (int)$_POST['quantity'];
        $price = (float)str_replace(',', '.', $_POST['price']);
        $cost = (float)str_replace(',', '.', $_POST['cost']);
        $subtotal = $price * $quantity;
        
        try {
            $pdo->beginTransaction();
            
            // Adicionar item
            $stmt = $pdo->prepare("
                INSERT INTO order_items 
                    (order_id, product_id, product_name, brand, quantity, price, cost, subtotal) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $product_id, $product_name, $brand, $quantity, $price, $cost, $subtotal]);
            
            // Atualizar totais do pedido
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET final_value = final_value + ?,
                    cost_total = cost_total + ?,
                    total = total + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subtotal, ($cost * $quantity), $subtotal, $order_id]);
            
            $pdo->commit();
            setFlashMessage("Item adicionado ao pedido com sucesso.", "success", "Sucesso");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao adicionar item: " . $e->getMessage(), "danger", "Erro");
        }
        
        header("Location: index.php?page=pedidos&action=detail&id=$order_id");
        exit;
    }
    
    // Remover item do pedido
    else if (isset($_POST['remove_item'])) {
        $order_id = (int)$_POST['order_id'];
        $item_id = (int)$_POST['item_id'];
        $subtotal = (float)$_POST['subtotal'];
        $cost_total = (float)$_POST['cost_total'];
        
        try {
            $pdo->beginTransaction();
            
            // Remover item
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
            $stmt->execute([$item_id, $order_id]);
            
            // Atualizar totais do pedido
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET final_value = final_value - ?,
                    cost_total = cost_total - ?,
                    total = total - ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subtotal, $cost_total, $subtotal, $order_id]);
            
            $pdo->commit();
            setFlashMessage("Item removido do pedido com sucesso.", "success", "Sucesso");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao remover item: " . $e->getMessage(), "danger", "Erro");
        }
        
        header("Location: index.php?page=pedidos&action=detail&id=$order_id");
        exit;
    }
    
    // Novo pedido manual
    else if (isset($_POST['new_manual_order'])) {
        $customer_name = $_POST['customer_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $final_value = (float)str_replace(',', '.', $_POST['final_value']);
        $cost_total = (float)str_replace(',', '.', $_POST['cost_total']);
        $address = $_POST['address'] ?? '';
        $payment_method = $_POST['payment_method'];
        $status = $_POST['status'];
        $admin_comments = $_POST['admin_comments'];
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO orders 
                    (customer_name, email, phone, address, final_value, cost_total, total, 
                     payment_method, status, admin_comments, created_at, updated_at) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $customer_name, $email, $phone, $address, $final_value, $cost_total, $final_value,
                $payment_method, $status, $admin_comments
            ]);
            
            $new_order_id = $pdo->lastInsertId();
            
            // Registra no financeiro (se fechado como CONCLUÍDO)
            if ($status == 'CONCLUIDO') {
                // Marcar como fechado
                $stmt = $pdo->prepare("UPDATE orders SET closed = 1 WHERE id = ?");
                $stmt->execute([$new_order_id]);
            }
            
            $pdo->commit();
            setFlashMessage("Pedido manual #$new_order_id criado com sucesso.", "success", "Sucesso");
            
            header("Location: index.php?page=pedidos&action=detail&id=$new_order_id");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao criar pedido manual: " . $e->getMessage(), "danger", "Erro");
        }
    }
    
    // Excluir pedido
    else if (isset($_POST['delete_order'])) {
        $order_id = (int)$_POST['order_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Primeiro, excluir itens relacionados
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            // Depois, excluir o pedido
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
            setFlashMessage("Pedido #$order_id excluído permanentemente.", "success", "Sucesso");
            
            header("Location: index.php?page=pedidos");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage("Erro ao excluir pedido: " . $e->getMessage(), "danger", "Erro");
        }
    }
}

// Tratar ações via GET (navegação)
switch ($action) {
    case 'delete':
        if ($id) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    setFlashMessage("Pedido #$id não encontrado.", "danger", "Erro");
                    header("Location: index.php?page=pedidos");
                    exit;
                }
            } catch (PDOException $e) {
                setFlashMessage("Erro ao carregar pedido: " . $e->getMessage(), "danger", "Erro");
                header("Location: index.php?page=pedidos");
                exit;
            }
            
            // Exibir confirmação
            ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Confirmar Exclusão</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Esta ação <strong>não pode ser desfeita</strong>. O pedido será permanentemente excluído do banco de dados.
                            </div>
                            
                            <p>Você está prestes a excluir o pedido:</p>
                            <ul>
                                <li><strong>ID:</strong> #<?= $order['id'] ?></li>
                                <li><strong>Cliente:</strong> <?= htmlspecialchars($order['customer_name']) ?></li>
                                <li><strong>Valor:</strong> R$ <?= number_format($order['final_value'], 2, ',', '.') ?></li>
                                <li><strong>Status:</strong> <?= $order['status'] ?></li>
                                <li><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></li>
                            </ul>
                            
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=pedidos&action=detail&id=<?= $order['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancelar
                                    </a>
                                    <button type="submit" name="delete_order" class="btn btn-danger">
                                        <i class="fas fa-trash-alt me-1"></i> Confirmar Exclusão
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;
        }
        // Se não tiver ID, cai no default
        
    case 'new':
        // Formulário para novo pedido manual
        // Buscar produtos para seleção
        try {
            $stmt = $pdo->query("
                SELECT p.id, p.name, b.name as brand_name, p.price, p.cost
                FROM products p
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.active = 1
                ORDER BY p.name ASC
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $products = [];
        }
        ?>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Novo Pedido Manual</h5>
                        <a href="index.php?page=pedidos" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="index.php?page=pedidos" id="newOrderForm">
                            <div class="row">
                                <!-- Coluna Esquerda -->
                                <div class="col-md-6">
                                    <h6 class="mb-3">Informações do Cliente</h6>
                                    
                                    <div class="mb-3">
                                        <label for="customer_name" class="form-label">Nome do Cliente <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Telefone</label>
                                        <input type="text" class="form-control" id="phone" name="phone">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Endereço (resumido)</label>
                                        <input type="text" class="form-control" id="address" name="address">
                                    </div>
                                    
                                    <h6 class="mb-3 mt-4">Produtos</h6>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex mb-2">
                                            <input type="text" class="form-control me-2" id="manual_product" placeholder="Nome do produto...">
                                            <button type="button" class="btn btn-primary" id="addManualProduct">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm" id="productsTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Produto</th>
                                                        <th width="80">Qtd</th>
                                                        <th width="120">Preço (R$)</th>
                                                        <th width="120">Custo (R$)</th>
                                                        <th width="40">Ação</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="no-products">
                                                        <td colspan="5" class="text-center text-muted py-3">
                                                            Nenhum produto adicionado
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Coluna Direita -->
                                <div class="col-md-6">
                                    <h6 class="mb-3">Informações de Pagamento</h6>
                                    
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Método de Pagamento <span class="text-danger">*</span></label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="">Selecione...</option>
                                            <option value="Dinheiro">Dinheiro</option>
                                            <option value="Cartão de Crédito">Cartão de Crédito</option>
                                            <option value="PIX">PIX</option>
                                            <option value="Transferência">Transferência</option>
                                            <option value="Outro">Outro</option>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="final_value" class="form-label">Valor Total (R$) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control money" id="final_value" name="final_value" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="cost_total" class="form-label">Custo Total (R$) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control money" id="cost_total" name="cost_total" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="PENDENTE">Pendente</option>
                                            <option value="CONFIRMADO">Confirmado</option>
                                            <option value="EM PROCESSO">Em Processo</option>
                                            <option value="CONCLUIDO" selected>Concluído</option>
                                            <option value="CANCELADO">Cancelado</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="admin_comments" class="form-label">Observações Administrativas</label>
                                        <textarea class="form-control" id="admin_comments" name="admin_comments" rows="4"></textarea>
                                    </div>
                                    
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Pedidos marcados como <strong>Concluído</strong> serão automaticamente registrados no sistema financeiro.
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="new_manual_order" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Salvar Pedido
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal de Produtos -->
        <div class="modal fade" id="productsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Selecionar Produto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchProduct" placeholder="Buscar produto...">
                        </div>
                        
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-striped" id="productsList">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Produto</th>
                                        <th>Marca</th>
                                        <th>Preço</th>
                                        <th>Custo</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" 
                                        data-brand="<?= htmlspecialchars($product['brand_name']) ?>" 
                                        data-price="<?= number_format($product['price'], 2, ',', '') ?>" 
                                        data-cost="<?= number_format($product['cost'], 2, ',', '') ?>">
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td><?= htmlspecialchars($product['brand_name']) ?></td>
                                        <td>R$ <?= number_format($product['price'], 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format($product['cost'], 2, ',', '.') ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary select-product">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicialização de máscaras monetárias
            document.querySelectorAll('.money').forEach(function(input) {
                IMask(input, {
                    mask: Number,
                    scale: 2,
                    signed: false,
                    thousandsSeparator: '.',
                    padFractionalZeros: true,
                    normalizeZeros: true,
                    radix: ','
                });
            });
            
            // Modal de produtos
            const productsModal = new bootstrap.Modal(document.getElementById('productsModal'));
            
            // Abrir modal ao clicar no botão de adicionar produto
            document.getElementById('addManualProduct').addEventListener('click', function() {
                productsModal.show();
            });
            
            // Busca de produtos
            document.getElementById('searchProduct').addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#productsList tbody tr');
                
                rows.forEach(row => {
                    const productName = row.querySelector('td:first-child').textContent.toLowerCase();
                    const brandName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (productName.includes(searchTerm) || brandName.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Selecionar produto da lista
            document.querySelectorAll('.select-product').forEach(btn => {
                btn.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const productId = row.dataset.id;
                    const productName = row.dataset.name;
                    const brandName = row.dataset.brand;
                    const price = row.dataset.price;
                    const cost = row.dataset.cost;
                    
                    addProductToTable(productId, productName, brandName, price, cost);
                    productsModal.hide();
                });
            });
            
            // Adicionar produto à tabela
            function addProductToTable(id, name, brand, price, cost) {
                // Remover mensagem de "nenhum produto"
                const noProducts = document.querySelector('.no-products');
                if (noProducts) {
                    noProducts.remove();
                }
                
                const table = document.getElementById('productsTable').querySelector('tbody');
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td>
                        ${name} <small class="text-muted d-block">${brand || ''}</small>
                        <input type="hidden" name="product_ids[]" value="${id}">
                        <input type="hidden" name="product_names[]" value="${name}">
                        <input type="hidden" name="product_brands[]" value="${brand || ''}">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm quantity-input" 
                               name="quantities[]" value="1" min="1" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm money price-input" 
                               name="prices[]" value="${price}" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm money cost-input" 
                               name="costs[]" value="${cost}" required>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-product">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                `;
                
                table.appendChild(newRow);
                
                // Reinicializar máscaras para os novos inputs
                newRow.querySelectorAll('.money').forEach(function(input) {
                    IMask(input, {
                        mask: Number,
                        scale: 2,
                        signed: false,
                        thousandsSeparator: '.',
                        padFractionalZeros: true,
                        normalizeZeros: true,
                        radix: ','
                    });
                });
                
                // Calcular totais ao mudar quantidade ou preço
                const quantityInput = newRow.querySelector('.quantity-input');
                const priceInput = newRow.querySelector('.price-input');
                const costInput = newRow.querySelector('.cost-input');
                
                [quantityInput, priceInput, costInput].forEach(input => {
                    input.addEventListener('change', calculateTotals);
                });
                
                // Adicionar evento para remover produto
                newRow.querySelector('.remove-product').addEventListener('click', function() {
                    newRow.remove();
                    calculateTotals();
                    
                    // Se não houver mais produtos, mostrar mensagem
                    if (table.querySelectorAll('tr').length === 0) {
                        table.innerHTML = `
                            <tr class="no-products">
                                <td colspan="5" class="text-center text-muted py-3">
                                    Nenhum produto adicionado
                                </td>
                            </tr>
                        `;
                    }
                });
                
                // Calcular totais iniciais
                calculateTotals();
            }
            
            // Calcular totais
            function calculateTotals() {
                let totalValue = 0;
                let totalCost = 0;
                
                document.querySelectorAll('#productsTable tbody tr:not(.no-products)').forEach(row => {
                    const quantity = parseInt(row.querySelector('.quantity-input').value) || 0;
                    const price = parseFloat(row.querySelector('.price-input').value.replace(',', '.')) || 0;
                    const cost = parseFloat(row.querySelector('.cost-input').value.replace(',', '.')) || 0;
                    
                    totalValue += quantity * price;
                    totalCost += quantity * cost;
                });
                
                // Atualizar campos de total
                document.getElementById('final_value').value = totalValue.toFixed(2).replace('.', ',');
                document.getElementById('cost_total').value = totalCost.toFixed(2).replace('.', ',');
            }
            
            // Envio do formulário
            document.getElementById('newOrderForm').addEventListener('submit', function(e) {
                const products = document.querySelectorAll('#productsTable tbody tr:not(.no-products)');
                if (products.length === 0) {
                    e.preventDefault();
                    alert('Adicione pelo menos um produto ao pedido.');
                    return;
                }
                
                // Validação de valores
                const finalValue = parseFloat(document.getElementById('final_value').value.replace(',', '.')) || 0;
                if (finalValue <= 0) {
                    e.preventDefault();
                    alert('O valor total deve ser maior que zero.');
                    return;
                }
            });
        });
        </script>
        <?php
        break;
    
    case 'detail':
        if (!$id) {
            setFlashMessage("ID de pedido inválido.", "danger", "Erro");
            header("Location: index.php?page=pedidos");
            exit;
        }
        
        try {
            // Buscar pedido
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                setFlashMessage("Pedido #$id não encontrado.", "danger", "Erro");
                header("Location: index.php?page=pedidos");
                exit;
            }
            
            // Buscar itens do pedido
            $stmtItems = $pdo->prepare("
                SELECT oi.*, p.cost as current_cost 
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmtItems->execute([$id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar produtos para possível adição
            $stmtProducts = $pdo->query("
                SELECT p.id, p.name, b.name as brand_name, p.price, p.cost
                FROM products p
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.active = 1
                ORDER BY p.name ASC
                LIMIT 100
            ");
            $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
            
            $lucro = $order['final_value'] - $order['cost_total'];
            $margemLucro = $order['final_value'] > 0 ? ($lucro / $order['final_value']) * 100 : 0;
        } catch (PDOException $e) {
            setFlashMessage("Erro ao carregar dados: " . $e->getMessage(), "danger", "Erro");
            header("Location: index.php?page=pedidos");
            exit;
        }
        

        
        // Função para gerar mensagem interna
        function generateInternalMessage($order, $items) {
            $message = "Envio: {$order['shipping_type']}\n\n";
            $message .= "Nome: {$order['customer_name']}\n";
            if (!empty($order['cpf'])) $message .= "CPF: {$order['cpf']}\n";
            if (!empty($order['email'])) $message .= "E-mail: {$order['email']}\n";
            if (!empty($order['phone'])) $message .= "Telefone: {$order['phone']}\n";
            
            // Endereço completo
            if (!empty($order['address'])) {
                $message .= "\nENDEREÇO:\n";
                $message .= "{$order['address']}";
                if (!empty($order['number'])) $message .= ", {$order['number']}";
                if (!empty($order['complement'])) $message .= " - {$order['complement']}";
                $message .= "\n";
                
                if (!empty($order['neighborhood'])) $message .= "{$order['neighborhood']}, ";
                if (!empty($order['city'])) $message .= "{$order['city']}";
                if (!empty($order['state'])) $message .= "/{$order['state']}";
                if (!empty($order['cep'])) $message .= " - CEP: {$order['cep']}";
                $message .= "\n";
            }
            
            // Valor do frete
            if (!empty($order['shipping_value']) && $order['shipping_value'] > 0) {
                $message .= "\nFRETE: R$ " . number_format($order['shipping_value'], 2, ',', '.') . "\n";
            }
            
            // Produtos
            $message .= "\nPRODUTOS:\n";
            $totalCost = 0;
            
            foreach ($items as $item) {
                $cost = !empty($item['cost']) ? $item['cost'] : ($item['current_cost'] ?? 0);
                $quantity = (int)$item['quantity'];
                $subtotalCost = $cost * $quantity;
                $totalCost += $subtotalCost;
                
                $productName = $item['product_name'];
                if (!empty($item['brand'])) {
                    $productName .= " ({$item['brand']})";
                }
                
                $message .= "{$quantity}x {$productName} - R$ " . number_format($subtotalCost, 2, ',', '.') . "\n";
            }
            
            // Total
            $message .= "\nTOTAL CUSTO: R$ " . number_format($totalCost, 2, ',', '.') . "\n";
            
            // Observações administrativas
            if (!empty($order['admin_comments'])) {
                $message .= "\nOBSERVAÇÕES:\n{$order['admin_comments']}\n";
            }
            
            return $message;
        }
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                Pedido #<?= $order['id'] ?>
                <span class="badge <?= getStatusClass($order['status']) ?> ms-2"><?= $order['status'] ?></span>
            </h2>
            
            <div>
                <a href="index.php?page=pedidos" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
                
                <div class="btn-group ms-2">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-cog me-1"></i> Ações
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#statusModal">
                                <i class="fas fa-edit me-1"></i> Alterar Status
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="fas fa-plus me-1"></i> Adicionar Item
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#trackingModal">
                                <i class="fas fa-truck me-1"></i> Atualizar Rastreio
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#valuesModal">
                                <i class="fas fa-dollar-sign me-1"></i> Ajustar Valores
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="index.php?page=pedidos&action=delete&id=<?= $order['id'] ?>">
                                <i class="fas fa-trash-alt me-1"></i> Excluir Pedido
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-lg-8">
                <!-- Resumo do Pedido -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Resumo do Pedido</h5>
                        <span class="text-muted small">Criado em: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Cliente:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                                
                                <?php if (!empty($order['email'])): ?>
                                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($order['phone'])): ?>
                                <p class="mb-1"><strong>Telefone:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($order['cpf'])): ?>
                                <p class="mb-1"><strong>CPF:</strong> <?= htmlspecialchars($order['cpf']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1">
                                    <strong>Status:</strong> 
                                    <span class="badge <?= getStatusClass($order['status']) ?>"><?= $order['status'] ?></span>
                                </p>
                                
                                <p class="mb-1">
                                    <strong>Código de Rastreio:</strong> 
                                    <?php if (!empty($order['tracking_code'])): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($order['tracking_code']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Sem rastreio</span>
                                    <?php endif; ?>
                                </p>
                                
                                <?php if (!empty($order['payment_method'])): ?>
                                <p class="mb-1"><strong>Pagamento:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($order['shipping_type'])): ?>
                                <p class="mb-1"><strong>Tipo de Envio:</strong> <?= htmlspecialchars($order['shipping_type']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['address'])): ?>
                        <hr>
                        
                        <div class="row">
                            <div class="col-12">
                                <h6>Endereço de Entrega</h6>
                                <address>
                                    <?= htmlspecialchars($order['address']) ?>
                                    <?php if (!empty($order['number'])): ?>, <?= htmlspecialchars($order['number']) ?><?php endif; ?>
                                    <?php if (!empty($order['complement'])): ?><br><?= htmlspecialchars($order['complement']) ?><?php endif; ?>
                                    <?php if (!empty($order['neighborhood'])): ?><br><?= htmlspecialchars($order['neighborhood']) ?><?php endif; ?>
                                    <?php if (!empty($order['city']) || !empty($order['state'])): ?><br><?= htmlspecialchars($order['city']) ?> - <?= htmlspecialchars($order['state']) ?><?php endif; ?>
                                    <?php if (!empty($order['cep'])): ?><br>CEP: <?= htmlspecialchars($order['cep']) ?><?php endif; ?>
                                </address>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Itens do Pedido -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Itens do Pedido</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="fas fa-plus me-1"></i> Adicionar Item
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" width="40%">Produto</th>
                                        <th scope="col" class="text-center">Qtd</th>
                                        <th scope="col" class="text-end">Preço Unit.</th>
                                        <th scope="col" class="text-end">Custo Unit.</th>
                                        <th scope="col" class="text-end">Subtotal</th>
                                        <th scope="col" class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3 text-muted">
                                            Nenhum item encontrado para este pedido
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $item): 
                                            $lucroItem = $item['subtotal'] - ($item['cost'] * $item['quantity']);
                                            $currentCost = !empty($item['current_cost']) ? $item['current_cost'] : $item['cost'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                                    <?php if (!empty($item['brand'])): ?>
                                                        <div class="text-muted small"><?= htmlspecialchars($item['brand']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center"><?= $item['quantity'] ?></td>
                                            <td class="text-end">R$ <?= number_format($item['price'], 2, ',', '.') ?></td>
                                            <td class="text-end position-relative">
                                                R$ <?= number_format($item['cost'], 2, ',', '.') ?>
                                                <?php if (!empty($currentCost) && $currentCost != $item['cost']): ?>
                                                <div class="position-absolute top-0 end-0 mt-1 me-2">
                                                    <span class="badge bg-info" title="Custo atual no estoque">
                                                        <i class="fas fa-tag"></i>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-bold">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></span>
                                                <div class="small <?= $lucroItem >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= $lucroItem >= 0 ? '+' : '' ?>R$ <?= number_format($lucroItem, 2, ',', '.') ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <form method="POST" onsubmit="return confirm('Remover este item do pedido?')">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="subtotal" value="<?= $item['subtotal'] ?>">
                                                    <input type="hidden" name="cost_total" value="<?= $item['cost'] * $item['quantity'] ?>">
                                                    <button type="submit" name="remove_item" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">R$ <?= number_format($order['total'], 2, ',', '.') ?></td>
                                        <td></td>
                                    </tr>
                                    <?php if (!empty($order['discount_value']) && $order['discount_value'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Desconto:</strong></td>
                                        <td class="text-end">- R$ <?= number_format($order['discount_value'], 2, ',', '.') ?></td>
                                        <td></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($order['shipping_value']) && $order['shipping_value'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Frete:</strong></td>
                                        <td class="text-end">R$ <?= number_format($order['shipping_value'], 2, ',', '.') ?></td>
                                        <td></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($order['insurance_value']) && $order['insurance_value'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Seguro:</strong></td>
                                        <td class="text-end">R$ <?= number_format($order['insurance_value'], 2, ',', '.') ?></td>
                                        <td></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Valor Final:</strong></td>
                                        <td class="text-end fw-bold fs-5">R$ <?= number_format($order['final_value'], 2, ',', '.') ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Informações Internas/Custo -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Mensagem Interna (Somente Custo)</h5>
                        <button class="btn btn-sm btn-outline-primary" id="copyInternalBtn">
                            <i class="fas fa-copy me-1"></i> Copiar
                        </button>
                    </div>
                    <div class="card-body">
                        <textarea id="internalMessage" class="form-control font-monospace" rows="10" readonly><?= generateInternalMessage($order, $items) ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Coluna Direita -->
            <div class="col-lg-4">
                <!-- Card Status -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Status do Pedido</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="old_status" value="<?= $order['status'] ?>">
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Alterar Status:</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="PENDENTE" <?= $order['status'] == 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="CONFIRMADO" <?= $order['status'] == 'CONFIRMADO' ? 'selected' : '' ?>>Confirmado</option>
                                    <option value="EM PROCESSO" <?= $order['status'] == 'EM PROCESSO' ? 'selected' : '' ?>>Em Processo</option>
                                    <option value="CONCLUIDO" <?= $order['status'] == 'CONCLUIDO' ? 'selected' : '' ?>>Concluído</option>
                                    <option value="CANCELADO" <?= $order['status'] == 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                            
                            <?php if ($order['status'] == 'CANCELADO'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Atenção:</strong> Este pedido está cancelado e não é contabilizado nas métricas financeiras.
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" name="update_status" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i> Atualizar Status
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Card Rastreio -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Código de Rastreio</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            
                            <div class="mb-3">
                                <label for="tracking_code" class="form-label">Código de Rastreio:</label>
                                <input type="text" name="tracking_code" id="tracking_code" class="form-control" value="<?= htmlspecialchars($order['tracking_code'] ?? '') ?>">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="update_tracking" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar Rastreio
                                </button>
                                
                                <?php if (!empty($order['tracking_code']) && !empty($order['phone'])): ?>
                                <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $order['phone']) ?>&text=<?= urlencode("Olá {$order['customer_name']}, o código de rastreio do seu pedido é: {$order['tracking_code']}") ?>" target="_blank" class="btn btn-success">
                                    <i class="fab fa-whatsapp me-1"></i> Enviar por WhatsApp
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Card Métricas Financeiras -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Métricas Financeiras</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Valor Total:</span>
                            <span class="fw-bold">R$ <?= number_format($order['final_value'], 2, ',', '.') ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Custo Total:</span>
                            <span class="fw-bold">R$ <?= number_format($order['cost_total'], 2, ',', '.') ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Lucro:</span>
                            <span class="fw-bold <?= $lucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                R$ <?= number_format($lucro, 2, ',', '.') ?>
                            </span>
                        </div>
                        
                        <div class="progress mb-2" style="height: 20px;">
                            <?php if ($margemLucro >= 0): ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, max(0, $margemLucro)) ?>%">
                                <?= number_format($margemLucro, 1, ',', '.') ?>%
                            </div>
                            <?php else: ?>
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?= min(100, max(0, abs($margemLucro))) ?>%">
                                <?= number_format($margemLucro, 1, ',', '.') ?>%
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-center text-muted small">Margem de Lucro</div>
                        
                        <hr>
                        
                        <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#valuesModal">
                            <i class="fas fa-edit me-1"></i> Ajustar Valores
                        </button>
                    </div>
                </div>
                
                <!-- Card Observações Administrativas -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Observações Administrativas</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            
                            <div class="mb-3">
                                <textarea name="admin_comments" rows="4" class="form-control" placeholder="Adicione observações internas aqui..."><?= htmlspecialchars($order['admin_comments'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" name="update_comments" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i> Salvar Observações
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Card Comprovante (se houver) -->
                <?php if (!empty($order['comprovante_url'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Comprovante de Pagamento</h5>
                    </div>
                    <div class="card-body text-center">
                        <a href="<?= htmlspecialchars($order['comprovante_url']) ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-file-alt me-1"></i> Ver Comprovante
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Modal de Status -->
        <div class="modal fade" id="statusModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Alterar Status do Pedido</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="old_status" value="<?= $order['status'] ?>">
                            
                            <div class="mb-3">
                                <label for="modal_status" class="form-label">Novo Status:</label>
                                <select name="status" id="modal_status" class="form-select">
                                    <option value="PENDENTE" <?= $order['status'] == 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="CONFIRMADO" <?= $order['status'] == 'CONFIRMADO' ? 'selected' : '' ?>>Confirmado</option>
                                    <option value="EM PROCESSO" <?= $order['status'] == 'EM PROCESSO' ? 'selected' : '' ?>>Em Processo</option>
                                    <option value="CONCLUIDO" <?= $order['status'] == 'CONCLUIDO' ? 'selected' : '' ?>>Concluído</option>
                                    <option value="CANCELADO" <?= $order['status'] == 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                            
                            <?php if ($order['status'] != 'CANCELADO'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Atenção:</strong> Ao marcar como "Cancelado", o pedido não será mais contabilizado nas métricas financeiras.
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal de Rastreio -->
        <div class="modal fade" id="trackingModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Atualizar Código de Rastreio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            
                            <div class="mb-3">
                                <label for="modal_tracking" class="form-label">Código de Rastreio:</label>
                                <input type="text" name="tracking_code" id="modal_tracking" class="form-control" value="<?= htmlspecialchars($order['tracking_code'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="submit" name="update_tracking" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal de Valores -->
        <div class="modal fade" id="valuesModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajustar Valores do Pedido</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Use esta opção para ajustar manualmente os valores totais do pedido, por exemplo, em caso de negociação especial com cliente.
                            </div>
                            
                            <div class="mb-3">
                                <label for="final_value" class="form-label">Valor Total (R$):</label>
                                <input type="text" name="final_value" id="final_value" class="form-control money" value="<?= number_format($order['final_value'], 2, ',', '.') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="cost_total" class="form-label">Custo Total (R$):</label>
                                <input type="text" name="cost_total" id="cost_total" class="form-control money" value="<?= number_format($order['cost_total'], 2, ',', '.') ?>">
                            </div>
                            
                            <div id="profit_preview" class="alert alert-success">
                                Lucro Estimado: <strong>R$ <?= number_format($lucro, 2, ',', '.') ?></strong>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="submit" name="update_values" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal Adicionar Item -->
        <div class="modal fade" id="addItemModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Adicionar Item ao Pedido</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="row">
                                <div class="col">
                                    <label class="form-label">Selecione um Produto:</label>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="manualItemCheck">
                                        <label class="form-check-label" for="manualItemCheck">
                                            Item Manual
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Busca de Produtos -->
                            <div id="productSearchContainer">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="productSearch" placeholder="Buscar produto...">
                                    <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover table-sm" id="productsTable">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Produto</th>
                                                <th>Marca</th>
                                                <th>Preço</th>
                                                <th>Custo</th>
                                                <th>Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                            <tr data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" 
                                                data-brand="<?= htmlspecialchars($product['brand_name'] ?? '') ?>" 
                                                data-price="<?= number_format($product['price'], 2, ',', '') ?>" 
                                                data-cost="<?= number_format($product['cost'], 2, ',', '') ?>">
                                                <td><?= htmlspecialchars($product['name']) ?></td>
                                                <td><?= htmlspecialchars($product['brand_name'] ?? '') ?></td>
                                                <td>R$ <?= number_format($product['price'], 2, ',', '.') ?></td>
                                                <td>R$ <?= number_format($product['cost'], 2, ',', '.') ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary select-product">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($products)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-3">
                                                    <i class="far fa-frown me-2"></i> Nenhum produto encontrado
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" id="addItemForm">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="product_id" id="product_id" value="0">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="product_name" class="form-label">Nome do Produto</label>
                                    <input type="text" class="form-control" id="product_name" name="product_name" readonly required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="brand" class="form-label">Marca</label>
                                    <input type="text" class="form-control" id="brand" name="brand" readonly>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="quantity" class="form-label">Quantidade</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="price" class="form-label">Preço Unit. (R$)</label>
                                    <input type="text" class="form-control money" id="price" name="price" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="cost" class="form-label">Custo Unit. (R$)</label>
                                    <input type="text" class="form-control money" id="cost" name="cost" required>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal (Preço):</span>
                                    <strong id="subtotal_preview">R$ 0,00</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal (Custo):</span>
                                    <strong id="cost_preview">R$ 0,00</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Lucro Estimado:</span>
                                    <strong id="profit_item_preview">R$ 0,00</strong>
                                </div>
                            </div>
                        
                            <div class="mt-3 d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" name="add_item" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> Adicionar Item
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Função para copiar a mensagem interna
        document.getElementById('copyInternalBtn').addEventListener('click', function() {
            const textArea = document.getElementById('internalMessage');
            textArea.select();
            document.execCommand('copy');
            
            // Feedback visual
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check me-1"></i> Copiado!';
            
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 2000);
        });
        
        // Inicialização das máscaras de moeda
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.money').forEach(function(input) {
                IMask(input, {
                    mask: Number,
                    scale: 2,
                    signed: false,
                    thousandsSeparator: '.',
                    padFractionalZeros: true,
                    normalizeZeros: true,
                    radix: ','
                });
            });
            
            // Calcular lucro estimado em tempo real
            const updateProfitPreview = function() {
                const finalValue = parseFloat(document.getElementById('final_value').value.replace(/\./g, '').replace(',', '.')) || 0;
                const costTotal = parseFloat(document.getElementById('cost_total').value.replace(/\./g, '').replace(',', '.')) || 0;
                const profit = finalValue - costTotal;
                
                const profitPreview = document.getElementById('profit_preview');
                profitPreview.innerHTML = `Lucro Estimado: <strong>R$ ${profit.toFixed(2).replace('.', ',')}</strong>`;
                profitPreview.className = `alert ${profit >= 0 ? 'alert-success' : 'alert-danger'}`;
            };
            
            document.getElementById('final_value').addEventListener('input', updateProfitPreview);
            document.getElementById('cost_total').addEventListener('input', updateProfitPreview);
            
            // Toggle para item manual
            const manualItemCheck = document.getElementById('manualItemCheck');
            const productSearchContainer = document.getElementById('productSearchContainer');
            const productNameInput = document.getElementById('product_name');
            const brandInput = document.getElementById('brand');
            
            manualItemCheck.addEventListener('change', function() {
                if (this.checked) {
                    productSearchContainer.style.display = 'none';
                    productNameInput.readOnly = false;
                    brandInput.readOnly = false;
                    productNameInput.value = '';
                    brandInput.value = '';
                    document.getElementById('product_id').value = '0';
                } else {
                    productSearchContainer.style.display = 'block';
                    productNameInput.readOnly = true;
                    brandInput.readOnly = true;
                }
            });
            
            // Busca de produtos
            document.getElementById('productSearch').addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#productsTable tbody tr');
                
                rows.forEach(row => {
                    const productName = row.querySelector('td:first-child').textContent.toLowerCase();
                    const brandName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (productName.includes(searchTerm) || brandName.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Seleção de produto
            document.querySelectorAll('.select-product').forEach(btn => {
                btn.addEventListener('click', function() {
                    const row = this.closest('tr');
                    
                    document.getElementById('product_id').value = row.dataset.id;
                    document.getElementById('product_name').value = row.dataset.name;
                    document.getElementById('brand').value = row.dataset.brand;
                    document.getElementById('price').value = row.dataset.price;
                    document.getElementById('cost').value = row.dataset.cost;
                    
                    // Calcular previews
                    updateItemPreviews();
                });
            });
            
            // Calcular previews do item
            const updateItemPreviews = function() {
                const quantity = parseInt(document.getElementById('quantity').value) || 0;
                const price = parseFloat(document.getElementById('price').value.replace(/\./g, '').replace(',', '.')) || 0;
                const cost = parseFloat(document.getElementById('cost').value.replace(/\./g, '').replace(',', '.')) || 0;
                
                const subtotal = quantity * price;
                const subtotalCost = quantity * cost;
                const profit = subtotal - subtotalCost;
                
                document.getElementById('subtotal_preview').textContent = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
                document.getElementById('cost_preview').textContent = `R$ ${subtotalCost.toFixed(2).replace('.', ',')}`;
                
                const profitPreview = document.getElementById('profit_item_preview');
                profitPreview.textContent = `R$ ${profit.toFixed(2).replace('.', ',')}`;
                profitPreview.className = profit >= 0 ? 'text-success' : 'text-danger';
            };
            
            document.getElementById('quantity').addEventListener('input', updateItemPreviews);
            document.getElementById('price').addEventListener('input', updateItemPreviews);
            document.getElementById('cost').addEventListener('input', updateItemPreviews);
            
            // Validação do formulário de adição de item
            document.getElementById('addItemForm').addEventListener('submit', function(e) {
                const productName = document.getElementById('product_name').value.trim();
                const quantity = parseInt(document.getElementById('quantity').value) || 0;
                const price = parseFloat(document.getElementById('price').value.replace(/\./g, '').replace(',', '.')) || 0;
                
                if (productName === '') {
                    e.preventDefault();
                    alert('Por favor, selecione ou informe um produto.');
                    return;
                }
                
                if (quantity <= 0) {
                    e.preventDefault();
                    alert('A quantidade deve ser maior que zero.');
                    return;
                }
                
                if (price <= 0) {
                    e.preventDefault();
                    alert('O preço deve ser maior que zero.');
                    return;
                }
            });
        });
        </script>
        <?php
        break;
        
    default:
        // Listagem de pedidos (lista principal)
        // Gerar condições WHERE para filtros
        $where = [];
        $params = [];
        
        if ($status) {
            $where[] = "status = :status";
            $params[':status'] = $status;
        }
        
        if ($nome) {
            $where[] = "customer_name LIKE :nome";
            $params[':nome'] = "%" . $nome . "%";
        }
        
        if ($dataInicio && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
            $where[] = "DATE(created_at) >= :data_inicio";
            $params[':data_inicio'] = $dataInicio;
        }
        
        if ($dataFim && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
            $where[] = "DATE(created_at) <= :data_fim";
            $params[':data_fim'] = $dataFim;
        }
        
        $whereStr = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        
        // Obter contagem total para paginação
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders" . $whereStr);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value);
        }
        $stmtCount->execute();
        $totalRecords = $stmtCount->fetchColumn();
        
        // Configurar paginação
        $perPage = 20;
        $currentPage = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $offset = ($currentPage - 1) * $perPage;
        $totalPages = ceil($totalRecords / $perPage);
        
        // Buscar pedidos
        $sql = "SELECT id, customer_name, final_value, cost_total, status, 
                       tracking_code, created_at, updated_at
                FROM orders
                $whereStr
                ORDER BY id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Função para gerar URLs de paginação preservando os filtros
        function getPaginationUrl($page) {
            $params = $_GET;
            $params['pagina'] = $page;
            return 'index.php?' . http_build_query($params);
        }
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Gerenciamento de Pedidos</h2>
            
            <div>
                <a href="index.php?page=pedidos&action=new" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i> Novo Pedido Manual
                </a>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="pedidos">
                    
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Todos os status</option>
                            <option value="PENDENTE" <?= $status == 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                            <option value="CONFIRMADO" <?= $status == 'CONFIRMADO' ? 'selected' : '' ?>>Confirmado</option>
                            <option value="EM PROCESSO" <?= $status == 'EM PROCESSO' ? 'selected' : '' ?>>Em Processo</option>
                            <option value="CONCLUIDO" <?= $status == 'CONCLUIDO' ? 'selected' : '' ?>>Concluído</option>
                            <option value="CANCELADO" <?= $status == 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="nome" class="form-label">Nome do Cliente</label>
                        <input type="text" name="nome" id="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>" placeholder="Busca por nome...">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data Início</label>
                        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?= htmlspecialchars($dataInicio) ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Data Fim</label>
                        <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?= htmlspecialchars($dataFim) ?>">
                    </div>
                    
                    <div class="col-12 d-flex justify-content-end">
                        <a href="index.php?page=pedidos" class="btn btn-secondary me-2">
                            <i class="fas fa-eraser me-1"></i> Limpar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabela de resultados -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Lista de Pedidos</h5>
                <span class="badge bg-secondary"><?= $totalRecords ?> pedidos encontrados</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Nenhum pedido encontrado com os filtros atuais.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#ID</th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Total (R$)</th>
                                <th scope="col">Lucro (R$)</th>
                                <th scope="col">Status</th>
                                <th scope="col">Rastreio</th>
                                <th scope="col">Data</th>
                                <th scope="col" class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                $lucro = $order['final_value'] - $order['cost_total'];
                                $statusClass = getStatusClass($order['status']);
                            ?>
                            <tr>
                                <td><strong>#<?= $order['id'] ?></strong></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td>R$ <?= number_format($order['final_value'], 2, ',', '.') ?></td>
                                <td class="<?= $lucro >= 0 ? 'text-success' : 'text-danger' ?>">
                                    R$ <?= number_format($lucro, 2, ',', '.') ?>
                                </td>
                                <td><span class="badge <?= $statusClass ?>"><?= $order['status'] ?></span></td>
                                <td>
                                    <?php if (!empty($order['tracking_code'])): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-truck me-1"></i>
                                            <?= htmlspecialchars($order['tracking_code']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Sem rastreio</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?page=pedidos&action=detail&id=<?= $order['id'] ?>" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Visualizar Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="index.php?page=pedidos&action=delete&id=<?= $order['id'] ?>" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Excluir Pedido">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Paginação de pedidos">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= getPaginationUrl($currentPage - 1) ?>" aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $currentPage - 2);
                        $end = min($totalPages, $currentPage + 2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl(1) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++) {
                            $active = $i == $currentPage ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . getPaginationUrl($i) . '">' . $i . '</a></li>';
                        }
                        
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl($totalPages) . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= getPaginationUrl($currentPage + 1) ?>" aria-label="Próximo">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
        <?php
        break;
}
?>