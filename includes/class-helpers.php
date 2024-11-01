<?php

class SNAP_Helpers {

	public static function make_request_payment_response_object( $data = array() ) {
		$vars = array(
			'token',
			'redirectUrl',
			'code',
			'message',
		);

		$object = new ArrayObject();
		foreach ( $data as $attribute => $value ) {
			if ( in_array( $attribute, $vars ) ) {
				$object->{$attribute} = $value;
			}
		}

		return $object;
	}

	public static function make_submit_payment_response_object( $data = array() ) {
		$vars = array(
			'client',
			'token',
			'success',
			'referenceCode',
			'errorCode',
			'errorMessage',
		);

		$object = new ArrayObject();
		foreach ( $data as $attribute => $value ) {
			if ( in_array( $attribute, $vars ) ) {
				$object->{$attribute} = $value;
			}
		}

		return $object;
	}

	public static function is_snap_payment_data_valid( $payment_esponse, $validation_response ) {
		if ( $payment_esponse->referenceCode !== $validation_response->referenceCode ) {
			return false;
		}
		if ( $payment_esponse->token !== $validation_response->authorisationCode ) {
			return false;
		}

		return true;
	}

	public static function snap_trigger_credentials_admin_error() {
		$admin_notice = new WC_Admin_Notices();
		$admin_notice->add_custom_notice( 'snapplify_cred_error', '<div>Error: The Snapplify Payment gateway is missing some credentials required to communicate with Snapplify. Please double check them.</div>' );
		$admin_notice->output_custom_notices();
	}

	public static function snap_trigger_supported_country_admin_error() {
		$admin_notice = new WC_Admin_Notices();
		$admin_notice->add_custom_notice( 'snapplify_country_error', '<div>Error: The Snapplify Payment gateway is not supported for the country that your store is set to.</div>' );
		$admin_notice->output_custom_notices();
	}

	public static function snap_trigger_supported_currency_admin_error() {
		$admin_notice = new WC_Admin_Notices();
		$admin_notice->add_custom_notice( 'snapplify_currency_error', '<div>Error: The Snapplify Payment gateway is not supported for the currency that your store is set to accept.</div>' );
		$admin_notice->output_custom_notices();
	}
}
