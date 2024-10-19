<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use DateTime;
use Validator;
use App\BePromotions;
use App\MasterRatePlan;
use App\Coupons;
use App\MasterRoomType;

class PromotionsController extends Controller
{

    private $rules = array(
        'coupon_name' => 'required ',
        'coupon_for' => 'required ',
        'valid_from' => 'required ',
        'valid_to' => 'required ',
        'discount' => 'required ',
        'hotel_id' => 'required'
     );

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
    * Author: Dibyajyoti Mishra
    * Date: 20-09-2024
    * Description: This api is develop for create new promotions like(Basic, EarlyBird lastminute)
    */
    public function addNewPromotions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required',
            'promotion_type' => 'required',
            'promotion_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ]);
        }

        $data = $request->all();

        if (isset($request->auth->admin_id)) {
            $data['user_id'] = $request->auth->admin_id;
        } else if (isset($request->auth->intranet_id)) {
            $data['user_id'] = $request->auth->intranet_id;
        } else if (isset($request->auth->id)) {
            $data['user_id'] = $request->auth->id;
        }

        $blckout_array = [];

        $blackoutdates = $data['blackout_dates'];
        $blackoutdays = $data['blackout_dates'];

        if ($data['promotion_type'] == 1) {

            $applicable_days = $data['applicable_days'];

            $week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $difference_date = array_diff($week_days, $applicable_days);

            $stay_start_date = date('Y-m-d', strtotime($data['stay_start_date']));
            $stay_end_date = date('Y-m-d', strtotime($data['stay_end_date']));
            $date1 = date_create($stay_start_date);
            $date2 = date_create($stay_end_date);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            $index = $stay_start_date;

            for ($i = 0; $i < $diff; $i++) {
                $timestamp = strtotime($index);
                $day = date('l', $timestamp);
                if (in_array($day, $difference_date)) {
                    $blckout_array[] = $index;
                }
                $index = date('Y-m-d', strtotime($index . '+1 days'));
            }
        }
        foreach ($blackoutdates as $blackoutdate) {
            $blckout_array[] = $blackoutdate;
        }
        $unique_blackout_dates = array_unique($blckout_array);

        $blackout_date = implode(',',$unique_blackout_dates);
        $blackoutdays_arr = [];
        foreach($blackoutdays as $blackdays){
            $blackoutdays_arr[] = date('Y-m-d', strtotime($blackdays));
        }

        $be_promotions = new BePromotions;
        $be_promotions->hotel_id = $data['hotel_id'];
        $be_promotions->promotion_type = $data['promotion_type']; // 1: Public/Basic , 2: Private , 3 : Early Bird , 4 : Last Minute 
        $be_promotions->promotion_name = $data['promotion_name'];
        $be_promotions->selected_room_rate_plan = isset($data['selected_room_rate_plan']) ? json_encode($data['selected_room_rate_plan']) : '';
        $be_promotions->offer_type = $data['offer_type']; // 0 : percentage , 1 : fixed 
        $be_promotions->discount_percentage = isset($data['discount_percentage']) ? $data['discount_percentage'] : 0;
        $be_promotions->discounted_amount = isset($data['discounted_amount']) ? $data['discounted_amount'] : 0;
        $be_promotions->stay_start_date = isset($data['stay_start_date']) ? date('Y-m-d', strtotime($data['stay_start_date'])) : '';
        $be_promotions->stay_end_date = isset($data['stay_end_date']) ? date('Y-m-d', strtotime($data['stay_end_date'])) : null;
        $be_promotions->booking_start_date = isset($data['booking_start_date']) ? date('Y-m-d', strtotime($data['booking_start_date'])) : null;
        $be_promotions->booking_end_date = isset($data['booking_end_date']) ? date('Y-m-d', strtotime($data['booking_end_date'])) : null;
        $be_promotions->min_los = isset($data['min_los']) ? $data['min_los'] : 0;
        $be_promotions->max_los = isset($data['max_los']) ? $data['max_los'] : 0;
        $be_promotions->advance_booking_days = isset($data['advance_booking_days']) ? $data['advance_booking_days'] : 0;
        $be_promotions->booking_days_within = isset($data['booking_days_within']) ? $data['booking_days_within'] : 0;
        $be_promotions->blackout_dates = json_encode($blackoutdays_arr);
        $be_promotions->blackout_days = $blackout_date;
        $be_promotions->user_id = isset($data['user_id']) ? $data['user_id'] : 0;
        $be_promotions->member_only = isset($data['member_only']) ? $data['member_only'] : 0;
        $be_promotions->mobile_users_only = isset($data['mobileuser_only']) ? $data['mobileuser_only'] : 0;

       
        
        // if($data['hotel_id'] == 2600){
        // if($data['promotion_type'] == 1){
        //     $be_promotions->save();
        //     $push_promotion_google_hotel_ads = $this->googleHotelAdsPromotion($data, $be_promotions->id,$data['selected_room_rate_plan'],$blackoutdays_arr);
        //  }
        // }
        $be_promotions->save();

        if ($be_promotions) {
            $res = array('status' => 1, 'message' => 'Promotion Created Successfully');
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Promotion Created Failed');
            return response()->json($res);
        }
    }

    public function googleHotelAdsPromotion($data, $coupon_id, $room_rate_plan,$blackoutdays)
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
                         <DateRange start="' . $data['stay_start_date'] . '" end="' . $data['stay_end_date'] . '" days_of_week="MTWHFSU"/>
                      </CheckinDates>
              
                      <Devices>
                        <Device type="mobile"/>
                        <Device type="tablet"/>
                        <Device type="desktop"/>
                      </Devices>
                      <Discount percentage="' . $data['discount_percentage'] . '" applied_nights="1"/>
                      <LengthOfStay min="1" max="14"/>';
        $promotion_rate_xml = '';
        $promotion_room_xml = '';

        // Loop through each room type and its rate plans
        foreach ($room_rate_plan as $room_info) {
            $room_type_id = $room_info['room_type_id'];
            $selected_rate_plans = $room_info['selected_rate_plans'];
           
            $promotion_room_xml .= '<RoomTypes><RoomType id="' . $room_type_id . '"/></RoomTypes>';

            $promotion_rate_xml .= '<RatePlans>';
            foreach ($selected_rate_plans as $rate_plan_id) {
                $promotion_rate_xml .= '<RatePlan id="' . $rate_plan_id . '"/>';
            }
            $promotion_rate_xml .= '</RatePlans>';
        }

        $promotion_xml .= $promotion_room_xml;
        $promotion_xml .= $promotion_rate_xml;

        // if (isset($data['blackout_dates']) && !empty($data['blackout_dates'])) {
        //     $promotion_xml .= '<StayDates application="exclude">';
        //     foreach ($data['blackout_dates'] as $blackout_date) {
        //         $promotion_xml .= '<DateRange start="' . $blackout_date . '" end="' . $blackout_date . '" days_of_week="MTWHFSU"/>';
        //     }
        //     $promotion_xml .= '</StayDates>';
        // }

        if (isset($data['blackout_dates']) && !empty($data['blackout_dates'])) {
            $promotion_xml .= '<BlackoutDates>';
            foreach ($data['blackout_dates'] as $blackout_date) {
                $promotion_xml .= '<DateRange start="' . $blackout_date . '" end="' . $blackout_date . '"/>';
            }
            $promotion_xml .= '</BlackoutDates>';
        }

        $promotion_xml .= '<Stacking type="base"/>
                     
                    </Promotion>
                  </HotelPromotions>
                </Promotions>';

        dd($promotion_xml);

        $headers = array(
            "Content-Type: application/xml",
        );
        // $url =  'https://www.google.com/travel/hotels/uploads/promotions';
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_POST, true);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, $promotion_xml);
        // $google_resp = curl_exec($ch);
        // curl_close($ch);
        // $resp = $google_resp;
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
        if (isset($google_resp["Success"])) {
            $google_coupon_id = $google_resp["@attributes"]["id"];
            $update_coupon = Coupons::where('coupon_id', $coupon_id)->update(['google_hotel_ads_promotion_id' => $google_coupon_id]);
        } else {
            $update_coupon = Coupons::where('coupon_id', $coupon_id)->update(['google_hotel_ads_promotion_id' => $resp]);
        }
    }

    /**
    * Author: Dibyajyoti Mishra
    * Date: 20-09-2024
    * Description: This api is develop for Update promotions.
    */
    public function updatePromotions(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required',
            'promotion_type' => 'required', // 1: Public , 2: Private , 3 : Early Bird , 4 : Last Minute 
            'promotion_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ]);
        }

        $data = $request->all();
        $be_promotions = BePromotions::find($id);
        if (!$be_promotions) {
            return response()->json([
                'status' => 0,
                'message' => 'Promotion not found'
            ]);
        }

        $blackoutdates = $data['blackout_dates'];
        $blackoutdays = $data['blackout_dates'];

        $blckout_array = [];
        if ($data['promotion_type'] == 1) {

            $applicable_days = $data['applicable_days'];

            $week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $difference_date = array_diff($week_days, $applicable_days);

            $stay_start_date = date('Y-m-d', strtotime($data['stay_start_date']));
            $stay_end_date = date('Y-m-d', strtotime($data['stay_end_date']));
            $date1 = date_create($stay_start_date);
            $date2 = date_create($stay_end_date);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            $index = $stay_start_date;

            for ($i = 0; $i < $diff; $i++) {
                $timestamp = strtotime($index);
                $day = date('l', $timestamp);
                if (in_array($day, $difference_date)) {
                    $blckout_array[] = $index;
                }
                $index = date('Y-m-d', strtotime($index . '+1 days'));
            }
        }
        // if(isset($blackoutdates)){
        foreach ($blackoutdates as $blackoutdate) {
            $blckout_array[] = $blackoutdate;
        }
        $unique_blackout_dates = array_unique($blckout_array);
        
        $blackout_date = implode(',',$unique_blackout_dates);
        $data['blackout_days'] = $blackout_date;
        //   }

        $blackoutdays_arr = [];
        foreach($blackoutdays as $blackdays){
            $blackoutdays_arr[] = date('Y-m-d', strtotime($blackdays));
        }
        // dd($blackout_date);

        $data['blackout_dates'] = json_encode($blackoutdays_arr);


        // if (isset($data['blackout_dates'])) {
        //     $data['blackout_dates'] = implode(',', $data['blackout_dates']);
        // }
        if (isset($data['selected_room_rate_plan'])) {
            $data['selected_room_rate_plan'] = json_encode($data['selected_room_rate_plan']);
        }
        if (isset($data['stay_start_date'])) {
            $data['stay_start_date'] =  date('Y-m-d', strtotime($data['stay_start_date']));
        }
        if (isset($data['stay_end_date'])) {
            $data['stay_end_date'] =  date('Y-m-d', strtotime($data['stay_end_date']));
        }
        if (isset($data['booking_start_date'])) {
            $data['booking_start_date'] =  date('Y-m-d', strtotime($data['booking_start_date']));
        }
        if (isset($data['booking_end_date'])) {
            $data['booking_end_date'] =  date('Y-m-d', strtotime($data['booking_end_date']));
        }

        $be_promotions->fill($data);

        if ($be_promotions->save()) {
            $res = array('status' => 1, 'message' => 'Promotion Update Successfully');
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Promotion Updation Failed');
            return response()->json($res);
        }
    }

    /**
    * Author: Dibyajyoti Mishra
    * Date: 20-09-2024
    * Description: This api is develop to fetch all promotions.
    */
    public function getAllPromotions(Request $request, $hotel_id, $promotion_type, $status)
    {
        $promotions = BePromotions::where('hotel_id', $hotel_id)
            ->where('promotion_type', $promotion_type)
            ->where('status', $status) // status 0 = active , 1 = in active 
            ->orderBy('id', 'DESC')
            ->get();

        if ($promotions->isNotEmpty()) {
            foreach ($promotions as $promotion) {
                $room_rateplans = [];

                if ($promotion->promotion_type == 3) {
                    $promotion->promotion_type = "Early Bird";
                } else if ($promotion->promotion_type == 4) {
                    $promotion->promotion_type = "Last Minute";
                } else if ($promotion->promotion_type == 1) {
                    $promotion->promotion_type = "Basic";
                }

                if ($promotion->stay_start_date) {
                    $timestamp = strtotime($promotion->stay_start_date);
                    $promotion->stay_start_date = date('d M Y', $timestamp);
                }
                if ($promotion->stay_end_date) {
                    $timestamp = strtotime($promotion->stay_end_date);
                    $promotion->stay_end_date = date('d M Y', $timestamp);
                }
                if ($promotion->booking_start_date) {
                    $timestamp = strtotime($promotion->booking_start_date);
                    $promotion->booking_start_date = date('d M Y', $timestamp);
                }
                if ($promotion->booking_end_date) {
                    $timestamp = strtotime($promotion->booking_end_date);
                    $promotion->booking_end_date = date('d M Y', $timestamp);
                }


                $pro_blackout_dates = isset($promotion->blackout_dates) && trim($promotion->blackout_dates) !== '' ? json_decode($promotion->blackout_dates) : [];

                if (!empty($pro_blackout_dates)) {
                    foreach ($pro_blackout_dates as &$date) {
                        $timestamp = strtotime($date);
                        $date = date('d M Y', $timestamp);
                    }
                    unset($date);
                }

                $promotion->blackout_dates = $pro_blackout_dates;

                $room_rate_plan = json_decode($promotion->selected_room_rate_plan);
                if(isset($room_rate_plan)){

                foreach ($room_rate_plan as $room) {
                    $room_type_id = $room->room_type_id;
                    $room_type_name = $room->room_type;
                    $rate_plan_ids = $room->selected_rate_plans;

                    $rate_plans = [];

                    foreach ($rate_plan_ids as $rate_id) {
                        $rate_plan = MasterRatePlan::where('rate_plan_id', $rate_id)
                            ->select('rate_plan_id', 'plan_type')
                            ->first();
                        if ($rate_plan) {
                            $rate_plans[] = $rate_plan;
                        }
                    }
                    $room_rateplans[] = [
                        'room_type_id' => $room_type_id,
                        'room_type_name' => $room_type_name,
                        'rate_plans' => $rate_plans
                    ];
                }

                $promotion->room_rateplans = $room_rateplans;
            }
            }

            $response = [
                'status' => 1,
                'message' => 'Promotions List',
                'data' => $promotions
            ];
        } else {
            $response = [
                'status' => 0,
                'message' => 'Promotions Not Found'
            ];
        }

        return response()->json($response);
    }
    
    /**
    * Author: Dibyajyoti Mishra
    * Date: 20-09-2024
    * Description: This api is develop to fetch promotions details.
    */
    public function promotionDetails(Request $request, $promotion_id)
    {
        $promotions = BePromotions::where('id', $promotion_id)->first();

        if (!$promotions) {
            return response()->json([
                'status' => 0,
                'message' => 'Promotion Not Found'
            ]);
        } else {
            $room_rateplans = [];
            $multiple_days = [];

            switch ($promotions->promotion_type) {
                case 1:
                    $promotions->promotion_type = "Basic";
                    break;
                case 3:
                    $promotions->promotion_type = "Early Bird";
                    break;
                case 4:
                    $promotions->promotion_type = "Last Minute";
                    break;
            }

            if ($promotions->promotion_type == "Basic") { 

                
                $blackoutdates = json_decode($promotions->blackout_dates );
                $blackoutdays = explode(',', $promotions->blackout_days );
                
                $black_out_dates = array_diff($blackoutdays, $blackoutdates);

                $multiple_days_array = [];

                $week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];



                if (isset($black_out_dates)) {
                    foreach ($black_out_dates as $blackout_date) {
                        $timestamp = strtotime(trim($blackout_date)); // Trim to remove any extra spaces
                        $day = date('l', $timestamp);
                        array_push($multiple_days_array, $day);
                    }
                    $multiple_days = array_unique($multiple_days_array);
                }

                $multiple_days = array_values(array_diff($week_days, $multiple_days));

                $promotions->blackout_days = $blackoutdays;
            }

            $promotions->blackout_dates = isset($promotions->blackout_dates) && trim($promotions->blackout_dates) !== '' ? json_decode($promotions->blackout_dates) : [];

            $room_rate_plan = json_decode($promotions->selected_room_rate_plan);

            foreach ($room_rate_plan as $room) {
                $room_type_id = $room->room_type_id;
                $room_type_name = $room->room_type;
                $rate_plan_ids = $room->selected_rate_plans;

                $rate_plans = [];

                foreach ($rate_plan_ids as $rate_id) {
                    $rate_plan = MasterRatePlan::where('rate_plan_id', $rate_id)
                        ->select('rate_plan_id', 'plan_type')
                        ->first();
                    if ($rate_plan) {
                        $rate_plans[] = $rate_plan;
                    }
                }
                $room_rateplans[] = [
                    'room_type_id' => $room_type_id,
                    'room_type_name' => $room_type_name,
                    'rate_plans' => $rate_plans
                ];
            }

            $promotions->room_rateplans = $room_rateplans;

            $response = [
                'status' => 1,
                'message' => 'Promotions Details',
                'data' => $promotions,
                'multiple_days' => $multiple_days
            ];
        }
        return response()->json($response);
    }

    /**
    * Author: Dibyajyoti Mishra
    * Date: 20-09-2024
    * Description: This api is develop to change the promotions status to Active/Inactive.
    */
    public function promotionStatusChange(Request $request, $promotion_id, $status)
    {
        $promotions = BePromotions::where('id', $promotion_id)
            ->update(['status' => $status]); // status 1 = active, 0 = inactive

        if ($promotions) {
            if ($status == 1) {
                $msg = 'Activated';
            } else {
                $msg = 'Deactivated';
            }

            $response = [
                'status' => 1,
                'message' => $msg,
            ];
        } else {
            $response = [
                'status' => 0,
                'message' => 'Updation Failed'
            ];
        }

        return response()->json($response);
    }
    
    /**
    * Author: Dibyajyoti Mishra
    * Date: 21-09-2024
    * Description: This api is develop to get the room type and rate plan details of a Hotel.
    */
    public function getAllHotelRateplan( Request $request,int $hotel_id)
    {
        $res = DB::table('kernel.room_rate_plan')
            ->join('kernel.rate_plan_table', 'room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
            ->join('kernel.room_type_table', 'room_rate_plan.room_type_id', '=', 'room_type_table.room_type_id')
            ->select(
                'rate_plan_table.plan_type',
                'rate_plan_table.plan_name',
                'room_type_table.room_type',
                'room_rate_plan.from_date',
                'room_rate_plan.to_date',
                'room_rate_plan.bar_price',
                'room_rate_plan.room_rate_plan_id',
                'room_rate_plan.room_type_id',
                'room_rate_plan.master_plan_status',
                'room_rate_plan.rate_plan_id',
                'room_rate_plan.be_rate_status',
                'room_type_table.max_people',
                'room_rate_plan.be_rate_status'
            )
            ->where('room_rate_plan.hotel_id', '=', $hotel_id)
            ->where('room_rate_plan.is_trash', '=', 0)
            ->where('room_type_table.is_trash', '=', 0)
            ->where('rate_plan_table.is_trash', '=', 0)
            ->get();

        $grouped = $res->groupBy('room_type_id');

        $response = [];

        foreach ($grouped as $room_type_id => $items) {
            $rate_plans = [];
            foreach ($items as $item) {
                $rate_plans[] = [
                    'rate_plan_id' => $item->rate_plan_id,
                    'plan_type' => $item->plan_type,
                    'plan_name' => $item->plan_name,
                ];
            }

            $response[] = [
                'room_type_id' => $room_type_id,
                'room_type' => $items->first()->room_type,
                'rate_plans' => $rate_plans,
            ];
        }

        return (!empty($res) > 0)
            ?
            response()->json(array('status' => 1, 'message' => "records found", 'data' => $response))
            :
            response()->json(array('status' => 0, "message" => "No Master Hotel Rate Plan  records found"));
    }

    /**
     * Author : Dibyajyoti Mishra
     * Date: 25-09-2024
     * Description: This api is develop for create private coupons
     */

    public function addNewPrivateCoupons(Request $request)
    {

        $coupons = new Coupons();
        $validator = Validator::make($request->all(), $this->rules, $this->messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => "Coupon Creation Failed", 'errors' => $validator->errors()));
        }
        $data = $request->all();
        //TO get user id from AUTH token
        // $user_id = "";
        // if (isset($request->auth->admin_id)) {
        //     $data['user_id'] = $request->auth->admin_id;
        // } else if (isset($request->auth->intranet_id)) {
        //     $data['user_id'] = $request->auth->intranet_id;
        // } else if (isset($request->auth->id)) {
        //     $data['user_id'] = $request->auth->id;
        // }

        $blckout_array = [];
        $blackoutdates = $data['blackoutdates'];
        $blackoutdays = $data['blackoutdates'];
        $multiple_days = $data['multiple_days'];

        $week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $difference_date = array_diff($week_days, $multiple_days);

        $data['valid_from'] = date('Y-m-d', strtotime($data['valid_from']));
        $data['valid_to'] = date('Y-m-d', strtotime($data['valid_to']));


        $valid_from = date('Y-m-d', strtotime($data['valid_from']));
        $valid_to = date('Y-m-d', strtotime($data['valid_to']));
        $date1 = date_create($valid_from);
        $date2 = date_create($valid_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%d");
        $index = $valid_from;

        for ($i = 0; $i < $diff; $i++) {
            $timestamp = strtotime($index);
            $day = date('l', $timestamp);
            if (in_array($day, $difference_date)) {
                $blckout_array[] = $index;
            }
            $index = date('Y-m-d', strtotime($index . '+1 days'));
        }

        foreach ($blackoutdates as $blackoutdate) {
            $blckout_array[] = $blackoutdate;
        }
        $unique_blackout_dates = array_unique($blckout_array);

        $blackout_dates = implode(',', $unique_blackout_dates);
        $blackoutdays = implode(',', $blackoutdays);

        if (isset($data['private_coupon_restriction'])) {
            $data['private_coupon_restriction'] = $data['private_coupon_restriction'];
        } else {
            $data['private_coupon_restriction'] = 0;
        }

        if (!isset($data['hotel_id'])) {
            return response()->json(['status' => 0, 'message' => 'Please provide hotel info !!']);
        }

        foreach ($data['room_type_id'] as $room_type) {
            $coupon_array = [
                // 'company_id' => $data['company_id'],
                'hotel_id' => $data['hotel_id'],
                'room_type_id' => $room_type,
                'user_id' => isset($data['user_id']) ? $data['user_id'] : 0,
                'coupon_name' => $data['coupon_name'],
                'coupon_code' => $data['coupon_code'],
                'coupon_for' => 2,
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'blackoutdates' =>  $blackout_dates,
                // 'blackout_type' =>  $data['blackout_type'],
                'blackoutdays' => $blackoutdays,
                'discount_type' => 0,
                'discount' => $data['discount'],
                'client_ip' => 0,
                'private_coupon_restriction' => $data['private_coupon_restriction'],
            ];
            $insert_coupon = coupons::insertGetId($coupon_array);

        }

        if ($insert_coupon) {
            $res = array('status' => 1, "message" => "Coupon Created Succesfully");
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Coupon Creation Failed");
            return response()->json($res);
        }
    }

    /**
     * Author : Dibyajyoti Mishra
     * Date: 25-09-2024
     * Description: This api is develop for update the details of private coupons
     */

    public function UpdatePrivatecoupons(int $coupon_id, Request $request)
    {
        $coupons = new Coupons();
        $validator = Validator::make($request->all(), $this->rules, $this->messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => "Coupon Updation Failed", 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $data['valid_from'] = date('Y-m-d', strtotime($data['valid_from']));
        $data['valid_to'] = date('Y-m-d', strtotime($data['valid_to']));

        // if (isset($request->auth->admin_id)) {
        //         $data['user_id'] = $request->auth->admin_id;
        // } else if (isset($request->auth->intranet_id)) {
        //         $data['user_id'] = $request->auth->intranet_id;
        // } else if (isset($request->auth->id)) {
        //         $data['user_id'] = $request->auth->id;
        // }

        $blckout_array = [];
        $blackoutdates = $data['blackoutdates'];
        $blackoutdays = $data['blackoutdates'];
        $multiple_days = $data['multiple_days'];

        $week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $difference_date = array_diff($week_days, $multiple_days);

        $valid_from = date('Y-m-d', strtotime($data['valid_from']));
        $valid_to = date('Y-m-d', strtotime($data['valid_to']));

        $date1 = strtotime($valid_from);
        $date2 = strtotime($valid_to);
        $diff = ($date2 - $date1) / (60 * 60 * 24);
        $index = $valid_from;

        if (isset($data['private_coupon_restriction'])) {
            $data['private_coupon_restriction'] = $data['private_coupon_restriction'];
        } else {
            $data['private_coupon_restriction'] = 0;
        }

        for ($i = 0; $i < $diff; $i++) {
            $timestamp = strtotime($index);
            $day = date('l', $timestamp);
            if (in_array($day, $difference_date)) {
                $blckout_array[] = $index;
            }
            $index = date('Y-m-d', strtotime($index . '+1 days'));
        }

        foreach ($blackoutdates as $blackoutdate) {
            $blckout_array[] = $blackoutdate;
        }
        $unique_blackout_dates = array_unique($blckout_array);


        $blackout_dates = implode(',', $unique_blackout_dates);
        $blackoutdays = implode(',', $blackoutdays);
        $coupons = Coupons::where('coupon_id', $coupon_id)->first();
        if (isset($data['room_type_id'])) {
            $coupon_array = [
                // 'company_id' => $data['company_id'],
                'hotel_id' => $data['hotel_id'],
                'room_type_id' => $data['room_type_id'],
                'user_id' => isset($data['user_id']) ? $data['user_id'] : 0,
                'coupon_name' => $data['coupon_name'],
                'coupon_code' => $data['coupon_code'],
                'coupon_for' => 2, // Private coupon
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'blackoutdates' =>  $blackout_dates,
                // 'blackout_type' =>  $data['blackout_type'],
                'blackoutdays' => $blackoutdays,
                'discount_type' => 0,
                'discount' => $data['discount'],
                'private_coupon_restriction' => $data['private_coupon_restriction'],

                
            ];
            $update_coupon = coupons::where('coupon_id', $coupon_id)->update($coupon_array);
        }

        if ($update_coupon) {
            $res = array('status' => 1, "message" => "Coupon Updation Successful");
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Coupon Updation Failed");
            return response()->json($res);
        }
    }

    /**
     * Author : Dibyajyoti Mishra
     * Date: 25-09-2024
     * Description: This api is develped for fetch the details of private coupons
     */
    public function fetchPrivateCoupon(int $coupon_id)
    {
        $conditions = array('coupon_id' => $coupon_id, 'is_trash' => 0);
        $res = Coupons::where($conditions)->first();

        if (!$res) {
            $res = array('status' => 0, "message" => "Coupon not found");
            return response()->json($res);
        }

       
        $res->coupon_for = 'private';

        $res->valid_from = date('d-m-Y', strtotime($res->valid_from));
        $res->valid_to = date('d-m-Y', strtotime($res->valid_to));
        // if ($res->blackout_type == '') {
        //     $res->blackout_type = 4;
        // }

        $blackoutdays = explode(',', $res->blackoutdays);
        if (empty($blackoutdays) || $blackoutdays[0] == '') {
            $blackoutdays = [];
        }
        $blackout_dates = explode(',', $res->blackoutdates);
        $blackout_days = explode(',', $res->blackoutdays);

        $black_out_dates = array_diff($blackout_dates, $blackout_days);
        $multiple_days_array = [];
        $multiple_days = [];
        $week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        if (isset($black_out_dates)) {
            foreach ($black_out_dates as $blackout_date) {
                $timestamp = strtotime(trim($blackout_date)); // Trim to remove any extra spaces
                $day = date('l', $timestamp);
                array_push($multiple_days_array, $day);
            }
            $multiple_days = array_unique($multiple_days_array);
        }
        $multiple_days = array_values(array_diff($week_days, $multiple_days));

        $resp = array(
            // "company_id" => $res->company_id,
            "hotel_id" => $res->hotel_id,
            "room_type_id" => $res->room_type_id,
            "coupon_name" => $res->coupon_name,
            "coupon_code" => $res->coupon_code,
            "coupon_for" => $res->coupon_for,
            "valid_from" => $res->valid_from,
            "valid_to" => $res->valid_to,
            "discount_type" => $res->discount_type,
            "discount" => $res->discount,
            "blackout_dates" => $blackoutdays,
            "blackoutdates" => $res->blackoutdates,
            "private_coupon_restriction" => $res->private_coupon_restriction,
            
            "multiple_days" => $multiple_days,
        );
        if (!empty($res)) {
            $res = array('status' => 1, "message" => "Coupon Retrieve Successfully", 'data' => $resp);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Coupon Retrieve Failed");
            return response()->json($res);
        }
    }

    /**
     * Author : Dibyajyoti Mishra
     * Date: 25-09-2024
     * Description: This api is develop for get all the private coupons for a hotel
     */
    public function GetAllPrivateCoupons(int $hotel_id)
    {
        $coupons = Coupons::where('hotel_id', $hotel_id)
            ->where('coupon_for', 2)
            ->where('is_trash', 0)
            ->orderby('coupon_id', 'Desc')
            ->get();

        if ($coupons->isempty()) {
            return response()->json([
                'status' => 0,
                'message' => 'Coupons not found for this Hotel'
            ]);
        }

        foreach ($coupons as $coupon) {

            $coupon->coupon_for = 'private';

            $room_types = MasterRoomType::select('room_type')->where('room_type_id', $coupon->room_type_id)->first();

            $coupon['room_type'] = $room_types['room_type'];

            $coupon->valid_from = date('d-M-Y', strtotime($coupon->valid_from));
            $coupon->valid_to = date('d-M-Y', strtotime($coupon->valid_to));
        }

        if (sizeof($coupons) > 0) {
            return response()->json([
                'status' => 1,
                'mesage' => "Coupons data found",
                'data' => $coupons
            ]);
        }
    }

    /**
     * Author :Dibyajyoti Mishra
     * Date : 25-09-2024
     * Description: This api is develop for Delete the private coupon
     */
    public function DeletePrivateCoupon(int $coupon_id)
    {
        $coupon = Coupons::where('coupon_id',$coupon_id)->first();

        if(!$coupon){
            return response()->json([
                'status' => 0,
                'message'=> 'Coupon Not found'
            ]);
        }

        if(Coupons::where('coupon_id',$coupon_id)->update(['is_trash' => 1])){
            return response()->json([
                'status' => 1,
                'message' => 'Coupon Deleted Successfully'
            ]);
        }else{
            return response()->json([
                'status' => 0,
                'message' => "Coupon Deletion Failed"
            ]);
        }

    }

    /**
    * Author: Saroj Patel
    * Date: 20-09-2024
    * Description:This api is develop to fetch promotions details.
    */
    public function aplicablePromotions(Request $request)
    {
        $data = $request->all();
        $check_in_date = date('Y-m-d', strtotime($data['check_in']));
        $check_out_date = $data['check_out'];
        $booking_date = date("Y-m-d");
        $hotel_id = $data['hotel_id'];
        $room_rate_plan = $data['room_rate_plan'];

        $response = [];

        foreach ($room_rate_plan as $roomPlan) {
            $room_type_id = $roomPlan['room_type_id'];
            $rate_plan_id = $roomPlan['rate_plan_id'];

            $maxDiscountPercentage = 0;
            $maxDiscountAmount = 0;
            $promotionType = '';
            $selectedPromotion = null;

            $earlybirdPromotions = BePromotions::where('hotel_id', $hotel_id)
                ->where('promotion_type', 3)
                // ->where('status', 1)
                ->where('stay_start_date', '<=', $check_in_date)
                ->where('stay_end_date', '>=', $check_in_date)
                ->first();

            if ($earlybirdPromotions) {
                $advance_booking_days = $earlybirdPromotions->advance_booking_days;
                $stay_start_date = $earlybirdPromotions->stay_start_date;
                $booking_start_date = $earlybirdPromotions->booking_start_date;
                $booking_end_date = $earlybirdPromotions->booking_end_date;

                $min_booking_date = date('Y-m-d', strtotime($stay_start_date . " - $advance_booking_days days"));

                if ($booking_date <= $min_booking_date && $booking_date >= $booking_start_date && $booking_date <= $booking_end_date) {
                    $earlybirdRoomRatePlan = json_decode($earlybirdPromotions->selected_room_rate_plan, true);

                    foreach ($earlybirdRoomRatePlan as $earlybirdPlan) {
                        if ($room_type_id == $earlybirdPlan['room_type_id'] && in_array($rate_plan_id, $earlybirdPlan['selected_rate_plans'])) {
                            if ($earlybirdPromotions->offer_type == 0) {
                                $discount = $earlybirdPromotions->discount_percentage;
                            } else {
                                $discount = $earlybirdPromotions->discounted_amount;
                            }

                            if ($discount > $maxDiscountPercentage) {
                                $maxDiscountPercentage = $discount;
                                $promotionType = 'Early Bird';
                                $selectedPromotion = $earlybirdPromotions;
                            }
                        }
                    }
                }
            }

            $lastminutePromotions = BePromotions::where('hotel_id', $hotel_id)
                ->where('promotion_type', 4)
                // ->where('status', 1)
                ->where('stay_start_date', '<=', $check_in_date)
                ->where('stay_end_date', '>=', $check_in_date)
                ->first();

                // dd($earlybirdPromotions , $lastminutePromotions);

            if ($lastminutePromotions) {

                $stay_start_date = $lastminutePromotions->stay_start_date;
                $booking_start_date = $lastminutePromotions->booking_start_date;
                $booking_end_date = $lastminutePromotions->booking_end_date;
                $booking_days_within = $lastminutePromotions->booking_days_within;

                $checkInTimestamp = strtotime($check_in_date);
                $bookingTimestamp = strtotime($booking_date);

                $min_booking_date = date('Y-m-d', strtotime($stay_start_date . " - $booking_days_within days"));

                // dd($min_booking_date);

                if ($booking_date >= $min_booking_date && $booking_date <= $check_in_date  && $booking_date >= $booking_start_date && $booking_date <= $booking_end_date) {
                    $lastminuteRoomRatePlan = json_decode($lastminutePromotions->selected_room_rate_plan, true);

                    foreach ($lastminuteRoomRatePlan as $lastminutePlan) {
                        if ($room_type_id == $lastminutePlan['room_type_id'] && in_array($rate_plan_id, $lastminutePlan['selected_rate_plans'])) {
                            if ($lastminutePromotions->offer_type == 0) {
                                $discount = $lastminutePromotions->discount_percentage;
                            } else {
                                $discount = $lastminutePromotions->discounted_amount;
                            }

                            if ($discount > $maxDiscountPercentage) {
                                $maxDiscountPercentage = $discount;
                                $promotionType = 'Last Minute';
                                $selectedPromotion = $lastminutePromotions;
                            }
                        }
                    }
                }
            }

            // Add the room type and rate plan only if a promotion applies
            if ($promotionType) {
                $response[] = [
                    'promotion_name' => $promotionType,
                    'room_type_id' => $room_type_id,
                    'rate_plan_id' => $rate_plan_id,
                    'max_discount_percentage' => $maxDiscountPercentage,
                    'max_discount_amount' => $selectedPromotion->discounted_amount ?? 0,
                ];
            }
        }
        if($response == []){

            $response [] = [
                "message" => "No applicable promotions found"
            ];

        }

        return response()->json($response);
    }


    // public function aplicablePromotions(Request $request)
    // {
    //     $data = $request->all();
    //     $check_in_date = $data['check_in'];
    //     $check_out_date = $data['check_out'];
    //     $booking_date = date("Y-m-d");
    //     // $booking_date = "2024-09-28";
    //     $hotel_id = $data['hotel_id'];
    //     $room_rate_plan = $data['room_rate_plan'];

    //     $response = [];

    //     foreach ($room_rate_plan as $roomPlan) {
    //         $room_type_id = $roomPlan['room_type_id'];
    //         $rate_plan_id = $roomPlan['rate_plan_id'];

    //         $maxDiscountPercentage = 0;
    //         $maxDiscountAmount = 0;
    //         $promotionType = '';
    //         $message = '';

    //         // $basicPromotions = BePromotions::where('hotel_id', $hotel_id)
    //         // ->where('promotion_type', 1)
    //         // ->where('status', 1)
    //         //     ->where('stay_start_date', '<=',
    //         //         $check_in_date
    //         //     )
    //         //     ->where('stay_end_date', '>=', $check_in_date)
    //         //     ->orderby('id', 'DESC')
    //         //     ->first();

    //         // if ($basicPromotions) {
    //         //     $basicRoomRatePlan = json_decode($basicPromotions->selected_room_rate_plan, true);

    //         //     foreach ($basicRoomRatePlan as $basicPlan) {
    //         //         if ($room_type_id == $basicPlan['room_type_id'] && in_array($rate_plan_id, $basicPlan['selected_rate_plans'])) {
    //         //             if ($basicPromotions->offer_type == 0) {
    //         //                 $maxDiscountPercentage = max($maxDiscountPercentage, $basicPromotions->discount_percentage);
    //         //             } elseif ($basicPromotions->offer_type == 1) {
    //         //                 $maxDiscountAmount = max($maxDiscountAmount, $basicPromotions->discounted_amount);
    //         //             }
    //         //             $promotionType = 'Basic';
    //         //         }
    //         //     }
    //         // }

    //         $earlybirdPromotions = BePromotions::where('hotel_id', $hotel_id)
    //             ->where('promotion_type', 3)
    //             // ->where('status', 1)
    //             ->where('stay_start_date', '<=', $check_in_date)
    //             ->where('stay_end_date', '>=', $check_in_date)
    //             ->first();

    //         if ($earlybirdPromotions) {

    //             $advance_booking_days = $earlybirdPromotions->advance_booking_days;
    //             $stay_start_date = $earlybirdPromotions->stay_start_date;
    //             $booking_start_date = $earlybirdPromotions->booking_start_date;
    //             $booking_end_date = $earlybirdPromotions->booking_end_date;

    //             $min_booking_date = date('Y-m-d', strtotime($stay_start_date . " - $advance_booking_days days"));

    //             if ($booking_date <= $min_booking_date && $booking_date >= $booking_start_date && $booking_date <= $booking_end_date
    //             ) {

    //                 $earlybirdRoomRatePlan = json_decode($earlybirdPromotions->selected_room_rate_plan, true);

    //                 foreach ($earlybirdRoomRatePlan as $earlybirdPlan) {
    //                     if (
    //                         $room_type_id == $earlybirdPlan['room_type_id'] && in_array($rate_plan_id, $earlybirdPlan['selected_rate_plans'])
    //                     ) {
    //                         if ($earlybirdPromotions->offer_type == 0) {
    //                             $maxDiscountPercentage = max($maxDiscountPercentage, $earlybirdPromotions->discount_percentage);
    //                         } elseif ($earlybirdPromotions->offer_type == 1) {
    //                             $maxDiscountAmount = max($maxDiscountAmount, $earlybirdPromotions->discounted_amount);
    //                         }
    //                         $promotionType = 'Early Bird';
    //                     }
    //                 }
    //             }
    //         }

    //         $lastminutePromotions = BePromotions::where('hotel_id', $hotel_id)
    //         ->where('promotion_type', 4)
    //             // ->where('status', 1)
    //             ->where('stay_start_date', '<=', $check_in_date)
    //             ->where('stay_end_date', '>=', $check_in_date)
    //             ->first();

    //         if ($lastminutePromotions) {

    //             $booking_start_date = $lastminutePromotions->booking_start_date;
    //             $booking_end_date = $lastminutePromotions->booking_end_date;
    //             $booking_days_within = $lastminutePromotions->booking_days_within;

    //             $checkInTimestamp = strtotime($check_in_date);
    //             $bookingTimestamp = strtotime($booking_date);

    //             $maxBookingTimestamp = $checkInTimestamp - ($booking_days_within * 86400); // 86400 seconds in a day

    //             if ($bookingTimestamp <= $maxBookingTimestamp) {
    //                 $lastminuteRoomRatePlan = json_decode($lastminutePromotions->selected_room_rate_plan, true);

    //                 foreach ($lastminuteRoomRatePlan as $lastminutePlan) {
    //                     if ($room_type_id == $lastminutePlan['room_type_id'] && in_array($rate_plan_id, $lastminutePlan['selected_rate_plans'])) {
    //                         if ($lastminutePromotions->offer_type == 0) {
    //                             $maxDiscountPercentage = max($maxDiscountPercentage, $lastminutePromotions->discount_percentage);
    //                         } elseif ($lastminutePromotions->offer_type == 1) {
    //                             $maxDiscountAmount = max($maxDiscountAmount, $lastminutePromotions->discounted_amount);
    //                         }
    //                         $promotionType = 'Last Minute';
    //                     }
    //                 }
    //             }
    //         }

    //         if ($promotionType) {
    //             $response[] = [
    //                 'promotion_name' => $promotionType,
    //                 'room_type_id' => $room_type_id,
    //                 'rate_plan_id' => $rate_plan_id,
    //                 'max_discount_percentage' => $maxDiscountPercentage,
    //                 'max_discount_amount' => $maxDiscountAmount,
    //             ];
    //         } 
    //     }

    //     return response()->json($response);
    // }



}
