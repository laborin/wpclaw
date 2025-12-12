import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( 'wpclaw/chat-react', {
	edit: Edit,
	save,
} );
