import type { RefObject, UIEventHandler } from 'react';
import type { HistoryTimelineEntry } from '../../../../shared/history-presenter';
import MessageItem from './MessageItem';

type MessageListProps = {
	entries: HistoryTimelineEntry[];
	listRef: RefObject< HTMLDivElement >;
	onScroll: UIEventHandler< HTMLDivElement >;
	hasMore: boolean;
	loadingOlder: boolean;
	onLoadOlder: () => void;
};

function MessageList( {
	entries,
	listRef,
	onScroll,
	hasMore,
	loadingOlder,
	onLoadOlder,
}: MessageListProps ) {
	return (
		<div
			className="wpclaw-message-list"
			ref={ listRef }
			onScroll={ onScroll }
		>
			{ hasMore ? (
				<button
					type="button"
					className="wpclaw-load-older"
					onClick={ onLoadOlder }
					disabled={ loadingOlder }
				>
					{ loadingOlder ? 'Loading...' : 'Load older messages' }
				</button>
			) : null }
			{ entries.map( ( entry, index ) => {
				if ( entry.type === 'message' ) {
					const message = entry.message;

					return (
						<MessageItem
							key={ `${ message.id ?? 'm' }-${ index }` }
							message={ message }
						/>
					);
				}

				const call = entry.run;

				return (
					<details
						key={ `${ call.id }-${ index }` }
						className="wpclaw-tool-call wpclaw-message wpclaw-message-tool wpclaw-timeline-entry"
					>
						<summary>{ call.name }</summary>
						<pre>{ JSON.stringify( call.args, null, 2 ) }</pre>
						<pre>
							{ JSON.stringify(
								{
									ok: call.ok,
									payload: call.payload,
									error: call.error,
								},
								null,
								2
							) }
						</pre>
					</details>
				);
			} ) }
		</div>
	);
}

export default MessageList;
