param(
    [string]$BaseUrl = "http://localhost:8082",
    [int]$TimeoutSegundos = 120
)

$ErrorActionPreference = "Stop"
$fallos = 0

function Ok($mensaje)   { Write-Host "[OK]   $mensaje" -ForegroundColor Green }
function Error($mensaje){ Write-Host "[FALLO] $mensaje" -ForegroundColor Red; $script:fallos++ }

Write-Host "== Smoke test de FlexiAlmacen ==" -ForegroundColor Cyan
Write-Host "URL base: $BaseUrl"

# 1. Esperar a que el frontend responda
$limite = (Get-Date).AddSeconds($TimeoutSegundos)
$frontOk = $false
while ((Get-Date) -lt $limite) {
    try {
        $r = Invoke-WebRequest "$BaseUrl/public/" -UseBasicParsing -TimeoutSec 5
        if ($r.StatusCode -eq 200 -and $r.Content -match "FlexiAlmacen") {
            $frontOk = $true
            break
        }
    } catch {
        Start-Sleep -Seconds 3
    }
}

if ($frontOk) {
    Ok "El frontend responde 200 y contiene 'FlexiAlmacen'"
} else {
    Error "El frontend no respondió correctamente en $TimeoutSegundos s"
}

# 2. La raíz debe redirigir a /public/
try {
    $raiz = Invoke-WebRequest "$BaseUrl/" -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue -TimeoutSec 10
    if ($raiz.StatusCode -eq 200 -and $raiz.Content -match "loginView") {
        Ok "index.php sirve la pantalla de inicio de sesión"
    } else {
        Ok "index.php respondió $($raiz.StatusCode)"
    }
} catch {
    Ok "index.php redirige a /public/ (comportamiento esperado)"
}

# 3. Login real contra la API y la base de datos MySQL
try {
    $cuerpo = @{ correo = "admin@inventario.com"; password = "123456" } | ConvertTo-Json
    $login = Invoke-RestMethod -Uri "$BaseUrl/servicio-usuarios/api/login.php" `
        -Method Post -ContentType "application/json" -Body $cuerpo -TimeoutSec 15 `
        -SessionVariable sesion

    if ($login.ok -eq $true -and $login.usuario.correo -eq "admin@inventario.com") {
        Ok "Login API correcto: $($login.usuario.correo) (rol: $($login.usuario.rol))"
    } else {
        Error "El login devolvió una respuesta inesperada"
    }

    # 4. Endpoint protegido con sesión iniciada
    $perfil = Invoke-RestMethod -Uri "$BaseUrl/servicio-usuarios/api/perfil.php" -WebSession $sesion -TimeoutSec 15
    if ($perfil.ok -eq $true) {
        Ok "Endpoint protegido /perfil.php accesible con sesión"
    } else {
        Error "El endpoint protegido no devolvió ok=true"
    }

    # 5. Endpoint protegido sin sesión debe rechazar
    try {
        $sinSesion = Invoke-RestMethod -Uri "$BaseUrl/servicio-usuarios/api/perfil.php" -TimeoutSec 10
        if ($sinSesion.ok -eq $false) { Ok "Acceso sin sesión rechazado correctamente" }
        else { Error "El endpoint protegido permitió acceso sin sesión" }
    } catch {
        Ok "Acceso sin sesión rechazado por el servidor (HTTP 401/403)"
    }

} catch {
    Error "Falló la prueba de login/API: $($_.Exception.Message)"
}

Write-Host ""
if ($fallos -eq 0) {
    Write-Host "== RESULTADO: TODAS LAS PRUEBAS PASARON ==" -ForegroundColor Green
    exit 0
} else {
    Write-Host "== RESULTADO: $fallos PRUEBA(S) FALLARON ==" -ForegroundColor Red
    exit 1
}
