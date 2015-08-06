<?php
    /*
    Plugin Name: LDAP/AD Login for Intranet sites
    Plugin URI: http://miniorange.com
    Description: LDAP/AD Login Plugin provides login to WordPress using credentials stored in your LDAP Server.
    Author: miniorange
    Version: 1.0
    Author URI: http://miniorange.com
    */

	require_once 'mo_ldap_pages.php';
	require('mo_ldap_support.php');
	require('class-mo-ldap-customer-setup.php');
	require('class-mo-ldap-utility.php');
	require('class-mo-ldap-config.php');
	error_reporting(E_ERROR);
	class Mo_Ldap_Local_Login{

		function __construct(){
			add_action('admin_menu', array($this, 'mo_ldap_local_login_widget_menu'));
			add_action('admin_init', array($this, 'login_widget_save_options'));
			add_action( 'admin_enqueue_scripts', array( $this, 'mo_ldap_local_settings_style' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'mo_ldap_local_settings_script' ) );
			add_action('parse_request', array($this, 'parse_sso_request'));
			remove_action( 'admin_notices', array( $this, 'success_message') );
			remove_action( 'admin_notices', array( $this, 'error_message') );
			add_filter('query_vars', array($this, 'plugin_query_vars'));
			register_deactivation_hook(__FILE__, array( $this, 'mo_ldap_local_deactivate'));
			add_action( 'login_footer', 'mo_ldap_local_link' );
			if(get_option('mo_ldap_local_enable_login') == 1){
				remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
				add_filter('authenticate', array($this, 'ldap_login'), 20, 3);
			}
			register_activation_hook( __FILE__, array($this,'mo_ldap_activate')) ;
		}

		function ldap_login($user, $username, $password){
			if(empty($username) || empty ($password)){
				//create new error object and add errors to it.
				$error = new WP_Error();

				if(empty($username)){ //No email
					$error->add('empty_username', __('<strong>ERROR</strong>: Email field is empty.'));
				}

				if(empty($password)){ //No password
					$error->add('empty_password', __('<strong>ERROR</strong>: Password field is empty.'));
				}
				return $error;
			}

			$mo_ldap_config = new Mo_Ldap_Local_Config();
			$status = $mo_ldap_config->ldap_login($username, $password);

			if($status == 'SUCCESS'){
				
				//Send request to miniOrange if enabled
			    if(get_option('mo_ldap_local_enable_log_requests') == true){
					$request_type = "User Login through LDAP";
					$status_message = "Successful Authentication";
					$mo_ldap_config->send_audit_request($username, $request_type, $status, $status_message);		
				}
				
			  if( username_exists( $username)) {
				  $user = get_userdatabylogin($username);
				  return $user;
			   } else {

					   if(!get_option('mo_ldap_local_register_user')) {
							$error = new WP_Error();
							$error->add('registration_disabled_error', __('<strong>ERROR</strong>: Your Administrator has not enabled Auto Registration. Please contact your Administrator.'));
							return $error;
						}else{
							//create user if not exists
						   $random_password 	= wp_generate_password( 10, false );
						   $userdata = array(
								'user_login'  =>  $username,
								'user_pass'   =>  $random_password  // When creating an user, `user_pass` is expected.
							);
							$user_id = wp_insert_user( $userdata ) ;

							//On success
							if( !is_wp_error($user_id) ) {
								$user = get_userdatabylogin($username);
								return $user;
							}else{
								$error = new WP_Error();
								$error->add('registration_error', __('<strong>ERROR</strong>: There was an error registering your account. Please try again.'));
								return $error;
							}
					}
				}
								
				wp_redirect( site_url() );
				exit;

			} else if($status == 'LDAP_ERROR'){
				$error = new WP_Error();
				$error->add('curl_error', __('<strong>ERROR</strong>: <a target="_blank" href="http://php.net/manual/en/ldap.installation.php">PHP LDAP extension</a> is not installed or disabled. Please enable it.'));
				
				if(get_option('mo_ldap_local_enable_log_requests') == true){
					$request_type = "User Login through LDAP";
					$status_message = "LDAP Error";
					$mo_ldap_config->send_audit_request($username, $request_type, $status, $status_message);
				}
				
				return $error;
			} else if($status == 'CURL_ERROR'){
				$error = new WP_Error();
				$error->add('curl_error', __('<strong>ERROR</strong>: <a href="http://php.net/manual/en/curl.installation.php">PHP cURL extension</a> is not installed or disabled.'));
				
				if(get_option('mo_ldap_local_enable_log_requests') == true){
					$request_type = "User Login through LDAP";
					$status_message = "cURL Error";
					$mo_ldap_config->send_audit_request($username, $request_type, $status, $status_message);
				}
				
				return $error;
			} else {
				$error = new WP_Error();
				$error->add('incorrect_credentials', __('<strong>ERROR</strong>: Invalid username or incorrect password. Please try again.'));
				
				if(get_option('mo_ldap_local_enable_log_requests') == true){
					$request_type = "User Login through LDAP";
					$status_message = "Incorrect Credentials";
					$mo_ldap_config->send_audit_request($username, $request_type, $status, $status_message);
				}
				
				return $error;
			}
		}

		function mo_ldap_local_login_widget_menu(){
			add_menu_page ('LDAP/AD Login for Intranet', 'LDAP/AD Login for Intranet', 'activate_plugins', 'mo_ldap_local_login', array( $this, 'mo_ldap_local_login_widget_options'),plugin_dir_url(__FILE__) . 'includes/images/miniorange_icon.png');
		}

		function mo_ldap_local_login_widget_options(){
			update_option( 'mo_ldap_local_host_name', 'https://auth.miniorange.com' );
			//Setting default configuration
			$default_config = array(
				'server_url' => 'ldap://58.64.132.235:389',
				'service_account_dn' => 'cn=testuser,cn=Users,dc=miniorange,dc=com',
				'admin_password' => 'XXXXXXXX',
				'dn_attribute' => 'distinguishedName',
				'search_base' => 'cn=Users,dc=miniorange,dc=com',
				'search_filter' => '(&(objectClass=*)(cn=?))',
				'test_username' => 'testuser',
				'test_password' => 'password'
			);
			update_option( 'mo_ldap_local_default_config', $default_config );
			mo_ldap_local_settings();
		}

		function login_widget_save_options(){
			if(isset($_POST['option'])){
				if($_POST['option'] == "mo_ldap_local_register_customer") {		//register the customer

					//validate and sanitize
					$email = '';
					$phone = '';
					$password = '';
					$confirmPassword = '';
					if( Mo_Ldap_Local_Util::check_empty_or_null( $_POST['email'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['phone'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['password'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['confirmPassword'] ) ) {
						update_option( 'mo_ldap_local_message', 'All the fields are required. Please enter valid entries.');
						$this->show_error_message();
						return;
					} else if( strlen( $_POST['password'] ) < 6 || strlen( $_POST['confirmPassword'] ) < 6){	//check password is of minimum length 6
						update_option( 'mo_ldap_local_message', 'Choose a password with minimum length 6.');
						$this->show_error_message();
						return;
					} else{
						$email = sanitize_email( $_POST['email'] );
						$phone = sanitize_text_field( $_POST['phone'] );
						$password = sanitize_text_field( $_POST['password'] );
						$confirmPassword = sanitize_text_field( $_POST['confirmPassword'] );
					}
					update_option( 'mo_ldap_local_admin_email', $email );
					update_option( 'mo_ldap_local_admin_phone', $phone );

					if( strcmp( $password, $confirmPassword) == 0 ) {
						update_option( 'mo_ldap_local_password', $password );

						$customer = new Mo_Ldap_Local_Customer();
						$content = json_decode($customer->check_customer(), true);
						if( strcasecmp( $content['status'], 'CUSTOMER_NOT_FOUND') == 0 ){
							$content = json_decode($customer->send_otp_token(), true);
							if(strcasecmp($content['status'], 'SUCCESS') == 0) {
								update_option( 'mo_ldap_local_message', ' A one time passcode is sent to ' . get_option('mo_ldap_local_admin_email') . '. Please enter the otp here to verify your email.');
								update_option('mo_ldap_local_transactionId',$content['txId']);
								update_option('mo_ldap_local_registration_status','MO_OTP_DELIVERED_SUCCESS');

								$this->show_success_message();
							} else {
								update_option('mo_ldap_local_message','There was an error in sending email. Please click on Resend OTP to try again.');
								update_option('mo_ldap_local_registration_status','MO_OTP_DELIVERED_FAILURE');
								$this->show_error_message();
							}
						} else if( strcasecmp( $content['status'], 'CURL_ERROR') == 0 ){
							update_option('mo_ldap_local_message', $content['statusMessage']);
							update_option('mo_ldap_local_registration_status','MO_OTP_DELIVERED_FAILURE');
							$this->show_error_message();
						} else{
							$content = $customer->get_customer_key();
							$customerKey = json_decode($content, true);
							if(json_last_error() == JSON_ERROR_NONE) {
								$this->save_success_customer_config($customerKey['id'], $customerKey['apiKey'], $customerKey['token'], 'Your account has been retrieved successfully.');
								update_option('mo_ldap_local_password', '');
							} else {
								update_option( 'mo_ldap_local_message', 'You already have an account with miniOrange. Please enter a valid password.');
								update_option('mo_ldap_local_verify_customer', 'true');
								delete_option('mo_ldap_local_new_registration');
								$this->show_error_message();
							}
						}

					} else {
						update_option( 'mo_ldap_local_message', 'Password and Confirm password do not match.');
						delete_option('mo_ldap_local_verify_customer');
						$this->show_error_message();
					}
				}
				else if( $_POST['option'] == "mo_ldap_local_verify_customer" ) {	//login the admin to miniOrange

					//validation and sanitization
					$email = '';
					$password = '';
					if( Mo_Ldap_Local_Util::check_empty_or_null( $_POST['email'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['password'] ) ) {
						update_option( 'mo_ldap_local_message', 'All the fields are required. Please enter valid entries.');
						$this->show_error_message();
						return;
					} else{
						$email = sanitize_email( $_POST['email'] );
						$password = sanitize_text_field( $_POST['password'] );
					}

					update_option( 'mo_ldap__localadmin_email', $email );
					update_option( 'mo_ldap_local_password', $password );
					$customer = new Mo_Ldap_Local_Customer();
					$content = $customer->get_customer_key();
					$customerKey = json_decode( $content, true );
					if( strcasecmp( $customerKey['apiKey'], 'CURL_ERROR') == 0) {
						update_option('mo_ldap_local_message', $customerKey['token']);
						$this->show_error_message();
					} else if( json_last_error() == JSON_ERROR_NONE ) {
						update_option( 'mo_ldap_local_admin_phone', $customerKey['phone'] );
						$this->save_success_customer_config($customerKey['id'], $customerKey['apiKey'], $customerKey['token'], 'Your account has been retrieved successfully.');
						update_option('mo_ldap_local_password', '');
					} else {
						update_option( 'mo_ldap_local_message', 'Invalid username or password. Please try again.');
						$this->show_error_message();
					}
					update_option('mo_ldap_local_password', '');
				}
				else if( $_POST['option'] == "mo_ldap_local_enable" ) {		//enable ldap login
					update_option( 'mo_ldap_local_enable_login', isset($_POST['enable_ldap_login']) ? $_POST['enable_ldap_login'] : 0);
					if(get_option('mo_ldap_local_enable_login')) {
						update_option( 'mo_ldap_local_message', 'Login through your LDAP has been enabled.');
						$this->show_success_message();
					} else {
						update_option( 'mo_ldap_local_message', 'Login through your LDAP has been disabled.');
						$this->show_success_message();
					}
				} else if($_POST['option'] == "mo_ldap_local_enable_log_requests"){
					update_option( 'mo_ldap_local_enable_log_requests', isset($_POST['enable_log_requests']) ? $_POST['enable_log_requests'] : 0);
					if(get_option('mo_ldap_local_enable_log_requests')) {
						update_option( 'mo_ldap_local_message', 'Log requests enabled');
						$this->show_success_message();
					} else {
						update_option( 'mo_ldap_local_message', 'Log requests disabled.');
						$this->show_success_message();
					}
				}
				else if( $_POST['option'] == "mo_ldap_local_register_user" ) {		//enable auto registration of users
					update_option( 'mo_ldap_local_register_user', isset($_POST['mo_ldap_local_register_user']) ? $_POST['mo_ldap_local_register_user'] : 0);
					if(get_option('mo_ldap_local_register_user')) {
						update_option( 'mo_ldap_local_message', 'Auto Registering users has been enabled.');
						$this->show_success_message();
					} else {
						update_option( 'mo_ldap_local_message', 'Auto Registering users has been disabled.');
						$this->show_success_message();
					}
				}
				else if( $_POST['option'] == "mo_ldap_local_save_config" ) {		//save ldap configuration

					//validation and sanitization
					$server_name = '';
					$dn = '';
					$admin_ldap_password = '';
					$dn_attribute = '';
					$search_base = '';
					$search_filter = '';
					if( Mo_Ldap_Local_Util::check_empty_or_null( $_POST['ldap_server'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['dn'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['admin_password'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['dn_attribute'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['search_base'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['search_filter'] ) ) {
						update_option( 'mo_ldap_local_message', 'All the fields are required. Please enter valid entries.');
						$this->show_error_message();
						return;
					} else{
						$server_name = sanitize_text_field( $_POST['ldap_server'] );
						$dn = sanitize_text_field( $_POST['dn'] );
						$admin_ldap_password = sanitize_text_field( $_POST['admin_password'] );
						$dn_attribute = sanitize_text_field( $_POST['dn_attribute'] );
						$search_base = sanitize_text_field( $_POST['search_base'] );
						$search_filter = sanitize_text_field( $_POST['search_filter'] );
					}

					//Encrypting all fields and storing them
					update_option( 'mo_ldap_local_server_url', Mo_Ldap_Local_Util::encrypt($server_name));
					update_option( 'mo_ldap_local_server_dn', Mo_Ldap_Local_Util::encrypt($dn));
					update_option( 'mo_ldap_local_server_password', Mo_Ldap_Local_Util::encrypt($admin_ldap_password));
					update_option( 'mo_ldap_local_dn_attribute', Mo_Ldap_Local_Util::encrypt($dn_attribute));
					update_option( 'mo_ldap_local_search_base', Mo_Ldap_Local_Util::encrypt($search_base));
					update_option( 'mo_ldap_local_search_filter', Mo_Ldap_Local_Util::encrypt($search_filter));

					delete_option('mo_ldap_local_message');
					$mo_ldap_config = new Mo_Ldap_Local_Config();

					//Save LDAP configuration
					$save_content = $mo_ldap_config->save_ldap_config();
					$message =  'Your configuration has been saved.';
					$status = 'success';

					//Test connection with the LDAP configuration provided. This makes a call to check if connection is established successfully.
					$content = $mo_ldap_config->test_connection(null);
					$response = json_decode( $content, true );
					if(strcasecmp($response['statusCode'], 'SUCCESS') == 0) {
						add_option( 'mo_ldap_local_message', $message . ' Connection was established successfully. Please test authentication to verify LDAP User Mapping Configuration.', '', 'no');
						$this->show_success_message();
					} else if(strcasecmp($response['statusCode'], 'ERROR') == 0) {
						add_option( 'mo_ldap_local_message', $response['statusMessage'], '', 'no' );
						$this->show_error_message();
					} else if( strcasecmp( $response['statusCode'], 'LDAP_ERROR') == 0) {
						add_option( 'mo_ldap_local_message', $response['statusMessage'], '', 'no');
						$this->show_error_message();
					} else {
						add_option( 'mo_ldap_local_message', $message . ' There was an error in connecting with the current settings. Make sure you have entered server url in format ldap://domain.com:port. Test using Ping LDAP Server.', '', 'no');
						$this->show_error_message();
					}
				}
				else if( $_POST['option'] == "mo_ldap_local_test_auth" ) {		//test authentication with current settings
					$server_name = get_option( 'mo_ldap_local_server_url');
					$dn = get_option( 'mo_ldap_local_server_dn');
					$admin_ldap_password = get_option( 'mo_ldap_local_server_password');
					$dn_attribute = get_option( 'mo_ldap_local_dn_attribute');
					$search_base = get_option( 'mo_ldap_local_search_base');
					$search_filter = get_option( 'mo_ldap_local_search_filter');
					
					delete_option('mo_ldap_local_message');
					
					//validation and sanitization
					$test_username = '';
					$test_password = '';
					//Check if username and password are empty
					if( Mo_Ldap_Local_Util::check_empty_or_null( $_POST['test_username'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['test_password'] ) ) {
						add_option( 'mo_ldap_local_message', 'All the fields are required. Please enter valid entries.', '', 'no');
						$this->show_error_message();
						return;
					}
					//Check if configuration is saved
					else if( Mo_Ldap_Local_Util::check_empty_or_null( $server_name ) || Mo_Ldap_Local_Util::check_empty_or_null( $dn ) || Mo_Ldap_Local_Util::check_empty_or_null( 		$admin_ldap_password ) || Mo_Ldap_Local_Util::check_empty_or_null( $dn_attribute ) || Mo_Ldap_Local_Util::check_empty_or_null( $search_base ) || Mo_Ldap_Local_Util::check_empty_or_null( $search_filter ) ) {
						add_option( 'mo_ldap_local_message', 'Please save LDAP Configuration to test authentication.', '', 'no');
						$this->show_error_message();
						return;
					} else{
						$test_username = sanitize_text_field( $_POST['test_username'] );
						$test_password = sanitize_text_field( $_POST['test_password'] );
					}
					//Call to authenticate test
					$mo_ldap_config = new Mo_Ldap_Local_Config();
					$content = $mo_ldap_config->test_authentication($test_username, $test_password, null);
					$response = json_decode( $content, true );

					if(strcasecmp($response['statusCode'], 'SUCCESS') == 0) {
						add_option( 'mo_ldap_local_message', 'Test is successful! Your credentials have matched.', '', 'no');
						$this->show_success_message();
					} else if(strcasecmp($response['statusCode'], 'ERROR') == 0) {
						add_option( 'mo_ldap_local_message', $response['statusMessage'], '', 'no');
						$this->show_error_message();
					} else if( strcasecmp( $response['statusCode'], 'LDAP_ERROR') == 0) {
						add_option('mo_ldap_local_message', $response['statusMessage'], '', 'no');
						$this->show_error_message();
					} else if( strcasecmp( $response['statusCode'], 'CURL_ERROR') == 0) {
						add_option('mo_ldap_local_message', $response['statusMessage'], '', 'no');
						$this->show_error_message();
					} else {
						add_option( 'mo_ldap_local_message', 'There was an error processing your request. Please verify the Search Base(s) and Search filter. Your user should be present in the Search base defined.', '', 'no');
						$this->show_error_message();
					}
					//Send request if enabled
					if(get_option('mo_ldap_local_enable_log_requests') == true){
						$request_type = "Test Authentication";
						$mo_ldap_config->send_audit_request($test_username, $request_type, $response['statusCode'], $response['statusMessage']);
					}
				}
				else if($_POST['option'] == "mo_ldap_local_login_send_query"){
					$query = '';
					if( Mo_Ldap_Local_Util::check_empty_or_null( $_POST['query_email'] ) || Mo_Ldap_Local_Util::check_empty_or_null( $_POST['query'] ) ) {
						update_option( 'mo_ldap_local_message', 'Please submit your query along with email.');
						$this->show_error_message();
						return;
					} else{
						$query = sanitize_text_field( $_POST['query'] );
						$email = sanitize_text_field( $_POST['query_email'] );
						$phone = sanitize_text_field( $_POST['query_phone'] );
						$contact_us = new Mo_Ldap_Local_Customer();
						$submited = json_decode($contact_us->submit_contact_us($email, $phone, $query),true);

						if( strcasecmp( $submited['status'], 'CURL_ERROR') == 0) {
							update_option('mo_ldap_local_message', $submited['statusMessage']);
							$this->show_error_message();
						} else if(json_last_error() == JSON_ERROR_NONE) {
							if ( $submited == false ) {
								update_option('mo_ldap_local_message', 'Your query could not be submitted. Please try again.');
								$this->show_error_message();
							} else {
								update_option('mo_ldap_local_message', 'Thanks for getting in touch! We shall get back to you shortly.');
								$this->show_success_message();
							}
						}

					}
				}
				else if( $_POST['option'] == "mo_ldap_local_resend_otp" ) {			//send OTP to user to verify email
					$customer = new Mo_Ldap_Local_Customer();
					$content = json_decode($customer->send_otp_token(), true);
					if(strcasecmp($content['status'], 'SUCCESS') == 0) {
							update_option( 'mo_ldap_local_message', ' A one time passcode is sent to ' . get_option('mo_ldap_admin_email') . ' again. Please enter the OTP recieved.');
							update_option('mo_ldap_local_transactionId',$content['txId']);
							update_option('mo_ldap_local_registration_status','MO_OTP_DELIVERED_SUCCESS');
							$this->show_success_message();
					} else if( strcasecmp( $content['status'], 'CURL_ERROR') == 0) {
						update_option('mo_ldap_local_message', $content['statusMessage']);
						update_option('mo_ldap_local_registration_status','MO_OTP_DELIVERED_FAILURE');
						$this->show_error_message();
					} else{
							update_option('mo_ldap_local_message','There was an error in sending email. Please click on Resend OTP to try again.');
							update_option('mo_ldap_local_registration_status','MO_OTP_DELIVERED_FAILURE');
							$this->show_error_message();
					}
				}
				else if( $_POST['option'] == "mo_ldap_local_validate_otp"){		//verify OTP entered by user

					//validation and sanitization
					$otp_token = '';
					if( Mo_Ldap_Local_Util::check_empty_or_null( $_POST['otp_token'] ) ) {
						update_option( 'mo_ldap_local_message', 'Please enter a value in otp field.');
						update_option('mo_ldap_local_registration_status','MO_OTP_VALIDATION_FAILURE');
						$this->show_error_message();
						return;
					} else{
						$otp_token = sanitize_text_field( $_POST['otp_token'] );
					}

					$customer = new Mo_Ldap_Local_Customer();
					$content = json_decode($customer->validate_otp_token(get_option('mo_ldap_local_transactionId'), $otp_token ),true);
					if(strcasecmp($content['status'], 'SUCCESS') == 0) {
						$customer = new Mo_Ldap_Local_Customer();
						$customerKey = json_decode($customer->create_customer(), true);
						if(strcasecmp($customerKey['status'], 'CUSTOMER_USERNAME_ALREADY_EXISTS') == 0) {	//admin already exists in miniOrange
							$content = $customer->get_customer_key();
							$customerKey = json_decode($content, true);
							if(json_last_error() == JSON_ERROR_NONE) {
								$this->save_success_customer_config($customerKey['id'], $customerKey['apiKey'], $customerKey['token'], 'Your account has been retrieved successfully.');
							} else {
								update_option( 'mo_ldap_local_message', 'You already have an account with miniOrange. Please enter a valid password.');
								update_option('mo_ldap_local_verify_customer', 'true');
								delete_option('mo_ldap_local_new_registration');
								$this->show_error_message();
							}
						} else if(strcasecmp($customerKey['status'], 'SUCCESS') == 0) { 	//registration successful
							$this->save_success_customer_config($customerKey['id'], $customerKey['apiKey'], $customerKey['token'], 'Registration complete!');
						}
						update_option('mo_ldap_local_password', '');
					} else if( strcasecmp( $content['status'], 'CURL_ERROR') == 0) {
						update_option('mo_ldap_local_message', $content['statusMessage']);
						update_option('mo_ldap_local_registration_status','MO_OTP_VALIDATION_FAILURE');
						$this->show_error_message();
					} else{
						update_option( 'mo_ldap_local_message','Invalid one time passcode. Please enter a valid otp.');
						update_option('mo_ldap_local_registration_status','MO_OTP_VALIDATION_FAILURE');
						$this->show_error_message();
					}
				} else if($_POST['option'] == 'mo_ldap_local_ping_server'){

					delete_option('mo_ldap_local_message');
					//Sanitize form fields
					$ldap_server_url = sanitize_text_field($_POST['ldap_server']);
					$mo_ldap_config = new Mo_Ldap_Local_Config();
					$response = $mo_ldap_config->ping_ldap_server($ldap_server_url);
					if(strcasecmp($response, 'SUCCESS') == 0){
						$status_message = "Successfully contacted LDAP Server";
						add_option('mo_ldap_local_message', $status_message, '', 'no');
						$this->show_success_message();
					} else if(strcasecmp($response, 'LDAP_ERROR') == 0){
						$status_message = "<a target='_blank' href='http://php.net/manual/en/ldap.installation.php'>PHP LDAP extension</a> is not installed or disabled. Please enable it.";
						add_option('mo_ldap_local_message', 'LDAP Extension is disabled: ' . $status_message, '', 'no');
						$this->show_error_message();
					} else{
						$status_message = " Please check your LDAP server address is correct e.g. <b>ldap://server_address:389</b> and if there is a firewall, please open the firewall to allow incoming requests to your LDAP from your wordpress IP and port 389.";
						add_option('mo_ldap_local_message', 'Error contacting LDAP Server: ' . $status_message, '', 'no');
						$this->show_error_message();
					}
					
					//Send request to miniOrange if enabled
					if(get_option('mo_ldap_local_enable_log_requests') == true){
						$username = null;
						$request_type = "Ping LDAP Server";
						$mo_ldap_config->send_audit_request($username, $request_type, $response, $status_message);
					}

				}
			}
		}

		/*
		 * Save all required fields on customer registration/retrieval complete.
		 */
		function save_success_customer_config($id, $apiKey, $token, $message) {
			update_option( 'mo_ldap_local_admin_customer_key', $id );
			update_option( 'mo_ldap_local_admin_api_key', $apiKey );
			update_option( 'mo_ldap_local_customer_token', $token );
			update_option( 'mo_ldap_local_enable_log_requests', true);
			update_option('mo_ldap_local_password', '');
			update_option( 'mo_ldap_local_message', $message);
			delete_option('mo_ldap_local_verify_customer');
			delete_option('mo_ldap_local_new_registration');
			delete_option('mo_ldap_local_registration_status');
			$this->show_success_message();
		}

		function mo_ldap_local_settings_style() {
			wp_enqueue_style( 'mo_ldap_admin_settings_style', plugins_url('includes/css/style_settings.css', __FILE__));
			wp_enqueue_style( 'mo_ldap_admin_settings_phone_style', plugins_url('includes/css/phone.css', __FILE__));
		}

		function mo_ldap_local_settings_script() {
			wp_enqueue_script( 'mo_ldap_admin_settings_phone_script', plugins_url('includes/js/phone.js', __FILE__ ));
			wp_enqueue_script( 'mo_ldap_admin_settings_script', plugins_url('includes/js/settings_page.js', __FILE__ ), array('jquery'));
		}

		function error_message() {
			$class = "error";
			$message = get_option('mo_ldap_local_message');
			echo "<div class='" . $class . "'> <p>" . $message . "</p></div>";
		}

		function success_message() {
			$class = "updated";
			$message = get_option('mo_ldap_local_message');
			echo "<div class='" . $class . "'> <p>" . $message . "</p></div>";
		}

		private function show_success_message() {
			remove_action( 'admin_notices', array( $this, 'error_message') );
			add_action( 'admin_notices', array( $this, 'success_message') );
		}

		private function show_error_message() {
			remove_action( 'admin_notices', array( $this, 'success_message') );
			add_action( 'admin_notices', array( $this, 'error_message') );
		}

		function plugin_query_vars($vars) {
			$vars[] = 'app_name';
			return $vars;
		}

		function parse_sso_request($wp){
			if (array_key_exists('app_name', $wp->query_vars)){
				$redirectUrl = mo_ldap_saml_login($wp->query_vars['app_name']);
				wp_redirect($redirectUrl, 302);
				exit;
			}
		}

		public function mo_ldap_activate() {
			update_option( 'mo_ldap_local_register_user',1);
		}

		public function mo_ldap_local_deactivate() {
			//delete all stored key-value pairs
			if( !Mo_Ldap_Local_Util::check_empty_or_null( get_option('mo_ldap_local_registration_status') ) ) {
				delete_option('mo_ldap_local_admin_email');
			}

			delete_option('mo_ldap_local_host_name');
			delete_option('mo_ldap_local_default_config');
			delete_option('mo_ldap_local_password');
			delete_option('mo_ldap_local_new_registration');
			delete_option('mo_ldap_local_admin_phone');
			delete_option('mo_ldap_local_verify_customer');
			delete_option('mo_ldap_local_admin_customer_key');
			delete_option('mo_ldap_local_admin_api_key');
			delete_option('mo_ldap_local_customer_token');
			delete_option('mo_ldap_local_message');

			delete_option('mo_ldap_local_enable_login');
			delete_option('mo_ldap_local_enable_log_requests');
			delete_option('mo_ldap_local_server_password');

			delete_option('mo_ldap_local_transactionId');
			delete_option('mo_ldap_local_registration_status');
		}
	}

	new Mo_Ldap_Local_Login;
?>