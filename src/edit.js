/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	ComboboxControl,
	TextControl,
	ToggleControl,
	SelectControl,
	TextareaControl,
	RadioControl,
	QueryControls,
	Disabled,
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
		hideTitle, title, titleLink, titleLinkUrl, titleLevel,
		order, orderBy, categories, status, num, offset, dateRange, startDate, endDate, daysAgo, excludeCurrentPost, hideNoThumb, sticky,
		template, itemTitleLevel, itemTitleLines, excerptLines, excerptMoreText,
		thumbW, thumbH, thumbHover, showPostFormat, textDoNotWrapThumb, everythingIsLink, presetDateFormat, dateFormat, datePastTime,
		disableCss, disableFontStyles, disableThemeStyles, noMatchHandling, noMatchText, enableLoadmore, loadmoreScrollTo, loadmoreText, loadingText,
		footerLinkText, footerLink
	} = attributes;
	const blockProps = useBlockProps({
		className: disableThemeStyles ? 'widget-title' : '',
	});

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
						<TextControl
							label={__('Title', 'category-posts')}
							value={title}
							onChange={(title) =>
								setAttributes({
									title,
								})}
						/>
						<ToggleControl
							label={__('Make widget title link', 'category-posts')}
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
									offset: newOffset,
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
										daysAgo: newDaysAgo,
									})
								}
								value={ daysAgo }
								min={1}
								allowReset={ false }
							/>
						)}
						{dateRange === 'between_dates' && (
							<>
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
							</>
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
						<PanelRow>{__('Displayed parts', 'category-posts')}</PanelRow>
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
						<PanelRow>{__('Title settings', 'category-posts')}</PanelRow>
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
							onChange={(itemTitleLines) => setAttributes({ itemTitleLines })}
							min={1}
							allowReset={false}
						/>
						<PanelRow>{__('Excerpt settings', 'category-posts')}</PanelRow>
						<NumberControl
							label={__('Excerpt lines', 'category-posts')}
							value={excerptLines}
							onChange={(excerptLines) => setAttributes({ excerptLines })}
							min={1}
							allowReset={false}
						/>
						<TextControl
							label={__('Read more text', 'category-posts')}
							value={excerptMoreText}
							onChange={(excerptMoreText) => setAttributes({ excerptMoreText })}
						/>
						<PanelRow>{__('Date format settings', 'category-posts')}</PanelRow>
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
						<PanelRow>{__('Thumbnail settings', 'category-posts')}</PanelRow>
						<NumberControl
							label={__('Thumbnail width', 'category-posts')}
							value={thumbW}
							onChange={(thumbW) => setAttributes({ thumbW })}
							min={0}
							allowReset={false}
						/>
						<NumberControl
							label={__('Thumbnail height', 'category-posts')}
							value={thumbH}
							onChange={(thumbH) => setAttributes({ thumbH })}
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
					</PanelBody>
					<PanelBody title={__('General', 'category-posts')} initialOpen={ false }>
						<PanelRow>Inherited CSS</PanelRow>
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
						<PanelRow>Interim text</PanelRow>
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
						<PanelRow>Ajax API</PanelRow>
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
					</PanelBody>
				</Panel>
			</InspectorControls>
			<div
				{...blockProps}
			>
				<Disabled>
					<ServerSideRender
						block="tiptip/category-posts-block"
						attributes={attributes}
					/>
				</Disabled>
			</div>
		</>
	);
}
