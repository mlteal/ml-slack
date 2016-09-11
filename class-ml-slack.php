<?php

/**
 * Class ML_Slack
 */
class ML_Slack {

	static $api_namespace = 'ml/v1';
	static $mojibot_trigger_words = 'emojify,hey mojibot,mojibot,yo mojibot';

	static function register_rest_route() {
		register_rest_route( static::$api_namespace, '/mojibot', array(
			'methods'  => 'POST',
			'callback' => array( 'ML_Slack', 'mojibot' ),
		) );

	}

	/**
	 * Mojibot endpoint functions
	 *
	 * @return array
	 */
	static function mojibot() {

		// default text
		$content        = 'these aren\'t the droids you are looking for :wave:';
		$slack_settings = get_option( ML_Slack_Admin::$option );

		if (
			! empty( $_REQUEST['token'] )
			&& ! empty( $slack_settings['slack_mojibot_token'] )
			&& ( $_REQUEST['token'] === $slack_settings['slack_mojibot_token'] )
		) {

			$content = $_REQUEST;

			// strip only the first instance of the trigger word out of the message
			$message = substr_replace( $_REQUEST['text'], '', 0, strlen( $_REQUEST['trigger_word'] ) );
			// check for any commas or dashes at the beginning of the message like "hey @mojibot,"
			// at this point I should have just done a preg replace for the whole trigger word piece and white space... oh well

			$message = preg_replace( array( '/^[,\- ]/' ), '', $message );
			// if there's a space at the beginning or end of the message still, strip that sucker out too
			$message = trim( $message );
			$message = esc_attr( $message );

			if ( 'help' == $message ) {
				ob_start();
				?>
You can emojify any image by entering the `emojify` command followed by the emoji slug and an image URL. Ex:
```emojify :nyancat: https://i.ytimg.com/vi/Jx8zYrMtdCI/maxresdefault.jpg```

The image will be auto-cropped and resized as needed before it's uploaded as an emoji.

* Emoji slugs must follow the Slack guidelines (no spaces, all lowercase, only underscores and no hyphens).
* If you already know the image is correctly cropped and sized according to Slack's guidelines,
  you can add the flag --original to make sure you don't send a processed version. This is
  especially useful for sending animated gif's.
* To do a google image search first instead, enter the command `emojify` followed by any search
  search query. You'll be presented with three image options to choose from, at which point you
  can run `emojify` again as outlined above to upload and set the emoji.
				<?php
				$content = ob_get_clean();
			} elseif ( 'emojify' == $_REQUEST['trigger_word'] && empty( $message ) ) {
				$content = 'Enter a search term or an emoji you want to add, please! For more info, type `emojify help`.';
			} elseif ( 'emojify' == $_REQUEST['trigger_word'] && 0 === strpos( $message, ':' ) ) {
				$item = explode( ' ', $message );
				$slug = sanitize_key( str_replace( array( ':', '-' ), '', $item[0] ) );
				if ( empty( $item[1] ) || false === preg_match( '/^https?:\/\//', $item[1] ) ) {
					return array(
						'text' => 'Looks like you wanted to add an emoji but something was wrong. Your message should look like 
					```emojify :nyancat: https://i.ytimg.com/vi/Jx8zYrMtdCI/maxresdefault.jpg```'
					);
				}

				// when Slack expands an image inline, it wraps it in angle brackets... gotta strip those buggers!
				$image_url = str_replace( array( '<', '>', '&lt;', '&gt;' ), '', $item[1] );

				$wp_image_atts = array(
					'title' => $slug,
					'user_name' => $_REQUEST['user_name'],
					'user_id' => $_REQUEST['user_id'],
				);

				$wp_image = static::upload_image_to_wp( $image_url, $wp_image_atts );

				// $wp_image should return an array with a `file` key that contains the file path that we need
				if ( empty( $wp_image['ID'] ) ) {
					return array(
						'text',
						'Uh oh... something went wrong when we tried to download the image. If this keeps happening, you should probably let someone know. '
					);
				}

				// now pull the correct image crop size based on the attachment ID
				$uploads = wp_upload_dir();

				// Get the image object
				$image_object = wp_get_attachment_image_src( $wp_image['ID'], array( 128, 128 ) );

				if ( false === $image_object || empty( $image_object[0] ) ) {
					return array(
						'text',
						'Uh oh, we got the image but had trouble getting it ready to send to Slack.'
					);
				}

				// Isolate the url
				$image_url = $image_object[0];

				// Using the wp_upload_dir replace the baseurl with the basedir
				$image_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $image_url );

				if ( defined( 'ML_SLACK_DEBUG' ) && true == ML_SLACK_DEBUG ) {
					// if emoji uploads are failing, it could be because the image path string replace isn't working!
					// at least this could give us some info
					error_log( var_export( array( 'slug' => $slug, 'image_path' => $image_path, 'uploads' => $uploads ), true ) );
				}

				// the add_emoji_to_slack function should return a message
				$request = static::add_emoji_to_slack( $slug, $image_path );

				/**
				 * TODO: use the channel details to post the message with resulting emoji
				 *
				 * I think the message isn't posting because it takes longer to respond
				 * than Slack allows for (_if_ the WP upload and Slack upload succeeds!)
				 */
				return array( 'text' => $request );

			} elseif ( 'emojify' == $_REQUEST['trigger_word'] ) {
				$images  = static::get_images( $message );
				$content = 'I searched for `' . $message . '` ' . $images;
			}


			// slack formatted response
			$response = array( 'text' => $content );

			return $response;
		}

		return array( 'text' => $content );
	}

	/**
	 * Performs a Google Image Search and returns the first 3 results
	 *
	 * @param $search_query
	 *
	 * @return string
	 */
	static function get_images( $search_query ) {
		$slack_settings = get_option( ML_Slack_Admin::$option );

		if (
			empty( $slack_settings['google_search_cx'] )
			|| empty( $slack_settings['google_api_key'] )
		) {
			return 'Looks like you still need to set up your Google authentication details!';
		}

		$url      = 'https://www.googleapis.com/customsearch/v1';
		$args     = array(
			'cx'         => $slack_settings['google_search_cx'],
			'key'        => $slack_settings['google_api_key'],
			'num'        => 3, // number of results to return
			'q'          => $search_query,
			'searchType' => 'image',
		);
		$response = '';

		$body = wp_remote_retrieve_body( wp_safe_remote_get( add_query_arg( $args, $url ) ) );
		$body = json_decode( $body, true );

		if ( ! empty( $body['items'] ) ) {
			foreach ( $body['items'] as $index => $item ) {
				$position = $index + 1;
				// just post the thumbnail, we'll have to just include a link to the actual image in case it's too big
				$response .= $position . '. ' . $item['image']['thumbnailLink'] . PHP_EOL . ' ```' . $item['link'] . '``` ';
			}
		} else {
			$response = 'Sorry, I got nothin :disappointed:';
		}

		// return the body of our Google API request
		return $response;
	}

	/**
	 * Log in and upload the image to Slack because they don't have an API endpoint for doing so.
	 *
	 * @param $slug
	 * @param $image_path
	 *
	 * @return string
	 */
	static function add_emoji_to_slack( $slug, $image_path ) {
		/**
		 * Ok, if things aren't already ugly enough in this function,
		 * we've got to store login info for a slack account with emoji
		 * upload privileges ¯\_(ツ)_/¯
		 *
		 * Someday perhaps Slack will give us the all-requested emoji upload
		 * capability via their API... someday! Then this ugliness will all get
		 * to be tossed into the trash!
		 *
		 * I have no clue what I'll even do for teams using 2FA
		 */

		$slack_settings = get_option( ML_Slack_Admin::$option );

		if (
			empty( $slack_settings['slack_login_email'] )
			|| empty( $slack_settings['slack_login_pass'] )
			|| empty( $slack_settings['slack_domain'] )
		) {
			return 'Looks like you still need to set up your authentication details!';
		}

		$username = $slack_settings['slack_login_email'];
		$password = $slack_settings['slack_login_pass'];
		$domain   = $slack_settings['slack_domain'];

		// load the simple html dom parser from http://simplehtmldom.sourceforge.net/
		require_once( __DIR__ . '/lib/simple_html_dom.php' );

		// login form action url
		$login_url = trailingslashit( $domain );
		$postinfo  = 'signin=1&email=' . $username . '&password=' . $password;
		$filepath  = realpath( WP_CONTENT_DIR . '/temp' );

		//
		if ( false === $filepath ) {
			// attempt to make the dir if it doesn't exist. if mkdir isn't allowed, return the whole thing as false
			if ( ! mkdir( WP_CONTENT_DIR . '/temp', 077 ) ) {
				return false;
			}

			$filepath = WP_CONTENT_DIR . '/temp';
		}


		$cookie_file_path = $filepath . '/cookie.txt';

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_NOBODY, false );
		curl_setopt( $ch, CURLOPT_URL, $login_url );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie_file_path );

		// Fun fact: Slack doesn't support Firefox
		curl_setopt( $ch, CURLOPT_USERAGENT,
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_REFERER, $_SERVER['REQUEST_URI'] );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

		// ok, first visit the login page and grab the crumb
		$html = curl_exec( $ch );
		$dom  = new simple_html_dom();
		$dom->load( $html );
		$crumb = $dom->find( '[name="crumb"]', 0 )->value;

		// then log in
		curl_setopt( $ch, CURLOPT_URL, $login_url );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postinfo . '&crumb=' . $crumb );

		curl_exec( $ch );

		// load the emoji upload screen to grab the new crumb value
		curl_setopt( $ch, CURLOPT_URL, $domain . '/customize/emoji' );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie_file_path );

		$html_emoji_screen = curl_exec( $ch );
		$dom               = new simple_html_dom();
		$dom->load( $html_emoji_screen );
		$crumb2 = $dom->find( '[name="crumb"]', 0 )->value;

		if ( empty( $crumb2 ) ) {
			return 'There was a problem getting to the emoji upload screen in Slack... :disappointed:';
		}

		// set up the post value
		$file      = $image_path;
		$curl_file = new CurlFile( $file, 'image/png' );
		$args      = array(
			'add'   => 1,
			'crumb' => $crumb2,
			'img'   => $curl_file,
			'mode'  => 'data',
			'name'  => $slug,
		);
		// run it again now that I have the newest crumb value
		curl_setopt( $ch, CURLOPT_URL, $domain . '/customize/emoji' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $args );

		$html_emoji_uploaded = curl_exec( $ch );
		curl_close( $ch );

		$dom = new simple_html_dom();
		$dom->load( $html_emoji_uploaded );
		$message = $dom->find( 'p.alert.alert_success', 0 );

		// if there's something there, consider this a success
		// TODO: It's probably better to be checking the redirect URL instead of the resulting page since they have a confirmation in there
		if ( ! empty( $message ) ) {
			return 'Your emoji has been uploaded, check it! :' . $slug . ': ';
		}


		return 'There was some sort of problem creating :' . $slug . ': (╯°□°）╯︵ ┻━┻ (...probably. if the emoji in this message appears then it worked and you got this by mistake!)';

	} // end static function add_emoji_to_slack

	/**
	 * @param       $image_url
	 * @param array $atts
	 *
	 * @return array|bool
	 */
	static function upload_image_to_wp( $image_url, $atts = array() ) {
		// make sure the correct image size/crop exists
		add_image_size( 'slackmoji', 128, 128, array( 'center', 'center' ) );

		$title     = ! empty( $atts['title'] ) ? $atts['title'] : 'unknown';
		$user_name = ! empty( $atts['user_name'] ) ? esc_attr( $atts['user_name'] ) : 'unknown';
		$user_id   = ! empty( $atts['user_id'] ) ? esc_attr( $atts['user_id'] ) : 'unknown';
		$file      = esc_url( $image_url );
		$extension = pathinfo( $file, PATHINFO_EXTENSION );
		$filename  = $title . '.' . $extension;

		$upload_file = wp_upload_bits( $filename, null, file_get_contents( $file ) );
		if ( ! $upload_file['error'] ) {
			$wp_filetype   = wp_check_filetype( $filename, null );
			$attachment    = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => 'Emoji Upload: ' . $title,
				'post_content'   => 'Uploaded by ' . $user_name . ' (ID: ' . $user_id . '). Original URL: ' . $image_url,
				'post_status'    => 'inherit'
			);
			$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'] );
			if ( ! is_wp_error( $attachment_id ) ) {
				require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
				wp_update_attachment_metadata( $attachment_id, $attachment_data );

				// add the attachment ID here because we need that too!
				$upload_file['ID'] = $attachment_id;

				return $upload_file;
			}
		}

		return false;
	}
}