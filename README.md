# Sistema de Centralización de Métricas de Marketing

Plataforma web para agencias que centraliza y analiza métricas de marketing digital. Arquitectura MVC estricta (Vista → Controller → Model).

## Contenido Base
- Sistema de Centralización de Métricas de Marketing
- Plataforma web para agencias que centraliza y analiza métricas de marketing digital.
- Arquitectura MVC estricta (Vista → Controller → Model).

## Resumen del Proyecto
- Objetivo: reducir trabajo manual de consolidación y facilitar reporte ejecutivo del rendimiento de campañas.
- Valor: dashboards unificados, insights automáticos y recomendaciones estratégicas basadas en datos.
- Usuarios: empleados de agencia (gestión de clientes y plataformas); los emprendimientos son entidades de datos, no usuarios.
- Alcance: centralización y visualización de métricas; no modifica campañas.

## Acceso Rápido
- Repositorio: `https://github.com/emanueleugster1/sistema-metricas-marketing`
- Sistema (login): `http://54.207.61.138/views/auth/login.php`

## Credenciales de Prueba
- Usuario: `admin`
- Contraseña: `admin`

## Arquitectura
- Patrón MVC vanilla (PHP 8+): separación estricta de responsabilidades.
- Flujo de datos: Vista → Controlador → Modelo → Base de Datos → Controlador → Vista.
- Conexión a base de datos con Singleton compartido en modelos.
- Correspondencia 1:1 entre vistas y assets (`assets/css` y `assets/js`).
- Integraciones externas vía `api/connectors` y handlers de sincronización en `api/handlers`.

## Stack Tecnológico
- Backend: PHP 8+ (MVC vanilla), cURL para APIs REST.
- Base de Datos: MySQL/MariaDB (utf8mb4), 10 tablas principales.
- Frontend: HTML5, CSS3, JavaScript ES6+, Bootstrap 5, Chart.js para visualizaciones.
- IA/ML: Gemini Flash 2.5 para insights; `php-ml` para análisis predictivo y recomendaciones.

## Estructura del Proyecto
```
/
├── docs/                    Documentación del proyecto
├── api/                     Conectores e integración con APIs externas
│   ├── connectors/          Conectores por plataforma (Meta, Google, LinkedIn)
│   └── handlers/            Lógica de sincronización y validación
├── assets/                  Recursos frontend
│   ├── css/                 Estilos (global y por vista)
│   ├── js/                  Scripts (global y por vista)
│   └── images/              Imágenes e íconos
├── config/                  Configuración del sistema y DB
├── controllers/             Controladores (lógica de negocio)
├── database/                Scripts SQL e instalación
├── includes/                Utilidades compartidas (auth, helpers)
├── logs/                    Logs de sistema
├── models/                  Modelos (lógica de datos)
├── uploads/                 Archivos subidos por usuarios
└── views/                   Vistas (presentación)
    ├── templates/           Plantillas comunes
    ├── auth/                Autenticación
    ├── clientes/            Gestión de clientes
    ├── dashboard/           Dashboards
    ├── plataformas/         Configuración de plataformas
    └── widgets/             Configuración de widgets
```

## Funcionalidades Principales
- Autenticación y sesiones para empleados de agencia.
- Gestión de clientes y credenciales de plataformas publicitarias.
- Extracción y sincronización de métricas (manual y programada).
- Dashboards personalizables con widgets y visualizaciones interactivas.
- Insights automáticos y recomendaciones estratégicas.
- Exportación de reportes.

## Estado de Integraciones
- Cliente Flex: datos reales obtenidos con `access_token` real (nunca expuesto en código/commits).
- Integración actual: Meta (Facebook e Instagram).
- Arquitectura diseñada para futuras integraciones (Google Ads, LinkedIn Ads, Google Analytics, etc.).

## Instalación Local
1. Clonar el repositorio: `git clone https://github.com/emanueleugster1/sistema-metricas-marketing`
2. Crear base de datos local (MySQL/MariaDB):
   - Ejecutar `database/create_database_metricas.sql` o usar `database/install.php`.
3. Configurar conexión en `config/databaseConfig.php` con tus credenciales locales.
4. Servir el proyecto:
   - Opción A (servidor embebido): `php -S localhost:8000 -t .` y navegar a `http://localhost:8000/views/auth/login.php`
   - Opción B (Apache/Nginx): configurar vhost y apuntar el docroot al proyecto.

### Instalación con Docker
- Requisitos: Docker Desktop y Docker Compose.
1. Actualizar `.env` para entorno Docker:
   - `DB_HOST=db`
   - `DB_DATABASE=sistema_metricas_marketing`
   - `DB_USERNAME=metrics_user`
   - `DB_PASSWORD=metrics_pass`
2. Levantar servicios:
   - `docker compose up -d --build`
3. Acceder a la aplicación:
   - `http://localhost/views/auth/login.php`
4. Base de datos:
   - MySQL queda disponible en `localhost:3306` (usuario: `metrics_user`, password: `metrics_pass`).
5. Logs y diagnóstico:
   - `docker compose logs -f web db`
6. Reinicio/limpieza (opcional):
   - `docker compose down -v` para bajar servicios y borrar volúmenes.

## Uso (flujo básico)
- Acceder a `views/auth/login.php` y autenticarse con las credenciales de prueba.
- Crear clientes y configurar credenciales de plataformas.
- Ejecutar sincronización de métricas (manual o programada).
- Explorar dashboards y exportar reportes.

## Consideraciones de Seguridad
- Prepared statements obligatorios en todas las consultas SQL.
- `htmlspecialchars()` en toda salida de datos de usuario.
- No exponer `access_token` ni credenciales sensibles en código/commits.
- Gestión de sesión segura: `session_regenerate_id()` tras login, `session_destroy()` en logout.

## Roadmap
- Integrar Google Ads y LinkedIn Ads.
- Agregar Google Analytics como fuente de atribución.
- Mejorar sistema de permisos/roles por funcionalidad.
- Programar reportes y exportaciones automáticas.
- Biblioteca de widgets ampliada y configuraciones avanzadas.
- Modelos ML adicionales para predicción y anomalías.


## Autor
- Emanuele Ugster (`emanueleugster1`)

## Métricas de Éxito
- Tiempo de respuesta < 3 s; disponibilidad > 99%.
- Reducción de horas de reportería y aumento de satisfacción de clientes.
- Escalabilidad para múltiples agencias y clientes.
