<?php
namespace qqworld_passport\modules;

use qqworld_passport\core;

class baidu extends core {
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
		$content .= '<p><strong>Baidu OpenID:</strong> <em>'. get_the_author_meta( 'QQWorld Passport Baidu Openid', $id ) . '</em></p>';
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
			<th><label for="bind-baidu-account-btn"><?php _e('Baidu', $this->text_domain); ?></label></th>
			<td>
			<?php
				$params=array(
					'response_type' => 'code',
					'client_id' => $this->options->moudule_baidu_apikey,
					'redirect_uri' => $this->redirect_uri,
					'scope'=>'basic',
					'display'=>'page'
				);
				$dialog_url = 'https://openapi.baidu.com/oauth/2.0/authorize?'.http_build_query($params);
				$openid = get_user_meta( $profileuser->ID, 'QQWorld Passport Baidu Openid', true );
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

	/*
	 * 用于多站点模式检测该用户是否是当前
	*/
	public static function is_user_member_of_blog($user_data) {
		if (is_multisite()) {
			// 如果找到有用户曾经登陆过，则检测该用户是否属于当前子站点
			$user_id = $user_data->ID;
			$blog_id = $GLOBALS['blog_id'];
			if (!is_user_member_of_blog($user_id, $blog_id)) {
				//如果不是当前子站点用，则添加
				$role = in_array('administrator', $user_data->roles) ? 'administrator' : 'subscriber';
				add_user_to_blog( $blog_id, $user_id, $role );
			}
		}
	}

	public function is_openid_exists($openid) {
		$args = array(
			'meta_key'     => 'QQWorld Passport Baidu Openid',
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
		if (!empty($users)) {
			$user_data = $users[0]->data;
			self::is_user_member_of_blog($user_data);
			return $user_data;
		} else return false;
	}

	public function parse_request() {
		@session_start();

		if (!isset($_GET['code']) ) wp_safe_redirect( wp_login_url() );

		$code = $_GET['code'];

		$params=array(
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $this->options->moudule_baidu_apikey,
			'client_secret' => $this->options->moudule_baidu_secretkey,
			'redirect_uri' => $this->redirect_uri
		);
		$respond = $this->curl('https://openapi.baidu.com/oauth/2.0/token', 'post', $params);
		$baidu_user = $this->curl("https://openapi.baidu.com/rest/2.0/passport/users/getInfo?access_token=".$respond->access_token);
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

		$openid = $baidu_user->userid;
		if (empty($openid)) return;
		// check is openid exists
		$user = $this->is_openid_exists($openid);
		if ($user) {
			$user_login = $user->user_login;
			if ( !$this->is_user_logged_in() ) {
				$this->login($user_login);
			}
		} else $user_login = false;

		$avatar = 'http://tb.himg.baidu.com/sys/portrait/item/'.$baidu_user->portrait;

		if (get_option( 'users_can_register' )) {
			if (!$this->is_user_logged_in() && !$user_login) {
				if ($this->options->automatic_register) {
					$user_login = current_time('timestamp');
					$random_password = wp_generate_password();
					$user_id = wp_create_user($user_login, $random_password);
					$userdata = array(
						'ID' => $user_id,
						'first_name' => $baidu_user->username,
						'user_nicename' => $baidu_user->username,
						'nickname' => $baidu_user->username,
						'display_name' => $baidu_user->username
					);
					wp_update_user( $userdata );
				}
			} else {
				$user_id = $this->get_current_user_id();
			}
			if (isset($user_id) && $user_id) {
				// 清除所有其他可能包含'QQWorld Passport Baidu Openid'的用户的这个meta
				$this->delete_user_meta($user_id, 'QQWorld Passport Baidu Openid', $openid);
				update_user_meta( $user_id, 'QQWorld Passport Baidu Openid', $openid );
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
			'client_id' => $this->options->moudule_baidu_apikey,
			'redirect_uri' => $this->redirect_uri,
			'scope'=>'basic',
			'display'=>'page'
		);
		$dialog_url = 'https://openapi.baidu.com/oauth/2.0/authorize?'.http_build_query($params);
		if ($this->options->moudule_baidu_hide_interface=='yes') return;
?>
		<a class="baidu loginbtn" href="<?php echo $dialog_url; ?>" title="<?php _e('Baidu Login', $this->text_domain); ?>"><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/icons/<?php echo $this->slug; ?>.png" width="32" height="32" /></a>
<?php
	}

	public function settings_form() {
?>
<div class="wrap" id="qqworld-passport-container">
	<h2><?php _e('Baidu', $this->text_domain); ?> <?php _e('Settings'); ?></h2>
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
							<label for="module-baidu-apikey"><?php _ex('API Key', 'baidu', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<input type="text" id="module-baidu-apikey" placeholder="<?php _ex('API Key', 'baidu', $this->text_domain); ?>" name="qqworld-passport-module-baidu[apikey]" class="regular-text" value="<?php echo $this->options->moudule_baidu_apikey; ?>" />
							<p class="description"><?php printf(__("Please enter API Key, if you don't have, please <a href=\"%s\" target=\"_blank\">click here</a> to get one.", $this->text_domain), 'http://developer.baidu.com/'); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="module-baidu-secretkey"><?php _ex('Secret Key', 'baidu', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<input type="password" id="module-baidu-secretkey" placeholder="<?php _ex('Secret Key', 'baidu', $this->text_domain); ?>" name="qqworld-passport-module-baidu[secretkey]" class="regular-text" value="<?php echo $this->options->moudule_baidu_secretkey; ?>" />
							<p class="description"><?php printf(__("Please enter Secret Key, if you don't have, please <a href=\"%s\" target=\"_blank\">click here</a> to get one.", $this->text_domain), 'http://developer.baidu.com/'); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="module-baidu-return-url"><?php _ex('Return URL', 'baidu', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<?php echo $this->redirect_uri; ?>
							<p class="description"><?php printf(__("Please <a href=\"%s\" target=\"_blank\">click here</a> to create a website APP.", $this->text_domain), 'http://developer.baidu.com/console#app/project'); ?><br />
							<?php printf(__('And set the Return Url <a href="%s" target="_blank">here</a>.', $this->text_domain), 'http://developer.baidu.com/wiki/index.php?title=docs/oauth/redirect'); ?><br /><br />
							<strong><?php _e('Return URL 404 error?', $this->text_domain); ?></strong><br />
							1. <?php _e('Your server must supports rewrite.', $this->text_domain); ?><br />
							2. <?php _e('In Wordpress admin page &gt; <strong>Settings</strong> &gt; <strong>Permalinks</strong>, do not select <strong>Plain</strong>.', $this->text_domain); ?><br />
							3. <?php _e('Do not disabled the REST API.', $this->text_domain); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="module-baidu-hide-interface"><?php _e('Hide Interface', $this->text_domain); ?></label>
						</th>
						<td class="forminp">
							<input type="checkbox" id="module-baidu-hide-interface" name="qqworld-passport-module-baidu[hide-interface]" value="yes" <?php checked('yes', $this->options->moudule_baidu_hide_interface); ?> />
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
		$this->slug = 'baidu';
		$this->name = __('Baidu', $this->text_domain);
		$this->description = __("Baidu is an Internet company mainly engaged in search engine services, in January 1, 2000 by Li Yanhong, Xu Yong, two were founded in Beijing Zhongguancun. The name of the \"Baidu\" word from the Southern Song Dynasty poet Xin Qiji's \"green jade case\" a word: \"the public to find him thousands of Baidu, suddenly look back, that person is in the light of the place\", corporate logo Is a \"bear's paw\", from the \"hunter to bear palm to trace\" image.", $this->text_domain);
		$this->redirect_uri = home_url('wp-json/qqworld-passport/v1/module/'.$this->slug.'/');
		$qqworld_passport_modules[$this->slug] = $this;
	}
}
?>