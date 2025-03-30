<?php
// inc/config.php - VERSÃO CORRIGIDA SEM VERIFICAÇÃO DE LOGIN
session_start();
date_default_timezone_set('America/Asuncion');

// Configurações do Banco de Dados
$dbHost = 'localhost';
$dbName = 'imperiopharma_loja_db';
$dbUser = 'imperiopharma_loja_user';
$dbPass = 'Miguel22446688';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone='-04:00'"); // Ajustado para Paraguay
} catch (Exception $e) {
    die("Erro ao conectar ao BD: " . $e->getMessage());
}

// Funções úteis
function setFlashMessage($message, $type = 'info', $title = 'Aviso') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_title'] = $title;
}

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// REMOVI A VERIFICAÇÃO DE LOGIN QUE CAUSAVA O REDIRECIONAMENTO
?>