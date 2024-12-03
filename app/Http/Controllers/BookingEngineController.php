<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Inventory; //class name from model
use App\MasterRoomType; //class name from model
use App\MasterHotelRatePlan; //class name from model
use App\RatePlanLog; //class name from model
use App\HotelInformation;
use App\HotelAmenities;
use App\PaidServices;
use App\Invoice;
use App\User;
use App\Country;
use App\HotelBooking;
use App\CmOtaDetails;
use App\CmOtaRoomTypeSynchronizeRead;
use App\ProductPrice;
use DB;
use App\CancellationPolicy;
use App\CancellationPolicyMaster;

use App\Notifications;
use App\BENotificationSlider;
use App\PmsAccount;
use App\ImageTable;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\InventoryService;
use App\OnlineTransactionDetail;
use App\CompanyDetails;
use App\TaxDetails;
use App\Coupons;
use App\BeSettings;
use App\MasterRatePlan;
use App\BeBookingDetailsTable;
use App\BillingDetails;
use App\Refund;
use App\Http\Controllers\PmsController;
use App\AmenityCategories;
//create a new class ManageInventoryController
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use App\PaymentGetway;
use App\UserNew;
use APP\DynamicPricingCurrentInventory;
use App\Models\Commonmodel;
use App\DayPackage;
use App\DayBookingARI;
use App\DayBookingARILog;
use App\DayBookings;
use App\PaymentGetwayNew;

class BookingEngineController extends Controller
{
    protected $invService, $ipService, $idsService;
    protected $updateInventoryService, $updateInventoryServiceTest;
    protected $otaInvRateService;
    protected $curency;
    protected $beConfBookingInvUpdate;
    protected $gst_arr = array();
    public function __construct(InventoryService $invService, UpdateInventoryService $updateInventoryService, CurrencyController $curency, IpAddressService $ipService, PmsController $idsService, InvRateBookingDisplayController $otaInvRateService, UpdateInventoryServiceTest $updateInventoryServiceTest, BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate)
    {
        $this->invService = $invService;
        $this->updateInventoryService = $updateInventoryService;
        $this->updateInventoryServiceTest = $updateInventoryServiceTest;
        $this->curency = $curency;
        $this->ipService = $ipService;
        $this->idsService = $idsService;
        $this->otaInvRateService = $otaInvRateService;
        $this->beConfBookingInvUpdate = $beConfBookingInvUpdate;
    }
    //validation rules
    private $rules = array(
        'hotel_id' => 'required | numeric',
        'from_date' => 'required ',
        'to_date' => 'required',
        'cart' => 'required'
    );
    //Custom Error Messages
    private $messages = [
        'hotel_id.required' => 'The hotel id field is required.',
        'hotel_id.numeric' => 'The hotel id must be numeric.',
        'from_date.required' => 'Check in date is required.',
        'to_date.required' => 'Check out is required.',
        'cart.required' => 'cart is required.'
    ];
    /* Get api key access and Hotel id
      * @auther Godti Vinod
      * function getAccess for fetching data
         **/
    public function getAccess(string $company_url, Request $request)
    {

        $conditions = array('company_url' => $company_url);
        $info = CompanyDetails::select('api_key', 'hotels_table.hotel_id', 'company_table.company_id', 'chat_option', 'currency', 'is_widget_active', 'refundable_cancelation_status', 'refundable_cancelation_mode', 'be_theme_color', 'be_header_color', 'be_menu_color')
            ->join('hotels_table', 'company_table.company_id', '=', 'hotels_table.company_id')
            ->where($conditions)->where('hotels_table.status', 1)->first();

        if ($info) {

            $hotels = HotelInformation::where('company_id', $info['company_id'])->where('status', 1)->select('*')->get();

            if ($hotels) {
                $hotels = $hotels->toArray();
                $hotel_with_package = [];
                foreach ($hotels as $key => $hotel) {
                    $today_date = date('Y-m-d');
                    $package_details = DB::table('kernel.package_table')->where('hotel_id', $hotel['hotel_id'])->where('date_to', '>', $today_date)->where('is_trash', 0)->first();
                    if ($package_details) {
                        $hotel_with_package[] = $hotel['hotel_id'];
                    }
                }
            }

            $info['hotel_with_package'] = $hotel_with_package;


            if (isset($info['api_key'])) {
                $info['comp_hash'] = openssl_digest($info['company_id'], 'sha512');
                if (empty($info['be_theme_color'])) {
                    $info['be_theme_color'] = '#F5AB35';
                }

                if ($info['currency'] == 'BDT') {
                    $info['currency_symbol'] = '09F3';
                } else if ($info['currency'] == 'USD') {
                    $info['currency_symbol'] = '0024';
                } else if ($info['currency'] == 'EUR') {
                    $info['currency_symbol'] = '20AC';
                } else if ($info['currency'] == 'GBP') {
                    $info['currency_symbol'] = '00A3';
                } elseif ($info['currency'] == 'THB') {
                    $info['currency_symbol']  = '0E3F';
                } else {
                    $info['currency_symbol'] = '20B9';
                }

                $res = array('status' => 1, 'message' => "Company auth successfull", 'data' => $info);
                return response()->json($res);
            } else {
                $res = array('status' => 0, 'message' => "Invalid company url");
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, 'message' => "Invalid company url");
            return response()->json($res);
        }
    }

    /**
     * Get base currency of hotel/Company
     * @param hotel_id(Hotel Id)
     * @auther Godti Vinod
     */
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
    //get inventory and rate details for app.
    /**
     * this getInvByApp is used to fetch the inventory and rate data for app
     * @author Ranjit Date: 25-06-2022
     */
    public function getInvByApp(string $api_key, int $hotel_id, string $date_from, string $date_to, string $currency_name, Request $request)
    {
        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 1, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime($date_to));
        $today = date('Y-m-d');
        if ($date_from < $today) {
            $date_from = $today;
        }
        $roomType = new MasterRoomType();
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0, 'be_room_status' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'max_room_capacity', 'image', 'rack_price', 'extra_person', 'extra_child', 'bed_type', 'room_amenities', 'max_occupancy', 'max_infant')->where($conditions)->orderBy('rack_price', 'ASC')->get();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $room_details = array();
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency; //Get base currency
        $room_type_n_rate_plans = array();
        if ($room_types) {
            foreach ($room_types as $key => $room) {
                $room['min_inv'] = 0;
                if ($baseCurrency == $currency_name) {
                    $room_details[$key]["rack_price"] = $room["rack_price"];
                } else {
                    $room_details[$key]["rack_price"] = round($this->curency->currencyDetails($room["rack_price"], $currency_name, $baseCurrency), 2);
                }
                $room['room_amenities'] = explode(',', $room['room_amenities']);
                $room['room_amenities'] = $this->getRoomamenity($room['room_amenities']);
                $room_details[$key]["room_amenities"] = $room['room_amenities'];

                $room_details[$key]["min_inv"] = 0;
                $room_details[$key]["extra_child"] = $room["extra_child"];
                $room_details[$key]["extra_person"] = $room["extra_person"];
                $room_details[$key]["max_child"] = $room["max_child"];
                $room_details[$key]["max_infant"] = $room["max_infant"];
                $room_details[$key]["max_people"] = $room["max_people"];
                $room_details[$key]["room_type"] = $room["room_type"];
                $room_details[$key]["room_type_id"] = $room["room_type_id"];
                $room_details[$key]["max_room_capacity"] = $room["max_room_capacity"];
                $room['image'] = explode(',', $room['image']); //Converting string to array

                $room_types[$key]['allImages'] = $this->getImages($room->image); //--------- get all the images of rooms 

                $room['image'] = $this->getImages(array($room['image'][0])); //Getting actual amenity names
                if (is_object($room['image']) && sizeof($room['image']) > 0) {
                    $room['image'] = $room['image'][0]->image_name;
                    $room_details[$key]["image"] = $room['image'];
                } else {
                    $room['image'] = $this->getImages(array(1));
                    $room['image'] = $room['image'][0]->image_name;
                    $room_details[$key]["image"] = $room['image'];
                }
                foreach ($date1 as $d) {
                    $from_date = date('Y-m-d', strtotime($d));
                    break;
                }
                foreach ($date2 as $d1) {
                    $to_date = date('Y-m-d', strtotime($d1));
                    break;
                }
                $resp = $this->getAllCouponsForApp($hotel_id, $from_date, $to_date, $request);
                $resp = json_decode($resp->getContent(), true);
                $room['coupons'] = array();
                if (isset($resp['data'])) {
                    foreach ($resp['data'] as $coupons) {
                        if (($coupons['room_type_id'] === $room["room_type_id"] || $coupons['room_type_id'] === 0) && (strtotime($from_date) >= strtotime($coupons['valid_from']))) {
                            $room['coupons'] = array($coupons);
                        }
                    }
                }
                $room_details[$key]["coupons"] = $room['coupons'];
                $data = $this->invService->getInventeryByRoomTYpe($room['room_type_id'], $date_from, $date_to, 0);
                $room['min_inv']  = $data[0]['no_of_rooms'];
                $room_details[$key]["min_inv"] = $data[0]['no_of_rooms'];
                foreach ($data as $inv_room) {
                    if ($inv_room['no_of_rooms'] < $room['min_inv']) {
                        $room['min_inv']  = $inv_room['no_of_rooms'];
                        $room_details[$key]["min_inv"] = $inv_room['no_of_rooms'];
                    }
                }
                $room['inv'] = $data;
                $room["max_occupancy"] = $room["max_occupancy"];
                // $room["max_occupancy"]=0;
                $room_details[$key]["inv"] = $data;
                $room_type_n_rate_plans = MasterHotelRatePlan::join('rate_plan_table as a', function ($join) {
                    $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                })
                    ->select('a.rate_plan_id', 'plan_type', 'plan_name', 'room_rate_plan.bar_price')
                    ->where('room_rate_plan.hotel_id', $hotel_id)
                    ->where('room_rate_plan.is_trash', 0)
                    ->where('room_rate_plan.be_rate_status', 0)
                    ->where('room_rate_plan.room_type_id', $room['room_type_id'])
                    ->orderBy('room_rate_plan.bar_price', 'ASC')
                    ->distinct()
                    ->get();
                $room['min_room_price'] = 0;
                $room_details[$key]["min_room_price"] = 0;
                $rateplan_details = array();

                foreach ($room_type_n_rate_plans as $key1 => $all_types) {
                    $rate_plan_id = (int)$all_types['rate_plan_id'];
                    $room_rate_status = $this->checkroomrateplan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    if ($room_rate_status) {
                        $data = $this->invService->getRatesByRoomnRatePlan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    } else {
                        if ($hotel_id == 2142 || $hotel_id == 2143 || $hotel_id == 2267) {
                            unset($room_type_n_rate_plans[$key1]);
                            continue;
                        } else {
                            continue;
                        }
                    }
                    //$data=$this->invService->getRatesByRoomnRatePlan($room['room_type_id'],$rate_plan_id,$date_from, $date_to);
                    $all_types['bar_price'] = 0;
                    $rateplan_details[$key1]['bar_price'] = 0;
                    $rate_data = array();
                    $rateplan_details[$key1]['plan_name'] = $all_types["plan_name"];
                    $rateplan_details[$key1]['plan_type'] = $all_types["plan_type"];
                    $rateplan_details[$key1]['rate_plan_id'] = $all_types["rate_plan_id"];

                    if ($data) {
                        foreach ($data as $key2 => $rate) {
                            $cur_value = array();
                            $currency_value = array();
                            $multiple_occ = array();
                            $i = 0;
                            $j = 0;

                            if (isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"]) == 0) {
                                $rate["multiple_occupancy"][0] = $all_types['bar_price'];
                            }
                            if (isset($rate["multiple_occupancy"]) && $rate["multiple_occupancy"] != "" && sizeof($rate["multiple_occupancy"]) > 0) {
                                foreach ($rate["multiple_occupancy"] as $value) {
                                    $multiple_occ[$i] = $this->getPriceDetails($rate, $value, $all_types['bar_price'], $diff, $diff1);
                                    $i++;
                                }
                                foreach ($multiple_occ as $mult) {
                                    $cur_value[$j] = round($this->curency->currencyDetails($mult, $currency_name, $baseCurrency), 2);
                                    $j++;
                                }
                                $data[$key2]["multiple_occupancy"] = $multiple_occ;
                                if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                                    $rate_data[$key2]["multiple_occupancy"] = $multiple_occ;
                                } else {
                                    $rate_data[$key2]["multiple_occupancy"] = $cur_value; //otherwise converted
                                }
                            }
                            $all_types['bar_price'] = $this->getPriceDetails($rate, $rate['bar_price'], $all_types['bar_price'], $diff, $diff1);
                            if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                                $rateplan_details[$key1]['bar_price'] = $all_types['bar_price'];
                            } else {
                                $rateplan_details[$key1]['bar_price'] = round($this->curency->currencyDetails($all_types['bar_price'], $currency_name, $baseCurrency), 2);
                            }
                            $data[$key2]['bar_price'] = $all_types['bar_price'];
                            $rate_data[$key2]['bar_price'] = $rateplan_details[$key1]['bar_price'];
                            $rate_data['before_days_offer'] = $rate["before_days_offer"];
                            $rate_data['bookingjini_price'] = $rate["bookingjini_price"];
                            $rate_data['currency'] = $rate["currency"];
                            $rate_data['date'] = $rate["date"];
                            $rate_data['day'] = $rate["day"];
                            $rate_data['extra_adult_price'] = $rate["extra_adult_price"];
                            $rate_data['extra_child_price'] = $rate["extra_child_price"];
                            $rate_data['hex_code'] = $rate["hex_code"];
                            $rate_data['lastminute_offer'] = $rate["lastminute_offer"];
                            $rate_data['rate_plan_id'] = $rate["rate_plan_id"];
                            $rate_data['room_type_id'] = $rate["room_type_id"];
                            $rate_data['stay_duration_offer'] = $rate["stay_duration_offer"];
                        }
                    }
                    if ($room['min_room_price'] == 0) {
                        $room['min_room_price'] = $all_types['bar_price'];
                        $room_details[$key]['min_room_price'] = $rateplan_details[$key1]['bar_price'];
                    }
                    if ($all_types['bar_price'] <= $room['min_room_price'] && $all_types['bar_price'] != null) {
                        $room['min_room_price']  = $all_types['bar_price'];
                        $room_details[$key]['min_room_price'] = $rateplan_details[$key1]['bar_price'];
                    }
                    if ($all_types['bar_price'] != 0 || $all_types['bar_price'] != null) {
                        $all_types['rates'] = $data;
                        $rateplan_details[$key1]['rates'] = $rate_data;
                    }
                    if ($all_types['bar_price'] == 0 || $all_types['bar_price'] == NULL) {
                        unset($room_type_n_rate_plans[$key1]);
                        // unset($rateplan_details[$key1]);
                    }
                    $rateplan_details = array_values($rateplan_details);
                }
                if ($room['min_room_price'] != 0 &&  $room['min_room_price'] != null) {
                    $room['rate_plans'] = $room_type_n_rate_plans;
                    $room_details[$key]['rate_plans'] = $rateplan_details;
                }
            }
            $res = array('status' => 1, 'message' => "Hotel inventory retrieved successfully ", 'data' => $room_types, 'room_data' => $room_details);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
    }
    public function getAllCouponsForApp(String $hotel_id, $from_date, $to_date, Request $request)
    {

        $begin = strtotime($from_date);
        $end = strtotime($to_date);
        $from_date = date('Y-m-d', strtotime($from_date));
        $data_array = array();
        for ($currentDate = $begin; $currentDate < $end; $currentDate += (86400)) {
            $data_array_present = array();
            $data_array_notpresent = array();
            $status = array();
            $Store = date('Y-m-d', $currentDate);

            $get_data = DB::select(DB::raw('select coupon_id,room_type_id,
        case when room_type_id != 0 then "present" when room_type_id = 0 then "notpresent" end as status,
        coupon_name,coupon_code,valid_from,
        valid_to,discount_type,coupon_for,discount,a.date,a.abc
        FROM
        (
        select t2.coupon_id,
        case when t2.room_type_id!=0 then t2.room_type_id else 0 end as room_type_id,
        coupon_name,coupon_code,valid_from,valid_to,discount_type,coupon_for,discount,"' . $Store . '" as date,
        case when "' . $Store . '" between valid_from and valid_to then "yes" else "no" end as abc
        from booking_engine.coupons
        INNER JOIN
        (
        SELECT room_type_id,
        substring_index(group_concat(cast(coupon_id as CHAR) order by discount desc), ",", 1 ) as coupon_id,MAX(discount)
        FROM booking_engine.coupons where hotel_id = "' . $hotel_id . '" and coupon_for = 3 and is_trash = 0
        and ("' . $Store . '" between valid_from and valid_to)
        GROUP BY room_type_id
        order by coupon_id desc
        ) t2 ON coupons.room_type_id = t2.room_type_id AND coupons.coupon_id = t2.coupon_id
        ) AS a where a.abc = "yes"
        order by room_type_id,coupon_id desc'));

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
        if (sizeof($data_array) > 0) {
            $res = array('status' => 1, 'message' => "Public coupon retrieved successfully", 'data' => $data_array);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "!Sorry Public coupon is not available", 'data' => array());
            return response()->json($res);
        }
    }
    //Get Inventory
    /**
     * Get Inventory By Hotel id
     * get all record of Inventory by hotel id
     * @auther Godti Vinod
     * function getInvByHotel for fetching data
     **/
    public function getInvByHotelCalender(string $api_key, int $hotel_id, string $date_from, string $date_to, string $currency_name, Request $request)
    {
        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 1, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime($date_to));
        $roomType = new MasterRoomType();
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0, 'be_room_status' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'max_room_capacity', 'image', 'rack_price', 'extra_person', 'extra_child', 'bed_type', 'room_amenities', 'max_occupancy', 'max_infant')->where($conditions)->orderBy('rack_price', 'ASC')->get();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $room_details = array();
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency; //Get base currency
        $room_type_n_rate_plans = array();
        if ($room_types) {
            foreach ($room_types as $key => $room) {
                $room['min_inv'] = 0;
                if ($baseCurrency == $currency_name) {
                    $room_details[$key]["rack_price"] = $room["rack_price"];
                } else {
                    $room_details[$key]["rack_price"] = round($this->curency->currencyDetails($room["rack_price"], $currency_name, $baseCurrency), 2);
                }
                $room['room_amenities'] = explode(',', $room['room_amenities']);
                $room['room_amenities'] = $this->getRoomamenity($room['room_amenities']);
                $room_details[$key]["room_amenities"] = $room['room_amenities'];

                $room_details[$key]["min_inv"] = 0;
                $room_details[$key]["extra_child"] = $room["extra_child"];
                $room_details[$key]["extra_person"] = $room["extra_person"];
                $room_details[$key]["max_child"] = $room["max_child"];
                $room_details[$key]["max_infant"] = $room["max_infant"];
                $room_details[$key]["max_people"] = $room["max_people"];
                $room_details[$key]["room_type"] = $room["room_type"];
                $room_details[$key]["room_type_id"] = $room["room_type_id"];
                $room_details[$key]["max_room_capacity"] = $room["max_room_capacity"];
                $room['image'] = explode(',', $room['image']); //Converting string to array

                $room_types[$key]['allImages'] = $this->getImages($room->image); //--------- get all the images of rooms 

                $room['image'] = $this->getImages(array($room['image'][0])); //Getting actual amenity names
                if (is_object($room['image']) && sizeof($room['image']) > 0) {
                    $room['image'] = $room['image'][0]->image_name;
                    $room_details[$key]["image"] = $room['image'];
                } else {
                    $room['image'] = $this->getImages(array(1));
                    $room['image'] = $room['image'][0]->image_name;
                    $room_details[$key]["image"] = $room['image'];
                }
                foreach ($date1 as $d) {
                    $from_date = date('Y-m-d', strtotime($d));
                    break;
                }
                foreach ($date2 as $d1) {
                    $to_date = date('Y-m-d', strtotime($d1));
                    break;
                }
                $resp = $this->getAllPublicCupons($hotel_id, $from_date, $to_date, $request);
                $resp = json_decode($resp->getContent(), true);
                $room['coupons'] = array();
                if (isset($resp['data'])) {
                    foreach ($resp['data'] as $coupons) {
                        if (($coupons['room_type_id'] === $room["room_type_id"] || $coupons['room_type_id'] === 0) && (strtotime($from_date) >= strtotime($coupons['valid_from']))) {
                            $room['coupons'] = array($coupons);
                        }
                    }
                }
                $room_details[$key]["coupons"] = $room['coupons'];
                $data = $this->invService->getInventeryByRoomTYpe($room['room_type_id'], $date_from, $date_to, 0);
                $room['min_inv']  = $data[0]['no_of_rooms'];
                $room_details[$key]["min_inv"] = $data[0]['no_of_rooms'];
                foreach ($data as $inv_room) {
                    if ($inv_room['no_of_rooms'] < $room['min_inv']) {
                        $room['min_inv']  = $inv_room['no_of_rooms'];
                        $room_details[$key]["min_inv"] = $inv_room['no_of_rooms'];
                    }
                }
                $room['inv'] = $data;
                $room["max_occupancy"] = $room["max_occupancy"];
                // $room["max_occupancy"]=0;
                $room_details[$key]["inv"] = $data;
                $room_type_n_rate_plans = MasterHotelRatePlan::join('rate_plan_table as a', function ($join) {
                    $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                })
                    ->select('a.rate_plan_id', 'plan_type', 'plan_name', 'room_rate_plan.bar_price')
                    ->where('room_rate_plan.hotel_id', $hotel_id)
                    ->where('room_rate_plan.is_trash', 0)
                    ->where('room_rate_plan.be_rate_status', 0)
                    ->where('room_rate_plan.room_type_id', $room['room_type_id'])
                    ->orderBy('room_rate_plan.bar_price', 'ASC')
                    ->distinct()
                    ->get();
                $room['min_room_price'] = 0;
                $room_details[$key]["min_room_price"] = 0;
                $rateplan_details = array();

                foreach ($room_type_n_rate_plans as $key1 => $all_types) {
                    $rate_plan_id = (int)$all_types['rate_plan_id'];
                    $room_rate_status = $this->checkroomrateplan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    if ($room_rate_status) {
                        $data = $this->invService->getRatesByRoomnRatePlan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    } else {
                        if ($hotel_id == 2142 || $hotel_id == 2143 || $hotel_id == 2267) {
                            unset($room_type_n_rate_plans[$key1]);
                            continue;
                        } else {
                            continue;
                        }
                    }
                    //$data=$this->invService->getRatesByRoomnRatePlan($room['room_type_id'],$rate_plan_id,$date_from, $date_to);
                    $all_types['bar_price'] = 0;
                    $rateplan_details[$key1]['bar_price'] = 0;
                    $rate_data = array();
                    $rateplan_details[$key1]['plan_name'] = $all_types["plan_name"];
                    $rateplan_details[$key1]['plan_type'] = $all_types["plan_type"];
                    $rateplan_details[$key1]['rate_plan_id'] = $all_types["rate_plan_id"];

                    if ($data) {
                        foreach ($data as $key2 => $rate) {
                            if ($rate['block_status'] == 1) {
                                continue;
                            }
                            $cur_value = array();
                            $currency_value = array();
                            $multiple_occ = array();
                            $i = 0;
                            $j = 0;

                            if (isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"]) == 0) {
                                $rate["multiple_occupancy"][0] = $all_types['bar_price'];
                            }
                            if (isset($rate["multiple_occupancy"]) && $rate["multiple_occupancy"] != "" && sizeof($rate["multiple_occupancy"]) > 0) {
                                foreach ($rate["multiple_occupancy"] as $value) {
                                    $multiple_occ[$i] = $this->getPriceDetails($rate, $value, $all_types['bar_price'], $diff, $diff1);
                                    $i++;
                                }
                                foreach ($multiple_occ as $mult) {
                                    $cur_value[$j] = round($this->curency->currencyDetails($mult, $currency_name, $baseCurrency), 2);
                                    $j++;
                                }
                                $data[$key2]["multiple_occupancy"] = $multiple_occ;
                                if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                                    $rate_data[$key2]["multiple_occupancy"] = $multiple_occ;
                                } else {
                                    $rate_data[$key2]["multiple_occupancy"] = $cur_value; //otherwise converted
                                }
                            }
                            $all_types['bar_price'] = $this->getPriceDetails($rate, $rate['bar_price'], $all_types['bar_price'], $diff, $diff1);
                            if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                                $rateplan_details[$key1]['bar_price'] = $all_types['bar_price'];
                            } else {
                                $rateplan_details[$key1]['bar_price'] = round($this->curency->currencyDetails($all_types['bar_price'], $currency_name, $baseCurrency), 2);
                            }
                            $data[$key2]['bar_price'] = $all_types['bar_price'];
                            $rate_data[$key2]['bar_price'] = $rateplan_details[$key1]['bar_price'];
                            $rate_data['before_days_offer'] = $rate["before_days_offer"];
                            $rate_data['bookingjini_price'] = $rate["bookingjini_price"];
                            $rate_data['currency'] = $rate["currency"];
                            $rate_data['date'] = $rate["date"];
                            $rate_data['day'] = $rate["day"];
                            $rate_data['extra_adult_price'] = $rate["extra_adult_price"];
                            $rate_data['extra_child_price'] = $rate["extra_child_price"];
                            $rate_data['hex_code'] = $rate["hex_code"];
                            $rate_data['lastminute_offer'] = $rate["lastminute_offer"];
                            $rate_data['rate_plan_id'] = $rate["rate_plan_id"];
                            $rate_data['room_type_id'] = $rate["room_type_id"];
                            $rate_data['stay_duration_offer'] = $rate["stay_duration_offer"];
                        }
                    }

                    if ($room['min_room_price'] == 0) {
                        $room['min_room_price'] = $all_types['bar_price'];
                        $room_details[$key]['min_room_price'] = $rateplan_details[$key1]['bar_price'];
                    }
                    if ($all_types['bar_price'] <= $room['min_room_price'] && $all_types['bar_price'] != null) {
                        $room['min_room_price']  = $all_types['bar_price'];
                        $room_details[$key]['min_room_price'] = $rateplan_details[$key1]['bar_price'];
                    }
                    if ($all_types['bar_price'] != 0 || $all_types['bar_price'] != null) {
                        $all_types['rates'] = $data;
                        $rateplan_details[$key1]['rates'] = $rate_data;
                    }
                    if ($all_types['bar_price'] == 0 || $all_types['bar_price'] == NULL) {
                        unset($room_type_n_rate_plans[$key1]);
                        // unset($rateplan_details[$key1]);
                    }
                    $rateplan_details = array_values($rateplan_details);
                }
                if ($room['min_room_price'] != 0 &&  $room['min_room_price'] != null) {
                    $room['rate_plans'] = $room_type_n_rate_plans;
                    $room_details[$key]['rate_plans'] = $rateplan_details;
                }
            }
            $res = array('status' => 1, 'message' => "Hotel inventory retrieved successfully ", 'data' => $room_types, 'room_data' => $room_details);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
    }
    public function getInvByHotel(string $api_key, int $hotel_id, string $date_from, string $date_to, string $currency_name, Request $request)
    {
        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 1, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime($date_to));
        $today = date('Y-m-d');

        $roomType = new MasterRoomType();
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0, 'be_room_status' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'max_room_capacity', 'image', 'rack_price', 'extra_person', 'extra_child', 'bed_type', 'room_amenities', 'max_occupancy', 'max_infant')->where($conditions)->orderBy('rack_price', 'ASC')->get();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $room_details = array();
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency; //Get base currency
        $room_type_n_rate_plans = array();

        if ($room_types) {
            foreach ($room_types as $key => $room) {
                $room['min_inv'] = 0;
                if ($baseCurrency == $currency_name) {
                    $room_details[$key]["rack_price"] = $room["rack_price"];
                } else {
                    $room_details[$key]["rack_price"] = round($this->curency->currencyDetails($room["rack_price"], $currency_name, $baseCurrency), 2);
                }
                $room['room_amenities'] = explode(',', $room['room_amenities']);
                $room['room_amenities'] = $this->getRoomamenity($room['room_amenities']);
                $room_details[$key]["room_amenities"] = $room['room_amenities'];

                $room_details[$key]["min_inv"] = 0;
                $room_details[$key]["extra_child"] = $room["extra_child"];
                $room_details[$key]["extra_person"] = $room["extra_person"];
                $room_details[$key]["max_child"] = $room["max_child"];
                $room_details[$key]["max_infant"] = $room["max_infant"];
                $room_details[$key]["max_people"] = $room["max_people"];
                $room_details[$key]["room_type"] = $room["room_type"];
                $room_details[$key]["room_type_id"] = $room["room_type_id"];
                $room_details[$key]["max_room_capacity"] = $room["max_room_capacity"];
                $room['image'] = trim($room['image'], ',');
                $room['image'] = explode(',', $room['image']); //Converting string to array
                $room_types[$key]['allImages'] = $this->getImages($room->image); //--------- get all the images of rooms 
                $room['image'] = $this->getImages(array($room['image'][0])); //Getting actual amenity names
                if (is_object($room['image']) && sizeof($room['image']) > 0) {
                    $room['image'] = $room['image'][0]->image_name;
                    $room_details[$key]["image"] = $room['image'];
                } else {
                    $room['image'] = $this->getImages(array(1));
                    $room['image'] = $room['image'][0]->image_name;
                    $room_details[$key]["image"] = $room['image'];
                }
                foreach ($date1 as $d) {
                    $from_date = date('Y-m-d', strtotime($d));
                    break;
                }
                foreach ($date2 as $d1) {
                    $to_date = date('Y-m-d', strtotime($d1));
                    break;
                }
                $resp = $this->getAllPublicCupons($hotel_id, $from_date, $to_date, $request);
                $resp = json_decode($resp->getContent(), true);
                $room['coupons'] = array();
                if (isset($resp['data'])) {
                    foreach ($resp['data'] as $coupons) {
                        if (($coupons['room_type_id'] === $room["room_type_id"] || $coupons['room_type_id'] === 0) && (strtotime($from_date) >= strtotime($coupons['valid_from']))) {
                            $room['coupons'] = array($coupons);
                        }
                    }
                }
                $room_details[$key]["coupons"] = $room['coupons'];
                $data = $this->invService->getInventeryByRoomTYpe($room['room_type_id'], $date_from, $date_to, 0);
                //changes if from date and to date is same and min inv is coming 0
                if (!isset($data[0]['no_of_rooms'])) {
                    if ($date_from < $today) {
                        $room['min_inv']  = 1;
                        $room_details[$key]["min_inv"] = 1;
                    }
                } else {
                    $room['min_inv']  = $data[0]['no_of_rooms'];
                    $room_details[$key]["min_inv"] = $data[0]['no_of_rooms'];
                }
                foreach ($data as $inv_room) {
                    if ($inv_room['no_of_rooms'] < $room['min_inv']) {
                        $room['min_inv']  = $inv_room['no_of_rooms'];
                        $room_details[$key]["min_inv"] = $inv_room['no_of_rooms'];
                    }
                }
                $room['inv'] = $data;
                $room["max_occupancy"] = $room["max_occupancy"];
                // $room["max_occupancy"]=0;
                $room_details[$key]["inv"] = $data;
                $room_type_n_rate_plans = MasterHotelRatePlan::join('rate_plan_table as a', function ($join) {
                    $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                })
                    ->select('a.rate_plan_id', 'a.sl_no', 'plan_type', 'plan_name', 'room_rate_plan.bar_price')
                    ->where('room_rate_plan.hotel_id', $hotel_id)
                    ->where('room_rate_plan.is_trash', 0)
                    ->where('room_rate_plan.be_rate_status', 0)
                    ->where('room_rate_plan.room_type_id', $room['room_type_id'])
                    ->orderByRaw("a.sl_no ASC, room_rate_plan.bar_price ASC, room_rate_plan.room_rate_plan_id ")
                    ->distinct()
                    ->get();
                $room['min_room_price'] = 0;
                $room_details[$key]["min_room_price"] = 0;
                $rateplan_details = array();

                foreach ($room_type_n_rate_plans as $key1 => $all_types) {
                    $rate_plan_id = (int)$all_types['rate_plan_id'];
                    $room_rate_status = $this->checkroomrateplan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    if ($room_rate_status) {
                        $data = $this->invService->getRatesByRoomnRatePlan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    } else {
                        if ($hotel_id == 2142 || $hotel_id == 2143 || $hotel_id == 2267) {
                            unset($room_type_n_rate_plans[$key1]);
                            continue;
                        } else {
                            continue;
                        }
                    }
                    //$data=$this->invService->getRatesByRoomnRatePlan($room['room_type_id'],$rate_plan_id,$date_from, $date_to);
                    $all_types['bar_price'] = 0;
                    $rateplan_details[$key1]['bar_price'] = 0;
                    $rate_data = array();
                    $rateplan_details[$key1]['plan_name'] = $all_types["plan_name"];
                    $rateplan_details[$key1]['plan_type'] = $all_types["plan_type"];
                    $rateplan_details[$key1]['rate_plan_id'] = $all_types["rate_plan_id"];

                    if ($data) {
                        // if ($hotel_id == 1953) {
                        $check_bar_price = array_search(0, array_column($data, 'bar_price'));
                        $check_block_status = array_search(1, array_column($data, 'block_status'));
                        if ($check_bar_price === false && $check_block_status === false) {
                            foreach ($data as $key2 => $rate) {
                                $cur_value = array();
                                $currency_value = array();
                                $multiple_occ = array();
                                $i = 0;
                                $j = 0;

                                if (isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"]) == 0) {
                                    $rate["multiple_occupancy"][0] = $all_types['bar_price'];
                                }
                                if (isset($rate["multiple_occupancy"]) && $rate["multiple_occupancy"] != "" && sizeof($rate["multiple_occupancy"]) > 0) {
                                    foreach ($rate["multiple_occupancy"] as $value) {
                                        $multiple_occ[$i] = $this->getPriceDetails($rate, $value, $all_types['bar_price'], $diff, $diff1);
                                        $i++;
                                    }
                                    foreach ($multiple_occ as $mult) {
                                        $cur_value[$j] = round($this->curency->currencyDetails($mult, $currency_name, $baseCurrency), 2);
                                        $j++;
                                    }

                                    //  if ($hotel_id == 1953 || $hotel_id == 4502) {
                                    $max_occupancy = $room["max_people"] - 1;
                                    while (sizeof($multiple_occ) < $max_occupancy) {
                                        $multiple_occ[sizeof($multiple_occ)] = (string)$rate['bar_price'];
                                    }
                                    //  }
                                    $data[$key2]["multiple_occupancy"] = $multiple_occ;
                                    if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                                        $rate_data[$key2]["multiple_occupancy"] = $multiple_occ;
                                    } else {
                                        $rate_data[$key2]["multiple_occupancy"] = $cur_value; //otherwise converted
                                    }
                                }
                                $all_types['bar_price'] = $this->getPriceDetails($rate, $rate['bar_price'], $all_types['bar_price'], $diff, $diff1);
                                if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                                    $rateplan_details[$key1]['bar_price'] = $all_types['bar_price'];
                                } else {
                                    $rateplan_details[$key1]['bar_price'] = round($this->curency->currencyDetails($all_types['bar_price'], $currency_name, $baseCurrency), 2);
                                }
                                $data[$key2]['bar_price'] = $all_types['bar_price'];
                                $rate_data[$key2]['bar_price'] = $rateplan_details[$key1]['bar_price'];
                                $rate_data['before_days_offer'] = $rate["before_days_offer"];
                                $rate_data['bookingjini_price'] = $rate["bookingjini_price"];
                                $rate_data['currency'] = $rate["currency"];
                                $rate_data['date'] = $rate["date"];
                                $rate_data['day'] = $rate["day"];
                                $rate_data['extra_adult_price'] = $rate["extra_adult_price"];
                                $rate_data['extra_child_price'] = $rate["extra_child_price"];
                                $rate_data['hex_code'] = $rate["hex_code"];
                                $rate_data['lastminute_offer'] = $rate["lastminute_offer"];
                                $rate_data['rate_plan_id'] = $rate["rate_plan_id"];
                                $rate_data['room_type_id'] = $rate["room_type_id"];
                                $rate_data['stay_duration_offer'] = $rate["stay_duration_offer"];
                            }
                        } else {
                            $all_types['bar_price'] = 0;
                        }
                        // } else {
                        //     foreach ($data as $key2 => $rate) {
                        //         $cur_value = array();
                        //         $currency_value = array();
                        //         $multiple_occ = array();
                        //         $i = 0;
                        //         $j = 0;

                        //         if (isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"]) == 0) {
                        //             $rate["multiple_occupancy"][0] = $all_types['bar_price'];
                        //         }
                        //         if (isset($rate["multiple_occupancy"]) && $rate["multiple_occupancy"] != "" && sizeof($rate["multiple_occupancy"]) > 0) {
                        //             foreach ($rate["multiple_occupancy"] as $value) {
                        //                 $multiple_occ[$i] = $this->getPriceDetails($rate, $value, $all_types['bar_price'], $diff, $diff1);
                        //                 $i++;
                        //             }
                        //             foreach ($multiple_occ as $mult) {
                        //                 $cur_value[$j] = round($this->curency->currencyDetails($mult, $currency_name, $baseCurrency), 2);
                        //                 $j++;
                        //             }
                        //             $data[$key2]["multiple_occupancy"] = $multiple_occ;
                        //             if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                        //                 $rate_data[$key2]["multiple_occupancy"] = $multiple_occ;
                        //             } else {
                        //                 $rate_data[$key2]["multiple_occupancy"] = $cur_value; //otherwise converted
                        //             }
                        //         }
                        //         $all_types['bar_price'] = $this->getPriceDetails($rate, $rate['bar_price'], $all_types['bar_price'], $diff, $diff1);
                        //         if ($baseCurrency == $currency_name) { //Same as base currency than not convert
                        //             $rateplan_details[$key1]['bar_price'] = $all_types['bar_price'];
                        //         } else {
                        //             $rateplan_details[$key1]['bar_price'] = round($this->curency->currencyDetails($all_types['bar_price'], $currency_name, $baseCurrency), 2);
                        //         }
                        //         $data[$key2]['bar_price'] = $all_types['bar_price'];
                        //         $rate_data[$key2]['bar_price'] = $rateplan_details[$key1]['bar_price'];
                        //         $rate_data['before_days_offer'] = $rate["before_days_offer"];
                        //         $rate_data['bookingjini_price'] = $rate["bookingjini_price"];
                        //         $rate_data['currency'] = $rate["currency"];
                        //         $rate_data['date'] = $rate["date"];
                        //         $rate_data['day'] = $rate["day"];
                        //         $rate_data['extra_adult_price'] = $rate["extra_adult_price"];
                        //         $rate_data['extra_child_price'] = $rate["extra_child_price"];
                        //         $rate_data['hex_code'] = $rate["hex_code"];
                        //         $rate_data['lastminute_offer'] = $rate["lastminute_offer"];
                        //         $rate_data['rate_plan_id'] = $rate["rate_plan_id"];
                        //         $rate_data['room_type_id'] = $rate["room_type_id"];
                        //         $rate_data['stay_duration_offer'] = $rate["stay_duration_offer"];
                        //     }
                        // }
                    }
                    if ($room['min_room_price'] == 0) {
                        $room['min_room_price'] = $all_types['bar_price'];
                        $room_details[$key]['min_room_price'] = $rateplan_details[$key1]['bar_price'];
                    }
                    if ($all_types['bar_price'] <= $room['min_room_price'] && $all_types['bar_price'] != null) {
                        $room['min_room_price']  = $all_types['bar_price'];
                        $room_details[$key]['min_room_price'] = $rateplan_details[$key1]['bar_price'];
                    }
                    if ($all_types['bar_price'] != 0 || $all_types['bar_price'] != null) {
                        $all_types['rates'] = $data;
                        $rateplan_details[$key1]['rates'] = $rate_data;
                    }
                    if ($all_types['bar_price'] == 0 || $all_types['bar_price'] == NULL) {
                        unset($room_type_n_rate_plans[$key1]);
                        // if($hotel_id == 2600){
                        // $room_type_n_rate = json_decode(json_encode($room_type_n_rate_plans),true);
                        // $room_type_n_rate_plans = array_values($room_type_n_rate);
                        // }
                    }



                    $rateplan_details = array_values($rateplan_details);
                }

                $room_type_n_rate = json_decode(json_encode($room_type_n_rate_plans), true);
                $room_type_n_rate_plans = array_values($room_type_n_rate);

                if ($room['min_room_price'] != 0 &&  $room['min_room_price'] != null) {
                    $room['rate_plans'] = $room_type_n_rate_plans;
                    $room_details[$key]['rate_plans'] = $rateplan_details;
                }
            }

            // if ($hotel_id == 2600 || $hotel_id == 1953) {
            $available_room = [];
            $not_available_room = [];
            $available_room_details = [];
            $not_available_room_details = [];
            $check_inv_block_status = [];

            foreach ($room_details as $room_detail) {
                $new_room_details[$room_detail['room_type_id']] = $room_detail;
            }

            foreach ($room_types as $room) {
                $inventories = $room->inv;
                $check_inv_block_status = [];
                $check_no_of_rooms = [];
                foreach ($inventories as $inv) {
                    $check_inv_block_status[] = $inv['block_status'];
                    $check_no_of_rooms[] = $inv['no_of_rooms'];
                }
                if (in_array('1', $check_inv_block_status)) {
                    $not_available_room[] = $room;
                    $not_available_room_details[] = $new_room_details[$room['room_type_id']];
                } else {
                    if (in_array('0', $check_no_of_rooms)) {
                        $not_available_room[] = $room;
                        $not_available_room_details[] = $new_room_details[$room['room_type_id']];
                    } else {
                        $min_room_price = $room->min_room_price;
                        if ($min_room_price == 0) {
                            $not_available_room[] = $room;
                            $not_available_room_details[] = $new_room_details[$room['room_type_id']];
                        } else {
                            if (isset($room->rate_plans)) {
                                $available_room[] = $room;
                                $available_room_details[] = $new_room_details[$room['room_type_id']];
                            } else {
                                $not_available_room[] = $room;
                                $not_available_room_details[] = $new_room_details[$room['room_type_id']];
                            }
                        }
                    }
                }
            }

            usort($available_room, array($this, 'compareByPrice'));
            usort($available_room_details, array($this, 'compareByPrice'));
            $room_types = array_merge($available_room, $not_available_room);
            $room_details = array_merge($available_room_details, $not_available_room_details);
            // }

            $res = array('status' => 1, 'message' => "Hotel inventory retrieved successfully ", 'data' => $room_types, 'room_data' => $room_details);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
    }

    function compareByPrice($a, $b)
    {
        return $a['min_room_price'] - $b['min_room_price'];
    }

    /*
 *multiple occupancy and barprice
 */
    public function getPriceDetails($rate, $pricevalue, $alltypeprice, $diff, $diff1)
    {
        /*if($pricevalue>=$alltypeprice)
    {*/
        $before_days_offr = $rate['before_days_offer'];
        $stayduration_offr = $rate['stay_duration_offer'];
        $lastminut_offr = $rate['lastminute_offer'];

        if ($before_days_offr != 'no' && $before_days_offr != "") {
            $bdf = explode(',', $before_days_offr);
            $before_days = $bdf[0];
            $before_days_offr = $bdf[1];
        } else {
            $before_days = 0;
            $before_days_offr = $pricevalue;
        }
        if ($stayduration_offr != 'no' && $stayduration_offr != "") {
            $sdf = explode(',', $stayduration_offr);
            $stayduration = $sdf[0];
            $stayduration_offr = $sdf[1];
        } else {
            $stayduration = 0;
            $stayduration_offr = $pricevalue;
        }
        if ($lastminut_offr != 'no' && $lastminut_offr != "") {
            $ldf = explode(',', $lastminut_offr);
            $lastminut = $ldf[0];
            $lastminut_offr = $ldf[1];
        } else {
            $lastminut = -1;
            $lastminut_offr = $pricevalue;
        }
        if ($diff1 >= $before_days) {
            $price1 = $before_days_offr;
        } else {
            $price1 = $pricevalue;
        }
        if ($diff >= $stayduration) {
            $price2 = $stayduration_offr;
        } else {
            $price2 = $pricevalue;
        }
        if ($diff1 <= $lastminut) {
            $price3 = $lastminut_offr;
        } else {
            $price3 = $pricevalue;
        }
        if (($price1 < $price2) && ($price1 < $price3)) {
            $alltypeprice = $price1;
        } else if ($price2 < $price3) {
            $alltypeprice = $price2;
        } else {
            $alltypeprice = $price3;
        }
        if ($rate['bookingjini_price']) {
            $alltypeprice = round($alltypeprice - ($alltypeprice * $rate['bookingjini_price']) / 100); //Bookingjini Price
        }
        return  $alltypeprice;
        //}
    }
    /*
 * Get Room description and picture and other information of the hotel By Hotel id
 * @auther Godti Vinod
 * function getInvByHotel for fetching data
**/
    public function getRoomDetails(string $api_key, int $hotel_id, int $room_type_id, Request $request)
    {
        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 1, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $roomType = new MasterRoomType();
        $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
        $room_types = MasterRoomType::select('room_type_id', 'room_type', 'description', 'image', 'room_amenities', 'bed_type', 'room_size_value', 'room_size_unit', 'room_view_type', 'total_rooms')->where($conditions)->first();
        $room_types->image = explode(',', $room_types->image); //Converting string to array
        $room_types->image = $this->getImages($room_types->image); //Getting actual amenity names
        $room_types->room_amenities = explode(',', $room_types->room_amenities);
        $room_types->room_amenities = $this->getRoomamenity($room_types->room_amenities);
        if ($room_types) {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieved successfully ", 'data' => $room_types);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
    }
    /*
 * Get Room description and picture and other information of the hotel By Hotel id
 * @auther Godti Vinod
 * function getInvByHotel for fetching data
    **/
    public function getHotelDetails(string $api_key, int $hotel_id, Request $request)
    {
        //$hotel=new HotelInformation();
        $cond = array('api_key' => $api_key);
        $comp_info = CompanyDetails::select('company_id', 'banner', 'logo', 'home_url')
            ->where($cond)->first();

        if (!$comp_info['company_id']) {
            $res = array('status' => 1, 'message' => "Invalid hotel or company");
            return response()->json($res);
        }

        $conditions = array('hotel_id' => $hotel_id, 'company_id' => $comp_info['company_id']);
        $info = HotelInformation::select('hotel_name', 'hotel_description', 'company_id', 'hotel_address', 'child_policy', 'cancel_policy', 'terms_and_cond', 'hotel_policy', 'facility', 'airport_name', 'distance_from_air', 'rail_station_name', 'distance_from_rail', 'land_line', 'star_of_property', 'nearest_tourist_places', 'tour_info', 'check_in', 'check_out', 'exterior_image', 'latitude', 'longitude', 'facebook_link', 'twitter_link', 'linked_in_link', 'instagram_link', 'whatsapp_no', 'tripadvisor_link', 'holiday_iq_link', 'prepaid', 'partial_payment', 'partial_pay_amt', 'pay_at_hotel', 'email_id', 'mobile', 'advance_booking_days', 'is_overseas', 'bus_station_name', 'distance_from_bus', 'country_id', 'round_clock_check_in_out', 'is_taxable', 'facebook_pixel', 'google_tag_manager', 'google_analytics', 'other_info')->where($conditions)->first();

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

        //get logo and banner
        $info->logo = $this->getImages(array($comp_info->logo));
        $info->logo = $info->logo[0]->image_name;
        $info->home_url = $comp_info->home_url;

        $info->rating = [];
        $info->guest_local_id = 0;
        $info->unmarried_couple = 0;

        if ($info->other_info != '') {
            $other_info_details = json_decode($info->other_info);
            $info->rating = $other_info_details->rating;
            $info->guest_local_id = $other_info_details->guest_local_id;
            // $info->unmarried_couple = $other_info_details->unmarried_couple;
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

        if ($info->round_clock_check_in_out == NULL || $info->round_clock_check_in_out == Null || $info->round_clock_check_in_out == null || $info->round_clock_check_in_out == 'NULL' || $info->round_clock_check_in_out == 'Null' || $info->round_clock_check_in_out == 'null') {
            $info->round_clock_check_in_out = 0;
        }

        $today_date = date('Y-m-d');
        $package_details = DB::table('kernel.package_table')->where('hotel_id', $hotel_id)->where('date_to', '>', $today_date)->where('is_trash', 0)->first();
        if ($package_details) {
            $info->package_available = 1;
        } else {
            $info->package_available = 0;
        }

        $pg_details = DB::table('paymentgateway_details')
            ->select('provider_name')
            ->where('hotel_id', '=', $hotel_id)
            ->where('is_active', '=', 1)
            ->first();
        if (!$pg_details) {
            $pg_details = PaymentGetway::select('provider_name')
                ->where('company_id', '=', $info->company_id)
                ->first();
        }
        if (!$pg_details) {
            $payment_provider_name = 'easebuzz';
        } else {
            $payment_provider_name = $pg_details->provider_name;
        }

        if ($payment_provider_name) {
            $onpage_url_details = DB::table('payment_gateways')->where('pg_name', $payment_provider_name)->first();
        }

        $info->payment_provider = $payment_provider_name;
        $info->onpage_url = $onpage_url_details->onpage_url;


        $info->note_text = 'Note';
        if ($hotel_id == 2600) {
            $info->note_text = 'Account Reference';
        }

        $info->is_note_mandatory = 0;
        if ($hotel_id == 2600) {
            $info->is_note_mandatory = 1;
        }

        if ($info) {
            $res = array('status' => 1, 'message' => "Hotel description successfully ", 'data' => $info);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "Invalid hotel or company");
            return response()->json($res);
        }
    }


    /*
    Get HOTEL AMENITIES of the hotel By Hotel id
    * @auther Godti Vinod
    * function getHotelAmen for fetching data
       **/
    public function getHotelAmen($amen)
    {
        $amenities = HotelAmenities::whereIn('hotel_amenities_id', $amen)
            ->select([
                'hotel_amenities_name',
                'bootstrap_class as icons',
                DB::raw('COALESCE(bootstrap_class, "jini-star") as icons')
            ])
            ->get();
        if ($amenities) {
            return $amenities;
        } else {
            return array();
        }
    }
    /*
    Get HOTEL Images  of the hotel By Hotel id
    * @auther Godti Vinod
    * function getHotelAmen for fetching data
       **/
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
    public function getBannerImages($imgs)
    {
        $banner_ids = explode(",", $imgs);
        $images = ImageTable::whereIn('image_id',  $banner_ids)
            ->select('image_name')
            ->get();
        if (sizeof($images) != 0) {
            return $images;
        } else {
            $images = ImageTable::where('image_id',  69214)
                ->select('image_name')
                ->get();
            return $images;
        }
    }
    /* Get Room description and picture and other information of the hotel By Hotel id
      * @auther Godti Vinod
      * function getInvByHotel for fetching data
         **/
    public function getHotelLogo(string $api_key, int $company_id, Request $request)
    {

        // try {
        //     $pdo = DB::connection()->getPdo();
        //     if ($pdo) {
        //         echo "Connected to the database!";
        //     }
        // } catch (\Exception $e) {
        //     echo "Failed to connect to the database: " . $e->getMessage();
        // }

        // dd('here');
        $conditions = array('company_id' => $company_id);
        $info = CompanyDetails::select('banner', 'logo', 'home_url')
            ->where($conditions)->first();
        //  dd($info);
        if (!$info) {
            $res = array('status' => 0, 'message' => "Invalid company credentials");
            return response()->json($res);
        }
        $info->logo = $this->getImages(array($info->logo));
        $info->logo = $info->logo[0]->image_name;

        $info->logo = $info->logo;
        $info->banner = $this->getBannerImages($info->banner);
        $info->banner = $info->banner;
        foreach ($info->banner as $key => $value) {
            $info->banner[$key]->image_name =  $value->image_name;
        }

        if ($info) {
            $res = array('status' => 1, 'message' => "Company log and banners retrieved successfully", 'data' => $info);
            return response()->json($res);
        }
    }


    //Bookings save starts here
    public function bookings(string $api_key, Request $request)
    {
        $logpath = storage_path("logs/cart.log" . date("Y-m-d"));
        $logfile = fopen($logpath, "a+");
        fwrite($logfile, "Processing starts at: " . date("Y-m-d H:i:s") . "\n");
        fclose($logfile);
        $reference = $request->input('reference');
        $date1 = date_create($request->input('from_date'));
        $date2 = date_create($request->input('to_date'));
        $today = date('Y-m-d');
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        $hotel_id = $request->input('hotel_id');
        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 0, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $percentage = 0; //THis shoud be set from Mail invoice
        $invoice = new Invoice();
        $booking = new HotelBooking();
        $failure_message = 'Booking failed due to unsuffcient booking details';
        $validator = Validator::make($request->all(), $this->rules, $this->messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
        }
        $cart = $request->input('cart');
        $cart_info = json_encode($cart);
        $logfile = fopen($logpath, "a+");
        fwrite($logfile, "cart data: " . $hotel_id . $cart_info . "\n");
        fwrite($logfile, "Processing ends at: " . date("Y-m-d H:i:s") . "\n");
        fclose($logfile);
        $refund_protect_data = $request->input('refund_protect_data');
        $data =  $request->all();
        if (isset($data['addon_charges_name']) && $data['addon_charges_name'] != '') {
            $addon_charges_name = $data['addon_charges_name'];
        } else {
            $addon_charges_name = 'NA';
        }

        if (isset($data['total_addon_price']) && $data['total_addon_price'] != 0) {
            $total_addon_price = $data['total_addon_price'];
        } else {
            $total_addon_price = 0;
        }
        if (isset($data['total_gst_addon_price']) && $data['total_gst_addon_price'] != 0) {
            $total_gst_addon_price = $data['total_gst_addon_price'];
        } else {
            $total_gst_addon_price = 0;
        }

        if (isset($data['company_name'])) {
            $company_name = $data['company_name'];
        } else {
            $company_name = '';
        }

        if (isset($data['GSTIN'])) {
            $gstin = $data['GSTIN'];
        } else {
            $gstin = '';
        }

        if (isset($data['hash_key_value'])) {
            $hash_key_value = base64_decode($data['hash_key_value']);
            if ($hash_key_value != $hotel_id) {
                $res = array('status' => 0, 'message' => "Invalid hotel for booking");
                return response()->json($res);
            }
        }
        $admin_id = isset($data['admin_id']) ? $data['admin_id'] : 0;
        $source = isset($data['source']) ? $data['source'] : '';

        // $discount_per = isset($data['discount_percent'])?$data['discount_percent']:'null';
        foreach ($cart as $cartItem) {
            $partial_percentage = 0;
            $paid_amount = $cartItem["paid_amount"];
            $percentage = $cartItem["paid_amount_per"];
            if (isset($cartItem["partial_amount_per"])) {
                $partial_percentage = $cartItem["partial_amount_per"];
            }
        }

        $from_date = date('Y-m-d', strtotime($request->input('from_date')));
        $to_date = date('Y-m-d', strtotime($request->input('to_date')));

        if ($source  != 'GEMS') {
            if (($from_date < $today) || ($from_date > $to_date) || ($to_date < $today)) {
                $res = array('status' => 0, "message" => "Checkin date should not greater than checkout date");
                return response()->json($res);
            }
        } else {
            if ($diff <= 0) {
                $diff = 1;
            }
        }
        $coupon = $request->input('coupon');
        $paid_service = $request->input('paid_service');
        if ($request->input('guest_note')) {
            $guest_note = $request->input('guest_note');
        } else {
            $guest_note = 'NA';
        }
        if ($request->input('arrival_time')) {
            $arrival_time = $request->input('arrival_time');
        } else {
            $arrival_time = 'NA';
        }

        $visitors_ip = $this->ipService->getIPAddress();
        $chkPrice = false;
        $chkPaidService = false;
        //to get partial payment status
        $payment_status = HotelInformation::select('partial_payment', 'partial_pay_amt', 'is_taxable')->where('hotel_id', $hotel_id)->first();
        // Check room price && Check Qty && Check Extra adult prce && Check Extra child price && Check GSt && Discounted Price
        //Check Paid services
        if ($paid_service) {
            $chkPaidService = $this->checkPaidService($paid_service);
        } else {
            $chkPaidService = true;
        }
        if (isset($data['type']) && $data['type'] == 'website') {
            $type = $data['type'];
        } else {
            $type = 'NA';
        }
        if (($hotel_id == 2065 && $type == 'website') || ($hotel_id == 2881 && $type == 'website') || ($hotel_id == 2882 && $type == 'website') || ($hotel_id == 2883 && $type == 'website') || ($hotel_id == 2884 && $type == 'website') || ($hotel_id == 2885 && $type == 'website') || ($hotel_id == 2886 && $type == 'website') || ($hotel_id == 2945 && $type == 'website') || ($hotel_id == 3438 && $type == 'website')) {
            $chkPrice = $this->checkRoomPrice($coupon, $cart, $from_date, $to_date, $hotel_id, $diff);

            if ($chkPrice && $chkPaidService) {
                $validCart = true;
            } else {
                $validCart = false;
            }
        } else {
            // $hotel_array = [3594,3578,3595,3618,3619];
            // if(in_array($hotel_id,$hotel_array)){
            //     $validCart = true;
            // }
            // else{
            if ($type == 'website') {
                $checkSum = $request->input('booking_reference');
                $check_in_date = $request->input('from_date');
                $check_out_date = $request->input('to_date');
                $checksum_string = "$check_in_date|$api_key|$hotel_id|$paid_amount|$check_out_date";
                $checksum_md5_string = md5($checksum_string);
                if ($checksum_md5_string == $checkSum && $chkPaidService) {
                    $validCart = true;
                } else {
                    $validCart = false;
                }
            } else {
                $validCart = true;
            }
            // }

        }
        if ($validCart) {
            $inv_data = array();
            $hotel = $this->getHotelInfo($hotel_id);
            $booking_data = array();
            $inv_data['check_in_out'] = "[" . $from_date . '-' . $to_date . "]";
            $inv_data['room_type']  = json_encode($this->prepareRoomType($cart));
            $inv_data['hotel_id']   = $hotel_id;
            $inv_data['hotel_name'] = $hotel->hotel_name;
            $inv_data['user_id']    = $request->auth->user_id;


            //01-11-22

            if (isset($refund_protect_data['refund_protect_price']) && $refund_protect_data['refund_protect_price'] > 0 && ($payment_status->is_taxable == 1)) {
                $inv_data['total_amount']  = number_format((float)$this->getTotal($cart, $paid_service, $hotel_id), 2, '.', '');
                $inv_data['total_amount']  = number_format((float)$inv_data['total_amount'] + $refund_protect_data['refund_protect_price'], 2, '.', '');
            } else {
                $inv_data['total_amount']  = number_format((float)$this->getTotal($cart, $paid_service, $hotel_id), 2, '.', '');
            }




            if ($payment_status->partial_payment == 1) {
                if ($partial_percentage) {
                    $partial_amount = $inv_data['total_amount'] * $partial_percentage / 100;
                } else if ($percentage == $payment_status->partial_pay_amt) {
                    $partial_amount = $inv_data['total_amount'] * $payment_status->partial_pay_amt / 100;
                } else {
                    $partial_amount = number_format((float)$inv_data['total_amount'], 2, '.', '');
                }
                if (round($partial_amount, 0) == round($paid_amount, 0)) {
                    $inv_data['paid_amount']   = number_format((float)$paid_amount, 2, '.', '');
                } else {
                    $inv_data['paid_amount'] = number_format((float)$paid_amount, 2, '.', '');
                }
            } else {
                if ($source  == 'GEMS' && $paid_amount >= 0) {
                    $inv_data['paid_amount'] = $paid_amount;
                } else {
                    $inv_data['paid_amount'] = number_format((float)$inv_data['total_amount'], 2, '.', '');
                    if ($payment_status->is_taxable == 0) {
                        $inv_data['paid_amount'] = number_format((float)$paid_amount, 2, '.', '');
                    }
                }
            }
            $inv_data['agent_code'] = isset($coupon[0]['applied_coupon']['coupon_code']) ? $coupon[0]['applied_coupon']['coupon_code'] : 'NA';
            $inv_data['paid_service_id'] = implode(",", $this->getPaidService($paid_service));
            $inv_data['extra_details'] = json_encode($this->getExtraDetails($cart));
            $inv_data['booking_date'] = date('Y-m-d H:i:s');
            $inv_data['visitors_ip'] = $visitors_ip;
            $inv_data['ref_no'] = rand() . strtotime("now");
            $inv_data['booking_status'] = 2; //Initially Booking status set 2 ,For the pending status
            $inv_data['booking_status'] = 2;


            //01-11-22


            // if($hotel_id==1953){
            $refund_protect_price_info = isset($refund_protect_data['refund_protect_price']) && $refund_protect_data['refund_protect_price'] > 0 ? $refund_protect_data['refund_protect_price'] : 0;
            // }else{
            //     $refund_protect_price_info = isset($refund_protect_data['refund_protect_price']) && $refund_protect_data['refund_protect_price'] > 0 && ($payment_status->is_taxable == 1) ? $refund_protect_data['refund_protect_price'] : 0;
            // }



            $offeringMethod = isset($refund_protect_data['offeringMethod']) ? $refund_protect_data['offeringMethod'] : 'NA';
            $rp_member_id = 0;
            if ($offeringMethod == 'OPT-IN') {
                $rp_member_id = 295;
            } else if ($offeringMethod == 'OPT-OUT') {
                $rp_member_id = 298;
            }
            if ($source  != 'GEMS') {
                $source_info = '';
            } else {
                $source_info = $source;
            }
            $refund_protect_sold_status = isset($refund_protect_data['sold']) ? $refund_protect_data['sold'] : '';


            $inv_data['invoice'] = $this->createInvoice($hotel_id, $cart, $coupon, $paid_service, $from_date, $to_date, $inv_data['user_id'], $percentage, $inv_data['ref_no'], $refund_protect_price_info, $rp_member_id, $source_info, $inv_data['paid_amount'], $addon_charges_name, $total_addon_price, $total_gst_addon_price, $guest_note, $arrival_time, $refund_protect_sold_status);
            //,$addon_charges_name,$total_addon_price,$total_gst_addon_price
            $booking_status = 'Commit';
            $is_ids = PmsAccount::where('name', 'IDS NEXT')->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')->first();
            if ($is_ids) {
                $inv_data['ids_re_id'] = $this->handleIds($cart, $from_date, $to_date, $inv_data['booking_date'], $hotel_id, $inv_data['user_id'], $booking_status);
            }
            $is_winhms = PmsAccount::where('name', 'WINHMS')->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')->first();
            if ($is_winhms) {
                $inv_data['winhms_re_id'] = $this->handleWimhms($cart, $from_date, $to_date, $inv_data['booking_date'], $hotel_id, $inv_data['user_id'], $booking_status);
            }
            // $inv_data['ktdc_id']=$this->handleKtdc($cart,$from_date,$to_date,$inv_data['booking_date'],$hotel_id,$inv_data['user_id'],$booking_status);

            $check_tax_price = $this->checkTaxPrice($cart, $payment_status);
            if ($check_tax_price == 0) {
                $res = array('status' => -1, "message" => "Booking failed due to GST data tempering,Please try again later");
                return response()->json($res);
            }

            $getInvInfo = $this->gstDiscountPrice($cart);

            if ($payment_status->is_taxable == 1) {
                $inv_data['tax_amount'] = $getInvInfo[0];
            } else {
                $inv_data['tax_amount'] = 0;
            }

            $inv_data['discount_amount'] = $getInvInfo[1];
            if (isset($reference)) {
                $inv_data['ref_from'] =  $reference;
            }
            $failure_message = "Invoice details saving failed";
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

            $inv_data['created_by'] = $admin_id;
            $inv_data['addon_charges_amount'] = $total_addon_price;
            $inv_data['addon_charges_tax'] = $total_gst_addon_price;
            if ($source) {
                $inv_data['booking_source'] = $source;
            } else {
                $inv_data['booking_source'] = "website";
            }
            $inv_data['guest_note'] = $guest_note;
            $inv_data['arrival_time'] = $arrival_time;
            $inv_data['company_name'] = $company_name;
            $inv_data['gstin'] = $gstin;

            // $inv_data['discount_code'] = $discount_per;

            /** end of is_cm code */
            if ($invoice->fill($inv_data)->save()) {

                $cur_invoice = Invoice::where('ref_no', $inv_data['ref_no'])->first();
                $invoice_id = $cur_invoice->invoice_id;

                if (isset($data['qpl_id'])) {
                    DB::table('quick_payment_link')->where('id', $data['qpl_id'])->update(['invoice_id' => $invoice_id]);
                }


                if ($refund_protect_data) {
                    if ($refund_protect_data['sold'] == 'true') {
                        $sold = "true";
                    } else {
                        $sold = "false";
                    }
                    $product_price_data = array(
                        'response_status'           => '',
                        'hotel_id'                  => $hotel_id,
                        'user_id'                   => $inv_data['user_id'],
                        'invoice_id'                => $invoice_id,
                        'sold'                      => $sold,
                        'product_price'             => $paid_amount,
                        // 'product_price'             => $refund_protect_data['productPrice'],
                        'product_code'              => $refund_protect_data['productCode'],
                        'currency_code'             => $refund_protect_data['currencyCode'],
                        'premium_rate'              => $refund_protect_data['premiumRate'],
                        'offering_method'           => $refund_protect_data['offeringMethod'],
                        'refundable_cancellation_mode'  => $refund_protect_data['refundable_cancelation_mode'],
                        'refundable_cancellation_status' => $refund_protect_data['refundable_cancelation_status'],
                        'refund_protect_price'          => $refund_protect_data['refund_protect_price']
                    );
                    $res = ProductPrice::create($product_price_data);
                }
                $booking_data = $this->prepare_booking_data($cart, $cur_invoice->invoice_id, $from_date, $to_date, $inv_data['user_id'], $inv_data['booking_date'], $hotel_id);
                if (HotelBooking::insert($booking_data)) {
                    if ($source  != 'GEMS') {
                        if ($this->preinvoiceMail($cur_invoice->invoice_id)) {
                            //Hashing starts
                            $user_data = $this->getUserDetails($inv_data['user_id']);
                            $b_invoice_id = base64_encode($cur_invoice->invoice_id);
                            $invoice_hashData = $cur_invoice->invoice_id . '|' . $cur_invoice->total_amount . '|' . $cur_invoice->paid_amount . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;

                            $invoice_secureHash = hash('sha512', $invoice_hashData);
                            $res = array("status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $cur_invoice->invoice_id, 'invoice_secureHash' => $invoice_secureHash);
                            return response()->json($res);
                        } else {
                            $res = array('status' => -1, "message" => $failure_message);
                            $res['errors'][] = "Internal server error";
                            return response()->json($res);
                        }
                    } else {
                        $user_data = $this->getUserDetails($inv_data['user_id']);
                        $b_invoice_id = base64_encode($cur_invoice->invoice_id);
                        $invoice_hashData = $cur_invoice->invoice_id . '|' . $cur_invoice->total_amount . '|' . $cur_invoice->paid_amount . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;

                        $invoice_secureHash = hash('sha512', $invoice_hashData);
                        $res = array("status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $cur_invoice->invoice_id, 'invoice_secureHash' => $invoice_secureHash);
                        return response()->json($res);
                    }
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
            } else {
                $res = array('status' => -1, "message" => $failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        } else {
            $res = array('status' => -1, "message" => "Booking failed due to data tempering,Please try again later");
            return response()->json($res);
        }
    }
    //Get Hotel info from the Hotel id
    public function getHotelInfo($hotel_id)
    {
        $hotel = HotelInformation::select('*')->where('hotel_id', $hotel_id)->first();
        return $hotel;
    }
    //Prepare the Room Types TO insert into database
    public function prepareRoomType($cart)
    {
        $room_types = array();
        foreach ($cart as $cartItem) {
            $room_type_id = $cartItem['room_type_id'];
            $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
            $room_type_array = MasterRoomType::select('room_type')->where($conditions)->first();
            $room_type = $room_type_array['room_type'];
            $plan_type = $cartItem['plan_type'];
            $rooms = $cartItem['rooms'];
            array_push($room_types, sizeof($rooms) . ' ' . $room_type . '(' . $plan_type . ')');
        }
        return $room_types;
    }
    //Get total price in the cart
    public function getTotal($cart, $paid_services, $hotel_id)
    {

        $check_taxable = HotelInformation::select('is_taxable')->where('hotel_id', $hotel_id)->first();
        $total_price = 0;
        foreach ($cart as $cartItem) {
            $total_extra_price = 0;
            foreach ($cartItem['rooms'] as $cart_room) {
                $total_extra_price += $cart_room['extra_child_price'] + $cart_room['extra_adult_price'];
            }

            if ($check_taxable->is_taxable == 1) {
                $total_price += $cartItem['price'] + $cartItem['tax'][0]['gst_price'] + $total_extra_price - $cartItem['discounted_price'];
                foreach ($cartItem['tax'][0]['other_tax'] as $other_tax) {
                    $total_price += $other_tax['tax_price'];
                }
            } else {
                $total_price += $cartItem['price'] + $total_extra_price - $cartItem['discounted_price'];
            }
        }

        $paid_service_price = 0;
        foreach ($paid_services as $paid_service) {
            $paid_service_price += $paid_service['price'];
        }
        $total_price = $total_price + $paid_service_price;
        $total_price = round($total_price, 2);
        return $total_price;
    }
    //Get the paid service Ids To insert into INVOICE Table
    public function getPaidService($paid_services)
    {
        $orig_paid_service = array();
        foreach ($paid_services as $paid_service) {
            array_push($orig_paid_service, $paid_service['service_no']);
        }
        return $orig_paid_service;
    }
    //Get the getExtra details such as Room Type id and Selected Child and Adults
    public function getExtraDetails($cart)
    {
        $extra_details = array();
        foreach ($cart as $cartItem) {
            foreach ($cartItem['rooms'] as $room) {
                array_push($extra_details, array($cartItem['room_type_id'] => array($room['selected_adult'], $room['selected_child'])));
            }
        }
        return $extra_details;
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
    //Prepare the booking table row to Insert
    public function prepare_booking_data($cart, int $invoice_id, $from_date, $to_date, $user_id, $booking_date, $hotel_id)
    {
        $booking_data = array();
        $booking_data_arr = array();
        foreach ($cart as $cartItem) {
            $booking_data['room_type_id'] = $cartItem['room_type_id'];
            $booking_data['rooms'] = sizeof($cartItem['rooms']);
            $booking_data['check_in'] = $from_date;
            $booking_data['check_out'] = $to_date;
            $booking_data['booking_status'] = 2; //Intially Un Paid
            $booking_data['user_id'] = $user_id;
            $booking_data['booking_date'] = $booking_date;
            $booking_data['invoice_id'] = $invoice_id;
            $booking_data['hotel_id'] = $hotel_id;
            array_push($booking_data_arr, $booking_data);
        }
        return $booking_data_arr;
    }


    /********============Pre Check Room Price,Qty,GST,Discounts===========********* */
    public function checkRoomPrice($coupon, $cart, $from_date, $to_date, $hotel_id, $diff)
    {
        $qty = 0;
        $chkQty = array(); //Initially chkQty set to false
        $chkRmRate = array();
        foreach ($cart as $key => $cartItem) {
            // $coupon_info = '';
            // if(isset($coupon)){
            //     foreach($coupon as $coup){
            //         if(sizeof($coupon) == 1){
            //             $coupon_info = $coupon[0];
            //         }else{
            //             $coupon_info = $coupon[$key];
            //         }

            //     }
            // }
            $coupon_info = '';
            if (isset($coupon)) {
                foreach ($coupon as $key1 => $coup) {
                    if (sizeof($coupon) == 1) {
                        $coupon_info = $coupon[0];
                    } else {
                        if ($coup['room_data']['room_type_id'] == $cartItem['room_type_id']) {
                            $coupon_info = $coupon[$key1];
                        } else if ($coup['room_data']['room_type_id'] == 0) {
                            $coupon_info = $coupon[$key1];
                        } else {
                        }
                    }
                }
            }


            $room_type_id = $cartItem['room_type_id'];
            $rooms = $cartItem['rooms'];
            $room_price = $cartItem['price'];
            $gst_price = $cartItem['tax'][0]['gst_price'];
            $other_tax_arr = $cartItem['tax'][0]['other_tax'];
            $discounted_price = $cartItem['discounted_price'];
            $qty = sizeof($rooms); //No of rooms is size of the rooms array
            array_push($chkQty, $this->CheckQty($room_type_id, $qty, $from_date, $to_date, $diff));
            array_push($chkRmRate, $this->CheckRoomRate($room_type_id, $rooms, $from_date, $to_date, $room_price, $coupon_info, $gst_price, $other_tax_arr, $discounted_price, $hotel_id));
        }

        $rmStatus = true;
        $qty_status = true;
        //Check all the room rates status
        foreach ($chkQty as $chk_qty) {
            if ($chk_qty != 1) {
                $qty_status = false;
            }
        }
        foreach ($chkRmRate as $chkRm) {
            if ($chkRm != 1) {
                $rmStatus = false;
            }
        }
        ///Check all the status
        if ($qty_status && $rmStatus) {
            return true;
        } else {
            return false;
        }
    }
    ///*************Pre check Paid service************************************/
    public function checkPaidService($paid_services)
    {
        $chk_paid_service = array();
        foreach ($paid_services as $paid_service) {
            $conditions = array('paid_service_id' => $paid_service['service_no'], 'is_trash' => 0);
            $orig_paidService = PaidServices::where($conditions)->first();
            if ($orig_paidService->service_amount == ($paid_service['price'])) {
                array_push($chk_paid_service, 1);
            }
        }
        return $chk_paid_service;
    }
    //*************Pre Check qunatity of the rooms*************************/
    public function CheckQty($room_type_id, $qty, $from_date, $to_date, $diff)
    {
        $min_inv = 0;
        $data = $this->invService->getInventeryByRoomTYpe($room_type_id, $from_date, $to_date, 0);
        foreach ($data as $inv_room) {
            if ($diff < $inv_room['los']) {
                return false;
            } else {
                if ($min_inv == 0) {
                    $min_inv = $inv_room['no_of_rooms'];
                }
                if ($inv_room['no_of_rooms'] <= $min_inv) {
                    $min_inv  = $inv_room['no_of_rooms'];
                }
            }
        }
        //Check qty
        if ($qty <= $min_inv) {
            return true;
        } else {
            return false;
        }
    }
    //*************Pre Check ROOM rate of the rooms*************************/
    public function CheckRoomRate($room_type_id, $rooms, $from_date, $to_date, $curr_room_price, $coupon, $curr_gst_price, $cur_other_tax, $discounted_price, $hotel_id)
    {
        $date1 = date_create($from_date);
        $date2 = date_create($to_date);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $room_price = 0;

        $extra_adult_ok = false;
        $extra_child_ok = false;
        $chk_disc_ok = false;
        $multiple_occ = array();
        $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'image', 'rack_price', 'extra_person', 'extra_child')->where($conditions)->first();
        $tot_extra_adult_price = 0;
        $tot_extra_child_price = 0;

        $max_adult = $room_types->max_people;
        $max_child = $room_types->max_child;
        $calculated_discounted_price = 0;
        $curr_discounted_price = 0;
        foreach ($rooms as $room) {

            $curr_extra_adult_price = $room['extra_adult_price'];
            $curr_extra_child_price = $room['extra_child_price'];
            $selected_adult = $room['selected_adult'];
            $selected_child = $room['selected_child'];
            $extra_adult_price = 0;
            $extra_child_price = 0;
            $room_rates = $this->invService->getRatesByRoomnRatePlan($room_type_id, $room['rate_plan_id'], $from_date, $to_date);
            foreach ($room_rates as $room_rate) {

                $j = 0;
                $before_days_offr = $room_rate['before_days_offer'];
                $stayduration_offr = $room_rate['stay_duration_offer'];
                $lastminut_offr = $room_rate['lastminute_offer'];
                if ($before_days_offr != 'no' && $before_days_offr != "") {
                    $bdf = explode(',', $before_days_offr);
                    $before_days = $bdf[0];
                    $before_days_offr = $bdf[1];
                } else {
                    $before_days = 0;
                    $before_days_offr = $room_rate['bar_price'];
                }
                if ($stayduration_offr != 'no' && $stayduration_offr != "") {
                    $sdf = explode(',', $stayduration_offr);
                    $stayduration = $sdf[0];
                    $stayduration_offr = $sdf[1];
                } else {
                    $stayduration = 0;
                    $stayduration_offr = $room_rate['bar_price'];
                }
                if ($lastminut_offr != 'no' && $lastminut_offr != "") {
                    $ldf = explode(',', $lastminut_offr);
                    $lastminut = $ldf[0];
                    $lastminut_offr = $ldf[1];
                } else {
                    $lastminut = -1;
                    $lastminut_offr = $room_rate['bar_price'];
                }
                if ($diff1 >= $before_days) {
                    $price1 = $before_days_offr;
                } else {
                    $price1 = $room_rate['bar_price'];
                }
                if ($diff >= $stayduration) {
                    $price2 = $stayduration_offr;
                } else {
                    $price2 = $room_rate['bar_price'];
                }
                if ($diff1 <= $lastminut) {
                    $price3 = $lastminut_offr;
                } else {
                    $price3 = $room_rate['bar_price'];
                }
                if (($price1 < $price2) && ($price1 < $price3)) {

                    $room_rate['bar_price'] = $price1;
                } else if ($price2 < $price3) {

                    $room_rate['bar_price'] = $price2;
                } else {

                    $room_rate['bar_price'] = $price3;
                }
                if ($room_rate['bookingjini_price']) {
                    $room_rate['bar_price'] = round($room_rate['bar_price'] - ($room_rate['bar_price'] * $room_rate['bookingjini_price']) / 100); //Bookingjini Price
                }
                $coupon_valid_from;
                // if($coupon && isset($coupon['applied_coupon']) &&$hotel_id==2885)
                // {
                //     if(isset($coupon[0])){
                //         foreach($coupon as $coup){
                //             $coupon_valid_from  = isset($coup->valid_from)?$coup->valid_from:$coup['valid_from'];
                //             $coupon_valid_to = isset($coup->valid_to)?$coup->valid_to:$coup['valid_to'];
                //          }
                //     }else{
                //         $coupon_valid_from  = isset($coupon['valid_from'])?$coupon['valid_from']:'';
                //         $coupon_valid_to = isset($coupon['valid_to'])?$coupon['valid_to']:'';
                //     }
                //     $coupon_date_array = $this->date_range($coupon_valid_from,$coupon_valid_to,"+1 day", "Y-m-d");
                //     $checkin_from = $date1->format('Y-m-d');
                //     $checkin_todate = $date2->format('Y-m-d');
                //     $checkin_to = date('Y-m-d', strtotime('-1 day', strtotime($checkin_todate)));
                //     $checkin_checkout = $this->date_range($checkin_from,$checkin_to,"+1 day", "Y-m-d");
                //     $coupon_model=new Coupons();
                //     $coupon_id = isset($coupon[0]['applied_coupon']["coupon_id"])?$coupon[0]['applied_coupon']["coupon_id"]:isset($coupon['applied_coupon']["coupon_id"])?$coupon['applied_coupon']["coupon_id"]:0;
                //     $db_coupon=$coupon_model->where('coupon_id',$coupon_id)->first();
                //     if($selected_adult < $max_adult)
                //         {
                //             $adult=$selected_adult-1;//Array
                //             if($room_rate['multiple_occupancy'] && $room_rate['multiple_occupancy'][$adult]>0)
                //             {
                //                 $room_rate['multiple_occupancy'][$adult]=$room_rate['multiple_occupancy'][$adult]-round(($room_rate['multiple_occupancy'][$adult] * $room_rate['bookingjini_price'])/100);

                //                 // $room_price+=$room_rate['multiple_occupancy'][$adult];
                //                 $total_room_price =$room_rate['multiple_occupancy'][$adult];
                //             }
                //             else
                //             {
                //                 $total_room_price  =$room_rate['bar_price'];
                //                 // $room_price+=$room_rate['bar_price'];
                //             }
                //         }
                //         else
                //         {
                //             $total_room_price  =$room_rate['bar_price'];
                //             // $room_price+=$room_rate['bar_price'];
                //         }
                //         $coupon_room_type_id = isset($coupon[0]['applied_coupon']['room_type_id'])?$coupon[0]['applied_coupon']['room_type_id']:isset($coupon['applied_coupon']['room_type_id'])?$coupon['applied_coupon']['room_type_id']:0;
                //         $coupon_discount = isset($coupon[0]['applied_coupon']['discount'])?$coupon[0]['applied_coupon']['discount']:isset($coupon['applied_coupon']['discount'])?$coupon['applied_coupon']['discount']:0;
                //     if($db_coupon)
                //     {
                //         if($coupon_room_type_id==0)
                //         {

                //                     if(in_array($room_rate['date'],$coupon_date_array) && $j==0){
                //                         $calculated_discounted_price += $total_room_price* $coupon_discount/100;
                //                         $curr_discounted_price = $calculated_discounted_price*sizeof($rooms);
                //                          $j++;
                //                     }


                //         }
                //         else if($coupon_room_type_id==$room_type_id)
                //         {
                //             if(in_array($room_rate['date'],$coupon_date_array) && $j==0){
                //                 $calculated_discounted_price += $total_room_price* $coupon_discount/100;
                //                 $curr_discounted_price = $calculated_discounted_price*sizeof($rooms);
                //                  $j++;
                //             }
                //         }
                //       }
                //         if($db_coupon->discount== $coupon_discount && $discounted_price==$curr_discounted_price)
                //         {
                //             $chk_disc_ok=true;
                //         }
                // }

                // else{
                $chk_disc_ok = true;
                // }
                $extra_adult_price += $room_rate['extra_adult_price'];
                $extra_child_price += $room_rate['extra_child_price'];

                if ($selected_adult < $max_adult) {
                    $adult = $selected_adult - 1; //Array
                    if (!isset($room_rate['multiple_occupancy']) || !isset($room_rate['multiple_occupancy'][$adult])) {
                        return false;
                    }
                    if ($room_rate['multiple_occupancy'] && $room_rate['multiple_occupancy'][$adult] > 0) {
                        $room_rate['multiple_occupancy'][$adult] = $room_rate['multiple_occupancy'][$adult] - round(($room_rate['multiple_occupancy'][$adult] * $room_rate['bookingjini_price']) / 100);

                        $room_price += $room_rate['multiple_occupancy'][$adult];
                    } else {
                        $room_price += $room_rate['bar_price'];
                    }
                } else {
                    $room_price += $room_rate['bar_price'];
                }

                //array_push($multiple_occ,$room_rate['multiple_occupancy']);
            }
            //Check extra adult price
            $total_extra_child_price = 0;
            $total_extra_adult_price = 0;
            if ($selected_adult > $max_adult) {
                $no_of_extra_adult = $selected_adult - $max_adult;
                $total_extra_adult_price = $no_of_extra_adult * $extra_adult_price;
                if ($curr_extra_adult_price == $total_extra_adult_price) {
                    $extra_adult_ok = true;
                }
            } else if ($selected_adult == $max_adult) {

                $extra_adult_ok = true;
            } else {
                $extra_adult_ok = true; //This case covered inside loop
            }
            //Check extra child price
            if ($selected_child > $max_child) {
                $no_of_extra_child = $selected_child - $max_child;
                $total_extra_child_price = $no_of_extra_child * $extra_child_price;
                if ($curr_extra_child_price == $total_extra_child_price) {
                    $extra_child_ok = true;
                }
            } else if ($selected_child == $max_child) {
                $extra_child_ok = true;
            } else {
                $extra_child_ok = true;
            }
            $tot_extra_adult_price += $total_extra_adult_price;
            $tot_extra_child_price += $total_extra_child_price;
        }
        ///To check the discounted price
        $chk_gst_ok = false;
        //TO check the GST And Other taxes
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;

        $price = $room_price + $tot_extra_child_price + $tot_extra_adult_price - $curr_discounted_price;
        $chk_other_tax_ok = "";
        if ($baseCurrency == 'INR') {

            if ($diff == 0) {
                $diff = 1;
            }
            $gst_price = $this->getGstPrice($diff, sizeof($rooms), $room_type_id, $price); //TO get the GSt price
            if (round($gst_price, 2) == round($curr_gst_price, 2)) {
                $chk_gst_ok = true;
                $chk_other_tax_ok = true;
            } else {
                $chk_gst_ok = true;
                $chk_other_tax_ok = true;
            }
        } else if ($baseCurrency != 'INR') {  //Other tax module check
            $orig_tax_details = $this->getorigTaxDetails($hotel_id);
            $chk_gst_ok = true;
            $chk_other_tax_ok = true;
            if (sizeof($orig_tax_details) == sizeof($cur_other_tax)) {
                foreach ($orig_tax_details as $key => $orig_tax) {
                    if (
                        $orig_tax['tax_name'] == $cur_other_tax[$key]['tax_name'] &&
                        round(($orig_tax['tax_percent'] * $price / 100), 2) == round($cur_other_tax[$key]['tax_price'], 2)
                    ) {
                        $chk_other_tax_ok = $chk_other_tax_ok && true;
                    } else {
                        $chk_other_tax_ok = $chk_other_tax_ok && false;
                    }
                }
            }
        }
        if (round($curr_room_price, 2) == round($room_price, 2) && $extra_child_ok && $extra_adult_ok &&  $chk_disc_ok && $chk_gst_ok && $chk_other_tax_ok) {
            return true;
        } else {
            return false;
        }
    }

    //GET dynamic tax moudletot_extra_adult_pricetot_extra_adult_price
    public function getorigTaxDetails($hotel_id)
    {
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
        $finDetails = TaxDetails::where($conditions)->select('tax_name', 'tax_percent')->first();
        $tax_name_arr = explode(',', $finDetails->tax_name);
        $tax_percent_arr = explode(',', $finDetails->tax_percent);
        $finace_details = array();
        foreach ($tax_name_arr as $key => $tax) {
            array_push($finace_details, array('tax_name' => $tax_name_arr[$key], 'tax_percent' => $tax_percent_arr[$key]));
        }
        return $finace_details;
    }
    //*************GET the GST of the Price of a Individual room*****************/
    public function getGstPrice($no_of_nights, $no_of_rooms, $room_type_id, $price)
    {

        $chek_price = ($price / $no_of_nights) / $no_of_rooms;
        $gstPercent = $this->checkGSTPercent($room_type_id, $chek_price);

        $gstPrice = (($price) * $gstPercent) / 100;
        $gstPrice = round($gstPrice, 2);
        return $gstPrice;
    }
    //*************Update the GST % of the Individual room*****************/
    public function checkGSTPercent($room_type_id, $price)
    {
        if ($price < 1000) {
            return 0;
        } else if ($price >= 1000 && $price < 7500) {
            return 12;
        } else if ($price >= 7500) {
            return 18;
        }
    }
    //Get User details
    public function getUserDetails($user_id)
    {
        $user = User::select('*')->where('user_id', $user_id)->first();
        return $user;
    }
    //Create Invoice
    /*--------------------------------------- INVOICE (START) ---------------------------------------*/

    public function createInvoice($hotel_id, $cart, $coupon, $paid_services, $check_in, $check_out, $user_id, $percentage, $ref_no, $refund_protect_price, $rp_member_id, $source, $paid_amount_info, $addon_charges_name, $total_addon_price, $total_gst_addon_price, $guest_note, $arrival_time, $refund_protect_sold_status)
    {
        //,$addon_charges_name,$total_addon_price,$total_gst_addon_price
        if ($hotel_id == 2065  || $hotel_id == 2881  || $hotel_id == 2882  || $hotel_id == 2883  || $hotel_id == 2884  || $hotel_id == 2885  || $hotel_id == 2886) {
            $booking_id = "#####";
            $serial_no = "*****";
            $transection_no = "@@@@@";
            $booking_date = date('Y-m-d');
            $booking_date = date("jS M, Y", strtotime($booking_date));
            $hotel_details = $this->getHotelInfo($hotel_id);
            $longitude = $hotel_details->longitude;
            $latitude = $hotel_details->latitude;
            $get_logo_info = CompanyDetails::select('logo')->where('company_id', $hotel_details->company_id)->first();
            $get_logo = ImageTable::select('image_name')->where('image_id', $get_logo_info->logo)->first();
            $logo_image = isset($get_logo->image_name) ? $get_logo->image_name : 'NA';
            $hotel_logo_image_src = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $logo_image;
            $OTDC_logo_image_src = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/otdc_logo.jpg';
            $package_info = explode(',', $hotel_details->facility);
            $package_includes = array();
            foreach ($package_info as $val) {
                $get_amenities = HotelAmenities::select('hotel_amenities_name')->where('hotel_amenities_id', $val)->first();
                $package_includes[] = $get_amenities->hotel_amenities_name;
            }
            $u = $this->getUserDetails($user_id);
            $user_name      = $u['first_name'] . ' ' . $u['last_name'];
            $user_mobile    = $u['mobile'];
            $user_email     = $u['email_id'];
            $user_address   = $u['address'];
            $user_gstin     = $u['GSTIN'];
            $user_company   = $u['company_name'];
            $today_date     = date("d M Y");
            $dsp_check_in = date("jS M, Y", strtotime($check_in));
            $dsp_check_out = date("jS M, Y", strtotime($check_out));
            $date1 = date("Y-m-d", strtotime($check_in));
            $date2 = date("Y-m-d", strtotime($check_out));
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
            $total_cost = 0;
            $all_room_type_name = "";
            $paid_service_details = "";
            $all_rows = "";
            $total_discount_price = 0;
            $total_price_after_discount = 0;
            $total_tax = 0;
            $display_discount = 0.00;
            $other_tax_arr = array();

            //Get base currency and currency hex code
            $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;
            $currency_code = $this->getBaseCurrency($hotel_id)->hex_code;
            $room_amt_array = array();
            foreach ($cart as $cartItem) {
                $room_type_id = $cartItem['room_type_id'];
                $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
                $room_type_array = MasterRoomType::select('room_type', 'room_amenities')->where($conditions)->first();
                $room_type = $room_type_array['room_type'];
                $room_amt_info = $room_type_array['room_amenities'];
                $room_amt_info = explode(',', $room_amt_info);
                foreach ($room_amt_info as $room_amt) {
                    if ($room_amt == 'NA') {
                        continue;
                    }
                    $get_room_amenities = HotelAmenities::select('hotel_amenities_name')->where('hotel_amenities_id', $room_amt)->first();
                    $room_amt_array[] = $get_room_amenities->hotel_amenities_name;
                }
                $rate_plan_id = $cartItem['rooms'][0]['rate_plan_id'];
                $conditions = array('rate_plan_id' => $rate_plan_id, 'is_trash' => 0);
                $rate_plan_id_array = MasterRatePlan::select('plan_name')->where($conditions)->first();
                $rate_plan = $rate_plan_id_array['plan_name'];

                $i = 1;
                $total_price = 0;
                $all_room_type_name .= ',' . sizeof($cartItem['rooms']) . ' ' . $room_type;
                $every_room_type = "";
                $all_rows .= '<tr><td colspan="2">' . $booking_id . '</td>';
                $all_rows .= '<td colspan="2">' . $hotel_details['hotel_name'] . '</td>';
                $all_rows .= '<td colspan="2">' . $room_type . '(' . $rate_plan . ')</td>';
                $all_rows .= '<td colspan="2">' . $dsp_check_in . '-' . $dsp_check_out . '</td>';
                foreach ($cartItem['rooms'] as $rooms) {
                    $ind_total_price = 0;
                    $total_adult += $rooms['selected_adult'];
                    $total_child += $rooms['selected_child'];
                    $ind_total_price = $rooms['bar_price'] + $rooms['extra_adult_price'] + $rooms['extra_child_price'];

                    if ($i == 1) {
                        $all_rows = $all_rows . '<td colspan="2">' . $i . '</td>
                   <td colspan="2"> ' . $currency_code . round($rooms['bar_price'] / $day) . '</td>
                   <td colspan="2">' . round($rooms['extra_adult_price'] / $day) . '</td>
                   <td colspan="2">' . round($rooms['extra_child_price'] / $day) . '</td>
                   <td colspan="2">' . $day . '</td>
                   <td colspan="2">' . $currency_code . $ind_total_price . '</td>
                    </tr>';
                    } else {
                        $all_rows = $all_rows . '<tr> <td colspan="2" style="border-right: none"></td>
                <td colspan="2" style="border-left: none; border-right: none"></td>
                <td colspan="2" style="border-left: none; border-right: none"></td>
                <td colspan="2" style="border-left: none"></td>
                <td colspan="2">' . $i . '</td>
                <td colspan="2">' . $currency_code . round($rooms['bar_price'] / $day) . '</td>
                <td colspan="2">' . round($rooms['extra_adult_price'] / $day) . '</td>
                <td colspan="2">' . round($rooms['extra_child_price'] / $day) . '</td>
                <td colspan="2">' . $day . '</td>
                <td colspan="2">' . $currency_code . $ind_total_price . '</td>
                 </tr>';
                    }

                    $i++;
                    $total_price += $ind_total_price;
                    $condition = array('hotel_id' => $hotel_id, 'room_type_id' => $cartItem['room_type_id'], 'rate_plan_id' => $cartItem['rooms'][0]['rate_plan_id'], 'ref_no' => $ref_no);
                    $check_existance = BeBookingDetailsTable::select('id')->where($condition)->first();
                    if (!$check_existance) {
                        $be_bookings = new BeBookingDetailsTable();
                        $booking_details['hotel_id'] = $hotel_id;
                        $booking_details['ref_no'] = $ref_no;
                        $booking_details['room_type'] = $room_type_array['room_type'];
                        $booking_details['rooms'] = sizeof($cartItem['rooms']);
                        $booking_details['room_rate'] = round($rooms['bar_price'] / $day);
                        $booking_details['extra_adult'] = round($rooms['extra_adult_price'] / $day);
                        $booking_details['extra_child'] = round($rooms['extra_child_price'] / $day);
                        $booking_details['adult'] = $rooms['selected_adult'];
                        $booking_details['child'] = $rooms['selected_child'];
                        $booking_details['room_type_id'] = $cartItem['room_type_id'];
                        $getGstPrice = $this->getGstPricePerRoom($booking_details['room_rate'], $cartItem['discounted_price']);
                        $booking_details['tax_amount'] = $getGstPrice;
                        $booking_details['rate_plan_name'] = $rate_plan_id_array['plan_name'];
                        $booking_details['rate_plan_id'] = $cartItem['rooms'][0]['rate_plan_id'];
                        if ($be_bookings->fill($booking_details)->save()) {
                            $res = array('status' => 1, 'message' => 'booking details save successfully');
                        }
                    }
                }
                $total_cost += $total_price;
                $total_discount_price += $cartItem['discounted_price'];
                $total_discount_price = number_format((float)$total_discount_price, 2, '.', '');
                $total_price_after_discount += ($total_price - $cartItem['discounted_price']);
                $total_price_after_discount = number_format((float)$total_price_after_discount, 2, '.', '');
                if ($cartItem['tax'][0]['gst_price'] != 0) {
                    $total_tax  += $cartItem['tax'][0]['gst_price'];
                } else {
                    foreach ($cartItem['tax'][0]['other_tax'] as $key => $other_tax) {
                        $total_tax += $other_tax['tax_price'];
                        $other_tax_arr[$key]['tax_name'] = $other_tax['tax_name'];
                        if (!isset($other_tax_arr[$key]['tax_price'])) {
                            $other_tax_arr[$key]['tax_price'] = 0;
                            $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                        } else {
                            $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                        }
                    }
                }
                $total_tax = number_format((float)$total_tax, 2, '.', '');
                $every_room_type = $every_room_type . '
           <tr>
               <td colspan="18" align="right" style="font-weight: bold;">Total &nbsp;</td>
               <td align="center" style="font-weight: bold;">' . $currency_code . $total_price . '</td>
           </tr>';

                $all_rows = $all_rows . $every_room_type;
            }
            if ($total_discount_price > 0) {
                $display_discount = $total_discount_price;
            }
            $service_amount = 0;
            if (sizeof($paid_services) > 0) {
                foreach ($paid_services as $paid_service) {
                    $paid_service_details = $paid_service_details . '<tr>
              <td colspan="18" style="text-align:right;">' . $paid_service['service_name'] . '&nbsp;&nbsp;</td>
              <td style="text-align:center;">' . $currency_code . ($paid_service['price'] * $paid_service['qty']) . '</td>
              <tr>';
                    $service_amount += $paid_service['price'] * $paid_service['qty'];
                }
                $paid_service_details = '<tr><td colspan="18" bgcolor="#ec8849" style="text-align:center; font-weight:bold;">Paid Service Details</td></tr>' . $paid_service_details;
            }
            $gst_tax_details = "";
            if ($baseCurrency == 'INR') {
                $gst_tax_details = '<tr>
           <td colspan="18" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
           <td align="center">' . $currency_code . $total_tax . '</td>
           </tr>';
            }
            if ($refund_protect_price > 0) {
                $refund_protect_info = '<tr>
               <td colspan="18" align="right">Refundable booking charges &nbsp;&nbsp;</td>
               <td align="center">' . $currency_code . $refund_protect_price . '</td>
           </tr>';
                $refund_protect_description = '
           <tr>
               <td colspan="2"><span style="color: #000; font-weight: bold;">If your booking is cancelled or you need to make changes  :</span>Please contact our customer service team at <a href="mailto:support@bookingjini.com">support@bookingjini.com</a></td>
           </tr>
           <tr>
               <td colspan="2"><span style="color: #000; font-weight: bold;">Refundable Booking :</span>
               This is a Refundable booking, so if you are unable to attend your booking due to unforeseen circumstances and can provide evidence as listed in the Terms and Conditions <a href="https://refundable.me/extended/en" target="_blank">here</a> you may be entitled to a full refund<br>You will need your reference number <b>#####</b> to apply for your refund using the form  <a href="https://form.refundable.me/forms/refund" target="_blank">here</a>."</td>
           </tr>
           ';
            } else {
                $refund_protect_info = '';
                $refund_protect_description = '';
            }
            $other_tax_details = "";
            if (sizeof($other_tax_arr) > 0) {

                foreach ($other_tax_arr as $other_tax) {
                    $other_tax_details = $other_tax_details . '<tr>
              <td colspan="18" style="text-align:right;">' . $other_tax['tax_name'] . '&nbsp;&nbsp;</td>
              <td style="text-align:center;">' . $currency_code . $other_tax['tax_price'] . '</td>
              <tr>';
                }
            }
            $total_amt = $total_price_after_discount + $service_amount;
            $total_amt = number_format((float)$total_amt, 2, '.', '');
            $total = $total_amt + $total_tax;
            $total = number_format((float)$total, 2, '.', '');

            if ($percentage == 0 || $percentage == 100) {
                $paid_amount = $total;
                $paid_amount = number_format((float)$paid_amount, 2, '.', '');
            } else {
                $paid_amount = $total * $percentage / 100;
                $paid_amount = number_format((float)$paid_amount, 2, '.', '');
            }
            $user_info = $u->first_name . ' ' . $u->last_name;
            $coupon_code = isset($coupon[0]['applied_coupon']['coupon_code']) ? $coupon[0]['applied_coupon']['coupon_code'] : 'NA';
            if ($coupon_code != 'NA') {
                $get_coupon_name = Coupons::select('coupon_name')
                    ->where('hotel_id', $hotel_id)
                    ->where('coupon_code', '=', $coupon_code)
                    ->first();
                if ($get_coupon_name) {
                    $coupon_name = $get_coupon_name->coupon_name;
                    $user_name = $coupon_name;
                } else {
                    $user_name = $user_info;
                }
            } else {
                $user_name = $user_info;
            }
            $due_amount = $total - $paid_amount;
            $due_amount = number_format((float)$due_amount, 2, '.', '');
            $manager_info = 'NAME:,EMAIL:,NUMBER:';
            if ($hotel_id == 2065) {
                $manager_info = 'NAME:Arman Nanda,EMAIL:otdc@panthanivas.com,NUMBER:8800448290/8249433577';
            } else if ($hotel_id == 2881) {
                $manager_info = 'NAME:Lakshya,EMAIL:otdc@panthanivas.com,NUMBER:,Front Desk:9668233220';
            } else if ($hotel_id == 2882) {
                $manager_info = 'NAME:Mr Lama,EMAIL:otdc@panthanivas.com,NUMBER:9777746060,Front Desk:9717749202';
            } else if ($hotel_id == 2883) {
                $manager_info = 'NAME:Sumanta Sarkar,EMAIL:otdc@panthanivas.com,NUMBER:9853199236,Front Desk:7683064397';
            } else if ($hotel_id == 2884) {
                $manager_info = 'NAME:Jayash Patil,EMAIL:otdc@panthanivas.com,NUMBER:8799869093,Front Desk:9717728202';
            } else if ($hotel_id == 2885) {
                $manager_info = 'NAME:,EMAIL:,NUMBER:';
            } else if ($hotel_id == 2886) {
                $manager_info = 'NAME:,EMAIL:,NUMBER:';
            }
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
                <th><img src="' . $hotel_logo_image_src . '" target="eco retreat logo" style = "float:left;height:80px;weight:100px"/></th>
                <th style="font-size: 23px; margin-right:20px"><div style="text-decoration: underline;">BOOKING CONFIRMATION VOUCHER</div>
                <div style="font-weight: bold; width: 36%; margin: 0 auto; font-size: 22px; color:#000; padding: 5px;">' . $hotel_details['hotel_name'] . '</div></th>
                <th> <img src="' . $OTDC_logo_image_src . '" target="otdc logo" style = "float:right;height:80px;weight:100px"/></th>
                </tr>
                <tr>
                <td><b style="color: #ffffff;">*</b></td>
                </tr>
                <tr>
                    <td>
                        <div>
                        </div>
                    </td>
                    <td style="font-size: 16px;font-weight: bold;" align="right">BOOKING ID : ' . $booking_id . '</td>
                </tr>
                <tr>
                    <td colspan="2"><b style="color: #ffffff;">*</b></td>
                </tr>
                <tr>
                    <td colspan="2"><b>Dear ' . $user_name . ',</b></td>
                </tr>';
            if ($hotel_id == 1602) {
                $body .= ' <tr>
                        <td colspan="2" style="font-size:17px;"><b>We welcome you to the ' . $hotel_details->hotel_name . ' as your property of choice for your visit and booking through our website. Your booking confirmation details are asunder:</b></td>
                    </tr>';
            } else {
                $body .= ' <tr>
                        <td colspan="2" style="font-size:17px;"><b>We hope this email finds you well. Thank you for choosing Eco Retreat. Your booking confirmation details are as below:</b></td>
                    </tr>';
            }
            $body .= '<tr>
                    <td colspan="2"><b style="color: #ffffff;">*</b></td>
                </tr>
        </table>
   
            <table width="100%" border="1" style="border-collapse: collapse;">
                <th colspan="2" bgcolor="#ec8849">BOOKING DETAILS</th>
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
            <td>CHECK IN DATE</td>
            <td>' . $dsp_check_in . '</td>
        </tr>
                <tr>
            <td>CHECK OUT DATE</td>
            <td>' . $dsp_check_out . '</td>
        </tr>
                <tr>
            <td>CHECK IN TIME</td>
            <td>' . $hotel_details->check_in . '</td>
        </tr>
                <tr>
            <td>CHECK OUT TIME</td>
            <td>' . $hotel_details->check_out . '</td>
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
        <tr>
            <td>CONTACT DETAILS OF PROPERTY MANAGER</td>
            <td>' . $manager_info . '</td>
        </tr>
        <tr>
            <td>ECO RETREAT LOCATION</td>
            <td><a href="http://maps.google.com/maps?q=' . $latitude . ',' . $longitude . '">View Map</a></td>
        </tr>
        </table>';
            $body1 = '<html>
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
            <table width="100%" border="0" style="border-collapse: collapse;">
                <tr>
                    <th colspan="6" style="width:18%">
                        <img src="' . $hotel_logo_image_src . '" target="eco retreat logo" style = "float:left;height:105px;width:100px"/>
                    </th>
                    <th colspan="6" style="width: 64%; text-align: center; font-size: 2rem;">Invoice<br/>
                    <span style="font-size: 1rem;">ODISHA TOURISM DEVELOPMENT CORPORATION LIMITED</span><br/>
                    <span style="font-size: 1rem;">GSTN NO : 21AAACO3579M2Z8</span>
                    </th>
                    <th colspan="6" style="width: 18%">
                    <img src="' . $OTDC_logo_image_src . '" target="OTDC logo" style = "float:right;height:80px;width:100px"/>
                    </th>
                </tr>
                <tr>
                    <th  colspan="6" style="width: 33.33%; text-align: left;">
                        <strong>TO :</strong>
                        <div>' . $user_name . '</div>
                        <div>' . $user_mobile . '</div>
                        <div>' . $user_email . '</div>
                        <div>' . $user_address . '</div>
                        <div>' . $user_company . '</div>
                        <div>GSTN NO :' . $user_gstin . '</div>
                    </th> 
                    <th colspan="6" style="width: 33.33%"></th>
                    <th colspan="6" style="width: 33.33%; text-align: right;">
                        <div>Serial No : ' . $serial_no . '</div>
                        <strong>Eco Retreat Address :</strong>
                        <div>' . $hotel_details['hotel_address'] . '</div>
                        <div>' . $hotel_details['email_id'] . '</div>
                        <div>' . $today_date . '</div>
                    </th> 
                </tr>
            </table>
            <table width="100%" border="1" style="border-collapse: collapse;">
                    <tr>
                <th colspan="2" bgcolor="#ec8849" align="center">Booking ID</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Hotel Name</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Type Of Room</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Check In - Check Out</th>
                <th colspan="2" bgcolor="#ec8849" align="center">No Of Room</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Room Rate</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Extra Adult Price</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Extra Child Price</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Days</th>
                <th colspan="2" bgcolor="#ec8849" align="center">Total Price</th>
            </tr>
                    ' . $all_rows . '
            <tr>
                <td colspan="18"><p style="color: #ffffff;">*</p></td>
            </tr>
            <tr>
                <td colspan="18" align="right">Discount/Commission &nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . $display_discount . '</td>
            </tr>
            <tr>
                <td colspan="18" align="right">After Discount/Commission&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . $total_price_after_discount . '</td>
            </tr>
                ' . $gst_tax_details . '
            <tr>
                <td colspan="18" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
                <td align="center">' . $currency_code . $total . '</td>
            </tr>
            <tr>
                <td colspan="18" align="right">Total Paid Amount&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . '<span id="pd_amt">' . $paid_amount . '</span></td>
            </tr>
            <tr>
                <td colspan="18" align="right">Pay at Retreat&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . '<span id="du_amt">' . $due_amount . '</span></td>
            </tr>
            <tr>
                <td colspan="18"><p style="color: #ffffff;">* </p></td>
            </tr>
            </table>
            <table width="100%" border="0" style="border-collapse: collapse;">
                <tr>
                    <th colspan="6">Payment Method : HDFC</th>
                    <th colspan="6">Transection Id : ' . $transection_no . '</th>
                </tr>
                <tr>Disclaimer : This is an electronically generated invoice, hence does not require a signature.</tr>
            </table>
            </div>
            </body>
            </html>';

            $body .=  '<tr>
            <td colspan="8">
            <strong>Facilities:</strong>
            <ul>';
            foreach ($room_amt_array as $amt) {
                $body .= '<li>' . $amt . '.</li>';
            }
            $body .= '</ul>
            <strong>Package includes:</strong>
            <ul>';
            foreach ($package_includes as $value) {
                $body .= '<li>' . $value . '.</li>';
            }

            $body .= '</ul>
            </td>
        </tr>';

            $body .=  '<table width="100%" border="0">
                <tr>
        <th colspan="2" style="font-size: 21px; color: #ffffff;"><u>*</u></th>
        </tr>
                <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;">We are looking forward to host you at ' . $hotel_details->hotel_name . '.</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
   
                <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold;">Regards,<br />
                Reservation Team<br />
                Eco Retreat<br />
                Mob   : ' . $hotel_details->mobile . '<br />
                Email : ' . $hotel_details->email_id . '</span>
            </td>
        </tr>
        
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Terms & Conditions</u> :</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->terms_and_cond . '</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Cancellation Policy</u> :</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>';
            if ($hotel_details->cancel_policy != "") {
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
        </html><br/><br/><br/><br/>';
            $body_info = $body . $body1;
            return $body_info;
        } else {
            $booking_id = "#####";
            $transaction_id = ">>>>>";
            $booking_date = date('Y-m-d');
            $booking_date = date("jS M Y", strtotime($booking_date));
            $hotel_details = $this->getHotelInfo($hotel_id);
            $u = $this->getUserDetails($user_id);
            $dsp_check_in = date("jS M, Y", strtotime($check_in));
            $dsp_check_out = date("jS M, Y", strtotime($check_out));
            $date1 = date("Y-m-d", strtotime($check_in));
            $date2 = date("Y-m-d", strtotime($check_out));
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
            $total_cost = 0;
            $all_room_type_name = "";
            $paid_service_details = "";
            $all_rows = "";
            $total_discount_price = 0;
            $total_price_after_discount = 0;
            $total_tax = 0;
            $display_discount = 0.00;
            $other_tax_arr = array();

            $coupon_details_str = '';
            // if ($hotel_id == 1953) {
            $coupon_code = isset($coupon[0]['applied_coupon']['coupon_code']) ? $coupon[0]['applied_coupon']['coupon_code'] : 'NA';
            if ($coupon_code != 'NA') {
                $get_coupon_name = Coupons::select('coupon_name', 'coupon_code', 'discount')
                    ->where('hotel_id', $hotel_id)
                    ->where('coupon_code', '=', $coupon_code)
                    ->first();
                if ($get_coupon_name) {
                    $coupon_code = $get_coupon_name->coupon_code;
                    $coupon_discount = $get_coupon_name->discount;
                    $coupon_details_str = '<br>(Code: ' . $coupon_code . ', ' . $coupon_discount . '%' . ')&nbsp;&nbsp;';
                }
            }
            // }

            //Get base currency and currency hex code
            $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;
            $currency_code = $this->getBaseCurrency($hotel_id)->hex_code;


            $hotel_info = HotelInformation::join('company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
                ->leftjoin('image_table', 'company_table.logo', '=', 'image_table.image_id')
                ->select('image_table.image_name', 'hotels_table.is_taxable')
                ->where('hotels_table.hotel_id', $hotel_id)
                ->first();
            if (isset($hotel_info->image_name)) {
                $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $hotel_info->image_name;
            } else {
                $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';
            }
            $bookingjini_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';
            // $bookingjini_logo = 'https://d3ki85qs1zca4t.cloudfront.net/logo/ota/bj.png';

            $total_add_on_price = 0;
            if ($addon_charges_name != 'NA' && $total_addon_price != 0) {
                $total_add_on_price = $total_addon_price + $total_gst_addon_price;
                $add_on_charge = '<tr>
        <td colspan="7" align="right" style="font-weight: bold;">' . $addon_charges_name . ' &nbsp;</td>
        <td align="center" style="font-weight: bold;">' . $currency_code . $total_add_on_price . '</td>
        </tr>';
            } else {
                $add_on_charge = '';
            }
            foreach ($cart as $cartItem) {
                $room_type_id = $cartItem['room_type_id'];
                $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
                $room_type_array = MasterRoomType::select('room_type')->where($conditions)->first();
                $room_type = $room_type_array['room_type'];
                $rate_plan_id = $cartItem['rooms'][0]['rate_plan_id'];
                $conditions = array('rate_plan_id' => $rate_plan_id, 'is_trash' => 0);
                $rate_plan_id_array = MasterRatePlan::select('plan_name')->where($conditions)->first();
                $rate_plan = $rate_plan_id_array['plan_name'];

                $i = 1;
                $total_price = 0;
                $all_room_type_name .= ',' . sizeof($cartItem['rooms']) . ' ' . $room_type;
                $every_room_type = "";
                $all_rows .= '<tr><td rowspan="' . sizeof($cartItem['rooms']) . '" colspan="2">' . $room_type . '(' . $rate_plan . ')</td>';

                $booking_details_room_rate = array();
                $booking_details_extra_adult = array();
                $booking_details_extra_child = array();
                $booking_details_adult = array();
                $booking_details_child = array();
                $booking_details_tax_amount = array();
                $booking_details_discount_price = array();
                $sizeof_rooms = sizeof($cartItem['rooms']);
                foreach ($cartItem['rooms'] as $rooms) {
                    $ind_total_price = 0;
                    $total_adult += $rooms['selected_adult'];
                    $total_child += $rooms['selected_child'];
                    $ind_total_price = $rooms['bar_price'] + $rooms['extra_adult_price'] + $rooms['extra_child_price'];

                    if ($i == 1) {
                        $all_rows = $all_rows . '<td  align="center">' . $i . '</td>
                <td  align="center"> ' . $currency_code . round($rooms['bar_price'] / $day) . '</td>
                <td  align="center">' . round($rooms['extra_adult_price'] / $day) . '</td>
                <td  align="center">' . round($rooms['extra_child_price'] / $day) . '</td>
                <td  align="center">' . $day . '</td>
                <td  align="center">' . $currency_code . $ind_total_price . '</td>
                 </tr>';
                    } else {
                        $all_rows = $all_rows . '<tr><td  align="center">' . $i . '</td>
                <td  align="center">' . $currency_code . round($rooms['bar_price'] / $day) . '</td>
                <td  align="center">' . round($rooms['extra_adult_price'] / $day) . '</td>
                <td  align="center">' . round($rooms['extra_child_price'] / $day) . '</td>
                <td  align="center">' . $day . '</td>
                <td  align="center">' . $currency_code . $ind_total_price . '</td>
                 </tr>';
                    }

                    $i++;
                    $total_price += $ind_total_price;
                    // if($hotel_id == 2319){
                    $booking_details_rooms = sizeof($cartItem['rooms']);
                    $booking_details_room_rate_dlt  = round($rooms['bar_price'] / $day, 2);
                    $booking_details_room_rate[] = round($rooms['bar_price'] / $day, 2);
                    $booking_details_extra_adult[] = round($rooms['extra_adult_price'] / $day, 2);
                    $booking_details_extra_child[] = round($rooms['extra_child_price'] / $day, 2);
                    $booking_details_adult[] = $rooms['selected_adult'];
                    $booking_details_child[] = $rooms['selected_child'];
                    $booking_details_discount_price_info = round(($cartItem['discounted_price'] / $sizeof_rooms) / $day, 2);
                    $booking_details_discount_price[] = round(($cartItem['discounted_price'] / $sizeof_rooms) / $day, 2);

                    if ($hotel_info->is_taxable == 1) {
                        $getGstPrice = $this->getGstPricePerRoom($booking_details_room_rate_dlt, $booking_details_discount_price_info);
                    } else {
                        $getGstPrice = 0;
                    }

                    $booking_details_tax_amount[] = round($getGstPrice, 2);
                    // }
                    // else{
                    //     $condition = array('hotel_id'=>$hotel_id,'room_type_id'=>$cartItem['room_type_id'],'rate_plan_id'=>$cartItem['rooms'][0]['rate_plan_id'],'ref_no'=>$ref_no);
                    //     $check_existance = BeBookingDetailsTable::select('id')->where($condition)->first();
                    //     if(!$check_existance){
                    //       $be_bookings = new BeBookingDetailsTable();
                    //       $booking_details['hotel_id'] = $hotel_id;
                    //       $booking_details['ref_no'] = $ref_no;
                    //       $booking_details['room_type'] = $room_type_array['room_type'];
                    //       $booking_details['rooms'] = sizeof($cartItem['rooms']);
                    //       $booking_details['room_rate'] = round($rooms['bar_price']/$day,2);
                    //       $booking_details['extra_adult'] = round($rooms['extra_adult_price']/$day,2);
                    //       $booking_details['extra_child'] = round($rooms['extra_child_price']/$day,2);
                    //       $booking_details['adult'] = $rooms['selected_adult'];
                    //       $booking_details['child'] = $rooms['selected_child'];
                    //       $booking_details['room_type_id'] = $cartItem['room_type_id'];
                    //       $getGstPrice = $this->getGstPricePerRoom($booking_details['room_rate'],$cartItem['discounted_price']);
                    //       $booking_details['tax_amount'] = round($getGstPrice,2);
                    //       $booking_details['rate_plan_name'] =$rate_plan_id_array['plan_name'];
                    //       $booking_details['rate_plan_id'] =$cartItem['rooms'][0]['rate_plan_id'];
                    //       if($be_bookings->fill($booking_details)->save()){
                    //           $res = array('status'=>1,'message'=>'booking details save successfully');
                    //       }
                    //     }
                    // }
                }
                // if($hotel_id == 2319){
                $condition = array('hotel_id' => $hotel_id, 'room_type_id' => $cartItem['room_type_id'], 'rate_plan_id' => $cartItem['rooms'][0]['rate_plan_id'], 'ref_no' => $ref_no);
                $check_existance = BeBookingDetailsTable::select('id')->where($condition)->first();
                if (!$check_existance) {
                    $be_bookings = new BeBookingDetailsTable();
                    $room_price_dtl = implode(',', $booking_details_room_rate);
                    $extra_adult_dtl = implode(',', $booking_details_extra_adult);
                    $extra_child_dtl = implode(',', $booking_details_extra_child);
                    $gst_dtl = implode(',', $booking_details_tax_amount);
                    $adult_dtl = implode(',', $booking_details_adult);
                    $child_dtl = implode(',', $booking_details_child);
                    $discount_price = implode(',', $booking_details_discount_price);
                    $booking_details['hotel_id'] = $hotel_id;
                    $booking_details['ref_no'] = $ref_no;
                    $booking_details['room_type'] = $room_type_array['room_type'];
                    $booking_details['rooms'] = $booking_details_rooms;
                    $booking_details['room_rate'] = $room_price_dtl;
                    $booking_details['extra_adult'] = $extra_adult_dtl;
                    $booking_details['extra_child'] =  $extra_child_dtl;
                    $booking_details['discount_price'] =  $discount_price;
                    $booking_details['adult'] = $adult_dtl;
                    $booking_details['child'] = $child_dtl;
                    $booking_details['room_type_id'] = $cartItem['room_type_id'];
                    $booking_details['tax_amount'] = $gst_dtl;
                    $booking_details['rate_plan_name'] = $rate_plan_id_array['plan_name'];
                    $booking_details['rate_plan_id'] = $cartItem['rooms'][0]['rate_plan_id'];
                    if ($be_bookings->fill($booking_details)->save()) {
                        $res = array('status' => 1, 'message' => 'booking details save successfully');
                    }
                }
                // }
                $total_cost += $total_price;
                $total_discount_price += $cartItem['discounted_price'];
                $total_discount_price = number_format((float)$total_discount_price, 2, '.', '');
                $total_price_after_discount += ($total_price - $cartItem['discounted_price']);
                $total_price_after_discount = number_format((float)$total_price_after_discount, 2, '.', '');
                if ($cartItem['tax'][0]['gst_price'] != 0) {
                    $total_tax  += $cartItem['tax'][0]['gst_price'];
                } else {
                    foreach ($cartItem['tax'][0]['other_tax'] as $key => $other_tax) {
                        $total_tax += $other_tax['tax_price'];
                        $other_tax_arr[$key]['tax_name'] = $other_tax['tax_name'];
                        if (!isset($other_tax_arr[$key]['tax_price'])) {
                            $other_tax_arr[$key]['tax_price'] = 0;
                            $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                        } else {
                            $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                        }
                    }
                }
                $total_tax = number_format((float)$total_tax, 2, '.', '');
                $every_room_type = $every_room_type . '
        <tr>
            <td colspan="7" align="right" style="font-weight: bold;">Total &nbsp;</td>
            <td align="center" style="font-weight: bold;">' . $currency_code . $total_price . '</td>
        </tr>';

                $all_rows = $all_rows . $every_room_type;
            }
            if ($total_discount_price > 0) {
                $display_discount = $total_discount_price;
            }
            $service_amount = 0;
            $paid_service_gst = 0;
            if (sizeof($paid_services) > 0) {
                foreach ($paid_services as $paid_service) {
                    $paid_service_details = $paid_service_details . '<tr>
           <td colspan="7" style="text-align:right;">' . $paid_service['service_name'] . '&nbsp;&nbsp;</td>
           <td style="text-align:center;">' . $currency_code . ($paid_service['price'] * $paid_service['qty']) . '</td>
           <tr>';
                    $service_amount += $paid_service['price'] * $paid_service['qty'];

                    // if ($hotel_id == 1953) {
                    if (isset($paid_service['service_tax_price'])) {
                        if (is_numeric($paid_service['service_tax_price'])) {
                            $service_tax_amt = $paid_service['service_tax_price'] * $paid_service['qty'];
                        } else {
                            $service_tax_amt = 0;
                        }
                        $paid_service_gst += $service_tax_amt;
                    }
                    // }

                }
                $total_tax = $total_tax + $paid_service_gst;
                $paid_service_details = '<tr><td colspan="8" bgcolor="#ec8849" style="text-align:center; font-weight:bold;">Paid Service Details</td></tr>' . $paid_service_details;
            }

            $check_tax_applicable = HotelInformation::select('hotels_table.is_taxable', 'hotels_table.pay_at_hotel', 'hotels_table.state_id', 'state_table.tax_name')
                ->leftjoin('kernel.state_table', 'kernel.hotels_table.state_id', '=', 'state_table.state_id')
                ->where('hotels_table.hotel_id', $hotel_id)->first();
            $gst_tax_details = "";
            if ($baseCurrency == 'INR') {
                if ($check_tax_applicable->is_taxable == 1) {
                    $gst_tax_details = '<tr>
            <td colspan="7" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
            <td align="center">' . $currency_code . $total_tax . '</td>
            </tr>';
                }
            } else {
                if ($check_tax_applicable->is_taxable == 1) {
                    if (isset($check_tax_applicable->tax_name) && $check_tax_applicable->tax_name != '') {
                        $gst_tax_details = '<tr>
                <td colspan="7" align="right">' . $check_tax_applicable->tax_name . '&nbsp;&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . $total_tax . '</td>
                </tr>';
                    }
                }
            }

            //01-11-22

            if ($refund_protect_price > 0 && $refund_protect_sold_status == true) {
                //added
                $refund_protect_price = number_format((float)$refund_protect_price, 2, '.', '');
                $refund_protect_info = '<tr>
                <td colspan="7" align="right">Refundable booking charges &nbsp;&nbsp;</td>
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
                } else if ($rp_member_id == 298) {
                    $refund_protect_description .= '<a href="https://form.refundable.me/forms/refund?memberId=298&bookingReference=#####" target="_blank">here</a>.</td>';
                }
                $refund_protect_description .= '</tr>';
            } else {
                $refund_protect_info = '';
                $refund_protect_description = '';
            }


            $other_tax_details = "";
            if (sizeof($other_tax_arr) > 0) {
                foreach ($other_tax_arr as $other_tax) {
                    $other_tax_details = $other_tax_details . '<tr>
           <td colspan="7" style="text-align:right;">' . $other_tax['tax_name'] . '&nbsp;&nbsp;</td>
           <td style="text-align:center;">' . $currency_code . $other_tax['tax_price'] . '</td>
           <tr>';
                }
            }
            $total_amt = $total_price_after_discount + $service_amount;
            $total_amt = number_format((float)$total_amt, 2, '.', '');
            if ($baseCurrency == 'INR') {
                if ($check_tax_applicable->is_taxable == 1) {
                    if ($refund_protect_price > 0 && $refund_protect_sold_status == true) {
                        $total = $total_amt + $total_tax + $refund_protect_price + $total_add_on_price;
                    } else {
                        $total = $total_amt + $total_tax  + $total_add_on_price;
                    }
                } else {
                    if ($refund_protect_price > 0 && $refund_protect_sold_status == true) {
                        $total = $total_amt + $refund_protect_price + $total_add_on_price;
                    } else {
                        $total = $total_amt + $total_add_on_price;
                    }
                }
            } else {
                if ($check_tax_applicable->is_taxable == 1) {
                    $total = $total_amt + $total_tax;
                } else {
                    $total = $total_amt;
                }
            }
            $total = number_format((float)$total, 2, '.', '');
            if ($source  == 'GEMS') {
                $paid_amount = $paid_amount_info;
            } else {
                if ($percentage == 100) {
                    $paid_amount = $total;
                    $paid_amount = number_format((float)$paid_amount, 2, '.', '');
                } elseif ($percentage == 0) {
                    $paid_amount = 0;
                    // $paid_amount = $total;
                } else {
                    $paid_amount = $total * $percentage / 100;

                    // if($hotel_id==1953){
                    $paid_amount = ($total - $refund_protect_price) * $percentage / 100;
                    $paid_amount = $paid_amount + $refund_protect_price;
                    $paid_amount = number_format((float)$paid_amount, 2, '.', '');
                    // }
                }

                // if($hotel_id==1953){
                //     if($percentage==0){
                //         $paid_amount = 0;
                //     }
                // }

            }


            $due_amount_info = $total - $paid_amount;
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
                <th><img src="' . $hotel_logo . '" style="float:left;height:80px;weight:100px"></th>
            </tr>
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
            if ($hotel_id == 1602) {
                $body .= ' <tr>
                     <td colspan="2" style="font-size:17px;"><b>We welcome you to the ' . $hotel_details->hotel_name . ' as your property of choice for your visit and booking through our hotel\'s website. Your booking confirmation details are asunder:</b></td>
                 </tr>';
            } else {
                $body .= ' <tr>
                     <td colspan="2" style="font-size:17px;"><b>We hope this email finds you well. Thank you for choosing ' . $hotel_details->hotel_name . ' as your property of choice for your visit and booking through our hotel\'s website. Your booking confirmation details have been provided below:</b></td>
                 </tr>';
            }
            $body .= '<tr>
                 <td colspan="2"><b style="color: #ffffff;">*</b></td>
             </tr>
     </table>

         <table width="100%" border="1" style="border-collapse: collapse;">
             <th colspan="2" bgcolor="#ec8849">BOOKING DETAILS</th>
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
         <td>CHECK IN DATE</td>
         <td>' . $dsp_check_in . '</td>
     </tr>
   
             <tr>
         <td>CHECK OUT DATE</td>
         <td>' . $dsp_check_out . '</td>
     </tr>
             <tr>
         <td>CHECK IN TIME</td>
         <td>' . $hotel_details->check_in . '</td>
     </tr>
             <tr>
         <td>CHECK OUT TIME</td>
         <td>' . $hotel_details->check_out . '</td>
     </tr>
     <tr>
     <td>EXPECTED ARRIVAL TIME</td>
     <td>' . $arrival_time . '</td>
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
     <th colspan="8" valign="middle" height="" style="font-size: 20px;">TARIFF APPLICABLE</th>
     </tr>
             <tr>
         <th colspan="2" bgcolor="#ec8849" align="center">Room Type</th>
         <th bgcolor="#ec8849" align="center">Rooms</th>
         <th bgcolor="#ec8849" align="center">Room Rate</th>
         <th bgcolor="#ec8849" align="center">Extra Adult Price</th>
         <th bgcolor="#ec8849" align="center">Extra Child Price</th>
         <th bgcolor="#ec8849" align="center">Days</th>
         <th bgcolor="#ec8849" align="center">Total Price</th>
     </tr>
             ' . $all_rows . '
     <tr>
         <td colspan="8"><p style="color: #ffffff;">*</p></td>
     </tr>
             <tr>
         <td colspan="7" align="right">Total Room Rate&nbsp;&nbsp;</td>
         <td align="center">' . $currency_code . $total_cost . '</td>
     </tr>
    
     <tr>
         <td colspan="7" align="right">Discount Coupon &nbsp;&nbsp;' . $coupon_details_str . '</td>
         <td align="center">' . $currency_code . $display_discount . '</td>
     </tr>

     <tr>
         <td colspan="7" align="right">After Discount&nbsp;&nbsp;</td>
         <td align="center">' . $currency_code . $total_price_after_discount . '</td>
     </tr>
     ' . $paid_service_details . '
     <tr>
         <td colspan="7" align="right">Total room rate with paid services &nbsp;&nbsp;</td>
         <td align="center">' . $currency_code . $total_amt . '</td>
     </tr>
         ' . $gst_tax_details . '
         ' . $add_on_charge . '
         ' . $other_tax_details . '
         ' . $refund_protect_info . '
     <tr>
         <td colspan="7" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
         <td align="center">' . $currency_code . $total . '</td>
     </tr>';
            $body .= '<tr>
        <td colspan="7" align="right">Total Paid Amount&nbsp;&nbsp;</td>
        <td align="center">' . $currency_code . '<span id="pd_amt">' . $paid_amount . '</span></td>
        </tr>
        <tr>
            <td colspan="7" align="right">Pay at hotel&nbsp;&nbsp;</td>
            <td align="center">' . $currency_code . '<span id="du_amt">' . $due_amount_info . '</span></td>
        </tr>
        <tr>
            <td colspan="8"><p style="color: #ffffff;">* </p></td>
        </tr>
        <tr>
            <td colspan="11" style="font-weight:bold;">Guest Note : ' . $guest_note . '</td>
        </tr>';

            if ($hotel_id == 1602) {
                $body .= '<tr>
                     <td colspan="8">
                     <strong>Package includes:</strong>
                     <ul>
                         <li>To & fro transfers from Bhubaneswar.</li>
                         <li>Accommodation.</li>
                         <li>Breakfast for Classic Cottage.</li>
                         <li>Breakfast, Lunch, High Tea and Dinner (buffet)(For all cottages other than Classic).</li>
                         <li>All activities mentioned in the itinerary including entry fees etc.</li>
                     </ul>
                     <strong>Package does not include:</strong>
                     <ul>
                         <li>A la Carte orders and bar services.</li>
                     </ul>
                     <strong>Cancellation & Refund Policy:</strong>
                     <ul>
                         <li>Cancellation 24 hours prior to check-in date/ time: No refund.</li>
                         <li>Cancellation between 24 – 72 hours prior to check-in date/ time: 50% refund.</li>
                         <li>Cancellation more than 72 hours prior to check-in date/ time: 100% refund.</li>
                     </ul>
                     </td>
             </tr>';
            }

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
            if ($hotel_details->cancel_policy != "") {
                $body .= '<tr>
         <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->cancel_policy . '</span></td>
     </tr>';
            } else {
                $body .= '<tr>
         <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->hotel_policy . '</span></td>
     </tr>';
            }
            $body .= '' . $refund_protect_description . '
     <tr>
        <td style="width:8%"><span>Powered by : </span>
        </td>
        <td style="width:92%">
            <img style="width:13%" src="' . $bookingjini_logo . '">
        </td>
     </tr>
     </table>
     </td>
     </tr>
     </table>
     </div>
     </body>
     </html>';
            //  if($hotel_id == ){

            //  }
            return $body;
        }
    }
    /*------------------------------------- INVOICE MAIL (START) ------------------------------------*/
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
        if ($this->sendMail($to_email, $body, $subject, "", $invoice->hotel_name, $invoice->hotel_id)) {
            return true;
        }
    }
    /*--------------------------------------- INVOICE MAIL (END) ------------------------------------*/


    /*----------------------------------- SUCCESS BOOKING (START) -----------------------------------*/

    public function successInvoice($id)
    {

        $query      = "Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice,a.ids_re_id, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond,a.agent_code,a.booking_status,a.modify_status from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$id";
        $result    = DB::select($query);
        return $result;
    }

    /*------------------------------------ SUCCESS BOOKING (END) ------------------------------------*/

    /*
*Email Invoice
*@param $email for to email
*@param $template is the email template
*@param $subject for email subject
*/
    public function sendMail($email, $template, $subject, $hotel_email, $hotel_name, $hotel_id)
    {
        $data = array('email' => $email, 'subject' => $subject);
        $data['template'] = $template;
        $data['hotel_name'] = $hotel_name;
        if ($hotel_id == 2065  || $hotel_id == 2881  || $hotel_id == 2882  || $hotel_id == 2883  || $hotel_id == 2884  || $hotel_id == 2885  || $hotel_id == 2886) {
            if ($hotel_email == "") {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com'];
            } else if (sizeof($hotel_email) > 1) {
                $data['hotel_email'] = $hotel_email[0];
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com', $hotel_email[1]];
            } else if (sizeof($hotel_email) == 1) {
                $data['hotel_email'] = $hotel_email[0];
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com'];
            } else {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com'];
            }
        } else {
            if ($hotel_email == "") {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            } else if (sizeof($hotel_email) > 1) {
                $data['hotel_email'] = $hotel_email;
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            } else if (sizeof($hotel_email) == 1) {
                $data['hotel_email'] = $hotel_email[0];
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            } else {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            }
        }
        $data['mail_array'] = $mail_array;
        //dd($data);
        try {
            Mail::send([], [], function ($message) use ($data) {
                if ($data['hotel_email'] != "") {
                    $message->to($data['email'])
                        ->cc($data['hotel_email'])
                        ->bcc($data['mail_array'])
                        ->from(env("MAIL_FROM"), $data['hotel_name'])
                        ->subject($data['subject'])
                        ->setBody($data['template'], 'text/html');
                } else {
                    $message->to($data['email'])
                        ->from(env("MAIL_FROM"), $data['hotel_name'])
                        //->cc('gourab.nandy@bookingjini.com')
                        ->subject($data['subject'])
                        ->setBody($data['template'], 'text/html');
                }
            });
            if (Mail::failures()) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return true;
        }
    }

    //Success full booking

    /*public function SuccessBooking(string $email_id,string $tr_id,Request $request)
{

}*/
    /*----------------------------------- SUCCESS BOOOKING (START) ----------------------------------*/
    public function testbooking(int $invoice_id, Request $request)
    {
        $this->successBooking(32199, 'WEBSITE', 'true', 'NA', 1234);
    }
    public function gemsBooking(int $invoice_id, string $gems, string $mail_opt, Request $request)
    {
        $get_url = $this->successBooking($invoice_id, $gems, $mail_opt, 'NA', 12345);
        if ($get_url) {
            return response()->json(array('status' => 1, "mesage" => "offline booking successful"));
        } else {
            return response()->json(array('status' => 0, "mesage" => "offline booking fails"));
        }
    }
    public function crsBooking(int $invoice_id, string $crs, Request $request)
    {
        $get_url = $this->successBooking($invoice_id, $crs, 'NA', 'NA', 12345);
        if ($get_url) {
            return response()->json(array('status' => 1, "mesage" => "offline booking successful"));
        } else {
            return response()->json(array('status' => 0, "mesage" => "offline booking fails"));
        }
    }
    public function bookingModification(int $invoice_id, string $modify, Request $request)
    {
        $get_url = $this->successBooking($invoice_id, $modify, 'true', 'NA', 12345);
        if ($get_url) {
            return response()->json(array('status' => 1, "mesage" => "offline booking successful"));
        } else {
            return response()->json(array('status' => 0, "mesage" => "offline booking fails"));
        }
    }
    public function quickPaymentLink(int $invoice_id, string $quickpayment, Request $request)
    {
        $get_url = $this->successBooking($invoice_id, $quickpayment, 'true', 'NA', 12345);
        if ($get_url) {
            return response()->json(array('status' => 1, "mesage" => "offline booking successful"));
        } else {
            return response()->json(array('status' => 0, "mesage" => "offline booking fails"));
        }
    }
    public function otdcBookingSuccess(int $invoice_id, $otdc_crs, $transection_id, Request $request)
    {
        $get_url = $this->successBooking($invoice_id, $otdc_crs, 'NA', 'NA', $transection_id);
        if ($get_url) {
            return response()->json(array('status' => 1, "mesage" => "offline booking successful"));
        } else {
            return response()->json(array('status' => 0, "mesage" => "offline booking fails"));
        }
    }

    public function manualBookingSuccess(int $invoice_id, $otdc_crs, $transection_id, Request $request)
    {
        $get_url = $this->successBooking($invoice_id, $otdc_crs, 'NA', 'NA', $transection_id);
        if ($get_url) {
            return $get_url;
        } else {
            return 0;
        }
    }

    public function crsPackageBookingSuccess(int $invoice_id, $package_crs, Request $request)
    {
        $get_url = $this->successBooking($invoice_id, $package_crs, 'NA', 'NA', 12345);
        if ($get_url) {
            return response()->json(array('status' => 1, "mesage" => "offline booking successful"));
        } else {
            return response()->json(array('status' => 0, "mesage" => "offline booking fails"));
        }
    }

    public function payAtHotelBooking(int $invoice_id, string $pay_at_hotel, Request $request)
    {


        $get_url = $this->successBooking($invoice_id, $pay_at_hotel, 'NA', 'NA', 12345);

        $txnid = date('dmy') . $invoice_id;
        if ($get_url) {
            if (strpos($get_url, 'bookingjini.com')) {
                $url = "https://" . $get_url . "/thank-you/" . $invoice_id . "/" . $txnid;
            } else {
                $url = "https://" . $get_url . "/thank-you/" . $invoice_id . "/" . $txnid;
            }
            return response()->json(array('status' => 1, 'data' => $url, "mesage" => "booking successful"));
        } else {
            return response()->json(array('status' => 0, "mesage" => "booking fails"));
        }
    }

    public function sendWhatsAppBookingNotificationToHoteler($to, $message)
    {
        // $data = $request->all();
        // $to = $data['to'];
        // $message = $data['message'];

        $messaging_product = "whatsapp";
        $type = "template";
        $language = array(
            "code" => "en_US"
        );

        $hotel_name = array(
            "type" => "text",
            "text" => $message['hotel_name']
        );

        $guest_name = array(
            "type" => "text",
            "text" => $message['guest_name']
        );
        $phone_number = array(
            "type" => "text",
            "text" => $message["phone_number"]
        );

        $booking_id = array(
            "type" => "text",
            "text" => $message['booking_id']
        );
        $booking_voucher_url = array(
            "type" => "text",
            "text" => $message["booking_voucher_url"]
        );

        $parameters[] = $hotel_name;
        $parameters[] = $guest_name;
        $parameters[] = $phone_number;
        $parameters[] = $booking_id;
        $parameters[] = $booking_voucher_url;

        $components[] = array(
            "type" => "body",
            "parameters" => $parameters
        );

        $template = array(
            "name" => "reservation_lead",
            "language" => $language,
            "components" => $components
        );

        $post_datas = array(
            "messaging_product" => $messaging_product,
            "to" => $to,
            "type" => $type,
            "template" => $template
        );
        $array_post_fields = json_encode($post_datas);
        // $result = Commonmodel::curlPostForWhatsApp(WHATSAPP_API_MESSAGE_URL,$array_post_fields,WHATSAPP_AUTHORIZATION);
        $url = WHATSAPP_API_MESSAGE_URL;
        $whatsapp_authorization = WHATSAPP_AUTHORIZATION;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $array_post_fields,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $whatsapp_authorization,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);
        return response()->json($response);
    }

    public function successBookingOld($invoice_id, $mihpayid, $payment_mode, $hash, $txnid)
    {
        try {
            $invoice_model = new Invoice();
            $transaction_model = new OnlineTransactionDetail();
            if ($mihpayid != "true" && $mihpayid != "crs" && $mihpayid != "quickpayment" && $mihpayid != "package_crs" && $mihpayid != "package_yes" && $mihpayid != 'modify' && $mihpayid != 'pay_at_hotel') {

                $hotel_dl_info = Invoice::select('*')->where('invoice_id', $invoice_id)->first();
                $hotel_id = $hotel_dl_info->hotel_id;

                $user_id = $hotel_dl_info->user_id;
                $user_data = UserNew::where('user_id', $user_id)->orderBy('user_id', 'DESC')->first();
                $dta_avail = $transaction_model->where('invoice_id', $invoice_id)->where('email_id', $user_data->email_id)->orderBy('tr_id', 'DESC')->first();

                $booking_info   = array(
                    "invoice_id" => $invoice_id,
                    "transaction_id" => $txnid
                );

                if ($hotel_id == 2065) {
                    $check = DB::table('eco_retreat_bhitarkanika')->select('*')->where('invoice_id', $invoice_id)->first();
                    if (!empty($check)) {
                        return '';
                    }
                    $save_info = DB::table('eco_retreat_bhitarkanika')->insert($booking_info);
                }
                if ($hotel_id == 2881) {
                    $check = DB::table('eco_retreat_konark')->select('*')->where('invoice_id', $invoice_id)->first();
                    if (!empty($check)) {
                        return '';
                    }
                    $save_info = DB::table('eco_retreat_konark')->insert($booking_info);
                }
                if ($hotel_id == 2882) {
                    $check = DB::table('eco_retreat_satkosia')->select('*')->where('invoice_id', $invoice_id)->first();
                    if (!empty($check)) {
                        return '';
                    }
                    $save_info = DB::table('eco_retreat_satkosia')->insert($booking_info);
                }
                if ($hotel_id == 2883) {
                    $check = DB::table('eco_retreat_daringbadi')->select('*')->where('invoice_id', $invoice_id)->first();
                    if (!empty($check)) {
                        return '';
                    }
                    $save_info = DB::table('eco_retreat_daringbadi')->insert($booking_info);
                }
                if ($hotel_id == 2884) {
                    $check = DB::table('eco_retreat_hirakud')->select('*')->where('invoice_id', $invoice_id)->first();
                    if (!empty($check)) {
                        return '';
                    }
                    $save_info = DB::table('eco_retreat_hirakud')->insert($booking_info);
                }
                if ($hotel_id == 2885) {
                    $check = DB::table('eco_retreat_sonapur')->select('*')->where('invoice_id', $invoice_id)->first();
                    if (!empty($check)) {
                        return '';
                    }
                    $save_info = DB::table('eco_retreat_sonapur')->insert($booking_info);
                }
                if ($hotel_id == 2886) {
                    $check = DB::table('eco_retreat_koraput')->select('*')->where('invoice_id', $invoice_id)->first();
                    if (!empty($check)) {
                        return '';
                    }
                    $save_info = DB::table('eco_retreat_koraput')->insert($booking_info);
                }

                $check_exist = ProductPrice::select('*')->where('invoice_id', $invoice_id)->first();

                if ($check_exist) {
                    $booking_data = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                        ->join('kernel.user_table as user_table', 'user_table.user_id', '=', 'hotel_booking.user_id')
                        ->join('booking_assure_table', 'booking_assure_table.invoice_id', '=', 'invoice_table.invoice_id')
                        ->where('invoice_table.invoice_id', $invoice_id)
                        ->select('hotel_booking.check_in', 'hotel_booking.booking_date', 'user_table.first_name', 'user_table.last_name', 'booking_assure_table.*')
                        ->first();
                    if ($booking_data) {
                        $today = date('Y-m-d');
                        $check_in = date('Y-m-d', strtotime($booking_data->check_in));
                        if ($check_in > $today) {
                            $booking_id = date('dmy', strtotime($booking_data->booking_date)) . $invoice_id;
                            $booking_mode = $booking_data->refundable_cancellation_mode;
                            $client = new \GuzzleHttp\Client();
                            $apiEndpoint = "https://api.protectgroup.co/api/v1/RefundProtect/salesoffering";
                            // $apiEndpoint = "https://test.api.protectgroup.co/api/v1/refundprotect/salesoffering"; 
                            $product_data = array(
                                "productCode"           => $booking_data->product_code,
                                "currencyCode"          => $booking_data->currency_code,
                                "productPrice"          => $booking_data->product_price,
                                "premiumRate"           => $booking_data->premium_rate,
                                "offeringMethod"        => $booking_data->offering_method,
                                "sold"                  => $booking_data->sold,
                                "insuranceEndDate"      =>  date('c', strtotime($booking_data->check_in)),
                            );
                            if ($booking_mode == 'mandatory') {
                                $request_data = array(
                                    "vendorCode" => "ven_live_0feca2c00627470797e19dfb86009bdb",
                                    "vendorSalesReferenceId" =>  $booking_id,
                                    "VendorSalesDate"       =>  date('c', strtotime($booking_data->booking_date)),
                                    "customerFirstName"     =>  $booking_data->first_name,
                                    "customerLastName"      =>  $booking_data->last_name,
                                    "products"              => [$product_data],
                                );

                                $response = $client->post($apiEndpoint, [
                                    'headers' => [
                                        'Content-Type'              => 'application/json',
                                        'X-RefundProtect-VendorId'  => 'ven_live_0feca2c00627470797e19dfb86009bdb',
                                        'X-RefundProtect-AuthToken' => 'sk_live_6aa41e1946b147af96e08220c49189b6',
                                    ],
                                    'body'                          => json_encode($request_data),
                                ]);
                            } else {
                                $request_data = array(
                                    "vendorCode" => "ven_live_ae8137b2306342c8be92196e007a460d",
                                    "vendorSalesReferenceId" =>  $booking_id,
                                    "VendorSalesDate"       =>  date('c', strtotime($booking_data->booking_date)),
                                    "customerFirstName"     =>  $booking_data->first_name,
                                    "customerLastName"      =>  $booking_data->last_name,
                                    "products"              => [$product_data],
                                );

                                $response = $client->post($apiEndpoint, [
                                    'headers' => [
                                        'Content-Type'              => 'application/json',
                                        'X-RefundProtect-VendorId'  => 'ven_live_ae8137b2306342c8be92196e007a460d',
                                        'X-RefundProtect-AuthToken' => 'sk_live_a512c2d8c03a4ad59d25f8fbeb9a784e',
                                    ],
                                    'body'                          => json_encode($request_data),
                                ]);
                            }

                            if ($response->getStatusCode() === 200) {
                                $response_status  = $response->getBody();
                                $res = ProductPrice::where('booking_assure_id', $booking_data->booking_assure_id)
                                    ->update(array('response_status' => $response_status));
                            }
                        }
                    }
                }
            }

            $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
            $invoice_details->booking_status = 1;
            if ($mihpayid == 'crs') {
                $invoice_details->booking_source = 'CRS';
            }
            if ($mihpayid == 'true') {
                $invoice_details->booking_source = 'GEMS';
            }
            if ($mihpayid == 'quickpayment') {
                $invoice_details->booking_source = 'QUICKPAYMENT';
            }
            if ($invoice_details->save()) {
                $hotel_booking_model = HotelBooking::where('invoice_id', $invoice_id)->get();
                if ($mihpayid != "true" && $mihpayid != "crs" && $mihpayid != "quickpayment" && $mihpayid != 'otdcCrs') {
                    foreach ($hotel_booking_model as $hbm) {
                        $period     = new \DatePeriod(
                            new \DateTime($hbm->check_in),
                            new \DateInterval('P1D'),
                            new \DateTime($hbm->check_out)
                        );
                        foreach ($period as $value) {
                            $index = $value->format('Y-m-d');
                            $check = Inventory::select('no_of_rooms')
                                ->where('hotel_id', $hbm->hotel_id)
                                ->where('room_type_id', $hbm->room_type_id)
                                ->whereDate('date_from', '<=', $value)
                                ->whereDate('date_to', '>=', $value)
                                ->orderBy('inventory_id', 'DESC')
                                ->first();
                            if ($check->no_of_rooms <= 0) {
                                $update = Invoice::where('invoice_id', $invoice_id)->where('booking_status', 1)->update(['booking_status' => 2]);
                                $insert_refund_data = Refund::insert(['booking_id' => $invoice_id, 'message' => "Room not available!"]);
                                // echo "Sorry, Room not available!";
                                return false;
                            }
                        }
                    }
                }
                $update_inv_cm = array();

                $invoiceData = Invoice::where('invoice_id', $invoice_id)
                    ->leftjoin('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
                    ->select('invoice_table.*', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile', 'user_table.state', 'user_table.city')
                    ->first();


                //push to gems
                if ($invoiceData->booking_source == 'website' || $invoiceData->booking_source == 'google' || $invoiceData->booking_source == 'QUICKPAYMENT') {

                    $booking_details_gems['hotel_id'] = $invoice_details->hotel_id;
                    $booking_details_gems['user_id'] = $invoice_details->user_id;
                    $booking_details_gems['invoice_id'] = $invoice_details->invoice_id;
                    $booking_details_gems['booking_amount'] = $invoice_details->total_amount;
                    $booking_details_gems['amount_paid'] = $invoice_details->paid_amount;
                    $booking_details_gems['pay_at_hotel_amount'] = $invoice_details->total_amount - $invoice_details->paid_amount;
                    $booking_details_gems['tax_amount'] = $invoice_details->tax_amount;
                    $booking_details_gems['check_in'] = $hotel_booking_model[0]->check_in;
                    $booking_details_gems['check_out'] = $hotel_booking_model[0]->check_out;
                    $booking_details_gems['user_name'] = $invoiceData->first_name . ' ' . $invoiceData->last_name;
                    $booking_details_gems['mobile'] = $invoiceData->mobile;
                    $booking_details_gems['email_id'] = $invoiceData->email_id;
                    $booking_details_gems['city'] = $invoiceData->city;
                    $booking_details_gems['state'] = $invoiceData->state;

                    $logpath = storage_path("logs/confirmWebsiteBooking.log" . date("Y-m-d"));
                    $booking_info = json_encode($booking_details_gems);
                    $logfile = fopen($logpath, "a+");
                    fwrite($logfile, $invoice_details->hotel_id . $booking_info . "\n");
                    fclose($logfile);
                }
                // print_r($booking_details_gems);exit;

                foreach ($hotel_booking_model as $hbm) {
                    //dd($hbm);
                    $hbm->booking_status = 1;
                    $hbm->save();
                    if ($mihpayid != 'otdcCrs') {
                        if ($invoiceData->is_cm == 1) {
                            $update_inv_cm[] = $this->updateInventoryCM($hbm, $invoice_details);
                            $resp = $this->updateInventory($hbm, $invoice_details);
                        } else {
                            $resp = $this->updateInventory($hbm, $invoice_details);
                        }
                    }
                }
                if ($invoiceData->is_cm == 1) {
                    if ($update_inv_cm) {
                        foreach ($update_inv_cm as $rm_val) {
                            $invoice_id = $rm_val['invoice_id'];
                            $hotel_id = $rm_val['hotel_id'];
                            $booking_details = $rm_val['booking_details'];
                            $room_type[] = $rm_val['room_type'];
                            $rooms_qty[] = $rm_val['rooms_qty'];
                            $booking_status = $rm_val['booking_status'];
                            $modify_status = $rm_val['modify_status'];
                        }
                        $bucketupdate = $this->beConfBookingInvUpdate->bookingConfirm($invoice_id, $hotel_id, $booking_details, $room_type, $rooms_qty, $booking_status, $modify_status);
                    }
                }

                //This code is use to tracking hotelier Activity
                // if (isset($invoiceData->invoice_id)) {
                //     $booking_id = date('dmy') . $invoice_id;
                //     $activity_from = "BE";
                //     if ($invoiceData->booking_source == 'GEMS') {
                //         $activity_id = "a12";
                //         $activity_description = "New PMS Booking received booking id $booking_id";
                //     } elseif ($invoiceData->booking_source == 'CRS') {
                //         $activity_id = "a14";
                //         $activity_from = "CRS";
                //         $activity_description = "New CRS Booking received booking id $booking_id";
                //     } elseif ($invoiceData->booking_source == 'google') {
                //         $activity_id = "a13";
                //         $activity_description = "New Google Booking received booking id $booking_id";
                //     } elseif ($invoiceData->booking_source == 'QUICKPAYMENT') {
                //         $activity_id = "a15";
                //         $activity_description = "New QUICKPAYMENT Booking received booking id $booking_id";
                //     } else {
                //         $activity_id = "a11";
                //         $activity_description = "New Website Booking received booking id $booking_id";
                //     }
                //     $activity_name = "";
                //     $user_id2 = $invoiceData->user_id;
                //     $hotel_id2 = $invoiceData->hotel_id;
                //     captureHotelActivityLog($hotel_id2, $user_id2, $activity_id, $activity_name, $activity_description, $activity_from);
                // }


                // if ($mihpayid != "true" && $mihpayid != "crs" && $mihpayid != "package_crs" && $mihpayid != "quickpayment" && $mihpayid != 'modify') {
                //     $this->invoiceMail($invoice_id);
                // }
                // if ($payment_mode == "true") {
                //     $this->invoiceMail($invoice_id);
                // }
                $getPMS_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'GEMS')->first();
                $hotel_info = explode(',', $getPMS_details->hotels);
                if (in_array($invoice_details->hotel_id, $hotel_info)) {
                    $pushBookignToGems = $this->pushBookingToGems($invoice_id, $mihpayid);
                }
                $getbookone_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'BookOne')->first();
                $hotel_info_bookone = explode(',', $getbookone_details->hotels);
                if (in_array($invoice_details->hotel_id, $hotel_info_bookone)) {
                    $pushBookignToBookone = $this->pushBookingToBookone($invoice_id, $mihpayid);
                }
                // if($mihpayid == 'crs'){
                $check_crs_booking_type = DB::table('crs_booking')->where('invoice_id', $invoice_id)->first();
                if (isset($check_crs_booking_type->payment_type) && $check_crs_booking_type->payment_type == 1) {
                    $check_crs_payment_status = DB::table('crs_booking')->where('invoice_id', $invoice_id)->update(['is_payment_received' => 1]);
                }
                // }


            }

            $transaction_det['payment_mode'] = $payment_mode;
            $transaction_det['invoice_id'] = $invoice_id;
            $transaction_det['email_id'] = isset($user_data->email_id) ? $user_data->email_id : 'test@gmail.com';
            $transaction_det['transaction_id'] = $txnid;
            $transaction_det['payment_id'] = $mihpayid;
            $transaction_det['secure_hash'] = $hash;

            $result = OnlineTransactionDetail::updateOrInsert(
                ['invoice_id' => $invoice_id],
                $transaction_det
            );

            if ($result) {
                $hotel_id       = $invoice_details['hotel_id'];
                $company_dtls   = HotelInformation::where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls->company_id;

                $hotel_details = HotelInformation::where('hotel_id', $hotel_id)->select('whatsapp_no', 'hotel_name', 'whatsapp_notification_enabled')->first();
                $booking_id = date('dmy') . $invoice_id;

                if (isset($hotel_details->whatsapp_notification_enabled) && $hotel_details->whatsapp_notification_enabled == 1) {
                    $user_details = User::where('user_id', $invoiceData->user_id)->select('first_name', 'mobile')->first();

                    $message = array(
                        "hotel_name" => $hotel_details->hotel_name,
                        "guest_name" => $user_details->first_name,
                        "phone_number" => $user_details->mobile,
                        "booking_id" => $booking_id,
                        "booking_voucher_url" => 'https://pms.bookingjini.com/booking-voucher/' . $booking_id . '/' . $invoiceData->booking_source
                    );

                    // $to = '919338845287';
                    // $to1 = '8073196221';
                    // $to2 = '91' . $hotel_details->whatsapp_no;
                    // $whatsapp_sent = $this->sendWhatsAppBookingNotificationToHoteler($to, $message);
                    // $whatsapp_sent = $this->sendWhatsAppBookingNotificationToHoteler($to1, $message);
                    // $whatsapp_sent = $this->sendWhatsAppBookingNotificationToHoteler($to2, $message);
                }

                $failure_message = "Booking failed";
                $CompanyDetaiils   = CompanyDetails::where('company_id', $company_id)->first();
                if ($CompanyDetaiils) {
                    return $CompanyDetaiils->company_url;
                } else {
                    return '';
                }
            }
        } catch (\Exception $e) {

            $res = array('status' => -1, 'response_msg' => $e->getMessage());
            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => 'invoice_id =' . $invoice_id, 'line_number' => $e->getLine(), 'end_point' => $_SERVER['REQUEST_URI'], 'request' => date("YmdHis"));
            // $result = json_encode($result);
            // $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);
            // $logpath = storage_path("logs/jigyansratesnew.log".date("Y-m-d"));
            // $logfile = fopen($logpath, "a+");
            // fwrite($logfile,"\n\n\n".date("YmdHis").json_encode($_SERVER['REQUEST_URI'])."\n\n\n");
            // fclose($logfile);
            return response()->json($res);
        }
    }


    public function successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid)
    {

        try {
            $invoice_model = new Invoice();
            $transaction_model = new OnlineTransactionDetail();
            if ($mihpayid != "true" && $mihpayid != "crs" && $mihpayid != "quickpayment" && $mihpayid != "package_crs" && $mihpayid != "package_yes" && $mihpayid != 'modify' && $mihpayid != 'pay_at_hotel') {

                $hotel_dl_info = Invoice::select('*')->where('invoice_id', $invoice_id)->first();
                $hotel_id = $hotel_dl_info->hotel_id;
                $user_id = $hotel_dl_info->user_id;
                $user_data = User::where('user_id', $user_id)->orderBy('user_id', 'DESC')->first();
                $dta_avail = $transaction_model->where('invoice_id', $invoice_id)->where('email_id', $user_data->email_id)->orderBy('tr_id', 'DESC')->first();

                $booking_info   = array(
                    "invoice_id" => $invoice_id,
                    "transaction_id" => $txnid
                );

                $check_exist = ProductPrice::select('*')->where('invoice_id', $invoice_id)->first();

                if ($check_exist) {
                    $booking_data = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                        ->join('kernel.user_table as user_table', 'user_table.user_id', '=', 'hotel_booking.user_id')
                        ->join('booking_assure_table', 'booking_assure_table.invoice_id', '=', 'invoice_table.invoice_id')
                        ->where('invoice_table.invoice_id', $invoice_id)
                        ->select('hotel_booking.check_in', 'hotel_booking.booking_date', 'user_table.first_name', 'user_table.last_name', 'booking_assure_table.*')
                        ->first();
                    if ($booking_data) {
                        $today = date('Y-m-d');
                        $check_in = date('Y-m-d', strtotime($booking_data->check_in));
                        if ($check_in > $today) {
                            $booking_id = date('dmy', strtotime($booking_data->booking_date)) . $invoice_id;
                            $booking_mode = $booking_data->refundable_cancellation_mode;
                            $client = new \GuzzleHttp\Client();
                            $apiEndpoint = "https://api.protectgroup.co/api/v1/RefundProtect/salesoffering";
                            // $apiEndpoint = "https://test.api.protectgroup.co/api/v1/refundprotect/salesoffering"; 
                            $product_data = array(
                                "productCode"           => $booking_data->product_code,
                                "currencyCode"          => $booking_data->currency_code,
                                "productPrice"          => $booking_data->product_price,
                                "premiumRate"           => $booking_data->premium_rate,
                                "offeringMethod"        => $booking_data->offering_method,
                                "sold"                  => $booking_data->sold,
                                "insuranceEndDate"      =>  date('c', strtotime($booking_data->check_in)),
                            );
                            if ($booking_mode == 'mandatory') {
                                $request_data = array(
                                    "vendorCode" => "ven_live_0feca2c00627470797e19dfb86009bdb",
                                    "vendorSalesReferenceId" =>  $booking_id,
                                    "VendorSalesDate"       =>  date('c', strtotime($booking_data->booking_date)),
                                    "customerFirstName"     =>  $booking_data->first_name,
                                    "customerLastName"      =>  $booking_data->last_name,
                                    "products"              => [$product_data],
                                );

                                $response = $client->post($apiEndpoint, [
                                    'headers' => [
                                        'Content-Type'              => 'application/json',
                                        'X-RefundProtect-VendorId'  => 'ven_live_0feca2c00627470797e19dfb86009bdb',
                                        'X-RefundProtect-AuthToken' => 'sk_live_6aa41e1946b147af96e08220c49189b6',
                                    ],
                                    'body'                          => json_encode($request_data),
                                ]);
                            } else {
                                $request_data = array(
                                    "vendorCode" => "ven_live_ae8137b2306342c8be92196e007a460d",
                                    "vendorSalesReferenceId" =>  $booking_id,
                                    "VendorSalesDate"       =>  date('c', strtotime($booking_data->booking_date)),
                                    "customerFirstName"     =>  $booking_data->first_name,
                                    "customerLastName"      =>  $booking_data->last_name,
                                    "products"              => [$product_data],
                                );

                                $response = $client->post($apiEndpoint, [
                                    'headers' => [
                                        'Content-Type'              => 'application/json',
                                        'X-RefundProtect-VendorId'  => 'ven_live_ae8137b2306342c8be92196e007a460d',
                                        'X-RefundProtect-AuthToken' => 'sk_live_a512c2d8c03a4ad59d25f8fbeb9a784e',
                                    ],
                                    'body'                          => json_encode($request_data),
                                ]);
                            }

                            if ($response->getStatusCode() === 200) {
                                $response_status  = $response->getBody();
                                $res = ProductPrice::where('booking_assure_id', $booking_data->booking_assure_id)
                                    ->update(array('response_status' => $response_status));
                            }
                        }
                    }
                }
            }


            $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
            $invoice_details->booking_status = 1;
            if ($mihpayid == 'crs') {
                $invoice_details->booking_source = 'CRS';
            }
            if ($mihpayid == 'true') {
                $invoice_details->booking_source = 'GEMS';
            }
            if ($mihpayid == 'quickpayment') {
                $invoice_details->booking_source = 'QUICKPAYMENT';
            }
            if ($invoice_details->save()) {

                $hotel_booking_model = HotelBooking::where('invoice_id', $invoice_id)->get();
                if ($mihpayid != "crs" && $mihpayid != "quickpayment" && $mihpayid != 'otdcCrs') {
                    foreach ($hotel_booking_model as $hbm) {
                        $period     = new \DatePeriod(
                            new \DateTime($hbm->check_in),
                            new \DateInterval('P1D'),
                            new \DateTime($hbm->check_out)
                        );
                        foreach ($period as $value) {
                            $index = $value->format('Y-m-d');
                            $check = Inventory::select('no_of_rooms')
                                ->where('hotel_id', $hbm->hotel_id)
                                ->where('room_type_id', $hbm->room_type_id)
                                ->whereDate('date_from', '<=', $value)
                                ->whereDate('date_to', '>=', $value)
                                ->orderBy('inventory_id', 'DESC')
                                ->first();

                            if ($mihpayid == "true") {
                                if ($check->no_of_rooms <= 0) {
                                    $update = Invoice::where('invoice_id', $invoice_id)->where('booking_status', 1)->update(['booking_status' => 2]);
                                    return false;
                                }
                            } else {
                                if ($check->no_of_rooms <= 0 || $check->block_status == 1) {
                                    $update = Invoice::where('invoice_id', $invoice_id)->where('booking_status', 1)->update(['booking_status' => 2]);
                                    $insert_refund_data = Refund::insert(['booking_id' => $invoice_id, 'message' => "Room not available!"]);
                                    return false;
                                }
                            }
                        }
                    }
                }
                $update_inv_cm = array();

                $invoiceData = Invoice::where('invoice_id', $invoice_id)->first();
                foreach ($hotel_booking_model as $hbm) {
                    //dd($hbm);
                    $hbm->booking_status = 1;
                    $hbm->save();
                    if ($mihpayid != 'otdcCrs') {
                        if ($invoiceData->is_cm == 1) {
                            $update_inv_cm[] = $this->updateInventoryCM($hbm, $invoice_details);
                            $resp = $this->updateInventory($hbm, $invoice_details);
                        } else {
                            $resp = $this->updateInventory($hbm, $invoice_details);
                        }
                    }
                }
                if ($invoiceData->is_cm == 1) {
                    if ($update_inv_cm) {
                        foreach ($update_inv_cm as $rm_val) {
                            $invoice_id = $rm_val['invoice_id'];
                            $hotel_id = $rm_val['hotel_id'];
                            $booking_details = $rm_val['booking_details'];
                            $room_type[] = $rm_val['room_type'];
                            $rooms_qty[] = $rm_val['rooms_qty'];
                            $booking_status = $rm_val['booking_status'];
                            $modify_status = $rm_val['modify_status'];
                        }
                        $bucketupdate = $this->beConfBookingInvUpdate->bookingConfirm($invoice_id, $hotel_id, $booking_details, $room_type, $rooms_qty, $booking_status, $modify_status);
                    }
                }

                if ($mihpayid != "true" && $mihpayid != "crs" && $mihpayid != "package_crs" && $mihpayid != "quickpayment" && $mihpayid != 'modify') {
                    $this->invoiceMailNew($invoice_id);
                }
                if ($payment_mode == "true") {
                    $this->invoiceMailNew($invoice_id);
                }
                $getPMS_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'GEMS')->first();
                $hotel_info = explode(',', $getPMS_details->hotels);
                if (in_array($invoice_details->hotel_id, $hotel_info)) {
                    $pushBookignToGems = $this->pushBookingToGems($invoice_id, $mihpayid);
                }
                $getbookone_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'BookOne')->first();
                $hotel_info_bookone = explode(',', $getbookone_details->hotels);
                if (in_array($invoice_details->hotel_id, $hotel_info_bookone)) {
                    $pushBookignToBookone = $this->pushBookingToBookone($invoice_id, $mihpayid);
                }
                // if($mihpayid == 'crs'){
                $check_crs_booking_type = DB::table('crs_booking')->where('invoice_id', $invoice_id)->first();
                if (isset($check_crs_booking_type->payment_type) && $check_crs_booking_type->payment_type == 1) {
                    $check_crs_payment_status = DB::table('crs_booking')->where('invoice_id', $invoice_id)->update(['is_payment_received' => 1]);
                }
                // }
            }

            $transaction_det['payment_mode'] = $payment_mode;
            $transaction_det['invoice_id'] = $invoice_id;
            $transaction_det['email_id'] = isset($user_data->email_id) ? $user_data->email_id : 'test@gmail.com';
            $transaction_det['transaction_id'] = $txnid;
            $transaction_det['payment_id'] = $mihpayid;
            $transaction_det['secure_hash'] = $hash;


            $result = OnlineTransactionDetail::updateOrInsert(
                ['invoice_id' => $invoice_id],
                $transaction_det
            );


            if ($result) {
                $hotel_id       = $invoice_details['hotel_id'];
                $company_dtls   = HotelInformation::where('hotel_id', $hotel_id)->first();
                $company_id     = $company_dtls->company_id;
                $booking_id = date('dmy') . $invoice_id;

                $CompanyDetaiils   = CompanyDetails::where('company_id', $company_id)->first();


                // if ($hotel_id == 2600) {
                    $pg_details = DB::table('paymentgateway_details')
                    ->where('hotel_id', '=', $hotel_id)
                    ->where('is_active', '=', 1)
                    ->get();

                    if ($pg_details->isEmpty()) {
                        $booking_source = $invoice_details->booking_source;
                        $paid_amount = $invoice_details->paid_amount;     
                        
                        if ($paid_amount > 0 && ($booking_source == 'website' || $booking_source == 'google' || $booking_source == 'QUICKPAYMENT' || $booking_source == 'QUICKPAYMENT' ||  $booking_source == 'jiniassist')) {
                            $commission_percentage = $company_dtls->be_commission;
                            $total_amount = $invoice_details->total_amount;
                            $tax_amount = $invoice_details->tax_amount;
                            $amount_before_tax = $total_amount - $tax_amount;
                            $tcs_percentage = $company_dtls->be_tcs_percentage;

                            $commission_amount = ($amount_before_tax * $commission_percentage) / 100;
                            $tax_on_comission = ($commission_amount * 18) / 100;
                            $tcs_amount = ($amount_before_tax * $tcs_percentage) / 100;
                            $settlement_amount = $paid_amount - $commission_amount - $tax_on_comission - $tcs_amount;

                            $update_settlement_details = Invoice::where('invoice_id', $invoice_id)
                                ->update([
                                    'commission_amount' => $commission_amount,
                                    'tax_on_commission' => $tax_on_comission,
                                    'tcs_amount' => $tcs_amount,
                                    'settlement_amount' => $settlement_amount,
                                    'commission_percentage' => $commission_percentage
                                ]);
                        }
                    }
                // }

                if ($CompanyDetaiils) {
                    return $CompanyDetaiils->company_url;
                } else {
                    return '';
                }
            }
        } catch (\Exception $e) {

            $res = array('status' => -1, 'response_msg' => $e->getMessage());
            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => 'invoice_id =' . $invoice_id, 'line_number' => $e->getLine(), 'end_point' => $_SERVER['REQUEST_URI'], 'request' => date("YmdHis"));
            $result = json_encode($result);
            $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);
            // $logpath = storage_path("logs/jigyansratesnew.log".date("Y-m-d"));
            // $logfile = fopen($logpath, "a+");
            // fwrite($logfile,"\n\n\n".date("YmdHis").json_encode($_SERVER['REQUEST_URI'])."\n\n\n");
            // fclose($logfile);
            return response()->json($res);
        }
    }

    /*------------------------------------ SUCCESS BOOOKING (END) -----------------------------------*/
    /*------------------------------------- INVOICE MAIL (START) ------------------------------------*/
    public  function invoiceMail($id)
    {
        $hotel_info = new HotelInformation();
        $invoice        = $this->successInvoice($id);
        $invoice = $invoice[0];
        $booking_id     = date("dmy", strtotime($invoice->booking_date)) . str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
        $u = $this->getUserDetails($invoice->user_id);
        $get_transection_details = OnlineTransactionDetail::select('payment_id')->where('invoice_id', $id)->orderBy('tr_id', 'DESC')->first();
        $user_email_id = $u['email_id'];
        $hotel_contact = $hotel_info->getEmailId($invoice->hotel_id);
        $hotel_email_id = $hotel_contact['email_id'];
        $hotel_mobile = $hotel_contact['mobile'];
        $body           = $invoice->invoice;
        if ($id == 80661) {
            $subject        = $invoice->hotel_name . " Booking Cancelation";
            $body           = str_replace("Confirmation", 'Cancelation', $body);
            $body           = str_replace("CONFIRMATION", 'CANCELLATION', $body);
        } else {
            $subject        = $invoice->hotel_name . " Booking Confirmation";
        }

        $body           = str_replace("#####", $booking_id, $body);
        if (isset($get_transection_details->payment_id)) {
            $body           = str_replace(">>>>>", $get_transection_details->payment_id, $body);
        } else {
            $body           = str_replace(">>>>>", $booking_id, $body);
        }
        $hotel_id       = $invoice->hotel_id;
        $invoice_id     = $invoice->invoice_id;
        if ($hotel_id == 2065 || $hotel_id == 2881 || $hotel_id == 2882 || $hotel_id == 2883 || $hotel_id == 2884 || $hotel_id == 2885 || $hotel_id == 2886) {
            $coupon_code    = isset($invoice->agent_code) ? $invoice->agent_code : 'NA';
            if ($coupon_code != 'NA') {
                $get_email_id =  Coupons::select('agent_email_id')
                    ->where('hotel_id', $hotel_id)
                    ->where('coupon_code', '=', $coupon_code)
                    ->first();
                if (isset($get_email_id->agent_email_id) && $get_email_id->agent_email_id) {
                    $user_email_id = $get_email_id->agent_email_id;
                }
            }
        }
        $serial_no      = 0;
        $transection_id = 0;
        if ($hotel_id == 2065) {
            $serial_code = '02';
            $save_info = DB::table('eco_retreat_bhitarkanika')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = isset($save_info->id) ? $save_info->id : 0;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = isset($save_info->transaction_id) ? $save_info->transaction_id : 0;
        }
        if ($hotel_id == 2881) {
            $serial_code = '01';
            $save_info = DB::table('eco_retreat_konark')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2882) {
            $serial_code = '03';
            $save_info = DB::table('eco_retreat_satkosia')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2883) {
            $serial_code = '05';
            $save_info = DB::table('eco_retreat_daringbadi')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2884) {
            $serial_code = '04';
            $save_info = DB::table('eco_retreat_hirakud')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2885) {
            $serial_code = '06';
            $save_info = DB::table('eco_retreat_sonapur')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2886) {
            $serial_code = '07';
            $save_info = DB::table('eco_retreat_koraput')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        $body           = str_replace("*****", $serial_no, $body);
        $body           = str_replace("@@@@@", $transection_id, $body);
        $body_info      = array('invoice' => $body);
        $updated_inv_info = Invoice::where('invoice_id', $invoice_id)->update($body_info);

        if ($this->sendMail($user_email_id, $body, $subject, $hotel_email_id, $invoice->hotel_name, $invoice->hotel_id)) {
            $to = $u['mobile'];
            $hotel_id = $invoice->hotel_id;
            if ($hotel_id == 2065 || $hotel_id == 2881 || $hotel_id == 2882 || $hotel_id == 2883 || $hotel_id == 2884 || $hotel_id == 2885 || $hotel_id == 2886) {
                $hotelName = $invoice->hotel_name;
                $bookingDate = date('d M Y', strtotime($invoice->booking_date));
                $guestName = $u['first_name'];
                $bookingID = $booking_id;
                $messageToSend = "Hi $guestName, Successfully booked for $hotelName on $bookingDate with Booking Id: $bookingID. Regards, OTDCHO";
                $otdc_mob = '7008839041';
                $messageToSend = urlencode($messageToSend);
                $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?userid=531&password=oYSeaxIVK9UPgvG0&sender=OTDCHO&to=$to,$otdc_mob&message=$messageToSend&reqid=1&format={json|text}&route_id=3";
                // $ch = curl_init($smsURL);
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // $res = curl_exec($ch);
                // curl_close($ch);
            } else {
                $msg = "Your transaction has been successful(Booking ID- " . $booking_id . "). For more details kindly check your mail ID given at the time of booking.";
                if ($this->sendSMS($to, $msg)) {
                    $to = $hotel_mobile;
                    $msg = "You have got new booking From Bookingjini(Booking ID- " . $booking_id . "). For more details kindly check registered email ID.";
                    $this->sendSMS($to, $msg);
                }
                if ($invoice->hotel_id == 1602) {
                    $sendMailtoAgent = $this->agentMail($id, $hotel_email_id, $invoice->hotel_name, $booking_id);
                }
            }
            return true;
        }
    }
    /*--------------------------------------- INVOICE MAIL (END) ------------------------------------*/


    public function agentMail($invoice_id, $hotel_email_id, $hotel_name, $booking_id)
    {
        return true;
        $subject = $hotel_name . " Booking Confirmation";
        $rateLogs = DB::select("CALL getAgentDetails('" . $invoice_id . "')");
        if (isset($rateLogs[0]->username) && sizeof($rateLogs[0]) > 0) {
            $email = $rateLogs[0]->username;
            $template = $rateLogs[0]->invoice;
            $template  = str_replace("#####", $booking_id, $template);
            $data = array('email' => $email, 'subject' => $subject, 'template' => $template, 'hotel_name' => $hotel_name, 'hotel_email' => $hotel_email_id);
            Mail::send([], [], function ($message) use ($data) {
                $message->to($data['email'])
                    ->cc($data['hotel_email'])
                    ->from(env("MAIL_FROM"), 'Agent Bookings')
                    ->subject($data['subject'])
                    ->setBody($data['template'], 'text/html');
            });
            if (Mail::failures()) {
                return false;
            }
            return true;
        }
    }

    /*------------------------------------- Send SMS after success booking (START) ------------------------------------*/
    public function sendSMS($to, $msg)
    {
        $messageToSend = $msg;
        $messageToSend = urlencode($messageToSend);
        $date = Date('d-m-Y\TH:i:s');
        $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?username=1135&password=oYSeaxIVK9UPgvG0&sender=BKJINI&to=" . $to . "&message=" . $messageToSend . "&reqid=1&format={json|text}&route_id=TRANS-OPT-IN&sendondate=" . $date;
        $ch = curl_init();
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set the url
        curl_setopt($ch, CURLOPT_URL, $smsURL);
        // Execute
        $result = curl_exec($ch);
        // Closing
        curl_close($ch);

        return true;
    }
    /*------------------------------------- Send SMS after success booking (END) ------------------------------------*/

    /*------------------------------------- UPDATE INVENTORY (START) ------------------------------------*/
    public  function updateInventory($bookingDeatil, $invoice_details)
    {
        $mindays = 0;
        $updated_inv = array();
        $inventory = new Inventory();
        $inv_update = array();
        $inv_data = $this->invService->getInventeryByRoomTYpe($bookingDeatil->room_type_id, $bookingDeatil->check_in, $bookingDeatil->check_out, $mindays);

        if ($inv_data) {
            foreach ($inv_data as $inv) {
                $updated_inv["invoice_id"] = $bookingDeatil->invoice_id;
                $updated_inv["hotel_id"] = $bookingDeatil->hotel_id;
                $updated_inv["room_type_id"] = $inv['room_type_id'];
                $updated_inv["user_id"] = 0; //User id set CM
                $updated_inv["date_from"] = $inv['date'];
                $updated_inv["date_to"] = date('Y-m-d', strtotime($inv['date']));
                $updated_inv["no_of_rooms"] = $inv['no_of_rooms'] - $bookingDeatil->rooms; //Deduct inventory
                $updated_inv["room_qty"] = $bookingDeatil->rooms;
                $room_type[] = $inv['room_type_id'];
                $rooms_qty[] = $bookingDeatil->rooms;
                $updated_inv["client_ip"] = '1.1.1.1'; //\Illuminate\Http\Request::ip();//As server is updating inventory automatically afetr succ booking
                $updated_inv["ota_id"] = 0; //Don't Remove this
                $resp = $this->updateInventoryService->updateInv($updated_inv, $invoice_details);
                if ($resp['be_status'] = "Inventory update successfull") {
                    array_push($inv_update, 1);
                }
            }
            // $index = sizeof($inv_data);
            // $booking_details=[];
            // $booking_details['checkin_at'] =date('Y-m-d', strtotime($inv_data[0]['date']));
            // $booking_details['checkout_at'] =date('Y-m-d', strtotime($inv_data[$index-1]['date'].'+1 day'));
            // $invoice_id = $bookingDeatil->invoice_id;
            // $booking_status = $invoice_details['booking_status'];
            // $modify_status = $invoice_details['modify_status'];
            // $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();
            // if($invoiceData->is_cm==1){
            //       $bucketupdate=$this->beConfBookingInvUpdate->bookingConfirm($invoice_id,$bookingDeatil->hotel_id,$booking_details,$room_type,$rooms_qty,$booking_status,$modify_status);
            // }
        }
        $inv_update_status = true;
        foreach ($inv_update as $up) {
            if (!$up == 1) {
                $inv_update_status = false;
            }
        }
        return  $inv_update_status;
    }
    /*--------------------------------------- UPDATE INVENTORY (END) ------------------------------------*/
    public function updateInventoryCM($bookingDeatil, $invoice_details)
    {
        $mindays = 0;
        $updated_inv = array();
        $inventory = new Inventory();
        $inv_update = array();
        $inv_data = $this->invService->getInventeryByRoomTYpe($bookingDeatil->room_type_id, $bookingDeatil->check_in, $bookingDeatil->check_out, $mindays);

        if ($inv_data) {
            foreach ($inv_data as $inv) {
                $room_type = $inv['room_type_id'];
            }
            $rooms_qty = $bookingDeatil->rooms;
            $index = sizeof($inv_data);
            $booking_details = [];
            $booking_details['checkin_at'] = date('Y-m-d', strtotime($inv_data[0]['date']));
            $booking_details['checkout_at'] = date('Y-m-d', strtotime($inv_data[$index - 1]['date'] . '+1 day'));
            $invoice_id = $bookingDeatil->invoice_id;
            $booking_status = $invoice_details['booking_status'];
            $modify_status = $invoice_details['modify_status'];
            $invoiceData = Invoice::where('invoice_id', $invoice_id)->first();
            $update_inv_cm_info = array(
                "invoice_id"      => $invoice_id,
                "hotel_id"        => $bookingDeatil->hotel_id,
                "booking_details" => $booking_details,
                "room_type"       => $room_type,
                "rooms_qty"       => $rooms_qty,
                "booking_status"  => $booking_status,
                "modify_status"   => $modify_status
            );
            return $update_inv_cm_info;
        }
    }
    /*----------------------------------- RESPONSE FROM PAYU (START) ----------------------------------*/

    public function payuResponse(Request $request)
    {
        $data = $request->all();
        $status     = $data['status'];
        $email      = $data['email'];
        $firstname    = $data['firstname'];
        $productinfo  = $data['productinfo'];
        $amount     = $data['amount'];
        $txnid      = $data['txnid'];
        $hash       = $data['hash'];
        $payment_mode   = $data['mode'];
        $mihpayid     = $data['mihpayid'];
        $curdate    = date('Ymd');
        $hashData     = 'sHFyCrYD|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|HpCvAH';
        if (strlen($hashData) > 0) {
            $secureHash = hash('sha512', $hashData);
            if (strcmp($secureHash, $hash) == 0) {
                if ($status == 'success') {
                    $id = str_replace($curdate, '', $txnid);
                    $url = $this->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);

                    if ($url != '') {
?>
                        <html>

                        <body onLoad="document.response.submit();">
                            <div style="width:300px; margin: 200px auto;">
                                <img src="loading.png" />
                            </div>
                            <form action="http://<?= $url ?>/v3/ibe/#/invoice-details/<?= $id ?>/<?= $txnid ?>" name="response" method="GET">
                            </form>
                        </body>

                        </html>
                    <?php
                    } else {
                        echo '<h1>Error!</h1>';
                        echo '<p>Database error</p>';
                    }
                } else {
                    ?>
                    <script>
                        alert("Sorry! Trasaction not completed");
                    </script>
<?php
                    header('Location: index.php');
                }
            } else {
                echo '<h1>Error!</h1>';
                echo '<p>Hash validation failed</p>';
            }
        } else {
            echo '<h1>Error!</h1>';
            echo '<p>Invalid response</p>';
        }
    }

    /*------------------------------------ RESPONSE FROM PAYU (END) -----------------------------------*/

    /*------------------------------------ AFTER SUCCESS VIEW INVOICE -----------------------------------*/
    public function invoiceDetails(int $invoice_id, Request $request)
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
            $res = array('status' => 1, "message" => "Invoice feteched sucesssfully!", 'data' => $body);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Invoice details not found");
            return response()->json($res);
        }
    }
    //BE Calendar Inventory and rates
    public function beCalendar(string $api_key, int $hotel_id, string $startDate, string $currency_name, Request $request)
    {
        $startDate = date('Y-m-d', strtotime($startDate));
        $result = array();
        $date_from = $startDate;
        for ($i = 0; $i < 15; $i++) {
            $d = $date_from;
            $d1 = date('Y-m-d', strtotime($d . ' +1 day'));
            $inv_array = $this->getInvByHotelCalender($api_key, $hotel_id, $d, $d1, $currency_name, $request);
            $inv_array = $inv_array->getData();
            $inv = $inv_array->data;
            $avail = 0;
            // $price='unavailable';
            $price = 0;
            $room_type_id = 0;
            $date = $d;
            foreach ($inv as $in) {
                if ($in->min_room_price == 0) {
                    continue;
                }
                if (sizeof($in->inv) > 0) {
                    if ($in->inv[0]->no_of_rooms != 0 && $in->inv[0]->block_status == 0 && $in->min_room_price != 0) {
                        $avail = 1;
                        if ($price == 0) {
                            $price = $in->min_room_price;
                            $room_type_id = $in->room_type_id;
                        } elseif ($price > $in->min_room_price) {
                            $price = $in->min_room_price;
                            $room_type_id = $in->room_type_id;
                        }
                        $date = $in->inv[0]->date;
                    }
                }
            }

            $result[] = array("date" => $date, "avail" => $avail, "price" => $price, "room_type_id" => $room_type_id);
            $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        foreach ($result as $key => $val) {

            if ($val["price"] === -1) {
                $result[$key]["price"] = $result[29]["price"];
            }
        }
        $res = array('status' => 1, "message" => "Invoice feteched sucessfully!", 'data' => $result);
        return response()->json($res);
    }
    //Get tax details if Base currency other INR
    public function getTaxDetails(string $company_id, string $hotel_id, Request $request)
    {
        $currencyname = CompanyDetails::where('company_id', $company_id)->select('currency')->first();
        if ($currencyname) {
            if ($currencyname->currency != 'INR') {
                if ($hotel_id == 1001) {
                    $tax_details['tax_type'] = 'GST';

                    //Changes for getting pay at hotel value
                    $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
                    $finDetails = TaxDetails::where($conditions)->select('tax_pay_hotel')->first();
                    if ($finDetails) {
                        $tax_details['tax_pay_hotel'] = $finDetails->tax_pay_hotel;
                    } else {
                        $tax_details['tax_pay_hotel'] = 0;
                    }
                    //Changes for getting pay at hotel value

                    $res = array('status' => 1, "message" => "Tax details fetched successfully!", 'tax_details' => $tax_details);
                    return response()->json($res);
                }
                $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
                $finDetails = TaxDetails::where($conditions)->select('tax_name', 'tax_percent')->first();
                if ($finDetails) {
                    $tax_name_arr = explode(',', $finDetails->tax_name);
                    $tax_percent_arr = explode(',', $finDetails->tax_percent);
                    $finace_details = array();
                    foreach ($tax_name_arr as $key => $tax) {
                        array_push($finace_details, array('tax_name' => $tax_name_arr[$key], 'tax_percent' => $tax_percent_arr[$key]));
                    }
                    $tax_details['tax_type'] = 'NonGST';
                    $tax_details['tax_applicable'] = true;
                    $tax_details['tax_rules'] = $finace_details;
                    $res = array('status' => 1, "message" => "Tax details fetched successfully!", 'tax_details' => $tax_details);
                    return response()->json($res);
                } else {
                    $tax_details['tax_type'] = 'NonGST';
                    $tax_details['tax_applicable'] = false;
                    $res = array('status' => 1, "message" => "Tax details fetched successfully!", 'tax_details' => $tax_details);
                    return response()->json($res);
                }
            } else {
                $tax_details['tax_type'] = 'GST';

                //Changes for getting pay at hotel value
                $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
                $finDetails = TaxDetails::where($conditions)->select('tax_pay_hotel')->first();
                if ($finDetails) {
                    $tax_details['tax_pay_hotel'] = $finDetails->tax_pay_hotel;
                } else {
                    $tax_details['tax_pay_hotel'] = 0;
                }
                //Changes for getting pay at hotel value

                $res = array('status' => 1, "message" => "Tax details fetched successfully!", 'tax_details' => $tax_details);
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, "message" => "Tax details fetching failed!");
            return response()->json($res);
        }
    }
    /*
    Get room amenity for showing in room details
    * @author Rajashree
    * function getRoomdetails for fetching data
    **/
    public function getRoomamenity($ammenityId)
    {
        $amenities = HotelAmenities::whereIn('hotel_amenities_id', $ammenityId)
            ->select('hotel_amenities_name', 'font_class', 'hotel_amenities_id')
            ->get();
        return ($amenities) ? $amenities : array();
    }
    /*
    Get discount of the room By room type id
    * @author Rajashree
    * function getInvByHotel for fetching data
    **/
    public function getAllPublicCuponsTest(String $hotel_id, $from_date, $to_date, Request $request)
    {

        $begin = strtotime($from_date);
        $end = strtotime($to_date);
        $from_date = date('Y-m-d', strtotime($from_date));
        $data_array = array();
        for ($currentDate = $begin; $currentDate < $end; $currentDate += (86400)) {
            $data_array_present = array();
            $data_array_notpresent = array();
            $status = array();
            $Store = date('Y-m-d', $currentDate);

            $get_data = DB::select(DB::raw('select coupon_id,room_type_id,
        case when room_type_id != 0 then "present" when room_type_id = 0 then "notpresent" end as status,
        coupon_name,coupon_code,valid_from,
        valid_to,discount_type,coupon_for,discount,a.date,a.abc
        FROM
        (
        select t2.coupon_id,
        case when t2.room_type_id!=0 then t2.room_type_id else 0 end as room_type_id,
        coupon_name,coupon_code,valid_from,valid_to,discount_type,coupon_for,discount,"' . $Store . '" as date,
        case when "' . $Store . '" between valid_from and valid_to then "yes" else "no" end as abc
        from booking_engine.coupons
        INNER JOIN
        (
        SELECT room_type_id,
        substring_index(group_concat(cast(coupon_id as CHAR) order by discount desc), ",", 1 ) as coupon_id,MAX(discount)
        FROM booking_engine.coupons where hotel_id = "' . $hotel_id . '" and coupon_for = 1 and is_trash = 0
        and ("' . $Store . '" between valid_from and valid_to)
        GROUP BY room_type_id
        order by coupon_id desc
        ) t2 ON coupons.room_type_id = t2.room_type_id AND coupons.coupon_id = t2.coupon_id
        ) AS a where a.abc = "yes"
        order by room_type_id,coupon_id desc'));

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
        if (sizeof($data_array) > 0) {
            $res = array('status' => 1, 'message' => "Public coupon retrieved successfully", 'data' => $data_array);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "!Sorry Public coupon is not available", 'data' => array());
            return response()->json($res);
        }
    }


    //fetching coupon for public
    public function AllPublicCupon(string $hotel_id, $from_date, $to_date, Request $request)
    {
        $dates = $this->date_range($from_date, $to_date, "+1 day", "Y-m-d");
        $coup;
        foreach ($dates as $date) {
            $cupons = Coupons::select('room_type_id', 'discount', 'coupon_code', 'discount_type', 'coupon_id', 'valid_from', 'valid_to')
                ->where('hotel_id', $hotel_id)
                ->where('is_trash', 0)
                ->where('coupon_for', 1)
                ->whereDate('valid_from', '<=', $date)
                ->whereDate('valid_to', '>=', $date)
                ->get();
            if (sizeof($cupons) > 0) {
                $coup = $cupons;
            }
        }
        if (is_object($coup) && sizeof($coup) > 0) {
            $res = array('status' => 1, 'message' => "Public coupon retrieved successfully", 'data' => $coup);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "!Sorry no public coupon", 'data' => array());
            return response()->json($res);
        }
    }
    function date_range($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
    {
        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {

            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }
    public function testSuccessBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid, Request $request)
    {
        $this->successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid);
    }

    /**
     * objective:check the rateplan is exist or not
     * auther @ranjit
     * date-02/04/2019
     */
    public function checkroomrateplan($room_type_id, $rate_plan_id, $date_from, $date_to)
    {
        $getstatus = MasterHotelRatePlan::select('*')
            ->where(['rate_plan_id' => $rate_plan_id])
            ->where(['room_type_id' => $room_type_id])
            ->where('is_trash', 0)
            ->where('from_date', '<=', $date_from)
            ->where('to_date', '>=', $date_from)
            ->where('be_rate_status', 0)
            ->first();
        if ($getstatus) {
            return 1;
        } else {
            return 0;
        }
    }

    /*------------------------------------- Booking.com review api ------------------------------------*/
    public function getReviewFromBookingDotCom($property_id, Request $request)
    {

        $properties  = [9192225, 8456854];

        if (!in_array($property_id, $properties)) {
            $headers = array(
                //Regulates versioning of the XML interface for the API
                'Content-Type: application/json',
                'Authorization:Basic Qm9va2luZ2ppbmktY2hhbm5lbG1hbmFnZXI6d1N6bldPPzJ3eS9eLWovaGZVS15NQ3E/OkEqRUspQkJYU01LLS4qKQ=='
            );
            $url = "https://supply-xml.booking.com/review-api/properties/$property_id/reviews?limit=10";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        } else {
            $res = array('status' => 0, 'message' => "Review not found");
            return response()->json($res);
        }
    }
    //Get Booking.com hotel code
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

    public function getOtaWiseRates(Request $request)
    {
        $data = $request->all();
        $date = $data['date'];
        $date = date('Y-m-d', strtotime($data['date']));
        $data['date'] = $date;
        $to_date = date('Y-m-d', strtotime($date . ' +1 day'));
        $hotel_id = $data['hotel_id'];
        $resp = '';
        if ($hotel_id == 42 || $hotel_id == 1953) {
            return response()->json(array("status" => 0, "message" => "Ota rates not found"));
        }
        if ($hotel_id == 2078) {
            return response()->json(array("status" => 0, "message" => "Ota rates not found"));
        }
        $api_key = $data['api_key'];
        $currency_name = $data['currency'];
        $inv_array = $this->getInvByHotel($api_key, $hotel_id, $date, $to_date, $currency_name, $request);
        $inv_array = $inv_array->getData();
        $min_room_price = 0;
        $min_rack_price = 0;
        $coupon_resp = $this->getAllPublicCupons($hotel_id, $date, $to_date, $request);
        $coupon_resp = json_decode($coupon_resp->getContent(), true);
        $couponPercent = 0;
        foreach ($inv_array->data as $inv) {
            if ($inv->inv[0]->no_of_rooms != 0 && $inv->inv[0]->block_status == 0 && $inv->min_room_price != 0) {
                if ($min_room_price == 0) {
                    $min_room_price = $inv->min_room_price;
                    $min_rack_price = $inv->rack_price;
                }
                if ($inv->min_room_price != 0 && $inv->min_room_price  < $min_room_price) {
                    $min_room_price = $inv->min_room_price;
                }
                if ($min_rack_price == 0) {
                    $min_rack_price = $inv->rack_price;
                }
                if ($inv->rack_price != 0 && $inv->rack_price  < $min_rack_price) {
                    $min_rack_price = $inv->rack_price;
                }

                if (isset($coupon_resp['data'])) {
                    foreach ($coupon_resp['data'] as $coupons) {
                        if ($coupons['room_type_id'] == $inv->room_type_id || $coupons['room_type_id'] == 0) {
                            if ($coupons['coupon_for'] == 1) {
                                if ($coupons['discount'] > $couponPercent) {
                                    $couponPercent = $coupons['discount'];
                                }
                            }
                        }
                    }
                }
            }
        }
        if (isset($couponPercent) && $couponPercent != 0) {
            $min_room_price = $min_room_price - ($min_room_price * $couponPercent / 100);
            $min_room_price = number_format((float)$min_room_price, 2, '.', '');;
        }
        if ($min_room_price == 0) {
            return response()->json(array("status" => 0, "message" => "Room not available"));
        }
        $found = false;
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0, 'be_room_status' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'max_room_capacity', 'image', 'rack_price', 'extra_person', 'extra_child', 'bed_type', 'room_amenities')->where($conditions)->orderBy('rack_price', 'ASC')->get();
        foreach ($room_types as $room) {
            $room_type_n_rate_plans = MasterHotelRatePlan::join('rate_plan_table as a', 'room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id')
                ->select('a.rate_plan_id', 'plan_type', 'plan_name')
                ->where('room_rate_plan.hotel_id', $hotel_id)
                ->where('room_rate_plan.is_trash', 0)
                ->where('room_rate_plan.room_type_id', $room['room_type_id'])
                ->orderBy('room_rate_plan.created_at', 'ASC')
                ->distinct()
                ->get();
            if (sizeof($room_type_n_rate_plans) <= 0) {
                continue;
            }
            $bar_price = 0;
            $repeat = false;
            foreach ($room_type_n_rate_plans as $room_type_n_rate_plan) {
                $room_type_id = $room['room_type_id'];
                $rate_plan_id = $room_type_n_rate_plan['rate_plan_id'];
                $date = date('Y-m-d', strtotime($data['date']));
                $request->request->add(['room_type_id' => $room['room_type_id']]);
                $request->request->add(['rate_plan_id' => $room_type_n_rate_plans[0]['rate_plan_id']]);
                $request->request->add(['date' => $date]);
                $check_room = Inventory::select('*')
                    ->where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room['room_type_id'])
                    ->orderBy('inventory_id', 'DESC')
                    ->first();

                if (!$check_room) {
                    continue;
                }
                if ($check_room->block_status == 0) {
                    $resp = $this->otaInvRateService->rateData($request);
                    if ($hotel_id == 98) {
                        $data_rate = $this->invService->getRatesByRoomnRatePlan($room_type_id, $rate_plan_id, $date, $to_date);
                        if ($bar_price == 0) {
                            $bar_price = $data_rate[0]['bar_price'];
                        } else {
                            if ($data_rate[0]['bar_price'] < $bar_price) {
                                $bar_price = $data_rate[0]['bar_price'];
                            }
                        }
                    }
                } else {
                    continue;
                }
                if ($resp) {
                    $resp = json_decode($resp->getContent(), true);
                    if (isset($resp['data'])) {
                        $otaRates = $resp['data'];
                        foreach ($otaRates as $key => $otaRate) {
                            if ($otaRate[0] != 'same as panel' &&  $otaRate[0] != "-") {
                                $found = true;
                            }
                            if ($otaRate[0] == 0) {
                                $repeat = true;
                            }
                        }
                        if ($found) {
                            $rate_data = $this->invService->getRatesByRoomnRatePlan($room_type_id, $rate_plan_id, $date, $to_date);
                            foreach ($otaRates as $key => $otaRate) {
                                if ($otaRate[0] == 'same as panel' || $otaRate[0] == "-") {
                                    if (isset($rate_data[0]['bar_price'])) {
                                        $otaRates[$key][0] = $rate_data[0]['bar_price'];
                                    }
                                }
                            }
                            if ($bar_price > 0) {
                                $min_room_price = $bar_price;
                                $min_room_price = $min_room_price - ($min_room_price * $couponPercent / 100);
                                $min_room_price = number_format((float)$min_room_price, 2, '.', '');
                                $min_rack_price = $bar_price;
                            }
                            $otaRates["BE"] = array('min_rack_price' => $min_rack_price, 'min_room_price' => $min_room_price);
                            $resp['data'] = $otaRates;
                        }
                    } else {
                        return response()->json(array("status" => 0, "message" => "Ota rates not found"));
                    }
                } else {
                    return response()->json(array("status" => 0, "message" => "Ota rates not found"));
                }
            }
            if ($repeat) {
                continue;
            }
            return $resp;
        }
        if ($found == false) {
            $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'max_room_capacity', 'image', 'rack_price', 'extra_person', 'extra_child', 'bed_type', 'room_amenities')->where($conditions)->orderBy('rack_price', 'ASC')->get();
            foreach ($room_types as $room) {
                $room_type_id_be = $room->room_type_id;
                $check_room = Inventory::select('*')
                    ->where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id_be)
                    ->orderBy('inventory_id', 'DESC')
                    ->first();
                if (isset($check_room->block_status) && $check_room->block_status  == 1) {
                    continue;
                }
                $bar_price = 0;
                $room_type_n_rate_plans = MasterHotelRatePlan::join('rate_plan_table as a', function ($join) {
                    $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                })
                    ->select('a.rate_plan_id', 'plan_type', 'plan_name')
                    ->where('room_rate_plan.hotel_id', $hotel_id)
                    ->where('room_rate_plan.is_trash', 0)
                    ->where('room_rate_plan.room_type_id', $room_type_id_be)
                    ->orderBy('room_rate_plan.created_at', 'ASC')
                    ->distinct()
                    ->get();
                $rate_plan_id = $room_type_n_rate_plans[0]['rate_plan_id'];
                $request->request->add(['room_type_id' =>  $room_type_id_be]);
                $request->request->add(['rate_plan_id' => $rate_plan_id]);
                $resp_inv = $this->otaInvRateService->rateData($request);
                if ($hotel_id == 98) {
                    $rate_data = $this->invService->getRatesByRoomnRatePlan($room_type_id_be, $rate_plan_id, $date, $to_date);
                    if ($bar_price == 0) {
                        $bar_price = $data_rate[0]['bar_price'];
                    } else {
                        if ($data_rate[0]['bar_price'] < $bar_price) {
                            $bar_price = $data_rate[0]['bar_price'];
                        }
                    }
                }
                if ($resp_inv) {
                    $resp_inv = json_decode($resp_inv->getContent(), true);
                    if (isset($resp_inv['data'])) {
                        $otaRates = $resp_inv['data'];
                        foreach ($otaRates as $key => $otaRate) {
                            if ($otaRate[0] === 'same as panel' || '-') {
                                $rate_data = $this->invService->getRatesByRoomnRatePlan($room_type_id_be, $rate_plan_id, $date, $to_date);
                                if (isset($rate_data[0]['bar_price'])) {
                                    $otaRates[$key][0] = $rate_data[0]['bar_price'];
                                }
                            }
                        }
                        if ($bar_price > 0) {
                            $min_room_price = $bar_price;
                            $min_room_price = $min_room_price - ($min_room_price * $couponPercent / 100);
                            $min_room_price = number_format((float)$min_room_price, 2, '.', '');
                            $min_rack_price = $bar_price;
                        }
                        $otaRates["BE"] = array('min_rack_price' => $min_rack_price, 'min_room_price' => $min_room_price);
                        $resp_inv['data'] = $otaRates;
                        return response()->json($resp_inv);
                    } else {
                        return response()->json(array("status" => 0, "message" => "Ota rates not found"));
                    }
                } else {
                    return response()->json(array("status" => 0, "message" => "Ota rates not found"));
                }
            }
        }
    }
    /*--------------------------------------- INVOICE MAIL (END) ------------------------------------*/
    /*-------------------------------------INVOICE DATA (GOOGLE)------------------------------------*/
    public function fetchInvoiceData($invoice_id, Request $request)
    {
        $hotel_id;
        $checkin_date;
        $checkout_date;
        $query      = "Select DISTINCT(a.invoice_id),a.ref_no,a.hotel_id,a.total_amount,a.paid_amount,a.ref_from,b.user_id, b.room_type_id, a.booking_date, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond,e.image_name,d.home_url from invoice_table a, hotel_booking b, kernel.hotels_table c, kernel.company_table d, kernel.image_table e where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND c.company_id=d.company_id AND d.logo=e.image_id AND a.invoice_id=$invoice_id";
        $result    = DB::select($query);
        $symbols = ['INR' => "fa fa-inr", 'EUR' => "fa fa-eur", 'GBP' => "fa-gbp", 'BDT' => "fa fa-bdt"];
        foreach ($result as $data) {
            $hotel_id = $data->hotel_id;
            $checkin_date = substr($data->check_in_out, 1, 10);
            $checkout_date = str_replace("]", "", substr($data->check_in_out, 12, 21));
            $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;
            $date1 = date_create($checkin_date);
            $date2 = date_create($checkout_date);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            $data->checkin_date = $checkin_date;
            $data->checkout_date = $checkout_date;
            $data->length_of_stay = $diff;
            $data->currency_code = $baseCurrency;
            $data->total_price = $data->total_amount;
            $data->total_amount = $data->paid_amount;
            $booking_date = date('dmy', strtotime($data->booking_date));
            $data->booking_id = $booking_date . $invoice_id;
            $check_slash = mb_substr($data->home_url, -1);
            if ($check_slash == '/') {
                $data->veu_home_url = $data->home_url;
            } else {
                $data->veu_home_url = $data->home_url;
            }
            foreach ($symbols as $key => $symbol) {
                if ($baseCurrency == $key) {
                    $data->currency_symbol = $symbol;
                } else {
                    $data->currency_symbol = "fa fa-inr";
                }
            }
        }
        if ($result) {
            $res = array('status' => 1, "message" => "Invoice data feteched sucesssfully!", 'data' => $result);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Invoice details not found");
            return response()->json($res);
        }
    }
    public function testIdsFlow(Request $request)
    {
        $cart = $request->input('cart');
        $resp = $this->handleIds($cart, '2021-01-26', '2021-02-05', '2021-01-25', 2142, 2142, 'Commit');
    }
    public function handleIds($cart, $from_date, $to_date, $booking_date, $hotel_id, $user_id, $booking_status)
    {
        $ids_status = $this->idsService->getIdsStatus($hotel_id);
        if ($ids_status) {
            $ids_data = $this->prepare_ids_data($cart, $from_date, $to_date, $booking_date, $hotel_id);
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
    public function prepare_ids_data($cart, $from_date, $to_date, $booking_date, $hotel_id)
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
                    $gst_price = $this->getGstPrice(1, 1, $cartItem['room_type_id'], $amount); //TO get the GSt price
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
        return $booking_data;
    }
    // public function handleKtdc($cart,$from_date,$to_date,$booking_date,$hotel_id,$user_id,$booking_status){
    //     $ids_status=$this->idsService->getKtdcStatus($hotel_id);

    //     if($ids_status){
    //         $ids_data=$this->prepare_ktdc_data($cart,$from_date,$to_date,$booking_date,$hotel_id);

    //         $customer_data=$this->getUserDetails($user_id)->toArray();
    //         $type="Bookingjini";
    //         $last_ids_id=$this->idsService->ktdcBookings($hotel_id,$type,$ids_data,$customer_data,$booking_status);
    //         if($last_ids_id){
    //             return $last_ids_id;
    //         }
    //         else{
    //             return 0;
    //         }
    //     }
    // }
    // public function prepare_ktdc_data($cart,$from_date,$to_date,$booking_date,$hotel_id){
    //     $booking_data=array();
    //     $booking_data['booking_id']='#####';//Intially Booking id not known ,After successful boooking Only booking id Set to this
    //     $booking_data['room_stay']=array();
    //     $date1=date_create($from_date);
    //     $date2=date_create($to_date);
    //     $diff=date_diff($date1,$date2);
    //     $diff=$diff->format("%a");
    //     $no_of_rooms=0;
    //     foreach($cart as $cartItem){
    //         $rates_arr=array();
    //         $gst_price=0;
    //         $total_adult=0;
    //         $total_child=0;
    //         $no_of_rooms=sizeof($cartItem['rooms']);

    //         foreach($cartItem['rooms'] as $rooms){
    //             $ind_total_price=0;
    //             $frm_date=$from_date;
    //             $total_adult=$rooms['selected_adult'];
    //             $ind_total_price=$rooms['bar_price']+$rooms['extra_adult_price']+$rooms['extra_child_price'];
    //             $rates_arr=array();
    //             for($j=1;$j<=$diff;$j++){
    //                 $amount=0;
    //                 $gst_price=0;
    //                 $d1=$frm_date;
    //                 $d2=date('Y-m-d', strtotime($d1 . ' +1 day'));
    //                 $amount=(($ind_total_price/$diff));
    //                 $gst_price=$this->getGstPrice(1,1,$cartItem['room_type_id'],$amount);//TO get the GSt price
    //                 if(strpos('.', $amount) == false){
    //                     $amount=$amount.".00";
    //                 }
    //                 array_push($rates_arr,array("from_date"=>$d1,"to_date"=>$d2,'amount'=>(int)$amount,'tax_amount'=>$gst_price));
    //                 $frm_date=date('Y-m-d', strtotime($d1 . ' +1 day'));
    //             }
    //             $arr=array('room_type_id'=>$cartItem['room_type_id'],'rate_plan_id'=>$rooms['rate_plan_id'],'adults'=>$total_adult,'from_date'=>$from_date,'to_date'=>$to_date,'rates'=>$rates_arr);
    //             array_push($booking_data['room_stay'],$arr);
    //             }
    //         }
    //     return $booking_data;
    // }
    public function sendOtp(Request $request)
    {
        $data = $request->all();
        $uniq = mt_rand(1000, 9999);
        $to = $data['mobile'];
        $messageToSend = 'Dear customer,' . $uniq . ' is your verification code,please enter this to proceed with login otherwise it will expire in 1 minute.Regards,BKJINI';
        $date = date('Y-m-d');

        $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?userid=1135&password=F4lKwI80ROA51fyq&sender=BKJINI&to=" . $to . "&message=" . $messageToSend . "&reqid=1&format={json|text}&route_id=3";


        // $ch = curl_init(); // initialize CURL
        // curl_setopt($ch, CURLOPT_POST, false); // Set CURL Post Data
        // curl_setopt($ch, CURLOPT_URL, $smsURL);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $output = curl_exec($ch);
        // curl_close($ch);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $smsURL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $output = curl_exec($ch);


        if ($output) {
            $res = array('status' => 1, 'data' => base64_encode(strval($uniq)), 'message' => $uniq, 'otp_value' => $uniq);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Otp not send');
            return response()->json($res);
        }
    }


    //added not to send otp to the mobile number

    public function sendOtpTest(Request $request)
    {
        $data = $request->all();
        $uniq = mt_rand(1000, 9999);


        if ($uniq) {
            $res = array('status' => 1, 'data' => base64_encode(strval($uniq)), 'message' => $uniq, 'otp_value' => $uniq);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Otp not send');
            return response()->json($res);
        }
    }

    //added not to send otp to the mobile number


    public function userInfo($user_id)
    {
        $UserInformation = User::select('first_name', 'last_name', 'mobile', 'email_id', 'address', 'zip_code', 'country', 'state', 'city', 'GSTIN', 'company_name')
            ->where('user_id', $user_id)
            ->first();
        return $UserInformation;
    }
    public function noOfBookings($invoice_id)
    {
        $booked_room_details = HotelBooking::select('room_type_id', 'rooms', 'check_in', 'check_out')
            ->where('invoice_id', $invoice_id)
            ->get();
        return $booked_room_details;
    }
    public function pushBookingToOTDC($otdc_bookings)
    {
        $otdc_bookings['api_key'] = 'de1cb34fddda83c3153d79d46b24cd50';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://panthanivas.com/reservations_from_gems.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $otdc_bookings,
        ));
        $response = curl_exec($curl);
        curl_close($curl);
    }
    public function pushBookingToGems($invoice_id, $gems)
    {
        $all_bookings = array();
        $getBookingDetails = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->select('hotel_booking.user_id', 'invoice_table.room_type', 'invoice_table.ref_no', 'invoice_table.extra_details', 'invoice_table.booking_date', 'invoice_table.invoice_id', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.hotel_name', 'invoice_table.hotel_id', 'company_table.currency', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_status', 'invoice_table.modify_status', 'invoice_table.booking_source', 'invoice_table.arrival_time', 'invoice_table.guest_note')
            ->distinct('hotel_booking.user_id')
            ->where('invoice_table.invoice_id', $invoice_id)
            ->where('invoice_table.ref_no', '!=', 'offline')
            ->orderBy('invoice_table.invoice_id', 'ASC')
            ->get();
        $otdc_room_type_id = array();
        $otdc_active_status = 0;
        $otdc_info_count = 0;
        $otdc_modify_status = '';
        foreach ($getBookingDetails as $key => $bk_details) {
            $rooms          = array();
            $user_id        = $bk_details->user_id;
            $ref_no         = $bk_details->ref_no;

            $User_Details   = $this->userInfo($user_id);
            $Booked_Rooms   = $this->noOfBookings($invoice_id);

            $date1 = date_create($Booked_Rooms[0]['check_out']);
            $date2 = date_create($Booked_Rooms[0]['check_in']);
            $diff = date_diff($date1, $date2);
            $no_of_nights = $diff->format("%a");

            $booking_date   = $bk_details->booking_date;
            $booking_id     = date("dmy", strtotime($booking_date)) . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

            if ($ref_no == 'offline') {
                $mode_of_payment = 'Offline';
            } else {
                $mode_of_payment = 'Online';
            }
            $room_type_plan = explode(",", $bk_details->room_type);
            $plan = array();
            for ($i = 0; $i < sizeof($room_type_plan); $i++) {
                $plan[] = substr($room_type_plan[$i], -5, -3);
            }
            $extra = json_decode($bk_details->extra_details);

            $k = 0;
            $getRoomDetails = BeBookingDetailsTable::select('*')->where('ref_no', $bk_details->ref_no)->get();
            if ($otdc_info_count == 0) {
                $get_hotel_code = CmOtaDetails::where('hotel_id', $bk_details->hotel_id)->where('ota_name', 'OTDC')->first();
                if ($get_hotel_code) {
                    $otdc_active_status = 1;
                    $otdc_info_count = 1;
                }
            }
            foreach ($getRoomDetails as $rm_key => $getRoom) {
                // if($bk_details->hotel_id  == 2319){
                $room_info          = array();
                $total_rooms        = $getRoom->rooms;
                $adult_info         = explode(',', $getRoom->adult);
                $child_info         = explode(',', $getRoom->child);
                $room_rate_info     = explode(',', $getRoom->room_rate);
                $tax_amount_info    = explode(',', $getRoom->tax_amount);
                $extra_adult_info   = explode(',', $getRoom->extra_adult);
                $extra_child_info   = explode(',', $getRoom->extra_child);
                $discount_price     = explode(',', $getRoom->discount_price);

                $total_room_rate = 0;
                $total_gst_amount = 0;
                $extra_adult_amount = 0;
                $extra_child_amount = 0;
                $total_adult = 0;
                $total_child = 0;
                for ($i = 0; $i < $total_rooms; $i++) {
                    if (isset($room_rate_info) && sizeof($room_rate_info) == 1 && $total_rooms > 1) {
                        continue;
                    }
                    if (isset($discount_price[$i]) && $discount_price[$i] != "") {
                        $room_rate_info_dlt = $room_rate_info[$i] - $discount_price[$i];
                    } else {
                        $room_rate_info_dlt = $room_rate_info[$i];
                    }
                    $room_info[] = array(
                        "ind_room_rate" => $room_rate_info_dlt,
                        "ind_tax_amount" => $tax_amount_info[$i],
                        "ind_extra_adult" => $extra_adult_info[$i],
                        "ind_extra_child" => $extra_child_info[$i],
                        "ind_adult_no" => $adult_info[$i],
                        "ind_child_no" => $child_info[$i]
                    );
                    $total_room_rate += (float)$room_rate_info_dlt;
                    $total_gst_amount += (float)$tax_amount_info[$i];
                    $extra_adult_amount += (float)$extra_adult_info[$i];
                    $extra_child_amount += (float)$extra_child_info[$i];
                    $total_adult += (int)$adult_info[$i];
                    $total_child += (int)$child_info[$i];
                }
                $rooms[] = array(
                    "room_type_id"          => $getRoom->room_type_id,
                    "room_type_name"        => $getRoom->room_type,
                    "no_of_rooms"           => $getRoom->rooms,
                    "room_rate"             => $total_room_rate,
                    "tax_amount"            => $total_gst_amount,
                    "plan"                  => $getRoom->rate_plan_name,
                    "adult"                 => $total_adult,
                    "child"                 => $total_child,
                    "extra_adult_rate"      => $extra_adult_amount,
                    "extra_child_rate"      => $extra_child_amount,
                    "rooms"                 => $room_info
                );
                if ($otdc_active_status == 1) {
                    $get_otdc_room_id = CmOtaRoomTypeSynchronizeRead::where('hotel_id', $bk_details->hotel_id)
                        ->where('room_type_id', $getRoom->room_type_id)
                        ->where('ota_type_id', $get_hotel_code->ota_id)
                        ->first();
                    if (in_array($get_otdc_room_id->ota_room_type, $otdc_room_type_id)) {
                        $otdc_index = array_search($get_otdc_room_id->ota_room_type, $otdc_room_type_id);
                        $otdc_rooms[$otdc_index] = $otdc_rooms[$otdc_index] + $getRoom->rooms;
                    } else {
                        $otdc_room_type[] = $getRoom->rooms . ' ' . $get_otdc_room_id->ota_room_type_name;
                        $otdc_room_type_id[] = $get_otdc_room_id->ota_room_type;
                        $otdc_rooms[] = $getRoom->rooms;
                    }
                }

                // }
                // else{
                //     $rooms[] = array(
                //         "room_type_id"          => $getRoom->room_type_id,
                //         "room_type_name"        => $getRoom->room_type,
                //         "no_of_rooms"           => $getRoom->rooms,
                //         "room_rate"             => $getRoom->room_rate,
                //         "tax_amount"            => $getRoom->tax_amount,
                //         "plan"                  => $getRoom->rate_plan_name,
                //         "adult"                 => $getRoom->adult,
                //         "child"                 => $getRoom->child,
                //         "extra_adult_rate"      => $getRoom->extra_adult,
                //         "extra_child_rate"      => $getRoom->extra_child
                //         );
                // }
            }
            $user_info = array(
                "user_name"             => $User_Details['first_name'] . ' ' . $User_Details['last_name'],
                "mobile"                => $User_Details['mobile'],
                "email"                 => $User_Details['email_id'],
                "address"               => $User_Details['address'],
                "zip_code"              => $User_Details['zip_code'],
                "country"               => $User_Details['country'],
                "state"                 => $User_Details['state'],
                "city"                  => $User_Details['city'],
                "GSTIN"                 => $User_Details['GSTIN'],
                "company_name"          => $User_Details['company_name'],
                "arrival_time"          => $bk_details->arrival_time,
                "guest_note"            => $bk_details->guest_note
            );
            if ($bk_details->booking_status == 1) {
                $booking_status = 'confirmed';
            } else if ($bk_details->booking_status == 1 && $bk_details->modify_status == 1) {
                $booking_status = 'modified';
            } else if ($bk_details->booking_status == 3) {
                $booking_status = 'cancelled';
            }
            if (isset($bk_details->booking_source) && $bk_details->booking_source == 'CRS') {
                $discount = 0;
            } else {
                $discount = $bk_details->discount_amount;
            }
            if ($gems == 'true' || $gems == 'crs') {
                $Bookings = array(
                    "date_of_booking"       => $booking_date,
                    "hotel_id"              => $bk_details->hotel_id,
                    "hotel_name"            => $bk_details->hotel_name,
                    "check_in"              => $Booked_Rooms[0]['check_in'],
                    "check_out"             => $Booked_Rooms[0]['check_out'],
                    "booking_id"            => $booking_id,
                    "mode_of_payment"       => $mode_of_payment,
                    "grand_total"           => $bk_details->total_amount,
                    "collection_amount"     => 0,
                    "currency"              => $bk_details->currency,
                    "paid_amount"           => 0,
                    "tax_amount"            => $bk_details->tax_amount,
                    "discount_amount"       => 0,
                    "channel"               => "Bookingjini",
                    // "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1016869990agoda.png",
                    "status"                => $booking_status
                );
            } else {
                $Bookings = array(
                    "date_of_booking"       => $booking_date,
                    "hotel_id"              => $bk_details->hotel_id,
                    "hotel_name"            => $bk_details->hotel_name,
                    "check_in"              => $Booked_Rooms[0]['check_in'],
                    "check_out"             => $Booked_Rooms[0]['check_out'],
                    "booking_id"            => $booking_id,
                    "mode_of_payment"       => $mode_of_payment,
                    "grand_total"           => $bk_details->total_amount,
                    "collection_amount"     => 0,
                    "paid_amount"           => $bk_details->paid_amount,
                    "tax_amount"            => $bk_details->tax_amount,
                    "discount_amount"       => 0,
                    "channel"               => "Bookingjini",
                    // "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1016869990agoda.png",
                    "status"                => $booking_status
                );
            }
            if ($otdc_active_status == 1) {
                $otdc_check_in = $Booked_Rooms[0]['check_in'];
                $otdc_check_out = $Booked_Rooms[0]['check_out'];
                $otdc_hotel_id = $get_hotel_code->ota_hotel_code;
                $otdc_hotel_name = $bk_details->hotel_name;
                $otdc_total_amount = $bk_details->total_amount;
                $otdc_booking_date = $booking_date;
                $otdc_booking_status = $bk_details->booking_status;
                $otdc_user_info = $user_info;
                $otdc_booking_id = $booking_id;
            }

            $all_bookings[] = array(
                'UserDetails'               => $user_info,
                'BookingsDetails'           => $Bookings,
                'RoomDetails'               => $rooms
            );
            $k++;
        }
        if ($otdc_active_status == 1) {
            $otdc_user_info = json_encode($otdc_user_info);
            $otdc_check_in_out = "[" . $otdc_check_in . '-' . $otdc_check_out . "]";
            $otdc_room_type = implode(',', $otdc_room_type);
            $otdc_room_type = "[" . $otdc_room_type . "]";
            $otdc_room_type_id = implode(',', $otdc_room_type_id);
            $otdc_rooms = implode(',', $otdc_rooms);
            $otdc_bookings = array(
                "hotel_id" => $otdc_hotel_id,
                "hotel_name" => $otdc_hotel_name,
                "room_type" => $otdc_room_type,
                "total_amount" => $otdc_total_amount,
                "check_in_out" => $otdc_check_in_out,
                "booking_date" => $otdc_booking_date,
                "booking_status" => $otdc_booking_status,
                "room_type_id" => $otdc_room_type_id,
                "rooms" => $otdc_rooms,
                "check_in" => $otdc_check_in,
                "check_out" => $otdc_check_out,
                "user_info" => $otdc_user_info,
                "otdc_booking_id" => $otdc_booking_id,
                "otdc_modify_status" => $bk_details->modify_status
            );
            $push_booking_to_otdc = $this->pushBookingToOTDC($otdc_bookings);
        }

        if (sizeof($all_bookings) > 0) {

            $logpath = storage_path("logs/gemsbookingpush.log" . date("Y-m-d"));
            $logfile = fopen($logpath, "a+");
            fwrite($logfile, "Processing at: " . date("Y-m-d H:i:s") . "\n");
            fwrite($logfile, "all booking: " . json_encode($all_bookings) . "\n");


            $all_bookings = http_build_query($all_bookings);
            $url = "https://gems.bookingjini.com/api/insertTravellerBookings";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $all_bookings);
            $rlt = curl_exec($ch);
            curl_close($ch);

            fwrite($logfile, "Response: " . json_encode($rlt) . "\n");
            fclose($logfile);
            
            return $rlt;
        }
    }
    public function gstDiscountPrice($cart)
    {
        $invInfo = array();
        $total_discount_price = 0;
        $total_tax = 0;
        foreach ($cart as $cartItem) {
            $total_discount_price += $cartItem['discounted_price'];
            $total_discount_price = number_format((float)$total_discount_price, 2, '.', '');
            if ($cartItem['tax'][0]['gst_price'] != 0) {
                $total_tax  += $cartItem['tax'][0]['gst_price'];
            } else {
                foreach ($cartItem['tax'][0]['other_tax'] as $key => $other_tax) {
                    $total_tax += $other_tax['tax_price'];
                    $other_tax_arr[$key]['tax_name'] = $other_tax['tax_name'];
                    if (!isset($other_tax_arr[$key]['tax_price'])) {
                        $other_tax_arr[$key]['tax_price'] = 0;
                        $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                    } else {
                        $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                    }
                }
            }
            $total_tax = number_format((float)$total_tax, 2, '.', '');
        }
        $invInfo[0] = $total_tax;
        $invInfo[1] = $total_discount_price;

        return $invInfo;
    }
    public function getGstPricePerRoom($room_rate, $discount)
    {
        $gstPrice = 0;
        $price = $room_rate - $discount;
        if ($room_rate > 0 && $room_rate <= 7500) {
            $gstPrice = ($price * 12) / 100;
        } else if ($room_rate > 7500) {
            $gstPrice = ($price * 18) / 100;
        }
        return $gstPrice;
    }



    //API to fetch cancellation policy master details

    public function fetchCancellationPolicyMasterData(Request $request)
    {

        $cancellation_policy_master_details = CancellationPolicyMaster::select('id', 'days_before_checkin')->get();

        $res = array('status' => 1, 'message' => 'Retrieved successfully', 'cancellation_policy_master_details' => $cancellation_policy_master_details);
        return response()->json($res);
    }


    public function fetchCancellationPolicy($hotel_id, Request $request)
    {

        $cancellation_policy = CancellationPolicy::select('id', 'hotel_id', 'policy_data')->where('hotel_id', $hotel_id)->first();
        $policy_data_array = array();
        if ($cancellation_policy) {
            $cancellation_policy->policy_data = json_decode($cancellation_policy->policy_data);

            foreach ($cancellation_policy->policy_data  as $policy_data) {
                $create_array = explode(':', $policy_data);
                $create_object = array('days_before_checkin' => $create_array[0], 'refund_percentage' => $create_array[1]);
                $policy_data_array[] = $create_object;
            }
            $cancellation_policy->policy_data_array = $policy_data_array;
        }


        $res = array('status' => 1, 'message' => 'Retrieved successfully', 'cancellation_policy' => $cancellation_policy);
        return response()->json($res);
    }
    //bookone bookings
    public function pushBookingToBookone($invoice_id, $bookone)
    {
        $all_bookings = array();
        $getBookingDetails = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->select('hotel_booking.user_id', 'invoice_table.room_type', 'invoice_table.ref_no', 'invoice_table.extra_details', 'invoice_table.booking_date', 'invoice_table.invoice_id', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.hotel_name', 'invoice_table.hotel_id', 'company_table.currency', 'company_table.company_full_name', 'hotels_table.email_id', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_status', 'invoice_table.modify_status')
            ->distinct('hotel_booking.user_id')
            ->where('invoice_table.invoice_id', $invoice_id)
            ->where('invoice_table.ref_no', '!=', 'offline')
            ->orderBy('invoice_table.invoice_id', 'ASC')
            ->get();

        foreach ($getBookingDetails as $key => $bk_details) {
            $rooms          = array();
            $user_id        = $bk_details->user_id;
            $ref_no         = $bk_details->ref_no;
            $email_id       = explode(',', $bk_details->email_id);


            $User_Details   = $this->userInfo($user_id);
            $Booked_Rooms   = $this->noOfBookings($invoice_id);

            $date1 = date_create($Booked_Rooms[0]['check_out']);
            $date2 = date_create($Booked_Rooms[0]['check_in']);
            $diff = date_diff($date1, $date2);
            $no_of_nights = $diff->format("%a");

            $booking_date   = $bk_details->booking_date;
            $booking_id     = date("dmy", strtotime($booking_date)) . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

            if ($ref_no == 'offline') {
                $mode_of_payment = 'Offline';
            } else {
                $mode_of_payment = 'Online';
            }
            $room_type_plan = explode(",", $bk_details->room_type);
            $plan = array();
            for ($i = 0; $i < sizeof($room_type_plan); $i++) {
                $plan[] = substr($room_type_plan[$i], -5, -3);
            }
            $extra = json_decode($bk_details->extra_details);

            $k = 0;
            $getRoomDetails = BeBookingDetailsTable::select('*')->where('ref_no', $bk_details->ref_no)->get();
            foreach ($getRoomDetails as $getRoom) {
                $rooms[] = array(
                    "room_type_id"          => $getRoom->room_type_id,
                    "room_type_name"        => $getRoom->room_type,
                    "no_of_rooms"           => $getRoom->rooms,
                    "room_rate"             => $getRoom->room_rate,
                    "tax_amount"            => $getRoom->tax_amount,
                    "plan"                  => $getRoom->rate_plan_name,
                    "adult"                 => $getRoom->adult,
                    "child"                 => $getRoom->child
                );
            }
            $user_info = array(
                "user_name"             => $User_Details['first_name'] . ' ' . trim($User_Details['last_name']),
                "mobile"                => $User_Details['mobile'],
                "email"                 => $User_Details['email_id'],
                "address"               => $User_Details['address'],
                "zip_code"              => $User_Details['zip_code'],
                "country"               => $User_Details['country'],
                "state"                 => $User_Details['state'],
                "city"                  => $User_Details['city'],
                "GSTIN"                 => $User_Details['GSTIN'],
                "company_name"          => $User_Details['company_full_name']
            );
            if ($bk_details->booking_status == 1) {
                $booking_status = 'confirmed';
            } else if ($bk_details->booking_status == 1 && $bk_details->modify_status == 1) {
                $booking_status = 'modified';
            } else if ($bk_details->booking_status == 3) {
                $booking_status = 'cancelled';
            }
            $Bookings = array(
                "ota_unique_id"         => $booking_id,
                "date_of_booking"       => $booking_date,
                "hotel_id"              => $bk_details->hotel_id,
                "hotel_name"            => $bk_details->hotel_name,
                "hotel_business_email"  => $email_id[0],
                "check_in"              => $Booked_Rooms[0]['check_in'],
                "check_out"             => $Booked_Rooms[0]['check_out'],
                "booking_id"            => $booking_id,
                "mode_of_payment"       => $mode_of_payment,
                "grand_total"           => $bk_details->total_amount,
                "currency"              => $bk_details->currency,
                "paid_amount"           => $bk_details->paid_amount,
                "tax_amount"            => $bk_details->tax_amount,
                "discount_amount"       => $bk_details->discount_amount,
                "channel"               => "Bookingjini",
                "status"                => $booking_status
            );


            $all_bookings['data'][] = array(
                'UserDetails'               => $user_info,
                'BookingsDetails'           => $Bookings,
                'RoomDetails'               => $rooms
            );
            $k++;
        }
        $all_bookings['b_status'] = "yes";
        $booking_details = json_encode($all_bookings);

        $headers =  array(
            'MESSAGE_TYPE: application/json',
            'CHANNEL_ID: 2',
            'TRANSACTION_ID: 02082131071',
            'Content-Type: application/json'
        );
        if (sizeof($all_bookings) > 0) {
            $url = "https://api.bookonelocal.in/channel-integration/api/bookingJini/reservation";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $booking_details);
            $rlt = curl_exec($ch);
            curl_close($ch);
            return $rlt;
        }
    }

    //API to fetch Cancellation Policy In FrontView

    public function fetchCancellationPolicyFrontView($hotel_id, Request $request)
    {

        $cancellation_policy = CancellationPolicy::select('id', 'hotel_id', 'policy_data')->where('hotel_id', $hotel_id)->first();
        $policy_data_array = array();
        if ($cancellation_policy) {
            $cancellation_policy->policy_data = json_decode($cancellation_policy->policy_data);

            foreach ($cancellation_policy->policy_data  as $policy_data) {
                $create_array = explode(':', $policy_data);
                $create_object = array('days' => $create_array[0], 'refund_percentage' => $create_array[1]);
                $policy_data_array[] = $create_object;
            }
            $cancellation_policy->policy_data_array = $policy_data_array;
        }

        $info = HotelInformation::select('child_policy', 'cancel_policy', 'terms_and_cond', 'hotel_policy', 'hotel_address', 'mobile', 'latitude', 'longitude')->where('hotel_id', $hotel_id)->first();

        $res = array('status' => 1, 'message' => 'Retrieved successfully', 'cancellation_policy' => $cancellation_policy, 'policy_info' => $info);
        return response()->json($res);
    }



    //API to insert/update Cancellation Policy
    public function updateCancellationPolicy(Request $request)
    {
        $data = $request->all();
        $data['policy_data'] = json_encode($request->input('policy_data'));
        if ($data['id'] == 'undefined') {
            $insertPolicyData = CancellationPolicy::insert(['hotel_id' => $data['hotel_id'], 'policy_data' => $data['policy_data']]);
            if ($insertPolicyData) {
                $res = array('status' => 1, 'message' => 'Inserted Successfully');
                return response()->json($res);
            } else {
                $res = array('status' => 0, 'message' => 'insertion Failed');
                return response()->json($res);
            }
        } else {
            $updatePolicyData = CancellationPolicy::where('id', $data['id'])->where('hotel_id', $data['hotel_id'])->update(['policy_data' => $data['policy_data']]);
            if ($updatePolicyData) {
                $res = array('status' => 1, 'message' => 'Policy updated successfully');
                return response()->json($res);
            } else {
                $res = array('status' => 0, 'message' => 'Policy updation failure');
                return response()->json($res);
            }
        }
    }



    // BE Cancellation Refund Amount //
    /**
     * @author Hafiz
     * This function is used to get the refund amount by cancel booking depend upon the days before checkin date */
    public function fetchCancelRefundAmount($invoice_id)
    {
        $get_today = date("Y-m-d");
        $ref_per = 0;
        $refund_amount = 0;

        $be_booking_data = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')->select('hotel_booking.check_in', 'invoice_table.total_amount', 'invoice_table.hotel_id')->where('hotel_booking.invoice_id', $invoice_id)->first();

        if ($be_booking_data) {
            $check_in = $be_booking_data->check_in;
            $total_amount = $be_booking_data->total_amount;
            $hotel_id = $be_booking_data->hotel_id;
        }
        if ($check_in >= $get_today) {
            $days = abs(strtotime($check_in) - strtotime($get_today)) / 86400;
            if ($days >= 0) {
                $get_cancellation_policy = CancellationPolicy::where('hotel_id', $hotel_id)->first();
                if ($get_cancellation_policy) {
                    $closest = null;
                    $refund_days = $get_cancellation_policy->policy_data;
                    $refund_days = json_decode($refund_days);

                    for ($i = 0; $i < sizeof($refund_days); $i++) {
                        $ref_data = explode(':', $refund_days[$i]);
                        $ref_per = $ref_data[1];
                        $daterange = explode('-', $ref_data[0]);
                        if ($days  >= $daterange[0] && $days  <= $daterange[1]) {
                            $refund_amount = $total_amount * ($ref_per / 100);
                        }
                    }
                } else {
                    $refund_amount = 0;
                }
            } else {
                $refund_amount = 0;
            }
        }
        return $refund_amount;
    }
    //API to fetch BE Notifications Popup 

    public function fetchBENotifications($hotel_id, Request $request)
    {

        $be_notifications = Notifications::select('id', 'hotel_id', 'content_html')->where('is_active', '1')->where('hotel_id', $hotel_id)->first();

        $res = array('status' => 1, 'message' => 'Retrieved successfully', 'be_notifications' => $be_notifications,'notification_status' => 1);
        return response()->json($res);
    }

    public function fetchNotificationSliderImage($hotel_id, Request $request)
    {

        $notification_slider_details = BENotificationSlider::select('id', 'hotel_id', 'images')->where('hotel_id', $hotel_id)->first();

        if ($notification_slider_details) {
            $slider_image_array = array();
            $slider_image_name = explode(',', $notification_slider_details->images);
            if ($notification_slider_details->images && sizeof($slider_image_name) > 0) {
                $notification_slider_details->image_id = $slider_image_name;
                foreach ($slider_image_name  as $id) {
                    $retrive_image_name = ImageTable::select('image_id', 'image_name')->where('image_id', $id)->first();
                    $slider_image_array[] = $retrive_image_name->image_name;
                }
            } else {
                $notification_slider_details->image_id = [];
            }
            $notification_slider_details->image_name =  $slider_image_array;
        }

        $res = array('status' => 1, 'message' => 'Retrieved', "notification_slider_details" => $notification_slider_details);
        return response()->json($res);
    }


    public function updateNotificationPopup(Request $request)
    {
        $data = $request->all();
        $cond = array('hotel_id' => $data['hotel_id']);

        $check = Notifications::where($cond)->first();
        $insertPopupData = new Notifications();
        if (!$check) {
            $insData = $insertPopupData->fill($data)->save();
            if ($insData) {
                return response()->json(array('status' => 1, 'message' => 'insert successfully'));
            } else {
                return response()->json(array('status' => 0, 'message' => 'insertion fails'));
            }
        } else {
            $upPopupData = Notifications::where($cond)->update(['content_html' => $data['content_html']]);
            if ($upPopupData) {
                return response()->json(array('status' => 1, 'message' => 'update successfully'));
            } else {
                return response()->json(array('status' => 0, 'message' => 'update fails'));
            }
        }
    }


    public function uploadNotificationSliderImage(int $hotel_id, Request $request)
    {
        $dz_failure_message = "Image uploading failed";
        $dz_data = array();
        $dz_data['hotel_id'] = $hotel_id;


        $dz_image_name = array();
        if ($request->hasFile('uploadFile')) {
            // Make Validation
            $file = array('uploadFile' => $request->file('uploadFile'));
            //Initialize Check Trigger
            $dz_cot = 0;
            //Check number of images and upload it
            foreach ($request->file('uploadFile') as $media) {
                //------------Amazon S3------------------//
                $files = $request->file('uploadFile');
                $dz_name = time() . $media->getClientOriginalName();
                $dz_name = str_replace(' ', '', $dz_name);

                $dz_filePath = 'uploads/' . $dz_name;

                Storage::disk('s3')->put($dz_filePath, file_get_contents($media), 'public');



                $dzfileUpload = new ImageTable;
                $dzfileUpload->image_name = $dz_filePath;
                $dzfileUpload->hotel_id = $hotel_id;
                $dzfileUpload->save();

                $dz_img_id = ImageTable::select('image_id', 'image_name')->where('hotel_id', $hotel_id)->where('image_name', $dz_filePath)->first();
                $dz_im_id = $dz_img_id->image_id;
                if ($dz_im_id) {
                    array_push($dz_image_name, $dz_img_id->image_name);
                    $dz_cot = $dz_cot + 1;
                } else {
                    $res = array('status' => -1, "message" => $dz_failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
            }
            // Check Trigger and return Message
            if ($dz_cot == count($request->file('uploadFile'))) {
                $res = array('status' => 1, "message" => "Images uploaded successfully", "image_ids" => $dz_im_id, "image_name" => $dz_image_name);
                return response()->json($res);
            } else {
                $res = array('status' => -1, "message" => $dz_failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        } else {
            return response()->json(array('status' => 0, 'message' => $dz_failure_message, 'errors' => 'No File Choosen.'));
        }
    }


    public function updateNotificationSliderImage(Request $request)
    {
        $data = $request->all();
        $cond = array('hotel_id' => $data['hotel_id']);

        $check = BENotificationSlider::where($cond)->first();
        $insertData = new BENotificationSlider();
        $data['images'] = implode(',', $data['images']);
        if (!$check) {
            $insData = $insertData->fill($data)->save();
            if ($insData) {
                return response()->json(array('status' => 1, 'message' => 'insert successfully'));
            } else {
                return response()->json(array('status' => 0, 'message' => 'insertion fails'));
            }
        } else {
            $upData = BENotificationSlider::where($cond)->update(['images' => $data['images']]);
            if ($upData) {
                return response()->json(array('status' => 1, 'message' => 'update successfully'));
            } else {
                return response()->json(array('status' => 0, 'message' => 'update fails'));
            }
        }
    }

    public function deleteNotificationSliderImage(Request $request)
    {
        $slider_image_data = $request->all();

        $delete_img_id = ImageTable::select('image_id')->where('hotel_id', $hotel_id)->where('image_name', $slider_image_data["image_name"])->first();

        $deleteImg = ImageTable::where('image_name', $slider_image_data["image_name"])->where('hotel_id', $slider_image_data['hotel_id'])->delete();

        $slider_image_data["image_name"] = str_replace('"', "'", $slider_image_data["image_name"]);
        // $sliderimagedata = Storage::disk('s3')->delete($slider_image_data["image_name"]); // delete image from amazon s3
        if ($sliderimagedata) {
            $res = array('status' => 1, 'message' => 'Image deleted successfully', "image_id" => $delete_img_id->image_id);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Image deletion failed');
            return response()->json($res);
        }
    }
    public function handleWimhms($cart, $from_date, $to_date, $booking_date, $hotel_id, $user_id, $booking_status)
    {
        $winhms_status = $this->idsService->getwinhmsStatus($hotel_id);
        if ($winhms_status) {
            $winhms_data = $this->prepare_winhms_data($cart, $from_date, $to_date, $booking_date, $hotel_id);
            $customer_data = $this->getUserDetails($user_id)->toArray();
            $type = "Bookingjini";
            if ($booking_status == 'Cancel') {
                $last_winhms_id = $this->idsService->winhmsCancelBooking($hotel_id, $type, $winhms_data, $customer_data, $booking_status);
            } else {
                $last_winhms_id = $this->idsService->winhmsBookings($hotel_id, $type, $winhms_data, $customer_data, $booking_status);
            }
            if ($last_winhms_id) {
                return $last_winhms_id;
            } else {
                return 0;
            }
        }
    }
    public function prepare_winhms_data($cart, $from_date, $to_date, $booking_date, $hotel_id)
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
                    $amount = $ind_total_price / $diff;
                    $gst_price = $this->getGstPrice(1, 1, $cartItem['room_type_id'], $amount); //TO get the GSt price
                    if (strpos('.', $amount) == false) {
                        $amount = $amount . '.00';
                    }
                    array_push($rates_arr, array("from_date" => $d1, "to_date" => $d2, 'amount' => (int)$amount, 'tax_amount' => $gst_price));
                    $frm_date = date('Y-m-d', strtotime($d1 . ' +1 day'));
                }
                $arr = array('room_type_id' => $cartItem['room_type_id'], 'rate_plan_id' => $rooms['rate_plan_id'], 'adults' => $total_adult, 'from_date' => $from_date, 'to_date' => $to_date, 'rates' => $rates_arr, 'no_of_rooms' => 1);
                array_push($booking_data['room_stay'], $arr);
            }
        }
        return $booking_data;
    }


    public function getBeVersionData()
    {
        $year = date('Y');
        return response()->json(array('status' => 1, 'version' => '3.2.0', 'year' => $year));
    }
    public function addOnCharges($hotel_id)
    {
        $get_convenience_fee = DB::table('kernel.add_on_charges')->where('hotel_id', $hotel_id)->where('is_active', 1)->get();
        if ($get_convenience_fee) {
            return response()->json(array('status' => 1, 'message' => 'convenience fee available', 'data' => $get_convenience_fee));
        } else {
            return response()->json(array('status' => 0, 'message' => 'convenience fee not available'));
        }
    }
    /**
     * Mannually Mail Fire
     * @param 
     * @auther Saroj Patel Date:15-07-2022
     */
    public function manualMailFire(Request $request)
    {

        $booking_id = $request->booking_id;
        $email = $request->email;
        $invoice_id = substr($booking_id, '6');
        $invoice        = $this->successInvoice($invoice_id);
        $invoice = $invoice[0];
        // $booking_id     = date("dmy", strtotime($invoice->booking_date)) . str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);

        $body           = $invoice->invoice;
        if ($invoice->booking_status == 1 && $invoice->modify_status == 1) {
            $subject        = $invoice->hotel_name . " Booking Modified";
            $body           = str_replace("#####", $booking_id, $body);
            $body           = str_replace("BOOKING CONFIRMATION", "BOOKING MODIFIED", $body);
        } elseif ($invoice->booking_status == 3) {
            $subject        = $invoice->hotel_name . " Booking Cancel";
            $body           = str_replace("#####", $booking_id, $body);
            $body           = str_replace("BOOKING CONFIRMATION", "BOOKING CANCEL", $body);
        } else {
            $subject        = $invoice->hotel_name . " Booking Confirmation";
            $body           = str_replace("#####", $booking_id, $body);
        }

        $body           = str_replace(">>>>>", $booking_id, $body);
        $invoice_id     = $invoice->invoice_id;

        $serial_no      = 'NA';
        $transection_id = 'NA';
        $body           = str_replace("*****", $serial_no, $body);
        $body           = str_replace("@@@@@", $transection_id, $body);
        $body_info      = array('invoice' => $body);
        // Invoice::where('invoice_id', $invoice_id)->update($body_info);

        $data = array('email' => $email, 'subject' => $subject);
        $data['template'] = $body;
        $data['hotel_name'] = $invoice->hotel_name;

        try {
            Mail::send([], [], function ($message) use ($data) {

                $message->to($data['email'])
                    ->from(env("MAIL_FROM"), $data['hotel_name'])
                    ->subject($data['subject'])
                    ->setBody($data['template'], 'text/html');
            });
            if (Mail::failures()) {
                $res = array('status' => 0, "message" => 'Mail send failed');
                return response()->json($res);
            }
            $res = array('status' => 1, "message" => 'Mail sent');
            return response()->json($res);
        } catch (Exception $e) {
            return true;
        }
    }
    public function getChildages($hotel_id)
    {
        $get_child_ages = DB::table('bookingengine_age_setup')->where('hotel_id', $hotel_id)->first();

        if ($get_child_ages) {
            $get_child_ages->infant = '(' . $get_child_ages->infant . ' yrs)';
            $get_child_ages->children = '(' . $get_child_ages->children . ' yrs)';
            $resp = array('status' => 1, "message" => "Age details fetch successfully", 'data' => $get_child_ages);
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, "message" => "Age details fetch failed");
            return response()->json($resp);
        }
    }
    public function insertDataIntoAddonCharges(Request $request)
    {
        $data = $request->all();
        $data = array(
            "hotel_id" => $data['hotel_id'],
            "infant" => $data['infant'],
            "children" => $data['children']
        );
        $insert_data = DB::table('bookingengine_age_setup')->insert($data);
        if ($insert_data) {
            $resp = array('status' => 1, 'message' => 'Success');
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'message' => 'Failour');
            return response()->json($resp);
        }
    }
    public function updateDataIntoAddonCharges(Request $request, $id)
    {
        $data = $request->all();
        $data = array(
            "hotel_id" => $data['hotel_id'],
            "infant" => $data['infant'],
            "children" => $data['children']
        );
        $update = DB::table('bookingengine_age_setup')->where('id', $id)->update($data);
        if ($update) {
            $resp = array('status' => 1, 'message' => 'Success');
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'message' => 'Failour');
            return response()->json($resp);
        }
    }
    public function selectDataIntoAddonCharges($hotel_id)
    {

        $select_data = DB::table('bookingengine_age_setup')->select('*')->where('hotel_id', $hotel_id)->first();
        if (!empty($select_data)) {
            $resp = array('status' => 1, 'message' => 'fetched', 'data' => $select_data);
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'message' => 'fetch failour');
            return response()->json($resp);
        }
    }

    public function checkTaxPrice($cart, $payment_status)
    {
        $return_value_true = 1;
        $return_value_false = 0;
        if ($payment_status->is_taxable == 1) {
            foreach ($cart as $cartItem) {
                if ($cartItem['tax'][0]['gst_price'] == 0) {
                    return $return_value_false;
                } else {
                    return $return_value_true;
                }
            }
        } else {
            return $return_value_true;
        }
    }


    //modified by saroj 
    //dt-03-01-2023
    public function getAllPublicCupons(String $hotel_id, $from_date, $to_date, Request $request)
    {

        $begin = strtotime($from_date);
        $end = strtotime($to_date);
        $from_date = date('Y-m-d', strtotime($from_date));
        $data_array = array();
        for ($currentDate = $begin; $currentDate < $end; $currentDate += (86400)) {
            $data_array_present = array();
            $data_array_notpresent = array();
            $status = array();
            $Store = date('Y-m-d', $currentDate);
            $convert_store_date = date('D', strtotime($Store));

            $get_data = DB::select(DB::raw('select coupon_id,room_type_id,
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
            order by room_type_id,coupon_id desc'));

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
        if (sizeof($data_array) > 0) {
            $res = array('status' => 1, 'message' => "Public coupon retrieved successfully", 'data' => $data_array);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "!Sorry Public coupon is not available", 'data' => array());
            return response()->json($res);
        }
    }

    public  function invoiceMailNew($id)
    {
        $hotel_info = new HotelInformation();
        $invoice        = $this->successInvoice($id);
        $invoice = $invoice[0];
        $booking_id     = date("dmy", strtotime($invoice->booking_date)) . str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
        $u = $this->getUserDetails($invoice->user_id);
        $user_email_id = $u['email_id'];
        $hotel_contact = $hotel_info->getEmailId($invoice->hotel_id);
        $hotel_email_id = $hotel_contact['email_id'];
        $hotel_mobile = $hotel_contact['mobile'];
        $body           = $invoice->invoice;
        $subject        = $invoice->hotel_name . " Booking Confirmation";

        $body           = str_replace("#####", $booking_id, $body);
        $body           = str_replace(">>>>>", $booking_id, $body);
        $invoice_id     = $invoice->invoice_id;

        $serial_no = 0;
        $transection_id = 0;
        $body           = str_replace("*****", $serial_no, $body);
        $body           = str_replace("@@@@@", $transection_id, $body);
        $body_info      = array('invoice' => $body);
        $updated_inv_info = Invoice::where('invoice_id', $invoice_id)->update($body_info);

        $email_ids = '';
        $email_ids .= $user_email_id . ',';
        $email_ids .= implode(',', $hotel_email_id);
        $email_ids .= ',trilochan.parida@5elements.co.in,reservations@bookingjini.com,accounts@bookingjini.com';


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
            CURLOPT_POSTFIELDS => array('mail_from' => 'do-not-reply@bookingjini.com', 'mail_to' => $email_ids, 'subject' => $subject, 'html' => $body),
            CURLOPT_HTTPHEADER => array(
                'Authorization: hRv8FpLbN7'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
    }

    public function daySuccessBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid , $mail_to_guest = 1)
    {


        $DayOutingBookings =  DayBookings::where('booking_id', $invoice_id)->first();

        if ($DayOutingBookings) {

            $package_date = $DayOutingBookings->outing_dates;
            $no_of_guest = $DayOutingBookings->no_of_guest;
            $hotel_id = $DayOutingBookings->hotel_id;
            $package_id = $DayOutingBookings->package_id;

            $check_inventory = DayBookingARI::where('hotel_id', $hotel_id)
                ->where('package_id', $package_id)
                ->where('day_outing_dates', $package_date)
                ->first();

            if ($check_inventory->no_of_guest < $no_of_guest) {
                return response()->json(['status' => 0, 'msg' => 'No room available']);
            }

            $updated_guest = $check_inventory->no_of_guest - $no_of_guest;

            $update_guest_ari = DayBookingARI::where('hotel_id', $hotel_id)
                ->where('package_id', $package_id)
                ->where('day_outing_dates', $package_date)
                ->update(['no_of_guest' => $updated_guest]);

            $ari_log_data = [
                "hotel_id" => $hotel_id,
                "package_id" => $package_id,
                "from_date" => $package_date,
                "to_date" => $package_date,
                "no_of_guest" => $updated_guest,
                "rate" => $check_inventory->rate,
            ];

            $update_guest_log = DayBookingARILog::insert($ari_log_data);


            //success the booking
            $DayOutingBookings =  DayBookings::where('booking_id', $invoice_id)->update(['booking_status' => 1]);
            if ($DayOutingBookings) {
                $this->dayBookingInvoiceMail($invoice_id,$mail_to_guest);
            }

            $company_dtls   = HotelInformation::where('hotel_id', $hotel_id)->first();
            $company_id     = $company_dtls->company_id;

            $CompanyDetaiils   = CompanyDetails::where('company_id', $company_id)->first();

            if ($CompanyDetaiils) {
                return $CompanyDetaiils->company_url;
            } else {
                return '';
            }
        }
    }


    public  function dayBookingInvoiceMail($id,$mail_to_guest)
    {
        $hotel_info = new HotelInformation();
        $booking_details =  DayBookings::where('booking_id', $id)->first();
        $invoice        = $this->bookingVoucher($id);
        $hotel_details   = HotelInformation::where('hotel_id', $booking_details->hotel_id)->first();
        $u = $this->getUserDetails($booking_details->user_id);
        $user_email_id = $u['email_id'];
        $hotel_contact = $hotel_info->getEmailId($booking_details->hotel_id);
        $hotel_email_id = $hotel_contact['email_id'];
        $body           = $invoice;
        $subject        = $hotel_details->hotel_name . " Booking Confirmation";

        $email_ids = '';
        // if($booking_details->hotel_id == 2600){
        //     dd($mail_to_guest);
        // }

        if($mail_to_guest != 0){
        $email_ids .= $user_email_id . ',';
        }

        $email_ids .= implode(',', $hotel_email_id);
        $email_ids .= ',sarojkumarpatel12@gmail.com';
        // $email_ids .= ',trilochan.parida@5elements.co.in,reservations@bookingjini.com,accounts@bookingjini.com';

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
            CURLOPT_POSTFIELDS => array('mail_from' => 'do-not-reply@bookingjini.com', 'mail_to' => $email_ids, 'subject' => $subject, 'html' => $body),
            CURLOPT_HTTPHEADER => array(
                'Authorization: hRv8FpLbN7'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
    }

    public function bookingVoucher($invoice_id)
    {

        $dayOutingBookings =  DayBookings::leftjoin('kernel.user_table', 'user_table.user_id', '=', 'day_bookings.user_id')
            ->where('day_bookings.booking_id', $invoice_id)
            ->select('day_bookings.*', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile', 'user_table.address')
            ->first();

        $dayBookingDetails = DayPackage::where('id', $dayOutingBookings->package_id)->first();

        $gst = '';

        if(isset($dayOutingBookings->gstin)){
            $gstin = $dayOutingBookings->gstin;

            $gst = '<tr>
                     <td style="padding-top: 10px; padding-bottom: 6px">
                      <pstyle="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                        GSTIN - '. $gstin .' </p>
                      </td>
                      </tr>
                     <tr>
                     ';
        }

        $package_price = $dayBookingDetails->price;
        // $price_after_discount = $dayBookingDetails->discount_price;
        // $discount_price = $package_price - $price_after_discount;
        $tax_percentage = $dayBookingDetails->tax_percentage;

        $discount_price = $dayOutingBookings->discount_amount;
        $price_after_discount = $package_price - $discount_price;


        // if($invoice_id=='DB-131'){
        //     $expeted_arrival =  isset($dayOutingBookings->arrival_time)?$dayOutingBookings->arrival_time:'';
        //     dd($expeted_arrival);
        // }

        $guest_note = $dayOutingBookings->guest_note;
        $expeted_arrival =  isset($dayOutingBookings->arrival_time) ? $dayOutingBookings->arrival_time : '';
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

        $booking_id = $dayOutingBookings->booking_id . date('dmy');
        $guest_name = $dayOutingBookings->first_name . ' ' . $dayOutingBookings->last_name;
        $mobile =  $dayOutingBookings->mobile;
        $email_id =  $dayOutingBookings->email_id;
        $address =  $dayOutingBookings->address;

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
        $selling_price = $dayOutingBookings->selling_price;
        $pay_at_hotel = $total_guest_price_including_tax - $paid_amount;

        // If the selling price is zero, set all related amounts to zero
        if($selling_price == 0){
            $total_guest_price_excluding_tax = 0;
            $tax_amount = 0;
            $tax_percentage = 0;
            $total_guest_price_including_tax = 0;
            $paid_amount = 0;
            $pay_at_hotel = 0;

        }



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

                                                                     ' . $gst . '
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
                                                                                ' . $expeted_arrival . '</p>
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
                                                                                                        Selling Price</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $selling_price . '</p>
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
