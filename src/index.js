import { registerBlockType } from '@wordpress/blocks';
import { createBlock } from '@wordpress/blocks';
import { select } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './style.scss';
import Edit from './edit';
import Save from './save';
import transformToPro from './transform';
import widgetToBlockAttributes from './widget-transform';
import queryToBlockAttributes, { isRepresentable } from './query-transform';
import metadata from './../block.json';
import { hasContentTab } from './editor-features';
const { name } = metadata;

// block.json shows some fields of the color and typography panels right away,
// as "Latest Posts" does: the "Styles" tab holds only these panels, ours are
// under "Content" and "Settings". Without the "Content" tab ours are in the
// "Styles" tab as well, below these panels, which WordPress puts first. Only
// their headers then, to leave room for ours. The empty color defaults close
// the "Elements" panel (link color) as well.
const supports = hasContentTab
	? metadata.supports
	: {
		...metadata.supports,
		color: { ...metadata.supports.color, __experimentalDefaultControls: {} },
		typography: { ...metadata.supports.typography, __experimentalDefaultControls: {} },
	};

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
registerBlockType(name, {
	...metadata,
	supports,
	keywords: [
		__('category'),
		__('categories'),
		__('posts widget'),
		__('recent posts'),
		__('excerpt'),
		__('tiptoppress'),
	],
	example: {
		attributes: {
			values:
				'<ul>' +
				'<li>' +
				'<div><a class="cat-post-title">dolorem eum fugiat quo voluptas</a><div>' +
				'<p class="cpwp-excerpt-text">But who has any right to find fault with a man who chooses to enjoy a pleasure that has no annoying consequences, or one who avoids a pain that produces no resultant pleasure?</p>' +
				'</li>' +
				'<li>' +
				'<div><a class="cat-post-title">gummies tootsie roll</a><div>' +
				'<p class="cpwp-excerpt-text">Cake jujubes jelly beans. Marzipan soufflé gummies gummi bears oat cake chocolate jelly icing. Cotton candy croissant wafer cake apple pie juj.</p>' +
				'</li>' +
				'</ul>',
			showPostCounts:
				true,
		},
	},
	transforms: {
		from: [
			{
				type: 'block',
				blocks: ['core/legacy-widget', 'core/paragraph'],
				isMatch: ({ idBase, instance }) => {
					if (!instance?.raw) {
						// Can't transform if raw instance is not shown in REST API.
						return false;
					}
					return idBase === 'category-posts';
				},
				transform: ({ instance }) => {
					return createBlock('tiptip/category-posts-block', widgetToBlockAttributes(instance.raw));
				},

			},
			{
				// A query loop block, as long as this block can show the same posts.
				type: 'block',
				blocks: [ 'core/query' ],
				isMatch: ( attributes ) => isRepresentable( attributes ),
				transform: ( attributes, innerBlocks ) => {
					// The sizes the thumbnail is measured against.
					const settings = select( blockEditorStore ).getSettings();
					const layout = settings.__experimentalFeatures?.layout || {};
					return createBlock( 'tiptip/category-posts-block', queryToBlockAttributes( attributes, innerBlocks, {
						imageDimensions: settings.imageDimensions,
						contentSize: layout.contentSize,
						wideSize: layout.wideSize,
					} ) );
				},
			},
		],
		to: [ transformToPro ],
	},
	// The block itself stays among the widgets, next to "Latest Posts". With the
	// query of the page taken over it is a list for templates, so it is offered
	// there too, in the "Theme" category of the Query Loop.
	variations: [
		{
			name: 'inherited-query',
			title: __( 'Category Posts of the Page', 'category-posts' ),
			description: __( 'Shows the posts of the archive or search page the block is on, with the layout of Category Posts.', 'category-posts' ),
			category: 'theme',
			attributes: { queryType: 'default' },
			keywords: [ __( 'archive', 'category-posts' ), __( 'search', 'category-posts' ), __( 'template', 'category-posts' ) ],
			scope: [ 'inserter' ],
			isActive: ( attributes ) => 'default' === attributes.queryType,
		},
	],
	edit: Edit,
	save: Save,
});
