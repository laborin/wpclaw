import { WpClawClient } from '../../../shared/api-client';
import {
	presentHistory,
	type HistoryTimelineEntry,
} from '../../../shared/history-presenter';
import { parseUiConfig, uiConfigToCssVars } from '../../../shared/ui-config';
import type { SystemPromptMode, ToolRun } from '../../../shared/types';
import './style.scss';
import '../view.scss';

const HISTORY_PAGE_SIZE = 80;
const BOTTOM_THRESHOLD = 48;

function escapeAttribute( value: string ): string {
	return value
		.replaceAll( '&', '&amp;' )
		.replaceAll( '"', '&quot;' )
		.replaceAll( '<', '&lt;' )
		.replaceAll( '>', '&gt;' );
}

function createMessageNode( role: string, content: string ): HTMLDivElement {
	const item = document.createElement( 'div' );
	item.className = `wpclaw-message wpclaw-message-${ role } wpclaw-timeline-entry`;

	const header = document.createElement( 'header' );
	header.textContent = role;

	const text = document.createElement( 'p' );
	text.textContent = content;

	item.appendChild( header );
	item.appendChild( text );

	return item;
}

function createToolRunNode( run: ToolRun ): HTMLDetailsElement {
	const node = document.createElement( 'details' );
	node.className =
		'wpclaw-tool-call wpclaw-message wpclaw-message-tool wpclaw-timeline-entry';

	const summary = document.createElement( 'summary' );
	summary.textContent = run.name;

	const args = document.createElement( 'pre' );
	args.textContent = JSON.stringify( run.args, null, 2 );

	const result = document.createElement( 'pre' );
	result.textContent = JSON.stringify(
		{ ok: run.ok, payload: run.payload, error: run.error },
		null,
		2
	);

	node.appendChild( summary );
	node.appendChild( args );
	node.appendChild( result );

	return node;
}

function createTimelineNode( entry: HistoryTimelineEntry ): HTMLElement {
	if ( entry.type === 'message' ) {
		return createMessageNode( entry.message.role, entry.message.content );
	}

	return createToolRunNode( entry.run );
}

function parseEnabledTools( raw: string | undefined ): string[] {
	if ( ! raw ) {
		return [];
	}

	try {
		const parsed = JSON.parse( raw );
		if ( Array.isArray( parsed ) ) {
			return parsed.filter(
				( entry ): entry is string => typeof entry === 'string'
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

function isNearBottom( node: HTMLElement ): boolean {
	return (
		node.scrollHeight - node.scrollTop - node.clientHeight <=
		BOTTOM_THRESHOLD
	);
}

function removeTimelineEntries( messagesRegion: HTMLElement ): void {
	messagesRegion
		.querySelectorAll( '.wpclaw-timeline-entry' )
		.forEach( ( node ) => node.remove() );
}

async function mountNode( root: HTMLElement ) {
	const client = new WpClawClient( {
		nonce: root.dataset.restNonce,
	} );

	const placeholder = root.dataset.placeholder ?? 'Ask something...';
	const maxHeight = root.dataset.maxHeight ?? '600px';
	const model = root.dataset.model ?? 'openai/gpt-4o-mini';
	const uiConfig = parseUiConfig( root.dataset.uiConfig );
	const uiVars = uiConfigToCssVars( uiConfig );

	root.classList.add( 'wpclaw-chat-window' );
	root.dataset.wpclawTheme = uiConfig.theme;
	root.style.maxHeight = maxHeight;
	Object.entries( uiVars ).forEach( ( [ key, value ] ) => {
		root.style.setProperty( key, value );
	} );

	root.innerHTML = `
    <header class="wpclaw-chat-header">
      <strong>WPClaw</strong>
      <div class="wpclaw-chat-actions">
        <button type="button" data-action="clear">Clear history</button>
        <button type="button" data-action="cancel" hidden>Cancel</button>
      </div>
    </header>
    <div class="wpclaw-message-list-wrap">
      <div class="wpclaw-message-list" data-region="messages">
        <button type="button" class="wpclaw-load-older" data-action="load-older" hidden>Load older messages</button>
      </div>
      <button type="button" class="wpclaw-jump-latest" data-action="jump-latest" hidden>Jump to latest</button>
    </div>
    <form class="wpclaw-composer" data-region="composer">
      <textarea rows="3" placeholder="${ escapeAttribute(
			placeholder
		) }"></textarea>
      <button type="submit">Send</button>
    </form>
    <p class="wpclaw-error" data-region="error" hidden></p>
  `;

	const messagesRegion = root.querySelector< HTMLElement >(
		'[data-region="messages"]'
	);
	const composer = root.querySelector< HTMLFormElement >(
		'[data-region="composer"]'
	);
	const textarea =
		composer?.querySelector< HTMLTextAreaElement >( 'textarea' );
	const submitButton = composer?.querySelector< HTMLButtonElement >(
		'button[type="submit"]'
	);
	const errorNode = root.querySelector< HTMLElement >(
		'[data-region="error"]'
	);
	const cancelButton = root.querySelector< HTMLButtonElement >(
		'[data-action="cancel"]'
	);
	const clearButton = root.querySelector< HTMLButtonElement >(
		'[data-action="clear"]'
	);
	const loadOlderButton = root.querySelector< HTMLButtonElement >(
		'[data-action="load-older"]'
	);
	const jumpLatestButton = root.querySelector< HTMLButtonElement >(
		'[data-action="jump-latest"]'
	);

	if (
		! messagesRegion ||
		! composer ||
		! textarea ||
		! submitButton ||
		! errorNode ||
		! cancelButton ||
		! clearButton ||
		! loadOlderButton ||
		! jumpLatestButton
	) {
		return;
	}

	const enabledTools = parseEnabledTools( root.dataset.enabledTools );
	const systemPromptOverride = root.dataset.systemPromptOverride ?? '';
	const systemPromptMode = readSystemPromptMode(
		root.dataset.systemPromptMode
	);
	let sending = false;
	let loadingOlder = false;
	let hasMore = false;
	let nextOffset: number | null = null;
	let stickToBottom = true;

	const setError = ( message: string | null ) => {
		if ( message === null || message.trim() === '' ) {
			errorNode.hidden = true;
			errorNode.textContent = '';
			return;
		}

		errorNode.hidden = false;
		errorNode.textContent = message;
	};

	const setButtonsState = () => {
		cancelButton.hidden = ! sending;
		submitButton.disabled = sending;
		submitButton.textContent = sending ? 'Sending...' : 'Send';
		textarea.disabled = sending;
		clearButton.disabled = sending;
		loadOlderButton.hidden = ! hasMore;
		loadOlderButton.disabled = loadingOlder || sending;
		loadOlderButton.textContent = loadingOlder
			? 'Loading...'
			: 'Load older messages';
		jumpLatestButton.hidden =
			stickToBottom ||
			messagesRegion.querySelectorAll( '.wpclaw-timeline-entry' )
				.length === 0;
	};

	const scrollToBottom = () => {
		messagesRegion.scrollTop = messagesRegion.scrollHeight;
		stickToBottom = true;
		setButtonsState();
	};

	const appendTimeline = ( entries: HistoryTimelineEntry[] ) => {
		entries.forEach( ( entry ) => {
			messagesRegion.appendChild( createTimelineNode( entry ) );
		} );
	};

	const prependTimeline = ( entries: HistoryTimelineEntry[] ) => {
		const firstTimelineNode =
			loadOlderButton.nextSibling ?? messagesRegion.firstChild;

		entries.forEach( ( entry ) => {
			messagesRegion.insertBefore(
				createTimelineNode( entry ),
				firstTimelineNode
			);
		} );
	};

	const loadLatest = async ( shouldScroll: boolean ) => {
		const history = await client.getHistory( {
			limit: HISTORY_PAGE_SIZE,
			offset: 0,
			order: 'desc',
		} );

		if ( ! history.ok ) {
			setError( history.error?.message ?? 'Failed to load history.' );
			return;
		}

		const presented = presentHistory(
			[ ...( history.messages ?? [] ) ].reverse()
		);

		removeTimelineEntries( messagesRegion );
		appendTimeline( presented.timeline );
		hasMore = Boolean( history.has_more );
		nextOffset =
			typeof history.next_offset === 'number'
				? history.next_offset
				: null;

		if ( shouldScroll ) {
			requestAnimationFrame( () => {
				scrollToBottom();
			} );
		} else {
			setButtonsState();
		}
	};

	const loadOlder = async () => {
		if ( loadingOlder || ! hasMore || nextOffset === null ) {
			return;
		}

		loadingOlder = true;
		setButtonsState();

		const previousHeight = messagesRegion.scrollHeight;
		const previousTop = messagesRegion.scrollTop;
		const history = await client.getHistory( {
			limit: HISTORY_PAGE_SIZE,
			offset: nextOffset,
			order: 'desc',
		} );

		if ( ! history.ok ) {
			loadingOlder = false;
			setButtonsState();
			setError(
				history.error?.message ?? 'Failed to load older messages.'
			);
			return;
		}

		const presented = presentHistory(
			[ ...( history.messages ?? [] ) ].reverse()
		);

		if ( presented.timeline.length > 0 ) {
			prependTimeline( presented.timeline );

			requestAnimationFrame( () => {
				const heightDiff = messagesRegion.scrollHeight - previousHeight;
				messagesRegion.scrollTop = previousTop + heightDiff;
			} );
		}

		hasMore = Boolean( history.has_more );
		nextOffset =
			typeof history.next_offset === 'number'
				? history.next_offset
				: null;
		loadingOlder = false;
		setButtonsState();
	};

	const onScroll = () => {
		stickToBottom = isNearBottom( messagesRegion );
		setButtonsState();
	};

	messagesRegion.addEventListener( 'scroll', onScroll );

	loadOlderButton.addEventListener( 'click', () => {
		void loadOlder();
	} );

	jumpLatestButton.addEventListener( 'click', () => {
		scrollToBottom();
	} );

	clearButton.addEventListener( 'click', async () => {
		await client.clearHistory();
		removeTimelineEntries( messagesRegion );
		hasMore = false;
		nextOffset = null;
		stickToBottom = true;
		setError( null );
		setButtonsState();
	} );

	cancelButton.addEventListener( 'click', async () => {
		await client.cancelCurrentRun();
		sending = false;
		setButtonsState();
	} );

	textarea.addEventListener( 'keydown', ( event ) => {
		if (
			event.key === 'Enter' &&
			! event.shiftKey &&
			! event.ctrlKey &&
			! event.metaKey &&
			! event.altKey
		) {
			event.preventDefault();
			composer.requestSubmit();
		}
	} );

	composer.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const content = textarea.value.trim();
		if ( content === '' || sending ) {
			return;
		}

		sending = true;
		scrollToBottom();
		setButtonsState();
		setError( null );

		const response = await client.sendChat( {
			message: content,
			model,
			enabled_tools: enabledTools,
			system_prompt_override: systemPromptOverride,
			system_prompt_mode: systemPromptMode,
		} );

		sending = false;
		setButtonsState();

		if ( ! response.ok ) {
			setError( response.error?.message ?? 'Chat request failed.' );
			return;
		}

		textarea.value = '';
		await loadLatest( true );
	} );

	await loadLatest( true );
	setButtonsState();
}

function mount() {
	const nodes = document.querySelectorAll< HTMLElement >(
		'[data-wpclaw-interactivity-chat]'
	);
	nodes.forEach( ( node ) => {
		void mountNode( node );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
