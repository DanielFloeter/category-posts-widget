<?php
/**
 * Implementation of the widget class.
 *
 * @package categoryposts.
 *
 * @since 4.7
 */

namespace categoryPosts;

// Don't call the file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category Posts Widget Class
 *
 * Shows the single category posts with some configurable options
 */
class Widget extends \WP_Widget {

	/**
	 * Widget constructor.
	 */
	public function __construct() {
		$widget_ops = array(
			'show_instance_in_rest' => true,
			'classname'   => 'cat-post-widget',
			'description' => __( 'List single category posts', 'category-posts' ),
		);
		parent::__construct( WIDGET_BASE_ID, __( 'Category Posts', 'category-posts' ), $widget_ops );
	}

	/**
	 * Calculate the attachment size for showing the feature image
	 *
	 * The attachment size should for width and height greater than the widget image width and height settings.
	 *
	 * @param int          $post_thumbnail_id The id of the featured image attachment.
	 * @return string      The HTML for the thumb related to the post
	 *
	 * @since 4.9.22
	 */
	public function post_thumbnail_attachment_size( $post_thumbnail_id ) { 
		// Get attachment image by size
		$image_sizes = array('thumbnail', 'medium', 'large', 'full');
		foreach ($image_sizes as $img_size) {
			$attachment_img_size = wp_get_attachment_image_src($post_thumbnail_id, $img_size);
			if ($attachment_img_size[1] >= intval($this->instance['thumb_w']) && $attachment_img_size[2] >= intval($this->instance['thumb_h']) && ! (0 === intval($this->instance['thumb_w']) && 0 === intval($this->instance['thumb_h'])) ||
				'full' === $img_size) {
				$html = wp_get_attachment_image(
					$post_thumbnail_id,
					$img_size,
					false,
					array
					(
						"data-cat-posts-width" => intval($this->instance['thumb_w']),
						"data-cat-posts-height" => intval($this->instance['thumb_h'])
					)
				);
				break;
			}
		}
		if ( ! $html ) {
			return '';
		}
		return $html;
	}

	/**
	 * Calculate the HTML for showing the feature image of a post item.
	 *
	 * Used as a filter for the thumb wordpress API to add css based stretching and cropping
	 * when the image is not at the requested dimensions
	 *
	 * @param int          $post_id the ID of the post of which the thumb is a featured image.
	 * @param int          $post_thumbnail_id The id of the featured image attachment.
	 * @param string|array $size The requested size identified by name or (width, height) array.
	 * @param mixed        $attr ignored in this context.
	 * @return string The HTML for the thumb related to the post
	 *
	 * @since 4.1
	 */
	public function post_thumbnail_html( $post_id, $post_thumbnail_id, $size, $attr ) { 

		$html = $this->post_thumbnail_attachment_size($post_thumbnail_id);

		// normalize style
		$html = preg_replace( '/style="([^"]*)"/i', '', $html );

		// replace size.
		$array = array();
		preg_match( '/width="([^"]*)"/i', $html, $array );
		$pattern = '/\s' . $array[1] . 'px/';
		$html = preg_replace( $pattern, ' ' . $size[0] . 'px', $html );

		// replace width.
		$pattern = '/width="' . $array[1] . '"/';
		if ( 0 != $this->instance['thumb_w'] ) {
			$html = preg_replace( $pattern, 'width="' . $size[0] . '"', $html );
		} else {
			$html = preg_replace( $pattern, '', $html );
		}

		// replace height
		$array = array();
		preg_match( '/height="([^"]*)"/i', $html, $array );
		$pattern = '/height="' . $array[1] . '"/';
		if ( 0 != $this->instance['thumb_h'] ) {
			$html = preg_replace( $pattern, 'height="' . $size[1] . '"', $html );
		} else {
			$html = preg_replace( $pattern, '', $html );
		}

		$show_post_format = isset( $this->instance['show_post_format'] ) && ( 'none' !== $this->instance['show_post_format'] );
		if ( $show_post_format || $this->instance['thumb_hover'] ) {
			$format = get_post_format() ? : 'standard';
			$post_format_class = 'cat-post-format cat-post-format-' . $format;
		}
		$html = '<span class="cat-post-crop ' . $post_format_class . '">' . $html . '</span>';

		return $html;
	}

	/*
	 * wrapper to execute the the_post_thumbnail with filters.
	 */
	/**
	 * Calculate the HTML for showing the thumb of a post item.
	 *
	 * It is a wrapper to execute the the_post_thumbnail with filters
	 *
	 * @param  string|array $size The requested size identified by name or (width, height) array.
	 *
	 * @return string The HTML for the thumb related to the post and empty string if it can not be calculated
	 *
	 * @since 4.1
	 */
	public function the_post_thumbnail( $size = 'post-thumbnail' ) {
		if ( empty( $size ) ) { // if junk value, make it a normal thumb.
			$size = 'post-thumbnail';
		} elseif ( is_array( $size ) && ( 2 === count( $size ) ) ) {  // good format at least.
			// normalize to ints first.
			list( $width, $height ) = array_map('intval', $size); 
			if ( ( 0 === $width ) && ( 0 === $height ) ) { // Both values zero then revert to ratio from the orig with large.
				$size = array( get_option( 'large_size_w', 150 ), get_option( 'large_size_h', 150 ) );
			} elseif ( ( 0 === $width ) && ( 0 !== $height ) ) {
				// if thumb width 0 set to max/full widths for wp rendering.
				$post_thumb = get_the_post_thumbnail( get_the_ID(), 'full' );
				preg_match( '/(?<=width=")[\d]*/', $post_thumb, $thumb_full_w );
				$size[0] = $thumb_full_w[0];
			} elseif ( ( 0 !== $width ) && ( 0 === $height ) ) {
				// if thumb height 0 get full thumb for ratio and calc height with ratio.
				$post_thumb = get_the_post_thumbnail( get_the_ID(), 'full' );
				preg_match( '/(?<=width=")[\d]*/', $post_thumb, $thumb_full_w );
				preg_match( '/(?<=height=")[\d]*/', $post_thumb, $thumb_full_h );
				$ratio = $thumb_full_w[0] / $thumb_full_h[0];
				$size[1] = intval( $width / $ratio );
			}
		} else {
			$size = array( get_option( 'thumbnail_size_w', 150 ), get_option( 'thumbnail_size_h', 150 ) ); // yet another form of junk.
		}

		$post_thumbnail_id = get_post_thumbnail_id( get_the_ID() );
		if ( ! $post_thumbnail_id && $this->instance['default_thunmbnail'] ) {
			$post_thumbnail_id = $this->instance['default_thunmbnail'];
		}

		do_action( 'begin_fetch_post_thumbnail_html', get_the_ID(), $post_thumbnail_id, $size );
		$ret = $this->post_thumbnail_html( get_the_ID(), $post_thumbnail_id, $size, '' );
		do_action( 'end_fetch_post_thumbnail_html', get_the_ID(), $post_thumbnail_id, $size );

		return $ret;
	}

	/**
	 * Excerpt more link filter
	 *
	 * @param string $more The "more" text passed by the filter.
	 *
	 * @return string The link to the post with the "more" text configured in the widget.
	 */
	public function excerpt_more_filter( $more ) {
		return ' <a class="cat-post-excerpt-more more-link" href="' . get_permalink() . '">' . esc_html( $this->instance['excerpt_more_text'] ) . '</a>';
	}

	/**
	 * Apply the_content filter for excerpt
	 * This should show sharing buttons which comes with other widgets in the widget output in the same way as on the main content
	 *
	 * @param string $text The HTML with other applied excerpt filters.
	 *
	 * @return string If option hide_social_buttons is unchecked applay the_content filter.
	 *
	 * @since 4.6
	 */
	public function apply_the_excerpt( $text ) {
		$ret = apply_filters( 'the_content', $text );

		return $ret;
	}

	/**
	 * Calculate the wp-query arguments matching the filter settings of the widget
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @return array The array that can be fed to wp_Query to get the relevant posts
	 *
	 * @since 4.6
	 */
	public function queryArgs( $instance ) {
		$valid_sort_orders = array(
								'date', 'title', 'comment_count', 'rand'
							);

		if ( isset( $instance['sort_by'] ) && in_array( $instance['sort_by'], $valid_sort_orders, true ) ) {
			$sort_by = $instance['sort_by'];
		} else {
			$sort_by = 'date';
		}
		$sort_order = ( isset( $instance['asc_sort_order'] ) && $instance['asc_sort_order'] ) ? 'ASC' : 'DESC';

		// start sticky posts
		$ignore_sticky = isset( $instance['sticky'] ) && $instance['sticky'] ? false : true;

		// Get array of post info.
		$args = array(
			'orderby'             => $sort_by,
			'order'               => $sort_order,
			'ignore_sticky_posts' => $ignore_sticky, // Make sure we do not get stickies out of order.
			'no_found_rows'       => true, // Do not count the total numbers of rows by default.
		);

		$non_default_valid_status = array(
			'publish',
			'future',
			'publish,future',
			'private',
			'private,publish',
			'private,publish,future',
		);
		if ( isset( $instance['status'] ) && in_array( $instance['status'], $non_default_valid_status, true ) ) {
			$args['post_status'] = $instance['status'];
		}

		if ( isset( $instance['num'] ) ) {
			$args['showposts'] = (int) $instance['num'];
		}

		if ( isset( $instance['offset'] ) && ( (int) $instance['offset'] > 1 ) ) {
			$args['offset'] = (int) $instance['offset'] - 1;
		}
		if ( isset( $instance['cat'] ) ) {
			if ( isset( $instance['no_cat_childs'] ) && $instance['no_cat_childs'] ) {
				$args['category__in'] = (int) $instance['cat'];
			} else {
				$args['cat'] = (int) $instance['cat'];
			}
		}

		if ( is_singular() && isset( $instance['exclude_current_post'] ) && $instance['exclude_current_post'] ) {
			$args['post__not_in'] = array( get_the_ID() );
		}

		if ( isset( $instance['hideNoThumb'] ) && $instance['hideNoThumb'] ) {
			$args = array_merge(
				$args,
				array(
					'meta_query' => array(
						array(
							'key'     => '_thumbnail_id',
							'compare' => 'EXISTS',
						),
					),
				)
			);
		}

		switch ( $instance['date_range'] ) {
			case 'days_ago':
				$ago = (int) $instance['days_ago'];

				// If there is no valid integer value given, bail.
				if ( 0 === $ago ) {
					break;
				}

				$date = date( 'Y-m-d', strtotime( '-' . $ago . ' days' ) );
				$args['date_query'] = array(
					'after'     => $date,
					'inclusive' => true,
				);
				break;
			case 'between_dates':
				// Validation note - not doing any, assuming the query will
				// fail gracefully enough for now as it is not clear what Should
				// the validation be right now.
				$start_date = $instance['start_date'];
				$end_date = $instance['end_date'];
				$args['date_query'] = array(
					'after'     => $start_date,
					'before'    => $end_date,
					'inclusive' => true,
				);
				break;
		}

		return $args;
	}

	/**
	 * Calculate the HTML of the title based on the widget settings
	 *
	 * @param  string $before_title The sidebar configured HTML that should come
	 *                              before the title itself.
	 * @param  string $after_title The sidebar configured HTML that should come
	 *                              after the title itself.
	 * @param  array  $instance Array which contains the various settings.
	 * @return string The HTML for the title area
	 *
	 * @since 4.6
	 */
	public function titleHTML( $before_title, $after_title, $instance ) {
		$ret = '';

		if( in_array( $instance['title_level'], array( 'H1','H2', 'H3', 'H6', 'H5', 'H6') ) ) {
			$before_title = '';
			$after_title  = '';
		}

		// If no title, use the name of the category.
		if ( ! isset( $instance['title'] ) || ! $instance['title'] ) {
			$instance['title'] = '';
			if ( 0 !== (int) $instance['cat'] ) {
				$category_info = get_category( $instance['cat'] );
				if ( $category_info && ! is_wp_error( $category_info ) ) {
					$instance['title'] = $category_info->name;
				} else {
					$instance['cat'] = 0; // For further processing treat it like "all categories".
					$instance['title'] = __( 'Category Posts', 'category-posts' );
				}
			} else {
				$instance['title'] = __( 'Category Posts', 'category-posts' );
			}
		}

		if ( ! ( isset( $instance['hide_title'] ) && $instance['hide_title'] ) ) {
			$ret = $before_title;
			if ( isset( $instance['is_shortcode'] ) ) {
				$title = esc_html( $instance['title'] );
			} else {
				$title = apply_filters( 'widget_title', $instance['title'], $instance, WIDGET_BASE_ID );
			}

			if ( isset( $instance['title_link'] ) && $instance['title_link'] ) {
				if ( 0 !== (int) $instance['cat'] ) {
					$ret .= '<a href="' . get_category_link( $instance['cat'] ) . '">' . $title . '</a>';
				} elseif ( isset( $instance['title_link_url'] ) && $instance['title_link_url'] ) {
					$ret .= '<a href="' . esc_url( $instance['title_link_url'] ) . '">' . $title . '</a>';
				} else {
					$ret .= '<a href="' . esc_url( $this->blog_page_url() ) . '">' . $title . '</a>';
				}
			} else {
				$ret .= $title;
			}

			$ret .= $after_title;
		}

		$ret = $this->add_heading_level( $instance, $ret, 'title_level' );

		return $ret;
	}

	/**
	 * Add a heading level
	 *
	 * @return string The title string
	 *
	 * @since 5.0
	 */
	public function add_heading_level( $instance, $ret, $key ) {

		$class = ( isset( $instance[ 'disable_theme_styles' ] ) && $instance[ 'disable_theme_styles' ] ) ? '' : ' class="widget-title"';

		switch( $instance[ $key ] ) {
			case 'H1':
				$ret = '<h1' . $class . '>' . $ret . '</h1>';
			break;
			case 'H2':
				$ret = '<h2' . $class . '>' . $ret . '</h2>';
			break;
			case 'H3':
				$ret = '<h3' . $class . '>' . $ret . '</h3>';
			break;
			case 'H4':
				$ret = '<h4' . $class . '>' . $ret . '</h4>';
			break;
			case 'H5':
				$ret = '<h5' . $class . '>' . $ret . '</h5>';
			break;
			case 'H6':
				$ret = '<h6' . $class . '>' . $ret . '</h6>';
			break;
		}

		return $ret;
	}

	/**
	 * Get the URL of the blog page or home page if no explicit blog page is defined.
	 *
	 * @return string The URL of the blog page
	 *
	 * @since 4.8
	 */
	private function blog_page_url() {

		$blog_page = get_option( 'page_for_posts' );
		if ( $blog_page ) {
			$url = get_permalink( $blog_page );
		} else {
			$url = home_url();
		}

		return $url;
	}

	/**
	 * Calculate the HTML of the load more button based on the widget settings
	 *
	 * @param  array $instance Array which contains the various settings.
	 *
	 * @return string The HTML for the load more area
	 *
	 * @since 4.9
	 */
	public function loadMoreHTML( $instance ) {
		global $post_count;

		if ( ! $instance['enable_loadmore'] || $post_count <= $instance['num'] ) {
			return '';
		}

		$ret = '<div class="' . __NAMESPACE__ . '-loadmore">';
		$context = 0;
		if ( is_singular() ) {
			$context = get_the_ID();
		}

		wp_enqueue_script( 'jquery' );
		add_action( 'wp_footer', __NAMESPACE__ . '\embed_loadmore_scripts', 100 );

		// We rely on the widget number to be properly set.
		// but need a slight different handling for proper widgets.
		if ( is_int( $this->number ) ) {
			// it is a proper widget, add the prefix.
			$id = 'widget-' . $this->number;
		} else {
			$id = str_replace( WIDGET_BASE_ID . '-', '', $this->number );
		}

		// Placeholder
		$placeholder_text = $instance['loadmore_text'] !== '' ? $instance['loadmore_text'] : sprintf( esc_attr__( 'Load More (%s/%s)', 'category-posts' ), '%step%', '%all%');
		$pattern          = '/%step%/';
		$loadmore_text    = preg_replace( $pattern,  $instance['num'], $placeholder_text );
		$pattern          = '/%all%/';
		$loadmore_text    = preg_replace( $pattern,  $post_count, $loadmore_text );

		// Load more
		$number   = $instance['num'];
		$start    = $instance['offset'] + $number;
		$loading  = $instance['loading_text'] !== '' ? $instance['loading_text'] : esc_attr__( 'Loading...', 'category-posts' );
		$scrollTo = isset( $instance['loadmore_scrollTo'] ) && $instance['loadmore_scrollTo'];

		$ret .= '<button type="button" data-loading="' . esc_attr( $loading ) . '" data-id="' . esc_attr( $id ) .
					'" data-start="' . esc_attr( $start ) . '" data-context="' . esc_attr( $context ) . '" data-number="' . esc_attr( $number ) . 
					'" data-post-count="' . esc_attr( $post_count ) . '" data-placeholder="' . esc_attr( $placeholder_text ) . '" data-scrollto="' . esc_html( $scrollTo ) . '">' . 
					esc_html( $loadmore_text ) .
				'</button>';
		$ret .= '</div>';
		return $ret;
	}

	/**
	 * Calculate the HTML of the footer based on the widget settings
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @return string The HTML for the footer area
	 *
	 * @since 4.6
	 */
	public function footerHTML( $instance ) {

		$ret = '';
		$url = '';
		$text = '';

		if ( isset( $instance['footer_link'] ) ) {
			$url = $instance['footer_link'];
		}

		if ( isset( $instance['footer_link_text'] ) ) {
			$text = $instance['footer_link_text'];
		}

		// if url is set, but no text, just use the url as text.
		if ( empty( $text ) && ! empty( $url ) ) {
			$text = $url;
		}

		// if no url is set but just text, assume the url should be to the relevant archive page
		// category archive for categories filter and home page or blog page when "all categories"
		// is used.
		if ( ! empty( $text ) && empty( $url ) ) {
			if ( isset( $instance['cat'] ) && ( 0 !== (int) $instance['cat'] ) && ( null !== get_category( $instance['cat'] ) ) ) {
				$url = get_category_link( $instance['cat'] );
			} else {
				$url = $this->blog_page_url();
			}
		}

		if ( ! empty( $url ) ) {
			$ret .= '<a class="cat-post-footer-link" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
		}

		return $ret;
	}

	/**
	 * Current post item date string based on the format requested in the settings
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 4.8
	 */
	public function itemDate( $instance, $everything_is_link ) {
		global $post;
		$ret = '';

		if ( ! isset( $instance['preset_date_format'] ) ) {
			$preset_date_format = 'other';
		} else {
			$preset_date_format = $instance['preset_date_format'];
		}

		$attr = '';
		switch ( $preset_date_format ) {
			case 'sitedateandtime':
				$date = get_the_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
				break;
			case 'localsitedateandtime':
				$date = get_the_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) . ' GMT';
				$time = get_post_time( 'U', true );
				$attr = ' data-publishtime="' . $time . '" data-format="time"';
				add_action( 'wp_footer', __NAMESPACE__ . '\embed_date_scripts' );
				break;
			case 'sitedate':
				$date = get_the_time( get_option( 'date_format' ) );
				break;
			case 'localsitedate':
				$date = get_the_time( get_option( 'date_format' ) ) . ' GMT';
				$time = get_post_time( 'U', true );
				$attr = ' data-publishtime="' . $time . '" data-format="date"';
				add_action( 'wp_footer', __NAMESPACE__ . '\embed_date_scripts' );
				break;
			default:
				if ( isset( $instance['date_format'] ) && strlen( trim( $instance['date_format'] ) ) > 0 ) {
					$date_format = $instance['date_format'];
				} else {
					$date_format = 'j M Y';
				}
				$date = get_the_time( $date_format );
				break;
		}

		if ( isset( $instance['date_past_time'] ) && 0 < $instance['date_past_time'] && $post ) {
			$post_date            = get_the_time( "Y-m-d H:i:s", $post->ID );
			$current_date         = current_time( "Y-m-d H:i:s" );
			$past_days = date_diff(
								date_create( $post_date ),
								date_create( $current_date )
							)->days;
			if ( $past_days <= $instance['date_past_time'] ) {
				$date = human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) );
				$attr = ' data-publishtime="" data-format="sincepublished"';
			}
		}

		$post_date_class = ( isset( $instance[ 'disable_theme_styles' ] ) && $instance[ 'disable_theme_styles' ] ) ? "" : " post-date";
		$ret .= '<span class="cat-post-date' . $post_date_class . '"' . $attr . '>' . $date . '</span>';

		return $ret;
	}


	/**
	 * Calculate the HTML for showing the thumb of a post item.
	 * Expected to be called from a loop with globals properly set.
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $no_link  indicates whether the thumb should be wrapped in a link or a span.
	 * @return string The HTML for the thumb related to the post
	 *
	 * @since 4.6
	 */
	public function itemThumb( $instance, $no_link ) {
		$ret = '';

		if ( ( isset( $instance['default_thunmbnail'] ) && ( $instance['default_thunmbnail'] ) ) || has_post_thumbnail() ) {
			$class              = '';
			$disable_css        = isset( $instance['disable_css'] ) && $instance['disable_css'];

			if ( isset( $this->instance['thumb_hover'] ) && ! $disable_css ) {
				$class = 'class="cat-post-thumbnail cat-post-' . $instance['thumb_hover'] . '"';
			} else {
				$class = 'class="cat-post-thumbnail"';
			}

			$title_args = array( 'echo' => false );

			if ( $no_link ) {
				$ret .= '<span ' . $class . '>';
			} else {
				$ret .= '<a ' . $class . ' href="' . get_the_permalink() . '" title="' . the_title_attribute( $title_args ) . '">';
			}

			$ret .= $this->the_post_thumbnail( array( intval($this->instance['thumb_w']), intval($this->instance['thumb_h']) ) );
			

			if ( $no_link ) {
				$ret .= '</span>';
			} else {
				$ret .= '</a>';
			}
		}

		return $ret;
	}

	/**
	 * Current post item categories string
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 4.8
	 */
	public function itemCategories( $instance, $everything_is_link ) {

		$post_category_class = ( isset( $instance[ 'disable_theme_styles' ] ) && $instance[ 'disable_theme_styles' ] ) ? "" : " entry-categories post-categories";

		$ret = '<span class="cat-post-tax-category' . $post_category_class . '">';
		$cat_ids = wp_get_post_categories( get_the_ID(), array( 'number' => 0 ) );
		foreach ( $cat_ids as $cat_id ) {
			if ( $everything_is_link ) {
				$ret .= ' ' . get_cat_name( $cat_id );
			} else {
				$ret .= " <a href='" . get_category_link( $cat_id ) . "'>" . get_cat_name( $cat_id ) . '</a>';
			}
		}
		$ret .= '</span>';
		return $ret;
	}

	/**
	 * Current post item tags string
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 4.8
	 */
	public function itemTags( $instance, $everything_is_link ) {

		$post_tag_class = ( isset( $instance[ 'disable_theme_styles' ] ) && $instance[ 'disable_theme_styles' ] ) ? "" : " widget_tag_cloud tagcloud post-tags";

		$ret = '<span class="cat-post-tax-tag' . $post_tag_class . '">';
		$tag_ids = wp_get_post_tags( get_the_ID(), array( 'number' => 0 ) );
		foreach ( $tag_ids as $tag_id ) {
			if ( $everything_is_link ) {
				$ret .= ' ' . $tag_id->name;
			} else {
				$ret .= " <a href='" . get_tag_link( $tag_id->term_id ) . "'>" . $tag_id->name . '</a>';
			}
		}
		$ret .= '</span>';
		return $ret;
	}

	/**
	 * Current post item comment number string
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 4.8
	 */
	public function itemCommentNum( $instance, $everything_is_link ) {
		global $post;

		$ret = '<span class="cat-post-comment-num comment-meta">';

		if ( $everything_is_link ) {
			$ret .= '(' . \get_comments_number() . ')';
		} else {
			$link = sprintf(
				'<a href="%1$s" title="%2$s">(%3$d)</a>',
				esc_url( get_comments_link( $post->ID ) ),
				esc_attr( sprintf( __( '(%d) comments to this post' ), get_comments_number() ) ),
				get_comments_number()
			);
			$ret .= $link;
		}

		$ret .= '</span>';
		return $ret;
	}

	/**
	 * Current post item author string
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 4.8
	 */
	public function itemAuthor( $instance, $everything_is_link ) {

		$post_author_class = ( isset( $instance[ 'disable_theme_styles' ] ) && $instance[ 'disable_theme_styles' ] ) ? "" : " post-author";

		$ret = '<span class="cat-post-author' . $post_author_class . '">';

		if ( $everything_is_link ) {
			$ret .= get_the_author();
		} else {
			$link = get_the_author_posts_link();
			$ret .= $link;
		}
		$ret .= '</span>';
		return $ret;
	}

	/**
	 * Current post item excerpt string
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 4.8
	 */
	public function itemExcerpt( $instance, $everything_is_link ) {
		global $post;

		// use the_excerpt filter to get the "normal" excerpt of the post
		// then apply our filter to let users customize excerpts in their own way.
		if ( isset( $instance['excerpt_length'] ) && ( $instance['excerpt_length'] > 0 ) ) {
			$length = (int) $instance['excerpt_length'];
		} else {
			$length = 999; // Use the wordpress default.
		}

		if ( ! isset( $instance['excerpt_filters'] ) || $instance['excerpt_filters'] ) { // pre 4.7 widgets has filters on.
			$excerpt = apply_filters( 'the_excerpt', \get_the_excerpt() );
		} else { // if filters off replicate functionality of core generating excerpt.
			$more_text = '[&hellip;]';
			if ( isset( $instance['excerpt_more_text'] ) && $instance['excerpt_more_text'] ) {
				$more_text = ltrim( $instance['excerpt_more_text'] );
			}

			if ( $everything_is_link ) {
				$excerpt_more_text = ' <span class="cat-post-excerpt-more">' . $more_text . '</span>';
			} else {
				$excerpt_more_text = ' <a class="cat-post-excerpt-more" href="' . get_permalink() . '" title="' . sprintf( __( 'Continue reading %s' ), get_the_title() ) . '">' . $more_text . '</a>';
			}
			if ( '' === $post->post_excerpt ) {
				$text = get_the_content( '' );
				$text = strip_shortcodes( $text );
				$excerpt = \wp_trim_words( $text, $length, $excerpt_more_text );
				// adjust html output same way as for the normal excerpt,
				// just force all functions depending on the_excerpt hook.
				$excerpt = shortcode_unautop( wpautop( convert_chars( convert_smilies( wptexturize( $excerpt ) ) ) ) );
			} else {
				$text = $post->post_excerpt;
				$excerpt = \wp_trim_words( $text, $length, $excerpt_more_text );
				$excerpt = shortcode_unautop( wpautop( convert_chars( convert_smilies( wptexturize( $excerpt ) ) ) ) );
			}
		}
		$excerpt = str_replace('<p>', '<p class="cpwp-excerpt-text">', $excerpt);
		$ret = apply_filters( 'cpw_excerpt', $excerpt, $this );
		return $ret;
	}

	/**
	 * Current post item More Link string
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 5.0
	 */
	public function itemMoreLink( $instance, $everything_is_link ) {
		global $post;

		$more_text = '[&hellip;]';

		if ( isset( $instance['excerpt_more_text'] ) && '' !== $instance['excerpt_more_text'] ) {
			$more_text = sanitize_text_field( $instance['excerpt_more_text'] );
		}

		$more_link_class = ( isset( $instance[ 'disable_theme_styles' ] ) && $instance[ 'disable_theme_styles' ] ) ? "" : " more-link";

		if ( $everything_is_link ) {
			$ret = ' <span class="cat-post-excerpt-more' . $more_link_class . '">' . $more_text . '</span>';
		} else {
			$ret = ' <a class="cat-post-excerpt-more' . $more_link_class . '" href="' . get_permalink() . '">' . $more_text . '</a>';
		}

		if ( isset( $instance['excerpt_more_text'] ) && '' === $instance['excerpt_more_text'] ) {
			$ret = '' !== apply_filters( 'excerpt_more', '' ) ? apply_filters( 'excerpt_more', '' ) : $ret;
		}
		return $ret;
	}

	/**
	 * Current post item title string
	 *
	 * @param  array $instance Array which contains the various settings.
	 * @param  bool  $everything_is_link Indicates whether the return string should avoid links.
	 *
	 * @since 4.8
	 */
	public function itemTitle( $instance, $everything_is_link ) {

		$ret = '';

		if ( $everything_is_link ) {
			$ret .= '<span class="cat-post-title">' . get_the_title() . '</span>';
		} else {
			$ret .= '<a class="cat-post-title"';
			$ret .= ' href="' . get_the_permalink() . '" rel="bookmark">' . get_the_title();
			$ret .= '</a>';
		}

		$ret = $this->add_heading_level( $instance, $ret, 'item_title_level' );

		return $ret;
	}

	/**
	 * Calculate the HTML for a post item based on the widget settings and post.
	 * Expected to be called in an active loop with all the globals set.
	 *
	 * @param  array        $instance Array which contains the various settings.
	 * @param  null|integer $current_post_id If on singular page specifies the id of
	 *                      the post, otherwise null.
	 * @return string The HTML for item related to the post
	 *
	 * @since 4.6
	 */
	public function itemHTML( $instance, $current_post_id ) {
		global $post;

		$everything_is_link = isset( $instance['everything_is_link'] ) && $instance['everything_is_link'];
		$no_wrap = isset( $instance['text_do_not_wrap_thumb'] ) && $instance['text_do_not_wrap_thumb'];

		$template = '';
		if ( isset( $instance['template'] ) ) {
			$template = $instance['template'];
		} else {
			$template = convert_settings_to_template( $instance );
		}
		$ret = '<li ';

		// Current post.
		if ( $current_post_id === $post->ID ) {
			$ret .= "class='cat-post-item cat-post-current'";
		} else {
			$ret .= "class='cat-post-item'";
		}
		$ret .= '>'; // close the li opening tag.

		if ( $everything_is_link ) {
			$ret .= '<a class="cat-post-everything-is-link" href="' . get_the_permalink() . '" title="">';
		}

		// Post details (Template).
		$widget = $this;
		$template_res = preg_replace_callback(
			get_template_regex(),
			function ( $matches ) use ( $widget, $instance, $everything_is_link ) {
				switch ( $matches[0] ) {
					case '%title%':
						return $widget->itemTitle( $instance, $everything_is_link );
					case '%author%':
						return $widget->itemAuthor( $instance, $everything_is_link );
					case '%commentnum%':
						return $widget->itemCommentNum( $instance, $everything_is_link );
					case '%date%':
						return $widget->itemDate( $instance, $everything_is_link );
					case '%thumb%':
						return $widget->itemThumb( $instance, $everything_is_link );
					case '%post_tag%':
						return $widget->itemTags( $instance, $everything_is_link );
					case '%category%':
						return $widget->itemCategories( $instance, $everything_is_link );
					case '%excerpt%':
						return $widget->itemExcerpt( $instance, $everything_is_link );
					case '%more-link%':
						return $widget->itemMoreLink( $instance, $everything_is_link );
					default:
						return $matches[0];
				}
			},
			$template
		);

		// Replace empty line with closing and opening DIV.
		$template_res = trim( $template_res );

		$template_res = str_replace( "\n\r", '</div><div>', $template_res ); // in widget areas.
		$template_res = str_replace( "\n\n", '</div><div>', $template_res ); // as shortcode.
		$template_res = '<div>' . $template_res . '</div>';

		// replace new lines with spaces.
		$template_res = str_replace( "\n\r", ' ', $template_res ); // in widget areas.
		$template_res = str_replace( "\n\n", ' ', $template_res ); // as shortcode.

		$ret .= $template_res;

		if ( $everything_is_link ) {
			$ret .= '</a>';
		}

		$ret .= '</li>'; 
		return wp_kses_post($ret);
	}

	/**
	 * Filter to set the number of words in an excerpt
	 *
	 * @param  int $length The number of words as configured by wordpress core or set by previous filters.
	 * @return int The number of words configured for the widget,
	 *             or the $length parameter if it is not configured or garbage value.
	 *
	 * @since 4.6
	 */
	public function excerpt_length_filter( $length ) {
		if ( isset( $this->instance['excerpt_length'] ) && $this->instance['excerpt_length'] > 0 ) {
			$length = $this->instance['excerpt_length'];
		}
		return $length;
	}

	/**
	 * Set the proper excerpt filters based on the settings
	 *
	 * @param  array $instance widget settings.
	 * @return void
	 *
	 * @since 4.6
	 */
	public function setExcerpFilters( $instance ) {

		if ( isset( $instance['excerpt'] ) && $instance['excerpt'] ) {

			// Excerpt length filter.
			if ( isset( $instance['excerpt_length'] ) && ( (int) $instance['excerpt_length'] ) > 0 ) {
				add_filter( 'excerpt_length', array( $this, 'excerpt_length_filter' ) );
			}

			if ( isset( $instance['excerpt_more_text'] ) && ( '' !== ltrim( $instance['excerpt_more_text'] ) ) ) {
				add_filter( 'excerpt_more', array( $this, 'excerpt_more_filter' ) );
			}

			add_filter( 'the_excerpt', array( $this, 'apply_the_excerpt' ) );
		}
	}

	/**
	 * Remove the excerpt filter
	 *
	 * @param  array $instance widget settings.
	 * @return void
	 *
	 * @since 4.6
	 */
	public function removeExcerpFilters( $instance ) {
		remove_filter( 'excerpt_length', array( $this, 'excerpt_length_filter' ) );
		remove_filter( 'excerpt_more', array( $this, 'excerpt_more_filter' ) );
		add_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
		remove_filter( 'the_excerpt', array( $this, 'apply_the_excerpt' ) );
	}

	/**
	 * The main widget display controller
	 *
	 * Called by the sidebar processing core logic to display the widget.
	 *
	 * @param array $args An array containing the "environment" setting for the widget,
	 *                     namely, the enclosing tags for the widget and its title.
	 * @param array $instance The settings associate with the widget.
	 *
	 * @since 4.1
	 */
	public function widget( $args, $instance ) {
		global $before_title, $after_title;

		$instance = upgrade_settings( $instance );

		extract( $args );

		$this->instance = $instance;

		$current_post_id = '';
		if ( is_singular() ) {
			$current_post_id = get_the_ID();
		}

		$items = $this->get_elements_HTML( $instance, $current_post_id, 0, 0 );

		if ( ( 'nothing' === $instance['no_match_handling'] ) || ! empty( $items ) ) {
			echo $before_widget; // Xss ok. This is how widget actually expected to behave.

			echo $this->titleHTML( $before_title, $after_title, $instance );

			$thumb = isset( $this->instance['template'] ) && preg_match( '/%thumb%/', $this->instance['template'] );

			if ( ! ( isset( $instance['is_shortcode'] ) && $instance['is_shortcode'] ) ) { // the internal id is needed only for widgets.
				echo '<ul id="' . esc_attr( WIDGET_BASE_ID ) . '-' . esc_attr( $this->number ) . '-internal" class="' . esc_attr( WIDGET_BASE_ID ) . '-internal' . "\">\n";
			} else {
				echo '<ul>';
			}

			// image crop browser fallback and workaround, no polyfill.
			if ( $thumb ) {
				if ( apply_filters( 'cpw_enqueue_resources', false ) ) {
					frontend_script();
				} else {
					wp_enqueue_script( 'jquery' );
					add_action( 'wp_footer', __NAMESPACE__ . '\embed_front_end_scripts', 100 );
				}
			}

			// set widget filters.
			if ( ! isset( $instance['excerpt_filters'] ) || $instance['excerpt_filters'] ) { // pre 4.7 widgets has filters on.
				$this->setExcerpFilters( $instance );
			}

			foreach ( $items as $item ) {
				echo $item;
			}
			echo "</ul>\n";

			// Load more only if we think we have more items.
			if ( count( $items ) === (int) $instance['num'] ) {
				echo $this->loadMoreHTML( $instance );
			}

			echo $this->footerHTML( $instance );
			echo $after_widget; // Xss ok. This is how widget actually expected to behave.

			// remove widget filters.
			if ( ! isset( $instance['excerpt_filters'] ) || $instance['excerpt_filters'] ) { // pre 4.7 widgets has filters on.
				$this->removeExcerpFilters( $instance );
			}

			wp_reset_postdata();

			$number = $this->number;
			// a temporary hack to handle difference in the number in a true widget
			// and the number format expected at the rest of the places.
			if ( is_numeric( $number ) ) {
				$number = WIDGET_BASE_ID . '-' . $number;
			}
			if ( ! ( isset( $instance['is_shortcode'] ) && $instance['is_shortcode'] ) ) { // the internal id is needed only for widgets.
				$number .= '-internal';
			}

			// enque relevant scripts and parameters to ensure correct image dimentions.
			if ( isset( $instance['template'] ) && preg_match( '/%thumb%|%excerpt%/', $instance['template'] ) ) {
				wp_enqueue_script( 'jquery' ); // just in case the theme or other plugins didn't enqueue it.
				add_action(
					'wp_footer',
					function () use ( $number, $instance ) {
						__NAMESPACE__ . '\\' . equal_cover_content_height( $number, $instance );
					},
					100
				);
			}
		} elseif ( 'text' === $instance['no_match_handling'] ) {
			echo $before_widget; // Xss ok. This is how widget actually expected to behave.
			echo $this->titleHTML( $before_title, $after_title, $instance );
			echo esc_html( $instance['no_match_text'] );
			echo $this->footerHTML( $instance );
			echo $after_widget; // Xss ok. This is how widget actually expected to behave.
		}
	}

	/**
	 * Get an array of HTML pre item, for item starting from a specific position.
	 *
	 * @since 4.9
	 *
	 * @param array  $instance    An array containing the settings of the widget.
	 * @param string $singular_id The ID of the post in which the widget is rendered,
	 *                            an empty string indicates the rendering context
	 *                            is not singular.
	 * @param int    $start       The start element (0 based).
	 * @param int    $number      The maximal number of elements to return. A value of 0
	 *                            Indicates to use the widget settings for that.
	 *
	 * @return string[] Array of HTML per element with the $start element first
	 *                  $start+1 next etc. An empty array is returned if there
	 *                  are no applicable items.
	 */
	public function get_elements_HTML( $instance, $singular_id, $start, $number ) {
		global $post_count;

		$ret = array();

		if ( 0 === count( $instance ) ) {
			$instance = default_settings();
		}

		$this->instance = $instance;

		if ( $start > 0 ) {
			$instance['offset'] = $start;
		}
		$number = (int) $number; // sanitize number with the side effect of non
								// numbers are converted to zero.
		if ( 0 < $number ) {
			$instance['num'] = $number;
		}
		$args = $this->queryArgs( $instance );
		$cat_posts = new \WP_Query( $args );

		if( isset( $instance['enable_loadmore'] ) && $instance['enable_loadmore'] ) {
			$args['showposts'] = 0;
			$args['nopaging'] = true;
			$post_count = ( new \WP_Query( $args ) )->post_count; // May there is a better workflow to count the loadable items
		}

		$current_post_id = null;
		if ( '' !== $singular_id ) {
			$current_post_id = (int) $singular_id;
		}

		$postCount = 0;

		while ( $cat_posts->have_posts() ) {
			$postCount++;

			// If sticky posts are added, break anyway after the set number of posts.
			if ( isset( $instance['sticky'] ) && $instance['sticky'] && $postCount > $instance['num'] ) {
				break;
			}

			$cat_posts->the_post();
			$ret[] = $this->itemHTML( $instance, $current_post_id );
		}

		wp_reset_postdata();

		return $ret;
	}

	/**
	 * Update the options.
	 *
	 * @param  array $new_instance The new settings of the widget.
	 * @param  array $old_instance The current settings of the widget.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {

		$new_instance['title'] = sanitize_text_field( $new_instance['title'] );  // sanitize the title like core widgets do.
		if ( ! isset( $new_instance['excerpt_filters'] ) ) {
			$new_instance['excerpt_filters'] = '';
		}
		if ( current_user_can( 'unfiltered_html' ) ) {
			$instance['text'] = $new_instance['template'];
		} else {
			$instance['text'] = wp_kses_post( $new_instance['template'] );
		}

		// Set the version of the DB structure.
		$new_instance['ver'] = VERSION;
		return $new_instance;
	}

	/**
	 * Output the title panel of the widget configuration form.
	 *
	 * @param  array $instance The widget's settings.
	 * @return void
	 *
	 * @since 4.6
	 */
	public function formTitlePanel( $instance ) {
		$cat = (int) $instance['cat'];

		$hide_title = false;
		if ( isset( $instance['hide_title'] ) && $instance['hide_title'] ) {
			$hide_title = true;
		}
?>
	<h4 data-panel="title"><?php esc_html_e( 'Title', 'category-posts' ); ?></h4>
	<div class="cpwp_ident">
		<?php echo $this->get_checkbox_block_html( $instance, 'hide_title', esc_html__( 'Hide title', 'category-posts' ), true ); ?>
		<div class="categoryposts-data-panel-title-settings" <?php echo ( $hide_title ) ? 'style="display:none"' : ''; ?>>
			<?php echo $this->get_text_input_block_html( $instance, 'title', esc_html__( 'Title', 'category-posts' ), '', true ); ?>
			<?php echo $this->get_checkbox_block_html( $instance, 'title_link', esc_html__( 'Make widget title link', 'category-posts' ), 0 !== $cat ); ?>
			<?php echo $this->get_text_input_block_html( $instance, 'title_link_url', esc_html__( 'Title link URL', 'category-posts' ), '', 0 === $cat ); ?>
			<?php echo $this->get_radio_buttons_block_html( $instance, 'title_level', array( 'Initial', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ), esc_html__( 'Heading Level', 'category-posts' ) . ":" .
				' <a href="#" class="dashicons toggle-title-level-help dashicons-paperclip"><span class="screen-reader-text">' . 
				esc_html__( 'Show title level help', 'category-posts' ) . '</span></a>', true ); ?>
			<div class="cat-post-title-level-help" style="display:none;">
				<p><?php echo __( 'Also, try \'Disable Theme\'s styles\' on General tab to avoid rendering commonly used CSS classes such here widget-title, which often used in Themes to write their CSS selectors and may affect the design.', 'category-posts' ); ?>
				</p>
			</div>
		</div>
	</div>
<?php
	}

	/**
	 * Output the filter panel of the widget configuration form.
	 *
	 * @param  array $instance The parameters configured for the widget.
	 * @return void
	 *
	 * @since 4.6
	 */
	public function formFilterPanel( $instance ) {
		$cat = (int) $instance['cat'];
?>
	<h4 data-panel="filter"><?php esc_html_e( 'Filter', 'category-posts' ); ?></h4>
	<div>
		<p>
			<label>
				<?php esc_html_e( 'Category', 'category-posts' ); ?>:
				<?php
					wp_dropdown_categories(
						array(
							'show_option_all' => __( 'All categories', 'category-posts' ),
							'hide_empty'      => 0,
							'name'            => $this->get_field_name( 'cat' ),
							'selected'        => $instance['cat'],
							'class'           => 'categoryposts-data-panel-filter-cat',
						)
					);
				?>
			</label>
		</p>
		<?php
			echo $this->get_checkbox_block_html( $instance, 'no_cat_childs', esc_html__( 'Exclude child categories', 'category-posts' ), ! empty( $instance['cat'] ) );
			echo $this->get_select_block_html(
				$instance,
				'status',
				esc_html__( 'Status', 'category-posts' ), array(
					'default'                => esc_html__( 'WordPress Default', 'category-posts' ),
					'publish'                => esc_html__( 'Published', 'category-posts' ),
					'future'                 => esc_html__( 'Scheduled', 'category-posts' ),
					'private'                => esc_html__( 'Private', 'category-posts' ),
					'publish,future'         => esc_html__( 'Published or Scheduled', 'category-posts' ),
					'private,publish'        => esc_html__( 'Published or Private', 'category-posts' ),
					'private,future'         => esc_html__( 'Private or Scheduled', 'category-posts' ),
					'private,publish,future' => esc_html__( 'Published, Private or Scheduled', 'category-posts' ),
				),
				'default',
				true
			);
			echo $this->get_number_input_block_html( $instance, 'num', esc_html__( 'Number of posts to show', 'category-posts' ), 1, '', '', true );
			echo $this->get_number_input_block_html( $instance, 'offset', esc_html__( 'Start with post', 'category-posts' ), 1, '', '', true );
			echo $this->get_select_block_html(
				$instance,
				'date_range',
				esc_html__( 'Date Range', 'category-posts' ),
				array(
					'off'           => esc_html__( 'Off', 'category-posts' ),
					'days_ago'      => esc_html__( 'Days ago', 'category-posts' ),
					'between_dates' => esc_html__( 'Between dates', 'category-posts' ),
				),
				'off',
				true
			);
		?>
			<div class="cpwp_ident categoryPosts-date-range" style="display:<?php echo 'off' === $instance['date_range'] ? 'none' : 'block'; ?>">
			<?php
			echo $this->get_number_input_block_html( $instance, 'days_ago', esc_html__( 'Up to', 'category-posts' ), 1, '', '', 'days_ago' === $instance['date_range'] );
			echo $this->get_date_input_block_html( $instance, 'start_date', esc_html__( 'After', 'category-posts' ), 'between_dates' === $instance['date_range'] );
			echo $this->get_date_input_block_html( $instance, 'end_date', esc_html__( 'Before', 'category-posts' ), 'between_dates' === $instance['date_range'] );
			?>
			</div>
			<?php
			echo $this->get_select_block_html(
				$instance,
				'sort_by',
				esc_html__( 'Sort by', 'category-posts' ),
				array(
					'date'          => esc_html__( 'Date', 'category-posts' ),
					'title'         => esc_html__( 'Title', 'category-posts' ),
					'comment_count' => esc_html__( 'Number of comments', 'category-posts' ),
					'rand'          => esc_html__( 'Random', 'category-posts' ),
				),
				'date',
				true
			);
			echo $this->get_checkbox_block_html( $instance, 'asc_sort_order', esc_html__( 'Reverse sort order (ascending)', 'category-posts' ), true );
			echo $this->get_checkbox_block_html( $instance, 'exclude_current_post', esc_html__( 'Exclude current post', 'category-posts' ), true );
			echo $this->get_checkbox_block_html( $instance, 'hideNoThumb', esc_html__( 'Exclude posts which have no thumbnail', 'category-posts' ), true );
			echo $this->get_checkbox_block_html( $instance, 'sticky', esc_html__( 'Start with sticky posts', 'category-posts' ), true );
			?>
		</div>
		<?php
	}

	/**
	 * Generate the wrapper P around a form input element
	 *
	 * @since 4.8
	 * @param string $html    The HTML to wrap.
	 * @param string $key     The key to use as the prefix to the class.
	 * @param bool   $visible Indicates if the element should be visible when rendered.
	 *
	 * @return string HTML with P element contaning the html being passed with class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_wrap_block_html( $html, $key, $visible ) {

		$cl = ' class="' . __NAMESPACE__ . '-' . esc_attr( $key ) . '"';

		$style = '';
		if ( ! $visible ) {
			$style = ' style="display:none"';
		}
		$ret = '<p' . $cl . $style . ">\n" . $html . "</p>\n";

		return $ret;
	}

	/**
	 * Generate a form P element containing a select element
	 *
	 * @since 4.8
	 * @param array  $instance  The instance.
	 * @param string $key       The key in the instance array.
	 * @param string $label     The label to display and associate with the input.
	 * @param array  $list      An array of pairs value (index) => label to be used for the options.
	 *                          The labels are expected to be html escaped.
	 * @param int    $default   The value to use if the key is not set in the instance.
	 * @param bool   $visible   Indicates if the element should be visible when rendered.
	 *
	 * @return string HTML a P element contaning the select, its label, class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_select_block_html( $instance, $key, $label, $list, $default, $visible ) {
		$value = $instance[ $key ];

		if ( ! array_key_exists( $value, $list ) ) {
			$value = $default;
		}

		if ( '' !== $label ) {
			$label .= ':';
		}

		$ret = '<label for="' . $this->get_field_id( $key ) . "\">\n" .
					$label .
				"</label>\n" .
				'<select id="' . $this->get_field_id( $key ) . '" name="' . $this->get_field_name( $key ) . '"  autocomplete="off">' . "\n";
		foreach ( $list as $v => $l ) {
			$ret .= '<option value="' . esc_attr( $v ) . '" ' . selected( $v, $value, false ) . '>' . $l . "</option>\n";
		}
		$ret .= "</select>\n";

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * Generate a form P element containing a textarea input
	 *
	 * @since 4.8
	 * @param array  $instance      The instance.
	 * @param string $key           The key in the instance array.
	 * @param string $label         The label to display and associate with the input (should be html escaped).
	 * @param string $placeholder   The placeholder to use in the input (should be attribute escaped).
	 * @param bool   $visible       Indicates if the element should be visible when rendered.
	 * @param int    $num_rows      Number of rows.
	 *
	 * @return string HTML a P element containing the input, its label, class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_textarea_html( $instance, $key, $label, $placeholder, $visible, $num_rows ) {

		$value = $instance[ $key ];

		$ret = '<label for="' . esc_attr( $this->get_field_id( $key ) ) . '">' . $label .
					'<textarea rows="' . esc_attr( $num_rows ) . '" placeholder="' . $placeholder . '" id="' . esc_attr( $this->get_field_id( $key ) ) . '" name="' . esc_attr( $this->get_field_name( $key ) ) . '" autocomplete="off">' . esc_textarea( $value ) . '</textarea>' .
				'</label>';

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * Generate a form P element containing a text input
	 *
	 * @since 4.8
	 * @param array  $instance  The instance.
	 * @param string $key       The key in the instance array.
	 * @param string $label     The label to display and associate with the input.
	 *                          Should be html escaped.
	 * @param string $placeholder The placeholder to use in the input. should be attribute escaped.
	 * @param bool   $visible   Indicates if the element should be visible when rendered.
	 *
	 * @return string HTML a P element contaning the input, its label, class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_text_input_block_html( $instance, $key, $label, $placeholder, $visible ) {

		$value = $instance[ $key ];

		$ret = '<label for="' . $this->get_field_id( $key ) . "\">\n" .
					$label . ":\n" .
					'<input placeholder="' . $placeholder . '" id="' . $this->get_field_id( $key ) . '" name="' . $this->get_field_name( $key ) . '" type="text" value="' . esc_attr( $value ) . '" autocomplete="off"/>' . "\n" .
				"</label>\n";

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * Generate a form P element containing a range input
	 *
	 * @since 4.8
	 * @param array  $instance  The instance.
	 * @param string $key       The key in the instance array.
	 * @param string $label     The label to display and associate with the input.
	 *                          expected to be escaped.
	 * @param int    $min       The minimum value allowed to be input.
	 * @param int    $max       The maximum value allowed to be input.
	 * @param string $value     The start value.
	 * @param string $step      The range of each step.
	 * @param bool   $visible   Indicates if the element should be visible when rendered.
	 *
	 * @return string HTML a P element contaning the input, its label, class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_range_input_block_html( $instance, $key, $label, $min, $max, $value, $step, $visible ) {

		$value = $instance[ $key ];

		$minMaxStep = '';
		if ( '' !== $min ) {
			$minMaxStep .= ' min="' . $min . '"';
		}
		if ( '' !== $max ) {
			$minMaxStep .= ' max="' . $max . '"';
		}
		if ( '' !== $step ) {
			$minMaxStep .= ' step="' . $step . '"';
		}

		$ret = '<label for="' . $this->get_field_id( $key ) . "\">\n" .
					esc_html( $label ) . ' <span>' . $value . "%</span>\n" .
					'<input id="' . esc_attr( $this->get_field_id( $key ) ) . '" value="' . $value . '" name="' . esc_attr( $this->get_field_name( $key ) ) . '" class="' . esc_attr( $key ) . '" type="range"' . $minMaxStep . ' />' . "\n" .
				"</label>\n";

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * Generate a form P element containing a number input
	 *
	 * @since 4.8
	 * @param array  $instance  The instance.
	 * @param string $key       The key in the instance array.
	 * @param string $label     The label to display and associate with the input.
	 *                          expected to be escaped.
	 * @param int    $min       The minimum value allowed to be input.
	 * @param int    $max       The maximum value allowed to be input.
	 * @param string $placeholder The placeholder string to be used. expected to be escaped.
	 * @param bool   $visible   Indicates if the element should be visible when rendered.
	 *
	 * @return string HTML a P element contaning the input, its label, class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_number_input_block_html( $instance, $key, $label, $min, $max, $placeholder, $visible ) {

		$value = $instance[ $key ];

		$minmax = '';
		if ( '' !== $min ) {
			$minmax .= ' min="' . $min . '"';
		}
		if ( '' !== $max ) {
			$minmax .= ' max="' . $max . '"';
		}

		$ret = '<label for="' . $this->get_field_id( $key ) . "\">\n" .
					esc_html( $label ) . "\n" .
					'<input placeholder="' . $placeholder . '" id="' . esc_attr( $this->get_field_id( $key ) ) . '" name="' . esc_attr( $this->get_field_name( $key ) ) . '" class="' . esc_attr( $key ) . '" type="number"' . $minmax . ' value="' . esc_attr( $value ) . '" autocomplete="off" />' . "\n" .
				"</label>\n";

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * Generate a form P element containing a date input
	 *
	 * @since 4.9
	 * @param array  $instance  The instance.
	 * @param string $key       The key in the instance array.
	 * @param string $label     The label to display and associate with the input.
	 *                          expected to be escaped.
	 * @param bool   $visible   Indicates if the element should be visible when rendered.
	 *
	 * @return string HTML a P element containing the input, its label, class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_date_input_block_html( $instance, $key, $label, $visible ) {

		$value = $instance[ $key ];

		$ret = '<label for="' . $this->get_field_id( $key ) . "\">\n" .
					esc_html( $label ) . "\n" .
					'<input id="' . esc_attr( $this->get_field_id( $key ) ) . '" name="' . esc_attr( $this->get_field_name( $key ) ) . '" class="' . esc_attr( $key ) . '" type="date" value="' . esc_attr( $value ) . '" autocomplete="off" />' . "\n" .
				"</label>\n";

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * Generate a form P element containing a checkbox input
	 *
	 * @since 4.8
	 * @param array  $instance  The instance.
	 * @param string $key       The key in the instance array.
	 * @param string $label     The label to display and associate with the checkbox.
	 *                          should be escaped string.
	 * @param bool   $visible   Indicates if the element should be visible when rendered.
	 *
	 * @return string HTML a P element contaning the checkbox, its label, class based on the key
	 *                and style set to display:none if visibility is off.
	 */
	private function get_checkbox_block_html( $instance, $key, $label, $visible ) {

		if ( array_key_exists( $key, $instance ) ) {
			if ( $instance[ $key ] ) {
				$value = true;
			} else {
				$value = false;
			}
		}
		$ret = '<label class="checkbox" for="' . esc_attr( $this->get_field_id( $key ) ) . "\">\n" .
					'<input id="' . esc_attr( $this->get_field_id( $key ) ) . '" name="' . esc_attr( $this->get_field_name( $key ) ) . '" type="checkbox" ' . checked( $value, true, false ) . '/>' . "\n" .
					$label .
				"</label>\n";

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * Generate a form button element containing
	 *
	 * @since 4.9
	 * @param array  $instance  The instance.
	 * @param string $key       The key in the instance array.
	 * @param string $label     The label to display and associate with the button.
	 *                          should be escaped string.
	 *
	 * @return string HTML a button element and class based on the key.
	 */
	private function get_button_thumb_size_html( $instance, $key, $label ) {

		$datas = '';

		switch ( $key ) {
			case 'thumb':
				$datas = 'data-thumb-w="' . get_option( 'thumbnail_size_w' ) . '" data-thumb-h="' . get_option( 'thumbnail_size_h' ) . '"';
				break;
			case 'medium':
				$datas = 'data-thumb-w="' . get_option( 'medium_size_w' ) . '" data-thumb-h="' . get_option( 'medium_size_h' ) . '"';
				break;
			case 'large':
				$datas = 'data-thumb-w="' . get_option( 'large_size_w' ) . '" data-thumb-h="' . get_option( 'large_size_h' ) . '"';
				break;
		}
		$ret = '<button type="button" ' . $datas . ' class="' . $key . ' button">' . esc_html( $label ) . "</button>\n";

		return $ret;
	}

	/**
	* Generate a form element containing native styled radio buttons
	*
	* @since 5.0
	* @param array  $instance   The instance.
	* @param string $key        The key in the instance array.
	* @param string $label      The label to display and associate with the checkbox.
	*                           should be escaped string.
	* @param bool   $visible    Indicates if the element should be visible when rendered.
	*
	* @return string HTML a element contaning the radio buttons, it's label, class based on the key
	*                and style set to display:none if visibility is off.
	*/
	private function get_radio_buttons_block_html( $instance, $key, $values, $label, $visible ) {

		$ret = '<label for="' . esc_attr( $this->get_field_id( $key ) ) . "\">" . $label . "</label>\n";
		$ret .= '<span class="cpwp-right">';

		array_map ( function( $value ) use ( &$ret, $instance, $key ) {
			if ( $instance[ $key ] == $value ) {
				$checked = true;
			} else {
				$checked = false;
			}

			$ret .= '<input class="' . $value . ' button" id="' . esc_attr( $this->get_field_id( $key . $value ) ) . '" name="' . esc_attr( $this->get_field_name( $key ) ) . 
					'" value="' . $value . '" type="radio" ' . checked( $checked, true, false ) . '/>' . "\n";
		}, $values );

		$ret .= "</span>\n";

		return $this->get_wrap_block_html( $ret, $key, $visible );
	}

	/**
	 * The widget configuration form back end.
	 *
	 * @param  array $instance The parameters associated with the widget.
	 * @return void
	 */
	public function form( $instance ) {
		if ( 0 === count( $instance ) ) { // new widget, use defaults.
			$instance = default_settings();
		} else { // updated widgets come from =< 4.6 excerpt filter is on.
			if ( ! isset( $instance['excerpt_filters'] ) ) {
				$instance['excerpt_filters'] = 'on';
			}
		}

		$instance = upgrade_settings( $instance );

		$item_title_level                = $instance['item_title_level'];
		$hide_post_titles                = $instance['hide_post_titles'];
		$excerpt_more_text               = $instance['excerpt_more_text'];
		$excerpt_filters                 = $instance['excerpt_filters'];
		$date_format                     = $instance['date_format'];
		$disable_css                     = $instance['disable_css'];
		$disable_font_styles             = $instance['disable_font_styles'];
		$preset_date_format              = $instance['preset_date_format'];
		$thumb                           = ! empty( $instance['thumb'] );
		$thumb_w                         = $instance['thumb_w'];
		$thumb_fluid_width               = $instance['thumb_fluid_width'];
		$thumb_h                         = $instance['thumb_h'];
		$default_thunmbnail              = $instance['default_thunmbnail'];
		$text_do_not_wrap_thumb          = $instance['text_do_not_wrap_thumb'];
		?>

		<div class="category-widget-cont">
			<?php if ( ! class_exists( '\\termcategoryPostsPro\\Widget' ) ) { ?>
			<p><a target="_blank" href="https://tiptoppress.com/term-and-category-based-posts-widget/"><?php esc_html_e( 'Get the Pro version', 'category-posts' ); ?></a></p>
				<?php
			}
			$this->formTitlePanel( $instance );
			$this->formFilterPanel( $instance );
			?>
			<h4 data-panel="details"><?php esc_html_e( 'Post details', 'category-posts' ); ?></h4>
			<div class="cpwp-sub-panel">
				<?php
				$template = '';
				if ( ! isset( $instance['template'] ) ) {
					$template = convert_settings_to_template( $instance );
				} else {
					$template = $instance['template'];
				}
				?>
				<p><?php esc_html_e( 'Displayed parts', 'category-posts' ); ?></p>
				<div class="cpwp_ident">
					<?php
					$label = esc_html__( 'Template', 'category-posts' ) .
								' <a href="#" class="dashicons toggle-template-help dashicons-editor-help"><span class="screen-reader-text">' . 
								esc_html__( 'Show template help', 'category-posts' ) . '</span></a>';
					$class_placement = '';
					if ( is_customize_preview() ) {
						$class_placement = 'customizer';
					} else {
						$class_placement = 'admin-panel';
					}
					$label .= '<span class="cat-post-add_premade_templates ' . $class_placement . '">' .
								'<button type="button" class="button cpwp-open-placholder-dropdown-menu"> + ' . esc_html__( 'Add Placeholder', 'category-posts' ) . '</button>' .
									'<span class="cpwp-placeholder-dropdown-menu">' .
										'<i class="cpwp-close-placeholder-dropdown-menu dashicons dashicons-no-alt"></i>' .
										'<span data-value="NewLine">' . esc_html__( 'New line', 'category-posts' ) . '</span>' .
										'<span data-value="EmptyLine">' . esc_html__( 'Empty line', 'category-posts' ) . '</span>' .
										'<span data-value="title">' . esc_html__( '%title%', 'category-posts' ) . '</span>' .
										'<span data-value="thumb">' . esc_html__( '%thumb%', 'category-posts' ) . '</span>' .
										'<span data-value="date">' . esc_html__( '%date%', 'category-posts' ) . '</span>' .
										'<span data-value="excerpt">' . esc_html__( '%excerpt%', 'category-posts' ) . '</span>' .
										'<span data-value="more-link">' . esc_html__( '%more-link%', 'category-posts' ) . '</span>' .
										'<span data-value="author">' . esc_html__( '%author%', 'category-posts' ) . '</span>' .
										'<span data-value="commentnum">' . esc_html__( '%commentnum%', 'category-posts' ) . '</span>' .
										'<span data-value="post_tag">' . esc_html__( '%post_tag%', 'category-posts' ) . '</span>' .
										'<span data-value="category">' . esc_html__( '%category%', 'category-posts' ) . '</span>' .
									'</span>' .
								'</span>';
					?>
					<?php
					echo $this->get_textarea_html( $instance, 'template', $label, '', true, 8 );
					preg_match_all( get_template_regex(), $template, $matches );
					$tags = array();
					if ( ! empty( $matches[0] ) ) {
						$tags = array_flip( $matches[0] );
					}
					?>
					<div class="cat-post-template-help" style="display:none;">
						<p><?php echo sprintf( __( 'The following placeholders will be replaced with the relevant information. In addition you can use text, HTML and <a target="_blank" href="%s">Dashicons</a>.', 'category-posts' ),
							'https://developer.wordpress.org/resource/dashicons/'); ?>
						</p>
						<table>
							<tr>
								<th><?php esc_html_e( 'New line', 'category-posts' ); ?></th>
								<td><?php esc_html_e( 'Space', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Empty line', 'category-posts' ); ?></th>
								<td><?php esc_html_e( 'Next line is a paragraph', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%title%</th>
								<td><?php esc_html_e( 'Post title', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%thumb%</th>
								<td><?php esc_html_e( 'Post thumbnail possibly wrapped by text', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%date%</th>
								<td><?php esc_html_e( 'Post publish date', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%excerpt%</th>
								<td><?php esc_html_e( 'Post excerpt', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%more-link%</th>
								<td><?php esc_html_e( 'Read more text', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%author%</th>
								<td><?php esc_html_e( 'Post author', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%commentnum%</th>
								<td><?php esc_html_e( 'The number of comments to the post', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%post_tag%</th>
								<td><?php esc_html_e( 'Post tags', 'category-posts' ); ?></td>
							</tr>
							<tr>
								<th>%category%</th>
								<td><?php esc_html_e( 'Post categories', 'category-posts' ); ?></td>
							</tr>
						</table>
					</div>
					<div class="cat-post-premade_templates">
						<p><label><?php esc_html_e( 'Select premade Template', 'category-posts' ); ?></label></p>
						<select>
							<option value="title"><?php esc_html_e( 'Title', 'category-posts' ); ?></option>
							<option value="title_excerpt"><?php esc_html_e( 'Title, Excerpt, More Link', 'category-posts' ); ?></option>
							<option value="title_thumb"><?php esc_html_e( 'Title, Thumbnail', 'category-posts' ); ?></option>
							<option value="title_thum_excerpt"><?php esc_html_e( 'Title, Thumbnail, Excerpt, More Link', 'category-posts' ); ?></option>
							<option value="everything"><?php esc_html_e( 'All with icons', 'category-posts' ); ?></option>
						</select>
						<p><button type="button" class="button"><?php esc_html_e( 'Select this template', 'category-posts' ); ?></button></p>
						<?php
						echo $this->get_checkbox_block_html( $instance, 'everything_is_link', esc_html__( 'Everything is a link', 'category-posts' ), true );
						?>
					</div>
				</div>
				<?php // Title settings. ?>
				<div class="cpwp-sub-panel categoryposts-data-panel-title" style="display:<?php echo ( isset( $tags['%title%'] ) ) ? 'block' : 'none'; ?>">
					<p><?php esc_html_e( 'Title settings', 'category-posts' ); ?></p>
					<div class="cpwp_ident">
					<?php
						echo $this->get_number_input_block_html( $instance, 'item_title_lines', esc_html__( 'Lines (responsive):', 'category-posts' ), 0, '', '', true );
						echo $this->get_radio_buttons_block_html( $instance, 'item_title_level', array( 'Inline', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ), esc_html__( 'Heading Level:', 'category-posts' ), true );
					?>
					</div>
				</div>
				<?php // Excerpt settings. ?>
				<div class="cpwp-sub-panel categoryposts-data-panel-excerpt" style="display:<?php echo ( isset( $tags['%excerpt%'] ) ) ? 'block' : 'none'; ?>">
					<p><?php esc_html_e( 'Excerpt settings', 'category-posts' ); ?></p>
					<div class="cpwp_ident">
					<?php
					echo $this->get_number_input_block_html( $instance, 'excerpt_lines', esc_html__( 'Lines (responsive):', 'category-posts' ), 0, '', '', true );
					// Remove the UI since free 5.0
					// echo $this->get_number_input_block_html( $instance, 'excerpt_length', esc_html__( 'Length (words):', 'category-posts' ), 0, '', '', true );
					// Remove the UI since free 5.0
					// echo $this->get_text_input_block_html( $instance, 'excerpt_more_text', esc_html__( '\'More ...\' text:', 'category-posts' ), esc_attr__( '...', 'category-posts' ), true );
					?>
					</div>
				</div>
				<?php // More link settings. ?>
				<div class="cpwp-sub-panel categoryposts-data-panel-more-link" style="display:<?php echo ( isset( $tags['%more-link%'] ) ) ? 'block' : 'none'; ?>">
					<p><?php esc_html_e( 'More Link settings', 'category-posts' ); ?></p>
					<div class="cpwp_ident">
						<?php
						echo $this->get_text_input_block_html
							( 
								$instance, 'excerpt_more_text',
								esc_html__( '\'Read more\' text', 'category-posts' ) .
									' <a href="#" class="dashicons toggle-more-link-help dashicons-editor-help">' .
										'<span class="screen-reader-text">' . esc_html__( 'Show More Link help', 'category-posts' ) . '</span>' .
									'</a>', esc_attr__( '[&hellip;]', 'category-posts' ), 
								true 
							);
						?>
						<div class="cat-post-more-link-help" style="display:none;">
							<p><?php echo sprintf( __( 'Text in the \'more\' link. Can be text, HTML and <a target="_blank" href="%s">Dashicons</a>.', 'category-posts' ),
								'https://developer.wordpress.org/resource/dashicons/'); ?>
							</p>
						</div>
					</div>
				</div>
				<?php // Data settings. ?>
				<div class="cpwp-sub-panel categoryposts-data-panel-date" style="display:<?php echo ( isset( $tags['%date%'] ) ) ? 'block' : 'none'; ?>">
					<p><?php esc_html_e( 'Date format settings', 'category-posts' ); ?></p>
					<div class="cpwp_ident">
						<?php
						echo $this->get_select_block_html(
							$instance,
							'preset_date_format',
							esc_html__( 'Date format', 'category-posts' ),
							array(
								'sitedateandtime'      => esc_html__( 'Site date and time', 'category-posts' ),
								'sitedate'             => esc_html__( 'Site date', 'category-posts' ),
								'localsitedateandtime' => esc_html__( 'Reader\'s local date and time', 'category-posts' ),
								'localsitedate'        => esc_html__( 'Reader\'s local date', 'category-posts' ),
								'other'                => esc_html__( 'PHP style format', 'category-posts' ),
							),
							'sitedateandtime',
							true
						);
						echo $this->get_text_input_block_html( $instance, 'date_format', esc_html__( 'PHP Style Date format', 'category-posts' ), 'j M Y', 'other' === $preset_date_format );
						echo $this->get_number_input_block_html( $instance, 'date_past_time', esc_html__( 'Show past time up to x-days:', 'category-posts' ), 0, '', '' , true );
						?>
					</div>
				</div>
				<?php // Thumbnail settings. ?>
				<div class="cpwp-sub-panel categoryposts-data-panel-thumb" style="display:<?php echo ( isset( $tags['%thumb%'] ) ) ? 'block' : 'none'; ?>">
					<p><?php esc_html_e( 'Thumbnail settings', 'category-posts' ); ?></p>
				<div class="cpwp_ident">
					<p><?php esc_html_e( 'Dimensions (pixel)', 'category-posts' ); ?>
						<a href="#" class="dashicons toggle-image-dimensions-help dashicons-editor-help">
							<span class="screen-reader-text"><?php esc_html__( 'Show image dimension help', 'category-posts' ); ?></span>
						</a>
					</p>
						<?php
						echo $this->get_number_input_block_html( $instance, 'thumb_w', esc_html__( 'Width:', 'category-posts' ), 0, '', '', true );
						echo $this->get_range_input_block_html( $instance, 'thumb_fluid_width', esc_html__( 'Max-width:', 'category-posts' ), 2, 100, 100, 2, true );
						echo $this->get_number_input_block_html( $instance, 'thumb_h', esc_html__( 'Height:', 'category-posts' ), 0, '', '', true );
						?>
						<div class="cat-post-image-dimensions-help" style="display:none;">
							<p><?php esc_html_e( 'Set one dimensions to 0 the set dimension will be used with the original image ratio.', 'category-posts' ); ?></p>
							<p><?php esc_html_e( 'Set both dimensions to 0 the original image ratio will be used.', 'category-posts' ); ?></p>
							<p><?php esc_html_e( 'Max-width limits in relation of the total Post width.', 'category-posts' ); ?></p>
						</div>
						<div class="cat-post-thumb-change-size">
							<p>
								<label><?php esc_html_e( 'Change size', 'category-posts' ); ?>: </label>
								<span class="cpwp-right">
									<?php
									echo $this->get_button_thumb_size_html( $instance, 'smaller', esc_html__( '-', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'quarter', esc_html__( '1/4', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'half', esc_html__( '1/2', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'double', esc_html__( '2x', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'bigger', esc_html__( '+', 'category-posts' ) );
									?>
								</span>
							</p>
							<p>
								<label><?php esc_html_e( 'Ratio', 'category-posts' ); ?>: </label>
								<span class="cpwp-right">
									<?php
									echo $this->get_button_thumb_size_html( $instance, 'square', esc_html__( '1:1', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'standard', esc_html__( '4:3', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'wide', esc_html__( '16:9', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'switch', esc_html__( 'switch', 'category-posts' ) );
									?>
								</span>
							</p>
							<p>
								<label><?php esc_html_e( 'Image ratio', 'category-posts' ); ?>: </label>
								<span class="cpwp-right">
									<?php
									echo $this->get_button_thumb_size_html( $instance, 'width', esc_html__( 'Width', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'height', esc_html__( 'Height', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'both', esc_html__( 'Both', 'category-posts' ) );
									?>
								</span>
							</p>
							<p>
								<label><?php esc_html_e( 'Available', 'category-posts' ); ?>: </label>
								<span class="cpwp-right">
									<?php
									echo $this->get_button_thumb_size_html( $instance, 'thumb', esc_html__( 'Thumb', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'medium', esc_html__( 'Medium', 'category-posts' ) );
									echo $this->get_button_thumb_size_html( $instance, 'large', esc_html__( 'Large', 'category-posts' ) );
									?>
								</span>
							</p>
						</div>
						<?php
						echo $this->get_checkbox_block_html( $instance, 'text_do_not_wrap_thumb', esc_html__( 'Do not wrap thumbnail with overflowing text', 'category-posts' ), true );
						echo $this->get_select_block_html( $instance, 'thumb_hover', esc_html__( 'Animation on mouse hover', 'category-posts' ), array(
							'none'  => esc_html__( 'None', 'category-posts' ),
							'dark'  => esc_html__( 'Darker', 'category-posts' ),
							'white' => esc_html__( 'Brighter', 'category-posts' ),
							'scale' => esc_html__( 'Zoom in', 'category-posts' ),
							'blur'  => esc_html__( 'Blur', 'category-posts' ),
							'icon'  => esc_html__( 'Icon', 'category-posts' ),
						), 'none', true);
						echo $this->get_select_block_html(
							$instance,
							'show_post_format',
							esc_html__( 'Indicate post format and position', 'category-posts' ),
							array(
								'none'        => esc_html__( 'None', 'category-posts' ),
								'topleft'     => esc_html__( 'Top left', 'category-posts' ),
								'bottomleft'  => esc_html__( 'Bottom left', 'category-posts' ),
								'ceter'       => esc_html__( 'Center', 'category-posts' ),
								'topright'    => esc_html__( 'Top right', 'category-posts' ),
								'bottomright' => esc_html__( 'Bottom right', 'category-posts' ),
								'nocss'       => esc_html__( 'HTML without styling', 'category-posts' ),
							),
							'none',
							true
						);
						?>
						<p>
							<label style="display:block">
								<?php esc_html_e( 'Default thumbnail: ', 'category-posts' ); ?>
							</label>
							<input type="hidden" class="default_thumb_id" id="<?php echo esc_attr( $this->get_field_id( 'default_thunmbnail' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'default_thunmbnail' ) ); ?>" value="<?php echo esc_attr( $default_thunmbnail ); ?>"/>
							<span class="default_thumb_img">
								<?php
								if ( ! $default_thunmbnail ) {
									esc_html_e( 'None', 'category-posts' );
								} else {
									$img = wp_get_attachment_image_src( $default_thunmbnail );
									echo '<img width="60" height="60" src="' . esc_url( $img[0] ) . '" />';
								}
								?>
							</span>
						</p>
						<p>
							<button type="button" class="cwp_default_thumb_select button upload-button">
								<?php esc_html_e( 'Select image', 'category-posts' ); ?>
							</button>
							<button type="button" class="cwp_default_thumb_remove button upload-button" <?php echo ( ! $default_thunmbnail ) ? 'style="display:none"' : ''; ?> >
								<?php esc_html_e( 'No default', 'category-posts' ); ?>
							</button>
						</p>
					</div>
				</div>
			</div>
			<h4 data-panel="general"><?php esc_html_e( 'General', 'category-posts' ); ?></h4>
			<div class="cpwp-sub-panel">
				<p><?php esc_html_e( 'Inherited CSS', 'categorypostspro' ); ?></p>
				<div class="cpwp_ident">
					<?php echo $this->get_checkbox_block_html( $instance, 'disable_css', esc_html__( 'Disable the built-in CSS', 'category-posts' ), true ); ?>
					<?php echo $this->get_checkbox_block_html( $instance, 'disable_font_styles', esc_html__( 'Disable only font styles', 'category-posts' ), ! ( isset( $instance['disable_css'] ) && $instance['disable_css'] ) ); ?>
					<?php echo $this->get_checkbox_block_html( $instance, 'disable_theme_styles', esc_html__( 'Disable Theme\'s styles', 'category-posts' ), true ); ?>
				</div>
				<p><?php esc_html_e( 'Interim text', 'categorypostspro' ); ?></p>
				<div class="cpwp_ident">
					<?php
						echo $this->get_select_block_html(
							$instance,
							'no_match_handling',
							esc_html__( 'When there are no matches', 'category-posts' ),
							array(
								'nothing' => esc_html__( 'Display empty widget', 'category-posts' ),
								'hide'    => esc_html__( 'Hide Widget', 'category-posts' ),
								'text'    => esc_html__( 'Show text', 'category-posts' ),
							),
							'nothing',
							true
						);
					?>
					<div class="categoryPosts-no-match-text" style="display:<?php echo ( 'text' === $instance['no_match_handling'] ) ? 'block' : 'none'; ?>">
						<?php echo $this->get_textarea_html( $instance, 'no_match_text', esc_html__( 'Text', 'category-posts' ), '', true, 4 ); ?>
					</div>
				</div>
				<p><?php esc_html_e( 'Ajax API', 'categorypostspro' ); ?></p>
				<div class="cpwp_ident">
					<?php echo $this->get_checkbox_block_html( $instance, 'enable_loadmore', esc_html__( 'Enable Load More', 'category-posts' ), true ); ?>
					<div class="loadmore-settings" style="display:<?php echo ( $instance['enable_loadmore'] ) ? 'block' : 'none'; ?>">
						<?php 
						echo $this->get_checkbox_block_html( $instance, 'loadmore_scrollTo', esc_html__( 'Scrollbar', 'category-posts' ), true );
						echo $this->get_text_input_block_html( $instance, 'loadmore_text', 
							esc_html__( 'Button text', 'category-posts' ) .
							' <a href="#" class="dashicons toggle-button-text-help dashicons-editor-help">' .
								'<span class="screen-reader-text">' . esc_html__( 'Show button text help', 'category-posts' ) . '</span>' .
							'</a>', sprintf( esc_attr__( 'Load More (%s/%s)', 'category-posts' ), '%step%', '%all%'), true ); 
						?>
						<div class="cat-post-button-text-help" style="display:none;">
							<p><?php echo esc_html__( 'The following placeholders will be replaced with the relevant information.', 'category-posts' ); ?>
							</p>
							<table>
								<tr>
									<th><?php esc_html_e( '%step%', 'category-posts' ); ?></th>
									<td><?php esc_html_e( 'Loaded items', 'category-posts' ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( '%all%', 'category-posts' ); ?></th>
									<td><?php esc_html_e( 'All possible items for the set filter query', 'category-posts' ); ?></td>
								</tr>
							</table>
						</div>
						<?php echo $this->get_text_input_block_html( $instance, 'loading_text', esc_html__( 'Loading text', 'category-posts' ), esc_attr__( 'Loading...', 'category-posts' ), true ); ?>
					</div>
				</div>
			</div>
			<h4 data-panel="footer"><?php esc_html_e( 'Footer', 'category-posts' ); ?></h4>
			<div>
				<?php echo $this->get_text_input_block_html( $instance, 'footer_link_text', esc_html__( 'Footer link text', 'category-posts' ), '', true ); ?>
				<?php echo $this->get_text_input_block_html( $instance, 'footer_link', esc_html__( 'Footer link URL', 'category-posts' ), '', true ); ?>
			</div>
			<p><a href="<?php echo esc_url( get_edit_user_link() ) . '#' . __NAMESPACE__; ?>"><?php esc_html_e( 'Widget admin behaviour settings', 'category-posts' ); ?></a></p>
			<p><a target="_blank" href="<?php echo esc_url( DOC_URL ); ?>"><?php esc_html_e( 'Documentation', 'category-posts' ); ?></a></p>
			<p><a target="_blank" href="<?php echo esc_url( SUPPORT_URL ); ?>"><?php esc_html_e( 'Support', 'category-posts' ); ?></a></p>
			<p><?php echo sprintf( wp_kses( __( 'We are on <a href="%1$s">Facebook</a> and <a href="%2$s">Twitter</a>.', 'category-posts' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( 'https://www.facebook.com/TipTopPress' ), esc_url( 'https://twitter.com/TipTopPress' ) ); ?></p>
		</div>
		<?php
	}
}
