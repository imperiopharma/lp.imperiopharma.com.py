<?php
// Verificar se a sessão já está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação sem redirecionar
$userLoggedIn = isset($_SESSION['admin_logged_in']) ? true : false;

// Obter o título da página
$pageTitle = 'Dashboard';
$currentPage = $_GET['page'] ?? 'dashboard';

switch ($currentPage) {
    case 'pedidos': $pageTitle = 'Gestão de Pedidos'; break;
    case 'rastreios': $pageTitle = 'Rastreio de Entregas'; break;
    case 'rastreios_novo': $pageTitle = 'Sistema de Rastreio'; break;
    case 'financeiro': $pageTitle = 'Resumo Financeiro'; break;
    case 'financeiro_completo': $pageTitle = 'Relatório Financeiro Completo'; break;
    case 'cupons': $pageTitle = 'Gerenciamento de Cupons'; break;
    case 'marcas_produtos': $pageTitle = 'Marcas e Produtos'; break;
    case 'usuarios': $pageTitle = 'Gerenciamento de Usuários'; break;
    case 'configuracoes': $pageTitle = 'Configurações do Sistema'; break;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Painel NexGen - Império Pharma">
  <meta name="robots" content="noindex, nofollow">
  
  <title><?= $pageTitle ?> - Império Pharma NexGen</title>
  
  <!-- Favicon -->
  <link rel="shortcut icon" href="assets/img/favicon.png" type="image/png">
  
  <!-- CSS Framework e Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- DataTables - carregado apenas quando necessário -->
  <?php if(in_array($currentPage, ['pedidos', 'financeiro_completo', 'cupons', 'usuarios'])): ?>
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
  <?php endif; ?>
  
  <!-- CSS Personalizado -->
  <link rel="stylesheet" href="assets/css/admin-styles.css">
  
  <style>
    /* Estilos Base para o Novo Header e Sidebar */
    :root {
      --primary: #0053a6;
      --sidebar-width: 260px;
      --sidebar-width-collapsed: 70px;
      --header-height: 60px;
      --header-bg: rgba(255, 255, 255, 0.95);
      --menu-bg: linear-gradient(180deg, #2c3e50 0%, #1a2530 100%);
      --menu-text: #ecf0f1;
      --menu-hover: rgba(255, 255, 255, 0.1);
      --menu-active: rgba(13, 110, 253, 0.25);
      --menu-border: rgba(255, 255, 255, 0.05);
    }
    
    body {
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f5f7fa;
      overflow-x: hidden;
    }
    
    /* Header Fixo */
    .nexgen-header {
      position: fixed;
      top: 0;
      left: var(--sidebar-width);
      right: 0;
      height: var(--header-height);
      background: var(--header-bg);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(0,0,0,0.05);
      box-shadow: 0 1px 15px rgba(0,0,0,0.04);
      z-index: 1000;
      transition: left 0.3s ease;
      padding: 0 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .header-left {
      display: flex;
      align-items: center;
    }
    
    .sidebar-toggle {
      background: transparent;
      border: none;
      color: #555;
      font-size: 18px;
      cursor: pointer;
      margin-right: 15px;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .sidebar-toggle:hover {
      background: rgba(0,0,0,0.05);
      color: var(--primary);
    }
    
    .page-title {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
      color: #333;
    }
    
    .header-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .search-box {
      position: relative;
      margin-right: 15px;
    }
    
    .search-input {
      background: rgba(255,255,255,0.8);
      border: 1px solid rgba(0,0,0,0.08);
      border-radius: 25px;
      padding: 8px 15px 8px 40px;
      width: 240px;
      transition: all 0.3s ease;
      font-size: 0.9rem;
    }
    
    .search-input:focus {
      background: #fff;
      box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
      border-color: rgba(13, 110, 253, 0.4);
      width: 280px;
    }
    
    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
      font-size: 0.9rem;
    }
    
    .header-notifications,
    .header-user {
      position: relative;
    }
    
    .header-btn {
      background: rgba(0,0,0,0.03);
      border: none;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      color: #555;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .header-btn:hover {
      background: rgba(0,0,0,0.07);
      color: var(--primary);
    }
    
    .notification-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      min-width: 18px;
      height: 18px;
      background: #dc3545;
      color: #fff;
      font-size: 10px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 5px;
      font-weight: 600;
    }
    
    .user-btn {
      display: flex;
      align-items: center;
      background: rgba(0,0,0,0.03);
      border: none;
      border-radius: 25px;
      padding: 5px 15px 5px 5px;
      gap: 8px;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    
    .user-btn:hover {
      background: rgba(0,0,0,0.07);
    }
    
    .user-avatar {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      overflow: hidden;
      background: #e9ecef;
    }
    
    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .user-name {
      font-size: 0.9rem;
      font-weight: 500;
      color: #333;
      max-width: 120px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    /* Sidebar */
    .nexgen-sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-width);
      height: 100vh;
      background: var(--menu-bg);
      z-index: 1100;
      transition: all 0.3s ease;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      overflow-y: auto;
      overflow-x: hidden;
    }
    
    .sidebar-header {
      height: var(--header-height);
      padding: 0 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--menu-border);
    }
    
    .logo-container {
      display: flex;
      align-items: center;
    }
    
    .logo {
      height: 40px;
      max-width: 180px;
      object-fit: contain;
    }
    
    .logo-icon {
      height: 30px;
      width: 30px;
      display: none;
    }
    
    .sidebar-profile {
      padding: 20px;
      border-bottom: 1px solid var(--menu-border);
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .profile-avatar {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      overflow: hidden;
      background: rgba(255,255,255,0.1);
      flex-shrink: 0;
    }
    
    .profile-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .profile-info {
      display: flex;
      flex-direction: column;
    }
    
    .profile-name {
      font-weight: 600;
      font-size: 0.95rem;
      color: var(--menu-text);
      margin: 0;
    }
    
    .profile-role {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.6);
    }
    
    .menu-section {
      padding: 10px 0;
    }
    
    .menu-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      color: rgba(255,255,255,0.4);
      font-weight: 500;
      letter-spacing: 0.5px;
      padding: 10px 20px;
      margin-bottom: 5px;
    }
    
    .menu-items {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .menu-item {
      position: relative;
    }
    
    .menu-link {
      display: flex;
      align-items: center;
      padding: 10px 20px;
      color: var(--menu-text);
      text-decoration: none;
      transition: all 0.3s ease;
      border-left: 3px solid transparent;
      gap: 12px;
    }
    
    .menu-link:hover {
      background: var(--menu-hover);
      color: #fff;
    }
    
    .menu-link.active {
      background: var(--menu-active);
      color: #fff;
      border-left-color: var(--primary);
    }
    
    .menu-icon {
      width: 22px;
      text-align: center;
      font-size: 1.1rem;
    }
    
    .menu-text {
      font-size: 0.9rem;
      font-weight: 500;
    }
    
    .badge-menu {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      background: var(--primary);
      color: #fff;
      font-size: 0.7rem;
      padding: 2px 6px;
      border-radius: 10px;
      min-width: 20px;
      text-align: center;
    }
    
    .menu-arrow {
      margin-left: auto;
      font-size: 0.8rem;
      transition: transform 0.3s ease;
    }
    
    .menu-link[aria-expanded="true"] .menu-arrow {
      transform: rotate(90deg);
    }
    
    .submenu {
      list-style: none;
      padding: 0;
      margin: 0;
      background: rgba(0,0,0,0.15);
      overflow: hidden;
      max-height: 0;
      transition: max-height 0.3s ease;
    }
    
    .submenu.show {
      max-height: 500px;
    }
    
    .submenu-item {
      padding-left: 30px;
    }
    
    .submenu-link {
      display: flex;
      align-items: center;
      padding: 8px 15px;
      color: rgba(255,255,255,0.8);
      text-decoration: none;
      transition: all 0.3s ease;
      gap: 10px;
      font-size: 0.85rem;
    }
    
    .submenu-link:hover {
      background: var(--menu-hover);
      color: #fff;
    }
    
    .submenu-link.active {
      color: #fff;
      background: var(--menu-active);
    }
    
    .submenu-icon {
      font-size: 0.8rem;
      width: 18px;
      text-align: center;
    }
    
    /* Sidebar Collapsed State */
    .sidebar-collapsed .nexgen-sidebar {
      width: var(--sidebar-width-collapsed);
    }
    
    .sidebar-collapsed .nexgen-header {
      left: var(--sidebar-width-collapsed);
    }
    
    .sidebar-collapsed .logo {
      display: none;
    }
    
    .sidebar-collapsed .logo-icon {
      display: block;
    }
    
    .sidebar-collapsed .sidebar-profile,
    .sidebar-collapsed .menu-label,
    .sidebar-collapsed .menu-text,
    .sidebar-collapsed .profile-info,
    .sidebar-collapsed .menu-arrow {
      display: none;
    }
    
    .sidebar-collapsed .menu-link {
      justify-content: center;
      padding: 15px;
    }
    
    .sidebar-collapsed .menu-icon {
      margin: 0;
      font-size: 1.25rem;
    }
    
    .sidebar-collapsed .badge-menu {
      position: absolute;
      top: 5px;
      right: 5px;
      transform: none;
      padding: 1px 5px;
      font-size: 0.65rem;
      min-width: 15px;
    }
    
    .sidebar-collapsed .submenu {
      position: absolute;
      left: 100%;
      top: 0;
      width: 200px;
      z-index: 1000;
      background: var(--menu-bg);
      box-shadow: 5px 0 15px rgba(0,0,0,0.1);
      border-radius: 0 5px 5px 0;
      display: none;
    }
    
    .sidebar-collapsed .menu-item:hover .submenu {
      display: block;
    }
    
    .sidebar-collapsed .submenu-item {
      padding-left: 0;
    }
    
    /* Conteúdo Principal */
    .nexgen-main {
      margin-left: var(--sidebar-width);
      padding-top: var(--header-height);
      min-height: 100vh;
      transition: margin-left 0.3s ease;
    }
    
    .sidebar-collapsed .nexgen-main {
      margin-left: var(--sidebar-width-collapsed);
    }
    
    .nexgen-content {
      padding: 20px;
    }
    
    /* Breadcrumb */
    .nexgen-breadcrumb {
      margin-bottom: 20px;
    }
    
    .breadcrumb {
      background: transparent;
      padding: 10px 0;
    }
    
    .breadcrumb-item a {
      color: #6c757d;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    
    .breadcrumb-item a:hover {
      color: var(--primary);
    }
    
    .breadcrumb-item.active {
      color: #333;
      font-weight: 500;
    }
    
    /* Dropdown no header */
    .nexgen-dropdown {
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 10px;
      min-width: 280px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 5px 25px rgba(0,0,0,0.1);
      z-index: 1000;
      display: none;
      padding: 10px 0;
      border: 1px solid rgba(0,0,0,0.08);
    }
    
    .nexgen-dropdown.show {
      display: block;
      animation: fadeInDown 0.3s ease;
    }
    
    .dropdown-header {
      padding: 10px 15px;
      border-bottom: 1px solid rgba(0,0,0,0.05);
      font-weight: 600;
      font-size: 0.9rem;
      color: #333;
    }
    
    .dropdown-item {
      padding: 10px 15px;
      display: flex;
      align-items: center;
      gap: 10px;
      color: #555;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    
    .dropdown-item:hover {
      background: rgba(0,0,0,0.03);
      color: var(--primary);
    }
    
    .dropdown-item-icon {
      width: 20px;
      text-align: center;
      color: #6c757d;
    }
    
    .dropdown-divider {
      height: 1px;
      background: rgba(0,0,0,0.05);
      margin: 5px 0;
    }
    
    /* Mobile adaptations */
    @media (max-width: 991.98px) {
      .nexgen-sidebar {
        left: -100%;
        width: 260px;
      }
      
      .nexgen-header {
        left: 0;
      }
      
      .nexgen-main {
        margin-left: 0;
      }
      
      .sidebar-visible .nexgen-sidebar {
        left: 0;
      }
      
      .search-input {
        width: 180px;
      }
      
      .search-input:focus {
        width: 220px;
      }
      
      .user-name {
        max-width: 80px;
      }
    }
    
    @media (max-width: 767.98px) {
      .search-box {
        display: none;
      }
      
      .user-name {
        display: none;
      }
      
      .user-btn {
        padding: 5px;
      }
      
      .nexgen-content {
        padding: 15px;
      }
    }
    
    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>

<body>
  <!-- Início da estrutura principal -->
  <div class="nexgen-wrapper">
    <!-- Sidebar -->
    <aside class="nexgen-sidebar">
      <div class="sidebar-header">
        <div class="logo-container">
          <img src="assets/img/logo-imperio.png" alt="Império Pharma" class="logo">
          <img src="assets/img/logo-icon.png" alt="Império Pharma" class="logo-icon">
        </div>
        <button class="sidebar-toggle d-lg-none" id="mobile-sidebar-toggle">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="sidebar-profile">
        <div class="profile-avatar">
          <img src="assets/img/user-placeholder.png" alt="Usuário">
        </div>
        <div class="profile-info">
          <h6 class="profile-name"><?= isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Administrador' ?></h6>
          <span class="profile-role"><?= isset($_SESSION['admin_role']) ? htmlspecialchars($_SESSION['admin_role']) : 'Gerente' ?></span>
        </div>
      </div>
      
      <div class="menu-section">
        <ul class="menu-items">
          <li class="menu-item">
            <a href="index.php?page=dashboard" class="menu-link <?= $currentPage == 'dashboard' ? 'active' : '' ?>">
              <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
              <span class="menu-text">Dashboard</span>
            </a>
          </li>
          
          <li class="menu-item">
            <a href="index.php?page=pedidos" class="menu-link <?= $currentPage == 'pedidos' ? 'active' : '' ?>">
              <span class="menu-icon"><i class="fas fa-shopping-cart"></i></span>
              <span class="menu-text">Pedidos</span>
              <?php
              // Contagem de pedidos pendentes
              $pendingOrderCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'PENDENTE'")->fetchColumn();
              if ($pendingOrderCount > 0):
              ?>
              <span class="badge-menu"><?= $pendingOrderCount ?></span>
              <?php endif; ?>
            </a>
          </li>
          
          <li class="menu-item">
            <a href="index.php?page=rastreios_novo" class="menu-link <?= in_array($currentPage, ['rastreios', 'rastreios_novo']) ? 'active' : '' ?>">
              <span class="menu-icon"><i class="fas fa-truck"></i></span>
              <span class="menu-text">Rastreios</span>
            </a>
          </li>
          
          <li class="menu-item">
            <a href="#financeiro-submenu" class="menu-link <?= in_array($currentPage, ['financeiro', 'financeiro_completo']) ? 'active' : '' ?>" data-bs-toggle="collapse" aria-expanded="<?= in_array($currentPage, ['financeiro', 'financeiro_completo']) ? 'true' : 'false' ?>">
              <span class="menu-icon"><i class="fas fa-chart-line"></i></span>
              <span class="menu-text">Financeiro</span>
              <span class="menu-arrow"><i class="fas fa-chevron-right"></i></span>
            </a>
            <ul class="submenu <?= in_array($currentPage, ['financeiro', 'financeiro_completo']) ? 'show' : '' ?>" id="financeiro-submenu">
              <li class="submenu-item">
                <a href="index.php?page=financeiro" class="submenu-link <?= $currentPage == 'financeiro' ? 'active' : '' ?>">
                  <span class="submenu-icon"><i class="fas fa-chart-bar"></i></span>
                  <span class="submenu-text">Resumo Financeiro</span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="index.php?page=financeiro_completo" class="submenu-link <?= $currentPage == 'financeiro_completo' ? 'active' : '' ?>">
                  <span class="submenu-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                  <span class="submenu-text">Relatório Completo</span>
                </a>
              </li>
            </ul>
          </li>
          
          <li class="menu-item">
            <a href="index.php?page=cupons" class="menu-link <?= $currentPage == 'cupons' ? 'active' : '' ?>">
              <span class="menu-icon"><i class="fas fa-ticket-alt"></i></span>
              <span class="menu-text">Cupons</span>
            </a>
          </li>
          
          <li class="menu-item">
            <a href="index.php?page=marcas_produtos" class="menu-link <?= $currentPage == 'marcas_produtos' ? 'active' : '' ?>">
              <span class="menu-icon"><i class="fas fa-tags"></i></span>
              <span class="menu-text">Marcas & Produtos</span>
            </a>
          </li>
          
          <li class="menu-item">
            <a href="index.php?page=usuarios" class="menu-link <?= $currentPage == 'usuarios' ? 'active' : '' ?>">
              <span class="menu-icon"><i class="fas fa-users"></i></span>
              <span class="menu-text">Usuários</span>
            </a>
          </li>
        </ul>
      </div>
      
      <div class="menu-section">
        <div class="menu-label">Sistema</div>
        <ul class="menu-items">
          <li class="menu-item">
            <a href="index.php?page=configuracoes" class="menu-link <?= $currentPage == 'configuracoes' ? 'active' : '' ?>">
              <span class="menu-icon"><i class="fas fa-cog"></i></span>
              <span class="menu-text">Configurações</span>
            </a>
          </li>
          
          <li class="menu-item">
            <a href="logout.php" class="menu-link logout-link">
              <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
              <span class="menu-text">Sair</span>
            </a>
          </li>
        </ul>
      </div>
    </aside>
    
    <!-- Header -->
    <header class="nexgen-header">
      <div class="header-left">
        <button class="sidebar-toggle" id="desktop-sidebar-toggle">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="page-title"><?= $pageTitle ?></h1>
      </div>
      
      <div class="header-actions">
        <div class="search-box">
          <i class="fas fa-search search-icon"></i>
          <input type="text" class="search-input" placeholder="Buscar...">
        </div>
        
        <div class="header-notifications">
          <button class="header-btn" id="notifications-btn">
            <i class="fas fa-bell"></i>
            <?php if ($pendingOrderCount > 0): ?>
            <span class="notification-badge"><?= $pendingOrderCount ?></span>
            <?php endif; ?>
          </button>
          
          <div class="nexgen-dropdown" id="notifications-dropdown">
            <div class="dropdown-header">Notificações</div>
            <?php if ($pendingOrderCount > 0): ?>
            <a href="index.php?page=pedidos&status=PENDENTE" class="dropdown-item">
              <div class="dropdown-item-icon text-warning"><i class="fas fa-clock"></i></div>
              <span><?= $pendingOrderCount ?> pedidos pendentes aguardando processamento</span>
            </a>
            <?php else: ?>
            <div class="dropdown-item">
              <div class="dropdown-item-icon"><i class="fas fa-check-circle"></i></div>
              <span>Nenhuma notificação no momento</span>
            </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="header-user">
          <button class="user-btn" id="user-btn">
            <div class="user-avatar">
              <img src="assets/img/user-placeholder.png" alt="Usuário">
            </div>
            <span class="user-name"><?= isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Usuário' ?></span>
          </button>
          
          <div class="nexgen-dropdown" id="user-dropdown">
            <div class="dropdown-header">Minha Conta</div>
            <a href="index.php?page=perfil" class="dropdown-item">
              <div class="dropdown-item-icon"><i class="fas fa-user"></i></div>
              <span>Perfil</span>
            </a>
            <a href="index.php?page=configuracoes" class="dropdown-item">
              <div class="dropdown-item-icon"><i class="fas fa-cog"></i></div>
              <span>Configurações</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="dropdown-item logout-link">
              <div class="dropdown-item-icon"><i class="fas fa-sign-out-alt"></i></div>
              <span>Sair</span>
            </a>
          </div>
        </div>
      </div>
    </header>
    
    <!-- Conteúdo Principal -->
    <main class="nexgen-main">
      <div class="nexgen-content">
        <!-- Breadcrumb -->
        <div class="nexgen-breadcrumb">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="index.php">Início</a></li>
              <?php if ($currentPage != 'dashboard'): ?>
              <li class="breadcrumb-item active" aria-current="page"><?= $pageTitle ?></li>
              <?php endif; ?>
            </ol>
          </nav>
        </div>
        
        <!-- Aqui entra o conteúdo da página -->