<?php
if (!defined('ABSPATH')) {
    exit;
}

function walleta_init_payment_gateway()
{
    function walleta_register_payment_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Walleta';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'walleta_register_payment_gateway');

    class WC_Gateway_Walleta extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'walleta';
            $this->icon = apply_filters(
                'woocommerce_walleta_icon',
                plugin_dir_url(WALLETA_PLUGIN_FILE) . 'assets/images/logo.png'
            );
            $this->has_fields = true;
            $this->method_title = __('والتا - درگاه پرداخت اقساطی', 'woocommerce');
            $this->method_description = __('تنظیمات درگاه پرداخت اقساطی والتا', 'woocommerce');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_receipt_' . $this->id, [$this, 'process_payment_request']);
            add_action('woocommerce_api_' . $this->id, [$this, 'process_payment_verify']);

            add_filter('woocommerce_checkout_fields', [$this, 'get_checkout_fields']);
            add_action('woocommerce_admin_order_data_after_billing_address',
                [$this, 'checkout_field_display_admin_order_meta'], 10, 1);
            add_action('woocommerce_after_checkout_validation', [$this, 'after_checkout_validation'], 10, 2);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'checkout_update_order_meta']);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('فعالسازی', 'woocommerce'),
                    'label' => __('فعالسازی درگاه والتا', 'woocommerce'),
                    'description' => __('برای فعالسازی درگاه باید چک باکس را تیک بزنید.', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => true,
                ],
                'title' => [
                    'title' => __('عنوان درگاه', 'woocommerce'),
                    'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده می‌شود.', 'woocommerce'),
                    'type' => 'text',
                    'default' => $this->method_title,
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('توضیحات درگاه', 'woocommerce'),
                    'description' => __('توضیحاتی که طی عملیات پرداخت نمایش داده خواهد شد.', 'woocommerce'),
                    'type' => 'text',
                    'default' => __('پرداخت اقساطی از طریق درگاه والتا', 'woocommerce'),
                    'desc_tip' => true,
                ],
                'merchant_code' => [
                    'title' => __('کد پذیرنده', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('کد پذیرنده دریافتی از والتا', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true
                ],
            ];
        }

        public function get_checkout_fields($fields)
        {
            $fields['billing']['billing_national_code'] = [
                'label' => __('کد ملی', 'woocommerce'),
                'placeholder' => '',
                'type' => 'tel',
                'required' => true,
                'class' => ['form-row-wide'],
                'clear' => true,
                'priority' => 90,
            ];

            $fields['billing']['billing_mobile'] = [
                'label' => __('موبایل', 'woocommerce'),
                'placeholder' => '',
                'type' => 'tel',
                'required' => true,
                'class' => ['form-row-wide'],
                'clear' => true,
                'priority' => 90,
            ];

            return $fields;
        }

        public function after_checkout_validation($data, $errors)
        {
            if (!isset($_POST['billing_national_code'])) {
                $errors->add('validation', __('<strong>کد ملی</strong> یک گزینه الزامی است.', 'woocommerce'));
            } elseif (!Walleta_Validation::nationalCode($_POST['billing_national_code'])) {
                $errors->add('validation', __('<strong>کد ملی</strong> خود را صحیح وارد کنید.', 'woocommerce'));
            }

            if (!isset($_POST['billing_mobile'])) {
                $errors->add('validation', __('<strong>شماره موبایل</strong> یک گزینه الزامی است.', 'woocommerce'));
            } elseif (!Walleta_Validation::mobile($_POST['billing_mobile'])) {
                $errors->add('validation', __('<strong>شماره موبایل</strong> خود را صحیح وارد کنید.', 'woocommerce'));
            }
        }

        public function checkout_field_display_admin_order_meta($order)
        {
            $national_code = $order->get_meta('billing_national_code');
            if ($national_code) {
                echo '<p><strong>' . __('کد ملی', 'woocommerce') . ':</strong> '
                    . $order->get_meta('billing_national_code') . '</p>';
            }

            $mobile = $order->get_meta('billing_mobile');
            if ($mobile) {
                echo '<p><strong>' . __('موبایل', 'woocommerce') . ':</strong> '
                    . $order->get_meta('billing_mobile') . '</p>';
            }
        }

        public function checkout_update_order_meta($order_id)
        {
            if (!empty($_POST['billing_national_code'])) {
                update_post_meta($order_id, 'billing_national_code',
                    sanitize_text_field($_POST['billing_national_code']));
            }

            if (!empty($_POST['billing_mobile'])) {
                update_post_meta($order_id, 'billing_mobile', sanitize_text_field($_POST['billing_mobile']));
            }
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            if ($order->get_total() > 0) {
                $order->update_status(
                    apply_filters('woocommerce_walleta_process_payment_order_status', 'pending', $order),
                    __('در انتظار پرداخت.', 'woocommerce')
                );
            } else {
                $order->payment_complete();
            }

            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true),
            ];
        }

        public function process_payment_request($order_id)
        {
            try {
                $response = (new Walleta_Http_Request())->post(
                    'https://cpg.walleta.ir/payment/request.json',
                    $this->get_payment_request_params($order_id)
                );

                if ($response->isSuccess()) {
                    update_post_meta($order_id, 'walleta_token', $response->getData('token'));
                    $redirectUrl = 'https://cpg.walleta.ir/ticket/' . $response->getData('token');
                    $this->redirect($redirectUrl);
                }

                $error = $this->get_walleta_errors($response);
            } catch (\Exception $ex) {
                $error = __('در هنگام اتصال به درگاه والتا خطایی رخ داده است.', 'woocommerce');
            }

            $this->set_message($order_id, $error);
        }

        public function process_payment_verify()
        {
            $order_id = !empty($_GET['wc_order']) ? (int)$_GET['wc_order'] : null;
            $payment_status = !empty($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
            $payment_token = !empty($_GET['token']) ? sanitize_text_field($_GET['token']) : null;

            if (!$order_id) {
                wc_add_notice(__('امکان پرداخت سفارش وجود ندارد.', 'woocommerce'), 'error');
                $this->redirect(wc_get_checkout_url());
            }

            $order = wc_get_order($order_id);
            $redirect_url = $order->get_view_order_url();

            if ($order->is_paid()) {
                $this->redirect($redirect_url);
            }

            if (!$order->needs_payment()) {
                $this->set_message($order_id, __('امکان پرداخت سفارش وجود ندارد.', 'woocommerce'));
                $this->redirect($redirect_url);
            }

            if ($order->get_meta('walleta_token') !== $payment_token) {
                wc_add_notice(__('توکن پرداخت معتبر نیست.', 'woocommerce'), 'error');
                $this->redirect($redirect_url);
            }

            if ($payment_status !== 'success') {
                $this->set_message($order_id, __('عملیات پرداخت لغو شده است.', 'woocommerce'));
                $this->redirect($redirect_url);
            }

            try {
                $response = (new Walleta_Http_Request())->post(
                    'https://cpg.walleta.ir/payment/verify.json',
                    $this->get_payment_verify_params($order, $payment_token)
                );

                if (!$response->isSuccess()) {
                    $this->set_message($order_id, $this->get_walleta_errors($response));
                    $this->redirect($redirect_url);
                }

                if ($response->getData('is_paid') !== true) {
                    $this->set_message($order_id, __('خطا در تایید تراکنش پرداخت.', 'woocommerce'));
                    $this->redirect($redirect_url);
                }

                $order->payment_complete();

                $this->set_message($order_id, __('پرداخت با موفقیت انجام شد.', 'woocommerce'), 'success');
                $this->redirect($this->get_return_url($order));
            } catch (Exception $ex) {
                $this->set_message($order_id, __('در هنگام اتصال به درگاه والتا خطایی رخ داده است.', 'woocommerce'));
            }

            $this->redirect($redirect_url);
        }

        protected function get_payment_request_params($order_id)
        {
            $order = wc_get_order($order_id);
            $currency = $order->get_currency();

            $data = [
                'merchant_code' => $this->get_option('merchant_code'),
                'invoice_reference' => $order->get_order_number(),
                'invoice_date' => (string)$order->get_date_created(),
                'invoice_amount' => $this->convert_money($currency, $order->get_total()),
                'payer_first_name' => $order->get_billing_first_name(),
                'payer_last_name' => $order->get_billing_last_name(),
                'payer_national_code' => $order->get_meta('billing_national_code'),
                'payer_mobile' => $order->get_meta('billing_mobile'),
                'callback_url' => add_query_arg('wc_order', $order->get_id(), WC()->api_request_url($this->id)),
                'description' => 'پرداخت سفارش #' . $order->get_id(),
                'items' => [],
            ];

            foreach ($order->get_items() as $product) {
                $productData = $product->get_data();

                $data['items'][] = [
                    'reference' => $productData['product_id'],
                    'name' => $productData['name'],
                    'quantity' => $productData['quantity'],
                    'unit_price' => $this->convert_money($currency, $product['subtotal'] / $productData['quantity']),
                    'unit_discount' => 0,
                    'unit_tax_amount' => $product['subtotal_tax'] > 0 ?
                        $this->convert_money($currency, $product['subtotal_tax'] / $product['quantity']) : 0,
                    'total_amount' => $this->convert_money($currency, $product['total'] + $product['total_tax']),
                ];
            }

            if ($order->get_shipping_total()) {
                $shippingCost = $this->convert_money($currency, $order->get_shipping_total());
                $shippingTax = $this->convert_money($currency, $order->get_shipping_tax());

                $data['items'][] = [
                    'name' => 'هزینه ارسال',
                    'quantity' => 1,
                    'unit_price' => $shippingCost,
                    'unit_discount' => 0,
                    'unit_tax_amount' => $shippingTax,
                    'total_amount' => $shippingCost + $shippingTax,
                ];
            }

            return $data;
        }

        protected function get_payment_verify_params($order, $payment_token)
        {
            $currency = $order->get_currency();
            $totalAmount = $this->convert_money($currency, $order->get_total());

            return [
                'merchant_code' => $this->get_option('merchant_code'),
                'token' => $payment_token,
                'invoice_reference' => $order->get_order_number(),
                'invoice_amount' => $totalAmount,
            ];
        }

        protected function convert_money($currency, $amount)
        {
            $currency = strtoupper($currency);

            if ($currency === 'IRR') {
                $amount /= 10;
            } elseif ($currency === 'IRHR') {
                $amount *= 100;
            } elseif ($currency === 'IRHT') {
                $amount *= 1000;
            }

            return (int)$amount;
        }

        protected function set_message($order_id, $note, $notice_type = 'error')
        {
            $order = wc_get_order($order_id);
            $order->add_order_note($note);
            wc_add_notice($note, $notice_type);
        }

        protected function get_walleta_errors($response)
        {
            $error = $response->getErrorMessage();

            if ($response->getErrorType() === 'validation_error') {
                foreach ($response->getValidationErrors() as $validationError) {
                    $error .= '<br>- ' . $validationError;
                }
            }

            return $error;
        }

        protected function redirect($url)
        {
            wp_redirect($url);
            exit;
        }
    }
}

add_action('plugins_loaded', 'walleta_init_payment_gateway');
