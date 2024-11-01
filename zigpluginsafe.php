<?php
/*
Plugin Name: ZigPluginSafe
Plugin URI: https://wordpress.org/plugins/zigpluginsafe/
Description: Allows the admin user who installs it to prevent other users (even admins) from editing or deactivating selected plugins. PLEASE NOTE THIS IS THE LAST VERSION. WE ARE RETIRING THIS PLUGIN.
Version: 0.2.2
Author: ZigPress
Requires at least: 4.2
Tested up to: 5.3
Author URI: https://www.zigpress.com/
License: GPLv2
*/


/*
Copyright (c) 2015-2019 ZigPress

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation Inc, 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/


require_once dirname(__FILE__) . '/admincallbacks.php';


if (!class_exists('zigpluginsafe')) {


	final class zigpluginsafe {


		# http://www.wpbeginner.com/wp-tutorials/how-to-disable-plugin-deactivation-from-wordpress-admin-panel/


		# could remove ability to add or remove plugins by using user_has_cap filter
		# http://danisadesigner.com/blog/note-self-user_has_cap-doesnt-think/
		# http://drincruz.blogspot.com.mt/2010/09/wordpress-using-userhascap-filter.html
		# http://stackoverflow.com/questions/12938514/restrict-users-from-editing-post-based-on-the-age-of-the-post
		# relevant caps are install_plugins, delete_plugins, edit_plugins


		public $protocol;
		public $server;
		public $callback_url;
		public $plugin_folder;
		public $plugin_directory;
		public $plugin_path;
		public $options;
		public $user_is_master_user;
		public $params;
		public $result_type;
		public $result_message;


		private static $_instance = null;


		public static function getinstance() {
			if (is_null(self::$_instance)) {
				self::$_instance = new self;
			}
			return self::$_instance;
		}


		private function __clone() {}


		private function __wakeup() {}


		private function __construct() {
			$this->protocol = (strpos($_SERVER['SERVER_PROTOCOL'], 'HTTPS') !== false) ? 'https://' : 'http://';
			$this->server = $_SERVER['SERVER_NAME'];
			$this->callback_url = $this->protocol . $this->server . preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI']);
			$this->plugin_folder = get_bloginfo('url') . '/' . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)); # no final slash
			$this->plugin_directory = WP_PLUGIN_DIR . '/zigpluginsafe/';
			$this->plugin_path = str_replace('plugin.php', 'zigpluginsafe.php', __FILE__);
			$this->options = get_option('zigpluginsafe');
			$this->user_is_master_user = false;
			$this->get_params();
			add_action('plugins_loaded', array($this, 'action_plugins_loaded'));
			add_action('init', array($this, 'action_init'));
			add_action('admin_init', array($this, 'action_admin_init'));
			add_action('admin_enqueue_scripts', array($this, 'action_admin_enqueue_scripts'));
			add_action('admin_menu', array($this, 'action_admin_menu'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'filter_plugin_action_links_zigpluginsafe'));
			add_filter('plugin_action_links', array($this, 'filter_plugin_action_links'), 10, 4);
			add_filter('plugin_row_meta', array($this, 'filter_plugin_row_meta'), 10, 2 );
			/* That which can be added without discussion, can be removed without discussion. */
			remove_filter( 'the_title', 'capital_P_dangit', 11 );
			remove_filter( 'the_content', 'capital_P_dangit', 11 );
			remove_filter( 'comment_text', 'capital_P_dangit', 31 );
		}


		public function activate() {
			if (!$this->options = get_option('zigpluginsafe')) {
				$this->options = array();
				$this->options['masteruser'] = '0';
				$this->options['safeplugins'] = array('zigpluginsafe/zigpluginsafe.php');
				add_option('zigpluginsafe', $this->options);
			}
		}


		public function autodeactivate($requirement) {
			if (!function_exists( 'get_plugins')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$plugin = plugin_basename($this->plugin_path);
			$plugindata = get_plugin_data($this->plugin_path, false);
			if (is_plugin_active($plugin)) {
				delete_option('zigpluginsafe');
				deactivate_plugins($plugin);
				wp_die($plugindata['Name'] . ' requires ' . $requirement . ' and has been deactivated. <a href="' . admin_url('plugins.php') . '">Click here to go back.</a>');
			}
		}


		# ACTIONS


		public function action_plugins_loaded() {
			global $wp_version;
			if (version_compare(phpversion(), '5.3.0', '<')) $this->autodeactivate('PHP 5.3.0');
			if (version_compare($wp_version, '4.2', '<')) $this->autodeactivate('WordPress 4.2');
		}


		public function action_init() {
			if ($this->options['masteruser'] == '0') {
				$this->options['masteruser'] = get_current_user_id();
				update_option('zigpluginsafe', $this->options);
			}
			$this->user_is_master_user = (get_current_user_id() == $this->options['masteruser']) ? true : false;
		}


		public function action_admin_init() {
			#die(print_r($this->params));
			new zigpluginsafe_admincallbacks(@$this->params['zigaction']);
		}


		public function action_admin_enqueue_scripts() {
			wp_enqueue_style('zigpluginsafe-admin', $this->plugin_folder . '/css/admin.css', false, date('Ymd'));
		}


		public function action_admin_menu() {
			if ($this->user_is_master_user) {
				add_options_page('ZigPluginSafe Options', 'ZigPluginSafe', 'manage_options', 'zigpluginsafe-options', array($this, 'admin_page_options'));
			}
		}


		# FILTERS


		public function filter_plugin_action_links_zigpluginsafe($actions) {
			if ($this->user_is_master_user) {
				$newactions = array(
					'<a href="' . get_admin_url() . 'options-general.php?page=zigpluginsafe-options">Settings</a>',
				);
				return array_merge( $actions, $newactions );
			}
			return $actions;
		}


		public function filter_plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
			// Remove edit link for all
			if (array_key_exists('edit', $actions))  {
				unset($actions['edit']);
			}
			// Remove deactivate link for crucial plugins
			if (array_key_exists('deactivate', $actions) && in_array($plugin_file, $this->options['safeplugins']) !== false) {
				unset($actions['deactivate']);
			}
			// Remove delete link for crucial plugins
			if (array_key_exists('delete', $actions) && in_array($plugin_file, $this->options['safeplugins']) !== false) {
				unset($actions['delete']);
			}
			return $actions;
		}


		public function filter_plugin_row_meta($links, $file) {
			$plugin = plugin_basename(__FILE__);
			$newlinks = array('<a target="_blank" href="http://www.zigpress.com/donations/">Donate</a>');
			if ($this->user_is_master_user) {
				$newlinks[] = '<a href="' . get_admin_url() . 'options-general.php?page=zigpluginsafe-options">Settings</a>';
			}
			if ($file == $plugin) return array_merge($links, $newlinks);
			return $links;
		}


		# ADMIN CONTENT


		public function admin_page_options() {
			if (!current_user_can('manage_options')) { wp_die('You are not allowed to do this.'); }
			$user = get_userdata($this->options['masteruser']);
			if ($user->ID != get_current_user_id()) { wp_die('You are not the master user.'); }
			if ($this->result_type != '') echo $this->show_result($this->result_type, $this->result_message);
			# https://codex.wordpress.org/Function_Reference/get_plugins
			$plugins = get_plugins();
			?>
			<div class="wrap zigpluginsafe-admin">
				<h2>ZigPluginSafe</h2>
				<div class="wrap-left">
					<div class="col-pad">
						<p>This plugin allows the administrator who installed and first activated it to prevent other users (even administrators) from editing, deactivating or deleting plugins.</p>
						<p>All plugins ticked below have their deactivate and delete links removed on the Plugins page. In addition, EVERY plugin has its edit link removed, if it has one.</p>
						<p>The master user named below is the only user who has access to this settings page.</p>
						<form action="<?php echo $_SERVER['PHP_SELF']?>?page=zigpluginsafe-options" method="post">
							<input type="hidden" name="zigaction" value="zigpluginsafe-admin-options-update" />
							<?php wp_nonce_field('zigpress_nonce'); ?>
							<table class="form-table">
								<tr>
									<th>Master user:</th>
									<td><?php echo $user->user_login ?></td>
								</tr>
								<tr>
									<th>Protected plugins:</th>
									<td>
										<?php
										foreach ($plugins as $plugin => $plugininfo) {
											?>
											<span class="half">
												<input class="checkbox" type="checkbox" name="safeplugins[]" value="<?php echo $plugin ?>" <?php if (in_array($plugin, $this->options['safeplugins']) !== false) { echo('checked="checked"'); } ?> />
												<?php echo $plugininfo['Name'] ?>
											</span>
											<?php
										}
										?>
									</td>
								</tr>
							</table>
							<p class="submit"><input type="submit" name="Submit" class="button-primary" value="Save Changes" /></p>
						</form>
						<p>Please note that if another administrator has access to the database, they could manually change the above settings. However, the purpose of this plugin is to discourage a developer's non-technical clients from disabling plugins themselves even if they insist on having an admin account, and it should be proof enough against that.</p>
					</div><!--col-pad-->
				</div><!--wrap-left-->
				<div class="wrap-right">
					<table class="widefat donate" cellspacing="0">
						<thead>
						<tr><th>Support this plugin!</th></tr>
						</thead>
						<tr><td>
								<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
									<input type="hidden" name="cmd" value="_s-xclick">
									<input type="hidden" name="hosted_button_id" value="GT252NPAFY8NN">
									<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
									<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
								</form>
								<p>If you find ZigPluginSafe useful, please keep it free and actively developed by making a donation.</p>
								<p>Suggested donation: &euro;10 or an amount of your choice. Thanks!</p>
							</td></tr>
					</table>
					<table class="widefat donate" cellspacing="0">
						<thead>
						<tr><th><img class="icon floatRight" src="<?php echo $this->plugin_folder?>/img/zp.png" />Brought to you by ZigPress</th></tr>
						</thead>
						<tr><td>
								<p><a href="http://www.zigpress.com/">ZigPress</a> is engaged in WordPress consultancy, solutions and research. We have also released a number of free and commercial plugins to support the WordPress community.</p>
								<p><a target="_blank" href="https://www.zigpress.com/plugins/zigpluginsafe/"><img class="icon" src="<?php echo $this->plugin_folder?>/img/zigpluginsafe.png" alt="ZigPluginSafe WordPress plugin by ZigPress" title="ZigPluginSafe WordPress plugin by ZigPress" />&nbsp; ZigPluginSafe page</a></p>
								<p><a target="_blank" href="https://www.zigpress.com/plugins/"><img class="icon" src="<?php echo $this->plugin_folder?>/img/plugin.png" alt="WordPress plugins by ZigPress" title="WordPress plugins by ZigPress" />&nbsp; Other ZigPress plugins</a></p>
								<p><a target="_blank" href="https://www.facebook.com/zigpress"><img class="icon" src="<?php echo $this->plugin_folder?>/img/facebook.png" alt="ZigPress on Facebook" title="ZigPress on Facebook" />&nbsp; ZigPress on Facebook</a></p>
								<p><a target="_blank" href="https://twitter.com/ZigPress"><img class="icon" src="<?php echo $this->plugin_folder?>/img/twitter.png" alt="ZigPress on Twitter" title="ZigPress on Twitter" />&nbsp; ZigPress on Twitter</a></p>
							</td></tr>
					</table>
				</div><!--wrap-right-->
				<div class="clearer">&nbsp;</div>
			</div><!--/wrap-->
		<?php
		}


		# UTILITIES


		public function is_classicpress() {
			return function_exists('classicpress_version');
		}


		public function get_params() {
			$this->params = array();
			foreach ($_REQUEST as $key=>$value) {
				$this->params[$key] = $value;
				if (!is_array($this->params[$key])) { $this->params[$key] = strip_tags(stripslashes(trim($this->params[$key]))); }
				# need to sanitise arrays as well really
			}
			if (!is_numeric(@$this->params['zigpage'])) { $this->params['zigpage'] = 1; }
			if ((@$this->params['zigaction'] == '') && (@$this->params['zigaction2'] != '')) { $this->params['zigaction'] = $this->params['zigaction2']; }
			$this->result = '';
			$this->result_type = '';
			$this->result_message = '';
			if ($this->result = base64_decode(@$this->params['r'])) list($this->result_type, $this->result_message) = explode('|', $this->result); # base64 for ease of encoding
		}


		public function show_result($strType, $strMessage) {
			$strOutput = '';
			if ($strMessage != '') {
				$strClass = '';
				switch (strtoupper($strType)) {
					case 'OK' :
						$strClass = 'updated';
						break;
					case 'INFO' :
						$strClass = 'updated highlight';
						break;
					case 'ERR' :
						$strClass = 'error';
						break;
					case 'WARN' :
						$strClass = 'error';
						break;
				}
				if ($strClass != '') {
					$strOutput .= '<div class="msg ' . $strClass . '" title="Click to hide"><p>' . $strMessage . '</p></div>';
				}
			}
			return $strOutput;
		}


	}


} else {
	wp_die('Namespace clash! Class zigpluginsafe already exists.');
}


$zigpluginsafe = zigpluginsafe::getinstance();
register_activation_hook(__FILE__, array(&$zigpluginsafe, 'activate'));


# EOF
