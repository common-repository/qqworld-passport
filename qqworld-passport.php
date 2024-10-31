<?php
/**
 * Plugin Name: QQWorld Passport
 * Plugin URI: https://wordpress.org/plugins/qqworld-passport/
 * Description: QQWorld Passport for Wordpress, Many Oauth 2.0 log in methods.
 * Version: 1.2.1
 * Author: Michael Wang
 * Author URI: https://www.qqworld.org/
 * Text Domain: qqworld-passport
 */
namespace qqworld_passport;

use qqworld_passport\lib\options;
use qqworld_passport\modules\qq;
use qqworld_passport\modules\wechat;
use qqworld_passport\modules\weibo;
use qqworld_passport\modules\baidu;
use qqworld_passport\modules\facebook;
use qqworld_passport\modules\google;
use qqworld_passport\modules\line;
use qqworld_passport\modules\twitter;
use qqworld_passport\modules\linkedin;
use qqworld_passport\modules\xiaomi;
use qqworld_passport\modules\taobao;
use qqworld_passport\modules\alipay;

$GLOBALS['qqworld_passport_modules'] = array();

define('QQWORLD_PASSPORT_DIR', __DIR__ . DIRECTORY_SEPARATOR);
define('QQWORLD_PASSPORT_LIB_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR);
define('QQWORLD_PASSPORT_URL', plugin_dir_url(__FILE__));

include_once QQWORLD_PASSPORT_DIR . 'options.php';

class core {
	var $text_domain = 'qqworld-passport';
	var $options;

	var $qq;
	var $wechat;
	var $weibo;
	var $baidu;
	var $taobao;
	var $alipay;

	public function __construct() {
		$this->options = new options;
	}

	public function outside_language() {
		__( 'Michael Wang', $this->text_domain );
	}

	public function init() {
		add_action( 'plugins_loaded', array($this, 'load_language') );
		add_action( 'admin_menu', array($this, 'admin_menu') );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_init', array($this, 'register_settings') );

		add_action( 'plugins_loaded', array($this, 'load_modules') );

		if (!empty($this->options->activated_modules)) {
			add_action( 'um_after_form', array($this, 'call_passport') ); // for Ultimate Member
			add_action( 'login_form', array($this, 'call_passport') ); // for wp-login.php
			add_action( 'woocommerce_login_form_end', array($this, 'call_passport') ); // for woocommerce form-login.php
			add_filter( 'login_form_middle', array($this, 'login_form_middle'), 10, 2 ); // for function wp_login_form()
			add_action( 'rest_api_init', array($this, 'register_oauth2_quest') );
			add_action( 'after_setup_theme', array($this, 'set_session_start') );
			add_filter( 'get_avatar', array($this, 'get_avatar'), $this->options->avatar_priority, 6 );

			add_action( 'show_user_profile', array($this, 'call_binding_social_media_account') );
		}

		add_filter( 'manage_users_columns', array($this, 'manage_users_columns') );
		add_filter( 'manage_users_custom_column', array($this, 'manage_users_custom_column'), 10, 3 );

		add_action( 'qqworld_passport_additional_form_settings', array($this, 'advertisement_qqworld_synchronizer') );
		add_action( 'qqworld_passport_additional_form_settings', array($this, 'advertisement_qqworld_mobile') );

		add_action( 'qqworld-passport', array($this, 'passport') );
		add_action( 'binding_social_media_account', array($this, 'binding_social_media_account') );

		// 必须执行登录post操作才会显示error
		//add_filter( 'wp_login_errors', array($this, 'wp_login_errors'), 0, 2 );
	}

	// 显示登录错误
	public function wp_login_errors($errors, $redirect_to) {
		if (isset($_COOKIE['qqworld-passport-login-error']) && $_COOKIE['qqworld-passport-login-error'] == 1) {
			$errors->add( 'cannot-auto-register', __( 'Your have to register a new account and binded this social media account, then you can login via this social media account.', $this->text_domain ), 'message' );
			setcookie('qqworld-passport-login-error', 1, -1, '/', $_SERVER['HTTP_HOST']);
		}
		return $errors;
	}

	public function manage_users_columns($column_headers){
		$column_headers['openid'] = __("Open ID", $this->text_domain);
		return $column_headers;
	}

	public function manage_users_custom_column($value, $column_name, $id) {
		if( $column_name == 'openid' ) {
			return apply_filters( 'qqworld-passport-openids', '', $id );
		}
	}

	public function call_passport() {
		do_action( 'qqworld-passport' );
	}

	public function call_binding_social_media_account($profileuser) {
		do_action( 'binding_social_media_account', $profileuser );
	}

	public function sanitize_callback($value) {
		return $value;
	}

	public function advertisement_qqworld_synchronizer() {
		if ( !is_plugin_active( 'qqworld-synchronizer/qqworld-synchronizer.php' ) ) {
?>
<div class="wrap" id="qqworld-synchronizer-container">
	<h2><?php _e('QQWorld Synchronizer', $this->text_domain); ?></h2>
	<p><?php _e("QQWorld Synchronizer is a component for QQWorld Passport.", $this->text_domain); ?></p>
	<img id="banner" src="<?php echo QQWORLD_PASSPORT_URL; ?>images/synchronizer/banner-772x250.png" title="<?php _e('QQWorld Synchronizer', $this->text_domain); ?>" />
	<ul id="extension-list">
		<li class="extension commercial">
			<aside class="attr pay"><a href="http://www.qqworld.org/products/qqworld-synchronizer" target="_blank"><?php _ex('$ Buy', 'extension', $this->text_domain); ?></a></aside>
			<figure class="extension-image" title="<?php _e('Wechat Robot', $this->text_domain); ?>"><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/synchronizer/wechat/plus.png"></figure>
			<h3 class="extension-label"><?php _e('Wechat Plus', $this->text_domain); ?></h3>
			<p class="extension-description"><?php _e('Automatic login, display follow us button, login in pc exproler via scan QR Code.', $this->text_domain); ?></p>
			<aside class="activate inactive"><?php _e('Inactive', $this->text_domain); ?></aside>
		</li>
		<li class="extension commercial">
			<aside class="attr pay"><a href="http://www.qqworld.org/products/qqworld-synchronizer" target="_blank"><?php _ex('$ Buy', 'extension', $this->text_domain); ?></a></aside>
			<figure class="extension-image" title="<?php _e('Wechat Robot', $this->text_domain); ?>"><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/synchronizer/wechat/robot.png"></figure>
			<h3 class="extension-label"><?php _e('Wechat Robot', $this->text_domain); ?></h3>
			<p class="extension-description"><?php _e('Make your website and WeChat public platform to interact.', $this->text_domain); ?></p>
			<aside class="activate inactive"><?php _e('Inactive', $this->text_domain); ?></aside>
		</li>
		<li class="extension commercial">
			<aside class="attr pay"><a href="http://www.qqworld.org/products/qqworld-synchronizer" target="_blank"><?php _ex('$ Buy', 'extension', $this->text_domain); ?></a></aside>
			<figure class="extension-image" title="<?php _e('Sync Posts to Wechat', $this->text_domain); ?>"><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/synchronizer/wechat/sync-posts.png"></figure>
			<h3 class="extension-label"><?php _e('Sync Posts to Wechat', $this->text_domain); ?></h3>
			<p class="extension-description"><?php _e('Automatically sync posts to your Wechat platform.', $this->text_domain); ?></p>
			<aside class="activate inactive"><?php _e('Inactive', $this->text_domain); ?></aside>
		</li>
	</ul>
</div>
<?php
		}
	}

	public function advertisement_qqworld_mobile() {
		if ( !is_plugin_active( 'qqworld-mobile/qqworld-mobile.php' ) ) {
?>
<div class="wrap" id="qqworld-mobile-container">
	<h2><?php _e('QQWorld Mobile', $this->text_domain); ?></h2>
	<p><?php _e("QQWorld Mobile is a component for QQWorld Passport, The featured such as Phone Nubmber Register and Sms Group Sends.", $this->text_domain); ?></p>
	<img id="banner" src="<?php echo QQWORLD_PASSPORT_URL; ?>images/mobile/banner-772x250.jpg" title="<?php _e('QQWorld Mobile', $this->text_domain); ?>" />
	<ul id="extension-list">
		<li class="extension commercial">
			<aside class="attr pay"><a href="https://www.qqworld.org/product/qqworld-mobile" target="_blank"><?php _ex('$ Buy', 'extension', $this->text_domain); ?></a></aside>
			<figure class="extension-image" title="<?php _e('Phone Number Login', $this->text_domain); ?>"><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/mobile/phone-number.png"></figure>
			<h3 class="extension-label"><?php _e('Phone Sign Up', $this->text_domain); ?></h3>
			<p class="extension-description"><?php _e('Phone Number register & Sms Group Sends.', $this->text_domain); ?></p>
			<aside class="activate inactive"><?php _e('Inactive', $this->text_domain); ?></aside>
		</li>
	</ul>
</div>
<?php
		}
	}

	public function binding_social_media_account($profileuser) {
		$_SESSION['redirect_uri'] = admin_url('/profile.php');
?>
	<h3><?php _e( 'Social Media Accounts', $this->text_domain ); ?></h3>
	<table id="binding_social_media_account" class="form-table">
		<tbody>
			<?php do_action( 'qqworld_passport_social_media_account_profile_form', $profileuser ); ?>
		</tbody>
	</table>
<?php
	}

	public function get_avatar($avatar, $id_or_email, $size, $default, $alt, $args) {
		$user_id = '';
		if ( filter_var($id_or_email, FILTER_VALIDATE_EMAIL) ) {
			$user = get_user_by( 'email', $id_or_email );
			$user_id = $user ? $user->ID : null;
		} else {
			$user_id = $id_or_email;
		}
		if (!empty($user_id)) {
			$url = get_user_meta($user_id ,'QQWorld Passport Avatar' ,true);
			if ($url) {
				$class = implode(' ', array( 'avatar', 'avatar-' . $size, 'photo' ));
				if ($url) $avatar = sprintf(
					"<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>",
					esc_attr( $alt ),
					esc_url( $url ),
					esc_attr( "$url 2x" ),
					esc_attr( $class ),
					(int) $args['height'],
					(int) $args['width'],
					$args['extra_attr']
				);
			}
		}
		return $avatar;
	}

	public function delete_user_meta($user_id, $meta_key, $meta_value) {
		global $wpdb;
		if ($meta_value) {
			$user_metas = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'usermeta WHERE `meta_value` = "'.$meta_value.'"', OBJECT );
			if (!empty($user_metas)) foreach ($user_metas as $user_meta) {
				// 只有和当前用户id不一样，且metakey一样的才会删掉
				if ($user_meta->user_id != $user_id && $user_meta->meta_key == $meta_key) delete_user_meta($user_meta->user_id, $user_meta->meta_key, $user_meta->meta_value);
			}
		}
		/*
		Array (
			[0] => stdClass Object
				(
					[umeta_id] => 288
					[user_id] => 1
					[meta_key] => QQWorld Passport Weibo Uid
					[meta_value] => 3217024355
				)

			[1] => stdClass Object
				(
					[umeta_id] => 988
					[user_id] => 13
					[meta_key] => QQWorld Passport Weibo Uid
					[meta_value] => 3217024355
				)

		)
		*/
	}

	// quick login by login name
	public function login($user_login, $redirect=true) {
		$user = get_user_by('login', $user_login);
		$user_id = $user->ID;
		wp_set_current_user($user_id, $user_login);
		wp_set_auth_cookie($user_id, true);
		do_action( 'wp_login', $user_login, $user );
		if ( isset($_SESSION['redirect_uri']) && !empty($_SESSION['redirect_uri']) ) {
			$redirect_uri = $_SESSION['redirect_uri'];
			unset($_SESSION['redirect_uri']);
		} else $redirect_uri = home_url();
		if (preg_match('/\.css$/i', $redirect_uri)) $redirect_uri = home_url();
		if ($redirect) {
			wp_redirect( $redirect_uri );
			exit;
		}
	}

	public function set_session_start() {
		session_start();
	}

	public function curl($url, $type='get', $body='', $headers='') { // $body => 'username=michel&password=...' | array('username' => '', 'password' => '')
		/*$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

		if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if ($type == 'post') {
			curl_setopt($ch, CURLOPT_POST, 1);
			if (!empty($args)) {
				if (is_array($args)) $args = http_build_query($args);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			}
		}
		$results = curl_exec($ch);
		curl_close($ch);*/
		$args = array(
			'method' => strtoupper($type)
		);
		if ($headers) $args['headers'] = $headers;
		if ($body) $args['body'] = $body;
		$response = wp_remote_request($url, $args);
		$results = wp_remote_retrieve_body($response);
		return json_decode($results);
	}

	public function get_current_user_id() {
		if (!empty($_COOKIE)) foreach ($_COOKIE as $key => $value) {
			if (preg_match('/^wordpress_logged_in_.*?$/i', $key, $match)) {
				$value = explode('|', $value);
				if (count($value)) {
					$user = get_user_by( 'login', $value[0] );
					return $user->ID;
				}
				break;
			}
		}
		return false;
	}

	/**
	 * check is user logged in from cookie
	**/
	public function is_user_logged_in() {
		if (!empty($_COOKIE)) foreach ($_COOKIE as $key => $value) {
			if (preg_match('/^wordpress_logged_in_.*?$/i', $key, $match)) {
				$value = explode('|', $value);
				return count($value);
			}
		}
		return false;
	}

	public function register_oauth2_quest() {
		$namespace = 'qqworld-passport/v1';
		// http://www.woocommerce.gov/wp-json/qqworld-passport/v1/module/qq
		register_rest_route( $namespace, 'module/(?P<slug>\w+)', array(
			'methods' => 'GET',
			'callback' => array($this, 'oauth2_quest'),
			'update_callback' => null,
			'schema' => null
		) );
		// http://www.woocommerce.gov/wp-json/qqworld-passport/v1/module/pre/qq
		register_rest_route( $namespace, 'module/pre/(?P<slug>\w+)', array(
			'methods' => 'GET',
			'callback' => array($this, 'pre_oauth2_quest'),
			'update_callback' => null,
			'schema' => null
		) );
	}

	public function oauth2_quest($data) {
		$slug = $data['slug'];
		do_action('qqworld_passport_parse_request_'.$slug);
		exit;
	}

	public function pre_oauth2_quest($data) {
		$slug = $data['slug'];
		do_action('qqworld_passport_pre_parse_request_'.$slug);
		exit;
	}

	public function login_form_middle($content, $args) {
		ob_start();
		$this->passport();
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	public function passport() {
		// for Ultimate Member checking template mode
		if (func_num_args() && func_get_arg(0)) {
			$args = func_get_arg(0);
			if (!in_array($args['mode'], array('login', 'register'))) return;
		}

		global $pagenow;
		if ( $pagenow != 'wp-login.php' ) $_SESSION['redirect_uri'] = isset($_SERVER['HTTP_REFERER']) ? set_url_scheme($_SERVER['HTTP_REFERER']) : set_url_scheme("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
?>
	<style>
	#qqworld-passport-container {
		padding: 0 0 10px;
	}
	#qqworld-passport-container .third-party-login-label {
		margin-bottom: 5px;
	}
	#qqworld-passport-container a {
		display: inline-block;
		padding: 5px;
		background: #f7f7f7;
		border: 1px solid #d4d4d4;
		border-radius: 3px;
		width: 32px;
		height: 32px;
		position: relative;
		box-sizing: initial;
		left: 0;
		top: 0;
	}
	#qqworld-passport-container a:hover {
		background: #fff;
	}
	#qqworld-passport-container img {
		width: 32px;
		height: 32px;
		margin: 0;
		padding: 0;
	}
	</style>
	<?php do_action('qqworld_passport_login_form_before'); ?>
	<div id="qqworld-passport-container">
		<p class="third-party-login-label"><label><?php _e('Third-Party Login', $this->text_domain); ?></label></p>
		<p><?php do_action('qqworld_passport_login_form_buttons'); ?></p>
	</div>
	<?php do_action('qqworld_passport_login_form_after'); ?>
<?php
	}

	//add link to plugin action links
	public function plugin_action_links( $links, $file ) {
		if ( dirname(plugin_basename( __FILE__ )) . '/qqworld-passport.php' === $file ) {
			$settings_link = '<a href="' . menu_page_url( 'qqworld-passport', 0 ) . '">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		}
		return $links;
	}

	public function load_modules() {
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'qq' . DIRECTORY_SEPARATOR . 'init.php';
		$this->qq = new qq;
		$this->qq->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'wechat' . DIRECTORY_SEPARATOR . 'init.php';
		$this->wechat = new wechat;
		$this->wechat->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'weibo' . DIRECTORY_SEPARATOR . 'init.php';
		$this->weibo = new weibo;
		$this->weibo->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'baidu' . DIRECTORY_SEPARATOR . 'init.php';
		$this->baidu = new baidu;
		$this->baidu->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'alipay' . DIRECTORY_SEPARATOR . 'init.php';
		$this->alipay = new alipay;
		$this->alipay->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR . 'init.php';
		$this->google = new google;
		$this->google->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'twitter' . DIRECTORY_SEPARATOR . 'init.php';
		$this->twitter = new twitter;
		$this->twitter->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'facebook' . DIRECTORY_SEPARATOR . 'init.php';
		$this->facebook = new facebook;
		$this->facebook->init();
		include_once QQWORLD_PASSPORT_DIR . 'modules' . DIRECTORY_SEPARATOR . 'line' . DIRECTORY_SEPARATOR . 'init.php';
		$this->line = new line;
		$this->line->init();
	}

	public function register_settings() {
		global $qqworld_passport_modules;
		register_setting($this->text_domain, 'qqworld-passport-modules');
		register_setting($this->text_domain, 'qqworld-passport-avatar-priority');
		register_setting($this->text_domain, 'qqworld-passport-automatic-register');
		if (!empty($qqworld_passport_modules)) {
			foreach ($qqworld_passport_modules as $module) {
				register_setting($this->text_domain.'-module-'.$module->slug, 'qqworld-passport-module-'.$module->slug, array($module, 'sanitize_callback'));
			}
		}

	}

	public function load_language() {
		load_plugin_textdomain( $this->text_domain, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	public function admin_menu() {
		$page_title = __('QQWorld Passport', $this->text_domain);
		$menu_title = __('QQWorld Passport', $this->text_domain);
		$capability = 'administrator';
		$menu_slug = $this->text_domain;
		$function = array($this, 'admin_page');
		$icon_url = 'none';
		$settings_page = add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url);
	}

	public function admin_enqueue_scripts() {
		wp_enqueue_style( $this->text_domain, QQWORLD_PASSPORT_URL . 'css/style.css' );
		wp_enqueue_script( $this->text_domain, QQWORLD_PASSPORT_URL . 'js/common.js', array('jquery') );
	}

	public function is_activated($module) {
		return is_array($this->options->activated_modules) && in_array($module, $this->options->activated_modules);
	}

	public function admin_page() {
		global $qqworld_passport_modules;
?>
<div class="wrap" id="qqworld-passport-container">
	<div class="notice notice-error"><p><?php printf(__('Users who have been supporting the QQWorld Passport have been sorry to tell you that this plugin is no longer updated. Instead, it is the <a href="%s" target="_blank">QQWorld Operating Officer</a>. The plugin is composed of QQWorld Passport, QQWorld Synchronizer and QQWorld Mobile. Welcome to use QQWorld Operating officer, Social login is permanently free.', $this->text_domain), 'https://www.qqworld.org/product/qqworld-operating-officer'); ?></p></div>
	<h2><?php _e('QQWorld Passport', $this->text_domain); ?></h2>
	<p><?php _e("QQWorld Passport for Wordpress, Many Oauth 2.0 log in methods.", $this->text_domain); ?></p>
	<img id="banner" src="<?php echo QQWORLD_PASSPORT_URL; ?>images/banner-772x250.png" title="<?php _e('QQWorld Passport', $this->text_domain); ?>" />
	
	<ul id="qqworld-passport-tabs">
		<li class="current"><?php _e('Settings', $this->text_domain); ?></li>
		<li class="hidden"><?php _e('Extension', $this->text_domain); ?></li>
		<li class="hidden"><?php _e('Other Products', $this->text_domain); ?></li>
		<li><?php _e('Contact', $this->text_domain); ?></li>
	</ul>

	<div class="tab-content">
		<form action="options.php" method="post" id="update-form">
			<?php settings_fields($this->text_domain); ?>
			<div class="icon32 icon32-qqworld-passport-settings" id="icon-qqworld-passport"><br></div>
			<table class="wp-list-table widefat plugins">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All'); ?></label><input id="cb-select-all-1" type="checkbox" /></td>
						<th scope="col" id="title" class="manage-column column-signin-method column-primary"><?php _e('Signin Methods', $this->text_domain); ?></th>
						<th>Logo</th>
						<th scope="col" id="author" class="manage-column column-description"><?php _e('Description', $this->text_domain); ?></th>
						<th scope="col" id="edit" class="manage-column column-edit"><?php _e('Edit'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-2"><?php _e('Select All'); ?></label><input id="cb-select-all-1" type="checkbox" /></td>
						<th scope="col" id="title" class="manage-column column-signin-method column-primary"><?php _e('Signin Methods', $this->text_domain); ?></th>
						<th>Logo</th>
						<th scope="col" id="author" class="manage-column column-description"><?php _e('Description', $this->text_domain); ?></th>
						<th scope="col" id="edit" class="manage-column column-edit"><?php _e('Edit'); ?></th>
					</tr>
				</tfoot>

				<tbody id="the-list">
				<?php
				if (!empty($qqworld_passport_modules)) :
					foreach ($qqworld_passport_modules as $module) :
						$is_activated = $this->is_activated($module->slug);
						$edit_link = admin_url( 'admin.php?page=qqworld-passport_settings_'.$module->slug );
				?>
					<tr id="module-<?php echo $module->slug; ?>" class="<?php echo $is_activated ? 'active' : 'inactive'; ?>">
						<th scope="row" class="check-column">
							<label class="screen-reader-text" for="cb-select-1"><?php echo $module->slug; ?></label>
							<input id="cb-select-1" type="checkbox" name="qqworld-passport-modules[]" value="<?php echo $module->slug; ?>"<?php if ($is_activated) echo ' checked'; ?> />
							<div class="locked-indicator"></div>
						</th>
						<td class="title column-title has-row-actions column-primary page-title" data-colname="<?php _e('Signin Methods', $this->text_domain); ?>">
						<?php if ($is_activated) : ?>
							<strong><a class="row-title" href="<?php echo $edit_link; ?>" title="<?php _e('Edit'); ?>&#147;<?php echo $module->name; ?>&#148;"><?php echo $module->name; ?></a></strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo $edit_link; ?>" title="<?php _e('Edit this item'); ?>"><?php _e('Edit'); ?></a>
							</div>
						<?php else: ?>
							<strong><?php echo $module->name; ?></strong>
						<?php endif; ?>
						</td>
						<td><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/icons/<?php echo $module->slug; ?>.png" /></td>
						<td class="date column-description"><?php echo $module->description; ?></td>
						<td class="date column-edit">
						<?php if ($is_activated) : ?>
							<a href="<?php echo $edit_link; ?>" class="button"><?php _e('Edit'); ?></a>
						<?php else: ?>
							<input type="button" class="button" value="<?php _e('Edit'); ?>" disabled />
						<?php endif; ?>
						</td>
					</tr>
				<?php
					endforeach;
				endif; ?>
				</tbody>
			</table>
			<script>
			$('[name="qqworld-passport-modules[]"]').each(function() {
				var value = $(this).val();
				if (value == 'alipay' || value == 'google' || value == 'twitter' || value == 'facebook' || value == 'line') {
					$(this).attr('disabled', 'disabled');
				}
			});
			</script>
			<?php submit_button(); ?>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><label for="qqworld-passport-avatar-priority"><?php _e('Avatar Priority', $this->text_domain); ?></label></th>
						<td><fieldset>
							<legend class="screen-reader-text"><span><?php _e('Enabled', $this->text_domain); ?></span></legend>
							<label>
								<input name="qqworld-passport-avatar-priority" type="number" id="qqworld-passport-avatar-priority" value="<?php echo $this->options->avatar_priority; ?>" />
								<p><?php _e('Default is 9999, if you want QQWorld Password to fully take over the avatar display address from the other plugins, please set a larger number.', $this->text_domain); ?></p>
							</label>
						</fieldset></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="qqworld-passport-automatic-register"><?php _e('Automatic Register', $this->text_domain); ?></label></th>
						<td><fieldset>
							<legend class="screen-reader-text"><span><?php _e('Enabled', $this->text_domain); ?></span></legend>
							<label>
								<input name="qqworld-passport-automatic-register" type="checkbox" id="qqworld-passport-automatic-register" value="1" <?php checked($this->options->automatic_register, 1); ?>" />
								<p><?php printf(__("If hasn't binded social media account, then create new account automatically. and do not forget allowed anyone can register in <a href=\"%s\" target=\"_blank\">General Settings</a>", $this->text_domain), admin_url('options-general.php')); ?></p>
							</label>
						</fieldset></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="avatar-desc"><?php _e('Documents', $this->text_domain); ?></label></th>
						<td><fieldset>
							<legend class="screen-reader-text"><span><?php _e('Documents', $this->text_domain); ?></span></legend>
							<label>
								<dl>
									<dt><strong><?php _e('How to print avatar image?', $this->text_domain); ?></strong></dt>
									<dd>&lt;?php get_avatar($id_or_email, $size, $default, $alt, $args); ?&gt;<br /><?php printf(__('Reference: <a href="%s" target="_blank">%s</a>', $this->text_domain), 'https://codex.wordpress.org/Function_Reference/get_avatar', 'https://codex.wordpress.org/Function_Reference/get_avatar'); ?></dd>
									<dt><strong><?php _e('How to get avatar image URL?', $this->text_domain); ?></strong></dt>
									<dd>&lt;?php get_user_meta($user->ID, 'QQWorld Passport Avatar', true); ?&gt;<br /><?php printf(__('Reference: <a href="%s" target="_blank">%s</a>', $this->text_domain), 'https://codex.wordpress.org/Function_Reference/get_user_meta', 'https://codex.wordpress.org/Function_Reference/get_user_meta'); ?></dd>
									<dt><strong><?php _e('How to update avatar image URL?', $this->text_domain); ?></strong></dt>
									<dd>&lt;?php update_user_meta($user->ID, 'QQWorld Passport Avatar', $image_url); ?&gt;<br /><?php printf(__('Reference: <a href="%s" target="_blank">%s</a>', $this->text_domain), 'https://codex.wordpress.org/Function_Reference/update_user_meta', 'https://codex.wordpress.org/Function_Reference/update_user_meta'); ?></dd>
									<dt><strong><?php _e('How to print third-part login list?', $this->text_domain); ?></strong></dt>
									<dd>&lt;?php do_action('qqworld-passport'); ?&gt;</dd>
									<dd><pre>&lt;?php 
ob_start();
do_action('qqworld-passport'); 
$codes = ob_get_contents();
ob_end_clean();
?&gt;</pre></dd>
									<dt><strong><?php _e('How to print buttons of binding social media account?', $this->text_domain); ?></strong></dt>
									<dd>&lt;?php<br />if (!function_exists('get_user_to_edit')) include(ABSPATH . '/wp-admin/includes/user.php');<br />do_action( 'binding_social_media_account', get_user_to_edit(get_current_user_id()) );<br />?&gt;</dd>
								<dl>
							</label>
						</fieldset></td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<div class="tab-content hidden">
		<?php do_action('qqworld_passport_additional_form_settings'); ?>
	</div>
	<div class="tab-content hidden">
		<?php
		$plugins = array(
			'qqworld-collector' => array(
				'recommend' => true,
				'url' => 'https://www.qqworld.org/product/qqworld-collector',
				'thumbnail' => QQWORLD_PASSPORT_URL . 'images/products/plugins/qqworld-collector/thumbnail.jpg',
				'title' => __('QQWorld Collector', $this->text_domain),
				'description' => __('感觉维护网站的工作很繁重吗？本插件将为你节约大量的时间！QQWorld收藏家是一款Wordpress采集插件，可以采集绝大部分网站，特色是定时批量采集微信公众号和头条号，抓取微信图片、支持水印，支持阿里云OSS、又拍云、腾讯COS、七牛云存储和百度BOS，尤其是可以在同步后自动删除本地附件，建立远程云媒体库……', $this->text_domain),
				'metas' => array(
					'author' => array(
						'label' => __('Author'),
						'name' => __('Michael Wang', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					),
					'company' => array(
						'label' => __('Company', $this->text_domain),
						'name' => __('QQWorld', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					)
				)
			),
			'qqworld-theme-maker' => array(
				'recommend' => true,
				'url' => 'https://www.qqworld.org/product/qqworld-framework',
				'thumbnail' => QQWORLD_PASSPORT_URL . 'images/products/plugins/qqworld-framework/thumbnail.jpg',
				'title' => __('QQWorld Theme Maker', $this->text_domain),
				'description' => __('众多的设计狮存在一个苦恼，为何自己设计的漂亮的网站必须依靠程序员才能做成成品上线，作为一个不懂编程的设计狮能否独立完成一个网站的建设呢？答案是肯定的，阿Q的项目倾力打造的QQWorld主题制造可以实现这一目标。', $this->text_domain),
				'metas' => array(
					'author' => array(
						'label' => __('Author'),
						'name' => __('Michael Wang', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					),
					'company' => array(
						'label' => __('Company', $this->text_domain),
						'name' => __('QQWorld', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					)
				)
			),
			'qqworld-woocommerce-assistant' => array(
				'recommend' => true,
				'url' => 'https://www.qqworld.org/product/qqworld-woocommerce-assistant',
				'thumbnail' => QQWORLD_PASSPORT_URL . 'images/products/plugins/qqworld-woocommerce-assistant/thumbnail.jpg',
				'title' => __('QQWorld Woocommerce Assistant', $this->text_domain),
				'description' => __('Woocommerce是一款非常优秀的电子商务插件，不过因为它的西方血统导致其在中国有些水土不服，本插件基于解决这些问题，为帮助Woocommerce能够更好地为中国地区用户服务而生。', $this->text_domain),
				'metas' => array(
					'author' => array(
						'label' => __('Author'),
						'name' => __('Michael Wang', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					),
					'company' => array(
						'label' => __('Company', $this->text_domain),
						'name' => __('QQWorld', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					)
				)
			),
			'qqworld-checkout' => array(
				'recommend' => false,
				'url' => 'https://www.qqworld.org/product/qqworld-checkout',
				'thumbnail' => QQWORLD_PASSPORT_URL . 'images/products/plugins/qqworld-checkout/thumbnail.jpg',
				'title' => __('QQWorld Checkout', $this->text_domain),
				'description' => __('本插件致力于将手机短信功能和网站结合起来，支持手机号注册，短信找回密码以及适用于Woocommerce的短信通知客户发货物流信息。', $this->text_domain),
				'metas' => array(
					'author' => array(
						'label' => __('Author'),
						'name' => __('Michael Wang', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					),
					'company' => array(
						'label' => __('Company', $this->text_domain),
						'name' => __('QQWorld', $this->text_domain),
						'url' => 'https://www.qqworld.org',
					)
				)
			)
		);
		?>
		<ul class="qqworld-product-list">
		<?php foreach ($plugins as $plugin) : ?>
			<li>
				<figure class="photo">
					<?php if ($plugin['recommend']) : ?><span class="recommend"><?php _e('Recommend', $this->text_domain); ?></span><?php endif; ?>
					<a href="<?php echo $plugin['url']; ?>" target="_blank" title="<?php _e('Purchase', $this->text_domain); ?>"><img src="<?php echo $plugin['thumbnail']; ?>" /></a>
				</figure>
				<h3><?php echo $plugin['title']; ?></h3>
				<p class="description"><?php echo $plugin['description']; ?></p>
				<p class="metas">
				<?php foreach ($plugin['metas'] as $className => $meta) : ?>
					<span class="<?php echo $className; ?>"><?php echo $meta['label']; ?> / <a href="<?php echo $meta['url']; ?>" target="_blank"><?php echo $meta['name']; ?></a></span>
				<?php endforeach; ?>
				</p>
			</li>
		<?php endforeach; ?>
		</ul>
	</div>
	<!-- Contact -->
	<div class="tab-content hidden">
		<table id="contact-table" class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for=""><?php _ex('Developer', 'contact', $this->text_domain); ?></label></th>
					<td><?php _e('Michael Wang', $this->text_domain); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for=""><?php _e('Official Website', $this->text_domain); ?></label></th>
					<td><a href="https://www.qqworld.org" target="_blank">www.qqworld.org</a></td>
				</tr>
				<tr>
					<th scope="row"><label for=""><?php _e('Email'); ?></label></th>
					<td><a href="mailto:<?php _e('Michael Wang', $this->text_domain); ?> <admin@qqworld.org>">admin@qqworld.org</a></td>
				</tr>
				<tr>
					<th scope="row"><label for=""><?php _e('Tencent QQ', $this->text_domain); ?></label></th>
					<td><a href="http://wpa.qq.com/msgrd?v=3&uin=172269588&site=qq&menu=yes" target="_blank">172269588</a> (<?php printf(__('%s: ', $this->text_domain), __('QQ Group', $this->text_domain)); ?>3372914)</td>
				</tr>
				<tr>
					<th scope="row"><label for=""><?php _e('Wechat', $this->text_domain); ?></label></th>
					<td><img src="<?php echo QQWORLD_PASSPORT_URL; ?>images/wechat-qrcode.png" class="contact-qrcode" />
					<p><?php _e('Please use the WeChat APP to scan the QR code.', $this->text_domain); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for=""><?php _e('Cellphone', $this->text_domain); ?></label></th>
					<td><a href="tel:13294296711">13294296711</a></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
<?php
	}
}
$core = new core;
$core->init();