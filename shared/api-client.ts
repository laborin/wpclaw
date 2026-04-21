import type {
	ApiError,
	CancelResponse,
	ChatResponse,
	ClearHistoryResponse,
	HistoryResponse,
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

export class WpClawClient {
	private readonly baseUrl: string;

	private readonly nonce: string | null;

	public constructor( config: ApiClientConfig = {} ) {
		this.baseUrl = config.baseUrl ?? '/wp-json/wpclaw/v1';
		this.nonce = config.nonce ?? this.readWpNonce();
	}

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

	public async cancelCurrentRun(): Promise< CancelResponse > {
		return this.request< CancelResponse >( `/chat/cancel`, {
			method: 'POST',
			body: {},
		} );
	}

	public async sendChat( payload: {
		message: string;
		model?: string;
	} ): Promise< ChatResponse > {
		return this.request< ChatResponse >( `/chat`, {
			method: 'POST',
			body: payload,
		} );
	}

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

	private readWpNonce(): string | null {
		const settings = (
			window as Window & { wpApiSettings?: { nonce?: string } }
		 ).wpApiSettings;

		return settings?.nonce ?? null;
	}

	private hasOkFlag( payload: unknown ): boolean {
		return (
			payload !== null &&
			typeof payload === 'object' &&
			'ok' in payload &&
			typeof ( payload as { ok?: unknown } ).ok === 'boolean'
		);
	}

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
