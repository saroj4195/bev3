<?php

namespace App\Http\Controllers\Extranetv4\invrateupdatecontrollersnew;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Inventory;
use App\LogTable;
use App\RatePlanLog;
use App\RateUpdateLog;
use App\DerivedPlan;
use App\MasterRoomType;
use App\MetaSearchEngineSetting;
use App\MasterHotelRatePlan;
use App\BaseRate;
use App\DynamicPricingCurrentInventory;
use App\DynamicPricingCurrentInventoryBe;
use App\Coupons;
use App\CurrentRate;
use App\CurrentRateBe;
use App\CmOtaRoomTypeSynchronize;
use App\Model\Commonmodel;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\Extranetv4\invrateupdatecontrollers\GetDataForRateController;
use App\Http\Controllers\Extranetv4\invrateupdatecontrollers\GetDataForInventoryController;
use App\Model\GoogleHotelAdsXML;
use App\HotelInformation;
use App\MasterRatePlan;

class BookingEngineInvRateController extends Controller
{
    protected $ipService, $getdata_curlreq, $getDataForInventory;
    public function __construct(IpAddressService $ipService, GetDataForRateController $getdata_curlreq, GetDataForInventoryController $getDataForInventory)
    {
        $this->ipService = $ipService;
        $this->getdata_curlreq = $getdata_curlreq;
        $this->getDataForInventory = $getDataForInventory;
    }

    //Added by Jigyans dt : - 17-04-2023
    //block inventory in booking engine
    public function unblockInvForDateRangeNew(Request $request)
    {
        try {
        $data = $request->all();
        $room_type_id = $data['room_type_id'];
        $date_from = date('Y-m-d', strtotime($data['date_from']));
        $date_to = date('Y-m-d', strtotime($data['date_to']));
        $imp_data = $this->impDate($data, $request); //used to get user id and client ip.
        $client_ip = $imp_data['client_ip'];
        $user_id = $imp_data['user_id'];
        $hotel_id = $data['hotel_id'];

        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        if ($diff > 366) {
            $check_google_hotel = $this->googleHotelStatus($data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 366 days', 'ota_name' => 'bookingjini');
            }
        }

        $logModel = new LogTable();
        $inventory = new Inventory();

        $get_inventory_url = "https://cm.bookingjini.com/extranetv4/getInventory_new/" . $hotel_id . "/" . $date_from . "/" . $date_to . "/" . $room_type_id ;
        $get_inventory_datas = Commonmodel::curlGet($get_inventory_url);
        // print_r($get_inventory_datas);exit;

        $be_all_inventory_datas = array();
        if (isset($get_inventory_datas->status)) {
            if ($get_inventory_datas->status == 1) {
                if (count($get_inventory_datas->data) > 0) {
                    $get_inventory_datas = collect($get_inventory_datas->data)->where("ota_name", "Bookingjini")->values()->all();
                    if(count($get_inventory_datas) > 0)
                    {
                        $get_inventory_datas = collect($get_inventory_datas[0]->inv)->where("room_type_id",$room_type_id)->values();
                        if(count($get_inventory_datas->all()) > 0)
                        {
                            $be_all_inventory_datas = json_decode(json_encode($get_inventory_datas->all()),true);
                        }
                    }
                } 
            } 
        }

        if(count($be_all_inventory_datas) == 0)
        {
            while (strtotime($date_from) <= strtotime($date_to)) {
                $array = $date_from;
                $bulk_data = array(
                    "room_type_id"  => $room_type_id,
                    "date"          => $array,
                    "no_of_rooms"   => 0,
                    "block_status"  => 0,
                    "los"      => 0
                );
                
                array_push($be_all_inventory_datas, $bulk_data);
                $date_from = date("Y-m-d", strtotime("+1 days", strtotime($date_from)));
            }
        }

        $inventory_get_last_id = [];
        $unblock_inv = [];
        $inv_unblock_datas = [];
        foreach($be_all_inventory_datas as $RsBeAllInvDatas)
        {
            $unblock_inv = [
                'hotel_id'              => $hotel_id,
                'room_type_id'          => $room_type_id,
                'no_of_rooms'           => $RsBeAllInvDatas['no_of_rooms'],
                'date_from'             => $RsBeAllInvDatas['date'],
                'date_to'               => $RsBeAllInvDatas['date'],
                'client_ip'             => $client_ip,
                'user_id'               => $user_id,
                'block_status'          => 0,
                'los'                   => $RsBeAllInvDatas['los'],
                'multiple_days'         => '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}'
            ];
            $inv_unblock_datas[] = $unblock_inv;
            $inventory_get_last_id[] = $inventory->insertGetId($unblock_inv);
        }

        if(count($inventory_get_last_id) > 0)
        {
            if ($this->googleHotelStatus($hotel_id) == true) {
                $update = $this->inventoryUnBlockDateRangeToGoogleAds($hotel_id, $room_type_id, 0, $date_from,$date_to);
                if($update['status'] == 0)
                {
                    return  $return_resp = array('status' => 0, 'response_msg' => $update['response_msg'], 'ota_name' => 'Bookingjini');
                }
            }

            $current_inv = [];
            $compare_inv = [];

            foreach($inv_unblock_datas as $RsInvUnblockDatas)
            {
                $current_inv = array(
                    "hotel_id"      => $hotel_id,
                    "room_type_id"  => $room_type_id,
                    "ota_id"        => -1,
                    "stay_day"      => $RsInvUnblockDatas['date_from'],
                    "no_of_rooms"      => $RsInvUnblockDatas['no_of_rooms'],
                    "block_status"  => 0,
                    "los"      => $RsInvUnblockDatas['los'],
                    "ota_name"      => "Bookingjini"
                );

                $compare_inv = array(
                    'hotel_id' => $hotel_id,
                    'room_type_id' => $room_type_id,
                    'ota_id' => -1,
                    'stay_day' => $RsInvUnblockDatas['date_from']
                );
                
                DynamicPricingCurrentInventory::updateOrInsert($compare_inv, $current_inv);
                DynamicPricingCurrentInventoryBe::updateOrInsert($compare_inv, $current_inv);
            }

            $log_data               = [
                "action_id"          => 4,
                "hotel_id"           => $data['hotel_id'],
                "ota_id"               => 0,
                "inventory_ref_id"   => implode(",",$inventory_get_last_id),
                "user_id"            => $user_id,
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"             =>  1,
                "ip"                 => $client_ip,
                "comment"    => "Processing for update "
            ];
            $logModel->fill($log_data)->save();
            return  $return_resp = array('status' => 1, 'response_msg' => 'Inventory un-blocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }
        else{
            return  $return_resp = array('status' => 0, 'response_msg' => 'Inventory un-block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }

        } catch (\Exception $e) {
            $res = array('status' => -1, 'response_msg' => $e->getMessage());

            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => array("method" => $request->method(), "request" => $request->all()));

            $result = json_encode($result);
            $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);
            return response()->json($res);
        }
    }


    //Added by Jigyans dt : - 17-04-2023
    //block inventory in booking engine
    public function blockInvForDateRangeNew(Request $request)
    {
        try {
        $data = $request->all();
        $room_type_id = $data['room_type_id'];
        $date_from = date('Y-m-d', strtotime($data['date_from']));
        $date_to = date('Y-m-d', strtotime($data['date_to']));
        $imp_data = $this->impDate($data, $request); //used to get user id and client ip.
        $client_ip = $imp_data['client_ip'];
        $user_id = $imp_data['user_id'];
        $hotel_id = $data['hotel_id'];

        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        if ($diff > 366) {
            $check_google_hotel = $this->googleHotelStatus($data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 366 days', 'ota_name' => 'bookingjini');
            }
        }

        $logModel = new LogTable();
        $inventory = new Inventory();

        $block_inv = [
            'hotel_id'              => $hotel_id,
            'room_type_id'          => $room_type_id,
            'no_of_rooms'           => 0,
            'date_from'             => $date_from,
            'date_to'               => $date_to,
            'client_ip'             => $client_ip,
            'user_id'               => $user_id,
            'block_status'          => 1,
            'los'                   => 1,
            'multiple_days'         => '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}'
        ];

        $inventory_get_last_id = $inventory->insertGetId($block_inv);

        if(is_int($inventory_get_last_id))
        {
            if ($this->googleHotelStatus($hotel_id) == true) {
                $update = $this->inventoryBlockDateRangeToGoogleAds($hotel_id, $room_type_id, 0, $date_from,$date_to);
                if($update['status'] == 0)
                {
                    return  $return_resp = array('status' => 0, 'response_msg' => $update['response_msg'], 'ota_name' => 'Bookingjini');
                }
            }

            $current_inv = [];
            $compare_inv = [];
            while (strtotime($date_from) <= strtotime($date_to)) {
                $array = $date_from;
                $current_inv = array(
                    "hotel_id"      => $hotel_id,
                    "room_type_id"  => $room_type_id,
                    "ota_id"        => -1,
                    "stay_day"      => $array,
                    "block_status"  => 1,
                    "ota_name"      => "Bookingjini"
                );

                $compare_inv = array(
                    'hotel_id' => $hotel_id,
                    'room_type_id' => $room_type_id,
                    'ota_id' => -1,
                    'stay_day' => $array
                );
                
                DynamicPricingCurrentInventory::updateOrInsert($compare_inv, $current_inv);
                DynamicPricingCurrentInventoryBe::updateOrInsert($compare_inv, $current_inv);
                $date_from = date("Y-m-d", strtotime("+1 days", strtotime($date_from)));
            }
            $log_data               = [
                "action_id"          => 4,
                "hotel_id"           => $data['hotel_id'],
                "ota_id"               => 0,
                "inventory_ref_id"   => $inventory_get_last_id,
                "user_id"            => $user_id,
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"             =>  1,
                "ip"                 => $client_ip,
                "comment"    => "Processing for update "
            ];
            $logModel->fill($log_data)->save();
            return  $return_resp = array('status' => 1, 'response_msg' => 'Inventory blocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }
        else{
            return  $return_resp = array('status' => 0, 'response_msg' => 'Inventory block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }

        } catch (\Exception $e) {
            $res = array('status' => -1, 'response_msg' => $e->getMessage());

            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => array("method" => $request->method(), "request" => $request->all()));

            $result = json_encode($result);
            $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);
            return response()->json($res);
        }
    }

    //Modified by Jigyans dt : - 19-05-2023
    public function sycInventoryUpdateNew(Request $request)
    {
        try{
            $data = $request->all();
            $hotel_inventory = new Inventory();
            $hotel_id = $data['hotel_id'];
            $room_type_id = (int)($data['room_type_id']);
            $from_date = date('Y-m-d', strtotime($data['from_date']));
            $duration = $data['duration'] - 1;
            $to_date = date('Y-m-d', strtotime($from_date . "+" . $duration . "days"));
            $source_ota_name = $data['source_ota_name'];
            
            $get_inventory_url = "https://cm.bookingjini.com/extranetv4/getInventory_new/" . $hotel_id . "/" . $from_date . "/" . $to_date . "/" . $room_type_id ;
            $get_inventory_datas = Commonmodel::curlGet($get_inventory_url);
            $imp_data = $this->impDate($data, $request);
            $check_blocked_inventory_datas = 0;
            $be_blocked_inventory_datas = array();
            $be_all_inventory_datas = array();
            $be_update_inventory_datas = array();
            if (isset($get_inventory_datas->status)) {
                if ($get_inventory_datas->status == 1) {
                    if (count($get_inventory_datas->data) > 0) {
                        $get_inventory_datas = collect($get_inventory_datas->data)->where("ota_name", $source_ota_name)->values()->all();
                        if(count($get_inventory_datas) > 0)
                        {
                            $get_inventory_datas = collect($get_inventory_datas[0]->inv)->where("room_type_id",$room_type_id)->values();
                            if(count($get_inventory_datas->all()) > 0)
                            {
                                $be_all_inventory_datas = json_decode(json_encode($get_inventory_datas->all()),true);
                                $be_blocked_inventory_datas =  json_decode(json_encode($get_inventory_datas->where("block_status",1)->values()->all()),true);
                                $be_update_inventory_datas =  json_decode(json_encode($get_inventory_datas->where("block_status",0)->values()->all()),true);
                            }
                        }
                    } 
                } 
            }

            
            // $inv_date_range = $this->getDataForInventory->getinventorydataForUpdate($be_update_inventory_datas, $hotel_id,-1);
            // print_r($inv_date_range);exit;

            if(count($be_update_inventory_datas) > 0)
            {
                if ($this->googleHotelStatus($hotel_id) == true) {
                    $update = $this->roomTypeWiseinventoryUpdateToGoogleAds($be_update_inventory_datas,$hotel_id);
                    if($update['status'] == 0)
                    {
                       $return_resp = array('status' => 0, 'response_msg' => $update['response_msg'], 'ota_name' => 'Bookingjini');
                        return response()->json(array($return_resp));
                    }
                    elseif($update['status'] == 1)
                    {
                       $los_update = $this->roomTypeWiselosUpdateToGoogleAds($be_update_inventory_datas,$hotel_id);
                    }   
                }
            }

            if(count($be_blocked_inventory_datas) > 0)
            {
                if ($this->googleHotelStatus($hotel_id) == true) {
                    $update = $this->inventoryBlockSyncToGoogleAds($be_blocked_inventory_datas,$hotel_id);
                    if($update['status'] == 0)
                    {
                        $return_resp = array('status' => 0, 'response_msg' => $update['response_msg'], 'ota_name' => 'Bookingjini');
                        return response()->json(array($return_resp));
                    }
                }
            }



            $invs = array();
            $inventorys = 0;
            
            foreach($be_all_inventory_datas as $RasInventoryDatas)
            {
                $invs[] = array(
                    "hotel_id"      => $hotel_id,
                    "room_type_id"  => $room_type_id,
                    "no_of_rooms"        => $RasInventoryDatas['no_of_rooms'],
                    "date_from"        => $RasInventoryDatas['date'],
                    "date_to"        => $RasInventoryDatas['date'],
                    "block_status"      => $RasInventoryDatas["block_status"],
                    "los"      => $RasInventoryDatas["los"],
                    "user_id"  => $imp_data["user_id"],
                    "client_ip"   => $imp_data["client_ip"],
                    "multiple_days" => '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}'
                );

                $current_inv = array(
                    "hotel_id"      =>$hotel_id,
                    "room_type_id"  => $room_type_id,
                    "ota_id"        => -1,
                    "stay_day"      => $RasInventoryDatas['date'],
                    "block_status"  => $RasInventoryDatas["block_status"],
                    "no_of_rooms"   => $RasInventoryDatas['no_of_rooms'],
                    "los"  => $RasInventoryDatas["los"],
                    "ota_name"      => "Bookingjini"
                );
                $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                    [
                        'hotel_id' => $hotel_id,
                        'room_type_id' =>  $room_type_id,
                        'ota_id' => -1,
                        'stay_day' => $RasInventoryDatas['date'],
                    ],
                    $current_inv
                );  
                
                $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                    [
                        'hotel_id' => $hotel_id,
                        'room_type_id' =>  $room_type_id,
                        'ota_id' => -1,
                        'stay_day' => $RasInventoryDatas['date'],
                    ],
                    $current_inv
                );
            }
        
            // $end_curr_time = microtime(true);
            // $execution_curr_time = ($end_curr_time - $start_currinventory_time);
            // print_r(array("dynamicpricing" =>$execution_curr_time));

            
            // $start_inventory_time = microtime(true);

            $inventorys = $hotel_inventory->insert($invs);
            if($inventorys == 1)
            {
                $resp = array('status' => 1, 'response_msg' => 'Inventory update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                return response()->json(array($resp));
            }
            else
            {
                $resp = array('status' => 0, 'response_msg' => 'Inventory was not able to update in Booking engine', 'ota_name' => 'Bookingjini');
                return response()->json(array($resp));

            }

            // $end_inventory_time = microtime(true);
            // $execution_inventory_time = ($end_inventory_time - $start_inventory_time);
            // print_r(array("inventory_table_time" =>$execution_inventory_time));

            // print_r($inventorys."@@@@@@@@@@");exit;
            
            // $data = ["status" => 1, "ota_name" => "BookingEngine", "response_msg" => "Insert data successfull"];

        } catch (\Exception $e) {
            $res = array('status' => -1, 'response_msg' => $e->getMessage());

            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => array("method" => $request->method(), "request" => $request->all()));

            // $result = json_encode($result);
            // $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);

            return response()->json($result);
        }
    }

    //Added by Jigyans dt : - 04-04-2023
    public function bulkInvUpdateNew(Request $request)
    {
        $hotel_inventory = new Inventory();
        $logModel = new LogTable();
    
        $data = $request->all();
    
        $date1 = date_create($data['date_from']);
        $date2 = date_create($data['date_to']);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        if ($diff > 366) {
            $check_google_hotel = $this->googleHotelStatus($data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 366 days', 'ota_name' => 'bookingjini');
            }
        }
        
        $imp_data                       = $this->impDate($data, $request);
        $bulk_data['hotel_id']          = $data['hotel_id'];
        $bulk_data['room_type_id']      = $data['room_type_id'];
        $bulk_data['no_of_rooms']       = $data['no_of_rooms'];
        $bulk_data['los']               = $data['los'];
        $bulk_data['block_status']      = $data['block_status'];
        $bulk_data['date_from']         = date('Y-m-d', strtotime($data['date_from']));
        $bulk_data['date_to']           = date('Y-m-d', strtotime($data['date_to']));
        $bulk_data['multiple_days']     = '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
        $bulk_data["client_ip"]         = $imp_data['client_ip'];
        $bulk_data["user_id"]           = $imp_data['user_id'];
        
        $inv_date_range_array = [];

        $inv_date_range = $this->getDataForInventory->getinventorydata($bulk_data, 'Bookingjini', '-1');
        $inv_start_date_range = $inv_date_range['start_date_info'];
        $inv_end_date_range = $inv_date_range['end_date_info'];
        if ($inv_start_date_range) {
            foreach ($inv_start_date_range as $key => $inv_date) {
                $bulk_data['date_from'] = date('Y-m-d', strtotime($inv_date));
                $bulk_data['date_to'] = date('Y-m-d', strtotime($inv_end_date_range[$key]));
                array_push($inv_date_range_array, $bulk_data);
            }
        }
        $last_inserted_id = [];
        if(count($inv_date_range_array) > 0)
        {
            foreach($inv_date_range_array as $Rsinvdaterange)
            {
                $last_inserted_id[] = $hotel_inventory->insertGetId($Rsinvdaterange);
            }
            
            if (count($last_inserted_id) > 0) {
    
                if ($this->googleHotelStatus($bulk_data['hotel_id']) == true) {
                    $update = $this->inventoryUpdateToGoogleAdsNew($bulk_data['hotel_id'], $bulk_data['room_type_id'], $bulk_data['no_of_rooms'], $inv_start_date_range,$inv_end_date_range);
                    // print_r($update);exit;
                    if($update['status'] == 1)
                    {
                        $updatecurrentinv = [];
                        foreach ($inv_start_date_range as $key => $inv_date) {
                            $start_date = $inv_date;
                            $end_date = date('Y-m-d', strtotime($inv_end_date_range[$key]));
                            $updatecurrentinv[] = $this->getDataForInventory->updateDataToCurrentInventory($data, 'Bookingjini', $start_date, $end_date);
                        }
                        
                    }
                    else
                    {
                        $resp = array('status' => 0, 'response_msg' => $update['response_msg'], 'ota_name' => 'Bookingjini');
                        return response()->json($resp);
                    }
                }
                else
                {
                    $updatecurrentinv = [];
                    foreach ($inv_start_date_range as $key => $inv_date) {
                    $start_date = $inv_date;
                    $end_date = date('Y-m-d', strtotime($inv_end_date_range[$key]));
                    $updatecurrentinv[] = $this->getDataForInventory->updateDataToCurrentInventory($data, 'Bookingjini', $start_date, $end_date);
                    }
                }

                $log_data               = [
                    "action_id"          => 4,
                    "hotel_id"           => $imp_data['hotel_id'],
                    "ota_id"               => 0,
                    "inventory_ref_id"    => implode(",",$last_inserted_id),
                    "user_id"            => $imp_data['user_id'],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"             =>  1,
                    "ip"                 => $imp_data['client_ip'],
                    "comment"    => "Processing for update "
                ];
                $logModel->fill($log_data)->save();

                if (in_array(1, $updatecurrentinv))
                {
                    $resp = array('status' => 1, 'response_msg' => 'inventory update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                    return response()->json($resp);
                }
                else
                {
                    $resp = array('status' => 0, 'response_msg' => 'inventory update failed in Booking engine', 'ota_name' => 'Bookingjini');
                    return response()->json($resp);
                }
            }
            else
            {
                $resp = array('status' => 0, 'response_msg' => 'inventory update failed in Booking engine', 'ota_name' => 'Bookingjini');
                return response()->json($resp);
            }
        }else
        {
            $resp = array('status' => 0, 'response_msg' => 'inventory update failed in Booking engine', 'ota_name' => 'Bookingjini');
            return response()->json($resp);
        }
        
    }

    public function inventoryUpdateToGoogleAdsNew($hotel_id, $room_type_id, $no_of_rooms, $inv_start_date_range,$inv_end_date_range)
    {
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelInvCountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                            EchoToken="' . $id . '"
                                            TimeStamp="' . $time . '"
                                            Version="3.0">
                <POS>
                    <Source>
                    <RequestorID ID="bookingjini_ari"/>
                    </Source>
                </POS>
                <Inventories HotelCode="' . $hotel_id . '">';
                
        // $from_date = date('Y-m-d', strtotime($from_date));
        // $to_date = date('Y-m-d', strtotime($to_date));
        $counter = 0;
        foreach($inv_start_date_range as $RsStartDate)
        {
            $xml_data .=   '<Inventory>
            <StatusApplicationControl Start="' . $RsStartDate . '"
                                        End="' . $inv_end_date_range[$counter] . '"
                                        InvTypeCode="' . $room_type_id . '"/>
            <InvCounts>
                <InvCount Count="' . $no_of_rooms . '" CountType="2"/>
            </InvCounts>
            </Inventory>';
            $counter++;
        }
        

        $xml_data .=   '</Inventories>
        </OTA_HotelInvCountNotifRQ>';
        // print_r($xml_data);exit;
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_inv_count_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resps = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);
        // print_r($google_resp);exit;
        if (isset($google_resp["Success"])) {
            // if ($no_of_rooms == 0) {
            //     $getRatePlans = MasterHotelRatePlan::select('rate_plan_id')
            //         ->where('hotel_id', $hotel_id)
            //         ->where('room_type_id', $room_type_id)
            //         ->get();
            //     if (!empty($getRatePlans)) {
            //         $rate_resp = array();
            //         $multiple_days = '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
            //         foreach ($getRatePlans as $rate_id) {
            //             $rate_resp[] = $this->rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_id->rate_plan_id, $from_date, $to_date, 0, $multiple_days);
            //         }
            //     }
            // }
            $resp = array('status' => 1, 'response_msg' => 'inventory updation successfully');
            return $resp;
        } else {
            $resp = array('status' => 0, 'response_msg' => $google_resps);
            return $resp;
        }
    }

    //Added by Jigyans dt : - 18-04-2023
    public function inventoryUnBlockDateRangeToGoogleAds($hotel_id, $room_type_id, $no_of_rooms, $inv_start_date_range,$inv_end_date_range)
    {
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $rate_plan_details = MasterRatePlan::where("hotel_id",$hotel_id)->get();
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
                        <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                        EchoToken="' . $id . '"
                                                        TimeStamp="' . $time . '"
                                                        Version="3.0">
                        <AvailStatusMessages HotelCode="' . $hotel_id . '">';
                        if(count($rate_plan_details) > 0)
                        {
                            foreach($rate_plan_details as $RsRatePlanDetails)
                            {
                                $xml_data .= '<AvailStatusMessage>
                                <StatusApplicationControl Start="' . $inv_start_date_range . '"
                                                            End="' . $inv_end_date_range . '"
                                                            InvTypeCode="' . $room_type_id . '"
                                                            RatePlanCode="' . $RsRatePlanDetails->rate_plan_id . '"/>
                                    <RestrictionStatus Status="Open" Restriction="Master"/>
                                    </AvailStatusMessage>';
                            }
                        }
                $xml_data .= '</AvailStatusMessages>
                </OTA_HotelAvailNotifRQ>';
                            
                        // print_r($xml_data);exit;
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resps = curl_exec($ch);
        curl_close($ch);
        // $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);
        // print_r($google_resp);exit;

        $xmlString = $google_resps;

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlString);

        $error_count = 0;
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $error_count++;
            }
        }
        
        if ($error_count == 0) {
            $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);
            if(isset($google_resp["Success"]))
            {
                $resp = array('status' => 1, 'response_msg' => 'inventory updation successfully');
                return $resp;
            }
            else
            {  
                $resp = array('status' => 0, 'response_msg' => $xmlString);
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => $xmlString);
            return $resp;
        }
    }
 

    //Added by Jigyans dt : - 20-03-2023
    public function bulkRateUpdateWithoutDerivedRatePlan(Request $request)
    {
        $data = $request->getContent();
        if ($this->isJSON($data)) {
            $data = json_decode($data, true);
        } else {
            $data = $request->all();
        }

        if (!is_array($data['multiple_occupancy'])) {
            $data['multiple_occupancy'] = json_decode($data['multiple_occupancy'], true);
        }
        if (!is_array($data['multiple_days'])) {
            $data['multiple_days'] = json_decode($data['multiple_days'], true);
        }
        if (!is_array($data['ota_id'])) {
            $data['ota_id'] = json_decode($data['ota_id'], true);
        }

        $imp_data = $this->impDate($data, $request);

        $date1 = date_create($data['from_date']);
        $date2 = date_create($data['to_date']);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        if ($diff > 366) {
            $check_google_hotel = $this->googleHotelStatus($imp_data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 366 days', 'ota_name' => 'bookingjini');
            }
        }
        // }

        $price = MasterHotelRatePlan::select('min_price', 'max_price')->where('hotel_id', $imp_data['hotel_id'])->where('room_type_id', $data["room_type_id"])->where('rate_plan_id', $data["rate_plan_id"])->orderBy('room_rate_plan_id', 'DESC')->first();
        $bp = 0;
        $mp = 0;
        if ($data['bar_price'] >= $price->min_price && $data['bar_price'] < $price->max_price) {
            $bp = 1;
        }
        if ($bp == 0) {
            $res = array('status' => 0, 'response_msg' => "price should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price);
            return response()->json($res);
        }

        if (sizeof($data['multiple_occupancy']) == 0) {
            $data['multiple_occupancy'][0] = $data['bar_price'];
        }
        $multi_price = $data['multiple_occupancy'];
        if (sizeof($multi_price) > 0) {
            foreach ($multi_price as $key => $multiprice) {
                $mp = 0;
                if ($multiprice == 0 || $multiprice == '') {
                    $data['multiple_occupancy'][$key] = $data['bar_price'];
                }
                if ($multiprice >= $price->min_price && $multiprice < $price->max_price) {
                    $mp = $mp + 1;
                }
            }
        }
        if ($mp == 0) {
            $res = array('status' => 0, 'response_msg' => "multiple occupancy should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price);
            return response()->json($res);
        }
        $data['from_date'] = date('Y-m-d', strtotime($data['from_date']));
        $data['to_date'] = date('Y-m-d', strtotime($data['to_date']));

        $response = $this->bulkBeOtaPushNew($imp_data, $data);
        return response()->json($response);
    }

    //Added by Jigyans dt : - 20-03-2023
    public function bulkRateUpdate(Request $request)
    {
        try{
            $data = $request->getContent();
            if ($this->isJSON($data)) {
                $data = json_decode($data, true);
            } else {
                $data = $request->all();
            }

            if (!is_array($data['multiple_occupancy'])) {
                $data['multiple_occupancy'] = json_decode($data['multiple_occupancy'], true);
            }
            if (!is_array($data['multiple_days'])) {
                $data['multiple_days'] = json_decode($data['multiple_days'], true);
            }
            if (!is_array($data['ota_id'])) {
                $data['ota_id'] = json_decode($data['ota_id'], true);
            }

            $imp_data = $this->impDate($data, $request); //used to get user id and client ip.

            // if($imp_data['hotel_id'] == 1953){
            $date1 = date_create($data['from_date']);
            $date2 = date_create($data['to_date']);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");

            if ($diff > 366) {
                $check_google_hotel = $this->googleHotelStatus($imp_data['hotel_id']);
                if ($check_google_hotel) {
                    return array('status' => 0, 'message' => 'Date range must be less than or equal to 366 days', 'ota_name' => 'bookingjini', 'ota_name' => 'Bookingjini');
                }
            }
            // }

            $price = MasterHotelRatePlan::select('min_price', 'max_price')->where('hotel_id', $imp_data['hotel_id'])->where('room_type_id', $data["room_type_id"])->where('rate_plan_id', $data["rate_plan_id"])->orderBy('room_rate_plan_id', 'DESC')->first();
            $bp = 0;
            $mp = 0;
            if ($data['bar_price'] >= $price->min_price && $data['bar_price'] < $price->max_price) {
                $bp = 1;
            }
            if ($bp == 0) {
                $res = array('status' => 0, 'message' => "price should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price , 'ota_name' => 'Bookingjini');
                return response()->json($res);
            }

            if (sizeof($data['multiple_occupancy']) == 0) {
                $data['multiple_occupancy'][0] = $data['bar_price'];
            }
            $multi_price = $data['multiple_occupancy'];
            if (sizeof($multi_price) > 0) {
                $mp = 0;
                foreach ($multi_price as $key => $multiprice) {
                    if ($multiprice == 0 || $multiprice == '') {
                        $data['multiple_occupancy'][$key] = $data['bar_price'];
                    }
                    if ($multiprice >= $price->min_price && $multiprice < $price->max_price) {
                        $mp = $mp + 1;
                    }
                }
            }

            if ($mp == 0) {
                $res = array('status' => 0, 'message' => "multiple occupancy should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price , 'ota_name' => 'Bookingjini');
                return response()->json($res);
            }
            $data['from_date'] = date('Y-m-d', strtotime($data['from_date']));
            $data['to_date'] = date('Y-m-d', strtotime($data['to_date']));
            $conds = array('hotel_id' => $data['hotel_id'], 'derived_room_type_id' => $data['room_type_id'], 'derived_rate_plan_id' => $data['rate_plan_id']);
            $chek_parents = DerivedPlan::select('*')->where($conds)->get();
            if (sizeof($chek_parents) > 0) {
                if ($data['extra_adult_price'] == 0 || $data['extra_adult_price'] == "") {
                    $data['extra_adult_price'] = $this->getExtraAdultChildPrice($data, 1);
                }
                if ($data['extra_child_price'] == 0 || $data['extra_child_price'] == "") {
                    $data['extra_child_price'] = $this->getExtraAdultChildPrice($data, 2);
                }

                $response = $this->bulkBeOtaPushNew($imp_data, $data);
                $bar_price = $data['bar_price'];
                $extra_adult_price = $data['extra_adult_price'];
                $extra_child_price = $data['extra_child_price'];
                $multiple_occupancy_array = $data['multiple_occupancy'];
                foreach ($chek_parents as $details) {
                    $multiple_occupancy = array();
                    $getPrice = explode(",", $details->amount_type);
                    $indexSize =  sizeof($getPrice) - 1;

                    if ($details->select_type == 'percentage') {
                        $percentage_price = ($bar_price * $getPrice[$indexSize]) / 100;
                        $data['bar_price'] = round($bar_price + $percentage_price);
                        foreach ($multiple_occupancy_array as $key => $multi) {
                            $multi_per_price = ($multi * $getPrice[$key]) / 100;
                            $multiple_occupancy[] = (string)round($multi + $multi_per_price);
                        }
                        $data['multiple_occupancy'] = $multiple_occupancy;
                    } else {
                        $data['bar_price'] = round($bar_price + $getPrice[$indexSize]);
                        foreach ($multiple_occupancy_array as $key => $multi) {
                            $multiple_occupancy[] = (string)round($multi + $getPrice[$key]);
                        }
                        $data['multiple_occupancy'] = $multiple_occupancy;
                    }
                    if ($details->extra_adult_select_type == 'percentage') {
                        $percentage_price = ($extra_adult_price * $details->extra_adult_amount) / 100;
                        $data['extra_adult_price'] = round($extra_adult_price + $percentage_price);
                    } else {
                        $data['extra_adult_price'] = round($extra_adult_price + $details->extra_adult_amount);
                    }
                    if ($details->extra_child_select_type == 'percentage') {
                        $percentage_price = ($extra_child_price * $details->extra_child_amount) / 100;
                        $data['extra_child_price'] = round($extra_child_price + $percentage_price);
                    } else {
                        $data['extra_child_price'] = round($extra_child_price + $details->extra_child_amount);
                    }
                    $data['room_type_id'] = $details->room_type_id;
                    $data['rate_plan_id'] = $details->rate_plan_id;
                    $response = $this->bulkBeOtaPushNew($imp_data, $data);
                }
            } else {
                $response = $this->bulkBeOtaPushNew($imp_data, $data);
            }
            return response()->json($response);
        } catch (\Exception $e) {
            $res = array('status' => -1, 'response_msg' => $e->getMessage());

            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => date("YmdHis"));

            $result = json_encode($result);
            $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);

            $logpath = storage_path("logs/jigyansratesnew.log".date("Y-m-d"));
            $logfile = fopen($logpath, "a+");
            fwrite($logfile,"\n\n\n".date("YmdHis").json_encode($request->all())."\n\n\n");
            fclose($logfile);

            return response()->json($res);
        }
    }

    public function bulkBeOtaPush($imp_data, $data)
    {
        $rate_plan_log = new RatePlanLog();
        $logModel      = new RateUpdateLog();
        $base_rate     = new BaseRate();
        $resp = array();

        foreach ($imp_data['ota_id'] as $otaid) {
            if ($otaid == -1) {
                $data["client_ip"] = $imp_data['client_ip'];
                $data["user_id"] = $imp_data['user_id'];
                $be_opt = $data;
                $be_opt['multiple_days'] = json_encode($be_opt['multiple_days']);
                $be_opt['multiple_occupancy'] = json_encode($be_opt['multiple_occupancy']);
                if ($rate_plan_log->fill($be_opt)->save()) {
                    if (!isset($imp_data['dp_status'])) {
                        $insertBaseRate = $base_rate->fill($be_opt)->save();
                    }
                    if ($this->googleHotelStatus($data['hotel_id'])) {
                        $from_date = date('Y-m-d', strtotime($be_opt['from_date']));
                        $to_date = date('Y-m-d', strtotime($be_opt['to_date']));
                        $p_start = $from_date;
                        $p_end = $to_date;
                        $period     = new \DatePeriod(
                            new \DateTime($p_start),
                            new \DateInterval('P1D'),
                            new \DateTime($p_end)
                        );
                        foreach ($period as $key1 => $value) {
                            $index = $value->format('Y-m-d');
                            $hotel_id = $be_opt['hotel_id'];
                            $room_type_id = $be_opt['room_type_id'];
                            $check_coupon = Coupons::select('discount')
                                ->where(["hotel_id" => $hotel_id, "room_type_id" => $room_type_id, 'coupon_for' => 1])
                                ->where('valid_from', '<=', $index)
                                ->where('valid_to', '>=', $index)
                                ->orWhere(function ($query) use ($hotel_id, $index) {
                                    $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                                        ->where('valid_from', '<=', $index)
                                        ->where('valid_to', '>=', $index);
                                })
                                ->orderBy('coupon_id', 'DESC')
                                ->first();
                            if ($check_coupon) {
                                $discount_per = $check_coupon->discount;
                                $discount_amt = round($be_opt['bar_price'] * $discount_per / 100);
                                $price = $be_opt['bar_price'] - $discount_amt;

                                $rate_update = $this->rateUpdateToGoogleAds($be_opt['hotel_id'], $be_opt['room_type_id'], $be_opt['rate_plan_id'], $index, $index, $price, $be_opt['multiple_days']);
                            } else {
                                $rate_update = $this->rateUpdateToGoogleAds($be_opt['hotel_id'], $be_opt['room_type_id'], $be_opt['rate_plan_id'], $index, $index, $be_opt['bar_price'], $be_opt['multiple_days']);
                            }
                        }
                        $los_updatec = $this->losUpdateToGoogleAds($be_opt['hotel_id'], $be_opt['room_type_id'], $be_opt['rate_plan_id'], $be_opt['from_date'], $be_opt['to_date'], $be_opt['los'], $be_opt['multiple_days']);
                    }
                    $log_data                 = [
                        "action_id"          => 2,
                        "hotel_id"           => $imp_data['hotel_id'],
                        "ota_id"             => -1,
                        "rate_ref_id"        => $rate_plan_log->rate_plan_log_id,
                        "user_id"            => $imp_data['user_id'],
                        "request_msg"        => '',
                        "response_msg"       => '',
                        "request_url"        => '',
                        "status"               => 1,
                        "ip"                   => $imp_data['client_ip'],
                        "comment"              => "Processing for update "
                    ];
                    $logModel->fill($log_data)->save(); //saving pre log data
                    $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                } else {
                    $resp = array('status' => 0, 'message' => 'rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                }
            }
        }
        return $resp;
    }

    //Added by Jigyans dt : - 21-03-2023
    public function bulkBeOtaPushNew($imp_data, $data)
    {
        $rate_plan_log = new RatePlanLog();
        $logModel      = new RateUpdateLog();
        $base_rate     = new BaseRate();
        $resp = array();
        $data['multiple_days'] = json_encode($data['multiple_days']);
        $data['multiple_occupancy'] = json_encode($data['multiple_occupancy']);
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $rate_plan_id = $data['rate_plan_id'];
        $los = $data['los'];
        $from_date = date('Y-m-d', strtotime($data['from_date']));
        $to_date = date('Y-m-d', strtotime($data['to_date']));

        $get_rates_url = "https://cm.bookingjini.com/extranetv4/getrates/" . $hotel_id . "/" . $room_type_id . "/" . $from_date . "/" . $to_date;
        $get_rates_datas = Commonmodel::curlGet($get_rates_url);

        $hotel_info = HotelInformation::where('hotel_id',$hotel_id)
        ->select('hotels_table.is_taxable')
        ->first();

        $filtered_dates = array();
        while (strtotime($from_date) <= strtotime($to_date)) {
            $array = $from_date;
            $timestamp = strtotime($array);
            $day = date('D', $timestamp);
            array_push($filtered_dates, $array);
            $from_date = date("Y-m-d", strtotime("+1 days", strtotime($from_date)));
        }
        
        $check_blocked_rates_datas = 0;
        $be_blocked_rates_datas = array();
        if (isset($get_rates_datas->status)) {
            if ($get_rates_datas->status == 1) {
                if (count($get_rates_datas->channel_rates) > 0) {
                    $get_rates_datas = collect($get_rates_datas->channel_rates)->where("ota_name", "Bookingjini")->all();
                    if(count($get_rates_datas) > 0)
                    {
                        $get_rates_datas = collect($get_rates_datas[0]->plans)->where("rate_plan_id",$rate_plan_id)->pluck("day_rates")->flatten(1)->values();
                        if(count($get_rates_datas->all()) > 0)
                        {
                            $be_blocked_rates_datas = $get_rates_datas->where("block_status",1)->pluck('rate_date')->all();
                            if(count($be_blocked_rates_datas) > 0)
                            {
                                $check_blocked_rates_datas = 1;
                                $filtered_dates = array_values(array_diff($filtered_dates,$be_blocked_rates_datas));
                            }
                        }
                    }
                } 
            } 
        } 

        $result = array();
        $be_rate_datas = array();
        if($check_blocked_rates_datas == 0)
        {
            $be_rate_datas[] = array(
                "hotel_id" => $imp_data['hotel_id'],
                "room_type_id" => $room_type_id,
                "rate_plan_id" => $rate_plan_id,
                "multiple_occupancy" => $data['multiple_occupancy'],
                "bar_price" => $data['bar_price'],
                "from_date" => date('Y-m-d', strtotime($data['from_date'])),
                "to_date" => date('Y-m-d', strtotime($data['to_date'])),
                "multiple_days" => $data['multiple_days'],
                "block_status" => 0,
                "los" => $los,
                "client_ip" => $imp_data['client_ip'],
                "user_id" => $imp_data['user_id'],
                "extra_adult_price" => $data['extra_adult_price'],
                "extra_child_price" => $data['extra_child_price']
            );

            $result[] = array(
                'from_date' => date('Y-m-d', strtotime($data['from_date'])),
                'to_date' => date('Y-m-d', strtotime($data['to_date']))
            );
        }
        else
        {
            $date_count = count($filtered_dates);

            for ($i = 0; $i < $date_count; $i++) {
                $current_date = $filtered_dates[$i];
                $from_date = $current_date;
                $to_date = $current_date;
                
                while (($i + 1) < $date_count && strtotime($filtered_dates[$i+1]) == strtotime($to_date . ' + 1 day')) {
                    $to_date = $filtered_dates[$i+1];
                    $i++;
                }
                
                $result[] = array(
                    'from_date' => $from_date,
                    'to_date' => $to_date
                );
            }
            
            foreach($result as $RsResult)
            {
                $be_rate_datas[] = array(
                    "hotel_id" => $imp_data['hotel_id'],
                    "room_type_id" => $room_type_id,
                    "rate_plan_id" => $rate_plan_id,
                    "multiple_occupancy" => $data['multiple_occupancy'],
                    "bar_price" => $data['bar_price'],
                    "from_date" => $RsResult['from_date'],
                    "to_date" => $RsResult['to_date'],
                    "multiple_days" => $data['multiple_days'],
                    "block_status" => 0,
                    "los" => $los,
                    "client_ip" => $imp_data['client_ip'],
                    "user_id" => $imp_data['user_id'],
                    "extra_adult_price" => $data['extra_adult_price'],
                    "extra_child_price" => $data['extra_child_price']
                );
            }

            foreach($be_blocked_rates_datas as $RSblockedRatesDates)
            {
                $be_rate_datas[] = array(
                    "hotel_id" => $imp_data['hotel_id'],
                    "room_type_id" => $room_type_id,
                    "rate_plan_id" => $rate_plan_id,
                    "multiple_occupancy" => $data['multiple_occupancy'],
                    "bar_price" => $data['bar_price'],
                    "from_date" => $RSblockedRatesDates,
                    "to_date" => $RSblockedRatesDates,
                    "multiple_days" => $data['multiple_days'],
                    "block_status" => 1,
                    "los" => $los,
                    "client_ip" => $imp_data['client_ip'],
                    "user_id" => $imp_data['user_id'],
                    "extra_adult_price" => $data['extra_adult_price'],
                    "extra_child_price" => $data['extra_child_price']
                );
            }
        }

        $stay_dates = $filtered_dates;
        if (count($be_rate_datas) > 0) {
            $google_hotel_status = "";

            $rate_plan_log_ids = [];
            foreach($be_rate_datas as $RsBeRateDatas)
            {
                $rate_plan_log_ids[] = $rate_plan_log->insertGetId($RsBeRateDatas);
            }
            $index = "";
            if (count($rate_plan_log_ids) > 0) {
                if (!isset($imp_data['dp_status'])) {
                    foreach ($be_rate_datas as $RsRateDatas) {
                        $RsRateDatas['bar_price'] = $data['bar_price'];
                    }
                    $insertBaseRate = $base_rate->insert($be_rate_datas);
                }
                $test_array = array(
                    "hotel_id" => $hotel_id,
                    "room_type_id" => $room_type_id,
                    "rate_plan_id" => $rate_plan_id,
                    "ota_id" => -1
                );
                $current_rates = array();
                $current_rate_data = array();
                if ($this->googleHotelStatus($data['hotel_id'])) {
                    $multiple_days = json_decode($data['multiple_days']);

                    foreach ($multiple_days as $key => $days) {
                        if ($key == 'Mon') {
                            $Mon = $days;
                        }
                        if ($key == 'Tue') {
                            $Tue = $days;
                        }
                        if ($key == 'Wed') {
                            $Weds = $days;
                        }
                        if ($key == 'Thu') {
                            $Thur = $days;
                        }
                        if ($key == 'Fri') {
                            $Fri = $days;
                        }
                        if ($key == 'Sat') {
                            $Sat = $days;
                        }
                        if ($key == 'Sun') {
                            $Sun = $days;
                        }
                    }

                    $id = uniqid();
                    $time = time();
                    $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';

                    $xml_common_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                        EchoToken="' . $id . '"
                                                        TimeStamp="' . $time . '"
                                                        Version="3.0"
                                                        NotifType="Overlay"
                                                        NotifScopeType="ProductRate">
                    <POS>
                        <Source>
                            <RequestorID ID="bookingjini_ari"/>
                        </Source>
                    </POS>';

                    $xml_los_header_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                            EchoToken="' . $id . '"
                                            TimeStamp="' . $time . '"
                                            Version="3.0">
                        <POS>
                            <Source>
                            <RequestorID ID="bookingjini_ari"/>
                            </Source>
                        </POS>';

                    $xml_data = $xml_common_data . '<RateAmountMessages HotelCode="' . $hotel_id . '">';

                    $los_xml_data = $xml_los_header_data . '<AvailStatusMessages HotelCode="' . $hotel_id . '">';

                    $price = (float) $data['bar_price'];

                    if($hotel_info->is_taxable==1){
                        
                    $getGstprice = $this->gstPrice($price);
                    }else{
                        $getGstprice = 0;
                    }

                    $totalprice = (float)($price + $getGstprice);

                    foreach($result as $Rsresult)
                    {
                        $xml_data .= '<RateAmountMessage>
                        <StatusApplicationControl Start="' . $Rsresult['from_date'] . '"
                                                        End="' . $Rsresult['to_date'] . '"
                                                        Mon="' . $Mon . '"
                                                        Tue="' . $Tue . '"
                                                        Weds="' . $Weds . '"
                                                        Thur="' . $Thur . '"
                                                        Fri="' . $Fri . '"
                                                        Sat="' . $Sat . '"
                                                        Sun="' . $Sun . '"
                                                        InvTypeCode="' . $room_type_id . '"
                                                        RatePlanCode="' . $rate_plan_id . '"/>
                                            <Rates>
                                                <Rate>
                                                    <BaseByGuestAmts>
                                                        <BaseByGuestAmt AmountBeforeTax="' . $price . '"
                                                                        AmountAfterTax="' . $totalprice . '"
                                                                        CurrencyCode="INR"
                                                                        NumberOfGuests="2"/>
                                                    </BaseByGuestAmts>
                                                </Rate>
                                            </Rates>
                                        </RateAmountMessage>';

                        $los_xml_data .= '<AvailStatusMessage>
                        <StatusApplicationControl Start="' . date('Y-m-d', strtotime($data['from_date'])) . '"
                                                    End="' . date('Y-m-d', strtotime($data['to_date'])) . '"
                                                    Mon="' . $Mon . '"
                                                    Tue="' . $Tue . '"
                                                    Weds="' . $Weds . '"
                                                    Thur="' . $Thur . '"
                                                    Fri="' . $Fri . '"
                                                    Sat="' . $Sat . '"
                                                    Sun="' . $Sun . '"
                                                    InvTypeCode="' . $room_type_id . '"
                                                    RatePlanCode="' . $rate_plan_id . '"/>
                        <LengthsOfStay>
                        <LengthOfStay Time="' . $los . '"
                                    TimeUnit="Day"
                                    MinMaxMessageType="SetMinLOS"/>
                        </LengthsOfStay>
                        </AvailStatusMessage>';
                    }

                    $los_xml_data .= '</AvailStatusMessages>
                                </OTA_HotelAvailNotifRQ>';
                    $xml_data .= '</RateAmountMessages>
                    </OTA_HotelRateAmountNotifRQ>';

                    $headers = array(
                        "Content-Type: application/xml",
                    );

                    $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_rate_amount_notif';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
                    $google_resp = curl_exec($ch);
                    curl_close($ch);
                    $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);

                    if (isset($google_resp["Success"])) {

                        $current_rate_all_datas_get = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates);
                        $check_current_rate_all_datas_get = $current_rate_all_datas_get->get();

                        if(count($check_current_rate_all_datas_get) > 0)
                        {
                            $new_rate_datas = array();
                            $update_ids = array();
                            $check_rates_datas = "";
                            foreach ($stay_dates as $RsDates) {
                                $check_rates_datas = $check_current_rate_all_datas_get->where("stay_date", $RsDates)->first();
                                if ($check_rates_datas == NULL) {
                                    $new_rate_datas[] = array(
                                        "hotel_id" => $hotel_id,
                                        "ota_id" => -1,
                                        "ota_name" => "Bookingjini",
                                        "room_type_id" => $room_type_id,
                                        "rate_plan_id" => $rate_plan_id,
                                        "stay_date" => $RsDates,
                                        "bar_price" => $data['bar_price'],
                                        "multiple_occupancy" => $data['multiple_occupancy'],
                                        "multiple_days" => $data['multiple_days'],
                                        "extra_adult_price" => $data['extra_adult_price'],
                                        "extra_child_price" => $data['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $los,
                                    );
                                } else {
                                    $update_ids[] = $check_rates_datas->id;
                                }
                            }
                            if (count($new_rate_datas) > 0) {
                                $test_current_rate = CurrentRate::insert($new_rate_datas);
                                // CurrentRateBe::insert($new_rate_datas);
                            }

                            if(count($update_ids) > 0)
                            {
                                $updateratedatas = array(
                                    "bar_price" => $data['bar_price'],
                                    "multiple_occupancy" => $data['multiple_occupancy'],
                                    "multiple_days" => $data['multiple_days'],
                                    "extra_adult_price" => $data['extra_adult_price'],
                                    "extra_child_price" => $data['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $los,
                                );
                                $testing = CurrentRate::whereIn("id", $update_ids)->update($updateratedatas);
                                // CurrentRateBe::whereIn("id", $update_ids)->update($updateratedatas);
                            }
                        }
                        else
                        {
                            $bookingjini_rates_data = [];
                            foreach($stay_dates as $RsStaydates)
                            {
                                $bookingjini_rates_data[] = array(
                                    "hotel_id" => $hotel_id,
                                    "ota_id" => -1,
                                    "ota_name" => "Bookingjini",
                                    "room_type_id" => $room_type_id,
                                    "rate_plan_id" => $rate_plan_id,
                                    "stay_date" => $RsStaydates,
                                    "bar_price" => $data['bar_price'],
                                    "multiple_occupancy" => $data['multiple_occupancy'],
                                    "multiple_days" => $data['multiple_days'],
                                    "extra_adult_price" => $data['extra_adult_price'],
                                    "extra_child_price" => $data['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $los,
                                );
                            }
                            $test_current_rate = CurrentRate::insert($bookingjini_rates_data);
                            // CurrentRateBe::insert($bookingjini_rates_data);
                        }

                        $log_data                 = [
                            "action_id"          => 2,
                            "hotel_id"           => $imp_data['hotel_id'],
                            "ota_id"             => -1,
                            "rate_ref_id"        => implode(",",$rate_plan_log_ids),
                            "user_id"            => $imp_data['user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"               => 1,
                            "ip"                   => $imp_data['client_ip'],
                            "comment"              => "Processing for update "
                        ];
                        $logModel->fill($log_data)->save(); //saving pre log data

                        foreach($stay_dates as $RsStaydates)
                        {
                            $current_rate_data = array(
                                "hotel_id" => $hotel_id,
                                "ota_id" => -1,
                                "ota_name" => "Bookingjini",
                                "room_type_id" => $room_type_id,
                                "rate_plan_id" => $rate_plan_id,
                                "stay_date" => $RsStaydates,
                                "bar_price" => $data['bar_price'],
                                "multiple_occupancy" => $data['multiple_occupancy'],
                                "multiple_days" => $data['multiple_days'],
                                "extra_adult_price" => $data['extra_adult_price'],
                                "extra_child_price" => $data['extra_child_price'],
                                "block_status" => 0,
                                "los" => $los,
                            );

                            $cond_current_rate_data                   = [
                                "hotel_id"          => $hotel_id,
                                "room_type_id"      => $room_type_id,
                                "rate_plan_id"      => $rate_plan_id,
                                "ota_id"            => '-1',
                                "stay_date"         => $RsStaydates,
                            ];
                            $cur_inv = CurrentRateBe::updateOrInsert($cond_current_rate_data, $current_rate_data);
                        }

                        $headers = array(
                            "Content-Type: application/xml",
                        );

                        $losurl =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
                        $los_ch = curl_init();
                        curl_setopt($los_ch, CURLOPT_URL, $losurl);
                        curl_setopt($los_ch, CURLOPT_POST, true);
                        curl_setopt($los_ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($los_ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($los_ch, CURLOPT_POSTFIELDS, $los_xml_data);
                        $google_los_resp = curl_exec($los_ch);
                        curl_close($los_ch);
                        $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                        return $resp;
                    } else {
                        $resp = array('status' => 0, 'message' => $google_resp, 'ota_name' => 'Bookingjini');
                        return $resp;
                    }
                } else {
                        $current_rate_all_datas_get = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates);
                        $check_current_rate_all_datas_get = $current_rate_all_datas_get->get();

                        if(count($check_current_rate_all_datas_get) > 0)
                        {
                            $new_rate_datas = array();
                            $update_ids = array();
                            $testing = "";
                            $check_rates_datas = "";
                            foreach ($stay_dates as $RsDates) {
                                $check_rates_datas = $check_current_rate_all_datas_get->where("stay_date", $RsDates)->first();
                                if ($check_rates_datas == NULL) {
                                    $new_rate_datas[] = array(
                                        "hotel_id" => $hotel_id,
                                        "ota_id" => -1,
                                        "ota_name" => "Bookingjini",
                                        "room_type_id" => $room_type_id,
                                        "rate_plan_id" => $rate_plan_id,
                                        "stay_date" => $RsDates,
                                        "bar_price" => $data['bar_price'],
                                        "multiple_occupancy" => $data['multiple_occupancy'],
                                        "multiple_days" => $data['multiple_days'],
                                        "extra_adult_price" => $data['extra_adult_price'],
                                        "extra_child_price" => $data['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $los,
                                    );
                                } else {
                                    $update_ids[] = $check_rates_datas->id;
                                }
                            }
                            if (count($new_rate_datas) > 0) {
                                $test_current_rate = CurrentRate::insert($new_rate_datas);
                                // CurrentRateBe::insert($new_rate_datas);
                            }

                            if(count($update_ids) > 0)
                            {
                                $updateratedatas = array(
                                    "bar_price" => $data['bar_price'],
                                    "multiple_occupancy" => $data['multiple_occupancy'],
                                    "multiple_days" => $data['multiple_days'],
                                    "extra_adult_price" => $data['extra_adult_price'],
                                    "extra_child_price" => $data['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $los,
                                );
                                $testing = CurrentRate::whereIn("id", $update_ids)->update($updateratedatas);
                                // CurrentRateBe::whereIn("id", $update_ids)->update($updateratedatas);
                            }
                        }
                        else
                        {
                            $bookingjini_rates_data = [];
                            foreach($stay_dates as $RsStaydates)
                            {
                                $bookingjini_rates_data[] = array(
                                    "hotel_id" => $hotel_id,
                                    "ota_id" => -1,
                                    "ota_name" => "Bookingjini",
                                    "room_type_id" => $room_type_id,
                                    "rate_plan_id" => $rate_plan_id,
                                    "stay_date" => $RsStaydates,
                                    "bar_price" => $data['bar_price'],
                                    "multiple_occupancy" => $data['multiple_occupancy'],
                                    "multiple_days" => $data['multiple_days'],
                                    "extra_adult_price" => $data['extra_adult_price'],
                                    "extra_child_price" => $data['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $los,
                                );
                            }
                            $test_current_rate = CurrentRate::insert($bookingjini_rates_data);
                            // CurrentRateBe::insert($bookingjini_rates_data);
                        }
                        foreach($stay_dates as $RsStaydates)
                        {
                            $current_rate_data = array(
                                "hotel_id" => $hotel_id,
                                "ota_id" => -1,
                                "ota_name" => "Bookingjini",
                                "room_type_id" => $room_type_id,
                                "rate_plan_id" => $rate_plan_id,
                                "stay_date" => $RsStaydates,
                                "bar_price" => $data['bar_price'],
                                "multiple_occupancy" => $data['multiple_occupancy'],
                                "multiple_days" => $data['multiple_days'],
                                "extra_adult_price" => $data['extra_adult_price'],
                                "extra_child_price" => $data['extra_child_price'],
                                "block_status" => 0,
                                "los" => $los,
                            );

                            $cond_current_rate_data                   = [
                                "hotel_id"          => $hotel_id,
                                "room_type_id"      => $room_type_id,
                                "rate_plan_id"      => $rate_plan_id,
                                "ota_id"            => '-1',
                                "stay_date"         => $RsStaydates,
                            ];
                            $cur_inv = CurrentRateBe::updateOrInsert($cond_current_rate_data, $current_rate_data);
                        }
                    $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                    return $resp;
                }
            } else {
                $resp = array('status' => 0, 'message' => 'rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'message' => 'No records found to update for this date', 'ota_name' => 'Bookingjini');
            return $resp;
        }
        return $resp;
    }

    //Added by Jigyans dt : - 20-03-2023
    public function bulkRateUpdateWithoutBlockedRates(Request $request)
    {
        $data = $request->getContent();
        if ($this->isJSON($data)) {
            $data = json_decode($data, true);
        } else {
            $data = $request->all();
        }

        if (!is_array($data['multiple_occupancy'])) {
            $data['multiple_occupancy'] = json_decode($data['multiple_occupancy'], true);
        }
        if (!is_array($data['multiple_days'])) {
            $data['multiple_days'] = json_decode($data['multiple_days'], true);
        }
        if (!is_array($data['ota_id'])) {
            $data['ota_id'] = json_decode($data['ota_id'], true);
        }

        $imp_data = $this->impDate($data, $request);

        $date1 = date_create($data['from_date']);
        $date2 = date_create($data['to_date']);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        if ($diff > 366) {
            $check_google_hotel = $this->googleHotelStatus($imp_data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 366 days', 'ota_name' => 'bookingjini');
            }
        }
        // }

        $price = MasterHotelRatePlan::select('min_price', 'max_price')->where('hotel_id', $imp_data['hotel_id'])->where('room_type_id', $data["room_type_id"])->where('rate_plan_id', $data["rate_plan_id"])->orderBy('room_rate_plan_id', 'DESC')->first();
        $bp = 0;
        $mp = 0;
        if ($data['bar_price'] >= $price->min_price && $data['bar_price'] < $price->max_price) {
            $bp = 1;
        }
        if ($bp == 0) {
            $res = array('status' => 0, 'response_msg' => "price should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price);
            return response()->json($res);
        }

        if (sizeof($data['multiple_occupancy']) == 0) {
            $data['multiple_occupancy'][0] = $data['bar_price'];
        }
        $multi_price = $data['multiple_occupancy'];
        if (sizeof($multi_price) > 0) {
            foreach ($multi_price as $key => $multiprice) {
                $mp = 0;
                if ($multiprice == 0 || $multiprice == '') {
                    $data['multiple_occupancy'][$key] = $data['bar_price'];
                }
                if ($multiprice >= $price->min_price && $multiprice < $price->max_price) {
                    $mp = $mp + 1;
                }
            }
        }
        if ($mp == 0) {
            $res = array('status' => 0, 'response_msg' => "multiple occupancy should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price);
            return response()->json($res);
        }
        $data['from_date'] = date('Y-m-d', strtotime($data['from_date']));
        $data['to_date'] = date('Y-m-d', strtotime($data['to_date']));

        $response = $this->bulkBeOtaPushNewWithoutBlockedDates($imp_data, $data);
        return response()->json($response);
    }

    //Added by Jigyans dt : - 20-03-2023
    public function bulkBeOtaPushNewWithoutBlockedDates($imp_data, $data)
    {
        $rate_plan_log = new RatePlanLog();
        $logModel      = new RateUpdateLog();
        $base_rate     = new BaseRate();
        $resp = array();
        $data['multiple_days'] = json_encode($data['multiple_days']);
        $data['multiple_occupancy'] = json_encode($data['multiple_occupancy']);
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $rate_plan_id = $data['rate_plan_id'];
        $los = $data['los'];
        $from_date = date('Y-m-d', strtotime($data['from_date']));
        $to_date = date('Y-m-d', strtotime($data['to_date']));

        $filtered_dates = array();
        while (strtotime($from_date) <= strtotime($to_date)) {
            $array = $from_date;
            $timestamp = strtotime($array);
            $day = date('D', $timestamp);
            array_push($filtered_dates, array("date" => $array, "day" => $day));
            $from_date = date("Y-m-d", strtotime("+1 days", strtotime($from_date)));
        }
        $hotel_info = HotelInformation::where('hotel_id',$hotel_id)
        ->select('hotels_table.is_taxable')
        ->first();
        $be_rate_datas = array(
            "hotel_id" => $imp_data['hotel_id'],
            "room_type_id" => $data['room_type_id'],
            "rate_plan_id" => $data['rate_plan_id'],
            "multiple_occupancy" => $data['multiple_occupancy'],
            "bar_price" => $data['bar_price'],
            "from_date" => $data['from_date'],
            "to_date" => $data['to_date'],
            "multiple_days" => $data['multiple_days'],
            "block_status" => $data['block_status'],
            "los" => $data['los'],
            "client_ip" => $imp_data['client_ip'],
            "user_id" => $imp_data['user_id'],
            "extra_adult_price" => $data['extra_adult_price'],
            "extra_child_price" => $data['extra_child_price']
        );
        $google_hotel_status = "";
        $rate_plan_log_ids = $rate_plan_log->insertGetId($be_rate_datas);
        $current_rates = array();
        if (is_int($rate_plan_log_ids)) {
            if (!isset($imp_data['dp_status'])) {
                $be_rate_datas['bar_price'] = $data['bar_price'];
                $insertBaseRate = $base_rate->insertGetId($be_rate_datas);
            }

            if ($this->googleHotelStatus($data['hotel_id'])) {
                $multiple_days = json_decode($data['multiple_days']);

                foreach ($multiple_days as $key => $days) {
                    if ($key == 'Mon') {
                        $Mon = $days;
                    }
                    if ($key == 'Tue') {
                        $Tue = $days;
                    }
                    if ($key == 'Wed') {
                        $Weds = $days;
                    }
                    if ($key == 'Thu') {
                        $Thur = $days;
                    }
                    if ($key == 'Fri') {
                        $Fri = $days;
                    }
                    if ($key == 'Sat') {
                        $Sat = $days;
                    }
                    if ($key == 'Sun') {
                        $Sun = $days;
                    }
                }

                $id = uniqid();
                $time = time();
                $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';

                $xml_common_data = '<?xml version="1.0" encoding="UTF-8"?>
                <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                    EchoToken="' . $id . '"
                                                    TimeStamp="' . $time . '"
                                                    Version="3.0"
                                                    NotifType="Overlay"
                                                    NotifScopeType="ProductRate">
                <POS>
                    <Source>
                        <RequestorID ID="bookingjini_ari"/>
                    </Source>
                </POS>';

                $xml_data = $xml_common_data . '<RateAmountMessages HotelCode="' . $hotel_id . '">';

                $los_xml_data = $xml_common_data . '<AvailStatusMessages HotelCode="' . $hotel_id . '">';

                $check_coupon = Coupons::select('discount')
                    ->where(["hotel_id" => $imp_data['hotel_id'], "room_type_id" => $data['room_type_id'], 'coupon_for' => 1])
                    ->orWhere(function ($query) use ($hotel_id) {
                        $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1]);
                    })
                    ->orderBy('coupon_id', 'DESC')
                    ->get();

                if (count($check_coupon) > 0) {
                    $index = "";
                    $price = "";
                    $discount_per = "";
                    $discount_amt = "";
                    $getGstprice = "";
                    $bar_price = "";
                    $totalprice = "";
                    $multiple_days = "";
                    foreach ($filtered_dates as $RsDates) {
                        $index = $RsDates['date'];
                        $check_coupon = Coupons::select('discount')
                            ->where(["hotel_id" => $imp_data['hotel_id'], "room_type_id" => $data['room_type_id'], 'coupon_for' => 1])
                            ->where('valid_from', '<=', $index)
                            ->where('valid_to', '>=', $index)
                            ->orWhere(function ($query) use ($hotel_id, $index) {
                                $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                                    ->where('valid_from', '<=', $index)
                                    ->where('valid_to', '>=', $index);
                            })
                            ->orderBy('coupon_id', 'DESC')
                            ->first();
                        if ($check_coupon != "") {
                            $discount_per = $check_coupon->discount;
                            $discount_amt = round($data['bar_price'] * $discount_per / 100);
                            $price = (float) ($data['bar_price'] - $discount_amt);
                        } else {
                            $price = (float) $data['bar_price'];
                        }

                        if($hotel_info->is_taxable==1){
                                $getGstprice = $this->gstPrice($price);
                            }else{
                                $getGstprice = 0;
                            }
                        $totalprice = (float)($price + $getGstprice);

                        $xml_data .= '<RateAmountMessage>
                                        <StatusApplicationControl Start="' . $index . '"
                                                                        End="' . $index . '"
                                                                        Mon="' . $Mon . '"
                                                                        Tue="' . $Tue . '"
                                                                        Weds="' . $Weds . '"
                                                                        Thur="' . $Thur . '"
                                                                        Fri="' . $Fri . '"
                                                                        Sat="' . $Sat . '"
                                                                        Sun="' . $Sun . '"
                                                                        InvTypeCode="' . $room_type_id . '"
                                                                        RatePlanCode="' . $rate_plan_id . '"/>
                                        <Rates>
                                            <Rate>
                                                <BaseByGuestAmts>
                                                    <BaseByGuestAmt AmountBeforeTax="' . $price . '"
                                                                    AmountAfterTax="' . $totalprice . '"
                                                                    CurrencyCode="INR"
                                                                    NumberOfGuests="2"/>
                                                </BaseByGuestAmts>
                                            </Rate>
                                        </Rates>
                                    </RateAmountMessage>';

                        $los_xml_data .= '<AvailStatusMessage>
                            <StatusApplicationControl Start="' . $index . '"
                                                        End="' . $index . '"
                                                        Mon="' . $Mon . '"
                                                        Tue="' . $Tue . '"
                                                        Weds="' . $Weds . '"
                                                        Thur="' . $Thur . '"
                                                        Fri="' . $Fri . '"
                                                        Sat="' . $Sat . '"
                                                        Sun="' . $Sun . '"
                                                        InvTypeCode="' . $room_type_id . '"
                                                        RatePlanCode="' . $rate_plan_id . '"/>
                            <LengthsOfStay>
                            <LengthOfStay Time="' . $los . '"
                                        TimeUnit="Day"
                                        MinMaxMessageType="SetMinLOS"/>
                            </LengthsOfStay>
                        </AvailStatusMessage>';

                        $current_rate_data[] = [
                            "hotel_id"          => $hotel_id,
                            "room_type_id"        => $room_type_id,
                            "rate_plan_id"        => $rate_plan_id,
                            "ota_id"             => -1,
                            "ota_name"             => "Bookingjini",
                            "stay_date"             => $index,
                            "bar_price"             => $price,
                            "multiple_occupancy" => $data['multiple_occupancy'],
                            "multiple_days" => $data['multiple_days'],
                            "extra_adult_price" => $data['extra_adult_price'],
                            "extra_child_price" => $data['extra_child_price'],
                            "los" => $los
                        ];

                        $cond_current_rate_data[] = [
                            "hotel_id"          => $hotel_id,
                            "room_type_id"        => $room_type_id,
                            "rate_plan_id"        => $rate_plan_id,
                            "ota_id"             => -1,
                            "stay_date"             => $index
                        ];
                    }
                    $current_rates =  $current_rate_data;

                    $xml_data .= '</RateAmountMessages>
                    </OTA_HotelRateAmountNotifRQ>';

                    $los_xml_data .= '</AvailStatusMessages>
                    </OTA_HotelAvailNotifRQ>';
                } else {

                    $price = (float) $data['bar_price'];
                    if($hotel_info->is_taxable==1){
                        $getGstprice = $this->gstPrice($price);
                    }else{
                        $getGstprice = 0;
                    }
                    $totalprice = (float)($price + $getGstprice);

                    $xml_data .= '<RateAmountMessage>
                                        <StatusApplicationControl Start="' . date('Y-m-d', strtotime($data['from_date'])) . '"
                                                                        End="' . date('Y-m-d', strtotime($data['to_date'])) . '"
                                                                        Mon="' . $Mon . '"
                                                                        Tue="' . $Tue . '"
                                                                        Weds="' . $Weds . '"
                                                                        Thur="' . $Thur . '"
                                                                        Fri="' . $Fri . '"
                                                                        Sat="' . $Sat . '"
                                                                        Sun="' . $Sun . '"
                                                                        InvTypeCode="' . $room_type_id . '"
                                                                        RatePlanCode="' . $rate_plan_id . '"/>
                                        <Rates>
                                            <Rate>
                                                <BaseByGuestAmts>
                                                    <BaseByGuestAmt AmountBeforeTax="' . $price . '"
                                                                    AmountAfterTax="' . $totalprice . '"
                                                                    CurrencyCode="INR"
                                                                    NumberOfGuests="2"/>
                                                </BaseByGuestAmts>
                                            </Rate>
                                        </Rates>
                                    </RateAmountMessage>';
                    $xml_data .= '</RateAmountMessages>
                    </OTA_HotelRateAmountNotifRQ>';

                    $los_xml_data .= '<AvailStatusMessage>
                                    <StatusApplicationControl Start="' . date('Y-m-d', strtotime($data['from_date'])) . '"
                                                                End="' . date('Y-m-d', strtotime($data['to_date'])) . '"
                                                                Mon="' . $Mon . '"
                                                                Tue="' . $Tue . '"
                                                                Weds="' . $Weds . '"
                                                                Thur="' . $Thur . '"
                                                                Fri="' . $Fri . '"
                                                                Sat="' . $Sat . '"
                                                                Sun="' . $Sun . '"
                                                                InvTypeCode="' . $room_type_id . '"
                                                                RatePlanCode="' . $rate_plan_id . '"/>
                                    <LengthsOfStay>
                                    <LengthOfStay Time="' . $los . '"
                                                TimeUnit="Day"
                                                MinMaxMessageType="SetMinLOS"/>
                                    </LengthsOfStay>
                                </AvailStatusMessage>';
                    $los_xml_data .= '</AvailStatusMessages>
                                </OTA_HotelAvailNotifRQ>';
                }
                $headers = array(
                    "Content-Type: application/xml",
                );

                $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_rate_amount_notif';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
                $google_resp = curl_exec($ch);
                curl_close($ch);
                $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
                if (isset($google_resp["Success"])) {

                    $test_array = array(
                        "hotel_id" => $hotel_id,
                        "room_type_id" => $room_type_id,
                        "rate_plan_id" => $rate_plan_id,
                        "ota_id" => -1
                    );

                    $stay_dates = array_column($filtered_dates, "date");
                    $current_rate_all_datas_get = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates);

                    if (count($current_rates) > 0) {

                        $check_current_rate_all_datas_get = $current_rate_all_datas_get->get();
                        $new_rate_datas = array();
                        $update_datas = array();
                        $testing = "";
                        foreach ($stay_dates as $RsDates) {
                            $check_rates_datas = $check_current_rate_all_datas_get->where("stay_date", $RsDates)->first();
                            if ($check_rates_datas == "") {
                                $new_rate_datas[] = collect($current_rates)->where("stay_date", $RsDates)->first();
                            } else {
                                $update_datas = collect($current_rates)->where("stay_date", $RsDates)->first();
                                $testing = CurrentRate::where("id", $check_rates_datas->id)->update($update_datas);
                                CurrentRateBe::where("id", $check_rates_datas->id)->update($update_datas);
                            }
                        }
                        if (count($new_rate_datas) > 0) {
                            $test_current_rate = CurrentRate::insert($new_rate_datas);
                            CurrentRateBe::insert($new_rate_datas);
                        }
                    } else {
                        $currentrates = [
                            "bar_price"             => $price,
                            "multiple_occupancy" => $data['multiple_occupancy'],
                            "multiple_days" => $data['multiple_days'],
                            "extra_adult_price" => $data['extra_adult_price'],
                            "extra_child_price" => $data['extra_child_price'],
                            "los" => $los
                        ];

                        $update_bulk_rates = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates)->update($currentrates);
                        CurrentRateBe::where($test_array)->whereIn("stay_date", $stay_dates)->update($currentrates);
                    }
                    $log_data                 = [
                        "action_id"          => 2,
                        "hotel_id"           => $imp_data['hotel_id'],
                        "ota_id"             => -1,
                        "rate_ref_id"        => $rate_plan_log_ids,
                        "user_id"            => $imp_data['user_id'],
                        "request_msg"        => '',
                        "response_msg"       => '',
                        "request_url"        => '',
                        "status"               => 1,
                        "ip"                   => $imp_data['client_ip'],
                        "comment"              => "Processing for update "
                    ];
                    $logModel->fill($log_data)->save(); //saving pre log data
                    $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');

                    $headers = array(
                        "Content-Type: application/xml",
                    );

                    $losurl =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
                    $los_ch = curl_init();
                    curl_setopt($los_ch, CURLOPT_URL, $losurl);
                    curl_setopt($los_ch, CURLOPT_POST, true);
                    curl_setopt($los_ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($los_ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($los_ch, CURLOPT_POSTFIELDS, $los_xml_data);
                    $google_resp = curl_exec($los_ch);
                    curl_close($los_ch);
                } else {
                    $resp = array('status' => 0, 'response_msg' => 'rate update unsucessfully in Booking engine1', 'ota_name' => 'Bookingjini');
                }
            } else {

                $current_filtered_dates = array();
                $from_date = date('Y-m-d', strtotime($be_rate_datas['from_date']));
                $to_date = date('Y-m-d', strtotime($be_rate_datas['to_date']));
                while (strtotime($from_date) <= strtotime($to_date)) {
                    $array = $from_date;
                    $timestamp = strtotime($array);
                    $day = date('D', $timestamp);
                    array_push($current_filtered_dates, array("date" => $array, "day" => $day));
                    $from_date = date("Y-m-d", strtotime("+1 days", strtotime($from_date)));
                }

                $current_filtered_dates = array_column($current_filtered_dates, "date");

                $currents_cond_datas = [
                    "hotel_id"          => $hotel_id,
                    "room_type_id"        => $room_type_id,
                    "rate_plan_id"        => $rate_plan_id,
                    "ota_id"             => -1
                ];

                $current_update_array = array(
                    "bar_price" => $be_rate_datas['bar_price'],
                    "multiple_occupancy" => $be_rate_datas['multiple_occupancy'],
                    "multiple_days" => $be_rate_datas['multiple_days'],
                    "extra_adult_price" => $be_rate_datas['extra_adult_price'],
                    "extra_child_price" => $be_rate_datas['extra_child_price'],
                    "los" => $be_rate_datas['los'],
                );

                $current_rate_all_datas_get = CurrentRate::where($currents_cond_datas)->whereIn("stay_date", $current_filtered_dates);
                CurrentRateBe::where($currents_cond_datas)->whereIn("stay_date", $current_filtered_dates)->update($current_update_array);
                $test_current_rate = $current_rate_all_datas_get->update($current_update_array);

                $get_current_rates = $current_rate_all_datas_get->get();
                $get_all_current_rates = $get_current_rates;
                $all_ota_current_rates = array();
                if (count($get_all_current_rates) > 0) {
                    foreach ($current_filtered_dates as $RsDates) {
                        if (count($get_all_current_rates->where("stay_date", $RsDates)) == 0) {
                            array_push($all_ota_current_rates, array(
                                "hotel_id" => $hotel_id,
                                "ota_id" => -1,
                                "ota_name" => "Bookingjini",
                                "room_type_id" => $room_type_id,
                                "rate_plan_id" => $rate_plan_id,
                                "stay_date" => $RsDates,
                                "bar_price" => $current_update_array['bar_price'],
                                "multiple_occupancy" => $current_update_array['multiple_occupancy'],
                                "multiple_days" => $current_update_array['multiple_days'],
                                "extra_adult_price" => $current_update_array['extra_adult_price'],
                                "extra_child_price" => $current_update_array['extra_child_price'],
                                "los" => $current_update_array['los'],
                            ));
                        } else {
                            continue;
                        }
                    }
                } else {
                    $new_current_rate_data = array();
                    foreach ($current_filtered_dates as $RsDates) {
                        $new_current_rate_data[] = [
                            "hotel_id"          => $hotel_id,
                            "room_type_id"        => $room_type_id,
                            "rate_plan_id"        => $rate_plan_id,
                            "ota_id"             => -1,
                            "ota_name"             => "Bookingjini",
                            "stay_date"             => $RsDates,
                            "bar_price"             => $current_update_array['bar_price'],
                            "multiple_occupancy" => $current_update_array['multiple_occupancy'],
                            "multiple_days" => $current_update_array['multiple_days'],
                            "extra_adult_price" => $current_update_array['extra_adult_price'],
                            "extra_child_price" => $current_update_array['extra_child_price'],
                            "los" => $current_update_array['los']
                        ];
                    }

                    $all_ota_current_rate_status = CurrentRate::insert($new_current_rate_data);
                    CurrentRateBe::insert($new_current_rate_data);
                }
                if (count($all_ota_current_rates) > 0) {
                    CurrentRate::insert($all_ota_current_rates);
                    CurrentRateBe::insert($all_ota_current_rates);
                }
                $log_data                 = [
                    "action_id"          => 2,
                    "hotel_id"           => $imp_data['hotel_id'],
                    "ota_id"             => -1,
                    "rate_ref_id"        => $rate_plan_log_ids,
                    "user_id"            => $imp_data['user_id'],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"               => 1,
                    "ip"                   => $imp_data['client_ip'],
                    "comment"              => "Processing for update "
                ];
                $logModel->fill($log_data)->save();
                $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => 'rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
        }
        return $resp;
    }

    //Added by Jigyans dt : - 25-01-2023
    public function individualRateUpdate(Request $request)
    {
        try {
            $data = $request->getContent();
            if ($this->isJSON($data)) {
                $data = json_decode($data, true);
            } else {
                $data = $request->all();
            }

            $multiple_days = array(
                'Mon' => 1,
                'Tue' => 1,
                'Wed' => 1,
                'Thu' => 1,
                'Fri' => 1,
                'Sat' => 1,
                'Sun' => 1
            );

            $hotel_id = $data['hotel_id'];
            $room_type_id = $data['rates'][0]['room_type_id'];
            $rate_plan_id = $data['rates'][0]['rate_plan_id'];
            $data['rates'][0]['multiple_days'] = $multiple_days;
            $be_rate_details = $data['rates'][0]['rates'];
            $extra_adult_and_extra_child = array(
                "hotel_id" => $hotel_id,
                "room_type_id" => $room_type_id,
                "rate_plan_id" => $rate_plan_id
            );

            if (count($be_rate_details) > 0) {
                $imp_data = $this->impDate($data, $request); //used to get user id and client ip.
                $check = array();
                foreach ($be_rate_details as $RsRoomTypeRateUpdate) {
                    $check[] = $this->checkBarPriceAndMultipleOccupancy($hotel_id, $room_type_id, $rate_plan_id, $RsRoomTypeRateUpdate);
                }

                if (count(array_column($check, "status")) > 0) {
                    $error_messages = array('status' => 0, 'message' => array_column($check, "message"));
                    return response()->json($error_messages);
                }
                $conds = array('hotel_id' => $hotel_id, 'derived_room_type_id' => $room_type_id, 'derived_rate_plan_id' => $rate_plan_id);
                $chek_parents = DerivedPlan::select('*')->where($conds)->get();
                if (count($chek_parents) > 0) {
                    $child_sync_rate_datas = array();
                    $child_room_type_rate_plan_datas = array();
                    foreach ($chek_parents as $details) 
                    {
                        $rate_planwise_rate_details = array();
                        $bar_price = "";
                        $extra_adult_price = "";
                        $extra_child_price = "";
                        $multiple_occupancy_array = "";
                        foreach($be_rate_details as $RsRateDetails)
                        {
                            if ($RsRateDetails['extra_adult_price'] == 0 || $RsRateDetails['extra_adult_price'] == "") {
                                $RsRateDetails['extra_adult_price'] = $this->getExtraAdultChildPrice($extra_adult_and_extra_child, 1);
                            }
                            if ($RsRateDetails['extra_child_price'] == 0 || $RsRateDetails['extra_child_price'] == "") {
                                $RsRateDetails['extra_child_price'] = $this->getExtraAdultChildPrice($extra_adult_and_extra_child, 2);
                            }

                            $bar_price = $RsRateDetails['bar_price'];
                            $extra_adult_price = $RsRateDetails['extra_adult_price'];
                            $extra_child_price = $RsRateDetails['extra_child_price'];
                            $multiple_occupancy_array = $RsRateDetails['multiple_occupancy'];
                            
                            $multiple_occupancy = array();
                            $getPrice = explode(",", $details->amount_type);
                            $indexSize =  sizeof($getPrice) - 1;
                            if ($details->select_type == 'percentage') {
                                $percentage_price = ($bar_price * $getPrice[$indexSize]) / 100;
                                $RsRateDetails['bar_price'] = round($bar_price + $percentage_price);
                                foreach ($multiple_occupancy_array as $key => $multi) {
                                    $multi_per_price = ($multi * $getPrice[$key]) / 100;
                                    $multiple_occupancy[] = round($multi + $multi_per_price);
                                }
                                $RsRateDetails['multiple_occupancy'] = $multiple_occupancy;
                            } else {
                                $RsRateDetails['bar_price'] = round($bar_price + $getPrice[$indexSize]);
                                foreach ($multiple_occupancy_array as $key => $multi) {
                                    $multiple_occupancy[] = round($multi + $getPrice[$key]);
                                }
                                $RsRateDetails['multiple_occupancy'] = $multiple_occupancy;
                            }
                            if ($details->extra_adult_select_type == 'percentage') {
                                $percentage_price = ($extra_adult_price * $details->extra_adult_amount) / 100;
                                $RsRateDetails['extra_adult_price'] = round($extra_adult_price + $percentage_price);
                            } else {
                                $RsRateDetails['extra_adult_price'] = round($extra_adult_price + $details->extra_adult_amount);
                            }
                            if ($details->extra_child_select_type == 'percentage') {
                                $percentage_price = ($extra_child_price * $details->extra_child_amount) / 100;
                                $RsRateDetails['extra_child_price'] = round($extra_child_price + $percentage_price);
                            } else {
                                $RsRateDetails['extra_child_price'] = round($extra_child_price + $details->extra_child_amount);
                            }
                            $rate_planwise_rate_details[] = $RsRateDetails;
                        }
                        
                        $child_room_type_rate_plan_datas = array(
                            'room_type_id' => $details->room_type_id,
                            'rate_plan_id' => $details->rate_plan_id,
                            'rates' => $rate_planwise_rate_details,
                            'multiple_days' => $multiple_days      
                        );
                        $child_sync_rate_datas[] = array(
                            'hotel_id' => $data['hotel_id'],
                            'admin_id' => isset($data['admin_id'])?$data['admin_id']:0,
                            'ota_id' => $data['ota_id'],
                            'rates' => array($child_room_type_rate_plan_datas)
                        );
                    }

                    $response = $this->singleRoomtypeSingleRatePlanMultiDatesUpdate($imp_data, $data);
                    if(count($child_sync_rate_datas) > 0)
                    {
                        $all_rate_plan_response = array();
                        foreach($child_sync_rate_datas as $RsChildSyncRate)
                        {
                            $all_rate_plan_response[] = $this->singleRoomtypeSingleRatePlanMultiDatesUpdate($imp_data, $RsChildSyncRate);
                        }
                    }
                    
                    $response = array_merge($response, ...$all_rate_plan_response);
                    // $response = array_values(array_merge($response,$all_rate_plan_response));
                    // $response = array_values(array_unique($response, SORT_REGULAR));
                } else {
                    $response = $this->singleRoomtypeSingleRatePlanMultiDatesUpdate($imp_data,$data);
                }  

                return response()->json($response);

            } else {
                $response = array('status' => 0, 'response_msg' => "update failed");
                return response()->json($response);
            }
        } catch (\Exception $e) {
            $res = array('status' => -1, 'response_msg' => $e->getMessage());

            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => date("YmdHis"));

            $result = json_encode($result);
            $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);

            $logpath = storage_path("logs/jigyansratesnew.log".date("Y-m-d"));
            $logfile = fopen($logpath, "a+");
            fwrite($logfile,"\n\n\n".date("YmdHis").json_encode($request->all())."\n\n\n");
            fclose($logfile);

            return response()->json($res);
        }
    }

    //Added by Jigyans dt : - 21-03-2023
    public function singleRoomtypeSingleRatePlanMultiDatesUpdate($imp_data, $data)
    {
        $rate_plan_log = new RatePlanLog();
        $logModel      = new RateUpdateLog();
        $base_rate     = new BaseRate();
        $resp = array();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['rates'][0]['room_type_id'];
        $rate_plan_id = $data['rates'][0]['rate_plan_id'];

        $multiple_days = array(
            'Mon' => 1,
            'Tue' => 1,
            'Wed' => 1,
            'Thu' => 1,
            'Fri' => 1,
            'Sat' => 1,
            'Sun' => 1
        );

        $hotel_info = HotelInformation::where('hotel_id',$hotel_id)
        ->select('hotels_table.is_taxable')
        ->first();

        $data['rates'][0]['multiple_days'] = $multiple_days;

        $be_rate_details = $data['rates'][0]['rates'];
        $filtered_dates = array_column($be_rate_details, 'date');

        $result = array();
        $k = 0;
        foreach($filtered_dates as $RsFilteredDates)
        {
            $result[] = array(
            'from_date' => date("Y-m-d",strtotime($RsFilteredDates)),
            'to_date' => date("Y-m-d",strtotime($RsFilteredDates))
            );
            $k++;
        }
        
        $be_rate_datas = array();
        foreach($be_rate_details as $RsResult)
        {
            $be_rate_datas[] = array(
                "hotel_id" => $hotel_id,
                "room_type_id" => $room_type_id,
                "rate_plan_id" => $rate_plan_id,
                "multiple_occupancy" => json_encode($RsResult['multiple_occupancy']),
                "bar_price" => $RsResult['bar_price'],
                "from_date" => date("Y-m-d",strtotime($RsResult['date'])),
                "to_date" => date("Y-m-d",strtotime($RsResult['date'])),
                "multiple_days" => json_encode($multiple_days),
                "block_status" => 0,
                "los" => $RsResult['los'],
                "client_ip" => $imp_data['client_ip'],
                "user_id" => $imp_data['user_id'],
                "extra_adult_price" => $RsResult['extra_adult_price'],
                "extra_child_price" => $RsResult['extra_child_price']
            );
        }

        $stay_dates = array_column($result,"from_date");
        if (count($be_rate_datas) > 0) {
            $google_hotel_status = "";

            $rate_plan_log_ids = [];
            foreach($be_rate_datas as $RsBeRateDatas)
            {
                $rate_plan_log_ids[] = $rate_plan_log->insertGetId($RsBeRateDatas);
            }
            $index = "";
            if (count($rate_plan_log_ids) > 0) {
                if (!isset($imp_data['dp_status'])) {
                    $insertBaseRate = $base_rate->insert($be_rate_datas);
                }
                $test_array = array(
                    "hotel_id" => $hotel_id,
                    "room_type_id" => $room_type_id,
                    "rate_plan_id" => $rate_plan_id,
                    "ota_id" => -1
                );
                $current_rates = array();
                $current_rate_data = array();
                if ($this->googleHotelStatus($data['hotel_id'])) {

                    foreach ($multiple_days as $key => $days) {
                        if ($key == 'Mon') {
                            $Mon = $days;
                        }
                        if ($key == 'Tue') {
                            $Tue = $days;
                        }
                        if ($key == 'Wed') {
                            $Weds = $days;
                        }
                        if ($key == 'Thu') {
                            $Thur = $days;
                        }
                        if ($key == 'Fri') {
                            $Fri = $days;
                        }
                        if ($key == 'Sat') {
                            $Sat = $days;
                        }
                        if ($key == 'Sun') {
                            $Sun = $days;
                        }
                    }

                    $id = uniqid();
                    $time = time();
                    $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';

                    $xml_common_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                        EchoToken="' . $id . '"
                                                        TimeStamp="' . $time . '"
                                                        Version="3.0"
                                                        NotifType="Overlay"
                                                        NotifScopeType="ProductRate">
                    <POS>
                        <Source>
                            <RequestorID ID="bookingjini_ari"/>
                        </Source>
                    </POS>';

                    $xml_los_header_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                            EchoToken="' . $id . '"
                                            TimeStamp="' . $time . '"
                                            Version="3.0">
                        <POS>
                            <Source>
                            <RequestorID ID="bookingjini_ari"/>
                            </Source>
                        </POS>';

                    $xml_data = $xml_common_data . '<RateAmountMessages HotelCode="' . $hotel_id . '">';

                    $los_xml_data = $xml_los_header_data . '<AvailStatusMessages HotelCode="' . $hotel_id . '">';

                    foreach($be_rate_datas as $Rsresult)
                    {
                        $price = (float) $Rsresult['bar_price'];

                        if($hotel_info->is_taxable==1){
                            $getGstprice = $this->gstPrice($price);
                            }else{
                                $getGstprice = 0;
                            }
    
                        $totalprice = (float)($price + $getGstprice);
                        $xml_data .= '<RateAmountMessage>
                        <StatusApplicationControl Start="' . $Rsresult['from_date'] . '"
                                                        End="' . $Rsresult['to_date'] . '"
                                                        Mon="' . $Mon . '"
                                                        Tue="' . $Tue . '"
                                                        Weds="' . $Weds . '"
                                                        Thur="' . $Thur . '"
                                                        Fri="' . $Fri . '"
                                                        Sat="' . $Sat . '"
                                                        Sun="' . $Sun . '"
                                                        InvTypeCode="' . $room_type_id . '"
                                                        RatePlanCode="' . $rate_plan_id . '"/>
                                            <Rates>
                                                <Rate>
                                                    <BaseByGuestAmts>
                                                        <BaseByGuestAmt AmountBeforeTax="' . $price . '"
                                                                        AmountAfterTax="' . $totalprice . '"
                                                                        CurrencyCode="INR"
                                                                        NumberOfGuests="2"/>
                                                    </BaseByGuestAmts>
                                                </Rate>
                                            </Rates>
                                        </RateAmountMessage>';

                        $los_xml_data .= '<AvailStatusMessage>
                        <StatusApplicationControl Start="' . $Rsresult['from_date'] . '"
                                                    End="' . $Rsresult['to_date'] . '"
                                                    Mon="' . $Mon . '"
                                                    Tue="' . $Tue . '"
                                                    Weds="' . $Weds . '"
                                                    Thur="' . $Thur . '"
                                                    Fri="' . $Fri . '"
                                                    Sat="' . $Sat . '"
                                                    Sun="' . $Sun . '"
                                                    InvTypeCode="' . $room_type_id . '"
                                                    RatePlanCode="' . $rate_plan_id . '"/>
                        <LengthsOfStay>
                        <LengthOfStay Time="' . $Rsresult['los'] . '"
                                    TimeUnit="Day"
                                    MinMaxMessageType="SetMinLOS"/>
                        </LengthsOfStay>
                        </AvailStatusMessage>';
                    }

                    $los_xml_data .= '</AvailStatusMessages>
                                </OTA_HotelAvailNotifRQ>';
                    $xml_data .= '</RateAmountMessages>
                    </OTA_HotelRateAmountNotifRQ>';
                    $headers = array(
                        "Content-Type: application/xml",
                    );

                    $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_rate_amount_notif';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
                    $google_resp = curl_exec($ch);
                    curl_close($ch);
                    $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
                    if (isset($google_resp["Success"])) {
                        $current_rate_all_datas_get = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates);
                        $check_current_rate_all_datas_get = $current_rate_all_datas_get->get();
                        if(count($check_current_rate_all_datas_get) > 0)
                        {
                            $new_rate_datas = array();
                            $update_ids = array();
                            $check_rates_datas = "";
                            foreach ($be_rate_datas as $RsDatas) {
                                $check_rates_datas = $check_current_rate_all_datas_get->where("stay_date", $RsDatas['from_date'])->first();
                                if ($check_rates_datas == NULL) {
                                    $new_rate_datas[] = array(
                                        "hotel_id" => $hotel_id,
                                        "ota_id" => -1,
                                        "ota_name" => "Bookingjini",
                                        "room_type_id" => $room_type_id,
                                        "rate_plan_id" => $rate_plan_id,
                                        "stay_date" => $RsDatas['from_date'],
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                } else {
                                    $update_ids["id"][] = $check_rates_datas->id;
                                    $update_ids["rates_data"][] = array(
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                }
                            }
                            if (count($new_rate_datas) > 0) {
                                $test_current_rate = CurrentRate::insert($new_rate_datas);
                                CurrentRateBe::insert($new_rate_datas);
                            }
                            if(count($update_ids) > 0)
                            {
                                $updateratedatas = array();
                                $counter = 0;
                                foreach($update_ids['rates_data'] as $RsUpdateRates)
                                {
                                    $updateratedatas = array(
                                    "bar_price" => $RsUpdateRates['bar_price'],
                                    "multiple_occupancy" => $RsUpdateRates['multiple_occupancy'],
                                    "multiple_days" => $RsUpdateRates['multiple_days'],
                                    "extra_adult_price" => $RsUpdateRates['extra_adult_price'],
                                    "extra_child_price" => $RsUpdateRates['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsUpdateRates['los'],
                                    );
                                    $testing = CurrentRate::where("id", $update_ids['id'][$counter])->update($updateratedatas);
                                    CurrentRateBe::where("id", $update_ids['id'][$counter])->update($updateratedatas);
                                    $counter++;
                                }
                            }
                        }
                        else
                        {
                            $bookingjini_rates_data = [];
                            foreach($be_rate_datas as $RsDatas)
                            {
                                $bookingjini_rates_data[] = array(
                                    "hotel_id" => $hotel_id,
                                    "ota_id" => -1,
                                    "ota_name" => "Bookingjini",
                                    "room_type_id" => $room_type_id,
                                    "rate_plan_id" => $rate_plan_id,
                                    "stay_date" => $RsDatas['from_date'],
                                    "bar_price" => $RsDatas['bar_price'],
                                    "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                    "multiple_days" => $RsDatas['multiple_days'],
                                    "extra_adult_price" => $RsDatas['extra_adult_price'],
                                    "extra_child_price" => $RsDatas['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsDatas['los']
                                );
                            }
                            $test_current_rate = CurrentRate::insert($bookingjini_rates_data);
                            CurrentRateBe::insert($bookingjini_rates_data);
                        }

                        $log_data                 = [
                            "action_id"          => 2,
                            "hotel_id"           => $imp_data['hotel_id'],
                            "ota_id"             => -1,
                            "rate_ref_id"        => implode(",",$rate_plan_log_ids),
                            "user_id"            => $imp_data['user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"               => 1,
                            "ip"                   => $imp_data['client_ip'],
                            "comment"              => "Processing for update "
                        ];
                        $logModel->fill($log_data)->save(); //saving pre log data

                        $headers = array(
                            "Content-Type: application/xml",
                        );

                        $losurl =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
                        $los_ch = curl_init();
                        curl_setopt($los_ch, CURLOPT_URL, $losurl);
                        curl_setopt($los_ch, CURLOPT_POST, true);
                        curl_setopt($los_ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($los_ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($los_ch, CURLOPT_POSTFIELDS, $los_xml_data);
                        $google_los_resp = curl_exec($los_ch);
                        curl_close($los_ch);
                        $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                        return $resp;
                    } else {
                        $resp = array('status' => 0, 'response_msg' => $google_resp, 'ota_name' => 'Bookingjini');
                        return $resp;
                    }
                } else {
                        $current_rate_all_datas_get = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates);
                        $check_current_rate_all_datas_get = $current_rate_all_datas_get->get();

                        if(count($check_current_rate_all_datas_get) > 0)
                        {
                            $new_rate_datas = array();
                            $update_ids = array();
                            $check_rates_datas = "";
                            foreach ($be_rate_datas as $RsDatas) {
                                $check_rates_datas = $check_current_rate_all_datas_get->where("stay_date", $RsDatas['from_date'])->first();
                                if ($check_rates_datas == NULL) {
                                    $new_rate_datas[] = array(
                                        "hotel_id" => $hotel_id,
                                        "ota_id" => -1,
                                        "ota_name" => "Bookingjini",
                                        "room_type_id" => $room_type_id,
                                        "rate_plan_id" => $rate_plan_id,
                                        "stay_date" => $RsDatas['from_date'],
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                } else {
                                    $update_ids["id"][] = $check_rates_datas->id;
                                    $update_ids["rates_data"][] = array(
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                }
                            }
                            if (count($new_rate_datas) > 0) {
                                $test_current_rate = CurrentRate::insert($new_rate_datas);
                                CurrentRateBe::insert($new_rate_datas);
                            }
                            if(count($update_ids) > 0)
                            {
                                $updateratedatas = array();
                                $counter = 0;
                                foreach($update_ids['rates_data'] as $RsUpdateRates)
                                {
                                    $updateratedatas = array(
                                    "bar_price" => $RsUpdateRates['bar_price'],
                                    "multiple_occupancy" => $RsUpdateRates['multiple_occupancy'],
                                    "multiple_days" => $RsUpdateRates['multiple_days'],
                                    "extra_adult_price" => $RsUpdateRates['extra_adult_price'],
                                    "extra_child_price" => $RsUpdateRates['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsUpdateRates['los'],
                                    );
                                    $testing = CurrentRate::where("id", $update_ids['id'][$counter])->update($updateratedatas);
                                    CurrentRateBe::where("id", $update_ids['id'][$counter])->update($updateratedatas);
                                    $counter++;
                                }
                            }
                        }
                        else
                        {
                            $bookingjini_rates_data = [];
                            foreach($be_rate_datas as $RsDatas)
                            {
                                $bookingjini_rates_data[] = array(
                                    "hotel_id" => $hotel_id,
                                    "ota_id" => -1,
                                    "ota_name" => "Bookingjini",
                                    "room_type_id" => $room_type_id,
                                    "rate_plan_id" => $rate_plan_id,
                                    "stay_date" => $RsDatas['from_date'],
                                    "bar_price" => $RsDatas['bar_price'],
                                    "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                    "multiple_days" => $RsDatas['multiple_days'],
                                    "extra_adult_price" => $RsDatas['extra_adult_price'],
                                    "extra_child_price" => $RsDatas['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsDatas['los']
                                );
                            }
                            $test_current_rate = CurrentRate::insert($bookingjini_rates_data);
                            CurrentRateBe::insert($bookingjini_rates_data);
                        }
                    $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                    return $resp;
                }
            } else {
                $resp = array('status' => 0, 'response_msg' => 'rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => 'No records found to update for this date', 'ota_name' => 'Bookingjini');
            return $resp;
        }
        return $resp;
    }


    //Added by Jigyans dt :- 22-03-2023
    public function blockRateUpdate(Request $request)
    {
        $data = $request->all();
        if (!is_array($data['rate_plan_id'])) {
            $data['rate_plan_id'] = json_decode($data['rate_plan_id']);
        }
        if (isset($data['rate_plan_type'])) {
            if (!is_array($data['rate_plan_type'])) {
                $data['rate_plan_type'] = json_decode($data['rate_plan_type']);
            }
        }

        if (!is_array($data['ota_id'])) {
            $data['ota_id'] = json_decode($data['ota_id']);
        }

        $room_type_id = $request->input('room_type_id');
        $fmonth = explode('-', $data['date_from']); //for removing extra o from month and remove this code after mobile app update
        if (strlen($fmonth[1]) == 3) {
            $fmonth[1] = ltrim($fmonth[1], 0);
        }

        $data['date_from'] = implode('-', $fmonth);
        $tmonth = explode('-', $data['date_to']);
        if (strlen($tmonth[1]) == 3) {
            $tmonth[1] = ltrim($tmonth[1], 0);
        }
        $data['date_to'] = implode('-', $tmonth);

        $date1 = date_create($data['date_from']);
        $date2 = date_create($data['date_to']);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        if ($diff > 366) {
            $check_google_hotel = $this->googleHotelStatus($data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 366 days', 'ota_name' => 'bookingjini');
            }
        }

        $data['date_from'] = date('Y-m-d', strtotime($data['date_from']));
        $data['date_to'] = date('Y-m-d', strtotime($data['date_to']));
        $imp_data = $this->impDate($data, $request); 
        $resp = array();

        $get_rates_url = "https://cm.bookingjini.com/extranetv4/getrates/" . $data['hotel_id'] . "/" . $room_type_id . "/" . $data['date_from'] . "/" . $data['date_to'];
        $get_rates_datas = Commonmodel::curlGet($get_rates_url);
       
        if (isset($get_rates_datas->status)) {
            if ($get_rates_datas->status == 1) {
                if (count($get_rates_datas->channel_rates) > 0) {
                    $get_rates_datas = collect($get_rates_datas->channel_rates)->where("ota_name", "Bookingjini")->all();
                    $get_rates_datas = json_decode(json_encode($get_rates_datas[0]->plans[0]->day_rates), true);
                    print_r($get_rates_datas);exit;


                    // $rate_data = [
                    //     'hotel_id'          => $data['hotel_id'],
                    //     'room_type_id'      => $room_type_id,
                    //     'rate_plan_id'      => $getRateDetails->rate_plan_id,
                    //     'bar_price'         => $getRateDetails->bar_price,
                    //     'multiple_occupancy' => $getRateDetails->multiple_occupancy,
                    //     'multiple_days'     => '{"Sun":1,"Mon":1,"Tue":1,"Wed":1,"Thu":1,"Fri":1,"Sat":1}',
                    //     'from_date'         => $date_from,
                    //     'to_date'           => $date_from,
                    //     'block_status'      => 1,
                    //     'los'               => $getRateDetails->los,
                    //     'client_ip'         => $imp_data['client_ip'],
                    //     'user_id'           => $imp_data['user_id'],
                    //     'extra_adult_price' => $getRateDetails->extra_adult_price,
                    //     'extra_child_price' => $getRateDetails->extra_child_price,
                    //     'channel'           => 'Bookingjini'
                    // ];
    
                    // $rate = new RatePlanLog();



                    foreach ($data['rate_plan_id'] as $rate_plan_id) {
                        $logModel      = new RateUpdateLog();
                        $data['date_from'] = date('Y-m-d', strtotime($data['date_from']));
                        $data['date_to'] = date('Y-m-d', strtotime($data['date_to']));
                        $cond = array('hotel_id' => $data['hotel_id'], 'room_type_id' => $room_type_id, 'rate_plan_id' => $rate_plan_id);
                        $date_from = $data['date_from'];
                        for ($i = 0; $i <= $diff; $i++) {
            
                            $getRateDetails = RatePlanLog::select('*')
                                ->where($cond)->where('from_date', '<=', $date_from)
                                ->where('to_date', '>=', $date_from)
                                ->orderBy('rate_plan_log_id', 'DESC')
                                ->first();
                            if (empty($getRateDetails)) {
                                $date_from = date('Y-m-d', strtotime($date_from . '+1 day'));
                                $date_to = date('Y-m-d', strtotime($data['date_to']));
                                $return_resp = array('status' => 0, 'response_msg' => 'rate is not available', 'ota_name' => 'Bookingjini');
                                continue;
                            }
                            $multiple_days = '{"Sun":1,"Mon":1,"Tue":1,"Wed":1,"Thu":1,"Fri":1,"Sat":1}';
                            $rate_data = [
                                'hotel_id'          => $getRateDetails->hotel_id,
                                'room_type_id'      => $getRateDetails->room_type_id,
                                'rate_plan_id'      => $getRateDetails->rate_plan_id,
                                'bar_price'         => $getRateDetails->bar_price,
                                'multiple_occupancy' => $getRateDetails->multiple_occupancy,
                                'multiple_days'     => '{"Sun":1,"Mon":1,"Tue":1,"Wed":1,"Thu":1,"Fri":1,"Sat":1}',
                                'from_date'         => $date_from,
                                'to_date'           => $date_from,
                                'block_status'      => 1,
                                'los'               => $getRateDetails->los,
                                'client_ip'         => $imp_data['client_ip'],
                                'user_id'           => $imp_data['user_id'],
                                'extra_adult_price' => $getRateDetails->extra_adult_price,
                                'extra_child_price' => $getRateDetails->extra_child_price,
                                'channel'           => 'Bookingjini'
                            ];
            
                            $rate = new RatePlanLog();
                            if ($rate_status = $rate->fill($rate_data)->save()) {
                                $base_rate     = new BaseRate();
                                $insertBaseRate = $base_rate->fill($rate_data)->save();
            
                                if ($rate_data['hotel_id'] == 1953) {
            
                                    $current_rate_data                   = [
                                        "hotel_id"          => $rate_data['hotel_id'],
                                        "room_type_id"        => $rate_data['room_type_id'],
                                        "rate_plan_id"        => $rate_data['rate_plan_id'],
                                        "ota_id"             => '-1',
                                        "ota_name"             => "Bookingjini",
                                        "stay_date"             => $date_from,
                                        "bar_price"             => $rate_data['bar_price'],
                                        "multiple_occupancy" => $rate_data['multiple_occupancy'],
                                        "multiple_days" => $rate_data['multiple_days'],
                                        'block_status'      => 1,
                                        "extra_adult_price" => $rate_data['extra_adult_price'],
                                        "extra_child_price" => $rate_data['extra_child_price'],
                                        "los" => $rate_data['los']
                                    ];
            
                                    $cond_current_rate_data                   = [
                                        "hotel_id"          => $rate_data['hotel_id'],
                                        "room_type_id"      => $rate_data['room_type_id'],
                                        "rate_plan_id"      => $rate_data['rate_plan_id'],
                                        "ota_id"            => '-1',
                                        "stay_date"         => $date_from
                                    ];
            
                                    $cur_inv = CurrentRate::updateOrInsert($cond_current_rate_data, $current_rate_data);
                                }
            
                                $log_data                   = [
                                    "action_id"             => 2,
                                    "hotel_id"              => $getRateDetails->hotel_id,
                                    "ota_id"                => 0,
                                    "rate_ref_id"           => $rate->rate_plan_log_id,
                                    "user_id"               => $getRateDetails->user_id,
                                    "request_msg"           => '',
                                    "response_msg"          => '',
                                    "request_url"           => '',
                                    "status"                => 1,
                                    "ip"                    => $getRateDetails->client_ip,
                                    "comment"               => "Processing for update "
                                ];
                                $logModel->fill($log_data)->save(); //saving pre log data
            
                                if ($this->googleHotelStatus($imp_data['hotel_id']) == true) {
                                    $los_updatec = $this->losUpdateToGoogleAds($getRateDetails->hotel_id, $getRateDetails->room_type_id, $getRateDetails->rate_plan_id, $date_from, $date_from, $getRateDetails->los, $multiple_days, $data['block_status']);
                                }
            
                                $return_resp = array('status' => 1, 'response_msg' => 'Rate plan blocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
                            } else {
                                $return_resp = array('status' => 0, 'response_msg' => 'Rate plan block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
                            }
                            $date_from = date('Y-m-d', strtotime($date_from . '+1 day'));
                        }
                    }
                    
                    return response()->json($return_resp);
                } else {
                    $return_resp = array('status' => 0, 'response_msg' => 'No rates fround for this room type', 'ota_name' => 'Bookingjini');
                    return response()->json($return_resp);
                }
            } else {
                $return_resp = array('status' => 0, 'response_msg' => 'Rate plan block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
                return response()->json($return_resp);
            }
        } else {
            $return_resp = array('status' => 0, 'response_msg' => 'Rate plan block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
            return response()->json($return_resp);
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

    public function rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_plan_id, $from_date, $to_date, $bar_price, $multiple_days)
    {
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date));
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $hotel_info = HotelInformation::where('hotel_id',$hotel_id)
        ->select('hotels_table.is_taxable')
        ->first();
        if($hotel_info->is_taxable==1){
            $getGstprice = $this->gstPrice($bar_price);
            }else{
                $getGstprice = 0;
            }
        $bar_price = (float)$bar_price;
        $totalprice = (float)($bar_price + $getGstprice);
        // $multiple_days = '{"Sun":1,"Mon":1,"Tue":1,"Wed":0,"Thu":0,"Fri":1,"Sat":1}';
        $multiple_days = json_decode($multiple_days);
        foreach ($multiple_days as $key => $days) {
            if ($key == 'Mon') {
                $Mon = $days;
            }
            if ($key == 'Tue') {
                $Tue = $days;
            }
            if ($key == 'Wed') {
                $Weds = $days;
            }
            if ($key == 'Thu') {
                $Thur = $days;
            }
            if ($key == 'Fri') {
                $Fri = $days;
            }
            if ($key == 'Sat') {
                $Sat = $days;
            }
            if ($key == 'Sun') {
                $Sun = $days;
            }
        }
        $xml_data =
            '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                EchoToken="' . $id . '"
                                                TimeStamp="' . $time . '"
                                                Version="3.0"
                                                NotifType="Overlay"
                                                NotifScopeType="ProductRate">
            <POS>
                <Source>
                <RequestorID ID="bookingjini_ari"/>
                </Source>
            </POS>
            <RateAmountMessages HotelCode="' . $hotel_id . '">
                <RateAmountMessage>
                    <StatusApplicationControl Start="' . $from_date . '"
                                                    End="' . $to_date . '"
                                                    Mon="' . $Mon . '"
                                                    Tue="' . $Tue . '"
                                                    Weds="' . $Weds . '"
                                                    Thur="' . $Thur . '"
                                                    Fri="' . $Fri . '"
                                                    Sat="' . $Sat . '"
                                                    Sun="' . $Sun . '"
                                                    InvTypeCode="' . $room_type_id . '"
                                                    RatePlanCode="' . $rate_plan_id . '"/>
                    <Rates>
                        <Rate>
                            <BaseByGuestAmts>
                                <BaseByGuestAmt AmountBeforeTax="' . $bar_price . '"
                                                AmountAfterTax="' . $totalprice . '"
                                                CurrencyCode="INR"
                                                NumberOfGuests="2"/>
                            </BaseByGuestAmts>
                        </Rate>
                    </Rates>
                </RateAmountMessage>
            </RateAmountMessages>
        </OTA_HotelRateAmountNotifRQ>';
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_rate_amount_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
        if (isset($google_resp["Success"])) {
            $resp = array('status' => 1, 'response_msg' => 'Rate updation successfully');
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'response_msg' => 'Rate updation fails');
            return response()->json($resp);
        }
    }

    public function losUpdateToGoogleAds($hotel_id, $room_type_id, $rate_plan_id, $from_date, $to_date, $los, $multiple_days)
    {
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date));
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        // $multiple_days = '{"Sun":1,"Mon":1,"Tue":1,"Wed":0,"Thu":0,"Fri":1,"Sat":1}';
        $multiple_days = json_decode($multiple_days);
        foreach ($multiple_days as $key => $days) {
            if ($key == 'Mon') {
                $Mon = $days;
            }
            if ($key == 'Tue') {
                $Tue = $days;
            }
            if ($key == 'Wed') {
                $Weds = $days;
            }
            if ($key == 'Thu') {
                $Thur = $days;
            }
            if ($key == 'Fri') {
                $Fri = $days;
            }
            if ($key == 'Sat') {
                $Sat = $days;
            }
            if ($key == 'Sun') {
                $Sun = $days;
            }
        }
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                    EchoToken="' . $id . '"
                                    TimeStamp="' . $time . '"
                                    Version="3.0">
                <POS>
                    <Source>
                    <RequestorID ID="bookingjini_ari"/>
                    </Source>
                </POS>
                <AvailStatusMessages HotelCode="' . $hotel_id . '">
                        <AvailStatusMessage>
                        <StatusApplicationControl Start="' . $from_date . '"
                                                    End="' . $to_date . '"
                                                    Mon="' . $Mon . '"
                                                    Tue="' . $Tue . '"
                                                    Weds="' . $Weds . '"
                                                    Thur="' . $Thur . '"
                                                    Fri="' . $Fri . '"
                                                    Sat="' . $Sat . '"
                                                    Sun="' . $Sun . '"
                                                    InvTypeCode="' . $room_type_id . '"
                                                    RatePlanCode="' . $rate_plan_id . '"/>
                        <LengthsOfStay>
                        <LengthOfStay Time="' . $los . '"
                                    TimeUnit="Day"
                                    MinMaxMessageType="SetMinLOS"/>
                        </LengthsOfStay>
                    </AvailStatusMessage>
                </AvailStatusMessages>
            </OTA_HotelAvailNotifRQ>';
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);

        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
        if (isset($google_resp["Success"])) {
            $resp = array('status' => 1, 'response_msg' => 'los updation successfully');
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'response_msg' => 'los updation fails');
            return response()->json($resp);
        }
    }

    //Added by Jigyans dt : - 13-04-2023
    public function inventoryBlockDateRangeToGoogleAds($hotel_id, $room_type_id, $no_of_rooms, $inv_start_date_range,$inv_end_date_range)
    {
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $rate_plan_details = MasterRatePlan::where("hotel_id",$hotel_id)->get();
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
                        <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                        EchoToken="' . $id . '"
                                                        TimeStamp="' . $time . '"
                                                        Version="3.0">
                        <AvailStatusMessages HotelCode="' . $hotel_id . '">';
                        if(count($rate_plan_details) > 0)
                        {
                            foreach($rate_plan_details as $RsRatePlanDetails)
                            {
                                $xml_data .= '<AvailStatusMessage>
                                <StatusApplicationControl Start="' . $inv_start_date_range . '"
                                                            End="' . $inv_end_date_range . '"
                                                            InvTypeCode="' . $room_type_id . '"
                                                            RatePlanCode="' . $RsRatePlanDetails->rate_plan_id . '"/>
                                    <RestrictionStatus Status="Close" Restriction="Master"/>
                                    </AvailStatusMessage>';
                            }
                        }
                $xml_data .= '</AvailStatusMessages>
                </OTA_HotelAvailNotifRQ>';
                            
                        // print_r($xml_data);exit;
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resps = curl_exec($ch);
        curl_close($ch);
        // $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);


        $xmlString = $google_resps;

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlString);

        $error_count = 0;
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                // print_r($error->message);
                $error_count++;
            }
        }
        
        if ($error_count == 0) {
            $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);
            if(isset($google_resp["Success"]))
            {
                $resp = array('status' => 1, 'response_msg' => 'inventory updation successfully');
                return $resp;
            }
            else
            {  
                $resp = array('status' => 0, 'response_msg' => $xmlString);
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => $xmlString);
            return $resp;
        }
    }

    public function impDate($data, $request)
    {
        $hotel_id = $data['hotel_id'];
        $ota_id = $data['ota_id'];
        $client_ip = $this->ipService->getIPAddress(); //get client ip
        $user_id = 0;
        if (isset($request->auth->admin_id)) {
            $user_id = $request->auth->admin_id;
        } else if (isset($request->auth->intranet_id)) {
            $user_id = $request->auth->intranet_id;
        } else if (isset($request->auth->id)) {
            $user_id = $request->auth->id;
        } else {
            if ($data['admin_id'] != 0) {
                $user_id = $data['admin_id'];
            }
        }

        return array('hotel_id' => $hotel_id, 'ota_id' => $ota_id, 'client_ip' => $client_ip, 'user_id' => $user_id);
    }

    function isJSON($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }

    public function getExtraAdultChildPrice($data, $source)
    {
        $conds = array('hotel_id' => $data['hotel_id'], 'room_type_id' => $data['room_type_id'], 'rate_plan_id' => $data['rate_plan_id']);
        $getPriceDetails = MasterHotelRatePlan::select('extra_adult_price', 'extra_child_price')
            ->where($conds)
            ->first();
        if ($getPriceDetails) {
            if ($source = 1) {
                return $getPriceDetails->extra_adult_price;
            } else {
                return $getPriceDetails->extra_child_price;
            }
        } else {
            return 0;
        }
    }

    public function gstPrice($bar_price)
    {
        $percentage = 0;
        if ($bar_price > 7500) {
            $percentage = 18;
        } elseif ($bar_price > 1000 && $bar_price <= 7500) {
            $percentage = 12;
        }
        $gstprice = $bar_price * $percentage / 100;
        return $gstprice;
    }

    public function checkBarPriceAndMultipleOccupancy($hotel_id, $room_type_id, $rate_plan_id, $data)
    {
        $price = MasterHotelRatePlan::select('min_price', 'max_price')->where('hotel_id', $hotel_id)->where('room_type_id', $room_type_id)->where('rate_plan_id', $rate_plan_id)->orderBy('room_rate_plan_id', 'DESC')->first();
        $bp = 0;
        if ($data['bar_price'] >= $price->min_price && $data['bar_price'] < $price->max_price) {
            $bp = 1;
        }
        if ($bp == 0) {
            $res = array('status' => 0, 'message' => "price should be equal or greater than: " . $price->min_price . " and should be lessthan: " . $price->max_price);
            return $res;
        }
        if($data['multiple_occupancy'] != "")
        {
            if (count($data['multiple_occupancy']) == 0) {
                $data['multiple_occupancy'][0] = $data['bar_price'];
            }
        }
        else{
            $data['multiple_occupancy'][0] = $data['bar_price'];
        }
        
        $multi_price = $data['multiple_occupancy'];
        if (count($multi_price) > 0) {
            foreach ($multi_price as $key => $multiprice) {
                $mp = 0;
                if ($multiprice == 0 || $multiprice == '') {
                    $rate['multiple_occupancy'][$key] = $data['bar_price'];
                }
                if ($multiprice >= $price->min_price && $multiprice < $price->max_price) {
                    $mp = $mp + 1;
                }
            }
        }
        if ($mp == 0) {
            $res = array('status' => 0, 'message' => "multiple occupancy should be equal or greater than: " . $price->min_price . " and should be lessthan: " . $price->max_price);
            return $res;
        }

        if ($bp > 0 && $mp > 0) {
            return 1;
        }
    }

    //Added by Jigyans dt : - 05-04-2023
    public function oldroomTypeWiseCalendarUpdate(Request $request)
    {
        $data = $request->all();
        $hotel_inventory = new Inventory();
        $hotel_id = $data['hotel_id'];
        $room_type_id = (int)($data['room_type_id']);
        $from_date = date('Y-m-d', strtotime($data['from_date']));
        $duration = $data['duration'] - 1;
        $to_date = date('Y-m-d', strtotime($from_date . "+" . $duration . "days"));
        $source_ota_name = $data['source_ota_name'];
        
        $get_inventory_url = "https://cm.bookingjini.com/extranetv4/getInventory_new/" . $hotel_id . "/" . $from_date . "/" . $to_date . "/" . $room_type_id ;
        $get_inventory_datas = Commonmodel::curlGet($get_inventory_url);
        $imp_data = $this->impDate($data, $request);
        // print_r($get_inventory_datas);exit;
        $check_blocked_inventory_datas = 0;
        $be_blocked_inventory_datas = array();
        $be_all_inventory_datas = array();
        if (isset($get_inventory_datas->status)) {
            if ($get_inventory_datas->status == 1) {
                if (count($get_inventory_datas->data) > 0) {
                    $get_inventory_datas = collect($get_inventory_datas->data)->where("ota_name", $source_ota_name)->values()->all();
                    if(count($get_inventory_datas) > 0)
                    {
                        $get_inventory_datas = collect($get_inventory_datas[0]->inv)->where("room_type_id",$room_type_id)->values();
                        if(count($get_inventory_datas->all()) > 0)
                        {
                            $be_all_inventory_datas = json_decode(json_encode($get_inventory_datas->all()),true);
                            $be_blocked_inventory_datas =  json_decode(json_encode($get_inventory_datas->where("block_status",1)->values()->all()),true);
                        }
                    }
                } 
            } 
        }

        if(count($be_all_inventory_datas) > 0)
        {
            $invs = array();
            
            // $start_currinventory_time = microtime(true);
            foreach($be_all_inventory_datas as $RasInventoryDatas)
            {
                $invs[] = array(
                    "hotel_id"      => $hotel_id,
                    "room_type_id"  => $room_type_id,
                    "no_of_rooms"        => $RasInventoryDatas['no_of_rooms'],
                    "date_from"        => $RasInventoryDatas['date'],
                    "date_to"        => $RasInventoryDatas['date'],
                    "block_status"      => $RasInventoryDatas["block_status"],
                    "los"      => $RasInventoryDatas["los"],
                    "user_id"  => $imp_data["user_id"],
                    "client_ip"   => $imp_data["client_ip"],
                    "multiple_days" => '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}'
                );

                $current_inv = array(
                    "hotel_id"      =>$hotel_id,
                    "room_type_id"  => $room_type_id,
                    "ota_id"        => -1,
                    "stay_day"      => $RasInventoryDatas['date'],
                    "block_status"  => $RasInventoryDatas["block_status"],
                    "no_of_rooms"   => $RasInventoryDatas['no_of_rooms'],
                    "los"  => $RasInventoryDatas["los"],
                    "ota_name"      => "Bookingjini"
                );
                $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                    [
                        'hotel_id' => $hotel_id,
                        'room_type_id' =>  $room_type_id,
                        'ota_id' => -1,
                        'stay_day' => $RasInventoryDatas['date'],
                    ],
                    $current_inv
                );
                
            }
            // $end_curr_time = microtime(true);
            // $execution_curr_time = ($end_curr_time - $start_currinventory_time);
            // print_r(array("dynamicpricing" =>$execution_curr_time));

            
            // $start_inventory_time = microtime(true);

            $inventorys = $hotel_inventory->insert($invs);

            if($inventorys == 1)
            {
                $resp = array('status' => 1, 'response_msg' => 'Inventory update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                return response()->json(array($resp));
            }
            else
            {
                $resp = array('status' => 0, 'response_msg' => 'Inventory was not able to update in Booking engine', 'ota_name' => 'Bookingjini');
                return response()->json(array($resp));

            }

            // $end_inventory_time = microtime(true);
            // $execution_inventory_time = ($end_inventory_time - $start_inventory_time);
            // print_r(array("inventory_table_time" =>$execution_inventory_time));

            // print_r($inventorys."@@@@@@@@@@");exit;
            
            // $data = ["status" => 1, "ota_name" => "BookingEngine", "response_msg" => "Insert data successfull"];
        }
        else
        {
            $resp = array('status' => 0, 'response_msg' => 'Inventory was not able to fetch from '.$source_ota_name, 'ota_name' => 'Bookingjini');
            return response()->json($resp);
        }
    }


    //Added by Jigyans dt : - 05-04-2023
    public function roomTypeWiseCalendarUpdate(Request $request)
    {
        try{
            $data = $request->all();
            $hotel_inventory = new Inventory();
            $hotel_id = $data['hotel_id'];
            $inv_details = $data['inv'][0]['inv'];
            $data['ota_id'] = -1;
            $imp_data = $this->impDate($data, $request);
            $room_type_info = array_values(array_column($inv_details,"room_type_id"));

            if(count($inv_details) > 0)
            {
                $all_dates = array_column($inv_details,"date");
                $from_date = min($all_dates);
                $to_date = max($all_dates);

                $get_inventory_url = "https://cm.bookingjini.com/extranetv4/inventory/getInventory_new_channelwise/" . $hotel_id . "/" . $from_date . "/" . $to_date . "/" .$data['ota_id'];
                $get_inventory_datas = Commonmodel::curlGet($get_inventory_url)->data->room_type_info;
                $getinventorydatas = collect($get_inventory_datas);

                $invs = array();
                $count_inv_details = 0;
                $single_inv_details = array();
                foreach($inv_details as $RasInventoryDatas)
                {
                    if(!isset($RasInventoryDatas['los']) || !isset($RasInventoryDatas['no_of_rooms']))
                    {
                        $single_inv_details[$count_inv_details] = $getinventorydatas->where("room_type_id",$RasInventoryDatas['room_type_id'])->values()->all()[0]->inv;
                        $single_inv_details[$count_inv_details] = collect($single_inv_details[$count_inv_details])->where("date",$RasInventoryDatas['date'])->values()->all();
                        if(!isset($RasInventoryDatas['los']))
                        {
                            $RasInventoryDatas['los'] = $single_inv_details[$count_inv_details][0]->los;
                        }
                        if(!isset($RasInventoryDatas['no_of_rooms']))
                        {
                            $RasInventoryDatas['no_of_rooms'] = $single_inv_details[$count_inv_details][0]->no_of_rooms;
                        }
                    }

                    $inv_details[$count_inv_details]['no_of_rooms'] = $RasInventoryDatas['no_of_rooms'];
                    $inv_details[$count_inv_details]['los'] = $RasInventoryDatas['los'];

                    $invs[] = array(
                        "hotel_id" => $hotel_id,
                        "room_type_id" => $RasInventoryDatas['room_type_id'],
                        "no_of_rooms" => $RasInventoryDatas['no_of_rooms'],
                        "date_from" => $RasInventoryDatas['date'],
                        "date_to" => $RasInventoryDatas['date'],
                        "block_status" => $RasInventoryDatas["block_status"],
                        "los"      => $RasInventoryDatas["los"],
                        "user_id"  => $imp_data["user_id"],
                        "client_ip"   => $imp_data["client_ip"],
                        "multiple_days" => '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}'
                    );

                    $current_inv = array(
                        "hotel_id"      =>$hotel_id,
                        "room_type_id"  => $RasInventoryDatas['room_type_id'],
                        "ota_id"        => -1,
                        "stay_day"      => $RasInventoryDatas['date'],
                        "block_status"  => $RasInventoryDatas["block_status"],
                        "no_of_rooms"   => $RasInventoryDatas['no_of_rooms'],
                        "los"  => $RasInventoryDatas["los"],
                        "ota_name"      => "Bookingjini"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id' => $hotel_id,
                            'room_type_id' =>  $RasInventoryDatas['room_type_id'],
                            'ota_id' => -1,
                            'stay_day' => $RasInventoryDatas['date'],
                        ],
                        $current_inv
                    );

                    $cur_inv = DynamicPricingCurrentInventoryBe::updateOrInsert(
                        [
                            'hotel_id' => $hotel_id,
                            'room_type_id' =>  $RasInventoryDatas['room_type_id'],
                            'ota_id' => -1,
                            'stay_day' => $RasInventoryDatas['date'],
                        ],
                        $current_inv
                    );   
                    $count_inv_details++;
                }

                // $start_ex_time = microtime(true);
                if ($this->googleHotelStatus($hotel_id) == true) {
                    $update = $this->roomTypeWiseinventoryUpdateToGoogleAds($inv_details,$hotel_id);
                    if($update['status'] == 0)
                    {
                        return  $return_resp = array('status' => 0, 'response_msg' => $update['response_msg'], 'ota_name' => 'Bookingjini');
                    }
                    elseif($update['status'] == 1)
                    {
                       $los_update = $this->roomTypeWiselosUpdateToGoogleAds($inv_details,$hotel_id);
                    }
                }

                // $end_ex_time = microtime(true);
                // $execution_ex_time = ($end_ex_time - $start_ex_time);
                // print_r(array("beforecurrenttableentry" =>$execution_ex_time));
                $inventorys = $hotel_inventory->insert($invs);

                if($inventorys == 1)
                {
                    $resp = array('status' => 1, 'response_msg' => 'Inventory update sucessfully in Booking engine', 'ota_name' => 'Bookingjini','room_type_info' =>$room_type_info);
                    return response()->json(array($resp));
                }
                else
                {
                    $resp = array('status' => 0, 'response_msg' => 'Inventory was not able to update in Booking engine', 'ota_name' => 'Bookingjini','room_type_info' =>$room_type_info);
                    return response()->json(array($resp));

                }
            }
            else
            {
                $resp = array('status' => 0, 'response_msg' => '', 'ota_name' => 'Bookingjini','room_type_info' =>$room_type_info);
                return response()->json($resp);
            }
        } catch (\Exception $e) {
            $res = array('status' => -1, 'response_msg' => $e->getMessage());

            $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => array("method" => $request->method(), "request" => $request->all()));

            $result = json_encode($result);
            $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);

            return response()->json($res);
        }
    }


    //Added by Jigyans dt : - 18-05-2023
    public function roomTypeWiseinventoryUpdateToGoogleAds($data,$hotel_id)
    {
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $inv_start_date_range = $data;
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelInvCountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                            EchoToken="' . $id . '"
                                            TimeStamp="' . $time . '"
                                            Version="3.0">
                <POS>
                    <Source>
                    <RequestorID ID="bookingjini_ari"/>
                    </Source>
                </POS>
                <Inventories HotelCode="' . $hotel_id . '">';
                
        // $from_date = date('Y-m-d', strtotime($from_date));
        // $to_date = date('Y-m-d', strtotime($to_date));
        $counter = 0;
        foreach($inv_start_date_range as $RsStartDate)
        {
            $xml_data .=   '<Inventory>
            <StatusApplicationControl Start="' . $RsStartDate['date'] . '"
                                        End="' . $RsStartDate['date'] . '"
                                        InvTypeCode="' . $RsStartDate['room_type_id'] . '"/>
            <InvCounts>
                <InvCount Count="' . $RsStartDate['no_of_rooms'] . '" CountType="2"/>
            </InvCounts>
            </Inventory>';
            $counter++;
        }
        

        $xml_data .=   '</Inventories>
        </OTA_HotelInvCountNotifRQ>';
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_inv_count_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resps = curl_exec($ch);
        curl_close($ch);
        $xmlString = $google_resps;
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlString);

        $error_count = 0;
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $error_count++;
            }
        }
        
        if ($error_count == 0) {
            $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);
            if(isset($google_resp["Success"]))
            {
                $resp = array('status' => 1, 'response_msg' => 'inventory updation successfully');
                return $resp;
            }
            else
            {  
                $resp = array('status' => 0, 'response_msg' => $xmlString);
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => $xmlString);
            return $resp;
        }
    }

    //Added by Jigyans dt : - 18-05-2023
    public function roomTypeWiselosUpdateToGoogleAds($data,$hotel_id)
    {
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $multiple_days = '{"Sun":1,"Mon":1,"Tue":1,"Wed":1,"Thu":1,"Fri":1,"Sat":1}';
        $multiple_days = json_decode($multiple_days);
        $inv_start_date_range = $data;

        $getRatePlans = MasterHotelRatePlan::select('*')
                    ->where('hotel_id', $hotel_id)
                    ->get();

        foreach ($multiple_days as $key => $days) {
            if ($key == 'Mon') {
                $Mon = $days;
            }
            if ($key == 'Tue') {
                $Tue = $days;
            }
            if ($key == 'Wed') {
                $Weds = $days;
            }
            if ($key == 'Thu') {
                $Thur = $days;
            }
            if ($key == 'Fri') {
                $Fri = $days;
            }
            if ($key == 'Sat') {
                $Sat = $days;
            }
            if ($key == 'Sun') {
                $Sun = $days;
            }
        }
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                    EchoToken="' . $id . '"
                                    TimeStamp="' . $time . '"
                                    Version="3.0">
                <POS>
                    <Source>
                    <RequestorID ID="bookingjini_ari"/>
                    </Source>
                </POS>
                <AvailStatusMessages HotelCode="' . $hotel_id . '">';

        $rate_plan_datas = array();        
        $counter = 0;
        foreach($inv_start_date_range as $RsStartDate)
        {
            $rate_plan_datas[$counter] = $getRatePlans->where("room_type_id",$RsStartDate['room_type_id'])->values()->toArray();
            foreach($rate_plan_datas[$counter] as $Rslosdetails)
            {
                $xml_data .=   '<AvailStatusMessage>
                <StatusApplicationControl Start="' . $RsStartDate['date'] . '"
                                            End="' . $RsStartDate['date'] . '"
                                            Mon="' . $Mon . '"
                                            Tue="' . $Tue . '"
                                            Weds="' . $Weds . '"
                                            Thur="' . $Thur . '"
                                            Fri="' . $Fri . '"
                                            Sat="' . $Sat . '"
                                            Sun="' . $Sun . '"
                                            InvTypeCode="' . $RsStartDate['room_type_id'] . '"
                                            RatePlanCode="' . $Rslosdetails['rate_plan_id'] . '"/>
                    <LengthsOfStay>
                                <LengthOfStay Time="' . $RsStartDate['los'] . '" TimeUnit="Day" MinMaxMessageType="SetMinLOS"/>
                    </LengthsOfStay>
                </AvailStatusMessage>';
            }
            $counter++;
        }

        $xml_data .=   '</AvailStatusMessages></OTA_HotelAvailNotifRQ>';

        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);

        $xmlString = $google_resp;

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlString);

        $error_count = 0;
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $error_count++;
            }
        }
        
        if ($error_count == 0) {
            $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
            if(isset($google_resp["Success"]))
            {
                $resp = array('status' => 1, 'response_msg' => 'los updation successfully');
                return $resp;
            }
            else
            {  
                $resp = array('status' => 0, 'response_msg' => $xmlString);
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => $xmlString);
            return $resp;
        }
    }

    //Added by Jigyans dt : - 19-05-2023
    public function inventoryBlockSyncToGoogleAds($data,$hotel_id)
    {
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        
        $inv_start_date_range = $data;

        $getRatePlans = MasterHotelRatePlan::select('*')
                    ->where('hotel_id', $hotel_id)
                    ->get();


        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
        <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                        EchoToken="' . $id . '"
                                        TimeStamp="' . $time . '"
                                        Version="3.0">
        <AvailStatusMessages HotelCode="' . $hotel_id . '">';

        $rate_plan_datas = array();        
        $counter = 0;
        foreach($inv_start_date_range as $RsStartDate)
        {
            $rate_plan_datas[$counter] = $getRatePlans->where("room_type_id",$RsStartDate['room_type_id'])->values()->toArray();
            foreach($rate_plan_datas[$counter] as $Rslosdetails)
            {
                $xml_data .=   '<AvailStatusMessage>
                <StatusApplicationControl Start="' . $RsStartDate['date'] . '"
                                            End="' . $RsStartDate['date'] . '"
                                            InvTypeCode="' . $RsStartDate['room_type_id'] . '"
                                            RatePlanCode="' . $Rslosdetails['rate_plan_id'] . '"/>
                    <RestrictionStatus Status="Close" Restriction="Master"/>
                    </AvailStatusMessage>';
            }
            $counter++;
        }

        $xml_data .=   '</AvailStatusMessages>
        </OTA_HotelAvailNotifRQ>';
        
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resps = curl_exec($ch);
        curl_close($ch);
        // $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);


        $xmlString = $google_resps;

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlString);

        $error_count = 0;
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                // print_r($error->message);
                $error_count++;
            }
        }
        
        if ($error_count == 0) {
            $google_resp = json_decode(json_encode(simplexml_load_string($google_resps)), true);
            if(isset($google_resp["Success"]))
            {
                $resp = array('status' => 1, 'response_msg' => 'inventory blocked successfully');
                return $resp;
            }
            else
            {  
                $resp = array('status' => 0, 'response_msg' => $xmlString);
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => $xmlString);
            return $resp;
        }
    }


    //Added by Jigyans dt : - 09-06-2023
    public function multiRoomtypeMultiRatePlanMultiDatesUpdate(Request $request)
    {
        $rate_plan_log = new RatePlanLog();
        $logModel      = new RateUpdateLog();
        $base_rate     = new BaseRate();
        
        $data = $request->all();
        $data['ota_id'] = $data['rates'][0]['ota_id'];
        $imp_data = $this->impDate($data, $request);

        $resp = array();
        $hotel_id = $data['hotel_id'];

        $multiple_days = array(
            'Mon' => 1,
            'Tue' => 1,
            'Wed' => 1,
            'Thu' => 1,
            'Fri' => 1,
            'Sat' => 1,
            'Sun' => 1
        );

        $be_rate_details = $data['rates'][0]['rates'];
        
        $stay_dates = array_values(array_unique(array_column($be_rate_details, "date")));

        $be_rate_datas = array();
        $current_be_rate_datas = array();
        foreach($be_rate_details as $RsResult)
        {
            $be_rate_datas[] = array(
                "hotel_id" => $hotel_id,
                "room_type_id" => $RsResult['room_type_id'],
                "rate_plan_id" => $RsResult['rate_plan_id'],
                "multiple_occupancy" => json_encode($RsResult['multiple_occupancy']),
                "bar_price" => $RsResult['bar_price'],
                "from_date" => date("Y-m-d",strtotime($RsResult['date'])),
                "to_date" => date("Y-m-d",strtotime($RsResult['date'])),
                "multiple_days" => json_encode($multiple_days),
                "block_status" => 0,
                "los" => $RsResult['los'],
                "client_ip" => $imp_data['client_ip'],
                "user_id" => $imp_data['user_id'],
                "extra_adult_price" => $RsResult['extra_adult_price'],
                "extra_child_price" => $RsResult['extra_child_price']
            );

            $current_be_rate_datas[] = array(
                "hotel_id" => $hotel_id,
                "ota_id" => -1,
                "ota_name" => "Bookingjini",
                "room_type_id" => $RsResult['room_type_id'],
                "rate_plan_id" => $RsResult['rate_plan_id'],
                "stay_date" => date("Y-m-d",strtotime($RsResult['date'])),
                "bar_price" => $RsResult['bar_price'],
                "multiple_occupancy" => json_encode($RsResult['multiple_occupancy']),
                "multiple_days" => json_encode($multiple_days),
                "extra_adult_price" => $RsResult['extra_adult_price'],
                "extra_child_price" => $RsResult['extra_child_price'],
                "block_status" => 0,
                "los" => $RsResult['los'],
            );
        }


        // foreach ($current_be_rate_datas as $RsCurrentInv) {
        //     CurrentRate::updateOrInsert(array(
        //         "hotel_id" => $hotel_id,
        //         "ota_id" => -1,
        //         "room_type_id" => $RsCurrentInv['room_type_id'],
        //         "rate_plan_id" => $RsCurrentInv['rate_plan_id'],
        //         "stay_date" => date("Y-m-d",strtotime($RsCurrentInv['stay_date'])),
        //     ), $RsCurrentInv);
        // }



        // print_r($current_be_rate_datas);exit;

        if (count($be_rate_datas) > 0) {
            $google_hotel_status = "";

            $rate_plan_log_ids = [];
            foreach($be_rate_datas as $RsBeRateDatas)
            {
                $rate_plan_log_ids[] = $rate_plan_log->insertGetId($RsBeRateDatas);
            }
            $index = "";
            if (count($rate_plan_log_ids) > 0) {
                if (!isset($imp_data['dp_status'])) {
                    $insertBaseRate = $base_rate->insert($be_rate_datas);
                }
                $test_array = array(
                    "hotel_id" => $hotel_id,
                    "ota_id" => -1
                );
                $current_rates = array();
                $current_rate_data = array();
                if ($this->googleHotelStatus($hotel_id)) {

                    foreach ($multiple_days as $key => $days) {
                        if ($key == 'Mon') {
                            $Mon = $days;
                        }
                        if ($key == 'Tue') {
                            $Tue = $days;
                        }
                        if ($key == 'Wed') {
                            $Weds = $days;
                        }
                        if ($key == 'Thu') {
                            $Thur = $days;
                        }
                        if ($key == 'Fri') {
                            $Fri = $days;
                        }
                        if ($key == 'Sat') {
                            $Sat = $days;
                        }
                        if ($key == 'Sun') {
                            $Sun = $days;
                        }
                    }

                    $id = uniqid();
                    $time = time();
                    $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';

                    $xml_common_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                        EchoToken="' . $id . '"
                                                        TimeStamp="' . $time . '"
                                                        Version="3.0"
                                                        NotifType="Overlay"
                                                        NotifScopeType="ProductRate">
                    <POS>
                        <Source>
                            <RequestorID ID="bookingjini_ari"/>
                        </Source>
                    </POS>';

                    $xml_los_header_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                            EchoToken="' . $id . '"
                                            TimeStamp="' . $time . '"
                                            Version="3.0">
                        <POS>
                            <Source>
                            <RequestorID ID="bookingjini_ari"/>
                            </Source>
                        </POS>';

                    $xml_data = $xml_common_data . '<RateAmountMessages HotelCode="' . $hotel_id . '">';

                    $los_xml_data = $xml_los_header_data . '<AvailStatusMessages HotelCode="' . $hotel_id . '">';

                    foreach($be_rate_datas as $Rsresult)
                    {
                        $price = (float) $Rsresult['bar_price'];
                        $getGstprice = $this->gstPrice($price);
                        
                        $totalprice = (float)($price + $getGstprice);
                        $xml_data .= '<RateAmountMessage>
                        <StatusApplicationControl Start="' . $Rsresult['from_date'] . '"
                                                        End="' . $Rsresult['to_date'] . '"
                                                        Mon="' . $Mon . '"
                                                        Tue="' . $Tue . '"
                                                        Weds="' . $Weds . '"
                                                        Thur="' . $Thur . '"
                                                        Fri="' . $Fri . '"
                                                        Sat="' . $Sat . '"
                                                        Sun="' . $Sun . '"
                                                        InvTypeCode="' . $Rsresult['room_type_id'] . '"
                                                        RatePlanCode="' . $Rsresult['rate_plan_id'] . '"/>
                                            <Rates>
                                                <Rate>
                                                    <BaseByGuestAmts>
                                                        <BaseByGuestAmt AmountBeforeTax="' . $price . '"
                                                                        AmountAfterTax="' . $totalprice . '"
                                                                        CurrencyCode="INR"
                                                                        NumberOfGuests="2"/>
                                                    </BaseByGuestAmts>
                                                </Rate>
                                            </Rates>
                                        </RateAmountMessage>';

                        $los_xml_data .= '<AvailStatusMessage>
                        <StatusApplicationControl Start="' . $Rsresult['from_date'] . '"
                                                    End="' . $Rsresult['to_date'] . '"
                                                    Mon="' . $Mon . '"
                                                    Tue="' . $Tue . '"
                                                    Weds="' . $Weds . '"
                                                    Thur="' . $Thur . '"
                                                    Fri="' . $Fri . '"
                                                    Sat="' . $Sat . '"
                                                    Sun="' . $Sun . '"
                                                    InvTypeCode="' . $Rsresult['room_type_id'] . '"
                                                    RatePlanCode="' . $Rsresult['rate_plan_id'] . '"/>
                        <LengthsOfStay>
                        <LengthOfStay Time="' . $Rsresult['los'] . '"
                                    TimeUnit="Day"
                                    MinMaxMessageType="SetMinLOS"/>
                        </LengthsOfStay>
                        </AvailStatusMessage>';
                    }

                    $los_xml_data .= '</AvailStatusMessages>
                                </OTA_HotelAvailNotifRQ>';
                    $xml_data .= '</RateAmountMessages>
                    </OTA_HotelRateAmountNotifRQ>';
                    $headers = array(
                        "Content-Type: application/xml",
                    );
                    
                    // $start_all_ota_rate_log_time = microtime(true);

                    $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_rate_amount_notif';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
                    $google_resp = curl_exec($ch);
                    curl_close($ch);
                    $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);

                    // $end_all_ota_rate_update_log_time = microtime(true);
                    // $execution_all_ota_rate_log_time = ($end_all_ota_rate_update_log_time - $start_all_ota_rate_log_time);
                    // print_r(array("curl_response" =>$execution_all_ota_rate_log_time));

                    if (isset($google_resp["Success"])) {

                        // $start_all_ota_rate_log_time2 = microtime(true);

                        $current_rate_all_datas_get = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates);
                        $check_current_rate_all_datas_get = $current_rate_all_datas_get->get();

                        // $end_all_ota_rate_update_log_time2 = microtime(true);
                        // $execution_all_ota_rate_log_time2 = ($end_all_ota_rate_update_log_time2 - $start_all_ota_rate_log_time2);
                        // print_r(array("retrieve_from_current_rate_table" =>$execution_all_ota_rate_log_time2));exit;

                        if(count($check_current_rate_all_datas_get) > 0)
                        {
                            $new_rate_datas = array();
                            $update_ids = array();
                            $check_rates_datas = "";
                            foreach ($be_rate_datas as $RsDatas) {
                                $check_rates_datas = $check_current_rate_all_datas_get->where("room_type_id", $RsDatas['room_type_id'])->where("rate_plan_id", $RsDatas['rate_plan_id'])->where("stay_date", $RsDatas['from_date'])->first();
                                // print_r($check_rates_datas->toArray());exit;
                                if ($check_rates_datas == NULL) {
                                    $new_rate_datas[] = array(
                                        "hotel_id" => $hotel_id,
                                        "ota_id" => -1,
                                        "ota_name" => "Bookingjini",
                                        "room_type_id" => $RsDatas['room_type_id'],
                                        "rate_plan_id" => $RsDatas['rate_plan_id'],
                                        "stay_date" => $RsDatas['from_date'],
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                } else {
                                    $update_ids["id"][] = $check_rates_datas->id;
                                    $update_ids["rates_data"][] = array(
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                }
                            }
                            if (count($new_rate_datas) > 0) {

                                // $start_all_ota_rate_log_time3 = microtime(true);

                                $test_current_rate = CurrentRate::insert($new_rate_datas);

                                
                                // $end_all_ota_rate_update_log_time3 = microtime(true);
                                // $execution_all_ota_rate_log_time3 = ($end_all_ota_rate_update_log_time3 - $start_all_ota_rate_log_time3);
                                // print_r(array("insert_into_current_rate" =>$execution_all_ota_rate_log_time3));

                            }
                            if(count($update_ids) > 0)
                            {
                                
                                // $start_all_ota_rate_log_time4 = microtime(true);

                                $updateratedatas = array();
                                $counter = 0;
                                foreach($update_ids['rates_data'] as $RsUpdateRates)
                                {
                                    $updateratedatas = array(
                                    "bar_price" => $RsUpdateRates['bar_price'],
                                    "multiple_occupancy" => $RsUpdateRates['multiple_occupancy'],
                                    "multiple_days" => $RsUpdateRates['multiple_days'],
                                    "extra_adult_price" => $RsUpdateRates['extra_adult_price'],
                                    "extra_child_price" => $RsUpdateRates['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsUpdateRates['los'],
                                    );
                                    $testing = CurrentRate::where("id", $update_ids['id'][$counter])->update($updateratedatas);
                                    $counter++;
                                }

                                
                                // $end_all_ota_rate_update_log_time4 = microtime(true);
                                // $execution_all_ota_rate_log_time4 = ($end_all_ota_rate_update_log_time4 - $start_all_ota_rate_log_time4);
                                // print_r(array("update_into_current_rate" =>$execution_all_ota_rate_log_time4));

                            }
                        }
                        else
                        {
                            
                            $start_all_ota_rate_log_time5 = microtime(true);

                            $bookingjini_rates_data = [];
                            foreach($be_rate_datas as $RsDatas)
                            {
                                $bookingjini_rates_data[] = array(
                                    "hotel_id" => $hotel_id,
                                    "ota_id" => -1,
                                    "ota_name" => "Bookingjini",
                                    "room_type_id" => $RsDatas['room_type_id'],
                                    "rate_plan_id" => $RsDatas['rate_plan_id'],
                                    "stay_date" => $RsDatas['from_date'],
                                    "bar_price" => $RsDatas['bar_price'],
                                    "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                    "multiple_days" => $RsDatas['multiple_days'],
                                    "extra_adult_price" => $RsDatas['extra_adult_price'],
                                    "extra_child_price" => $RsDatas['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsDatas['los']
                                );
                            }
                            $test_current_rate = CurrentRate::insert($bookingjini_rates_data);

                            // $end_all_ota_rate_update_log_time5 = microtime(true);
                            // $execution_all_ota_rate_log_time5 = ($end_all_ota_rate_update_log_time5 - $start_all_ota_rate_log_time5);
                            // print_r(array("insert_into_current_rate_full" =>$execution_all_ota_rate_log_time5));

                        }


                        $start_all_ota_rate_log_time6 = microtime(true);

                        $log_data                 = [
                            "action_id"          => 2,
                            "hotel_id"           => $imp_data['hotel_id'],
                            "ota_id"             => -1,
                            "rate_ref_id"        => implode(",",$rate_plan_log_ids),
                            "user_id"            => $imp_data['user_id'],
                            "request_msg"        => '',
                            "response_msg"       => '',
                            "request_url"        => '',
                            "status"               => 1,
                            "ip"                   => $imp_data['client_ip'],
                            "comment"              => "Processing for update "
                        ];
                        $logModel->fill($log_data)->save(); //saving pre log data

                        
                            
                        // $end_all_ota_rate_update_log_time6 = microtime(true);
                        // $execution_all_ota_rate_log_time6 = ($end_all_ota_rate_update_log_time6 - $start_all_ota_rate_log_time6);
                        // print_r(array("insert_into_log_table" =>$execution_all_ota_rate_log_time6));

                        $headers = array(
                            "Content-Type: application/xml",
                        );



                        $start_all_ota_rate_log_time7 = microtime(true);

                        $losurl =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
                        $los_ch = curl_init();
                        curl_setopt($los_ch, CURLOPT_URL, $losurl);
                        curl_setopt($los_ch, CURLOPT_POST, true);
                        curl_setopt($los_ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($los_ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($los_ch, CURLOPT_POSTFIELDS, $los_xml_data);
                        $google_los_resp = curl_exec($los_ch);
                        curl_close($los_ch);

                        
                            
                        // $end_all_ota_rate_update_log_time7 = microtime(true);
                        // $execution_all_ota_rate_log_time7 = ($end_all_ota_rate_update_log_time7 - $start_all_ota_rate_log_time7);
                        // print_r(array("los_curl_call" =>$execution_all_ota_rate_log_time7));

                        $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                        return $resp;
                    } else {
                        $resp = array('status' => 0, 'response_msg' => $google_resp, 'ota_name' => 'Bookingjini');
                        return $resp;
                    }
                } else {
                        $current_rate_all_datas_get = CurrentRate::where($test_array)->whereIn("stay_date", $stay_dates);
                        $check_current_rate_all_datas_get = $current_rate_all_datas_get->get();

                        if(count($check_current_rate_all_datas_get) > 0)
                        {
                            $new_rate_datas = array();
                            $update_ids = array();
                            $check_rates_datas = "";
                            foreach ($be_rate_datas as $RsDatas) {
                                $check_rates_datas = $check_current_rate_all_datas_get->where("room_type_id", $RsDatas['room_type_id'])->where("rate_plan_id", $RsDatas['rate_plan_id'])->where("stay_date", $RsDatas['from_date'])->first();
                                if ($check_rates_datas == NULL) {
                                    $new_rate_datas[] = array(
                                        "hotel_id" => $hotel_id,
                                        "ota_id" => -1,
                                        "ota_name" => "Bookingjini",
                                        "room_type_id" => $RsDatas['room_type_id'],
                                        "rate_plan_id" => $RsDatas['rate_plan_id'],
                                        "stay_date" => $RsDatas['from_date'],
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                } else {
                                    $update_ids["id"][] = $check_rates_datas->id;
                                    $update_ids["rates_data"][] = array(
                                        "bar_price" => $RsDatas['bar_price'],
                                        "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                        "multiple_days" => $RsDatas['multiple_days'],
                                        "extra_adult_price" => $RsDatas['extra_adult_price'],
                                        "extra_child_price" => $RsDatas['extra_child_price'],
                                        "block_status" => 0,
                                        "los" => $RsDatas['los'],
                                    );
                                }
                            }
                            if (count($new_rate_datas) > 0) {
                                $test_current_rate = CurrentRate::insert($new_rate_datas);
                            }
                            if(count($update_ids) > 0)
                            {
                                $updateratedatas = array();
                                $counter = 0;
                                foreach($update_ids['rates_data'] as $RsUpdateRates)
                                {
                                    $updateratedatas = array(
                                    "bar_price" => $RsUpdateRates['bar_price'],
                                    "multiple_occupancy" => $RsUpdateRates['multiple_occupancy'],
                                    "multiple_days" => $RsUpdateRates['multiple_days'],
                                    "extra_adult_price" => $RsUpdateRates['extra_adult_price'],
                                    "extra_child_price" => $RsUpdateRates['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsUpdateRates['los'],
                                    );
                                    $testing = CurrentRate::where("id", $update_ids['id'][$counter])->update($updateratedatas);
                                    $counter++;
                                }
                            }
                        }
                        else
                        {
                            $bookingjini_rates_data = [];
                            foreach($be_rate_datas as $RsDatas)
                            {
                                $bookingjini_rates_data[] = array(
                                    "hotel_id" => $hotel_id,
                                    "ota_id" => -1,
                                    "ota_name" => "Bookingjini",
                                    "room_type_id" => $RsDatas['room_type_id'],
                                    "rate_plan_id" => $RsDatas['rate_plan_id'],
                                    "stay_date" => $RsDatas['from_date'],
                                    "bar_price" => $RsDatas['bar_price'],
                                    "multiple_occupancy" => $RsDatas['multiple_occupancy'],
                                    "multiple_days" => $RsDatas['multiple_days'],
                                    "extra_adult_price" => $RsDatas['extra_adult_price'],
                                    "extra_child_price" => $RsDatas['extra_child_price'],
                                    "block_status" => 0,
                                    "los" => $RsDatas['los']
                                );
                            }
                            $test_current_rate = CurrentRate::insert($bookingjini_rates_data);
                        }
                    $resp = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                    return $resp;
                }
            } else {
                $resp = array('status' => 0, 'response_msg' => 'rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                return $resp;
            }
        } else {
            $resp = array('status' => 0, 'response_msg' => 'No records found to update for this date', 'ota_name' => 'Bookingjini');
            return $resp;
        }
        return $resp;
    }
}
