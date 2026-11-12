/**
 * Tests for the footer link of a widget turned into a block (#9).
 *
 * Run with: npm run test:unit
 */
import widgetToBlockAttributes, { footerLinkContent } from '../widget-transform';

describe( 'the footer link', () => {
	test( 'becomes a paragraph, not block attributes', () => {
		const raw = { footer_link: 'https://example.com/news', footer_link_text: 'More news' };
		expect( footerLinkContent( raw ) ).toBe( '<a href="https://example.com/news">More news</a>' );
		const attributes = widgetToBlockAttributes( raw );
		expect( attributes ).not.toHaveProperty( 'footerLink' );
		expect( attributes ).not.toHaveProperty( 'footerLinkText' );
	} );

	test( 'a URL without a text is its own text', () => {
		expect( footerLinkContent( { footer_link: 'https://example.com' } ) ).toBe( '<a href="https://example.com">https://example.com</a>' );
	} );

	test( 'opens in a new tab', () => {
		expect( footerLinkContent( { footer_link: '/news', footer_link_text: 'News', footer_link_target: 'on' } ) )
			.toBe( '<a href="/news" target="_blank" rel="noreferrer noopener">News</a>' );
	} );

	test( 'is escaped', () => {
		expect( footerLinkContent( { footer_link: 'https://example.com/?a=1&b="2"', footer_link_text: '<b>Tom & Jerry</b>' } ) )
			.toBe( '<a href="https://example.com/?a=1&amp;b=&quot;2&quot;">&lt;b>Tom &amp; Jerry&lt;/b></a>' );
	} );

	test( 'gets a protocol like esc_url()', () => {
		expect( footerLinkContent( { footer_link: 'example.com' } ) ).toBe( '<a href="http://example.com">http://example.com</a>' );
	} );

	test( 'is dropped for an unknown protocol', () => {
		expect( footerLinkContent( { footer_link: 'javascript:alert(1)', footer_link_text: 'x' } ) ).toBeNull();
	} );

	test( 'no paragraph without a URL', () => {
		expect( footerLinkContent( {} ) ).toBeNull();
		expect( footerLinkContent( { footer_link: '', footer_link_text: 'News' } ) ).toBeNull();
	} );

	test( 'a text without a URL stays with the block, the server finds the archive link', () => {
		expect( widgetToBlockAttributes( { footer_link_text: 'News', footer_link_target: 'on' } ) )
			.toMatchObject( { footerLinkText: 'News', footerLinkTarget: true } );
		expect( widgetToBlockAttributes( { footer_link_text: 'News' } ).footerLinkTarget ).toBe( false );
	} );
} );
