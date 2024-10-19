<?php

namespace App\Http\Controllers\Extranetv4\invrateupdatecontrollers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Inventory;
use App\LogTable;
use App\RatePlanLog;
use App\RateUpdateLog;
use App\DerivedPlan;
use App\MetaSearchEngineSetting;
use App\MasterRoomType;
use App\MasterHotelRatePlan;
use App\BaseRate;
use App\DynamicPricingCurrentInventory;
use App\DynamicPricingCurrentInventoryBe;
use App\Coupons;
use App\CmOtaRoomTypeSynchronize;
use App\CurrentRate;
use App\CurrentRateBe;
use App\Model\Commonmodel;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Extranetv4\IpAddressService;
use App\Http\Controllers\Extranetv4\invrateupdatecontrollers\GetDataForRateController;
use App\Http\Controllers\Extranetv4\invrateupdatecontrollers\GetDataForInventoryController;
use App\HotelInformation;
/**
 * This controller is used for bookingengine single,bulk,sync and block of inv and rate
 * @auther swatishree padhy
 * created date 21/04/22.
 */

class BookingEngineInvRateControllerTest extends Controller
{
    protected $ipService;
    protected $getdata_curlreq;
    protected $getDataForInventory;

    
    public function __construct(IpAddressService $ipService, GetDataForRateController $getdata_curlreq, GetDataForInventoryController $getDataForInventory)
    {
        $this->ipService = $ipService;
        $this->getdata_curlreq = $getdata_curlreq;
        $this->getDataForInventory = $getDataForInventory;
    }
    private $inv_rules = array(
        'hotel_id' => 'required | numeric',
    );
    private $inv_messages = [
        'hotel_id.required' => 'Hotel id is required.',
        'hotel_id.numeric' => 'Hotel id should be numeric.',
    ];
    private $rules = array(
        'hotel_id' => 'required',
        'room_type_id' => 'required',
        'date_from' => 'required',
        'date_to' => 'required'
    );
    private $messages = [
        'hotel_id.required' => 'The hotel id is required.',
        'room_type_id.required' => 'The room type id is required.',
        'date_from.required' => 'The date from is required.',
        'date_to.required' => 'The date to is required.'
    ];
    private $blk_rules = array(
        'no_of_rooms' => 'required | numeric',
        'date_from' => 'required ',
        'date_to' => 'required ',
        'hotel_id' => 'required | numeric',
        'room_type_id' => 'required | numeric'
    );
    private $blk_messages = [
        'no_of_rooms.required' => 'No of rooms required.',
        'date_from.required' => 'From date is required.',
        'date_to.required' => 'To date is required.',
        'hotel_id.required' => 'Hotel id is required.',
        'room_type_id.required' => 'Room type id is required.',
        'hotel_id.numeric' => 'Hotel id should be numeric.',
        'no_of_rooms.numeric' => 'No of rooms should be numeric.',
        'date_from.date_format' => 'Date format should be Y-m-d',
        'date_to.date_format' => 'Date format should be Y-m-d'
    ];
    //single inventory update for booking engine
    public function singleInventoryUpdate(Request $request)
    {
        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $inventory = $data['inv'];
        $imp_data = $this->impDate($data, $request); //used to get user id and client ip.
        $resp = array();

        $inv_count = count($inventory['inv']);
        if ($inv_count > 60) {
            $check_google_hotel = $this->googleHotelStatus($imp_data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less then 60 days', 'ota_name' => 'bookingjini');
            }
        }

        foreach ($imp_data['ota_id'] as $otaid) {
            if ($otaid == -1) { //booking engine update
                $flag = 0;
                $count = 0;

                $logModel = new LogTable();

                // foreach ($inventory as $invs) {
                    foreach ($inventory['inv'] as $inv) {
                        $hotel_inventory = new Inventory();
                        $fmonth = explode('-', $inv['date']); //for removing extra o from month and remove this code after mobile app update
                        if (strlen($fmonth[1]) == 3) {
                            $fmonth[1] = ltrim($fmonth[1], 0);
                        }
                        $inv['room_type_id']       = $inventory['room_type_id'];
                        $inv['block_status']       = 0;
                        $inv['user_id']            = $imp_data['user_id'];
                        $inv['client_ip']          = $imp_data['client_ip'];
                        $inv['hotel_id']           = $imp_data['hotel_id'];
                        $inv['date_from']          = date('Y-m-d', strtotime($inv['date']));
                        $inv['date_to']            = date('Y-m-d', strtotime($inv['date']));
                        $inv['date']               = date('Y-m-d', strtotime($inv['date']));
                        $success_flag = 1;
                        try {
                            $inv_update = $hotel_inventory->fill($inv)->save();
                            $current_inv = array(
                                "hotel_id"      => $imp_data['hotel_id'],
                                "room_type_id"  => $inventory['room_type_id'],
                                "ota_id"        => -1,
                                "stay_day"      => $inv['date'],
                                "no_of_rooms"   => $inv['no_of_rooms'],
                                "los"           => $inv['los'],
                                "ota_name"      => "Bookingjini"
                            );

                            $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                                [
                                    'hotel_id' => $imp_data['hotel_id'],
                                    'room_type_id' => $inventory['room_type_id'],
                                    'ota_id' => -1,
                                    'stay_day' => $inv['date']
                                ],
                                $current_inv
                            );

                            $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                                [
                                    'hotel_id' => $imp_data['hotel_id'],
                                    'room_type_id' => $inventory['room_type_id'],
                                    'ota_id' => -1,
                                    'stay_day' => $inv['date']
                                ],
                                $current_inv
                            );
                          
                            if(empty($cur_inv_be) || $cur_inv_be==false){
                                $cur_inv_be_json = json_encode($current_inv);
                                $logpath = storage_path("logs/inverrorlog.log" . date("Y-m-d"));
                                $logfile = fopen($logpath, "a+");
                                fwrite($logfile, "cart data: " . $imp_data['hotel_id'] . $cur_inv_be_json . "\n");
                                fclose($logfile);
                            }
                          
                            
                            if ($this->googleHotelStatus($imp_data['hotel_id']) == true) {
                                $update = $this->inventoryUpdateToGoogleAds($imp_data['hotel_id'], $inventory['room_type_id'], $inv['no_of_rooms'], $inv['date_from'], $inv['date_to']);
                            }
                        } catch (\Exception $e) {
                            $success_flag = 0;
                        }
                        if ($success_flag) {
                            $log_data        = [
                                "action_id"          => 4,
                                "hotel_id"           => $imp_data['hotel_id'],
                                "ota_id"               => 0,
                                "inventory_ref_id"   => $hotel_inventory->inventory_id,
                                "user_id"            => $imp_data['user_id'],
                                "request_msg"        => '',
                                "response_msg"       => '',
                                "request_url"        => '',
                                "status"             =>  1,
                                "ip"                 => $imp_data['client_ip'],
                                "comment"    => "Processing for update "
                            ];
                            $logModel->fill($log_data)->save(); //saving pre log data

                            $flag = 1;
                        } else {
                            $flag = 0;
                        }
                        $count = $count + $flag;
                    }
                   
                // }
                if (sizeof($inventory['inv']) == $count) {
                    return response()->json(array('status' => 1, 'response_msg' => 'Updated Successfully!', 'ota_name' => 'Bookingjini'));
                } else {
                    return response()->json(array('status' => 0, 'response_msg' => 'Updation Failed', 'ota_name' => 'Bookingjini'));
                }
            }
        }
    }


    //block inventory in booking engine
    public function blockInvForDateRange(Request $request)
    {
        $failure_message = 'Block inventry operation failed';
        $validator = Validator::make($request->all(), $this->rules, $this->messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        // if($data['hotel_id'] == 1953){
        $date1 = date_create($data['date_from']);
        $date2 = date_create($data['date_to']);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        if ($diff > 60) {
            $check_google_hotel = $this->googleHotelStatus($data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 60 days', 'ota_name' => 'bookingjini');
            }
        }
        // }
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

        $data['date_from'] = date('Y-m-d', strtotime($data['date_from']));
        $data['date_to'] = date('Y-m-d', strtotime($data['date_to']));

        if(isset($data['pms_status']) && $data['pms_status'] == "YES")
        {
            $imp_data = $this->pmsImpDate($data);
        }
        else
        {
            $imp_data = $this->impDate($data, $request);
        }

        $data["client_ip"] = $imp_data['client_ip'];
        $data["user_id"] = $imp_data['user_id'];
        $hotel_id = $imp_data['hotel_id'];
        $resp = array();
        $logModel = new LogTable();
        $count = 0;
        $data['room_type_id'] = $room_type_id;
        $success_flag = 1;
        try {
            $from_date = date('Y-m-d', strtotime($data['date_from']));
            $to_date = date('Y-m-d', strtotime($data['date_to']));
            $p_start = $from_date;
            $p_end = date('Y-m-d', strtotime($to_date . '+1 day'));
            $period     = new \DatePeriod(
                new \DateTime($p_start),
                new \DateInterval('P1D'),
                new \DateTime($p_end)
            );

            $weekdays = array();
            if(isset($data['multiple_days']))
            {
                $weekdays = $data['multiple_days'];
                $data['multiple_days'] = json_encode($data['multiple_days']);
            }
            else
            {
                $data['multiple_days'] = '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
                
                $weekdays = json_decode($data['multiple_days'],true);
            }

            $block_inv = [
                'hotel_id'              => $hotel_id,
                'room_type_id'          => $room_type_id,
                'no_of_rooms'           => 0,
                'date_from'             => $data['date_from'],
                'date_to'               => $data['date_to'],
                'client_ip'             => $data['client_ip'],
                'user_id'               => $data['user_id'],
                'block_status'          => 1,
                'los'                   => 1,
                'multiple_days'         => $data['multiple_days']
            ];

            $inventory = new Inventory();
            $inventory->fill($block_inv)->save();

            $weekday = array();
            foreach ($period as $key => $value) {
                $index = $value->format('Y-m-d');
                $weekday = $value->format('D');
                if($weekdays[$weekday] == 1)
                {
                    $inv_table = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type_id)
                        ->where('stay_day', '=', $index)
                        ->where('ota_id', -1)
                        ->where('block_status', 0)
                        ->orderBy('id', 'DESC')
                        ->first();

                    $current_inv = array(
                        "hotel_id"      => $data['hotel_id'],
                        "room_type_id"  => $data['room_type_id'],
                        "ota_id"        => -1,
                        "stay_day"      => $index,
                        "block_status"  => 1,
                        "ota_name"      => "Bookingjini"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id' => $data['hotel_id'],
                            'room_type_id' => $data['room_type_id'],
                            'ota_id' => -1,
                            'stay_day' => $index,
                            'ota_name' => "Bookingjini"
                        ],
                        $current_inv
                    );

                    $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                        [
                            'hotel_id' => $data['hotel_id'],
                            'room_type_id' => $data['room_type_id'],
                            'ota_id' => -1,
                            'stay_day' => $index,
                            'ota_name' => "Bookingjini"
                        ],
                        $current_inv
                    );

                    if ($this->googleHotelStatus($data['hotel_id']) == true) {
                        $update = $this->inventoryUpdateToGoogleAds($data['hotel_id'], $data['room_type_id'], 0, $data['date_from'], $data['date_to']);
                    }
                }
                else
                {
                    continue;
                }
            }
        } catch (\Exception $e) {
            $success_flag = 0;
        }
        if ($success_flag) {
            $log_data               = [
                "action_id"          => 4,
                "hotel_id"           => $data['hotel_id'],
                "ota_id"               => 0,
                "inventory_ref_id"   => $inventory->inventory_id,
                "user_id"            => $inventory->user_id,
                "request_msg"        => '',
                "response_msg"       => '',
                "request_url"        => '',
                "status"             =>  1,
                "ip"                 => $inventory->client_ip,
                "comment"    => "Processing for update "
            ];
            $logModel->fill($log_data)->save(); //saving pre log data
            return  $return_resp = array('status' => 1, 'response_msg' => 'Inventory blocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        } else {
            return  $return_resp = array('status' => 0, 'response_msg' => 'Inventory block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }
    }

    //Modified by Jigyans dt : - 05-04-2023
    public function bulkInvUpdate(Request $request)
    {
        $hotel_inventory = new Inventory();
        $logModel = new LogTable();
        $failure_message = 'Inventory updation failed';
        $validator = Validator::make($request->all(), $this->blk_rules, $this->blk_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $fmonth = explode('-', $data['date_from']); //for removing extra o from month and remove this code after mobile app update
        if (strlen($fmonth[1]) == 3) {
            $fmonth[1] = ltrim($fmonth[1], 0);
            $fmonth[2] = $fmonth[2] < 10 ? "0" . $fmonth[2] : $fmonth[2];
        }
        $data['date_from'] = implode('-', $fmonth);
        $tmonth = explode('-', $data['date_to']);
        if (strlen($tmonth[1]) == 3) {
            $tmonth[1] = ltrim($tmonth[1], 0);
            $tmonth[2] = $tmonth[2] < 10 ? "0" . $tmonth[2] : $tmonth[2];
        }

        // if($data['hotel_id'] == 1953){
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
        // }

        if(isset($data['multiple_days']))
        {
            $data['multiple_days'] = json_encode($data['multiple_days']);
        }
        else
        {
            $data['multiple_days'] = '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
        }

        $bulk_data['hotel_id']          = $data['hotel_id'];
        $bulk_data['room_type_id']      = $data['room_type_id'];
        $bulk_data['no_of_rooms']       = $data['no_of_rooms'];
        $bulk_data['los']               = $data['los'];
        $bulk_data['block_status']      = $data['block_status'];
        $bulk_data['date_to']           = implode('-', $tmonth);
        $bulk_data['date_from']         = date('Y-m-d', strtotime($data['date_from']));
        $bulk_data['date_to']           = date('Y-m-d', strtotime($data['date_to']));
        $bulk_data['multiple_days']     = $data['multiple_days'];

        if(isset($data['pms_status']) && $data['pms_status'] == "YES")
        {
            $imp_data = $this->pmsImpDate($data);
        }
        else
        {
            $imp_data = $this->impDate($data, $request);
        }


        // $imp_data                       = $this->impDate($data, $request); //used to get user id and client ip.
        $bulk_data["client_ip"]         = $imp_data['client_ip'];
        $bulk_data["user_id"]           = $imp_data['user_id'];
        // $bulk_data["ota_id"]           = -1;
        $resp = array();
        // print_r($imp_data);exit;
        foreach ($imp_data['ota_id'] as $otaid) {
            if ($otaid == -1) {

                $inv_date_range_array = [];

                $inv_date_range = $this->getDataForInventory->getinventorydata($bulk_data, 'Bookingjini', '-1');

                // if($bulk_data['hotel_id'] == 3707)
                // {

                //     $inv_date_range = $this->getDataForInventory->getinventorydataforMultipledays($bulk_data, 'Bookingjini', '-1');
                // }


                // return $inv_date_range;
                $inv_start_date_range = $inv_date_range['start_date_info'];
                $inv_end_date_range = $inv_date_range['end_date_info'];
                if ($inv_start_date_range) {
                    foreach ($inv_start_date_range as $key => $inv_date) {
                        $bulk_data['date_from'] = date('Y-m-d', strtotime($inv_date));
                        $bulk_data['date_to'] = date('Y-m-d', strtotime($inv_end_date_range[$key]));
                        array_push($inv_date_range_array, $bulk_data);
                    }
                }

                if ($hotel_inventory->insert($inv_date_range_array)) {

                    foreach ($inv_start_date_range as $key => $inv_date) {

                        $start_date = $inv_date;
                        $end_date = date('Y-m-d', strtotime($inv_end_date_range[$key]));
                        $update_current_inv = $this->getDataForInventory->updateDataToCurrentInventory($data, 'Bookingjini', $start_date, $end_date);
                        if ($this->googleHotelStatus($bulk_data['hotel_id']) == true) {
                            $update = $this->inventoryUpdateToGoogleAds($bulk_data['hotel_id'], $bulk_data['room_type_id'], $bulk_data['no_of_rooms'], $start_date, $end_date);
                        }
                    }
                }

                $log_data               = [
                    "action_id"          => 4,
                    "hotel_id"           => $imp_data['hotel_id'],
                    "ota_id"               => 0,
                    "inventory_ref_id"    => $hotel_inventory->inventory_id,
                    "user_id"            => $imp_data['user_id'],
                    "request_msg"        => '',
                    "response_msg"       => '',
                    "request_url"        => '',
                    "status"             =>  1,
                    "ip"                 => $imp_data['client_ip'],
                    "comment"    => "Processing for update "
                ];
                $logModel->fill($log_data)->save(); //saving pre log data
                $resp = array('status' => 1, 'response_msg' => 'inventory update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                return response()->json($resp);
            } else {
                $resp = array('status' => 0, 'response_msg' => 'inventory update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                return response()->json($resp);
            }
        }
    }
    /**
     * This controller is used for unblock inventory for specific date range.
     * @auther Swatishree
     * created date 21/04/22.
     */
    public function UnblockInvForSpecificDates(request $request)
    {

        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $ota_id = $data['ota_id'];
        $insert_specific_date = '';
        $client_ip = $this->ipService->getIPAddress(); //get client ip
        $user_id = $data['admin_id'];
        foreach ($data['inv'] as $inv) {

            $date_from = date('Y-m-d', strtotime($inv['date']));
            $date_to = date('Y-m-d', strtotime($inv['date']));

            $inv_table = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
                ->where('room_type_id', $room_type_id)
                ->where('stay_day', '=', $date_from)
                ->where('ota_id', '=', -1)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$inv_table) {
                $inv_table = Inventory::where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->where('date_from', '<=', $date_from)
                    ->where('date_to', '>=', $date_to)
                    ->orderBy('inventory_id', 'DESC')
                    ->first();
            }
            $block_status = isset($inv_table->block_status) ? $inv_table->block_status : $inv_table['block_status'];
            if (!empty($inv_table) && $block_status == 1) {


                $specific_date_array = [
                    'hotel_id'              => $hotel_id,
                    'room_type_id'          => $room_type_id,
                    'no_of_rooms'           => $inv_table['no_of_rooms'],
                    'date_from'             => $date_from,
                    'date_to'               => $date_to,
                    'client_ip'             => $client_ip,
                    'user_id'               => $user_id,
                    'block_status'          => 0,
                    'los'                   => $inv_table['los'],
                    'multiple_days'         => '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}'
                ];
                // try {
                $insert_specific_date = Inventory::insert($specific_date_array);
                $current_inv = array(
                    "hotel_id"          => $hotel_id,
                    "room_type_id"      => $room_type_id,
                    "ota_id"            => -1,
                    "stay_day"          => $date_from,
                    'block_status'      => 0,
                    "los"               => $inv_table['los'],
                    "no_of_rooms"       => $inv_table['no_of_rooms'],
                    "ota_name"          => "Bookingjini"
                );

                $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                    [
                        'hotel_id'      => $hotel_id,
                        'room_type_id'  => $room_type_id,
                        'ota_id'        => -1,
                        'stay_day'      => $date_from,
                    ],
                    $current_inv
                );

                $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                    [
                        'hotel_id'      => $hotel_id,
                        'room_type_id'  => $room_type_id,
                        'ota_id'        => -1,
                        'stay_day'      => $date_from,
                    ],
                    $current_inv
                );

                if ($this->googleHotelStatus($hotel_id) == true) {
                    $update = $this->inventoryUpdateToGoogleAds($hotel_id, $room_type_id, $inv_table['no_of_rooms'], $date_from, $date_from);
                }
                // } catch (\Exception $e) {
                // }
            } else {
                continue;
            }
        }
        if ($insert_specific_date) {
            return  $return_resp = array('status' => 1, 'response_msg' => 'Inventory unblocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        } else {
            return  $return_resp = array('status' => 0, 'response_msg' => 'Inventory unblock unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }
    }
    //Unblock inventory for date range
    public function UnblockInvForDateRange(request $request)
    {

        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $data['hotel_id'];

        // if($hotel_id == 1953){
        $date1 = date_create($data['date_from']);
        $date2 = date_create($data['date_to']);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        if ($diff > 60) {
            $check_google_hotel = $this->googleHotelStatus($hotel_id);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 60 days', 'ota_name' => 'bookingjini');
            }
        }
        // }

        $room_type_id = $data['room_type_id'];
        $date_from = date('Y-m-d', strtotime($data['date_from']));
        $date_to = date('Y-m-d', strtotime($data['date_to'] . '+1 day'));
        $p_start = $date_from;
        $p_end = $date_to;
        $i = 0;
        $ota_name = 'Bookingjini';
        $ota_id = '-1';
        $insert_unblock = "";
        $inv_date_range = $this->getDataForInventory->getinventorydataForUnblockforMultipleDays($data, $ota_name, $ota_id);
        $client_ip = $this->ipService->getIPAddress(); //get client ip
        $user_id = $data['admin_id'];
        if ($inv_date_range) {
            $inv_start_date_range = $inv_date_range['start_date_info'];
            $inv_end_date_range = $inv_date_range['end_date_info'];

            foreach ($inv_start_date_range as $key => $inv_date) {
                $p_start = $inv_date;
                $p_end = date('Y-m-d', strtotime($inv_end_date_range[$key] . '+1 day'));


                $period     = new \DatePeriod(
                    new \DateTime($p_start),
                    new \DateInterval('P1D'),
                    new \DateTime($p_end)
                );

                foreach ($period as $value) {
                    $index = $value->format('Y-m-d');

                    $inv_table = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type_id)
                        ->where('stay_day', '=', $index)
                        ->where('ota_id', -1)
                        ->orderBy('id', 'DESC')
                        ->first();

                    if (empty($inv_table)) {
                        $inv_table = Inventory::where('hotel_id', $hotel_id)
                            ->where('room_type_id', $room_type_id)
                            ->where('date_from', '<=', $index)
                            ->where('date_to', '>=', $index)
                            ->orderBy('inventory_id', 'DESC')
                            ->first();
                    }
                    $block_status = isset($inv_table->block_status) ? $inv_table->block_status : $inv_table['block_status'];


                    if (!empty($inv_table) && $block_status == 1) {

                   
                            $specific_date_array = [
                                'hotel_id'              => $hotel_id,
                                'room_type_id'          => $room_type_id,
                                'no_of_rooms'           => $inv_table['no_of_rooms'],
                                'date_from'             => $index,
                                'date_to'               => $index,
                                'client_ip'             => $client_ip,
                                'user_id'               => $user_id,
                                'block_status'          => 0,
                                'los'                   => $inv_table['los'],
                                'multiple_days'         => isset($inv_table['multiple_days'])
                            ];
                       
                        // try {
                        $insert_unblock = Inventory::insert($specific_date_array);
                        if ($insert_unblock) {
                            $i = $i + 1;
                        }

                        $current_inv = array(
                            "hotel_id"          => $hotel_id,
                            "room_type_id"      => $room_type_id,
                            "ota_id"            => -1,
                            "stay_day"          => $index,
                            'block_status'      => 0,
                            "los"               => $inv_table['los'],
                            "no_of_rooms"       => $inv_table['no_of_rooms'],
                            "ota_name"          => "Bookingjini"
                        );


                        $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(

                            [
                                'hotel_id'      => $hotel_id,
                                'room_type_id'  => $room_type_id,
                                'ota_id'        => -1,
                                'stay_day'      => $index,
                            ],
                            $current_inv
                        );
                        $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                            [
                                'hotel_id'      => $hotel_id,
                                'room_type_id'  => $room_type_id,
                                'ota_id'        => -1,
                                'stay_day'      => $index,
                            ],
                            $current_inv
                        );


                        if ($this->googleHotelStatus($hotel_id) == true) {
                            $update = $this->inventoryUpdateToGoogleAds($hotel_id, $room_type_id, $inv_table['no_of_rooms'], $date_from, $date_from);
                        }
                    } else {
                        continue;
                    }
                }
            }
        }

        if ($insert_unblock) {
            return  $return_resp = array('status' => 1, 'response_msg' => 'Room type unblocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        } else {
            return  $return_resp = array('status' => 0, 'response_msg' => 'Room type unblock unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini', 'data' => $i);
        }
    }
    //Block inventory for  date range.
    public function BlockInvForSpecificDates(request $request)
    {
        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $ota_id = $data['ota_id'];
        $insert_specific_date = '';
        $client_ip = $this->ipService->getIPAddress(); //get client ip
        $user_id = $data['admin_id'];
        foreach ($data['inv'] as $inv) {

            $date_from = date('Y-m-d', strtotime($inv['date']));
            $date_to = date('Y-m-d', strtotime($inv['date']));

            $inv_table = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
                ->where('room_type_id', $room_type_id)
                ->where('stay_day', '=', $date_from)
                ->where('ota_id', -1)
                ->orderBy('id', 'DESC')
                ->first();

            $block_status = isset($inv_table->block_status) ? $inv_table->block_status : $inv_table['block_status'];
            if (!empty($inv_table) && $block_status == 0) {

                $specific_date_array = [
                    'hotel_id'              => $hotel_id,
                    'room_type_id'          => $room_type_id,
                    'no_of_rooms'           => $inv_table['no_of_rooms'],
                    'date_from'             => $date_from,
                    'date_to'               => $date_to,
                    'client_ip'             => $client_ip,
                    'user_id'               => $user_id,
                    'block_status'          => 1,
                    'los'                   => $inv_table['los'],
                    'multiple_days'         => '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}'
                ];
                // try {
                $insert_specific_date = Inventory::insert($specific_date_array);
                $current_inv = array(
                    "hotel_id"          => $hotel_id,
                    "room_type_id"      => $room_type_id,
                    "ota_id"            => -1,
                    "stay_day"          => $date_from,
                    'block_status'      => 1,
                    "los"               => $inv_table['los'],
                    "no_of_rooms"       => $inv_table['no_of_rooms'],
                    "ota_name"          => "Bookingjini"
                );

                $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                    [
                        'hotel_id'      => $hotel_id,
                        'room_type_id'  => $room_type_id,
                        'ota_id'        => -1,
                        'stay_day'      => $date_from,
                    ],
                    $current_inv
                );

                $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                    [
                       'hotel_id'      => $hotel_id,
                        'room_type_id'  => $room_type_id,
                        'ota_id'        => -1,
                        'stay_day'      => $date_from,
                    ],
                    $current_inv
                );

                if ($this->googleHotelStatus($hotel_id) == true) {
                    $update = $this->inventoryUpdateToGoogleAds($hotel_id, $room_type_id, 0, $date_from, $date_from);
                }
                // } catch (\Exception $e) {
                // }
            } else {
                continue;
            }
        }
        if ($insert_specific_date) {
            return  $return_resp = array('status' => 1, 'response_msg' => 'Room type blocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        } else {
            return  $return_resp = array('status' => 0, 'response_msg' => 'Room type block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }
    }

    public function BlockProperty(request $request)
    {
        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $request->hotel_id;
        $room_types = MasterRoomType::where('hotel_id', $data['hotel_id'])->where('is_trash', '0')->get();
        foreach ($room_types as $roominfo) {
            $count = 0;
            $room_type_id = $roominfo->room_type_id;
            $date_from = date('Y-m-d', strtotime($data['date_from']));
            // $date_to = date('Y-m-d', strtotime($data['date_to']));
            $date_to = date('Y-m-d', strtotime($data['date_to']));
            $date1 = date_create($date_from);
            $date2 = date_create($date_to);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            if ($diff > 7) {
                return  $return_resp = array('status' => 0, 'response_msg' => 'Date should not be greater than 7 days.');
            }
            $p_start = $date_from;
            $p_end = date('Y-m-d', strtotime($date_to . '+1 day'));
            $period     = new \DatePeriod(
                new \DateTime($p_start),
                new \DateInterval('P1D'),
                new \DateTime($p_end)
            );
            foreach ($period as $value) {
                $index = $value->format('Y-m-d');
                // $date_to = date('Y-m-d', strtotime($index . '+1 day'));
                $inv_table = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->where('stay_day', '=', $index)
                    ->orderBy('id', 'DESC')
                    ->first();
                if (empty($inv_table)) {
                    $inv_table = Inventory::where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type_id)
                        ->where('date_from', '<=', $index)
                        ->where('date_to', '>=', $date_to)
                        ->orderBy('inventory_id', 'DESC')
                        ->first();
                    if (empty($inv_table)) {
                        $inv_table = array(
                            "no_of_rooms" => 0,
                            'client_ip'   => 'manual',
                            'user_id'     => 0,
                            'block_status' => 0,
                            'los'         => 0,
                        );
                    }
                }
                $block_status = isset($inv_table->block_status) ? $inv_table->block_status : $inv_table['block_status'];
                if (!empty($inv_table)) {
                    $property_array = [
                        'hotel_id'              => $hotel_id,
                        'room_type_id'          => $room_type_id,
                        'no_of_rooms'           => $inv_table['no_of_rooms'],
                        'date_from'             => $index,
                        'date_to'               => $date_to,
                        'client_ip'             => isset($inv_table['client_ip']),
                        'user_id'               => isset($inv_table['user_id']),
                        'block_status'          => 1,
                        'los'                   => $inv_table['los'],
                        'multiple_days'         => isset($inv_table['multiple_days'])
                    ];
                    $property_insert =  Inventory::insert($property_array);
                    $current_inv = array(
                        "hotel_id"          => $hotel_id,
                        "room_type_id"      => $room_type_id,
                        "ota_id"            => -1,
                        "stay_day"          => $index,
                        'block_status'      => 1,
                        "ota_name"          => "Bookingjini"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id' => $hotel_id,
                            'room_type_id' => $room_type_id,
                            'ota_id' => -1,
                            'stay_day' => $index
                        ],
                        $current_inv
                    );

                    $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
						[
                            'hotel_id' => $hotel_id,
                            'room_type_id' => $room_type_id,
                            'ota_id' => -1,
                            'stay_day' => $index
						],
						$current_inv
					);
                    //dd($property_insert);
                    if ($cur_inv) {
                        $count++;
                    }
                } else {
                    continue;
                    $count++;
                }
            }
            $channel_logo = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg";
            if ($count) {
                $res[$roominfo->room_type_id] = array('status' => 1, "response_msg" => "Inventory Update Successfull", "room_type" => $roominfo->room_type, 'channel_name' => 'Bookingengine', 'channel_logo' => $channel_logo);
                // } elseif ($count > 0) {
                //     $res[$roominfo->room_type_id] = array('status' => -1, "response_msg" => "Inventory Update Partially Successfull", "room_type" => $roominfo->room_type, 'channel_name' => 'Bookingengine', 'channel_logo' => $channel_logo);
            } else {
                $res[$roominfo->room_type_id] = array('status' => 0, "response_msg" => "Failed to Inventory Update", "room_type" => $roominfo->room_type, 'channel_name' => 'Bookingengine', 'channel_logo' => $channel_logo);
            }
        }
        return response()->json($res);
    }

    public function UnblockProperty(request $request)
    {
        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $request->hotel_id;
        $room_types = MasterRoomType::where('hotel_id', $data['hotel_id'])->where('is_trash', '0')->get();
        foreach ($room_types as $roominfo) {
            $count = 0;
            $room_type_id = $roominfo->room_type_id;
            $date_from = date('Y-m-d', strtotime($data['date_from']));
            // $date_to = date('Y-m-d', strtotime($data['date_to']));
            $date_to = date('Y-m-d', strtotime($data['date_to']));
            $date1 = date_create($date_from);
            $date2 = date_create($date_to);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            if ($diff > 7) {
                return  $return_resp = array('status' => 0, 'response_msg' => 'Date should not be greater than 7 days');
            }
            $p_start = $date_from;
            $p_end = date('Y-m-d', strtotime($date_to . '+1 day'));
            $period     = new \DatePeriod(
                new \DateTime($p_start),
                new \DateInterval('P1D'),
                new \DateTime($p_end)
            );
            foreach ($period as $value) {
                $index = $value->format('Y-m-d');
                $dateto = date('Y-m-d', strtotime($index . '+1 day'));
                $inv_table = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->where('stay_day', '=', $index)
                    ->orderBy('id', 'DESC')
                    ->first();
                if (empty($inv_table)) {
                    $inv_table = Inventory::where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type_id)
                        ->where('date_from', '<=', $index)
                        ->where('date_to', '>=', $dateto)
                        ->orderBy('inventory_id', 'DESC')
                        ->first();
                    if (empty($inv_table)) {
                        $inv_table = array(
                            "no_of_rooms" => 0,
                            'client_ip'   => 'manual',
                            'user_id'     => 0,
                            'block_status' => 0,
                            'los'         => 0,
                        );
                    }
                }
                $block_status = isset($inv_table->block_status) ? $inv_table->block_status : $inv_table['block_status'];
                if (!empty($inv_table)) {
                    $property_array = [
                        'hotel_id'              => $hotel_id,
                        'room_type_id'          => $room_type_id,
                        'date_from'             => $index,
                        'date_to'               => $dateto,
                        'client_ip'             => isset($inv_table['client_ip']),
                        'user_id'               => isset($inv_table['user_id']),
                        'block_status'          => 0,
                        'los'                   => $inv_table['los'],
                        'multiple_days'         => isset($inv_table['multiple_days'])
                    ];
                    $property_insert = Inventory::insert($property_array);
                    $current_inv = array(
                        "hotel_id"          => $hotel_id,
                        "room_type_id"      => $room_type_id,
                        "ota_id"            => -1,
                        "stay_day"          => $index,
                        'block_status'      => 0,
                        "ota_name"          => "Bookingjini"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id'      => $hotel_id,
                            'room_type_id'  => $room_type_id,
                            'ota_id'        => -1,
                            'stay_day'      => $index
                        ],
                        $current_inv
                    );

                    $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
						[
                            'hotel_id' => $hotel_id,
                            'room_type_id' => $room_type_id,
                            'ota_id' => -1,
                            'stay_day' => $index
						],
						$current_inv
					);
                    
                    if ($cur_inv) {
                        $count++;
                    }
                } else {
                    continue;
                    $count++;
                }
            }
            $channel_logo = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg";
            if ($count) {
                $res[$roominfo->room_type_id] = array('status' => 1, "response_msg" => "Inventory Update Successfull", "room_type" => $roominfo->room_type, 'channel_name' => 'Bookingengine', 'channel_logo' => $channel_logo);
                // } elseif ($count > 0) {
                //     $res[$roominfo->room_type_id] = array('status' => -1, "response_msg" => "Inventory Update Partially Successfull", "room_type" => $roominfo->room_type, 'channel_name' => 'Bookingengine', 'channel_logo' => $channel_logo);
            } else {
                $res[$roominfo->room_type_id] = array('status' => 0, "response_msg" => "Failed to Inventory Update.", "room_type" => $roominfo->room_type, "room_type" => $roominfo->room_type, 'channel_name' => 'Bookingengine', 'channel_logo' => $channel_logo);
            }
        }
        return response()->json($res);
    }


    //update rate in bookingt engine.
    public function singleRateUpdate(Request $request)
    {
        $data = $request->all();
        $rates_data = $data['rates'];
        $imp_data = $this->impDate($data, $request); //used to get user id and client ip.
        $resp = array();
        foreach ($imp_data['ota_id'] as $otaid) {
            if ($otaid == -1) {
                $flag = 0;
                $count = 0;
                $logModel      = new RateUpdateLog();
                foreach ($rates_data as $rates) {
                    $rates_count = count($rates['rates']);
                    if ($rates_count > 60) {
                        $check_google_hotel = $this->googleHotelStatus($imp_data['hotel_id']);
                        if ($check_google_hotel) {
                            return array('status' => 0, 'response_msg' => 'Date range must be less then 60 days', 'ota_name' => 'bookingjini');
                        }
                    }

                    foreach ($rates['rates'] as $rate) {
                        $rate_plan_log = new RatePlanLog();
                        $ratedata = array();
                        $ratedata['room_type_id']   =   $rates['room_type_id'];
                        $ratedata['rate_plan_id']   =   $rates['rate_plan_id'];
                        $multiple_days              =   '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
                        $fmonth = explode('-', $rate['date']); //for removing extra o from month and remove this code after mobile app update
                        if (strlen($fmonth[1]) == 3) {
                            $fmonth[1] = ltrim($fmonth[1], 0);
                        }
                        $rate['date'] = implode('-', $fmonth);
                        $ratedata['hotel_id'] = $imp_data['hotel_id'];
                        $ratedata['from_date'] = date('Y-m-d', strtotime($rate['date']));
                        $ratedata['to_date'] = date('Y-m-d', strtotime($rate['date']));
                        $ratedata['multiple_occupancy'] = json_encode($rate['multiple_occupancy']);
                        $ratedata['bar_price'] = $rate['bar_price'];
                        $ratedata['los'] = $rate['los']; //Default length of stay
                        $ratedata['extra_adult_price'] = isset($rate['extra_adult_price']) ? $rate['extra_adult_price'] : 0;
                        $ratedata['extra_child_price'] = isset($rate['extra_child_price']) ? $rate['extra_child_price'] : 0;
                        $ratedata['multiple_days'] = $multiple_days;
                        $ratedata['user_id'] = $imp_data['user_id'];
                        $ratedata['channel'] = 'Bookingjini';
                        $ratedata['client_ip'] = $imp_data['client_ip'];
                        $price = MasterHotelRatePlan::select('min_price', 'max_price')->where('hotel_id', $imp_data['hotel_id'])->where('room_type_id', $rates["room_type_id"])->where('rate_plan_id', $rates["rate_plan_id"])->orderBy('room_rate_plan_id', 'DESC')->first();
                        $bp = 0;
                        $mp = 0;
                        if ($rate['bar_price'] >= $price->min_price && $rate['bar_price'] < $price->max_price) {
                            $bp = 1;
                        }
                        if ($bp == 0) {
                            $res = array('status' => 0, 'response_msg' => "price should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price);
                            return $res;
                        }
                        if (!empty($rate['multiple_occupancy'])) {
                            foreach ($rate['multiple_occupancy'] as $multi) {
                                $multi_occupancy = (int)$rate['multiple_occupancy'][0];
                                if ($multi_occupancy >= $price->min_price && $multi_occupancy < $price->max_price) {
                                    $mp = 1;
                                }
                            }
                        } else {
                            $rate['multiple_occupancy'] = $rate['bar_price'];
                            $mp = 1;
                        }
                        if ($mp == 0) {
                            return array('status' => 0, 'response_msg' => "price should be equal or greater than: " . $price->min_price . " and should be less than: " . $price->max_price);
                        }
                        $success_flag = 1;
                        try {
                            $rate_plan_log->fill($ratedata)->save();
                            $base_rate     = new BaseRate();
                            $insertBaseRate = $base_rate->fill($ratedata)->save();

                            // if($ratedata['hotel_id'] == 1953){
                                $current_rate_data                   = [
                                    "hotel_id"          => $ratedata['hotel_id'],
                                    "room_type_id"        => $ratedata['room_type_id'],
                                    "rate_plan_id"        => $ratedata['rate_plan_id'],
                                    "ota_id"             => '-1',
                                    "ota_name"             => "Bookingjini",
                                    "stay_date"             => $ratedata['from_date'],
                                    "bar_price"             => $ratedata['bar_price'],
                                    "multiple_occupancy" => $ratedata['multiple_occupancy'],
                                    "multiple_days" => $multiple_days,
                                    "extra_adult_price" => $ratedata['extra_adult_price'],
                                    "extra_child_price" => $ratedata['extra_child_price'],
                                    "los" => $ratedata['los']
                                ];
                                $cond_current_rate_data                   = [
                                    "hotel_id"          => $ratedata['hotel_id'],
                                    "room_type_id"      => $ratedata['room_type_id'],
                                    "rate_plan_id"      => $ratedata['rate_plan_id'],
                                    "ota_id"            => '-1',
                                    "stay_date"         => $ratedata['from_date'],
                                ];
                                $cur_inv = CurrentRate::updateOrInsert($cond_current_rate_data, $current_rate_data);
                                $cur_inv_be = CurrentRateBe::updateOrInsert($cond_current_rate_data, $current_rate_data);       
    
                            // }

                            if ($this->googleHotelStatus($imp_data['hotel_id']) == true) {
                                $hotel_id = $ratedata['hotel_id'];
                                $room_type_id = $ratedata['room_type_id'];
                                $index = $ratedata['from_date'];
                                // $check_coupon = Coupons::select('discount')
                                //     ->where(["hotel_id" => $hotel_id, "room_type_id" => $room_type_id, 'coupon_for' => 1])
                                //     ->where('valid_from', '<=', $index)
                                //     ->where('valid_to', '>=', $index)
                                //     ->orWhere(function ($query) use ($hotel_id, $index) {
                                //         $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                                //             ->where('valid_from', '<=', $index)
                                //             ->where('valid_to', '>=', $index);
                                //     })
                                //     ->orderBy('coupon_id', 'DESC')
                                //     ->first();
                                // if ($check_coupon) {
                                //     $discount_per = $check_coupon->discount;
                                //     $discount_amt = round($ratedata['bar_price'] * $discount_per / 100);
                                //     $price = $ratedata['bar_price'] - $discount_amt;

                                //     $rate_update = $this->rateUpdateToGoogleAds($ratedata['hotel_id'], $ratedata['room_type_id'], $ratedata['rate_plan_id'], $ratedata['from_date'], $ratedata['to_date'], $price, $ratedata['multiple_days']);
                                // } else {
                                $rate_update = $this->rateUpdateToGoogleAds($ratedata['hotel_id'], $ratedata['room_type_id'], $ratedata['rate_plan_id'], $ratedata['from_date'], $ratedata['to_date'], $ratedata['bar_price'], $ratedata['multiple_days']);
                                // }
                                $los_update = $this->losUpdateToGoogleAds($ratedata['hotel_id'], $ratedata['room_type_id'], $ratedata['rate_plan_id'], $ratedata['from_date'], $ratedata['to_date'], $ratedata['los'], $ratedata['multiple_days'],0);
                            }
                        } catch (\Exception $e) {
                        }
                        if ($success_flag) {
                            $log_data                   = [
                                "action_id"          => 2,
                                "hotel_id"           => $imp_data['hotel_id'],
                                "ota_id"               => 0,
                                "rate_ref_id"        => $rate_plan_log->rate_plan_log_id,
                                "user_id"            => $imp_data['user_id'],
                                "request_msg"        => '',
                                "response_msg"       => '',
                                "request_url"        => '',
                                "status"              => 1,
                                "ip"                  => $imp_data['client_ip'],
                                "comment"             => "Processing for update "
                            ];
                            $logModel->fill($log_data)->save(); //saving pre log data
                            $flag = 1;
                            $res = array('status' => 1, 'response_msg' => 'rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                        } else {
                            $flag = 0;
                            $res = array('status' => 0, 'response_msg' => 'rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                        }
                    }
                    $count = $count + $flag;
                }
                if (sizeof($rates_data) == $count) {
                    return array('status' => 1, 'response_msg' => 'Rate update sucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                } else {
                    return array('status' => 0, 'response_msg' => 'Rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                }
            }
        }
    }


    public function bulkRateUpdate(Request $request)
    {
        $data = $request->all();
        // if($data['hotel_id'] == 2600)
        // {
        //     return $data;
        // }

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

        if ($diff > 60) {
            $check_google_hotel = $this->googleHotelStatus($imp_data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 60 days', 'ota_name' => 'bookingjini');
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
        $conds = array('hotel_id' => $data['hotel_id'], 'derived_room_type_id' => $data['room_type_id'], 'derived_rate_plan_id' => $data['rate_plan_id']);
        $chek_parents = DerivedPlan::select('*')->where($conds)->get();
        if (sizeof($chek_parents) > 0) {
            if ($data['extra_adult_price'] == 0 || $data['extra_adult_price'] == "") {
                $data['extra_adult_price'] = $this->getExtraAdultChildPrice($data, 1);
            }
            if ($data['extra_child_price'] == 0 || $data['extra_child_price'] == "") {
                $data['extra_child_price'] = $this->getExtraAdultChildPrice($data, 2);
            }
            $response = $this->bulkBeOtaPush($imp_data, $data);
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
                $response = $this->bulkBeOtaPush($imp_data, $data);
            }
        } else {
            $response = $this->bulkBeOtaPush($imp_data, $data);
        }
        return response()->json($response);
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
                            // $check_coupon = Coupons::select('discount')
                            //     ->where(["hotel_id" => $hotel_id, "room_type_id" => $room_type_id, 'coupon_for' => 1])
                            //     ->where('valid_from', '<=', $index)
                            //     ->where('valid_to', '>=', $index)
                            //     ->orWhere(function ($query) use ($hotel_id, $index) {
                            //         $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                            //             ->where('valid_from', '<=', $index)
                            //             ->where('valid_to', '>=', $index);
                            //     })
                            //     ->orderBy('coupon_id', 'DESC')
                            //     ->first();
                            // if ($check_coupon) {
                            //     $discount_per = $check_coupon->discount;
                            //     $discount_amt = round($be_opt['bar_price'] * $discount_per / 100);
                            //     $price = $be_opt['bar_price'] - $discount_amt;

                            //     $rate_update = $this->rateUpdateToGoogleAds($be_opt['hotel_id'], $be_opt['room_type_id'], $be_opt['rate_plan_id'], $index, $index, $price, $be_opt['multiple_days']);
                            // } else {
                                $rate_update = $this->rateUpdateToGoogleAds($be_opt['hotel_id'], $be_opt['room_type_id'], $be_opt['rate_plan_id'], $index, $index, $be_opt['bar_price'], $be_opt['multiple_days']);
                            // }
                        }
                        $los_updatec = $this->losUpdateToGoogleAds($be_opt['hotel_id'], $be_opt['room_type_id'], $be_opt['rate_plan_id'], $be_opt['from_date'], $be_opt['to_date'], $be_opt['los'], $be_opt['multiple_days'],0);
                    }
                    $log_data                 = [
                        "action_id"          => 2,
                        "hotel_id"           => $imp_data['hotel_id'],
                        "ota_id"             => 0,
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
                    $resp = array('status' => 0, 'response_msg' => 'rate update unsucessfully in Booking engine', 'ota_name' => 'Bookingjini');
                }
            }
        }
        return $resp;
    }


    //block rate in booking engine
    public function blockRateUpdate(Request $request)
    {
        // try {
            $failure_message = 'Block inventry operation failed';
            $validator = Validator::make($request->all(), $this->rules, $this->messages);
            if ($validator->fails()) {
                return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
            }
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

            // if($data['hotel_id'] == 1953){
            $date1 = date_create($data['date_from']);
            $date2 = date_create($data['date_to']);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            if ($diff > 60) {
                $check_google_hotel = $this->googleHotelStatus($data['hotel_id']);
                if ($check_google_hotel) {
                    return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 60 days', 'ota_name' => 'bookingjini');
                }
            }
            // }
            if(isset($data['multiple_days']))
            {
                $data['multiple_days']          = json_encode($data['multiple_days']);
                $multiple_days = $data['multiple_days'];
            }
            else
            {
                $data['multiple_days'] = '{"Sun":1,"Mon":1,"Tue":1,"Wed":1,"Thu":1,"Fri":1,"Sat":1}';
                $multiple_days = $data['multiple_days'];
            }
            

            $data['date_from'] = date('Y-m-d', strtotime($data['date_from']));
            $data['date_to'] = date('Y-m-d', strtotime($data['date_to']));
            $imp_data = $this->impDate($data, $request); //used to get user id and client ip.
            $resp = array();
            foreach ($imp_data['ota_id'] as $otaid) {
                if ($otaid == -1) {
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

                            $rate_data = [
                                'hotel_id'          => $getRateDetails->hotel_id,
                                'room_type_id'      => $getRateDetails->room_type_id,
                                'rate_plan_id'      => $getRateDetails->rate_plan_id,
                                'bar_price'         => $getRateDetails->bar_price,
                                'multiple_occupancy' => $getRateDetails->multiple_occupancy,
                                'multiple_days'     => $multiple_days,
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

                                // if($rate_data['hotel_id'] == 1953){

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

                                    $cur_inv_be = CurrentRateBe::updateOrInsert($cond_current_rate_data, $current_rate_data);  

                                // }

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
                                    $los_updatec = $this->losUpdateToGoogleAds($getRateDetails->hotel_id, $getRateDetails->room_type_id, $getRateDetails->rate_plan_id, $date_from, $date_from, $getRateDetails->los, $multiple_days,$data['block_status']);
                                }

                                $return_resp = array('status' => 1, 'response_msg' => 'Rate plan blocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
                            } else {
                                $return_resp = array('status' => 0, 'response_msg' => 'Rate plan block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
                            }
                            $date_from = date('Y-m-d', strtotime($date_from . '+1 day'));
                        }
                    }
                    return $return_resp;
                }
            }

        // } catch (\Exception $e) {
        //     $res = array('status' => -1, 'response_msg' => $e->getMessage());

        //     $result = array('status' => -1, 'response_msg' => $e->getMessage(), 'file_name' => $e->getFile(), 'line_number' => $e->getLine(), 'end_point' => $request->url(), 'request' => array("method" => $request->method(), "request" => $request->all()));

        //     $result = json_encode($result);
        //     $result = Commonmodel::curlPostWhatsApp("https://dev.be.bookingjini.com/error-code-notification", $result);

        //     return response()->json($res);
        // }
    }

    //Block rate for specific date range.
    public function BlockRateForSpecificDates(request $request)
    {
        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $ota_id = $data['ota_id'];
        $insert_rate = '';
        $imp_data = $this->impDate($data, $request); //used to get user id and client ip.

        foreach ($data['inv'] as $inv) {
            $rate_plan_id = $inv['rate_plan_id'];
            $date_from = date('Y-m-d', strtotime($inv['date']));
            $date_to = date('Y-m-d', strtotime($inv['date']));


            $rate_range_table = RatePlanLog::where('hotel_id', $hotel_id)
                ->where('room_type_id', $room_type_id)
                ->where('rate_plan_id', $rate_plan_id)
                ->where('from_date', '<=', $date_from)
                ->where('to_date', '>=', $date_to)
                //->where('block_status', 0)
                ->orderBy('rate_plan_log_id', 'DESC')
                ->first();

            if (!empty($rate_range_table) && $rate_range_table->block_status == 0) {
                $rate_array = [
                    'hotel_id'                        => $hotel_id,
                    'room_type_id'                    => $room_type_id,
                    'rate_plan_id'                    => $rate_plan_id,
                    'bar_price'                       => $rate_range_table['bar_price'],
                    'multiple_occupancy'              => $rate_range_table['multiple_occupancy'],
                    'multiple_days'                   => '{"Sun":1,"Mon":1,"Tue":1,"Wed":1,"Thu":1,"Fri":1,"Sat":1}',
                    'from_date'                       => $date_from,
                    'to_date'                         => $date_to,
                    'block_status'                    => 1,
                    'los'                             => $rate_range_table['los'],
                    'client_ip'                       => $imp_data['client_ip'],
                    'user_id'                         => $imp_data['user_id'],
                    'extra_adult_price'               => $rate_range_table['extra_adult_price'],
                    'extra_child_price'               => $rate_range_table['extra_child_price']
                ];

                
                try {
                    $insert_rate = RatePlanLog::insert($rate_array);
                    $base_rate     = new BaseRate();
                    $insertBaseRate = $base_rate->fill($rate_array)->save();

                    // if($rate_array['hotel_id'] == 1953){

                        $current_rate_data                   = [
                            "hotel_id"          => $rate_array['hotel_id'],
                            "room_type_id"        => $rate_array['room_type_id'],
                            "rate_plan_id"        => $rate_array['rate_plan_id'],
                            "ota_id"             => '-1',
                            "ota_name"             => "Bookingjini",
                            "stay_date"             => $date_from,
                            "bar_price"             => $rate_array['bar_price'],
                            "multiple_occupancy" => $rate_array['multiple_occupancy'],
                            "multiple_days" => $rate_array['multiple_days'],
                            'block_status'      => 1,
                            "extra_adult_price" => $rate_array['extra_adult_price'],
                            "extra_child_price" => $rate_array['extra_child_price'],
                            "los" => $rate_array['los']
                        ];
    
                        $cond_current_rate_data                   = [
                            "hotel_id"          => $rate_array['hotel_id'],
                            "room_type_id"      => $rate_array['room_type_id'],
                            "rate_plan_id"      => $rate_array['rate_plan_id'],
                            "ota_id"            => '-1',
                            "stay_date"         => $date_from
                        ];
    
                        $cur_inv = CurrentRate::updateOrInsert($cond_current_rate_data, $current_rate_data);
                        $cur_inv_be = CurrentRateBe::updateOrInsert($cond_current_rate_data, $current_rate_data);  

                    // }

                    if ($this->googleHotelStatus($hotel_id) == true) {
                        $hotel_id = $rate_array['hotel_id'];
                        $room_type_id = $rate_array['room_type_id'];
                        $index = $rate_array['from_date'];
                        // $check_coupon = Coupons::select('discount')
                        //     ->where(["hotel_id" => $hotel_id, "room_type_id" => $room_type_id, 'coupon_for' => 1])
                        //     ->where('valid_from', '<=', $index)
                        //     ->where('valid_to', '>=', $index)
                        //     ->orWhere(function ($query) use ($hotel_id, $index) {
                        //         $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                        //             ->where('valid_from', '<=', $index)
                        //             ->where('valid_to', '>=', $index);
                        //     })
                        //     ->orderBy('coupon_id', 'DESC')
                        //     ->first();
                        // if ($check_coupon) {
                        //     $discount_per = $check_coupon->discount;
                        //     $discount_amt = round($rate_array['bar_price'] * $discount_per / 100);
                        //     $price = $rate_array['bar_price'] - $discount_amt;

                        //     $rate_update = $this->rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $price, $rate_array['multiple_days']);
                        // } else {
                            $rate_update = $this->rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $rate_array['bar_price'], $rate_array['multiple_days']);
                        // }

                        // if($rate_array['hotel_id'] == 42){
                            $los_update = $this->losUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $rate_array['los'], $rate_array['multiple_days'],$inv['block_status']);
                        // }
                        
                    }
                } catch (\Exception $e) {
                }
            } else {
                continue;
            }
        }
        if ($insert_rate) {
            return  $return_resp = array('status' => 1, 'response_msg' => 'Room type blocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        } else {
            return  $return_resp = array('status' => 0, 'response_msg' => 'Room type block unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }
    }

    //Unblock rate for specific rate range.
    public function UnblockRateForSpecificaDates(request $request)
    {
        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $ota_id = $data['ota_id'];
        $insert_rate_range = '';
        $imp_data = $this->impDate($data, $request); //used to get user id and client ip.

        foreach ($data['inv'] as $inv) {
            $rate_plan_id = $inv['rate_plan_id'];
            $date_from = date('Y-m-d', strtotime($inv['date']));
            $date_to = date('Y-m-d', strtotime($inv['date']));

            $specific_rate_table = RatePlanLog::where('hotel_id', $hotel_id)
                ->where('room_type_id', $room_type_id)
                ->where('rate_plan_id', $rate_plan_id)
                ->where('from_date', '<=', $date_from)
                ->where('to_date', '>=', $date_to)
                //->where('block_status', 1)
                ->orderBy('rate_plan_log_id', 'DESC')
                ->first();

            if (!empty($specific_rate_table) && $specific_rate_table->block_status == 1) {

                $rate_array = [
                    'hotel_id'                        => $hotel_id,
                    'room_type_id'                    => $room_type_id,
                    'rate_plan_id'                    => $specific_rate_table['rate_plan_id'],
                    'bar_price'                       => $specific_rate_table['bar_price'],
                    'multiple_occupancy'              => $specific_rate_table['multiple_occupancy'],
                    'multiple_days'                   => $specific_rate_table['multiple_days'],
                    'from_date'                       => $date_from,
                    'to_date'                         => $date_to,
                    'block_status'                    => 0,
                    'los'                             => $specific_rate_table['los'],
                    'client_ip'                       => $imp_data['client_ip'],
                    'user_id'                         => $imp_data['user_id'],
                    'extra_adult_price'               => $specific_rate_table['extra_adult_price'],
                    'extra_child_price'               => $specific_rate_table['extra_child_price']
                ];
                try {
                    $insert_rate_range = RatePlanLog::insert($rate_array);
                    $base_rate     = new BaseRate();
                    $insertBaseRate = $base_rate->fill($rate_array)->save();

                    // if($rate_array['hotel_id'] == 1953){
                        $current_rate_data                   = [
                            "hotel_id"          => $rate_array['hotel_id'],
                            "room_type_id"        => $rate_array['room_type_id'],
                            "rate_plan_id"        => $rate_array['rate_plan_id'],
                            "ota_id"             => '-1',
                            "ota_name"             => "Bookingjini",
                            "stay_date"             => $date_from,
                            "bar_price"             => $rate_array['bar_price'],
                            "multiple_occupancy" => $rate_array['multiple_occupancy'],
                            "multiple_days" => $rate_array['multiple_days'],
                            'block_status'      => 0,
                            "extra_adult_price" => $rate_array['extra_adult_price'],
                            "extra_child_price" => $rate_array['extra_child_price'],
                            "los" => $rate_array['los']
                        ];
    
                        $cond_current_rate_data                   = [
                            "hotel_id"          => $rate_array['hotel_id'],
                            "room_type_id"      => $rate_array['room_type_id'],
                            "rate_plan_id"      => $rate_array['rate_plan_id'],
                            "ota_id"            => '-1',
                            "stay_date"         => $date_from
                        ];
    
                        $cur_inv = CurrentRate::updateOrInsert($cond_current_rate_data, $current_rate_data);
                        $cur_inv_be = CurrentRateBe::updateOrInsert($cond_current_rate_data, $current_rate_data);  
                    // }
                    

                    if ($this->googleHotelStatus($hotel_id) == true) {
                        $hotel_id = $rate_array['hotel_id'];
                        $room_type_id = $rate_array['room_type_id'];
                        $index = $rate_array['from_date'];
                        // $check_coupon = Coupons::select('discount')
                        //     ->where(["hotel_id" => $hotel_id, "room_type_id" => $room_type_id, 'coupon_for' => 1])
                        //     ->where('valid_from', '<=', $index)
                        //     ->where('valid_to', '>=', $index)
                        //     ->orWhere(function ($query) use ($hotel_id, $index) {
                        //         $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                        //             ->where('valid_from', '<=', $index)
                        //             ->where('valid_to', '>=', $index);
                        //     })
                        //     ->orderBy('coupon_id', 'DESC')
                        //     ->first();
                        // if ($check_coupon) {
                        //     $discount_per = $check_coupon->discount;
                        //     $discount_amt = round($rate_array['bar_price'] * $discount_per / 100);
                        //     $price = $rate_array['bar_price'] - $discount_amt;

                        //     $rate_update = $this->rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $price, $rate_array['multiple_days']);
                        // } else {
                            $rate_update = $this->rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $rate_array['bar_price'], $rate_array['multiple_days']);
                        // }
                        
                        // if($rate_array['hotel_id'] == 42){
                             $los_update = $this->losUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $rate_array['los'], $rate_array['multiple_days'],$inv['block_status']);
                        // }
                    }
                } catch (\Exception $e) {
                }
            } else {
                continue;
            }
        }
        if ($insert_rate_range) {
            return  $return_resp = array('status' => 1, 'response_msg' => 'Room type unblocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        } else {
            return  $return_resp = array('status' => 0, 'response_msg' => 'Room type unblock unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
        }
    }

    //Unblock rate for rate range.
    public function UnblockRateForDateRange(request $request)
    {

        $failure_message = 'Inventory sync failed';
        $validator = Validator::make($request->all(), $this->inv_rules, $this->inv_messages);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'response_msg' => $failure_message, 'errors' => $validator->errors()));
        }
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $insert_rate_range = '';

        // if($data['hotel_id'] == 1953){
        $date1 = date_create($data['date_from']);
        $date2 = date_create($data['date_to']);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        $imp_data = $this->impDate($data, $request); //used to get user id and client ip.


        if ($diff > 60) {
            $check_google_hotel = $this->googleHotelStatus($data['hotel_id']);
            if ($check_google_hotel) {
                return array('status' => 0, 'response_msg' => 'Date range must be less than or equal to 60 days', 'ota_name' => 'bookingjini');
            }
        }
        // }

        if(isset($data['multiple_days']))
        {
            $data['multiple_days']          = json_encode($data['multiple_days']);
            $multiple_days = $data['multiple_days'];
        }
        else
        {
            $data['multiple_days'] = '{"Sun":1,"Mon":1,"Tue":1,"Wed":1,"Thu":1,"Fri":1,"Sat":1}';
            $multiple_days = $data['multiple_days'];
        }

        // if($hotel_id == 2600)
        // {
        //     print_r($data['rate_plan_id']);exit;
        // }

        foreach ($data['rate_plan_id'] as $rate_plan_id) {
            $date_from = date('Y-m-d', strtotime($data['date_from']));
            $date_to = date('Y-m-d', strtotime($data['date_to'] . '+1 day'));
            $p_start = $date_from;
            $p_end = $date_to;
            $period     = new \DatePeriod(
                new \DateTime($p_start),
                new \DateInterval('P1D'),
                new \DateTime($p_end)
            );
            foreach ($period as $value) {
                $index = $value->format('Y-m-d');

                $rate_range_table = RatePlanLog::where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->where('rate_plan_id', $rate_plan_id)
                    ->where('from_date', '<=', $index)
                    ->where('to_date', '>=', $index)
                    //->where('block_status', 1)
                    ->orderBy('rate_plan_log_id', 'DESC')
                    ->first();

                if (!empty($rate_range_table) && $rate_range_table->block_status == 1) {

                    $rate_array = [
                        'hotel_id'                        => $hotel_id,
                        'room_type_id'                    => $room_type_id,
                        'rate_plan_id'                    => $rate_plan_id,
                        'bar_price'                       => $rate_range_table['bar_price'],
                        'multiple_occupancy'              => $rate_range_table['multiple_occupancy'],
                        'multiple_days'                   => $multiple_days,
                        'from_date'                       => $index,
                        'to_date'                         => $index,
                        'block_status'                    => 0,
                        'los'                             => $rate_range_table['los'],
                        'client_ip'                       => $imp_data['client_ip'],
                        'user_id'                         => $imp_data['user_id'],
                        'extra_adult_price'               => $rate_range_table['extra_adult_price'],
                        'extra_child_price'               => $rate_range_table['extra_child_price']
                    ];
                    try {
                        $insert_rate_range = RatePlanLog::insert($rate_array);
                        $base_rate     = new BaseRate();
                        $insertBaseRate = $base_rate->fill($rate_array)->save();

                        // if($rate_array['hotel_id'] == 1953){
                            $current_rate_data                   = [
                                "hotel_id"          => $rate_array['hotel_id'],
                                "room_type_id"        => $rate_array['room_type_id'],
                                "rate_plan_id"        => $rate_array['rate_plan_id'],
                                "ota_id"             => '-1',
                                "ota_name"             => "Bookingjini",
                                "stay_date"             => $index,
                                "bar_price"             => $rate_array['bar_price'],
                                "multiple_occupancy" => $rate_array['multiple_occupancy'],
                                "multiple_days" => $rate_array['multiple_days'],
                                'block_status'      => 0,
                                "extra_adult_price" => $rate_array['extra_adult_price'],
                                "extra_child_price" => $rate_array['extra_child_price'],
                                "los" => $rate_array['los']
                            ];
    
                            $cond_current_rate_data                   = [
                                "hotel_id"          => $rate_array['hotel_id'],
                                "room_type_id"      => $rate_array['room_type_id'],
                                "rate_plan_id"      => $rate_array['rate_plan_id'],
                                "ota_id"            => '-1',
                                "stay_date"         => $index
                            ];
    
                            $cur_inv = CurrentRate::updateOrInsert($cond_current_rate_data, $current_rate_data);
                            $cur_inv_be = CurrentRateBe::updateOrInsert($cond_current_rate_data, $current_rate_data);  
                        // }
                       

                        if ($this->googleHotelStatus($hotel_id) == true) {
                            $hotel_id = $rate_array['hotel_id'];
                            $room_type_id = $rate_array['room_type_id'];
                            $index = $rate_array['from_date'];
                            // $check_coupon = Coupons::select('discount')
                            //     ->where(["hotel_id" => $hotel_id, "room_type_id" => $room_type_id, 'coupon_for' => 1])
                            //     ->where('valid_from', '<=', $index)
                            //     ->where('valid_to', '>=', $index)
                            //     ->orWhere(function ($query) use ($hotel_id, $index) {
                            //         $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                            //             ->where('valid_from', '<=', $index)
                            //             ->where('valid_to', '>=', $index);
                            //     })
                            //     ->orderBy('coupon_id', 'DESC')
                            //     ->first();
                            // if ($check_coupon) {
                            //     $discount_per = $check_coupon->discount;
                            //     $discount_amt = round($rate_array['bar_price'] * $discount_per / 100);
                            //     $price = $rate_array['bar_price'] - $discount_amt;

                            //     $rate_update = $this->rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $price, $rate_array['multiple_days']);
                            // } else {
                                $rate_update = $this->rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $rate_array['bar_price'], $rate_array['multiple_days']);
                            // }
                            $los_updatec = $this->losUpdateToGoogleAds($hotel_id, $room_type_id, $rate_array['rate_plan_id'], $rate_array['from_date'], $rate_array['to_date'], $rate_array['los'], $rate_array['multiple_days'],$data['block_status']);
                        }
                    } catch (\Exception $e) {
                    }
                } else {
                    continue;
                }
            }
        }

        if ($insert_rate_range) {
            return  $return_resp = array('status' => 1, 'response_msg' => 'rate plan unblocked successfully on Booking Engine', 'ota_name' => 'Bookingjini');
        } else {
            return  $return_resp = array('status' => 0, 'response_msg' => 'rate plan unblock unsuccessfully on Booking Engine', 'ota_name' => 'Bookingjini');
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
        } elseif (isset($request->auth->intranet_id)) {
            $user_id = $request->auth->intranet_id;
        } elseif (isset($request->auth->id)) {
            $user_id = $request->auth->id;
        } else {
            if ($data['admin_id'] != 0) {
                $user_id = $data['admin_id'];
            }
        }

        return array('hotel_id' => $hotel_id, 'ota_id' => $ota_id, 'client_ip' => $client_ip, 'user_id' => $user_id);
    }

    //Added by Jigyans dt : - 25-07-2023
    public function pmsImpDate($data)
    {
        $hotel_id = $data['hotel_id'];
        $ota_id = isset($data['ota_id'])?$data['ota_id']:0;
        $client_ip = $data['client_ip'];
        $user_id = $data['user_id'];
        $ota_booking_id = $data['bucket_id'];
        $action_status = $data['action_status'];
        $pms_id = $data['pms_id'];
        $pms_name = $data['pms_name'];
        $pms_status = $data['pms_status'];
        $source = "PMS";
        return array('hotel_id' => $hotel_id, 'ota_id' => $ota_id, 'client_ip' => $client_ip, 'user_id' => $user_id,'ota_booking_id' => $ota_booking_id,'action_status' => $action_status,'pms_id' => $pms_id,'pms_name' => $pms_name,'pms_status' => $pms_status,'source' => $source);
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

    public function inventoryUpdateToGoogleAds($hotel_id, $room_type_id, $no_of_rooms, $from_date, $to_date)
    {
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date));
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
                <Inventories HotelCode="' . $hotel_id . '">
                    <Inventory>
                    <StatusApplicationControl Start="' . $from_date . '"
                                                End="' . $to_date . '"
                                                InvTypeCode="' . $room_type_id . '"/>
                    <InvCounts>
                        <InvCount Count="' . $no_of_rooms . '" CountType="2"/>
                    </InvCounts>
                    </Inventory>
                </Inventories>
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
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
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
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'response_msg' => 'inventory updation fails');
            return response()->json($resp);
        }
    }

    public function rateUpdateToGoogleAds($hotel_id, $room_type_id, $rate_plan_id, $from_date, $to_date, $bar_price, $multiple_days)
    {

        $hotel_info = HotelInformation::join('kernel.company_table','hotels_table.company_id','=','company_table.company_id')
        ->where('hotel_id',$hotel_id)
        ->select('hotels_table.is_taxable','company_table.currency')
        ->first();

        if(isset($hotel_info->currency)){
            $currency = $hotel_info->currency;
        }else{
            $currency ='INR';
        }

        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date));
        $id = uniqid();
        $time = time();
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';

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
                                                CurrencyCode="'.$currency.'"
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

    public function losUpdateToGoogleAdsOld($hotel_id, $room_type_id, $rate_plan_id, $from_date, $to_date, $los, $multiple_days)
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

    public function gstPrice($bar_price)
    {
        $percentage = 0;
        if ($bar_price > 7500) {
            $percentage = 18;
        } elseif ($bar_price > 0 && $bar_price <= 7500) {
            $percentage = 12;
        }
        $gstprice = $bar_price * $percentage / 100;
        return $gstprice;
    }

    public function dynamicPricingRateUpdate(Request $request)
    {
        $details = $request->getContent();
        parse_str($details, $data);
        $hotel_id = $data['hotel_id'];
        $ota_id[] = $data['ota_id'];
        $client_ip = $data['client_ip'];
        $user_id = $data['admin_id'];
        $dp_imp_data = array(
            'hotel_id' => $hotel_id,
            'ota_id' => $ota_id,
            'client_ip' => $client_ip,
            'user_id' => $user_id,
            'dp_status' => 1
        );
        $update_dp_to_be = $this->bulkBeOtaPush($dp_imp_data, $data);
    }

    public function googleHotelStatusNew($hotel_id)
    {
        $getHotelDetails = MetaSearchEngineSetting::select('hotels')->where('name', 'google-hotel')->first();
        $hotel_ids = explode(',', $getHotelDetails->hotels);
        if (in_array($hotel_id, $hotel_ids)) {
            return 1;
        } else {
            return 0;
        }
    }

    public function losUpdateToGoogleAds($hotel_id, $room_type_id, $rate_plan_id, $from_date, $to_date, $los, $multiple_days,$block_status)
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
                        </LengthsOfStay>';
                        if($block_status==0){
                            $block_status = 'Open';
                        }else{
                            $block_status = 'Close';
                        }
                        $xml_data .= '<RestrictionStatus Status="'.$block_status.'" Restriction="Master"/>

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
}
