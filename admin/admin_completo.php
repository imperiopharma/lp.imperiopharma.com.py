<?php
/**
 * admin_completo.php
 *
 * Página índice/listagem que mostra todos os arquivos do Painel,
 * com descrições e links para execução, agora **AGRUPADOS** entre
 * “Principais” e “Outros”. Em dispositivos móveis, a tabela
 * fica responsiva.
 */

// Se estiver em "/admin", provavelmente $baseDir = '' já funciona.
// Se precisar, pode alterar para "../admin/" ou outro caminho relativo:
$baseDir = '';

// Array de arquivos PRINCIPAIS
$arquivosPrincipais = [
    [
        'nome' => 'index.php',
        'descricao' => 'Página inicial (Dashboard principal): métricas, cupons etc.',
        'link' => 'index.php'
    ],
    [
        'nome' => 'painel_pedidos.php',
        'descricao' => 'Lista de pedidos (com filtros), link para detalhe de cada pedido.',
        'link' => 'painel_pedidos.php'
    ],
    [
        'nome' => 'painel_detalhe_pedido.php',
        'descricao' => 'Detalhes de um pedido (itens, custo, status, pontuação cliente).',
        'link' => 'painel_detalhe_pedido.php?id=1'
    ],
    [
        'nome' => 'painel_financeiro.php',
        'descricao' => 'Financeiro (movimentação, caixa diário, fechamentos e relatórios).',
        'link' => 'painel_financeiro.php'
    ],
    [
        'nome' => 'painel_cupons.php',
        'descricao' => 'Gerencia cupons/promoções (coupons, coupon_brands, coupon_categories).',
        'link' => 'painel_cupons.php'
    ],
    [
        'nome' => 'painel_caixa_diario.php',
        'descricao' => 'Mostra vendas do dia em tempo real + botão para fechar o dia.',
        'link' => 'painel_caixa_diario.php'
    ],
    [
        'nome' => 'painel.css',
        'descricao' => 'Folha de estilo principal do painel (cores, botões, formulários etc.).',
        'link' => 'painel.css'
    ],
];

// Array de OUTROS arquivos (também importantes, mas menos acessados no dia a dia)
$arquivosOutros = [
    [
        'nome' => 'admin_painel.php',
        'descricao' => 'Página “unificada” (métricas, links), usada como “menu” ou “painel central”.',
        'link' => 'admin_painel.php'
    ],
    [
        'nome' => 'ajax_movimentacao_diaria.php',
        'descricao' => 'Script AJAX para buscar pedidos do dia em tempo real (parcial).',
        'link' => 'ajax_movimentacao_diaria.php'
    ],
    [
        'nome' => 'dashboard.php',
        'descricao' => 'Painel inicial simples (cards com contadores e links).',
        'link' => 'dashboard.php'
    ],
    [
        'nome' => 'gerarFechamento.php',
        'descricao' => 'Script que consolida/gera fechamento diário ou data arbitrária.',
        'link' => 'gerarFechamento.php'
    ],
    [
        'nome' => 'historico_caixa.php',
        'descricao' => 'Exibe histórico de fechamentos (daily_closures).',
        'link' => 'historico_caixa.php'
    ],
    [
        'nome' => 'marcas.php',
        'descricao' => 'CRUD básico de marcas (inserir, editar, excluir).',
        'link' => 'marcas.php'
    ],
    [
        'nome' => 'marcas_e_produtos.php',
        'descricao' => 'Página unificada em abas para gerenciar Marcas & Produtos.',
        'link' => 'marcas_e_produtos.php'
    ],
    [
        'nome' => 'movimentacao_diaria.php',
        'descricao' => 'Mostra pedidos de uma data, receita, custo, lucro.',
        'link' => 'movimentacao_diaria.php'
    ],
    [
        'nome' => 'painel.php',
        'descricao' => 'Outra página de métricas gerais/diárias + links de atalho.',
        'link' => 'painel.php'
    ],
    [
        'nome' => 'painel_marca_detalhes.php',
        'descricao' => 'Exibe dados de uma marca e lista seus produtos.',
        'link' => 'painel_marca_detalhes.php?brand_id=1'
    ],
    [
        'nome' => 'painel_marcas_editar.php',
        'descricao' => 'Form para inserir/editar 1 marca (carregado por ?id=).',
        'link' => 'painel_marcas_editar.php'
    ],
    [
        'nome' => 'painel_marcas_list.php',
        'descricao' => 'Lista simples de marcas, com link p/ detalhes.',
        'link' => 'painel_marcas_list.php'
    ],
    [
        'nome' => 'painel_produtos.php',
        'descricao' => 'CRUD unificado de Marcas & Produtos, listagem por marca.',
        'link' => 'painel_produtos.php'
    ],
    [
        'nome' => 'painel_vendas.php',
        'descricao' => 'Lista daily_closings (fechamentos) com resumo de período.',
        'link' => 'painel_vendas.php'
    ],
    [
        'nome' => 'pedido_detalhe.php',
        'descricao' => 'Versão alternativa de “detalhe do pedido” (cálculo custo/lucro).',
        'link' => 'pedido_detalhe.php?id=1'
    ],
    [
        'nome' => 'produtos.php',
        'descricao' => 'CRUD de produtos (inserir/editar/excluir), sem agrupar por marca.',
        'link' => 'produtos.php'
    ],
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Admin Completo - Arquivos do Painel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    /* Reset Básico */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background: #f0f0f2;
      color: #333;
      max-width: 1000px;
      margin: 0 auto;
      padding: 1rem;
    }
    h1 {
      font-size: 1.4rem;
      margin-bottom: 1rem;
      padding-bottom: 0.4rem;
      border-bottom: 2px solid #ccc;
      color: #333;
    }
    h2 {
      font-size: 1.2rem;
      margin: 1.5rem 0 1rem 0;
      padding-bottom: 0.3rem;
      border-bottom: 1px solid #ccc;
      color: #444;
    }
    p {
      font-size: 0.95rem;
      margin-bottom: 1rem;
      line-height: 1.4;
    }

    /* Tabela Responsiva */
    .table-wrapper {
      width: 100%;
      overflow-x: auto;
      margin-top: 1rem;
      background: #fff;
      border-radius: 6px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 600px;
    }
    thead {
      background: #f7f7f7;
    }
    th, td {
      border: 1px solid #ddd;
      padding: 0.75rem 0.9rem;
      font-size: 0.9rem;
      text-align: left;
      vertical-align: top;
    }
    th {
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.5px;
      color: #555;
    }
    tbody tr:nth-child(even) {
      background: #fafafa;
    }

    .file-link {
      color: #007bff;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }
    .file-link:hover {
      color: #0056b3;
      text-decoration: underline;
    }

    .arquivo-nome {
      font-weight: bold;
      color: #333;
    }
    .arquivo-desc {
      color: #555;
      line-height: 1.3;
    }

    /* Responsividade extra */
    @media (max-width: 700px) {
      th, td {
        font-size: 0.85rem;
        padding: 0.6rem;
      }
      .arquivo-nome {
        font-size: 0.9rem;
      }
      .arquivo-desc {
        font-size: 0.85rem;
      }
      thead {
        display: none;
      }
      table, tbody, tr, td {
        display: block;
        width: 100%;
      }
      tr {
        margin-bottom: 1rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fff;
      }
      td {
        border: none;
        border-bottom: 1px solid #ddd;
        position: relative;
        padding-left: 50%;
      }
      td:last-child {
        border-bottom: none;
      }
      td:before {
        content: attr(data-label);
        position: absolute;
        left: 0.7rem;
        width: 45%;
        font-weight: 600;
        color: #333;
        text-transform: uppercase;
        font-size: 0.75rem;
      }
    }
  </style>
</head>
<body>

<h1>Admin Completo - Arquivos do Painel</h1>

<p>
  Aqui você encontra dois grupos de arquivos:  
  1) <strong>Principais</strong>, geralmente mais usados no dia a dia.  
  2) <strong>Outros</strong>, que complementam funcionalidades.
</p>

<!-- Seção: PRINCIPAIS -->
<h2>Principais</h2>
<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>Arquivo</th>
        <th>Descrição</th>
        <th>Abrir</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($arquivosPrincipais as $arq): ?>
      <tr>
        <td data-label="Arquivo">
          <span class="arquivo-nome">
            <?= htmlspecialchars($arq['nome']) ?>
          </span>
        </td>
        <td data-label="Descrição" class="arquivo-desc">
          <?= htmlspecialchars($arq['descricao']) ?>
        </td>
        <td data-label="Abrir">
          <a class="file-link"
             href="<?= $baseDir . $arq['link'] ?>"
             target="_blank"
             rel="noopener noreferrer">
            Executar
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Seção: OUTROS -->
<h2>Outros Arquivos</h2>
<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>Arquivo</th>
        <th>Descrição</th>
        <th>Abrir</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($arquivosOutros as $arq): ?>
      <tr>
        <td data-label="Arquivo">
          <span class="arquivo-nome">
            <?= htmlspecialchars($arq['nome']) ?>
          </span>
        </td>
        <td data-label="Descrição" class="arquivo-desc">
          <?= htmlspecialchars($arq['descricao']) ?>
        </td>
        <td data-label="Abrir">
          <a class="file-link"
             href="<?= $baseDir . $arq['link'] ?>"
             target="_blank"
             rel="noopener noreferrer">
            Executar
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</body>
</html>
