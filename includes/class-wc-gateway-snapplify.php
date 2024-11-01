<?php

class SNAP_WC_Gateway_Snapplify extends WC_Payment_Gateway {

	private $api_secret;
	private $api_key;
	private $store_location;
	private $store_currency;
	private $supported_countries;
	private $default_currency_value;

	public function __construct() {
		$this->id                = 'snapplify';
		$this->method_title       = __( 'Snapplify Pay', 'snapplify-pay' );
		$this->method_description = __( 'Snapplify Pay - powers Snapplify Pay payments for WooCommerce', 'snapplify-pay' );
		$this->title              = __( 'Snapplify Pay', 'snapplify-pay' );

		$this->icon = SNAP_DIR . '/assets/snapplify-icon.png';

		$this->has_fields = true;
		$this->snap_init_form_fields();
		$this->init_settings();
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		if ( false === $this->is_snap_mode_production() ) {
			$this->title = '(Sandbox mode) ' . $this->title;
		}

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		$this->supports = array(
			'products',
			'pre-orders',
		);
		$this->supported_countries  = ( !empty($this->get_option('payment_supported_country')) ) ? $this->get_option('payment_supported_country') : array('ZA', 'KE');
		$this->supported_currencies = ( !empty($this->get_option('payment_supported_currency')) ) ? $this->get_option('payment_supported_currency') : array('ZAR', 'USD', 'KES');

		$this->api_key    = ( ( true === $this->is_snap_mode_production() ) ? $this->get_option( 'api_key' ) : $this->get_option( 'sandbox_api_key' ) );
		$this->api_secret = ( ( true === $this->is_snap_mode_production() ) ? $this->get_option( 'api_secret' ) : $this->get_option( 'sandbox_api_secret' ) );

		$client = "client=$this->api_key";
		$secret = "secret=$this->api_secret";

		$base_url = ( ( true === $this->is_snap_mode_production() ) ? 'https://pay.snapplify.com' : 'https://sandboxpay.snapplify.com' );

		$this->payment_request_url = "$base_url/payment/request?$client&$secret";
		$this->payment_submit_url  = "$base_url/payment/submit?$client";
		$this->validate_url        = "$base_url/payment/validate?$client&$secret";
		$this->validate_ipn_url    = "$base_url/payment/ipn/validate?$client&$secret";

		$this->ipn_notification_url  = get_site_url() . '?wc-api=wc_gateway_snapplify_ipn';
		$this->redirect_response_url = get_site_url() . '?wc-api=wc_gateway_snapplify_success';

		$this->store_location = wc_get_base_location();
		$this->store_currency = get_woocommerce_currency();
		$this->default_currency_value = __( 1, 'snapplify-pay' );

		add_action( 'woocommerce_receipt_snapplify', array( $this, 'snapplify_receipt_page' ) );
		add_action( 'woocommerce_api_wc_gateway_snapplify_success', array( $this, 'snapplify_success' ) );
		add_action( 'woocommerce_api_wc_gateway_snapplify_failed', array( $this, 'snapplify_failed' ) );

		/**
		 * IPN URL, not currently implemented.
		 */
		// add_action('woocommerce_api_wc_gateway_snapplify_ipn', array($this, 'snapplify_ipn'));
	}

	public function snap_get_formated_country_list() {
		$newCountries = [];
		$countries = WC()->countries->get_allowed_countries();
		if (!empty($countries)) {
			foreach ($countries as $key => $val) {
				$newCountries[$key] = $val . ' (' . $key . ')';
			}
		}
		return $newCountries;
	}

	public function snap_get_formated_currency_list() {
		$newCurrencies = [];
		$currencies = get_woocommerce_currencies();
		if (!empty($currencies)) {
			foreach ($currencies as $code => $val) {
				$newCurrencies[$code] = $val . ' (' . get_woocommerce_currency_symbol($code) . ')';
			}
		}
		return $newCurrencies;
	}
	/**
	 * Displayed in the admin for gateway configuration
	 */
	public function snap_init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable / Disable', 'snapplify-pay' ),
				'label'   => __( 'Enable this payment gateway', 'snapplify-pay' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'test_mode'   => array(
				'title'   => __( 'Enable / Disable Evaluation Mode', 'snapplify-pay' ),
				'label'   => __( 'Only allow Admins & Shop Managers to use this payment gateway.', 'snapplify-pay' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'live_mode'   => array(
				'title'   => ( ( true === $this->is_snap_mode_production() ) ? 'Disable' : 'Enable' ) . ' Live / Production Mode',
				'label'   => ( ( true === $this->is_snap_mode_production() ) ? 'Uncheck to put gateway in Sandbox' : 'Check to put gateway in Production' ) . ' mode.',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title'       => array(
				'title'    => __( 'Title', 'snapplify-pay' ),
				'type'     => 'text',
				'desc_tip' => __( 'Payment title the customer will see during the checkout process.', 'snapplify-pay' ),
				'default'  => __( 'Pay with Snapplify', 'snapplify-pay' ),
			),
			'description' => array(
				'title'    => __( 'Description', 'snapplify-pay' ),
				'type'     => 'textarea',
				'desc_tip' => __( 'Payment description the customer will see during the checkout process.', 'snapplify-pay' ),
				'default'  => __( 'You will be redirected to Snapplify to complete the payment', 'snapplify-pay' ),
				'css'      => 'max-width:350px;',
			),
		);

		if ( true === $this->is_snap_mode_production() ) {
			$this->form_fields['api_key']    = array(
				'title'    => __( 'Snapplify Public Key', 'snapplify-pay' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the Public Key provided by Snapplify', 'snapplify-pay' ),
			);
			$this->form_fields['api_secret'] = array(
				'title'    => __( 'Snapplify Secret Key', 'snapplify-pay' ),
				'type'     => 'password',
				'desc_tip' => __( 'This is the Secret Key provided by Snapplify', 'snapplify-pay' ),
			);
		} else {
			$this->form_fields['sandbox_api_key']    = array(
				'title'    => __( 'Snapplify Sandbox Public Key', 'snapplify-pay' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the SANDBOX (TEST MODE) Public Key provided by Snapplify', 'snapplify-pay' ),
			);
			$this->form_fields['sandbox_api_secret'] = array(
				'title'    => __( 'Snapplify Sandbox Secret Key', 'snapplify-pay' ),
				'type'     => 'password',
				'desc_tip' => __( 'This is the SANDBOX (TEST MODE) Secret Key provided by Snapplify', 'snapplify-pay' ),
			);
		}
		$this->form_fields['payment_supported_country'] = [
			'title'     => __('Snapplify Pay Supported Country', 'snapplify-pay'),
			'type'      => 'multiselect',
			'desc_tip'  => __('This is the Payment Supported Country', 'snapplify-pay'),
			'class'   => 'chosen_select select2',
			'css'     => 'width: 450px;',
			'default' => array('ZA', 'KE'),
			'options' => $this->snap_get_formated_country_list(),
		];
		$this->form_fields['payment_supported_currency'] = [
			'title'     => __('Snapplify Pay Supported Currency', 'snapplify-pay'),
			'type'      => 'multiselect',
			'desc_tip'  => __('This is the Payment Supported Currency', 'snapplify-pay'),
			'class'   => 'chosen_select select2',
			'css'     => 'width: 450px;',
			'default' => array('ZAR', 'USD', 'KES'),
			'options' => $this->snap_get_formated_currency_list(),
		];
	}

	public function snap_needs_setup() {
		if (
			false === $this->are_snap_prod_credentials_set()
			&& true === $this->is_snap_mode_production()
		) {
			SNAP_Helpers::snap_trigger_credentials_admin_error();
			return true;
		}

		if (
			false === $this->are_snap_dev_credentials_set()
			&& false === $this->is_snap_mode_production()
		) {
			SNAP_Helpers::snap_trigger_credentials_admin_error();
			return true;
		}

		return false;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$url = $order->get_checkout_payment_url( true );

		return array(
			'result'   => 'success',
			'redirect' => wp_sanitize_redirect( $url ),
		);
	}

	public function snapplify_receipt_page( $order ) {
		echo esc_html( $this->routeToSnapplify( $order ) );
	}

	public function routeToSnapplify( $order_id ) {
		$order = wc_get_order( $order_id );
		$url  = $this->payment_request_url;
		$billingCurrency = ( null != $_SESSION['scd_target_currency'] ) ? $_SESSION['scd_target_currency'] : $this->store_currency;
		$billingCountry = ( null != $order->get_billing_country() ) ? $order->get_billing_country() : $this->store_location;
        $amountInOrderCurrency = $originalAmount = $order->get_total();
        if ($billingCountry != 'ZA') {
            $USDToZAR = (null != $_SESSION['scd_rates'][$this->store_currency]) ? $_SESSION['scd_rates'][$this->store_currency] : $this->default_currency_value;
            $USDToCurrency = (null != $_SESSION['scd_rates'][$billingCurrency]) ? $_SESSION['scd_rates'][$billingCurrency] : $this->default_currency_value;
            $amountInUSD = round(($originalAmount / $USDToZAR), 2);
            $amountInOrderCurrency = round(($amountInUSD * $USDToCurrency), 2);
        }
		if ($billingCountry == 'KE') {
			$billingCurrency = 'KES';
			$amountInKES = $this->snapplify_get_payment_country_currency($originalAmount, $billingCurrency);
			$amountInOrderCurrency = $amountInKES;
		}
		$body = array(
			'referenceCode' => $order_id . '_' . gmdate( 'YmmddHis' ),
			'redirectUrl'   => $this->redirect_response_url,
			'amount' => $amountInOrderCurrency, // R50 
			'currency' => $billingCurrency, //$this->store_currency,
			'country' => $billingCountry, //$this->store_location['country'],
			'description' => 'woocommerce payment for #' . $order_id,
		);
		$body = wp_json_encode( $body );

		$options = array(
			'body'        => $body,
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'data_format' => 'body',
		);

		try {
			$request = wp_remote_post( $url, $options );
		} catch ( Exception $e ) {
			wc_add_notice( 'There was an error reaching the payment service. Please try your purchase again', 'error' );
			$payment_page = $order->get_checkout_payment_url();
			wp_redirect( $payment_page );
			exit;
		}

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			wc_add_notice( 'There was an error during payment. Please try your purchase again (Request Error)', 'error' );
			
			$payment_page = $order->get_checkout_payment_url();
			wp_redirect( $payment_page );
			exit;
		}

		$snapplify_response = json_decode( wp_remote_retrieve_body( $request ) );

		if ( isset( $snapplify_response->code ) ) {
			$data = (object) array(
				'response' => $snapplify_response,
				'order'    => $order,
			);
			$this->snap_handle_payment_request_error( $data );
		}

		$target_url = $snapplify_response->redirectUrl;
		wp_redirect( $target_url );
		exit;
	}

	private function snapplify_get_payment_country_currency($originalAmount, $billingCurrency)
	{
		$success = false;
		$rates = null;
		$apiArray = array();
		//   $apiArray[] = "c6bc01da16fb403a9a7c09be270c269b";
		//   $apiArray[] = "26bec72a4ed1403c98fe7b2e213d6174";
		//   $apiArray[] = "6e40b0cdb2ab4f1396eba6ed764b0bfc";
		$apiArray[] = "c5859382ef1e487fb159251230d9f2ad";
		$apiArray[] = "23d274d3fd754224af55549416a9c6ac";
		$apiArray[] = "8f4c6268eb2c482b88c6201712f8e91d";
		$apiArray[] = "3c06a455b44e4ce1b731ff931cc1165d";
		$apiArray[] = "26bec72a4ed1403c98fe7b2e213d6174";
		$apiArray[] = "a326110605c44d7c801e65e7dbc8caca";
		$apiArray[] = "42adbe51bdfb4a22a297da4f4394636b";
		$apiArray[] = "9230dc426e86404788827905613120cd";
		$apiArray[] = "3008d67f86a94e149a59090512698156";
		// We got this url from the documentation for the remote API.
		$url = 'https://openexchangerates.org/api/latest.json';

		while ($success == false && count($apiArray) > 0) {

			$appId = array_pop($apiArray);
			$query = $url . "?app_id=" . $appId . '&symbols=ZAR,KES';

			// Call the API.
			$response = wp_remote_get($query);

			if (is_wp_error($response)) {
				$success = false;
			} else {
				// Check the response
				$code = wp_remote_retrieve_response_code($response);
				$body = wp_remote_retrieve_body($response);

				if ($code == 200) {
					try {
						$decoded = json_decode($body, true);

						$data = array();
						$data = $decoded["rates"];
						$USDToCurrency = $data[$billingCurrency];
						$storeCurrency = $data[$this->store_currency];
						$amountInUSD = round(($originalAmount / $storeCurrency), 2);
						$rates = round(($amountInUSD * $USDToCurrency), 2);
					} catch (\Error $e) {
						$rates = null;
					} catch (\Exception $e) {
						$rates = null;
					}
					$success = true;
				}
			}
		} // end while

		return $rates;
	}

	public function snapplify_success()
	{
		$payment_response = SNAP_Helpers::make_submit_payment_response_object($_REQUEST);

		$validation_response = $this->snap_attempt_payment_validation( $payment_response );
		$order = $this->getOrderFromSnapplifyReference($payment_response->referenceCode);
		update_post_meta( $order->get_id(), '_paymentVia', $validation_response->paymentMethod  );

		if ( false === $this->isSnapplifyPaymentSuccessful( $payment_response ) ) {

			$order->update_status( 'failed', 'Payment was declined by Snapplify.' );
			wc_add_notice( 'Your payment was unsuccessful. Please try your purchase again', 'error' );

			wp_redirect( $this->get_return_url( $order ) );
			exit;
		}

		if ( true === $this->isSnapplifyPaymentSuccessful( $payment_response ) && 'PENDING' === $validation_response->paymentState ) {
			$payment_method = $validation_response->paymentMethod;

			// put the order on hold as we're awaiting completion of payment
			$order->update_status( 'on-hold', sprintf( 'Awaiting %s payment.', $payment_method ) );
			wc_empty_cart();
			wc_add_notice( 'Your order has been placed on hold. Please complete your outstanding payment.', 'notice' );

			wp_redirect( $this->get_return_url( $order ) );
			exit;
		}

		if ( true === $this->isSnapplifyPaymentSuccessful( $payment_response ) && 'PAID' === $validation_response->paymentState ) {


			if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

				wc_add_notice( 'Thank you. Your payment was successful.', 'success' );
				wp_redirect( $this->get_return_url( $order ) );
				exit;
			}

			$snapplify_reference = $payment_response->referenceCode;

			$order->add_order_note(
				sprintf(
					'Payment via Snapplify successful (Transaction Reference: %s)',
					$snapplify_reference
				)
			);
			$order->payment_complete();
			wc_empty_cart();
			wc_add_notice( 'Thank you. Your payment was successful.', 'success' );

			wp_redirect( $this->get_return_url( $order ) );
			exit;
		}

		wc_add_notice( 'There was an error during payment. Please try your purchase again', 'error' );
		wp_redirect( wc_get_page_permalink( 'cart' ) );
		exit;
	}

	private function snap_attempt_payment_validation( $payment_response ) {
		$url = $this->validate_url . "&token=$payment_response->token";

		try {
			$request = wp_remote_post( $url );
		} catch ( Exception $e ) {
			return;
		}

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return;
		}

		$validation_response = json_decode( wp_remote_retrieve_body( $request ) );
		$validation_response = $validation_response->payment;

		if ( isset( $validation_response->code ) ) {
			$data = (object) array( 'response' => $validation_response );
			$this->snap_handle_payment_validation_request_error( $data );
		}

		if ( false === SNAP_Helpers::is_snap_payment_data_valid( $payment_response, $validation_response ) ) {
			wc_add_notice( 'There was an error validating your payment.', 'error' );
			wp_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		}

		if ( false === $validation_response->validated ) {
			wc_add_notice( 'There was an error validating your payment.', 'error' );
			wp_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		}

		return $validation_response;
	}

	/**
	 * Handle Snapplify Pay IPN
	 */
	public function snapplify_ipn() {
		$server_request_method = 'not post';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {
			$server_request_method = sanitize_key( $_SERVER['REQUEST_METHOD'] );
		}

		if ( 'POST' !== strtoupper( $server_request_method ) ) {
			exit;
		}

		$json = file_get_contents( 'php://input' );

		$ipn_response = json_decode( $json );

		$verification = $this->verify_snapplify_ipn( $ipn_response );

		if ( true === $verification ) {

			http_response_code( 200 );

			$order_details = explode( '-', $ipn_response->payment->referenceCode );
			$order_id      = (int) $order_details[0];
			$order         = wc_get_order( $order_id );

			if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
				exit;
			}

			if ( in_array( $order->get_status(), array( 'pending' ) ) ) {
				$order->update_status( 'processing', 'Payment via Snapplify was successful.' );
				exit;
			}

			$order->add_order_note(
				sprintf(
					'Snapplify Payment IPN Confirmation successful (Transaction Reference: %s)',
					$snapplify_reference
				)
			);

			wc_empty_cart();
		}

		exit;
	}

	public function verify_snapplify_ipn( $data ) {
		$url = $this->validate_ipn_url;

		$data = json_encode( $data );

		$parameters = array(
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'body'        => $data,
			'data_format' => 'body',
		);

		try {
			$request = wp_remote_post( $url, $parameters );
		} catch ( Exception $e ) {
			throw $e;
			wc_add_notice( 'There was an error reaching the payment service. Please try your purchase again', 'error' );
			wp_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		}

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return false;
		}

		$verify_response = wp_remote_retrieve_body( $request );

		if ( 'VERIFIED' === strtoupper( $verify_response ) ) {
			return true;
		}

		return false;
	}

	private function snap_handle_payment_request_error( $data ) {
		$response = $data->response;
		$order    = $data->order;

		wc_add_notice( "There was an error during payment. Please try your purchase again ($response->code)", 'error' );
		$payment_page = $order->get_checkout_payment_url();
		wp_redirect( $payment_page );
		exit;
	}

	private function snap_handle_payment_validation_request_error( $data ) {
		$response = $data->response;

		wc_add_notice( "There was an error validating your payment. Please contact support if you've been billed ($response->code)", 'error' );
		wp_redirect( wc_get_page_permalink( 'cart' ) );
		exit;
	}

	private function isSnapplifyPaymentSuccessful( $response ) {
		return ( 1 === (int) $response->success ) ? true : false;
	}

	private function getOrderFromSnapplifyReference( $reference_code ) {
		$order_details = explode( '_', $reference_code );
		$order_id      = (int) $order_details[0];
		$order         = wc_get_order( $order_id );

		return $order;
	}

	public function is_snap_available() {
		$is_snap_available = ( 'yes' === $this->enabled );

		if ( 'yes' !== $this->test_mode ) {
			return $is_snap_available;
		}

		if ( ! current_user_can( 'administrator' ) && ! current_user_can( 'shop_manager' ) ) {
			$is_snap_available = false;
			wc_clear_notices();
		}

		// are the required api credentials set up in the backend?
		if (
			false === $this->are_snap_prod_credentials_set()
			&& true === $this->is_snap_mode_production()
		) {
			$is_snap_available = false;
			SNAP_Helpers::snap_trigger_credentials_admin_error();
		}

		if (
			false === $this->are_snap_dev_credentials_set()
			&& false === $this->is_snap_mode_production()
		) {
			$is_snap_available = false;
			SNAP_Helpers::snap_trigger_credentials_admin_error();
		}

		// is the current country supported?
		if ( false === $this->is_snap_country_supported() ) {
			$is_snap_available = false;
			SNAP_Helpers::snap_trigger_supported_country_admin_error();
		}

		// is the current currency supported?
		if ( false === $this->is_snap_currency_supported() ) {
			$is_snap_available = false;
			SNAP_Helpers::snap_trigger_supported_currency_admin_error();
		}

		return $is_snap_available;
	}

	private function is_snap_mode_production() {
		 return ( $this->get_option( 'live_mode' ) === 'yes' ) ? true : false;
	}

	private function are_snap_prod_credentials_set() {
		if ( true === $this->is_snap_mode_production() ) {
			if ( '' === $this->get_option( 'api_key' ) ) {
				return false;
			}
			if ( '' === $this->get_option( 'api_secret' ) ) {
				return false;
			}

			return true;
		}
		return false;
	}

	private function are_snap_dev_credentials_set() {
		if ( false === $this->is_snap_mode_production() ) {
			if ( '' === $this->get_option( 'sandbox_api_key' ) ) {
				return false;
			}
			if ( '' === $this->get_option( 'sandbox_api_secret' ) ) {
				return false;
			}

			return true;
		}
		return false;
	}

	private function is_snap_country_supported() {
		if ( in_array( $this->store_location['country'], $this->supported_countries ) ) {
			return true;
		}

		return true;
	}

	private function is_snap_currency_supported() {
		if ( in_array( $this->store_currency, $this->supported_currencies ) ) {
			return true;
		}

		return true;
	}
}
