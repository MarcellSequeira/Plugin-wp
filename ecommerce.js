var successCallback = function(data) {

	var checkout_form = $( 'form.woocommerce-checkout' );

	//aniade un token para el campo oculto
	checkout_form.find('#misha_token').val(data.token);

	// desactiva la funcion de peticion de toquen
	checkout_form.off( 'checkout_place_order', tokenRequest );

	//envia el formulario
	checkout_form.submit();

};

var errorCallback = function(data) {
    console.log(data);
};

var tokenRequest = function() {

	//espacio para el API REST que procesa la informacion de la tarjeta de credito
	return false;
		
};

jQuery(function($){

	var checkout_form = $( 'form.woocommerce-checkout' );
	checkout_form.on( 'checkout_place_order', tokenRequest );

});