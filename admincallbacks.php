<?php


class zigpluginsafe_admincallbacks
{


	public function __construct($zigaction) {
		if ($zigaction == 'zigpluginsafe-admin-options-update') { $this->update_options(); }
	}


	public function update_options() {
		global $zigpluginsafe;
		if (!current_user_can('manage_options')) { wp_die('You are not allowed to do this.'); }
		if (!$zigpluginsafe->user_is_master_user) { wp_die('You are not allowed to do this.'); }
		check_admin_referer('zigpress_nonce');
		$zigpluginsafe->result = 'ERR|Invalid form post.';
		$safeplugins = @$_POST['safeplugins'];
		if (is_array($safeplugins)) {
			if (count($safeplugins) > 0) {
				$zigpluginsafe->options['safeplugins'] = $safeplugins;
				update_option('zigpluginsafe', $zigpluginsafe->options);
				$zigpluginsafe->result = 'OK|Options saved.';
			}
		}
		$zigpluginsafe->result = 'OK|Options saved.';
		if (ob_get_status()) ob_clean();
		wp_safe_redirect($_SERVER['PHP_SELF'] . '?page=zigpluginsafe-options&r=' . base64_encode($zigpluginsafe->result));
		exit();
	}


}


# EOF
