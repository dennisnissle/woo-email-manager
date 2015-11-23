<?php
/**
 * Plugin Name: Woo Email Manager
 * Plugin URI: https://wordpress.org/plugins/woo-emails-manager
 * Description: Manage WooCommerce email texts and set BCC email addresses for every WooCommerce template
 * Version: 1.0.0
 * Author: Vendidero
 * Author URI: https://vendidero.de
 * Requires at least: 3.8
 * Tested up to: 4.2
 *
 * Text Domain: woo-email-manager
 * Domain Path: /languages/
 *
 * @author Vendidero
 */
if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

if ( ! class_exists( 'Woo_Email_Manager' ) ) :

final class Woo_Email_Manager {

	/**
	 * Current WooCommerce Emails BCC Version
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Single instance of WooCommerce Emails BCC Main Class
	 *
	 * @var object
	 */
	protected static $_instance = null;

	/**
	 * Main WooCommerce Emails BCC Instance
	 *
	 * Ensures that only one instance of WooCommerce Emails BCC is loaded or can be loaded.
	 *
	 * @static
	 * @see WC_emails_bcc()
	 * @return WooCommerce_Emails_BCC - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woo-email-manager' ), '1.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woo-email-manager' ), '1.0' );
	}

	/**
	 * Global getter
	 *
	 * @param string  $key
	 * @return mixed
	 */
	public function __get( $key ) {
		return self::$key;
	}

	/**
	 * adds some initialization hooks and inits WooCommerce Emails BCC
	 */
	public function __construct() {

		// Define constants
		$this->define_constants();

		$this->includes();

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'woocommerce_email', array( $this, 'init_email_fields' ), 10, 1 );
		add_filter( 'woocommerce_email_headers', array( $this, 'set_email_headers' ), 10, 3 );
		add_action( 'woocommerce_before_template_part', array( $this, 'set_filters' ), 100, 4 );
		add_action( 'woocommerce_email_settings_after', array( $this, 'delete_transient_button' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'rescan_email_strings' ) );

	}

	public function rescan_email_strings() {

		if( isset( $_GET[ 'rescan-email' ] ) && isset( $_GET[ '_wpnonce' ] ) && check_admin_referer( 'woo-email-manager-rescan-emails' ) ) {
			
			$mail = $this->get_email_instance_by_id( wc_clean( $_GET[ 'rescan-email' ] ) );
			
			if ( ! $mail )
				return;

			delete_transient( 'woo_email_manager_text_' . $mail->id );
			
		}

	}

	public function delete_transient_button( $mail ) {

		?>

			<p><?php _e( 'Please mind that the number of parameters (e.g. %s or %d) and their order included in the default texts has to match your translations - otherwise you will face errors.', 'woo-email-manager' ); ?></p>

			<a class="button button-secondary" href="<?php echo wp_nonce_url( add_query_arg( array( 'rescan-email' => $mail->id ) ), 'woo-email-manager-rescan-emails' ); ?>"><?php _e( 'Rescan Email Strings', 'woo-email-manager' ); ?></a>

		<?php

	}

	public function set_filters( $template_name, $template_path, $located, $args ) {

		if ( strpos( $template_name, 'email' ) !== false && ! in_array( $template_name, array( 'emails/email-header.php', 'emails/email-footer.php' ) ) ) {

			$mail = $this->get_email_instance_by_tpl( array( $template_name ) );
			$GLOBALS[ 'woo_email_manager_current_mail' ] = $mail;
			add_filter( 'gettext', array( $this, 'set_localization' ), 10, 3 );
		}

	}

	public function set_localization( $translated, $original, $domain ) {

		if ( ! isset( $GLOBALS[ 'woo_email_manager_current_mail' ] ) || ! is_object( $GLOBALS[ 'woo_email_manager_current_mail' ] ) )
			return $translated;

		$mail = $GLOBALS[ 'woo_email_manager_current_mail' ];

		$text_data = $this->get_text_data( $mail );
		
		if ( ! empty( $text_data ) ) {

			foreach ( $text_data as $text ) {

				if ( $domain === $text[ 'textdomain' ] && $mail->get_option( 'text_' . md5( $original ) ) ) {
					$translated = $mail->get_option( 'text_' . md5( $original ) );
				}

			}

		}

		return $translated;

	}

	public function set_email_headers( $headers, $id, $object ) {

		$mail = $this->get_email_instance_by_id( $id );

		if ( ! $mail || ! is_object( $mail ) )
			return $headers;

		$bcc = $mail->get_option( 'bcc' );
		
		if ( $bcc && ! empty( $bcc ) )
			$headers .= "Bcc: " . trim( $bcc ) . "\r\n";
	
		return $headers;

	}	

	public function init_email_fields( $mailer ) {		
		
		$mails = $mailer->get_emails();
		
		foreach ( $mails as $mail ) {

			$gettexts = $this->get_text_data( $mail );

			$mail->form_fields[ 'bcc' ] = array(
				'title'         => __( 'BCC', 'woo-email-manager' ),
				'type'          => 'text',
				'description'   => __( 'Seperate multiple email addresses by comma', 'woo-email-manager' ),
				'placeholder'   => '',
				'default'       => ''
			);

			$count = 0;

			if ( ! empty( $gettexts ) ) {

				foreach ( $gettexts as $gettext ) {

					$mail->form_fields[ 'text_' . md5( $gettext[ 'text' ] ) ] = array(
						'title'         => sprintf( __( 'Text %d', 'woo-email-manager' ), ++$count ),
						'type'          => 'textarea',
						'description'   => sprintf( __( 'Default: %s', 'woo-email-manager' ), ( $gettext[ 'specified' ] ? _x( $gettext[ 'text' ], $gettext[ 'specified' ], $gettext[ 'textdomain' ] ) : __( $gettext[ 'text' ], $gettext[ 'textdomain' ] ) ) ),
						'placeholder'   => '',
						'default'       => ''
					);

				}
			}
		}
	}

	public function get_text_data( $mail ) {
		
		if ( $text_data = get_transient( 'woo_email_manager_text_' . $mail->id ) )
			return $text_data;

		$template = $mail->template_html;
		
		if ( $mail->get_option( 'type' ) === 'plain' )
			$template = $mail->template_plain;

		$template_file = wc_locate_template( $template );

		// Parse text in email
		$file = fopen( $template_file, 'r' );
		$content = '';

		if ( $file )
			$content = fread( $file, filesize( $template_file ) );

		$gettexts = array();

		if ( ! empty( $content ) ) {
			
			preg_match_all( '/_(_|e|x)\(.*,.?(\'|\").*(\'|\").?\)/', $content, $matches );

			if ( ! empty( $matches[0] ) ) {

				foreach ( $matches[0] as $string ) {
					
					$string = str_replace( '"', "'", $string );

					$items = explode( "'", $string );

					if ( ! isset( $items[3] ) )
						continue;

					$gettexts[] = array( 
						'text' => trim( $items[1] ), 
						'textdomain' => ( strpos( $string, '_x' ) !== false && isset( $items[5] ) ? trim( $items[5] ) : trim( $items[3] ) ), 
						'specified' => ( strpos( $string, '_x' ) !== false && isset( $items[3] ) ? $items[3] : false )
					);

				}

			}

		}

		set_transient( 'woo_email_manager_text_' . $mail->id, $gettexts, WEEK_IN_SECONDS );

		return $gettexts;

	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get the language path
	 *
	 * @return string
	 */
	public function language_path() {
		return $this->plugin_path() . '/i18n/languages';
	}

	/**
	 * Define WC_Germanized Constants
	 */
	private function define_constants() {
		define( 'WOO_EMAIL_MANAGER_PLUGIN_FILE', __FILE__ );
		define( 'WOO_EMAIL_MANAGER_VERSION', $this->version );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	private function includes() {

	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Frontend/global Locales found in:
	 * 		- WP_LANG_DIR/woo-email-manager/woo-email-manager-LOCALE.mo
	 * 	 	- woo-email-manager/languages/woo-email-manager-LOCALE.mo (which if not found falls back to:)
	 * 	 	- WP_LANG_DIR/plugins/woo-email-manager-LOCALE.mo
	 */
	public function load_plugin_textdomain() {
		$domain = 'woo-email-manager';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	private function get_email_instance_by_id( $id ) {

		$mailer = WC()->mailer();
		$mails = $mailer->get_emails();
		
		foreach ( $mails as $mail ) {
			if ( $id === $mail->id )
				return $mail;
		}
		
		return false;
	}

	private function get_email_instance_by_tpl( $tpls = array() ) {
		
		$found_mails = array();
		
		foreach ( $tpls as $tpl ) {
		
			$tpl = apply_filters( 'woo_email_manager_email_template_name',  str_replace( array( 'admin-', '-' ), array( '', '_' ), basename( $tpl, '.php' ) ), $tpl );
			$mails = WC()->mailer()->get_emails();
		
			if ( ! empty( $mails ) ) {
		
				foreach ( $mails as $mail ) {
		
					if ( $mail->id == $tpl )
						array_push( $found_mails, $mail );
		
				}
			}
		}

		if ( ! empty( $found_mails ) )
			return $found_mails[ sizeof( $found_mails ) - 1 ];

		return null;
	}

}

endif;

/**
 * Returns the global instance of WooCommerce Emails BCC
 */
function woo_email_manager() {
	return Woo_Email_Manager::instance();
}

$GLOBALS['woo_email_manager'] = woo_email_manager();
?>