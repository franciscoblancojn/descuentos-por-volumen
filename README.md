# 🏷️ Descuentos por Volumen

> Plugin de WordPress para WooCommerce: **descuentos por cantidad y por categoría**. Guarda el primer descuento como **precio rebajado**, para que se vea en la tienda, en Google y en los feeds. Aplica los demás tramos en el carrito y los publica en el schema del producto.

**Versión:** 1.0.0 · **WordPress:** 5.8+ · **PHP:** 7.4+ · **WooCommerce:** requerido · **Licencia:** GPLv2+

📋 [Registro de cambios](CHANGELOG.md) · 🤖 [Reglas para IAs](AGENTS.md) · 📦 [Releases](https://github.com/franciscoblancojn/descuentos-por-volumen/releases)

---

## 💡 Cómo funciona

```
precio rebajado (guardado) = precio normal × (1 − (% del 1er tramo + % de su categoría))
precio en el carrito       = precio normal × (1 − (% del tramo de la cantidad + % de su categoría))
```

Ejemplo con tramos 1–5 → 30 %, 6–48 → 35 %, 49–200 → 40 % y la categoría Ofertas con +4 %:

| Producto de $100.000 | 1–5 u. | 6–48 u. | 49–200 u. |
|---|---|---|---|
| Sin categoría con regla | $70.000 | $65.000 | $60.000 |
| En Ofertas (+4 %) | $66.000 | $61.000 | $56.000 |

- **Tienda y SEO:** se ve el precio normal tachado y el rebajado (1er tramo + categoría). Ese es el precio que leen Google, Rank Math y los feeds.
- **Schema:** el producto publica un `UnitPriceSpecification` con `eligibleQuantity` por cada tramo. Así Google sabe que hay más descuento por cantidad.
- **Ficha del producto:** tabla "Más descuento por cantidad" debajo del precio.
- **Carrito:** el precio baja según la cantidad. La cantidad puede ser el total del carrito o la de cada producto. Un aviso dice cuántas unidades faltan para el siguiente tramo; por encima del último se muestra un texto de contacto.

## ✨ Características

| | Funcionalidad | Detalle |
|---|---|---|
| ⚙️ | **Reglas** | Tramos `desde|hasta|%`, % adicional por categoría (incluye subcategorías), combinar **sumando** (30 + 4 = 34 %) o **encadenando** (32,8 %), cantidad por carrito o por producto. Ejemplo calculado en la misma pantalla. |
| 🔄 | **Sincronización** | El precio rebajado se recalcula al guardar un producto: cambio de precio normal, de categoría, importaciones. Hay un botón **"Recalcular todos"** para aplicar las reglas a todo el catálogo. |
| ✋ | **Precios manuales** | Si se cambia el precio rebajado a mano, o con la herramienta masiva, el producto queda como **manual** y las reglas no lo tocan. "Volver a reglas" lo devuelve. |
| 📋 | **Tabla de productos** | Productos y variaciones, con filtros por búsqueda, categoría, etiqueta, marca, tipo y estado del descuento. Acciones: % manual, quitar precio rebajado o volver a reglas, sobre los seleccionados o sobre todos los filtrados. |
| ⚡ | **Por lotes** | De a 40 productos, con barra de progreso. |
| ↩️ | **Historial** | Últimas 30 operaciones, con **Revertir** (precio, fechas de oferta y marca manual). |
| 📝 | **Log** | `DPV_LOG` en la barra de administración. |
| 🔄 | **Actualizaciones desde GitHub** | Con `FWUUpdate` de `wordpress_utils`, como los demás plugins. El token va en `wp-config.php`, nunca en el código. |

## ⚠️ A tener en cuenta

- Al aplicar reglas o un % se **borran las fechas de oferta programada**, para que el precio rebajado rija desde ya. Revertir las recupera.
- Los productos sin precio normal se omiten.
- Si la tienda usa otro plugin de descuentos (p. ej. Woo Discount Rules), se acumulan: revisar que no se apliquen dos veces.
- Los productos **manuales** no reciben los tramos por cantidad en el carrito.

## 📋 Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce activo
- Permiso `manage_woocommerce` (administrador o gestor de la tienda)

## ⚙️ Instalación

1. Descarga `descuentos-por-volumen.zip` de la [última release](https://github.com/franciscoblancojn/descuentos-por-volumen/releases/latest).
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin** y actívalo.
3. Ve a **Descuentos → Reglas**, revisa los tramos y los % por categoría y guarda.
4. Pulsa **"Recalcular precios rebajados de todos los productos"**.

## 🔄 Actualizaciones

- Usa `FWUUpdate` (librería `wordpress_utils`): en **Plugins** aparece "Actualizar" cuando hay una release más nueva en GitHub. Descarga el zip de la rama `master`, y el plugin lo instala en su carpeta de siempre.
- **🔑 Token obligatorio:** `FWUUpdate` siempre envía `Authorization: token …`, y con un token vacío GitHub responde 401 aunque el repo sea público. Agrégalo en `wp-config.php` (token *fine-grained*, solo lectura de este repo):

  ```php
  define('DPV_GITHUB_TOKEN', '...');
  ```

  **Nunca en el código**: `npm run check` falla si encuentra uno. Sin token, las actualizaciones automáticas quedan desactivadas y Plugins muestra un aviso.

## 🧑‍💻 Desarrollo

```bash
npm run install          # composer install → libs/ (franciscoblancojn/wordpress_utils)
npm run check            # validaciones: php -l, JS ES5, require_once, secretos
npm run push-v -- patch  # o minor | major → publica una versión
```

`npm run push-v`:

1. exige una sección `## [Sin publicar]` en [`CHANGELOG.md`](CHANGELOG.md) y que `npm run check` pase;
2. sube la versión en `index.php`, `package.json` y este README;
3. cambia `## [Sin publicar]` por `## [X.Y.Z] - fecha`;
4. hace commit "Release X.Y.Z", crea el tag `X.Y.Z` y hace push.

Después, GitHub Actions crea la release con `descuentos-por-volumen.zip`.

## 🗂️ Estructura

```
descuentos-por-volumen/
├── index.php                 # Cabecera del plugin, constantes DPV_*, carga
├── libs/                     # franciscoblancojn/wordpress_utils (Composer → libs)
├── bin/harness.sh            # Validaciones (npm run check)
├── scripts/bump-version.sh   # npm run push-v
├── .github/workflows/        # Release con el zip al subir un tag
└── src/
    ├── _.php                 # Cargador maestro
    ├── log/                  # DPV_LOG (FWUSystemLog)
    ├── update/               # DPV_UPDATER (FWUUpdate + token de wp-config + carpeta fija)
    ├── data/                 # DPV_USE_DATA_BASE, _HISTORY, _CONFIG (wp_options)
    ├── services/             # DPV_RULES (motor de reglas), DPV_PRODUCTS (filtros), DPV_DISCOUNT (aplicar)
    ├── hooks/                # sync (al guardar), cart (tramos), product (tabla en la ficha), schema (SEO)
    ├── api/                  # DPV_API_DISCOUNT (AJAX por lotes), DPV_API_RULES (guardar reglas)
    ├── templates/            # Filtros, tabla de productos, paginación
    ├── page/                 # Menú "Descuentos" y secciones (FWUPage, FWUCollapse)
    ├── js/admin.js           # Vista previa, selección, lotes (ES5 + jQuery)
    └── css/admin.css
```
