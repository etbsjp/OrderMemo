(function () {
	'use strict';

	function init() {
		if ( ! window.OrmmData || ! OrmmData.templates || ! OrmmData.templates.length ) { return; }

		var addNote  = document.querySelector( '#woocommerce-order-notes .add_note' );
		var textarea = document.getElementById( 'add_order_note' );
		if ( ! addNote || ! textarea ) { return; }

		var row = document.createElement( 'p' );
		row.className = 'ormm-insert-row';

		var label = document.createElement( 'label' );
		label.textContent = OrmmData.i18n.selectLabel;
		label.setAttribute( 'for', 'ormm_template_select' );
		label.style.display = 'block';

		var select = document.createElement( 'select' );
		select.id = 'ormm_template_select';
		select.style.maxWidth = '70%';

		var placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = OrmmData.i18n.placeholder;
		select.appendChild( placeholder );

		OrmmData.templates.forEach( function ( template ) {
			var option = document.createElement( 'option' );
			option.value = String( template.id );
			option.textContent = template.title;
			select.appendChild( option );
		} );

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button ormm-insert-button';
		button.textContent = OrmmData.i18n.insertButton;
		button.style.marginLeft = '4px';

		button.addEventListener( 'click', function () {
			var templateId = select.value;
			if ( ! templateId ) { return; }

			button.disabled = true;

			var body = new URLSearchParams();
			body.append( 'action', 'ormm_render_template' );
			body.append( 'nonce', OrmmData.nonce );
			body.append( 'template_id', templateId );
			body.append( 'order_id', String( OrmmData.orderId ) );

			fetch( OrmmData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( json ) {
				if ( ! json || ! json.success ) {
					var message = ( json && json.data && json.data.message ) ? json.data.message : OrmmData.i18n.insertError;
					window.alert( message );
					return;
				}
				// 既存の入力は消さず、改行を挟んで末尾に追記する。
				var current = textarea.value;
				textarea.value = ( '' === current ) ? json.data.text : current.replace( /\n*$/, '' ) + '\n' + json.data.text;
				textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				textarea.focus();
			} ).catch( function () {
				window.alert( OrmmData.i18n.insertError );
			} ).finally( function () {
				button.disabled = false;
			} );
		} );

		row.appendChild( label );
		row.appendChild( select );
		row.appendChild( button );
		addNote.insertBefore( row, addNote.firstChild );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
