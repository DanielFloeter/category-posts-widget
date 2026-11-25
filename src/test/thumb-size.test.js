/**
 * The aspect ratio of the thumbnails worked out into pixels.
 */
import { parseRatio, thumbSize, ratioOptions, ratioChoice, ORIGINAL } from '../thumb-size';

describe( 'parseRatio', () => {
	it( 'reads presets, plain numbers and nothing', () => {
		expect( parseRatio( '16/9' ) ).toBeCloseTo( 16 / 9 );
		expect( parseRatio( '1' ) ).toBe( 1 );
		expect( parseRatio( '1.62' ) ).toBe( 1.62 );
		expect( parseRatio( '' ) ).toBe( 0 );
		expect( parseRatio( ORIGINAL ) ).toBe( 0 );
		expect( parseRatio( '4/0' ) ).toBe( 0 );
		expect( parseRatio( 'abc' ) ).toBe( 0 );
	} );
} );

describe( 'thumbSize', () => {
	const dimensions = {
		thumbnail: { width: 150, height: 150 },
		medium: { width: 300, height: 300 },
		large: { width: 0, height: 1024 },
	};
	const size = ( args ) => thumbSize( { width: 200, height: 100, dimensions, ...args } );

	it( 'keeps the pixels without image size and aspect ratio', () => {
		expect( size( { imageSize: '', aspectRatio: '' } ) ).toEqual( { width: 200, height: 100 } );
	} );

	it( 'works the height out of the width and the aspect ratio', () => {
		expect( size( { imageSize: '', aspectRatio: '16/9' } ) ).toEqual( { width: 200, height: 113 } );
		expect( size( { imageSize: '', aspectRatio: '1.62' } ) ).toEqual( { width: 200, height: 123 } );
	} );

	it( 'takes the width of the image size and keeps the ratio so far', () => {
		expect( size( { imageSize: 'medium', aspectRatio: '' } ) ).toEqual( { width: 300, height: 150 } );
	} );

	it( 'takes the width of the image size and the aspect ratio', () => {
		expect( size( { imageSize: 'medium', aspectRatio: '4/3' } ) ).toEqual( { width: 300, height: 225 } );
	} );

	it( 'leaves each image its own ratio for "Original"', () => {
		expect( size( { imageSize: 'medium', aspectRatio: ORIGINAL } ) ).toEqual( { width: 300, height: 0 } );
	} );

	it( 'keeps a height of 0', () => {
		expect( size( { imageSize: 'medium', aspectRatio: '', height: 0 } ) ).toEqual( { width: 300, height: 0 } );
	} );

	it( 'keeps the width for a size without one', () => {
		expect( size( { imageSize: 'large', aspectRatio: '' } ) ).toEqual( { width: 200, height: 100 } );
		expect( size( { imageSize: 'full', aspectRatio: '1' } ) ).toEqual( { width: 200, height: 200 } );
	} );

	it( 'keeps the height without a width', () => {
		expect( size( { imageSize: '', aspectRatio: '1', width: 0 } ) ).toEqual( { width: 0, height: 100 } );
	} );
} );

describe( 'ratioOptions', () => {
	const options = ratioOptions( [ { label: 'Square', value: '1' } ] );

	it( 'lists "Free" and "Original" first, then the presets', () => {
		expect( options.map( ( option ) => option.value ) ).toEqual( [ '', ORIGINAL, '1' ] );
	} );

	it( 'shows a free ratio as "Free"', () => {
		expect( ratioChoice( options, '1.62' ) ).toBe( '' );
		expect( ratioChoice( options, '1' ) ).toBe( '1' );
		expect( ratioChoice( options, ORIGINAL ) ).toBe( ORIGINAL );
	} );
} );
