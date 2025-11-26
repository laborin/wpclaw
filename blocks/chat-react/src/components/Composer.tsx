import type { FormEvent, KeyboardEvent } from 'react';
import { useState } from '@wordpress/element';

type ComposerProps = {
	disabled: boolean;
	onSubmit: ( content: string ) => Promise< void >;
	placeholder: string;
};

function Composer( { disabled, onSubmit, placeholder }: ComposerProps ) {
	const [ value, setValue ] = useState( '' );

	const submitCurrentValue = async () => {
		const trimmed = value.trim();
		if ( trimmed === '' || disabled ) {
			return;
		}

		setValue( '' );
		await onSubmit( trimmed );
	};

	const handleSubmit = async ( event: FormEvent< HTMLFormElement > ) => {
		event.preventDefault();
		await submitCurrentValue();
	};

	const handleKeyDown = ( event: KeyboardEvent< HTMLTextAreaElement > ) => {
		if (
			event.key === 'Enter' &&
			! event.shiftKey &&
			! event.ctrlKey &&
			! event.metaKey &&
			! event.altKey
		) {
			event.preventDefault();
			void submitCurrentValue();
		}
	};

	return (
		<form
			className="wpna-composer"
			onSubmit={ ( event ) => void handleSubmit( event ) }
		>
			<textarea
				rows={ 3 }
				placeholder={ placeholder }
				value={ value }
				onChange={ ( event ) => setValue( event.target.value ) }
				onKeyDown={ handleKeyDown }
				disabled={ disabled }
			/>
			<button type="submit" disabled={ disabled }>
				{ disabled ? 'Sending...' : 'Send' }
			</button>
		</form>
	);
}

export default Composer;
