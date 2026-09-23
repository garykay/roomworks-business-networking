/**
 * Post-editor sidebar panel for 'post' and 'page': lets an admin pick which
 * advert (see RBN_Post_Type_Advert) is assigned to that page/post, stored as
 * the `rbn_advert_id` meta. Not a block itself (no block.json) - see
 * webpack.config.js for how this gets built, and RBN_Advert_Picker for where
 * the meta it edits is registered/enqueued and how the advertising block
 * reads it back.
 *
 * @package RoomworksBusinessNetworking
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { SelectControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const META_KEY = 'rbn_advert_id';

function AdvertPicker() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	// register_post_meta() only registers this key for 'post'/'page' - on
	// any other post type useEntityProp below would be editing a meta key
	// the REST schema doesn't know about, so bail out before rendering.
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const adverts = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'rbn_advert', {
				per_page: -1,
				status: [ 'publish', 'draft', 'pending' ],
				orderby: 'title',
				order: 'asc',
				_fields: [ 'id', 'title' ],
			} ),
		[]
	);

	if ( 'post' !== postType && 'page' !== postType ) {
		return null;
	}

	// On the very first render(s) the entity's meta hasn't finished loading
	// yet and useEntityProp returns undefined rather than {}.
	if ( ! meta ) {
		return null;
	}

	const selectedId = meta[ META_KEY ] || 0;

	const options = [
		{ label: __( 'None', 'roomworks-business-networking' ), value: 0 },
		...( adverts || [] ).map( ( advert ) => ( {
			label: advert.title?.rendered || __( '(no title)', 'roomworks-business-networking' ),
			value: advert.id,
		} ) ),
	];

	return (
		<PluginDocumentSettingPanel
			name="rbn-advert-picker"
			title={ __( 'Advert', 'roomworks-business-networking' ) }
		>
			<SelectControl
				label={ __( 'Advert shown here', 'roomworks-business-networking' ) }
				help={ __(
					'Used by the advertising block when it appears in a template on this page/post.',
					'roomworks-business-networking'
				) }
				value={ selectedId }
				options={ options }
				onChange={ ( value ) =>
					setMeta( { ...meta, [ META_KEY ]: Number( value ) } )
				}
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'rbn-advert-picker', {
	render: AdvertPicker,
} );
