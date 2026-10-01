/**
 * Post-editor sidebar panel for 'post' and 'page': lets an admin pick which
 * advert (see RBN_Post_Type_Advert) is assigned to that page/post, stored as
 * the `rbn_advert_id` meta, or switch adverts off there entirely (the
 * `rbn_hide_adverts` meta, which also overrides the settings-screen
 * default advert). Not a block itself (no block.json) - see
 * webpack.config.js for how this gets built, and RBN_Advert_Picker for where
 * the meta it edits is registered/enqueued and how the advertising block
 * reads it back.
 *
 * @package
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { SelectControl, ToggleControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

const META_KEY = 'rbn_advert_id';
const HIDE_META_KEY = 'rbn_hide_adverts';

// Printed by RBN_Advert_Picker::enqueue_editor_script() - empty when no
// default advert applies to this post type.
const defaultAdvertTitle = window.rbnAdvertPicker?.defaultAdvertTitle || '';

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
	const isHidden = true === meta[ HIDE_META_KEY ];

	const options = [
		{
			label: defaultAdvertTitle
				? sprintf(
						/* translators: %s: title of the default advert. */
						__(
							'Use default (%s)',
							'roomworks-business-networking'
						),
						defaultAdvertTitle
					)
				: __( 'None', 'roomworks-business-networking' ),
			value: 0,
		},
		...( adverts || [] ).map( ( advert ) => ( {
			label:
				advert.title?.rendered ||
				__( '(no title)', 'roomworks-business-networking' ),
			value: advert.id,
		} ) ),
	];

	return (
		<PluginDocumentSettingPanel
			name="rbn-advert-picker"
			title={ __( 'Advert', 'roomworks-business-networking' ) }
		>
			<ToggleControl
				label={ __(
					'Show adverts on this page',
					'roomworks-business-networking'
				) }
				help={
					isHidden
						? __(
								'No adverts will show here, including the default advert.',
								'roomworks-business-networking'
							)
						: undefined
				}
				checked={ ! isHidden }
				onChange={ ( value ) =>
					setMeta( { ...meta, [ HIDE_META_KEY ]: ! value } )
				}
			/>
			{ ! isHidden && (
				<SelectControl
					label={ __(
						'Advert shown here',
						'roomworks-business-networking'
					) }
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
			) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'rbn-advert-picker', {
	render: AdvertPicker,
} );
