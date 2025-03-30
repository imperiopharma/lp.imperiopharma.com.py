<?php
session_start();

// =========================
// 1) CONFIG DE BD (AJUSTE SE PRECISAR)
// =========================
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
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao conectar ao banco: ' . $e->getMessage()
    ]);
    exit;
}

// 2) LER DADOS DO POST (JSON)
$rawJson = file_get_contents('php://input');
if(!$rawJson) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Nenhum dado enviado.'
    ]);
    exit;
}

$data = json_decode($rawJson, true);
if(!$data) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'JSON inválido.'
    ]);
    exit;
}

// Esperamos { email, displayName, googleUid }
$email       = isset($data['email'])       ? trim($data['email'])       : '';
$displayName = isset($data['displayName']) ? trim($data['displayName']) : '';
$googleUid   = isset($data['googleUid'])   ? trim($data['googleUid'])   : '';

if($email === '' || $googleUid === '') {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Email ou googleUid faltando.'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Tenta achar por google_uid
    $stmtCheckUid = $pdo->prepare("SELECT id, nome, email FROM customers WHERE google_uid = :gUid LIMIT 1");
    $stmtCheckUid->execute([':gUid' => $googleUid]);
    $existUid = $stmtCheckUid->fetch(PDO::FETCH_ASSOC);

    if($existUid) {
        // Já existe esse google_uid -> loga direto
        $clienteId = $existUid['id'];
        $pdo->commit();

        $_SESSION['customer_id']   = $clienteId;
        $_SESSION['customer_nome'] = $existUid['nome'] ?: 'Cliente Google'; 
        echo json_encode(['sucesso' => true]);
        exit;
    }

    // 2) Se não achou google_uid, ver se existe um registro com esse email
    $stmtCheckEmail = $pdo->prepare("
        SELECT id, nome, email, google_uid
        FROM customers
        WHERE email = :email
        LIMIT 1
    ");
    $stmtCheckEmail->execute([':email' => $email]);
    $existEmail = $stmtCheckEmail->fetch(PDO::FETCH_ASSOC);

    if($existEmail) {
        // Usuário já existe pelo email, mas não tem google_uid
        $clienteId = $existEmail['id'];

        if(!empty($existEmail['google_uid'])) {
            // Em teoria, não deveria cair aqui sem já ter ido no if anterior
            $pdo->commit();
            $_SESSION['customer_id']   = $clienteId;
            $_SESSION['customer_nome'] = $existEmail['nome'] ?: 'Cliente Google';
            echo json_encode(['sucesso' => true]);
            exit;
        } else {
            // Vincular google_uid
            $stmtUpd = $pdo->prepare("
              UPDATE customers
              SET google_uid = :gUid
              WHERE id = :cid
              LIMIT 1
            ");
            $stmtUpd->execute([
              ':gUid' => $googleUid,
              ':cid'  => $clienteId
            ]);
            $pdo->commit();

            $_SESSION['customer_id']   = $clienteId;
            $_SESSION['customer_nome'] = $existEmail['nome'] ?: 'Cliente Google';
            echo json_encode(['sucesso' => true]);
            exit;
        }

    } else {
        // 3) Se não existe, cria
        $nomeFinal = $displayName !== '' ? $displayName : 'Cliente Google';

        $stmtIns = $pdo->prepare("
            INSERT INTO customers (nome, email, google_uid, senha_hash, points)
            VALUES (:nome, :email, :gUid, '', 0)
        ");
        $stmtIns->execute([
            ':nome'  => $nomeFinal,
            ':email' => $email,
            ':gUid'  => $googleUid
        ]);
        $clienteId = $pdo->lastInsertId();

        $pdo->commit();
        $_SESSION['customer_id']   = $clienteId;
        $_SESSION['customer_nome'] = $nomeFinal;

        echo json_encode(['sucesso' => true]);
        exit;
    }

} catch(Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro interno: '.$e->getMessage()
    ]);
    exit;
}
