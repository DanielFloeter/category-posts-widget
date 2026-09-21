/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useInstanceId } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
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
	DatePicker,
	Popover,
	Button,
	Panel,
	PanelBody,
	PanelRow,
} from '@wordpress/components';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import TemplateControl from './template-control';
import './editor.scss';

export const ToggleGroupControl = __experimentalToggleGroupControl || stableToggleGroupControl;
export const ToggleGroupControlOption = __experimentalToggleGroupControlOption || stableToggleGroupControlOption;
export const NumberControl = __experimentalNumberControl || stableNumberControl;

export default function Edit({ attributes, setAttributes }) {
	const {
		hideTitle, title, titleLink, titleLinkUrl, titleLinkTarget, titleLevel,
		order, orderBy, categories, status, num, offset, dateRange, startDate, endDate, daysAgo, excludeCurrentPost, hideNoThumb, sticky,
		template, itemTitleLevel, itemTitleLines, excerptRadio, excerptLines, excerptMoreText,
		thumbW, thumbH, thumbHover, thumbSymbols, showPostFormat, textDoNotWrapThumb, everythingIsLink, presetDateFormat, dateFormat, datePastTime,
		disableCss, disableFontStyles, disableThemeStyles, noMatchHandling, noMatchText, enableLoadmore, loadmoreScrollTo, loadmoreText, loadingText,
		footerLinkText, footerLink, footerLinkTarget, instanceId
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
			label: 'All Categories',
		},
		...(categoriesList || []).map( ( category ) => ( {
			label: category.name,
			value: String( category.id ),
		})),
	];

	const statusOptions = [
		{
			value: 'default',
			label: 'WordPress Default',
		},
		{
			value: 'publish',
			label: 'Published',
		},
		{
			value: 'future',
			label: 'Scheduled',
		},
		{
			value: 'private',
			label: 'Private',
		},
		{
			value: 'publish,future',
			label: 'Published or Scheduled',
		},
		{
			value: 'private,publish',
			label: 'Published or Private',
		},
		{
			value: 'private,future',
			label: 'Private or Scheduled',
		},
		{
			value: 'private,publish,future',
			label: 'Published, Private or Scheduled',
		},
	];

	const dateRangeOptions = [
		{
			value: 'off',
			label: 'Off',
		},
		{
			value: 'days_ago',
			label: 'Days ago',
		},
		{
			value: 'between_dates',
			label: 'Between dates',
		},
	];

	const [openStartDatePopup, setOpenStartDatePopup] = useState( false );
	const [openEndDatePopup, setOpenEndDatePopup] = useState( false );

	return (
		<>
			<InspectorControls>
				<Panel>
					<PanelBody title={__('Title', 'category-posts')} initialOpen={ false }>
						<ToggleControl
							label={__('Hide title', 'category-posts')}
							checked={hideTitle}
							onChange={() =>
								setAttributes({
									hideTitle: !hideTitle,
								})
							}
						/>
						{ !hideTitle && (
							<>
								<TextControl
									label={__('Title', 'category-posts')}
									value={title}
									onChange={(title) =>
										setAttributes({
											title,
										})}
								/>
								<ToggleControl
									label={__('Make block title link', 'category-posts')}
									checked={titleLink}
									onChange={() =>
										setAttributes({
											titleLink: !titleLink,
										})
									}
								/>
								<TextControl
									label={__('Title link URL', 'category-posts')}
									value={titleLinkUrl}
									onChange={(titleLinkUrl) =>
										setAttributes({
											titleLinkUrl,
										})}
								/>
								<ToggleControl
									label={__('Open title link in new tab', 'category-posts')}
									checked={titleLinkTarget}
									onChange={() =>
										setAttributes({
											titleLinkTarget: !titleLinkTarget,
										})
									}
								/>
								<ToggleGroupControl
									label="Heading level"
									value={titleLevel}
									isBlock
									isAdaptiveWidth
									onChange={(titleLevel) =>
										setAttributes({
											titleLevel,
										})}
								>
									<ToggleGroupControlOption value="Initial" label="Initial" />
									<ToggleGroupControlOption value="H1" label="H1" />
									<ToggleGroupControlOption value="H2" label="H2" />
									<ToggleGroupControlOption value="H3" label="H3" />
									<ToggleGroupControlOption value="H4" label="H4" />
									<ToggleGroupControlOption value="H5" label="H5" />
									<ToggleGroupControlOption value="H6" label="H6" />
								</ToggleGroupControl>
							</>
						) }
					</PanelBody>
					<PanelBody title={__('Filter', 'category-posts', 'category-posts')} initialOpen={ false }>
						<ComboboxControl
							label={ __( 'Category' ) }
							options={ categoryOptions }
							value={ categories }
							onChange={( newCategory) =>
								setAttributes({
									categories: newCategory,
								})
							}
							allowReset={ false }
						/>
						<SelectControl
							label="Order By"
							value={ orderBy }
							options={[
								{ label: 'Date', value: 'date' },
								{ label: 'Title', value: 'title' },
								{ label: 'Number of comments', value: 'comment_count' },
								{ label: 'Random', value: 'rand' },
							]}
							onChange={(value) =>
								setAttributes({ orderBy: value })
							}
						/>
						<ToggleControl
							label="Order"
							checked={ order }
							onChange={(value) =>
								setAttributes({ order: value })
							}
						/>
						<ComboboxControl
							label={ __( 'Status' ) }
							options={ statusOptions }
							value={ status }
							onChange={( newStatus) =>
								setAttributes({
									status: newStatus,
								})
							}
							allowReset={ false }
						/>
						<NumberControl
							label={ __( 'Number of posts to show' ) }
							onChange={( newNum) =>
								setAttributes({
									num: newNum,
								})
							}
							value={ num }
							min={1}
							allowReset={ false }
						/>
						<NumberControl
							label={ __( 'Start with post' ) }
							onChange={( newOffset) =>
								setAttributes({
									offset: parseInt( newOffset ),
								})
							}
							value={ offset }
							min={1}
							allowReset={ false }
						/>
						<ComboboxControl
							label={ __( 'Date Range' ) }
							options={ dateRangeOptions }
							value={ dateRange }
							onChange={( newDateRange) =>
								setAttributes({
									dateRange: newDateRange,
								})
							}
							allowReset={ false }
						/>
						{dateRange === 'days_ago' && (
							<NumberControl
								label={ __( 'Up to' ) }
								onChange={( newDaysAgo) =>
									setAttributes({
										daysAgo: parseInt( newDaysAgo ),
									})
								}
								value={ daysAgo }
								min={1}
								allowReset={ false }
							/>
						)}
						{dateRange === 'between_dates' && (
							<div className="cpwp-block-ident">
								<PanelRow>
									<label>After</label>
									<Button isLink={true} onClick={() => setOpenStartDatePopup( ! openStartDatePopup )}>
										{ startDate ? dateI18n( 'j.m.Y', startDate ) : "tt.mm.jjjj" }
									</Button>
									{ openStartDatePopup && (
										<Popover onClose={ setOpenStartDatePopup.bind( null, false )}>
											<DatePicker
												onChange={( newStartDate) =>
													setAttributes({
														startDate: newStartDate,
													})
												}
												currentDate={ startDate }
												startOfWeek={1}
											/>
										</Popover>
									) }
								</PanelRow>
								<PanelRow>
									<label>Before</label>
									<Button isLink={true} onClick={() => setOpenEndDatePopup( ! openEndDatePopup )}>
										{ endDate ? dateI18n( 'j.m.Y', endDate ) : "tt.mm.jjjj" }
									</Button>
									{ openEndDatePopup && (
										<Popover onClose={ setOpenEndDatePopup.bind( null, false )}>
											<DatePicker
												onChange={( newEndDate) =>
													setAttributes({
														endDate: newEndDate,
													})
												}
												currentDate={ endDate }
												startOfWeek={1}
											/>
										</Popover>
									) }
								</PanelRow>
							</div>
						)}
						<br />
						<ToggleControl
							label={__('Exclude current post', 'category-posts')}
							checked={excludeCurrentPost}
							onChange={() =>
								setAttributes({
									excludeCurrentPost: !excludeCurrentPost,
								})
							}
						/>
						<ToggleControl
							label={__('Hide posts which have no thumbnail', 'category-posts')}
							checked={hideNoThumb}
							onChange={() =>
								setAttributes({
									hideNoThumb: !hideNoThumb,
								})
							}
						/>
						<ToggleControl
							label={__('Start with sticky posts', 'category-posts')}
							checked={sticky}
							onChange={() =>
								setAttributes({
									sticky: !sticky,
								})
							}
						/>
					</PanelBody>
					<PanelBody title={__('Post details', 'category-posts')} initialOpen={ false }>
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
						{ template && template.includes( '%title%' ) && (
							<>
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
							>
								<ToggleGroupControlOption value="Inline" label="Inline" />
								<ToggleGroupControlOption value="H1" label="H1" />
								<ToggleGroupControlOption value="H2" label="H2" />
								<ToggleGroupControlOption value="H3" label="H3" />
								<ToggleGroupControlOption value="H4" label="H4" />
								<ToggleGroupControlOption value="H5" label="H5" />
								<ToggleGroupControlOption value="H6" label="H6" />
							</ToggleGroupControl>
							<NumberControl
								label={__('Item title lines', 'category-posts')}
								value={itemTitleLines}
								onChange={(itemTitleLines) => setAttributes({ itemTitleLines: parseInt(itemTitleLines) })}
								min={0}
								allowReset={false}
							/>
						</div>
							</>
						) }
						{ template && template.includes( '%excerpt%' ) && (
							<>
						<PanelRow>{__('Excerpt settings', 'category-posts')}</PanelRow>
						<div className="cpwp-block-ident">
							<NumberControl
								label={__('Excerpt lines', 'category-posts')}
								value={excerptLines}
								onChange={(excerptLines) => setAttributes({ excerptLines: parseInt(excerptLines) })}
								min={0}
								allowReset={false}
							/>
							<RadioControl
								label={ __( 'Intent', 'category-posts' ) }
								selected={ excerptRadio }
								options={ [
									{ label: __( 'Excerpt', 'category-posts' ), value: 'excerpt' },
									{
										label: __( 'Full Post', 'category-posts' ),
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
							</>
						) }
						{ template && template.includes( '%more-link%' ) && (
							<>
						<PanelRow>{__('More Link settings', 'category-posts')}</PanelRow>
						<div className="cpwp-block-ident">
							<TextControl
								label={__('Read more text', 'category-posts')}
								value={excerptMoreText}
								onChange={(excerptMoreText) => setAttributes({ excerptMoreText })}
							/>
						</div>
							</>
						) }
						{ template && template.includes( '%date%' ) && (
							<>
						<PanelRow>{__('Date format settings', 'category-posts')}</PanelRow>
						<div className="cpwp-block-ident">
							<SelectControl
								label={__('Date format', 'category-posts')}
								value={presetDateFormat}
								onChange={(presetDateFormat) => setAttributes({ presetDateFormat })}
								options={[
									{ label: 'Site date and time', value: 'sitedateandtime' },
									{ label: 'Site date', value: 'sitedate' },
									{ label: 'Reader\'s local date and time', value: 'localsitedateandtime' },
									{ label: 'Reader\'s local date', value: 'localsitedate' },
									{ label: 'PHP style format', value: 'other' },
								]}
							/>
							<TextControl
								label={__('PHP style date format', 'category-posts')}
								value={dateFormat}
								onChange={(dateFormat) => setAttributes({ dateFormat })}
							/>
							<NumberControl
								label={__('Show past time up to x-days', 'category-posts')}
								value={datePastTime}
								onChange={(datePastTime) => setAttributes({ datePastTime })}
								min={0}
								allowReset={false}
							/>
						</div>
							</>
						) }
						{ template && template.includes( '%thumb%' ) && (
							<>
						<PanelRow>{__('Thumbnail settings', 'category-posts')}</PanelRow>
						<div className="cpwp-block-ident">
							<NumberControl
								label={__('Thumbnail width', 'category-posts')}
								value={thumbW}
								onChange={(thumbW) => setAttributes({ thumbW: parseInt(thumbW) })}
								min={0}
								allowReset={false}
							/>
							<NumberControl
								label={__('Thumbnail height', 'category-posts')}
								value={thumbH}
								onChange={(thumbH) => setAttributes({ thumbH: parseInt(thumbH) })}
								min={0}
								allowReset={false}
							/>
							<SelectControl
								label={__('Hover effect', 'category-posts')}
								value={thumbHover}
								onChange={(thumbHover) => setAttributes({ thumbHover })}
								options={[
									{ label: 'None', value: 'none' },
									{ label: 'Darker', value: 'dark' },
									{ label: 'Brighter', value: 'white' },
									{ label: 'Zoom in', value: 'scale' },
									{ label: 'Blur', value: 'blur' },
									{ label: 'Icon', value: 'icon' },
								]}
							/>
							<SelectControl
								label={__('Post format indicator', 'category-posts')}
								value={showPostFormat}
								onChange={(showPostFormat) => setAttributes({ showPostFormat })}
								options={[
									{ label: 'None', value: 'none' },
									{ label: 'Top left', value: 'topleft' },
									{ label: 'Bottom left', value: 'bottomleft' },
									{ label: 'Center', value: 'ceter' },
									{ label: 'Top right', value: 'topright' },
									{ label: 'Bottom right', value: 'bottomright' },
									{ label: 'HTML without styling', value: 'nocss' },
								]}
							/>
							<ToggleControl
								label={__('Do not wrap thumbnail with text', 'category-posts')}
								checked={textDoNotWrapThumb}
								onChange={() => setAttributes({ textDoNotWrapThumb: !textDoNotWrapThumb })}
							/>
							<ToggleControl
								label={__('Everything is a link', 'category-posts')}
								checked={everythingIsLink}
								onChange={() => setAttributes({ everythingIsLink: !everythingIsLink })}
							/>
							<ToggleControl
								label={__('Symbols', 'category-posts')}
								checked={thumbSymbols}
								onChange={() => setAttributes({ thumbSymbols: !thumbSymbols })}
							/>
						</div>
							</>
						) }
					</PanelBody>
					<PanelBody title={__('General', 'category-posts')} initialOpen={ false }>
						<PanelRow>Inherited CSS</PanelRow>
						<div className="cpwp-block-ident">
							<ToggleControl
								label={__('Disable the built-in CSS', 'category-posts')}
								checked={disableCss}
								onChange={() =>
									setAttributes({
										disableCss: !disableCss,
									})
								}
							/>
							<ToggleControl
								label={__('Disable only font styles', 'category-posts')}
								checked={disableFontStyles}
								onChange={() =>
									setAttributes({
										disableFontStyles: !disableFontStyles,
									})
								}
							/>
							<ToggleControl
								label={__('Disable Theme\'s styles', 'category-posts')}
								checked={disableThemeStyles}
								onChange={() =>
									setAttributes({
										disableThemeStyles: !disableThemeStyles,
									})
								}
							/>
						</div>
						<PanelRow>Interim text</PanelRow>
						<div className="cpwp-block-ident">
							<SelectControl
								label={ __( 'When there are no matches:', 'category-posts' ) }
								value={ noMatchHandling }
								onChange={( noMatchHandling ) => 
									setAttributes( {
										noMatchHandling
									} )}
								options={ [
									{ value: 'nothing', label: __( 'Display empty widget', 'category-posts' ) },
									{ value: 'hide', label: __( 'Hide Widget', 'category-posts' ) },
									{ value: 'text', label: __( 'Show text', 'category-posts' ) },
								] }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							{noMatchHandling === 'text' && (
								<TextareaControl
									__nextHasNoMarginBottom
									label="Text"
									value={ noMatchText }
									onChange={( noMatchText ) => 
										setAttributes( {
											noMatchText
										})}
									rows={ 4 }
								/>
							)}
						</div>
						<PanelRow>Ajax API</PanelRow>
						<div className="cpwp-block-ident">
							<ToggleControl
								label={__('Enable Load More', 'category-posts')}
								checked={enableLoadmore}
								onChange={() =>
									setAttributes({
										enableLoadmore: !enableLoadmore,
									})
								}
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
									/>
									<TextControl
										label={__('Loading text', 'category-posts')}
										value={loadingText}
										onChange={(loadingText) =>
											setAttributes({
												loadingText,
											})}
									/>
								</>
							)}
						</div>
					</PanelBody>
					<PanelBody title={__('Footer', 'category-posts')} initialOpen={ false }>
						<TextControl
							label={__('Footer link text', 'category-posts')}
							value={footerLinkText}
							onChange={(footerLinkText) =>
								setAttributes({
									footerLinkText,
								})}
						/>
						<TextControl
							label={__('Footer link URL', 'category-posts')}
							value={footerLink}
							onChange={(footerLink) =>
								setAttributes({
									footerLink,
								})}
						/>
						<ToggleControl
							label={__('Open footer link in new tab', 'category-posts')}
							checked={footerLinkTarget}
							onChange={() =>
								setAttributes({
									footerLinkTarget: !footerLinkTarget,
								})
							}
						/>
					</PanelBody>
				</Panel>
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
						attributes={attributes}
					/>
				</div>
			</div>
		</>
	);
}
