export type ChatTheme = 'auto' | 'light' | 'dark';

export type ChatUiConfig = {
	theme: ChatTheme;
	fontFamily: string;
	fontSize: string;
	lineHeight: string;
	borderRadius: string;
	bubbleRadius: string;
	outerMargin: string;
	chatPadding: string;
	messageGap: string;
	chatBackgroundColor: string;
	borderColor: string;
	headerBackgroundColor: string;
	headerTextColor: string;
	userBubbleColor: string;
	userTextColor: string;
	assistantBubbleColor: string;
	assistantTextColor: string;
	toolBubbleColor: string;
	toolTextColor: string;
	composerBackgroundColor: string;
	inputBackgroundColor: string;
	inputTextColor: string;
	buttonBackgroundColor: string;
	buttonTextColor: string;
	accentColor: string;
};

export const DEFAULT_CHAT_UI_CONFIG: ChatUiConfig = {
	theme: 'auto',
	fontFamily: '"Manrope", "Avenir Next", "Segoe UI", sans-serif',
	fontSize: '16px',
	lineHeight: '1.45',
	borderRadius: '18px',
	bubbleRadius: '14px',
	outerMargin: '0px',
	chatPadding: '14px',
	messageGap: '12px',
	chatBackgroundColor: '#f8fafc',
	borderColor: '#cbd5e1',
	headerBackgroundColor: '#ffffff',
	headerTextColor: '#0f172a',
	userBubbleColor: '#dbeafe',
	userTextColor: '#1e293b',
	assistantBubbleColor: '#ffffff',
	assistantTextColor: '#0f172a',
	toolBubbleColor: '#f1f5f9',
	toolTextColor: '#0f172a',
	composerBackgroundColor: '#ffffff',
	inputBackgroundColor: '#ffffff',
	inputTextColor: '#0f172a',
	buttonBackgroundColor: '#0f172a',
	buttonTextColor: '#ffffff',
	accentColor: '#2563eb',
};

function isRecord( value: unknown ): value is Record< string, unknown > {
	return (
		value !== null && typeof value === 'object' && ! Array.isArray( value )
	);
}

function readTheme( value: unknown, fallback: ChatTheme ): ChatTheme {
	return value === 'light' || value === 'dark' || value === 'auto'
		? value
		: fallback;
}

function readString( value: unknown, fallback: string ): string {
	return typeof value === 'string' && value.trim() !== ''
		? value.trim()
		: fallback;
}

export function normalizeUiConfig(
	value: Partial< ChatUiConfig > | null | undefined
): ChatUiConfig {
	const input = value ?? {};

	return {
		theme: readTheme( input.theme, DEFAULT_CHAT_UI_CONFIG.theme ),
		fontFamily: readString(
			input.fontFamily,
			DEFAULT_CHAT_UI_CONFIG.fontFamily
		),
		fontSize: readString( input.fontSize, DEFAULT_CHAT_UI_CONFIG.fontSize ),
		lineHeight: readString(
			input.lineHeight,
			DEFAULT_CHAT_UI_CONFIG.lineHeight
		),
		borderRadius: readString(
			input.borderRadius,
			DEFAULT_CHAT_UI_CONFIG.borderRadius
		),
		bubbleRadius: readString(
			input.bubbleRadius,
			DEFAULT_CHAT_UI_CONFIG.bubbleRadius
		),
		outerMargin: readString(
			input.outerMargin,
			DEFAULT_CHAT_UI_CONFIG.outerMargin
		),
		chatPadding: readString(
			input.chatPadding,
			DEFAULT_CHAT_UI_CONFIG.chatPadding
		),
		messageGap: readString(
			input.messageGap,
			DEFAULT_CHAT_UI_CONFIG.messageGap
		),
		chatBackgroundColor: readString(
			input.chatBackgroundColor,
			DEFAULT_CHAT_UI_CONFIG.chatBackgroundColor
		),
		borderColor: readString(
			input.borderColor,
			DEFAULT_CHAT_UI_CONFIG.borderColor
		),
		headerBackgroundColor: readString(
			input.headerBackgroundColor,
			DEFAULT_CHAT_UI_CONFIG.headerBackgroundColor
		),
		headerTextColor: readString(
			input.headerTextColor,
			DEFAULT_CHAT_UI_CONFIG.headerTextColor
		),
		userBubbleColor: readString(
			input.userBubbleColor,
			DEFAULT_CHAT_UI_CONFIG.userBubbleColor
		),
		userTextColor: readString(
			input.userTextColor,
			DEFAULT_CHAT_UI_CONFIG.userTextColor
		),
		assistantBubbleColor: readString(
			input.assistantBubbleColor,
			DEFAULT_CHAT_UI_CONFIG.assistantBubbleColor
		),
		assistantTextColor: readString(
			input.assistantTextColor,
			DEFAULT_CHAT_UI_CONFIG.assistantTextColor
		),
		toolBubbleColor: readString(
			input.toolBubbleColor,
			DEFAULT_CHAT_UI_CONFIG.toolBubbleColor
		),
		toolTextColor: readString(
			input.toolTextColor,
			DEFAULT_CHAT_UI_CONFIG.toolTextColor
		),
		composerBackgroundColor: readString(
			input.composerBackgroundColor,
			DEFAULT_CHAT_UI_CONFIG.composerBackgroundColor
		),
		inputBackgroundColor: readString(
			input.inputBackgroundColor,
			DEFAULT_CHAT_UI_CONFIG.inputBackgroundColor
		),
		inputTextColor: readString(
			input.inputTextColor,
			DEFAULT_CHAT_UI_CONFIG.inputTextColor
		),
		buttonBackgroundColor: readString(
			input.buttonBackgroundColor,
			DEFAULT_CHAT_UI_CONFIG.buttonBackgroundColor
		),
		buttonTextColor: readString(
			input.buttonTextColor,
			DEFAULT_CHAT_UI_CONFIG.buttonTextColor
		),
		accentColor: readString(
			input.accentColor,
			DEFAULT_CHAT_UI_CONFIG.accentColor
		),
	};
}

export function parseUiConfig( raw: string | null | undefined ): ChatUiConfig {
	if ( typeof raw !== 'string' || raw.trim() === '' ) {
		return DEFAULT_CHAT_UI_CONFIG;
	}

	try {
		const parsed = JSON.parse( raw );
		if ( ! isRecord( parsed ) ) {
			return DEFAULT_CHAT_UI_CONFIG;
		}

		return normalizeUiConfig( parsed as Partial< ChatUiConfig > );
	} catch {
		return DEFAULT_CHAT_UI_CONFIG;
	}
}

export function uiConfigToCssVars(
	config: ChatUiConfig
): Record< string, string > {
	return {
		'--wpclaw-font-family': config.fontFamily,
		'--wpclaw-font-size': config.fontSize,
		'--wpclaw-line-height': config.lineHeight,
		'--wpclaw-border-radius': config.borderRadius,
		'--wpclaw-bubble-radius': config.bubbleRadius,
		'--wpclaw-outer-margin': config.outerMargin,
		'--wpclaw-chat-padding': config.chatPadding,
		'--wpclaw-message-gap': config.messageGap,
		'--wpclaw-chat-bg': config.chatBackgroundColor,
		'--wpclaw-border-color': config.borderColor,
		'--wpclaw-header-bg': config.headerBackgroundColor,
		'--wpclaw-header-text': config.headerTextColor,
		'--wpclaw-user-bg': config.userBubbleColor,
		'--wpclaw-user-text': config.userTextColor,
		'--wpclaw-assistant-bg': config.assistantBubbleColor,
		'--wpclaw-assistant-text': config.assistantTextColor,
		'--wpclaw-tool-bg': config.toolBubbleColor,
		'--wpclaw-tool-text': config.toolTextColor,
		'--wpclaw-composer-bg': config.composerBackgroundColor,
		'--wpclaw-input-bg': config.inputBackgroundColor,
		'--wpclaw-input-text': config.inputTextColor,
		'--wpclaw-button-bg': config.buttonBackgroundColor,
		'--wpclaw-button-text': config.buttonTextColor,
		'--wpclaw-accent': config.accentColor,
	};
}
