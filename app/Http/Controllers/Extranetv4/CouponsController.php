<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Coupons; //class name from model
use App\UsedCoupon;
use App\MasterRoomType;
use App\MetaSearchEngineSetting;
use App\MasterHotelRatePlan;
use App\Invoice;
use App\HotelInformation;
use DB;
use App\Http\Controllers\Controller;

class CouponsController extends Controller
{
        private $rules = array(
                'coupon_name' => 'required ',
                'coupon_for' => 'required ',
                'valid_from' => 'required ',
                'valid_to' => 'required ',
                'discount' => 'required ',
                'hotel_id' => 'required'
        );
        private $getCouponRules = array(
                'hotel_id' => 'required ',
                'checkin_date' => 'required',
                'checkout_date' => 'required',
                'room_type_id' => 'required '
        );
        private $checkCouponRules = array(
                'hotel_id' => 'required',
                'checkin_date' => 'required',
                'checkout_date' => 'required',
                'code' => 'required'
        );
        //Custom Error Messages
        private $messages = [
                'coupon_name.required' => 'The coupon name field is required.',
                'coupon_for.required' => 'The coupon for field is required.',
                'valid_from.required' => 'The valid from field is required.',
                'valid_to.required' => 'The valid to field is required.',
                'discount.required' => 'The discount field is required.',
                'hotel_id.required' => 'Hotel id is required',
                'avail_date.required' => 'Coupon date is required',
                'room_type_id.required' => 'Room type id is required',
                'code.required' => 'Coupon code is required',
                'hotel_id.required' => 'Hotel id is required'
        ];

        /**
         * coupons Details
         * Create a new record of coupons details.
         * @auther subhradip
         * @return coupons details saving status
         *  function addNewCoupons use for cerating new coupons
         * Modified by Saroj Patel
         **/
        //validation rules
        public function addNewCoupons(Request $request)
        {
                $coupons = new Coupons();
                $failure_message = FAILED_COUPONS_MESSAGE;
                $validator = Validator::make($request->all(), $this->rules, $this->messages);
                if ($validator->fails()) {
                        return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
                }
                $data = $request->all();

                //TO get user id from AUTH token
                $user_id = "";
                if (isset($request->auth->admin_id)) {
                        $data['user_id'] = $request->auth->admin_id;
                } else if (isset($request->auth->intranet_id)) {
                        $data['user_id'] = $request->auth->intranet_id;
                } else if (isset($request->auth->id)) {
                        $data['user_id'] = $request->auth->id;
                }
                $week_days = ["Mon", "Tue", "Wed", "Thu", "Fri"];
                $week_end = ["Sat", "Sun"];
                $blackout_types = $data['blackout_type'];
                $blackoutdates = $data['blackoutdates'];
                $blackoutdays = $data['blackoutdates'];
                $data['coupon_code'] = $data['coupon_code'];
                $data['valid_from'] = date('Y-m-d', strtotime($data['valid_from']));
                $data['valid_to'] = date('Y-m-d', strtotime($data['valid_to']));

                $coupon_type = $data['coupon_for'];
                $blckout_array = [];

                if ($coupon_type == 'Private') {
                        $data['coupon_for'] = 2;
                } else {
                        $data['coupon_for'] = 1;
                }
                $valid_from = date('Y-m-d', strtotime($data['valid_from']));
                $valid_to = date('Y-m-d', strtotime($data['valid_to']));
                $date1 = date_create($valid_from);
                $date2 = date_create($valid_to);
                $diff = date_diff($date1, $date2);
                $diff = $diff->format("%d");
                $index = $valid_from;
                if ($blackout_types == 1) {
                        for ($i = 0; $i < $diff; $i++) {
                                $timestamp = strtotime($index);
                                $day = date('D', $timestamp);
                                if (in_array($day, $week_days)) {
                                        $blckout_array[] = $index;
                                }
                                $index = date('Y-m-d', strtotime($index . '+1 days'));
                        }
                        foreach ($blackoutdates as $blackoutdate) {
                                $blckout_array[] = $blackoutdate;
                        }
                        $unique_blackout_dates = array_unique($blckout_array);
                } else if ($blackout_types == 2) {
                        for ($i = 0; $i < $diff; $i++) {
                                $timestamp = strtotime($index);
                                $day = date('D', $timestamp);
                                if (in_array($day, $week_end)) {
                                        $blckout_array[] = $index;
                                }
                                $index = date('Y-m-d', strtotime($index . '+1 days'));
                        }
                        foreach ($blackoutdates as $blackoutdate) {
                                $blckout_array[] = $blackoutdate;
                        }
                        $unique_blackout_dates = array_unique($blckout_array);
                } else if ($blackout_types == 3) {
                        foreach ($blackoutdates as $blackoutdate) {
                                $blckout_array[] = $blackoutdate;
                        }
                        $unique_blackout_dates = array_unique($blckout_array);
                } else {
                        $unique_blackout_dates = $blckout_array;
                }
                $blackout_dates = implode(',', $unique_blackout_dates);
                $blackoutdays = implode(',', $blackoutdays);

                if(isset($data['private_coupon_restriction'])){
                        $data['private_coupon_restriction'] = $data['private_coupon_restriction'];
                }else{
                        $data['private_coupon_restriction'] = 0;
                }

                if (isset($data['hotel_id'])) {
                        $hotels = $data['hotel_id'];
                        if (is_array($hotels)) {
                                $count = 0;
                                if (sizeof($hotels) > 0) {
                                        foreach ($hotels as $hotel) {
                                                $data['hotel_id'] = $hotel;
                                                $coupon_array = [
                                                        'company_id' => $data['company_id'],
                                                        'hotel_id' => $data['hotel_id'],
                                                        'room_type_id' => $data['room_type_id'],
                                                        'user_id' => $data['user_id'],
                                                        'coupon_name' => $data['coupon_name'],
                                                        'coupon_code' => $data['coupon_code'],
                                                        'coupon_for' => $data['coupon_for'],
                                                        'valid_from' => $data['valid_from'],
                                                        'valid_to' => $data['valid_to'],
                                                        'blackoutdates' =>  $blackout_dates,
                                                        'blackout_type' =>  $data['blackout_type'],
                                                        'blackoutdays' => $blackoutdays,
                                                        'discount_type' => 0,
                                                        'discount' => $data['discount'],
                                                        'client_ip' => 0,
                                                        'private_coupon_restriction'=>$data['private_coupon_restriction'],
                                                ];
                                                
                                                $coupon_id[] = $coupons::insertGetId($coupon_array);
                                                if ($this->googleHotelStatus($data['hotel_id']) && $data['coupon_for'] == 1) {
                                                        try {
                                                                $push_promotion_google_hotel_ads = $this->googleHotelAdsPromotion($data, $coupons->coupon_id);
                                                                
                                                        } catch (Exception $e) {
                                                        }
                                                }
                                                if ($coupon_id) {
                                                        $count += 1;
                                                } else {
                                                        Coupons::whereIn('coupon_id', $coupon_id)->delete();

                                                        $res = array('status' => 0, "message" => $failure_message);
                                                        return response()->json($res);
                                                }
                                        }
                                        if (sizeof($hotels) == $count) {
                                                $res = array('status' => 1, "message" => SAVED_COUPONS_MESSAGE);
                                                return response()->json($res);
                                        } else {
                                                $res = array('status' => 0, "message" => $failure_message);
                                                return response()->json($res);
                                        }
                                } else {
                                        $res = array('status' => 0, "message" => PROVIDE_HOTELINFO_COUPONS_MESSAGE);
                                        return response()->json($res);
                                }
                        } else {
                                $coupon_array = [
                                        'company_id' => $data['company_id'],
                                        'hotel_id' => $data['hotel_id'],
                                        'room_type_id' => $data['room_type_id'],
                                        'user_id' => $data['user_id'],
                                        'coupon_name' => $data['coupon_name'],
                                        'coupon_code' => $data['coupon_code'],
                                        'coupon_for' => $data['coupon_for'],
                                        'valid_from' => $data['valid_from'],
                                        'valid_to' => $data['valid_to'],
                                        'blackoutdates' =>  $blackout_dates,
                                        'blackout_type' =>  $data['blackout_type'],
                                        'blackoutdays' => $blackoutdays,
                                        'discount_type' => 0,
                                        'discount' => $data['discount'],
                                        'client_ip' => 0,
                                        'private_coupon_restriction'=>$data['private_coupon_restriction'],
                                ];
                                $insert_coupon = coupons::insertGetId($coupon_array);

                                //This code is use to tracking hotelier Activity
                                $activity_name = "";
                                if ($data['coupon_for'] == 1) {
                                        $activity_id = "a5";
                                        $activity_description = "Added new public coupon '".$data['coupon_code']."' valid from '". $data['valid_from']."' valid to '".$data['valid_to']."' for '".$data['room_type_id']."' at '".$data['discount']."'";
                                } else {
                                        $activity_id = "a8";
                                        $activity_description = "Added new private coupon '".$data['coupon_code']."' valid from '". $data['valid_from']."' valid to '".$data['valid_to']."' for '".$data['room_type_id']."' at '".$data['discount']."'";
                                }
                                $activity_from = "BE";
                                captureHotelActivityLog($data['hotel_id'], $data['user_id'], $activity_id, $activity_name, $activity_description, $activity_from);
                               
                                if ($this->googleHotelStatus($data['hotel_id']) && $data['coupon_for'] == 1) {
                                        try {
                                                $push_promotion_google_hotel_ads = $this->googleHotelAdsPromotion($data, $insert_coupon);

                                                //This code is use to tracking hotelier Activity
                                                $activity_name = "";
                                                $activity_id = "a21";
                                                $activity_description = "Added GHC coupon '".$data['coupon_code']."' valid from '". $data['valid_from']."' valid to '".$data['valid_to']."' for '".$data['room_type_id']."' at '".$data['discount']."'";
                                                $activity_from = "BE";
                                                captureHotelActivityLog($data['hotel_id'], $data['user_id'], $activity_id, $activity_name, $activity_description, $activity_from);

                                        } catch (Exception $e) {
                                        }
                                }
                                if ($insert_coupon) {
                                        $res = array('status' => 1, "message" => SAVED_COUPONS_MESSAGE);
                                        return response()->json($res);
                                } else {
                                        $res = array('status' => 0, "message" => $failure_message);
                                        return response()->json($res);
                                }
                        }
                } else {
                        $res = array('status' => 0, "message" => PROVIDE_HOTELINFO_COUPONS_MESSAGE);
                        return response()->json($res);
                }
        }
        /**
         * Delete coupons
         * delete record of coupons
         * @author subhradip
         * @return coupons deleting status
         * function DeleteCoupons used for delete
         **/
        public function DeleteCoupons(int $coupon_id, Request $request)
        {
                if (Coupons::where('coupon_id', $coupon_id)->update(['is_trash' => 1])) {
                        
                        try {
                                $get_coupon_details = Coupons::where('coupon_id', $coupon_id)->first();

                                //This code is use to tracking hotelier Activity
                                $activity_name = "";
                                if ($get_coupon_details->coupon_for == 1) {
                                        $activity_id = "a7";
                                        $activity_description = "Delete public coupon id '".$coupon_id."'";
                                } else {
                                        $activity_id = "a10";
                                        $activity_description = "Delete private coupon id '".$coupon_id."'";
                                }
                                $activity_from = "BE";
                                $user_id = 0;
                                captureHotelActivityLog($get_coupon_details->hotel_id, $user_id, $activity_id, $activity_name, $activity_description, $activity_from);

                                if ($this->googleHotelStatus($get_coupon_details->hotel_id) && $get_coupon_details->coupon_for == 1) {

                                        //This code is use to tracking hotelier Activity
                                        $activity_name = "";
                                        $activity_id = "a23";
                                        $activity_description = "Delete GHC coupon id '".$coupon_id."'";
                                        $activity_from = "BE";
                                        captureHotelActivityLog($get_coupon_details->hotel_id, $user_id, $activity_id, $activity_name, $activity_description, $activity_from);

                                        $id = uniqid();
                                        $time = time();
                                        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';

                                        $removal_promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                                                        <Promotions partner="bookingjini_ari"
                                                                id="' . $id . '"
                                                                timestamp="' . $time . '">
                                                        <HotelPromotions hotel_id="' . $get_coupon_details->hotel_id . '">
                                                        <Promotion id="' . $coupon_id . '" action="delete"/>
                                                        </HotelPromotions>
                                                        </Promotions>';


                                        $headers = array(
                                                "Content-Type: application/xml",
                                        );
                                        $url =  'https://www.google.com/travel/hotels/uploads/promotions';
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_POST, true);
                                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, $removal_promotion_xml);
                                        $google_resp = curl_exec($ch);
                                        curl_close($ch);
                                }
                        } catch (Exception $e) {
                        }
                        $res = array('status' => 1, "message" => 'Coupons Deleted successfully');
                        return response()->json($res);
                } else {
                        $res = array('status' => -1, "message" => $failure_message);
                        $res['errors'][] = "Internal server error";
                        return response()->json($res);
                }
        }

        /**
         * coupons
         * Update record ofcoupons
         * @author subhradip
         * @return coupons  saving status
         * function Updatecoupons use for update
         **/
        public function Updatecoupons(int $coupon_id, Request $request)
        {
                $coupons = new Coupons();
                $failure_message = FAILED_UPDATE_COUPONS_MESSAGE;
                $validator = Validator::make($request->all(), $this->rules, $this->messages);
                if ($validator->fails()) {
                        return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
                }
                $data = $request->all();
                $data['valid_from'] = date('Y-m-d', strtotime($data['valid_from']));
                $data['valid_to'] = date('Y-m-d', strtotime($data['valid_to']));
                $coupon_type = $data['coupon_for'];
                if (isset($request->auth->admin_id)) {
                        $data['user_id'] = $request->auth->admin_id;
                } else if (isset($request->auth->intranet_id)) {
                        $data['user_id'] = $request->auth->intranet_id;
                } else if (isset($request->auth->id)) {
                        $data['user_id'] = $request->auth->id;
                }

                if ($coupon_type == 'Private') {
                        $data['coupon_for'] = 2;
                } else {
                        $data['coupon_for'] = 1;
                }
                $week_days = ["Mon", "Tue", "Wed", "Thu", "Fri"];
                $week_end = ["Sat", "Sun"];
                $blckout_array = [];
                $blackout_types = $data['blackout_type'];
                $blackoutdates = $data['blackoutdates'];
                $blackoutdays = $data['blackoutdates'];
                $valid_from = date('Y-m-d', strtotime($data['valid_from']));
                $valid_to = date('Y-m-d', strtotime($data['valid_to']));
                $date1 = date_create($valid_from);
                $date2 = date_create($valid_to);
                $diff = date_diff($date1, $date2);
                $diff = $diff->format("%d");
                $index = $valid_from;

                if(isset($data['private_coupon_restriction'])){
                        $data['private_coupon_restriction'] = $data['private_coupon_restriction'];
                }else{
                        $data['private_coupon_restriction'] = 0;
                }

                if ($blackout_types == 1) {
                        for ($i = 0; $i < $diff; $i++) {
                                $timestamp = strtotime($index);
                                $day = date('D', $timestamp);
                                if (in_array($day, $week_days)) {
                                        $blckout_array[] = $index;
                                }
                                $index = date('Y-m-d', strtotime($index . '+1 days'));
                        }
                        foreach ($blackoutdates as $blackoutdate) {
                                array_push($blckout_array, $blackoutdate);
                        }
                        $unique_blackout_dates = array_values(array_unique($blckout_array));
                } else if ($blackout_types == 2) {
                        for ($i = 0; $i < $diff; $i++) {
                                $timestamp = strtotime($index);
                                $day = date('D', $timestamp);
                                if (in_array($day, $week_end)) {
                                        $blckout_array[] = $index;
                                }
                                $index = date('Y-m-d', strtotime($index . '+1 days'));
                        }

                        foreach ($blackoutdates as $blackoutdate) {
                                array_push($blckout_array, $blackoutdate);
                        }

                        $unique_blackout_dates = array_values(array_unique($blckout_array));
                } else if ($blackout_types == 3) {
                        foreach ($blackoutdates as $blackoutdate) {
                                array_push($blckout_array, $blackoutdate);
                        }
                        $unique_blackout_dates = array_values(array_unique($blckout_array));
                } else {
                        $unique_blackout_dates = $blckout_array;
                }
                $blackout_dates = implode(',', $unique_blackout_dates);
                $blackoutdays = implode(',', $blackoutdays);
                $coupons = Coupons::where('coupon_id', $coupon_id)->first();
                if ($coupons->coupon_id == $coupon_id) {
                        $coupon_array = [
                                'company_id' => $data['company_id'],
                                'hotel_id' => $data['hotel_id'],
                                'room_type_id' => $data['room_type_id'],
                                'user_id' => $data['user_id'],
                                'coupon_name' => $data['coupon_name'],
                                'coupon_code' => $data['coupon_code'],
                                'coupon_for' => $data['coupon_for'],
                                'valid_from' => $data['valid_from'],
                                'valid_to' => $data['valid_to'],
                                'blackoutdates' =>  $blackout_dates,
                                'blackout_type' =>  $data['blackout_type'],
                                'blackoutdays' => $blackoutdays,
                                'discount_type' => 0,
                                'discount' => $data['discount'],
                                'private_coupon_restriction'=>$data['private_coupon_restriction'],
                        ];
                        $update_coupon = coupons::where('coupon_id', $coupon_id)->update($coupon_array);

                         //This code is use to tracking hotelier Activity
                         $activity_name = "";
                         if ($data['coupon_for'] == 1) {
                                 $activity_id = "a6";
                                 $activity_description = "Modified public coupon '".$data['coupon_code']."' valid from '". $data['valid_from']."' valid to '".$data['valid_to']."' for '".$data['room_type_id']."' at '".$data['discount']."'";
                         } else {
                                 $activity_id = "a9";
                                 $activity_description = "Modified private coupon '".$data['coupon_code']."' valid from '". $data['valid_from']."' valid to '".$data['valid_to']."' for '".$data['room_type_id']."' at '".$data['discount']."'";
                         }
                         $activity_from = "BE";
                         captureHotelActivityLog($data['hotel_id'], $data['user_id'], $activity_id, $activity_name, $activity_description, $activity_from);


                        if ($update_coupon) {
                                if ($this->googleHotelStatus($data['hotel_id']) && $data['coupon_for'] == 1) {
                                        try {
                                                $push_promotion_google_hotel_ads = $this->googleHotelAdsPromotion($data, $coupon_id);

                                                 //This code is use to tracking hotelier Activity
                                                 $activity_name = "";
                                                 $activity_id = "a22";
                                                 $activity_description = "Update GHC coupon '".$data['coupon_code']."' valid from '". $data['valid_from']."' valid to '".$data['valid_to']."' for '".$data['room_type_id']."' at '".$data['discount']."'";
                                                 $activity_from = "BE";
                                                 captureHotelActivityLog($data['hotel_id'], $data['user_id'], $activity_id, $activity_name, $activity_description, $activity_from);

                                        } catch (Exception $e) {
                                        }
                                }
                                $res = array('status' => 1, "message" => UPDATE_COUPONS_MESSAGE);
                                return response()->json($res);
                        } else {
                                $res = array('status' => -1, "message" => $failure_message);
                                $res['errors'][] = INTERNAL_SERVER_ERR_MESSAGE;
                                return response()->json($res);
                        }
                }
        }
        /**
         * Get  coupons
         * get one record of  Macoupons
         * @author subhradip
         * function GetCoupons for delecting data
         **/
        public function GetCoupons(int $coupon_id, Request $request)
        {
                $coupons = new Coupons();
                if ($coupon_id) {
                        $conditions = array('coupon_id' => $coupon_id, 'is_trash' => 0);
                        $res = Coupons::where($conditions)->first();
                        if (!empty($res)) {
                                $res = array('status' => 1, "message" => RETRIEVED_COUPONS_MESSAGE, 'data' => $res);
                                return response()->json($res);
                        } else {
                                $res = array('status' => 0, "message" => FAILED_FETCHING_COUPONS_MESSAGE);
                                return response()->json($res);
                        }
                } else {
                        $res = array('status' => -1, "message" => FAILED_FETCHING_COUPONS_MESSAGE);
                        return response()->json($res);
                }
        }
        /**
         * Get all coupons
         * get All record of coupons
         * @author subhradip
         * function GetAllMasterHotelRateplan for selecting all data
         **/
        public function GetAllCoupons(Request $request)
        {
                $coupons = new Coupons();
                $conditions = array('is_trash' => 0);
                $res = Coupons::where($conditions)->get();
                if (sizeof($res) > 0) {
                        $res = array('status' => 1, 'message' => "records found", 'data' => $res);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, "message" => NO_COUPONS_MESSAGE);
                        return response()->json($res);
                }
        }
        /**
         * Get all coupons by hotel_id
         * get All record of coupons
         * @author subhradip
         * function GetAllMasterHotelRateplan for selecting all data
         **/
        public function GetCouponsByHotel(int $hotel_id, Request $request)
        {
                $coupons = new Coupons();
                $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
                $res = Coupons::where($conditions)->get();
                foreach ($res as $data) {
                        if ($data->room_type_id == 0) {
                                $data['room_type'] = "All";
                                if ($data->coupon_for == '1') {
                                        $data->coupon_for = 'public';
                                } else {
                                        $data->coupon_for = 'private';
                                }
                        } else {
                                $room_types = MasterRoomType::select('room_type')->where('room_type_id', $data->room_type_id)->first();
                                $data['room_type'] = $room_types['room_type'];
                                if ($data->coupon_for == '1') {
                                        $data->coupon_for = 'public';
                                } else {
                                        $data->coupon_for = 'private';
                                }
                        }
                        $data->valid_from = date('d-m-Y', strtotime($data->valid_from));
                        $data->valid_to = date('d-m-Y', strtotime($data->valid_to));
                        $data->valid_from_hrf = date('d-M-Y', strtotime($data->valid_from));
                        $data->valid_to_hrf = date('d-M-Y', strtotime($data->valid_to));
                }
                if (sizeof($res) > 0) {
                        $res = array('status' => 1, 'message' => "records found", 'data' => $res);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, "message" => NO_COUPONS_MESSAGE);
                        return response()->json($res);
                }
        }
        public function GetCouponsPublic(Request $request)
        {
                $failure_message = "No Coupons found";
                $validator = Validator::make($request->all(), $this->getCouponRules, $this->messages);
                if ($validator->fails()) {
                        return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
                }
                $data = $request->all();
                $hotel_id = $data['hotel_id'];
                $length = sizeof($data['room_type_id']);
                $room_type_id = $data['room_type_id'];
                $room_type_id[$length] = 0; //Push all room types id(coupon applicable for all rooms is 0) to room_type_id array
                $checkin_date = $data['checkin_date'];
                $checkout_date = $data['checkout_date'];
                $checkin_date = date('Y-m-d', strtotime($checkin_date));
                $checkout_date = date('Y-m-d', strtotime($checkout_date));
                $coupons = new Coupons();
                /*$user_id=$request->auth->user_id;
        $conditions=array('user_id'=>$user_id);
        $used_coupons=UsedCoupon::where($conditions)->get();*/
                $coupons = Coupons::leftJoin('bookingjini_kernel.room_type_table as room_type_table', 'coupons.room_type_id', 'room_type_table.room_type_id')
                        ->where('coupons.hotel_id', $hotel_id)
                        ->whereIn('coupons.room_type_id', $room_type_id)
                        ->where('coupons.valid_from', '<=', $checkin_date)
                        ->where('coupons.valid_to', '>=', $checkin_date)
                        ->where('coupons.coupon_for', 1) ///2 means private coupon
                        ->where('coupons.is_trash', 0)
                        ->select('coupons.*', 'room_type_table.room_type as room_type')->get();
                if (sizeof($coupons) > 0) {
                        $res = array('status' => 1, 'message' => "Coupons retrieved successfully", 'data' => $coupons);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, "message" => "No Coupons found");
                        return response()->json($res);
                }
        }
        public function checkCouponCode(Request $request)
        {

                // $failure_message = "No Coupons found";
                // $validator = Validator::make($request->all(), $this->checkCouponRules, $this->messages);
                // if ($validator->fails()) {
                //         return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
                // }
                $data = $request->all();
                $hotel_id = $data['hotel_id'];
                $checkin_date = date('Y-m-d', strtotime($data['checkin_date']));
                $checkout_date = date('Y-m-d', strtotime($data['checkout_date']));
                $code = $data['code'];
                $coupons = Coupons::where('hotel_id', $hotel_id)
                        ->where('valid_from', '<=', $checkin_date)
                        ->where('valid_to', '>=', $checkin_date)
                        ->where('coupon_for', 2) ///2 means private coupon
                        ->where('coupon_code', $code)
                        ->where('is_trash', 0)
                        ->select('*')->first();

                if ($coupons) {
                        $private_coupon_status = $coupons['restriction_status'];
                        if ($private_coupon_status == 0) {
                                $res = array('status' => 0, 'message' => "Invalid Coupon");
                                return response()->json($res);
                        }
                }
                //$user_id=$request->auth->user_id;
                /*$conditions=array('user_id'=>$user_id,'code'=>$code); 
        $used_coupons=UsedCoupon::where($conditions)->first();*/
                $used_coupons = 0;
                if ($coupons && $used_coupons == 0) {
                        $res = array('status' => 1, 'message' => "Coupon is valid", 'data' => $coupons);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, 'message' => "Coupon you have applied ,Already expired");
                        return response()->json($res);
                }
        }


        public function checkCouponCodeNew(Request $request)
        {
                $data = $request->all();
                $hotel_id = $data['hotel_id'];
                $begin = strtotime($data['checkin_date']);
                $end = strtotime($data['checkout_date']);
                $code = $data['code'];
                $from_date = date('Y-m-d', strtotime($data['checkin_date']));
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
            valid_to,discount_type,coupon_for,discount,blackoutdates,blackoutdays,created_date_time,updated_date_time,created_at,updated_at,private_coupon_restriction,a.date,a.abc
            FROM
            (
            select t2.coupon_id,
            case when t2.room_type_id!=0 then t2.room_type_id else 0 end as room_type_id,
            coupon_name,coupon_code,valid_from,valid_to,discount_type,coupon_for,discount,blackoutdates,blackoutdays,created_date_time,updated_date_time,created_at,updated_at,private_coupon_restriction,"' . $Store . '" as date,
            case when "' . $Store . '" between valid_from and valid_to then "yes" else "no" end as abc
            from booking_engine.coupons
            INNER JOIN
            (
            SELECT room_type_id,
            substring_index(group_concat(cast(coupon_id as CHAR) order by discount desc), ",", 1 ) as coupon_id,MAX(discount)
            FROM booking_engine.coupons where hotel_id = "' . $hotel_id . '" and coupon_for = 2 and is_trash = 0
            and ("' . $Store . '" between valid_from and valid_to) and (NOT FIND_IN_SET("' . $Store . '",blackoutdates) OR blackoutdates IS NULL) and (NOT FIND_IN_SET("' . $convert_store_date . '",blackoutdays) OR blackoutdays IS NULL) and coupon_code = "' . $code . '"
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
                                $data_present['blackoutdates']    = $data->blackoutdates;
                                $data_present['blackoutdays']         = $data->blackoutdays;
                                $data_present['private_coupon_restriction']         = $data->private_coupon_restriction;
                                $data_present['created_date_time']        = $data->created_date_time;
                                $data_present['updated_date_time']        = $data->updated_date_time;
                                $data_present['created_at']        = $data->created_at;
                                $data_present['updated_at']        = $data->updated_at;

                                // if(empty($check_blackout_dates)){
                                if ($data->valid_from <= $from_date && $data->valid_to >= $from_date) {
                                        $data_array_present[] = $data_present;
                                }
                                // }
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
                        $res = array('status' => 1, 'message' => "Private coupon retrieved successfully", 'data' => $data_array);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, 'message' => "!Sorry Private coupon is not available", 'data' => array());
                        return response()->json($res);
                }
        }

        public function couponsType()
        {

                $coupon_type = ['1' => 'Public', '2' => 'Private'];

                if ($coupon_type) {
                        $res = array('status' => 1, 'message' => RETRIEVE_COUPONS_TYPE_MESSAGE, 'data' => $coupon_type);
                        return response()->json($res);
                } else {
                        $res = array('status' => 0, 'message' => FAILED_RETRIEVE_COUPONS_TYPE_MESSAGE);
                        return response()->json($res);
                }
        }
        public function googleHotelAdsPromotion($data, $coupon_id)
        {
                $id = uniqid();
                $time = time();
                $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
                $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
                <Promotions partner="bookingjini_ari"
                            id="' . $id . '"
                            timestamp="' . $time . '">
                  <HotelPromotions hotel_id="' . $data['hotel_id'] . '">
                    <Promotion id="' . $coupon_id . '">
              
                      <CheckinDates>
                         <DateRange start="' . $data['valid_from'] . '" end="' . $data['valid_to'] . '" days_of_week="MTWHFSU"/>
                      </CheckinDates>
              
                      <Devices>
                        <Device type="mobile"/>
                        <Device type="tablet"/>
                        <Device type="desktop"/>
                      </Devices>
                      <Discount percentage="' . $data['discount'] . '" applied_nights="1"/>
                      <LengthOfStay min="1" max="14"/>';
                $promotion_rate_xml = '';
                $promotion_room_xml = '';
                $rate_plan_array = array();
                if ($data['room_type_id'] == 0) {
                        $get_room_types = MasterRoomType::select('room_type_id')->where('hotel_id', $data['hotel_id'])->get();
                        $promotion_room_xml .= '<RoomTypes>';
                        foreach ($get_room_types as $room_info) {
                                $get_rate_plans = MasterHotelRatePlan::select('rate_plan_id')->where('hotel_id', $data['hotel_id'])->where('room_type_id', $room_info->room_type_id)->where('be_rate_status','0')->where('is_trash','0')->get();
                                $promotion_rate_xml .= '<RatePlans>';
                                foreach ($get_rate_plans as $rate_info) {
                                        if (in_array($rate_info->rate_plan_id, $rate_plan_array)) {
                                                continue;
                                        } else {
                                                $rate_plan_array[] = $rate_info->rate_plan_id;
                                                $promotion_rate_xml .= '<RatePlan id="' . $rate_info->rate_plan_id . '"/>';
                                        }
                                }
                                $promotion_rate_xml .= '</RatePlans>';
                                $promotion_room_xml .= '<RoomType id="' . $room_info->room_type_id . '"/>';
                        }
                        $promotion_room_xml .= '</RoomTypes>';
                } else {
                        $get_rate_plans = MasterHotelRatePlan::select('rate_plan_id')->where('hotel_id', $data['hotel_id'])->where('room_type_id', $data['room_type_id'])->where('be_rate_status','0')->where('is_trash','0')->get();
                        $promotion_rate_xml .= '<RatePlans>';
                        foreach ($get_rate_plans as $rate_info) {
                                // $promotion_rate_xml .= '<RatePlan id="' . $rate_info->rate_plan_id . '"/>';
                                if (in_array($rate_info->rate_plan_id, $rate_plan_array)) {
                                        continue;
                                } else {
                                        $rate_plan_array[] = $rate_info->rate_plan_id;
                                        $promotion_rate_xml .= '<RatePlan id="' . $rate_info->rate_plan_id . '"/>';
                                }
                        }
                        $promotion_rate_xml .= '</RatePlans>';
                        $promotion_room_xml = '<RoomTypes><RoomType id="' . $data['room_type_id'] . '"/></RoomTypes>';
                }
                $promotion_xml .= $promotion_rate_xml;
                $promotion_xml .= $promotion_room_xml;
                
                $promotion_xml .= '<Stacking type="base"/>
                     
                    </Promotion>
                  </HotelPromotions>
                </Promotions>';

                $headers = array(
                        "Content-Type: application/xml",
                );
                $url =  'https://www.google.com/travel/hotels/uploads/promotions';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $promotion_xml);
                $google_resp = curl_exec($ch);
                curl_close($ch);
                $resp = $google_resp;
                $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
                if (isset($google_resp["Success"])) {
                        $google_coupon_id = $google_resp["@attributes"]["id"];
                        $update_coupon = Coupons::where('coupon_id', $coupon_id)->update(['google_hotel_ads_promotion_id' => $google_coupon_id]);
                } else {
                        $update_coupon = Coupons::where('coupon_id', $coupon_id)->update(['google_hotel_ads_promotion_id' => $resp]);
                }
        }
        public function googleHotelStatus($hotel_id)
        {
                $getHotelDetails = MetaSearchEngineSetting::select('hotels')->where('name', 'google-hotel')->first();
                $hotel_ids = explode(',', $getHotelDetails->hotels);
                if (in_array($hotel_id, $hotel_ids)) {
                        return true;
                } else {
                        return false;
                }
        }
        public function fetchCoupons(int $coupon_id, Request $request)
        {
                $coupons = new Coupons();
                if ($coupon_id) {
                        $conditions = array('coupon_id' => $coupon_id, 'is_trash' => 0);
                        $res = Coupons::where($conditions)->first();

                        if ($res->coupon_for == '1') {
                                $res->coupon_for = 'public';
                        } else {
                                $res->coupon_for = 'private';
                        }
                        $res->valid_from = date('d-m-Y', strtotime($res->valid_from));
                        $res->valid_to = date('d-m-Y', strtotime($res->valid_to));
                        if ($res->blackout_type == '') {
                                $res->blackout_type = 4;
                        }
                        $blackout_dates = explode(',', $res->blackoutdays);
                        if (empty($blackout_dates) || $blackout_dates[0] == '') {
                                $blackout_dates = [];
                        }
                        $resp = array(
                                "company_id" => $res->company_id,
                                "hotel_id" => $res->hotel_id,
                                "room_type_id" => $res->room_type_id,
                                "coupon_name" => $res->coupon_name,
                                "coupon_code" => $res->coupon_code,
                                "coupon_for" => $res->coupon_for,
                                "valid_from" => $res->valid_from,
                                "valid_to" => $res->valid_to,
                                "discount_type" => $res->discount_type,
                                "discount" => $res->discount,
                                "blackout_dates" => $blackout_dates,
                                "blackoutdates" => $res->blackoutdates,
                                "blackout_type" => $res->blackout_type,
                                "private_coupon_restriction" => $res->private_coupon_restriction,
                        );
                        if (!empty($res)) {
                                $res = array('status' => 1, "message" => RETRIEVED_COUPONS_MESSAGE, 'data' => $resp);
                                return response()->json($res);
                        } else {
                                $res = array('status' => 0, "message" => FAILED_FETCHING_COUPONS_MESSAGE);
                                return response()->json($res);
                        }
                } else {
                        $res = array('status' => -1, "message" => FAILED_FETCHING_COUPONS_MESSAGE);
                        return response()->json($res);
                }
        }
        public function GetRatePlanByRoomType(int $hotel_id, int $room_type_id, Request $request)
        {

                $get_datas = MasterRoomRatePlan::join('rate_plan_table', 'room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
                        ->select('rate_plan_table.plan_type')
                        ->where('room_rate_plan.hotel_id', $hotel_id)
                        ->where('room_rate_plan.room_type_id', $room_type_id)
                        ->where('room_rate_plan.is_trash', 0)
                        ->where('room_rate_plan.be_rate_status', 0)
                        ->get();
                $plan_name = [];
                foreach ($get_datas as $get_data) {
                        $plan_name[] = $get_data->plan_type;
                }
                return (!empty($plan_name))
                        ?
                        response()->json(array('status' => 1, 'message' => "records found", 'data' => $plan_name))
                        :
                        response()->json(array('status' => 0, "message" => "No Room Rate Plan records found"));
        }

        public function getAppliedCouponDetails(Request $request)
        {
            try
            {
                $data = $request->all();
    
                $company_id = (isset($data['company_id']) && $data['company_id'] != '') ? $data['company_id'] : '';
                $hotel_id = isset($data['hotel_id']) ? $data['hotel_id'] : '';
                $from_date = date('Y-m-d', strtotime($data['from_date']));
                $to_date = date('Y-m-d', strtotime($data['to_date'].' +1 day'));
    
                $coupons = new Coupons();
    
                if ($company_id != '') {
    
                        $hotels = HotelInformation::where('company_id', $company_id)
                        ->where('status',1)
                        ->pluck('hotel_id');
    
                        $hotel_ids = array_values($hotels->toArray());
    
                        $coupons = Coupons::where('company_id', $company_id)
                                // ->where('is_trash', 0)
                                ->get();
                } else {
                        $coupons = Coupons::where('hotel_id', $hotel_id)
                                // ->where('is_trash', 0)
                                ->get();
                        $hotel_ids[] = $hotel_id;
                }
                $coupon_data = [];
                foreach($coupons as $coupon){
                    $data['coupon_id']=$coupon['coupon_id']; 
                    $data['coupon_code']=$coupon['coupon_code']; 
                    $data['coupon_name']=$coupon['coupon_name']; 
                    $data['discount']=$coupon['discount']; 
                    $coupon_data[$data['coupon_code']]=$data;
                }
    
               $bookings = Invoice::select('hotel_id','hotel_name','agent_code',DB::raw('group_concat(invoice_id) as invoice_ids'), DB::raw('count(*) as count') , DB::raw('ROUND(SUM(total_amount), 2) as revenue'))
               ->whereIN('hotel_id',$hotel_ids)
                ->where('booking_status', '=', 1)
                ->where('agent_code', '!=', 'NA')
                ->whereBetween('Booking_date', [$from_date, $to_date])
                ->groupBy('hotel_id','hotel_name','agent_code')
                ->get();
               
                $resp_data = [];
                foreach($bookings as $booking){
                    if($booking['agent_code'] != 'NA'){
                        $booking['coupon_name'] = isset($coupon_data[$booking['agent_code']])?$coupon_data[$booking['agent_code']]['coupon_name']:''; 
                        $booking['coupon_code'] = isset($coupon_data[$booking['agent_code']])? $coupon_data[$booking['agent_code']]['coupon_code']:$booking['agent_code']; 
                        $booking['discount'] = isset($coupon_data[$booking['agent_code']])?$coupon_data[$booking['agent_code']]['discount']:'';
                    }else{
                        $booking['coupon_name'] ='NA'; 
                        $booking['coupon_code'] = 'NA';
                        $booking['discount'] = 'NA';
                    }
                   
                    $resp_data[] =  $booking;
                }
                $resp =array('status'=>1,'message'=>'Fetched','data'=>$resp_data);
            }catch(Exception $e){
                $resp =array('status'=>0,'message'=>'Fetch failed');
            }
               
                return response()->json($resp);
        }
}
