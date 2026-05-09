import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	Button,
	Placeholder,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const FORMAT_LINE_DEFAULTS = {
	sonnet: 14,
	free_verse: 6,
	couplets: 4,
	prose: 6,
	list: 5,
};

const FORMAT_STEP = {
	sonnet: 14,
	free_verse: 1,
	couplets: 2,
	prose: 1,
	list: 1,
};

const FORMAT_MIN = {
	sonnet: 14,
	free_verse: 1,
	couplets: 2,
	prose: 1,
	list: 1,
};

const FORMAT_FIXED = {
	sonnet: 14,
};

export default function Edit( { attributes, setAttributes } ) {
	const { format, lines, lineCount, attribution, postOrder } = attributes;
	const [ loading, setLoading ] = useState( false );
	const [ warning, setWarning ] = useState( '' );
	const [ scrambling, setScrambling ] = useState( new Set() );
	const blockProps = useBlockProps();

	const {
		formats = [],
		restUrl = '',
		nonce = '',
	} = window.lexicalLodeData || {};

	const apiFetch = useCallback( async ( endpoint, body ) => {
		const response = await fetch( `${ restUrl }/${ endpoint }`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify( body ),
		} );
		if ( ! response.ok ) {
			throw new Error( `API error: ${ response.status }` );
		}
		return response.json();
	}, [ restUrl, nonce ] );

	const handleFormatSelect = async ( selectedFormat ) => {
		const count = FORMAT_LINE_DEFAULTS[ selectedFormat ] || 6;
		setAttributes( { format: selectedFormat, lineCount: count } );
		await generateLines( count );
	};

	const generateLines = async ( count, order ) => {
		setLoading( true );
		setWarning( '' );
		try {
			const data = await apiFetch( 'generate', {
				line_count: count || lineCount,
				order: order || postOrder || 'random',
			} );
			if ( data.lines ) {
				setAttributes( { lines: data.lines } );
			}
			if ( data.warning ) {
				setWarning( data.warning );
			}
		} catch ( err ) {
			setWarning( __( 'Failed to generate lines. Please try again.', 'lexical-lode' ) );
		}
		setLoading( false );
	};

	// Sync lines array to match lineCount.
	const appendTimerRef = useRef( null );
	useEffect( () => {
		if ( ! format ) return;

		if ( lines.length > lineCount ) {
			// Confirm before truncating if losing more than 1 line.
			const excess = lines.length - lineCount;
			if ( excess > 1 ) {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm(
					`This will remove ${ excess } lines. Continue?`
				) ) {
					setAttributes( { lineCount: lines.length } );
					return;
				}
			}
			setAttributes( { lines: lines.slice( 0, lineCount ) } );
			return;
		}

		if ( lines.length < lineCount ) {
			if ( appendTimerRef.current ) {
				clearTimeout( appendTimerRef.current );
			}
			appendTimerRef.current = setTimeout( async () => {
				setLoading( true );
				setWarning( '' );
				try {
					const currentLines = attributes.lines;
					const delta = lineCount - currentLines.length;
					if ( delta <= 0 ) return;
					const excludeIds = currentLines.map( ( l ) => l.post_id );
					const data = await apiFetch( 'generate', {
						line_count: delta,
						order: postOrder || 'random',
						exclude_post_ids: excludeIds,
					} );
					if ( data.lines && data.lines.length > 0 ) {
						setAttributes( { lines: [ ...currentLines, ...data.lines ] } );
					}
					if ( data.warning ) {
						setWarning( data.warning );
					}
				} catch ( err ) {
					setWarning( __( 'Failed to fetch additional lines.', 'lexical-lode' ) );
				}
				setLoading( false );
			}, 500 );
		}

		return () => {
			if ( appendTimerRef.current ) {
				clearTimeout( appendTimerRef.current );
			}
		};
	}, [ lineCount, format, postOrder, apiFetch, attributes.lines, setAttributes ] );

	const rerollLine = async ( index ) => {
		const line = lines[ index ];
		if ( ! line || scrambling.has( index ) ) return;

		setScrambling( ( prev ) => new Set( prev ).add( index ) );
		try {
			const data = await apiFetch( 'generate', {
				line_count: 1,
				order: 'random',
				exclude_post_ids: [],
			} );
			if ( data.lines && data.lines.length > 0 ) {
				const updated = [ ...lines ];
				updated[ index ] = data.lines[ 0 ];
				setAttributes( { lines: updated } );
			}
		} catch ( err ) {
			setWarning( __( 'Failed to re-roll line.', 'lexical-lode' ) );
		}
		setScrambling( ( prev ) => {
			const next = new Set( prev );
			next.delete( index );
			return next;
		} );
	};

	// Format picker — shown before anything renders.
	if ( ! format ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="editor-quote"
					label={ __( 'Lexical Lode', 'lexical-lode' ) }
					instructions={ __( 'Choose a format to generate found poetry from your posts.', 'lexical-lode' ) }
				>
					<div className="lexical-lode-format-picker">
						{ formats.map( ( f ) => (
							<Button
								key={ f.value }
								variant="secondary"
								onClick={ () => handleFormatSelect( f.value ) }
								disabled={ loading }
							>
								{ f.label }
							</Button>
						) ) }
						{ formats.length === 0 && (
							<p>{ __( 'No formats enabled. Check Lexical Lode settings.', 'lexical-lode' ) }</p>
						) }
					</div>
					{ loading && <Spinner /> }
				</Placeholder>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Format', 'lexical-lode' ) }>
					<SelectControl
						label={ __( 'Format', 'lexical-lode' ) }
						value={ format }
						options={ formats.map( ( f ) => ( { value: f.value, label: f.label } ) ) }
						onChange={ ( val ) => {
							const count = FORMAT_LINE_DEFAULTS[ val ] || lineCount;
							setAttributes( { format: val, lineCount: count } );
						} }
					/>
					{ ! FORMAT_FIXED[ format ] && (
						<RangeControl
							label={ __( 'Number of lines', 'lexical-lode' ) }
							value={ lineCount }
							onChange={ ( val ) => setAttributes( { lineCount: val } ) }
							min={ FORMAT_MIN[ format ] || 1 }
							max={ 50 }
							step={ FORMAT_STEP[ format ] || 1 }
						/>
					) }
					<Button
						variant="secondary"
						onClick={ () => generateLines( lineCount ) }
						disabled={ loading }
					>
						{ __( 'Regenerate all', 'lexical-lode' ) }
					</Button>
				</PanelBody>

				<PanelBody title={ __( 'Source Posts', 'lexical-lode' ) }>
					<ToggleControl
						label={ __( 'Use random posts', 'lexical-lode' ) }
						checked={ postOrder === 'random' }
						onChange={ ( val ) =>
							setAttributes( { postOrder: val ? 'random' : 'newest' } )
						}
					/>
					{ postOrder !== 'random' && (
						<SelectControl
							label={ __( 'Post order', 'lexical-lode' ) }
							value={ postOrder }
							options={ [
								{ value: 'newest', label: __( 'Most recent', 'lexical-lode' ) },
								{ value: 'oldest', label: __( 'Oldest', 'lexical-lode' ) },
							] }
							onChange={ ( val ) => setAttributes( { postOrder: val } ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Display', 'lexical-lode' ) }>
					<SelectControl
						label={ __( 'Attribution', 'lexical-lode' ) }
						value={ attribution }
						options={ [
							{ value: 'hidden', label: __( 'Hidden', 'lexical-lode' ) },
							{ value: 'hover', label: __( 'On hover', 'lexical-lode' ) },
							{ value: 'footnotes', label: __( 'Footnotes', 'lexical-lode' ) },
						] }
						onChange={ ( val ) => setAttributes( { attribution: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ warning && (
				<Notice status="warning" isDismissible={ false }>
					{ warning }
				</Notice>
			) }

			{ loading && (
				<div className="lexical-lode-loading">
					<Spinner />
					<p>{ __( 'Mining your posts...', 'lexical-lode' ) }</p>
				</div>
			) }

			{ ! loading && lines.length > 0 && (
				<div className={ `lexical-lode-editor-preview lexical-lode-format-${ format }` }>
					{ format === 'prose' ? (
						<p className="lexical-lode-prose">
							{ lines.map( ( line, i ) => (
								<span key={ i } className="lexical-lode-phrase-wrapper">
									<span className="lexical-lode-phrase">{ line.phrase }</span>
									<button
										type="button"
										className="lexical-lode-scramble-btn"
										onClick={ () => rerollLine( i ) }
										disabled={ scrambling.has( i ) }
										title={ __( 'Re-roll', 'lexical-lode' ) }
									>
										&#x21bb;
									</button>
									{ i < lines.length - 1 ? ' ' : '' }
								</span>
							) ) }
						</p>
					) : format === 'list' ? (
						<ol className="lexical-lode-list">
							{ lines.map( ( line, i ) => (
								<li key={ i } className="lexical-lode-line">
									<span className="lexical-lode-phrase">{ line.phrase }</span>
									<button
										type="button"
										className="lexical-lode-scramble-btn"
										onClick={ () => rerollLine( i ) }
										disabled={ scrambling.has( i ) }
										title={ __( 'Re-roll', 'lexical-lode' ) }
									>
										&#x21bb;
									</button>
								</li>
							) ) }
						</ol>
					) : (
						<div className="lexical-lode-lines">
							{ lines.map( ( line, i ) => (
								<div key={ i }>
									{ format === 'couplets' && i > 0 && i % 2 === 0 && (
										<div className="lexical-lode-stanza-break" />
									) }
									<div className="lexical-lode-line">
										<span className="lexical-lode-phrase">{ line.phrase }</span>
										<button
											type="button"
											className="lexical-lode-scramble-btn"
											onClick={ () => rerollLine( i ) }
											disabled={ scrambling.has( i ) }
											title={ __( 'Re-roll', 'lexical-lode' ) }
										>
											&#x21bb;
										</button>
									</div>
								</div>
							) ) }
						</div>
					) }
				</div>
			) }

			{ ! loading && lines.length === 0 && format && (
				<Notice status="info" isDismissible={ false }>
					{ __( 'No lines generated. Your site may not have enough posts with usable phrases.', 'lexical-lode' ) }
				</Notice>
			) }
		</div>
	);
}
