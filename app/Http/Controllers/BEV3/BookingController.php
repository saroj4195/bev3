<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\MasterRoomType; //class name from model
use App\HotelInformation;
use App\Invoice;
use App\User;
use App\HotelBooking;
use DB;
use App\CompanyDetails;
use App\MasterRatePlan;
use App\BillingDetails;
use App\CurrentRateBe;
use App\CurrentRate;
use App\DayWisePrice;
use App\UserNew;
use App\CurrencyDetails;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\CurrencyController;
use App\ImageTable;
use App\Coupons;
use App\HotelAmenities;
use App\CmOtaDetails;
use App\Country;
use App\PaymentGetway;
use App\State;
use App\City;
use App\BeBookingDetailsTable;
use App\Models\Commonmodel;
use Illuminate\Support\Facades\Mail;
use App\Packages;
use App\PmsAccount;
use App\Http\Controllers\PmsController;
use App\QuickPaymentLink;


class BookingController extends Controller
{
    protected $invService;
    protected $ipService;
    protected $curency;
    protected $idsService;
    public function __construct(IpAddressService $ipService, CurrencyController $curency, PmsController $idsService,)
    {
        $this->ipService = $ipService;
        $this->curency = $curency;
        $this->idsService = $idsService;
    }

    public function bookings(string $api_key, Request $request)
    {
        //store the log
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
        $diff = date_diff($checkin, $checkout);
        $diff = $diff->format("%a");
        if ($diff == 0) {
            $diff = 1;
        }

        if(strlen($user_details['mobile']) != '10'){
            $res = array('status' => 0, 'message' => "Mobile number should be 10 digits");
            return response()->json($res);
        }

        //Checkin date validation
        if ($date > $check_in) {
            $res = array('status' => 0, 'message' => "Check-in date must not be past date.");
            return response()->json($res);
        }

        //Hotel Validation 
        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('company_id', 'gst_slab', 'is_taxable', 'partial_pay_amt')->first();
        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 0, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $company_name = DB::table('kernel.company_table')->where('company_id', $hotel_info->company_id)->first();

        if($user_details['last_name'] == 'undefined'){
            $user_details['last_name'] = ' ' ;
        }

        //User registration
        $user_data['first_name'] = $user_details['first_name'];
        $user_data['last_name'] = isset($user_details['last_name'])?$user_details['last_name']: ' ';
        $user_data['email_id'] = $user_details['email_id'];
        $user_data['mobile'] = $user_details['mobile'];
        $country = Country::where('country_id',$user_details['country'])->first();
        $state = State::where('state_id',$user_details['state'])->first();
        // $city = City::where('city_id',$user_details['city'])->first();

        if(is_numeric($user_details['city'])){
            $city = City::where('city_id',$user_details['city'])->first();
            $city_name = isset($city->city_name) ? $city->city_name:'';
            $city_id = $user_details['city'];
        }else{
            $city_name = $user_details['city'];
            $payload = [
                'city_name' => $city_name,
                'state_id' => $user_details['state'],
            ];

            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://kernel.bookingjini.com/add-new-city',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $city_id = $response;
        }

        $user_data['password'] = uniqid(); //To generate unique rsndom number
        $user_data['password'] = Hash::make($user_data['password']); //Password encryption
        $user_data['country'] = isset($country->country_name)?$country->country_name:'';
        $user_data['state'] = isset($state->state_name)?$state->state_name:'';
        $user_data['city'] = $city_name;
        $user_data['zip_code'] = $user_details['zip_code'];
        $user_data['company_name'] = $user_details['company_name'];
        $user_data['GSTIN'] = $user_details['GST_IN'];
        $user_data['address'] = isset($user_details['address'])?$user_details['address']:'';
        $res = User::updateOrCreate(
            [
                'mobile' => $user_details['mobile'],
                'company_id' => $hotel_info->company_id
            ],
            $user_data
        );
        $user_id = $res->user_id;

        //insert in new user table
        $new_user_data['first_name'] = $user_details['first_name'];
        $new_user_data['last_name'] = isset($user_details['last_name'])?$user_details['last_name']: ' ';
        $new_user_data['email_id'] = $user_details['email_id'];
        $new_user_data['mobile'] = $user_details['mobile'];
        if (isset($user_details['mobile'])) {
            $user_name = $user_details['mobile'];
        } else {
            $user_name = $user_details['email_id'];
        }
        $new_user_data['password'] = uniqid(); //To generate unique rsndom number
        $new_user_data['password'] = Hash::make($user_data['password']); //Password encryption
        $new_user_data['country'] = $user_details['country'];
        $new_user_data['state'] = $user_details['state'];
        $new_user_data['city'] = $city_id;
        $new_user_data['zip_code'] = $user_details['zip_code'];
        $new_user_data['company_name'] = $user_details['company_name'];
        $new_user_data['GSTIN'] = $user_details['GST_IN'];
        $new_user_data['locality'] = $user_details['address'];
        $new_user_data['user_name'] = $user_name;
        $new_user_data['bookings'] = '';
        $user_res = UserNew::updateOrCreate(['mobile' => $user_details['mobile']], $new_user_data);
        $user_id_new = $user_res->user_id;

        //Store invoice details
        $inv_data = array();
        $inv_data['hotel_id']   = $hotel_id;
        $hotel = $this->getHotelInfo($hotel_id);
        $inv_data['hotel_name'] = $hotel->hotel_name;
        $inv_data['room_type']  = json_encode($this->prepareRoomTypes($room_details));
        $inv_data['ref_no'] = rand() . strtotime("now");
        $inv_data['check_in_out'] = "[" . $check_in . '-' . $check_out . "]";
        $inv_data['booking_date'] = date('Y-m-d H:i:s');
        $inv_data['booking_status'] = 2;
        $inv_data['user_id'] = $user_id;
        $inv_data['user_id_new'] = $user_id_new;
       
        $is_cm_active = CmOtaDetails::where('hotel_id',$hotel_id)->where('is_status',1)->where('is_active',1)->first();
        $inv_data['is_cm'] = 0;
        if($is_cm_active){
            $inv_data['is_cm'] = 1;  
        }
        
        $visitors_ip = $this->ipService->getIPAddress();
        $inv_data['visitors_ip'] = $visitors_ip;
        $inv_data['booking_source'] = "website";
        $inv_data['guest_note'] = $user_details['guest_note'];
        $inv_data['arrival_time'] = $user_details['arrival_time'];
        $inv_data['company_name'] = $user_details['company_name'];
        $inv_data['gstin'] = $user_details['GST_IN'];
        $inv_data['agent_code'] = '';

        //Room price calculation
        $room_price_including_gst = 0;
        $room_price_excluding_tax = 0;
        $total_gst_price = 0;
        $total_discount_price = 0;
        $extra_details = array();
        $hotel_booking_details = array();
        $cart = array();
        $idsCart = array();
        if (sizeof($room_details) > 0) {
            $invoice_details_array = [];
            $per_room_occupancy = [];
            foreach ($room_details as $details) {
                $room_type_id = $details['room_type_id'];
                $rate_plan_id = $details['rate_plan_id'];
                $occupancy = $details['occupancy'];
                $occupancy_details = $details['occupancy'];
                foreach ($occupancy_details as $occupancy_detail) {
                    array_push($extra_details, array($details['room_type_id'] => array($occupancy_detail['adult'], $occupancy_detail['child'])));
                }

                //Room type validation
                $room_type_details = MasterRoomType::where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->where('is_trash', 0)
                    ->first();

                if (empty($room_type_details)) {
                    $res = array('status' => 0, 'message' => "Invalid room types");
                    return response()->json($res);
                }

                $base_adult = $room_type_details->max_people;
                $base_child = $room_type_details->max_child;

                $room_rate = CurrentRate::where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->where('rate_plan_id', $rate_plan_id)
                    ->where('ota_id', '-1')
                    ->whereBetween('stay_date', [$check_in, $check_out])
                    ->get();

                foreach ($room_rate as $rate) {
                    $filtered_rate[$rate['stay_date']] = $rate;
                }

                $check_in_data = $check_in;
                $coupons_percentage = 0;
                $booking = [];
                $public_coupon_array = [];
                $private_coupon_array = [];
                $idsRooms = [];
                for ($i = 0; $i < $diff; $i++) {
                    $d = $check_in_data;
                    $discounted_price = 0;
                    $private_coupon_id = $booking_details['private_coupon'];
                        if(!empty($private_coupon_id)){
                        $privatecoupons = Coupons::where('hotel_id', $hotel_id)
                        ->where('coupon_id',$private_coupon_id)
                        ->where('is_trash', 0)
                        ->select('coupon_code')
                        ->first();
                        }
                       
                    $coupons = Coupons::where('hotel_id', $hotel_id)
                        ->where('valid_from', '<=', $d)
                        ->where('valid_to', '>=', $d)
                        ->whereIN('room_type_id', [0, $room_type_id])
                        ->where('is_trash', 0)
                        ->get();

                                            

                    if (sizeof($coupons) > 0) {
                        $multiple_coupon_one_date = [];
                        foreach ($coupons as $coupon) {
                            
                            if($hotel_id == 2319 || $hotel_id == 5951 || $hotel_id == 5976){
                                $blackoutdates = $coupon->blackoutdates;
                                $blackoutdates_array = explode(',',$blackoutdates);

                                if(!in_array($d,$blackoutdates_array)){
                                    if ($coupon->coupon_for == 1) {
                                        $multiple_coupon_one_date[] = $coupon->discount;
                                    }
                                    if ($coupon->coupon_for == 2) {
                                        $private_coupon_array[$d] = $coupon->discount;
                                    }
                                }
                            }else{
                                if ($coupon->coupon_for == 1) {
                                    $multiple_coupon_one_date[] = $coupon->discount;
                                }
                                if ($coupon->coupon_for == 2) {
                                  
                                   
                                    if(!empty($privatecoupons) && $coupon->coupon_code == $privatecoupons->coupon_code ){
                                        $private_coupon_array[$d] = $coupon->discount;
                                    }
                              
                                }
                            }
                        }

                        if (!empty($multiple_coupon_one_date)) {
                            $public_coupon_array[$d] = max($multiple_coupon_one_date);
                        } else {
                            $public_coupon_array[$d] = 0;
                        }
                    } else {
                        $public_coupon_array[$d] = 0;
                    }

                    if ($private_coupon_id != '') {
                        if ($private_coupon_array[$d] >= $public_coupon_array[$d]) {
                            $coupons_percentage = $private_coupon_array[$d];
                        } else {
                            $coupons_percentage = $public_coupon_array[$d];
                        }
                    } else {
                        $coupons_percentage = $public_coupon_array[$d];
                    }

                    $room_occu = [];
                    foreach ($occupancy as $key => $acc) {
                        $adult = $acc['adult'];
                        $child = $acc['child'];

                        $multiple_occupancy = $filtered_rate[$d]->multiple_occupancy;
                        $multiple_occupancy = json_decode($multiple_occupancy);
                        if (is_string($multiple_occupancy)) {
                            $multiple_occupancy = json_decode($multiple_occupancy);
                        }
                        $extra_adult = 0;
                        $extra_child = 0;
                        $bar_price = $filtered_rate[$d]->bar_price;
                        $extra_adult_price = $filtered_rate[$d]->extra_adult_price;
                        $extra_child_price = $filtered_rate[$d]->extra_child_price;

                        if ($base_child < $child) {
                            $extra_child = $child - $base_child;
                        }

                        if ($base_adult == $adult) {
                            $room_price = $bar_price;
                        } else if ($base_adult > $adult) {
                            $acc = $acc['adult'] - 1;
                            $room_price = isset($multiple_occupancy[$acc]) ? $multiple_occupancy[$acc] : $bar_price;
                        } else {
                            $extra_adult = $adult - $base_adult;
                            $room_price = $bar_price;
                        }

                        $discounted_price = $room_price * $coupons_percentage / 100;
                        $price_after_discount =  $room_price -  $discounted_price;

                                              
                        //Gst calculation
                        if ($hotel_info->is_taxable == 1) {
                            if ($hotel_info->gst_slab == 1) {
                                $price_for_gst_slab = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);
                                $gst_percentage = $this->checkGSTPercent($price_for_gst_slab);
                            } else {
                                $gst_percentage = $this->checkGSTPercent($price_after_discount);
                            }
                            $room_price_excluding_gst = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);

                            $gst_price = $room_price_excluding_gst * $gst_percentage / 100;
                        } else {
                            $room_price_excluding_gst = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);
                            $gst_price = 0;
                        }

                        $room_price_excluding_tax += $room_price_excluding_gst;
                        $room_price_including_gst += $room_price_excluding_gst + $gst_price;

                        
                        $total_gst_price += $gst_price;
                        $total_discount_price += $discounted_price;

                        $rwo['selected_adult'] = $adult;
                        $rwo['selected_child'] = $child;
                        $rwo['extra_adult'] = $extra_adult;
                        $rwo['extra_child'] = $extra_child;
                        array_push($room_occu, $rwo);

                        //Store the details in invoice details
                        $invoice_details['hotel_id'] = $hotel_id;
                        $invoice_details['user_id'] = $user_id;
                        $invoice_details['room_type_id'] = $details['room_type_id'];
                        $invoice_details['rate_plan_id'] = $details['rate_plan_id'];
                        $invoice_details['date'] = $d;
                        $invoice_details['room_rate'] = $room_price;
                        $invoice_details['extra_adult'] = $extra_adult;
                        $invoice_details['extra_child'] = $extra_child;
                        $invoice_details['extra_adult_price'] = $extra_adult_price;
                        $invoice_details['extra_child_price'] = $extra_child_price;
                        $invoice_details['discount_price'] = (float)$discounted_price;
                        $invoice_details['price_after_discount'] = (float)$room_price - $discounted_price;
                        $invoice_details['rooms'] = $key + 1;
                        $invoice_details['gst_price'] = (float)$gst_price;
                        $invoice_details['total_price'] = (float)$room_price_excluding_gst + $gst_price;

                         //This code is used in quickpaymentlink reservation
                        if(isset($booking_details['qpl_id'])){
                            $room_price_excluding_extra_ocupancy = $details['total_room_type_price']; //7142 , 3000
                            $room_rate_qpl = $room_price_excluding_extra_ocupancy/($details['no_of_rooms'] * $diff); //1785.5 //3000
                            if($details['total_room_type_discount_amount']>0){
                                $discount_price_qpl = (float)$details['total_room_type_discount_amount']/($details['no_of_rooms'] * $diff);
                            }else{
                                $discount_price_qpl = 0;
                            }

                            $ind_room_rate_qpl = $room_rate_qpl - ($extra_adult_price*$extra_adult) - ($extra_child_price*$extra_child);//785.5 //2000
                         
                            $gst_price_qpl = (float)$details['tax']/($details['no_of_rooms'] * $diff);
                            $price_after_discount_qpl = (float)$ind_room_rate_qpl -  $discount_price_qpl; //785.5
                            $invoice_details['room_rate'] = $ind_room_rate_qpl; //785.5
                            $invoice_details['discount_price'] = $discount_price_qpl;
                            $invoice_details['price_after_discount'] = $price_after_discount_qpl;
                            $invoice_details['gst_price'] = $gst_price_qpl;
                            $invoice_details['total_price'] = (float)$price_after_discount_qpl + $gst_price_qpl;


                        }

                        array_push($invoice_details_array, $invoice_details);
                    }

                    //Prepared cart details
                    $bookings['selected_adult'] = $adult;
                    $bookings['selected_child'] = $child;
                    $bookings['rate_plan_id'] = $details['rate_plan_id'];
                    $bookings['extra_adult_price'] = $extra_adult_price;
                    $bookings['extra_child_price'] = $extra_child_price;
                    $bookings['bar_price'] = $room_price;
                    array_push($booking, $bookings);

                    $check_in_data = date('Y-m-d', strtotime($d . ' +1 day'));
                }

                foreach($occupancy as $occupancy_details){

                    $adult = $occupancy_details['adult'];
                    $child = $occupancy_details['child'];
                    $extra_adult_price = $filtered_rate[$d]->extra_adult_price;
                    $extra_child_price = $filtered_rate[$d]->extra_child_price;

                    if ($base_adult == $adult) {
                        $room_price = $bar_price;
                    } else if ($base_adult > $adult) {
                        $acc = $occupancy_details['adult'] - 1;
                        $room_price = isset($multiple_occupancy[$acc]) ? $multiple_occupancy[$acc] : $bar_price;
                    } else {
                        $extra_adult = $adult - $base_adult;
                        $room_price = $bar_price;
                    }
                    //Prepared cart details
                    $idsRoomsData['selected_adult'] = $adult;
                    $idsRoomsData['selected_child'] = $child;
                    $idsRoomsData['rate_plan_id'] = $details['rate_plan_id'];
                    $idsRoomsData['extra_adult_price'] = $extra_adult_price;
                    $idsRoomsData['extra_child_price'] = $extra_child_price;
                    $idsRoomsData['bar_price'] = $room_price;
                    array_push($idsRooms, $idsRoomsData);
                }
                array_push($per_room_occupancy, $room_occu);

                //store the data in hotel booking table
                $hotel_booking_data['room_type_id'] = $details['room_type_id'];
                $hotel_booking_data['rate_plan_id'] = $details['rate_plan_id'];
                $hotel_booking_data['rooms'] = sizeof($occupancy);
                $hotel_booking_data['check_in'] = $check_in;
                $hotel_booking_data['check_out'] = $check_out;
                $hotel_booking_data['booking_status'] = 2; //Intially Un Paid
                $hotel_booking_data['user_id'] = $user_id;
                $hotel_booking_data['booking_date'] = date('Y-m-d');
                $hotel_booking_data['hotel_id'] = $hotel_id;
                array_push($hotel_booking_details, $hotel_booking_data);
                array_push($cart, array('rooms' => $booking, 'room_type_id' => $details['room_type_id'], 'rate_plan_id' => $details['rate_plan_id'], 'room_type' => $room_type_details->room_type));
                array_push($idsCart, array('rooms' => $idsRooms, 'room_type_id' => $details['room_type_id'], 'rate_plan_id' => $details['rate_plan_id'], 'room_type' => $room_type_details->room_type));
            }

            //Paid services calculations
            $paid_service = $data['paid_service'];
            $paid_service_id = [];
            $total_paidservices_amount = 0;
            if ($paid_service) {
                foreach ($paid_service as $service) {
                    $paid_service_id[] = $service['service_no'];
                }

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
         
            //Addon Carges calculations
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

            if($hotel_id == 2319){
                dd($room_price,$coupons_percentage,$discounted_price,$price_after_discount,$addon_charges,$room_price_including_gst,$room_price_excluding_gst,$gst_price);
            }

            
            $total_amount = $room_price_including_gst;
            if ($payment_mode == 2) {
                $partial_pay_per = $hotel_info->partial_pay_amt;
                $room_price_including_gst = $room_price_including_gst * $partial_pay_per / 100;
            } elseif ($payment_mode == 3) {
                $room_price_including_gst = 0;
            } else {
                $room_price_including_gst = $room_price_including_gst;
            }

            //This code is used in quickpaymentlink reservation
            if(isset($booking_details['qpl_id'])){
                $quick_payment = QuickPaymentLink::where('id',$booking_details['qpl_id'])->first();
                if($quick_payment->advance_amount != ''){
                    $room_price_including_gst = $quick_payment->advance_amount;
                }else{
                    $room_price_including_gst = $quick_payment->amount;
                }
                $total_amount = $quick_payment->amount;
            }

            $room_price_including_gst = number_format((float)$room_price_including_gst, 2, '.', '');

            $diff_amount = intval($amount_to_pay) - intval($room_price_including_gst);


            if (!isset($booking_details['qpl_id'])) {
                if ($diff_amount > 1) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Booking failed due to data tampering',
                        'data' => $room_price_including_gst
                    ]);
                }
            }
  
        } else {
            $res = array('status' => 0, 'message' => "Booking failed invalid room type");
            return response()->json($res);
        }

        $inv_data['total_amount'] = number_format((float)$total_amount, 2, '.', '');
        $inv_data['tax_amount'] = number_format((float)$total_gst_price, 2, '.', '');
        $inv_data['paid_amount'] = number_format((float)$room_price_including_gst, 2, '.', '');
        $inv_data['discount_amount'] = number_format((float)$total_discount_price, 2, '.', '');
        $inv_data['extra_details'] = json_encode($extra_details);
        $inv_data['paid_service_id'] = '';
        $inv_data['invoice'] = '';

        $refund_protect_data = $booking_details['opted_book_assure'];
        $refund_protect_price_info = isset($refund_protect_data['refund_protect_price']) && $refund_protect_data['refund_protect_price'] > 0 ? $refund_protect_data['refund_protect_price'] : 0;
        $rp_member_id = 0;
        $source_info = '';
        $refund_protect_sold_status = isset($refund_protect_data['sold']) ? $refund_protect_data['sold'] : '';
        $coupon = '';

        $is_ids = PmsAccount::where('name', 'IDS NEXT')->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')->first();
        if ($is_ids) {
            $inv_data['ids_re_id'] = $this->handleIds($idsCart, $check_in, $check_out, $inv_data['booking_date'], $hotel_id, $inv_data['user_id'], $inv_data['booking_status'],$hotel_info);

        }

        $result = $invoice->fill($inv_data)->save();
        if ($result) {
            $invoice_id = $invoice->invoice_id;

            foreach ($hotel_booking_details as &$hotel_booking_detail) {
                $hotel_booking_detail['invoice_id'] = $invoice_id;
            }
            HotelBooking::insert($hotel_booking_details);

            foreach ($invoice_details_array as &$invoice_detail) {
                $invoice_detail['invoice_id'] = $invoice_id;
            }
            DayWisePrice::insert($invoice_details_array);

            $coupon = '';
                    $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
                    $bookings_details = HotelBooking::where('invoice_id', $invoice_id)->get();

                    foreach ($bookings_details as $day_wise_booking) {
                        $room_type_id = $day_wise_booking->room_type_id;
                        $day_wise_bookings = DayWisePrice::where('invoice_id', $invoice_id)
                        ->leftjoin('kernel.room_type_table', 'room_type_table.room_type_id', 'voucher_day_wise_price.room_type_id')
                        ->leftjoin('kernel.rate_plan_table', 'rate_plan_table.rate_plan_id', 'voucher_day_wise_price.rate_plan_id')
                        ->select('voucher_day_wise_price.*', 'room_type_table.room_type', 'rate_plan_table.plan_name', 'room_type_table.max_people', 'room_type_table.max_child')
                        ->where('voucher_day_wise_price.room_type_id', $room_type_id)
                            ->get();
                            
                        $room_price_dtl = [];
                        $extra_adult_dtl = [];
                        $extra_child_dtl = []; 
                        $gst_dtl = [];
                        $adult_dtl = [];
                        $child_dtl = [];
                        $discount_price = [];

                        foreach ($day_wise_bookings as $day_wise_booking) {
                            $room_price_dtl[] = $day_wise_booking->room_rate;
                            $extra_adult_dtl[] = $day_wise_booking->extra_adult * $day_wise_booking->extra_adult_price;
                            $extra_child_dtl[] = $day_wise_booking->extra_child * $day_wise_booking->extra_child_price;
                            $gst_dtl[] = $day_wise_booking->gst_price;
                            $adult_dtl[] = $day_wise_booking->max_people;
                            $child_dtl[] = $day_wise_booking->max_child;
                            $discount_price[] = $day_wise_booking->discount_price;
                        }

                        $room_price_dtl = implode(',', $room_price_dtl);
                        $extra_adult_dtl = implode(',', $extra_adult_dtl);
                        $extra_child_dtl = implode(',', $extra_child_dtl);
                        $gst_dtl = implode(',', $gst_dtl);
                        $adult_dtl = implode(',', $adult_dtl);
                        $child_dtl = implode(',', $child_dtl);
                        $discount_price = implode(',', $discount_price);

                        $be_booking_det['hotel_id'] = $day_wise_bookings[0]['hotel_id'];
                        $be_booking_det['ref_no'] = $invoice_details->ref_no;
                        $be_booking_det['room_type'] = $day_wise_bookings[0]['room_type'];
                        $be_booking_det['rooms'] = $day_wise_booking->rooms;
                        $be_booking_det['room_rate'] = $room_price_dtl;
                        $be_booking_det['extra_adult'] = $extra_adult_dtl;
                        $be_booking_det['extra_child'] =  $extra_child_dtl;
                        $be_booking_det['discount_price'] =  $discount_price;
                        $be_booking_det['adult'] = $adult_dtl;
                        $be_booking_det['child'] = $child_dtl;
                        $be_booking_det['room_type_id'] = $day_wise_bookings[0]['room_type_id'];
                        $be_booking_det['tax_amount'] = $gst_dtl;
                        $be_booking_det['rate_plan_name'] = $day_wise_bookings[0]['plan_name'];
                        $be_booking_det['rate_plan_id'] = $day_wise_bookings[0]['rate_plan_id'];

                        $insert_be_bookings_table = BeBookingDetailsTable::insert($be_booking_det);
                    }

            $inv_data['invoice'] = $this->createInvoice($hotel_id, $invoice_id, $cart, $coupon, $total_paidservices_amount, $check_in, $check_out, $inv_data['user_id'], $refund_protect_price_info, $rp_member_id, $source_info, $inv_data['total_amount'], $inv_data['paid_amount'], $per_room_occupancy,$addon_charges);

            $update_vouchere = invoice::where('invoice_id', $invoice_id)->update(['invoice' => $inv_data['invoice']]);

            $user_data = $this->getUserDetails($inv_data['user_id']);
            $b_invoice_id = base64_encode($invoice_id);
            
            $this->preinvoiceMail($invoice_id);
            
            $invoice_hashData = $invoice_id . '|' . $inv_data['total_amount'] . '|' . $room_price_including_gst . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;
            $invoice_secureHash = hash('sha512', $invoice_hashData);

            $res = array("status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $invoice_id, 'invoice_secureHash' => $invoice_secureHash);

            if(isset($booking_details['qpl_id'])){
                $res = array("status" => 1,'invoice_id'=>$invoice_id, "message" => "Offline Booking Successfull");
            }

            return response()->json($res);

        } else {
            $res = array('status' => -1, "message" => 'Booking Failed');
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }


    public function repushIdsBooking($invoice_id){

        $invoice_details = Invoice::where('invoice_id',$invoice_id)->first();
        $room_type_wise_booking = HotelBooking::where('invoice_id',$invoice_id)->get();
        $occupancy_details = json_decode($invoice_details->extra_details, true);
        $hotel_info = HotelInformation::where('hotel_id', $invoice_details->hotel_id)->select('is_taxable')->first();

        $check_in = $room_type_wise_booking[0]->check_in;
        $check_out = $room_type_wise_booking[0]->check_out;
        $hotel_id = $invoice_details->hotel_id;
        $user_id = $invoice_details->user_id;
        $booking_status = $invoice_details->booking_status;
        $booking_date = $invoice_details->booking_date;




        $checkin = date_create($check_in);
        $checkout = date_create($check_out);
        $date = date('Y-m-d');
        $diff = date_diff($checkin, $checkout);
        $diff = $diff->format("%a");
        if ($diff == 0) {
            $diff = 1;
        }
        
        $cart = [];
        $total_room_type_adult = 0;
        $total_room_type_child = 0;
        foreach($room_type_wise_booking as $booking_details){

            $be_booking_details = DB::table('voucher_day_wise_price')->where('invoice_id',$invoice_id)->where('room_type_id',$booking_details->room_type_id)->first();

            foreach ($occupancy_details as $occupancy_detail) {
                if (isset($occupancy_detail)) {
                    foreach ($occupancy_detail as $rm_id => $extra) {
                        if ($rm_id == $booking_details->room_type_id) {
                            $total_room_type_adult = $extra[0];
                            $total_room_type_child = $extra[1];
                        }
                    }
                }
            }
           
            $booking = [];
            for ($i = 0; $i < $diff; $i++) {
                //Prepared cart details
                $bookings['selected_adult'] = $total_room_type_adult;
                $bookings['selected_child'] = $total_room_type_child;
                $bookings['rate_plan_id'] = $booking_details->rate_plan_id;
                $bookings['extra_adult_price'] = 0;
                $bookings['extra_child_price'] = 0;
                $bookings['bar_price'] = $be_booking_details->room_rate;
                array_push($booking, $bookings);  
               }

            array_push($cart, array('rooms' => $booking, 'room_type_id' => $booking_details->room_type_id, 'rate_plan_id' => $booking_details->rate_plan_id, 'room_type' => $booking_details->room_type));
        }

        $ids_id = $this->handleIds($cart, $check_in, $check_out, $booking_date, $hotel_id, $user_id, $booking_status, $hotel_info);

        $invoice_details = Invoice::where('invoice_id',$invoice_id)->update(['ids_re_id' => $ids_id]);

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://cm.bookingjini.com/ids-reservation-push-from-be/'.$invoice_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;

    }

    public function handleIds($cart, $from_date, $to_date, $booking_date, $hotel_id, $user_id, $booking_status,$hotel_info)
    {
        $ids_status = $this->idsService->getIdsStatus($hotel_id);
        if ($ids_status) {
            $ids_data = $this->prepare_ids_data($cart, $from_date, $to_date, $booking_date, $hotel_id,$hotel_info);
            $customer_data = $this->getUserDetails($user_id)->toArray();
            $type = "Bookingjini";
            $last_ids_id = $this->idsService->idsBookings($hotel_id, $type, $ids_data, $customer_data, $booking_status);
            if ($last_ids_id) {
                return $last_ids_id;
            } else {
                return 0;
            }
        }
    }

    public function prepare_ids_data($cart, $from_date, $to_date, $booking_date, $hotel_id,$hotel_info)
    {
        $booking_data = array();
        $booking_data['booking_id'] = '#####'; //Intially Booking id not known ,After successful boooking Only booking id Set to this
        $booking_data['room_stay'] = array();
        $date1 = date_create($from_date);
        $date2 = date_create($to_date);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $no_of_rooms = 0;
        foreach ($cart as $cartItem) {
            $rates_arr = array();
            $gst_price = 0;
            $total_adult = 0;
            $total_child = 0;
            if (isset($cartItem['rooms'])) {
                $no_of_rooms = sizeof($cartItem['rooms']);

                foreach ($cartItem['rooms'] as $rooms) {
                    $ind_total_price = 0;
                    $frm_date = $from_date;
                    $total_adult = $rooms['selected_adult'];
                    $ind_total_price = $rooms['bar_price'] + $rooms['extra_adult_price'] + $rooms['extra_child_price'];
                    $rates_arr = array();
                    for ($j = 1; $j <= $diff; $j++) {
                        $amount = 0;
                        $gst_price = 0;
                        $d1 = $frm_date;
                        $d2 = date('Y-m-d', strtotime($d1 . ' +1 day'));
                        $amount = (($ind_total_price / $diff));
                        if($hotel_info->is_taxable==1){
                            $gst_price = $this->getGstPrice(1, 1, $cartItem['room_type_id'], $amount); //TO get the GSt price
                        }

                        if (strpos('.', $amount) == false) {
                            $amount = $amount . ".00";
                        }
                        array_push($rates_arr, array("from_date" => $d1, "to_date" => $d2, 'amount' => (int)$amount, 'tax_amount' => $gst_price));
                        $frm_date = date('Y-m-d', strtotime($d1 . ' +1 day'));
                    }
                    $arr = array('room_type_id' => $cartItem['room_type_id'], 'rate_plan_id' => $rooms['rate_plan_id'], 'adults' => $total_adult, 'from_date' => $from_date, 'to_date' => $to_date, 'rates' => $rates_arr);
                    array_push($booking_data['room_stay'], $arr);
                }
            }
        }
        return $booking_data;
    }

    public function getGstPrice($no_of_nights, $no_of_rooms, $room_type_id, $price)
    {

        $chek_price = ($price / $no_of_nights) / $no_of_rooms;
        $gstPercent = $this->checkGSTPercent($room_type_id, $chek_price);

        $gstPrice = (($price) * $gstPercent) / 100;
        $gstPrice = round($gstPrice, 2);
        return $gstPrice;
    }

    public function createInvoice($hotel_id, $invoice_id, $cart, $coupon, $paid_services, $check_in, $check_out, $user_id, $refund_protect_price, $rp_member_id, $source, $total_amount, $paid_amount_info, $per_room_occupancy,$addon_charges)
    {
       
        $booking_id = "#####";
        $transaction_id = ">>>>>";
        $booking_date = date('Y-m-d');
        $booking_date = date("jS M, Y", strtotime($booking_date));
        $hotel_details = $this->getHotelInfo($hotel_id);
        $u = $this->getUserDetails($user_id);
        $dsp_check_in = date("jS M, Y", strtotime($check_in));
        $dsp_check_out = date("jS M, Y", strtotime($check_out));
        $diff = abs(strtotime($check_out) - strtotime($check_in));

               // $dsp_check_in = date_create($check_in);
        // $dsp_check_out = date_create($check_out);
        // $diff = date_diff($dsp_check_in,$dsp_check_out)->format('%a');
        
        if ($diff == 0) {
            $diff = 1;
        }
        $years = floor($diff / (365 * 60 * 60 * 24));
        $months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
        $day = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));
        if ($day == 0) {
            $day = 1;
        }
        $total_adult = 0;
        $total_child = 0;
        $all_room_type_name = "";
        $paid_service_details = "";
        $all_rows_date = "";
        $total_discount_price = 0;
        $total_price_after_discount = 0;
        $total_tax = 0;
        $other_tax_arr = array();

        //Get base currency and currency hex code
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;
        $currency_code = $this->getBaseCurrency($hotel_id)->hex_code;
        $grand_total_amount = 0;
        $grand_total_amount_after_discount = 0;
        $discount = 0;

        $tax_info = HotelInformation::where('hotel_id', $hotel_id)->select('is_taxable')->first();

        foreach ($cart as $j => $cartItem) {
            $room_type_id = $cartItem['room_type_id'];
            $room_type = $cartItem['room_type'];

            $rate_plan_id = $cartItem['rooms'][0]['rate_plan_id'];
            $conditions = array('rate_plan_id' => $rate_plan_id, 'is_trash' => 0);
            $rate_plan_id_array = MasterRatePlan::select('plan_name')->where($conditions)->first();
            $rate_plan = $rate_plan_id_array['plan_name'];

            $total_room_amount = 0;
            $total_room_amount_after_discount = 0;


            "";
            

            $all_rows_date .= '<tr><td colspan="13" bgcolor="#ec8849" align="center" style="font-weight:700;padding:10px; ">' . $room_type . '(' . $rate_plan . ')</td>';

            if($tax_info->is_taxable==1){
                $colspan = 3;
            }else{
                $colspan = 4;
            }

            $all_rows_date .= '<tr>
                  <th bgcolor="#A6ACAF" colspan="'.$colspan.'" align="center">Rooms</th>
                  <th bgcolor="#A6ACAF" colspan="3" align="center">Date</th>
                  <th bgcolor="#A6ACAF" align="center">Room Rate</th>
                  <th bgcolor="#A6ACAF" align="center">Discount</th>
                  <th bgcolor="#A6ACAF" align="center">Price After Discount</th>
                  <th bgcolor="#A6ACAF" align="center">Extra Adult Price</th>
                  <th bgcolor="#A6ACAF" align="center">Extra Child Price</th>
                  <th bgcolor="#A6ACAF" align="center">Total Price</th>';
                if($tax_info->is_taxable == 1){
                    $all_rows_date .= '<th bgcolor="#A6ACAF" align="center">GST</th>';
                }
            $all_rows_date .= '</tr>';

            $dates = DayWisePrice::where('invoice_id', $invoice_id)->where('room_type_id', $room_type_id)->groupBy('date')->get();
            $rooms = DayWisePrice::where('invoice_id', $invoice_id)->where('room_type_id', $room_type_id)->groupBy('rooms')->get();
            $rooms_row_span = sizeof($cartItem['rooms']);
            
            $fno_of_rooms = 0;
            foreach ($rooms as $k => $room) {
                $fno_of_rooms = $room->rooms;

                $occupancy_room_wise = '';
                if (isset($per_room_occupancy[$j][$k]['selected_adult'])) {
                    $selected_adult = $per_room_occupancy[$j][$k]['selected_adult'];
                    $selected_child = $per_room_occupancy[$j][$k]['selected_child'];
                    $extra_adult = $per_room_occupancy[$j][$k]['extra_adult'];
                    $extra_child = $per_room_occupancy[$j][$k]['extra_child'];
                    $base_adult = $selected_adult - $extra_adult;
                    $base_child = 0;
                    if ($selected_child != 0) {
                        $base_child = $selected_child - $extra_child;
                    }
                    $occupancy_room_wise .= 'Adult' . '(' . $base_adult . '+' . $extra_adult . ')' . 'Child' . '(' . $base_child . '+' . $extra_child . ')';

                    $total_adult += $selected_adult;
                    $total_child += $selected_child;
                }

                if($tax_info->is_taxable==1){
                    $colspan1 = 1;
                }else{
                    $colspan1 = 2;
                }


                $all_rows_date .= '<tr>
                <td rowspan="' . $rooms_row_span . '" colspan = "'.$colspan1.'" align="center">Room - ' . ($k + 1) . '</td>
                <td rowspan="' . $rooms_row_span . '" colspan = "2" align="center">' . $occupancy_room_wise . '</td>';
                foreach ($dates as $index => $date) {
                    $day_wise_rooms_details = DayWisePrice::where('invoice_id', $invoice_id)->where('room_type_id', $room_type_id)->where('date', $date['date'])->where('rooms', $room['rooms'])->get();
                    $stay_date = date("jS M, Y", strtotime($date['date']));
                    
                    if($index != 0){
                        $all_rows_date .= '<tr>';
                    }

                    $all_rows_date .= '<td rowspan="1" colspan = "3" align="center">' . $stay_date . '</td>';

                    foreach ($day_wise_rooms_details as $key => $day_wise_room) {
                        $extra_adult_price = $day_wise_room['extra_adult_price'] * $day_wise_room['extra_adult'];
                        $extra_child_price = $day_wise_room['extra_child_price'] * $day_wise_room['extra_child'];

                        $total_amount = $day_wise_room['room_rate'] + $extra_adult_price + $extra_child_price;
                        $total_amount_after_discount = $day_wise_room['room_rate'] - $day_wise_room['discount_price'] + $extra_adult_price + $extra_child_price;
                        $total_room_amount += $total_amount;
                        $total_room_amount_after_discount += $total_amount_after_discount;
                        $discount += $day_wise_room['discount_price'];
                        $total_tax += $day_wise_room['gst_price'];

                        $all_rows_date = $all_rows_date .
                                  '<td  align="center">' . $currency_code . number_format((float)$day_wise_room['room_rate'], 2, '.', '') . '</td>
                                  <td  align="center">' . $currency_code . number_format((float)$day_wise_room['discount_price'], 2, '.', ''). '</td>
                                  <td  align="center">' . $currency_code . number_format((float)$day_wise_room['price_after_discount'], 2, '.', '') . '</td>
                                  <td  align="center">' . $currency_code .  number_format((float)$extra_adult_price, 2, '.', ''). '</td>
                                  <td  align="center">' . $currency_code .  number_format((float)$extra_child_price, 2, '.', ''). '</td>
                                  <td  align="center">' . $currency_code .  number_format((float)$total_amount, 2, '.', '') . '</td>
                               ';

                                if($tax_info->is_taxable == 1){
                                    $all_rows_date .= '<td  align="center">' . $currency_code .  number_format((float)$day_wise_room['gst_price'], 2, '.', '') . '</td>';
                                }
                                $all_rows_date .= '</tr>';
                    }
                }
            }

            // if($hotel_id==2600){
                $all_room_type_name .= ',' . $fno_of_rooms . ' ' . $room_type;
            // }else{
            //     $all_room_type_name .= ',' . sizeof($cartItem['rooms']) . ' ' . $room_type;
            // }

            $grand_total_amount += $total_room_amount;
            $grand_total_amount_after_discount += $total_room_amount_after_discount;

            $all_rows_date .= '<tr><td colspan="11" align="right">Total Amount</td><td align="center">' . $currency_code . $total_room_amount . '</td>
                  </tr>';
        }

        // $service_amount = 0;
        // if (sizeof($paid_services) > 0) {
        //     foreach ($paid_services as $paid_service) {
        //         $paid_service_details = $paid_service_details . '<tr>
        //               <td colspan="8" style="text-align:right;">' . $paid_service['service_name'] . '&nbsp;&nbsp;</td>
        //               <td style="text-align:center;">' . $currency_code . ($paid_service['price'] * $paid_service['qty']) . '</td>
        //               <tr>';
        //         $service_amount += $paid_service['price'] * $paid_service['qty'];
        //     }
            // $paid_service_details = '<tr><td colspan="8" bgcolor="#ec8849" style="text-align:center; font-weight:bold;">Paid Service Details</td></tr>' . $paid_services;
        // }

        $total_paid_amount = $grand_total_amount_after_discount + $total_tax + $paid_services + $addon_charges;
        if ($total_discount_price > 0) {
            $display_discount = $total_discount_price;
        }
 
        $gst_tax_details = "";
        if ($baseCurrency == 'INR') {
            $gst_tax_details = '<tr>
                  <td colspan="12" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
                  <td align="center">' . $currency_code .  number_format((float)$total_tax, 2, '.', '') . '</td>
                  </tr>';
        }
        if ($refund_protect_price > 0) {
            $refund_protect_info = '<tr>
                      <td colspan="8" align="right">Refundable booking charges &nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . $refund_protect_price . '</td>
                  </tr>';
            $refund_protect_description = '
                  <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">If your booking is cancelled or you need to make changes  :</span>Please contact our customer service team at <a href="mailto:support@bookingjini.com">support@bookingjini.com</a>.</td>
                  </tr>
                  <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">Refundable Booking :</span>
                      This is a Refundable booking, so if you are unable to attend your booking due to unforeseen circumstances and can provide evidence as listed in the Terms and Conditions <a href="https://refundable.me/extended/en" target="_blank">here</a>. you may be entitled to a full refund.<br>You will need your reference number <b>#####</b> to apply for your refund using the form ';
            if ($rp_member_id == 295) {
                $refund_protect_description .= '<a href="https://form.refundable.me/forms/refund?memberId=295&bookingReference=#####" target="_blank">here</a>.</td>';
            } elseif ($rp_member_id == 298) {
                $refund_protect_description .= '<a href="https://form.refundable.me/forms/refund?memberId=298&bookingReference=#####" target="_blank">here</a>.</td>';
            }
            $refund_protect_description .= '</tr>';
        } else {
            $refund_protect_info = '';
            $refund_protect_description = '';
        }
        $other_tax_details = "";
        if (
            sizeof($other_tax_arr) > 0
        ) {
            foreach ($other_tax_arr as $other_tax) {
                $other_tax_details = $other_tax_details . '<tr>
                  <td colspan="8" style="text-align:right;">' . $other_tax['tax_name'] . '&nbsp;&nbsp;</td>
                  <td style="text-align:center;">' . $currency_code . $other_tax['tax_price'] . '</td>
                  <tr>';
            }
        }
        $total_amt = $grand_total_amount + $paid_services + $addon_charges;
        $total_amt = number_format((float)$total_amt, 2, '.', '');
        $total = $total_amt + $total_tax;
        $total = number_format((float)$total, 2, '.', '');

        $due_amount_info = $total - $paid_amount_info - $discount;
        $due_amount_info = number_format((float)$due_amount_info, 2, '.', '');
        $body = '<html>
                      <head>
                      <style>
                      html{
                          font-family: Arial, Helvetica, sans-serif;
                      }
                      table, td {
                          height: 26px;
                      }
                      table, th {
                          height: 35px;
                      }
                      p{
                          color: #000000;
                      }
                      </style>
                      </head>
                      <body style="color:#000;">
                      <div style="margin: 0px auto;">
                      <table width="100%" align="center">
                      <tr>
                      <td style="border: 1px #0; padding: 4%; border-style: double;">
                      <table width="100%" border="0">
                          <tr>
                              <th colspan="2" valign="middle" style="font-size: 23px;"><u>BOOKING CONFIRMATION</u></th>
                          </tr>
                          <tr>
                          <td><b style="color: #ffffff;">*</b></td>
                          </tr>
                          <tr>
                              <td>
                                  <div>
                                      <div style="font-weight: bold; font-size: 22px; color:#fff; background-color: #1d99b5; padding: 5px;">' . $hotel_details['hotel_name'] . '</div>
                                  </div>
                              </td>
                              <td style="font-size: 16px;font-weight: bold;" align="right">BOOKING ID : ' . $booking_id . '</td>
                          </tr>
                          <tr>
                              <td colspan="2"><b style="color: #ffffff;">*</b></td>
                          </tr>';
        if ($source  != 'GEMS') {
            $body .= '<tr><td></td><td style="font-size: 16px;font-weight: bold;" align="right">PAYMENT REFERENCE NUMBER : ' . $transaction_id . '</td></tr>';
        }
        $body .=
            '<tr>
                              <td colspan="2"><b>Dear ' . $u->first_name . ' ' . $u->last_name . ',</b></td>
                          </tr>';

        $body .= ' <tr>
                                  <td colspan="2" style="font-size:17px;"><b>We hope this email finds you well. Thank you for choosing ' . $hotel_details->hotel_name . ' as your property of choice for your visit and booking through our hotel\'s website. Your booking confirmation details have been provided below:</b></td>
                              </tr>';
        $body .= '<tr>
                              <td colspan="2"><b style="color: #ffffff;">*</b></td>
                          </tr>
                      </table>
                      
                      <table width="100%" border="1" style="border-collapse: collapse;">
                          <th colspan="2" bgcolor="#A6ACAF">BOOKING DETAILS</th>
                          <tr>
                              <td >PROPERTY & PLACE</td>
                              <td>' . $hotel_details->hotel_name . '</td>
                          </tr>
                          <tr>
                              <td width="45%">NAME OF PRIMARY GUEST</td>
                              <td>' . $u->first_name . ' ' . $u->last_name . '</td>
                          </tr>
                          <tr>
                              <td>PHONE NUMBER</td>
                              <td>' . $u->mobile . '</td>
                          </tr>
                          <tr>
                              <td>EMAIL ID</td>
                              <td>' . $u->email_id . '</td>
                          </tr>
                          <tr>
                              <td>ADDRESS</td>
                              <td>' . $u->address . ',' . $u->city . ',' . $u->state . ',' . $u->country . '</td>
                          </tr>
                          <tr>
                      <td>BOOKING DATE</td>
                      <td>' . $booking_date . '</td>
                      </tr>
                          <tr>
                      <td>CHECK IN</td>
                      <td>' . $dsp_check_in . '(' . $hotel_details->check_in . ')' . '</td>
                      </tr>
                          <tr>
                      <td>CHECK OUT</td>
                      <td>' . $dsp_check_out . '(' . $hotel_details->check_out . ')' . '</td>
                      </tr>
                              <tr>
                      <td>TOTAL ADULT</td>
                      <td>' . $total_adult . '</td>
                      </tr>
                              <tr>
                      <td>TOTAL CHILDREN</td>
                      <td>' . $total_child . '</td>
                      </tr>
                          <tr>
                      <td>NUMBER OF NIGHTS</td>
                      <td>' . $day . '</td>
                      </tr>
                          <tr>
                      <td>NO. & TYPES OF ACCOMMODATIONS BOOKED</td>
                      <td>' . substr($all_room_type_name, 1) . '</td>
                      </tr>
                      
                      </table>
                      
                      <table width="100%" border="1" style="border-collapse: collapse;">
                          <tr>
                      <th colspan="13" valign="middle" height="" style="font-size: 20px;">TARIFF APPLICABLE</th>
                      </tr>
                          ' . $all_rows_date . '
                      <tr>
                      <td colspan="13"><p style="color: #ffffff;">*</p></td>
                      </tr>
                    <tr>
                      <td colspan="12" align="right">Total Room Price&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$grand_total_amount, 2, '.', '') . '</td>
                      </tr>
                      <tr>
                      <td colspan="12" align="right">Total Discount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$discount, 2, '.', ''). '</td>
                      </tr>
                      <tr>
                      <td colspan="12" align="right">Total Room Price after discount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$grand_total_amount_after_discount, 2, '.', '') . '</td>
                      </tr>
                      
                    
                      ' . $gst_tax_details . '
                      ' . $other_tax_details . '
                      ' . $refund_protect_info . '
                      <tr>
                      <td colspan="12" align="right">Paid Services&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$paid_services, 2, '.', '') . '</td>
                      </tr>
                      <tr>';
                     
                    if($addon_charges!=0){
                       
                        $body .= '<tr><td colspan="12" align="right">Service Charges &nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code .  number_format((float)$addon_charges, 2, '.', '') . '</td>
                      </tr>';
                    
                    }
        
        $body .= '<tr><td colspan="12" align="right">Total Amount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$total_paid_amount, 2, '.', '') . '</td>
                      </tr>';
        $body .= '<tr>
                      <td colspan="12" align="right">Total Paid Amount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . '<span id="pd_amt">' .  number_format((float)$paid_amount_info, 2, '.', ''). '</span></td>
                      </tr>
                      <tr>
                          <td colspan="12" align="right">Pay at hotel at the time of check-in &nbsp;&nbsp;</td>
                          <td align="center">' . $currency_code . '<span id="du_amt">' . number_format((float)$due_amount_info, 2, '.', '') . '</span></td>
                      </tr>
                      <tr>
                          <td colspan="13"><p style="color: #ffffff;">* </p></td>
                      </tr>';

        $body .= '</table>
                      
                      <table width="100%" border="0">
                          <tr>
                      <th colspan="2" style="font-size: 21px; color: #ffffff;"><u>*</u></th>
                      </tr>
                          <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;">We are looking forward to hosting you at ' . $hotel_details->hotel_name . '.</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      
                          <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">Regards,<br />
                          Reservation Team<br />
                          ' . $hotel_details->hotel_name . '<br />
                          ' . $hotel_details->hotel_address . '<br />
                          Mob   : ' . $hotel_details->mobile . '<br />
                          Email : ' . $hotel_details->email_id . '</span>
                      </td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Note</u> :</span> Taxes applicable may change subject to govt policy at the time of check in.</td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      <!--tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Terms & Conditions</u> :</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr-->
                      <!--<tr>
                      <td colspan="2"><span style="color:#f00"><li>On Any 100% Cancellation Policy there will be a 3% mandatory deduction from the booking amount due to payment gateway charges.</li></span></td>
                      </tr>-->
                      <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->terms_and_cond . '</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      <!--tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Cancellation Policy</u> :</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr-->';
        if (
            $hotel_details->cancel_policy != ""
        ) {
            $body .= '<tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->cancel_policy . '</span></td>
                      </tr>';
        } else {
            $body .= '<tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->hotel_policy . '</span></td>
                      </tr>';
        }
        $body .= '' . $refund_protect_description . '</table>
                      </td>
                      </tr>
                      </table>
                      </div>
                      </body>
                      </html>';
        return  $body;
    }

    public  function preinvoiceMail($id)
    {
        $invoice        = $this->successInvoice($id);
        $invoice = $invoice[0];
        //dd( $invoice);
        $booking_id     = date("dmy", strtotime($invoice->booking_date)) . str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
        $u = $this->getUserDetails($invoice->user_id);

        $subject        = "Booking From " . $invoice->hotel_name;
        $body           = $invoice->invoice;
        $body           = str_replace("#####", $booking_id, $body);
        $body           = str_replace("BOOKING CONFIRMATION", "BOOKING CONFIRMATION(UNPAID)", $body);
        $to_email = 'reservations@bookingjini.com'; //don't change this
       
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://tools.bookingjini.com/mailduck/raw',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('mail_from' => 'do-not-reply@bookingjini.com', 'mail_to' => $to_email, 'subject' => $subject, 'html' => $body),
            CURLOPT_HTTPHEADER => array(
                'Authorization: hRv8FpLbN7'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        
    }


    public function userRegister($user_details, $company_id, $company_name)
    {

        // $user_res = UserNew::where('mobile', $user_details['mobile'])->first();
        $user_data['first_name'] = $user_details['first_name'];
        $user_data['last_name'] = $user_details['last_name'];
        $user_data['email_id'] = $user_details['email_id'];
        $user_data['mobile'] = $user_details['mobile'];
        if (isset($user_details['mobile'])) {
            $user_name = $user_details['mobile'];
        } else {
            $user_name = $user_details['email_id'];
        }


        $user_data['password'] = uniqid(); //To generate unique rsndom number
        $user_data['password'] = Hash::make($user_data['password']); //Password encryption
        $user_data['country'] = $user_details['country'];
        $user_data['state'] = $user_details['state'];
        $user_data['city'] = $user_details['city'];
        $user_data['zip_code'] = $user_details['zip_code'];
        $user_data['company_name'] = $company_name;
        $user_data['GSTIN'] = $user_details['GST_IN'];

        $res = User::updateOrCreate(
            [
                'mobile' => $user_details['mobile'],
                'company_id' => $company_id
            ],
            $user_data
        );

        $user_data['locality'] = $user_details['address'];
        $user_data['user_name'] = $user_name;
        $user_data['bookings'] = '';



        $user_res = UserNew::updateOrCreate(['mobile' => $user_details['mobile']], $user_data);
        $user_id = $user_res->user_id;

        return $user_id;
    }

    public function allPublicCupons($hotel_id, $from_date, $to_date)
    {
        $begin = strtotime($from_date);
        $end = strtotime($to_date);
        $from_date = date('Y-m-d', strtotime($from_date));
        $data_array = array();
        for ($currentDate = $begin; $currentDate < $end; $currentDate += (86400)) {
            $data_array_present = array();
            $status = array();
            $Store = date('Y-m-d', $currentDate);



            $get_data = DB::raw('select coupon_id,room_type_id,
                  case when room_type_id != 0 then "present" when room_type_id = 0 then "notpresent" end as status,
                  coupon_name,coupon_code,valid_from,
                  valid_to,discount_type,coupon_for,discount,blackoutdates,blackoutdays,a.date,a.abc
                  FROM
                  (
                  select t2.coupon_id,
                  case when t2.room_type_id!=0 then t2.room_type_id else 0 end as room_type_id,
                  coupon_name,coupon_code,valid_from,valid_to,discount_type,coupon_for,discount,blackoutdates,blackoutdays,"' . $Store . '" as date,
                  case when "' . $Store . '" between valid_from and valid_to then "yes" else "no" end as abc
                  from booking_engine.coupons
                  INNER JOIN
                  (
                  SELECT room_type_id,
                  substring_index(group_concat(cast(coupon_id as CHAR) order by discount desc), ",", 1 ) as coupon_id,MAX(discount)
                  FROM booking_engine.coupons where hotel_id = "' . $hotel_id . '" and coupon_for = 1 and is_trash = 0
                  and ("' . $Store . '" between valid_from and valid_to) and (NOT FIND_IN_SET("' . $Store . '",blackoutdates) OR blackoutdates IS NULL)
                  GROUP BY room_type_id
                  order by coupon_id desc
                  ) t2 ON coupons.room_type_id = t2.room_type_id AND coupons.coupon_id = t2.coupon_id
                  ) AS a where a.abc = "yes"
                  order by room_type_id,coupon_id desc');

            $get_data = DB::select($get_data->getValue(DB::connection()->getQueryGrammar()));


            foreach ($get_data as $data) {
                $status[] = $data->status;
                $data_present['coupon_id']        = $data->coupon_id;
                $data_present['room_type_id']     = $data->room_type_id;
                $data_present['date']             = $data->date;
                $data_present['coupon_name']      = $data->coupon_name;
                $data_present['coupon_code']      = $data->coupon_code;
                $data_present['valid_from']       = $data->valid_from;
                $data_present['valid_to']         = $data->valid_to;
                $data_present['coupon_for']       = $data->coupon_for;
                $data_present['discount_type']    = $data->discount_type;
                $data_present['discount']         = $data->discount;
                if ($data->valid_from <= $from_date && $data->valid_to >= $from_date) {
                    $data_array_present[] = $data_present;
                }
            }
            if ($data_array_present) {
                for ($i = 0; $i < sizeof($data_array_present); $i++) {
                    $data_array[] = $data_array_present[$i];
                }
            }
            $from_info = strtotime($from_date);
            $from_info += (86400);
            $from_date = date('Y-m-d', $from_info);
        }
        return $data_array;
    }

    public function checkGSTPercent($price)
    {
        if ($price > 0 && $price < 7500) {
            return 12;
        } else if ($price >= 7500) {
            return 18;
        }
    }

    public function checkAccess($api_key, $hotel_id)
    {
        //$hotel=new HotelInformation();
        $cond = array('api_key' => $api_key);
        $comp_info = CompanyDetails::select('company_id')
            ->where($cond)->first();
        if (!$comp_info['company_id']) {
            return "Invalid";
        }
        $conditions = array('hotel_id' => $hotel_id, 'company_id' => $comp_info['company_id']);
        $info = HotelInformation::select('hotel_name')->where($conditions)->first();
        if ($info) {
            return 'valid';
        } else {
            return "invalid";
        }
    }

    public function getHotelInfo($hotel_id)
    {
        $hotel = HotelInformation::select('*')->where('hotel_id', $hotel_id)->first();
        return $hotel;
    }

    public function prepareRoomTypes($room_details)
    {
        $room_types = array();
        foreach ($room_details as $room_detail) {
            $room_type_id = $room_detail['room_type_id'];
            $rate_plan_id = $room_detail['rate_plan_id'];
            $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
            $room_type_array = MasterRoomType::select('room_type')->where($conditions)->first();
            $room_type = $room_type_array['room_type'];
            $rate_plan = MasterRatePlan::where('rate_plan_id', $rate_plan_id)->where('is_trash', 0)->first();
            $plan_type = $rate_plan['plan_type'];
            $rooms = $room_detail['no_of_rooms'];
            array_push($room_types, $rooms . ' ' . $room_type . '(' . $plan_type . ')');
        }
        return $room_types;
    }

    public function getUserDetails($user_id)
    {
        $user = User::select('*')->where('user_id', $user_id)->first();
        return $user;
    }

    public function getBaseCurrency($hotel_id)
    {
        $company = CompanyDetails::join('hotels_table', 'company_table.company_id', 'hotels_table.company_id')
            ->where('hotels_table.hotel_id', $hotel_id)
            ->select('currency', 'hex_code')
            ->first();
        if ($company) {
            return $company;
        }
    }

    // public function bookingsTest(string $api_key, Request $request)
    // {
    //     //store the log
    //     $logpath = storage_path("logs/prip-cart.log" . date("Y-m-d"));
    //     $logfile = fopen($logpath, "a+");
    //     fwrite($logfile, "Processing starts at: " . date("Y-m-d H:i:s") . "\n");
    //     fclose($logfile);
    //     $data = $request->all();
    //     $booking_details = $data['booking_details'];
    //     $hotel_id = $booking_details['hotel_id'];
    //     $payment_mode = $booking_details['payment_mode'];
    //     $cart_info = json_encode($data);
    //     $logfile = fopen($logpath, "a+");
    //     fwrite($logfile, "cart data: " . $hotel_id . $cart_info . "\n");
    //     fwrite($logfile, "Processing ends at: " . date("Y-m-d H:i:s") . "\n");
    //     fclose($logfile);

    //     $invoice = new Invoice();
    //     $room_details = $data['room_details'];
    //     $user_details = $data['user_details'];
    //     $amount_to_pay = $booking_details['amount_to_pay'];
    //     $currency = $booking_details['currency'];
    //     $check_in = date('Y-m-d', strtotime($booking_details['checkin_date']));
    //     $check_out = date('Y-m-d', strtotime($booking_details['checkout_date']));
    //     $checkin = date_create($check_in);
    //     $checkout = date_create($check_out);
    //     $date = date('Y-m-d');
    //     $diff = date_diff($checkin, $checkout);
    //     $diff = $diff->format("%a");
    //     if ($diff == 0) {
    //         $diff = 1;
    //     }

    //     if(strlen($user_details['mobile']) != '10'){
    //         $res = array('status' => 0, 'message' => "Mobile number should be 10 digits");
    //         return response()->json($res);
    //     }

    //     //Checkin date validation
    //     if ($date > $check_in) {
    //         $res = array('status' => 0, 'message' => "Check-in date must not be past date.");
    //         return response()->json($res);
    //     }

    //     //Hotel Validation 
    //     $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('company_id', 'gst_slab', 'is_taxable', 'partial_pay_amt')->first();
    //     $status = "invalid";
    //     $status = $this->checkAccess($api_key, $hotel_id);
    //     if ($status == "invalid") {
    //         $res = array('status' => 0, 'message' => "Invalid company or Hotel");
    //         return response()->json($res);
    //     }
    //     $company_name = DB::table('kernel.company_table')->where('company_id', $hotel_info->company_id)->first();

    //     //User registration
    //     $user_data['first_name'] = $user_details['first_name'];
    //     $user_data['last_name'] = $user_details['last_name'];
    //     $user_data['email_id'] = $user_details['email_id'];
    //     $user_data['mobile'] = $user_details['mobile'];
    //     $country = Country::where('country_id',$user_details['country'])->first();
    //     $state = State::where('state_id',$user_details['state'])->first();
      
    //     if(is_numeric($user_details['city'])){
    //         $city = City::where('city_id',$user_details['city'])->first();
    //         $city_name = $city->city_name;
    //         $city_id = $user_details['city'];
    //     }else{
    //         $city_name = $user_details['city'];
    //         $payload = [
    //             'city_name' => $city_name,
    //             'state_id' => $user_details['state'],
    //         ];

    //         $curl = curl_init();
    //         curl_setopt_array($curl, array(
    //         CURLOPT_URL => 'https://kernel.bookingjini.com/add-new-city',
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => 'POST',
    //         CURLOPT_POSTFIELDS =>json_encode($payload),
    //         CURLOPT_HTTPHEADER => array(
    //             'Content-Type: application/json'
    //         ),
    //         ));

    //         $response = curl_exec($curl);
    //         curl_close($curl);

    //         $city_id = $response['data'];
    //     }

    //     $user_data['password'] = uniqid(); //To generate unique rsndom number
    //     $user_data['password'] = Hash::make($user_data['password']); //Password encryption
    //     $user_data['country'] = $country->country_name;
    //     $user_data['state'] = $state->state_name;
    //     $user_data['city'] = $city_name;
    //     $user_data['zip_code'] = $user_details['zip_code'];
    //     $user_data['company_name'] = $user_details['company_name'];
    //     $user_data['GSTIN'] = $user_details['GST_IN'];
    //     $user_data['address'] = $user_details['address'];
    //     $res = User::updateOrCreate(
    //         [
    //             'mobile' => $user_details['mobile'],
    //             'company_id' => $hotel_info->company_id
    //         ],
    //         $user_data
    //     );
    //     $user_id = $res->user_id;


    //     //insert in new user table
    //     $new_user_data['first_name'] = $user_details['first_name'];
    //     $new_user_data['last_name'] = $user_details['last_name'];
    //     $new_user_data['email_id'] = $user_details['email_id'];
    //     $new_user_data['mobile'] = $user_details['mobile'];
    //     if (isset($user_details['mobile'])) {
    //         $user_name = $user_details['mobile'];
    //     } else {
    //         $user_name = $user_details['email_id'];
    //     }
    //     $new_user_data['password'] = uniqid(); //To generate unique rsndom number
    //     $new_user_data['password'] = Hash::make($user_data['password']); //Password encryption
    //     $new_user_data['country'] = $user_details['country'];
    //     $new_user_data['state'] = $user_details['state'];
    //     $new_user_data['city'] = $city_id;
    //     $new_user_data['zip_code'] = $user_details['zip_code'];
    //     $new_user_data['company_name'] = $user_details['company_name'];
    //     $new_user_data['GSTIN'] = $user_details['GST_IN'];
    //     $new_user_data['locality'] = $user_details['address'];
    //     $new_user_data['user_name'] = $user_name;
    //     $new_user_data['bookings'] = '';
    //     $user_res = UserNew::updateOrCreate(['mobile' => $user_details['mobile']], $new_user_data);
    //     $user_id_new = $user_res->user_id;



    //     //Store invoice details
    //     $inv_data = array();
    //     $inv_data['hotel_id']   = $hotel_id;
    //     $hotel = $this->getHotelInfo($hotel_id);
    //     $inv_data['hotel_name'] = $hotel->hotel_name;
    //     $inv_data['room_type']  = json_encode($this->prepareRoomTypes($room_details));
    //     $inv_data['ref_no'] = rand() . strtotime("now");
    //     $inv_data['check_in_out'] = "[" . $check_in . '-' . $check_out . "]";
    //     $inv_data['booking_date'] = date('Y-m-d H:i:s');
    //     $inv_data['booking_status'] = 2;
    //     $inv_data['user_id'] = $user_id;
    //     $inv_data['user_id_new'] = $user_id_new;
       
    //     $payload = [
    //         "hotel_id" => $hotel_id,
    //         "user_id" => 0,
    //     ];
      
    //     $payload = json_encode($payload);
      
    //     $curl = curl_init();
    //     curl_setopt_array($curl, array(
    //     CURLOPT_URL => 'https://subscription.bookingjini.com/api/my-subscription',
    //     CURLOPT_RETURNTRANSFER => true,
    //     CURLOPT_ENCODING => '',
    //     CURLOPT_MAXREDIRS => 10,
    //     CURLOPT_TIMEOUT => 0,
    //     CURLOPT_FOLLOWLOCATION => true,
    //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //     CURLOPT_CUSTOMREQUEST => 'POST',
    //     CURLOPT_POSTFIELDS => $payload,
    //     CURLOPT_HTTPHEADER => array(
    //         'Content-Type: application/json',
    //         'X_Auth_Subscription-API-KEY: Vz9P1vfwj6P6hiTCc9ddC5bNieqA5ScT'
    //     ),
    //     ));
      
    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     $be_sources = json_decode($response);

    //     $inv_data['is_cm'] = 0;
    //     if ($be_sources && !empty($be_sources->apps)) {
    //         foreach ($be_sources->apps as $be_source) {
    //             if ($be_source->app_code == 'JINI HIVE') {
    //                 $inv_data['is_cm'] = 1;
    //             }
    //         }
    //     }
        
    //     $visitors_ip = $this->ipService->getIPAddress();
    //     $inv_data['visitors_ip'] = $visitors_ip;
    //     $inv_data['booking_source'] = "website";
    //     $inv_data['guest_note'] = $user_details['guest_note'];
    //     $inv_data['arrival_time'] = $user_details['arrival_time'];
    //     $inv_data['company_name'] = $user_details['company_name'];
    //     $inv_data['gstin'] = $user_details['GST_IN'];
    //     $inv_data['agent_code'] = '';

    //     //Room price calculation
    //     $room_price_including_gst = 0;
    //     $room_price_excluding_tax = 0;
    //     $total_gst_price = 0;
    //     $total_discount_price = 0;
    //     $extra_details = array();
    //     $hotel_booking_details = array();
    //     $cart = array();
    //     if (sizeof($room_details) > 0) {
    //         $invoice_details_array = [];
    //         $per_room_occupancy = [];
    //         foreach ($room_details as $details) {
    //             $room_type_id = $details['room_type_id'];
    //             $rate_plan_id = $details['rate_plan_id'];
    //             $occupancy = $details['occupancy'];
    //             $occupancy_details = $details['occupancy'];
    //             foreach ($occupancy_details as $occupancy_detail) {
    //                 array_push($extra_details, array($details['room_type_id'] => array($occupancy_detail['adult'], $occupancy_detail['child'])));
    //             }

    //             //Room type validation
    //             $room_type_details = MasterRoomType::where('hotel_id', $hotel_id)
    //                 ->where('room_type_id', $room_type_id)
    //                 ->where('is_trash', 0)
    //                 ->first();

    //             if (empty($room_type_details)) {
    //                 $res = array('status' => 0, 'message' => "Invalid room types");
    //                 return response()->json($res);
    //             }

    //             $base_adult = $room_type_details->max_people;
    //             $base_child = $room_type_details->max_child;

    //             $room_rate = CurrentRate::where('hotel_id', $hotel_id)
    //                 ->where('room_type_id', $room_type_id)
    //                 ->where('rate_plan_id', $rate_plan_id)
    //                 ->where('ota_id', '-1')
    //                 ->whereBetween('stay_date', [$check_in, $check_out])
    //                 ->get();

    //             foreach ($room_rate as $rate) {
    //                 $filtered_rate[$rate['stay_date']] = $rate;
    //             }

    //             $check_in_data = $check_in;
    //             $coupons_percentage = 0;
    //             $booking = [];
    //             $public_coupon_array = [];
    //             $private_coupon_array = [];

    //             for ($i = 0; $i < $diff; $i++) {
    //                 $d = $check_in_data;
    //                 $discounted_price = 0;
    //                 $private_coupon_id = $booking_details['private_coupon'];

    //                 $coupons = Coupons::where('hotel_id', $hotel_id)
    //                     ->where('valid_from', '<=', $d)
    //                     ->where('valid_to', '>=', $d)
    //                     ->whereIN('room_type_id', [0, $room_type_id])
    //                     ->where('is_trash', 0)
    //                     ->get();

    //                 if (sizeof($coupons) > 0) {
    //                     $multiple_coupon_one_date = [];
    //                     foreach ($coupons as $coupon) {
    //                         if ($coupon->coupon_for == 1) {
    //                             $multiple_coupon_one_date[] = $coupon->discount;
    //                         }
    //                         if ($coupon->coupon_for == 2) {
    //                             $private_coupon_array[$d] = $coupon->discount;
    //                         }
    //                     }
    //                     if (!empty($multiple_coupon_one_date)) {
    //                         $public_coupon_array[$d] = max($multiple_coupon_one_date);
    //                     } else {
    //                         $public_coupon_array[$d] = 0;
    //                     }
    //                 } else {
    //                     $public_coupon_array[$d] = 0;
    //                 }

    //                 if ($private_coupon_id != '') {
    //                     if ($private_coupon_array[$d] >= $public_coupon_array[$d]) {
    //                         $coupons_percentage = $private_coupon_array[$d];
    //                     } else {
    //                         $coupons_percentage = $public_coupon_array[$d];
    //                     }
    //                 } else {
    //                     $coupons_percentage = $public_coupon_array[$d];
    //                 }

    //                 $room_occu = [];
    //                 foreach ($occupancy as $key => $acc) {
    //                     $adult = $acc['adult'];
    //                     $child = $acc['child'];

    //                     $multiple_occupancy = $filtered_rate[$d]->multiple_occupancy;
    //                     $multiple_occupancy = json_decode($multiple_occupancy);
    //                     if (is_string($multiple_occupancy)) {
    //                         $multiple_occupancy = json_decode($multiple_occupancy);
    //                     }
    //                     $extra_adult = 0;
    //                     $extra_child = 0;
    //                     $bar_price = $filtered_rate[$d]->bar_price;
    //                     $extra_adult_price = $filtered_rate[$d]->extra_adult_price;
    //                     $extra_child_price = $filtered_rate[$d]->extra_child_price;

    //                     if ($base_child < $child) {
    //                         $extra_child = $child - $base_child;
    //                     }

    //                     if ($base_adult == $adult) {
    //                         $room_price = $bar_price;
    //                     } else if ($base_adult > $adult) {
    //                         $acc = $acc['adult'] - 1;
    //                         $room_price = isset($multiple_occupancy[$acc]) ? $multiple_occupancy[$acc] : $bar_price;
    //                     } else {
    //                         $extra_adult = $adult - $base_adult;
    //                         $room_price = $bar_price;
    //                     }

    //                     $discounted_price = $room_price * $coupons_percentage / 100;
    //                     $price_after_discount =  $room_price -  $discounted_price;

    //                     //Gst calculation
    //                     if ($hotel_info->is_taxable == 1) {
    //                         if ($hotel_info->gst_slab == 1) {
    //                             $price_for_gst_slab = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);
    //                             $gst_percentage = $this->checkGSTPercent($price_for_gst_slab);
    //                         } else {
    //                             $gst_percentage = $this->checkGSTPercent($price_after_discount);
    //                         }
    //                         $room_price_excluding_gst = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);

    //                         $gst_price = $room_price_excluding_gst * $gst_percentage / 100;
    //                     } else {
    //                         $room_price_excluding_gst = $price_after_discount + ($extra_adult_price * $extra_adult) + ($extra_child_price * $extra_child);
    //                         $gst_price = 0;
    //                     }

    //                     $room_price_excluding_tax += $room_price_excluding_gst;
    //                     $room_price_including_gst += $room_price_excluding_gst + $gst_price;
    //                     $total_gst_price += $gst_price;
    //                     $total_discount_price += $discounted_price;

    //                     $rwo['selected_adult'] = $adult;
    //                     $rwo['selected_child'] = $child;
    //                     $rwo['extra_adult'] = $extra_adult;
    //                     $rwo['extra_child'] = $extra_child;
    //                     array_push($room_occu, $rwo);

    //                     //Store the details in invoice details
    //                     $invoice_details['hotel_id'] = $hotel_id;
    //                     $invoice_details['user_id'] = $user_id;
    //                     $invoice_details['room_type_id'] = $details['room_type_id'];
    //                     $invoice_details['rate_plan_id'] = $details['rate_plan_id'];
    //                     $invoice_details['date'] = $d;
    //                     $invoice_details['room_rate'] = $room_price;
    //                     $invoice_details['extra_adult'] = $extra_adult;
    //                     $invoice_details['extra_child'] = $extra_child;
    //                     $invoice_details['extra_adult_price'] = $extra_adult_price;
    //                     $invoice_details['extra_child_price'] = $extra_child_price;
    //                     $invoice_details['discount_price'] = (float)$discounted_price;
    //                     $invoice_details['price_after_discount'] = (float)$room_price - $discounted_price;
    //                     $invoice_details['rooms'] = $key + 1;
    //                     $invoice_details['gst_price'] = (float)$gst_price;
    //                     $invoice_details['total_price'] = (float)$room_price_excluding_gst + $gst_price;
    //                     array_push($invoice_details_array, $invoice_details);
    //                 }

    //                 //Prepared cart details
    //                 $bookings['selected_adult'] = $adult;
    //                 $bookings['selected_child'] = $child;
    //                 $bookings['rate_plan_id'] = $details['rate_plan_id'];
    //                 $bookings['extra_adult_price'] = 0;
    //                 $bookings['extra_child_price'] = 0;
    //                 $bookings['bar_price'] = $room_price;
    //                 array_push($booking, $bookings);

    //                 $check_in_data = date('Y-m-d', strtotime($d . ' +1 day'));
    //             }

    //             array_push($per_room_occupancy, $room_occu);

    //             //store the data in hotel booking table
    //             $hotel_booking_data['room_type_id'] = $details['room_type_id'];
    //             $hotel_booking_data['rate_plan_id'] = $details['rate_plan_id'];
    //             $hotel_booking_data['rooms'] = sizeof($occupancy);
    //             $hotel_booking_data['check_in'] = $check_in;
    //             $hotel_booking_data['check_out'] = $check_out;
    //             $hotel_booking_data['booking_status'] = 2; //Intially Un Paid
    //             $hotel_booking_data['user_id'] = $user_id;
    //             $hotel_booking_data['booking_date'] = date('Y-m-d');
    //             $hotel_booking_data['hotel_id'] = $hotel_id;
    //             array_push($hotel_booking_details, $hotel_booking_data);
    //             array_push($cart, array('rooms' => $booking, 'room_type_id' => $details['room_type_id'], 'room_type' => $room_type_details->room_type));
    //         }


    //         //Paid services calculations
    //         $paid_service = $data['paid_service'];
    //         $paid_service_id = [];
    //         $total_paidservices_amount = 0;
    //         if ($paid_service) {
    //             foreach ($paid_service as $service) {


    //                 $paid_service_id[] = $service['service_no'];
    //             }

    //             $services = DB::table('paid_service')
    //                 ->where('hotel_id', $hotel_id)
    //                 ->whereIn('paid_service_id', $paid_service_id)
    //                 ->select('paid_service_id', 'service_amount', 'service_tax')
    //                 ->where('is_trash', 0)
    //                 ->get();

    //             foreach ($services as $key => $service) {
    //                 $paid_service_amount = $service->service_amount;
    //                 $paid_service_tax = $paid_service_amount * $service->service_tax / 100;
    //                 $total_paidservices_amount += ($paid_service_amount + $paid_service_tax) * $paid_service[$key]['qty'];
    //             }
    //         }



    //         //Addon Carges calculations
    //         $add_on_charges = DB::table('kernel.add_on_charges')
    //             ->where('hotel_id', $hotel_id)
    //             ->where('is_active', 1)
    //             ->get();

    //         $addon_charges = 0;
    //         if (sizeof($add_on_charges) > 0) {
    //             foreach ($add_on_charges as $add_on_charge) {
    //                 $add_on_percentage = $add_on_charge->add_on_charges_percentage;
    //                 $add_on_tax_percentage = $add_on_charge->add_on_tax_percentage;
    //                 $add_on_price = $room_price_excluding_tax * $add_on_percentage / 100;

    //                 $add_on_tax_price = 0;
    //                 if ($add_on_tax_percentage) {
    //                     $add_on_tax_price = $add_on_price * $add_on_tax_percentage / 100;
    //                 }
    //                 $addon_charges += $add_on_price + $add_on_tax_price;
    //             }
    //         }

    //         $room_price_including_gst = $room_price_including_gst + $total_paidservices_amount + $addon_charges;

    //         if ($currency != 'INR') {
    //             $currency_value = CurrencyDetails::where('name', $currency)->first();
    //             $amount_to_pay = (1 / $currency_value->currency_value) * $amount_to_pay;
    //         }

    //         $total_amount = $room_price_including_gst;
    //         if ($payment_mode == 2) {
    //             $partial_pay_per = $hotel_info->partial_pay_amt;
    //             $room_price_including_gst = $room_price_including_gst * $partial_pay_per / 100;
    //         } elseif ($payment_mode == 3) {
    //             $room_price_including_gst = 0;
    //         } else {
    //             $room_price_including_gst = $room_price_including_gst;
    //         }

    //         $room_price_including_gst = number_format((float)$room_price_including_gst, 2, '.', '');



    //         if (round($amount_to_pay) != round($room_price_including_gst)) {
    //             $res = array('status' => 0, 'message' => "Booking failed due to data tampering", 'data' => $room_price_including_gst);
    //             return response()->json($res);
    //         }
    //     } else {
    //         $res = array('status' => 0, 'message' => "Booking failed invalid room type");
    //         return response()->json($res);
    //     }

    //     $inv_data['total_amount'] = number_format((float)$total_amount, 2, '.', '');
    //     $inv_data['tax_amount'] = number_format((float)$total_gst_price, 2, '.', '');
    //     $inv_data['paid_amount'] = number_format((float)$room_price_including_gst, 2, '.', '');
    //     $inv_data['discount_amount'] = number_format((float)$total_discount_price, 2, '.', '');
    //     $inv_data['extra_details'] = json_encode($extra_details);
    //     $inv_data['paid_service_id'] = '';
    //     $inv_data['invoice'] = '';

    //     $refund_protect_data = $booking_details['opted_book_assure'];
    //     $refund_protect_price_info = isset($refund_protect_data['refund_protect_price']) && $refund_protect_data['refund_protect_price'] > 0 ? $refund_protect_data['refund_protect_price'] : 0;
    //     $rp_member_id = 0;
    //     $source_info = '';
    //     $refund_protect_sold_status = isset($refund_protect_data['sold']) ? $refund_protect_data['sold'] : '';
    //     $coupon = '';

    //     // $invoice_id = 189228;

    //     // $inv_data['invoice'] = $this->createInvoice($hotel_id, $invoice_id, $cart, $coupon, $total_paidservices_amount, $check_in, $check_out, $inv_data['user_id'], $refund_protect_price_info, $rp_member_id, $source_info, $inv_data['total_amount'], $inv_data['paid_amount'], $per_room_occupancy);

    //     // return $inv_data['invoice'];

    //     $result = $invoice->fill($inv_data)->save();
    //     if ($result) {
    //         $invoice_id = $invoice->invoice_id;

    //         foreach ($hotel_booking_details as &$hotel_booking_detail) {
    //             $hotel_booking_detail['invoice_id'] = $invoice_id;
    //         }
    //         HotelBooking::insert($hotel_booking_details);

    //         foreach ($invoice_details_array as &$invoice_detail) {
    //             $invoice_detail['invoice_id'] = $invoice_id;
    //         }
    //         DayWisePrice::insert($invoice_details_array);

    //         $coupon = '';
    //         // if($hotel_id==2600){
    //         //     try {
    //                 $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
    //                 $bookings_details = HotelBooking::where('invoice_id', $invoice_id)->get();

    //                 foreach ($bookings_details as $day_wise_booking) {
    //                     $room_type_id = $day_wise_booking->room_type_id;
    //                     $day_wise_bookings = DayWisePrice::where('invoice_id', $invoice_id)
    //                     ->leftjoin('kernel.room_type_table', 'room_type_table.room_type_id', 'voucher_day_wise_price.room_type_id')
    //                     ->leftjoin('kernel.rate_plan_table', 'rate_plan_table.rate_plan_id', 'voucher_day_wise_price.rate_plan_id')
    //                     ->select('voucher_day_wise_price.*', 'room_type_table.room_type', 'rate_plan_table.plan_name', 'room_type_table.max_people', 'room_type_table.max_child')
    //                     ->where('voucher_day_wise_price.room_type_id', $room_type_id)
    //                         ->get();
                            
    //                     $room_price_dtl = [];
    //                     $extra_adult_dtl = [];
    //                     $extra_child_dtl = []; 
    //                     $gst_dtl = [];
    //                     $adult_dtl = [];
    //                     $child_dtl = [];
    //                     $discount_price = [];

    //                     foreach ($day_wise_bookings as $day_wise_booking) {
    //                         $room_price_dtl[] = $day_wise_booking->room_rate;
    //                         $extra_adult_dtl[] = $day_wise_booking->extra_adult;
    //                         $extra_child_dtl[] = $day_wise_booking->extra_child;
    //                         $gst_dtl[] = $day_wise_booking->gst_price;
    //                         $adult_dtl[] = $day_wise_booking->max_people;
    //                         $child_dtl[] = $day_wise_booking->max_child;
    //                         $discount_price[] = $day_wise_booking->discount_price;
    //                     }

    //                     $room_price_dtl = implode(',', $room_price_dtl);
    //                     $extra_adult_dtl = implode(',', $extra_adult_dtl);
    //                     $extra_child_dtl = implode(',', $extra_child_dtl);
    //                     $gst_dtl = implode(',', $gst_dtl);
    //                     $adult_dtl = implode(',', $adult_dtl);
    //                     $child_dtl = implode(',', $child_dtl);
    //                     $discount_price = implode(',', $discount_price);

    //                     $be_booking_det['hotel_id'] = $day_wise_bookings[0]['hotel_id'];
    //                     $be_booking_det['ref_no'] = $invoice_details->ref_no;
    //                     $be_booking_det['room_type'] = $day_wise_bookings[0]['room_type'];
    //                     $be_booking_det['rooms'] = count($day_wise_bookings);
    //                     $be_booking_det['room_rate'] = $room_price_dtl;
    //                     $be_booking_det['extra_adult'] = $extra_adult_dtl;
    //                     $be_booking_det['extra_child'] =  $extra_child_dtl;
    //                     $be_booking_det['discount_price'] =  $discount_price;
    //                     $be_booking_det['adult'] = $adult_dtl;
    //                     $be_booking_det['child'] = $child_dtl;
    //                     $be_booking_det['room_type_id'] = $day_wise_bookings[0]['room_type_id'];
    //                     $be_booking_det['tax_amount'] = $gst_dtl;
    //                     $be_booking_det['rate_plan_name'] = $day_wise_bookings[0]['plan_name'];
    //                     $be_booking_det['rate_plan_id'] = $day_wise_bookings[0]['rate_plan_id'];

    //                     $insert_be_bookings_table = BeBookingDetailsTable::insert($be_booking_det);
    //                 }
    //         //     } catch (Throwable $e) {
    //         //         $res = array('status' => -1, 'response_msg' => $e->getMessage());
    //         //         $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => date("YmdHis"));
 
    //         //         $result = json_encode($result);
    //         //         $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);
        
    //         //         return response()->json($res);
                    
    //         //     }
    //         // }
               

    //         $inv_data['invoice'] = $this->createInvoiceTest($hotel_id, $invoice_id, $cart, $coupon, $total_paidservices_amount, $check_in, $check_out, $inv_data['user_id'], $refund_protect_price_info, $rp_member_id, $source_info, $inv_data['total_amount'], $inv_data['paid_amount'], $per_room_occupancy);

    //         $update_vouchere = invoice::where('invoice_id', $invoice_id)->update(['invoice' => $inv_data['invoice']]);

    //         $user_data = $this->getUserDetails($inv_data['user_id']);
    //         $b_invoice_id = base64_encode($invoice_id);
            
    //         $this->preinvoiceMail($invoice_id);
            
    //         $invoice_hashData = $invoice_id . '|' . $room_price_including_gst . '|' . $room_price_including_gst . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;

    //         $invoice_secureHash = hash('sha512', $invoice_hashData);
    //         $res = array("status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $invoice_id, 'invoice_secureHash' => $invoice_secureHash);
    //         return response()->json($res);
    //     } else {
    //         $res = array('status' => -1, "message" => 'Booking Failed');
    //         $res['errors'][] = "Internal server error";
    //         return response()->json($res);
    //     }
    // }


    public function createInvoiceTest($hotel_id, $invoice_id, $cart, $coupon, $paid_services, $check_in, $check_out, $user_id, $refund_protect_price, $rp_member_id, $source, $total_amount, $paid_amount_info, $per_room_occupancy)
    {
        $booking_id = "#####";
        $transaction_id = ">>>>>";
        $booking_date = date('Y-m-d');
        $booking_date = date("jS M, Y", strtotime($booking_date));
        $hotel_details = $this->getHotelInfo($hotel_id);
        $u = $this->getUserDetails($user_id);
        $dsp_check_in = date("jS M, Y", strtotime($check_in));
        $dsp_check_out = date("jS M, Y", strtotime($check_out));
        $diff = abs(strtotime($check_out) - strtotime($check_in));
        if ($diff == 0) {
            $diff = 1;
        }
        $years = floor($diff / (365 * 60 * 60 * 24));
        $months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
        $day = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));
        if ($day == 0) {
            $day = 1;
        }
        $total_adult = 0;
        $total_child = 0;
        $all_room_type_name = "";
        $paid_service_details = "";
        $all_rows_date = "";
        $total_discount_price = 0;
        $total_price_after_discount = 0;
        $total_tax = 0;
        $other_tax_arr = array();

        //Get base currency and currency hex code
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;
        $currency_code = $this->getBaseCurrency($hotel_id)->hex_code;
        $grand_total_amount = 0;
        $grand_total_amount_after_discount = 0;
        $discount = 0;

        foreach ($cart as $j => $cartItem) {
            $room_type_id = $cartItem['room_type_id'];
            $room_type = $cartItem['room_type'];

            $rate_plan_id = $cartItem['rooms'][0]['rate_plan_id'];
            $conditions = array('rate_plan_id' => $rate_plan_id, 'is_trash' => 0);
            $rate_plan_id_array = MasterRatePlan::select('plan_name')->where($conditions)->first();
            $rate_plan = $rate_plan_id_array['plan_name'];

            $total_room_amount = 0;
            $total_room_amount_after_discount = 0;

            $all_room_type_name .= ',' . sizeof($cartItem['rooms']) . ' ' . $room_type;
            "";

            $all_rows_date .= '<tr><td colspan="13" bgcolor="#ec8849" align="center" style="font-weight:700;padding:10px; ">' . $room_type . '(' . $rate_plan . ')</td>';

            $all_rows_date .= '<tr>
                  <th bgcolor="#A6ACAF" colspan="3" align="center">Rooms</th>
                  <th bgcolor="#A6ACAF" colspan="3" align="center">Date</th>
                  <th bgcolor="#A6ACAF" align="center">Room Rate</th>
                  <th bgcolor="#A6ACAF" align="center">Discount</th>
                  <th bgcolor="#A6ACAF" align="center">Price After Discount</th>
                  <th bgcolor="#A6ACAF" align="center">Extra Adult Price</th>
                  <th bgcolor="#A6ACAF" align="center">Extra Child Price</th>
                  <th bgcolor="#A6ACAF" align="center">Total Price</th>
                  <th bgcolor="#A6ACAF" align="center">GST</th>
                  </tr>';

            $dates = DayWisePrice::where('invoice_id', $invoice_id)->where('room_type_id', $room_type_id)->groupBy('date')->get();
            $rooms = DayWisePrice::where('invoice_id', $invoice_id)->where('room_type_id', $room_type_id)->groupBy('rooms')->get();
            $rooms_row_span = sizeof($cartItem['rooms']) + 1;

            foreach ($rooms as $k => $room) {

                $occupancy_room_wise = '';
                if (isset($per_room_occupancy[$j][$k]['selected_adult'])) {
                    $selected_adult = $per_room_occupancy[$j][$k]['selected_adult'];
                    $selected_child = $per_room_occupancy[$j][$k]['selected_child'];
                    $extra_adult = $per_room_occupancy[$j][$k]['extra_adult'];
                    $extra_child = $per_room_occupancy[$j][$k]['extra_child'];
                    $base_adult = $selected_adult - $extra_adult;
                    $base_child = 0;
                    if ($selected_child != 0) {
                        $base_child = $selected_child - $extra_child;
                    }
                    $occupancy_room_wise .= 'Adult' . '(' . $base_adult . '+' . $extra_adult . ')' . 'Child' . '(' . $base_child . '+' . $extra_child . ')';

                    $total_adult += $selected_adult;
                    $total_child += $selected_child;
                }


                $all_rows_date .= '<tr>
                <td rowspan="' . $rooms_row_span . '" colspan = "1" align="center">Room - ' . ($k + 1) . '</td>
                <td rowspan="' . $rooms_row_span . '" colspan = "2" align="center">' . $occupancy_room_wise . '</td>';
                foreach ($dates as $date) {
                    $day_wise_rooms_details = DayWisePrice::where('invoice_id', $invoice_id)->where('room_type_id', $room_type_id)->where('date', $date['date'])->where('rooms', $room['rooms'])->get();
                    $stay_date = date("jS M, Y", strtotime($date['date']));

                    $all_rows_date .= '<tr><td rowspan="1" colspan = "3" align="center">' . $stay_date . '</td>';

                    foreach ($day_wise_rooms_details as $key => $day_wise_room) {
                        $extra_adult_price = $day_wise_room['extra_adult_price'] * $day_wise_room['extra_adult'];
                        $extra_child_price = $day_wise_room['extra_child_price'] * $day_wise_room['extra_child'];

                        $total_amount = $day_wise_room['room_rate'] + $extra_adult_price + $extra_child_price;
                        $total_amount_after_discount = $day_wise_room['room_rate'] - $day_wise_room['discount_price'] + $extra_adult_price + $extra_child_price;
                        $total_room_amount += $total_amount;
                        $total_room_amount_after_discount += $total_amount_after_discount;
                        $discount += $day_wise_room['discount_price'];
                        $total_tax += $day_wise_room['gst_price'];

                        $all_rows_date = $all_rows_date .
                            '<td  align="center">' . $currency_code . number_format((float)$day_wise_room['room_rate'], 2, '.', '') . '</td>
                                  <td  align="center">' . $currency_code . number_format((float)$day_wise_room['discount_price'], 2, '.', ''). '</td>
                                  <td  align="center">' . $currency_code . number_format((float)$day_wise_room['price_after_discount'], 2, '.', '') . '</td>
                                  <td  align="center">' . $currency_code .  number_format((float)$extra_adult_price, 2, '.', ''). '</td>
                                  <td  align="center">' . $currency_code .  number_format((float)$extra_child_price, 2, '.', ''). '</td>
                                  <td  align="center">' . $currency_code .  number_format((float)$total_amount, 2, '.', ''). '</td>
                                  <td  align="center">' . $currency_code .  number_format((float)$day_wise_room['gst_price'], 2, '.', '') . '</td></tr>';
                    }
                }
            }

            $grand_total_amount += $total_room_amount;
            $grand_total_amount_after_discount += $total_room_amount_after_discount;

            $all_rows_date .= '<tr><td colspan="11" align="right">Total Amount</td><td align="center">' . $currency_code . $total_room_amount . '</td>
                  </tr>';
        }
        $total_paid_amount = $grand_total_amount_after_discount + $total_tax;
        if ($total_discount_price > 0) {
            $display_discount = $total_discount_price;
        }
        $service_amount = 0;
        if (sizeof($paid_services) > 0) {
            foreach ($paid_services as $paid_service) {
                $paid_service_details = $paid_service_details . '<tr>
                      <td colspan="8" style="text-align:right;">' . $paid_service['service_name'] . '&nbsp;&nbsp;</td>
                      <td style="text-align:center;">' . $currency_code . ($paid_service['price'] * $paid_service['qty']) . '</td>
                      <tr>';
                $service_amount += $paid_service['price'] * $paid_service['qty'];
            }
            $paid_service_details = '<tr><td colspan="8" bgcolor="#ec8849" style="text-align:center; font-weight:bold;">Paid Service Details</td></tr>' . number_format((float)$paid_service_details, 2, '.', '');
        }
        $gst_tax_details = "";
        if ($baseCurrency == 'INR') {
            $gst_tax_details = '<tr>
                  <td colspan="12" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
                  <td align="center">' . $currency_code . number_format((float)$total_tax, 2, '.', '') . '</td>
                  </tr>';
        }
        if ($refund_protect_price > 0) {
            $refund_protect_info = '<tr>
                      <td colspan="8" align="right">Refundable booking charges &nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . $refund_protect_price . '</td>
                  </tr>';
            $refund_protect_description = '
                  <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">If your booking is cancelled or you need to make changes  :</span>Please contact our customer service team at <a href="mailto:support@bookingjini.com">support@bookingjini.com</a>.</td>
                  </tr>
                  <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">Refundable Booking :</span>
                      This is a Refundable booking, so if you are unable to attend your booking due to unforeseen circumstances and can provide evidence as listed in the Terms and Conditions <a href="https://refundable.me/extended/en" target="_blank">here</a>. you may be entitled to a full refund.<br>You will need your reference number <b>#####</b> to apply for your refund using the form ';
            if ($rp_member_id == 295) {
                $refund_protect_description .= '<a href="https://form.refundable.me/forms/refund?memberId=295&bookingReference=#####" target="_blank">here</a>.</td>';
            } elseif ($rp_member_id == 298) {
                $refund_protect_description .= '<a href="https://form.refundable.me/forms/refund?memberId=298&bookingReference=#####" target="_blank">here</a>.</td>';
            }
            $refund_protect_description .= '</tr>';
        } else {
            $refund_protect_info = '';
            $refund_protect_description = '';
        }
        $other_tax_details = "";
        if (
            sizeof($other_tax_arr) > 0
        ) {
            foreach ($other_tax_arr as $other_tax) {
                $other_tax_details = $other_tax_details . '<tr>
                  <td colspan="8" style="text-align:right;">' . $other_tax['tax_name'] . '&nbsp;&nbsp;</td>
                  <td style="text-align:center;">' . $currency_code . $other_tax['tax_price'] . '</td>
                  <tr>';
            }
        }
        $total_amt = $total_amount + $service_amount;
        $total_amt = number_format((float)$total_amt, 2, '.', '');
        $total = $total_amt + $total_tax;
        $total = number_format((float)$total, 2, '.', '');

        if(is_string($paid_amount_info)){
            $paid_amount_info = 0;
        }


        $due_amount_info = $total - $paid_amount_info;
        $due_amount_info = number_format((float)$due_amount_info, 2, '.', '');
        $body = '<html>
                      <head>
                      <style>
                      html{
                          font-family: Arial, Helvetica, sans-serif;
                      }
                      table, td {
                          height: 26px;
                      }
                      table, th {
                          height: 35px;
                      }
                      p{
                          color: #000000;
                      }
                      </style>
                      </head>
                      <body style="color:#000;">
                      <div style="margin: 0px auto;">
                      <table width="100%" align="center">
                      <tr>
                      <td style="border: 1px #0; padding: 4%; border-style: double;">
                      <table width="100%" border="0">
                          <tr>
                              <th colspan="2" valign="middle" style="font-size: 23px;"><u>BOOKING CONFIRMATION</u></th>
                          </tr>
                          <tr>
                          <td><b style="color: #ffffff;">*</b></td>
                          </tr>
                          <tr>
                              <td>
                                  <div>
                                      <div style="font-weight: bold; font-size: 22px; color:#fff; background-color: #1d99b5; padding: 5px;">' . $hotel_details['hotel_name'] . '</div>
                                  </div>
                              </td>
                              <td style="font-size: 16px;font-weight: bold;" align="right">BOOKING ID : ' . $booking_id . '</td>
                          </tr>
                          <tr>
                              <td colspan="2"><b style="color: #ffffff;">*</b></td>
                          </tr>';
        if ($source  != 'GEMS') {
            $body .= '<tr><td></td><td style="font-size: 16px;font-weight: bold;" align="right">PAYMENT REFERENCE NUMBER : ' . $transaction_id . '</td></tr>';
        }
        $body .=
            '<tr>
                              <td colspan="2"><b>Dear ' . $u->first_name . ' ' . $u->last_name . ',</b></td>
                          </tr>';

        $body .= ' <tr>
                                  <td colspan="2" style="font-size:17px;"><b>We hope this email finds you well. Thank you for choosing ' . $hotel_details->hotel_name . ' as your property of choice for your visit and booking through our hotel\'s website. Your booking confirmation details have been provided below:</b></td>
                              </tr>';
        $body .= '<tr>
                              <td colspan="2"><b style="color: #ffffff;">*</b></td>
                          </tr>
                      </table>
                      
                      <table width="100%" border="1" style="border-collapse: collapse;">
                          <th colspan="2" bgcolor="#A6ACAF">BOOKING DETAILS</th>
                          <tr>
                              <td >PROPERTY & PLACE</td>
                              <td>' . $hotel_details->hotel_name . '</td>
                          </tr>
                          <tr>
                              <td width="45%">NAME OF PRIMARY GUEST</td>
                              <td>' . $u->first_name . ' ' . $u->last_name . '</td>
                          </tr>
                          <tr>
                              <td>PHONE NUMBER</td>
                              <td>' . $u->mobile . '</td>
                          </tr>
                          <tr>
                              <td>EMAIL ID</td>
                              <td>' . $u->email_id . '</td>
                          </tr>
                          <tr>
                              <td>ADDRESS</td>
                              <td>' . $u->address . ',' . $u->city . ',' . $u->state . ',' . $u->country . '</td>
                          </tr>
                          <tr>
                      <td>BOOKING DATE</td>
                      <td>' . $booking_date . '</td>
                      </tr>
                          <tr>
                      <td>CHECK IN</td>
                      <td>' . $dsp_check_in . '(' . $hotel_details->check_in . ')' . '</td>
                      </tr>
                          <tr>
                      <td>CHECK OUT</td>
                      <td>' . $dsp_check_out . '(' . $hotel_details->check_out . ')' . '</td>
                      </tr>
                              <tr>
                      <td>TOTAL ADULT</td>
                      <td>' . $total_adult . '</td>
                      </tr>
                              <tr>
                      <td>TOTAL CHILDREN</td>
                      <td>' . $total_child . '</td>
                      </tr>
                          <tr>
                      <td>NUMBER OF NIGHTS</td>
                      <td>' . $day . '</td>
                      </tr>
                          <tr>
                      <td>NO. & TYPES OF ACCOMMODATIONS BOOKED</td>
                      <td>' . substr($all_room_type_name, 1) . '</td>
                      </tr>
                      
                      </table>
                      
                      <table width="100%" border="1" style="border-collapse: collapse;">
                          <tr>
                      <th colspan="13" valign="middle" height="" style="font-size: 20px;">TARIFF APPLICABLE</th>
                      </tr>
                          ' . $all_rows_date . '
                      <tr>
                      <td colspan="13"><p style="color: #ffffff;">*</p></td>
                      </tr>
                    <tr>
                      <td colspan="12" align="right">Total Room Price&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$grand_total_amount, 2, '.', '') . '</td>
                      </tr>
                      <tr>
                      <td colspan="12" align="right">Total Discount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$discount, 2, '.', '') . '</td>
                      </tr>
                      <tr>
                      <td colspan="12" align="right">Total Room Price after discount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . number_format((float)$grand_total_amount_after_discount, 2, '.', '') . '</td>
                      </tr>
                      
                    
                      ' . $gst_tax_details . '
                      ' . $other_tax_details . '
                      ' . $refund_protect_info . '
                      <tr>
                      <td colspan="12" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
                      <td align="center">' . $currency_code . number_format((float)$total_paid_amount, 2, '.', '') . '</td>
                      </tr>';
        $body .= '<tr>
                      <td colspan="12" align="right">Total Paid Amount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . '<span id="pd_amt">' . number_format((float)$paid_amount_info, 2, '.', '') . '</span></td>
                      </tr>
                      <tr>
                          <td colspan="12" align="right">Pay at hotel at the time of check-in &nbsp;&nbsp;</td>
                          <td align="center">' . $currency_code . '<span id="du_amt">' . number_format((float)$due_amount_info, 2, '.', '') . '</span></td>
                      </tr>
                      <tr>
                          <td colspan="13"><p style="color: #ffffff;">* </p></td>
                      </tr>';

        $body .= '</table>
                      
                      <table width="100%" border="0">
                          <tr>
                      <th colspan="2" style="font-size: 21px; color: #ffffff;"><u>*</u></th>
                      </tr>
                          <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;">We are looking forward to hosting you at ' . $hotel_details->hotel_name . '.</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      
                          <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">Regards,<br />
                          Reservation Team<br />
                          ' . $hotel_details->hotel_name . '<br />
                          ' . $hotel_details->hotel_address . '<br />
                          Mob   : ' . $hotel_details->mobile . '<br />
                          Email : ' . $hotel_details->email_id . '</span>
                      </td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Note</u> :</span> Taxes applicable may change subject to govt policy at the time of check in.</td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      <!--tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Terms & Conditions</u> :</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr-->
                      <!--<tr>
                      <td colspan="2"><span style="color:#f00"><li>On Any 100% Cancellation Policy there will be a 3% mandatory deduction from the booking amount due to payment gateway charges.</li></span></td>
                      </tr>-->
                      <tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->terms_and_cond . '</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr>
                      <!--tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Cancellation Policy</u> :</span></td>
                      </tr>
                      <tr>
                      <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
                      </tr-->';
        if (
            $hotel_details->cancel_policy != ""
        ) {
            $body .= '<tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->cancel_policy . '</span></td>
                      </tr>';
        } else {
            $body .= '<tr>
                      <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->hotel_policy . '</span></td>
                      </tr>';
        }
        $body .= '' . $refund_protect_description . '</table>
                      </td>
                      </tr>
                      </table>
                      </div>
                      </body>
                      </html>';
        return  $body;
    }

    public function bookingInvoiceDetails($invoice_id, Request $request)
    {
        
        $getBookingDetails = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->select('hotel_booking.check_in', 'hotel_booking.rooms', 'hotel_booking.check_out', 'invoice_table.room_type', 'invoice_table.extra_details', 'invoice_table.booking_date', 'invoice_table.invoice_id', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.hotel_name', 'invoice_table.hotel_id', 'company_table.currency', 'invoice_table.booking_status','invoice_table.package_id')
            ->where('invoice_table.invoice_id', $invoice_id)
            ->first();

        if (isset($getBookingDetails)) {
            if ($getBookingDetails->booking_status != 1) {
                $res = array('status' => 0, "message" => "Invoice details not found");
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, "message" => "Invoice details not found");
            return response()->json($res);
        }


        $date1 = date_create($getBookingDetails->check_in);
        $date2 = date_create($getBookingDetails->check_out);
        $diff = date_diff($date1, $date2);
        $no_of_nights = $diff->format("%a");
        if ($no_of_nights == 0) {
            $no_of_nights = 1;
        }

        $fetchHotelLogo = ImageTable::where('image_id', $getBookingDetails->logo)->select('image_name')->first();
        if (isset($fetchHotelLogo->image_name)) {
            $booking_details['hotel_logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $fetchHotelLogo->image_name;
        } else {
            $booking_details['hotel_logo'] = "https://s3.ap-south-1.amazonaws.com/images.bookingjini.com/logo/logo.png";
        }

        $booking_details['paid_amount'] = $getBookingDetails->paid_amount;
        $booking_details['booking_id'] = date('dmy') . $getBookingDetails->invoice_id;
        $booking_details['check_in'] =  date('d M,Y', strtotime($getBookingDetails->check_in));
        $booking_details['check_out'] =  date('d M,Y', strtotime($getBookingDetails->check_out));
        $booking_details['night'] = $no_of_nights;
        $booking_details['is_package'] = 0;


        if ($getBookingDetails->currency == 'USD') {
            $booking_details['currency_symbol'] = '0024';
        } elseif ($getBookingDetails->currency == 'EUR') {
            $booking_details['currency_symbol'] = '20AC';
        } elseif ($getBookingDetails->currency == 'GBP') {
            $booking_details['currency_symbol'] = '00A3';
        } elseif ($getBookingDetails->currency == 'BDT') {
            $booking_details['currency_symbol'] = '09F3';
        }elseif ($getBookingDetails->currency == 'THB') {
            $booking_details['currency_symbol']  = '0E3F';
        } else {
            $booking_details['currency_symbol'] = '20B9';
        }


        $room_type_details = HotelBooking::where('invoice_id', $invoice_id)->get();
        $occupancy_details = json_decode($getBookingDetails->extra_details, true);

        
        $package_type_array = [];
        if($getBookingDetails->package_id!=0){
            $booking_details['is_package'] = 1;
            $package_details = Packages::join('kernel.image_table','image_table.image_id','package_table.package_image')
            ->where('package_id', $getBookingDetails->package_id)
            ->select('package_table.*','image_table.image_name')
            ->first();
            $total_package_adult = 0;
            $total_package_child = 0;
            foreach ($occupancy_details as $occupancy_detail) {
                if (isset($occupancy_detail)) {
                    foreach ($occupancy_detail as $extra) {
                        $total_package_adult = $extra[0];
                        $total_package_child = $extra[1];
                    }
                }
            }

            $package_det = [
                'package_name' => $package_details->package_name,
                'no_of_package' => $getBookingDetails->rooms,
                'package_image' => 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $package_details->image_name,
                'adult' => $total_package_adult,
                'child' => $total_package_child,
            ];
            array_push($package_type_array, $package_det);

            $booking_details['package_details'] = $package_type_array;
        }

        $room_type_array = [];
        foreach ($room_type_details as $key => $room_type) {
            $details = DB::table('kernel.room_rate_plan')
                ->join('kernel.room_type_table', 'kernel.room_rate_plan.room_type_id', 'room_type_table.room_type_id')
                ->select('room_type_table.room_type', 'room_type_table.image')
                ->where('room_rate_plan.room_type_id', $room_type->room_type_id)
                ->first();
            if (isset($details->image)) {
                $images = explode(',', $details->image);
                $image_url = ImageTable::where('image_id', $images['0'])->first();
                if ($image_url) {
                    $img = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image_url->image_name;
                } else {
                    $img = '';
                }
            } else {
                $img = '';
            }

            $rate_plan_info = json_decode($getBookingDetails->room_type);
            if(isset($rate_plan_info)){
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
            
            if(isset($occupancy_details)){
                foreach ($occupancy_details as $occupancy_detail) {
                    if (isset($occupancy_detail)) {
                        foreach ($occupancy_detail as $rm_id => $extra) {
                            if ($rm_id == $room_type->room_type_id) {
                                $total_room_type_adult = $extra[0];
                                $total_room_type_child = $extra[1];
                            }
                        }
                    }
                }
            }else{
                $total_room_type_adult = '';
                $total_room_type_child = '';
            }
            

            $room_details = [
                'no_of_rooms' => $room_type->rooms,
                'room_type_name' => $details->room_type,
                'plan_type' => $rate_plan_dtl,
                'room_image' => $img,
                'adult' => $total_room_type_adult == "" ? 0 : $total_room_type_adult,
                'child' => $total_room_type_child == "" ? 0 : $total_room_type_child,
            ];
            array_push($room_type_array, $room_details);
        }
        $booking_details['room_details'] = $room_type_array;


        if ($booking_details) {
            $res = array('status' => 1, "message" => "Invoice data feteched sucesssfully!", 'booking_details' => $booking_details);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Invoice details not found");
            return response()->json($res);
        }
    }

    public function getHotelDetails(string $api_key, int $hotel_id, Request $request)
    {
        //$hotel=new HotelInformation();
        $cond = array('api_key' => $api_key);
        $comp_info = CompanyDetails::select('company_id', 'banner', 'logo', 'home_url','favicon')
            ->where($cond)->first();

        if (!$comp_info['company_id']) {
            $res = array('status' => 1, 'message' => "Invalid hotel or company");
            return response()->json($res);
        }

        $conditions = array('hotel_id' => $hotel_id, 'company_id' => $comp_info['company_id']);
        $info = HotelInformation::select('hotel_name', 'hotel_description', 'company_id', 'hotel_address', 'child_policy', 'cancel_policy', 'terms_and_cond', 'hotel_policy', 'facility', 'airport_name', 'distance_from_air', 'rail_station_name', 'distance_from_rail', 'land_line', 'star_of_property', 'nearest_tourist_places', 'tour_info', 'check_in', 'check_out', 'exterior_image', 'latitude', 'longitude', 'facebook_link', 'twitter_link', 'linked_in_link', 'instagram_link', 'whatsapp_no', 'tripadvisor_link', 'holiday_iq_link', 'prepaid', 'partial_payment', 'partial_pay_amt', 'pay_at_hotel', 'email_id', 'mobile', 'advance_booking_days', 'is_overseas', 'bus_station_name', 'distance_from_bus', 'country_id', 'round_clock_check_in_out', 'is_taxable', 'facebook_pixel', 'google_tag_manager', 'google_analytics', 'other_info')->where($conditions)->first();
        
        $total_rooms = DB::table('kernel.room_type_table')
                     ->where('hotel_id',$hotel_id)
                     ->where('is_trash',0)
                     ->sum('total_rooms');
        $info->total_no_of_rooms = $total_rooms;

        $age_details = DB::table('booking_engine.bookingengine_age_setup')->where('hotel_id',$hotel_id)->first();
        if($age_details){
            $info->infant_age = $age_details->infant;
            $info->child_age = $age_details->children;
        }else{
            $info->infant_age =  '0-5';
            $info->child_age = '5-12';
        }

        if ($info->google_tag_manager != '') {
            $google_tag_manager = explode('@@@', $info->google_tag_manager);
            $tag_id = '';
            if (isset($google_tag_manager[2])) {
                $tag_id = $google_tag_manager[2];
            }
            $info->google_tag_manager = array(
                "header_content" => $google_tag_manager[0],
                "body_content" => $google_tag_manager[1],
                "tag_id" => $tag_id
            );
        } else {
            $info->google_tag_manager = array(
                "header_content" => '',
                "body_content" => '',
                "tag_id" => ''
            );
        }

        $payment_option_count = 0;
        if($info->prepaid == 1){
            $payment_option_count = $payment_option_count + 1;
            $enable_payment = 'prepaid';
        }

        if($info->partial_payment == 1){
            $payment_option_count = $payment_option_count + 1;
            $enable_payment = 'partialpayment';
        }

        if($info->pay_at_hotel == 1){
            $payment_option_count = $payment_option_count + 1;
            $enable_payment = 'payathotel';
        }

        if($payment_option_count == 1){
            $info->enable_payment = $enable_payment;
            $info->multiple_payment_gateway = 0;
        }else{
            $info->enable_payment = '';
            $info->multiple_payment_gateway = 1;
        }



        //get logo and banner
        $info->logo = $this->getImages(array($comp_info->logo));
        $info->logo = $info->logo[0]->image_name;

        if(isset($comp_info->home_url) && $comp_info->home_url !='' && strpos($comp_info->home_url, 'https://') !== 0) {
            $info->home_url = 'https://' . $comp_info->home_url;
        }else{
            $info->home_url = $comp_info->home_url;
        }

        $info->currency_code = $comp_info->currency;

        if(isset($comp_info->favicon)){
            $info->favicon = $this->getImages(array($comp_info->favicon));
            $info->favicon = $info->favicon[0]->image_name;
        }else{
            $info->favicon = $info->logo;
        } 

        $info->favicon = $info->favicon;

        if ($comp_info->currency == 'USD') {
            $info->currency_symbol = '0024';
        } elseif ($comp_info->currency == 'EUR') {
            $info->currency_symbol  = '20AC';
        } elseif ($comp_info->currency == 'GBP') {
            $info->currency_symbol  = '00A3';
        } elseif ($comp_info->currency == 'BDT') {
            $info->currency_symbol  = '09F3';
        } elseif ($comp_info->currency == 'THB') {
            $info->currency_symbol  = '0E3F';
        } else {
            $info->currency_symbol  = '20B9';
        }


        $info->rating = [];
        $info->guest_local_id = 0;
        $info->unmarried_couple = 0;
        $info['is_day_booking_available'] = 0;
        if ($info->other_info != '') {
            $day_booking_package = DB::table('booking_engine.day_package')->where('hotel_id',$hotel_id)->first();
            $other_info_details = json_decode($info->other_info);

                if(isset($other_info_details->package_menu_name)){
                    $info['day_booking_menu'] = $other_info_details->package_menu_name;
                }else{
                    $info['day_booking_menu'] = 'Day Booking';
                }

                if($day_booking_package){
                    $info['is_day_booking_available'] = 1;
                }

            if (isset($other_info_details->rating)) {
                $validRatings = [];
                foreach ($other_info_details->rating as $rating) {
                    if ($rating->rating != 0) {
                        $validRatings[] = $rating;

                         if($rating->name=='Google'){
                            $rating->logo = 'google.png';
                        }
                        if($rating->name=='Tripadvisor'){
                            $rating->logo  = 'trip.png';
                        }
                    } 
                }
                $info->rating = $validRatings;
            }
            if (isset($other_info_details->guest_local_id)) {
                $info->guest_local_id = $other_info_details->guest_local_id;
            }
            if (isset($other_info_details->unmarried_couple)) {
                $info->unmarried_couple = $other_info_details->unmarried_couple;
            }
        }

        $today_date = date('Y-m-d');
        $package_details = DB::table('kernel.package_table')->where('hotel_id', $hotel_id)->where('date_to', '>', $today_date)->where('is_trash', 0)->first();
        if ($package_details) {
            $info->package_available = 1;
        } else {
            $info->package_available = 0;
        }


        $info->check_in_icon = 'check-in';
        $info->check_out_icon = 'check-out';
        // dd($info->mobile);

        $info->payment_icons = [
            'payment/visa-pngrepo-com.png',
            'payment/pngwing.png',
            'payment/google-pay-pngrepo-com.png',
            'payment/maestro-old-3-pngrepo-com.png',
        ];


        if (isset($info->facility)) {

            $info->facility = explode(',', $info->facility); //Converting string to array
            $keys = array_slice(array_values($info->facility), 0, 5);
            $info->facility = $this->getHotelAmen($keys); //Getting actual amenity names
        }
        if (isset($info->exterior_image)) {
            $info->exterior_image = explode(',', $info->exterior_image); //Converting string to array
            $info->exterior_image = $this->getImages($info->exterior_image); //Getting actual amenity names
        }
        $info->facebook_pixel = json_decode($info->facebook_pixel);
        $info['ota_hotel_code'] = $this->getBookingDotComPropertyID($hotel_id); //Get booking.com hotel code
        $get_country_code = $country_info = Country::select('country_dial_code')->where('country_id', $info->country_id)->first();
        $get_mobile_number = explode(',', $info->mobile);
        foreach ($get_mobile_number as $key => $value) {
            $get_mobile_number[$key] = $get_country_code->country_dial_code . " " . $value;
        }
        $info->mobile = $get_mobile_number;
        $info->email_id = explode(',', $info->email_id);
        if (!empty($info->land_line)) {
            $info->land_line = explode(',', $info->land_line);
        } else {
            $info->land_line = '';
        }


        if ($info->round_clock_check_in_out == 1) {
            $info->check_in = '24*7';
            $info->check_out = '24*7';
        }

        $info->check_in = date('H:i',strtotime($info->check_in));
        $info->check_out = date('H:i',strtotime($info->check_out));

       

        $pg_details = DB::table('paymentgateway_details')
            ->select('provider_name')
            ->where('hotel_id', '=', $hotel_id)
            ->where('is_active', '=', 1)
            ->first();
        // if (!$pg_details) {
        //     $pg_details = PaymentGetway::select('provider_name')
        //         ->where('company_id', '=', $info->company_id)
        //         ->first();
        // }
        if (!$pg_details) {
            $payment_provider_name = 'easebuzz';
        } else {
            $payment_provider_name = $pg_details->provider_name;
        }

        if ($payment_provider_name) {
            $onpage_url_details = DB::table('payment_gateways')->where('pg_name', $payment_provider_name)->first();
        }

        $info->payment_provider = $payment_provider_name;
        $info->onpage_url = isset($onpage_url_details->onpage_url) ? $onpage_url_details->onpage_url : '';

     


        $info->note_text = 'Note';
        if ($hotel_id == 2600) {
            $info->note_text = 'Account Reference';
        }

        $info->is_note_mandatory = 0;
        if ($hotel_id == 2600) {
            $info->is_note_mandatory = 1;
        }

        // if ($hotel_id == 6810 || $hotel_id == 2319) {
        //     $info->is_whatsapp_active = 1;
        //     $info->whatsapp_url = 'https://api.whatsapp.com/send/?phone=917387913669&text&type=phone_number&app_absent=0';
        // }else{
        //     $info->is_whatsapp_active = 0;
        //     $info->whatsapp_url = '';
        // }

        $info->is_banner_active = 1 ; // added manually


        if ($info) {
            $res = array('status' => 1, 'message' => "Hotel description successfully ", 'data' => $info);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "Invalid hotel or company");
            return response()->json($res);
        }
    }

    public function getImages($imgs)
    {
        if (empty($imgs[0])) {
            unset($imgs[0]);
        }
        $imgs = array_values($imgs);
        $imp_images = implode(',', $imgs);
        $images = ImageTable::whereIn('image_id', $imgs)
            ->select('image_name')
            ->orderByRaw("FIELD (image_id, ' . $imp_images . ') ASC")
            //   ->orderByRaw('FIELD (image_id, ' . implode(', ', $imgs) . ') ASC')
            ->get();
        if (sizeof($images) > 0) {
            return $images;
        } else {
            $images = ImageTable::where('image_id', 3)
                ->select('image_name')
                ->get();
            return $images;
        }
    }

    public function getHotelAmen($amen)
    {
        $amenities = HotelAmenities::whereIn('hotel_amenities_id', $amen)
        ->select([
            'hotel_amenities_name',
            DB::raw('COALESCE(NULLIF(TRIM(bootstrap_class), ""), "jini-star") as icons')
        ])
        ->get();

        if ($amenities) {
            return $amenities;
        } else {
            return array();
        }
    }

    public function getBookingDotComPropertyID($hotel_id)
    {
        $ota_hotel_code_obj = CmOtaDetails::select('ota_hotel_code')
            ->where('hotel_id', $hotel_id)
            ->where('ota_name', 'Booking.com')
            ->first();
        if ($ota_hotel_code_obj) {
            return $ota_hotel_code_obj['ota_hotel_code'];
        } else {
            return 0;
        }
    }

    public function downloadInvoiceDetails($invoice_id)
    {

        $invoice = $this->successInvoice($invoice_id);
        $invoice = $invoice[0];
        if (!$invoice) {
            $res = array('status' => 0, "message" => "Invoice details not found");
            return response()->json($res);
        }
        $booking_id = date("dmy", strtotime($invoice->booking_date)) . str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
        $body = $invoice->invoice;
        $body = str_replace("#####", $booking_id, $body);
        if ($body) {
            $html_voucher = array('html' => $body);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://tools.bookingjini.com/pdfmonkey',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $html_voucher,
            ));
            $response = curl_exec($curl);
            curl_close($curl);

            $res = array('status' => 1, "message" => "Invoice feteched sucesssfully!", 'data' => $response);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Invoice details not found");
            return response()->json($res);
        }
    }

    public function successInvoice($id)
    {
        $query      = "Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice,a.ids_re_id, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond,a.agent_code,a.booking_status,a.modify_status from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$id";
        $result    = DB::select($query);
        return $result;
    }

    public function deleteBookings(Request $request)
    {
        $invoice_id = $request->invoice_id;
        $booking = HotelBooking::where('invoice_id', $invoice_id)
                ->get();

         if ($booking) {
            foreach ($booking as $bookings) {
                DB::table('booking_engine.hotel_booking')
                  ->where('invoice_id', $bookings->invoice_id)
                  ->update(['booking_status' => 5]);
            }


          DB::table('booking_engine.invoice_table')
            ->where('invoice_id', $invoice_id)
            ->update(['booking_status' => 5]);

         return response()->json(['status' => 1 , 'message' => 'Booking deleted successfully']);
        } else {
        return response()->json(['status' => 0, 'message' => 'Booking not found']);
      }
    }
 
}
