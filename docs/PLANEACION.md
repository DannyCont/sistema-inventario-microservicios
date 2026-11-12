# Planeación del sistema

## Propósito

Desarrollar un sistema web para controlar usuarios, productos y movimientos de inventario mediante módulos separados por responsabilidad.

## Requerimientos funcionales

1. Registrar usuarios e iniciar sesión.
2. Consultar y actualizar el perfil.
3. Asignar roles y permisos.
4. Registrar, consultar, actualizar y desactivar productos.
5. Consultar existencias.
6. Registrar entradas y salidas.
7. Consultar el historial de movimientos.
8. Alertar cuando la existencia alcance el stock mínimo.

## Reglas de negocio

- No se aceptan correos ni códigos de producto duplicados.
- No se permiten cantidades o precios negativos.
- Una salida no puede superar la existencia disponible.
- Cada movimiento conserva responsable, motivo, fecha y existencias anterior y actual.
- Los productos con historial se desactivan en lugar de eliminarse físicamente.

## Fases

1. Análisis de requerimientos y roles.
2. Diseño de base de datos y estructura.
3. Desarrollo del servicio de usuarios.
4. Desarrollo del servicio de productos.
5. Desarrollo del servicio de inventario.
6. Integración de la interfaz.
7. Pruebas y documentación.

## Pruebas principales

- Inicio de sesión correcto e incorrecto.
- Restricción de funciones según el rol.
- Registro duplicado de usuario o producto.
- Alta y modificación de productos.
- Entrada que aumente correctamente el stock.
- Salida que reduzca el stock.
- Rechazo de una salida sin unidades suficientes.
- Generación de alerta al alcanzar el stock mínimo.

