/**
 * Editor UI for the Community Notice Board block.
 *
 * @package RoomworksBusinessNetworking
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import './editor.scss';

/**
 * @return {Element} Element to render.
 */
export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<p>
				{ __(
					'Community Notice Board: lists members’ requests for work to be done, with search, category, urgency and location filters. Results are loaded from the site, not shown here in the editor.',
					'roomworks-business-networking'
				) }
			</p>
		</div>
	);
}
