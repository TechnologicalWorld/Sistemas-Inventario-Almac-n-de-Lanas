-- ====================================================
-- 1. USUARIOS DEL SISTEMA
-- ====================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('administrador', 'vendedor') NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    telefono VARCHAR(20),
    fecha_creacion DATE DEFAULT (CURRENT_DATE),
    ultimo_login DATETIME,
    INDEX idx_usuario_activo (activo),
    INDEX idx_usuario_rol (rol)
) ENGINE=InnoDB;

-- ====================================================
-- 2. PROVEEDORES
-- ====================================================
CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    ciudad VARCHAR(50) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    credito_limite DECIMAL(12,2) DEFAULT 0.00,
    saldo_actual DECIMAL(12,2) DEFAULT 0.00,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro DATE DEFAULT (CURRENT_DATE),
    observaciones TEXT,
    INDEX idx_proveedor_activo (activo)
) ENGINE=InnoDB;

-- ====================================================
-- 3. CATEGORIAS
-- ====================================================
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    subpaquetes_por_paquete INT DEFAULT 10,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE CASCADE,
    INDEX idx_categoria_proveedor (proveedor_id)
) ENGINE=InnoDB;

-- ====================================================
-- 4. PRODUCTOS (COLORES por código)
-- ====================================================
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    nombre_color VARCHAR(100) NOT NULL,
    proveedor_id INT NOT NULL,
    categoria_id INT NOT NULL,
    precio_menor DECIMAL(10,2) NOT NULL,
    precio_mayor DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    tiene_stock TINYINT(1) DEFAULT 1,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    UNIQUE KEY uk_producto_proveedor (proveedor_id, codigo, categoria_id),
    INDEX idx_producto_codigo (codigo),
    INDEX idx_producto_activo (activo)
) ENGINE=InnoDB;

-- ====================================================
-- 5. INVENTARIO FISICO
-- ====================================================
CREATE TABLE inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    paquetes_completos INT DEFAULT 0,
    subpaquetes_sueltos INT DEFAULT 0,
    subpaquetes_por_paquete INT DEFAULT 10,
    -- Cálculo automático del inventario total
    total_subpaquetes INT GENERATED ALWAYS AS 
        ((paquetes_completos * subpaquetes_por_paquete) + subpaquetes_sueltos) STORED,
    costo_paquete DECIMAL(10,2),
    ubicacion VARCHAR(50),
    fecha_ultimo_ingreso DATE,
    fecha_ultima_salida DATE,
    usuario_registro INT,
    observaciones TEXT,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_registro) REFERENCES usuarios(id),
    UNIQUE KEY uk_inventario_producto (producto_id)
) ENGINE=InnoDB;

-- ====================================================
-- 6. CLIENTES
-- ====================================================
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    ciudad VARCHAR(50),
    telefono VARCHAR(20),
    tipo_documento ENUM('DNI', 'RUC', 'Cedula') DEFAULT 'DNI',
    numero_documento VARCHAR(20),
    limite_credito DECIMAL(12,2) DEFAULT 0.00,
    saldo_actual DECIMAL(12,2) DEFAULT 0.00,
    total_comprado DECIMAL(15,2) DEFAULT 0.00,
    compras_realizadas INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro DATE DEFAULT (CURRENT_DATE),
    observaciones TEXT,
    INDEX idx_cliente_codigo (codigo),
    INDEX idx_cliente_activo (activo)
) ENGINE=InnoDB;

-- ====================================================
-- 7. VENTAS 
-- ====================================================
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_venta VARCHAR(50) UNIQUE NOT NULL,
    cliente_id INT,
    cliente_contado VARCHAR(100),
    vendedor_id INT NOT NULL,
    tipo_venta ENUM('menor', 'mayor') DEFAULT 'menor',
    tipo_pago ENUM('contado', 'credito', 'mixto') NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    descuento DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL,
    -- PAGOS AL MOMENTO DE LA VENTA
    pago_inicial DECIMAL(12,2) DEFAULT 0.00,
    metodo_pago_inicial ENUM('efectivo', 'transferencia', 'QR', 'mixto') DEFAULT 'efectivo',
    referencia_pago_inicial VARCHAR(100),
    es_venta_rapida TINYINT(1) DEFAULT 0,
    -- DEUDA PENDIENTE
    debe DECIMAL(12,2) GENERATED ALWAYS AS (total - pago_inicial) STORED,
    estado ENUM('pendiente', 'pagada', 'cancelada') DEFAULT 'pendiente',
    -- FECHAS Y HORAS
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME,
    -- CONTROL
    impreso TINYINT(1) DEFAULT 0,
    anulado TINYINT(1) DEFAULT 0,
    motivo_anulacion TEXT,
    observaciones TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (vendedor_id) REFERENCES usuarios(id),
    INDEX idx_ventas_fecha (fecha),
    INDEX idx_ventas_cliente (cliente_id),
    INDEX idx_ventas_estado (estado),
    INDEX idx_ventas_vendedor (vendedor_id)
) ENGINE=InnoDB;

-- ====================================================
-- 8. VENTA_DETALLES 
-- ====================================================
CREATE TABLE venta_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad_subpaquetes INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    hora_extraccion TIME NOT NULL,
    usuario_extraccion INT,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_extraccion) REFERENCES usuarios(id),
    INDEX idx_detalle_venta (venta_id),
    INDEX idx_detalle_hora (hora_extraccion)
) ENGINE=InnoDB;

-- ====================================================
-- 9. PROVEEDORES_ESTADO_CUENTAS 
-- ====================================================
CREATE TABLE proveedores_estado_cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    -- CAMPOS DEL EXCEL:
    codigo_proveedor VARCHAR(20),
    nombre_proveedor VARCHAR(100),
    ciudad_proveedor VARCHAR(50),
    -- MOVIMIENTOS:
    compra DECIMAL(12,2) DEFAULT 0.00,
    a_cuenta DECIMAL(12,2) DEFAULT 0.00,
    adelanto DECIMAL(12,2) DEFAULT 0.00,
    saldo DECIMAL(12,2) NOT NULL,
    -- CONTROL:
    fecha DATE NOT NULL,
    referencia VARCHAR(100),
    descripcion VARCHAR(255),
    usuario_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_estado_proveedor_fecha (proveedor_id, fecha)
) ENGINE=InnoDB;

-- ====================================================
-- 10. CLIENTES_CUENTAS_COBRAR
-- ====================================================
CREATE TABLE clientes_cuentas_cobrar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    venta_id INT NOT NULL,
    monto_total DECIMAL(12,2) NOT NULL,
    saldo_pendiente DECIMAL(12,2) NOT NULL,
    fecha_vencimiento DATE,
    estado ENUM('pendiente', 'pagada', 'vencida') DEFAULT 'pendiente',
    usuario_registro INT NOT NULL,
    fecha_registro DATE DEFAULT (CURRENT_DATE),
    observaciones TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (usuario_registro) REFERENCES usuarios(id),
    INDEX idx_cobrar_cliente (cliente_id),
    INDEX idx_cobrar_estado (estado)
) ENGINE=InnoDB;

-- ====================================================
-- 11. PAGOS_CLIENTES (ABONOS Y PAGOS)
-- ====================================================
CREATE TABLE pagos_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('pago_inicial', 'abono', 'pago_total') NOT NULL,
    cliente_id INT NOT NULL,
    venta_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'transferencia', 'QR') NOT NULL,
    referencia VARCHAR(100),
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    usuario_id INT NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_pagos_cliente (cliente_id),
    INDEX idx_pagos_fecha (fecha),
    INDEX idx_pagos_tipo (tipo)
) ENGINE=InnoDB;

-- ====================================================
-- 12. PAGOS_PROVEEDORES
-- ====================================================
CREATE TABLE pagos_proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'transferencia', 'QR') NOT NULL,
    referencia VARCHAR(100),
    fecha DATE NOT NULL,
    usuario_id INT NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_pagos_proveedor (proveedor_id),
    INDEX idx_pagos_proveedor_fecha (proveedor_id, fecha)
) ENGINE=InnoDB;

-- ====================================================
-- 13. MOVIMIENTOS_CAJA (CON GASTOS VARIOS)
-- ====================================================
CREATE TABLE movimientos_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('ingreso', 'gasto') NOT NULL,
    categoria ENUM('venta_contado', 'pago_inicial', 'abono_cliente', 
                   'gasto_almuerzo', 'gasto_varios', 'pago_proveedor', 'otros') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    referencia_venta VARCHAR(50),
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    usuario_id INT NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_movimientos_fecha (fecha),
    INDEX idx_movimientos_tipo (tipo)
) ENGINE=InnoDB;

-- ====================================================
-- 14. OTROS_PRODUCTOS
-- ====================================================
CREATE TABLE otros_productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    categoria VARCHAR(50),
    unidad ENUM('unidad', 'paquete', 'docena') DEFAULT 'unidad',
    precio_compra DECIMAL(10,2),
    precio_venta DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    stock_minimo INT DEFAULT 5,
    activo TINYINT(1) DEFAULT 1,
    fecha_ingreso DATE DEFAULT (CURRENT_DATE),
    observaciones TEXT,
    INDEX idx_otros_codigo (codigo),
    INDEX idx_otros_activo (activo)
) ENGINE=InnoDB;

-- ====================================================
-- 15. VENTA_OTROS_PRODUCTOS
-- ====================================================
CREATE TABLE venta_otros_productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES otros_productos(id),
    INDEX idx_venta_otros_venta (venta_id)
) ENGINE=InnoDB;

-- ====================================================
-- 16. HISTORIAL_INVENTARIO
-- ====================================================
CREATE TABLE historial_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    tipo_movimiento ENUM('ingreso', 'venta', 'ajuste', 'devolucion') NOT NULL,
    paquetes_anteriores INT,
    subpaquetes_anteriores INT,
    paquetes_nuevos INT,
    subpaquetes_nuevos INT,
    diferencia INT,
    referencia VARCHAR(100),
    fecha_hora DATETIME NOT NULL,
    usuario_id INT NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_historial_producto_fecha (producto_id, fecha_hora)
) ENGINE=InnoDB;

-- ====================================================
-- SISTEMA_CONFIG
-- ====================================================
CREATE TABLE sistema_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_nombre VARCHAR(100) DEFAULT 'TIENDA DE LANAS',
    moneda VARCHAR(10) DEFAULT 'Bs',
    telefono_empresa VARCHAR(20),
    direccion_empresa TEXT,
    impresora_termica TINYINT(1) DEFAULT 1,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ====================================================
-- TRIGGERS
-- ====================================================

DELIMITER $$

-- ====================================================
-- ELIMINAR TRIGGERS EXISTENTES
-- ====================================================
DROP TRIGGER IF EXISTS after_venta_detalle_insert$$
DROP TRIGGER IF EXISTS after_venta_insert$$
DROP TRIGGER IF EXISTS after_pago_cliente_insert$$
DROP TRIGGER IF EXISTS after_inventario_insert$$
DROP TRIGGER IF EXISTS after_inventario_update$$
DROP TRIGGER IF EXISTS after_pago_proveedor_insert$$

-- ====================================================
-- TRIGGERS
-- ====================================================

-- Trigger para actualizar inventario después de venta
CREATE TRIGGER after_venta_detalle_insert
AFTER INSERT ON venta_detalles
FOR EACH ROW
BEGIN
    DECLARE v_subpaquetes_por_paquete INT;
    DECLARE v_paquetes_actuales INT;
    DECLARE v_subpaquetes_actuales INT;
    DECLARE v_total_subpaquetes INT;
    DECLARE v_nuevo_total INT;
    
    SELECT i.paquetes_completos, i.subpaquetes_sueltos, i.subpaquetes_por_paquete
    INTO v_paquetes_actuales, v_subpaquetes_actuales, v_subpaquetes_por_paquete
    FROM inventario i
    WHERE i.producto_id = NEW.producto_id;
    
    SET v_total_subpaquetes = (v_paquetes_actuales * v_subpaquetes_por_paquete) + v_subpaquetes_actuales;
    SET v_nuevo_total = v_total_subpaquetes - NEW.cantidad_subpaquetes;
    
    UPDATE inventario i
    SET 
        i.paquetes_completos = FLOOR(v_nuevo_total / v_subpaquetes_por_paquete),
        i.subpaquetes_sueltos = MOD(v_nuevo_total, v_subpaquetes_por_paquete),
        i.fecha_ultima_salida = CURRENT_DATE
    WHERE i.producto_id = NEW.producto_id;
    
    INSERT INTO historial_inventario (
        producto_id, tipo_movimiento, 
        paquetes_anteriores, subpaquetes_anteriores,
        paquetes_nuevos, subpaquetes_nuevos,
        diferencia, referencia, fecha_hora, usuario_id, observaciones
    ) VALUES (
        NEW.producto_id, 'venta',
        v_paquetes_actuales, v_subpaquetes_actuales,
        FLOOR(v_nuevo_total / v_subpaquetes_por_paquete),
        MOD(v_nuevo_total, v_subpaquetes_por_paquete),
        -NEW.cantidad_subpaquetes,
        CONCAT('VENTA-', NEW.venta_id),
        NOW(),
        NEW.usuario_extraccion,
        CONCAT('Venta de ', NEW.cantidad_subpaquetes, ' subpaquetes')
    );
END$$

-- Trigger para manejar ventas con pagos parciales
CREATE TRIGGER after_venta_insert
AFTER INSERT ON ventas
FOR EACH ROW
BEGIN
    DECLARE v_saldo_anterior DECIMAL(12,2);
    DECLARE v_saldo_nuevo DECIMAL(12,2);
    
    IF NEW.cliente_id IS NOT NULL THEN
        SELECT saldo_actual INTO v_saldo_anterior
        FROM clientes WHERE id = NEW.cliente_id;
        
        SET v_saldo_nuevo = v_saldo_anterior + NEW.debe;
        
        UPDATE clientes 
        SET 
            saldo_actual = v_saldo_nuevo,
            total_comprado = total_comprado + NEW.total,
            compras_realizadas = compras_realizadas + 1
        WHERE id = NEW.cliente_id;
        
        IF NEW.debe > 0 THEN
            INSERT INTO clientes_cuentas_cobrar (
                cliente_id, venta_id, monto_total, saldo_pendiente, 
                usuario_registro, observaciones
            ) VALUES (
                NEW.cliente_id,
                NEW.id,
                NEW.total,
                NEW.debe,
                NEW.vendedor_id,
                CONCAT('Venta ', NEW.codigo_venta, ' - Debe: ', NEW.debe, ' Bs')
            );
        END IF;
        
        IF NEW.pago_inicial > 0 THEN
            INSERT INTO pagos_clientes (
                tipo, cliente_id, venta_id, monto, metodo_pago,
                referencia, fecha, hora, usuario_id, observaciones
            ) VALUES (
                'pago_inicial',
                NEW.cliente_id,
                NEW.id,
                NEW.pago_inicial,
                NEW.metodo_pago_inicial,
                NEW.referencia_pago_inicial,
                NEW.fecha,
                COALESCE(NEW.hora_fin, '18:00:00'),
                NEW.vendedor_id,
                CONCAT('Pago inicial venta ', NEW.codigo_venta)
            );
            
            INSERT INTO movimientos_caja (
                tipo, categoria, monto, descripcion, referencia_venta,
                fecha, hora, usuario_id, observaciones
            ) VALUES (
                'ingreso',
                'pago_inicial',
                NEW.pago_inicial,
                CONCAT('Pago inicial cliente ', 
                      (SELECT nombre FROM clientes WHERE id = NEW.cliente_id)),
                NEW.codigo_venta,
                NEW.fecha,
                COALESCE(NEW.hora_fin, '18:00:00'),
                NEW.vendedor_id,
                CONCAT('Método: ', NEW.metodo_pago_inicial)
            );
        END IF;
    END IF;
END$$

-- Trigger para abonos posteriores de clientes
CREATE TRIGGER after_pago_cliente_insert
AFTER INSERT ON pagos_clientes
FOR EACH ROW
BEGIN
    DECLARE v_total_venta DECIMAL(12,2);
    DECLARE v_pagado_total DECIMAL(12,2);
    DECLARE v_pago_inicial DECIMAL(12,2);
    
    IF NEW.tipo != 'pago_inicial' THEN
        UPDATE clientes 
        SET saldo_actual = saldo_actual - NEW.monto
        WHERE id = NEW.cliente_id;
        
        UPDATE clientes_cuentas_cobrar
        SET saldo_pendiente = saldo_pendiente - NEW.monto
        WHERE venta_id = NEW.venta_id;
        
        SELECT total, pago_inicial INTO v_total_venta, v_pago_inicial
        FROM ventas WHERE id = NEW.venta_id;
        
        SELECT COALESCE(SUM(monto), 0) + v_pago_inicial INTO v_pagado_total
        FROM pagos_clientes 
        WHERE venta_id = NEW.venta_id AND tipo != 'pago_inicial';
        
        IF v_pagado_total >= v_total_venta THEN
            UPDATE ventas 
            SET estado = 'pagada'
            WHERE id = NEW.venta_id;
            
            UPDATE clientes_cuentas_cobrar
            SET estado = 'pagada'
            WHERE venta_id = NEW.venta_id;
        END IF;
        
        INSERT INTO movimientos_caja (
            tipo, categoria, monto, descripcion, referencia_venta,
            fecha, hora, usuario_id, observaciones
        ) VALUES (
            'ingreso',
            'abono_cliente',
            NEW.monto,
            CONCAT('Abono cliente ', (SELECT nombre FROM clientes WHERE id = NEW.cliente_id)),
            (SELECT codigo_venta FROM ventas WHERE id = NEW.venta_id),
            NEW.fecha,
            NEW.hora,
            NEW.usuario_id,
            CONCAT('Método: ', NEW.metodo_pago, ' - Abono')
        );
    END IF;
END$$

-- Trigger para registrar compra a proveedor
CREATE TRIGGER after_inventario_insert
AFTER INSERT ON inventario
FOR EACH ROW
BEGIN
    DECLARE v_codigo_prov VARCHAR(20);
    DECLARE v_nombre_prov VARCHAR(100);
    DECLARE v_ciudad_prov VARCHAR(50);
    DECLARE v_saldo_anterior DECIMAL(12,2);
    DECLARE v_saldo_nuevo DECIMAL(12,2);
    DECLARE v_costo_total DECIMAL(12,2);
    DECLARE v_proveedor_id INT;

    SET v_costo_total = NEW.paquetes_completos * NEW.costo_paquete;

    IF v_costo_total > 0 THEN
        SELECT p.id, p.codigo, p.nombre, p.ciudad, p.saldo_actual
        INTO v_proveedor_id, v_codigo_prov, v_nombre_prov, v_ciudad_prov, v_saldo_anterior
        FROM proveedores p
        JOIN productos pr ON p.id = pr.proveedor_id
        WHERE pr.id = NEW.producto_id;

        SET v_saldo_nuevo = v_saldo_anterior + v_costo_total;

        UPDATE proveedores
        SET saldo_actual = v_saldo_nuevo
        WHERE id = v_proveedor_id;

        INSERT INTO proveedores_estado_cuentas (
            proveedor_id, codigo_proveedor, nombre_proveedor, ciudad_proveedor,
            compra, a_cuenta, adelanto, saldo, fecha, descripcion, usuario_id
        ) VALUES (
            v_proveedor_id,
            v_codigo_prov,
            v_nombre_prov,
            v_ciudad_prov,
            v_costo_total,
            v_costo_total,
            0.00,
            v_saldo_nuevo,
            NEW.fecha_ultimo_ingreso,
            CONCAT('Compra de ', NEW.paquetes_completos, ' paquetes'),
            NEW.usuario_registro
        );
    END IF;
END$$

-- Trigger para actualizar saldo de proveedor al modificar inventario
CREATE TRIGGER after_inventario_update
AFTER UPDATE ON inventario
FOR EACH ROW
BEGIN
    DECLARE v_codigo_prov VARCHAR(20);
    DECLARE v_nombre_prov VARCHAR(100);
    DECLARE v_ciudad_prov VARCHAR(50);
    DECLARE v_saldo_anterior DECIMAL(12,2);
    DECLARE v_saldo_nuevo DECIMAL(12,2);
    DECLARE v_costo_antes DECIMAL(12,2);
    DECLARE v_costo_despues DECIMAL(12,2);
    DECLARE v_diff DECIMAL(12,2);
    DECLARE v_proveedor_id INT;

    SET v_costo_antes = OLD.paquetes_completos * OLD.costo_paquete;
    SET v_costo_despues = NEW.paquetes_completos * NEW.costo_paquete;
    SET v_diff = v_costo_despues - v_costo_antes;

    IF v_diff <> 0 THEN
        SELECT p.id, p.codigo, p.nombre, p.ciudad, p.saldo_actual
        INTO v_proveedor_id, v_codigo_prov, v_nombre_prov, v_ciudad_prov, v_saldo_anterior
        FROM proveedores p
        JOIN productos pr ON p.id = pr.proveedor_id
        WHERE pr.id = NEW.producto_id;

        SET v_saldo_nuevo = v_saldo_anterior + v_diff;

        UPDATE proveedores
        SET saldo_actual = v_saldo_nuevo
        WHERE id = v_proveedor_id;

        INSERT INTO proveedores_estado_cuentas (
            proveedor_id, codigo_proveedor, nombre_proveedor, ciudad_proveedor,
            compra, a_cuenta, adelanto, saldo, fecha, descripcion, usuario_id
        ) VALUES (
            v_proveedor_id,
            v_codigo_prov,
            v_nombre_prov,
            v_ciudad_prov,
            v_diff,
            v_diff,
            0.00,
            v_saldo_nuevo,
            NEW.fecha_ultimo_ingreso,
            CONCAT('Ajuste inventario (antes=', v_costo_antes, ', despues=', v_costo_despues, ')'),
            NEW.usuario_registro
        );
    END IF;
END$$

-- Trigger para registrar pago a proveedor
CREATE TRIGGER after_pago_proveedor_insert
AFTER INSERT ON pagos_proveedores
FOR EACH ROW
BEGIN
    DECLARE v_codigo_prov VARCHAR(20);
    DECLARE v_nombre_prov VARCHAR(100);
    DECLARE v_ciudad_prov VARCHAR(50);
    DECLARE v_saldo_anterior DECIMAL(12,2);
    DECLARE v_saldo_nuevo DECIMAL(12,2);
    
    SELECT codigo, nombre, ciudad, saldo_actual
    INTO v_codigo_prov, v_nombre_prov, v_ciudad_prov, v_saldo_anterior
    FROM proveedores WHERE id = NEW.proveedor_id;
    
    SET v_saldo_nuevo = v_saldo_anterior - NEW.monto;
    
    UPDATE proveedores 
    SET saldo_actual = v_saldo_nuevo
    WHERE id = NEW.proveedor_id;
    
    INSERT INTO proveedores_estado_cuentas (
        proveedor_id, codigo_proveedor, nombre_proveedor, ciudad_proveedor,
        compra, a_cuenta, adelanto, saldo, fecha, descripcion, usuario_id
    ) VALUES (
        NEW.proveedor_id,
        v_codigo_prov,
        v_nombre_prov,
        v_ciudad_prov,
        0.00,
        0.00,
        NEW.monto,
        v_saldo_nuevo,
        NEW.fecha,
        CONCAT('Pago a proveedor - Ref: ', COALESCE(NEW.referencia, 'Sin referencia')),
        NEW.usuario_id
    );
    
    INSERT INTO movimientos_caja (
        tipo, categoria, monto, descripcion, referencia_venta,
        fecha, hora, usuario_id, observaciones
    ) VALUES (
        'gasto',
        'pago_proveedor',
        NEW.monto,
        CONCAT('Pago a proveedor ', v_nombre_prov),
        NULL,
        NEW.fecha,
        '12:00:00',
        NEW.usuario_id,
        CONCAT('Método: ', NEW.metodo_pago, ' - Ref: ', COALESCE(NEW.referencia, ''))
    );
END$$

DELIMITER ;

-- ====================================================
-- FUNCIONES UTILES
-- ====================================================

DELIMITER $$

-- Eliminar funciones si existen
DROP FUNCTION IF EXISTS generar_codigo_venta$$
DROP FUNCTION IF EXISTS calcular_precio_unitario$$

-- Función para generar código de venta automático
CREATE FUNCTION generar_codigo_venta() RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    DECLARE prefijo VARCHAR(10) DEFAULT 'VTA';
    DECLARE fecha_actual VARCHAR(10);
    DECLARE secuencia INT;
    DECLARE codigo VARCHAR(50);
    
    SET fecha_actual = DATE_FORMAT(CURDATE(), '%Y%m%d');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(codigo_venta, 12) AS UNSIGNED)), 0) + 1 
    INTO secuencia
    FROM ventas 
    WHERE codigo_venta LIKE CONCAT(prefijo, '-', fecha_actual, '-%');
    
    SET codigo = CONCAT(prefijo, '-', fecha_actual, '-', LPAD(secuencia, 4, '0'));
    
    RETURN codigo;
END$$

-- Función para calcular precio unitario
CREATE FUNCTION calcular_precio_unitario(
    p_producto_id INT,
    p_cantidad INT
) RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE v_precio DECIMAL(10,2);
    
    SELECT 
        CASE 
            WHEN p_cantidad > 5 THEN precio_mayor
            ELSE precio_menor
        END INTO v_precio
    FROM productos 
    WHERE id = p_producto_id;
    
    RETURN v_precio;
END$$

DELIMITER ;

-- ====================================================
-- PROCEDIMIENTOS UTILES
-- ====================================================

DELIMITER $$

-- Eliminar procedimientos si existen
DROP PROCEDURE IF EXISTS registrar_gasto$$
DROP PROCEDURE IF EXISTS cierre_caja_diario$$
DROP PROCEDURE IF EXISTS ajustar_inventario_fisico$$

-- Procedimiento para registrar gasto varios
CREATE PROCEDURE registrar_gasto(
    IN p_categoria VARCHAR(20),
    IN p_descripcion VARCHAR(255),
    IN p_monto DECIMAL(10,2),
    IN p_usuario_id INT
)
BEGIN
    DECLARE v_categoria_enum VARCHAR(20);
    
    CASE p_categoria
        WHEN 'almuerzo' THEN SET v_categoria_enum = 'gasto_almuerzo';
        WHEN 'varios' THEN SET v_categoria_enum = 'gasto_varios';
        WHEN 'proveedor' THEN SET v_categoria_enum = 'pago_proveedor';
        ELSE SET v_categoria_enum = 'otros';
    END CASE;
    
    INSERT INTO movimientos_caja (
        tipo, categoria, monto, descripcion,
        fecha, hora, usuario_id, observaciones
    ) VALUES (
        'gasto',
        v_categoria_enum,
        p_monto,
        p_descripcion,
        CURDATE(),
        CURTIME(),
        p_usuario_id,
        CONCAT('Gasto registrado por sistema')
    );
    
    SELECT 'Gasto registrado correctamente' as mensaje;
END$$

-- Procedimiento para cierre de caja diario
CREATE PROCEDURE cierre_caja_diario(
    IN p_fecha DATE,
    IN p_usuario_id INT
)
BEGIN
    DECLARE v_ingresos DECIMAL(12,2);
    DECLARE v_gastos DECIMAL(12,2);
    DECLARE v_balance DECIMAL(12,2);
    
    SELECT 
        COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0)
    INTO v_ingresos, v_gastos
    FROM movimientos_caja
    WHERE fecha = p_fecha;
    
    SET v_balance = v_ingresos - v_gastos;
    
    -- Insertar registro de cierre
    INSERT INTO movimientos_caja (
        tipo, categoria, monto, descripcion,
        fecha, hora, usuario_id, observaciones
    ) VALUES (
        'ingreso',
        'otros',
        0,
        CONCAT('CIERRE DE CAJA - Balance: ', v_balance, ' Bs'),
        p_fecha,
        '23:59:59',
        p_usuario_id,
        CONCAT('Ingresos: ', v_ingresos, ' Bs | Gastos: ', v_gastos, ' Bs | Balance: ', v_balance, ' Bs')
    );
    
    SELECT 
        p_fecha as fecha,
        v_ingresos as ingresos_totales,
        v_gastos as gastos_totales,
        v_balance as balance_final,
        'Cierre de caja registrado' as mensaje;
END$$

-- Procedimiento para ajustar inventario físico
CREATE PROCEDURE ajustar_inventario_fisico(
    IN p_producto_id INT,
    IN p_paquetes_fisicos INT,
    IN p_subpaquetes_fisicos INT,
    IN p_usuario_id INT,
    IN p_observaciones TEXT
)
BEGIN
    DECLARE v_paquetes_actual INT;
    DECLARE v_subpaquetes_actual INT;
    DECLARE v_subpaquetes_por_paquete INT DEFAULT 10;
    DECLARE v_diferencia INT;
    
    SELECT paquetes_completos, subpaquetes_sueltos
    INTO v_paquetes_actual, v_subpaquetes_actual
    FROM inventario 
    WHERE producto_id = p_producto_id;
    
    -- Calcular diferencia (asumiendo 10 subpaquetes por paquete)
    SET v_diferencia = (p_paquetes_fisicos * v_subpaquetes_por_paquete + p_subpaquetes_fisicos) - 
                       (v_paquetes_actual * v_subpaquetes_por_paquete + v_subpaquetes_actual);
    
    -- Actualizar inventario
    UPDATE inventario 
    SET 
        paquetes_completos = p_paquetes_fisicos,
        subpaquetes_sueltos = p_subpaquetes_fisicos,
        usuario_registro = p_usuario_id
    WHERE producto_id = p_producto_id;
    
    -- Registrar en historial
    INSERT INTO historial_inventario (
        producto_id, tipo_movimiento,
        paquetes_anteriores, subpaquetes_anteriores,
        paquetes_nuevos, subpaquetes_nuevos,
        diferencia, referencia, fecha_hora, usuario_id, observaciones
    ) VALUES (
        p_producto_id, 'ajuste',
        v_paquetes_actual, v_subpaquetes_actual,
        p_paquetes_fisicos, p_subpaquetes_fisicos,
        v_diferencia,
        'AJUSTE_FISICO',
        NOW(),
        p_usuario_id,
        CONCAT('Ajuste físico: ', p_observaciones)
    );
    
    SELECT 'Inventario ajustado correctamente' as mensaje, v_diferencia as diferencia;
END$$

DELIMITER ;

-- ====================================================
-- ELIMINAR VISTAS EXISTENTES
-- ====================================================
DROP VIEW IF EXISTS vista_inventario_completo;
DROP VIEW IF EXISTS vista_ventas_detalladas;
DROP VIEW IF EXISTS vista_estado_cuentas;
DROP VIEW IF EXISTS vista_proveedores_estado_cuentas;
DROP VIEW IF EXISTS vista_gastos_varios;
DROP VIEW IF EXISTS vista_caja_diaria;
DROP VIEW IF EXISTS vista_inventario_bajo;

-- ====================================================
-- VISTAS PARA REPORTES
-- ====================================================

-- Vista de inventario completo
CREATE VIEW vista_inventario_completo AS
SELECT 
    p.codigo,
    p.nombre_color,
    pr.nombre as proveedor,
    c.nombre as categoria,
    i.paquetes_completos,
    i.subpaquetes_sueltos,
    i.subpaquetes_por_paquete,
    (i.paquetes_completos * i.subpaquetes_por_paquete + i.subpaquetes_sueltos) as total_subpaquetes,
    p.precio_menor,
    p.precio_mayor,
    i.ubicacion,
    p.activo
FROM productos p
JOIN proveedores pr ON p.proveedor_id = pr.id
JOIN categorias c ON p.categoria_id = c.id
LEFT JOIN inventario i ON p.id = i.producto_id
ORDER BY pr.nombre, p.codigo;

-- Vista de ventas con detalles de pago
CREATE VIEW vista_ventas_detalladas AS
SELECT 
    v.codigo_venta,
    v.fecha,
    v.hora_inicio,
    v.hora_fin,
    COALESCE(c.nombre, v.cliente_contado) as cliente,
    u.nombre as vendedor,
    v.tipo_pago,
    v.subtotal,
    v.descuento,
    v.total,
    v.pago_inicial,
    v.debe,
    v.estado,
    v.impreso,
    GROUP_CONCAT(CONCAT(p.codigo, ' (', vd.cantidad_subpaquetes, ')') SEPARATOR ', ') as productos
FROM ventas v
LEFT JOIN clientes c ON v.cliente_id = c.id
JOIN usuarios u ON v.vendedor_id = u.id
LEFT JOIN venta_detalles vd ON v.id = vd.venta_id
LEFT JOIN productos p ON vd.producto_id = p.id
GROUP BY v.id
ORDER BY v.fecha DESC, v.hora_inicio DESC;

-- Vista de estado de cuentas clientes
CREATE VIEW vista_estado_cuentas AS
SELECT 
    cl.codigo,
    cl.nombre,
    cl.ciudad,
    cl.telefono,
    cl.saldo_actual as deuda_total,
    COUNT(cc.id) as ventas_pendientes,
    COALESCE(SUM(cc.saldo_pendiente), 0) as saldo_pendiente,
    cl.limite_credito,
    CASE 
        WHEN cl.saldo_actual > cl.limite_credito THEN 'EXCEDIDO'
        WHEN cl.saldo_actual > 0 THEN 'DEUDA'
        ELSE 'AL DÍA'
    END as estado_credito
FROM clientes cl
LEFT JOIN clientes_cuentas_cobrar cc ON cl.id = cc.cliente_id AND cc.estado = 'pendiente'
GROUP BY cl.id
HAVING deuda_total > 0
ORDER BY cl.saldo_actual DESC;

-- Vista para estado de cuentas proveedores
CREATE VIEW vista_proveedores_estado_cuentas AS
SELECT 
    pe.codigo_proveedor as CODIGO,
    pe.nombre_proveedor as 'NOMBRE DEL PROVEEDOR',
    pe.ciudad_proveedor as CIUDAD,
    pe.compra as COMPRA,
    pe.a_cuenta as 'A CUENTA',
    pe.adelanto as ADELANTO,
    pe.fecha as FECHA,
    pe.saldo as SALDO,
    pe.descripcion,
    u.nombre as 'USUARIO REGISTRO'
FROM proveedores_estado_cuentas pe
JOIN usuarios u ON pe.usuario_id = u.id
ORDER BY pe.fecha DESC, pe.proveedor_id;

-- Vista para gastos varios
CREATE VIEW vista_gastos_varios AS
SELECT 
    fecha as FECHA,
    hora as HORA,
    descripcion as DESCRIPCION,
    categoria as CATEGORIA,
    monto as MONTO,
    (SELECT nombre FROM usuarios WHERE id = mc.usuario_id) as USUARIO,
    observaciones as OBSERVACIONES
FROM movimientos_caja mc
WHERE tipo = 'gasto'
ORDER BY fecha DESC, hora DESC;

-- Vista para caja diaria
CREATE VIEW vista_caja_diaria AS
SELECT 
    fecha,
    SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
    SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END) as gastos,
    SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) as balance,
    COUNT(*) as total_movimientos
FROM movimientos_caja
GROUP BY fecha
ORDER BY fecha DESC;

-- Vista para inventario bajo
CREATE VIEW vista_inventario_bajo AS
SELECT 
    p.codigo,
    p.nombre_color,
    pr.nombre as proveedor,
    (i.paquetes_completos * i.subpaquetes_por_paquete + i.subpaquetes_sueltos) as total_subpaquetes,
    i.ubicacion,
    CASE 
        WHEN (i.paquetes_completos * i.subpaquetes_por_paquete + i.subpaquetes_sueltos) < 50 THEN 'CRÍTICO'
        WHEN (i.paquetes_completos * i.subpaquetes_por_paquete + i.subpaquetes_sueltos) < 100 THEN 'BAJO'
        ELSE 'NORMAL'
    END as nivel_stock
FROM productos p
JOIN proveedores pr ON p.proveedor_id = pr.id
JOIN inventario i ON p.id = i.producto_id
WHERE (i.paquetes_completos * i.subpaquetes_por_paquete + i.subpaquetes_sueltos) < 100
ORDER BY (i.paquetes_completos * i.subpaquetes_por_paquete + i.subpaquetes_sueltos) ASC;

-- ====================================================
-- INSERCIONES INICIALES
-- ====================================================

-- Insertar configuración inicial (solo si no existe)
INSERT INTO sistema_config (empresa_nombre, telefono_empresa, direccion_empresa) 
SELECT 'TIENDA DE LANAS', '12345678', 'Dirección Principal de la Tienda'
WHERE NOT EXISTS (SELECT 1 FROM sistema_config LIMIT 1);

-- Insertar usuario administrador (password: admin123)
INSERT INTO usuarios (codigo, nombre, usuario, password, rol, activo) 
SELECT 'ADM001', 'Administrador Principal', 'admin', '$2y$10$YourHashedPasswordHere', 'administrador', 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'admin');

-- Insertar usuario vendedor (password: vendedor123)
INSERT INTO usuarios (codigo, nombre, usuario, password, rol, activo) 
SELECT 'VEN001', 'Vendedor Principal', 'vendedor', '$2y$10$3jYk13goPJj.TP9ZaJ7ckOw/kPmvCajiTyJleeu6eyhDEZDdnE6ei', 'vendedor', 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'vendedor');

-- ====================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ====================================================

-- Crear índices si no existen
CREATE INDEX IF NOT EXISTS idx_productos_busqueda ON productos(codigo, nombre_color, activo);
CREATE INDEX IF NOT EXISTS idx_clientes_busqueda ON clientes(codigo, nombre, activo);
CREATE INDEX IF NOT EXISTS idx_ventas_busqueda_fecha ON ventas(fecha, cliente_id, vendedor_id);
CREATE INDEX IF NOT EXISTS idx_inventario_stock ON inventario(paquetes_completos, subpaquetes_sueltos);
CREATE INDEX IF NOT EXISTS idx_movimientos_caja_completo ON movimientos_caja(fecha, tipo, categoria, usuario_id);
CREATE INDEX IF NOT EXISTS idx_productos_precios ON productos(precio_menor, precio_mayor);
CREATE INDEX IF NOT EXISTS idx_ventas_codigo ON ventas(codigo_venta);







