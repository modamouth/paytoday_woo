<?php
/**
 * Plugin Name: PayToday Payment Gateway for WooCommerce
 * Version: 0.1.0
 * Author: PayToday
 * Author URI: https://site.paytoday.com.na/
 * Description: Accept payments using PayToday payment gateway in WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

add_filter( 'woocommerce_payment_gateways', 'payToday_add_gateway_class' );
function payToday_add_gateway_class( $gateways ) {
    error_log('PayToday Debug - Adding gateway to WooCommerce');
    $gateways[] = 'WC_PayToday_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'payToday_init_gateway_class' );

function payToday_init_gateway_class() {

    class WC_PayToday_Gateway extends WC_Payment_Gateway {

        /**
         * Class properties
         */
        public $sandbox_mode;
        public $private_key;
        public $publishable_key;
        public $shop_key;
        public $shop_handle;
        public $paytoday_private_key;
        public $return_url;
        public $service_url;

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct() {
            // Debug: Log that gateway is being constructed
            error_log('PayToday Debug - Gateway constructor called');

            $this->id = 'paytoday'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'PayToday Gateway';
            $this->method_description = 'Accept payments using PayToday payment gateway in WooCommerce'; // will be displayed on the options page
        
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
        
            // Method with all the options fields
            $this->init_form_fields();
        
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->sandbox_mode = 'yes' === $this->get_option( 'sandbox_mode' );
            $this->private_key = $this->sandbox_mode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->sandbox_mode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
        
            // Load PayToday specific settings - use sandbox or live credentials based on mode
            $this->shop_key = $this->sandbox_mode ? $this->get_option( 'sandbox_shop_key' ) : $this->get_option( 'shop_key' );
            $this->shop_handle = $this->sandbox_mode ? $this->get_option( 'sandbox_shop_handle' ) : $this->get_option( 'shop_handle' );
            $this->paytoday_private_key = $this->get_option( 'paytoday_private_key' );
            
            // Set return URL
            $this->return_url = home_url('/wc-api/paytoday_return');
            
            // Set service URL based on environment
            $this->service_url = $this->sandbox_mode ? 'https://admin.today-ww.net' : 'https://admin.today.com.na';
            
            // Log the service URL being used
            error_log('PayToday Debug - Service URL set to: ' . $this->service_url . ' (Sandbox Mode: ' . ($this->sandbox_mode ? 'ENABLED' : 'DISABLED') . ')');
            error_log('PayToday Debug - Using ' . ($this->sandbox_mode ? 'SANDBOX' : 'LIVE') . ' credentials - Shop Handle: ' . $this->shop_handle . ', Shop Key: ' . substr($this->shop_key, 0, 10) . '...');
        
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            
            // Add popup handling for payment
            add_action( 'wp_footer', array( $this, 'add_payment_popup_script' ) );
            
            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
            // ✅ Register WooCommerce API endpoint for front-channel return
            add_action( 'woocommerce_api_paytoday_return', array( $this, 'handle_paytoday_return' ) );
            // ✅ Define explicit return URL for PayToday redirect
            $this->return_url = home_url( '/wc-api/paytoday_return' );
            // removed: add_action( 'wp_footer', array( $this, 'add_payment_popup_script' ) ); // popup disabled


        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable PayToday Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Credit Card',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'sandbox_mode' => array(
                    'title'       => 'Sandbox mode',
                    'label'       => 'Enable Sandbox Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in sandbox mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                ),
                'shop_key' => array(
                    'title'       => 'Live Shop Key',
                    'type'        => 'text',
                    'description' => 'Your PayToday shop key for production environment.',
                    'default'     => 'f4c5fe648ba0b6ff54716bf627a09c811c6d5fd713e3e74331544b4f6c408c61',
                    'desc_tip'    => true,
                ),
                'shop_handle' => array( 
                    'title'       => 'Live Shop Handle',
                    'type'        => 'text',
                    'description' => 'Your PayToday shop handle identifier for production environment.',
                    'default'     => '833::b69c2ea2985a',
                    'desc_tip'    => true,
                ),
                'sandbox_shop_key' => array(
                    'title'       => 'Sandbox Shop Key',
                    'type'        => 'text',
                    'description' => 'Your PayToday shop key for sandbox environment.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sandbox_shop_handle' => array( 
                    'title'       => 'Sandbox Shop Handle',
                    'type'        => 'text',
                    'description' => 'Your PayToday shop handle identifier for sandbox environment.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'paytoday_private_key' => array(
                    'title'       => 'PayToday Private Key',
                    'type'        => 'password',
                    'description' => 'Your PayToday private key for secure transactions.',
                    'default'     => 'DvzOMtOpC4215y7ClqpM',
                    'desc_tip'    => true,
                )
            );
    
        }

                // Process payment
                public function process_payment($order_id) {
                    $order = wc_get_order($order_id);
                    $amount = $order->get_total();
                    
                    error_log('PayToday Debug - Starting payment process for Order #' . $order_id);
                    error_log('PayToday Debug - Environment: ' . ($this->sandbox_mode ? 'SANDBOX' : 'PRODUCTION') . ' | Service URL: ' . $this->service_url);
                    $payment_data = array(
                        'amount' => $amount,
                        'invoice_number' => $order->get_order_number(),
                        'user_first_name' => $order->get_billing_first_name(),
                        'user_last_name' => $order->get_billing_last_name(),
                        'user_email' => $order->get_billing_email(),
                        'user_phone_number' => $order->get_billing_phone(),
                        'return_url' => $this->return_url
                    );
        
                    // Log the payment attempt
                    $this->log('Processing payment for Order #' . $order_id . '. Amount: ' . $amount);
                    
                    // Create configuration request to get authorization token
                    $authorization_token = $this->create_configuration_intent();
                    if ($authorization_token) {
                        // Log successful token retrieval
                        $this->log('Authorization token received for Order #' . $order_id);
                        
                        // Create payment intent
                        error_log('PayToday Debug - About to create payment intent for Order #' . $order_id);
                        $payment_intent_result = $this->create_payment_intent($payment_data, $authorization_token);
                        error_log('PayToday Debug - Payment intent result: ' . print_r($payment_intent_result, true));
                        
                        if ($payment_intent_result && isset($payment_intent_result['payment_url'])) {
                            $payment_intent_url = $payment_intent_result['payment_url'];
                            $payment_token = $payment_intent_result['payment_token'] ?? null;
                            
                            // Store tokens for status checking
                            error_log('PayToday Debug - Storing tokens for Order #' . $order_id);
                            error_log('PayToday Debug - Payment token to store: ' . ($payment_token ? 'EXISTS (length: ' . strlen($payment_token) . ')' : 'NULL/EMPTY'));
                            error_log('PayToday Debug - Authorization token to store: ' . ($authorization_token ? 'EXISTS (length: ' . strlen($authorization_token) . ')' : 'NULL/EMPTY'));
                            
                            // Always store authorization token if available
                            if ($authorization_token) {
                                $auth_token_stored = update_post_meta($order_id, '_paytoday_authorization_token', $authorization_token);
                                error_log('PayToday Debug - Authorization token storage: ' . ($auth_token_stored ? 'SUCCESS' : 'FAILED'));
                            }
                            
                            // Store payment token if available
                            if ($payment_token) {
                                $payment_token_stored = update_post_meta($order_id, '_paytoday_payment_token', $payment_token);
                                error_log('PayToday Debug - Payment token storage: ' . ($payment_token_stored ? 'SUCCESS' : 'FAILED'));
                            } else {
                                // If no payment token, try to extract it from the payment URL
                                $url_parts = parse_url($payment_intent_url);
                                if (isset($url_parts['path'])) {
                                    $path_parts = explode('/', trim($url_parts['path'], '/'));
                                    $last_part = end($path_parts);
                                    if (!empty($last_part) && $last_part !== 'payments') {
                                        $extracted_token = $last_part;
                                        $payment_token_stored = update_post_meta($order_id, '_paytoday_payment_token', $extracted_token);
                                        error_log('PayToday Debug - Extracted payment token from URL: ' . $extracted_token . ' (storage: ' . ($payment_token_stored ? 'SUCCESS' : 'FAILED') . ')');
                                    }
                                }
                            }
                            
                            // Store other meta data
                            $status_check_stored = update_post_meta($order_id, '_paytoday_status_check_started', time());
                            $polling_flag_stored = update_post_meta($order_id, '_paytoday_polling_active', 'yes');
                            
                            error_log('PayToday Debug - Final storage results: status_check=' . ($status_check_stored ? 'SUCCESS' : 'FAILED') . 
                                     ', polling_flag=' . ($polling_flag_stored ? 'SUCCESS' : 'FAILED'));
                            
                            $this->log('Payment Intent Created for Order #' . $order_id . '. Opening PayToday popup: ' . $payment_intent_url);
                            
                            // Store the payment URL for popup opening
                            update_post_meta($order_id, '_paytoday_payment_url', $payment_intent_url);
                            
                            // Return success with a special redirect that will trigger popup
                            return array('result' => 'success', 'redirect' => $payment_intent_url);
                        } else {
                            error_log('PayToday Debug - Payment intent creation failed for Order #' . $order_id . '. Result: ' . print_r($payment_intent_result, true));
                            $this->log('Failed to create Payment Intent for Order #' . $order_id . '. Result: ' . print_r($payment_intent_result, true));
                        }
                    } else {
                        $this->log('Failed to retrieve authorization token for Order #' . $order_id);
                    }
        
                    wc_add_notice(__('Payment failed. Please try again.', 'paytoday'), 'error');
                    return array('result' => 'failure');
                }
        
                // Create a configuration intent to get the authorization token
                private function create_configuration_intent() {
                    $config_url = $this->service_url . '/web/configuration/intent/';
                    error_log('PayToday Debug - Making configuration intent API call...');
                    error_log('PayToday Debug - Configuration URL: ' . $config_url);
                    error_log('PayToday Debug - Shop Handle: ' . $this->shop_handle);
                    error_log('PayToday Debug - Shop Key: ' . substr($this->shop_key, 0, 10) . '...');
                    $this->log('Creating configuration intent to obtain authorization token...');
                    $this->log('Using Configuration URL: ' . $config_url);
                    
                    // Validate required fields
                    if (empty($this->shop_handle)) {
                        error_log('PayToday Debug - Shop Handle is empty!');
                        $this->log('Error: Shop Handle is not configured');
                        return null;
                    }
                    
                    if (empty($this->shop_key)) {
                        error_log('PayToday Debug - Shop Key is empty!');
                        $this->log('Error: Shop Key is not configured');
                        return null;
                    }
                    
                    $request_body = json_encode([ 
                        'v' => '12.12.2024',
                        'handle' => $this->shop_handle,
                        'key' => $this->shop_key,
                    ]);
                    
                    error_log('PayToday Debug - Request body: ' . $request_body);
                    $this->log('Request body: ' . $request_body);
                    
                    $response = wp_remote_post($config_url, array(
                        'method'    => 'POST',
                        'headers'   => array(
                            'Content-Type' => 'application/json',
                            'User-Agent' => 'PayToday-WooCommerce-Plugin/1.0',
                            'Accept' => 'application/json',
                        ),
                        'body'      => $request_body,
                        'timeout'   => 30,
                        'sslverify' => false,
                    ));
                    
                    if (is_wp_error($response)) {
                        error_log('PayToday Debug - Configuration intent API call failed: ' . $response->get_error_message());
                        $this->log('Error creating configuration intent: ' . $response->get_error_message());
                        return null;
                    }
                    
                    $response_code = wp_remote_retrieve_response_code($response);
                    error_log('PayToday Debug - Configuration intent response code: ' . $response_code);
                    
                    if ($response_code !== 200 && $response_code !== 201) {
                        $response_body = wp_remote_retrieve_body($response);
                        error_log('PayToday Debug - Configuration intent error response: ' . $response_body);
                        $this->log('Configuration intent API error (Code: ' . $response_code . '): ' . $response_body);
                        
                        // Try to decode JWT error response
                        $error_data = json_decode($response_body, true);
                        if (isset($error_data['token'])) {
                            $decoded_error = $this->decode_jwt($error_data['token']);
                            if ($decoded_error) {
                                error_log('PayToday Debug - Decoded error response: ' . print_r($decoded_error, true));
                                $this->log('Decoded error response: ' . print_r($decoded_error, true));
                            }
                        }
                        
                        return null;
                    }
        
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    error_log('PayToday Debug - Configuration intent API response: ' . print_r($body, true));
                    if (isset($body['token'])) {
                        error_log('PayToday Debug - Token found in response');
                        $this->log('Authorization token retrieved successfully.');
                        
                        $decoded_data = $this->decode_jwt($body['token']);
        
                        $this->log('Payment Token: ' . print_r($decoded_data, true));
        
                        // Check if the decoded data has the expected structure
                        if ($decoded_data && isset($decoded_data->data) && isset($decoded_data->data->authorization) && isset($decoded_data->data->authorization->access_token)) {
                            return $decoded_data->data->authorization->access_token;
                        } else {
                            error_log('PayToday Debug - Invalid token structure: ' . print_r($decoded_data, true));
                            $this->log('Invalid token structure: ' . print_r($decoded_data, true));
                            return null;
                        }
                    }
                    
                    error_log('PayToday Debug - No token found in configuration intent response');
        
                    $this->log('Authorization token not found in response.');
                    return null;
                }
        
                // Create a payment intent with PayToday
                private function create_payment_intent($payment_data, $authorization_token) {
                    $payment_url = $this->service_url . '/web/create/payment/intent/';
                    error_log('PayToday Debug - Making payment intent API call...');
                    error_log('PayToday Debug - Payment Intent URL: ' . $payment_url);
                    $this->log('Creating payment intent...');
                    $this->log('Using Payment Intent URL: ' . $payment_url);
                    // Log the payment data array as a string representation
                    $this->log('Payment Data: ' . print_r($payment_data, true));
                    $this->log('amount: ' . print_r($payment_data['amount'], true));
                    
                    // Log the authorization token
                    $this->log('Authorization Token: ' . $authorization_token);
                    $response = wp_remote_post($payment_url, array(
                        'method'    => 'POST',
                        'headers'   => array(
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $authorization_token,
                            'User-Agent' => 'PayToday-WooCommerce-Plugin/1.0',
                            'Accept' => 'application/json',
                        ),
                        'body'      => json_encode([
                            'v' => '12.12.2024',
                            'handle' => $this->shop_handle,
                            'amount' => $payment_data['amount'],
                            'invoice_number' => $payment_data['invoice_number'],
                            'user_first_name' => $payment_data['user_first_name'],
                            'user_last_name' => $payment_data['user_last_name'],
                            'user_email' => $payment_data['user_email'],
                            'user_phone_number' => $payment_data['user_phone_number'],
                            'return_url' => $payment_data['return_url'],
                        ]),
                        'timeout'   => 30,
                        'sslverify' => false,
                    ));
        
                    if (is_wp_error($response)) {
                        error_log('PayToday Debug - Payment intent API call failed: ' . $response->get_error_message());
                        $this->log('Error creating payment intent: ' . $response->get_error_message());
                        return null;
                    }
                    
                    $response_code = wp_remote_retrieve_response_code($response);
                    error_log('PayToday Debug - Payment intent response code: ' . $response_code);
                    
                    if ($response_code !== 200 && $response_code !== 201) {
                        $response_body = wp_remote_retrieve_body($response);
                        error_log('PayToday Debug - Payment intent error response: ' . $response_body);
                        $this->log('Payment intent API error (Code: ' . $response_code . '): ' . $response_body);
                        return null;
                    }
        
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    error_log('PayToday Debug - Payment intent API response: ' . print_r($body, true));

                    if (!isset($body['token'])) {
                        error_log('PayToday Debug - No token found in payment intent response');
                        $this->log('No token found in payment intent response');
                        return null;
                    }

                    $decoded_data = $this->decode_jwt($body['token']);
        
                    $this->log('Decoded Payment Intent: ' . print_r($decoded_data, true));
        
                    // Log the response for debugging
                    $this->log('Payment Intent API Response: ' . print_r($decoded_data, true));
        
                    if ($decoded_data && isset($decoded_data->data)) {
                        $payment_url = $decoded_data->data->payment_url ?? null;
                        $payment_token = $decoded_data->data->payment_token ?? null;
                        
                        // Check for alternative token field names
                        if (!$payment_token) {
                            $payment_token = $decoded_data->data->token ?? null;
                        }
                        if (!$payment_token) {
                            $payment_token = $decoded_data->data->id ?? null;
                        }
                        if (!$payment_token) {
                            $payment_token = $decoded_data->data->payment_id ?? null;
                        }
                        
                        error_log('PayToday Debug - Payment intent response data:');
                        error_log('PayToday Debug - payment_url: ' . ($payment_url ? 'EXISTS (' . $payment_url . ')' : 'NULL/EMPTY'));
                        error_log('PayToday Debug - payment_token: ' . ($payment_token ? 'EXISTS (length: ' . strlen($payment_token) . ')' : 'NULL/EMPTY'));
                        error_log('PayToday Debug - Full decoded data: ' . print_r($decoded_data, true));
                        
                        // Log all available fields in the data object
                        if (is_object($decoded_data->data)) {
                            $data_fields = get_object_vars($decoded_data->data);
                            error_log('PayToday Debug - Available fields in data object: ' . implode(', ', array_keys($data_fields)));
                            foreach ($data_fields as $field => $value) {
                                error_log('PayToday Debug - data.' . $field . ': ' . (is_string($value) ? $value : gettype($value)));
                            }
                        }
                        
                        return array(
                            'payment_url' => $payment_url,
                            'payment_token' => $payment_token
                        );
                    }
                    
                    error_log('PayToday Debug - Invalid payment intent response structure');
                    $this->log('Invalid payment intent response structure');
                    return null;
                }
        
                // Simple logging method
                private function log($message) {
                    if ('yes' === $this->get_option('debug')) {
                        $logger = wc_get_logger();
                        $logger->info('PayToday: ' . $message, array('source' => 'paytoday'));
                    }
                }
        
                private function decode_jwt($jwt)
                {
                    $parts = explode('.', $jwt);
                    if (count($parts) !== 3) {
                        return false;  // Invalid JWT format
                    }
        
                    $payload = base64_decode($parts[1]);
                    if (!$payload) {
                        return false;
                    }
        
                    return json_decode($payload);
                }

                // Query payment intent status
                public function query_payment_intent($payment_token, $authorization_token) {
                    $lookup_url = $this->service_url . '/web/payment/lookup/' . $payment_token . '/';
                    error_log('PayToday Debug - Checking payment status...');
                    error_log('PayToday Debug - Payment Lookup URL: ' . $lookup_url);
                    error_log('PayToday Debug - Payment Token: ' . $payment_token);
                    $this->log('Checking payment status...');
                    $this->log('Using Payment Lookup URL: ' . $lookup_url);
                    
                    $response = wp_remote_get($lookup_url, array(
                        'method'    => 'GET',
                        'headers'   => array(
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $authorization_token,
                            'User-Agent' => 'PayToday-WooCommerce-Plugin/1.0',
                            'Accept' => 'application/json',
                        ),
                        'timeout'   => 30,
                        'sslverify' => false,
                    ));
                    
                    if (is_wp_error($response)) {
                        error_log('PayToday Debug - Payment status check failed: ' . $response->get_error_message());
                        $this->log('Error checking payment status: ' . $response->get_error_message());
                        return array('error' => $response->get_error_message());
                    }
                    
                    $response_code = wp_remote_retrieve_response_code($response);
                    error_log('PayToday Debug - Payment status response code: ' . $response_code);
                    
                    if ($response_code !== 200 && $response_code !== 201) {
                        $response_body = wp_remote_retrieve_body($response);
                        error_log('PayToday Debug - Payment status error response: ' . $response_body);
                        $this->log('Payment status API error (Code: ' . $response_code . '): ' . $response_body);
                        return array('error' => 'API error: ' . $response_code . ' - ' . $response_body);
                    }
                    
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    error_log('PayToday Debug - Payment status API response: ' . print_r($body, true));
                    
                    if (isset($body['token'])) {
                        $decoded_data = $this->decode_jwt($body['token']);
                        error_log('PayToday Debug - Decoded payment status: ' . print_r($decoded_data, true));
                        $this->log('Payment Status Result: ' . print_r($decoded_data, true));
                        return $decoded_data;
                    } else {
                        error_log('PayToday Debug - No token found in payment status response');
                        $this->log('No token found in payment status response');
                        return array('error' => 'No token found in response');
                    }
                }

                // Schedule payment status check
                private function schedule_payment_status_check($order_id, $payment_token, $authorization_token) {
                    $next_check_time = date('Y-m-d H:i:s', time() + 15);
                    error_log('PayToday Debug - Scheduling payment status check for Order #' . $order_id . ' (first check at: ' . $next_check_time . ')');
                    $this->log('Scheduling payment status check for Order #' . $order_id . ' (first check at: ' . $next_check_time . ')');
                    
                    // Check if WordPress cron is disabled
                    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                        error_log('PayToday Debug - WARNING: WordPress cron is disabled!');
                        $this->log('WARNING: WordPress cron is disabled!');
                    }
                    
                    // Schedule the first check in 15 seconds
                    $scheduled = wp_schedule_single_event(time() + 15, 'paytoday_check_payment_status', array($order_id, $payment_token, $authorization_token));
                    
                    if ($scheduled === false) {
                        error_log('PayToday Debug - ERROR: Failed to schedule payment status check for Order #' . $order_id);
                        $this->log('ERROR: Failed to schedule payment status check for Order #' . $order_id);
                    } else {
                        error_log('PayToday Debug - Successfully scheduled payment status check for Order #' . $order_id);
                        $this->log('Successfully scheduled payment status check for Order #' . $order_id);
                    }
                    
                    // Log all scheduled cron jobs for debugging
                    $cron_jobs = _get_cron_array();
                    error_log('PayToday Debug - Current cron jobs: ' . print_r($cron_jobs, true));
                }

                // Check payment status (called by cron)
                public function check_payment_status($order_id, $payment_token, $authorization_token) {
                    $timestamp = date('Y-m-d H:i:s');
                    error_log('PayToday Debug - [' . $timestamp . '] Running scheduled payment status check for Order #' . $order_id);
                    $this->log('[' . $timestamp . '] Running scheduled payment status check for Order #' . $order_id);
                    
                    $order = wc_get_order($order_id);
                    if (!$order) {
                        error_log('PayToday Debug - Order #' . $order_id . ' not found, stopping status checks');
                        return;
                    }
                    
                    // Check if order is already completed/cancelled
                    if ($order->get_status() === 'completed' || $order->get_status() === 'cancelled' || $order->get_status() === 'failed') {
                        error_log('PayToday Debug - Order #' . $order_id . ' is already ' . $order->get_status() . ', stopping status checks');
                        $this->log('Order #' . $order_id . ' is already ' . $order->get_status() . ', stopping status checks');
                        return;
                    }
                    
                    // Query the payment status
                    $status_result = $this->query_payment_intent($payment_token, $authorization_token);
                    
                    if (isset($status_result['error'])) {
                        error_log('PayToday Debug - Error checking payment status for Order #' . $order_id . ': ' . $status_result['error']);
                        $this->log('Error checking payment status for Order #' . $order_id . ': ' . $status_result['error']);
                        
                        // Schedule another check in 15 seconds
                        wp_schedule_single_event(time() + 15, 'paytoday_check_payment_status', array($order_id, $payment_token, $authorization_token));
                        return;
                    }
                    
                    // Log the full status result with detailed structure analysis
                    error_log('PayToday Debug - Payment status for Order #' . $order_id . ': ' . print_r($status_result, true));
                    $this->log('Payment status for Order #' . $order_id . ': ' . print_r($status_result, true));
                    
                    // Log detailed structure analysis
                    if (is_object($status_result)) {
                        error_log('PayToday Debug - Status result is an object');
                        if (isset($status_result->data)) {
                            error_log('PayToday Debug - Status result has data property: ' . print_r($status_result->data, true));
                            if (is_object($status_result->data)) {
                                $data_properties = get_object_vars($status_result->data);
                                error_log('PayToday Debug - Data properties: ' . implode(', ', array_keys($data_properties)));
                                foreach ($data_properties as $key => $value) {
                                    error_log('PayToday Debug - Data.' . $key . ': ' . print_r($value, true));
                                }
                            }
                        } else {
                            error_log('PayToday Debug - Status result does not have data property');
                        }
                    } else {
                        error_log('PayToday Debug - Status result is not an object: ' . gettype($status_result));
                    }
                    
                    // Check if payment is completed
                    if (isset($status_result->data) && isset($status_result->data->intent) && isset($status_result->data->intent->transaction_status)) {
                        $payment_status = $status_result->data->intent->transaction_status;
                        error_log('PayToday Debug - Payment status for Order #' . $order_id . ' is: ' . $payment_status);
                        $this->log('Payment status for Order #' . $order_id . ' is: ' . $payment_status);
                        
                        if ($payment_status === 'completed' || $payment_status === 'success') {
                            // ===== SUCCESS TRIGGER - CRON/BACKGROUND CHECK =====
                            // Payment completed successfully
                            error_log('PayToday Debug - PAYMENT SUCCESSFUL! Order #' . $order_id . ' - Status: ' . $payment_status);
                            $this->log('PAYMENT SUCCESSFUL! Order #' . $order_id . ' - Status: ' . $payment_status);
                            $order->payment_complete();                                    // MARKS ORDER AS COMPLETED
                            $order->add_order_note('Payment completed successfully via PayToday');  // ADDS SUCCESS NOTE
                            error_log('PayToday Debug - Order #' . $order_id . ' marked as completed');
                            $this->log('Order #' . $order_id . ' marked as completed');
                            return;                                                        // STOPS POLLING
                        } elseif ($payment_status === 'failed' || $payment_status === 'cancelled') {
                            // Payment failed
                            $order->update_status('failed', 'Payment failed via PayToday');
                            error_log('PayToday Debug - Order #' . $order_id . ' marked as failed');
                            $this->log('Order #' . $order_id . ' marked as failed');
                            return;
                        }
                    }
                    
                    // If payment is still pending, schedule another check
                    error_log('PayToday Debug - Payment still pending for Order #' . $order_id . ', scheduling next check');
                    $this->log('Payment still pending for Order #' . $order_id . ', scheduling next check');
                    wp_schedule_single_event(time() + 15, 'paytoday_check_payment_status', array($order_id, $payment_token, $authorization_token));
                }


                // Manual trigger for testing payment status (bypasses cron)
                public function manual_check_payment_status($order_id) {
                    $payment_token = get_post_meta($order_id, '_paytoday_payment_token', true);
                    $authorization_token = get_post_meta($order_id, '_paytoday_authorization_token', true);
                    
                    if ($payment_token && $authorization_token) {
                        error_log('PayToday Debug - Manual payment status check triggered for Order #' . $order_id);
                        $this->check_payment_status($order_id, $payment_token, $authorization_token);
                    } else {
                        error_log('PayToday Debug - Manual check failed: Missing tokens for Order #' . $order_id);
                    }
                }

                // Add popup script for payment
                public function add_payment_popup_script() {
                    if (is_checkout() && isset($_GET['paytoday_popup']) && isset($_GET['order_id'])) {
                        $order_id = intval($_GET['order_id']);
                        $payment_url = get_post_meta($order_id, '_paytoday_payment_url', true);
                        
                        if ($payment_url) {
                            ?>
                            <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                var orderId = <?php echo $order_id; ?>;
                                var popup;
                                var pollInterval;
                                var checkClosedInterval;
                                var pollCount = 0;
                                
                                // Open payment URL in popup window
                                popup = window.open('<?php echo esc_js($payment_url); ?>', 'paytoday_payment', 'width=800,height=600,scrollbars=yes,resizable=yes');
                                
                                // Log popup opening
                                console.log('PayToday: Payment popup opened for Order #' + orderId);
                                
                                // Check if popup was blocked
                                if (!popup || popup.closed || typeof popup.closed == 'undefined') {
                                    alert('Please allow popups for this site to complete your payment.');
                                    return;
                                }
                                
                                // Focus on popup
                                popup.focus();
                                
                                // Start polling immediately
                                console.log('PayToday: Starting payment status polling for Order #' + orderId);
                                checkPaymentStatus();
                                
                                function checkPaymentStatus() {
                                    pollCount++;
                                    console.log('PayToday: Checking payment status for Order #' + orderId + ' (check #' + pollCount + ')');
                                    
                                    $.ajax({
                                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                        type: 'POST',
                                        data: {
                                            action: 'paytoday_check_status',
                                            order_id: orderId,
                                            nonce: '<?php echo wp_create_nonce('paytoday_status_check'); ?>'
                                        },
                                        success: function(response) {
                                            console.log('PayToday: Status check response for Order #' + orderId + ':', response);
                                            
                                            if (response.success && response.data.completed) {
                                                console.log('PayToday: Payment completed for Order #' + orderId);
                                                stopPolling();
                                                
                                                // Close popup
                                                if (popup && !popup.closed) {
                                                    popup.close();
                                                }
                                                
                                                // Redirect to success page
                                                window.location.href = response.data.redirect_url;
                                            } else if (response.success && response.data.failed) {
                                                console.log('PayToday: Payment failed for Order #' + orderId);
                                                stopPolling();
                                                
                                                // Close popup
                                                if (popup && !popup.closed) {
                                                    popup.close();
                                                }
                                                
                                                alert('Payment failed. Please try again.');
                                            } else if (response.success && response.data.pending) {
                                                // Still pending, continue polling
                                                console.log('PayToday: Payment still pending for Order #' + orderId);
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('PayToday: Status check error for Order #' + orderId + ':', error);
                                        }
                                    });
                                }
                                
                                function stopPolling() {
                                    if (pollInterval) {
                                        clearInterval(pollInterval);
                                        pollInterval = null;
                                    }
                                    if (checkClosedInterval) {
                                        clearInterval(checkClosedInterval);
                                        checkClosedInterval = null;
                                    }
                                    console.log('PayToday: Polling stopped for Order #' + orderId);
                                }
                                
                                // Start polling every 15 seconds
                                pollInterval = setInterval(checkPaymentStatus, 15000);
                                
                                // Optional: Check if popup is closed (but don't stop polling)
                                checkClosedInterval = setInterval(function() {
                                    try {
                                        if (popup && popup.closed === true) {
                                            console.log('PayToday: Popup closed, but continuing polling for Order #' + orderId);
                                            // Don't stop polling - let it continue until payment completes
                                        }
                                    } catch (e) {
                                        console.log('PayToday: Cannot access popup, continuing polling for Order #' + orderId);
                                        // Don't stop polling - let it continue
                                    }
                                }, 10000); // Check every 10 seconds
                                
                                // Add a maximum polling time (30 minutes)
                                setTimeout(function() {
                                    console.log('PayToday: Maximum polling time reached, stopping for Order #' + orderId);
                                    stopPolling();
                                }, 30 * 60 * 1000); // 30 minutes
                            });
                            </script>
                            <?php
                        }
                    }
                }

        /*
         * Fields validation, more in Step 5
         */
        public function validate_fields() {
            // Debug: Log that validation is being called
            error_log('PayToday Debug - validate_fields() method called');
            error_log('PayToday Debug - POST data count: ' . count($_POST));
            error_log('PayToday Debug - POST keys: ' . implode(', ', array_keys($_POST)));
            error_log('PayToday Debug - Full POST data: ' . print_r($_POST, true));
            error_log('PayToday Debug - REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
            error_log('PayToday Debug - Current action: ' . (isset($_POST['action']) ? $_POST['action'] : 'no action'));
            error_log('PayToday Debug - REST_REQUEST: ' . (defined('REST_REQUEST') ? 'true' : 'false'));
            error_log('PayToday Debug - is_checkout(): ' . (function_exists('is_checkout') ? (is_checkout() ? 'true' : 'false') : 'function not available'));
            error_log('PayToday Debug - wp_doing_ajax(): ' . (wp_doing_ajax() ? 'true' : 'false'));
            
            // Check if we have the required billing data
            if (count($_POST) <= 1) {
                error_log('PayToday Debug - Not enough POST data for validation, skipping');
                return true; // Skip validation if no billing data
            }
            
            // Check if this is an API request (WooCommerce REST API)
            if (defined('REST_REQUEST') && REST_REQUEST) {
                error_log('PayToday Debug - This is a REST API request, skipping validation');
                return true; // Skip validation for API requests
            }
            
            // Check if we're in a proper checkout context
            if (!is_checkout() && !wp_doing_ajax()) {
                error_log('PayToday Debug - Not in checkout context, skipping validation');
                return true; // Skip validation if not in checkout
            }
            
            error_log('PayToday Debug - Proceeding with validation...');
            
            // Get the posted data - using billing fields (standard WooCommerce)
            $billing_email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';
            $billing_first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : '';
            $billing_last_name = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : '';
            $billing_country = isset($_POST['billing_country']) ? sanitize_text_field($_POST['billing_country']) : '';
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';
            $billing_state = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
            $billing_postcode = isset($_POST['billing_postcode']) ? sanitize_text_field($_POST['billing_postcode']) : '';
            $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';

            // Validate email address
            if (empty($billing_email)) {
                wc_add_notice('Email address is required.', 'error');
                return false;
            }

            if (!is_email($billing_email)) {
                wc_add_notice('Please enter a valid email address.', 'error');
                return false;
            }

            // Validate first name
            if (empty($billing_first_name)) {
                wc_add_notice('First name is required.', 'error');
                return false;
            }

            // Validate last name
            if (empty($billing_last_name)) {
                wc_add_notice('Last name is required.', 'error');
                return false;
            }

            // Validate country/region
            if (empty($billing_country)) {
                wc_add_notice('Country/region is required.', 'error');
                return false;
            }

            // Validate town/city
            if (empty($billing_city)) {
                wc_add_notice('Town/city is required.', 'error');
                return false;
            }

            // Validate state/county
            if (empty($billing_state)) {
                wc_add_notice('State/county is required.', 'error');
                return false;
            }

            // Validate postcode/zip
            if (empty($billing_postcode)) {
                wc_add_notice('Postcode/zip is required.', 'error');
                return false;
            }

            // Phone is optional, so no validation needed

            error_log('PayToday Debug - Validation completed successfully');
            return true;
        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        // public function process_payment( $order_id ) {

        // ...
                    
        // }

        /*
         * In case you need a webhook, like PayPal IPN etc
         */
        // public function webhook() {

        // ...
                    
        // }
    
        /**
         * Handle front-channel return from PayToday
         */
        public function handle_paytoday_return() {
            $status           = isset($_GET['status']) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
            $gateway_ref      = isset($_GET['reference']) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';
            $invoice_number   = isset($_GET['invoice_number']) ? absint( $_GET['invoice_number'] ) : 0;
            $reference_number = isset($_GET['reference_number']) ? sanitize_text_field( wp_unslash( $_GET['reference_number'] ) ) : '';

            if ( ! $invoice_number ) { status_header(400); echo 'Missing invoice_number.'; exit; }
            $order = wc_get_order( $invoice_number );
            if ( ! $order ) { status_header(404); echo 'Order not found.'; exit; }

            if ( strtolower( $status ) === 'success' ) {
                if ( $order->has_status( array( 'pending', 'failed', 'on-hold' ) ) ) {
                    $order->payment_complete( $gateway_ref ?: null );
                    $order->add_order_note( 'PayToday: payment success. Reference: ' . $gateway_ref . ' / Ref No: ' . $reference_number );
                }
                wp_safe_redirect( $this->get_return_url( $order ) ); exit;
            }

            if ( ! $order->has_status( 'failed' ) ) {
                $order->update_status( 'failed', 'PayToday: payment failed or cancelled. Status=' . $status . ' Reference=' . $gateway_ref );
            }
            wp_safe_redirect( wc_get_checkout_url() ); exit;
        }
}
}

// Register the cron hook for payment status checking
add_action('paytoday_check_payment_status', 'paytoday_handle_payment_status_check', 10, 3);
function paytoday_handle_payment_status_check($order_id, $payment_token, $authorization_token) {
    // Get the gateway instance
    $gateways = WC()->payment_gateways->payment_gateways();
    if (isset($gateways['paytoday'])) {
        $gateway = $gateways['paytoday'];
        $gateway->check_payment_status($order_id, $payment_token, $authorization_token);
    }
}

// Register the interval check hook
add_action('paytoday_interval_check', 'paytoday_handle_interval_check', 10, 3);
function paytoday_handle_interval_check($order_id, $payment_token, $authorization_token) {
    // Get the gateway instance
    $gateways = WC()->payment_gateways->payment_gateways();
    if (isset($gateways['paytoday'])) {
        $gateway = $gateways['paytoday'];
        $gateway->do_payment_status_check($order_id, $payment_token, $authorization_token);
    }
}

// Add manual status check endpoint for testing
add_action('init', 'paytoday_manual_status_check');
function paytoday_manual_status_check() {
    if (isset($_GET['paytoday_manual_check']) && isset($_GET['order_id'])) {
        $order_id = intval($_GET['order_id']);
        $gateways = WC()->payment_gateways->payment_gateways();
        if (isset($gateways['paytoday'])) {
            $gateway = $gateways['paytoday'];
            $gateway->manual_check_payment_status($order_id);
            echo "Manual status check triggered for Order #" . $order_id . ". Check the logs.";
            exit;
        }
    }
}

// AJAX handler for payment status checking
add_action('wp_ajax_paytoday_check_status', 'paytoday_ajax_check_status');
add_action('wp_ajax_nopriv_paytoday_check_status', 'paytoday_ajax_check_status');
function paytoday_ajax_check_status() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'paytoday_status_check')) {
        wp_die('Security check failed');
    }
    
    $order_id = intval($_POST['order_id']);
    $gateways = WC()->payment_gateways->payment_gateways();
    
    // Debug: Log the AJAX request details
    error_log('PayToday Debug - AJAX Status check request for Order #' . $order_id);
    error_log('PayToday Debug - POST data: ' . print_r($_POST, true));
    
    if (isset($gateways['paytoday'])) {
        $gateway = $gateways['paytoday'];
        $payment_token = get_post_meta($order_id, '_paytoday_payment_token', true);
        $authorization_token = get_post_meta($order_id, '_paytoday_authorization_token', true);
        
        // Debug: Log token retrieval
        error_log('PayToday Debug - Retrieved payment_token: ' . ($payment_token ? 'EXISTS (length: ' . strlen($payment_token) . ')' : 'NULL/EMPTY'));
        error_log('PayToday Debug - Retrieved authorization_token: ' . ($authorization_token ? 'EXISTS (length: ' . strlen($authorization_token) . ')' : 'NULL/EMPTY'));
        
        // Debug: Log all meta data for this order
        $all_meta = get_post_meta($order_id);
        error_log('PayToday Debug - All meta data for Order #' . $order_id . ': ' . print_r($all_meta, true));
        
        if ($payment_token && $authorization_token) {
            // Check payment status
            $status_result = $gateway->query_payment_intent($payment_token, $authorization_token);
            
            if (isset($status_result->error)) {
                wp_send_json_error(array('message' => 'Status check failed: ' . $status_result->error));
                return;
            }
            
            // Log the status result for debugging
            error_log('PayToday Debug - AJAX Status check for Order #' . $order_id . ': ' . print_r($status_result, true));
            
            // Check if payment is completed
            if (isset($status_result->data) && isset($status_result->data->intent) && isset($status_result->data->intent->transaction_status)) {
                $payment_status = $status_result->data->intent->transaction_status;
                error_log('PayToday Debug - AJAX Payment status for Order #' . $order_id . ' is: ' . $payment_status);
                
                if ($payment_status === 'completed' || $payment_status === 'success') {
                    // ===== SUCCESS TRIGGER - AJAX POLLING CHECK =====
                    // Payment completed successfully
                    error_log('PayToday Debug - PAYMENT SUCCESSFUL! Order #' . $order_id . ' - Status: ' . $payment_status);
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $order->payment_complete();                                // MARKS ORDER AS COMPLETED
                        $order->add_order_note('Payment completed successfully via PayToday');  // ADDS SUCCESS NOTE
                        
                        wp_send_json_success(array(
                            'completed' => true,                                   // TELLS JAVASCRIPT SUCCESS
                            'redirect_url' => $order->get_checkout_order_received_url()  // PROVIDES REDIRECT URL
                        ));
                        return;                                                    // STOPS AJAX POLLING
                    }
                } elseif ($payment_status === 'failed' || $payment_status === 'cancelled') {
                    // Payment failed
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $order->update_status('failed', 'Payment failed via PayToday');
                    }
                    
                    wp_send_json_success(array('failed' => true));
                    return;
                }
            }
            
            // Still pending
            wp_send_json_success(array('pending' => true, 'status_data' => $status_result));
        } else {
            wp_send_json_error(array('message' => 'Missing payment tokens'));
        }
    } else {
        wp_send_json_error(array('message' => 'PayToday gateway not found'));
    }
}

add_action( 'woocommerce_blocks_loaded', 'paytoday_gateway_block_support' );
function paytoday_gateway_block_support() {

    // if( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    //  return;
    // }

    // here we're including our "gateway block support class"
    require_once __DIR__ . '/includes/class-wc-PayToday-gateway-blocks-support.php';

    // registering the PHP class we have just included
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            $payment_method_registry->register( new WC_PayToday_Gateway_Blocks_Support );
        }
    );

}



add_action( 'before_woocommerce_init', 'paytoday_cart_checkout_blocks_compatibility' );

function paytoday_cart_checkout_blocks_compatibility() {

    if( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                false // true (compatible, default) or false (not compatible)
            );
    }
        
}
