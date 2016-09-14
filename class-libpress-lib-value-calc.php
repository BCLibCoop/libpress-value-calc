<?php defined('ABSPATH') || die(-1);

/**
 * Plugin Name: LibPress Library Value Calculator
 * Description: Options and GravityForm for library value calculators
 * Author: Jonathan Schatz, BC Libraries Cooperative
 * Author URI: https://bc.libraries.coop
 * Version: 0.1.0
 **/

if (!class_exists('LibValueCalc')):

	class LibValueCalc {
		
		protected $slug = 'lib-value-';

		protected $fields = array();

		public function __construct() {
			$this->fieldset = array(
                (object) [ 'internal_name' => 'books', 'public_name' => 'Books Read', 'description' => 'Average print book price', 'value' => 0.00],
				(object) [ 'internal_name' => 'magazines', 'public_name' => 'Magazines Read', 'description' => 'Average retail price of a magazine', 'value' => 0.00],
				(object) [ 'internal_name' => 'dvds', 'public_name' => 'DVDs Watched', 'description' => 'Rental price of a new release DVD movie', 'value' => 0.00],
				(object) [ 'internal_name' => 'games', 'public_name' => 'Video Games Played', 'description' => 'Rental price of a new video game', 'value' => 0.00],
				(object) [ 'internal_name' => 'cds', 'public_name' => 'Music CDs Listened to', 'description' => 'Average price of a music CD from iTunes', 'value' => 0.00],
				(object) [ 'internal_name' => 'ebooks', 'public_name' => 'eBooks/Audiobooks Downloaded', 'description' => 'Average download price of a recently published eBook or audiobook', 'value' => 0.00],
				(object) [ 'internal_name' => 'holds', 'public_name' => 'Holds Placed', 'description' => 'Cost of ILL service per patron', 'value' => 0.00],
				(object) [ 'internal_name' => 'questions', 'public_name' => 'Questions Answered', 'description' => 'Cost of staffing information desk/ask service per patron', 'value' => 0.00],
				(object) [ 'internal_name' => 'computer', 'public_name' => 'Public Computer Use 60 mins/day', 'description' => 'Average price of hour of computer/internet time', 'value' => 0.00],
				(object) [ 'internal_name' => 'programs', 'public_name' => 'Programs and Classes Attended', 'description' => 'Average cost of delivering workshops and programming per patron', 'value' => 0.00],
                (object) [ 'internal_name' => 'property-tax', 'public_name' => 'Total you paid for these monthly services', 'description' => 'Monthly property tax collected from average home value in community', 'value' => 0.00],
				);

			add_action('init', array(&$this, '_init'));
            add_filter( 'gform_field_value', array( &$this, 'populate_fields'), 10, 3 );

		}

		public function _init() {

            if ( is_admin() ) {
                add_action( 'admin_enqueue_scripts', array(&$this, 'admin_enqueue_styles_scripts') );
                add_action( 'admin_menu', array(&$this, 'add_lib_calc_menu') );
                add_action( 'wp_ajax_' . $this->slug . 'save-change', array(&$this, 'lib_value_calc_save_change_callback')) ;
            } else {
                add_action( 'wp_enqueue_scripts', array(&$this, 'frontside_enqueue_styles_scripts') );
            }

            //default options if not already set
            add_option($this->slug . 'options', $this->fieldset, '', 'no'); //Store the entire initial object, no autoload

            //Log form object
            $form_id = '3';
            Kint::enabled(Kint::MODE_WHITESPACE);
            $kintOutput = d($form = GFAPI::get_form($form_id));
            $log_file = WP_CONTENT_DIR . '/kintout.log';
            file_put_contents($log_file, $kintOutput);

		}

        /**
         * Match each option to form field and provide to filter for dynamic population.
         *
         * @param string $value The string containing the current field value to be filtered.
         * @param object $field The current field being processed
         * @param string $field_name The parameter name of the field or input being processed.
         * @return mixed
         */

        public function populate_fields($value, $field, $field_name ) {
		    $results = $this->check_obj_cache( 'lib_value_calc_opts_cache' );

            $values = array();
		    foreach( $results as $fieldObj ) {
                $our_internal_name = 'lib_value_' . $fieldObj->internal_name;
                if ( $field_name === $our_internal_name ) $values[$our_internal_name] = $fieldObj->value;
            }

            return isset( $values[ $field_name ] ) ? $values[ $field_name ] : $value;
        }

        //Later use memcache?
        public function check_obj_cache( $key ) {

            $cached = wp_cache_get( $key );
            if ( false === $cached ) {
                $cached = get_option( $this->slug . 'options' );
                wp_cache_set( $key, $cached, '', 60 ); //one minute, same request
            }
            return $cached;

        }

		public function admin_enqueue_styles_scripts( $hook ) {

			if ('site-manager_page_' . $this->slug . 'options' !== $hook) {
				return;
			}

			wp_register_script($this->slug . 'calc-admin-js', plugins_url('/js/' . $this->slug . 'calc-admin.js', __FILE__), array('jquery'));
			wp_enqueue_script($this->slug . 'calc-admin-js');

		}

		public function add_lib_calc_menu() {
			add_submenu_page('site-manager', 'Library Value Calculator', 'Value Calculator Rates', 'manage_local_site', $this->slug . 'options', array(&$this, 'admin_lib_calc_settings_page'));
		}

		public function frontside_enqueue_styles_scripts( $hook ) {
		    wp_register_style( $this->slug . 'form-front-css', plugins_url( '/css/' . $this->slug . 'form-front.css', __FILE__ ), false );
            wp_enqueue_style( $this->slug . 'form-front-css' );
        }

		public function lib_value_calc_save_change_callback() {

			$optionsArray = array();

			foreach ( $this->fieldset as $fieldObj ) {
				if ( isset($_POST[$this->slug . $fieldObj->internal_name]) ) {
					$fieldObj->value = (float) sanitize_text_field($_POST[$this->slug . $fieldObj->internal_name]); //updated values from form
				}
				$optionsArray[] = $fieldObj; //the new object array for option storage
			}

			update_option($this->slug . 'options', $optionsArray);

			echo '{"result":"success","feedback":"Saved"}';
			die();
		}

		/**
		 *	Store value field as options
		 *	per Co-op client library
		 *
		 **/
		public function admin_lib_calc_settings_page() {

			if (!current_user_can('manage_local_site')) {
				die('You do not have required permissions to view this page');
			}

			$out = array();
			$out[] = '<div class="wrap">';

			$out[] = '<div id="icon-options-general" class="icon32">';
			$out[] = '<br>';
			$out[] = '</div>';

			$out[] = '<h2>Library Value Rates</h2>';

			$out[] = '<table class="form-table">';

			$stored = get_option( $this->slug . 'options' );

			foreach ($stored as $obj) {

                $name = (string) $obj->internal_name;
                $public = (string) $obj->public_name;
                $value = (isset($obj->value)) ? $obj->value: '0.00';
                $description = $obj->description;

				$out[] = '<tr valign="top">';
				$out[] = '<th scope="row">';
                $out[] = '<label for="' . $this->slug . $name . '">' . ucfirst( $name ) . '</label>';
                //$out[] = '<p class="' . $this->slug . $name . '-public' . '">' . ucfirst( $public ) . '</p>';
				$out[] = '</th>';
				$out[] = '<td>';
				$out[] = '$ <input type="text" id="' . $this->slug . $name . '" name="' . $this->slug . $name . '"  value="' . $value . '">';
                $out[] = '<legend><em>'.$description.'</em></legend>';
				$out[] = '</td>';
				$out[] = '</tr>';
			}

			$out[] = '</table>';

			$out[] = '<p class="submit">';
			$out[] = '<input type="submit" value="Save Changes" class="button button-primary" id="' . $this->slug . 'submit" name="submit">';
			$out[] = '</p>';

			echo implode("\n", $out);
		}


	} //class

	if (!isset($libcalc)) {
		global $libcalc;
		$libcalc = new LibValueCalc();
	}
endif; /* no singleton */
