<?php
/*
Plugin Name: azurecurve Multisite Favicon
Plugin URI: http://wordpress.azurecurve.co.uk/plugins/multisite-favicon/
Description: Allows Setting of Separate Favicon For Each Site In A Multisite Installation
Author: azurecurve
Version: 1.0.0
Author URI: http://wordpress.azurecurve.co.uk/

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

The full copy of the GNU General Public License is available here: http://www.gnu.org/licenses/gpl.txt
 */

add_action( 'wp_head', 'azurecurve_msfi_load_favicon' );

function azurecurve_msfi_load_favicon() {
	$options = get_option( 'azc_msfi_options' );
	$network_options = get_site_option( 'azc_msfi_options' );
	
	$icon_url = '';
	if (strlen($options['default_path']) > 0 and strlen($options['default_favicon']) > 0){
		$icon_url = stripslashes($options['default_path']).stripslashes($options['default_favicon']);
	}elseif (strlen($options['default_path']) > 0 and strlen($options['default_favicon']) == 0 and strlen($network_options['default_favicon']) > 0){
		$icon_url = stripslashes($options['default_path']).stripslashes($network_options['default_favicon']);
	}elseif (strlen($options['default_path']) == 0 and strlen($options['default_favicon']) > 0 and strlen($network_options['default_path']) > 0){
		$icon_url = stripslashes($network_options['default_path']).stripslashes($options['default_favicon']);
	}elseif (strlen($options['default_path']) == 0 and strlen($options['default_favicon']) == 0 and strlen($network_options['default_path']) > 0 and strlen($network_options['default_favicon']) > 0){
		$icon_url = stripslashes($network_options['default_path']).stripslashes($network_options['default_favicon']);
	}

	if (strlen($icon_url) > 0){
		echo '<link rel="shortcut icon" href="'.$icon_url.'" />';
	}
	
}
 
register_activation_hook( __FILE__, 'azc_msfi_set_default_options' );

function azc_msfi_set_default_options($networkwide) {
	
	$new_options = array(
				'default_path' => plugin_dir_url(__FILE__).'images/',
				'default_favicon' => ''
			);
	
	// set defaults for multi-site
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			global $wpdb;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			$original_blog_id = get_current_blog_id();

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );

				if ( get_option( 'azc_msfi_options' ) === false ) {
					add_option( 'azc_msfi_options', $new_options );
				}
			}

			switch_to_blog( $original_blog_id );
		}else{
			if ( get_option( 'azc_msfi_options' ) === false ) {
				add_option( 'azc_msfi_options', $new_options );
			}
		}
		if ( get_site_option( 'azc_msfi_options' ) === false ) {
			add_site_option( 'azc_msfi_options', $new_options );
		}
	}
	//set defaults for single site
	else{
		if ( get_option( 'azc_msfi_options' ) === false ) {
			add_option( 'azc_msfi_options', $new_options );
		}
	}
}

add_filter('plugin_action_links', 'azc_msfi_plugin_action_links', 10, 2);

function azc_msfi_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=azurecurve-favicon">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}


add_action( 'admin_menu', 'azc_msfi_settings_menu' );

function azc_msfi_settings_menu() {
	add_options_page( 'azurecurve Favicon Settings',
	'azurecurve Favicon', 'manage_options',
	'azurecurve-favicon', 'azc_msfi_config_page' );
}

function azc_msfi_config_page() {
	if (!current_user_can('manage_options')) {
        wp_die('You do not have sumsficient permissions to access this page.');
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_msfi_options' );
	?>
	<div id="azc-msfi-general" class="wrap">
		<fieldset>
			<h2>azurecurve Favicon Configuration</h2>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_msfi_options" />
				<input name="page_options" type="hidden" value="default_path, default_favicon" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_msfi' ); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<p>Set the path for where you will be storing the favicon; default is to the plugin/images folder.</p>
				</td></tr>
				<tr><th scope="row"><label for="width">Path</label></th><td>
					<input type="text" name="default_path" value="<?php echo esc_html( stripslashes($options['default_path']) ); ?>" class="large-text" />
					<p class="description">Set folder for favicon</p>
				</td></tr>
				<tr><th scope="row"><label for="width">Favicon</label></th><td>
					<input type="text" name="default_favicon" value="<?php echo esc_html( stripslashes($options['default_favicon']) ); ?>" class="regular-text" />
					<p class="description">Set favicon name</p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }

add_action( 'admin_init', 'azc_msfi_admin_init' );

function azc_msfi_admin_init() {
	add_action( 'admin_post_save_azc_msfi_options', 'process_azc_msfi_options' );
}

function process_azc_msfi_options() {
	// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){
		wp_die( 'Not allowed' );
	}
	// Check that nonce field created in configuration form is present
	check_admin_referer( 'azc_msfi' );
	settings_fields('azc_msfi');
	
	// Retrieve original plugin options array
	$options = get_option( 'azc_msfi_options' );
	
	$option_name = 'default_path';
	if ( isset( $_POST[$option_name] ) ) {
		$options[$option_name] = ($_POST[$option_name]);
	}
	
	$option_name = 'default_favicon';
	if ( isset( $_POST[$option_name] ) ) {
		$options[$option_name] = ($_POST[$option_name]);
	}
	
	// Store updated options array to database
	update_option( 'azc_msfi_options', $options );
	
	// Redirect the page to the configuration form that was processed
	wp_redirect( add_query_arg( 'page', 'azurecurve-favicon', admin_url( 'options-general.php' ) ) );
	exit;
}

add_action('network_admin_menu', 'add_azc_msfi_network_settings_page');

function add_azc_msfi_network_settings_page() {
	if (function_exists('is_multisite') && is_multisite()) {
		add_submenu_page(
			'settings.php',
			'azurecurve Multisite Favicon Settings',
			'azurecurve Multisite Favicon',
			'manage_network_options',
			'azurecurve-multisite-favicon',
			'azc_msfi_network_settings_page'
			);
	}
}

function azc_msfi_network_settings_page(){
	$options = get_site_option('azc_msfi_options');

	?>
	<div id="azc-msfi-general" class="wrap">
		<fieldset>
			<h2>azurecurve Multisite Favicon Configuration</h2>
			<form action="edit.php?action=update_azc_msfi_network_options" method="post">
				<input type="hidden" name="action" value="save_azc_msfi_network_options" />
				<input name="page_options" type="hidden" value="default_path, default_favicon" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_msfi' ); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<p>Set the default path for where you will be storing the favicons; default is to the plugin/images folder.</p>
				</td></tr>
				<tr><th scope="row"><label for="width">Default Path</label></th><td>
					<input type="text" name="default_path" value="<?php echo esc_html( stripslashes($options['default_path']) ); ?>" class="large-text" />
					<p class="description">Set default folder for favicons</p>
				</td></tr>
				<tr><th scope="row"><label for="width">Default Favicon</label></th><td>
					<input type="text" name="default_favicon" value="<?php echo esc_html( stripslashes($options['default_favicon']) ); ?>" class="regular-text" />
					<p class="description">Set default favicon used when no img attribute set</p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary" />
			</form>
		</fieldset>
	</div>
	<?php
}

add_action('network_admin_edit_update_azc_msfi_network_options', 'process_azc_msfi_network_options');

function process_azc_msfi_network_options(){     
	if(!current_user_can('manage_network_options')) wp_die('FU');
	check_admin_referer('azc_msfi');
	
	// Retrieve original plugin options array
	$options = get_site_option( 'azc_msfi_options' );

	$option_name = 'default_path';
	if ( isset( $_POST[$option_name] ) ) {
		$options[$option_name] = ($_POST[$option_name]);
	}

	$option_name = 'default_favicon';
	if ( isset( $_POST[$option_name] ) ) {
		$options[$option_name] = ($_POST[$option_name]);
	}
	
	update_site_option( 'azc_msfi_options', $options );

	wp_redirect(network_admin_url('settings.php?page=azurecurve-multisite-favicon'));
	exit;  
}

?>