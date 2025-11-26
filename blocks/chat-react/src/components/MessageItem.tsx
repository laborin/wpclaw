import type { ChatMessage } from '../../../../shared/types';

type MessageItemProps = {
	message: ChatMessage;
};

function MessageItem( { message }: MessageItemProps ) {
	return (
		<div
			className={ `wpna-message wpna-message-${ message.role } wpna-timeline-entry` }
		>
			<header>{ message.role }</header>
			<p>{ message.content }</p>
		</div>
	);
}

export default MessageItem;
