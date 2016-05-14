jQuery( document ).ready( function( ) {

	// Hook into heartbeat send. We need to send some data to kick off the AJAX call.
	jQuery( document ).on( 'heartbeat-send', function( e, data ) {
		data[ 'locOnline' ] = 'true';
	} );
	
	// Hook into heartbeat-error. In case of error, let's log it.
	jQuery( document ).on( 'heartbeat-error', function( e, jqXHR, textStatus, error ) {
		console.log( 'BEGIN ERROR' );
		console.log( textStatus );
		console.log( error );
		console.log( 'END ERROR' );
	} );
} ); 