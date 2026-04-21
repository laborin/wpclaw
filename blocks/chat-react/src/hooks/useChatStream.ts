import { useCallback, useState } from '@wordpress/element';
import { WpClawClient } from '../../../../shared/api-client';

type SendPayload = {
	message: string;
	model: string;
};

type UseChatStreamResult = {
	isSending: boolean;
	error: string | null;
	setError: ( value: string | null ) => void;
	cancel: () => Promise< void >;
	send: ( payload: SendPayload ) => Promise< boolean >;
};

export function useChatStream( client: WpClawClient ): UseChatStreamResult {
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
