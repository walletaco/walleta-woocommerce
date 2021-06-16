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
        const PAYMENT_REQUEST_URL = 'https://cpg.walleta.ir/payment/request.json';
        const PAYMENT_VERIFY_URL = 'https://cpg.walleta.ir/payment/verify.json';
        const PAYMENT_GATEWAY_URL = 'https://cpg.walleta.ir/ticket/';

        public function __construct()
        {
            $this->id = 'walleta';
            $this->icon = apply_filters(
                'woocommerce_walleta_icon',
                plugin_dir_url(WALLETA_PLUGIN_FILE) . 'assets/images/logo.png'
            );
            $this->has_fields = true;
            $this->method_title = __('والتا - درگاه پرداخت اعتباری (‌اقساطی)', 'walleta');
            $this->method_description = __('تنظیمات درگاه پرداخت اعتباری والتا', 'walleta');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_' . $this->id, [$this, 'process_payment_verify']);

            add_action('woocommerce_admin_order_data_after_billing_address',
                [$this, 'checkout_field_display_admin_order_meta'], 10, 1);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'checkout_update_order_meta']);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('فعالسازی', 'walleta'),
                    'label' => __('فعالسازی درگاه والتا', 'walleta'),
                    'description' => __('برای فعالسازی درگاه باید چک باکس را تیک بزنید.', 'walleta'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => true,
                ],
                'title' => [
                    'title' => __('عنوان درگاه', 'walleta'),
                    'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده می‌شود.', 'walleta'),
                    'type' => 'text',
                    'default' => $this->method_title,
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('توضیحات درگاه', 'walleta'),
                    'description' => __('توضیحاتی که طی عملیات پرداخت نمایش داده خواهد شد.', 'walleta'),
                    'type' => 'text',
                    'default' => __('پرداخت اقساطی از طریق درگاه والتا', 'walleta'),
                    'desc_tip' => true,
                ],
                'merchant_code' => [
                    'title' => __('کد پذیرنده', 'walleta'),
                    'type' => 'text',
                    'description' => __('کد پذیرنده دریافتی از والتا', 'walleta'),
                    'default' => '',
                    'desc_tip' => true
                ],
            ];
        }

        public function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }

            echo sprintf(
                '<fieldset id="wc-%s-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
                    <div class="form-row form-row-first">
                        <label>موبایل <span class="required">*</span></label>
                        <input name="payer_mobile" value="%s" type="text" autocomplete="off" placeholder="شماره موبایل" maxlength="11">
                    </div>
                    <div class="form-row form-row-last">
                        <label>کد ملی <span class="required">*</span></label>
                        <input name="payer_national_code" value="%s" type="text" autocomplete="off" placeholder="کد ملی" maxlength="10">
                    </div>
                    <div class="clear"></div>
                </fieldset>',
                esc_attr($this->id),
                $this->getPayerMobile(),
                $this->getPayerNationalCode()
            );
        }

        public function validate_fields()
        {
            $isValid = true;

            $mobile = $this->getPayerMobile();
            $nationalCode = $this->getPayerNationalCode();

            WC()->session->set('payer_mobile', $mobile);
            WC()->session->set('payer_national_code', $nationalCode);

            if (!$mobile) {
                wc_add_notice(__('<strong>شماره موبایل</strong> یک گزینه الزامی است.', 'walleta'), 'error');
                $isValid = false;
            } elseif (!Walleta_Validation::mobile($mobile)) {
                wc_add_notice(__('<strong>شماره موبایل</strong> خود را صحیح وارد کنید.', 'walleta'), 'error');
                $isValid = false;
            }

            if (!$nationalCode) {
                wc_add_notice(__('<strong>کد ملی</strong> یک گزینه الزامی است.', 'walleta'), 'error');
                $isValid = false;
            } elseif (!Walleta_Validation::nationalCode($nationalCode)) {
                wc_add_notice(__('<strong>کد ملی</strong> خود را صحیح وارد کنید.', 'walleta'), 'error');
                $isValid = false;
            }

            return $isValid;
        }

        public function checkout_field_display_admin_order_meta($order)
        {
            $nationalCode = $order->get_meta('payer_national_code');
            if ($nationalCode) {
                echo sprintf(
                    '<p><strong>%s</strong> %s</p>',
                    __('کد ملی خریدار:', 'walleta'),
                    $nationalCode
                );
            }

            $mobile = $order->get_meta('payer_mobile');
            if ($mobile) {
                echo sprintf(
                    '<p><strong>%s</strong> <a href="tel:%s">%s</a></p>',
                    __('موبایل خریدار:', 'walleta'),
                    $mobile,
                    $mobile
                );
            }
        }

        public function checkout_update_order_meta($order_id)
        {
            $nationalCode = $this->getPayerNationalCode();
            if ($nationalCode) {
                update_post_meta($order_id, 'payer_national_code', $nationalCode);
            }

            $mobile = $this->getPayerMobile();
            if ($mobile) {
                update_post_meta($order_id, 'payer_mobile', $mobile);
            }
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            try {
                $response = (new Walleta_Http_Request())
                    ->post(self::PAYMENT_REQUEST_URL, $this->get_payment_request_params($order_id));

                if (!$response->isSuccess()) {
                    wc_add_notice($this->get_walleta_errors($response), 'error');
                    return false;
                }

                $order->update_status('pending', __('در انتظار پرداخت', 'walleta'));
                update_post_meta($order_id, 'walleta_token', $response->getData('token'));

                WC()->cart->empty_cart();

                return [
                    'result' => 'success',
                    'redirect' => self::PAYMENT_GATEWAY_URL . $response->getData('token'),
                ];

            } catch (\Exception $ex) {
                wc_add_notice(__('در هنگام اتصال به درگاه والتا خطایی رخ داده است.', 'walleta'), 'error');
            }

            return false;
        }

        public function process_payment_verify()
        {
            $order_id = !empty($_GET['wc_order']) ? (int)$_GET['wc_order'] : null;
            $payment_status = !empty($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
            $payment_token = !empty($_GET['token']) ? sanitize_text_field($_GET['token']) : null;

            if (!$order_id) {
                $this->redirect(wc_get_checkout_url());
            }

            $order = wc_get_order($order_id);
            $redirect_url = $order->get_view_order_url();

            if ($order->is_paid() || !$order->needs_payment()) {
                $this->redirect($redirect_url);
            }

            if ($order->get_meta('walleta_token') !== $payment_token) {
                wc_add_notice(__('توکن پرداخت معتبر نیست.', 'walleta'), 'error');
                $this->redirect($redirect_url);
            }

            if ($payment_status !== 'success') {
                wc_add_notice(__('عملیات پرداخت لغو شده است.', 'walleta'), 'error');
                $order->update_status('cancelled', __('پرداخت لغو شد', 'walleta'));
                $this->redirect($redirect_url);
            }

            try {
                $response = (new Walleta_Http_Request())
                    ->post(self::PAYMENT_VERIFY_URL, $this->get_payment_verify_params($order, $payment_token));

                if (!$response->isSuccess()) {
                    $this->set_message($order_id, $this->get_walleta_errors($response));
                    $this->redirect($redirect_url);
                }

                if ($response->getData('is_paid') !== true) {
                    wc_add_notice(__('خطا در تایید تراکنش پرداخت.', 'walleta'), 'error');
                    $order->update_status('failed', __('پرداخت تایید نشد', 'walleta'));
                    $this->redirect($redirect_url);
                }

                $order->payment_complete();

                wc_add_notice(__('پرداخت با موفقیت انجام شد.', 'walleta'), 'success');
                $this->redirect($this->get_return_url($order));
            } catch (Exception $ex) {
                $this->set_message($order_id, __('در هنگام اتصال به درگاه والتا خطایی رخ داده است.', 'walleta'));
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
                'payer_national_code' => $order->get_meta('payer_national_code'),
                'payer_mobile' => $order->get_meta('payer_mobile'),
                'callback_url' => add_query_arg('wc_order', $order->get_id(), WC()->api_request_url($this->id)),
                'description' => 'پرداخت سفارش #' . $order->get_id(),
                'items' => [],
            ];

            if (!$data['payer_national_code']) {
                $data['payer_national_code'] = $this->getPayerNationalCode();
            }

            if (!$data['payer_mobile']) {
                $data['payer_mobile'] = $this->getPayerMobile();
            }

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

        protected function getPayerNationalCode()
        {
            if (!empty($_POST['payer_national_code'])) {
                return Walleta_Persian_Text::toEnglishNumber(sanitize_text_field($_POST['payer_national_code']));
            }

            return WC()->session->get('payer_national_code', '');
        }

        protected function getPayerMobile()
        {
            if (!empty($_POST['payer_mobile'])) {
                return Walleta_Persian_Text::toEnglishNumber(sanitize_text_field($_POST['payer_mobile']));
            }

            return WC()->session->get('payer_mobile', '');
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
