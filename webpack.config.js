/**
 * Extends the default @wordpress/scripts webpack config with one extra
 * entry point that isn't a block: the post-editor sidebar toggle for the
 * single-post-author-business-profile block. wp-scripts only auto-discovers
 * entries by scanning src/**\/block.json, so a plain editor-only script
 * (no block.json of its own) has to be added here by hand.
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
	} ),
};
