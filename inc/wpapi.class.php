<?php

/**
 * A simple API plugin for WordPress that is easily extendable.
 *
 * @category    API
 * @package     WordPress API Framework
 * @author      Michael Dearman <mickeyuk@live.co.uk>
 * @license     http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link        https://github.com/MickeyUK/wp-api-framework
 */
class WPAPI {
    
    /**
     * Available endpoints
     * @var array 
     */
    protected static $endpoints;
    
    /**
     * The API secret key
     * @var string 
     */
    protected static $secret;
    
    /**
     * Token expire time (in seconds).
     * @var int 
     */
    protected static $expire_time;
    
    /**
     * The request method
     * @var string
     */
    protected static $method;
    
    /**
     * The JSON sent to the API.
     * @var object 
     */
    protected static $input;
    
    /**
     * Holds WordPress user data.
     * @var array 
     */
    protected static $user;
    
    /**
     * This loads settings for the plugin and adds the hooks.
     */
    public static function init() {
        
        // Token settings
        self::$secret = get_option('wpapi-secret');
        self::$expire_time = get_option('wpapi-expire');
        
        // Endpoint settings
        self::$endpoints = get_option('wpapi-endpoints');
        
        // Hooks
        add_action('init', array('WPAPI', 'rewrite_rule'), 0);
        add_filter('query_vars', array('WPAPI', 'query_vars'), 10, 1);
        add_action('parse_request', array('WPAPI', 'parse_request'), 0);
        add_action('plugins_loaded', array('WPAPI'), 'load_textdomain');
        
    }
    
    /**
     * This creates an array of available endpoints and holds their relative values.
     */
    private static function init_endpoints() {
        
        // This array holds all the available endpoints.
        self::$endpoints = [
            "login"         => [                // Name
                "enabled"       => true,        // Can be accessed
                "secure"        => false,       // Requires a user token
                "func"          => "login"      // Function name
            ],
            "post"          => [
                "enabled"       => true,
                "secure"        => true,
                "func"          => "post"
            ]
        ];
        
    }
    
    /**
     * This installs options for the plugin and initiates endpoints.
     */
    public static function plugin_activation() {
        
        // Flush rewrite array
        self::rewrite_rule();
        flush_rewrite_rules();
        
        // Initiate endpoints
        self::init_endpoints();
        
        // Token settings
        add_option('wpapi-secret', self::generate_key(30));
        add_option('wpapi-expire', 1600);
        
        // Endpoint settings
        update_option('wpapi-endpoints', self::$endpoints);
        
    }
    
    /**
     * Deactivates the plugin.
     */
    public static function plugin_deativation() {
        
        // Flush rewrite array
        flush_rewrite_rules();
        
    }
    
    /**
     * Inserts query variables for the API.
     * 
     * @param array $qvars
     * @return string
     */
    public static function query_vars($qvars) {
        
        $qvars[] = 'wpapi';
        $qvars[] = 'endpoint';
        return $qvars;
        
    }
    
    /**
     * Adds a new rewrite rule for the API.
     */
    public static function rewrite_rule() {
        
        add_rewrite_rule('^api/v1/?([A-Za-z0-9]+)', 
                'index.php?wpapi&endpoint=$matches[1]', 'top');
        
    }
    
    /**
     * Parses requests to the API.
     * 
     * @global object $wp WordPress
     */
    public static function parse_request() {
        
        global $wp;
        
        // Get query variables
        $query = $wp->query_vars;
        
        // If API called & endpoint is valid
        if (isset($query['wpapi']) && self::check_endpoint($query['endpoint'])) {
            
            // Request method
            self::$method = $_SERVER['REQUEST_METHOD'];
            
            // Decode JSON to object
            self::$input = json_decode(file_get_contents('php://input'));
            
            // Endpoint settings
            $endpoint = self::$endpoints[$query['endpoint']];
            
            // If endpoint requires a token
            if ($endpoint['secure']) {
                self::decode_token();
            }
            
            // Call endpoint function
            call_user_func(array('WPAPI',$endpoint['func']));
            
        }
        
    }
    
    /**
     * Tells WordPress where the translation files reside.
     */
    public static function load_textdomain() {
        load_plugin_textdomain( WPAPI_DOMAIN, false, WPAPI_DIR . '/lang/');
    }
    
    /**
     * Checks an endpoint to see if valid and user has access to.
     * 
     * @param string $endpoint The endpoint name.
     * 
     * @return bool
     */
    private static function check_endpoint($endpoint) {
        
        // Available endpoints
        $endpoints = array_keys(self::$endpoints);
        
        // If not declared
        if (!isset($endpoint) || !in_array($endpoint,$endpoints) 
                || !self::$endpoints[$endpoint]['enabled']) {
            return false;
        }
        
        return true;
        
    }
    
    /**
     * Generates a JSON Web Token for secure endpoints.
     * 
     * @param object $user WordPress user.
     * 
     * @return string
     */
    private static function create_token() {
        
        // Token
        $tokenId    = base64_encode(mcrypt_create_iv(32));
        $issuedAt   = time();
        $notBefore  = $issuedAt + 10; //Adding 10 seconds
        $expire     = $notBefore + self::$expire_time; // Adding 60 seconds
        $serverName = 'http://wordpress.dev';
        
        // Payload
        $data = [
            'iat'   => $issuedAt,         // Time when the token was generated
            'jti'   => $tokenId,          // Unique identifier for the token
            'iss'   => $serverName,       // Issuer
            'nbf'   => $notBefore,        // Not before
            'exp'   => $expire,           // Expire
            'user'  => self::$user        // WordPress User Info
        ];
        
        // Create JSON Web Token
        $token = JWT::encode($data, self::$secret, 'HS512');
        
        // Return token
        return $token;
        
    }
    
    /**
     * This checks the JWT (JSON Web Token) in the authorization header.
     */
    private static function decode_token() {
        
        // Check for authorization header
        $headers = getallheaders();
        if (isset($headers['authorization'])) {
        
            // Extract JWT
            list($jwt) = sscanf($headers['authorization'], 'Bearer %s');

            if ($jwt) {

                try {

                    // Decode JWT
                    $token = JWT::decode($jwt,self::$secret);
                    
                    // Validate token
                    self::validate_token($token);
                    
                } catch (Exception $e) {

                    // Token may have been tampered with
                    self::error(__("Invalid token.",WPAPI_DOMAIN));

                }

            } else {
                
                // Token could not be extracted from header
                self::error(__("Invalid token.",WPAPI_DOMAIN));
                
            }
        
        } else {
            
            // No token sent with request
            self::error(__("This endpoint requires a token.",WPAPI_DOMAIN));
            
        }
        
    }
    
    /**
     * Verifies a decoded token.
     * 
     * @param object $token The decoded token.
     */
    private static function validate_token($token) {
        
        // Check if token has expired
        if (time() > $token->exp) {
            self::error(__("This token has expired.",WPAPI_DOMAIN));
        }
        
        // Set user
        self::$user = $token->user;
        
    }
    
    /**
     * Parses inputted JSON for required and optional values.
     * Arrays should follow this example: 
     * $req = ["email" => FILTER_VALIDATE_EMAIL,"password" = "",
     * "hobbies" => FILTER_REQUIRE_ARRAY]
     * 
     * @param array $req The required values and their filter types.
     * @param array $opt The optional values and their filter types.
     */
    private static function validate_input($req, $opt = []) {
        
        // If data sent to API
        if (isset(self::$input)) {
            
            // Check for required values
            foreach($req as $key => $value) {
                
                if (!isset(self::$input->$key)) {
                    self::error($key . __(" is required!",WPAPI_DOMAIN));
                }
                
            }
            
            // Check all inputted values
            foreach(self::$input as $key => $value) {
                
                // If required value
                if (isset($req[$key])) {
                    
                    // Validate value
                    if (!self::filter_value($value,$req[$key])) {
                        self::error($key . __(" is invalid!",WPAPI_DOMAIN)); 
                    }
                    
                } else
                    
                // If optional value
                if (isset($opt[$key])) {

                    // Validate value
                    if (!self::filter_value($value,$opt[$key])) {
                        self::error($key . __(" is invalid!",WPAPI_DOMAIN)); 
                    }

                } else {

                    unset(self::$input->$key);

                }
                
            }
            
        } else {
            self::error(__("This endpoint requires additional parameters.",WPAPI_DOMAIN)); 
        }
        
    }
    
    /**
     * Validates a value against a filter type.
     * 
     * @param string $value The value to check.
     * @param string $filter The filter validation type.
     * 
     * @return bool
     */
    private static function filter_value($value, $filter) {
        
        if (is_int($filter)) {
        
            // Array
            if (is_array($value) && $filter != FILTER_REQUIRE_ARRAY) {

                return false;

            } else {

                // Validate
                if (!is_array($value) && filter_var($value,$filter) === false) {
                    return false;
                }

            }
            
        }

        return true;
        
    }
    
    /**
     * Sends JSON to the client.
     * 
     * @param array $array The data to be JSON encoded.
     */
    private static function send($array) {
        
        header('Content-type: application/json');
        echo json_encode($array);
        die();
        
    }
    
    /**
     * Sends JSON encoded error to the client.
     * 
     * @param string $error The message to display.
     */
    private static function error($error) {
        
        header('Content-type: application/json');      
        echo json_encode(["error"=>$error]);
        die();
        
    }
    
    /**
     * Generates a secure key.
     * 
     * @param int $length The length of the generated key.
     * @param string $str (Optional) The string to generate a key from.
     * 
     * @return string
     */
    public static function generate_key($length, $str = '') {
        
        if ($str == '') {
            $chars = 'abcdefghijklmnopqrstuvwxyz';
            $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $chars .= '0123456789';
            $chars .= '~!@$%^&*()_+-={}[]:;\<>?,./|\\';
        } else {
            $chars = $str;
        }
        
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[floor(rand(0,strlen($chars)-1))];
        }
        
        return $result;
        
    }
    
    /**
     * Authenticates and generates a JSON Web Token for the user.
     */
    private static function login() {
        
        // Validate input
        self::validate_input(["username"=>"","password"=>""]);
        
        // Credentials
        $username = self::$input->username;
        $password = self::$input->password;
        
        // Authenticate
        $auth = wp_authenticate($username,$password);
        
        // Invalid credentials
        if (is_wp_error($auth)) {
            
            self::error(__("Invalid login credentials.",WPAPI_DOMAIN));
            
        } else {
            
            // WordPress user (Do not store confidential information!)
            // JSON Web Tokens are not encrypted
            self::$user = [
                "ID"            => $auth->data->ID,
                "user_login"    => $auth->data->user_login,
                "user_status"   => $auth->data->user_status,
                "roles"         => $auth->roles
            ];
            
            // Create JSON Web Token
            $token = self::create_token();
            
            // Send token to user
            self::send(["token" => $token]);
            
        }
        
    }
    
    /**
     * Insert a post on the blog.
     */
    private static function post() {
        
        // Can post?
        if (self::$user->roles[0] == "subscriber") {
            self::error(__("You do not have the correct permissions to post!",WPAPI_DOMAIN));
        }
        
        // Required JSON
        $req = array(
            "post_title"        =>  "",
            "post_content"      =>  ""
        );
        
        // Optional JSON
        $opt = array (
            "post_content_filtered"     => "",
            "post_excerpt"              => "",
            "post_status"               => "",
            "post_type"                 => "",
            "post_date"                 => "",
            "post_password"             => "",
            "post_name"                 => "",
            "post_parent"               => FILTER_VALIDATE_INT,
            "menu_order"                => FILTER_VALIDATE_INT,
            "tax_input"                 => FILTER_REQUIRE_ARRAY,
            "meta_input"                => FILTER_REQUIRE_ARRAY
        );
        
        // Validate
        self::validate_input($req,$opt);
        
        // Can publish?
        if (self::$user->roles[0] == "contributor") {
            unset(self::$input->post_status);
        }
        
        // Set post author
        self::$input->post_author = self::$user->ID;
        
        // Insert post
        $post = wp_insert_post(self::$input);
        
        // Response
        if ($post) {
            
            self::send(["post_id" => $post]);
            
        } else {
            
            self::error(__("Could not insert post!",WPAPI_DOMAIN));
            
        }
        
    }
    
}