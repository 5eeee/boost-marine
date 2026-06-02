<?php
/**
 * miniapp.php — Telegram Mini App для Boost Marine
 * Полное управление: Работы, Товары, Команда, Услуги, Контакты, Статистика
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/metrica_api.php';

// ============ AJAX API ============
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];
    
    // Статистика для отправки в чат — требует авторизации
    if ($action === 'send_stats_to_chat') {
        if (!isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Не авторизован']);
            exit;
        }
        handleSendStatsToChat();
        exit;
    }
    
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Не авторизован']);
        exit;
    }
    
    switch ($action) {
        // === РАБОТЫ ===
        case 'works_list':
            $stmt = $pdo->query("SELECT w.*, (SELECT wi.image_path FROM work_images wi WHERE wi.work_id=w.id ORDER BY wi.sort_order,wi.id LIMIT 1) AS thumb FROM works w ORDER BY w.sort_order,w.id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'work_get':
            $id=(int)($_GET['id']??0);
            $w=$pdo->prepare("SELECT * FROM works WHERE id=?"); $w->execute([$id]); $work=$w->fetch(PDO::FETCH_ASSOC);
            if(!$work){echo json_encode(['error'=>'Не найдено']);break;}
            $im=$pdo->prepare("SELECT id,image_path,sort_order FROM work_images WHERE work_id=? ORDER BY sort_order,id"); $im->execute([$id]);
            $work['images']=$im->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($work);
            break;
        case 'work_save':
            requirePost(); requireCsrf();
            $id=(int)($_POST['id']??0); $vessel=trim($_POST['vessel']??''); $rt=trim($_POST['repair_type']??'');
            if(!$vessel||!$rt){echo json_encode(['error'=>'Заполните обязательные поля']);break;}
            $dur=trim($_POST['duration']??''); $desc=trim($_POST['description']??''); $so=(int)($_POST['sort_order']??0);
            if($id>0){$s=$pdo->prepare("UPDATE works SET vessel=?,repair_type=?,duration=?,description=?,sort_order=? WHERE id=?");$s->execute([$vessel,$rt,$dur,$desc,$so,$id]);}
            else{$s=$pdo->prepare("INSERT INTO works(vessel,repair_type,duration,description,sort_order)VALUES(?,?,?,?,?)");$s->execute([$vessel,$rt,$dur,$desc,$so]);$id=(int)$pdo->lastInsertId();}
            handleFileUploads('photos','work_images','work_id',$id,'uploads/works/','work_');
            echo json_encode(['ok'=>true,'id'=>$id]);
            break;
        case 'work_delete':
            requirePost(); deleteEntityWithImages('works',$_POST['id']??0,'work_images','work_id');
            break;
        // === ТОВАРЫ ===
        case 'products_list':
            $stmt=$pdo->query("SELECT p.*,(SELECT pi.image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) AS thumb FROM products p ORDER BY p.sort_order,p.id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'product_get':
            $id=(int)($_GET['id']??0);
            $p=$pdo->prepare("SELECT * FROM products WHERE id=?"); $p->execute([$id]); $prod=$p->fetch(PDO::FETCH_ASSOC);
            if(!$prod){echo json_encode(['error'=>'Не найдено']);break;}
            $im=$pdo->prepare("SELECT id,image_path,sort_order FROM product_images WHERE product_id=? ORDER BY sort_order,id"); $im->execute([$id]);
            $prod['images']=$im->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($prod);
            break;
        case 'product_save':
            requirePost(); requireCsrf();
            $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $cat=trim($_POST['category']??'all');
            if(!$name){echo json_encode(['error'=>'Введите название']);break;}
            $price=trim($_POST['price']??''); $desc=trim($_POST['description']??''); $so=(int)($_POST['sort_order']??0);
            if($id>0){$s=$pdo->prepare("UPDATE products SET name=?,description=?,price=?,category=?,sort_order=? WHERE id=?");$s->execute([$name,$desc,$price,$cat,$so,$id]);}
            else{$s=$pdo->prepare("INSERT INTO products(name,description,price,category,sort_order)VALUES(?,?,?,?,?)");$s->execute([$name,$desc,$price,$cat,$so]);$id=(int)$pdo->lastInsertId();}
            handleFileUploads('photos','product_images','product_id',$id,'uploads/products/','prod_');
            echo json_encode(['ok'=>true,'id'=>$id]);
            break;
        case 'product_delete':
            requirePost(); deleteEntityWithImages('products',$_POST['id']??0,'product_images','product_id');
            break;
        // === КОМАНДА ===
        case 'team_list':
            $stmt=$pdo->query("SELECT * FROM team_members ORDER BY sort_order,id"); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'team_save':
            requirePost(); requireCsrf();
            $id=(int)($_POST['id']??0); $so=(int)($_POST['sort_order']??0);
            if($id>0){
                $pdo->prepare("UPDATE team_members SET sort_order=? WHERE id=?")->execute([$so,$id]);
                if(isset($_FILES['photo'])&&$_FILES['photo']['error']===UPLOAD_ERR_OK){
                    $old=$pdo->prepare("SELECT image_path FROM team_members WHERE id=?"); $old->execute([$id]); $oi=$old->fetchColumn();
                    if($oi&&file_exists(__DIR__.'/'.$oi))@unlink(__DIR__.'/'.$oi);
                    $path=uploadSingleFile('photo','uploads/team/','team_');
                    if($path)$pdo->prepare("UPDATE team_members SET image_path=? WHERE id=?")->execute([$path,$id]);
                }
            } else {
                $path=uploadSingleFile('photo','uploads/team/','team_');
                if(!$path){echo json_encode(['error'=>'Загрузите фото']);break;}
                $pdo->prepare("INSERT INTO team_members(image_path,sort_order)VALUES(?,?)")->execute([$path,$so]);
                $id=(int)$pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
            break;
        case 'team_delete':
            requirePost();
            $id=(int)($_POST['id']??0);
            $old=$pdo->prepare("SELECT image_path FROM team_members WHERE id=?"); $old->execute([$id]);
            $path=$old->fetchColumn(); if($path&&file_exists(__DIR__.'/'.$path))@unlink(__DIR__.'/'.$path);
            $pdo->prepare("DELETE FROM team_members WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
            break;
        // === УСЛУГИ ===
        case 'services_list':
            $dirs=$pdo->query("SELECT * FROM service_directions ORDER BY sort_order,id")->fetchAll(PDO::FETCH_ASSOC);
            foreach($dirs as &$d){
                $s=$pdo->prepare("SELECT * FROM service_subsections WHERE direction_id=? ORDER BY position,id"); $s->execute([$d['id']]);
                $d['subsections']=$s->fetchAll(PDO::FETCH_ASSOC);
            } unset($d);
            echo json_encode($dirs);
            break;
        case 'direction_save':
            requirePost(); requireCsrf();
            $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $so=(int)($_POST['sort_order']??0);
            if(!$name){echo json_encode(['error'=>'Введите название']);break;}
            if($id>0){$pdo->prepare("UPDATE service_directions SET name=?,sort_order=? WHERE id=?")->execute([$name,$so,$id]);}
            else{$pdo->prepare("INSERT INTO service_directions(name,sort_order)VALUES(?,?)")->execute([$name,$so]);$id=(int)$pdo->lastInsertId();}
            echo json_encode(['ok'=>true,'id'=>$id]);
            break;
        case 'direction_delete':
            requirePost();
            $id=(int)($_POST['id']??0);
            // Удаляем файлы подразделов
            $subs=$pdo->prepare("SELECT image_path FROM service_subsections WHERE direction_id=?"); $subs->execute([$id]);
            foreach($subs->fetchAll() as $sub){if($sub['image_path']&&file_exists(__DIR__.'/'.$sub['image_path']))@unlink(__DIR__.'/'.$sub['image_path']);}
            $pdo->prepare("DELETE FROM service_subsections WHERE direction_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM service_directions WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
            break;
        case 'subsection_save':
            requirePost(); requireCsrf();
            $id=(int)($_POST['id']??0); $dirId=(int)($_POST['direction_id']??0); $name=trim($_POST['name']??'');
            $desc=trim($_POST['description']??''); $pos=(int)($_POST['position']??0);
            if(!$name||!$dirId){echo json_encode(['error'=>'Заполните поля']);break;}
            if($id>0){
                $pdo->prepare("UPDATE service_subsections SET direction_id=?,name=?,description=?,position=? WHERE id=?")->execute([$dirId,$name,$desc,$pos,$id]);
                if(isset($_FILES['photo'])&&$_FILES['photo']['error']===UPLOAD_ERR_OK){
                    $old=$pdo->prepare("SELECT image_path FROM service_subsections WHERE id=?"); $old->execute([$id]); $oi=$old->fetchColumn();
                    if($oi&&file_exists(__DIR__.'/'.$oi))@unlink(__DIR__.'/'.$oi);
                    $path=uploadSingleFile('photo','uploads/services/','svc_');
                    if($path)$pdo->prepare("UPDATE service_subsections SET image_path=? WHERE id=?")->execute([$path,$id]);
                }
            } else {
                $path=uploadSingleFile('photo','uploads/services/','svc_');
                if(!$path){echo json_encode(['error'=>'Загрузите фото']);break;}
                $pdo->prepare("INSERT INTO service_subsections(direction_id,name,description,image_path,position)VALUES(?,?,?,?,?)")->execute([$dirId,$name,$desc,$path,$pos]);
                $id=(int)$pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
            break;
        case 'subsection_delete':
            requirePost();
            $id=(int)($_POST['id']??0);
            $old=$pdo->prepare("SELECT image_path FROM service_subsections WHERE id=?"); $old->execute([$id]);
            $path=$old->fetchColumn(); if($path&&file_exists(__DIR__.'/'.$path))@unlink(__DIR__.'/'.$path);
            $pdo->prepare("DELETE FROM service_subsections WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
            break;
        // === КОНТАКТЫ ===
        case 'contacts_get':
            $s=$pdo->query("SELECT * FROM settings WHERE id=1"); echo json_encode($s->fetch(PDO::FETCH_ASSOC)?:new stdClass);
            break;
        case 'contacts_save':
            requirePost(); requireCsrf();
            $phone=trim($_POST['phone']??''); $tgCh=trim($_POST['telegram_channel_url']??'');
            $tgChat=trim($_POST['telegram_chat_url']??''); $wa=trim($_POST['whatsapp_url']??'');
            $addr=trim($_POST['address']??'');
            $pdo->prepare("UPDATE settings SET phone=?,telegram_channel_url=?,telegram_chat_url=?,whatsapp_url=?,address=? WHERE id=1")
                 ->execute([$phone,$tgCh,$tgChat,$wa,$addr]);
            echo json_encode(['ok'=>true]);
            break;
        // === СТАТИСТИКА ===
        case 'stats':
            $period=$_GET['period']??'week';
            $range=getDateRangeMA($period);
            $totals=metricaGetTotals($range[0],$range[1]);
            $sources=metricaGetSources($range[0],$range[1]);
            $pages=metricaGetPages($range[0],$range[1]);
            $devices=metricaGetDevices($range[0],$range[1]);
            echo json_encode(['period'=>$range,'totals'=>$totals,'sources'=>$sources,'pages'=>$pages,'devices'=>$devices]);
            break;
        // === УДАЛИТЬ ФОТО ===
        case 'image_delete':
            requirePost();
            $imgId=(int)($_POST['image_id']??0); $table=$_POST['table']??'work_images';
            $allowed=['work_images','product_images'];
            if(!in_array($table,$allowed)){echo json_encode(['error'=>'bad table']);break;}
            $s=$pdo->prepare("SELECT image_path FROM $table WHERE id=?"); $s->execute([$imgId]); $img=$s->fetch();
            if($img){$p=__DIR__.'/'.$img['image_path']; if(file_exists($p))@unlink($p); $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([$imgId]);}
            echo json_encode(['ok'=>true]);
            break;
        case 'reorder_images':
            requirePost(); requireCsrf();
            $table=$_POST['table']??'work_images';
            $allowed=['work_images','product_images'];
            if(!in_array($table,$allowed)){echo json_encode(['error'=>'bad table']);break;}
            $order=json_decode($_POST['order']??'[]',true);
            if(is_array($order)){
                $stmt=$pdo->prepare("UPDATE $table SET sort_order=? WHERE id=?");
                foreach($order as $i=>$imgId){$stmt->execute([$i,(int)$imgId]);}
            }
            echo json_encode(['ok'=>true]);
            break;
        default:
            echo json_encode(['error'=>'Unknown action']);
    }
    exit;
}

// ============ HELPERS ============
function requirePost(){if($_SERVER['REQUEST_METHOD']!=='POST'){echo json_encode(['error'=>'POST required']);exit;}}
function requireCsrf(){if(!isset($_POST['csrf_token'])||!verifyCsrfToken($_POST['csrf_token'])){echo json_encode(['error'=>'CSRF']);exit;}}

function handleFileUploads($field,$table,$fk,$entityId,$dir,$prefix){
    global $pdo;
    if(!isset($_FILES[$field]))return;
    $f=$_FILES[$field]; $cnt=is_array($f['name'])?count($f['name']):0;
    $targetDir=UPLOAD_DIR.basename($dir).'/';
    if(!is_dir($targetDir))mkdir($targetDir,0755,true);
    $allowedMimes=['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
    for($i=0;$i<$cnt;$i++){
        if($f['error'][$i]!==UPLOAD_ERR_OK||$f['size'][$i]>MAX_FILE_SIZE)continue;
        $ext=strtolower(pathinfo($f['name'][$i],PATHINFO_EXTENSION));
        if(!in_array($ext,ALLOWED_EXTENSIONS))continue;
        $finfo=finfo_open(FILEINFO_MIME_TYPE);
        $mime=finfo_file($finfo,$f['tmp_name'][$i]);
        finfo_close($finfo);
        if(!in_array($mime,$allowedMimes))continue;
        $newName=$prefix.uniqid().'_'.time().'_'.$i.'.'.$ext;
        if(move_uploaded_file($f['tmp_name'][$i],$targetDir.$newName)){
            $pdo->prepare("INSERT INTO $table ($fk,image_path,sort_order) VALUES(?,?,?)")->execute([$entityId,$dir.$newName,$i]);
        }
    }
}

function uploadSingleFile($field,$dir,$prefix){
    if(!isset($_FILES[$field])||$_FILES[$field]['error']!==UPLOAD_ERR_OK)return null;
    $f=$_FILES[$field];
    if($f['size']>MAX_FILE_SIZE)return null;
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,ALLOWED_EXTENSIONS))return null;
    $allowedMimes=['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
    $finfo=finfo_open(FILEINFO_MIME_TYPE);
    $mime=finfo_file($finfo,$f['tmp_name']);
    finfo_close($finfo);
    if(!in_array($mime,$allowedMimes))return null;
    $targetDir=UPLOAD_DIR.basename($dir).'/';
    if(!is_dir($targetDir))mkdir($targetDir,0755,true);
    $newName=$prefix.uniqid().'_'.time().'.'.$ext;
    if(move_uploaded_file($f['tmp_name'],$targetDir.$newName))return $dir.$newName;
    return null;
}

function deleteEntityWithImages($table,$id,$imgTable,$fk){
    global $pdo;
    $id=(int)$id;
    $imgs=$pdo->prepare("SELECT image_path FROM $imgTable WHERE $fk=?"); $imgs->execute([$id]);
    foreach($imgs->fetchAll() as $img){$p=__DIR__.'/'.$img['image_path']; if(file_exists($p))@unlink($p);}
    $pdo->prepare("DELETE FROM $imgTable WHERE $fk=?")->execute([$id]);
    $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);
}

function getDateRangeMA($period){
    switch($period){
        case 'today':return[date('Y-m-d'),date('Y-m-d')];
        case 'yesterday':$d=date('Y-m-d',strtotime('-1 day'));return[$d,$d];
        case 'month':return[date('Y-m-d',strtotime('-30 days')),date('Y-m-d')];
        case 'quarter':return[date('Y-m-d',strtotime('-90 days')),date('Y-m-d')];
        default:return[date('Y-m-d',strtotime('-7 days')),date('Y-m-d')];
    }
}

function handleSendStatsToChat(){
    $chatId=(int)($_POST['chat_id']??0);
    $period=$_POST['period']??'week';
    if(!$chatId){echo json_encode(['error'=>'No chat_id']);return;}
    $range=getDateRangeMA($period);
    $t=metricaGetTotals($range[0],$range[1]);
    $sources=metricaGetSources($range[0],$range[1]);
    $pages=metricaGetPages($range[0],$range[1]);
    $labels=['today'=>'Сегодня','yesterday'=>'Вчера','week'=>'За неделю','month'=>'За месяц','quarter'=>'За квартал'];
    $label=$labels[$period]??'Период';
    $caption="📊 Статистика — {$label}\n📅 {$range[0]} — {$range[1]}\n\n";
    $caption.="👀 Визиты: {$t['visits']}\n";
    $caption.="👤 Посетители: {$t['users']}\n";
    $caption.="📄 Просмотры: {$t['pageviews']}\n";
    $caption.="📊 Отказы: {$t['bounceRate']}%\n";
    $caption.="⏱ Ср. время: ".($t['avgDuration']>0?gmdate('i:s',$t['avgDuration']):'—')."\n";
    $caption.="📚 Глубина: {$t['pageDepth']}";
    // Если есть скриншот — отправляем как фото
    if(isset($_FILES['screenshot'])&&$_FILES['screenshot']['error']===UPLOAD_ERR_OK){
        $tmpFile=$_FILES['screenshot']['tmp_name'];
        $apiUrl='https://api.telegram.org/bot'.TG_BOT_TOKEN.'/sendPhoto';
        $ch=curl_init($apiUrl);
        $postFields=['chat_id'=>$chatId,'caption'=>$caption,'photo'=>new CURLFile($tmpFile,'image/png','stats.png')];
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$postFields);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_TIMEOUT,30);
        $res=json_decode(curl_exec($ch),true);
        curl_close($ch);
        // Также отправляем детальный текст отдельным сообщением
        $text="<b>📊 Детальная статистика — {$label}</b>\n📅 {$range[0]} — {$range[1]}\n";
        if(!empty($sources)){$text.="\n<b>🔗 Источники:</b>\n";foreach(array_slice($sources,0,5) as $s){$text.="  • ".mb_substr($s['source'],0,25)." — {$s['visits']}\n";}}
        if(!empty($pages)){$text.="\n<b>📄 Топ страницы:</b>\n";foreach(array_slice($pages,0,5) as $p){$text.="  • ".mb_substr($p['url'],0,30)." — {$p['pageviews']}\n";}}
        $ch2=curl_init('https://api.telegram.org/bot'.TG_BOT_TOKEN.'/sendMessage');
        curl_setopt($ch2,CURLOPT_POST,1);
        curl_setopt($ch2,CURLOPT_POSTFIELDS,json_encode(['chat_id'=>$chatId,'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true]));
        curl_setopt($ch2,CURLOPT_HTTPHEADER,['Content-Type: application/json']);
        curl_setopt($ch2,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch2,CURLOPT_TIMEOUT,10);
        curl_exec($ch2);curl_close($ch2);
        echo json_encode(['ok'=>$res['ok']??false]);
    } else {
        // Фоллбэк — просто текст
        $apiUrl='https://api.telegram.org/bot'.TG_BOT_TOKEN.'/sendMessage';
        $ch=curl_init($apiUrl);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode(['chat_id'=>$chatId,'text'=>$caption,'parse_mode'=>'HTML','disable_web_page_preview'=>true]));
        curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/json']);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_TIMEOUT,10);
        $res=json_decode(curl_exec($ch),true);
        curl_close($ch);
        echo json_encode(['ok'=>$res['ok']??false]);
    }
}

// ============ LOGIN ============
$loginError='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['miniapp_login'])){
    if(!isset($_POST['csrf_token'])||!verifyCsrfToken($_POST['csrf_token'])){$loginError='Ошибка безопасности';}
    else{
        $login=trim($_POST['login']??''); $password=$_POST['password']??'';
        $stmt=$pdo->prepare("SELECT id,password_hash FROM users WHERE login=? OR email=?"); $stmt->execute([$login,$login]); $user=$stmt->fetch();
        if($user&&password_verify($password,$user['password_hash'])){$_SESSION['user_id']=$user['id'];session_regenerate_id(true);header('Location: '.BASE_URL.'miniapp.php');exit;}
        else{$loginError='Неверный логин или пароль';}
    }
}
$csrfToken=generateCsrfToken(); $isAuth=isAuthenticated();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Boost Marine — Mini App</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<style>
:root{--bg:var(--tg-theme-bg-color,#0a0e17);--text:var(--tg-theme-text-color,#e0e6f0);--hint:var(--tg-theme-hint-color,#7a8ba7);--link:var(--tg-theme-link-color,#5ba0d6);--btn:var(--tg-theme-button-color,#00d4ff);--btn-text:var(--tg-theme-button-text-color,#000);--sec:var(--tg-theme-secondary-bg-color,#111827);--accent:#00d4ff;--danger:#ff4757;--success:#2ed573;--r:14px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Montserrat',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased;padding-bottom:90px}
/* Login */
.login-screen{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:24px}
.login-logo{font-size:28px;font-weight:800;letter-spacing:2px;margin-bottom:8px;color:var(--accent)}
.login-sub{color:var(--hint);font-size:13px;margin-bottom:32px}
.login-form{width:100%;max-width:340px}
.login-form input{width:100%;padding:14px 16px;margin-bottom:12px;background:var(--sec);border:1px solid rgba(255,255,255,.08);border-radius:var(--r);color:var(--text);font-size:15px;font-family:inherit;outline:none}
.login-form input:focus{border-color:var(--accent)}
.login-form button{width:100%;padding:14px;background:var(--btn);color:var(--btn-text);border:none;border-radius:var(--r);font-size:15px;font-weight:600;font-family:inherit;cursor:pointer}
.login-error{color:var(--danger);font-size:13px;margin-bottom:12px;text-align:center}
/* Tab Bar */
.tab-bar{position:fixed;bottom:0;left:0;right:0;background:var(--sec);display:flex;border-top:1px solid rgba(255,255,255,.06);z-index:200;padding:8px 0;padding-bottom:calc(14px + env(safe-area-inset-bottom,14px))}
.tab-bar a{flex:1;display:flex;flex-direction:column;align-items:center;padding:6px 0;font-size:10px;color:var(--hint);text-decoration:none;transition:.2s;gap:2px}
.tab-bar a i{font-size:18px}
.tab-bar a.active{color:var(--accent)}
/* Header */
.app-header{position:sticky;top:0;z-index:100;background:var(--sec);padding:12px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.06)}
.app-header h1{font-size:16px;font-weight:700;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.btn-icon{width:38px;height:38px;border-radius:50%;border:none;background:rgba(255,255,255,.06);color:var(--text);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px}
.btn-back{font-size:20px}
/* Cards */
.card-list{padding:12px}
.card{background:var(--sec);border-radius:var(--r);margin-bottom:10px;overflow:hidden;cursor:pointer;display:flex;align-items:stretch;border:1px solid rgba(255,255,255,.04);transition:.15s}
.card:active{transform:scale(.98);opacity:.9}
.card__thumb{width:80px;min-height:72px;object-fit:cover;flex-shrink:0;background:#1a2332}
.card__info{padding:10px 12px;flex:1;min-width:0}
.card__title{font-size:14px;font-weight:600;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card__sub{font-size:12px;color:var(--hint);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.empty{text-align:center;padding:60px 20px;color:var(--hint)}
.empty i{font-size:44px;margin-bottom:10px;display:block}
/* FAB */
.fab{position:fixed;bottom:100px;right:20px;width:52px;height:52px;background:var(--btn);color:var(--btn-text);border:none;border-radius:50%;font-size:26px;cursor:pointer;box-shadow:0 4px 16px rgba(0,212,255,.3);z-index:50;display:flex;align-items:center;justify-content:center}
/* Top links */
.top-links{display:flex;gap:8px;padding:8px 12px;background:var(--sec);border-bottom:1px solid rgba(255,255,255,.06)}
.top-links a{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:rgba(255,255,255,.04);border-radius:10px;color:var(--hint);font-size:12px;font-weight:500;text-decoration:none;transition:.2s}
.top-links a:active{transform:scale(.96);background:rgba(255,255,255,.08)}
/* Form */
.form-screen{padding:12px 16px 100px}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:11px;font-weight:600;color:var(--hint);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px}
.form-group input,.form-group textarea,.form-group select{width:100%;padding:12px 14px;background:var(--sec);border:1px solid rgba(255,255,255,.08);border-radius:12px;color:var(--text);font-size:14px;font-family:inherit;outline:none;resize:vertical}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--accent)}
.form-group textarea{min-height:90px}
.form-group select{appearance:none;-webkit-appearance:none}
.form-group select option{background:var(--sec);color:var(--text)}
/* Photos */
.photos-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.photo-thumb{width:72px;height:72px;border-radius:10px;overflow:hidden;position:relative;background:#1a2332;cursor:grab;touch-action:none}
.photo-thumb.sortable-chosen{opacity:.7;cursor:grabbing}
.photo-ghost{opacity:.4}
.photos-sortable{display:flex;flex-wrap:wrap;gap:8px}
.photo-thumb img{width:100%;height:100%;object-fit:cover}
.photo-thumb__del{position:absolute;top:2px;right:2px;width:20px;height:20px;border-radius:50%;background:var(--danger);color:#fff;border:none;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.photo-upload-btn{width:72px;height:72px;border-radius:10px;background:rgba(255,255,255,.04);border:2px dashed rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:24px;color:var(--hint)}
/* Buttons */
.btn-primary{width:100%;padding:14px;background:var(--btn);color:var(--btn-text);border:none;border-radius:var(--r);font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;margin-top:8px}
.btn-primary:disabled{opacity:.5}
.btn-danger{width:100%;padding:12px;background:transparent;color:var(--danger);border:1px solid var(--danger);border-radius:var(--r);font-size:14px;font-family:inherit;cursor:pointer;margin-top:8px}
.btn-outline{width:100%;padding:12px;background:transparent;color:var(--accent);border:1px solid var(--accent);border-radius:var(--r);font-size:14px;font-family:inherit;cursor:pointer;margin-top:8px}
/* Stats */
.stats-screen{padding:12px 16px 24px}
.kpi-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}
.kpi-card{background:var(--sec);border-radius:12px;padding:14px;text-align:center;border:1px solid rgba(255,255,255,.04)}
.kpi-card__val{font-size:22px;font-weight:700;color:var(--accent)}
.kpi-card__label{font-size:11px;color:var(--hint);margin-top:2px}
.stat-section{margin-bottom:16px}
.stat-section h3{font-size:13px;font-weight:600;margin-bottom:8px;color:var(--hint)}
.stat-row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:13px}
.stat-row span:first-child{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-right:8px}
.stat-row span:last-child{font-weight:600;flex-shrink:0}
.period-tabs{display:flex;gap:6px;margin-bottom:14px;overflow-x:auto}
.period-tab{padding:7px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.1);background:transparent;color:var(--text);cursor:pointer;white-space:nowrap}
.period-tab.active{background:var(--accent);color:var(--btn-text);border-color:var(--accent)}
.send-chat-btn{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;padding:12px;background:rgba(0,212,255,.12);border:1px solid var(--accent);border-radius:var(--r);color:var(--accent);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit}
.send-chat-btn i{font-size:16px}
/* Detail */
.detail-screen{padding:0 0 20px}
.detail-images{width:100%;overflow-x:auto;display:flex;gap:2px;scroll-snap-type:x mandatory}
.detail-images img{width:100%;max-height:260px;object-fit:cover;flex-shrink:0;scroll-snap-align:start}
.detail-body{padding:14px 16px}
.detail-body h2{font-size:17px;margin-bottom:10px}
.detail-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px}
.detail-row span:first-child{color:var(--hint)}
.detail-actions{padding:0 16px;display:flex;gap:8px}
.detail-actions button{flex:1}
/* Loading */
.loading{display:flex;align-items:center;justify-content:center;padding:40px}
.spinner{width:28px;height:28px;border:3px solid rgba(255,255,255,.1);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
/* Toast */
.toast{position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:#222;color:#fff;padding:10px 20px;border-radius:20px;font-size:13px;z-index:999;opacity:0;transition:.3s;pointer-events:none}
.toast.show{opacity:1}
.hidden{display:none!important}
/* Services tree */
.svc-dir{background:var(--sec);border-radius:var(--r);margin-bottom:10px;border:1px solid rgba(255,255,255,.04);overflow:hidden}
.svc-dir__header{padding:12px 14px;display:flex;align-items:center;justify-content:space-between;cursor:pointer}
.svc-dir__header h3{font-size:14px;font-weight:600;flex:1}
.svc-dir__actions{display:flex;gap:6px}
.svc-dir__actions button{width:28px;height:28px;border-radius:50%;border:none;background:rgba(255,255,255,.06);color:var(--text);font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.svc-sub{padding:8px 14px;border-top:1px solid rgba(255,255,255,.04);display:flex;align-items:center;gap:10px}
.svc-sub img{width:40px;height:40px;border-radius:8px;object-fit:cover}
.svc-sub__info{flex:1;min-width:0}
.svc-sub__name{font-size:13px;font-weight:500}
.svc-sub__desc{font-size:11px;color:var(--hint);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.svc-sub__del{width:24px;height:24px;border-radius:50%;border:none;background:rgba(255,71,87,.15);color:var(--danger);font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center}
</style>
</head>
<body>

<?php if(!$isAuth): ?>
<div class="login-screen">
    <div class="login-logo">boostmarine</div>
    <div class="login-sub">Панель управления</div>
    <form class="login-form" method="POST">
        <input type="hidden" name="miniapp_login" value="1">
        <input type="hidden" name="csrf_token" value="<?=e($csrfToken)?>">
        <?php if($loginError):?><div class="login-error"><?=e($loginError)?></div><?php endif;?>
        <input type="text" name="login" placeholder="Логин" autocomplete="username" required>
        <input type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
        <button type="submit">Войти</button>
    </form>
</div>
<?php else: ?>

<!-- TOP LINKS BAR -->
<div class="top-links">
    <a href="#" onclick="goToBot(); return false;"><i class="fab fa-telegram"></i> Бот</a>
    <a href="https://boostmarine.ru/" target="_blank"><i class="fas fa-globe"></i> Сайт</a>
    <a href="https://admin.boostmarine.ru/" target="_blank"><i class="fas fa-lock"></i> Админка</a>
</div>

<!-- TAB BAR -->
<nav class="tab-bar" id="tabBar">
    <a href="#" onclick="switchTab('works')" data-tab="works" class="active"><i class="fas fa-briefcase"></i>Работы</a>
    <a href="#" onclick="switchTab('products')" data-tab="products"><i class="fas fa-box"></i>Товары</a>
    <a href="#" onclick="switchTab('team')" data-tab="team"><i class="fas fa-users"></i>Команда</a>
    <a href="#" onclick="switchTab('services')" data-tab="services"><i class="fas fa-cogs"></i>Услуги</a>
    <a href="#" onclick="switchTab('contacts')" data-tab="contacts"><i class="fas fa-phone"></i>Контакты</a>
    <a href="#" onclick="switchTab('stats')" data-tab="stats"><i class="fas fa-chart-bar"></i>Стат-ка</a>
</nav>

<!-- ===== WORKS SCREENS ===== -->
<div id="tab-works">
    <div id="worksList-screen">
        <div class="app-header"><h1>📋 Работы</h1><button class="btn-icon" onclick="loadWorks()"><i class="fas fa-sync-alt"></i></button></div>
        <div class="card-list" id="worksList"><div class="loading"><div class="spinner"></div></div></div>
        <button class="fab" onclick="openWorkForm()">+</button>
    </div>
    <div id="workDetail-screen" class="hidden">
        <div class="app-header"><button class="btn-icon btn-back" onclick="backToWorksList()">←</button><h1 id="workDetailTitle">Работа</h1></div>
        <div class="detail-screen" id="workDetailContent"></div>
    </div>
    <div id="workForm-screen" class="hidden">
        <div class="app-header"><button class="btn-icon btn-back" onclick="backToWorksList()">←</button><h1 id="workFormTitle">Новая работа</h1></div>
        <div class="form-screen">
            <input type="hidden" id="wId" value="0">
            <div class="form-group"><label>Судно *</label><input id="wVessel" placeholder="Название судна"></div>
            <div class="form-group"><label>Тип ремонта *</label><input id="wRepair" placeholder="Тип ремонта"></div>
            <div class="form-group"><label>Срок</label><input id="wDuration" placeholder="Напр.: 2 недели"></div>
            <div class="form-group"><label>Описание</label><textarea id="wDesc" placeholder="Описание..."></textarea></div>
            <div class="form-group"><label>Сортировка</label><input id="wSort" type="number" value="0"></div>
            <div class="form-group"><label>Фотографии</label><div class="photos-grid" id="wPhotos"></div></div>
            <button class="btn-primary" id="wSaveBtn" onclick="saveWork()">Сохранить</button>
            <button class="btn-danger hidden" id="wDelBtn" onclick="deleteEntity('work_delete','wId',loadWorks)">Удалить</button>
        </div>
    </div>
</div>

<!-- ===== PRODUCTS SCREENS ===== -->
<div id="tab-products" class="hidden">
    <div id="productsList-screen">
        <div class="app-header"><h1>🛒 Товары</h1><button class="btn-icon" onclick="loadProducts()"><i class="fas fa-sync-alt"></i></button></div>
        <div class="card-list" id="productsList"><div class="loading"><div class="spinner"></div></div></div>
        <button class="fab" onclick="openProductForm()">+</button>
    </div>
    <div id="productDetail-screen" class="hidden">
        <div class="app-header"><button class="btn-icon btn-back" onclick="backToProductsList()">←</button><h1 id="productDetailTitle">Товар</h1></div>
        <div class="detail-screen" id="productDetailContent"></div>
    </div>
    <div id="productForm-screen" class="hidden">
        <div class="app-header"><button class="btn-icon btn-back" onclick="backToProductsList()">←</button><h1 id="productFormTitle">Новый товар</h1></div>
        <div class="form-screen">
            <input type="hidden" id="pId" value="0">
            <div class="form-group"><label>Название *</label><input id="pName" placeholder="Название товара"></div>
            <div class="form-group"><label>Категория</label><select id="pCat"><option value="all">Все</option><option value="parts">Запчасти</option><option value="equipment">Оборудование</option></select></div>
            <div class="form-group"><label>Цена</label><input id="pPrice" placeholder="Напр.: 12000"></div>
            <div class="form-group"><label>Описание</label><textarea id="pDesc" placeholder="Описание..."></textarea></div>
            <div class="form-group"><label>Сортировка</label><input id="pSort" type="number" value="0"></div>
            <div class="form-group"><label>Фотографии</label><div class="photos-grid" id="pPhotos"></div></div>
            <button class="btn-primary" id="pSaveBtn" onclick="saveProduct()">Сохранить</button>
            <button class="btn-danger hidden" id="pDelBtn" onclick="deleteEntity('product_delete','pId',loadProducts)">Удалить</button>
        </div>
    </div>
</div>

<!-- ===== TEAM SCREENS ===== -->
<div id="tab-team" class="hidden">
    <div id="teamList-screen">
        <div class="app-header"><h1>👥 Команда</h1><button class="btn-icon" onclick="loadTeam()"><i class="fas fa-sync-alt"></i></button></div>
        <div class="card-list" id="teamList"><div class="loading"><div class="spinner"></div></div></div>
        <button class="fab" onclick="openTeamForm()">+</button>
    </div>
    <div id="teamForm-screen" class="hidden">
        <div class="app-header"><button class="btn-icon btn-back" onclick="backToTeamList()">←</button><h1 id="teamFormTitle">Участник</h1></div>
        <div class="form-screen">
            <input type="hidden" id="tId" value="0">
            <div class="form-group"><label>Фото *</label><div class="photos-grid" id="tPhoto"></div></div>
            <div class="form-group"><label>Сортировка</label><input id="tSort" type="number" value="0"></div>
            <button class="btn-primary" onclick="saveTeam()">Сохранить</button>
            <button class="btn-danger hidden" id="tDelBtn" onclick="deleteTeamMember()">Удалить</button>
        </div>
    </div>
</div>

<!-- ===== SERVICES SCREENS ===== -->
<div id="tab-services" class="hidden">
    <div id="servicesList-screen">
        <div class="app-header"><h1>⚙️ Услуги</h1><button class="btn-icon" onclick="loadServices()"><i class="fas fa-sync-alt"></i></button></div>
        <div class="card-list" id="servicesList"><div class="loading"><div class="spinner"></div></div></div>
        <button class="fab" onclick="openDirForm()">+</button>
    </div>
    <div id="svcForm-screen" class="hidden">
        <div class="app-header"><button class="btn-icon btn-back" onclick="backToSvcList()">←</button><h1 id="svcFormTitle">Направление</h1></div>
        <div class="form-screen" id="svcFormContent"></div>
    </div>
</div>

<!-- ===== CONTACTS SCREEN ===== -->
<div id="tab-contacts" class="hidden">
    <div class="app-header"><h1>📞 Контакты</h1></div>
    <div class="form-screen">
        <div class="form-group"><label>Телефон</label><input id="cPhone" placeholder="+7 (...)"></div>
        <div class="form-group"><label>Telegram канал</label><input id="cTgChannel" placeholder="https://t.me/..."></div>
        <div class="form-group"><label>Telegram чат</label><input id="cTgChat" placeholder="https://t.me/..."></div>
        <div class="form-group"><label>WhatsApp</label><input id="cWa" placeholder="https://wa.me/..."></div>
        <div class="form-group"><label>Адрес</label><textarea id="cAddr" placeholder="Адрес..."></textarea></div>
        <button class="btn-primary" onclick="saveContacts()">Сохранить</button>
    </div>
</div>

<!-- ===== STATS SCREEN ===== -->
<div id="tab-stats" class="hidden">
    <div class="app-header"><h1>📊 Статистика</h1></div>
    <div class="stats-screen">
        <div class="period-tabs" id="periodTabs">
            <button class="period-tab" data-p="today" onclick="loadStats('today')">Сегодня</button>
            <button class="period-tab" data-p="yesterday" onclick="loadStats('yesterday')">Вчера</button>
            <button class="period-tab active" data-p="week" onclick="loadStats('week')">Неделя</button>
            <button class="period-tab" data-p="month" onclick="loadStats('month')">Месяц</button>
            <button class="period-tab" data-p="quarter" onclick="loadStats('quarter')">Квартал</button>
        </div>
        <div id="statsContent"><div class="loading"><div class="spinner"></div></div></div>
        <button class="send-chat-btn" onclick="sendStatsToChat()"><i class="fab fa-telegram"></i> Отправить в чат</button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const B='<?=e(BASE_URL)?>';
const CSRF='<?=e($csrfToken)?>';
const tg=window.Telegram?.WebApp;
let chatId=null;
if(tg){tg.ready();tg.expand();try{tg.headerColor='#111827';tg.backgroundColor='#0a0e17';}catch(e){} chatId=tg.initDataUnsafe?.user?.id||null;}

function goToBot(){
    if(tg){tg.close();}
    else{window.location.href='https://t.me/BoostMarineAdmin_bot';}
}

let currentTab='works';
let currentPeriod='week';
let newFiles=[];
let existingImgs=[];
let currentGridId='';
let currentImgTable='';
let currentMulti=true;

// ===== TAB SWITCHING =====
function switchTab(tab){
    document.querySelectorAll('[id^="tab-"]').forEach(el=>el.classList.add('hidden'));
    document.getElementById('tab-'+tab).classList.remove('hidden');
    document.querySelectorAll('.tab-bar a').forEach(a=>a.classList.toggle('active',a.dataset.tab===tab));
    currentTab=tab;
    // Reset sub-screens
    if(tab==='works'){backToWorksList();}
    if(tab==='products'){backToProductsList();}
    if(tab==='team'){backToTeamList();}
    if(tab==='services'){backToSvcList();}
    if(tab==='contacts'){loadContacts();}
    if(tab==='stats'){loadStats(currentPeriod);}
    if(tg)tg.BackButton.hide();
    return false;
}

// ===== GENERIC HELPERS =====
function api(action,params=''){return fetch(B+'miniapp.php?action='+action+params).then(r=>r.json());}
function apiPost(action,body){return fetch(B+'miniapp.php?action='+action,{method:'POST',body}).then(r=>r.json());}
function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500);}
function haptic(type){try{tg?.HapticFeedback?.notificationOccurred(type);}catch(e){}}

function deleteEntity(action,idField,callback){
    const id=document.getElementById(idField).value;
    if(!id||id==='0')return;
    if(!confirm('Удалить? Действие необратимо.'))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    apiPost(action,fd).then(r=>{if(r.ok){toast('Удалено');haptic('warning');callback();}});
}

function renderPhotoGrid(containerId,images,imgTable,acceptMultiple){
    existingImgs=images||[];currentGridId=containerId;currentImgTable=imgTable;currentMulti=!!acceptMultiple;
    redrawPhotos();
}
function redrawPhotos(){
    const grid=document.getElementById(currentGridId);if(!grid)return;
    let h='<div class="photos-sortable" id="photosSortable">';
    existingImgs.forEach(img=>{
        h+=`<div class="photo-thumb" data-id="${img.id}"><img src="${esc(B+img.image_path)}"><button class="photo-thumb__del" onclick="delImage(${img.id},'${currentImgTable}',this,${existingImgs.indexOf(img)})">×</button></div>`;
    });
    newFiles.forEach((f,i)=>{
        h+=`<div class="photo-thumb"><img src="${URL.createObjectURL(f)}"><button class="photo-thumb__del" onclick="rmNewFile(${i})">×</button></div>`;
    });
    h+='</div>';
    const multi=currentMulti?'multiple':'';
    h+=`<label class="photo-upload-btn" style="margin-top:8px">+<input type="file" accept="image/*" ${multi} hidden onchange="addNewFiles(this.files)"></label>`;
    grid.innerHTML=h;
    // Init drag & drop sorting
    const sortEl=document.getElementById('photosSortable');
    if(sortEl && existingImgs.length>1 && typeof Sortable!=='undefined'){
        Sortable.create(sortEl,{animation:150,ghostClass:'photo-ghost',delay:200,delayOnTouchOnly:true,
            onEnd:function(){
                const ids=[...sortEl.querySelectorAll('.photo-thumb[data-id]')].map(el=>el.dataset.id);
                if(ids.length&&currentImgTable){
                    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('table',currentImgTable);fd.append('order',JSON.stringify(ids));
                    apiPost('reorder_images',fd).then(r=>{if(r.ok){toast('Порядок сохранён');haptic('success');}});
                }
            }
        });
    }
}
function addNewFiles(files){
    for(const f of files){if(f.size>5*1024*1024){toast('Макс 5 МБ');continue;} if(!currentMulti){newFiles=[];} newFiles.push(f);}
    redrawPhotos();
}
function rmNewFile(i){newFiles.splice(i,1);redrawPhotos();}
function delImage(imgId,table,btn,idx){
    if(!confirm('Удалить фото?'))return;
    if(table){
        const fd=new FormData();fd.append('image_id',imgId);fd.append('table',table);fd.append('csrf_token',CSRF);
        apiPost('image_delete',fd).then(r=>{if(r.ok){existingImgs.splice(idx,1);redrawPhotos();}});
    } else {
        // Team / subsections: image is part of entity, just remove visual
        existingImgs.splice(idx,1);redrawPhotos();
    }
}

function showSubScreen(tab,screen){
    document.querySelectorAll('#tab-'+tab+' > div').forEach(d=>d.classList.add('hidden'));
    document.getElementById(screen).classList.remove('hidden');
    if(tg&&screen.includes('Form')||screen.includes('Detail')){tg.BackButton.show();}
}

// ===== WORKS =====
function backToWorksList(){showSubScreen('works','worksList-screen');loadWorks();if(tg)tg.BackButton.hide();}
function loadWorks(){
    const c=document.getElementById('worksList');c.innerHTML='<div class="loading"><div class="spinner"></div></div>';
    api('works_list').then(works=>{
        if(!works.length){c.innerHTML='<div class="empty"><i class="fas fa-briefcase"></i>Работ пока нет</div>';return;}
        c.innerHTML=works.map(w=>{
            const img=w.thumb?`<img class="card__thumb" src="${esc(B+w.thumb)}">`:'<div class="card__thumb"></div>';
            return`<div class="card" onclick="showWorkDetail(${w.id})">${img}<div class="card__info"><div class="card__title">${esc(w.vessel)}</div><div class="card__sub">${esc(w.repair_type)}</div><div class="card__sub">${esc(w.duration||'')}</div></div></div>`;
        }).join('');
    });
}
function showWorkDetail(id){
    showSubScreen('works','workDetail-screen');
    const c=document.getElementById('workDetailContent');c.innerHTML='<div class="loading"><div class="spinner"></div></div>';
    if(tg){tg.BackButton.show();tg.BackButton.onClick(backToWorksList);}
    api('work_get','&id='+id).then(w=>{
        document.getElementById('workDetailTitle').textContent=w.vessel;
        let imgs='';if(w.images?.length)imgs='<div class="detail-images">'+w.images.map(i=>`<img src="${esc(B+i.image_path)}">`).join('')+'</div>';
        c.innerHTML=`${imgs}<div class="detail-body"><h2>${esc(w.vessel)}</h2><div class="detail-row"><span>Ремонт</span><span>${esc(w.repair_type)}</span></div><div class="detail-row"><span>Срок</span><span>${esc(w.duration||'—')}</span></div><div class="detail-row"><span>Сорт.</span><span>${w.sort_order}</span></div><p style="margin-top:12px;font-size:13px;color:var(--hint)">${esc(w.description||'')}</p></div><div class="detail-actions"><button class="btn-primary" onclick="openWorkForm(${w.id})">✏️ Редактировать</button></div>`;
    });
}
function openWorkForm(id){
    showSubScreen('works','workForm-screen');newFiles=[];
    document.getElementById('wId').value=id||0;
    document.getElementById('workFormTitle').textContent=id?'Редактирование':'Новая работа';
    document.getElementById('wDelBtn').classList.toggle('hidden',!id);
    if(tg){tg.BackButton.show();tg.BackButton.onClick(backToWorksList);}
    if(id){
        api('work_get','&id='+id).then(w=>{
            document.getElementById('wVessel').value=w.vessel||'';document.getElementById('wRepair').value=w.repair_type||'';
            document.getElementById('wDuration').value=w.duration||'';document.getElementById('wDesc').value=w.description||'';
            document.getElementById('wSort').value=w.sort_order||0;
            renderPhotoGrid('wPhotos',w.images||[],'work_images',true);
        });
    } else {
        ['wVessel','wRepair','wDuration','wDesc'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('wSort').value='0';renderPhotoGrid('wPhotos',[],'work_images',true);
    }
}
function saveWork(){
    const v=document.getElementById('wVessel').value.trim(),r=document.getElementById('wRepair').value.trim();
    if(!v||!r){toast('Заполните обязательные поля');return;}
    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('id',document.getElementById('wId').value);
    fd.append('vessel',v);fd.append('repair_type',r);fd.append('duration',document.getElementById('wDuration').value.trim());
    fd.append('description',document.getElementById('wDesc').value.trim());fd.append('sort_order',document.getElementById('wSort').value);
    newFiles.forEach(f=>fd.append('photos[]',f));
    const btn=document.getElementById('wSaveBtn');btn.disabled=true;
    apiPost('work_save',fd).then(r=>{btn.disabled=false;if(r.error){toast('Ошибка: '+r.error);return;}toast('✅ Сохранено');haptic('success');setTimeout(backToWorksList,400);}).catch(()=>{btn.disabled=false;toast('Ошибка сети');});
}

// ===== PRODUCTS =====
function backToProductsList(){showSubScreen('products','productsList-screen');loadProducts();if(tg)tg.BackButton.hide();}
function loadProducts(){
    const c=document.getElementById('productsList');c.innerHTML='<div class="loading"><div class="spinner"></div></div>';
    api('products_list').then(items=>{
        if(!items.length){c.innerHTML='<div class="empty"><i class="fas fa-box"></i>Товаров нет</div>';return;}
        c.innerHTML=items.map(p=>{
            const img=p.thumb?`<img class="card__thumb" src="${esc(B+p.thumb)}">`:'<div class="card__thumb"></div>';
            const price=p.price?` — ${esc(p.price)}`:'';
            return`<div class="card" onclick="showProductDetail(${p.id})">${img}<div class="card__info"><div class="card__title">${esc(p.name)}</div><div class="card__sub">${esc(p.category||'')}${price}</div></div></div>`;
        }).join('');
    });
}
function showProductDetail(id){
    showSubScreen('products','productDetail-screen');
    const c=document.getElementById('productDetailContent');c.innerHTML='<div class="loading"><div class="spinner"></div></div>';
    if(tg){tg.BackButton.show();tg.BackButton.onClick(backToProductsList);}
    api('product_get','&id='+id).then(p=>{
        document.getElementById('productDetailTitle').textContent=p.name;
        let imgs='';if(p.images?.length)imgs='<div class="detail-images">'+p.images.map(i=>`<img src="${esc(B+i.image_path)}">`).join('')+'</div>';
        c.innerHTML=`${imgs}<div class="detail-body"><h2>${esc(p.name)}</h2><div class="detail-row"><span>Категория</span><span>${esc(p.category)}</span></div><div class="detail-row"><span>Цена</span><span>${esc(p.price||'—')}</span></div><div class="detail-row"><span>Сорт.</span><span>${p.sort_order}</span></div><p style="margin-top:12px;font-size:13px;color:var(--hint)">${esc(p.description||'')}</p></div><div class="detail-actions"><button class="btn-primary" onclick="openProductForm(${p.id})">✏️ Редактировать</button></div>`;
    });
}
function openProductForm(id){
    showSubScreen('products','productForm-screen');newFiles=[];
    document.getElementById('pId').value=id||0;
    document.getElementById('productFormTitle').textContent=id?'Редактирование':'Новый товар';
    document.getElementById('pDelBtn').classList.toggle('hidden',!id);
    if(tg){tg.BackButton.show();tg.BackButton.onClick(backToProductsList);}
    if(id){
        api('product_get','&id='+id).then(p=>{
            document.getElementById('pName').value=p.name||'';document.getElementById('pCat').value=p.category||'all';
            document.getElementById('pPrice').value=p.price||'';document.getElementById('pDesc').value=p.description||'';
            document.getElementById('pSort').value=p.sort_order||0;
            renderPhotoGrid('pPhotos',p.images||[],'product_images',true);
        });
    } else {
        ['pName','pPrice','pDesc'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('pCat').value='all';document.getElementById('pSort').value='0';
        renderPhotoGrid('pPhotos',[],'product_images',true);
    }
}
function saveProduct(){
    const n=document.getElementById('pName').value.trim();
    if(!n){toast('Введите название');return;}
    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('id',document.getElementById('pId').value);
    fd.append('name',n);fd.append('category',document.getElementById('pCat').value);
    fd.append('price',document.getElementById('pPrice').value.trim());fd.append('description',document.getElementById('pDesc').value.trim());
    fd.append('sort_order',document.getElementById('pSort').value);
    newFiles.forEach(f=>fd.append('photos[]',f));
    const btn=document.getElementById('pSaveBtn');btn.disabled=true;
    apiPost('product_save',fd).then(r=>{btn.disabled=false;if(r.error){toast('Ошибка: '+r.error);return;}toast('✅ Сохранено');haptic('success');setTimeout(backToProductsList,400);}).catch(()=>{btn.disabled=false;toast('Ошибка сети');});
}

// ===== TEAM =====
function backToTeamList(){showSubScreen('team','teamList-screen');loadTeam();if(tg)tg.BackButton.hide();}
function loadTeam(){
    const c=document.getElementById('teamList');c.innerHTML='<div class="loading"><div class="spinner"></div></div>';
    api('team_list').then(members=>{
        if(!members.length){c.innerHTML='<div class="empty"><i class="fas fa-users"></i>Участников нет</div>';return;}
        c.innerHTML=members.map((m,i)=>{
            const img=m.image_path?`<img class="card__thumb" src="${esc(B+m.image_path)}">`:'<div class="card__thumb"></div>';
            return`<div class="card" onclick="openTeamForm(${m.id})">${img}<div class="card__info"><div class="card__title">Участник #${m.id}</div><div class="card__sub">Сортировка: ${m.sort_order}</div></div></div>`;
        }).join('');
    });
}
function openTeamForm(id){
    showSubScreen('team','teamForm-screen');newFiles=[];
    document.getElementById('tId').value=id||0;
    document.getElementById('teamFormTitle').textContent=id?'Редактирование':'Новый участник';
    document.getElementById('tDelBtn').classList.toggle('hidden',!id);
    if(tg){tg.BackButton.show();tg.BackButton.onClick(backToTeamList);}
    if(id){
        // Load existing data - team has single image only
        api('team_list').then(members=>{
            const m=members.find(x=>x.id==id);
            if(m){
                document.getElementById('tSort').value=m.sort_order||0;
                const existing=m.image_path?[{id:0,image_path:m.image_path}]:[];
                renderPhotoGrid('tPhoto',existing,'',false);
            }
        });
    } else {
        document.getElementById('tSort').value='0';renderPhotoGrid('tPhoto',[],'',false);
    }
}
function saveTeam(){
    const id=document.getElementById('tId').value;
    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('id',id);
    fd.append('sort_order',document.getElementById('tSort').value);
    if(newFiles.length)fd.append('photo',newFiles[0]);
    else if(!id||id==='0'){toast('Загрузите фото');return;}
    apiPost('team_save',fd).then(r=>{if(r.error){toast('Ошибка: '+r.error);return;}toast('✅ Сохранено');haptic('success');setTimeout(backToTeamList,400);}).catch(()=>toast('Ошибка сети'));
}
function deleteTeamMember(){
    const id=document.getElementById('tId').value;
    if(!id||id==='0')return;if(!confirm('Удалить участника?'))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    apiPost('team_delete',fd).then(r=>{if(r.ok){toast('Удалено');haptic('warning');backToTeamList();}});
}

// ===== SERVICES =====
let svcData=[];
function backToSvcList(){showSubScreen('services','servicesList-screen');loadServices();if(tg)tg.BackButton.hide();}
function loadServices(){
    const c=document.getElementById('servicesList');c.innerHTML='<div class="loading"><div class="spinner"></div></div>';
    api('services_list').then(dirs=>{
        svcData=dirs;
        if(!dirs.length){c.innerHTML='<div class="empty"><i class="fas fa-cogs"></i>Направлений нет</div>';return;}
        c.innerHTML=dirs.map(d=>{
            let subsHtml=(d.subsections||[]).map(s=>{
                const img=s.image_path?`<img src="${esc(B+s.image_path)}">`:'';
                return`<div class="svc-sub">${img}<div class="svc-sub__info"><div class="svc-sub__name">${esc(s.name)}</div><div class="svc-sub__desc">${esc(s.description||'')}</div></div><button class="svc-sub__del" onclick="event.stopPropagation();delSubsection(${s.id})">×</button></div>`;
            }).join('');
            return`<div class="svc-dir"><div class="svc-dir__header" onclick="openDirForm(${d.id})"><h3>${esc(d.name)}</h3><div class="svc-dir__actions"><button onclick="event.stopPropagation();openSubForm(${d.id})">+</button><button onclick="event.stopPropagation();delDirection(${d.id})">×</button></div></div>${subsHtml}</div>`;
        }).join('');
    });
}
function openDirForm(id){
    showSubScreen('services','svcForm-screen');
    if(tg){tg.BackButton.show();tg.BackButton.onClick(backToSvcList);}
    const isEdit=id&&id>0;
    document.getElementById('svcFormTitle').textContent=isEdit?'Направление':'Новое направление';
    let name='',so=0;
    if(isEdit){const d=svcData.find(x=>x.id==id);if(d){name=d.name;so=d.sort_order;}}
    document.getElementById('svcFormContent').innerHTML=`
        <input type="hidden" id="dirId" value="${id||0}">
        <div class="form-group"><label>Название *</label><input id="dirName" value="${esc(name)}" placeholder="Название направления"></div>
        <div class="form-group"><label>Сортировка</label><input id="dirSort" type="number" value="${so}"></div>
        <button class="btn-primary" onclick="saveDirection()">Сохранить</button>
        ${isEdit?'<button class="btn-danger" onclick="delDirection('+id+')">Удалить направление</button>':''}
    `;
}
function saveDirection(){
    const name=document.getElementById('dirName').value.trim();
    if(!name){toast('Введите название');return;}
    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('id',document.getElementById('dirId').value);
    fd.append('name',name);fd.append('sort_order',document.getElementById('dirSort').value);
    apiPost('direction_save',fd).then(r=>{if(r.error){toast('Ошибка: '+r.error);return;}toast('✅ Сохранено');haptic('success');setTimeout(backToSvcList,400);});
}
function delDirection(id){
    if(!confirm('Удалить направление и все подразделы?'))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    apiPost('direction_delete',fd).then(r=>{if(r.ok){toast('Удалено');haptic('warning');loadServices();}});
}
function openSubForm(dirId,subId){
    showSubScreen('services','svcForm-screen');newFiles=[];
    if(tg){tg.BackButton.show();tg.BackButton.onClick(backToSvcList);}
    const isEdit=subId&&subId>0;
    document.getElementById('svcFormTitle').textContent=isEdit?'Подраздел':'Новый подраздел';
    const dirs=svcData;
    let name='',desc='',pos=0,existImg=[];
    if(isEdit){for(const d of dirs)for(const s of(d.subsections||[]))if(s.id==subId){name=s.name;desc=s.description||'';pos=s.position;if(s.image_path)existImg=[{id:s.id,image_path:s.image_path}];dirId=d.id;}}
    const dirOpts=dirs.map(d=>`<option value="${d.id}"${d.id==dirId?' selected':''}>${esc(d.name)}</option>`).join('');
    document.getElementById('svcFormContent').innerHTML=`
        <input type="hidden" id="subId" value="${subId||0}">
        <div class="form-group"><label>Направление *</label><select id="subDir">${dirOpts}</select></div>
        <div class="form-group"><label>Название *</label><input id="subName" value="${esc(name)}" placeholder="Название услуги"></div>
        <div class="form-group"><label>Описание</label><textarea id="subDesc" placeholder="Описание...">${esc(desc)}</textarea></div>
        <div class="form-group"><label>Фото *</label><div class="photos-grid" id="subPhoto"></div></div>
        <div class="form-group"><label>Позиция</label><input id="subPos" type="number" value="${pos}"></div>
        <button class="btn-primary" onclick="saveSubsection()">Сохранить</button>
    `;
    renderPhotoGrid('subPhoto',existImg,'',false);
}
function saveSubsection(){
    const name=document.getElementById('subName').value.trim(),dirId=document.getElementById('subDir').value;
    if(!name||!dirId){toast('Заполните поля');return;}
    const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('id',document.getElementById('subId').value);
    fd.append('direction_id',dirId);fd.append('name',name);fd.append('description',document.getElementById('subDesc').value.trim());
    fd.append('position',document.getElementById('subPos').value);
    if(newFiles.length)fd.append('photo',newFiles[0]);
    apiPost('subsection_save',fd).then(r=>{if(r.error){toast('Ошибка: '+r.error);return;}toast('✅ Сохранено');haptic('success');setTimeout(backToSvcList,400);}).catch(()=>toast('Ошибка сети'));
}
function delSubsection(id){
    if(!confirm('Удалить подраздел?'))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    apiPost('subsection_delete',fd).then(r=>{if(r.ok){toast('Удалено');haptic('warning');loadServices();}});
}

// ===== CONTACTS =====
function loadContacts(){
    api('contacts_get').then(c=>{
        document.getElementById('cPhone').value=c.phone||'';
        document.getElementById('cTgChannel').value=c.telegram_channel_url||'';
        document.getElementById('cTgChat').value=c.telegram_chat_url||'';
        document.getElementById('cWa').value=c.whatsapp_url||'';
        document.getElementById('cAddr').value=c.address||'';
    });
}
function saveContacts(){
    const fd=new FormData();fd.append('csrf_token',CSRF);
    fd.append('phone',document.getElementById('cPhone').value.trim());
    fd.append('telegram_channel_url',document.getElementById('cTgChannel').value.trim());
    fd.append('telegram_chat_url',document.getElementById('cTgChat').value.trim());
    fd.append('whatsapp_url',document.getElementById('cWa').value.trim());
    fd.append('address',document.getElementById('cAddr').value.trim());
    apiPost('contacts_save',fd).then(r=>{if(r.ok){toast('✅ Контакты сохранены');haptic('success');}else toast('Ошибка');});
}

// ===== STATS =====
function loadStats(period){
    currentPeriod=period;
    document.querySelectorAll('.period-tab').forEach(b=>b.classList.toggle('active',b.dataset.p===period));
    const c=document.getElementById('statsContent');c.innerHTML='<div class="loading"><div class="spinner"></div></div>';
    api('stats','&period='+period).then(d=>{
        const t=d.totals||{};
        let h=`<div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-card__val">${t.visits||0}</div><div class="kpi-card__label">Визиты</div></div>
            <div class="kpi-card"><div class="kpi-card__val">${t.users||0}</div><div class="kpi-card__label">Посетители</div></div>
            <div class="kpi-card"><div class="kpi-card__val">${t.pageviews||0}</div><div class="kpi-card__label">Просмотры</div></div>
            <div class="kpi-card"><div class="kpi-card__val">${t.bounceRate||0}%</div><div class="kpi-card__label">Отказы</div></div>
            <div class="kpi-card"><div class="kpi-card__val">${t.avgDuration>0?formatTime(t.avgDuration):'—'}</div><div class="kpi-card__label">Ср. время</div></div>
            <div class="kpi-card"><div class="kpi-card__val">${t.pageDepth||0}</div><div class="kpi-card__label">Глубина</div></div>
        </div>`;
        if(d.sources?.length){
            h+='<div class="stat-section"><h3>🔗 Источники</h3>';
            d.sources.slice(0,8).forEach(s=>{h+=`<div class="stat-row"><span>${esc(s.source)}</span><span>${s.visits}</span></div>`;});
            h+='</div>';
        }
        if(d.pages?.length){
            h+='<div class="stat-section"><h3>📄 Страницы</h3>';
            d.pages.slice(0,8).forEach(p=>{h+=`<div class="stat-row"><span>${esc(p.url)}</span><span>${p.pageviews}</span></div>`;});
            h+='</div>';
        }
        if(d.devices?.length){
            h+='<div class="stat-section"><h3>📱 Устройства</h3>';
            d.devices.forEach(d2=>{h+=`<div class="stat-row"><span>${esc(d2.device)}</span><span>${d2.visits}</span></div>`;});
            h+='</div>';
        }
        c.innerHTML=h;
    }).catch(()=>{c.innerHTML='<div class="empty">Ошибка загрузки</div>';});
}
function formatTime(sec){const m=Math.floor(sec/60);const s=Math.floor(sec%60);return m+':'+(s<10?'0':'')+s;}
async function sendStatsToChat(){
    const cid=chatId||prompt('Введите ваш chat_id в Telegram:');
    if(!cid){toast('Chat ID не указан');return;}
    const sendBtn=document.querySelector('.send-chat-btn');
    sendBtn.innerHTML='<div class="spinner" style="width:18px;height:18px;border-width:2px"></div> Создание скриншота...';
    sendBtn.style.pointerEvents='none';
    try{
        const statsEl=document.getElementById('statsContent');
        const canvas=await html2canvas(statsEl,{backgroundColor:'#0a0e17',scale:2,useCORS:true,logging:false});
        const blob=await new Promise(r=>canvas.toBlob(r,'image/png'));
        const fd=new FormData();
        fd.append('chat_id',cid);
        fd.append('period',currentPeriod);
        fd.append('screenshot',blob,'stats.png');
        sendBtn.innerHTML='<div class="spinner" style="width:18px;height:18px;border-width:2px"></div> Отправка...';
        const res=await fetch(B+'miniapp.php?action=send_stats_to_chat',{method:'POST',body:fd}).then(r=>r.json());
        if(res.ok){toast('📤 Фото отправлено в чат!');haptic('success');}
        else{toast('Ошибка отправки');}
    }catch(e){
        console.error(e);toast('Ошибка: '+e.message);
    }finally{
        sendBtn.innerHTML='<i class="fab fa-telegram"></i> Отправить в чат';
        sendBtn.style.pointerEvents='';
    }
}

// ===== INIT =====
loadWorks();
</script>
<?php endif;?>
</body>
</html>
