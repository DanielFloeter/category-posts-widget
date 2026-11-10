<?php
/**
 * Tests for the "Edit template" links in the admin lists (template-links.php).
 *
 * They run with Twenty Twenty-Five as the block theme: it brings archive, home,
 * index, page and single as files, more specific templates are added to the
 * database the way the site editor stores them.
 *
 * @since 5.1.3
 */

if ( ! defined( 'NS' ) ) {
	define( 'NS', 'categoryPosts' );
}

/**
 * The "Edit template" links.
 */
class testTemplateLinks extends WP_UnitTestCase {

	/**
	 * Switch to a block theme, log in an administrator.
	 */
	public function set_up() {
		parent::set_up();
		switch_theme( 'twentytwentyfive' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->reset_templates();
	}

	/**
	 * Forget the templates loaded by an earlier test.
	 */
	private function reset_templates() {
		call_user_func( NS . '\block_templates_by_slug', true );
	}

	/**
	 * Store a template in the database, as the site editor does.
	 *
	 * @param string $slug  The template slug.
	 * @param string $title The template title.
	 */
	private function add_db_template( $slug, $title ) {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_post_terms( $id, get_stylesheet(), 'wp_theme' );
		$this->reset_templates();
	}

	/**
	 * Call a function of the plugin.
	 *
	 * @param string $name    The function name without namespace.
	 * @param mixed  ...$args The arguments.
	 *
	 * @return mixed
	 */
	private function call( $name, ...$args ) {
		return call_user_func_array( NS . '\\' . $name, $args );
	}

	/**
	 * Turn the links on for the given lists.
	 *
	 * @param array $settings The option value.
	 */
	private function enable( $settings ) {
		update_option( 'cat_posts_admin_links', $settings );
	}

	/**
	 * Render the settings form.
	 *
	 * @return string The HTML.
	 */
	private function form() {
		ob_start();
		$this->call( 'admin_links_form' );
		return ob_get_clean();
	}

	/**
	 * Category: own slug, then its id, then the general templates.
	 */
	public function testCategoryHierarchy() {
		$term = get_term( self::factory()->category->create( array( 'slug' => 'news' ) ) );
		$this->assertSame(
			array( 'category-news', 'category-' . $term->term_id, 'category', 'archive', 'index' ),
			$this->call( 'term_template_hierarchy', $term )
		);
	}

	/**
	 * An encoded slug (umlauts) is tried decoded first, as in the front end.
	 */
	public function testEncodedSlugIsTriedDecodedFirst() {
		$term = get_term( self::factory()->category->create( array( 'slug' => 'wettk%c3%a4mpfe' ) ) );
		$this->assertSame(
			array( 'category-wettkämpfe', 'category-' . $term->slug, 'category-' . $term->term_id, 'category', 'archive', 'index' ),
			$this->call( 'term_template_hierarchy', $term )
		);
	}

	/**
	 * Posts: the assigned template wins.
	 */
	public function testPostHierarchy() {
		$post = get_post( self::factory()->post->create( array( 'post_name' => 'hello' ) ) );
		$this->assertSame(
			array( 'single-post-hello', 'single-post', 'single', 'singular', 'index' ),
			$this->call( 'post_template_hierarchy', $post )
		);

		update_post_meta( $post->ID, '_wp_page_template', 'wide' );
		$this->assertSame( 'wide', $this->call( 'post_template_hierarchy', get_post( $post->ID ) )[0] );
	}

	/**
	 * A template of its own for the category is found in the database and named in the link.
	 */
	public function testOwnTemplateOfTheTerm() {
		$this->enable( array( 'taxonomies' => array( 'category' ) ) );
		$this->add_db_template( 'category-news', 'Category: News' );
		$term    = get_term( self::factory()->category->create( array( 'slug' => 'news' ) ) );
		$actions = $this->call( 'template_row_action', array(), $term );

		$this->assertStringContainsString( 'Edit template (Category: News)', $actions['cpwp_edit_template'] );
		$this->assertStringContainsString( 'postId=' . get_stylesheet() . '//category-news&', $actions['cpwp_edit_template'] );
		$this->assertStringContainsString( 'canvas=edit', $actions['cpwp_edit_template'] );
	}

	/**
	 * Without a template of its own the general one of the theme (a file) applies.
	 */
	public function testGeneralTemplateFromTheTheme() {
		$this->enable( array( 'taxonomies' => array( 'category' ) ) );
		$term    = get_term( self::factory()->category->create( array( 'slug' => 'other' ) ) );
		$actions = $this->call( 'template_row_action', array(), $term );
		$this->assertStringContainsString( 'postId=' . get_stylesheet() . '//archive&', $actions['cpwp_edit_template'] );

		$this->add_db_template( 'category', 'All Categories' );
		$actions = $this->call( 'template_row_action', array(), $term );
		$this->assertStringContainsString( '//category&', $actions['cpwp_edit_template'] );
	}

	/**
	 * Tags get no link, they are left to the Pro version.
	 */
	public function testNoLinkForTags() {
		$this->enable( array( 'taxonomies' => array( 'post_tag' ) ) );
		$tag = get_term( self::factory()->tag->create() );
		$this->assertSame( array(), $this->call( 'template_row_action', array(), $tag ) );
	}

	/**
	 * Posts get the link only when published; pages not.
	 */
	public function testPostRows() {
		$this->enable( array( 'post_types' => array( 'post', 'page' ) ) );
		$post    = get_post( self::factory()->post->create() );
		$actions = $this->call( 'single_template_row_action', array(), $post );
		$this->assertStringContainsString( '//single&', $actions['cpwp_edit_template'] );

		$draft = get_post( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );
		$this->assertSame( array(), $this->call( 'single_template_row_action', array(), $draft ) );

		// "page" was not kept by the sanitizing, but even stored it does not count.
		$page = get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		$this->enable( array( 'post_types' => array( 'page' ) ) );
		$this->assertSame( array(), $this->call( 'single_template_row_action', array(), $page ) );
	}

	/**
	 * No link without the capability or with a classic theme, and none for lists that are off.
	 */
	public function testNoLink() {
		$term = get_term( self::factory()->category->create() );
		$this->enable( array( 'taxonomies' => array() ) );
		$this->assertSame( array(), $this->call( 'template_row_action', array(), $term ), 'list off' );

		$this->enable( array( 'taxonomies' => array( 'category' ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( array(), $this->call( 'template_row_action', array(), $term ), 'editor' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		switch_theme( 'twentytwelve' );
		$this->assertSame( array(), $this->call( 'template_row_action', array(), $term ), 'classic theme' );
	}

	/**
	 * Only categories and posts are kept.
	 */
	public function testSanitize() {
		$this->assertSame(
			array(
				'taxonomies' => array( 'category' ),
				'post_types' => array( 'post' ),
				'archives'   => array( 'post' ),
				'singles'    => array( 'post' ),
				'links'      => array( 'create' ),
			),
			$this->call(
				'sanitize_admin_links',
				array(
					'links'      => array( 'create', 'delete' ),
					'taxonomies' => array( 'category', 'nope', 'post_tag', 'category' ),
					'post_types' => array( 'post', 'page', 'attachment' ),
					'archives'   => array( 'post', 'page' ),
					'singles'    => array( 'page', 'post' ),
				)
			)
		);
		$this->assertSame( array( 'taxonomies' => array(), 'post_types' => array(), 'archives' => array(), 'singles' => array(), 'links' => array() ), $this->call( 'sanitize_admin_links', null ) );
	}

	/**
	 * Without a stored option every link is on; a stored option without links keeps only "Edit template".
	 */
	public function testDefaults() {
		delete_option( 'cat_posts_admin_links' );
		$this->assertSame(
			array(
				'taxonomies' => array( 'category' ),
				'post_types' => array( 'post' ),
				'archives'   => array( 'post' ),
				'singles'    => array( 'post' ),
				'links'      => array( 'edit', 'create' ),
			),
			$this->call( 'admin_links_settings' )
		);

		$this->enable( array( 'taxonomies' => array( 'category' ) ) );
		$this->assertSame( array( 'edit' ), $this->call( 'admin_links_settings' )['links'] );
		$this->assertSame( array(), $this->call( 'admin_links_settings' )['post_types'] );
	}

	/**
	 * With a classic theme the fields are disabled, saving keeps the stored values.
	 */
	public function testSanitizeKeepsValuesWithAClassicTheme() {
		$this->enable( array( 'taxonomies' => array( 'category' ) ) );
		switch_theme( 'twentytwelve' );
		$this->assertSame( array( 'category' ), $this->call( 'sanitize_admin_links', null )['taxonomies'] );
	}

	/**
	 * The option and its group have names of their own, not those of the Pro version.
	 */
	public function testOwnOptionGroup() {
		global $new_allowed_options;
		$this->call( 'register_template_links' );
		$this->assertSame( array( 'cat_posts_admin_links' ), $new_allowed_options['cat_posts_admin_settings'] );
	}

	/**
	 * The form lists categories and posts, the chosen ones checked; disabled with a classic theme.
	 */
	public function testForm() {
		$this->enable( array( 'taxonomies' => array( 'category' ) ) );
		$html = $this->form();
		$this->assertStringContainsString( 'name="cat_posts_admin_links[taxonomies][]" value="category"  checked=\'checked\'', $html );
		$this->assertStringNotContainsString( 'value="post_tag"', $html );
		$this->assertStringContainsString( "name='option_page' value='cat_posts_admin_settings'", $html );
		$this->assertStringNotContainsString( 'Requires a block theme', $html );

		switch_theme( 'twentytwelve' );
		$html = $this->form();
		$this->assertStringContainsString( 'Requires a block theme', $html );
		$this->assertStringContainsString( "disabled='disabled'", $html );
	}

	/**
	 * The settings page shows the form.
	 */
	public function testSettingsPage() {
		ob_start();
		$this->call( 'settings_page' );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<h1>Category Posts</h1>', $html );
		$this->assertStringContainsString( 'cat_posts_admin_links[links][]', $html );
	}

	/**
	 * Without a stored choice only "Edit template" is shown; each link can be turned off.
	 */
	public function testChoiceOfLinks() {
		$term = get_term( self::factory()->category->create( array( 'slug' => 'news' ) ) );

		$this->enable( array( 'taxonomies' => array( 'category' ) ) );
		$this->assertSame( array( 'cpwp_edit_template' ), array_keys( $this->call( 'template_row_action', array(), $term ) ) );

		$this->enable( array( 'taxonomies' => array( 'category' ), 'links' => array( 'edit', 'create' ) ) );
		$this->assertSame( array( 'cpwp_edit_template', 'cpwp_create_template' ), array_keys( $this->call( 'template_row_action', array(), $term ) ) );

		$this->enable( array( 'taxonomies' => array( 'category' ), 'links' => array( 'create' ) ) );
		$this->assertSame( array( 'cpwp_create_template' ), array_keys( $this->call( 'template_row_action', array(), $term ) ) );
	}

	/**
	 * "Create own template" only while a general template applies, and only where one of its own would be used.
	 */
	public function testCreateLinkOnlyForGeneralTemplates() {
		$this->enable( array( 'taxonomies' => array( 'category' ), 'post_types' => array( 'post' ), 'links' => array( 'create' ) ) );
		$news  = get_term( self::factory()->category->create( array( 'slug' => 'news' ) ) );
		$other = get_term( self::factory()->category->create( array( 'slug' => 'other' ) ) );
		$this->add_db_template( 'category-news', 'Category: News' );

		$this->assertSame( array(), $this->call( 'template_row_action', array(), $news ), 'has its own' );
		$actions = $this->call( 'template_row_action', array(), $other );
		$this->assertStringContainsString( 'admin-post.php?action=cat_posts_create_template&#038;kind=term&#038;id=' . $other->term_id, $actions['cpwp_create_template'] );
		$this->assertStringContainsString( '_wpnonce=', $actions['cpwp_create_template'] );

		$this->add_db_template( 'category-' . $other->term_id, 'By id' );
		$this->assertSame( array(), $this->call( 'template_row_action', array(), $other ), 'own template by id' );

		$assigned = self::factory()->post->create();
		update_post_meta( $assigned, '_wp_page_template', 'wide' );
		$this->assertSame( array(), $this->call( 'single_template_row_action', array(), get_post( $assigned ) ), 'assigned template' );
	}

	/**
	 * Creating copies the template that applies now and stores it for the theme under the category's own slug.
	 */
	public function testCreateOwnTemplate() {
		$term     = get_term( self::factory()->category->create( array( 'slug' => 'news', 'name' => 'News' ) ) );
		$template = $this->call( 'create_own_template', 'term', $term->term_id );

		$this->assertInstanceOf( 'WP_Block_Template', $template );
		$this->assertSame( 'category-news', $template->slug );
		$this->assertSame( 'Category: News', $template->title );
		$this->assertSame( get_stylesheet() . '//category-news', $template->id );
		$this->assertSame( 'custom', $template->source );
		$archive = get_block_template( get_stylesheet() . '//archive' );
		$this->assertSame( $archive->content, $template->content );

		$this->assertSame( 'category-news', $this->call( 'find_block_template', $this->call( 'term_template_hierarchy', $term ) )->slug );
		$this->assertSame( $template->wp_id, $this->call( 'create_own_template', 'term', $term->term_id )->wp_id, 'a second click opens the same' );

		$post = self::factory()->post->create( array( 'post_name' => 'hello', 'post_title' => 'Hello' ) );
		$this->assertSame( 'single-post-hello', $this->call( 'create_own_template', 'post', $post )->slug );
		$this->assertInstanceOf( 'WP_Error', $this->call( 'create_own_template', 'archive', 'page' ), 'pages have no archive' );
		$this->assertInstanceOf( 'WP_Error', $this->call( 'create_own_template', 'single', 'page' ), 'pages are left to Pro' );
	}

	/**
	 * Posts link to the template of the blog page, which depends on the reading settings.
	 */
	public function testPostsArchive() {
		$this->assertSame( array( 'front-page', 'home', 'index' ), $this->call( 'archive_template_hierarchy', 'post' ), 'latest posts on the front page' );

		update_option( 'show_on_front', 'page' );
		$this->assertSame( array(), $this->call( 'archive_template_hierarchy', 'post' ), 'no blog page' );
		$this->assertStringContainsString( 'No page for posts is set (<a href="http://example.org/wp-admin/options-reading.php">', $this->form() );

		update_option( 'page_for_posts', self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		$this->assertSame( array( 'home', 'index' ), $this->call( 'archive_template_hierarchy', 'post' ) );
		$this->assertStringNotContainsString( 'No page for posts is set', $this->form() );

		$this->enable( array( 'archives' => array( 'post' ), 'links' => array( 'edit', 'create' ) ) );
		set_current_screen( 'edit' );
		$views = $this->call( 'archive_template_view', array() );
		$this->assertStringContainsString( '//home&', $views['cpwp_archive_edit_template'] );
		$this->assertArrayNotHasKey( 'cpwp_archive_create_template', $views, 'the theme has home' );
		set_current_screen( 'front' );
	}

	/**
	 * The default single template of posts, above the post list.
	 */
	public function testSingleTemplate() {
		$this->assertSame( array( 'single-post', 'single', 'singular', 'index' ), $this->call( 'template_target', 'single', 'post' )['hierarchy'] );
		$this->assertNull( $this->call( 'template_target', 'single', 'page' ) );

		$this->enable( array( 'singles' => array( 'post' ), 'links' => array( 'edit', 'create' ) ) );
		set_current_screen( 'edit' );
		$views = $this->call( 'archive_template_view', array() );
		$this->assertSame( array( 'cpwp_single_edit_template' ), array_keys( $views ), 'single is the single template of posts' );
		$this->assertStringContainsString( 'Default single template (', $views['cpwp_single_edit_template'] );
		set_current_screen( 'front' );
	}
}
