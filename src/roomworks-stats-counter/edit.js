/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';
/**
 * React hook that is used to mark the block wrapper element, plus the
 * sidebar controls used to customise each stat's label/suffix.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
/**
 * Renders the block's own render.php inside the editor, so the numbers
 * shown while editing are the same live counts visitors will see.
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
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Current block attributes.
 * @param {Function} props.setAttributes Updates block attributes.
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		membersLabel,
		membersSuffix,
		businessesLabel,
		businessesSuffix,
		citiesLabel,
		citiesSuffix,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Members', 'roomworks-stats-counter' ) }>
					<TextControl
						label={ __( 'Label', 'roomworks-stats-counter' ) }
						value={ membersLabel }
						onChange={ ( value ) => setAttributes( { membersLabel: value } ) }
					/>
					<TextControl
						label={ __( 'Suffix', 'roomworks-stats-counter' ) }
						help={ __( 'Shown straight after the number, e.g. "+".', 'roomworks-stats-counter' ) }
						value={ membersSuffix }
						onChange={ ( value ) => setAttributes( { membersSuffix: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Businesses Listed', 'roomworks-stats-counter' ) }>
					<TextControl
						label={ __( 'Label', 'roomworks-stats-counter' ) }
						value={ businessesLabel }
						onChange={ ( value ) => setAttributes( { businessesLabel: value } ) }
					/>
					<TextControl
						label={ __( 'Suffix', 'roomworks-stats-counter' ) }
						help={ __( 'Shown straight after the number, e.g. "+".', 'roomworks-stats-counter' ) }
						value={ businessesSuffix }
						onChange={ ( value ) => setAttributes( { businessesSuffix: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'UK Cities', 'roomworks-stats-counter' ) }>
					<TextControl
						label={ __( 'Label', 'roomworks-stats-counter' ) }
						value={ citiesLabel }
						onChange={ ( value ) => setAttributes( { citiesLabel: value } ) }
					/>
					<TextControl
						label={ __( 'Suffix', 'roomworks-stats-counter' ) }
						help={ __( 'Shown straight after the number, e.g. "+".', 'roomworks-stats-counter' ) }
						value={ citiesSuffix }
						onChange={ ( value ) => setAttributes( { citiesSuffix: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block="create-block/roomworks-stats-counter"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
