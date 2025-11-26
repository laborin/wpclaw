import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { DEFAULT_CHAT_UI_CONFIG } from '../../../shared/ui-config';

type ChatInteractivityAttributes = {
	placeholder: string;
	maxHeight: string;
	hideIfDisallowed: boolean;
	theme: 'auto' | 'light' | 'dark';
	enabledTools: string[];
	systemPromptOverride: string;
	systemPromptOverridesGlobal: boolean;
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

type StyleKey =
	| 'fontFamily'
	| 'fontSize'
	| 'lineHeight'
	| 'borderRadius'
	| 'bubbleRadius'
	| 'outerMargin'
	| 'chatPadding'
	| 'messageGap'
	| 'chatBackgroundColor'
	| 'borderColor'
	| 'headerBackgroundColor'
	| 'headerTextColor'
	| 'userBubbleColor'
	| 'userTextColor'
	| 'assistantBubbleColor'
	| 'assistantTextColor'
	| 'toolBubbleColor'
	| 'toolTextColor'
	| 'composerBackgroundColor'
	| 'inputBackgroundColor'
	| 'inputTextColor'
	| 'buttonBackgroundColor'
	| 'buttonTextColor'
	| 'accentColor';

type EditProps = {
	attributes: ChatInteractivityAttributes;
	setAttributes: ( next: Partial< ChatInteractivityAttributes > ) => void;
};

const TOOL_OPTIONS: Array< { label: string; value: string } > = [
	{ label: 'search_posts', value: 'search_posts' },
	{ label: 'get_post', value: 'get_post' },
	{ label: 'list_recent_comments', value: 'list_recent_comments' },
	{ label: 'get_site_stats', value: 'get_site_stats' },
	{ label: 'search_media', value: 'search_media' },
	{ label: 'get_current_user', value: 'get_current_user' },
	{ label: 'create_draft_post', value: 'create_draft_post' },
	{ label: 'update_post', value: 'update_post' },
	{ label: 'delete_post', value: 'delete_post' },
];

function normalizeEnabledToolsValue( value: string | string[] ): string[] {
	if ( Array.isArray( value ) ) {
		return value;
	}

	if ( typeof value === 'string' && value.length > 0 ) {
		return [ value ];
	}

	return [];
}

function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps();

	const setStyleValue = ( key: StyleKey, value: string ) => {
		setAttributes( {
			[ key ]: value,
		} as Partial< ChatInteractivityAttributes > );
	};

	const resetStyleDefaults = () => {
		setAttributes( {
			fontFamily: DEFAULT_CHAT_UI_CONFIG.fontFamily,
			fontSize: DEFAULT_CHAT_UI_CONFIG.fontSize,
			lineHeight: DEFAULT_CHAT_UI_CONFIG.lineHeight,
			borderRadius: DEFAULT_CHAT_UI_CONFIG.borderRadius,
			bubbleRadius: DEFAULT_CHAT_UI_CONFIG.bubbleRadius,
			outerMargin: DEFAULT_CHAT_UI_CONFIG.outerMargin,
			chatPadding: DEFAULT_CHAT_UI_CONFIG.chatPadding,
			messageGap: DEFAULT_CHAT_UI_CONFIG.messageGap,
			chatBackgroundColor: DEFAULT_CHAT_UI_CONFIG.chatBackgroundColor,
			borderColor: DEFAULT_CHAT_UI_CONFIG.borderColor,
			headerBackgroundColor: DEFAULT_CHAT_UI_CONFIG.headerBackgroundColor,
			headerTextColor: DEFAULT_CHAT_UI_CONFIG.headerTextColor,
			userBubbleColor: DEFAULT_CHAT_UI_CONFIG.userBubbleColor,
			userTextColor: DEFAULT_CHAT_UI_CONFIG.userTextColor,
			assistantBubbleColor: DEFAULT_CHAT_UI_CONFIG.assistantBubbleColor,
			assistantTextColor: DEFAULT_CHAT_UI_CONFIG.assistantTextColor,
			toolBubbleColor: DEFAULT_CHAT_UI_CONFIG.toolBubbleColor,
			toolTextColor: DEFAULT_CHAT_UI_CONFIG.toolTextColor,
			composerBackgroundColor:
				DEFAULT_CHAT_UI_CONFIG.composerBackgroundColor,
			inputBackgroundColor: DEFAULT_CHAT_UI_CONFIG.inputBackgroundColor,
			inputTextColor: DEFAULT_CHAT_UI_CONFIG.inputTextColor,
			buttonBackgroundColor: DEFAULT_CHAT_UI_CONFIG.buttonBackgroundColor,
			buttonTextColor: DEFAULT_CHAT_UI_CONFIG.buttonTextColor,
			accentColor: DEFAULT_CHAT_UI_CONFIG.accentColor,
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title="Chat UI" initialOpen={ true }>
					<TextControl
						label="Placeholder"
						value={ attributes.placeholder }
						onChange={ ( value ) =>
							setAttributes( { placeholder: value } )
						}
					/>
					<TextControl
						label="Max height"
						value={ attributes.maxHeight }
						onChange={ ( value ) =>
							setAttributes( { maxHeight: value } )
						}
					/>
					<SelectControl
						label="Theme"
						value={ attributes.theme }
						options={ [
							{ label: 'Auto', value: 'auto' },
							{ label: 'Light', value: 'light' },
							{ label: 'Dark', value: 'dark' },
						] }
						onChange={ ( value ) =>
							setAttributes( {
								theme: value as ChatInteractivityAttributes[ 'theme' ],
							} )
						}
					/>
					<ToggleControl
						label="Hide block when user cannot chat"
						checked={ attributes.hideIfDisallowed }
						onChange={ ( value ) =>
							setAttributes( { hideIfDisallowed: value } )
						}
					/>
				</PanelBody>
				<PanelBody title="Typography & Spacing" initialOpen={ false }>
					<TextControl
						label="Font family"
						value={ attributes.fontFamily }
						onChange={ ( value ) =>
							setStyleValue( 'fontFamily', value )
						}
					/>
					<TextControl
						label="Font size"
						value={ attributes.fontSize }
						onChange={ ( value ) =>
							setStyleValue( 'fontSize', value )
						}
					/>
					<TextControl
						label="Line height"
						value={ attributes.lineHeight }
						onChange={ ( value ) =>
							setStyleValue( 'lineHeight', value )
						}
					/>
					<TextControl
						label="Container radius"
						value={ attributes.borderRadius }
						onChange={ ( value ) =>
							setStyleValue( 'borderRadius', value )
						}
					/>
					<TextControl
						label="Bubble radius"
						value={ attributes.bubbleRadius }
						onChange={ ( value ) =>
							setStyleValue( 'bubbleRadius', value )
						}
					/>
					<TextControl
						label="Outer margin"
						value={ attributes.outerMargin }
						onChange={ ( value ) =>
							setStyleValue( 'outerMargin', value )
						}
					/>
					<TextControl
						label="Chat padding"
						value={ attributes.chatPadding }
						onChange={ ( value ) =>
							setStyleValue( 'chatPadding', value )
						}
					/>
					<TextControl
						label="Message gap"
						value={ attributes.messageGap }
						onChange={ ( value ) =>
							setStyleValue( 'messageGap', value )
						}
					/>
				</PanelBody>
				<PanelBody title="Colors" initialOpen={ false }>
					<TextControl
						label="Chat background"
						value={ attributes.chatBackgroundColor }
						onChange={ ( value ) =>
							setStyleValue( 'chatBackgroundColor', value )
						}
					/>
					<TextControl
						label="Border color"
						value={ attributes.borderColor }
						onChange={ ( value ) =>
							setStyleValue( 'borderColor', value )
						}
					/>
					<TextControl
						label="Header background"
						value={ attributes.headerBackgroundColor }
						onChange={ ( value ) =>
							setStyleValue( 'headerBackgroundColor', value )
						}
					/>
					<TextControl
						label="Header text"
						value={ attributes.headerTextColor }
						onChange={ ( value ) =>
							setStyleValue( 'headerTextColor', value )
						}
					/>
					<TextControl
						label="User bubble"
						value={ attributes.userBubbleColor }
						onChange={ ( value ) =>
							setStyleValue( 'userBubbleColor', value )
						}
					/>
					<TextControl
						label="User text"
						value={ attributes.userTextColor }
						onChange={ ( value ) =>
							setStyleValue( 'userTextColor', value )
						}
					/>
					<TextControl
						label="Assistant bubble"
						value={ attributes.assistantBubbleColor }
						onChange={ ( value ) =>
							setStyleValue( 'assistantBubbleColor', value )
						}
					/>
					<TextControl
						label="Assistant text"
						value={ attributes.assistantTextColor }
						onChange={ ( value ) =>
							setStyleValue( 'assistantTextColor', value )
						}
					/>
					<TextControl
						label="Tool bubble"
						value={ attributes.toolBubbleColor }
						onChange={ ( value ) =>
							setStyleValue( 'toolBubbleColor', value )
						}
					/>
					<TextControl
						label="Tool text"
						value={ attributes.toolTextColor }
						onChange={ ( value ) =>
							setStyleValue( 'toolTextColor', value )
						}
					/>
					<TextControl
						label="Composer background"
						value={ attributes.composerBackgroundColor }
						onChange={ ( value ) =>
							setStyleValue( 'composerBackgroundColor', value )
						}
					/>
					<TextControl
						label="Input background"
						value={ attributes.inputBackgroundColor }
						onChange={ ( value ) =>
							setStyleValue( 'inputBackgroundColor', value )
						}
					/>
					<TextControl
						label="Input text"
						value={ attributes.inputTextColor }
						onChange={ ( value ) =>
							setStyleValue( 'inputTextColor', value )
						}
					/>
					<TextControl
						label="Button background"
						value={ attributes.buttonBackgroundColor }
						onChange={ ( value ) =>
							setStyleValue( 'buttonBackgroundColor', value )
						}
					/>
					<TextControl
						label="Button text"
						value={ attributes.buttonTextColor }
						onChange={ ( value ) =>
							setStyleValue( 'buttonTextColor', value )
						}
					/>
					<TextControl
						label="Accent color"
						value={ attributes.accentColor }
						onChange={ ( value ) =>
							setStyleValue( 'accentColor', value )
						}
					/>
					<Button variant="secondary" onClick={ resetStyleDefaults }>
						Reset style defaults
					</Button>
				</PanelBody>
				<PanelBody title="Agent" initialOpen={ false }>
					<TextareaControl
						label="Block system prompt"
						rows={ 8 }
						value={ attributes.systemPromptOverride }
						onChange={ ( value ) =>
							setAttributes( { systemPromptOverride: value } )
						}
					/>
					<ToggleControl
						label="Override global system prompt"
						checked={ attributes.systemPromptOverridesGlobal }
						onChange={ ( value ) =>
							setAttributes( {
								systemPromptOverridesGlobal: value,
							} )
						}
					/>
					<SelectControl
						multiple
						label="Enabled tools"
						value={ attributes.enabledTools }
						options={ TOOL_OPTIONS }
						onChange={ ( value ) => {
							const next = normalizeEnabledToolsValue( value );

							setAttributes( { enabledTools: next } );
						} }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="wpna-chat-placeholder">
					WP Native Agent Interactivity Chat
				</div>
			</div>
		</>
	);
}

export default Edit;
