<?php


function Activate(){
    add_action('wp_footer', function(){
        ?>
        <script>
            alert('Comercio Electronico: Pasarela de pagos activada con exito')
        </script>
        <?php 
        }  ,9999);
}

function Deactivate(){
    
}

register_activation_hook(__FILE__, 'Activate');

register_deactivation_hook(__FILE__, 'Deactivate');

/*comprobar si se encuentra woocommerce instalado */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

//Registrar la clase de php como un plugin para procesar pagos
add_filter( 'woocommerce_payment_gateways', 'add_payment_gateway' );
function add_payment_gateway( $gateways ) {
	$gateways[] = 'Wc_Ecommerce_Payment_Gateway'; 
	return $gateways;
}

add_action( 'plugins_loaded', 'Ecommerce_load_gateway' );
function Ecommerce_load_gateway() {

	class Wc_Ecommerce_Payment_Gateway extends WC_Payment_Gateway {

 		public function __construct() {
            $this->id = 'misha'; //identificador para el proceso de pago
            $this->icon = ''; // URL del icono que sera desplegado cerca del nombre de tu pasarela en la pagina de chequeo
            $this->has_fields = true; // en caso de necesitar un campo personalizado para tarjetas de credito
            $this->method_title = 'Ecommerce Payment Gateway';
            $this->method_description = 'Pasarela de pago para el componente de Comercio Electronico'; //mensaje desplegado en las opciones del plugin

            //indicando que sera un plugin para un tipo de procesamiento de pagos simples
            $this->supports = array(
                'products'
            );
            
            //inicializa los campos del formulario para la configuracion del plugin
            $this->init_form_fields();

            //cargando la configuracion
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

            // Este action guarda las configuraciones
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            // para crear un token se hara uso de codigo javascript 
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 		}

 		//Opciones del plugin
 		public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Habilitar/Deshabilitar',
                    'label'       => 'Habilita Ecommerce Gateway Plugin',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Titulo',
                    'type'        => 'text',
                    'description' => 'Esto controla el titulo que el usuario ve en el momento del chequeo.',
                    'default'     => 'Credit Card',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Descripcion',
                    'type'        => 'textarea',
                    'description' => 'Esto controla la descripcion que el usuario ve durante el pago.',
                    'default'     => 'Realiza el pago con tu tarjeta de credito en nuestra nada segura pasarela de pago. ',
                ),
                'testmode' => array(
                    'title'       => 'Modo de prueba',
                    'label'       => 'Habilita el modo de prueba',
                    'type'        => 'checkbox',
                    'description' => 'Cambia la pasarela de pago en el modo de pruebas usando las llaves API de prueba.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Llave de pruebas publica',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Llave de pruebas privada',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'Clave publica en vivo',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Clave provada en vivo',
                    'type'        => 'password'
                )
            );

	 	}

	 	public function payment_scripts() {
            if(!is_cart() && !is_checkout() && !isset( $_GET['pay_for_order'])) {
                return;
            }

            if('no' === $this->enabled) {
                return;
            }

            if(empty($this->private_key) || empty($this->publishable_key)) {
                return;
            }

            if (!$this->testmode && !is_ssl() ) {
                return;
            }

            wp_enqueue_script( 'ecommerce.js', './' );
            wp_register_script( 'E_commerce', plugins_url( 'ecommerce.js', __FILE__ ), array( 'jquery', 'ecommerce.js' ) );
            wp_localize_script( 'E_commerce', 'misha_params', array(
                'publishableKey' => $this->publishable_key
            ) );

            wp_enqueue_script( 'E_commerce' );
	
	 	}

         public function payment_fields() {
            if ( $this->description ) {
                if ($this->testmode ) {
                    $this->description .= ' Modo de pruebas habilitado';
                    $this->description  = trim( $this->description );
                }
                echo wpautop( wp_kses_post( $this->description ) );
            }
  
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
  
            do_action( 'woocommerce_credit_card_form_start', $this->id );

            echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
                <input id="misha_ccNo" type="text" autocomplete="off">
                </div>
                <div class="form-row form-row-first">
                    <label>Expiry Date <span class="required">*</span></label>
                    <input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
                </div>
                <div class="form-row form-row-last">
                    <label>Card Code (CVC) <span class="required">*</span></label>
                    <input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
                </div>
                <div class="clear"></div>';
         
            do_action( 'woocommerce_credit_card_form_end', $this->id );
         
            echo '<div class="clear"></div></fieldset>';
         
        }

		public function validate_fields() {
            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }
            return true;
		}

		public function process_payment( $order_id ) {
            global $woocommerce;
            $order = wc_get_order( $order_id );
            $args = array();
            $response = wp_remote_post( '{payment processor endpoint}', $args );
            if( !is_wp_error( $response ) ) {
                $body = json_decode( $response['body'], true );
                if ( $body['response']['responseCode'] == 'APPROVED' ) {
                    $order->payment_complete();
                    $order->reduce_order_stock();
                    $order->add_order_note( 'Hey, your order is paid! Thank you!', true );
                    $woocommerce->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                } else {
                    wc_add_notice(  'Please try again.', 'error' );
                    return;
                }
            } else {
                wc_add_notice(  'Connection error.', 'error' );
                return;
            }
	 	}

		public function webhook() {
            $order = wc_get_order( $_GET['id'] );
            $order->payment_complete();
            $order->reduce_order_stock();
            update_option('webhook_debug', $_GET);
	 	}
 	}
}

