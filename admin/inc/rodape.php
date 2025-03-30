<script>
// Script para o header e menu lateral NexGen
document.addEventListener('DOMContentLoaded', function() {
  // Toggle da sidebar
  const desktopToggle = document.getElementById('desktop-sidebar-toggle');
  const mobileToggle = document.getElementById('mobile-sidebar-toggle');
  const body = document.documentElement;
  
  function toggleSidebar() {
    body.classList.toggle('sidebar-collapsed');
    
    // Salvar preferência no localStorage
    if (window.innerWidth >= 992) {  // Apenas para desktop
      localStorage.setItem('sidebar_collapsed', body.classList.contains('sidebar-collapsed'));
    } else {
      body.classList.toggle('sidebar-visible');
    }
  }
  
  if (desktopToggle) {
    desktopToggle.addEventListener('click', toggleSidebar);
  }
  
  if (mobileToggle) {
    mobileToggle.addEventListener('click', toggleSidebar);
  }
  
  // Verificar preferência no localStorage (apenas desktop)
  if (window.innerWidth >= 992 && localStorage.getItem('sidebar_collapsed') === 'true') {
    body.classList.add('sidebar-collapsed');
  }
  
  // Dropdowns no header
  const notificationsBtn = document.getElementById('notifications-btn');
  const notificationsDropdown = document.getElementById('notifications-dropdown');
  const userBtn = document.getElementById('user-btn');
  const userDropdown = document.getElementById('user-dropdown');
  
  // Função para mostrar/esconder dropdown
  function toggleDropdown(btn, dropdown) {
    if (!btn || !dropdown) return;
    
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      
      const isActive = dropdown.classList.contains('show');
      
      // Fechar todos os dropdowns
      document.querySelectorAll('.nexgen-dropdown').forEach(el => {
        el.classList.remove('show');
      });
      
      // Abrir este dropdown se não estava ativo
      if (!isActive) {
        dropdown.classList.add('show');
      }
    });
  }
  
  toggleDropdown(notificationsBtn, notificationsDropdown);
  toggleDropdown(userBtn, userDropdown);
  
  // Fechar dropdowns ao clicar fora
  document.addEventListener('click', function() {
    document.querySelectorAll('.nexgen-dropdown').forEach(el => {
      el.classList.remove('show');
    });
  });
  
  // Submenus no menu lateral (para telas menores onde o hover não funciona)
  document.querySelectorAll('.menu-link[data-bs-toggle="collapse"]').forEach(link => {
    link.addEventListener('click', function(e) {
      if (window.innerWidth < 992 || !document.documentElement.classList.contains('sidebar-collapsed')) {
        e.preventDefault();
        const target = this.getAttribute('href');
        const submenu = document.querySelector(target);
        
        // Fechar outros submenus
        document.querySelectorAll('.submenu.show').forEach(sub => {
          if (sub !== submenu) {
            sub.classList.remove('show');
            const parentLink = document.querySelector(`[href="#${sub.id}"]`);
            if (parentLink) {
              parentLink.setAttribute('aria-expanded', 'false');
            }
          }
        });
        
        // Toggle do submenu atual
        submenu.classList.toggle('show');
        this.setAttribute('aria-expanded', submenu.classList.contains('show'));
      }
    });
  });
  
  // Confirmação de logout
  document.querySelectorAll('.logout-link').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      
      if (confirm('Tem certeza que deseja sair?')) {
        window.location.href = this.getAttribute('href');
      }
    });
  });
  
  // Fix para que a apresentação inicial esteja correta em telas menores
  function adjustLayoutForScreenSize() {
    if (window.innerWidth < 992) {
      body.classList.remove('sidebar-collapsed');
    }
  }
  
  // Ajustar no carregamento inicial
  adjustLayoutForScreenSize();
  
  // Ajustar quando a tela mudar de tamanho
  window.addEventListener('resize', adjustLayoutForScreenSize);
});
</script>
</body>
</html>