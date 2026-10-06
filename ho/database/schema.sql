CREATE DATABASE IF NOT EXISTS maxcolor_web
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maxcolor_web;

CREATE TABLE IF NOT EXISTS administradores (
  id_admin BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  correo VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuario (
  id_usuario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  correo VARCHAR(190) NOT NULL UNIQUE,
  telefono VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
  id_categoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  descripcion VARCHAR(500) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
  id_producto BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_categoria BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  precio_referencial DECIMAL(12,2) UNSIGNED NOT NULL,
  imagen VARCHAR(500) NULL,
  icono VARCHAR(20) NULL,
  etiqueta VARCHAR(50) NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)
    REFERENCES categorias(id_categoria) ON UPDATE CASCADE,
  INDEX idx_producto_activo_categoria (activo, id_categoria)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS producto_colores (
  id_producto BIGINT UNSIGNED NOT NULL,
  color VARCHAR(40) NOT NULL,
  PRIMARY KEY (id_producto, color),
  CONSTRAINT fk_color_producto FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS producto_tallas (
  id_producto BIGINT UNSIGNED NOT NULL,
  talla VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_producto, talla),
  CONSTRAINT fk_talla_producto FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS producto_bordados (
  id_producto BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  precio_adicional DECIMAL(12,2) UNSIGNED NOT NULL,
  PRIMARY KEY (id_producto, nombre),
  CONSTRAINT fk_bordado_producto FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS solicitudes_cotizacion (
  id_solicitud BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo_seguimiento VARCHAR(20) NOT NULL UNIQUE,
  id_usuario BIGINT UNSIGNED NULL,
  nombre_cliente VARCHAR(120) NOT NULL,
  empresa VARCHAR(150) NULL,
  correo VARCHAR(190) NOT NULL,
  telefono VARCHAR(30) NULL,
  observacion TEXT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('Recibida','En revisiÃ³n','Cotizada','Cerrada') NOT NULL DEFAULT 'Recibida',
  CONSTRAINT fk_solicitud_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_solicitud_fecha_estado (fecha_creacion, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_solicitud (
  id_detalle BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_solicitud BIGINT UNSIGNED NOT NULL,
  id_producto BIGINT UNSIGNED NULL,
  nombre_producto VARCHAR(150) NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  color VARCHAR(40) NULL,
  talla VARCHAR(20) NULL,
  bordado VARCHAR(100) NULL,
  costo_bordado DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
  observacion VARCHAR(500) NULL,
  precio_referencial DECIMAL(12,2) UNSIGNED NOT NULL,
  CONSTRAINT fk_detalle_solicitud FOREIGN KEY (id_solicitud)
    REFERENCES solicitudes_cotizacion(id_solicitud) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_detalle_solicitud (id_solicitud)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS solicitud_imagenes (
  id_imagen BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_solicitud BIGINT UNSIGNED NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  nombre_original VARCHAR(255) NOT NULL,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_imagen_solicitud FOREIGN KEY (id_solicitud)
    REFERENCES solicitudes_cotizacion(id_solicitud) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- These checks also upgrade an earlier MaxColor schema without duplicating columns.
SET @has_talla = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='detalle_solicitud' AND column_name='talla');
SET @ddl_talla = IF(@has_talla=0,'ALTER TABLE detalle_solicitud ADD COLUMN talla VARCHAR(20) NULL AFTER color','SELECT 1');
PREPARE stmt_talla FROM @ddl_talla; EXECUTE stmt_talla; DEALLOCATE PREPARE stmt_talla;
SET @has_bordado = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='detalle_solicitud' AND column_name='bordado');
SET @ddl_bordado = IF(@has_bordado=0,'ALTER TABLE detalle_solicitud ADD COLUMN bordado VARCHAR(100) NULL AFTER talla','SELECT 1');
PREPARE stmt_bordado FROM @ddl_bordado; EXECUTE stmt_bordado; DEALLOCATE PREPARE stmt_bordado;
SET @has_costo_bordado = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='detalle_solicitud' AND column_name='costo_bordado');
SET @ddl_costo_bordado = IF(@has_costo_bordado=0,'ALTER TABLE detalle_solicitud ADD COLUMN costo_bordado DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0 AFTER bordado','SELECT 1');
PREPARE stmt_costo_bordado FROM @ddl_costo_bordado; EXECUTE stmt_costo_bordado; DEALLOCATE PREPARE stmt_costo_bordado;

CREATE TABLE IF NOT EXISTS informacion_corporativa (
  id_informacion TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  telefono VARCHAR(50) NULL,
  correo VARCHAR(190) NULL,
  mensaje TEXT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO informacion_corporativa(id_informacion,telefono,correo,mensaje)
VALUES(1,'','', 'Dotaciones empresariales para equipos de trabajo en UrabÃ¡.');

INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
 ('Uniformes','Prendas empresariales y dotaciÃ³n corporativa'),
 ('Calzado industrial','Calzado de trabajo y seguridad'),
 ('ProtecciÃ³n personal','Elementos de protecciÃ³n personal'),
 ('Accesorios','Complementos y prendas de identidad corporativa');

INSERT INTO productos (id_producto,id_categoria,nombre,descripcion,precio_referencial,icono,etiqueta,stock) VALUES
 (1,(SELECT id_categoria FROM categorias WHERE nombre='ProtecciÃ³n personal'),'Chaleco reflectivo','Chaleco de alta visibilidad con bandas reflectivas.',50000,'ðŸ¦º','MÃS PEDIDO',38),
 (2,(SELECT id_categoria FROM categorias WHERE nombre='Calzado industrial'),'Botas de seguridad dielÃ©ctricas','Botas de protecciÃ³n para trabajo industrial.',110000,'ðŸ¥¾','SEGURIDAD',24),
 (3,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'Camisa Oxford corporativa','Camisa corporativa con opciÃ³n de bordado.',68000,'ðŸ‘”','CORPORATIVO',80),
 (4,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'PantalÃ³n cargo industrial','PantalÃ³n cargo de uso industrial.',50000,'ðŸ‘–','RESISTENTE',42),
 (5,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'Camiseta polo','Camiseta polo empresarial cÃ³moda y personalizable.',30000,'ðŸ‘•','NUEVO',60),
 (6,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'Buzo polo','Buzo polo cÃ¡lido y cÃ³modo para el equipo de trabajo.',38000,'ðŸ§¥','NUEVO',50),
 (7,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'PantalÃ³n de dama licrado','PantalÃ³n licrado de dotaciÃ³n para dama.',60000,'ðŸ‘–','NUEVO',40),
 (8,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'Camisa antifluido','Camisa antifluido para la jornada de trabajo.',40000,'ðŸ¥¼','ANTIFLUIDO',40),
 (9,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'Sudadera antifluido','Sudadera antifluido para la jornada de trabajo.',40000,'ðŸ¥¼','ANTIFLUIDO',40),
 (10,(SELECT id_categoria FROM categorias WHERE nombre='Accesorios'),'Gorra corporativa','Gorra para dotaciÃ³n e identidad corporativa.',29900,'ðŸ§¢','IDENTIDAD',44),
 (11,(SELECT id_categoria FROM categorias WHERE nombre='Accesorios'),'Pava','Pava de dotaciÃ³n.',24000,'ðŸ§¢','NUEVO',35),
 (12,(SELECT id_categoria FROM categorias WHERE nombre='Accesorios'),'Gorro de cocina','Gorro de cocina para dotaciÃ³n.',12000,'ðŸ‘¨â€ðŸ³','NUEVO',45),
 (13,(SELECT id_categoria FROM categorias WHERE nombre='Uniformes'),'Delantal antifluido','Delantal antifluido para trabajo y servicio.',22000,'ðŸ¦º','ANTIFLUIDO',35)
ON DUPLICATE KEY UPDATE id_producto=VALUES(id_producto),id_categoria=VALUES(id_categoria),nombre=VALUES(nombre),descripcion=VALUES(descripcion),precio_referencial=VALUES(precio_referencial),icono=VALUES(icono),etiqueta=VALUES(etiqueta),stock=VALUES(stock),activo=1;

INSERT IGNORE INTO producto_colores (id_producto,color)
SELECT id_producto, color FROM productos
CROSS JOIN (SELECT 'Blanco' color UNION ALL SELECT 'Negro' UNION ALL SELECT 'Gris') colores
WHERE nombre IN ('Camiseta polo','Buzo polo','Camisa antifluido','Sudadera antifluido','Gorra corporativa');
INSERT IGNORE INTO producto_colores (id_producto,color)
SELECT id_producto,color FROM productos
CROSS JOIN (SELECT 'Blanco' color UNION ALL SELECT 'Negro' UNION ALL SELECT 'Gris') colores
WHERE nombre IN ('Chaleco reflectivo','Camisa Oxford corporativa','PantalÃ³n cargo industrial','PantalÃ³n de dama licrado','Pava','Gorro de cocina','Delantal antifluido');
DELETE pc FROM producto_colores pc JOIN productos p ON p.id_producto=pc.id_producto
WHERE p.nombre NOT IN ('Camiseta polo','Buzo polo','Camisa antifluido','Sudadera antifluido','Gorra corporativa','Chaleco reflectivo','Camisa Oxford corporativa','PantalÃ³n cargo industrial','PantalÃ³n de dama licrado','Pava','Gorro de cocina','Delantal antifluido');

INSERT IGNORE INTO producto_tallas (id_producto,talla)
SELECT id_producto,talla FROM productos CROSS JOIN (SELECT 'S' talla UNION ALL SELECT 'M' UNION ALL SELECT 'L') tallas
WHERE nombre IN ('Chaleco reflectivo','Camisa Oxford corporativa','PantalÃ³n cargo industrial','PantalÃ³n de dama licrado','Camiseta polo','Buzo polo','Camisa antifluido','Sudadera antifluido','Gorra corporativa');

INSERT IGNORE INTO producto_bordados(id_producto,nombre,precio_adicional)
SELECT p.id_producto,opcion.nombre,opcion.precio FROM productos p
CROSS JOIN (SELECT 'Bordado corazÃ³n' nombre,8000 precio UNION ALL SELECT 'Bordado corazÃ³n y lado derecho',16000 UNION ALL SELECT 'Bordado corazÃ³n y espalda',22000) opcion
WHERE p.nombre IN ('Buzo polo','Camisa antifluido','Camiseta polo','Camisa Oxford corporativa','Chaleco reflectivo');

INSERT IGNORE INTO producto_bordados(id_producto,nombre,precio_adicional)
SELECT id_producto,'Un bordado',8000 FROM productos
WHERE nombre IN ('PantalÃ³n cargo industrial','PantalÃ³n de dama licrado','Sudadera antifluido','Gorra corporativa','Pava');



