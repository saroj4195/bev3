<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\UserNew;
use App\UserOtpLog;
use App\Invoice;
use DB;
use App\PaidServices;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use App\HotelBooking;
use App\CancellationPolicy;
use App\CurrentRate;
use App\HotelInformation;
use App\ImageTable;
use App\DayBookings;
use App\CompanyDetails;
use App\Refund;
use App\HotelCancellationPolicy;

class UserAuthController extends Controller
{

    public function userSignIn(Request $request)
    {

        $user_data = $request->all();
        $email_id = $user_data['email_id'];
        $mobile = $user_data['mobile'];
        $login_details = [];
        if ($email_id != '') {
            $userDetails = userNew::where('email_id', $email_id)->first();
            if ($userDetails) {
                $exp_time = strtotime("+1 day"); //Expirition time
                $tp = "Guest";
                $scope = ['login'];
                $token = $this->jwt($userDetails->user_id, $exp_time, $tp, $scope, 0);
                $login_details['token'] = $token;
            } else {
                $res = array('status' => 0, 'message' => "User Signin failed");
                return response()->json($res);
            }
        } else {
            $userDetails = userNew::where('mobile', $mobile)->first();
        }

        if ($userDetails) {
            $res = array('status' => 1, 'data' => $login_details, 'message' => "User Signin successfull");
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => "No reservation found for this number.");
            return response()->json($res);
        }
    }

    public function sendOtp(Request $request)
    {
        $data = $request->all();
        $uniq = mt_rand(1000, 9999);
        $country_code = '+91';
 

        // if($country_code == '+91'){
        //     $type = "pinacle";
        //     $senderid = "BKJINI";
        //     $dlttempid = "1507165941583723245";
        //     $to = $data['mobile'];
        // }else{
            $type = "pinpoint";
            $senderid = "BKJINI";
            $dlttempid = "";
            $to = $country_code.$data['mobile'];
        // }

        $post_datas = array(
            "mobile_number" => $to,
            "message" => ('Dear customer, ' . $uniq . '  is your verification code,please enter this to proceed with login otherwise it will expire in 1 minute.Regards,BKJINI'),
            "type" => $type,
            "senderid" => $senderid,
            "dlttempid" => $dlttempid
        );

        $array_post_fields = json_encode($post_datas);

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://tools.bookingjini.com/smsbird',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>$array_post_fields,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: hRv8FpLbN7'
        ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);

        // $messageToSend = urlencode('Dear customer,' . $uniq . ' is your verification code,please enter this to proceed with login otherwise it will expire in 1 minute.Regards,BKJINI');

        // $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?userid=1135&password=F4lKwI80ROA51fyq&sender=BKJINI&to=" . $to . "&message=" . $messageToSend . "&reqid=1&format={json|text}&route_id=3";

        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $smsURL);
        // curl_setopt($ch, CURLOPT_HEADER, 0);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_HEADER, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        // $output = curl_exec($ch);

        if ($res->status=='sent') {
            $generated_on = date('y-m-d H:i:s');
            $expire_time = date('Y-m-d H:i:s', strtotime("+5 minutes"));
            $userOtpDetails['mobile'] = $data['mobile'];
            $userOtpDetails['otp'] = $uniq;
            $userOtpDetails['generated_on'] = $generated_on;
            $userOtpDetails['send_on'] = $generated_on;
            $userOtpDetails['expire_at'] = $expire_time;
            $userOtpDetails['status'] = 'Pending';

            $res = UserOtpLog::insert($userOtpDetails);

            return response()->json(array(
                'status' => 1,
                //  'otp' => $uniq,
                  'message' => 'Otp send'));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Otp send failed'));
        }
    }

    public function verifyOtp(Request $request)
    {

        $otp_details = $request->all();
        $mobile = $otp_details['mobile'];
        $otp = $otp_details['otp'];

        $check_otp_status = UserOtpLog::where('mobile', $mobile)->where('status', 'Pending')->orderBy('id', 'desc')->first();

        if ($check_otp_status) {
            $current_time = date('Y-m-d H:i:s');
            $expire_time = $check_otp_status->expire_at;
            $id = $check_otp_status->id;

            if ($current_time > $expire_time) {
                $check_otp_status = UserOtpLog::where('mobile', $mobile)->where('id', $id)->update(['status' => 'Expired']);
                return response()->json(array('status' => 2, 'message' => 'Otp Expired'));
            }

            if ($check_otp_status->otp == $otp) {
                $userDetails = userNew::leftjoin('kernel.country_table','country_table.country_id','user_table_new.country')
                ->leftjoin('kernel.state_table','state_table.state_id','user_table_new.state')
                ->leftjoin('kernel.city_table','city_table.city_id','user_table_new.city')
                ->select('user_table_new.*','country_table.country_name','state_table.state_name','city_table.city_name')
                ->where('user_table_new.mobile', $mobile)
                ->first();
                
                $exp_time = strtotime("+1 day"); //Expirition time
                $tp = "Guest";
                $scope = ['login'];
                $token = $this->jwt($userDetails->user_id, $exp_time, $tp, $scope, 0);
                $user_id = base64_encode($userDetails->user_id);

                $login_details =[
                    'user_id' => $user_id,
                    'token' => $token,
                    'name' => $userDetails->first_name .' '. $userDetails->last_name,
                    'mobile' => $userDetails->mobile,
                    'email_id' => $userDetails->email_id,
                    'country' => isset($userDetails->country)?$userDetails->country:0,
                    'state' => isset($userDetails->state)?$userDetails->state:0,
                    'city' => (int)isset($userDetails->city)?$userDetails->city:0,
                    'country_name' => $userDetails->country_name,
                    'state_name' => $userDetails->state_name,
                    'city_name' => $userDetails->city_name,
                    'address' => $userDetails->locality,
                    'zip_code' => isset($userDetails->zip_code) ? $userDetails->zip_code:"",
                    'company_name' => $userDetails->company_name,
                    'gstin' => $userDetails->gstin,
                ];

                $check_otp_status = UserOtpLog::where('mobile', $mobile)->where('id', $id)->update(['status' => 'Verified']);

                return response()->json(array('status' => 1, 'data' => $login_details, 'message' => 'Otp verified'));
            } else {
                return response()->json(array('status' => 3, 'message' => 'Wrong Otp'));
            }
        } else {
            return response()->json(array('status' => 0, 'message' => 'Already verified'));
        }
    }

    public function fetchGuestBookingListOld(Request $request)
    {
        $data = $request->all();
        if (isset($request->auth->user_id)) {
            $user_id = $request->auth->user_id;
            $booking_status = $data['booking_status'];
            $from_date = date('Y-m-d', strtotime($data['from_date']));
            $to_date = date('Y-m-d', strtotime($data['to_date']));

            if ($booking_status == 'completed') {
                $booking_status = 1;
            }elseif ($booking_status == 'upcoming') {
                $booking_status = 1;

                $currentDate = date('Y-m-d'); 
                $from_date = date('Y-m-d', strtotime($currentDate));
                $to_date = date('Y-m-d', strtotime('+365 days', strtotime($from_date)));
            } else {
                $booking_status = 3;
            }

            $invoice_details = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                ->join('kernel.user_table_new', 'invoice_table.user_id_new', '=', 'user_table_new.user_id')
                ->select(
                    'invoice_table.hotel_name',
                    'invoice_table.total_amount',
                    'invoice_table.paid_amount',
                    'invoice_table.invoice',
                    'invoice_table.hotel_id',
                    'invoice_table.invoice_id',
                    'invoice_table.user_id',
                    'invoice_table.booking_status',
                    'invoice_table.booking_date',
                    'invoice_table.room_type',
                    'invoice_table.updated_at',
                    'hotel_booking.check_in',
                    'hotel_booking.check_out',
                    'user_table_new.first_name',
                    'user_table_new.last_name'
                )
                ->where([
                    ['invoice_table.user_id_new', $user_id],
                    ['invoice_table.booking_status', $booking_status],
                    ['hotel_booking.check_in', '>=', $from_date],
                    ['hotel_booking.check_in', '<=', $to_date]
                ])->groupby('invoice_table.invoice_id')
                ->get();

            
            foreach ($invoice_details as $detail) {
            
                $room_type_details = HotelBooking::where('invoice_id', $detail->invoice_id)->get();
                $room_type_array = [];
                foreach ($room_type_details as $key => $room_type) {
                    $details = DB::table('kernel.room_rate_plan')
                        ->join('kernel.room_type_table', 'kernel.room_rate_plan.room_type_id', 'room_type_table.room_type_id')
                        ->select('room_type_table.room_type', 'room_type_table.image')
                        ->where('room_rate_plan.room_type_id', $room_type->room_type_id)
                        ->first();

                    $rate_plan_info = json_decode($detail->room_type);
                    if($rate_plan_info){
                    $rate_info = explode('(', $rate_plan_info[$key]);

                    if (isset($rate_info[1])) {
                        $rate_plan_end = end($rate_info);
                        if (isset($rate_plan_end)) {
                            $rate_info_sep = explode(')', $rate_plan_end);
                            $rate_plan_dtl = $rate_info_sep[0];
                        } else {
                            $rate_plan_dtl = 'NA';
                        }
                    } else {
                        $rate_plan_dtl = 'NA';
                    }
                }else{
                    $rate_plan_dtl = 'NA';
                }

                    $room_details = [
                        'no_of_rooms' => $room_type->rooms,
                        'room_type_name' => isset($details->room_type)?$details->room_type:'NA',
                        'plan_type' => $rate_plan_dtl,
                    ];

                    array_push($room_type_array, $room_details);
                }
                $detail->room_details = $room_type_array;

                $detail->check_in = date('d M, Y', strtotime($detail->check_in));
                $detail->check_out = date('d M, Y', strtotime($detail->check_out));
                $detail->booking_date = date('d M, Y', strtotime($detail->booking_date));

                $date1 = date_create($detail->check_in);
                $date2 = date_create($detail->check_out);
                $diff = date_diff($date1, $date2);
                $no_of_nights = $diff->format("%a");
                if ($no_of_nights == 0) {
                    $no_of_nights = 1;
                }

                $detail->hotel_name = $detail->hotel_name;
                $detail->no_of_night = $no_of_nights;

                $booking_id     = date("dmy", strtotime($detail->booking_date)) . str_pad($detail->invoice_id, 4, '0', STR_PAD_LEFT);
                $detail->booking_id =  $booking_id;
                $detail->invoice_id =  $detail->invoice_id;
                $detail->invoice = str_replace("#####", $booking_id, $detail->invoice);

                if ($data['booking_status'] == 'cancelled') {
                    $detail->cancelled_date = date('d M, Y', strtotime($detail->updated_at));
                    $cancelled_date = date("Y-m-d", strtotime($detail->updated_at));

                    $check_in_date = $detail->check_in;

                    if ($check_in_date) {
                        $days = abs(strtotime($check_in_date) - strtotime($cancelled_date)) / 86400;
                        if ($days >= 0) {
                            $get_cancellation_policy = CancellationPolicy::where('hotel_id', $data['hotel_id'])->first();
                            if ($get_cancellation_policy) {
                                $closest = null;
                                $refund_days = $get_cancellation_policy->policy_data;
                                $refund_days = json_decode($refund_days);
                                for ($i = 0; $i < sizeof($refund_days); $i++) {
                                    $ref_data = explode(':', $refund_days[$i]);
                                    $ref_per = $ref_data[1];
                                    $daterange = explode('-', $ref_data[0]);
                                    if ($days  >= $daterange[0] && $days  <= $daterange[1]) {
                                        $detail->refund_amount = $detail->total_amount * ($ref_per / 100);
                                    } else {
                                        $detail->refund_amount = 0;
                                    }
                                }
                            } else {
                                $detail->refund_amount = 0;
                            }
                        }
                    }
                    // $room_dlt = json_decode($detail->room_type);
                }
                $detail->booking_type = 'Room(s)';
            }
            if (sizeof($invoice_details) > 0) {
                $res = array('status' => 1, 'message' => 'details retrive sucessfully', 'details' => $invoice_details);
                return response()->json($res);
            } else {
                $res = array('status' => 1, 'message' => 'Bookings not found', 'details' => []);
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, 'message' => 'details retrive fails');
            return response()->json($res);
        }
    }

    public function guestInfo(Request $request){

        if (isset($request->auth->user_id)) {
            $user_id = $request->auth->user_id;

            $userDetails = userNew::leftjoin('kernel.country_table','country_table.country_id','user_table_new.country')
            ->leftjoin('kernel.state_table','state_table.state_id','user_table_new.state')
            ->leftjoin('kernel.city_table','city_table.city_id','user_table_new.city')
            ->select('user_table_new.*','country_table.country_name','state_table.state_name','city_table.city_name')
            ->where('user_table_new.user_id', $user_id)
            ->first();

            if($userDetails){
                $login_details =[
                    'user_id' => $user_id,
                    'name' => $userDetails->first_name .' '. $userDetails->last_name,
                    'mobile' => $userDetails->mobile,
                    'email_id' => $userDetails->email_id,
                    'country' => isset($userDetails->country)?$userDetails->country:0,
                    'state' => isset($userDetails->state)?$userDetails->state:0,
                    'city' => (int)isset($userDetails->city)?$userDetails->city:0,
                    'country_name' => $userDetails->country_name,
                    'state_name' => $userDetails->state_name,
                    'city_name' => $userDetails->city_name,
                    'address' => $userDetails->locality,
                    'zip_code' => isset($userDetails->zip_code) ? $userDetails->zip_code:"",
                    'company_name' => $userDetails->company_name,
                    'gstin' => $userDetails->gstin,
                ];
                return response()->json(array('status' => 1, 'data' => $login_details));
            }else{
                return response()->json(array('status' => 0, 'message' => 'Guest details fetched failed'));
            }
        }else{
            return response()->json(array('status' => 0, 'message' => 'Guest details fetched failed'));
        }
    }

    protected function jwt($user_id, $exptime, $tp, $scope, $hot_id)
    {
        $xsrftoken = md5(uniqid(rand(), true));
        $payload = [
            'iss' => env('APP_DOMAIN'), // Issuer of the token
            'user_id' => $user_id, // Subject of the token
            'iat' => time(), // Time when JWT was issued.
            'exp' => $exptime, // Expiration time
            'type' => $tp, //super admin
            'hot_id' => $hot_id, // Hotel ID ===> It is applicable for Public Login Otherwise it will be {zero}
            'scope' => $scope,
            'xsrfToken' => $xsrftoken,
        ];
        return JWT::encode($payload, 'h0nzq8HudRGm2JU6X7klvzmRjSMpZE5K', 'HS256');
    }

    public function bookings(string $api_key, Request $request)
    {
        // Store the log
        $logpath = storage_path("logs/prip-cart.log" . date("Y-m-d"));
        $logfile = fopen($logpath, "a+");
        fwrite($logfile, "Processing starts at: " . date("Y-m-d H:i:s") . "\n");
        fclose($logfile);

        $data = $request->all();
        $booking_details = $data['booking_details'];
        $hotel_id = $booking_details['hotel_id'];
        $payment_mode = $booking_details['payment_mode'];
        $cart_info = json_encode($data);

        $logfile = fopen($logpath, "a+");
        fwrite($logfile, "cart data: " . $hotel_id . $cart_info . "\n");
        fwrite($logfile, "Processing ends at: " . date("Y-m-d H:i:s") . "\n");
        fclose($logfile);

        $invoice = new Invoice();
        $room_details = $data['room_details'];
        $user_details = $data['user_details'];
        $amount_to_pay = $booking_details['amount_to_pay'];
        $currency = $booking_details['currency'];
        $check_in = date('Y-m-d', strtotime($booking_details['checkin_date']));
        $check_out = date('Y-m-d', strtotime($booking_details['checkout_date']));
        $checkin = date_create($check_in);
        $checkout = date_create($check_out);
        $date = date('Y-m-d');
        $diff = date_diff($checkin, $checkout)->format("%a");
        $diff = ($diff == 0) ? 1 : $diff;

        // Check-in date validation
        if ($date > $check_in) {
            $res = array('status' => 0, 'message' => "Check-in date must not be past date.");
            return response()->json($res);
        }

        // Hotel Validation
        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('company_id', 'gst_slab', 'is_taxable', 'partial_pay_amt')->first();
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 0, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }

        $company_name = DB::table('kernel.company_table')->where('company_id', $hotel_info->company_id)->first();

        // User registration
        $user_data = [
            'first_name' => $user_details['first_name'],
            'last_name' => $user_details['last_name'],
            'email_id' => $user_details['email_id'],
            'mobile' => $user_details['mobile'],
            'password' => Hash::make(uniqid()), // Generate a unique random number and encrypt it for password
            'country' => $user_details['country'],
            'state' => $user_details['state'],
            'city' => $user_details['city'],
            'zip_code' => $user_details['zip_code'],
            'company_name' => $company_name->company_full_name,
            'GSTIN' => $user_details['GST_IN'],
        ];

        $res = User::updateOrCreate(
            [
                'mobile' => $user_details['mobile'],
                'company_id' => $hotel_info->company_id
            ],
            $user_data
        );

        $user_id = $res->user_id;
        $user_data['locality'] = $user_details['address'];
        $user_name = $user_details['mobile'] ?? $user_details['email_id'];
        $user_data['user_name'] = $user_name;
        $user_data['bookings'] = '';
        $user_res = UserNew::updateOrCreate(['mobile' => $user_details['mobile']], $user_data);
        $user_id_new = $user_res->user_id;

        // Get public coupon
        // $coupons = $this->allPublicCupons($hotel_id, $check_in, $check_out);

        // Store invoice details
        $inv_data = [
            'hotel_id' => $hotel_id,
            'hotel_name' => $this->getHotelInfo($hotel_id)->hotel_name,
            'room_type' => json_encode($this->prepareRoomTypes($room_details)),
            'ref_no' => rand() . strtotime("now"),
            'check_in_out' => "[" . $check_in . '-' . $check_out . "]",
            'booking_date' => date('Y-m-d H:i:s'),
            'booking_status' => 2,
            'user_id' => $user_id,
            'user_id_new' => $user_id_new,
            'getCompanyId' => HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first()->company_id,
            'is_cm' => in_array('Channel Manager', json_decode(BillingDetails::select('product_name')->where('company_id', $company_id)->first()->product_name)) ? 1 : 0,
            'visitors_ip' => $this->ipService->getIPAddress(),
            'booking_source' => "website",
            'guest_note' => $user_details['guest_note'],
            'arrival_time' => $user_details['arrival_time'],
            'company_name' => $user_details['company_name'],
            'gstin' => $user_details['GST_IN'],
            'agent_code' => ''
        ];

        // Room price calculation
        $room_price_including_gst = 0;
        $room_price_excluding_tax = 0;
        $total_gst_price = 0;
        $total_discount_price = 0;
        $extra_details = [];
        $hotel_booking_details = [];
        $cart = [];

        if (sizeof($room_details) > 0) {
            $invoice_details_array = [];
            $per_room_occupancy = [];

            foreach ($room_details as $details) {
                // ... Rest of your code for room details processing ...
            }

            // Paid services calculations
            // ... Rest of your code for paid services ...

            // Addon Charges calculations
            // ... Rest of your code for addon charges ...

            // Additional calculations and checks
            // ...

            // Finally, return the response
            // ...
        } else {
            $res = array('status' => 0, 'message' => "Booking failed invalid room type");
            return response()->json($res);
        }



        // Initialize variables
        $room_price_including_gst = 0;
        $room_price_excluding_tax = 0;
        $total_gst_price = 0;
        $total_discount_price = 0;
        $extra_details = [];
        $hotel_booking_details = [];
        $cart = [];

        if (empty($room_details)) {
            $res = ['status' => 0, 'message' => 'Booking failed invalid room type'];
            return response()->json($res);
        }

        $invoice_details_array = [];
        $per_room_occupancy = [];

        foreach ($room_details as $details) {
            $room_type_id = $details['room_type_id'];
            $rate_plan_id = $details['rate_plan_id'];
            $occupancy_details = $details['occupancy'];

            // Extract occupancy details
            $extra_details[] = [$room_type_id => array_map(function ($od) {
                return [$od['adult'], $od['child']];
            }, $occupancy_details)];

            // Fetch room type details
            $room_type_details = MasterRoomType::where('hotel_id', $hotel_id)
                ->where('room_type_id', $room_type_id)
                ->where('is_trash', 0)
                ->first();

            if (empty($room_type_details)) {
                $res = ['status' => 0, 'message' => 'Invalid room types'];
                return response()->json($res);
            }

            $base_adult = $room_type_details->max_people;
            $base_child = $room_type_details->max_child;

            $room_rate = CurrentRate::where('hotel_id', $hotel_id)
                ->where('room_type_id', $room_type_id)
                ->where('rate_plan_id', $rate_plan_id)
                ->where('ota_id', '-1')
                ->whereBetween('stay_date', [$check_in, $check_out])
                ->get()->keyBy('stay_date');

            $check_in_data = $check_in;
            $coupons_percentage_all = [];
            $booking = [];
            $public_coupon_array = [];

            for ($i = 0; $i < $diff; $i++) {
                $d = $check_in_data;
                $private_coupon_id = $booking_details['private_coupon'];

                $coupons = Coupons::where('hotel_id', $hotel_id)
                    ->where('valid_from', '<=', $d)
                    ->where('valid_to', '>=', $d)
                    ->whereIn('room_type_id', [0, $room_type_id])
                    ->where('is_trash', 0)
                    ->get();

                $multiple_coupon_one_date = [];
                foreach ($coupons as $coupon) {
                    if ($coupon->coupon_for == 1) {
                        $multiple_coupon_one_date[] = $coupon->discount;
                    }
                    if ($coupon->coupon_for == 2) {
                        $private_coupon_array[$d] = $coupon->discount;
                    }
                }

                if (!empty($multiple_coupon_one_date)) {
                    $public_coupon_array[$d] = max($multiple_coupon_one_date);
                } else {
                    $public_coupon_array[$d] = 0;
                }

                $coupons_percentage = $private_coupon_id !== '' ?
                    max($private_coupon_array[$d], $public_coupon_array[$d]) :
                    $public_coupon_array[$d];

                $room_occu = [];
                foreach ($occupancy_details as $key => $acc) {
                    $adult = $acc['adult'];
                    $child = $acc['child'];

                    $multiple_occupancy = json_decode($room_rate[$d]->multiple_occupancy, true);
                    if (is_string($multiple_occupancy)) {
                        $multiple_occupancy = json_decode($multiple_occupancy, true);
                    }
                    $extra_adult = 0;
                    $extra_child = max(0, $child - $base_child);
                    $bar_price = $room_rate[$d]->bar_price;
                    $extra_adult_price = $room_rate[$d]->extra_adult_price;
                    $extra_child_price = $room_rate[$d]->extra_child_price;

                    if ($base_adult == $adult) {
                        $room_price = $bar_price;
                    } elseif ($base_adult > $adult) {
                        $acc = $adult - 1;
                        $room_price = $multiple_occupancy[$acc] ?? $bar_price;
                    } else {
                        $extra_adult = $adult - $base_adult;
                        $room_price = $bar_price;
                    }

                    $discounted_price = $room_price * $coupons_percentage / 100;
                    $price_after_discount = $room_price - $discounted_price;

                    // GST calculation
                    $gst_price = 0;
                    if ($hotel_info->is_taxable == 1) {
                        $price_for_gst_slab = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);
                        $gst_percentage = $hotel_info->gst_slab == 1 ?
                            $this->checkGSTPercent($price_for_gst_slab) :
                            $this->checkGSTPercent($price_after_discount);
                        $room_price_excluding_gst = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);
                        $gst_price = $room_price_excluding_gst * $gst_percentage / 100;
                    } else {
                        $room_price_excluding_gst = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);
                    }

                    $room_price_excluding_tax += $room_price_excluding_gst;
                    $room_price_including_gst += $room_price_excluding_gst + $gst_price;
                    $total_gst_price += $gst_price;
                    $total_discount_price += $discounted_price;

                    // Store the details in invoice details
                    $invoice_details = [
                        'hotel_id' => $hotel_id,
                        'user_id' => 1,
                        'room_type_id' => $details['room_type_id'],
                        'rate_plan_id' => $details['rate_plan_id'],
                        'date' => $d,
                        'room_rate' => $room_price,
                        'extra_adult' => $extra_adult,
                        'extra_child' => $extra_child,
                        'extra_adult_price' => $extra_adult_price,
                        'extra_child_price' => $extra_child_price,
                        'discount_price' => (float)$discounted_price,
                        'price_after_discount' => (float)($room_price - $discounted_price),
                        'rooms' => $key + 1,
                        'gst_price' => (float)$gst_price,
                        'total_price' => (float)($room_price_excluding_gst + $gst_price),
                    ];
                    $invoice_details_array[] = $invoice_details;

                    // Prepared cart details
                    $bookings = [
                        'selected_adult' => $adult,
                        'selected_child' => $child,
                        'rate_plan_id' => $details['rate_plan_id'],
                        'extra_adult_price' => 0,
                        'extra_child_price' => 0,
                        'bar_price' => $room_price,
                    ];
                    $booking[] = $bookings;

                    $check_in_data = date('Y-m-d', strtotime($d . ' +1 day'));
                }

                $per_room_occupancy[] = $room_occu;

                // Store the data in hotel booking table
                $hotel_booking_data = [
                    'room_type_id' => $details['room_type_id'],
                    'rooms' => count($occupancy_details),
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'booking_status' => 2, // Initially Unpaid
                    'user_id' => 1,
                    'booking_date' => date('Y-m-d'),
                    'hotel_id' => $hotel_id,
                ];
                $hotel_booking_details[] = $hotel_booking_data;

                $cart[] = [
                    'rooms' => $booking,
                    'room_type_id' => $details['room_type_id'],
                    'room_type' => $room_type_details->room_type,
                ];
            }

            // Paid services calculations
            $paid_service = $data['paid_service'];
            $paid_service_id = [];
            $total_paidservices_amount = 0;

            if ($paid_service) {
                $paid_service_id = array_column($paid_service, 'service_no');

                $services = DB::table('paid_service')
                    ->where('hotel_id', $hotel_id)
                    ->whereIn('paid_service_id', $paid_service_id)
                    ->select('paid_service_id', 'service_amount', 'service_tax')
                    ->where('is_trash', 0)
                    ->get();

                foreach ($services as $key => $service) {
                    $paid_service_amount = $service->service_amount;
                    $paid_service_tax = $paid_service_amount * $service->service_tax / 100;
                    $total_paidservices_amount += ($paid_service_amount + $paid_service_tax) * $paid_service[$key]['qty'];
                }
            }

            // Addon Charges calculations
            $add_on_charges = DB::table('kernel.add_on_charges')
                ->where('hotel_id', $hotel_id)
                ->where('is_active', 1)
                ->get();

            $addon_charges = 0;

            if (sizeof($add_on_charges) > 0) {
                foreach ($add_on_charges as $add_on_charge) {
                    $add_on_percentage = $add_on_charge->add_on_charges_percentage;
                    $add_on_tax_percentage = $add_on_charge->add_on_tax_percentage;
                    $add_on_price = $room_price_excluding_tax * $add_on_percentage / 100;

                    $add_on_tax_price = 0;
                    if ($add_on_tax_percentage) {
                        $add_on_tax_price = $add_on_price * $add_on_tax_percentage / 100;
                    }
                    $addon_charges += $add_on_price + $add_on_tax_price;
                }
            }

            $room_price_including_gst = $room_price_including_gst + $total_paidservices_amount + $addon_charges;

            if ($currency != 'INR') {
                $currency_value = CurrencyDetails::where('name', $currency)->first();
                $amount_to_pay = (1 / $currency_value->currency_value) * $amount_to_pay;
            }

            $total_amount = $room_price_including_gst;

            if ($payment_mode == 2) {
                $partial_pay_per = $hotel_info->partial_pay_amt;
                $room_price_including_gst = $room_price_including_gst * $partial_pay_per / 100;
            } elseif ($payment_mode == 3) {
                $room_price_including_gst = 0;
            }

            $room_price_including_gst = number_format((float)$room_price_including_gst, 2, '.', '');

            if ($amount_to_pay != $room_price_including_gst) {
                $res = ['status' => 0, 'message' => 'Booking failed due to data tampering', 'data' => $room_price_including_gst];
                return response()->json($res);
            }
        }
    }

    public function fetchGuestBookingList(Request $request)
    {
        $data = $request->all();
        $user_id =  $request->auth->user_id;
        $booking_status = $data['booking_status'];
        $hotel_id = $data['hotel_id'];
        $filter_status = $data['filter_status'];
        $from_date = date('Y-m-d', strtotime($data['from_date']));
        $to_date = date('Y-m-d', strtotime($data['to_date']));

        if ($booking_status == 'completed') {
            $booking_status = 1;
        } elseif ($booking_status == 'upcoming') {
            $booking_status = 1;

            $currentDate = date('Y-m-d');
            $from_date = date('Y-m-d', strtotime($currentDate));
            $to_date = date('Y-m-d', strtotime('+365 days', strtotime($from_date)));
        } else {
            $booking_status = 3;
        }

        if ($filter_status == 0 || $filter_status == 3) {
            $day_booking_details = DayBookings::join('kernel.user_table_new', 'day_bookings.user_id', '=', 'user_table_new.user_id')
                ->join('kernel.hotels_table', 'day_bookings.hotel_id', '=', 'hotels_table.hotel_id')
                ->select(
                    'hotels_table.hotel_name',
                    'day_bookings.booking_id',
                    'day_bookings.package_name',
                    'day_bookings.no_of_guest',
                    'day_bookings.outing_dates',
                    'day_bookings.total_amount',
                    'day_bookings.paid_amount',
                    'day_bookings.updated_at',
                    'user_table_new.first_name',
                    'user_table_new.last_name'
                )
                ->where([
                    // ['day_bookings.user_id', $user_id],
                    ['day_bookings.booking_status', $booking_status],
                    ['day_bookings.outing_dates', '>=', $from_date],
                    ['day_bookings.outing_dates', '<=', $to_date]
                ])
                ->get();

            foreach ($day_booking_details as $b_detail) {
                $invoice_id = $b_detail->booking_id;
                $invoice = $this->bookingVoucher($invoice_id);
                // $invoice = '';
                $b_detail->invoice = $invoice;

                if ($data['booking_status'] == 'cancelled') {
                    $b_detail->cancelled_date = date('d M, Y', strtotime($b_detail->updated_at));
                    $b_detail->refund_amount = 0;
                }
                $b_detail->is_cancellable = 0;
                $b_detail->booking_type = 'DayBookings';
            }
        }


        if ($filter_status != 3) {
            $invoice_details = Invoice::join('booking_engine.hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.user_table_new', 'invoice_table.user_id_new', '=', 'user_table_new.user_id')
            ->select(
                'invoice_table.hotel_name',
                'invoice_table.total_amount',
                'invoice_table.paid_amount',
                'invoice_table.invoice',
                'invoice_table.hotel_id',
                'invoice_table.invoice_id',
                'invoice_table.user_id',
                'invoice_table.package_id',
                'invoice_table.booking_status',
                'invoice_table.booking_date',
                'invoice_table.room_type',
                'invoice_table.updated_at',
                'hotel_booking.check_in',
                'hotel_booking.check_out',
                'user_table_new.first_name',
                'user_table_new.last_name'
            )
               
                ->where([
                    ['invoice_table.user_id_new', $user_id],
                    ['invoice_table.booking_status', $booking_status],
                    ['hotel_booking.check_in', '>=', $from_date],
                    ['hotel_booking.check_in', '<=', $to_date]
                ])->groupby('invoice_table.invoice_id');

            if ($filter_status == 2) {
                $invoice_details = $invoice_details->where('invoice_table.package_id', '>', 0);
            } else {
                $invoice_details = $invoice_details->where('invoice_table.package_id', 0);
            }

            $invoice_details = $invoice_details->get();
        
            foreach ($invoice_details as $detail) {

                if($hotel_id == 2600){
                $refund_status = Refund::where('booking_id',$detail->invoice_id)->select('refund_status')->first();
                $detail->refund_status = isset($refund_status->refund_status) ? $refund_status->refund_status : null ; 
                }

                $room_type_details = DB::table('booking_engine.hotel_booking')->where('invoice_id', $detail->invoice_id)->get();

                $room_type_array = [];
                foreach ($room_type_details as $key => $room_type) {
                    $details = DB::table('kernel.room_rate_plan')
                        ->join('kernel.room_type_table', 'kernel.room_rate_plan.room_type_id', 'room_type_table.room_type_id')
                        ->select('room_type_table.room_type', 'room_type_table.image')
                        ->where('room_rate_plan.room_type_id', $room_type->room_type_id)
                        ->first();
    
                    $rate_plan_info = json_decode($detail->room_type);
                    if ($rate_plan_info) {
                        $rate_info = explode('(', $rate_plan_info[$key]);
    
                        if (isset($rate_info[1])) {
                            $rate_plan_end = end($rate_info);
                            if (isset($rate_plan_end)) {
                                $rate_info_sep = explode(')', $rate_plan_end);
                                $rate_plan_dtl = $rate_info_sep[0];
                            } else {
                                $rate_plan_dtl = 'NA';
                            }
                        } else {
                            $rate_plan_dtl = 'NA';
                        }
                    } else {
                        $rate_plan_dtl = 'NA';
                    }
    
                    $room_details = [
                        'no_of_rooms' => $room_type->rooms,
                        'room_type_name' => isset($details->room_type) ? $details->room_type : 'NA',
                        'plan_type' => $rate_plan_dtl,
                    ];
    
                    array_push($room_type_array, $room_details);
                }
                $detail->room_details = $room_type_array;

                $detail->check_in = date('d M, Y', strtotime($detail->check_in));
                $detail->check_out = date('d M, Y', strtotime($detail->check_out));
                $detail->booking_date = date('d M, Y', strtotime($detail->booking_date));
        
                $no_of_nights = max(1, date_diff(date_create($detail->check_in), date_create($detail->check_out))->days);
                $detail->no_of_night = $no_of_nights;
        
                $booking_id = date("dmy", strtotime($detail->booking_date)) . str_pad($detail->invoice_id, 4, '0', STR_PAD_LEFT);
                $detail->booking_id = $booking_id;
                $detail->invoice_id = $detail->invoice_id;
                $detail->invoice = str_replace("#####", $booking_id, $detail->invoice);
        
                if ($data['booking_status'] == 'cancelled') {
                    $detail->cancelled_date = date('d M, Y', strtotime($detail->updated_at));
                    $cancelled_date = strtotime($detail->cancelled_date);
                    $check_in_date = strtotime($detail->check_in);
        
                    if ($check_in_date && $cancelled_date >= $check_in_date) {
                        // if($hotel_id == 2600){
                        //     $cancellation = HotelCancellationPolicy::where('hotel_id', $request->hotel_id)
                        //     ->where('valid_from', '<=', $detail->check_in)
                        //     ->where('valid_to', '>=', $detail->check_in)
                        //     ->select('cancellation_rules')
                        //     ->first();
                        //     if(!$cancellation){
                        //         $detail->refund_amount = 0 ;
                        //     }else{

                        //     $pg_charge_per = 2; // in percentage 

                        //     $date1 = date_create(date('Y-m-d'));
                        //     $date2 = date_create($detail->check_in);
                        //     $diff = date_diff($date1, $date2);
                        //     $diff = $diff->format("%a");

                        //     $datas = json_decode($cancellation->cancellation_rules);

                        //     foreach ($datas as $data) {
                        //         $data_n = explode(':', $data);
                        //         $days = $data_n[0];
                        //         $refund_per = $data_n[1];

                        //         if ($diff <= $days) {
                        //             $refund_percent = $refund_per;
                        //             break;
                        //         }
                        //     }

                        //     $paid_amount = $detail->paid_amount;
                        //     $refund_amount = $this->calculateRefundAmount($paid_amount, $pg_charge_per, $refund_percent);
                        //     $detail->refund_amount = $refund_amount;
                        // }

                        // }else{
                        $get_cancellation_policy = DB::table('booking_engine.cancellation_policy')->where('hotel_id', $data['hotel_id'])->first();
                        if ($get_cancellation_policy) {
                            $refund_days = json_decode($get_cancellation_policy->policy_data);
                            foreach ($refund_days as $refund_day) {
                                [$range, $percentage] = explode(':', $refund_day);
                                [$start, $end] = explode('-', $range);
                                if ($start <= $no_of_nights && $no_of_nights <= $end) {
                                    $detail->refund_amount = $detail->total_amount * ($percentage / 100);
                                    break;
                                }
                            }
                        } else {
                            $detail->refund_amount = 0;
                        }
                    //  }
                    }
                }
                $detail->is_cancellable = 0;
                $detail->booking_type = $detail->package_id ? 'Package' : 'Room(s)';
                if ($detail->package_id && $data['booking_status'] == 'cancelled') {
                    $detail->refund_amount = 0;
                }
            }
        }
        

        $filtered_details = [];
        //0-all,1-room,2-package,3-day
        if ($filter_status == 1 || $filter_status == 2) {
            $filtered_details = $invoice_details;
        } elseif ($filter_status == 3) {
            $filtered_details = $day_booking_details;
        }else{
            $filtered_details = $day_booking_details->merge($invoice_details);
        }

        if (empty($filtered_details)) {
            $res = array('status' => 0, 'message' => 'No details found');
        } else {
            $res = array('status' => 1, 'message' => 'Details retrieved successfully', 'details' => $filtered_details);
        }

        return response()->json($res);
    }

    private function calculateRefundAmount($paid_amount, $pg_charge_per, $refund_percent)
   {
    $pg_charge = $paid_amount * $pg_charge_per / 100;
    $amount_after_pg_charge = $paid_amount - $pg_charge;
    $refund_amount = $amount_after_pg_charge - ($amount_after_pg_charge * $refund_percent / 100);

    return $refund_amount;
   }


    public function bookingVoucher($invoice_id)
    {

        $dayOutingBookings =  DB::table('booking_engine.day_bookings')
            ->leftjoin('kernel.user_table_new', 'day_bookings.user_id', '=', 'user_table_new.user_id')
            ->where('day_bookings.booking_id', $invoice_id)
            ->select('day_bookings.*', 'user_table_new.first_name', 'user_table_new.last_name', 'user_table_new.email_id', 'user_table_new.mobile', 'user_table_new.locality')
            ->first();

        $dayBookingDetails = DayBookings::where('id', $dayOutingBookings->package_id)->first();

        $package_price = $dayBookingDetails->price;
        $price_after_discount = $dayBookingDetails->discount_price;
        $discount_price = $package_price - $price_after_discount;
        $tax_percentage = $dayBookingDetails->tax_percentage;

        $guest_note = $dayOutingBookings->guest_note;
        $expeted_arrival =  isset($dayOutingBookings->arrival_time)?$dayOutingBookings->arrival_time:'';
        // $package_time = date('h:i:s A', strtotime($dayBookingDetails->arrival_time));


        $hotel_details = HotelInformation::where('hotel_id', $dayOutingBookings->hotel_id)->first();

        $get_logo_info = CompanyDetails::select('logo')->where('company_id', $hotel_details->company_id)->first();
        $get_logo = ImageTable::select('image_name')->where('image_id', $get_logo_info->logo)->first();

        if (isset($get_logo->image_name)) {
            // $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $get_logo->image_name;
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        } else {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        }

        $booking_id = $dayOutingBookings->booking_id.date('dmy');
        $guest_name = $dayOutingBookings->first_name . ' ' . $dayOutingBookings->last_name;
        $mobile =  $dayOutingBookings->mobile;
        $email_id =  $dayOutingBookings->email_id;
        $address =  $dayOutingBookings->locality;

        $booking_date = date('d M Y', strtotime($dayOutingBookings->booking_date));
        $outing_date = date('d M Y', strtotime($dayOutingBookings->outing_dates));
        $package_name = $dayOutingBookings->package_name;
        $hotel_name = $hotel_details->hotel_name;

        $hotel_address = $hotel_details->hotel_address;
        $hotel_mobile = $hotel_details->mobile;
        $hotel_email_id = $hotel_details->email_id;
        $hotel_terms_and_cond = $hotel_details->terms_and_cond;



        $no_of_guest = $dayOutingBookings->no_of_guest;
        $tax_amount = $dayOutingBookings->tax_amount;

        $total_guest_price_excluding_tax = $price_after_discount * $no_of_guest;
        $total_guest_price_including_tax = $total_guest_price_excluding_tax + $tax_amount;

        $paid_amount = $dayOutingBookings->paid_amount;
        $pay_at_hotel = $total_guest_price_including_tax - $paid_amount;



        $body = '<!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@700" />
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Urbanist:wght@600;700" />
            <title></title>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <meta name="x-apple-disable-message-reformatting" />
            <style></style>
        </head>
        
        <body>
            <div
                style="font-size: 0px; line-height: 1px; mso-line-height-rule: exactly; display: none; max-width: 0px; max-height: 0px; opacity: 0; overflow: hidden; mso-hide: all">
            </div>
            <center lang="und" dir="auto"
                style="width: 100%; table-layout: fixed; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%">
                <table cellpadding="0" cellspacing="0" border="0" role="presentation" bgcolor="white" width="1157"
                    style="background-color: white; width: 1157px; border-spacing: 0; font-family: Urbanist">
                    <tr>
                        <td valign="top" class="force-w100" width="100.00%"
                            style="padding-top: 36px; padding-bottom: 60px; width: 100%">
                            <table class="force-w100" cellpadding="0" cellspacing="0" border="0" role="presentation"
                                width="100.00%" style="width: 100%; border-spacing: 0">
                                <tr>
                                    <td align="center" style="padding-bottom: 15.5px">
                                        <p
                                            style="font-family: Inter; font-size: 30px; font-weight: 700; color: #3d3d3d; margin: 0; padding: 0; line-height: 36px; mso-line-height-rule: exactly">
                                            Booking Confirmation</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 15.5px; padding-bottom: 11.5px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0; font-family: Proxima Nova">
                                            <tr>
                                                <td valign="middle" width="700" style="width: 700px">
                                                    <img src="' . $hotel_logo . '"
                                                        width="139" style="max-width: initial; width: 139px; display: block" />
                                                </td>
                                                <td align="right" valign="middle" width="377" style="width: 377px">
                                                    <table class="force-w100" cellpadding="0" cellspacing="0" border="0"
                                                        role="presentation" width="377" style="width: 377px; border-spacing: 0">
                                                        <tr>
                                                            <td align="right" style="padding-bottom: 8px">
                                                                <p
                                                                    style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 400">Booking
                                                                        No.</span>
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_id . '
                                                                    </span>
                                                                </p>
                                                            </td>
                                                        </tr>
        
                                                        <tr>
                                                            <td align="right" style="padding-bottom: 8px">
                                                                <p
                                                                    style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 400">Payment
                                                                        Reference Number: </span>
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_id . '
                                                                    </span>
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 11.5px; padding-bottom: 11px">
                                        <div width="1077" style="width: 1077px; border-top: 2px solid #e7e7e7"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 11px; padding-bottom: 6px; padding-left: 40px">
                                        <p
                                            style="font-size: 18px; font-weight: 600; color: #616161; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Dear ' . $guest_name . ',</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 6px; padding-bottom: 11px; padding-left: 40px">
                                        <p width="1077" height="66"
                                            style="font-size: 18px; font-weight: 600; text-align: left; color: #616161; margin: 0; padding: 0; width: 1077px; height: 66px">
                                            <span>Thank you for choosing </span>
                                            <span
                                                style="font-size: 18px; font-weight: 700; color: #1C5EAA; text-align: left">' . $hotel_name . '</span>
                                            <span>, as your property of choice for your visit and booking through our hotels website. Your booking confirmation details have been provided below: </span>
                                            
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 11px; padding-bottom: 4px; padding-left: 40px">
                                        <p
                                            style="font-size: 20px; font-weight: 600; color: #616161; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                            Property name</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 4px;">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="top" width="790" style="width: 790px">
                                                    <p
                                                        style="font-size: 34px; font-weight: 700; color: #1C5EAA; margin: 0; padding: 0; line-height: 41px; mso-line-height-rule: exactly">
                                                        <span>' . $hotel_name . ' </span>
                                                    </p>
                                                </td>
                                                <td align="right" valign="top" width="287"
                                                    style="padding-bottom: 4px; width: 287px">
                                                    <p style="font-family: Inter; font-size: 16px; font-weight: 400">
                                                        Booked on:
                                                        <span
                                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_date . '
                                                    </span>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 12px; padding-bottom: 8px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td width="533" style="padding-right: 7px; width: 533px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="100.00%" height="261"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 100%; height: 261px; border-spacing: 0">
                                                        <tr>
                                                            <td valign="top" style="padding-top: 26px; padding-bottom: 22px;  padding-left: 22px;">
                                                                <table align="left" cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" style="border-spacing: 0">
                                                                    <tr>
                                                                        <td align="center" valign="top" height="107"
                                                                            style="height: 107px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <p
                                                                                        style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                        Package Date</p>
        
                                                                                        <p
                                                                                            style="margin-top: 4px; margin-bottom: 0px;">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e;">
                                                                                                ' . $outing_date . '
                                                                                            </span>
                                                                                        </p>
        
                                                                                       
                                                                                    </td>
                                                                                    
                                                                                    
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="center" style="padding-top: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation"
                                                                                style="width: 100%; border-spacing: 0">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="padding-bottom: 6px">
                                                                                            <p
                                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                                Package Name</p>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td valign="middle"
                                                                                            style="padding-bottom: 6px;">
                                                                                            <p
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                ' . $package_name . '</p>
                                                                                        </td>

                                                                                    </tr>
                                                                                   
                                                                                </tbody>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td width="533" style="padding-left: 8px; width: 533px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="100.00%" height="261"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 100%; height: 261px; border-spacing: 0">
                                                        <tr>
                                                            <td align="left" valign="top"
                                                                style="padding-top: 26px; padding-bottom: 22px; padding-left: 24px">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" style="border-spacing: 0">
                                                                    <tr>
                                                                        <td style="padding-bottom: 6px">
                                                                            <p
                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                Primary Guest Details</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 6px; padding-bottom: 4px">
                                                                            <p
                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                                                                ' . $guest_name . '</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="left"
                                                                            style="padding-top: 4px; padding-bottom: 4.5px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/call_b.png"
                                                                                        
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $mobile . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="left"
                                                                            style="padding-top: 4.5px; padding-bottom: 4.5px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/email_b.png"
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $email_id . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 4.5px; padding-bottom: 6px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/location_b.png"
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $address . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 10px; padding-bottom: 6px">
                                                                            <p
                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                Expected Arrival</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 2px">
                                                                            <p
                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                '.$expeted_arrival.'</p>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" valign="top" height="96"
                                        style="padding-top: 8px; padding-left: 40px; height: 96px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="1077"
                                            height="67"
                                            style="border-radius: 12px; border: 2px solid #c7c6c6; width: 1077px; height: 67px; border-spacing: 0">
                                            <tr>
                                                <td align="left" valign="middle" style="padding-left: 24px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        style="border-spacing: 0">
                                                        <tr>
                                                            <td>
                                                                <p
                                                                    style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                    Guest Note:</p>
                                                            </td>
                                                            <td style="padding-left: 6px">
                                                                <p
                                                                    style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                                                    ' . $guest_note . '</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top: 16px; padding-bottom: 12px; padding-right: 40px; padding-left: 589px;">
                                        <p
                                            style="font-family: Inter; font-size: 18px; font-weight: 700; text-align: left; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                            Price Breakup</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td align="right" width="100.00%" style="padding-top: 10px; width: 100%">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="100.00%"
                                            style="width: 100%; border-spacing: 0">
                                            
                                            <tr>
                                                <td style="padding-left: 589px; padding-right: 36px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="530" height="222"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 530px; height: 222px; border-spacing: 0">
                                                        <tr>
                                                            <td width="100.00%" style="width: 100%">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" width="100.00%" height="129"
                                                                    style="border-bottom: 1px solid #c7c6c6; width: 100%; height: 129px; border-spacing: 0">
                                                                    <tr>
                                                                        <td align="left" valign="top"
                                                                            style="padding-top: 15px; padding-bottom: 12px; padding-left: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td style="padding-bottom: 3.5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Date</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ' . $outing_date . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 3.5px; padding-bottom: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p height="19"
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; height: 19px; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                       Package Price</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $package_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 5px; padding-bottom: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Discount Price
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width:215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $discount_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Price after discount</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $price_after_discount . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    No of guest</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ' . $no_of_guest . '</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    Amount Excl. Tax</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ₹' . $total_guest_price_excluding_tax . '</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                             </tr>
                                                                             <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    Tax Price</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ₹' . $tax_amount . '(' . $tax_percentage . '%)</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                             </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td width="100.00%" style="width: 100%">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" width="100.00%" height="97"
                                                                    style="width: 100%; height: 97px; border-spacing: 0">
                                                                    <tr>
                                                                        <td align="left" valign="top"
                                                                            style="padding-top: 12px; padding-bottom: 14px; padding-left: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td style="padding-bottom: 3.5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Amount Incl. tax
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $total_guest_price_including_tax . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 3.5px; padding-bottom: 4px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Paid Amount</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $paid_amount . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 4px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Pay at hotel</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $pay_at_hotel . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-bottom: 6.5px; padding-left: 40px">
                                        <p
                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Regards</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 6.5px; padding-bottom: 5.5px; padding-left: 40px">
                                        <p
                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Reservation Team</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 5.5px; padding-bottom: 7.5px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/location_bl.png"
                                                        width="15.00" height="16.00"
                                                        style="width: 15px; height: 16px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                        ' . $hotel_address . '</p>
                                                </td>
                                            </tr>
                                            
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 7.5px; padding-bottom: 4.5px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/call_bl.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        ' . $hotel_mobile . '</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" valign="top" height="40"
                                        style="padding-top: 4.5px; padding-left: 40px; height: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/email_bl.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        ' . $hotel_email_id . '</p>
                                                        
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_terms_and_cond . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_details->cancel_policy . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_details->hotel_policy . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                
                                                <td valign="middle" width="700" style="width: 700px">
                                                
                                                <span valign="middle" style="padding-left:50px;">Powered by</span>
                                                    <img src="' . $hotel_logo . '"
                                                        width="139" style="max-width: initial; width: 139px; display: block;padding-left: 35px;" />
                                                </td>
                                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </center>
        </body>
        
        </html>';
        return $body;
    }
}
