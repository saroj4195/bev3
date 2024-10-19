<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\CmOtaBooking;
use App\RatePlanLog;
use App\Inventory;
use App\OtaInventory;
use App\OtaRatePlan;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaDetails;
use App\Http\Controllers\Controller;
class InvRateBookingDisplayController extends Controller
{
    /**
     * This controller only used for displaying inventory,rate and bookings
     * @author @ranjit date 31-05-2019
     */

    private $rules_inv=array(
        'hotel_id'=>'required | numeric',
        'room_type_id'=>'required'
    );
    private $message_inv=[
        'hotel_id.required'=>'hotel code field is required',
        'hotel_id.numeric'=>'hotel code should be numeric',
        'room_type_id.required'=>'room type is needed'
    ];
    private $rules_rate=array(
        'hotel_id'=>'required | numeric',
        'room_type_id'=>'required',
        'rate_plan_id'=>'required'
    );
    private $message_rate=[
        'hotel_id.required'=>'hotel code field is required',
        'hotel_id.numeric'=>'hotel code should be numeric',
        'room_type_id.required'=>'room type is needed',
        'rate_plan_id.required'=>'rate plan is needed'
    ];
    //used to get inventory details channel wise
    public function invData(Request $request){
        $failure_message="Please! provide appropriate data";
        $validator=Validator::make($request->all(),$this->rules_inv,$this->message_inv);
        if($validator->fails())
        {
           return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
        }
        $data=$request->all();
        $channelwise_inventory=array();
        $cm_hotel_otas  = DB::table('cm_ota_details')
                            ->select('ota_name','ota_id')
                            ->where('hotel_id',$data['hotel_id'])
                            ->where('is_active',1)
                            ->get();
        $timestamp = strtotime($data['date']);
        $day = date('D', $timestamp);
        $data['date'] = date('Y-m-d',strtotime($data['date']));
        foreach($cm_hotel_otas as $otas){
            $key=$otas->ota_name;
            $ota_id=$otas->ota_id;
            $getdata = OtaInventory::select('*')
                            ->where('room_type_id' , '=' , $data['room_type_id'])
                            ->where('hotel_id' , '=' , $data['hotel_id'])
                            ->where('channel',$key)
                            ->where('date_from' , '<=' , $data['date'])
                            ->where('date_to' , '>=' , $data['date'])
                            ->orderBy('inventory_id', 'desc')
                            ->first();
            $getbookingdate=$this->bookingData($data['date'],$data['hotel_id'],$key,$data['room_type_id']);
            if($getdata){
                if($getdata->multiple_days == "sync"){
                    $getBeData = Inventory::select('*')
                            ->where('room_type_id' , '=' , $data['room_type_id'])
                            ->where('hotel_id' , '=' , $data['hotel_id'])
                            ->where('date_from' , '<=' , $data['date'])
                            ->where('date_to' , '>=' , $data['date'])
                            ->orderBy('inventory_id', 'desc')
                            ->first();

                    if($getBeData){
                        if($getBeData->block_status == 0){
                            $channelwise_inventory[$key][0]=$getBeData->no_of_rooms;
                            $channelwise_inventory[$key][2]=$getBeData->block_status;
                            $channelwise_inventory[$key][3]=$getBeData->room_type_id;
                            $channelwise_inventory[$key][4]=$getBeData->date_from;
                            $channelwise_inventory[$key][5]=$getBeData->date_to;
                        }
                        else{
                            $getdata_nonblk = Inventory::select('*')
                                    ->where('room_type_id' , '=' , $data['room_type_id'])
                                    ->where('hotel_id' , '=' , $data['hotel_id'])
                                    ->where('date_from' , '<=' , $data['date'])
                                    ->where('date_to' , '>=' , $data['date'])
                                    ->where('block_status','=',0)
                                    ->orderBy('inventory_id', 'desc')
                                    ->first();
                            if(sizeof($getdata_nonblk)>0){
                                $channelwise_inventory[$key][0]=$getdata_nonblk->no_of_rooms;
                                $channelwise_inventory[$key][2]=$getBeData->block_status;
                                $channelwise_inventory[$key][3]=$getBeData->room_type_id;
                                $channelwise_inventory[$key][4]=$getBeData->date_from;
                                $channelwise_inventory[$key][5]=$getBeData->date_to;
                            }
                            else{
                                $channelwise_inventory[$key][0]=1;
                                $channelwise_inventory[$key][2]=$getBeData->block_status;
                                $channelwise_inventory[$key][3]=$getBeData->room_type_id;
                                $channelwise_inventory[$key][4]=$getBeData->date_from;
                                $channelwise_inventory[$key][5]=$getBeData->date_to;
                            }
                        }
                    }
                    else{
                        $channelwise_inventory[$key][0]='-';
                        $channelwise_inventory[$key][2]=0;
                    }
                }
                else{
                    if($getdata->block_status == 0){
                        $multiple_days = json_decode($getdata->multiple_days);
                        if($multiple_days != null){
                            if($multiple_days->$day == 0){
                                $getdata1 = OtaInventory::select('*')
                                        ->where('room_type_id' , '=' , $data['room_type_id'])
                                        ->where('hotel_id' , '=' , $data['hotel_id'])
                                        ->where('channel',$key)
                                        ->where('date_from' , '<=' , $data['date'])
                                        ->where('date_to' , '>=' , $data['date'])
                                        ->orderBy('inventory_id', 'desc')
                                        ->skip(1)
                                        ->take(2)
                                        ->get();
                                if(empty($getdata1[0])){
                                    $channelwise_inventory[$key][0]='-';
                                    $channelwise_inventory[$key][2]=$getdata->block_status;
                                    $channelwise_inventory[$key][3]=$getdata->room_type_id;
                                    $channelwise_inventory[$key][4]=$getdata->date_from;
                                    $channelwise_inventory[$key][5]=$getdata->date_to;
                                    $channelwise_inventory[$key][6]=$ota_id;

                                }
                                else{
                                    $multiple_days1=json_decode($getdata1[0]->multiple_days);
                                    if($multiple_days1 != null){
                                        if($multiple_days1->$day == 0){
                                            $getdata2 = OtaInventory::select('*')
                                                        ->where('room_type_id' , '=' , $data['room_type_id'])
                                                        ->where('hotel_id' , '=' , $data['hotel_id'])
                                                        ->where('channel',$key)
                                                        ->where('date_from' , '<=' , $data['date'])
                                                        ->where('date_to' , '>=' , $data['date'])
                                                        ->orderBy('inventory_id', 'desc')
                                                        ->skip(2)
                                                        ->take(3)
                                                        ->get();
                                            if(empty($getdata2)){
                                                $channelwise_inventory[$key][0]='-';
                                                $channelwise_inventory[$key][2]=$getdata->block_status;
                                                $channelwise_inventory[$key][3]=$getdata->room_type_id;
                                                $channelwise_inventory[$key][4]=$getdata->date_from;
                                                $channelwise_inventory[$key][5]=$getdata->date_to;
                                                $channelwise_inventory[$key][6]=$ota_id;
                                            }
                                            else{
                                                $channelwise_inventory[$key][0]=$getdata2[0]->no_of_rooms;
                                                $channelwise_inventory[$key][2]=$getdata2[0]->block_status;
                                                $channelwise_inventory[$key][3]=$getdata2[0]->room_type_id;
                                                $channelwise_inventory[$key][4]=$getdata2[0]->date_from;
                                                $channelwise_inventory[$key][5]=$getdata2[0]->date_to;
                                                $channelwise_inventory[$key][6]=$ota_id;

                                            }
                                        }
                                        else{
                                            $channelwise_inventory[$key][0]=$getdata1[0]->no_of_rooms;
                                            $channelwise_inventory[$key][2]=$getdata1[0]->block_status;
                                            $channelwise_inventory[$key][3]=$getdata1[0]->room_type_id;
                                            $channelwise_inventory[$key][4]=$getdata1[0]->date_from;
                                            $channelwise_inventory[$key][5]=$getdata1[0]->date_to;
                                            $channelwise_inventory[$key][6]=$ota_id;

                                        }
                                    }
                                    else{
                                        $channelwise_inventory[$key][0]=$getdata1[0]->no_of_rooms;
                                        $channelwise_inventory[$key][2]=$getdata1[0]->block_status;
                                        $channelwise_inventory[$key][3]=$getdata1[0]->room_type_id;
                                        $channelwise_inventory[$key][4]=$getdata1[0]->date_from;
                                        $channelwise_inventory[$key][5]=$getdata1[0]->date_to;
                                        $channelwise_inventory[$key][6]=$ota_id;

                                    }
                                }
                            }
                            else{
                                $channelwise_inventory[$key][0]=$getdata->no_of_rooms;
                                $channelwise_inventory[$key][2]=$getdata->block_status;
                                $channelwise_inventory[$key][3]=$getdata->room_type_id;
                                $channelwise_inventory[$key][4]=$getdata->date_from;
                                $channelwise_inventory[$key][5]=$getdata->date_to;
                                $channelwise_inventory[$key][6]=$ota_id;

                            }
                        }
                        else{
                            $channelwise_inventory[$key][0]=$getdata->no_of_rooms;
                            $channelwise_inventory[$key][2]=$getdata->block_status;
                            $channelwise_inventory[$key][3]=$getdata->room_type_id;
                            $channelwise_inventory[$key][4]=$getdata->date_from;
                            $channelwise_inventory[$key][5]=$getdata->date_to;
                            $channelwise_inventory[$key][6]=$ota_id;

                        }
                    }
                    else{
                        $getdata_nonblk = OtaInventory::select('*')
                                ->where('room_type_id' , '=' , $data['room_type_id'])
                                ->where('hotel_id' , '=' , $data['hotel_id'])
                                ->where('channel',$key)
                                ->where('date_from' , '<=' , $data['date'])
                                ->where('date_to' , '>=' , $data['date'])
                                ->where('block_status','=',0)
                                ->orderBy('inventory_id', 'desc')
                                ->first();
                        if(sizeof($getdata_nonblk)>0){
                            $channelwise_inventory[$key][0]=$getdata_nonblk->no_of_rooms;
                            $channelwise_inventory[$key][2]=$getdata->block_status;
                            $channelwise_inventory[$key][3]=$getdata->room_type_id;
                            $channelwise_inventory[$key][4]=$getdata->date_from;
                            $channelwise_inventory[$key][5]=$getdata->date_to;
                            $channelwise_inventory[$key][6]=$ota_id;
                        }
                        else{
                            $channelwise_inventory[$key][0]=1;
                            $channelwise_inventory[$key][2]=$getdata->block_status;
                            $channelwise_inventory[$key][3]=$getdata->room_type_id;
                            $channelwise_inventory[$key][4]=$getdata->date_from;
                            $channelwise_inventory[$key][5]=$getdata->date_to;
                            $channelwise_inventory[$key][6]=$ota_id;
                        }
                    }
                }
            }

                else{
                    $channelwise_inventory[$key][0]='-';
                    $channelwise_inventory[$key][2]=0;
                }
                $channelwise_inventory[$key][1]=$getbookingdate;
                // if($channelwise_inventory[$key][8] == 0 && $channelwise_inventory[$key][2] == 0){
                //     //$return_data = $this->getRestriction($data['room_type_id'],$data['date']);
                //     if($return_data['block_status'] != null){
                //         $channelwise_inventory[$key][2] = $return_data['block_status'];
                //     }
                //     if($return_data['action_status'] != null){
                //         $channelwise_inventory[$key][8] = $return_data['action_status'];
                //     }
                //     if($return_data['restriction_status'] != null){
                //         $channelwise_inventory[$key][7] = $return_data['restriction_status'];
                //     }
                // }
        }
        if(sizeof($channelwise_inventory)>0){
            $resp=array('status'=>1,'message'=>"inventory details and booking details fetched successfully",'data'=>$channelwise_inventory);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>"Same as Panel");
            return response()->json($resp);
        }
    }
    // public function getRestriction($room_type_id,$date_from){
    //     $inventory  =   new OtaInventory();
    //     $inv_details = $inventory
    //                 ->select('*')
    //                 ->where('room_type_id' , '=' , $room_type_id)
    //                 ->where('date_from' , '<=' , $date_from)
    //                 ->where('date_to' , '>=' , $date_from)
    //                 ->where('action_status',1)
    //                 ->where('block_status',1)
    //                 ->orWhere(function($query) use ($room_type_id,$date_from){
    //                     $query->where('room_type_id' , '=' , $room_type_id)
    //                     ->where('date_from' , '<=' , $date_from)
    //                     ->where('date_to' , '>=' , $date_from)
    //                     ->where('action_status', '=', 2)
    //                     ->where('block_status', '=', 0);
    //                     })
    //                 ->orWhere(function($query) use ($room_type_id,$date_from){
    //                     $query->where('room_type_id' , '=' , $room_type_id)
    //                     ->where('date_from' , '<=' , $date_from)
    //                     ->where('date_to' , '>=' , $date_from)
    //                     ->where('restriction_status','!=',0);
    //                     })
    //                 ->orderBy('inventory_id', 'desc')
    //                 ->skip(1)
    //                 ->take(2)
    //                 ->first();
    //     if(sizeof($inv_details)>0){
    //         $details = array('block_status'=>$inv_details->block_status,'action_status'=>$inv_details->action_status,'restriction_status'=>$inv_details->restriction_status);
    //         return $details;
    //     }
    // }
     //used to get booking details channel wise
     public function bookingData($date,$hotel_id,$channel_name,$room_type_id){
        $cmort=new CmOtaRoomTypeSynchronize();
        $channel_data=0;
        if($channel_name == 'Goibibo'){
            $cm_booking_details= CmOtaBooking::select('rooms_qty', 'room_type')
            ->where('hotel_id' , '=' , $hotel_id)
            ->where('channel_name',$channel_name)
            ->where('checkin_at' , '<=' , $date)
            ->where('checkout_at' , '>' , $date)
            ->where('confirm_status' , '=' , 1)
            ->where('cancel_status' , '=' , 0)
            ->orWhere(function($query) use ($hotel_id,$date){
                $query->where('hotel_id' , '=' , $hotel_id)
                ->where('channel_name','MakeMyTrip')
                ->where('checkin_at' , '<=' , $date)
                ->where('checkout_at' , '>' , $date)
                ->where('confirm_status' , '=' , 1)
                ->where('cancel_status' , '=' , 0);
                })
            ->get();
        }
        else{
            $cm_booking_details= CmOtaBooking::select('rooms_qty', 'room_type')
            ->where('hotel_id' , '=' , $hotel_id)
            ->where('channel_name',$channel_name)
            ->where('checkin_at' , '<=' , $date)
            ->where('checkout_at' , '>' , $date)
            ->where('confirm_status' , '=' , 1)
            ->where('cancel_status' , '=' , 0)
            ->get();
        }
        foreach ($cm_booking_details as $cmb)
        {
            $room_type=explode(',',$cmb['room_type']);
            $room_qty=explode(',',$cmb['rooms_qty']);
            if(sizeof( $room_type)!=sizeof($room_qty))
            {
                $channel_data=0;
            }
            else
            {
                for($r=0;$r<sizeof($room_type);$r++)
                {
                    $ota_room_type=$room_type[$r];
                    $hotelroom_id=$cmort->getSingleHotelRoomIdFromRoomSynch($ota_room_type,$hotel_id);
                    if($hotelroom_id==$room_type_id)
                    {
                        $channel_data=$channel_data+$room_qty[$r];
                    }
                }
            }
        }
        if($channel_data > 0){
            return $channel_data;
        }
        else{
            return 0;
        }
    }
    //used to get rate details channel wise
    public function rateData(Request $request){
        $failure_message="Please! provide appropriate data";
        $validator=Validator::make($request->all(),$this->rules_rate,$this->message_rate);
        if($validator->fails())
        {
           return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
        }
        $data=$request->all();
        $channelwise_rate=array();
        $cm_hotel_otas= CmOtaDetails::select('ota_name','ota_id')
                        ->where('hotel_id',$data['hotel_id'])
                        ->where('is_active',1)
                        ->get();
        $timestamp = strtotime($data['date']);
        $day = date('D', $timestamp);
        $data['date'] = date('Y-m-d',strtotime($data['date']));

        foreach($cm_hotel_otas as $otas){
                $key     = $otas->ota_name;
                $ota_id  = $otas->ota_id;
                $getdata = OtaRatePlan::select('*')
                                ->where('room_type_id' , '=' , $data['room_type_id'])
                                ->where('hotel_id' , '=' , $data['hotel_id'])
                                ->where('rate_plan_id' , '=' , $data['rate_plan_id'])
                                ->where('channel',$key)
                                ->where('from_date' , '<=' , $data['date'])
                                ->where('to_date' , '>=' , $data['date'])
                                ->orderBy('rate_plan_log_id', 'desc')
                                ->first();

                if($getdata){
                    if($getdata->multiple_occupancy == "sync"){
                        $getBeData = RatePlanLog::select('*')
                                ->where('room_type_id' , '=' , $data['room_type_id'])
                                ->where('hotel_id' , '=' , $data['hotel_id'])
                                ->where('rate_plan_id' , '=' , $data['rate_plan_id'])
                                ->where('from_date' , '<=' , $data['date'])
                                ->where('to_date' , '>=' , $data['date'])
                                ->orderBy('rate_plan_log_id', 'desc')
                                ->first();

                        $multiple_days = json_decode($getBeData->multiple_days);
                        if($multiple_days != null){
                            if($multiple_days->$day == 0){
                                $getBeData1 = RatePlanLog::select('*')
                                            ->where('room_type_id' , '=' , $data['room_type_id'])
                                            ->where('hotel_id' , '=' , $data['hotel_id'])
                                            ->where('rate_plan_id' , '=' , $data['rate_plan_id'])
                                            ->where('from_date' , '<=' , $data['date'])
                                            ->where('to_date' , '>=' , $data['date'])
                                            ->orderBy('rate_plan_log_id', 'desc')
                                            ->skip(1)
                                            ->take(2)
                                            ->get();
                                if(empty($getBeData1[0])){
                                    $channelwise_rate[$key][0]='-';
                                    $channelwise_rate[$key][1]='-';
                                    $channelwise_rate[$key][2]=$getBeData->block_status;
                                    $channelwise_rate[$key][3]=$getBeData->room_type_id;
                                    $channelwise_rate[$key][4]=$getBeData->rate_plan_id;
                                    $channelwise_rate[$key][5]=$getBeData->extra_adult_price;
                                    $channelwise_rate[$key][6]=$getBeData->extra_child_price;
                                    $channelwise_rate[$key][7]=$getBeData->from_date;
                                    $channelwise_rate[$key][8]=$getBeData->to_date;
                                    $channelwise_rate[$key][9]=$ota_id;
                                }
                                else{
                                    $multiple_days1 = json_decode($getBeData1[0]->multiple_days);
                                    if($multiple_days1 != null){
                                        if($multiple_days1->$day == 0){
                                            $getBeData2 = RatePlanLog::select('*')
                                                    ->where('room_type_id' , '=' , $data['room_type_id'])
                                                    ->where('hotel_id' , '=' , $data['hotel_id'])
                                                    ->where('rate_plan_id' , '=' , $data['rate_plan_id'])
                                                    ->where('from_date' , '<=' , $data['date'])
                                                    ->where('to_date' , '>=' , $data['date'])
                                                    ->orderBy('rate_plan_log_id', 'desc')
                                                    ->skip(2)
                                                    ->take(3)
                                                    ->get();
                                            if(empty($getdata2[0])){
                                                $channelwise_rate[$key][0]='-';
                                                $channelwise_rate[$key][1]='-';
                                                $channelwise_rate[$key][2]=$getBeData->block_status;
                                                $channelwise_rate[$key][3]=$getBeData->room_type_id;
                                                $channelwise_rate[$key][4]=$getBeData->rate_plan_id;
                                                $channelwise_rate[$key][5]=$getBeData->extra_adult_price;
                                                $channelwise_rate[$key][6]=$getBeData->extra_child_price;
                                                $channelwise_rate[$key][7]=$getBeData->from_date;
                                                $channelwise_rate[$key][8]=$getBeData->to_date;
                                                $channelwise_rate[$key][9]=$ota_id;
                                            }
                                            else{
                                                $channelwise_rate[$key][0]=$getBeData2[0]->bar_price;
                                                $channelwise_rate[$key][1]=json_decode($getBeData2[0]->multiple_occupancy);
                                                $channelwise_rate[$key][2]=$getBeData2[0]->block_status;
                                                $channelwise_rate[$key][3]=$getBeData2[0]->room_type_id;
                                                $channelwise_rate[$key][4]=$getBeData2[0]->rate_plan_id;
                                                $channelwise_rate[$key][5]=$getBeData2[0]->extra_adult_price;
                                                $channelwise_rate[$key][6]=$getBeData2[0]->extra_child_price;
                                                $channelwise_rate[$key][7]=$getBeData2[0]->from_date;
                                                $channelwise_rate[$key][8]=$getBeData2[0]->to_date;
                                                $channelwise_rate[$key][9]=$ota_id;
                                            }
                                        }
                                        else{
                                            $channelwise_rate[$key][0]=$getBeData1[0]->bar_price;
                                            $channelwise_rate[$key][1]=json_decode($getBeData1[0]->multiple_occupancy);
                                            $channelwise_rate[$key][2]=$getBeData1[0]->block_status;
                                            $channelwise_rate[$key][3]=$getBeData1[0]->room_type_id;
                                            $channelwise_rate[$key][4]=$getBeData1[0]->rate_plan_id;
                                            $channelwise_rate[$key][5]=$getBeData1[0]->extra_adult_price;
                                            $channelwise_rate[$key][6]=$getBeData1[0]->extra_child_price;
                                            $channelwise_rate[$key][7]=$getBeData1[0]->from_date;
                                            $channelwise_rate[$key][8]=$getBeData1[0]->to_date;
                                            $channelwise_rate[$key][9]=$ota_id;
                                        }
                                    }
                                    else{
                                        $channelwise_rate[$key][0]=$getBeData1[0]->bar_price;
                                        $channelwise_rate[$key][1]=json_decode($getBeData1[0]->multiple_occupancy);
                                        $channelwise_rate[$key][2]=$getBeData1[0]->block_status;
                                        $channelwise_rate[$key][3]=$getBeData1[0]->room_type_id;
                                        $channelwise_rate[$key][4]=$getBeData1[0]->rate_plan_id;
                                        $channelwise_rate[$key][5]=$getBeData1[0]->extra_adult_price;
                                        $channelwise_rate[$key][6]=$getBeData1[0]->extra_child_price;
                                        $channelwise_rate[$key][7]=$getBeData1[0]->from_date;
                                        $channelwise_rate[$key][8]=$getBeData1[0]->to_date;
                                        $channelwise_rate[$key][9]=$ota_id;
                                    }
                                }
                            }
                            else{
                                $channelwise_rate[$key][0]=$getBeData->bar_price;
                                $channelwise_rate[$key][1]=json_decode($getBeData->multiple_occupancy);
                                $channelwise_rate[$key][2]=$getBeData->block_status;
                                $channelwise_rate[$key][3]=$getBeData->room_type_id;
                                $channelwise_rate[$key][4]=$getBeData->rate_plan_id;
                                $channelwise_rate[$key][5]=$getBeData->extra_adult_price;
                                $channelwise_rate[$key][6]=$getBeData->extra_child_price;
                                $channelwise_rate[$key][7]=$getBeData->from_date;
                                $channelwise_rate[$key][8]=$getBeData->to_date;
                                $channelwise_rate[$key][9]=$ota_id;
                            }
                        }
                        else{
                            $channelwise_rate[$key][0]=$getBeData->bar_price;
                            $channelwise_rate[$key][1]=json_decode($getBeData->multiple_occupancy);
                            $channelwise_rate[$key][2]=$getBeData->block_status;
                            $channelwise_rate[$key][3]=$getBeData->room_type_id;
                            $channelwise_rate[$key][4]=$getBeData->rate_plan_id;
                            $channelwise_rate[$key][5]=$getBeData->extra_adult_price;
                            $channelwise_rate[$key][6]=$getBeData->extra_child_price;
                            $channelwise_rate[$key][7]=$getBeData->from_date;
                            $channelwise_rate[$key][8]=$getBeData->to_date;
                            $channelwise_rate[$key][9]=$ota_id;

                        }
                    }
                    else{
                        $multiple_days = json_decode($getdata->multiple_days);
                        if($multiple_days != null){
                            if($multiple_days->$day == 0){
                                $getdata1 = OtaRatePlan::select('*')
                                            ->where('room_type_id' , '=' , $data['room_type_id'])
                                            ->where('hotel_id' , '=' , $data['hotel_id'])
                                            ->where('rate_plan_id' , '=' , $data['rate_plan_id'])
                                            ->where('channel',$key)
                                            ->where('from_date' , '<=' , $data['date'])
                                            ->where('to_date' , '>=' , $data['date'])
                                            ->orderBy('rate_plan_log_id', 'desc')
                                            ->skip(1)
                                            ->take(2)
                                            ->get();

                                if(empty($getdata1[0])){
                                    $channelwise_rate[$key][0]='-';
                                    $channelwise_rate[$key][1]='-';
                                    $channelwise_rate[$key][2]=$getdata->block_status;
                                    $channelwise_rate[$key][3]=$getdata->room_type_id;
                                    $channelwise_rate[$key][4]=$getdata->rate_plan_id;
                                    $channelwise_rate[$key][5]=$getdata->extra_adult_price;
                                    $channelwise_rate[$key][6]=$getdata->extra_child_price;
                                    $channelwise_rate[$key][7]=$getdata->from_date;
                                    $channelwise_rate[$key][8]=$getdata->to_date;
                                    $channelwise_rate[$key][9]=$ota_id;
                                }
                                else{
                                    $multiple_days1 = json_decode($getdata1[0]->multiple_days);
                                    if($multiple_days1 != null){
                                        if($multiple_days1->$day == 0){
                                            $getdata2 = DB::connection('bookingjini_cm')->table('ota_rateupdate')->select('*')
                                                    ->where('room_type_id' , '=' , $data['room_type_id'])
                                                    ->where('hotel_id' , '=' , $data['hotel_id'])
                                                    ->where('rate_plan_id' , '=' , $data['rate_plan_id'])
                                                    ->where('channel',$key)
                                                    ->where('from_date' , '<=' , $data['date'])
                                                    ->where('to_date' , '>=' , $data['date'])
                                                    ->orderBy('rate_plan_log_id', 'desc')
                                                    ->skip(2)
                                                    ->take(3)
                                                    ->get();
                                            if(empty($getdata2[0])){
                                                $channelwise_rate[$key][0]='-';
                                                $channelwise_rate[$key][1]='-';
                                                $channelwise_rate[$key][2]=$getdata->block_status;
                                                $channelwise_rate[$key][3]=$getdata->room_type_id;
                                                $channelwise_rate[$key][4]=$getdata->rate_plan_id;
                                                $channelwise_rate[$key][5]=$getdata->extra_adult_price;
                                                $channelwise_rate[$key][6]=$getdata->extra_child_price;
                                                $channelwise_rate[$key][7]=$getdata->from_date;
                                                $channelwise_rate[$key][8]=$getdata->to_date;
                                                $channelwise_rate[$key][9]=$ota_id;
                                            }
                                            else{
                                                $channelwise_rate[$key][0]=$getdata2[0]->bar_price;
                                                $channelwise_rate[$key][1]=json_decode($getdata2[0]->multiple_occupancy);
                                                $channelwise_rate[$key][2]=$getdata2[0]->block_status;
                                                $channelwise_rate[$key][3]=$getdata2[0]->room_type_id;
                                                $channelwise_rate[$key][4]=$getdata2[0]->rate_plan_id;
                                                $channelwise_rate[$key][5]=$getdata2[0]->extra_adult_price;
                                                $channelwise_rate[$key][6]=$getdata2[0]->extra_child_price;
                                                $channelwise_rate[$key][7]=$getdata2[0]->from_date;
                                                $channelwise_rate[$key][8]=$getdata2[0]->to_date;
                                                $channelwise_rate[$key][9]=$ota_id;
                                            }
                                        }
                                        else{
                                            $channelwise_rate[$key][0]=$getdata1[0]->bar_price;
                                            $channelwise_rate[$key][1]=json_decode($getdata1[0]->multiple_occupancy);
                                            $channelwise_rate[$key][2]=$getdata1[0]->block_status;
                                            $channelwise_rate[$key][3]=$getdata1[0]->room_type_id;
                                            $channelwise_rate[$key][4]=$getdata1[0]->rate_plan_id;
                                            $channelwise_rate[$key][5]=$getdata1[0]->extra_adult_price;
                                            $channelwise_rate[$key][6]=$getdata1[0]->extra_child_price;
                                            $channelwise_rate[$key][7]=$getdata1[0]->from_date;
                                            $channelwise_rate[$key][8]=$getdata1[0]->to_date;
                                            $channelwise_rate[$key][9]=$ota_id;
                                        }
                                    }
                                    else{
                                        $channelwise_rate[$key][0]=$getdata1[0]->bar_price;
                                        $channelwise_rate[$key][1]=json_decode($getdata1[0]->multiple_occupancy);
                                        $channelwise_rate[$key][2]=$getdata1[0]->block_status;
                                        $channelwise_rate[$key][3]=$getdata1[0]->room_type_id;
                                        $channelwise_rate[$key][4]=$getdata1[0]->rate_plan_id;
                                        $channelwise_rate[$key][5]=$getdata1[0]->extra_adult_price;
                                        $channelwise_rate[$key][6]=$getdata1[0]->extra_child_price;
                                        $channelwise_rate[$key][7]=$getdata1[0]->from_date;
                                        $channelwise_rate[$key][8]=$getdata1[0]->to_date;
                                        $channelwise_rate[$key][9]=$ota_id;
                                    }
                                }
                            }
                            else{
                                $channelwise_rate[$key][0]=$getdata->bar_price;
                                $channelwise_rate[$key][1]=json_decode($getdata->multiple_occupancy);
                                $channelwise_rate[$key][2]=$getdata->block_status;
                                $channelwise_rate[$key][3]=$getdata->room_type_id;
                                $channelwise_rate[$key][4]=$getdata->rate_plan_id;
                                $channelwise_rate[$key][5]=$getdata->extra_adult_price;
                                $channelwise_rate[$key][6]=$getdata->extra_child_price;
                                $channelwise_rate[$key][7]=$getdata->from_date;
                                $channelwise_rate[$key][8]=$getdata->to_date;
                                $channelwise_rate[$key][9]=$ota_id;
                            }
                        }
                        else{
                            $channelwise_rate[$key][0]=$getdata->bar_price;
                            $channelwise_rate[$key][1]=json_decode($getdata->multiple_occupancy);
                            $channelwise_rate[$key][2]=$getdata->block_status;
                            $channelwise_rate[$key][3]=$getdata->room_type_id;
                            $channelwise_rate[$key][4]=$getdata->rate_plan_id;
                            $channelwise_rate[$key][5]=$getdata->extra_adult_price;
                            $channelwise_rate[$key][6]=$getdata->extra_child_price;
                            $channelwise_rate[$key][7]=$getdata->from_date;
                            $channelwise_rate[$key][8]=$getdata->to_date;
                            $channelwise_rate[$key][9]=$ota_id;

                        }
                    }

                }
                else{
                    $channelwise_rate[$key][0]=0;
                    $channelwise_rate[$key][1]=0;
                    $channelwise_rate[$key][2]=0;
                }
            }

            if(sizeof($channelwise_rate)>0){
                $resp=array('status'=>1,'message'=>"rate details fetched successfully",'data'=>$channelwise_rate);
                return response()->json($resp);
            }
            else{
                $resp=array('status'=>0,'message'=>"-");
                return response()->json($resp);
            }
    }
}
