// assets/js/admin-custom.js
document.addEventListener('DOMContentLoaded', function() {
  // Controle da Sidebar
  const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
  const sidebar = document.getElementById('sidebar');
  
  if (sidebarCollapseBtn) {
    sidebarCollapseBtn.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      
      // Armazena preferência do usuário
      if (sidebar.classList.contains('collapsed')) {
        localStorage.setItem('sidebarState', 'collapsed');
      } else {
        localStorage.setItem('sidebarState', 'expanded');
      }
    });
  }
  
  // Recupera estado da sidebar do localStorage
  const savedState = localStorage.getItem('sidebarState');
  if (savedState === 'collapsed') {
    sidebar.classList.add('collapsed');
  }
  
  // Toggle para dispositivos móveis
  const mobileToggle = document.querySelector('.mobile-toggle');
  if (mobileToggle) {
    mobileToggle.addEventListener('click', function() {
      sidebar.classList.toggle('show');
    });
  }
  
  // Fechar sidebar ao clicar fora em dispositivos móveis
  document.addEventListener('click', function(event) {
    if (window.innerWidth < 992) {
      if (!sidebar.contains(event.target) && 
          !event.target.classList.contains('mobile-toggle')) {
        sidebar.classList.remove('show');
      }
    }
  });
  
  // DataTables inicialização
  if (typeof $.fn.DataTable !== 'undefined') {
    $('.datatable').DataTable({
      responsive: true,
      language: {
        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
      }
    });
  }
  
  // Tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
  
  // Máscaras para inputs usando IMask
  if (typeof IMask !== 'undefined') {
    // Máscara para CPF
    document.querySelectorAll('.mask-cpf').forEach(function(el) {
      IMask(el, {
        mask: '000.000.000-00'
      });
    });
    
    // Máscara para telefone
    document.querySelectorAll('.mask-phone').forEach(function(el) {
      IMask(el, {
        mask: '(00) 00000-0000'
      });
    });
    
    // Máscara para CEP
    document.querySelectorAll('.mask-cep').forEach(function(el) {
      IMask(el, {
        mask: '00000-000'
      });
    });
    
    // Máscara para dinheiro
    document.querySelectorAll('.mask-currency').forEach(function(el) {
      IMask(el, {
        mask: Number,
        scale: 2,
        radix: ',',
        thousandsSeparator: '.',
        padFractionalZeros: true,
        normalizeZeros: true,
        mapToRadix: ['.']
      });
    });
  }
  
  // Confirmações para ações perigosas
  document.querySelectorAll('.confirm-action').forEach(function(el) {
    el.addEventListener('click', function(e) {
      const message = el.getAttribute('data-confirm') || 'Tem certeza que deseja realizar esta ação?';
      if (!confirm(message)) {
        e.preventDefault();
        return false;
      }
    });
  });
  
  // Atualização automática de totais em formulários
  function updateTotal() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const priceInputs = document.querySelectorAll('.price-input');
    
    if (quantityInputs.length === priceInputs.length) {
      let total = 0;
      
      for (let i = 0; i < quantityInputs.length; i++) {
        const quantity = parseFloat(quantityInputs[i].value) || 0;
        const price = parseFloat(priceInputs[i].value.replace('.', '').replace(',', '.')) || 0;
        total += quantity * price;
      }
      
      const totalElement = document.getElementById('total-value');
      if (totalElement) {
        totalElement.textContent = total.toLocaleString('pt-BR', {
          style: 'currency',
          currency: 'BRL'
        });
      }
    }
  }
  
  // Atualizando total ao alterar valores
  document.querySelectorAll('.quantity-input, .price-input').forEach(function(el) {
    el.addEventListener('input', updateTotal);
  });
  
  // Inicialização
  updateTotal();
});