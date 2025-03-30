<?php
/**
 * Retorna a classe CSS apropriada com base no status do pedido
 * 
 * @param string $status O status do pedido
 * @return string A classe CSS correspondente
 */
function getStatusClass($status) {
    $status = strtoupper(trim($status));
    
    switch ($status) {
        case 'PENDENTE':
            return 'bg-warning text-dark';
        case 'CONFIRMADO':
            return 'bg-info text-white';
        case 'EM PROCESSO':
            return 'bg-primary text-white';
        case 'CONCLUIDO':
        case 'CONCLUÍDO':
            return 'bg-success text-white';
        case 'CANCELADO':
            return 'bg-danger text-white';
        default:
            return 'bg-secondary text-white';
    }
}

/**
 * Função auxiliar para obter ícone de status
 */
function getStatusIcon($status) {
    $status = strtoupper(trim($status));
    
    switch ($status) {
        case 'PENDENTE':
            return '<i class="fas fa-clock"></i>';
        case 'CONFIRMADO':
            return '<i class="fas fa-check-circle"></i>';
        case 'EM PROCESSO':
            return '<i class="fas fa-cog fa-spin"></i>';
        case 'CONCLUIDO':
        case 'CONCLUÍDO':
            return '<i class="fas fa-check-double"></i>';
        case 'CANCELADO':
            return '<i class="fas fa-times-circle"></i>';
        default:
            return '<i class="fas fa-question-circle"></i>';
    }
}