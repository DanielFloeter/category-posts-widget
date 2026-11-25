/**
 * The pixels of the thumbnails worked out of the image size and the aspect ratio,
 * without anything of the editor, so they can be tested on their own.
 */

/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';

// The value of aspectRatio which leaves each image its own ratio (thumbH = 0).
export const ORIGINAL = 'original';

/**
 * The number behind an aspect ratio, width by height.
 *
 * @param {string} ratio E.g. "16/9", "1" or "1.62". Empty or "original" for none.
 * @return {number} The ratio, 0 when there is none.
 */
export function parseRatio( ratio ) {
	if ( ! ratio || ORIGINAL === ratio ) {
		return 0;
	}
	const [ width, height = '1' ] = String( ratio ).split( '/' );
	const value = parseFloat( width ) / parseFloat( height );
	return Number.isFinite( value ) && value > 0 ? value : 0;
}

/**
 * The width and height of the thumbnails after the image size or the aspect
 * ratio has changed. The width comes from the image size, the height from the
 * aspect ratio, without one from the ratio of the pixels so far.
 *
 * @param {Object} args
 * @param {string} args.imageSize   The slug of the image size, empty for none.
 * @param {string} args.aspectRatio The aspect ratio, empty for none.
 * @param {number} args.width       thumbW so far.
 * @param {number} args.height      thumbH so far.
 * @param {Object} args.dimensions  The imageDimensions setting of the editor,
 *                                  { slug: { width, height } }.
 * @return {{width: number, height: number}} The new thumbW and thumbH.
 */
export function thumbSize( { imageSize, aspectRatio, width, height, dimensions = {} } ) {
	// A size without a width of its own (no width set, "Full Size") keeps the width.
	const sizeWidth = imageSize ? parseInt( dimensions[ imageSize ]?.width, 10 ) || 0 : 0;
	const newWidth = sizeWidth || width;

	if ( ORIGINAL === aspectRatio ) {
		return { width: newWidth, height: 0 };
	}

	const ratio = parseRatio( aspectRatio );
	if ( ratio ) {
		// Without a width there is nothing to work the height out of.
		return { width: newWidth, height: newWidth ? Math.round( newWidth / ratio ) : height };
	}

	// No ratio chosen: the one of the pixels so far stays, a height of 0 as well.
	if ( newWidth !== width && width && height ) {
		return { width: newWidth, height: Math.round( ( newWidth * height ) / width ) };
	}
	return { width: newWidth, height };
}

/**
 * The options of a select for the aspect ratio: "Free", "Original" and the
 * presets. A free ratio is stored as a number, e.g. 1.62, and shows as "Free".
 *
 * @param {Array} presets The presets, see useAspectRatioOptions().
 * @return {Array<{label: string, value: string}>} The options.
 */
export function ratioOptions( presets ) {
	return [
		{ label: _x( 'Free', 'Aspect ratio option', 'category-posts' ), value: '' },
		{ label: __( 'Original', 'category-posts' ), value: ORIGINAL },
		...presets,
	];
}

/**
 * The option a stored aspect ratio shows as: a free ratio as "Free".
 *
 * @param {Array}  options The options, see ratioOptions().
 * @param {string} value   The stored aspect ratio.
 * @return {string} The value of the option.
 */
export function ratioChoice( options, value ) {
	return options.some( ( option ) => option.value === value ) ? value : '';
}
