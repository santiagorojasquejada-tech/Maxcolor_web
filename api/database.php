<?php
declare(strict_types=1);
$localConfig=__DIR__.'/config.local.php';
if(is_file($localConfig)) $cfg=require $localConfig;
else $cfg=['host'=>getenv('MAXCOLOR_DB_HOST')?:'127.0.0.1','database'=>getenv('MAXCOLOR_DB_NAME')?:'maxcolor_web','username'=>getenv('MAXCOLOR_DB_USER')?:'root','password'=>getenv('MAXCOLOR_DB_PASSWORD')?:''];
$pdo=new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['database'].';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
