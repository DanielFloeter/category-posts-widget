/**
 * The image size and the aspect ratio of the thumbnails, and the "Crop" dialog
 * behind the toolbar button to pick the aspect ratio with.
 *
 * Both are stored as attributes of their own (imageSize, aspectRatio), but what
 * counts for the output are the pixels the editor works
 * out of them and writes into thumbW/thumbH. The server, the CSS and the front
 * end only ever see those, as before.
 */

/**
 * External dependencies
 */
import ReactCrop from 'react-image-crop';
import 'react-image-crop/dist/ReactCrop.css';

/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __, _x, sprintf } from '@wordpress/i18n';
import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	Modal,
	SelectControl,
	ToolbarButton,
	SVG,
	Path,
} from '@wordpress/components';
import {
	BlockControls,
	store as blockEditorStore,
	useSettings,
} from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { ORIGINAL, parseRatio, ratioChoice, ratioOptions } from './thumb-size';

export { ORIGINAL, parseRatio, ratioChoice, ratioOptions, thumbSize } from './thumb-size';

// The aspect ratios of the core block editor, for an editor without the
// dimensions.aspectRatios settings.
const FALLBACK_RATIOS = [
	{ name: _x( 'Square - 1:1', 'Aspect ratio option', 'category-posts' ), slug: 'square', ratio: '1' },
	{ name: _x( 'Standard - 4:3', 'Aspect ratio option', 'category-posts' ), slug: '4-3', ratio: '4/3' },
	{ name: _x( 'Portrait - 3:4', 'Aspect ratio option', 'category-posts' ), slug: '3-4', ratio: '3/4' },
	{ name: _x( 'Classic - 3:2', 'Aspect ratio option', 'category-posts' ), slug: '3-2', ratio: '3/2' },
	{ name: _x( 'Classic Portrait - 2:3', 'Aspect ratio option', 'category-posts' ), slug: '2-3', ratio: '2/3' },
	{ name: _x( 'Wide - 16:9', 'Aspect ratio option', 'category-posts' ), slug: '16-9', ratio: '16/9' },
	{ name: _x( 'Tall - 9:16', 'Aspect ratio option', 'category-posts' ), slug: '9-16', ratio: '9/16' },
];

// The sample image of the dialog when the list shows no thumbnail.
const PLACEHOLDER = { width: 480, height: 320 };

// The aspectRatio icon of @wordpress/icons, which is not a script of WordPress.
const aspectRatioIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		<Path d="M18.5 5.5h-13c-1.1 0-2 .9-2 2v9c0 1.1.9 2 2 2h13c1.1 0 2-.9 2-2v-9c0-1.1-.9-2-2-2zm.5 11c0 .3-.2.5-.5.5h-13c-.3 0-.5-.2-.5-.5v-9c0-.3.2-.5.5-.5h13c.3 0 .5.2.5.5v9zM6.5 12H8v-2h2V8.5H6.5V12zm9.5 2h-2v1.5h3.5V12H16v2z" />
	</SVG>
);

/**
 * The aspect ratios to pick from, like those of the core blocks: the defaults of
 * core (unless the theme turns them off) and the ones of the theme.
 *
 * @return {Array<{label: string, value: string}>} The options, without "Original".
 */
export function useAspectRatioOptions() {
	const [ defaultRatios, themeRatios, showDefaultRatios ] = useSettings(
		'dimensions.aspectRatios.default',
		'dimensions.aspectRatios.theme',
		'dimensions.defaultAspectRatios'
	);
	const toOption = ( { name, ratio } ) => ( { label: name, value: String( ratio ) } );
	const defaults = defaultRatios || ( undefined === themeRatios ? FALLBACK_RATIOS : [] );
	return [
		...( false !== showDefaultRatios ? defaults.map( toOption ) : [] ),
		...( themeRatios || [] ).map( toOption ),
	];
}

/**
 * The image sizes to pick from, with the width of each. Sizes without a width
 * ("Full Size", or a width of 0 under Settings → Media) are left out: they have
 * no width to set.
 *
 * @return {{options: Array<{label: string, value: string}>, dimensions: Object}} The
 *         options and the imageDimensions setting.
 */
export function useImageSizes() {
	const { imageSizes, imageDimensions } = useSelect( ( select ) => {
		const settings = select( blockEditorStore ).getSettings();
		return {
			imageSizes: settings.imageSizes || [],
			imageDimensions: settings.imageDimensions || {},
		};
	}, [] );

	const options = imageSizes
		.filter( ( { slug } ) => parseInt( imageDimensions[ slug ]?.width, 10 ) > 0 )
		.map( ( { slug, name } ) => ( {
			value: slug,
			/* translators: 1: The name of an image size, 2: its width in pixels. */
			label: sprintf( __( '%1$s (%2$d px)', 'category-posts' ), name, parseInt( imageDimensions[ slug ].width, 10 ) ),
		} ) );
	return { options, dimensions: imageDimensions };
}

/**
 * The largest centered frame of an aspect ratio on an image, in percent.
 *
 * @param {number} ratio  The aspect ratio, width by height.
 * @param {Object} media  The width and height of the image.
 * @return {Object} The crop of react-image-crop.
 */
function centeredCrop( ratio, media ) {
	const mediaRatio = media.width / media.height;
	const width = ratio > mediaRatio ? 100 : ( ratio / mediaRatio ) * 100;
	const height = ratio > mediaRatio ? ( mediaRatio / ratio ) * 100 : 100;
	return { unit: '%', x: ( 100 - width ) / 2, y: ( 100 - height ) / 2, width, height };
}

/**
 * The "Crop" dialog, built like "Edit media" → "Crop" of core: the sample image
 * with a frame on the left, the aspect ratio on the right, "Cancel" and "Apply"
 * below. The frame always stays in the middle, as the thumbnails are cut from
 * the middle of each image (object-fit: cover).
 *
 * @param {Object}   props
 * @param {string}   props.title       The title of the dialog.
 * @param {string}   props.image       The URL of the sample image, empty for none.
 * @param {string}   props.aspectRatio The stored aspect ratio.
 * @param {number}   props.width       The width of the thumbnails (thumbW).
 * @param {number}   props.height      The height of the thumbnails (thumbH).
 * @param {Function} props.onApply     Called with the new aspect ratio.
 * @param {Function} props.onClose     Called when the dialog closes.
 */
function CropModal( { title, image, aspectRatio, width, height, onApply, onClose } ) {
	const presets = useAspectRatioOptions();

	// What the select shows: a stored ratio which is no preset is a free one, and
	// without one the ratio of the pixels so far (none with a height of 0).
	const isPreset = presets.some( ( option ) => option.value === aspectRatio );
	const initialChoice = ( () => {
		if ( ORIGINAL === aspectRatio || ( ! aspectRatio && ! height ) ) {
			return ORIGINAL;
		}
		return isPreset ? aspectRatio : '';
	} )();
	const initialRatio = parseRatio( aspectRatio ) || ( width && height ? width / height : 1 );

	const [ choice, setChoice ] = useState( initialChoice );
	// The size of the sample image is known once it has loaded.
	const [ media, setMedia ] = useState( image ? null : PLACEHOLDER );
	const [ crop, setCrop ] = useState( image ? null : centeredCrop( initialRatio, PLACEHOLDER ) );

	const fixedRatio = parseRatio( choice );
	const showsFrame = ORIGINAL !== choice;

	// The frame once the size of the image is known.
	const onMediaLoad = ( size ) => {
		setMedia( size );
		setCrop( centeredCrop( initialRatio, size ) );
	};

	const onChoice = ( value ) => {
		setChoice( value );
		if ( media && ORIGINAL !== value ) {
			setCrop( centeredCrop( parseRatio( value ) || ( crop ? cropRatio( crop, media ) : initialRatio ), media ) );
		}
	};

	// Dragging changes the size of the frame only, it stays in the middle.
	const onCropChange = ( pixelCrop, percentCrop ) => {
		setCrop( {
			...percentCrop,
			x: ( 100 - percentCrop.width ) / 2,
			y: ( 100 - percentCrop.height ) / 2,
		} );
	};

	const apply = () => {
		if ( ORIGINAL === choice || fixedRatio ) {
			onApply( choice );
		} else if ( crop && media ) {
			// A free frame is stored by its ratio, with two decimals.
			onApply( String( Math.round( cropRatio( crop, media ) * 100 ) / 100 ) );
		}
		onClose();
	};

	const sample = image ? (
		<img
			src={ image }
			alt=""
			className="cpwp-crop-modal__image"
			onLoad={ ( event ) => onMediaLoad( {
				width: event.currentTarget.naturalWidth,
				height: event.currentTarget.naturalHeight,
			} ) }
			onError={ () => onMediaLoad( PLACEHOLDER ) }
		/>
	) : (
		<div
			className="cpwp-crop-modal__placeholder"
			style={ { aspectRatio: `${ PLACEHOLDER.width } / ${ PLACEHOLDER.height }` } }
		/>
	);

	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			className="cpwp-crop-modal"
			size="large"
		>
			<div className="cpwp-crop-modal__body">
				<div className="cpwp-crop-modal__canvas">
					{ showsFrame && crop ? (
						<ReactCrop
							crop={ crop }
							aspect={ fixedRatio || undefined }
							onChange={ onCropChange }
							keepSelection
							ruleOfThirds
						>
							{ sample }
						</ReactCrop>
					) : sample }
				</div>
				<div className="cpwp-crop-modal__sidebar">
					<SelectControl
						label={ __( 'Aspect ratio', 'category-posts' ) }
						value={ choice }
						onChange={ onChoice }
						options={ ratioOptions( presets ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<p className="cpwp-crop-modal__help">
						{ __( 'Applies to the thumbnails of all posts in the list. Each image is cut from its middle.', 'category-posts' ) }
					</p>
				</div>
			</div>
			<Flex justify="flex-end" className="cpwp-crop-modal__footer">
				<Button variant="tertiary" onClick={ onClose } __next40pxDefaultSize>
					{ __( 'Cancel', 'category-posts' ) }
				</Button>
				<Button variant="primary" onClick={ apply } __next40pxDefaultSize>
					{ __( 'Apply', 'category-posts' ) }
				</Button>
			</Flex>
		</Modal>
	);
}

/**
 * The ratio of a frame in percent on an image, width by height.
 *
 * @param {Object} crop  The crop in percent.
 * @param {Object} media The width and height of the image.
 * @return {number} The ratio.
 */
function cropRatio( crop, media ) {
	return ( crop.width * media.width ) / ( crop.height * media.height || 1 );
}

/**
 * The "Crop" button in the block toolbar and its dialog.
 * The dialog is opened from the "Aspect ratio" in the panel as well, so which one
 * is open is kept by the block.
 *
 * @param {Object}      props
 * @param {Array}       props.buttons Each with an id, a label (the text of the
 *                                    button and the title of the dialog), an
 *                                    optional className and the props of the
 *                                    dialog: aspectRatio, width, height, onApply.
 * @param {Object|null} props.open    The open dialog, { id, image }.
 * @param {Function}    props.onOpen  Called with the id of a button clicked.
 * @param {Function}    props.onClose Called when the dialog closes.
 */
export function CropToolbar( { buttons, open, onOpen, onClose } ) {
	if ( ! buttons.length ) {
		return null;
	}
	const opened = open && buttons.find( ( button ) => button.id === open.id );
	return (
		<>
			{/* Straight in the group, a group of its own inside it adds a second line. */}
			<BlockControls group="other">
				{ buttons.map( ( button ) => (
					<ToolbarButton
						key={ button.id }
						icon={ aspectRatioIcon }
						label={ button.label }
						className={ button.className }
						onClick={ () => onOpen( button.id ) }
					/>
				) ) }
			</BlockControls>
			{ opened && (
				<CropModal
					title={ opened.label }
					image={ open.image }
					aspectRatio={ opened.aspectRatio }
					width={ opened.width }
					height={ opened.height }
					onApply={ opened.onApply }
					onClose={ onClose }
				/>
			) }
		</>
	);
}

/**
 * The aspect ratio in the "Thumbnail" panel: a select of the ratios and, beside
 * it, a button which opens the "Crop" dialog, where a frame can be drawn freely.
 * "Free" in the select only leaves the height as it is. A free ratio, e.g. 1.62,
 * shows as "Free".
 *
 * @param {Object}   props
 * @param {string}   props.label       The label.
 * @param {string}   props.buttonLabel The label of the button, e.g. "Crop".
 * @param {Array}    props.presets     The presets, see useAspectRatioOptions().
 * @param {string}   props.value       The stored aspect ratio.
 * @param {Function} props.onChange    Called with a new aspect ratio.
 * @param {Function} props.onOpen      Called when the button is clicked.
 */
export function AspectRatioControl( { label, buttonLabel, presets, value, onChange, onOpen } ) {
	const ratios = ratioOptions( presets );
	return (
		<Flex align="flex-end" gap={ 2 } className="cpwp-aspect-ratio-control">
			<FlexBlock>
				<SelectControl
					label={ label }
					value={ ratioChoice( ratios, value ) }
					options={ ratios }
					onChange={ onChange }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</FlexBlock>
			<FlexItem>
				<Button
					icon={ aspectRatioIcon }
					label={ buttonLabel }
					onClick={ onOpen }
					variant="secondary"
					__next40pxDefaultSize
				/>
			</FlexItem>
		</Flex>
	);
}
