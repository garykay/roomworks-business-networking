/**
 * Registers the Roomworks Blog Filters block.
 *
 * @package RoomworksBusinessNetworking
 */

import { registerBlockType } from '@wordpress/blocks';
import './style.scss';
import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
} );
