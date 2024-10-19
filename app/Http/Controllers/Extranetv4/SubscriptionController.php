<?php

namespace App\Http\Controllers\Extranetv4;

use App\Activity;
use App\ActivityMaster;
use App\CurrentActivityTable;
use App\Plans;
use App\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LDAP\Result;
use App\Features;
use App\Apps;

use DB;
use Illuminate\Support\Facades\DB as FacadesDB;
use App\Http\Controllers\Controller;

class SubscriptionController extends Controller
{

    public function subscriptionPlanSetup(Request $request)
    {

        $subscription_details = $request->all();

        $hotel_creation = $subscription_details['hotel'];
        $room_creation = $subscription_details['room'];
        $rate_creation = $subscription_details['hotel'];
        $inventory = $subscription_details['inventory'];
        $rate = $subscription_details['rate'];
        $booking_transation = $subscription_details['booking_transation'];
        $subscription_details['user_id'] = 1;
        $feature = json_encode(['hotel' => $hotel_creation, 'room' => $room_creation, 'rate' => $rate_creation, 'inventory' => $inventory, 'booking_transation' => $booking_transation]);
        $subscription_details['feature'] = $feature;

        $subscription_details = Subscription::insert($subscription_details);
        if ($subscription_details) {
            $res = array('status' => 1, "message" => "Subscription Plan Saved successfully");
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Subscription Plan Saved Failed");
            return response()->json($res);
        }
    }

    public function subscriptionPlanUpdate(Request $request, $id)
    {

        $subscription_details = $request->all();

        $hotel_creation = $subscription_details['hotel'];
        $room_creation = $subscription_details['room'];
        $rate_creation = $subscription_details['hotel'];
        $inventory = $subscription_details['inventory'];
        $rate = $subscription_details['rate'];
        $booking_transation = $subscription_details['booking_transation'];
        $subscription_details['user_id'] = 1;
        $feature = json_encode(['hotel' => $hotel_creation, 'room' => $room_creation, 'rate' => $rate_creation, 'inventory' => $inventory, 'booking_transation' => $booking_transation]);
        $subscription_details['feature'] = $feature;

        $subscription_details = Subscription::where('subscription_plan_id', $id)->update($subscription_details);
        if ($subscription_details) {
            $res = array('status' => 1, "message" => "Subscription Plan updated successfully");
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Subscription Plan updated Failed");
            return response()->json($res);
        }
    }

    public function fetchSubscriptionPlan(Request $request, $id)
    {

        $subscription_details = Subscription::where('subscription_plan_id', $id)->first();
        if ($subscription_details) {
            $res = array('status' => 1, "message" => "Subscription Plan fetched successfully", 'data' => $subscription_details);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Subscription Plan fetched Failed");
            return response()->json($res);
        }
    }

    public function deleteSubscriptionPlan(Request $request, $id)
    {

        $subscription_details = Subscription::where('subscription_plan_id', $id)->update(['is_trash', '1']);
        if ($subscription_details) {
            $res = array('status' => 1, "message" => "Subscription Plan Details Deleted Successfully");
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Subscription Plan Details Deleted Failed");
            return response()->json($res);
        }
    }


    public function hotelSubscriptionPlanAdd(Request $request)
    {

        $subscription_details = [
            'hotel_id' => $request->hotel_id,
            'plan_id' => $request->plan_id,
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
            'status' => 1,
        ];
        
        $gems_array = [
            'hotel_code' => $request['hotel_code'],
            'hotel_id' => $request['hotel_id'],
            'pms_type_name' => 'GEMS',
            'pms_status'  => 1

        ];
        
        $hotel_subscription_details = Subscription::insert($subscription_details);
        if ($hotel_subscription_details) {
            $plan_details = Plans::where('plan_id', $subscription_details['plan_id'])->first();
            $apps = $plan_details->apps;
            $app = explode(',', $apps);
            $check = in_array('6', $app);
            $url = "https://dev.cm.bookingjini.com/cm_pms_details/add_pms_hotelid";
            if ($check) {
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
                    CURLOPT_POSTFIELDS => $gems_array
                ));
                $result = curl_exec($curl);
                curl_close($curl);

                //  print_r($result);
                //  exit;
                
                return $result;
            } else {
                $res = array('status' => 0, "message" => "Failed");
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, "message" => " Subscription Plan Saved Failed");
            return response()->json($res);
        }

        // if ($hotel_subscription_details) {
        //     $res = array('status' => 1, "message" => "Subscription Plan Saved successfully");
        //     return response()->json($res);
        // } else {
        //     $res = array('status' => 0, "message" => "Subscription Plan Saved Failed");
        //     return response()->json($res);
        // }
    }


    public function hotelSubscriptionPlanEdit($subscription_id, $id, $hotel_id)
    {

        $subscription_details['subscription_master_id'] = $id;
        $subscription_details['hotel_id'] = $hotel_id;
        // $subscription_details['user_id']= 1;
        // $subscription_details['is_active']= 1;
        // $subscription_details['is_trash']= 0;

        $hotel_subscription_details = Plans::where('subscription_plan_id', $subscription_id)
            ->update($subscription_details);

        if ($hotel_subscription_details) {
            $res = array('status' => 1, "message" => "Subscription Plan Saved successfully");
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Subscription Plan Saved Failed");
            return response()->json($res);
        }
    }


    public function getHotelSubscriptionPlan($hotel_id)
    {

        $subscription_detaills = Subscription::
            join('kernel.plans', 'kernel.subscriptions.plan_id', '=', 'plans.plan_id')
            ->select('kernel.subscriptions.*', 'plans.plan_name')
            ->where('hotel_id', $hotel_id)->first();

        $valid_from = $subscription_detaills->valid_from;
        $valid_to = $subscription_detaills->valid_to;
        $current_date = date('Y-m-d');
        $details = [];
        if ($subscription_detaills) {
            if ($current_date >= $valid_to) {
                $res = array('status' => 0, "message" => "Plan is Expaired");
                return response()->json($res);
            } else {

                // $subscription_details = DB::table('kernel.subscriptions')->where('hotel_id', $hotel_id)->first();
                $details['hotel_id'] = $hotel_id;
                $details['plan_id'] = $subscription_detaills->plan_id;
                $details['plan_name'] = $subscription_detaills->plan_name;


                $plan_details = Plans::where('plan_id', $subscription_detaills->plan_id)->first();
                $features = $plan_details->features;
                $features = explode(',', $features);
                $apps = $plan_details->apps;
                $apps = explode(',', $apps);

                $app_code_array = [];
                $app_details = Apps::whereIn('id', $apps)->get();
                //dd($app_details[0]->app_code);
                foreach ($app_details as $app_detail) {
                    $app_code_array[] = $app_detail->app_code;
                }
                $details['app_code'] = $app_code_array;

                $feature_details = Features::whereIN('feature_id', $features)->get();

                $feature_code_array = [];
                foreach ($feature_details as $feature_detail) {

                    $feature_code_array[] = $feature_detail->feature_name;
                }
                $details['feature_name'] = $feature_code_array;
                return response()->json($details);
            }
        } else {
            $res = array('status' => 0, "message" => "Plan is not Subscribed");
            return response()->json($res);
        }
    }


    public function upgradeHotelSubscriptionPlan(Request $request)
    {
        $data = $request->all();
        $check_plan = Subscription::where('hotel_id', $data['hotel_id'])->first();
        $current_date = date('Y-m-d');
        $valid_to = date('Y-m-d', strtotime('+1 year'));
        $subscription_details = [
            'hotel_id' => $request['hotel_id'],
            'plan_id' => $request['plan_id'],
            'valid_from' => $current_date,
            'valid_to' => $valid_to,
            'status' => 1
        ];
        $gems_array = [
            'hotel_code' => $request['hotel_id'],
            'hotel_id' => $request['hotel_id'],
            'pms_type_name' => 'GEMS',
            'pms_status'  => 1

        ];
       
        if ($check_plan) {
            $subscription_date = $check_plan->valid_from;
            $subscription_date = date('d-m-y', strtotime($check_plan->valid_from));

            $current_date = date_create($current_date);
            $subscription_date = date_create($subscription_date);
            $diff = date_diff($current_date, $subscription_date);
            $diff = $diff->format("%a");


            if ($diff > 90) {
                $inactive_previous_plan = Subscription::where('hotel_id', $data['hotel_id'])->update(['status' => 0]);
                if ($inactive_previous_plan) {
                    $hotel_subscription_details = Subscription::insert($subscription_details);
                    $subscription_detaills =  Subscription::where('hotel_id', $data['hotel_id'])->first();
                    $plan_id = $subscription_detaills->plan_id;
                    $plan_details = Plans::where('plan_id', $subscription_details['plan_id'])->first();
                    $current_apps = $plan_details->apps;
                    $current_app = explode(',', $current_apps);
                    $current_plan = in_array('6', $current_app);
                    if ($current_plan) {
                        $is_currentplan_active = 1;
                    } else {
                        $is_currentplan_active = 0;
                    }

                    $plan_id = $request['plan_id'];
                    $plan_details = Plans::where('plan_id', $subscription_details['plan_id'])->first();
                    $upgraded_apps = $plan_details->apps;
                    $upgraded_apps = explode(',', $upgraded_apps);
                    $upgraded_plan = in_array('6', $upgraded_apps);
                    if ($upgraded_plan) {
                        $is_upgradeplan_active = 1;
                    } else {
                        $is_upgradeplan_active = 0;
                    }

                    $url = "https://dev.cm.bookingjini.com/cm_pms_details/add_pms_hotelid";
                    $url2 = "https://dev.cm.bookingjini.com/remove-hotel-pms-info";

                    if ($is_currentplan_active = 0 && $is_upgradeplan_active = 1) {
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
                            CURLOPT_POSTFIELDS => $gems_array
                        ));
                        $result = curl_exec($curl);
                        curl_close($curl);
                        return $result;
                    } elseif ($is_currentplan_active = 1 && $is_upgradeplan_active = 0) {
                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url2,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => $gems_array
                        ));
                        $result = curl_exec($curl);
                        curl_close($curl);
                        return $result;
                    }
                }
            } else {
                $res = array('status' => 0, "message" => "You are not eligible to obtain the Plan.");
                return response()->json($res);
            }

            if ($hotel_subscription_details) {
                $res = array('status' => 1, "message" => "Subscription plan obtain successfully");
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => "Subscription plan obtain failed");
                return response()->json($res);
            }
        } else {
            $hotel_subscription_details = Subscription::insert($subscription_details);

            if ($hotel_subscription_details) {
                $res = array('status' => 1, "message" => "Subscription plan obtain successfully");
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => "Subscription plan obtain failed");
                return response()->json($res);
            }
        }
    }




    public function GetProductDetails()
    {
        $products = [];
        $all_products = Apps::select('app_code')->get();

        if ($all_products) {
            foreach ($all_products as $product) {
                array_push($products, $product->app_code);
            }

            return response()->json(array('status' => 1, 'message' => " Product fetched sucessfully", 'Data' => $products));
        } else {
            return response()->json(array('status' => 0, 'message' => "Product fetched failed"));
        }
    }

    public function FetchPlans()
    {

        $all_plans = Plans::select('subscription_plan_id', 'plan_name', 'apps', 'features')->get();

        if ($all_plans) {
           
            foreach ($all_plans as  $all_plan) {
                $app_name = [];
                $plan_app = $all_plan->apps;
                $app_details = explode(',', $plan_app);
                foreach ($app_details as $app_detail) {
                    $apps = Apps::where('id', $app_detail)->first();
                    array_push($app_name, $apps['app_name']);
                }
                $all_plan->apps = $app_name;

                $featuress = [];
                $features = $all_plan->features;
                $feature_details = explode(',', $features);
                foreach ($feature_details as $feature_detail) {
                    $feature = Features::where('feature_id', $feature_detail)->first();
                    array_push($featuress, $feature['feature_name']);
                } 
                $all_plan->features = $featuress;
            }

            $res = array('status' => 1, "message" => "Fatched Suscessfully", 'data' => $all_plans);
            return response()->json($res);
        }else{
            $res = array('status' => 0, "message" => "Failed");
            return response()->json($res);
        }
    }
}
