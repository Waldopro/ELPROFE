# Manual de Usuario - ELPROFE POS

El Punto de Venta (POS) está orientado para un control integral y una atención super-rápida. Su principal atajo es F2 en cualquier parte del sistema.

## Ingreso y Apertura de Caja
- **Paso 1:** Al iniciar sesión, verás en el Dashboard una advertencia en tu sección de "Estado de Caja", indicando que está *Cerrada*. No podrás vender ni abonar deudas si tienes caja cerrada. Haz clic en "Abrir caja para operar" o selecciona "Mi Caja" en el menú lateral.
- **Paso 2:** En la pantalla "Mi Caja", verifica la hora y la cuenta. Indica un fondo base ($) y un fondo en Bs., en caso de tener alguna base física. Dale a "Abrir Caja".

## Configuración y Creación de Inventario (Solo Administradores)
- **Creación Directa:** Ve a la opción **Inventario** y presiona el botón "1. Nuevo Producto". Rellena los datos (Nombre, Categoría, Precio de Costo USD, Margen de Ganancia que calcula el Precio Público USD Automáticamente o viceversa y el Stock Mínimo).
- **Inventario Inicial Automático:** Si introduces una cantidad en "Stock Actual", se generará de manera mágica detrás de escena una compra al proveedor sistema llamado 'Inventario Inicial', asegurando trazabilidad contable.
- **Variaciones (Cajas/Bultos):** Si de un artículo "Unidad" quieres vender su respectivo Bulto con otro código de barras, ve a "Añadir Presentación" y vincúlalo.
- **Categorías y Proveedores:** En sus propios módulos de la barra lateral puedes organizar tu mercancía o agenda para futuras compras.

## El Proceso de Venta (F2)
- Pulsa la tecla mágica del sistema: **F2**
- Aparecerás en el Terminal POS. Colócate sobre la barra de búsqueda (puede usar tu lector láser, siempre enfocará el cursor allí si hay una pausa).
- **Multicaja Tiempo Real:** Mientras que tienes un producto en el carrito o le das clic en 'Hold/En Espera', ese producto figura temporalmente descontado del stock mientras estés allí resolviendo al cliente. Nadie más lo podrá añadir a otra factura en otro terminal.
- El panel de cobro te permite dividir el tipo de pago: en un solo ticket un cliente puede darte $10 en efectivo dólares y el resto en Punto de Venta. Se guardarán 2 registros de abonos diferenciando las divisas, calculando siempre en base a la Tasa BCV diaria.

## Cobrando Fiados y Deudas Pendientes
- Los abonos se procesan en la vista de **Cobranza (Proformas)**. 
- Filtra por un cliente, selecciona la vista, haz click en el botón `+ Abono` e introduce el nuevo registro basándose en un método diferente o en una moneda equivalente al día local de la transacción.
- Cuando la deuda logre 0, podrás Facturarla fiscalmente, y un número correlativo legal será atado.

## Auditoría Final y Reportes
- En "Auditoría Caja" el sistema separa de forma inquebrantable el registro y métodos por Usuario e Indentificador de su Sesión (Turno).
- Los "Reportes / Libros" te emitirán con un simple filtrado de Mes y Año los tabuladores contables de Ventas Gravadas vs Exentas, pre-computadas para enviar luego el archivo en formato XLS/CSV (.xls) a tu contador de mes, siguiendo en todo momento las normas de Contabilidad Básica y los requisitos del Seniat.

## Cierre de Turno
Cuando vayas a salir o al final de tu día, vuelve a **Mi Caja**, ingresa la sumatoria declarada de tu cierre y haz clic en *Cerrar*, tu supervisor ya podrá constatar que has finalizado sin contratiempos, protegiendo tus operaciones del día.
