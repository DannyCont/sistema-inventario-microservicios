'use strict';

// Una base por ruta permite tener varias instalaciones en el mismo origen.
const almacenLocal = (() => {
    const nombre = 'flexialmacen:' + new URL('./', location.href).pathname;
    let apertura;

    function abrir() {
        if (!apertura) {
            apertura = new Promise((resolve, reject) => {
                const req = indexedDB.open(nombre, 1);
                req.onupgradeneeded = () => {
                    req.result.createObjectStore('datos');
                    req.result.createObjectStore('cola', { keyPath: 'orden', autoIncrement: true })
                        .createIndex('usuario', 'usuario');
                };
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error);
            });
        }
        return apertura;
    }

    async function operar(tabla, modo, operacion) {
        const db = await abrir();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(tabla, modo);
            const req = operacion(tx.objectStore(tabla));
            tx.oncomplete = () => resolve(req.result);
            tx.onabort = () => reject(tx.error ?? req.error);
        });
    }

    return {
        nombre,
        leer: clave => operar('datos', 'readonly', store => store.get(clave)),
        guardar: (clave, valor) => operar('datos', 'readwrite', store => store.put(valor, clave)),
        cola: usuario => operar('cola', 'readonly', store => store.index('usuario').getAll(usuario)),
        agregar: item => operar('cola', 'readwrite', store => store.add(item)),
        actualizar: item => operar('cola', 'readwrite', store => store.put(item)),
        retirar: orden => operar('cola', 'readwrite', store => store.delete(orden))
    };
})();
