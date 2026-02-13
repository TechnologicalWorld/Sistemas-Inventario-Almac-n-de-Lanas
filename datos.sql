-- ====================================================
-- INSERTS PARA PROVEEDORES (sin cambios en fechas)
-- ====================================================
INSERT INTO proveedores (codigo, nombre, ciudad, telefono, email, credito_limite, saldo_actual, activo, observaciones) VALUES
('PROV001', 'Lanas del Sur', 'La Paz', '2-2221111', 'ventas@lanasdelsur.bo', 25000.00, 8500.50, 1, 'Proveedor principal - Lanas acrílicas'),
('PROV002', 'Textiles Andinos', 'El Alto', '2-3332222', 'contacto@textilesandinos.bo', 30000.00, 12500.75, 1, 'Especialistas en lana de alpaca'),
('PROV003', 'Hilos y Colores', 'Cochabamba', '4-4443333', 'pedidos@hiloscolores.bo', 20000.00, 4500.25, 1, 'Lanas importadas y nacionales'),
('PROV004', 'Lanas Merino Bolivia', 'Santa Cruz', '3-5554444', 'info@merinobolivia.bo', 35000.00, 15800.00, 1, 'Lana merino de alta calidad'),
('PROV005', 'Distribuidora de Lanas', 'La Paz', '2-6665555', 'ventas@distrilanas.bo', 15000.00, 3200.00, 1, 'Distribuidor oficial'),
('PROV006', 'Andean Textiles', 'Oruro', '5-7776666', 'export@andeantextiles.bo', 28000.00, 9800.30, 1, 'Exportación de textiles');

-- ====================================================
-- INSERTS PARA CATEGORIAS (sin cambios)
-- ====================================================
INSERT INTO categorias (proveedor_id, nombre, subpaquetes_por_paquete, descripcion, activo) VALUES
(1, 'Lana Acrílica Fina', 10, 'Lana acrílica para todo tipo de tejido - Grosor fino', 1),
(1, 'Lana Acrílica Gruesa', 10, 'Lana acrílica para prendas de abrigo - Grosor grueso', 1),
(2, 'Lana de Alpaca Baby', 10, 'Lana de alpaca bebé - Suave y cálida', 1),
(2, 'Lana de Alpaca Adulto', 10, 'Lana de alpaca adulto - Resistente', 1),
(3, 'Lana Importada Italiana', 8, 'Lana importada de Italia - Alta calidad', 1),
(3, 'Lana Nacional Premium', 10, 'Lana de producción nacional', 1),
(4, 'Lana Merino Extra Fina', 8, 'Lana merino superfina - Ideal para bebés', 1),
(4, 'Lana Merino Regular', 10, 'Lana merino estándar', 1),
(5, 'Lana Sintética', 12, 'Lana sintética económica', 1),
(5, 'Mezcla Alpaca/Acrílico', 10, 'Combinación alpaca 30% - acrílico 70%', 1),
(6, 'Lana Tinturada Natural', 8, 'Teñida con tintes naturales', 1),
(6, 'Lana Orgánica', 8, 'Certificación orgánica', 1);

-- ====================================================
-- INSERTS PARA PRODUCTOS (sin cambios)
-- ====================================================
INSERT INTO productos (codigo, nombre_color, proveedor_id, categoria_id, precio_menor, precio_mayor, activo, tiene_stock) VALUES
-- Lanas del Sur - Acrílica Fina
('LS-001', 'Blanco Nieve', 1, 1, 8.50, 7.50, 1, 1),
('LS-002', 'Negro Azabache', 1, 1, 8.50, 7.50, 1, 1),
('LS-003', 'Gris Perla', 1, 1, 8.50, 7.50, 1, 1),
('LS-004', 'Beige', 1, 1, 8.50, 7.50, 1, 1),
('LS-005', 'Marrón Café', 1, 1, 8.50, 7.50, 1, 1),
('LS-006', 'Azul Marino', 1, 1, 8.50, 7.50, 1, 1),
('LS-007', 'Rojo Cereza', 1, 1, 8.50, 7.50, 1, 1),
('LS-008', 'Verde Manzana', 1, 1, 8.50, 7.50, 1, 1),
-- Lanas del Sur - Acrílica Gruesa
('LS-101', 'Blanco', 1, 2, 12.50, 10.50, 1, 1),
('LS-102', 'Negro', 1, 2, 12.50, 10.50, 1, 1),
('LS-103', 'Gris Oxford', 1, 2, 12.50, 10.50, 1, 1),
-- Textiles Andinos - Alpaca Baby
('TA-201', 'Crema Natural', 2, 3, 28.00, 25.00, 1, 1),
('TA-202', 'Café Claro', 2, 3, 28.00, 25.00, 1, 1),
('TA-203', 'Gris Claro', 2, 3, 28.00, 25.00, 1, 1),
('TA-204', 'Blanco Alpaca', 2, 3, 28.00, 25.00, 1, 1),
-- Textiles Andinos - Alpaca Adulto
('TA-301', 'Negro Alpaca', 2, 4, 24.00, 21.00, 1, 1),
('TA-302', 'Marrón Oscuro', 2, 4, 24.00, 21.00, 1, 1),
('TA-303', 'Gris Oscuro', 2, 4, 24.00, 21.00, 1, 1),
-- Hilos y Colores - Importada Italiana
('HC-401', 'Rosa Pastel', 3, 5, 35.00, 32.00, 1, 1),
('HC-402', 'Lila', 3, 5, 35.00, 32.00, 1, 1),
('HC-403', 'Turquesa', 3, 5, 35.00, 32.00, 1, 1),
-- Hilos y Colores - Nacional Premium
('HC-501', 'Amarillo Sol', 3, 6, 15.00, 13.00, 1, 1),
('HC-502', 'Naranja', 3, 6, 15.00, 13.00, 1, 1),
('HC-503', 'Verde Esmeralda', 3, 6, 15.00, 13.00, 1, 1),
-- Lanas Merino Bolivia - Merino Extra Fina
('MB-601', 'Blanco Merino', 4, 7, 42.00, 38.00, 1, 1),
('MB-602', 'Beige Merino', 4, 7, 42.00, 38.00, 1, 1),
('MB-603', 'Gris Merino', 4, 7, 42.00, 38.00, 1, 1),
-- Lanas Merino Bolivia - Merino Regular
('MB-701', 'Azul Real', 4, 8, 32.00, 29.00, 1, 1),
('MB-702', 'Rojo Pasión', 4, 8, 32.00, 29.00, 1, 1),
('MB-703', 'Verde Bosque', 4, 8, 32.00, 29.00, 1, 1),
-- Distribuidora de Lanas - Sintética
('DL-801', 'Multicolor', 5, 9, 6.50, 5.50, 1, 1),
('DL-802', 'Celeste', 5, 9, 6.50, 5.50, 1, 1),
('DL-803', 'Fucsia', 5, 9, 6.50, 5.50, 1, 1),
-- Distribuidora de Lanas - Mezcla
('DL-901', 'Marrón Mezcla', 5, 10, 18.50, 16.00, 1, 1),
('DL-902', 'Gris Mezcla', 5, 10, 18.50, 16.00, 1, 1),
('DL-903', 'Verde Mezcla', 5, 10, 18.50, 16.00, 1, 1),
-- Andean Textiles - Tinturada Natural
('AT-011', 'Cobre', 6, 11, 38.00, 34.00, 1, 1),
('AT-012', 'Índigo', 6, 11, 38.00, 34.00, 1, 1),
('AT-013', 'Granate', 6, 11, 38.00, 34.00, 1, 1),
-- Andean Textiles - Orgánica
('AT-021', 'Crudo', 6, 12, 45.00, 41.00, 1, 1),
('AT-022', 'Ecru', 6, 12, 45.00, 41.00, 1, 1),
('AT-023', 'Terracota', 6, 12, 45.00, 41.00, 1, 1);

-- ====================================================
-- INSERTS PARA INVENTARIO (fechas ajustadas a febrero 2026)
-- ====================================================
INSERT INTO inventario (producto_id, paquetes_completos, subpaquetes_sueltos, subpaquetes_por_paquete, costo_paquete, ubicacion, fecha_ultimo_ingreso, usuario_registro, observaciones) VALUES
(1, 25, 3, 10, 45.00, 'A-01', '2026-02-01', 1, 'Inventario inicial mes'),
(2, 20, 5, 10, 45.00, 'A-01', '2026-02-01', 1, 'Inventario inicial mes'),
(3, 18, 2, 10, 45.00, 'A-01', '2026-02-01', 1, 'Inventario inicial mes'),
(4, 22, 1, 10, 45.00, 'A-02', '2026-02-01', 1, 'Inventario inicial mes'),
(5, 15, 4, 10, 45.00, 'A-02', '2026-02-01', 1, 'Inventario inicial mes'),
(6, 19, 6, 10, 45.00, 'A-02', '2026-02-01', 1, 'Inventario inicial mes'),
(7, 24, 2, 10, 45.00, 'A-03', '2026-02-01', 1, 'Inventario inicial mes'),
(8, 16, 5, 10, 45.00, 'A-03', '2026-02-01', 1, 'Inventario inicial mes'),
(9, 30, 0, 10, 70.00, 'B-01', '2026-02-02', 1, 'Compra acrílica gruesa'),
(10, 28, 3, 10, 70.00, 'B-01', '2026-02-02', 1, 'Compra acrílica gruesa'),
(11, 25, 2, 10, 70.00, 'B-01', '2026-02-02', 1, 'Compra acrílica gruesa'),
(12, 12, 5, 10, 180.00, 'C-01', '2026-02-01', 1, 'Alpaca baby'),
(13, 10, 3, 10, 180.00, 'C-01', '2026-02-01', 1, 'Alpaca baby'),
(14, 8, 7, 10, 180.00, 'C-01', '2026-02-01', 1, 'Alpaca baby'),
(15, 14, 1, 10, 180.00, 'C-01', '2026-02-01', 1, 'Alpaca baby'),
(16, 8, 4, 10, 150.00, 'C-02', '2026-02-01', 1, 'Alpaca adulto'),
(17, 9, 2, 10, 150.00, 'C-02', '2026-02-01', 1, 'Alpaca adulto'),
(18, 6, 6, 10, 150.00, 'C-02', '2026-02-01', 1, 'Alpaca adulto'),
(19, 5, 2, 8, 240.00, 'D-01', '2026-02-03', 1, 'Importada italiana'),
(20, 4, 1, 8, 240.00, 'D-01', '2026-02-03', 1, 'Importada italiana'),
(21, 3, 5, 8, 240.00, 'D-01', '2026-02-03', 1, 'Importada italiana'),
(22, 18, 4, 10, 90.00, 'D-02', '2026-02-01', 1, 'Nacional premium'),
(23, 20, 2, 10, 90.00, 'D-02', '2026-02-01', 1, 'Nacional premium'),
(24, 15, 6, 10, 90.00, 'D-02', '2026-02-01', 1, 'Nacional premium'),
(25, 6, 2, 8, 280.00, 'E-01', '2026-02-04', 1, 'Merino extrafina'),
(26, 5, 1, 8, 280.00, 'E-01', '2026-02-04', 1, 'Merino extrafina'),
(27, 4, 3, 8, 280.00, 'E-01', '2026-02-04', 1, 'Merino extrafina'),
(28, 10, 4, 10, 200.00, 'E-02', '2026-02-01', 1, 'Merino regular'),
(29, 12, 2, 10, 200.00, 'E-02', '2026-02-01', 1, 'Merino regular'),
(30, 9, 5, 10, 200.00, 'E-02', '2026-02-01', 1, 'Merino regular'),
(31, 40, 0, 12, 40.00, 'F-01', '2026-02-05', 1, 'Sintética'),
(32, 35, 5, 12, 40.00, 'F-01', '2026-02-05', 1, 'Sintética'),
(33, 38, 3, 12, 40.00, 'F-01', '2026-02-05', 1, 'Sintética'),
(34, 12, 4, 10, 120.00, 'F-02', '2026-02-01', 1, 'Mezcla'),
(35, 14, 2, 10, 120.00, 'F-02', '2026-02-01', 1, 'Mezcla'),
(36, 10, 6, 10, 120.00, 'F-02', '2026-02-01', 1, 'Mezcla'),
(37, 4, 2, 8, 260.00, 'G-01', '2026-02-06', 1, 'Tinturada natural'),
(38, 3, 1, 8, 260.00, 'G-01', '2026-02-06', 1, 'Tinturada natural'),
(39, 2, 5, 8, 260.00, 'G-01', '2026-02-06', 1, 'Tinturada natural'),
(40, 5, 2, 8, 310.00, 'G-02', '2026-02-07', 1, 'Orgánica'),
(41, 4, 1, 8, 310.00, 'G-02', '2026-02-07', 1, 'Orgánica'),
(42, 3, 4, 8, 310.00, 'G-02', '2026-02-07', 1, 'Orgánica');

-- ====================================================
-- INSERTS PARA CLIENTES (sin cambios en fechas)
-- ====================================================
INSERT INTO clientes (codigo, nombre, ciudad, telefono, tipo_documento, numero_documento, limite_credito, saldo_actual, total_comprado, compras_realizadas, activo, observaciones) VALUES
('CLI001', 'Tejidos María', 'La Paz', '2-7112233', 'DNI', '45678912', 5000.00, 850.00, 12500.50, 15, 1, 'Cliente frecuente - Tienda de tejidos'),
('CLI002', 'Artesanías Andinas', 'El Alto', '2-7445566', 'NIT', '1234567023', 8000.00, 2100.75, 18450.25, 22, 1, 'Compra al por mayor'),
('CLI003', 'Mamá Teje', 'Cochabamba', '4-4778899', 'DNI', '56789123', 3000.00, 0.00, 5670.00, 8, 1, 'Emprendimiento familiar'),
('CLI004', 'Lanas y Puntos', 'Santa Cruz', '3-4889900', 'NIT', '2345678012', 10000.00, 3450.50, 23450.75, 28, 1, 'Tienda especializada'),
('CLI005', 'Doña Rosa Tejidos', 'La Paz', '2-7223344', 'DNI', '67891234', 2000.00, 450.00, 3450.25, 5, 1, 'Cliente regular'),
('CLI006', 'Artesanías Titicaca', 'Puno', '2-7334455', 'RUC', '3456789012', 6000.00, 1250.00, 8900.00, 12, 1, 'Cliente internacional'),
('CLI007', 'Fábrica de Chompas', 'El Alto', '2-7556677', 'NIT', '4567890123', 15000.00, 5800.00, 45200.00, 45, 1, 'Fábrica - Cliente mayorista'),
('CLI008', 'Mercado de Tejidos', 'Cochabamba', '4-4667788', 'NIT', '5678901234', 12000.00, 3200.00, 28750.00, 32, 1, 'Venta en mercado'),
('CLI009', 'Ana González', 'La Paz', '2-7889900', 'DNI', '78912345', 1000.00, 0.00, 890.00, 3, 1, 'Cliente particular'),
('CLI010', 'Taller de Lanas', 'Oruro', '5-5990011', 'NIT', '6789012345', 4000.00, 950.00, 5670.00, 9, 1, 'Taller de tejidos'),
('CLI011', 'Tejidos Bolivianos', 'La Paz', '2-7001122', 'NIT', '7890123456', 7000.00, 1850.00, 12340.00, 18, 1, 'Exportación'),
('CLI012', 'Mercado Camacho', 'La Paz', '2-7113344', 'DNI', '89012345', 2500.00, 0.00, 2340.00, 6, 1, 'Puesto en mercado'),
('CLI013', 'Carmen López', 'El Alto', '2-7224455', 'DNI', '90123456', 1500.00, 380.00, 1780.00, 4, 1, 'Tejedora independiente'),
('CLI014', 'Cooperativa Tejedores', 'Potosí', '6-6225566', 'NIT', '8901234567', 9000.00, 2900.00, 18760.00, 24, 1, 'Cooperativa'),
('CLI015', 'Boutique de Lanas', 'Santa Cruz', '3-4996677', 'NIT', '9012345678', 5500.00, 1200.00, 8900.50, 14, 1, 'Boutique especializada');

-- ====================================================
-- INSERTS PARA OTROS_PRODUCTOS (sin cambios)
-- ====================================================
INSERT INTO otros_productos (codigo, nombre, categoria, unidad, precio_compra, precio_venta, stock, stock_minimo, activo, observaciones) VALUES
('AGUJAS-001', 'Agujas de Tejer N° 3', 'Accesorios', 'unidad', 3.50, 8.00, 45, 20, 1, 'Agujas rectas'),
('AGUJAS-002', 'Agujas de Tejer N° 4', 'Accesorios', 'unidad', 3.50, 8.00, 52, 20, 1, 'Agujas rectas'),
('AGUJAS-003', 'Agujas de Tejer N° 5', 'Accesorios', 'unidad', 3.50, 8.00, 38, 20, 1, 'Agujas rectas'),
('AGUJAS-004', 'Agujas Circulares N° 4', 'Accesorios', 'unidad', 8.00, 15.00, 25, 15, 1, 'Agujas circulares'),
('AGUJAS-005', 'Agujas Circulares N° 6', 'Accesorios', 'unidad', 8.00, 15.00, 20, 15, 1, 'Agujas circulares'),
('GANCHOS-001', 'Gancho de Tejer N° 2', 'Accesorios', 'unidad', 2.50, 6.00, 30, 15, 1, 'Gancho metálico'),
('GANCHOS-002', 'Gancho de Tejer N° 3', 'Accesorios', 'unidad', 2.50, 6.00, 42, 15, 1, 'Gancho metálico'),
('GANCHOS-003', 'Gancho de Tejer N° 4', 'Accesorios', 'unidad', 2.50, 6.00, 35, 15, 1, 'Gancho metálico'),
('ETIQUETAS-001', 'Etiquetas Tejido a Mano', 'Insumos', 'paquete', 5.00, 12.00, 100, 30, 1, 'Paquete x 50 unidades'),
('ETIQUETAS-002', 'Etiquetas Lana Natural', 'Insumos', 'paquete', 5.00, 12.00, 85, 30, 1, 'Paquete x 50 unidades'),
('BOLSAS-001', 'Bolsas de Almacenamiento', 'Empaques', 'paquete', 8.00, 18.00, 150, 40, 1, 'Paquete x 25 unidades'),
('BOLSAS-002', 'Bolsas Regalo', 'Empaques', 'paquete', 6.00, 15.00, 120, 40, 1, 'Paquete x 20 unidades'),
('TIJERAS-001', 'Tijeras para Tejido', 'Accesorios', 'unidad', 7.00, 15.00, 28, 15, 1, 'Tijeras profesionales'),
('TIJERAS-002', 'Tijeras Pequeñas', 'Accesorios', 'unidad', 4.00, 9.00, 35, 15, 1, 'Tijeras de precisión'),
('MARCADORES-001', 'Marcadores de Tejer', 'Accesorios', 'paquete', 3.00, 8.00, 75, 25, 1, 'Paquete x 20 unidades'),
('BOTONES-001', 'Botones Surtidos', 'Insumos', 'paquete', 4.00, 10.00, 80, 30, 1, 'Surtido x 100 unidades'),
('CIERRES-001', 'Cierres Metálicos', 'Insumos', 'paquete', 6.00, 15.00, 45, 20, 1, 'Paquete x 10 unidades'),
('HILOS-001', 'Hilo de Coser', 'Insumos', 'unidad', 2.00, 5.00, 90, 30, 1, 'Conos x 500m'),
('PATRONES-001', 'Revista Patrones Tejido', 'Material', 'unidad', 10.00, 25.00, 25, 10, 1, 'Revista especializada'),
('PATRONES-002', 'Libro Tejido Básico', 'Material', 'unidad', 25.00, 45.00, 15, 8, 1, 'Libro de instrucciones');

-- ====================================================
-- INSERTS PARA VENTAS (FEBRERO 2026)
-- ====================================================
INSERT INTO ventas (codigo_venta, cliente_id, cliente_contado, vendedor_id, tipo_venta, tipo_pago, subtotal, descuento, total, pago_inicial, metodo_pago_inicial, referencia_pago_inicial, es_venta_rapida, estado, fecha, hora_inicio, hora_fin, impreso, anulado, observaciones) VALUES
-- SEMANA 1: 1-7 FEBRERO
('VTA-20260201-0001', 1, NULL, 2, 'mayor', 'credito', 1250.00, 25.00, 1225.00, 500.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-01', '09:30:00', '09:45:00', 1, 0, 'Venta inicio de mes - Tejidos María'),
('VTA-20260201-0002', 2, NULL, 2, 'mayor', 'mixto', 2350.50, 50.00, 2300.50, 1000.00, 'transferencia', 'TRA-23456', 0, 'pendiente', '2026-02-01', '10:15:00', '10:30:00', 1, 0, 'Artesanías Andinas - Pago parcial'),
('VTA-20260201-0003', NULL, 'Cliente Feria', 1, 'menor', 'contado', 85.50, 0.00, 85.50, 85.50, 'efectivo', NULL, 1, 'pagada', '2026-02-01', '11:00:00', '11:10:00', 1, 0, 'Venta rápida domingo'),
('VTA-20260202-0001', 3, NULL, 2, 'menor', 'contado', 120.00, 5.00, 115.00, 115.00, 'efectivo', NULL, 0, 'pagada', '2026-02-02', '09:45:00', '09:55:00', 1, 0, 'Mamá Teje - Contado'),
('VTA-20260202-0002', 7, NULL, 2, 'mayor', 'credito', 3450.00, 100.00, 3350.00, 1000.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-02', '11:30:00', '11:50:00', 1, 0, 'Fábrica de Chompas - Crédito'),
('VTA-20260203-0001', 4, NULL, 1, 'mayor', 'credito', 890.00, 20.00, 870.00, 300.00, 'transferencia', 'TRA-34567', 0, 'pendiente', '2026-02-03', '10:00:00', '10:20:00', 1, 0, 'Lanas y Puntos - SCZ'),
('VTA-20260203-0002', 6, NULL, 2, 'mayor', 'contado', 560.00, 10.00, 550.00, 550.00, 'QR', 'QR-87654', 0, 'pagada', '2026-02-03', '14:30:00', '14:45:00', 1, 0, 'Artesanías Titicaca - Pago QR'),
('VTA-20260204-0001', NULL, 'Turista Argentina', 1, 'menor', 'contado', 45.00, 0.00, 45.00, 45.00, 'efectivo', NULL, 1, 'pagada', '2026-02-04', '11:15:00', '11:20:00', 1, 0, 'Venta rápida'),
('VTA-20260204-0002', 8, NULL, 2, 'mayor', 'mixto', 1200.00, 30.00, 1170.00, 500.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-04', '15:30:00', '15:45:00', 1, 0, 'Mercado de Tejidos - Cbba'),
('VTA-20260205-0001', 2, NULL, 2, 'mayor', 'credito', 780.00, 15.00, 765.00, 0.00, NULL, NULL, 0, 'pendiente', '2026-02-05', '09:20:00', '09:35:00', 1, 0, 'Crédito total - Artesanías Andinas'),
('VTA-20260205-0002', 5, NULL, 1, 'menor', 'contado', 68.00, 0.00, 68.00, 68.00, 'efectivo', NULL, 0, 'pagada', '2026-02-05', '16:00:00', '16:08:00', 1, 0, 'Doña Rosa - Pago efectivo'),
('VTA-20260206-0001', 11, NULL, 2, 'mayor', 'credito', 950.00, 25.00, 925.00, 400.00, 'transferencia', 'TRA-45678', 0, 'pendiente', '2026-02-06', '10:30:00', '10:50:00', 1, 0, 'Tejidos Bolivianos - Exportación'),
('VTA-20260206-0002', 14, NULL, 2, 'mayor', 'credito', 1350.00, 40.00, 1310.00, 500.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-06', '11:45:00', '12:05:00', 1, 0, 'Cooperativa Tejedores - Potosí'),
('VTA-20260207-0001', NULL, 'Feria Artesanal', 1, 'mayor', 'contado', 2200.00, 80.00, 2120.00, 2120.00, 'transferencia', 'TRA-56789', 0, 'pagada', '2026-02-07', '09:00:00', '09:30:00', 1, 0, 'Venta feria fin de semana'),
('VTA-20260207-0002', 9, NULL, 2, 'menor', 'contado', 32.50, 0.00, 32.50, 32.50, 'efectivo', NULL, 0, 'pagada', '2026-02-07', '12:30:00', '12:35:00', 1, 0, 'Ana González - Particular'),

-- SEMANA 2: 8-14 FEBRERO
('VTA-20260208-0001', 13, NULL, 1, 'menor', 'contado', 56.00, 0.00, 56.00, 56.00, 'efectivo', NULL, 0, 'pagada', '2026-02-08', '10:00:00', '10:08:00', 1, 0, 'Carmen López - Tejedora'),
('VTA-20260208-0002', 10, NULL, 2, 'mayor', 'mixto', 680.00, 10.00, 670.00, 300.00, 'QR', 'QR-98765', 0, 'pendiente', '2026-02-08', '11:30:00', '11:50:00', 1, 0, 'Taller de Lanas - Oruro'),
('VTA-20260209-0001', 12, NULL, 2, 'menor', 'contado', 95.00, 0.00, 95.00, 95.00, 'efectivo', NULL, 0, 'pagada', '2026-02-09', '09:15:00', '09:25:00', 1, 0, 'Mercado Camacho - LP'),
('VTA-20260209-0002', 15, NULL, 1, 'menor', 'contado', 48.50, 0.00, 48.50, 48.50, 'efectivo', NULL, 0, 'pagada', '2026-02-09', '15:45:00', '15:52:00', 1, 0, 'Boutique de Lanas - SCZ'),
('VTA-20260210-0001', 1, NULL, 2, 'mayor', 'credito', 980.00, 20.00, 960.00, 400.00, 'transferencia', 'TRA-67890', 0, 'pendiente', '2026-02-10', '10:30:00', '10:45:00', 1, 0, 'Tejidos María - Segunda compra mes'),
('VTA-20260210-0002', 3, NULL, 2, 'menor', 'contado', 156.00, 0.00, 156.00, 156.00, 'efectivo', NULL, 0, 'pagada', '2026-02-10', '14:20:00', '14:30:00', 1, 0, 'Mamá Teje - Lanas colores'),
('VTA-20260211-0001', 7, NULL, 2, 'mayor', 'credito', 2800.00, 80.00, 2720.00, 1000.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-11', '09:45:00', '10:10:00', 1, 0, 'Fábrica de Chompas - Pedido grande'),
('VTA-20260211-0002', NULL, 'Cliente Mayorista', 1, 'mayor', 'contado', 1500.00, 50.00, 1450.00, 1450.00, 'transferencia', 'TRA-78901', 0, 'pagada', '2026-02-11', '11:00:00', '11:25:00', 1, 0, 'Venta contado mayorista'),
('VTA-20260212-0001', 4, NULL, 2, 'mayor', 'credito', 750.00, 15.00, 735.00, 300.00, 'QR', 'QR-54321', 0, 'pendiente', '2026-02-12', '10:15:00', '10:30:00', 1, 0, 'Lanas y Puntos - Reabastecimiento'),
('VTA-20260212-0002', 6, NULL, 1, 'menor', 'contado', 120.00, 0.00, 120.00, 120.00, 'efectivo', NULL, 0, 'pagada', '2026-02-12', '16:30:00', '16:38:00', 1, 0, 'Artesanías Titicaca - Accesorios'),
('VTA-20260213-0001', 2, NULL, 2, 'mayor', 'credito', 890.00, 20.00, 870.00, 0.00, NULL, NULL, 0, 'pendiente', '2026-02-13', '09:30:00', '09:50:00', 1, 0, 'Artesanías Andinas - Crédito total'),
('VTA-20260213-0002', 5, NULL, 1, 'menor', 'contado', 42.00, 0.00, 42.00, 42.00, 'efectivo', NULL, 0, 'pagada', '2026-02-13', '11:45:00', '11:52:00', 1, 0, 'Doña Rosa - Compra pequeña'),
('VTA-20260214-0001', NULL, 'San Valentín', 2, 'menor', 'contado', 230.00, 0.00, 230.00, 230.00, 'efectivo', NULL, 1, 'pagada', '2026-02-14', '10:00:00', '10:20:00', 1, 0, 'Ventas especiales San Valentín'),
('VTA-20260214-0002', 8, NULL, 2, 'mayor', 'mixto', 1100.00, 30.00, 1070.00, 500.00, 'transferencia', 'TRA-89012', 0, 'pendiente', '2026-02-14', '15:00:00', '15:25:00', 1, 0, 'Mercado de Tejidos - Cbba'),

-- SEMANA 3: 15-21 FEBRERO
('VTA-20260215-0001', 11, NULL, 1, 'mayor', 'credito', 1200.00, 30.00, 1170.00, 500.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-15', '09:20:00', '09:40:00', 1, 0, 'Tejidos Bolivianos - Exportación'),
('VTA-20260215-0002', 14, NULL, 2, 'mayor', 'credito', 980.00, 20.00, 960.00, 400.00, 'transferencia', 'TRA-90123', 0, 'pendiente', '2026-02-15', '11:00:00', '11:20:00', 1, 0, 'Cooperativa Tejedores'),
('VTA-20260216-0001', 9, NULL, 2, 'menor', 'contado', 28.50, 0.00, 28.50, 28.50, 'efectivo', NULL, 0, 'pagada', '2026-02-16', '10:30:00', '10:35:00', 1, 0, 'Ana González - Lana'),
('VTA-20260216-0002', 13, NULL, 1, 'menor', 'contado', 67.00, 0.00, 67.00, 67.00, 'efectivo', NULL, 0, 'pagada', '2026-02-16', '16:15:00', '16:22:00', 1, 0, 'Carmen López'),
('VTA-20260217-0001', 10, NULL, 2, 'mayor', 'mixto', 550.00, 10.00, 540.00, 200.00, 'QR', 'QR-13579', 0, 'pendiente', '2026-02-17', '09:45:00', '10:00:00', 1, 0, 'Taller de Lanas'),
('VTA-20260217-0002', 12, NULL, 2, 'menor', 'contado', 84.00, 0.00, 84.00, 84.00, 'efectivo', NULL, 0, 'pagada', '2026-02-17', '14:30:00', '14:38:00', 1, 0, 'Mercado Camacho'),
('VTA-20260218-0001', 1, NULL, 2, 'mayor', 'credito', 650.00, 15.00, 635.00, 200.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-18', '10:00:00', '10:15:00', 1, 0, 'Tejidos María'),
('VTA-20260218-0002', 15, NULL, 1, 'menor', 'contado', 56.50, 0.00, 56.50, 56.50, 'efectivo', NULL, 0, 'pagada', '2026-02-18', '15:30:00', '15:38:00', 1, 0, 'Boutique de Lanas'),
('VTA-20260219-0001', 3, NULL, 2, 'menor', 'contado', 95.00, 0.00, 95.00, 95.00, 'efectivo', NULL, 0, 'pagada', '2026-02-19', '09:30:00', '09:40:00', 1, 0, 'Mamá Teje'),
('VTA-20260219-0002', 7, NULL, 2, 'mayor', 'credito', 1950.00, 50.00, 1900.00, 800.00, 'transferencia', 'TRA-01234', 0, 'pendiente', '2026-02-19', '11:15:00', '11:40:00', 1, 0, 'Fábrica de Chompas'),
('VTA-20260220-0001', 4, NULL, 1, 'mayor', 'credito', 820.00, 20.00, 800.00, 300.00, 'QR', 'QR-24680', 0, 'pendiente', '2026-02-20', '10:30:00', '10:50:00', 1, 0, 'Lanas y Puntos'),
('VTA-20260220-0002', 2, NULL, 2, 'mayor', 'credito', 670.00, 15.00, 655.00, 0.00, NULL, NULL, 0, 'pendiente', '2026-02-20', '14:00:00', '14:20:00', 1, 0, 'Artesanías Andinas'),
('VTA-20260221-0001', NULL, 'Feria Dominical', 1, 'mayor', 'contado', 1850.00, 60.00, 1790.00, 1790.00, 'transferencia', 'TRA-13579', 0, 'pagada', '2026-02-21', '09:00:00', '09:35:00', 1, 0, 'Venta feria dominical'),

-- SEMANA 4: 22-28 FEBRERO
('VTA-20260222-0001', 6, NULL, 2, 'menor', 'contado', 132.00, 0.00, 132.00, 132.00, 'efectivo', NULL, 0, 'pagada', '2026-02-22', '10:15:00', '10:28:00', 1, 0, 'Artesanías Titicaca'),
('VTA-20260222-0002', 11, NULL, 2, 'mayor', 'credito', 880.00, 20.00, 860.00, 350.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-22', '15:30:00', '15:50:00', 1, 0, 'Tejidos Bolivianos'),
('VTA-20260223-0001', 14, NULL, 1, 'mayor', 'credito', 750.00, 15.00, 735.00, 300.00, 'transferencia', 'TRA-24680', 0, 'pendiente', '2026-02-23', '09:45:00', '10:05:00', 1, 0, 'Cooperativa Tejedores'),
('VTA-20260223-0002', 5, NULL, 2, 'menor', 'contado', 39.00, 0.00, 39.00, 39.00, 'efectivo', NULL, 0, 'pagada', '2026-02-23', '11:30:00', '11:36:00', 1, 0, 'Doña Rosa'),
('VTA-20260224-0001', 8, NULL, 2, 'mayor', 'mixto', 950.00, 25.00, 925.00, 400.00, 'QR', 'QR-36912', 0, 'pendiente', '2026-02-24', '10:00:00', '10:20:00', 1, 0, 'Mercado de Tejidos'),
('VTA-20260224-0002', 13, NULL, 1, 'menor', 'contado', 48.00, 0.00, 48.00, 48.00, 'efectivo', NULL, 0, 'pagada', '2026-02-24', '16:00:00', '16:08:00', 1, 0, 'Carmen López'),
('VTA-20260225-0001', 1, NULL, 2, 'mayor', 'credito', 590.00, 10.00, 580.00, 200.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-25', '09:30:00', '09:45:00', 1, 0, 'Tejidos María'),
('VTA-20260225-0002', 10, NULL, 2, 'mayor', 'credito', 480.00, 10.00, 470.00, 150.00, 'transferencia', 'TRA-36912', 0, 'pendiente', '2026-02-25', '14:30:00', '14:45:00', 1, 0, 'Taller de Lanas'),
('VTA-20260226-0001', 12, NULL, 1, 'menor', 'contado', 76.00, 0.00, 76.00, 76.00, 'efectivo', NULL, 0, 'pagada', '2026-02-26', '10:45:00', '10:55:00', 1, 0, 'Mercado Camacho'),
('VTA-20260226-0002', 15, NULL, 2, 'menor', 'contado', 62.50, 0.00, 62.50, 62.50, 'efectivo', NULL, 0, 'pagada', '2026-02-26', '15:15:00', '15:22:00', 1, 0, 'Boutique de Lanas'),
('VTA-20260227-0001', 7, NULL, 2, 'mayor', 'credito', 2300.00, 70.00, 2230.00, 1000.00, 'transferencia', 'TRA-48216', 0, 'pendiente', '2026-02-27', '09:00:00', '09:30:00', 1, 0, 'Fábrica de Chompas - Pedido fin mes'),
('VTA-20260227-0002', 2, NULL, 1, 'mayor', 'credito', 590.00, 15.00, 575.00, 0.00, NULL, NULL, 0, 'pendiente', '2026-02-27', '11:30:00', '11:50:00', 1, 0, 'Artesanías Andinas'),
('VTA-20260228-0001', NULL, 'Cierre de mes', 2, 'mayor', 'contado', 1200.00, 40.00, 1160.00, 1160.00, 'QR', 'QR-48216', 0, 'pagada', '2026-02-28', '10:00:00', '10:25:00', 1, 0, 'Venta cierre febrero'),
('VTA-20260228-0002', 4, NULL, 2, 'mayor', 'credito', 680.00, 15.00, 665.00, 250.00, 'efectivo', NULL, 0, 'pendiente', '2026-02-28', '15:30:00', '15:50:00', 1, 0, 'Lanas y Puntos - Cierre mes');

-- ====================================================
-- INSERTS PARA VENTA_DETALLES (FEBRERO 2026)
-- ====================================================
INSERT INTO venta_detalles (venta_id, producto_id, cantidad_subpaquetes, precio_unitario, subtotal, hora_extraccion, usuario_extraccion) VALUES
-- VTA-20260201-0001
(1, 1, 50, 7.50, 375.00, '09:35:00', 2),
(1, 2, 40, 7.50, 300.00, '09:38:00', 2),
(1, 6, 50, 7.50, 375.00, '09:40:00', 2),
(1, 3, 30, 7.50, 225.00, '09:42:00', 2),
-- VTA-20260201-0002
(2, 12, 25, 25.00, 625.00, '10:20:00', 2),
(2, 13, 20, 25.00, 500.00, '10:22:00', 2),
(2, 31, 100, 5.50, 550.00, '10:25:00', 2),
(2, 32, 80, 5.50, 440.00, '10:27:00', 2),
-- VTA-20260201-0003
(3, 22, 5, 13.00, 65.00, '11:05:00', 1),
(3, 23, 2, 13.00, 26.00, '11:06:00', 1),
-- VTA-20260202-0001
(4, 5, 8, 7.50, 60.00, '09:50:00', 2),
(4, 7, 6, 7.50, 45.00, '09:52:00', 2),
-- VTA-20260202-0002
(5, 28, 50, 29.00, 1450.00, '11:35:00', 2),
(5, 29, 40, 29.00, 1160.00, '11:38:00', 2),
(5, 30, 30, 29.00, 870.00, '11:40:00', 2),
-- VTA-20260203-0001
(6, 16, 15, 21.00, 315.00, '10:05:00', 1),
(6, 17, 12, 21.00, 252.00, '10:08:00', 1),
(6, 18, 10, 21.00, 210.00, '10:10:00', 1),
-- VTA-20260203-0002
(7, 8, 20, 7.50, 150.00, '14:35:00', 2),
(7, 9, 15, 10.50, 157.50, '14:38:00', 2),
(7, 10, 12, 10.50, 126.00, '14:40:00', 2),
-- VTA-20260204-0001
(8, 14, 3, 25.00, 75.00, '11:18:00', 1),
-- VTA-20260204-0002
(9, 34, 25, 16.00, 400.00, '15:35:00', 2),
(9, 35, 20, 16.00, 320.00, '15:38:00', 2),
(9, 36, 18, 16.00, 288.00, '15:40:00', 2),
-- VTA-20260205-0001
(10, 19, 8, 32.00, 256.00, '09:25:00', 2),
(10, 20, 6, 32.00, 192.00, '09:28:00', 2),
(10, 21, 5, 32.00, 160.00, '09:30:00', 2),
-- VTA-20260205-0002
(11, 4, 5, 7.50, 37.50, '16:03:00', 1),
(11, 11, 3, 10.50, 31.50, '16:05:00', 1),
-- VTA-20260206-0001
(12, 37, 8, 34.00, 272.00, '10:35:00', 2),
(12, 38, 6, 34.00, 204.00, '10:38:00', 2),
(12, 39, 5, 34.00, 170.00, '10:40:00', 2),
-- VTA-20260206-0002
(13, 40, 10, 41.00, 410.00, '11:50:00', 2),
(13, 41, 8, 41.00, 328.00, '11:53:00', 2),
(13, 42, 7, 41.00, 287.00, '11:56:00', 2),
-- VTA-20260207-0001
(14, 25, 15, 38.00, 570.00, '09:05:00', 1),
(14, 26, 12, 38.00, 456.00, '09:08:00', 1),
(14, 27, 10, 38.00, 380.00, '09:10:00', 1),
-- VTA-20260207-0002
(15, 14, 2, 25.00, 50.00, '12:32:00', 2),
-- VTA-20260208-0001
(16, 22, 3, 13.00, 39.00, '10:05:00', 1),
(16, 24, 2, 13.00, 26.00, '10:06:00', 1),
-- VTA-20260208-0002
(17, 33, 40, 5.50, 220.00, '11:35:00', 2),
(17, 31, 30, 5.50, 165.00, '11:38:00', 2),
(17, 32, 35, 5.50, 192.50, '11:40:00', 2),
-- VTA-20260209-0001
(18, 1, 8, 7.50, 60.00, '09:20:00', 2),
(18, 2, 5, 7.50, 37.50, '09:22:00', 2),
-- VTA-20260209-0002
(19, 9, 3, 10.50, 31.50, '15:48:00', 1),
(19, 10, 2, 10.50, 21.00, '15:49:00', 1),
-- VTA-20260210-0001
(20, 3, 40, 7.50, 300.00, '10:35:00', 2),
(20, 6, 30, 7.50, 225.00, '10:38:00', 2),
(20, 7, 35, 7.50, 262.50, '10:40:00', 2),
-- VTA-20260210-0002
(21, 12, 5, 25.00, 125.00, '14:25:00', 2),
(21, 13, 2, 25.00, 50.00, '14:26:00', 2),
-- VTA-20260211-0001
(22, 28, 45, 29.00, 1305.00, '09:50:00', 2),
(22, 29, 35, 29.00, 1015.00, '09:53:00', 2),
(22, 30, 20, 29.00, 580.00, '09:56:00', 2),
-- VTA-20260211-0002
(23, 16, 25, 21.00, 525.00, '11:05:00', 1),
(23, 17, 20, 21.00, 420.00, '11:08:00', 1),
(23, 18, 15, 21.00, 315.00, '11:10:00', 1),
-- VTA-20260212-0001
(24, 34, 20, 16.00, 320.00, '10:20:00', 2),
(24, 35, 15, 16.00, 240.00, '10:22:00', 2),
(24, 36, 12, 16.00, 192.00, '10:24:00', 2),
-- VTA-20260212-0002
(25, 22, 5, 13.00, 65.00, '16:33:00', 1),
(25, 23, 4, 13.00, 52.00, '16:35:00', 1),
-- VTA-20260213-0001
(26, 40, 8, 41.00, 328.00, '09:35:00', 2),
(26, 41, 6, 41.00, 246.00, '09:38:00', 2),
(26, 42, 5, 41.00, 205.00, '09:40:00', 2),
-- VTA-20260213-0002
(27, 5, 4, 7.50, 30.00, '11:48:00', 1),
(27, 8, 2, 7.50, 15.00, '11:49:00', 1),
-- VTA-20260214-0001
(28, 7, 15, 7.50, 112.50, '10:05:00', 2),
(28, 14, 3, 25.00, 75.00, '10:08:00', 2),
(28, 15, 2, 25.00, 50.00, '10:10:00', 2),
-- VTA-20260214-0002
(29, 31, 80, 5.50, 440.00, '15:05:00', 2),
(29, 32, 70, 5.50, 385.00, '15:08:00', 2),
(29, 33, 50, 5.50, 275.00, '15:10:00', 2),
-- VTA-20260215-0001
(30, 37, 12, 34.00, 408.00, '09:25:00', 1),
(30, 38, 10, 34.00, 340.00, '09:28:00', 1),
(30, 39, 8, 34.00, 272.00, '09:30:00', 1),
-- VTA-20260215-0002
(31, 25, 12, 38.00, 456.00, '11:05:00', 2),
(31, 26, 10, 38.00, 380.00, '11:08:00', 2),
(31, 27, 6, 38.00, 228.00, '11:10:00', 2),
-- VTA-20260216-0001
(32, 1, 3, 8.50, 25.50, '10:32:00', 2),
-- VTA-20260216-0002
(33, 4, 4, 8.50, 34.00, '16:18:00', 1),
(33, 9, 3, 12.50, 37.50, '16:20:00', 1),
-- VTA-20260217-0001
(34, 31, 50, 5.50, 275.00, '09:50:00', 2),
(34, 32, 40, 5.50, 220.00, '09:52:00', 2),
-- VTA-20260217-0002
(35, 2, 6, 7.50, 45.00, '14:33:00', 2),
(35, 6, 5, 7.50, 37.50, '14:35:00', 2),
-- VTA-20260218-0001
(36, 11, 25, 10.50, 262.50, '10:05:00', 2),
(36, 10, 20, 10.50, 210.00, '10:08:00', 2),
-- VTA-20260218-0002
(37, 19, 3, 35.00, 105.00, '15:33:00', 1),
-- VTA-20260219-0001
(38, 12, 3, 28.00, 84.00, '09:35:00', 2),
-- VTA-20260219-0002
(39, 28, 35, 29.00, 1015.00, '11:20:00', 2),
(39, 29, 30, 29.00, 870.00, '11:23:00', 2),
-- VTA-20260220-0001
(40, 16, 20, 21.00, 420.00, '10:35:00', 1),
(40, 17, 15, 21.00, 315.00, '10:38:00', 1),
-- VTA-20260220-0002
(41, 19, 8, 32.00, 256.00, '14:05:00', 2),
(41, 20, 6, 32.00, 192.00, '14:08:00', 2),
(41, 21, 5, 32.00, 160.00, '14:10:00', 2),
-- VTA-20260221-0001
(42, 22, 50, 13.00, 650.00, '09:05:00', 1),
(42, 23, 45, 13.00, 585.00, '09:08:00', 1),
(42, 24, 40, 13.00, 520.00, '09:10:00', 1),
-- VTA-20260222-0001
(43, 5, 8, 7.50, 60.00, '10:20:00', 2),
(43, 7, 6, 7.50, 45.00, '10:22:00', 2),
-- VTA-20260222-0002
(44, 40, 8, 41.00, 328.00, '15:35:00', 2),
(44, 41, 6, 41.00, 246.00, '15:38:00', 2),
(44, 42, 5, 41.00, 205.00, '15:40:00', 2),
-- VTA-20260223-0001
(45, 34, 20, 16.00, 320.00, '09:50:00', 1),
(45, 35, 15, 16.00, 240.00, '09:53:00', 1),
(45, 36, 12, 16.00, 192.00, '09:56:00', 1),
-- VTA-20260223-0002
(46, 2, 3, 7.50, 22.50, '11:32:00', 2),
(46, 3, 2, 7.50, 15.00, '11:33:00', 2),
-- VTA-20260224-0001
(47, 12, 15, 25.00, 375.00, '10:05:00', 2),
(47, 13, 12, 25.00, 300.00, '10:08:00', 2),
(47, 14, 10, 25.00, 250.00, '10:10:00', 2),
-- VTA-20260224-0002
(48, 8, 4, 7.50, 30.00, '16:03:00', 1),
(48, 9, 2, 10.50, 21.00, '16:04:00', 1),
-- VTA-20260225-0001
(49, 1, 30, 7.50, 225.00, '09:35:00', 2),
(49, 4, 25, 7.50, 187.50, '09:38:00', 2),
(49, 6, 20, 7.50, 150.00, '09:40:00', 2),
-- VTA-20260225-0002
(50, 31, 40, 5.50, 220.00, '14:35:00', 2),
(50, 32, 35, 5.50, 192.50, '14:38:00', 2),
-- VTA-20260226-0001
(51, 7, 6, 7.50, 45.00, '10:48:00', 1),
(51, 10, 3, 10.50, 31.50, '10:50:00', 1),
-- VTA-20260226-0002
(52, 15, 2, 25.00, 50.00, '15:18:00', 2),
-- VTA-20260227-0001
(53, 28, 40, 29.00, 1160.00, '09:05:00', 2),
(53, 29, 35, 29.00, 1015.00, '09:08:00', 2),
-- VTA-20260227-0002
(54, 19, 8, 32.00, 256.00, '11:35:00', 1),
(54, 20, 6, 32.00, 192.00, '11:38:00', 1),
(54, 21, 4, 32.00, 128.00, '11:40:00', 1),
-- VTA-20260228-0001
(55, 16, 25, 21.00, 525.00, '10:05:00', 2),
(55, 17, 20, 21.00, 420.00, '10:08:00', 2),
(55, 18, 15, 21.00, 315.00, '10:10:00', 2),
-- VTA-20260228-0002
(56, 22, 25, 13.00, 325.00, '15:35:00', 2),
(56, 23, 20, 13.00, 260.00, '15:38:00', 2),
(56, 24, 8, 13.00, 104.00, '15:40:00', 2);

-- ====================================================
-- INSERTS PARA CLIENTES_CUENTAS_COBRAR (FEBRERO 2026)
-- ====================================================
INSERT INTO clientes_cuentas_cobrar (cliente_id, venta_id, monto_total, saldo_pendiente, fecha_vencimiento, estado, usuario_registro, observaciones) VALUES
(1, 1, 1225.00, 725.00, '2026-03-01', 'pendiente', 2, 'Saldo pendiente - Tejidos María'),
(2, 2, 2300.50, 1300.50, '2026-03-01', 'pendiente', 2, 'Saldo pendiente - Artesanías Andinas'),
(7, 5, 3350.00, 2350.00, '2026-03-02', 'pendiente', 2, 'Crédito Fábrica de Chompas'),
(4, 6, 870.00, 570.00, '2026-03-03', 'pendiente', 1, 'Lanas y Puntos - Saldo'),
(8, 9, 1170.00, 670.00, '2026-03-04', 'pendiente', 2, 'Mercado de Tejidos'),
(2, 10, 765.00, 765.00, '2026-03-05', 'pendiente', 2, 'Crédito total Artesanías Andinas'),
(11, 12, 925.00, 525.00, '2026-03-06', 'pendiente', 2, 'Tejidos Bolivianos'),
(14, 13, 1310.00, 810.00, '2026-03-06', 'pendiente', 2, 'Cooperativa Tejedores'),
(1, 20, 960.00, 560.00, '2026-03-10', 'pendiente', 2, 'Segunda compra mes'),
(7, 22, 2720.00, 1720.00, '2026-03-11', 'pendiente', 2, 'Fábrica de Chompas - Pedido grande'),
(4, 24, 735.00, 435.00, '2026-03-12', 'pendiente', 2, 'Lanas y Puntos - Reabastecimiento'),
(2, 26, 870.00, 870.00, '2026-03-13', 'pendiente', 2, 'Artesanías Andinas - Crédito'),
(8, 29, 1070.00, 570.00, '2026-03-14', 'pendiente', 2, 'Mercado de Tejidos - Cbba'),
(11, 30, 1170.00, 670.00, '2026-03-15', 'pendiente', 1, 'Tejidos Bolivianos - Exportación'),
(14, 31, 960.00, 560.00, '2026-03-15', 'pendiente', 2, 'Cooperativa Tejedores'),
(10, 34, 540.00, 340.00, '2026-03-17', 'pendiente', 2, 'Taller de Lanas'),
(1, 36, 635.00, 435.00, '2026-03-18', 'pendiente', 2, 'Tejidos María'),
(7, 39, 1900.00, 1100.00, '2026-03-19', 'pendiente', 2, 'Fábrica de Chompas'),
(4, 40, 800.00, 500.00, '2026-03-20', 'pendiente', 1, 'Lanas y Puntos'),
(2, 41, 655.00, 655.00, '2026-03-20', 'pendiente', 2, 'Artesanías Andinas'),
(11, 44, 860.00, 510.00, '2026-03-22', 'pendiente', 2, 'Tejidos Bolivianos'),
(14, 45, 735.00, 435.00, '2026-03-23', 'pendiente', 1, 'Cooperativa Tejedores'),
(8, 47, 925.00, 525.00, '2026-03-24', 'pendiente', 2, 'Mercado de Tejidos'),
(1, 49, 580.00, 380.00, '2026-03-25', 'pendiente', 2, 'Tejidos María'),
(10, 50, 470.00, 320.00, '2026-03-25', 'pendiente', 2, 'Taller de Lanas'),
(7, 53, 2230.00, 1230.00, '2026-03-27', 'pendiente', 2, 'Fábrica de Chompas - Fin mes'),
(2, 54, 575.00, 575.00, '2026-03-27', 'pendiente', 1, 'Artesanías Andinas'),
(4, 56, 665.00, 415.00, '2026-03-28', 'pendiente', 2, 'Lanas y Puntos - Cierre mes');

-- ====================================================
-- INSERTS PARA PAGOS_CLIENTES (FEBRERO 2026 - ABONOS)
-- ====================================================
INSERT INTO pagos_clientes (tipo, cliente_id, venta_id, monto, metodo_pago, referencia, fecha, hora, usuario_id, observaciones) VALUES
('abono', 1, 1, 200.00, 'efectivo', NULL, '2026-02-05', '10:30:00', 2, 'Abono Tejidos María'),
('abono', 2, 2, 300.00, 'transferencia', 'TRA-67890', '2026-02-06', '11:15:00', 1, 'Abono Artesanías Andinas'),
('abono', 7, 5, 500.00, 'efectivo', NULL, '2026-02-08', '09:45:00', 2, 'Abono Fábrica de Chompas'),
('abono', 4, 6, 150.00, 'QR', 'QR-54321', '2026-02-08', '14:20:00', 2, 'Abono Lanas y Puntos'),
('abono', 2, 10, 200.00, 'efectivo', NULL, '2026-02-10', '10:05:00', 1, 'Abono Artesanías Andinas'),
('abono', 11, 12, 150.00, 'transferencia', 'TRA-78901', '2026-02-11', '11:30:00', 2, 'Abono Tejidos Bolivianos'),
('abono', 14, 13, 200.00, 'efectivo', NULL, '2026-02-12', '09:20:00', 2, 'Abono Cooperativa'),
('abono', 1, 20, 150.00, 'efectivo', NULL, '2026-02-15', '11:45:00', 2, 'Abono segunda compra'),
('abono', 7, 22, 600.00, 'transferencia', 'TRA-90123', '2026-02-16', '10:30:00', 2, 'Abono pedido grande'),
('abono', 4, 24, 100.00, 'QR', 'QR-13579', '2026-02-17', '14:15:00', 2, 'Abono reabastecimiento'),
('abono', 8, 29, 200.00, 'efectivo', NULL, '2026-02-18', '15:30:00', 2, 'Abono Mercado de Tejidos'),
('abono', 11, 30, 200.00, 'transferencia', 'TRA-01234', '2026-02-19', '09:50:00', 1, 'Abono exportación'),
('abono', 14, 31, 150.00, 'efectivo', NULL, '2026-02-20', '11:20:00', 2, 'Abono Cooperativa'),
('abono', 10, 34, 100.00, 'QR', 'QR-24680', '2026-02-21', '10:40:00', 2, 'Abono Taller de Lanas'),
('abono', 1, 36, 100.00, 'efectivo', NULL, '2026-02-22', '09:30:00', 2, 'Abono Tejidos María'),
('abono', 7, 39, 400.00, 'transferencia', 'TRA-13579', '2026-02-23', '11:15:00', 2, 'Abono Fábrica de Chompas'),
('abono', 4, 40, 120.00, 'efectivo', NULL, '2026-02-24', '10:25:00', 1, 'Abono Lanas y Puntos'),
('abono', 11, 44, 150.00, 'transferencia', 'TRA-24680', '2026-02-25', '15:45:00', 2, 'Abono Tejidos Bolivianos'),
('abono', 14, 45, 120.00, 'efectivo', NULL, '2026-02-26', '09:35:00', 1, 'Abono Cooperativa'),
('abono', 8, 47, 150.00, 'QR', 'QR-36912', '2026-02-26', '14:20:00', 2, 'Abono Mercado de Tejidos'),
('abono', 1, 49, 80.00, 'efectivo', NULL, '2026-02-27', '10:15:00', 2, 'Abono Tejidos María'),
('abono', 10, 50, 80.00, 'transferencia', 'TRA-36912', '2026-02-27', '11:30:00', 2, 'Abono Taller de Lanas'),
('abono', 7, 53, 500.00, 'efectivo', NULL, '2026-02-28', '09:45:00', 2, 'Abono Fábrica de Chompas'),
('abono', 4, 56, 100.00, 'efectivo', NULL, '2026-02-28', '16:00:00', 2, 'Abono Lanas y Puntos');

-- ====================================================
-- INSERTS PARA VENTA_OTROS_PRODUCTOS (FEBRERO 2026)
-- ====================================================
INSERT INTO venta_otros_productos (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
(3, 1, 2, 8.00, 16.00),
(3, 6, 1, 6.00, 6.00),
(4, 2, 3, 8.00, 24.00),
(4, 7, 2, 6.00, 12.00),
(7, 13, 2, 15.00, 30.00),
(7, 15, 3, 8.00, 24.00),
(11, 3, 1, 8.00, 8.00),
(11, 8, 1, 6.00, 6.00),
(14, 19, 1, 12.00, 12.00),
(14, 20, 1, 12.00, 12.00),
(15, 4, 2, 15.00, 30.00),
(15, 17, 1, 10.00, 10.00),
(18, 5, 1, 15.00, 15.00),
(18, 18, 1, 15.00, 15.00),
(21, 9, 2, 6.00, 12.00),
(25, 14, 1, 9.00, 9.00),
(28, 1, 4, 8.00, 32.00),
(28, 6, 2, 6.00, 12.00),
(32, 2, 1, 8.00, 8.00),
(35, 7, 2, 6.00, 12.00),
(38, 16, 3, 8.00, 24.00),
(42, 11, 5, 5.00, 25.00),
(46, 4, 1, 15.00, 15.00),
(48, 3, 1, 8.00, 8.00),
(51, 5, 1, 15.00, 15.00),
(52, 13, 1, 15.00, 15.00),
(55, 10, 4, 15.00, 60.00);

-- ====================================================
-- INSERTS PARA MOVIMIENTOS_CAJA (FEBRERO 2026)
-- ====================================================
INSERT INTO movimientos_caja (tipo, categoria, monto, descripcion, referencia_venta, fecha, hora, usuario_id, observaciones) VALUES
-- Ingresos por ventas y pagos
('ingreso', 'venta_contado', 85.50, 'Venta contado', 'VTA-20260201-0003', '2026-02-01', '11:10:00', 1, 'Venta rápida'),
('ingreso', 'pago_inicial', 500.00, 'Pago inicial Tejidos María', 'VTA-20260201-0001', '2026-02-01', '09:45:00', 2, 'Efectivo'),
('ingreso', 'pago_inicial', 1000.00, 'Pago inicial Artesanías Andinas', 'VTA-20260201-0002', '2026-02-01', '10:30:00', 2, 'Transferencia TRA-23456'),
('ingreso', 'venta_contado', 115.00, 'Venta contado', 'VTA-20260202-0001', '2026-02-02', '09:55:00', 2, 'Mamá Teje'),
('ingreso', 'pago_inicial', 1000.00, 'Pago inicial Fábrica de Chompas', 'VTA-20260202-0002', '2026-02-02', '11:50:00', 2, 'Efectivo'),
('ingreso', 'pago_inicial', 300.00, 'Pago inicial Lanas y Puntos', 'VTA-20260203-0001', '2026-02-03', '10:20:00', 1, 'Transferencia TRA-34567'),
('ingreso', 'venta_contado', 550.00, 'Venta contado', 'VTA-20260203-0002', '2026-02-03', '14:45:00', 2, 'Pago QR QR-87654'),
('ingreso', 'venta_contado', 45.00, 'Venta rápida', 'VTA-20260204-0001', '2026-02-04', '11:20:00', 1, 'Turista'),
('ingreso', 'pago_inicial', 500.00, 'Pago inicial Mercado Tejidos', 'VTA-20260204-0002', '2026-02-04', '15:45:00', 2, 'Efectivo'),
('ingreso', 'venta_contado', 68.00, 'Venta contado', 'VTA-20260205-0002', '2026-02-05', '16:08:00', 1, 'Doña Rosa'),
('ingreso', 'pago_inicial', 400.00, 'Pago inicial Tejidos Bolivianos', 'VTA-20260206-0001', '2026-02-06', '10:50:00', 2, 'Transferencia TRA-45678'),
('ingreso', 'pago_inicial', 500.00, 'Pago inicial Cooperativa', 'VTA-20260206-0002', '2026-02-06', '12:05:00', 2, 'Efectivo'),
('ingreso', 'venta_contado', 2120.00, 'Venta feria', 'VTA-20260207-0001', '2026-02-07', '09:30:00', 1, 'Transferencia TRA-56789'),
('ingreso', 'venta_contado', 32.50, 'Venta particular', 'VTA-20260207-0002', '2026-02-07', '12:35:00', 2, 'Efectivo'),
('ingreso', 'venta_contado', 56.00, 'Venta contado', 'VTA-20260208-0001', '2026-02-08', '10:08:00', 1, 'Carmen López'),
('ingreso', 'pago_inicial', 300.00, 'Pago inicial Taller Lanas', 'VTA-20260208-0002', '2026-02-08', '11:50:00', 2, 'QR QR-98765'),
('ingreso', 'venta_contado', 95.00, 'Venta contado', 'VTA-20260209-0001', '2026-02-09', '09:25:00', 2, 'Mercado Camacho'),
('ingreso', 'venta_contado', 48.50, 'Venta boutique', 'VTA-20260209-0002', '2026-02-09', '15:52:00', 1, 'Boutique de Lanas'),
('ingreso', 'abono', 200.00, 'Abono Tejidos María', 'VTA-20260201-0001', '2026-02-05', '10:30:00', 2, 'Efectivo'),
('ingreso', 'abono', 300.00, 'Abono Artesanías Andinas', 'VTA-20260201-0002', '2026-02-06', '11:15:00', 1, 'Transferencia TRA-67890'),
('ingreso', 'abono', 500.00, 'Abono Fábrica de Chompas', 'VTA-20260202-0002', '2026-02-08', '09:45:00', 2, 'Efectivo'),
('ingreso', 'abono', 150.00, 'Abono Lanas y Puntos', 'VTA-20260203-0001', '2026-02-08', '14:20:00', 2, 'QR QR-54321'),

-- Gastos del mes
('gasto', 'gasto_almuerzo', 45.00, 'Almuerzo personal', NULL, '2026-02-01', '13:00:00', 1, '3 personas'),
('gasto', 'gasto_varios', 30.00, 'Movilidad envíos', NULL, '2026-02-02', '14:30:00', 2, 'Envío a cliente'),
('gasto', 'gasto_varios', 25.00, 'Refrigerios', NULL, '2026-02-03', '16:00:00', 1, 'Personal'),
('gasto', 'gasto_almuerzo', 42.00, 'Almuerzo equipo', NULL, '2026-02-04', '13:00:00', 2, '3 personas'),
('gasto', 'gasto_varios', 120.00, 'Compra bolsas empaque', NULL, '2026-02-05', '15:30:00', 1, 'Insumos'),
('gasto', 'gasto_varios', 80.00, 'Limpieza local', NULL, '2026-02-06', '11:00:00', 1, 'Productos limpieza'),
('gasto', 'gasto_almuerzo', 56.00, 'Almuerzo', NULL, '2026-02-07', '13:00:00', 2, '4 personas'),
('gasto', 'gasto_varios', 65.00, 'Papelería', NULL, '2026-02-08', '09:30:00', 1, 'Facturas, tickets'),
('gasto', 'gasto_almuerzo', 48.00, 'Almuerzo', NULL, '2026-02-09', '13:00:00', 2, '3 personas'),
('gasto', 'gasto_varios', 35.00, 'Movilidad', NULL, '2026-02-10', '14:45:00', 2, 'Envíos'),
('gasto', 'gasto_almuerzo', 52.00, 'Almuerzo', NULL, '2026-02-11', '13:00:00', 1, '4 personas'),
('gasto', 'gasto_varios', 90.00, 'Mantenimiento local', NULL, '2026-02-12', '10:30:00', 2, 'Limpieza'),
('gasto', 'gasto_almuerzo', 44.00, 'Almuerzo', NULL, '2026-02-13', '13:00:00', 1, '3 personas'),
('gasto', 'gasto_varios', 60.00, 'Material empaque', NULL, '2026-02-14', '11:20:00', 2, 'Cajas, etiquetas'),
('gasto', 'gasto_almuerzo', 58.00, 'Almuerzo', NULL, '2026-02-15', '13:00:00', 2, '4 personas'),
('gasto', 'gasto_varios', 75.00, 'Transporte', NULL, '2026-02-16', '15:15:00', 1, 'Envíos clientes'),
('gasto', 'gasto_almuerzo', 46.00, 'Almuerzo', NULL, '2026-02-17', '13:00:00', 2, '3 personas'),
('gasto', 'gasto_varios', 40.00, 'Café y refrigerios', NULL, '2026-02-18', '16:30:00', 1, 'Personal'),
('gasto', 'gasto_almuerzo', 54.00, 'Almuerzo', NULL, '2026-02-19', '13:00:00', 2, '4 personas'),
('gasto', 'gasto_varios', 85.00, 'Insumos limpieza', NULL, '2026-02-20', '10:45:00', 1, 'Local'),
('gasto', 'gasto_almuerzo', 49.00, 'Almuerzo', NULL, '2026-02-21', '13:00:00', 1, '3 personas'),
('gasto', 'gasto_varios', 70.00, 'Papelería', NULL, '2026-02-22', '09:50:00', 2, 'Hojas, tinta'),
('gasto', 'gasto_almuerzo', 57.00, 'Almuerzo', NULL, '2026-02-23', '13:00:00', 2, '4 personas'),
('gasto', 'gasto_varios', 45.00, 'Movilidad', NULL, '2026-02-24', '14:20:00', 1, 'Envíos'),
('gasto', 'gasto_almuerzo', 51.00, 'Almuerzo', NULL, '2026-02-25', '13:00:00', 1, '3 personas'),
('gasto', 'gasto_varios', 95.00, 'Compra estantes', NULL, '2026-02-26', '11:30:00', 2, 'Mobiliario'),
('gasto', 'gasto_almuerzo', 53.00, 'Almuerzo', NULL, '2026-02-27', '13:00:00', 2, '4 personas'),
('gasto', 'gasto_varios', 38.00, 'Refrigerios', NULL, '2026-02-28', '15:45:00', 1, 'Personal');

-- ====================================================
-- INSERTS PARA PAGOS_PROVEEDORES (FEBRERO 2026)
-- ====================================================
INSERT INTO pagos_proveedores (proveedor_id, monto, metodo_pago, referencia, fecha, usuario_id, observaciones) VALUES
(1, 3000.00, 'transferencia', 'PAG-001-02-26', '2026-02-05', 1, 'Pago parcial Lanas del Sur'),
(2, 4500.00, 'efectivo', NULL, '2026-02-08', 2, 'Pago Textiles Andinos'),
(3, 2000.00, 'transferencia', 'PAG-002-02-26', '2026-02-10', 1, 'Abono Hilos y Colores'),
(4, 3500.00, 'QR', 'QR-PROV-02-26', '2026-02-12', 2, 'Pago Lanas Merino Bolivia'),
(5, 1500.00, 'efectivo', NULL, '2026-02-15', 1, 'Pago Distribuidora de Lanas'),
(6, 2800.00, 'transferencia', 'PAG-003-02-26', '2026-02-18', 2, 'Pago Andean Textiles'),
(1, 2500.00, 'efectivo', NULL, '2026-02-20', 1, 'Segundo pago Lanas del Sur'),
(2, 3500.00, 'transferencia', 'PAG-004-02-26', '2026-02-22', 2, 'Pago Textiles Andinos'),
(3, 1500.00, 'QR', 'QR-PROV-02-26-B', '2026-02-25', 1, 'Pago Hilos y Colores');

-- ====================================================
-- INSERTS PARA HISTORIAL_INVENTARIO (FEBRERO 2026)
-- ====================================================
INSERT INTO historial_inventario (producto_id, tipo_movimiento, paquetes_anteriores, subpaquetes_anteriores, paquetes_nuevos, subpaquetes_nuevos, diferencia, referencia, fecha_hora, usuario_id, observaciones) VALUES
(1, 'venta', 25, 3, 20, 3, -50, 'VENTA-1', '2026-02-01 09:35:00', 2, 'Venta 50 subpaquetes'),
(2, 'venta', 20, 5, 16, 5, -40, 'VENTA-1', '2026-02-01 09:38:00', 2, 'Venta 40 subpaquetes'),
(12, 'venta', 12, 5, 9, 5, -25, 'VENTA-2', '2026-02-01 10:20:00', 2, 'Venta 25 subpaquetes'),
(31, 'venta', 40, 0, 30, 0, -100, 'VENTA-2', '2026-02-01 10:25:00', 2, 'Venta 100 subpaquetes'),
(28, 'venta', 10, 4, 5, 4, -50, 'VENTA-5', '2026-02-02 11:35:00', 2, 'Venta 50 subpaquetes'),
(16, 'venta', 8, 4, 6, 4, -15, 'VENTA-6', '2026-02-03 10:05:00', 1, 'Venta 15 subpaquetes'),
(34, 'venta', 12, 4, 9, 4, -25, 'VENTA-9', '2026-02-04 15:35:00', 2, 'Venta 25 subpaquetes'),
(19, 'venta', 5, 2, 4, 2, -8, 'VENTA-10', '2026-02-05 09:25:00', 2, 'Venta 8 subpaquetes'),
(37, 'venta', 4, 2, 3, 2, -8, 'VENTA-12', '2026-02-06 10:35:00', 2, 'Venta 8 subpaquetes'),
(40, 'venta', 5, 2, 4, 2, -10, 'VENTA-13', '2026-02-06 11:50:00', 2, 'Venta 10 subpaquetes'),
(25, 'venta', 6, 2, 4, 2, -15, 'VENTA-14', '2026-02-07 09:05:00', 1, 'Venta 15 subpaquetes'),
(33, 'venta', 38, 3, 34, 3, -40, 'VENTA-17', '2026-02-08 11:35:00', 2, 'Venta 40 subpaquetes'),
(1, 'ajuste', 20, 3, 22, 3, 20, 'AJUSTE', '2026-02-09 08:00:00', 1, 'Ajuste físico'),
(12, 'ingreso', 9, 5, 12, 5, 25, 'COMPRA-02-01', '2026-02-10 10:00:00', 1, 'Nueva compra'),
(31, 'ingreso', 30, 0, 40, 0, 100, 'COMPRA-02-02', '2026-02-11 11:00:00', 1, 'Nueva compra'),
(28, 'ingreso', 5, 4, 10, 4, 50, 'COMPRA-02-03', '2026-02-12 09:30:00', 2, 'Reabastecimiento'),
(16, 'venta', 6, 4, 4, 4, -20, 'VENTA-23', '2026-02-11 09:05:00', 1, 'Venta 20 subpaquetes'),
(34, 'venta', 9, 4, 7, 4, -20, 'VENTA-24', '2026-02-12 10:20:00', 2, 'Venta 20 subpaquetes'),
(40, 'venta', 4, 2, 3, 2, -8, 'VENTA-26', '2026-02-13 09:35:00', 2, 'Venta 8 subpaquetes'),
(31, 'venta', 40, 0, 32, 0, -80, 'VENTA-29', '2026-02-14 15:05:00', 2, 'Venta 80 subpaquetes'),
(37, 'venta', 3, 2, 2, 2, -12, 'VENTA-30', '2026-02-15 09:25:00', 1, 'Venta 12 subpaquetes'),
(25, 'venta', 4, 2, 3, 2, -12, 'VENTA-31', '2026-02-15 11:05:00', 2, 'Venta 12 subpaquetes'),
(31, 'venta', 32, 0, 27, 0, -50, 'VENTA-34', '2026-02-17 09:50:00', 2, 'Venta 50 subpaquetes'),
(11, 'venta', 25, 2, 23, 2, -25, 'VENTA-36', '2026-02-18 10:05:00', 2, 'Venta 25 subpaquetes'),
(28, 'venta', 10, 4, 6, 4, -35, 'VENTA-39', '2026-02-19 11:20:00', 2, 'Venta 35 subpaquetes'),
(16, 'venta', 4, 4, 2, 4, -20, 'VENTA-40', '2026-02-20 10:35:00', 1, 'Venta 20 subpaquetes'),
(19, 'venta', 4, 2, 3, 2, -8, 'VENTA-41', '2026-02-20 14:05:00', 2, 'Venta 8 subpaquetes'),
(22, 'venta', 18, 4, 13, 4, -50, 'VENTA-42', '2026-02-21 09:05:00', 1, 'Venta 50 subpaquetes'),
(40, 'venta', 3, 2, 2, 2, -8, 'VENTA-44', '2026-02-22 15:35:00', 2, 'Venta 8 subpaquetes'),
(34, 'venta', 7, 4, 5, 4, -20, 'VENTA-45', '2026-02-23 09:50:00', 1, 'Venta 20 subpaquetes'),
(12, 'venta', 12, 5, 10, 5, -15, 'VENTA-47', '2026-02-24 10:05:00', 2, 'Venta 15 subpaquetes'),
(1, 'venta', 22, 3, 19, 3, -30, 'VENTA-49', '2026-02-25 09:35:00', 2, 'Venta 30 subpaquetes'),
(31, 'venta', 27, 0, 23, 0, -40, 'VENTA-50', '2026-02-25 14:35:00', 2, 'Venta 40 subpaquetes'),
(28, 'venta', 6, 4, 2, 4, -40, 'VENTA-53', '2026-02-27 09:05:00', 2, 'Venta 40 subpaquetes'),
(19, 'venta', 3, 2, 2, 2, -8, 'VENTA-54', '2026-02-27 11:35:00', 1, 'Venta 8 subpaquetes'),
(16, 'venta', 2, 4, 0, 4, -25, 'VENTA-55', '2026-02-28 10:05:00', 2, 'Venta 25 subpaquetes'),
(22, 'venta', 13, 4, 10, 4, -25, 'VENTA-56', '2026-02-28 15:35:00', 2, 'Venta 25 subpaquetes');

-- ====================================================
-- INSERTS PARA PROVEEDORES_ESTADO_CUENTAS (FEBRERO 2026)
-- ====================================================
INSERT INTO proveedores_estado_cuentas (proveedor_id, codigo_proveedor, nombre_proveedor, ciudad_proveedor, compra, a_cuenta, adelanto, saldo, fecha, descripcion, usuario_id) VALUES
(1, 'PROV001', 'Lanas del Sur', 'La Paz', 3000.00, 3000.00, 0.00, 11500.50, '2026-02-01', 'Compra inicial mes', 1),
(1, 'PROV001', 'Lanas del Sur', 'La Paz', 0.00, 0.00, 3000.00, 8500.50, '2026-02-05', 'Pago - PAG-001-02-26', 1),
(2, 'PROV002', 'Textiles Andinos', 'El Alto', 4500.00, 4500.00, 0.00, 17000.75, '2026-02-02', 'Compra alpaca', 2),
(2, 'PROV002', 'Textiles Andinos', 'El Alto', 0.00, 0.00, 4500.00, 12500.75, '2026-02-08', 'Pago efectivo', 2),
(3, 'PROV003', 'Hilos y Colores', 'Cochabamba', 2000.00, 2000.00, 0.00, 6500.25, '2026-02-03', 'Compra importada', 1),
(3, 'PROV003', 'Hilos y Colores', 'Cochabamba', 0.00, 0.00, 2000.00, 4500.25, '2026-02-10', 'Pago - PAG-002-02-26', 1),
(4, 'PROV004', 'Lanas Merino Bolivia', 'Santa Cruz', 3500.00, 3500.00, 0.00, 19300.00, '2026-02-04', 'Compra merino', 2),
(4, 'PROV004', 'Lanas Merino Bolivia', 'Santa Cruz', 0.00, 0.00, 3500.00, 15800.00, '2026-02-12', 'Pago QR', 2),
(5, 'PROV005', 'Distribuidora de Lanas', 'La Paz', 1500.00, 1500.00, 0.00, 4700.00, '2026-02-05', 'Compra sintética', 1),
(5, 'PROV005', 'Distribuidora de Lanas', 'La Paz', 0.00, 0.00, 1500.00, 3200.00, '2026-02-15', 'Pago efectivo', 1),
(6, 'PROV006', 'Andean Textiles', 'Oruro', 2800.00, 2800.00, 0.00, 12600.30, '2026-02-06', 'Compra tinturada', 2),
(6, 'PROV006', 'Andean Textiles', 'Oruro', 0.00, 0.00, 2800.00, 9800.30, '2026-02-18', 'Pago - PAG-003-02-26', 2),
(1, 'PROV001', 'Lanas del Sur', 'La Paz', 2500.00, 2500.00, 0.00, 11000.50, '2026-02-15', 'Segunda compra', 1),
(1, 'PROV001', 'Lanas del Sur', 'La Paz', 0.00, 0.00, 2500.00, 8500.50, '2026-02-20', 'Pago efectivo', 1),
(2, 'PROV002', 'Textiles Andinos', 'El Alto', 3500.00, 3500.00, 0.00, 16000.75, '2026-02-18', 'Compra adicional', 2),
(2, 'PROV002', 'Textiles Andinos', 'El Alto', 0.00, 0.00, 3500.00, 12500.75, '2026-02-22', 'Pago transferencia', 2),
(3, 'PROV003', 'Hilos y Colores', 'Cochabamba', 1500.00, 1500.00, 0.00, 6000.25, '2026-02-20', 'Compra nacional', 1),
(3, 'PROV003', 'Hilos y Colores', 'Cochabamba', 0.00, 0.00, 1500.00, 4500.25, '2026-02-25', 'Pago QR', 1);