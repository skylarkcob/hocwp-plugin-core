<?php
/**
 * Plugin Name: Post Box
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Used For: example.com
 * Last Updated: 22/05/2019
 * Coder: laidinhcuongvn@gmail.com
 * Version: 1.0.2
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: post-box
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once dirname( __FILE__ ) . '/core/core.php';

class Post_Box extends Post_Box_Core {
	protected static $instance;

	protected $plugin_file = __FILE__;

	public $option_defaults = array(
		'number'    => 5,
		'column'    => 5,
		'post_type' => 'post',
		'price'     => 0
	);

	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public $post_type_trusted_source = 'trusted_source';
	public $trusted_links = '';

	public function __construct() {
		$this->option_defaults['discover_more_text'] = __( 'Discover more', $this->textdomain );

		parent::__construct();

		if ( self::$instance instanceof self ) {
			return;
		}

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'custom_wp_enqueue_scripts_action' ) );
		}

		add_shortcode( 'post_box', array( $this, 'shortcode_post_box_func' ) );
	}

	public function shortcode_post_box_func( $atts = array() ) {
		$atts = shortcode_atts( $this->option_defaults, $atts );

		$post_type = $atts['post_type'];

		$post_type = explode( ',', $post_type );
		$post_type = array_map( 'trim', $post_type );

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $atts['number'],
			'post_status'    => 'publish'
		);

		$query = new WP_Query( $args );

		ob_start();

		if ( $query->have_posts() ) {
			global $post;

			$discover_more_text = $atts['discover_more_text'];

			if ( $query->post_count > $atts['column'] ) {
				$chunk = array_chunk( $query->get_posts(), $atts['column'] );
			} else {
				$chunk = array( $query->get_posts() );
			}

			$show_price = $atts['price'];
			?>
            <div class="post-box hocwp">
                <div class="container row_inner pagewidth">
                    <div class="loop">
						<?php
						foreach ( $chunk as $part ) {
							?>
                            <div class="post-row">
								<?php
								foreach ( $part as $post ) {
									setup_postdata( $post );
									?>
                                    <div <?php post_class( 'post-item' ); ?>>
                                        <div class="post-inner">
                                            <div class="post-bg"></div>
                                            <div class="post-box-inner">
												<?php
												if ( has_post_thumbnail() ) {
													?>
                                                    <a href="<?php the_permalink(); ?>"
                                                       title="<?php the_title(); ?>"><?php the_post_thumbnail(); ?></a>
													<?php
												}
												?>
                                                <div class="post-name">
                                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                                </div>
												<?php
												if ( 0 != $show_price ) {
													$price = '';

													if ( 1 == $show_price ) {
														$price = get_post_meta( $post->ID, 'price', true );
													}

													if ( empty( $price ) || 1 == $show_price) {
														$price = __( 'Contact for price', $this->textdomain );
													}

													?>
                                                    <p class="post-price">
                                                        <span><?php echo $price; ?></span>
                                                    </p>
													<?php
												}

												if ( ! empty( $discover_more_text ) ) {
													?>
                                                    <p class="post-detail">
                                                        <a href="<?php the_permalink(); ?>"
                                                           class="btn btn-discover-more"><?php echo $discover_more_text; ?></a>
                                                    </p>
													<?php
												}
												?>
                                            </div>
                                        </div>
                                    </div>
									<?php
								}

								wp_reset_postdata();
								?>
                            </div>
							<?php
						}
						?>
                    </div>
                </div>
            </div>
			<?php
		}

		return ob_get_clean();
	}

	public function load_plugin_frontend() {
		return ( is_single() || is_singular() || is_home() || is_front_page() );
	}

	public function custom_wp_enqueue_scripts_action() {
		if ( $this->load_plugin_frontend() ) {
			wp_enqueue_style( $this->textdomain . '-style', $this->base_url . '/css/frontend.css' );
		}
	}

	public function custom_admin_init_action() {
		$args = array(
			'class' => 'regular-text',
			'type'  => 'number'
		);

		$this->add_settings_field( 'number', __( 'Post Number', $this->textdomain ), null, 'default', $args );
		$this->add_settings_field( 'column', __( 'Post Column', $this->textdomain ), null, 'default', $args );

		$args['type'] = 'text';

		$this->add_settings_field( 'discover_more_text', __( 'Discover More Text', $this->textdomain ), null, 'default', $args );

		$args['description'] = __( 'Each post type separated by commas.', $this->textdomain );

		$this->add_settings_field( 'post_type', __( 'Post Type', $this->textdomain ), null, 'default', $args );

		unset( $args );
	}
}

function Post_Box() {
	return Post_Box::get_instance();
}

add_action( 'plugins_loaded', function () {
	Post_Box();
} );

function post_box_mark_on_activation() {
	set_transient( 'flush_rewrite_rules', 1 );
}

register_activation_hook( __FILE__, 'post_box_mark_on_activation' );

function post_box_mark_on_deactivation() {
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'post_box_mark_on_deactivation' );