( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! config || ! wp.plugins || ! wp.data || ! wp.element ) {
		return;
	}

	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const useSelect = wp.data.useSelect;
	const useDispatch = wp.data.useDispatch;
	const components = wp.components;
	const Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! Panel ) {
		return;
	}

	function Field( props ) {
		const field = props.field;
		const common = {
			label: field.label,
			help: field.help || undefined,
			value: props.value === undefined || props.value === null ? '' : props.value,
			onChange: props.onChange,
			__nextHasNoMarginBottom: true
		};

		if ( field.type === 'textarea' ) {
			return el( components.TextareaControl, common );
		}
		if ( field.type === 'select' ) {
			common.options = Object.keys( field.options || {} ).map( function ( value ) {
				return { label: field.options[ value ], value: value };
			} );
			return el( components.SelectControl, common );
		}
		common.type = field.type === 'number' ? 'number' : field.type;
		return el( components.TextControl, common );
	}

	function Workspace() {
		const meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		const editPost = useDispatch( 'core/editor' ).editPost;

		function update( field, value ) {
			const next = Object.assign( {}, meta );
			next[ field.key ] = field.type === 'number' ? ( value === '' ? 0 : parseInt( value, 10 ) || 0 ) : value;
			editPost( { meta: next } );
		}

		function fields( group ) {
			return config.fields.filter( function ( field ) {
				return field.group === group;
			} ).map( function ( field ) {
				return el( Field, {
					key: field.key,
					field: field,
					value: meta[ field.key ],
					onChange: function ( value ) { update( field, value ); }
				} );
			} );
		}

		if ( config.postType === 'post' ) {
			return el( Panel, { name: 'cywater-news-presentation', title: config.copy.newsPanel },
				el( 'p', { className: 'cywater-editor-help' }, config.copy.newsHelp ),
				fields( 'presentation' ), fields( 'source' )
			);
		}

		if ( config.postType === 'cyw_event' ) {
			return el( Fragment, null,
				el( Panel, { name: 'cywater-event-schedule', title: config.copy.eventSchedule, initialOpen: true },
					el( 'p', { className: 'cywater-editor-help' }, config.copy.eventHelp ), fields( 'schedule' )
				),
				el( Panel, { name: 'cywater-event-publishing', title: config.copy.eventPublishing, initialOpen: false },
					el( 'div', { className: 'cywater-editor-visibility' },
						el( 'strong', null, config.copy.publicHeading ),
						el( 'p', null, config.copy.publicCopy )
					), fields( 'publishing' )
				)
			);
		}

		return el( Panel, { name: 'cywater-award-details', title: config.copy.awardPanel },
			el( 'p', { className: 'cywater-editor-help' }, config.copy.awardHelp ), fields( 'details' )
		);
	}

	wp.plugins.registerPlugin( 'cywater-editor-workspace', { icon: 'edit-page', render: Workspace } );

	// These public editorial records deliberately do not use PMPro content
	// restriction. Retry briefly because PMPro may register after this script.
	function removeConflictingPanels( attempts ) {
		const plugin = wp.plugins.getPlugin && wp.plugins.getPlugin( 'pmpro-sidebar' );
		if ( plugin ) {
			wp.plugins.unregisterPlugin( 'pmpro-sidebar' );
			return;
		}
		if ( attempts > 0 ) {
			window.setTimeout( function () { removeConflictingPanels( attempts - 1 ); }, 100 );
		}
	}
	removeConflictingPanels( 20 );
} )( window.wp, window.cywaterEditorWorkspace );
