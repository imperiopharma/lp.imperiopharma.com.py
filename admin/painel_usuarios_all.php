<?php
// painel_usuarios_all.php
// Uma única página para:
// 1) Listar usuários (aba=lista)
// 2) Editar/criar usuário (aba=editar&id=X)
// 3) Ranking/marketing (aba=ranking)
// 4) Ações em massa (enviar cupons/mensagens) etc.

session_start(); // Se precisar

include 'cabecalho.php'; // Seu cabeçalho padrão do admin

/****************************************************
 * CONFIGURAÇÃO DO BD
 ****************************************************/
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

} catch (Exception $e) {
    die("Erro ao conectar: " . $e->getMessage());
}

// Definindo qual "aba" mostrar. "lista", "editar", "ranking", etc.
$aba = isset($_GET['aba']) ? trim($_GET['aba']) : 'lista'; 
// 'lista' será o padrão

$erro = "";
$sucesso = "";

/****************************************************
 * 1) ABAS ou MENU SUPERIOR
 ****************************************************/
?>
<h2>Painel de Usuários (Tudo em Um)</h2>
<nav style="margin-bottom:1rem;">
  <a href="?aba=lista"
     style="margin-right:12px; text-decoration:none; font-weight:bold; color:<?= ($aba==='lista'?'blue':'#333'); ?>;">
    [ Lista ]
  </a>
  <a href="?aba=editar"
     style="margin-right:12px; text-decoration:none; font-weight:bold; color:<?= ($aba==='editar'?'blue':'#333'); ?>;">
    [ Criar Novo Usuário ]
  </a>
  <a href="?aba=ranking"
     style="margin-right:12px; text-decoration:none; font-weight:bold; color:<?= ($aba==='ranking'?'blue':'#333'); ?>;">
    [ Ranking / Marketing ]
  </a>
</nav>

<?php
/****************************************************
 * 2) AÇÃO DE EXCLUIR (caso ?excluir=ID) — só vale se aba=lista
 ****************************************************/
if ($aba === 'lista' && isset($_GET['excluir']) && ctype_digit($_GET['excluir'])) {
    $userId = (int)$_GET['excluir'];

    // Excluir da tabela customers
    $stmtDel = $pdo->prepare("DELETE FROM customers WHERE id=? LIMIT 1");
    $stmtDel->execute([$userId]);

    // Redireciona para evitar reenvio
    header("Location: painel_usuarios_all.php?aba=lista");
    exit;
}

/****************************************************
 * 3) ABA = LISTA
 ****************************************************/
if ($aba === 'lista') {
    // Buscar usuários da tabela customers
    $sql = "SELECT * FROM customers ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <h3>Lista de Usuários</h3>
    <table border="1" cellpadding="6" cellspacing="0" style="width:100%; max-width:1000px;">
      <thead style="background:#f5f5f5;">
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Ativo?</th>
          <th>Pontos</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($usuarios)): ?>
          <tr>
            <td colspan="6" style="text-align:center;">Nenhum usuário cadastrado.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= htmlspecialchars($u['nome'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
              <td><?= ($u['active'] == 1 ? 'Sim' : 'Não') ?></td>
              <td><?= (int)($u['points'] ?? 0) ?></td>
              <td>
                <a href="?aba=editar&id=<?= $u['id'] ?>"
                   style="text-decoration:none; color:blue; margin-right:8px;">
                  Editar
                </a>
                <a href="?aba=lista&excluir=<?= $u['id'] ?>"
                   style="text-decoration:none; color:red;"
                   onclick="return confirm('Excluir este usuário?');">
                  Excluir
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <?php
} // fim aba=lista

/****************************************************
 * 4) ABA = EDITAR (criar ou editar usuário)
 ****************************************************/
elseif ($aba === 'editar') {
    // Verificar se é edição (se tem ?id=XXX)
    $userId = 0;
    if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
        $userId = (int)$_GET['id'];
    }

    // Processar POST (Salvar)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome   = trim($_POST['nome']   ?? '');
        $email  = trim($_POST['email']  ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $points = (int)($_POST['points'] ?? 0);
        $senha  = trim($_POST['senha']   ?? '');

        if ($nome === '' || $email === '') {
            $erro = "Nome e e-mail são obrigatórios.";
        } else {
            try {
                if ($userId > 0) {
                    // UPDATE
                    if ($senha !== '') {
                        $hash = password_hash($senha, PASSWORD_DEFAULT);
                        $sqlUp = "UPDATE customers
                                  SET nome=:n, email=:e, active=:a,
                                      points=:p, senha_hash=:sh
                                  WHERE id=:id";
                        $stmtUp = $pdo->prepare($sqlUp);
                        $stmtUp->execute([
                            ':n'  => $nome,
                            ':e'  => $email,
                            ':a'  => $active,
                            ':p'  => $points,
                            ':sh' => $hash,
                            ':id' => $userId
                        ]);
                    } else {
                        $sqlUp = "UPDATE customers
                                  SET nome=:n, email=:e, active=:a,
                                      points=:p
                                  WHERE id=:id";
                        $stmtUp = $pdo->prepare($sqlUp);
                        $stmtUp->execute([
                            ':n'  => $nome,
                            ':e'  => $email,
                            ':a'  => $active,
                            ':p'  => $points,
                            ':id' => $userId
                        ]);
                    }
                    $sucesso = "Usuário atualizado com sucesso!";
                } else {
                    // INSERT
                    $hash = '';
                    if ($senha !== '') {
                        $hash = password_hash($senha, PASSWORD_DEFAULT);
                    }
                    $sqlIns = "INSERT INTO customers
                      (nome, email, active, points, senha_hash)
                       VALUES (:n, :e, :a, :p, :sh)";
                    $stmtIns = $pdo->prepare($sqlIns);
                    $stmtIns->execute([
                        ':n' => $nome,
                        ':e' => $email,
                        ':a' => $active,
                        ':p' => $points,
                        ':sh'=> $hash
                    ]);
                    $userId = $pdo->lastInsertId();
                    $sucesso = "Usuário criado com sucesso!";
                }
            } catch (Exception $ex) {
                $erro = "Erro ao salvar: " . $ex->getMessage();
            }
        }
    }

    // Buscar dados do BD (se for edição)
    $user = [
      'id'=>0,
      'nome'=> '',
      'email'=> '',
      'active'=>1,
      'points'=>0,
      'senha_hash'=>''
    ];
    if ($userId > 0 && $erro==='') {
        $stmtU = $pdo->prepare("SELECT * FROM customers WHERE id=? LIMIT 1");
        $stmtU->execute([$userId]);
        $rowU = $stmtU->fetch(PDO::FETCH_ASSOC);
        if ($rowU) {
            $user = $rowU;
        } else {
            $erro = "Usuário #$userId não encontrado.";
        }
    }

    // Formulário:
    ?>
    <h3><?= ($userId>0 ? 'Editar Usuário' : 'Criar Novo Usuário') ?></h3>

    <?php if ($erro): ?>
      <div style="color:red; font-weight:bold; margin-bottom:8px;">
        <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
      <div style="color:green; font-weight:bold; margin-bottom:8px;">
        <?= htmlspecialchars($sucesso) ?>
      </div>
    <?php endif; ?>

    <form method="POST" style="max-width:500px;">
      <label for="nome" style="display:block; margin-top:10px; font-weight:bold;">
        Nome:
      </label>
      <input type="text" name="nome" id="nome"
             value="<?= htmlspecialchars($user['nome'] ?? '') ?>"
             style="width:100%; padding:6px;"
             required
      />

      <label for="email" style="display:block; margin-top:10px; font-weight:bold;">
        E-mail:
      </label>
      <input type="email" name="email" id="email"
             value="<?= htmlspecialchars($user['email'] ?? '') ?>"
             style="width:100%; padding:6px;"
             required
      />

      <label for="points" style="display:block; margin-top:10px; font-weight:bold;">
        Pontos:
      </label>
      <input type="number" name="points" id="points"
             value="<?= (int)($user['points'] ?? 0) ?>"
             style="width:100%; padding:6px;"
      />

      <div style="margin-top:10px;">
        <label style="font-weight:bold;">
          <input type="checkbox" name="active" value="1"
            <?= ($user['active'] == 1 ? 'checked' : '') ?>
          />
          Usuário Ativo?
        </label>
      </div>

      <label for="senha" style="display:block; margin-top:10px; font-weight:bold;">
        Senha (deixe em branco se não quiser alterar):
      </label>
      <input type="password" name="senha" id="senha"
             style="width:100%; padding:6px;"
      />

      <div style="margin-top:12px;">
        <button type="submit"
                style="
                  padding:8px 14px;
                  background:#28a745;
                  color:#fff;
                  border:none;
                  border-radius:4px;
                ">
          <?= ($userId>0 ? 'Salvar Alterações' : 'Criar Usuário') ?>
        </button>
        <a href="?aba=lista"
           style="text-decoration:none; font-weight:bold; color:#333; margin-left:12px;">
          Voltar
        </a>
      </div>
    </form>
    <?php
} // fim aba=editar

/****************************************************
 * 5) ABA = RANKING (Marketing)
 ****************************************************/
elseif ($aba === 'ranking') {

    // Possíveis ações em massa
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
        $listIDs = $_POST['cliente_ids'] ?? [];
        if (empty($listIDs)) {
            $erro = "Nenhum cliente selecionado.";
        } else {
            $clienteIds = array_map('intval', $listIDs);
            $acao = $_POST['acao'];

            if ($acao === 'enviar_mensagem') {
                // Exemplo: "enviar mensagem" (simples)
                $sucesso = "Mensagem enviada para " . count($clienteIds) . " clientes (exemplo).";

            } elseif ($acao === 'dar_cupom') {
                $cupomId = (int)($_POST['cupom_id'] ?? 0);
                if ($cupomId <= 0) {
                    $erro = "Cupom inválido.";
                } else {
                    // Insere na tabela "coupon_allowed_customers" (exemplo)
                    foreach ($clienteIds as $cid) {
                        $stmtCup = $pdo->prepare("INSERT IGNORE INTO coupon_allowed_customers (coupon_id, customer_id) VALUES (?, ?)");
                        $stmtCup->execute([$cupomId, $cid]);
                    }
                    $sucesso = "Cupom #$cupomId atribuído a " . count($clienteIds) . " clientes.";
                }
            }
        }
    }

    // Agora buscar dados (ranking)
    // Exemplo: totalSpent = soma de final_value, totalProfit = soma de final_value - cost_total
    $sql = "
    SELECT
      c.id,
      c.nome,
      c.email,
      c.points,
      c.active,
      (SELECT IFNULL(SUM(o.final_value),0)
         FROM orders o
         WHERE o.customer_id = c.id
      ) AS totalSpent,
      (SELECT IFNULL(SUM(o.final_value - o.cost_total),0)
         FROM orders o
         WHERE o.customer_id = c.id
      ) AS totalProfit
    FROM customers c
    ORDER BY totalSpent DESC
    ";
    $stmt = $pdo->query($sql);
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <h3>Ranking / Marketing de Clientes</h3>

    <?php if ($erro): ?>
      <div style="color:red; font-weight:bold; margin-bottom:8px;">
        <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
      <div style="color:green; font-weight:bold; margin-bottom:8px;">
        <?= htmlspecialchars($sucesso) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <table border="1" cellpadding="6" cellspacing="0" style="width:100%; max-width:1000px;">
        <thead style="background:#f5f5f5;">
          <tr>
            <th></th> <!-- Checkbox para seleção -->
            <th>ID</th>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Ativo?</th>
            <th>Pontos</th>
            <th>Total Gasto (R$)</th>
            <th>Lucro Gerado (R$)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($lista)): ?>
            <tr>
              <td colspan="8" style="text-align:center;">
                Nenhum cliente cadastrado.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($lista as $cli): ?>
              <tr>
                <td><input type="checkbox" name="cliente_ids[]" value="<?= $cli['id'] ?>" /></td>
                <td><?= $cli['id'] ?></td>
                <td><?= htmlspecialchars($cli['nome']) ?></td>
                <td><?= htmlspecialchars($cli['email']) ?></td>
                <td><?= ($cli['active']==1 ? 'Sim':'Não') ?></td>
                <td><?= (int)$cli['points'] ?></td>
                <td><?= number_format($cli['totalSpent'],2,',','.') ?></td>
                <td><?= number_format($cli['totalProfit'],2,',','.') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <div style="margin-top:1rem;">
        <label for="acao" style="font-weight:bold;">Ação em Massa:</label>
        <select name="acao" id="acao" style="margin-right:10px;">
          <option value="">-- Selecione --</option>
          <option value="enviar_mensagem">Enviar Mensagem (Ex.)</option>
          <option value="dar_cupom">Atribuir Cupom (Ex.)</option>
        </select>

        <!-- Se for dar cupom, pede ID do cupom -->
        <span id="cupomBox" style="display:none; margin-left:10px;">
          Cupom ID:
          <input type="number" name="cupom_id" style="width:80px;" />
        </span>

        <button type="submit"
                style="padding:8px 14px; background:#1e73be; color:#fff; border:none; border-radius:4px; margin-left:10px;">
          Executar
        </button>
      </div>
    </form>

    <script>
    // Se "dar_cupom" for escolhido, exibe o campo "cupom_id"
    document.getElementById('acao').addEventListener('change', function() {
      if (this.value === 'dar_cupom') {
        document.getElementById('cupomBox').style.display = 'inline-block';
      } else {
        document.getElementById('cupomBox').style.display = 'none';
      }
    });
    </script>
    <?php
} // fim aba=ranking

/****************************************************
 * 6) Se a aba não for reconhecida, exibir algo
 ****************************************************/
else {
    echo "<p>Ops, aba não reconhecida. Selecione no menu acima.</p>";
}

include 'rodape.php';
