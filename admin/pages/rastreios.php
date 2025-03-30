<?php
/****************************************************************************
 * RASTREIOS AVANÇADO - Sistema de Gestão de Rastreios ImperioPharma
 * - Dashboard de estatísticas
 * - Pedidos sem rastreio / com rastreio (com filtros avançados)
 * - Importação de rastreios com possibilidade de aceitar/rejeitar combinações
 * - Logs de envio de WhatsApp
 * - Configurações dinâmicas (mensagem padrão, link padrão) + Teste de envio
 * - Funções de automação (reenviar, filtrar CEP, etc.)
 ****************************************************************************/

// Garantir que está sendo executado dentro do sistema
defined('SYSTEM_PATH') or die('Acesso direto ao arquivo não permitido');

// Ajuste para conexão PDO
require_once __DIR__ . '/../inc/config.php';

// Definição da função getStatusClass - necessária para o sistema
function getStatusClass($status) {
    // Normaliza o status para comparação
    $status = strtoupper(trim($status));
    
    switch ($status) {
        case 'PENDENTE': 
            return 'bg-warning text-dark';
        case 'CONFIRMADO': 
            return 'bg-primary text-white';
        case 'EM PROCESSO': 
            return 'bg-info text-white';
        case 'CONCLUIDO': 
        case 'CONCLUÍDO': // Versão com acento
            return 'bg-success text-white';
        case 'CANCELADO': 
            return 'bg-danger text-white';
        default: 
            return 'bg-secondary text-white';
    }
}

// Função para obter ícone de status
function getStatusIcon($status) {
    $status = strtoupper(trim($status));
    
    switch ($status) {
        case 'PENDENTE':
            return '<i class="fas fa-clock"></i>';
        case 'CONFIRMADO':
            return '<i class="fas fa-check-circle"></i>';
        case 'EM PROCESSO':
            return '<i class="fas fa-cog"></i>';
        case 'CONCLUIDO':
        case 'CONCLUÍDO':
            return '<i class="fas fa-check-double"></i>';
        case 'CANCELADO':
            return '<i class="fas fa-times-circle"></i>';
        default:
            return '<i class="fas fa-question-circle"></i>';
    }
}

/**
 * Se possível, garanta que a conexão PDO esteja usando utf8mb4 (para emojis).
 */
try {
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
} catch (Exception $e) {
    // Em caso de erro, apenas registra
    error_log("Falha ao definir utf8mb4: " . $e->getMessage());
}

/****************************************************************************
 * CONFIGURAÇÕES:
 * Buscamos da tabela 'settings' (se existir).
 ****************************************************************************/
$WASCRIPT_TOKEN_DEFAULT       = "1741243040070-789f20d337e5e8d6c95621ba5f5807f8"; // fallback
$MENSAGEM_PADRAO_DEFAULT      = "📦 *Rastreamento do Seu Pedido* 📦\n\nOlá {NOME},\n\nSegue abaixo seu código:\n*{CODIGO}*\n\nEquipe Império Pharma";
$LINK_RASTREIO_PADRAO_DEFAULT = "https://melhorrastreio.com.br/";
$ENABLE_BULK_WHATSAPP         = true; // habilita envio em lote

// Função para checar se tabela existe
function tableExists($pdo, $tableName) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        return ($check && $check->rowCount() > 0);
    } catch(Exception $e) {
        return false;
    }
}

// Lê config da tabela settings
function getSetting($pdo, $key, $default='') {
    static $cached = [];
    if (isset($cached[$key])) return $cached[$key];

    if (!tableExists($pdo, 'settings')) {
        $cached[$key] = $default;
        return $default;
    }
    $stm = $pdo->prepare("SELECT config_value FROM settings WHERE config_key=? LIMIT 1");
    $stm->execute([$key]);
    $val = $stm->fetchColumn();
    if ($val===false) {
        $cached[$key] = $default;
        return $default;
    }
    return $val;
}

// Carrega configs do DB ou usa default
$WASCRIPT_TOKEN       = getSetting($pdo, 'wascript_token', $WASCRIPT_TOKEN_DEFAULT);
$MENSAGEM_PADRAO      = getSetting($pdo, 'whatsapp_message', $MENSAGEM_PADRAO_DEFAULT);
$LINK_RASTREIO_PADRAO = getSetting($pdo, 'link_rastreio_padrao', $LINK_RASTREIO_PADRAO_DEFAULT);

// Verifica se colunas shipped, shipped_at existem
function columnExists($pdo, $table, $column) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return ($check && $check->rowCount()>0);
    } catch(Exception $e) {
        return false;
    }
}
$hasShippedColumn = columnExists($pdo, 'orders', 'shipped');

/****************************************************************************
 * TABELA DE LOGS (whatsapp_logs)
 ****************************************************************************/
function logWhatsApp($pdo, $orderId, $phone, $msg, $httpCode, $response) {
    // Se a tabela whatsapp_logs não existir, ignora
    if (!tableExists($pdo, 'whatsapp_logs')) {
        return;
    }
    // Se for envio de teste (orderId===null), não registra para evitar erro
    if ($orderId === null) {
        return;
    }

    $stm = $pdo->prepare("INSERT INTO whatsapp_logs (order_id, phone, message, http_code, response, created_at)
                         VALUES (:oid, :ph, :msg, :hc, :resp, NOW())");
    $stm->execute([
        ':oid'  => $orderId,
        ':ph'   => $phone,
        ':msg'  => $msg,
        ':hc'   => $httpCode,
        ':resp' => $response
    ]);
}

/****************************************************************************
 * FUNÇÕES DE AUXÍLIO GERAIS
 ****************************************************************************/
function cepClean($cep) {
    return substr(preg_replace('/\D/','',$cep??''),0,8);
}
function normalize_str($str) {
    if(!$str)return'';
    $s=mb_strtoupper($str,'UTF-8');
    $map=[
        '/[ÁÀÂÃÄ]/u'=>'A','/[ÉÈÊË]/u'=>'E','/[ÍÌÎÏ]/u'=>'I',
        '/[ÓÒÔÕÖ]/u'=>'O','/[ÚÙÛÜ]/u'=>'U','/[Ç]/u'=>'C'
    ];
    foreach($map as $rgx=>$rep){ 
        $s=preg_replace($rgx,$rep,$s); 
    }
    $s=preg_replace('/[^A-Z0-9 ]+/',' ',$s);
    $s=preg_replace('/\s+/',' ',$s);
    return trim($s);
}
function similar_score($a,$b){
    if(!$a||!$b)return 0.0;
    similar_text($a,$b,$pct);
    return $pct;
}

// Envio WhatsApp via Wascript
function enviarMensagemWhatsApp($phone, $message, $token, $pdo, $orderId=null) {
    $url = "https://api-whatsapp.wascript.com.br/api/enviar-texto/{$token}";

    // Normaliza telefone, garante "55"
    $phone = preg_replace('/\D/','',$phone);
    if (substr($phone,0,2)!=='55') {
        $phone='55'.$phone;
    }
    $payload = ["phone"=>$phone,"message"=>$message];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Registra log
    logWhatsApp($pdo, $orderId, $phone, $message, $httpCode, $response);

    return ['httpCode'=>$httpCode, 'response'=>$response];
}

/****************************************************************************
 * MATCHING FUNÇÕES
 ****************************************************************************/
function matchAllOrders($pdo, $nome, $cidade, $uf, $cep) {
    $cp=cepClean($cep);
    if(!$cp)return[];
    $sql="SELECT * FROM orders
          WHERE REPLACE(cep,'-','')=:c
            AND (tracking_code IS NULL OR tracking_code='')
          ORDER BY id DESC LIMIT 200";
    $stm=$pdo->prepare($sql);
    $stm->execute([':c'=>$cp]);
    $rows=$stm->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows)return[];

    $nomeUf=normalize_str($nome.' '.$uf);
    $cityUf=normalize_str($cidade.' '.$uf);

    $matches=[];
    foreach($rows as $r){
        $dbName=normalize_str(($r['customer_name']??'').' '.($r['state']??''));
        $dbCity=normalize_str(($r['city']??'').' '.($r['state']??''));
        $scoreNome=similar_score($nomeUf,$dbName);
        $scoreCity=similar_score($cityUf,$dbCity);
        $avg=($scoreNome+$scoreCity)/2.0;

        if($scoreNome>=30 && $scoreCity>=30 && $avg>=60) {
            $r['score_nome'] = $scoreNome;
            $r['score_cidade'] = $scoreCity;
            $r['score_total'] = $avg;
            $matches[] = $r;
        }
    }
    usort($matches, function($a,$b){
        return ($b['score_total'] <=> $a['score_total']);
    });
    return $matches;
}
function matchOrdersByCEP($pdo, $cep) {
    $cp=cepClean($cep);
    if(!$cp)return[];
    $sql="SELECT * FROM orders
          WHERE REPLACE(cep,'-','')=:c
            AND (tracking_code IS NULL OR tracking_code='')
          ORDER BY id DESC LIMIT 200";
    $stm=$pdo->prepare($sql);
    $stm->execute([':c'=>$cp]);
    return $stm->fetchAll(PDO::FETCH_ASSOC);
}

/****************************************************************************
 * PARSER DE PLANILHA/BLOCO
 ****************************************************************************/
function parsePlanilhaLine($line) {
    if(stripos($line,'Objeto')===0)return null;
    $cols=preg_split('/\t|\s{2,}/',trim($line));
    if(count($cols)<11)return null;
    return [
        'raw'=>$line,
        'objeto'=>trim($cols[0]),
        'codigo'=>trim($cols[1]),
        'nome'=>trim($cols[2]),
        'cidade'=>trim($cols[8]),
        'uf'=>trim($cols[9]),
        'cep'=>trim($cols[10])
    ];
}
function parseBlocosTexto($txt) {
    $ret=[];
    $parts=explode('------',$txt);
    foreach($parts as $bk){
        $bk=trim($bk);
        if($bk==='')continue;

        $nome='';$cid='';$uf='';$cp='';$obj='';$cod='';
        $lines=preg_split('/\r?\n/',$bk);
        foreach($lines as $ln){
            $ln=trim($ln);
            if(preg_match('/^NOME:\s*(.*)$/i',$ln,$m)){
                $nome=$m[1];
            }elseif(preg_match('/^CIDADE:\s*(.*)$/i',$ln,$m)){
                $tmp=$m[1];
                if(strpos($tmp,'-')!==false){
                    list($cid,$uf)=array_map('trim', explode('-', $tmp));
                } else {
                    $cid=trim($tmp);
                }
            }elseif(preg_match('/^CEP:\s*(.*)$/i',$ln,$m)){
                $cp=$m[1];
            }elseif(preg_match('/^OBJETO:\s*(.*)$/i',$ln,$m)){
                $obj=$m[1];
            }elseif(preg_match('/^CODIGO:\s*(.*)$/i',$ln,$m)){
                $cod=$m[1];
            }
        }
        $ret[]=[
            'raw'=>$bk,
            'objeto'=>$obj,
            'codigo'=>$cod,
            'nome'=>$nome,
            'cidade'=>$cid,
            'uf'=>$uf,
            'cep'=>$cp
        ];
    }
    return $ret;
}

/****************************************************************************
 * AGENDAMENTO (Opcional)
 ****************************************************************************/
function scheduleWhatsApp($pdo, $orderId, $datetime) {
    if(!tableExists($pdo,'scheduler'))return false;
    $stm=$pdo->prepare("INSERT INTO scheduler (order_id, send_at, created_at) VALUES (?, ?, NOW())");
    return $stm->execute([$orderId,$datetime]);
}
function processScheduler($pdo) {
    if(!tableExists($pdo,'scheduler')){
        echo "Tabela 'scheduler' não existe.\n";
        return;
    }
    $stm=$pdo->prepare("SELECT * FROM scheduler WHERE send_at<=NOW() AND (status=0 OR status IS NULL) LIMIT 50");
    $stm->execute();
    $rows=$stm->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r){
        $sId=(int)$r['id'];
        $oid=(int)$r['order_id'];
        $stmtOrd=$pdo->prepare("SELECT phone, customer_name, tracking_code FROM orders WHERE id=? LIMIT 1");
        $stmtOrd->execute([$oid]);
        $o=$stmtOrd->fetch(PDO::FETCH_ASSOC);
        if(!$o){
            $upd=$pdo->prepare("UPDATE scheduler SET status=2 WHERE id=?");
            $upd->execute([$sId]);
            continue;
        }
        $phone = trim($o['phone']??'');
        $nome  = trim($o['customer_name']??'');
        $trk   = trim($o['tracking_code']??'');
        if(!$phone||!$trk){
            $upd=$pdo->prepare("UPDATE scheduler SET status=2 WHERE id=?");
            $upd->execute([$sId]);
            continue;
        }
        global $MENSAGEM_PADRAO, $WASCRIPT_TOKEN;
        $msg=str_replace(['{NOME}','{CODIGO}'], [$nome,$trk], $MENSAGEM_PADRAO);
        $msg.="\nAcompanhe aqui: (default link)";
        $res=enviarMensagemWhatsApp($phone,$msg,$WASCRIPT_TOKEN,$pdo,$oid);

        $upd=$pdo->prepare("UPDATE scheduler SET status=1, processed_at=NOW() WHERE id=?");
        $upd->execute([$sId]);
    }
    echo "Agendamentos processados: ".count($rows)."\n";
}

/****************************************************************************
 * FUNÇÕES DE AUTILIZAÇÃO DO AMBIENTE DE EXIBIÇÃO
 * Estas funções substituem parte das variáveis HTML diretamente embutidas
 ****************************************************************************/
// Define o título da página
$pageTitle = "Rastreio de Entregas";

// Defina a página atual para o menu
$currentMenu = "rastreios";

/****************************************************************************
 * AÇÕES
 ****************************************************************************/
$action = $_GET['action'] ?? '';
$tab    = $_GET['tab']    ?? 'dashboard';

// Se for CRON de agendamento
if($action==='process_scheduler'){
    processScheduler($pdo);
    exit;
}

/****************************************************************************
 * 1) ENVIAR WHATSAPP (INDIVIDUAL)
 ****************************************************************************/
if($action==='send_whatsapp') {
    $orderId = (int)($_GET['id'] ?? 0);
    if($orderId<=0) { exit("ID inválido."); }
    $stmt=$pdo->prepare("SELECT phone, customer_name, tracking_code FROM orders WHERE id=? LIMIT 1");
    $stmt->execute([$orderId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order){ exit("Pedido não encontrado."); }

    $phone = trim($order['phone'] ?? '');
    $nome  = trim($order['customer_name'] ?? '');
    $trk   = trim($order['tracking_code'] ?? '');
    if(!$phone || !$trk){ exit("Telefone ou tracking inexistente."); }

    $baseLink = trim($_GET['base'] ?? $LINK_RASTREIO_PADRAO);
    $msg = str_replace(['{NOME}','{CODIGO}'], [$nome,$trk], $MENSAGEM_PADRAO);
    $msg .= "\nAcompanhe em: ".$baseLink;

    $res = enviarMensagemWhatsApp($phone,$msg,$WASCRIPT_TOKEN,$pdo,$orderId);
    if($res['httpCode']===200){
        // Marca 'shipped=1'
        if($hasShippedColumn){
            $upd=$pdo->prepare("UPDATE orders SET shipped=1, shipped_at=NOW() WHERE id=?");
            $upd->execute([$orderId]);
        }
        echo "<script>alert('Mensagem enviada com sucesso!'); window.location='index.php?page=rastreios&tab=matched';</script>";
    } else {
        echo "<script>alert('Falha ao enviar (HTTP {$res['httpCode']}).'); window.location='index.php?page=rastreios&tab=matched';</script>";
    }
    exit;
}

/****************************************************************************
 * 1.1) ENVIO EM LOTE
 ****************************************************************************/
if($action==='send_whatsapp_bulk' && $_SERVER['REQUEST_METHOD']==='POST' && $ENABLE_BULK_WHATSAPP){
    $ids=$_POST['ids']??[];
    if(!is_array($ids) || count($ids)===0){
        echo "<script>alert('Nenhum pedido selecionado!'); window.location='index.php?page=rastreios&tab=matched';</script>";
        exit;
    }
    $sqlIds = implode(',', array_map('intval',$ids));
    $stmt   = $pdo->query("SELECT id, phone, customer_name, tracking_code FROM orders WHERE id IN($sqlIds)");
    $pedidos= $stmt->fetchAll(PDO::FETCH_ASSOC);

    $enviados=0; 
    $erros=[];
    foreach($pedidos as $od){
        $oid   = (int)$od['id'];
        $phone = trim($od['phone'] ?? '');
        $nome  = trim($od['customer_name'] ?? '');
        $trk   = trim($od['tracking_code'] ?? '');

        if(!$phone || !$trk){
            $erros[]="Pedido #{$oid}: faltam dados (telefone ou código).";
            continue;
        }
        $msg = str_replace(['{NOME}','{CODIGO}'], [$nome,$trk], $MENSAGEM_PADRAO);
        $msg .= "\nAcompanhe em: ".$LINK_RASTREIO_PADRAO;

        $res=enviarMensagemWhatsApp($phone,$msg,$WASCRIPT_TOKEN,$pdo,$oid);
        if($res['httpCode']===200){
            if($hasShippedColumn){
                $upd=$pdo->prepare("UPDATE orders SET shipped=1, shipped_at=NOW() WHERE id=?");
                $upd->execute([$oid]);
            }
            $enviados++;
        } else {
            $erros[]="Pedido #{$oid}: falha (HTTP {$res['httpCode']}).";
        }
    }

    echo "<div style='padding:20px;font-family:sans-serif;'>";
    echo "<h3>Envio em Lote - Concluído</h3>";
    echo "<p>Enviados: {$enviados}</p>";
    if($erros){
        echo "<p>Erros:</p><ul>";
        foreach($erros as $er){ 
            echo "<li>".htmlspecialchars($er)."</li>"; 
        }
        echo "</ul>";
    }
    echo "<a href='index.php?page=rastreios&tab=matched'>Voltar</a>";
    echo "</div>";
    exit;
}

/****************************************************************************
 * 2) DESFAZER TRACKING
 ****************************************************************************/
if($action==='undo'){
    $orderId = (int)($_GET['id'] ?? 0);
    if($orderId>0){
        // Remove tracking e marca 'shipped=0'
        if($hasShippedColumn){
            $stmt=$pdo->prepare("UPDATE orders SET tracking_code='', shipped=0 WHERE id=?");
        } else {
            $stmt=$pdo->prepare("UPDATE orders SET tracking_code='' WHERE id=?");
        }
        $stmt->execute([$orderId]);
    }
    header("Location: index.php?page=rastreios&tab=matched");
    exit;
}

/****************************************************************************
 * 3) EDIT TRACKING
 ****************************************************************************/
if($action==='edit'){
    $orderId = (int)($_GET['id'] ?? 0);
    if($orderId<=0){
        exit("ID inválido.");
    }
    $stm=$pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
    $stm->execute([$orderId]);
    $order=$stm->fetch(PDO::FETCH_ASSOC);
    if(!$order){
        exit("Pedido não encontrado!");
    }

    if($_SERVER['REQUEST_METHOD']==='POST'){
        $newTrack= trim($_POST['tracking_code'] ?? '');
        $upd=$pdo->prepare("UPDATE orders SET tracking_code=? WHERE id=? LIMIT 1");
        $upd->execute([$newTrack,$orderId]);
        // Opcional: se remover o tracking, também definimos shipped=0, etc.
        echo "<script>window.location='index.php?page=rastreios&tab=matched';</script>";
        exit;
    }
    
    // Incluir o layout do sistema
    include_once("header.php");
    ?>
    <div class="container my-4" style="max-width:600px;">
      <h2>Editar Tracking do Pedido #<?= $orderId ?></h2>
      <form method="POST">
        <div class="mb-3">
          <label>Tracking Atual:</label>
          <input type="text" class="form-control" disabled
                 value="<?=htmlspecialchars($order['tracking_code']??'')?>">
        </div>
        <div class="mb-3">
          <label>Novo Tracking (vazio p/ remover):</label>
          <input type="text" name="tracking_code" class="form-control"
                 value="<?=htmlspecialchars($order['tracking_code']??'')?>">
        </div>
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="index.php?page=rastreios&tab=matched" class="btn btn-secondary">Cancelar</a>
      </form>
    </div>
    <?php
    include_once("footer.php");
    exit;
}

/****************************************************************************
 * 4) UPDATE MENSAGENS (admin_comments)
 ****************************************************************************/
if($action==='update_messages' && $_SERVER['REQUEST_METHOD']==='POST'){
    $messages=$_POST['messages']??[];
    $countUp=0;
    foreach($messages as $orderId=>$msg){
        $orderId=(int)$orderId; 
        $msg=trim($msg);
        if($orderId>0 && $msg!==''){
            $stmGet=$pdo->prepare("SELECT admin_comments FROM orders WHERE id=? LIMIT 1");
            $stmGet->execute([$orderId]);
            $old=$stmGet->fetchColumn()??'';
            $sep=$old ? "\n" : "";
            $new=$old.$sep.$msg;

            $stmUpd=$pdo->prepare("UPDATE orders SET admin_comments=? WHERE id=? LIMIT 1");
            $stmUpd->execute([$new,$orderId]);
            $countUp++;
        }
    }
    echo "<script>alert('Mensagens atualizadas em {$countUp} pedidos!'); window.location='index.php?page=rastreios&tab=unmatched';</script>";
    exit;
}

/****************************************************************************
 * 5) IMPORT (PROCESS)
 ****************************************************************************/
function parseImport($pdo, $texto, $formato){
    $arrOK=[]; 
    $arrFail=[];

    if($formato==='bloco'){
        $blocos=parseBlocosTexto($texto);
        foreach($blocos as $bk){
            if(!$bk['cep']||!$bk['codigo']){
                $arrFail[]=$bk; 
                continue;
            }
            $matches=matchAllOrders($pdo, $bk['nome'], $bk['cidade'], $bk['uf'], $bk['cep']);
            if(!$matches){
                $arrFail[]=$bk;
            } else {
                $bk['matches']=$matches;
                $arrOK[]=$bk;
            }
        }
    }
    elseif($formato==='novopar'){
        $lines=preg_split('/\r?\n/',$texto);
        $nonEmpty=array_values(array_filter($lines,fn($x)=>trim($x)!=''));
        if(count($nonEmpty)%2!==0){
            return ['error'=>"Número de linhas inválido (cada par: 1 CEP + 1 Código).", 'ok'=>[], 'fail'=>[]];
        }
        for($i=0;$i<count($nonEmpty);$i+=2){
            $cep=cepClean($nonEmpty[$i]);
            $cod=trim($nonEmpty[$i+1]);
            if(strlen($cep)!==8||strlen($cod)<5){
                $arrFail[]=['raw'=>$nonEmpty[$i]."\n".$nonEmpty[$i+1]];
                continue;
            }
            $m=matchOrdersByCEP($pdo,$cep);
            if(!$m){
                $arrFail[]=['raw'=>$nonEmpty[$i]."\n".$nonEmpty[$i+1]];
            } else {
                $arrOK[]=[
                    'raw'=>$nonEmpty[$i]."\n".$nonEmpty[$i+1],
                    'cep'=>$cep,
                    'codigo'=>$cod,
                    'matches'=>$m
                ];
            }
        }
    }
    else{
        // planilha
        $lines=preg_split('/\r?\n/',$texto);
        foreach($lines as $ln){
            $ln=trim($ln);
            if($ln===''||stripos($ln,'Objeto')===0)continue;
            $p=parsePlanilhaLine($ln);
            if(!$p||!$p['cep']||!$p['codigo']){
                $arrFail[]=['raw'=>$ln];
                continue;
            }
            $m=matchAllOrders($pdo,$p['nome'],$p['cidade'],$p['uf'],$p['cep']);
            if(!$m){
                $arrFail[]=$p;
            } else {
                $p['matches']=$m;
                $arrOK[]=$p;
            }
        }
    }
    return ['error'=>null,'ok'=>$arrOK,'fail'=>$arrFail];
}

if($action==='import_process' && $_SERVER['REQUEST_METHOD']==='POST'){
    $texto=trim($_POST['textoBruto']??'');
    $formato=trim($_POST['formato']??'planilha');
    if($texto===''){
        echo "<script>alert('Texto vazio.'); window.location='index.php?page=rastreios&tab=import';</script>";
        exit;
    }
    $res=parseImport($pdo, $texto, $formato);
    if($res['error']){
        echo "<script>alert('Erro: {$res['error']}'); window.location='index.php?page=rastreios&tab=import';</script>";
        exit;
    }
    $arrOK=$res['ok'];
    $arrFail=$res['fail'];
    
    // Incluir o layout do sistema
    include_once("header.php");
    ?>
    <div class="container my-4">
      <h3>Resultado do Import</h3>
      <form method="POST" action="index.php?page=rastreios&action=save_import_results">
        <?php if(!empty($arrOK)): ?>
          <div class="alert alert-info mb-3">
            Foram encontrados <?=count($arrOK)?> blocos/linhas com match.
          </div>
          <?php foreach($arrOK as $i=>$item):
              $raw=$item['raw']??'';
              $cep=$item['cep']??'';
              $cod=$item['codigo']??'';
              $matches=$item['matches']??[];
          ?>
          <div class="card mb-3">
            <div class="card-body">
              <p><strong>CEP:</strong> <?=htmlspecialchars($cep)?>
                 &nbsp;|&nbsp;
                 <strong>Código:</strong> <?=htmlspecialchars($cod)?></p>
              <pre style="white-space:pre-wrap; background:#f8f9fa; padding:8px;"><?=htmlspecialchars($raw)?></pre>
              <?php if($matches): ?>
                <p><strong>Pedidos Possíveis (marque os que são certos):</strong></p>
                <div class="row">
                  <?php foreach($matches as $j=>$od):
                      $pid=(int)$od['id'];
                      $scoreNome=round($od['score_nome']??0,1);
                      $scoreCid=round($od['score_cidade']??0,1);
                      $scoreTot=round($od['score_total']??0,1);
                  ?>
                  <div class="col-md-6 col-lg-4 mb-2">
                    <div class="border p-2">
                      <div class="form-check">
                        <input type="checkbox" class="form-check-input"
                               name="import[<?= $i ?>][matches][]"
                               id="ch_<?= $i ?>_<?= $j ?>"
                               value="<?=$pid?>">
                        <label class="form-check-label" for="ch_<?= $i ?>_<?= $j ?>">
                          Pedido #<?=$pid?> | Score <?=$scoreTot?>%
                        </label>
                      </div>
                      <button type="button" class="btn btn-sm btn-secondary mt-1"
                              onclick="toggleMatchDetail('md_<?=$i?>_<?=$j?>')">Detalhes</button>
                      <div id="md_<?=$i?>_<?=$j?>" class="details-match">
                        <ul class="list-unstyled" style="font-size:0.85rem;">
                          <li><strong>ID:</strong> <?=$pid?></li>
                          <li><strong>Nome:</strong> <?=htmlspecialchars($od['customer_name']??'')?> </li>
                          <li><strong>Telefone:</strong> <?=htmlspecialchars($od['phone']??'')?> </li>
                          <li><strong>Endereço:</strong> <?=htmlspecialchars($od['address']??'')?>, 
                            <?=htmlspecialchars($od['number']??'')?>, <?=htmlspecialchars($od['cep']??'')?>,
                            <?=htmlspecialchars(($od['city']??'').'/'.($od['state']??''))?>
                          </li>
                          <li><strong>Score Nome:</strong> <?=$scoreNome?>%</li>
                          <li><strong>Score Cidade:</strong> <?=$scoreCid?>%</li>
                          <li><strong>Status:</strong> <?=htmlspecialchars($od['status']??'')?> </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="alert alert-warning mt-2">Nenhum pedido possível encontrado.</div>
              <?php endif; ?>

              <div class="mt-2">
                <label><strong>Mensagem admin_comments (opcional):</strong></label>
                <textarea name="import[<?=$i?>][msg]" rows="2" class="form-control"></textarea>
              </div>
              <input type="hidden" name="import[<?=$i?>][cod]" value="<?=htmlspecialchars($cod)?>">
            </div>
          </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary">Salvar Rastreios</button>
        <?php else: ?>
          <div class="alert alert-warning">Nenhum match encontrado.</div>
        <?php endif; ?>

        <?php if(!empty($arrFail)): ?>
          <div class="alert alert-secondary mt-4">
            <strong><?=count($arrFail)?> blocos/linhas sem match</strong>
          </div>
          <ul class="list-group">
          <?php foreach($arrFail as $ff):
              $r=$ff['raw']??(is_array($ff)?implode(' ',$ff):$ff);
          ?>
            <li class="list-group-item" style="white-space:pre-wrap;">
              <?=htmlspecialchars($r)?>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <p class="mt-3">
          <a href="index.php?page=rastreios&tab=import" class="btn btn-secondary">Voltar</a>
        </p>
      </form>
    </div>
    <script>
    function toggleMatchDetail(id){
      var d=document.getElementById(id);
      if(d){d.style.display=(d.style.display==='none'?'block':'none');}
    }
    </script>
    <?php
    include_once("footer.php");
    exit;
}

/****************************************************************************
 * 6) SALVAR RESULTADOS DA IMPORT
 ****************************************************************************/
if($action==='save_import_results' && $_SERVER['REQUEST_METHOD']==='POST'){
    $arr=$_POST['import']??[];
    $sqlTrack=$pdo->prepare("UPDATE orders SET tracking_code=? WHERE id=? LIMIT 1");
    $sqlGetAdm=$pdo->prepare("SELECT admin_comments FROM orders WHERE id=? LIMIT 1");
    $sqlUpdAdm=$pdo->prepare("UPDATE orders SET admin_comments=? WHERE id=? LIMIT 1");

    $countOk=0;
    foreach($arr as $i=>$item){
        $cod=trim($item['cod']??'');
        if(!$cod)continue;
        if(!isset($item['matches']))continue;

        $pids=$item['matches'];
        if(!is_array($pids)||!$pids)continue;

        $msg=trim($item['msg']??'');
        foreach($pids as $pidStr){
            $pid=(int)$pidStr;
            if($pid>0){
                try{
                    $sqlTrack->execute([$cod,$pid]);
                    $countOk++;
                }catch(Exception $e){}
                if($msg!==''){
                    $sqlGetAdm->execute([$pid]);
                    $old=$sqlGetAdm->fetchColumn()??'';
                    $sep=$old?"\n":""; 
                    $new=$old.$sep.$msg;
                    $sqlUpdAdm->execute([$new,$pid]);
                }
            }
        }
    }
    echo "<script>alert('Salvo rastreio em {$countOk} associações.'); window.location='index.php?page=rastreios&tab=unmatched';</script>";
    exit;
}

/****************************************************************************
 * 7) ENVIO DE TESTE
 ****************************************************************************/
$testSendResult = "";
if($action==='test_config_send' && $_SERVER['REQUEST_METHOD']==='POST'){
    $testPhone = trim($_POST['test_phone'] ?? '');
    if(!$testPhone){
        $testSendResult = "<div class='alert alert-danger mt-2'>Nenhum telefone informado!</div>";
    } else {
        // Exemplo substituindo placeholders
        $demoMsg = str_replace(['{NOME}','{CODIGO}'], ['Exemplo','AB123456BR'], $MENSAGEM_PADRAO);
        $demoMsg .= "\n(Este é um envio de teste.)";

        // Envia com orderId=null -> não grava log
        $res = enviarMensagemWhatsApp($testPhone, $demoMsg, $WASCRIPT_TOKEN, $pdo, null);
        if($res['httpCode'] === 200){
            $testSendResult = "<div class='alert alert-success mt-2'>Mensagem de teste enviada para <b>{$testPhone}</b> com sucesso!</div>";
        } else {
            $testSendResult = "<div class='alert alert-danger mt-2'>Falha ao enviar (HTTP {$res['httpCode']}). Telefone: <b>{$testPhone}</b></div>";
        }
    }
}

/****************************************************************************
 * CONTEÚDO PRINCIPAL (CONTENT)
 ****************************************************************************/
?>
<!-- Breadcrumb -->
<div class="container-fluid mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Rastreio de Entregas</li>
        </ol>
    </nav>
</div>

<!-- Conteúdo Principal -->
<div class="container-fluid">
    <h2 class="mb-4">Dashboard / Visão Geral</h2>

    <!-- Sub Menu -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?=($tab==='dashboard'?'active':'')?>" href="index.php?page=rastreios&tab=dashboard">Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?=($tab==='unmatched'?'active':'')?>" href="index.php?page=rastreios&tab=unmatched">Sem Rastreio</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?=($tab==='matched'?'active':'')?>" href="index.php?page=rastreios&tab=matched">Com Rastreio</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?=($tab==='import'?'active':'')?>" href="index.php?page=rastreios&tab=import">Importar</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?=($tab==='logs'?'active':'')?>" href="index.php?page=rastreios&tab=logs">Logs Envio</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?=($tab==='config'?'active':'')?>" href="index.php?page=rastreios&tab=config">Configurações</a>
        </li>
    </ul>

    <!-- Content -->
    <div class="container-fluid p-0">

        <?php if($tab==='dashboard'): ?>

          <?php
            // Exemplo de estatísticas
            try {
                $c1=$pdo->query("SELECT COUNT(*) FROM orders WHERE (tracking_code IS NULL OR tracking_code='')")->fetchColumn();
                $c2=$pdo->query("SELECT COUNT(*) FROM orders WHERE (tracking_code IS NOT NULL AND tracking_code<>'')")->fetchColumn();
                $c3=$pdo->query("SELECT COUNT(*) FROM orders WHERE status='CONCLUIDO'")->fetchColumn();
                $c4=$pdo->query("SELECT COUNT(*) FROM orders WHERE status='CANCELADO'")->fetchColumn();
                // Se quiser quantos foram "shipped=1":
                $c5 = 0;
                if ($hasShippedColumn) {
                    $c5=$pdo->query("SELECT COUNT(*) FROM orders WHERE shipped=1")->fetchColumn();
                }
            } catch (Exception $e) {
                $c1 = $c2 = $c3 = $c4 = $c5 = 0;
            }
          ?>
          
          <div class="row">
            <div class="col">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Sem Rastreio</h5>
                        <p class="card-text fs-4"><?=$c1?></p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Com Rastreio</h5>
                        <p class="card-text fs-4"><?=$c2?></p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Concluídos</h5>
                        <p class="card-text fs-4"><?=$c3?></p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Cancelados</h5>
                        <p class="card-text fs-4"><?=$c4?></p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">WhatsApp Enviados</h5>
                        <p class="card-text fs-4"><?=$c5?></p>
                    </div>
                </div>
            </div>
          </div>

          <p class="text-muted">Estatísticas gerais do sistema...</p>

        <?php elseif($tab==='unmatched'): ?>

          <?php
            $status=trim($_GET['status']??'');
            $nome=trim($_GET['nome']??'');
            $where=["(tracking_code IS NULL OR tracking_code='')"];
            $params=[];

            if($status!==''){
                $where[]="status=:st";
                $params[':st']=$status;
            }
            if($nome!==''){
                $where[]="customer_name LIKE :nm";
                $params[':nm']="%$nome%";
            }
            $whereSql=$where?'WHERE '.implode(' AND ',$where):'';

            $sql="SELECT id, customer_name, phone, email, address, number,
                         complement, city, state, cep, final_value, status, created_at,
                         shipped
                  FROM orders
                  $whereSql
                  ORDER BY id DESC
                  LIMIT 200";
            try {
                $stm=$pdo->prepare($sql);
                $stm->execute($params);
                $rows=$stm->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $rows = [];
            }
          ?>
          <h3>Pedidos Sem Rastreio</h3>
          <form method="GET" class="row g-3 align-items-end mb-3">
            <input type="hidden" name="page" value="rastreios">
            <input type="hidden" name="tab" value="unmatched">
            <div class="col-auto">
              <label>Status:</label>
              <select name="status" class="form-select">
                <option value="">--Todos--</option>
                <?php
                  $stt=['PENDENTE','CONFIRMADO','EM PROCESSO','CONCLUIDO','CANCELADO'];
                  foreach($stt as $s){
                      $sel=($status===$s)?'selected':'';
                      echo "<option value='{$s}' {$sel}>{$s}</option>";
                  }
                ?>
              </select>
            </div>
            <div class="col-auto">
              <label>Nome Cliente:</label>
              <input type="text" name="nome" class="form-control" value="<?=htmlspecialchars($nome)?>">
            </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
          </form>

          <form method="POST" action="index.php?page=rastreios&action=update_messages&tab=unmatched">
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Cidade/UF</th>
                    <th>Valor Final</th>
                    <th>Status</th>
                    <th>Data</th>
                    <th>Enviado?</th> <!-- NOVA COLUNA -->
                    <th>Ações</th>
                    <th>Mensagem p/ Cliente</th>
                  </tr>
                </thead>
                <tbody>
                <?php if(!$rows): ?>
                  <tr><td colspan="9" class="text-center text-muted">Nenhum pedido encontrado</td></tr>
                <?php else: ?>
                  <?php foreach($rows as $od):
                      $pid=(int)$od['id'];
                      $cuf=htmlspecialchars(($od['city']??'').'/'.($od['state']??''));
                      $fv=number_format($od['final_value']??0,2,',','.');
                      $shippedVal=(int)($od['shipped']??0);
                  ?>
                  <tr>
                    <td><?=$pid?></td>
                    <td><?=htmlspecialchars($od['customer_name']??'')?></td>
                    <td><?=$cuf?></td>
                    <td>R$ <?=$fv?></td>
                    <td>
                        <span class="badge <?=getStatusClass($od['status']??'')?>">
                            <?=htmlspecialchars($od['status']??"")?>
                        </span>
                    </td>
                    <td><?=htmlspecialchars($od['created_at']??'')?></td>
                    <td>
                      <?php if($shippedVal===1): ?>
                        <span class="badge bg-success">Enviado</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Não enviado</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-secondary" onclick="toggleDetails(<?=$pid?>)">Detalhes</button>
                      <a href="index.php?page=rastreios&action=edit&id=<?=$pid?>&tab=unmatched" class="btn btn-sm btn-primary">Editar Tracking</a>
                    </td>
                    <td>
                      <textarea name="messages[<?=$pid?>]" rows="2" class="form-control" placeholder="Mensagem p/ admin_comments..."></textarea>
                    </td>
                  </tr>
                  <tr class="details-row" id="details_<?=$pid?>">
                    <td colspan="9">
                      <div class="row">
                        <div class="col-md-6">
                          <ul class="list-unstyled">
                            <li><strong>Telefone:</strong> <?=htmlspecialchars($od['phone']??'')?></li>
                            <li><strong>Email:</strong> <?=htmlspecialchars($od['email']??'')?></li>
                            <li><strong>Endereço:</strong> <?=htmlspecialchars($od['address']??'')?>, <?=htmlspecialchars($od['number']??'')?></li>
                            <li><strong>Complemento:</strong> <?=htmlspecialchars($od['complement']??'')?></li>
                            <li><strong>CEP:</strong> <?=htmlspecialchars($od['cep']??'')?></li>
                          </ul>
                        </div>
                        <div class="col-md-6">
                          <ul class="list-unstyled">
                            <li><strong>Valor Final:</strong> R$ <?=$fv?></li>
                            <li><strong>Status:</strong> <?=htmlspecialchars($od['status']??'')?></li>
                            <li><strong>Data Criação:</strong> <?=htmlspecialchars($od['created_at']??'')?></li>
                          </ul>
                        </div>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach;?>
                <?php endif;?>
                </tbody>
              </table>
            </div>
            <button type="submit" class="btn btn-success mt-2">Salvar Mensagens</button>
          </form>

        <?php elseif($tab==='matched'): ?>

          <?php
            $status=trim($_GET['status']??'');
            $nome=trim($_GET['nome']??'');
            $page=(int)($_GET['p']??1);
            if($page<1)$page=1;
            $limit=50;
            $offset=($page-1)*$limit;

            $where=["(tracking_code IS NOT NULL AND tracking_code<>'')"];
            $params=[];

            if($status!==''){
                $where[]="status=:st";
                $params[':st']=$status;
            }
            if($nome!==''){
                $where[]="customer_name LIKE :nm";
                $params[':nm']="%$nome%";
            }
            $whereSql=$where?'WHERE '.implode(' AND ',$where):'';

            try {
                $countSql="SELECT COUNT(*) FROM orders $whereSql";
                $cStm=$pdo->prepare($countSql);
                $cStm->execute($params);
                $totalRows=(int)$cStm->fetchColumn();
                $totalPages=ceil($totalRows/$limit);

                $sql="SELECT id, customer_name, phone, tracking_code, city, state, final_value,
                             updated_at, created_at, shipped
                      FROM orders
                      $whereSql
                      ORDER BY updated_at DESC, id DESC
                      LIMIT :l OFFSET :o";
                $stm=$pdo->prepare($sql);
                foreach($params as $k=>$v){
                    $stm->bindValue($k,$v);
                }
                $stm->bindValue(':l',$limit,PDO::PARAM_INT);
                $stm->bindValue(':o',$offset,PDO::PARAM_INT);
                $stm->execute();
                $rows=$stm->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $rows = [];
                $totalRows = 0;
                $totalPages = 1;
            }

            $hoje=date('Y-m-d');
          ?>
          <h3>Pedidos Com Rastreio</h3>
          <form method="GET" class="row g-3 align-items-end mb-3">
            <input type="hidden" name="page" value="rastreios">
            <input type="hidden" name="tab" value="matched">
            <div class="col-auto">
              <label>Status:</label>
              <select name="status" class="form-select">
                <option value="">--Todos--</option>
                <?php
                  $stt=['PENDENTE','CONFIRMADO','EM PROCESSO','CONCLUIDO','CANCELADO'];
                  foreach($stt as $s){
                      $sel=($status===$s)?'selected':'';
                      echo "<option value='{$s}' {$sel}>{$s}</option>";
                  }
                ?>
              </select>
            </div>
            <div class="col-auto">
              <label>Nome Cliente:</label>
              <input type="text" name="nome" class="form-control" value="<?=htmlspecialchars($nome)?>">
            </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
          </form>

          <?php if($ENABLE_BULK_WHATSAPP): ?>
          <form method="POST" action="index.php?page=rastreios&action=send_whatsapp_bulk&tab=matched">
          <?php endif;?>
          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <?php if($ENABLE_BULK_WHATSAPP): ?>
                  <th>Sel</th>
                  <?php endif;?>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>Cidade/UF</th>
                  <th>Valor Final</th>
                  <th>Tracking</th>
                  <th>Status</th>
                  <th>Atualizado</th>
                  <th>Enviado?</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
              <?php if(!$rows): ?>
                <tr><td colspan="<?=($ENABLE_BULK_WHATSAPP?10:9)?>" class="text-center text-muted">Nenhum pedido encontrado</td></tr>
              <?php else: ?>
                <?php foreach($rows as $od):
                    $pid=(int)$od['id'];
                    $cuf=htmlspecialchars(($od['city']??'').'/'.($od['state']??''));
                    $fv=number_format($od['final_value']??0,2,',','.');
                    $trk=htmlspecialchars($od['tracking_code']??'');
                    $upd=($od['updated_at']??$od['created_at']??'');
                    $isToday=(substr($upd,0,10)===$hoje);
                    $rowClass=$isToday?'today-match':'';
                    $shippedVal = (int)($od['shipped'] ?? 0);
                ?>
                <tr class="<?=$rowClass?>">
                  <?php if($ENABLE_BULK_WHATSAPP): ?>
                  <td><input type="checkbox" name="ids[]" value="<?=$pid?>"></td>
                  <?php endif;?>
                  <td><?=$pid?></td>
                  <td><?=htmlspecialchars($od['customer_name']??'')?></td>
                  <td><?=$cuf?></td>
                  <td>R$ <?=$fv?></td>
                  <td><?=$trk?></td>
                  <td>
                      <span class="badge <?=getStatusClass($od['status']??'')?>">
                          <?=htmlspecialchars($od['status']??"")?>
                      </span>
                  </td>
                  <td><?=htmlspecialchars($upd)?></td>
                  <td>
                    <?php if($shippedVal===1): ?>
                      <span class="badge bg-success">Enviado</span>
                    <?php else: ?>
                      <span class="badge bg-danger">Pendente</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="index.php?page=rastreios&action=edit&id=<?=$pid?>&tab=matched" class="btn btn-sm btn-primary">Editar</a>
                    <a href="index.php?page=rastreios&action=undo&id=<?=$pid?>&tab=matched" class="btn btn-sm btn-danger"
                       onclick="return confirm('Remover tracking?');">Desfazer</a>
                    <br>
                    <select id="sel_<?=$pid?>" class="form-select form-select-sm d-inline-block mt-1" style="width:220px;">
                      <option value="">--Selecione--</option>
                      <option value="https://melhorrastreio.com.br/">Melhor Rastreio</option>
                      <option value="https://conecta.log.br/rastreio.php">Onlog</option>
                      <option value="https://www.loggi.com/rastreador/">Loggi</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-warning mt-1" onclick="reenvioWhatsApp(<?=$pid?>)">Enviar/Reenviar</button>
                  </td>
                </tr>
                <?php endforeach;?>
              <?php endif;?>
              </tbody>
            </table>
          </div>
          <?php if($ENABLE_BULK_WHATSAPP && !empty($rows)): ?>
            <button type="submit" class="btn btn-secondary mt-2">Enviar WhatsApp em Lote</button>
          <?php endif;?>
          <?php if($ENABLE_BULK_WHATSAPP): ?>
          </form>
          <?php endif;?>

          <?php if($totalPages>1): ?>
          <nav class="mt-3">
            <ul class="pagination justify-content-center">
              <?php if($page>1): ?>
              <li class="page-item">
                <a class="page-link" href="index.php?page=rastreios&tab=matched&p=<?=($page-1)?>&status=<?=urlencode($status)?>&nome=<?=urlencode($nome)?>">&laquo; Anterior</a>
              </li>
              <?php endif;?>
              <?php for($i=1;$i<=$totalPages;$i++): ?>
              <li class="page-item <?=($i==$page?'active':'')?>">
                <a class="page-link" href="index.php?page=rastreios&tab=matched&p=<?=$i?>&status=<?=urlencode($status)?>&nome=<?=urlencode($nome)?>"><?=$i?></a>
              </li>
              <?php endfor;?>
              <?php if($page<$totalPages): ?>
              <li class="page-item">
                <a class="page-link" href="index.php?page=rastreios&tab=matched&p=<?=($page+1)?>&status=<?=urlencode($status)?>&nome=<?=urlencode($nome)?>">Próximo &raquo;</a>
              </li>
              <?php endif;?>
            </ul>
          </nav>
          <?php endif;?>

        <?php elseif($tab==='import'): ?>

          <h3>Importar Rastreios</h3>
          <p>Escolha o formato e cole o texto a ser importado (bloco, planilha ou pares CEP/Código).</p>
          <form method="POST" action="index.php?page=rastreios&action=import_process&tab=import">
            <div class="mb-3">
              <label>Formato:</label>
              <select name="formato" class="form-select" required>
                <option value="planilha">Planilha (linhas com Objeto, Codigo, Nome etc.)</option>
                <option value="bloco">Bloco (NOME:, CIDADE:, CEP:, CODIGO:... ------)</option>
                <option value="novopar">Novo Formato (1 linha CEP, outra linha CÓDIGO)</option>
              </select>
            </div>
            <div class="mb-3">
              <label>Texto:</label>
              <textarea name="textoBruto" rows="10" class="form-control" placeholder="Cole aqui..." required></textarea>
            </div>
            <button type="submit" class="btn btn-success">Processar</button>
          </form>

        <?php elseif($tab==='logs'): ?>

          <h3>Logs de Envio WhatsApp</h3>
          <?php
            if(!tableExists($pdo,'whatsapp_logs')){
                echo '<div class="alert alert-warning">';
                echo '<p>A tabela <code>whatsapp_logs</code> não existe. Crie-a para registrar os envios.</p>';
                echo '<p>Execute esta SQL para criar a tabela:</p>';
                echo '<pre>CREATE TABLE `whatsapp_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `http_code` int(11) NOT NULL,
  `response` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</pre>';
                echo '</div>';
            } else {
                try {
                    $stm=$pdo->query("SELECT COUNT(*) FROM whatsapp_logs");
                    $totalLogs=(int)$stm->fetchColumn();
                    $page=(int)($_GET['p']??1); 
                    if($page<1)$page=1;
                    $limit=50;
                    $offset=($page-1)*$limit;
                    $totalPages=ceil($totalLogs/$limit);

                    $sqlLog="SELECT wl.*, o.customer_name, o.tracking_code
                             FROM whatsapp_logs wl
                             LEFT JOIN orders o ON o.id=wl.order_id
                             ORDER BY wl.id DESC
                             LIMIT :l OFFSET :o";
                    $stmtLog=$pdo->prepare($sqlLog);
                    $stmtLog->bindValue(':l',$limit,PDO::PARAM_INT);
                    $stmtLog->bindValue(':o',$offset,PDO::PARAM_INT);
                    $stmtLog->execute();
                    $logs=$stmtLog->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">Erro ao buscar logs: ' . $e->getMessage() . '</div>';
                    $logs = [];
                    $totalLogs = 0;
                    $totalPages = 1;
                }
                ?>
                <div class="table-responsive">
                  <table class="table table-bordered align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>ID</th>
                        <th>Pedido</th>
                        <th>Telefone</th>
                        <th>HTTP</th>
                        <th>Data</th>
                        <th>Mensagem</th>
                        <th>Resposta</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if(!$logs): ?>
                      <tr><td colspan="7" class="text-center text-muted">Nenhum log encontrado</td></tr>
                    <?php else: ?>
                      <?php foreach($logs as $lg):
                          $lid=(int)$lg['id'];
                          $oid=(int)$lg['order_id'];
                          $cname=$lg['customer_name']??'';
                          $trk=$lg['tracking_code']??'';
                      ?>
                      <tr>
                        <td><?=$lid?></td>
                        <td>#<?=$oid?> | <?=htmlspecialchars($cname)?>
                          <br><small><?=htmlspecialchars($trk)?></small>
                        </td>
                        <td><?=htmlspecialchars($lg['phone'])?></td>
                        <td><?=$lg['http_code']?></td>
                        <td><?=htmlspecialchars($lg['created_at'])?></td>
                        <td style="max-width:220px; white-space:pre-wrap;">
                          <?=htmlspecialchars($lg['message'])?>
                        </td>
                        <td style="max-width:220px; white-space:pre-wrap;">
                          <?=htmlspecialchars($lg['response'])?>
                        </td>
                      </tr>
                      <?php endforeach;?>
                    <?php endif;?>
                    </tbody>
                  </table>
                </div>

                <?php if($totalPages>1): ?>
                <nav class="mt-2">
                  <ul class="pagination justify-content-center">
                    <?php if($page>1): ?>
                    <li class="page-item">
                      <a class="page-link" href="index.php?page=rastreios&tab=logs&p=<?=($page-1)?>">&laquo; Anterior</a>
                    </li>
                    <?php endif;?>
                    <?php for($i=1;$i<=$totalPages;$i++): ?>
                    <li class="page-item <?=($i==$page?'active':'')?>">
                        <a class="page-link" href="index.php?page=rastreios&tab=logs&p=<?=$i?>"><?=$i?></a>
                    </li>
                    <?php endfor;?>
                    <?php if($page<$totalPages): ?>
                    <li class="page-item">
                      <a class="page-link" href="index.php?page=rastreios&tab=logs&p=<?=($page+1)?>">Próximo &raquo;</a>
                    </li>
                    <?php endif;?>
                  </ul>
                </nav>
                <?php endif; ?>
                <?php
            }
          ?>

        <?php elseif($tab==='config'): ?>

          <h3>Configurações do Sistema</h3>
          <?= $testSendResult ?>

          <?php
            if(!tableExists($pdo,'settings')){
                echo '<div class="alert alert-warning">';
                echo '<p>A tabela <code>settings</code> não existe no BD.</p>';
                echo '<p>Execute esta SQL para criar a tabela:</p>';
                echo '<pre>CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</pre>';
                echo '</div>';
            } else {
                if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['salvar_config'])){
                    $wascript = trim($_POST['wascript_token'] ?? '');
                    $mensagem = trim($_POST['whatsapp_message'] ?? '');
                    $link     = trim($_POST['link_rastreio_padrao'] ?? '');

                    try {
                        $sqlC="REPLACE INTO settings (config_key, config_value) VALUES (?,?)";
                        $stmC=$pdo->prepare($sqlC);

                        $stmC->execute(['wascript_token',$wascript]);
                        $stmC->execute(['whatsapp_message',$mensagem]);
                        $stmC->execute(['link_rastreio_padrao',$link]);

                        echo "<div class='alert alert-success'>Configurações salvas!</div>";

                        // Recarrega
                        $WASCRIPT_TOKEN       = getSetting($pdo, 'wascript_token', $WASCRIPT_TOKEN_DEFAULT);
                        $MENSAGEM_PADRAO      = getSetting($pdo, 'whatsapp_message', $MENSAGEM_PADRAO_DEFAULT);
                        $LINK_RASTREIO_PADRAO = getSetting($pdo, 'link_rastreio_padrao', $LINK_RASTREIO_PADRAO_DEFAULT);
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>Erro ao salvar configurações: " . $e->getMessage() . "</div>";
                    }
                }

                $wascript = getSetting($pdo,'wascript_token',$WASCRIPT_TOKEN_DEFAULT);
                $mensagem = getSetting($pdo,'whatsapp_message',$MENSAGEM_PADRAO_DEFAULT);
                $link     = getSetting($pdo,'link_rastreio_padrao',$LINK_RASTREIO_PADRAO_DEFAULT);
                ?>
                <form method="POST">
                  <div class="mb-3">
                    <label>Token Wascript (API WhatsApp):</label>
                    <input type="text" name="wascript_token" class="form-control"
                           value="<?=htmlspecialchars($wascript)?>">
                  </div>
                  <div class="mb-3">
                    <label>Mensagem Padrão WhatsApp (use {NOME} e {CODIGO}):</label>
                    <textarea name="whatsapp_message" rows="4" class="form-control"><?=htmlspecialchars($mensagem)?></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Link de Rastreio Padrão:</label>
                    <input type="text" name="link_rastreio_padrao" class="form-control"
                           value="<?=htmlspecialchars($link)?>">
                  </div>
                  <button type="submit" name="salvar_config" class="btn btn-primary">Salvar</button>
                </form>

                <hr>
                <h5 class="mt-4">Teste de Envio de Mensagem</h5>
                <p class="text-muted">Informe um telefone (ex.: <code>11999999999</code>) para testar o envio.</p>
                <form method="POST" action="index.php?page=rastreios&action=test_config_send&tab=config" class="row g-3 align-items-end">
                  <div class="col-auto">
                    <label>Telefone de teste:</label>
                    <input type="text" name="test_phone" class="form-control" placeholder="11999999999">
                  </div>
                  <div class="col-auto">
                    <button type="submit" class="btn btn-warning">Testar Mensagem</button>
                  </div>
                </form>
                <?php
            }
          ?>

        <?php endif; ?>

    </div>
</div>

<script>
// Script para manipulação das ações na interface
function toggleDetails(id){
  var d=document.getElementById('details_'+id);
  if(d)d.style.display=(d.style.display==='none'?'table-row':'none');
}
function reenvioWhatsApp(orderId){
  var sel=document.getElementById('sel_'+orderId);
  var baseLink=sel?sel.value:"";
  if(!baseLink) baseLink="<?= $LINK_RASTREIO_PADRAO ?>";
  if(confirm("Deseja enviar ou reenviar o código via WhatsApp com link:\n"+baseLink+"?")){
    window.location="index.php?page=rastreios&action=send_whatsapp&id="+orderId+"&tab=matched&base="+encodeURIComponent(baseLink);
  }
}
</script>