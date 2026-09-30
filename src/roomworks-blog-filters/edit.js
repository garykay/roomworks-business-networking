/**
 * Editor UI for the Roomworks Blog Filters block - no configurable
 * attributes, so this just previews the live server render (the community
 * field only appears for a logged-in member who's joined a community, same
 * as the front end).
 *
 * @package RoomworksBusinessNetworking
 */

import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import './editor.scss';

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<ServerSideRender block="roomworks-business-networking/roomworks-blog-filters" />
		</div>
	);
}
