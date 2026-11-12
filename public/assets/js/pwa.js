'use strict';

const pushUrl = '../servicio-inventario/api/push.php';
const tema = document.querySelector('#tema');
const temas = ['sistema', 'claro', 'oscuro'];

const temaSistema = matchMedia('(prefers-color-scheme: dark)');
function aplicarTema(valor) {
    tema.value = temas.includes(valor) ? valor : 'sistema';
    document.documentElement.dataset.tema = tema.value === 'sistema'
        ? (temaSistema.matches ? 'oscuro' : 'claro')
        : tema.value;
}

try {
    aplicarTema(localStorage.getItem('flexialmacen-tema'));
} catch {
    aplicarTema('sistema');
}

temaSistema.addEventListener('change', () => aplicarTema(tema.value));
tema.addEventListener('change', () => {
    aplicarTema(tema.value);
    try {
        localStorage.setItem('flexialmacen-tema', tema.value);
    } catch {
        // El tema sigue activo en la página actual.
    }
});

const registroPwa = (async () => {
    if (!('serviceWorker' in navigator)) return null;
    try {
        await navigator.serviceWorker.register('./service-worker.js');
        return await navigator.serviceWorker.ready;
    } catch (error) {
        console.warn('FlexiAlmacén: service worker no disponible.', error);
        return null;
    }
})();

const botonPush = document.querySelector('#togglePush');
let gestorPush = null;
let clavePush = null;
let suscripcionPush = null;

function bytesVapid(clave) {
    const base64 = clave.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(clave.length / 4) * 4, '=');
    return Uint8Array.from(atob(base64), letra => letra.charCodeAt(0));
}

async function prepararPush() {
    if (!window.isSecureContext || !('PushManager' in window) || !('Notification' in window)) {
        botonPush.textContent = 'Push requiere HTTPS y un navegador compatible';
        return;
    }
    if (gestorPush && clavePush) return;
    try {
        const configuracion = await request(pushUrl);
        if (!configuracion.clavePublica) {
            botonPush.textContent = 'Push pendiente de configurar en el servidor';
            return;
        }
        const registro = await registroPwa;
        if (!registro?.pushManager) return;
        gestorPush = registro.pushManager;
        clavePush = bytesVapid(configuracion.clavePublica);
        suscripcionPush = await gestorPush.getSubscription();
        botonPush.disabled = false;
        botonPush.textContent = suscripcionPush ? 'Desactivar avisos en este dispositivo' : 'Activar avisos en este dispositivo';
    } catch (error) {
        botonPush.textContent = error.transitorio ? 'Conéctate para configurar los avisos' : 'No se pudo preparar Web Push';
    }
}

botonPush.addEventListener('click', async () => {
    if (!gestorPush || !clavePush || sinConexion) return;
    botonPush.disabled = true;
    try {
        if (suscripcionPush) {
            await request(pushUrl, { method: 'DELETE', body: JSON.stringify({ endpoint: suscripcionPush.endpoint }) });
            await suscripcionPush.unsubscribe();
            suscripcionPush = null;
            toast('Avisos desactivados.');
        } else {
            // La suscripción se solicita desde el gesto del usuario.
            const nueva = await gestorPush.subscribe({ userVisibleOnly: true, applicationServerKey: clavePush });
            try {
                await request(pushUrl, { method: 'POST', body: JSON.stringify(nueva) });
            } catch (error) {
                // Si la respuesta se pierde, retirar también el registro remoto es seguro.
                await request(pushUrl, { method: 'DELETE', body: JSON.stringify({ endpoint: nueva.endpoint }) }).catch(() => {});
                await nueva.unsubscribe();
                throw error;
            }
            suscripcionPush = nueva;
            toast('Avisos activados en este dispositivo.');
        }
    } catch (error) {
        toast(Notification.permission === 'denied' ? 'Permite las notificaciones en los ajustes del navegador.' : error.message, true);
    } finally {
        botonPush.disabled = false;
        botonPush.textContent = suscripcionPush ? 'Desactivar avisos en este dispositivo' : 'Activar avisos en este dispositivo';
    }
});

navigator.serviceWorker?.addEventListener('message', event => {
    if (event.data?.tipo === 'inventario-actualizado' && usuario) actualizarInventario();
});
