<?php
/**
 * Класс админки — страница настроек с вкладками, color picker, media upload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBB_Admin {

	private static ?self $instance = null;
	private const PAGE_SLUG = 'wp-block-boosty-settings';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_wpbb_export', [ $this, 'handle_export' ] );
		add_action( 'admin_post_wpbb_import', [ $this, 'handle_import' ] );
		add_action( 'admin_post_wpbb_reset', [ $this, 'handle_reset' ] );
	}

	public function add_settings_page(): void {
		// Видимый пункт в меню «Настройки» (URL остаётся options-general.php?page=...).
		add_options_page(
			'WP Block Boosty Settings',
			'Block Boosty',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/* ─── TOOLS: EXPORT / IMPORT / RESET ────────────────────── */

	private function tools_redirect( string $status ): void {
		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'wpbb_notice' => $status ],
			admin_url( 'options-general.php' )
		) );
		exit;
	}

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'wpbb_export' );

		$data = wp_json_encode( WPBB_Settings::get_all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wp-block-boosty-settings.json' );
		echo $data;
		exit;
	}

	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'wpbb_import' );

		$raw = isset( $_POST['wpbb_import_json'] ) ? wp_unslash( $_POST['wpbb_import_json'] ) : '';
		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			$this->tools_redirect( 'import_error' );
		}

		$clean = WPBB_Settings::instance()->sanitize_options( $decoded );
		update_option( WPBB_OPTION_KEY, $clean );
		$this->tools_redirect( 'import_ok' );
	}

	public function handle_reset(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'wpbb_reset' );

		update_option( WPBB_OPTION_KEY, WPBB_Settings::defaults() );
		$this->tools_redirect( 'reset_ok' );
	}

	private function render_notice(): void {
		if ( empty( $_GET['wpbb_notice'] ) ) {
			return;
		}
		$notice = sanitize_key( wp_unslash( $_GET['wpbb_notice'] ) );
		$map = [
			'import_ok'    => [ 'success', 'Settings imported successfully.' ],
			'import_error' => [ 'error', 'Import failed: invalid JSON.' ],
			'reset_ok'     => [ 'success', 'Settings reset to defaults.' ],
		];
		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $map[ $notice ][1] )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== self::PAGE_SLUG ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'wpbb-admin',
			WPBB_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WPBB_VERSION
		);
		wp_enqueue_script(
			'wpbb-admin',
			WPBB_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'wp-color-picker' ],
			WPBB_VERSION,
			true
		);
	}

	/* ─── HELPERS ───────────────────────────────────────────── */

	private function opts(): array {
		return WPBB_Settings::get_all();
	}

	private function text( string $name, string $placeholder = '' ): void {
		$val = esc_attr( $this->opts()[ $name ] ?? '' );
		printf(
			'<input type="text" name="%s[%s]" value="%s" placeholder="%s">',
			esc_attr( WPBB_OPTION_KEY ),
			esc_attr( $name ),
			$val,
			esc_attr( $placeholder )
		);
	}

	private function color( string $name ): void {
		$val = esc_attr( $this->opts()[ $name ] ?? '' );
		printf(
			'<input type="text" name="%s[%s]" value="%s" class="wpbb-color-picker">',
			esc_attr( WPBB_OPTION_KEY ),
			esc_attr( $name ),
			$val
		);
	}

	private function select( string $name, array $options ): void {
		$current = $this->opts()[ $name ] ?? '';
		printf( '<select name="%s[%s]">', esc_attr( WPBB_OPTION_KEY ), esc_attr( $name ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	private function checkbox( string $name, string $label_text ): void {
		$checked = ! empty( $this->opts()[ $name ] );
		printf(
			'<div class="wpbb-toggle"><input type="hidden" name="%1$s[%2$s]" value="0"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s><span>%4$s</span></div>',
			esc_attr( WPBB_OPTION_KEY ),
			esc_attr( $name ),
			checked( $checked, true, false ),
			esc_html( $label_text )
		);
	}

	private function switch_toggle( string $name ): void {
		$checked = ! empty( $this->opts()[ $name ] );
		printf(
			'<label class="wpbb-switch"><input type="hidden" name="%1$s[%2$s]" value="0"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s><span class="slider round"></span></label>',
			esc_attr( WPBB_OPTION_KEY ),
			esc_attr( $name ),
			checked( $checked, true, false )
		);
	}

	private function photo_upload( string $name ): void {
		$url = esc_attr( $this->opts()[ $name ] ?? '' );
		?>
		<div class="wpbb-photo-upload">
			<input type="hidden" id="wpbb_<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( WPBB_OPTION_KEY ); ?>[<?php echo esc_attr( $name ); ?>]" value="<?php echo $url; ?>">
			<div id="wpbb_<?php echo esc_attr( $name ); ?>_preview" class="wpbb-preview-circle">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>">
				<?php endif; ?>
			</div>
			<div class="wpbb-upload-controls">
				<button type="button" class="button wpbb-upload-btn" data-target="wpbb_<?php echo esc_attr( $name ); ?>">Upload Photo</button>
				<button type="button" class="button button-link-delete wpbb-remove-btn" data-target="wpbb_<?php echo esc_attr( $name ); ?>" style="<?php echo $url ? '' : 'display:none;'; ?>">Remove</button>
			</div>
		</div>
		<?php
	}

	/* ─── SECTION ENABLED BADGE ─────────────────────────────── */

	private function section_header( string $icon, string $title, string $enabled_key ): void {
		?>
		<h3>
			<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
			<?php echo esc_html( $title ); ?>
			<span class="wpbb-section-toggle">
				<?php $this->switch_toggle( $enabled_key ); ?>
			</span>
		</h3>
		<?php
	}

	/* ─── RENDER ────────────────────────────────────────────── */

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = $this->opts();
		?>
		<div class="wrap wpbb-admin-wrap">
			<?php $this->render_notice(); ?>
			<form method="post" action="options.php" class="wpbb-settings-form">
				<!-- Header -->
				<div class="wpbb-admin-header">
					<div class="wpbb-logo">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor" class="wpbb-logo-icon">
							<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
						</svg>
						<h1>WP Block Boosty <span class="v-tag">v<?php echo esc_html( WPBB_VERSION ); ?></span></h1>
						<div class="wpbb-status-toggle">
							<span class="status-label">Plugin Status:</span>
							<?php $this->switch_toggle( 'global_enabled' ); ?>
						</div>
					</div>
				</div>

				<?php settings_fields( 'wpbb_settings_group' ); ?>

				<!-- Tab Navigation -->
				<div class="wpbb-tabs-nav">
					<button type="button" class="wpbb-tab-btn active" data-tab="global"><span class="dashicons dashicons-admin-settings"></span> Global</button>
					<button type="button" class="wpbb-tab-btn" data-tab="blockquotes"><span class="dashicons dashicons-format-quote"></span> Quotes</button>
					<button type="button" class="wpbb-tab-btn" data-tab="ol"><span class="dashicons dashicons-editor-ol"></span> OL</button>
					<button type="button" class="wpbb-tab-btn" data-tab="ul"><span class="dashicons dashicons-editor-ul"></span> UL</button>
					<button type="button" class="wpbb-tab-btn" data-tab="tables"><span class="dashicons dashicons-editor-table"></span> Tables</button>
					<button type="button" class="wpbb-tab-btn" data-tab="lead"><span class="dashicons dashicons-editor-paragraph"></span> Lead P</button>
					<button type="button" class="wpbb-tab-btn" data-tab="headings"><span class="dashicons dashicons-heading"></span> Headings</button>
					<button type="button" class="wpbb-tab-btn" data-tab="images"><span class="dashicons dashicons-format-image"></span> Images</button>
					<button type="button" class="wpbb-tab-btn" data-tab="gallery"><span class="dashicons dashicons-format-gallery"></span> Gallery</button>
					<button type="button" class="wpbb-tab-btn" data-tab="latest"><span class="dashicons dashicons-admin-post"></span> Posts</button>
					<button type="button" class="wpbb-tab-btn" data-tab="alternating"><span class="dashicons dashicons-columns"></span> Sections</button>
					<button type="button" class="wpbb-tab-btn" data-tab="proscons"><span class="dashicons dashicons-yes-alt"></span> Pros/Cons</button>
				</div>

				<!-- TABS CONTENT -->
				<div class="wpbb-tabs-content">

					<!-- ═══ GLOBAL ═══ -->
					<div class="wpbb-tab-panel active" id="wpbb-tab-global">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-art"></span> Colors</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Primary Color</label><?php $this->color( 'primary_color' ); ?></div>
									<div class="wpbb-field"><label>Secondary Color</label><?php $this->color( 'secondary_color' ); ?></div>
								</div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-editor-expand"></span> Borders</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'border_radius', '12' ); ?></div>
									<div class="wpbb-field"><label>Border Width (px)</label><?php $this->text( 'border_width', '0' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Border Width Hover (px)</label><?php $this->text( 'border_width_hover', '0' ); ?></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-visibility"></span> Shadows</h3>
								<div class="wpbb-field"><label>Shadow (normal)</label><?php $this->text( 'shadow', '0 4px 24px rgba(0,0,0,0.08)' ); ?></div>
								<div class="wpbb-field"><label>Shadow (hover)</label><?php $this->text( 'shadow_hover', '0 8px 32px rgba(0,0,0,0.14)' ); ?></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-admin-generic"></span> Technical</h3>
								<div class="wpbb-field"><label>CSS Class Prefix</label><?php $this->text( 'css_prefix', 'be' ); ?><p class="description">Default: <code>be</code>. Prefix for all generated CSS classes.</p></div>
								<div class="wpbb-field"><label>Transition Speed (s)</label><?php $this->text( 'transition_speed', '0.3' ); ?></div>
								<div class="wpbb-field"><?php $this->checkbox( 'cascade_primary', 'Cascade Primary Color' ); ?><p class="description">When on, section colors left at their default fall back to the Primary Color. Turn off to use each section's own default colors.</p></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-admin-page"></span> Display Rules</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><?php $this->checkbox( 'display_on_pages', 'Apply on Pages' ); ?></div>
									<div class="wpbb-field"><?php $this->checkbox( 'display_on_posts', 'Apply on Posts' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Specific Page/Post IDs (comma-separated)</label><?php $this->text( 'display_ids', '123,456' ); ?><p class="description">Leave empty to apply on all.</p></div>
							</div>
							<div class="wpbb-card">
								<div class="wpbb-info-box">
									<span class="dashicons dashicons-info"></span>
									<p>Access this page: <br><code><?php echo esc_html( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ); ?></code></p>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ BLOCKQUOTES ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-blockquotes">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'admin-users', 'Author Details', 'bq_enabled' ); ?>
								<div class="wpbb-field"><label>Author Name</label><?php $this->text( 'bq_author_name', 'e.g. Lewis Crane' ); ?></div>
								<div class="wpbb-field"><label>Author Role</label><?php $this->text( 'bq_author_role', 'e.g. Content Editor' ); ?></div>
								<div class="wpbb-field"><label>Author Photo</label><?php $this->photo_upload( 'bq_author_photo' ); ?></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-layout"></span> Layout & Sizing</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Alignment</label><?php $this->select( 'bq_layout', [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] ); ?></div>
									<div class="wpbb-field"><?php $this->checkbox( 'bq_one_line', 'One Line (Name - Role)' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Quote Font Size (rem)</label><?php $this->text( 'bq_font_size', '1.3' ); ?></div>
									<div class="wpbb-field"><label>Photo Size (px)</label><?php $this->text( 'bq_photo_size', '64' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Max Block Width</label><?php $this->text( 'bq_max_width', '100%' ); ?></div>
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'bq_border_radius', '16' ); ?></div>
								</div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-art"></span> Colors & Appearance</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Background Color</label><?php $this->color( 'bq_bg_color' ); ?></div>
									<div class="wpbb-field"><label>Icon Color (Badge)</label><?php $this->color( 'bq_icon_color' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Shadow Color</label><?php $this->color( 'bq_shadow_color' ); ?></div>
									<div class="wpbb-field"><label>Shadow Blur (px)</label><?php $this->text( 'bq_shadow_blur', '30' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><?php $this->checkbox( 'bq_show_quotes', 'Show Quote Icon' ); ?></div>
									<div class="wpbb-field"><label>Quote Icon Color</label><?php $this->color( 'bq_quote_color' ); ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ ORDERED LISTS ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-ol">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'editor-ol', 'Ordered Lists (ol)', 'ol_enabled' ); ?>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Marker Color</label><?php $this->color( 'ol_marker_color' ); ?></div>
									<div class="wpbb-field"><label>Marker Background</label><?php $this->color( 'ol_marker_bg' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Marker BG Hover</label><?php $this->color( 'ol_marker_bg_hover' ); ?></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-layout"></span> List Item Style</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>LI Background</label><?php $this->color( 'ol_li_bg' ); ?></div>
									<div class="wpbb-field"><label>LI Background Hover</label><?php $this->color( 'ol_li_bg_hover' ); ?></div>
								</div>
								<div class="wpbb-field"><label>LI Shadow</label><?php $this->text( 'ol_li_shadow', '0 2px 8px rgba(0,0,0,0.04)' ); ?></div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Max Width</label><?php $this->text( 'ol_max_width', '100%' ); ?></div>
									<div class="wpbb-field"><label>Font Size (rem)</label><?php $this->text( 'ol_font_size', '1' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'ol_border_radius', '12' ); ?></div>
							</div>
						</div>
					</div>

					<!-- ═══ UNORDERED LISTS ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-ul">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'editor-ul', 'Unordered Lists (ul) — Cards', 'ul_enabled' ); ?>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Card Background</label><?php $this->color( 'ul_li_bg' ); ?></div>
									<div class="wpbb-field"><label>Card BG Hover</label><?php $this->color( 'ul_li_bg_hover' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Text Color</label><?php $this->color( 'ul_text_color' ); ?></div>
									<div class="wpbb-field"><label>Font Size (rem)</label><?php $this->text( 'ul_font_size', '1' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Default Skin</label><?php $this->select( 'ul_default_skin', [ 'cards' => 'Cards (default)', 'glass' => 'Glass', 'outline' => 'Outline Accent' ] ); ?><p class="description">Можно переопределить на конкретном списке классом: <code>ul-skin-cards</code>, <code>ul-skin-glass</code>, <code>ul-skin-outline</code>.</p></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-layout"></span> Layout</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Card Shadow</label><?php $this->text( 'ul_li_shadow', '0 4px 16px rgba(0,0,0,0.08)' ); ?></div>
									<div class="wpbb-field"><label>Max Container Width</label><?php $this->text( 'ul_max_width', '100%' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Card Max Width (px)</label><?php $this->text( 'ul_card_max_width', '480' ); ?></div>
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'ul_border_radius', '12' ); ?></div>
								</div>
								<div class="wpbb-field"><?php $this->checkbox( 'ul_show_numbers', 'Show decorative numbers (01, 02…)' ); ?></div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Numbers Size (px)</label><?php $this->text( 'ul_numbers_size', '32' ); ?></div>
									<div class="wpbb-field"><label>Numbers Color</label><?php $this->color( 'ul_numbers_color' ); ?></div>
									<div class="wpbb-field"><label>Numbers Weight</label><?php $this->select( 'ul_numbers_weight', [ '400' => 'Normal', '600' => 'Semi-Bold', '700' => 'Bold', '900' => 'Black' ] ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Numbers Opacity (0..1)</label><?php $this->text( 'ul_numbers_opacity', '0.2' ); ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ TABLES ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-tables">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'editor-table', 'Tables', 'table_enabled' ); ?>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Header Background</label><?php $this->color( 'table_header_bg' ); ?></div>
									<div class="wpbb-field"><label>Header Text Color</label><?php $this->color( 'table_header_color' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Zebra Row Color 1</label><?php $this->color( 'table_row_color1' ); ?></div>
									<div class="wpbb-field"><label>Zebra Row Color 2</label><?php $this->color( 'table_row_color2' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Row Hover Color</label><?php $this->color( 'table_row_hover' ); ?></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-layout"></span> Layout & Shadows</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Max Width</label><?php $this->text( 'table_max_width', '100%' ); ?></div>
									<div class="wpbb-field"><label>Font Size (rem)</label><?php $this->text( 'table_font_size', '1' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Border Width (px)</label><?php $this->text( 'table_border_width', '0' ); ?></div>
									<div class="wpbb-field"><label>Border Color</label><?php $this->color( 'table_border_color' ); ?></div>
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'table_border_radius', '12' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Shadow</label><?php $this->text( 'table_shadow', '0 4px 24px rgba(0,0,0,0.08)' ); ?></div>
								<div class="wpbb-field"><label>Shadow Hover</label><?php $this->text( 'table_shadow_hover', '0 8px 32px rgba(0,0,0,0.14)' ); ?></div>
								<div class="wpbb-admin-preview" id="wpbb-table-preview">
									<p class="wpbb-admin-preview-title">Live Preview</p>
									<table>
										<thead><tr><th>Feature</th><th>Value</th></tr></thead>
										<tbody>
											<tr><td>CPU</td><td>A17 Pro</td></tr>
											<tr><td>Battery</td><td>4300 mAh</td></tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ LEAD PARAGRAPH ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-lead">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'editor-paragraph', 'Lead Paragraph', 'lead_enabled' ); ?>
								<div class="wpbb-field"><label>Hero Skin (Overrides custom settings)</label><?php $this->select( 'lead_hero_skin', [ 'none' => 'None (Custom Settings)', 'aurora' => 'Aurora (Gradient Mesh)', 'glass' => 'Glass (Glassmorphism)', 'editorial' => 'Editorial (Dark Premium)' ] ); ?><p class="description">If active, wraps the lead paragraph into a full-width Hero section.</p></div>
								<div class="wpbb-admin-preview" style="margin-top: 10px; margin-bottom: 20px;">
									<p class="wpbb-admin-preview-title">Skins Preview</p>
									<style>
									.wpbb-hero-previews{display:flex;gap:12px;flex-wrap:wrap;}
									.wpbb-hero-thumb{flex:1;min-width:140px;border-radius:16px;padding:22px 16px 16px;text-align:center;cursor:default;position:relative;overflow:hidden;}
									.wpbb-hero-thumb-name{font-size:14px;font-weight:700;margin-bottom:4px;position:relative;z-index:1;}
									.wpbb-hero-thumb-desc{font-size:11px;opacity:0.7;line-height:1.3;position:relative;z-index:1;}
									.wpbb-hero-thumb.is-aurora{background:linear-gradient(135deg,#667eea 0%,#764ba2 40%,#f093fb 70%,#f5576c 100%);color:#fff;box-shadow:0 8px 32px rgba(102,126,234,0.35);}
									.wpbb-hero-thumb.is-aurora::before{content:'';position:absolute;top:-30%;right:-20%;width:80px;height:80px;background:rgba(255,255,255,0.15);border-radius:50%;filter:blur(20px);}
									.wpbb-hero-thumb.is-glass{background:linear-gradient(145deg,rgba(255,255,255,0.75) 0%,rgba(255,255,255,0.45) 100%);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);color:#1d1d1f;box-shadow:0 8px 32px rgba(0,0,0,0.08),inset 0 1px 0 rgba(255,255,255,0.9);border:1px solid rgba(255,255,255,0.6);}
									.wpbb-hero-thumb.is-editorial{background:linear-gradient(160deg,#0a0a1a 0%,#1a1a3e 50%,#2d1b69 100%);color:#f0eeff;box-shadow:0 8px 32px rgba(0,0,0,0.3),inset 0 1px 0 rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
									.wpbb-hero-thumb.is-editorial::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:radial-gradient(circle at 70% 30%,rgba(139,92,246,0.15) 0%,transparent 60%);}
									</style>
									<div class="wpbb-hero-previews">
										<div class="wpbb-hero-thumb is-aurora"><div class="wpbb-hero-thumb-name">Aurora</div><div class="wpbb-hero-thumb-desc">Gradient mesh + orbs</div></div>
										<div class="wpbb-hero-thumb is-glass"><div class="wpbb-hero-thumb-name">Glass</div><div class="wpbb-hero-thumb-desc">Frosted blur + glow</div></div>
										<div class="wpbb-hero-thumb is-editorial"><div class="wpbb-hero-thumb-name">Editorial</div><div class="wpbb-hero-thumb-desc">Dark + accent glow</div></div>
									</div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Font Size (rem)</label><?php $this->text( 'lead_font_size', '1.25' ); ?></div>
									<div class="wpbb-field"><label>Max Width</label><?php $this->text( 'lead_max_width', '100%' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Background Color</label><?php $this->color( 'lead_bg_color' ); ?></div>
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'lead_border_radius', '0' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Padding Top (px)</label><?php $this->text( 'lead_padding_top', '0' ); ?></div>
									<div class="wpbb-field"><label>Padding Bottom (px)</label><?php $this->text( 'lead_padding_bottom', '0' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Padding Left (px)</label><?php $this->text( 'lead_padding_left', '0' ); ?></div>
									<div class="wpbb-field"><label>Padding Right (px)</label><?php $this->text( 'lead_padding_right', '0' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Shadow</label><?php $this->text( 'lead_shadow', 'none' ); ?></div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-editor-textcolor"></span> Drop Cap (First Letter)</h3>
								<div class="wpbb-field"><?php $this->checkbox( 'lead_drop_cap', 'Enable Drop Cap' ); ?></div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Letter Size (rem)</label><?php $this->text( 'lead_drop_cap_size', '3.5' ); ?></div>
									<div class="wpbb-field"><label>Letter Color</label><?php $this->color( 'lead_drop_cap_color' ); ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ HEADING DECOR ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-headings">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'heading', 'Heading Decor', 'hdecor_enabled' ); ?>
								<div class="wpbb-field"><label>Heading Tags (comma-separated)</label><?php $this->text( 'hdecor_tags', 'h2,h3' ); ?><p class="description">E.g.: <code>h2,h3,h4</code></p></div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Shape</label><?php $this->select( 'hdecor_shape', [ 'line' => 'Line', 'square' => 'Square', 'icon' => 'Icon (Dashicon)' ] ); ?></div>
									<div class="wpbb-field"><label>Position</label><?php $this->select( 'hdecor_position', [ 'left' => 'Left', 'bottom' => 'Bottom', 'icon' => 'As Icon' ] ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Size/Height (px)</label><?php $this->text( 'hdecor_size', '4' ); ?></div>
									<div class="wpbb-field"><label>Line Length (px/%)</label><?php $this->text( 'hdecor_line_length', '40' ); ?></div>
									<div class="wpbb-field"><label>Decor Color</label><?php $this->color( 'hdecor_color' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Dashicon Name</label><?php $this->text( 'hdecor_icon', 'dashicons-star-filled' ); ?><p class="description">Use <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">Dashicons</a> name.</p></div>
									<div class="wpbb-field"><label>Icon Color</label><?php $this->color( 'hdecor_icon_color' ); ?></div>
									<div class="wpbb-field"><label>Icon Background</label><?php $this->color( 'hdecor_icon_bg' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Icon Size (px)</label><?php $this->text( 'hdecor_icon_size', '32' ); ?></div>
									<div class="wpbb-field"><label>Icon Border Radius (px)</label><?php $this->text( 'hdecor_border_radius', '100' ); ?></div>
								</div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-editor-textcolor"></span> Word Highlight</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Number of words to highlight</label><?php $this->text( 'hdecor_highlight_words', '0' ); ?><p class="description">0 = off. E.g. <code>2</code> highlights first 2 words.</p></div>
									<div class="wpbb-field"><label>Highlight Color</label><?php $this->color( 'hdecor_highlight_color' ); ?></div>
								</div>
								<div class="wpbb-admin-preview" id="wpbb-heading-preview">
									<p class="wpbb-admin-preview-title">Live Preview</p>
									<div class="wpbb-preview-heading shape-line">
										<span class="wpbb-preview-icon dashicons dashicons-star-filled"></span>
										<span>Heading Preview</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ IMAGES ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-images">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'format-image', 'Images (figure)', 'img_enabled' ); ?>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'img_border_radius', '12' ); ?></div>
									<div class="wpbb-field"><label>Border Width (px)</label><?php $this->text( 'img_border_width', '0' ); ?></div>
								</div>
								<div class="wpbb-field"><label>Shadow</label><?php $this->text( 'img_shadow', '0 4px 20px rgba(0,0,0,0.08)' ); ?></div>
								<div class="wpbb-field"><label>Shadow Hover</label><?php $this->text( 'img_shadow_hover', '0 8px 30px rgba(0,0,0,0.15)' ); ?></div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Margin Top (rem)</label><?php $this->text( 'img_margin_top', '1.5' ); ?></div>
									<div class="wpbb-field"><label>Margin Bottom (rem)</label><?php $this->text( 'img_margin_bottom', '1.5' ); ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ GALLERY ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-gallery">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'format-gallery', 'Gallery → Carousel', 'gallery_enabled' ); ?>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Columns Desktop</label><?php $this->text( 'gallery_cols_desktop', '3' ); ?></div>
									<div class="wpbb-field"><label>Columns Mobile</label><?php $this->text( 'gallery_cols_mobile', '1' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><?php $this->checkbox( 'gallery_show_arrows', 'Show Arrows' ); ?></div>
									<div class="wpbb-field"><?php $this->checkbox( 'gallery_show_dots', 'Show Dots' ); ?></div>
								</div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-art"></span> Appearance</h3>
								<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'gallery_border_radius', '12' ); ?></div>
								<div class="wpbb-field"><label>Shadow</label><?php $this->text( 'gallery_shadow', '0 4px 20px rgba(0,0,0,0.08)' ); ?></div>
								<div class="wpbb-field"><label>Shadow Hover</label><?php $this->text( 'gallery_shadow_hover', '0 8px 30px rgba(0,0,0,0.15)' ); ?></div>
							</div>
						</div>
					</div>

					<!-- ═══ LATEST POSTS ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-latest">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'admin-post', 'Latest Posts', 'latest_enabled' ); ?>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'latest_border_radius', '16' ); ?></div>
									<div class="wpbb-field"><label>Hover Effect</label><?php $this->select( 'latest_hover_effect', [ 'lift' => 'Lift Up', 'scale' => 'Scale' ] ); ?></div>
								</div>
								<div class="wpbb-field"><label>Shadow</label><?php $this->text( 'latest_shadow', '0 4px 20px rgba(0,0,0,0.08)' ); ?></div>
								<div class="wpbb-field"><label>Shadow Hover</label><?php $this->text( 'latest_shadow_hover', '0 12px 36px rgba(0,0,0,0.16)' ); ?></div>
							</div>
						</div>
					</div>

					<!-- ═══ ALTERNATING SECTIONS ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-alternating">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'columns', 'Alternating Sections', 'alt_enabled' ); ?>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Odd Background</label><?php $this->color( 'alt_odd_bg' ); ?></div>
									<div class="wpbb-field"><label>Odd Text Color</label><?php $this->color( 'alt_odd_color' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Even Background</label><?php $this->color( 'alt_even_bg' ); ?></div>
									<div class="wpbb-field"><label>Even Text Color</label><?php $this->color( 'alt_even_color' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Layout Style</label><?php $this->select( 'alt_layout', [ 'boxed' => 'Boxed (In Container)', 'full' => 'Full Width (Breakout)' ] ); ?></div>
									<div class="wpbb-field"><label>Border Radius (px)</label><?php $this->text( 'alt_border_radius', '12' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Padding Top (rem)</label><?php $this->text( 'alt_padding_top', '3' ); ?></div>
									<div class="wpbb-field"><label>Padding Bottom (rem)</label><?php $this->text( 'alt_padding_bottom', '3' ); ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- ═══ PROS / CONS ═══ -->
					<div class="wpbb-tab-panel" id="wpbb-tab-proscons">
						<div class="wpbb-admin-grid">
							<div class="wpbb-card">
								<?php $this->section_header( 'yes-alt', 'Pros / Cons Columns (col-pros-cons)', 'pc_enabled' ); ?>
								<div class="wpbb-field"><label>Default Skin</label><?php $this->select( 'pc_default_skin', [ 'soft' => 'Soft (cards + big corner icon)', 'goodbad' => 'Good/Bad (big caps + tag)', 'editorial' => 'Editorial (gradient backdrop)', 'minimal' => 'Minimal' ] ); ?><p class="description">Можно переопределить на конкретном блоке классом: <code>pc-skin-soft</code>, <code>pc-skin-goodbad</code>, <code>pc-skin-editorial</code>, <code>pc-skin-minimal</code>.</p></div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Pros Background</label><?php $this->color( 'pc_pros_bg' ); ?></div>
									<div class="wpbb-field"><label>Cons Background</label><?php $this->color( 'pc_cons_bg' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Title Color</label><?php $this->color( 'pc_title_color' ); ?></div>
									<div class="wpbb-field"><label>Pros Title Background</label><?php $this->color( 'pc_pros_title_bg' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Cons Title Background</label><?php $this->color( 'pc_cons_title_bg' ); ?></div>
									<div class="wpbb-field"></div>
								</div>
							</div>
							<div class="wpbb-card">
								<h3><span class="dashicons dashicons-admin-customizer"></span> Icons</h3>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Pros Icon</label><?php $this->select( 'pc_pros_icon', [ 'check' => 'Check', 'plus' => 'Plus' ] ); ?></div>
									<div class="wpbb-field"><label>Cons Icon</label><?php $this->select( 'pc_cons_icon', [ 'cross' => 'Cross', 'minus' => 'Minus' ] ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Pros Icon Color</label><?php $this->color( 'pc_pros_icon_color' ); ?></div>
									<div class="wpbb-field"><label>Cons Icon Color</label><?php $this->color( 'pc_cons_icon_color' ); ?></div>
								</div>
								<h3 style="margin-top:22px;"><span class="dashicons dashicons-art"></span> Section Decor</h3>
								<div class="wpbb-field"><?php $this->checkbox( 'pc_decor_enabled', 'Enable section decor (large icon in corner)' ); ?></div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Pros Decor Type</label><?php $this->select( 'pc_pros_decor_type', [ 'thumb-up' => 'Thumb Up', 'check' => 'Check', 'plus' => 'Plus', 'star' => 'Star', 'spark' => 'Spark' ] ); ?></div>
									<div class="wpbb-field"><label>Cons Decor Type</label><?php $this->select( 'pc_cons_decor_type', [ 'thumb-down' => 'Thumb Down', 'cross' => 'Cross', 'minus' => 'Minus', 'alert' => 'Alert', 'ban' => 'Ban' ] ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Pros Decor Color</label><?php $this->color( 'pc_pros_decor_color' ); ?></div>
									<div class="wpbb-field"><label>Cons Decor Color</label><?php $this->color( 'pc_cons_decor_color' ); ?></div>
								</div>
								<div class="wpbb-field-group">
									<div class="wpbb-field"><label>Decor Opacity (0..1)</label><?php $this->text( 'pc_decor_opacity', '0.10' ); ?></div>
									<div class="wpbb-field"><label>Decor Size (px)</label><?php $this->text( 'pc_decor_size', '160' ); ?></div>
								</div>
								<p class="description">Section activates when Gutenberg columns wrapper has class <code>col-pros-cons</code>. Title can be <code>p</code>, <code>h3</code> or <code>h4</code>. UL styling from the UL tab is skipped inside this section.</p>
								<div class="wpbb-admin-preview" id="wpbb-proscons-preview">
									<p class="wpbb-admin-preview-title">Live Preview</p>
									<div class="wpbb-preview-pc-wrap">
										<div class="wpbb-preview-pc-col wpbb-preview-pros">
											<div class="wpbb-preview-title-pill">Pros</div>
											<ul class="wpbb-preview-list">
												<li><span class="wpbb-preview-item-icon">+</span> Fast performance</li>
												<li><span class="wpbb-preview-item-icon">+</span> Better UX</li>
											</ul>
										</div>
										<div class="wpbb-preview-pc-col wpbb-preview-cons">
											<div class="wpbb-preview-title-pill">Cons</div>
											<ul class="wpbb-preview-list">
												<li><span class="wpbb-preview-item-icon">-</span> Premium price</li>
												<li><span class="wpbb-preview-item-icon">-</span> Learning curve</li>
											</ul>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div> <!-- .wpbb-tabs-content -->

				<div class="wpbb-fixed-footer">
					<?php submit_button( 'Save Changes', 'primary large' ); ?>
				</div>
			</form>

			<!-- Tools: Backup / Restore / Reset (separate forms — must be outside the settings form) -->
			<div class="wpbb-tools-bar">
				<div class="wpbb-card">
					<h3><span class="dashicons dashicons-backup"></span> Backup &amp; Restore</h3>
					<div class="wpbb-tools-row">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpbb-tool-form">
							<input type="hidden" name="action" value="wpbb_export">
							<?php wp_nonce_field( 'wpbb_export' ); ?>
							<button type="submit" class="button">Export settings (.json)</button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpbb-tool-form" onsubmit="return confirm('Reset ALL settings to defaults? This cannot be undone.');">
							<input type="hidden" name="action" value="wpbb_reset">
							<?php wp_nonce_field( 'wpbb_reset' ); ?>
							<button type="submit" class="button button-link-delete">Reset to defaults</button>
						</form>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpbb-tool-form-import">
						<input type="hidden" name="action" value="wpbb_import">
						<?php wp_nonce_field( 'wpbb_import' ); ?>
						<label>Import settings (paste exported JSON)</label>
						<textarea name="wpbb_import_json" rows="4" placeholder='{ "primary_color": "#2b5a9e", ... }'></textarea>
						<button type="submit" class="button">Import settings</button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
