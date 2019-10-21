<?php
/**
 * Plugin Name: Auto Fetch Post
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Used For: example.com
 * Last Updated: 21/10/2019
 * Coder: laidinhcuongvn@gmail.com
 * Version: 1.0.4
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: auto-fetch-post
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once dirname( __FILE__ ) . '/core/core.php';

class AUTO_FETCH_POST_DOMAIN {
	const AUTODAILY = 'autodaily.vn';
	const XEDOISONG = 'xedoisong.com';
	const XEHAY = 'xehay.vn';
	const AUTOPRO = 'autopro.com.vn';
	const OTOSAIGON = 'otosaigon.com';
	const AUTOBIKES = 'autobikes.vn';
}

class Auto_Fetch_Post extends Auto_Fetch_Post_Core {
	// Default plugin variable: Plugin single instance.
	protected static $instance;

	// Default plugin variable: Plugin file path.
	protected $plugin_file = __FILE__;

	// Default plugin variable: Plugin default options.
	public $option_defaults = array(
		'sites'                => array(
			'https://' . AUTO_FETCH_POST_DOMAIN::AUTODAILY . '/',
			'http://' . AUTO_FETCH_POST_DOMAIN::XEDOISONG . './',
			'https://' . AUTO_FETCH_POST_DOMAIN::XEHAY . './',
			'https://' . AUTO_FETCH_POST_DOMAIN::AUTOPRO . './',
			'https://' . AUTO_FETCH_POST_DOMAIN::OTOSAIGON . './',
			'https://' . AUTO_FETCH_POST_DOMAIN::AUTOBIKES . './'
		),
		'posts_number'         => 1,
		'post_status'          => 'pending',
		'reload_interval'      => 60,
		'fetch_posts_interval' => 60,
		'default_category'     => ''
	);

	public $plugin_content_dir;

	/*
	 * Default plugin function: Check single instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/*
	 * Default plugin function: Plugin construct.
	 */
	public function __construct() {
		parent::__construct();

		if ( self::$instance instanceof self ) {
			return;
		}

		// Default load action
		add_action( 'init', array( $this, 'load' ) );
	}

	private function create_skip_file( $content = '' ) {
		$file = $this->plugin_content_dir;

		$file .= 'skip-link-' . date( 'Ymd' ) . '.txt';

		if ( ! file_exists( $file ) ) {
			$fp = fopen( $file, 'w' );
			fwrite( $fp, $content );
			fclose( $fp );
		}
	}

	/*
	 * Default plugin function: Load plugin environment.
	 */
	public function load() {
		$file = trailingslashit( WP_CONTENT_DIR );
		$file .= trailingslashit( $this->textdomain );

		if ( ! is_dir( $file ) ) {
			mkdir( $file, 0777, true );
		}

		$this->plugin_content_dir = $file;

		$this->create_skip_file();

		require_once $this->base_dir . '/abstract-class-auto-fetch-post.php';
		require_once $this->base_dir . '/class-autodaily.php';
		require_once $this->base_dir . '/class-xedoisong.php';
		require_once $this->base_dir . '/class-xehay.php';
		require_once $this->base_dir . '/class-autopro.php';
		require_once $this->base_dir . '/class-otosaigon.php';
		require_once $this->base_dir . '/class-autobikes.php';

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );
			add_action( 'wp_ajax_auto_fetch_post', array( $this, 'custom_ajax' ) );
			add_action( 'wp_ajax_nopriv_auto_fetch_post', array( $this, 'custom_ajax' ) );
			add_filter( 'manage_posts_columns', array( $this, 'custom_manage_posts_columns_filter' ), 1 );

			add_action( 'manage_posts_custom_column', array(
				$this,
				'custom_manage_posts_custom_column_action'
			), 10, 2 );

		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'custom_wp_enqueue_scripts_action' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'custom_global_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'custom_global_scripts' ) );
			//add_action( 'wp', array( $this, 'custom_wp_action' ) );
			add_action( 'wp_footer', array( $this, 'custom_wp_footer_action' ) );
			add_filter( 'the_content', array( $this, 'custom_the_content_filter' ), 99 );
		}

		add_filter( 'posts_where', array( $this, 'custom_post_where_filter' ), 10, 2 );

		//$this->fetch_random_posts();
	}

	public function custom_manage_posts_custom_column_action( $column, $post_id ) {
		if ( 'source_domain' == $column ) {
			$source_url = get_post_meta( $post_id, 'source_url', true );

			if ( ! empty( $source_url ) ) {
				echo $this->get_domain_name( $source_url );
			}
		}
	}

	public function custom_manage_posts_columns_filter( $columns ) {
		$columns['source_domain'] = __( 'Source domain', $this->textdomain );

		return $columns;
	}

	public function custom_the_content_filter( $content ) {
		$search = array(
			'<p></p>',
			'<p>&nbsp;</p>',
			'<p> </p>'
		);

		$replace = array(
			'',
			'',
			''
		);

		$content = str_replace( $search, $replace, $content );

		$add_before_content = $this->get_option( 'add_before_content' );

		if ( ! empty( $add_before_content ) && false === strpos( $content, 'custom-before-content' ) ) {
			$content = str_replace( 'class"fetched-content"', 'class="fetched-content"', $content );

			$add_before_content = '<span class="custom-before-content">' . $add_before_content . '</span>';

			$content = $add_before_content . $content;
		}

		return $content;
	}

	public function custom_wp_footer_action() {
		if ( is_single() || is_singular() ) {
			$post_id = get_the_ID();
			$source  = get_post_meta( $post_id, 'source_url', true );
			?>
			<input type="hidden" name="current_post_id" value="<?php echo $post_id; ?>"
			       data-source-url="<?php echo esc_attr( $source ); ?>"
			       data-source-domain="<?php echo esc_attr( $this->get_domain_name( $source, true ) ); ?>">
			<?php
		}
	}

	public function update_url_protocol( $url, $website = '' ) {
		if ( empty( $website ) ) {
			$sites = $this->get_option( 'sites' );

			if ( $this->array_has_value( $sites ) ) {
				$domain = $this->get_domain_name( $url, true );

				foreach ( (array) $sites as $site ) {
					if ( false !== strpos( $site, $domain ) ) {
						$website = $site;
						break;
					}
				}
			}
		}

		if ( ! empty( $website ) ) {
			$parse  = parse_url( $website );
			$scheme = isset( $parse['scheme'] ) ? $parse['scheme'] : '';

			if ( ! empty( $scheme ) ) {
				if ( false === strpos( $url, $scheme ) ) {
					$url = ltrim( $url, 'http://' );
					$url = ltrim( $url, 'https://' );
					$url = $scheme . '://' . $url;
				}
			}
		}

		return $url;
	}

	public function custom_ajax() {
		$data = array();

		$do_action = isset( $_REQUEST['do_action'] ) ? $_REQUEST['do_action'] : '';

		switch ( $do_action ) {
			case 'fetch_random_posts':
				$number = $this->get_option( 'posts_number' );
				$number = absint( $number );
				$number = max( $number, 1 );

				while ( $number > 0 ) {
					$this->fetch_random_posts();
					$number --;
				}

				break;
			case 'update_post_content':
				$post_id      = isset( $_POST['post_id'] ) ? $_POST['post_id'] : '';
				$post_content = isset( $_POST['post_content'] ) ? $_POST['post_content'] : '';

				if ( $this->is_positive_number( $post_id ) && ! empty( $post_content ) ) {
					$result = wp_update_post( array(
						'ID'           => $post_id,
						'post_content' => $post_content
					) );

					if ( ! $result || is_wp_error( $result ) ) {
						wp_send_json_error( $data );
					}
				}

				break;
			case 'update_post_content_links':
				$post_id = isset( $_POST['post_id'] ) ? $_POST['post_id'] : '';
				$links   = isset( $_POST['links'] ) ? $_POST['links'] : '';

				if ( $this->is_positive_number( $post_id ) && $this->array_has_value( $links ) ) {
					$domain = isset( $_POST['domain'] ) ? $_POST['domain'] : '';
					$links  = $this->fetch_post_links( $links, $domain );

					$object = get_post( $post_id );

					if ( $this->array_has_value( $links ) && $object instanceof WP_Post ) {
						$this->fetch_random_posts( $links, $domain, array(
							'ID'           => $post_id,
							'post_content' => $object->post_content
						) );
					}
				}

				break;
			case 'update_post_content_links_pending':
				$post_id = isset( $_POST['post_id'] ) ? $_POST['post_id'] : '';
				$links   = isset( $_POST['links'] ) ? $_POST['links'] : '';

				if ( $this->is_positive_number( $post_id ) && $this->array_has_value( $links ) ) {
					$object = get_post( $post_id );

					if ( $object instanceof WP_Post ) {
						$this->debug( $links );
					}
				}

				break;
		}

		wp_send_json_success( $data );
	}

	public function custom_wp_action() {
		if ( is_single() ) {
			$post_id = get_the_ID();

			$obj = get_post( $post_id );

			if ( $obj instanceof WP_Post ) {
				$source_url = get_post_meta( $post_id, 'source_url', true );

				if ( ! empty( $source_url ) ) {
					$link_updated = get_post_meta( $post_id, 'link_updated', true );

					if ( ! $link_updated ) {
						$domain = $this->get_domain_name( $source_url, true );

						$links = $this->fetch_post_links( $obj->post_content, $domain );

						if ( empty( $links ) ) {
							update_post_meta( $post_id, 'link_updated', true );
						} else {
							$postdata = array(
								'ID'           => $obj->ID,
								'post_content' => $obj->post_content
							);

							$this->fetch_random_posts( $links, $domain, $postdata );
						}
					} else {
						$links = $this->fetch_post_links( $obj->post_content );

						if ( $this->array_has_value( $links ) ) {
							$blog = get_bloginfo( 'url' );

							$replace = array();

							foreach ( $links as $index => $link ) {
								$domain = $this->get_domain_name( $link, true );
								$update = false;

								if ( false !== strpos( $blog, $domain ) && false !== strpos( $link, 'p=' ) ) {
									$parts = parse_url( $link );
									parse_str( $parts['query'], $query );

									$p = isset( $query['p'] ) ? $query['p'] : '';

									$tmp = get_post( $p );

									if ( $tmp instanceof WP_Post && 'publish' == $tmp->post_status ) {
										$replace[] = get_permalink( $tmp );
										$update    = true;
									}
								}

								if ( ! $update ) {
									unset( $links[ $index ] );
								}
							}

							if ( $this->array_has_value( $replace ) && $this->array_has_value( $links ) ) {
								$content = str_replace( $links, $replace, $obj->post_content );

								wp_update_post( array(
									'ID'           => $post_id,
									'post_content' => $content
								) );
							}
						}
					}
				}
			}
		}
	}

	public function custom_post_where_filter( $where, $wp_query ) {
		global $wpdb;

		if ( $title = $wp_query->get( 'find_title' ) ) {
			$where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( $wpdb->esc_like( $title ) ) . '%\'';
		}

		return $where;
	}

	public function find_exists_posts_by_title( $title ) {
		$args = array(
			'post_type'   => 'post',
			'post_status' => array( 'publish', 'pending', 'draft' ),
			'find_title'  => $title,
			'fields'      => 'ids'
		);

		return new WP_Query( $args );
	}

	public function fetch_random_posts( $urls = '', $domain = '', $postdata = '' ) {
		if ( empty( $urls ) ) {
			$sites = $this->get_option( 'sites' );
			$key   = array_rand( $sites );

			$website = $sites[ $key ];

			//$website = $sites[5];

			$domain = $this->get_domain_name( $website, true );

			$urls = $this->fetch_recent_post_url( $website );
		}

		if ( $this->array_has_value( $urls ) ) {
			global $wpdb;

			$args = array(
				'select' => 'post_id',
				'where'  => array(
					'meta_key' => 'source_url'
				)
			);

			$replace = array();

			if ( empty( $domain ) ) {
				$domain = $this->get_domain_name( $urls[0], true );
			}

			$count = 0;

			$tr_name = 'auto_fetching_post';

			if ( empty( $postdata ) ) {
				if ( false === get_transient( $tr_name ) ) {
					set_transient( $tr_name, 1, $this->get_option( 'fetch_posts_interval' ) );
				} else {
					return;
				}
			}

			$filename = $this->plugin_content_dir . 'skip-link-' . date( 'Ymd' ) . '.txt';

			if ( ! file_exists( $filename ) ) {
				file_put_contents( $filename, '' );
			}

			$skip = file_get_contents( $filename );
			$skip = explode( PHP_EOL, $skip );

			foreach ( $urls as $index => $url ) {
				if ( $this->get_option( 'posts_number' ) <= $count ) {
					break;
				}

				if ( false !== strpos( $url, 'googletagmanager.com' ) || false !== strpos( $url, 'facebook.com' ) || false !== strpos( $url, 'facebook.net' ) || false !== strpos( $url, '?p=tim-kiem' ) ) {
					$skip[] = $url;
					unset( $urls[ $index ] );
					continue;
				}

				if ( $this->array_has_value( $skip ) && in_array( $url, $skip ) ) {
					continue;
				}

				$added = false;

				$args['where']['meta_value'] = $url;

				$results = $this->mysql_select( $wpdb->postmeta, $args );

				if ( empty( $results ) ) {
					$post_data = array();
					$object    = null;

					switch ( $domain ) {
						case AUTO_FETCH_POST_DOMAIN::AUTODAILY:
							$object = new AutoDaily( $url );

							break;
						case AUTO_FETCH_POST_DOMAIN::XEDOISONG:
							break;
						case AUTO_FETCH_POST_DOMAIN::XEHAY:
							$object = new XeHay( $url );

							break;
						case AUTO_FETCH_POST_DOMAIN::AUTOPRO:
							$object = new AutoPro( $url );

							break;
						case AUTO_FETCH_POST_DOMAIN::OTOSAIGON:
							$object = new OtoSaigon( $url );

							break;
						case AUTO_FETCH_POST_DOMAIN::AUTOBIKES:
							$object = new AutoBikes( $url );

							break;
					}

					if ( $object instanceof Abstract_Auto_Fetch_Post ) {
						if ( ! empty( $object->get_title() ) && ! empty( $object->get_content() ) ) {
							$content = $object->get_content();

							if ( 20 < strlen( $content ) ) {
								$post_data['post_content'] = $content;

								$title = $object->get_title();
								$title = wp_strip_all_tags( $title );

								if ( false !== strpos( $title, '&nbsp;' ) ) {
									$title = explode( '&nbsp;', $title );

									$title = $title[1];
								}

								$title = trim( $title );

								$post_data['post_title'] = $title;

								$query = $this->find_exists_posts_by_title( $title );

								if ( $query->have_posts() ) {
									continue;
								}
							}
						}

						if ( ! empty( $post_data ) ) {
							$post_data['post_status'] = $this->get_option( 'post_status' );
							$post_data['post_type']   = 'post';

							$post_id = wp_insert_post( $post_data );

							if ( $this->is_positive_number( $post_id ) ) {
								$skip[] = $url;

								$replace[] = get_permalink( $post_id );

								if ( false === strpos( $url, 'http' ) ) {
									$url = $this->update_url_protocol( $url );
								}

								update_post_meta( $post_id, 'source_url', $url );

								if ( $this->is_media_file_exists( $object->get_thumbnail() ) ) {
									set_post_thumbnail( $post_id, $object->get_thumbnail() );
								}

								$default_category = $this->get_option( 'default_category' );

								if ( $this->is_positive_number( $default_category ) ) {
									wp_set_post_categories( $post_id, array( $default_category ) );
								} else {
									$cats = $object->get_category();

									if ( ! empty( $cats ) ) {
										$last = array_pop( $cats );
										$cat  = get_cat_ID( $last );

										if ( ! $this->is_positive_number( $cat ) ) {
											$data = wp_insert_term( $last, 'category' );

											if ( $this->array_has_value( $data ) && isset( $data['term_id'] ) ) {
												$cat = $data['term_id'];
											}
										}

										if ( $this->is_positive_number( $cat ) ) {
											$last = wp_strip_all_tags( $last );
											$last = trim( $last );
											wp_set_object_terms( $post_id, $last, 'category' );
										}
									}
								}

								$tags = $object->get_tag();

								if ( ! empty( $tags ) ) {
									$tags = array_map( 'wp_strip_all_tags', $tags );
									$tags = array_map( 'trim', $tags );
									wp_set_post_tags( $post_id, join( ',', $tags ) );
								}

								break;
							}
						}
					}
				} else {
					if ( $this->array_has_value( $postdata ) ) {
						if ( isset( $postdata['post_content'] ) ) {
							$added     = true;
							$replace[] = get_permalink( $results[0]->post_id );
						}
					}
				}

				if ( ! $added ) {
					unset( $urls[ $index ] );
				}
			}

			if ( $this->array_has_value( $skip ) ) {
				$skip = array_unique( $skip );
				$skip = array_filter( $skip );
				$skip = join( PHP_EOL, $skip );
				file_put_contents( $filename, $skip );
			}

			if ( $this->array_has_value( $postdata ) && $this->array_has_value( $replace ) ) {
				if ( isset( $postdata['post_content'] ) ) {
					$postdata['post_content'] = str_replace( $urls, $replace, $postdata['post_content'] );
					wp_update_post( $postdata );
				}
			}
		}
	}

	private function fetch_post_links( $string, $domain = '' ) {
		if ( ! is_array( $string ) ) {
			$links = $this->get_url_from_string( $string, $domain );
		} else {
			$links = $string;
		}

		$links = array_unique( $links );
		$links = array_filter( $links );

		$urls = array();

		if ( $this->array_has_value( $links ) ) {
			switch ( $domain ) {
				case AUTO_FETCH_POST_DOMAIN::AUTODAILY:
					$year  = date( 'Y' );
					$month = date( 'm' );

					$base = trailingslashit( $domain );
					$base .= trailingslashit( $year );
					$base .= trailingslashit( $month );

					foreach ( $links as $url ) {
						if ( false !== strpos( $url, $base ) ) {
							$urls[] = $url;
						} else {
							$parts = explode( '/', $url );
							$key   = array_search( $domain, $parts );

							if ( isset( $parts[ $key ] ) && isset( $parts[ $key + 1 ] ) && isset( $parts[ $key + 2 ] ) ) {
								$year  = absint( $parts[ $key + 1 ] );
								$month = absint( $parts[ $key + 2 ] );

								if ( checkdate( $month, 1, $year ) ) {
									$urls[] = $url;
								}
							}
						}
					}

					break;
				case AUTO_FETCH_POST_DOMAIN::XEDOISONG:
					break;
				case AUTO_FETCH_POST_DOMAIN::XEHAY:
					$base = trailingslashit( $domain );

					foreach ( $links as $url ) {
						if ( false !== strpos( $url, $base ) && false !== strpos( $url, '.html' ) ) {
							$urls[] = $url;
						}
					}

					break;
				case AUTO_FETCH_POST_DOMAIN::AUTOPRO:
					$base = trailingslashit( $domain );

					foreach ( $links as $url ) {
						if ( false !== strpos( $url, $base ) && false !== strpos( $url, '.chn' ) ) {
							$info  = pathinfo( $url );
							$parts = explode( '-', $info['filename'] );
							$last  = array_pop( $parts );

							if ( $this->is_positive_number( $last ) ) {
								$urls[] = $url;
							}
						}
					}

					break;
				case AUTO_FETCH_POST_DOMAIN::OTOSAIGON:
					$base = trailingslashit( $domain );
					$base .= 'threads/';

					foreach ( $links as $url ) {
						if ( false !== strpos( $url, $base ) ) {
							$info  = pathinfo( $url );
							$parts = explode( '.', $info['filename'] );
							$last  = array_pop( $parts );

							if ( $this->is_positive_number( $last ) || ( isset( $info['extension'] ) && $this->is_positive_number( $info['extension'] ) ) ) {
								$urls[] = $url;
							}
						}
					}

					break;
				case AUTO_FETCH_POST_DOMAIN::AUTOBIKES:
					$base = trailingslashit( $domain );

					foreach ( $links as $url ) {
						if ( false !== strpos( $url, $base ) && false !== strpos( $url, '.html' ) ) {
							$urls[] = $url;
						}
					}

					break;
				default:
					$urls = $links;
			}
		}

		return $urls;
	}

	/*
	 * Get recent post link from source site. Try reduce html string to exact match.
	 */
	public function fetch_recent_post_url( $website ) {
		$domain = $this->get_domain_name( $website, true );

		$res  = wp_remote_get( $website );
		$body = wp_remote_retrieve_body( $res );

		switch ( $domain ) {
			case AUTO_FETCH_POST_DOMAIN::AUTODAILY:
				$body = substr( $body, strpos( $body, 'id="contents"' ) );
				break;
			case AUTO_FETCH_POST_DOMAIN::XEDOISONG:
				break;
			case AUTO_FETCH_POST_DOMAIN::XEHAY:
				$body = substr( $body, strpos( $body, 'class="body"' ) );
				break;
			case AUTO_FETCH_POST_DOMAIN::AUTOPRO:
				$body = substr( $body, strpos( $body, '<div class="mainsection">' ) );
				break;
			case AUTO_FETCH_POST_DOMAIN::OTOSAIGON:
				$body = substr( $body, strpos( $body, '<div uix_component="MainContent" class="p-body-content">' ) );
				break;
			case AUTO_FETCH_POST_DOMAIN::AUTOBIKES:
				$body = substr( $body, strpos( $body, '<div id="fixMainBdy">' ) );
				break;
		}

		$urls = $this->fetch_post_links( $body, $domain );

		return $urls;
	}

	/*
	 * Default plugin function: Load styles and scripts on frontend.
	 */
	public function custom_wp_enqueue_scripts_action() {
		wp_enqueue_script( $this->textdomain, $this->base_url . '/js/frontend.js', array( 'jquery' ), false, true );

		$l10n = array(
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'reload_interval' => $this->get_option( 'reload_interval' )
		);

		wp_localize_script( $this->textdomain, 'AFP', $l10n );
	}

	public function custom_global_scripts() {
		wp_enqueue_script( $this->textdomain . '-global', $this->base_url . '/js/global.js', array( 'jquery' ), false, true );

		$l10n = array(
			'sites'                => $this->get_option( 'sites' ),
			'google_search'        => 'http://google.com/search?q=site:SITE_URL&tbm=nws&tbs=qdr:w',
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'fetch_posts_interval' => $this->get_option( 'fetch_posts_interval' )
		);

		wp_localize_script( $this->textdomain . '-global', 'AFPG', $l10n );
	}

	public function load_more_button() {
		ob_start();
		?>
		<button type="button"
		        class="bnt-blue load-more-button real-btn"
		        data-text="<?php echo esc_attr( __( 'Load more', $this->textdomain ) ); ?>"
		        data-loading-text="<?php echo esc_attr( __( 'Loading more', $this->textdomain ) ); ?>"><?php _e( 'Load more', $this->textdomain ); ?></button>
		<?php
		return ob_get_clean();
	}

	public function find_links_on_google( $url, $interval_key = 'w' ) {
		$base = 'http://google.com/search';

		$defaults = array(
			'q'   => 'site:' . $url,
			'tbm' => 'nws',
			'tbs' => 'qdr:' . $interval_key
		);

		$base = add_query_arg( $defaults, $base );

		$res  = wp_remote_get( $base );
		$body = wp_remote_retrieve_body( $res );

		return $body;
	}

	/*
	 * Default plugin function: Add setting fields on admin_init action.
	 */
	public function custom_admin_init_action() {
		//$this->add_settings_field( 'cse_api_key', __( 'Custom Search API Key', $this->textdomain ) );

		//$this->add_settings_field( 'cse_id', __( 'Search Engine ID', $this->textdomain ) );

		$args = array(
			'type'        => 'number',
			'class'       => 'small-text',
			'description' => __( 'The number of posts will be fetched on each process.', $this->textdomain )
		);

		$this->add_settings_field( 'posts_number', __( 'Posts Number', $this->textdomain ), '', 'default', $args );

		$args['description'] = __( 'Interval in seconds allows the web page to automatically reload when the user is inactive.', $this->textdomain );

		$this->add_settings_field( 'reload_interval', __( 'Reload Interval', $this->textdomain ), '', 'default', $args );

		$args['description'] = __( 'Time interval in seconds for the site to automatically fetch posts.', $this->textdomain );

		$this->add_settings_field( 'fetch_posts_interval', __( 'Fetch Posts Interval', $this->textdomain ), '', 'default', $args );

		$args = array(
			'class'       => 'regular-text',
			'description' => __( 'The default post status for new post.', $this->textdomain ),
			'options'     => get_post_statuses()
		);

		$this->add_settings_field( 'post_status', __( 'Default Post Status', $this->textdomain ), 'admin_setting_field_select', 'default', $args );

		$categories = get_categories( array(
			'hide_empty' => false
		) );

		$lists = array();

		foreach ( $categories as $cat ) {
			$lists[ $cat->term_id ] = $cat->name;
		}

		$args = array(
			'class'       => 'regular-text',
			'description' => __( 'The default category for posts which auto fetched.', $this->textdomain ),
			'options'     => $lists,
			'value'       => $this->get_option( 'default_category' )
		);

		$this->add_settings_field( 'default_category', __( 'Default Category', $this->textdomain ), 'admin_setting_field_select', 'default', $args );

		$args = array(
			'class'       => 'widefat',
			'description' => __( 'Prepend text or custom HTML to before post content.', $this->textdomain )
		);

		$this->add_settings_field( 'add_before_content', __( 'Add Before Content', $this->textdomain ), 'admin_setting_field_textarea', 'default', $args );
	}
}

function Auto_Fetch_Post() {
	return Auto_Fetch_Post::get_instance();
}

add_action( 'plugins_loaded', function () {
	Auto_Fetch_Post();
} );