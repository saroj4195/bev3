<?php
namespace App\Http\Controllers\crs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\MasterRoomType;//class name from model
use App\MasterHotelRatePlan;//class name from model
use App\CrsRatePlanLog;//class name from model
use App\AdminUser;
use App\RoomRateSettings;
use DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InventoryService;
use App\AgentInventory;
use App\Http\Controllers\IpAddressService;
//create a new class ManageInventoryController

class ManageCrsRatePlanController extends Controller
{
    protected $ipService;
    protected $invService;
    public function __construct(InventoryService $invService,IpAddressService $ipService)
    {
        $this->invService = $invService;
        $this->ipService=$ipService;
    }
    //validation rules
    private $rules = array(
    'hotel_id' => 'required',
    'room_type_id' => 'required',
    'date_from' => 'required',
    'date_to' => 'required',
    //'client_ip' => 'required'
    );
    //Custom Error Messages
    private $messages = [
    'hotel_id.required' => 'The hotel id is required.',
    'room_type_id.required' => 'The room type id is required.',
    'date_from.required' => 'The date from is required.',
    'date_to.required' => 'The date to is required.',
    //'client_ip.required' => 'The client ip is required.',
        ];
//Get Room Rates By hotel id
/**
 * Get Room Rates By Hotel id
 * get all record of Room Rates by hotel id
 * @auther Godti Vinod
 * function getRatesByHotel for fetching data
**/
public function getRates(int $user_id,int $hotel_id,string $date_from ,string $date_to)
{
    $roomRateSettings = new RoomRateSettings();
    $room_rate_plan_details = $roomRateSettings->join('kernel.rate_plan_table as a', 'roomrate_settings.rate_plan_id', '=', 'a.rate_plan_id')
        ->join('kernel.room_type_table as b','roomrate_settings.room_type_id', '=', 'b.room_type_id')
        ->select('b.room_type_id','room_type','a.rate_plan_id','plan_type','plan_name')
        ->where('roomrate_settings.hotel_id',$hotel_id)
        ->where('roomrate_settings.is_trash',0)
        ->get();
    if($room_rate_plan_details)
    {
        foreach($room_rate_plan_details as $all_types)
        {
            $data=$this->getRatesByRoomnRatePlan($user_id,$all_types->room_type_id,$all_types->rate_plan_id,$date_from, $date_to);

            $all_types['rates']=$data;
        }
        $res=array('status'=>1,'message'=>"Hotel room rates retrieved successfully ",'data'=>$room_rate_plan_details);
        return response()->json($res);
    }
    else
    {
        $res=array('status'=>0,'message'=>"Hotel room rates retrieval failed");
    }
}
public function getRatesByRoomnRatePlan(int $user_id,int $room_type_id,int $rate_plan_id,string $date_from ,string $date_to)
{
    $filtered_rate=array();
    $date1=date_create($date_from);
    $date2=date_create($date_to);
    $date3=date_create(date('Y-m-d'));
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");
    $crsrateplanlog = new CrsRatePlanLog();
    $roomRateSettings = new RoomRateSettings();
    $user_role=$this->getUserRole($user_id);
for($i=1;$i<=$diff; $i++ )
{
    $d=date('Y-m-d',strtotime($date_from));
    $timestamp = strtotime($d);
    $day = date('D', $timestamp);

    $room_rate_plan_details = $roomRateSettings
                            ->where(['rate_plan_id' => $rate_plan_id])
                            ->where(['room_type_id' => $room_type_id])
                            ->where('is_trash',0)
                            ->where('from_date' , '<=' , $d)
                            ->where('to_date' , '>=' , $d)
                            ->first();
    if(!empty($room_rate_plan_details))//If Room rate plans Not with in date range then Latest created room rate plan is considered
    {
        $rate_plan_details = $roomRateSettings
            ->where(['rate_plan_id' => $rate_plan_id])
            ->where(['room_type_id' => $room_type_id])
            ->where('is_trash',0)
            ->orderBy('created_at', 'desc')
            ->first();
    }
    else
    {
        $rate_plan_details=$room_rate_plan_details;
    }
    //dd($rate_plan_details);
    $bar_price = $user_role===4 ? $rate_plan_details['agent_price'] : $rate_plan_details['corporate_price'];
    $multiple_occupancy = $rate_plan_details['multiple_occupancy'];
    $bookingjini_price = $rate_plan_details['bookingjini_price'];
    $extra_adult_price = $rate_plan_details['extra_adult_price'];
    $extra_child_price = $rate_plan_details['extra_child_price'];
    $before_days_offer = $rate_plan_details['before_days_offer'];
    $stay_duration_offer = $rate_plan_details['stay_duration_offer'];
    $lastminute_offer = $rate_plan_details['lastminute_offer'];
    $rate_plan_log_details = $crsrateplanlog
                            ->select('extra_adult_price','extra_child_price','bar_price','multiple_occupancy','multiple_days')
                            ->where(['room_type_id' => $room_type_id])
                            ->where('rate_plan_id' , '=' , $rate_plan_id)
                            ->where('from_date' , '<=' , $d)
                            ->where('to_date' , '>=' , $d)
                            ->where('for_user_id',$user_id)
                            ->orderBy('crs_rate_plan_log_id', 'desc')
                            ->first();
    if(empty($rate_plan_log_details))
    {
        $array=array(
        'bar_price'=>$bar_price ,
        'multiple_occupancy'=>json_decode($multiple_occupancy),
        'bookingjini_price' => $bookingjini_price,
        'extra_adult_price' => $extra_adult_price,
        'extra_child_price' => $extra_child_price,
        'before_days_offer' => $before_days_offer,
        'stay_duration_offer' => $stay_duration_offer,
        'lastminute_offer' => $lastminute_offer,
        'rate_plan_id'=>$rate_plan_id,
        'room_type_id'=>$room_type_id,
        'date'=>$date_from,
        'day'=>$day
    );
        array_push($filtered_rate,$array);
    }
    else
    {
    $multiple_days=json_decode($rate_plan_log_details->multiple_days);
    if($rate_plan_log_details->extra_adult_price)
    {
        $extra_adult_price=$rate_plan_log_details->extra_adult_price;
    }
    if($rate_plan_log_details->extra_child_price)
    {
        $extra_child_price=$rate_plan_log_details->extra_child_price;
    }
    if($multiple_days!=null)
    {
    if($multiple_days->$day==0)
    {
        $rate_plan_log_details1 = $crsrateplanlog
                                ->select('bar_price','multiple_occupancy', 'multiple_days')
                                ->where(['room_type_id' => $room_type_id])
                                ->where('rate_plan_id' , '=' , $rate_plan_id)
                                ->where('from_date' , '<=' , $d)
                                ->where('to_date' , '>=' , $d)
                                ->where('for_user_id',$user_id)
                                ->orderBy('crs_rate_plan_log_id', 'desc')
                                ->skip(1)
                                ->take(2)
                                ->get();
        if(empty($rate_plan_log_details1[0]))
        {
                $array=array(
                'bar_price'=>$bar_price ,
                'multiple_occupancy'=>json_decode($multiple_occupancy),
                'bookingjini_price' => $bookingjini_price,
                'extra_adult_price' => $extra_adult_price,
                'extra_child_price' => $extra_child_price,
                'before_days_offer' => $before_days_offer,
                'stay_duration_offer' => $stay_duration_offer,
                'lastminute_offer' => $lastminute_offer,
                'rate_plan_id'=>$rate_plan_id,
                'room_type_id'=>$room_type_id,
                'date'=>$date_from,
                'day'=>$day
                );
        }
        else
        {

            $multiple_days1=json_decode($rate_plan_log_details1[0]->multiple_days);
           if($multiple_days1!=null)
           {
            if($multiple_days1->$day==0)
            {
                $rate_plan_log_details2 = $crsrateplanlog
                                        ->select('bar_price','multiple_occupancy')
                                        ->where(['room_type_id' => $room_type_id])
                                        ->where('rate_plan_id' , '=' , $rate_plan_id)
                                        ->where('from_date' , '<=' , $d)
                                        ->where('to_date' , '>=' , $d)
                                        ->where('for_user_id',$user_id)
                                        ->orderBy('crs_rate_plan_log_id', 'desc')
                                        ->skip(2)
                                        ->take(3)
                                        ->get();
                if(empty($rate_plan_log_details2[0]))
                {
                        $array=array(
                        'bar_price'=>$bar_price ,
                        'multiple_occupancy'=>json_decode($multiple_occupancy),
                        'bookingjini_price' => $bookingjini_price,
                        'extra_adult_price' => $extra_adult_price,
                        'extra_child_price' => $extra_child_price,
                        'before_days_offer' => $before_days_offer,
                        'stay_duration_offer' => $stay_duration_offer,
                        'lastminute_offer' => $lastminute_offer,
                        'rate_plan_id'=>$rate_plan_id,
                        'room_type_id'=>$room_type_id,
                        'date'=>$date_from,
                        'day'=>$day
                        );
                }
                else
                {
                         $array=array(
                        'bar_price'=>$rate_plan_log_details2[0]->bar_price,
                        'multiple_occupancy'=>json_decode($rate_plan_log_details2[0]->multiple_occupancy),
                        'bookingjini_price' => $bookingjini_price,
                        'extra_adult_price' => $extra_adult_price,
                        'extra_child_price' => $extra_child_price,
                        'before_days_offer' => $before_days_offer,
                        'stay_duration_offer' => $stay_duration_offer,
                        'lastminute_offer' => $lastminute_offer,
                        'rate_plan_id'=>$rate_plan_id,
                        'room_type_id'=>$room_type_id,
                        'date'=>$date_from,
                        'day'=>$day
                    );
                }
            }
            else
            {
                 $array=array(
                'bar_price'=>$rate_plan_log_details1[0]->bar_price,
                'multiple_occupancy'=>json_decode($rate_plan_log_details1[0]->multiple_occupancy),
                'bookingjini_price' => $bookingjini_price,
                'extra_adult_price' => $extra_adult_price,
                'extra_child_price' => $extra_child_price,
                'before_days_offer' => $before_days_offer,
                'stay_duration_offer' => $stay_duration_offer,
                'lastminute_offer' => $lastminute_offer,
                'rate_plan_id'=>$rate_plan_id,
                'room_type_id'=>$room_type_id,
                'date'=>$date_from,
                'day'=>$day
            );
           }
           }
            else
            {
                $array=array(
                    'bar_price'=>$rate_plan_log_details['bar_price'],
                    'multiple_occupancy'=>json_decode($rate_plan_log_details['multiple_occupancy']),
                    'bookingjini_price' => $bookingjini_price,
                    'extra_adult_price' => $extra_adult_price,
                    'extra_child_price' => $extra_child_price,
                    'before_days_offer' => $before_days_offer,
                    'stay_duration_offer' => $stay_duration_offer,
                    'lastminute_offer' => $lastminute_offer,
                    'rate_plan_id'=>$rate_plan_id,
                    'room_type_id'=>$room_type_id,
                    'date'=>$date_from,
                    'day'=>$day
                );
            }
           
        }
    }
    else
    {
        $array=array(
            'bar_price'=>$rate_plan_log_details['bar_price'],
            'multiple_occupancy'=>json_decode($rate_plan_log_details['multiple_occupancy']),
            'bookingjini_price' => $bookingjini_price,
            'extra_adult_price' => $extra_adult_price,
            'extra_child_price' => $extra_child_price,
            'before_days_offer' => $before_days_offer,
            'stay_duration_offer' => $stay_duration_offer,
            'lastminute_offer' => $lastminute_offer,
            'rate_plan_id'=>$rate_plan_id,
            'room_type_id'=>$room_type_id,
            'date'=>$date_from,
            'day'=>$day
        );
    }

}
    else
    {
        $array=array(
            'bar_price'=>$rate_plan_log_details['bar_price'],
            'multiple_occupancy'=>json_decode($rate_plan_log_details['multiple_occupancy']),
            'bookingjini_price' => $bookingjini_price,
            'extra_adult_price' => $extra_adult_price,
            'extra_child_price' => $extra_child_price,
            'before_days_offer' => $before_days_offer,
            'stay_duration_offer' => $stay_duration_offer,
            'lastminute_offer' => $lastminute_offer,
            'rate_plan_id'=>$rate_plan_id,
            'room_type_id'=>$room_type_id,
            'date'=>$date_from,
            'day'=>$day
        );
    }
    array_push($filtered_rate,$array);
    }

        $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
}
return $filtered_rate;
}
    /**
     * Agent wise unblock inventry.
     * Create a new record of block inventry.
     * @author Godti Vinod
     * @return Block inventory unblock saving status
     * function addnew for createing a new unblock inventry.
    **/
    public function unBlockInventoryByAgent(Request $request)
    {
        //dd("sadas");
        //$inventory = new Inventory();
        $failure_message='Un block inventry operation failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $room_types=$request->input('room_type_id');
        //dd($room_types);
        $data=$request->all();
        $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
        $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
        $data['user_id']=$request->auth->admin_id;
        $user_id=$data['user_id'];
        $agents=$request->input('agent_id');
        $client_ip=$this->ipService->getIPAddress();
        $count=0;
        foreach($agents as $agent){
            foreach($room_types as $room_type_id)
            {
                $inventory = new AgentInventory();
                $data['room_type_id']=$room_type_id;
                $data['agent_id']=$agent;
                if($inventory->fill($data)->save())
                    {   $inventorId = $inventory->inventory_id;
                        $hotel_id = $inventory->hotel_id;
                        $count++;
                    }
            }
        }
        if($count==sizeof($room_types))
        {
            $res=array('status'=>1,"message"=>"Inventry unblocked successfully");
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }

    }
    /**
     * Agent wise block inventry.
     * Create a new record of block inventry.
     * @author Godti Vinod
     * @return Block inventory saving status
     * function addnew for createing a new CM ota rblock inventry.
    **/
    public function blockInventoryByAgent(Request $request)
    {
        //$inventory = new Inventory();
        $failure_message='Block inventry operation failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $room_types=$request->input('room_type_id');
        $agents=$request->input('agent_id');
        $data=$request->all();
        $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
        $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
        $data['user_id']=$request->auth->admin_id;
        $user_id=$data['user_id'];
        $client_ip=$this->ipService->getIPAddress();
        $count=0;
        foreach($agents as $agent){
            foreach($room_types as $room_type_id)
            {
                $inventory = new AgentInventory();
                $data['room_type_id']=$room_type_id;
                $data['agent_id']=$agent;
                if($inventory->fill($data)->save())
                    {   $inventorId = $inventory->inventory_id;
                        $hotel_id = $inventory->hotel_id;
                        $count++;
                    }
            }
        }

        if($count==sizeof($room_types) * sizeof($agents))
        {
            $res=array('status'=>1,"message"=>"Inventry blocked successfully");
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }

    }

/**
 * Agent wise inventory fetch
 * Method to fetch agent wise inventory
 * @params hotel id ,Room type id,check in date,check out date
 * @return inventory counts
 * @author Godti Vinod
*/
    public function getInventeryByRoomType($user_id,$room_type_id,$date_from,$date_to)
    {
        $inventory_arr=array();
        $agent_block_arr=array();
    //get the public inventory
        $inventory_arr=$this->invService->getInventeryByRoomTYpe($room_type_id,$date_from, $date_to, 0);
    //Check for the user role
        $user=DB::connection('bookingjini_kernel')->table('admin_table')->where('admin_id',$user_id)->select('role_id')->first();
    //Check the block inventory status for the agent user
        if($user->role_id==4)//agent role id
        {

            $agent_block_arr=$this->getInventeryAgentByRoomType($user_id,$room_type_id,$date_from, $date_to, 0);
            //Update the user inventory block status to exsting public inventory

            if(sizeof($inventory_arr)==sizeof($agent_block_arr))
            {
                foreach($inventory_arr as $key=>$inv)
                {

                    $inventory_arr[$key]['block_status']=$agent_block_arr[$key]['block_status'];

                }
            }

        }
    //Return inventory
        return $inventory_arr;
    }
    /**
     * Get Inventory Block status by agent
     * @auther Godti Vinod
    **/
    public function getInventeryAgentByRoomType(int $agent_id,int $room_type_id ,string $date_from ,string $date_to,int $mindays)
    {
        $filtered_inventory=array();
        $inventoryAgent=new AgentInventory();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");
        $diff1=date_diff($date1,$date3);
        $diff1=$diff1->format("%a");
        if($diff1<=$mindays && $mindays!=0)
        {   $d=$date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);
            $array=array('block_status'=>1,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
            array_push($filtered_inventory,$array);
        }
        else
        {
        for($i=1;$i<=$diff; $i++ )
        {
            $d=$date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);
            $inventory_details= $inventoryAgent
                                ->where('agent_id' , '=' , $agent_id)
                                ->where('room_type_id' , '=' , $room_type_id)
                                ->where('date_from' , '<=' , $d)
                                ->where('date_to' , '>=' , $d)
                                ->orderBy('agent_inventory_id', 'desc')
                                ->first();
             if(empty($inventory_details))
             {

                    $array=array(
                    'block_status'=>0,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                    array_push($filtered_inventory,$array);

             }
             else
             {
                $block_status           = trim($inventory_details->block_status);
                $los                    = trim($inventory_details->los);
                if($block_status==1)
                {

                    $array=array('block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                    array_push($filtered_inventory,$array);

                }
                else
                {
                    $array=array('block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                    array_push($filtered_inventory,$array);
                }
            }
             $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
        }
    }
        return $filtered_inventory;
    }

    public function getUserRole($user_id){
        $role=DB::connection('bookingjini_kernel')->table('admin_table')->where('admin_id',$user_id)->select('role_id')->first();
        return $role->role_id;
    }
}