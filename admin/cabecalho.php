<?php
/***************************************************************
 * cabecalho.php
 *
 * Contém o início do HTML (doctype, head, link para CSS),
 * o <header> com o título do painel e o <nav> (menu superior),
 * e abre a <div class="painel-container"> para o conteúdo.
 ***************************************************************/
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Painel Administrativo - Império Pharma</title>

  <!-- Linke seu CSS do painel -->
  <link rel="stylesheet" href="painel.css">
</head>
<body>

<!-- CABEÇALHO -->
<header class="painel-header">
  <h1>Painel Administrativo - Império Pharma</h1>
</header>

<!-- MENU SUPERIOR -->
<nav class="painel-nav">
  <a href="painel_pedidos.php">Pedidos</a>
  <a href="painel_produtos.php">Marcas & Produtos</a>
  <a href="painel_financeiro.php">Financeiro</a>
  <a href="painel_cupons.php">Cupons</a>
</nav>

<!-- Container principal -->
<div class="painel-container">
