import type { ChatMessage, ToolRun } from './types';

export type HistoryTimelineEntry =
	| {
			type: 'message';
			message: ChatMessage;
	  }
	| {
			type: 'tool';
			run: ToolRun;
	  };

type HistoryPresentation = {
	messages: ChatMessage[];
	toolRuns: ToolRun[];
	timeline: HistoryTimelineEntry[];
};

type ParsedToolContent = {
	ok: boolean;
	payload: unknown;
	error?: string;
};

function isRecord( value: unknown ): value is Record< string, unknown > {
	return (
		value !== null && typeof value === 'object' && ! Array.isArray( value )
	);
}

function normalizeArgs( value: unknown ): Record< string, unknown > {
	if ( isRecord( value ) ) {
		return value;
	}

	return {};
}

function parseToolContent( content: string ): ParsedToolContent {
	const raw = content.trim();
	if ( raw === '' ) {
		return {
			ok: true,
			payload: null,
		};
	}

	let parsed: unknown;
	try {
		parsed = JSON.parse( raw );
	} catch {
		return {
			ok: true,
			payload: raw,
		};
	}

	if (
		isRecord( parsed ) &&
		typeof parsed.error === 'string' &&
		parsed.error !== ''
	) {
		return {
			ok: false,
			payload: null,
			error: parsed.error,
		};
	}

	return {
		ok: true,
		payload: parsed,
	};
}

export function presentHistory( history: ChatMessage[] ): HistoryPresentation {
	const argsByCallId: Record< string, Record< string, unknown > > = {};
	const namesByCallId: Record< string, string > = {};

	for ( const message of history ) {
		if (
			message.role !== 'assistant' ||
			! Array.isArray( message.tool_calls )
		) {
			continue;
		}

		for ( const call of message.tool_calls ) {
			const callId = typeof call?.id === 'string' ? call.id : '';
			if ( callId === '' ) {
				continue;
			}

			argsByCallId[ callId ] = normalizeArgs( call.arguments );
			if ( typeof call.name === 'string' && call.name !== '' ) {
				namesByCallId[ callId ] = call.name;
			}
		}
	}

	const messages: ChatMessage[] = [];
	const toolRuns: ToolRun[] = [];
	const timeline: HistoryTimelineEntry[] = [];

	for ( const [ index, message ] of history.entries() ) {
		if ( message.role === 'tool' ) {
			const callId =
				typeof message.tool_call_id === 'string' &&
				message.tool_call_id !== ''
					? message.tool_call_id
					: `history_tool_${ index }`;

			const parsedContent = parseToolContent( message.content );
			const toolName =
				typeof message.tool_name === 'string' &&
				message.tool_name !== ''
					? message.tool_name
					: namesByCallId[ callId ] ?? 'tool';

			toolRuns.push( {
				id: callId,
				name: toolName,
				args: argsByCallId[ callId ] ?? {},
				ok: parsedContent.ok,
				payload: parsedContent.payload,
				error: parsedContent.error,
			} );
			timeline.push( {
				type: 'tool',
				run: toolRuns[ toolRuns.length - 1 ],
			} );
			continue;
		}

		if ( message.role === 'system' ) {
			continue;
		}

		if ( message.role === 'assistant' && message.content.trim() === '' ) {
			continue;
		}

		messages.push( message );
		timeline.push( {
			type: 'message',
			message,
		} );
	}

	return {
		messages,
		toolRuns,
		timeline,
	};
}
