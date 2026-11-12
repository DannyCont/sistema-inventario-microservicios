const API={usuarios:'../servicio-usuarios/api',productos:'../servicio-productos/api/productos.php',inventario:'../servicio-inventario/api/inventario.php'};
let usuario=null,productos=[];
const $=s=>document.querySelector(s), $$=s=>document.querySelectorAll(s);
const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
async function request(url, options = {}) {
    const { method = 'GET', body, cuenta = usuario?.id } = options;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 8000);
    try {
        const headers = { 'Content-Type': 'application/json' };
        if (cuenta != null) headers['X-Flexi-User'] = String(cuenta);
        const response = await fetch(url, {
            method, headers, body, signal: controller.signal, cache: 'no-store'
        });
        const data = await response.json().catch(() => null);
        const respuestaApi = typeof data?.ok === 'boolean';
        if (!response.ok || !respuestaApi) {
            const ruta = new URL(url, location.href).pathname;
            const mensaje = respuestaApi && typeof data.mensaje === 'string'
                ? data.mensaje
                : `${method} ${ruta} devolvió una respuesta inválida.`;
            throw Object.assign(new Error(mensaje), {
                status: response.status,
                rechazoApi: respuestaApi && data.ok === false,
                transitorio: response.status >= 500 || response.status === 408 || response.status === 429 || !respuestaApi
            });
        }
        return data;
    } catch (error) {
        if (error.name === 'TypeError' || error.name === 'AbortError') {
            error.red = true;
            error.transitorio = true;
        }
        throw error;
    } finally {
        clearTimeout(timeout);
    }
}
function toast(msg,error=false){const t=$('#toast');t.textContent=msg;t.className=error?'show error':'show';setTimeout(()=>t.className='',2600)}
function formData(form){return Object.fromEntries(new FormData(form).entries())}

let sinConexion = false;
let errorSincronizacion = null;
let fechaInventario = null;
let sincronizacion = null;
let actualizacion = null;
let cerrandoSesion = false;
const puedeMover = u => ['Administrador', 'Almacenista'].includes(u?.rol);
const idLocal = () => String(usuario.id);
const cacheUsuario = (tipo, id = idLocal()) => `${tipo}:${id}`;
const guardarLocal = (clave, valor) => almacenLocal.guardar(clave, valor).catch(() => {});
const tiempoSesionLocal = 24 * 60 * 60 * 1000;

$('#loginForm').addEventListener('submit', async event => {
    event.preventDefault();
    const boton = $('#loginForm button');
    if (boton.disabled) return;
    boton.disabled = true;
    $('#loginMsg').textContent = '';
    try {
        const data = await request(`${API.usuarios}/login.php`, { method: 'POST', body: JSON.stringify(formData(event.target)) });
        await guardarLocal('sesion', { usuario: data.usuario, fecha: Date.now() });
        event.target.reset();
        iniciar(data.usuario);
    } catch (error) {
        $('#loginMsg').textContent = error.red ? 'Conéctate para iniciar sesión.' : error.message;
    } finally {
        boton.disabled = false;
    }
});

$('#logout').addEventListener('click', async () => {
    if (sinConexion || cerrandoSesion) return;
    cerrandoSesion = true;
    try {
        await actualizacion;
        await sincronizacion;
        await request(`${API.usuarios}/logout.php`, { method: 'POST' });
        await guardarLocal('sesion', null);
        location.reload();
    } catch (error) {
        cerrandoSesion = false;
        toast(error.message, true);
    }
});

function iniciar(u, local = false) {
    usuario = u;
    $('#loginView').classList.add('hidden');
    $('#appView').classList.remove('hidden');
    mostrarUsuario(u);
    llenarPerfil();
    actualizarConexion(local);
    actualizarInventario();
}

function mostrarUsuario(u) {
    $('#welcome').textContent = `Hola, ${u.nombre}`;
    $('#roleBadge').textContent = u.rol;
    $$('.admin-only').forEach(el => el.classList.toggle('hidden', u.rol !== 'Administrador'));
    $('[data-section="movimientos"]').classList.toggle('hidden', !puedeMover(u));
    if ($('#movimientos').classList.contains('active') && !puedeMover(u)
        || $('#usuarios').classList.contains('active') && u.rol !== 'Administrador') {
        $('[data-section="resumen"]').click();
    }
}

async function comprobarSesion() {
    try {
        const data = await request(`${API.usuarios}/sesion.php`);
        await guardarLocal('sesion', data.autenticado ? { usuario: data.usuario, fecha: Date.now() } : null);
        if (data.autenticado) iniciar(data.usuario);
    } catch (error) {
        if (!error.transitorio) return;
        const sesion = await almacenLocal.leer('sesion').catch(() => null);
        if (sesion && Date.now() - sesion.fecha < tiempoSesionLocal) {
            iniciar(sesion.usuario, true);
        } else {
            $('#loginMsg').textContent = error.red
                ? 'Conéctate para iniciar sesión y habilitar el acceso local.'
                : error.message;
        }
    }
}

async function pedirSesion() {
    if (cerrandoSesion) return;
    cerrandoSesion = true;
    await guardarLocal('sesion', null);
    location.reload();
}

function actualizarConexion(local, error = null) {
    sinConexion = local;
    const fecha = fechaInventario ? new Date(fechaInventario).toLocaleString('es-MX') : 'sin copia guardada';
    const aviso = error?.status
        ? `El servidor respondió con un error (HTTP ${error.status}). ${error.message}`
        : 'Servidor sin conexión.';
    $('#estadoConexion').textContent = local
        ? `${aviso} Existencias de la última consulta (${fecha}). Los movimientos se guardan en este dispositivo.`
        : '';
    $('#revisarConexion').hidden = !local;
    $('#logout').disabled = local;
    $('#nuevoProducto').disabled = local;
    $$('#productosBody button').forEach(boton => { boton.disabled = local; });
    $$('#usuarios button').forEach(boton => { boton.disabled = local; });
    $('[data-section="usuarios"]').disabled = local;
    $('#perfilForm button[type="submit"]').disabled = local;
    $('#sincronizarAhora').disabled = local || !!sincronizacion;
    if (local) {
        $('#productoDialog').close();
        $('#usuarioDialog').close();
    }
}
$$('nav [data-section]').forEach(b=>b.addEventListener('click',()=>{$$('nav button').forEach(x=>x.classList.remove('active'));b.classList.add('active');$$('.section').forEach(s=>s.classList.remove('active'));$('#'+b.dataset.section).classList.add('active');$('#sectionTitle').textContent=b.textContent.trim();if(b.dataset.section==='historial')cargarHistorial();if(b.dataset.section==='usuarios')cargarUsuarios()}));

function mostrarInventario(datos) {
    productos = datos.productos;
    fechaInventario = datos.fecha;
    const alertas = productos.filter(p => Number(p.existencia) <= Number(p.stock_minimo));
    const seleccion = $('#movProducto').value;
    const filtro = $('#buscarProducto').value.toLowerCase();
    renderProductos(productos.filter(p => [p.codigo, p.nombre, p.categoria].some(v => String(v ?? '').toLowerCase().includes(filtro))));
    renderAlertas(alertas);
    $('#totalProductos').textContent = productos.length;
    $('#totalUnidades').textContent = productos.reduce((n, p) => n + Number(p.existencia), 0);
    $('#totalAlertas').textContent = alertas.length;
    $('#movProducto').innerHTML = productos.map(p => `<option value="${p.id}">${esc(p.codigo)} — ${esc(p.nombre)} (${p.existencia})</option>`).join('');
    if (productos.some(p => String(p.id) === seleccion)) $('#movProducto').value = seleccion;
    $('#movimientoForm button[type="submit"]').disabled = !productos.length;
    actualizarAlertasCampana(alertas);
}

async function cargarTodo(validarSesion = false) {
    const clave = cacheUsuario('inventario');
    try {
        if (validarSesion) {
            const sesion = await request(`${API.usuarios}/sesion.php`);
            if (!sesion.autenticado) {
                await pedirSesion();
                return false;
            }
            usuario = sesion.usuario;
            mostrarUsuario(usuario);
            await guardarLocal('sesion', { usuario, fecha: Date.now() });
        }
        const data = await request(API.productos);
        const copia = { productos: data.productos, fecha: Date.now() };
        await guardarLocal(clave, copia);
        actualizarConexion(false);
        mostrarInventario(copia);
        return true;
    } catch (error) {
        if (error.rechazoApi && error.status === 401) {
            await pedirSesion();
        } else if (error.transitorio) {
            actualizarConexion(true, error);
            const copia = await almacenLocal.leer(clave).catch(() => null);
            mostrarInventario(copia ?? { productos: [], fecha: null });
            actualizarConexion(true, error);
        } else {
            toast(error.message, true);
        }
        return false;
    }
}

function actualizarInventario() {
    if (!usuario || cerrandoSesion) return;
    if (actualizacion) return actualizacion;
    actualizacion = (async () => {
        if (await cargarTodo(true)) {
            prepararPush();
            if (puedeMover(usuario)) await sincronizarCola();
        } else if (puedeMover(usuario)) {
            await renderCola();
        }
    })().catch(error => toast(error.message, true)).finally(() => { actualizacion = null; });
    return actualizacion;
}
function renderAlertas(rows){$('#alertas').innerHTML=rows.length?`<table><thead><tr><th>Producto</th><th>Existencia</th><th>Mínimo</th></tr></thead><tbody>${rows.map(p=>`<tr><td>${esc(p.codigo)} — ${esc(p.nombre)}</td><td class="low">${p.existencia}</td><td>${p.stock_minimo}</td></tr>`).join('')}</tbody></table>`:'<p class="empty">No hay alertas de bajo inventario.</p>'}
function renderProductos(rows){$('#productosBody').innerHTML=rows.length?rows.map(p=>`<tr><td>${esc(p.codigo)}</td><td>${esc(p.nombre)}</td><td>${esc(p.categoria)}</td><td>$${Number(p.precio).toFixed(2)}</td><td class="${Number(p.existencia)<=Number(p.stock_minimo)?'low':'ok'}">${p.existencia}</td><td>${p.stock_minimo}</td>${usuario.rol==='Administrador'?`<td><button ${sinConexion?'disabled':''} class="small" onclick="editarProducto(${p.id})">Editar</button> <button ${sinConexion?'disabled':''} class="small danger" onclick="eliminarProducto(${p.id})">Eliminar</button></td>`:''}</tr>`).join(''):'<tr><td colspan="7" class="empty">No hay productos.</td></tr>'}
$('#buscarProducto').addEventListener('input',e=>{const q=e.target.value.toLowerCase();renderProductos(productos.filter(p=>[p.codigo,p.nombre,p.categoria].some(v=>String(v ?? '').toLowerCase().includes(q))))});

$('#nuevoProducto').addEventListener('click',()=>{$('#productoForm').reset();$('#productoForm [name=id]').value='';$('#productoFormTitle').textContent='Nuevo producto';$('#productoDialog').showModal()});
window.editarProducto=id=>{const p=productos.find(x=>Number(x.id)===id),f=$('#productoForm');Object.keys(p).forEach(k=>{if(f.elements[k])f.elements[k].value=p[k]});$('#productoFormTitle').textContent='Editar producto';$('#productoDialog').showModal()};
window.eliminarProducto=async id=>{if(!confirm('¿Deseas desactivar este producto?'))return;try{const d=await request(`${API.productos}?id=${id}`,{method:'DELETE'});toast(d.mensaje);actualizarInventario()}catch(e){toast(e.message,true)}};
$('#productoForm').addEventListener('submit',async e=>{e.preventDefault();const d=formData(e.target),method=d.id?'PUT':'POST';try{const r=await request(API.productos,{method,body:JSON.stringify(d)});$('#productoDialog').close();toast(r.mensaje);actualizarInventario()}catch(err){toast(err.message,true)}});

function conBloqueoCola(trabajo) {
    return navigator.locks
        ? navigator.locks.request(`${almacenLocal.nombre}:cola:${idLocal()}`, trabajo)
        : trabajo();
}

async function renderCola() {
    const lista = $('#listaCola');
    try {
        const items = await almacenLocal.cola(idLocal());
        lista.replaceChildren();
        $('#estadoCola').textContent = items.length
            ? `${items.length} por resolver. ${errorSincronizacion ?? 'La existencia mostrada es la última confirmada.'}`
            : 'No hay movimientos pendientes.';
        for (const item of items) {
            const li = document.createElement('li');
            li.textContent = `${item.datos.tipo} · ${item.datos.cantidad} · ${item.producto} · ${item.error ?? 'Pendiente'}`;
            if (item.error) {
                const retirar = document.createElement('button');
                retirar.type = 'button';
                retirar.className = 'small danger';
                retirar.textContent = 'Retirar';
                retirar.disabled = !!sincronizacion;
                retirar.addEventListener('click', async () => {
                    if (!confirm('¿Retirar este movimiento rechazado de la cola?')) return;
                    try {
                        await conBloqueoCola(async () => {
                            const actuales = await almacenLocal.cola(idLocal());
                            if (actuales.some(actual => actual.orden === item.orden && actual.error)) {
                                await almacenLocal.retirar(item.orden);
                            }
                        });
                        await renderCola();
                        if (!sinConexion) await sincronizarCola();
                    } catch (error) {
                        toast(error.message, true);
                    }
                });
                li.append(' ', retirar);
            }
            lista.append(li);
        }
    } catch {
        $('#estadoCola').textContent = 'El almacenamiento local no está disponible.';
    }
}

function sincronizarCola(reintentar = false) {
    if (sincronizacion || sinConexion || cerrandoSesion || !puedeMover(usuario)) return sincronizacion;
    const cuenta = idLocal();
    errorSincronizacion = null;
    let enviados = 0;
    sincronizacion = conBloqueoCola(async () => {
        const items = await almacenLocal.cola(cuenta);
        for (const item of items) {
            if (cerrandoSesion || sinConexion || (item.error && !reintentar)) break;
            try {
                const respuesta = await request(API.inventario, {
                    method: 'POST', cuenta, body: JSON.stringify(item.datos)
                });
                if (!respuesta.ok) throw new Error(respuesta.mensaje);
                await almacenLocal.retirar(item.orden);
                enviados++;
            } catch (error) {
                if (error.rechazoApi && error.status === 401) {
                    await pedirSesion();
                } else if (error.rechazoApi && [403, 404, 409, 422].includes(error.status)) {
                    await almacenLocal.actualizar({ ...item, error: error.message });
                } else {
                    if (item.error && !error.rechazoApi && error.status) {
                        // Una respuesta HTML no confirma un rechazo del movimiento.
                        await almacenLocal.actualizar({ ...item, error: null });
                    }
                    const codigo = error.status ? ` (HTTP ${error.status})` : '';
                    errorSincronizacion = `No se pudo sincronizar${codigo}. ${error.red ? 'Se reintentará al recuperar el acceso al servidor.' : error.message}`;
                    if (error.red) actualizarConexion(true);
                }
                break;
            }
        }
    }).then(async () => {
        if (enviados && !cerrandoSesion && !sinConexion) {
            await cargarTodo();
            if ($('#historial').classList.contains('active')) await cargarHistorial();
            toast(enviados === 1 ? 'Movimiento sincronizado.' : `${enviados} movimientos sincronizados.`);
        }
    }).catch(() => toast('No se pudo acceder a la cola local.', true)).finally(async () => {
        sincronizacion = null;
        $('#sincronizarAhora').disabled = sinConexion;
        await renderCola();
    });
    $('#sincronizarAhora').disabled = true;
    renderCola();
    return sincronizacion;
}

function identificadorMovimiento() {
    // getRandomValues también funciona en la instalación HTTP de desarrollo.
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
}

$('#sincronizarAhora').addEventListener('click', () => sincronizarCola(true));
$('#movimientoForm').addEventListener('submit', async event => {
    event.preventDefault();
    if (!puedeMover(usuario) || cerrandoSesion) return;
    const boton = event.submitter ?? $('#movimientoForm button[type="submit"]');
    if (boton.disabled) return;
    boton.disabled = true;
    try {
        const datos = formData(event.target);
        datos.motivo = datos.motivo.trim();
        if (!datos.motivo) throw new Error('Escribe el motivo del movimiento.');
        datos.solicitud_id = identificadorMovimiento();
        const producto = productos.find(p => String(p.id) === datos.producto_id);
        await almacenLocal.agregar({ usuario: idLocal(), producto: producto?.nombre ?? datos.producto_id, datos });
        event.target.reset();
        toast('Movimiento guardado en este dispositivo.');
        await renderCola();
        if (!sinConexion) {
            await sincronizarCola();
            // Otra sincronización puede haber tomado su lista antes de este registro.
            if (!sinConexion && !cerrandoSesion) await sincronizarCola();
        }
    } catch (error) {
        toast(error.message || 'No se pudo guardar el movimiento en este dispositivo.', true);
    } finally {
        boton.disabled = !productos.length;
    }
});

async function cargarHistorial() {
    const clave = cacheUsuario('historial');
    let movimientos;
    try {
        if (!sinConexion) {
            const datos = await request(`${API.inventario}?tipo=movimientos`);
            movimientos = datos.movimientos;
            await guardarLocal(clave, movimientos);
        }
    } catch (error) {
        if (error.rechazoApi && error.status === 401) return pedirSesion();
        if (error.red) actualizarConexion(true);
        else toast(error.message, true);
    }
    movimientos ??= await almacenLocal.leer(clave).catch(() => null);
    $('#historialBody').innerHTML = movimientos?.length
        ? movimientos.map(m => `<tr><td>${esc(m.creado_en)}</td><td>${esc(m.codigo)} — ${esc(m.producto)}</td><td><span class="${m.tipo === 'ENTRADA' ? 'ok' : 'low'}">${esc(m.tipo)}</span></td><td>${m.cantidad}</td><td>${m.existencia_anterior}</td><td>${m.existencia_actual}</td><td>${esc(m.usuario)}</td><td>${esc(m.motivo)}</td></tr>`).join('')
        : `<tr><td colspan="8" class="empty">${movimientos ? 'Aún no existen movimientos.' : 'El historial todavía no tiene una copia local.'}</td></tr>`;
}

$('#nuevoUsuario').addEventListener('click',()=>{$('#usuarioForm').reset();$('#usuarioDialog').showModal()});
$('#usuarioForm').addEventListener('submit',async e=>{e.preventDefault();try{const d=await request(`${API.usuarios}/usuarios.php`,{method:'POST',body:JSON.stringify(formData(e.target))});$('#usuarioDialog').close();toast(d.mensaje);cargarUsuarios()}catch(err){toast(err.message,true)}});
async function cargarUsuarios(){try{const d=await request(`${API.usuarios}/usuarios.php`);$('#usuariosBody').innerHTML=d.usuarios.map(u=>`<tr><td>${esc(u.nombre)} ${esc(u.apellidos)}</td><td>${esc(u.correo)}</td><td><select id="rol-${u.id}"><option value="1" ${u.rol==='Administrador'?'selected':''}>Administrador</option><option value="2" ${u.rol==='Almacenista'?'selected':''}>Almacenista</option><option value="3" ${u.rol==='Consulta'?'selected':''}>Consulta</option></select></td><td><select id="activo-${u.id}"><option value="1" ${Number(u.activo)?'selected':''}>Activo</option><option value="0" ${!Number(u.activo)?'selected':''}>Inactivo</option></select></td><td><button class="small" onclick="actualizarUsuario(${u.id})">Guardar</button></td></tr>`).join('')}catch(e){toast(e.message,true)}}
window.actualizarUsuario=async id=>{try{const d=await request(`${API.usuarios}/usuarios.php`,{method:'PUT',body:JSON.stringify({id,rol_id:$(`#rol-${id}`).value,activo:$(`#activo-${id}`).value})});toast(d.mensaje)}catch(e){toast(e.message,true)}};

function llenarPerfil(){const f=$('#perfilForm');f.nombre.value=usuario.nombre;f.apellidos.value=usuario.apellidos;f.correo.value=usuario.correo;f.rol.value=usuario.rol}
$('#perfilForm').addEventListener('submit',async e=>{e.preventDefault();try{const d=await request(`${API.usuarios}/perfil.php`,{method:'PUT',body:JSON.stringify(formData(e.target))});usuario=d.usuario;$('#welcome').textContent=`Hola, ${usuario.nombre}`;toast(d.mensaje)}catch(err){toast(err.message,true)}});
$$('[data-close]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));
let actualizarAlertasCampana;

function initNotifications() {
    const button = $('#notificationButton');
    const panel = $('#notificationPanel');
    const count = $('#notificationCount');
    const list = $('#notificationList');
    const leidas = new Map();
    let alertas = [];

    function render() {
        list.replaceChildren();
        if (!alertas.length) list.textContent = 'No hay alertas de stock bajo.';
        for (const producto of alertas) {
            const item = document.createElement('div');
            item.className = 'notification-item';
            const contenido = document.createElement('div');
            contenido.className = 'notification-item-content';
            const titulo = document.createElement('strong');
            titulo.textContent = producto.nombre;
            const detalle = document.createElement('p');
            detalle.textContent = `Existencia ${producto.existencia} · Mínimo ${producto.stock_minimo}`;
            const leida = leidas.get(producto.id) === producto.existencia;
            item.classList.toggle('is-read', leida);
            contenido.append(titulo, detalle);
            item.append(contenido);
            list.append(item);
        }
        const pendientes = alertas.filter(p => leidas.get(p.id) !== p.existencia).length;
        count.textContent = pendientes > 99 ? '99+' : String(pendientes);
        count.hidden = !pendientes;
        button.setAttribute('aria-label', pendientes ? `Notificaciones: ${pendientes} sin leer` : 'Abrir notificaciones');
    }

    function abrir(visible) {
        panel.hidden = !visible;
        button.setAttribute('aria-expanded', String(visible));
    }

    button.addEventListener('click', () => abrir(panel.hidden));
    document.addEventListener('click', event => {
        if (!event.target.closest('.notification-container')) abrir(false);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            abrir(false);
            button.focus();
        }
    });
    $('#markNotificationsRead').addEventListener('click', () => {
        alertas.forEach(p => leidas.set(p.id, p.existencia));
        render();
    });

    actualizarAlertasCampana = nuevas => {
        for (const id of leidas.keys()) {
            if (!nuevas.some(p => p.id === id)) leidas.delete(id);
        }
        alertas = nuevas;
        render();
    };
}

initNotifications();
comprobarSesion();
window.addEventListener('offline', () => {
    if (!usuario) return;
    actualizarConexion(true);
    mostrarInventario({ productos, fecha: fechaInventario });
});
function revisarConexion() {
    if (!usuario) return comprobarSesion();
    // Una petición iniciada antes de recuperar la red todavía puede fallar.
    if (actualizacion) return actualizacion.then(() => sinConexion && actualizarInventario());
    return actualizarInventario();
}

$('#revisarConexion').addEventListener('click', revisarConexion);
window.addEventListener('online', revisarConexion);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && navigator.onLine) actualizarInventario();
});
setInterval(() => {
    if (sinConexion && navigator.onLine && document.visibilityState === 'visible') revisarConexion();
}, 5000);
setInterval(() => {
    if (!sinConexion && document.visibilityState === 'visible' && navigator.onLine && !sincronizacion) actualizarInventario();
}, 30000);
