=== LDAP/AD Login for Intranet sites ===
Contributors: miniOrange
Donate link: http://miniorange.com
Tags:ldap, AD, ldap login, ldap sso, AD sso, ldap authentication, AD authentication, active directory authentication, ldap single sign on, ad single sign on, ad login,active directory single sign on, active directory, openldap login, login form, user login, authentication, login, WordPress login
Requires at least: 2.0.2
Tested up to: 4.2.3
Stable tag: 2.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Login to your intranet wordpress site using credentials stored in AD, OpenLDAP and other LDAP. No need to have public IP address for LDAP server.

== Description ==

LDAP/AD Login Plugin provides login to WordPress using credentials stored in your LDAP Server. It allows users to authenticate against various LDAP implementations like Microsoft Active Directory, OpenLDAP and other directory systems.

= Features :- =

*	Login to WordPress using your LDAP credentials
*	Automatic user registration after login if the user is not already registered with your site
*	Uses LDAP or LDAPS for secure connection to your LDAP Server
*	Can authenticate users against multiple search bases
*	Can authenticate users against multiple user attributes like uid, cn, mail, sAMAccountName.
*	Test connection to your LDAP server
*	Test authentication using credentials stored in your LDAP server
*	Ability to test against demo LDAP server and demo credentials
*	You will need to install PHP LDAP extension in WordPress
*	No need for a public IP address or FQDN for your LDAP. 
*	Will get support if you contact miniOrange at info@miniorange.com

= Do you want support? =
Please email us at info@miniorange.com or <a href="http://miniorange.com/contact" target="_blank">Contact us</a>

== Installation ==

= From your WordPress dashboard =
1. Visit `Plugins > Add New`
2. Search for `LDAP/AD Login for Intranet sites`. Find and Install `LDAP/AD Login for Intranet sites`
3. Activate the plugin from your Plugins page

= From WordPress.org =
1. Download LDAP/AD Login for Intranet sites.
2. Unzip and upload the `ldap-login-for-intranet-sites` directory to your `/wp-content/plugins/` directory.
3. Activate LDAP/AD Login for Intranet sites from your Plugins page.

= Once Activated =
1. Upload `ldap-login-for-intranet-sites.zip` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Go to `Settings-> LDAP Login Config`, and follow the instructions.
4. Click on `Save`

Make sure that if there is a firewall, you `OPEN THE FIREWALL` to allow incoming requests to your LDAP from your WordPress Server IP and open port 389(636 for SSL or ldaps).

== Frequently Asked Questions ==

= How should I enter my LDAP configuration? I only see Register with miniOrange. =
Our very simple and easy registration lets you register with miniOrange. Once you have registered with a valid email-address and phone number, you will be able to add your LDAP configuration.

= I am not able to get the configuration right. =
Make sure that if there is a firewall, you `OPEN THE FIREWALL` to allow incoming requests to your LDAP from your WordPress Server IP and open port 389(636 for SSL or ldaps). For further help please click on the Troubleshooting tab where you can find detailed description for each configuration. If that does not help, please check the format of example settings in `Example LDAP Configuration` tab.

= I am locked out of my account and can't login with either my WordPress credentials or LDAP credentials. What should I do? =
Firstly, please check if the `user you are trying to login with` exists in your WordPress. To unlock yourself, rename ldap-login-for-intranet-sites plugin name. You will be able to login with your WordPress credentials. After logging in, rename the plugin back to ldap-login-for-intranet-sites. If the problem persists, `activate, deactivate and again activate` the plugin.

= For support or troubleshooting help =
Please email us at info@miniorange.com or <a href="http://miniorange.com/contact" target="_blank">Contact us</a>.

We can add the provision of user management such as creating users not present in WordPress from LDAP Server, adding users, editing users and so on. For further details, please email us at info@miniorange.com or <a href="http://miniorange.com/contact" target="_blank">Contact us</a>.

== Screenshots ==

1. Configure LDAP plugin
2. Example LDAP Configuration

== Changelog ==

= 1.0.0 =
* this is the first release.

== Upgrade Notice ==

= 1.0 =
First version of plugin.
