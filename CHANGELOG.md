# 📋 Registro de cambios

Todos los cambios importantes del plugin Descuentos por Volumen. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [Sin publicar]

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
