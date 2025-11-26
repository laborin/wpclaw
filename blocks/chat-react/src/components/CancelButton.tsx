type CancelButtonProps = {
	busy: boolean;
	onCancel: () => Promise< void >;
};

function CancelButton( { busy, onCancel }: CancelButtonProps ) {
	if ( ! busy ) {
		return null;
	}

	return (
		<button type="button" onClick={ () => void onCancel() }>
			Cancel
		</button>
	);
}

export default CancelButton;
