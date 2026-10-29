/* Descuentos por Volumen — admin (ES5 + jQuery). Datos de PHP en window.DPV. */
jQuery(function ($) {
    var $page = $('.dpv-page');
    if (!$page.length) {
        return;
    }
    var busy = false;
    // Prefijo de los elementos de progreso y mensaje de la operación en curso
    // ('#dpv' en Productos, '#dpv-sync' en Reglas).
    var ui = '#dpv';

    // ------------------------------------------------------------------ utilidades

    function dpv_format_price(value) {
        var fixed = Number(value).toFixed(DPV.decimals);
        var parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, DPV.thousand);
        return DPV.currency + ' ' + parts.join(DPV.decimal);
    }

    function dpv_calc(regular, percent) {
        var factor = Math.pow(10, DPV.decimals);
        return Math.round(regular * (1 - percent / 100) * factor) / factor;
    }

    function dpv_percent() {
        return parseFloat($('#dpv-percent').val());
    }

    function dpv_message(text, type) {
        $(ui + '-message').html(text ? '<p class="fwue-message dpv-message ' + type + '"></p>' : '')
            .find('p').text(text);
    }

    function dpv_post(action, data) {
        return $.ajax({
            url: DPV.ajax,
            method: 'POST',
            dataType: 'json',
            data: $.extend({ action: 'dpv_' + action, nonce: DPV.nonce }, data)
        }).then(function (res) {
            if (!res || !res.success) {
                return $.Deferred().reject(res && res.data ? res.data : 'Error inesperado.').promise();
            }
            return res.data;
        }, function (xhr) {
            return $.Deferred().reject('Error de conexión (' + xhr.status + ').').promise();
        });
    }

    // ------------------------------------------------------------------ selección

    function dpv_selected_ids() {
        return $page.find('.dpv-check--priced:checked').map(function () {
            return this.value;
        }).get();
    }

    function dpv_update_count() {
        $('#dpv-selected-count').text(dpv_selected_ids().length);
    }

    $page.on('change', '.dpv-check-all', function () {
        $page.find('.dpv-check').prop('checked', this.checked);
        dpv_update_count();
    });

    // Marcar un variable marca todas sus variaciones.
    $page.on('change', '.dpv-check--parent', function () {
        $page.find('tr[data-parent="' + this.value + '"] .dpv-check').prop('checked', this.checked);
        dpv_update_count();
    });

    $page.on('change', '.dpv-check--priced', function () {
        var parent = $(this).closest('tr').data('parent');
        if (parent) {
            var $siblings = $page.find('tr[data-parent="' + parent + '"] .dpv-check');
            $page.find('tr[data-id="' + parent + '"] .dpv-check').prop('checked', $siblings.length === $siblings.filter(':checked').length);
        }
        dpv_update_count();
    });

    $page.on('click', '.dpv-toggle', function () {
        var open = $(this).attr('aria-expanded') !== 'true';
        $(this).attr('aria-expanded', open ? 'true' : 'false');
        $page.find('tr[data-parent="' + $(this).data('id') + '"]').toggle(open);
    });

    // Enlace al historial desde el texto de ayuda.
    $page.on('click', '[data-dpv-tab]', function () {
        $page.find('.nav-tab[data-tab="' + $(this).data('dpv-tab') + '"]').trigger('click');
    });

    // ------------------------------------------------------------------ tramos (pestaña Reglas)

    var tier_index = 0;

    $('#dpv-tier-add').on('click', function () {
        // Índice único para que los name="dpv[tiers][…]" no choquen con las filas existentes.
        tier_index += 1;
        var html = $('#dpv-tier-template').html().replace(/__i__/g, 'new' + tier_index);
        var $row = $(html).appendTo('#dpv-tiers tbody');
        $row.find('input').first().trigger('focus');
    });

    $page.on('click', '.dpv-tier-remove', function () {
        $(this).closest('tr').remove();
    });

    // ------------------------------------------------------------------ vista previa

    $('#dpv-percent').on('input', function () {
        var percent = dpv_percent();
        var valid = percent > 0 && percent < 100;
        $page.find('tr[data-regular]').each(function () {
            var regular = parseFloat($(this).data('regular'));
            var $cell = $(this).find('.dpv-preview');
            if (!valid || !(regular > 0)) {
                $cell.text('');
                return;
            }
            $cell.text(dpv_format_price(dpv_calc(regular, percent)));
        });
    });

    // ------------------------------------------------------------------ procesamiento por lotes

    function dpv_progress(done, total, label) {
        var pct = total ? Math.round(done / total * 100) : 0;
        $(ui + '-progress').prop('hidden', false);
        $(ui + '-progress .dpv-progress__bar span').css('width', pct + '%');
        $(ui + '-progress .dpv-progress__text').text(label + ': ' + done + ' de ' + total + ' (' + pct + ' %)');
    }

    function dpv_set_busy(state) {
        busy = state;
        $page.find('#dpv-apply, #dpv-remove, #dpv-rules, #dpv-sync-all, .dpv-revert').prop('disabled', state);
    }

    // Procesa los IDs de la operación en lotes, uno tras otro.
    function dpv_run(op, ids, size, label) {
        var done = 0;
        var skipped = 0;
        var deferred = $.Deferred();

        function next() {
            if (done >= ids.length) {
                dpv_post('finish', { op: op }).then(deferred.resolve, deferred.reject);
                return;
            }
            var chunk = ids.slice(done, done + size);
            dpv_post('batch', { op: op, ids: chunk }).then(function (data) {
                done += chunk.length;
                skipped += Object.keys(data.skipped || {}).length;
                dpv_progress(done, ids.length, label);
                next();
            }, deferred.reject);
        }

        dpv_progress(0, ids.length, label);
        next();
        return deferred.promise();
    }

    var LABELS = {
        apply: 'Aplicando descuento',
        remove: 'Quitando precio rebajado',
        rules: 'Volviendo a reglas',
        sync: 'Recalculando según las reglas'
    };

    function dpv_question(action, percent, total) {
        if (action === 'apply') {
            return '¿Aplicar ' + percent + ' % de descuento manual a ' + total + ' productos/variaciones?\n\nSe reemplaza su precio rebajado actual y quedan como "manual" (las reglas no los cambian). Queda en el historial para revertir.';
        }
        if (action === 'remove') {
            return '¿Quitar el precio rebajado de ' + total + ' productos/variaciones?\n\nQuedan como "manual". Queda en el historial para revertir.';
        }
        if (action === 'rules') {
            return '¿Devolver ' + total + ' productos/variaciones a las reglas?\n\nSe quita la marca "manual" y se aplica el precio de las reglas. Queda en el historial para revertir.';
        }
        return '¿Recalcular el precio rebajado de ' + total + ' productos/variaciones según las reglas?\n\nLos que tienen precio manual no se tocan. Queda en el historial para revertir.';
    }

    // opts.scope / opts.filters fuerzan el alcance (p. ej. "Recalcular todos" desde Reglas).
    function dpv_execute(action, opts) {
        if (busy) {
            return;
        }
        opts = opts || {};
        ui = opts.ui || '#dpv';
        var percent = dpv_percent();
        var scope = opts.scope || $('input[name="dpv-scope"]:checked').val();
        var filters = opts.filters || $('#dpv-actionbar').data('filters');

        if (action === 'apply' && !(percent > 0 && percent < 100)) {
            dpv_message('Escribe un porcentaje mayor que 0 y menor que 100.', 'error');
            $('#dpv-percent').trigger('focus');
            return;
        }
        if (scope === 'selected' && !dpv_selected_ids().length) {
            dpv_message('Selecciona al menos un producto o variación en la tabla, o elige "Todos los filtrados".', 'error');
            return;
        }

        dpv_message('', '');
        dpv_set_busy(true);

        dpv_post('resolve', { scope: scope, ids: scope === 'selected' ? dpv_selected_ids() : [], filters: filters }).then(function (data) {
            if (!data.total) {
                return $.Deferred().reject('No hay productos con precio para procesar.').promise();
            }
            if (!window.confirm(dpv_question(action, percent, data.total))) {
                return $.Deferred().reject(null).promise();
            }
            return dpv_post('start', { dpv_action: action, percent: percent, scope: scope, ids: data.ids, filters: filters });
        }).then(function (start) {
            return dpv_run(start.op, start.ids, start.batch, LABELS[action]);
        }).then(function (summary) {
            dpv_message('Listo: ' + summary.processed + ' modificados' + (summary.skipped ? ', ' + summary.skipped + ' omitidos (ver Historial)' : '') + '. Recargando…', 'ok');
            window.setTimeout(function () { window.location.reload(); }, 1200);
        }, function (error) {
            dpv_set_busy(false);
            $(ui + '-progress').prop('hidden', true);
            if (error) {
                dpv_message(error, 'error');
            }
        });
    }

    $('#dpv-apply').on('click', function () { dpv_execute('apply'); });
    $('#dpv-remove').on('click', function () { dpv_execute('remove'); });
    $('#dpv-rules').on('click', function () { dpv_execute('rules'); });
    $('#dpv-sync-all').on('click', function () {
        dpv_execute('sync', { scope: 'filtered', filters: {}, ui: '#dpv-sync' });
    });

    $page.on('click', '.dpv-revert', function () {
        var $btn = $(this);
        if (busy || !window.confirm('¿Revertir esta operación? ' + $btn.data('count') + ' productos vuelven al precio rebajado que tenían antes.')) {
            return;
        }
        dpv_set_busy(true);
        $btn.after('<span class="dpv-inline-progress"> Revirtiendo…</span>');
        dpv_post('revert_start', { op: $btn.data('op') }).then(function (start) {
            return dpv_run(start.op, start.ids, start.batch, 'Revirtiendo');
        }).then(function () {
            window.location.hash = 'tag-historial';
            window.location.reload();
        }, function (error) {
            dpv_set_busy(false);
            $btn.next('.dpv-inline-progress').remove();
            window.alert(error || 'No se pudo revertir.');
        });
    });

    dpv_update_count();
});
