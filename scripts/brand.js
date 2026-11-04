const path = require( 'path' );
const fs = require( 'fs' );
const ROOT = path.join( __dirname, '..' );
const { chromium } = require( path.join( ROOT, 'node_modules/@playwright/test' ) );

const shot = 'data:image/png;base64,' + fs.readFileSync( path.join( ROOT, '.wordpress-org/screenshot-2.png' ) ).toString( 'base64' );
const historyIcon = ( size, color ) => `<svg width="${ size }" height="${ size }" viewBox="0 0 24 24"><path fill="${ color }" d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>`;
const bg = 'background:linear-gradient(135deg,#1d4f91 0%,#2271b1 55%,#3a8fd6 100%);';
const font = "font-family:-apple-system,'Segoe UI',Inter,Helvetica,Arial,sans-serif;";
const previewShot = ( w, h ) => `<div style="width:${ w }px;height:${ h }px;border-radius:${ Math.round( h / 14 ) }px;overflow:hidden;box-shadow:0 ${ h / 25 }px ${ h / 8 }px rgba(0,0,0,.35);background:url(${ shot }) 50% 50% / 100% auto no-repeat #f0f0f0"></div>`;

const banner = ( w, h ) => `<div style="${ bg }${ font }width:${ w }px;height:${ h }px;display:flex;align-items:center;justify-content:space-between;padding:0 ${ w * 0.06 }px;box-sizing:border-box;color:#fff">
	<div>
		<div style="display:flex;align-items:center;gap:${ h * 0.05 }px">
			${ historyIcon( h * 0.22, '#fff' ) }
			<div style="font-size:${ h * 0.14 }px;font-weight:700;letter-spacing:-.5px;white-space:nowrap">Options Revisions</div>
		</div>
		<div style="font-size:${ h * 0.065 }px;opacity:.92;margin-top:${ h * 0.04 }px">Unified diffs & instant rollback for wp_options</div>
	</div>
	${ previewShot( w * 0.36, h * 0.72 ) }
</div>`;

const items = [
	[ 'icon-256x256.png', 256, 256, `<div style="${ bg }width:256px;height:256px;display:flex;align-items:center;justify-content:center">${ historyIcon( 160, '#fff' ) }</div>` ],
	[ 'icon-128x128.png', 128, 128, `<div style="${ bg }width:128px;height:128px;display:flex;align-items:center;justify-content:center">${ historyIcon( 80, '#fff' ) }</div>` ],
	[ 'banner-772x250.png', 772, 250, banner( 772, 250 ) ],
	[ 'banner-1544x500.png', 1544, 500, banner( 1544, 500 ) ],
	[ '../.github/media/social-preview.png', 1280, 640, banner( 1280, 640 ) ],
];

( async () => {
	const browser = await chromium.launch( { channel: 'chrome' } );
	for ( const [ name, w, h, html ] of items ) {
		const page = await browser.newPage( { viewport: { width: w, height: h } } );
		await page.setContent( `<body style="margin:0">${ html }</body>` );
		await page.screenshot( { path: path.join( ROOT, '.wordpress-org', name ) } );
		await page.close();
	}
	await browser.close();
	console.log( 'Generated icons, banners and social-preview.' );
} )();
