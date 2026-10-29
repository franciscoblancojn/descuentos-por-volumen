# 📋 Registro de cambios

Todos los cambios importantes del plugin Descuentos por Volumen. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [1.2.0] - 2026-10-29

### ✨ Añadido
- 🧢 **Descuento máximo** (por defecto 40 %) en Reglas. Si el descuento final de un tramo (tramo + categoría, sumando o encadenando) supera el tope, se usa el tope. Aplica al precio rebajado, al carrito, a la tabla de la ficha y al schema.
- 🧩 En la ficha del producto y en el schema, las filas consecutivas con el mismo % final se **agrupan** (p. ej. 6–48 y 49–200 a 40 % → 6–200: 40 %). Si todo queda en una sola fila, no se muestra la tabla ni se publican tramos.

### 🔁 Cambiado
- 🛒 El aviso del carrito usa el % con el tope, y solo ofrece el siguiente tramo si de verdad da más descuento.
- 🧮 El ejemplo de la pestaña Reglas muestra el % final con el tope y marca con "tope" los tramos limitados.

## [1.1.0] - 2026-10-28

### ✨ Añadido
- 🔄 **Sincronización automática en segundo plano** (Action Scheduler de WooCommerce). El precio rebajado de las reglas se aplica solo, sin pulsar "Recalcular":
  - al activar o actualizar el plugin (catálogo que ya existía);
  - al guardar las reglas;
  - una vez al día, como revisión de seguridad.

  Solo toca los productos sin sincronizar, nunca los manuales. Cada pasada con cambios queda en el historial y se puede revertir.
- ⚠️ Aviso en **Reglas** con cuántos productos/variaciones no tienen todavía el precio rebajado de las reglas (por ejemplo, los que existían antes de activar el plugin), junto al botón "Recalcular".
- 🏷️ Etiqueta **"sin sincronizar"** en la tabla de Productos, para los productos cuyo precio rebajado no es el de las reglas.

### 🐛 Corregido
- 🔍 La tabla "Más descuento por cantidad" y los tramos del schema solo se publican si el precio rebajado guardado es el de las reglas. Antes, un producto sin sincronizar (por ejemplo, creado antes de activar el plugin) mostraba "1–5 u.: $14.000" mientras su precio era $20.000.
- 🔍 Schema con Rank Math: su `priceSpecification` es un objeto sin `@type` y se mezclaba con los tramos, lo que dejaba un JSON inválido para Google. Ahora se convierte en lista: el precio original más un precio por tramo.

## [1.0.2] - 2026-10-28

### 🔁 Cambiado
- 📊 Los tramos por cantidad se editan en una **tabla** (Mínimo, Máximo, Descuento %), con un botón "+ Agregar tramo" y otro ✕ para eliminar filas. Antes era un texto `desde|hasta|%`. Por defecto trae las tarifas 1–5 → 30 %, 6–48 → 35 % y 49–200 → 40 %.

## [1.0.1] - 2026-10-28

### ✨ Añadido
- ⚙️ Pestaña **Reglas**:
  - tramos por cantidad (`desde|hasta|%`) y % adicional por categoría, que incluye subcategorías;
  - combinar sumando o encadenando, y contar la cantidad por carrito o por producto;
  - ejemplo calculado en la misma pantalla.
- 🔄 El precio rebajado se calcula con el 1er tramo + la categoría y se mantiene solo al guardar productos. Botón **"Recalcular precios rebajados de todos los productos"**.
- 🛒 Precios por cantidad en el carrito, con un aviso de cuántas unidades faltan para el siguiente tramo y texto de contacto por encima del último.
- 📊 Tabla "Más descuento por cantidad" en la ficha del producto.
- 🔍 SEO: `UnitPriceSpecification` con `eligibleQuantity` por tramo en el schema de WooCommerce y de Rank Math.
- ✋ Precios **manuales**: lo editado a mano o con la herramienta masiva no lo tocan las reglas. Acción "Volver a reglas".
- 📋 Tabla de productos con filtros y acciones masivas (% manual, quitar precio rebajado, volver a reglas), por lotes y con barra de progreso.
- ↩️ Historial de las últimas 30 operaciones, con "Revertir".
- 📝 Log `DPV_LOG` (`FWUSystemLog`): guardado de reglas y cada operación de precios.
- 🔄 Actualizaciones desde GitHub con `FWUUpdate` (wordpress_utils), configurado en `index.php` como en los demás plugins.
- 🧪 `npm run check` (harness) y `npm run push-v`, que actualiza el CHANGELOG.
