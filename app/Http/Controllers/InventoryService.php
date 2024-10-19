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
use App\CmOtaBooking; //class name from model
use App\Booking; //class name from model
use App\CmOtaRoomTypeSynchronize; //class name from model;
use App\CompanyDetails;
use App\HotelInformation;
use App\Coupons;
use DB;
//create a new class ManageInventoryController

class InventoryService extends Controller
{

    /**
     * Get Inventory
     * get one record of Inventory
     * @auther subhradip
     * function getInventery for detecting data
     **/
    public function getInventeryByRoomTYpe(int $room_type_id, string $date_from, string $date_to, int $mindays)
    {
        $filtered_inventory = array();
        $inventory = new Inventory();
        $masterroomtype = new MasterRoomType();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $diff1 = date_diff($date1, $date3);
        $diff1 = $diff1->format("%a");
        if ($diff1 <= $mindays && $mindays != 0) {
            $d = $date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);
            $array = array('no_of_rooms' => 0, 'block_status' => 1, 'los' => 1, 'room_type_id' => $room_type_id, 'date' => $date_from, 'day' => $day);
            array_push($filtered_inventory, $array);
        } else {
            for ($i = 1; $i <= $diff; $i++) {
                $d = $date_from;
                $timestamp = strtotime($d);
                $day = date('D', $timestamp);
                $inventory_details = $inventory
                    ->where('room_type_id', '=', $room_type_id)
                    ->where('date_from', '<=', $d)
                    ->where('date_to', '>=', $d)
                    ->orderBy('inventory_id', 'desc')
                    ->first();
                if (empty($inventory_details)) {

                    // $inv_rooms= $masterroomtype
                    //                     ->select('total_rooms')
                    //                     ->where('room_type_id' , '=' , $room_type_id)
                    //                     ->where('is_trash',0)
                    //                     ->first();
                    // if(!empty($inv_rooms))
                    // {
                    $array = array(
                        'no_of_rooms' => 0,
                        'block_status' => 0, 'los' => 1, 'room_type_id' => $room_type_id, 'date' => $date_from, 'day' => $day
                    );
                    array_push($filtered_inventory, $array);
                    // }

                } else {
                    $block_status           = trim($inventory_details->block_status);
                    $los                    = trim($inventory_details->los);
                    if ($block_status == 1) {

                        $inv_rooms = $inventory
                            ->select('no_of_rooms')
                            ->where('room_type_id', '=', $room_type_id)
                            ->where('date_from', '<=', $date_from)
                            ->where('date_to', '>=', $date_from)
                            ->where('block_status', '=', 0)
                            ->orderBy('inventory_id', 'desc')
                            ->first();
                        if (empty($inv_rooms)) {
                            $inv_rooms = $masterroomtype
                                ->select('total_rooms')
                                ->where('room_type_id', '=', $room_type_id)
                                ->where('is_trash', 0)
                                ->first();
                            if (!empty($inv_rooms)) {
                                $array = array('no_of_rooms' => $inv_rooms['total_rooms'], 'block_status' => 1, 'los' => 1, 'room_type_id' => $room_type_id, 'date' => $date_from, 'day' => $day);
                                array_push($filtered_inventory, $array);
                            }
                        } else if (!empty($inv_rooms)) {
                            $array = array('no_of_rooms' => $inv_rooms['no_of_rooms'], 'block_status' => 1, 'los' => $los, 'room_type_id' => $room_type_id, 'date' => $date_from, 'day' => $day);
                            array_push($filtered_inventory, $array);
                        }
                    } else {

                        $inv_rooms = $inventory
                            ->select('no_of_rooms')
                            ->where('room_type_id', '=', $room_type_id)
                            ->where('date_from', '<=', $date_from)
                            ->where('date_to', '>=', $date_from)
                            ->orderBy('inventory_id', 'desc')
                            ->first();


                        if (!empty($inv_rooms)) {
                            $array = array('no_of_rooms' => $inv_rooms['no_of_rooms'], 'block_status' => 0, 'los' => $los, 'room_type_id' => $room_type_id, 'date' => $date_from, 'day' => $day);
                            array_push($filtered_inventory, $array);
                        }
                    }
                }
                $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }
        return $filtered_inventory;
    }

    public function getRatesByRoomnRatePlanOld(int $room_type_id, int $rate_plan_id, string $date_from, string $date_to)
    {
        $filtered_rate = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $rateplanlog = new RatePlanLog();
        $masterhotelrateplan = new MasterHotelRatePlan();
        $room_rate_plan_data = $masterhotelrateplan->where(['rate_plan_id' => $rate_plan_id])
            ->select('hotel_id')
            ->where(['room_type_id' => $room_type_id])
            ->first();
        $hotel_id = $room_rate_plan_data->hotel_id;
        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->first();
        $comp_info = CompanyDetails::where('company_id', $hotel_info->company_id)->first();
        $hex_code = $comp_info->hex_code;
        $currency = $comp_info->currency;
        for ($i = 1; $i <= $diff; $i++) {
            $d = $date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);

            $room_rate_plan_details = $masterhotelrateplan
                ->where(['rate_plan_id' => $rate_plan_id])
                ->where(['room_type_id' => $room_type_id])
                ->where('is_trash', 0)
                ->where('be_rate_status', 0)
                ->where('from_date', '<=', $d)
                ->where('to_date', '>=', $d)
                ->first();
            if (!isset($room_rate_plan_details->rate_plan_id)) //If Room rate plans Not with in date range then Latest created room rate plan is considered
            {
                $rate_plan_details = $masterhotelrateplan
                    ->where(['rate_plan_id' => $rate_plan_id])
                    ->where(['room_type_id' => $room_type_id])
                    ->where('is_trash', 0)
                    ->where('be_rate_status', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $rate_plan_details = $room_rate_plan_details;
            }

            //when rate is not updated in rate plan log table it's consider the rate from room rate plan table that is wrong so change it to 0;

            // if($hotel_id==1953){
            $bar_price = 0;
            $bookingjini_price = 0;
            $extra_adult_price = 0;
            $extra_child_price = 0;
            $multiple_occupancy = $rate_plan_details['multiple_occupancy'];
            $before_days_offer = $rate_plan_details['before_days_offer'];
            $stay_duration_offer = $rate_plan_details['stay_duration_offer'];
            $lastminute_offer = $rate_plan_details['lastminute_offer'];
            // }else{
            //     $bar_price = $rate_plan_details['bar_price'];
            //     $multiple_occupancy = $rate_plan_details['multiple_occupancy'];
            //     $bookingjini_price = $rate_plan_details['bookingjini_price'];
            //     $extra_adult_price = $rate_plan_details['extra_adult_price'];
            //     $extra_child_price = $rate_plan_details['extra_child_price'];
            //     $before_days_offer = $rate_plan_details['before_days_offer'];
            //     $stay_duration_offer = $rate_plan_details['stay_duration_offer'];
            //     $lastminute_offer = $rate_plan_details['lastminute_offer'];
            // }

            $rate_plan_log_details = $rateplanlog
                ->select('bar_price', 'multiple_occupancy', 'multiple_days', 'block_status', 'extra_adult_price', 'extra_child_price')
                ->where(['room_type_id' => $room_type_id])
                ->where('rate_plan_id', '=', $rate_plan_id)
                ->where('from_date', '<=', $d)
                ->where('to_date', '>=', $d)
                ->orderBy('rate_plan_log_id', 'desc')
                ->first();

            if (empty($rate_plan_log_details)) {
                $array = array(
                    'bar_price' => $bar_price,
                    'multiple_occupancy' => json_decode($multiple_occupancy),
                    'bookingjini_price' => $bookingjini_price,
                    'extra_adult_price' => $extra_adult_price,
                    'extra_child_price' => $extra_child_price,
                    'before_days_offer' => $before_days_offer,
                    'stay_duration_offer' => $stay_duration_offer,
                    'lastminute_offer' => $lastminute_offer,
                    'rate_plan_id' => $rate_plan_id,
                    'room_type_id' => $room_type_id,
                    'date' => $date_from,
                    'day' => $day,
                    'hex_code' => $hex_code,
                    'block_status' => 0,
                    'currency' => $currency

                );
                array_push($filtered_rate, $array);
            } else {
                $multiple_days = json_decode($rate_plan_log_details->multiple_days);
                $block_status     = $rate_plan_log_details['block_status'];
                if ($multiple_days != null) {
                    if ($multiple_days->$day == 0) {
                        $rate_plan_log_details1 = $rateplanlog
                            ->select('bar_price', 'multiple_occupancy', 'multiple_days', 'block_status', 'extra_adult_price', 'extra_child_price')
                            ->where(['room_type_id' => $room_type_id])
                            ->where('rate_plan_id', '=', $rate_plan_id)
                            ->where('from_date', '<=', $d)
                            ->where('to_date', '>=', $d)
                            ->orderBy('rate_plan_log_id', 'desc')
                            ->skip(1)
                            ->take(2)
                            ->get();

                        if (empty($rate_plan_log_details1[0])) {
                            $array = array(
                                'bar_price' => $bar_price,
                                'multiple_occupancy' => json_decode($multiple_occupancy),
                                'bookingjini_price' => $bookingjini_price,
                                'extra_adult_price' => $extra_adult_price,
                                'extra_child_price' => $extra_child_price,
                                'before_days_offer' => $before_days_offer,
                                'stay_duration_offer' => $stay_duration_offer,
                                'lastminute_offer' => $lastminute_offer,
                                'rate_plan_id' => $rate_plan_id,
                                'room_type_id' => $room_type_id,
                                'date' => $date_from,
                                'day' => $day,
                                'hex_code' => $hex_code,
                                'block_status' => $block_status,
                                'currency' => $currency
                            );
                        } else {

                            $multiple_days1 = json_decode($rate_plan_log_details1[0]->multiple_days);
                            $block_status1 = $rate_plan_log_details1[0]['block_status'];
                            if ($multiple_days1 != null) {
                                if ($multiple_days1->$day == 0) {
                                    $rate_plan_log_details2 = $rateplanlog
                                        ->select('bar_price', 'multiple_occupancy', 'block_status', 'extra_adult_price', 'extra_child_price')
                                        ->where(['room_type_id' => $room_type_id])
                                        ->where('rate_plan_id', '=', $rate_plan_id)
                                        ->where('from_date', '<=', $d)
                                        ->where('to_date', '>=', $d)
                                        ->orderBy('rate_plan_log_id', 'desc')
                                        ->skip(2)
                                        ->take(3)
                                        ->get();
                                    if (empty($rate_plan_log_details2[0])) {
                                        $array = array(
                                            'bar_price' => $bar_price,
                                            'multiple_occupancy' => json_decode($multiple_occupancy),
                                            'bookingjini_price' => $bookingjini_price,
                                            'extra_adult_price' => $extra_adult_price,
                                            'extra_child_price' => $extra_child_price,
                                            'before_days_offer' => $before_days_offer,
                                            'stay_duration_offer' => $stay_duration_offer,
                                            'lastminute_offer' => $lastminute_offer,
                                            'rate_plan_id' => $rate_plan_id,
                                            'room_type_id' => $room_type_id,
                                            'date' => $date_from,
                                            'day' => $day,
                                            'hex_code' => $hex_code,
                                            'block_status' => $block_status1,
                                            'currency' => $currency
                                        );
                                    } else {
                                        $block_status2 = $rate_plan_log_details2[0]['block_status'];
                                        $array = array(
                                            'bar_price' => $rate_plan_log_details2[0]->bar_price,
                                            'multiple_occupancy' => json_decode($rate_plan_log_details2[0]->multiple_occupancy),
                                            'bookingjini_price' => $bookingjini_price,
                                            'extra_adult_price' => $rate_plan_log_details2[0]->extra_adult_price,
                                            'extra_child_price' => $rate_plan_log_details2[0]->extra_child_price,
                                            'before_days_offer' => $before_days_offer,
                                            'stay_duration_offer' => $stay_duration_offer,
                                            'lastminute_offer' => $lastminute_offer,
                                            'rate_plan_id' => $rate_plan_id,
                                            'room_type_id' => $room_type_id,
                                            'date' => $date_from,
                                            'day' => $day,
                                            'hex_code' => $hex_code,
                                            'block_status' => $block_status2,
                                            'currency' => $currency
                                        );
                                    }
                                } else {
                                    $array = array(
                                        'bar_price' => $rate_plan_log_details1[0]->bar_price,
                                        'multiple_occupancy' => json_decode($rate_plan_log_details1[0]->multiple_occupancy),
                                        'bookingjini_price' => $bookingjini_price,
                                        'extra_adult_price' => $rate_plan_log_details1[0]->extra_adult_price,
                                        'extra_child_price' => $rate_plan_log_details1[0]->extra_child_price,
                                        'before_days_offer' => $before_days_offer,
                                        'stay_duration_offer' => $stay_duration_offer,
                                        'lastminute_offer' => $lastminute_offer,
                                        'rate_plan_id' => $rate_plan_id,
                                        'room_type_id' => $room_type_id,
                                        'date' => $date_from,
                                        'day' => $day,
                                        'block_status' => $block_status1,
                                        'hex_code' => $hex_code,
                                        'currency' => $currency
                                    );
                                }
                            } else {
                                $array = array(
                                    'bar_price' => $rate_plan_log_details['bar_price'],
                                    'multiple_occupancy' => json_decode($rate_plan_log_details['multiple_occupancy']),
                                    'bookingjini_price' => $bookingjini_price,
                                    'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                                    'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                                    'before_days_offer' => $before_days_offer,
                                    'stay_duration_offer' => $stay_duration_offer,
                                    'lastminute_offer' => $lastminute_offer,
                                    'rate_plan_id' => $rate_plan_id,
                                    'room_type_id' => $room_type_id,
                                    'date' => $date_from,
                                    'day' => $day,
                                    'block_status' => $block_status1,
                                    'hex_code' => $hex_code,
                                    'currency' => $currency
                                );
                            }
                        }
                    } else {
                        $array = array(
                            'bar_price' => $rate_plan_log_details['bar_price'],
                            'multiple_occupancy' => json_decode($rate_plan_log_details['multiple_occupancy']),
                            'bookingjini_price' => $bookingjini_price,
                            'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                            'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                            'before_days_offer' => $before_days_offer,
                            'stay_duration_offer' => $stay_duration_offer,
                            'lastminute_offer' => $lastminute_offer,
                            'rate_plan_id' => $rate_plan_id,
                            'room_type_id' => $room_type_id,
                            'date' => $date_from,
                            'day' => $day,
                            'block_status' => $block_status,
                            'hex_code' => $hex_code,
                            'currency' => $currency
                        );
                    }
                } else {
                    $array = array(
                        'bar_price' => $rate_plan_log_details['bar_price'],
                        'multiple_occupancy' => json_decode($rate_plan_log_details['multiple_occupancy']),
                        'bookingjini_price' => $bookingjini_price,
                        'extra_adult_price' => $rate_plan_log_details['extra_adult_price'],
                        'extra_child_price' => $rate_plan_log_details['extra_child_price'],
                        'before_days_offer' => $before_days_offer,
                        'stay_duration_offer' => $stay_duration_offer,
                        'lastminute_offer' => $lastminute_offer,
                        'rate_plan_id' => $rate_plan_id,
                        'room_type_id' => $room_type_id,
                        'date' => $date_from,
                        'day' => $day,
                        'block_status' => $block_status,
                        'hex_code' => $hex_code,
                        'currency' => $currency
                    );
                }
                array_push($filtered_rate, $array);
            }
            $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $filtered_rate;
    }
    public function getRatesByRoomnRatePlan(int $room_type_id, int $rate_plan_id, string $date_from, string $date_to)
    {
        $filtered_rate = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $rateplanlog = new RatePlanLog();
        $masterhotelrateplan = new MasterHotelRatePlan();
        $room_rate_plan_data = $masterhotelrateplan->where(['rate_plan_id' => $rate_plan_id])
            ->select('hotel_id')
            ->where(['room_type_id' => $room_type_id])
            ->first();
        $hotel_id = $room_rate_plan_data->hotel_id;
        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->first();
        $comp_info = CompanyDetails::where('company_id', $hotel_info->company_id)->first();
        $hex_code = $comp_info->hex_code;
        $currency = $comp_info->currency;
        for ($i = 1; $i <= $diff; $i++) {
            $d = $date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);

            $room_rate_plan_details = $masterhotelrateplan
                ->where(['rate_plan_id' => $rate_plan_id])
                ->where(['room_type_id' => $room_type_id])
                ->where('is_trash', 0)
                ->where('be_rate_status', 0)
                ->where('from_date', '<=', $d)
                ->where('to_date', '>=', $d)
                ->first();
            if (!isset($room_rate_plan_details->rate_plan_id)) //If Room rate plans Not with in date range then Latest created room rate plan is considered
            {
                $rate_plan_details = $masterhotelrateplan
                    ->where(['rate_plan_id' => $rate_plan_id])
                    ->where(['room_type_id' => $room_type_id])
                    ->where('is_trash', 0)
                    ->where('be_rate_status', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $rate_plan_details = $room_rate_plan_details;
            }

            //when rate is not updated in rate plan log table it's consider the rate from room rate plan table that is wrong so change it to 0;

            $bar_price = 0;
            $bookingjini_price = 0;
            $extra_adult_price = 0;
            $extra_child_price = 0;
            $multiple_occupancy = $rate_plan_details['multiple_occupancy'];
            $before_days_offer = $rate_plan_details['before_days_offer'];
            $stay_duration_offer = $rate_plan_details['stay_duration_offer'];
            $lastminute_offer = $rate_plan_details['lastminute_offer'];
            
            $rate_plan_log_details = $rateplanlog
                ->select('bar_price', 'multiple_occupancy', 'multiple_days', 'block_status', 'extra_adult_price', 'extra_child_price')
                ->where(['room_type_id' => $room_type_id])
                ->where('rate_plan_id', '=', $rate_plan_id)
                ->where('from_date', '<=', $d)
                ->where('to_date', '>=', $d)
                ->orderBy('rate_plan_log_id', 'desc')
                ->get();


            if ($rate_plan_log_details->isEmpty()) {
                $array = array(
                    'bar_price' => $bar_price,
                    'multiple_occupancy' => json_decode($multiple_occupancy),
                    'bookingjini_price' => $bookingjini_price,
                    'extra_adult_price' => $extra_adult_price,
                    'extra_child_price' => $extra_child_price,
                    'before_days_offer' => $before_days_offer,
                    'stay_duration_offer' => $stay_duration_offer,
                    'lastminute_offer' => $lastminute_offer,
                    'rate_plan_id' => $rate_plan_id,
                    'room_type_id' => $room_type_id,
                    'date' => $date_from,
                    'day' => $day,
                    'hex_code' => $hex_code,
                    'block_status' => 0,
                    'currency' => $currency

                );
                array_push($filtered_rate, $array);
            } else {
                foreach ($rate_plan_log_details as $rate_plan_log_detail) {
                    $multiple_days = json_decode($rate_plan_log_detail->multiple_days);
                    $block_status     = $rate_plan_log_detail['block_status'];
                    if ($multiple_days != null) {
                        if ($multiple_days->$day == 0) {
                            continue;
                        } else {
                            $array = array(
                                'bar_price' => $rate_plan_log_detail['bar_price'],
                                'multiple_occupancy' => json_decode($rate_plan_log_detail['multiple_occupancy']),
                                'bookingjini_price' => $bookingjini_price,
                                'extra_adult_price' => $rate_plan_log_detail['extra_adult_price'],
                                'extra_child_price' => $rate_plan_log_detail['extra_child_price'],
                                'before_days_offer' => $before_days_offer,
                                'stay_duration_offer' => $stay_duration_offer,
                                'lastminute_offer' => $lastminute_offer,
                                'rate_plan_id' => $rate_plan_id,
                                'room_type_id' => $room_type_id,
                                'date' => $date_from,
                                'day' => $day,
                                'block_status' => $block_status,
                                'hex_code' => $hex_code,
                                'currency' => $currency
                            );
                            break;
                        }
                    } else {
                        $array = array(
                            'bar_price' => $rate_plan_log_detail['bar_price'],
                            'multiple_occupancy' => json_decode($rate_plan_log_detail['multiple_occupancy']),
                            'bookingjini_price' => $bookingjini_price,
                            'extra_adult_price' => $rate_plan_log_detail['extra_adult_price'],
                            'extra_child_price' => $rate_plan_log_detail['extra_child_price'],
                            'before_days_offer' => $before_days_offer,
                            'stay_duration_offer' => $stay_duration_offer,
                            'lastminute_offer' => $lastminute_offer,
                            'rate_plan_id' => $rate_plan_id,
                            'room_type_id' => $room_type_id,
                            'date' => $date_from,
                            'day' => $day,
                            'block_status' => $block_status,
                            'hex_code' => $hex_code,
                            'currency' => $currency
                        );
                    }
                }
                if(!empty($array)){
                    array_push($filtered_rate, $array);
                }
                
            }
            $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $filtered_rate;
    }

    public function getBookingByRoomtype($hotel_id, $room_type_id, $date_from, $date_to)
    {
        $filtered_booking = array();
        $booking = new Booking();
        $date1 = date_create($date_to);
        $date2 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        for ($i = 1; $i <= $diff; $i++) {
            $d = $date_from;
            $booking_details = $booking
                ->where('room_type_id', '=', $room_type_id)
                ->where('check_in', '=', $d)
                ->where('check_out', '>', $d)
                ->where('booking_status', '=', 1)
                ->sum('rooms');
            if ($booking_details) {
                $array = array(
                    'booking' => $booking_details,
                    'date' => $d
                );
            } else {
                $array = array(
                    'booking' => 0,
                    'date' => $d
                );
            }
            array_push($filtered_booking, $array);
            $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $filtered_booking;
    }
    //GET Discount amount
    //@Rajendra
    public function fetchLiveDiscountRate(int $hotel_id, string $date_from)
    {

        $room_types =  MasterRoomType::select('room_type_id')->where('hotel_id', $hotel_id)->get();

        if (!$room_types) {
            return response()->json(['status' => 0, 'message' => 'Room type is not found']);
        }

        $date_from  = date('Y-m-d', strtotime($date_from));
        $date_to    = date('Y-m-d', strtotime($date_from . ' +1 day'));
        $rates_plan_price_data  = array();
        if ($room_types) {
            foreach ($room_types as $room_type) {
                $master_hotel_rate_plan = MasterHotelRatePlan::select('rate_plan_id')->where('hotel_id', $hotel_id)->where('room_type_id', $room_type->room_type_id)->get();
                $rates_plan_data = array();
                if (sizeof($master_hotel_rate_plan) > 0) {
                    foreach ($master_hotel_rate_plan as $rate_plan) {
                        if (isset($rate_plan->rate_plan_id)) {
                            $rate_plans_data = '';
                            $rate_plans_data = $this->getRatesByRoomnRatePlan($room_type->room_type_id, $rate_plan->rate_plan_id, $date_from, $date_to);
                            if (isset($rate_plans_data)) {
                                if (isset($rate_plans_data[0]['bar_price'])) {
                                    if ($rate_plans_data[0]['bar_price'] > 0) {
                                        $rates_plan_data[$rate_plan->rate_plan_id] = $rate_plans_data[0]['bar_price'];
                                    }
                                }
                            }
                        }
                    }
                } else {
                    continue;
                }

                $discount_data = Coupons::where('room_type_id', $room_type->room_type_id)
                    ->where('coupon_for', 1)
                    ->where('hotel_id', $hotel_id)
                    ->whereRaw('"' . $date_from . '" between `valid_from` and `valid_to`')
                    ->orderBy('coupon_id', 'DESC')
                    ->first();
                if ($discount_data) {
                    $discount = isset($discount_data->discount) ? $discount_data->discount : 0;
                } else {
                    $discount_data = Coupons::where('room_type_id', 0)
                        ->where('coupon_for', 1)
                        ->where('hotel_id', $hotel_id)
                        ->whereRaw('"' . $date_from . '" between `valid_from` and `valid_to`')
                        ->where('is_trash', 0)
                        ->orderBy('coupon_id', 'DESC')
                        ->first();
                    $discount = isset($discount_data->discount) ? $discount_data->discount : 0;
                }
                $rates_plan_data = array_unique($rates_plan_data);
                sort($rates_plan_data, SORT_NUMERIC);
                $smallest_price = array_shift($rates_plan_data);

                if ($smallest_price != 0) {
                    $min_price = $smallest_price;
                } else {
                    if (count($rates_plan_data) > 1) {
                        $min_price = array_shift($rates_plan_data);
                    } else {
                        $min_price = 0;
                    }
                }
                if (($discount > 0) && ($min_price > 0)) {
                    $discount_price = round($min_price - (($discount * $min_price) / 100));
                } else {
                    $discount_price = round($min_price);
                }
                $discount_data = array(
                    'room_price'    => $discount_price,
                    'room_type_id'  => $room_type->room_type_id,
                    'hotel_id'      => $hotel_id
                );
                array_push($rates_plan_price_data, $discount_data);
            }
        }
        return response()->json(['status' => 1, 'discount_data' => $rates_plan_price_data]);
    }
}
