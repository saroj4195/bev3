<?php
namespace App\Http\Controllers\crs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\RoomRateSettings;
use DB;
use App\Http\Controllers\Controller;
//use App\Http\Controllers\InventoryBucketEngine;
use App\Http\Controllers\IpAddressService;
class RoomRateSettingsController extends Controller
{
    protected $ipService;
    public function __construct(IpAddressService $ipService)
    {
       $this->ipService=$ipService;
    }

    private $rules=array(
        'from_date' => 'required',
        'to_date' => 'required ',
        'hotel_id' => 'required | numeric',
        'room_type_id' => 'required | numeric',
        'rate_plan_id' => 'required | numeric',
        'agent_price' => 'required',
        'corporate_price' => 'required'
    );
    private $messages = [
        'from_date.required' => 'From date is required.',
        'to_date.required' => 'To date is required.',
        'hotel_id.required' => 'Hotel id is required.',
        'room_type_id.required' => 'Room type id is required.',
        'rate_plan_id.required' => 'Rate plan id is required.',
        'user_id.required'  => 'User id is required.',
        'rate_plan_id.numeric' => 'Rate plan id should be numeric.',
        'hotel_id.numeric' => 'Hotel id should be numeric.',
        'from_date.date_format' => 'Date format should be Y-m-d',
        'to_date.date_format' => 'Date format should be Y-m-d',
        'agent_price.required'=>'Agent price should be required',
        'corporate_price.required'=>'corporate price should be required'
    ];

    public function addRoomRate(Request $request)
    {
        $roomratedata=new RoomRateSettings();
        $failure_message="record submitation failed";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>-1,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']=json_encode($data['multiple_occupancy']);
        if($request->auth){
            $data['user_id']=$request->auth->admin_id;
        }else{
            $data['user_id']=0;
        }
        $data['client_ip']=$this->ipService->getIPAddress();
        $restrict=$this->checkStatus($data);
        if($restrict == 'new')
        {
            if($roomratedata->fill($data)->save())
            {
                $res=array('status'=>1,'message'=>'record Submition sucessfully');
                return response()->json($res);
            }
            else{
                $res=array('status'=>0,'message'=>'record Submition failed');
                return response()->json($res);
            }
        }
        else{
            $res=array('status'=>0,'message'=>'The rateplan already exist');
            return response()->json($res);
        }
      
    }
    public function getRoomRate(int $hotel_id,Request $request)
    {
        $roomratedata=RoomRateSettings::join('kernel.room_type_table as room_type_table','roomrate_settings.room_type_id','=','room_type_table.room_type_id')
        ->join('kernel.rate_plan_table as rate_plan_table','roomrate_settings.rate_plan_id','=','rate_plan_table.rate_plan_id')
        ->select('roomrate_settings.*','room_type_table.room_type','rate_plan_table.plan_type','rate_plan_table.plan_name')->where('roomrate_settings.hotel_id',$hotel_id)->get();
        if(sizeof($roomratedata)>0)
        {
            $res=array('status'=>1,'message'=>'record retrived sucessfully','data'=>$roomratedata);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'record retrived failed');
            return response()->json($res);
        }
    }
    public function getRoomRateById(int $room_rateplan_id,Request $request)
    {
        $roomratedata=RoomRateSettings::select('*')->where('room_rateplan_id',$room_rateplan_id)->first();
        $roomratedata->from_date= date('d-m-Y',strtotime($roomratedata->from_date));
        $roomratedata->to_date= date('d-m-Y',strtotime($roomratedata->to_date));
        $roomratedata->multiple_occupancy=json_decode( $roomratedata->multiple_occupancy);
        if(!empty($roomratedata))
        {
            $res=array('status'=>1,'message'=>'record retrived sucessfully','data'=>$roomratedata);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'record retrived failed');
            return response()->json($res);
        }
    }
    public function updateRoomRate(int $room_rateplan_id,Request $request)
    {
        $roomratedata=new RoomRateSettings();
        $failure_message="record submitation failed";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>-1,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
        $data['multiple_occupancy']=json_encode($data['multiple_occupancy']);
        if($request->auth){
            $data['user_id']=$request->auth->admin_id;
        }else{
            $data['user_id']=0;
        }
        $data['client_ip']=$this->ipService->getIPAddress();
        $roomratedata=RoomRateSettings::where('room_rateplan_id',$room_rateplan_id)->update($data);
        if($roomratedata)
        {
            $res=array('status'=>1,'message'=>'record updated sucessfully');
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'record updated failed');
            return response()->json($res);
        }
    }
    public function deleteRoomRate(int $room_rateplan_id,Request $request)
    {
        $roomratedata=RoomRateSettings::where('room_rateplan_id',$room_rateplan_id)->delete();
        if($roomratedata)
        {
            $res=array('status'=>1,'message'=>'record deleted sucessfully');
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'record deleted failed');
            return response()->json($res);
        }
    }
    public function getAllHotels(int $company_id,Request $request)
    {
        $allhotels=DB::table('hotels_table')->select('hotel_id','hotel_name')->where('company_id',$company_id)->get();
        if(sizeof($allhotels)>0)
        {
            $res=array('status'=>1,'message'=>'Fetching all hotels sucessfully','data'=>$allhotels);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'Fetching all hotels sucessfully');
            return response()->json($res);
        }
    }
    public function checkStatus($data)
    {
        $get_status=RoomRateSettings::where('room_type_id',$data['room_type_id'])->where('rate_plan_id',$data['rate_plan_id'])->first();
        if($get_status)
        {
            return "exist";
        }
        else{
            return "new";
        }
    }
}