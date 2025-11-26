import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( 'wp-native-agent/chat-react', {
	edit: Edit,
	save,
} );
