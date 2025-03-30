<?php
session_start();

// Configurações de conexão com o BD
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
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Variável de erro (caso ocorram problemas)
$erro = "";

// nextParam (ex: ?next=checkout)
$nextParam = isset($_GET['next']) ? trim($_GET['next']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se vier no POST, atualiza
    $nextParam = $_POST['next'] ?? $nextParam;

    $nome  = trim($_POST['nome']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    // Validação simples
    if ($nome === '' || $email === '' || $senha === '') {
        $erro = "Preencha todos os campos obrigatórios!";
    } else {
        // Verificar se e-mail já existe
        $stmtCheck = $pdo->prepare("SELECT id FROM customers WHERE email = :email LIMIT 1");
        $stmtCheck->execute([':email' => $email]);

        if ($stmtCheck->rowCount() > 0) {
            $erro = "Este e-mail já está cadastrado!";
        } else {
            try {
                // Criptografar senha
                $hashSenha = password_hash($senha, PASSWORD_DEFAULT);

                // Inserir novo cliente
                $stmtIns = $pdo->prepare("
                    INSERT INTO customers (nome, email, senha_hash)
                    VALUES (:nome, :email, :senha_hash)
                ");
                $stmtIns->execute([
                    ':nome'       => $nome,
                    ':email'      => $email,
                    ':senha_hash' => $hashSenha
                ]);

                // Logar automaticamente
                $novoId = $pdo->lastInsertId();
                $_SESSION['customer_id']   = $novoId;
                $_SESSION['customer_nome'] = $nome;

                // Redirecionar conforme o 'next'
                if ($nextParam === 'checkout') {
                    header("Location: index.html#paginaClienteEnvio");
                } else {
                    header("Location: index.html");
                }
                exit;

            } catch (PDOException $e) {
                $erro = "Erro ao cadastrar. Tente novamente.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Cadastro de Cliente | Império Pharma</title>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      color: #333;
      background: #f9f9f9;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 500px;
      margin: 40px auto;
      background: #fff;
      padding: 20px;
      border-radius: 6px;
      box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }
    h1 {
      text-align: center;
      font-size: 1.5rem;
      color: #081c4b;
      margin-bottom: 10px;
    }
    p.subtitulo {
      text-align: center;
      font-size: 0.95rem;
      color: #666;
      margin-bottom: 20px;
    }
    .erro {
      color: #d00;
      margin-bottom: 10px;
      text-align: center;
      font-weight: bold;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #444;
    }
    .form-group input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }
    .btn-cadastro {
      width: 100%;
      padding: 12px;
      background: #d52b1e; /* vermelho para combinar com o resto da loja */
      color: #fff;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      border-radius: 4px;
      margin-top: 10px;
      transition: background 0.2s;
    }
    .btn-cadastro:hover {
      background: #b2221c;
    }
    .links {
      margin-top: 20px;
      text-align: center;
    }
    .links a {
      margin: 0 10px;
      text-decoration: none;
      color: #081c4b;
      font-weight: bold;
      font-size: 0.95rem;
    }
    .links a:hover {
      text-decoration: underline;
    }

    @media (max-width: 480px) {
      .container {
        margin: 15px;
        padding: 15px;
      }
      h1 {
        font-size: 1.2rem;
      }
      .btn-cadastro {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Criar Conta</h1>
    <p class="subtitulo">Preencha os dados para criar sua conta</p>

    <?php if ($erro): ?>
      <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post">
      <!-- Retém o parâmetro next se existir -->
      <input type="hidden" name="next" value="<?= htmlspecialchars($nextParam) ?>">

      <div class="form-group">
        <label for="nome">Nome Completo:</label>
        <input
          type="text"
          name="nome"
          id="nome"
          placeholder="Ex: João da Silva"
          required
        />
      </div>

      <div class="form-group">
        <label for="email">E-mail:</label>
        <input
          type="email"
          name="email"
          id="email"
          placeholder="seuemail@exemplo.com"
          required
        />
      </div>

      <div class="form-group">
        <label for="senha">Senha:</label>
        <input
          type="password"
          name="senha"
          id="senha"
          placeholder="Crie uma senha segura"
          required
        />
      </div>

      <button type="submit" class="btn-cadastro">Cadastrar</button>
    </form>

    <div class="links" style="margin-top:30px;">
      <a href="login.php<?php if($nextParam==='checkout') echo '?next=checkout'; ?>">
        Já tenho conta (Login)
      </a>
      <a href="index.html">Voltar à Loja</a>
    </div>
  </div>
</body>
</html>
