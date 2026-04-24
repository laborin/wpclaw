import type {
	ApiError,
	CancelResponse,
	ChatResponse,
	ClearHistoryResponse,
	HistoryResponse,
	StreamEvent,
} from './types';

export type ApiClientConfig = {
	baseUrl?: string;
	nonce?: string;
};

export type HistoryQuery = {
	limit?: number;
	offset?: number;
	order?: 'asc' | 'desc';
};

/**
 * REST client shared by both block frontends.
 *
 * It keep WordPress nonce handling and error normalization in one place, so UI
 * code can read `ok` and `error` without try/catch in every call.
 */
export class WpClawClient {
	private readonly baseUrl: string;

	private readonly nonce: string | null;

	public constructor( config: ApiClientConfig = {} ) {
		this.baseUrl = config.baseUrl ?? '/wp-json/wpclaw/v1';
		this.nonce = config.nonce ?? this.readWpNonce();
	}

	/**
	 * Loads one history page for the current user session.
	 *
	 * The server can return rows in ascending or descending order; presenters
	 * decide how to turn those rows into the visible timeline.
	 *
	 * @param query Pagination and order options.
	 */
	public async getHistory(
		query: HistoryQuery = {}
	): Promise< HistoryResponse > {
		const params = new URLSearchParams();
		params.set( 'limit', String( query.limit ?? 200 ) );

		if ( typeof query.offset === 'number' ) {
			params.set( 'offset', String( query.offset ) );
		}

		if ( query.order === 'desc' || query.order === 'asc' ) {
			params.set( 'order', query.order );
		}

		return this.request< HistoryResponse >(
			`/history?${ params.toString() }`,
			{
				method: 'GET',
			}
		);
	}

	/**
	 * Deletes the current user chat history.
	 */
	public async clearHistory(): Promise< {
		ok: boolean;
		deleted_messages?: number;
		deleted_sessions?: number;
		error?: ApiError;
	} > {
		return this.request< ClearHistoryResponse >( `/history`, {
			method: 'DELETE',
		} );
	}

	/**
	 * Requests cancellation for the active run in the current session.
	 */
	public async cancelCurrentRun(): Promise< CancelResponse > {
		return this.request< CancelResponse >( `/chat/cancel`, {
			method: 'POST',
			body: {},
		} );
	}

	/**
	 * Sends one user turn.
	 *
	 * Prompt and tool permissions are resolved in PHP from plugin settings, not
	 * from client payload.
	 *
	 * @param payload         Request body for the chat turn.
	 * @param payload.message User message to send.
	 * @param payload.model   Provider model selected by the block renderer.
	 */
	public async sendChat( payload: {
		message: string;
		model?: string;
	} ): Promise< ChatResponse > {
		return this.request< ChatResponse >( `/chat`, {
			method: 'POST',
			body: payload,
		} );
	}

	/**
	 * Sends one user turn and reads newline JSON stream events.
	 *
	 * The callback is called as soon as each event line is decoded.
	 *
	 * @param payload         Request body for the chat turn.
	 * @param payload.message User message to send.
	 * @param payload.model   Provider model selected by the block renderer.
	 * @param onEvent         Event handler for decoded stream events.
	 * @param signal          Optional abort signal for the active fetch.
	 */
	public async sendChatStream(
		payload: {
			message: string;
			model?: string;
		},
		onEvent: ( event: StreamEvent ) => void,
		signal?: AbortSignal
	): Promise< ChatResponse > {
		const headers: Record< string, string > = {
			Accept: 'application/x-ndjson',
			'Content-Type': 'application/json',
		};

		if ( this.nonce ) {
			headers[ 'X-WP-Nonce' ] = this.nonce;
		}

		let response: Response;
		try {
			response = await fetch( `${ this.baseUrl }/chat/stream`, {
				method: 'POST',
				headers,
				credentials: 'same-origin',
				body: JSON.stringify( payload ),
				signal,
			} );
		} catch ( error ) {
			if (
				error instanceof DOMException &&
				error.name === 'AbortError'
			) {
				return this.failure< ChatResponse >(
					'Chat request was cancelled.',
					'request_cancelled',
					0
				);
			}

			return this.failure< ChatResponse >(
				error instanceof Error
					? error.message
					: 'Network request failed.',
				'network_error',
				0
			);
		}

		if ( ! response.ok ) {
			return this.responseFailure< ChatResponse >( response );
		}

		if ( ! response.body ) {
			return this.failure< ChatResponse >(
				'Streaming response is not available.',
				'stream_unavailable',
				response.status
			);
		}

		const reader = response.body.getReader();
		const decoder = new TextDecoder();
		const events: StreamEvent[] = [];
		let buffer = '';
		let sessionId: number | undefined;
		let streamError: ApiError | null = null;

		const readLine = ( line: string ) => {
			const trimmed = line.trim();
			if ( trimmed === '' ) {
				return;
			}

			let event: StreamEvent;
			try {
				event = JSON.parse( trimmed ) as StreamEvent;
			} catch {
				streamError = {
					code: 'invalid_stream_response',
					message: 'Invalid streaming response from server.',
					status: 502,
				};
				return;
			}

			events.push( event );
			if (
				event.type === 'session_ready' &&
				typeof event.session_id === 'number'
			) {
				sessionId = event.session_id;
			}

			if ( event.type === 'error' ) {
				streamError = {
					code:
						typeof event.code === 'string'
							? event.code
							: 'stream_error',
					message:
						typeof event.message === 'string'
							? event.message
							: 'Chat request failed.',
					status: 500,
				};
			}

			onEvent( event );
		};

		try {
			for (;;) {
				const { done, value } = await reader.read();
				if ( done ) {
					break;
				}

				buffer += decoder.decode( value, { stream: true } );
				const lines = buffer.split( '\n' );
				buffer = lines.pop() ?? '';
				lines.forEach( readLine );
			}
		} catch ( error ) {
			if (
				error instanceof DOMException &&
				error.name === 'AbortError'
			) {
				return this.failure< ChatResponse >(
					'Chat request was cancelled.',
					'request_cancelled',
					0
				);
			}

			return this.failure< ChatResponse >(
				error instanceof Error
					? error.message
					: 'Network stream failed.',
				'network_error',
				0
			);
		}

		buffer += decoder.decode();
		readLine( buffer );

		if ( streamError !== null ) {
			return {
				ok: false,
				session_id: sessionId,
				events,
				error: streamError,
			};
		}

		return {
			ok: true,
			session_id: sessionId,
			events,
		};
	}

	/**
	 * Sends a JSON REST request and normalize WordPress or WPClaw errors.
	 *
	 * @param path           REST path relative to WPClaw namespace.
	 * @param options        Request method and optional JSON body.
	 * @param options.method HTTP method used by the request.
	 * @param options.body   JSON body sent for non-GET requests.
	 */
	private async request< T >(
		path: string,
		options: { method: 'GET' | 'POST' | 'DELETE'; body?: unknown }
	): Promise< T > {
		const headers: Record< string, string > = {
			Accept: 'application/json',
		};

		if ( options.method !== 'GET' ) {
			headers[ 'Content-Type' ] = 'application/json';
		}

		if ( this.nonce ) {
			headers[ 'X-WP-Nonce' ] = this.nonce;
		}

		let response: Response;
		try {
			response = await fetch( `${ this.baseUrl }${ path }`, {
				method: options.method,
				headers,
				credentials: 'same-origin',
				body:
					options.body !== undefined
						? JSON.stringify( options.body )
						: undefined,
			} );
		} catch ( error ) {
			return this.failure< T >(
				error instanceof Error
					? error.message
					: 'Network request failed.',
				'network_error',
				0
			);
		}

		let payload: unknown = null;
		try {
			payload = await response.json();
		} catch {
			payload = null;
		}

		const normalizedError = this.readWpRestError( payload );
		if ( normalizedError !== null ) {
			if ( response.ok && this.hasOkFlag( payload ) ) {
				return payload as T;
			}

			return this.failure< T >(
				normalizedError.message,
				normalizedError.code,
				normalizedError.status
			);
		}

		if ( payload !== null && typeof payload === 'object' ) {
			return payload as T;
		}

		if ( ! response.ok ) {
			return this.failure< T >(
				`Request failed with status ${ response.status }.`,
				'request_failed',
				response.status
			);
		}

		return this.failure< T >( 'Invalid response from server.' );
	}

	private async responseFailure< T >( response: Response ): Promise< T > {
		let payload: unknown = null;
		try {
			payload = await response.json();
		} catch {
			payload = null;
		}

		const normalizedError = this.readWpRestError( payload );
		if ( normalizedError !== null ) {
			return this.failure< T >(
				normalizedError.message,
				normalizedError.code,
				normalizedError.status
			);
		}

		return this.failure< T >(
			`Request failed with status ${ response.status }.`,
			'request_failed',
			response.status
		);
	}

	/**
	 * Reads the nonce that WordPress prints for REST authenticated requests.
	 */
	private readWpNonce(): string | null {
		const settings = (
			window as Window & { wpApiSettings?: { nonce?: string } }
		 ).wpApiSettings;

		return settings?.nonce ?? null;
	}

	/**
	 * Detects the WPClaw response envelope before reading `error`.
	 *
	 * @param payload Decoded JSON response.
	 */
	private hasOkFlag( payload: unknown ): boolean {
		return (
			payload !== null &&
			typeof payload === 'object' &&
			'ok' in payload &&
			typeof ( payload as { ok?: unknown } ).ok === 'boolean'
		);
	}

	/**
	 * Converts WP REST native errors and WPClaw envelope errors to one shape.
	 *
	 * @param payload Decoded JSON response.
	 */
	private readWpRestError( payload: unknown ): ApiError | null {
		if ( payload === null || typeof payload !== 'object' ) {
			return null;
		}

		if ( this.hasOkFlag( payload ) ) {
			const response = payload as {
				ok: boolean;
				error?: { code?: string; message?: string; status?: number };
			};

			if ( response.ok ) {
				return null;
			}

			return {
				code: String( response.error?.code ?? 'request_failed' ),
				message: String( response.error?.message ?? 'Request failed.' ),
				status: Number( response.error?.status ?? 400 ),
			};
		}

		if (
			'code' in payload &&
			'message' in payload &&
			typeof ( payload as { code?: unknown } ).code === 'string' &&
			typeof ( payload as { message?: unknown } ).message === 'string'
		) {
			const data = ( payload as { data?: unknown } ).data;
			let status = 400;

			if ( typeof data === 'number' ) {
				status = data;
			} else if (
				data !== null &&
				typeof data === 'object' &&
				'status' in data &&
				typeof ( data as { status?: unknown } ).status === 'number'
			) {
				status = ( data as { status: number } ).status;
			}

			return {
				code: ( payload as { code: string } ).code,
				message: ( payload as { message: string } ).message,
				status,
			};
		}

		return null;
	}

	/**
	 * Creates a typed failure payload for callers.
	 *
	 * @param message User-facing error message.
	 * @param code    Stable error code.
	 * @param status  HTTP status.
	 */
	private failure< T >(
		message: string,
		code = 'request_failed',
		status = 400
	): T {
		return {
			ok: false,
			error: {
				code,
				message,
				status,
			},
		} as T;
	}
}
