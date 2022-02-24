<?php

/**
 * LibPress Library Value Calculator
 *
 * Options and GravityForm for library value calculators
 *
 * PHP Version 7
 *
 * @package           BCLibCoop\LibValueCalc
 * @author            Jonathan Schatz <jonathan.schatz@bc.libraries.coop>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2016-2021 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       LibPress Library Value Calculator
 * Description:       Options and GravityForm for library value calculators
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            BC Libraries Cooperative
 * Author URI:        https://bc.libraries.coop
 * Text Domain:       libpress-value-calc
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace BCLibCoop;

class LibValueCalc
{
    private static $instance;

    public $slug = 'lib-value-';
    public $fieldset = [];

    public function __construct()
    {
        if (isset(self::$instance)) {
            return;
        }

        self::$instance = $this;

        $this->fieldset = [
            (object) ['internal_name' => 'books', 'public_name' => 'Books Read',
                'description' => 'Average print book price', 'value' => 0.00],
            (object) ['internal_name' => 'magazines', 'public_name' => 'Magazines Read',
                'description' => 'Average retail price of a magazine', 'value' => 0.00],
            (object) ['internal_name' => 'dvds', 'public_name' => 'DVDs Watched',
                'description' => 'Rental price of a new release DVD movie', 'value' => 0.00],
            (object) ['internal_name' => 'games', 'public_name' => 'Video Games Played',
                'description' => 'Rental price of a new video game', 'value' => 0.00],
            (object) ['internal_name' => 'cds', 'public_name' => 'Music CDs Listened to',
                'description' => 'Average price of a music CD from iTunes', 'value' => 0.00],
            (object) ['internal_name' => 'ebooks', 'public_name' => 'eBooks/Audiobooks Downloaded',
                'description' => 'Average download price of a recently published eBook or audiobook', 'value' => 0.00],
            (object) ['internal_name' => 'holds', 'public_name' => 'Holds Placed',
                'description' => 'Cost of ILL service per patron', 'value' => 0.00],
            (object) ['internal_name' => 'questions', 'public_name' => 'Questions Answered',
                'description' => 'Cost of staffing information desk/ask service per patron', 'value' => 0.00],
            (object) ['internal_name' => 'computer', 'public_name' => 'Public Computer Use 60 mins/day',
                'description' => 'Average price of hour of computer/internet time', 'value' => 0.00],
            (object) ['internal_name' => 'programs', 'public_name' => 'Programs and Classes Attended',
                'description' => 'Average cost of delivering workshops and programming per patron', 'value' => 0.00],
            (object) ['internal_name' => 'property-tax', 'public_name' => 'Total you paid for these monthly services',
                'description' => 'Monthly property tax collected from average home value in community',
                'value' => 0.00],
        ];

        add_action('init', [&$this, 'init']);
    }

    public function init()
    {
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [&$this, 'adminEnqueueStylesScripts']);
            add_action('admin_menu', [&$this, 'addLibCalcMenu']);
            add_action('wp_ajax_' . $this->slug . 'save-change', [&$this, 'libValueCalcSaveChangeCallback']);
        } else {
            add_action('wp_enqueue_scripts', [&$this, 'frontsideEnqueueStylesScripts']);
        }

        // default options if not already set
        add_option($this->slug . 'options', $this->fieldset);

        add_filter('gform_field_value', [&$this, 'populateFields'], 10, 3);
    }

    public function adminEnqueueStylesScripts($hook)
    {
        if ('site-manager_page_' . $this->slug . 'options' !== $hook) {
            return;
        }

        wp_enqueue_script(
            $this->slug . 'calc-admin-js',
            plugins_url('/js/' . $this->slug . 'calc-admin.js', __FILE__),
            ['jquery'],
            get_plugin_data(__FILE__, false, false)['Version']
        );
    }

    public function frontsideEnqueueStylesScripts()
    {
        wp_enqueue_style(
            $this->slug . 'form-front-css',
            plugins_url('/css/' . $this->slug . 'form-front.css', __FILE__),
            [],
            get_plugin_data(__FILE__, false, false)['Version']
        );
    }

    public function addLibCalcMenu()
    {
        add_submenu_page(
            'site-manager',
            'Library Value Calculator',
            'Value Calculator Rates',
            'manage_local_site',
            $this->slug . 'options',
            [&$this, 'adminLibCalcSettingsPage']
        );
    }

    public function libValueCalcSaveChangeCallback()
    {
        $optionsArray = [];

        foreach ($this->fieldset as $fieldObj) {
            if (isset($_POST[$this->slug . $fieldObj->internal_name])) {
                // updated values from form
                $fieldObj->value = (float) sanitize_text_field($_POST[$this->slug . $fieldObj->internal_name]);
            }
            $optionsArray[] = $fieldObj; // the new object array for option storage
        }

        update_option($this->slug . 'options', $optionsArray);

        wp_send_json([
            'result' => 'success',
            'feedback' => 'Saved',
        ]);
    }

    /**
     * Store value field as options per Co-op client library
     **/
    public function adminLibCalcSettingsPage()
    {
        if (!current_user_can('manage_local_site')) {
            wp_die('You do not have required permissions to view this page');
        }

        $out = [];
        $out[] = '<div class="wrap">';

        $out[] = '<div id="icon-options-general" class="icon32">';
        $out[] = '<br>';
        $out[] = '</div>';

        $out[] = '<h2>Library Value Rates</h2>';

        $out[] = '<table class="form-table">';

        $stored = get_option($this->slug . 'options');

        foreach ($stored as $obj) {
            $name = (string) $obj->internal_name;
            $public = (string) $obj->public_name;
            $value = (isset($obj->value)) ? $obj->value : '0.00';
            $description = $obj->description;

            $out[] = '<tr valign="top">';
            $out[] = '<th scope="row">';
            $out[] = '<label for="' . $this->slug . $name . '">' . ucfirst($name) . '</label>';
            // $out[] = '<p class="' . $this->slug . $name . '-public' . '">' . ucfirst($public) . '</p>';
            $out[] = '</th>';
            $out[] = '<td>';
            $out[] = '$ <input type="text" id="' . $this->slug . $name . '" name="' . $this->slug . $name
                     . '"  value="' . $value . '">';
            $out[] = '<legend><em>' . $description . '</em></legend>';
            $out[] = '</td>';
            $out[] = '</tr>';
        }

        $out[] = '</table>';

        $out[] = '<p class="submit">';
        $out[] = '<input type="submit" value="Save Changes" class="button button-primary" id="' . $this->slug
                 . 'submit" name="submit">';
        $out[] = '</p>';

        echo implode("\n", $out);
    }

    /**
     * Match each option to form field and provide to filter for dynamic population.
     *
     * @param string $value The string containing the current field value to be filtered.
     * @param object $field The current field being processed
     * @param string $field_name The parameter name of the field or input being processed.
     *
     * @return mixed
     */
    public function populateFields($value, $field, $field_name)
    {
        if (strpos($field_name, 'lib_value_') === 0) {
            $fieldset = get_option($this->slug . 'options');

            foreach ($fieldset as $fieldObj) {
                if ($field_name === 'lib_value_' . $fieldObj->internal_name) {
                    return $fieldObj->value;
                }
            }
        }

        return $value;
    }
}

defined('ABSPATH') || die(-1);

new LibValueCalc();
