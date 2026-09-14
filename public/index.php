<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sistema de Inventario</title><link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<main id="loginView" class="login-wrap">
  <form id="loginForm" class="card login-card">
    <div class="brand">SI</div><h1>Sistema de inventario</h1><p class="muted">Ingresa con tu cuenta</p>
    <label>Correo<input type="email" name="correo" value="admin@inventario.com" required></label>
    <label>Contraseña<input type="password" name="password" value="123456" required></label>
    <button type="submit">Iniciar sesión</button><p id="loginMsg" class="message"></p>
  </form>
</main>

<div id="appView" class="app hidden">
  <aside>
    <div class="logo"><span>SI</span><strong>Inventario</strong></div>
    <nav>
      <button data-section="resumen" class="active">Resumen</button>
      <button data-section="productos">Productos</button>
      <button data-section="movimientos">Movimientos</button>
      <button data-section="historial">Historial</button>
      <button data-section="usuarios" class="admin-only">Usuarios</button>
      <button data-section="perfil">Mi perfil</button>
    </nav>
    <button id="logout" class="danger ghost">Cerrar sesión</button>
  </aside>
  <div class="content">
    <header><div><h2 id="sectionTitle">Resumen</h2><p id="welcome" class="muted"></p></div><span id="roleBadge" class="badge"></span></header>

    <section id="resumen" class="section active">
      <div class="stats"><article><small>Productos</small><strong id="totalProductos">0</strong></article><article><small>Unidades</small><strong id="totalUnidades">0</strong></article><article class="warning"><small>Alertas</small><strong id="totalAlertas">0</strong></article></div>
      <div class="card"><h3>Productos con bajo inventario</h3><div id="alertas"></div></div>
    </section>

    <section id="productos" class="section">
      <div class="toolbar"><input id="buscarProducto" placeholder="Buscar por código, nombre o categoría"><button id="nuevoProducto" class="admin-only">+ Nuevo producto</button></div>
      <div class="card table-wrap"><table><thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Existencia</th><th>Stock mín.</th><th class="admin-only">Acciones</th></tr></thead><tbody id="productosBody"></tbody></table></div>
    </section>

    <section id="movimientos" class="section">
      <form id="movimientoForm" class="card form-grid">
        <h3>Registrar entrada o salida</h3>
        <label>Producto<select name="producto_id" id="movProducto" required></select></label>
        <label>Tipo<select name="tipo"><option>ENTRADA</option><option>SALIDA</option></select></label>
        <label>Cantidad<input type="number" name="cantidad" min="1" required></label>
        <label class="wide">Motivo<input name="motivo" maxlength="255" required placeholder="Compra, venta, ajuste..."></label>
        <button type="submit">Registrar movimiento</button>
      </form>
    </section>

    <section id="historial" class="section"><div class="card table-wrap"><table><thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Anterior</th><th>Actual</th><th>Responsable</th><th>Motivo</th></tr></thead><tbody id="historialBody"></tbody></table></div></section>

    <section id="usuarios" class="section admin-only">
      <div class="toolbar"><span></span><button id="nuevoUsuario">+ Nuevo usuario</button></div>
      <div class="card table-wrap"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acción</th></tr></thead><tbody id="usuariosBody"></tbody></table></div>
    </section>

    <section id="perfil" class="section"><form id="perfilForm" class="card form-grid"><h3>Datos personales</h3><label>Nombre<input name="nombre" required></label><label>Apellidos<input name="apellidos" required></label><label>Correo<input name="correo" readonly></label><label>Rol<input name="rol" readonly></label><button type="submit">Guardar cambios</button></form></section>
  </div>
</div>

<dialog id="productoDialog"><form id="productoForm" class="modal-form"><div class="modal-title"><h3 id="productoFormTitle">Nuevo producto</h3><button type="button" data-close>×</button></div><input type="hidden" name="id"><label>Código<input name="codigo" required></label><label>Nombre<input name="nombre" required></label><label>Descripción<input name="descripcion"></label><label>Categoría<select name="categoria_id"><option value="1">Electrónica</option><option value="2">Papelería</option><option value="3">Limpieza</option><option value="4">Otros</option></select></label><div class="two"><label>Precio<input type="number" name="precio" min="0" step="0.01" required></label><label>Stock mínimo<input type="number" name="stock_minimo" min="0" required></label></div><label>Unidad de medida<input name="unidad_medida" value="Pieza" required></label><button type="submit">Guardar producto</button></form></dialog>

<dialog id="usuarioDialog"><form id="usuarioForm" class="modal-form"><div class="modal-title"><h3>Nuevo usuario</h3><button type="button" data-close>×</button></div><label>Nombre<input name="nombre" required></label><label>Apellidos<input name="apellidos" required></label><label>Correo<input type="email" name="correo" required></label><label>Contraseña<input type="password" name="password" minlength="6" required></label><label>Rol<select name="rol_id"><option value="1">Administrador</option><option value="2">Almacenista</option><option value="3">Consulta</option></select></label><button type="submit">Registrar usuario</button></form></dialog>
<div id="toast"></div><script src="assets/js/app.js"></script>
</body></html>
