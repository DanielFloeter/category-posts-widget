/**
 * Tests for turning a core query loop block into this block (#8).
 *
 * Run with: npm run test:unit
 */
import queryToBlockAttributes, { isRepresentable } from '../query-transform';

const block = ( name, attributes = {}, innerBlocks = [] ) => ( { name, attributes, innerBlocks } );
const map = ( query, innerBlocks = [] ) => queryToBlockAttributes( { query }, innerBlocks );

describe( 'the query', () => {
	test( 'number of posts and offset', () => {
		expect( map( { perPage: 3, offset: 2 } ) ).toMatchObject( { num: '3', offset: 3 } );
		expect( map( { perPage: '5' } ).num ).toBe( '5' );

		// Without a number of posts, core uses "Blog pages show at most" and no offset.
		const unset = map( { perPage: null, offset: 2 } );
		expect( unset.num ).toBeUndefined();
		expect( unset.offset ).toBeUndefined();

		expect( map( { perPage: 3, offset: 0 } ).offset ).toBeUndefined();
	} );

	test( 'order and order by', () => {
		expect( map( { order: 'asc' } ).order ).toBe( true );
		expect( map( { order: 'desc' } ).order ).toBeUndefined();
		[ 'date', 'title', 'comment_count', 'rand' ].forEach( ( orderBy ) =>
			expect( map( { orderBy } ).orderBy ).toBe( orderBy )
		);
	} );

	test( 'sticky posts and the current post', () => {
		expect( map( { sticky: '' } ).sticky ).toBe( true );
		expect( map( {} ).sticky ).toBe( true );
		expect( map( { sticky: 'ignore' } ).sticky ).toBe( false );
		expect( map( { excludeCurrent: true } ).excludeCurrentPost ).toBe( true );
	} );

	test( 'one category, in the old and in the new format of taxQuery', () => {
		expect( map( { taxQuery: { category: [ 4 ], post_tag: [] } } ) ).toMatchObject( { categories: '4', noCatChilds: true } );
		expect( map( { taxQuery: { include: { category: [ 4 ] } } } ) ).toMatchObject( { categories: '4', noCatChilds: true } );
		expect( map( { taxQuery: null } ).categories ).toBeUndefined();
		expect( map( { taxQuery: { category: [] } } ).noCatChilds ).toBeUndefined();
	} );

	test( 'inherit takes over the query of the template', () => {
		const inherited = map( { inherit: true, perPage: 3, taxQuery: { category: [ 4 ] } } );
		expect( inherited.queryType ).toBe( 'default' );
		expect( inherited.num ).toBeUndefined();
		expect( inherited.categories ).toBeUndefined();
		expect( inherited.sticky ).toBeUndefined();
	} );
} );

describe( 'what can not be shown the same way', () => {
	test( 'is not offered', () => {
		[
			{ postType: 'page' },
			{ author: '1' },
			{ author: [ 1, 5 ] },
			{ sticky: 'only' },
			{ sticky: 'exclude' },
			{ search: 'foo' },
			{ parents: [ 3 ] },
			{ format: [ 'aside' ] },
			{ exclude: [ 9 ] },
			{ orderBy: 'modified' },
			{ taxQuery: { category: [ 4, 6 ] } },
			{ taxQuery: { post_tag: [ 5 ] } },
			{ taxQuery: { include: { category: [ 4 ] }, exclude: { category: [ 5 ] } } },
			{ taxQuery: { exclude: { category: [ 5 ] } } },
			{ taxQuery: { include: { genre: [ 2 ] } } },
		].forEach( ( query ) => expect( isRepresentable( { query } ) ).toBe( false ) );
	} );

	test( 'everything else is', () => {
		[
			{},
			{ postType: 'post' },
			{ author: '' },
			{ sticky: '' },
			{ sticky: 'ignore' },
			{ search: '', parents: [], format: [], exclude: [] },
			{ orderBy: 'title' },
			{ taxQuery: { category: [ 4 ], post_tag: [] } },
			{ taxQuery: { include: { category: [ 4 ] } } },
			// Inherit ignores the other settings, the same as core.
			{ inherit: true, search: 'foo', sticky: 'only', postType: 'page', taxQuery: { post_tag: [ 5 ] } },
		].forEach( ( query ) => expect( isRepresentable( { query } ) ).toBe( true ) );
	} );
} );

describe( 'the block', () => {
	test( 'the alignment from the toolbar', () => {
		expect( queryToBlockAttributes( { query: {}, align: 'wide' } ).align ).toBe( 'wide' );
		expect( queryToBlockAttributes( { query: {} } ).align ).toBeUndefined();
	} );
} );

describe( 'the inner blocks', () => {
	const template = ( layout, ...inner ) => block( 'core/post-template', { layout }, inner );

	test( 'a grid is not kept', () => {
		expect( map( {}, [ template( { type: 'grid', columnCount: 3 } ) ] ).layout ).toBeUndefined();
	} );

	test( 'the template from the blocks, in their order, also nested', () => {
		const inner = [
			template(
				{ type: 'default' },
				block( 'core/post-title' ),
				block( 'core/group', {}, [
					block( 'core/post-featured-image' ),
					block( 'core/columns', {}, [ block( 'core/column', {}, [ block( 'core/post-excerpt' ) ] ) ] ),
				] ),
				block( 'core/post-date' ),
				block( 'core/post-author-name' ),
				block( 'core/post-terms', { term: 'category' } ),
				block( 'core/post-terms', { term: 'genre' } ),
				block( 'core/paragraph' )
			),
		];
		// Stacked blocks, each on a line of its own (an empty line).
		expect( map( {}, inner ).template ).toBe( '%title%\n\n%thumb%\n\n%excerpt%\n\n%date%\n\n%author%\n\n%category%' );

		// Nothing known inside: the default template stays.
		expect( map( {}, [ template( {}, block( 'core/paragraph' ) ) ] ).template ).toBeUndefined();
	} );

	test( 'the blocks of a row stay on one line', () => {
		const row = ( layout, ...inner ) => block( 'core/group', { layout }, inner );
		const inner = [
			template(
				{ type: 'default' },
				block( 'core/post-title' ),
				row( { type: 'flex', flexWrap: 'nowrap' }, block( 'core/post-date' ), block( 'core/group', {}, [ block( 'core/post-author' ) ] ) ),
				row( { type: 'flex', orientation: 'vertical' }, block( 'core/post-featured-image' ), block( 'core/post-excerpt' ) ),
				row( { type: 'flex' }, block( 'core/paragraph' ) ),
				block( 'core/read-more' )
			),
		];
		expect( map( {}, inner ).template ).toBe( '%title%\n\n%date%\n%author%\n\n%thumb%\n\n%excerpt%\n\n%more-link%' );
	} );

	test( 'the heading level of the post title', () => {
		const level = ( attributes ) => map( {}, [ template( {}, block( 'core/group', {}, [ block( 'core/post-title', attributes ) ] ) ) ] ).itemTitleLevel;
		expect( level( {} ) ).toBe( 'H2' );
		expect( level( { level: 3 } ) ).toBe( 'H3' );
		expect( level( { level: 0 } ) ).toBeUndefined();
		expect( map( {}, [ template( {}, block( 'core/post-date' ) ) ] ).itemTitleLevel ).toBeUndefined();
	} );

	test( 'the heading before the post template is the title', () => {
		const titled = map( {}, [ block( 'core/heading', { content: 'Latest <em>news</em>', level: 3 } ), template( {} ) ] );
		expect( titled ).toMatchObject( { title: 'Latest news', titleLevel: 'H3' } );
		expect( titled.hideTitle ).toBeUndefined();

		// Rich text data objects, since WP 6.5.
		const data = { toString: () => 'News' };
		expect( map( {}, [ block( 'core/heading', { content: data } ), template( {} ) ] ).title ).toBe( 'News' );

		expect( map( {}, [ template( {} ) ] ).hideTitle ).toBe( true );
	} );

	test( 'no results with a single paragraph', () => {
		const one = map( {}, [ template( {} ), block( 'core/query-no-results', {}, [ block( 'core/paragraph', { content: 'Nothing <b>here</b>.' } ) ] ) ] );
		expect( one ).toMatchObject( { noMatchHandling: 'text', noMatchText: 'Nothing <b>here</b>.' } );

		const two = map( {}, [ block( 'core/query-no-results', {}, [ block( 'core/paragraph' ), block( 'core/paragraph' ) ] ) ] );
		expect( two.noMatchHandling ).toBeUndefined();
	} );

	test( 'the pagination', () => {
		const pagination = [ template( {} ), block( 'core/query-pagination', {}, [ block( 'core/query-pagination-numbers' ) ] ) ];
		expect( map( {}, pagination ).paginationType ).toBe( 'pages' );
		expect( map( {}, [ template( {} ) ] ).paginationType ).toBeUndefined();
	} );
} );

describe( 'the featured image', () => {
	const editor = {
		imageDimensions: { medium: { width: 300, height: 300 }, large: { width: 1024, height: 1024 } },
		contentSize: '600px',
		wideSize: '1200px',
	};
	const image = ( attributes = {} ) => block( 'core/post-featured-image', attributes );
	const thumb = ( inner, { layout = {}, query = {}, sizes = editor } = {} ) =>
		queryToBlockAttributes( query, [ block( 'core/post-template', { layout }, inner ) ], sizes );

	test( 'without a size: auto', () => {
		expect( thumb( [ image() ] ) ).toMatchObject( { thumbW: 0, thumbH: 0 } );
		expect( thumb( [ image( { aspectRatio: 'auto' } ) ] ) ).toMatchObject( { thumbW: 0, thumbH: 0 } );

		// No featured image, nothing to set.
		expect( thumb( [ block( 'core/post-title' ) ] ).thumbW ).toBeUndefined();
	} );

	test( 'width and height in pixels, also from em and with the ratio', () => {
		expect( thumb( [ image( { width: '320px', height: '200px' } ) ] ) ).toMatchObject( { thumbW: 320, thumbH: 200 } );
		expect( thumb( [ image( { width: '20em', aspectRatio: '4/3' } ) ] ) ).toMatchObject( { thumbW: 320, thumbH: 240 } );
		expect( thumb( [ image( { height: '200px', aspectRatio: '3/2' } ) ] ) ).toMatchObject( { thumbW: 300, thumbH: 200 } );
		expect( thumb( [ image( { width: '320px' } ) ] ) ).toMatchObject( { thumbW: 320, thumbH: 0 } );
	} );

	test( 'a ratio takes the width of the column', () => {
		expect( thumb( [ image( { aspectRatio: '16/9' } ) ] ) ).toMatchObject( { thumbW: 600, thumbH: 338 } );
		// A grid is not kept, but its columns are narrower.
		expect( thumb( [ image( { aspectRatio: '1' } ) ], { layout: { type: 'grid', columnCount: 3 } } ) ).toMatchObject( { thumbW: 200, thumbH: 200 } );
		expect( thumb( [ image( { aspectRatio: '2/1' } ) ], { query: { align: 'wide' } } ) ).toMatchObject( { thumbW: 1200, thumbH: 600 } );
		expect( thumb( [ image( { aspectRatio: '1', sizeSlug: 'medium' } ) ] ) ).toMatchObject( { thumbW: 300, thumbH: 300 } );
		expect( thumb( [ image( { aspectRatio: '2/1' } ) ], { query: { align: 'full' } } ) ).toMatchObject( { thumbW: 1024, thumbH: 512 } );
		expect( thumb( [ image( { aspectRatio: '2/1' } ) ], { sizes: {} } ).thumbW ).toBe( 1024 );
	} );

	test( 'a width in percent is the maximum width', () => {
		expect( thumb( [ image( { width: '50%' } ) ] ) ).toMatchObject( { thumbW: 0, thumbH: 0, thumbFluidWidth: 50 } );
	} );

	test( 'beside the text in a row or in columns: first in the template, the text not wrapped around it', () => {
		const row = ( ...inner ) => block( 'core/group', { layout: { type: 'flex' } }, inner );
		const column = ( attributes, ...inner ) => block( 'core/column', attributes, inner );

		expect( thumb( [ row( image(), block( 'core/post-title' ) ) ] ) ).toMatchObject( { textDoNotWrapThumb: true, template: '%thumb%\n%title%' } );
		expect( thumb( [ row( block( 'core/post-title' ), image() ) ] ) ).toMatchObject( { textDoNotWrapThumb: true, template: '%thumb%\n%title%' } );

		// The core pattern "Image at left".
		const imageAtLeft = thumb( [
			block( 'core/columns', { align: 'wide' }, [
				column( { width: '66.66%' }, image( { isLink: true } ) ),
				column( { width: '33.33%' }, block( 'core/post-title' ), block( 'core/post-excerpt' ) ),
			] ),
		] );
		expect( imageAtLeft ).toMatchObject( { textDoNotWrapThumb: true, thumbFluidWidth: 67, template: '%thumb%\n%title%\n%excerpt%' } );

		// On a line of its own, or in the middle of others: the text wraps around it as before.
		expect( thumb( [ image() ] ).textDoNotWrapThumb ).toBeUndefined();
		expect( thumb( [ row( block( 'core/post-title' ), image(), block( 'core/post-date' ) ) ] ).textDoNotWrapThumb ).toBeUndefined();
	} );

	test( 'contain only for a square', () => {
		expect( thumb( [ image( { scale: 'contain', aspectRatio: '1' } ) ] ).thumbSymbols ).toBe( true );
		expect( thumb( [ image( { scale: 'contain', aspectRatio: '4/3' } ) ] ).thumbSymbols ).toBeUndefined();
	} );
} );
