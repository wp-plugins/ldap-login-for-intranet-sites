<?php
/** miniOrange enables user to log in using LDAP credentials.
    Copyright (C) 2015  miniOrange

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>
* @package 		miniOrange OAuth
* @license		http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/
/**
This library is miniOrange Authentication Service. 
Contains Request Calls to LDAP Service.

**/
class Mo_Ldap_Local_Config{
	
	
	function ping_ldap_server($ldap_server_url){
		if(!Mo_Ldap_Local_Util::is_extension_installed('ldap')) {
			return "LDAP_ERROR";
		}
	
		$customer_id = get_option('mo_ldap_local_admin_customer_key');
		$application_name = $_SERVER['SERVER_NAME'];
		$admin_email = get_option('mo_ldap_local_admin_email');
		$app_type = 'WP LDAP/AD Login for Intranet Sites';
		$request_type = 'Ping LDAP Server';
		
		$ldapconn = ldap_connect($ldap_server_url);
		if ($ldapconn) {
			// binding anonymously
			$ldapbind = @ldap_bind($ldapconn);
			if ($ldapbind) {
				return "SUCCESS";
			}
		}
		return "ERROR";
	
	}
	
	function ldap_login($username, $password) {
		
		$authStatus = null;
		
		if(!Mo_Ldap_Local_Util::is_extension_installed('ldap')) {
			$authStatus = "LDAP_ERROR";
			return $authStatus;
		}
		
		$ldapconn = $this->getConnection();
		
		
		if ($ldapconn) {
			$dnAttribute = get_option('mo_ldap_local_dn_attribute') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_dn_attribute')) : '';
			$filter = get_option('mo_ldap_local_search_filter') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_search_filter')) : '';
			$search_base_string = get_option('mo_ldap_local_search_base') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_search_base')) : '';
			$ldap_bind_dn = get_option('mo_ldap_local_server_dn') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_dn')) : '';
			$ldap_bind_password = get_option('mo_ldap_local_server_password') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_password')) : '';
			$attr = array($dnAttribute);
			$filter = str_replace('?', $username, $filter);

			$search_bases = explode(";", $search_base_string);			
			$user_search_result = null;
			$info = null;
			$bind = @ldap_bind($ldapconn, $ldap_bind_dn, $ldap_bind_password);
			for($i = 0 ; $i < sizeof($search_bases) ; $i++){
				if(ldap_search($ldapconn, $search_bases[$i], $filter, $attr))
					$user_search_result = ldap_search($ldapconn, $search_bases[$i], $filter, $attr);
				else{
					$authStatus = "ERROR";
					return $authStatus;
				}
				$info = ldap_first_entry($ldapconn, $user_search_result);
				if($info)
					break;
			}
			$dnAttribute = strtolower($dnAttribute);
			if($info){
				$userDn = ldap_get_dn($ldapconn, $info);
			} else{
				$authStatus = "USER_NOT_EXIST";
				return $authStatus;
			}
				
			$isValidLogin = $this->authenticate($userDn,$password);
			if($isValidLogin){
				$authStatus = "SUCCESS";
				return $authStatus;
			}
		} else{
			$authStatus = "ERROR";
			return $authStatus;
		}

	}
	
	function save_ldap_config(){
		if(!Mo_Ldap_Local_Util::is_curl_installed()) {
			return 0;
		}
		
		$url = get_option('mo_ldap_local_host_name') . '/moas/api/ldap/update-config';
		$ch = curl_init($url);
		
		$fields = $this->get_encrypted_config('Save LDAP Configuration', null);
		$field_string = json_encode($fields);
		
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_ENCODING, "" );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );    # required for https urls
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt( $ch, CURLOPT_TIMEOUT, 1);
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'charset: UTF - 8',
			'Authorization: Basic'
		));
		curl_setopt( $ch, CURLOPT_POST, true);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $field_string);
		curl_exec($ch);
		return 1;
	}
	
	/*
	*	Test connection for default config or user config
	*/
	function test_connection($is_default) {
		
		if(!Mo_Ldap_Local_Util::is_extension_installed('ldap')) {
			return json_encode(array("statusCode"=>'LDAP_ERROR','statusMessage'=>'<a target="_blank" href="http://php.net/manual/en/ldap.installation.php">PHP LDAP extension</a> is not installed or disabled. Please enable it.'));
		}
		
		if(Mo_Ldap_Local_Util::check_empty_or_null($is_default)) {
			
			$server_name = get_option('mo_ldap_local_server_url') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_url')) : '';
			$pingResult = $this->ping_ldap_server($server_name);
			if($pingResult=='ERROR')
				return json_encode(array("statusCode"=>'PING_ERROR','statusMessage'=>$error . 'Can not connect to LDAP Server. Make sure you have entered server url in format <b>ldap://server_address:port</b> and if there is a firewall, please open the firewall to allow incoming requests to your LDAP from your wordpress IP and port 389. Also check troubleshooting or contact us using support below.'));

			$ldapconn = $this->getConnection();
			if ($ldapconn) {
				$ldap_bind_dn = get_option('mo_ldap_local_server_dn') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_dn')) : '';
				$ldap_bind_password = get_option('mo_ldap_local_server_password') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_password')) : '';
					
				$ldapbind = @ldap_bind($ldapconn, $ldap_bind_dn, $ldap_bind_password);
				// verify binding
				if ($ldapbind) {
					return json_encode(array("statusCode"=>'SUCCESS','statusMessage'=>'Connection was established successfully. Please test authentication to verify LDAP User Mapping Configuration.'));
				} else {
					return json_encode(array("statusCode"=>'ERROR','statusMessage'=>$error . 'Invalid bind account credentials. Make sure you have entered correct Service Account DN and password. Also check troubleshooting or contact us using support below.'));
				}
			} else {
				return json_encode(array("statusCode"=>'ERROR','statusMessage'=>$error . 'Invalid bind account credentials. Make sure you have entered correct Service Account DN and password. Also check troubleshooting or contact us using support below.'));
			}
			
		}else{
			// Default is removed
			return json_encode(array("statusCode"=>'SUCCESS','statusMessage'=>'Connection was established successfully. Please test authentication to verify LDAP User Mapping Configuration.'));
		}

	}
	
	/*
	*	Test authentication for default config or user config
	*/
	function test_authentication($username, $password, $is_default) {

		if(!Mo_Ldap_Local_Util::is_extension_installed('ldap')) {
			return json_encode(array("statusCode"=>'LDAP_ERROR','statusMessage'=>'<a target="_blank" href="http://php.net/manual/en/ldap.installation.php">PHP LDAP extension</a> is not installed or disabled. Please enable it.'));
		}
		
		//Check if request is for default auth
		if(Mo_Ldap_Local_Util::check_empty_or_null($is_default)) {
			
			$status = $this->ldap_login($username, $password);
				if($status=="SUCCESS")
					return json_encode(array("statusCode"=>'SUCCESS','statusMessage'=>'Login was successful.'));
				else if($status=="USER_NOT_EXIST")
					return json_encode(array("statusCode"=>'ERROR','statusMessage'=>'Cannot find user <b>'.$username.'</b> in the directory. Please check your username. Also please verify the Search Base(s) and Search filter. Your user should be present in the Search base defined.'));
				else
					return json_encode(array("statusCode"=>'ERROR','statusMessage'=>'Invalid login credentials. Please verify the Search Base(s) and Search filter. Your user should be present in the Search base defined.'));
		} else {
			// Default is removed
			return json_encode(array("statusCode"=>'SUCCESS','statusMessage'=>''));
		}
		
	}
	
	
	function getConnection() {
		
		$server_name = get_option('mo_ldap_local_server_url') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_url')) : ''; 
		$ldaprdn = get_option('mo_ldap_local_server_dn') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_dn')) : '';
		$ldappass = get_option('mo_ldap_local_server_password') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_password')) : '';
		
		if ( version_compare(PHP_VERSION, '5.3.0') >= 0 ) {
			ldap_set_option( null, LDAP_OPT_NETWORK_TIMEOUT, 10);
		}
			
		$ldapconn = ldap_connect($server_name);		
		if ($ldapconn) {
			ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
			return $ldapconn;
		} 
		return null;
	}
	
	function authenticate($userDn, $password) {
		$server_name = get_option('mo_ldap_local_server_url') ? Mo_Ldap_Local_Util::decrypt(get_option('mo_ldap_local_server_url')) : ''; 
			
		if ( version_compare(PHP_VERSION, '5.3.0') >= 0 ) {
			ldap_set_option( null, LDAP_OPT_NETWORK_TIMEOUT, 10);
		}
			
		$ldapconn = ldap_connect($server_name);
	
		if ($ldapconn) {
			// binding to ldap server
			$ldapbind = @ldap_bind($ldapconn, $userDn, $password);
			// verify binding
			if ($ldapbind) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}
	
	function get_encrypted_config($request_type, $is_default) {
		global $current_user;
		get_currentuserinfo();
		
		$server_name = '';
		$dn = '';
		$admin_ldap_password = '';
		$dn_attribute = '';
		$search_base = '';
		$search_filter = '';
		$username = $current_user->user_email;
		
		if(Mo_Ldap_Local_Util::check_empty_or_null($is_default)) {
			$server_name = get_option( 'mo_ldap_local_server_url');
			$dn = get_option( 'mo_ldap_local_server_dn');
			$admin_ldap_password = get_option( 'mo_ldap_local_server_password');
			$dn_attribute = get_option( 'mo_ldap_local_dn_attribute');
			$search_base = get_option( 'mo_ldap_local_search_base');
			$search_filter = get_option( 'mo_ldap_local_search_filter');
			$username = get_option('mo_ldap_local_admin_email');
		}
		$customer_id = get_option('mo_ldap_local_admin_customer_key') ? get_option('mo_ldap_local_admin_customer_key') : null;
		
		$fields = array(
			'customerId' => $customer_id,
			'ldapAuditRequest' => array(
				'endUserEmail' => $username,
				'applicationName' => $_SERVER['SERVER_NAME'],
				'appType' => 'WP LDAP Login Plugin',
				'requestType' => $request_type
			),
			'gatewayConfiguration' => array(
				'ldapServer' =>$server_name,
				'bindAccountDN'=>$dn,
				'bindAccountPassword'=>$admin_ldap_password,
				'searchBase'=>$search_base,
				'dnAttribute'=>$dn_attribute,
				'ldapSearchFilter'=>$search_filter
			)
		);
		
		return $fields;
	}
	
	function get_login_config($encrypted_username, $username, $encrypted_password, $request_type, $is_default) {
		global $current_user;
		get_currentuserinfo();
		
		$customer_id = get_option('mo_ldap_local_admin_customer_key') ? get_option('mo_ldap_local_admin_customer_key') : null;
		
		$fields = array(
			'customerId' => $customer_id,
			'userName' => $encrypted_username,
			'password' => $encrypted_password,
			'ldapAuditRequest' => array(
				'endUserEmail' => $username,
				'applicationName' => $_SERVER['SERVER_NAME'],
				'appType' => 'WP LDAP Login Plugin',
				'requestType' => $request_type
			)
		);
		
		return $fields;
	}
	
	function send_audit_request($username, $request_type, $status_code, $status_message){
		
		if(!Mo_Ldap_Local_Util::is_curl_installed()) {
			return "CURL_ERROR";
		}
		
		if($request_type == "Ping LDAP Server"){
			$username = get_option('mo_ldap_local_admin_email');
		}
		
		$customer_id = get_option('mo_ldap_local_admin_customer_key');
		
		$url = get_option('mo_ldap_local_host_name') . '/moas/api/ldap/audit';
		$ch = curl_init($url);
		
		$fields = array(
			'customerId' => $customer_id,
			'ldapAuditRequest' => array(
				'endUserEmail' => $username,
				'applicationName' => $_SERVER['SERVER_NAME'],
				'appType' => 'WP LDAP/AD Login for Intranet Sites',
				'requestType' => $request_type,
				'statusCode' => $status_code,
				'statusMessage' => $status_message
			),

		);
		
		$field_string = json_encode($fields);
		
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_ENCODING, "" );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );    # required for https urls
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt( $ch, CURLOPT_TIMEOUT, 1);
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'charset: UTF - 8',
			'Authorization: Basic'
		));
		curl_setopt( $ch, CURLOPT_POST, true);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $field_string);
		curl_exec($ch);
		
		return "SUCCESS";
	}
	
}?>