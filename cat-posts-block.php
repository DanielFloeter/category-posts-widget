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
	$instance['asc_sort_order']         = isset( $attributes['order'] ) ? $attributes['order'] : false;
	$instance['exclude_current_post']   = isset( $attributes['excludeCurrentPost'] ) ? $attributes['excludeCurrentPost'] : false;
	$instance['hideNoThumb']            = isset( $attributes['hideNoThumb'] ) ? $attributes['hideNoThumb'] : false;
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
	$instance['thumb_symbols']          = isset( $attributes['thumbSymbols'] ) ? $attributes['thumbSymbols'] : false;
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
	$instance['template']               = isset( $attributes['template'] ) ? $attributes['template'] : "%title%\n\n%thumb%\n%excerpt%";
	$instance['text_do_not_wrap_thumb'] = isset( $attributes['textDoNotWrapThumb'] ) ? $attributes['textDoNotWrapThumb'] : false;
	$instance['enable_loadmore']        = isset( $attributes['enableLoadmore'] ) ? $attributes['enableLoadmore'] : false;
	// "More posts" (none / load more / pagination) replaced the "Enable Load More"
	// toggle of the block in 5.1.3. Blocks saved before have no choice, it follows
	// the toggle then. enable_loadmore is kept in sync, the load more code reads it.
	$instance['pagination_type']        = pagination_type(
		array(
			'pagination_type' => isset( $attributes['paginationType'] ) ? $attributes['paginationType'] : '',
			'enable_loadmore' => $instance['enable_loadmore'],
		)
	);
	$instance['enable_loadmore']        = ( 'loadmore' === $instance['pagination_type'] );
	// "Query type": 'custom', or 'default', which takes over the query of the page
	// (see Widget::inherited_query()).
	$query_type = isset( $attributes['queryType'] ) ? $attributes['queryType'] : '';
	if ( ! in_array( $query_type, array( 'custom', 'default' ), true ) ) {
		$query_type = 'custom';
	}
	$instance['query_type']             = $query_type;
	$instance['inherit_query']          = ( 'default' === $query_type );
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
	$instance['ver']                    = isset( $attributes['ver'] ) ? $attributes['ver'] : '5.1.2';
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
 * Collect everything the editor preview has to apply on its own.
 *
 * On the front end equal_cover_content_height() prints a script on wp_footer/
 * admin_footer that refines the server-side "wrap the excerpt around the thumbnail"
 * guess once the actual rendered heights are known. Neither hook runs for the REST
 * request the ServerSideRender preview is built from, so the same flags are handed
 * to src/edit.js as data instead, which applies the refinement itself.
 *
 * The conditions have to stay in sync with equal_cover_content_height().
 *
 * @param array $instance The widget instance data.
 *
 * @return array The wrap-text/image-ratio handling flags for the preview.
 */
function block_preview_config( $instance ) {
	$wrap_text = ! ( isset( $instance['text_do_not_wrap_thumb'] ) && $instance['text_do_not_wrap_thumb'] );

	// No ratio calculation if one or more dimensions is set to 0.
	$img_size = isset( $instance['thumb_w'] ) && 0 !== intval( $instance['thumb_w'] ) &&
		isset( $instance['thumb_h'] ) && 0 !== intval( $instance['thumb_h'] );

	return array(
		'wrapText' => $wrap_text,
		'imgSize'  => $img_size,
	);
}

/**
 * How deep the rendering currently is inside Post Template blocks, the entries
 * of a Query Loop. Counted up before a Post Template renders its entries and
 * down again once it is done, see the two filters below.
 *
 * @param int $change 1 or -1 to change the depth, 0 to just read it.
 *
 * @return int The depth.
 */
function post_template_depth( $change = 0 ) {
	static $depth = 0;

	$depth = max( 0, $depth + $change );
	return $depth;
}

add_filter(
	'render_block_data',
	function ( $parsed_block ) {
		if ( isset( $parsed_block['blockName'] ) && 'core/post-template' === $parsed_block['blockName'] ) {
			post_template_depth( 1 );
		}
		return $parsed_block;
	}
);

add_filter(
	'render_block_core/post-template',
	function ( $block_content ) {
		post_template_depth( -1 );
		return $block_content;
	}
);

/**
 * The post the block is shown for, the one "Exclude current post" leaves out.
 *
 * Inside a Query Loop it is the post of the loop entry, from the context. The
 * context does not tell whether the block is inside a Query Loop: the Post
 * Template hands its entries only 'postId' and 'postType', not the 'queryId'
 * of the Query Loop, and outside a loop core fills 'postId' from the global
 * $post, which on an archive page is just one of the archive's posts. So the
 * Post Templates are counted while they render (post_template_depth()).
 * Outside a loop only a single post page has a current post.
 *
 * The editor preview has no page, it takes the post being edited, or the post
 * the template being edited stands for (Widget::preview_context()).
 *
 * @param \WP_Block|null $block  The block instance.
 * @param Widget         $widget The widget the block is rendered with, with
 *                               its preview context already set.
 *
 * @return int The post ID, 0 if there is none.
 */
function current_post_id_of_block( $block, $widget ) {
	$context = ( $block instanceof \WP_Block ) ? $block->context : array();

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only, preview.
		$post_id = isset( $_GET['cpwp_post_id'] ) ? absint( $_GET['cpwp_post_id'] ) : 0;
		if ( 0 === $post_id && isset( $widget->preview_context['type'] ) && 'post' === $widget->preview_context['type'] ) {
			$post_id = (int) $widget->preview_context['object']->ID;
		}
		return $post_id;
	}

	if ( isset( $context['postId'] ) && 0 < post_template_depth() ) {
		return (int) $context['postId'];
	}

	return is_singular() ? (int) get_the_ID() : 0;
}

/**
 * Renders the `tiptip/category-posts-block` on server.
 *
 * @param array          $attributes The block attributes.
 * @param string         $content    The block content, empty for this block.
 * @param \WP_Block|null $block      The block instance, for its context.
 *
 * @return string Returns the HTML of the block.
 */
function render_category_posts_block( $attributes, $content = '', $block = null ) {
	global $attr, $before_title, $after_title;

	$attr = $attributes;

	// Get HTML
	$widget = new Widget();
	$instance = build_block_instance( $attributes );
	$instance = upgrade_settings( $instance );

	$block_id = get_block_loadmore_id( $attributes );
	store_block_loadmore_settings( $block_id, $attributes );
	$widget->number = $block_id;

	// The preview has no page of its own. In the site editor it gets the slug of
	// the template being edited, in the post editor the post being edited
	// (src/edit.js), which stand in for the page the block is shown on.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only, preview.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ( ! empty( $_GET['cpwp_template_slug'] ) || ! empty( $_GET['cpwp_post_id'] ) ) ) {
		$widget->preview_template_slug = isset( $_GET['cpwp_template_slug'] ) ? sanitize_text_field( wp_unslash( $_GET['cpwp_template_slug'] ) ) : '';
		$widget->preview_context       = $widget->preview_context(
			$widget->preview_template_slug,
			isset( $_GET['cpwp_post_id'] ) ? absint( $_GET['cpwp_post_id'] ) : 0
		);
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// With the query of the page taken over, a page holds as many posts as the
	// page does, and the list starts where the page starts, for "load more".
	if ( $instance['inherit_query'] ) {
		$per_page = (int) get_query_var( 'posts_per_page' );
		if ( $per_page < 1 || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			$per_page = max( 1, (int) get_option( 'posts_per_page' ) );
		}
		$instance['num']    = $per_page;
		$instance['offset'] = 1 + ( max( 1, (int) get_query_var( 'paged' ) ) - 1 ) * $per_page;
	}

	$current_post_id = current_post_id_of_block( $block, $widget );

	// On the front end the block is rendered while the 'the_content' filter runs, and
	// wp_get_loading_optimization_attributes() skips images built inside such a content
	// blob altogether - the thumbnails get neither a 'loading' nor a 'sizes=auto'
	// attribute there. No such filter runs for the REST request the editor preview is
	// built from, so the very same thumbnails would come back lazily loaded and
	// auto-sized instead. With the thumbnail width and height set to 0 nothing else
	// gives the image a size, and a lazily loaded auto-sized image is laid out at the
	// 300x150 default object size: the preview showed a cropped 300x150 thumbnail where
	// the front end lays the image out at its full width. Take core's own skip branch
	// for the preview so both start from the same markup.
	$preview = defined( 'REST_REQUEST' ) && REST_REQUEST;
	if ( $preview ) {
		add_filter( 'pre_wp_get_loading_optimization_attributes', '__return_empty_array' );
	}

	// The loop of the list moves the global post. Put back the one before, not
	// that of the main query (wp_reset_postdata()): inside a Query Loop it is the
	// post of the loop entry, which the blocks after this one rely on.
	$previous_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

	$items = $widget->get_elements_HTML( $instance, $current_post_id, 0, 0 );
	$ret   = '';

	$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring it.
	if ( $previous_post instanceof \WP_Post ) {
		setup_postdata( $previous_post );
	}

	if ( $preview ) {
		remove_filter( 'pre_wp_get_loading_optimization_attributes', '__return_empty_array' );
	}

	// Id of the wrapper element. $widget->number has to keep its 'block-' prefix
	// because loadMoreHTML() relies on it, so use a separate variable for the DOM id.
	$dom_id = WIDGET_BASE_ID . '-' . $block_id;

	if ( ( 'nothing' === $instance['no_match_handling'] ) || ! empty( $items ) ) {
		$ret = $widget->titleHTML( $before_title, $after_title, $instance );

		$ret .= '<ul>' . implode( $items ) . '</ul>';

		if ( 'pages' === $instance['pagination_type'] ) {
			$ret .= $widget->paginationHTML( $instance );
		} elseif ( count( $items ) === (int) $instance['num'] ) {
			// Load more only if we think we have more items.
			$ret .= $widget->loadMoreHTML( $instance, $current_post_id );
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

		// Everything above hooks into wp_footer/admin_footer, which never run for the
		// REST request the editor preview is built from - and a <script> element
		// inside the returned markup would not be executed by React either
		// (dangerouslySetInnerHTML does not run embedded scripts). So hand the editor
		// the same wrap-text/image-ratio flags as inert JSON, src/edit.js applies
		// them to the preview itself.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$ret .= '<script type="application/json" class="cpwp-block-preview-config">' .
				wp_json_encode( block_preview_config( $instance ) ) .
				'</script>';
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

	// get_block_wrapper_attributes() is what actually applies the block-supports
	// classes (e.g. "aligncenter" from the toolbar's alignment option) to a
	// dynamic block's markup - core never adds them on its own, they only ever
	// end up in $attributes and are otherwise silently dropped by a hardcoded
	// wrapper like the one this used to build.
	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'id'    => $dom_id,
			'class' => WIDGET_BASE_ID . '-block',
		)
	);

	// The style island goes inside the wrapper: core adds some classes to the
	// first tag of a block's markup (e.g. 'wp-elements-…' for the link color),
	// which has to be the wrapper.
	return '<div ' . $wrapper_attributes . '>' .
		( '' !== $css ? '<style>' . $css . '</style>' : '' ) .
		$ret . '</div>';
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
		throw new \Error(
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

	$block_type = register_block_type_from_metadata(
		__DIR__,
		array(
			'render_callback' => __NAMESPACE__ . '\render_category_posts_block',
		)
	);

	// src/editor-features.js reads it while the script loads, in registerBlockType().
	if ( $block_type && ! empty( $block_type->editor_script_handles ) ) {
		wp_add_inline_script(
			$block_type->editor_script_handles[0],
			'window.cpwpBlockEditor = ' . wp_json_encode( array( 'hasContentTab' => editor_has_content_tab() ) ) . ';',
			'before'
		);
	}
}

/**
 * Whether the block editor has the "Content" tab in the block sidebar, which a
 * block fills through <InspectorControls group="content">.
 *
 * WordPress has it since 7.0, the Gutenberg plugin since 22.4. Before, the
 * editor does not know the group and shows nothing of what is put into it, so
 * src/edit.js keeps the old arrangement there. The editor itself does not tell
 * which groups it knows, hence the versions.
 *
 * @return bool
 */
function editor_has_content_tab() {
	return version_compare( get_bloginfo( 'version' ), '7.0-alpha', '>=' )
		|| ( defined( 'GUTENBERG_VERSION' ) && version_compare( GUTENBERG_VERSION, '22.4', '>=' ) );
}
add_action( 'init', __NAMESPACE__ . '\category_posts_block_init' );
