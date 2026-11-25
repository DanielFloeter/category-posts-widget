/**
 * Color and typography of single placeholders, set in the
 * "Styles" tab of the block. The rules are built on the server
 * (placeholder_style_rules() in cat-posts-block.php) and come with the preview.
 *
 * While the "Styles" tab is open, a click on a placeholder in the preview
 * selects it: its panel is marked and scrolled into view, and its elements in
 * the preview are outlined. See docs/placeholder-styles-selection.md.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import {
	__experimentalToolsPanel, ToolsPanel as stableToolsPanel,
	__experimentalToolsPanelItem, ToolsPanelItem as stableToolsPanelItem,
	__experimentalBoxControl, BoxControl as stableBoxControl,
	__experimentalBorderBoxControl, BorderBoxControl as stableBorderBoxControl,
	__experimentalUseCustomUnits as useCustomUnits,
	__experimentalUnitControl, UnitControl as stableUnitControl,
	__experimentalToggleGroupControl, ToggleGroupControl as stableToggleGroupControl,
	__experimentalToggleGroupControlOption, ToggleGroupControlOption as stableToggleGroupControlOption,
} from '@wordpress/components';
import {
	FontSizePicker,
	__experimentalFontAppearanceControl as FontAppearanceControl,
	__experimentalTextTransformControl as TextTransformControl,
	LineHeightControl,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalSpacingSizesControl as SpacingSizesControl,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	getColorObjectByColorValue,
	getFontSizeObjectByValue,
	useSettings,
} from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { BLOCK_TITLE_TARGET, ITEM_TARGET, blockTargets, templatesOf, targetsIn } from './placeholder-style-targets';

export { findStyleTarget, selectionCSS } from './placeholder-style-targets';

const ToolsPanel = __experimentalToolsPanel || stableToolsPanel;
const ToolsPanelItem = __experimentalToolsPanelItem || stableToolsPanelItem;
const BoxControl = stableBoxControl || __experimentalBoxControl;
const UnitControl = stableUnitControl || __experimentalUnitControl;
const ToggleGroupControl = stableToggleGroupControl || __experimentalToggleGroupControl;
const ToggleGroupControlOption = stableToggleGroupControlOption || __experimentalToggleGroupControlOption;

const JUSTIFY_LABELS = {
	left: __( 'Left', 'category-posts' ),
	center: __( 'Center', 'category-posts' ),
	right: __( 'Right', 'category-posts' ),
	'space-between': __( 'Space between', 'category-posts' ),
};
const BorderBoxControl = stableBorderBoxControl || __experimentalBorderBoxControl;

// A value of sides or corners without the empty ones, undefined when none is left.
const withoutEmpty = ( values ) => {
	if ( ! values || 'object' !== typeof values ) {
		return values || undefined;
	}
	const entries = Object.entries( values )
		.map( ( [ key, entry ] ) => [ key, 'object' === typeof entry ? withoutEmpty( entry ) : entry ] )
		.filter( ( [ , entry ] ) => undefined !== entry && null !== entry && '' !== entry );
	return entries.length ? Object.fromEntries( entries ) : undefined;
};

// The color presets of all origins as one list.
const flatColors = ( colors ) => ( Array.isArray( colors ) ? colors : [] )
	.flatMap( ( entry ) => ( Array.isArray( entry?.colors ) ? entry.colors : [ entry ] ) )
	.filter( ( entry ) => entry?.slug && entry?.color );

// Presets like the font sizes, as one list even when they come by origin.
const flatPresets = ( presets ) => {
	if ( Array.isArray( presets ) ) {
		return presets;
	}
	return presets && 'object' === typeof presets ? Object.values( presets ).flat() : [];
};
const flatFontSizes = flatPresets;

/**
 * Padding or margin like in the "Dimensions" panel of WordPress: with spacing
 * presets of the theme the slider of the presets, split into vertical and
 * horizontal, else a value per side.
 *
 * @param {Object}   props
 * @param {string}   props.label         The label.
 * @param {Object}   props.values        { top, right, bottom, left }.
 * @param {Array}    props.units         The units for custom values.
 * @param {boolean}  props.hasPresets    Whether the theme has spacing presets.
 * @param {boolean}  props.allowNegative Whether values below 0 are allowed (margin).
 * @param {Function} props.onChange      Gets the new values.
 */
function SpacingControl( { label, values, units, hasPresets, allowNegative, onChange } ) {
	if ( hasPresets ) {
		return (
			<SpacingSizesControl
				label={ label }
				values={ values }
				onChange={ onChange }
				units={ units }
				minimumCustomValue={ allowNegative ? -Infinity : 0 }
				allowReset={ false }
			/>
		);
	}
	return (
		<BoxControl
			label={ label }
			values={ values }
			units={ units }
			onChange={ onChange }
			inputProps={ allowNegative ? { min: -Infinity } : undefined }
			allowReset={ false }
			__next40pxDefaultSize
		/>
	);
}

const PRESET = /^var:preset\|([a-z-]+)\|(.+)$/;

// A stored value as the control shows it: the value of the preset it names.
const presetValue = ( value, presets, type, key ) => {
	const match = 'string' === typeof value && value.match( PRESET );
	if ( ! match || match[ 1 ] !== type ) {
		return value;
	}
	return presets.find( ( preset ) => preset.slug === match[ 2 ] )?.[ key ] ?? value;
};

/**
 * The styles of one placeholder in one template.
 *
 * @param {Object}   props
 * @param {string}   props.template   'default' (the items) or 'block'.
 * @param {Object}   props.target     Entry of PLACEHOLDER_STYLE_TARGETS.
 * @param {Object}   props.value      { color, fontSize, fontWeight, fontStyle, textTransform,
 *                                    lineHeight } for text,
 *                                    { background, padding, margin, border } for the item.
 * @param {Function} props.onChange   Gets the new value.
 * @param {boolean}  props.isSelected Whether it was picked in the preview.
 */
function PlaceholderStylePanel( { template, target, value, onChange, isSelected } ) {
	const ref = useRef();
	const colorSettings = useMultipleOriginColorsAndGradients();
	const [ fontSizeSetting, spacingUnits, spacingSizes ] = useSettings( 'typography.fontSizes', 'spacing.units', 'spacing.spacingSizes' );
	const hasSpacingPresets = flatPresets( spacingSizes ).length > 0;
	// Without units BoxControl stores bare numbers, which are no valid CSS.
	const units = useCustomUnits( {
		availableUnits: spacingUnits || [ 'px', 'em', 'rem', 'vw', 'vh', '%' ],
	} );
	const colors = flatColors( colorSettings?.colors );
	const fontSizes = flatFontSizes( fontSizeSetting );

	useEffect( () => {
		if ( isSelected ) {
			ref.current?.scrollIntoView?.( { block: 'nearest', behavior: 'smooth' } );
		}
	}, [ isSelected ] );

	const panelId = `cpwp-placeholder-style-${ template }-${ target.key }`;
	const kind = target.kind || 'text';
	const isText = 'text' === kind;
	const label = target.label;

	// Sets several values at once, an empty one removes its key.
	const setMany = ( changes ) => {
		const next = { ...value };
		Object.entries( changes ).forEach( ( [ key, newValue ] ) => {
			if ( undefined === newValue || '' === newValue || null === newValue ) {
				delete next[ key ];
			} else {
				next[ key ] = newValue;
			}
		} );
		onChange( next );
	};
	const set = ( key, newValue ) => setMany( { [ key ]: newValue } );

	// Shown by default, like in the panels of WordPress, is only the size of a
	// text and the margin of the item. A button or badges (more link, terms) are
	// text: the size only. The others are chosen in the menu of the
	// panel and stay shown while they have a value.

	// A preset is stored by its slug, so it follows changes of the theme.
	const onColorChange = ( color ) => {
		const preset = color ? getColorObjectByColorValue( colors, color ) : null;
		set( 'color', preset?.slug ? `var:preset|color|${ preset.slug }` : color );
	};
	// A color of the given key, a preset by its slug.
	const onAnyColorChange = ( key ) => ( color ) => {
		const preset = color ? getColorObjectByColorValue( colors, color ) : null;
		set( key, preset?.slug ? `var:preset|color|${ preset.slug }` : color );
	};
	const onBackgroundChange = onAnyColorChange( 'background' );
	const onFontSizeChange = ( size, selected ) => {
		let preset = selected;
		if ( ! preset?.slug && size ) {
			preset = getFontSizeObjectByValue( fontSizes, size );
		}
		set( 'fontSize', preset?.slug ? `var:preset|font-size|${ preset.slug }` : size );
	};

	return (
		<div ref={ ref } className={ 'cpwp-placeholder-style-panel' + ( isSelected ? ' is-selected' : '' ) }>
			<ToolsPanel
				label={ label }
				panelId={ panelId }
				resetAll={ () => onChange( {} ) }
			>
				{ isText && (
					<>
						<ColorGradientSettingsDropdown
							panelId={ panelId }
							__experimentalIsRenderedInSidebar
							settings={ [ {
								label: __( 'Text', 'category-posts' ),
								colorValue: presetValue( value.color, colors, 'color', 'color' ),
								onColorChange,
								isShownByDefault: false,
								resetAllFilter: () => ( {} ),
								enableAlpha: true,
							}, ...( target.hover ? [ {
								label: __( 'Hover text', 'category-posts' ),
								colorValue: presetValue( value.hoverColor, colors, 'color', 'color' ),
								onColorChange: onAnyColorChange( 'hoverColor' ),
								isShownByDefault: false,
								resetAllFilter: () => ( {} ),
								enableAlpha: true,
							} ] : [] ) ] }
							{ ...colorSettings }
							gradients={ [] }
							disableCustomGradients
						/>
						<ToolsPanelItem
							panelId={ panelId }
							label={ __( 'Size', 'category-posts' ) }
							hasValue={ () => !! value.fontSize }
							onDeselect={ () => set( 'fontSize' ) }
							isShownByDefault
						>
							<FontSizePicker
								value={ presetValue( value.fontSize, fontSizes, 'font-size', 'size' ) }
								onChange={ onFontSizeChange }
								withReset={ false }
								withSlider
								size="__unstable-large"
								__nextHasNoMarginBottom
							/>
						</ToolsPanelItem>
						<ToolsPanelItem
							panelId={ panelId }
							label={ __( 'Appearance', 'category-posts' ) }
							hasValue={ () => !! ( value.fontWeight || value.fontStyle ) }
							onDeselect={ () => setMany( { fontWeight: undefined, fontStyle: undefined } ) }
						>
							<FontAppearanceControl
								value={ { fontStyle: value.fontStyle, fontWeight: value.fontWeight } }
								onChange={ ( { fontStyle, fontWeight } ) => setMany( { fontStyle, fontWeight } ) }
								size="__unstable-large"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</ToolsPanelItem>
						<ToolsPanelItem
							panelId={ panelId }
							label={ __( 'Letter case', 'category-posts' ) }
							hasValue={ () => !! value.textTransform }
							onDeselect={ () => set( 'textTransform' ) }
						>
							<TextTransformControl
								value={ value.textTransform }
								onChange={ ( textTransform ) => set( 'textTransform', textTransform ) }
								showNone
								isBlock
								size="__unstable-large"
								__nextHasNoMarginBottom
							/>
						</ToolsPanelItem>
						{ target.lineHeight && (
							<ToolsPanelItem
								panelId={ panelId }
								label={ __( 'Line height', 'category-posts' ) }
								hasValue={ () => !! value.lineHeight }
								onDeselect={ () => set( 'lineHeight' ) }
							>
								<LineHeightControl
									value={ value.lineHeight }
									onChange={ ( lineHeight ) => set( 'lineHeight', lineHeight ) }
									size="__unstable-large"
									__next40pxDefaultSize
									__unstableInputWidth="auto"
								/>
							</ToolsPanelItem>
						) }
					</>
				) }
				{ target.justify && (
					<ToolsPanelItem
						panelId={ panelId }
						label={ __( 'Justification', 'category-posts' ) }
						hasValue={ () => !! value.justify }
						onDeselect={ () => set( 'justify' ) }
					>
						<ToggleGroupControl
							label={ __( 'Justification', 'category-posts' ) }
							value={ value.justify }
							onChange={ ( justify ) => set( 'justify', justify ) }
							isBlock
							isDeselectable
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						>
							{ target.justify.map( ( justify ) => (
								<ToggleGroupControlOption key={ justify } value={ justify } label={ JUSTIFY_LABELS[ justify ] } />
							) ) }
						</ToggleGroupControl>
					</ToolsPanelItem>
				) }
				{ target.gap && (
					<ToolsPanelItem
						panelId={ panelId }
						label={ __( 'Gap', 'category-posts' ) }
						hasValue={ () => !! value.gap }
						onDeselect={ () => set( 'gap' ) }
					>
						<UnitControl
							label={ __( 'Gap', 'category-posts' ) }
							value={ value.gap }
							placeholder="0.5em"
							units={ units }
							min={ 0 }
							onChange={ ( gap ) => set( 'gap', gap ) }
							__next40pxDefaultSize
						/>
					</ToolsPanelItem>
				) }
				{ ( 'box' === kind || target.box ) && (
					<>
						<ColorGradientSettingsDropdown
							panelId={ panelId }
							__experimentalIsRenderedInSidebar
							settings={ [ {
								label: __( 'Background', 'category-posts' ),
								colorValue: presetValue( value.background, colors, 'color', 'color' ),
								onColorChange: onBackgroundChange,
								isShownByDefault: false,
								resetAllFilter: () => ( {} ),
								enableAlpha: true,
							}, ...( target.hover ? [ {
								label: __( 'Hover background', 'category-posts' ),
								colorValue: presetValue( value.hoverBackground, colors, 'color', 'color' ),
								onColorChange: onAnyColorChange( 'hoverBackground' ),
								isShownByDefault: false,
								resetAllFilter: () => ( {} ),
								enableAlpha: true,
							} ] : [] ) ] }
							{ ...colorSettings }
							gradients={ [] }
							disableCustomGradients
						/>
						<ToolsPanelItem
							panelId={ panelId }
							label={ __( 'Padding', 'category-posts' ) }
							hasValue={ () => !! value.padding }
							onDeselect={ () => set( 'padding' ) }
						>
							<SpacingControl
								label={ __( 'Padding', 'category-posts' ) }
								values={ value.padding }
								units={ units }
								hasPresets={ hasSpacingPresets }
								onChange={ ( padding ) => set( 'padding', withoutEmpty( padding ) ) }
							/>
						</ToolsPanelItem>
						<ToolsPanelItem
							panelId={ panelId }
							label={ __( 'Margin', 'category-posts' ) }
							hasValue={ () => !! value.margin }
							onDeselect={ () => set( 'margin' ) }
							isShownByDefault={ 'box' === kind }
						>
							<SpacingControl
								label={ __( 'Margin', 'category-posts' ) }
								values={ value.margin }
								units={ units }
								hasPresets={ hasSpacingPresets }
								onChange={ ( margin ) => set( 'margin', withoutEmpty( margin ) ) }
								// Like the margin of WordPress, it may be negative.
								allowNegative
							/>
						</ToolsPanelItem>
						<ToolsPanelItem
							panelId={ panelId }
							label={ __( 'Border', 'category-posts' ) }
							hasValue={ () => !! value.border }
							onDeselect={ () => set( 'border' ) }
						>
							<BorderBoxControl
								label={ __( 'Border', 'category-posts' ) }
								colors={ colors }
								value={ value.border }
								onChange={ ( border ) => set( 'border', withoutEmpty( border ) ) }
								enableAlpha
								enableStyle
								popoverOffset={ 40 }
								popoverPlacement="left-start"
								size="__unstable-large"
								__experimentalIsRenderedInSidebar
							/>
						</ToolsPanelItem>
					</>
				) }
			</ToolsPanel>
		</div>
	);
}

/**
 * One panel for each placeholder of each template. Rendered into the "Styles"
 * group: WordPress mounts it only while the "Styles" tab is open, which is
 * what onActiveChange reports.
 *
 * @param {Object}      props
 * @param {Object}      props.attributes     The block attributes.
 * @param {Function}    props.setAttributes  Sets block attributes.
 * @param {Object|null} props.selection      { template, target } picked in the preview.
 * @param {Function}    props.onActiveChange Gets true when mounted, false when unmounted.
 */
export function PlaceholderStylePanels( { attributes, setAttributes, selection, onActiveChange } ) {
	useEffect( () => {
		onActiveChange( true );
		return () => onActiveChange( false );
	}, [ onActiveChange ] );

	const styles = attributes.placeholderStyles || {};

	const onChange = ( template, key, value ) => {
		const forTemplate = { ...( styles[ template ] || {} ) };
		if ( Object.keys( value ).length ) {
			forTemplate[ key ] = value;
		} else {
			delete forTemplate[ key ];
		}
		const next = { ...styles };
		if ( Object.keys( forTemplate ).length ) {
			next[ template ] = forTemplate;
		} else {
			delete next[ template ];
		}
		setAttributes( { placeholderStyles: next } );
	};

	// In the order of the block: its title, the items, "Load more" or the
	// pagination below them.
	const outside = blockTargets( attributes );
	const groups = [
		{ template: 'block', targets: outside.filter( ( target ) => BLOCK_TITLE_TARGET === target ) },
		...templatesOf( attributes ).map( ( { template, text } ) => ( { template, targets: [ ITEM_TARGET, ...targetsIn( text ) ] } ) ),
		{ template: 'block', targets: outside.filter( ( target ) => BLOCK_TITLE_TARGET !== target ) },
	];

	return groups.flatMap( ( { template, targets } ) =>
		targets.map( ( target ) => (
			<PlaceholderStylePanel
				key={ `${ template }-${ target.key }` }
				template={ template }
				target={ target }
				value={ styles[ template ]?.[ target.key ] || {} }
				onChange={ ( value ) => onChange( template, target.key, value ) }
				isSelected={ !! selection && selection.template === template && selection.target === target.key }
			/>
		) )
	);
}
