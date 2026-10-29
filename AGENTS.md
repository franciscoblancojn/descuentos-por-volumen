# Descuentos por Volumen — Reglas para IAs

## Código
- **PHP 7.4+**, estándares de WordPress. Clases y funciones con prefijo `DPV_`.
- **JavaScript ES5 + jQuery** (sin arrow functions, `let`/`const` ni template literals). Funciones con prefijo `dpv_`.
- **CSS** con prefijo `dpv-` (las clases `fwue-` son de `wordpress_utils`).
- Escapar toda salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) y sanitizar toda entrada.

## Seguridad
- Todo AJAX: `check_ajax_referer('dpv_nonce', 'nonce')` + `current_user_can('manage_woocommerce')` (ver `DPV_API_DISCOUNT::guard()`).
- **Nunca** escribir tokens o claves en el código (ni partidos en arrays, como `token_array_split`). El token de GitHub de `FWUUpdate` va en `wp-config.php` (`DPV_GITHUB_TOKEN`). `npm run check` falla si encuentra uno.

## Arquitectura
- `index.php`: cabecera y constantes `DPV_*`. Sin lógica.
- `src/_.php`: cargador maestro. Cada carpeta tiene su `_.php`.
- Plugin genérico: **no** usar nombres ni textos de un sitio concreto (el contenido por defecto es solo un ejemplo editable).
- Reglas: `DPV_RULES` es la única fuente del cálculo (tienda, carrito, tabla de la ficha y schema). Si cambia la fórmula, cambia ahí.
- Al guardar precios desde el plugin, usar `DPV_DISCOUNT::process()` (suspende la sincronización automática para no pisar la acción).
- Precios: siempre con la API CRUD de WooCommerce (`set_sale_price()` + `save()`), nunca con `update_post_meta` directo; después de tocar variaciones llamar `DPV_DISCOUNT::syncParents()`.
- Toda operación que cambie precios debe quedar en el historial (`DPV_USE_DATA_HISTORY`) con el valor anterior, para poder revertir, y en el log (`DPV_LOG::add`).

## Publicar
- Anotar los cambios en `CHANGELOG.md` bajo `## [Sin publicar]`.
- `npm run push-v -- patch|minor|major`.
