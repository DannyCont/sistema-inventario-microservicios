# Sistema de inventario con microservicios

Proyecto académico desarrollado con HTML, CSS, JavaScript, PHP y MySQL. Permite administrar usuarios y roles, productos, existencias, entradas, salidas y alertas de bajo inventario.

## Integrantes

- Daniel Martínez Contreras
- Valeria Reyes Hernandez

## Módulos

- **Usuarios:** inicio y cierre de sesión, registro de usuarios, perfil, roles y estado de cuenta.
- **Productos:** registro, consulta, búsqueda, actualización y baja lógica.
- **Inventario:** existencias, entradas, salidas, historial y alertas por stock mínimo.

## Roles

- **Administrador:** acceso completo.
- **Almacenista:** consulta productos y registra entradas y salidas.
- **Consulta:** únicamente consulta productos, existencias, alertas e historial.

## Requisitos

- XAMPP con Apache, PHP 8.0 o superior y MySQL.
- Navegador web moderno.

## Instalación en XAMPP

1. Copiar la carpeta `sistema-inventario-microservicios` dentro de `C:\xampp\htdocs\`.
2. Encender Apache y MySQL desde el panel de XAMPP.
3. Abrir `http://localhost/phpmyadmin`.
4. Para una instalación nueva, importar `database/inventario_completo.sql`. Después seleccionar la base `inventario_completo` e importar `database/pwa.sql`, también al actualizar una instalación existente.
5. Abrir `http://localhost/sistema-inventario-microservicios/`.

Si MySQL utiliza otra contraseña, editar `config/database.php`.

## Usuarios de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@inventario.com | 123456 |
| Almacenista | almacen@inventario.com | 123456 |
| Consulta | consulta@inventario.com | 123456 |

La versión utiliza SHA-256 para que las credenciales de prueba funcionen de manera sencilla en XAMPP. Para un sistema de producción se recomienda migrar a `password_hash()` y `password_verify()`.

## Estructura

```text
sistema-inventario-completo/
├── config/                    # Conexión y funciones compartidas
├── database/                  # Script SQL
├── docs/                      # Planeación
├── public/                    # Interfaz HTML, CSS y JavaScript
├── servicio-usuarios/api/     # Usuarios, sesión, perfil y roles
├── servicio-productos/api/    # CRUD de productos
├── servicio-inventario/api/   # Existencias y movimientos
├── .gitignore
├── index.php
└── README.md
```

## Reglas implementadas

- Correos y códigos de producto únicos.
- Contraseñas de prueba almacenadas con SHA-256 para máxima compatibilidad con XAMPP.
- Todas las operaciones privadas validan la sesión en PHP.
- Los permisos de cada rol se validan tanto en la interfaz como en el servidor.
- Permisos validados también en PHP, no solamente en la interfaz.
- Salidas bloqueadas cuando superan la existencia disponible.
- Movimientos realizados dentro de transacciones MySQL.
- Baja lógica de productos para conservar su historial.
- Alertas automáticas cuando la existencia es menor o igual al stock mínimo.

## Comandos para subirlo a GitHub

Primero crea un repositorio vacío en GitHub. Después abre Git Bash o una terminal dentro de la carpeta del proyecto y ejecuta:

```bash
git init
git branch -M main
git add .gitignore README.md docs database
git commit -m "docs: agregar planeacion y estructura de base de datos"

git add config servicio-usuarios
git commit -m "feat: implementar autenticacion usuarios perfiles y roles"

git add servicio-productos servicio-inventario
git commit -m "feat: implementar productos y movimientos de inventario"

git add public index.php
git commit -m "feat: agregar interfaz web del sistema"

git remote add origin URL_DEL_REPOSITORIO
git push -u origin main
```

Sustituye `URL_DEL_REPOSITORIO` por una dirección como:

```text
https://github.com/tu-usuario/sistema-inventario.git
```

## Docker y funciones PWA

Desde la carpeta donde está `compose.yaml`, ejecuta:

```powershell
docker compose up -d --build
docker compose ps -a
```

Abre [FlexiAlmacén en localhost](http://localhost:8080/public/). El servicio `migrar` debe terminar con código 0. Crea las tablas adicionales sin borrar las existentes. Para conservar una base de Docker ya cargada, usa el mismo proyecto de Compose que antes (la misma carpeta o el mismo nombre indicado con `-p`) y conserva el volumen `db_data`.

### Tema y trabajo sin conexión

1. Usa el selector **Tema** para elegir **Del sistema**, **Claro** u **Oscuro**. La elección se guarda en el navegador.
2. Con conexión, inicia sesión y espera a que cargue el inventario. La PWA guarda la interfaz y una copia de la última consulta. Si quieres consultar el historial sin conexión, ábrelo una vez en línea.
3. Desconecta el dispositivo y vuelve a abrir la app. En **Movimientos**, un administrador o almacenista puede guardar entradas y salidas. **Movimientos por sincronizar** muestra los registros locales. Las existencias visibles siguen siendo las últimas confirmadas.
4. Recupera la conexión con la app abierta o vuelve a abrirla. Si el aviso sigue visible, pulsa **Comprobar conexión**. La cola se envía en el orden registrado. Si la sesión expiró, entra con la misma cuenta. Cada movimiento tiene un identificador que evita aplicarlo dos veces aunque se pierda la respuesta.
5. Si una salida supera el stock real, queda rechazada y pausa el resto de la cola. Puedes usar **Sincronizar ahora** cuando se reponga el stock, o **Retirar** ese movimiento y registrar uno corregido.

El acceso local requiere una sesión validada en ese navegador durante las últimas 24 horas. Los pendientes quedan separados por cuenta y dirección de la app. Usa la misma URL en el teléfono durante toda la prueba. Cerrar la sesión requiere conexión. El modo offline está pensado para un dispositivo de confianza. Si se borran los datos del sitio, también se borra la cola local. Los cambios de productos, usuarios y perfil requieren conexión.

### Web Push en el teléfono

1. En PowerShell, copia la configuración de ejemplo y genera tus claves.

   ```powershell
   Copy-Item .env.example .env
   docker compose run --rm --no-deps web php config/generar-vapid.php
   ```

2. En `.env`, copia las dos líneas que imprime el comando. Pon tu correo de contacto como `VAPID_SUBJECT=mailto:tu-correo@dominio.com` y la dirección IPv4 local de tu PC como `FLEXI_LAN_HOST` (puedes verla con `ipconfig`). Conserva esas claves para las siguientes ejecuciones y guarda el archivo de forma privada.
3. Conecta el teléfono y la PC a la misma red. Levanta el perfil de HTTPS y copia el certificado de la autoridad local.

   ```powershell
   docker compose --profile movil up -d --build
   docker compose --profile movil cp https:/data/caddy/pki/authorities/local/root.crt ./flexialmacen-ca.crt
   ```

4. Transfiere al teléfono únicamente `flexialmacen-ca.crt` e instálalo como certificado de CA para confiar en el HTTPS de esta prueba. En Android, busca **Instalar certificado de CA** en Ajustes. En iPhone, instala el perfil y habilita la confianza total en **Configuración > General > Información > Configuración de confianza de certificados**. En el teléfono, abre `https://IP_DE_TU_PC:8443/public/` y comprueba que el navegador acepte el certificado. Si la dirección no abre, revisa que el firewall de Windows permita el puerto TCP 8443 en la red privada.
5. En Android, abre la app en Chrome y usa **Instalar** o **Añadir a pantalla de inicio**. En iPhone con iOS 16.4 o posterior, abre Safari y elige **Compartir > Añadir a pantalla de inicio**. Abre la app instalada, entra a tu cuenta y en la campana elige **Activar avisos en este dispositivo**. Acepta el permiso.
6. En otra ventana o dispositivo, deja un producto por encima de su mínimo. Cierra la app en el teléfono y registra una salida que deje el producto justo en el mínimo o por debajo. Docker debe seguir encendido y tener salida a Internet. Si el teléfono tiene conexión, el aviso debe aparecer en el sistema. Para revisar el servicio, ejecuta `docker compose logs push`. El mismo botón en la campana permite desactivar los avisos de ese dispositivo.

La campana muestra los productos que ya tienen stock bajo. El aviso del teléfono se encola cuando un movimiento confirmado cruza el umbral. Si el producto sigue por debajo del mínimo, los movimientos posteriores no generan otro aviso hasta que vuelva a superar el mínimo. Los avisos se mantienen activos al cerrar la app o la sesión hasta que se desactiven desde la campana o desde el navegador.

El perfil HTTPS con certificado local está preparado para una red privada de pruebas. Las cuentas del SQL son de demostración.
