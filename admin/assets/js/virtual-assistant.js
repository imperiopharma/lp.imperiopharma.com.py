// assets/js/virtual-assistant.js
document.addEventListener('DOMContentLoaded', function() {
  // Elementos do DOM
  const assistantButton = document.getElementById('assistantButton');
  const assistantModal = document.getElementById('assistantModal');
  const chatMessages = document.getElementById('chat-messages');
  const assistantForm = document.getElementById('assistant-form');
  const questionInput = document.getElementById('assistant-question');
  const suggestionButtons = document.querySelectorAll('.suggestion-btn');
  
  // Inicializa modal Bootstrap
  const modal = new bootstrap.Modal(assistantModal);
  
  // Abre o modal quando o botão é clicado
  assistantButton.addEventListener('click', function() {
    modal.show();
    setTimeout(() => questionInput.focus(), 500);
  });
  
  // Processa o envio do formulário
  assistantForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const question = questionInput.value.trim();
    if (question) {
      addUserMessage(question);
      generateResponse(question);
      questionInput.value = '';
    }
  });
  
  // Processa cliques nos botões de sugestão
  suggestionButtons.forEach(button => {
    button.addEventListener('click', function() {
      const question = this.getAttribute('data-question');
      questionInput.value = question;
      addUserMessage(question);
      generateResponse(question);
      questionInput.value = '';
    });
  });
  
  // Adiciona mensagem do usuário ao chat
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
  
  // Adiciona resposta do assistente ao chat
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
  
  // Gera uma resposta baseada na pergunta
  function generateResponse(question) {
    // Exibe indicador de digitação para simular processamento
    const loadingMessage = addAssistantMessage('', true);
    
    // Simula processamento da IA
    setTimeout(() => {
      loadingMessage.remove();
      
      // Sistema baseado em regras (pseudo-IA)
      const response = getResponseByKeywords(question.toLowerCase());
      addAssistantMessage(response);
    }, 1500);
  }
  
  // Sistema de respostas baseado em palavras-chave
  function getResponseByKeywords(question) {
    // Verifica vendas
    if (question.includes('venda') || question.includes('faturamento')) {
      const valor = (Math.random() * 1500 + 500).toFixed(2).replace('.', ',');
      const percentual = (Math.random() * 15 + 5).toFixed(1).replace('.', ',');
      
      return `As vendas estão progredindo bem! Hoje tivemos um aumento de ${percentual}% em relação à média do mês, totalizando R$ ${valor}. Recomendo verificar o dashboard para mais detalhes.`;
    }
    
    // Verifica pedidos
    if (question.includes('pedido') || question.includes('encomenda')) {
      const pendentes = Math.floor(Math.random() * 5) + 1;
      
      if (question.includes('pendente') || question.includes('aguardando')) {
        return `Existem ${pendentes} pedidos pendentes aguardando processamento. Recomendo verificá-los para manter um bom nível de serviço ao cliente.`;
      }
      
      const total = Math.floor(Math.random() * 20) + 10;
      return `No total, temos ${total} pedidos processados hoje. A taxa de conclusão está em 87%, o que é um excelente indicador de eficiência operacional.`;
    }
    
    // Verifica estoque
    if (question.includes('estoque') || question.includes('produto')) {
      const baixo = Math.floor(Math.random() * 5) + 1;
      return `Atualmente temos ${baixo} produtos com estoque baixo que precisam de atenção. O produto mais vendido no mês atual é "Vitamina C 1000mg".`;
    }
    
    // Verifica financeiro
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
});