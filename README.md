# 🛒 ELPROFE - Sistema de Punto de Venta (POS)

![Estado](https://img.shields.io/badge/Estado-Fase_1_Completada-success)
![Versión](https://img.shields.io/badge/Versión-1.1.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.4-purple)
![MySQL](https://img.shields.io/badge/MariaDB-11.8-orange)

**ELPROFE** es un sistema Point of Sale (POS) robusto, seguro y moderno diseñado en PHP Estructurado (Procedural) y MySQL. Está construido bajo rigurosos estándares operacionales y reglas de negocio enfocadas a la realidad de negocios físicos con transacciones bimonetarias (Divisas/Moneda Local) y descomposición de inventarios (Padre e Hijos).

---

## ✨ Características Principales

### 📦 Gestión Inteligente de Inventario
- **Estructura Padre/Hijo:** Gestión de productos base con múltiples **presentaciones** (Unidades sueltas, Cajas, Bultos).
- **Factor de Conversión:** El sistema recalcula y descuenta todo a nivel de unidad base automáticamente, evitando el descuadre de inventarios al vender unidades sueltas extraídas de una caja.
- **Triggers de MySQL:** Blindaje a nivel motor. El inventario solo se afecta a través de registros legítimos en los módulos de Compras (suma) y Ventas (resta), actualizando promedios de costo en tiempo real sin cálculos en bruto en el backend (PHP).
- **Etiquetas de Barras:** Generador y dibujador visual de códigos de barra (Formato Internacional) listo para impresoras tickets o láser.

### 💰 Contabilidad y Multimoneda
- **Manejo Dual de Moneda:** API scraper en tiempo real conectada al Banco Central de Venezuela (BCV) o Tasa de Cambio Manual. Modificable globalmente en el Panel de Configuración.
- **Auditoría Estricta Multicaja:** Control individualizado de sesiones de caja por usuario. Reportes avanzados por fecha, usuario y método de pago, además de generación nativa de los **Libros de Compras y Ventas** exigidos por el SENIAT con exportación a PDF y Excel (XLS).
- **Control de Inventarios Integrado:** Modal único de alta de Producto Completo con creación de "Inventario Inicial" automatizado para máxima trazabilidad. Reservas temporales exclusivas (Holds) entre múltiples cajas operando al mismo tiempo.
- **Cobranza a Crédito (Fiado):** Emisión de proformas (Notas de Entrega) que quedan en estado por cobrar (`PENDIENTE` o `PARCIAL`).
- **Ponderación Dinámica de Deudas:** Si un cliente abona dinero a un fiado días después, el sistema automáticamente usa la Tasa del Día **actual** y calcula el equivalente exacto restándolo de su saldo global en las divisas que se anclaron de origen.
- **Control de Abonos (Split-Payment):** Un cliente puede pagar una proforma en varias partes o usar diferentes métodos simultáneamente (Ej: Efectivo USD + Pago Móvil + Binance).
- **Auditoría de Caja:** Historial transparente en cascada, clasificado por Cajero, Movimientos Operativos, Métodos de Pago y Balance de Turno (Sesiones de Caja).
- **Historial en POS:** Desde el módulo `Ventas (Punto de Venta)` puedes abrir el historial de proformas recientes y re-emitir/consultar comprobantes.
- **Gestión de Usuarios:** El módulo `Usuarios` permite **editar** usuarios (no solo crear/eliminar) manteniendo control RBAC.
- **Cobro Profesional en POS:** El modal de pago permite elegir explícitamente modalidad `USD`, `Bs` o `Mixto` antes de capturar montos, mostrando siempre total y restante en ambas monedas.
- **Crédito con Abono Inicial:** Al vender a crédito (`FIADO`), el cajero puede registrar un abono inicial en la misma operación y dejar el saldo pendiente automáticamente en USD (ancla), con estado `PENDIENTE` o `PARCIAL`.
- **Tasa Dinámica por Día en Abonos Bs:** Cada abono en bolívares se guarda con la tasa vigente del día del pago, preservando consistencia para cobranzas en fechas distintas.

### 📱 Compartición y Movilidad (Novedades)
- **Progressive Web App (PWA):** Sistema instalable gráficamente en dispositivos Android/iOS y Escritorios (Chrome/Edge) con soporte offline en caché (Service Workers) y Manifest dedicado.
- **Escáner de Cámara para Móviles:** Integración avanzada con `html5-qrcode` que permite usar la cámara trasera de cualquier smartphone como lector de códigos de barras profesional, ideal para inventariado rápido y ventas sin hardware adicional. (Requiere HTTPS).
- **Módulo Multi-Comprobante Pro:** Generación bajo normativas SENIAT de *Tickets Térmicos* (58mm/80mm) con rediseño de alta legibilidad, desglose dinámico de impuestos IVA/B.I., e identificación automática de **Caja** (01, 02, etc.) según el cajero en sesión.
- **Compartición Nativa Web-Share (WhatsApp):** Motor gráfico impulsado por **HTML2Canvas** capaz de fotografiar virtualmente los comprobantes de venta desde el navegador (limpiando botones) y convertirlos en imágenes .PNG físicas de alta calidad. Envío instantáneo por *WhatsApp Native* en móviles, guardando copias perpetuas transaccionales en el servidor (`assets/tickets`).
- **Gestión de Cobranza Inteligente:** Módulo de cobranza (Fiados) con filtros reactivos para separar deudas activas del historial pagado, facilitando el control de cuentas por cobrar.
- **Notas Editables Al Vuelo:** Opción táctil de escritura rápida sobre las facturas Web para colocar observaciones instantáneas antes de la impresión, almacenadas mediante Caché `localStorage`.
- **Entorno de Testing Autónomo:** Área exclusiva para Administradores que carga "Plantillas Genéricas de Prueba Guiada", permitiendo revisar y calibrar las ticketeras sin necesidad de procesar ventas ni ensuciar la Base de Datos.
- **Catálogo Modal de Productos en POS:** Botón de catálogo con búsqueda por nombre/código y selección rápida para cargar productos al carrito.
- **Alertas de Stock en Tiempo Real:** Notificaciones globales y en POS para `sin stock` y `stock bajo`, actualizadas por sondeo periódico.

### 🛡️ Seguridad Operacional y Arquitectura
- **Protección de Accesos Internos:** Sistema de Roles RBAC (`Administrador` vs `Cajero` visible).
- **Seguridad Web:** Sistema blindado contra *Cross-Site Request Forgery (CSRF)* con tokens obligatorios en todas las llamadas `AJAX` y formularios. Salidas sanitizadas contra `XSS`. Cifrado de Password en One-Way Hashing (`Bcrypt`).
- **Enlaces Compartidos (Tickets / Notas):** Los comprobantes compartidos por WhatsApp ahora usan un **token firmado (HMAC)** para evitar enumeración por `id` sin autorización. Configura `ELPROFE_SHARE_LINK_SECRET` en `.env` para producción.
- **Diseño Mobile-First:** Desarrollado sobre Bootstrap 5, completamente amigable e intuitivo, con Modo Oscuro/Claro nativo y transacciones de carga limpias tipo Single-Page App (Manejo de estados con SweetAlert2).
- **UI Responsiva Mejorada:** Layout con sidebar (desktop) + offcanvas (móvil), logo mejor visible y altura del menú consistente.
- **Recepción de Hardware en Vivo:** Listener global asíncrono en JavaScript para atrapar cadenas de ingreso USB desde pistolas de códigos de barra, enrutándose sin perder el *focus*.

---

## 🛠 Instalación y Despliegue

### 1. Requisitos del Servidor
- Servidor Web (Apache/Nginx integrado con URLs Amigables Mod_Rewrite activo)
- PHP >= 8.4 (Extensiones recomendadas: `pdo_mysql`, `mbstring`, `json`, `gd` o `cURL`)
- MariaDB >= 11.8 o MySQL equivalente superior

### 2. Puesta en Marcha Inicial
1. Clona el repositorio en tu espacio HTDocs o `/var/www/html/ELPROFE`.
2. Habilita una Base de Datos vacía en tu gestor SQL.
3. Importa rigurosamente el archivo  `database.sql` incluido; aquí reside la "inteligencia" matemática:
   - Esquemas (`Tablas`, `Restricciones CASCADE / RESTRICT`).
   - Disparadores Estrictos (`Triggers` para inventarios).
   - Inyecciones `INSERT` con usuarios principales de testeo.
4. Vincula el archivo interno `includes/db.php` colocando el usuario y puerto preciso hacia tu motor de Base de Datos recientemente alimentado.

### 3. Accesos de Demo
| Usuario Base | Username | Password | Nivel de Privilegios |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin` | `123456` | Absolutos (Flujo contable, Proveedores y Compras) |
| **Cajero Estándar** | `caja01` | `123456` | Estricto (Restricción solo a Front POS) |

*(Se recomienda modificar los hashes internos de claves posteriormente para puestas en producción).*

---

## 🏗 Arquitectura Funcional del Código Base

El flujo y enrutamiento ha sido desacoplado para garantizar escabilidad de negocio en la rama Main:

```text
ELPROFE/
├── api/          # Endpoints de Lógica Transaccional (Reciben peticiones AJAX).
├── assets/       # Capa de presentación abstracta (CSS global, Framework JS, POS.js).
├── includes/     # Lógicas compartidas, Conexiones a DB (PDO), Macros Header/Footer, Tokens.
├── pages/        # Capa de Vistas modulares por Roles de Flujo de Tienda.
├── .htaccess     # Motor de Clean Routing URLs.
├── database.sql  # Mapa general SQL + Trigger Engines (Inventarios).
└── index.php     # Entry Point.
```
