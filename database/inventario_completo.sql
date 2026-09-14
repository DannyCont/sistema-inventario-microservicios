CREATE DATABASE IF NOT EXISTS inventario_completo
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventario_completo;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password CHAR(64) NOT NULL,
    rol_id TINYINT UNSIGNED NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
);

CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255),
    categoria_id INT UNSIGNED NOT NULL,
    precio DECIMAL(10,2) NOT NULL DEFAULT 0,
    unidad_medida VARCHAR(30) NOT NULL DEFAULT 'Pieza',
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

CREATE TABLE inventario (
    producto_id INT UNSIGNED PRIMARY KEY,
    existencia INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventario_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE movimientos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    tipo ENUM('ENTRADA','SALIDA') NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    existencia_anterior INT UNSIGNED NOT NULL,
    existencia_actual INT UNSIGNED NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

INSERT INTO roles (nombre) VALUES ('Administrador'), ('Almacenista'), ('Consulta');
INSERT INTO categorias (nombre) VALUES ('Electrónica'), ('Papelería'), ('Limpieza'), ('Otros');

-- Contraseña de los tres usuarios: 123456
INSERT INTO usuarios (nombre, apellidos, correo, password, rol_id) VALUES
('Administrador', 'General', 'admin@inventario.com', LOWER(SHA2('123456', 256)), 1),
('Ana', 'Almacén', 'almacen@inventario.com', LOWER(SHA2('123456', 256)), 2),
('Carlos', 'Consulta', 'consulta@inventario.com', LOWER(SHA2('123456', 256)), 3);

INSERT INTO productos (codigo, nombre, descripcion, categoria_id, precio, unidad_medida, stock_minimo) VALUES
('PROD-001', 'Teclado USB', 'Teclado alámbrico', 1, 250.00, 'Pieza', 5),
('PROD-002', 'Cuaderno profesional', 'Cuaderno de 100 hojas', 2, 65.50, 'Pieza', 10);
INSERT INTO inventario (producto_id, existencia) VALUES (1, 12), (2, 8);
