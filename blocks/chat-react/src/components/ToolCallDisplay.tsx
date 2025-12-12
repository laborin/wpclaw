import type { ToolRun } from '../../../../shared/types';

type ToolCallDisplayProps = {
	calls: ToolRun[];
	inline?: boolean;
};

function ToolCallDisplay( { calls, inline = false }: ToolCallDisplayProps ) {
	if ( calls.length === 0 ) {
		return null;
	}

	const content = calls.map( ( call ) => (
		<details
			key={ call.id }
			className={
				inline
					? 'wpclaw-tool-call wpclaw-message wpclaw-message-tool'
					: 'wpclaw-tool-call'
			}
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
	) );

	if ( inline ) {
		return <>{ content }</>;
	}

	return <div className="wpclaw-tool-calls">{ content }</div>;
}

export default ToolCallDisplay;
