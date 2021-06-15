<?php
/*
Plugin Name: افزونه درگاه پرداخت اعتباری والتا
Plugin URI: https://github.com/walletaco/walleta-woocommerce
Description: افزونه درگاه پرداخت اعتباری (اقساطی) والتا برای سیستم فروشگاه‌ساز ووکامرس
Author: Mahmood Dehghani
Version: 1.3.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WALLETA_PLUGIN_FILE')) {
    define('WALLETA_PLUGIN_FILE', __FILE__);
}

add_action('woocommerce_loaded', function () {
    require_once('include/class-persian-text.php');
    require_once('include/class-validation.php');
    require_once('include/client/class-response.php');
    require_once('include/client/class-http-request.php');
    include_once 'class-wc-payment-gateway-walleta.php';
});
