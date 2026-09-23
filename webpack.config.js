/**
 * Extends the default @wordpress/scripts webpack config with extra entry
 * points that aren't blocks: post-editor sidebar scripts. wp-scripts only
 * auto-discovers entries by scanning src/**\/block.json, so a plain
 * editor-only script (no block.json of its own) has to be added here by
 * hand.
 *
 * @package RoomworksBusinessNetworking
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: () => ( {
		...defaultConfig.entry(),
		'post-author-business-profile-toggle': path.resolve(
			process.cwd(),
			'src/post-author-business-profile-toggle/index.js'
		),
		'advert-picker': path.resolve( process.cwd(), 'src/advert-picker/index.js' ),
	} ),
};
