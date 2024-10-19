<?php

namespace App\Http\Controllers\Extranetv4;

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
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;

class QuickPaymentLinkController extends Controller
{
        protected $ipService;
        public function __construct(IpAddressService $ipService)
        {
                $this->ipService=$ipService;
        }

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

                if (isset($request->auth->admin_id)) {
                        $data['admin_id'] = $request->auth->admin_id;
                } else if (isset($request->auth->intranet_id)) {
                        $data['admin_id'] = $request->auth->intranet_id;
                } else if (isset($request->auth->id)) {
                        $data['admin_id'] = $request->auth->id;
                } else {
                        $data['admin_id'] = $request->auth->id;
                }
                
                if (!empty($data)) {
                       
                        $last_insert_id = $this->actionCreate($data);
                       
                        if ($last_insert_id == 0) {
                                return response()->json(array('status' => 0, 'message' => 'Invalid Price'));
                        }
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
                $total_amount           = $model['amount'];

                if (isset($model['advance_amount']) && $model['advance_amount'] > 0) {
                        $advance_amount = $model['advance_amount'];
                        $send_payment_amount = $model['advance_amount'];
                } else {
                        $advance_amount = 0;
                        $send_payment_amount = $total_amount;
                }

                $name                   = $model['name'];
                $email                  = $model['email'];
                $phone                  = $model['phone'];
                $hotel_id               = $model['hotel_id'];
                $room_type              = $model['room_type'];
                $rate_plan              = $model['rate_plan'];
                $check_in               = $model['check_in'];
                $check_out              = $model['check_out'];

                // if ($model['hotel_id'] == 1953) {
                $total_amount = $model['amount'];
                $no_of_rooms = $model['no_of_rooms'];
                $check_in = $model['check_in'];
                $check_out = $model['check_out'];

                $date1 = date_create($check_in);
                $date2 = date_create($check_out);
                $diff = date_diff($date1, $date2);
                $diff = $diff->format("%a");

                if ($diff == 0) {
                        $diff = 1;
                }
                $no_of_nights = $diff;
                //single room price
                $per_room_price = ($total_amount / $no_of_nights) / $no_of_rooms;
                //check gst per room price

                $is_taxable = HotelInformation::where('hotel_id', $model['hotel_id'])->select('is_taxable')->first();
                
                if ($is_taxable->is_taxable == 1) {
                        $gstPercent = $this->checkGSTPercent($per_room_price);
                        if ($gstPercent == 0) {
                                return 0;
                        }
                } else {
                        $gstPercent = 0;
                }

                $gstPrice = (($per_room_price) * $gstPercent) / (100 + $gstPercent);
                $gstPrice = round($gstPrice, 2);

                $total_gst_price = $gstPrice * $no_of_nights * $no_of_rooms;
                $amount_with_out_gst = $total_amount - $total_gst_price;

                $data['amount'] = $amount_with_out_gst;
                $data['gst_price'] = $total_gst_price;

                $today_date = date('Y-m-d H:i');
                $check_in_date = date('Y-m-d 23:59',strtotime($check_in));
                $seconds = strtotime($check_in_date) - strtotime($today_date);
                $hours = round($seconds / 60 /  60);
                $easebuzz_exp = date('d-m-Y', strtotime($check_in .'+1 day'));
                // }

                $txnid                  = 'QPL' . $hotel_id . '' . strtotime("now");
                $hotel = DB::connection('bookingjini_kernel')->table('hotels_table')->where('hotel_id', $hotel_id)->select('hotel_name')->first();
                $hotel_name = $hotel->hotel_name;
                $pgname =    $model['pg_name'];
                // if($hotel_id==2600){
                //         $pgname  = 'easybuzz';
                // }
                if ($pgname == 'payu') {
                        $url                    = 'https://info.payu.in/merchant/postservice.php?form=2'; //URL
                        $value                  = array("amount" => $send_payment_amount, "txnid" => $txnid, "productinfo" => "MailInvoice for" . $hotel_name, "firstname" => $name, "email" => $email, "phone" => $phone, "send_email_now" => "0","validation_period"=>$hours,"time_unit"=>"H");
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
                        $data = '{"MERCHANT_ID": 24149, "INVOICE_NUMBER": "' . $txnid . '", "TOTAL_AMOUNT": ' . $send_payment_amount . ', "MODE": "", "customer": { "FIRST_NAME": "' . $name1 . '", "LAST_NAME": "' . $name2 . '", "EMAIL": "' . $email . '", "PHONE": ' . $phone . '}, "SEND_REQUEST": { "EMAIL": false, "SMS": false }}';
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
                }else if($pgname == 'EaseBuzz'){
                        $phone = (string)$phone;
                        $operation [] = array('type'=>'email','template'=>'Default email template');
                        $url                    = 'https://dashboard.easebuzz.in/easycollect/v1/create'; //URL
                        $hash_value             = '0AWXYPX8GH|'.$txnid.'|'.$name.'|'.$email.'|'.$phone.'|'.$send_payment_amount.'|udf1|udf2|udf3|udf4|udf5|'.$hotel_name.'|PXEDP97NF2';
                        $hash                   = hash('sha512', $hash_value);
                        $post                   = array("merchant_txn" => $txnid,"key" => "0AWXYPX8GH","email" => $email,"name" => $name,"amount" => $send_payment_amount,"phone" => $phone, "udf1" => "udf1", "udf2" => "udf2","udf3" => "udf3","udf4" => "udf4","udf5" => "udf5","message"=>$hotel_name, "expiry_date"=>$easebuzz_exp, "operation"=>$operation, "hash"=>$hash);
                        $post = json_encode($post);
                        // print_r($post);exit;
                        $ch = curl_init();
                        curl_setopt_array($ch, array(
                                CURLOPT_URL => 'https://dashboard.easebuzz.in/easycollect/v1/create',
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_POSTFIELDS =>$post,
                                CURLOPT_HTTPHEADER => array(
                                  'Content-Type: application/json',
                                  'Cookie: Path=/'
                                ),
                              ));
                              
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $value = json_decode($result);
                        // print_r($post);
                        // print_r($value);exit;
                        if ($value->status == true) {
                                $data = $value->data;
                                $model['txn_id'] = $txnid;
                                $model['url'] = $data->payment_url;
                                // dd($model);
                        }
                }
                $model['check_in'] = date('Y-m-d',strtotime($model['check_in']));
                $model['check_out'] = date('Y-m-d',strtotime($model['check_out']));
                $model['payment_status'] = "initiated";
                $model['user_id'] = $model['admin_id'];
             

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
                if ($getQuickPaymentData->advance_amount > 0) {
                        $amount = $getQuickPaymentData->advance_amount;
                } else {
                        $amount = $getQuickPaymentData->amount;
                }

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
                // if($statusid == '2032'){
                //         print_r($data->transaction_details->$tr_id->addedon);exit;
                // }
                if (isset($data->transaction_details->$tr_id->addedon)) {
                        $payment_received_on = $data->transaction_details->$tr_id->addedon;
                } else {
                        $payment_received_on = 'Waiting';
                }

                if (isset($data->transaction_details->$tr_id->status)) {
                        $payment_status = $data->transaction_details->$tr_id->status;
                        if ($payment_status == "success") {
                                DB::table('quick_payment_link')
                                        ->where('id', $statusid)
                                        ->update(['payment_status' => 'captured', 'payment_received_on' => $payment_received_on]);
                                // return true;
                                return $var1;
                        } else {
                                return FALSE;
                        }
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
                $res = QuickPaymentLink::join('kernel.room_type_table', 'quick_payment_link.room_type', '=', 'kernel.room_type_table.room_type_id')
                        ->join('kernel.rate_plan_table', 'quick_payment_link.rate_plan', '=', 'rate_plan_table.rate_plan_id')
                        ->where('quick_payment_link.hotel_id', $hotel_id)
                        ->select('quick_payment_link.*', 'room_type_table.room_type', 'room_type_table.image', 'rate_plan_table.plan_type')
                        ->orderBy('quick_payment_link.id', 'DESC')->get();
                if (sizeof($res) > 0) {
                        foreach ($res as $result) {
                                $image_details = explode(',', $result->image);
                                $image_url = DB::table('kernel.image_table')->where('image_id', $image_details[0])->first();
                                if (isset($image_url)) {
                                        $result->image = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image_url->image_name;
                                } else {
                                        $result->image = "";
                                }
                                $result->bookingjini_icon = 'https://d3ki85qs1zca4t.cloudfront.net/logo/ota/bj.png';
                                $result->link_send_on = date('d M Y H:i:s A', strtotime($result->created_date));
                                $result->payment_received_on = ($result->payment_received_on != null) ? date('d M Y H:i:s A', strtotime($result->payment_received_on)) : "Waiting";
                                $result->pg_image = 'https://d3ki85qs1zca4t.cloudfront.net/logo/payment_gateway/payU_transparentl.png';
                        }
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
                $qpl_data = QuickPaymentLink::where('id',$payment_link_id)->where('pg_name','EaseBu')->where('payment_received_on','!=','')->first();
                if($qpl_data){
                        $data = $qpl_data;
                        QuickPaymentLink::where('id',$payment_link_id)->update(['payment_status'=>'captured']);
                }else{
                        $data = $this->actionPaymentstatusUpdate($payment_link_id);
                }
               
                if ($data) {
                        // if($payment_link_id == 1982){
                        $total_amount = $data->amount;
                        $no_of_rooms = $data->no_of_rooms;
                        $check_in = $data->check_in;
                        $check_out = $data->check_out;

                        $date1 = date_create($check_in);
                        $date2 = date_create($check_out);
                        $diff = date_diff($date1, $date2);
                        $diff = $diff->format("%a");

                        if ($diff == 0) {
                                $diff = 1;
                        }
                        $no_of_nights = $diff;
                        //single room price
                        $per_room_price = ($total_amount / $no_of_nights) / $no_of_rooms;
                        
                        $is_taxable = HotelInformation::where('hotel_id', $data['hotel_id'])->select('is_taxable')->first();
                        if ($is_taxable->is_taxable == 1) {
                                $gstPercent = $this->checkGSTPercent($per_room_price);
                                if ($gstPercent == 0) {
                                        $gstPercent = 0;
                                }
                        } else {
                                $gstPercent = 0;
                        }

                        

                        $gstPrice = (($per_room_price) * $gstPercent) / (100 + $gstPercent);
                        $gstPrice = round($gstPrice, 2);

                        $total_gst_price = $gstPrice * $no_of_nights * $no_of_rooms;
                        $amount = $total_amount - $total_gst_price;

                        $data['amount'] = $amount;
                        $data['gst_price'] = $total_gst_price;
                        $data['bar_price'] = $amount/$no_of_rooms;

                        // }
                }

                if ($data) {
                        $data['room_details'] = $this->getRoomRateDetails($data['hotel_id'], $data['room_type'], $data['rate_plan']);
                        $res = array('status' => 1, 'message' => "status updated successfully", 'qpdata' => $data);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, 'message' => "Payment not received yet");
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
                $getBJpaymentgateways = BookingjiniPaymentgateway::select('*')->where('paymentgateway_name','EaseBuzz')->get();
                if (sizeof($getBJpaymentgateways) > 0) {
                        foreach ($getBJpaymentgateways as $getBJpaymentgateway) {
                                // $getBJpaymentgateway->logo = "https://d3ki85qs1zca4t.cloudfront.net/logo/payment_gateway/payU_transparentl.png";
                                $getBJpaymentgateway->logo ="https://d3ki85qs1zca4t.cloudfront.net/logo/payment_gateway/Easebuzz_Logo.jpg";
                        }
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

        public function checkPaymentStatus($tnx_id)
        {
                $invoice_id = substr($tnx_id, 6);

                $company_details = Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', 'kernel.hotels_table.hotel_id')
                        ->select('kernel.hotels_table.company_id')
                        ->where('invoice_table.invoice_id', $invoice_id)
                        ->first();

                if ($company_details) {
                        $check_payment_getway = PaymentGetway::where('company_id', $company_details->company_id)->first();

                        if ($check_payment_getway) {
                                if ($check_payment_getway->payumoney) {
                                        $url = 'https://info.payu.in/merchant/postservice.php?form=2';
                                        $hash_value = 'HpCvAH|verify_payment|' . $tnx_id . '|sHFyCrYD';
                                        $hash = hash('sha512', $hash_value);
                                        $post = array('key' => 'HpCvAH', 'command' => 'verify_payment', 'hash' => $hash, 'var1' => $tnx_id);

                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                                        $result = curl_exec($ch);
                                        curl_close($ch);
                                        $data = json_decode($result);
                                        $tr_id = $tnx_id;
                                        $payment_status = $data->transaction_details->$tr_id->status;
                                        if ($payment_status == "success") {
                                                return $payment_status;
                                        } else {
                                                return FALSE;
                                        }
                                } else {
                                        return response()->json(array("status" => 0, "message" => "Payment Gateway does't have this facility."));
                                }
                        } else {
                                return response()->json(array("status" => 0, "message" => "Payment Gateway does't have this facility."));
                        }
                } else {
                        return response()->json(array("status" => 0, "message" => "Check Payment status Failed."));
                }
        }

        //Saroj Patel Dt-21-01-2023
        public function checkGSTPercent($price)
        {
                if ($price >= 1.12 && $price <= 8400) {
                        return 12;
                } else if ($price >= 8851.18) {
                        return 18;
                } else {
                        return '0';
                }
        }

        public function CheckQuickPaymentLinkStatus($type, $hotel_id)
        {
                $current_date = date('Y-m-d');
                $res = QuickPaymentLink::join('kernel.room_type_table', 'quick_payment_link.room_type', '=', 'kernel.room_type_table.room_type_id')
                        ->join('kernel.rate_plan_table', 'quick_payment_link.rate_plan', '=', 'rate_plan_table.rate_plan_id')
                        ->select('quick_payment_link.created_at', 'quick_payment_link.amount', 'quick_payment_link.advance_amount', 'quick_payment_link.name', 'quick_payment_link.email', 'quick_payment_link.phone', 'quick_payment_link.check_in', 'quick_payment_link.check_out', 'quick_payment_link.pg_name', 'quick_payment_link.no_of_rooms', 'quick_payment_link.payment_status', 'room_type_table.room_type', 'room_type_table.image', 'rate_plan_table.plan_type')
                        ->where('quick_payment_link.hotel_id', $hotel_id);

                if ($type == 'received') {
                        $res = $res->where('quick_payment_link.payment_status', 'captured');
                } else if ($type == 'active') {
                        $res = $res->where('quick_payment_link.payment_status', 'initiated')
                                ->where('quick_payment_link.check_in', '>=', $current_date);
                } else {
                        $res = $res->where('quick_payment_link.payment_status', 'initiated')
                                ->where('quick_payment_link.check_in', '<', $current_date);
                }
                $res = $res->orderBy('quick_payment_link.id', 'DESC')
                        ->get();

                $payment_link = [];
                if (sizeof($res) > 0) {
                        foreach ($res as $qpl) {
                                $image_details = explode(',', $qpl->image);
                                $image_url = DB::table('kernel.image_table')->where('image_id', $image_details[0])->first();
                                if (isset($image_url)) {
                                        $qpl_details['image'] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image_url->image_name;
                                } else {
                                        $qpl_details['image'] = "";
                                }
                                $qpl_details['room_type'] = $qpl->room_type;
                                $qpl_details['plan_type'] = $qpl->plan_type;
                                $qpl_details['no_of_rooms'] = $qpl->no_of_rooms;
                                $qpl_details['check_in_out'] = date('d M Y', strtotime($qpl->check_in)) . '  -  ' . date('d M Y', strtotime($qpl->check_out));
                                $qpl_details['name'] =  $qpl->name;
                                $qpl_details['email_id'] = $qpl->email;
                                $qpl_details['phone'] = $qpl->phone;
                                $qpl_details['amount'] = $qpl->amount;
                                $qpl_details['advance_amount'] = $qpl->advance_amount;
                                $qpl_details['pg_name'] = $qpl->pg_name;
                                if ($qpl->pg_name == 'payu') {
                                        $qpl_details['pg_image'] = 'https://d3ki85qs1zca4t.cloudfront.net/logo/payment_gateway/payU_transparentl.png';
                                } else if ($qpl->pg_name == 'razorpay') {
                                        $qpl_details['pg_image'] = 'https://d3ki85qs1zca4t.cloudfront.net/logo/payment_gateway/razorpay_transparent.png';
                                }

                                $qpl_data['link_send_date'] =  date('Y-m-d', strtotime($qpl->created_at));
                                $qpl_data['link_send_time'] =  date('h:i A', strtotime($qpl->created_at));
                                if ($qpl->advance_amount > 0) {
                                        $qpl_data['amount'] =  $qpl->advance_amount;
                                } else {
                                        $qpl_data['amount'] =  $qpl->amount;
                                }
                                $qpl_data['name'] = $qpl->name;
                                $qpl_data['payment_status'] = $qpl->payment_status;
                                if ($qpl->payment_received_on == '' && $type == 'active') {
                                        $qpl_data['payment_received_on_date'] =  'Not Yet Received';
                                        $qpl_data['payment_received_on_time'] = '';
                                } elseif ($qpl->payment_received_on == '' && $type == 'expired') {
                                        $qpl_data['payment_received_on_date'] =  'Link Expired';
                                        $qpl_data['payment_received_on_time'] = '';
                                } else {
                                        $qpl_data['payment_received_on_date'] =  date('Y-m-d', strtotime($qpl->created_at));
                                        $qpl_data['payment_received_on_time'] =  date('h:i A', strtotime($qpl->created_at));
                                }
                                $qpl_data['details'] = $qpl_details;
                                $payment_link[] = $qpl_data;
                        }

                        $res = array('status' => 1, 'message' => "records found", 'data' => $payment_link);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, "message" => "No quick payment  records found");
                        return response()->json($res);
                }
        }


        public function CheckQuickPaymentLinkStatusNew($type, $hotel_id, $date)
        {
                $current_date = date('Y-m-d');
                $filter_date = date('Y-m-d ', strtotime($date));

                $res = QuickPaymentLink::join('kernel.room_type_table', 'quick_payment_link.room_type', '=', 'kernel.room_type_table.room_type_id')
                        ->join('kernel.rate_plan_table', 'quick_payment_link.rate_plan', '=', 'rate_plan_table.rate_plan_id')
                        ->select('quick_payment_link.id','quick_payment_link.txn_id','quick_payment_link.created_at', 'quick_payment_link.amount', 'quick_payment_link.advance_amount', 'quick_payment_link.name', 'quick_payment_link.email', 'quick_payment_link.phone', 'quick_payment_link.check_in', 'quick_payment_link.check_out', 'quick_payment_link.pg_name', 'quick_payment_link.no_of_rooms', 'quick_payment_link.payment_status', 'quick_payment_link.payment_received_on','quick_payment_link.url',  'room_type_table.room_type', 'room_type_table.image', 'rate_plan_table.plan_type')
                        ->where('quick_payment_link.hotel_id', $hotel_id);

                if ($type == 'received') {
                        $res = $res->where('quick_payment_link.payment_status', 'captured');
                        $res =  $res->whereDate('quick_payment_link.payment_received_on', $filter_date);
                } else if ($type == 'active') {
                        $res = $res->where('quick_payment_link.payment_status', 'initiated')
                                ->where('quick_payment_link.check_in', '>=', $current_date);
                } else {
                        $res = $res->where('quick_payment_link.payment_status', 'initiated')
                                ->whereDate('quick_payment_link.created_at', $filter_date)
                                ->whereDate('quick_payment_link.check_in', '<', $current_date);
                }
                $res = $res->orderBy('quick_payment_link.id', 'DESC')
                        ->get();

                $payment_link = [];
                if (sizeof($res) > 0) {
                        foreach ($res as $qpl) {
                                $image_details = explode(',', $qpl->image);
                                $image_url = DB::table('kernel.image_table')->where('image_id', $image_details[0])->first();
                                if (isset($image_url)) {
                                        $qpl_details['image'] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image_url->image_name;
                                } else {
                                        $qpl_details['image'] = "";
                                }
                                $qpl_details['room_type'] = $qpl->room_type;
                                $qpl_details['plan_type'] = $qpl->plan_type;
                                $qpl_details['no_of_rooms'] = $qpl->no_of_rooms;
                                $qpl_details['check_in_out'] = date('d M Y', strtotime($qpl->check_in)) . '  -  ' . date('d M Y', strtotime($qpl->check_out));
                                $qpl_details['name'] =  $qpl->name;
                                $qpl_details['email_id'] = $qpl->email;
                                $qpl_details['phone'] = $qpl->phone;
                                $qpl_details['amount'] = $qpl->amount;
                                $qpl_details['advance_amount'] = $qpl->advance_amount;
                                $qpl_details['pg_name'] = $qpl->pg_name;
                                if ($qpl->pg_name == 'payu') {
                                        $qpl_details['pg_image'] = 'https://d3ki85qs1zca4t.cloudfront.net/logo/payment_gateway/payU_transparentl.png';
                                } else if ($qpl->pg_name == 'razorpay') {
                                        $qpl_details['pg_image'] = 'https://d3ki85qs1zca4t.cloudfront.net/logo/payment_gateway/razorpay_transparent.png';
                                }

                                $qpl_data['link_send_date'] =  date('Y-m-d', strtotime($qpl->created_at));
                                $qpl_data['link_send_time'] =  date('h:i A', strtotime($qpl->created_at));
                                if ($qpl->advance_amount > 0) {
                                        $qpl_data['amount'] =  $qpl->advance_amount;
                                } else {
                                        $qpl_data['amount'] =  $qpl->amount;
                                }
                                $qpl_data['qpl_id'] = $qpl->id;
                                $qpl_data['txn_id'] = $qpl->txn_id;
                                $qpl_data['name'] = $qpl->name;
                                $qpl_data['payment_status'] = $qpl->payment_status;
                                if ($qpl->payment_received_on == '' && $type == 'active') {
                                        $qpl_data['payment_received_on_date'] =  'Not Yet Received';
                                        $qpl_data['payment_received_on_time'] = '';
                                        $qpl_details['link'] = $qpl->url;
                                } elseif ($qpl->payment_received_on == '' && $type == 'expired') {
                                        $qpl_data['payment_received_on_date'] =  'Link Expired';
                                        $qpl_data['payment_received_on_time'] = '';
                                } else {
                                        $qpl_data['payment_received_on_date'] =  date('Y-m-d', strtotime($qpl->payment_received_on));
                                        $qpl_data['payment_received_on_time'] =  date('h:i A', strtotime($qpl->payment_received_on));
                                }
                                $qpl_data['details'] = $qpl_details;
                                $payment_link[] = $qpl_data;
                        }

                        $res = array('status' => 1, 'message' => "records found", 'data' => $payment_link);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, "message" => "No quick payment  records found");
                        return response()->json($res);
                }
        }


        public function checkPaymentLinkStatus(Request $request){
                // dd($request->all());

                $data = $request->all();
                $status = $data['status'];
                $merchantTransactionId = $data['merchantTransactionId'];

                if ($status == "success") {
                        DB::table('quick_payment_link')
                                ->where('id', $merchantTransactionId)
                                ->update(['payment_status' => 'captured', 'payment_received_on' => $payment_received_on]);
                        // return true;
                        return $var1;
                } else {
                        return FALSE;
                }

                if ($data) {
                        // if($payment_link_id == 1982){
                        $total_amount = $data->amount;
                        $no_of_rooms = $data->no_of_rooms;
                        $check_in = $data->check_in;
                        $check_out = $data->check_out;

                        $date1 = date_create($check_in);
                        $date2 = date_create($check_out);
                        $diff = date_diff($date1, $date2);
                        $diff = $diff->format("%a");

                        if ($diff == 0) {
                                $diff = 1;
                        }
                        $no_of_nights = $diff;
                        //single room price
                        $per_room_price = ($total_amount / $no_of_nights) / $no_of_rooms;

                        //check gst per room price
                        $gstPercent = $this->checkGSTPercent($per_room_price);
                        $gstPrice = (($per_room_price) * $gstPercent) / (100 + $gstPercent);
                        $gstPrice = round($gstPrice, 2);

                        $total_gst_price = $gstPrice * $no_of_nights * $no_of_rooms;
                        $amount = $total_amount - $total_gst_price;

                        $data['amount'] = $amount;
                        $data['gst_price'] = $total_gst_price;

                        // }
                }

                if ($data) {
                        $data['room_details'] = $this->getRoomRateDetails($data['hotel_id'], $data['room_type'], $data['rate_plan']);
                        $res = array('status' => 1, 'message' => "status updated successfully", 'qpdata' => $data);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, 'message' => "Payment not received yet");
                        return response()->json($res);
                }
        }
}
