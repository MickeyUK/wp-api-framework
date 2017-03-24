<?php

/**
 * Adds backend options for the WordPress API Framework plugin.
 *
 * @category    Admin
 * @package     WordPress API Framework
 * @author      Michael Dearman <mickeyuk@live.co.uk>
 * @license     http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link        https://github.com/MickeyUK/wp-api-framework
 */
class WPAPI_Admin {

    /**
     * Initializes all the admin hooks for the plugin.
     */
    public static function admin_hooks() {

        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename(WPAPI_FILE), 
                array('WPAPI_Admin', 'plugin_action_links'));

        // Add settings fields
        add_action("admin_init", array('WPAPI_Admin', 'admin_page_sections'));

        // Options page
        add_action('admin_menu', array('WPAPI_Admin', 'add_admin_page'));

    }

    /**
     * Extends action links for the plugin.
     * 
     * @param array $links Action links list.
     * 
     * @return string
     */
    public static function plugin_action_links($links) {

        $links[] = '<a href="' . esc_url(get_admin_url(null, 'options-general.php?page=wpapi')) . '">Settings</a>';
        $links[] = '<a href="http://github.com/mickeyuk/wp-api-framework" target="_blank">Guide</a>';
        return $links;
        
    }

    /**
     * Adds the options page to the admin dashboard.
     */
    public static function add_admin_page() {

        add_options_page('API', 'API', 'manage_options', 
                'wpapi', array('WPAPI_Admin', 'admin_page'));
        
    }

    /**
     * Displays the options page.
     */
    public static function admin_page() {
        
        ?>
        <div class="wrap">
            <h1>API</h1>
            <p>Settings for the WordPress API Framework plugin.</p>
            <form method="post" action="options.php">   
        <?php
        
        settings_fields("wpapi-settings");
        
        do_settings_sections("wpapi");
        
        submit_button();
        
        ?>
            </form>
        </div>
        <?php
    }

    /**
     * Adds all the admin settings sections.
     */
    public static function admin_page_sections() {

        // Token settings
        add_settings_section("wpapi-settings", "All Settings", 
                array('WPAPI_Admin', 'admin_page_settings'), "wpapi");
        
        register_setting("wpapi-settings", "wpapi-secret");
        register_setting("wpapi-settings", "wpapi-expire");
        
        register_setting("wpapi-settings", "wpapi-endpoints",
                array('WPAPI_Admin','endpoint_save'));
        
    }

    /**
     * Displays the token settings.
     */
    public static function admin_page_settings() {

        // Secret key
        add_settings_field("wpapi-secret", "Secret Key", function() {
            self::input_text("wpapi-secret", get_option('wpapi-secret'), "This secret key is used to sign and verify user tokens.");
        }, "wpapi", "wpapi-settings");

        // Expiration time
        add_settings_field("wpapi-expire", "Expire Time", function() {
            self::input_number("wpapi-expire", get_option('wpapi-expire'), "How long a token lasts before expiring (in seconds).");
        }, "wpapi", "wpapi-settings");
        
        // Endpoints list
        add_settings_field('wpapi-endpoints', 'Available Endpoints', function($cb) {
                
            foreach ($cb[0] as $name => $set) {
                
                echo '<input type="hidden" name="';
                echo 'wpapi-endpoints['.$name.'][name]';
                echo '" value="'.$name.'"/>';
                
                $enabled = ($set["enabled"]) ? 1 : 0;
                $secure = ($set["secure"]) ? 1 : 0;
                
                self::input_checkbox("wpapi-endpoints[".$name."][enabled]",
                        "Enabled",$enabled);
                
                self::input_checkbox("wpapi-endpoints[".$name."][secure]",
                        "Secure",$secure);
                
                echo '<label style="margin-left:2em;"><code>'.$name.'</code></label>';
                
                echo '<br><br>';
            }
                
        }, "wpapi", "wpapi-settings", [get_option('wpapi-endpoints')]);

    }
    
    /**
     * Updates the endpoint settings.
     * 
     * @param array $input
     * @return array
     */
    public static function endpoint_save($input) {
        
        $endpoints = get_option('wpapi-endpoints');
        
        foreach($input as $name => $set) {
            
            if (isset($set['enabled'])) {
                $endpoints[$name]['enabled'] = true;
            } else {
                $endpoints[$name]['enabled'] = false;
            }
            
            if (isset($set['secure'])) {
                $endpoints[$name]['secure'] = true;
            } else {
                $endpoints[$name]['secure'] = false;
            }
            
        }
        
        return $endpoints;
        
    }

    /**
     * Displays an input text field.
     * 
     * @param string $name The name and ID for the input field.
     * @param string $value The value for the input field.
     * @param string $description A description for the field.
     */
    public static function input_text($name, $value = "", $description = "") {

        // ARIA
        $desc = ($description != '') ? 'aria-describedby = "' . $name . '-description"' : '';

        // Input field
        echo sprintf('<input type="text" id="%1$s" name="%1$s" value="%2$s" class="%3$s" %4$s/>', $name, $value, "regular-text", $desc);

        // Description
        if ($description != "") {
            echo '<p id="' . $name . '-description" class="description">' . $description;
        }
        
    }

    /**
     * Displays an input number field.
     * 
     * @param string $name The name and ID for the input field.
     * @param string $value The value for the input field.
     * @param string $description A description for the field.
     */
    public static function input_number($name, $value = "", $description = "") {

        // ARIA
        $desc = ($description != '') ? 'aria-describedby = "' . $name . '-description"' : '';

        // Input field
        echo sprintf('<input type="number" id="%1$s" name="%1$s" value="%2$s" %3$s/>', $name, $value, $desc);

        // Description
        if ($description != "") {
            echo '<p id="' . $name . '-description" class="description">' . $description;
        }
        
    }

    /**
     * Displays an input checkbox field.
     * 
     * @param string $name The name for the input field.
     * @param string $label The label for the checkbox.
     * @param bool $enabled Checked?
     */
    public static function input_checkbox($name, $label, $enabled=false) {

        echo '<label for="' . $name . '">';
        echo '<input type="checkbox" name="' . $name . '" ';
        echo ($enabled) ? 'checked/>' : '/>';
        echo '&nbsp;<span>' . $label . '</span>&nbsp;&nbsp;&nbsp;';
        echo '</label>';
        
    }

}
