// assets/js/insight-engine.js
class InsightEngine {
  constructor() {
    this.dataPoints = {};
    this.anomalies = [];
    this.insights = [];
    this.thresholds = {
      salesIncrease: 15,     // % aumento em vendas para ser notável
      salesDecrease: -10,    // % queda em vendas para ser alarmante
      lowStock: 5,           // qtd. em estoque considerada baixa
      pendingOrders: 3,      // pedidos pendentes para destacar
      inactivityTime: 60000  // tempo de inatividade para sugestões (ms)
    };
  }

  // Carrega dados da API ou do sistema
  async loadData(endpoint, params = {}) {
    try {
      const queryString = new URLSearchParams(params).toString();
      const response = await fetch(`api/${endpoint}?${queryString}`);
      if (!response.ok) throw new Error('Falha ao carregar dados');
      
      const data = await response.json();
      this.dataPoints[endpoint] = data;
      return data;
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      return null;
    }
  }

  // Detecta anomalias nos dados
  detectAnomalies() {
    // Limpa anomalias anteriores
    this.anomalies = [];
    
    // Verifica vendas diárias
    if (this.dataPoints.sales) {
      const dailySales = this.dataPoints.sales;
      if (dailySales.length > 7) {
        // Calcula média móvel de 7 dias
        const recentAvg = dailySales.slice(-7).reduce((sum, day) => sum + day.total, 0) / 7;
        const previousAvg = dailySales.slice(-14, -7).reduce((sum, day) => sum + day.total, 0) / 7;
        
        // Calcula variação percentual
        const variation = ((recentAvg - previousAvg) / previousAvg) * 100;
        
        // Identifica anomalias baseadas nos limiares
        if (variation >= this.thresholds.salesIncrease) {
          this.anomalies.push({
            type: 'positive',
            icon: 'arrow-trend-up',
            title: 'Aumento significativo nas vendas',
            description: `As vendas aumentaram ${variation.toFixed(1)}% na última semana`,
            urgency: 'medium',
            actionLink: 'index.php?page=financeiro_completo'
          });
        } else if (variation <= this.thresholds.salesDecrease) {
          this.anomalies.push({
            type: 'negative',
            icon: 'arrow-trend-down',
            title: 'Queda nas vendas detectada',
            description: `As vendas caíram ${Math.abs(variation).toFixed(1)}% na última semana`,
            urgency: 'high',
            actionLink: 'index.php?page=financeiro_completo'
          });
        }
      }
    }
    
    // Verifica estoque baixo
    if (this.dataPoints.inventory) {
      const lowStockItems = this.dataPoints.inventory.filter(
        item => item.stock <= this.thresholds.lowStock && item.active
      );
      
      if (lowStockItems.length > 0) {
        this.anomalies.push({
          type: 'warning',
          icon: 'box-open',
          title: 'Produtos com estoque baixo',
          description: `${lowStockItems.length} produtos precisam de reposição`,
          urgency: 'medium',
          actionLink: 'index.php?page=marcas_produtos&filter=low_stock'
        });
      }
    }
    
    // Verifica pedidos pendentes
    if (this.dataPoints.orders) {
      const pendingOrders = this.dataPoints.orders.filter(
        order => order.status === 'PENDENTE'
      );
      
      if (pendingOrders.length >= this.thresholds.pendingOrders) {
        this.anomalies.push({
          type: 'warning',
          icon: 'clock',
          title: 'Pedidos aguardando processamento',
          description: `${pendingOrders.length} pedidos pendentes precisam de atenção`,
          urgency: 'high',
          actionLink: 'index.php?page=pedidos&status=PENDENTE'
        });
      }
    }
    
    return this.anomalies;
  }

  // Gera insights baseados nos dados
  generateInsights() {
    this.insights = [];
    
    // Se temos dados de vendas e produtos
    if (this.dataPoints.sales && this.dataPoints.products) {
      // Identifica produtos mais vendidos no período
      const topProducts = this.dataPoints.products
        .sort((a, b) => b.total_sold - a.total_sold)
        .slice(0, 5);
      
      if (topProducts.length > 0) {
        this.insights.push({
          icon: 'trophy',
          title: 'Produtos mais vendidos',
          description: `"${topProducts[0].name}" é seu produto mais vendido no período`,
          actionText: 'Ver ranking completo',
          actionLink: 'index.php?page=financeiro_completo&action=ranking'
        });
      }
      
      // Identifica dias da semana com melhores vendas
      const salesByDay = Array(7).fill(0);
      this.dataPoints.sales.forEach(sale => {
        const day = new Date(sale.date).getDay();
        salesByDay[day] += sale.total;
      });
      
      const bestDay = salesByDay.indexOf(Math.max(...salesByDay));
      const days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
      
      this.insights.push({
        icon: 'calendar-check',
        title: 'Melhor dia para vendas',
        description: `${days[bestDay]} é o dia com maior volume de vendas`,
        actionText: 'Analisar padrões',
        actionLink: 'index.php?page=financeiro_completo&action=by_day'
      });
    }
    
    return this.insights;
  }

  // Realiza previsões simples baseadas em tendências
  makePredictions() {
    const predictions = {};
    
    if (this.dataPoints.sales && this.dataPoints.sales.length >= 30) {
      // Pega os últimos 30 dias de vendas
      const last30Days = this.dataPoints.sales.slice(-30);
      
      // Calcula média diária
      const avgDaily = last30Days.reduce((sum, day) => sum + day.total, 0) / 30;
      
      // Calcula média dos últimos 7 dias
      const last7Days = last30Days.slice(-7);
      const avgLast7 = last7Days.reduce((sum, day) => sum + day.total, 0) / 7;
      
      // Calcula tendência (% aumento/diminuição)
      const trend = ((avgLast7 - avgDaily) / avgDaily) * 100;
      
      // Projeta para próximos 7 dias considerando a tendência
      predictions.nextWeek = avgLast7 * 7 * (1 + (trend / 100));
      
      // Projeta para o mês considerando dias restantes
      const today = new Date();
      const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
      const remainingDays = lastDayOfMonth - today.getDate();
      
      // Soma vendas do mês atual até hoje + previsão dos dias restantes
      const currentMonthSales = this.dataPoints.sales
        .filter(sale => {
          const saleDate = new Date(sale.date);
          return saleDate.getMonth() === today.getMonth() && 
                 saleDate.getFullYear() === today.getFullYear();
        })
        .reduce((sum, day) => sum + day.total, 0);
      
      predictions.monthEnd = currentMonthSales + (avgLast7 * remainingDays);
    }
    
    return predictions;
  }

  // Gera sugestões contextuais baseadas na página atual
  getContextualSuggestion(currentPage) {
    const suggestions = {
      dashboard: {
        title: 'Dica do Assistente',
        text: 'Para uma análise mais detalhada, experimente utilizar os filtros de período disponíveis acima dos gráficos.',
        icon: 'lightbulb'
      },
      pedidos: {
        title: 'Modo Foco',
        text: 'Ative o modo "Apenas Pendentes" para focar nos pedidos que precisam de processamento imediato.',
        icon: 'bullseye'
      },
      financeiro_completo: {
        title: 'Análise Sugerida',
        text: 'Compare os dados com o mês anterior para identificar tendências e padrões de vendas.',
        icon: 'chart-line'
      },
      marcas_produtos: {
        title: 'Otimização de Catálogo',
        text: 'Produtos sem movimentação por mais de 90 dias podem ser candidatos a promoções especiais.',
        icon: 'tags'
      }
    };
    
    return suggestions[currentPage] || {
      title: 'Assistente Virtual',
      text: 'Precisa de ajuda? Clique no ícone do robô para acessar o assistente virtual.',
      icon: 'robot'
    };
  }
}

// Exporta a classe para uso global
window.InsightEngine = InsightEngine;