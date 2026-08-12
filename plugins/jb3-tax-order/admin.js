jQuery( function ( $ ) {
    var $tbody = $( '#the-list' );
    if ( ! $tbody.length ) return;

    // Agregar ícono de arrastre a cada fila.
    $tbody.find( 'tr' ).each( function () {
        $( this ).find( 'td.name' ).prepend(
            '<span class="jb3-drag-handle dashicons dashicons-menu" title="Arrastrar para reordenar"></span>'
        );
    } );

    $tbody.sortable( {
        handle:      '.jb3-drag-handle',
        placeholder: 'jb3-sort-placeholder',
        axis:        'y',
        tolerance:   'pointer',
        stop: function () {
            var order = [];
            $tbody.find( 'tr' ).each( function () {
                var id = parseInt( ( $( this ).attr( 'id' ) || '' ).replace( 'tag-', '' ), 10 );
                if ( id ) order.push( id );
            } );

            $.post( jb3TaxOrder.ajaxurl, {
                action: 'jb3_save_term_order',
                nonce:  jb3TaxOrder.nonce,
                order:  order,
            } );
        },
    } );
} );
