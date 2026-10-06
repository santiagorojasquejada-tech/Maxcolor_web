<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
$isHttps=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='' && strtolower((string)$_SERVER['HTTPS'])!=='off';
session_set_cookie_params(['httponly'=>true,'secure'=>$isHttps,'samesite'=>'Lax','path'=>'/']);
session_start();

function reply(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) reply(['error'=>'Metodo no permitido.'],405);
}
function require_role(string $role): void {
    if (($_SESSION['role'] ?? '') !== $role) reply(['error'=>'Inicia sesion con una cuenta autorizada.'],401);
}
function clean_string(mixed $value, int $max): string {
    return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
}
function send_whatsapp_quote(array $cfg, string $report): string {
    $getSetting=static function(string $key,string $env,string $default='') use ($cfg): string {$value=trim((string)($cfg[$key]??''));return $value!==''?$value:(string)(getenv($env)?:$default);};
    $token=$getSetting('whatsapp_token','MAXCOLOR_WA_TOKEN');
    $phoneId=$getSetting('whatsapp_phone_id','MAXCOLOR_WA_PHONE_ID');
    $to=preg_replace('/\D+/','',$getSetting('whatsapp_to','MAXCOLOR_WA_TO'));
    $version=$getSetting('whatsapp_graph_version','MAXCOLOR_WA_GRAPH_VERSION');
    $template=$getSetting('whatsapp_template','MAXCOLOR_WA_TEMPLATE','quote_notification');
    $language=$getSetting('whatsapp_language','MAXCOLOR_WA_LANGUAGE','es_CO');
    if($token===''||$phoneId===''||$to===''||$version===''||!function_exists('curl_init')) return 'not_configured';
    if(function_exists('iconv')) $report=(string)(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$report)?:$report);
    $report=substr($report,0,900);
    $body=['messaging_product'=>'whatsapp','to'=>$to,'type'=>'template','template'=>['name'=>$template,'language'=>['code'=>$language],'components'=>[['type'=>'body','parameters'=>[['type'=>'text','text'=>$report]]]]]];
    $ch=curl_init('https://graph.facebook.com/'.$version.'/'.$phoneId.'/messages');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_TIMEOUT=>10]);
    $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($response!==false&&$status>=200&&$status<300) return 'sent';
    error_log('MaxColor WhatsApp API notification failed with HTTP '.$status);
    return 'failed';
}
try {
    require __DIR__ . '/database.php';
    $action = (string)($_GET['action'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($action === 'products' && $method === 'GET') {
        $rows = $pdo->query("SELECT p.id_producto AS id,p.nombre AS name,c.nombre AS category,p.precio_referencial AS price,p.icono AS icon,p.etiqueta AS tag,p.stock,p.descripcion AS description,p.imagen AS image FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria WHERE p.activo=1 ORDER BY p.id_producto")->fetchAll();
        $colors = $pdo->query('SELECT id_producto,color FROM producto_colores')->fetchAll();
        $sizes = $pdo->query('SELECT id_producto,talla FROM producto_tallas')->fetchAll();
        $embroidery = $pdo->query('SELECT id_producto,nombre,precio_adicional FROM producto_bordados')->fetchAll();
        $byId=[];$bySize=[];$byEmbroidery=[];
        foreach ($colors as $row) $byId[$row['id_producto']][]=$row['color'];
        foreach ($sizes as $row) $bySize[$row['id_producto']][]=$row['talla'];
        foreach ($embroidery as $row) $byEmbroidery[$row['id_producto']][]=['name'=>$row['nombre'],'price'=>(float)$row['precio_adicional']];
        foreach ($rows as &$row) { $row['id']=(int)$row['id']; $row['price']=(float)$row['price']; $row['stock']=(int)$row['stock']; $row['colors']=$byId[$row['id']] ?? []; $row['sizes']=$bySize[$row['id']] ?? []; $row['embroideryOptions']=$byEmbroidery[$row['id']] ?? []; }
        reply(['products'=>$rows]);
    }

    if ($action === 'register') {
        require_method('POST'); $d=body();
        $name=clean_string($d['name']??'',120); $email=mb_strtolower(clean_string($d['email']??'',190));
        $phone=clean_string($d['phone']??'',30); $password=(string)($d['password']??'');
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10) reply(['error'=>'Ingresa nombre y correo validos. La contrasena debe tener al menos 10 caracteres.'],422);
        $q=$pdo->prepare('INSERT INTO usuario(nombre,correo,telefono,password_hash) VALUES(?,?,?,?)');
        try {$q->execute([$name,$email,$phone ?: null,password_hash($password,PASSWORD_DEFAULT)]);} catch (PDOException $e) {if ($e->getCode()==='23000') reply(['error'=>'Ya existe una cuenta con ese correo.'],409); throw $e;}
        session_regenerate_id(true); $_SESSION=['role'=>'client','id'=>(int)$pdo->lastInsertId(),'name'=>$name,'email'=>$email];
        reply(['user'=>['name'=>$name,'email'=>$email,'role'=>'client']],201);
    }

    if ($action === 'login') {
        require_method('POST'); $d=body(); $role=($d['role']??'client')==='admin'?'admin':'client';
        $email=mb_strtolower(clean_string($d['email']??'',190)); $password=(string)($d['password']??'');
        $table=$role==='admin'?'administradores':'usuario'; $idcol=$role==='admin'?'id_admin':'id_usuario';
        $q=$pdo->prepare("SELECT $idcol AS id,nombre,correo,password_hash FROM $table WHERE correo=? LIMIT 1"); $q->execute([$email]); $user=$q->fetch();
        if (!$user || !password_verify($password,$user['password_hash'])) reply(['error'=>'Correo o contrasena incorrectos.'],401);
        if (password_needs_rehash($user['password_hash'],PASSWORD_DEFAULT)) {$u=$pdo->prepare("UPDATE $table SET password_hash=? WHERE $idcol=?");$u->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]);}
        session_regenerate_id(true); $_SESSION=['role'=>$role,'id'=>(int)$user['id'],'name'=>$user['nombre'],'email'=>$user['correo']];
        reply(['user'=>['name'=>$user['nombre'],'email'=>$user['correo'],'role'=>$role]]);
    }

    if ($action === 'session' && $method === 'GET') {
        reply(['user'=>isset($_SESSION['role'])?['name'=>$_SESSION['name'],'email'=>$_SESSION['email'],'role'=>$_SESSION['role']]:null]);
    }
    if ($action === 'logout') {require_method('POST');$_SESSION=[];session_regenerate_id(true);reply(['ok'=>true]);}

    if ($action === 'quote') {
        require_method('POST'); $d=isset($_POST['payload'])?json_decode((string)$_POST['payload'],true):body();if(!is_array($d))reply(['error'=>'Datos de cotizacion no validos.'],422);$files=$_FILES['images']??null;$name=clean_string($d['name']??'',120);$email=mb_strtolower(clean_string($d['email']??'',190));
        $lines=$d['items']??[];
        if($name==='')reply(['error'=>'Enter your name.'],422);if(!filter_var($email,FILTER_VALIDATE_EMAIL))reply(['error'=>'Enter a valid email address.'],422);if(!is_array($lines)||count($lines)<1)reply(['error'=>'Your quote has no products. Add a product and try again.'],422);if(count($lines)>60)reply(['error'=>'A quote can contain up to 60 product lines.'],422);
        $fileCount=0;if($files && isset($files['name']))$fileCount=is_array($files['name'])?count(array_filter($files['name'],fn($x)=>$x!=='')):($files['name']!==''?1:0);
        if($fileCount>3)reply(['error'=>'Adjunta maximo tres imagenes.'],422);
        $pdo->beginTransaction();
        $code='MC-'.strtoupper(bin2hex(random_bytes(4)));
        $q=$pdo->prepare("INSERT INTO solicitudes_cotizacion(codigo_seguimiento,id_usuario,nombre_cliente,empresa,correo,telefono,observacion) VALUES(?,?,?,?,?,?,?)");
        $userId=($_SESSION['role']??'')==='client'?$_SESSION['id']:null;
        $q->execute([$code,$userId,$name,clean_string($d['company']??'',150) ?: null,$email,clean_string($d['phone']??'',30) ?: null,clean_string($d['notes']??'',2000) ?: null]);
        $requestId=(int)$pdo->lastInsertId(); $get=$pdo->prepare('SELECT id_producto,nombre,precio_referencial FROM productos WHERE id_producto=? AND activo=1');
        $ins=$pdo->prepare('INSERT INTO detalle_solicitud(id_solicitud,id_producto,nombre_producto,cantidad,color,talla,bordado,costo_bordado,observacion,precio_referencial) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $chosenEmbroidery=false;$reportLines=[];
        foreach ($lines as $line) {
            $pid=(int)($line['id']??0);$qty=(int)($line['quantity']??0);$color=clean_string($line['color']??'',40);$size=clean_string($line['size']??'',20);$embroidery=clean_string($line['embroidery']??'',100);
            if ($pid<1 || $qty<1 || $qty>10000) { $pdo->rollBack(); reply(['error'=>'Hay un producto o una cantidad no valida.'],422); }
            $get->execute([$pid]);$p=$get->fetch();if(!$p){$pdo->rollBack();reply(['error'=>'Uno de los productos ya no esta disponible.'],422);}
            $available=$pdo->prepare('SELECT COUNT(*) FROM producto_colores WHERE id_producto=?');$available->execute([$pid]);$hasColors=(int)$available->fetchColumn()>0;
            if(($hasColors && $color==='') || ($color!=='' && !$hasColors)){$pdo->rollBack();reply(['error'=>'Selecciona una opcion de color para cada prenda.'],422);}
            if($color!==''){$valid=$pdo->prepare('SELECT 1 FROM producto_colores WHERE id_producto=? AND color=?');$valid->execute([$pid,$color]);if(!$valid->fetchColumn()){$pdo->rollBack();reply(['error'=>'Uno de los colores seleccionados no esta disponible.'],422);}}
            $sizeOptions=$pdo->prepare('SELECT COUNT(*) FROM producto_tallas WHERE id_producto=?');$sizeOptions->execute([$pid]);$hasSizes=(int)$sizeOptions->fetchColumn()>0;
            if(($hasSizes && $size==='') || ($size!=='' && !$hasSizes)){$pdo->rollBack();reply(['error'=>'Selecciona una talla valida para cada prenda que la requiera.'],422);}
            if($size!==''){$valid=$pdo->prepare('SELECT 1 FROM producto_tallas WHERE id_producto=? AND talla=?');$valid->execute([$pid,$size]);if(!$valid->fetchColumn()){$pdo->rollBack();reply(['error'=>'Una talla seleccionada no esta disponible.'],422);}}
            $extra=0;if($embroidery!==''){$opt=$pdo->prepare('SELECT precio_adicional FROM producto_bordados WHERE id_producto=? AND nombre=?');$opt->execute([$pid,$embroidery]);$extra=$opt->fetchColumn();if($extra===false){$pdo->rollBack();reply(['error'=>'El tipo de bordado seleccionado no esta disponible para este producto.'],422);}$extra=(float)$extra;$chosenEmbroidery=true;}
            $ins->execute([$requestId,$pid,$p['nombre'],$qty,$color ?: null,$size ?: null,$embroidery ?: null,$extra,clean_string($line['notes']??'',500) ?: null,$p['precio_referencial']]);$reportLines[]=$qty.' x '.$p['nombre'].($size!==''?' / size '.$size:'').($color!==''?' / '.$color:'').($embroidery!==''?' / '.$embroidery:'').' - '.number_format(((float)$p['precio_referencial']+$extra)*$qty,0,',','.').' COP';
        }
        $imageUrls=[];$saved=[];
        if($fileCount>0){$dir=dirname(__DIR__).'/uploads/quotes';if(!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)){$pdo->rollBack();reply(['error'=>'No fue posible preparar el almacenamiento de imagenes.'],500);}
            $names=is_array($files['name'])?$files['name']:[$files['name']];$tmp=is_array($files['tmp_name'])?$files['tmp_name']:[$files['tmp_name']];$errors=is_array($files['error'])?$files['error']:[$files['error']];$mime=finfo_open(FILEINFO_MIME_TYPE);
            foreach($names as $i=>$original){if(($errors[$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){foreach($saved as $path)@unlink($path);$pdo->rollBack();reply(['error'=>'No se pudo recibir una imagen. Revisa los limites de subida de PHP.'],422);}
                $type=finfo_file($mime,$tmp[$i]);if(!is_string($type)||!str_starts_with($type,'image/')){foreach($saved as $path)@unlink($path);$pdo->rollBack();reply(['error'=>'Adjunta archivos de imagen validos.'],422);}$subtype=substr($type,6);$ext=$subtype==='jpeg'?'jpg':preg_replace('/[^a-z0-9]+/i','',$subtype);if($ext==='')$ext='img';
                $filename=$code.'-'.($i+1).'-'.bin2hex(random_bytes(5)).'.'.$ext;$target=$dir.'/'.$filename;if(!move_uploaded_file($tmp[$i],$target)){foreach($saved as $path)@unlink($path);$pdo->rollBack();reply(['error'=>'No se pudieron guardar las imagenes adjuntas.'],500);}$saved[]=$target;
                $relative='uploads/quotes/'.$filename;$image=$pdo->prepare('INSERT INTO solicitud_imagenes(id_solicitud,ruta,nombre_original) VALUES(?,?,?)');$image->execute([$requestId,$relative,clean_string(basename((string)$original),255)]);$rootPath=rtrim(dirname(dirname($_SERVER['SCRIPT_NAME']??'/api/index.php')),'/');$publicUrl=rtrim((string)(getenv('MAXCOLOR_PUBLIC_URL')?:($cfg['public_url']??'')),'/');if($publicUrl==='')$publicUrl=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').$rootPath;$imageUrls[]=$publicUrl.'/'.$relative;
            }finfo_close($mime);
        }
        $pdo->commit();$report='Quote '.$code."\nClient: ".$name."\nCompany: ".clean_string($d['company']??'',150)."\nEmail: ".$email."\nPhone: ".clean_string($d['phone']??'',30)."\nProducts: ".implode('; ',$reportLines)."\nNotes: ".clean_string($d['notes']??'',2000);$waStatus=send_whatsapp_quote($cfg,$report);reply(['code'=>$code,'status'=>'Received','imageUrls'=>$imageUrls,'whatsapp'=>$waStatus],201);
    }

    if ($action === 'track' && $method === 'GET') {
        $code=strtoupper(clean_string($_GET['code']??'',20));$q=$pdo->prepare('SELECT codigo_seguimiento,fecha_creacion,estado FROM solicitudes_cotizacion WHERE codigo_seguimiento=?');$q->execute([$code]);$o=$q->fetch();
        if(!$o) reply(['error'=>'No encontramos ese codigo de seguimiento.'],404);
        $n=$pdo->prepare('SELECT COUNT(*) FROM detalle_solicitud WHERE id_solicitud=(SELECT id_solicitud FROM solicitudes_cotizacion WHERE codigo_seguimiento=?)');$n->execute([$code]);
        reply(['code'=>$o['codigo_seguimiento'],'date'=>$o['fecha_creacion'],'status'=>$o['estado'],'itemCount'=>(int)$n->fetchColumn()]);
    }

    if ($action === 'admin/orders' && $method === 'GET') {
        require_role('admin');
        $orders=$pdo->query('SELECT id_solicitud AS id,codigo_seguimiento AS code,nombre_cliente AS name,empresa,correo AS email,telefono AS phone,observacion AS notes,fecha_creacion AS date,estado AS status FROM solicitudes_cotizacion ORDER BY fecha_creacion DESC')->fetchAll();
        foreach($orders as &$o){$q=$pdo->prepare('SELECT nombre_producto AS name,cantidad AS quantity,color,talla AS size,bordado AS embroidery,costo_bordado AS embroideryCost,precio_referencial AS price FROM detalle_solicitud WHERE id_solicitud=?');$q->execute([$o['id']]);$o['items']=$q->fetchAll();$im=$pdo->prepare('SELECT ruta AS path,nombre_original AS name FROM solicitud_imagenes WHERE id_solicitud=?');$im->execute([$o['id']]);$o['images']=$im->fetchAll();}
        reply(['orders'=>$orders]);
    }
    if ($action === 'admin/status') {
        require_method('POST');require_role('admin');$d=body();$allowed=['Recibida','En revision','Cotizada','Cerrada'];
        if(!in_array($d['status']??'', $allowed,true))reply(['error'=>'Estado no valido.'],422);
        $q=$pdo->prepare('UPDATE solicitudes_cotizacion SET estado=? WHERE id_solicitud=?');$q->execute([$d['status'],(int)($d['id']??0)]);reply(['ok'=>$q->rowCount()===1]);
    }
    if ($action === 'admin/product') {
        require_method('POST');require_role('admin');$d=body();$name=clean_string($d['name']??'',150);$price=(float)($d['price']??0);$cat=(int)($d['categoryId']??0);
        if($name===''||$price<0||$cat<1)reply(['error'=>'Revisa nombre, categoria y precio.'],422);
        $q=$pdo->prepare('INSERT INTO productos(id_categoria,nombre,descripcion,precio_referencial,icono,etiqueta,stock) VALUES(?,?,?,?,?,?,?)');$q->execute([$cat,$name,clean_string($d['description']??'',1000),$price,clean_string($d['icon']??'◈',20),clean_string($d['tag']??'NUEVO',50),max(0,(int)($d['stock']??0))]);$id=(int)$pdo->lastInsertId();
        foreach(($d['colors']??[]) as $color){$color=clean_string($color,40);if(in_array($color,['Blanco','Negro','Gris'],true)){$c=$pdo->prepare('INSERT IGNORE INTO producto_colores(id_producto,color) VALUES(?,?)');$c->execute([$id,$color]);}}
        reply(['id'=>$id],201);
    }
    if ($action === 'admin/delete-product') {
        require_method('POST');require_role('admin');$d=body();$q=$pdo->prepare('UPDATE productos SET activo=0 WHERE id_producto=?');$q->execute([(int)($d['id']??0)]);reply(['ok'=>$q->rowCount()===1]);
    }
    if ($action === 'admin/categories' && $method === 'GET') {
        require_role('admin');reply(['categories'=>$pdo->query('SELECT id_categoria AS id,nombre AS name,descripcion AS description FROM categorias ORDER BY nombre')->fetchAll()]);
    }
    if ($action === 'admin/category') {
        require_method('POST');require_role('admin');$d=body();$name=clean_string($d['name']??'',100);if($name==='')reply(['error'=>'Escribe el nombre de la categoria.'],422);
        try{$q=$pdo->prepare('INSERT INTO categorias(nombre,descripcion) VALUES(?,?)');$q->execute([$name,clean_string($d['description']??'',500)]);}catch(PDOException $e){if($e->getCode()==='23000')reply(['error'=>'Esa categoria ya existe.'],409);throw $e;}reply(['id'=>(int)$pdo->lastInsertId()],201);
    }
    if ($action === 'admin/report' && $method === 'GET') {
        require_role('admin');header_remove('Content-Type');header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="maxcolor-solicitudes.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['codigo','fecha','cliente','empresa','correo','telefono','estado','productos','observaciones']);
        $q=$pdo->query('SELECT s.*,GROUP_CONCAT(CONCAT(d.cantidad,"x ",d.nombre_producto,IF(d.talla IS NULL,"",CONCAT(" / talla ",d.talla)),IF(d.color IS NULL,"",CONCAT(" / ",d.color)),IF(d.bordado IS NULL,"",CONCAT(" / ",d.bordado," +$",d.costo_bordado))) SEPARATOR "; ") AS productos FROM solicitudes_cotizacion s LEFT JOIN detalle_solicitud d ON d.id_solicitud=s.id_solicitud GROUP BY s.id_solicitud ORDER BY s.fecha_creacion DESC');
        while($r=$q->fetch())fputcsv($out,[$r['codigo_seguimiento'],$r['fecha_creacion'],$r['nombre_cliente'],$r['empresa'],$r['correo'],$r['telefono'],$r['estado'],$r['productos'],$r['observacion']]);fclose($out);exit;
    }
    if ($action === 'admin/counts' && $method === 'GET') {
        require_role('admin');reply(['clients'=>(int)$pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn(),'admins'=>(int)$pdo->query('SELECT COUNT(*) FROM administradores')->fetchColumn(),'products'=>(int)$pdo->query('SELECT COUNT(*) FROM productos WHERE activo=1')->fetchColumn(),'requests'=>(int)$pdo->query('SELECT COUNT(*) FROM solicitudes_cotizacion')->fetchColumn()]);
    }
    if ($action === 'admin/users' && $method === 'GET') {
        require_role('admin');$clients=$pdo->query('SELECT nombre AS name,correo AS email,telefono AS phone,fecha_registro AS date FROM usuario ORDER BY fecha_registro DESC')->fetchAll();$admins=$pdo->query('SELECT nombre AS name,correo AS email,fecha_registro AS date FROM administradores ORDER BY fecha_registro DESC')->fetchAll();reply(['clients'=>$clients,'admins'=>$admins]);
    }
    if ($action === 'admin/site-info' && $method === 'GET') {
        require_role('admin');$info=$pdo->query('SELECT telefono AS phone,correo AS email,mensaje AS message FROM informacion_corporativa WHERE id_informacion=1')->fetch();reply(['info'=>$info ?: ['phone'=>'','email'=>'','message'=>'']]);
    }
    if ($action === 'admin/site-info') {
        require_method('POST');require_role('admin');$d=body();$q=$pdo->prepare('UPDATE informacion_corporativa SET telefono=?,correo=?,mensaje=? WHERE id_informacion=1');$q->execute([clean_string($d['phone']??'',50) ?: null,filter_var($d['email']??'',FILTER_VALIDATE_EMAIL)?mb_strtolower(clean_string($d['email'],190)):null,clean_string($d['message']??'',3000) ?: null]);reply(['ok'=>true]);
    }
    reply(['error'=>'Ruta no encontrada.'],404);
} catch (Throwable $e) {
    error_log('MaxColor API: '.$e->getMessage());
    reply(['error'=>'No se pudo completar la operacion. Revisa la configuracion de la base de datos.'],500);
}
