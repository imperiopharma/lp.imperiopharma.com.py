<?php
// Ponto de entrada principal do sistema
require_once 'inc/config.php';

// Iniciar a sessão se ainda não foi iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Página padrão é dashboard
$page = $_GET['page'] ?? 'dashboard';

// Lista de páginas válidas
$validPages = [
    'dashboard', 'pedidos', 'rastreios', 'rastreios_novo', 
    'financeiro', 'financeiro_completo', 'cupons', 
    'marcas_produtos', 'usuarios', 'configuracoes'
];

// Validação de segurança
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Incluir cabeçalho
include 'inc/cabecalho.php';

// Incluir página solicitada
$pageFile = "pages/{$page}.php";
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    echo "<div class='alert alert-danger'>Página não encontrada.</div>";
}

// Incluir rodapé
include 'inc/rodape.php';