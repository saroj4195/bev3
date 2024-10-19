<?php

namespace App\Http\Controllers\DayBooking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\DayPackage;
use App\Http\Controllers\Controller;
use App\DayBookingPromotions;

class DayBookingPromotionsController extends Controller
{

    public function activeBasicPromotions(int $hotel_id, Request $request)
    {
            $res = DayBookingPromotions::where('hotel_id',$hotel_id)
                                       ->where('is_active',1)
                                       ->orderby('id','Desc')->get();
            
            $promotions = [];
            foreach ($res as $data) {
                    if ($data->day_package_id){
                        $pack_id = trim($data->day_package_id, '[]');

                        $pack_ids = explode(',', $pack_id);
                        
                        $pack_names = DayPackage::whereIn('id',$pack_ids)
                                                ->pluck('package_name')
                                                ->toArray();
                       }
                  
                    $promotions[] = [
                          'promotions_id' => $data->id,
                          'promotions_name' => $data->promotions_name,
                          'experience_name' => implode(', ', $pack_names),
                          'promotions_code' => $data->promotions_code,
                          'valid_from' => date('d M, y', strtotime($data->valid_from)),
                          'valid_to' => date('d M, y', strtotime($data->valid_to)),
                          'discount_percentage' => $data->discount_percentage,
                    ];
            }
            if (sizeof($res) > 0) {
                    $res = array('status' => 1, 'message' => "Promotion list", 'data' => $promotions);
                    return response()->json($res);
            } else {
                    $res = array('status' => 0, "message" => "No promotion found");
                    return response()->json($res);
            }
    }
    public function basicPromotionsDetails(int $pro_id, Request $request)
    {
           $res = DayBookingPromotions::where('id', $pro_id)
                                      ->where('is_active', 1)
                                      ->first();
     
         if ($res) {
             $package_id = trim($res->day_package_id, '[]');
             $res->day_package_id = explode(',', $package_id);
             $pack_names  = DayPackage::whereIn('id',$res->day_package_id)
                                                ->pluck('package_name')
                                                ->toArray();
             $res->day_package_name = $pack_names;
             $res->blackout_dates = !empty($res->blackout_dates) ? explode(',',$res->blackout_dates) : [];
             return response()->json([
                 'status' => 1,
                 'message' => 'Promotion details',
                 'data' => $res
             ]);
         }

           return response()->json([
               'status' => 0,
               'message' => 'No promotion found'
           ]);
    }

    public function getDayPackages($hotel_id)
    {

    $day_outing_Package = DayPackage::where('hotel_id', $hotel_id)->where('is_trash', 0)->get();
    $active_package_array = [];
    if (sizeof($day_outing_Package) > 0) {
        foreach ($day_outing_Package as $package) {
        
        $active_package_array[] = array(
        'package_id' => $package->id,
        'package_name' => $package->package_name,
        );
        }
    if ($day_outing_Package) {
        $final_data = ['status' => 1, 'data' => $active_package_array];
    } else {
        $final_data = ['status' => 0, 'msg' => 'No package available.'];
    }
    } else {
        $final_data = ['status' => 0, 'msg' => 'No package available.'];
        }
    return response()->json($final_data);
    }

    public function addBasisPromotion(Request $request)
    {
        
            $validator = Validator::make($request->all(), [
                'day_package_id' => 'required',
                // 'promotions_name' => 'required',
                'promotions_code' => 'required',
                'valid_from' => 'required',
                'valid_to' => 'required',
                'discount_percentage' => 'required',
                'hotel_id' => 'required'
            ], [
                'day_package_id.required' => 'The day package id field is required.',
                // 'promotions_name.required' => 'The promotion name field is required.',
                'promotions_code.required' => 'The promotion code field is required.',
                'valid_from.required' => 'The valid from field is required.',
                'valid_to.required' => 'The valid to field is required.',
                'discount_percentage.required' => 'The discount percentage field is required.',
                'hotel_id.required' => 'The hotel id field is required.'
            ]);
        
            if ($validator->fails()) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Promotion added failed',
                    'errors' => $validator->errors()
                ]);
            }
            $data = $request->all();

            //TO get user id from AUTH token
            // $user_id = "";
            // if (isset($request->auth->admin_id)) {
            //         $data['user_id'] = $request->auth->admin_id;
            // } else if (isset($request->auth->intranet_id)) {
            //         $data['user_id'] = $request->auth->intranet_id;
            // } else if (isset($request->auth->id)) {
            //         $data['user_id'] = $request->auth->id;
            // }

            $blackoutdates = $data['blackout_dates'];
            $blackout_dates = implode(',', $blackoutdates);

            $day_package_id = $data['day_package_id'];
            $day_package_ids = implode(',', $day_package_id);

            $promotion = new DayBookingPromotions();
            $promotion->day_package_id = $day_package_ids;
            // $promotion->day_package_name = $data['day_package_name'];
            $promotion->promotions_name = $data['promotions_name'];
            $promotion->promotions_code = $data['promotions_code'];
            $promotion->valid_from = date('Y-m-d', strtotime($data['valid_from']));
            $promotion->valid_to = date('Y-m-d', strtotime($data['valid_to']));
            $promotion->discount_percentage = $data['discount_percentage'];
            $promotion->hotel_id = $data['hotel_id'];
            $promotion->blackout_dates = $blackout_dates;
            $promotion->is_active = 1;

            $promotion->save();
            if($promotion){
            return response()->json([
                'status' => 1,
                'message' => 'Promotion added successfully',
            ]);
        }else{
            return response()->json([
                'status' => 0,
                'message' => 'Promotion added failed',
            ]);
        }

           
    }

    public function updateBasicPromotion(int $pro_id , Request $request)
    {
        
            $validator = Validator::make($request->all(), [
                'day_package_id' => 'required',
                'promotions_name' => 'required',
                'promotions_code' => 'required',
                'valid_from' => 'required',
                'valid_to' => 'required',
                'discount_percentage' => 'required',
            ], [
                'day_package_id.required' => 'The day package id field is required.',
                'promotions_name.required' => 'The promotion name field is required.',
                'promotions_code.required' => 'The promotion code field is required.',
                'valid_from.required' => 'The valid from field is required.',
                'valid_to.required' => 'The valid to field is required.',
                'discount_percentage.required' => 'The discount percentage field is required.',
            ]);
        
            if ($validator->fails()) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Promotion update failed',
                    'errors' => $validator->errors()
                ]);
            }
            $data = $request->all();

            if (isset($data['blackout_dates'])) {
                $data['blackout_dates'] = implode(',', $data['blackout_dates']);
            }
            $promotion =DayBookingPromotions::where('id',$pro_id)->first();

            if(!$promotion){
            return response()->json([
                'status' => 0,
                'message' => 'Promotion not found',
            ]);
        }


        if ($promotion->fill($data)->save()) {
            return response()->json([
                    'status' => 1,
                    'message' => 'Promotion Updated'
                ]);
        } 
        else{
            return response()->json([
                'status' => 0,
                'message' => 'Promotion update failed',
            ]);
        }

           
    }

    public function deleteBasicPromotions(int $pro_id, Request $request)
    {
        $promotion = DayBookingPromotions::where('id', $pro_id)->first();

        if (!$promotion) {
            return response()->json([
                'status' => 0,
                'message' => 'Promotion not found',
            ]);
        }
        $update_success = DayBookingPromotions::where('id', $pro_id)->update(['is_active' => 0]);

        if ($update_success) {
            return response()->json([
                'status' => 1,
                'message' => 'Promotion deleted'
            ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Promotion delete failed'
                ]);
        }
    }


}
