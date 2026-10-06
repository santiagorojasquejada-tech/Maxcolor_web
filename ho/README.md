# MaxColor Web

Sitio web con frontend HTML/CSS/JavaScript, API PHP y base de datos MySQL mediante PDO.

## Requisitos

- XAMPP con Apache, MySQL y PHP 8.1 o posterior.
- Extensiones PHP `pdo_mysql` y `mbstring` habilitadas.
- Visual Studio Code para editar los archivos.

## Instalacion y conexion frontend-MySQL

1. Descomprime la carpeta `ho` del ZIP en `C:\xampp\htdocs\ho (1)\`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Abre `http://localhost/phpmyadmin`, selecciona Importar y carga `ho/database/schema.sql`. El script crea la base `maxcolor_web`, sus tablas y el catalogo inicial.
4. Revisa `ho/api/config.local.php`. La configuracion incluida es `127.0.0.1`, base `maxcolor_web`, usuario `root` y contrasena vacia (configuracion predeterminada de XAMPP). Cambia la contrasena si tu MySQL utiliza otra. No publiques este archivo.
5. Abre `http://localhost/ho%20(1)/ho/`. No abras `index.html` mediante `file://`: Apache/PHP deben servir el frontend y sus solicitudes a `api/index.php`.

El frontend consulta `api/index.php`; esa API carga `api/database.php`, que conecta con MySQL por PDO y `utf8mb4`. El catalogo se consulta desde MySQL y las cotizaciones, cuentas, seguimiento y administracion se guardan/consultan en la misma base.
## Crear el primer administrador

Las cuentas de administrador no tienen registro publico. Abre PowerShell y ejecuta desde la carpeta del proyecto:

```powershell
C:\xampp\php\php.exe .\api\create_admin.php
```

Escribe nombre, correo y contrasena de al menos 10 caracteres. El script solo funciona desde la consola local y almacena la contrasena con `password_hash`. Despues inicia sesion desde el boton **Administracion** del sitio.

## Funcionalidades conectadas

- Catalogo de productos y categorias desde MySQL.
- Registro, inicio de sesion y cierre de sesion de clientes.
- Inicio de sesion de administradores y panel protegido por sesion en servidor.
- Administracion de productos, categorias y estados de las solicitudes.
- Edicion de la informacion corporativa del sitio desde el panel.
- Solicitudes de cotizacion asociadas a la cuenta del cliente cuando inicio sesion.
- Codigo de seguimiento consultable desde la pagina.
- Reporte CSV de solicitudes y lista de clientes/administradores para la cuenta administrativa.
- Tallas S, M y L para chaleco reflectivo, camisa Oxford, ambos pantalones, camiseta polo, buzo polo, camisa y sudadera antifluido y gorra corporativa. Al abrir una prenda con opciones se muestra un menu con precio, talla, colores y bordados aplicables.
- Referencias de bordado: corazon (+$8.000), corazon y lado derecho (+$16.000), o corazon y espalda (+$22.000). Para pantalones, sudadera antifluido, gorra y pava hay una opcion simple de bordado (+$8.000).
- Adjuntar hasta 3 imagenes de formatos comunes, sin limite de tamano definido por la aplicacion.
- Al terminar una solicitud, genera un mensaje con productos y enlaces de referencia dirigido a WhatsApp **+57 311 763 2762**. El cliente debe confirmar el envio en WhatsApp.

Precios: chaleco reflectivo $50.000, botas dielectricas $110.000, camisa Oxford $68.000, pantalon cargo $50.000, pantalon de dama licrado $60.000, camiseta polo $30.000, buzo polo $38.000, camisa antifluido $40.000, sudadera antifluido $40.000, gorra corporativa $29.900, pava $24.000, gorro de cocina $12.000 y delantal antifluido $22.000. La gorra conserva el precio anterior porque no indicaste uno nuevo. Todos los valores son COP. Se retiraron del catalogo el casco, gafas, calzado antideslizante, uniforme antifluido completo y tapaoidos.

## Estructura de datos

`administradores`, `usuario`, `categorias`, `productos`, `producto_colores`, `producto_tallas`, `producto_bordados`, `solicitudes_cotizacion`, `detalle_solicitud` y `solicitud_imagenes`.

Se anadieron `password_hash` en las tablas de usuarios, datos de empresa y observacion de cotizacion, variantes para color y talla, precios de bordado, e imagenes de referencia. El detalle guarda precio y bordado para conservar las opciones escogidas aunque cambie el catalogo.

## WhatsApp y publicacion

El boton abre WhatsApp con un mensaje prellenado al numero de MaxColor. La web no puede pulsar **Enviar** por el cliente: la persona confirma el mensaje dentro de WhatsApp. Para que el destinatario abra las imagenes, el sitio debe estar publicado en una direccion HTTPS accesible desde internet; los enlaces `localhost` solo funcionan en el computador local. Para envio automatico de mensajes o imagenes adjuntas se requiere integrar WhatsApp Business Platform y sus credenciales.

## Configuracion para servidor

En produccion, configura `MAXCOLOR_DB_HOST`, `MAXCOLOR_DB_NAME`, `MAXCOLOR_DB_USER`, `MAXCOLOR_DB_PASSWORD` y `MAXCOLOR_PUBLIC_URL` como variables de entorno, usa HTTPS y una cuenta MySQL con privilegios minimos. No uses el usuario `root` ni incluyas contrasenas en archivos publicados. El servidor tambien debe bloquear el acceso HTTP a `api/config.local.php`. En XAMPP revisa que `upload_max_filesize` y `post_max_size` sean suficientemente altos para el peso total de las tres imagenes.


## WhatsApp Cloud API notifications

The app saves each quote in MySQL first. If Cloud API settings are present, it then sends a quote report to the configured recipient. A failed WhatsApp send does not undo the saved quote.

1. In Meta for Developers, connect a WhatsApp Business account and create an approved message template named `quote_notification` in Spanish (`es_CO`). Use one body variable, for example: `Nueva cotizacion recibida: {{1}}`.
2. Set these values in `api/config.local.php`: `whatsapp_token`, `whatsapp_phone_id`, `whatsapp_to` (recipient number with country code, digits only), and `whatsapp_graph_version` (a Graph API version enabled for your Meta app). Keep the access token private.
3. The recipient must be a WhatsApp number allowed to receive messages from the business. The configured template name and language must match the approved template in Meta.
4. Submit a quote. The result page reports whether MySQL saved it and whether the API accepted the WhatsApp notification. A `sent` response means Meta accepted the request; check the WhatsApp delivery status in Meta if it does not arrive.

This integration uses Meta's official Cloud API messages endpoint. If no credentials are configured, the quote still saves and the customer can use the prefilled WhatsApp button instead.
