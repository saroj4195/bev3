<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\CrsRatePlanLog;
use App\Http\Controllers\IpAddressService;
use DB;
//use App\Http\Controllers\InventoryBucketEngine;
class CrsRoomRatePlanController extends Controller
{
protected $ipService;
public function __construct(IpAddressService $ipService)
{
$this->ipService=$ipService;
}
private $rules = array(
'from_date' => 'required',
        'to_date' => 'required ',
'hotel_id' => 'required | numeric',
        'room_type_id' => 'required | numeric',
'rate_plan_id' => 'required | numeric',
        'bar_price'    => 'required | numeric',
//'multiple_occupancy' => 'required',
        'block_status'       => 'required',
'for_user_id'       => 'required'

);
    private $inline_rate_update_rules = array(
'hotel_id' => 'required | numeric',
        'for_user_id'       => 'required'
);
//Custom Error Messages
private $messages = [
'date_from.required' => 'From date is required.',
'date_to.required' => 'To date is required.',
'hotel_id.required' => 'Hotel id is required.',
'room_type_id.required' => 'Room type id is required.',
'rate_plan_id.required' => 'Rate plan id is required.',
'bar_price.required'   => 'Bar price is required.',
'multiple_occupancy.required'   => 'Multiple occupency rates required.',
'block_status.required' => 'Block status is required.',
'user_id.required'  => 'User id is required.',
'rate_plan_id.numeric' => 'Rate plan id should be numeric.',
'bar_price.numeric' => 'Bar price should be numeric.',
'hotel_id.numeric' => 'Hotel id should be numeric.',
'from_date.date_format' => 'Date format should be Y-m-d',
'to_date.date_format' => 'Date format should be Y-m-d',
'for_user_id.required' => 'Corporate or agent User required'
];
/**
* Room Rate update
* Update the rate ofrooms in rate_plan_log_table
* @author Godti Vinod
* @return Update staus
**/
public function roomRateUpdate(Request $request)
{
$rate_plan_log = new CrsRatePlanLog();
$ota_data=array();
$failure_message='Room Rates updation failed';
$validator = Validator::make($request->all(),$this->rules,$this->messages);
if ($validator->fails())
{
return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
}
$data=$request->all();
$data['from_date']=date('Y-m-d',strtotime($data['from_date']));
$data['to_date']=date('Y-m-d',strtotime($data['to_date']));
$data['multiple_occupancy']=json_encode($data['multiple_occupancy']);
$data['multiple_days']=json_encode($data['multiple_days']);
$data['user_id']=$request->auth->admin_id;
$user_id=$data['user_id'];
$rate_ota_data=array();
if($rate_plan_log->fill($data)->save())
{
$res=array('status'=>1,"message"=>"Special room rates updated successfully","data_status"=>$rate_ota_data);
return response()->json($res);
}
else
{
$res=array('status'=>-1,"message"=>$failure_message);
$res['errors'][] = "Internal server error";
return response()->json($res);
}
}
//Update Rates to CRS  
public function inlineUpdateRates(Request $request)
{
$failure_message="Inline rate update failed";
$validator = Validator::make($request->all(),$this->inline_rate_update_rules,$this->messages);
if ($validator->fails())
{
return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
}
$multiple_days='{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
$data=$request->all();
$user_id=$request->auth->admin_id;
$result_arr=array();
$count=0;
foreach($data['rates'] as $rates)
{
foreach($rates['rates'] as $rate)
{
$ratedata=array();
$ratedata['room_type_id']=$rates['room_type_id'];
$ratedata['rate_plan_id']=$rates['rate_plan_id'];
$ratedata['from_date']=date('Y-m-d',strtotime($rate['date']));
$ratedata['to_date']=date('Y-m-d',strtotime($rate['date']));
$ratedata['multiple_occupancy']=json_encode($rate['multiple_occupancy']);
$ratedata['bar_price']=$rate['bar_price'];
$ratedata['extra_adult_price']=0;
$ratedata['extra_child_price']=0;
$ratedata['multiple_days']=$multiple_days;
$ratedata['user_id']=$request->auth->admin_id;
$ratedata['client_ip']=$this->ipService->getIPAddress();
$ratedata['hotel_id']=$data['hotel_id'];
$ratedata['for_user_id']=$data['for_user_id'];
$crs_rate_plan_log=new CrsRatePlanLog();
if($crs_rate_plan_log->fill($ratedata)->save())
{
$count++;
}
}
}
if($count > 0){
$res=array('status'=>1,"message"=>"Hotel room rates updated successfully");
return response()->json($res);
}else{
$res=array('status'=>0,"message"=>"Rate update not successful");
return response()->json($res); 
}
}
}