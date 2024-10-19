<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\LogTable;
use App\LoginLog;
use App\Http\Controllers\Extranetv4\InventoryService;
use App\RateUpdateLog;
use App\BookingLog;
use App\HotelInformation;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
class LogsController extends Controller
{
protected $inventoryService;
public function __construct(InventoryService $inventoryService)
{
$this->inventoryService = $inventoryService;
}
/**
* Lists inventory details.
* @return details
* @auther ranjit
*/
public function inventoryDetails(int $hotel_id,string $from_date,string $to_date,int $room_type_id,int $selected_be_ota_id,Request $request)
{
        $from_date=date("Y-m-d", strtotime($from_date));
        $to_date=date("Y-m-d", strtotime($to_date));
        $empty = "''";
        if($selected_be_ota_id === 0){
                $inventory_info=LogTable::join('booking_engine.inventory_table','log_table.inventory_ref_id','=','inventory_table.inventory_id')
                ->leftJoin('kernel.admin_table','log_table.user_id','=','admin_table.admin_id')
                ->leftJoin('kernel.super_admin','log_table.user_id','=','super_admin.intranet_id')
                ->join('kernel.room_type_table','inventory_table.room_type_id','=','room_type_table.room_type_id')
                ->select(DB::raw('CASE WHEN name != '.$empty.' then name else first_name end as first_name'),
                DB::raw('CASE WHEN name != '.$empty.' then '.$empty.' else last_name end as last_name'),'log_table.updated_at','log_table.status','log_table.user_id','inventory_table.date_from','inventory_table.date_to','inventory_table.no_of_rooms',
                'inventory_table.los','room_type_table.room_type')
                ->whereDate('log_table.created_at','>=',$from_date)
                ->whereDate('log_table.created_at','<=',$to_date)
                ->where('inventory_table.room_type_id',$room_type_id)
                ->where("log_table.hotel_id",$hotel_id)
                ->where('log_table.ota_id', 0)
                ->where('log_table.inventory_ref_id','>', 0)
                ->orderBy('log_table.id','SORT_DESC')
                ->get();
                // ->paginate(25);
        }
        else{
                $res=array('status'=>0,'message'=>'Please Provide Be details');
                return response()->json($res);
        }
        if(sizeof($inventory_info)<=0){
                $res=array('status'=>0,'message'=>'details retrive fails');
                return response()->json($res);
        }
        $res=array('status'=>1,'message'=>'details retrive sucessfully','data'=> $inventory_info);
        return response()->json($res);
    }
/**
* Lists Rateplan Log models.
* @return mixed
* @auther ranjit
*/
public function rateplanDetails(int $hotel_id,string $from_date,string $to_date,int $rate_plan_id,int $selected_be_ota_id,int $room_type_id,Request $request)
{
    
        $from_date=date("Y-m-d", strtotime($from_date));
        $to_date=date("Y-m-d", strtotime($to_date));
        $empty = "''";
        if($selected_be_ota_id === 0){
                $rate_plan=RateUpdateLog::join('booking_engine.rate_plan_log_table','rate_update_logs.rate_ref_id','=','rate_plan_log_table.rate_plan_log_id')
                ->leftJoin('kernel.admin_table','rate_update_logs.user_id','=','admin_table.admin_id')
                ->leftJoin('kernel.super_admin','rate_update_logs.user_id','=','super_admin.intranet_id')
                ->join('kernel.rate_plan_table','rate_plan_log_table.rate_plan_id','=','rate_plan_table.rate_plan_id')
                ->join('kernel.room_type_table','rate_plan_log_table.room_type_id','=','room_type_table.room_type_id')
                ->select(DB::raw('CASE WHEN name != '.$empty.' then name else first_name end as first_name'),
                DB::raw('CASE WHEN name != '.$empty.' then '.$empty.' else last_name end as last_name'),'rate_update_logs.updated_at','rate_update_logs.status','rate_update_logs.user_id','rate_plan_log_table.from_date','rate_plan_log_table.to_date','rate_plan_log_table.bar_price','rate_plan_log_table.multiple_occupancy','room_type_table.room_type','rate_plan_table.plan_type','rate_plan_log_table.rate_plan_id')
                ->whereDate('rate_update_logs.created_at','>=',$from_date)
                ->whereDate('rate_update_logs.created_at','<=',$to_date)
                ->where('rate_plan_log_table.rate_plan_id',$rate_plan_id)
                ->where('rate_plan_log_table.room_type_id',$room_type_id)
                ->where("rate_update_logs.hotel_id",$hotel_id)
                ->where('rate_update_logs.rate_ref_id','>', 0)
                ->orderBy('rate_update_logs.id','SORT_DESC')
                ->where('rate_update_logs.ota_id', 0)
                ->get();
                // ->paginate(25);
        }
        
        foreach($rate_plan as $plan)
        {
                //$data=explode(',',$plan->multiple_occupancy);
                $data= json_decode($plan->multiple_occupancy);
                if($data[0] ==  null || $data[0] == ''){
                        $data[0]=0;
                }
                $plan->multiple_occupancy=$data[0];
                if(!$plan->first_name){
                        $plan->first_name="Administrator";
                }
        }
        if(sizeof($rate_plan)<=0)
        {
                $res=array('status'=>0,'message'=>'details retrive fails', 'data'=>$rate_plan);
                return response()->json($res);
        }
        $res=array('status'=>1,'message'=>'details retrive sucessfully','data'=>$rate_plan);
        return response()->json($res);
}
/**
* Lists Bookings Log models.
     * @return mixed
* @auther ranjit
     */
public function bookingDetails(int $hotel_id,string $from_date,string $to_date,Request $request)
    {
$from_date=date("Y-m-d", strtotime($from_date));
        $to_date=date("Y-m-d", strtotime($to_date));
$logModel          = new BookingLog();
        $booking_details   = $logModel
->join('cm_ota_booking','booking_logs.booking_ref_id','=','cm_ota_booking.id')
                                ->join('cm_ota_booking_push_bucket','cm_ota_booking.id','=','cm_ota_booking_push_bucket.ota_booking_tabel_id')
->select('booking_logs.status','cm_ota_booking_push_bucket.ota_booking_tabel_id','cm_ota_booking_push_bucket.ota_name','cm_ota_booking_push_bucket.push_by','cm_ota_booking.booking_status','cm_ota_booking.ota_id','cm_ota_booking.rate_code','cm_ota_booking.rooms_qty','cm_ota_booking.room_type','cm_ota_booking.checkin_at','cm_ota_booking.checkout_at','cm_ota_booking.booking_date')
                                ->whereDate('booking_logs.created_at','>=',$from_date)
->whereDate('booking_logs.created_at','<=',$to_date)
                                ->where("booking_logs.hotel_id",$hotel_id)
->Where('booking_logs.booking_ref_id','>', 0)
                                ->orderBy('booking_logs.id','SORT_DESC')
->paginate(25);

if(sizeof($booking_details) === 0 ){
            $booking_details   = LogTable::
join('cm_ota_booking','log_table.booking_ref_id','=','cm_ota_booking.id')
            //->join('cm_ota_booking_push_bucket','cm_ota_booking.id','=','cm_ota_booking_push_bucket.ota_booking_tabel_id')
//->select('log_table.status','cm_ota_booking_push_bucket.ota_booking_tabel_id','cm_ota_booking_push_bucket.ota_name','cm_ota_booking_push_bucket.push_by','cm_ota_booking.booking_status','cm_ota_booking.ota_id','cm_ota_booking.rate_code','cm_ota_booking.rooms_qty','cm_ota_booking.room_type','cm_ota_booking.checkin_at','cm_ota_booking.checkout_at','cm_ota_booking.booking_date')
            ->whereDate('log_table.created_at','>=',$from_date)
->whereDate('log_table.created_at','<=',$to_date)
            ->where("log_table.hotel_id",$hotel_id)
->Where('log_table.booking_ref_id','>', 0)
            ->orderBy('log_table.id','SORT_DESC')
->paginate(25);
        }
foreach($booking_details as $booking)
        {
$booking->rate_code=$this->getRate_plan($booking->room_type,$booking->ota_id,$booking->rate_code);
            $booking->room_type=$this->getRoom_types($booking->room_type,$booking->ota_id);
}
        if(sizeof($booking_details)<=0)
{
            $res=array('status'=>0,'message'=>'details retrive fails');
return response()->json($res);
        }
$res=array('status'=>1,'message'=>'details retrive sucessfully','data'=>$booking_details);
        return response()->json($res);
}
    /**
* returning room details
     * @auther ranjit
*/
    public function getRoom_types($room_type,$ota_id)
{
      $cmOtaRoomTypeSynchronize= new CmOtaRoomTypeSynchronize();
$room_types=explode(',',$room_type);
      $hotel_room_type=array();
foreach($room_types as $ota_room_type)
      {
array_push($hotel_room_type,$cmOtaRoomTypeSynchronize->getRoomType($ota_room_type,$ota_id));
      }
return implode(',',$hotel_room_type);
    }
/**
     * returning rate plan details
* @auther ranjit
     */
public function getRate_plan($ota_room_type,$ota_id,$rate_plan_id)
    {
$cmOtaRatePlanSynchronize= new CmOtaRatePlanSynchronize();
      $rate_plan_ids=explode(',',$rate_plan_id);
$hotel_rate_plan=array();
      foreach($rate_plan_ids as $ota_rate_plan_id)
{

array_push($hotel_rate_plan,$cmOtaRatePlanSynchronize->getRoomRatePlan($ota_id,$ota_rate_plan_id));
      }
return implode(',',$hotel_rate_plan);
    }
/**
* Lists user Session Log models.
* @return mixed
* @auther ranjit
*/
public function userSession(int $hotel_id,string $from_date,string $to_date,Request $request)
{
$from_date=date("Y-m-d", strtotime($from_date));
$to_date=date("Y-m-d", strtotime($to_date));
$manageHotelModel  = new HotelInformation();
$manageHotelDetails= $manageHotelModel->select('company_id')->where("hotel_id",$hotel_id)->first();
$company_id        = $manageHotelDetails->company_id;
$loginLogModel     = new LoginLog();
$session_info      = $loginLogModel->join('kernel.admin_table','login_log.user_id','=','admin_table.admin_id')
->select('admin_table.first_name','admin_table.last_name','login_log.ip_address','login_log.browser','login_log.login_date')
->whereDate('login_log.login_date','>=',$from_date)
->whereDate('login_log.login_date','<=',$to_date)
->where('login_log.company_id',$company_id)
->orderBy('login_log_id','SORT_DESC')
->get();
if(sizeof($session_info)<=0 )
{
$res=array('status'=>0,'message'=>'details retrive fails');
return response()->json($res);
}
$res=array('status'=>1,'message'=>'details retrive sucessfully','data'=>$session_info);
return response()->json($res);
}
/**
* rate plan details
* @auther ranjit
*/
public function ratePlanInfo(int $hotel_id,Request $request)
{
$rateplan=DB::table('kernel.rate_plan_table')->select('plan_type','rate_plan_id')->where('hotel_id',$hotel_id)->get();
if(sizeof($rateplan)<=0 )
{
$res=array('status'=>0,'message'=>'details retrive fails');
return response()->json($res);
}
$res=array('status'=>1,'message'=>'details retrive sucessfully','data'=>$rateplan);
return response()->json($res);
}
}
