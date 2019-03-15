<?php
/**
 * Plugin Name: App Summary
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Used For: example.com
 * Last Updated: 13/03/2019
 * Coder: laidinhcuongvn@gmail.com
 * Version: 1.0.2
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: app-summary
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once dirname( __FILE__ ) . '/core.php';

class App_Summary extends App_Summary_Core {
	protected static $instance;

	protected $plugin_file = __FILE__;

	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();

		if ( self::$instance instanceof self ) {
			return;
		}

		add_shortcode( 'app_summary', array( $this, 'shortcode_func' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );

			if ( $this->array_has_value( $this->get_meta_fields() ) ) {
				add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
				add_action( 'save_post', array( $this, 'save_post_action' ) );
			}

			add_action( 'admin_enqueue_scripts', array( $this, 'backend_scripts' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
			add_filter( 'the_content', array( $this, 'the_content_filter' ) );
		}
	}

	public function the_content_filter( $content ) {
		$post_id = get_the_ID();

		if ( is_single( $post_id ) ) {
			$position = $this->get_option( 'position' );

			if ( 'before' == $position ) {
				$tmp = do_shortcode( '[app_summary]' );
				$tmp .= PHP_EOL;

				$content = $tmp . $content;

				unset( $tmp );
			} elseif ( 'after' == $position ) {
				$tmp = do_shortcode( '[app_summary]' );
				$tmp .= PHP_EOL;

				$content .= $tmp;

				unset( $tmp );
			}
		}

		return $content;
	}

	public function get_meta_fields() {
		$value = $this->get_option( 'meta_fields' );

		if ( ! empty( $value ) ) {
			$value = explode( ',', $value );
			$value = array_map( 'trim', $value );
		}

		return $value;
	}

	public function section_taxonomy_fields_callback() {
		echo wpautop( __( 'You can set the fields which get value from taxonomy.', $this->textdomain ) );
	}

	public function get_taxonomy_objects() {
		$taxs   = get_taxonomies( array( 'public' => true, '_builtin' => false ) );
		$taxs[] = 'category';
		$taxs[] = 'post_tag';

		return array_map( 'get_taxonomy', $taxs );
	}

	public function custom_admin_init_action() {
		$args = array(
			'description' => __( 'The title for post information box on frontend. Use tag <code>%post_title%</code> for replacing with post title.', $this->textdomain )
		);

		$this->add_settings_field( 'box_title', __( 'Box Title', $this->textdomain ), null, 'default', $args );

		$args = array(
			'options' => array(
				'none'   => __( 'Do not add summary box to post content', $this->textdomain ),
				'before' => __( 'Before post content', $this->textdomain ),
				'after'  => __( 'After post content', $this->textdomain )
			),
			'class'   => 'regular-text'
		);

		$this->add_settings_field( 'position', __( 'Box Position', $this->textdomain ), 'admin_setting_field_select', 'default', $args );

		$args = array(
			'description' => __( 'Each field separated by commas.', $this->textdomain ),
			'class'       => 'widefat'
		);

		$this->add_settings_field( 'meta_fields', __( 'Meta Fields', $this->textdomain ), null, 'default', $args );

		$this->add_settings_field( 'ads', __( 'Ads', $this->textdomain ), 'admin_setting_field_textarea' );

		$fields = $this->get_meta_fields();

		if ( $this->array_has_value( $fields ) ) {
			$this->add_settings_section( 'taxonomy_fields', __( 'Taxonomy Fields', $this->textdomain ), array(
				$this,
				'section_taxonomy_fields_callback'
			) );

			$taxs = $this->get_taxonomy_objects();

			$options = array();

			foreach ( $fields as $text ) {
				$options[ $text ] = $text;
			}

			foreach ( $taxs as $taxonomy ) {
				if ( $taxonomy instanceof WP_Taxonomy ) {
					$args = array(
						'options' => $options
					);

					$this->add_settings_field( $taxonomy->name, $taxonomy->labels->singular_name, 'admin_setting_field_chosen', 'taxonomy_fields', $args );
				}
			}
		}

		unset( $args, $fields, $text, $options );
	}

	public function backend_scripts() {
		global $pagenow, $plugin_page;

		if ( 'post-new.php' == $pagenow || 'post.php' == $pagenow || $plugin_page == $this->get_option_name() ) {
			wp_enqueue_style( 'select2-style', $this->base_url . '/lib/select2/4.0.5/css/select2.min.css', array(), '4.0.5' );
			wp_enqueue_script( 'select2', $this->base_url . '/lib/select2/4.0.5/js/select2.min.js', array( 'jquery' ), '4.0.5', true );

			wp_enqueue_style( $this->textdomain . '-style', $this->get_base_url() . '/css/backend.css' );

			wp_enqueue_script( $this->textdomain, $this->get_base_url() . '/js/backend.js', array( 'jquery' ), false, true );
		}
	}

	public function meta_boxes( $post_type ) {
		$object = get_post_type_object( $post_type );

		add_meta_box(
			$post_type . '-information',
			sprintf( __( '%s Information', $this->textdomain ), $object->labels->singular_name ),
			array( $this, 'meta_boxes_html' ),
			array( $post_type ),
			'normal',
			'default'
		);
	}

	public function meta_boxes_html( $post ) {
		$post_id = $post->ID;
		$fields  = $this->get_meta_fields();
		?>
		<table class="form-table hocwp-theme">
			<tbody>
			<?php
			$field = __( 'Box Title', $this->textdomain );
			$name  = 'box_title';
			$value = get_post_meta( $post_id, $name, true );
			?>
			<tr>
				<th scope="row">
					<label
						for="<?php echo esc_attr( $name ); ?>"><?php echo $field; ?>:</label>
				</th>
				<td>
					<input type="text" id="<?php echo esc_attr( $name ); ?>"
					       name="<?php echo esc_attr( $name ); ?>"
					       class="regular-text" value="<?php echo esc_attr( $value ); ?>">
				</td>
			</tr>
			<?php
			$taxs = $this->get_taxonomy_objects();

			foreach ( $fields as $field ) {
				$name  = 'as_' . sanitize_title( $field );
				$value = get_post_meta( $post_id, $name, true );

				$select = false;

				foreach ( $taxs as $taxonomy ) {
					if ( $taxonomy instanceof WP_Taxonomy ) {
						$options = $this->get_option( $taxonomy->name );

						if ( $this->array_has_value( $options ) && in_array( $field, $options ) ) {
							$args = array(
								'taxonomy'   => $taxonomy->name,
								'hide_empty' => false
							);

							$terms = $this->get_terms( $args );

							ob_start();
							?>
							<select name="<?php echo esc_attr( $name ); ?>[]" id="<?php echo esc_attr( $name ); ?>"
							        data-chosen="1" multiple="multiple" class="widefat">
								<?php
								foreach ( $terms as $term ) {
									if ( $term instanceof WP_Term ) {
										$selected = false;

										if ( $value === $term->term_id || ( $this->array_has_value( $value ) && in_array( $term->term_id, $value ) ) ) {
											$selected = true;
										}
										?>
										<option
											value="<?php echo esc_attr( $term->term_id ); ?>"<?php selected( $selected, true ); ?>><?php echo esc_attr( $term->name ); ?></option>
										<?php
									}
								}
								?>
							</select>
							<?php
							$select = ob_get_clean();
						}
					}
				}
				?>
				<tr>
					<th scope="row">
						<label
							for="<?php echo esc_attr( $name ); ?>"><?php echo $field; ?>:</label>
					</th>
					<td>
						<?php
						if ( ! $select ) {
							?>
							<input type="text" id="<?php echo esc_attr( $name ); ?>"
							       name="<?php echo esc_attr( $name ); ?>"
							       class="regular-text" value="<?php echo esc_attr( $value ); ?>">
							<?php
						} else {
							echo $select;
						}
						?>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
	}

	public function save_post_action( $post_id ) {
		$fields = $this->get_meta_fields();

		if ( isset( $_POST['box_title'] ) ) {
			update_post_meta( $post_id, 'box_title', $_POST['box_title'] );
		}

		foreach ( $fields as $field ) {
			$name  = 'as_' . sanitize_title( $field );
			$value = isset( $_POST[ $name ] ) ? $_POST[ $name ] : '';

			update_post_meta( $post_id, $name, $value );
		}
	}

	public function frontend_scripts() {
		if ( is_single() ) {
			$object = get_post( get_the_ID() );

			if ( has_shortcode( $object->post_content, 'app_summary' ) ) {
				wp_enqueue_style( $this->textdomain . '-style', $this->base_url . '/css/frontend.css' );

				/*
				wp_enqueue_script( $this->textdomain, $this->base_url . '/js/frontend.js', array(), false, true );

				$l10n = array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' )
				);

				wp_localize_script( $this->textdomain, 'carPrice', $l10n );
				*/
			}
		}
	}

	public function shortcode_func( $atts = array() ) {
		$fields = $this->get_meta_fields();

		if ( ! $this->array_has_value( $fields ) ) {
			return '';
		}

		$atts = shortcode_atts( array( 'title' => '', 'post_id' => 0 ), $atts );

		$post_id = $atts['post_id'];

		if ( ! $this->is_positive_number( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$title = $atts['title'];

		if ( empty( $title ) ) {
			$title = get_post_meta( $post_id, 'box_title', true );

			if ( empty( $title ) ) {
				$title = $this->get_option( 'box_title' );
			}
		}

		ob_start();

		$taxs = $this->get_taxonomy_objects();

		foreach ( $fields as $field ) {
			$name  = 'as_' . sanitize_title( $field );
			$value = get_post_meta( $post_id, $name, true );

			if ( $this->array_has_value( $value ) ) {
				$tax_name = '';

				foreach ( $taxs as $taxonomy ) {
					if ( $taxonomy instanceof WP_Taxonomy ) {
						$options = $this->get_option( $taxonomy->name );

						if ( $this->array_has_value( $options ) && in_array( $field, $options ) ) {
							$tax_name = $taxonomy->name;
							break;
						}
					}
				}

				if ( ! empty( $tax_name ) ) {
					$links = '';

					foreach ( $value as $term_id ) {
						$term = get_term( $term_id, $tax_name );

						if ( $term instanceof WP_Term ) {
							$rel = 'tag';

							if ( is_taxonomy_hierarchical( $tax_name ) ) {
								$rel = 'category';
							}

							$links .= '<a href="' . esc_url( get_term_link( $term ) ) . '" rel="' . esc_attr( $rel ) . '">' . $term->name . '</a>, ';
						}
					}

					$links = rtrim( $links, ', ' );
					$links = trim( $links );

					$value = $links;
				}
			}

			if ( ! empty( $value ) ) {
				?>
				<tr>
					<th><?php echo $field; ?></th>
					<td><?php echo $value; ?></td>
				</tr>
				<?php
			}
		}

		$html = ob_get_clean();

		if ( ! empty( $html ) ) {
			ob_start();
			?>
			<table class="striped app-summary">
				<?php
				if ( ! empty( $title ) ) {
					$title = str_replace( '%post_title%', get_the_title( $post_id ), $title );
					?>
					<caption>
						<h2 id="summary"><?php echo $title; ?></h2>
					</caption>
					<?php
				}
				?>
				<tbody>
				<?php echo $html; ?>
				</tbody>
			</table>
			<?php
			$ads = $this->get_option( 'ads' );
			$ads = do_shortcode( $ads );
			$ads = wpautop( $ads );

			echo $ads;

			unset( $links, $term, $term_id, $tax_name, $taxs, $taxonomy, $name, $fields, $atts, $title, $post_id, $field, $value, $html, $ads );

			return ob_get_clean();
		}

		return '';
	}
}

function App_Summary() {
	return App_Summary::get_instance();
}

add_action( 'plugins_loaded', function () {
	App_Summary();
} );