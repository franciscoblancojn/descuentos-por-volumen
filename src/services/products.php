<?php

/**
 * Consulta de productos para la tabla y para resolver a qué productos se aplica un descuento.
 *
 * "Con precio" = productos a los que se les puede poner precio rebajado:
 * simples, externos y variaciones. Los variables no tienen precio propio
 * (se aplica a sus variaciones).
 */
class DPV_PRODUCTS
{
    const TYPES = ['simple', 'variable', 'external'];
    const STATUSES = ['publish', 'private', 'draft', 'pending', 'future'];
    const PER_PAGE = [25, 50, 100, 200];

    /**
     * Filtros normalizados a partir de $_GET / $_POST.
     */
    public static function filters($source)
    {
        $source = (array) $source;
        $f = [
            's' => sanitize_text_field(wp_unslash($source['s'] ?? '')),
            'cat' => sanitize_title(wp_unslash($source['cat'] ?? '')),
            'tag' => sanitize_title(wp_unslash($source['tag'] ?? '')),
            'brand' => sanitize_title(wp_unslash($source['brand'] ?? '')),
            'type' => sanitize_key($source['type'] ?? ''),
            'sale' => sanitize_key($source['sale'] ?? ''),
            'mode' => sanitize_key($source['mode'] ?? ''),
            'per_page' => absint($source['per_page'] ?? 50),
            'paged' => max(1, absint($source['paged'] ?? 1)),
        ];
        if (!in_array($f['type'], self::TYPES, true)) {
            $f['type'] = '';
        }
        if (!in_array($f['sale'], ['with', 'without'], true)) {
            $f['sale'] = '';
        }
        if (!in_array($f['mode'], ['auto', 'manual'], true)) {
            $f['mode'] = '';
        }
        if (!in_array($f['per_page'], self::PER_PAGE, true)) {
            $f['per_page'] = 50;
        }

        return $f;
    }

    public static function brandTaxonomy()
    {
        foreach (['product_brand', 'pwb-brand', 'yith_product_brand'] as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                return $taxonomy;
            }
        }

        return '';
    }

    /**
     * Argumentos de WP_Query para los productos padre que cumplen los filtros.
     */
    private static function queryArgs($f)
    {
        $tax_query = [
            [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => $f['type'] ? [$f['type']] : self::TYPES,
            ],
        ];
        if ($f['cat']) {
            $tax_query[] = ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [$f['cat']], 'include_children' => true];
        }
        if ($f['tag']) {
            $tax_query[] = ['taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => [$f['tag']]];
        }
        if ($f['brand'] && self::brandTaxonomy()) {
            $tax_query[] = ['taxonomy' => self::brandTaxonomy(), 'field' => 'slug', 'terms' => [$f['brand']]];
        }

        $args = [
            'post_type' => 'product',
            'post_status' => self::STATUSES,
            'orderby' => 'title',
            'order' => 'ASC',
            'tax_query' => array_merge(['relation' => 'AND'], $tax_query),
            'no_found_rows' => false,
        ];

        $include = null;
        if ('' !== $f['s']) {
            // Título, contenido y SKU (también SKU de variaciones → devuelve el padre).
            $data_store = WC_Data_Store::load('product');
            $include = $data_store->search_products($f['s'], '', true, true);
            $parents = [];
            foreach ($include as $id) {
                $parent = wp_get_post_parent_id($id);
                $parents[] = $parent ? $parent : $id;
            }
            $include = array_values(array_unique(array_filter(array_map('absint', $parents))));
        }

        // wc_get_product_ids_on_sale() incluye los padres de variaciones en oferta.
        if ('with' === $f['sale']) {
            $on_sale = wc_get_product_ids_on_sale();
            $include = null === $include ? $on_sale : array_values(array_intersect($include, $on_sale));
        } elseif ('without' === $f['sale']) {
            $args['post__not_in'] = wc_get_product_ids_on_sale();
        }

        // Modo del precio rebajado: manual (meta DPV_MANUAL_META) o automático (reglas).
        if ('manual' === $f['mode']) {
            $manual = self::manualParents()['any'];
            $include = null === $include ? $manual : array_values(array_intersect($include, $manual));
        } elseif ('auto' === $f['mode']) {
            $args['post__not_in'] = array_values(array_unique(array_merge($args['post__not_in'] ?? [], self::manualParents()['all'])));
        }

        if (null !== $include) {
            $args['post__in'] = $include ? $include : [0];
        }

        return $args;
    }

    /**
     * Productos padre con precio manual:
     *   any = simples/externos manuales y variables con alguna variación manual,
     *   all = simples/externos manuales y variables con todas sus variaciones manuales.
     *
     * @return array{any:int[], all:int[]}
     */
    private static function manualParents()
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_parent, p.post_type FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = '1'
             WHERE p.post_type IN ('product', 'product_variation')",
            DPV_MANUAL_META
        ));
        $any = [];
        $manual_children = [];
        foreach ($rows as $row) {
            if ('product_variation' === $row->post_type) {
                $parent = (int) $row->post_parent;
                $any[] = $parent;
                $manual_children[$parent] = ($manual_children[$parent] ?? 0) + 1;
            } else {
                $any[] = (int) $row->ID;
            }
        }
        $all = [];
        foreach ($rows as $row) {
            if ('product' === $row->post_type) {
                $all[] = (int) $row->ID;
            }
        }
        foreach ($manual_children as $parent => $count) {
            $children = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation' AND post_status IN ('publish', 'private')",
                $parent
            ));
            if ((int) $children > 0 && $count >= (int) $children) {
                $all[] = $parent;
            }
        }

        return ['any' => array_values(array_unique($any)), 'all' => array_values(array_unique($all))];
    }

    /**
     * Página de resultados para la tabla.
     *
     * @return array{total:int, pages:int, items:array}
     */
    public static function page($f)
    {
        $query = new WP_Query(array_merge(self::queryArgs($f), [
            'posts_per_page' => $f['per_page'],
            'paged' => $f['paged'],
        ]));

        $items = [];
        foreach ($query->posts as $post) {
            $product = wc_get_product($post);
            if (!$product) {
                continue;
            }
            $row = self::row($product);
            if ($product->is_type('variable')) {
                $row['children'] = [];
                foreach ($product->get_children() as $child_id) {
                    $variation = wc_get_product($child_id);
                    if ($variation) {
                        $row['children'][] = self::row($variation);
                    }
                }
            }
            $items[] = $row;
        }

        return [
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'items' => $items,
        ];
    }

    public static function row($product)
    {
        $is_variation = $product->is_type('variation');
        $regular = $product->is_type('variable') ? '' : $product->get_regular_price('edit');
        $sale = $product->is_type('variable') ? '' : $product->get_sale_price('edit');
        $from = $product->get_date_on_sale_from('edit');
        $to = $product->get_date_on_sale_to('edit');

        return [
            'id' => $product->get_id(),
            'type' => $product->get_type(),
            'name' => $is_variation ? wc_get_formatted_variation($product, true, true, false) : $product->get_name(),
            'sku' => $product->get_sku('edit'),
            'status' => $product->get_status(),
            'image' => wp_get_attachment_image_url($product->get_image_id() ? $product->get_image_id() : get_post_thumbnail_id($product->get_parent_id()), 'thumbnail'),
            'edit' => get_edit_post_link($is_variation ? $product->get_parent_id() : $product->get_id(), 'raw'),
            'link' => $product->get_permalink(),
            'cats' => $is_variation ? '' : wp_strip_all_tags(wc_get_product_category_list($product->get_id(), ', ')),
            'regular' => $regular,
            'sale' => $sale,
            'percent' => DPV_DISCOUNT::percentOf($regular, $sale),
            'scheduled' => ($from || $to) ? trim(($from ? $from->date_i18n('Y-m-d') : '…') . ' → ' . ($to ? $to->date_i18n('Y-m-d') : '…')) : '',
            'priced' => !$product->is_type('variable'),
            'manual' => DPV_RULES::isManual($product),
            // false = las reglas aplican pero el precio rebajado guardado no es el de las reglas
            'synced' => DPV_RULES::applies($product) ? DPV_RULES::isSynced($product) : null,
        ];
    }

    /**
     * IDs con precio (simples, externos y variaciones) de TODOS los productos que cumplen los filtros.
     *
     * @return int[]
     */
    public static function pricedIdsByFilters($f)
    {
        $parent_ids = get_posts(array_merge(self::queryArgs($f), [
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]));

        $ids = self::expand($parent_ids);
        if ($f['mode'] ?? '') {
            // En variables, solo las variaciones del modo pedido.
            $want_manual = 'manual' === $f['mode'];
            $ids = array_values(array_filter($ids, function ($id) use ($want_manual) {
                $product = wc_get_product($id);

                return $product && DPV_RULES::isManual($product) === $want_manual;
            }));
        }

        return $ids;
    }

    /**
     * Convierte IDs (padres variables, simples o variaciones) en IDs con precio, sin repetir.
     *
     * @return int[]
     */
    public static function expand($ids)
    {
        $out = [];
        foreach (array_unique(array_map('absint', (array) $ids)) as $id) {
            $product = $id ? wc_get_product($id) : null;
            if (!$product) {
                continue;
            }
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $child_id) {
                    $out[] = (int) $child_id;
                }
            } elseif (in_array($product->get_type(), ['simple', 'external', 'variation'], true)) {
                $out[] = $product->get_id();
            }
        }

        return array_values(array_unique($out));
    }
}
