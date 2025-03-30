<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Se salvou o ID do cliente na sessão como 'customer_id':
$logado = isset($_SESSION['customer_id']);
$nome   = $_SESSION['customer_nome'] ?? '';

echo json_encode([
  'logado' => $logado,
  'nome'   => $nome
], JSON_UNESCAPED_UNICODE);
