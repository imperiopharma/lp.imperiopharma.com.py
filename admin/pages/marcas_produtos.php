<?php
/**************************************************************
 * admin/pages/marcas_produtos.php
 *
 * GERENCIAMENTO AVANÇADO DE MARCAS & PRODUTOS
 * Sistema completo e integrado para gestão de catálogo
 * com CRUD, edição em massa e análise de lucro em tempo real
 * 
 * Versão: 2.0 (Otimizada e ampliada)
 * 
 * Tabelas utilizadas:
 *   - brands: marcas e suas propriedades
 *   - products: produtos e valores associados
 * 
 * Desenvolvido para Império Pharma
 **************************************************************/

// Conexão com banco de dados (usando o arquivo config.php existente)
require_once(__DIR__ . '/../inc/config.php');

// Funções Auxiliares
function formatMoney($value) {
    return number_format($value, 2, ',', '.');
}

function formatProfit($value) {
    $class = $value >= 0 ? 'text-success' : 'text-danger';
    $sign = $value >= 0 ? '+' : '';
    return "<span class='$class'>$sign" . formatMoney($value) . "</span>";
}

function calculateProfitValue($price, $promo, $cost) {
    $price = floatval($price);
    $promo = floatval($promo);
    $cost = floatval($cost);
    
    $sellPrice = ($promo > 0) ? $promo : $price;
    return $sellPrice - $cost;
}

function calculateProfitPercent($price, $promo, $cost) {
    $price = floatval($price);
    $promo = floatval($promo);
    $cost = floatval($cost);
    
    if ($cost <= 0) return 0;
    
    $sellPrice = ($promo > 0) ? $promo : $price;
    if ($sellPrice <= 0) return 0;
    
    return (($sellPrice - $cost) / $sellPrice) * 100;
}

// Helper para validação de entrada
function validateInput($input, $type = 'string') {
    $input = trim($input);
    
    switch($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0;
        case 'bool':
            return filter_var($input, FILTER_VALIDATE_BOOLEAN);
        default: // string
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

// Gerenciamento de Mensagens de Notificação
$notifications = [];

function addNotification($message, $type = 'success') {
    global $notifications;
    $notifications[] = ['message' => $message, 'type' => $type];
}

// Qual aba está selecionada? (tab=marcas|produtos|mass_products|mass_brands|mass_prices)
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'marcas';

// Parâmetros de paginação
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$perPage = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;
$offset = ($page - 1) * $perPage;

// Parâmetros de filtragem
$filterBrandName = isset($_GET['filter_brand_name']) ? trim($_GET['filter_brand_name']) : '';
$filterBrandId = isset($_GET['filter_brand_id']) ? intval($_GET['filter_brand_id']) : 0;
$filterProductName = isset($_GET['filter_product_name']) ? trim($_GET['filter_product_name']) : '';
$filterCategory = isset($_GET['filter_category']) ? trim($_GET['filter_category']) : '';
$filterActive = isset($_GET['filter_active']) ? intval($_GET['filter_active']) : -1; // -1 = todos

//--------------------------------------------------------------------
// 1) AÇÕES PARA MARCAS
//--------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'marca') {
    $act = $_POST['act'] ?? '';

    // (a) Inserir Marca
    if ($act === 'add') {
        $slug = validateInput($_POST['slug'] ?? '');
        $name = validateInput($_POST['name'] ?? '');
        $brandType = validateInput($_POST['brand_type'] ?? '');
        $banner = validateInput($_POST['banner'] ?? '');
        $btnImg = validateInput($_POST['btn_image'] ?? '');
        $sortOrder = validateInput($_POST['sort_order'] ?? 0, 'int');
        $stock = validateInput($_POST['stock'] ?? 1, 'int');
        $stockMsg = validateInput($_POST['stock_message'] ?? '');

        if (empty($slug) || empty($name)) {
            addNotification('Slug e Nome são campos obrigatórios para cadastrar uma marca.', 'danger');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO brands 
                    (slug, name, brand_type, banner, btn_image, stock, stock_message, sort_order) 
                    VALUES (:slug, :name, :btype, :banner, :btn, :stock, :stockmsg, :order)
                ");
                
                $result = $stmt->execute([
                    ':slug' => $slug,
                    ':name' => $name,
                    ':btype' => $brandType,
                    ':banner' => $banner,
                    ':btn' => $btnImg,
                    ':stock' => $stock,
                    ':stockmsg' => $stockMsg,
                    ':order' => $sortOrder
                ]);
                
                if ($result) {
                    $newId = $pdo->lastInsertId();
                    addNotification("Marca <strong>$name</strong> inserida com sucesso! (ID: $newId)");
                } else {
                    addNotification("Erro ao inserir marca. Verifique os dados e tente novamente.", 'danger');
                }
            } catch (PDOException $e) {
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (b) Editar Marca
    elseif ($act === 'edit') {
        $id = validateInput($_POST['id'] ?? 0, 'int');
        $slug = validateInput($_POST['slug'] ?? '');
        $name = validateInput($_POST['name'] ?? '');
        $brandType = validateInput($_POST['brand_type'] ?? '');
        $banner = validateInput($_POST['banner'] ?? '');
        $btnImg = validateInput($_POST['btn_image'] ?? '');
        $sortOrder = validateInput($_POST['sort_order'] ?? 0, 'int');
        $stock = validateInput($_POST['stock'] ?? 1, 'int');
        $stockMsg = validateInput($_POST['stock_message'] ?? '');

        if ($id <= 0 || empty($slug) || empty($name)) {
            addNotification('ID inválido ou campos obrigatórios não preenchidos.', 'danger');
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE brands
                    SET slug = :slug,
                        name = :name,
                        brand_type = :btype,
                        banner = :banner,
                        btn_image = :btn,
                        stock = :stock,
                        stock_message = :stockmsg,
                        sort_order = :order
                    WHERE id = :id
                ");
                
                $result = $stmt->execute([
                    ':slug' => $slug,
                    ':name' => $name,
                    ':btype' => $brandType,
                    ':banner' => $banner,
                    ':btn' => $btnImg,
                    ':stock' => $stock,
                    ':stockmsg' => $stockMsg,
                    ':order' => $sortOrder,
                    ':id' => $id
                ]);
                
                if ($result) {
                    addNotification("Marca <strong>$name</strong> atualizada com sucesso!");
                } else {
                    addNotification("Erro ao atualizar marca. Verifique os dados e tente novamente.", 'danger');
                }
            } catch (PDOException $e) {
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (c) Excluir Marca
    elseif ($act === 'delete') {
        $id = validateInput($_POST['id'] ?? 0, 'int');
        
        if ($id <= 0) {
            addNotification('ID inválido para exclusão.', 'danger');
        } else {
            try {
                // Verificar se a marca tem produtos associados
                $checkProducts = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
                $checkProducts->execute([$id]);
                $hasProducts = $checkProducts->fetchColumn() > 0;
                
                if ($hasProducts) {
                    addNotification("Não é possível excluir esta marca pois existem produtos associados a ela. Remova os produtos primeiro ou reatribua-os a outra marca.", 'warning');
                } else {
                    // Obter nome da marca antes de excluir (para mensagem)
                    $brandStmt = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
                    $brandStmt->execute([$id]);
                    $brandName = $brandStmt->fetchColumn() ?: "ID: $id";
                    
                    $deleteStmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
                    $result = $deleteStmt->execute([$id]);
                    
                    if ($result) {
                        addNotification("Marca <strong>$brandName</strong> excluída com sucesso!");
                    } else {
                        addNotification("Erro ao excluir marca. Tente novamente.", 'danger');
                    }
                }
            } catch (PDOException $e) {
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (d) Edição em Massa de Marcas
    elseif ($act === 'mass_update_brands') {
        $massArr = $_POST['mass_brands'] ?? [];
        
        if (!is_array($massArr) || empty($massArr)) {
            addNotification("Nenhuma marca selecionada para edição em massa!", 'warning');
        } else {
            try {
                $pdo->beginTransaction();
                $countUpdated = 0;
                $errors = 0;
                
                foreach ($massArr as $brandId => $fields) {
                    $brandId = (int)$brandId;
                    if ($brandId <= 0) continue;
                    
                    $slug = validateInput($fields['slug'] ?? '');
                    $name = validateInput($fields['name'] ?? '');
                    $brandType = validateInput($fields['brand_type'] ?? '');
                    $stock = validateInput($fields['stock'] ?? 1, 'int');
                    $sortOrder = validateInput($fields['sort_order'] ?? 0, 'int');
                    $stockMsg = validateInput($fields['stock_message'] ?? '');
                    
                    if (empty($slug) || empty($name)) {
                        $errors++;
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE brands
                        SET slug = :slug,
                            name = :name,
                            brand_type = :btype,
                            stock = :stock,
                            stock_message = :stockmsg,
                            sort_order = :order
                        WHERE id = :id
                    ");
                    
                    $success = $stmt->execute([
                        ':slug' => $slug,
                        ':name' => $name,
                        ':btype' => $brandType,
                        ':stock' => $stock,
                        ':stockmsg' => $stockMsg,
                        ':order' => $sortOrder,
                        ':id' => $brandId
                    ]);
                    
                    if ($success) {
                        $countUpdated++;
                    } else {
                        $errors++;
                    }
                }
                
                if ($errors == 0) {
                    $pdo->commit();
                    addNotification("Edição em massa concluída com sucesso. <strong>$countUpdated</strong> marcas atualizadas.");
                } else {
                    $pdo->commit(); // Ainda commitamos as mudanças bem-sucedidas
                    addNotification("Edição em massa concluída, mas com $errors erros. $countUpdated marcas foram atualizadas.", 'warning');
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }
}

//--------------------------------------------------------------------
// 2) AÇÕES PARA PRODUTOS
//--------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'produto') {
    $act = $_POST['act'] ?? '';

    // (a) Inserir Produto
    if ($act === 'add') {
        $brandId = validateInput($_POST['brand_id'] ?? 0, 'int');
        $name = validateInput($_POST['name'] ?? '');
        $description = validateInput($_POST['description'] ?? '');
        $price = validateInput($_POST['price'] ?? 0, 'float');
        $promoPrice = validateInput($_POST['promo_price'] ?? 0, 'float');
        $cost = validateInput($_POST['cost'] ?? 0, 'float');
        $category = validateInput($_POST['category'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $imageUrl = validateInput($_POST['image_url'] ?? '');
        
        if (empty($name)) {
            addNotification("Nome do produto é obrigatório.", 'danger');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO products 
                    (brand_id, name, description, price, promo_price, cost, category, active, image_url)
                    VALUES (:brand, :name, :descr, :price, :promo, :cost, :cat, :active, :img)
                ");
                
                $result = $stmt->execute([
                    ':brand' => $brandId,
                    ':name' => $name,
                    ':descr' => $description,
                    ':price' => $price,
                    ':promo' => $promoPrice,
                    ':cost' => $cost,
                    ':cat' => $category,
                    ':active' => $active,
                    ':img' => $imageUrl
                ]);
                
                if ($result) {
                    $newId = $pdo->lastInsertId();
                    
                    // Calcular e exibir lucro na mensagem
                    $profit = calculateProfitValue($price, $promoPrice, $cost);
                    $profitPercent = calculateProfitPercent($price, $promoPrice, $cost);
                    $profitFormatted = formatMoney($profit);
                    $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
                    
                    addNotification("Produto <strong>$name</strong> inserido com sucesso! 
                        <div class='mt-1 small'>Lucro estimado: <span class='$profitClass'>R$ $profitFormatted</span> 
                        (" . number_format($profitPercent, 1) . "%)</div>");
                } else {
                    addNotification("Erro ao inserir produto. Verifique os dados e tente novamente.", 'danger');
                }
            } catch (PDOException $e) {
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (b) Editar Produto
    elseif ($act === 'edit') {
        $id = validateInput($_POST['id'] ?? 0, 'int');
        $brandId = validateInput($_POST['brand_id'] ?? 0, 'int');
        $name = validateInput($_POST['name'] ?? '');
        $description = validateInput($_POST['description'] ?? '');
        $price = validateInput($_POST['price'] ?? 0, 'float');
        $promoPrice = validateInput($_POST['promo_price'] ?? 0, 'float');
        $cost = validateInput($_POST['cost'] ?? 0, 'float');
        $category = validateInput($_POST['category'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $imageUrl = validateInput($_POST['image_url'] ?? '');
        
        if ($id <= 0 || empty($name)) {
            addNotification("ID inválido ou nome em branco.", 'danger');
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE products
                    SET brand_id = :brand,
                        name = :name,
                        description = :descr,
                        price = :price,
                        promo_price = :promo,
                        cost = :cost,
                        category = :cat,
                        active = :active,
                        image_url = :img
                    WHERE id = :id
                ");
                
                $result = $stmt->execute([
                    ':brand' => $brandId,
                    ':name' => $name,
                    ':descr' => $description,
                    ':price' => $price,
                    ':promo' => $promoPrice,
                    ':cost' => $cost,
                    ':cat' => $category,
                    ':active' => $active,
                    ':img' => $imageUrl,
                    ':id' => $id
                ]);
                
                if ($result) {
                    // Calcular e exibir lucro na mensagem
                    $profit = calculateProfitValue($price, $promoPrice, $cost);
                    $profitPercent = calculateProfitPercent($price, $promoPrice, $cost);
                    $profitFormatted = formatMoney($profit);
                    $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
                    
                    addNotification("Produto <strong>$name</strong> atualizado com sucesso! 
                        <div class='mt-1 small'>Lucro estimado: <span class='$profitClass'>R$ $profitFormatted</span> 
                        (" . number_format($profitPercent, 1) . "%)</div>");
                } else {
                    addNotification("Erro ao atualizar produto. Verifique os dados e tente novamente.", 'danger');
                }
            } catch (PDOException $e) {
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (c) Excluir Produto
    elseif ($act === 'delete') {
        $id = validateInput($_POST['id'] ?? 0, 'int');
        
        if ($id <= 0) {
            addNotification("ID inválido para exclusão.", 'danger');
        } else {
            try {
                // Obtém informações do produto antes de excluir
                $productStmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $productStmt->execute([$id]);
                $productName = $productStmt->fetchColumn() ?: "ID: $id";
                
                // Verificar se há pedidos com este produto (verificação de integridade)
                $checkOrders = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
                $checkOrders->execute([$id]);
                $hasOrders = $checkOrders->fetchColumn() > 0;
                
                if ($hasOrders) {
                    addNotification("Não é possível excluir o produto <strong>$productName</strong> pois ele está associado a pedidos existentes.", 'warning');
                } else {
                    $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    $result = $deleteStmt->execute([$id]);
                    
                    if ($result) {
                        addNotification("Produto <strong>$productName</strong> excluído com sucesso!");
                    } else {
                        addNotification("Erro ao excluir produto. Tente novamente.", 'danger');
                    }
                }
            } catch (PDOException $e) {
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (d) Edição em Massa de Produtos
    elseif ($act === 'mass_update') {
        $massProducts = $_POST['mass'] ?? [];
        
        if (!is_array($massProducts) || empty($massProducts)) {
            addNotification("Nenhum produto selecionado para edição em massa!", 'warning');
        } else {
            try {
                $pdo->beginTransaction();
                $countUpdated = 0;
                $errors = 0;
                
                foreach ($massProducts as $productId => $fields) {
                    $productId = (int)$productId;
                    if ($productId <= 0) continue;
                    
                    $brandId = validateInput($fields['brand_id'] ?? 0, 'int');
                    $price = validateInput($fields['price'] ?? 0, 'float');
                    $promoPrice = validateInput($fields['promo_price'] ?? 0, 'float');
                    $cost = validateInput($fields['cost'] ?? 0, 'float');
                    $active = isset($fields['active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE products
                        SET brand_id = :brand,
                            price = :price,
                            promo_price = :promo,
                            cost = :cost,
                            active = :active
                        WHERE id = :id
                    ");
                    
                    $success = $stmt->execute([
                        ':brand' => $brandId,
                        ':price' => $price,
                        ':promo' => $promoPrice,
                        ':cost' => $cost,
                        ':active' => $active,
                        ':id' => $productId
                    ]);
                    
                    if ($success) {
                        $countUpdated++;
                    } else {
                        $errors++;
                    }
                }
                
                if ($errors == 0) {
                    $pdo->commit();
                    addNotification("Edição em massa concluída com sucesso. <strong>$countUpdated</strong> produtos atualizados.");
                } else {
                    $pdo->commit(); // Ainda commitamos as mudanças bem-sucedidas
                    addNotification("Edição em massa concluída, mas com $errors erros. $countUpdated produtos foram atualizados.", 'warning');
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (e) Inserir Múltiplos Produtos em Massa
    elseif ($act === 'mass_add') {
        $massNewProducts = $_POST['mass_new'] ?? [];
        
        if (!is_array($massNewProducts) || empty($massNewProducts)) {
            addNotification("Nenhum produto novo para inserir em massa.", 'warning');
        } else {
            try {
                $pdo->beginTransaction();
                $countInserted = 0;
                $errors = 0;
                
                foreach ($massNewProducts as $index => $fields) {
                    // Pular linhas vazias
                    if (empty(trim($fields['name'] ?? ''))) continue;
                    
                    $brandId = validateInput($fields['brand_id'] ?? 0, 'int');
                    $name = validateInput($fields['name'] ?? '');
                    $description = validateInput($fields['description'] ?? '');
                    $price = validateInput($fields['price'] ?? 0, 'float');
                    $promoPrice = validateInput($fields['promo_price'] ?? 0, 'float');
                    $cost = validateInput($fields['cost'] ?? 0, 'float');
                    $category = validateInput($fields['category'] ?? '');
                    $active = isset($fields['active']) ? 1 : 0;
                    $imageUrl = validateInput($fields['image_url'] ?? '');
                    
                    if (empty($name)) {
                        $errors++;
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO products 
                        (brand_id, name, description, price, promo_price, cost, category, active, image_url)
                        VALUES (:brand, :name, :descr, :price, :promo, :cost, :cat, :active, :img)
                    ");
                    
                    $success = $stmt->execute([
                        ':brand' => $brandId,
                        ':name' => $name,
                        ':descr' => $description,
                        ':price' => $price,
                        ':promo' => $promoPrice,
                        ':cost' => $cost,
                        ':cat' => $category,
                        ':active' => $active,
                        ':img' => $imageUrl
                    ]);
                    
                    if ($success) {
                        $countInserted++;
                    } else {
                        $errors++;
                    }
                }
                
                if ($errors == 0) {
                    $pdo->commit();
                    addNotification("Inserção em massa concluída com sucesso. <strong>$countInserted</strong> novos produtos inseridos.");
                } else {
                    $pdo->commit(); // Commitamos as inserções bem-sucedidas
                    addNotification("Inserção em massa concluída, mas com $errors erros. $countInserted produtos foram inseridos.", 'warning');
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }

    // (f) Edição em Massa de Valores/Preços
    elseif ($act === 'mass_update_prices') {
        $massPrices = $_POST['mass_prices'] ?? [];
        
        if (!is_array($massPrices) || empty($massPrices)) {
            addNotification("Nenhum produto selecionado para edição de valores!", 'warning');
        } else {
            try {
                $pdo->beginTransaction();
                $countUpdated = 0;
                $errors = 0;
                $totalProfitChange = 0;
                
                foreach ($massPrices as $productId => $fields) {
                    $productId = (int)$productId;
                    if ($productId <= 0) continue;
                    
                    // Obter valores originais para calcular mudança no lucro
                    $origStmt = $pdo->prepare("SELECT price, promo_price, cost FROM products WHERE id = ?");
                    $origStmt->execute([$productId]);
                    $origProduct = $origStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$origProduct) {
                        $errors++;
                        continue;
                    }
                    
                    $price = validateInput($fields['price'] ?? 0, 'float');
                    $promoPrice = validateInput($fields['promo_price'] ?? 0, 'float');
                    $cost = validateInput($fields['cost'] ?? 0, 'float');
                    
                    $stmt = $pdo->prepare("
                        UPDATE products
                        SET price = :price,
                            promo_price = :promo,
                            cost = :cost
                        WHERE id = :id
                    ");
                    
                    $success = $stmt->execute([
                        ':price' => $price,
                        ':promo' => $promoPrice,
                        ':cost' => $cost,
                        ':id' => $productId
                    ]);
                    
                    if ($success) {
                        $countUpdated++;
                        
                        // Calcular mudança no lucro
                        $oldProfit = calculateProfitValue($origProduct['price'], $origProduct['promo_price'], $origProduct['cost']);
                        $newProfit = calculateProfitValue($price, $promoPrice, $cost);
                        $profitChange = $newProfit - $oldProfit;
                        $totalProfitChange += $profitChange;
                    } else {
                        $errors++;
                    }
                }
                
                if ($errors == 0) {
                    $pdo->commit();
                    $changeMessage = '';
                    if ($totalProfitChange != 0) {
                        $changeClass = $totalProfitChange > 0 ? 'text-success' : 'text-danger';
                        $changeSign = $totalProfitChange > 0 ? '+' : '';
                        $changeMessage = "<div class='mt-1'>Impacto total no lucro: <span class='$changeClass'>$changeSign" . 
                                         formatMoney($totalProfitChange) . "</span></div>";
                    }
                    
                    addNotification("Valores atualizados com sucesso para <strong>$countUpdated</strong> produtos. $changeMessage");
                } else {
                    $pdo->commit();
                    addNotification("Valores atualizados, mas com $errors erros. $countUpdated produtos foram atualizados.", 'warning');
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                addNotification("Erro no banco de dados: " . $e->getMessage(), 'danger');
            }
        }
    }
}

//--------------------------------------------------------------------
// 3) CARREGAR LISTAS DE MARCAS E PRODUTOS
//--------------------------------------------------------------------

// 3.1) Marcas (para exibição em tabela)
$whereMarcas = [];
$paramsMarcas = [];

if (!empty($filterBrandName)) {
    $whereMarcas[] = "name LIKE :name";
    $paramsMarcas[':name'] = "%$filterBrandName%";
}

// Contar total (para paginação)
$countSql = "SELECT COUNT(*) FROM brands";
if (!empty($whereMarcas)) {
    $countSql .= " WHERE " . implode(' AND ', $whereMarcas);
}

try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($paramsMarcas);
    $totalBrands = $countStmt->fetchColumn();
    $totalBrandPages = ceil($totalBrands / $perPage);
} catch (PDOException $e) {
    $totalBrands = 0;
    $totalBrandPages = 1;
    addNotification("Erro ao contar marcas: " . $e->getMessage(), 'danger');
}

// Buscar marcas paginadas
$sqlMarcas = "SELECT * FROM brands";
if (!empty($whereMarcas)) {
    $sqlMarcas .= " WHERE " . implode(' AND ', $whereMarcas);
}
$sqlMarcas .= " ORDER BY sort_order ASC, name ASC LIMIT :offset, :limit";

try {
    $stmtMarcas = $pdo->prepare($sqlMarcas);
    $stmtMarcas->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtMarcas->bindValue(':limit', $perPage, PDO::PARAM_INT);
    
    foreach ($paramsMarcas as $key => $value) {
        $stmtMarcas->bindValue($key, $value);
    }
    
    $stmtMarcas->execute();
    $listaMarcas = $stmtMarcas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listaMarcas = [];
    addNotification("Erro ao listar marcas: " . $e->getMessage(), 'danger');
}

// 3.2) Para <select> de marcas (usado em todos os lugares)
try {
    $stmtBrandsSelect = $pdo->query("SELECT id, name FROM brands ORDER BY sort_order ASC, name ASC");
    $brandsSelect = $stmtBrandsSelect->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $brandsSelect = [];
    addNotification("Erro ao listar marcas para seleção: " . $e->getMessage(), 'danger');
}

// 3.3) Produtos
$whereProducts = [];
$paramsProducts = [];

if ($filterBrandId > 0) {
    $whereProducts[] = "p.brand_id = :brand_id";
    $paramsProducts[':brand_id'] = $filterBrandId;
}

if (!empty($filterProductName)) {
    $whereProducts[] = "p.name LIKE :product_name";
    $paramsProducts[':product_name'] = "%$filterProductName%";
}

if (!empty($filterCategory)) {
    $whereProducts[] = "p.category LIKE :category";
    $paramsProducts[':category'] = "%$filterCategory%";
}

if ($filterActive != -1) {
    $whereProducts[] = "p.active = :active";
    $paramsProducts[':active'] = $filterActive;
}

// Contar total de produtos (para paginação)
$countProductsSql = "
    SELECT COUNT(*) 
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
";

if (!empty($whereProducts)) {
    $countProductsSql .= " WHERE " . implode(' AND ', $whereProducts);
}

try {
    $countProductsStmt = $pdo->prepare($countProductsSql);
    $countProductsStmt->execute($paramsProducts);
    $totalProducts = $countProductsStmt->fetchColumn();
    $totalProductPages = ceil($totalProducts / $perPage);
} catch (PDOException $e) {
    $totalProducts = 0;
    $totalProductPages = 1;
    addNotification("Erro ao contar produtos: " . $e->getMessage(), 'danger');
}

// Buscar produtos paginados
$sqlProducts = "
    SELECT p.*, b.name AS brand_name,
           CASE 
               WHEN p.promo_price > 0 THEN p.promo_price
               ELSE p.price
           END AS effective_price,
           (CASE 
               WHEN p.promo_price > 0 THEN p.promo_price
               ELSE p.price
           END - p.cost) AS profit_value
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
";

if (!empty($whereProducts)) {
    $sqlProducts .= " WHERE " . implode(' AND ', $whereProducts);
}

// Ordenação
if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    $validSortColumns = ['id', 'name', 'brand_name', 'price', 'promo_price', 'cost', 'profit_value', 'effective_price', 'category'];
    $sortColumn = in_array($_GET['sort'], $validSortColumns) ? $_GET['sort'] : 'id';
    $sortDir = (isset($_GET['dir']) && $_GET['dir'] == 'asc') ? 'ASC' : 'DESC';
    
    // Para ordenar por nome da marca, precisamos ajustar
    if ($sortColumn === 'brand_name') {
        $sqlProducts .= " ORDER BY b.name $sortDir, p.name ASC";
    } else {
        $sqlProducts .= " ORDER BY $sortColumn $sortDir";
    }
} else {
    $sqlProducts .= " ORDER BY p.id DESC";
}

$sqlProducts .= " LIMIT :offset, :limit";

try {
    $stmtProducts = $pdo->prepare($sqlProducts);
    $stmtProducts->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtProducts->bindValue(':limit', $perPage, PDO::PARAM_INT);
    
    foreach ($paramsProducts as $key => $value) {
        $stmtProducts->bindValue($key, $value);
    }
    
    $stmtProducts->execute();
    $listaProducts = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listaProducts = [];
    addNotification("Erro ao listar produtos: " . $e->getMessage(), 'danger');
}

// 3.4) Categorias distintas para filtros
try {
    $stmtCategories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $distinctCategories = $stmtCategories->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $distinctCategories = [];
}

// Dados estatísticos para dashboard
try {
    // Total de produtos e marcas
    $totalBrandsCount = $pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
    $totalProductsCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalActiveProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn();
    
    // Produto mais caro e mais barato
    $mostExpensive = $pdo->query("SELECT id, name, price FROM products ORDER BY price DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $cheapest = $pdo->query("SELECT id, name, price FROM products WHERE price > 0 ORDER BY price ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    // Marca com mais produtos
    $topBrand = $pdo->query("
        SELECT b.id, b.name, COUNT(p.id) as product_count 
        FROM brands b 
        JOIN products p ON b.id = p.brand_id 
        GROUP BY b.id 
        ORDER BY product_count DESC 
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Produto com maior margem absoluta
    $topProfitProduct = $pdo->query("
        SELECT id, name, price, promo_price, cost, 
        CASE 
            WHEN promo_price > 0 THEN promo_price - cost
            ELSE price - cost
        END as profit
        FROM products
        WHERE cost > 0
        ORDER BY profit DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Média de preço e custo dos produtos
    $avgPriceAndCost = $pdo->query("
        SELECT AVG(price) as avg_price, AVG(cost) as avg_cost
        FROM products
        WHERE price > 0 AND cost > 0
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em caso de erro, não mostramos estatísticas
    $statsError = true;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Marcas e Produtos - Império Pharma</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos Gerais */
        .tab-content {
            margin-top: 1.5rem;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom-width: 3px;
        }
        .stats-card {
            transition: all 0.2s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        .card-icon {
            font-size: 2rem;
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        /* Indicadores Visuais */
        .profit-indicator {
            width: 100%;
            height: 6px;
            margin-top: 5px;
            border-radius: 3px;
            background-color: #f0f0f0;
        }
        .profit-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .profit-positive {
            background-color: #28a745;
        }
        .profit-negative {
            background-color: #dc3545;
        }
        
        /* Tabelas */
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .table th {
            position: relative;
        }
        th.sortable {
            cursor: pointer;
        }
        th.sortable::after {
            content: "↕";
            position: absolute;
            right: 8px;
            opacity: 0.5;
        }
        th.sorted-asc::after {
            content: "↑";
            opacity: 1;
        }
        th.sorted-desc::after {
            content: "↓";
            opacity: 1;
        }
        
        /* Modals */
        .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        
        /* Form Elements */
        .profit-badge {
            font-size: 0.9rem;
            padding: 0.25rem 0.5rem;
        }
        .readonly-calc {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        .image-preview {
            max-width: 100%;
            max-height: 120px;
            margin-top: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 3px;
            display: none;
        }
        
        /* Bulk Edit */
        .mass-edit-row:hover {
            background-color: rgba(0,123,255,0.05);
        }
        .mass-edit-actions {
            position: sticky;
            bottom: 0;
            background: #fff;
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            box-shadow: 0 -5px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }
        
        /* Pagination and Filters */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-card {
            border-radius: 0.5rem;
            background-color: #f8f9fa;
            margin-bottom: 1.5rem;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 767.98px) {
            .card-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
            .hidden-sm {
                display: none;
            }
            .table-responsive {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid py-3">
    <h1 class="mb-4 d-flex justify-content-between align-items-center">
        <span>Gerenciamento de Marcas e Produtos</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#estatisticasModal">
            <i class="fas fa-chart-bar me-1"></i> Estatísticas
        </button>
    </h1>
    
    <?php if (!empty($notifications)): ?>
        <div class="notifications mb-4">
            <?php foreach($notifications as $notification): ?>
                <div class="alert alert-<?= $notification['type'] ?> alert-dismissible fade show" role="alert">
                    <?= $notification['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Navegação por abas -->
    <ul class="nav nav-tabs" id="gerenciamentoTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'marcas' ? 'active' : '' ?>" 
                    id="marcas-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#marcas" 
                    type="button" 
                    role="tab" 
                    aria-controls="marcas" 
                    aria-selected="<?= $tab === 'marcas' ? 'true' : 'false' ?>"
                    onclick="updateQueryParam('tab', 'marcas')">
                <i class="fas fa-tags me-1"></i> Marcas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'produtos' ? 'active' : '' ?>" 
                    id="produtos-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#produtos" 
                    type="button" 
                    role="tab" 
                    aria-controls="produtos" 
                    aria-selected="<?= $tab === 'produtos' ? 'true' : 'false' ?>"
                    onclick="updateQueryParam('tab', 'produtos')">
                <i class="fas fa-boxes me-1"></i> Produtos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'mass_products' ? 'active' : '' ?>" 
                    id="mass-products-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#mass_products" 
                    type="button" 
                    role="tab" 
                    aria-controls="mass_products" 
                    aria-selected="<?= $tab === 'mass_products' ? 'true' : 'false' ?>"
                    onclick="updateQueryParam('tab', 'mass_products')">
                <i class="fas fa-edit me-1"></i> Edição em Massa (Produtos)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'mass_brands' ? 'active' : '' ?>" 
                    id="mass-brands-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#mass_brands" 
                    type="button" 
                    role="tab" 
                    aria-controls="mass_brands" 
                    aria-selected="<?= $tab === 'mass_brands' ? 'true' : 'false' ?>"
                    onclick="updateQueryParam('tab', 'mass_brands')">
                <i class="fas fa-layer-group me-1"></i> Edição em Massa (Marcas)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'mass_prices' ? 'active' : '' ?>" 
                    id="mass-prices-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#mass_prices" 
                    type="button" 
                    role="tab" 
                    aria-controls="mass_prices" 
                    aria-selected="<?= $tab === 'mass_prices' ? 'true' : 'false' ?>"
                    onclick="updateQueryParam('tab', 'mass_prices')">
                <i class="fas fa-dollar-sign me-1"></i> Gestão de Preços e Lucro
            </button>
        </li>
    </ul>
    
    <!-- Conteúdo das abas -->
    <div class="tab-content" id="gerenciamentoTabContent">
    
        <!-- ABA DE MARCAS -->
        <div class="tab-pane fade <?= $tab === 'marcas' ? 'show active' : '' ?>" id="marcas" role="tabpanel" aria-labelledby="marcas-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Gerenciamento de Marcas</h3>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#marcaModal">
                    <i class="fas fa-plus-circle me-1"></i> Nova Marca
                </button>
            </div>
            
            <!-- Filtros para Marcas -->
            <div class="card filter-card mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="marcas">
                        
                        <div class="col-md-5">
                            <label for="filter_brand_name" class="form-label"><i class="fas fa-search me-1"></i> Nome da Marca</label>
                            <input type="text" class="form-control" id="filter_brand_name" name="filter_brand_name" 
                                   value="<?= htmlspecialchars($filterBrandName) ?>" placeholder="Buscar marcas...">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="per_page" class="form-label"><i class="fas fa-list-ol me-1"></i> Por página</label>
                            <select class="form-select" id="per_page" name="per_page">
                                <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="?tab=marcas" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tabela de Marcas -->
            <?php if (empty($listaMarcas)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    Nenhuma marca encontrada. <?= !empty($filterBrandName) ? 'Tente ajustar os filtros de busca.' : 'Cadastre sua primeira marca clicando no botão "Nova Marca".' ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#ID</th>
                                <th>Slug</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th class="text-center">Estoque</th>
                                <th class="text-center">Ordem</th>
                                <th class="text-center">Imagens</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaMarcas as $marca): ?>
                            <tr>
                                <td><?= $marca['id'] ?></td>
                                <td><code><?= htmlspecialchars($marca['slug']) ?></code></td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($marca['name']) ?></div>
                                    <?php if (!empty($marca['stock_message'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($marca['stock_message']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($marca['brand_type'] ?? 'N/A') ?></td>
                               <td class="text-center">
    <span class="badge bg-secondary">Fornecedor <?= $marca['stock'] ?></span>
</td>
                                <td class="text-center"><?= $marca['sort_order'] ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center">
                                        <?php if (!empty($marca['banner'])): ?>
                                        <a href="<?= htmlspecialchars($marca['banner']) ?>" target="_blank" class="text-primary me-2" data-bs-toggle="tooltip" title="Banner">
                                            <i class="fas fa-image"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($marca['btn_image'])): ?>
                                        <a href="<?= htmlspecialchars($marca['btn_image']) ?>" target="_blank" class="text-info" data-bs-toggle="tooltip" title="Botão">
                                            <i class="fas fa-hand-pointer"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editMarca(<?= $marca['id'] ?>)" 
                                                data-bs-toggle="tooltip" title="Editar marca">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDeleteMarca(<?= $marca['id'] ?>, '<?= htmlspecialchars($marca['name'], ENT_QUOTES) ?>')" 
                                                data-bs-toggle="tooltip" title="Excluir marca">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-success" 
                                                onclick="window.location.href='?tab=produtos&filter_brand_id=<?= $marca['id'] ?>'"
                                                data-bs-toggle="tooltip" title="Ver produtos desta marca">
                                            <i class="fas fa-boxes"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação para Marcas -->
                <?php if ($totalBrandPages > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Paginação de marcas">
                        <ul class="pagination">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=marcas&page_num=1&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Primeira">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=marcas&page_num=<?= $page-1 ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalBrandPages, $page + 2);
                            
                            if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=marcas&page_num=<?= $i ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalBrandPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                            
                            <li class="page-item <?= $page >= $totalBrandPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=marcas&page_num=<?= $page+1 ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Próxima">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page >= $totalBrandPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=marcas&page_num=<?= $totalBrandPages ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Última">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    
                    <div class="pagination-info">
                        Mostrando <strong><?= count($listaMarcas) ?></strong> de <strong><?= $totalBrands ?></strong> marcas
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- ABA DE PRODUTOS -->
        <div class="tab-pane fade <?= $tab === 'produtos' ? 'show active' : '' ?>" id="produtos" role="tabpanel" aria-labelledby="produtos-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Gerenciamento de Produtos</h3>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#produtoModal">
                    <i class="fas fa-plus-circle me-1"></i> Novo Produto
                </button>
            </div>
            
            <!-- Filtros de Produtos -->
            <div class="card filter-card mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="produtos">
                        
                        <div class="col-md-4">
                            <label for="filter_product_name" class="form-label"><i class="fas fa-search me-1"></i> Nome do Produto</label>
                            <input type="text" class="form-control" id="filter_product_name" name="filter_product_name" 
                                   value="<?= htmlspecialchars($filterProductName) ?>" placeholder="Buscar produtos...">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="filter_brand_id" class="form-label"><i class="fas fa-tag me-1"></i> Marca</label>
                            <select class="form-select" id="filter_brand_id" name="filter_brand_id">
                                <option value="0">Todas as Marcas</option>
                                <?php foreach ($brandsSelect as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" <?= $brand['id'] == $filterBrandId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="filter_category" class="form-label"><i class="fas fa-folder me-1"></i> Categoria</label>
                            <select class="form-select" id="filter_category" name="filter_category">
                                <option value="">Todas</option>
                                <?php foreach ($distinctCategories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>" <?= $category == $filterCategory ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="filter_active" class="form-label"><i class="fas fa-toggle-on me-1"></i> Status</label>
                            <select class="form-select" id="filter_active" name="filter_active">
                                <option value="-1" <?= $filterActive == -1 ? 'selected' : '' ?>>Todos</option>
                                <option value="1" <?= $filterActive == 1 ? 'selected' : '' ?>>Ativos</option>
                                <option value="0" <?= $filterActive == 0 ? 'selected' : '' ?>>Inativos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <label for="per_page" class="form-label">Por pág.</label>
                            <select class="form-select" id="per_page" name="per_page">
                                <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="?tab=produtos" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tabela de Produtos -->
            <?php if (empty($listaProducts)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    Nenhum produto encontrado. <?= !empty($filterProductName) || $filterBrandId > 0 || !empty($filterCategory) ? 'Tente ajustar os filtros de busca.' : 'Cadastre seu primeiro produto clicando no botão "Novo Produto".' ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="sortable <?= isset($_GET['sort']) && $_GET['sort'] == 'id' ? ($_GET['dir'] == 'asc' ? 'sorted-asc' : 'sorted-desc') : '' ?>"
                                    onclick="sortTable('id', '<?= isset($_GET['sort']) && $_GET['sort'] == 'id' && $_GET['dir'] == 'asc' ? 'desc' : 'asc' ?>')">
                                    #ID
                                </th>
                                <th class="sortable <?= isset($_GET['sort']) && $_GET['sort'] == 'brand_name' ? ($_GET['dir'] == 'asc' ? 'sorted-asc' : 'sorted-desc') : '' ?>"
                                    onclick="sortTable('brand_name', '<?= isset($_GET['sort']) && $_GET['sort'] == 'brand_name' && $_GET['dir'] == 'asc' ? 'desc' : 'asc' ?>')">
                                    Marca
                                </th>
                                <th class="sortable <?= isset($_GET['sort']) && $_GET['sort'] == 'name' ? ($_GET['dir'] == 'asc' ? 'sorted-asc' : 'sorted-desc') : '' ?>"
                                    onclick="sortTable('name', '<?= isset($_GET['sort']) && $_GET['sort'] == 'name' && $_GET['dir'] == 'asc' ? 'desc' : 'asc' ?>')">
                                    Nome do Produto
                                </th>
                                <th class="sortable <?= isset($_GET['sort']) && $_GET['sort'] == 'price' ? ($_GET['dir'] == 'asc' ? 'sorted-asc' : 'sorted-desc') : '' ?>"
                                    onclick="sortTable('price', '<?= isset($_GET['sort']) && $_GET['sort'] == 'price' && $_GET['dir'] == 'asc' ? 'desc' : 'asc' ?>')">
                                    Preço (R$)
                                </th>
                                <th class="sortable <?= isset($_GET['sort']) && $_GET['sort'] == 'cost' ? ($_GET['dir'] == 'asc' ? 'sorted-asc' : 'sorted-desc') : '' ?>"
                                    onclick="sortTable('cost', '<?= isset($_GET['sort']) && $_GET['sort'] == 'cost' && $_GET['dir'] == 'asc' ? 'desc' : 'asc' ?>')">
                                    Custo (R$)
                                </th>
                                <th class="sortable <?= isset($_GET['sort']) && $_GET['sort'] == 'profit_value' ? ($_GET['dir'] == 'asc' ? 'sorted-asc' : 'sorted-desc') : '' ?>"
                                    onclick="sortTable('profit_value', '<?= isset($_GET['sort']) && $_GET['sort'] == 'profit_value' && $_GET['dir'] == 'asc' ? 'desc' : 'asc' ?>')">
                                    Lucro (R$)
                                </th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Imagem</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaProducts as $produto): ?>
                            <?php 
                                $profitValue = floatval($produto['profit_value']);
                                $profitClass = $profitValue >= 0 ? 'text-success' : 'text-danger';
                                $profitPrefix = $profitValue >= 0 ? '+' : '';
                                
                                // Calcular porcentagem de lucro
                                $sellPrice = floatval($produto['effective_price']);
                                $profitPercent = ($sellPrice > 0) ? ($profitValue / $sellPrice) * 100 : 0;
                                
                                // Definir largura da barra de lucro (0-100%)
                                $barWidth = min(100, max(0, abs($profitPercent)));
                                $barClass = $profitValue >= 0 ? 'profit-positive' : 'profit-negative';
                            ?>
                            <tr>
                                <td><?= $produto['id'] ?></td>
                                <td>
                                    <?php if ($produto['brand_id'] > 0): ?>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($produto['brand_name'] ?? 'N/A') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Sem marca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($produto['name']) ?></div>
                                    <?php if (!empty($produto['category'])): ?>
                                        <small class="badge bg-light text-dark"><?= htmlspecialchars($produto['category']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-medium">R$ <?= formatMoney($produto['price']) ?></div>
                                    <?php if (floatval($produto['promo_price']) > 0): ?>
                                        <div class="text-danger">Promo: R$ <?= formatMoney($produto['promo_price']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>R$ <?= formatMoney($produto['cost']) ?></td>
                                <td>
                                    <div class="<?= $profitClass ?>">
                                        <?= $profitPrefix ?>R$ <?= formatMoney(abs($profitValue)) ?>
                                        <small>(<?= number_format($profitPercent, 1) ?>%)</small>
                                    </div>
                                    <div class="profit-indicator">
                                        <div class="profit-bar <?= $barClass ?>" style="width: <?= $barWidth ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($produto['active']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($produto['image_url'])): ?>
                                        <a href="<?= htmlspecialchars($produto['image_url']) ?>" target="_blank" data-bs-toggle="tooltip" title="Ver imagem">
                                            <img src="<?= htmlspecialchars($produto['image_url']) ?>" alt="Imagem do produto" class="img-thumbnail" style="max-height: 50px; max-width: 80px;">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-image"></i> Sem imagem</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editProduto(<?= $produto['id'] ?>)" 
                                                data-bs-toggle="tooltip" title="Editar produto">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDeleteProduto(<?= $produto['id'] ?>, '<?= htmlspecialchars($produto['name'], ENT_QUOTES) ?>')" 
                                                data-bs-toggle="tooltip" title="Excluir produto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewProdutoDetalhes(<?= $produto['id'] ?>)" 
                                                data-bs-toggle="tooltip" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação para Produtos -->
                <?php if ($totalProductPages > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Paginação de produtos">
                        <ul class="pagination">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=produtos&page_num=1&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Primeira">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=produtos&page_num=<?= $page-1 ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalProductPages, $page + 2);
                            
                            if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=produtos&page_num=<?= $i ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalProductPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                            
                            <li class="page-item <?= $page >= $totalProductPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=produtos&page_num=<?= $page+1 ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Próxima">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page >= $totalProductPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=produtos&page_num=<?= $totalProductPages ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Última">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    
                    <div class="pagination-info">
                        Mostrando <strong><?= count($listaProducts) ?></strong> de <strong><?= $totalProducts ?></strong> produtos
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- ABA DE EDIÇÃO EM MASSA DE PRODUTOS -->
        <div class="tab-pane fade <?= $tab === 'mass_products' ? 'show active' : '' ?>" id="mass_products" role="tabpanel" aria-labelledby="mass-products-tab">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h3>Edição em Massa de Produtos</h3>
                    <p class="text-muted">Altere múltiplos produtos simultaneamente ou insira novos produtos em lote.</p>
                </div>
                <div class="btn-group" role="group" aria-label="Alterar modo de edição em massa">
                    <button type="button" class="btn btn-outline-primary active" id="btn-mass-edit" onclick="switchMassMode('edit')">
                        <i class="fas fa-edit me-1"></i> Editar Existentes
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btn-mass-add" onclick="switchMassMode('add')">
                        <i class="fas fa-plus me-1"></i> Inserir Novos
                    </button>
                </div>
            </div>
            
            <!-- Filtros de Produtos para Edição em Massa -->
            <div class="card filter-card mb-4" id="mass-edit-filters">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="mass_products">
                        
                        <div class="col-md-4">
                            <label for="filter_product_name_mass" class="form-label"><i class="fas fa-search me-1"></i> Nome do Produto</label>
                            <input type="text" class="form-control" id="filter_product_name_mass" name="filter_product_name" 
                                   value="<?= htmlspecialchars($filterProductName) ?>" placeholder="Buscar produtos...">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="filter_brand_id_mass" class="form-label"><i class="fas fa-tag me-1"></i> Marca</label>
                            <select class="form-select" id="filter_brand_id_mass" name="filter_brand_id">
                                <option value="0">Todas as Marcas</option>
                                <?php foreach ($brandsSelect as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" <?= $brand['id'] == $filterBrandId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="filter_category_mass" class="form-label"><i class="fas fa-folder me-1"></i> Categoria</label>
                            <select class="form-select" id="filter_category_mass" name="filter_category">
                                <option value="">Todas</option>
                                <?php foreach ($distinctCategories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>" <?= $category == $filterCategory ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="filter_active_mass" class="form-label"><i class="fas fa-toggle-on me-1"></i> Status</label>
                            <select class="form-select" id="filter_active_mass" name="filter_active">
                                <option value="-1" <?= $filterActive == -1 ? 'selected' : '' ?>>Todos</option>
                                <option value="1" <?= $filterActive == 1 ? 'selected' : '' ?>>Ativos</option>
                                <option value="0" <?= $filterActive == 0 ? 'selected' : '' ?>>Inativos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <label for="per_page_mass" class="form-label">Por pág.</label>
                            <select class="form-select" id="per_page_mass" name="per_page">
                                <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="?tab=mass_products" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Formulário de Edição em Massa de Produtos Existentes -->
            <div id="mass-edit-container">
                <?php if (empty($listaProducts)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Nenhum produto encontrado para edição em massa. <?= !empty($filterProductName) || $filterBrandId > 0 || !empty($filterCategory) ? 'Tente ajustar os filtros de busca.' : 'Cadastre produtos primeiro ou use a aba "Inserir Novos".' ?>
                    </div>
                <?php else: ?>
                    <form method="POST" id="formMassEdit" onsubmit="return confirmMassEdit()">
                        <input type="hidden" name="action" value="produto">
                        <input type="hidden" name="act" value="mass_update">
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px" class="text-center">ID</th>
                                        <th>Nome do Produto</th>
                                        <th style="width: 200px">Marca</th>
                                        <th style="width: 120px">Preço (R$)</th>
                                        <th style="width: 120px">Promo (R$)</th>
                                        <th style="width: 120px">Custo (R$)</th>
                                        <th style="width: 80px" class="text-center">Ativo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listaProducts as $produto): ?>
                                    <tr class="mass-edit-row">
                                        <td class="text-center"><?= $produto['id'] ?></td>
                                        <td><?= htmlspecialchars($produto['name']) ?></td>
                                        <td>
                                            <select name="mass[<?= $produto['id'] ?>][brand_id]" class="form-select form-select-sm">
                                                <option value="0">Sem marca</option>
                                                <?php foreach ($brandsSelect as $brand): ?>
                                                    <option value="<?= $brand['id'] ?>" <?= $brand['id'] == $produto['brand_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($brand['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" step="0.01" min="0" 
                                                       name="mass[<?= $produto['id'] ?>][price]" 
                                                       value="<?= number_format($produto['price'], 2, '.', '') ?>" 
                                                       class="form-control form-control-sm" 
                                                       onchange="calcMassProfit(<?= $produto['id'] ?>)">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" step="0.01" min="0" 
                                                       name="mass[<?= $produto['id'] ?>][promo_price]" 
                                                       value="<?= number_format($produto['promo_price'], 2, '.', '') ?>" 
                                                       class="form-control form-control-sm" 
                                                       onchange="calcMassProfit(<?= $produto['id'] ?>)">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" step="0.01" min="0" 
                                                       name="mass[<?= $produto['id'] ?>][cost]" 
                                                       value="<?= number_format($produto['cost'], 2, '.', '') ?>" 
                                                       class="form-control form-control-sm" 
                                                       onchange="calcMassProfit(<?= $produto['id'] ?>)">
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="mass[<?= $produto['id'] ?>][active]" 
                                                       value="1" <?= $produto['active'] ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mass-edit-actions">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-1"></i> Salvar Alterações em Massa
                                    </button>
                                </div>
                                <div class="text-muted">
                                    <small>Edição em massa de <strong><?= count($listaProducts) ?></strong> produtos</small>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Paginação -->
                    <?php if ($totalProductPages > 1): ?>
                    <div class="pagination-container mt-3">
                        <nav aria-label="Paginação de produtos">
                            <ul class="pagination">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?tab=mass_products&page_num=1&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Primeira">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?tab=mass_products&page_num=<?= $page-1 ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalProductPages, $page + 2);
                                
                                if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                
                                for ($i = $startPage; $i <= $endPage; $i++): 
                                ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?tab=mass_products&page_num=<?= $i ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalProductPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                                
                                <li class="page-item <?= $page >= $totalProductPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?tab=mass_products&page_num=<?= $page+1 ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Próxima">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item <?= $page >= $totalProductPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?tab=mass_products&page_num=<?= $totalProductPages ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Última">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        
                        <div class="pagination-info">
                            Mostrando <strong><?= count($listaProducts) ?></strong> de <strong><?= $totalProducts ?></strong> produtos
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Formulário de Adição em Massa de Novos Produtos -->
            <div id="mass-add-container" style="display:none;">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Adicionar Múltiplos Produtos</h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-row">
                                    <i class="fas fa-plus me-1"></i> Adicionar Linha
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="btn-clear-all">
                                    <i class="fas fa-trash me-1"></i> Limpar Tudo
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <form method="POST" id="formMassAdd" onsubmit="return confirmMassAdd()">
                            <input type="hidden" name="action" value="produto">
                            <input type="hidden" name="act" value="mass_add">
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle" id="massAddTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 180px">Marca</th>
                                            <th>Nome do Produto</th>
                                            <th style="width: 200px">Descrição</th>
                                            <th style="width: 110px">Preço (R$)</th>
                                            <th style="width: 110px">Promo (R$)</th>
                                            <th style="width: 110px">Custo (R$)</th>
                                            <th style="width: 140px">Categoria</th>
                                            <th style="width: 70px" class="text-center">Ativo</th>
                                            <th style="width: 60px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                        <tr class="mass-add-row">
                                            <td>
                                                <select name="mass_new[<?= $i ?>][brand_id]" class="form-select form-select-sm">
                                                    <option value="0">Sem marca</option>
                                                    <?php foreach ($brandsSelect as $brand): ?>
                                                        <option value="<?= $brand['id'] ?>">
                                                            <?= htmlspecialchars($brand['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="mass_new[<?= $i ?>][name]" class="form-control form-control-sm" placeholder="Nome do produto">
                                            </td>
                                            <td>
                                                <textarea name="mass_new[<?= $i ?>][description]" rows="2" class="form-control form-control-sm" placeholder="Descrição breve"></textarea>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" step="0.01" min="0" name="mass_new[<?= $i ?>][price]" 
                                                           class="form-control form-control-sm" placeholder="0.00"
                                                           onchange="calcMassAddProfit(<?= $i ?>)">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" step="0.01" min="0" name="mass_new[<?= $i ?>][promo_price]" 
                                                           class="form-control form-control-sm" placeholder="0.00"
                                                           onchange="calcMassAddProfit(<?= $i ?>)">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" step="0.01" min="0" name="mass_new[<?= $i ?>][cost]" 
                                                           class="form-control form-control-sm" placeholder="0.00"
                                                           onchange="calcMassAddProfit(<?= $i ?>)">
                                                </div>
                                            </td>
                                            <td>
                                                <select name="mass_new[<?= $i ?>][category]" class="form-select form-select-sm">
                                                    <option value="">Selecione...</option>
                                                    <?php foreach ($distinctCategories as $category): ?>
                                                        <option value="<?= htmlspecialchars($category) ?>">
                                                            <?= htmlspecialchars($category) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" name="mass_new[<?= $i ?>][active]" value="1" checked>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mass-edit-actions">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i> Inserir Produtos em Massa
                                        </button>
                                        <button type="button" class="btn btn-outline-primary ms-2" id="btn-add-more-rows">
                                            <i class="fas fa-plus me-1"></i> Adicionar Mais 5 Linhas
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ABA DE EDIÇÃO EM MASSA DE MARCAS -->
        <div class="tab-pane fade <?= $tab === 'mass_brands' ? 'show active' : '' ?>" id="mass_brands" role="tabpanel" aria-labelledby="mass-brands-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3>Edição em Massa de Marcas</h3>
                    <p class="text-muted">Altere múltiplas marcas simultaneamente para ganhar tempo.</p>
                </div>
            </div>
            
            <!-- Filtros para Edição em Massa de Marcas -->
            <div class="card filter-card mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="mass_brands">
                        
                        <div class="col-md-6">
                            <label for="filter_brand_name_mass" class="form-label"><i class="fas fa-search me-1"></i> Nome da Marca</label>
                            <input type="text" class="form-control" id="filter_brand_name_mass" name="filter_brand_name" 
                                   value="<?= htmlspecialchars($filterBrandName) ?>" placeholder="Buscar marcas...">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="per_page_brands" class="form-label"><i class="fas fa-list-ol me-1"></i> Por página</label>
                            <select class="form-select" id="per_page_brands" name="per_page">
                                <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="?tab=mass_brands" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Formulário de Edição em Massa de Marcas -->
            <?php if (empty($listaMarcas)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    Nenhuma marca encontrada para edição em massa. <?= !empty($filterBrandName) ? 'Tente ajustar os filtros de busca.' : 'Cadastre marcas primeiro na aba "Marcas".' ?>
                </div>
            <?php else: ?>
                <form method="POST" id="formMassBrands" onsubmit="return confirmMassBrandsEdit()">
                    <input type="hidden" name="action" value="marca">
                    <input type="hidden" name="act" value="mass_update_brands">
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px" class="text-center">ID</th>
                                    <th style="width: 150px">Slug</th>
                                    <th>Nome da Marca</th>
                                    <th style="width: 120px">Tipo</th>
                                    <th style="width: 100px" class="text-center">Estoque</th>
                                    <th style="width: 80px" class="text-center">Ordem</th>
                                    <th>Mensagem de Estoque</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaMarcas as $marca): ?>
                                <tr class="mass-edit-row">
                                    <td class="text-center"><?= $marca['id'] ?></td>
                                    <td>
                                        <input type="text" name="mass_brands[<?= $marca['id'] ?>][slug]" 
                                               value="<?= htmlspecialchars($marca['slug']) ?>" 
                                               class="form-control form-control-sm" required>
                                    </td>
                                    <td>
                                        <input type="text" name="mass_brands[<?= $marca['id'] ?>][name]" 
                                               value="<?= htmlspecialchars($marca['name']) ?>" 
                                               class="form-control form-control-sm" required>
                                    </td>
                                    <td>
                                        <input type="text" name="mass_brands[<?= $marca['id'] ?>][brand_type]" 
                                               value="<?= htmlspecialchars($marca['brand_type'] ?? '') ?>" 
                                               class="form-control form-control-sm">
                                    </td>
                            <td>
    <select name="mass_brands[<?= $marca['id'] ?>][stock]" class="form-select form-select-sm">
        <option value="1" <?= $marca['stock'] == 1 ? 'selected' : '' ?>>Fornecedor 1</option>
        <option value="2" <?= $marca['stock'] == 2 ? 'selected' : '' ?>>Fornecedor 2</option>
        <option value="3" <?= $marca['stock'] == 3 ? 'selected' : '' ?>>Fornecedor 3</option>
        <option value="4" <?= $marca['stock'] == 4 ? 'selected' : '' ?>>Fornecedor 4</option>
        <option value="5" <?= $marca['stock'] == 5 ? 'selected' : '' ?>>Fornecedor 5</option>
        <option value="0" <?= $marca['stock'] == 0 ? 'selected' : '' ?>>Outro</option>
    </select>
</td>
                                    <td>
                                        <input type="number" name="mass_brands[<?= $marca['id'] ?>][sort_order]" 
                                               value="<?= (int)$marca['sort_order'] ?>" 
                                               class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <textarea name="mass_brands[<?= $marca['id'] ?>][stock_message]" 
                                                  rows="2" class="form-control form-control-sm"><?= htmlspecialchars($marca['stock_message'] ?? '') ?></textarea>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mass-edit-actions">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Salvar Alterações em Massa
                                </button>
                            </div>
                            <div class="text-muted">
                                <small>Edição em massa de <strong><?= count($listaMarcas) ?></strong> marcas</small>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Paginação -->
                <?php if ($totalBrandPages > 1): ?>
                <div class="pagination-container mt-3">
                    <nav aria-label="Paginação de marcas">
                        <ul class="pagination">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_brands&page_num=1&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Primeira">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_brands&page_num=<?= $page-1 ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalBrandPages, $page + 2);
                            
                            if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=mass_brands&page_num=<?= $i ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalBrandPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                            
                            <li class="page-item <?= $page >= $totalBrandPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_brands&page_num=<?= $page+1 ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Próxima">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page >= $totalBrandPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_brands&page_num=<?= $totalBrandPages ?>&per_page=<?= $perPage ?>&filter_brand_name=<?= urlencode($filterBrandName) ?>" aria-label="Última">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    
                    <div class="pagination-info">
                        Mostrando <strong><?= count($listaMarcas) ?></strong> de <strong><?= $totalBrands ?></strong> marcas
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- ABA DE EDIÇÃO EM MASSA DE PREÇOS E LUCRO -->
        <div class="tab-pane fade <?= $tab === 'mass_prices' ? 'show active' : '' ?>" id="mass_prices" role="tabpanel" aria-labelledby="mass-prices-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3>Gestão de Preços e Análise de Lucro</h3>
                    <p class="text-muted">Edite preços de venda, promoção e custo com cálculo de lucro em tempo real.</p>
                </div>
            </div>
            
            <!-- Filtros para Gestão de Preços -->
            <div class="card filter-card mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="mass_prices">
                        
                        <div class="col-md-4">
                            <label for="filter_product_name_prices" class="form-label"><i class="fas fa-search me-1"></i> Nome do Produto</label>
                            <input type="text" class="form-control" id="filter_product_name_prices" name="filter_product_name" 
                                   value="<?= htmlspecialchars($filterProductName) ?>" placeholder="Buscar produtos...">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="filter_brand_id_prices" class="form-label"><i class="fas fa-tag me-1"></i> Marca</label>
                            <select class="form-select" id="filter_brand_id_prices" name="filter_brand_id">
                                <option value="0">Todas as Marcas</option>
                                <?php foreach ($brandsSelect as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" <?= $brand['id'] == $filterBrandId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="filter_category_prices" class="form-label"><i class="fas fa-folder me-1"></i> Categoria</label>
                            <select class="form-select" id="filter_category_prices" name="filter_category">
                                <option value="">Todas</option>
                                <?php foreach ($distinctCategories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>" <?= $category == $filterCategory ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="filter_active_prices" class="form-label"><i class="fas fa-toggle-on me-1"></i> Status</label>
                            <select class="form-select" id="filter_active_prices" name="filter_active">
                                <option value="-1" <?= $filterActive == -1 ? 'selected' : '' ?>>Todos</option>
                                <option value="1" <?= $filterActive == 1 ? 'selected' : '' ?>>Ativos</option>
                                <option value="0" <?= $filterActive == 0 ? 'selected' : '' ?>>Inativos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <label for="per_page_prices" class="form-label">Por pág.</label>
                            <select class="form-select" id="per_page_prices" name="per_page">
                                <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="?tab=mass_prices" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Formulário de Gestão de Preços e Lucro -->
            <?php if (empty($listaProducts)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    Nenhum produto encontrado para gestão de preços. <?= !empty($filterProductName) || $filterBrandId > 0 || !empty($filterCategory) ? 'Tente ajustar os filtros de busca.' : 'Cadastre produtos primeiro na aba "Produtos".' ?>
                </div>
            <?php else: ?>
                <!-- Estatísticas de Lucro da Seleção Atual -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="card-icon bg-primary-subtle">
                                    <i class="fas fa-calculator text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Margem Média de Lucro</h6>
                                    <h3 class="mb-0" id="avg-profit-percent">Calculando...</h3>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="card-icon bg-success-subtle">
                                    <i class="fas fa-dollar-sign text-success"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Lucro Total Estimado</h6>
                                    <h3 class="mb-0" id="total-profit-value">Calculando...</h3>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="card-icon bg-info-subtle">
                                    <i class="fas fa-tags text-info"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Produtos com Promoção</h6>
                                    <h3 class="mb-0" id="promo-count">Calculando...</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" id="formMassPrices" onsubmit="return confirmMassPricesEdit()">
                    <input type="hidden" name="action" value="produto">
                    <input type="hidden" name="act" value="mass_update_prices">
                    
                    <!-- Ações em Massa - Aplicação Rápida -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Aplicação Rápida em Massa</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Ajuste nos Preços</label>
                                    <div class="input-group">
                                        <select class="form-select" id="prices-adjustment-type">
                                            <option value="percent">Percentual (%)</option>
                                            <option value="fixed">Valor Fixo (R$)</option>
                                        </select>
                                        <input type="number" step="0.01" class="form-control" id="prices-adjustment-value" placeholder="Valor">
                                        <button class="btn btn-outline-primary" type="button" id="btn-apply-prices">Aplicar</button>
                                    </div>
                                    <div class="form-text">Ex: 10 = aumento de 10% ou R$10</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Ajuste nas Promoções</label>
                                    <div class="input-group">
                                        <select class="form-select" id="promos-adjustment-type">
                                            <option value="percent">Percentual (%)</option>
                                            <option value="fixed">Valor Fixo (R$)</option>
                                        </select>
                                        <input type="number" step="0.01" class="form-control" id="promos-adjustment-value" placeholder="Valor">
                                        <button class="btn btn-outline-primary" type="button" id="btn-apply-promos">Aplicar</button>
                                    </div>
                                    <div class="form-text">Use valores negativos para redução</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Ajuste nos Custos</label>
                                    <div class="input-group">
                                        <select class="form-select" id="costs-adjustment-type">
                                            <option value="percent">Percentual (%)</option>
                                            <option value="fixed">Valor Fixo (R$)</option>
                                        </select>
                                        <input type="number" step="0.01" class="form-control" id="costs-adjustment-value" placeholder="Valor">
                                        <button class="btn btn-outline-primary" type="button" id="btn-apply-costs">Aplicar</button>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Ajuste de Margem</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" class="form-control" id="margin-adjustment-value" placeholder="% de Margem">
                                        <button class="btn btn-outline-success" type="button" id="btn-apply-margin">Aplicar Margem</button>
                                    </div>
                                    <div class="form-text">Define preço baseado no custo + margem</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 55px" class="text-center">#</th>
                                    <th>Produto</th>
                                    <th>Marca</th>
                                    <th style="width: 110px">Preço (R$)</th>
                                    <th style="width: 110px">Promo (R$)</th>
                                    <th style="width: 110px">Custo (R$)</th>
                                    <th style="width: 110px">Lucro (R$)</th>
                                    <th style="width: 100px">Margem (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaProducts as $produto): ?>
                                <?php 
                                    $prodId = $produto['id'];
                                    $price = floatval($produto['price']);
                                    $promoPrice = floatval($produto['promo_price']);
                                    $cost = floatval($produto['cost']);
                                    
                                    $sellPrice = ($promoPrice > 0) ? $promoPrice : $price;
                                    $profit = $sellPrice - $cost;
                                    $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
                                    
                                    $margin = ($sellPrice > 0) ? ($profit / $sellPrice) * 100 : 0;
                                    $marginFormatted = number_format($margin, 1);
                                    
                                    // Para a barra de lucro
                                    $barWidth = min(100, max(0, abs($margin)));
                                    $barClass = $profit >= 0 ? 'profit-positive' : 'profit-negative';
                                ?>
                                <tr class="mass-edit-row" data-product-id="<?= $prodId ?>">
                                    <td class="text-center"><?= $prodId ?></td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($produto['name']) ?></div>
                                        <?php if (!empty($produto['category'])): ?>
                                            <small class="badge bg-light text-dark"><?= htmlspecialchars($produto['category']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($produto['brand_name'] ?? 'Sem marca') ?></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">R$</span>
                                            <input type="number" step="0.01" min="0" 
                                                   name="mass_prices[<?= $prodId ?>][price]" 
                                                   value="<?= number_format($price, 2, '.', '') ?>" 
                                                   class="form-control form-control-sm product-price" 
                                                   data-product-id="<?= $prodId ?>"
                                                   onchange="calculateProductProfit(<?= $prodId ?>)">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">R$</span>
                                            <input type="number" step="0.01" min="0" 
                                                   name="mass_prices[<?= $prodId ?>][promo_price]" 
                                                   value="<?= number_format($promoPrice, 2, '.', '') ?>" 
                                                   class="form-control form-control-sm product-promo" 
                                                   data-product-id="<?= $prodId ?>"
                                                   onchange="calculateProductProfit(<?= $prodId ?>)">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">R$</span>
                                            <input type="number" step="0.01" min="0" 
                                                   name="mass_prices[<?= $prodId ?>][cost]" 
                                                   value="<?= number_format($cost, 2, '.', '') ?>" 
                                                   class="form-control form-control-sm product-cost" 
                                                   data-product-id="<?= $prodId ?>"
                                                   onchange="calculateProductProfit(<?= $prodId ?>)">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm readonly-calc product-profit" 
                                               value="R$ <?= number_format($profit, 2, ',', '.') ?>" 
                                               data-product-id="<?= $prodId ?>" 
                                               data-value="<?= $profit ?>"
                                               readonly>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <input type="text" class="form-control form-control-sm readonly-calc product-margin" 
                                                   value="<?= $marginFormatted ?>%" 
                                                   data-product-id="<?= $prodId ?>" 
                                                   data-value="<?= $margin ?>"
                                                   readonly>
                                            <div class="profit-indicator mt-1">
                                                <div class="profit-bar <?= $barClass ?> product-margin-bar" 
                                                     data-product-id="<?= $prodId ?>" 
                                                     style="width: <?= $barWidth ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mass-edit-actions">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Salvar Alterações de Preços e Custos
                                </button>
                            </div>
                            <div class="text-muted">
                                <small>Edição de <strong><?= count($listaProducts) ?></strong> produtos</small>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Paginação -->
                <?php if ($totalProductPages > 1): ?>
                <div class="pagination-container mt-3">
                    <nav aria-label="Paginação de produtos">
                        <ul class="pagination">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_prices&page_num=1&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Primeira">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_prices&page_num=<?= $page-1 ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalProductPages, $page + 2);
                            
                            if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=mass_prices&page_num=<?= $i ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalProductPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                            
                            <li class="page-item <?= $page >= $totalProductPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_prices&page_num=<?= $page+1 ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Próxima">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page >= $totalProductPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=mass_prices&page_num=<?= $totalProductPages ?>&per_page=<?= $perPage ?>&filter_brand_id=<?= $filterBrandId ?>&filter_product_name=<?= urlencode($filterProductName) ?>&filter_category=<?= urlencode($filterCategory) ?>&filter_active=<?= $filterActive ?>" aria-label="Última">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    
                    <div class="pagination-info">
                        Mostrando <strong><?= count($listaProducts) ?></strong> de <strong><?= $totalProducts ?></strong> produtos
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAIS -->
    
    <!-- Modal de Estatísticas -->
    <div class="modal fade" id="estatisticasModal" tabindex="-1" aria-labelledby="estatisticasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="estatisticasModalLabel">
                        <i class="fas fa-chart-line me-2"></i> Estatísticas do Catálogo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($statsError)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Não foi possível gerar estatísticas. Tente novamente mais tarde.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3 stats-card">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon bg-primary-subtle">
                                                <i class="fas fa-boxes text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-0">Total de Produtos</h6>
                                                <h3 class="mt-1 mb-0"><?= number_format($totalProductsCount, 0, ',', '.') ?></h3>
                                                <small class="text-muted"><?= number_format($totalActiveProducts, 0, ',', '.') ?> ativos</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3 stats-card">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon bg-info-subtle">
                                                <i class="fas fa-tags text-info"></i>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-0">Total de Marcas</h6>
                                                <h3 class="mt-1 mb-0"><?= number_format($totalBrandsCount, 0, ',', '.') ?></h3>
                                                <?php if (isset($topBrand) && !empty($topBrand)): ?>
                                                <small class="text-muted">Top: <?= htmlspecialchars($topBrand['name']) ?> (<?= $topBrand['product_count'] ?> produtos)</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3 stats-card">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon bg-success-subtle">
                                                <i class="fas fa-dollar-sign text-success"></i>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-0">Média de Preços</h6>
                                                <h3 class="mt-1 mb-0">R$ <?= number_format($avgPriceAndCost['avg_price'] ?? 0, 2, ',', '.') ?></h3>
                                                <small class="text-muted">Custo médio: R$ <?= number_format($avgPriceAndCost['avg_cost'] ?? 0, 2, ',', '.') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3 stats-card">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon bg-warning-subtle">
                                                <i class="fas fa-percentage text-warning"></i>
                                            </div>
                                            <div>
                                                <h6 class="text-muted mb-0">Maior Margem de Lucro</h6>
                                                <?php if (isset($topProfitProduct) && !empty($topProfitProduct)): ?>
                                                    <?php
                                                        $topProfit = floatval($topProfitProduct['profit']);
                                                        $topSellPrice = floatval($topProfitProduct['promo_price'] > 0 ? $topProfitProduct['promo_price'] : $topProfitProduct['price']);
                                                        $topMargin = ($topSellPrice > 0) ? round(($topProfit / $topSellPrice) * 100, 1) : 0;
                                                    ?>
                                                    <h3 class="mt-1 mb-0"><?= $topMargin ?>%</h3>
                                                    <small class="text-muted"><?= htmlspecialchars($topProfitProduct['name']) ?></small>
                                                <?php else: ?>
                                                    <h3 class="mt-1 mb-0">0.0%</h3>
                                                    <small class="text-muted">Sem dados</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="mt-1 mb-4">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Destaques de Produtos</h5>
                                <div class="list-group">
                                    <?php if (isset($mostExpensive) && !empty($mostExpensive)): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Produto mais caro</h6>
                                            <span class="badge bg-primary">R$ <?= formatMoney($mostExpensive['price']) ?></span>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($mostExpensive['name']) ?></p>
                                        <small class="text-muted">ID: <?= $mostExpensive['id'] ?></small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($cheapest) && !empty($cheapest)): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Produto mais barato</h6>
                                            <span class="badge bg-info">R$ <?= formatMoney($cheapest['price']) ?></span>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($cheapest['name']) ?></p>
                                        <small class="text-muted">ID: <?= $cheapest['id'] ?></small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($topProfitProduct) && !empty($topProfitProduct)): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Maior lucro absoluto</h6>
                                            <span class="badge bg-success">R$ <?= formatMoney($topProfitProduct['profit']) ?></span>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($topProfitProduct['name']) ?></p>
                                        <small class="text-muted">
                                            Preço: R$ <?= formatMoney($topProfitProduct['price']) ?> | 
                                            Custo: R$ <?= formatMoney($topProfitProduct['cost']) ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Distribuição de Produtos</h5>
                                
                                <?php 
                                // Categorias por quantidade
                                try {
                                    $categoryDistribution = $pdo->query("
                                        SELECT category, COUNT(*) as count
                                        FROM products
                                        WHERE category IS NOT NULL AND category != ''
                                        GROUP BY category
                                        ORDER BY count DESC
                                        LIMIT 5
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    $totalWithCategories = $pdo->query("
                                        SELECT COUNT(*) FROM products
                                        WHERE category IS NOT NULL AND category != ''
                                    ")->fetchColumn();
                                    
                                    if (!empty($categoryDistribution)):
                                ?>
                                <div class="card mb-3">
                                    <div class="card-body p-3">
                                        <h6 class="card-title">Top Categorias</h6>
                                        <?php foreach ($categoryDistribution as $catInfo): ?>
                                            <?php 
                                                $catPercentage = round(($catInfo['count'] / $totalWithCategories) * 100); 
                                            ?>
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span><?= htmlspecialchars($catInfo['category']) ?></span>
                                                    <small><?= $catInfo['count'] ?> produtos (<?= $catPercentage ?>%)</small>
                                                </div>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?= $catPercentage ?>%;" 
                                                         aria-valuenow="<?= $catPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; 
                                } catch (Exception $e) {} ?>
                                
                                <?php 
                                // Produtos por marca
                                try {
                                    $brandDistribution = $pdo->query("
                                        SELECT b.name, COUNT(p.id) as count
                                        FROM products p
                                        JOIN brands b ON p.brand_id = b.id
                                        GROUP BY b.name
                                        ORDER BY count DESC
                                        LIMIT 5
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    $totalWithBrands = $pdo->query("
                                        SELECT COUNT(*) FROM products
                                        WHERE brand_id > 0
                                    ")->fetchColumn();
                                    
                                    if (!empty($brandDistribution)):
                                ?>
                                <div class="card">
                                    <div class="card-body p-3">
                                        <h6 class="card-title">Distribuição por Marca</h6>
                                        <?php foreach ($brandDistribution as $brandInfo): ?>
                                            <?php 
                                                $brandPercentage = round(($brandInfo['count'] / $totalWithBrands) * 100); 
                                            ?>
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span><?= htmlspecialchars($brandInfo['name']) ?></span>
                                                    <small><?= $brandInfo['count'] ?> produtos (<?= $brandPercentage ?>%)</small>
                                                </div>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= $brandPercentage ?>%;" 
                                                         aria-valuenow="<?= $brandPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; 
                                } catch (Exception $e) {} ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Marca (Adicionar/Editar) -->
    <div class="modal fade" id="marcaModal" tabindex="-1" aria-labelledby="marcaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="marcaModalLabel">Nova Marca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" id="formMarca">
                    <input type="hidden" name="action" value="marca">
                    <input type="hidden" name="act" value="add" id="marcaAct">
                    <input type="hidden" name="id" value="" id="marcaId">
                    
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="marcaSlug" class="form-label">Slug <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="marcaSlug" name="slug" required>
                                <div class="form-text">URL da marca (sem espaços/acentos)</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="marcaName" class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="marcaName" name="name" required>
                                <div class="form-text">Nome para exibição</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="marcaType" class="form-label">Tipo de Marca</label>
                                <input type="text" class="form-control" id="marcaType" name="brand_type">
                                <div class="form-text">Classifique a marca (opcional)</div>
                            </div>
                            
                       <div class="col-md-6">
    <label for="marcaStock" class="form-label">ID do Fornecedor/Estoque</label>
    <select class="form-select" id="marcaStock" name="stock">
        <option value="1">Fornecedor 1</option>
        <option value="2">Fornecedor 2</option>
        <option value="3">Fornecedor 3</option>
        <option value="4">Fornecedor 4</option>
        <option value="5">Fornecedor 5</option>
        <option value="0">Outro</option>
    </select>
    <div class="form-text">Identifica a origem do produto/fornecedor</div>
</div>
                            
                            <div class="col-md-12">
                                <label for="stockMessage" class="form-label">Mensagem de Estoque</label>
                                <textarea class="form-control" id="stockMessage" name="stock_message" rows="2"></textarea>
                                <div class="form-text">Mensagem personalizada sobre disponibilidade</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="marcaSort" class="form-label">Ordem de Exibição</label>
                                <input type="number" class="form-control" id="marcaSort" name="sort_order" value="0">
                                <div class="form-text">Quanto menor o número, mais prioritário</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="marcaBanner" class="form-label">URL do Banner</label>
                                <input type="text" class="form-control" id="marcaBanner" name="banner" onchange="previewImage('marcaBannerPreview', this.value)">
                                <div class="form-text">Imagem principal da marca</div>
                                <img id="marcaBannerPreview" class="image-preview">
                            </div>
                            
                            <div class="col-md-12">
                                <label for="marcaBtn" class="form-label">URL do Botão</label>
                                <input type="text" class="form-control" id="marcaBtn" name="btn_image" onchange="previewImage('marcaBtnPreview', this.value)">
                                <div class="form-text">Imagem para botão da marca</div>
                                <img id="marcaBtnPreview" class="image-preview">
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Marca</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de Produto (Adicionar/Editar) -->
    <div class="modal fade" id="produtoModal" tabindex="-1" aria-labelledby="produtoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="produtoModalLabel">Novo Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" id="formProduto">
                    <input type="hidden" name="action" value="produto">
                    <input type="hidden" name="act" value="add" id="produtoAct">
                    <input type="hidden" name="id" value="" id="produtoId">
                    
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="produtoName" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="produtoName" name="name" required>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="produtoBrand" class="form-label">Marca</label>
                                <select class="form-select" id="produtoBrand" name="brand_id">
                                    <option value="0">Sem marca</option>
                                    <?php foreach ($brandsSelect as $brand): ?>
                                        <option value="<?= $brand['id'] ?>">
                                            <?= htmlspecialchars($brand['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="produtoDesc" class="form-label">Descrição</label>
                                <textarea class="form-control" id="produtoDesc" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="produtoPrice" class="form-label">Preço (R$)</label>
                                <div class="input-group mb-1">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="produtoPrice" name="price" value="0.00" onchange="calcProdutoProfit()">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="produtoPromo" class="form-label">Preço Promocional (R$)</label>
                                <div class="input-group mb-1">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="produtoPromo" name="promo_price" value="0.00" onchange="calcProdutoProfit()">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="produtoCost" class="form-label">Custo (R$)</label>
                                <div class="input-group mb-1">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="produtoCost" name="cost" value="0.00" onchange="calcProdutoProfit()">
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="row align-items-center">
                                            <div class="col-md-4">
                                                <div class="form-text mb-1">Lucro Estimado:</div>
                                                <span id="produtoLucro" class="fs-5 fw-semibold">R$ 0,00</span>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <div class="form-text mb-1">Margem de Lucro:</div>
                                                <span id="produtoMargemPercent" class="fs-5 fw-semibold">0.0%</span>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="progress">
                                                    <div id="produtoMargemBar" class="progress-bar bg-success" role="progressbar" 
                                                         style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="produtoCategory" class="form-label">Categoria</label>
                                <select class="form-select" id="produtoCategory" name="category">
                                    <option value="">Selecione a categoria</option>
                                    <?php foreach ($distinctCategories as $category): ?>
                                        <option value="<?= htmlspecialchars($category) ?>">
                                            <?= htmlspecialchars($category) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-7">
                                <label for="produtoImagem" class="form-label">URL da Imagem</label>
                                <input type="text" class="form-control" id="produtoImagem" name="image_url" onchange="previewImage('produtoImagePreview', this.value)">
                                <img id="produtoImagePreview" class="image-preview mt-2">
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="produtoActive" name="active" value="1" checked>
                                    <label class="form-check-label" for="produtoActive">Produto Ativo</label>
                                </div>
                                <div class="form-text">Produtos inativos não são exibidos no site</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Produto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes do Produto -->
    <div class="modal fade" id="detalheProdutoModal" tabindex="-1" aria-labelledby="detalheProdutoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalheProdutoModalLabel">Detalhes do Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando informações do produto...</p>
                    </div>
                    <div id="detalhesProdutoContent" style="display:none;">
                        <!-- Conteúdo carregado via AJAX -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnEditarProdutoDetalhe">Editar Produto</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Exclusão (Genérico) -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="confirmDeleteBody">
                    Tem certeza que deseja excluir este item?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" id="deleteAction">
                        <input type="hidden" name="act" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Funções de utilidade
function updateQueryParam(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    history.replaceState(null, '', url.toString());
}

// Preview de imagens
function previewImage(previewId, imgUrl) {
    const preview = document.getElementById(previewId);
    if (!imgUrl) {
        preview.style.display = 'none';
        return;
    }
    
    preview.src = imgUrl;
    preview.style.display = 'block';
}

// Funções para edição de marca
function editMarca(id) {
    // Limpa o formulário
    document.getElementById('formMarca').reset();
    
    // Atualiza título do modal
    document.getElementById('marcaModalLabel').textContent = `Editar Marca #${id}`;
    document.getElementById('marcaAct').value = 'edit';
    document.getElementById('marcaId').value = id;
    
    // Buscar dados da marca
    fetch(`?ajax=marca_info&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const marca = data.data;
                document.getElementById('marcaSlug').value = marca.slug || '';
                document.getElementById('marcaName').value = marca.name || '';
                document.getElementById('marcaType').value = marca.brand_type || '';
                document.getElementById('marcaBanner').value = marca.banner || '';
                document.getElementById('marcaBtn').value = marca.btn_image || '';
                document.getElementById('marcaStock').value = marca.stock || 1;
                document.getElementById('marcaSort').value = marca.sort_order || 0;
                document.getElementById('stockMessage').value = marca.stock_message || '';
                
                // Exibir previews de imagens se disponíveis
                previewImage('marcaBannerPreview', marca.banner);
                previewImage('marcaBtnPreview', marca.btn_image);
                
                // Abre o modal
                const modal = new bootstrap.Modal(document.getElementById('marcaModal'));
                modal.show();
            } else {
                alert('Erro ao carregar dados da marca: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao buscar informações da marca. Tente novamente.');
        });
}

// Preparar modal para nova marca
document.getElementById('marcaModal').addEventListener('show.bs.modal', function (event) {
    if (event.relatedTarget) { // Se foi aberto pelo botão "Nova Marca", não pelo JS
        document.getElementById('marcaModalLabel').textContent = 'Nova Marca';
        document.getElementById('marcaAct').value = 'add';
        document.getElementById('marcaId').value = '';
        document.getElementById('formMarca').reset();
        
        // Limpar previews
        document.getElementById('marcaBannerPreview').style.display = 'none';
        document.getElementById('marcaBtnPreview').style.display = 'none';
    }
});

// Funções para edição de produto
function editProduto(id) {
    // Limpa o formulário
    document.getElementById('formProduto').reset();
    
    // Atualiza título do modal
    document.getElementById('produtoModalLabel').textContent = `Editar Produto #${id}`;
    document.getElementById('produtoAct').value = 'edit';
    document.getElementById('produtoId').value = id;
    
    // Buscar dados do produto
    fetch(`?ajax=produto_info&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const produto = data.data;
                document.getElementById('produtoName').value = produto.name || '';
                document.getElementById('produtoBrand').value = produto.brand_id || 0;
                document.getElementById('produtoDesc').value = produto.description || '';
                document.getElementById('produtoPrice').value = produto.price || 0;
                document.getElementById('produtoPromo').value = produto.promo_price || 0;
                document.getElementById('produtoCost').value = produto.cost || 0;
                document.getElementById('produtoCategory').value = produto.category || '';
                document.getElementById('produtoImagem').value = produto.image_url || '';
                document.getElementById('produtoActive').checked = produto.active == 1;
                
                // Exibir preview de imagem se disponível
                previewImage('produtoImagePreview', produto.image_url);
                
                // Calcular e mostrar lucro
                calcProdutoProfit();
                
                // Abre o modal
                const modal = new bootstrap.Modal(document.getElementById('produtoModal'));
                modal.show();
            } else {
                alert('Erro ao carregar dados do produto: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao buscar informações do produto. Tente novamente.');
        });
}

// Visualizar detalhes do produto
function viewProdutoDetalhes(id) {
    const modal = new bootstrap.Modal(document.getElementById('detalheProdutoModal'));
    modal.show();
    
    // Mostrar spinner e esconder conteúdo anterior
    document.querySelector('#detalheProdutoModal .spinner-border').parentElement.style.display = 'block';
    document.getElementById('detalhesProdutoContent').style.display = 'none';
    
    // Configurar botão de edição
    document.getElementById('btnEditarProdutoDetalhe').onclick = function() {
        modal.hide();
        editProduto(id);
    };
    
    // Carregar dados
    fetch(`?ajax=produto_detail&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const produto = data.data;
                
                // Calcular lucro
                const price = parseFloat(produto.price) || 0;
                const promoPrice = parseFloat(produto.promo_price) || 0;
                const cost = parseFloat(produto.cost) || 0;
                
                const sellPrice = (promoPrice > 0) ? promoPrice : price;
                const profit = sellPrice - cost;
                const profitClass = profit >= 0 ? 'text-success' : 'text-danger';
                const profitPrefix = profit >= 0 ? '+' : '';
                
                const margin = (sellPrice > 0) ? ((profit / sellPrice) * 100).toFixed(1) : '0.0';
                
                // Formatar dados
                const formattedPrice = parseFloat(price).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const formattedPromo = promoPrice > 0 ? parseFloat(promoPrice).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'N/A';
                const formattedCost = parseFloat(cost).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const formattedProfit = parseFloat(Math.abs(profit)).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                // Construir HTML
                let html = `
                    <h4>${produto.name}</h4>
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">ID:</div>
                                <div class="col-md-8">${produto.id}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Marca:</div>
                                <div class="col-md-8">${produto.brand_name || 'Sem marca'}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Categoria:</div>
                                <div class="col-md-8">${produto.category || 'Não categorizado'}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Status:</div>
                                <div class="col-md-8">
                                    ${produto.active == 1 
                                    ? '<span class="badge bg-success">Ativo</span>' 
                                    : '<span class="badge bg-danger">Inativo</span>'}
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Preço Normal:</div>
                                <div class="col-md-8">R$ ${formattedPrice}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Preço Promocional:</div>
                                <div class="col-md-8">
                                    ${promoPrice > 0 
                                    ? `R$ ${formattedPromo} <span class="badge bg-danger ms-2">Promoção Ativa</span>` 
                                    : 'Não possui'}
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Custo:</div>
                                <div class="col-md-8">R$ ${formattedCost}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Lucro Estimado:</div>
                                <div class="col-md-8">
                                    <span class="${profitClass}">${profitPrefix}R$ ${formattedProfit}</span>
                                    <span class="badge bg-light text-dark ms-2">${margin}% de margem</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            ${produto.image_url 
                            ? `<img src="${produto.image_url}" alt="${produto.name}" class="img-fluid img-thumbnail" style="max-height: 200px;">` 
                            : '<div class="bg-light p-4 rounded"><i class="fas fa-image fa-3x text-muted"></i><p class="mt-2">Sem imagem</p></div>'}
                        </div>
                    </div>
                    
<div class="col-12">
                            <h5>Descrição</h5>
                            <div class="card bg-light">
                                <div class="card-body">
                                    ${produto.description || '<em class="text-muted">Sem descrição</em>'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Adicionar metadata e datas
                if (produto.created_at) {
                    const created = new Date(produto.created_at).toLocaleString('pt-BR');
                    html += `
                        <div class="mt-3 text-muted small">
                            <div>Data de cadastro: ${created}</div>
                        </div>
                    `;
                }
                
                // Atualizar o conteúdo
                document.getElementById('detalhesProdutoContent').innerHTML = html;
                
                // Esconder spinner e mostrar conteúdo
                document.querySelector('#detalheProdutoModal .spinner-border').parentElement.style.display = 'none';
                document.getElementById('detalhesProdutoContent').style.display = 'block';
            } else {
                alert('Erro ao carregar dados do produto: ' + data.message);
                modal.hide();
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao buscar detalhes do produto. Tente novamente.');
            modal.hide();
        });
}

// Cálculos de lucro para formulário de produto
function calcProdutoProfit() {
    const price = parseFloat(document.getElementById('produtoPrice').value) || 0;
    const promoPrice = parseFloat(document.getElementById('produtoPromo').value) || 0;
    const cost = parseFloat(document.getElementById('produtoCost').value) || 0;
    
    const sellPrice = (promoPrice > 0) ? promoPrice : price;
    const profit = sellPrice - cost;
    
    // Margem de lucro em percentual (sobre preço de venda)
    let margin = 0;
    if (sellPrice > 0) {
        margin = (profit / sellPrice) * 100;
    }
    
    // Formatar valores
    const formattedProfit = Math.abs(profit).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const profitClass = profit >= 0 ? 'text-success' : 'text-danger';
    const profitPrefix = profit >= 0 ? '+' : '-';
    
    // Atualizar elementos na interface
    document.getElementById('produtoLucro').className = profitClass;
    document.getElementById('produtoLucro').textContent = `${profitPrefix}R$ ${formattedProfit}`;
    
    document.getElementById('produtoMargemPercent').textContent = `${margin.toFixed(1)}%`;
    
    const progressBar = document.getElementById('produtoMargemBar');
    progressBar.style.width = `${Math.min(Math.abs(margin), 100)}%`;
    progressBar.className = profit >= 0 ? 'progress-bar bg-success' : 'progress-bar bg-danger';
}

// Preparar modal para novo produto
document.getElementById('produtoModal').addEventListener('show.bs.modal', function (event) {
    if (event.relatedTarget) { // Se foi aberto pelo botão "Novo Produto", não pelo JS
        document.getElementById('produtoModalLabel').textContent = 'Novo Produto';
        document.getElementById('produtoAct').value = 'add';
        document.getElementById('produtoId').value = '';
        document.getElementById('formProduto').reset();
        
        // Limpar previews
        document.getElementById('produtoImagePreview').style.display = 'none';
        
        // Resetar cálculos de lucro
        document.getElementById('produtoLucro').className = '';
        document.getElementById('produtoLucro').textContent = 'R$ 0,00';
        document.getElementById('produtoMargemPercent').textContent = '0.0%';
        document.getElementById('produtoMargemBar').style.width = '0%';
        document.getElementById('produtoMargemBar').className = 'progress-bar bg-success';
    }
});

// Funções para modificação em massa de preços
function calculateProductProfit(productId) {
    const priceEl = document.querySelector(`.product-price[data-product-id="${productId}"]`);
    const promoEl = document.querySelector(`.product-promo[data-product-id="${productId}"]`);
    const costEl = document.querySelector(`.product-cost[data-product-id="${productId}"]`);
    const profitEl = document.querySelector(`.product-profit[data-product-id="${productId}"]`);
    const marginEl = document.querySelector(`.product-margin[data-product-id="${productId}"]`);
    const barEl = document.querySelector(`.product-margin-bar[data-product-id="${productId}"]`);
    
    if (!priceEl || !costEl || !profitEl || !marginEl || !barEl) return;
    
    const price = parseFloat(priceEl.value) || 0;
    const promoPrice = promoEl ? (parseFloat(promoEl.value) || 0) : 0;
    const cost = parseFloat(costEl.value) || 0;
    
    const sellPrice = (promoPrice > 0) ? promoPrice : price;
    const profit = sellPrice - cost;
    
    // Margem de lucro em percentual
    let margin = 0;
    if (sellPrice > 0) {
        margin = (profit / sellPrice) * 100;
    }
    
    // Formatar profit
    const formattedProfit = Math.abs(profit).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const profitPrefix = profit >= 0 ? '+' : '-';
    
    // Atualizar elementos UI
    profitEl.value = `${profitPrefix}R$ ${formattedProfit}`;
    profitEl.dataset.value = profit;
    profitEl.classList.remove('text-success', 'text-danger');
    profitEl.classList.add(profit >= 0 ? 'text-success' : 'text-danger');
    
    marginEl.value = `${margin.toFixed(1)}%`;
    marginEl.dataset.value = margin;
    
    barEl.style.width = `${Math.min(Math.abs(margin), 100)}%`;
    barEl.classList.remove('profit-positive', 'profit-negative');
    barEl.classList.add(profit >= 0 ? 'profit-positive' : 'profit-negative');
    
    // Recalcular estatísticas gerais
    updateProfitStatistics();
}

// Confirmar exclusão de marca
function confirmDeleteMarca(id, nome) {
    document.getElementById('confirmDeleteModalLabel').textContent = 'Confirmar Exclusão de Marca';
    document.getElementById('confirmDeleteBody').innerHTML = `
        <p>Tem certeza que deseja excluir a marca <strong>${nome}</strong>?</p>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Esta ação não poderá ser desfeita. Se houver produtos associados a esta marca, eles permanecerão no sistema, mas sem associação com a marca.
        </div>
    `;
    
    document.getElementById('deleteAction').value = 'marca';
    document.getElementById('deleteId').value = id;
    
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
}

// Confirmar exclusão de produto
function confirmDeleteProduto(id, nome) {
    document.getElementById('confirmDeleteModalLabel').textContent = 'Confirmar Exclusão de Produto';
    document.getElementById('confirmDeleteBody').innerHTML = `
        <p>Tem certeza que deseja excluir o produto <strong>${nome}</strong>?</p>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Esta ação não poderá ser desfeita. Se este produto estiver associado a pedidos existentes, esses registros serão mantidos.
        </div>
    `;
    
    document.getElementById('deleteAction').value = 'produto';
    document.getElementById('deleteId').value = id;
    
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
}

// Funções para Edição em Massa
function switchMassMode(mode) {
    if (mode === 'edit') {
        document.getElementById('mass-edit-container').style.display = 'block';
        document.getElementById('mass-add-container').style.display = 'none';
        document.getElementById('mass-edit-filters').style.display = 'block';
        document.getElementById('btn-mass-edit').classList.add('active');
        document.getElementById('btn-mass-add').classList.remove('active');
    } else if (mode === 'add') {
        document.getElementById('mass-edit-container').style.display = 'none';
        document.getElementById('mass-add-container').style.display = 'block';
        document.getElementById('mass-edit-filters').style.display = 'none';
        document.getElementById('btn-mass-edit').classList.remove('active');
        document.getElementById('btn-mass-add').classList.add('active');
    }
}

// Adicionar linha na inserção em massa de produtos
document.getElementById('btn-add-row').addEventListener('click', function() {
    addMassAddRow();
});

// Adicionar 5 linhas na inserção em massa
document.getElementById('btn-add-more-rows').addEventListener('click', function() {
    for (let i = 0; i < 5; i++) {
        addMassAddRow();
    }
});

// Limpar todas as linhas de inserção em massa
document.getElementById('btn-clear-all').addEventListener('click', function() {
    if (confirm('Tem certeza que deseja limpar todos os campos?')) {
        const tbody = document.querySelector('#massAddTable tbody');
        // Manter apenas a primeira linha e limpar seus campos
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 0) {
            const inputs = rows[0].querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'checkbox') {
                    input.checked = true;
                } else {
                    input.value = '';
                }
            });
            
            // Remover as demais linhas
            for (let i = 1; i < rows.length; i++) {
                rows[i].remove();
            }
        }
    }
});

// Função auxiliar para adicionar linha na tabela de inserção em massa
function addMassAddRow() {
    const tbody = document.querySelector('#massAddTable tbody');
    const rowCount = tbody.querySelectorAll('tr').length;
    
    const newRow = document.createElement('tr');
    newRow.className = 'mass-add-row';
    
    // Criar HTML da linha com índice correto
    newRow.innerHTML = `
        <td>
            <select name="mass_new[${rowCount}][brand_id]" class="form-select form-select-sm">
                <option value="0">Sem marca</option>
                <?php foreach ($brandsSelect as $brand): ?>
                    <option value="<?= $brand['id'] ?>">
                        <?= htmlspecialchars($brand['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" name="mass_new[${rowCount}][name]" class="form-control form-control-sm" placeholder="Nome do produto">
        </td>
        <td>
            <textarea name="mass_new[${rowCount}][description]" rows="2" class="form-control form-control-sm" placeholder="Descrição breve"></textarea>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">R$</span>
                <input type="number" step="0.01" min="0" name="mass_new[${rowCount}][price]" 
                       class="form-control form-control-sm" placeholder="0.00"
                       onchange="calcMassAddProfit(${rowCount})">
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">R$</span>
                <input type="number" step="0.01" min="0" name="mass_new[${rowCount}][promo_price]" 
                       class="form-control form-control-sm" placeholder="0.00"
                       onchange="calcMassAddProfit(${rowCount})">
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">R$</span>
                <input type="number" step="0.01" min="0" name="mass_new[${rowCount}][cost]" 
                       class="form-control form-control-sm" placeholder="0.00"
                       onchange="calcMassAddProfit(${rowCount})">
            </div>
        </td>
        <td>
            <select name="mass_new[${rowCount}][category]" class="form-select form-select-sm">
                <option value="">Selecione...</option>
                <?php foreach ($distinctCategories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>">
                        <?= htmlspecialchars($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="text-center">
            <div class="form-check form-switch d-flex justify-content-center">
                <input class="form-check-input" type="checkbox" name="mass_new[${rowCount}][active]" value="1" checked>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    
    // Adicionar event listener para o botão de remoção
    newRow.querySelector('.btn-remove-row').addEventListener('click', function() {
        newRow.remove();
    });
}

// Delegação de evento para botões de remoção de linha
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-remove-row')) {
        e.target.closest('tr').remove();
    }
});

// Funções para cálculo de lucro em massa
function calcMassAddProfit(rowIndex) {
    // Implementação futura se necessário
}

// Funções para ajustes em massa nos preços
document.getElementById('btn-apply-prices').addEventListener('click', function() {
    const type = document.getElementById('prices-adjustment-type').value;
    const value = parseFloat(document.getElementById('prices-adjustment-value').value);
    
    if (isNaN(value)) {
        alert('Por favor, digite um valor válido para o ajuste.');
        return;
    }
    
    const priceFields = document.querySelectorAll('.product-price');
    priceFields.forEach(field => {
        const currentPrice = parseFloat(field.value) || 0;
        let newPrice = currentPrice;
        
        if (type === 'percent') {
            newPrice = currentPrice * (1 + (value / 100));
        } else { // fixed
            newPrice = currentPrice + value;
        }
        
        // Garantir que o preço não fique negativo
        newPrice = Math.max(0, newPrice);
        
        field.value = newPrice.toFixed(2);
        
        // Recalcular lucro
        const productId = field.dataset.productId;
        calculateProductProfit(productId);
    });
});

document.getElementById('btn-apply-promos').addEventListener('click', function() {
    const type = document.getElementById('promos-adjustment-type').value;
    const value = parseFloat(document.getElementById('promos-adjustment-value').value);
    
    if (isNaN(value)) {
        alert('Por favor, digite um valor válido para o ajuste.');
        return;
    }
    
    const promoFields = document.querySelectorAll('.product-promo');
    promoFields.forEach(field => {
        const currentPromo = parseFloat(field.value) || 0;
        let newPromo = currentPromo;
        
        if (type === 'percent') {
            newPromo = currentPromo * (1 + (value / 100));
        } else { // fixed
            newPromo = currentPromo + value;
        }
        
        // Garantir que o preço não fique negativo
        newPromo = Math.max(0, newPromo);
        
        field.value = newPromo.toFixed(2);
        
        // Recalcular lucro
        const productId = field.dataset.productId;
        calculateProductProfit(productId);
    });
});

document.getElementById('btn-apply-costs').addEventListener('click', function() {
    const type = document.getElementById('costs-adjustment-type').value;
    const value = parseFloat(document.getElementById('costs-adjustment-value').value);
    
    if (isNaN(value)) {
        alert('Por favor, digite um valor válido para o ajuste.');
        return;
    }
    
    const costFields = document.querySelectorAll('.product-cost');
    costFields.forEach(field => {
        const currentCost = parseFloat(field.value) || 0;
        let newCost = currentCost;
        
        if (type === 'percent') {
            newCost = currentCost * (1 + (value / 100));
        } else { // fixed
            newCost = currentCost + value;
        }
        
        // Garantir que o custo não fique negativo
        newCost = Math.max(0, newCost);
        
        field.value = newCost.toFixed(2);
        
        // Recalcular lucro
        const productId = field.dataset.productId;
        calculateProductProfit(productId);
    });
});

// Aplicar margem em massa
document.getElementById('btn-apply-margin').addEventListener('click', function() {
    const margin = parseFloat(document.getElementById('margin-adjustment-value').value);
    
    if (isNaN(margin)) {
        alert('Por favor, digite um valor válido para a margem.');
        return;
    }
    
    if (confirm(`Isso irá recalcular os preços de todos os produtos com base no custo + margem de ${margin}%. Deseja continuar?`)) {
        const rows = document.querySelectorAll('.mass-edit-row');
        rows.forEach(row => {
            const productId = row.dataset.productId;
            if (!productId) return;
            
            const costField = row.querySelector(`.product-cost[data-product-id="${productId}"]`);
            const priceField = row.querySelector(`.product-price[data-product-id="${productId}"]`);
            const promoField = row.querySelector(`.product-promo[data-product-id="${productId}"]`);
            
            if (!costField || !priceField) return;
            
            const cost = parseFloat(costField.value) || 0;
            
            // Novo preço baseado no custo + margem desejada
            // Formula: preço = custo / (1 - margem/100)
            let newPrice = 0;
            if (margin < 100) {
                newPrice = cost / (1 - (margin / 100));
            } else {
                // Se margem for 100% ou mais, aplica um multiplicador sobre o custo
                const multiplier = 1 + (margin / 100);
                newPrice = cost * multiplier;
            }
            
            priceField.value = newPrice.toFixed(2);
            
            // Limpar preço promocional
            if (promoField) {
                promoField.value = '0.00';
            }
            
            // Recalcular lucro
            calculateProductProfit(productId);
        });
    }
});

// Funções de estatísticas de lucro
function updateProfitStatistics() {
    try {
        // Calcular estatísticas de todos os produtos exibidos
        const profitFields = document.querySelectorAll('.product-profit');
        const marginFields = document.querySelectorAll('.product-margin');
        const promoFields = document.querySelectorAll('.product-promo');
        
        let totalProfit = 0;
        let totalMarginSum = 0;
        let promoCount = 0;
        
        profitFields.forEach(field => {
            totalProfit += parseFloat(field.dataset.value) || 0;
        });
        
        marginFields.forEach(field => {
            totalMarginSum += parseFloat(field.dataset.value) || 0;
        });
        
        promoFields.forEach(field => {
            if (parseFloat(field.value) > 0) {
                promoCount++;
            }
        });
        
        const avgMargin = marginFields.length > 0 ? totalMarginSum / marginFields.length : 0;
        
        // Atualizar elementos na interface
        document.getElementById('avg-profit-percent').textContent = `${avgMargin.toFixed(1)}%`;
        document.getElementById('total-profit-value').textContent = `R$ ${Math.abs(totalProfit).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('promo-count').textContent = `${promoCount} produtos`;
        
        // Colorir o lucro total
        document.getElementById('total-profit-value').className = totalProfit >= 0 ? 'mb-0 text-success' : 'mb-0 text-danger';
    } catch (error) {
        console.error('Erro ao calcular estatísticas:', error);
    }
}

// Funções de confirmação para submit de formulários
function confirmMassEdit() {
    return confirm('Tem certeza que deseja salvar as alterações em massa para todos os produtos listados?');
}

function confirmMassAdd() {
    const rows = document.querySelectorAll('#massAddTable tbody tr');
    let validRows = 0;
    
    rows.forEach(row => {
        const nameField = row.querySelector('input[name*="[name]"]');
        if (nameField && nameField.value.trim() !== '') {
            validRows++;
        }
    });
    
    if (validRows === 0) {
        alert('Não há produtos válidos para inserir. Preencha pelo menos o nome de um produto.');
        return false;
    }
    
    return confirm(`Tem certeza que deseja inserir ${validRows} novos produtos?`);
}

function confirmMassBrandsEdit() {
    return confirm('Tem certeza que deseja salvar as alterações em massa para todas as marcas listadas?');
}

function confirmMassPricesEdit() {
    return confirm('Tem certeza que deseja salvar as alterações de preços e custos?');
}

// Função para ordenação de tabelas
function sortTable(column, direction) {
    const url = new URL(window.location);
    url.searchParams.set('sort', column);
    url.searchParams.set('dir', direction);
    window.location.href = url.toString();
}

// Inicializar cálculos ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar contadores de lucro para produtos atuais
    const productRows = document.querySelectorAll('[data-product-id]');
    productRows.forEach(row => {
        const productId = row.dataset.productId;
        if (productId) {
            calculateProductProfit(productId);
        }
    });
    
    // Atualizar estatísticas iniciais
    updateProfitStatistics();
    
    // Adicionar listeners para remoção de linha 
    document.querySelectorAll('.btn-remove-row').forEach(btn => {
        btn.addEventListener('click', function() {
            btn.closest('tr').remove();
        });
    });
});
</script>
</body>
</html>