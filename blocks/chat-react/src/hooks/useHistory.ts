import { useCallback, useEffect, useState } from '@wordpress/element';
import { WpClawClient } from '../../../../shared/api-client';
import {
	presentHistory,
	type HistoryTimelineEntry,
} from '../../../../shared/history-presenter';

const HISTORY_PAGE_SIZE = 80;

type UseHistoryResult = {
	timeline: HistoryTimelineEntry[];
	reload: () => Promise< void >;
	loadOlder: () => Promise< number >;
	clear: () => Promise< void >;
	loading: boolean;
	loadingOlder: boolean;
	hasMore: boolean;
	error: string | null;
	setError: ( value: string | null ) => void;
};

export function useHistory( client: WpClawClient ): UseHistoryResult {
	const [ timeline, setTimelineState ] = useState< HistoryTimelineEntry[] >(
		[]
	);
	const [ loading, setLoading ] = useState( true );
	const [ loadingOlder, setLoadingOlder ] = useState( false );
	const [ hasMore, setHasMore ] = useState( false );
	const [ nextOffset, setNextOffset ] = useState< number | null >( null );
	const [ error, setErrorState ] = useState< string | null >( null );

	const reload = useCallback( async () => {
		setLoading( true );
		setErrorState( null );

		const response = await client.getHistory( {
			limit: HISTORY_PAGE_SIZE,
			offset: 0,
			order: 'desc',
		} );

		if ( ! response.ok ) {
			setErrorState(
				response.error?.message ?? 'Failed to load history.'
			);
			setLoading( false );
			return;
		}

		const presented = presentHistory(
			[ ...( response.messages ?? [] ) ].reverse()
		);

		setTimelineState( presented.timeline );
		setHasMore( Boolean( response.has_more ) );
		setNextOffset(
			typeof response.next_offset === 'number'
				? response.next_offset
				: null
		);
		setLoading( false );
	}, [ client ] );

	useEffect( () => {
		void reload();
	}, [ reload ] );

	const loadOlder = useCallback( async (): Promise< number > => {
		if ( loading || loadingOlder || ! hasMore || nextOffset === null ) {
			return 0;
		}

		setLoadingOlder( true );

		const response = await client.getHistory( {
			limit: HISTORY_PAGE_SIZE,
			offset: nextOffset,
			order: 'desc',
		} );

		if ( ! response.ok ) {
			setErrorState(
				response.error?.message ?? 'Failed to load older messages.'
			);
			setLoadingOlder( false );
			return 0;
		}

		const presented = presentHistory(
			[ ...( response.messages ?? [] ) ].reverse()
		);

		if ( presented.timeline.length > 0 ) {
			setTimelineState( ( current ) => [
				...presented.timeline,
				...current,
			] );
		}

		setHasMore( Boolean( response.has_more ) );
		setNextOffset(
			typeof response.next_offset === 'number'
				? response.next_offset
				: null
		);
		setLoadingOlder( false );

		return presented.timeline.length;
	}, [ client, hasMore, loading, loadingOlder, nextOffset ] );

	const clear = useCallback( async () => {
		const response = await client.clearHistory();

		if ( ! response.ok ) {
			setErrorState(
				response.error?.message ?? 'Failed to clear history.'
			);
			return;
		}

		setTimelineState( [] );
		setHasMore( false );
		setNextOffset( null );
	}, [ client ] );

	const setError = useCallback( ( value: string | null ) => {
		setErrorState( value );
	}, [] );

	return {
		timeline,
		reload,
		loadOlder,
		clear,
		loading,
		loadingOlder,
		hasMore,
		error,
		setError,
	};
}
