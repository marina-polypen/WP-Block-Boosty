<?php
/**
 * Класс настроек — дефолтные значения, получение/сохранение единого массива опций.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBB_Settings {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Кеш смёрдженных настроек на время запроса. */
	private static ?array $cache = null;

	private function __construct() {
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		// Сброс кеша настроек и кеша динамического CSS при сохранении.
		add_action( 'update_option_' . WPBB_OPTION_KEY, [ __CLASS__, 'flush_cache' ] );
		add_action( 'add_option_' . WPBB_OPTION_KEY, [ __CLASS__, 'flush_cache' ] );
	}

	/**
	 * Сбросить in-memory кеш и transient динамического CSS.
	 */
	public static function flush_cache(): void {
		self::$cache = null;
		delete_transient( 'wpbb_dynamic_css' );
	}

	/**
	 * Регистрируем единую опцию с санитайзинг-обратным вызовом.
	 */
	public function register_setting(): void {
		register_setting( 'wpbb_settings_group', WPBB_OPTION_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_options' ],
			'default'           => self::defaults(),
		] );
	}

	/**
	 * Все дефолтные значения. Единственный источник истины.
	 */
	public static function defaults(): array {
		return [
			/* ── Global ──────────────────────────────── */
			'global_enabled'          => 1,
			'cascade_primary'         => 1,
			'css_prefix'              => 'be',
			'primary_color'           => '#2b5a9e',
			'secondary_color'        => '#4B9C52',
			'border_radius'           => '12',
			'border_width'            => '0',
			'border_width_hover'      => '0',
			'shadow'                  => '0 4px 24px rgba(0,0,0,0.08)',
			'shadow_hover'            => '0 8px 32px rgba(0,0,0,0.14)',
			'transition_speed'        => '0.3',
			'display_on_pages'        => 1,
			'display_on_posts'        => 1,
			'display_ids'             => '',

			/* ── #1 Blockquotes ──────────────────────── */
			'bq_enabled'              => 1,
			'bq_author_name'          => '',
			'bq_author_role'          => '',
			'bq_author_photo'         => '',
			'bq_layout'               => 'left',
			'bq_show_quotes'          => 1,
			'bq_one_line'             => 0,
			'bq_bg_color'             => '#ffffff',
			'bq_icon_color'           => '#4B9C52',
			'bq_quote_color'          => '#f0f0f0',
			'bq_font_size'            => '1.3',
			'bq_photo_size'           => '64',
			'bq_border_radius'        => '16',
			'bq_shadow_color'         => 'rgba(0,0,0,0.05)',
			'bq_shadow_blur'          => '30',
			'bq_max_width'            => '100%',

			/* ── #2 Ordered Lists ────────────────────── */
			'ol_enabled'              => 1,
			'ol_marker_color'         => '#ffffff',
			'ol_marker_bg'            => '#2b5a9e',
			'ol_marker_bg_hover'      => '#1e4278',
			'ol_li_bg'                => '#f8f9fb',
			'ol_li_bg_hover'          => '#f0f2f6',
			'ol_li_shadow'            => '0 2px 8px rgba(0,0,0,0.04)',
			'ol_max_width'            => '100%',
			'ol_font_size'            => '1',
			'ol_border_radius'        => '12',

			/* ── #3 Unordered Lists ──────────────────── */
			'ul_enabled'              => 1,
			'ul_li_bg'                => '#1e3a5f',
			'ul_li_bg_hover'          => '#24466f',
			'ul_text_color'           => '#ffffff',
			'ul_font_size'            => '1',
			'ul_li_shadow'            => '0 4px 16px rgba(0,0,0,0.08)',
			'ul_max_width'            => '100%',
			'ul_card_max_width'       => '480',
			'ul_border_radius'        => '12',
			'ul_show_numbers'         => 0,
			'ul_numbers_size'         => '32',
			'ul_numbers_color'        => 'rgba(0,0,0,0.1)',
			'ul_numbers_opacity'      => '0.2',
			'ul_numbers_weight'       => '700',
			'ul_default_skin'         => 'cards',

			/* ── #4 Tables ───────────────────────────── */
			'table_enabled'           => 1,
			'table_header_bg'         => '#2b5a9e',
			'table_header_color'      => '#ffffff',
			'table_row_color1'        => '#ffffff',
			'table_row_color2'        => '#f4f7fb',
			'table_row_hover'         => '#eaf2ff',
			'table_border_width'      => '0',
			'table_border_color'      => '#e1e8f0',
			'table_border_radius'     => '12',
			'table_max_width'         => '100%',
			'table_font_size'         => '1',
			'table_shadow'            => '0 4px 24px rgba(0,0,0,0.08)',
			'table_shadow_hover'      => '0 8px 32px rgba(0,0,0,0.14)',

			/* ── #5 Lead Paragraph ───────────────────── */
			'lead_enabled'            => 1,
			'lead_hero_skin'          => 'none',
			'lead_font_size'          => '1.25',
			'lead_max_width'          => '100%',
			'lead_bg_color'           => 'transparent',
			'lead_padding_top'        => '0',
			'lead_padding_bottom'     => '0',
			'lead_padding_left'       => '0',
			'lead_padding_right'      => '0',
			'lead_drop_cap'           => 0,
			'lead_drop_cap_size'      => '3.5',
			'lead_drop_cap_color'     => '#2b5a9e',
			'lead_border_radius'      => '0',
			'lead_shadow'             => 'none',

			/* ── #6 Heading Decor ────────────────────── */
			'hdecor_enabled'          => 1,
			'hdecor_tags'             => 'h2,h3',
			'hdecor_shape'            => 'line',
			'hdecor_position'         => 'left',
			'hdecor_size'             => '4',
			'hdecor_line_length'      => '40',
			'hdecor_color'            => '#4B9C52',
			'hdecor_icon'             => '',
			'hdecor_icon_size'         => '32',
			'hdecor_icon_color'        => '',
			'hdecor_icon_bg'          => '#eaf5eb',
			'hdecor_border_radius'    => '100',
			'hdecor_highlight_words'  => '0',
			'hdecor_highlight_color'  => '#4B9C52',

			/* ── #7 Images (Figure) ──────────────────── */
			'img_enabled'             => 1,
			'img_border_radius'       => '12',
			'img_shadow'              => '0 4px 20px rgba(0,0,0,0.08)',
			'img_shadow_hover'        => '0 8px 30px rgba(0,0,0,0.15)',
			'img_border_width'        => '0',
			'img_margin_top'          => '1.5',
			'img_margin_bottom'       => '1.5',

			/* ── #8 Galleries (Carousel) ─────────────── */
			'gallery_enabled'         => 1,
			'gallery_cols_desktop'    => '3',
			'gallery_cols_mobile'     => '1',
			'gallery_show_arrows'     => 1,
			'gallery_show_dots'       => 1,
			'gallery_border_radius'   => '12',
			'gallery_shadow'          => '0 4px 20px rgba(0,0,0,0.08)',
			'gallery_shadow_hover'    => '0 8px 30px rgba(0,0,0,0.15)',

			/* ── #9 Latest Posts ──────────────────────── */
			'latest_enabled'          => 1,
			'latest_border_radius'    => '16',
			'latest_shadow'           => '0 4px 20px rgba(0,0,0,0.08)',
			'latest_shadow_hover'     => '0 12px 36px rgba(0,0,0,0.16)',
			'latest_hover_effect'     => 'lift',

			/* ── #10 Alternating Sections ────────────── */
			'alt_enabled'             => 1,
			'alt_odd_bg'              => '#ffffff',
			'alt_odd_color'           => '#1a1a2e',
			'alt_even_bg'             => '#f4f7fb',
			'alt_even_color'          => '#1a1a2e',
			'alt_padding_top'         => '3',
			'alt_padding_bottom'      => '3',
			'alt_layout'              => 'boxed',
			'alt_border_radius'       => '12',

			/* ── #11 Pros / Cons Columns ─────────────── */
			'pc_enabled'              => 1,
			'pc_pros_bg'              => '#eaf5eb',
			'pc_cons_bg'              => '#fdeaea',
			'pc_pros_icon_color'      => '#1f2937',
			'pc_cons_icon_color'      => '#1f2937',
			'pc_title_color'          => '#1f2937',
			'pc_pros_title_bg'        => '#4B9C52',
			'pc_cons_title_bg'        => '#d14b4b',
			'pc_pros_icon'            => 'check',
			'pc_cons_icon'            => 'cross',
			'pc_decor_enabled'        => 1,
			'pc_pros_decor_type'      => 'thumb-up',
			'pc_cons_decor_type'      => 'thumb-down',
			'pc_pros_decor_color'     => '#4B9C52',
			'pc_cons_decor_color'     => '#d14b4b',
			'pc_decor_opacity'        => '0.10',
			'pc_decor_size'           => '160',
			'pc_default_skin'         => 'soft',
		];
	}

	/**
	 * Получить все настройки (merged с defaults).
	 */
	public static function get_all(): array {
		if ( self::$cache !== null ) {
			return self::$cache;
		}
		$saved = get_option( WPBB_OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		self::$cache = wp_parse_args( $saved, self::defaults() );
		return self::$cache;
	}

	/**
	 * Получить конкретное значение.
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$opts = self::get_all();
		return $opts[ $key ] ?? $fallback;
	}

	/**
	 * Санитайзинг при сохранении.
	 */
	public function sanitize_options( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}

		$clean    = [];
		$defaults = self::defaults();
		$enum_map = [
			'bq_layout'           => [ 'left', 'center', 'right' ],
			'hdecor_shape'        => [ 'line', 'square', 'icon' ],
			'hdecor_position'     => [ 'left', 'bottom', 'icon' ],
			'ul_numbers_weight'   => [ '400', '600', '700', '900' ],
			'ul_default_skin'     => [ 'cards', 'glass', 'outline' ],
			'lead_hero_skin'      => [ 'none', 'aurora', 'glass', 'editorial' ],
			'latest_hover_effect' => [ 'lift', 'scale' ],
			'alt_layout'          => [ 'boxed', 'full' ],
			'pc_pros_icon'        => [ 'check', 'plus' ],
			'pc_cons_icon'        => [ 'cross', 'minus' ],
			'pc_pros_decor_type'  => [ 'thumb-up', 'check', 'plus', 'star', 'spark' ],
			'pc_cons_decor_type'  => [ 'thumb-down', 'cross', 'minus', 'alert', 'ban' ],
			'pc_default_skin'     => [ 'soft', 'goodbad', 'editorial', 'minimal' ],
		];

		$color_keys = [
			'primary_color', 'secondary_color',
			'bq_bg_color', 'bq_icon_color', 'bq_quote_color', 'bq_shadow_color',
			'ol_marker_color', 'ol_marker_bg', 'ol_marker_bg_hover', 'ol_li_bg', 'ol_li_bg_hover',
			'ul_li_bg', 'ul_li_bg_hover', 'ul_text_color', 'ul_numbers_color',
			'table_row_hover',
			'table_header_bg', 'table_header_color', 'table_row_color1', 'table_row_color2', 'table_border_color',
			'lead_bg_color', 'lead_drop_cap_color',
			'hdecor_color', 'hdecor_icon_color', 'hdecor_icon_bg', 'hdecor_highlight_color',
			'alt_odd_bg', 'alt_odd_color', 'alt_even_bg', 'alt_even_color',
			'pc_pros_bg', 'pc_cons_bg', 'pc_pros_icon_color', 'pc_cons_icon_color', 'pc_title_color',
			'pc_pros_title_bg', 'pc_cons_title_bg', 'pc_pros_decor_color', 'pc_cons_decor_color',
		];

		$numeric_keys = [
			'border_radius', 'border_width', 'border_width_hover', 'transition_speed',
			'bq_font_size', 'bq_photo_size', 'bq_border_radius', 'bq_shadow_blur',
			'ol_font_size', 'ol_border_radius',
			'ul_font_size', 'ul_card_max_width', 'ul_border_radius', 'ul_numbers_size', 'ul_numbers_opacity',
			'table_border_width', 'table_border_radius', 'table_font_size',
			'lead_font_size', 'lead_padding_top', 'lead_padding_bottom', 'lead_padding_left', 'lead_padding_right', 'lead_drop_cap_size', 'lead_border_radius',
			'hdecor_size', 'hdecor_line_length', 'hdecor_icon_size', 'hdecor_border_radius', 'hdecor_highlight_words',
			'img_border_radius', 'img_border_width', 'img_margin_top', 'img_margin_bottom',
			'gallery_cols_desktop', 'gallery_cols_mobile', 'gallery_border_radius',
			'latest_border_radius',
			'alt_padding_top', 'alt_padding_bottom', 'alt_border_radius',
			'pc_decor_opacity', 'pc_decor_size',
		];

		$free_css_keys = [
			'shadow', 'shadow_hover',
			'ol_li_shadow', 'ul_li_shadow',
			'table_shadow', 'table_shadow_hover',
			'lead_shadow',
			'img_shadow', 'img_shadow_hover',
			'gallery_shadow', 'gallery_shadow_hover',
			'latest_shadow', 'latest_shadow_hover',
		];

		foreach ( $defaults as $key => $default_value ) {
			if ( ! isset( $input[ $key ] ) ) {
				// Для чекбоксов: если нет в POST — ставим 0.
				if ( is_int( $default_value ) && in_array( $default_value, [ 0, 1 ], true ) ) {
					$clean[ $key ] = 0;
				} else {
					$clean[ $key ] = $default_value;
				}
				continue;
			}

			$val = $input[ $key ];

			// Булевые/целочисленные поля.
			if ( is_int( $default_value ) ) {
				$clean[ $key ] = absint( $val );
				continue;
			}

			$raw = is_string( $val ) ? wp_unslash( $val ) : (string) $val;

			if ( isset( $enum_map[ $key ] ) ) {
				$clean[ $key ] = in_array( $raw, $enum_map[ $key ], true ) ? $raw : (string) $default_value;
				continue;
			}

			if ( in_array( $key, $color_keys, true ) ) {
				$clean[ $key ] = $this->sanitize_color_value( $raw, (string) $default_value );
				continue;
			}

			if ( in_array( $key, $numeric_keys, true ) ) {
				$clean[ $key ] = $this->sanitize_numeric_string( $raw, (string) $default_value );
				continue;
			}

			if ( in_array( $key, $free_css_keys, true ) ) {
				$clean[ $key ] = sanitize_text_field( $raw );
				continue;
			}

			if ( $key === 'bq_author_photo' ) {
				$clean[ $key ] = esc_url_raw( $raw );
				continue;
			}

			if ( $key === 'css_prefix' ) {
				$prefix = sanitize_html_class( $raw );
				$clean[ $key ] = $prefix !== '' ? $prefix : (string) $default_value;
				continue;
			}

			if ( $key === 'display_ids' ) {
				$parts = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
				$clean[ $key ] = implode( ',', array_filter( $parts ) );
				continue;
			}

			if ( $key === 'hdecor_tags' ) {
				$tags = array_map( 'trim', explode( ',', strtolower( $raw ) ) );
				$tags = array_filter( $tags, static function( $tag ) {
					return preg_match( '/^h[1-6]$/', $tag ) === 1;
				} );
				$clean[ $key ] = ! empty( $tags ) ? implode( ',', $tags ) : (string) $default_value;
				continue;
			}

			if ( $key === 'hdecor_icon' ) {
				$clean[ $key ] = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $raw ) );
				continue;
			}

			$clean[ $key ] = sanitize_text_field( $raw );
		}

		return $clean;
	}

	private function sanitize_color_value( string $raw, string $fallback ): string {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return $fallback;
		}

		$hex = sanitize_hex_color( $raw );
		if ( $hex ) {
			return $hex;
		}

		if ( in_array( strtolower( $raw ), [ 'transparent', 'currentcolor' ], true ) ) {
			return $raw;
		}

		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(\s*,\s*(0|0?\.\d+|1(\.0)?))?\s*\)$/i', $raw ) ) {
			return $raw;
		}

		return $fallback;
	}

	private function sanitize_numeric_string( string $raw, string $fallback ): string {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return $fallback;
		}
		if ( is_numeric( $raw ) ) {
			return (string) $raw;
		}
		return $fallback;
	}
}
