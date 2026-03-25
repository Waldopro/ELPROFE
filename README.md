# 🛒 ELPROFE - Sistema de Punto de Venta (POS)

![Estado](https://img.shields.io/badge/Estado-Fase_1_Completada-success)
![Versión](https://img.shields.io/badge/Versión-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.2+-purple)
![MySQL](https://img.shields.io/badge/MySQL-Triggers_Avanzados-orange)

**ELPROFE** es un sistema Point of Sale (POS) robusto, seguro y moderno diseñado en PHP Estructurado (Procedural) y MySQL. Está construido bajo rigurosos estándares operacionales y reglas de negocio enfocadas a la realidad de negocios físicos con transacciones bimonetarias (Divisas/Moneda Local) y descomposición de inventarios (Padre e Hijos).

---

## ✨ Características Principales

### 📦 Gestión Inteligente de Inventario
- **Estructura Padre/Hijo:** Gestión de productos base con múltiples **presentaciones** (Unidades sueltas, Cajas, Bultos).
- **Factor de Conversión:** El sistema recalcula y descuenta todo a nivel de unidad base automáticamente, evitando el descuadre de inventarios al vender unidades sueltas extraídas de una caja.
- **Triggers de MySQL:** Blindaje a nivel motor. El inventario solo se afecta a través de registros legítimos en los módulos de Compras (suma) y Ventas (resta), actualizando promedios de costo en tiempo real sin cálculos en bruto en el backend (PHP).
- **Etiquetas de Barras:** Generador y dibujador visual de códigos de barra (Formato Internacional) listo para impresoras tickets o láser.

### 💰 Contabilidad y Multimoneda
- **Manejo Dual de Moneda:** Todas las operaciones de cálculo (Ventas y Compras) utilizan una Tasa del Día global configurable (`tasa_usd_bs`).
- **Cobranza a Crédito (Fiado):** Emisión de proformas (Notas de Entrega) que quedan en estado por cobrar (`PENDIENTE` o `PARCIAL`).
- **Ponderación Dinámica de Deudas:** Si un cliente abona dinero a un fiado días después, el sistema automáticamente usa la Tasa del Día **actual** y calcula el equivalente exacto restándolo de su saldo global en las divisas que se anclaron de origen.
- **Control de Abonos (Split-Payment):** Un cliente puede pagar una proforma en varias partes o usar diferentes métodos simultáneamente (Ej: Efectivo USD + Punto de Venta + Binance).
- **Auditoría de Caja:** Historial transparente en cascada, clasificado por Cajero, Movimientos Operativos, Métodos de Pago y Balance de Turno (Sesiones de Caja).

### 🛡️ Seguridad Operacional y Arquitectura
- **Protección de Accesos Internos:** Sistema de Roles RBAC (`Administrador` vs `Cajero` visible).
- **Seguridad Web:** Sistema blindado contra *Cross-Site Request Forgery (CSRF)* con tokens obligatorios en todas las llamadas `AJAX` y formularios. Salidas sanitizadas contra `XSS`. Cifrado de Password en One-Way Hashing (`Bcrypt`).
- **Diseño Mobile-First:** Desarrollado sobre Bootstrap 5, completamente amigable e intuitivo, con Modo Oscuro/Claro nativo y transacciones de carga limpias tipo Single-Page App (Manejo de estados con SweetAlert2).
- **Recepción de Hardware en Vivo:** Listener global asíncrono en JavaScript para atrapar cadenas de ingreso USB desde pistolas de códigos de barra, enrutándose sin perder el *focus*.

---

## 🛠 Instalación y Despliegue

### 1. Requisitos del Servidor
- Servidor Web (Apache/Nginx integrado con URLs Amigables Mod_Rewrite activo)
- PHP >= 8.0 (Extensiones recomendadas: `pdo_mysql`, `mbstring`, `json`)
- MySQL >= 8.0 o MariaDB equivalente

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
