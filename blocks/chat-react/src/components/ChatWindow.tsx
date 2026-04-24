import type { CSSProperties, UIEventHandler } from 'react';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { WpClawClient } from '../../../../shared/api-client';
import {
	type ChatUiConfig,
	uiConfigToCssVars,
} from '../../../../shared/ui-config';
import CancelButton from './CancelButton';
import Composer from './Composer';
import MessageList from './MessageList';
import { useChatStream } from '../hooks/useChatStream';
import { useHistory } from '../hooks/useHistory';

type ChatWindowProps = {
	placeholder: string;
	model: string;
	maxHeight: string;
	nonce?: string;
	uiConfig: ChatUiConfig;
};

const BOTTOM_THRESHOLD = 48;

function isNearBottom( node: HTMLDivElement ): boolean {
	return (
		node.scrollHeight - node.scrollTop - node.clientHeight <=
		BOTTOM_THRESHOLD
	);
}

function ChatWindow( {
	placeholder,
	model,
	maxHeight,
	nonce,
	uiConfig,
}: ChatWindowProps ) {
	const client = useMemo( () => new WpClawClient( { nonce } ), [ nonce ] );
	const history = useHistory( client );
	const stream = useChatStream( client );
	const messagesRef = useRef< HTMLDivElement >( null );
	const [ stickToBottom, setStickToBottom ] = useState( true );
	const visibleTimeline = useMemo(
		() => [ ...history.timeline, ...stream.liveTimeline ],
		[ history.timeline, stream.liveTimeline ]
	);

	const windowStyle = useMemo( () => {
		const uiVars = uiConfigToCssVars( uiConfig );

		return {
			maxHeight,
			...uiVars,
		} as CSSProperties;
	}, [ maxHeight, uiConfig ] );

	const send = async ( content: string ) => {
		history.setError( null );
		stream.setError( null );
		const node = messagesRef.current;
		if ( node ) {
			node.scrollTop = node.scrollHeight;
		}
		setStickToBottom( true );

		await stream.send( {
			message: content,
			model,
		} );

		await history.reload();
		stream.clearLive();
	};

	const clearHistory = async () => {
		await history.clear();
		history.setError( null );
		stream.setError( null );
		stream.clearLive();
	};

	const loadOlder = async () => {
		const node = messagesRef.current;
		if ( ! node ) {
			return;
		}

		const previousHeight = node.scrollHeight;
		const previousTop = node.scrollTop;
		const appended = await history.loadOlder();

		if ( appended < 1 ) {
			return;
		}

		requestAnimationFrame( () => {
			const currentNode = messagesRef.current;
			if ( ! currentNode ) {
				return;
			}

			const heightDiff = currentNode.scrollHeight - previousHeight;
			currentNode.scrollTop = previousTop + heightDiff;
		} );
	};

	const onScroll: UIEventHandler< HTMLDivElement > = () => {
		const node = messagesRef.current;
		if ( ! node ) {
			return;
		}

		setStickToBottom( isNearBottom( node ) );
	};

	useEffect( () => {
		const node = messagesRef.current;
		if ( ! node ) {
			return;
		}

		if ( stickToBottom ) {
			node.scrollTop = node.scrollHeight;
		}
	}, [ visibleTimeline.length, stickToBottom ] );

	const jumpToLatest = () => {
		const node = messagesRef.current;
		if ( ! node ) {
			return;
		}

		node.scrollTop = node.scrollHeight;
		setStickToBottom( true );
	};

	const errorText = stream.error ?? history.error;

	return (
		<div
			className="wpclaw-chat-window"
			style={ windowStyle }
			data-wpclaw-theme={ uiConfig.theme }
		>
			<header className="wpclaw-chat-header">
				<strong>WPClaw</strong>
				<div className="wpclaw-chat-actions">
					<button type="button" onClick={ () => void clearHistory() }>
						Clear history
					</button>
					<CancelButton
						busy={ stream.isSending }
						onCancel={ stream.cancel }
					/>
				</div>
			</header>
			<div className="wpclaw-message-list-wrap">
				<MessageList
					entries={ visibleTimeline }
					listRef={ messagesRef }
					onScroll={ onScroll }
					hasMore={ history.hasMore }
					loadingOlder={ history.loadingOlder }
					onLoadOlder={ () => void loadOlder() }
				/>
				{ ! stickToBottom && visibleTimeline.length > 0 ? (
					<button
						type="button"
						className="wpclaw-jump-latest"
						onClick={ jumpToLatest }
					>
						Jump to latest
					</button>
				) : null }
			</div>
			<Composer
				disabled={ stream.isSending || history.loading }
				onSubmit={ send }
				placeholder={ placeholder }
			/>
			{ errorText ? <p className="wpclaw-error">{ errorText }</p> : null }
		</div>
	);
}

export default ChatWindow;
