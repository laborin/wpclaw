<?php

declare(strict_types=1);

namespace WPNativeAgent\Block;

use WPNativeAgent\Security\RoleGate;

/**
 * Server renderer for the React chat block variant.
 */
final class ReactRenderer
{
    public function __construct(private readonly RoleGate $roleGate)
    {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes = []): string
    {
        $canChat = is_user_logged_in() && $this->roleGate->can_chat_current_user();
        $hideIfDisallowed = (bool) ($attributes['hideIfDisallowed'] ?? false);

        if (! $canChat && $hideIfDisallowed) {
            return '';
        }

        if (! $canChat) {
            return '<div class="wpna-chat-disabled">You are not allowed to use this chat.</div>';
        }

        $placeholder = (string) ($attributes['placeholder'] ?? 'Ask something...');
        $systemPromptOverride = (string) ($attributes['systemPromptOverride'] ?? '');
        $systemPromptMode = (bool) ($attributes['systemPromptOverridesGlobal'] ?? true) ? 'override' : 'append';
        $maxHeight = (string) ($attributes['maxHeight'] ?? '600px');
        $enabledTools = isset($attributes['enabledTools']) && is_array($attributes['enabledTools'])
            ? $attributes['enabledTools']
            : [];
        $uiConfig = [
            'theme' => (string) ($attributes['theme'] ?? 'auto'),
            'fontFamily' => (string) ($attributes['fontFamily'] ?? '"Manrope", "Avenir Next", "Segoe UI", sans-serif'),
            'fontSize' => (string) ($attributes['fontSize'] ?? '16px'),
            'lineHeight' => (string) ($attributes['lineHeight'] ?? '1.45'),
            'borderRadius' => (string) ($attributes['borderRadius'] ?? '18px'),
            'bubbleRadius' => (string) ($attributes['bubbleRadius'] ?? '14px'),
            'outerMargin' => (string) ($attributes['outerMargin'] ?? '0px'),
            'chatPadding' => (string) ($attributes['chatPadding'] ?? '14px'),
            'messageGap' => (string) ($attributes['messageGap'] ?? '12px'),
            'chatBackgroundColor' => (string) ($attributes['chatBackgroundColor'] ?? '#f8fafc'),
            'borderColor' => (string) ($attributes['borderColor'] ?? '#cbd5e1'),
            'headerBackgroundColor' => (string) ($attributes['headerBackgroundColor'] ?? '#ffffff'),
            'headerTextColor' => (string) ($attributes['headerTextColor'] ?? '#0f172a'),
            'userBubbleColor' => (string) ($attributes['userBubbleColor'] ?? '#dbeafe'),
            'userTextColor' => (string) ($attributes['userTextColor'] ?? '#1e293b'),
            'assistantBubbleColor' => (string) ($attributes['assistantBubbleColor'] ?? '#ffffff'),
            'assistantTextColor' => (string) ($attributes['assistantTextColor'] ?? '#0f172a'),
            'toolBubbleColor' => (string) ($attributes['toolBubbleColor'] ?? '#f1f5f9'),
            'toolTextColor' => (string) ($attributes['toolTextColor'] ?? '#0f172a'),
            'composerBackgroundColor' => (string) ($attributes['composerBackgroundColor'] ?? '#ffffff'),
            'inputBackgroundColor' => (string) ($attributes['inputBackgroundColor'] ?? '#ffffff'),
            'inputTextColor' => (string) ($attributes['inputTextColor'] ?? '#0f172a'),
            'buttonBackgroundColor' => (string) ($attributes['buttonBackgroundColor'] ?? '#0f172a'),
            'buttonTextColor' => (string) ($attributes['buttonTextColor'] ?? '#ffffff'),
            'accentColor' => (string) ($attributes['accentColor'] ?? '#2563eb'),
        ];

        $model = (string) get_option('wp_native_agent_default_model', 'openai/gpt-4o-mini');
        $restNonce = wp_create_nonce('wp_rest');

        return sprintf(
            '<div class="wpna-react-chat-root" data-wpna-react-chat="1" data-placeholder="%s" data-system-prompt-override="%s" data-system-prompt-mode="%s" data-max-height="%s" data-enabled-tools="%s" data-ui-config="%s" data-model="%s" data-rest-nonce="%s"></div>',
            esc_attr($placeholder),
            esc_attr($systemPromptOverride),
            esc_attr($systemPromptMode),
            esc_attr($maxHeight),
            esc_attr((string) wp_json_encode($enabledTools)),
            esc_attr((string) wp_json_encode($uiConfig)),
            esc_attr($model),
            esc_attr($restNonce)
        );
    }
}
