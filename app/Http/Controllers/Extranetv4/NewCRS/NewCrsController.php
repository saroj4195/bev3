<?php

namespace App\Http\Controllers\Extranetv4\NewCRS;

use App\NewCrs\PartnerDetails;
use App\NewCrs\PartnerMaster;
use App\NewCrs\SeasonMaster;
use Illuminate\Http\Request;
use Validator;
use App\NewCrs\PartnerRateplanSetup;
use DB;
use App\NewCrs\RoomRatePlan;
use App\RoomTypeTable;
use PhpParser\Node\Expr\FuncCall;
use Illuminate\Validation\Rule;
use PhpParser\Builder\Function_;
use PHPUnit\Util\Json;
use App\Http\Controllers\Controller;
use App\Invoice;
use App\NewCrs\CrsCanclePolicy;

class NewCrsController extends Controller
{
    /**
     * @author Swatishree Padhy
     * 03-09-2022
     * api for partner and season create, update and select
     **/
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function addSeason(request $request)
    {
        $data = $request->all();
        $from_date = date('Y-m-d', strtotime($data['validate_from']));
        $to_date = date('Y-m-d', strtotime($data['validate_to']));
        $season_range_details = SeasonMaster::insert($data);

        if ($season_range_details) {
            $result = array('status' => 1, "message" => 'Created');
            return response()->json($result);
        } else {
            $res = array('status' => 0, "message" => 'Create Failed');
            return response()->json($res);
        }
    }

    public function UpdateSeason(Request $request, $season_id)
    {
        $data = $request->all();

        $from_date = date('Y-m-d', strtotime($data['validate_from']));
        $to_date = date('Y-m-d', strtotime($data['validate_to']));

        $update_season = SeasonMaster::where('season_id', $season_id)
            ->update($data);

        if ($update_season) {
            $result = array('status' => 1, "message" => 'Updated');
            return response()->json($result);
        } else {
            $res = array('status' => 0, "message" => 'Update Failed');
            return response()->json($res);
        }
    }

    public function selectAllSeason($hotel_id)
    {
        //$select_season = SeasonMaster::select('season_id', 'season_type', 'pickseason')->where('season_id', $season_id)->where('is_trash', 1)->get();
        $select_season = SeasonMaster::select('season_id', 'season_type', 'validate_from', 'validate_to', 'is_active')->where('hotel_id',$hotel_id)->get();


        if (sizeOf($select_season) > 0) {
            $result = array('status' => 1, "message" => 'Data Fetched', 'data' => $select_season);
            return response()->json($result);
        } else {
            $res = array('status' => 0, "message" => 'Data Fetch Failed or No data available');
            return response()->json($res);
        }
    }
    
    public function activeSeason($season_id)
    {
        if ($delete_season_details = SeasonMaster::where('season_id', $season_id)
            ->update(['is_active' => 1])

        ) {
            $ins =  array('status' => 1, "message" => 'Activated');
            return response()->json($ins);
        } else {
            $ins1 =  array('status' => 0, "message" => 'Activated Failed');
            return response()->json($ins1);
        }
    }


    public function deActiveSeason($season_id)
    {
        if ($delete_season_details = SeasonMaster::where('season_id', $season_id)
            ->update(['is_active' => 0])

        ) {
            $ins =  array('status' => 1, "message" => 'Deactivated');
            return response()->json($ins);
        } else {
            $ins1 =  array('status' => 0, "message" => 'Deactivated Failed');
            return response()->json($ins1);
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //partner

    public function addPartner(request $request)
    {

        $get_partner_details = $request->all();

        if ($get_partner_details) {
            $insert_data = PartnerDetails::insert($get_partner_details);
            if ($insert_data) {
                $result = array('status' => 1, "message" => 'Created');
                return response()->json($result);
            } else {
                $res = array('status' => 0, "message" => 'Create Failed');
                return response()->json($res);
            }
        }
    }

    public function updatePartnerDetails(request $request, $id)
    {

        // $insert_data = PartnerDetails::where('id', '!=', $id)
        //     ->where('gstin', $request->gstin)
        //     ->where('pan_card', $request->pan_card)
        //     ->get();
        // if (sizeof($insert_data) > 0) {
        // } else {
        //     $res = array('status' => 0, "message" => 'Already Exits');
        //     return response()->json($res);
        // }

        $update_partner_detail = $request->all();

        if ($update_partner_detail) {

            $insert_data = PartnerDetails::where('id', $id)
                ->update($update_partner_detail);

            if ($insert_data) {
                $result = array('status' => 1, "message" => 'Updated');
                return response()->json($result);
            } else {
                $res = array('status' => 0, "message" => 'Updated Failed');
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, "message" => 'failed');
            return response()->json($res);
        }
    }

    public function getAllPartnerDetails($hotel_id)
    {

        $select_data = PartnerDetails::where('hotel_id', $hotel_id)
            // ->where('is_active', 1)
            ->get();

        if (sizeof($select_data) > 0) {
            $result = array('status' => 1, "message" => 'Fetched', 'data' => $select_data);
            return response()->json($select_data);
        } else {
            $res = array('status' => 0, "message" => 'Fetched Failed');
            return response()->json($res);
        }
    }

    public function selectPartnerDetail($id)
    {
        $select_partner_details = PartnerDetails::where('id', $id)
            ->where('is_active', 1)
            ->first();
        if ($select_partner_details) {
            //hide the column
            $select_partner_details->makeHidden(['is_active', 'created_at', 'updated_at']);
            $ins =  array('status' => 1, "message" => 'Fetched', 'data' => $select_partner_details);
            return response()->json($ins);
        } else {
            $ins1 =  array('status' => 0, "message" => 'Failed');
            return response()->json($ins1);
        }
    }

    public function selectPartnerDetailByContactDetails($hotel_id, $contact_no)
    {
        $select_partner_details = PartnerDetails::where('hotel_id', $hotel_id)
        ->where('contact_no', $contact_no)->where('is_active',1)
            ->first();

        if (empty($select_partner_details)) {
            $select_partner_details = PartnerMaster::where('hotel_id', $hotel_id)->where('contact_no', $contact_no)->first();

            if ($select_partner_details) {
                $ins =  array('status' => 1, "message" => 'Fetched', 'data' => $select_partner_details);
                return response()->json($ins);
            } else {
                $ins1 =  array('status' => 0, "message" => 'Failed');
                return response()->json($ins1);
            }
        } else {
            $ins1 =  array('status' => 1, "message" => 'Fetched', 'data' => $select_partner_details);
            return response()->json($ins1);
        }
    }

    public function selectPartnerByGst($hotel_id, $gstin)
    {
        $select_partner_details = PartnerDetails::where('gstin', $gstin)
            ->where('hotel_id', $hotel_id)
            ->first();
        if (empty($select_partner_details)) {
            $select_partner_details = PartnerMaster::where('gstin', $gstin)->first();
            if ($select_partner_details) {

                $ins =  array('status' => 1, "message" => 'Fetched', 'data' => $select_partner_details);
                return response()->json($ins);
            } else {
                $ins1 =  array('status' => 0, "message" => 'Failed');
                return response()->json($ins1);
            }
        } else {
            $ins =  array('status' => 1, "message" => 'Fetched', 'data' => $select_partner_details);
            return response()->json($ins);
        }
    }

    public function activePartner($hotel_id, $id)
    {
        if ($delete_season_details = PartnerDetails::where('hotel_id', $hotel_id)->where('id', $id)
            ->update(['is_active' => 1])
        ) {
            $ins =  array('status' => 1, "message" => 'Activated');
            return response()->json($ins);
        } else {
            $ins1 =  array('status' => 0, "message" => 'Activate Failed');
            return response()->json($ins1);
        }
    }

    public function deactivatePartner($id, $hotel_id)
    {

        $remove_data = PartnerDetails::where('hotel_id', $hotel_id)
            ->where('id', $id)
            ->update(['is_active' => 0]);
        if ($remove_data) {
            $ins =  array('status' => 1, "message" => 'Deactivated',);
            return response()->json($ins);
        } else {
            $ins =  array('status' => 0, "message" => 'Deactivate Failed');
            return response()->json($ins);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // add partner master
    public function addPartnerDetails(request $request)
    {
        $data = $request->all();

        $partner_details = PartnerMaster::insert($data);

        if ($partner_details) {
            $result = array('status' => 1, "message" => 'Created');
            return response()->json($result);
        } else {
            $res = array('status' => 0, "message" => 'Create Failed');
            return response()->json($res);
        }
    }

    public function selectPartnerDetails($id)
    {
        $select_data = PartnerMaster::where('partner_id', $id)->first();

        if ($select_data) {
            $result = array('status' => 1, "message" => 'Fetched', 'data' => $select_data);
            return response()->json($select_data);
        } else {
            $res = array('status' => 0, "message" => 'Fetch Failed');
            return response()->json($res);
        }
    }

    public function selectAllPartnerDetails($partner_name)
    {

        $select_data = PartnerMaster::where('partner_name', $partner_name)->get();

        if (sizeof($select_data) > 0) {
            $result = array('status' => 1, "message" => 'Fetched', 'data' => $select_data);
            return response()->json($select_data);
        } else {
            $res = array('status' => 0, "message" => 'Fetched Failed');
            return response()->json($res);
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //partner rate plan setup

    public function addPartnerRatePlanSetup(request $request)
    {
        $data = $request->all();
        $partner_array = [];
        foreach ($data['rate_plan'] as $key => $val) {
            $multiple_occupancies = $val['multiple_occupancy'];
            $multiple_occupancy = json_encode($multiple_occupancies);

            $partner_array[] = [
                'hotel_id'          => $data['hotel_id'],
                'partner_id'        => $data['partner_id'],
                'room_type_id'      => $data['room_type_id'],
                'season_id'         => $data['season_id'],
                'rate_plan_id'      => $val['rate_plan_id'],
                'bar_price'         => $val['bar_price'],
                'multiple_occupancy' => $multiple_occupancy,
                'extra_adult'       => $val['extra_adult'],
                'extra_child'       => $val['extra_child']
            ];
        }

        if ($partner_array) {
            $season_range_details = PartnerRateplanSetup::insert($partner_array);

            if ($season_range_details) {
                $result = array('status' => 1, "message" => 'Created');
                return response()->json($result);
            } else {
                $res = array('status' => 0, "message" => 'Create Failed');
                return response()->json($res);
            }
        }
    }

    public function updatePartnerRatePlanSetup(request $request)
    {
        $data = $request->all();
        $delet_rateplan_details = PartnerRateplanSetup::where('hotel_id', $data['hotel_id'])
            ->where('partner_id', $data['partner_id'])
            ->where('season_id', $data['season_id'])
            ->where('room_type_id', $data['room_type_id'])
            ->delete();

        $partner_array = [];
        if ($delet_rateplan_details) {

            foreach ($data['rate_plan'] as $key => $val) {
                $multiple_occupancies = $val['multiple_occupancy'];
                $multiple_occupancy = json_encode($multiple_occupancies);
                $partner_array[] = [
                    'hotel_id' => $data['hotel_id'],
                    'partner_id' => $data['partner_id'],
                    'room_type_id' => $data['room_type_id'],
                    'season_id' => $data['season_id'],
                    'rate_plan_id' => $val['rate_plan_id'],
                    'bar_price' => $val['bar_price'],
                    'multiple_occupancy' => $multiple_occupancy,
                    'extra_adult' => $val['extra_adult'],
                    'extra_child' => $val['extra_child']
                ];
            }

            if ($partner_array) {
                $season_range_details = PartnerRateplanSetup::insert($partner_array);
                if ($season_range_details) {
                    $result = array('status' => 1, "message" => 'Updated');
                    return response()->json($result);
                } else {
                    $res = array('status' => 0, "message" => 'Update Failed');
                    return response()->json($res);
                }
            }
        } else {
            $res = array('status' => 0, "message" => ' Failed');
            return response()->json($res);
        }
    }

    public function selectRatePlan($id, $hotel_id, $room_type_id, $season_id)
    {

        $select_data = PartnerRateplanSetup::where('id', $id)
            ->where('hotel_id', $hotel_id)
            ->where('room_type_id', $room_type_id)
            ->where('season_id', $season_id)
            ->get();

        if ($select_data) {
            $result = array('status' => 1, "message" => 'Fetched', 'data' => $select_data);
            return response()->json($select_data);
        } else {
            $res = array('status' => 0, "message" => 'Fetch Failed');
            return response()->json($res);
        }
    }

    public function selectAllPartnerRatePlanSetup($hotel_id)
    {

        $select_data = PartnerRateplanSetup::where('hotel_id', $hotel_id)
            ->get();

        if (sizeof($select_data) > 0) {
            $result = array('status' => 1, "message" => 'Fetched', 'data' => $select_data);
            return response()->json($select_data);
        } else {
            $res = array('status' => 0, "message" => 'Fetched Failed');
            return response()->json($res);
        }
    }

    public function selectPartnerRatePlanList($hotel_id, $room_type_id, $partner_id, $season_id)
    {
        $partner_price_datails = PartnerRateplanSetup::join('kernel.rate_plan_table', 'partner_rateplan_setup.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
            ->join('kernel.room_type_table', 'partner_rateplan_setup.room_type_id', '=', 'room_type_table.room_type_id')
            ->select('partner_rateplan_setup.*', 'rate_plan_table.plan_type', 'rate_plan_table.plan_name', 'room_type_table.max_people')
            ->where('partner_rateplan_setup.hotel_id', $hotel_id)
            ->where('partner_rateplan_setup.room_type_id', $room_type_id)
            ->where('partner_rateplan_setup.partner_id', $partner_id)
            ->where('partner_rateplan_setup.season_id', $season_id)
            ->get();

        foreach ($partner_price_datails as $partner_price) {
            $partner_price->multiple_occupancy = json_decode($partner_price->multiple_occupancy);
        }

        if (sizeof($partner_price_datails) > 0) {
            return response()->json(array('status' => 1, 'message' => 'Fetched', 'Data' => $partner_price_datails));
        } else {

            $select_room_rate_plans = RoomRatePlan::join('rate_plan_table', 'room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
                ->join('room_type_table', 'room_rate_plan.room_type_id', '=', 'room_type_table.room_type_id')
                ->select('room_rate_plan.room_type_id', 'room_type_table.room_type', 'room_rate_plan.rate_plan_id', 'rate_plan_table.plan_name', 'rate_plan_table.plan_type', 'room_type_table.max_people')
                ->where('room_rate_plan.room_type_id', $room_type_id)
                ->where('room_rate_plan.hotel_id', $hotel_id)
                ->get();

            if ($select_room_rate_plans) {
                foreach ($select_room_rate_plans as  $select_room_rate_plan) {
                    if ($select_room_rate_plan->room_type_shortcode == null) {
                        $select_room_rate_plan['room_type_code'] = substr($select_room_rate_plan->room_type, 0, 3);
                    } else {
                        $select_room_rate_plan['room_type_code'] = $select_room_rate_plan->room_type_shortcode;
                    }
                }
            }

            if ($select_room_rate_plans) {
                return response()->json(array('status' => 1, 'message' => 'Fetched', 'Data' => $select_room_rate_plans));
            } else {
                return response()->json(array('status' => 0, 'message' => 'Fetch Failed'));
            }
        }
    }

    public function percentageCalculation($hotel_id, $room_type_id, $rate_plan_id, request $request)
    {

        $data = $request->all();

        $rateplan_details = RoomRatePlan::where('hotel_id', $hotel_id)
            ->where('room_type_id', $room_type_id)
            ->where('rate_plan_id', $rate_plan_id)
            ->orderby('room_rate_plan_id', 'DESC')
            ->first();

        $bar_price = $rateplan_details->bar_price;

        $multiple_occupancies =  $rateplan_details->multiple_occupancy;


        $occupancy_percentage = [];
        $occupancy_details = [];

        $multiple_occupancy_details = json_decode($multiple_occupancies);

        // $mobile_details = json_decode(json_encode($multiple_occupancies),true);
        // $check_array = substr($mobile_details, -1);
        // if($check_array == "]")
        // {
        //     $multiple_occupancy_details = json_decode($multiple_occupancies);
        // }else{
        //     $multiple_occupancy_details = 0;
        // }

        $extra_adult_price = $rateplan_details->extra_adult_price;

        $extra_adult_per = $data['percentage'] / 100 * $extra_adult_price;
        $occupancy_details['extra_adult_price'] =  $extra_adult_price - $extra_adult_per;



        $extra_child_price = $rateplan_details->extra_child_price;
        $extra_child_per = $data['percentage'] / 100 * $extra_child_price;
        $occupancy_details['extra_child_price'] =  $extra_child_price - $extra_child_per;


        $percentage_cal = $data['percentage'] / 100 * $bar_price;
        $occupancy_details['bar_price'] = $bar_price - $percentage_cal;

        if ($multiple_occupancy_details != 0) {
            foreach ($multiple_occupancy_details as $multiple_occupancy_detail) {

                if (empty($multiple_occupancy_detail)) {

                    $multiple_occupancy = 0;
                } else {

                    $multiple_occupancy = (int)$multiple_occupancy_detail;
                }

                $percentage_of_occupancy = $data['percentage'] / 100 * $multiple_occupancy;

                $total_percentage_of_multipleoccupancy = (float)$multiple_occupancy_detail - (float)$percentage_of_occupancy;

                $occupancy_percentage[] = $total_percentage_of_multipleoccupancy;
            }
        } else {
            $occupancy_percentage[] = 0;
        }


        $occupancy_details['multiple_occupancy'] = $occupancy_percentage;

        if ($occupancy_percentage) {
            $result = array('status' => 1, "Data" =>  $occupancy_details);
            return response()->json($result);
        } else {
            $result = array('status' => 1, "message" => 'Failed');
            return response()->json($result);
        }
    }

    public function getRatePlanData(request $request)
    {

        $data = $request->all();

        $details = [];
        $season_details = SeasonMaster::select('season_id')->where('hotel_id', $data['hotel_id'])
            ->where('validate_from', '<=', $data['validate_from'])
            ->where('validate_to', '>=', $data['validate_to'])
            ->first();

        if ($season_details) {
            $season_id = $season_details['season_id'];
            $room_details = $data['room_details'];

            foreach ($room_details as $room_detail) {

                $room_data = PartnerRateplanSetup::where('hotel_id', $data['hotel_id'])
                    ->where('partner_id', $data['partner_id'])
                    ->where('room_type_id', $room_detail['room_type_id'])
                    ->where('rate_plan_id', $room_detail['rate_plan_id'])
                    ->first();

                if ($room_data) {
                    $hotel_id = $room_data->hotel_id;
                    $partner_id = $room_data->partner_id;
                    $room_type_id = $room_data->room_type_id;
                    $rate_plan_id = $room_data->rate_plan_id;
                    $bar_price = $room_data->bar_price;
                    $multiple_occupancies = json_decode($room_data->multiple_occupancy, true);
                    $extra_adult = $room_data->extra_adult;
                    $extra_child = $room_data->extra_child;

                    $details[] = [
                        "hotel_id"           =>  $hotel_id,
                        "partner_id"         => $partner_id,
                        "season_id"          => $season_id,
                        "room_type_id"       => $room_type_id,
                        "rate_plan_id"       => $rate_plan_id,
                        "bar_price"          => $bar_price,
                        "multiple_occupancy" => $multiple_occupancies,
                        "extra_adult"        => $extra_adult,
                        "extra_child"        => $extra_child

                    ];
                } else {
                    $details[] = [
                        "hotel_id"         =>  $room_data['hotel_id'],
                        "partner_id"       => $room_data['partner_id'],
                        "season_id"        => $season_id,
                        "room_type_id"     => $room_data['room_type_id'],
                        "rate_plan_id"     => $room_data['rate_plan_id'],
                        "bar_price"        => 0,
                        "multiple_occupancy" => 0,
                        "extra_adult"      => 0,
                        "extra_child"      => 0

                    ];
                }
            }


            if ($details) {
                return response()->json(array('status' => 1, "message" => 'Fetched', 'data' => $details));
            } else {
                return response()->json(array('status' => 0, "message" => 'Fetch failed'));
            }
        } else {
            $result = array('status' => 0, "message" => 'No data avaliable');
            return response()->json($result);
        }
    }

    public function partnerRateMapping($hotel_id, $partner_id)
    {
        $room_type_details =  RoomTypeTable::select('room_type', 'room_type_id')->where('hotel_id', $hotel_id)->get();
    
        $season_mapping_details = [];
        $season_details = SeasonMaster::select('season_type', 'season_id')->where('hotel_id', $hotel_id)->where('is_active', 1)->get();
        // return $season_details;

        foreach ($room_type_details as $room_type_detail) {

            foreach ($season_details as $season_detail) {

                $details['room_type_name'] = $room_type_detail->room_type;
                $details['room_type_id'] = $room_type_detail->room_type_id;
                $details['season_name'] = $season_detail->season_type;
                $details['season_id'] = $season_detail->season_id;

                array_push($season_mapping_details, $details);
            }
        }
        foreach ($season_mapping_details as $key => $season_mapping_detail) {
            $partner_rate_details = PartnerRateplanSetup::where('hotel_id', $hotel_id)->where('room_type_id', $season_mapping_detail['room_type_id'])->where('season_id', $season_mapping_detail['season_id'])->where('partner_id', $partner_id)->first();

            if (!empty($partner_rate_details)) {
                $season_mapping_details[$key]['is_active'] = 1;
            } else {
                $season_mapping_details[$key]['is_active'] = 0;
            }
        }
        // return $season_mapping_details;

        if ($season_mapping_details) {
            return response()->json(array('status' => 1, "message" => 'Fetched', 'data' => $season_mapping_details));
        } else {
            return response()->json(array('status' => 0, "message" => 'Fetch failed'));
        }
    }


    public function addCanclePolicy(request $request)
    {

        $data = $request->all();
        if($data){
        $policy_array = [
            'hotel_id' => $data['hotel_id'],
            'cancel_policy' => $data['cancel_policy']
        ];

        $insert_policy = CrsCanclePolicy::updateOrInsert(
            [
                'hotel_id' => $data['hotel_id'],
            ],
            $policy_array
        );

        if ($insert_policy) {
            $res = array('status' => 1, "message" => 'Saved');
            return response()->json($res);
        } else {
            $res = array('status' => 1, "message" => 'Saved Failed');
            return response()->json($res);
        }
      }else{
        return response()->json(array('status' => 0 , 'msg' =>'Failed'));
      } 
    }


    public function getHotelPolicies(int $hotel_id)
    {
        if ($hotel_id) {
            $conditions = array('hotel_id' => $hotel_id);
            $data = CrsCanclePolicy::select('cancel_policy')->where($conditions)->first();
            if (!empty($data)) {
                $res = array('status' => 1, "message" => 'Fetched', 'policies' => $data->cancel_policy);
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => "Fetch Failed");
                return response()->json($res);
            }
        } else {
            return response()->json(array('status' => 0, "message" => "Failed"));
        }
    }


    public function activePartnerList($hotel_id)
    {

        $select_data = PartnerDetails::where('hotel_id', $hotel_id)
            ->where('is_active', 1)
            ->get();
        foreach($select_data as $select){
            $select['desgination'] = ucfirst($select['desgination']);
            $select['partner_type'] = ucfirst($select['partner_type']);
        }

        if (sizeof($select_data) > 0) {
            $result = array('status' => 1, "message" => 'Fetched', 'data' => $select_data);
            return response()->json($select_data);
        } else {
            $res = array('status' => 0, "message" => 'Fetch Failed');
            return response()->json($res);
        }
    }

}
