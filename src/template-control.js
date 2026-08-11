/**
 * WordPress dependencies
 */
import { useRef, useState, useLayoutEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { BaseControl, Button } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';

/**
 * All placeholders the template supports.
 *
 * Keep in sync with the widget UI in class-widget.php
 * (`cpwp-open-placholder-dropdown-menu`) and with get_template_regex().
 *
 * @type {Array<{id: string, insert: string, label: string, description: string, meta?: boolean}>}
 */
export const PLACEHOLDERS = [
	{
		id: 'newline',
		insert: '\n',
		label: __( 'New line', 'category-posts' ),
		description: __( 'Line break', 'category-posts' ),
		meta: true,
	},
	{
		id: 'emptyline',
		insert: '\n\n',
		label: __( 'Empty line', 'category-posts' ),
		description: __( 'Next line is a paragraph', 'category-posts' ),
		meta: true,
	},
	{
		id: 'title',
		insert: '%title%',
		label: '%title%',
		description: __( 'Post title', 'category-posts' ),
	},
	{
		id: 'thumb',
		insert: '%thumb%',
		label: '%thumb%',
		description: __( 'Post thumbnail possibly wrapped by text', 'category-posts' ),
	},
	{
		id: 'date',
		insert: '%date%',
		label: '%date%',
		description: __( 'Post publish date', 'category-posts' ),
	},
	{
		id: 'excerpt',
		insert: '%excerpt%',
		label: '%excerpt%',
		description: __( 'Post excerpt', 'category-posts' ),
	},
	{
		id: 'more-link',
		insert: '%more-link%',
		label: '%more-link%',
		description: __( 'Read more text', 'category-posts' ),
	},
	{
		id: 'author',
		insert: '%author%',
		label: '%author%',
		description: __( 'Post author', 'category-posts' ),
	},
	{
		id: 'commentnum',
		insert: '%commentnum%',
		label: '%commentnum%',
		description: __( 'The number of comments to the post', 'category-posts' ),
	},
	{
		id: 'post_tag',
		insert: '%post_tag%',
		label: '%post_tag%',
		description: __( 'Post tags', 'category-posts' ),
	},
	{
		id: 'category',
		insert: '%category%',
		label: '%category%',
		description: __( 'Post categories', 'category-posts' ),
	},
];

/**
 * Only the real `%tag%` placeholders, used by the autocomplete.
 */
const TAGS = PLACEHOLDERS.filter( ( placeholder ) => ! placeholder.meta );

/**
 * Find the placeholder token the caret currently sits in.
 *
 * Returns null when the caret is not directly behind a `%`+word sequence, or
 * when the `%` is obviously the closing one of an already complete tag
 * (i.e. preceded by a word character, as in `%title%|`).
 *
 * @param {string} text  Full textarea value.
 * @param {number} caret Caret offset.
 * @return {?{start: number, query: string}} Token info or null.
 */
function findToken( text, caret ) {
	const before = text.substring( 0, caret );
	const match = /%([a-z0-9_-]*)$/i.exec( before );

	if ( ! match ) {
		return null;
	}

	const start = caret - match[ 0 ].length;
	const charBefore = start > 0 ? text.charAt( start - 1 ) : '';

	if ( /[a-z0-9_-]/i.test( charBefore ) ) {
		return null; // Closing `%` of an existing tag.
	}

	return { start, query: match[ 1 ].toLowerCase() };
}

/**
 * Textarea for the post details template with two ways to add placeholders:
 *
 * 1. A row of buttons below the textarea, inserting at the caret position.
 * 2. An autocomplete list that opens while typing `%`.
 *
 * @param {Object}   props
 * @param {string}   props.label    Control label.
 * @param {string}   props.help     Help text below the control.
 * @param {string}   props.value    Current template.
 * @param {number}   props.rows     Number of textarea rows.
 * @param {Function} props.onChange Called with the new template.
 * @return {JSX.Element} Element.
 */
export default function TemplateControl( {
	label = __( 'Template', 'category-posts' ),
	help,
	value = '',
	rows = 8,
	onChange,
} ) {
	const instanceId = useInstanceId( TemplateControl, 'cpwp-template-control' );
	const textareaRef = useRef( null );
	// { pos, expected } – caret to restore once the controlled value has landed.
	const caretRef = useRef( null );
	// { items, tokenStart, activeIndex } – open autocomplete, null when closed.
	const [ suggestions, setSuggestions ] = useState( null );

	// A controlled textarea loses the caret when text is inserted in the
	// middle, so put it back as soon as the new value has been rendered.
	useLayoutEffect( () => {
		const caret = caretRef.current;

		if ( ! caret || ! textareaRef.current || caret.expected !== value ) {
			return;
		}

		caretRef.current = null;
		textareaRef.current.focus();
		textareaRef.current.setSelectionRange( caret.pos, caret.pos );
	} );

	/**
	 * Replace a range of the template and remember where the caret goes.
	 *
	 * @param {number} start Start offset.
	 * @param {number} end   End offset.
	 * @param {string} text  Replacement.
	 */
	const replaceRange = ( start, end, text ) => {
		const next = value.substring( 0, start ) + text + value.substring( end );

		caretRef.current = { pos: start + text.length, expected: next };
		onChange( next );
	};

	/**
	 * Insert text at the caret, replacing the selection if there is one.
	 *
	 * @param {string} text Text to insert.
	 */
	const insertAtCursor = ( text ) => {
		const element = textareaRef.current;
		const start = element ? element.selectionStart : value.length;
		const end = element ? element.selectionEnd : value.length;

		replaceRange( start, end, text );
	};

	/**
	 * Open, update or close the autocomplete for the given state.
	 *
	 * @param {string} text  Full value.
	 * @param {number} caret Caret offset.
	 */
	const refreshSuggestions = ( text, caret ) => {
		const token = findToken( text, caret );

		if ( ! token ) {
			setSuggestions( null );
			return;
		}

		const items = TAGS.filter(
			( placeholder ) =>
				placeholder.insert.substring( 1 ).toLowerCase().indexOf( token.query ) === 0
		);

		if ( ! items.length ) {
			setSuggestions( null );
			return;
		}

		setSuggestions( { items, tokenStart: token.start, activeIndex: 0 } );
	};

	/**
	 * Accept an autocomplete suggestion.
	 *
	 * @param {Object} placeholder The chosen placeholder.
	 */
	const acceptSuggestion = ( placeholder ) => {
		if ( ! suggestions || ! textareaRef.current ) {
			return;
		}

		replaceRange(
			suggestions.tokenStart,
			textareaRef.current.selectionStart,
			placeholder.insert
		);
		setSuggestions( null );
	};

	const onTextareaChange = ( event ) => {
		const next = event.target.value;
		const caret = event.target.selectionStart;

		onChange( next );
		refreshSuggestions( next, caret );
	};

	const onTextareaKeyDown = ( event ) => {
		if ( ! suggestions ) {
			return;
		}

		const { items, activeIndex } = suggestions;

		switch ( event.key ) {
			case 'ArrowDown':
				event.preventDefault();
				setSuggestions( {
					...suggestions,
					activeIndex: ( activeIndex + 1 ) % items.length,
				} );
				break;
			case 'ArrowUp':
				event.preventDefault();
				setSuggestions( {
					...suggestions,
					activeIndex: ( activeIndex - 1 + items.length ) % items.length,
				} );
				break;
			case 'Enter':
			case 'Tab':
				event.preventDefault();
				acceptSuggestion( items[ activeIndex ] );
				break;
			case 'Escape':
				event.preventDefault();
				event.stopPropagation(); // Do not let the editor close the sidebar.
				setSuggestions( null );
				break;
			default:
				break;
		}
	};

	const suggestionsId = `${ instanceId }-suggestions`;

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ instanceId }
			label={ label }
			help={ help }
			className="cpwp-template-control"
		>
			<div className="cpwp-template-control__field">
				<textarea
					ref={ textareaRef }
					id={ instanceId }
					className="components-textarea-control__input"
					rows={ rows }
					value={ value }
					onChange={ onTextareaChange }
					onKeyDown={ onTextareaKeyDown }
					onBlur={ () => setSuggestions( null ) }
					onClick={ () => setSuggestions( null ) }
					role="combobox"
					aria-expanded={ !! suggestions }
					aria-autocomplete="list"
					aria-controls={ suggestions ? suggestionsId : undefined }
					aria-activedescendant={
						suggestions
							? `${ instanceId }-option-${ suggestions.activeIndex }`
							: undefined
					}
				/>
				{ suggestions && (
					<ul
						className="cpwp-template-control__suggestions"
						id={ suggestionsId }
						role="listbox"
						aria-label={ __( 'Placeholder suggestions', 'category-posts' ) }
					>
						{ suggestions.items.map( ( placeholder, index ) => (
							<li key={ placeholder.id } role="presentation">
								<button
									type="button"
									id={ `${ instanceId }-option-${ index }` }
									role="option"
									aria-selected={ index === suggestions.activeIndex }
									className={
										'cpwp-template-control__suggestion' +
										( index === suggestions.activeIndex ? ' is-active' : '' )
									}
									// Keep the focus (and the caret) in the textarea.
									onMouseDown={ ( event ) => {
										event.preventDefault();
										acceptSuggestion( placeholder );
									} }
								>
									<span className="cpwp-template-control__tag">
										{ placeholder.label }
									</span>
									<span className="cpwp-template-control__description">
										{ placeholder.description }
									</span>
								</button>
							</li>
						) ) }
					</ul>
				) }
			</div>
			<div className="cpwp-template-control__placeholders">
				{ PLACEHOLDERS.map( ( placeholder ) => (
					<Button
						key={ placeholder.id }
						variant="secondary"
						size="small"
						showTooltip
						label={ placeholder.description }
						className={
							'cpwp-template-control__chip' +
							( placeholder.meta ? ' is-meta' : '' )
						}
						onClick={ () => insertAtCursor( placeholder.insert ) }
					>
						{ placeholder.meta ? `↵ ${ placeholder.label }` : placeholder.label }
					</Button>
				) ) }
			</div>
		</BaseControl>
	);
}
