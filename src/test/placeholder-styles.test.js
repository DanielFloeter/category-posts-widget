/**
 * Tests for picking a placeholder in the preview.
 *
 * Run with: npm run test:unit
 */
import { findStyleTarget, selectionCSS } from '../placeholder-style-targets';

const preview = () => {
	document.body.innerHTML = `
		<div class="cpwp-block-title" id="block-title"><a href="#" id="block-title-link">Posts</a></div>
		<ul>
			<li class="cat-post-item" id="first">
				<a class="cat-post-title" href="#"><span id="title-text">Title</span></a>
				<span class="cat-post-date" id="date">1. July</span>
				<div><p class="cpwp-excerpt-text" id="excerpt">Text
					<a class="cat-post-excerpt-more more-link" id="more" href="#">more</a></p></div>
				<span class="cat-post-author post-author"><a href="#" id="author">Ann</a></span>
				<span class="cat-post-tax-category"><a class="cat-post-term" href="#" id="term">News</a></span>
				<span class="cat-post-tax-tag"><a class="cat-post-term" href="#" id="tag">Summer</a></span>
				<div id="empty">&nbsp;</div>
			</li>
		</ul>`;
};

const attributes = {
	template: '%title%\n%date%\n%excerpt% %more-link%\n%author% %category%',
};

describe( 'findStyleTarget', () => {
	beforeEach( preview );

	test( 'finds the placeholder', () => {
		const el = ( id ) => document.getElementById( id );
		expect( findStyleTarget( el( 'title-text' ), attributes ) ).toEqual( { template: 'default', target: 'title' } );
		expect( findStyleTarget( el( 'date' ), attributes ) ).toEqual( { template: 'default', target: 'date' } );
	} );

	test( 'the title of the block, unless it is hidden', () => {
		expect( findStyleTarget( document.getElementById( 'block-title-link' ), attributes ) ).toEqual( { template: 'block', target: 'title' } );
		expect( findStyleTarget( document.getElementById( 'block-title' ), { ...attributes, hideTitle: true } ) ).toBeNull();
	} );

	test( 'author and terms, also by their inner link', () => {
		expect( findStyleTarget( document.getElementById( 'author' ), attributes ) ).toEqual( { template: 'default', target: 'author' } );
		expect( findStyleTarget( document.getElementById( 'term' ), attributes ) ).toEqual( { template: 'default', target: 'terms' } );
		// The tags count as terms as well.
		expect( findStyleTarget( document.getElementById( 'tag' ), { ...attributes, template: '%post_tag%' } ) ).toEqual( { template: 'default', target: 'terms' } );
		// Without terms in the template the click goes to the item.
		expect( findStyleTarget( document.getElementById( 'term' ), { ...attributes, template: '%title%' } ) ).toEqual( { template: 'default', target: 'item' } );
	} );

	test( 'load more, the pagination and its current page', () => {
		document.body.insertAdjacentHTML( 'beforeend', `
			<div class="categoryPosts-loadmore"><button id="load">Load more</button></div>
			<nav class="categoryPosts-pagination"><a class="page-numbers" id="page2">2</a><span class="page-numbers current" id="page1">1</span></nav>` );
		const el = ( id ) => document.getElementById( id );
		expect( findStyleTarget( el( 'load' ), { ...attributes, paginationType: 'loadmore' } ) ).toEqual( { template: 'block', target: 'loadMore' } );
		expect( findStyleTarget( el( 'load' ), { ...attributes, enableLoadmore: true } ) ).toEqual( { template: 'block', target: 'loadMore' } );
		expect( findStyleTarget( el( 'load' ), attributes ) ).toBeNull();
		expect( findStyleTarget( el( 'page2' ), { ...attributes, paginationType: 'pages' } ) ).toEqual( { template: 'block', target: 'pagination' } );
		expect( findStyleTarget( el( 'page1' ), { ...attributes, paginationType: 'pages' } ) ).toEqual( { template: 'block', target: 'paginationCurrent' } );
	} );

	test( 'the more link within the excerpt wins', () => {
		expect( findStyleTarget( document.getElementById( 'more' ), attributes ) ).toEqual( { template: 'default', target: 'moreLink' } );
		expect( findStyleTarget( document.getElementById( 'excerpt' ), attributes ) ).toEqual( { template: 'default', target: 'excerpt' } );
	} );

	test( 'without %more-link% in the template the click goes to the excerpt', () => {
		const withoutMoreLink = { ...attributes, template: '%title%\n%excerpt%' };
		expect( findStyleTarget( document.getElementById( 'more' ), withoutMoreLink ) ).toEqual( { template: 'default', target: 'excerpt' } );
	} );

	test( 'the item beside its placeholders, nothing outside the items', () => {
		expect( findStyleTarget( document.getElementById( 'empty' ), attributes ) ).toEqual( { template: 'default', target: 'item' } );
		expect( findStyleTarget( document.body, attributes ) ).toBeNull();
	} );
} );

describe( 'selectionCSS', () => {
	test( 'outlines the placeholder in the items', () => {
		expect( selectionCSS( '[data-cpwp-preview="x"]', { template: 'default', target: 'date' } ) )
			.toBe( '[data-cpwp-preview="x"] .cat-post-item .cat-post-date{outline:2px solid var(--wp-admin-theme-color,#3858e9);outline-offset:2px}' );
		expect( selectionCSS( '#x', { template: 'block', target: 'title' } ) )
			.toBe( '#x .cpwp-block-title{outline:2px solid var(--wp-admin-theme-color,#3858e9);outline-offset:2px}' );
		expect( selectionCSS( '#x', { template: 'default', target: 'item' } ) )
			.toBe( '#x .cat-post-item{outline:2px solid var(--wp-admin-theme-color,#3858e9);outline-offset:2px}' );
		expect( selectionCSS( '#x', null ) ).toBe( '' );
	} );
} );
