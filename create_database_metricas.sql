-- Script de creaciÃ³n de base de datos para Sistema de CentralizaciÃ³n de MÃ©tricas
-- TFG - Universidad Siglo 21
-- Autor: Fernando Emanuel Eugster Cufre

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS sistema_metricas_marketing
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE sistema_metricas_marketing;

-- Tabla usuarios
CREATE TABLE usuarios (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla plataformas
CREATE TABLE plataformas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    api_base_url VARCHAR(500),
    activa BOOLEAN DEFAULT TRUE
);

-- Tabla clientes
CREATE TABLE clientes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    sector VARCHAR(100),
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla plataforma_campos
CREATE TABLE plataforma_campos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    plataforma_id BIGINT NOT NULL,
    nombre_campo VARCHAR(100) NOT NULL,
    label VARCHAR(100) NOT NULL,
    tipo ENUM('text', 'password', 'textarea') DEFAULT 'text',
    orden INT DEFAULT 0,
    descripcion VARCHAR(255),
    FOREIGN KEY (plataforma_id) REFERENCES plataformas(id) ON DELETE CASCADE
);

-- Tabla credenciales_plataforma
CREATE TABLE credenciales_plataforma (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT NOT NULL,
    plataforma_id BIGINT NOT NULL,
    credenciales JSON NOT NULL,
    validada BOOLEAN DEFAULT FALSE,
    fecha_validacion DATETIME NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (plataforma_id) REFERENCES plataformas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cliente_plataforma (cliente_id, plataforma_id)
);

-- Tabla metricas
CREATE TABLE metricas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT NOT NULL,
    plataforma_id BIGINT NOT NULL,
    fecha_metrica DATE NOT NULL,
    nombre_metrica VARCHAR(100) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    unidad VARCHAR(50),
    fecha_extraccion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (plataforma_id) REFERENCES plataformas(id) ON DELETE CASCADE,
    INDEX idx_cliente_fecha (cliente_id, fecha_metrica),
    INDEX idx_plataforma_fecha (plataforma_id, fecha_metrica)
);

-- Tabla recomendaciones_ml
CREATE TABLE recomendaciones_ml (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT NOT NULL,
    contenido TEXT NOT NULL,
    fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabla widgets
CREATE TABLE widgets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tipo_visualizacion ENUM('chart', 'table', 'metric', 'gauge') NOT NULL,
    metrica_principal VARCHAR(100),
    orden_defecto INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla dashboards
CREATE TABLE dashboards (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabla dashboard_widgets (relaciÃ³n muchos a muchos entre dashboards y widgets)
CREATE TABLE dashboard_widgets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    dashboard_id BIGINT NOT NULL,
    widget_id BIGINT NOT NULL,
    visible BOOLEAN DEFAULT TRUE,
    orden INT DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dashboard_id) REFERENCES dashboards(id) ON DELETE CASCADE,
    FOREIGN KEY (widget_id) REFERENCES widgets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dashboard_widget (dashboard_id, widget_id)
);

-- Crear Ã­ndices adicionales para optimizaciÃ³n de consultas
CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_activo ON usuarios(activo);
CREATE INDEX idx_clientes_usuario ON clientes(usuario_id);
CREATE INDEX idx_clientes_activo ON clientes(activo);
CREATE INDEX idx_credenciales_cliente ON credenciales_plataforma(cliente_id);
CREATE INDEX idx_credenciales_validada ON credenciales_plataforma(validada);
CREATE INDEX idx_metricas_nombre ON metricas(nombre_metrica);
CREATE INDEX idx_recomendaciones_fecha ON recomendaciones_ml(fecha_generacion);
CREATE INDEX idx_dashboards_cliente ON dashboards(cliente_id);

-- Comentarios sobre el diseÃ±o de la base de datos
/*
NOTAS SOBRE EL DISEÃ‘O:

1. USUARIOS: Tabla principal para autenticaciÃ³n de agencias
2. PLATAFORMAS: CatÃ¡logo de plataformas publicitarias disponibles (Meta, Google, etc.)
3. CLIENTES: Clientes de cada agencia con informaciÃ³n bÃ¡sica
4. PLATAFORMA_CAMPOS: ConfiguraciÃ³n dinÃ¡mica de campos por plataforma
5. CREDENCIALES_PLATAFORMA: Almacena credenciales API en formato JSON encriptado
6. METRICAS: Datos histÃ³ricos de mÃ©tricas extraÃ­das de APIs
7. RECOMENDACIONES_ML: AnÃ¡lisis predictivo generado por PHP-ML
8. WIDGETS: Componentes configurables para dashboards
9. DASHBOARDS: Dashboards personalizados por cliente
10. DASHBOARD_WIDGETS: RelaciÃ³n configuraciÃ³n de widgets en dashboards

CARACTERÃSTICAS DE SEGURIDAD:
- Credenciales almacenadas en JSON para flexibilidad
- Ãndices optimizados para consultas frecuentes
- Cascade delete para integridad referencial
- Campos de auditorÃ­a (fecha_creacion, fecha_actualizacion)
*/