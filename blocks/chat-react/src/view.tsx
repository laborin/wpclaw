import { render } from '@wordpress/element';
import { parseUiConfig, type ChatUiConfig } from '../../../shared/ui-config';
import type { SystemPromptMode } from '../../../shared/types';
import ChatWindow from './components/ChatWindow';
import './style.scss';
import '../view.scss';

type RootConfig = {
	placeholder: string;
	model: string;
	maxHeight: string;
	enabledTools: string[];
	systemPromptOverride: string;
	systemPromptMode: SystemPromptMode;
	nonce?: string;
	uiConfig: ChatUiConfig;
};

function readEnabledTools( raw: string | undefined ): string[] {
	if ( ! raw ) {
		return [];
	}

	try {
		const parsed = JSON.parse( raw );

		if ( Array.isArray( parsed ) ) {
			return parsed.filter(
				( value ): value is string => typeof value === 'string'
			);
		}
	} catch {
		return [];
	}

	return [];
}

function readSystemPromptMode( raw: string | undefined ): SystemPromptMode {
	return raw === 'append' ? 'append' : 'override';
}

function parseConfig( element: HTMLElement ): RootConfig {
	return {
		placeholder: element.dataset.placeholder ?? 'Ask something...',
		model: element.dataset.model ?? 'openai/gpt-4o-mini',
		maxHeight: element.dataset.maxHeight ?? '600px',
		enabledTools: readEnabledTools( element.dataset.enabledTools ),
		systemPromptOverride: element.dataset.systemPromptOverride ?? '',
		systemPromptMode: readSystemPromptMode(
			element.dataset.systemPromptMode
		),
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
				enabledTools={ config.enabledTools }
				systemPromptOverride={ config.systemPromptOverride }
				systemPromptMode={ config.systemPromptMode }
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
