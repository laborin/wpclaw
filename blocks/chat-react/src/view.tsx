import { render } from '@wordpress/element';
import { parseUiConfig, type ChatUiConfig } from '../../../shared/ui-config';
import ChatWindow from './components/ChatWindow';
import './style.scss';
import '../view.scss';

type RootConfig = {
	placeholder: string;
	model: string;
	maxHeight: string;
	nonce?: string;
	uiConfig: ChatUiConfig;
};

function parseConfig( element: HTMLElement ): RootConfig {
	return {
		placeholder: element.dataset.placeholder ?? 'Ask something...',
		model: element.dataset.model ?? 'openai/gpt-4o-mini',
		maxHeight: element.dataset.maxHeight ?? '600px',
		nonce: element.dataset.restNonce,
		uiConfig: parseUiConfig( element.dataset.uiConfig ),
	};
}

function mount() {
	const nodes = document.querySelectorAll< HTMLElement >(
		'[data-wpclaw-react-chat]'
	);

	nodes.forEach( ( node ) => {
		const config = parseConfig( node );

		render(
			<ChatWindow
				placeholder={ config.placeholder }
				model={ config.model }
				maxHeight={ config.maxHeight }
				nonce={ config.nonce }
				uiConfig={ config.uiConfig }
			/>,
			node
		);
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
