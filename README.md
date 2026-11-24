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

1. Copiar la carpeta `sistema-inventario` dentro de `C:\xampp\htdocs\`.
2. Encender Apache y MySQL desde el panel de XAMPP.
3. Abrir `http://localhost/phpmyadmin`.
4. Seleccionar **Importar** y cargar `database/inventario_completo.sql`.
5. Abrir `http://localhost/sistema-inventario-completo/`.

Si MySQL utiliza otra contraseña, editar `config/database.php`.

## Ejecución con Docker (contenedores)

El proyecto incluye `Dockerfile` y `compose.yaml` para ejecutarse sin XAMPP.

```bash
# 1. Construir la imagen y levantar la aplicación + MySQL
docker compose up -d --build

# 2. Ver la aplicación
#    http://localhost:8082/public/

# 3. Revisar el estado de los contenedores
docker compose ps

# 4. Detener los contenedores
docker compose down
```

El contenedor `web` usa `php:8.2-apache` y el contenedor `db` usa `mysql:8.0`, que carga
automáticamente `database/inventario_completo.sql` la primera vez que se crea el volumen.

> La aplicación se publica en el puerto **8082** porque Jenkins ocupa el puerto 8080.

## Integración continua con Jenkins

El repositorio incluye un `Jenkinsfile` en la raíz con el pipeline de CI:

| Etapa | Qué hace |
|---|---|
| 1. Checkout | Obtiene el código desde GitHub |
| 2. Verificar herramientas | Comprueba `docker` y `docker compose` |
| 3. Pruebas estáticas | `php -l` sobre todos los archivos PHP dentro de un contenedor |
| 4. Construir imagen | `docker build -t sistema-inventario:<build> -t sistema-inventario:latest .` |
| 5. Desplegar | `docker compose up -d` (web + MySQL) |
| 6. Pruebas funcionales | `ci/smoke-test.ps1`: frontend, login y endpoint protegido |
| 7. Evidencia | `docker ps`, `docker images` y versión desplegada |

### Configuración en Jenkins

1. Instalar Jenkins y los complementos **Git**, **Pipeline** y **Docker Pipeline**.
2. Crear una tarea de tipo **Pipeline** llamada `sistema-inventario-ci`.
3. En *Pipeline* → *Definition* elegir **Pipeline script from SCM**.
4. SCM: **Git**, URL `https://github.com/DannyCont/sistema-inventario-microservicios.git`, rama `*/main`.
5. Script Path: `Jenkinsfile`.
6. Ejecutar **Build Now**.

### Webhook de GitHub (opcional)

En GitHub → *Settings → Webhooks → Add webhook*:

- Payload URL: `http://<servidor-jenkins>:8080/github-webhook/`
- Content type: `application/json`
- Evento: *Just the push event*

En la tarea de Jenkins activar **GitHub hook trigger for GITScm polling**.

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

## Estado

Versión académica funcional. No requiere Composer ni dependencias externas.
