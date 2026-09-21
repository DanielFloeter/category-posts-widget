<?php

define( 'NS', 'categoryPosts' );

/**
 *  Normalize html for comparison by removing white space between tags, and leading/ending space
 *
 *  @param string $string The html to normalize.
 *  @return string A Normalized string.
 */
function removeSpaceBetweenTags( $string ) {
	$string = preg_replace( '~\s+~', ' ', $string ); // collapse spaces the way html handles it.
	return trim( preg_replace( '~>\s*<~', '><', $string ) );
}

/**
 *  Filter function to test the widget_title filter behaviour.
 *  Helps to check html escaping as a side job
 *
 *  @param string $title The title as passed to the filter.
 *  @return string whatever constant string
 */
function titleFilterTest( $title ) {
	return 'Me > You';
}

/**
 *  Stub used by testGetCSSRules() to fake the twentyseventeen theme's
 *  setup hook being present, so is_theme_active-style detection code
 *  (if any) has something to find.
 *
 *  Declared here, at file scope, guarded by function_exists() rather than
 *  inline inside the test method: a bare `function twentyseventeen_setup(){}`
 *  defined inside a test method body is only safe as long as that method
 *  runs at most once per process. Once testGetCSSRules() became a
 *  @dataProvider-driven method (so its body runs once per data set), an
 *  inline declaration would fatal with "Cannot redeclare" on the second run.
 */
if ( ! function_exists( 'twentyseventeen_setup' ) ) {
	function twentyseventeen_setup() {}
}

/**
 *  Add a file as an attachment.
 *
 *  @param string $filename The path of the file to add as an attachment.
 *  @return int the ID of the new attachment
 */
function _make_attachment( $filename ) {

	$contents = file_get_contents( $filename );

	$upload = wp_upload_bits( basename( $filename ), '', $contents );
	$type = '';
	if ( ! empty( $upload['type'] ) ) {
		$type = $upload['type'];
	} else {
		$mime = wp_check_filetype( $upload['file'] );
		if ( $mime ) {
			$type = $mime['type'];
		}
	}

	$attachment = array(
		'post_title'     => basename( $upload['file'] ),
		'post_content'   => '',
		'post_type'      => 'attachment',
		'post_mime_type' => $type,
		'guid'           => $upload['url'],
	);

	// Save the data.
	$id = wp_insert_attachment( $attachment, $upload['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );

	return $id;

}

class testWidgetFront extends WP_UnitTestCase {

	/**
	 *  Check that there are no errors when instance is new
	 */
	public function testNoSetting() {
		$className = NS . '\Widget';
		$widget = new $className();
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array()
		);
		$out = removeSpaceBetweenTags( ob_get_contents() );
		ob_end_clean();
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"></ul>', $out );
	}

	/**
	 *  Ensure block load-more IDs resolve to the stored block settings.
	 */
	public function testBlockLoadMoreIdNormalization() {
		$attributes = array( 'enableLoadmore' => true, 'num' => 2 );
		$block_id   = 'block-test-block-id';

		\categoryPosts\store_block_loadmore_settings( $block_id, $attributes );

		$this->assertSame( $attributes, \categoryPosts\get_block_loadmore_settings( $block_id ) );
		$this->assertSame( $attributes, \categoryPosts\get_block_loadmore_settings( \categoryPosts\normalize_block_loadmore_id( $block_id ) ) );
	}

	/**
	 *  Regression test for get_block_loadmore_id() (see #185, "Don't wrap
	 *  thumb with text with two or more Block instances"): the load-more ID
	 *  must now be derived from the `instanceId` block attribute rather than
	 *  from wp_unique_id(), because wp_unique_id() only counts up within a
	 *  single PHP request - it produced colliding/diverging IDs whenever a
	 *  block was rendered more than once per request or several instances
	 *  were each rendered via their own request (ServerSideRender previews,
	 *  excerpt generation, REST API output, full-page caching).
	 *
	 *  Content saved before the `instanceId` attribute existed has no such
	 *  key (or the editor has not yet filled it in), so that case must keep
	 *  falling back to wp_unique_id().
	 */
	public function testGetBlockLoadmoreId() {
		// Same instanceId -> same, stable ID, no matter how many times (or
		// via how many separate calls/requests) the same block instance is
		// rendered.
		$attributes = array( 'instanceId' => 7 );
		$this->assertSame( 'block-7', \categoryPosts\get_block_loadmore_id( $attributes ) );
		$this->assertSame(
			\categoryPosts\get_block_loadmore_id( $attributes ),
			\categoryPosts\get_block_loadmore_id( $attributes )
		);

		// A different instanceId gets a different (but equally stable) ID -
		// two instances of the block on the same page must not collide.
		$other_attributes = array( 'instanceId' => 8 );
		$this->assertSame( 'block-8', \categoryPosts\get_block_loadmore_id( $other_attributes ) );
		$this->assertNotSame(
			\categoryPosts\get_block_loadmore_id( $attributes ),
			\categoryPosts\get_block_loadmore_id( $other_attributes )
		);

		// Legacy content saved before the `instanceId` attribute existed (no
		// key at all) must keep falling back to wp_unique_id(): it still
		// gets *some* id, and - unlike the instanceId path above - a fresh,
		// different one on every call, since there is nothing stable to key
		// off of.
		$legacy_id_1 = \categoryPosts\get_block_loadmore_id( array() );
		$legacy_id_2 = \categoryPosts\get_block_loadmore_id( array() );
		$this->assertStringStartsWith( 'block-', $legacy_id_1 );
		$this->assertNotSame( $legacy_id_1, $legacy_id_2 );

		// Same fallback for a block whose editor-side effect has not run yet
		// (instanceId present but still empty).
		$empty_instance_id = \categoryPosts\get_block_loadmore_id( array( 'instanceId' => '' ) );
		$this->assertStringStartsWith( 'block-', $empty_instance_id );
		$this->assertNotSame( $legacy_id_1, $empty_instance_id );

		// Defensive: a non-array $attributes must not fatal and should also
		// fall back rather than e.g. emitting a PHP warning.
		$this->assertStringStartsWith( 'block-', \categoryPosts\get_block_loadmore_id( null ) );
	}

	/**
	 *  End-to-end regression test for #185 ("Don't wrap thumb with text with
	 *  two or more Block instances"): render two block instances - each with
	 *  its own `instanceId` and its own settings - in the same request, the
	 *  way the block editor does for ServerSideRender previews or a page with
	 *  more than one instance of the block.
	 *
	 *  Before load-more ids were derived from `instanceId`, two instances
	 *  could end up sharing one wp_unique_id()-generated id. That single id
	 *  is used as the key for two independent per-instance caches inside
	 *  render_category_posts_block(): the `cat_posts_block_<id>` transient
	 *  (store_block_loadmore_settings()) and the `static $styled` CSS-emission
	 *  guard ("emit the style island for this dom id only once"). A shared id
	 *  means a shared cache slot, so the second instance's own settings -
	 *  including things like "do not wrap thumbnail with overflowing text" -
	 *  silently never took effect: it re-used whatever the first instance had
	 *  already written there.
	 *
	 *  This test renders two instances that deliberately disagree on
	 *  `textDoNotWrapThumb` and asserts that each instance's own markup, own
	 *  CSS and own stored load-more settings stay independent.
	 */
	public function testRenderCategoryPostsBlockMultipleInstances() {
		$GLOBALS['before_title'] = '';
		$GLOBALS['after_title']  = '';

		$attributes_a = array(
			'instanceId'         => 9101,
			'template'           => "%title%\n\n%thumb%\n%excerpt%",
			'textDoNotWrapThumb' => true,
			'num'                => 3,
		);
		$attributes_b = array(
			'instanceId'         => 9102,
			'template'           => "%title%\n\n%thumb%\n%excerpt%",
			'textDoNotWrapThumb' => false,
			'num'                => 7,
		);

		$html_a = \categoryPosts\render_category_posts_block( $attributes_a );
		$html_b = \categoryPosts\render_category_posts_block( $attributes_b );

		// Each instance renders under its own, stable dom id derived from its
		// own instanceId - not a shared/colliding one.
		$this->assertStringContainsString( 'id="category-posts-block-9101"', $html_a );
		$this->assertStringContainsString( 'id="category-posts-block-9102"', $html_b );

		// Instance A turned "wrap thumbnail with overflowing text" off
		// (textDoNotWrapThumb = true -> no 'wrap_thumb' CSS rule); instance B
		// left it on (false -> the 'wrap_thumb' CSS rule IS present). If the
		// two instances shared a cache slot, both would show the same
		// (wrong, for one of them) answer here.
		$this->assertStringNotContainsString( '.cpwp-wrap-text p {display: inline;}', $html_a );
		$this->assertStringContainsString( '.cpwp-wrap-text p {display: inline;}', $html_b );

		// The stored load-more settings for each block id must be that
		// instance's own attributes, not overwritten by the other instance's
		// call.
		$this->assertSame( $attributes_a, \categoryPosts\get_block_loadmore_settings( 'block-9101' ) );
		$this->assertSame( $attributes_b, \categoryPosts\get_block_loadmore_settings( 'block-9102' ) );
	}

	/**
	 * Test that the block wrapper carries the block-supports classes.
	 *
	 * The markup has to go through render_block()/do_blocks(), not through
	 * render_category_posts_block() directly, because only then does core
	 * expose the block being rendered to get_block_wrapper_attributes().
	 */
	public function testBlockWrapperHasBlockSupportsClasses() {
		$GLOBALS['before_title'] = '';
		$GLOBALS['after_title']  = '';

		$html = do_blocks( '<!-- wp:tiptip/category-posts-block {"align":"center","instanceId":9201} /-->' );

		// The alignment picked in the toolbar ends up as a class on the
		// wrapper - a hardcoded wrapper silently dropped it.
		$this->assertStringContainsString( 'aligncenter', $html );

		// ... without losing the plugin's own id and class.
		$this->assertStringContainsString( 'id="category-posts-block-9201"', $html );
		$this->assertStringContainsString( 'category-posts-block', $html );

		// Without an alignment there is no align class.
		$html = do_blocks( '<!-- wp:tiptip/category-posts-block {"instanceId":9202} /-->' );
		$this->assertStringNotContainsString( 'aligncenter', $html );
		$this->assertStringContainsString( 'id="category-posts-block-9202"', $html );
	}

	/**
	 * Test that the block's thumbSymbols attribute reaches the CSS.
	 */
	public function testBlockThumbSymbolsAttribute() {
		$GLOBALS['before_title'] = '';
		$GLOBALS['after_title']  = '';

		$html = \categoryPosts\render_category_posts_block(
			array(
				'instanceId'   => 9301,
				'thumbSymbols' => true,
			)
		);
		$this->assertStringContainsString( 'object-fit: contain;', $html );

		$html = \categoryPosts\render_category_posts_block(
			array(
				'instanceId'   => 9302,
				'thumbSymbols' => false,
			)
		);
		$this->assertStringContainsString( 'object-fit: cover;', $html );
	}

	/**
	 *  Test the titleHTML method of the widget
	 */
	function testtitleHTML() {
		$className = NS . '\Widget';
		$widget = new $className();

		// test no setting, should return empty  string
		$out = $widget->titleHTML( '', '', array() );
		$this->assertEquals( 'Category Posts', $out );

		// test simple title
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'title' => 'test',
			)
		);
		$this->assertEquals( '<h3>test</h3>', $out );

		// test simple title with html escape
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'title' => 'te&st',
			)
		);
		$this->assertEquals( '<h3>te&#038;st</h3>', $out );

		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'title'      => 'te&st',
				'hide_title' => false,
			)
		);
		$this->assertEquals( '<h3>te&#038;st</h3>', $out );

		// test hide title
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'title'      => 'test',
				'hide_title' => true,
			)
		);
		$this->assertEquals( '', $out );

		// A hidden title must not leave an empty heading behind: with a
		// title_level set, add_heading_level() used to wrap the (empty)
		// title in an <h2></h2> even though nothing was rendered.
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'title'       => 'test',
				'hide_title'  => true,
				'title_level' => 'H2',
			)
		);
		$this->assertEquals( '', $out );

		// The heading level is still applied when the title is visible.
		$out = $widget->titleHTML(
			'', '', array(
				'title'       => 'test',
				'title_level' => 'H2',
			)
		);
		$this->assertEquals( '<h2 class="widget-title">test</h2>', $out );

		// test title as category name when title empty
		$cid = $this->factory->category->create( array( 'name' => 'test cat' ) );

		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'cat' => $cid,
			)
		);
		$this->assertEquals( '<h3>test cat</h3>', $out );

		// test title as category name when title is empty string
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'test' => '',
				'cat'  => $cid,
			)
		);
		$this->assertEquals( '<h3>test cat</h3>', $out );

		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'test'       => '',
				'hide_title' => false,
				'cat'        => $cid,
			)
		);
		$this->assertEquals( '<h3>test cat</h3>', $out );

		// test title as category name when title is empty string not displayed when tite is hidden
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'test'       => '',
				'cat'        => $cid,
				'hide_title' => true,
			)
		);
		$this->assertEquals( '', $out );

		// empty title with non existing category
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'test' => '',
				'cat'  => 10000,
			)
		);
		$this->assertEquals( '<h3>Category Posts</h3>', $out );

		// link to category with manual title
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'test'       => 'test',
				'cat'        => $cid,
				'title_link' => true,
			)
		);
		$this->assertEquals( '<h3><a href="http://example.org/?cat=' . $cid . '">test cat</a></h3>', $out );

		// link to category with no manual title
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'cat'        => $cid,
				'title_link' => true,
			)
		);
		$this->assertEquals( '<h3><a href="http://example.org/?cat=' . $cid . '">test cat</a></h3>', $out );

		// no link when it is not set to be
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'cat'        => $cid,
				'title_link' => false,
			)
		);
		$this->assertEquals( '<h3>test cat</h3>', $out );

		// link to not existing category will just not link unless link is provided.
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'title_link' => true,
			)
		);
		$this->assertEquals( '<h3><a href="http://example.org">Category Posts</a></h3>', $out );

		// test widget_title filtering
		add_filter( 'widget_title', 'titleFilterTest' );

		// widget_filte filter for link to category with no manual title
		// for a widget, the title should be escaped by the filtering code
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'cat'        => $cid,
				'title_link' => true,
			)
		);
		$this->assertEquals( '<h3><a href="http://example.org/?cat=' . $cid . '">Me > You</a></h3>', $out );

		// for a shortcode the filter is not applied
		 $out = $widget->titleHTML(
			 '<h3>', '</h3>', array(
				 'cat'          => $cid,
				 'title_link'   => true,
				 'is_shortcode' => true,
			 )
		 );
		$this->assertEquals( '<h3><a href="http://example.org/?cat=' . $cid . '">test cat</a></h3>', $out );

		// widget_filte filter fortitle without a link
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'cat' => $cid,
			)
		);
		$this->assertEquals( '<h3>Me > You</h3>', $out );

		remove_filter( 'widget_title', 'titleFilterTest' );

		// test all categories links point to the post page when appropriate.
		$page = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'title'       => 'test',
				'post_status' => 'publish',
			)
		);

		update_option( 'page_for_posts', $page );
		$out = $widget->titleHTML(
			'<h3>', '</h3>', array(
				'title_link' => true,
			)
		);
		$this->assertEquals( '<h3><a href="http://example.org/?page_id=' . $page . '">Category Posts</a></h3>', $out );
	}

	/**
	 *  Test the footerHTML method of the widget
	 */
	function testfooterHTML() {
		$className = NS . '\Widget';
		$widget = new $className();

		// no options set
		$out = $widget->footerHTML( array() );
		$this->assertEquals( '', $out );

		// option set to not do it
		$out = $widget->footerHTML(
			array(
				'footer_link' => false,
			)
		);
		$this->assertEquals( '', $out );

		// empty category.
		$out = $widget->footerHTML(
			array(
				'footer_link' => 'http://test.org',
			)
		);
		$this->assertEquals( '<a class="cat-post-footer-link" href="http://test.org">http://test.org</a>', $out );

		// bad category.
		$out = $widget->footerHTML(
			array(
				'footer_link' => 'http://test.org',
				'cat'         => 1000,
			)
		);
		$this->assertEquals( '<a class="cat-post-footer-link" href="http://test.org">http://test.org</a>', $out );

		// bad category no css.
		$out = $widget->footerHTML(
			array(
				'footer_link' => 'http://test.org',
				'cat'         => 1000,
				'disable_css' => true,
			)
		);
		$this->assertEquals( '<a class="cat-post-footer-link" href="http://test.org">http://test.org</a>', $out );

		// valid category.
		$cid = $this->factory->category->create(
			array(
				'name' => 'test cat',
			)
		);

		$out = $widget->footerHTML(
			array(
				'footer_link' => 'http://test.org',
				'cat'         => $cid,
			)
		);
		$this->assertEquals( '<a class="cat-post-footer-link" href="http://test.org">http://test.org</a>', $out );

		// valid category explicit css.
		$out = $widget->footerHTML(
			array(
				'footer_link' => 'http://test.org',
				'disable_css' => false,
				'cat'         => $cid,
			)
		);
		$this->assertEquals( '<a class="cat-post-footer-link" href="http://test.org">http://test.org</a>', $out );

		// valid category no css.
		$out = $widget->footerHTML(
			array(
				'footer_link' => 'http://test.org',
				'cat'         => $cid,
				'disable_css' => true,
			)
		);
		$this->assertEquals( '<a class="cat-post-footer-link" href="http://test.org">http://test.org</a>', $out );

		// test footer link for "all categories" when a posts page is set.
		$page = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'title'       => 'test',
				'post_status' => 'publish',
			)
		);

		update_option( 'page_for_posts', $page );
		$out = $widget->footerHTML(
			array(
				'footer_link' => 'http://test.org',
				'cat'         => 0,
			)
		);
		$this->assertEquals( '<a class="cat-post-footer-link" href="http://test.org">http://test.org</a>', $out );
	}

	/**
	 *  Test the excerpt_length_filter method of the widget
	 */
	function testexcerpt_length_filter() {
		$className = NS . '\Widget';
		$widget = new $className();

		// no setting
		$widget->instance = array();
		$this->assertEquals( 55, $widget->excerpt_length_filter( 55 ) );

		$widget->instance = array( 'excerpt_length' => 20 );
		$this->assertEquals( 20, $widget->excerpt_length_filter( 55 ) );
	}

	/**
	 *  Test the excerpt_more_filter method of the widget
	 */
	function testexcerpt_more_filter() {
		$className = NS . '\Widget';
		$widget = new $className();

		// generate a post to test with as the function expects to be called in a loop
		$pid = $this->factory->post->create(
			array(
				'title'       => 'test',
				'post_status' => 'publish',
			)
		);

		global $post;
		$post = get_post( $pid );
		setup_postdata( $post );

		$widget->instance['excerpt_more_text'] = 'text"';
		$this->assertEquals( ' <a class="cat-post-excerpt-more more-link" href="http://example.org/?p=' . $pid . '">text&quot;</a>', $widget->excerpt_more_filter( '' ) );
	}

	/**
	 *  Data provider for testQueryArgsOnArchivePage().
	 *
	 *  queryArgs() builds each key of its return value off a single instance
	 *  setting in isolation - there is no branch anywhere in it that depends on
	 *  a *combination* of two settings (the one exception, cat + no_cat_childs,
	 *  gets its own row below). Exercising every dimension one at a time against
	 *  the all-defaults baseline therefore gives the same bug-catching power as
	 *  the old 9-level-deep nested-loop cartesian product (previously ~774,000
	 *  assertions from a single test method) without inflating this into
	 *  anywhere near that many reported test cases.
	 */
	public function queryArgsProvider() {
		$base = array(
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		return array(
			'all defaults'                         => array( array(), $base ),

			'sort_by: date'                        => array( array( 'sort_by' => 'date' ), $base ),
			'sort_by: title'                       => array( array( 'sort_by' => 'title' ), array_merge( $base, array( 'orderby' => 'title' ) ) ),
			'sort_by: comment_count'                => array( array( 'sort_by' => 'comment_count' ), array_merge( $base, array( 'orderby' => 'comment_count' ) ) ),
			'sort_by: rand'                         => array( array( 'sort_by' => 'rand' ), array_merge( $base, array( 'orderby' => 'rand' ) ) ),
			'sort_by: invalid value falls back to date' => array( array( 'sort_by' => 'garbage' ), $base ),
			'sort_by: not set falls back to date'   => array( array( 'sort_by' => null ), $base ),

			'asc_sort_order: true'                 => array( array( 'asc_sort_order' => true ), array_merge( $base, array( 'order' => 'ASC' ) ) ),
			'asc_sort_order: truthy string'        => array( array( 'asc_sort_order' => 'whatever' ), array_merge( $base, array( 'order' => 'ASC' ) ) ),
			'asc_sort_order: false'                => array( array( 'asc_sort_order' => false ), $base ),
			'asc_sort_order: not set'               => array( array( 'asc_sort_order' => null ), $base ),

			'cat: numeric string'                  => array( array( 'cat' => '10' ), array_merge( $base, array( 'cat' => 10 ) ) ),
			'cat: int'                              => array( array( 'cat' => 7 ), array_merge( $base, array( 'cat' => 7 ) ) ),
			'cat: non-numeric string coerces to 0'  => array( array( 'cat' => 'fail' ), array_merge( $base, array( 'cat' => 0 ) ) ),
			'cat: not set'                          => array( array( 'cat' => null ), $base ),
			'cat + no_cat_childs uses category__in' => array(
				array( 'cat' => 7, 'no_cat_childs' => true ),
				array_merge( $base, array( 'category__in' => 7 ) ),
			),

			'num: numeric string'                  => array( array( 'num' => '10' ), array_merge( $base, array( 'showposts' => 10 ) ) ),
			'num: int'                              => array( array( 'num' => 7 ), array_merge( $base, array( 'showposts' => 7 ) ) ),
			'num: non-numeric string coerces to 0'  => array( array( 'num' => 'oops' ), array_merge( $base, array( 'showposts' => 0 ) ) ),
			'num: not set'                          => array( array( 'num' => null ), $base ),

			'offset: 1 has no effect'               => array( array( 'offset' => 1 ), $base ),
			'offset: 2'                             => array( array( 'offset' => 2 ), array_merge( $base, array( 'offset' => 1 ) ) ),
			'offset: 4'                             => array( array( 'offset' => 4 ), array_merge( $base, array( 'offset' => 3 ) ) ),
			'offset: not set'                       => array( array( 'offset' => null ), $base ),

			'hideNoThumb: true'                    => array(
				array( 'hideNoThumb' => true ),
				array_merge( $base, array( 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ) ) ),
			),
			'hideNoThumb: false'                    => array( array( 'hideNoThumb' => false ), $base ),
			'hideNoThumb: not set'                   => array( array( 'hideNoThumb' => null ), $base ),

			'status: publish'                       => array( array( 'status' => 'publish' ), array_merge( $base, array( 'post_status' => 'publish' ) ) ),
			'status: future'                        => array( array( 'status' => 'future' ), array_merge( $base, array( 'post_status' => 'future' ) ) ),
			'status: publish,future'                => array( array( 'status' => 'publish,future' ), array_merge( $base, array( 'post_status' => 'publish,future' ) ) ),
			'status: private'                       => array( array( 'status' => 'private' ), array_merge( $base, array( 'post_status' => 'private' ) ) ),
			'status: private,publish'               => array( array( 'status' => 'private,publish' ), array_merge( $base, array( 'post_status' => 'private,publish' ) ) ),
			'status: private,publish,future'        => array( array( 'status' => 'private,publish,future' ), array_merge( $base, array( 'post_status' => 'private,publish,future' ) ) ),
			'status: not set'                       => array( array( 'status' => null ), $base ),
		);
	}

	/**
	 *  Test the queryArgs method of the widget on an archive-type page.
	 *
	 *  @dataProvider queryArgsProvider
	 */
	public function testQueryArgsOnArchivePage( $instance, $expected ) {
		$className = NS . '\Widget';
		$widget = new $className();
		$this->go_to( '/' );
		$this->assertEquals( $expected, $widget->queryArgs( $instance ) );
	}

	/**
	 *  exclude_current_post is the one queryArgs() setting whose effect depends
	 *  on where the query runs (only on a singular page is there a "current
	 *  post" to exclude), so it gets its own test rather than a data provider row.
	 */
	public function testQueryArgsExcludeCurrentPost() {
		$className = NS . '\Widget';
		$widget = new $className();
		$pid = $this->factory->post->create(
			array(
				'title'       => 'test',
				'post_status' => 'publish',
			)
		);

		$base = array(
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		// On an archive page there is no "current post", so this setting has no effect.
		$this->go_to( '/' );
		$this->assertEquals( $base, $widget->queryArgs( array( 'exclude_current_post' => true ) ) );

		// On a single post page, the current post is excluded only when the setting is truthy.
		$this->go_to( '/?p=' . $pid );
		$this->assertEquals( $base, $widget->queryArgs( array( 'exclude_current_post' => false ) ) );
		$this->assertEquals(
			array_merge( $base, array( 'post__not_in' => array( $pid ) ) ),
			$widget->queryArgs( array( 'exclude_current_post' => true ) )
		);
		$this->assertEquals(
			array_merge( $base, array( 'post__not_in' => array( $pid ) ) ),
			$widget->queryArgs( array( 'exclude_current_post' => 'whatever' ) )
		);
	}

	/**
	 *  Differences between version 4.3 4.4 and 4.5 require manipulation of expected results for thunmbnail testing
	 *
	 *  @param string    $expected The expected result 4.5 format
	 *  @param WP_Widget $widget the widget to use for testing
	 *  $param int|array $size the size of requested thumbnail
	 */
	function postThumbnailTester( $expected, $widget, $size ) {
		global $wp_version;

		$compare_to = $widget->the_post_thumbnail( $size );
		$compare_to = preg_replace( '/ alt="[^"]*"/', '', $compare_to );

		if ( version_compare( $wp_version, '4.5', '<' ) ) { // size_WxH and size-{size name} were added to image at 4.5, remove if exist
		}
		if ( version_compare( $wp_version, '4.4', '<' ) ) { // srcset and sizes were added in 4.4
			$expected = preg_replace( '/ size-\w*/', '', $expected );
			$expected = preg_replace( '/ srcset="[^"]*"/', '', $expected );
			$expected = preg_replace( '/ sizes="[^"]*"/', '', $expected );
		}
		$this->assertEquals( $expected, $compare_to );
	}

	/**
	 * Test the post_thumbnail method of the widget
	 *
	 * @since 4.6
	 */
	public function test_the_post_thumbnail() {

		global $wp_version;

		$className = NS . '\Widget';
		$widget = new $className();

		// clean upload dir for consistent file names.
		$dir = wp_upload_dir();
		$dirurl = $dir['url'];
		$dir = $dir['path'];
		array_map( 'unlink', glob( $dir . '/*' ) );

		// 1) use image size: 640x480
		$pid = $this->factory->post->create(
			array(
				'title'       => 'canola',
				'post_status' => 'publish',
			)
		);
		$thumbnail_id = _make_attachment( DIR_TESTDATA . '/images/canola.jpg' ); // wp-content\plugins\4.5\tests\phpunit\includes\..\data\images\canola.jpg.
		set_post_thumbnail( $pid, $thumbnail_id );

		global $post;
		$post = get_post( $pid );
		setup_postdata( $post );

		// test no thumb width and height, should get same html
		// there are slight differences with how versions handle "empty" values.
		if ( version_compare( $wp_version, '4.5', '<' ) ) {
			$this->postThumbnailTester( '<img width="640" height="480" src="' . $dirurl . '/canola.jpg" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" srcset="' . $dirurl . '/canola-300x225.jpg 300w, ' . $dirurl . '/canola.jpg 640w" sizes="(max-width: 640px) 100vw, 640px" />', $widget, array() );
		} else {
			$this->postThumbnailTester( '<span class="cat-post-crop "><img   src="' . $dirurl . '/canola.jpg" class="attachment-full size-full wp-post-image" data-cat-posts-width="0" data-cat-posts-height="0" decoding="async" loading="lazy" srcset="' . $dirurl . '/canola.jpg 640w, ' . $dirurl . '/canola-300x225.jpg 300w" sizes="auto, (max-width: 150px) 100vw, 150px" /></span>', $widget, array() );
		}

		$this->postThumbnailTester( '<span class="cat-post-crop "><img   src="' . $dirurl . '/canola.jpg" class="attachment-full size-full wp-post-image" data-cat-posts-width="0" data-cat-posts-height="0" decoding="async" loading="lazy" srcset="' . $dirurl . '/canola.jpg 640w, ' . $dirurl . '/canola-300x225.jpg 300w" sizes="auto, (max-width: 10px) 100vw, 10px" /></span>', $widget, array( 10, '' ) );

		$this->postThumbnailTester( '<span class="cat-post-crop "><img   src="' . $dirurl . '/canola.jpg" class="attachment-full size-full wp-post-image" data-cat-posts-width="0" data-cat-posts-height="0" decoding="async" loading="lazy" srcset="' . $dirurl . '/canola.jpg 640w, ' . $dirurl . '/canola-300x225.jpg 300w" sizes="auto, (max-width: 640px) 100vw, 640px" /></span>', $widget, array( '', 10 ) );

		// equal to min thumb size. no manipulation needed.
		$widget->instance = array(
			'thumb_h' => 150,
			'thumb_w' => 150,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="150" height="150" src="' . $dirurl . '/canola-150x150.jpg" class="attachment-thumbnail size-thumbnail wp-post-image" data-cat-posts-width="150" data-cat-posts-height="150" decoding="async" loading="lazy" /></span>',
			$widget, array( 150, 150 )
		);

		$widget->instance = array(
			'thumb_h' => 200,
			'thumb_w' => 200,
		);

		if ( version_compare( $wp_version, '4.6', '<' ) ) {
			$this->postThumbnailTester(
				'<img width="200" height="150" src="' . $dirurl . '/canola-300x225.jpg" class="attachment-200x200 size-200x200 wp-post-image" srcset="' . $dirurl . '/canola-300x225.jpg 300w, ' . $dirurl . '/canola.jpg 640w" sizes="(max-width: 200px) 100vw, 200px" />',
				$widget, array( 200, 200 )
			);
		} else {
			$this->postThumbnailTester(
				'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/canola-300x225.jpg" class="attachment-medium size-medium wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" srcset="' . $dirurl . '/canola-300x225.jpg 300w, ' . $dirurl . '/canola.jpg 640w" sizes="auto, (max-width: 200px) 100vw, 200px" /></span>',
				$widget, array( 200, 200 )
			);
		}

		// Use with "use_css_cropping".
		$widget->instance = array(
			'thumb_h'          => 150,
			'thumb_w'          => 150,
			'use_css_cropping' => true,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="150" height="150" src="' . $dirurl . '/canola-150x150.jpg" class="attachment-thumbnail size-thumbnail wp-post-image" data-cat-posts-width="150" data-cat-posts-height="150" decoding="async" loading="lazy" /></span>',
			$widget, array( 150, 150 )
		);

		$widget->instance = array(
			'thumb_h'          => 200,
			'thumb_w'          => 200,
			'use_css_cropping' => true,
		);
		if ( version_compare( $wp_version, '4.6', '<' ) ) {
			$this->postThumbnailTester(
				'<span style="width:200px;height:200px;"><img style="margin-left:-33.333333333333px;height:200px;clip:rect(auto,233.33333333333px,auto,33.333333333333px);width:auto;max-width:initial;" width=\'266.66666666667\' height=\'200\' src="' . $dirurl . '/canola-300x225.jpg" class="attachment-200x200 size-200x200 wp-post-image" srcset="' . $dirurl . '/canola-300x225.jpg 300w, ' . $dirurl . '/canola.jpg 3w" sizes="(max-width: 266.66666666667px) 100vw, 266.66666666667px" /></span>',
				$widget, array( 200, 200 )
			);
		} else {
			$this->postThumbnailTester(
				'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/canola-300x225.jpg" class="attachment-medium size-medium wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" srcset="' . $dirurl . '/canola-300x225.jpg 300w, ' . $dirurl . '/canola.jpg 640w" sizes="auto, (max-width: 200px) 100vw, 200px" /></span>',
				$widget, array( 200, 200 )
			);
		}

		// 2.) use smaller image as media -> settings thumbnail_size, image size: 50x50
		delete_post_thumbnail( $pid );
		$pid = $this->factory->post->create(
			array(
				'title'       => 'test-image',
				'post_status' => 'publish',
			)
		);
		$thumbnail_id = _make_attachment( DIR_TESTDATA . '/images/test-image.jpg' ); // wp-content\plugins\4.5\tests\phpunit\includes\..\data\images\test-image.jpg
		set_post_thumbnail( $pid, $thumbnail_id );

		$post = get_post( $pid );
		setup_postdata( $post );

		$widget->instance = array( 'use_css_cropping' => false );

		$widget->instance = array(
			'thumb_h' => 150,
			'thumb_w' => 150,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="150" height="150" src="' . $dirurl . '/test-image.jpg" class="attachment-full size-full wp-post-image" data-cat-posts-width="150" data-cat-posts-height="150" decoding="async" loading="lazy" /></span>',
			$widget, array( 150, 150 )
		);

		$widget->instance = array(
			'thumb_h' => 200,
			'thumb_w' => 200,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/test-image.jpg" class="attachment-full size-full wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" /></span>',
			$widget, array( 200, 200 )
		);

		// Use with "use_css_cropping".
		$widget->instance = array(
			'thumb_h'          => 150,
			'thumb_w'          => 150,
			'use_css_cropping' => true,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="150" height="150" src="' . $dirurl . '/test-image.jpg" class="attachment-full size-full wp-post-image" data-cat-posts-width="150" data-cat-posts-height="150" decoding="async" loading="lazy" /></span>',
			$widget, array( 150, 150 )
		);

		$widget->instance = array(
			'thumb_h'          => 200,
			'thumb_w'          => 200,
			'use_css_cropping' => true,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/test-image.jpg" class="attachment-full size-full wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" /></span>',
			$widget, array( 200, 200 )
		);

		// 3.) use bigger image as media -> settings large_size, image size: 1920x1080
		delete_post_thumbnail( $pid );
		$pid = $this->factory->post->create(
			array(
				'title'       => '33772',
				'post_status' => 'publish',
			)
		);
		$thumbnail_id = _make_attachment( DIR_TESTDATA . '/images/33772.jpg' ); // wp-content\plugins\4.5\tests\phpunit\includes\..\data\images\33772.jpg.
		set_post_thumbnail( $pid, $thumbnail_id );

		$post = get_post( $pid );
		setup_postdata( $post );

		$widget->instance = array( 'use_css_cropping' => false );

		$widget->instance = array(
			'thumb_h' => 150,
			'thumb_w' => 150,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="150" height="150" src="' . $dirurl . '/33772-150x150.jpg" class="attachment-thumbnail size-thumbnail wp-post-image" data-cat-posts-width="150" data-cat-posts-height="150" decoding="async" loading="lazy" /></span>',
			$widget, array( 150, 150 )
		);

		$widget->instance = array(
			'thumb_h' => 200,
			'thumb_w' => 200,
		);
		if ( version_compare( $wp_version, '4.5', '<' ) ) {
			$this->postThumbnailTester( '<img width="825" height="510" src="' . $dirurl . '/33772-825x510.jpg" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" />', $widget, array() );
		} elseif ( version_compare( $wp_version, '4.6', '<' ) ) {
			$this->postThumbnailTester(
				'<img width="200" height="113" src="' . $dirurl . '/33772-768x432.jpg" class="attachment-200x200 size-200x200 wp-post-image" srcset="' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-1024x576.jpg 1024w" sizes="(max-width: 200px) 100vw, 200px" />',
				$widget, array( 200, 200 )
			);
		} else {
			$this->postThumbnailTester(
				'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/33772-1024x576.jpg" class="attachment-large size-large wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" srcset="' . $dirurl . '/33772-1024x576.jpg 1024w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-1536x864.jpg 1536w, ' . $dirurl . '/33772.jpg 1920w" sizes="auto, (max-width: 200px) 100vw, 200px" /></span>',
				$widget, array( 200, 200 )
			);
		}

		// Use with "use_css_cropping"
		$widget->instance = array(
			'thumb_h'          => 150,
			'thumb_w'          => 150,
			'use_css_cropping' => true,
		);
		$this->postThumbnailTester(
			'<span class="cat-post-crop "><img width="150" height="150" src="' . $dirurl . '/33772-150x150.jpg" class="attachment-thumbnail size-thumbnail wp-post-image" data-cat-posts-width="150" data-cat-posts-height="150" decoding="async" loading="lazy" /></span>',
			$widget, array( 150, 150 )
		);

		$widget->instance = array(
			'thumb_h'          => 200,
			'thumb_w'          => 200,
			'use_css_cropping' => true,
		);
		if ( version_compare( $wp_version, '4.5', '<' ) ) {
			$this->postThumbnailTester(
				'<span style="width:200px;height:200px;"><img style="margin-left:-77.777777777778px;height:200px;clip:rect(auto,277.77777777778px,auto,77.777777777778px);width:auto;max-width:initial;" width=\'355.55555555556\' height=\'200\' src="' . $dirurl . '/33772-768x432.jpg" class="attachment-200x200 size-200x200 wp-post-image" /></span>',
				$widget, array( 200, 200 )
			);
		} elseif ( version_compare( $wp_version, '4.6', '<' ) ) {
			$this->postThumbnailTester(
				'<span style="width:200px;height:200px;"><img style="margin-left:-77.777777777778px;height:200px;clip:rect(auto,277.77777777778px,auto,77.777777777778px);width:auto;max-width:initial;" width=\'355.55555555556\' height=\'200\' src="' . $dirurl . '/33772-768x432.jpg" class="attachment-200x200 size-200x200 wp-post-image" srcset="' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-1024x576.jpg 1024w" sizes="(max-width: 355.55555555556px) 100vw, 355.55555555556px" /></span>',
				$widget, array( 200, 200 )
			);
		} else {
			$this->postThumbnailTester(
				'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/33772-1024x576.jpg" class="attachment-large size-large wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" srcset="' . $dirurl . '/33772-1024x576.jpg 1024w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-1536x864.jpg 1536w, ' . $dirurl . '/33772.jpg 1920w" sizes="auto, (max-width: 200px) 100vw, 200px" /></span>',
				$widget, array( 200, 200 )
			);
		}

		// test default thumb.
		$pidd = $this->factory->post->create(
			array(
				'title'       => 'default thumb',
				'post_status' => 'publish',
			)
		);
		$post = get_post( $pidd );
		setup_postdata( $post );

		$widget->instance = array(
			'thumb_h'            => 200,
			'thumb_w'            => 200,
			'use_css_cropping'   => true,
			'default_thunmbnail' => $thumbnail_id,
		);
		if ( version_compare( $wp_version, '4.5', '<' ) ) {
			$this->postThumbnailTester(
				'<span style="width:200px;height:200px;"><img style="margin-left:-77.777777777778px;height:200px;clip:rect(auto,277.77777777778px,auto,77.777777777778px);width:auto;max-width:initial;" width=\'355.55555555556\' height=\'200\' src="' . $dirurl . '/33772-768x432.jpg" class="attachment-200x200 size-200x200 wp-post-image" /></span>',
				$widget, array( 200, 200 )
			);
		} elseif ( version_compare( $wp_version, '4.6', '<' ) ) {
			$this->postThumbnailTester(
				'<span style="width:200px;height:200px;"><img style="margin-left:-77.777777777778px;height:200px;clip:rect(auto,277.77777777778px,auto,77.777777777778px);width:auto;max-width:initial;" width=\'355.55555555556\' height=\'200\' src="' . $dirurl . '/33772-768x432.jpg" class="attachment-200x200 size-200x200 wp-post-image" srcset="' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-1024x576.jpg 1024w" sizes="(max-width: 355.55555555556px) 100vw, 355.55555555556px" /></span>',
				$widget, array( 200, 200 )
			);
		} else {
			$this->postThumbnailTester(
				'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/33772-1024x576.jpg" class="attachment-large size-large wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" srcset="' . $dirurl . '/33772-1024x576.jpg 1024w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-1536x864.jpg 1536w, ' . $dirurl . '/33772.jpg 1920w" sizes="auto, (max-width: 200px) 100vw, 200px" /></span>',
				$widget, array( 200, 200 )
			);
		}

		$widget->instance = array(
			'thumb_h'            => 200,
			'thumb_w'            => 200,
			'default_thunmbnail' => $thumbnail_id,
		);
		if ( version_compare( $wp_version, '4.5', '<' ) ) {
			$this->postThumbnailTester( '<img width="825" height="510" src="' . $dirurl . '/33772-825x510.jpg" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" />', $widget, array() );
		} elseif ( version_compare( $wp_version, '4.6', '<' ) ) {
			$this->postThumbnailTester(
				'<img width="200" height="113" src="' . $dirurl . '/33772-768x432.jpg" class="attachment-200x200 size-200x200 wp-post-image" srcset="' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-1024x576.jpg 1024w" sizes="(max-width: 200px) 100vw, 200px" />',
				$widget, array( 200, 200 )
			);
		} else {
			$this->postThumbnailTester(
				'<span class="cat-post-crop "><img width="200" height="200" src="' . $dirurl . '/33772-1024x576.jpg" class="attachment-large size-large wp-post-image" data-cat-posts-width="200" data-cat-posts-height="200" decoding="async" loading="lazy" srcset="' . $dirurl . '/33772-1024x576.jpg 1024w, ' . $dirurl . '/33772-300x169.jpg 300w, ' . $dirurl . '/33772-768x432.jpg 768w, ' . $dirurl . '/33772-1536x864.jpg 1536w, ' . $dirurl . '/33772.jpg 1920w" sizes="auto, (max-width: 200px) 100vw, 200px" /></span>',
				$widget, array( 200, 200 )
			);
		}
	}

	/**
	 * Test that the global post variable is reset after widget loop.
	 */
	public function testLoopReset() {
		$className = NS . '\Widget';
		$widget = new $className();

		$cid = get_option( 'default_category' );

		$pid = $this->factory->post->create(
			array(
				'title'        => 'test',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		$pid2 = $this->factory->post->create(
			array(
				'title'        => 'test2',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		$this->go_to( '/' );
		$tempid = get_the_ID();
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat' => $cid,
				'num' => 10,
			)
		);
		ob_end_clean();
		$this->assertEquals( $tempid, get_the_ID() );

		$this->go_to( '/?p=' . $pid );
		$tempid = get_the_ID();
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat' => $cid,
				'num' => 10,
			)
		);
		ob_end_clean();
		$this->assertEquals( $tempid, get_the_ID() );
	}

	/**
	 *  Helper function to test that excerptmore filter is triggered
	 */
	function excerptMoreFilter( $v ) {
		return '[more test]';
	}

	/**
	 *  Helper function to test that excerpt length filter is triggered
	 */
	function excerptLengthFilter( $v ) {
		return 2;
	}

	/**
	 * Test that the excerpt filters are removed after the loop.
	 */
	public function testExcerptFilters() {
		$className = NS . '\Widget';
		$widget = new $className();

		$cid = get_option( 'default_category' );

		$pid = $this->factory->post->create(
			array(
				'post_title'   => 'test',
				'post_status'  => 'publish',
				'post_content' => 'more then one word',
				'post_excerpt' => '',
			)
		);

		add_filter( 'excerpt_more', array( $this, 'excerptMoreFilter' ), 10 );
		add_filter( 'excerpt_length', array( $this, 'excerptLengthFilter' ), 10 );

		// Test filters not applied when excerpt off.
		$this->go_to( '/?p=' . $pid );
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'            => $cid,
				'num'            => 10,
				'excerpt_length' => 1,
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a></div></li></ul>', $o );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'             => $cid,
				'num'             => 10,
				'excerpt_length'  => 1,
				'excerpt_filters' => true,
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a></div></li></ul>', $o );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'            => $cid,
				'num'            => 10,
				'excerpt'        => false,
				'excerpt_length' => 1,
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a></div></li></ul>', $o );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'             => $cid,
				'num'             => 10,
				'excerpt'         => false,
				'excerpt_length'  => 1,
				'excerpt_filters' => true,
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a></div></li></ul>', $o );

		// test excerpt length filter.
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'            => $cid,
				'num'            => 10,
				'excerpt'        => true,
				'excerpt_length' => 1,
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div class="cpwp-wrap-text-stage cpwp-wrap-text"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a><p class="cpwp-excerpt-text">more <a class="cat-post-excerpt-more" href="http://example.org/?p=' . $pid . '" title="Continue reading test">[&hellip;]</a></p></div></div></li></ul>', $o );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'             => $cid,
				'num'             => 10,
				'excerpt'         => true,
				'excerpt_length'  => 1,
				'excerpt_filters' => true,
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div class="cpwp-wrap-text-stage cpwp-wrap-text"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a><p class="cpwp-excerpt-text">more then[more test]</p></div></div></li></ul>', $o );

		// test excerpt more filter.
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'               => $cid,
				'num'               => 10,
				'excerpt'           => true,
				'excerpt_length'    => 1,
				'excerpt_more_text' => 'blabla',
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div class="cpwp-wrap-text-stage cpwp-wrap-text"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a><p class="cpwp-excerpt-text">more <a class="cat-post-excerpt-more" href="http://example.org/?p=' . $pid . '" title="Continue reading test">blabla</a></p></div></div></li></ul>', $o );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'               => $cid,
				'num'               => 10,
				'excerpt'           => true,
				'excerpt_length'    => 1,
				'excerpt_more_text' => 'blabla',
				'excerpt_filters'   => true,
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div class="cpwp-wrap-text-stage cpwp-wrap-text"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a><p class="cpwp-excerpt-text">more then[more test]</p></div></div></li></ul>', $o );

		remove_filter( 'excerpt_more', array( $this, 'excerptMoreFilter' ), 10 );
		remove_filter( 'excerpt_length', array( $this, 'excerptLengthFilter' ), 10 );

	}

	/**
	 * Test that the excerpt filters are removed after the loop.
	 */
	public function testExcerptFilterRemove() {
		$className = NS . '\Widget';
		$widget = new $className();

		$cid = get_option( 'default_category' );

		$pid = $this->factory->post->create(
			array(
				'title'        => 'test',
				'post_status'  => 'publish',
				'post_content' => 'more then one word',
				'post_excerpt' => '',
			)
		);

		add_filter( 'excerpt_more', array( $this, 'excerptMoreFilter' ), 10 );
		add_filter( 'excerpt_length', array( $this, 'excerptLengthFilter' ), 10 );

		$this->go_to( '/?p=' . $pid );
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'               => $cid,
				'num'               => 10,
				'excerpt'           => true,
				'excerpt_length'    => 1,
				'excerpt_more_text' => 'blabla',
			)
		);
		ob_end_clean();
		ob_start();
		the_excerpt();
		$excerpt = trim( ob_get_clean() );
		$this->assertEquals( '<p>more then[more test]</p>', $excerpt );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'               => $cid,
				'num'               => 10,
				'excerpt'           => true,
				'excerpt_length'    => 1,
				'excerpt_more_text' => 'blabla',
				'excerpt_filters'   => true,
			)
		);
		ob_end_clean();
		ob_start();
		the_excerpt();
		$excerpt = trim( ob_get_clean() );
		$this->assertEquals( '<p>more then[more test]</p>', $excerpt );

		remove_filter( 'excerpt_more', array( $this, 'excerptMoreFilter' ), 10 );
		remove_filter( 'excerpt_length', array( $this, 'excerptLengthFilter' ), 10 );

	}

	/**
	 *  test that the internal excerpt genertion works
	 */
	function testInternalExcerptGeneration() {
		$className = NS . '\Widget';
		$widget = new $className();

		$cid = get_option( 'default_category' );

		$pid = $this->factory->post->create(
			array(
				'post_title'   => 'test',
				'post_status'  => 'publish',
				'post_content' => 'more then one word',
				'post_excerpt' => '',
			)
		);

		$this->go_to( '/?p=' . $pid );

		// test length default.
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'             => $cid,
				'num'             => 10,
				'excerpt'         => true,
				'excerpt_filters' => '',
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div class="cpwp-wrap-text-stage cpwp-wrap-text"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a><p class="cpwp-excerpt-text">more then one word</p></div></div></li></ul>', $o );

		// test more text default.
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'             => $cid,
				'num'             => 10,
				'excerpt'         => true,
				'excerpt_length'  => 1,
				'excerpt_filters' => '',
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div class="cpwp-wrap-text-stage cpwp-wrap-text"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a><p class="cpwp-excerpt-text">more <a class="cat-post-excerpt-more" href="http://example.org/?p=' . $pid . '" title="Continue reading test">[&hellip;]</a></p></div></div></li></ul>', $o );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			), array(
				'cat'               => $cid,
				'num'               => 10,
				'excerpt'           => true,
				'excerpt_length'    => 1,
				'excerpt_more_text' => 'blabla',
				'excerpt_filters'   => '',
			)
		);
		$o = removeSpaceBetweenTags( ob_get_clean() );
		$this->assertEquals( 'Category Posts<ul id="category-posts--internal" class="category-posts-internal"><li class="cat-post-item cat-post-current"><div class="cpwp-wrap-text-stage cpwp-wrap-text"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a><p class="cpwp-excerpt-text">more <a class="cat-post-excerpt-more" href="http://example.org/?p=' . $pid . '" title="Continue reading test">blabla</a></p></div></div></li></ul>', $o );

	}
}

class testWidgetAdmin extends WP_UnitTestCase {

	public function testformTitlePanel() {
		$className = NS . '\Widget';
		$widget = new $className();

		// no setting.
		ob_start();
		$widget->formTitlePanel( array() );
		$out = removeSpaceBetweenTags( ob_get_contents() );
		ob_end_clean();
		$this->assertEquals( '<h4 data-panel="title">Title</h4><div class="cpwp_ident"><p class="categoryPosts-hide_title"><label class="checkbox" for="widget-category-posts--hide_title"><input id="widget-category-posts--hide_title" name="widget-category-posts[][hide_title]" type="checkbox" /> Hide title</label></p><div class="categoryposts-data-panel-title-settings" ><p class="categoryPosts-title"><label for="widget-category-posts--title"> Title: <input placeholder="" id="widget-category-posts--title" name="widget-category-posts[][title]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_link" style="display:none"><label class="checkbox" for="widget-category-posts--title_link"><input id="widget-category-posts--title_link" name="widget-category-posts[][title_link]" type="checkbox" /> Make block title link</label></p><p class="categoryPosts-title_link_url"><label for="widget-category-posts--title_link_url"> Title link URL: <input placeholder="" id="widget-category-posts--title_link_url" name="widget-category-posts[][title_link_url]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_level"><label for="widget-category-posts--title_level">Heading Level: <a href="#" class="dashicons toggle-title-level-help dashicons-paperclip"><span class="screen-reader-text">Show title level help</span></a></label><span class="cpwp-right"><input class="Initial button" id="widget-category-posts--title_levelInitial" name="widget-category-posts[][title_level]" value="Initial" type="radio" /><input class="H1 button" id="widget-category-posts--title_levelH1" name="widget-category-posts[][title_level]" value="H1" type="radio" /><input class="H2 button" id="widget-category-posts--title_levelH2" name="widget-category-posts[][title_level]" value="H2" type="radio" /><input class="H3 button" id="widget-category-posts--title_levelH3" name="widget-category-posts[][title_level]" value="H3" type="radio" /><input class="H4 button" id="widget-category-posts--title_levelH4" name="widget-category-posts[][title_level]" value="H4" type="radio" /><input class="H5 button" id="widget-category-posts--title_levelH5" name="widget-category-posts[][title_level]" value="H5" type="radio" /><input class="H6 button" id="widget-category-posts--title_levelH6" name="widget-category-posts[][title_level]" value="H6" type="radio" /></span></p><div class="cat-post-title-level-help" style="display:none;"><p>Also, try \'Disable Theme\'s styles\' on General tab to avoid rendering commonly used CSS classes such here widget-title, which often used in Themes to write their CSS selectors and may affect the design. </p></div></div></div>', $out );

		// title.
		ob_start();
		$widget->formTitlePanel( array( 'title' => 'title <> me' ) );
		$out = removeSpaceBetweenTags( ob_get_contents() );
		ob_end_clean();
		$this->assertEquals( '<h4 data-panel="title">Title</h4><div class="cpwp_ident"><p class="categoryPosts-hide_title"><label class="checkbox" for="widget-category-posts--hide_title"><input id="widget-category-posts--hide_title" name="widget-category-posts[][hide_title]" type="checkbox" /> Hide title</label></p><div class="categoryposts-data-panel-title-settings" ><p class="categoryPosts-title"><label for="widget-category-posts--title"> Title: <input placeholder="" id="widget-category-posts--title" name="widget-category-posts[][title]" type="text" value="title &lt;&gt; me" autocomplete="off"/></label></p><p class="categoryPosts-title_link" style="display:none"><label class="checkbox" for="widget-category-posts--title_link"><input id="widget-category-posts--title_link" name="widget-category-posts[][title_link]" type="checkbox" /> Make block title link</label></p><p class="categoryPosts-title_link_url"><label for="widget-category-posts--title_link_url"> Title link URL: <input placeholder="" id="widget-category-posts--title_link_url" name="widget-category-posts[][title_link_url]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_level"><label for="widget-category-posts--title_level">Heading Level: <a href="#" class="dashicons toggle-title-level-help dashicons-paperclip"><span class="screen-reader-text">Show title level help</span></a></label><span class="cpwp-right"><input class="Initial button" id="widget-category-posts--title_levelInitial" name="widget-category-posts[][title_level]" value="Initial" type="radio" /><input class="H1 button" id="widget-category-posts--title_levelH1" name="widget-category-posts[][title_level]" value="H1" type="radio" /><input class="H2 button" id="widget-category-posts--title_levelH2" name="widget-category-posts[][title_level]" value="H2" type="radio" /><input class="H3 button" id="widget-category-posts--title_levelH3" name="widget-category-posts[][title_level]" value="H3" type="radio" /><input class="H4 button" id="widget-category-posts--title_levelH4" name="widget-category-posts[][title_level]" value="H4" type="radio" /><input class="H5 button" id="widget-category-posts--title_levelH5" name="widget-category-posts[][title_level]" value="H5" type="radio" /><input class="H6 button" id="widget-category-posts--title_levelH6" name="widget-category-posts[][title_level]" value="H6" type="radio" /></span></p><div class="cat-post-title-level-help" style="display:none;"><p>Also, try \'Disable Theme\'s styles\' on General tab to avoid rendering commonly used CSS classes such here widget-title, which often used in Themes to write their CSS selectors and may affect the design. </p></div></div></div>', $out );

		// title and link.
		ob_start();
		$widget->formTitlePanel(
			array(
				'title'      => 'title <> me',
				'title_link' => true,
			)
		);
		$out = removeSpaceBetweenTags( ob_get_contents() );
		ob_end_clean();
		$this->assertEquals( '<h4 data-panel="title">Title</h4><div class="cpwp_ident"><p class="categoryPosts-hide_title"><label class="checkbox" for="widget-category-posts--hide_title"><input id="widget-category-posts--hide_title" name="widget-category-posts[][hide_title]" type="checkbox" /> Hide title</label></p><div class="categoryposts-data-panel-title-settings" ><p class="categoryPosts-title"><label for="widget-category-posts--title"> Title: <input placeholder="" id="widget-category-posts--title" name="widget-category-posts[][title]" type="text" value="title &lt;&gt; me" autocomplete="off"/></label></p><p class="categoryPosts-title_link" style="display:none"><label class="checkbox" for="widget-category-posts--title_link"><input id="widget-category-posts--title_link" name="widget-category-posts[][title_link]" type="checkbox" checked=\'checked\'/> Make block title link</label></p><p class="categoryPosts-title_link_url"><label for="widget-category-posts--title_link_url"> Title link URL: <input placeholder="" id="widget-category-posts--title_link_url" name="widget-category-posts[][title_link_url]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_level"><label for="widget-category-posts--title_level">Heading Level: <a href="#" class="dashicons toggle-title-level-help dashicons-paperclip"><span class="screen-reader-text">Show title level help</span></a></label><span class="cpwp-right"><input class="Initial button" id="widget-category-posts--title_levelInitial" name="widget-category-posts[][title_level]" value="Initial" type="radio" /><input class="H1 button" id="widget-category-posts--title_levelH1" name="widget-category-posts[][title_level]" value="H1" type="radio" /><input class="H2 button" id="widget-category-posts--title_levelH2" name="widget-category-posts[][title_level]" value="H2" type="radio" /><input class="H3 button" id="widget-category-posts--title_levelH3" name="widget-category-posts[][title_level]" value="H3" type="radio" /><input class="H4 button" id="widget-category-posts--title_levelH4" name="widget-category-posts[][title_level]" value="H4" type="radio" /><input class="H5 button" id="widget-category-posts--title_levelH5" name="widget-category-posts[][title_level]" value="H5" type="radio" /><input class="H6 button" id="widget-category-posts--title_levelH6" name="widget-category-posts[][title_level]" value="H6" type="radio" /></span></p><div class="cat-post-title-level-help" style="display:none;"><p>Also, try \'Disable Theme\'s styles\' on General tab to avoid rendering commonly used CSS classes such here widget-title, which often used in Themes to write their CSS selectors and may affect the design. </p></div></div></div>', $out );

		// no title just link.
		ob_start();
		$widget->formTitlePanel(
			array(
				'title_link' => true,
			)
		);
		$out = removeSpaceBetweenTags( ob_get_contents() );
		ob_end_clean();
		$this->assertEquals( '<h4 data-panel="title">Title</h4><div class="cpwp_ident"><p class="categoryPosts-hide_title"><label class="checkbox" for="widget-category-posts--hide_title"><input id="widget-category-posts--hide_title" name="widget-category-posts[][hide_title]" type="checkbox" /> Hide title</label></p><div class="categoryposts-data-panel-title-settings" ><p class="categoryPosts-title"><label for="widget-category-posts--title"> Title: <input placeholder="" id="widget-category-posts--title" name="widget-category-posts[][title]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_link" style="display:none"><label class="checkbox" for="widget-category-posts--title_link"><input id="widget-category-posts--title_link" name="widget-category-posts[][title_link]" type="checkbox" checked=\'checked\'/> Make block title link</label></p><p class="categoryPosts-title_link_url"><label for="widget-category-posts--title_link_url"> Title link URL: <input placeholder="" id="widget-category-posts--title_link_url" name="widget-category-posts[][title_link_url]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_level"><label for="widget-category-posts--title_level">Heading Level: <a href="#" class="dashicons toggle-title-level-help dashicons-paperclip"><span class="screen-reader-text">Show title level help</span></a></label><span class="cpwp-right"><input class="Initial button" id="widget-category-posts--title_levelInitial" name="widget-category-posts[][title_level]" value="Initial" type="radio" /><input class="H1 button" id="widget-category-posts--title_levelH1" name="widget-category-posts[][title_level]" value="H1" type="radio" /><input class="H2 button" id="widget-category-posts--title_levelH2" name="widget-category-posts[][title_level]" value="H2" type="radio" /><input class="H3 button" id="widget-category-posts--title_levelH3" name="widget-category-posts[][title_level]" value="H3" type="radio" /><input class="H4 button" id="widget-category-posts--title_levelH4" name="widget-category-posts[][title_level]" value="H4" type="radio" /><input class="H5 button" id="widget-category-posts--title_levelH5" name="widget-category-posts[][title_level]" value="H5" type="radio" /><input class="H6 button" id="widget-category-posts--title_levelH6" name="widget-category-posts[][title_level]" value="H6" type="radio" /></span></p><div class="cat-post-title-level-help" style="display:none;"><p>Also, try \'Disable Theme\'s styles\' on General tab to avoid rendering commonly used CSS classes such here widget-title, which often used in Themes to write their CSS selectors and may affect the design. </p></div></div></div>', $out );

		// no title just link.
		ob_start();
		$widget->formTitlePanel(
			array(
				'hide_title' => true,
			)
		);
		$out = removeSpaceBetweenTags( ob_get_contents() );
		ob_end_clean();
		$this->assertEquals( '<h4 data-panel="title">Title</h4><div class="cpwp_ident"><p class="categoryPosts-hide_title"><label class="checkbox" for="widget-category-posts--hide_title"><input id="widget-category-posts--hide_title" name="widget-category-posts[][hide_title]" type="checkbox" checked=\'checked\'/> Hide title</label></p><div class="categoryposts-data-panel-title-settings" style="display:none"><p class="categoryPosts-title"><label for="widget-category-posts--title"> Title: <input placeholder="" id="widget-category-posts--title" name="widget-category-posts[][title]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_link" style="display:none"><label class="checkbox" for="widget-category-posts--title_link"><input id="widget-category-posts--title_link" name="widget-category-posts[][title_link]" type="checkbox" /> Make block title link</label></p><p class="categoryPosts-title_link_url"><label for="widget-category-posts--title_link_url"> Title link URL: <input placeholder="" id="widget-category-posts--title_link_url" name="widget-category-posts[][title_link_url]" type="text" value="" autocomplete="off"/></label></p><p class="categoryPosts-title_level"><label for="widget-category-posts--title_level">Heading Level: <a href="#" class="dashicons toggle-title-level-help dashicons-paperclip"><span class="screen-reader-text">Show title level help</span></a></label><span class="cpwp-right"><input class="Initial button" id="widget-category-posts--title_levelInitial" name="widget-category-posts[][title_level]" value="Initial" type="radio" /><input class="H1 button" id="widget-category-posts--title_levelH1" name="widget-category-posts[][title_level]" value="H1" type="radio" /><input class="H2 button" id="widget-category-posts--title_levelH2" name="widget-category-posts[][title_level]" value="H2" type="radio" /><input class="H3 button" id="widget-category-posts--title_levelH3" name="widget-category-posts[][title_level]" value="H3" type="radio" /><input class="H4 button" id="widget-category-posts--title_levelH4" name="widget-category-posts[][title_level]" value="H4" type="radio" /><input class="H5 button" id="widget-category-posts--title_levelH5" name="widget-category-posts[][title_level]" value="H5" type="radio" /><input class="H6 button" id="widget-category-posts--title_levelH6" name="widget-category-posts[][title_level]" value="H6" type="radio" /></span></p><div class="cat-post-title-level-help" style="display:none;"><p>Also, try \'Disable Theme\'s styles\' on General tab to avoid rendering commonly used CSS classes such here widget-title, which often used in Themes to write their CSS selectors and may affect the design. </p></div></div></div>', $out );
	}
}

/**
 *  Reference copy of the default widget/shortcode/block settings, used to build
 *  expected values in assertions.
 *
 *  This simply forwards to the plugin's own \categoryPosts\default_settings() so
 *  the two can never drift apart again - a hand-maintained duplicate here kept
 *  going stale every time a setting was added, renamed or removed in the plugin.
 */
function default_settings() {
	return \categoryPosts\default_settings();
}

class testShortCode extends WP_UnitTestCase {

	const SHORTCODE_NAME = 'catposts';
	const SHORTCODE_META = 'categoryPosts-shorcode';
	const WIDGET_BASE_ID = 'category-posts';

	/**
	 *  Test the generation and removal of met values when a shortcode is
	 *  inserted and removed from content
	 */
	public function testsave_post() {
		$pid = $this->factory->post->create(
			array(
				'title'        => 'test',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		// test no meta when post created with no shortcode.
		$this->assertEmpty( get_post_meta( $pid, self::SHORTCODE_META, true ) );

		// initialization to defaults when inserted.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ']',
			)
		);
		$this->assertEquals(
			array( '' => default_settings() ),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);

		// test change in other parts of the content.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . '] lovely day',
			)
		);
		$this->assertEquals(
			array( '' => default_settings() ),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);

		// test removal.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . 'bla] ' . self::SHORTCODE_NAME,
			)
		);
		$this->assertEmpty( get_post_meta( $pid, self::SHORTCODE_META, true ) );

		// same as above with name parameter
		// initialization to defaults when inserted.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ' name="test"]',
			)
		);
		$this->assertEquals(
			array( 'test' => default_settings() ),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);

		// test change in other parts of the content.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ' name="test"] lovely day',
			)
		);
		$this->assertEquals(
			array( 'test' => default_settings() ),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);

		// test removal.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . 'bla] ' . self::SHORTCODE_NAME,
			)
		);
		$this->assertEmpty( get_post_meta( $pid, self::SHORTCODE_META, true ) );

		// test multiple shortcodes.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ' name="test"]' .
																		 '[' . self::SHORTCODE_NAME . ' mistake="test2"]' .
																		 '[' . self::SHORTCODE_NAME . ' name="test testing"]',
			)
		);
		$this->assertEquals(
			array(
				''             => default_settings(),
				'test'         => default_settings(),
				'test testing' => default_settings(),
			),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);
	}

	/**
	 * Test the customize_save_after function to make sure the shortcode meta is updated (or not)
	 * when the customizer save.
	 */
	public function test_customize_save_after() {
		$pid = $this->factory->post->create(
			array(
				'title'        => 'test',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		$pid2 = $this->factory->post->create(
			array(
				'title'        => 'test2',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ']',
			)
		);
		wp_update_post(
			array(
				'ID'           => $pid2,
				'post_content' => '[' . self::SHORTCODE_NAME . ']',
			)
		);

		// no update at all
		categoryPosts\customize_save_after();
		$this->assertEquals(
			array( '' => default_settings() ),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);

		// update some other post
		update_option( '_virtual-' . self::WIDGET_BASE_ID, array( $pid2 => array( 'title' => 'bla' ) ) );
		categoryPosts\customize_save_after();
		$this->assertEquals(
			array( '' => default_settings() ),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);

		// update some property on "our" post, title
		update_option( '_virtual-' . self::WIDGET_BASE_ID, array( $pid => array( '' => array( 'title' => 'bla' ) ) ) );
		categoryPosts\customize_save_after();
		$out = default_settings();
		$out['title'] = 'bla';
		$this->assertEquals(
			array( '' => $out ),
			get_post_meta( $pid, self::SHORTCODE_META, true )
		);

		// test multiple shortcodes
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ' name="test"]' .
																		  '[' . self::SHORTCODE_NAME . ' mistake="test2"]' .
																		  '[' . self::SHORTCODE_NAME . ' name="test testing"]',
			)
		);
		update_option(
			'_virtual-' . self::WIDGET_BASE_ID, array(
				$pid => array(
					''             => array( 'title' => 'bla' ),
					'test'         => array( 'title' => 'ble' ),
					'test testing' => array( 'title' => 'bla2' ),
				),
			)
		);
		categoryPosts\customize_save_after();

		$out1 = default_settings();
		$out1['title'] = 'bla';
		$out2 = default_settings();
		$out2['title'] = 'ble';
		$out3 = default_settings();
		$out3['title'] = 'bla2';
		$this->assertEquals(
			array(
				''             => $out1,
				'test'         => $out2,
				'test testing' => $out3,
			), get_post_meta( $pid, self::SHORTCODE_META, true )
		);
	}

	/**
	 *  Test shortcode output
	 */
	function test_output() {
		$pid = $this->factory->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'test',
				'post_status'  => 'publish',
				'post_content' => '[' . self::SHORTCODE_NAME . ']',
			)
		);
		// setup global enviroment.
		$this->go_to( '/?p=' . $pid );
		the_post();
		categoryposts\register_virtual_widgets(); // generate virtual widgets usually done in the head of the theme.
		ob_start();
		the_content();
		$content = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(
			'<div id="category-posts-shortcode-' . $pid . '" class="category-posts-shortcode">Category Posts<ul>' .
						'<li class="cat-post-item cat-post-current"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a></div></li></ul>' .
						'</div>', str_replace( "\n", '', $content )
		);

		// named shortcode.
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ' name="bla"]',
			)
		);
		// setup global environment.
		$this->go_to( '/?p=' . $pid );
		the_post();
		categoryposts\register_virtual_widgets(); // generate virtual widgets usually done in the head of the theme.
		ob_start();
		the_content();
		$content = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(
			'<div id="category-posts-shortcode-' . $pid . '-bla" class="category-posts-shortcode">Category Posts<ul>' .
						'<li class="cat-post-item cat-post-current"><div><a class="cat-post-title" href="http://example.org/?p=' . $pid . '" rel="bookmark">test</a></div></li></ul>' .
						'</div>', str_replace( "\n", '', $content )
		);
	}

	/**
	 * Test that the DB is cleaned after uninstall.
	 */
	public function test_uninstall() {
		// test widget option.
		add_option( 'widget-category-posts', 'dummy' );
		$uninstall = NS . '\uninstall';
		$uninstall();
		$this->assertEquals( false, get_option( 'widget-category-posts', false ) );

		// test meta
		$pid = $this->factory->post->create(
			array(
				'title'        => 'test',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		$pid2 = $this->factory->post->create(
			array(
				'title'        => 'test2',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		$pid3 = $this->factory->post->create(
			array(
				'title'        => 'test2',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => '[' . self::SHORTCODE_NAME . ']',
			)
		);
		wp_update_post(
			array(
				'ID'           => $pid2,
				'post_content' => 'dummy',
			)
		);
		wp_update_post(
			array(
				'ID'           => $pid3,
				'post_content' => '[' . self::SHORTCODE_NAME . ']',
			)
		);
		$uninstall();
		$this->assertEquals( false, get_post_meta( $pid, 'categoryPosts-shorcode', true ) );
		$this->assertEquals( false, get_post_meta( $pid2, 'categoryPosts-shorcode', true ) );
		$this->assertEquals( false, get_post_meta( $pid3, 'categoryPosts-shorcode', true ) );
	}
}

class testVirtualwidget extends WP_UnitTestCase {

	/**
	 *  Test the id method
	 */
	function testId() {
		$v = new categoryPosts\Virtual_Widget( 'test', 'testclass', array() );
		$this->assertEquals( $v->id(), 'test' );
	}

	/**
	 * Test that virtual widgets are added from the collectio.
	 */
	public function testConstructor() {

		// test default setting with no override.
		$v = new categoryPosts\Virtual_Widget( 'test', 'testclass', array() );
		$col = categoryPosts\Virtual_Widget::getAllSettings();
		$this->assertEquals( $col['test'], default_settings() );

		// test default setting with override
		$v = new categoryPosts\Virtual_Widget( 'test2', 'testclass', array( 'title' => 'bla' ) );
		$col = categoryPosts\Virtual_Widget::getAllSettings();
		$expect = default_settings();
		$expect['title'] = 'bla';
		$this->assertEquals( $col['test2'], $expect );

	}

	/**
	 * Data provider for testGetCSSRules().
	 *
	 * Each row is one (settings, context) combination. The expected arrays
	 * are ground truth captured directly from the current getCSSRules()
	 * implementation, which is now template-driven (it decides what to
	 * output by inspecting $settings['template'] for %title%/%excerpt%/
	 * %thumb%, rather than by branching on individual display flags like
	 * the old hand-written expectations here did) - so the previous
	 * per-scenario copy-paste blocks built on the old flag-driven shape had
	 * drifted and no longer matched actual output.
	 *
	 * @since 4.7
	 */
	public function cssRulesProvider() {
		$default_widget = array(
			'normalize'                 => '#%1$s ul {padding: 0;}',
			'thumb_clenup'              => '#%1$s .cat-post-item img {max-width: initial; max-height: initial; margin: initial;}',
			'author_clenup'             => '#%1$s .cat-post-author {margin-bottom: 0;}',
			'thumb'                     => '#%1$s .cat-post-thumbnail {margin: 5px 10px 5px 0;}',
			'item_clenup'               => '#%1$s .cat-post-item:before {content: ""; clear: both;}',
			'more_link'                 => '#%1$s .cat-post-excerpt-more {display: inline-block;}',
			'item_style'                => '#%1$s .cat-post-item {list-style: none; margin: 3px 0 10px; padding: 3px 0;}',
			'current_title_font'        => '#%1$s .cat-post-current .cat-post-title {font-weight: bold; text-transform: uppercase;}',
			'post-taxs'                 => '#%1$s [class*=cat-post-tax] {font-size: 0.85em;}',
			'post-tax-childs'           => '#%1$s [class*=cat-post-tax] * {display:inline-block;}',
			'after_item'                => '#%1$s .cat-post-item:after {content: ""; display: table;	clear: both;}',
			'item_title_lines'          => '#%1$s .cat-post-item .cat-post-title {overflow: hidden;text-overflow: ellipsis;white-space: initial;display: -webkit-box;-webkit-line-clamp: 2;-webkit-box-orient: vertical;padding-bottom: 0 !important;}',
			'clear_previous_item'       => '#%1$s .cat-post-item:after {content: ""; display: table;	clear: both;}',
			'left'                      => '#%1$s .cat-post-thumbnail {display:block; float:left; margin:5px 10px 5px 0;}',
			'crop'                      => '#%1$s .cat-post-crop {overflow:hidden;display:block;}',
			'p_styling'                 => '#%1$s p {margin:5px 0 0 0}',
			'div_styling'               => '#%1$s li > div {margin:5px 0 0 0; clear:both;}',
			'dashicons'                 => '#%1$s .dashicons {vertical-align:middle;}',
			'thumb_crop_h'              => '#%1$s .cat-post-thumbnail .cat-post-crop img {height: 150px;}',
			'thumb_crop_w'              => '#%1$s .cat-post-thumbnail .cat-post-crop img {width: 150px;}',
			'thumb_crop'                => '#%1$s .cat-post-thumbnail .cat-post-crop img {object-fit: cover; max-width: 100%%; display: block;}',
			'thumb_crop_not_supported'  => '#%1$s .cat-post-thumbnail .cat-post-crop-not-supported img {width: 100%%;}',
			'thumb_fluid_width'         => '#%1$s .cat-post-thumbnail {max-width:100%%;}',
			'thumb_styling'             => '#%1$s .cat-post-item img {margin: initial;}',
		);

		// The shortcode context additionally gets the twentysixteen/twentyfifteen
		// theme-compat rules (unaffected by getCSSRules()'s $is_widget flag) and
		// drops the thumb_crop_* rules (those are only emitted for widgets).
		$default_shortcode = $default_widget;
		unset(
			$default_shortcode['thumb_crop_h'],
			$default_shortcode['thumb_crop_w'],
			$default_shortcode['thumb_crop'],
			$default_shortcode['thumb_crop_not_supported'],
			$default_shortcode['thumb_fluid_width'],
			$default_shortcode['thumb_styling']
		);
		$default_shortcode = array_merge(
			$default_shortcode,
			array(
				'twentysixteen_thumb'     => '#%1$s .cat-post-thumbnail {box-shadow:none}',
				'twentysixteen_tag_link'  => '#%1$s .cat-post-tax-tag a {border:0}',
				'twentysixteen_tag_span'  => '#%1$s .cat-post-tax-tag span {border:0}',
				'twentyfifteen_thumb'     => '#%1$s .cat-post-thumbnail {border:0}',
				'left'                    => '#%1$s .cat-post-thumbnail {display:block; float:left; margin:5px 10px 5px 0;}',
				'crop'                    => '#%1$s .cat-post-crop {overflow:hidden;display:block;}',
				'p_styling'               => '#%1$s p {margin:5px 0 0 0}',
				'div_styling'             => '#%1$s li > div {margin:5px 0 0 0; clear:both;}',
				'dashicons'               => '#%1$s .dashicons {vertical-align:middle;}',
				'thumb_crop_h'            => '#%1$s .cat-post-thumbnail .cat-post-crop img {height: 150px;}',
				'thumb_crop_w'            => '#%1$s .cat-post-thumbnail .cat-post-crop img {width: 150px;}',
				'thumb_crop'              => '#%1$s .cat-post-thumbnail .cat-post-crop img {object-fit: cover; max-width: 100%%; display: block;}',
				'thumb_crop_not_supported' => '#%1$s .cat-post-thumbnail .cat-post-crop-not-supported img {width: 100%%;}',
				'thumb_fluid_width'       => '#%1$s .cat-post-thumbnail {max-width:100%%;}',
				'thumb_styling'           => '#%1$s .cat-post-item img {margin: initial;}',
			)
		);

		/**
		 *  Build the expected array for one scenario/context by taking the
		 *  matching base set, formatting in the widget id, and merging in
		 *  ($extra) any rules specific to that scenario (hover effects, etc).
		 */
		$expect = function ( $base, $id, $extra = array() ) {
			$out = array();
			foreach ( $base as $key => $template ) {
				$out[ $key ] = sprintf( $template, $id );
			}
			foreach ( $extra as $key => $template ) {
				$out[ $key ] = sprintf( $template, $id );
			}
			return $out;
		};

		$hover_transition = '#%1$s .cat-post-%2$s img {padding-bottom: 0 !important; -webkit-transition: all 0.3s ease; -moz-transition: all 0.3s ease; -ms-transition: all 0.3s ease; -o-transition: all 0.3s ease; transition: all 0.3s ease;}';

		return array(
			// disable_css: only the essential (non-optional) rules survive.
			'disable_css, widget' => array(
				'test', array( 'disable_css' => true ), false,
				array(
					'thumb_crop_h'             => '#test-internal .cat-post-thumbnail .cat-post-crop img {height: 150px;}',
					'thumb_crop_w'             => '#test-internal .cat-post-thumbnail .cat-post-crop img {width: 150px;}',
					'thumb_crop'               => '#test-internal .cat-post-thumbnail .cat-post-crop img {object-fit: cover; max-width: 100%; display: block;}',
					'thumb_crop_not_supported' => '#test-internal .cat-post-thumbnail .cat-post-crop-not-supported img {width: 100%;}',
					'thumb_fluid_width'        => '#test-internal .cat-post-thumbnail {max-width:100%;}',
					'thumb_styling'            => '#test-internal .cat-post-item img {margin: initial;}',
				),
			),
			'disable_css, shortcode' => array(
				'test', array( 'disable_css' => true ), true,
				array(
					'thumb_crop_h'             => '#test .cat-post-thumbnail .cat-post-crop img {height: 150px;}',
					'thumb_crop_w'             => '#test .cat-post-thumbnail .cat-post-crop img {width: 150px;}',
					'thumb_crop'               => '#test .cat-post-thumbnail .cat-post-crop img {object-fit: cover; max-width: 100%; display: block;}',
					'thumb_crop_not_supported' => '#test .cat-post-thumbnail .cat-post-crop-not-supported img {width: 100%;}',
					'thumb_fluid_width'        => '#test .cat-post-thumbnail {max-width:100%;}',
					'thumb_styling'            => '#test .cat-post-item img {margin: initial;}',
				),
			),

			// default settings.
			'default, widget'    => array( 'test2', array(), false, $expect( $default_widget, 'test2-internal' ) ),
			'default, shortcode' => array( 'test2', array(), true, $expect( $default_shortcode, 'test2' ) ),

			// thumbTop: loses the thumb_crop_* rules (a pre-existing quirk of
			// the legacy-settings conversion for this flag), keeps the rest.
			'thumbTop, widget' => array(
				'test3', array( 'thumbTop' => true ), false,
				array_diff_key(
					$expect( $default_widget, 'test3-internal' ),
					array_flip( array( 'thumb_crop_h', 'thumb_crop_w', 'thumb_crop', 'thumb_crop_not_supported', 'thumb_fluid_width', 'thumb_styling' ) )
				),
			),
			'thumbTop, shortcode' => array(
				'test3', array( 'thumbTop' => true ), true,
				array_diff_key(
					$expect( $default_shortcode, 'test3' ),
					array_flip( array( 'thumb_crop_h', 'thumb_crop_w', 'thumb_crop', 'thumb_crop_not_supported', 'thumb_fluid_width', 'thumb_styling' ) )
				),
			),

			// thumb_hover variants: default set plus the hover-specific rules.
			'hover white, widget' => array(
				'test4', array( 'thumb_hover' => 'white' ), false,
				$expect(
					$default_widget, 'test4-internal', array(
						'white_hover_background' => '#%1$s .cat-post-white span {background-color: white;}',
						'white_hover_thumb'       => sprintf( $hover_transition, '%1$s', 'white' ),
						'white_hover_transform'   => '#%1$s .cat-post-white:hover img {opacity: 0.8;}',
					)
				),
			),
			'hover white, shortcode' => array(
				'test4', array( 'thumb_hover' => 'white' ), true,
				$expect(
					$default_shortcode, 'test4', array(
						'white_hover_background' => '#%1$s .cat-post-white span {background-color: white;}',
						'white_hover_thumb'       => sprintf( $hover_transition, '%1$s', 'white' ),
						'white_hover_transform'   => '#%1$s .cat-post-white:hover img {opacity: 0.8;}',
					)
				),
			),
			'hover dark, widget' => array(
				'test5', array( 'thumb_hover' => 'dark' ), false,
				$expect(
					$default_widget, 'test5-internal', array(
						'dark_hover_thumb'     => sprintf( $hover_transition, '%1$s', 'dark' ),
						'dark_hover_transform' => '#%1$s .cat-post-dark:hover img {-webkit-filter: brightness(75%%); -moz-filter: brightness(75%%); -ms-filter: brightness(75%%); -o-filter: brightness(75%%); filter: brightness(75%%);}',
					)
				),
			),
			'hover dark, shortcode' => array(
				'test5', array( 'thumb_hover' => 'dark' ), true,
				$expect(
					$default_shortcode, 'test5', array(
						'dark_hover_thumb'     => sprintf( $hover_transition, '%1$s', 'dark' ),
						'dark_hover_transform' => '#%1$s .cat-post-dark:hover img {-webkit-filter: brightness(75%%); -moz-filter: brightness(75%%); -ms-filter: brightness(75%%); -o-filter: brightness(75%%); filter: brightness(75%%);}',
					)
				),
			),
			'hover scale, widget' => array(
				'test6', array( 'thumb_hover' => 'scale' ), false,
				$expect(
					$default_widget, 'test6-internal', array(
						'scale_hover_thumb'     => '#%1$s .cat-post-scale img {margin: initial; padding-bottom: 0 !important; -webkit-transition: all 0.3s ease; -moz-transition: all 0.3s ease; -ms-transition: all 0.3s ease; -o-transition: all 0.3s ease; transition: all 0.3s ease;}',
						'scale_hover_transform' => '#%1$s .cat-post-scale:hover img {-webkit-transform: scale(1.1, 1.1); -ms-transform: scale(1.1, 1.1); transform: scale(1.1, 1.1);}',
					)
				),
			),
			'hover scale, shortcode' => array(
				'test6', array( 'thumb_hover' => 'scale' ), true,
				$expect(
					$default_shortcode, 'test6', array(
						'scale_hover_thumb'     => '#%1$s .cat-post-scale img {margin: initial; padding-bottom: 0 !important; -webkit-transition: all 0.3s ease; -moz-transition: all 0.3s ease; -ms-transition: all 0.3s ease; -o-transition: all 0.3s ease; transition: all 0.3s ease;}',
						'scale_hover_transform' => '#%1$s .cat-post-scale:hover img {-webkit-transform: scale(1.1, 1.1); -ms-transform: scale(1.1, 1.1); transform: scale(1.1, 1.1);}',
					)
				),
			),
			'hover blur, widget' => array(
				'test7', array( 'thumb_hover' => 'blur' ), false,
				$expect(
					$default_widget, 'test7-internal', array(
						'blur_hover_thumb'     => sprintf( $hover_transition, '%1$s', 'blur' ),
						'blur_hover_transform' => '#%1$s .cat-post-blur:hover img {-webkit-filter: blur(2px); -moz-filter: blur(2px); -o-filter: blur(2px); -ms-filter: blur(2px); filter: blur(2px);}',
					)
				),
			),
			'hover blur, shortcode' => array(
				'test7', array( 'thumb_hover' => 'blur' ), true,
				$expect(
					$default_shortcode, 'test7', array(
						'blur_hover_thumb'     => sprintf( $hover_transition, '%1$s', 'blur' ),
						'blur_hover_transform' => '#%1$s .cat-post-blur:hover img {-webkit-filter: blur(2px); -moz-filter: blur(2px); -o-filter: blur(2px); -ms-filter: blur(2px); filter: blur(2px);}',
					)
				),
			),

			// twentyseventeen: getCSSRules() no longer special-cases this
			// theme at all, so with default settings the output is byte
			// identical to the plain "default" scenario above (only the
			// widget id differs).
			'twentyseventeen, widget'    => array( 'test8', array(), false, $expect( $default_widget, 'test8-internal' ) ),
			'twentyseventeen, shortcode' => array( 'test8', array(), true, $expect( $default_shortcode, 'test8' ) ),

			// thumb_symbols ("Symbols"): symbols/logos must stay whole, so
			// the thumbnail is fitted with object-fit: contain instead of
			// the cropping default cover. Everything else is unchanged.
			'thumb_symbols, widget' => array(
				'test9', array( 'thumb_symbols' => true ), false,
				$expect(
					$default_widget, 'test9-internal', array(
						'thumb_crop' => '#%1$s .cat-post-thumbnail .cat-post-crop img {object-fit: contain; max-width: 100%%; display: block;}',
					)
				),
			),
			'thumb_symbols, shortcode' => array(
				'test9', array( 'thumb_symbols' => true ), true,
				$expect(
					$default_shortcode, 'test9', array(
						'thumb_crop' => '#%1$s .cat-post-thumbnail .cat-post-crop img {object-fit: contain; max-width: 100%%; display: block;}',
					)
				),
			),
			'thumb_symbols off, widget' => array(
				'test10', array( 'thumb_symbols' => false ), false,
				$expect( $default_widget, 'test10-internal' ),
			),
		);
	}

	/**
	 * Test getCSSRules method.
	 *
	 * Replaces eight near-identical copy-paste blocks (one per hover/theme
	 * variant) with a single data-provider-driven test - see
	 * cssRulesProvider() for the expected values, captured directly from
	 * the current implementation.
	 *
	 * @since 4.7
	 *
	 * @dataProvider cssRulesProvider
	 */
	public function testGetCSSRules( $id, $args, $is_shortcode_context, $expected ) {
		$v = new categoryPosts\Virtual_Widget( $id, 'testclass', $args );

		// getCSSRules() appends its whole rules array as a single element
		// ($rules[] = $ret;) rather than merging into $test directly, so
		// the actual rule set to compare against is $test[0].
		$test = array();
		$v->getCSSRules( $is_shortcode_context, $test );

		$this->assertEquals( $expected, $test[0] );
	}
}
