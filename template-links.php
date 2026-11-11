<?php
/**
 * "Edit template" links in the admin lists of terms and posts.
 *
 * Every row of the category list and of the post list gets a link to the block
 * template that renders the category's archive or the post in the front end,
 * opened directly in the site editor. Above the post list there can also be
 * links to the default single template and to the archive template of posts,
 * the posts page. Tags, custom taxonomies and other post types are left to the
 * Pro version.
 *
 * When only a general template applies (e.g. "All Archives"), a second link
 * "Create own template" copies it to a template of the term or post's own
 * (e.g. category-news) and opens that in the site editor.
 *
 * Which links are shown and which lists get them is chosen on the plugin's
 * settings page (option cat_posts_admin_links). Until the settings are saved,
 * every link is shown in every list.
 *
 * The option, the settings group and the action have names of their own, not
 * those of the Pro version, so both can be active at the same time. The keys of
 * the links are the same, a list then shows each link once.
 *
 * @package categoryposts.
 *
 * @since 5.1.3
 */

namespace categoryPosts;

// Don't call the file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The name of the option that holds the lists the links are shown in.
 */
const ADMIN_LINKS_OPTION = 'cat_posts_admin_links';

/**
 * The settings group of the option, see settings_fields().
 */
const ADMIN_LINKS_GROUP = 'cat_posts_admin_settings';

/**
 * The admin-post.php action of "Create own template".
 */
const CREATE_TEMPLATE_ACTION = 'cat_posts_create_template';

/**
 * The template link settings, with every key present.
 *
 * @return array {
 *     @type string[] $links      The links to show: 'edit' and/or 'create'. Without a stored
 *                                option both; a stored option without the key only 'edit',
 *                                as before there was a choice.
 *     @type string[] $taxonomies Taxonomies whose term rows get the link.
 *     @type string[] $post_types Post types whose rows get the link.
 *     @type string[] $archives   Of the chosen post types those with an archive, for its link above the list.
 *     @type string[] $singles    The same as $post_types, for the single template link above the list.
 * }
 *
 * One choice per list: $archives and $singles follow $post_types. Stored before,
 * when they were choices of their own, a post type chosen in any of the three counts.
 *
 * @since 5.1.3
 */
function admin_links_settings() {
	$settings = get_option( ADMIN_LINKS_OPTION, null );

	// Every link is on until the settings are saved for the first time.
	if ( null === $settings ) {
		$settings = array(
			'links'      => array_keys( template_link_kinds() ),
			'taxonomies' => array_keys( template_link_taxonomies() ),
			'post_types' => array_keys( template_link_post_types() ),
			'archives'   => array_keys( template_link_archive_post_types() ),
			'singles'    => array_keys( template_link_post_types() ),
		);
	} elseif ( ! is_array( $settings ) ) {
		$settings = array();
	}

	// Only categories and posts, also when the option holds more, e.g. written
	// by the Pro version or by hand.
	$allowed = array(
		'taxonomies' => array_keys( template_link_taxonomies() ),
		'post_types' => array_keys( template_link_post_types() ),
		'archives'   => array_keys( template_link_archive_post_types() ),
		'singles'    => array_keys( template_link_post_types() ),
	);

	$result = array();
	foreach ( $allowed as $key => $names ) {
		$values         = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? array_map( 'strval', $settings[ $key ] ) : array();
		$result[ $key ] = array_values( array_intersect( $values, $names ) );
	}
	$result['post_types'] = array_values( array_unique( array_merge( $result['post_types'], $result['singles'], $result['archives'] ) ) );
	$result['singles']    = $result['post_types'];
	$result['archives']   = array_values( array_intersect( $result['post_types'], $allowed['archives'] ) );
	$result['links'] = isset( $settings['links'] ) && is_array( $settings['links'] ) ? array_values( array_map( 'strval', $settings['links'] ) ) : array( 'edit' );

	return $result;
}

/**
 * The taxonomies whose term lists can get the link: the categories. Tags and
 * custom taxonomies are left to the Pro version.
 *
 * @return \WP_Taxonomy[] Keyed by name.
 *
 * @since 5.1.3
 */
function template_link_taxonomies() {
	$taxonomy = get_taxonomy( 'category' );

	return ( $taxonomy && $taxonomy->show_ui && $taxonomy->public ) ? array( 'category' => $taxonomy ) : array();
}

/**
 * The post types whose lists can get the link: the posts. Pages and other post
 * types are left to the Pro version.
 *
 * @return \WP_Post_Type[] Keyed by name.
 *
 * @since 5.1.3
 */
function template_link_post_types() {
	$type = get_post_type_object( 'post' );

	return ( $type && $type->show_ui && is_post_type_viewable( $type ) ) ? array( 'post' => $type ) : array();
}

/**
 * The post types with an archive, whose list can get the link to its template:
 * the posts, whose archive is the posts page.
 *
 * @return \WP_Post_Type[] Keyed by name.
 *
 * @since 5.1.3
 */
function template_link_archive_post_types() {
	return template_link_post_types();
}

/**
 * The links that can be shown, with their labels for the settings.
 *
 * @return string[] Label by key.
 *
 * @since 5.1.3
 */
function template_link_kinds() {
	return array(
		'edit'   => __( 'Edit template', 'category-posts' ),
		'create' => __( 'Create own template', 'category-posts' ),
		'view'   => __( 'View in the front end', 'category-posts' ),
	);
}

/**
 * Sanitize the template link settings.
 *
 * Only names of taxonomies and post types that can get a link are kept. With a
 * classic theme the fields are disabled and not submitted, the stored values are
 * kept as they are.
 *
 * @param mixed $input The submitted value.
 *
 * @return array The value to store.
 *
 * @since 5.1.3
 */
function sanitize_admin_links( $input ) {
	if ( ! wp_is_block_theme() ) {
		return admin_links_settings();
	}

	if ( ! is_array( $input ) ) {
		$input = array();
	}

	$allowed = array(
		'taxonomies' => array_keys( template_link_taxonomies() ),
		'post_types' => array_keys( template_link_post_types() ),
	);

	$allowed['links'] = array_keys( template_link_kinds() );

	$result = array();
	foreach ( $allowed as $key => $names ) {
		$values         = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? array_map( 'strval', $input[ $key ] ) : array();
		$result[ $key ] = array_values( array_intersect( array_unique( $values ), $names ) );
	}

	return $result;
}

/**
 * The slug of the plugin's settings page.
 */
const SETTINGS_PAGE = 'category-posts';

add_action( 'admin_menu', __NAMESPACE__ . '\add_settings_page' );

/**
 * Add the settings page of the plugin under Settings.
 *
 * @since 5.1.3
 */
function add_settings_page() {
	add_options_page(
		esc_html__( 'Category Posts', 'category-posts' ),
		esc_html__( 'Category Posts', 'category-posts' ),
		'manage_options',
		SETTINGS_PAGE,
		__NAMESPACE__ . '\settings_page'
	);
}

/**
 * Output the settings page of the plugin.
 *
 * @since 5.1.3
 */
function settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Category Posts', 'category-posts' ); ?></h1>
		<h2><?php esc_html_e( 'Template Links', 'category-posts' ); ?></h2>
		<p><?php esc_html_e( 'Links in the admin lists that open the block template of a category or a post in the site editor.', 'category-posts' ); ?></p>
		<?php admin_links_form(); ?>
	</div>
	<?php
}

add_action( 'admin_init', __NAMESPACE__ . '\register_template_links' );

/**
 * Register the template link setting and the filters for the chosen lists.
 *
 * @since 5.1.3
 */
function register_template_links() {
	register_setting(
		ADMIN_LINKS_GROUP,
		ADMIN_LINKS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_admin_links',
			'default'           => array(
				'links'      => array( 'edit' ),
				'taxonomies' => array(),
				'post_types' => array(),
				'archives'   => array(),
				'singles'    => array(),
			),
		)
	);

	if ( ! wp_is_block_theme() ) {
		return;
	}

	$settings = admin_links_settings();
	if ( ! $settings['links'] ) {
		return;
	}

	foreach ( $settings['taxonomies'] as $taxonomy ) {
		add_filter( "{$taxonomy}_row_actions", __NAMESPACE__ . '\template_row_action', 10, 2 );
		add_filter( "views_edit-{$taxonomy}", __NAMESPACE__ . '\taxonomy_template_view' );
	}

	if ( $settings['post_types'] ) {
		add_filter( 'post_row_actions', __NAMESPACE__ . '\single_template_row_action', 10, 2 );
		add_filter( 'page_row_actions', __NAMESPACE__ . '\single_template_row_action', 10, 2 );
	}

	foreach ( array_unique( array_merge( $settings['singles'], $settings['archives'] ) ) as $post_type ) {
		add_filter( "views_edit-{$post_type}", __NAMESPACE__ . '\archive_template_view' );
	}
}

/**
 * The names a specific template for a term, a post or a page can have, most specific first.
 *
 * The front end tries the decoded slug before the slug itself, so a slug with
 * encoded characters (umlauts) finds a template named with the characters too.
 * Categories, tags and pages are also looked up by their id.
 *
 * @param string $prefix The template prefix, e.g. 'category' or 'single-post'.
 * @param string $slug   The slug of the term or post.
 * @param int    $id     The id, 0 when templates are not looked up by id.
 *
 * @return string[] Template slugs.
 *
 * @since 5.1.3
 */
function specific_template_slugs( $prefix, $slug, $id = 0 ) {
	$slugs   = array();
	$decoded = urldecode( $slug );
	if ( $decoded !== $slug ) {
		$slugs[] = $prefix . '-' . $decoded;
	}
	$slugs[] = $prefix . '-' . $slug;
	if ( $id ) {
		$slugs[] = $prefix . '-' . $id;
	}

	return $slugs;
}

/**
 * Replace the first, most specific entry of a template hierarchy by a list of slugs.
 *
 * @param string[] $specific  The slugs to put first.
 * @param string[] $hierarchy The hierarchy from get_template_hierarchy().
 *
 * @return string[] Template slugs.
 *
 * @since 5.1.3
 */
function merge_template_hierarchy( $specific, $hierarchy ) {
	array_shift( $hierarchy );

	return array_values( array_unique( array_merge( $specific, $hierarchy ) ) );
}

/**
 * The templates that can render a term's archive, in the order the front end tries them.
 *
 * @param \WP_Term $term The term.
 *
 * @return string[] Template slugs.
 *
 * @since 5.1.3
 */
function term_template_hierarchy( $term ) {
	$prefix = term_template_prefix( $term );

	return merge_template_hierarchy(
		term_specific_template_slugs( $term ),
		get_template_hierarchy( $prefix . '-' . $term->slug, false, $prefix )
	);
}

/**
 * The template prefix of a term's taxonomy: 'category', 'tag' or 'taxonomy-{taxonomy}'.
 *
 * @param \WP_Term $term The term.
 *
 * @return string The prefix.
 *
 * @since 5.1.3
 */
function term_template_prefix( $term ) {
	return taxonomy_template_prefix( $term->taxonomy );
}

/**
 * The template prefix of a taxonomy: 'category', 'tag' or 'taxonomy-{taxonomy}'.
 *
 * Also the slug of the template for all its terms.
 *
 * @param string $taxonomy The taxonomy name.
 *
 * @return string The prefix.
 *
 * @since 5.2.1
 */
function taxonomy_template_prefix( $taxonomy ) {
	if ( 'category' === $taxonomy ) {
		return 'category';
	}
	if ( 'post_tag' === $taxonomy ) {
		return 'tag';
	}

	return 'taxonomy-' . $taxonomy;
}

/**
 * The names a template of the term's own can have.
 *
 * @param \WP_Term $term The term.
 *
 * @return string[] Template slugs.
 *
 * @since 5.1.3
 */
function term_specific_template_slugs( $term ) {
	$prefix = term_template_prefix( $term );
	$id     = 'category' === $prefix || 'tag' === $prefix ? (int) $term->term_id : 0;

	return specific_template_slugs( $prefix, $term->slug, $id );
}

/**
 * The templates that can render a post, in the order the front end tries them.
 *
 * A template assigned to the post in the editor comes before the hierarchy. The
 * static front page tries 'front-page' first, the page for posts is rendered as
 * the blog home with 'home'.
 *
 * @param \WP_Post $post The post.
 *
 * @return string[] Template slugs.
 *
 * @since 5.1.3
 */
function post_template_hierarchy( $post ) {
	$is_static_front = 'page' === get_option( 'show_on_front' );

	if ( $is_static_front && 'page' === $post->post_type && (int) get_option( 'page_for_posts' ) === $post->ID ) {
		return get_template_hierarchy( 'home' );
	}

	if ( 'page' === $post->post_type ) {
		$hierarchy = merge_template_hierarchy(
			post_specific_template_slugs( $post ),
			get_template_hierarchy( 'page-' . $post->post_name )
		);
	} else {
		$prefix    = 'single-' . $post->post_type;
		$hierarchy = merge_template_hierarchy(
			post_specific_template_slugs( $post ),
			get_template_hierarchy( $prefix . '-' . $post->post_name, false, $prefix )
		);
	}

	// A template assigned in the editor wins over the hierarchy.
	$assigned = get_page_template_slug( $post );
	if ( $assigned ) {
		array_unshift( $hierarchy, $assigned );
	}

	if ( $is_static_front && 'page' === $post->post_type && (int) get_option( 'page_on_front' ) === $post->ID ) {
		array_unshift( $hierarchy, 'front-page' );
	}

	return array_values( array_unique( $hierarchy ) );
}

/**
 * The names a template of the post's own can have.
 *
 * @param \WP_Post $post The post.
 *
 * @return string[] Template slugs.
 *
 * @since 5.1.3
 */
function post_specific_template_slugs( $post ) {
	if ( 'page' === $post->post_type ) {
		return specific_template_slugs( 'page', $post->post_name, $post->ID );
	}

	return specific_template_slugs( 'single-' . $post->post_type, $post->post_name );
}

/**
 * Whether the post is the static front page or the page for posts.
 *
 * Their templates are 'front-page' and 'home', they win over a template of the page's own.
 *
 * @param \WP_Post $post The post.
 *
 * @return bool
 *
 * @since 5.1.3
 */
function is_front_or_posts_page( $post ) {
	return 'page' === $post->post_type && 'page' === get_option( 'show_on_front' )
		&& in_array( $post->ID, array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ), true );
}

/**
 * The templates that can render a post type's archive, in the order the front end tries them.
 *
 * For posts that is the blog page: 'home' when it is a page of its own, 'front-page'
 * first when the front page shows the latest posts.
 *
 * @param string $post_type The post type.
 *
 * @return string[] Template slugs, empty when posts have no blog page (a static front page without page for posts).
 *
 * @since 5.1.3
 */
function archive_template_hierarchy( $post_type ) {
	if ( 'post' === $post_type ) {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return get_template_hierarchy( 'front-page' );
		}

		return get_option( 'page_for_posts' ) ? get_template_hierarchy( 'home' ) : array();
	}

	return get_template_hierarchy( 'archive-' . $post_type );
}

/**
 * All block templates of the site, from the theme and the database, keyed by slug.
 *
 * Loaded once per request, a list with 20 rows only looks them up.
 *
 * @param bool $reset Forget the loaded templates, for the tests.
 *
 * @return \WP_Block_Template[]
 *
 * @since 5.1.3
 */
function block_templates_by_slug( $reset = false ) {
	static $templates = null;

	if ( $reset ) {
		$templates = null;
	}

	if ( null === $templates ) {
		$templates = array();
		foreach ( get_block_templates( array(), 'wp_template' ) as $template ) {
			$templates[ $template->slug ] = $template;
		}
	}

	return $templates;
}

/**
 * The first existing template of a hierarchy.
 *
 * @param string[] $hierarchy Template slugs, most specific first.
 *
 * @return \WP_Block_Template|null The template, null when none of them exists.
 *
 * @since 5.1.3
 */
function find_block_template( $hierarchy ) {
	$templates = block_templates_by_slug();
	foreach ( $hierarchy as $slug ) {
		if ( isset( $templates[ $slug ] ) ) {
			return $templates[ $slug ];
		}
	}

	return null;
}

/**
 * The link that opens a template in the site editor, as the admin bar builds it.
 *
 * @param \WP_Block_Template $template The template.
 *
 * @return string The URL.
 *
 * @since 5.1.3
 */
function edit_template_url( $template ) {
	return add_query_arg(
		array(
			'postType' => 'wp_template',
			'postId'   => $template->id,
			'canvas'   => 'edit',
		),
		admin_url( 'site-editor.php' )
	);
}

/**
 * What the links of a row need: the templates that can render the term, post or
 * archive, and the template of its own that "Create own template" would add.
 *
 * @param string     $kind 'term', 'taxonomy', 'post', 'single' or 'archive'.
 * @param int|string $id   The term id, the taxonomy, the post id or the post type.
 *
 * @return array|null {
 *     @type string[]    $hierarchy Template slugs, most specific first.
 *     @type string[]    $own       The slugs a template of its own can have.
 *     @type string|null $slug      The slug to create, null when a template of its own would not be used.
 *     @type string      $title     The title for the created template.
 * } Null when there is no such term, post or post type.
 *
 * @since 5.1.3
 */
function template_target( $kind, $id ) {
	if ( 'term' === $kind ) {
		$term = get_term( (int) $id );
		if ( ! $term instanceof \WP_Term ) {
			return null;
		}
		$taxonomy = get_taxonomy( $term->taxonomy );

		return array(
			'hierarchy' => term_template_hierarchy( $term ),
			'own'       => term_specific_template_slugs( $term ),
			'slug'      => term_template_prefix( $term ) . '-' . $term->slug,
			'title'     => sprintf( '%1$s: %2$s', $taxonomy ? $taxonomy->labels->singular_name : $term->taxonomy, $term->name ),
		);
	}

	if ( 'taxonomy' === $kind ) {
		$taxonomy = get_taxonomy( (string) $id );
		if ( ! $taxonomy || ! isset( template_link_taxonomies()[ $taxonomy->name ] ) ) {
			return null;
		}
		$prefix = taxonomy_template_prefix( $taxonomy->name );
		$titles = array(
			'category' => __( 'Category Archives', 'category-posts' ),
			'tag'      => __( 'Tag Archives', 'category-posts' ),
		);

		return array(
			'hierarchy' => get_template_hierarchy( $prefix, false, $prefix ),
			'own'       => array( $prefix ),
			'slug'      => $prefix,
			/* translators: %s: taxonomy name, singular */
			'title'     => isset( $titles[ $prefix ] ) ? $titles[ $prefix ] : sprintf( __( 'Taxonomy: %s', 'category-posts' ), $taxonomy->labels->singular_name ),
		);
	}

	if ( 'post' === $kind ) {
		$post = get_post( (int) $id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$type      = get_post_type_object( $post->post_type );
		$hierarchy = post_template_hierarchy( $post );

		// The front page and the page for posts have templates of their own that
		// win over the page's: 'front-page' and 'home'.
		if ( is_front_or_posts_page( $post ) ) {
			$slug = $hierarchy[0];

			return array(
				'hierarchy' => $hierarchy,
				'own'       => array( $slug ),
				'slug'      => $slug,
				'title'     => 'front-page' === $slug ? __( 'Homepage', 'category-posts' ) : __( 'Blog Home', 'category-posts' ),
			);
		}

		// A template assigned in the editor wins over one of the post's own,
		// there is nothing to create.
		$own      = post_specific_template_slugs( $post );
		$assigned = get_page_template_slug( $post );
		$slug     = $assigned ? null : ( 'page' === $post->post_type ? 'page-' : 'single-' . $post->post_type . '-' ) . $post->post_name;
		if ( $assigned ) {
			$own[] = $assigned;
		}

		return array(
			'hierarchy' => $hierarchy,
			'own'       => $own,
			'slug'      => $slug,
			'title'     => sprintf( '%1$s: %2$s', $type ? $type->labels->singular_name : $post->post_type, get_the_title( $post ) ),
		);
	}

	if ( 'single' === $kind ) {
		$type = get_post_type_object( (string) $id );
		if ( ! $type || ! isset( template_link_post_types()[ $type->name ] ) ) {
			return null;
		}

		return single_template_target( $type );
	}

	if ( 'archive' === $kind ) {
		$type = get_post_type_object( (string) $id );
		if ( ! $type || ! isset( template_link_archive_post_types()[ $type->name ] ) ) {
			return null;
		}

		if ( 'post' === $type->name ) {
			$hierarchy = archive_template_hierarchy( 'post' );
			if ( ! $hierarchy ) {
				return null;
			}

			// 'front-page' is the front page's own, so only 'home' is created.
			return array(
				'hierarchy' => $hierarchy,
				'own'       => array_values( array_diff( $hierarchy, array( 'index' ) ) ),
				'slug'      => 'home',
				'title'     => __( 'Blog Home', 'category-posts' ),
			);
		}

		return array(
			'hierarchy' => archive_template_hierarchy( $type->name ),
			'own'       => array( 'archive-' . $type->name ),
			'slug'      => 'archive-' . $type->name,
			/* translators: %s: post type name, plural */
			'title'     => sprintf( __( 'Archive: %s', 'category-posts' ), $type->labels->name ),
		);
	}

	return null;
}

/**
 * What the single template links of a post type need, see template_target().
 *
 * The single template is the one a post of the type gets without a template of
 * its own: 'page' for pages, 'single-post' or 'single' for posts and
 * 'single-{type}' or 'single' for other post types. For posts 'single' counts as
 * their own, it is the default template WordPress offers for them.
 *
 * @param \WP_Post_Type $type The post type.
 *
 * @return array The target.
 *
 * @since 5.1.3
 */
function single_template_target( $type ) {
	if ( 'page' === $type->name ) {
		return array(
			'hierarchy' => get_template_hierarchy( 'page' ),
			'own'       => array( 'page' ),
			'slug'      => 'page',
			'title'     => __( 'Pages', 'category-posts' ),
		);
	}

	$prefix = 'single-' . $type->name;

	if ( 'post' === $type->name ) {
		return array(
			'hierarchy' => get_template_hierarchy( $prefix, false, $prefix ),
			'own'       => array( $prefix, 'single' ),
			'slug'      => 'single',
			'title'     => __( 'Single Posts', 'category-posts' ),
		);
	}

	return array(
		'hierarchy' => get_template_hierarchy( $prefix, false, $prefix ),
		'own'       => array( $prefix ),
		'slug'      => $prefix,
		/* translators: %s: post type name, singular */
		'title'     => sprintf( __( 'Single item: %s', 'category-posts' ), $type->labels->singular_name ),
	);
}

/**
 * The links of a row: "Edit template" and "Create own template", as chosen in the settings.
 *
 * "Edit template" opens the template that renders the term, post or archive. The
 * template's name in the link tells whether a template of its own applies or a
 * general one like "All Archives". "Create own template" is there only for a
 * general one.
 *
 * @param string     $kind 'term', 'taxonomy', 'post', 'single' or 'archive'.
 * @param int|string $id   The term id, the taxonomy, the post id or the post type.
 *
 * @return string[] The HTML of the links, keyed 'cpwp_edit_template' and 'cpwp_create_template'.
 *
 * @since 5.1.3
 */
function template_links( $kind, $id ) {
	if ( ! wp_is_block_theme() || ! current_user_can( 'edit_theme_options' ) ) {
		return array();
	}

	$target = template_target( $kind, $id );
	if ( ! $target ) {
		return array();
	}

	$template = find_block_template( $target['hierarchy'] );
	if ( ! $template ) {
		return array();
	}

	$show  = admin_links_settings()['links'];
	$links = array();

	// Above the list the single and the archive template can stand side by side,
	// so there the links say which one they are and have keys of their own.
	if ( 'single' === $kind ) {
		$prefix = 'cpwp_single_';
		/* translators: %s: template title */
		$edit_label   = __( 'Edit default single template (%s)', 'category-posts' );
		/* translators: %s: title of the template to create */
		$create_label = __( 'Create default single template (%s)', 'category-posts' );
	} elseif ( 'taxonomy' === $kind ) {
		$prefix = 'cpwp_taxonomy_';
		/* translators: %s: template title */
		$edit_label   = __( 'Edit taxonomy template (%s)', 'category-posts' );
		/* translators: %s: title of the template to create */
		$create_label = __( 'Create taxonomy template (%s)', 'category-posts' );
	} elseif ( 'archive' === $kind ) {
		$prefix = 'cpwp_archive_';
		/* translators: %s: template title */
		$edit_label   = __( 'Edit archive template (%s)', 'category-posts' );
		/* translators: %s: title of the template to create */
		$create_label = __( 'Create archive template (%s)', 'category-posts' );
	} else {
		$prefix = 'cpwp_';
		/* translators: %s: template title */
		$edit_label   = __( 'Edit template (%s)', 'category-posts' );
		/* translators: %s: title of the template to create */
		$create_label = __( 'Create own template (%s)', 'category-posts' );
	}

	if ( in_array( 'edit', $show, true ) ) {
		$title = '' !== (string) $template->title ? $template->title : $template->slug;

		$links[ $prefix . 'edit_template' ] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( edit_template_url( $template ) ),
			esc_html( sprintf( $edit_label, $title ) )
		);
	}

	if ( in_array( 'create', $show, true ) && null !== $target['slug'] && ! in_array( $template->slug, $target['own'], true ) ) {
		$links[ $prefix . 'create_template' ] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( create_template_url( $kind, $id ) ),
			// The title the new template gets, see create_own_template().
			esc_html( sprintf( $create_label, $target['title'] ) )
		);
	}

	return $links;
}

/**
 * The URL that creates a template of its own for a term, post or archive.
 *
 * @param string     $kind 'term', 'taxonomy', 'post', 'single' or 'archive'.
 * @param int|string $id   The term id, the taxonomy, the post id or the post type.
 *
 * @return string The URL, not escaped.
 *
 * @since 5.1.3
 */
function create_template_url( $kind, $id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => CREATE_TEMPLATE_ACTION,
				'kind'   => $kind,
				'id'     => $id,
			),
			admin_url( 'admin-post.php' )
		),
		CREATE_TEMPLATE_ACTION . '_' . $kind . '_' . $id
	);
}

/**
 * Create a template of its own for a term, post or archive, as a copy of the template that applies now.
 *
 * Stored in the database for the active theme, as the site editor does. When the
 * template exists already, that one is returned.
 *
 * @param string     $kind 'term', 'taxonomy', 'post', 'single' or 'archive'.
 * @param int|string $id   The term id, the taxonomy, the post id or the post type.
 *
 * @return \WP_Block_Template|\WP_Error The template.
 *
 * @since 5.1.3
 */
function create_own_template( $kind, $id ) {
	$target = template_target( $kind, $id );
	if ( ! $target || null === $target['slug'] ) {
		return new \WP_Error( 'cpwp_no_own_template', __( 'A template of its own can not be created for this.', 'category-posts' ) );
	}

	$templates = block_templates_by_slug();
	if ( isset( $templates[ $target['slug'] ] ) ) {
		return $templates[ $target['slug'] ];
	}

	$source = find_block_template( $target['hierarchy'] );

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'wp_template',
			'post_status'  => 'publish',
			'post_name'    => $target['slug'],
			'post_title'   => $target['title'],
			'post_content' => $source ? $source->content : '',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	wp_set_post_terms( $post_id, get_stylesheet(), 'wp_theme' );

	$templates = block_templates_by_slug( true );
	$slug      = get_post_field( 'post_name', $post_id );
	if ( ! isset( $templates[ $slug ] ) ) {
		return new \WP_Error( 'cpwp_template_not_created', __( 'The template could not be created.', 'category-posts' ) );
	}

	return $templates[ $slug ];
}

add_action( 'admin_post_' . CREATE_TEMPLATE_ACTION, __NAMESPACE__ . '\handle_create_template' );

/**
 * Handle the "Create own template" link: create the template and open it in the site editor.
 *
 * @since 5.1.3
 */
function handle_create_template() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checked by check_admin_referer() below.
	$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
	$id   = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	check_admin_referer( CREATE_TEMPLATE_ACTION . '_' . $kind . '_' . $id );

	if ( ! wp_is_block_theme() || ! current_user_can( 'edit_theme_options' ) || ! in_array( 'create', admin_links_settings()['links'], true ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to create templates.', 'category-posts' ), 403 );
	}

	$template = create_own_template( $kind, $id );
	if ( is_wp_error( $template ) ) {
		wp_die( esc_html( $template->get_error_message() ) );
	}

	wp_safe_redirect( edit_template_url( $template ) );
	exit;
}

/**
 * Add the links to the row of a term.
 *
 * Filter of '{$taxonomy}_row_actions'.
 *
 * @param string[] $actions Row actions.
 * @param \WP_Term $term    The term of the row.
 *
 * @return string[] Row actions.
 *
 * @since 5.1.3
 */
function template_row_action( $actions, $term ) {
	if ( ! $term instanceof \WP_Term || ! in_array( $term->taxonomy, admin_links_settings()['taxonomies'], true ) ) {
		return $actions;
	}

	return array_merge( $actions, template_links( 'term', $term->term_id ) );
}

/**
 * Add the links to the row of a post.
 *
 * Filter of 'post_row_actions' and 'page_row_actions'. Only published posts of the
 * chosen post types get them, drafts have no page in the front end yet.
 *
 * @param string[] $actions Row actions.
 * @param \WP_Post $post    The post of the row.
 *
 * @return string[] Row actions.
 *
 * @since 5.1.3
 */
function single_template_row_action( $actions, $post ) {
	if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status
		|| ! in_array( $post->post_type, admin_links_settings()['post_types'], true )
		|| ! is_post_type_viewable( $post->post_type ) ) {
		return $actions;
	}

	return array_merge( $actions, template_links( 'post', $post->ID ) );
}

/**
 * Add the links for the post type's single and archive template to the views above the list.
 *
 * Filter of 'views_edit-{$post_type}'.
 *
 * @param string[] $views The views ("All", "Mine", "Published", ...).
 *
 * @return string[] The views.
 *
 * @since 5.1.3
 */
function archive_template_view( $views ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	// The term list of a taxonomy with the post type's name has the same filter.
	$post_type = $screen && 'edit' === $screen->base && $screen->post_type ? $screen->post_type : '';
	if ( '' === $post_type ) {
		return $views;
	}

	$settings = admin_links_settings();
	$view     = in_array( 'view', $settings['links'], true );
	if ( in_array( $post_type, $settings['singles'], true ) ) {
		if ( $view ) {
			/* translators: %s: title of the template that renders the item */
			$views = array_merge( $views, view_link( 'cpwp_single_view', __( 'View single item (%s)', 'category-posts' ), 'single', $post_type, 'single_view_url' ) );
		}
		$views = array_merge( $views, template_links( 'single', $post_type ) );
	}
	if ( in_array( $post_type, $settings['archives'], true ) ) {
		if ( $view ) {
			/* translators: %s: title of the template that renders the archive */
			$views = array_merge( $views, view_link( 'cpwp_archive_view', __( 'View archive (%s)', 'category-posts' ), 'archive', $post_type, 'archive_url' ) );
		}
		$views = array_merge( $views, template_links( 'archive', $post_type ) );
	}

	return $views;
}

/**
 * A "View" link above the post list: a page in the front end that the template renders.
 *
 * Named after the template, the one the "Edit" link next to it opens, like "View" in the rows.
 *
 * @param string   $key       The key of the view.
 * @param string   $label     The label with %s for the template title.
 * @param string   $kind      'single' or 'archive', see template_target().
 * @param string   $post_type The post type.
 * @param callable $url       Gets the post type and the template, returns the URL or ''.
 *
 * @return string[] The link keyed by $key, empty without a page to show.
 *
 * @since 5.2.1
 */
function view_link( $key, $label, $kind, $post_type, $url ) {
	$target   = template_target( $kind, $post_type );
	$template = $target ? find_block_template( $target['hierarchy'] ) : null;
	if ( ! $template ) {
		return array();
	}

	$address = call_user_func( __NAMESPACE__ . '\\' . $url, $post_type, $template );
	if ( '' === $address ) {
		return array();
	}

	$title = '' !== (string) $template->title ? $template->title : $template->slug;

	return array( $key => sprintf( '<a href="%s">%s</a>', esc_url( $address ), esc_html( sprintf( $label, $title ) ) ) );
}

/**
 * The address of the newest published item that the default single template renders.
 *
 * Items with a template of their own or an assigned one are skipped, so are the
 * front page and the page for posts. Looks at the 20 newest.
 *
 * @param string             $post_type The post type.
 * @param \WP_Block_Template $template  The default single template.
 *
 * @return string The URL, not escaped. Empty without such an item.
 *
 * @since 5.2.1
 */
function single_view_url( $post_type, $template ) {
	$ids = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'numberposts'      => 20,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post || is_front_or_posts_page( $post ) ) {
			continue;
		}
		$renders = find_block_template( post_template_hierarchy( $post ) );
		if ( $renders && $renders->slug === $template->slug ) {
			return (string) get_permalink( $post );
		}
	}

	return '';
}

/**
 * The address of a post type's archive in the front end.
 *
 * For posts the page for posts, or the front page when it shows the latest posts.
 * Other post types have the address of has_archive, e.g. /bildergalerien/.
 *
 * @param string $post_type The post type.
 *
 * @return string The URL, not escaped. Empty without an archive.
 *
 * @since 5.2.1
 */
function archive_url( $post_type ) {
	if ( ! archive_template_hierarchy( $post_type ) ) {
		return '';
	}
	$url = get_post_type_archive_link( $post_type );

	return $url ? $url : '';
}

/**
 * Add the links for the template of all terms of the taxonomy above the term list.
 *
 * Filter of 'views_edit-{$taxonomy}', edit-tags.php shows the views above the table.
 * E.g. "taxonomy-galerie_kategorie" for all /galerie_kategorie/.../ archives, the
 * links in the rows are for the template of one term.
 *
 * @param string[] $views The views, none in core.
 *
 * @return string[] The views.
 *
 * @since 5.2.1
 */
function taxonomy_template_view( $views ) {
	$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$settings = admin_links_settings();
	if ( ! $screen || 'edit-tags' !== $screen->base || ! $screen->taxonomy
		|| ! in_array( $screen->taxonomy, $settings['taxonomies'], true ) ) {
		return $views;
	}

	$views = (array) $views;
	if ( in_array( 'view', $settings['links'], true ) ) {
		/* translators: %s: title of the template that renders the term archive */
		$views = array_merge( $views, view_link( 'cpwp_taxonomy_view', __( 'View term archive (%s)', 'category-posts' ), 'taxonomy', $screen->taxonomy, 'taxonomy_view_url' ) );
	}

	return array_merge( $views, template_links( 'taxonomy', $screen->taxonomy ) );
}

/**
 * The address of a term archive that the template for all terms of the taxonomy renders.
 *
 * The taxonomy has no page of its own, its template renders the archive of every
 * term without one of its own. The newest such term with posts, among the 20 newest.
 *
 * @param string              $taxonomy The taxonomy.
 * @param \WP_Block_Template $template The template of the taxonomy that applies.
 *
 * @return string The URL, not escaped. Empty without such a term.
 *
 * @since 5.2.1
 */
function taxonomy_view_url( $taxonomy, $template ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'term_id',
			'order'      => 'DESC',
			'number'     => 20,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return '';
	}
	foreach ( $terms as $term ) {
		$renders = find_block_template( term_template_hierarchy( $term ) );
		if ( $renders && $renders->slug === $template->slug ) {
			$link = get_term_link( $term );
			return is_wp_error( $link ) ? '' : $link;
		}
	}

	return '';
}

/**
 * Output the template link settings, the form of the settings page.
 *
 * @since 5.1.3
 */
function admin_links_form() {
	$settings    = admin_links_settings();
	$block_theme = wp_is_block_theme();

	// Notes next to single checkboxes, keyed '{group}:{name}'.
	$notes = array();
	if ( ! archive_template_hierarchy( 'post' ) ) {
		$notes['post_types:post'] = sprintf(
			/* translators: %s: link to Settings > Reading */
			__( 'No page for posts is set (%s), so posts have no archive.', 'category-posts' ),
			'<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Settings → Reading', 'category-posts' ) . '</a>'
		);
	}

	$groups = array(
		'links'      => array(
			'label' => __( 'Show these links', 'category-posts' ),
			'help'  => __( '"Create own template" only appears when a general template applies, e.g. "All Archives". It copies that template.', 'category-posts' ),
			'items' => template_link_kinds(),
		),
		'taxonomies' => array(
			'label' => __( 'Term lists', 'category-posts' ),
			'help'  => __( 'In the rows the template of the category, above the list the template of all categories, "Category Archives".', 'category-posts' ),
			'items' => template_link_taxonomies(),
		),
		'post_types' => array(
			'label' => __( 'Post lists', 'category-posts' ),
			'help'  => __( 'In the rows the template of the post, above the list the default single template and the archive template.', 'category-posts' ),
			'items' => template_link_post_types(),
		),
	);
	?>
	<form method="POST" action="options.php">
		<?php settings_fields( ADMIN_LINKS_GROUP ); ?>
		<?php if ( ! $block_theme ) { ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Requires a block theme. The links open the templates in the site editor.', 'category-posts' ); ?></p></div>
		<?php } ?>
		<table class="form-table" role="presentation">
			<?php foreach ( $groups as $key => $group ) { ?>
				<tr>
					<th scope="row"><?php echo esc_html( $group['label'] ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php echo esc_html( $group['label'] ); ?></legend>
							<?php if ( ! $group['items'] ) { ?>
								<p><em><?php esc_html_e( 'None available.', 'category-posts' ); ?></em></p>
							<?php } ?>
							<?php foreach ( $group['items'] as $name => $object ) { ?>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( ADMIN_LINKS_OPTION . '[' . $key . '][]' ); ?>" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $settings[ $key ], true ) ); ?> <?php disabled( ! $block_theme ); ?>>
									<?php echo esc_html( is_string( $object ) ? $object : $object->labels->name ); ?>
								</label>
								<?php if ( isset( $notes[ $key . ':' . $name ] ) ) { ?>
									<span class="description">&ndash; <?php echo wp_kses( $notes[ $key . ':' . $name ], array( 'a' => array( 'href' => array() ) ) ); ?></span>
								<?php } ?>
								<br>
							<?php } ?>
							<?php if ( ! empty( $group['help'] ) ) { ?>
								<p class="description"><?php echo esc_html( $group['help'] ); ?></p>
							<?php } ?>
						</fieldset>
					</td>
				</tr>
			<?php } ?>
		</table>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to the Pro version */
				esc_html__( 'Tags, custom taxonomies, pages and custom post types: %s', 'category-posts' ),
				'<a target="_blank" href="' . esc_url( PRO_URL ) . '">' . esc_html__( 'Pro version', 'category-posts' ) . '</a>'
			);
			?>
		</p>
		<?php submit_button( null, 'primary', 'submit', true, $block_theme ? null : array( 'disabled' => 'disabled' ) ); ?>
	</form>
	<?php
}
