/********************************************************
 * SCRIPT.JS – Versão Profissional Revisada
 * 
 * - Corrigidos problemas de rolagem automática
 * - Interface otimizada com feedback preciso
 * - Sistema de notificações aprimorado
 * - Tratamento de erros robusto
 * - Performance e usabilidade melhoradas
 ********************************************************/

/********************************************************
 * CONFIGURAÇÕES DE FRETE
 ********************************************************/
const shippingData = {
  SEDEX: {
    SP: 45.0, DF: 65.0, RJ: 75.0, MG: 75.0, GO: 75.0, PR: 65.0, SC: 75.0,
    ES: 75.0, RS: 130.0, MS: 90.0, MT: 95.0, BA: 95.0, CE: 110.0, SE: 130.0,
    PE: 120.0, AL: 130.0, PB: 130.0, RN: 130.0, PI: 130.0, MA: 130.0, PA: 110.0,
    AP: 130.0, AM: 130.0, TO: 110.0
  },
  PAC: {
    SP: 35.0, DF: 50.0, RJ: 50.0, ES: 50.0, MG: 50.0, GO: 50.0, PR: 50.0,
    SC: 50.0, RS: 50.0, MS: 50.0, MT: 60.0, BA: 60.0, CE: 75.0, SE: 105.0,
    AL: 115.0, PB: 105.0, RN: 105.0, PI: 105.0, AP: 105.0, TO: 95.0, PE: 95.0,
    MA: 105.0, AM: 105.0, PA: 95.0, RO: 105.0
  },
  TRANSPORTADORA: {
    SP: 53.0, RJ: 75.0, ES: 75.0, MG: 75.0, DF: 75.0, SC: 75.0, PR: 75.0,
    RS: 105.0, SE: 90.0, AL: 90.0, BA: 85.0, PB: 105.0, CE: 85.0, PI: 115.0,
    PA: 115.0, GO: 80.0, TO: 115.0, MS: 85.0, RN: 105.0, MA: 95.0, MT: 80.0,
    PE: 90.0, AM: 110.0, AP: 125.0, AC: 150.0
  }
};

/********************************************************
 * VARIÁVEIS GLOBAIS
 ********************************************************/
let catalogo = {};               // Armazena as marcas e produtos
let carrinho = [];               // Lista de itens no carrinho
let cupomAtivo = null;           // Cupom simples (ex.: DESCONTO10)
let descontoAplicado = 0;        // Valor fixo de desconto aplicado
let freteValor = 0;              // Valor do frete atual
let valorSeguro = 0;             // Valor do seguro (20% opcional)
window.comprovantePixFile = null; // Arquivo de comprovante (Pix)
let scrollPosition = 0;          // Controle de posição de rolagem
let prevPageSection = '';        // Rastreia a seção anterior

/********************************************************
 * EXEMPLO DE SUB-ITENS DE COMBO
 ********************************************************/
const comboOptionsExample = [
  { id: 101, nome: 'Item A do Combo' },
  { id: 102, nome: 'Item B do Combo' },
  { id: 103, nome: 'Item C do Combo' },
  { id: 104, nome: 'Item D do Combo' }
];

/********************************************************
 * UTILIDADES PARA CONTROLE DE ROLAGEM
 ********************************************************/
function salvarPosicaoRolagem() {
  scrollPosition = window.scrollY;
}

function restaurarPosicaoRolagem() {
  setTimeout(() => window.scrollTo(0, scrollPosition), 50);
}

function removerAlertaFormulario() {
  const alertas = document.querySelectorAll('.alerta-formulario, .erro-formulario, [id^="alerta"]');
  alertas.forEach(alerta => alerta.remove());
}

/********************************************************
 * LOADER INICIAL
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    const loader = document.getElementById('initialLoader');
    if (loader) {
      loader.style.opacity = '0';
      setTimeout(function() {
        loader.style.display = 'none';
        // Exibir modal de boas-vindas se não estiver em checkout
        mostrarModalBoasVindas();
      }, 300);
    }
  }, 800);
});

/********************************************************
 * CARREGAR CATÁLOGO (via PHP)
 ********************************************************/
async function carregarCatalogoDoBD() {
  try {
    mostrarLoading(true, "Carregando produtos...");
    const resp = await fetch('carregarCatalogo.php');
    
    if (!resp.ok) {
      throw new Error(`Erro ao carregar catálogo: ${resp.status} ${resp.statusText}`);
    }
    
    catalogo = await resp.json();
    console.log('Catálogo carregado:', catalogo);
    mostrarLoading(false);
    return true;
  } catch (err) {
    console.error('Erro ao carregar catálogo:', err);
    mostrarToast("Erro ao carregar produtos", "Verifique sua conexão e tente novamente.", "error");
    mostrarLoading(false);
    catalogo = {};
    return false;
  }
}

/********************************************************
 * FORMATAR VALOR EM BRL
 ********************************************************/
function formatarBRL(valor) {
  return valor.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

/********************************************************
 * CALCULAR SUBTOTAL DO CARRINHO
 ********************************************************/
function getCarrinhoSubtotal() {
  return carrinho.reduce((acc, item) => acc + (item.preco * item.quantidade), 0);
}

/********************************************************
 * SALVAR / CARREGAR CARRINHO (LocalStorage)
 ********************************************************/
function salvarCarrinhoLS() {
  localStorage.setItem('carrinho', JSON.stringify(carrinho));
}

function carregarCarrinhoLS() {
  const ls = localStorage.getItem('carrinho');
  carrinho = ls ? JSON.parse(ls) : [];
}

/********************************************************
 * SALVAR / RESTAURAR DADOS DO CLIENTE
 ********************************************************/
function salvarDadosClienteLS() {
  const dados = {
    nome:        document.getElementById('nomeCliente')?.value || '',
    cpf:         document.getElementById('cpfCliente')?.value || '',
    cel:         document.getElementById('celCliente')?.value || '',
    email:       document.getElementById('emailCliente')?.value || '',
    endereco:    document.getElementById('enderecoCliente')?.value || '',
    numero:      document.getElementById('numeroCliente')?.value || '',
    complemento: document.getElementById('complementoCliente')?.value || '',
    bairro:      document.getElementById('bairroCliente')?.value || '',
    cidade:      document.getElementById('cidadeCliente')?.value || '',
    estado:      document.getElementById('estadoCliente')?.value || '',
    cep:         document.getElementById('cepCliente')?.value || ''
  };
  localStorage.setItem('dadosCliente', JSON.stringify(dados));
}

function restaurarDadosClienteLS() {
  const dadosLS = localStorage.getItem('dadosCliente');
  if (dadosLS) {
    const d = JSON.parse(dadosLS);
    if (document.getElementById('nomeCliente')) {
      document.getElementById('nomeCliente').value       = d.nome || '';
      document.getElementById('cpfCliente').value        = d.cpf || '';
      document.getElementById('celCliente').value        = d.cel || '';
      document.getElementById('emailCliente').value      = d.email || '';
      document.getElementById('enderecoCliente').value   = d.endereco || '';
      document.getElementById('numeroCliente').value     = d.numero || '';
      document.getElementById('complementoCliente').value= d.complemento || '';
      document.getElementById('bairroCliente').value     = d.bairro || '';
      document.getElementById('cidadeCliente').value     = d.cidade || '';
      document.getElementById('estadoCliente').value     = d.estado || '';
      document.getElementById('cepCliente').value        = d.cep || '';
    }
  }
}

/********************************************************
 * ATUALIZAR ÍCONE DO CARRINHO
 ********************************************************/
function atualizarContagemCarrinho() {
  const cartCount = document.getElementById('cartCount');
  const headerCartCount = document.getElementById('headerCartCount');
  
  if (cartCount || headerCartCount) {
    const qtdTotal = carrinho.reduce((acc, item) => acc + item.quantidade, 0);
    
    if (cartCount) cartCount.textContent = qtdTotal;
    if (headerCartCount) headerCartCount.textContent = qtdTotal;
    
    // Animação quando a quantidade muda
    if (qtdTotal > 0) {
      if (cartCount) {
        cartCount.classList.add('pulse-animation');
        setTimeout(() => cartCount.classList.remove('pulse-animation'), 500);
      }
      if (headerCartCount) {
        headerCartCount.classList.add('pulse-animation');
        setTimeout(() => headerCartCount.classList.remove('pulse-animation'), 500);
      }
    }
  }
}

/********************************************************
 * VERIFICAÇÃO DO CARRINHO
 ********************************************************/
function verificarCarrinho() {
  if (carrinho.length === 0) {
    mostrarToast('Carrinho vazio', 'Adicione produtos antes de prosseguir.', 'info');
    return false;
  } else {
    mostrarSecao('paginaCarrinho');
    renderizarCarrinho();
    return true;
  }
}

/********************************************************
 * VERIFICAÇÃO DO PERFIL
 ********************************************************/
function verificarPerfil() {
  checarLogin().then(logado => {
    if (logado) {
      exibirPerfil();
    } else {
      // Salvar referência para redirecionar de volta
      sessionStorage.setItem('redirectAfterLogin', 'perfil');
      window.location.href = 'login.php';
    }
  });
}

/********************************************************
 * PROGRESSO DO CHECKOUT
 ********************************************************/
function updateCheckoutProgress(secaoAtual) {
  const steps = {
    paginaCarrinho: 'progressStep1',
    paginaClienteEnvio: 'progressStep2',
    paginaResumo: 'progressStep3',
    paginaPagamento: 'progressStep4'
  };
  const stepOrder = [
    'paginaCarrinho',
    'paginaClienteEnvio',
    'paginaResumo',
    'paginaPagamento'
  ];

  const progressBar = document.getElementById('checkoutProgressBar');
  if (!progressBar) return;

  stepOrder.forEach(s => {
    const elId = steps[s];
    const stepEl = document.getElementById(elId);
    if (stepEl) {
      stepEl.classList.remove('active', 'completed');
    }
  });
  
  const idx = stepOrder.indexOf(secaoAtual);
  if (idx >= 0) {
    for (let i = 0; i < idx; i++) {
      const stepEl = document.getElementById(steps[stepOrder[i]]);
      if (stepEl) stepEl.classList.add('completed');
    }
    const currentStep = document.getElementById(steps[secaoAtual]);
    if (currentStep) currentStep.classList.add('active');
    progressBar.style.display = 'flex';
  } else {
    progressBar.style.display = 'none';
  }
}

/********************************************************
 * MOSTRAR SEÇÃO (SPA)
 ********************************************************/
function mostrarSecao(secaoId) {
  // Salvar posição de rolagem atual
  salvarPosicaoRolagem();
  
  // Se já estamos na mesma seção, não fazer nada
  if (prevPageSection === secaoId) {
    return;
  }
  
  // Array com todas as seções possíveis
  const secoes = [
    'paginaInicial', 'paginaMarca', 'paginaCarrinho', 'paginaClienteEnvio',
    'paginaResumo', 'paginaPagamento', 'paginaAgradecimento', 'paginaPerfil'
  ];
  
  // Oculta todas as seções
  secoes.forEach(s => {
    const el = document.getElementById(s);
    if (el) el.classList.add('hidden');
  });
  
  // Remove todos os alertas quando trocar de seção
  removerAlertaFormulario();
  
  // Mostra a seção desejada
  const alvo = document.getElementById(secaoId);
  if (alvo) {
    alvo.classList.remove('hidden');
    // Rolagem para topo APENAS quando troca de seção principal
    window.scrollTo(0, 0);
    updateCheckoutProgress(secaoId);
    atualizarBotaoAtivo(secaoId);
    
    // Atualizar seção anterior
    prevPageSection = secaoId;
  }
}

// Adicionar classe ativa para o botão do menu atual
function atualizarBotaoAtivo(secaoId) {
  // Remover classe ativa de todos os botões
  document.querySelectorAll('.footer-button').forEach(btn => {
    btn.classList.remove('active');
  });
  
  // Adicionar classe para o botão correspondente
  if (secaoId === 'paginaInicial') {
    document.getElementById('btnMarcas')?.classList.add('active');
  } else if (secaoId === 'paginaCarrinho') {
    document.getElementById('btnCarrinho')?.classList.add('active');
  } else if (secaoId === 'paginaPerfil') {
    document.getElementById('btnPerfil')?.classList.add('active');
  }
}

/********************************************************
 * RESETAR FRETE
 ********************************************************/
function resetFrete() {
  freteValor = 0;
  const tipoFreteEl = document.getElementById('tipoFrete');
  if (tipoFreteEl) {
    const cepValue = (document.getElementById('cepCliente')?.value||'').replace(/\D/g,'');
    if (cepValue.length===8) {
      tipoFreteEl.disabled = false;
      tipoFreteEl.selectedIndex = 0;
    } else {
      tipoFreteEl.disabled = true;
    }
  }
  const fExp = document.getElementById('freteExplicativo');
  if (fExp) fExp.remove();
  const vFrete = document.getElementById('valorFrete');
  if (vFrete) vFrete.textContent = '0,00';
  const ff = document.getElementById('freteFeedback');
  if (ff) ff.style.display='none';
}

/********************************************************
 * VOLTAR PARA CARRINHO (botão)
 ********************************************************/
function voltarParaCarrinho() {
  resetFrete();
  mostrarSecao('paginaCarrinho');
  renderizarCarrinho();
}

/********************************************************
 * EXIBIR MARCA (com acordeões de produtos)
 ********************************************************/
function exibirMarca(marcaId, categoriaAlvo=null, produtoIdAlvo=null) {
  mostrarLoading(true, "Carregando produtos...");
  setTimeout(() => {
    mostrarSecao('paginaMarca');
    const marca = catalogo[marcaId];
    if (!marca) {
      mostrarToast("Erro ao carregar marca", "Marca não encontrada no catálogo.", "error");
      mostrarLoading(false);
      return;
    }

    // Remove aviso anterior
    const avisoAnterior = document.getElementById('avisoMarcaFreteSeparado');
    if (avisoAnterior) avisoAnterior.classList.add('hidden');

    // Banner
    const bannerEl = document.getElementById('bannerMarca');
    bannerEl.src = marca.banner || 'https://via.placeholder.com/900x500?text=SEM+BANNER';
    bannerEl.alt = marca.nome || 'Marca sem nome';
    document.getElementById('nomeMarca').textContent = marca.nome || 'Marca Desconhecida';

    const acordioesContainer = document.getElementById('acordioesContainer');
    acordioesContainer.innerHTML='';

    // Stock message custom
    if (marca.stock_message && marca.stock_message.trim()!=='') {
      const aviso = document.createElement('div');
      aviso.id = 'avisoMarcaFreteSeparado';
      aviso.classList.add('aviso-frete-separado');
      aviso.style.marginBottom='1rem';
      aviso.style.padding='0.75rem';
      aviso.innerHTML = `
        <i class="fa-solid fa-triangle-exclamation aviso-icon"></i>
        <p>${marca.stock_message.trim()}</p>
      `;
      acordioesContainer.parentElement.insertBefore(aviso, acordioesContainer);
    } else {
      const est = marca.stock || 1;
      if (est>1) {
        const av = document.createElement('div');
        av.id = 'avisoMarcaFreteSeparado';
        av.classList.add('aviso-frete-separado');
        av.style.marginBottom='1rem';
        av.style.padding='0.75rem';
        av.innerHTML = `
          <i class="fa-solid fa-triangle-exclamation aviso-icon"></i>
          <p><strong>ATENÇÃO:</strong> Esta marca está no Estoque ${est}. Misturar estoques gera frete adicional.</p>
        `;
        acordioesContainer.parentElement.insertBefore(av, acordioesContainer);
      }
    }

    // Acordeões
    const cats = Object.keys(marca.categorias);
    cats.forEach(cat => {
      const item = document.createElement('div');
      item.classList.add('acordion-item');

      const header = document.createElement('div');
      header.classList.add('acordion-header');
      header.textContent = cat;

      const body = document.createElement('div');
      body.classList.add('acordion-body');

      const listaProdutos = document.createElement('div');
      listaProdutos.classList.add('lista-produtos');

      // Lista de produtos
      marca.categorias[cat].forEach(prod => {
        const card = document.createElement('div');
        card.classList.add('card-produto');
        card.setAttribute('data-produto-id', prod.id);

        // Imagem
        const img = document.createElement('img');
        img.src = prod.imagem || 'https://via.placeholder.com/60?text=SEM+IMG';
        img.alt = prod.nome || 'Produto';
        img.onclick = e => {
          e.stopPropagation();
          abrirProdutoZoom(img.src);
        };

        // Info do produto
        const info = document.createElement('div');
        info.classList.add('info-produto');

        const nomeP = document.createElement('p');
        nomeP.classList.add('nome-produto');
        nomeP.textContent = prod.nome || 'Produto sem nome';

        const descP = document.createElement('p');
        descP.classList.add('desc-produto');
        descP.textContent = prod.descricao || '';

        const precoP = document.createElement('p');
        precoP.classList.add('preco-produto');
        let precoBase = prod.preco || 0;

        // Verifica promo
        if (prod.promo_price && prod.promo_price > 0) {
          precoP.innerHTML = `
            <span style="color:#999; text-decoration:line-through; margin-right:6px;">
              R$ ${formatarBRL(precoBase)}
            </span>
            <strong style="color:#e23b3b;">
              R$ ${formatarBRL(prod.promo_price)}
            </strong>
          `;
          precoBase = prod.promo_price;
        } else if (prod.promo && prod.promo > 0) {
          precoP.innerHTML = `
            <span style="color:#999; text-decoration:line-through; margin-right:6px;">
              R$ ${formatarBRL(precoBase)}
            </span>
            <strong style="color:#e23b3b;">
              R$ ${formatarBRL(prod.promo)}
            </strong>
          `;
          precoBase = prod.promo;
        } else {
          precoP.textContent = `R$ ${formatarBRL(precoBase)}`;
        }

        info.appendChild(nomeP);
        info.appendChild(descP);
        info.appendChild(precoP);

        // Ações (quantidade + botão "Adicionar")
        const acoes = document.createElement('div');
        acoes.classList.add('acoes-produto');

        const inpQtd = document.createElement('input');
        inpQtd.type = 'number';
        inpQtd.min = 1;
        inpQtd.value = 1;

        const btnAdd = document.createElement('button');
        btnAdd.classList.add('btn-adicionar-carrinho');
        btnAdd.innerHTML = '<i class="fa-solid fa-cart-plus"></i> <span>Adicionar</span>';
        btnAdd.onclick = () => {
          if (prod.isCombo) {
            abrirModalCombo(prod, parseInt(inpQtd.value), marcaId, precoBase);
          } else {
            adicionarAoCarrinho(prod, parseInt(inpQtd.value), marcaId);
          }
        };

        acoes.appendChild(inpQtd);
        acoes.appendChild(btnAdd);
        info.appendChild(acoes);

        card.appendChild(img);
        card.appendChild(info);
        listaProdutos.appendChild(card);
      });

      body.appendChild(listaProdutos);

      // Abre/fecha acordeão
      header.onclick = function() {
        // Salvar posição antes de alterar a visibilidade
        salvarPosicaoRolagem();
        
        const allBodies = document.querySelectorAll('.acordion-body');
        const allHeaders = document.querySelectorAll('.acordion-header');
        
        // Remover classe active de todos os headers
        allHeaders.forEach(h => h.classList.remove('active'));
        
        // Fechar todos os acordeões
        allBodies.forEach(b => b.style.maxHeight = null);
        
        if (!body.style.maxHeight || body.style.maxHeight === '0px') {
          header.classList.add('active');
          
          // Abrir este acordeão
          body.style.maxHeight = body.scrollHeight + 'px';
          
          // Somente role para o header do acordeão para melhor UX
          // Esperamos um pouco para o browser calcular o scrollHeight corretamente
          setTimeout(() => {
            const headerRect = header.getBoundingClientRect();
            const offsetTop = headerRect.top + window.pageYOffset;
            window.scrollTo({
              top: offsetTop - 20,
              behavior: 'smooth'
            });
          }, 100);
        }
      };

      item.appendChild(header);
      item.appendChild(body);
      acordioesContainer.appendChild(item);
    });

    // Se veio categoria e produto a destacar
    if (categoriaAlvo && produtoIdAlvo) {
      destacarProdutoNaMarca(categoriaAlvo, produtoIdAlvo);
    }
    
    mostrarLoading(false);
  }, 300);
}

/********************************************************
 * DESTAQUE DE PRODUTO APÓS PESQUISA
 ********************************************************/
function destacarProdutoNaMarca(categoriaAlvo, produtoIdAlvo) {
  const acordioes = document.querySelectorAll('.acordion-item');
  acordioes.forEach(item => {
    const hdr = item.querySelector('.acordion-header');
    if (hdr && hdr.textContent.trim() === categoriaAlvo) {
      const body = item.querySelector('.acordion-body');
      hdr.classList.add('active');
      body.style.maxHeight = body.scrollHeight + 'px';
      
      setTimeout(() => {
        const prodDiv = body.querySelector(`[data-produto-id="${produtoIdAlvo}"]`);
        if (prodDiv) {
          // Rolar suavemente para o produto específico
          const prodOffset = prodDiv.offsetTop;
          const bodyOffset = body.offsetTop;
          const totalOffset = prodOffset + bodyOffset - 80; // Ajuste para o header
          
          window.scrollTo({
            top: totalOffset,
            behavior: 'smooth'
          });
          
          // Destacar visualmente o produto
          prodDiv.classList.add('produto-destaque');
          setTimeout(() => {
            prodDiv.classList.remove('produto-destaque');
          }, 2000);
        }
      }, 300);
    }
  });
}

/********************************************************
 * MONTAR LISTAS DE MARCAS (HOME)
 ********************************************************/
function montarListasMarcas() {
  const cImp = document.getElementById('containerImportadas');
  const cPre = document.getElementById('containerPremium');
  const cNac = document.getElementById('containerNacionais');
  const cDiv = document.getElementById('containerDiversos');
  if (!cImp || !cPre || !cNac || !cDiv) return;

  cImp.innerHTML = '';
  cPre.innerHTML = '';
  cNac.innerHTML = '';
  cDiv.innerHTML = '';

  Object.keys(catalogo).forEach(brandSlug => {
    const bd = catalogo[brandSlug];
    const tipo = bd.brand_type || 'Diversos';
    let target = cDiv;

    if (tipo === 'Marcas Importadas') target = cImp;
    else if (tipo === 'Marcas Premium') target = cPre;
    else if (tipo === 'Marcas Nacionais') target = cNac;

    const card = document.createElement('div');
    card.classList.add('marca-card');
    card.onclick = () => exibirMarca(brandSlug);

    const img = document.createElement('img');
    img.src = bd.btn_image || '';
    img.alt = bd.nome || 'Marca sem nome';

    card.appendChild(img);
    target.appendChild(card);
  });
}

/********************************************************
 * BOTÕES DE MENU (Marcas, Carrinho, Menu)
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btnMarcas')?.addEventListener('click', function() {
    mostrarSecao('paginaInicial');
  });
  document.getElementById('btnCarrinho')?.addEventListener('click', function() {
    verificarCarrinho();
  });
  document.getElementById('btnMenuCentral')?.addEventListener('click', function() {
    abrirMenu();
  });
  document.getElementById('btnPerfil')?.addEventListener('click', function() {
    verificarPerfil();
  });
  
  // Efeito de ripple nos botões do footer
  const footerButtons = document.querySelectorAll('.footer-button');
  footerButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      const rect = button.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      ripple.style.left = x + 'px';
      ripple.style.top = y + 'px';
      
      button.appendChild(ripple);
      
      setTimeout(() => {
        ripple.remove();
      }, 600);
    });
  });
  
  // Limpar campo de busca
  const clearSearch = document.getElementById('clearSearch');
  const searchInput = document.getElementById('searchInput');
  
  if (clearSearch && searchInput) {
    clearSearch.addEventListener('click', () => {
      searchInput.value = '';
      searchInput.focus();
    });
  }
});

/********************************************************
 * ADICIONAR AO CARRINHO
 ********************************************************/
function adicionarAoCarrinho(produto, quantidade, brandSlug){
  // Salvar posição de rolagem antes da operação
  salvarPosicaoRolagem();
  
  let precoFinal = produto.preco || 0;
  if (produto.promo_price && produto.promo_price > 0) {
    precoFinal = produto.promo_price;
  } else if (produto.promo && produto.promo > 0) {
    precoFinal = produto.promo;
  }
  const reqSep = (catalogo[brandSlug] && catalogo[brandSlug].separate_shipping === 1);
  const itemExist = carrinho.find(it => it.id === produto.id);

  if (itemExist) {
    itemExist.quantidade += quantidade;
    if (reqSep) {
      itemExist.requiresSeparateShipping = true;
      itemExist.brandSlug = brandSlug;
    }
  } else {
    carrinho.push({
      id: produto.id,
      nome: produto.nome,
      descricao: produto.descricao,
      preco: precoFinal,
      marca: catalogo[brandSlug]?.nome || brandSlug,
      quantidade,
      imagem: produto.imagem || '',
      requiresSeparateShipping: reqSep,
      brandSlug,
      comboItems: null
    });
  }
  salvarCarrinhoLS();
  atualizarContagemCarrinho();

  const novoSubtotal = getCarrinhoSubtotal();
  const modalSubEl = document.getElementById('modalCartSubtotal');
  if (modalSubEl) {
    modalSubEl.querySelector('span').textContent = formatarBRL(novoSubtotal);
  }
  const cartImageDiv = document.getElementById('modalCartImage');
  if (cartImageDiv) {
    cartImageDiv.innerHTML = '';
    if (produto.imagem) {
      const imgTag = document.createElement('img');
      imgTag.src = produto.imagem;
      imgTag.style.width = '100%';
      imgTag.style.height = '100%';
      imgTag.style.objectFit = 'cover';
      cartImageDiv.appendChild(imgTag);
    } else {
      cartImageDiv.innerHTML = `
        <img 
          src="https://via.placeholder.com/90?text=SEM+IMG" 
          style="width:100%; height:100%; object-fit:cover;"
        />
      `;
    }
  }
  exibirModalCarrinho(`${produto.nome} adicionado ao carrinho!`);
}

/********************************************************
 * MODAL: PRODUTO ADICIONADO
 ********************************************************/
function exibirModalCarrinho(msg) {
  document.getElementById('modalTitulo').textContent = 'Produto adicionado!';
  document.getElementById('modalMensagem').textContent = msg;
  document.getElementById('modalCarrinho').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btnContinuar')?.addEventListener('click', () => {
    document.getElementById('modalCarrinho')?.classList.add('hidden');
    restaurarPosicaoRolagem();
  });
  
  document.getElementById('btnIrCarrinho')?.addEventListener('click', () => {
    document.getElementById('modalCarrinho')?.classList.add('hidden');
    mostrarSecao('paginaCarrinho');
    renderizarCarrinho();
  });
  
  document.getElementById('btnCloseCartModal')?.addEventListener('click', () => {
    document.getElementById('modalCarrinho')?.classList.add('hidden');
    restaurarPosicaoRolagem();
  });
});

/********************************************************
 * RENDERIZAR CARRINHO
 ********************************************************/
function renderizarCarrinho() {
  const cartItems = document.getElementById('cartItems');
  if (!cartItems) return;

  cartItems.innerHTML = '';
  if (carrinho.length === 0) {
    cartItems.innerHTML = `
      <div class="empty-cart">
        <i class="fa-solid fa-cart-shopping empty-cart-icon"></i>
        <p>Seu carrinho está vazio</p>
        <button class="btn-continue-shopping" onclick="mostrarSecao('paginaInicial')">
          <i class="fa-solid fa-store"></i> Continuar comprando
        </button>
      </div>
    `;
    return;
  }

  carrinho.forEach((item, index) => {
    const divItem = document.createElement('div');
    divItem.classList.add('cart-item');

    const info = document.createElement('div');
    info.classList.add('cart-item-info');

    const subtotal = item.preco * item.quantidade;
    let comboText = '';
    if (item.comboItems && item.comboItems.length > 0) {
      comboText = '<br/><em>Itens do Combo:</em> ' 
                + item.comboItems.map(ci => ci.nome).join(', ');
    }

    info.innerHTML = `
      <p><strong>${item.nome}</strong> - ${item.marca}</p>
      <p>Qtd: ${item.quantidade} | Subtotal: R$ ${formatarBRL(subtotal)}${comboText}</p>
    `;

    const btnRemove = document.createElement('button');
    btnRemove.classList.add('cart-item-remove');
    btnRemove.innerHTML = '<i class="fa-solid fa-trash"></i> <span>Remover</span>';
    btnRemove.onclick = (e) => {
      e.preventDefault();
      confirmarRemocaoItem(index);
    };

    divItem.appendChild(info);
    divItem.appendChild(btnRemove);
    cartItems.appendChild(divItem);
  });
  atualizarResumoCarrinho();
}

/********************************************************
 * CONFIRMAR REMOÇÃO DE ITEM
 ********************************************************/
function confirmarRemocaoItem(index) {
  const item = carrinho[index];
  if (!item) return;
  
  // Salvar posição de rolagem antes de mostrar confirmação
  salvarPosicaoRolagem();
  
  mostrarConfirmacao(
    `Remover "${item.nome}"?`, 
    "Tem certeza que deseja remover este item do carrinho?",
    () => removerItemCarrinho(index)
  );
}

/********************************************************
 * REMOVER ITEM
 ********************************************************/
function removerItemCarrinho(index) {
  const item = carrinho[index];
  carrinho.splice(index, 1);
  salvarCarrinhoLS();
  atualizarContagemCarrinho();
  renderizarCarrinho();
  resetFrete();
  
  // Restaurar posição de rolagem após a remoção
  restaurarPosicaoRolagem();
  
  // Aviso de remoção
  mostrarToast("Item removido", `${item.nome} foi removido do carrinho.`, "info");
}

/********************************************************
 * ATUALIZAR RESUMO (Subtotal, Desconto, Total)
 ********************************************************/
function atualizarResumoCarrinho() {
  const subEl = document.getElementById('subtotalValor');
  const descInfo = document.getElementById('descontoInfo');
  const descValEl = document.getElementById('descontoValor');
  const totalValEl = document.getElementById('totalValor');
  if (!subEl || !descInfo || !descValEl || !totalValEl) return;

  const subtotal = getCarrinhoSubtotal();
  subEl.textContent = formatarBRL(subtotal);

  if (descontoAplicado > 0) {
    descInfo.classList.remove('hidden');
    descValEl.textContent = formatarBRL(descontoAplicado);
  } else {
    descInfo.classList.add('hidden');
  }
  const total = subtotal - descontoAplicado;
  totalValEl.textContent = formatarBRL(total);
}

/********************************************************
 * CUPOM SIMPLES
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('aplicarCupomBtn')?.addEventListener('click', () => {
    // Salvar posição de rolagem antes de aplicar cupom
    salvarPosicaoRolagem();
    
    const cupomInput = (document.getElementById('cupomInput')?.value || '').trim().toUpperCase();
    if (cupomInput === 'DESCONTO10' && cupomAtivo !== 'DESCONTO10') {
      cupomAtivo = 'DESCONTO10';
      descontoAplicado = 10;
      mostrarToast("Cupom aplicado", "Desconto de R$ 10,00 aplicado com sucesso.", "success");
    } else if (cupomInput === 'DESCONTO10' && cupomAtivo === 'DESCONTO10') {
      mostrarToast("Cupom já utilizado", "Este cupom já foi aplicado anteriormente.", "info");
    } else {
      mostrarToast("Cupom inválido", "O código informado não é válido.", "error");
    }
    atualizarResumoCarrinho();
    
    // Restaurar posição de rolagem após a operação
    restaurarPosicaoRolagem();
  });
});

/********************************************************
 * CONFIRMAR CARRINHO => LOGIN (se não logado)
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('confirmarCarrinhoBtn')?.addEventListener('click', async() => {
    if (carrinho.length === 0) {
      mostrarToast("Carrinho vazio", "Adicione produtos antes de prosseguir.", "info");
      return;
    }
    
    mostrarLoading(true, "Verificando login...");
    const logado = await checarLogin();
    mostrarLoading(false);
    
    if (!logado) {
      // Marcar que está em processo de checkout
      sessionStorage.setItem('checkoutEmAndamento', 'true');
      
      mostrarToast("Login necessário", "Faça login ou crie uma conta para continuar.", "info");
      setTimeout(() => {
        window.location.href = 'login.php?next=checkout';
      }, 1500);
      return;
    }
    
    resetFrete();
    mostrarSecao('paginaClienteEnvio');
  });
});

/********************************************************
 * LIMPAR ESTADO DE CHECKOUT
 ********************************************************/
function limparEstadoCheckout() {
  sessionStorage.removeItem('checkoutEmAndamento');
}

/********************************************************
 * CHECAR LOGIN (Ajax: checkLogin.php)
 ********************************************************/
async function checarLogin() {
  try {
    const resp = await fetch('checkLogin.php');
    if (!resp.ok) {
      throw new Error(`Erro ${resp.status}: ${resp.statusText}`);
    }
    const data = await resp.json();
    return data.logado === true;
  } catch (err) {
    console.error('Erro ao checar login:', err);
    mostrarToast("Erro de conexão", "Não foi possível verificar seu login. Tente novamente.", "error");
    return false;
  }
}

/********************************************************
 * VIA CEP
 ********************************************************/
async function obterEstadoPeloCEP() {
  const cepInput = document.getElementById('cepCliente');
  if (!cepInput) return;
  const cep = cepInput.value.replace(/\D/g, '');
  if (cep.length !== 8) return;

  // Salvar posição de rolagem antes de buscar CEP
  salvarPosicaoRolagem();

  const spinner = document.getElementById('cepLoadingSpinner');
  if (spinner) spinner.style.display = 'inline-block';

  try {
    const resp = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    if (!resp.ok) throw new Error('Não foi possível consultar o CEP na ViaCEP.');
    const data = await resp.json();
    if (data.erro) {
      mostrarToast("CEP não encontrado", "O CEP informado é inválido ou não foi encontrado.", "error");
      if (spinner) spinner.style.display = 'none';
      restaurarPosicaoRolagem();
      return;
    }
    
    document.getElementById('estadoCliente').value = data.uf || '';
    document.getElementById('cidadeCliente').value = data.localidade || '';
    document.getElementById('enderecoCliente').value = data.logradouro || '';
    document.getElementById('bairroCliente').value = data.bairro || '';

    const tipoFreteEl = document.getElementById('tipoFrete');
    if (tipoFreteEl) {
      tipoFreteEl.disabled = false;
      tipoFreteEl.selectedIndex = 0;
    }
    
    const ff = document.getElementById('freteFeedback');
    if (ff) {
      ff.style.display = 'flex';
    }
    
    // Remover alertas de formulário após preencher os dados do CEP
    removerAlertaFormulario();
    
    mostrarToast("CEP encontrado", "Endereço preenchido com sucesso.", "success");
  } catch (e) {
    console.error('Erro ao buscar CEP:', e);
    mostrarToast("Erro ao buscar CEP", "Não foi possível consultar o CEP. Tente novamente.", "error");
  } finally {
    if (spinner) spinner.style.display = 'none';
    // Restaurar posição de rolagem
    restaurarPosicaoRolagem();
  }
}

/********************************************************
 * CÁLCULO DE FRETE (Múltiplos estoques)
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('tipoFrete')?.addEventListener('change', () => {
    // Salvar posição atual antes de calcular frete
    salvarPosicaoRolagem();
    
    // Remover alertas de formulário quando o frete é calculado
    removerAlertaFormulario();
    
    const tf = document.getElementById('tipoFrete')?.value;
    const uf = (document.getElementById('estadoCliente')?.value || '').toUpperCase();
    let baseFrete = 0;
    if (tf && shippingData[tf] && shippingData[tf][uf]) {
      baseFrete = shippingData[tf][uf];
    }
    
    const estoques = new Set();
    carrinho.forEach(item => {
      const bSlug = item.brandSlug;
      if (catalogo[bSlug] && typeof catalogo[bSlug].stock !== 'undefined') {
        estoques.add(catalogo[bSlug].stock);
      } else {
        estoques.add(1);
      }
    });
    const qtdEst = estoques.size;
    const valorFinalFrete = baseFrete * qtdEst;

    let texto = '';
    if (qtdEst > 1) {
      texto = `<strong>Frete múltiplo:</strong> Seus produtos saem de ${qtdEst} estoques diferentes. `
            + `O frete base (R$ ${formatarBRL(baseFrete)}) será multiplicado por ${qtdEst}, `
            + `resultando em R$ ${formatarBRL(valorFinalFrete)}.`;
    } else {
      texto = `<strong>Frete único:</strong> Todos os produtos saem do mesmo estoque. Valor: R$ ${formatarBRL(valorFinalFrete)}.`;
    }
    freteValor = valorFinalFrete;

    const vF = document.getElementById('valorFrete');
    if (vF) vF.textContent = formatarBRL(valorFinalFrete);

    let fExp = document.getElementById('freteExplicativo');
    if (!fExp) {
      fExp = document.createElement('div');
      fExp.id = 'freteExplicativo';
      fExp.className = 'frete-explicativo';
      vF.parentNode.appendChild(fExp);
    }
    fExp.innerHTML = texto;
    
    // Restaurar posição de rolagem
    restaurarPosicaoRolagem();
    
    // Mostrar toast informativo
    if (qtdEst > 1) {
      mostrarToast("Frete múltiplo calculado", `Seus produtos saem de ${qtdEst} estoques diferentes.`, "info");
    } else {
      mostrarToast("Frete calculado", "Frete calculado com sucesso.", "success");
    }
  });
});

/********************************************************
 * VALIDAR FORMULÁRIO DE ENVIO
 ********************************************************/
function validarFormularioEnvio() {
  // Determinar em qual etapa do processo de checkout estamos
  const etapaAtual = document.querySelector('.main-content > section:not(.hidden)')?.id || '';
  const estaNaEtapaPagamento = etapaAtual === 'paginaPagamento';
  
  // Remover alertas existentes
  removerAlertaFormulario();
  
  // Lista de campos básicos obrigatórios
  const reqIds = [
    'nomeCliente', 'cpfCliente', 'celCliente', 'emailCliente',
    'cepCliente', 'enderecoCliente', 'numeroCliente',
    'bairroCliente', 'cidadeCliente', 'estadoCliente'
  ];
  
  // Remover classes de erro anteriores
  reqIds.forEach(id => {
    document.getElementById(id)?.classList.remove('campo-invalido');
  });
  document.getElementById('tipoFrete')?.classList.remove('campo-invalido');
  
  // Só remove classe de erro do pagamento se estivermos na etapa de pagamento
  if (estaNaEtapaPagamento) {
    document.getElementById('tipoPagamento')?.classList.remove('campo-invalido');
  }

  let isOk = true;
  let primeiroCampoInvalido = null;
  
  // Validar campos de cliente
  reqIds.forEach(id => {
    const el = document.getElementById(id);
    if (!el || !el.value.trim()) {
      if (el) {
        el.classList.add('campo-invalido');
        if (!primeiroCampoInvalido) primeiroCampoInvalido = el;
      }
      isOk = false;
    }
  });
  
  // Validar tipo de frete
  const tfValue = document.getElementById('tipoFrete')?.value.trim() || '';
  if (!tfValue) {
    const el = document.getElementById('tipoFrete');
    if (el) {
      el.classList.add('campo-invalido');
      if (!primeiroCampoInvalido) primeiroCampoInvalido = el;
      isOk = false;
    }
  }
  
  // Validar pagamento APENAS se estivermos na etapa de pagamento
  if (estaNaEtapaPagamento) {
    const pagamentoVal = document.getElementById('tipoPagamento')?.value.trim() || '';
    if (!pagamentoVal) {
      const el = document.getElementById('tipoPagamento');
      if (el) {
        el.classList.add('campo-invalido');
        if (!primeiroCampoInvalido) primeiroCampoInvalido = el;
        isOk = false;
      }
    }
  }

  // Feedback visual
  if (!isOk) {
    mostrarToast("Formulário incompleto", "Preencha todos os campos obrigatórios.", "error");
    if (primeiroCampoInvalido) {
      primeiroCampoInvalido.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => primeiroCampoInvalido.focus(), 500);
    }
  } else {
    salvarDadosClienteLS();
    mostrarToast("Informações validadas", "Todos os dados foram preenchidos corretamente.", "success");
  }
  return isOk;
}

/********************************************************
 * AVANÇAR PARA RESUMO
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btnAvancarResumo')?.addEventListener('click', () => {
    // Remover qualquer alerta persistente
    removerAlertaFormulario();
    
    if (!validarFormularioEnvio()) return;

    // Copia dados para o Resumo
    document.getElementById('resumoNome').textContent = document.getElementById('nomeCliente')?.value || '';
    document.getElementById('resumoCPF').textContent = document.getElementById('cpfCliente')?.value || '';
    document.getElementById('resumoCel').textContent = document.getElementById('celCliente')?.value || '';
    document.getElementById('resumoEmail').textContent = document.getElementById('emailCliente')?.value || '';
    document.getElementById('resumoCEP').textContent = document.getElementById('cepCliente')?.value || '';
    document.getElementById('resumoEndereco').textContent = document.getElementById('enderecoCliente')?.value || '';
    document.getElementById('resumoNumero').textContent = document.getElementById('numeroCliente')?.value || '';
    document.getElementById('resumoComplemento').textContent =
      document.getElementById('complementoCliente')?.value || '';
    document.getElementById('resumoBairro').textContent = document.getElementById('bairroCliente')?.value || '';
    document.getElementById('resumoCidade').textContent = document.getElementById('cidadeCliente')?.value || '';
    document.getElementById('resumoEstado').textContent = document.getElementById('estadoCliente')?.value || '';

    // Itens do carrinho
    const resumoCarrinho = document.getElementById('resumoItensCarrinho');
    if (!resumoCarrinho) return;
    resumoCarrinho.innerHTML = '';
    let subtotal = 0;
    carrinho.forEach(item => {
      const sub = item.preco * item.quantidade;
      subtotal += sub;
      const cEl = document.createElement('div');
      cEl.classList.add('cart-item');
      let comboText = '';
      if (item.comboItems && item.comboItems.length > 0) {
        comboText = `<br/><em>Itens do Combo:</em> ${item.comboItems.map(ci => ci.nome).join(', ')}`;
      }
      cEl.innerHTML = `
        <div class="cart-item-info">
          <p><strong>${item.nome}</strong> - ${item.marca}</p>
          <p>Qtd: ${item.quantidade} | Subtotal: R$ ${formatarBRL(sub)}${comboText}</p>
        </div>
      `;
      resumoCarrinho.appendChild(cEl);
    });

    if (descontoAplicado > 0) {
      document.getElementById('resumoDescontoInfo')?.classList.remove('hidden');
      document.getElementById('resumoDescontoValor').textContent = formatarBRL(descontoAplicado);
    } else {
      document.getElementById('resumoDescontoInfo')?.classList.add('hidden');
    }

    let total = (subtotal - descontoAplicado) + freteValor;
    const acrescCartao = 0;
    total += acrescCartao;

    document.getElementById('resumoFormaPagamento').textContent = 'PIX';
    document.getElementById('resumoFreteValor').textContent = formatarBRL(freteValor);
    document.getElementById('resumoAcrescimoCartao').textContent = formatarBRL(acrescCartao);
    document.getElementById('resumoTotal').textContent = formatarBRL(total);

    // Aviso (se vários estoques)
    let avisoF = document.getElementById('avisoFreteCheckout');
    if (avisoF) avisoF.style.display = 'none';
    const temFSep = carrinho.some(it => it.requiresSeparateShipping === true);
    if (temFSep) {
      avisoF = document.getElementById('avisoFreteCheckout');
      if (avisoF) avisoF.style.display = 'flex';
    }

    // Reseta seguro
    document.getElementById('checkboxSeguro').checked = false;
    document.getElementById('blocoValorSeguro').style.display = 'none';
    document.getElementById('resumoTotalFinal').textContent = formatarBRL(total);
    valorSeguro = 0;

    mostrarSecao('paginaResumo');
  });
});

/********************************************************
 * SEGURO (20%)
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('checkboxSeguro')?.addEventListener('change', function() {
    // Salvar posição atual antes da atualização
    salvarPosicaoRolagem();
    
    // Recupera o total atual sem seguro
    const totAtual = parseFloat(
      document.getElementById('resumoTotal').textContent.replace('.', '').replace(',', '.')
    );
    
    // Elemento onde o total final (com seguro, se aplicável) será exibido
    const elFinal = document.getElementById('resumoTotalFinal');
    
    // Elemento onde o valor do seguro é exibido (dentro do bloco seguro)
    const elValorSeguro = document.getElementById('valorSeguro');
    
    // Bloco que contém o valor do seguro (para mostrar ou ocultar)
    const blocoSeguro = document.getElementById('blocoValorSeguro');
    
    if (this.checked) {
      // Calcula 20% do total atual para o seguro
      valorSeguro = totAtual * 0.2;
      // Atualiza o total final para incluir o seguro
      elFinal.textContent = formatarBRL(totAtual + valorSeguro);
      // Atualiza o valor do seguro exibido no span
      elValorSeguro.textContent = formatarBRL(valorSeguro);
      // Torna visível o bloco que mostra o valor do seguro
      blocoSeguro.style.display = 'flex';
      
      // Não rola para o topo, só mostra a confirmação sem mover a página
      mostrarConfirmacao(
        "Confirmação do Seguro",
        "Você está adicionando o seguro ao seu pedido. O seguro protege contra retenção/apreensão, " +
        "extravio, roubo e danos durante o transporte. É obrigatório filmar a abertura da embalagem " +
        "com o lacre intacto, mostrando toda a abertura da caixa.",
        null
      );
    } else {
      // Se desmarcar, zera o seguro e atualiza o total final para o total sem seguro
      valorSeguro = 0;
      elFinal.textContent = formatarBRL(totAtual);
      elValorSeguro.textContent = formatarBRL(0);
      // Oculta o bloco do valor do seguro
      blocoSeguro.style.display = 'none';
    }
    
    // Restaurar posição após atualização
    restaurarPosicaoRolagem();
  });
});

/********************************************************
 * IR PARA PAGAMENTO
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btnIrPagamento')?.addEventListener('click', () => {
    mostrarSecao('paginaPagamento');
    const totRes = parseFloat(
      document.getElementById('resumoTotal').textContent.replace('.', '').replace(',', '.')
    );
    const totalFinal = valorSeguro ? (totRes + valorSeguro) : totRes;
    const sessaoPIX = document.getElementById('sessaoPIX');
    if (sessaoPIX) {
      sessaoPIX.classList.remove('hidden');
      document.getElementById('valorPix').textContent = formatarBRL(totalFinal);
    }
  });
});

/********************************************************
 * UPLOAD COMPROVANTE PIX
 ********************************************************/
function configurarUploadPix(inputId, previewId, btnFinalizarId) {
  const inp = document.getElementById(inputId);
  const pre = document.getElementById(previewId);
  const btn = document.getElementById(btnFinalizarId);
  if (!inp || !pre || !btn) return;

  inp.addEventListener('change', function() {
    // Salvar posição antes da atualização
    salvarPosicaoRolagem();
    
    if (this.files && this.files.length > 0) {
      const file = this.files[0];
      pre.textContent = `Arquivo anexado: ${file.name}`;
      btn.disabled = false;
      window.comprovantePixFile = file;
      
      mostrarToast("Comprovante anexado", "Comprovante de pagamento anexado com sucesso.", "success");
    }
    
    // Restaurar posição após atualização
    restaurarPosicaoRolagem();
  });
}

document.addEventListener('DOMContentLoaded', function() {
  configurarUploadPix('comprovantePix', 'comprovantePixPreview', 'btnFinalizarPedido');
});

/********************************************************
 * FINALIZAR PEDIDO
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btnFinalizarPedido')?.addEventListener('click', async() => {
    const btnFin = document.getElementById('btnFinalizarPedido');
    if (!btnFin || btnFin.disabled) return;

    const checkTermos = document.getElementById('checkTermos');
    if (checkTermos && !checkTermos.checked) {
      mostrarToast("Termos não aceitos", "Você precisa aceitar os termos para finalizar.", "error");
      return;
    }
    
    btnFin.disabled = true;
    const textoOriginal = btnFin.innerHTML;
    btnFin.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Processando...</span>';
    
    mostrarLoading(true, "Finalizando seu pedido...");

    const dadosPedido = {
      cliente: {
        nome:       document.getElementById('nomeCliente')?.value || '',
        cpf:        document.getElementById('cpfCliente')?.value || '',
        cel:        document.getElementById('celCliente')?.value || '',
        email:      document.getElementById('emailCliente')?.value || '',
        endereco:   document.getElementById('enderecoCliente')?.value || '',
        numero:     document.getElementById('numeroCliente')?.value || '',
        complemento: document.getElementById('complementoCliente')?.value || '',
        bairro:     document.getElementById('bairroCliente')?.value || '',
        cidade:     document.getElementById('cidadeCliente')?.value || '',
        estado:     document.getElementById('estadoCliente')?.value || '',
        cep:        document.getElementById('cepCliente')?.value || ''
      },
      carrinho,
      freteValor,
      desconto: descontoAplicado,
      seguro: valorSeguro,
      shipping_type: document.getElementById('tipoFrete')?.value || '',
      pagamento: 'PIX'
    };

    try {
      const fd = new FormData();
      fd.append('dadosJson', JSON.stringify(dadosPedido));
      if (window.comprovantePixFile) {
        fd.append('comprovanteFile', window.comprovantePixFile);
      }
      
      const resp = await fetch('salvarPedido.php', { 
        method: 'POST', 
        body: fd 
      });
      
      if (!resp.ok) {
        throw new Error(`Erro ${resp.status}: ${resp.statusText}`);
      }
      
      const js = await resp.json();
      if (js.sucesso) {
        // Limpar estado de checkout ao finalizar
        limparEstadoCheckout();
        
        const pedidoId = js.pedidoId;
        const numeroVendedor = '5569999369209'; // Ajuste conforme necessário

        const agora = new Date();
        const ano = agora.getFullYear();
        const mes = String(agora.getMonth() + 1).padStart(2, '0');
        const dia = String(agora.getDate()).padStart(2, '0');
        const yyyymmdd = `${ano}${mes}${dia}`;
        const pidPad = String(pedidoId).padStart(4, '0');
        const prefixo = 'M';
        const pedidoCod = `${prefixo}#${yyyymmdd}-${pidPad}`;
        const dataBrasilia = new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });

        let texto = `=== PEDIDO ${pedidoCod} ===\n`;
        texto += `Data/Hora: ${dataBrasilia}\n\n`;
        texto += `=== DADOS DO CLIENTE ===\n`;
        texto += `Nome: ${dadosPedido.cliente.nome}\n`;
        texto += `CPF: ${dadosPedido.cliente.cpf}\n`;
        texto += `Telefone: ${dadosPedido.cliente.cel}\n`;
        texto += `E-mail: ${dadosPedido.cliente.email}\n`;
        texto += `Endereço: ${dadosPedido.cliente.endereco}, Nº ${dadosPedido.cliente.numero}\n`;
        texto += `Complemento: ${dadosPedido.cliente.complemento}\n`;
        texto += `Bairro: ${dadosPedido.cliente.bairro}\n`;
        texto += `Cidade/Estado: ${dadosPedido.cliente.cidade}/${dadosPedido.cliente.estado}\n`;
        texto += `CEP: ${dadosPedido.cliente.cep}\n\n`;

        texto += `=== ITENS DO PEDIDO ===\n`;
        let totalTemp = 0;
        carrinho.forEach(item => {
          const sub = item.preco * item.quantidade;
          totalTemp += sub;
          let comboLine = '';
          if (item.comboItems && item.comboItems.length > 0) {
            comboLine = ' [combo: ' + item.comboItems.map(ci => ci.nome).join(', ') + ']';
          }
          texto += `${item.quantidade}x ${item.nome} (${item.marca})${comboLine} `
                +  `${sub.toFixed(2).replace('.', ',')}\n`;
        });

        texto += `\n=== RESUMO FINANCEIRO ===\n`;
        texto += `Subtotal Itens: ${totalTemp.toFixed(2).replace('.', ',')}\n`;
        texto += `Frete: ${dadosPedido.freteValor.toFixed(2).replace('.', ',')}\n`;
        if (dadosPedido.desconto > 0) {
          texto += `Desconto: ${dadosPedido.desconto.toFixed(2).replace('.', ',')}\n`;
        }
        if (dadosPedido.seguro > 0) {
          texto += `Seguro: ${dadosPedido.seguro.toFixed(2).replace('.', ',')}\n`;
        }
        const totalFinal = totalTemp - dadosPedido.desconto + dadosPedido.freteValor + dadosPedido.seguro;
        texto += `*Total Final: ${totalFinal.toFixed(2).replace('.', ',')}*\n\n`;
        texto += `FormaDePagamento: PIX\n`;
        texto += `Tipo de Frete: ${dadosPedido.shipping_type}\n`;

        const linkWhats = `https://api.whatsapp.com/send?phone=${numeroVendedor}&text=${encodeURIComponent(texto)}`;
        window.open(linkWhats, '_self');

        // Limpa carrinho e mostra "Agradecimento"
        carrinho = [];
        salvarCarrinhoLS();
        atualizarContagemCarrinho();
        mostrarSecao('paginaAgradecimento');
        mostrarLoading(false);
      } else {
        mostrarToast("Erro ao salvar pedido", js.mensagem || "Ocorreu um erro desconhecido", "error");
        btnFin.disabled = false;
        btnFin.innerHTML = textoOriginal;
        mostrarLoading(false);
      }
    } catch (err) {
      console.error(err);
      mostrarToast("Erro de conexão", "Não foi possível enviar o pedido. Tente novamente.", "error");
      btnFin.disabled = false;
      btnFin.innerHTML = textoOriginal;
      mostrarLoading(false);
    }
  });
});

/********************************************************
 * SLIDER (BANNERS) – Com controles de navegação
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  let currentBannerIndex = 0;
  const bannerImages = document.querySelectorAll('.banner-image');
  const dots = document.querySelectorAll('.dot');
  
  if (!bannerImages.length || !dots.length) return;
  
  // Função para alternar slides
  function showSlide(index) {
    // Remove a classe active de todas as imagens e dots
    bannerImages.forEach(img => img.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    // Adiciona a classe active ao slide atual
    bannerImages[index].classList.add('active');
    dots[index].classList.add('active');
  }
  
  // Configura os dots para navegação
  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      currentBannerIndex = index;
      showSlide(currentBannerIndex);
    });
  });
  
  // Rotação automática de banners
  const sliderInterval = setInterval(() => {
    currentBannerIndex = (currentBannerIndex + 1) % bannerImages.length;
    showSlide(currentBannerIndex);
  }, 4000);
  
  // Limpar o intervalo quando a página for fechada/trocada
  window.addEventListener('beforeunload', () => {
    clearInterval(sliderInterval);
  });
});

/********************************************************
 * PESQUISA DINÂMICA
 ********************************************************/
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('searchInput');
  const searchSuggestions = document.getElementById('searchSuggestions');
  
  if (searchInput && searchSuggestions) {
    // Função de pesquisa otimizada com debounce
    let searchTimeout;
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      
      const query = searchInput.value.toLowerCase().trim();
      if (!query) {
        searchSuggestions.classList.remove('active');
        searchSuggestions.innerHTML = '';
        return;
      }
      
      // Usar debounce para evitar muitas pesquisas
      searchTimeout = setTimeout(() => {
        searchSuggestions.innerHTML = '';
        
        const results = [];
        Object.keys(catalogo).forEach(brandKey => {
          const marca = catalogo[brandKey];
          Object.keys(marca.categorias).forEach(cat => {
            marca.categorias[cat].forEach(prod => {
              const nomeLow = (prod.nome || '').toLowerCase();
              if (nomeLow.includes(query)) {
                results.push({
                  marca: marca.nome,
                  marcaSlug: brandKey,
                  produto: prod,
                  categoria: cat
                });
              }
            });
          });
        });
        
        if (results.length > 0) {
          const fragment = document.createDocumentFragment();
          results.slice(0, 10).forEach(r => { // Limitar a 10 resultados para performance
            const p = document.createElement('p');
            let precoLabel = `R$ ${formatarBRL(r.produto.preco)}`;
            if ((r.produto.promo_price && r.produto.promo_price > 0)
               || (r.produto.promo && r.produto.promo > 0)) {
              const valPromo = r.produto.promo_price || r.produto.promo;
              precoLabel = `De R$ ${formatarBRL(r.produto.preco)} `
                         + `por R$ ${formatarBRL(valPromo)}`;
            }
            p.textContent = `${r.produto.nome} - ${r.marca} (${precoLabel})`;
            p.onclick = () => {
              searchSuggestions.classList.remove('active');
              exibirMarca(r.marcaSlug, r.categoria, r.produto.id);
              searchInput.value = ''; // Limpa a pesquisa após clicar
            };
            fragment.appendChild(p);
          });
          
          searchSuggestions.appendChild(fragment);
          searchSuggestions.classList.add('active');
        } else {
          searchSuggestions.classList.remove('active');
        }
      }, 300); // Aguardar 300ms antes de pesquisar
    });
    
    document.addEventListener('click', e => {
      if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
        searchSuggestions.classList.remove('active');
      }
    });
    
    // Mostrar ou esconder botão de limpar
    searchInput.addEventListener('input', function() {
      const clearButton = document.getElementById('clearSearch');
      if (clearButton) {
        clearButton.style.display = this.value.length > 0 ? 'block' : 'none';
      }
    });
  }
});

/********************************************************
 * MODAL DE BOAS-VINDAS
 ********************************************************/
function mostrarModalBoasVindas() {
  const modalBoasVindas = document.getElementById('modalBoasVindas');
  if (!modalBoasVindas) return;
  
  // Verificar parâmetros de URL
  const urlParams = new URLSearchParams(window.location.search);
  const nextParam = urlParams.get('next') || '';
  const secaoParam = urlParams.get('secao') || '';
  
  // Verificar se veio de uma autenticação para checkout
  const veioDeCheckout = sessionStorage.getItem('checkoutEmAndamento') === 'true';
  
  // Seções onde não mostramos o modal
  const skipCheckoutSecoes = [
    'paginaCarrinho', 'paginaClienteEnvio',
    'paginaResumo', 'paginaPagamento', 'paginaPerfil'
  ];
  
  // Não mostrar o modal se:
  // - Está em processo de checkout OU
  // - A seção atual é uma das etapas de checkout
  if (nextParam === 'checkout' || 
      skipCheckoutSecoes.includes(secaoParam) || 
      veioDeCheckout) {
    modalBoasVindas.classList.add('hidden');
    
    // Se veio de checkout e chegou na página de dados do cliente,
    // limpa a flag pois o fluxo foi completado corretamente
    if (secaoParam === 'paginaClienteEnvio') {
      sessionStorage.removeItem('checkoutEmAndamento');
    }
  } else {
    modalBoasVindas.classList.remove('hidden');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const btnFecharBoasVindas = document.getElementById('btnFecharBoasVindas');
  if (btnFecharBoasVindas) {
    btnFecharBoasVindas.addEventListener('click', function() {
      const modal = document.getElementById('modalBoasVindas');
      if (modal) {
        modal.classList.add('hidden');
      }
    });
  }
  
  // Suporte à tecla Escape para fechar modais
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const modais = document.querySelectorAll('.modal, .modal-boas-vindas');
      modais.forEach(modal => {
        if (!modal.classList.contains('hidden')) {
          modal.classList.add('hidden');
        }
      });
    }
  });
});

/********************************************************
 * MOSTRAR TOAST (NOTIFICAÇÕES)
 ********************************************************/
function mostrarToast(titulo, mensagem, tipo = 'info') {
  const toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) return;
  
  // Remover classe hidden
  toastContainer.classList.remove('hidden');
  
  // Criar elemento de toast
  const toast = document.createElement('div');
  toast.className = `toast toast-${tipo}`;
  
  // Definir ícone com base no tipo
  let icone = 'fa-info-circle';
  if (tipo === 'success') icone = 'fa-check-circle';
  if (tipo === 'error') icone = 'fa-exclamation-circle';
  
  // Conteúdo do toast
  toast.innerHTML = `
    <i class="fa-solid ${icone} toast-icon"></i>
    <div class="toast-content">
      <div class="toast-title">${titulo}</div>
      <div class="toast-message">${mensagem}</div>
    </div>
    <button class="toast-close" aria-label="Fechar notificação">
      <i class="fa-solid fa-times"></i>
    </button>
  `;
  
  // Adicionar ao container
  toastContainer.appendChild(toast);
  
  // Configurar fechamento
  toast.querySelector('.toast-close').addEventListener('click', () => {
    toast.style.opacity = '0';
    setTimeout(() => {
      toast.remove();
      // Se não houver mais toasts, esconder o container
      if (toastContainer.children.length === 0) {
        toastContainer.classList.add('hidden');
      }
    }, 300);
  });
  
  // Auto-fechar após 5 segundos
  setTimeout(() => {
    if (toast.parentElement) {
      toast.style.opacity = '0';
      setTimeout(() => {
        toast.remove();
        // Se não houver mais toasts, esconder o container
        if (toastContainer.children.length === 0) {
          toastContainer.classList.add('hidden');
        }
      }, 300);
    }
  }, 5000);
}

/********************************************************
 * MODAL DE CONFIRMAÇÃO
 ********************************************************/
function mostrarConfirmacao(titulo, mensagem, callback) {
  // Salvar posição de rolagem atual
  salvarPosicaoRolagem();
  
  // Verifica se o modal já existe, se não, cria
  let modalConfirmacao = document.getElementById('modalConfirmacao');
  
  if (!modalConfirmacao) {
    modalConfirmacao = document.createElement('div');
    modalConfirmacao.id = 'modalConfirmacao';
    modalConfirmacao.className = 'modal';
    modalConfirmacao.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <h3 class="modal-title" id="modalConfirmacaoTitulo"></h3>
          <button class="modal-close" id="btnCloseConfirmacao" aria-label="Fechar janela de confirmação">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
        <p class="modal-message" id="modalConfirmacaoMensagem"></p>
        <div class="modal-buttons">
          <button id="btnCancelarConfirmacao" class="btn-modal-continue">
            <i class="fa-solid fa-times"></i> <span>Cancelar</span>
          </button>
          <button id="btnConfirmarAcao" class="btn-modal-carrinho">
            <i class="fa-solid fa-check"></i> <span>Confirmar</span>
          </button>
        </div>
      </div>
    `;
    document.body.appendChild(modalConfirmacao);
    
    // Adiciona listeners
    document.getElementById('btnCloseConfirmacao').addEventListener('click', () => {
      modalConfirmacao.classList.add('hidden');
      restaurarPosicaoRolagem();
    });
    
    document.getElementById('btnCancelarConfirmacao').addEventListener('click', () => {
      modalConfirmacao.classList.add('hidden');
      restaurarPosicaoRolagem();
    });
  }
  
  // Atualiza o conteúdo
  document.getElementById('modalConfirmacaoTitulo').textContent = titulo;
  document.getElementById('modalConfirmacaoMensagem').textContent = mensagem;
  
  // Configura callback apenas se for fornecida uma função
  const btnConfirmar = document.getElementById('btnConfirmarAcao');
  const btnCancelar = document.getElementById('btnCancelarConfirmacao');
  
  if (callback) {
    btnConfirmar.style.display = 'block';
    btnConfirmar.onclick = () => {
      modalConfirmacao.classList.add('hidden');
      callback();
    };
    btnCancelar.textContent = 'Cancelar';
  } else {
    // Se não houver callback, estamos apenas usando para mostrar informação
    btnConfirmar.style.display = 'none';
    btnCancelar.innerHTML = '<i class="fa-solid fa-check"></i> <span>Entendi</span>';
  }
  
  // Mostra o modal
  modalConfirmacao.classList.remove('hidden');
}

/********************************************************
 * LOADING GLOBAL
 ********************************************************/
function mostrarLoading(exibir, mensagem = 'Carregando...') {
  let loadingOverlay = document.getElementById('loadingOverlay');
  
  if (!loadingOverlay && exibir) {
    loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loadingOverlay';
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = `
      <div class="loading-container">
        <div class="loading-spinner">
          <svg viewBox="0 0 50 50" aria-label="Carregando">
            <circle cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
          </svg>
        </div>
        <p class="loading-text" id="loadingText">${mensagem}</p>
      </div>
    `;
    document.body.appendChild(loadingOverlay);
  } else if (loadingOverlay) {
    const loadingText = document.getElementById('loadingText');
    if (loadingText) loadingText.textContent = mensagem;
    
    if (exibir) {
      loadingOverlay.style.display = 'flex';
    } else {
      loadingOverlay.style.opacity = '0';
      setTimeout(() => {
        loadingOverlay.style.display = 'none';
        loadingOverlay.style.opacity = '1';
      }, 300);
    }
  }
}

/********************************************************
 * PRODUTO ZOOM (MODAL)
 ********************************************************/
function abrirProdutoZoom(urlImagem) {
  // Salvar posição atual
  salvarPosicaoRolagem();
  
  const modalZ = document.getElementById('modalProdutoZoom');
  const zoomImg = document.getElementById('zoomProdutoImage');
  if (modalZ && zoomImg) {
    zoomImg.src = urlImagem;
    modalZ.classList.remove('hidden');
  }
}

function fecharProdutoZoom(e) {
  if (!e || e.target.id === 'modalProdutoZoom' || e.target.id === 'btnCloseProdutoZoom') {
    document.getElementById('modalProdutoZoom')?.classList.add('hidden');
    restaurarPosicaoRolagem();
  }
}

/********************************************************
 * SERVICE WORKER (PWA)
 ********************************************************/
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/miguel/service-worker.js')
      .then(reg => console.log('SW registrado:', reg))
      .catch(err => console.error('Erro ao registrar SW:', err));
  });
}

/********************************************************
 * EVITAR AUTOZOOM NO iOS (Opcional)
 ********************************************************/
function configureInputFocusZoom() {
  const campos = document.querySelectorAll('input, select, textarea');
  campos.forEach(el => {
    el.addEventListener('focus', () => {
      // Salvar posição quando o campo recebe foco
      salvarPosicaoRolagem();
    });
    
    el.addEventListener('blur', () => {
      // Não fazer nada especial ao perder foco, para não rolar a tela
    });
  });
}

/********************************************************
 * MENU FUTURISTA (DRAWER)
 ********************************************************/
function abrirMenu() {
  const overlay = document.getElementById('drawerOverlay');
  const drawer = document.getElementById('menuDrawer');
  if (overlay && drawer) {
    overlay.classList.remove('hidden');
    drawer.classList.remove('hidden');
    drawer.classList.add('open');
  }
}

function fecharMenu() {
  const overlay = document.getElementById('drawerOverlay');
  const drawer = document.getElementById('menuDrawer');
  if (overlay && drawer) {
    drawer.classList.remove('open');
    setTimeout(() => {
      drawer.classList.add('hidden');
      overlay.classList.add('hidden');
    }, 400);
  }
}

function fecharMenuPorOverlay() {
  fecharMenu();
}

function toggleSubmenu(tipo) {
  // Salvar posição
  salvarPosicaoRolagem();
  
  // Remove o estado ativo de todas as categorias
  document.querySelectorAll('.menu-category').forEach(cat => {
    cat.classList.remove('active');
  });
  
  const menuCategory = document.querySelector(`.menu-category:has(.menu-item[onclick="toggleSubmenu('${tipo}')"])`);
  const submenuEl = document.getElementById(`submenu${capitalizar(tipo)}`);
  
  if (!submenuEl) return;
  
  if (submenuEl.classList.contains('hidden')) {
    // Fecha outros submenus
    document.querySelectorAll('.submenu').forEach(sub => {
      if (sub.id !== `submenu${capitalizar(tipo)}`) {
        sub.classList.add('hidden');
        sub.style.maxHeight = '0';
      }
    });
    
    // Abre o submenu selecionado
    submenuEl.classList.remove('hidden');
    submenuEl.style.maxHeight = submenuEl.scrollHeight + 'px';
    
    if (!submenuEl.hasChildNodes()) {
      populateSubmenu(tipo);
    }
    
    // Marca categoria como ativa
    if (menuCategory) menuCategory.classList.add('active');
  } else {
    submenuEl.style.maxHeight = '0';
    setTimeout(() => {
      submenuEl.classList.add('hidden');
    }, 300);
  }
}

function populateSubmenu(tipo) {
  let brandTypeText;
  switch (tipo) {
    case 'importadas':  brandTypeText = 'Marcas Importadas';  break;
    case 'premium':     brandTypeText = 'Marcas Premium';     break;
    case 'nacionais':   brandTypeText = 'Marcas Nacionais';   break;
    case 'diversos':    brandTypeText = 'Diversos';           break;
    default:            brandTypeText = 'Diversos';
  }
  const subEl = document.getElementById(`submenu${capitalizar(tipo)}`);
  if (!subEl) return;
  subEl.innerHTML = '';
  Object.keys(catalogo).forEach(slug => {
    const bd = catalogo[slug];
    if (bd.brand_type === brandTypeText) {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = '#';
      a.innerHTML = `<i class="fa-solid fa-angle-right submenu-icon"></i><span>${bd.nome || 'Marca Sem Nome'}</span>`;
      a.onclick = e => {
        e.preventDefault();
        fecharMenu();
        exibirMarca(slug);
      };
      li.appendChild(a);
      subEl.appendChild(li);
    }
  });
}

function capitalizar(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function abrirFarmarcia() {
  fecharMenu();
  mostrarSecao('paginaInicial');
  // Aqui você pode chamar a função filtrarCategoria('Farmacia') se ela existir
  setTimeout(() => {
    // Vamos buscar e clicar no card da farmácia
    const cardFarmacia = document.querySelector('.highlight-card[onclick*="filtrarCategoria(\'Farmacia\')"]');
    if (cardFarmacia) {
      cardFarmacia.click();
    } else {
      const div = document.getElementById('containerDiversos');
      if (div) {
        div.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  }, 150);
}

function abrirCBD() {
  fecharMenu();
  mostrarSecao('paginaInicial');
  setTimeout(() => {
    const div = document.getElementById('containerDiversos');
    if (div) {
      div.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }, 150);
}

function abrirFretes() {
  fecharMenu();
  window.location.href = 'fretes.html';
}

function abrirFAQ() {
  fecharMenu();
  mostrarToast("Em desenvolvimento", "A página de FAQ estará disponível em breve.", "info");
}

/********************************************************
 * ATUALIZAR HEADER USUÁRIO
 ********************************************************/
async function atualizarHeaderUsuario() {
  const userAreaEl = document.getElementById('userArea');
  if (!userAreaEl) return;
  const logado = await checarLogin();
  
  if (logado) {
    userAreaEl.innerHTML = `
      <a href="#" class="header-action-button" onclick="exibirPerfil(); return false;">
        <div class="action-icon-wrapper">
          <i class="fa-solid fa-user"></i>
        </div>
        <span class="action-text">Meu Perfil</span>
      </a>
    `;
  } else {
    userAreaEl.innerHTML = `
      <a href="login.php" class="header-action-button">
        <div class="action-icon-wrapper">
          <i class="fa-solid fa-right-to-bracket"></i>
        </div>
        <span class="action-text">Entrar</span>
      </a>
    `;
  }
}

/********************************************************
 * EXIBIR PERFIL
 ********************************************************/
async function exibirPerfil() {
  const estaLogado = await checarLogin();
  if (!estaLogado) {
    mostrarToast("Login necessário", "Você precisa estar logado para ver seu perfil.", "info");
    // Salvar referência para redirecionar de volta ao perfil após login
    sessionStorage.setItem('redirectAfterLogin', 'perfil'); 
    setTimeout(() => {
      window.location.href = 'login.php';
    }, 1500);
    return;
  }
  
  mostrarLoading(true, "Carregando seu perfil...");
  
  try {
    const resp = await fetch('getPerfil.php');
    if (!resp.ok) {
      throw new Error(`Erro ${resp.status}: ${resp.statusText}`);
    }
    
    const data = await resp.json();
    if (!data.sucesso) {
      mostrarToast("Erro ao carregar perfil", data.mensagem || "Ocorreu um erro inesperado.", "error");
      mostrarLoading(false);
      return;
    }
    
    let html = `
      <div class="perfil-card">
        <p><strong>Nome:</strong> ${data.nome}</p>
        <p><strong>E-mail:</strong> ${data.email}</p>
        <p><strong>Pontos:</strong> <span class="pontos-destaque">${data.points}</span></p>
    `;
    
    if (data.ultimo_pedido && data.ultimo_pedido.admin_comments) {
      html += `
        <hr style="margin:0.75rem 0;" />
        <div class="admin-comments-destaque">
          <strong>Mensagem do Admin:</strong>
          <p style="margin-top:4px;">${data.ultimo_pedido.admin_comments}</p>
        </div>
      `;
    }
    html += `</div>`;

    // Último pedido
    if (data.ultimo_pedido) {
      const up = data.ultimo_pedido;
      const statusBadge = gerarStatusBadge(up.status);
      html += `
        <h3 class="secao-subtitulo">Último Pedido</h3>
        <div class="pedido-card">
          <p>
            <strong>Pedido #${up.id}</strong> — R$ ${up.final_value || '0,00'} ${statusBadge}<br/>
            <small>Criado em: ${up.created_at}</small>
          </p>
        </div>
      `;
    }

    html += `<h3 class="secao-subtitulo">Histórico de Pedidos</h3>`;
    if (data.pedidos && data.pedidos.length > 0) {
      data.pedidos.forEach(p => {
        html += renderizarPedido(p);
      });
    } else {
      html += `<p>Nenhum pedido encontrado.</p>`;
    }
    
    const perfilConteudo = document.getElementById('perfilConteudo');
    if (perfilConteudo) {
      perfilConteudo.innerHTML = html;
    }
    
    mostrarSecao('paginaPerfil');
    mostrarLoading(false);
  } catch (err) {
    console.error(err);
    mostrarToast("Erro de comunicação", "Não foi possível buscar seu perfil. Tente novamente.", "error");
    mostrarLoading(false);
  }
}

/********************************************************
 * FUNÇÃO DE LOGOUT
 ********************************************************/
function fazerLogout() {
  // Limpar estado de checkout ao fazer logout
  limparEstadoCheckout();
  
  mostrarConfirmacao(
    "Confirmar logout", 
    "Tem certeza que deseja sair da sua conta?",
    function() {
      mostrarLoading(true, "Saindo...");
      setTimeout(() => {
        window.location.href = 'logout.php';
      }, 500);
    }
  );
}

/********************************************************
 * FUNÇÕES DE PERFIL (STATUS, PEDIDOS)
 ********************************************************/
function gerarStatusBadge(status) {
  const rot = status.toUpperCase();
  let classe = '';
  switch (rot) {
    case 'PENDENTE':   classe = 'status-pendente';  break;
    case 'PAGO':       classe = 'status-pago';      break;
    case 'ENVIADO':
    case 'EM TRÂNSITO': classe = 'status-envio';     break;
    case 'CANCELADO':  classe = 'status-cancelado'; break;
    default:           classe = 'status-outro';
  }
  return `<span class="status-label ${classe}">${rot}</span>`;
}

function renderizarPedido(p) {
  const orderId = p.id;
  const finalValue = p.final_value || '0,00';
  const shippingValue = p.shipping_value || '0,00';
  const discountValue = p.discount_value || '0,00';
  const insuranceValue = p.insurance_value || '0,00';
  const total = p.total || '0,00';
  const pointsEarned = p.points_earned || 0;
  const statusBadge = gerarStatusBadge(p.status || 'PENDENTE');
  const adminComments = p.admin_comments || '';

  let html = `
    <div class="pedido-card">
      <p class="pedido-resumo">
        <strong>Pedido #${orderId}</strong> — R$ ${finalValue} ${statusBadge}<br/>
        <small>Criado em: ${p.created_at}</small>
      </p>
      <button class="btn-ver-detalhes" onclick="toggleDetalhesPedido(${orderId})">
        <i class="fa-solid fa-eye"></i> <span>Ver Detalhes</span>
      </button>
      <div id="detalhes-pedido-${orderId}" class="detalhes-pedido">
        <p><strong>Frete:</strong> R$ ${shippingValue}</p>
        <p><strong>Desconto:</strong> R$ ${discountValue}</p>
        <p><strong>Seguro:</strong> R$ ${insuranceValue}</p>
        <hr class="linha-divisoria"/>
        <p><strong>Total:</strong> R$ ${total}</p>
        <p><strong>Valor Final:</strong> R$ ${finalValue}</p>
        <p><strong>Pontos Ganhos:</strong> ${pointsEarned}</p>
  `;
  if (p.items && Array.isArray(p.items) && p.items.length > 0) {
    html += renderizarItensDoPedido(p.items);
  } else {
    html += `<p style="color:#555;">(Nenhum item detalhado)</p>`;
  }
  if (adminComments) {
    html += `
      <hr class="linha-divisoria"/>
      <div class="admin-comments">
        <strong>Observações do Administrador:</strong><br/>
        <div style="white-space: pre-wrap; margin-top:4px;">${adminComments}</div>
      </div>
    `;
  }
  const msgCompleta = gerarMensagemVendedor(p);
  html += `
      <hr class="linha-divisoria"/>
      <p><strong>Detalhes do Pedido (Completo):</strong></p>
      <div class="mensagem-completa">
        <pre>${msgCompleta}</pre>
      </div>
    </div>
  </div>
  `;
  return html;
}

function renderizarItensDoPedido(itens) {
  let html = `<h4 class="titulo-itens">Itens do Pedido:</h4>`;
  itens.forEach(it => {
    html += `
      <div class="item-pedido-card">
        <p>
          <strong>${it.product_name}</strong> 
          ${it.brand ? `(${it.brand})` : ''}<br/>
          Qtd: ${it.quantity} - Subtotal: R$ ${formatarBRL(it.subtotal)}
        </p>
      </div>
    `;
  });
  return html;
}

function toggleDetalhesPedido(orderId) {
  // Salvar posição antes de alternar detalhes
  salvarPosicaoRolagem();
  
  const div = document.getElementById('detalhes-pedido-' + orderId);
  if (!div) return;
  
  if (!div.style.display || div.style.display === 'none') {
    div.style.display = 'block';
  } else {
    div.style.display = 'none';
  }
  
  // Restaurar posição depois de alternar
  setTimeout(restaurarPosicaoRolagem, 10);
}

// Função para exibir a chave PIX
function exibirChavePixOculta() {
  // Salvar posição antes de mostrar o bloco
  salvarPosicaoRolagem();
  
  const bloco = document.getElementById('blocoChavePix');
  if (bloco) {
    bloco.classList.remove('hidden'); // Remove a classe que esconde o bloco
  }
  
  // Não rolamos a página ao mostrar o bloco
  setTimeout(restaurarPosicaoRolagem, 10);
}

// Função para ocultar a chave PIX
function ocultarChavePix() {
  const bloco = document.getElementById('blocoChavePix');
  if (bloco) {
    bloco.classList.add('hidden'); // Adiciona a classe para esconder o bloco
  }
}

// Função para copiar a chave PIX para a área de transferência
function copiarPixCola() {
  const pixTextElem = document.getElementById('pixCopiaCola');
  if (pixTextElem) {
    const text = pixTextElem.textContent;
    navigator.clipboard.writeText(text)
      .then(() => {
        mostrarToast("Chave PIX copiada", "A chave PIX foi copiada para sua área de transferência.", "success");
      })
      .catch(err => {
        mostrarToast("Erro ao copiar", "Não foi possível copiar a chave PIX: " + err, "error");
      });
  }
}

function gerarMensagemVendedor(p) {
  const dataC = p.created_at || '';
  const dObj = dataC ? new Date(dataC) : new Date();
  const ano = dObj.getFullYear();
  const mes = String(dObj.getMonth() + 1).padStart(2, '0');
  const dia = String(dObj.getDate()).padStart(2, '0');
  const yyyymmdd = `${ano}${mes}${dia}`;
  const idZeropad = String(p.id).padStart(4, '0');
  const pref = 'M';
  const pedidoCod = `${pref}#${yyyymmdd}-${idZeropad}`;

  let txt = `=== PEDIDO ${pedidoCod} ===\n`;
  txt += `Data/Hora: ${dataC}\n\n`;
  txt += `=== DADOS DO CLIENTE ===\n`;
  txt += `Nome: ${p.customer_name || 'Desconhecido'}\n`;
  txt += `CPF: ${p.cpf || '--'}\n`;
  txt += `Telefone: ${p.phone || '--'}\n`;
  txt += `E-mail: ${p.email || '--'}\n`;
  txt += `Endereço: ${p.address || ''}, Nº ${p.number || ''}\n`;
  txt += `Complemento: ${p.complement || ''}\n`;
  txt += `Bairro: ${p.neighborhood || ''}\n`;
  txt += `Cidade/Estado: ${p.city || ''}/${p.state || ''}\n`;
  txt += `CEP: ${p.cep || ''}\n\n`;
  txt += `=== ITENS DO PEDIDO ===\n`;

  let totalT = 0;
  if (p.items && Array.isArray(p.items)) {
    p.items.forEach(it => {
      const sub = it.subtotal ? Number(it.subtotal) : 0;
      totalT += sub;
      txt += `${it.quantity}x ${it.product_name} (${it.brand || ''}) ${sub.toFixed(2).replace('.', ',')}\n`;
    });
  }

  txt += `\n=== RESUMO FINANCEIRO ===\n`;
  txt += `Subtotal Itens: ${totalT.toFixed(2).replace('.', ',')}\n`;
  
  const fr = Number(p.shipping_value || 0).toFixed(2).replace('.', ',');
  const ds = Number(p.discount_value || 0).toFixed(2).replace('.', ',');
  txt += `Frete: ${fr}\n`;
  if (ds !== '0,00') {
    txt += `Desconto: ${ds}\n`;
  }
  
  // Exibe a linha do seguro somente se o valor for maior que zero
  if (Number(p.insurance_value) > 0) {
    txt += `Seguro (opcional) contratado: R$ ${Number(p.insurance_value).toFixed(2).replace('.', ',')}\n`;
    txt += `*Este seguro cobre retenção/apreensão, extravio, roubo e danos durante o transporte.*\n`;
  }
  
  const tf = Number(p.final_value || 0).toFixed(2).replace('.', ',');
  txt += `*Total Final: ${tf}*\n\n`;
  txt += `Status: ${p.status || '---'}\n`;
  
  return txt;
}

/********************************************************
 * MODAL DE COMBOS
 ********************************************************/
const modalCombo= document.getElementById('modalCombo');
const comboItemsContainer= document.getElementById('comboItemsContainer');
let comboProdutoTemp=null;
let comboQuantidadeTemp=1;
let comboMarcaSlugTemp=null;
let comboPrecoTemp=0;


function abrirModalCombo(produto, quantidade, brandSlug, precoBase) {
  comboProdutoTemp = produto;
  comboQuantidadeTemp = quantidade;
  comboMarcaSlugTemp = brandSlug;
  comboPrecoTemp = precoBase;

  if (!comboItemsContainer) return;
  comboItemsContainer.innerHTML = '';
  
  comboOptionsExample.forEach(opt => {
    const chId = `comboItemOpt-${opt.id}`;
    const row = document.createElement('div');
    row.className = 'combo-item';
    row.innerHTML = `
      <label class="checkbox-seguro">
        <input type="checkbox" id="${chId}" value="${opt.id}">
        <span class="checkbox-custom"></span>
        <span class="checkbox-text">${opt.nome}</span>
      </label>
    `;
    comboItemsContainer.appendChild(row);
  });
  
  modalCombo?.classList.remove('hidden');
}

function fecharModalCombo() {
  modalCombo?.classList.add('hidden');
  comboProdutoTemp = null;
  comboQuantidadeTemp = 1;
  comboMarcaSlugTemp = null;
  comboPrecoTemp = 0;
  restaurarPosicaoRolagem();
}

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btnConfirmarCombo')?.addEventListener('click', () => {
    if (!comboProdutoTemp) {
      fecharModalCombo();
      return;
    }
    
    // Salvar posição antes de processar o combo
    salvarPosicaoRolagem();
    
    const marcados = comboItemsContainer.querySelectorAll('input[type="checkbox"]:checked');
    const comboItems = [];
    
    marcados.forEach(ch => {
      const fid = Number(ch.value);
      const foundOpt = comboOptionsExample.find(o => o.id === fid);
      if (foundOpt) {
        comboItems.push({ id: foundOpt.id, nome: foundOpt.nome });
      }
    });
    
    const reqSep = (catalogo[comboMarcaSlugTemp]?.separate_shipping === 1);
    const existente = carrinho.find(it => it.id === comboProdutoTemp.id);

    if (existente) {
      existente.quantidade += comboQuantidadeTemp;
      existente.comboItems = comboItems;
      if (reqSep) {
        existente.requiresSeparateShipping = true;
        existente.brandSlug = comboMarcaSlugTemp;
      }
    } else {
      carrinho.push({
        id: comboProdutoTemp.id,
        nome: comboProdutoTemp.nome,
        descricao: comboProdutoTemp.descricao,
        preco: comboPrecoTemp,
        marca: catalogo[comboMarcaSlugTemp]?.nome || comboMarcaSlugTemp,
        quantidade: comboQuantidadeTemp,
        imagem: comboProdutoTemp.imagem || '',
        requiresSeparateShipping: reqSep,
        brandSlug: comboMarcaSlugTemp,
        comboItems
      });
    }
    
    salvarCarrinhoLS();
    atualizarContagemCarrinho();
    fecharModalCombo();

    const ns = getCarrinhoSubtotal();
    const modalCartSub = document.getElementById('modalCartSubtotal');
    if (modalCartSub) {
      modalCartSub.querySelector('span').textContent = formatarBRL(ns);
    }
    
    const cartImgDiv = document.getElementById('modalCartImage');
    if (cartImgDiv) {
      cartImgDiv.innerHTML = '';
      if (comboProdutoTemp.imagem) {
        const mg = document.createElement('img');
        mg.src = comboProdutoTemp.imagem;
        mg.style.width = '100%';
        mg.style.height = '100%';
        mg.style.objectFit = 'cover';
        cartImgDiv.appendChild(mg);
      } else {
        cartImgDiv.innerHTML = `
          <img 
            src="https://via.placeholder.com/90?text=SEM+IMG"
            style="width:100%;height:100%;object-fit:cover;"
          />
        `;
      }
    }
    
    exibirModalCarrinho(`${comboProdutoTemp.nome} (Combo) adicionado ao carrinho!`);
    mostrarToast("Combo adicionado", "Combo adicionado com sucesso ao carrinho", "success");
  });
});

// Função para filtrar por categoria - implementação básica
function filtrarCategoria(categoria) {
  mostrarToast("Filtrando produtos", `Mostrando produtos da categoria ${categoria}`, "info");
  // Buscar marcas que contêm a categoria selecionada
  const marcasComCategoria = Object.keys(catalogo).filter(marcaSlug => {
    const marca = catalogo[marcaSlug];
    return marca.categorias && Object.keys(marca.categorias).some(cat => 
      cat.toLowerCase().includes(categoria.toLowerCase())
    );
  });
  
  if (marcasComCategoria.length > 0) {
    // Se encontrou marca(s), exibir a primeira que contém a categoria
    exibirMarca(marcasComCategoria[0]);
  } else {
    mostrarToast("Categoria não encontrada", "Não encontramos produtos nesta categoria.", "error");
  }
}

/********************************************************
 * ONLOAD (Inicialização Geral)
 ********************************************************/
window.addEventListener('load', async() => {
  // Carrega catálogo
  const catalogoCarregado = await carregarCatalogoDoBD();

  // Carrega carrinho e dados do cliente do LocalStorage
  carregarCarrinhoLS();
  restaurarDadosClienteLS();

  // Monta listas de marcas + Atualiza contagem do carrinho
  if (catalogoCarregado) {
    montarListasMarcas();
  }
  atualizarContagemCarrinho();

  const urlParams = new URLSearchParams(window.location.search);
  const secaoParam = urlParams.get('secao') || '';
  const nextParam = urlParams.get('next') || '';

  // Máscaras IMask (CPF, CEL)
  try {
    IMask(document.getElementById('cpfCliente'), { mask: '000.000.000-00' });
    IMask(document.getElementById('celCliente'), { mask: '(00) 00000-0000' });
    IMask(document.getElementById('cepCliente'), { mask: '00000-000' });
  } catch (e) { /* Se não tiver campos, ignore */ }

  // Se já houver CEP salvo
  const cepField = document.getElementById('cepCliente');
  if (cepField) {
    const cepSalvo = cepField.value.replace(/\D/g, '');
    if (cepSalvo.length === 8) {
      obterEstadoPeloCEP();
    }
  }

  // Decide qual seção exibir
  const hash = window.location.hash;
  if (secaoParam) {
    mostrarSecao(secaoParam);
  } else if (hash) {
    mostrarSecao(hash.replace('#', ''));
  } else {
    mostrarSecao('paginaInicial');
  }

  // Configura observer para remover alertas quando o feedback de frete aparece
  const freteFeedback = document.getElementById('freteFeedback');
  if (freteFeedback) {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.attributeName === 'style' && 
            window.getComputedStyle(freteFeedback).display !== 'none') {
          removerAlertaFormulario();
        }
      });
    });
    observer.observe(freteFeedback, { attributes: true });
  }

  // Verifica se há um redirecionamento após login
  const redirectAfterLogin = sessionStorage.getItem('redirectAfterLogin');
  if (redirectAfterLogin === 'perfil') {
    sessionStorage.removeItem('redirectAfterLogin');
    exibirPerfil();
  }

  // Atualiza área do usuário (header)
  await atualizarHeaderUsuario();
  
  // Configura input focus sem rolagem automática
  configureInputFocusZoom();
});