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
						$widget = new Widget();
						$ret = $widget->get_elements_HTML( $instance, $context, $start, $number );
					}
				}
				break;
		}
	}

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
