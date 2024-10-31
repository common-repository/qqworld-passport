<?php
namespace qqworld_passport\modules;

use qqworld_passport\core;

class facebook extends core {
	var $enabled;
	var $slug;
	var $redirect_uri;

	public function init() {
		$this->register_method();
		if ($this->is_activated($this->slug)) {
			add_action( 'admin_menu', array($this, 'admin_menu') );

			add_action( 'qqworld_passport_login_form_buttons', array($this, 'login_form_button') ); // 登录页的表单
			add_action( 'qqworld_passport_social_media_account_profile_form', array($this, 'profile_form') ); // 个人资料页面的表单
			add_action( 'qqworld_passport_parse_request_'.$this->slug, array($this, 'parse_request') ); // 处理登录页回调的信息

			add_filter( 'qqworld-passport-openids', array($this, 'openids'), 10, 2 );
		}
	}

	public function openids($content, $id) {
		$content .= '<p><strong>Facebook OpenID:</strong> <em>'. get_the_author_meta( 'QQWorld Passport Facebook Openid', $id ) . '</em></p>';
		return $content;
	}

	public function sanitize_callback($value) {
		return $value;
	}

	public function admin_menu() {
		$page_title = $this->name;
		$menu_title = $this->name;
		$capability = 'administrator';
		$menu_slug = $this->text_domain . '_settings_' . $this->slug;
		$function = array($this, 'settings_form');
		$icon_url = 'none';
		$settings_page = add_submenu_page($this->text_domain, $page_title, $menu_title, $capability, $menu_slug, $function);
	}

	public function profile_form($profileuser) {
?>
		<tr>
			<th><label for="bind-facebook-account-btn"><?php _e('Facebook', $this->text_domain); ?></label></th>
			<td>
			<?php
				$params=array(
					'response_type' => 'code',
					'client_id' => $this->options->moudule_facebook_apikey,
					'redirect_uri' => $this->redirect_uri,
					'scope'=>'basic',
					'display'=>'page'
				);
				$dialog_url = 'https://openapi.facebook.com/oauth/2.0/authorize?'.http_build_query($params);
				$openid = get_user_meta( $profileuser->ID, 'QQWorld Passport Facebook Openid', true );
				if (empty($openid)) {
?>
					<a class="button button-primary" href="<?php echo $dialog_url; ?>"><?php _e('Bind Now', $this->text_domain); ?></a>
<?php
				} else {
?>
					<a class="button" href="<?php echo $dialog_url; ?>"><?php _e('Rebind', $this->text_domain); ?></a>
<?php
				}
				do_action('qqworld_passport_profile_form_'.$this->slug);
?>
			</td>
		</tr>
<?php
	}

	public function is_openid_exists($openid) {
		$args = array(
			'meta_key'     => 'QQWorld Passport Facebook Openid',
			'meta_value'   => $openid,
			'meta_compare' => '='
		);
		if (is_multisite()) {
			$sites = get_sites();
			$blog_ids = array();
			foreach ($sites as $site) {
				$blog_ids[] = $site->blog_id;
			}
			$args['blog_id'] = $blog_ids;
		}
		$users = get_users($args);
		if (!empty($users)) return $users[0]->data;
		else return false;
	}

	public function parse_request() {
		@session_start();

		if (!isset($_GET['code']) ) wp_safe_redirect( wp_login_url() );

		$code = $_GET['code'];

		$params=array(
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $this->options->moudule_facebook_apikey,
			'client_secret' => $this->options->moudule_facebook_secretkey,
			'redirect_uri' => $this->redirect_uri
		);
		$respond = $this->curl('https://openapi.facebook.com/oauth/2.0/token', 'post', $params);
		$facebook_user = $this->curl("https://openapi.facebook.com/rest/2.0/passport/users/getInfo?access_token=".$respond->access_token);
		/*
		stdClass Object
		(
			[blood] => 0
			[constellation] => 0
			[education] => 0
			[figure] => 0
			[job] => 0
			[marriage] => 0
			[sex] => 1
			[trade] => 0
			[userid] => 3624361358
			[username] => kbzwxq
			[portrait] => 13d86b627a7778715103
			[birthday] => 0000-00-00
			[is_bind_mobile] => 1
			[is_realname] => 1
		)
		*/

		$openid = $facebook_user->userid;
		if (empty($openid)) return;
		// check is openid exists
		$user = $this->is_openid_exists($openid);
		if ($user) {
			$user_login = $user->user_login;
			if ( !$this->is_user_logged_in() ) {
				$this->login($user_login);
			}
		} else $user_login = false;

		$avatar = 'http://tb.himg.facebook.com/sys/portrait/item/'.$facebook_user->portrait;

		if (get_option( 'users_can_register' )) {
			if (!$this->is_user_logged_in() && !$user_login) {
				if ($this->options->automatic_register) {
					$user_login = current_time('timestamp');
					$random_password = wp_generate_password();
					$user_id = wp_create_user($user_login, $random_password);
					$userdata = array(
						'ID' => $user_id,
						'first_name' => $facebook_user->username,
						'user_nicename' => $facebook_user->username,
						'nickname' => $facebook_user->username,
						'display_name' => $facebook_user->username
					);
					wp_update_user( $userdata );
				}
			} else {
				$user_id = $this->get_current_user_id();
			}
			if (isset($user_id) && $user_id) {
				// 清除所有其他可能包含'QQWorld Passport Facebook Openid'的用户的这个meta
				$this->delete_user_meta($user_id, 'QQWorld Passport Facebook Openid', $openid);
				update_user_meta( $user_id, 'QQWorld Passport Facebook Openid', $openid );
				update_user_meta( $user_id, 'QQWorld Passport Avatar', set_url_scheme($avatar) );
			}
		}
		if ($user_login) $this->login($user_login, false);
		if (isset($_SESSION['redirect_uri'])) {
			$redirect_uri = $_SESSION['redirect_uri'];
		} else {
			$redirect_uri = home_url();
		}
		wp_redirect( $redirect_uri );
	}

	public function login_form_button() {
		$params=array(
			'response_type' => 'code',
			'client_id' => $this->options->moudule_facebook_apikey,
			'redirect_uri' => $this->redirect_uri,
			'scope'=>'basic',
			'display'=>'page'
		);
		$dialog_url = 'https://openapi.facebook.com/oauth/2.0/authorize?'.http_build_query($params);
		if ($this->options->moudule_facebook_hide_interface=='yes') return;
?>
		<a class="facebook loginbtn" href="<?php echo $dialog_url; ?>" title="<?php _e('Facebook Login', $this->text_domain); ?>"><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/icons/<?php echo $this->slug; ?>.png" width="32" height="32" /></a>
<?php
	}

	public function settings_form() {
?>
<div class="wrap" id="qqworld-passport-container">
	<h2><?php _e('Facebook', $this->text_domain); ?> <?php _e('Settings'); ?></h2>
	<form action="options.php" method="post" id="update-form">
		<?php settings_fields($this->text_domain.'-module-'.$this->slug); ?>
		<div class="icon32 icon32-qqworld-synchronizer-settings" id="icon-qqworld-synchronizer"><br></div>
		<?php
		$tabs = array(
			'regular' => __('Regular', $this->text_domain)
		);
		$tabs = apply_filters( 'qqworld_passport_'.$this->slug.'_form_tabs', $tabs);
		if (count($tabs)>1): ?>
		<h2 class="nav-tab-wrapper">
		<?php
		foreach ($tabs as $name => $label) : ?>
			<a id="<?php echo $name; ?>" href="#<?php echo $name; ?>" class="nav-tab"><?php echo $label; ?></a>
		<?php endforeach; ?>
		</h2>
		<?php endif; ?>
		<?php if (count($tabs)>1): ?><div class="nav-section"><?php endif; ?>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label for="module-facebook-apikey"><?php _ex('API Key', 'facebook', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<input type="text" id="module-facebook-apikey" placeholder="<?php _ex('API Key', 'facebook', $this->text_domain); ?>" name="qqworld-passport-module-facebook[apikey]" class="regular-text" value="<?php echo $this->options->moudule_facebook_apikey; ?>" />
							<p class="description"><?php printf(__("Please enter API Key, if you don't have, please <a href=\"%s\" target=\"_blank\">click here</a> to get one.", $this->text_domain), 'http://developer.facebook.com/'); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="module-facebook-secretkey"><?php _ex('Secret Key', 'facebook', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<input type="password" id="module-facebook-secretkey" placeholder="<?php _ex('Secret Key', 'facebook', $this->text_domain); ?>" name="qqworld-passport-module-facebook[secretkey]" class="regular-text" value="<?php echo $this->options->moudule_facebook_secretkey; ?>" />
							<p class="description"><?php printf(__("Please enter Secret Key, if you don't have, please <a href=\"%s\" target=\"_blank\">click here</a> to get one.", $this->text_domain), 'http://developer.facebook.com/'); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="module-facebook-return-url"><?php _ex('Return URL', 'facebook', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<?php echo $this->redirect_uri; ?>
							<p class="description"><?php printf(__("Please <a href=\"%s\" target=\"_blank\">click here</a> to create a website APP.", $this->text_domain), 'http://developer.facebook.com/console#app/project'); ?><br />
							<?php printf(__('And set the Return Url <a href="%s" target="_blank">here</a>.', $this->text_domain), 'http://developer.facebook.com/wiki/index.php?title=docs/oauth/redirect'); ?><br /><br />
							<strong><?php _e('Return URL 404 error?', $this->text_domain); ?></strong><br />
							1. <?php _e('Your server must supports rewrite.', $this->text_domain); ?><br />
							2. <?php _e('In Wordpress admin page &gt; <strong>Settings</strong> &gt; <strong>Permalinks</strong>, do not select <strong>Plain</strong>.', $this->text_domain); ?><br />
							3. <?php _e('Do not disabled the REST API.', $this->text_domain); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="module-facebook-hide-interface"><?php _e('Hide Interface', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<input type="checkbox" id="module-facebook-hide-interface" name="qqworld-passport-module-facebook[hide-interface]" value="yes" <?php checked('yes', $this->options->moudule_facebook_hide_interface); ?> />
							<p class="description"><?php _e("Don't display login icon in login form.", $this->text_domain); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		<?php if (count($tabs)>1): ?></div><?php endif; ?>
		<?php do_action( 'qqworld_passport_'.$this->slug.'_form_sections' ); ?>
		<?php submit_button(); ?>
	</form>
<?php
	}

	public function register_method() {
		global $qqworld_passport_modules;
		$this->slug = 'facebook';
		$this->name = __('Facebook', $this->text_domain);
		$this->description = __('Facebook is a US social networking service website. Founded on February 4, 2004, the company is headquartered in Paraty, Calif., and is the founder of Mark Zuckerberg.', $this->text_domain);
		$this->redirect_uri = home_url('wp-json/qqworld-passport/v1/module/'.$this->slug.'/');
		$qqworld_passport_modules[$this->slug] = $this;
	}
}
?>