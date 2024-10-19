<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Inventory;//class name from model
use App\MasterRoomType;//class name from model
use App\MasterHotelRatePlan;//class name from model
use App\RatePlanLog;//class name from model
use DB;
use App\Http\Controllers\InventoryService;
//create a new class ManageInventoryController

class ManageInventoryController extends Controller
{
    protected $invService;
    public function __construct(InventoryService $invService)
    {
       $this->invService = $invService;
    }

    public function getInventery(int $room_type_id ,string $date_from ,string $date_to,int $mindays,Request $request)
    {
        $date_from=date('Y-m-d',strtotime($date_from));
        $date_to=date('Y-m-d',strtotime($date_to));
        $data=$this->invService->getInventeryByRoomTYpe($room_type_id,$date_from,$date_to,$mindays);
        if($data)
        {
            $res=array('status'=>1,"message"=>"Inventory retrieved successfully","data"=>$data) ;
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>0,"message"=>"Inventory not found") ;
            return response()->json($res);
        }

    }
public function getRates(int $room_type_id,int $rate_plan_id,string $date_from ,string $date_to,Request $request)
{
    $data=$this->invService->getRatesByRoomnRatePlan($room_type_id,$rate_plan_id,$date_from,$date_to);
    if($data)
    {
        $res=array('status'=>1,"message"=>"Inventory retrieved successfully","data"=>$data) ;
        return response()->json($res);
    }
    else
    {
        $res=array('status'=>0,"message"=>"Inventory not found") ;
        return response()->json($res);
    }

}

//Get Inventory
/**
 * Get Inventory By Hotel id
 * get all record of Inventory by hotel id
 * @auther Godti Vinod
 * function getInvByHotel for fetching data
**/
public function getInvByHotel(int $hotel_id ,string $date_from ,string $date_to,int $mindays,Request $request)
{
    $date_from=date('Y-m-d',strtotime($date_from));
    $date_to=date('Y-m-d',strtotime($date_to));
    $roomType=new MasterRoomType();
    $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
    $from = strtotime($date_from);
    $to = strtotime($date_to);
    $dif_dates=array();
    $date1=date_create($date_from);
    $date2=date_create($date_to);
    $diff=date_diff($date1,$date2);
    $diff=$diff->format("%a");
    $j=0;
    $k=0;
    for ($i=$from; $i<=$to; $i+=86400) {
        $dif_dates[$j]= date("Y-m-d", $i);
        $j++;
    }
    $room_types=MasterRoomType::select('room_type','room_type_id','total_rooms')->where($conditions)->orderBy('room_type_table.room_type_id','ASC')->get();
    if($room_types)
    {
        foreach($room_types as $room)
        {
            $data=$this->invService->getInventeryByRoomTYpe($room['room_type_id'],$date_from, $date_to, $mindays);
            $room['inv']=$data;
            $data2=$this->invService->getBookingByRoomtype($hotel_id,$room['room_type_id'],$date_from,$date_to);
            $room['bookings']=$data2;
        }
        for($i=0;$i<$diff;$i++)
        {
            $sum=0;
            foreach($room_types as $room)
            {
                if($room['inv'][$i]['date']==$dif_dates[$i] && $room['inv'][$i]['block_status']==0)
                {
                    $sum+=$room['inv'][$i]['no_of_rooms'];
                }
            }
            $count[$k]=$sum;
            $k++;
        }
        $res=array('status'=>1,'message'=>"Hotel inventory retrieved successfully ",'data'=>$room_types,'count'=>$count);
        return response()->json($res);
    }
    else
    {
        $res=array('status'=>1,'message'=>"Hotel inventory retrieval failed");
    }
}
//Get Room Rates By hotel id
/**
 * Get Room Rates By Hotel id
 * get all record of Room Rates by hotel id
 * @auther Godti Vinod
 * function getRatesByHotel for fetching data
**/
public function getRatesByHotel(int $hotel_id ,string $date_from ,string $date_to,Request $request)
{
    $date_from=date('Y-m-d',strtotime($date_from));
    $date_to=date('Y-m-d',strtotime($date_to));
    $roomType=new MasterRoomType();
    $rate_plans=new MasterHotelRatePlan();
    $room_type_n_rate_plans=$rate_plans->
         join('rate_plan_table as a', 'room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id')
        ->join('room_type_table as b','room_rate_plan.room_type_id', '=', 'b.room_type_id')
        ->select('b.room_type_id','room_type','a.rate_plan_id','plan_type','plan_name')
        ->where('room_rate_plan.hotel_id',$hotel_id)->where('room_rate_plan.is_trash',0)
        ->groupBy('b.room_type_id','a.rate_plan_id')
        ->distinct()
        ->get();
    if($room_type_n_rate_plans){
        foreach($room_type_n_rate_plans as $all_types){
            if($all_types->rate_plan_id){
                $data=$this->invService->getRatesByRoomnRatePlan($all_types->room_type_id,$all_types->rate_plan_id,$date_from, $date_to);
                $all_types['rates']=$data;
            }
            else{
                unset($all_types);
            }
        }
        $res=array('status'=>1,'message'=>"Hotel room rates retrieved successfully ",'data'=>$room_type_n_rate_plans);
        return response()->json($res);
    }
    else{
        $res=array('status'=>1,'message'=>"Hotel room rates retrieval failed");
    }
}
public function getRatesByRoomType(int $hotel_id ,string $date_from ,string $date_to,$room_type_id,Request $request)
{
    $date_from=date('Y-m-d',strtotime($date_from));
    $date_to=date('Y-m-d',strtotime($date_to));
    $roomType=new MasterRoomType();
    $rate_plans=new MasterHotelRatePlan();
    $room_type_n_rate_plans=$rate_plans->
         join('rate_plan_table as a', 'room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id')
        ->join('room_type_table as b','room_rate_plan.room_type_id', '=', 'b.room_type_id')
        ->select('b.room_type_id','room_type','a.rate_plan_id','plan_type','plan_name')
        ->where('room_rate_plan.hotel_id',$hotel_id)->where('room_rate_plan.room_type_id',$room_type_id)
        ->where('room_rate_plan.is_trash',0)
        ->groupBy('b.room_type_id','a.rate_plan_id')
        ->distinct()
        ->get();
    if($room_type_n_rate_plans){
        foreach($room_type_n_rate_plans as $all_types){
            if($all_types->rate_plan_id){
                $data=$this->invService->getRatesByRoomnRatePlan($all_types->room_type_id,$all_types->rate_plan_id,$date_from, $date_to);
                $all_types['rates']=$data;
            }
            else{
                unset($all_types);
            }
        }
        $res=array('status'=>1,'message'=>"Hotel room rates retrieved successfully ",'data'=>$room_type_n_rate_plans);
        return response()->json($res);
    }
    else{
        $res=array('status'=>1,'message'=>"Hotel room rates retrieval failed");
    }
}
public function getRoomTypesAndRatePlans(int $hotel_id,Request $request)
{
   
    $roomType=new MasterRoomType();
    $rate_plans=new MasterHotelRatePlan();

    $all_room_types = $roomType->select('room_type_id','room_type')->where('hotel_id',$hotel_id)->where('is_trash',0)
    ->orderBy('room_type_id','ASC')
    ->get();

        if($all_room_types){
            foreach($all_room_types as $room_type){
                $rate_plan_array=array();
                $room_type_n_rate_plans=$rate_plans->
                join('rate_plan_table as a', 'room_rate_plan.rate_plan_id', '=', 'a.rate_plan_id')
                ->select('a.rate_plan_id','plan_type','plan_name')
                ->where('room_rate_plan.hotel_id',$hotel_id)->where('room_rate_plan.room_type_id',$room_type->room_type_id)->where('room_rate_plan.is_trash',0)
                ->orderBy('room_rate_plan.room_rate_plan_id','ASC')
                ->get();

                if($room_type_n_rate_plans){
                    $room_type->rate_plans=$room_type_n_rate_plans;
                }
                else{
                    $room_type->rate_plans=[];
                }

            }
            
            $res=array('status'=>1,'message'=>"Room Types and Rate Plans retrieved successfully ",'data'=>$all_room_types);
            return response()->json($res);
        }
        else{
            $res=array('status'=>1,'message'=>"Room Types and Rate Plans retrieval failed");
        }
    }
    public function getInventeryByRoomtype($hotel_id,$room_type_id){
        $get_room_details = MasterRoomType::select('total_rooms')->where([['hotel_id',$hotel_id],['room_type_id',$room_type_id]])->first();
        if($get_room_details){
            $resp = array('status'=>1,"message"=>"Total number of room fetch successfully","data"=>$get_room_details->total_rooms);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0,"message"=>"Total number of room fetch fails");
            return response()->json($resp);
        }
    }

}
