<?php
/**
 * Plugin Name: WooCommerce Emails BCC
 * Plugin URI: https://wordpress.org/plugins/woocommerce-emails-bcc
 * Description: Easily receive blind copies of emails sent to your customers by defining BCC email addresses for every email template.
 * Version: 1.0.0
 * Author: Vendidero
 * Author URI: https://vendidero.de
 * Requires at least: 3.8
 * Tested up to: 4.2
 *
 * Text Domain: woocommerce-emails-bcc
 * Domain Path: /languages/
 *
 * @author Vendidero
 */
if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

if ( ! class_exists( 'WooCommerce_Emails_BCC' ) ) :

final class WooCommerce_Emails_BCC {

	/**
	 * Current WooCommerce Germanized Version
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Single instance of WooCommerce Germanized Main Class
	 *
	 * @var object
	 */
	protected static $_instance = null;

	/**
	 * Main WooCommerceGermanized Instance
	 *
	 * Ensures that only one instance of WooCommerceGermanized is loaded or can be loaded.
	 *
	 * @static
	 * @see WC_germanized()
	 * @return WooCommerceGermanized - Main instance
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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-emails-bcc' ), '1.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-emails-bcc' ), '1.0' );
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
	 * adds some initialization hooks and inits WooCommerce Germanized
	 */
	public function __construct() {

		// Define constants
		$this->define_constants();

		$this->includes();

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'woocommerce_email', array( $this, 'init_email_fields' ), 10, 1 );
		add_filter( 'woocommerce_email_headers', array( $this, 'set_email_headers' ), 10, 3 );

	}

	/**
	 * Init WooCommerceGermanized when WordPress initializes.
	 */
	public function init() {
		
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
			$mail->form_fields[ 'bcc' ] = array(
				'title'         => __( 'BCC', 'woocommerce-emails-bcc' ),
				'type'          => 'text',
				'description'   => __( 'Seperate multiple email addresses by comma', 'woocommerce-emails-bcc' ),
				'placeholder'   => '',
				'default'       => ''
			);
		}
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
		define( 'WC_EMAILS_BCC_PLUGIN_FILE', __FILE__ );
		define( 'WC_EMAILS_BCC_VERSION', $this->version );
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
	 * 		- WP_LANG_DIR/woocommerce-germanized/woocommerce-germanized-LOCALE.mo
	 * 	 	- woocommerce-germanized/i18n/languages/woocommerce-germanized-LOCALE.mo (which if not found falls back to:)
	 * 	 	- WP_LANG_DIR/plugins/woocommerce-germanized-LOCALE.mo
	 */
	public function load_plugin_textdomain() {
		$domain = 'woocommerce-emails-bcc';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

}

endif;

/**
 * Returns the global instance of WooCommerce Germanized
 */
function WC_emails_bcc() {
	return WooCommerce_Emails_BCC::instance();
}

$GLOBALS['woocommerce_emails_bcc'] = WC_emails_bcc();
?>