/**
 * Post-editor sidebar panel: lets an editor opt a post in to the
 * single-post-author-business-profile block, which is off by default even
 * when the post's author has a published business. Not a block itself (no
 * block.json) - see webpack.config.js for how this gets built, and
 * RBN_Author_Business_Profile_Toggle for where the
 * `rbn_show_author_business_profile` meta it edits is registered/enqueued.
 *
 * @package RoomworksBusinessNetworking
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const META_KEY = 'rbn_show_author_business_profile';

function AuthorBusinessProfileToggle() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	// register_post_meta() only registers this key for 'post' - on any
	// other post type useEntityProp below would be editing a meta key the
	// REST schema doesn't know about, so bail out before rendering.
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	if ( 'post' !== postType ) {
		return null;
	}

	// Meta defaults to false (register_post_meta's own default), so it
	// takes an explicit true to turn this on.
	const isEnabled = true === meta[ META_KEY ];

	return (
		<PluginDocumentSettingPanel
			name="rbn-author-business-profile"
			title={ __( "Author's Business Profile", 'roomworks-business-networking' ) }
		>
			<ToggleControl
				label={ __( "Show author's business profile", 'roomworks-business-networking' ) }
				help={
					isEnabled
						? __(
							'Shown below this post if the author has a published business.',
							'roomworks-business-networking'
						)
						: __(
							'Off by default - the business profile will not show on this post.',
							'roomworks-business-networking'
						)
				}
				checked={ isEnabled }
				onChange={ ( value ) =>
					setMeta( { ...meta, [ META_KEY ]: value } )
				}
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'rbn-author-business-profile-toggle', {
	render: AuthorBusinessProfileToggle,
} );
