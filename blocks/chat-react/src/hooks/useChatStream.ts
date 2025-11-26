import { useCallback, useState } from '@wordpress/element';
import { WpNativeAgentClient } from '../../../../shared/api-client';
import type { SystemPromptMode } from '../../../../shared/types';

type SendPayload = {
	message: string;
	model: string;
	enabledTools: string[];
	systemPromptOverride: string;
	systemPromptMode: SystemPromptMode;
};

type UseChatStreamResult = {
	isSending: boolean;
	error: string | null;
	setError: ( value: string | null ) => void;
	cancel: () => Promise< void >;
	send: ( payload: SendPayload ) => Promise< boolean >;
};

export function useChatStream(
	client: WpNativeAgentClient
): UseChatStreamResult {
	const [ isSending, setIsSending ] = useState( false );
	const [ error, setErrorState ] = useState< string | null >( null );

	const setError = useCallback( ( value: string | null ) => {
		setErrorState( value );
	}, [] );

	const cancel = useCallback( async () => {
		await client.cancelCurrentRun();
		setIsSending( false );
	}, [ client ] );

	const send = useCallback(
		async ( payload: SendPayload ): Promise< boolean > => {
			setIsSending( true );
			setErrorState( null );

			const response = await client.sendChat( {
				message: payload.message,
				model: payload.model,
				enabled_tools: payload.enabledTools,
				system_prompt_override: payload.systemPromptOverride,
				system_prompt_mode: payload.systemPromptMode,
			} );

			setIsSending( false );

			if ( ! response.ok ) {
				setErrorState(
					response.error?.message ?? 'Chat request failed.'
				);
				return false;
			}

			return true;
		},
		[ client ]
	);

	return {
		isSending,
		error,
		setError,
		cancel,
		send,
	};
}
