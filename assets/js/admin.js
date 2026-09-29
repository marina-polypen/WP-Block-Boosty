/**
 * WP Block Boosty – Admin JS
 * Tabs, Color Picker, Media Upload.
 */
jQuery(document).ready(function ($) {
    'use strict';
    var optionKey = 'wp_block_boosty_options';

    function fieldSelector(name) {
        return '[name="' + optionKey + '[' + name + ']"]';
    }

    function getValue(name, fallback) {
        var $el = $(fieldSelector(name));
        if (!$el.length) return fallback;
        return ($el.val() || fallback);
    }

    function refreshHeadingPreview() {
        var $preview = $('#wpbb-heading-preview');
        if (!$preview.length) return;

        var shape = getValue('hdecor_shape', 'line');
        var decorColor = getValue('hdecor_color', '#4B9C52');
        var iconColor = getValue('hdecor_icon_color', '#ffffff');
        var iconBg = getValue('hdecor_icon_bg', '#4B9C52');
        var size = parseInt(getValue('hdecor_size', '4'), 10) || 4;
        var lineLength = parseInt(getValue('hdecor_line_length', '40'), 10) || 40;

        var $heading = $preview.find('.wpbb-preview-heading');
        $heading.removeClass('shape-line shape-square shape-icon').addClass('shape-' + shape);
        $heading.css('--wpbb-preview-decor-color', decorColor);
        $heading.css('--wpbb-preview-decor-size', size + 'px');
        $heading.css('--wpbb-preview-line-length', lineLength + 'px');
        $preview.find('.wpbb-preview-icon').css({ color: iconColor, background: iconBg });
    }

    function refreshTablePreview() {
        var $preview = $('#wpbb-table-preview');
        if (!$preview.length) return;
        $preview.css('--wpbb-table-row-hover', getValue('table_row_hover', '#eaf2ff'));
        $preview.find('thead th').css({
            background: getValue('table_header_bg', '#2b5a9e'),
            color: getValue('table_header_color', '#ffffff')
        });
        $preview.find('tbody tr').eq(0).find('td').css('background', getValue('table_row_color1', '#ffffff'));
        $preview.find('tbody tr').eq(1).find('td').css('background', getValue('table_row_color2', '#f4f7fb'));
    }

    function refreshProsConsPreview() {
        var $preview = $('#wpbb-proscons-preview');
        if (!$preview.length) return;
        $preview.find('.wpbb-preview-pros').css('background', getValue('pc_pros_bg', '#eaf5eb'));
        $preview.find('.wpbb-preview-cons').css('background', getValue('pc_cons_bg', '#fdeaea'));
        $preview.find('.wpbb-preview-pros .wpbb-preview-title-pill').css({
            background: getValue('pc_pros_title_bg', '#4B9C52'),
            color: '#fff'
        });
        $preview.find('.wpbb-preview-cons .wpbb-preview-title-pill').css({
            background: getValue('pc_cons_title_bg', '#d14b4b'),
            color: '#fff'
        });
        $preview.find('.wpbb-preview-pros .wpbb-preview-item-icon').css('color', getValue('pc_pros_icon_color', '#1f2937'));
        $preview.find('.wpbb-preview-cons .wpbb-preview-item-icon').css('color', getValue('pc_cons_icon_color', '#1f2937'));
    }

    function refreshPreviews() {
        refreshHeadingPreview();
        refreshTablePreview();
        refreshProsConsPreview();
    }

    /* ── Color Pickers ─────────────────────────── */
    $('.wpbb-color-picker').wpColorPicker({
        change: function () {
            refreshPreviews();
        },
        clear: function () {
            refreshPreviews();
        }
    });

    /* ── Tab Switching ─────────────────────────── */
    $('.wpbb-tab-btn').on('click', function () {
        var tab = $(this).data('tab');

        // Deactivate all.
        $('.wpbb-tab-btn').removeClass('active');
        $('.wpbb-tab-panel').removeClass('active');

        // Activate clicked.
        $(this).addClass('active');
        $('#wpbb-tab-' + tab).addClass('active');

        // Persist in localStorage.
        try {
            localStorage.setItem('wpbb_active_tab', tab);
        } catch (e) { /* silent */ }
    });

    // Restore last tab.
    try {
        var savedTab = localStorage.getItem('wpbb_active_tab');
        if (savedTab) {
            var $btn = $('.wpbb-tab-btn[data-tab="' + savedTab + '"]');
            if ($btn.length) {
                $btn.trigger('click');
            }
        }
    } catch (e) { /* silent */ }

    /* ── Media Upload (Photo) ──────────────────── */
    var mediaUploader = {};

    $(document).on('click', '.wpbb-upload-btn', function (e) {
        e.preventDefault();
        var targetId = $(this).data('target');

        if (mediaUploader[targetId]) {
            mediaUploader[targetId].open();
            return;
        }

        mediaUploader[targetId] = wp.media({
            title: 'Choose Photo',
            button: { text: 'Use this photo' },
            multiple: false
        });

        mediaUploader[targetId].on('select', function () {
            var attachment = mediaUploader[targetId].state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.url);
            $('#' + targetId + '_preview').html('<img src="' + attachment.url + '">');
            $(document).find('.wpbb-remove-btn[data-target="' + targetId + '"]').show();
        });

        mediaUploader[targetId].open();
    });

    $(document).on('click', '.wpbb-remove-btn', function (e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
        $('#' + targetId + '_preview').empty();
        $(this).hide();
    });

    /* ── Conditional Fields ────────────────────── */
    function toggleHDecorFields() {
        var shape = $(fieldSelector('hdecor_shape')).val();
        var $lineLength = $(fieldSelector('hdecor_line_length')).closest('.wpbb-field');
        var $iconName = $(fieldSelector('hdecor_icon')).closest('.wpbb-field');
        var $iconBg = $(fieldSelector('hdecor_icon_bg')).closest('.wpbb-field');
        var $iconColor = $(fieldSelector('hdecor_icon_color')).closest('.wpbb-field');
        var $iconSize = $(fieldSelector('hdecor_icon_size')).closest('.wpbb-field');
        var $iconRadius = $(fieldSelector('hdecor_border_radius')).closest('.wpbb-field');
        var $position = $(fieldSelector('hdecor_position')).closest('.wpbb-field');

        if (shape === 'line') {
            $lineLength.show();
            $iconName.hide();
            $iconBg.hide();
            $iconColor.hide();
            $iconSize.hide();
            $iconRadius.hide();
            $position.show();
        } else if (shape === 'icon') {
            $lineLength.hide();
            $iconName.show();
            $iconBg.show();
            $iconColor.show();
            $iconSize.show();
            $iconRadius.show();
            $position.hide();
        } else {
            $lineLength.hide();
            $iconName.hide();
            $iconBg.hide();
            $iconColor.hide();
            $iconSize.hide();
            $iconRadius.hide();
            $position.show();
        }
    }

    $(document).on('change input', '.wpbb-settings-form input, .wpbb-settings-form select', function () {
        toggleHDecorFields();
        refreshPreviews();
    });

    toggleHDecorFields();
    refreshPreviews();
});
