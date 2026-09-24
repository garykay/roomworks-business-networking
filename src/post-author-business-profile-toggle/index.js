/**
 * Post-editor sidebar panel: lets an editor opt a post in to the
 * single-post-author-business-profile block, which is off by default even
 * when the post's author has a published business. When that author has
 * more than one published business, also lets the editor pick which one to
 * show (rbn_author_business_id) - render.php would otherwise have no way to
 * know which one was meant, and would fall back to showing every one of
 * them. Not a block itself (no block.json) - see webpack.config.js for how
 * this gets built, and RBN_Author_Business_Profile_Toggle for where both
 * meta keys it edits (and the REST route this fetches the author's
 * businesses from) are registered.
 *
 * @package RoomworksBusinessNetworking
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl, RadioControl, Spinner } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const SHOW_META_KEY = 'rbn_show_author_business_profile';
const BUSINESS_META_KEY = 'rbn_author_business_id';

function AuthorBusinessProfileToggle() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const authorId = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( 'author' ),
		[]
	);

	// register_post_meta() only registers these keys for 'post' - on any
	// other post type useEntityProp below would be editing meta the REST
	// schema doesn't know about, so bail out before rendering.
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	// Every published business belonging to this post's author, so the
	// radio control below only ever appears (and only ever offers a choice
	// between) businesses that could actually be shown - same "publish"
	// status render.php itself requires, in the same order
	// RBN_Business_Repository::get_published_for_user() returns them in, so
	// the default selection here matches render.php's own fallback. Fetched
	// from a custom route rather than the default `wp/v2/businesses`
	// collection endpoint filtered by `?author=` - see
	// RBN_Author_Business_Profile_Toggle's docblock for why: this site's
	// Wordfence firewall blocks any request with `author=` in its query
	// string outright.
	const [ businesses, setBusinesses ] = useState( [] );
	const [ hasResolvedBusinesses, setHasResolvedBusinesses ] = useState( false );

	useEffect( () => {
		if ( ! authorId ) {
			setBusinesses( [] );
			setHasResolvedBusinesses( true );
			return;
		}

		let cancelled = false;
		setHasResolvedBusinesses( false );

		apiFetch( {
			path: `/roomworks-business-networking/v1/author-businesses/${ authorId }`,
		} )
			.then( ( result ) => {
				if ( ! cancelled ) {
					setBusinesses( result );
					setHasResolvedBusinesses( true );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setBusinesses( [] );
					setHasResolvedBusinesses( true );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ authorId ] );

	if ( 'post' !== postType ) {
		return null;
	}

	// Meta defaults to false (register_post_meta's own default), so it
	// takes an explicit true to turn this on.
	const isEnabled = true === meta[ SHOW_META_KEY ];

	// The stored choice if it's still one of the author's current published
	// businesses, otherwise the first one alphabetically - the same
	// fallback render.php applies server-side, so what's highlighted here
	// always matches what would actually show on the front end.
	const selectedBusinessId =
		businesses.find( ( business ) => business.id === meta[ BUSINESS_META_KEY ] )
			?.id ??
		businesses[ 0 ]?.id ??
		0;

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
					setMeta( { ...meta, [ SHOW_META_KEY ]: value } )
				}
			/>

			{ isEnabled && ! hasResolvedBusinesses && <Spinner /> }

			{ isEnabled && hasResolvedBusinesses && businesses.length > 1 && (
				<RadioControl
					label={ __( 'Which business to show', 'roomworks-business-networking' ) }
					help={ __(
						'This author has more than one published business - choose which one to show below this post.',
						'roomworks-business-networking'
					) }
					selected={ selectedBusinessId }
					options={ businesses.map( ( business ) => ( {
						label: business.title || __( '(untitled)', 'roomworks-business-networking' ),
						value: business.id,
					} ) ) }
					onChange={ ( value ) =>
						setMeta( {
							...meta,
							[ BUSINESS_META_KEY ]: Number( value ),
						} )
					}
				/>
			) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'rbn-author-business-profile-toggle', {
	render: AuthorBusinessProfileToggle,
} );
