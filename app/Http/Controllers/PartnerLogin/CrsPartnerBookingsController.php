<?php

namespace App\Http\Controllers\PartnerLogin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use App\PartnerRateplanDatewiseSetup;
use DB;
use App\Invoice;
use App\CrsBooking;
use App\Country;
use App\State;
use App\City;
use App\HotelInformation;
use App\MasterRoomType;
use App\MasterRatePlan;
use App\CurrencyDetails;
use App\HotelBooking;
use App\DayWisePrice;
use App\BeBookingDetailsTable;
use App\CompanyDetails;
use App\User;
use App\PartnerDetails;
use App\ImageTable;
use Hash;
use App\Http\Controllers\Extranetv4\InventoryService;
use App\Http\Controllers\Extranetv4\UpdateInventoryService;
use App\Http\Controllers\Extranetv4\UpdateInventoryServiceTest;
use App\Http\Controllers\Extranetv4\IpAddressService;
use App\Http\Controllers\Extranetv4\PmsController;
use App\Http\Controllers\Extranetv4\InvRateBookingDisplayController;
use App\Http\Controllers\Extranetv4\BeConfirmBookingInvUpdateRedirectingController;
use App\Http\Controllers\CurrencyController;






class CrsPartnerBookingsController extends Controller
 {

    protected $invService, $ipService, $idsService;
    protected $updateInventoryService, $updateInventoryServiceTest;
    protected $otaInvRateService;
    // protected $curency;
    protected $beConfBookingInvUpdate;
    protected $curency;

    protected $gst_arr = array();
    public function __construct(InventoryService $invService, UpdateInventoryService $updateInventoryService, IpAddressService $ipService, PmsController $idsService, InvRateBookingDisplayController $otaInvRateService, UpdateInventoryServiceTest $updateInventoryServiceTest, BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate, CurrencyController $curency)
    {
        $this->invService = $invService;
        $this->updateInventoryService = $updateInventoryService;
        $this->updateInventoryServiceTest = $updateInventoryServiceTest;
        // $this->curency = $curency;
        $this->ipService = $ipService;
        $this->idsService = $idsService;
        $this->otaInvRateService = $otaInvRateService;
        $this->beConfBookingInvUpdate = $beConfBookingInvUpdate;
        $this->curency = $curency;
    }

    public function cityList(Request $request)
    {

        $data = $request->all();

        $mobile = $data['mobile'];
        $user_id = $data['user_id'];
        $partnerDetails = PartnerDetails::where('contact_no', $mobile)
            // ->where('user_id', $user_id)
            ->select('hotel_id')
            ->groupBy('hotel_id')
            ->get();

        $hotel_ids = [];

        if ($partnerDetails) {
            foreach ($partnerDetails as $partnerDetail) {
                if (!in_array($partnerDetail->hotel_id, $hotel_ids)) {
                    $hotel_ids[] = $partnerDetail->hotel_id;
                }
            }
        }
        $hotel_details = HotelInformation::whereIn('hotel_id', $hotel_ids)->select('city_id')->groupBy('city_id')->get();
        // dd($hotel_details);
        $city_details_array = [];
        if ($hotel_details) {
            foreach ($hotel_details as $hotel_detail) {
                $city_id = $hotel_detail->city_id;
                $city_name = City::where('city_id', $city_id)->value('city_name');

                $city_details_array[] = array('city_id' => $city_id, 'city_name' => $city_name);
            }
        }

        if (sizeof($city_details_array) > 0) {
            $final_data = ['status' => 1, 'data' => $city_details_array];
        } else {
            $final_data = ['status' => 0, 'msg' => 'City detaiils not found'];
        }
        return response()->json($final_data);
    }

    public function hotelList(Request $request)
    {
        $data = $request->all();
        $mobile = $data['mobile'];
        $city_id = $data['city_id'];

        $partnerDetails = PartnerDetails::where('contact_no', $mobile)
            ->select('hotel_id')
            ->groupBy('hotel_id')
            ->get();

        $hotel_ids = [];
        if ($partnerDetails) {
            foreach ($partnerDetails as $partnerDetail) {
                if (!in_array($partnerDetail->hotel_id, $hotel_ids)) {
                    $hotel_ids[] = $partnerDetail->hotel_id;
                }
            }
        }

        if ($city_id != 0) {
            $hotel_details = HotelInformation::whereIn('hotel_id', $hotel_ids)
                ->where('city_id', $city_id)
                ->get();
        } else {
            $hotel_details = HotelInformation::whereIn('hotel_id', $hotel_ids)->get();
        }

        $hotel_details_array = [];

        foreach ($hotel_details as $hotel_detail) {
            $city_id = $hotel_detail->city_id;
            $city_name = City::where('city_id', $city_id)->value('city_name');

            $hotel_details_array[] = [
                'hotel_id' => $hotel_detail->hotel_id,
                'hotel_name' => $hotel_detail->hotel_name,
                'city_id' => $city_id,
                'city_name' => $city_name,
            ];
        }

        if (sizeof($hotel_details_array) > 0) {
            $final_data = ['status' => 1, 'data' => $hotel_details_array];
        } else {
            $final_data = ['status' => 0, 'msg' => 'Hotel details not found'];
        }

        return response()->json($final_data);
    }

    public function getPartnerAvailableRooms(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $partner_id = $request->partner_id;

        $from_date = date('Y-m-d', strtotime($request->from_date));
        $to_date = date('Y-m-d', strtotime('+10 days', strtotime($request->from_date)));

        $room_types = DB::table('kernel.room_type_table')
        ->select('room_type_table.room_type', 'room_type_table.room_type_id')
        ->where('room_type_table.hotel_id', $hotel_id)
            ->get();

        $total_no_rooms = DB::table('booking_engine.partner_rateplan_setup')
        ->where('hotel_id', $hotel_id)
        ->where('partner_id', $partner_id)
        ->sum('total_allocated_room');

        $availability_array = [];

        foreach ($room_types as $room_type) {
            $room_type_id = $room_type->room_type_id;
            $room_type_name = $room_type->room_type;

            $room_rate_plans = DB::table('kernel.rate_plan_table')
            ->join('kernel.room_rate_plan', 'kernel.rate_plan_table.rate_plan_id', 'kernel.room_rate_plan.rate_plan_id')
            ->select('rate_plan_table.plan_type', 'rate_plan_table.rate_plan_id')
            ->where('room_rate_plan.room_type_id', $room_type_id)
            ->get();

            foreach ($room_rate_plans as $room_rate_plan) {
                $room_rate_plan_type = $room_rate_plan->plan_type;
                $room_rate_plan_id = $room_rate_plan->rate_plan_id;
                for ($date = $from_date; $date <= $to_date; $date = date('Y-m-d', strtotime('+1 day', strtotime($date)))) {
                    $available_room = DB::table('booking_engine.partner_rateplan_datewise_setup')
                    ->select(
                        'partner_rateplan_datewise_setup.date',
                        'partner_rateplan_datewise_setup.no_of_rooms',
                        'partner_rateplan_datewise_setup.bar_price',
                        'partner_rateplan_datewise_setup.extra_adult',
                        'partner_rateplan_datewise_setup.extra_child',
                        'partner_rateplan_datewise_setup.multiple_occupancy'
                    )
                        ->where('partner_rateplan_datewise_setup.hotel_id', $hotel_id)
                        ->where('partner_rateplan_datewise_setup.partner_id', $partner_id)
                        ->where('partner_rateplan_datewise_setup.room_type_id', $room_type_id)
                        ->where('partner_rateplan_datewise_setup.rate_plan_id', $room_rate_plan_id)
                        ->where('partner_rateplan_datewise_setup.date', $date)
                        ->first();
                    if ($available_room) {
                        $rates[$room_rate_plan_type][] = [
                            'date' => $date,
                            'no_of_rooms' => $available_room->no_of_rooms,
                            'bar_price' => $available_room->bar_price,
                            'extra_adult' => $available_room->extra_adult,
                            'extra_child' => $available_room->extra_child,
                            'multiple_occupancy' => json_decode($available_room->multiple_occupancy),
                        ];
                    } else {
                        $rates[$room_rate_plan_type][] = [
                            'date' => $date,
                            'no_of_rooms' => "NA",
                            'bar_price' => "NA",
                            'extra_adult' => "NA",
                            'extra_child' => "NA",
                            'multiple_occupancy' => "NA",
                        ];
                    }
                }
            }
            foreach($rates as $key=> $rate){
                $availability_array[$room_type_name][] = array($key=>$rate);
            }
        }
        if (!empty($availability_array)) {


            $data[] = $availability_array;

            return response()->json([
                'status' => 1,
                'message' => 'Rooms fetched successfully.',
                'data' => $data,
                'total_rooms' => $total_no_rooms,
            ]);
        } else {
            return response()->json(['status' => 0, 'message' => 'No rooms available.']);
        }
    }

    public function CheckPartnerAvalableRooms(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $no_of_rooms = $request->no_of_rooms;

        $check_in = date_create($request->check_in);
        $check_out = date_create($request->check_out);

        $diff = date_diff($check_in, $check_out);
        $diff = (int)$diff->format("%a");
        if ($diff == 0) {
            $diff = 1;
        }
        $no_of_nights = $diff;

        $grouped_data = [];
        $no_data_found = false;

        for ($date = $check_in; $date <= $check_out; $date->modify('+1 day')) {
            $date_str = $date->format('Y-m-d');

            $availability = DB::table('booking_engine.partner_rateplan_datewise_setup')
                ->join('kernel.room_type_table', 'booking_engine.partner_rateplan_datewise_setup.room_type_id', '=', 'kernel.room_type_table.room_type_id')
                ->join('kernel.rate_plan_table', 'booking_engine.partner_rateplan_datewise_setup.rate_plan_id', '=', 'kernel.rate_plan_table.rate_plan_id')
                ->select(
                    'partner_rateplan_datewise_setup.date',
                    'partner_rateplan_datewise_setup.no_of_rooms',
                    'partner_rateplan_datewise_setup.bar_price',
                    'partner_rateplan_datewise_setup.extra_adult',
                    'partner_rateplan_datewise_setup.extra_child',
                    'partner_rateplan_datewise_setup.multiple_occupancy',
                    'partner_rateplan_datewise_setup.room_type_id',
                    'partner_rateplan_datewise_setup.rate_plan_id',
                    'rate_plan_table.plan_type',
                    'room_type_table.room_type'
                )
                ->where('booking_engine.partner_rateplan_datewise_setup.hotel_id', $hotel_id)
                ->where('booking_engine.partner_rateplan_datewise_setup.date', $date_str)
                ->get();

            if ($availability->isEmpty()) {
                $no_data_found = true;
                break;
            }

            foreach ($availability as $room) {

                $room_type_id = $room->room_type_id;
                $room_rate_plan_id = $room->rate_plan_id;

                if ($no_of_rooms > $room->no_of_rooms) {
                    return response()->json([
                        'status' => 1,
                        'message' => 'No Rooms Available for this date range with the requested number of rooms.',
                    ]);
                }

                if (!isset($grouped_data[$room_type_id])) {
                    $grouped_data[$room_type_id] = [
                        'room_type' => $room->room_type,
                        'room_type_id' => $room->room_type_id,
                        // 'extra_child' => $room->extra_child,
                        'rate_plans' => []
                    ];
                }

                if (!isset($grouped_data[$room_type_id]['rate_plans'][$room_rate_plan_id])) {
                    $grouped_data[$room_type_id]['rate_plans'][$room_rate_plan_id] = [
                        'rate_plan_id' => $room->rate_plan_id,
                        'plan_type' => $room->plan_type,
                        // 'plan_name' => $room->plan_name,
                        'bar_price' => $room->bar_price,
                        'rates' => []
                    ];
                }

                $grouped_data[$room_type_id]['rate_plans'][$room_rate_plan_id]['rates'][] = [
                    'bar_price' => $room->bar_price,
                    'multiple_occupancy' => json_decode($room->multiple_occupancy),
                    'extra_adult_price' => $room->extra_adult,
                    'extra_child_price' => $room->extra_child,
                    'rate_plan_id' => $room->rate_plan_id,
                    'room_type_id' => $room->room_type_id,
                    'date' => $date_str,
                    'no_of_rooms' => $room->no_of_rooms,

                ];
            }
        }

        if ($no_data_found) {
            return response()->json([
                'status' => 1,
                'message' => 'No Rooms Available for this date range.',
            ]);
        }

        if (!empty($grouped_data)) {
            return response()->json([
                'status' => 1,
                'message' => 'Rooms fetched successfully.',
                'data' => array_values($grouped_data)
            ]);
        } else {
            return response()->json(['status' => 0, 'message' => 'No rooms available.']);
        }
    }

    public function bookings(string $api_key, Request $request)
    {
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

        if (strlen($user_details['mobile']) != '10') {
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

        //User registration
        $user_data['first_name'] = $user_details['first_name'];
        $user_data['last_name'] = $user_details['last_name'];
        $user_data['email_id'] = $user_details['email_id'];
        $user_data['mobile'] = $user_details['mobile'];
        $country = Country::where('country_id', $user_details['country'])->first();
        $state = State::where('state_id', $user_details['state'])->first();
        $city = City::where('city_id', $user_details['city'])->first();
        $user_data['password'] = uniqid();
        $user_data['password'] = Hash::make($user_data['password']);
        $user_data['country'] = $country->country_name;
        $user_data['state'] = $state->state_name;
        $user_data['city'] = $city->city_name;
        $user_data['zip_code'] = $user_details['zip_code'];
        $user_data['company_name'] = $company_name->company_full_name;
        $user_data['GSTIN'] = $user_details['GST_IN'];
        $user_data['address'] = $user_details['address'];
        //  $res = User::updateOrCreate(
        //      [
        //          'mobile' => $user_details['mobile'],
        //          'company_id' => $hotel_info->company_id
        //      ],
        //      $user_data
        //  );
        //  $user_id = $res->user_id;

        //insert in new user table
        $new_user_data['first_name'] = $user_details['first_name'];
        $new_user_data['last_name'] = $user_details['last_name'];
        $new_user_data['email_id'] = $user_details['email_id'];
        $new_user_data['mobile'] = $user_details['mobile'];
        if (isset($user_details['mobile'])) {
            $user_name = $user_details['mobile'];
        } else {
            $user_name = $user_details['email_id'];
        }
        $new_user_data['password'] = uniqid();
        $new_user_data['password'] = Hash::make($user_data['password']);
        $new_user_data['country'] = $user_details['country'];
        $new_user_data['state'] = $user_details['state'];
        $new_user_data['city'] = $user_details['city'];
        $new_user_data['zip_code'] = $user_details['zip_code'];
        $new_user_data['company_name'] = $company_name->company_full_name;
        $new_user_data['GSTIN'] = $user_details['GST_IN'];
        $new_user_data['locality'] = $user_details['address'];
        $new_user_data['user_name'] = $user_name;
        $new_user_data['bookings'] = '';
        // $user_res = UserNew::updateOrCreate(['mobile' => $user_details['mobile']], $new_user_data);
        // $user_id_new = $user_res->user_id;
        // dd($user_id_new);
        // exit;

        //Store invoice details
        $inv_data = array();
        $inv_data['hotel_id']   = $hotel_id;
        $hotel = $this->getHotelInfo($hotel_id);
        $inv_data['hotel_name'] = $hotel->hotel_name;
        $inv_data['room_type']  = json_encode($this->prepareRoomTypes($room_details, $diff));
        $inv_data['ref_no'] = rand() . strtotime("now");
        $inv_data['check_in_out'] = "[" . $check_in . '-' . $check_out . "]";
        $inv_data['booking_date'] = date('Y-m-d H:i:s');
        $inv_data['booking_status'] = 2;
        $inv_data['user_id'] = 1;
        $inv_data['user_id_new'] = 1;

        $payload = [
            "hotel_id" => $hotel_id,
            "user_id" => 0,
        ];

        $payload = json_encode($payload);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://subscription.bookingjini.com/api/my-subscription',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X_Auth_Subscription-API-KEY: Vz9P1vfwj6P6hiTCc9ddC5bNieqA5ScT'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $be_sources = json_decode($response);

        $inv_data['is_cm'] = 0;
        if ($be_sources && !empty($be_sources->apps)) {
            foreach ($be_sources->apps as $be_source) {
                if ($be_source->app_code == 'JINI HIVE') {
                    $inv_data['is_cm'] = 1;
                }
            }
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
        if (sizeof($room_details) > 0) {
            $invoice_details_array = [];
            $per_room_occupancy = [];
            foreach ($room_details as $details) {

                $booking = [];

                $room_type_id = $details['room_type_id'];
                $rate_plan_id = $details['rate_plan_id'];
                $total_room_type_price = $details['total_room_type_price'];
                $total_room_type_discount_amount = $details['total_room_type_discount_amount'];

                $rooms = $details['rooms'];
                $taxes = $details['tax'];

                $rooms_details = $details['rooms'];
                foreach ($rooms_details as $rooms_detail) {
                    array_push($extra_details, array($details['room_type_id'] => array($rooms_detail['adult'], $rooms_detail['child'])));
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

                $check_in_data = $check_in;
                for ($i = 0; $i < $diff; $i++) {
                    $d = $check_in_data;
                    $discounted_price = 0;

                    $room_occu = [];
                    foreach ($rooms as $key => $acc) {
                        $adult = $acc['adult'];
                        $child = $acc['child'];
                        $extra_adult = 0;
                        $bookings['extra_child'] = 0;
                        $room_rate_per_night = $acc['room_rate_per_night'];
                        $discount_amount_per_night = $acc['discount_amount_per_night'];

                        //Gst calculation
                        if ($hotel_info->is_taxable == 1) {
                            if ($room_rate_per_night >= 0 && $room_rate_per_night < 7500) {
                                $gst_percentage = 12;
                            } else if ($room_rate_per_night >= 7500) {
                                $gst_percentage = 18;
                            }

                            $room_price_excluding_gst = $room_rate_per_night - $discount_amount_per_night;

                            $gst_price = $room_price_excluding_gst * $gst_percentage / 100 * $diff;
                        } else {
                            $room_price_excluding_gst = $room_rate_per_night - $discount_amount_per_night;
                            $gst_price = 0;
                        }

                        $room_price_excluding_tax += $room_price_excluding_gst;
                        $room_price_including_gst += $room_price_excluding_gst + $gst_price;
                        $total_gst_price += $gst_price;
                        $total_discount_price = $discount_amount_per_night;

                        $rwo['selected_adult'] = $adult;
                        $rwo['selected_child'] = $child;

                        array_push($room_occu, $rwo);


                        //Store the details in day wise price setup
                        $invoice_details['hotel_id'] = $hotel_id;
                        $invoice_details['user_id'] = 1;   //$user_id;(on production)
                        $invoice_details['room_type_id'] = $details['room_type_id'];
                        $invoice_details['rate_plan_id'] = $details['rate_plan_id'];
                        $invoice_details['date'] = $d;
                        $invoice_details['room_rate'] = $room_rate_per_night;
                        $invoice_details['extra_adult'] = 0;
                        $invoice_details['extra_child'] = 0;
                        $invoice_details['extra_adult_price'] = 0;
                        $invoice_details['extra_child_price'] = 0;
                        $invoice_details['discount_price'] = (float)$discount_amount_per_night;
                        $invoice_details['price_after_discount'] = (float)$room_price_excluding_gst;
                        $invoice_details['rooms'] = $key + 1;
                        $invoice_details['gst_price'] = (float)$gst_price;
                        $invoice_details['total_price'] = (float)$room_price_including_gst;
                        array_push($invoice_details_array, $invoice_details);
                    }

                    //Prepared cart details
                    $bookings['selected_adult'] = $adult;
                    $bookings['selected_child'] = $child;
                    // $bookings['extra_adult'] = 0;
                    // $bookings['extra_child'] = 0;
                    $bookings['rate_plan_id'] = $details['rate_plan_id'];
                    $bookings['extra_adult_price'] = 0;
                    $bookings['extra_child_price'] = 0;
                    $bookings['bar_price'] = $room_rate_per_night;
                    array_push($booking, $bookings);

                    $check_in_data = date('Y-m-d', strtotime($d . ' +1 day'));
                }
                array_push($per_room_occupancy, $room_occu);

                //store the data in hotel booking table
                $hotel_booking_data['room_type_id'] = $details['room_type_id'];
                $hotel_booking_data['rate_plan_id'] = $details['rate_plan_id'];
                $hotel_booking_data['rooms'] = sizeof($rooms);
                $hotel_booking_data['check_in'] = $check_in;
                $hotel_booking_data['check_out'] = $check_out;
                $hotel_booking_data['booking_status'] = 2; //Intially Un Paid
                $hotel_booking_data['user_id'] = 1; //$user_id (on production)
                $hotel_booking_data['booking_date'] = date('Y-m-d');
                $hotel_booking_data['hotel_id'] = $hotel_id;
                array_push($hotel_booking_details, $hotel_booking_data);
                array_push($cart, array('rooms' => $booking, 'room_type_id' => $details['room_type_id'], 'room_type' => $room_type_details->room_type));
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

            if ($currency != 'INR') {
                $currency_value = CurrencyDetails::where('name', $currency)->first();
                $amount_to_pay = (1 / $currency_value->currency_value) * $amount_to_pay;
            }
            $total_amount = $room_price_including_gst;
            // dd($total_amount);

            if ($payment_mode == 2) {
                $partial_pay_per = $hotel_info->partial_pay_amt;
                $room_price_including_gst = $room_price_including_gst * $partial_pay_per / 100;
            } elseif ($payment_mode == 3) {
                $room_price_including_gst = 0;
            } else {
                $room_price_including_gst = $room_price_including_gst;
            }

            $room_price_including_gst = number_format((float)$room_price_including_gst, 2, '.', '');

            if (round($amount_to_pay) != round($room_price_including_gst)) {
                $res = array('status' => 0, 'message' => "Booking failed due to data tampering", 'data' => $room_price_including_gst);
                return response()->json($res);
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

        $rp_member_id = 0;
        $refund_protect_price_info = 0;
        $source_info = '';
        $result = $invoice->fill($inv_data)->save();
        // dd($result);
        // exit;
        if ($result) {
            $invoice_id = $invoice->invoice_id;

            foreach ($hotel_booking_details as &$hotel_booking_detail) {
                $hotel_booking_detail['invoice_id'] = $invoice_id;
            }
            // }
            HotelBooking::insert($hotel_booking_details);

            foreach ($invoice_details_array as &$invoice_detail) {
                $invoice_detail['invoice_id'] = $invoice_id;
            }
            DayWisePrice::insert($invoice_details_array);
           
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
                    $extra_adult_dtl[] = $day_wise_booking->extra_adult;
                    $extra_child_dtl[] = $day_wise_booking->extra_child;
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
                $be_booking_det['rooms'] = count($day_wise_bookings);
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

            $inv_data['invoice'] = $this->createInvoice($hotel_id, $invoice_id, $cart, $total_paidservices_amount, $check_in, $check_out, $inv_data['user_id'], $refund_protect_price_info, $rp_member_id, $source_info, $inv_data['total_amount'], $inv_data['paid_amount'], $per_room_occupancy);

            $update_vouchere = invoice::where('invoice_id', $invoice_id)->update(['invoice' => $inv_data['invoice']]);

            $user_data = $this->getUserDetails($inv_data['user_id']);
            $b_invoice_id = base64_encode($invoice_id);
            $invoice_hashData = $invoice_id . '|' . $room_price_including_gst . '|' . $room_price_including_gst . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;

            $invoice_secureHash = hash('sha512', $invoice_hashData);
            $res = array("status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $invoice_id, 'invoice_secureHash' => $invoice_secureHash);
            return response()->json($res);
        } else {
            $res = array('status' => -1, "message" => 'Booking Failed');
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }

    public function createInvoice($hotel_id, $invoice_id, $cart, $paid_services, $check_in, $check_out, $user_id, $refund_protect_price,  $rp_member_id, $source, $total_amount, $paid_amount_info, $per_room_occupancy)
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
                    $extra_adult = 0;
                    $extra_child = 0;
                    $base_adult = 0;
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
                            '<td  align="center">' . $currency_code . round($day_wise_room['room_rate']) . '</td>
                                  <td  align="center">' . $currency_code . round($day_wise_room['discount_price']) . '</td>
                                  <td  align="center">' . $currency_code . round($day_wise_room['price_after_discount']) . '</td>
                                  <td  align="center">' . $currency_code . round($extra_adult_price) . '</td>
                                  <td  align="center">' . $currency_code . round($extra_child_price) . '</td>
                                  <td  align="center">' . $currency_code . round($total_amount) . '</td>
                                  <td  align="center">' . $currency_code . round($day_wise_room['gst_price']) . '</td></tr>';;
                    }
                }
            }

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

        $total_paid_amount = $grand_total_amount_after_discount + $total_tax + $paid_services;
        if ($total_discount_price > 0) {
            $display_discount = $total_discount_price;
        }

        $gst_tax_details = "";
        if ($baseCurrency == 'INR') {
            $gst_tax_details = '<tr>
                  <td colspan="12" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
                  <td align="center">' . $currency_code . $total_tax . '</td>
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
        $total_amt = $grand_total_amount + $paid_services;
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
                      <td align="center">' . $currency_code . $grand_total_amount . '</td>
                      </tr>
                      <tr>
                      <td colspan="12" align="right">Total Discount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . $discount . '</td>
                      </tr>
                      <tr>
                      <td colspan="12" align="right">Total Room Price after discount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . $grand_total_amount_after_discount . '</td>
                      </tr>
                      
                    
                      ' . $gst_tax_details . '
                      ' . $other_tax_details . '
                      ' . $refund_protect_info . '
                      <tr>
                      <td colspan="12" align="right">Paid Services&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . $paid_services . '</td>
                      </tr>
                      <tr>
                      <td colspan="12" align="right">Total Amount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . $total_paid_amount . '</td>
                      </tr>';
        $body .= '<tr>
                      <td colspan="12" align="right">Total Paid Amount&nbsp;&nbsp;</td>
                      <td align="center">' . $currency_code . '<span id="pd_amt">' . $paid_amount_info . '</span></td>
                      </tr>
                      <tr>
                          <td colspan="12" align="right">Pay at hotel&nbsp;&nbsp;</td>
                          <td align="center">' . $currency_code . '<span id="du_amt">' . $due_amount_info . '</span></td>
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

    public function getUserDetails($user_id)
    {
        $user = User::select('*')->where('user_id', $user_id)->first();
        return $user;
    }

    public function prepareRoomTypes($room_details, $diff)
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
            $rooms = $diff;
            array_push($room_types, $rooms . ' ' . $room_type . '(' . $plan_type . ')');
        }
        return $room_types;
    }

    public function getHotelInfo($hotel_id)
    {
        $hotel = HotelInformation::select('*')->where('hotel_id', $hotel_id)->first();
        return $hotel;
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



    public  function partnerListViewBookings(Request $request)
    {
        $failure_message = 'Bookings Fetch Failed';

        $data = $request->all();
        $from_date = isset($data['from_date']) ? date('Y-m-d', strtotime($data['from_date'])) : date('Y-m-d');
        $to_date = isset($data['to_date']) ? date('Y-m-d', strtotime($data['to_date'])) : date('Y-m-d');

        $booking_status = $data['booking_status'];
        //    $booking_id = $data[ 'booking_id' ];
        $partner_id = $data['partner_id'];
        if (isset($data['hotel_id'])) {
            $hotel_id = $data['hotel_id'];
        }

        if (isset($data['date_type'])) {
            $date_type = $data['date_type'];
        }

        $be_ids = [];

        if ($date_type == 1) {
            $check_date_type = 'booking_date';
        } elseif ($date_type == 3) {
            $check_date_type = 'checkout_at';
        } else {
            $check_date_type = 'checkin_at';
        }
        $booking_source = 'CRS';

        $all_booking_data = [];


        if ($date_type == 1) {
            $check_be_date = 'hotel_booking.booking_date';
        } elseif ($date_type == 3) {
            $check_be_date = 'hotel_booking.check_out';
        } else {
            $check_be_date = 'hotel_booking.check_in';
        }

        $be_data = DB::table('booking_engine.invoice_table')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('crs_booking', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->select(
                'user_table.first_name',
                'user_table.last_name',
                'user_table.user_id',
                'invoice_table.booking_date',
                'invoice_table.hotel_id',
                'invoice_table.hotel_name',
                'invoice_table.pay_to_hotel',
                'invoice_table.check_in_out',
                'invoice_table.booking_status',
                'invoice_table.hotel_name',
                'invoice_table.total_amount',
                'invoice_table.paid_amount',
                'hotel_booking.hotel_booking_id',
                'hotel_booking.check_in',
                'hotel_booking.check_out',
                'invoice_table.booking_source',
                'hotel_booking.invoice_id',
                'crs_booking.partner_id',
                'invoice_table.room_type',
                'company_table.logo'
            )
            ->groupBy('hotel_booking.invoice_id')
            ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date));

        $be_data = $be_data->where('invoice_table.booking_source', 'CRS');
        if ($hotel_id) {
            $be_data->where('invoice_table.hotel_id', '=', $hotel_id);
        }
        //    if ( $partner_id ) {
        $be_data = $be_data->where('crs_booking.partner_id', '=', $partner_id);
        //    }
        if ($booking_status == 'confirm') {
            $be_data = $be_data->where('invoice_table.booking_status', '=', 1);
        } elseif ($booking_status == 'cancel') {
            $be_data =  $be_data->where('invoice_table.booking_status', '=', 3);
        } else {
            $be_data =  $be_data->where('invoice_table.booking_status', '!=', 2);
        }

        //    if ( $booking_id ) {
        //        $be_data = $be_data->orWhere( 'invoice_table.invoice_id', 'like', '%' . $booking_id . '%' )
        //        ->orWhere( 'first_name', 'like', '%' . $booking_id . '%' );
        //    }
        $be_data =  $be_data->get();

        if (sizeof($be_data) > 0) {
            foreach ($be_data as $be) {
                if ($be->booking_source == 'CRS') {
                    $check_crs_payment_type = CrsBooking::where('invoice_id', $be->invoice_id)->where('payment_type', 1)->where('booking_status', 1)->first();
                    if ($check_crs_payment_type) {
                        $pay_to_hotel = 'Paid';
                        $payment_status_color = '#2DB321';
                    } else {
                        $pay_to_hotel = 'Pay at hotel';
                        $payment_status_color = '#E52929';
                    }
                }
                $fetch_rooms = json_decode($be->room_type);
                $total_rooms = 0;
                foreach ($fetch_rooms as $rooms) {
                    $no_of_rooms = substr($rooms, 0, 1);
                    $total_rooms += (int) $no_of_rooms;
                }
                $date1 = date_create($be->check_in);
                $date2 = date_create($be->check_out);
                $diff = date_diff($date1, $date2);
                $no_of_nights = $diff->format('%a');
                if ($no_of_nights == 0) {
                    $no_of_nights = 1;
                }

                if ($be->booking_status == 1) {
                    $btn_status = 'Confirmed';
                    $booking_status_color = '#2DB321';
                } elseif ($be->booking_status == 3) {
                    $btn_status = 'Cancelled';
                    $booking_status_color = '#E52929';
                }

                $booking_details['booking_id'] = date('dmy', strtotime($be->booking_date)) . $be->invoice_id;
                $booking_details['hotel_name'] = $be->hotel_name;
                $booking_details['booking_date'] = date('d M Y', strtotime($be->booking_date));
                $booking_details['booker_details'] = strtoupper($be->first_name) . ' ' . strtoupper($be->last_name);
                $booking_details['nights'] = $no_of_nights;
                $booking_details['no_of_rooms'] = $total_rooms;
                $booking_details['checkin_out'] =  date('d M', strtotime($be->check_in)) . ' - ' . date('d M y', strtotime($be->check_out));
                $booking_details['payment_status'] = $pay_to_hotel;
                $booking_details['payment_status_color'] = $payment_status_color;
                $booking_details['booking_status'] = $btn_status;
                $booking_details['booking_status_color'] = $booking_status_color;

                //    $booking_details[ 'hotel_id' ] = $be->hotel_id;
                //    $booking_details[ 'partner_id' ] = $be->partner_id;
                //    $booking_details[ 'channel_name' ] = $be->booking_source;
                //    $booking_details[ 'ota_id' ] = -1;

                array_push($all_booking_data, $booking_details);
            }
        }

        if ($all_booking_data) {
            usort($all_booking_data, array($this, 'cmp'));
            $result = array('status' => 1, 'message' => 'Bookings Fetch Successfully', 'data' => $all_booking_data);
            return response()->json($result);
        } else {
            $result = array('status' => 0, 'message' => $failure_message);
            return response()->json($result);
        }
    }

    // public function partnerBookingReport( Request $request )
    // {
    //     $failure_message = 'Bookings Fetch Failed';
    //     $data = $request->all();

    //     $from_date = isset( $data[ 'from_date' ] ) ? date( 'Y-m-d', strtotime( $data[ 'from_date' ] ) ) : date( 'Y-m-d' );
    //     $to_date = isset( $data[ 'to_date' ] ) ? date( 'Y-m-d', strtotime( $data[ 'to_date' ] ) ) : date( 'Y-m-d' );

    //     $hotel_id = $data[ 'hotel_id' ];
    //     $payment_options = $data[ 'payment_options' ];
    //     $booking_status = $data[ 'booking_status' ];
    //     $agent = $data[ 'partner_id' ];

    //     if ( isset( $data[ 'date_type' ] ) ) {
    //         $date_type = $data[ 'date_type' ];
    //     }

    //     if ( $date_type == 1 ) {
    //         $check_be_date = 'hotel_booking.booking_date';
    //     } elseif ( $date_type == 3 ) {
    //         $check_be_date = 'hotel_booking.check_out';
    //     } else {
    //         $check_be_date = 'hotel_booking.check_in';
    //     }

    //     if ( isset( $data[ 'sales_executive_id' ] ) ) {
    //         $sales_executive = $data[ 'sales_executive_id' ];
    //     } else {
    //         $sales_executive = 0;
    //     }

    //     $be_data = Invoice::join( 'kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id' )
    //     ->join( 'hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id' )
    //     ->join( 'crs_booking', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id' )
    //     ->leftjoin( 'booking_engine.partner_table', 'partner_table.id', '=', 'crs_booking.partner_id' )
    //     ->leftjoin( 'crs_sales_executive', 'crs_sales_executive.id', '=', 'crs_booking.sales_executive_id' )
    //     ->select(
    //         'user_table.first_name',
    //         'user_table.last_name',
    //         'user_table.user_id',
    //         'invoice_table.booking_date',
    //         'invoice_table.hotel_id',
    //         'invoice_table.pay_to_hotel',
    //         'invoice_table.booking_status',
    //         'invoice_table.hotel_name',
    //         'invoice_table.total_amount',
    //         'invoice_table.paid_amount',
    //         'invoice_table.booking_source',
    //         'invoice_table.invoice_id',
    //         'crs_booking.no_of_rooms',
    //         'crs_booking.booking_time',
    //         'crs_booking.payment_type',
    //         'crs_booking.payment_link_status',
    //         'crs_booking.payment_status',
    //         'crs_booking.is_payment_received',
    //         'crs_booking.valid_hour',
    //         'crs_booking.check_in',
    //         'crs_booking.check_out',
    //         'crs_booking.partner_id',
    //         'crs_booking.sales_executive_id',
    //         'crs_sales_executive.name as sales_executive_name',
    //         'partner_table.partner_name',
    //         'crs_booking.expiry_time'
    //     )->where( 'invoice_table.hotel_id', '=', $hotel_id )
    //     ->where( 'invoice_table.booking_source', '=', 'CRS' )
    //     ->whereIn( 'invoice_table.booking_status', [ 1, 2, 3 ] )
    //     ->whereBetween( DB::raw( 'date(' . $check_be_date . ')' ), array( $from_date, $to_date ) );

    //     // 0 = all, 1 = Email with payment link, 2 = Email no payment link, 3 = No email no payment
    //     if ( $payment_options != 0 ) {
    //         $be_data =  $be_data->where( 'crs_booking.payment_type', '=', $payment_options );
    //     }
    //     // 0 = all, 1 = Confirm, 3 = Cancelled
    //     if ( $booking_status != 0 ) {
    //         $be_data = $be_data->where( 'invoice_table.booking_status', '=', $booking_status );
    //     }

    //     if ( $agent != 0 ) {
    //         $be_data = $be_data->where( 'crs_booking.partner_id', '=', $agent );
    //     }

    //     if ( $sales_executive != 0 ) {
    //         $be_data = $be_data->where( 'crs_booking.sales_executive_id', '=', $sales_executive );
    //     }

    //     $be_data =  $be_data->get();

    //     $all_booking_data = [];
    //     if ( sizeof( $be_data ) > 0 ) {
    //         foreach ( $be_data as $be ) {

    //             $date1 = date_create( $be->check_in );
    //             $date2 = date_create( $be->check_out );
    //             $diff = date_diff( $date1, $date2 );
    //             $no_of_nights = $diff->format( '%a' );
    //             $no_of_nights = $no_of_nights == 0 ? 1 : $no_of_nights;

    //             if ( $be->booking_status == 3 ) {
    //                 $btn_status = 'Cancelled';
    //                 $booking_status_color = '#E52929';
    //             } else {
    //                 $btn_status = 'Confirmed';
    //                 $booking_status_color = '#72D543';

    //             }

    //             $payment_options = $be->payment_type == 1 ? 'Email with Payment Link' :
    //             ( $be->payment_type == 2 ? 'Email (no payment link)' : 'No Email No Payment Link' );

    //             $booking_details[ 'booking_id' ] = date( 'dmy', strtotime( $be->booking_time ) ) . $be->invoice_id;
    //             $booking_details[ 'guest_details' ] = strtoupper( $be->first_name ) . ' ' . strtoupper( $be->last_name );
    //             $booking_details[ 'booking_time' ] = date( 'd M Y h:i a', strtotime( $be->booking_time ) );

    //             $payment_received = DB::table( 'crs_payment_receive' )->where( 'invoice_id', $be->invoice_id )->sum( 'receive_amount' );

    //             if ( $payment_received == 0 ) {
    //                 $received_amount = 0;
    //             } else {
    //                 $received_amount = $payment_received;
    //             }

    //             if ( $be->payment_type == 1 ) {

    //                 $expiry_time = strtotime( $be->expiry_time );
    //                 if ( $expiry_time == '' ) {
    //                     $expiry_time = strtotime( $be->check_out );

    //                 }
    //                 $current_time = strtotime( date( 'Y-m-d H:i:s' ) );
    //                 $booking_time = strtotime( $be->booking_time );
    //                 $expeted_payment = $expiry_time - $booking_time;

    //                 $expeted_payment = $expeted_payment / 60;
    //                 $expeted_payment_time = $expeted_payment . ' ' . 'Minutes';
    //                 if ( $expeted_payment_time >= 60 ) {
    //                     $expeted_payment_to_hr = $expeted_payment / 60;
    //                     if ( $expeted_payment_to_hr ) {
    //                         $expeted_payment_time = round( $expeted_payment_to_hr ) . ' ' . 'Hour';
    //                     } else {
    //                         $expeted_payment_time = round( $expeted_payment_to_hr ) . ' ' . 'Hours';
    //                     }

    //                     if ( $expeted_payment_to_hr >= 24 ) {

    //                         $remaning_times_to_hr = round( $expeted_payment_to_hr / 24 );
    //                         if ( $remaning_times_to_hr >= 1 ) {
    //                             $expeted_payment_time = round( $remaning_times_to_hr ) . ' ' . 'Days';
    //                         } else {
    //                             $expeted_payment_time = round( $remaning_times_to_hr ) . ' ' . 'Day';
    //                         }
    //                     }
    //                 }

    //                 if ( isset( $be->expiry_time ) && ( $be->expiry_time != 0 )  && ( $be->payment_link_status == 'valid' ) && $be->payment_status == 'Confirm' ) {

    //                     if ( ( $expiry_time > $current_time ) ) {
    //                         $remaning_time = $expiry_time - $current_time;
    //                         $remaning_time_to_min = round( $remaning_time / 60 );
    //                         $remaning_times = $remaning_time_to_min . ' ' . 'Minutes';
    //                         if ( $remaning_times >= 60 ) {
    //                             $remaning_times_to_hr = $remaning_time_to_min / 60;
    //                             $remaning_times = round( $remaning_times_to_hr ) . ' ' . 'Hours';
    //                             if ( $remaning_times_to_hr >= 24 ) {

    //                                 $remaning_times_to_hr = round( $remaning_times_to_hr / 24 );
    //                                 if ( $remaning_times_to_hr >= 1 ) {
    //                                     $remaning_times = round( $remaning_times_to_hr ) . ' ' . 'Days';
    //                                 } else {
    //                                     $remaning_times = round( $remaning_times_to_hr ) . ' ' . 'Day';
    //                                 }
    //                             }
    //                         }

    //                         $booking_details[ 'recived_amount' ] = $received_amount;
    //                     } else {
    //                         if ( ( $be->payment_link_status == 'valid' ) && $be->payment_status == 'Confirm' ) {
    //                             $remaning_times = 0;
    //                             $booking_details[ 'recived_amount' ] = $received_amount;
    //                         } else {
    //                             $remaning_times = 0;
    //                             $booking_details[ 'recived_amount' ] = $received_amount;
    //                         }

    //                     }
    //                 } else {

    //                     if ( isset( $be->expiry_time ) ) {
    //                         $remaning_times = strtotime( $be->expiry_time ) - $current_time;
    //                     } else {
    //                         $remaning_times = strtotime( $be->check_out );
    //                     }

    //                     if ( $remaning_times < $current_time ) {
    //                         $remaning_times = 0;
    //                     } else {
    //                         $expiry_time = strtotime( $be->check_out );
    //                         $remaning_time = $expiry_time - $current_time;
    //                         $remaning_time_to_min = round( $remaning_time / 60 );
    //                         $remaning_times = $remaning_time_to_min . ' ' . 'Minutes';
    //                         if ( $remaning_times >= 60 ) {
    //                             $remaning_times_to_hr = $remaning_time_to_min / 60;
    //                             $remaning_times = round( $remaning_times_to_hr ) . ' ' . 'Hours';
    //                             if ( $remaning_times_to_hr >= 24 ) {

    //                                 $remaning_times_to_hr = round( $remaning_times_to_hr / 24 );
    //                                 if ( $remaning_times_to_hr >= 1 ) {
    //                                     $remaning_times = round( $remaning_times_to_hr ) . ' ' . 'Days';
    //                                 } else {
    //                                     $remaning_times = round( $remaning_times_to_hr ) . ' ' . 'Day';
    //                                 }
    //                             }
    //                         }
    //                     }

    //                     $booking_details[ 'recived_amount' ] = $received_amount;
    //                 }

    //                 $booking_details[ 'payment_expected_in' ] = $expeted_payment_time;
    //                 $booking_details[ 'time_left' ] = $remaning_times == 0 ? 'Expired' : $remaning_times;
    //             } else {
    //                 $booking_details[ 'recived_amount' ] = $received_amount;
    //                 $booking_details[ 'payment_expected_in' ] = '-';
    //                 $booking_details[ 'time_left' ] = '-';
    //             }
    //             $booking_details[ 'total_amount' ] = $be->total_amount;
    //             $booking_details[ 'requested_amount' ] = $be->paid_amount;
    //             $booking_details[ 'booking_status' ] = $btn_status;
    //             $booking_details[ 'booking_status_color' ] = $booking_status_color;
    //             $booking_details[ 'payment_options' ] = $payment_options;
    //             $booking_details[ 'currency_symbol' ] = '20B9';
    //             $booking_details[ 'partner_name' ] = isset( $be->partner_name ) ? $be->partner_name : '-';
    //             $booking_details[ 'sales_executive' ] = isset( $be->sales_executive_name )?$be->sales_executive_name:'-';
    //             array_push( $all_booking_data, $booking_details );
    //         }
    //     }

    //     if ( $all_booking_data ) {
    //         $result = array( 'status' => 1, 'message' => 'Bookings Report Fetched Successfully', 'data' => $all_booking_data );
    //         return response()->json( $result );
    //     } else {
    //         $result = array( 'status' => 0, 'message' => $failure_message );
    //         return response()->json( $result );
    //     }
    // }

    public  function cmp($a, $b)
    {
        if (isset($a['date_type'])) {
            $data = $a['date_type'];
            return $b[$data] > $a[$data];
        } else {
            return $b['booking_date'] > $a['booking_date'];
        }
    }

    public function bookingVoucher($invoice_id)
    {

        $hotelbookings =  HotelBooking::leftjoin('kernel.user_table', 'user_table.user_id', '=', 'hotel_booking.user_id')
            ->join('booking_engine.invoice_table', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->where('hotel_booking.invoice_id', $invoice_id)
            ->select('hotel_booking.*', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile', 'user_table.address','invoice_table.guest_note','invoice_table.room_type','invoice_table.paid_amount')
            ->first();
        $invoice_details = Invoice::where('invoice_id',$invoice_id)->select('total_amount','tax_amount','discount_amount','paid_amount')->first();
        
        $BookingVoucherDetails = DB::table('booking_engine.voucher_day_wise_price')
              ->join('kernel.room_type_table', 'booking_engine.voucher_day_wise_price.room_type_id', 'kernel.room_type_table.room_type_id')
              ->join('kernel.room_rate_plan', 'booking_engine.voucher_day_wise_price.rate_plan_id', 'kernel.room_rate_plan.room_rate_plan_id')
              ->where('voucher_day_wise_price.invoice_id', $invoice_id)
              ->select('room_type_table.room_type',
                       'bar_price',
                       'rooms',
                       'date',
                       'room_rate',
                       'discount_price',
                       'gst_price',
                       'price_after_discount',
                       'booking_engine.voucher_day_wise_price.extra_adult_price',
                       'booking_engine.voucher_day_wise_price.extra_child_price',
                       'total_price',
                       'voucher_day_wise_price.room_type_id',
                       'voucher_day_wise_price.extra_adult',
                       'voucher_day_wise_price.extra_child',
                       'voucher_day_wise_price.extra_adult_price',
                       'voucher_day_wise_price.extra_child_price',
                       DB::raw('COUNT(rooms) as room_count'),
                       DB::raw('SUM(room_rate) as room_rate_total'),
                       DB::raw('SUM(discount_price) as discount_price_total'),
                       DB::raw('SUM(price_after_discount) as total_price_after_discount'),
                       DB::raw('SUM(gst_price) as total_gst_price'),
                       DB::raw('SUM(voucher_day_wise_price.extra_adult) as total_extra_adult'),
                       DB::raw('SUM(voucher_day_wise_price.extra_child) as total_extra_child'),
                    //    DB::raw('SUM(voucher_day_wise_price.extra_adult_price) as total_extra_adult_price'),
                    //    DB::raw('SUM(voucher_day_wise_price.extra_child_price) as total_extra_child_price'),
              )
              ->groupBy('room_type')
              ->get();
            // return $BookingVoucherDetails;

            $room_type_ids = $BookingVoucherDetails->pluck('room_type_id')->toArray();

            $room_details = [];
            $total_room_rate = 0;
            $total_discount_price = 0;
            $total_gst_price = 0;
            $total_price_after_discount = 0;
            $total_extra_adult = 0;
            $total_extra_child = 0;
           
          foreach ($BookingVoucherDetails as $Bookings) {
            
          $room_data = DB::table('booking_engine.voucher_day_wise_price')
          ->where('voucher_day_wise_price.invoice_id', $invoice_id)
          ->where('voucher_day_wise_price.room_type_id', $Bookings->room_type_id)
          ->join('kernel.room_type_table', 'voucher_day_wise_price.room_type_id', '=', 'room_type_table.room_type_id')
          ->select(
            'room_type_table.room_type',
            'rooms',
            'date',
            'room_rate',
            'discount_price',
            'gst_price',
            'price_after_discount',
            'booking_engine.voucher_day_wise_price.extra_adult_price',
            'booking_engine.voucher_day_wise_price.extra_child_price',
            'total_price',
            'voucher_day_wise_price.room_type_id',
            'voucher_day_wise_price.extra_adult',
            'voucher_day_wise_price.extra_child'
        )
        ->get();
        $total_extra_adult += $Bookings->total_extra_adult;
        $total_extra_child += $Bookings->total_extra_child;
       

        $extra_adult_price = $Bookings->extra_adult_price * $total_extra_adult;
        $extra_child_price = $Bookings->extra_child_price * $total_extra_child;

        $total_room_rate += $Bookings->room_rate_total + $extra_adult_price + $extra_child_price;
        $total_discount_price += $Bookings->discount_price_total;
        $total_gst_price += $Bookings->total_gst_price;
        $price_breakup = '';
        foreach ($room_data as $room)
        {
            // dd($room->discount_price);
            $price_breakup .= '
                                    <tr>
                                        <td align="left" style="padding-top: 10px">
                                            <table cellpadding="0" cellspacing="0" border="0"
                                                role="presentation" style="border-spacing: 0">
                                                <tr>
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="71"
                                                    style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 60px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            Room '.$room->rooms.'
                                                        </p>
                                                    </td>

                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                        <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="90"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 90px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            '.$room->date.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="87"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 87px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            ₹'.$room->room_rate.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="114"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 114px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            -₹'.$room->discount_price.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="124"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 124px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            ₹'.$room->price_after_discount.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="105"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 105px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            ₹'.$room->extra_adult_price * $room->extra_adult.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="106"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 106px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            ₹'.$room->extra_child_price * $room->extra_child.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="111"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 111px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            ₹'.$room->room_rate.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top" width="102"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px; width: 102px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            ₹'.$room->gst_price.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                    <!--[if mso]>
                                                        <td>
                                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <![endif]-->
                                                    <td valign="top"
                                                        style="padding-top: 0; padding-bottom: 18px; padding-left: 8px">
                                                        <p
                                                            style="font-family: Inter; font-size: 14px; font-weight: 400; margin: 0; padding: 0">
                                                            ₹'.$room->total_price.'</p>
                                                    </td>
                                                    <!--[if mso]>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <![endif]-->
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>';
                                   
        }
        $room_details[$Bookings->room_type] = $price_breakup;

      }
    //   dd($extra_adult_price);
        $total_price_after_discount = $total_room_rate - $total_discount_price ;


        $total_amount = $invoice_details->total_amount;
        $discount_amount = $invoice_details->discount_amount;
        $tax_amount = $invoice_details->tax_amount;
        // $paid_amount = $invoice_details->paid_amount;
        $amount_exclu_tax = $total_amount - $tax_amount;
        $amount_after_discount = $amount_exclu_tax - $discount_amount;
        $amount_inclu_tax = $total_price_after_discount + Round($total_gst_price,2);
        
        $guest_note = $hotelbookings->guest_note;
        $paid_amount = $hotelbookings->paid_amount;
        
        $pay_at_hotel = $amount_inclu_tax - $paid_amount; 

        $hotel_details = HotelInformation::where('hotel_id', $hotelbookings->hotel_id)->first();
        $city_details = City::where('city_id', $hotel_details->city_id)->first();
        $city_name = $city_details->city_name;
        
        $get_logo_info = CompanyDetails::select('logo')->where('company_id', $hotel_details->company_id)->first();
        $get_logo = ImageTable::select('image_name')->where('image_id', $get_logo_info->logo)->first();

        if (isset($get_logo->image_name)) {
            // $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $get_logo->image_name;
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        } else {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        }

        $booking_id = $hotelbookings->hotel_booking_id.date('dmy');
        $guest_name = $hotelbookings->first_name . ' ' . $hotelbookings->last_name;
        $mobile =  $hotelbookings->mobile;
        $email_id =  $hotelbookings->email_id;
        $address =  $hotelbookings->address;

        $booking_date = date('d M Y', strtotime($hotelbookings->booking_date));
        $checkin_date = date('d M Y', strtotime($hotelbookings->check_in));
        $checkout_date = date('d M Y', strtotime($hotelbookings->check_out));
        // $package_name = $hotelbookings->package_name;
        
        $date1 = date_create($checkin_date);
        $date2 = date_create($checkout_date);
        
        $diff = date_diff($date1, $date2);
        $no_of_nights = $diff->format('%a');
        if ($no_of_nights == 0) {
            $no_of_nights = 1;
        }
        
        
        $hotel_name = $hotel_details->hotel_name;
        $hotel_address = $hotel_details->hotel_address;
        $hotel_mobile = $hotel_details->mobile;
        $hotel_email_id = $hotel_details->email_id;
        $hotel_terms_and_cond = $hotel_details->terms_and_cond;

        $body =
         '<!DOCTYPE html>
        <html lang="und">
        
        <head>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@700" />
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Urbanist:wght@600;700" />
            <title></title>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <!--[if !mso]>
                <!-->
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <!--
                <![endif]-->
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
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e"> ' . $booking_id . '
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
                                        <!--[if mso]>
                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" width="1077px" style=" width:1077px;">
                                                <tr>
                                                    <td>
                                                        <![endif]-->
                                        <div width="1077" style="width: 1077px; border-top: 2px solid #e7e7e7"></div>
                                        <!--[if mso]>
                                                    </td>
                                                </tr>
                                            </table>
                                            <![endif]-->
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 11px; padding-bottom: 6px; padding-left: 40px">
                                        <p
                                            style="font-size: 18px; font-weight: 600; color: #616161; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Dear '.$guest_name.',</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 6px; padding-bottom: 11px; padding-left: 40px">
                                        <!--[if mso]>
                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" width="1077px" height="66px" style=" width:1077px; height:66px;">
                                                <tr>
                                                    <td>
                                                        <![endif]-->
                                        <p width="1077" height="66"
                                            style="font-size: 18px; font-weight: 600; text-align: left; color: #616161; margin: 0; padding: 0; width: 1077px; height: 66px">
                                            <span>Thank you for choosing </span>
                                            <span
                                                style="font-size: 18px; font-weight: 700; color: #1C5EAA; text-align: left">'.$hotel_name.','.$city_name.'</span>
                                            <span>, as your property of choice for your visit. Your booking confirmation details
                                                have been provided below. We sincerely appreciate your decision to book through
                                                our website </span>
                                            <span>. Your support is invaluable to us, and we are committed to ensuring your stay
                                                exceeds your expectations.</span>
                                        </p>
                                        <!--[if mso]>
                                                    </td>
                                                </tr>
                                            </table>
                                            <![endif]-->
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
                                                        <span> '.$hotel_name.', </span>
                                                        <span
                                                            style="font-size: 20px; font-weight: 700; color: #616161">'.$city_name.'</span>
                                                    </p>
                                                </td>
                                                <!--[if mso]>
                                                    <td>
                                                        <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                                                            <tr>
                                                                <![endif]-->
                                                <td align="right" valign="top" width="287"
                                                    style="padding-bottom: 4px; width: 287px">
                                                    <p style="font-family: Inter; font-size: 16px; font-weight: 400">
                                                        Booked on:
                                                        <span
                                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">'.$booking_date.'</span>
                                                    </p>
                                                </td>
                                                <!--[if mso]>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                    <![endif]-->
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
                                                            <td valign="top" style="padding-top: 26px; padding-bottom: 22px">
                                                                <table align="center" cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" style="border-spacing: 0">
                                                                    <tr>
                                                                        <td align="center" valign="top" height="107"
                                                                            style="height: 107px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle" width="191"
                                                                                        style="width: 191px">
                                                                                        <p
                                                                                            style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 400">Check
                                                                                                In: </span>
                                                                                        </p>
        
                                                                                        <p
                                                                                            style="margin-top: 4px; margin-bottom: 0px;">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e;">'.$checkin_date.'
                                                                                            </span>
                                                                                        </p>
        
                                                                                    </td>
                                                                                    <td valign="middle" width="90"
                                                                                        style="padding-left: 8px; width: 90px">
                                                                                        <!-- Button Component is detected here -->
                                                                                        <!--[if mso]>
                                                                                            <table border="0" cellspacing="0" cellpadding="0" role="presentation" width="87.00">
                                                                                                <tr>
                                                                                                    <td align="center" valign="middle" width="87.00" height="38.00" bgcolor="transparent" style=" border-radius: 7px; border: 1px solid black;">
                                                                                                        <![endif]-->
                                                                                        <a href="#" target="_blank"
                                                                                            rel="noopener noreferrer"
                                                                                            bgcolor="transparent" width="85"
                                                                                            style="border-radius: 7px; border: 1px solid black; background-color: transparent; cursor: pointer; min-width: 85px; width: 85px; display: block; text-align: center; text-decoration: none; mso-border-alt: none">
                                                                                            <span
                                                                                                style="font-size: 18px; font-weight: 600; color: #313131; line-height: 38px; mso-line-height-rule: exactly">'.$no_of_nights.'
                                                                                                nights</span>
                                                                                        </a>
                                                                                        <!--[if mso]>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                            <![endif]-->
                                                                                    </td>
                                                                                    <td align="right" valign="middle"
                                                                                        width="191" style="width: 191px"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 400">Check
                                                                                                Out: </span>
                                                                                        </p>
        
                                                                                        <p
                                                                                            style="margin-top: 4px; margin-bottom: 0px;">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e;">'.$checkout_date.'
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
                                                                                                Room Booked</p>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>';
                                                                            
                                                                                  foreach ($BookingVoucherDetails as $booking)
                                                                                    {
                                                                                $body .= ' <tr>
                                                                                            <td valign="middle" style="padding-bottom: 6px;">
                                                                                                <p style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                    '.$booking->room_type.' x '.$booking->room_count.'
                                                                                                  
                                                                                                </p>
                                                                                            </td> <td valign="middle" align="right"
                                                                                            style="padding-bottom: 6px;">
                                                                                            <p
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                Ault 1, Child 0</p>
                                                                                        </td>
                                                                                            
                                                                                        </tr>';
                                                                                    }
                                                                                    $body .= '    
                                                                                        
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
                                                                                '.$guest_name.'</p>
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
                                                                                            '.$mobile.'</p>
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
                                                                                            '.$email_id.'</p>
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
                                                                                            '.$address.'</p>
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
                                                                    '.$guest_note.'
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
                <td align="left" style="padding-top: 16px; padding-bottom: 12px; padding-left: 40px">
                    <p
                        style="font-family: Inter; font-size: 18px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                        Price Breakup</p>
                </td>
            </tr>
            <tr><br>
                <td align="left" style="padding-top: 12px; padding-bottom: 12px; padding-left: 40px">
                    <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="1077"
                        height="158"
                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 1077px; height: 158px; border-spacing: 0">
                        <tr>
                            <td align="left" valign="middle" style="padding-left: 17px">
                                <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                    style="border-spacing: 0">';
                                    foreach ($BookingVoucherDetails as $booking)
                                    {
                                        $body.= '<tr>
                                        <td align="left" style="padding-bottom: 9px; padding-left: 7px">
                                            <p
                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                '.$booking->room_type.'</p>
                                        </td>
                                     </tr><br>
                                     <tr>
                                        <td align="left"
                                            style="padding-top: 9px; padding-bottom: 10px; padding-left: 4px; background-color: #D9D9D9;">
                                            <table cellpadding="0" cellspacing="0" border="0"
                                                role="presentation" style="border-spacing: 0">
                                                <tr>
                                                    <td width="81" style="width: 81px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Room</p>
                                                    </td>
                                                    <td width="61" style="padding-left: 8px; width: 61px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Date</p>
                                                    </td>
                                                    <td width="93" style="padding-left: 8px; width: 93px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Room Rate</p>
                                                    </td>
                                                    <td width="78" style="padding-left: 8px; width: 78px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Discount</p>
                                                    </td>
                                                    <td width="148" style="padding-left: 8px; width: 148px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Price After Discount</p>
                                                    </td>
                                                    <td width="99" style="padding-left: 8px; width: 99px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Extra Adult</p>
                                                    </td>
                                                    <td width="97" style="padding-left: 8px; width: 97px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Extra Child</p>
                                                    </td>
                                                    <td width="148" style="padding-left: 8px; width: 148px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Amount Excl. Tax</p>
                                                    </td>
                                                    <td width="64" style="padding-left: 8px; width: 64px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            GST</p>
                                                    </td>
                                                    <td style="padding-right: 8px">
                                                        <p
                                                            style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 17px; mso-line-height-rule: exactly">
                                                            Amount Incl. Tax</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                   '.$room_details[$booking->room_type].'';
                                
                            }
                            $body .= '</table>
                             </td>
                        </tr>
                           </table>
                         </td>
                     </tr>

                                <tr>
                                    <td align="left" width="100.00%" style="padding-top: 10px; width: 100%">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="100.00%"
                                            style="width: 100%; border-spacing: 0">
                                            <tr>
                                                <td style="padding-left: 752px; padding-right: 36px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="365" height="222"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 365px; height: 222px; border-spacing: 0">
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
                                                                                                        Total Room Price Excl.
                                                                                                        tax</p>
                                                                                                </td>
                                                                                                <td align="right" width="51"
                                                                                                    style="width: 51px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹'.$total_room_rate.'</p>
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
                                                                                                <td width="282"
                                                                                                    style="width: 282px">
                                                                                                    <!--[if mso]>
                                                                                                        <table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" height="19px" style=" height:19px;">
                                                                                                            <tr>
                                                                                                                <td>
                                                                                                                    <![endif]-->
                                                                                                    <p height="19"
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; height: 19px; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Discount</p>
                                                                                                    <!--[if mso]>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                        </table>
                                                                                                        <![endif]-->
                                                                                                </td>
                                                                                                <td align="right" 
                                                                                                    >
                                                                                                    <p
                                                                                                        style="font-size: 14px;display:flex; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        -₹'.$total_discount_price.'</p>
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
                                                                                                        Amount After Discount
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td align="right" width="51"
                                                                                                    style="width: 51px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹'.$total_price_after_discount.'</p>
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
                                                                                                <td width="288"
                                                                                                    style="width: 288px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Total GST</p>
                                                                                                </td>
                                                                                                <td align="right" width="43"
                                                                                                    style="width: 43px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹'.Round($total_gst_price,2).'</p>
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
                                                                                                <td align="right" width="51"
                                                                                                    style="width: 51px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹'.$amount_inclu_tax.'</p>
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
                                                                                                <td width="283"
                                                                                                    style="width: 283px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Paid Amount</p>
                                                                                                </td>
                                                                                                <td align="right" width="48"
                                                                                                    style="width: 48px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹'.$paid_amount.'</p>
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
                                                                                                <td align="right" width="51"
                                                                                                    style="width: 51px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹'.$pay_at_hotel.'</p>
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
                                            '.$hotel_name.'</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 5.5px; padding-bottom: 7.5px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/location_b.png"
                                                        width="15.00" height="16.00"
                                                        style="width: 15px; height: 16px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                        '.$hotel_address.'</p>
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
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/call_b.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                       '. $hotel_mobile.'</p>
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
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/email_b.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        <a href="mailto:'.$hotel_email_id.'" target="_blank"
                                                            rel="noopener noreferrer"
                                                            style="font-size: 16px; font-weight: 600; text-decoration-line: underline; color: inherit">'.$hotel_email_id.',</a>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 40px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span style="font-size: 16px; font-weight: 700; color: #ff2828">Note :</span>
                                            <span>'.$hotel_terms_and_cond.'</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 8.5px; padding-bottom: 3.5px">
                                        <!--[if mso]>
                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" width="1077px" style=" width:1077px;">
                                                <tr>
                                                    <td>
                                                        <![endif]-->
                                        <div width="1077" style="width: 1077px; border-top: 2px solid #e7e7e7"></div>
                                        <!--[if mso]>
                                                    </td>
                                                </tr>
                                            </table>
                                            <![endif]-->
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 6px; padding-left: 40px">
                                       
                                                '.$hotel_details->cancel_policy.'
        
                                                '. $hotel_details->hotel_policy .'
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
        // if ($body) {
        //     $html_voucher = array('html' => $body);
        //     $curl = curl_init();
        //     curl_setopt_array($curl, array(
        //         CURLOPT_URL => 'https://tools.bookingjini.com/pdfmonkey',
        //         CURLOPT_RETURNTRANSFER => true,
        //         CURLOPT_ENCODING => '',
        //         CURLOPT_MAXREDIRS => 10,
        //         CURLOPT_TIMEOUT => 0,
        //         CURLOPT_FOLLOWLOCATION => true,
        //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //         CURLOPT_CUSTOMREQUEST => 'POST',
        //         CURLOPT_POSTFIELDS => $html_voucher,
        //     ));
        //     $response = curl_exec($curl);
        //     curl_close($curl);

        //     $res = array('status' => 1, "message" => "Invoice feteched sucesssfully!", 'data' => $response);
        //     return response()->json($res);
        // } else {
        //     $res = array('status' => 0, "message" => "Invoice details not found");
        //     return response()->json($res);
        // }
       
    }


 }