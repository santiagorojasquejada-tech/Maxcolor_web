<?php

$servidor = "127.0.0.1";
$usuario = "root";
$contrasena = "";
$baseDatos = "maxcolor_web";

$conexion = new mysqli(
    $servidor,
    $usuario,
    $contrasena,
    $baseDatos
);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");

?>