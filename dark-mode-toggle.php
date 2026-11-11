<?php
/**
 * Plugin Name:       Simple Dark Mode Toggle
 * Plugin URI:        https://example.com/dark-mode-toggle
 * Description:       Adds a floating light/dark mode switch to the bottom-right corner of any website. Works on activation, no shortcode needed.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Your Agency
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dark-mode-toggle
 *
 * @package Dark_Mode_Toggle
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DMT_VERSION' ) ) {
	define( 'DMT_VERSION', '1.0.0' );
	define( 'DMT_PLUGIN_FILE', __FILE__ );
	define( 'DMT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Plugin class. Everything is prefixed with DMT / dmt to avoid conflicts.
 */
final class DMT_Plugin {

	const OPTION = 'dmt_settings';
	const GROUP  = 'dmt_settings_group';
	const SLUG   = 'dmt-settings';

	/**
	 * Singleton instance.
	 *
	 * @var DMT_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return DMT_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: register hooks.
	 */
	private function __construct() {
		add_action( 'wp_head', array( $this, 'print_head_script' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_button' ), 99 );

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DMT_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array( 'default_mode' => 'light' )
		);
	}

	/**
	 * Should the switch be shown on this request?
	 *
	 * @return bool
	 */
	private function is_enabled() {
		static $enabled = null;

		if ( null !== $enabled ) {
			return $enabled;
		}

		$enabled = true;

		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || is_robots() || is_trackback() ) {
			$enabled = false;
		}

		// Never run inside page-builder editors / previews.
		if ( $enabled ) {
			$builder_params = array( 'elementor-preview', 'et_fb', 'fl_builder', 'ct_builder', 'brizy-edit', 'vc_editable', 'bricks', 'customize_changeset_uuid' );
			foreach ( $builder_params as $param ) {
				if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check.
					$enabled = false;
					break;
				}
			}
		}

		/**
		 * Filter: enable/disable the dark mode switch for the current request.
		 *
		 * @param bool $enabled Whether the switch is enabled.
		 */
		$enabled = (bool) apply_filters( 'dmt_enabled', $enabled );

		return $enabled;
	}

	/**
	 * Tiny inline script in <head>: applies the saved mode before the page is
	 * painted, so there is no white flash in dark mode.
	 *
	 * @return void
	 */
	public function print_head_script() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = self::get_settings();
		$default  = in_array( $settings['default_mode'], array( 'light', 'dark', 'auto' ), true ) ? $settings['default_mode'] : 'light';

		$js  = '(function(){var d=document.documentElement,m="light";try{';
		$js .= 'var s=localStorage.getItem("dmt_mode");';
		$js .= 'if(s==="dark"||s==="light"){m=s;}else{var f=' . wp_json_encode( $default ) . ';';
		$js .= 'if(f==="dark"){m="dark";}else if(f==="auto"&&window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches){m="dark";}}';
		$js .= '}catch(e){}d.setAttribute("data-dmt",m);d.classList.add("dmt-js");})();';

		wp_print_inline_script_tag( $js );
	}

	/**
	 * Enqueue the stylesheet (in <head>) and script (deferred, footer).
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_enqueue_style( 'dmt-style', DMT_PLUGIN_URL . 'assets/css/dmt-style.css', array(), DMT_VERSION );

		wp_enqueue_script(
			'dmt-script',
			DMT_PLUGIN_URL . 'assets/js/dmt-script.js',
			array(),
			DMT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Output the floating button.
	 *
	 * @return void
	 */
	public function render_button() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = self::get_settings();
		$default  = in_array( $settings['default_mode'], array( 'light', 'dark', 'auto' ), true ) ? $settings['default_mode'] : 'light';
		?>
		<button type="button" id="dmt-toggle" class="dmt-toggle" aria-pressed="false"
			aria-label="<?php echo esc_attr__( 'Switch to dark mode', 'dark-mode-toggle' ); ?>"
			data-label-dark="<?php echo esc_attr__( 'Switch to dark mode', 'dark-mode-toggle' ); ?>"
			data-label-light="<?php echo esc_attr__( 'Switch to light mode', 'dark-mode-toggle' ); ?>"
			data-default="<?php echo esc_attr( $default ); ?>">
			<svg class="dmt-icon dmt-icon--moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
			<svg class="dmt-icon dmt-icon--sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg>
		</button>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Admin: Settings > Dark Mode Toggle
	 * ------------------------------------------------------------------ */

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'Dark Mode Toggle', 'dark-mode-toggle' ),
			__( 'Dark Mode Toggle', 'dark-mode-toggle' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array( 'default_mode' => 'light' ),
			)
		);
	}

	/**
	 * Sanitise settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$mode = is_array( $input ) && isset( $input['default_mode'] ) ? sanitize_key( $input['default_mode'] ) : 'light';

		return array(
			'default_mode' => in_array( $mode, array( 'light', 'dark', 'auto' ), true ) ? $mode : 'light',
		);
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Settings', 'dark-mode-toggle' ) . '</a>' );

		return $links;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dark-mode-toggle' ), '', array( 'response' => 403 ) );
		}

		$settings = self::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Dark Mode Toggle', 'dark-mode-toggle' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dmt_default_mode"><?php esc_html_e( 'Default mode for new visitors', 'dark-mode-toggle' ); ?></label></th>
						<td>
							<select id="dmt_default_mode" name="<?php echo esc_attr( self::OPTION ); ?>[default_mode]">
								<option value="light" <?php selected( $settings['default_mode'], 'light' ); ?>><?php esc_html_e( 'Light', 'dark-mode-toggle' ); ?></option>
								<option value="dark" <?php selected( $settings['default_mode'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'dark-mode-toggle' ); ?></option>
								<option value="auto" <?php selected( $settings['default_mode'], 'auto' ); ?>><?php esc_html_e( 'Follow the visitor\'s device setting', 'dark-mode-toggle' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'A visitor\'s own choice is remembered in their browser and always wins over this default.', 'dark-mode-toggle' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Uninstall: remove the option.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( self::OPTION );
	}
}

register_uninstall_hook( __FILE__, array( 'DMT_Plugin', 'uninstall' ) );

DMT_Plugin::instance();
