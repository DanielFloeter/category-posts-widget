<?php
/**
 * Gutenberg Block implementation.
 *
 * @package categoryposts.
 *
 * @since 4.9
 */

namespace categoryPosts;

/**
 * Build the widget instance data from block attributes.
 *
 * @param array $attributes The block attributes.
 *
 * @return array The widget instance data.
 */
function build_block_instance( $attributes ) {
	$instance = array();

	if ( ! is_array( $attributes ) ) {
		return $instance;
	}

	$instance['title']                  = isset( $attributes['title'] ) ? $attributes['title'] : '';
	$instance['title_link']             = isset( $attributes['titleLink'] ) ? $attributes['titleLink'] : false;
	$instance['title_link_target']      = isset( $attributes['titleLinkTarget'] ) ? $attributes['titleLinkTarget'] : false;
	$instance['title_level']            = isset( $attributes['titleLevel'] ) ? $attributes['titleLevel'] : 'Initial';
	$instance['title_link_url']         = isset( $attributes['titleLinkUrl'] ) ? $attributes['titleLinkUrl'] : '';
	$instance['hide_title']             = isset( $attributes['hideTitle'] ) ? $attributes['hideTitle'] : false;
	$instance['category_suggestions']   = isset( $attributes['categorySuggestions'] ) ? $attributes['categorySuggestions'] : array();
	$instance['select_categories']      = isset( $attributes['selectCategories'] ) ? $attributes['selectCategories'] : array();
	$instance['cat']                    = isset( $attributes['categories'] ) ? $attributes['categories'] : '';
	$instance['num']                    = isset( $attributes['num'] ) ? $attributes['num'] : 10;
	$instance['offset']                 = isset( $attributes['offset'] ) ? $attributes['offset'] : 1;
	$instance['sort_by']                = isset( $attributes['orderBy'] ) ? $attributes['orderBy'] : 'date';
	$instance['status']                 = isset( $attributes['status'] ) ? $attributes['status'] : 'publish';
	$instance['asc_sort_order']         = isset( $attributes['order'] ) ? $attributes['order'] : true;
	$instance['exclude_current_post']   = isset( $attributes['excludeCurrentPost'] ) ? $attributes['excludeCurrentPost'] : false;
	$instance['hide_no_thumb']          = isset( $attributes['hideNoThumb'] ) ? $attributes['hideNoThumb'] : false;
	$instance['sticky']                 = isset( $attributes['sticky'] ) ? $attributes['sticky'] : false;
	$instance['footer_link_text']       = isset( $attributes['footerLinkText'] ) ? $attributes['footerLinkText'] : '';
	$instance['footer_link']            = isset( $attributes['footerLink'] ) ? $attributes['footerLink'] : '';
	$instance['footer_link_target']     = isset( $attributes['footerLinkTarget'] ) ? $attributes['footerLinkTarget'] : false;
	$instance['item_title_level']       = isset( $attributes['itemTitleLevel'] ) ? $attributes['itemTitleLevel'] : 'Inline';
	$instance['item_title_lines']       = isset( $attributes['itemTitleLines'] ) ? $attributes['itemTitleLines'] : 2;
	$instance['thumb_w']                = isset( $attributes['thumbW'] ) ? $attributes['thumbW'] : 150;
	$instance['thumb_fluid_width']      = isset( $attributes['thumbFluidWidth'] ) ? $attributes['thumbFluidWidth'] : 100;
	$instance['thumb_h']                = isset( $attributes['thumbH'] ) ? $attributes['thumbH'] : 150;
	$instance['thumb_hover']            = isset( $attributes['thumbHover'] ) ? $attributes['thumbHover'] : 'none';
	$instance['hide_post_titles']       = isset( $attributes['hidePostTitles'] ) ? $attributes['hidePostTitles'] : false;
	$instance['excerpt_radio']          = isset( $attributes['excerptRadio'] ) ? $attributes['excerptRadio'] : 'excerpt';
	$instance['excerpt_lines']          = isset( $attributes['excerptLines'] ) ? $attributes['excerptLines'] : 4;
	$instance['excerpt_length']         = isset( $attributes['excerptLength'] ) ? $attributes['excerptLength'] : 0;
	$instance['excerpt_more_text']      = isset( $attributes['excerptMoreText'] ) ? $attributes['excerptMoreText'] : '';
	$instance['excerpt_filters']        = isset( $attributes['excerptFilters'] ) ? $attributes['excerptFilters'] : false;
	$instance['comment_num']            = isset( $attributes['commentNum'] ) ? $attributes['commentNum'] : false;
	$instance['disable_css']            = isset( $attributes['disableCss'] ) ? $attributes['disableCss'] : false;
	$instance['disable_font_styles']    = isset( $attributes['disableFontStyles'] ) ? $attributes['disableFontStyles'] : false;
	$instance['disable_theme_styles']   = isset( $attributes['disableThemeStyles'] ) ? $attributes['disableThemeStyles'] : false;
	$instance['show_post_format']       = isset( $attributes['showPostFormat'] ) ? $attributes['showPostFormat'] : 'none';
	$instance['no_cat_childs']          = isset( $attributes['noCatChilds'] ) ? $attributes['noCatChilds'] : false;
	$instance['everything_is_link']     = isset( $attributes['everythingIsLink'] ) ? $attributes['everythingIsLink'] : false;
	$instance['preset_date_format']     = isset( $attributes['presetDateFormat'] ) ? $attributes['presetDateFormat'] : 'sitedate';
	$instance['date_format']            = isset( $attributes['dateFormat'] ) ? $attributes['dateFormat'] : '';
	$instance['date_past_time']         = isset( $attributes['datePastTime'] ) ? $attributes['datePastTime'] : '0';
	$instance['template']               = isset( $attributes['template'] ) ? $attributes['template'] : "%title%\n\n%thumb%\n\n%excerpt%";
	$instance['text_do_not_wrap_thumb'] = isset( $attributes['textDoNotWrapThumb'] ) ? $attributes['textDoNotWrapThumb'] : false;
	$instance['enable_loadmore']        = isset( $attributes['enableLoadmore'] ) ? $attributes['enableLoadmore'] : false;
	$instance['loadmore_scrollTo']      = isset( $attributes['loadmoreScrollTo'] ) ? $attributes['loadmoreScrollTo'] : false;
	$instance['loadmore_text']          = isset( $attributes['loadmoreText'] ) ? $attributes['loadmoreText'] : 'Load More (%step%/%all%)';
	$instance['loading_text']           = isset( $attributes['loadingText'] ) ? $attributes['loadingText'] : 'Loading...';
	$instance['date_range']             = isset( $attributes['dateRange'] ) ? $attributes['dateRange'] : 'off';
	$instance['start_date']             = isset( $attributes['startDate'] ) ? $attributes['startDate'] : '';
	$instance['end_date']               = isset( $attributes['endDate'] ) ? $attributes['endDate'] : '';
	$instance['days_ago']               = isset( $attributes['daysAgo'] ) ? $attributes['daysAgo'] : 30;
	$instance['no_match_handling']      = isset( $attributes['noMatchHandling'] ) ? $attributes['noMatchHandling'] : 'nothing';
	$instance['no_match_text']          = isset( $attributes['noMatchText'] ) ? $attributes['noMatchText'] : '';
	$instance['default_thunmbnail']     = isset( $attributes['defaultThunmbnail'] ) ? $attributes['defaultThunmbnail'] : 0;
	$instance['ver']                    = isset( $attributes['ver'] ) ? $attributes['ver'] : '5.0.0';
	$instance['context']                = CONTEXT_BLOCK;

	return $instance;
}

/**
 * Get a stable ID for a block instance's load-more requests.
 *
 * Uses the `instanceId` block attribute (frozen in by the editor the first
 * time the block is inserted, see src/edit.js) instead of wp_unique_id().
 * wp_unique_id() only counts up within a single PHP request, so it produces
 * colliding or diverging IDs when a block is rendered more than once per
 * request or when several instances are each rendered via their own request
 * (e.g. ServerSideRender previews in the block editor, excerpt generation,
 * REST API output, full-page caching). Falling back to wp_unique_id() keeps
 * older content saved before this attribute existed working.
 *
 * @param array $attributes The block attributes.
 *
 * @return string The block load-more ID.
 */
function get_block_loadmore_id( $attributes ) {
	if ( is_array( $attributes ) && isset( $attributes['instanceId'] ) && '' !== $attributes['instanceId'] ) {
		return 'block-' . $attributes['instanceId'];
	}

	return wp_unique_id( 'block-' );
}

/**
 * Store block settings for load-more requests.
 *
 * @param string $id The block load-more ID.
 * @param array  $attributes The block attributes.
 *
 * @return void
 */
function store_block_loadmore_settings( $id, $attributes ) {
	if ( empty( $id ) || ! is_array( $attributes ) ) {
		return;
	}
	set_transient( 'cat_posts_block_' . $id, $attributes, HOUR_IN_SECONDS );
}

/**
 * Retrieve stored block settings for load-more requests.
 *
 * @param string $id The block load-more ID.
 *
 * @return array|false Stored attributes or false.
 */
function get_block_loadmore_settings( $id ) {
	if ( empty( $id ) ) {
		return false;
	}
	$settings = get_transient( 'cat_posts_block_' . $id );
	return is_array( $settings ) ? $settings : false;
}

/**
 * Renders the `tiptip/category-posts-block` on server.
 *
 * @see WP_Widget_Archives
 *
 * @param array $attributes The block attributes.
 *
 * @return string Returns the post content with archives added.
 */
function render_category_posts_block( $attributes ) {
	global $attr, $before_title, $after_title;

	$attr = $attributes;

	// Get HTML
	$widget = new Widget();
	$instance = build_block_instance( $attributes );
	$instance = upgrade_settings( $instance );

	$block_id = get_block_loadmore_id( $attributes );
	store_block_loadmore_settings( $block_id, $attributes );
	$widget->number = $block_id;


	$current_post_id = '';
	if ( is_singular() ) {
		$current_post_id = get_the_ID();
	}

	$items = $widget->get_elements_HTML( $instance, $current_post_id, 0, 0 );
	$ret   = '';

	// Id of the wrapper element. $widget->number has to keep its 'block-' prefix
	// because loadMoreHTML() relies on it, so use a separate variable for the DOM id.
	$dom_id = WIDGET_BASE_ID . '-' . $block_id;

	if ( ( 'nothing' === $instance['no_match_handling'] ) || ! empty( $items ) ) {
		$ret = $widget->titleHTML( $before_title, $after_title, $instance );

		$ret .= '<ul>' . implode( $items ) . '</ul>';

		// Load more only if we think we have more items.
		if ( count( $items ) === (int) $instance['num'] ) {
			$ret .= $widget->loadMoreHTML( $instance );
		}

		$ret .= $widget->footerHTML( $instance );

		// The 'cpwp-wrap-text' class the excerpt-lines CSS relies on, and the
		// 'cpwp-wrap-text-stage' wrapper, are added by this script at runtime.
		// Without it the excerpt lines and image ratio settings have no effect.
		if ( isset( $instance['template'] ) && preg_match( '/%thumb%|%excerpt%/', $instance['template'] ) ) {
			wp_enqueue_script( 'jquery' ); // Just in case the theme or other plugins did not enqueue it.
			add_action(
				'wp_footer',
				function () use ( $dom_id, $instance ) {
					equal_cover_content_height( $dom_id, $instance );
				},
				100
			);
			// Gutenberg Editor does not run wp_footer, so we also need to enqueue the script in the admin footer.
			add_action(
				'admin_footer',
				function () use ( $dom_id, $instance ) {
					equal_cover_content_height( $dom_id, $instance );
				},
				100
			);
		}
	} elseif ( 'text' === $instance['no_match_handling'] ) {
		$ret = $widget->titleHTML( $before_title, $after_title, $instance );
		$ret .= '<span class="cat-post-no-match">' . wp_kses_post( $instance['no_match_text'] ) . '</span>';
		$ret .= $widget->footerHTML( $instance );
	}

	if ( '' === $ret ) {
		return ''; // Nothing to show, so no style island either.
	}

	// The CSS rules depend on the settings of this specific block, so they can not be
	// part of the static stylesheet. wp_head has long been sent by the time a block is
	// rendered, so the rules are emitted as a style island right here.
	$css = '';

	static $styled = array(); // Identical attributes produce an identical id, emit once.
	if ( ! isset( $styled[ $dom_id ] ) ) {
		$styled[ $dom_id ] = true;

		$virtual = new Virtual_Widget( $dom_id, WIDGET_BASE_ID . '-block', $instance );
		$rules   = array();
		$virtual->getCSSRules( CONTEXT_BLOCK, $rules );

		foreach ( $rules as $group ) {
			$css .= implode( "\n", $group ) . "\n";
		}
	}

	return ( '' !== $css ? '<style>' . $css . '</style>' : '' ) .
		'<div id="' . esc_attr( $dom_id ) . '" class="' . WIDGET_BASE_ID . '-block">' . $ret . '</div>';
}

/**
 * Registers all block assets so that they can be enqueued through the block editor
 * in the corresponding context.
 *
 * @see https://developer.wordpress.org/block-editor/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */
function category_posts_block_init() {
	$dir = __DIR__;

	$script_asset_path = "$dir/build/index.asset.php";
	if ( ! file_exists( $script_asset_path ) ) {
		throw new Error(
			'You need to run `npm start` or `npm run build` for the "tiptip/category-posts-block" block first.'
		);
	}
	$index_js     = 'build/index.js';
	$script_asset = require( $script_asset_path );
	wp_register_script(
		'tiptip-category-posts-block-editor',
		plugins_url( $index_js, __FILE__ ),
		$script_asset['dependencies'],
		$script_asset['version']
	);
	wp_set_script_translations( 'tiptip-category-posts-block-editor', 'category-posts' );

	$editor_css = 'build/style-index.css';
	wp_register_style(
		'tiptip-category-posts-block-editor',
		plugins_url( $editor_css, __FILE__ ),
		array(),
		filemtime( "$dir/$editor_css" )
	);

	$style_css = 'build/style-index.css';
	wp_register_style(
		'tiptip-category-posts-block',
		plugins_url( $style_css, __FILE__ ),
		array(),
		filemtime( "$dir/$style_css" )
	);

	register_block_type_from_metadata(
		__DIR__,
		array(
			'render_callback' => __NAMESPACE__ . '\render_category_posts_block',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\category_posts_block_init' );
