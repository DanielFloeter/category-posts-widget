/**
 * The placeholders whose style can be set, and how a click
 * in the preview finds one. Kept apart from the panels (placeholder-styles.js),
 * so it can be tested without the editor packages.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * The placeholders whose style can be set, with the element each one puts out.
 * Same keys and selectors as PLACEHOLDER_STYLE_SELECTORS in cat-posts-block.php.
 * 'lineHeight' only for the excerpt, the other placeholders are mostly one line.
 * The terms stand for the categories and tags, %category% and %post_tag%.
 * 'hover': a hover color, for those which are links (with 'box' a hover
 * background as well), see PLACEHOLDER_STYLE_HOVER in cat-posts-block.php.
 * 'box': background, padding, margin and border as well, for a button (more
 * link) or badges (each term).
 */
export const PLACEHOLDER_STYLE_TARGETS = [
	{ key: 'title', placeholder: '%title%', selector: '.cat-post-title', label: __( 'Title', 'category-posts' ), hover: true },
	{ key: 'date', placeholder: '%date%', selector: '.cat-post-date', label: __( 'Date', 'category-posts' ) },
	{ key: 'excerpt', placeholder: '%excerpt%', selector: '.cpwp-excerpt-text', label: __( 'Excerpt', 'category-posts' ), lineHeight: true },
	{ key: 'moreLink', placeholder: '%more-link%', selector: '.cat-post-excerpt-more', label: __( 'More link', 'category-posts' ), box: true, hover: true },
	{ key: 'author', placeholder: '%author%', selector: '.cat-post-author', label: __( 'Author', 'category-posts' ), hover: true },
	{ key: 'commentNum', placeholder: '%commentnum%', selector: '.cat-post-comment-num', label: __( 'Number of comments', 'category-posts' ), hover: true },
	{
		key: 'terms',
		selector: '[class*="cat-post-tax-"]',
		label: __( 'Terms', 'category-posts' ),
		box: true,
		hover: true,
		inTemplate: ( text ) => text.includes( '%category%' ) || text.includes( '%post_tag%' ),
	},
];

/**
 * The item of a post as a whole, li.cat-post-item (div in the table layout).
 * No placeholder, it is always there: a click in an item beside its
 * placeholders picks it.
 */
export const ITEM_TARGET = { key: 'item', label: __( 'List item', 'category-posts' ), kind: 'box' };

/**
 * The title of the block, outside the items. It is no placeholder, but styled
 * the same way, as 'title' of the pseudo template 'block'. Same class as
 * BLOCK_TITLE_CLASS in cat-posts-block.php.
 */
export const BLOCK_TITLE_TARGET = { key: 'title', selector: '.cpwp-block-title', label: __( 'Block title', 'category-posts' ), hover: true };

/**
 * "Load more" and the pagination below the list, styled like a button and
 * like links with a box each. 'justify' aligns them, 'gap' sets the space
 * between the page numbers. 'pick' is the area a click picks it by, the
 * current page before the other page numbers. Same selectors as
 * BLOCK_STYLE_SELECTORS in cat-posts-block.php.
 */
export const LOAD_MORE_TARGET = {
	key: 'loadMore',
	selector: '.categoryPosts-loadmore button',
	pick: '.categoryPosts-loadmore',
	label: __( 'Load more', 'category-posts' ),
	box: true,
	hover: true,
	justify: [ 'left', 'center', 'right' ],
};
export const PAGINATION_CURRENT_TARGET = {
	key: 'paginationCurrent',
	selector: '.categoryPosts-pagination .page-numbers.current',
	pick: '.categoryPosts-pagination .page-numbers.current',
	label: __( 'Current page', 'category-posts' ),
	box: true,
};
export const PAGINATION_TARGET = {
	key: 'pagination',
	selector: '.categoryPosts-pagination .page-numbers',
	pick: '.categoryPosts-pagination',
	label: __( 'Pagination', 'category-posts' ),
	box: true,
	hover: true,
	justify: [ 'left', 'center', 'right', 'space-between' ],
	gap: true,
};

/**
 * The targets outside the items: the title of the block, unless it is hidden,
 * and "Load more" or the pagination, whichever the list has.
 *
 * @param {Object} attributes The block attributes.
 * @return {Array} Targets like the entries of PLACEHOLDER_STYLE_TARGETS.
 */
export function blockTargets( attributes ) {
	// Blocks saved before "More posts" existed follow the old toggle, see pagination_type().
	const morePosts = [ 'none', 'loadmore', 'pages' ].includes( attributes.paginationType )
		? attributes.paginationType
		: ( attributes.enableLoadmore ? 'loadmore' : 'none' );
	return [
		...( attributes.hideTitle ? [] : [ BLOCK_TITLE_TARGET ] ),
		...( 'loadmore' === morePosts ? [ LOAD_MORE_TARGET ] : [] ),
		...( 'pages' === morePosts ? [ PAGINATION_TARGET, PAGINATION_CURRENT_TARGET ] : [] ),
	];
}

/**
 * The items (Widget::itemHTML()). There is one template, its styles are stored
 * under 'default'.
 */
const ITEM_SELECTORS = {
	default: '.cat-post-item',
};

const ALL_SELECTORS = PLACEHOLDER_STYLE_TARGETS.map( ( target ) => target.selector ).join( ',' );

/**
 * The templates with styles of their own: the one template of the list.
 *
 * @param {Object} attributes The block attributes.
 * @return {Array} { template, text } for each template.
 */
export function templatesOf( attributes ) {
	return [ { template: 'default', text: attributes.template || '' } ];
}

/**
 * The placeholders of a template whose style can be set.
 *
 * @param {string} text The template.
 * @return {Array} Entries of PLACEHOLDER_STYLE_TARGETS.
 */
export function targetsIn( text ) {
	return PLACEHOLDER_STYLE_TARGETS.filter( ( target ) =>
		target.inTemplate ? target.inTemplate( text ) : text.includes( target.placeholder )
	);
}

/**
 * The placeholder a click in the preview landed on.
 *
 * The innermost placeholder element wins, e.g. the more link within the
 * excerpt. One whose placeholder is not in the template of the item (the more
 * text of the excerpt without %more-link%) passes the click on to the next one
 * around it. A click in the item beside its placeholders picks the item.
 *
 * @param {Element} element    The element clicked.
 * @param {Object}  attributes The block attributes.
 * @return {Object|null} { template, target } or null.
 */
export function findStyleTarget( element, attributes ) {
	if ( ! element?.closest ) {
		return null;
	}
	const blockTarget = blockTargets( attributes )
		.slice()
		// The current page first, it lies within the pagination.
		.sort( ( a, b ) => ( b === PAGINATION_CURRENT_TARGET ) - ( a === PAGINATION_CURRENT_TARGET ) )
		.find( ( target ) => element.closest( target.pick || target.selector ) );
	if ( blockTarget ) {
		return { template: 'block', target: blockTarget.key };
	}
	const item = element.closest( '.cat-post-item' );
	if ( ! item ) {
		return null;
	}
	const template = 'default';
	const entry = templatesOf( attributes ).find( ( candidate ) => candidate.template === template );
	if ( ! entry ) {
		return null;
	}
	const available = targetsIn( entry.text );

	let candidate = element.closest( ALL_SELECTORS );
	while ( candidate && item.contains( candidate ) ) {
		const match = available.find( ( target ) => candidate.matches( target.selector ) );
		if ( match ) {
			return { template, target: match.key };
		}
		candidate = candidate.parentElement?.closest( ALL_SELECTORS );
	}
	return { template, target: ITEM_TARGET.key };
}

/**
 * The rule which outlines the elements of the selected placeholder.
 *
 * @param {string}      scope     Selector of the preview.
 * @param {Object|null} selection { template, target }.
 * @return {string} The CSS.
 */
export function selectionCSS( scope, selection ) {
	const outline = '{outline:2px solid var(--wp-admin-theme-color,#3858e9);outline-offset:2px}';
	if ( 'block' === selection?.template ) {
		const blockTarget = [ BLOCK_TITLE_TARGET, LOAD_MORE_TARGET, PAGINATION_TARGET, PAGINATION_CURRENT_TARGET ]
			.find( ( target ) => target.key === selection.target );
		return blockTarget ? `${ scope } ${ blockTarget.selector }${ outline }` : '';
	}
	if ( selection && ITEM_TARGET.key === selection.target && ITEM_SELECTORS[ selection.template ] ) {
		return `${ scope } ${ ITEM_SELECTORS[ selection.template ] }${ outline }`;
	}
	const target = selection && PLACEHOLDER_STYLE_TARGETS.find( ( candidate ) => candidate.key === selection.target );
	if ( ! target ) {
		return '';
	}
	return `${ scope } ${ ITEM_SELECTORS[ selection.template ] } ${ target.selector }${ outline }`;
}
