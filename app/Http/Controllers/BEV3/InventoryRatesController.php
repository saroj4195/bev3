<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\MasterRoomType; //class name from model
use App\HotelInformation;
use DB;
use App\CompanyDetails;
use App\CurrentRateBe;
use App\CurrentRate;
use App\ImageTable;
use App\HotelAmenities;
use App\MasterHotelRatePlan;
use App\Coupons;
use App\MasterRatePlan;
use App\DynamicPricingCurrentInventory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CurrencyController;
use App\Invoice;
use App\DynamicPricingCurrentInventoryBe;
use App\CmOtaDetails;
use App\BePromotions;

class InventoryRatesController extends Controller
{
    protected $invService;
    protected $ipService;
    protected $curency;
    public function __construct(CurrencyController $curency)
    {
        $this->curency = $curency;
    }

    public function currency()
    {

        $currency_details = [
            array('currency_code' => 'INR', 'currency_symbol' => '20B9'),
            array('currency_code' => 'USD', 'currency_symbol' => '0024'),
            // array('currency_code'=>'EUR','currency_symbol'=>'20AC'),
            // array('currency_code'=>'GBP','currency_symbol'=>'00A3'),
            // array('currency_code'=>'BDT','currency_symbol'=>'09F3'),
        ];

        if ($currency_details) {
            $result = array(
                'status' => 1, "message" => 'Currency Details fetched', 'currency_details' => $currency_details
            );
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Currency Details fetched Failed');
            return response()->json($result);
        }
    }

    public function getInvByHotel(Request $request)
    {
        $api_key = $request->api_key;
        $hotel_id = $request->hotel_id;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $currency_name = $request->currency;
        $rooms = $request->rooms;
        // $adult = $request->adult;
        // $child = $request->child;

        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 1, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime($date_to));
        $today = date('Y-m-d');
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0, 'be_room_status' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people as base_adult', 'max_child as base_child', 'max_infant as base_infant', 'image', 'extra_person as extra_adult', 'extra_child', 'max_occupancy', 'room_amenities', 'room_size_value', 'room_size_unit', 'bed_type', 'room_view_type', 'description', 'bookable_name', '360_room_image')->where($conditions)->get();

        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency; //Get base currency
        $room_type_n_rate_plans = array();

        $all_room_ctd_status = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)->where('stay_day', $date_to)->where('ota_id', '-1')->get();
        $ctd_status_room_wise = [];
        foreach ($all_room_ctd_status as $ctd) {
            $ctd_status_room_wise[$ctd['room_type_id']] = $ctd['ctd_status'];
        }

        if ($room_types) {
            foreach ($room_types as $key => $room) {
                $room['min_inv'] = 0;
                $room['image'] = trim($room['image'], ',');
                $room['image'] = explode(',', $room['image']); //Converting string to array
                $images = $this->getImages($room->image); //--------- get all the images of rooms 
                $room_types[$key]['allImages'] = $images;
                if($room['360_room_image']){
                    $room_360_image = ImageTable::where('image_id', $room['360_room_image'])
                    ->select('image_name')
                    ->first();
                    $room_360_image = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/'.$room_360_image->image_name;
                }else{
                    $room_360_image = "";
                }
                $room_types[$key]['room_image_360'] = $room_360_image;
                $room['image'] = $images; //Getting actual amenity names
                if (is_object($room['image']) && sizeof($room['image']) > 0) {
                    $room['image'] = $room['image'][0]->image_name;
                } else {
                    $room['image'] = $this->getImages(array(1));
                    $room['image'] = $room['image'][0]->image_name;
                }


                if (isset($room['bookable_name']) && !empty($room['bookable_name'])) {
                    $room['bookable_name'] = $room['bookable_name'];
                } else {
                    $room['bookable_name'] = 'Room';
                }

                if ($room['bed_type'] == 'Hall') {
                    $room['display_bed_type'] = 0;
                } else {
                    $room['display_bed_type'] = 1;
                }

                $room['room_size_value'] = isset($room['room_size_value']) ? $room['room_size_value'] : '';
                $room['room_size_unit'] = isset($room['room_size_unit']) ? $room['room_size_unit'] : '';
                $room['bed_type'] = isset($room['bed_type']) ? $room['bed_type'] : '';

                $room['room_amenities'] = explode(',', $room['room_amenities']);
                $room['room_amenities'] = $this->getRoomamenity($room['room_amenities']);
                $room['description'] = $room['description'];


                $data = $this->getCurrentInventeryByRoomTYpe($hotel_id, $room['room_type_id'], $date_from, $date_to);


                //changes if from date and to date is same and min inv is coming 0
                if (!isset($data[0]['no_of_rooms'])) {
                    if ($date_from < $today) {
                        $room['min_inv']  = 1;
                    }
                } else {
                    $room['min_inv']  = $data[0]['no_of_rooms'];
                }
                $inv_count = count($data) - 1;
                foreach ($data as $inv_room) {
                    if ($inv_room['no_of_rooms'] < $room['min_inv']) {
                        $room['min_inv']  = $inv_room['no_of_rooms'];
                    }

                    //cta ctd check
                    $is_rti_available = array_key_exists($inv_room['room_type_id'], $ctd_status_room_wise);
                    if ($is_rti_available == 1) {
                        if ($inv_room['room_type_id'] && $ctd_status_room_wise[$inv_room['room_type_id']] == 1) {
                            $room['min_inv'] = 0;
                        }
                    }


                    if ($inv_room['block_status'] == 1 ||  $data[0]['cta_status'] == 1) {
                        $room['min_inv'] = 0;
                    }
                }
                $room['min_los'] = $data[0]['los'];

                $room['inv'] = $data;

                $room["max_occupancy"] = $room["max_occupancy"];
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
                $room['discount_percentage'] = 0;

                $rateplan_details = array();
                foreach ($room_type_n_rate_plans as $key1 => $all_types) {


                    $rate_plan_id = (int)$all_types['rate_plan_id'];
                    $room_rate_status = $this->checkroomrateplan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    $rates = [];
                    if ($room_rate_status) {
                        $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $room['room_type_id'], $rate_plan_id, $date_from, $date_to, $baseCurrency, $currency_name);
                        foreach ($data as $d) {
                            $rates[] = $d;
                        }
                    } else {
                        continue;
                    }

                    $all_types['bar_price'] = 0;
                    $rateplan_details[$key1]['bar_price'] = 0;
                    $rateplan_details[$key1]['price_after_discount'] = 0;
                    $rateplan_details[$key1]['discount_percentage'] = 0;
                    $rateplan_details[$key1]['plan_name'] = $all_types["plan_name"];
                    $rateplan_details[$key1]['plan_type'] = $all_types["plan_type"];
                    $rateplan_details[$key1]['rate_plan_id'] = $all_types["rate_plan_id"];

                    if ($data) {
                        $check_bar_price = array_search(0, array_column($data, 'bar_price'));
                        $check_block_status = array_search(1, array_column($data, 'block_status'));

                        if ($check_bar_price === false && $check_block_status === false) {

                            foreach ($data as $key2 => $rate) {

                                $i = 0;
                                $j = 0;

                                if (isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"]) == 0) {
                                    $rate["multiple_occupancy"][0] = $all_types['bar_price'];
                                }
                                $base_adult_count = $room['base_adult'] - 1;
                                $multiple_occ_count = sizeof($rate["multiple_occupancy"]);

                                if ($base_adult_count > $multiple_occ_count) {
                                    for ($i = 0; $i < $base_adult_count; $i++) {
                                        if (!isset($rate["multiple_occupancy"][$i])) {
                                            // $rate["multiple_occupancy"][$i] =  12345;
                                            $rate["multiple_occupancy"][$i] =  $all_types['bar_price'];
                                        }
                                    }
                                }

                                $rates[$key2]['multiple_occupancy'] = $rate["multiple_occupancy"];

                                $all_types['bar_price'] = $rate['bar_price'];
                                $all_types['price_after_discount'] =  floatval(number_format((float)$rate['price_after_discount'], 2, '.', ''));
                                $all_types['discount_percentage'] = $rate['discount_percentage'];
                                $all_types['extra_adult_price'] = $rate['extra_adult_price'];
                                $all_types['extra_child_price'] = $rate['extra_child_price'];
                            }
                            $room['discount_percentage'] =  $all_types['discount_percentage'];
                        } else {
                            $all_types['bar_price'] = 0;
                        }
                    }

                    if ($room['min_room_price'] == 0) {
                        $room['min_room_price'] = $all_types['bar_price'];
                        $room['price_after_discount'] =  floatval(number_format((float)$all_types['price_after_discount'], 2, '.', ''));
                    }

                    if ($all_types['bar_price'] <= $room['min_room_price'] && $all_types['bar_price'] != null) {
                        $room['min_room_price']  = $all_types['bar_price'];
                        $room['price_after_discount'] =  floatval(number_format((float)$all_types['price_after_discount'], 2, '.', ''));
                    }

                    if ($all_types['bar_price'] != 0 || $all_types['bar_price'] != null) {
                        $all_types['rates'] = $rates;
                    }

                    if ($all_types['bar_price'] == 0 || $all_types['bar_price'] == NULL) {
                        unset($room_type_n_rate_plans[$key1]);
                    }
                }



                foreach ($room_type_n_rate_plans as $i => $room_rate_plan) {
                    if (!isset($room_rate_plan['rates'])) {
                        unset($room_type_n_rate_plans[$i]);
                    }
                }

                $room_type_n_rate = json_decode(json_encode($room_type_n_rate_plans), true);
                $room_type_n_rate_plans = array_values($room_type_n_rate);

                if (isset($room_type_n_rate_plans)) {
                    $ratePlans = collect($room_type_n_rate_plans);
                    $sortedRatePlans = $ratePlans->sortBy('bar_price')->values();
                }

                if ($room['min_room_price'] != 0 &&  $room['min_room_price'] != null) {
                    $room['rate_plans'] = $sortedRatePlans;
                }
            }

            $available_room = [];
            $not_available_room = [];
            $available_room_details = [];
            $not_available_room_details = [];
            $check_inv_block_status = [];

            $no_of_rooms_date_wise = [];

            foreach ($room_types as $room_detail) {
                $new_room_details[$room_detail['room_type_id']] = $room_detail;
                $inv_details = $room_detail['inv'];

                foreach ($inv_details as $inv_detail) {
                    $date = $inv_detail['date'];
                    $no_of_rooms = $inv_detail['no_of_rooms'];

                    if (!isset($no_of_rooms_date_wise[$date])) {
                        $no_of_rooms_date_wise[$date] = [];
                    }
                    $no_of_rooms_date_wise[$date][] = $no_of_rooms;
                }
            }

            $sums = [];
            foreach ($no_of_rooms_date_wise as $date => $values) {
                $sums[] = array_sum($values);
            }

            $smallestValue = collect($sums)->min();

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
                    if (array_intersect([0, -1, -2, -3, -4], $check_no_of_rooms)) {
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
                    // }
                }

                if ($rooms > $smallestValue) {
                    $room->min_inv = 0;
                }

                if (!isset($room->rate_plans)) {
                    $room->min_inv = 0;
                }
            }

            usort($available_room, array($this, 'compareByPrice'));
            usort($available_room_details, array($this, 'compareByPrice'));
            $room_types = array_merge($available_room, $not_available_room);
            if ($diff > 15) {
                $res = array('status' => 2, 'message' => "No. of nights should be less than 15", 'data' => $room_types);
                return response()->json($res);
            } else {
                $res = array('status' => 1, 'message' => "Hotel inventory retrieved successfully ", 'data' => $room_types);
                return response()->json($res);
            }
        } else {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
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

    public function getImages($imgs)
    {
        if (empty($imgs[0])) {
            unset($imgs[0]);
        }
        $imgs = array_values($imgs);
        $imp_images = implode(',', $imgs);
        // $images = ImageTable::whereIn('image_id', $imgs)
        //     ->select('image_name')
        //     ->orderByRaw("FIELD (image_id, ' . $imp_images . ') ASC")
        //     ->get();

        if (!empty($imgs)) {
            $sql = "SELECT image_name
                    FROM kernel.image_table
                    WHERE image_id IN ($imp_images)
                    ORDER BY FIELD(image_id, $imp_images) ASC";

            $images = DB::select($sql);

            return $images;
        } else {
            $images = ImageTable::where('image_id', 3)
                ->select('image_name')
                ->get();
            return $images;
        }
    }

    public function getRoomamenity($ammenityId)
    {
        $amenities = HotelAmenities::whereIn('hotel_amenities_id', $ammenityId)
            ->select('hotel_amenities_name', 'font_class', 'hotel_amenities_id')
            ->get();
        return ($amenities) ? $amenities : array();
    }

    public function getCurrentInventeryByRoomTYpe($hotel_id, $room_type_id, $date_from, $date_to)
    {

        $data = [];
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        for ($i = 1; $i <= $diff; $i++) {
            $d = $date_from;
            $timestamp = strtotime($date_from);
            $day = date('D', $timestamp);
            $inv_details = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)->where('room_type_id', $room_type_id)->where('stay_day', $date_from)->where('ota_id', '-1')->first();
            if ($inv_details) {
                $array = array('no_of_rooms' => $inv_details->no_of_rooms, 'block_status' => $inv_details->block_status, 'los' => $inv_details->los, 'room_type_id' => $inv_details->room_type_id, 'date' => $date_from, 'day' => $day, 'cta_status' => $inv_details->cta_status, 'ctd_status' => $inv_details->ctd_status);
                array_push($data, $array);
            } else {
                $array = array('no_of_rooms' => 0, 'block_status' => 0, 'los' => 0, 'room_type_id' => $room_type_id, 'date' => $date_from, 'day' => $day, 'cta_status' => 0, 'ctd_status' => 0);
                array_push($data, $array);
            }
            $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
        }

        return $data;
    }

    public function checkroomrateplan($room_type_id, $rate_plan_id, $date_from, $date_to)
    {
        $getstatus = MasterHotelRatePlan::select('*')
            ->where(['rate_plan_id' => $rate_plan_id])
            ->where(['room_type_id' => $room_type_id])
            ->where('is_trash', 0)
            // ->where('from_date', '<=', $date_from)
            // ->where('to_date', '>=', $date_from)
            ->where('be_rate_status', 0)
            ->first();
        if ($getstatus) {
            return 1;
        } else {
            return 0;
        }
    }

    public function getCurrentRatesByRoomnRatePlan($hotel_id, $room_type_id, $rate_plan_id, $date_from, $date_to, $baseCurrency, $currency_name)
    {
        $filtered_rate = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $date_to = date('Y-m-d', strtotime($date_to . '-1 day'));

        $coupons = Coupons::where('hotel_id', $hotel_id)
            ->whereIN('room_type_id', [0, $room_type_id])
            ->where('coupon_for', 1)
            ->where('is_trash', 0)
            ->get()->toArray();

        $rates = CurrentRate::where('room_type_id', $room_type_id)
            ->where('hotel_id', $hotel_id)
            ->where('rate_plan_id', $rate_plan_id)
            ->where('ota_id', '-1')
            ->whereBetween('stay_date', [$date_from, $date_to])
            ->get()->toArray();

        if (sizeof($rates) > 0) {
            foreach ($rates as $rate) {

                $multiple_occupancy = json_decode($rate['multiple_occupancy']);
                if (is_string($multiple_occupancy)) {
                    $multiple_occupancy = json_decode($multiple_occupancy);
                }


                if ($baseCurrency == $currency_name) {
                    $filtered_rate[$rate['stay_date']] = array('bar_price' => $rate['bar_price'], 'extra_adult_price' => $rate['extra_adult_price'], 'extra_child_price' => $rate['extra_child_price'], 'date' => $rate['stay_date'], 'block_status' => $rate['block_status'], 'multiple_occupancy' => $multiple_occupancy);
                } else {
                    $converted_multiple_occupancy = [];
                    foreach ($multiple_occupancy as $m_occupancy) {
                        $m_occupancy =  round($this->curency->currencyDetails($m_occupancy, $currency_name, $baseCurrency), 2);
                        array_push($converted_multiple_occupancy, $m_occupancy);
                    }
                    $bar_price =  round($this->curency->currencyDetails($rate['bar_price'], $currency_name, $baseCurrency), 2);
                    $extra_adult_price = round($this->curency->currencyDetails($rate['extra_adult_price'], $currency_name, $baseCurrency), 2);
                    $extra_child_price = round($this->curency->currencyDetails($rate['extra_child_price'], $currency_name, $baseCurrency), 2);

                    $filtered_rate[$rate['stay_date']] = array('bar_price' => $bar_price, 'extra_adult_price' => $extra_adult_price, 'extra_child_price' => $extra_child_price, 'date' => $rate['stay_date'], 'block_status' => $rate['block_status'], 'multiple_occupancy' => $converted_multiple_occupancy);
                }
            }

            for ($i = 0; $i < $diff; $i++) {
                $d = $date_from;
                $coupons_percentage = [];

                if($hotel_id == 2600){

                    $basicPromotions = BePromotions::where('hotel_id', $hotel_id)
                        ->where('promotion_type', 1)
                        ->where('status', 1)
                        ->where(
                            'stay_start_date',
                            '<=',
                            $date_from
                        )
                        ->where('stay_end_date', '>=', $date_from)
                        ->orderby('id', 'DESC')
                        ->first();
                        $maxDiscountPercentage = 0;
                        $maxDiscountAmount = 0;

                    if ($basicPromotions) {
                        $basicRoomRatePlan = json_decode($basicPromotions->selected_room_rate_plan, true);

                        foreach ($basicRoomRatePlan as $basicPlan) {
                            if ($room_type_id == $basicPlan['room_type_id'] && in_array($rate_plan_id, $basicPlan['selected_rate_plans'])) {
                                if ($basicPromotions->offer_type == 0) {
                                    $maxDiscountPercentage = max($maxDiscountPercentage, $basicPromotions->discount_percentage);
                                } elseif ($basicPromotions->offer_type == 1) {
                                    $maxDiscountAmount = max($maxDiscountAmount, $basicPromotions->discounted_amount);
                                }
                            }
                        }
                        // dd($basicPromotions);
                    }

                }
                foreach ($coupons as $coupon) {
                    $valid_from = $coupon['valid_from'];
                    $valid_to = $coupon['valid_to'];
                    $blackoutdates = explode(',', $coupon['blackoutdates']);
                    if ($date_from >= $valid_from && $date_from <= $valid_to) {
                        if (in_array($date_from, $blackoutdates)) {
                            $coupons_percentage[] = 0;
                        } else {
                            $coupons_percentage[] = $coupon['discount'];
                        }
                    }
                }

                $drate =  array('bar_price' => 0, 'extra_adult_price' => 0, 'extra_child_price' => 0, 'date' => $date_from, 'block_status' => 0);
                $filtered_rate[$d] = isset($filtered_rate[$d]) ? $filtered_rate[$d] : $drate;
                $rates_by_date = $filtered_rate[$d];
                $bar_price = $rates_by_date['bar_price'];
                if (empty($coupons_percentage)) {
                    $applied_coupon = 0;
                    $discounted_price = 0;
                } else {
                    $applied_coupon = max($coupons_percentage);
                    $discounted_price = $bar_price * $applied_coupon / 100;
                }
                $price_after_discount = $bar_price - $discounted_price;
                // $filtered_rate[$d]['price_after_discount'] = (int)number_format((float)$price_after_discount, 2, '.', '');

                // if($hotel_id == 2319){
                $filtered_rate[$d]['price_after_discount'] = floatval(number_format((float)$price_after_discount, 2, '.', ''));
                // }

                $filtered_rate[$d]['discount_percentage'] = $applied_coupon;

                if (isset($filtered_rate[$d]['multiple_occupancy'])) {
                    $multiple_occ = [];
                    foreach ($filtered_rate[$d]['multiple_occupancy'] as $occupancy) {
                        $discounted_price = $occupancy * $applied_coupon / 100;
                        $multiple_occupancy = $occupancy - $discounted_price;
                        array_push($multiple_occ, $multiple_occupancy);
                    }
                    $filtered_rate[$d]['multiple_occupancy'] = $multiple_occ;
                }
                $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        } else {
            for ($i = 0; $i < $diff; $i++) {
                $d = $date_from;
                $filtered_rate[$date_from] = array('bar_price' => 0, 'extra_adult_price' => 0, 'extra_child_price' => 0, 'date' => $date_from, 'block_status' => 0, 'price_after_discount' => 0, 'discount_percentage' => 0);
                $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }

        $filtered_rate = array_values($filtered_rate);
        return $filtered_rate;
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

    function compareByPrice($a, $b)
    {
        return $a['min_room_price'] - $b['min_room_price'];
    }

    public function minRates($hotel_id, $from_date, $currency_name, $baseCurrency)
    {
        // if ($hotel_id == 2319) {
        $from_date = date('Y-m-d', strtotime($from_date));
        $next_month = date('Y-m-d', strtotime($from_date . ' + 1 months'));
        $to_date = date('Y-m-t', strtotime($next_month));

        $currency_symbol = '20B9';
        if ($currency_name == 'USD') {
            $currency_symbol = '0024';
        } elseif ($currency_name == 'EUR') {
            $currency_symbol = '20AC';
        } elseif ($currency_name == 'GBP') {
            $currency_symbol = '00A3';
        } elseif ($currency_name  == 'THB') {
            $currency_symbol  = '0E3F';
        } elseif ($currency_name == 'BDT') {
            $currency_symbol = '09F3';
        }

        $getRates = CurrentRate::where('hotel_id', $hotel_id)
            ->where('stay_date', '>=', $from_date)
            ->where('stay_date', '<=', $to_date)
            ->where('ota_id', '-1')
            ->where('block_status', '0')
            ->orderBy('stay_date', 'ASC')
            ->get();

        $allDates = [];
        $currentDate = $from_date;
        while ($currentDate <= $to_date) {
            $allDates[] = $currentDate;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' + 1 day'));
        }

        // return $getRates;
        $after_discount_price = [];
        foreach ($getRates as $getRate) {

            $dpCurInvDetails = DynamicPricingCurrentInventory::where('stay_day', $getRate->stay_date)
                ->where('hotel_id', $hotel_id)
                ->where('room_type_id', $getRate->room_type_id)
                ->where('ota_id', '-1')
                ->first();

            $maxDiscount = Coupons::where('hotel_id', $hotel_id)
                ->where('valid_from', '<=', $getRate->stay_date)
                ->where('valid_to', '>=', $getRate->stay_date)
                ->where('room_type_id', '=', $getRate->room_type_id)
                ->where('coupon_for', 1)
                ->where('is_trash', 0)
                ->max('discount');

            if ($dpCurInvDetails) {
                if ($dpCurInvDetails->no_of_rooms == 0 || $dpCurInvDetails->block_status == 1) {
                    continue;
                } else {
                    $inv_available = 1;
                    $coupon_discount_price = $getRate['bar_price'] * $maxDiscount / 100;
                    $discounted_price = $getRate['bar_price'] - $coupon_discount_price;
                    $after_discount_price[$getRate['stay_date']][$getRate['room_type_id']][$getRate['rate_plan_id']] = $discounted_price;
                }
            }
        }

        $available_dates = [];
        foreach ($after_discount_price as $key => $discount_price) {
            // return $discount_price;
            $minValue = [];
            foreach ($discount_price as $key1 => $room_type) {
                // return $room_type;
                foreach ($room_type as $value) {
                    if ($minValue === null || $value < $minValue && $value != 0) {
                        $minValue[] = $value;
                    }
                }
            }

            $available_dates[] = $key;

            $current_month = date('M');
            $month = date('M', strtotime($key));
            if ($current_month == $month) {
                $availableRoomsDateWise[$month][] = array('date' => $key, 'minimum_rates' => min($minValue), 'currency_symbol' => $currency_symbol, 'is_room_available' => $inv_available);
            } else {
                $availableRoomsDateWise[$month][] = array('date' => $key, 'minimum_rates' => min($minValue), 'currency_symbol' => $currency_symbol, 'is_room_available' => $inv_available);
            }
        }

        $difference_date = array_diff($allDates, $available_dates);

        foreach ($difference_date as $key => $diff_date) {
            $current_month = date('M', strtotime($diff_date));
            $availableRoomsDateWise[$current_month][] = array('date' => $diff_date, 'minimum_rates' => 0, 'currency_symbol' => 0, 'is_room_available' => 0);
        }

        foreach ($availableRoomsDateWise as &$month) {
            usort($month, function ($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
        }

        if ($availableRoomsDateWise) {
            $result = array(
                'status' => 1, "message" => 'Invoice feteched sucessfully', 'details' => $availableRoomsDateWise
            );
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Bookings fetched Failed');
            return response()->json($result);
        }
        // } else {
        //     $from_date = date('Y-m-d', strtotime($from_date));
        //     $next_month = date('Y-m-d', strtotime($from_date . ' + 1 months'));
        //     $to_date = date('Y-m-t', strtotime($next_month));

        //     $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->first();
        //     $adv_days = $hotel_info->advance_booking_days;
        //     $today_date = date('Y-m-d');
        //     $booking_start_date = date('Y-m-d', strtotime($today_date . ' + ' . $adv_days . ' day'));

        //     $currency_symbol = '20B9';
        //     if ($currency_name == 'USD') {
        //         $currency_symbol = '0024';
        //     } elseif ($currency_name == 'EUR') {
        //         $currency_symbol = '20AC';
        //     } elseif ($currency_name == 'GBP') {
        //         $currency_symbol = '00A3';
        //     } elseif ($currency_name  == 'THB') {
        //         $currency_symbol  = '0E3F';
        //     } elseif ($currency_name == 'BDT') {
        //         $currency_symbol = '09F3';
        //     }

        //     $getRates = CurrentRate::where('hotel_id', $hotel_id)
        //         ->where('stay_date', '>=', $from_date)
        //         ->where('stay_date', '<=', $to_date)
        //         ->where('ota_id', '-1')
        //         ->where('block_status', '0')
        //         ->get()->toArray();

        //     $cta_status = 0;
        //     $ctd_status = 0;

        //     if (sizeof($getRates) > 0) {
        //         $rate_plan_ids = [];

        //         $room_type_id = [];
        //         $get_all_room_types = DB::table('kernel.room_type_table')
        //         ->select('room_type_id')
        //         ->where('hotel_id', $hotel_id)
        //             ->where('is_trash', 0)
        //             ->get();

        //         foreach ($get_all_room_types as $all_room_types) {
        //             $room_type_id[] = $all_room_types->room_type_id;
        //         }

        //         $rate_plan_details = MasterRatePlan::join('room_rate_plan', 'room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
        //         ->select('rate_plan_table.plan_type', 'rate_plan_table.rate_plan_id', 'room_rate_plan.room_type_id')
        //         ->where('room_rate_plan.hotel_id', $hotel_id)
        //             ->whereIn('room_rate_plan.room_type_id', $room_type_id)
        //             ->where('room_rate_plan.is_trash', 0)
        //             ->get()->toArray();

        //         foreach ($rate_plan_details as $plan_type) {
        //             $plan_types[$plan_type['room_type_id']][] = $plan_type['rate_plan_id'];
        //         }

        //         foreach ($room_type_id as $room_type) {
        //             $rate_plan_ids[$room_type] = $plan_types[$room_type];
        //         }


        //         $rate_date = [];
        //         $rate_record = [];

        //         for ($currentDate = strtotime($from_date); $currentDate <= strtotime($to_date); $currentDate = strtotime('+1 day', $currentDate)) {
        //             $currentDateStr = date('Y-m-d', $currentDate);

        //             foreach ($room_type_id as $room_type) {
        //                 foreach ($rate_plan_ids[$room_type] as $rate_plan) {
        //                     $getRatesratesroomtypeid = array_values(
        //                         array_filter($getRates, function ($rates) use ($room_type, $rate_plan, $currentDateStr) {
        //                             return $rates['room_type_id'] == $room_type && $rates['rate_plan_id'] == $rate_plan && $rates['stay_date'] == $currentDateStr;
        //                         })
        //                     );

        //                     if (count($getRatesratesroomtypeid) > 0) {
        //                         $rate_record = [
        //                             'bar_price' => $getRatesratesroomtypeid[0]['bar_price'],
        //                             'created_at' => $getRatesratesroomtypeid[0]['created_at'],
        //                         ];
        //                     } else {
        //                         $rate_record = [
        //                             'bar_price' => 9999999,
        //                             'created_at' => $currentDateStr,
        //                         ];
        //                     }

        //                     $rate_date[$currentDateStr][$room_type][$rate_plan] = $rate_record;
        //                 }
        //             }
        //         }

        //         // print_r($rate_date);
        //         // exit;

        //         foreach ($rate_date as $rt_dt => $value) {
        //             $rate_plan_min_price = 1000000;
        //             foreach ($room_type_id as $room_type) {
        //                 if (isset($value[$room_type])) {

        //                     foreach ($value[$room_type] as $rooms) {
        //                         if (isset($rooms['bar_price']) && $rooms['bar_price'] < $rate_plan_min_price) {
        //                             $rate_plan_min_price = $rooms['bar_price'];
        //                         }
        //                     }
        //                 }
        //             }
        //             if ($rate_plan_min_price != 1000000) {
        //                 $rate_date[$rt_dt]['min_price'] = $rate_plan_min_price;
        //             } else {
        //                 $rate_date[$rt_dt]['min_price'] = 'NA';
        //             }
        //         }

        //         $availableRoomsDateWise = [];
        //         $dpCurInvDetails = DynamicPricingCurrentInventory::where('stay_day', '>=', $from_date)
        //             ->where('stay_day', '<=', $to_date)
        //             ->where('hotel_id', $hotel_id)
        //             ->where('ota_id', '-1')
        //             ->groupBy('stay_day', 'room_type_id')
        //             ->orderBy('stay_day', 'ASC')
        //             ->get();

        //         $minprice_array = array();

        //         for ($d = $from_date; strtotime($d) < strtotime($booking_start_date);) {
        //             $rate_date[$d]['min_price'] = 'NA';
        //             $d = date('Y-m-d', strtotime($d . ' + 1 day'));
        //         }

        //         foreach ($rate_date as $rt_dt => $value) {
        //             if ($value['min_price'] != 'NA') {
        //                 $minprice_array[] = $value['min_price'];
        //             } else {
        //                 $minprice_array[] = 'NA';
        //             }

        //             $is_room_available = 0;
        //             foreach ($dpCurInvDetails as $details) {
        //                 if ($details->stay_day == $rt_dt) {
        //                     if ($details->block_status == 0 && $details->no_of_rooms > 0) {
        //                         $is_room_available = 1;
        //                         $cta_status = $details->cta_status;
        //                         $ctd_status = $details->ctd_status;
        //                         break;
        //                     }
        //                 }
        //             }

        //             $min_price =  min($minprice_array);
        //             if ($min_price != 'NA') {
        //                 $min_price = round($this->curency->currencyDetails($min_price, $currency_name, $baseCurrency), 2);
        //             } else {
        //                 $min_price = $min_price;
        //                 $is_room_available = 0;
        //             }
        //             $minprice_array = [];

        //             $current_month = date('M');
        //             $month = date('M', strtotime($rt_dt));
        //             if ($current_month == $month) {
        //                 $availableRoomsDateWise[$month][] = array('date' => $rt_dt, 'minimum_rates' => $min_price, 'currency_symbol' => $currency_symbol, 'is_room_available' => $is_room_available, 'cta_status' => $cta_status, 'ctd_status' => $ctd_status);
        //             } else {
        //                 $availableRoomsDateWise[$month][] = array('date' => $rt_dt, 'minimum_rates' => $min_price, 'currency_symbol' => $currency_symbol, 'is_room_available' => $is_room_available, 'cta_status' => $cta_status, 'ctd_status' => $ctd_status);
        //             }
        //         }
        //     } else {
        //         for ($dt = $from_date; strtotime($dt) <= strtotime($to_date);) {

        //             $current_month = date('M');
        //             $month = date('M', strtotime($dt));
        //             if ($current_month == $month) {
        //                 $availableRoomsDateWise[$month][] = array('date' => $dt, 'minimum_rates' => 'NA', 'currency_symbol' => $currency_symbol, 'is_room_available' => 0, 'cta_status' => $cta_status, 'ctd_status' => $ctd_status);
        //             } else {
        //                 $availableRoomsDateWise[$month][] = array('date' => $dt, 'minimum_rates' => 'NA', 'currency_symbol' => $currency_symbol, 'is_room_available' => 0, 'cta_status' => $cta_status, 'ctd_status' => $ctd_status);
        //             }
        //             $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
        //         }
        //     }

        //     if ($availableRoomsDateWise) {
        //         $result = array(
        //             'status' => 1, "message" => 'Bookings fetched', 'details' => $availableRoomsDateWise
        //         );
        //         return response()->json($result);
        //     } else {
        //         $result = array('status' => 0, "message" => 'Bookings fetched Failed');
        //         return response()->json($result);
        //     }
        // }
    }


    public function minRatesTest($hotel_id, $from_date, $currency_name, $baseCurrency)
    {
        $from_date = date('Y-m-d', strtotime($from_date));
        $next_month = date('Y-m-d', strtotime($from_date . ' + 1 months'));
        $to_date = date('Y-m-t', strtotime($next_month));

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->first();
        $adv_days = $hotel_info->advance_booking_days;
        $today_date = date('Y-m-d');
        $booking_start_date = date('Y-m-d', strtotime($today_date . ' + ' . $adv_days . ' day'));

        $currency_symbol = '20B9';
        if ($currency_name == 'USD') {
            $currency_symbol = '0024';
        } elseif ($currency_name == 'EUR') {
            $currency_symbol = '20AC';
        } elseif ($currency_name == 'GBP') {
            $currency_symbol = '00A3';
        } elseif ($currency_name == 'BDT') {
            $currency_symbol = '09F3';
        }

        $getRates = CurrentRate::where('hotel_id', $hotel_id)
            ->where('stay_date', '>=', $from_date)
            ->where('stay_date', '<=', $to_date)
            ->where('ota_id', '-1')
            ->where('block_status', '0')
            ->get()->toArray();

        if (sizeof($getRates) > 0) {
            $rate_plan_ids = [];

            $room_type_id = [];
            $get_all_room_types = DB::table('kernel.room_type_table')
                ->select('room_type_id')
                ->where('hotel_id', $hotel_id)
                ->where('is_trash', 0)
                ->get();

            foreach ($get_all_room_types as $all_room_types) {
                $room_type_id[] = $all_room_types->room_type_id;
            }

            $rate_plan_details = MasterRatePlan::join('room_rate_plan', 'room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
                ->select('rate_plan_table.plan_type', 'rate_plan_table.rate_plan_id', 'room_rate_plan.room_type_id')
                ->where('room_rate_plan.hotel_id', $hotel_id)
                ->whereIn('room_rate_plan.room_type_id', $room_type_id)
                ->where('room_rate_plan.is_trash', 0)
                ->get()->toArray();

            foreach ($rate_plan_details as $plan_type) {
                $plan_types[$plan_type['room_type_id']][] = $plan_type['rate_plan_id'];
            }

            foreach ($room_type_id as $room_type) {
                $rate_plan_ids[$room_type] = $plan_types[$room_type];
            }


            $rate_date = [];
            $rate_record = [];

            for ($currentDate = strtotime($from_date); $currentDate <= strtotime($to_date); $currentDate = strtotime('+1 day', $currentDate)) {
                $currentDateStr = date('Y-m-d', $currentDate);

                foreach ($room_type_id as $room_type) {
                    foreach ($rate_plan_ids[$room_type] as $rate_plan) {
                        $getRatesratesroomtypeid = array_values(
                            array_filter($getRates, function ($rates) use ($room_type, $rate_plan, $currentDateStr) {
                                return $rates['room_type_id'] == $room_type && $rates['rate_plan_id'] == $rate_plan && $rates['stay_date'] == $currentDateStr;
                            })
                        );

                        if (count($getRatesratesroomtypeid) > 0) {
                            $rate_record = [
                                'bar_price' => $getRatesratesroomtypeid[0]['bar_price'],
                                'created_at' => $getRatesratesroomtypeid[0]['created_at'],
                            ];
                        } else {
                            $rate_record = [
                                'bar_price' => 9999999,
                                'created_at' => $currentDateStr,
                            ];
                        }

                        $rate_date[$currentDateStr][$room_type][$rate_plan] = $rate_record;
                    }
                }
            }

            // print_r($rate_date);
            // exit;

            foreach ($rate_date as $rt_dt => $value) {
                $rate_plan_min_price = 1000000;
                foreach ($room_type_id as $room_type) {
                    if (isset($value[$room_type])) {

                        foreach ($value[$room_type] as $rooms) {
                            if (isset($rooms['bar_price']) && $rooms['bar_price'] < $rate_plan_min_price) {
                                $rate_plan_min_price = $rooms['bar_price'];
                            }
                        }
                    }
                }
                if ($rate_plan_min_price != 1000000) {
                    $rate_date[$rt_dt]['min_price'] = $rate_plan_min_price;
                } else {
                    $rate_date[$rt_dt]['min_price'] = 'NA';
                }
            }

            $availableRoomsDateWise = [];
            $dpCurInvDetails = DynamicPricingCurrentInventory::where('stay_day', '>=', $from_date)
                ->where('stay_day', '<=', $to_date)
                ->where('hotel_id', $hotel_id)
                ->where('ota_id', '-1')
                ->groupBy('stay_day', 'room_type_id')
                ->orderBy('stay_day', 'ASC')
                ->get();

            $minprice_array = array();

            for ($d = $from_date; strtotime($d) < strtotime($booking_start_date);) {
                $rate_date[$d]['min_price'] = 'NA';
                $d = date('Y-m-d', strtotime($d . ' + 1 day'));
            }

            foreach ($rate_date as $rt_dt => $value) {
                if ($value['min_price'] != 'NA') {
                    $minprice_array[] = $value['min_price'];
                } else {
                    $minprice_array[] = 'NA';
                }

                $is_room_available = 0;
                foreach ($dpCurInvDetails as $details) {
                    if ($details->stay_day == $rt_dt) {
                        if ($details->block_status == 0 && $details->no_of_rooms > 0) {
                            $is_room_available = 1;
                        }
                    }
                }

                $min_price =  min($minprice_array);
                if ($min_price != 'NA') {
                    $min_price = round($this->curency->currencyDetails($min_price, $currency_name, $baseCurrency), 2);
                } else {
                    $min_price = $min_price;
                    $is_room_available = 0;
                }
                $minprice_array = [];

                $current_month = date('M');
                $month = date('M', strtotime($rt_dt));
                if ($current_month == $month) {
                    $availableRoomsDateWise[$month][] = array('date' => $rt_dt, 'minimum_rates' => $min_price, 'currency_symbol' => $currency_symbol, 'is_room_available' => $is_room_available);
                } else {
                    $availableRoomsDateWise[$month][] = array('date' => $rt_dt, 'minimum_rates' => $min_price, 'currency_symbol' => $currency_symbol, 'is_room_available' => $is_room_available);
                }
            }
        } else {
            for ($dt = $from_date; strtotime($dt) <= strtotime($to_date);) {

                $current_month = date('M');
                $month = date('M', strtotime($dt));
                if ($current_month == $month) {
                    $availableRoomsDateWise[$month][] = array('date' => $dt, 'minimum_rates' => 'NA', 'currency_symbol' => $currency_symbol, 'is_room_available' => 0);
                } else {
                    $availableRoomsDateWise[$month][] = array('date' => $dt, 'minimum_rates' => 'NA', 'currency_symbol' => $currency_symbol, 'is_room_available' => 0);
                }
                $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
            }
        }

        if ($availableRoomsDateWise) {
            $result = array(
                'status' => 1, "message" => 'Bookings fetched', 'details' => $availableRoomsDateWise
            );
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Bookings fetched Failed');
            return response()->json($result);
        }
    }

    public function getInvByHotelTestold(string $api_key, int $hotel_id, string $date_from, string $date_to, string $currency_name, Request $request)
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
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0, 'be_room_status' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people as base_adult', 'max_child as base_child', 'max_infant as base_infant', 'image', 'extra_person as extra_adult', 'extra_child', 'max_occupancy', 'room_amenities', 'room_size_value', 'room_size_unit', 'bed_type', 'room_view_type', 'description')->where($conditions)->get();

        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency; //Get base currency
        $room_type_n_rate_plans = array();

        if ($room_types) {
            foreach ($room_types as $key => $room) {
                $room['min_inv'] = 0;
                $room['image'] = trim($room['image'], ',');
                $room['image'] = explode(',', $room['image']); //Converting string to array
                $images = $this->getImages($room->image); //--------- get all the images of rooms 
                $room_types[$key]['allImages'] = $images;
                $room['image'] = $images; //Getting actual amenity names
                if (is_object($room['image']) && sizeof($room['image']) > 0) {
                    $room['image'] = $room['image'][0]->image_name;
                } else {
                    $room['image'] = $this->getImages(array(1));
                    $room['image'] = $room['image'][0]->image_name;
                }

                $room['room_amenities'] = explode(',', $room['room_amenities']);
                $room['room_amenities'] = $this->getRoomamenity($room['room_amenities']);

                $data = $this->getCurrentInventeryByRoomTYpe($hotel_id, $room['room_type_id'], $date_from, $date_to);
                //changes if from date and to date is same and min inv is coming 0
                if (!isset($data[0]['no_of_rooms'])) {
                    if ($date_from < $today) {
                        $room['min_inv']  = 1;
                    }
                } else {
                    $room['min_inv']  = $data[0]['no_of_rooms'];
                }

                foreach ($data as $inv_room) {
                    if ($inv_room['no_of_rooms'] < $room['min_inv']) {
                        $room['min_inv']  = $inv_room['no_of_rooms'];
                    }
                }

                $room['inv'] = $data;
                $room["max_occupancy"] = $room["max_occupancy"];
                $room_type_n_rate_plans = MasterHotelRatePlan::join('rate_plan_table as a', function ($join) {
                    $join->on('room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id');
                })
                    ->select('a.rate_plan_id', 'plan_type', 'plan_name', 'room_rate_plan.bar_price')
                    ->where('room_rate_plan.hotel_id', $hotel_id)
                    ->where('room_rate_plan.is_trash', 0)
                    ->where('room_rate_plan.be_rate_status', 0)
                    ->where('room_rate_plan.room_type_id', $room['room_type_id'])
                    // ->orderBy('room_rate_plan.bar_price', 'ASC')
                    ->distinct()
                    ->get();

                $room['min_room_price'] = 0;
                $room['discount_percentage'] = 0;
                $rateplan_details = array();
                foreach ($room_type_n_rate_plans as $key1 => $all_types) {
                    $rate_plan_id = (int)$all_types['rate_plan_id'];
                    $room_rate_status = $this->checkroomrateplan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    $rates = [];
                    if ($room_rate_status) {
                        $data = $this->getCurrentRatesByRoomnRatePlanTest($hotel_id, $room['room_type_id'], $rate_plan_id, $date_from, $date_to, $baseCurrency, $currency_name);
                        foreach ($data as $d) {
                            $rates[] = $d;
                        }
                    } else {
                        continue;
                    }

                    $all_types['bar_price'] = 0;
                    $rateplan_details[$key1]['bar_price'] = 0;
                    $rateplan_details[$key1]['price_after_discount'] = 0;
                    $rateplan_details[$key1]['discount_percentage'] = 0;
                    $rateplan_details[$key1]['plan_name'] = $all_types["plan_name"];
                    $rateplan_details[$key1]['plan_type'] = $all_types["plan_type"];
                    $rateplan_details[$key1]['rate_plan_id'] = $all_types["rate_plan_id"];

                    if ($data) {
                        $check_bar_price = array_search(0, array_column($data, 'bar_price'));
                        $check_block_status = array_search(1, array_column($data, 'block_status'));

                        if ($check_bar_price === false && $check_block_status === false) {

                            foreach ($data as $key2 => $rate) {

                                $i = 0;
                                $j = 0;

                                if (isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"]) == 0) {
                                    $rate["multiple_occupancy"][0] = $all_types['bar_price'];
                                }
                                $base_adult_count = $room['base_adult'] - 1;
                                $multiple_occ_count = sizeof($rate["multiple_occupancy"]);

                                if ($base_adult_count > $multiple_occ_count) {
                                    for ($i = 0; $i < $base_adult_count; $i++) {
                                        if (!isset($rate["multiple_occupancy"][$i])) {
                                            $rate["multiple_occupancy"][$i] = 12345;
                                        }
                                    }
                                }

                                $rates[$key2]['multiple_occupancy'] = $rate["multiple_occupancy"];

                                $all_types['bar_price'] = $rate['bar_price'];
                                $all_types['price_after_discount'] = floatval($rate['price_after_discount']);
                                $all_types['discount_percentage'] = $rate['discount_percentage'];
                                $all_types['extra_adult_price'] = $rate['extra_adult_price'];
                                $all_types['extra_child_price'] = $rate['extra_child_price'];
                            }
                            $room['discount_percentage'] =  $all_types['discount_percentage'];
                        } else {
                            $all_types['bar_price'] = 0;
                        }
                    }

                    if ($room['min_room_price'] == 0) {
                        $room['min_room_price'] = $all_types['bar_price'];
                        $room['price_after_discount'] =  floatval($all_types['price_after_discount']);
                    }

                    if ($all_types['bar_price'] <= $room['min_room_price'] && $all_types['bar_price'] != null) {
                        $room['min_room_price']  = $all_types['bar_price'];
                        $room['price_after_discount'] =  floatval($all_types['price_after_discount']);
                    }

                    if ($all_types['bar_price'] != 0 || $all_types['bar_price'] != null) {
                        $all_types['rates'] = $rates;
                    }

                    if ($all_types['bar_price'] == 0 || $all_types['bar_price'] == NULL) {
                        unset($room_type_n_rate_plans[$key1]);
                    }
                }

                foreach ($room_type_n_rate_plans as $i => $room_rate_plan) {
                    if (!isset($room_rate_plan['rates'])) {
                        unset($room_type_n_rate_plans[$i]);
                    }
                }

                $room_type_n_rate = json_decode(json_encode($room_type_n_rate_plans), true);
                $room_type_n_rate_plans = array_values($room_type_n_rate);

                if (isset($room_type_n_rate_plans['rate_plans'])) {
                    $ratePlans = collect($room_type_n_rate_plans['rate_plans']);
                    $sortedRatePlans = $ratePlans->sortBy('bar_price')->values();
                }

                if ($room['min_room_price'] != 0 &&  $room['min_room_price'] != null) {
                    $room['rate_plans'] = $sortedRatePlans;
                }
            }

            $available_room = [];
            $not_available_room = [];
            $available_room_details = [];
            $not_available_room_details = [];
            $check_inv_block_status = [];

            foreach ($room_types as $room_detail) {
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

            $res = array('status' => 1, 'message' => "Hotel inventory retrieved successfully ", 'data' => $room_types);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
    }

    public function getCurrentRatesByRoomnRatePlanTest($hotel_id, $room_type_id, $rate_plan_id, $date_from, $date_to, $baseCurrency, $currency_name)
    {
        $filtered_rate = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $date_to = date('Y-m-d', strtotime($date_to . '-1 day'));

        $coupons = Coupons::where('hotel_id', $hotel_id)
            ->whereIN('room_type_id', [0, $room_type_id])
            ->where('coupon_for', 1)
            ->where('is_trash', 0)
            ->get()->toArray();

        $rates = CurrentRate::where('room_type_id', $room_type_id)
            ->where('hotel_id', $hotel_id)
            ->where('rate_plan_id', $rate_plan_id)
            ->where('ota_id', '-1')
            ->whereBetween('stay_date', [$date_from, $date_to])
            ->get()->toArray();

        if (sizeof($rates) > 0) {
            foreach ($rates as $rate) {

                $multiple_occupancy = json_decode($rate['multiple_occupancy']);
                if (is_string($multiple_occupancy)) {
                    $multiple_occupancy = json_decode($multiple_occupancy);
                }

                if ($baseCurrency == $currency_name) {
                    $filtered_rate[$rate['stay_date']] = array('bar_price' => $rate['bar_price'], 'extra_adult_price' => $rate['extra_adult_price'], 'extra_child_price' => $rate['extra_child_price'], 'date' => $rate['stay_date'], 'block_status' => $rate['block_status'], 'multiple_occupancy' => $multiple_occupancy);
                } else {
                    $converted_multiple_occupancy = [];
                    foreach ($multiple_occupancy as $m_occupancy) {
                        $m_occupancy =  round($this->curency->currencyDetails($m_occupancy, $currency_name, $baseCurrency), 2);
                        array_push($converted_multiple_occupancy, $m_occupancy);
                    }
                    $bar_price =  round($this->curency->currencyDetails($rate['bar_price'], $currency_name, $baseCurrency), 2);
                    $extra_adult_price = round($this->curency->currencyDetails($rate['extra_adult_price'], $currency_name, $baseCurrency), 2);
                    $extra_child_price = round($this->curency->currencyDetails($rate['extra_child_price'], $currency_name, $baseCurrency), 2);

                    $filtered_rate[$rate['stay_date']] = array('bar_price' => $bar_price, 'extra_adult_price' => $extra_adult_price, 'extra_child_price' => $extra_child_price, 'date' => $rate['stay_date'], 'block_status' => $rate['block_status'], 'multiple_occupancy' => $converted_multiple_occupancy);
                }
            }

            for ($i = 0; $i < $diff; $i++) {
                $d = $date_from;
                $coupons_percentage = [];
                foreach ($coupons as $coupon) {
                    $valid_from = $coupon['valid_from'];
                    $valid_to = $coupon['valid_to'];
                    if ($date_from >= $valid_from && $date_from <= $valid_to) {
                        $coupons_percentage[] = $coupon['discount'];
                    }
                }

                $drate =  array('bar_price' => 0, 'extra_adult_price' => 0, 'extra_child_price' => 0, 'date' => $date_from, 'block_status' => 0);
                $filtered_rate[$d] = isset($filtered_rate[$d]) ? $filtered_rate[$d] : $drate;
                $rates_by_date = $filtered_rate[$d];
                $bar_price = $rates_by_date['bar_price'];
                if (empty($coupons_percentage)) {
                    $applied_coupon = 0;
                    $discounted_price = 0;
                } else {
                    $applied_coupon = max($coupons_percentage);
                    $discounted_price = $bar_price * $applied_coupon / 100;
                }
                $price_after_discount = $bar_price - $discounted_price;
                $filtered_rate[$d]['price_after_discount'] = floatval($price_after_discount);
                $filtered_rate[$d]['discount_percentage'] = $applied_coupon;


                if (isset($filtered_rate[$d]['multiple_occupancy'])) {
                    $multiple_occ = [];
                    foreach ($filtered_rate[$d]['multiple_occupancy'] as $occupancy) {
                        $discounted_price = $occupancy * $applied_coupon / 100;
                        $multiple_occupancy = $occupancy - $discounted_price;
                        array_push($multiple_occ, $multiple_occupancy);
                    }
                    $filtered_rate[$d]['multiple_occupancy'] = $multiple_occ;
                }
                $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        } else {
            for ($i = 0; $i < $diff; $i++) {
                $d = $date_from;
                $filtered_rate[$date_from] = array('bar_price' => 0, 'extra_adult_price' => 0, 'extra_child_price' => 0, 'date' => $date_from, 'block_status' => 0, 'price_after_discount' => 0, 'discount_percentage' => 0);
                $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }

        $filtered_rate = array_values($filtered_rate);
        return $filtered_rate;
    }

    public function getInvByHotelTest(Request $request)
    {
        $api_key = $request->api_key;
        $hotel_id = $request->hotel_id;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $currency_name = $request->currency;
        $rooms = $request->rooms;
        // $adult = $request->adult;
        // $child = $request->child;

        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 1, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime($date_to));
        $today = date('Y-m-d');
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0, 'be_room_status' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people as base_adult', 'max_child as base_child', 'max_infant as base_infant', 'image', 'extra_person as extra_adult', 'extra_child', 'max_occupancy', 'room_amenities', 'room_size_value', 'room_size_unit', 'bed_type', 'room_view_type', 'description')->where($conditions)->get();

        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency; //Get base currency
        $room_type_n_rate_plans = array();

        if ($room_types) {
            foreach ($room_types as $key => $room) {
                $room['min_inv'] = 0;
                $room['image'] = trim($room['image'], ',');
                $room['image'] = explode(',', $room['image']); //Converting string to array
                $images = $this->getImages($room->image); //--------- get all the images of rooms 
                $room_types[$key]['allImages'] = $images;
                $room['image'] = $images; //Getting actual amenity names
                if (is_object($room['image']) && sizeof($room['image']) > 0) {
                    $room['image'] = $room['image'][0]->image_name;
                } else {
                    $room['image'] = $this->getImages(array(1));
                    $room['image'] = $room['image'][0]->image_name;
                }

                $room['room_amenities'] = explode(',', $room['room_amenities']);
                $room['room_amenities'] = $this->getRoomamenity($room['room_amenities']);
                $room['description'] = strip_tags($room['description']);

                $data = $this->getCurrentInventeryByRoomTYpe($hotel_id, $room['room_type_id'], $date_from, $date_to);
                //changes if from date and to date is same and min inv is coming 0
                if (!isset($data[0]['no_of_rooms'])) {
                    if ($date_from < $today) {
                        $room['min_inv']  = 1;
                    }
                } else {
                    $room['min_inv']  = $data[0]['no_of_rooms'];
                }

                foreach ($data as $inv_room) {
                    if ($inv_room['no_of_rooms'] < $room['min_inv']) {
                        $room['min_inv']  = $inv_room['no_of_rooms'];
                    }

                    if ($inv_room['block_status'] == 1) {
                        $room['min_inv'] = 0;
                    }
                }
                $room['min_los'] = $data[0]['los'];

                $room['inv'] = $data;

                $room["max_occupancy"] = $room["max_occupancy"];
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
                $room['discount_percentage'] = 0;
                $rateplan_details = array();
                foreach ($room_type_n_rate_plans as $key1 => $all_types) {
                    $rate_plan_id = (int)$all_types['rate_plan_id'];
                    $room_rate_status = $this->checkroomrateplan($room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    $rates = [];
                    if ($room_rate_status) {
                        $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $room['room_type_id'], $rate_plan_id, $date_from, $date_to, $baseCurrency, $currency_name);
                        foreach ($data as $d) {
                            $rates[] = $d;
                        }
                    } else {
                        continue;
                    }

                    $all_types['bar_price'] = 0;
                    $rateplan_details[$key1]['bar_price'] = 0;
                    $rateplan_details[$key1]['price_after_discount'] = 0;
                    $rateplan_details[$key1]['discount_percentage'] = 0;
                    $rateplan_details[$key1]['plan_name'] = $all_types["plan_name"];
                    $rateplan_details[$key1]['plan_type'] = $all_types["plan_type"];
                    $rateplan_details[$key1]['rate_plan_id'] = $all_types["rate_plan_id"];

                    if ($data) {
                        $check_bar_price = array_search(0, array_column($data, 'bar_price'));
                        $check_block_status = array_search(1, array_column($data, 'block_status'));

                        if ($check_bar_price === false && $check_block_status === false) {

                            foreach ($data as $key2 => $rate) {

                                $i = 0;
                                $j = 0;

                                if (isset($rate["multiple_occupancy"]) && sizeof($rate["multiple_occupancy"]) == 0) {
                                    $rate["multiple_occupancy"][0] = $all_types['bar_price'];
                                }
                                $base_adult_count = $room['base_adult'] - 1;
                                $multiple_occ_count = sizeof($rate["multiple_occupancy"]);

                                if ($base_adult_count > $multiple_occ_count) {
                                    for ($i = 0; $i < $base_adult_count; $i++) {
                                        if (!isset($rate["multiple_occupancy"][$i])) {
                                            // $rate["multiple_occupancy"][$i] =  12345;
                                            $rate["multiple_occupancy"][$i] =  $all_types['bar_price'];
                                        }
                                    }
                                }

                                $rates[$key2]['multiple_occupancy'] = $rate["multiple_occupancy"];

                                $all_types['bar_price'] = $rate['bar_price'];
                                $all_types['price_after_discount'] = floatval($rate['price_after_discount']);
                                $all_types['discount_percentage'] = $rate['discount_percentage'];
                                $all_types['extra_adult_price'] = $rate['extra_adult_price'];
                                $all_types['extra_child_price'] = $rate['extra_child_price'];
                            }
                            $room['discount_percentage'] =  $all_types['discount_percentage'];
                        } else {
                            $all_types['bar_price'] = 0;
                        }
                    }

                    if ($room['min_room_price'] == 0) {
                        $room['min_room_price'] = $all_types['bar_price'];
                        $room['price_after_discount'] =  floatval($all_types['price_after_discount']);
                    }

                    if ($all_types['bar_price'] <= $room['min_room_price'] && $all_types['bar_price'] != null) {
                        $room['min_room_price']  = $all_types['bar_price'];
                        $room['price_after_discount'] =  floatval($all_types['price_after_discount']);
                    }

                    if ($all_types['bar_price'] != 0 || $all_types['bar_price'] != null) {
                        $all_types['rates'] = $rates;
                    }

                    if ($all_types['bar_price'] == 0 || $all_types['bar_price'] == NULL) {
                        unset($room_type_n_rate_plans[$key1]);
                    }
                }

                foreach ($room_type_n_rate_plans as $i => $room_rate_plan) {
                    if (!isset($room_rate_plan['rates'])) {
                        unset($room_type_n_rate_plans[$i]);
                    }
                }

                $room_type_n_rate = json_decode(json_encode($room_type_n_rate_plans), true);
                $room_type_n_rate_plans = array_values($room_type_n_rate);

                if (isset($room_type_n_rate_plans)) {
                    $ratePlans = collect($room_type_n_rate_plans);
                    $sortedRatePlans = $ratePlans->sortBy('bar_price')->values();
                }

                if ($room['min_room_price'] != 0 &&  $room['min_room_price'] != null) {
                    $room['rate_plans'] = $sortedRatePlans;
                }
            }

            $available_room = [];
            $not_available_room = [];
            $available_room_details = [];
            $not_available_room_details = [];
            $check_inv_block_status = [];

            $no_of_rooms_date_wise = [];

            foreach ($room_types as $room_detail) {
                $new_room_details[$room_detail['room_type_id']] = $room_detail;
                $inv_details = $room_detail['inv'];

                foreach ($inv_details as $inv_detail) {
                    $date = $inv_detail['date'];
                    $no_of_rooms = $inv_detail['no_of_rooms'];

                    if (!isset($no_of_rooms_date_wise[$date])) {
                        $no_of_rooms_date_wise[$date] = [];
                    }
                    $no_of_rooms_date_wise[$date][] = $no_of_rooms;
                }
            }

            $sums = [];
            foreach ($no_of_rooms_date_wise as $date => $values) {
                $sums[] = array_sum($values);
            }

            $smallestValue = collect($sums)->min();

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
                    foreach ($check_no_of_rooms as $check_rooms) {
                        if ($check_rooms <= 0) {
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

                if ($rooms > $smallestValue) {
                    $room->min_inv = 0;
                }

                if (!isset($room->rate_plans)) {
                    $room->min_inv = 0;
                }
            }

            usort($available_room, array($this, 'compareByPrice'));
            usort($available_room_details, array($this, 'compareByPrice'));
            $room_types = array_merge($available_room, $not_available_room);
            if ($diff > 15) {
                $res = array('status' => 2, 'message' => "No. of nights should be less than 15", 'data' => $room_types);
                return response()->json($res);
            } else {
                $res = array('status' => 1, 'message' => "Hotel inventory retrieved successfully ", 'data' => $room_types);
                return response()->json($res);
            }
        } else {
            $res = array('status' => 1, 'message' => "Hotel inventory retrieval failed due to invalid information");
            return response()->json($res);
        }
    }

    public function altAvailableDatesByroomTypes(Request $request)
    {

        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $check_in = $data['check_in'];
        $check_out = $data['check_out'];
        $no_of_rooms = $data['no_of_rooms'];

        $checkout = date('Y-m-d', strtotime($check_in . ' +15 day'));

        $date1 = date_create($check_in);
        $date2 = date_create($check_out);
        $diff = date_diff($date1, $date2);
        $no_of_nights = $diff->format("%a");
        if ($no_of_nights == 0) {
            $no_of_nights = 1;
        }

        $date_array = [];
        $period     = new \DatePeriod(
            new \DateTime($data['check_in']),
            new \DateInterval('P1D'),
            new \DateTime($checkout)
        );
        foreach ($period as $value) {
            $date_array[] = $value->format('Y-m-d');
        }

        $invetories = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
            ->where('room_type_id', $room_type_id)
            ->where('no_of_rooms', '>=', $no_of_rooms)
            ->where('block_status', 0)
            ->whereIn('stay_day', $date_array)
            ->get();

        $available_inv_dates = [];
        foreach ($invetories as $invetory) {
            $available_inv_dates[] = $invetory->stay_day;
        }

        $rates = CurrentRate::where('room_type_id', $room_type_id)
            ->where('hotel_id', $hotel_id)
            ->where('ota_id', '-1')
            ->whereIn('stay_date', $available_inv_dates)
            ->where('block_status', 0)
            ->get();

        $available_rates_dates = [];
        foreach ($rates as $rate) {
            if (!isset($available_rates_dates[$rate->stay_date])) {
                $available_rates_dates[$rate->stay_date] = true;
            }
        }
        $uniqueDatesArray = array_keys($available_rates_dates);
        usort($uniqueDatesArray, array($this, 'customSort'));

        $available_date_array = [];
        foreach ($uniqueDatesArray as $uniqueDates) {
            $status = true;
            $d = $uniqueDates;
            for ($i = 1; $i <= $no_of_nights - 1; $i++) {
                $d = date('Y-m-d', strtotime($d . ' +1 day'));
                if (!in_array($d, $uniqueDatesArray)) {
                    $status = false;
                    break;
                }
            }
            if ($status) {
                $available_date_array[] = $uniqueDates;
            }
        }


        if (!empty($available_date_array)) {
            $firstThreeElements = array_slice($available_date_array, 0, 3);
            $res = array('status' => 1, 'message' => "Date Retrive successfully", 'available_dates' => $firstThreeElements);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => "No rooms available for next 15 days");
            return response()->json($res);
        }
    }

    function customSort($a, $b)
    {
        return strtotime($a) - strtotime($b);
    }


    public function connectedOtaRates(Request $request)
    {

        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $from_date = $data['from_date'];
        $to_date = $data['to_date'];

        $connected_ota = CmOtaDetails::where('hotel_id', $hotel_id)->where('is_active', 1)->where('is_status', 1)->get()->toArray();
        $ota_wise_min_rates = [];
        array_push($connected_ota, array('ota_id' => '-1', 'ota_name' => 'BookingEngine'));


        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://cm.bookingjini.com/extranetv4/getallchannel/2600',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $logoResp = collect(json_decode($response, true)['ota']);

        $ota_logo_path = "";

        foreach ($connected_ota as $ota_details) {

            $ota_id = $ota_details['ota_id'];
            $ota_name = $ota_details['ota_name'];
            $ota_logo_path = $logoResp->where("ota_name", $ota_name)->first()['logo'];

            $url = 'https://cm.bookingjini.com/extranetv4/rates-channel-wise/' . $hotel_id . '/' . $ota_id . '/' . $from_date . '/' . $to_date;

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

            if ($response->status==1) {
                $channel_rates = $response->channel_rates;
                $minimum_price_by_room_type = $channel_rates->minimum_price_by_room_type;
                $price_array = [];
                $room_type_array = [];

                foreach ($minimum_price_by_room_type as $key => $minimum_price) {

                    if ($minimum_price[0]->min_price != 'NA') {
                        $price_array[] = $minimum_price[0]->min_price;
                        $room_type_array[] = $key;
                    }
                }

                if(!empty($price_array)){
                    $min_price = min($price_array);
                    $min_price_index = array_search($min_price, $price_array);
                    $min_price_room_type = $room_type_array[$min_price_index];
                }else{
                    $min_price = 0;
                    $min_price_index = 0;
                    $min_price_room_type = 0;
                }

                
              


                if ($ota_id == -1) {
                    $maxDiscount = Coupons::where('hotel_id', $hotel_id)
                        ->where('valid_from', '<=', $from_date)
                        ->where('valid_to', '>=', $from_date)
                        ->where('room_type_id', '=', $min_price_room_type)
                        ->where('coupon_for', 1)
                        ->where('is_trash', 0)
                        ->max('discount');

                    $coupon_discount_price = $min_price * $maxDiscount / 100;
                    $discounted_price = $min_price - $coupon_discount_price;

                    $min_price = $discounted_price;
                }

                if($min_price>0){
                    $ota_wise_min_rates[] = array('ota_name' => $ota_name, 'min_price' => $min_price, 'logo' => $ota_logo_path);

                }

            }
        }

        if (count($ota_wise_min_rates)>1) {
            $res = array('status' => 1,  'data' => $ota_wise_min_rates);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => "Ota rate not found");
            return response()->json($res);
        }
    }
}
