import metadata from './../block.json';

/**
 * Widget instance key => block attribute name.
 *
 * The reverse of build_block_instance() in cat-posts-block.php, keep both in
 * sync. 'cat' is not listed, the widget stores "All Categories" as 0 while the
 * block uses an empty string for it, see below.
 */
const WIDGET_TO_BLOCK = {
	title: 'title',
	title_link: 'titleLink',
	title_link_target: 'titleLinkTarget',
	title_level: 'titleLevel',
	title_link_url: 'titleLinkUrl',
	hide_title: 'hideTitle',
	num: 'num',
	offset: 'offset',
	sort_by: 'orderBy',
	status: 'status',
	asc_sort_order: 'order',
	exclude_current_post: 'excludeCurrentPost',
	hideNoThumb: 'hideNoThumb',
	sticky: 'sticky',
	footer_link_text: 'footerLinkText',
	footer_link: 'footerLink',
	footer_link_target: 'footerLinkTarget',
	item_title_level: 'itemTitleLevel',
	item_title_lines: 'itemTitleLines',
	thumb_w: 'thumbW',
	thumb_fluid_width: 'thumbFluidWidth',
	thumb_h: 'thumbH',
	thumb_hover: 'thumbHover',
	thumb_symbols: 'thumbSymbols',
	hide_post_titles: 'hidePostTitles',
	excerpt_radio: 'excerptRadio',
	excerpt_lines: 'excerptLines',
	excerpt_length: 'excerptLength',
	excerpt_more_text: 'excerptMoreText',
	excerpt_filters: 'excerptFilters',
	comment_num: 'commentNum',
	disable_css: 'disableCss',
	disable_font_styles: 'disableFontStyles',
	disable_theme_styles: 'disableThemeStyles',
	show_post_format: 'showPostFormat',
	no_cat_childs: 'noCatChilds',
	everything_is_link: 'everythingIsLink',
	preset_date_format: 'presetDateFormat',
	date_format: 'dateFormat',
	date_past_time: 'datePastTime',
	template: 'template',
	text_do_not_wrap_thumb: 'textDoNotWrapThumb',
	enable_loadmore: 'enableLoadmore',
	loadmore_scrollTo: 'loadmoreScrollTo',
	loadmore_text: 'loadmoreText',
	loading_text: 'loadingText',
	date_range: 'dateRange',
	start_date: 'startDate',
	end_date: 'endDate',
	days_ago: 'daysAgo',
	no_match_handling: 'noMatchHandling',
	no_match_text: 'noMatchText',
	default_thunmbnail: 'defaultThunmbnail',
};

/**
 * Whether PHP would treat the value as true, the way the widget reads its
 * checkboxes ('on' when checked, missing or '' when not).
 *
 * @param {*} value The stored widget value.
 * @return {boolean} The value as boolean.
 */
const isTruthy = ( value ) =>
	!! value && '0' !== value && ! ( Array.isArray( value ) && 0 === value.length );

/**
 * Convert a widget value to the type the block attribute is declared with.
 *
 * @param {*}      value The stored widget value.
 * @param {string} name  The block attribute name.
 * @return {*} The converted value, undefined to keep the attribute's default.
 */
const toAttribute = ( value, name ) => {
	switch ( metadata.attributes[ name ].type ) {
		case 'boolean':
			return isTruthy( value );
		case 'integer': {
			const number = parseInt( value, 10 );
			return isNaN( number ) ? undefined : number;
		}
		case 'string':
			return String( value );
		default:
			return value;
	}
};

/**
 * Build the block attributes from a widget instance.
 *
 * @param {Object} raw The widget instance as stored in the database.
 * @return {Object} The block attributes.
 */
export default function widgetToBlockAttributes( raw ) {
	const attributes = {};

	Object.entries( WIDGET_TO_BLOCK ).forEach( ( [ key, name ] ) => {
		if ( undefined === raw[ key ] || null === raw[ key ] ) {
			return;
		}
		const value = toAttribute( raw[ key ], name );
		if ( undefined !== value ) {
			attributes[ name ] = value;
		}
	} );

	if ( parseInt( raw.cat, 10 ) ) {
		attributes.categories = String( raw.cat );
	}

	return attributes;
}
