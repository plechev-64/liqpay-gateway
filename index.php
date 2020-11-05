<?php

add_action( 'rcl_payments_gateway_init', 'rcl_add_liqpay_gateway' );
function rcl_add_liqpay_gateway() {
	rcl_gateway_register( 'liqpay', 'Rcl_Liqpay_Payment' );
}

class Rcl_Liqpay_Payment extends Rcl_Gateway_Core {
	function __construct() {
		parent::__construct( array(
			'request'	 => 'signature',
			'name'		 => 'Liqpay',
			'submit'	 => __( 'Оплатить через Liqpay' ),
			'image'		 => rcl_addon_url( 'icon.jpg', __FILE__ )
		) );
	}

	function get_options() {

		return array(
			array(
				'type'	 => 'text',
				'slug'	 => 'lq_public_key',
				'title'	 => __( 'Public Key', 'rcl-liqpay' )
			),
			array(
				'type'	 => 'password',
				'slug'	 => 'lq_private_key',
				'title'	 => __( 'Private Key', 'rcl-liqpay' )
			),
			array(
				'type'	 => 'select',
				'slug'	 => 'lq_mode',
				'title'	 => __( 'Режим работы', 'rcl-liqpay' ),
				'values' => array(
					__( 'Рабочий', 'rcl-liqpay' ),
					__( 'Тестовый', 'rcl-liqpay' )
				)
			)
		);
	}

	function get_form( $data ) {

		$private_key = rcl_get_commerce_option( 'lq_private_key' );

		$params = array(
			'action'		 => 'pay',
			'amount'		 => $data->pay_summ,
			'currency'		 => $data->currency,
			'description'	 => $data->description,
			'order_id'		 => $data->pay_id,
			'public_key'	 => rcl_get_commerce_option( 'lq_public_key' ),
			'sandbox'		 => rcl_get_commerce_option( 'lq_mode', 0 ),
			'server_url'	 => get_permalink( $data->page_result ),
			'result_url'	 => get_permalink( $data->page_success ),
			'customer'		 => $data->user_id,
			'info'			 => base64_encode( json_encode( array(
				'baggage_data'	 => $data->baggage_data,
				'pay_type'		 => $data->pay_type
			) ) ),
			'version'		 => '3'
		);

		$jsondata = base64_encode( json_encode( $params ) );

		return parent::construct_form( [
				'action' => 'https://www.liqpay.ua/api/3/checkout',
				'fields' => array(
					'data'		 => $jsondata,
					'signature'	 => base64_encode( sha1( $private_key . $jsondata . $private_key, 1 ) )
				)
			] );
	}

	function result( $data ) {

		$private_key = rcl_get_commerce_option( 'lq_private_key' );

		$requestdata = json_decode( base64_decode( $_REQUEST["data"] ) );

		if ( $requestdata->status != 'success' ) {
			return false;
		}

		$sign = base64_encode( sha1(
				$private_key .
				$_REQUEST["data"] .
				$private_key
				, 1 ) );

		if ( $sign != $_REQUEST["signature"] ) {
			rcl_mail_payment_error( $sign );
			echo "bad sign\n";
			exit();
		}

		$info = json_decode( base64_decode( $requestdata->info ) );

		if ( ! parent::get_payment( $requestdata->order_id ) ) {
			parent::insert_payment( array(
				'pay_id'		 => $requestdata->order_id,
				'pay_summ'		 => $requestdata->amount,
				'user_id'		 => $requestdata->customer,
				'pay_type'		 => $info->pay_type,
				'baggage_data'	 => $info->baggage_data
			) );
		}

		echo "OK" . $requestdata->order_id . "\n";
		exit();
	}

	function success( $process ) {

		$requestdata = json_decode( base64_decode( $_REQUEST["data"] ) );

		if ( parent::get_payment( $requestdata->order_id ) ) {
			wp_redirect( get_permalink( $process->page_successfully ) );
			exit;
		} else {
			wp_die( 'Платеж не найден в базе данных!' );
		}
	}

}
