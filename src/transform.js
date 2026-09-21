/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './../block.json';

/**
 * The block registered by the premium "Term and Category based Posts Widget"
 * plugin (see PRO_URL in cat-posts.php).
 *
 * Nothing here has to test whether that plugin is installed: Gutenberg builds
 * the "Transform to" list by mapping the block names a 'to' transform points
 * at through getBlockType() and then keeps only the ones that resolve to a
 * registered block type (see getBlockTransformItems() in the block-editor
 * store). With the premium plugin missing, its block is never registered, so
 * this transform silently drops out of the block switcher; with the plugin
 * active it appears and runs.
 */
const PRO_BLOCK_NAME = 'tiptip/term-category-posts-block';

/**
 * Attributes of this block that are not handed over to the premium block
 * as they are. Everything else both blocks share under the very same name,
 * type and default, so it is copied over verbatim - the list of what to copy
 * is taken from block.json rather than spelled out here, and createBlock()
 * drops whatever the premium block does not know.
 *
 * - ver / instanceId are per-block bookkeeping. The premium block has its own
 *   version default and hands out its own instance ids (it keeps track of
 *   which editor block owns which id), so both are left to it.
 * - categories / noCatChilds / textDoNotWrapThumb have a premium counterpart
 *   under a different name and shape, see transformAttributes() below.
 * - selectCategories / categorySuggestions are leftovers of the widget's
 *   category picker that the block itself never writes.
 */
const NOT_COPIED_VERBATIM = [
	'ver',
	'instanceId',
	'categories',
	'noCatChilds',
	'textDoNotWrapThumb',
	'selectCategories',
	'categorySuggestions',
];

/**
 * Maps this block's attributes onto the premium block's attributes.
 *
 * @param {Object} attributes This block's attributes.
 *
 * @return {Object} Attributes for the premium block.
 */
function transformAttributes( attributes ) {
	const proAttributes = Object.keys( metadata.attributes ).reduce( ( accumulator, name ) => {
		if ( ! NOT_COPIED_VERBATIM.includes( name ) && undefined !== attributes[ name ] ) {
			accumulator[ name ] = attributes[ name ];
		}
		return accumulator;
	}, {} );

	// This block filters by one category, held as its id in a string, with the
	// empty string meaning "All Categories". The premium block filters by any
	// number of terms of any taxonomy, held as term ids in arrays keyed by
	// taxonomy slug, with a taxonomy missing from that object meaning "Ignore"
	// - which is what "All Categories" amounts to, hence no terms entry at all
	// in that case. The same keyed-by-taxonomy shape holds for excluding child
	// terms, the premium counterpart of this block's noCatChilds.
	if ( attributes.categories ) {
		proAttributes.terms = { category: [ String( attributes.categories ) ] };
		if ( attributes.noCatChilds ) {
			proAttributes.noChildTerms = { category: true };
		}
	}

	// This block offers the thumbnail on the left with text wrapping around it
	// or not; the premium block folds that into one alignment setting that
	// also covers right and inline placement. 'left' is its default, so only
	// the non-wrapping case has to be spelled out.
	if ( attributes.textDoNotWrapThumb ) {
		proAttributes.thumbAlign = 'left_no_wrap';
	}

	return proAttributes;
}

export default {
	type: 'block',
	blocks: [ PRO_BLOCK_NAME ],
	transform: ( attributes ) => createBlock( PRO_BLOCK_NAME, transformAttributes( attributes ) ),
};
