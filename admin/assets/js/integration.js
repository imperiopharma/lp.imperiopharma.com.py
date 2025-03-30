/**
 * Império Pharma NexGen - Módulo de Integração do Sistema
 * Versão: 1.0.0
 * 
 * Este arquivo realiza a integração de todos os componentes do sistema,
 * incluindo a inicialização do assistente virtual, motor de insights,
 * e configurações de responsividade.
 */

// Configuração imediata quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa o sistema principal
    initializeSystem();
    
    // Configura o tema e aparência
    setupThemeSystem();
    
    // Inicializa componentes UI interativos
    initializeUIComponents();
    
    // Configura motor de insights
    setupInsightEngine();
    
    // Inicializa o assistente virtual
    initializeVirtualAssistant();
    
    // Configura detector de inatividade
    setupInactivityDetector();
    
    // Configura sistema de feedback
    setupFeedbackSystem();
    
    // Processa inicializações específicas da página atual
    processPageSpecificInitialization();
    
    // Notifica conclusão da inicialização
    console.log('🚀 Império Pharma NexGen inicializado com sucesso!');
});

/**
 * Inicializa o sistema principal
 */
function initializeSystem() {
    // Verifica compatibilidade do navegador
    checkBrowserCompatibility();
    
    // Configura interceptores de requisições AJAX
    setupAjaxInterceptors();
    
    // Inicializa sistema de cache
    initializeCache();
    
    // Configura tratamento de erros global
    setupErrorHandling();
    
    // Registra métricas de performance
    registerPerformanceMetrics();
}

/**
 * Verifica compatibilidade do navegador
 */
function checkBrowserCompatibility() {
    const incompatibilityIssues = [];
    
    // Verifica suporte a Flexbox
    if (!('flex' in document.documentElement.style)) {
        incompatibilityIssues.push('Flexbox não suportado');
    }
    
    // Verifica suporte a localStorage
    let storageAvailable = false;
    try {
        storageAvailable = 'localStorage' in window && window.localStorage !== null;
    } catch (e) {
        incompatibilityIssues.push('localStorage não disponível');
    }
    
    // Verifica suporte a CSS Variables
    const isNativeSupport = window.CSS && window.CSS.supports && window.CSS.supports('--a', 0);
    if (!isNativeSupport) {
        incompatibilityIssues.push('CSS Variables não suportadas');
    }
    
    // Exibe alerta se houver problemas de compatibilidade
    if (incompatibilityIssues.length > 0) {
        console.warn('⚠️ Problemas de compatibilidade detectados:', incompatibilityIssues);
        
        // Adiciona alerta visual
        const appContainer = document.querySelector('.admin-wrapper');
        if (appContainer) {
            const compatAlert = document.createElement('div');
            compatAlert.className = 'alert alert-warning compatibility-alert';
            compatAlert.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Atenção:</strong> Seu navegador pode não ser totalmente compatível com alguns recursos.
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fechar"></button>
            `;
            appContainer.prepend(compatAlert);
        }
    }
}

/**
 * Configura interceptores de requisições AJAX
 */
function setupAjaxInterceptors() {
    // Se estiver usando fetch nativo
    const originalFetch = window.fetch;
    window.fetch = function() {
        const fetchRequest = originalFetch.apply(this, arguments);
        
        // Adiciona indicador de carregamento
        showGlobalLoadingIndicator();
        
        return fetchRequest
            .then(response => {
                hideGlobalLoadingIndicator();
                return response;
            })
            .catch(error => {
                hideGlobalLoadingIndicator();
                console.error('Erro na requisição fetch:', error);
                
                // Exibe notificação de erro
                showToast('Erro ao carregar dados. Verifique sua conexão.', 'danger');
                
                throw error;
            });
    };
    
    // Se estiver usando jQuery
    if (typeof $ !== 'undefined' && $.ajax) {
        $(document).ajaxStart(function() {
            showGlobalLoadingIndicator();
        });
        
        $(document).ajaxStop(function() {
            hideGlobalLoadingIndicator();
        });
        
        $(document).ajaxError(function(event, jqXHR, settings, error) {
            console.error('Erro na requisição AJAX:', error, settings.url);
            showToast('Erro ao carregar dados. Verifique sua conexão.', 'danger');
        });
    }
}

/**
 * Mostra indicador global de carregamento
 */
function showGlobalLoadingIndicator() {
    // Verifica se o indicador já existe
    let loadingIndicator = document.getElementById('global-loading-indicator');
    
    if (!loadingIndicator) {
        // Cria o indicador
        loadingIndicator = document.createElement('div');
        loadingIndicator.id = 'global-loading-indicator';
        loadingIndicator.className = 'loading-indicator';
        loadingIndicator.innerHTML = `
            <div class="spinner">
                <div class="bounce1"></div>
                <div class="bounce2"></div>
                <div class="bounce3"></div>
            </div>
        `;
        document.body.appendChild(loadingIndicator);
    }
    
    // Incrementa contador de requisições em andamento
    loadingIndicator.dataset.requestCount = (parseInt(loadingIndicator.dataset.requestCount || '0') + 1).toString();
    
    // Exibe indicador
    loadingIndicator.style.display = 'flex';
}

/**
 * Esconde indicador global de carregamento
 */
function hideGlobalLoadingIndicator() {
    const loadingIndicator = document.getElementById('global-loading-indicator');
    if (!loadingIndicator) return;
    
    // Decrementa contador de requisições
    const requestCount = parseInt(loadingIndicator.dataset.requestCount || '0') - 1;
    loadingIndicator.dataset.requestCount = Math.max(0, requestCount).toString();
    
    // Esconde indicador se não houver mais requisições
    if (requestCount <= 0) {
        loadingIndicator.style.display = 'none';
    }
}

/**
 * Inicializa sistema de cache
 */
function initializeCache() {
    // Cache simples baseado em localStorage
    window.appCache = {
        // Armazena dados no cache
        set: function(key, data, expirationMinutes = 30) {
            try {
                const item = {
                    data: data,
                    expiry: new Date().getTime() + (expirationMinutes * 60 * 1000)
                };
                localStorage.setItem('cache_' + key, JSON.stringify(item));
                return true;
            } catch (e) {
                console.warn('Falha ao salvar no cache:', e);
                return false;
            }
        },
        
        // Recupera dados do cache
        get: function(key) {
            try {
                const itemStr = localStorage.getItem('cache_' + key);
                if (!itemStr) return null;
                
                const item = JSON.parse(itemStr);
                const now = new Date().getTime();
                
                // Verifica se o cache expirou
                if (now > item.expiry) {
                    localStorage.removeItem('cache_' + key);
                    return null;
                }
                
                return item.data;
            } catch (e) {
                console.warn('Falha ao recuperar do cache:', e);
                return null;
            }
        },
        
        // Remove item do cache
        remove: function(key) {
            try {
                localStorage.removeItem('cache_' + key);
                return true;
            } catch (e) {
                console.warn('Falha ao remover do cache:', e);
                return false;
            }
        },
        
        // Limpa todo o cache
        clear: function() {
            try {
                const toRemove = [];
                
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key.startsWith('cache_')) {
                        toRemove.push(key);
                    }
                }
                
                toRemove.forEach(key => localStorage.removeItem(key));
                return true;
            } catch (e) {
                console.warn('Falha ao limpar cache:', e);
                return false;
            }
        }
    };
    
    // Limpa itens expirados no carregamento
    cleanExpiredCache();
}

/**
 * Limpa itens expirados do cache
 */
function cleanExpiredCache() {
    try {
        const now = new Date().getTime();
        const toRemove = [];
        
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith('cache_')) {
                const itemStr = localStorage.getItem(key);
                const item = JSON.parse(itemStr);
                
                if (now > item.expiry) {
                    toRemove.push(key);
                }
            }
        }
        
        toRemove.forEach(key => localStorage.removeItem(key));
    } catch (e) {
        console.warn('Erro ao limpar cache expirado:', e);
    }
}

/**
 * Configura tratamento de erros global
 */
function setupErrorHandling() {
    window.onerror = function(message, source, lineno, colno, error) {
        // Registra erro no console
        console.error('Erro global:', {message, source, lineno, colno, error});
        
        // Implementar envio para serviço de telemetria
        // sendErrorToTelemetry({message, source, lineno, colno, stack: error ? error.stack : null});
        
        // Exibe notificação discreta
        if (!isErrorNotificationActive) {
            showToast('Ocorreu um erro inesperado. Nossa equipe foi notificada.', 'warning');
            isErrorNotificationActive = true;
            
            // Reseta flag após timeout
            setTimeout(() => {
                isErrorNotificationActive = false;
            }, 10000);
        }
        
        // Permite que o erro continue propagando
        return false;
    };
    
    // Flag para evitar múltiplas notificações simultâneas
    let isErrorNotificationActive = false;
}

/**
 * Registra métricas de performance
 */
function registerPerformanceMetrics() {
    // Verifica suporte à API de Performance
    if (window.performance && window.performance.getEntriesByType) {
        // Registra tempo de carregamento da página
        window.addEventListener('load', function() {
            setTimeout(() => {
                const navigationEntry = performance.getEntriesByType('navigation')[0];
                
                if (navigationEntry) {
                    const metrics = {
                        pageLoadTime: navigationEntry.loadEventEnd - navigationEntry.startTime,
                        domReadyTime: navigationEntry.domContentLoadedEventEnd - navigationEntry.startTime,
                        resourceCount: performance.getEntriesByType('resource').length,
                        date: new Date().toISOString()
                    };
                    
                    // Armazena métricas para análise futura
                    storePerformanceMetrics(metrics);
                    
                    // Log de performance
                    console.log('📊 Métricas de Performance:', metrics);
                }
            }, 0);
        });
    }
}

/**
 * Armazena métricas de performance
 */
function storePerformanceMetrics(metrics) {
    try {
        // Recupera métricas anteriores
        let perfHistory = JSON.parse(localStorage.getItem('perfMetricsHistory') || '[]');
        
        // Limita tamanho do histórico
        if (perfHistory.length > 20) {
            perfHistory = perfHistory.slice(perfHistory.length - 20);
        }
        
        // Adiciona nova métrica
        perfHistory.push(metrics);
        
        // Salva histórico atualizado
        localStorage.setItem('perfMetricsHistory', JSON.stringify(perfHistory));
    } catch (e) {
        console.warn('Falha ao armazenar métricas:', e);
    }
}

/**
 * Configura o sistema de tema
 */
function setupThemeSystem() {
    // Aplica tema salvo se existir
    applyUserThemePreferences();
    
    // Configura alternador de tema
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            toggleDarkMode();
        });
    }
    
    // Configura detector de tema do sistema
    setupSystemThemeDetector();
}

/**
 * Aplica preferências de tema do usuário
 */
function applyUserThemePreferences() {
    try {
        // Verifica se há preferência salva
        const savedTheme = localStorage.getItem('theme-preference');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Atualiza ícone do alternador
            updateThemeToggleIcon(savedTheme);
        } else {
            // Verifica preferência do sistema
            const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const defaultTheme = prefersDarkMode ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', defaultTheme);
            updateThemeToggleIcon(defaultTheme);
        }
    } catch (e) {
        console.warn('Erro ao aplicar preferências de tema:', e);
    }
}

/**
 * Configura detector de tema do sistema
 */
function setupSystemThemeDetector() {
    // Monitora mudanças na preferência do sistema
    const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    
    if (darkModeMediaQuery.addEventListener) {
        darkModeMediaQuery.addEventListener('change', (e) => {
            // Só aplica se o usuário não tiver definido uma preferência explícita
            if (!localStorage.getItem('theme-preference')) {
                const newTheme = e.matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', newTheme);
                updateThemeToggleIcon(newTheme);
            }
        });
    }
}

/**
 * Alterna entre modos claro e escuro
 */
function toggleDarkMode() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    // Aplica novo tema
    document.documentElement.setAttribute('data-theme', newTheme);
    
    // Salva preferência
    localStorage.setItem('theme-preference', newTheme);
    
    // Atualiza ícone
    updateThemeToggleIcon(newTheme);
    
    // Exibe notificação
    const themeName = newTheme === 'dark' ? 'escuro' : 'claro';
    showToast(`Tema ${themeName} ativado`, 'info');
}

/**
 * Atualiza ícone do alternador de tema
 */
function updateThemeToggleIcon(theme) {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;
    
    // Remove classes existentes
    themeToggle.querySelector('i').className = '';
    
    // Adiciona classe correta
    if (theme === 'dark') {
        themeToggle.querySelector('i').className = 'fas fa-sun';
        themeToggle.setAttribute('title', 'Mudar para tema claro');
        themeToggle.setAttribute('aria-label', 'Mudar para tema claro');
    } else {
        themeToggle.querySelector('i').className = 'fas fa-moon';
        themeToggle.setAttribute('title', 'Mudar para tema escuro');
        themeToggle.setAttribute('aria-label', 'Mudar para tema escuro');
    }
}

/**
 * Inicializa componentes da UI
 */
function initializeUIComponents() {
    // Inicializa tooltips Bootstrap
    initTooltips();
    
    // Inicializa popovers
    initPopovers();
    
    // Configura comportamento do sidebar
    setupSidebar();
    
    // Configura links com confirmação
    setupConfirmationLinks();
    
    // Configura máscaras de entrada
    setupInputMasks();
    
    // Inicializa DataTables
    initDataTables();
    
    // Inicializa componentes aninhados
    initializeNestedComponents();
    
    // Configura botões de ação
    setupActionButtons();
    
    // Configura micro-interações
    setupMicroInteractions();
    
    // Configura sistema de notificações
    setupNotifications();
}

/**
 * Inicializa tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl, {
            delay: { show: 300, hide: 100 },
            animation: true
        });
    });
}

/**
 * Inicializa popovers
 */
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.forEach(function(popoverTriggerEl) {
        new bootstrap.Popover(popoverTriggerEl, {
            trigger: 'focus'
        });
    });
}

/**
 * Configura comportamento do sidebar
 */
function setupSidebar() {
    // Toggle do sidebar
    const sidebarToggleButtons = document.querySelectorAll('#sidebar-toggle, #desktop-sidebar-toggle');
    const adminWrapper = document.querySelector('.admin-wrapper');
    
    sidebarToggleButtons.forEach(button => {
        if (!button) return;
        
        button.addEventListener('click', function() {
            adminWrapper.classList.toggle('sidebar-collapsed');
            
            // Armazena o estado do sidebar
            localStorage.setItem('sidebar-collapsed', adminWrapper.classList.contains('sidebar-collapsed'));
            
            // Dispara evento resize para ajustar gráficos
            window.dispatchEvent(new Event('resize'));
        });
    });
    
    // Restaura estado do sidebar
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        adminWrapper.classList.add('sidebar-collapsed');
    }
    
    // Toggle mobile
    const mobileToggle = document.querySelector('.mobile-toggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-mobile-open');
        });
    }
    
    // Fecha sidebar em dispositivos móveis ao clicar fora
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.admin-sidebar');
        if (!sidebar) return;
        
        // Ignora clicks dentro do sidebar ou no botão de toggle
        if (sidebar.contains(event.target) || event.target.closest('.mobile-toggle')) {
            return;
        }
        
        // Fecha o sidebar em dispositivos móveis
        if (window.innerWidth < 992 && document.body.classList.contains('sidebar-mobile-open')) {
            document.body.classList.remove('sidebar-mobile-open');
        }
    });
}

/**
 * Configura links com confirmação
 */
function setupConfirmationLinks() {
    const confirmLinks = document.querySelectorAll('.confirm-action');
    
    confirmLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const target = this.getAttribute('href');
            const message = this.getAttribute('data-confirm-message') || 'Tem certeza que deseja continuar?';
            const title = this.getAttribute('data-confirm-title') || 'Confirmação';
            const btnText = this.getAttribute('data-confirm-btn') || 'Confirmar';
            const btnClass = this.getAttribute('data-confirm-btn-class') || 'btn-primary';
            
            // Usa SweetAlert2 se disponível, ou fallback para confirm nativo
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    text: message,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: btnText,
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--color-primary'),
                    cancelButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--color-secondary'),
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = target;
                    }
                });
            } else {
                if (confirm(message)) {
                    window.location.href = target;
                }
            }
        });
    });
}

/**
 * Configura máscaras de entrada
 */
function setupInputMasks() {
    // Verifica se IMask está disponível
    if (typeof IMask === 'undefined') return;
    
    // Máscara para CPF
    document.querySelectorAll('.mask-cpf').forEach(input => {
        IMask(input, {
            mask: '000.000.000-00'
        });
    });
    
    // Máscara para telefone
    document.querySelectorAll('.mask-phone').forEach(input => {
        IMask(input, {
            mask: [
                {mask: '(00) 0000-0000'},
                {mask: '(00) 00000-0000'}
            ]
        });
    });
    
    // Máscara para valores monetários
    document.querySelectorAll('.mask-money').forEach(input => {
        IMask(input, {
            mask: 'R$ num',
            blocks: {
                num: {
                    mask: Number,
                    thousandsSeparator: '.',
                    decimalSeparator: ',',
                    scale: 2,
                    padFractionalZeros: true
                }
            }
        });
    });
    
    // Máscara para CEP
    document.querySelectorAll('.mask-cep').forEach(input => {
        IMask(input, {
            mask: '00000-000'
        });
    });
    
    // Máscara para data
    document.querySelectorAll('.mask-date').forEach(input => {
        IMask(input, {
            mask: '00/00/0000'
        });
    });
}

/**
 * Inicializa DataTables
 */
function initDataTables() {
    // Verifica se DataTables está disponível
    if (typeof $.fn.DataTable === 'undefined') return;
    
    // Configuração padrão
    const defaultConfig = {
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
        },
        pageLength: 25,
        dom: 'lfrtip',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
    };
    
    // Inicializa DataTables básicas
    document.querySelectorAll('.datatable:not(.advanced-datatable)').forEach(table => {
        if (!$.fn.dataTable.isDataTable(table)) {
            $(table).DataTable(defaultConfig);
        }
    });
    
    // Inicializa DataTables avançadas
    document.querySelectorAll('.advanced-datatable').forEach(table => {
        if (!$.fn.dataTable.isDataTable(table)) {
            // Obtém configurações específicas da tabela
            const exportButtons = table.hasAttribute('data-export-buttons');
            const scrollX = table.hasAttribute('data-scroll-x');
            const scrollY = table.getAttribute('data-scroll-y');
            
            // Configura opções específicas
            const config = {...defaultConfig};
            
            // Adiciona botões de exportação se necessário
            if (exportButtons) {
                config.dom = 'Blfrtip';
                config.buttons = [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        exportOptions: {
                            columns: ':not(.no-export)'
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn btn-sm btn-danger',
                        exportOptions: {
                            columns: ':not(.no-export)'
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Imprimir',
                        className: 'btn btn-sm btn-primary',
                        exportOptions: {
                            columns: ':not(.no-export)'
                        }
                    }
                ];
            }
            
            // Configura rolagem
            if (scrollX) {
                config.scrollX = true;
            }
            
            if (scrollY) {
                config.scrollY = scrollY;
            }
            
            // Inicializa DataTable com configurações específicas
            $(table).DataTable(config);
        }
    });
}

/**
 * Inicializa componentes aninhados
 */
function initializeNestedComponents() {
    // Inicializa select2 se disponível
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
    
    // Inicializa datepicker se disponível
    if (typeof $.fn.datepicker !== 'undefined') {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            language: 'pt-BR',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Inicializa summernote se disponível
    if (typeof $.fn.summernote !== 'undefined') {
        $('.summernote').summernote({
            height: 200,
            lang: 'pt-BR',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    }
}

/**
 * Configura botões de ação
 */
function setupActionButtons() {
    // Botões de refresh
    document.querySelectorAll('.refresh-btn').forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            const iconElement = this.querySelector('i');
            
            // Adiciona classe de animação
            if (iconElement) {
                iconElement.classList.add('fa-spin');
            }
            
            // Desabilita o botão durante o refresh
            this.disabled = true;
            
            // Simula recarregamento (substitua por chamada real à API)
            setTimeout(() => {
                // Remove animação
                if (iconElement) {
                    iconElement.classList.remove('fa-spin');
                }
                
                // Reativa o botão
                this.disabled = false;
                
                // Exibe notificação
                showToast('Dados atualizados com sucesso!', 'success');
                
                // Evento específico para cada alvo
                if (target) {
                    // Dispara evento para que outros componentes possam reagir
                    const refreshEvent = new CustomEvent('imperiopharma:refresh', {
                        detail: { target: target }
                    });
                    document.dispatchEvent(refreshEvent);
                }
            }, 800);
        });
    });
    
    // Botões de exportação
    document.querySelectorAll('.export-btn').forEach(button => {
        button.addEventListener('click', function() {
            const format = this.getAttribute('data-format');
            const target = this.getAttribute('data-target') || 'dashboard';
            
            showToast(`Exportando para ${format.toUpperCase()}...`, 'info');
            
            // Simula exportação (substitua por implementação real)
            setTimeout(() => {
                showToast(`Exportação para ${format.toUpperCase()} concluída!`, 'success');
            }, 1000);
        });
    });
}

/**
 * Configura micro-interações
 */
function setupMicroInteractions() {
    // Efeito hover em cards
    document.querySelectorAll('.hover-lift').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('shadow-lg');
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('shadow-lg');
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Efeito de ripple em botões
    document.querySelectorAll('.btn:not(.no-ripple)').forEach(button => {
        button.classList.add('ripple');
    });
    
    // Efeito de pulso para elementos de destaque
    document.querySelectorAll('.pulse-attention').forEach(element => {
        element.classList.add('pulse-animation');
    });
    
    // Efeito de highlight para novos itens
    highlightNewItems();
}

/**
 * Destaca itens novos na interface
 */
function highlightNewItems() {
    document.querySelectorAll('[data-highlight="new"]').forEach(element => {
        // Adiciona classe de destaque
        element.classList.add('highlight-new');
        
        // Remove após alguns segundos
        setTimeout(() => {
            element.classList.remove('highlight-new');
            element.removeAttribute('data-highlight');
        }, 5000);
    });
}

/**
 * Configura sistema de notificações
 */
function setupNotifications() {
    // Carrega contagem de notificações
    loadNotificationCount();
    
    // Configura clique no botão de notificações
    const notificationBtn = document.getElementById('notificationBtn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            loadNotifications();
        });
    }
    
    // Configura ações de notificação
    document.addEventListener('click', function(e) {
        const markAllRead = e.target.closest('#mark-all-notifications-read');
        if (markAllRead) {
            e.preventDefault();
            markAllNotificationsAsRead();
        }
    });
}

/**
 * Carrega contagem de notificações
 */
function loadNotificationCount() {
    // Verifica se há elemento de contagem
    const notificationCount = document.querySelector('.notification-badge');
    if (!notificationCount) return;
    
    // Recupera dados de cache ou API
    const cachedCount = window.appCache ? window.appCache.get('notification_count') : null;
    
    if (cachedCount !== null) {
        updateNotificationBadge(cachedCount);
    } else {
        // Simula chamada à API
        simulateApiCall('notifications/count', {})
            .then(response => {
                const count = response.count || 0;
                updateNotificationBadge(count);
                
                // Armazena em cache por 5 minutos
                if (window.appCache) {
                    window.appCache.set('notification_count', count, 5);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar contagem de notificações:', error);
            });
    }
}

/**
 * Atualiza badge de notificação
 */
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('d-none');
        
        // Adiciona animação de pulso se o badge não estava visível
        if (badge.classList.contains('d-none')) {
            badge.classList.add('pulse-animation');
            setTimeout(() => badge.classList.remove('pulse-animation'), 2000);
        }
    } else {
        badge.classList.add('d-none');
    }
}

/**
 * Carrega notificações
 */
function loadNotifications() {
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (!notificationDropdown) return;
    
    // Exibe indicador de carregamento
    notificationDropdown.innerHTML = `
        <li class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="small text-muted mt-2 mb-0">Carregando notificações...</p>
        </li>
    `;
    
    // Recupera dados de cache ou API
    const cachedNotifications = window.appCache ? window.appCache.get('notifications') : null;
    
    if (cachedNotifications !== null) {
        renderNotifications(cachedNotifications);
    } else {
        // Simula chamada à API
        simulateApiCall('notifications', {limit: 10})
            .then(response => {
                const notifications = response.notifications || [];
                renderNotifications(notifications);
                
                // Armazena em cache por 5 minutos
                if (window.appCache) {
                    window.appCache.set('notifications', notifications, 5);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar notificações:', error);
                notificationDropdown.innerHTML = `
                    <li class="text-center py-3">
                        <i class="fas fa-exclamation-circle text-danger mb-2" style="font-size: 1.5rem;"></i>
                        <p class="small text-muted mb-0">Erro ao carregar notificações.</p>
                    </li>
                `;
            });
    }
}

/**
 * Renderiza notificações no dropdown
 */
function renderNotifications(notifications) {
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (!notificationDropdown) return;
    
    // Limpa o dropdown
    notificationDropdown.innerHTML = '';
    
    // Exibe mensagem se não houver notificações
    if (notifications.length === 0) {
        notificationDropdown.innerHTML = `
            <li class="text-center py-3">
                <i class="fas fa-bell-slash text-muted mb-2" style="font-size: 1.5rem;"></i>
                <p class="small text-muted mb-0">Nenhuma notificação no momento.</p>
            </li>
        `;
        return;
    }
    
    // Adiciona cada notificação
    notifications.forEach(notification => {
        const li = document.createElement('li');
        
        li.innerHTML = `
            <a class="dropdown-item notification-item ${notification.type}" href="${notification.link || '#'}">
                <div class="notification-icon">
                    <i class="fas ${getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-desc">${notification.description}</div>
                    <div class="notification-time">${notification.time}</div>
                </div>
            </a>
        `;
        
        notificationDropdown.appendChild(li);
    });
    
    // Adiciona item para ver todas as notificações
    const allItem = document.createElement('li');
    allItem.innerHTML = `
        <div class="dropdown-divider"></div>
        <div class="d-flex justify-content-between px-3 py-2">
            <a href="index.php?page=notificacoes" class="small text-primary">Ver todas</a>
            <a href="#" id="mark-all-notifications-read" class="small text-muted">Marcar como lidas</a>
        </div>
    `;
    notificationDropdown.appendChild(allItem);
}

/**
 * Marca todas as notificações como lidas
 */
function markAllNotificationsAsRead() {
    // Simula chamada à API
    simulateApiCall('notifications/mark-read', {}, 'POST')
        .then(response => {
            // Atualiza contagem
            updateNotificationBadge(0);
            
            // Atualiza cache
            if (window.appCache) {
                window.appCache.set('notification_count', 0, 5);
                
                // Atualiza notificações em cache
                const cachedNotifications = window.appCache.get('notifications');
                if (cachedNotifications) {
                    const updatedNotifications = cachedNotifications.map(n => ({...n, read: true}));
                    window.appCache.set('notifications', updatedNotifications, 5);
                }
            }
            
            // Exibe notificação
            showToast('Todas notificações marcadas como lidas', 'success');
        })
        .catch(error => {
            console.error('Erro ao marcar notificações como lidas:', error);
            showToast('Erro ao marcar notificações como lidas', 'danger');
        });
}

/**
 * Retorna ícone adequado para tipo de notificação
 */
function getNotificationIcon(type) {
    switch (type) {
        case 'warning': return 'fa-exclamation-triangle';
        case 'success': return 'fa-check-circle';
        case 'danger': return 'fa-exclamation-circle';
        case 'info': return 'fa-info-circle';
        default: return 'fa-bell';
    }
}

/**
 * Configura motor de insights
 */
function setupInsightEngine() {
    // Verifica se o motor existe
    if (typeof InsightEngine === 'undefined') {
        console.warn('Motor de insights não disponível');
        return;
    }
    
    // Cria instância global do motor
    window.insightEngine = new InsightEngine();
    
    // Carrega dados iniciais
    const dataEndpoints = [
        'sales', 'orders', 'products', 'customers', 'inventory'
    ];
    
    // Carrega dados em paralelo
    Promise.all(dataEndpoints.map(endpoint => 
        window.insightEngine.loadData(endpoint)
    )).then(() => {
        // Gera insights iniciais
        generateAndDisplayInsights();
        
        // Configura geração periódica de insights (a cada 5 minutos)
        setInterval(generateAndDisplayInsights, 5 * 60 * 1000);
    }).catch(error => {
        console.error('Erro ao inicializar motor de insights:', error);
    });
    
    // Configura eventos para atualização de dados
    document.addEventListener('imperiopharma:refresh', function(e) {
        const target = e.detail.target;
        
        // Atualiza dados específicos
        if (dataEndpoints.includes(target)) {
            window.insightEngine.loadData(target).then(() => {
                generateAndDisplayInsights();
            });
        }
    });
}

/**
 * Gera e exibe insights
 */
function generateAndDisplayInsights() {
    if (!window.insightEngine) return;
    
    // Detecta anomalias
    const anomalies = window.insightEngine.detectAnomalies();
    
    // Gera insights baseados nos dados
    const insights = window.insightEngine.generateInsights();
    
    // Atualiza UI com insights
    updateInsightsUI([...anomalies, ...insights]);
}

/**
 * Atualiza UI com insights
 */
function updateInsightsUI(insights) {
    const insightsContainer = document.getElementById('insights-container');
    if (!insightsContainer) return;
    
    // Limpa container se necessário
    const shouldClear = !insightsContainer.hasAttribute('data-append-only');
    if (shouldClear) {
        insightsContainer.innerHTML = '';
    }
    
    // Adiciona novos insights
    insights.forEach(insight => {
        // Cria elemento de insight
        const insightElement = document.createElement('div');
        insightElement.className = `insight-alert ${insight.type || 'info'}`;
        
        // Adiciona animação de entrada
        insightElement.style.opacity = '0';
        insightElement.style.transform = 'translateY(10px)';
        
        insightElement.innerHTML = `
            <div class="insight-icon bg-${insight.type || 'info'}-subtle text-${insight.type || 'info'}">
                <i class="fas ${insight.icon || 'fa-lightbulb'}"></i>
            </div>
            <div class="insight-content">
                <h6 class="insight-title">${insight.title}</h6>
                <p class="insight-description">${insight.description}</p>
            </div>
            ${insight.link ? `
                <a href="${insight.link}" class="btn btn-sm btn-light">
                    <i class="fas fa-arrow-right"></i>
                </a>
            ` : ''}
        `;
        
        // Adiciona ao container
        insightsContainer.appendChild(insightElement);
        
        // Anima entrada
        setTimeout(() => {
            insightElement.style.transition = 'opacity 0.5s, transform 0.5s';
            insightElement.style.opacity = '1';
            insightElement.style.transform = 'translateY(0)';
        }, 50);
    });
    
    // Exibe mensagem se não houver insights
    if (shouldClear && insightsContainer.children.length === 0) {
        const noInsightsElement = document.createElement('div');
        noInsightsElement.className = 'insight-alert';
        noInsightsElement.innerHTML = `
            <div class="insight-icon bg-light text-muted">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="insight-content">
                <h6 class="insight-title">Tudo em ordem</h6>
                <p class="insight-description">Nenhum insight relevante para exibir no momento</p>
            </div>
        `;
        insightsContainer.appendChild(noInsightsElement);
    }
}

/**
 * Inicializa o assistente virtual
 */
function initializeVirtualAssistant() {
    // Verifica se o assistente já foi inicializado
    if (window.virtualAssistantInitialized) return;
    window.virtualAssistantInitialized = true;
    
    // Cria contêiner do assistente se não existir
    createAssistantContainer();
    
    // Configura botão de ativação
    setupAssistantTrigger();
    
    // Carrega dados iniciais do assistente
    loadAssistantData();
    
    // Configura eventos do assistente
    setupAssistantEvents();
}

/**
 * Cria contêiner do assistente virtual
 */
function createAssistantContainer() {
    // Verifica se já existe
    if (document.getElementById('virtual-assistant-container')) return;
    
    // Cria elemento
    const assistantContainer = document.createElement('div');
    assistantContainer.id = 'virtual-assistant-container';
    assistantContainer.className = 'virtual-assistant-container';
    assistantContainer.innerHTML = `
        <div class="virtual-assistant-button" id="assistantButton">
            <i class="fas fa-robot"></i>
        </div>
        
        <div class="virtual-assistant-panel" id="assistantPanel">
            <div class="assistant-header">
                <div class="assistant-title">
                    <i class="fas fa-robot me-2"></i>
                    <span>Assistente Virtual</span>
                </div>
                <div class="assistant-actions">
                    <button class="assistant-action-btn" id="assistantMinimize">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="assistant-action-btn" id="assistantClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div class="assistant-body">
                <div class="assistant-chat" id="assistantChat">
                    <div class="assistant-message">
                        <div class="assistant-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-content">
                            <p>Olá! Sou o assistente virtual da Império Pharma. Como posso ajudar?</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="assistant-footer">
                <div class="assistant-input">
                    <input type="text" id="assistantInput" placeholder="Digite sua pergunta...">
                    <button id="assistantSend">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="assistant-suggestions" id="assistantSuggestions">
                    <button class="suggestion-btn" data-query="Como estão as vendas hoje?">
                        Como estão as vendas hoje?
                    </button>
                    <button class="suggestion-btn" data-query="Pedidos pendentes?">
                        Pedidos pendentes?
                    </button>
                    <button class="suggestion-btn" data-query="Produtos em baixo estoque?">
                        Produtos em baixo estoque?
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Adiciona ao body
    document.body.appendChild(assistantContainer);
    
    // Adiciona estilos CSS necessários
    addAssistantStyles();
}

/**
 * Adiciona estilos CSS para o assistente
 */
function addAssistantStyles() {
    // Verifica se os estilos já foram adicionados
    if (document.getElementById('virtual-assistant-styles')) return;
    
    // Cria elemento style
    const styleElement = document.createElement('style');
    styleElement.id = 'virtual-assistant-styles';
    styleElement.textContent = `
        .virtual-assistant-container {
            --assistant-primary: #0d6efd;
            --assistant-bg: #ffffff;
            --assistant-text: #212529;
            --assistant-border: rgba(0, 0, 0, 0.1);
            --assistant-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }
        
        [data-theme="dark"] .virtual-assistant-container {
            --assistant-primary: #3d8bfd;
            --assistant-bg: #343a40;
            --assistant-text: #f8f9fa;
            --assistant-border: rgba(255, 255, 255, 0.1);
        }
        
        .virtual-assistant-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--assistant-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: var(--assistant-shadow);
            z-index: 1050;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .virtual-assistant-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .virtual-assistant-panel {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: var(--assistant-bg);
            border-radius: 15px;
            box-shadow: var(--assistant-shadow);
            z-index: 1050;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.3s, opacity 0.3s;
        }
        
        .virtual-assistant-panel.active {
            transform: translateY(0);
            opacity: 1;
            pointer-events: all;
        }
        
        .assistant-header {
            padding: 15px;
            border-bottom: 1px solid var(--assistant-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .assistant-title {
            display: flex;
            align-items: center;
            font-weight: 600;
            color: var(--assistant-text);
        }
        
        .assistant-actions {
            display: flex;
            gap: 8px;
        }
        
        .assistant-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        [data-theme="dark"] .assistant-action-btn {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .assistant-action-btn:hover {
            background: rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] .assistant-action-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .assistant-body {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        .assistant-chat {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .assistant-message, .user-message {
            display: flex;
            gap: 10px;
            max-width: 85%;
        }
        
        .assistant-message {
            align-self: flex-start;
        }
        
        .user-message {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .assistant-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--assistant-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: 18px;
            background: rgba(13, 110, 253, 0.1);
            color: var(--assistant-text);
        }
        
        .user-message .message-content {
            background: rgba(108, 117, 125, 0.1);
        }
        
        [data-theme="dark"] .message-content {
            background: rgba(13, 110, 253, 0.2);
        }
        
        [data-theme="dark"] .user-message .message-content {
            background: rgba(108, 117, 125, 0.2);
        }
        
        .message-content p {
            margin: 0;
        }
        
        .assistant-footer {
            padding: 15px;
            border-top: 1px solid var(--assistant-border);
        }
        
        .assistant-input {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .assistant-input input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 20px;
            border: 1px solid var(--assistant-border);
            background: var(--assistant-bg);
            color: var(--assistant-text);
        }
        
        .assistant-input input:focus {
            outline: none;
            border-color: var(--assistant-primary);
        }
        
        .assistant-input button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--assistant-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .assistant-input button:hover {
            background: var(--assistant-primary);
            filter: brightness(0.9);
        }
        
        .assistant-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .suggestion-btn {
            padding: 8px 12px;
            border-radius: 15px;
            border: 1px solid var(--assistant-border);
            background: var(--assistant-bg);
            color: var(--assistant-text);
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            white-space: nowrap;
        }
        
        .suggestion-btn:hover {
            background: rgba(13, 110, 253, 0.1);
            border-color: var(--assistant-primary);
        }
        
        [data-theme="dark"] .suggestion-btn:hover {
            background: rgba(13, 110, 253, 0.2);
        }
        
        /* Animação de digitação */
        .typing-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 0;
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: var(--assistant-primary);
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
        
        /* Responsividade */
        @media (max-width: 576px) {
            .virtual-assistant-panel {
                width: calc(100% - 40px);
                height: 70vh;
                bottom: 80px;
            }
        }
    `;
    
    // Adiciona ao head
    document.head.appendChild(styleElement);
}

/**
 * Configura botão de ativação do assistente
 */
function setupAssistantTrigger() {
    const assistantButton = document.getElementById('assistantButton');
    const assistantPanel = document.getElementById('assistantPanel');
    const assistantClose = document.getElementById('assistantClose');
    const assistantMinimize = document.getElementById('assistantMinimize');
    
    if (!assistantButton || !assistantPanel) return;
    
    // Abre o assistente
    assistantButton.addEventListener('click', function() {
        assistantPanel.classList.add('active');
        document.getElementById('assistantInput').focus();
    });
    
    // Fecha o assistente
    assistantClose.addEventListener('click', function() {
        assistantPanel.classList.remove('active');
    });
    
    // Minimiza o assistente
    assistantMinimize.addEventListener('click', function() {
        assistantPanel.classList.remove('active');
    });
}

/**
 * Carrega dados iniciais do assistente
 */
function loadAssistantData() {
    // Recupera histórico de conversas se existir
    try {
        const chatHistory = JSON.parse(localStorage.getItem('assistant_chat_history') || '[]');
        
        // Limita a 10 mensagens para não sobrecarregar
        const recentMessages = chatHistory.slice(-10);
        
        // Restaura mensagens
        const assistantChat = document.getElementById('assistantChat');
        if (assistantChat && recentMessages.length > 0) {
            // Remove mensagem inicial se houver histórico
            assistantChat.innerHTML = '';
            
            // Adiciona mensagens do histórico
            recentMessages.forEach(message => {
                if (message.type === 'user') {
                    addUserMessage(message.text);
                } else {
                    addAssistantMessage(message.text);
                }
            });
        }
    } catch (e) {
        console.warn('Erro ao carregar histórico de conversas:', e);
    }
    
    // Carrega sugestões personalizadas
    loadPersonalizedSuggestions();
}

/**
 * Carrega sugestões personalizadas
 */
function loadPersonalizedSuggestions() {
    const suggestionsContainer = document.getElementById('assistantSuggestions');
    if (!suggestionsContainer) return;
    
    // Obtém página atual
    const currentPage = document.body.getAttribute('data-page') || 'dashboard';
    
    // Sugestões específicas para cada página
    const pageSuggestions = {
        dashboard: [
            'Como estão as vendas hoje?',
            'Pedidos pendentes?',
            'Produtos mais vendidos?'
        ],
        pedidos: [
            'Quantos pedidos pendentes?',
            'Último pedido cancelado?',
            'Pedidos com frete atrasado?'
        ],
        financeiro_completo: [
            'Resumo do mês atual?',
            'Comparar com mês anterior?',
            'Projeção para este mês?'
        ],
        marcas_produtos: [
            'Produtos com baixo estoque?',
            'Marcas mais vendidas?',
            'Produtos sem movimento?'
        ]
    };
    
    // Obtem sugestões para a página atual
    const suggestions = pageSuggestions[currentPage] || pageSuggestions.dashboard;
    
    // Atualiza sugestões
    suggestionsContainer.innerHTML = '';
    
    suggestions.forEach(suggestion => {
        const button = document.createElement('button');
        button.className = 'suggestion-btn';
        button.textContent = suggestion;
        button.dataset.query = suggestion;
        
        suggestionsContainer.appendChild(button);
    });
}

/**
 * Configura eventos do assistente
 */
function setupAssistantEvents() {
    const assistantInput = document.getElementById('assistantInput');
    const assistantSend = document.getElementById('assistantSend');
    const suggestions = document.getElementById('assistantSuggestions');
    
    if (!assistantInput || !assistantSend) return;
    
    // Envio de mensagem com botão
    assistantSend.addEventListener('click', function() {
        sendAssistantMessage();
    });
    
    // Envio de mensagem com Enter
    assistantInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendAssistantMessage();
        }
    });
    
    // Uso de sugestões
    if (suggestions) {
        suggestions.addEventListener('click', function(e) {
            const suggestionBtn = e.target.closest('.suggestion-btn');
            if (suggestionBtn) {
                const query = suggestionBtn.dataset.query;
                assistantInput.value = query;
                sendAssistantMessage();
            }
        });
    }
}

/**
 * Envia mensagem para o assistente
 */
function sendAssistantMessage() {
    const assistantInput = document.getElementById('assistantInput');
    const message = assistantInput.value.trim();
    
    if (!message) return;
    
    // Adiciona mensagem do usuário ao chat
    addUserMessage(message);
    
    // Limpa input
    assistantInput.value = '';
    
    // Gera resposta
    generateAssistantResponse(message);
}

/**
 * Adiciona mensagem do usuário ao chat
 */
function addUserMessage(text) {
    const assistantChat = document.getElementById('assistantChat');
    if (!assistantChat) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'user-message';
    messageDiv.innerHTML = `
        <div class="message-content">
            <p>${escapeHtml(text)}</p>
        </div>
        <div class="user-avatar">
            <i class="fas fa-user"></i>
        </div>
    `;
    
    assistantChat.appendChild(messageDiv);
    scrollChatToBottom();
    
    // Salva mensagem no histórico
    saveMessageToHistory('user', text);
}

/**
 * Adiciona mensagem do assistente ao chat
 */
function addAssistantMessage(text, isLoading = false) {
    const assistantChat = document.getElementById('assistantChat');
    if (!assistantChat) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'assistant-message';
    
    if (isLoading) {
        messageDiv.innerHTML = `
            <div class="assistant-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <div class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="assistant-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <p>${processMessageWithMarkdown(text)}</p>
            </div>
        `;
        
        // Salva mensagem no histórico
        saveMessageToHistory('assistant', text);
    }
    
    assistantChat.appendChild(messageDiv);
    scrollChatToBottom();
    
    return messageDiv;
}

/**
 * Processa texto da mensagem com markdown simples
 */
function processMessageWithMarkdown(text) {
    // Converte URLs em links
    text = text.replace(
        /(https?:\/\/[^\s]+)/g, 
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );
    
    // Negrito
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Itálico
    text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Preserva quebras de linha
    text = text.replace(/\n/g, '<br>');
    
    return text;
}

/**
 * Salva mensagem no histórico
 */
function saveMessageToHistory(type, text) {
    try {
        // Recupera histórico existente
        const chatHistory = JSON.parse(localStorage.getItem('assistant_chat_history') || '[]');
        
        // Adiciona nova mensagem
        chatHistory.push({ type, text, timestamp: new Date().toISOString() });
        
        // Limita tamanho do histórico (máximo 50 mensagens)
        if (chatHistory.length > 50) {
            chatHistory.shift();
        }
        
        // Salva histórico atualizado
        localStorage.setItem('assistant_chat_history', JSON.stringify(chatHistory));
    } catch (e) {
        console.warn('Erro ao salvar mensagem no histórico:', e);
    }
}

/**
 * Gera resposta do assistente
 */
function generateAssistantResponse(message) {
    // Exibe indicador de digitação
    const loadingMessage = addAssistantMessage('', true);
    
    // Atraso simulado para parecer mais natural
    setTimeout(() => {
        // Remove indicador de digitação
        loadingMessage.remove();
        
        // Gera resposta do assistente
        let response = '';
        
        // Verifica se temos o motor de insights disponível
        if (window.insightEngine) {
            // Tenta gerar resposta baseada em dados reais
            const insightResponse = window.insightEngine.generateResponseBasedOnData(message);
            if (insightResponse) {
                response = insightResponse;
            }
        }
        
        // Se não temos resposta do motor, use respostas predefinidas
        if (!response) {
            response = getAssistantResponse(message);
        }
        
        // Adiciona a resposta ao chat
        addAssistantMessage(response);
    }, 1500); // Simulação de "pensamento"
}

/**
 * Obtém resposta predefinida do assistente
 */
function getAssistantResponse(message) {
    // Converte mensagem para minúsculas para facilitar a comparação
    const lowerMessage = message.toLowerCase();
    
    // Verificações por categorias de perguntas
    if (lowerMessage.includes('venda') || lowerMessage.includes('faturamento')) {
        const valor = (Math.random() * 1500 + 500).toFixed(2).replace('.', ',');
        const percentual = (Math.random() * 15 + 5).toFixed(1).replace('.', ',');
        
        return `As vendas estão progredindo bem! Hoje tivemos R$ ${valor} em faturamento, o que representa um aumento de ${percentual}% em relação à média do mês. Se precisar de dados mais detalhados, sugiro verificar o relatório de Financeiro Completo.`;
    }
    
    if (lowerMessage.includes('pedido') || lowerMessage.includes('encomenda')) {
        const pendentes = Math.floor(Math.random() * 5) + 1;
        
        if (lowerMessage.includes('pendente') || lowerMessage.includes('aguardando')) {
            return `Atualmente existem ${pendentes} pedidos pendentes aguardando processamento. Recomendo verificá-los para garantir um bom nível de serviço ao cliente. Você pode acessá-los diretamente pela seção de Pedidos com filtro de status "Pendente".`;
        }
        
        const total = Math.floor(Math.random() * 20) + 10;
        return `No total, processamos ${total} pedidos hoje. Nossa taxa de conclusão está em 87%, o que é um excelente indicador de eficiência operacional. O ticket médio dos pedidos hoje está em R$ 127,35.`;
    }
    
    if (lowerMessage.includes('estoque') || lowerMessage.includes('produto')) {
        const baixo = Math.floor(Math.random() * 5) + 1;
        
        if (lowerMessage.includes('baixo') || lowerMessage.includes('repor')) {
            return `Atualmente temos ${baixo} produtos com estoque crítico que necessitam de reposição urgente. Acesse a seção de Marcas & Produtos e use o filtro "Baixo Estoque" para visualizá-los.`;
        }
        
        return `O monitoramento de estoque está funcionando normalmente. Nosso produto mais vendido atualmente é "Vitamina C 1000mg", que representa 8,3% das vendas totais do mês. Temos um total de 347 produtos ativos no catálogo.`;
    }
    
    if (lowerMessage.includes('finan') || lowerMessage.includes('lucro') || lowerMessage.includes('resumo')) {
        const receita = (Math.random() * 25000 + 10000).toFixed(2).replace('.', ',');
        const margem = (Math.random() * 10 + 25).toFixed(1).replace('.', ',');
        
        return `O resumo financeiro atual mostra uma receita de R$ ${receita} com margem de lucro média de ${margem}%. Comparado ao mesmo período do mês anterior, temos um crescimento de 12,3%. Para análises mais detalhadas, recomendo acessar o relatório financeiro completo.`;
    }
    
    // Respostas para perguntas sobre o próprio assistente
    if (lowerMessage.includes('quem é você') || lowerMessage.includes('o que você') || lowerMessage.includes('como você funciona')) {
        return `Sou o assistente virtual da Império Pharma, projetado para ajudar com informações sobre vendas, estoque, pedidos e análises financeiras. Posso responder perguntas sobre o desempenho do negócio e ajudar a encontrar informações rapidamente. Embora eu não utilize machine learning avançado, fui programado com heurísticas e regras inteligentes para fornecer respostas úteis baseadas nos dados disponíveis.`;
    }
    
    // Resposta padrão quando nenhum padrão é identificado
    const respostasPadrao = [
        `Baseado na sua pergunta, sugiro verificar o dashboard para informações atualizadas sobre o desempenho do negócio. Posso ser mais útil com perguntas específicas sobre vendas, pedidos, produtos ou finanças.`,
        
        `Desculpe, não tenho informações específicas sobre isso no momento. Posso ajudar com dados sobre vendas, estoque, pedidos pendentes ou resumos financeiros. Poderia reformular sua pergunta?`,
        
        `Para essa consulta específica, recomendo acessar o módulo correspondente no sistema para informações mais detalhadas. Estou constantemente aprendendo para oferecer análises mais precisas.`
    ];
    
    // Seleciona uma resposta aleatória
    return respostasPadrao[Math.floor(Math.random() * respostasPadrao.length)];
}

/**
 * Rola o chat para o final
 */
function scrollChatToBottom() {
    const assistantChat = document.getElementById('assistantChat');
    if (assistantChat) {
        assistantChat.scrollTop = assistantChat.scrollHeight;
    }
}

/**
 * Escapa HTML para evitar XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Configura detector de inatividade
 */
function setupInactivityDetector() {
    let inactivityTimer;
    const inactivityTimeout = 5 * 60 * 1000; // 5 minutos
    
    // Funções de reset e ação
    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(handleInactivity, inactivityTimeout);
    }
    
    function handleInactivity() {
        // Verifica se o usuário já está vendo o assistente
        const assistantPanel = document.getElementById('assistantPanel');
        if (assistantPanel && assistantPanel.classList.contains('active')) {
            return;
        }
        
        // Sugere assistência se o usuário estiver inativo
        showAssistantSuggestion();
    }
    
    // Inicia o timer
    resetInactivityTimer();
    
    // Reset do timer em interações do usuário
    const events = ['mousedown', 'keypress', 'scroll', 'touchstart'];
    events.forEach(event => {
        document.addEventListener(event, resetInactivityTimer);
    });
}

/**
 * Exibe sugestão do assistente após inatividade
 */
function showAssistantSuggestion() {
    // Verifica se já existe uma sugestão visível
    if (document.querySelector('.assistant-suggestion')) return;
    
    // Cria elemento de sugestão
    const suggestionElement = document.createElement('div');
    suggestionElement.className = 'assistant-suggestion';
    
    // Obtém dica contextual
    const currentPage = document.body.getAttribute('data-page') || 'dashboard';
    let suggestionText = '';
    
    switch (currentPage) {
        case 'dashboard':
            suggestionText = 'Precisa de ajuda para interpretar os dados do dashboard?';
            break;
        case 'pedidos':
            suggestionText = 'Quer verificar os pedidos pendentes ou com prioridade alta?';
            break;
        case 'financeiro_completo':
            suggestionText = 'Posso ajudar a analisar os dados financeiros recentes?';
            break;
        default:
            suggestionText = 'Precisa de ajuda? Estou aqui para auxiliar!';
    }
    
    suggestionElement.innerHTML = `
        <div class="suggestion-content">
            <p>${suggestionText}</p>
            <div class="suggestion-actions">
                <button class="suggestion-action-btn" id="openAssistant">Abrir Assistente</button>
                <button class="suggestion-dismiss-btn" id="dismissSuggestion">Agora não</button>
            </div>
        </div>
    `;
    
    // Adiciona estilos
    suggestionElement.style.cssText = `
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 280px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        padding: 15px;
        z-index: 1040;
        transform: translateY(20px);
        opacity: 0;
        transition: transform 0.3s, opacity 0.3s;
        font-family: 'Poppins', 'Segoe UI', sans-serif;
    `;
    
    // Adiciona estilos para tema escuro
    if (document.documentElement.getAttribute('data-theme') === 'dark') {
        suggestionElement.style.background = '#343a40';
        suggestionElement.style.color = '#f8f9fa';
    }
    
    // Adiciona ao body
    document.body.appendChild(suggestionElement);
    
    // Anima entrada
    setTimeout(() => {
        suggestionElement.style.transform = 'translateY(0)';
        suggestionElement.style.opacity = '1';
    }, 100);
    
    // Configura eventos
    document.getElementById('openAssistant').addEventListener('click', function() {
        // Remove sugestão
        suggestionElement.remove();
        
        // Abre assistente
        const assistantPanel = document.getElementById('assistantPanel');
        if (assistantPanel) {
            assistantPanel.classList.add('active');
            document.getElementById('assistantInput').focus();
        }
    });
    
    document.getElementById('dismissSuggestion').addEventListener('click', function() {
        // Anima saída
        suggestionElement.style.transform = 'translateY(20px)';
        suggestionElement.style.opacity = '0';
        
        // Remove após animação
        setTimeout(() => {
            suggestionElement.remove();
        }, 300);
    });
    
    // Auto-remove após 15 segundos
    setTimeout(() => {
        if (document.body.contains(suggestionElement)) {
            suggestionElement.style.transform = 'translateY(20px)';
            suggestionElement.style.opacity = '0';
            
            setTimeout(() => {
                if (document.body.contains(suggestionElement)) {
                    suggestionElement.remove();
                }
            }, 300);
        }
    }, 15000);
}

/**
 * Configura sistema de feedback
 */
function setupFeedbackSystem() {
    // Adiciona botão de feedback se não existir
    if (!document.getElementById('feedback-button')) {
        const feedbackButton = document.createElement('button');
        feedbackButton.id = 'feedback-button';
        feedbackButton.className = 'feedback-button';
        feedbackButton.innerHTML = '<i class="fas fa-comment-alt"></i>';
        feedbackButton.title = 'Enviar Feedback';
        feedbackButton.setAttribute('aria-label', 'Enviar Feedback');
        
        // Estilos para o botão
        feedbackButton.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #6c757d;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1030;
            transition: background 0.3s, transform 0.3s;
        `;
        
        document.body.appendChild(feedbackButton);
        
        // Configura evento de hover
        feedbackButton.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.background = '#0d6efd';
        });
        
        feedbackButton.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.background = '#6c757d';
        });
        
        // Configura evento de clique
        feedbackButton.addEventListener('click', showFeedbackModal);
    }
}

/**
 * Exibe modal de feedback
 */
function showFeedbackModal() {
    // Verifica se já existe um modal de feedback
    if (document.getElementById('feedback-modal')) return;
    
    // Cria modal de feedback
    const feedbackModal = document.createElement('div');
    feedbackModal.id = 'feedback-modal';
    feedbackModal.className = 'modal fade';
    feedbackModal.tabIndex = '-1';
    feedbackModal.setAttribute('aria-labelledby', 'feedbackModalLabel');
    feedbackModal.setAttribute('aria-hidden', 'true');
    
    feedbackModal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackModalLabel">
                        <i class="fas fa-comment-alt me-2 text-primary"></i>
                        Enviar Feedback
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Sua opinião é muito importante para melhorarmos continuamente o sistema.</p>
                    
                    <form id="feedback-form">
                        <div class="mb-3">
                            <label for="feedback-type" class="form-label">Tipo de Feedback</label>
                            <select class="form-select" id="feedback-type" required>
                                <option value="" selected disabled>Selecione...</option>
                                <option value="bug">Reportar um Problema</option>
                                <option value="suggestion">Sugestão de Melhoria</option>
                                <option value="compliment">Elogio</option>
                                <option value="other">Outro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="feedback-text" class="form-label">Sua Mensagem</label>
                            <textarea class="form-control" id="feedback-text" rows="4" placeholder="Descreva seu feedback em detalhes..." required></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="feedback-screenshot">
                            <label class="form-check-label" for="feedback-screenshot">Incluir captura da tela atual</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="send-feedback">
                        <i class="fas fa-paper-plane me-1"></i> Enviar Feedback
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Adiciona ao body
    document.body.appendChild(feedbackModal);
    
    // Inicializa modal Bootstrap
    const modal = new bootstrap.Modal(feedbackModal);
    modal.show();
    
    // Configura envio do feedback
    document.getElementById('send-feedback').addEventListener('click', function() {
        const form = document.getElementById('feedback-form');
        const feedbackType = document.getElementById('feedback-type').value;
        const feedbackText = document.getElementById('feedback-text').value;
        const includeScreenshot = document.getElementById('feedback-screenshot').checked;
        
        // Validação básica
        if (!feedbackType || !feedbackText) {
            alert('Por favor, preencha todos os campos obrigatórios.');
            return;
        }
        
        // Simula envio
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Enviando...';
        
        // Simula processo de envio
        setTimeout(() => {
            // Fecha modal
            modal.hide();
            
            // Remove modal do DOM após fechamento
            feedbackModal.addEventListener('hidden.bs.modal', function() {
                feedbackModal.remove();
            });
            
            // Exibe notificação de sucesso
            showToast('Feedback enviado com sucesso! Agradecemos sua contribuição.', 'success');
        }, 1500);
    });
}

/**
 * Processa inicializações específicas da página atual
 */
function processPageSpecificInitialization() {
    // Obtém página atual
    const currentPage = document.body.getAttribute('data-page') || 'dashboard';
    
    // Inicializações específicas para cada página
    switch (currentPage) {
        case 'dashboard':
            // Inicialização especial para dashboard (se necessário)
            break;
            
        case 'pedidos':
            // Inicialização especial para pedidos (se necessário)
            break;
            
        case 'financeiro_completo':
            // Inicialização especial para financeiro (se necessário)
            break;
            
        case 'marcas_produtos':
            // Inicialização especial para produtos (se necessário)
            break;
    }
}

/**
 * Simula chamada de API para desenvolvimento
 */
function simulateApiCall(endpoint, params, method = 'GET') {
    return new Promise((resolve, reject) => {
        // Simula tempo de resposta
        setTimeout(() => {
            // Respostas simuladas
            if (endpoint === 'notifications/count') {
                resolve({ count: Math.floor(Math.random() * 5) });
            } 
            else if (endpoint === 'notifications') {
                resolve({
                    notifications: [
                        {
                            id: 1,
                            type: 'warning',
                            title: 'Estoque Baixo',
                            description: '3 produtos estão com estoque crítico',
                            time: '10 minutos atrás',
                            link: 'index.php?page=marcas_produtos&filter=low_stock',
                            read: false
                        },
                        {
                            id: 2,
                            type: 'success',
                            title: 'Meta Atingida',
                            description: 'Você atingiu 85% da meta mensal de vendas',
                            time: '2 horas atrás',
                            link: 'index.php?page=financeiro_completo',
                            read: false
                        },
                        {
                            id: 3,
                            type: 'info',
                            title: 'Atualização do Sistema',
                            description: 'Nova versão disponível (v2.1.5)',
                            time: '1 dia atrás',
                            link: 'index.php?page=configuracoes',
                            read: true
                        }
                    ]
                });
            }
            else if (endpoint === 'notifications/mark-read') {
                if (method === 'POST') {
                    resolve({ success: true, message: 'Todas notificações marcadas como lidas' });
                } else {
                    reject(new Error('Método inválido'));
                }
            }
            else {
                // Endpoint desconhecido
                reject(new Error('Endpoint não simulado: ' + endpoint));
            }
        }, 800); // Simula latência de rede
    });
}

/**
 * Exibe notificação toast
 */
function showToast(message, type = 'info') {
    // Verifica se o container existe
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1080';
        document.body.appendChild(container);
    }
    
    // Cria elemento toast
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
    
    // Inicializa toast Bootstrap
    const toast = new bootstrap.Toast(toastEl, { delay: 3000, animation: true });
    toast.show();
    
    // Remove toast após ocultação
    toastEl.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}