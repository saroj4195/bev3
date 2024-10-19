<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\QuickPaymentLink; //class name from model
use DB;
use App\TempMailCode;
use App\BookingjiniPaymentgateway;
use App\OnlineTransactionDetail;
use App\MasterHotelRatePlan;
use App\HotelInformation;
use App\PaymentGetway;
use App\Invoice;

class QuickPaymentLinkController extends Controller
{
        private $rules = array(
                'name' => 'required  ',
                'email' => 'required ',
                'phone' => 'required ',
                'amount' => 'required ',
                'comment' => 'required ',
        );
        //Custom Error Messages
        private $messages = [
                'name.required' => 'The name field is required.',
                'email.required' => 'The email field is required.',
                'phone.required' => 'The phone field is required.',
                'amount.required' => 'The amount field is required.',
                'comment.required' => 'The comment field is required.',
        ];
        /**
         * quick payment Details
         * Create a new record of quick payment details.
         * @auther subhradip
         * @return quick payment details saving status
         *  function addNewPromo use for cerating new quick payment
         **/
        public function addQuickPayment(Request $request)
        {
                $quickpaymentlink = new QuickPaymentLink();
                $failure_message = 'Quick payment details saving failed';
                $validator = Validator::make($request->all(), $this->rules, $this->messages);
                if ($validator->fails()) {
                        return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
                }
                $data = $request->all();
                if (!empty($data)) {
                        $last_insert_id = $this->actionCreate($data);
                }
                if ($last_insert_id) {
                        $id = $this->actionStatuschange($last_insert_id);
                }
                if (!empty($id)) {
                        $this->actionPaymentstatusUpdate($id);
                        return response()->json(array('status' => 1, 'message' => "Payment link created successfully"));
                } else {
                        return response()->json(array('status' => 0, 'message' => $failure_message));
                }
        }
        public function actionCreate($model)
        {
                $quickpaymentlink = new QuickPaymentLink();
                $amount                 = $model['amount'];
                $name                   = $model['name'];
                $email                  = $model['email'];
                $phone                  = $model['phone'];
                $hotel_id               = $model['hotel_id'];
                $room_type              = $model['room_type'];
                $rate_plan              = $model['rate_plan'];
                $check_in               = $model['check_in'];
                $check_out              = $model['check_out'];
                $txnid                  = 'QPL' . $hotel_id . '' . strtotime("now");
                $hotel = DB::connection('bookingjini_kernel')->table('hotels_table')->where('hotel_id', $hotel_id)->select('hotel_name')->first();
                $hotel_name = $hotel->hotel_name;
                $pgname =    $model['pg_name'];
                if ($pgname == 'payu') {
                        $url                    = 'https://info.payu.in/merchant/postservice.php?form=2'; //URL
                        $value                  = array("amount" => $amount, "txnid" => $txnid, "productinfo" => "MailInvoice for" . $hotel_name, "firstname" => $name, "email" => $email, "phone" => $phone, "send_email_now" => "0");
                        $var1                   = json_encode($value); // var1
                        $hash_value             = 'HpCvAH|create_invoice|' . $var1 . '|sHFyCrYD';
                        $hash                   = hash('sha512', $hash_value);
                        $post                   = array('key' => 'HpCvAH', 'command' => 'create_invoice', 'hash' => $hash, 'var1' => $var1);
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
                        $value = json_decode($result);
                        if ($value->Status == "Success") {
                                $model['txn_id'] = $txnid;
                                $model['url'] = $value->URL;
                        } else {
                                $model['txn_id'] = $txnid;
                                $model['url'] = $url;
                        }
                } else if ($pgname == 'airpay') {
                        $n = explode(" ", $name);
                        $name1 = $n[0];
                        $name2 = $n[1];
                        $url = 'http://payments.nowpay.co.in/api/invoice/create';
                        $data = '{"MERCHANT_ID": 24149, "INVOICE_NUMBER": "' . $txnid . '", "TOTAL_AMOUNT": ' . $amount . ', "MODE": "", "customer": { "FIRST_NAME": "' . $name1 . '", "LAST_NAME": "' . $name2 . '", "EMAIL": "' . $email . '", "PHONE": ' . $phone . '}, "SEND_REQUEST": { "EMAIL": false, "SMS": false }}';
                        $token = md5('dxI1fPPevCUq3iM~' . $data);
                        $post = array('data' => $data, 'token' => $token);
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
                        $value = json_decode($result);
                        if ($value->success) {
                                $model['txn_id'] = $txnid;
                                $model['url'] = $value->payment_url;
                        } else {
                                $model['txn_id'] = $txnid;
                                $model['url'] = $url;
                        }
                }
                $model['payment_status'] = "initiated";
                if ($quickpaymentlink->fill($model)->save()) {
                        $res = array('status' => 1, 'message' => "Quick payment details saved successfully", 'res' => $model);
                        $last_insert_id = $quickpaymentlink->id;
                        return $last_insert_id;
                } else {
                        $res = array('status' => -1, "message" => $failure_message);
                        $res['errors'][] = "Internal server error";
                        return response()->json($res);
                }
        }
        public function actionStatuschange($statusid)
        {
                $getQuickPaymentData = DB::table('quick_payment_link')
                        ->select('*')
                        ->where('id', $statusid)
                        ->first();

                $payment_url = $getQuickPaymentData->url;
                $name = $getQuickPaymentData->name;
                $email = $getQuickPaymentData->email;
                $amount = $getQuickPaymentData->amount;
                $hotel_id = $getQuickPaymentData->hotel_id;
                $room_type = $getQuickPaymentData->room_type;
                $number_of_rooms = $getQuickPaymentData->no_of_rooms;
                $check_in = $getQuickPaymentData->check_in;
                $check_out = $getQuickPaymentData->check_out;
                $comments = $getQuickPaymentData->comment;
                $hotel = DB::connection('bookingjini_kernel')->table('hotels_table')->where('hotel_id', $hotel_id)->select('hotel_name')->first();

                $hotel_name = $hotel->hotel_name;
                $mail_deatils = [
                        "to" => $email, "bcc" => "trilochan@bookingjini.com", "subject" => $hotel_name . " Quick Link Payment"
                ];
                $hotel_details =  ["hotel_name" => $hotel_name, "room_type" => $room_type, "number_of_rooms" => $number_of_rooms, "check_in" => $check_in, "check_out" => $check_out, "comments" => $comments];
                $payment_details = ["amount" => $amount, "payment_url" => $payment_url];
                $login_temp = new TempMailCode();
                $mailSend = $login_temp->SendQuickPaymentLink($mail_deatils, 'emails.quickPaymentLinkTemplate', $hotel_details, $payment_details);
                if ($mailSend) {
                        DB::table('quick_payment_link')
                                ->where('id', $statusid)
                                ->update(['status' => 1]);
                        $id = DB::table('quick_payment_link')->where('id', $statusid)->value('id');
                        return $id;
                } else {
                        return FALSE;
                }
        } //actionOtaOnOff closed
        public function actionPaymentstatusUpdate($statusid)
        {
                $var1 = QuickPaymentLink::where('id', $statusid)->select('*')->first();
                $url = 'https://info.payu.in/merchant/postservice.php?form=2';
                $hash_value = 'HpCvAH|verify_payment|' . $var1['txn_id'] . '|sHFyCrYD';
                $hash = hash('sha512', $hash_value);
                $post = array('key' => 'HpCvAH', 'command' => 'verify_payment', 'hash' => $hash, 'var1' => $var1['txn_id']);
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
                $tr_id = $var1['txn_id'];
                $payment_status = $data->transaction_details->$tr_id->status;
                if ($payment_status == "success") {
                        DB::table('quick_payment_link')
                                ->where('id', $statusid)
                                ->update(['payment_status' => 'captured']);
                        // return true;
                        return $var1;
                } else {
                        return FALSE;
                }
        }
        /**
         * Get all quick payment
         * get All record of quick payment
         * @author subhradip
         * function GetAllQuickPayment for selecting all data
         **/
        public function GetAllQuickPayment(int $hotel_id, Request $request)
        {
                $quickpaymentlink = new QuickPaymentLink();
                $conditions = array('hotel_id' => $hotel_id);
                $res = QuickPaymentLink::where($conditions)->orderBy('id', 'DESC')->get();
                if (sizeof($res) > 0) {
                        $res = array('status' => 1, 'message' => "records found", 'data' => $res);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, "message" => "No quick payment  records found");
                        return response()->json($res);
                }
        }
        /**
         * To check and update quick payment status
         *
         * @author subhradip
         * function CheckQuickPayment to check and update quick payment status
         **/
        public function CheckQuickPayment(int $payment_link_id, Request $request)
        {
                $data = $this->actionPaymentstatusUpdate($payment_link_id);
                if ($data) {
                        $data['room_details'] = $this->getRoomRateDetails($data['hotel_id'], $data['room_type'], $data['rate_plan']);
                        $res = array('status' => 1, 'message' => "status updated successfully", 'qpdata' => $data);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, 'message' => "Payment not done yet");
                        return response()->json($res);
                }
        }
        public function resendEmail(int $payment_link_id, string $txn_id, Request $request)
        {
                if ($this->actionStatuschange($payment_link_id)) {
                        $res = array('status' => 1, 'message' => "Quick payment link sent successfully!");
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, 'message' => "Quick payment link sending failed");
                        return response()->json($res);
                }
        }
        public function getBookingjiniPaymentGateway()
        {
                $getBJpaymentgateways = BookingjiniPaymentgateway::select('*')->get();
                if (sizeof($getBJpaymentgateways) > 0) {
                        $resp = array('status' => 1, 'message' => 'bookingjini paymantgateways retrieve successfully', 'data' => $getBJpaymentgateways);
                        return response()->json($resp);
                } else {
                        $resp = array('status' => 0, 'message' => 'bookingjini paymantgateways retrieve fails');
                        return response()->json($resp);
                }
        }

        public function getQuickPaymentBookingDetails(int $id, $hotel_id, Request $request)
        {
                $getBookingDetails = QuickPaymentLink::where('hotel_id', $hotel_id)->where('id', $id)->get();
                if (sizeof($getBookingDetails) > 0) {
                        $response = array('status' => 1, 'message' => 'Data Retrieved Successful', 'data' => $getBookingDetails);
                } else {
                        $response = array('status' => 0, 'message' => 'Fails');
                }
                return response()->json($response);
        }

        public function getRoomRateDetails($hotel_id, $room_type, $rate_plan)
        {
                $room_rate_data = MasterHotelRatePlan::join('room_type_table', 'room_type_table.room_type_id', '=', 'room_rate_plan.room_type_id')
                        ->join('rate_plan_table', 'rate_plan_table.rate_plan_id', '=', 'room_rate_plan.rate_plan_id')
                        ->select('room_type_table.room_type', 'room_type_table.max_people', 'room_type_table.max_child', 'rate_plan_table.plan_name', 'rate_plan_table.plan_type', 'room_rate_plan.room_type_id', 'room_rate_plan.rate_plan_id', 'room_rate_plan.bar_price', 'room_rate_plan.extra_adult_price', 'room_rate_plan.extra_child_price')
                        ->where('room_rate_plan.room_type_id', $room_type)->where('room_rate_plan.rate_plan_id', $rate_plan)
                        ->where('room_rate_plan.hotel_id', $hotel_id)->orderby('room_rate_plan_id', 'desc')->first();
                return $room_rate_data;
        }
        public function beBookingPaymentStatus(Request $request)
        {
                $data = $request->all();
                $invoice_id = $data['invoice_id'];
                $hotel_id = $data['hotel_id'];
                // $getCompanyId = HotelInformation::select('company_id')->where('hotel_id',$hotel_id)->first();
                // $company_id = $getCompanyId->company_id;
                // $gateway_details = PaymentGetway::select('credentials')->where('company_id',$company_id)->first();
                // $get_credentials = json_decode($gateway_details->credentials);
                // $key = $get_credentials[0]->key;
                // $salt = $get_credentials[0]->salt;
                $getBookingDate = Invoice::select('booking_date')->where('invoice_id', $invoice_id)->first();
                $bk_date = $getBookingDate->booking_date;
                $bk_date = date('dmy', strtotime($bk_date));
                $var1 = $bk_date . $invoice_id;
                $url = 'https://test.payu.in/merchant/postservice.php?form=2';
                $hash_value = '7rnFly|verify_payment|' . $var1 . '|pjVQAWpA';
                $hash = hash('sha512', $hash_value);
                $post = array('key' => '7rnFly', 'command' => 'verify_payment', 'hash' => $hash, 'var1' => $var1);
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
                if ($data) {
                        return response()->json(array("status" => 1, "message" => "Payment captured successfully", "data" => $result));
                } else {
                        return response()->json(array("status" => 0, "message" => "Payment not captured", "data" => $result));
                }
        }

        public function paymentEasyCollect(Request $request)
        {
                $logpath = storage_path("logs/easybuzz.log" . date("Y-m-d"));
                $response_data = $request->all();
                $response = json_encode($response_data);
                $logfile = fopen($logpath, "a+");
                fwrite($logfile, "data: " . $response . "\n");
                fclose($logfile);

                $txnid = $response_data['txnid'];
                if (substr($txnid, 0, 3) == 'QPL') {
                        $qpl_data = QuickPaymentLink::where('txn_id', $txnid)->first();

                        if ($qpl_data) {
                                $date = date('Y-m-d H:i:s');
                                QuickPaymentLink::where('txn_id', $txnid)->update(['payment_received_on' => $date]);
                        }
                } elseif (substr($txnid, 0, 4) == 'ADAL') {

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                                CURLOPT_URL => 'https://subscription.bookingjini.com/api/easebuzz-update-autodebit-authorization-accesskey',
                                CURLOPT_RETURNTRANSFER => false,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_POSTFIELDS => $response_data,
                                CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json'
                                ),
                        ));
                        $response = curl_exec($curl);
                        curl_close($curl);
                } elseif (substr($txnid, 0, 5) == 'ADREQ') {

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                                CURLOPT_URL => 'https://subscription.bookingjini.com/api/easebuzz-update-autodebit-transaction-status',
                                CURLOPT_RETURNTRANSFER => false,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_POSTFIELDS => $response_data,
                                CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json'
                                ),
                        ));
                        $response = curl_exec($curl);
                        curl_close($curl);
                }
        }
}
