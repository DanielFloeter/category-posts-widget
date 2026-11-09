/**
 * Turning a core query loop block (core/query) into this block (#8).
 *
 * The query is mapped onto the filters of the block, the blocks inside the
 * post template onto the template placeholders, a pagination onto "More
 * posts: Pagination" and a no results paragraph onto the "no matches" text.
 * A query this block can not show the same way is not offered for the
 * transform at all, see isRepresentable(), rather than silently showing other
 * posts afterwards. This block shows posts of at most one category, without
 * authors, tags or other taxonomies.
 */

/**
 * The orders both blocks know, see the possible sorts in Widget::queryArgs().
 */
const ORDER_BY = [ 'date', 'title', 'comment_count', 'rand' ];

/**
 * The template placeholder of a block inside the post template.
 *
 * @param {Object} block A parsed block.
 * @return {?string} The placeholder, null for a block without one.
 */
function placeholderOf( block ) {
	switch ( block.name ) {
		case 'core/post-title':
			return '%title%';
		case 'core/post-featured-image':
			return '%thumb%';
		case 'core/post-excerpt':
			return '%excerpt%';
		case 'core/post-date':
			return '%date%';
		case 'core/post-author':
		case 'core/post-author-name':
			return '%author%';
		case 'core/post-comments-count':
			return '%commentnum%';
		case 'core/read-more':
			return '%more-link%';
		case 'core/post-terms':
			// This block knows the categories and the tags of a post only.
			return [ 'category', 'post_tag' ].includes( block.attributes?.term ) ? `%${ block.attributes.term }%` : null;
		default:
			return null;
	}
}

/**
 * All blocks of a tree, depth first, in their order.
 *
 * @param {Object[]} blocks Parsed blocks.
 * @return {Object[]} The blocks and all blocks inside them.
 */
function flatten( blocks = [] ) {
	return blocks.flatMap( ( block ) => [ block, ...flatten( block.innerBlocks ) ] );
}

/**
 * Whether a block lays out the blocks inside it side by side: a group with a
 * flex layout that is not vertical, as the "Row" variation of core/group.
 *
 * @param {Object} block A parsed block.
 * @return {boolean} True for a row.
 */
function isRow( block ) {
	const layout = block.attributes?.layout;
	return 'core/group' === block.name && 'flex' === layout?.type && 'vertical' !== layout?.orientation;
}

/**
 * Whether a block puts the blocks inside it side by side: a row or columns.
 *
 * @param {Object} block A parsed block.
 * @return {boolean} True for a row or columns.
 */
function isSideBySide( block ) {
	return isRow( block ) || 'core/columns' === block.name;
}

/**
 * Where the featured image sits beside other placeholders, in a row or in
 * columns.
 *
 * @param {Object[]} blocks The blocks inside the post template.
 * @return {?{side: string, column: ?Object}} 'left' when the image comes
 *         first, 'right' when it comes last, with the column holding it, null
 *         when it is not beside others or in the middle of them.
 */
function imageBeside( blocks ) {
	for ( const block of flatten( blocks ) ) {
		if ( ! isSideBySide( block ) ) {
			continue;
		}
		const placeholders = flatten( block.innerBlocks ).map( placeholderOf ).filter( Boolean );
		const index = placeholders.indexOf( '%thumb%' );
		if ( index < 0 ) {
			continue;
		}
		if ( placeholders.length < 2 || ( index > 0 && index < placeholders.length - 1 ) ) {
			return null;
		}
		const column = 'core/columns' === block.name
			? ( block.innerBlocks || [] ).find( ( inner ) => flatten( [ inner ] ).some( ( b ) => 'core/post-featured-image' === b.name ) )
			: null;

		return { side: 0 === index ? 'left' : 'right', column: column || null };
	}

	return null;
}

/**
 * The template for the blocks inside the post template.
 *
 * In the template an empty line starts a new line (a DIV of its own, see
 * Widget::itemHTML()), a single line break is only a space. The blocks of the
 * post template are stacked, so each placeholder gets a line of its own, but
 * the placeholders inside a row stay together on one line.
 *
 * An image at one end of a row or of columns puts all their placeholders on
 * one line, the image first: it floats left then, and a float only takes the
 * content after it beside itself.
 *
 * @param {Object[]} blocks The blocks inside the post template.
 * @return {string} The template, '' for blocks without a placeholder.
 */
function templateOf( blocks ) {
	const imageFirst = ( list ) => {
		const index = list.findIndex( ( item ) => '%thumb%' === item );
		return index > 0 ? [ '%thumb%', ...list.slice( 0, index ), ...list.slice( index + 1 ) ] : list;
	};
	const collect = ( list = [] ) =>
		list.flatMap( ( block ) => {
			const placeholder = placeholderOf( block );
			if ( placeholder ) {
				return [ placeholder ];
			}
			const placeholders = flatten( block.innerBlocks ).map( placeholderOf ).filter( Boolean );
			// The image at one end of a row or columns, beside the text: all on
			// one line, the image first, the text floats beside it.
			if ( isSideBySide( block ) && imageBeside( [ block ] ) ) {
				return [ imageFirst( placeholders ).join( '\n' ) ];
			}
			if ( isRow( block ) ) {
				return placeholders.length ? [ placeholders.join( '\n' ) ] : [];
			}
			return collect( block.innerBlocks );
		} );

	return collect( blocks ).join( '\n\n' );
}

/**
 * A CSS length in pixels, em and rem counted as 16px.
 *
 * @param {*} value A number or a CSS length, e.g. '300px' or '20em'.
 * @return {?number} The pixels, null for other units, e.g. '50%', 'auto'
 *                   or 'clamp(...)', and for nothing set.
 */
function toPx( value ) {
	if ( 'number' === typeof value ) {
		return value > 0 ? value : null;
	}
	const match = /^\s*(\d+(?:\.\d+)?)\s*(px|r?em)?\s*$/.exec( String( value ?? '' ) );
	if ( ! match || ! Number( match[ 1 ] ) ) {
		return null;
	}

	return Number( match[ 1 ] ) * ( match[ 2 ] && 'px' !== match[ 2 ] ? 16 : 1 );
}

/**
 * A percentage, e.g. '50%'.
 *
 * @param {*} value A CSS length.
 * @return {?number} The percentage, null for other units.
 */
function toPercent( value ) {
	const match = /^\s*(\d+(?:\.\d+)?)\s*%\s*$/.exec( String( value ?? '' ) );
	return match && Number( match[ 1 ] ) > 0 ? Math.min( 100, Number( match[ 1 ] ) ) : null;
}

/**
 * The aspect ratio of the featured image block, e.g. '16/9' or '1'.
 *
 * @param {*} value The aspectRatio attribute.
 * @return {?number} Width by height, null for 'auto' and nothing set.
 */
function toRatio( value ) {
	const match = /^\s*(\d+(?:\.\d+)?)\s*(?:\/\s*(\d+(?:\.\d+)?))?\s*$/.exec( String( value ?? '' ) );
	if ( ! match ) {
		return null;
	}
	const ratio = Number( match[ 1 ] ) / ( undefined === match[ 2 ] ? 1 : Number( match[ 2 ] ) );

	return ratio > 0 && Number.isFinite( ratio ) ? ratio : null;
}

/**
 * The width a thumbnail gets when the featured image block has a ratio or a
 * height but no width: in core it then takes the width of its column. Here the
 * image needs a width in pixels to keep the ratio, and shrinks from it to the
 * width there is (max-width: 100%), so the width has to be the one of the
 * column, not more: the browser loads the image for it (sizes).
 *
 * The width of the image size the block asks for, if it names one. Otherwise
 * the content width of the theme, the wide one for a wide query loop, split
 * into the columns of a grid. Without either the width of the "large" size.
 *
 * @param {Object} image      The attributes of the featured image block.
 * @param {Object} attributes The attributes of the core/query block.
 * @param {number} columns    The columns of the grid, 1 for a list.
 * @param {Object} editor     The sizes of the editor, see queryToBlockAttributes().
 * @return {number} The width in pixels.
 */
function referenceWidth( image, attributes, columns, editor ) {
	const sizes = editor.imageDimensions || {};
	const slugWidth = image.sizeSlug && toPx( sizes[ image.sizeSlug ]?.width );
	if ( slugWidth ) {
		return Math.round( slugWidth );
	}
	if ( 'full' !== attributes.align ) {
		const layoutWidth = toPx( 'wide' === attributes.align ? editor.wideSize : editor.contentSize );
		if ( layoutWidth ) {
			return Math.round( layoutWidth / Math.max( 1, columns ) );
		}
	}

	return Math.round( toPx( sizes.large?.width ) || 1024 );
}

/**
 * The thumbnail settings for the featured image block.
 *
 * Without any size the image takes the width of its column in its own ratio
 * in core, which is "Auto" (0) for both dimensions here. A ratio or a height
 * without a width get the width of referenceWidth(), a width in percent the
 * maximum width. Beside the text, in a row or in columns, the text does not
 * wrap around the image.
 *
 * @param {Object[]} blocks     The blocks inside the post template.
 * @param {Object}   attributes The attributes of the core/query block.
 * @param {number}   columns    The columns of the grid, 1 for a list.
 * @param {Object}   editor     The sizes of the editor.
 * @return {Object} The thumbnail attributes, none without a featured image.
 */
function thumbnailOf( blocks, attributes, columns, editor ) {
	const imageBlock = flatten( blocks ).find( ( block ) => 'core/post-featured-image' === block.name );
	if ( ! imageBlock ) {
		return {};
	}
	const image = imageBlock.attributes || {};
	const result = {};

	let width = toPx( image.width );
	let height = toPx( image.height );
	const ratio = toRatio( image.aspectRatio );
	if ( ! width && ( ratio || height ) ) {
		width = referenceWidth( image, attributes, columns, editor );
		if ( height && ratio ) {
			width = height * ratio;
		}
	}
	if ( width && ! height && ratio ) {
		height = width / ratio;
	}
	result.thumbW = width ? Math.round( width ) : 0;
	result.thumbH = height ? Math.round( height ) : 0;

	const beside = imageBeside( blocks );
	const percent = toPercent( image.width ) || toPercent( beside?.column?.attributes?.width );
	if ( percent ) {
		result.thumbFluidWidth = Math.round( percent );
	}

	// Beside the text, not with the text around it.
	if ( beside ) {
		result.textDoNotWrapThumb = true;
	}

	// Only a square image can be shown whole here, as the symbols are.
	if ( 'contain' === image.scale && 1 === ratio ) {
		result.thumbSymbols = true;
	}

	return result;
}

/**
 * The plain text of rich text content, which is a string or, since WP 6.5, a
 * RichTextData object.
 *
 * @param {*} content The content attribute.
 * @return {string} The content as HTML.
 */
function html( content ) {
	if ( content === undefined || content === null ) {
		return '';
	}
	return String( content );
}

/**
 * The terms of a taxQuery by taxonomy, split into those to include and those
 * to exclude. Reads both formats of core: the old one, { category: [ 4 ] },
 * and the one since WP 7.0, { include: { category: [ 4 ] }, exclude: { ... } }.
 *
 * @param {?Object} taxQuery The taxQuery of the query.
 * @return {{include: Object, exclude: Object}} Term ids by taxonomy.
 */
function splitTaxQuery( taxQuery ) {
	if ( ! taxQuery || 'object' !== typeof taxQuery ) {
		return { include: {}, exclude: {} };
	}
	const keys = Object.keys( taxQuery );
	const isNew = keys.length > 0 && keys.every( ( key ) => 'include' === key || 'exclude' === key );
	const clean = ( byTaxonomy ) =>
		Object.fromEntries(
			Object.entries( byTaxonomy || {} )
				.map( ( [ taxonomy, ids ] ) => [ taxonomy, ( Array.isArray( ids ) ? ids : [] ).filter( ( id ) => id !== '' && id !== null ) ] )
				.filter( ( [ , ids ] ) => ids.length )
		);

	return isNew
		? { include: clean( taxQuery.include ), exclude: clean( taxQuery.exclude ) }
		: { include: clean( taxQuery ), exclude: {} };
}

/**
 * The authors of the query, which core holds in three forms.
 *
 * @param {*} author The author of the query: '1,5', [ 1, 5 ] or 7.
 * @return {string[]} The author ids.
 */
function authorsOf( author ) {
	const authors = Array.isArray( author ) ? author : String( author ?? '' ).split( ',' );
	return authors.map( ( id ) => String( id ).trim() ).filter( ( id ) => id && '0' !== id );
}

/**
 * Whether this block can show the posts the query loop shows.
 *
 * Only posts, of at most one category, without authors, tags or other
 * taxonomies. Not for a query with only or without sticky posts, a search,
 * parents, post formats, excluded posts or an order this block does not know.
 * With "inherit" the query of the template is taken over and the other
 * settings of the query do not count, the same as in core.
 *
 * @param {Object} attributes The attributes of the core/query block.
 * @return {boolean} True if the transform keeps the posts shown.
 */
export function isRepresentable( attributes = {} ) {
	const query = attributes.query || {};
	if ( query.inherit ) {
		return true;
	}
	if ( query.postType && 'post' !== query.postType ) {
		return false;
	}
	if ( authorsOf( query.author ).length ) {
		return false;
	}
	if ( 'only' === query.sticky || 'exclude' === query.sticky ) {
		return false;
	}
	if ( query.search || query.parents?.length || query.format?.length || query.exclude?.length ) {
		return false;
	}
	if ( query.orderBy && ! ORDER_BY.includes( query.orderBy ) ) {
		return false;
	}
	const { include, exclude } = splitTaxQuery( query.taxQuery );
	if ( Object.keys( exclude ).length ) {
		return false;
	}
	const taxonomies = Object.keys( include );

	return 0 === taxonomies.length ||
		( 1 === taxonomies.length && 'category' === taxonomies[ 0 ] && 1 === include.category.length );
}

/**
 * The attributes of this block for a query loop block.
 *
 * @param {Object}   attributes               The attributes of the core/query block.
 * @param {Object[]} innerBlocks              The blocks inside it.
 * @param {Object}   editor                   The sizes of the editor, for the thumbnail.
 * @param {Object}   [editor.imageDimensions] The image sizes, { large: { width, height }, ... }.
 * @param {string}   [editor.contentSize]     The content width of the theme, e.g. '650px'.
 * @param {string}   [editor.wideSize]        The wide width of the theme.
 * @return {Object} The attributes of this block. Only those that differ from
 *                  the defaults are set.
 */
export default function queryToBlockAttributes( attributes = {}, innerBlocks = [], editor = {} ) {
	const query = attributes.query || {};
	const result = {};

	// The query.
	if ( query.inherit ) {
		result.queryType = 'default';
	} else {
		if ( 'number' === typeof query.perPage || ( 'string' === typeof query.perPage && /^\d+$/.test( query.perPage ) ) ) {
			result.num = String( query.perPage );
			// Core only applies the offset with a number of posts set.
			if ( Number( query.offset ) > 0 ) {
				result.offset = Number( query.offset ) + 1; // "Start with post" counts from 1.
			}
		}
		if ( 'asc' === query.order ) {
			result.order = true;
		}
		if ( query.orderBy && ORDER_BY.includes( query.orderBy ) ) {
			result.orderBy = query.orderBy;
		}
		// '' keeps sticky posts first, as WP_Query does, 'ignore' does not.
		result.sticky = 'ignore' !== query.sticky;
		if ( query.excludeCurrent ) {
			result.excludeCurrentPost = true;
		}

		const { include } = splitTaxQuery( query.taxQuery );
		if ( include.category?.length ) {
			result.categories = String( include.category[ 0 ] );
			// Core leaves the posts of child categories out.
			result.noCatChilds = true;
		}
	}

	// The alignment from the toolbar (wide, full), this block supports them all.
	if ( attributes.align ) {
		result.align = attributes.align;
	}

	// The heading right before the post template becomes the title, the query
	// loop has none of its own otherwise.
	const templateIndex = innerBlocks.findIndex( ( block ) => 'core/post-template' === block.name );
	const heading = templateIndex > 0 ? innerBlocks[ templateIndex - 1 ] : null;
	if ( heading && 'core/heading' === heading.name ) {
		result.title = html( heading.attributes?.content ).replace( /<[^>]*>/g, '' ).trim();
		result.titleLevel = 'H' + ( heading.attributes?.level || 2 );
	} else {
		result.hideTitle = true;
	}

	const all = flatten( innerBlocks );

	// The post template: placeholders. This block has no grid, a grid only
	// counts for the width of the thumbnail.
	const postTemplate = all.find( ( block ) => 'core/post-template' === block.name );
	if ( postTemplate ) {
		const layout = postTemplate.attributes?.layout;
		const columns = 'grid' === layout?.type ? Number( layout.columnCount ) || 3 : 1;
		const template = templateOf( postTemplate.innerBlocks );
		if ( template ) {
			result.template = template;
		}
		Object.assign( result, thumbnailOf( postTemplate.innerBlocks, attributes, columns, editor ) );
		// The heading level of the post title, H2 by default as in core. Level
		// 0 is a paragraph there, which stays text here.
		const postTitle = flatten( postTemplate.innerBlocks ).find( ( block ) => 'core/post-title' === block.name );
		if ( postTitle ) {
			const level = Number( postTitle.attributes?.level ?? 2 );
			if ( level >= 1 && level <= 6 ) {
				result.itemTitleLevel = 'H' + level;
			}
		}
	}

	// No results: a single paragraph becomes the text.
	const noResults = all.find( ( block ) => 'core/query-no-results' === block.name );
	if ( noResults && 1 === noResults.innerBlocks?.length && 'core/paragraph' === noResults.innerBlocks[ 0 ].name ) {
		result.noMatchHandling = 'text';
		result.noMatchText = html( noResults.innerBlocks[ 0 ].attributes?.content );
	}

	// The pagination: page numbers with a previous and a next link.
	if ( all.some( ( block ) => 'core/query-pagination' === block.name ) ) {
		result.paginationType = 'pages';
	}

	return result;
}
