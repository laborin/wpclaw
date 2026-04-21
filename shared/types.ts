export type ChatRole = 'user' | 'assistant' | 'tool' | 'system';

export type ApiError = {
	code: string;
	message: string;
	status: number;
};

export type ToolCall = {
	id: string;
	name: string;
	arguments: Record< string, unknown >;
};

export type ToolRun = {
	id: string;
	name: string;
	args: Record< string, unknown >;
	ok?: boolean;
	payload?: unknown;
	error?: string;
};

export type ChatMessage = {
	id?: number;
	role: ChatRole;
	content: string;
	tool_calls?: ToolCall[] | null;
	tool_call_id?: string | null;
	tool_name?: string | null;
	created_at?: string;
};

export type SessionReadyEvent = {
	type: 'session_ready';
	session_id: number;
	message_id: number;
};

export type AssistantDeltaEvent = {
	type: 'assistant_delta';
	text: string;
};

export type ToolCallStartEvent = {
	type: 'tool_call_start';
	call_id: string;
	tool_name: string;
	arguments: Record< string, unknown >;
};

export type ToolCallResultEvent = {
	type: 'tool_call_result';
	call_id: string;
	ok: boolean;
	payload?: unknown;
	error?: string;
};

export type IterationEndEvent = {
	type: 'iteration_end';
	iteration: number;
	stop_reason: string;
};

export type DoneEvent = {
	type: 'done';
	stop_reason: string;
	total_iterations: number;
	final_message_id?: number;
};

export type ErrorEvent = {
	type: 'error';
	code: string;
	message: string;
};

export type StreamEvent =
	| SessionReadyEvent
	| AssistantDeltaEvent
	| ToolCallStartEvent
	| ToolCallResultEvent
	| IterationEndEvent
	| DoneEvent
	| ErrorEvent
	| Record< string, unknown >;

export type ChatResponse = {
	ok: boolean;
	session_id?: number;
	events?: StreamEvent[];
	error?: ApiError;
};

export type HistoryResponse = {
	ok: boolean;
	session_id: number | null;
	messages: ChatMessage[];
	total_messages?: number;
	limit?: number;
	offset?: number;
	next_offset?: number | null;
	has_more?: boolean;
	order?: 'asc' | 'desc';
	error?: ApiError;
};

export type ClearHistoryResponse = {
	ok: boolean;
	deleted_messages?: number;
	deleted_sessions?: number;
	error?: ApiError;
};

export type CancelResponse = {
	ok: boolean;
	cancelled?: boolean;
	error?: ApiError;
};
