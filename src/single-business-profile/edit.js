/**
 * Editor UI for the Single Business Profile block.
 *
 * @package RoomworksBusinessNetworking
 */

/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';
/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';
/**
 * Renders a live preview by calling this block's own PHP render callback,
 * so the editor never has to duplicate RBN_Templates::business_profile()'s
 * markup in JS - what you see here is exactly what the front end renders.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/
 */
import ServerSideRender from '@wordpress/server-side-render';
/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object} props         Block props.
 * @param {Object} props.context Block context provided by an ancestor
 *                                template (postId/postType) - populated
 *                                automatically when this block is placed
 *                                inside a Single Business template, or when
 *                                editing a business post directly.
 *
 * @return {Element} Element to render.
 */
export default function Edit( { context } ) {
	const blockProps = useBlockProps();
	const postId     = context ? .postId;

	if ( ! postId || 'rbn_business' !== context ? .postType ) {
		return (
			< div { ...blockProps } >
				< p >
					{ __(
						'Single Business Profile: shows the business being viewed. Add this inside a Single Business template (Appearance ▸ Editor ▸ Templates) to see a live preview here.',
						'roomworks-business-networking'
					) }
				< / p >
			< / div >
		);
	}

	return (
		< div { ...blockProps } >
			< ServerSideRender
				block      = "roomworks-business-networking/single-business-profile"
				attributes = { {} }
				context    = { context }
			/ >
		< / div >
	);
}
