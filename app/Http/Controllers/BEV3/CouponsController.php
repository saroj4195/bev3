<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use App\CurrentRateBe;
use App\CurrentRate;
use App\Coupons;

class CouponsController extends Controller
{


    public function checkPrivateCoupon(Request $request)
    {
        $couponData = $request->all();
        $checkin_date = date('Y-m-d', strtotime($couponData['checkin_date']));
        $checkout_date = date('Y-m-d', strtotime($couponData['checkout_date']));
        $code = $couponData['code'];
        $hotel_id = $couponData['hotel_id'];
        $selected_room_types = $couponData['room_types'];

        $coupons_room_type_wise = [];

        $room_type_id = [];
        foreach ($selected_room_types as $room_type) {
            $room_type_id[] = $room_type['room_type_id'];
        }
        $couponDetails = Coupons::where('hotel_id', $hotel_id)
        ->whereIn('room_type_id', $room_type_id)
        ->where('coupon_code', $code)
        ->where('coupon_for', 2)
        ->where('is_trash', 0)
        ->first();

        // $couponDetails = Coupons::where('hotel_id', $hotel_id)
        // ->where('coupon_code', $code)
        // ->where('coupon_for', 2)
        // ->where('is_trash', 0)
        // ->first();
        // }

        if (empty($couponDetails)) {
            $res = array('status' => 0, 'message' => "Invalid Coupon");
            return response()->json($res);
        }

        $data_present = [];
        $data = [];

        if (isset($couponDetails)) {
            if ($checkin_date > $couponDetails->valid_to) {
                $res = array('status' => 0, 'message' => "This Coupon is Expired!");
                return response()->json($res);
            }
            $room_type = $couponDetails->room_type_id;
            // if ($room_type == 0) {
            foreach ($selected_room_types as $selected_room_type) {
                $period     = new \DatePeriod(
                    new \DateTime($checkin_date),
                    new \DateInterval('P1D'),
                    new \DateTime($checkout_date)
                );

                foreach ($period as $value) {
                    $index = $value->format('Y-m-d');
                    if ($couponDetails->valid_to >= $index) {
                        $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $selected_room_type['room_type_id'], $selected_room_type['rate_plan_id'], $checkin_date, $checkout_date, $code);
                    }
                }

                $data_present['coupon_id']        = $couponDetails->coupon_id;
                $data_present['room_type_id']     = $selected_room_type['room_type_id'];
                $data_present['coupon_code']      = $couponDetails->coupon_code;
                $data_present['discount']         = $couponDetails->discount;
                $data_present['rates']            = $data;

                $coupons_room_type_wise[] = $data_present;
            }

            // } else {

            //     $rate_plan_det = $selected_room_types[0];
            //     $rate_plan_id = $rate_plan_det['rate_plan_id'];
            //     $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $room_type, $rate_plan_id, $checkin_date, $checkout_date, $code);

            //     $data_present['coupon_id']        = $couponDetails->coupon_id;
            //     $data_present['room_type_id']     = $room_type;
            //     $data_present['coupon_code']      = $couponDetails->coupon_code;
            //     $data_present['discount']         = $couponDetails->discount;
            //     $data_present['rates']            = $data;

            //     $coupons_room_type_wise[] = $data_present;
            // }

            if ($coupons_room_type_wise[0]['rates'][0]['discount_percentage'] == 0) {
                $res = array('status' => 0, 'message' => "This coupon is not applicable");
                return response()->json($res);
            }

            $res = array('status' => 1, 'message' => "Private coupon retrieved successfully", 'data' => $coupons_room_type_wise);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => "Invalid Coupon");
            return response()->json($res);
        }
    }


    public function getCurrentRatesByRoomnRatePlan($hotel_id, $room_type_id, $rate_plan_id, $date_from, $date_to, $code)
    {
        $filtered_rate = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $date_to = date('Y-m-d', strtotime($date_to . '-1 day'));
 
        $rates = CurrentRate::where('room_type_id', $room_type_id)
            ->where('hotel_id', $hotel_id)
            ->where('rate_plan_id', $rate_plan_id)
            ->where('ota_id', '-1')
            ->whereBetween('stay_date', [$date_from, $date_to])
            ->get()->toArray();

        if (sizeof($rates) > 0) {
            foreach ($rates as $rate) {
                $multiple_occupancy = json_decode($rate['multiple_occupancy']);
                $filtered_rate[$rate['stay_date']] = array('bar_price' => $rate['bar_price'], 'extra_adult_price' => $rate['extra_adult_price'], 'extra_child_price' => $rate['extra_child_price'], 'date' => $rate['stay_date'], 'multiple_occupancy' => $multiple_occupancy);
            }

            $private_coupon_array = [];
            $public_coupon_array = [];
            for ($i = 0; $i < $diff; $i++) {
                $d = $date_from;
                $coupons = Coupons::where('hotel_id', $hotel_id)
                ->where('valid_from', '<=', $date_from)
                ->where('valid_to', '>=', $date_from)
                ->where('coupon_code',$code)
                ->whereIN('room_type_id', [0, $room_type_id])
                ->where('is_trash', 0)
                ->get();

                $max_coupon_percentage = 0;
                if(sizeof($coupons)>0){
                    $multiple_coupon_one_date = [];
                    foreach($coupons as $coupon){
                        if($coupon->coupon_for == 1){
                            $multiple_coupon_one_date[] = $coupon->discount;
                        }
                        if($coupon->coupon_for == 2){
                            $private_coupon_array[$d] = $coupon->discount;
                        }
                    }
                 
                    if(!empty($multiple_coupon_one_date)){
                        $public_coupon_array[$d] = max($multiple_coupon_one_date);
                    }else{
                        $public_coupon_array[$d] = 0;
                    }
                }

                $filtered_rate[$d] = $filtered_rate[$d];
                $rates_by_date = $filtered_rate[$d];
                $bar_price = $rates_by_date['bar_price'];

                // $private_coupon_applied_count = 0;
                if($private_coupon_array[$d] >= $public_coupon_array[$d]){
                    // $private_coupon_applied_count = 1;
                    $discounted_price = $bar_price * $private_coupon_array[$d] / 100;
                    $filtered_rate[$d]['discount_percentage'] = $private_coupon_array[$d];
                }else{
                    $discounted_price = 0;
                    $filtered_rate[$d]['discount_percentage'] = 0;
                }

                $price_after_discount = $bar_price - $discounted_price;
                $filtered_rate[$d]['price_after_discount'] = $price_after_discount;

                if (isset($filtered_rate[$d]['multiple_occupancy'])) {
                    $multiple_occ = [];
                    foreach ($filtered_rate[$d]['multiple_occupancy'] as $occupancy) {
                        $discounted_price = $occupancy * $filtered_rate[$d]['discount_percentage'] / 100;
                        $multiple_occupancy = $occupancy - $discounted_price;
                        array_push($multiple_occ, $multiple_occupancy);
                    }
                    $filtered_rate[$d]['multiple_occupancy'] = $multiple_occ;
                }
                $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }
      
        $filtered_rate = array_values($filtered_rate);
       
        return $filtered_rate;
    }

    public function checkPrivateCouponold2(Request $request)
    {
        $couponData = $request->all();
        $checkin_date = date('Y-m-d', strtotime($couponData['checkin_date']));
        $checkout_date = date('Y-m-d', strtotime($couponData['checkout_date']));
        $code = $couponData['code'];
        $hotel_id = $couponData['hotel_id'];
        $selected_room_types = $couponData['room_types'];

        $coupons_room_type_wise = [];
        $couponDetails = Coupons::where('hotel_id', $hotel_id)
        ->where('valid_from', '<=', $checkin_date)
        ->where('valid_to', '>=', $checkin_date)
        ->where('coupon_code', $code)
        ->where('is_trash', 0)
        ->first();


        if(empty($couponDetails)){
            $res = array('status' => 0, 'message' => "Coupon is expired");
            return response()->json($res);
        }

        $coupons = Coupons::where('hotel_id', $hotel_id)
        ->where('valid_from', '<=', $checkin_date)
        ->where('valid_to', '>=', $checkin_date)
        ->where('is_trash', 0)
        ->get();

        $public_coupons_array = [];
        if(sizeof($coupons)>0){
            foreach($coupons as $coupon){
                if($coupon->coupon_for == 1){
                    $public_coupons_array [] = $coupon->discount;
                }
            }
            if(!empty($public_coupons_array)){
                $max_coupon_percentage = max($public_coupons_array);
            }else{
                $max_coupon_percentage = 0;
            }
        }

        if($couponDetails->discount < $max_coupon_percentage){
        
            $res = array('status' => 0, 'message' => "Coupon is not applicable");
            return response()->json($res);
        }
        $data_present = [];
        $data = [];

        if (isset($couponDetails)) {
            $room_type = $couponDetails->room_type_id;
            if ($room_type == 0) {
                foreach ($selected_room_types as $selected_room_type) {
                    $period     = new \DatePeriod(
                        new \DateTime($checkin_date),
                        new \DateInterval('P1D'),
                        new \DateTime($checkout_date)
                    );
                 
                    foreach ($period as $value) {
                        $index = $value->format('Y-m-d');
                        if ($couponDetails->valid_to >= $index) {
                            $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $selected_room_type['room_type_id'], $selected_room_type['rate_plan_id'], $checkin_date, $checkout_date, $code);
                        }
                    }

                    $data_present['coupon_id']        = $couponDetails->coupon_id;
                    $data_present['room_type_id']     = $selected_room_type['room_type_id'];
                    $data_present['coupon_code']      = $couponDetails->coupon_code;
                    $data_present['rates']            = $data;

                    $coupons_room_type_wise[] = $data_present;
                }

            } else {

                $rate_plan_det = $selected_room_types[0];
                $rate_plan_id = $rate_plan_det['rate_plan_id'];
                $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $room_type, $rate_plan_id, $checkin_date, $checkout_date, $code);

                $data_present['room_type_id']     = $room_type;
                $data_present['coupon_code']      = $couponDetails->coupon_code;
                $data_present['discount']         = $couponDetails->discount;
                $data_present['rates']            = $data;

                $coupons_room_type_wise[] = $data_present;
            }
            $res = array('status' => 1, 'message' => "Private coupon retrieved successfully", 'data' => $coupons_room_type_wise);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => "Invalid Coupon");
            return response()->json($res);
        }
    }


    public function getCurrentRatesByRoomnRatePlanold($hotel_id, $room_type_id, $rate_plan_id, $date_from, $date_to, $code)
    {
        $filtered_rate = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $date_to = date('Y-m-d', strtotime($date_to . '-1 day'));
 
        $rates = CurrentRate::where('room_type_id', $room_type_id)
            ->where('hotel_id', $hotel_id)
            ->where('rate_plan_id', $rate_plan_id)
            ->where('ota_id', '-1')
            ->whereBetween('stay_date', [$date_from, $date_to])
            ->get()->toArray();

        if (sizeof($rates) > 0) {
            foreach ($rates as $rate) {
                $multiple_occupancy = json_decode($rate['multiple_occupancy']);
                $filtered_rate[$rate['stay_date']] = array('bar_price' => $rate['bar_price'], 'extra_adult_price' => $rate['extra_adult_price'], 'extra_child_price' => $rate['extra_child_price'], 'date' => $rate['stay_date'], 'multiple_occupancy' => $multiple_occupancy);
            }

            for ($i = 0; $i < $diff; $i++) {

                $coupons = Coupons::where('hotel_id', $hotel_id)
                ->where('valid_from', '<=', $date_from)
                ->where('valid_to', '>=', $date_from)
                ->whereIN('room_type_id', [0, $room_type_id])
                ->where('is_trash', 0)
                ->get();

                $max_coupon_percentage = 0;
                if(sizeof($coupons)>0){
                    $coupons_percentage = [];
                    foreach($coupons as $coupon){

                        $coupons_percentage [] = $coupon->discount;
                    }
                    $max_coupon_percentage = max($coupons_percentage);
                }
                $d = $date_from;

                $filtered_rate[$d] = $filtered_rate[$d];
                $rates_by_date = $filtered_rate[$d];
                $bar_price = $rates_by_date['bar_price'];

                if (empty($coupons_percentage)) {
                    $discounted_price = 0;
                } else {
                    $discounted_price = $bar_price * $max_coupon_percentage / 100;
                }

                $price_after_discount = $bar_price - $discounted_price;
                $filtered_rate[$d]['price_after_discount'] = $price_after_discount;
                $filtered_rate[$d]['discount_percentage'] = $max_coupon_percentage;

                if (isset($filtered_rate[$d]['multiple_occupancy'])) {
                    $multiple_occ = [];
                    foreach ($filtered_rate[$d]['multiple_occupancy'] as $occupancy) {
                        $discounted_price = $occupancy * $max_coupon_percentage / 100;
                        $multiple_occupancy = $occupancy - $discounted_price;
                        array_push($multiple_occ, $multiple_occupancy);
                    }
                    $filtered_rate[$d]['multiple_occupancy'] = $multiple_occ;
                }
                $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }

        $filtered_rate = array_values($filtered_rate);
        return $filtered_rate;
    }


    public function checkPrivateCouponOld(Request $request)
    {
        $couponData = $request->all();
        $checkin_date = date('Y-m-d', strtotime($couponData['checkin_date']));
        $checkout_date = date('Y-m-d', strtotime($couponData['checkout_date']));
        $code = $couponData['code'];
        $hotel_id = $couponData['hotel_id'];
        $selected_room_types = $couponData['room_types'];

        $coupons_room_type_wise = [];
        $couponDetails = Coupons::where('hotel_id', $hotel_id)->where('coupon_code', $code)->where('is_trash', 0)->first();
        if (isset($couponDetails)) {
            $room_type = $couponDetails->room_type_id;
            if ($room_type == 0) {
                foreach ($selected_room_types as $selected_room_type) {
                    $period     = new \DatePeriod(
                        new \DateTime($checkin_date),
                        new \DateInterval('P1D'),
                        new \DateTime($checkout_date)
                    );
                    $data_present = [];
                    foreach ($period as $value) {
                        $index = $value->format('Y-m-d');
                        if ($couponDetails->valid_to >= $index) {
                            $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $selected_room_type['room_type_id'], $selected_room_type['rate_plan_id'], $checkin_date, $checkout_date, $code);
                        }
                    }

                    $data_present['coupon_id']        = $couponDetails->coupon_id;
                    $data_present['room_type_id']     = $selected_room_type['room_type_id'];
                    $data_present['coupon_code']      = $couponDetails->coupon_code;
                    $data_present['discount']         = $couponDetails->discount;
                    $data_present['rates']            = $data;

                    $coupons_room_type_wise[] = $data_present;
                }
            } else {

                $rate_plan_det = $selected_room_types[0];
                $rate_plan_id = $rate_plan_det['rate_plan_id'];
                $data = $this->getCurrentRatesByRoomnRatePlan($hotel_id, $room_type, $rate_plan_id, $checkin_date, $checkout_date, $code);

                $data_present['room_type_id']     = $room_type;
                $data_present['coupon_code']      = $couponDetails->coupon_code;
                $data_present['discount']         = $couponDetails->discount;
                $data_present['rates']            = $data;

                $coupons_room_type_wise[] = $data_present;
            }
            $res = array('status' => 1, 'message' => "Private coupon retrieved successfully", 'data' => $coupons_room_type_wise);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => "Invalid Coupon", 'data' => array());
            return response()->json($res);
        }
    }
}
