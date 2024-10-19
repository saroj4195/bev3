<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\QuickPaymentLink; //class name from model
use DB;

require('razorpay/razorpay-php/Razorpay.php');
require('vendor/autoload.php');
use Razorpay\Api\Api;

use App\TempMailCode;
use App\BookingjiniPaymentgateway;
use App\OnlineTransactionDetail;
use App\MasterHotelRatePlan;
use App\HotelInformation;
use App\RefundTransactionDetails;
use App\PaymentGetway;
use App\Invoice;
use App\Refund;
use App\Http\Controllers\Checksum;



class UnpaidBookingController extends Controller
{
        protected $checksum;

        public function __construct(Checksum $checksum)
        {
            $this->checksum = $checksum;
        }

        public function payuUnpaidBooking($tnx_id)
        {
                $invoice_id = substr($tnx_id, 6);
                $invoice_id = (int)$invoice_id;
                $company_details = Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', 'kernel.hotels_table.hotel_id')
                        ->select('kernel.hotels_table.company_id', 'kernel.hotels_table.hotel_id')
                        ->where('invoice_table.invoice_id', $invoice_id)
                        ->first();
                $hotel_id = $company_details->hotel_id;
                $refund_details = Refund::where('booking_id', $invoice_id)->first();
                // $txn_details = OnlineTransactionDetail::where('invoice_id', $invoice_id)->first();
                // $paymentid = $txn_details['payment_id'];
                // $txn_id = $txn_details['transaction_id'];
                $company_id     = $company_details->company_id;
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('hotel_id', '=', $hotel_id)
                        ->where('is_active', '=', 1)
                        ->where('provider_name', '=', 'hdfc_payu')
                        ->first();

                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('company_id', '=', $company_id)
                                ->where('provider_name', '=', 'hdfc_payu')
                                ->first();
                }
                if ($getPaymentgateway_key) {
                        $credentials = json_decode($getPaymentgateway_key->credentials);
                        $key = $credentials[0]->key;
                        $salt = $credentials[0]->salt;
                } else {
                        $key = 'HpCvAH';
                        $salt = 'sHFyCrYD';
                }
                // $var1 = QuickPaymentLink::where('id', $statusid)->select('*')->first();
                $url = 'https://info.payu.in/merchant/postservice.php?form=2';
                $hash_value = $key . '|verify_payment|' . $tnx_id . '|' . $salt;
                $hash = hash('sha512', $hash_value);
                $post = array('key' => $key, 'command' => 'verify_payment', 'hash' => $hash, 'var1' => $tnx_id);
                // dd($hash_value,$hash,$post);
                //  Initiate curl
                $ch = curl_init();
                // Disable SSL verification
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                // Will return the response, if false it print the response
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // Set the url
                curl_setopt($ch, CURLOPT_URL, $url);
                //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: '. $key));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                // Execute
                $result = curl_exec($ch);
                // Closing
                curl_close($ch);
                $data = json_decode($result);
                if($data){
                        $tr_id = $tnx_id;
                        $payment_status = $data->transaction_details->$tr_id->status;
                        if ($payment_status == "success" && !empty($refund_details)) {
                                $txn_details = Refund::where('booking_id', $invoice_id)->update(['payment_id' => $data->transaction_details->$tr_id->mihpayid,'transaction_id' => $data->transaction_details->$tr_id->txnid]);
                                return response()->json(array('status' => 2, 'msg' => 'This transaction eligible for refund'));
                        } elseif ($payment_status == "success" && empty($refund_details)) {
                                return response()->json(array('status' => 1, 'msg' => 'This transaction eligible for success'));
                        } else {
                                return response()->json(array('status' => 3, 'msg' => 'payment not received for this transaction'));
                        }
                }
                else {
                        return response()->json(array('status' => 0, 'msg' => 'Sorry! Please try again'));
                }
        }

        /*-----------------------------------   REFUND FROM Payu (START) ----------------------------------*/

        function payURefund($tnx_id)
        {
                $invoice_id = substr($tnx_id, 6);
                $invoice_id = (int)$invoice_id;

                $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
                $hotel_id = $invoice_details->hotel_id;
                $paid_amount = $invoice_details->paid_amount;
                $txn_details = Refund::where('booking_id', $invoice_id)->first();
                $paymentid = $txn_details->payment_id;
                $txn_id = $txn_details->transaction_id;
                $command = 'cancel_refund_transaction';
                $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls['company_id'];
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('provider_name', 'hdfc_payu')
                        ->where('hotel_id', '=', $hotel_id)
                        ->first();

                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('provider_name', 'hdfc_payu')
                                ->where('company_id', '=', $company_id)
                                ->first();
                }
                if($getPaymentgateway_key){
                        $credentials = json_decode($getPaymentgateway_key->credentials);
                        $salt = $credentials->salt;
                        $key = $credentials->key;
                }
                else{
                        $key = 'HpCvAH';
                        $salt = 'sHFyCrYD';
                }

                $hashData = $key . '|' . $command . '|' . $paymentid . '|' . $salt ;

                if (strlen($hashData) > 0) {
                        $secureHash = hash('sha512', $hashData);
                }

                $curl = curl_init();
               
                curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://info.payu.in/merchant/postservice?form=2',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => 'key=' . $key . '&command=' . $command . '&var1=' . $paymentid . '&var2=' . $txn_id . '&var3=' . $paid_amount . '&hash=' .$secureHash. '',
                        CURLOPT_HTTPHEADER => array(
                                'Accept: application/json',
                                'Content-Type: application/x-www-form-urlencoded'
                        ),
                ));

                $response = curl_exec($curl);
                curl_close($curl);
                if (!empty($response)) {
                        $response = json_decode($response);
                        $status = isset($response->status) ? $response->status : $response['status'];
                        $res = isset( $response->msg) ?  $response->msg :$response['msg'];
                        if ($status == 1) {
                                $refund_array = [
                                        'hotel_id' => $hotel_id,
                                        'transaction_id' =>  $txn_id,
                                        "provider_name" => 'hdfc_payu',
                                        'refund_status'  => 1,
                                        'refund_amount'  => $paid_amount
                                ];
                                $insert_cancel_data = RefundTransactionDetails::insert($refund_array);
                                return response()->json(array('status' => 1, 'msg' => 'Refund initiated'));
                        } else {
                                $refund_array = [
                                        'hotel_id' => $hotel_id,
                                        'transaction_id' =>  $txn_id,
                                        "provider_name" => 'hdfc_payu',
                                        'refund_status'  => 0,
                                        'refund_amount'  => $paid_amount
                                ];
                                $insert_cancel_data = RefundTransactionDetails::insert($refund_array);
                                return response()->json(array('status' => 0, 'msg' => 'Refund failed'));
                        }
                }
        }

        /*-----------------------------------   REFUND FROM Payu (END) ----------------------------------*/

        
        /*-----------------------------------   PAYMENT CHECK FROM AIRPAY (START) ----------------------------------*/

        public function airpayUnpaidBooking($tnx_id)
        {
                $invoice_id = substr($tnx_id, 6);
                $invoice_id = (int)$invoice_id;

                $company_details = Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', 'kernel.hotels_table.hotel_id')
                        ->select('kernel.hotels_table.company_id', 'kernel.hotels_table.hotel_id')
                        ->where('invoice_table.invoice_id', $invoice_id)
                        ->first();
                $hotel_id = $company_details->hotel_id;
                $refund_details = Refund::where('booking_id', $invoice_id)->first();
                // $txn_details = OnlineTransactionDetail::where('invoice_id', $invoice_id)->first();
                $airpayId = $refund_details['payment_id'];
                $merchant_txnId = $refund_details['transaction_id'];
                $company_id     = $company_details->company_id;
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('hotel_id', '=', $hotel_id)
                        ->where('provider_name', '=', 'airpay')
                        ->first();

                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('company_id', '=', $company_id)
                                ->where('provider_name', '=', 'airpay')
                                ->first();
                }
                // if ($getPaymentgateway_key) {
                $credentials = json_decode($getPaymentgateway_key->credentials);

                $mid = $credentials[0]->m_id;
                $key = $credentials[0]->secret;
                $username = $credentials[0]->username;
                $password = $credentials[0]->password;

                $url = 'https://payments.airpay.co.in/order/verify.php';
                // $hash_value = $mid . '|' . $orderid . '|' . $uniqueid;
                $privatekey = $this->checksum->encrypt($username.":|:".$password, $key);
                // dd($privatekey);
                $post = array('mercid' => $mid, 'merchant_txnId' => $merchant_txnId, 'airpayId' => $airpayId, 'privatekey' => $privatekey);
                //  Initiate curl
                $ch = curl_init();
                // Disable SSL verification
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                // Will return the response, if false it print the response
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // Set the url
                curl_setopt($ch, CURLOPT_URL, $url);
                //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: '. $key));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                // Execute
                $result = curl_exec($ch);
                // Closing

                curl_close($ch);
                $data = simplexml_load_string($result, null , LIBXML_NOCDATA);               
                if ($data) {
                        $msg = (array) $data->TRANSACTION->MESSAGE;
                        if ($msg[0] == "Success" && !empty($refund_details)) {
                                return response()->json(array('status' => 1, 'msg' => 'This trascation eligible for refund'));
                        } elseif ($msg[0] == "Success" && empty($refund_details)) {
                                return response()->json(array('status' => 1, 'msg' => 'This trascation eligible for sucess'));
                        } else {
                                return response()->json(array('status' => 0, 'msg' => 'payment not recived for this transcation'));
                        }
                } else {
                        return response()->json(array('status' => 0, 'msg' => 'Sorry! Please try again'));
                }
        }

        /*-----------------------------------   PAYMENT CHECK FROM AIRPAY (END) ----------------------------------*/

        /*-----------------------------------   REFUND FROM AIRPAY (START) ----------------------------------*/
        public function airpayRefund($tnx_id)
        {
                $invoice_id = substr($tnx_id, 6);
                $invoice_id = (int)$invoice_id;
                $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
                $hotel_id = $invoice_details->hotel_id;
                $invoice_id = $invoice_details['invoice_id'];
                $paid_amount = $invoice_details['paid_amount'];
                $date = date('Y-m-d');
                $txn_details = Refund::where('booking_id', $invoice_id)->first();
                $paymentid = $txn_details['payment_id'];
                $txn_id = $txn_details['transaction_id'];
                $status = 'refund';
                $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls['company_id'];
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('provider_name', 'airpay')
                        ->where('hotel_id', '=', $hotel_id)
                        ->first();

                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('provider_name', 'airpay')
                                ->where('company_id', '=', $company_id)
                                ->first();
                }
                // dd($getPaymentgateway_key);
                $credentials = json_decode($getPaymentgateway_key->credentials, true);

                $mid = $credentials[0]['m_id'];
                $private_key = $credentials[0]['secret'];
                $username = $credentials[0]['username'];
                $password = $credentials[0]['password'];

                // $private_key = $paymentid . '|' . $paid_amount;

                $transactions =  array(
                        "processor_id" => $txn_id,
                        "amount" => $paid_amount

                );
                $transaction = json_encode($transactions);
                $all_data = $mid.'|'.$status.'|'.$transaction.'|'.$date;
                // dd($all_data);

                $key_data = $username.'~:~'.$password;
                if (strlen($key_data) > 0) {
                        $key = hash('SHA256', $key_data);
                }
             
                $hashData = $key.'@'.$all_data;

                if (strlen($hashData) > 0) {
                        $secureHash = hash('SHA256', $hashData);
                }
              

                $curl = curl_init();

                curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://kraken.airpay.co.in/airpay/api/refundtxn.ph',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => '{
                 "merchant_id":' . $mid . ',
                 "private_key": ' . $private_key . ',
                 "mode": ' . $status . ',
                 "checksum":' . $secureHash . ',
                 "transactions": [
                 {
                         ' . $transaction . '
                 }
                 ],
         
                 }',
                        CURLOPT_HTTPHEADER => array(
                                'Content-Type: application/json'
                        ),
                ));

                $response = curl_exec($curl);
                curl_close($curl);
              

                if (!empty($response)) {

                        $response = json_decode($response);
                        if ($response->sucess == 'true') {
                                $refund_array = [
                                        'hotel_id' => $hotel_id,
                                        'transaction_id' =>  $txn_id,
                                        'refund_status'  => 1,
                                        'refund_amount'  => $paid_amount,
                                        'provider_name'  => 'airpay'
                                ];
                                $insert_cancel_data = RefundTransactionDetails::insert($refund_array);
                                return response()->json(array('status' => 1, 'msg' => 'Refund initiated'));
                        } else {
                                if ($response->sucess == 'false') {
                                        $refund_array = [
                                                'hotel_id' => $hotel_id,
                                                'transaction_id' =>  $txn_id,
                                                'refund_status'  => 0,
                                                'refund_amount'  => $paid_amount,
                                                'provider_name'  => 'airpay'
                                        ];
                                        $insert_cancel_data = RefundTransactionDetails::insert($refund_array);
                                        return response()->json(array('status' => 0, 'msg' => 'Refund failed'));
                                }
                        }
                }
                else {
                        return response()->json(array('status' => 0, 'msg' => 'Sorry! Please try again'));
                }
        }

        /*-----------------------------------   REFUND FROM AIRPAY (END) ----------------------------------*/

        
        /*-----------------------------------   PAYMENT CHECK FROM RAZORPAY (START) ----------------------------------*/

        public function razorUnpaidBooking($tnx_id)
        {
                $invoice_id = substr($tnx_id, 6);
                $invoice_id = (int)$invoice_id;

                $company_details = Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', 'kernel.hotels_table.hotel_id')
                        ->select('kernel.hotels_table.company_id', 'kernel.hotels_table.hotel_id')
                        ->where('invoice_table.invoice_id', $invoice_id)
                        ->first();
                $hotel_id = $company_details->hotel_id;
                $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
                $paid_amount = $invoice_details->paid_amount;
                $refund_details = Refund::where('booking_id', $invoice_id)->first();
                // $txn_details = OnlineTransactionDetail::where('invoice_id', $invoice_id)->first();
                $paymentid = $refund_details['payment_id'];
                $orderid = $refund_details['transaction_id'];
                $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls['company_id'];
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('hotel_id', '=', $hotel_id)
                        ->where('provider_name', '=', 'razorpay')
                        ->first();

                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('company_id', '=', $company_id)
                                ->where('provider_name', '=', 'razorpay')
                                ->first();
                }
                $credentials = json_decode($getPaymentgateway_key->credentials);
                $key_id = $credentials[0]->key;
                $secret = $credentials[0]->secret;
                $api = new Api($key_id, $secret);

                $response = $api->payment->fetch($paymentid);
                if (!empty($response)) {
                        if ($response) {
                                $tr_id = $orderid;
                                $payment_status = $response['status'];
                                if ($payment_status == "captured" && !empty($refund_details)) {
                                        // $txn_details = Refund::where('booking_id', $invoice_id)->update(['payment_id' => $response->transaction_details->$tr_id->mihpayid, 'transcation_id' => $data->transaction_details->$tr_id->txnid]);
                                        return response()->json(array('status' => 2, 'msg' => 'This trascation eligible for refund'));
                                } elseif ($payment_status == "success" && empty($refund_details)) {
                                        return response()->json(array('status' => 1, 'msg' => 'This trascation eligible for sucess'));
                                } else {
                                        return response()->json(array('status' => 3, 'msg' => 'payment not recived for this transcation'));
                                }
                        } else {
                                return response()->json(array('status' => 0, 'msg' => 'Sorry! Please try again'));
                        }
                }
        }

        /*-----------------------------------   PAYMENT CHECK FROM RAZORPAY (END) ----------------------------------*/

        /*-----------------------------------   REFUND FROM RAZORPAY (START) ----------------------------------*/

        public function razorpayRefund($tnx_id)
        {
                $invoice_id = substr($tnx_id, 6);
                $invoice_id = (int)$invoice_id;
                $currency =  "INR";
                $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
                $hotel_id = $invoice_details->hotel_id;
                $invoice_id = $invoice_details['invoice_id'];
                $paid_amount = $invoice_details['paid_amount'];
                $txn_details = Refund::where('booking_id', $invoice_id)->first();
                $paymentid = $txn_details['payment_id'];
                $txn_id = $txn_details['transaction_id'];
                $status = 'refund';
                $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls['company_id'];
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('provider_name', 'razorpay')
                        ->where('hotel_id', '=', $hotel_id)
                        ->first();
                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('provider_name', 'razorpay')
                                ->where('company_id', '=', $company_id)
                                ->first();
                }
                $credentials = $getPaymentgateway_key->credentials;

                $credentials = json_decode($getPaymentgateway_key->credentials);
                $key_id = $credentials[0]->key;
                $secret = $credentials[0]->secret;

                $api = new Api($key_id, $secret);

                $response = $api->payment->fetch($paymentid)->refund(array("amount" => $paid_amount));
                if (!empty($response)) {
                        if ($response['entity'] == 'refund') {
                                $refund_array = [
                                        'hotel_id' => $hotel_id,
                                        'transaction_id' =>  $txn_id,
                                        'refund_status'  => 1,
                                        'refund_amount'  => $paid_amount,
                                        'provider_name'  => 'razorpay'
                                ];
                                $insert_cancel_data = RefundTransactionDetails::insert($refund_array);
                                return response()->json(array('status' => 1, 'msg' => 'Refund initiated'));
                        } else {
                                if ($response['entity'] != 'refund') {
                                        $refund_array = [
                                                'hotel_id'       => $hotel_id,
                                                'transaction_id' =>  $txn_id,
                                                'refund_status'  => 0,
                                                'refund_amount'  => $paid_amount,
                                                'provider_name'  => 'razorpay'
                                        ];
                                        $insert_cancel_data = RefundTransactionDetails::insert($refund_array);
                                        return response()->json(array('status' => 0, 'msg' => 'Refund failed'));
                                }
                        }
                }
        }

        /*-----------------------------------   REFUND FROM RAZORPAY (END) ----------------------------------*/


        public function stripeUnpaidBooking($txn_id)
        {
                $invoice_id = substr($txn_id, 6);

                $company_details = Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', 'kernel.hotels_table.hotel_id')
                        ->select('kernel.hotels_table.company_id', 'kernel.hotels_table.hotel_id')
                        ->where('invoice_table.invoice_id', $invoice_id)
                        ->first();
                $hotel_id = $company_details->hotel_id;
                $invoice_details = Invoice::where('hotel_id', $hotel_id)->first();
                $invoice_id = $invoice_details->invoice_id;
                $paid_amount = $invoice_details->paid_amount;
                $refund_details = Refund::where('booking_id', $invoice_id)->first();
                $txn_details = OnlineTransactionDetail::where('invoice_id', $invoice_id)->first();
                $paymentid = $txn_details['payment_id'];
                $orderid = $txn_details['transaction_id'];
                $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls['company_id'];
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('hotel_id', '=', $hotel_id)
                        ->where('provider_name', '=', 'stripe')
                        ->first();

                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('company_id', '=', $company_id)
                                ->where('provider_name', '=', 'stripe')
                                ->first();
                }
                $credentials = $getPaymentgateway_key->credentials;

                $credentials = json_decode($getPaymentgateway_key->credentials);
                $key_id = $credentials->key;
                //$secret = $credentials[0]->secret;     
                $stripe = new \Stripe\StripeClient(
                        $credentials[0]->key
                );
                $response = $stripe->refunds->create();
                if (!empty($response)) {

                        $response = json_decode($response);
                        // $tr_id = $orderid;
                        $payment_status = $response['status'];
                        if ($payment_status == "succeeded" && !empty($refund_details)) {

                                return response()->json(array('status' => 2, 'msg' => 'This transaction eligible for refund'));
                                //return $var1;
                        } elseif ($payment_status == "success" && empty($refund_details)) {
                                return response()->json(array('status' => 1, 'msg' => 'This transaction eligible for success'));
                                //return FALSE;
                        } else {
                                return response()->json(array('status' => 3, 'msg' => 'payment not received for this transaction'));
                        }
                }
        }
  
        /*-----------------------------------   REFUND FROM STRIPE (START) ----------------------------------*/
        public function StripeRefund($hotel_id, $provider_name)
        {
                $currency =  "INR";
                $invoice_details = Invoice::where('hotel_id', $hotel_id)->first();
                $invoice_id = $invoice_details['invoice_id'];
                $paid_amount = $invoice_details['paid_amount'];
                $txn_details = OnlineTransactionDetail::where('invoice_id', $invoice_id)->first();
                $paymentid = $txn_details['payment_id'];
                $txn_id = $txn_details['transaction_id'];
                $status = 'refund';
                $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls['company_id'];
                $getPaymentgateway_key = DB::table('paymentgateway_details')
                        ->select('provider_name', 'credentials')
                        ->where('provider_name', $provider_name)
                        ->where('hotel_id', '=', $hotel_id)
                        ->first();
                if (!$getPaymentgateway_key) {
                        $getPaymentgateway_key = DB::table('payment_gateway_details')
                                ->select('provider_name', 'credentials')
                                ->where('provider_name', $provider_name)
                                ->where('company_id', '=', $company_id)
                                ->first();
                }
                $credentials = $getPaymentgateway_key->credentials;

                $credentials = json_decode($getPaymentgateway_key->credentials);
                $key_id = $credentials[0]->key;
        }

        /*-----------------------------------   REFUND FROM STRIOE (END) ----------------------------------*/
        public function checkstatus(Request $request){
                $data = $request->all();

                $provider_name = strtolower($data['provider_name']);
               
                if($provider_name == 'payu'){
                $payu = $this->payuUnpaidBooking($data['booking_id']);
                return $payu;

                }elseif($provider_name == 'airpay'){
                        $airpay = $this->airpayUnpaidBooking($data['booking_id']);
                        return $airpay;

                }elseif($provider_name == 'razorpay'){
                        $razorpay = $this->razorUnpaidBooking($data['booking_id']);
                        return $razorpay;

                }else{
                        return response()->json(array('status' => 0,'message' =>'Please provide a valid provider name'));
                }
        }

        public function checkRefundStatus(Request $request){
                $data = $request->all();

                $provider_name = strtolower($data['provider_name']);
               
                if($provider_name == 'payu'){
                        $payu = $this->payURefund($data['booking_id']);
                        return $payu;

                }elseif($provider_name == 'airpay'){
                        $airpay = $this->airpayRefund($data['booking_id']);
                        return $airpay;

                }elseif($provider_name == 'razorpay'){
                        $razorpay = $this->razorpayRefund($data['booking_id']);
                        return $razorpay;

                }else{
                        return response()->json(array('status' => 0,'message' =>'Please provide a valid provider name'));
                }
        }
}
