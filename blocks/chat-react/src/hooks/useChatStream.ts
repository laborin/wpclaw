import { useCallback, useRef, useState } from '@wordpress/element';
import { WpClawClient } from '../../../../shared/api-client';
import type { HistoryTimelineEntry } from '../../../../shared/history-presenter';
import type { StreamEvent } from '../../../../shared/types';

type SendPayload = {
	message: string;
	model: string;
};

type UseChatStreamResult = {
	isSending: boolean;
	error: string | null;
	liveTimeline: HistoryTimelineEntry[];
	setError: ( value: string | null ) => void;
	clearLive: () => void;
	cancel: () => Promise< void >;
	send: ( payload: SendPayload ) => Promise< boolean >;
};

function isRecord( value: unknown ): value is Record< string, unknown > {
	return (
		value !== null && typeof value === 'object' && ! Array.isArray( value )
	);
}

/**
 * Owns send/cancel state for the composer.
 *
 * It exposes a temporary timeline so the UI can show streamed text before the
 * final messages are reloaded from storage.
 *
 * @param client REST client for the current block instance.
 */
export function useChatStream( client: WpClawClient ): UseChatStreamResult {
	const [ isSending, setIsSending ] = useState( false );
	const [ error, setErrorState ] = useState< string | null >( null );
	const [ liveTimeline, setLiveTimeline ] = useState<
		HistoryTimelineEntry[]
	>( [] );
	const abortControllerRef = useRef< AbortController | null >( null );

	const setError = useCallback( ( value: string | null ) => {
		setErrorState( value );
	}, [] );

	const clearLive = useCallback( () => {
		setLiveTimeline( [] );
	}, [] );

	const appendAssistantDelta = useCallback( ( text: string ) => {
		if ( text === '' ) {
			return;
		}

		setLiveTimeline( ( current ) => {
			const last = current[ current.length - 1 ];
			if (
				last?.type === 'message' &&
				last.message.role === 'assistant'
			) {
				return [
					...current.slice( 0, -1 ),
					{
						type: 'message',
						message: {
							...last.message,
							content: `${ last.message.content }${ text }`,
						},
					},
				];
			}

			return [
				...current,
				{
					type: 'message',
					message: {
						role: 'assistant',
						content: text,
					},
				},
			];
		} );
	}, [] );

	const appendToolStart = useCallback( ( event: StreamEvent ) => {
		const callId =
			typeof event.call_id === 'string' ? event.call_id : 'tool_call';
		const toolName =
			typeof event.tool_name === 'string' ? event.tool_name : 'tool';
		const args = isRecord( event.arguments ) ? event.arguments : {};

		setLiveTimeline( ( current ) => [
			...current,
			{
				type: 'tool',
				run: {
					id: callId,
					name: toolName,
					args,
				},
			},
		] );
	}, [] );

	const applyToolResult = useCallback( ( event: StreamEvent ) => {
		const callId = typeof event.call_id === 'string' ? event.call_id : '';
		if ( callId === '' ) {
			return;
		}

		setLiveTimeline( ( current ) =>
			current.map( ( entry ) => {
				if ( entry.type !== 'tool' || entry.run.id !== callId ) {
					return entry;
				}

				return {
					type: 'tool',
					run: {
						...entry.run,
						ok: Boolean( event.ok ),
						payload: event.payload,
						error:
							typeof event.error === 'string'
								? event.error
								: undefined,
					},
				};
			} )
		);
	}, [] );

	const handleStreamEvent = useCallback(
		( event: StreamEvent ) => {
			if ( event.type === 'assistant_delta' ) {
				appendAssistantDelta(
					typeof event.text === 'string' ? event.text : ''
				);
				return;
			}

			if ( event.type === 'tool_call_start' ) {
				appendToolStart( event );
				return;
			}

			if ( event.type === 'tool_call_result' ) {
				applyToolResult( event );
				return;
			}

			if ( event.type === 'error' ) {
				setErrorState(
					typeof event.message === 'string'
						? event.message
						: 'Chat request failed.'
				);
			}
		},
		[ appendAssistantDelta, appendToolStart, applyToolResult ]
	);

	const cancel = useCallback( async () => {
		await client.cancelCurrentRun();
		abortControllerRef.current?.abort();
		abortControllerRef.current = null;
		setIsSending( false );
	}, [ client ] );

	const send = useCallback(
		async ( payload: SendPayload ): Promise< boolean > => {
			const abortController = new AbortController();
			abortControllerRef.current = abortController;
			setIsSending( true );
			setErrorState( null );
			setLiveTimeline( [
				{
					type: 'message',
					message: {
						role: 'user',
						content: payload.message,
					},
				},
			] );

			const response = await client.sendChatStream(
				{
					message: payload.message,
					model: payload.model,
				},
				handleStreamEvent,
				abortController.signal
			);

			abortControllerRef.current = null;
			setIsSending( false );

			if ( ! response.ok ) {
				if ( response.error?.code !== 'request_cancelled' ) {
					setErrorState(
						response.error?.message ?? 'Chat request failed.'
					);
				}
				return false;
			}

			return true;
		},
		[ client, handleStreamEvent ]
	);

	return {
		isSending,
		error,
		liveTimeline,
		setError,
		clearLive,
		cancel,
		send,
	};
}
