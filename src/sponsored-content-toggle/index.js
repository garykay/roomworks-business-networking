/**
 * Post-editor sidebar panel: marks a post as paid-for ("sponsored")
 * content. Not a block itself (no block.json) - see webpack.config.js for
 * how this gets built, and RBN_Sponsored_Content for where the
 * `rbn_is_sponsored` meta it edits is registered/enqueued and what turning
 * it on does on the front end.
 *
 * @package RoomworksBusinessNetworking
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const META_KEY = 'rbn_is_sponsored';

function SponsoredContentToggle() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	// register_post_meta() only registers this key for 'post' - see the
	// same guard in post-author-business-profile-toggle.
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	if ( 'post' !== postType ) {
		return null;
	}

	const isEnabled = true === meta[ META_KEY ];

	return (
		<PluginDocumentSettingPanel
			name="rbn-sponsored-content"
			title={ __( 'Sponsored Content', 'roomworks-business-networking' ) }
		>
			<ToggleControl
				label={ __( 'This is a paid (sponsored) post', 'roomworks-business-networking' ) }
				help={
					isEnabled
						? __(
							'A "Sponsored" label shows above the title, and links to other sites are marked rel="sponsored".',
							'roomworks-business-networking'
						)
						: __(
							'Turn on whenever a business has paid for this post - advertising rules require paid content to be labelled.',
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

registerPlugin( 'rbn-sponsored-content-toggle', {
	render: SponsoredContentToggle,
} );
