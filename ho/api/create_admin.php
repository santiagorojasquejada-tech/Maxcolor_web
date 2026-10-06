<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/database.php';
$name=trim((string)readline('Nombre del administrador: '));
$email=strtolower(trim((string)readline('Correo: ')));
$password=(string)readline('Contraseña (mínimo 10 caracteres): ');
if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10){fwrite(STDERR,"Datos no válidos.\n");exit(1);}
$q=$pdo->prepare('INSERT INTO administradores(nombre,correo,password_hash) VALUES(?,?,?)');
$q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
fwrite(STDOUT,"Administrador creado. Ya puedes iniciar sesión en el panel.\n");
