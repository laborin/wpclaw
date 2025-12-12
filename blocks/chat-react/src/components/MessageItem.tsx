import type { ChatMessage } from '../../../../shared/types';

type MessageItemProps = {
	message: ChatMessage;
};

function MessageItem( { message }: MessageItemProps ) {
	return (
		<div
			className={ `wpclaw-message wpclaw-message-${ message.role } wpclaw-timeline-entry` }
		>
			<header>{ message.role }</header>
			<p>{ message.content }</p>
		</div>
	);
}

export default MessageItem;
