// assets/js/anomaly-detector.js
class AnomalyDetector {
  constructor(options = {}) {
    this.options = {
      threshold: 2.0,         // Desvios padrão para considerar anomalia
      minSamples: 7,         // Mínimo de amostras para análise
      sensitivityLevel: 0.5,  // 0.0 a 1.0 (maior = mais sensível)
      ...options
    };
  }
  
  // Detecta anomalias usando o método Z-score
  detectWithZScore(data, dateField = 'date', valueField = 'value') {
    if (!data || data.length < this.options.minSamples) {
      return { hasAnomalies: false, anomalies: [], message: 'Dados insuficientes' };
    }
    
    // Extrai valores para análise
    const values = data.map(item => parseFloat(item[valueField]));
    
    // Calcula média
    const mean = values.reduce((sum, val) => sum + val, 0) / values.length;
    
    // Calcula desvio padrão
    const squareDiffs = values.map(value => Math.pow(value - mean, 2));
    const avgSquareDiff = squareDiffs.reduce((sum, val) => sum + val, 0) / squareDiffs.length;
    const stdDev = Math.sqrt(avgSquareDiff);
    
    // Ajusta limiar com base na sensibilidade
    const adjustedThreshold = this.options.threshold * (2 - this.options.sensitivityLevel);
    
    // Detecta anomalias
    const anomalies = [];
    data.forEach((item, index) => {
      const value = parseFloat(item[valueField]);
      const zScore = stdDev === 0 ? 0 : Math.abs((value - mean) / stdDev);
      
      if (zScore > adjustedThreshold) {
        anomalies.push({
          date: item[dateField],
          value,
          zScore,
          direction: value > mean ? 'acima' : 'abaixo',
          percentDiff: ((value - mean) / mean) * 100
        });
      }
    });
    
    return {
      hasAnomalies: anomalies.length > 0,
      anomalies,
      stats: {
        mean,
        stdDev,
        threshold: adjustedThreshold
      },
      message: anomalies.length > 0 
        ? `Detectadas ${anomalies.length} anomalias` 
        : 'Nenhuma anomalia detectada'
    };
  }
  
  // Detecta tendências usando regressão linear simples
  detectTrend(data, dateField = 'date', valueField = 'value') {
    if (!data || data.length < this.options.minSamples) {
      return { hasTrend: false, trend: 0, message: 'Dados insuficientes' };
    }
    
    // Prepara dados para regressão
    const xValues = data.map((_, i) => i); // Índices como x
    const yValues = data.map(item => parseFloat(item[valueField]));
    
    // Calcula médias
    const xMean = xValues.reduce((sum, x) => sum + x, 0) / xValues.length;
    const yMean = yValues.reduce((sum, y) => sum + y, 0) / yValues.length;
    
    // Calcula coeficientes para y = mx + b
    let numerator = 0;
    let denominator = 0;
    
    for (let i = 0; i < xValues.length; i++) {
      numerator += (xValues[i] - xMean) * (yValues[i] - yMean);
      denominator += Math.pow(xValues[i] - xMean, 2);
    }
    
    // Previne divisão por zero
    const slope = denominator === 0 ? 0 : numerator / denominator;
    const intercept = yMean - slope * xMean;
    
    // Calcula R²
    let ssRes = 0;
    let ssTot = 0;
    
    for (let i = 0; i < xValues.length; i++) {
      const predicted = slope * xValues[i] + intercept;
      ssRes += Math.pow(yValues[i] - predicted, 2);
      ssTot += Math.pow(yValues[i] - yMean, 2);
    }
    
    const rSquared = ssTot === 0 ? 0 : 1 - (ssRes / ssTot);
    
    // Determina se há uma tendência significativa
    const hasTrend = Math.abs(rSquared) >= 0.5;
    
    // Calcula a tendência em termos de % de mudança
    const firstValue = yValues[0];
    const lastValue = yValues[yValues.length - 1];
    const percentChange = firstValue === 0 ? 0 : ((lastValue - firstValue) / firstValue) * 100;
    
    return {
      hasTrend,
      trend: slope,
      percentChange,
      direction: slope > 0 ? 'crescente' : slope < 0 ? 'decrescente' : 'estável',
      confidence: rSquared,
      equation: {
        slope,
        intercept,
        formula: `y = ${slope.toFixed(4)}x + ${intercept.toFixed(4)}`
      },
      message: hasTrend 
        ? `Tendência ${slope > 0 ? 'crescente' : 'decrescente'} detectada (${Math.abs(percentChange).toFixed(1)}%)` 
        : 'Nenhuma tendência significativa'
    };
  }
  
  // Detecta sazonalidade usando autocorrelação
  detectSeasonality(data, dateField = 'date', valueField = 'value', maxLag = 30) {
    if (!data || data.length < maxLag * 2) {
      return { hasSeasonality: false, period: 0, message: 'Dados insuficientes' };
    }
    
    const values = data.map(item => parseFloat(item[valueField]));
    const n = values.length;
    
    // Calcula média
    const mean = values.reduce((sum, val) => sum + val, 0) / n;
    
    // Calcula autocorrelação para diferentes lags
    const autocorr = [];
    for (let lag = 1; lag < Math.min(maxLag, Math.floor(n / 2)); lag++) {
      let numerator = 0;
      let denominator = 0;
      
      for (let i = 0; i < n - lag; i++) {
        numerator += (values[i] - mean) * (values[i + lag] - mean);
      }
      
      for (let i = 0; i < n; i++) {
        denominator += Math.pow(values[i] - mean, 2);
      }
      
      const correlation = denominator === 0 ? 0 : numerator / denominator;
      autocorr.push({ lag, correlation });
    }
    
    // Encontra picos de correlação (potenciais períodos)
    const peaks = [];
    for (let i = 1; i < autocorr.length - 1; i++) {
      if (autocorr[i].correlation > autocorr[i - 1].correlation && 
          autocorr[i].correlation > autocorr[i + 1].correlation &&
          autocorr[i].correlation > 0.3) { // Limiar para considerar relevante
        peaks.push(autocorr[i]);
      }
    }
    
    // Ordena picos por correlação
    peaks.sort((a, b) => b.correlation - a.correlation);
    
    // Retorna o período mais provável
    const hasSeasonality = peaks.length > 0;
    const topPeriod = hasSeasonality ? peaks[0].lag : 0;
    
    return {
      hasSeasonality,
      period: topPeriod,
      correlation: hasSeasonality ? peaks[0].correlation : 0,
      peaks: peaks.slice(0, 3), // Top 3 períodos
      allCorrelations: autocorr,
      message: hasSeasonality 
        ? `Sazonalidade detectada com período provável de ${topPeriod} ${topPeriod === 1 ? 'dia' : 'dias'}` 
        : 'Nenhuma sazonalidade detectada'
    };
  }
}

// Exporta a classe para uso global
window.AnomalyDetector = AnomalyDetector;