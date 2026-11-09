<?php
/**
 * Server side implementation of load more handling.
 *
 * @package categoryposts.
 *
 * @since 4.9
 */

namespace categoryPosts;

// Don't call the file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed the front end JS for load more.
 *
 * @since 4.9
 */
function embed_loadmore_scripts() {
	echo '<script>{';
	$suffix = 'min.js';
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
		$suffix = 'js';
	}
	echo 'var tiptoppress = Array();';
	echo 'tiptoppress["' . esc_js( __NAMESPACE__ ) . '"] = { json_root_url : "' . esc_js( rest_url( __NAMESPACE__ . '/loadmore' ) ) . '"};';
	include __DIR__ . '/js/frontend/loadmore.' . $suffix;
	echo '}</script>';
}

/**
 * Normalize a block load-more ID so stored block settings can be resolved
 * consistently for both the prefixed and unprefixed form.
 *
 * @param string $id The load-more identifier.
 *
 * @return string The normalized block ID.
 */
function normalize_block_loadmore_id( $id ) {
	if ( empty( $id ) ) {
		return '';
	}

	return 0 === strpos( $id, 'block-' ) ? $id : 'block-' . $id;
}

/**
 * The query variables of a page, signed, for "load more" of a list which took
 * over the query of the page, or the context of the page, for a list with
 * "Query type: Matching" (Widget::page_context_vars()).
 *
 * @param array  $vars The query variables of the request of the page, or the context.
 * @param string $kind What the signature is for, a signature of one kind is
 *                     not valid for another.
 *
 * @return array Array with 'query' (JSON) and 'sig'.
 *
 * @since 5.1.3
 */
function sign_inherited_query( $vars, $kind = 'inherited-query' ) {
	$vars = (array) $vars;
	unset( $vars['paged'], $vars['page'], $vars['offset'], $vars['posts_per_page'] );
	$query = wp_json_encode( $vars );

	return array(
		'query' => $query,
		'sig'   => wp_hash( 'cpwp-' . $kind . '|' . $query ),
	);
}

/**
 * The query variables of a signed query, see sign_inherited_query().
 *
 * @param string $query The query variables as JSON.
 * @param string $sig   The signature.
 * @param string $kind  What the signature is for.
 *
 * @return array|null The query variables, null if the signature does not match.
 *
 * @since 5.1.3
 */
function verify_inherited_query( $query, $sig, $kind = 'inherited-query' ) {
	if ( '' === $query || ! hash_equals( wp_hash( 'cpwp-' . $kind . '|' . $query ), $sig ) ) {
		return null;
	}
	$vars = json_decode( $query, true );

	return is_array( $vars ) ? $vars : null;
}

/**
 * Generate the JSON response which includes additional element as a response
 * to a "load more" request.
 *
 * @param \WP_REST_Request $request The rest request with widget aand start point info.
 */
function get_next_elements( \WP_REST_Request $request ) {
	$id = (string) $request['id'];
	$start = (int) $request['start'];
	$number = (int) $request['number'];
	$context = (string) $request['context'];

	$ret = array();

	// The first page of a block or a shortcode is rendered while the 'the_content'
	// filter runs, and wp_get_loading_optimization_attributes() leaves images built
	// there alone: the thumbnails get neither 'loading' nor 'sizes="auto"'. No such
	// filter runs for this REST request, so the very same thumbnails came back lazily
	// loaded and auto-sized. With the thumbnail width and height at 0 nothing else
	// gives such an image a size before it is loaded (core's contain-intrinsic-size
	// fallback is 3000x1500). Take core's skip branch here as well, like
	// render_category_posts_block() does for the editor preview, so the loaded
	// entries match those of the first page.
	add_filter( 'pre_wp_get_loading_optimization_attributes', '__return_empty_array' );

	$id_components = explode( '-', $id );
	if ( 2 <= count( $id_components ) ) {
		switch ( $id_components[0] ) {
			case 'shortcode':
				if ( 2 === count( $id_components ) || 3 === count( $id_components ) ) {
					$pid = $id_components[1];  // The ID of the relevant post.
					$name = isset( $id_components[2] ) ? $id_components[2] : ''; // The shortcode "name".
					$settings = shortcode_settings( $pid, $name );
					if ( ! empty( $settings ) ) {
						$settings['context'] = CONTEXT_SHORTCODE;
						$virtual_widget = new Virtual_Widget( '', '', $settings );
						$ret = $virtual_widget->get_elements_HTML( $start, $number, $context );
					}
				}
				break;
			case 'widget':
				if ( 2 === count( $id_components ) ) {
					$id = $id_components[1];  // The ID of the widget.
					$class = __NAMESPACE__ . '\Widget';
					$widgetclass = new $class();
					$allsettings = $widgetclass->get_settings();
					if ( isset( $allsettings[ $id ] ) ) {
						$allsettings[ $id ]['context'] = CONTEXT_WIDGET;
						$virtual_widget = new Virtual_Widget( '', '', $allsettings[ $id ] );
						$ret = $virtual_widget->get_elements_HTML( $start, $number, $context );
					}
				}
				break;
			case 'block':
				if ( 2 === count( $id_components ) ) {
					$block_id = normalize_block_loadmore_id( $id_components[1] );
					$settings = get_block_loadmore_settings( $block_id );
					if ( false !== $settings ) {
						$instance = build_block_instance( $settings );
						$widget   = new Widget();
						if ( $instance['inherit_query'] ) {
							// The query of the page the list is on, signed by loadMoreHTML().
							// Without a valid signature nothing is loaded, rather than posts from
							// everywhere.
							$widget->inherited_vars = verify_inherited_query( (string) $request['query'], (string) $request['sig'] );
							if ( null === $widget->inherited_vars ) {
								break;
							}
						}
						if ( 'context' === $instance['query_type'] && 0 === (int) $context ) {
							// What the page the list is on is about, signed by loadMoreHTML().
							// With a current post the context comes from the post instead.
							$widget->page_context = verify_inherited_query( (string) $request['query'], (string) $request['sig'], 'context' );
							if ( null === $widget->page_context ) {
								break;
							}
						}
						$ret = $widget->get_elements_HTML( $instance, $context, $start, $number );
					}
				}
				break;
		}
	}

	remove_filter( 'pre_wp_get_loading_optimization_attributes', '__return_empty_array' );

	return new \WP_REST_Response( $ret );
}

/**
 * This function is where we register our routes for our example endpoint.
 */
function register_route() {
	register_rest_route(
		__NAMESPACE__,
		'/loadmore/(?P<id>[\w-]+)/(?P<start>[\d]+)/(?P<number>[\d]+)/(?P<context>[\w]+)',
		array(
			'methods'  => 'GET',
			'callback' => __NAMESPACE__ . '\get_next_elements',
			'permission_callback' => '__return_true',
		)
	);
}

add_action( 'rest_api_init', __NAMESPACE__ . '\register_route' );
