<?php

/**
 * Class ML_Slack_Admin
 */
class ML_Slack_Admin {
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;
	static $slug = 'ml_admin';
	static $prefix = 'ml_admin_';
	static $option = 'ml_slack_options';

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
// This page will be under "Settings"
		add_options_page(
			'ML Slack Settings',
			'ML Slack Admin',
			'manage_options',
			static::$slug,
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		// Set class property
		$this->options = get_option( static::$option );
		?>
		<div class="wrap">
			<h1>My Settings</h1>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields( static::$prefix . 'option_group' );
				do_settings_sections( static::$slug );
				submit_button();
				?>
			</form>
			<script type="text/javascript">
				(function( $ ) {
					$.toggleShowPassword = function( options ) {
						var settings = $.extend( {
							field: "#password",
						}, options );

						var field = $( settings.field );
						var control = $( field ).next( '.showhide' );

						control.bind( 'click', function() {
							if ( control.is( ':checked' ) ) {
								field.attr( 'type', 'text' );
							} else {
								field.attr( 'type', 'password' );
							}
						} )
					};

					$.toggleShowPassword( {
						field: '#slack_login_pass'
					} );

					$.toggleShowPassword( {
						field: '#slack_mojibot_token'
					} );

					$.toggleShowPassword( {
						field: '#google_api_key'
					} );
				}( jQuery ));

			</script>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			static::$prefix . 'option_group', // Option group
			static::$option, // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		/**
		 * Slack settings section
		 */
		add_settings_section(
			'slack_instance', // ID
			'Slack Instance Details', // Title
			array( $this, 'print_section_info' ), // Callback
			static::$slug // Page
		);

		add_settings_field(
			'slack_domain', // ID
			'Slack Domain', // Title
			array( $this, 'slack_domain_callback' ), // Callback
			static::$slug, // Page
			'slack_instance' // Section
		);

		add_settings_field(
			'slack_login_email', // ID
			'Slack Login: Email', // Title
			array( $this, 'slack_login_email_callback' ), // Callback
			static::$slug, // Page
			'slack_instance' // Section
		);

		add_settings_field(
			'slack_login_pass',
			'Slack Login: Pass',
			array( $this, 'slack_login_pass_callback' ),
			static::$slug,
			'slack_instance'
		);

		add_settings_field(
			'slack_mojibot_token',
			'Slack Moijibot Token',
			array( $this, 'slack_mojibot_token_callback' ),
			static::$slug,
			'slack_instance'
		);

		/**
		 * Google Settings
		 */
		add_settings_section(
			'google_details', // ID
			'Google Details', // Title
			array( $this, 'print_section_info' ), // Callback
			static::$slug // Page
		);

		add_settings_field(
			'google_search_cx', // ID
			'Google Search CX ID', // Title
			array( $this, 'google_search_cx_callback' ), // Callback
			static::$slug, // Page
			'google_details' // Section
		);

		add_settings_field(
			'google_api_key', // ID
			'Google API Key', // Title
			array( $this, 'google_api_key_callback' ), // Callback
			static::$slug, // Page
			'google_details' // Section
		);
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = array();

		if ( isset( $input['slack_domain'] ) ) {
			$new_input['slack_domain'] = untrailingslashit( sanitize_url( $input['slack_domain'] ) );
		}

		if ( isset( $input['slack_login_email'] ) ) {
			$new_input['slack_login_email'] = sanitize_email( $input['slack_login_email'] );
		}

		if ( isset( $input['slack_login_pass'] ) ) {
			$new_input['slack_login_pass'] = sanitize_text_field( $input['slack_login_pass'] );
		}

		if ( isset( $input['slack_mojibot_token'] ) ) {
			$new_input['slack_mojibot_token'] = sanitize_text_field( $input['slack_mojibot_token'] );
		}

		if ( isset( $input['google_search_cx'] ) ) {
			$new_input['google_search_cx'] = sanitize_text_field( $input['google_search_cx'] );
		}

		if ( isset( $input['google_api_key'] ) ) {
			$new_input['google_api_key'] = sanitize_text_field( $input['google_api_key'] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		echo 'Enter your settings below:';
	}

	/**
	 * Return a password field with show/hide checkbox
	 *
	 * @param $options
	 * @param $opt_id
	 *
	 * @return string
	 */
	static function get_password_field( $options, $opt_id ) {
		$html = sprintf(
			'<input type="password" id="' . $opt_id . '" name="' . static::$option . '[' . $opt_id . ']" value="%s" />',
			isset( $options[ $opt_id ] ) ? esc_attr( $options[ $opt_id ] ) : ''
		);
		$html .= '<input type="checkbox" class="showhide" />';

		return $html;
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function slack_domain_callback() {
		$opt_id = 'slack_domain';

		printf(
			'<input type="text" placeholder="%1$s" class="widefat" id="%2$s" name="%3$s[%2$s]" value="%4$s" />',
			'https://myteam.slack.com',
			$opt_id,
			static::$option,
			isset( $this->options[ $opt_id ] ) ? esc_url( $this->options[ $opt_id ] ) : ''
		);
		echo '<p class="description">Enter the full URL of the slack domain your bot will be posting to.</p>';
	}

	public function slack_login_email_callback() {
		$opt_id = 'slack_login_email';
		printf(
			'<input type="email" id="' . $opt_id . '" name="' . static::$option . '[' . $opt_id . ']" value="%s" />',
			isset( $this->options[ $opt_id ] ) ? esc_attr( $this->options[ $opt_id ] ) : ''
		);
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function slack_login_pass_callback() {
		echo static::get_password_field( $this->options, 'slack_login_pass' );
	}

	public function slack_mojibot_token_callback() {
		echo static::get_password_field( $this->options, 'slack_mojibot_token' );
		?>
		<p class="description">The token from the Mojobot\'s outgoing webhook.
			When creating the outgoing webhook, you should set the URL to
			<code style="white-space: nowrap">https://{yoursite.com}/wp-json/ml/v1/mojibot</code>*
			and the following trigger words are supported: </p>
		<pre><?php echo ML_Slack::$mojibot_trigger_words; ?></pre>
		<p class="description">* It should be noted, I believe Slack requires the site you
			use for the API to be secure (https)</p>
		<?php
	}

	public function google_search_cx_callback() {
		$opt_id = 'google_search_cx';
		printf(
			'<input type="text" id="$1%s" name="%2$s[%1$s]" value="%3$s" />',
			$opt_id,
			static::$option,
			isset( $this->options[ $opt_id ] ) ? esc_attr( $this->options[ $opt_id ] ) : ''
		);
		echo '<p class="description">The Google Search "cx" value/ID.</p>';
	}

	public function google_api_key_callback() {
		echo static::get_password_field( $this->options, 'google_api_key' );
		echo '<p class="description">The google API key with search enabled.</p>';
	}

	/**
	 * @param string $url
	 * @param string $emoji_slug
	 *
	 * @return bool|int Attachment ID
	 */
	static function insert_attachment_from_url( $url, $emoji_slug ) {

		$response = wp_safe_remote_get( $url );
		if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$upload = wp_upload_bits( basename( $url ), null, $response['body'] );
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}

		// make sure emoji file size exists
		add_image_size( 'slack_emoji', 128, 128, array( 'center', 'center' ) );

		$file_path = $upload['file'];
		$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
		$file_name = sanitize_key( $emoji_slug ) . '.' . $extension;
		$file_type = wp_check_filetype( $file_name, null );

		// TODO: modify the uploads directory so we can save all the emoji in one place
		$wp_upload_dir = wp_upload_dir();

		$post_info = array(
			'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
			'post_mime_type' => $file_type['type'],
			'post_title'     => $emoji_slug,
			'post_content'   => $url,
			'post_status'    => 'inherit',
		);

		// Create the attachment
		$attach_id = wp_insert_attachment( $post_info, $file_path, 0 );

		return $attach_id;

	}
}