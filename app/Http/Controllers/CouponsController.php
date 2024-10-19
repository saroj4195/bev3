<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Coupons;//class name from model
use App\UsedCoupon;
use App\MasterRoomType;
use App\MasterHotelRatePlan;
use App\MetaSearchEngineSetting;
use DB;
use App\Invoice;
class CouponsController extends Controller
{
private $rules = array(
        'coupon_name' => 'required ',
        'coupon_for' => 'required ',
        'valid_from' => 'required ',
        'valid_to' => 'required ',
        'discount' => 'required ',
        'hotel_id' =>'required'
    );
private $getCouponRules = array(
        'hotel_id' => 'required ',
        'checkin_date' => 'required',
        'checkout_date' => 'required',
        'room_type_id' => 'required '
    );
private $checkCouponRules = array(
        'hotel_id' => 'required',
        'checkin_date' => 'required',
        'checkout_date' => 'required',
        'code' => 'required'
    );
//Custom Error Messages
    private $messages = [
        'coupon_name.required' => 'The coupon name field is required.',
        'coupon_for.required' => 'The coupon for field is required.',
        'valid_from.required' => 'The valid from field is required.',
        'valid_to.required' => 'The valid to field is required.',
        'discount.required' => 'The discount field is required.',
        'hotel_id.required' =>'Hotel id is required',
        'avail_date.required'=>'Coupon date is required',
        'room_type_id.required'=>'Room type id is required',
        'code.required' =>'Coupon code is required',
        'hotel_id.required' =>'Hotel id is required'
     ];

/**
     * coupons Details
* Create a new record of coupons details.
     * @auther subhradip
* @return coupons details saving status
     *  function addNewCoupons use for cerating new coupons
**/
     //validation rules
public function addNewCoupons(Request $request)
{
      $coupons = new Coupons();
      $failure_message='Coupons details saving failed';
      $validator = Validator::make($request->all(),$this->rules,$this->messages);
            if ($validator->fails())
            {
                    return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
            }
      $data=$request->all();
      //TO get user id from AUTH token
      $user_id="";
      if(isset($request->auth->admin_id)){
              $data['user_id']=$request->auth->admin_id;
      }else
      if(isset($request->auth->intranet_id)){
              $data['user_id']=$request->auth->intranet_id;
      }
      else
      if(isset($request->auth->id)){
              $data['user_id']=$request->auth->id;
      }
      $data['coupon_code'] = $data['coupon_code'];
      $valid_from=$data['valid_from'];
      $valid_to=$data['valid_to'];
      $data['valid_from']=date('Y-m-d', strtotime($valid_from));
      $data['valid_to']=date('Y-m-d', strtotime($valid_to));
      if($coupons->fill($data)->save()){
        if($this->googleHotelStatus($data['hotel_id']) && $data['coupon_for'] == 1){
                try{
                        $push_promotion_google_hotel_ads = $this->googleHotelAdsPromotion($data,$coupons->coupon_id);
                }
                catch(Exception $e){
                        
                }
        }
        $res=array('status'=>1,"message"=>"Coupons details saved successfully");
        return response()->json($res);
      }
      else{
        $res=array('status'=>-1,"message"=>$failure_message);
        $res['errors'][] = "Internal server error";
        return response()->json($res);
      }
    }
    
    public function googleHotelAdsPromotion($data,$coupon_id){
        $id = uniqid();
        $time = time(); 
        $time = gmdate( "Y-m-d",$time )."T".gmdate( "H:i:s", $time ).'+05:30';
        $promotion_xml = '<?xml version="1.0" encoding="UTF-8"?>
        <Promotions partner="bookingjini_ari"
                    id="'.$id.'"
                    timestamp="'.$time.'">
          <HotelPromotions hotel_id="'.$data['hotel_id'].'">
            <Promotion id="'.$coupon_id.'">
              <BookingDates>
                 <DateRange start="'.$data['valid_from'].'" end="'.$data['valid_to'].'"/>
              </BookingDates>
              <CheckinDates>
                 <DateRange start="'.$data['valid_from'].'" end="'.$data['valid_to'].'" days_of_week="MTWHFSU"/>
              </CheckinDates>
              <CheckoutDates>
                 <DateRange start="'.$data['valid_from'].'" end="'.$data['valid_to'].'" days_of_week="MTWHFSU"/>
              </CheckoutDates>
              <Devices>
                <Device type="mobile"/>
                <Device type="tablet"/>
                <Device type="desktop"/>
              </Devices>
              <Discount percentage="'.$data['discount'].'" applied_nights="1"/>
              <LengthOfStay min="1" max="14"/>';
              $promotion_rate_xml = '';
              $promotion_room_xml = '';
              $rate_plan_array = array();
              if($data['room_type_id'] == 0){
                $get_room_types = MasterRoomType::select('room_type_id')->where('hotel_id',$data['hotel_id'])->get();
                $promotion_room_xml.= '<RoomTypes>';
                foreach($get_room_types as $room_info){
                        $get_rate_plans = MasterHotelRatePlan::select('rate_plan_id')->where('hotel_id',$data['hotel_id'])->where('room_type_id',$room_info->room_type_id)->get();
                        $promotion_rate_xml.='<RatePlans>';
                        foreach($get_rate_plans as $rate_info){ 
                                if(in_array($rate_info->rate_plan_id,$rate_plan_array)){
                                        continue;
                                }
                                else{
                                        $rate_plan_array[]= $rate_info->rate_plan_id;
                                        $promotion_rate_xml.='<RatePlan id="'.$rate_info->rate_plan_id.'"/>';
                                }
                        }
                        $promotion_rate_xml.='</RatePlans>';
                        $promotion_room_xml.='<RoomType id="'.$room_info->room_type_id.'"/>';
                }
                $promotion_room_xml.= '</RoomTypes>';
              }
              else{
                $get_rate_plans = MasterHotelRatePlan::select('rate_plan_id')->where('hotel_id',$data['hotel_id'])->where('room_type_id',$data['room_type_id'])->get();
                $promotion_rate_xml.='<RatePlans>';
                foreach($get_rate_plans as $rate_info){ 
                        $promotion_rate_xml.='<RatePlan id="'.$rate_info->rate_plan_id.'"/>';
                }
                $promotion_rate_xml.='</RatePlans>';
                $promotion_room_xml = '<RoomTypes><RoomType id="'.$data['room_type_id'].'"/></RoomTypes>';
              }
              $promotion_xml.=$promotion_rate_xml;
              $promotion_xml.=$promotion_room_xml;
              $promotion_xml.='<Stacking type="base"/>
              <UserCountries>
                <Country code="IN"/>
              </UserCountries>
            </Promotion>
          </HotelPromotions>
        </Promotions>';

        $headers = array(
                "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/promotions';
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $promotion_xml);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $resp = $google_resp;
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)),true);
        if(isset($google_resp["Success"])){
                $google_coupon_id = $google_resp["@attributes"]["id"];
                $update_coupon = Coupons::where('coupon_id',$coupon_id)->update(['google_hotel_ads_promotion_id'=>$google_coupon_id]);
        }
        else{
                $update_coupon = Coupons::where('coupon_id',$coupon_id)->update(['google_hotel_ads_promotion_id'=>$resp]);
        }
    }
    public function googleHotelStatus($hotel_id){
        $getHotelDetails = MetaSearchEngineSetting::select('hotels')->where('name','google-hotel')->first();
        $hotel_ids = explode(',',$getHotelDetails->hotels);
        if(in_array($hotel_id,$hotel_ids)){
            return true;
        }
        else{
            return false;
        }
    }
/**
     * Delete coupons
* delete record of coupons
     * @author subhradip
* @return coupons deleting status
     * function DeleteCoupons used for delete
**/
    public function DeleteCoupons(int $coupon_id ,Request $request)
    {
        if(Coupons::where('coupon_id',$coupon_id)->update(['is_trash' => 1]))
        {
                try{
                        $get_coupon_details = Coupons::where('coupon_id',$coupon_id)->first();
                        if($this->googleHotelStatus($get_coupon_details->hotel_id) && $get_coupon_details->coupon_for == 1){
                                $id = uniqid();
                                $time = time(); 
                                $time = gmdate( "Y-m-d",$time )."T".gmdate( "H:i:s", $time ).'+05:30';
                                
                                $removal_promotion_xml ='<?xml version="1.0" encoding="UTF-8"?>
                                                        <Promotions partner="bookingjini_ari"
                                                                id="'.$id.'"
                                                                timestamp="'.$time.'">
                                                        <HotelPromotions hotel_id="'.$get_coupon_details->hotel_id.'">
                                                        <Promotion id="'.$coupon_id.'" action="delete"/>
                                                        </HotelPromotions>
                                                        </Promotions>';
                                
                                
                                $headers = array(
                                        "Content-Type: application/xml",
                                );
                                $url =  'https://www.google.com/travel/hotels/uploads/promotions';
                                $ch = curl_init();
                                curl_setopt( $ch, CURLOPT_URL, $url );
                                curl_setopt( $ch, CURLOPT_POST, true );
                                curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
                                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                                curl_setopt( $ch, CURLOPT_POSTFIELDS, $removal_promotion_xml);
                                $google_resp = curl_exec($ch);
                                curl_close($ch);
                        }
                }
                catch(Exception $e){

                }
                $res=array('status'=>1,"message"=>'Coupons Deleted successfully');
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
     * coupons
* Update record ofcoupons
     * @author subhradip
* @return coupons  saving status
     * function Updatecoupons use for update
**/
    public function Updatecoupons(int $coupon_id,Request $request)
    {
        $coupons = new Coupons();
        $failure_message="Coupons  updation failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
                return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $valid_from=$data['valid_from'];
        $valid_to=$data['valid_to'];
        $data['valid_from']=date('Y-m-d', strtotime($valid_from));
        $data['valid_to']=date('Y-m-d', strtotime($valid_to));
        $coupons = Coupons::where('coupon_id',$coupon_id)->first();
        if($coupons->coupon_id == $coupon_id )
        {
                if($coupons->fill($data)->save())
                {
                        if($this->googleHotelStatus($data['hotel_id']) && $data['coupon_for'] == 1){
                                try{
                                        $push_promotion_google_hotel_ads = $this->googleHotelAdsPromotion($data,$coupon_id);
                                }
                                catch(Exception $e){
                                        
                                }
                        }
                        $res=array('status'=>1,"message"=>"Coupons updated successfully");
                        return response()->json($res);
                }
                else
                {
                        $res=array('status'=>-1,"message"=>$failure_message);
                        $res['errors'][] = "Internal server error";
                        return response()->json($res);
                }
        }
     }
    /**
* Get  coupons
     * get one record of  Macoupons
* @author subhradip
     * function GetCoupons for delecting data
**/
    public function GetCoupons(int $coupon_id ,Request $request)
    {
        $coupons=new Coupons();
        if($coupon_id)
        {
                $conditions=array('coupon_id'=>$coupon_id,'is_trash'=>0);
                $res=Coupons::where($conditions)->first();
                if(!empty($res))
                {
                        $res=array('status'=>1,"message"=>"Coupons details retrieved successfully",'data'=>$res);
                        return response()->json($res);
                }
                else
                {
                        $res=array('status'=>0,"message"=>"Coupons fetching failed");
                        return response()->json($res);
                }
        }
        else
        {
                $res=array('status'=>-1,"message"=>"Coupons fetching failed");
                return response()->json($res);
        }
    }
/**
     * Get all coupons
* get All record of coupons
     * @author subhradip
* function GetAllMasterHotelRateplan for selecting all data
    **/
public function GetAllCoupons(Request $request)
{
        $coupons=new Coupons();
        $conditions=array('is_trash'=>0);
        $res=Coupons::where($conditions)->get();
        if(sizeof($res)>0)
        {
                $res=array('status'=>1,'message'=>"records found",'data'=>$res);
                return response()->json($res);
        }
        else
        {
                $res=array('status'=>0,"message"=>"No Coupons  records found");
                return response()->json($res);
        }
}
/**
* Get all coupons by hotel_id
* get All record of coupons
* @author subhradip
* function GetAllMasterHotelRateplan for selecting all data
**/
public function GetCouponsByHotel(int $hotel_id,Request $request)
{
        $coupons=new Coupons();
        $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
        $res=Coupons::where($conditions)->get();
        foreach($res as $data){
              if($data->room_type_id == 0){
                  $data['room_type'] = "All";
                  if($data->coupon_for == '1'){
                    $data->coupon_for = 'public';
                  }
                  else{
                    $data->coupon_for = 'private';
                  }
              }
              else{
                  $room_types=MasterRoomType::select('room_type')->where('room_type_id',$data->room_type_id)->first();
                  $data['room_type'] = isset($room_types->room_type)?$room_types->room_type:'NA';
                  if($data->coupon_for == '1'){
                    $data->coupon_for = 'public';
                  }
                  else{
                    $data->coupon_for = 'private';
                  }
              }
        }
        if(sizeof($res)>0)
        {
                $res=array('status'=>1,'message'=>"records found",'data'=>$res);
                return response()->json($res);
        }
        else
        {
                $res=array('status'=>0,"message"=>"No Coupons  records found");
                return response()->json($res);
        }
}
public function GetCouponsPublic(Request $request)
{
        $failure_message="No Coupons found";
        $validator = Validator::make($request->all(),$this->getCouponRules,$this->messages);
        if ($validator->fails())
        {
                return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $hotel_id=$data['hotel_id'];
        $length=sizeof($data['room_type_id']);
        $room_type_id=$data['room_type_id'];
        $room_type_id[$length]=0;//Push all room types id(coupon applicable for all rooms is 0) to room_type_id array
        $checkin_date=$data['checkin_date'];
        $checkout_date=$data['checkout_date'];
        $checkin_date=date('Y-m-d',strtotime($checkin_date));
        $checkout_date=date('Y-m-d',strtotime($checkout_date));
        $coupons=new Coupons();
        /*$user_id=$request->auth->user_id;
        $conditions=array('user_id'=>$user_id);
        $used_coupons=UsedCoupon::where($conditions)->get();*/
        $coupons=Coupons::
        leftJoin('bookingjini_kernel.room_type_table as room_type_table','coupons.room_type_id','room_type_table.room_type_id')
        ->where('coupons.hotel_id',$hotel_id)
        ->whereIn('coupons.room_type_id', $room_type_id)
        ->where('coupons.valid_from', '<=', $checkin_date)
        ->where('coupons.valid_to', '>=', $checkin_date)
        ->where('coupons.coupon_for',1)///2 means private coupon
        ->where('coupons.is_trash',0)
        ->select('coupons.*','room_type_table.room_type as room_type')->get();
        if(sizeof($coupons)>0)
        {
                $res=array('status'=>1,'message'=>"Coupons retrieved successfully",'data'=>$coupons);
                 return response()->json($res);
        }
        else
        {
                $res=array('status'=>0,"message"=>"No Coupons found");
                return response()->json($res);
        }
}
public function checkCouponCode(Request $request)
{
        $failure_message="No Coupons found";
        $validator = Validator::make($request->all(),$this->checkCouponRules,$this->messages);
        if ($validator->fails())
        {
                return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $hotel_id=$data['hotel_id'];
        $checkin_date=date('Y-m-d',strtotime($data['checkin_date']));
        $checkout_date=date('Y-m-d',strtotime($data['checkout_date']));
        $code=$data['code'];
        $coupons=Coupons::where('hotel_id',$hotel_id)
        ->where('valid_from', '<=', $checkin_date)
        ->where('valid_to', '>=', $checkin_date)
        ->where('coupon_for',2)///2 means private coupon
        ->where('coupon_code',$code)
        ->where('is_trash',0)
        ->select('*')->first();
        //dd($coupons);
        //$user_id=$request->auth->user_id;
        /*$conditions=array('user_id'=>$user_id,'code'=>$code);
        $used_coupons=UsedCoupon::where($conditions)->first();*/
        $used_coupons=0;
        if($coupons && $used_coupons==0)
        {
                $res=array('status'=>1,'message'=>"Coupon is valid",'data'=>$coupons);
                return response()->json($res);
        }
        else
        {
                $res=array('status'=>0,'message'=>"Coupon you have applied ,Already expired");
                return response()->json($res);
        }
}

public function checkCouponCodeNew(Request $request)
{
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $begin = strtotime($data['checkin_date']);
        $end = strtotime($data['checkout_date']);
        $code = $data['code'];
        $from_date = date('Y-m-d', strtotime($data['checkin_date']));
        $data_array = array();
    for ($currentDate = $begin; $currentDate < $end; $currentDate += (86400)) {

        $data_array_present = array();
        $data_array_notpresent = array();
        $status = array();
        $Store = date('Y-m-d', $currentDate);

        $convert_store_date = date('D',strtotime($Store));

        $get_data = DB::select(DB::raw('select coupon_id,room_type_id,
    case when room_type_id != 0 then "present" when room_type_id = 0 then "notpresent" end as status,
    coupon_name,coupon_code,valid_from,
    valid_to,discount_type,coupon_for,discount,blackoutdates,blackoutdays,created_date_time,updated_date_time,created_at,updated_at,private_coupon_restriction,a.date,a.abc
    FROM
    (
    select t2.coupon_id,
    case when t2.room_type_id!=0 then t2.room_type_id else 0 end as room_type_id,
    coupon_name,coupon_code,valid_from,valid_to,discount_type,coupon_for,discount,blackoutdates,blackoutdays,created_date_time,updated_date_time,created_at,updated_at,private_coupon_restriction,"' . $Store . '" as date,
    case when "' . $Store . '" between valid_from and valid_to then "yes" else "no" end as abc
    from booking_engine.coupons
    INNER JOIN
    (
    SELECT room_type_id,
    substring_index(group_concat(cast(coupon_id as CHAR) order by discount desc), ",", 1 ) as coupon_id,MAX(discount)
    FROM booking_engine.coupons where hotel_id = "' . $hotel_id . '" and coupon_for = 2 and is_trash = 0
    and ("' . $Store . '" between valid_from and valid_to) and (NOT FIND_IN_SET("' . $Store . '",blackoutdates) OR blackoutdates IS NULL) and (NOT FIND_IN_SET("' . $convert_store_date . '",blackoutdays) OR blackoutdays IS NULL) and coupon_code = "'.$code.'"
    GROUP BY room_type_id
    order by coupon_id desc
    ) t2 ON coupons.room_type_id = t2.room_type_id AND coupons.coupon_id = t2.coupon_id
    ) AS a where a.abc = "yes"
    order by room_type_id,coupon_id desc'));


        foreach ($get_data as $data) {

                $status[] = $data->status;
                $data_present['coupon_id']        = $data->coupon_id;


                $data_present['room_type_id']     = $data->room_type_id;
                $data_present['date']             = $data->date;
                $data_present['coupon_name']      = $data->coupon_name;
                $data_present['coupon_code']      = $data->coupon_code;
                $data_present['valid_from']       = $data->valid_from;
                $data_present['valid_to']         = $data->valid_to;
                $data_present['coupon_for']       = $data->coupon_for;
                $data_present['discount_type']    = $data->discount_type;
                $data_present['discount']         = $data->discount;
                $data_present['blackoutdates']    = $data->blackoutdates;
                $data_present['blackoutdays']         = $data->blackoutdays;
                $data_present['private_coupon_restriction']         = $data->private_coupon_restriction;
                $data_present['created_date_time']        = $data->created_date_time;
                $data_present['updated_date_time']        = $data->updated_date_time;
                $data_present['created_at']        = $data->created_at;
                $data_present['updated_at']        = $data->updated_at;

            // if(empty($check_blackout_dates)){
                if ($data->valid_from <= $from_date && $data->valid_to >= $from_date) {
                    $data_array_present[] = $data_present;
                }
            // }
        }
        if ($data_array_present) {
            for ($i = 0; $i < sizeof($data_array_present); $i++) {
                $data_array[] = $data_array_present[$i];
            }
        }
        $from_info = strtotime($from_date);
        $from_info += (86400);
        $from_date = date('Y-m-d', $from_info);

        if (sizeof($get_data)>0) {
                $coupon_count = Invoice::where('hotel_id',$hotel_id)->where('agent_code',$code)->where('booking_status','1')->count();
                $private_coupon_status = $get_data[0]->private_coupon_restriction;
                if ($private_coupon_status!= 0 && $private_coupon_status <= $coupon_count) {
                        $res = array('status' => 0, 'message' => "Invalid Coupon");
                        return response()->json($res);
                }
        }
    }
    if (sizeof($data_array) > 0) {
        $res = array('status' => 1, 'message' => "Private coupon retrieved successfully", 'data' => $data_array);
        return response()->json($res);
    } else {
        $res = array('status' => 0, 'message' => "!Sorry Private coupon is not available", 'data' => array());
        return response()->json($res);
    }    
}

}
