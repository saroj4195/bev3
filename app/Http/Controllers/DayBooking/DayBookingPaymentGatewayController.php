<?php

namespace App\Http\Controllers\DayBooking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use DB;
use App\Http\Controllers\Checksum;
use App\Http\Controllers\BookingEngineController;
use App\Http\Controllers\worldline\Awlmeapi;
use App\Http\Controllers\worldline\ReqMsgDTO;

require('razorpay/razorpay-php/Razorpay.php');
require('vendor/autoload.php');

use Razorpay\Api\Api;
use App\Invoice;
use App\HotelInformation;
use App\CompanyDetails;
use App\OnlineTransactionDetail;
use App\Http\Controllers\Axis\VPCPaymentConnection;
use App\Http\Controllers\Paytm\PaytmEncdecController;
use App\MetaSearchEngineSetting;
use App\PaymentGetway;
use App\HotelBooking;
use App\Refund;
use App\DayBookings;

require('easebuzz/easebuzz_payment_gateway.php');

use Easebuzz;

class DayBookingPaymentGatewayController extends Controller
{
    protected $dayBookingController;

    public function __construct(DayBookingsController $dayBookingController)
    {
        $this->dayBookingController = $dayBookingController;
    }

    public function dayBookingEasebuzzAccesskey($invoice_id)
    {
        
        $hotel_info = DayBookings::leftjoin('kernel.user_table', 'user_table.user_id', '=', 'day_bookings.user_id')
        ->leftjoin('kernel.hotels_table','hotels_table.hotel_id','=','day_bookings.hotel_id')
        ->leftjoin('kernel.company_table','company_table.company_id','=','hotels_table.company_id')
        ->where('day_bookings.booking_id', $invoice_id)
        ->select('day_bookings.hotel_id', 'hotels_table.hotel_name', 'day_bookings.paid_amount', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile', 'user_table.address','company_table.company_url','hotels_table.company_id')->first();


        $pg_details = DB::table('paymentgateway_details')
            ->select('provider_name', 'credentials', 'fail_url')
            ->where('hotel_id', '=', $hotel_info->hotel_id)
            ->where('is_active', '=', 1)
            ->first();
        if (!$pg_details) {
            $pg_details = DB::table('payment_gateway_details')
                ->select('provider_name', 'credentials', 'fail_url')
                ->where('company_id', '=', $hotel_info->company_id)
                ->first();
        }

        if (isset($pg_details)) {
            $credentials = json_decode($pg_details->credentials);
            $MERCHANT_KEY = $credentials[0]->merchant_key;
            $SALT = $credentials[0]->salt;
        }else{
            $company_url = 'https://'.$hotel_info->company_url;
            $parsed_url = parse_url($company_url);
            $host_parts = explode('.', $parsed_url['host']);
            if (count($host_parts) >= 2) {
                $subdomain = $host_parts[count($host_parts) - 2] . '.' . $host_parts[count($host_parts) - 1];
                if($subdomain =='pripgo.com'){
                    $MERCHANT_KEY = "VQ8L89923G";
                    $SALT = "ZYOVGLEMVA";       
                }else{
                    $MERCHANT_KEY = "0AWXYPX8GH";
                    $SALT = "PXEDP97NF2";       
                }
            } else {
                $MERCHANT_KEY = "0AWXYPX8GH";
                $SALT = "PXEDP97NF2";
            }
        }

        $ENV = "prod";
        $easebuzzObj = new Easebuzz($MERCHANT_KEY, $SALT, $ENV);
        $amount = $hotel_info->paid_amount;
        $buyerFirstName = trim($hotel_info->first_name);
        $email = trim($hotel_info->email_id);
        $mobile = $hotel_info->mobile;
        $address = $hotel_info->address;
        $txnid = date('dmy') . $invoice_id;
        $hotel_id = $hotel_info->hotel_id;
        $hotel_name = $hotel_info->hotel_name;

        $specialChars = array("'", '@', '#', '$', '%', '&', ',', '-');
        $replacement = ' ';
        $hotel_name = str_replace($specialChars, $replacement, $hotel_name);

        $postData = array(
            "txnid" => $txnid,
            "amount" => number_format((float)$amount, 2, '.', ''),
            "firstname" => $buyerFirstName,
            "email" => $email,
            "phone" => $mobile,
            "productinfo" => $hotel_name . " Booking",
            "surl" => "https://be-alpha.bookingjini.com/easebuzz-response",
            "furl" => "https://be-alpha.bookingjini.com/easebuzz-response",
            "udf1" => $hotel_id,
            "udf2" => $hotel_name,
            "udf3" => "",
            "udf4" => "",
            "udf5" => "",
            "address1" => $address,
            "address2" => "",
            "city" => "",
            "state" => "",
            "country" => "",
            "zipcode" => ""
        );

        $encode_request = json_encode($postData);
        $res = $easebuzzObj->initiatePaymentAPI($postData,false);
        $response = json_decode($res);


        if($response->status == 1){
            $tnx_data = [
                'invoice_id' => $invoice_id,
                'transaction_id' => $txnid,
                'email_id' => $hotel_info->email_id,
                'order_no' => $txnid,
                'request_data' => $encode_request,
                'access_key' => $response->access_key,
            ];
            
            $condition = [
                'invoice_id' => $invoice_id,
            ];
            
            $details = OnlineTransactionDetail::updateOrCreate($condition, $tnx_data);
            $res_data['access_key'] = $response->access_key;
        }else{
            $check_access_key = OnlineTransactionDetail::where('invoice_id',$invoice_id)->orderBy('tr_id','DESC')->first();
            $res_data['access_key'] = $check_access_key->access_key;
        }

        return response()->json(array('status' => 1, 'data'=>$res_data));
    }

    public function easeBuzzResponse(Request $request)
    {
        $data = $request->all();
        $status = $request['status'];
        $curdate    = date('dmy');
        $txnid = $data['txnid'];
        $id = str_replace($curdate, '', $txnid);

        if ($status == 'success') {
            $payment_mode = $data['mode'];
            $hash       = $data['hash'];
            $mihpayid = $data['txnid'];
            //store the payment response
            try {
                $encode_response = json_encode($data);
                $this->saveResponseData($encode_response, $id);
            } catch (\Exception $e) {
            }

            $url = $this->dayBookingController->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);
            if ($url != '') {
                $url = $this->findURL($url, $id, $txnid);
                return response()->json(array('status' => 1, 'url'=>$url));
            } else {
                $CompanyDetails   = Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
                    ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
                    ->select('company_table.company_url')
                    ->where('invoice_table.invoice_id', '=', $id)
                    ->first();
                $url = $CompanyDetails['company_url'];
                return response()->json(array('status' => 1, 'url'=>$url));
            }
        } else {
            $res = array('status' => 0, 'message' => "Booking Failed!");
            return response()->json($res);
        }
    }

    public function saveResponseData($encResponse, $inv_data)
    {
        $details = OnlineTransactionDetail::where('invoice_id', $inv_data)->update(['response_data' => $encResponse]);
        if ($details) {
            return true;
        } else {
            return false;
        }
    }

    public function saveRequestTrasnstionData($data, $invoice_id, $txnid, $email_id)
    {
        $tnx_data = [
            'invoice_id' => $invoice_id,
            'transaction_id' => $txnid,
            'email_id'   => $email_id,
            'order_no'   => $txnid,
            'request_data' => $data,
        ];
        $details = OnlineTransactionDetail::insert($tnx_data);
        if ($details) {
            return true;
        } else {
            return false;
        }
    }

    public function findURL($url, $invoice_id, $txnid)
    {
        if (strpos($url, 'bookingjini.com')) {
            $url = "https://" . $url . "/day-booking/thank-you/" . $invoice_id . "/" . $txnid;
        } else {
            $url = "https://" . $url . "/day-booking/thank-you/" . $invoice_id . "/" . $txnid;
        }

        return $url;
    }
    
    public function RazorpayOrderId($invoice_id)
    {

        // $row = $this->invoiceDetails($invoice_id);
        $hotel_info = DayBookings::leftjoin('kernel.user_table', 'user_table.user_id', '=', 'day_bookings.user_id')
        ->leftjoin('kernel.hotels_table','hotels_table.hotel_id','=','day_bookings.hotel_id')
        ->leftjoin('kernel.company_table','company_table.company_id','=','hotels_table.company_id')
        ->where('day_bookings.booking_id', $invoice_id)
        ->select('day_bookings.hotel_id', 'hotels_table.hotel_name', 'day_bookings.paid_amount', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile', 'user_table.address','company_table.company_url','hotels_table.company_id')->first();


        $company_id = $hotel_info->company_id;
        $hotel_id = $hotel_info->hotel_id;
        $pg_details = $this->pgDetails($company_id, $hotel_id);
        $txnid = date('dmy') . $invoice_id;

        if ($pg_details) {
            $credentials = json_decode($pg_details->credentials);
        }

        $keyId = trim($credentials[0]->key);
        $keySecret = trim($credentials[0]->secret);

    
        // $keyId = 'rzp_test_kYzro6v3xfkAlE';
        // $keySecret = 'kcuj3onMFgIp46y08e3gqNrl';
        $api = new Api($keyId, $keySecret);
        $orderData = [
            'receipt'         => $txnid,
            'amount'          => $hotel_info->paid_amount * 100, // 2000 rupees in paise
            'currency'        => 'INR',
            'payment_capture' => 1 // auto capture
        ];

        $razorpayOrder = $api->order->create($orderData);
        $razorpayOrderId = $razorpayOrder['id'];

          // store the payment request.
        $encode_request = json_encode($orderData);
        $rquest_data = $this->saveRequestTrasnstionData($encode_request, $invoice_id, $razorpayOrderId, $hotel_info->email_id);
  

        if ($razorpayOrderId) {
            $res = array('status' => 1, 'order_id' => $razorpayOrderId,'key'=>$keyId);
            return response()->json($res);
        } else {
            $res = array('status' => 0);
            return response()->json($res);
        }
    }

    public function onpageRazorpayResponse(Request $request)
    {
        $data = $request->all();

        //store the payment response
        try {
            $id = (int)$this->fetchOrder($data['razorpay_order_id']);
            $encode_response = json_encode($data);
            $this->saveResponseData($encode_response, $id);
        } catch (\Exception $e) {
        }

        if (isset($data['razorpay_payment_id'])) {
            $txnid = $data['razorpay_payment_id'];
            $string = $data['razorpay_order_id'] . "|" . $txnid;
            $id = (int)$this->fetchOrder($data['razorpay_order_id']);
            $invoice_details = DayBookings::where('booking_id', $id)->first();
            $hotel_id       = $invoice_details['hotel_id'];
            $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
            $company_id     = $company_dtls['company_id'];
            $getPaymentgateway_key = DB::table('paymentgateway_details')
                ->select('provider_name', 'credentials', 'fail_url')
                ->where('hotel_id', '=', $hotel_id)
                ->where('is_active', '=', 1)
                ->first();
            if (!$getPaymentgateway_key) {
                $getPaymentgateway_key = DB::table('payment_gateway_details')
                    ->select('provider_name', 'credentials', 'fail_url')
                    ->where('company_id', '=', $company_id)
                    ->first();
            }
            $credentials = json_decode($getPaymentgateway_key->credentials);
            // $keyId = 'rzp_test_kYzro6v3xfkAlE';
            // $keySecret = 'kcuj3onMFgIp46y08e3gqNrl';
            $keySecret = trim($credentials[0]->secret);
            $secureHash = hash_hmac('sha256', $string, $keySecret);
            if ($secureHash == $data['razorpay_signature']) {
                // $id=$this->fetchOrder($data['razorpay_order_id']);
                
                $url = $this->dayBookingController->successBooking($id, $txnid, 'NA', $data['razorpay_signature'], $id);

                
                if ($url != '') {
                    $url = $this->findURL($url, $id, $txnid);

                    $res = array('status' => 1, 'message'=>'Payment Successfull','url'=>$url,'invoice_id'=>$id);
                    return response()->json($res);
                }else{
                    $res = array('status' => 0, 'message'=>'Payment Failed');
                    return response()->json($res);
                }
            }else{
                $res = array('status' => 0, 'message'=>'Payment Failed');
                    return response()->json($res);
            }
        }else{
            $res = array('status' => 0, 'message'=>'Payment Failed');
                    return response()->json($res);
        }      
    }

    public function pgDetails($company_id, $hotel_id)
    {

        $pg_details = DB::table('paymentgateway_details')
            ->select('provider_name', 'credentials', 'fail_url')
            ->where('hotel_id', '=', $hotel_id)
            ->where('is_active', '=', 1)
            ->first();
        if (!$pg_details) {
            $pg_details = DB::table('payment_gateway_details')
                ->select('provider_name', 'credentials', 'fail_url')
                ->where('company_id', '=', $company_id)
                ->first();
        }
        return $pg_details;
    }

    public function fetchOrder($order_id)
    {
        $row = DB::table('online_transaction_details')
            ->select('invoice_id')
            ->where('transaction_id', '=', $order_id)
            ->first();
        return $row->invoice_id;
    }
}
