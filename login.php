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
    echo "Erro ao conectar ao banco: " . $e->getMessage();
    exit;
}

// Variável para exibir erro na tela (para login normal por email/senha)
$erro = "";
// Parâmetro next (ex.: ?next=checkout)
$nextParam = isset($_GET['next']) ? trim($_GET['next']) : '';

// Se enviar formulário (POST) -> Login Tradicional
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se houver 'next' enviado como campo hidden, sobrescreve
    $nextParam = isset($_POST['next']) ? trim($_POST['next']) : $nextParam;

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        $erro = "Preencha todos os campos (e-mail e senha).";
    } else {
        // Buscar 'senha_hash' do cliente no BD
        $sql = "SELECT id, nome, senha_hash FROM customers WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente) {
            // Verifica a senha usando password_verify
            if (password_verify($senha, $cliente['senha_hash'])) {
                // LOGIN OK
                $_SESSION['customer_id']   = $cliente['id'];
                $_SESSION['customer_nome'] = $cliente['nome'];

                // Verifica o nextParam
                if ($nextParam === 'checkout') {
                    // Redireciona direto para o checkout
                    header("Location: index.html#paginaClienteEnvio");
                } else {
                    // Caso contrário, volta pra loja
                    header("Location: index.html");
                }
                exit;
            } else {
                $erro = "Senha incorreta.";
            }
        } else {
            $erro = "E-mail não cadastrado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Login | Império Pharma</title>

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

    /* Botão “Entrar” com efeito leve de 3D */
    .btn-login {
      width: 100%;
      padding: 12px;
      background: #d52b1e;
      color: #fff;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      border-radius: 6px;
      margin-top: 10px;
      box-shadow: 0 4px 0 #b2221c;
      transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
    }
    .btn-login:hover {
      background: #e33124; 
    }
    .btn-login:active {
      transform: translateY(1px);
      box-shadow: 0 2px 0 #b2221c;
    }

    /* Divisor “ou” */
    .divider-or {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 20px 0;
    }
    .divider-or::before,
    .divider-or::after {
      content: "";
      flex: 1;
      height: 1px;
      background: #ccc;
      margin: 0 8px;
    }
    .divider-or span {
      font-size: 0.9rem;
      color: #666;
      font-weight: bold;
    }

    /* Botão Google (personalizado) */
    .google-btn {
      width: 100%;
      margin-top: 10px;
      padding: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.6rem;
      background: #4285F4; /* cor oficial do Google */
      color: #fff;
      font-weight: bold;
      border: none;
      border-radius: 4px;
      font-size: 1rem;
      cursor: pointer;
      box-shadow: 0 4px 0 #2c64c0;
      transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
    }
    .google-btn i {
      font-size: 1.2rem;
    }
    .google-btn:hover {
      background: #357ae8;
    }
    .google-btn:active {
      transform: translateY(1px);
      box-shadow: 0 2px 0 #2c64c0;
    }

    /* Links inferiores */
    .links-secundarios {
      margin-top: 20px;
      text-align: center;
    }
    .links-secundarios a {
      margin: 0 10px;
      text-decoration: none;
      color: #081c4b;
      font-weight: bold;
      font-size: 0.95rem;
    }
    .links-secundarios a:hover {
      text-decoration: underline;
    }

    /* Botão destacado para criar conta */
    .btn-criar-conta {
      display: inline-block;
      background: linear-gradient(135deg, #0066cc, #003f7b);
      color: #fff !important;
      padding: 12px 16px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      margin-right: 10px;
      box-shadow: 0 4px 0 #002850;
      transition: background 0.3s, box-shadow 0.3s, transform 0.3s, color 0.3s;
    }
    .btn-criar-conta:hover {
      background: linear-gradient(135deg, #0077ee, #003f7b);
      box-shadow: 0 3px 0 #002850;
      transform: translateY(-1px);
      color: #fff !important;
    }
    .btn-criar-conta:active {
      transform: translateY(1px);
      box-shadow: 0 2px 0 #002850;
      color: #fff !important;
    }

    /* Ajustes responsivos */
    @media (max-width: 480px) {
      .container {
        margin: 15px;
        padding: 15px;
      }
      h1 {
        font-size: 1.2rem;
      }
      .btn-login, .google-btn {
        font-size: 1rem;
      }
      .btn-criar-conta {
        font-size: 0.9rem;
        padding: 10px 12px;
      }
      .divider-or span {
        font-size: 0.85rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Acesse sua Conta</h1>
    <p class="subtitulo">Faça login para prosseguir com suas compras</p>

    <?php if ($erro): ?>
      <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- LOGIN TRADICIONAL (FORM) -->
    <form method="post">
      <!-- Retém o parâmetro ?next se existir -->
      <input type="hidden" name="next" value="<?= htmlspecialchars($nextParam) ?>">

      <div class="form-group">
        <label for="email">E-mail:</label>
        <input
          type="email"
          name="email"
          id="email"
          placeholder="Digite seu e-mail"
          required
          autofocus
        />
      </div>

      <div class="form-group">
        <label for="senha">Senha:</label>
        <input
          type="password"
          name="senha"
          id="senha"
          placeholder="Digite sua senha"
          required
        />
      </div>

      <button type="submit" class="btn-login">Entrar</button>
    </form>

    <!-- Divisor “OU” -->
    <div class="divider-or">
      <span>OU</span>
    </div>

    <!-- Título acima do botão Google (opcional) -->
    <p style="text-align:center; font-size:0.95rem; color:#444; margin-bottom:10px;">
      Use sua Conta Google
    </p>

    <!-- BOTÃO GOOGLE -->
    <button type="button" class="google-btn" id="btnGoogleLogin">
      <i class="fa-brands fa-google"></i> Continuar com Google
    </button>

    <div class="links-secundarios" style="margin-top: 30px;">
      <a 
        href="register.php<?php if($nextParam==='checkout') echo '?next=checkout'; ?>"
        class="btn-criar-conta"
      >
        Criar Conta
      </a>
      <a href="index.html" style="font-size:0.95rem;">
        Voltar à Loja
      </a>
    </div>
  </div>


  <!-- SCRIPTS DO FIREBASE (versão 9.x compat) -->
  <script src="https://www.gstatic.com/firebasejs/9.21.0/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/9.21.0/firebase-auth-compat.js"></script>

  <script>
    // SEU firebaseConfig
    const firebaseConfig = {
      apiKey: "AIzaSyC3UARqOJeIFaS3LZx8Q_c4ckD-6cF24y0",
      authDomain: "imperio-projeto.firebaseapp.com",
      projectId: "imperio-projeto",
      storageBucket: "imperio-projeto.firebasestorage.app",
      messagingSenderId: "350104445801",
      appId: "1:350104445801:web:5d7bd221ec21259533d3f8"
    };

    // Inicializa Firebase com compat (assim "firebase" é global)
    firebase.initializeApp(firebaseConfig);
    const auth = firebase.auth();

    // Pegar o nextParam da URL (para redirecionar depois)
    const urlParams = new URLSearchParams(window.location.search);
    let nextParam = urlParams.get('next') || '';

    // Botão “Entrar com Google”
    const btnGoogle = document.getElementById('btnGoogleLogin');
    if(btnGoogle){
      btnGoogle.addEventListener('click', async()=>{
        try {
          const provider = new firebase.auth.GoogleAuthProvider();
          // Abre popup
          const result = await auth.signInWithPopup(provider);
          const user = result.user;
          if(!user) {
            alert("Falha ao logar com Google (sem usuário).");
            return;
          }

          // Pega dados do user
          const email = user.email || "";
          const displayName = user.displayName || "";
          const googleUid = user.uid || "";

          // Envia ao back-end (loginSocial.php) para criar/atualizar conta e abrir sessão
          const payload = { email, displayName, googleUid };
          const resp = await fetch('loginSocial.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const json = await resp.json();
          if(!json.sucesso){
            alert("Erro ao sincronizar conta Google: " + (json.mensagem || 'desconhecido'));
            return;
          }

          // Se deu certo, redireciona
          if(nextParam === 'checkout'){
            window.location.href = 'index.html#paginaClienteEnvio';
          } else {
            window.location.href = 'index.html';
          }

        } catch(err){
          console.error(err);
          alert("Erro ao logar com Google: " + err.message);
        }
      });
    }
  </script>
</body>
</html>
