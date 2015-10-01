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

	}

	public function set_email_headers( $headers, $id, $object ) {

		$mails = WC()->mailer()->get_emails();
		$mail = null;

		foreach ( $mails as $mail_instance ) {
			if ( $id === $mail_instance->id )
				$mail = $mail_instance;
		}

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

			foreach ( $gettexts as $gettext ) {

				$mail->form_fields[ 'text_' . md5( $gettext[ 'text' ] ) ] = array(
					'title'         => sprintf( __( 'Text %d', 'woo-email-manager' ), ++$count ),
					'type'          => 'textarea',
					'description'   => sprintf( __( 'Default: %s', 'woo-email-manager' ), __( $gettext[ 'text' ], $gettext[ 'textdomain' ] ) ),
					'placeholder'   => '',
					'default'       => ''
				);

			}

		}
	}

	public function get_text_data( $mail ) {
		
		if ( $text_data = get_transient( 'woo_email_manager_text_' . $mail->id ) ) {
			return $text_data;
		}

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
			preg_match_all( "/printf\(( ?)__\(.*?\)/", $content, $matches );
			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $string ) {
					$string = explode( "'", $string );
					if ( isset( $string[1] ) && isset( $string[3] ) ) {
						$gettexts[] = array( 
							'text' => trim( $string[1] ), 
							'textdomain' => trim( $string[3] ), 
						);
					}
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