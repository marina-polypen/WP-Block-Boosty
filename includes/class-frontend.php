<?php
/**
 * Фронтенд — обработка контента через the_content, динамические стили, подключение ассетов.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBB_Frontend {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_head', [ $this, 'inject_dynamic_styles' ], 99 );
		add_filter( 'the_content', [ $this, 'process_content' ], 20 );
	}

	/* ─── SHOULD APPLY ──────────────────────────────────────── */

	private function should_apply(): bool {
		if ( is_admin() ) {
			return false;
		}
		$opts = WPBB_Settings::get_all();
		if ( empty( $opts['global_enabled'] ) ) {
			return false;
		}

		// Display rules.
		if ( is_page() && empty( $opts['display_on_pages'] ) ) {
			return false;
		}
		if ( is_single() && empty( $opts['display_on_posts'] ) ) {
			return false;
		}

		// Specific IDs.
		$ids_raw = trim( $opts['display_ids'] ?? '' );
		if ( $ids_raw !== '' ) {
			$ids = array_map( 'absint', array_filter( explode( ',', $ids_raw ) ) );
			if ( ! empty( $ids ) && ! in_array( get_the_ID(), $ids, true ) ) {
				return false;
			}
		}

		return true;
	}

	/* ─── ASSETS ────────────────────────────────────────────── */

	public function enqueue_assets(): void {
		if ( ! $this->should_apply() ) {
			return;
		}
		wp_enqueue_style(
			'wpbb-frontend',
			WPBB_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			WPBB_VERSION
		);
		wp_enqueue_script(
			'wpbb-frontend',
			WPBB_PLUGIN_URL . 'assets/js/frontend.js',
			[],
			WPBB_VERSION,
			true
		);

		$opts = WPBB_Settings::get_all();

		// Dashicons на фронте для heading icons.
		if ( ! empty( $opts['hdecor_enabled'] ) && $opts['hdecor_shape'] === 'icon' ) {
			wp_enqueue_style( 'dashicons' );
		}

		// Передаём настройки в JS.
		wp_localize_script( 'wpbb-frontend', 'wpbbSettings', [
			'prefix'          => sanitize_html_class( $opts['css_prefix'] ),
			'galleryEnabled'  => (int) $opts['gallery_enabled'],
			'galleryCols'     => (int) $opts['gallery_cols_desktop'],
			'galleryColsMob'  => (int) $opts['gallery_cols_mobile'],
			'galleryArrows'   => (int) $opts['gallery_show_arrows'],
			'galleryDots'     => (int) $opts['gallery_show_dots'],
			'altEnabled'      => (int) $opts['alt_enabled'],
		] );
	}

	/* ─── DYNAMIC STYLES ────────────────────────────────────── */

	public function inject_dynamic_styles(): void {
		if ( ! $this->should_apply() ) {
			return;
		}

		$cache_key = 'wpbb_dynamic_css';
		$css = get_transient( $cache_key );
		if ( ! is_string( $css ) || $css === '' ) {
			$css = $this->build_dynamic_css();
			set_transient( $cache_key, $css, DAY_IN_SECONDS );
		}

		if ( $css ) {
			echo '<style id="wpbb-dynamic-styles">' . $css . '</style>';
		}
	}

	/**
	 * Собрать динамический CSS из настроек.
	 */
	private function build_dynamic_css(): string {
		$o  = WPBB_Settings::get_all();
		$p  = sanitize_html_class( $o['css_prefix'] );
		$tr = floatval( $o['transition_speed'] ) . 's';

		// Helper: добавить px если числовое.
		$px = static function ( $v ) {
			if ( $v === '' || $v === null ) return $v;
			return is_numeric( $v ) ? $v . 'px' : $v;
		};
		$rem = static function ( $v ) {
			if ( $v === '' || $v === null ) return $v;
			return is_numeric( $v ) ? $v . 'rem' : $v;
		};

		$css = '';

		// Cascade Primary Color logic.
		$defaults   = WPBB_Settings::defaults();
		$primary    = $o['primary_color'];
		$cascade_on = ! empty( $o['cascade_primary'] );

		$get_with_cascade = function( $key ) use ( $o, $defaults, $primary, $cascade_on ) {
			$val = $o[ $key ] ?? '';
			if ( empty( $val ) ) {
				return $primary;
			}
			// Каскад primary активен — подменяем только «нетронутые» дефолтные значения секций.
			if ( $cascade_on && $val === $defaults[ $key ] ) {
				return $primary;
			}
			return $val;
		};

		$olMarkerBg = $get_with_cascade( 'ol_marker_bg' );
		$ulLiBg     = $get_with_cascade( 'ul_li_bg' );
		$tableHBg   = $get_with_cascade( 'table_header_bg' );
		$leadBg     = $get_with_cascade( 'lead_bg_color' );
		$hDecorC    = $get_with_cascade( 'hdecor_color' );
		$hHighC     = $get_with_cascade( 'hdecor_highlight_color' );

		/* ── CSS Variables ─────────────────────────── */
		$css .= ":root{";
		$css .= "--{$p}-primary:{$primary};";
		$css .= "--{$p}-secondary:{$o['secondary_color']};";
		$css .= "--{$p}-radius:{$px($o['border_radius'])};";
		$css .= "--{$p}-border-w:{$px($o['border_width'])};";
		$css .= "--{$p}-border-w-hover:{$px($o['border_width_hover'])};";
		$css .= "--{$p}-shadow:{$o['shadow']};";
		$css .= "--{$p}-shadow-hover:{$o['shadow_hover']};";
		$css .= "--{$p}-transition:{$tr};";
		// Текстовые токены — можно переопределить темой (в т.ч. для тёмного режима).
		$css .= "--{$p}-text:#3a3a4a;";
		$css .= "--{$p}-heading:#1a1a2e;";
		$css .= "--{$p}-muted:#6b7280;";
		$css .= "}";

		/* ── #1 Blockquotes ────────────────────────── */
		if ( ! empty( $o['bq_enabled'] ) ) {
			$bqR   = $px( $o['bq_border_radius'] );
			$bqFS  = $rem( $o['bq_font_size'] );
			$bqPS  = $px( $o['bq_photo_size'] );
			$bqMW  = $px( $o['bq_max_width'] );
			$bqSB  = $px( $o['bq_shadow_blur'] );

			$css .= "
			.{$p}-bq-block{background:{$o['bq_bg_color']};border-radius:{$bqR};box-shadow:0 10px {$bqSB} {$o['bq_shadow_color']};max-width:{$bqMW};margin:2rem auto;padding:2rem;border:none!important;border-left:none!important;display:flex;flex-direction:column;position:relative;overflow:hidden;transition:all {$tr};}
			.{$p}-bq-block p{font-size:{$bqFS};font-style:italic;position:relative;z-index:3;color:#2c3e50;margin:0;line-height:1.6;}
			.{$p}-bq-photo{width:{$bqPS};height:{$bqPS};border-radius:50%;overflow:hidden;flex-shrink:0;border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
			.{$p}-bq-photo img{width:100%;height:100%;object-fit:cover;}
			.{$p}-bq-meta{display:flex;align-items:center;gap:16px;margin-bottom:20px;font-style:normal;position:relative;z-index:2;}
			.{$p}-bq-badge{display:inline-flex;width:20px;height:20px;}
			.{$p}-bq-badge svg{width:100%;height:100%;fill:{$o['bq_icon_color']};}
			.{$p}-bq-details{display:flex;align-items:center;gap:8px;}
			.{$p}-bq-info{display:flex;flex-direction:column;}
			.{$p}-bq-name{font-weight:700;font-size:1.15rem;color:var(--{$p}-heading);}
			.{$p}-bq-role{font-size:0.95rem;color:#555;font-weight:500;}
			.{$p}-bq-info-oneline{flex-direction:row!important;align-items:center;gap:6px;}
			.{$p}-bq-info-oneline .{$p}-bq-name{white-space:nowrap;}
			.{$p}-bq-info-oneline .{$p}-bq-role::before{content:'–';margin-right:6px;color:#888;}
			.{$p}-bq-quote-icon{position:absolute;top:10px;right:20px;width:100px;height:100px;color:{$o['bq_quote_color']};opacity:0.8;pointer-events:none;z-index:1;}
			.{$p}-bq-quote-icon svg{width:100%;height:100%;}
			.{$p}-bq-layout-center{text-align:center;}
			.{$p}-bq-layout-center .{$p}-bq-meta{flex-direction:column;align-items:center;}
			.{$p}-bq-layout-center .{$p}-bq-details{flex-direction:column;}
			.{$p}-bq-layout-right .{$p}-bq-meta{flex-direction:row-reverse;}
			@media(max-width:640px){.{$p}-bq-block{padding:1.5rem;max-width:100%!important;}.{$p}-bq-meta{flex-direction:column;text-align:center;}.{$p}-bq-info-oneline{flex-direction:column!important;gap:2px;}.{$p}-bq-info-oneline .{$p}-bq-role::before{display:none;}.{$p}-bq-block p{font-size:1.1rem;}}
			";
		}

		/* ── #2 Ordered Lists ──────────────────────── */
		if ( ! empty( $o['ol_enabled'] ) ) {
			$olR  = $px( $o['ol_border_radius'] );
			$olFS = $rem( $o['ol_font_size'] );
			$olMW = $px( $o['ol_max_width'] );

			$css .= "
			.{$p}-ol{list-style:none;counter-reset:{$p}-ol-counter;padding:0;margin:2rem auto;max-width:{$olMW};}
			.{$p}-ol li{counter-increment:{$p}-ol-counter;display:flex;align-items:flex-start;gap:16px;padding:18px 24px;margin-bottom:12px;background:{$o['ol_li_bg']};border-radius:{$olR};box-shadow:{$o['ol_li_shadow']};font-size:{$olFS};line-height:1.6;transition:all {$tr};border:1px solid transparent;}
			.{$p}-ol li:hover{background:{$o['ol_li_bg_hover']};transform:translateX(4px);}
			.{$p}-ol li::before{content:counter({$p}-ol-counter);display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;border-radius:50%;background:{$olMarkerBg};color:{$o['ol_marker_color']};font-weight:700;font-size:0.95rem;flex-shrink:0;transition:background {$tr};}
			.{$p}-ol li:hover::before{background:{$o['ol_marker_bg_hover']};}
			.{$p}-ol li strong{display:block;font-size:1.1em;font-weight:700;margin-bottom:4px;color:var(--{$p}-heading);}
			@media(max-width:640px){.{$p}-ol li{padding:14px 16px;gap:12px;}.{$p}-ol li::before{min-width:30px;height:30px;font-size:0.85rem;}}
			";
		}

		/* ── #3 Unordered Lists ────────────────────── */
		if ( ! empty( $o['ul_enabled'] ) ) {
			$ulR   = $px( $o['ul_border_radius'] );
			$ulFS  = $rem( $o['ul_font_size'] );
			$ulMW  = $px( $o['ul_max_width'] );
			$ulCMW = $px( $o['ul_card_max_width'] );

			$css .= "
			.{$p}-ul{list-style:none;padding:0;padding-left:0;margin:2rem auto;max-width:{$ulMW};display:grid;grid-template-columns:repeat(auto-fit,minmax(min({$ulCMW},100%),1fr));gap:20px;}
			.{$p}-ul li{position:relative;padding:28px 24px;background:{$ulLiBg};color:{$o['ul_text_color']};border-radius:{$ulR};box-shadow:{$o['ul_li_shadow']};font-size:{$ulFS};line-height:1.6;transition:all {$tr};overflow:hidden;}
			.{$p}-ul li:hover{background:{$o['ul_li_bg_hover']};transform:translateY(-2px);}
			.{$p}-ul li strong{display:block;font-size:1.15em;font-weight:700;margin-bottom:8px;}
			";

			$css .= "
			.{$p}-ul.{$p}-ul-skin-glass li{background:linear-gradient(135deg,rgba(255,255,255,0.22),rgba(255,255,255,0.08));backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);border:1px solid rgba(255,255,255,0.22);}
			.{$p}-ul.{$p}-ul-skin-glass li:hover{background:linear-gradient(135deg,rgba(255,255,255,0.3),rgba(255,255,255,0.12));transform:translateY(-3px);}
			.{$p}-ul.{$p}-ul-skin-outline li{background:transparent!important;border:2px solid {$ulLiBg};box-shadow:none;}
			.{$p}-ul.{$p}-ul-skin-outline li:hover{background:{$o['ul_li_bg_hover']};border-color:{$o['ul_li_bg_hover']};transform:translateY(-2px);}
			";

			if ( ! empty( $o['ul_show_numbers'] ) ) {
				$numbers_opacity = is_numeric( $o['ul_numbers_opacity'] ) ? (float) $o['ul_numbers_opacity'] : 0.2;
				$numbers_opacity = max( 0.0, min( 1.0, $numbers_opacity ) );
				$numbers_hover_opacity = max( 0.0, min( 1.0, $numbers_opacity + 0.2 ) );

				$css .= "
				.{$p}-ul li{counter-increment:{$p}-ul-counter;padding-top:60px;}
				.{$p}-ul{counter-reset:{$p}-ul-counter;}
				.{$p}-ul li::before{content:counter({$p}-ul-counter,decimal-leading-zero);position:absolute;top:16px;left:24px;font-size:{$px($o['ul_numbers_size'])};font-weight:{$o['ul_numbers_weight']};opacity:{$numbers_opacity};font-style:italic;color:{$o['ul_numbers_color']};line-height:1;transition:all {$tr};}
				.{$p}-ul li:hover::before{opacity:{$numbers_hover_opacity};transform:translateY(-2px);}
				";
			}

			$css .= "@media(max-width:640px){.{$p}-ul{grid-template-columns:1fr;}.{$p}-ul li{padding:22px 18px;}}";
		}

		/* ── #4 Tables ─────────────────────────────── */
		if ( ! empty( $o['table_enabled'] ) ) {
			$tR  = $px( $o['table_border_radius'] );
			$tFS = $rem( $o['table_font_size'] );
			$tMW = $px( $o['table_max_width'] );
			$tBW = $px( $o['table_border_width'] );

			$css .= "
			.{$p}-table-wrap{max-width:{$tMW};margin:2rem auto;border-radius:{$tR};overflow:hidden;box-shadow:{$o['table_shadow']};border:{$tBW} solid rgba(0,0,0,0.08);transition:box-shadow {$tr};}
			.{$p}-table-wrap:hover{box-shadow:{$o['table_shadow_hover']};}
			.{$p}-table-wrap table{width:100%;border-collapse:collapse;font-size:{$tFS};}
			.{$p}-table-wrap thead th,.{$p}-table-wrap thead td{background:{$tableHBg};color:{$o['table_header_color']};font-weight:700;text-transform:uppercase;font-size:0.85em;letter-spacing:0.05em;padding:14px 20px;text-align:left;border:none;}
			.{$p}-table-wrap tbody tr:nth-child(odd) td{background:{$o['table_row_color1']};}
			.{$p}-table-wrap tbody tr:nth-child(even) td{background:{$o['table_row_color2']};}
			.{$p}-table-wrap tbody tr:hover td{background:{$o['table_row_hover']};}
			.{$p}-table-wrap tbody td{padding:14px 20px;border-top:{$tBW} solid {$o['table_border_color']};border-right:{$tBW} solid {$o['table_border_color']};border-bottom:{$tBW} solid {$o['table_border_color']};border-left:{$tBW} solid {$o['table_border_color']};font-weight:500;}
			.{$p}-table-wrap tbody td:first-child{border-left:none;}
			.{$p}-table-wrap tbody td:last-child{border-right:none;}
			.{$p}-table-wrap tbody tr:last-child td{border-bottom:none;}
			@media(max-width:640px){.{$p}-table-wrap{border-radius:8px;overflow-x:auto;}.{$p}-table-wrap thead th,.{$p}-table-wrap thead td,.{$p}-table-wrap tbody td{padding:10px 14px;font-size:0.9em;}}
			";
		}

		/* ── #5 Lead Paragraph ─────────────────────── */
		if ( ! empty( $o['lead_enabled'] ) ) {
			$lFS = $rem( $o['lead_font_size'] );
			$lMW = $px( $o['lead_max_width'] );
			$lR  = $px( $o['lead_border_radius'] );

			$css .= "
			.{$p}-lead-p{font-size:{$lFS};line-height:1.7;color:var(--{$p}-text);font-weight:500;max-width:{$lMW};margin:0 auto 2rem;border-radius:{$lR};box-shadow:{$o['lead_shadow']};background:{$leadBg};padding-top:{$px($o['lead_padding_top'])};padding-bottom:{$px($o['lead_padding_bottom'])};padding-left:{$px($o['lead_padding_left'])};padding-right:{$px($o['lead_padding_right'])};position:relative;display:block;}
			";

			if ( ! empty( $o['lead_drop_cap'] ) ) {
				$dcSize  = $rem( $o['lead_drop_cap_size'] );
				$dcColor = $o['lead_drop_cap_color'];
				$css .= "
				.{$p}-lead-p:first-letter {
					float: left;
					font-size: {$dcSize};
					line-height: 1;
					margin-bottom: 0px;
					margin-right: 12px;
					font-weight: 800;
					color: {$dcColor};
					text-transform: uppercase;
					font-family: inherit;
				}
				.{$p}-lead-p::after { content: ''; display: table; clear: both; }
				";
			}

			// Hero Skins — full section styles
			$hero_skin = $o['lead_hero_skin'] ?? 'none';
			if ( $hero_skin !== 'none' ) {
				$css .= "
				@keyframes {$p}-hero-float {
					0%, 100% { transform: translate(0, 0) scale(1); }
					33% { transform: translate(30px, -20px) scale(1.05); }
					66% { transform: translate(-20px, 15px) scale(0.95); }
				}
				@keyframes {$p}-hero-pulse {
					0%, 100% { opacity: 0.6; }
					50% { opacity: 1; }
				}
				@keyframes {$p}-hero-shimmer {
					0% { background-position: -200% center; }
					100% { background-position: 200% center; }
				}

				/* ── Hero Wrapper (shared) ── */
				.{$p}-hero-wrap {
					position: relative;
					overflow: hidden;
					margin: 0 auto 2.5rem;
					max-width: 100%;
					border-radius: 28px;
					isolation: isolate;
				}
				.{$p}-hero-wrap .{$p}-lead-p {
					position: relative;
					z-index: 2;
					background: transparent !important;
					box-shadow: none !important;
					border: none !important;
					max-width: 720px;
					margin: 0 auto;
					text-align: center;
					font-size: 1.35rem;
					line-height: 1.75;
					font-weight: 500;
					letter-spacing: -0.01em;
				}
				.{$p}-hero-orb {
					position: absolute;
					border-radius: 50%;
					pointer-events: none;
					z-index: 0;
					will-change: transform;
				}
				.{$p}-hero-line {
					position: absolute;
					z-index: 1;
					pointer-events: none;
				}

				/* ── Aurora (Gradient Mesh + Animated Orbs) ── */
				.{$p}-hero-aurora {
					background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
					padding: 72px 48px 64px;
				}
				.{$p}-hero-aurora .{$p}-lead-p {
					color: rgba(255,255,255,0.92) !important;
					text-shadow: 0 1px 2px rgba(0,0,0,0.3);
					padding: 0 !important;
				}
				.{$p}-hero-aurora .{$p}-hero-orb:nth-child(1) {
					width: 280px; height: 280px;
					background: radial-gradient(circle, rgba(102,126,234,0.5) 0%, transparent 70%);
					top: -60px; left: -40px;
					filter: blur(40px);
					animation: {$p}-hero-float 8s ease-in-out infinite;
				}
				.{$p}-hero-aurora .{$p}-hero-orb:nth-child(2) {
					width: 220px; height: 220px;
					background: radial-gradient(circle, rgba(240,147,251,0.45) 0%, transparent 70%);
					bottom: -40px; right: -20px;
					filter: blur(35px);
					animation: {$p}-hero-float 10s ease-in-out infinite reverse;
				}
				.{$p}-hero-aurora .{$p}-hero-orb:nth-child(3) {
					width: 160px; height: 160px;
					background: radial-gradient(circle, rgba(245,87,108,0.35) 0%, transparent 70%);
					top: 40%; left: 55%;
					filter: blur(45px);
					animation: {$p}-hero-float 12s ease-in-out infinite 2s;
				}
				.{$p}-hero-aurora .{$p}-hero-line {
					top: 0; left: 0; right: 0; bottom: 0;
					background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.03) 50%, transparent 60%);
					background-size: 200% 200%;
					animation: {$p}-hero-shimmer 6s linear infinite;
				}
				.{$p}-hero-aurora::after {
					content: '';
					position: absolute;
					inset: 0;
					background: radial-gradient(ellipse at 50% 0%, rgba(102,126,234,0.15) 0%, transparent 60%);
					z-index: 1;
					pointer-events: none;
				}

				/* ── Glass (Glassmorphism) ── */
				.{$p}-hero-glass {
					background: linear-gradient(160deg, #f0f4ff 0%, #e8ecf8 40%, #f5f0ff 100%);
					padding: 68px 48px 60px;
					border: 1px solid rgba(255,255,255,0.7);
					box-shadow: 0 24px 64px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.03);
				}
				.{$p}-hero-glass .{$p}-lead-p {
					color: #1a1a2e !important;
					background: linear-gradient(145deg, rgba(255,255,255,0.72) 0%, rgba(255,255,255,0.48) 100%) !important;
					backdrop-filter: blur(20px) saturate(180%);
					-webkit-backdrop-filter: blur(20px) saturate(180%);
					border: 1px solid rgba(255,255,255,0.8) !important;
					border-radius: 22px !important;
					box-shadow: 0 16px 48px rgba(0,0,0,0.06), inset 0 1px 0 rgba(255,255,255,0.95) !important;
					padding: 40px 44px !important;
					font-weight: 500;
				}
				.{$p}-hero-glass .{$p}-hero-orb:nth-child(1) {
					width: 200px; height: 200px;
					background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, transparent 70%);
					top: -30px; right: 10%;
					filter: blur(50px);
					animation: {$p}-hero-pulse 5s ease-in-out infinite;
				}
				.{$p}-hero-glass .{$p}-hero-orb:nth-child(2) {
					width: 180px; height: 180px;
					background: radial-gradient(circle, rgba(168,85,247,0.15) 0%, transparent 70%);
					bottom: -20px; left: 5%;
					filter: blur(40px);
					animation: {$p}-hero-pulse 7s ease-in-out infinite 1s;
				}
				.{$p}-hero-glass .{$p}-hero-orb:nth-child(3) {
					width: 120px; height: 120px;
					background: radial-gradient(circle, rgba(236,72,153,0.12) 0%, transparent 70%);
					top: 50%; left: 60%;
					filter: blur(35px);
					animation: {$p}-hero-float 9s ease-in-out infinite;
				}
				.{$p}-hero-glass .{$p}-hero-line {
					bottom: 0; left: 0; right: 0; height: 1px;
					background: linear-gradient(90deg, transparent, rgba(99,102,241,0.2), transparent);
				}

				/* ── Editorial (Dark Premium) ── */
				.{$p}-hero-editorial {
					background: linear-gradient(160deg, #0a0a1a 0%, #111128 35%, #1a1a3e 65%, #2d1b69 100%);
					padding: 80px 48px 70px;
					border: 1px solid rgba(255,255,255,0.06);
					box-shadow: 0 32px 80px rgba(0,0,0,0.25);
				}
				.{$p}-hero-editorial .{$p}-lead-p {
					color: rgba(240,238,255,0.9) !important;
					font-size: 1.4rem;
					font-weight: 400;
					letter-spacing: 0.3px;
					line-height: 1.8;
					padding: 0 !important;
				}
				.{$p}-hero-editorial .{$p}-hero-orb:nth-child(1) {
					width: 300px; height: 300px;
					background: radial-gradient(circle, rgba(139,92,246,0.25) 0%, transparent 70%);
					top: -80px; right: -60px;
					filter: blur(60px);
					animation: {$p}-hero-float 10s ease-in-out infinite;
				}
				.{$p}-hero-editorial .{$p}-hero-orb:nth-child(2) {
					width: 200px; height: 200px;
					background: radial-gradient(circle, rgba(59,130,246,0.2) 0%, transparent 70%);
					bottom: -40px; left: -30px;
					filter: blur(50px);
					animation: {$p}-hero-float 12s ease-in-out infinite reverse;
				}
				.{$p}-hero-editorial .{$p}-hero-orb:nth-child(3) {
					width: 140px; height: 140px;
					background: radial-gradient(circle, rgba(236,72,153,0.15) 0%, transparent 70%);
					top: 60%; left: 45%;
					filter: blur(40px);
					animation: {$p}-hero-pulse 8s ease-in-out infinite 3s;
				}
				.{$p}-hero-editorial .{$p}-hero-line {
					bottom: 0; left: 10%; right: 10%; height: 1px;
					background: linear-gradient(90deg, transparent, rgba(139,92,246,0.3), transparent);
				}
				.{$p}-hero-editorial::before {
					content: '';
					position: absolute;
					top: 0; left: 0; right: 0;
					height: 1px;
					background: linear-gradient(90deg, transparent 10%, rgba(139,92,246,0.4) 50%, transparent 90%);
					z-index: 1;
				}
				.{$p}-hero-editorial::after {
					content: '';
					position: absolute;
					inset: 0;
					background: radial-gradient(ellipse at 60% 20%, rgba(139,92,246,0.08) 0%, transparent 50%);
					z-index: 1;
					pointer-events: none;
				}

				/* ── Responsive ── */
				@media(max-width:782px) {
					.{$p}-hero-wrap {
						border-radius: 20px;
					}
					.{$p}-hero-aurora {
						padding: 48px 24px 40px;
					}
					.{$p}-hero-glass {
						padding: 44px 24px 38px;
					}
					.{$p}-hero-editorial {
						padding: 52px 24px 44px;
					}
					.{$p}-hero-wrap .{$p}-lead-p {
						font-size: 1.15rem !important;
						max-width: 100%;
					}
					.{$p}-hero-glass .{$p}-lead-p {
						padding: 28px 24px !important;
						border-radius: 16px !important;
					}
				}
				";
			}
		}

		/* ── #6 Heading Decor ──────────────────────── */
		if ( ! empty( $o['hdecor_enabled'] ) ) {
			$tags = array_map( 'trim', explode( ',', $o['hdecor_tags'] ) );
			$hS   = $px( $o['hdecor_size'] );
			$hR   = $px( $o['hdecor_border_radius'] );

			foreach ( $tags as $tag ) {
				$tag = preg_replace( '/[^a-z0-9]/', '', strtolower( $tag ) );
				if ( ! $tag ) continue;

				$sel = ".{$p}-heading-{$tag}";

				if ( $o['hdecor_shape'] === 'line' && $o['hdecor_position'] === 'left' ) {
					$css .= "{$sel}{position:relative;padding-left:calc({$hS} + 14px);}{$sel}::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:{$hS};height:60%;background:{$hDecorC};border-radius:{$hR};}";
				} elseif ( $o['hdecor_shape'] === 'line' && $o['hdecor_position'] === 'bottom' ) {
					$hL = $px( $o['hdecor_line_length'] ?? '60' );
					$css .= "{$sel}{position:relative;padding-bottom:12px;}{$sel}::after{content:'';position:absolute;bottom:0;left:0;width:{$hL};height:{$hS};background:{$hDecorC};border-radius:{$hR};}";
				} elseif ( $o['hdecor_shape'] === 'square' ) {
					$sz = max( 10, intval( $o['hdecor_size'] ) );
					$css .= "{$sel}{position:relative;padding-left:calc({$px($sz)} + 14px);}{$sel}::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:{$px($sz)};height:{$px($sz)};background:{$hDecorC};border-radius:{$hR};}";
				} elseif ( $o['hdecor_shape'] === 'icon' ) {
					$css .= "{$sel}{display:flex;align-items:center;gap:10px;}";
				}
			}

			// Highlight words
			$hw = intval( $o['hdecor_highlight_words'] );
			if ( $hw > 0 ) {
				$css .= ".{$p}-heading-highlight{color:{$hHighC};}";
			}
		}

		/* ── #7 Images ─────────────────────────────── */
		if ( ! empty( $o['img_enabled'] ) ) {
			$iR  = $px( $o['img_border_radius'] );
			$iBW = $px( $o['img_border_width'] );
			$iMT = $rem( $o['img_margin_top'] );
			$iMB = $rem( $o['img_margin_bottom'] );

			$css .= "
			.{$p}-figure{margin-top:{$iMT};margin-bottom:{$iMB};transition:box-shadow {$tr};}
			.{$p}-figure img{border-radius:{$iR};box-shadow:{$o['img_shadow']};border:{$iBW} solid rgba(0,0,0,0.08);transition:all {$tr};}
			.{$p}-figure:hover img{box-shadow:{$o['img_shadow_hover']};}
			";
		}

		/* ── #8 Gallery Carousel ───────────────────── */
		if ( ! empty( $o['gallery_enabled'] ) ) {
			$gR    = $px( $o['gallery_border_radius'] );
			$gCols = intval( $o['gallery_cols_desktop'] );
			$gColM = intval( $o['gallery_cols_mobile'] );

			$css .= "
			.{$p}-carousel-wrap{position:relative;margin:2rem auto;}
			.{$p}-carousel{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;gap:16px;scrollbar-width:none;scroll-behavior:smooth;}
			.{$p}-carousel::-webkit-scrollbar{display:none;}
			.{$p}-carousel .{$p}-carousel-item{flex:0 0 calc((100% - " . ( ( $gCols - 1 ) * 16 ) . "px)/{$gCols});scroll-snap-align:start;border-radius:{$gR};overflow:hidden;box-shadow:{$o['gallery_shadow']};transition:all {$tr};}
			.{$p}-carousel .{$p}-carousel-item:hover{box-shadow:{$o['gallery_shadow_hover']};}
			.{$p}-carousel .{$p}-carousel-item img{width:100%;height:100%;object-fit:cover;display:block;}
			.{$p}-carousel-arrow{position:absolute;top:50%;transform:translateY(-50%);width:48px!important;height:48px!important;padding:0;min-width:0;min-height:0;aspect-ratio:1/1;border-radius:50%;background:#ffffff;border:1px solid rgba(0,0,0,0.05);cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,0.12);z-index:5;transition:all 0.2s;color:var(--{$p}-primary);}
			.{$p}-carousel-arrow svg{stroke:var(--{$p}-primary);width:22px;height:22px;pointer-events:none;}
			.{$p}-carousel-arrow:hover{background:#fff;box-shadow:0 6px 20px rgba(0,0,0,0.18);transform:translateY(-50%) scale(1.05);}
			.{$p}-carousel-prev{left:-23px;}
			.{$p}-carousel-next{right:-23px;}
			.{$p}-carousel-dots{display:flex;justify-content:center;gap:8px;margin-top:20px;}
			.{$p}-carousel-dot{width:12px;height:12px;border-radius:50%;background:rgba(0,0,0,0.1);border:none;cursor:pointer;transition:all 0.2s;padding:0;}
			.{$p}-carousel-dot.active{background:var(--{$p}-primary);transform:scale(1.25);box-shadow:0 2px 6px rgba(0,0,0,0.15);}
			@media(max-width:640px){.{$p}-carousel .{$p}-carousel-item{flex:0 0 calc((100% - " . ( ( $gColM - 1 ) * 16 ) . "px)/{$gColM});}.{$p}-carousel-prev{left:10px;}.{$p}-carousel-next{right:10px;}.{$p}-carousel-arrow{width:36px;height:36px;font-size:1.1rem;}}
			";
		}

		/* ── #9 Latest Posts ───────────────────────── */
		if ( ! empty( $o['latest_enabled'] ) ) {
			$lpR = $px( $o['latest_border_radius'] );
			$hoverTransform = $o['latest_hover_effect'] === 'scale' ? 'scale(1.03)' : 'translateY(-6px)';

			$css .= "
			.{$p}-latest-posts{}
			.{$p}-latest-posts.wp-block-latest-posts{list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;}
			.{$p}-latest-posts.wp-block-latest-posts li{border-radius:{$lpR};box-shadow:{$o['latest_shadow']};overflow:hidden;transition:all {$tr};background:#fff;margin:0;padding:0;}
			.{$p}-latest-posts.wp-block-latest-posts li:hover{box-shadow:{$o['latest_shadow_hover']};transform:{$hoverTransform};}
			.{$p}-latest-posts .wp-block-latest-posts__featured-image{margin:0;}
			.{$p}-latest-posts .wp-block-latest-posts__featured-image a{display:block;}
			.{$p}-latest-posts .wp-block-latest-posts__featured-image img{border-radius:{$lpR} {$lpR} 0 0;width:100%;height:200px;object-fit:cover;display:block;}
			.{$p}-latest-posts.wp-block-latest-posts li > a{display:block;padding:16px 20px 8px;font-weight:700;font-size:1.05rem;color:var(--{$p}-heading);text-decoration:none;line-height:1.4;}
			.{$p}-latest-posts .wp-block-latest-posts__post-date,.{$p}-latest-posts .wp-block-latest-posts__post-excerpt{padding:0 20px 12px;font-size:0.9rem;color:var(--{$p}-muted);line-height:1.5;}
			@media(max-width:640px){.{$p}-latest-posts.wp-block-latest-posts{grid-template-columns:1fr;}}
			";
		}

		/* ── #10 Alternating Sections ──────────────── */
		if ( ! empty( $o['alt_enabled'] ) ) {
			$aPT    = $rem( $o['alt_padding_top'] );
			$aPB    = $rem( $o['alt_padding_bottom'] );
			$aR     = $px( $o['alt_border_radius'] );
			$layout = $o['alt_layout'] ?? 'boxed';

			if ( $layout === 'full' ) {
				$css .= "
				.{$p}-section-odd, .{$p}-section-even {
					padding-top: {$aPT}; padding-bottom: {$aPB};
					width: 100vw;
					max-width: 100vw !important;
					position: relative;
					left: 50%;
					transform: translateX(-50%);
					padding-left: calc(50vw - 50% + 20px);
					padding-right: calc(50vw - 50% + 20px);
					box-sizing: border-box;
				}
				@media(max-width: 1024px) {
					.{$p}-section-odd, .{$p}-section-even {
						width: calc(100% + 40px);
						margin-left: -20px;
						padding-left: 20px;
						padding-right: 20px;
						transform: none;
						left: auto;
					}
				}
				";
			} else {
				$css .= "
				.{$p}-section-odd, .{$p}-section-even {
					padding-top: {$aPT}; padding-bottom: {$aPB};
					margin: 20px 0;
					padding-left: 30px;
					padding-right: 30px;
					border-radius: {$aR};
					box-sizing: border-box;
				}
				@media(max-width: 640px) {
					.{$p}-section-odd, .{$p}-section-even {
						padding-left: 15px;
						padding-right: 15px;
						margin: 10px 0;
					}
				}
				";
			}

			$css .= "
			.{$p}-section-odd{background:{$o['alt_odd_bg']};color:{$o['alt_odd_color']};}
			.{$p}-section-even{background:{$o['alt_even_bg']};color:{$o['alt_even_color']};}
			";
		}

		/* ── #11 Pros / Cons Columns ──────────────── */
		if ( ! empty( $o['pc_enabled'] ) ) {
			$pros_bg   = $o['pc_pros_bg'] ?? '#eaf5eb';
			$cons_bg   = $o['pc_cons_bg'] ?? '#fdeaea';
			$title_c   = $o['pc_title_color'] ?? '#1f2937';
			$pros_title_bg = $o['pc_pros_title_bg'] ?? '#4B9C52';
			$cons_title_bg = $o['pc_cons_title_bg'] ?? '#d14b4b';

			$pros_icon_c = $o['pc_pros_icon_color'] ?? '#1f2937';
			$cons_icon_c = $o['pc_cons_icon_color'] ?? '#1f2937';
			$pros_icon   = $this->pc_icon_data_uri( $o['pc_pros_icon'] ?? 'check', $pros_icon_c );
			$cons_icon   = $this->pc_icon_data_uri( $o['pc_cons_icon'] ?? 'cross', $cons_icon_c );

			$decor_enabled = ! empty( $o['pc_decor_enabled'] );
			$decor_opacity = is_numeric( $o['pc_decor_opacity'] ?? null ) ? (string) $o['pc_decor_opacity'] : '0.10';
			$decor_size    = intval( $o['pc_decor_size'] ?? 160 );
			$pros_decor    = $this->pc_icon_data_uri( $o['pc_pros_decor_type'] ?? 'thumb-up', $o['pc_pros_decor_color'] ?? $pros_title_bg );
			$cons_decor    = $this->pc_icon_data_uri( $o['pc_cons_decor_type'] ?? 'thumb-down', $o['pc_cons_decor_color'] ?? $cons_title_bg );

			$css .= "
			.{$p}-pc{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:2rem 0;}
			@media(max-width:768px){.{$p}-pc{grid-template-columns:1fr;}}
			.{$p}-pc-col{border-radius:14px;padding:22px 22px 18px;position:relative;overflow:hidden;border:1px solid rgba(0,0,0,0.04);box-shadow:0 8px 26px rgba(17,24,39,0.06);}
			.{$p}-pc-pros{background:{$pros_bg};}
			.{$p}-pc-cons{background:{$cons_bg};}
			.{$p}-pc-title{margin:0 0 14px!important;font-weight:800;letter-spacing:-0.2px;color:{$title_c};display:inline-flex;align-items:center;padding:8px 12px;border-radius:10px;}
			.{$p}-pc-pros .{$p}-pc-title{background:{$pros_title_bg};}
			.{$p}-pc-cons .{$p}-pc-title{background:{$cons_title_bg};}
			.{$p}-pc-pros .{$p}-pc-title, .{$p}-pc-cons .{$p}-pc-title{color:#fff;}
			.{$p}-pc-title.wp-block-heading{margin-bottom:14px!important;}
			.{$p}-pc ul{list-style:none;margin:0!important;padding:0!important;}
			.{$p}-pc li{display:flex;gap:12px;align-items:flex-start;line-height:1.55;margin:0 0 10px!important;padding:0!important;}
			.{$p}-pc li:last-child{margin-bottom:0!important;}
			.{$p}-pc li::before{content:'';width:18px;height:18px;flex:0 0 18px;margin-top:2px;background-repeat:no-repeat;background-position:center;background-size:18px 18px;}
			.{$p}-pc-pros li::before{background-image:url('{$pros_icon}');}
			.{$p}-pc-cons li::before{background-image:url('{$cons_icon}');}
			";

			if ( $decor_enabled ) {
				$sz = max( 60, $decor_size );
				$css .= "
				.{$p}-pc-col::after{content:'';position:absolute;top:-18px;right:-18px;width:{$sz}px;height:{$sz}px;opacity:{$decor_opacity};pointer-events:none;background-repeat:no-repeat;background-position:center;background-size:contain;transform:rotate(12deg);}
				.{$p}-pc-pros::after{background-image:url('{$pros_decor}');}
				.{$p}-pc-cons::after{background-image:url('{$cons_decor}');}
				";
			}

			/* ── Skins ───────────────────────────────── */
			// Minimal: no shadow/border, no title pill, no decor.
			$css .= "
			.{$p}-pc-skin-minimal .{$p}-pc-col{box-shadow:none;border:0;border-radius:12px;}
			.{$p}-pc-skin-minimal .{$p}-pc-title{background:transparent!important;padding:0!important;border-radius:0!important;color:{$title_c}!important;}
			.{$p}-pc-skin-minimal .{$p}-pc-col::after{display:none!important;}
			";

			// Soft: like screenshot #1 (default).
			$css .= "
			.{$p}-pc-skin-soft .{$p}-pc-col{border-radius:16px;}
			.{$p}-pc-skin-soft .{$p}-pc-title{gap:10px;}
			.{$p}-pc-skin-soft .{$p}-pc-title::before{content:'';width:18px;height:18px;flex:0 0 18px;background-repeat:no-repeat;background-position:center;background-size:18px 18px;filter:brightness(0) invert(1);opacity:0.95;}
			.{$p}-pc-skin-soft .{$p}-pc-pros .{$p}-pc-title::before{background-image:url('{$pros_icon}');}
			.{$p}-pc-skin-soft .{$p}-pc-cons .{$p}-pc-title::before{background-image:url('{$cons_icon}');}
			";

			// Good/Bad: like screenshot #2 (big caps + small tag).
			$css .= "
			.{$p}-pc-skin-goodbad .{$p}-pc-title{background:transparent!important;padding:0!important;border-radius:0!important;color:{$title_c}!important;display:flex!important;justify-content:space-between;width:100%;align-items:baseline;font-size:1.55rem;letter-spacing:0.4px;text-transform:uppercase;}
			.{$p}-pc-skin-goodbad .{$p}-pc-title::after{content:'STRENGTHS';font-size:11px;letter-spacing:1px;font-weight:800;padding:4px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#065f46;margin-left:auto;}
			.{$p}-pc-skin-goodbad .{$p}-pc-cons .{$p}-pc-title::after{content:'WEAKNESSES';background:rgba(239,68,68,0.12);color:#7f1d1d;}
			.{$p}-pc-skin-goodbad .{$p}-pc-col{padding:26px 26px 18px;border-radius:18px;}
			";

			// Editorial: like screenshot #3 (gradient backdrop + inner card feel).
			$css .= "
			.{$p}-pc-skin-editorial{padding:24px;border-radius:22px;background:linear-gradient(135deg, rgba(147,197,253,0.30) 0%, rgba(167,243,208,0.30) 35%, rgba(253,186,116,0.22) 100%);}
			.{$p}-pc-skin-editorial .{$p}-pc-col{background:rgba(255,255,255,0.65)!important;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.55);box-shadow:0 10px 30px rgba(15,23,42,0.08);}
			.{$p}-pc-skin-editorial .{$p}-pc-title{background:transparent!important;color:{$title_c}!important;padding:0!important;}
			.{$p}-pc-skin-editorial .{$p}-pc-title::before{content:'';width:34px;height:34px;border-radius:999px;background:rgba(0,0,0,0.06);margin-right:10px;flex:0 0 34px;background-repeat:no-repeat;background-position:center;background-size:18px 18px;}
			.{$p}-pc-skin-editorial .{$p}-pc-pros .{$p}-pc-title::before{background-image:url('{$pros_icon}');}
			.{$p}-pc-skin-editorial .{$p}-pc-cons .{$p}-pc-title::before{background-image:url('{$cons_icon}');}
			";
		}

		return $css;
	}

	/* ─── CONTENT PROCESSING ────────────────────────────────── */

	public function process_content( string $content ): string {
		if ( ! $this->should_apply() ) {
			return $content;
		}

		$o = WPBB_Settings::get_all();
		$p = sanitize_html_class( $o['css_prefix'] );

		// #11 Pros / Cons Columns (must run before UL processing to prevent UL styling inside).
		if ( ! empty( $o['pc_enabled'] ) ) {
			$content = $this->process_pros_cons_columns( $content, $p );
		}

		// #1 Blockquotes.
		if ( ! empty( $o['bq_enabled'] ) ) {
			$content = $this->process_blockquotes( $content, $o, $p );
		}

		// #2 Ordered lists.
		if ( ! empty( $o['ol_enabled'] ) ) {
			$content = $this->process_ol( $content, $p );
		}

		// #3 Unordered lists.
		if ( ! empty( $o['ul_enabled'] ) ) {
			$content = $this->process_ul( $content, $p );
		}

		// #4 Tables.
		if ( ! empty( $o['table_enabled'] ) ) {
			$content = $this->process_tables( $content, $p );
		}

		// #5 Lead Paragraph.
		if ( ! empty( $o['lead_enabled'] ) ) {
			$content = $this->process_lead( $content, $p, $o );
		}

		// #6 Heading Decor.
		if ( ! empty( $o['hdecor_enabled'] ) ) {
			$content = $this->process_headings( $content, $o, $p );
		}

		// #7 Images.
		if ( ! empty( $o['img_enabled'] ) ) {
			$content = $this->process_images( $content, $p );
		}

		// #8 Galleries — handled via JS.
		// #9 Latest Posts — CSS only.

		// #10 Alternating Sections.
		if ( ! empty( $o['alt_enabled'] ) ) {
			$content = $this->process_alternating( $content, $p );
		}

		return $content;
	}

	/* ─── #1 BLOCKQUOTES ────────────────────────────────────── */

	private function process_blockquotes( string $content, array $o, string $p ): string {
		$layout      = $o['bq_layout'];
		$name        = $o['bq_author_name'];
		$role        = $o['bq_author_role'];
		$photo       = $o['bq_author_photo'];
		$show_quotes = ! empty( $o['bq_show_quotes'] );
		$one_line    = ! empty( $o['bq_one_line'] );

		// Fallback to post author.
		global $post;
		if ( empty( $name ) && isset( $post->post_author ) ) {
			$name = get_the_author_meta( 'display_name', $post->post_author );
		}
		if ( empty( $photo ) && isset( $post->post_author ) ) {
			$photo = get_avatar_url( $post->post_author, [ 'size' => 120 ] );
		}

		if ( empty( $name ) && empty( $photo ) ) {
			return $content;
		}

		$quote_svg = $show_quotes ? '<span class="' . $p . '-bq-quote-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-3c0-1.1.895-2 2-2h3c.553 0 1-.447 1-1V9c0-.553-.447-1-1-1h-3c-.553 0-1 .447-1 1v3h-2V9c0-1.657 1.343-3 3-3h3c1.657 0 3 1.343 3 3v6c0 1.657-1.343 3-3 3h-3v3h-2zM4 21v-3c0-1.1.895-2 2-2h3c.553 0 1-.447 1-1V9c0-.553-.447-1-1-1H6c-.553 0-1 .447-1 1v3H3V9c0-1.657 1.343-3 3-3h3c1.657 0 3 1.343 3 3v6c0 1.657-1.343 3-3 3H6v3H4z"/></svg></span>' : '';

		$oneline_class = $one_line ? ' ' . $p . '-bq-info-oneline' : '';

		$author_html  = '<div class="' . $p . '-bq-meta ' . $p . '-bq-layout-' . esc_attr( $layout ) . '">';
		if ( $photo ) {
			$author_html .= '<div class="' . $p . '-bq-photo"><img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '"></div>';
		}
		$author_html .= '<div class="' . $p . '-bq-details">';
		$author_html .= '<span class="' . $p . '-bq-badge"><svg viewBox="0 0 24 24"><path d="M23,12l-2.43-2.78.34-3.68-3.61-.82L15.4,1.5 12,2.95 8.6,1.5 6.7,4.72l-3.61.82.34,3.68L1,12l2.43,2.78-.34,3.68 3.61.82L8.6,22.5l3.4-1.45 3.4,1.45 1.9-3.22 3.61.82-.34-3.68L23,12z" fill="' . esc_attr( $o['bq_icon_color'] ) . '"/><path d="M10,15.58l-3.5-3.5 1.41-1.41L10,12.75l5.09-5.09 1.41,1.41L10,15.58z" fill="#fff"/></svg></span>';
		$author_html .= '<div class="' . $p . '-bq-info' . $oneline_class . '">';
		$author_html .= '<div class="' . $p . '-bq-name">' . esc_html( $name ) . '</div>';
		if ( $role ) {
			$author_html .= '<div class="' . $p . '-bq-role">' . esc_html( $role ) . '</div>';
		}
		$author_html .= '</div></div>';
		$author_html .= $quote_svg;
		$author_html .= '</div>';

		// Только первая цитата в контенте получает блок автора — чтобы pull-quote
		// и последующие цитаты не дублировали одну и ту же подпись.
		$content = preg_replace_callback(
			'/<blockquote([^>]*)>/i',
			function ( $matches ) use ( $p, $author_html ) {
				$attrs       = $matches[1];
				$new_classes = $p . '-bq-block';

				if ( preg_match( '/class=["\']([^"\']*)["\']/', $attrs, $cm ) ) {
					$updated = str_replace( $cm[0], 'class="' . trim( $cm[1] . ' ' . $new_classes ) . '"', $attrs );
					return '<blockquote' . $updated . '>' . $author_html;
				}
				return '<blockquote class="' . $new_classes . '"' . $attrs . '>' . $author_html;
			},
			$content,
			1
		);

		return $content;
	}

	/* ─── #2 ORDERED LISTS ──────────────────────────────────── */

	private function process_ol( string $content, string $p ): string {
		return preg_replace_callback(
			'/<ol([^>]*)>/i',
			function ( $m ) use ( $p ) {
				return $this->add_class_to_tag( 'ol', $m[1], $p . '-ol' );
			},
			$content
		);
	}

	/* ─── #3 UNORDERED LISTS ────────────────────────────────── */

	private function process_ul( string $content, string $p ): string {
		return preg_replace_callback(
			'/<ul([^>]*)>/i',
			function ( $m ) use ( $p ) {
				// Don't apply to latest-post section.
				if ( strpos( $m[1], 'wp-block-latest-posts' ) !== false ) {
					return $m[0];
				}
				// Don't apply to pros/cons section.
				if ( strpos( $m[1], $p . '-pc-ul' ) !== false ) {
					return $m[0];
				}
				$skin_class = $this->resolve_ul_skin_class( $m[1], $p );
				return $this->add_class_to_tag( 'ul', $m[1], $p . '-ul ' . $skin_class );
			},
			$content
		);
	}

	private function resolve_ul_skin_class( string $attrs, string $p ): string {
		$o = WPBB_Settings::get_all();
		$default_skin = sanitize_html_class( $o['ul_default_skin'] ?? 'cards' );
		$allowed = [ 'cards', 'glass', 'outline' ];
		$skin = in_array( $default_skin, $allowed, true ) ? $default_skin : 'cards';

		if ( preg_match( '/class=["\']([^"\']*)["\']/', $attrs, $cm ) ) {
			if ( preg_match( '/\bul-skin-(cards|glass|outline)\b/i', $cm[1], $sm ) ) {
				$skin = strtolower( sanitize_html_class( $sm[1] ) );
			}
		}

		return $p . '-ul-skin-' . $skin;
	}

	/* ─── #11 PROS / CONS COLUMNS ───────────────────────────── */

	private function process_pros_cons_columns( string $content, string $p ): string {
		if ( stripos( $content, 'col-pros-cons' ) === false ) {
			return $content;
		}

		$o = WPBB_Settings::get_all();
		$default_skin = sanitize_html_class( $o['pc_default_skin'] ?? 'soft' );

		$libxml_prev = libxml_use_internal_errors( true );
		$dom = new DOMDocument();

		// Wrap to keep fragments valid.
		$dom->loadHTML(
			'<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="wpbb-root">' . $content . '</div></body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		$root = $dom->getElementById( 'wpbb-root' );
		if ( ! $root ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_prev );
			return $content;
		}

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-columns ") and contains(concat(" ", normalize-space(@class), " "), " col-pros-cons ")]' );
		if ( ! $nodes || $nodes->length === 0 ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_prev );
			return $content;
		}

		foreach ( $nodes as $columns ) {
			if ( ! ( $columns instanceof DOMElement ) ) {
				continue;
			}

			$this->dom_add_class( $columns, $p . '-pc' );

			// Skin per-block: pc-skin-xxx on original markup, otherwise fallback to default skin.
			$skin = '';
			$cls_attr = $columns->getAttribute( 'class' );
			if ( preg_match( '/\bpc-skin-([a-z0-9_-]+)\b/i', $cls_attr, $m ) ) {
				$skin = sanitize_html_class( strtolower( $m[1] ) );
			} else {
				$skin = $default_skin ?: 'soft';
			}
			$this->dom_add_class( $columns, $p . '-pc-skin-' . $skin );

			$col_idx = 0;
			foreach ( $columns->childNodes as $child ) {
				if ( ! ( $child instanceof DOMElement ) ) {
					continue;
				}
				if ( ! $this->dom_has_class( $child, 'wp-block-column' ) ) {
					continue;
				}

				$col_idx++;
				$this->dom_add_class( $child, $p . '-pc-col' );
				$this->dom_add_class( $child, $col_idx === 1 ? $p . '-pc-pros' : $p . '-pc-cons' );

				// Title can be p/h3/h4 — first matching element inside the column.
				$title = $xpath->query( './/*[self::p or self::h3 or self::h4][1]', $child );
				if ( $title && $title->length > 0 && $title->item(0) instanceof DOMElement ) {
					$this->dom_add_class( $title->item(0), $p . '-pc-title' );
				}

				// Mark ULs inside to skip UL-cards styling.
				$uls = $xpath->query( './/ul', $child );
				if ( $uls && $uls->length > 0 ) {
					foreach ( $uls as $ul ) {
						if ( $ul instanceof DOMElement ) {
							$this->dom_add_class( $ul, $p . '-pc-ul' );
						}
					}
				}
			}
		}

		// Extract inner HTML of root wrapper.
		$out = '';
		foreach ( $root->childNodes as $n ) {
			$out .= $dom->saveHTML( $n );
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_prev );

		return $out;
	}

	private function pc_icon_data_uri( string $type, string $color ): string {
		$type = strtolower( preg_replace( '/[^a-z-]/', '', $type ) );
		$color = $color ?: '#1f2937';

		$svg = '';
		if ( $type === 'thumb-up' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M3 9h4v11H3V9zm6.5 11H17a3 3 0 0 0 2.95-2.45l1.04-5.5A2.5 2.5 0 0 0 18.53 9H14V4.8A1.8 1.8 0 0 0 12.2 3a1.6 1.6 0 0 0-1.48.98L8.2 9.7A2 2 0 0 0 8 10.6V18a2 2 0 0 0 1.5 2z"/></svg>';
		} elseif ( $type === 'thumb-down' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M3 4h4v11H3V4zm6.5 0H17a3 3 0 0 1 2.95 2.45l1.04 5.5A2.5 2.5 0 0 1 18.53 15H14v4.2a1.8 1.8 0 0 1-1.8 1.8 1.6 1.6 0 0 1-1.48-.98L8.2 14.3A2 2 0 0 1 8 13.4V6a2 2 0 0 1 1.5-2z"/></svg>';
		} elseif ( $type === 'plus' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M11 5a1 1 0 0 1 2 0v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6V5z"/></svg>';
		} elseif ( $type === 'minus' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M5 11a1 1 0 0 0 0 2h14a1 1 0 1 0 0-2H5z"/></svg>';
		} elseif ( $type === 'cross' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M18.3 5.7a1 1 0 0 1 0 1.4L13.4 12l4.9 4.9a1 1 0 1 1-1.4 1.4L12 13.4l-4.9 4.9a1 1 0 0 1-1.4-1.4l4.9-4.9-4.9-4.9a1 1 0 0 1 1.4-1.4l4.9 4.9 4.9-4.9a1 1 0 0 1 1.4 0z"/></svg>';
		} elseif ( $type === 'star' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="m12 2.5 2.9 5.87 6.48.94-4.69 4.57 1.1 6.46L12 17.3l-5.79 3.04 1.1-6.46L2.62 9.31l6.48-.94L12 2.5z"/></svg>';
		} elseif ( $type === 'spark' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M12 2 14.3 8.7 21 11l-6.7 2.3L12 20l-2.3-6.7L3 11l6.7-2.3L12 2zm7-1 1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3zM5 15l1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2z"/></svg>';
		} elseif ( $type === 'alert' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M12 2 1.7 20h20.6L12 2zm-1 6h2v6h-2V8zm0 8h2v2h-2v-2z"/></svg>';
		} elseif ( $type === 'ban' ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M12 3a9 9 0 1 0 9 9 9.01 9.01 0 0 0-9-9zm0 2a6.95 6.95 0 0 1 4.9 2.03L7.03 16.9A7 7 0 0 1 12 5zm0 14a6.95 6.95 0 0 1-4.9-2.03L16.97 7.1A7 7 0 0 1 12 19z"/></svg>';
		} else { // check (default)
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="' . esc_attr( $color ) . '" d="M9.0 16.2 4.8 12a1 1 0 0 1 1.4-1.4l2.8 2.8 8.8-8.8a1 1 0 1 1 1.4 1.4l-10.2 10.2a1 1 0 0 1-1.4 0z"/></svg>';
		}

		return 'data:image/svg+xml,' . rawurlencode( $svg );
	}

	private function dom_has_class( DOMElement $el, string $class ): bool {
		$cls = ' ' . preg_replace( '/\s+/', ' ', $el->getAttribute( 'class' ) ) . ' ';
		return strpos( $cls, ' ' . $class . ' ' ) !== false;
	}

	private function dom_add_class( DOMElement $el, string $class ): void {
		$existing = trim( $el->getAttribute( 'class' ) );
		if ( $existing === '' ) {
			$el->setAttribute( 'class', $class );
			return;
		}
		$classes = preg_split( '/\s+/', $existing );
		if ( in_array( $class, $classes, true ) ) {
			return;
		}
		$classes[] = $class;
		$el->setAttribute( 'class', trim( implode( ' ', $classes ) ) );
	}

	/* ─── #4 TABLES ─────────────────────────────────────────── */

	private function process_tables( string $content, string $p ): string {
		$cls = $p . '-table-wrap';

		// Шаг 1: Gutenberg figure.wp-block-table → div-обёртка.
		// Внутренние <table> помечаем маркером, чтобы шаг 2 их пропустил (без хрупкого strpos).
		$content = preg_replace_callback(
			'/<figure[^>]*class="[^"]*wp-block-table[^"]*"[^>]*>(.*?)<\/figure>/is',
			function ( $m ) use ( $cls ) {
				$inner = preg_replace( '/<table\b/i', '<table data-wpbb-wrapped="1"', $m[1], 1 );
				return '<div class="' . $cls . '">' . $inner . '</div>';
			},
			$content
		);

		// Шаг 2: Standalone таблицы (без маркера и не внутри обёртки).
		$content = preg_replace_callback(
			'/<table\b(?![^>]*data-wpbb-wrapped)([^>]*)>(.*?)<\/table>/is',
			function ( $m ) use ( $cls ) {
				return '<div class="' . $cls . '"><table' . $m[1] . '>' . $m[2] . '</table></div>';
			},
			$content
		);

		// Убираем служебный маркер.
		$content = str_replace( ' data-wpbb-wrapped="1"', '', $content );

		return $content;
	}

	/* ─── #5 LEAD PARAGRAPH ─────────────────────────────────── */

	private function process_lead( string $content, string $p, array $o ): string {
		$hero_skin = $o['lead_hero_skin'] ?? 'none';
		// Добавляем класс только к первому <p>.
		$done = false;
		$content = preg_replace_callback(
			'/<p([^>]*)>(.*?)<\/p>/is',
			function ( $m ) use ( $p, $hero_skin, &$done ) {
				if ( $done ) {
					return $m[0];
				}
				$attrs = $m[1];
				$inner = $m[2];

				// Пропускаем уже обработанные плагином абзацы (заголовок Pros/Cons и т.п.)
				// и пустые абзацы — чтобы lead навешивался на реальный вводный текст,
				// а не на первый попавшийся <p> внутри колонок/цитат.
				if ( stripos( $attrs, $p . '-pc-title' ) !== false || stripos( $attrs, $p . '-bq-' ) !== false ) {
					return $m[0];
				}
				if ( trim( wp_strip_all_tags( $inner ) ) === '' ) {
					return $m[0];
				}

				$done = true;
				$cls = $p . '-lead-p';

				$p_tag = $this->add_class_to_tag( 'p', $m[1], $cls );
				$inner = $p_tag . $m[2] . '</p>';

				if ( $hero_skin !== 'none' ) {
					$skin_class = sanitize_html_class( $hero_skin );
					$orbs  = '<span class="' . $p . '-hero-orb"></span>';
					$orbs .= '<span class="' . $p . '-hero-orb"></span>';
					$orbs .= '<span class="' . $p . '-hero-orb"></span>';
					$line  = '<span class="' . $p . '-hero-line"></span>';
					$inner = '<div class="' . $p . '-hero-wrap ' . $p . '-hero-' . $skin_class . '">'
						. $orbs . $line . $inner
						. '</div>';
				}

				return $inner;
			},
			$content
		);
		return $content;
	}

	/* ─── #6 HEADINGS ───────────────────────────────────────── */

	private function process_headings( string $content, array $o, string $p ): string {
		$tags_raw = array_map( 'trim', explode( ',', $o['hdecor_tags'] ) );
		$tags     = [];
		foreach ( $tags_raw as $t ) {
			$t = preg_replace( '/[^a-z0-9]/', '', strtolower( $t ) );
			if ( $t ) $tags[] = $t;
		}
		if ( empty( $tags ) ) return $content;

		$hw = intval( $o['hdecor_highlight_words'] );

		$pattern = '/<(' . implode( '|', $tags ) . ')([^>]*)>(.*?)<\/\1>/is';
		$content = preg_replace_callback( $pattern, function ( $m ) use ( $p, $o, $hw ) {
			$tag     = strtolower( $m[1] );
			$attrs   = $m[2];
			$inner   = $m[3];
			$class   = $p . '-heading-' . $tag;

			// Не декорируем заголовки внутри Pros/Cons — они уже стилизованы как pc-title.
			if ( stripos( $attrs, $p . '-pc-title' ) !== false ) {
				return $m[0];
			}

			// Highlight words.
			if ( $hw > 0 ) {
				$inner = $this->highlight_words( $inner, $hw, $p );
			}

			// Dashicon inject.
			$icon_html = '';
			if ( $o['hdecor_shape'] === 'icon' && ! empty( $o['hdecor_icon'] ) ) {
				$icon_color = ! empty( $o['hdecor_icon_color'] ) ? $o['hdecor_icon_color'] : $this->get_cascaded_color( 'hdecor_color', $o );
				$icon_size  = intval( $o['hdecor_icon_size'] ?? 32 );
				$icon_html  = '<span class="' . $p . '-heading-icon" style="display:inline-flex;align-items:center;justify-content:center;width:' . $icon_size . 'px;height:' . $icon_size . 'px;background:' . esc_attr( $o['hdecor_icon_bg'] ) . ';border-radius:' . intval( $o['hdecor_border_radius'] ) . 'px;flex-shrink:0;"><span class="dashicons ' . esc_attr( $o['hdecor_icon'] ) . '" style="color:' . esc_attr( $icon_color ) . ';font-size:' . round( $icon_size * 0.6 ) . 'px;width:auto;height:auto;"></span></span>';
			}

			$new_tag = $this->add_class_to_tag( $tag, $attrs, $class );
			return $new_tag . $icon_html . $inner . '</' . $tag . '>';
		}, $content );

		return $content;
	}

	private function highlight_words( string $text, int $count, string $p ): string {
		// Strip existing tags for word counting but preserve structure.
		$words = preg_split( '/(\s+)/', $text, $count + 1, PREG_SPLIT_DELIM_CAPTURE );
		if ( count( $words ) <= 1 ) {
			return $text;
		}

		$highlighted = '';
		$word_idx    = 0;
		foreach ( $words as $part ) {
			if ( trim( $part ) === '' ) {
				$highlighted .= $part;
				continue;
			}
			if ( $word_idx < $count ) {
				$highlighted .= '<span class="' . $p . '-heading-highlight">' . $part . '</span>';
				$word_idx++;
			} else {
				$highlighted .= $part;
			}
		}
		return $highlighted;
	}

	/* ─── #7 IMAGES ─────────────────────────────────────────── */

	private function process_images( string $content, string $p ): string {
		return preg_replace_callback(
			'/<figure([^>]*)>/i',
			function ( $m ) use ( $p ) {
				// Только wp-block-image.
				if ( strpos( $m[1], 'wp-block-image' ) === false ) {
					return $m[0];
				}
				return $this->add_class_to_tag( 'figure', $m[1], $p . '-figure' );
			},
			$content
		);
	}

	/* ─── #10 ALTERNATING SECTIONS ──────────────────────────── */

	private function process_alternating( string $content, string $p ): string {
		// Разделяем контент по H2 заголовкам.
		$parts = preg_split( '/(<h2[^>]*>.*?<\/h2>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( count( $parts ) <= 1 ) {
			return $content;
		}

		$result  = '';
		$counter = 0;
		$in_section = false;

		foreach ( $parts as $part ) {
			if ( preg_match( '/^<h2[^>]*>/i', $part ) ) {
				// Закрываем предыдущую секцию.
				if ( $in_section ) {
					$result .= '</div>';
				}
				$counter++;
				$parity = ( $counter % 2 === 1 ) ? 'odd' : 'even';
				$result .= '<div class="' . $p . '-section-' . $parity . '">';
				$result .= $part;
				$in_section = true;
			} else {
				if ( ! $in_section && $counter === 0 ) {
					// Контент до первого H2 — пропускаем обёртку.
					$result .= $part;
				} else {
					$result .= $part;
				}
			}
		}

		if ( $in_section ) {
			$result .= '</div>';
		}

		return $result;
	}

	/* ─── UTILITY ───────────────────────────────────────────── */

	private function add_class_to_tag( string $tag, string $attrs, string $new_class ): string {
		if ( preg_match( '/class=["\']([^"\']*)["\']/', $attrs, $cm ) ) {
			$updated = str_replace( $cm[0], 'class="' . trim( $cm[1] . ' ' . $new_class ) . '"', $attrs );
			return '<' . $tag . $updated . '>';
		}
		return '<' . $tag . ' class="' . $new_class . '"' . $attrs . '>';
	}

	private function get_cascaded_color( string $key, array $o ): string {
		$defaults = WPBB_Settings::defaults();
		$primary  = $o['primary_color'];
		$val      = $o[ $key ] ?? '';
		if ( empty( $val ) ) {
			return $primary;
		}
		if ( ! empty( $o['cascade_primary'] ) && $val === $defaults[ $key ] ) {
			return $primary;
		}
		return $val;
	}
}
