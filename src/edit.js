/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */
import { Children, useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import {
	ComboboxControl,
	TextControl,
	ToggleControl,
	SelectControl,
	TextareaControl,
	RadioControl,
	QueryControls,
	__experimentalToggleGroupControl, ToggleGroupControl as stableToggleGroupControl,
	__experimentalToggleGroupControlOption, ToggleGroupControlOption as stableToggleGroupControlOption,
	__experimentalNumberControl, NumberControl as stableNumberControl,
	__experimentalInputControlSuffixWrapper, InputControlSuffixWrapper as stableInputControlSuffixWrapper,
	__experimentalToolsPanel, ToolsPanel as stableToolsPanel,
	__experimentalToolsPanelItem, ToolsPanelItem as stableToolsPanelItem,
	DatePicker,
	Dropdown,
	Button,
	PanelRow,
	Flex,
	FlexBlock,
	BaseControl,
} from '@wordpress/components';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';

/**
 * Internal dependencies
 */
import TemplateControl from './template-control';
import metadata from './../block.json';
import './editor.scss';

export const ToggleGroupControl = __experimentalToggleGroupControl || stableToggleGroupControl;
export const ToggleGroupControlOption = __experimentalToggleGroupControlOption || stableToggleGroupControlOption;
export const NumberControl = __experimentalNumberControl || stableNumberControl;
const InputControlSuffixWrapper = __experimentalInputControlSuffixWrapper || stableInputControlSuffixWrapper || 'span';
const ToolsPanel = __experimentalToolsPanel || stableToolsPanel;
const ToolsPanelItem = __experimentalToolsPanelItem || stableToolsPanelItem;

/*
 * The attributes behind each entry of a ToolsPanel menu. "Reset" sets them back to
 * their defaults from block.json.
 */
const TITLE = [ 'hideTitle', 'title' ];
const TITLE_LEVEL = [ 'titleLevel' ];
const TITLE_LINK = [ 'titleLink', 'titleLinkUrl', 'titleLinkTarget' ];
const NO_RESULTS = [ 'noMatchHandling', 'noMatchText' ];
const LOAD_MORE = [ 'enableLoadmore', 'loadmoreScrollTo', 'loadmoreText', 'loadingText' ];
const FOOTER_LINK = [ 'footerLinkText', 'footerLink', 'footerLinkTarget' ];
const CATEGORY = [ 'categories' ];
const ORDER = [ 'orderBy', 'order' ];
const STATUS = [ 'status' ];
const NUMBER_OF_POSTS = [ 'num', 'offset' ];
// Hiding the date range clears its dates as well.
const DATE_RANGE = [ 'dateRange', 'daysAgo', 'startDate', 'endDate' ];
const START_DATE = [ 'startDate' ];
const END_DATE = [ 'endDate' ];
const EXCLUDE_CURRENT_POST = [ 'excludeCurrentPost' ];
const HIDE_NO_THUMB = [ 'hideNoThumb' ];
const STICKY = [ 'sticky' ];
const FILTER = [
	...CATEGORY, ...ORDER, ...STATUS, ...NUMBER_OF_POSTS, ...DATE_RANGE,
	...EXCLUDE_CURRENT_POST, ...HIDE_NO_THUMB, ...STICKY,
];
const BUILT_IN_CSS = [ 'disableCss', 'disableFontStyles', 'disableThemeStyles' ];
const TEMPLATE = [ 'template' ];
const ITEM_TITLE = [ 'itemTitleLevel', 'itemTitleLines' ];
const EXCERPT = [ 'excerptLines', 'excerptRadio' ];
const MORE_LINK = [ 'excerptMoreText' ];
const DATE_FORMAT = [ 'presetDateFormat', 'dateFormat', 'datePastTime' ];
const THUMBNAIL = [ 'thumbW', 'thumbH', 'textDoNotWrapThumb' ];
const THUMBNAIL_TOOLS = [ 'thumbHover', 'showPostFormat', 'everythingIsLink', 'thumbSymbols' ];
const POST_DETAILS = [ ...TEMPLATE, ...ITEM_TITLE, ...EXCERPT, ...MORE_LINK, ...DATE_FORMAT, ...THUMBNAIL, ...THUMBNAIL_TOOLS ];

// The entries a ToolsPanel shows only when chosen under "Tools", by the id they are
// stored with in the shownTools attribute.
const OPTIONAL_TOOLS = {
	titleLevel: TITLE_LEVEL,
	titleLink: TITLE_LINK,
	order: ORDER,
	status: STATUS,
	numberOfPosts: NUMBER_OF_POSTS,
	dateRange: DATE_RANGE,
	excludeCurrentPost: EXCLUDE_CURRENT_POST,
	hideNoThumb: HIDE_NO_THUMB,
	sticky: STICKY,
	loadMore: LOAD_MORE,
	// One entry for each setting of the thumbnail, e.g. thumbHover.
	...Object.fromEntries( THUMBNAIL_TOOLS.map( ( key ) => [ key, [ key ] ] ) ),
};

/**
 * The defaults of attributes from block.json, to pass to setAttributes().
 *
 * @param {string[]} keys The attribute names.
 * @return {Object} The default of each attribute by its name.
 */
const defaultsOf = ( keys ) => Object.fromEntries(
	keys.map( ( key ) => [ key, metadata.attributes[ key ].default ] )
);

/**
 * Whether one of the attributes differs from its default in block.json. Arrays and
 * objects are compared by their content. A number field stores its value as a
 * string, so "10" counts as the default 10.
 *
 * @param {Object}   attributes The block attributes.
 * @param {string[]} keys       The attribute names.
 * @return {boolean} True if one of them is set to another value.
 */
const differsFromDefault = ( attributes, keys ) => keys.some( ( key ) => {
	const value = attributes[ key ];
	const defaultValue = metadata.attributes[ key ].default;
	if ( 'object' === typeof value || 'object' === typeof defaultValue ) {
		return JSON.stringify( value ) !== JSON.stringify( defaultValue );
	}
	return String( value ) !== String( defaultValue );
} );

/**
 * A unit shown at the right end of a number field, as its suffix.
 *
 * @param {string} text The unit, e.g. "px".
 */
const unit = ( text ) => <InputControlSuffixWrapper>{ text }</InputControlSuffixWrapper>;

/**
 * Two related fields side by side, e.g. a width and a height. A field left out by
 * its condition leaves the other one the full width.
 *
 * @param {Object} props          The component props.
 * @param {Array}  props.children The fields.
 */
function ControlPair( { children } ) {
	return (
		<Flex align="flex-end" gap={ 4 }>
			{ Children.toArray( children ).map( ( child ) => (
				<FlexBlock key={ child.key }>{ child }</FlexBlock>
			) ) }
		</Flex>
	);
}

/**
 * A date picked from a calendar which opens below a button, like the post date of
 * the editor. The button shows the date in the date format of the site.
 *
 * @param {Object}   props             The component props.
 * @param {string}   props.id          The id of the button, which the label is for.
 * @param {string}   props.label       The label.
 * @param {string}   props.buttonLabel What the button does, for screen readers.
 * @param {string}   props.value       The date, empty when none is set.
 * @param {Function} props.onChange    Called with the new date.
 */
function DateControl( { id, label, buttonLabel, value, onChange } ) {
	const settings = getDateSettings();
	const formatted = value ? dateI18n( settings.formats.date, value ) : '';

	return (
		<BaseControl id={ id } label={ label } __nextHasNoMarginBottom>
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						id={ id }
						className="cpwp-date-control__toggle"
						variant="secondary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						aria-label={ formatted
							? sprintf(
								/* translators: 1: what the button does, e.g. "Choose start date". 2: the date. */
								__( '%1$s, currently %2$s', 'category-posts' ),
								buttonLabel,
								formatted
							)
							: buttonLabel }
						__next40pxDefaultSize
					>
						{ formatted || __( 'Not set', 'category-posts' ) }
					</Button>
				) }
				renderContent={ () => (
					<DatePicker
						currentDate={ value }
						onChange={ onChange }
						startOfWeek={ settings.l10n.startOfWeek }
					/>
				) }
			/>
		</BaseControl>
	);
}

export default function Edit({ attributes, setAttributes, clientId }) {
	const {
		hideTitle, title, titleLink, titleLinkUrl, titleLinkTarget, titleLevel,
		order, orderBy, categories, status, num, offset, dateRange, startDate, endDate, daysAgo, excludeCurrentPost, hideNoThumb, sticky,
		template, itemTitleLevel, itemTitleLines, excerptRadio, excerptLines, excerptMoreText,
		thumbW, thumbH, thumbHover, thumbSymbols, showPostFormat, textDoNotWrapThumb, everythingIsLink, presetDateFormat, dateFormat, datePastTime,
		disableCss, disableFontStyles, disableThemeStyles, noMatchHandling, noMatchText, enableLoadmore, loadmoreScrollTo, loadmoreText, loadingText,
		footerLinkText, footerLink, footerLinkTarget, instanceId, shownTools
	} = attributes;
	const blockProps = useBlockProps({
		className: disableThemeStyles ? 'widget-title' : '',
	});

	// Give every instance of this block a stable ID that is persisted into the
	// block's attributes (and therefore into the post content) the first time
	// it is inserted. wp_unique_id() on the PHP side is only unique within a
	// single request, so it collides between multiple instances of this block
	// when each is rendered via its own ServerSideRender/REST request in the
	// editor, or when the same instance is rendered more than once per request
	// (excerpts, REST API output, page caching, etc). Reading a value that was
	// frozen in at insert time avoids all of that.
	const generatedInstanceId = useInstanceId( Edit, '', instanceId );
	useEffect( () => {
		if ( instanceId !== generatedInstanceId ) {
			setAttributes( { instanceId: generatedInstanceId } );
		}
	}, [ instanceId, generatedInstanceId ] );

	// The 'cpwp-wrap-text' class the excerpt-lines CSS hooks on, and the image-ratio
	// height, are normally corrected on the front end by equal_cover_content_height()
	// on wp_footer/admin_footer once the real rendered heights are known. Neither hook
	// runs for the REST request the ServerSideRender preview is built from, and a
	// <script> element inside that markup is not executed by React either
	// (dangerouslySetInnerHTML does not run embedded scripts) - so
	// render_category_posts_block() hands the same flags to the preview as inert
	// JSON (the '.cpwp-block-preview-config' node), and this effect applies them.
	const previewRef = useRef( null );
	useEffect( () => {
		const host = previewRef.current;
		if ( ! host ) {
			return;
		}

		let timer = null;

		// Port of cat_posts_namespace.layout_wrap_text.add() (see cat-posts.php). Its
		// position decides the layout: on the stage the text wraps around the
		// floating thumbnail, on the paragraph it does not. itemHTML() always puts it
		// on the stage when wrapping is enabled, and this refines that guess once the
		// rendered heights are known. Uses plain DOM instead of jQuery because the
		// preview lives in the editor's iframe while jQuery here belongs to the outer
		// document, and its offset based measuring would be wrong across that boundary.
		const placeWrapText = ( item ) => {
			const text = item.querySelector( 'p.cpwp-excerpt-text' );
			if ( ! text ) {
				return;
			}

			const stage = text.closest( '.cpwp-wrap-text-stage' );
			if ( ! stage ) {
				return;
			}

			// Measure from a defined state: the class on the paragraph, so its height
			// is the clamped text height no matter what the server side left behind.
			stage.classList.remove( 'cpwp-wrap-text' );
			text.classList.add( 'cpwp-wrap-text' );

			const thumb = item.querySelector( '.cat-post-thumbnail' );
			const thumbHeight = thumb ? thumb.getBoundingClientRect().height : 0;

			if ( text.getBoundingClientRect().height < thumbHeight ) {
				// The text is shorter than the thumbnail, no wrapping needed and the
				// class is already on the paragraph.
				return;
			}

			text.classList.remove( 'cpwp-wrap-text' );
			stage.classList.add( 'cpwp-wrap-text' );
		};

		// Port of cat_posts_namespace.layout_img_size.replace() (see cat-posts.php).
		const fixImageRatio = ( item ) => {
			item.querySelectorAll( 'img' ).forEach( ( img ) => {
				const originalWidth = parseInt( img.getAttribute( 'data-cat-posts-width' ), 10 );
				const originalHeight = parseInt( img.getAttribute( 'data-cat-posts-height' ), 10 );
				if ( ! originalWidth || ! originalHeight ) {
					return;
				}

				const width = img.getBoundingClientRect().width;
				if ( width && width < originalWidth ) {
					img.style.height = ( width * originalHeight / originalWidth ) + 'px';
				} else {
					img.style.height = '';
				}
			} );
		};

		const handleItem = ( item, config ) => {
			if ( config.imgSize ) {
				fixImageRatio( item );
			}
			if ( config.wrapText ) {
				placeWrapText( item );
			}
		};

		const applyOnce = ( config ) => {
			if ( ! config.wrapText && ! config.imgSize ) {
				return;
			}

			host.querySelectorAll( '.cat-post-item' ).forEach( ( item ) => {
				// Measure right away, and again once an image that was still on its
				// way has arrived - waiting for the images first would drop the item
				// completely whenever its load event had already passed or never
				// comes at all, for instance for a lazily loaded image below the fold.
				handleItem( item, config );

				item.querySelectorAll( 'img' ).forEach( ( img ) => {
					if ( img.complete && img.naturalWidth ) {
						return;
					}
					img.addEventListener( 'load', () => handleItem( item, config ), { once: true } );
				} );
			} );
		};

		// The very first pass can land before the style island of the freshly
		// inserted markup has been applied, and then measures the unclamped instead
		// of the line-clamped text height, which flips the decision. The front end
		// has the same problem and solves it by running on ready, load and resize;
		// repeat the pass the same way here. It is idempotent, so repeating costs
		// nothing but a reflow.
		// Each of the three passes below re-reads the config from the DOM instead of
		// sharing one snapshot: ServerSideRender can swap in a newer render (a new
		// config node) while an older pass's rAF/timeout is still pending, and
		// applying that stale config to the then-current markup would undo what the
		// newer render already got right.
		const run = () => {
			const configNode = host.querySelector( '.cpwp-block-preview-config' );
			if ( ! configNode ) {
				return;
			}

			let config;
			try {
				config = JSON.parse( configNode.textContent );
			} catch ( e ) {
				return;
			}

			applyOnce( config );
		};

		const apply = () => {
			run();
			window.requestAnimationFrame( run );
			window.setTimeout( run, 400 );
		};

		const schedule = () => {
			window.clearTimeout( timer );
			timer = window.setTimeout( apply, 200 );
		};

		// ServerSideRender replaces the preview markup with a raw innerHTML swap, not
		// a reconciled React update, so this has to watch for that DOM change itself
		// rather than relying on a dependency array.
		const observer = new window.MutationObserver( () => schedule() );
		observer.observe( host, { childList: true, subtree: true } );
		schedule();

		// The preview has its own window when the editor renders its canvas in an iframe.
		const previewWindow = host.ownerDocument.defaultView || window;
		previewWindow.addEventListener( 'resize', schedule );

		return () => {
			observer.disconnect();
			previewWindow.removeEventListener( 'resize', schedule );
			window.clearTimeout( timer );
		};
	}, [] );

	const categoriesList = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords(
			'taxonomy',
			'category',
			{
				per_page: -1,
			},
		);
	}, [] ); 

	const categoryOptions = [
		{
			value: '',
			label: __( 'All Categories', 'category-posts' ),
		},
		...(categoriesList || []).map( ( category ) => ( {
			label: category.name,
			value: String( category.id ),
		})),
	];

	const statusOptions = [
		{
			value: 'default',
			label: __( 'WordPress Default', 'category-posts' ),
		},
		{
			value: 'publish',
			label: __( 'Published', 'category-posts' ),
		},
		{
			value: 'future',
			label: __( 'Scheduled', 'category-posts' ),
		},
		{
			value: 'private',
			label: __( 'Private', 'category-posts' ),
		},
		{
			value: 'publish,future',
			label: __( 'Published or Scheduled', 'category-posts' ),
		},
		{
			value: 'private,publish',
			label: __( 'Published or Private', 'category-posts' ),
		},
		{
			value: 'private,future',
			label: __( 'Private or Scheduled', 'category-posts' ),
		},
		{
			value: 'private,publish,future',
			label: __( 'Published, Private or Scheduled', 'category-posts' ),
		},
	];

	const dateRangeOptions = [
		{
			value: 'off',
			label: __( 'Off', 'category-posts' ),
		},
		{
			value: 'days_ago',
			label: __( 'Days ago', 'category-posts' ),
		},
		{
			value: 'between_dates',
			label: __( 'Between dates', 'category-posts' ),
		},
	];

	/*
	 * A ToolsPanel shows an entry from "Tools" only while one of its values differs
	 * from the default, so one chosen with its defaults would be gone after saving.
	 * The shownTools attribute keeps it, and choosing it changes the block.
	 */
	const optionalTool = ( id ) => ( {
		hasValue: () => shownTools.includes( id ) || differsFromDefault( attributes, OPTIONAL_TOOLS[ id ] ),
		onSelect: () => setAttributes( { shownTools: [ ...shownTools, id ] } ),
		onDeselect: () => setAttributes( {
			...defaultsOf( OPTIONAL_TOOLS[ id ] ),
			shownTools: shownTools.filter( ( shown ) => shown !== id ),
		} ),
	} );

	// Which entries are shown does not change the preview, so it is not loaded again.
	const { shownTools: omitted, ...previewAttributes } = attributes;

	// "Reset all" of a ToolsPanel, which hides its entries from "Tools" as well.
	const resetPanel = ( keys ) => setAttributes( {
		...defaultsOf( keys ),
		shownTools: shownTools.filter( ( id ) => ! ( OPTIONAL_TOOLS[ id ] || [] ).some( ( key ) => keys.includes( key ) ) ),
	} );

	return (
		<>
			<InspectorControls>
				<ToolsPanel
					className="cpwp-tools-panel"
					label={ __( 'Title', 'category-posts' ) }
					resetAll={ () => resetPanel( [ ...TITLE, ...TITLE_LEVEL, ...TITLE_LINK ] ) }
				>
					<ToolsPanelItem
						label={ __( 'Title', 'category-posts' ) }
						hasValue={ () => differsFromDefault( attributes, TITLE ) }
						onDeselect={ () => setAttributes( defaultsOf( TITLE ) ) }
						isShownByDefault
					>
						<ToggleControl
							label={__('Hide title', 'category-posts')}
							checked={hideTitle}
							onChange={() =>
								setAttributes({
									hideTitle: !hideTitle,
								})
							}
							__nextHasNoMarginBottom
						/>
						{ !hideTitle && (
							<TextControl
								label={__('Title', 'category-posts')}
								value={title}
								onChange={(title) =>
									setAttributes({
										title,
									})}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						) }
					</ToolsPanelItem>
					{/* A hidden title has neither a heading level nor a link. */}
					{ !hideTitle && (
						<ToolsPanelItem
							label={ __( 'Heading level', 'category-posts' ) }
							{ ...optionalTool( 'titleLevel' ) }
						>
							<ToggleGroupControl
								label={__('Heading level', 'category-posts')}
								value={titleLevel}
								isBlock
								isAdaptiveWidth
								onChange={(titleLevel) =>
									setAttributes({
										titleLevel,
									})}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							>
								<ToggleGroupControlOption value="Initial" label={__('Text', 'category-posts')} />
								<ToggleGroupControlOption value="H1" label="H1" />
								<ToggleGroupControlOption value="H2" label="H2" />
								<ToggleGroupControlOption value="H3" label="H3" />
								<ToggleGroupControlOption value="H4" label="H4" />
								<ToggleGroupControlOption value="H5" label="H5" />
								<ToggleGroupControlOption value="H6" label="H6" />
							</ToggleGroupControl>
						</ToolsPanelItem>
					) }
					{ !hideTitle && (
						<ToolsPanelItem
							label={ __( 'Link the title', 'category-posts' ) }
							{ ...optionalTool( 'titleLink' ) }
						>
							<ToggleControl
								label={__('Link the title', 'category-posts')}
								checked={titleLink}
								onChange={() =>
									setAttributes({
										titleLink: !titleLink,
									})
								}
								__nextHasNoMarginBottom
							/>
							{ titleLink && (
								<>
									<TextControl
										label={__('Title link URL', 'category-posts')}
										value={titleLinkUrl}
										onChange={(titleLinkUrl) =>
											setAttributes({
												titleLinkUrl,
											})}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
									<ToggleControl
										label={__('Open title link in new tab', 'category-posts')}
										checked={titleLinkTarget}
										onChange={() =>
											setAttributes({
												titleLinkTarget: !titleLinkTarget,
											})
										}
										__nextHasNoMarginBottom
									/>
								</>
							) }
						</ToolsPanelItem>
					) }
				</ToolsPanel>
				<ToolsPanel
					className="cpwp-tools-panel"
					label={ __( 'Filter', 'category-posts' ) }
					resetAll={ () => resetPanel( FILTER ) }
				>
					<ToolsPanelItem
						label={ __( 'Category', 'category-posts' ) }
						hasValue={ () => differsFromDefault( attributes, CATEGORY ) }
						onDeselect={ () => setAttributes( defaultsOf( CATEGORY ) ) }
						isShownByDefault
					>
						<ComboboxControl
							label={ __( 'Category', 'category-posts' ) }
							options={ categoryOptions }
							value={ categories }
							onChange={( newCategory) =>
								setAttributes({
									categories: newCategory,
								})
							}
							allowReset={ false }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Order', 'category-posts' ) }
						{ ...optionalTool( 'order' ) }
					>
						<SelectControl
							label={ __( 'Order By', 'category-posts' ) }
							value={ orderBy }
							options={[
								{ label: __( 'Date', 'category-posts' ), value: 'date' },
								{ label: __( 'Title', 'category-posts' ), value: 'title' },
								{ label: __( 'Number of comments', 'category-posts' ), value: 'comment_count' },
								{ label: __( 'Random', 'category-posts' ), value: 'rand' },
							]}
							onChange={(value) =>
								setAttributes({ orderBy: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						{/* A random order has no direction. */}
						{ 'rand' !== orderBy && (
							<ToggleControl
								label={ __( 'Order', 'category-posts' ) }
								checked={ order }
								onChange={(value) =>
									setAttributes({ order: value })
								}
								__nextHasNoMarginBottom
							/>
						) }
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Status', 'category-posts' ) }
						{ ...optionalTool( 'status' ) }
					>
						<ComboboxControl
							label={ __( 'Status', 'category-posts' ) }
							options={ statusOptions }
							value={ status }
							onChange={( newStatus) =>
								setAttributes({
									status: newStatus,
								})
							}
							allowReset={ false }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Number of posts / Start with post', 'category-posts' ) }
						{ ...optionalTool( 'numberOfPosts' ) }
					>
						<ControlPair>
							<NumberControl
								label={ __( 'Number of posts to show', 'category-posts' ) }
								onChange={( newNum) =>
									setAttributes({
										num: newNum,
									})
								}
								value={ num }
								min={1}
								allowReset={ false }
								__next40pxDefaultSize
							/>
							<NumberControl
								label={ __( 'Start with post', 'category-posts' ) }
								onChange={( newOffset) =>
									setAttributes({
										offset: parseInt( newOffset ) || 1,
									})
								}
								value={ offset }
								min={1}
								allowReset={ false }
								__next40pxDefaultSize
							/>
						</ControlPair>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Date range', 'category-posts' ) }
						{ ...optionalTool( 'dateRange' ) }
					>
						<ComboboxControl
							label={ __( 'Date Range', 'category-posts' ) }
							options={ dateRangeOptions }
							value={ dateRange }
							onChange={( newDateRange) =>
								setAttributes({
									dateRange: newDateRange,
								})
							}
							allowReset={ false }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						{dateRange === 'days_ago' && (
							<NumberControl
								label={ __( 'Up to', 'category-posts' ) }
								onChange={( newDaysAgo) =>
									setAttributes({
										daysAgo: parseInt( newDaysAgo ) || 1,
									})
								}
								value={ daysAgo }
								min={1}
								suffix={ unit( __( 'days', 'category-posts' ) ) }
								allowReset={ false }
								__next40pxDefaultSize
							/>
						)}
					</ToolsPanelItem>
					{ 'between_dates' === dateRange && (
						<>
							<ToolsPanelItem
								className="single-column"
								label={ __( 'After', 'category-posts' ) }
								hasValue={ () => differsFromDefault( attributes, START_DATE ) }
								onDeselect={ () => setAttributes( defaultsOf( START_DATE ) ) }
								isShownByDefault
							>
								<DateControl
									id={ `cpwp-start-date-${ clientId }` }
									label={ __( 'After', 'category-posts' ) }
									buttonLabel={ __( 'Choose start date', 'category-posts' ) }
									value={ startDate }
									onChange={( newStartDate) =>
										setAttributes({
											startDate: newStartDate,
										})
									}
								/>
							</ToolsPanelItem>
							<ToolsPanelItem
								className="single-column"
								label={ __( 'Before', 'category-posts' ) }
								hasValue={ () => differsFromDefault( attributes, END_DATE ) }
								onDeselect={ () => setAttributes( defaultsOf( END_DATE ) ) }
								isShownByDefault
							>
								<DateControl
									id={ `cpwp-end-date-${ clientId }` }
									label={ __( 'Before', 'category-posts' ) }
									buttonLabel={ __( 'Choose end date', 'category-posts' ) }
									value={ endDate }
									onChange={( newEndDate) =>
										setAttributes({
											endDate: newEndDate,
										})
									}
								/>
							</ToolsPanelItem>
						</>
					) }
					<ToolsPanelItem
						label={ __( 'Exclude current post', 'category-posts' ) }
						{ ...optionalTool( 'excludeCurrentPost' ) }
					>
						<ToggleControl
							label={__('Exclude current post', 'category-posts')}
							checked={excludeCurrentPost}
							onChange={() =>
								setAttributes({
									excludeCurrentPost: !excludeCurrentPost,
								})
							}
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Hide posts which have no thumbnail', 'category-posts' ) }
						{ ...optionalTool( 'hideNoThumb' ) }
					>
						<ToggleControl
							label={__('Hide posts which have no thumbnail', 'category-posts')}
							checked={hideNoThumb}
							onChange={() =>
								setAttributes({
									hideNoThumb: !hideNoThumb,
								})
							}
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Start with sticky posts', 'category-posts' ) }
						{ ...optionalTool( 'sticky' ) }
					>
						<ToggleControl
							label={__('Start with sticky posts', 'category-posts')}
							checked={sticky}
							onChange={() =>
								setAttributes({
									sticky: !sticky,
								})
							}
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
				</ToolsPanel>
				<ToolsPanel
					className="cpwp-tools-panel"
					label={ __( 'General', 'category-posts' ) }
					resetAll={ () => resetPanel( [ ...NO_RESULTS, ...LOAD_MORE ] ) }
				>
					<ToolsPanelItem
						label={ __( 'No results', 'category-posts' ) }
						hasValue={ () => differsFromDefault( attributes, NO_RESULTS ) }
						onDeselect={ () => setAttributes( defaultsOf( NO_RESULTS ) ) }
						isShownByDefault
					>
						<SelectControl
							label={ __( 'When there are no matches:', 'category-posts' ) }
							value={ noMatchHandling }
							onChange={( noMatchHandling ) =>
								setAttributes( {
									noMatchHandling
								} )}
							options={ [
								{ value: 'nothing', label: __( 'Show an empty block', 'category-posts' ) },
								{ value: 'hide', label: __( 'Hide the block', 'category-posts' ) },
								{ value: 'text', label: __( 'Show text', 'category-posts' ) },
							] }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						{noMatchHandling === 'text' && (
							<TextareaControl
								__nextHasNoMarginBottom
								label={ __( 'Text', 'category-posts' ) }
								value={ noMatchText }
								onChange={( noMatchText ) =>
									setAttributes( {
										noMatchText
									})}
								rows={ 4 }
							/>
						)}
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Load more', 'category-posts' ) }
						{ ...optionalTool( 'loadMore' ) }
					>
						<ToggleControl
							label={__('Enable Load More', 'category-posts')}
							checked={enableLoadmore}
							onChange={() =>
								setAttributes({
									enableLoadmore: !enableLoadmore,
								})
							}
							__nextHasNoMarginBottom
						/>
						{enableLoadmore && (
							<>
								<ToggleControl
									label={__('Scroll to the loaded items', 'category-posts')}
									checked={loadmoreScrollTo}
									onChange={() =>
										setAttributes({
											loadmoreScrollTo: !loadmoreScrollTo,
										})
									}
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={__('Button text', 'category-posts')}
									help={[
										__('The following placeholders will be replaced with the relevant information: (%step% - Loaded items, %all% - All possible items for the set filter query)', 'category-posts'),
									]}
									value={loadmoreText}
									onChange={(loadmoreText) =>
										setAttributes({
											loadmoreText,
										})}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={__('Loading text', 'category-posts')}
									value={loadingText}
									onChange={(loadingText) =>
										setAttributes({
											loadingText,
										})}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</>
						)}
					</ToolsPanelItem>
				</ToolsPanel>
				<ToolsPanel
					className="cpwp-tools-panel"
					label={ __( 'Footer', 'category-posts' ) }
					resetAll={ () => setAttributes( defaultsOf( FOOTER_LINK ) ) }
				>
					<ToolsPanelItem
						label={ __( 'Footer link', 'category-posts' ) }
						hasValue={ () => differsFromDefault( attributes, FOOTER_LINK ) }
						onDeselect={ () => setAttributes( defaultsOf( FOOTER_LINK ) ) }
						isShownByDefault
					>
						<TextControl
							label={__('Footer link text', 'category-posts')}
							value={footerLinkText}
							onChange={(footerLinkText) =>
								setAttributes({
									footerLinkText,
								})}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						{/*
						  * footerHTML() shows a URL without a text as its own text, so a
						  * block saved that way keeps its URL field.
						  */}
						{ ( footerLinkText || footerLink ) && (
							<>
								<TextControl
									label={__('Footer link URL', 'category-posts')}
									value={footerLink}
									onChange={(footerLink) =>
										setAttributes({
											footerLink,
										})}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<ToggleControl
									label={__('Open footer link in new tab', 'category-posts')}
									checked={footerLinkTarget}
									onChange={() =>
										setAttributes({
											footerLinkTarget: !footerLinkTarget,
										})
									}
									__nextHasNoMarginBottom
								/>
							</>
						) }
					</ToolsPanelItem>
				</ToolsPanel>
			</InspectorControls>
			<InspectorControls group="styles">
				<ToolsPanel
					className="cpwp-tools-panel"
					label={ __( 'Post details', 'category-posts' ) }
					resetAll={ () => resetPanel( POST_DETAILS ) }
				>
					<ToolsPanelItem
						label={ __( 'Template', 'category-posts' ) }
						hasValue={ () => differsFromDefault( attributes, TEMPLATE ) }
						onDeselect={ () => setAttributes( defaultsOf( TEMPLATE ) ) }
						isShownByDefault
					>
						<TemplateControl
							label={__('Template', 'category-posts')}
							help={__('Click a placeholder to insert it at the cursor position, or type % inside the field. In addition you can use text, HTML and Dashicons.', 'category-posts')}
							value={ template }
							onChange={( template ) =>
								setAttributes( {
									template
								})}
							rows={ 8 }
						/>
					</ToolsPanelItem>
					{ template && template.includes( '%title%' ) && (
						<ToolsPanelItem
							label={ __( 'Item title', 'category-posts' ) }
							hasValue={ () => differsFromDefault( attributes, ITEM_TITLE ) }
							onDeselect={ () => setAttributes( defaultsOf( ITEM_TITLE ) ) }
							isShownByDefault
						>
							<PanelRow>{__('Title settings', 'category-posts')}</PanelRow>
							<div className="cpwp-block-ident">
								<ToggleGroupControl
									label={__('Item title heading level', 'category-posts')}
									value={itemTitleLevel}
									isBlock
									isAdaptiveWidth
									onChange={(itemTitleLevel) =>
										setAttributes({
											itemTitleLevel,
										})}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								>
									<ToggleGroupControlOption value="Inline" label={__('Text', 'category-posts')} />
									<ToggleGroupControlOption value="H1" label="H1" />
									<ToggleGroupControlOption value="H2" label="H2" />
									<ToggleGroupControlOption value="H3" label="H3" />
									<ToggleGroupControlOption value="H4" label="H4" />
									<ToggleGroupControlOption value="H5" label="H5" />
									<ToggleGroupControlOption value="H6" label="H6" />
								</ToggleGroupControl>
								<NumberControl
									label={__('Item title lines', 'category-posts')}
									value={itemTitleLines || ''}
									placeholder={__('No limit', 'category-posts')}
									onChange={(itemTitleLines) => setAttributes({ itemTitleLines: parseInt(itemTitleLines) || 0 })}
									min={0}
									suffix={ unit( __( 'lines', 'category-posts' ) ) }
									allowReset={false}
									__next40pxDefaultSize
								/>
							</div>
						</ToolsPanelItem>
					) }
					{ template && template.includes( '%excerpt%' ) && (
						<ToolsPanelItem
							label={ __( 'Excerpt', 'category-posts' ) }
							hasValue={ () => differsFromDefault( attributes, EXCERPT ) }
							onDeselect={ () => setAttributes( defaultsOf( EXCERPT ) ) }
							isShownByDefault
						>
							<PanelRow>{__('Excerpt settings', 'category-posts')}</PanelRow>
							<div className="cpwp-block-ident">
								{/* The lines limit the full post as well, paragraph by paragraph. */}
								<NumberControl
									label={__('Excerpt lines', 'category-posts')}
									value={excerptLines || ''}
									placeholder={__('No limit', 'category-posts')}
									onChange={(excerptLines) => setAttributes({ excerptLines: parseInt(excerptLines) || 0 })}
									min={0}
									suffix={ unit( __( 'lines', 'category-posts' ) ) }
									allowReset={false}
									__next40pxDefaultSize
								/>
								<RadioControl
									label={ __( 'Content', 'category-posts' ) }
									selected={ excerptRadio }
									options={ [
										{ label: __( 'Excerpt', 'category-posts' ), value: 'excerpt' },
										{
											label: __( 'Full post', 'category-posts' ),
											value: 'full_post',
										},
									] }
									onChange={ ( value ) =>
										setAttributes( {
											excerptRadio: value,
										} )
									}
								/>
							</div>
						</ToolsPanelItem>
					) }
					{ template && template.includes( '%more-link%' ) && (
						<ToolsPanelItem
							label={ __( 'More link', 'category-posts' ) }
							hasValue={ () => differsFromDefault( attributes, MORE_LINK ) }
							onDeselect={ () => setAttributes( defaultsOf( MORE_LINK ) ) }
							isShownByDefault
						>
							<PanelRow>{__('More Link settings', 'category-posts')}</PanelRow>
							<div className="cpwp-block-ident">
								<TextControl
									label={__('Read more text', 'category-posts')}
									value={excerptMoreText}
									onChange={(excerptMoreText) => setAttributes({ excerptMoreText })}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</div>
						</ToolsPanelItem>
					) }
					{ template && template.includes( '%date%' ) && (
						<ToolsPanelItem
							label={ __( 'Date', 'category-posts' ) }
							hasValue={ () => differsFromDefault( attributes, DATE_FORMAT ) }
							onDeselect={ () => setAttributes( defaultsOf( DATE_FORMAT ) ) }
							isShownByDefault
						>
							<PanelRow>{__('Date format settings', 'category-posts')}</PanelRow>
							<div className="cpwp-block-ident">
								<SelectControl
									label={__('Date format', 'category-posts')}
									value={presetDateFormat}
									onChange={(presetDateFormat) => setAttributes({ presetDateFormat })}
									options={[
										{ label: __( 'Site date and time', 'category-posts' ), value: 'sitedateandtime' },
										{ label: __( 'Site date', 'category-posts' ), value: 'sitedate' },
										{ label: __( 'Reader\'s local date and time', 'category-posts' ), value: 'localsitedateandtime' },
										{ label: __( 'Reader\'s local date', 'category-posts' ), value: 'localsitedate' },
										{ label: __( 'PHP style format', 'category-posts' ), value: 'other' },
									]}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								{ 'other' === presetDateFormat && (
									<TextControl
										label={__('PHP style date format', 'category-posts')}
										value={dateFormat}
										onChange={(dateFormat) => setAttributes({ dateFormat })}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								) }
								<NumberControl
									label={__('Relative date up to', 'category-posts')}
									help={__( 'Shows the age of a post, e.g. "3 days", instead of its date while it is not older than this.', 'category-posts' )}
									value={parseInt(datePastTime) || ''}
									placeholder={__('Off', 'category-posts')}
									onChange={(datePastTime) => setAttributes({ datePastTime: String(parseInt(datePastTime) || 0) })}
									min={0}
									suffix={ unit( __( 'days', 'category-posts' ) ) }
									allowReset={false}
									__next40pxDefaultSize
								/>
							</div>
						</ToolsPanelItem>
					) }
					{ template && template.includes( '%thumb%' ) && (
						<ToolsPanelItem
							label={ __( 'Thumbnail', 'category-posts' ) }
							hasValue={ () => differsFromDefault( attributes, THUMBNAIL ) }
							onDeselect={ () => setAttributes( defaultsOf( THUMBNAIL ) ) }
							isShownByDefault
						>
							<PanelRow>{__('Thumbnail settings', 'category-posts')}</PanelRow>
							<div className="cpwp-block-ident">
								{/*
								  * A 0 means "let the image decide this dimension". It is
								  * shown as an empty field with "Auto" as its placeholder,
								  * and an emptied field is saved as 0 again. Without the
								  * fallback parseInt() hands back a NaN which the attribute
								  * can not hold.
								  */}
								<ControlPair>
									<NumberControl
										label={__('Width', 'category-posts')}
										value={thumbW || ''}
										placeholder={__('Auto', 'category-posts')}
										onChange={(thumbW) => setAttributes({ thumbW: parseInt(thumbW) || 0 })}
										min={0}
										suffix={ unit( 'px' ) }
										allowReset={false}
										__next40pxDefaultSize
									/>
									<NumberControl
										label={__('Height', 'category-posts')}
										value={thumbH || ''}
										placeholder={__('Auto', 'category-posts')}
										onChange={(thumbH) => setAttributes({ thumbH: parseInt(thumbH) || 0 })}
										min={0}
										suffix={ unit( 'px' ) }
										allowReset={false}
										__next40pxDefaultSize
									/>
								</ControlPair>
								<ToolsPanelItem
									label={ __( 'Hover effect', 'category-posts' ) }
									{ ...optionalTool( 'thumbHover' ) }
								>
									<SelectControl
										label={__('Hover effect', 'category-posts')}
										value={thumbHover}
										onChange={(thumbHover) => setAttributes({ thumbHover })}
										options={[
											{ label: __( 'None', 'category-posts' ), value: 'none' },
											{ label: __( 'Darker', 'category-posts' ), value: 'dark' },
											{ label: __( 'Brighter', 'category-posts' ), value: 'white' },
											{ label: __( 'Zoom in', 'category-posts' ), value: 'scale' },
											{ label: __( 'Blur', 'category-posts' ), value: 'blur' },
											{ label: __( 'Icon', 'category-posts' ), value: 'icon' },
										]}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								</ToolsPanelItem>
								<ToolsPanelItem
									label={ __( 'Post format indicator', 'category-posts' ) }
									{ ...optionalTool( 'showPostFormat' ) }
								>
									<SelectControl
										label={__('Post format indicator', 'category-posts')}
										value={showPostFormat}
										onChange={(showPostFormat) => setAttributes({ showPostFormat })}
										options={[
											{ label: __( 'None', 'category-posts' ), value: 'none' },
											{ label: __( 'Top left', 'category-posts' ), value: 'topleft' },
											{ label: __( 'Bottom left', 'category-posts' ), value: 'bottomleft' },
											{ label: __( 'Center', 'category-posts' ), value: 'ceter' },
											{ label: __( 'Top right', 'category-posts' ), value: 'topright' },
											{ label: __( 'Bottom right', 'category-posts' ), value: 'bottomright' },
											{ label: __( 'HTML without styling', 'category-posts' ), value: 'nocss' },
										]}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								</ToolsPanelItem>
								<ToggleControl
									label={__('Do not wrap thumbnail with text', 'category-posts')}
									checked={textDoNotWrapThumb}
									onChange={() => setAttributes({ textDoNotWrapThumb: !textDoNotWrapThumb })}
									__nextHasNoMarginBottom
								/>
								<ToolsPanelItem
									label={ __( 'Everything is a link', 'category-posts' ) }
									{ ...optionalTool( 'everythingIsLink' ) }
								>
									<ToggleControl
										label={__('Everything is a link', 'category-posts')}
										checked={everythingIsLink}
										onChange={() => setAttributes({ everythingIsLink: !everythingIsLink })}
										__nextHasNoMarginBottom
									/>
								</ToolsPanelItem>
								<ToolsPanelItem
									label={ __( 'Symbols', 'category-posts' ) }
									{ ...optionalTool( 'thumbSymbols' ) }
								>
									<ToggleControl
										label={__('Symbols', 'category-posts')}
										checked={thumbSymbols}
										onChange={() => setAttributes({ thumbSymbols: !thumbSymbols })}
										__nextHasNoMarginBottom
									/>
								</ToolsPanelItem>
							</div>
						</ToolsPanelItem>
					) }
				</ToolsPanel>
				<ToolsPanel
					className="cpwp-tools-panel"
					label={ __( 'Styles', 'category-posts' ) }
					resetAll={ () => setAttributes( defaultsOf( BUILT_IN_CSS ) ) }
				>
					<ToolsPanelItem
						label={ __( 'Styles', 'category-posts' ) }
						hasValue={ () => differsFromDefault( attributes, BUILT_IN_CSS ) }
						onDeselect={ () => setAttributes( defaultsOf( BUILT_IN_CSS ) ) }
						isShownByDefault
					>
						<ToggleControl
							label={__('Disable the built-in CSS', 'category-posts')}
							checked={disableCss}
							onChange={() =>
								setAttributes({
									disableCss: !disableCss,
								})
							}
							__nextHasNoMarginBottom
						/>
						{/* The font styles are part of the built-in CSS. */}
						{ !disableCss && (
							<ToggleControl
								label={__('Disable only font styles', 'category-posts')}
								checked={disableFontStyles}
								onChange={() =>
									setAttributes({
										disableFontStyles: !disableFontStyles,
									})
								}
								__nextHasNoMarginBottom
							/>
						) }
						{/*
						  * Independent of the built-in CSS: it leaves out the class
						  * names, like widget-title or post-date, the theme styles.
						  */}
						<ToggleControl
							label={__('Disable Theme\'s styles', 'category-posts')}
							checked={disableThemeStyles}
							onChange={() =>
								setAttributes({
									disableThemeStyles: !disableThemeStyles,
								})
							}
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
				</ToolsPanel>
			</InspectorControls>
			<div
				{...blockProps}
			>
				{/*
				  * The preview must not be interactive, but the <Disabled> component does
				  * that with the inert attribute and pointer-events:none, which switches
				  * off :hover as well, so the thumbnail hover effect could not be seen in
				  * the editor. Keep the pointer events and just swallow the clicks instead.
				  */}
				<div
					className="cpwp-block-preview"
					ref={ previewRef }
					onClickCapture={(event) => event.preventDefault()}
				>
					<ServerSideRender
						block="tiptip/category-posts-block"
						attributes={ previewAttributes }
					/>
				</div>
			</div>
		</>
	);
}
