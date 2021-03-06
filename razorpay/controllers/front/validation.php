<?php

class RazorpayValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {

        $key_id            = Configuration::get('RAZORPAY_KEY_ID');
        $key_secret        = Configuration::get('RAZORPAY_KEY_SECRET');
        $razorpay_payment_id = $_REQUEST['razorpay_payment_id'];
        $cart_id        = $_REQUEST['merchant_order_id'];

        $cart = new Cart($cart_id);

        $razorpay = new Razorpay();

        $amount = number_format($cart->getOrderTotal(true, 3), 2, '.', '')*100;

        $success = false;
        $error = "";

        try {
            $url = 'https://api.razorpay.com/v1/payments/'.$razorpay_payment_id.'/capture';
            $fields_string="amount=$amount";

            //cURL Request
            $ch = curl_init();

            //set the url, number of POST vars, POST data
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_USERPWD, $key_id . ":" . $key_secret);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_POST, 1);
            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);

            //execute post
            $result = curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);


            if($result === false) {
                $success = false;
                $error = 'Curl error: ' . curl_error($ch);
            }
            else {
                $response_array = Tools::jsonDecode($result, true);
                //Check success response
                if($http_status === 200 and isset($response_array['error']) === false){
                    $success = true;
                }
                else {
                    $success = false;

                    if(!empty($response_array['error']['code'])) {
                        $error = $response_array['error']['code'].":".$response_array['error']['description'];
                    }
                    else {
                        $error = "RAZORPAY_ERROR: Invalid Response <br/>".$result;
                    }
                }
            }

            //close connection
            curl_close($ch);
        }
        catch (Exception $e) {
            $success = false;
            $error ="PRESTASHOP_ERROR: Request to Razorpay Failed";
        }

        if ($success == true) {
            $customer = new Customer($cart->id_customer);
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $razorpay->validateOrder($cart_id, _PS_OS_PAYMENT_, $total, $razorpay->displayName,  '', array(), NULL, false, $customer->secure_key);

            Logger::addLog("Payment Successful for Order#".$cart_id.". Razorpay payment id:".$razorpay_payment_id, 1);

            $query = http_build_query(array(
                'controller'    =>  'order-confirmation',
                'id_cart'       =>  (int) $cart->id,
                'id_module'     =>  (int) $this->module->id,
                'id_order'      =>  $razorpay->currentOrder
            ), '', '&');

            $url = 'index.php?' . $query;

            Tools::redirect($url);
        } else {
            Logger::addLog("Payment Failed for Order# ".$cart_id.". Razorpay payment id:".$razorpay_payment_id. "Error: ".$error, 4);
            echo 'Error! Please contact the seller directly for assistance.</br>';
            echo 'Order Id: '.$cart_id.'</br>';
            echo 'Razorpay Payment Id: '.$razorpay_payment_id.'</br>';
            echo 'Error: '.$error.'</br>';
        }
    }
}
