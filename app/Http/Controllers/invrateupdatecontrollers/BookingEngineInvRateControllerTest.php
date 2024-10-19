<?php
namespace App\Http\Controllers\invrateupdatecontrollers;
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
use App\MasterHotelRatePlan;
use App\CmOtaRoomTypeSynchronize;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;
/**
 * This controller is used for bookingengine single,bulk,sync and block of inv and rate
 * @auther ranjit
 * created date 21/02/19.
 */

class BookingEngineInvRateControllerTest extends Controller
{
  protected $ipService,$getdata_curlreq;
  public function __construct(IpAddressService $ipService,GetDataForRateController $getdata_curlreq){
      $this->ipService = $ipService;
      $this->getdata_curlreq = $getdata_curlreq;
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
    public function singleInventoryUpdate(Request $request){
      $failure_message='Inventory sync failed';
      $validator = Validator::make($request->all(),$this->inv_rules,$this->inv_messages);
      if ($validator->fails())
      {
          return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
      }
      $data=$request->all();
      $inventory=$data['inv'];
      $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
      $resp=array();
      foreach($imp_data['ota_id'] as $otaid){
          if($otaid == -1){//booking engine update
          //Google-Hotel-api
          $flag=0;
          $count=0;
          $logModel = new LogTable();
          foreach($inventory as $invs)
          {
            foreach($invs['inv'] as $inv)
            {
                $hotel_inventory= new Inventory();
                $fmonth=explode('-',$inv['date']);//for removing extra o from month and remove this code after mobile app update
                if(strlen($fmonth[1]) == 3)
                {
                    $fmonth[1]=ltrim($fmonth[1],0);
                }
                $inv['room_type_id']       = $invs['room_type_id'];
                $inv['block_status']       = 0;
                $inv['user_id']            = $imp_data['user_id'];
                $inv['client_ip']          = $imp_data['client_ip'];
                $inv['hotel_id']           = $imp_data['hotel_id'];
                $inv['date_from']          = $inv['date'];
                $inv['date_to']            = $inv['date'];
                $success_flag=1;
                try{
                    $hotel_inventory->fill($inv)->save();
                    if($this->googleHotelStatus($imp_data['hotel_id'])==true){
                        $update = $this->inventoryUpdateToGoogleAds($inv['hotel_id'],$inv['room_type_id'],$inv['no_of_rooms'],$inv['date_from'],$inv['date_to']);
                    }
                }
                catch(\Exception $e){
                   $success_flag=0;
                }
                if($success_flag)
                {
                    $log_data               = [
                        "action_id"          => 4,
                        "hotel_id"           => $imp_data['hotel_id'],
                        "ota_id"      	     => 0,
                        "inventory_ref_id"   => $hotel_inventory->inventory_id,
                        "user_id"            => $imp_data['user_id'],
                        "request_msg"        => '',
                        "response_msg"       => '',
                        "request_url"        => '',
                        "status"             =>  1,
                        "ip"                 => $imp_data['client_ip'],
                        "comment"	=> "Processing for update "
                        ];
                    $logModel->fill($log_data)->save();//saving pre log data

                    $flag=1;
                }
                else{
                    $flag=0;
                }
            }
            $count=$count+$flag;
        }
          if(sizeof($inventory) == $count)
          {
              return response()->json(array('status'=>1,'message'=>'Room type update successfully in Booking engine','be'=>'be'));
          }
          else{
              return response()->json(array('status'=>0,'message'=>'Room type update unsuccessfully in Booking engine','be'=>'be'));
          }
        }
      }
    }
    //block inventory in booking engine
    public function blockInventoryUpdate(Request $request){
        $failure_message='Block inventry operation failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails()){
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $room_type_id=$request->input('room_type_id');
        $fmonth=explode('-',$data['date_from']);//for removing extra o from month and remove this code after mobile app update
        if(strlen($fmonth[1]) == 3){
            $fmonth[1]=ltrim($fmonth[1],0);
        }

        $data['date_from']=implode('-',$fmonth);
        $tmonth=explode('-',$data['date_to']);
        if(strlen($tmonth[1]) == 3){
            $tmonth[1]=ltrim($tmonth[1],0);
        }
        $data['date_to']=implode('-',$tmonth);

        $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
        $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
        $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
        $data["client_ip"]=$imp_data['client_ip'];
        $data["user_id"]=$imp_data['user_id'];
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){
          if($otaid == -1){
          $logModel = new LogTable();
          $count=0;
          $inventory = new Inventory();
          $data['room_type_id']=$room_type_id;
          $success_flag=1;
              try{
                  $inventory->fill($data)->save();
                  if($this->googleHotelStatus($data['hotel_id'])==true){
                    $update = $this->inventoryUpdateToGoogleAds($data['hotel_id'],$data['room_type_id'],0,$data['date_from'],$data['date_to']);
                    }
              }
              catch(\Exception $e){
                 $success_flag=0;
              }
          if($success_flag)
          {
              $log_data               = [
                  "action_id"          => 4,
                  "hotel_id"           => $data['hotel_id'],
                  "ota_id"      	     => 0,
                  "inventory_ref_id"   => $inventory->inventory_id,
                  "user_id"            => $inventory->user_id,
                  "request_msg"        => '',
                  "response_msg"       => '',
                  "request_url"        => '',
                  "status"             =>  1,
                  "ip"                 => $inventory->client_ip,
                  "comment"	=> "Processing for update "
                  ];
              $logModel->fill($log_data)->save();//saving pre log data
              return  $return_resp=array('status' => 1,'message'=> 'Room type blocked successfully on Booking Engine','be'=>'be');
          }
          else
          {
              return  $return_resp=array('status' => 0,'message'=> 'Room type block unsuccessfully on Booking Engine','be'=>'be');
          }
        }
      }
    }
    public function bulkInvUpdate(Request $request){
        $hotel_inventory = new Inventory();
        $logModel = new LogTable();
        $failure_message='Inventory updation failed';
        $validator = Validator::make($request->all(),$this->blk_rules,$this->blk_messages);
        if ($validator->fails()){
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $fmonth=explode('-',$data['date_from']);//for removing extra o from month and remove this code after mobile app update
        if(strlen($fmonth[1]) == 3){
            $fmonth[1]=ltrim($fmonth[1],0);
            $fmonth[2]= $fmonth[2] < 10 ? "0".$fmonth[2] : $fmonth[2];
        }
        $data['date_from']=implode('-',$fmonth);
        $tmonth=explode('-',$data['date_to']);
        if(strlen($tmonth[1]) == 3){
            $tmonth[1]=ltrim($tmonth[1],0);
            $tmonth[2]= $tmonth[2] < 10 ? "0".$tmonth[2] : $tmonth[2];
        }
        $bulk_data['hotel_id']          = $data['hotel_id'];
        $bulk_data['room_type_id']      = $data['room_type_id'];
        $bulk_data['no_of_rooms']       = $data['no_of_rooms'];
        $bulk_data['los']               = $data['los'];
        $bulk_data['block_status']      = $data['block_status'];
        $bulk_data['date_to']           = implode('-',$tmonth);
        $bulk_data['date_from']         = date('Y-m-d',strtotime($data['date_from']));
        $bulk_data['date_to']           = date('Y-m-d',strtotime($data['date_to']));
        $bulk_data['multiple_days']     = '{"Sun":"1","Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1"}';
        $imp_data                       = $this->impDate($data,$request);//used to get user id and client ip.
        $bulk_data["client_ip"]         = $imp_data['client_ip'];
        $bulk_data["user_id"]           = $imp_data['user_id'];
        $resp=array();
        foreach($imp_data['ota_id'] as $otaid){
          if($otaid == -1){
              if($hotel_inventory->fill($bulk_data)->save()){
                  //Google-hotel-api
                  if($this->googleHotelStatus($data['hotel_id'])==true){
                      $update = $this->inventoryUpdateToGoogleAds($bulk_data['hotel_id'],$bulk_data['room_type_id'],$bulk_data['no_of_rooms'],$bulk_data['date_from'],$bulk_data['date_to']);
                  }
                  //Google-hotel-api ends

                  $log_data               = [
                      "action_id"          => 4,
                      "hotel_id"           => $imp_data['hotel_id'],
                      "ota_id"      	     => 0,
                    "inventory_ref_id"    => $hotel_inventory->inventory_id,
                      "user_id"            => $imp_data['user_id'],
                      "request_msg"        => '',
                      "response_msg"       => '',
                      "request_url"        => '',
                      "status"             =>  1,
                      "ip"                 => $imp_data['client_ip'],
                      "comment"	=> "Processing for update "
                      ];
                  $logModel->fill($log_data)->save();//saving pre log data
                  $resp=array('status'=>1,'message'=>'inventory update sucessfully in Booking engine','be'=>'be');
                  return response()->json($resp);
              }
              else{
                  $resp=array('status'=>0,'message'=>'inventory update unsucessfully in Booking engine','be'=>'be');
                    return response()->json($resp);
              }
          }
        }
    }
    //update rate in bookingt engine.
    public function singleRateUpdate(Request $request)
    {
      $data=$request->all();
      $rates_data=$data['rates'];

      $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
      $resp=array();
      foreach($imp_data['ota_id'] as $otaid){
          if($otaid == -1){
              //Google-hotel-api
              $flag=0;
              $count=0;
              $logModel      = new RateUpdateLog();
              foreach($rates_data as $rates)
              {
                  foreach($rates['rates'] as $rate)
                  {
                      $rate_plan_log=new RatePlanLog();
                      $ratedata=array();
                      $ratedata['room_type_id']   =   $rates['room_type_id'];
                      $ratedata['rate_plan_id']   =   $rates['rate_plan_id'];
                      $multiple_days              =   '{"Mon":"1","Tue":"1","Wed":"1","Thu":"1","Fri":"1","Sat":"1","Sun":"1"}';
                      $fmonth=explode('-',$rate['date']);//for removing extra o from month and remove this code after mobile app update
                      if(strlen($fmonth[1]) == 3)
                      {
                          $fmonth[1]=ltrim($fmonth[1],0);
                      }
                      $rate['date']=implode('-',$fmonth);
                      $ratedata['hotel_id']=$imp_data['hotel_id'];
                      $ratedata['from_date']=date('Y-m-d',strtotime($rate['date']));
                      $ratedata['to_date']=date('Y-m-d',strtotime($rate['date']));
                      $ratedata['multiple_occupancy']=json_encode($rate['multiple_occupancy']);
                      $ratedata['bar_price']=$rate['bar_price'];
                      $ratedata['los']=1;//Default length of stay
                      $ratedata['extra_adult_price']=0;
                      $ratedata['extra_child_price']=0;
                      $ratedata['multiple_days']=$multiple_days;
                      $price=MasterHotelRatePlan::select('min_price','max_price')->where('hotel_id',$imp_data['hotel_id'])->where('room_type_id',$rates["room_type_id"])->where('rate_plan_id',$rates["rate_plan_id"])->first();
                      $bp=0;
                      $mp = 0;
                      if($rate['bar_price'] >= $price->min_price && $rate['bar_price'] < $price->max_price)
                      {
                          $bp=1;
                      }
                      if($bp==0)
                      {
                          $res=array('status'=>0,'message'=>"price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
                          return $res;
                      }
                      foreach($rate['multiple_occupancy'] as $multi){
                          $multi_occupancy = (int)$rate['multiple_occupancy'][0];
                          if($multi_occupancy >= $price->min_price && $multi_occupancy < $price->max_price){
                              $mp=1;
                          }
                      }
                      if($mp==0)
                      {
                          return array('status'=>0,'message'=>"price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
                      }
                      $success_flag=1;
                      try{
                          $rate_plan_log->fill($ratedata)->save();
                            if($this->googleHotelStatus($imp_data['hotel_id'])==true){
                                $rate_update = $this->rateUpdateToGoogleAds($ratedata['hotel_id'],$ratedata['room_type_id'],$ratedata['rate_plan_id'],$ratedata['from_date'],$ratedata['to_date'],$ratedata['bar_price'],$ratedata['multiple_days']);
                                $los_update = $this->losUpdateToGoogleAds($ratedata['hotel_id'],$ratedata['room_type_id'],$ratedata['rate_plan_id'],$ratedata['from_date'],$ratedata['to_date'],$ratedata['los'],$ratedata['multiple_days']);
                            }
                      }
                      catch(\Exception $e){
                         $success_flag=0;
                      }
                      if($success_flag)
                      {
                          $log_data               	= [
                              "action_id"          => 2,
                              "hotel_id"           => $imp_data['hotel_id'],
                              "ota_id"      		 => 0,
                              "rate_ref_id"        => $rate_plan_log->rate_plan_log_id,
                              "user_id"            => $imp_data['user_id'],
                              "request_msg"        => '',
                              "response_msg"       => '',
                              "request_url"        => '',
                              "status"         	 => 1,
                              "ip"         		 => $imp_data['client_ip'],
                              "comment"			 => "Processing for update "
                              ];
                          $logModel->fill($log_data)->save();//saving pre log data
                          $flag=1;
                          $res=array('status'=>1,'message'=>'rate update sucessfully in Booking engine','be'=>'be');
                      }
                      else{
                          $flag=0;
                          $res=array('status'=>0,'message'=>'rate update unsucessfully in Booking engine','be'=>'be');
                      }
                  }
                  $count=$count+$flag;
              }
              if(sizeof($rates_data) == $count)
              {
                  return array('status'=>1,'message'=>'Rate update sucessfully in Booking engine','be'=>'be');
              }
              else{
                  return array('status'=>0,'message'=>'Rate update unsucessfully in Booking engine','be'=>'be');
              }
          }
        }
     }
     //block rate in booking engine
     public function blockRateUpdate(Request $request)
     {
       $failure_message='Block inventry operation failed';
       $validator = Validator::make($request->all(),$this->rules,$this->messages);
       if ($validator->fails())
       {
           return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
       }
       $data=$request->all();
       $room_type_id=$request->input('room_type_id');
       $fmonth=explode('-',$data['date_from']);//for removing extra o from month and remove this code after mobile app update
       if(strlen($fmonth[1]) == 3)
       {
           $fmonth[1]=ltrim($fmonth[1],0);
       }

       $data['date_from']=implode('-',$fmonth);
       $tmonth=explode('-',$data['date_to']);
       if(strlen($tmonth[1]) == 3)
       {
           $tmonth[1]=ltrim($tmonth[1],0);
       }
       $data['date_to']=implode('-',$tmonth);

       $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
       $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
       $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
       $resp=array();
       foreach($imp_data['ota_id'] as $otaid){
           if($otaid == -1){
                if($this->googleHotelStatus($imp_data['hotel_id'])==true){
                   
              }
               $logModel      = new RateUpdateLog();
                $rate = new RatePlanLog();
                $data['date_from'] = date('Y-m-d',strtotime($data['date_from']));
                $data['date_to'] = date('Y-m-d',strtotime($data['date_to']));
                $cond = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$room_type_id,'rate_plan_id'=>$data['rate_plan_id']);
                $getRateDetails = RatePlanLog::select('*')
                                  ->where($cond)->where('from_date','<=',$data['date_from'])
                                  ->where('to_date','>=',$data['date_to'])
                                  ->orderBy('rate_plan_log_id','DESC')
                                  ->first();

               $rate_data = [
                  'hotel_id'          => $getRateDetails->hotel_id,
                  'room_type_id'      => $getRateDetails->room_type_id,
                  'rate_plan_id'      => $getRateDetails->rate_plan_id,
                  'bar_price'         => $getRateDetails->bar_price,
                  'multiple_occupancy'=> $getRateDetails->multiple_occupancy,
                  'multiple_days'     => $getRateDetails->multiple_days,
                  'from_date'         => $data['date_from'],
                  'to_date'           => $data['date_to'],
                  'block_status'      => 1,
                  'los'               => 0,
                  'client_ip'         => $getRateDetails->client_ip,
                  'user_id'           => $getRateDetails->user_id,
                  'extra_adult_price' => $getRateDetails->extra_adult_price,
                  'extra_child_price' => $getRateDetails->extra_child_price,
              ];

               if($rate_status=$rate->fill($rate_data)->save()){
               $log_data               	= [
                  "action_id"          => 2,
                  "hotel_id"           => $getRateDetails->hotel_id,
                  "ota_id"      		   => 0,
                  "rate_ref_id"        => $rate->rate_plan_log_id,
                  "user_id"            => $getRateDetails->user_id,
                  "request_msg"        => '',
                  "response_msg"       => '',
                  "request_url"        => '',
                  "status"         	 => 1,
                  "ip"         		 => $getRateDetails->client_ip,
                  "comment"			 => "Processing for update "
                  ];
              $logModel->fill($log_data)->save();//saving pre log data
               return  $return_resp=array('status' => 1,'message'=> 'Rate plan blocked successfully on Booking Engine','be'=>'be');
                }
               else{
                  return  $return_resp=array('status' => 0,'message'=> 'Rate plan block unsuccessfully on Booking Engine','be'=>'be');
               }
             }
         }
      }
     public function bulkRateUpdate(Request $request){
         $data=$request->all();
         $imp_data=$this->impDate($data,$request);//used to get user id and client ip.
         $price=MasterHotelRatePlan::select('min_price','max_price')->where('hotel_id',$imp_data['hotel_id'])->where('room_type_id',$data["room_type_id"])->where('rate_plan_id',$data["rate_plan_id"])->first();
         $bp=0;
         $mp=0;
         if($data['bar_price'] >= $price->min_price && $data['bar_price'] < $price->max_price)
         {
             $bp=1;
         }
         if($bp==0)
         {
             $res=array('status'=>0,'message'=>"price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
             return response()->json($res);
         }
         if(sizeof($data['multiple_occupancy']) == 0){
             $data['multiple_occupancy'][0] = $data['bar_price'];
         }
         $multi_price=$data['multiple_occupancy'];
         if(sizeof($multi_price)>0){
             foreach($multi_price as $key => $multiprice)
             {
                 if($multiprice == 0 || $multiprice == ''){
                     $rate['multiple_occupancy'][$key] = $rate['bar_price'];
                 }
                 if($multiprice >= $price->min_price && $multiprice < $price->max_price)
                 {
                     $mp=$mp+1;
                 }
             }
         }
         if($mp == 0)
         {
             $res=array('status'=>0,'message'=>"multiple occupancy should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
             return response()->json($res);
         }
         $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
         $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
         $conds = array('hotel_id'=>$data['hotel_id'],'derived_room_type_id'=>$data['room_type_id'],'derived_rate_plan_id'=>$data['rate_plan_id']);
         $chek_parents = DerivedPlan::select('*')->where($conds)->get();
         if(sizeof($chek_parents)>0){
             if($data['extra_adult_price'] == 0 || $data['extra_adult_price'] == ""){
                 $data['extra_adult_price'] = $this->getExtraAdultChildPrice($data,1);
             }
             if($data['extra_child_price'] == 0 || $data['extra_child_price'] == ""){
                 $data['extra_child_price'] = $this->getExtraAdultChildPrice($data,2);
             }
             $response = $this->bulkBeOtaPush($imp_data,$data);
             $bar_price = $data['bar_price'];
             $extra_adult_price = $data['extra_adult_price'];
             $extra_child_price = $data['extra_child_price'];
             $multiple_occupancy_array = $data['multiple_occupancy'];
             foreach($chek_parents as $details){
                 $multiple_occupancy=array();
                 $getPrice = explode(",",$details->amount_type);
                 $indexSize =  sizeof($getPrice)-1;

                 if($details->select_type == 'percentage'){
                     $percentage_price = ($bar_price * $getPrice[$indexSize])/100;
                     $data['bar_price'] = round($bar_price + $percentage_price);
                     foreach($multiple_occupancy_array as $key => $multi){
                         $multi_per_price = ($multi * $getPrice[$key])/100;
                         $multiple_occupancy[]= (string)round($multi + $multi_per_price);
                     }
                     $data['multiple_occupancy'] = $multiple_occupancy;
                 }
                 else{
                     $data['bar_price'] = round($bar_price + $getPrice[$indexSize]);
                     foreach($multiple_occupancy_array as $key => $multi){
                         $multiple_occupancy[]= (string)round($multi + $getPrice[$key]);
                     }
                     $data['multiple_occupancy'] = $multiple_occupancy;
                 }
                 if($details->extra_adult_select_type == 'percentage'){
                     $percentage_price = ($extra_adult_price * $details->extra_adult_amount)/100;
                     $data['extra_adult_price'] = round($extra_adult_price + $percentage_price);
                 }
                 else{
                     $data['extra_adult_price'] = round($extra_adult_price + $details->extra_adult_amount);
                 }
                 if($details->extra_child_select_type == 'percentage'){
                     $percentage_price = ($extra_child_price * $details->extra_child_amount)/100;
                     $data['extra_child_price'] = round($extra_child_price + $percentage_price);
                 }
                 else{
                     $data['extra_child_price'] = round($extra_child_price + $details->extra_child_amount);
                 }
                 $data['room_type_id'] = $details->room_type_id;
                 $data['rate_plan_id'] = $details->rate_plan_id;
                 $response = $this->bulkBeOtaPush($imp_data,$data);
             }
         }
         else{
             $response = $this->bulkBeOtaPush($imp_data,$data);
         }
        return response()->json($response);
     }
     public function bulkBeOtaPush($imp_data,$data){
         $rate_plan_log = new RatePlanLog();
         $logModel      = new RateUpdateLog();
         $resp=array();
         foreach($imp_data['ota_id'] as $otaid){
             if($otaid == -1) {
                 $data["client_ip"]=$imp_data['client_ip'];
                 $data["user_id"]=$imp_data['user_id'];
                 $be_opt = $data;
                 $be_opt['multiple_days']=json_encode($be_opt['multiple_days']);
                 $be_opt['multiple_occupancy'] = json_encode($be_opt['multiple_occupancy']);
                 if($rate_plan_log->fill($be_opt)->save())
                 {
                     //Google-hotel-api
                     if($this->googleHotelStatus($data['hotel_id'])){
                        $rate_update = $this->rateUpdateToGoogleAds($be_opt['hotel_id'],$be_opt['room_type_id'],$be_opt['rate_plan_id'],$be_opt['from_date'],$be_opt['to_date'],$be_opt['bar_price'],$be_opt['multiple_days']);
                        $los_updatec = $this->losUpdateToGoogleAds($be_opt['hotel_id'],$be_opt['room_type_id'],$be_opt['rate_plan_id'],$be_opt['from_date'],$be_opt['to_date'],$be_opt['los'],$be_opt['multiple_days']);
                     }
                     $log_data               	= [
                         "action_id"          => 2,
                         "hotel_id"           => $imp_data['hotel_id'],
                         "ota_id"             => 0,
                         "rate_ref_id"        => $rate_plan_log->rate_plan_log_id,
                         "user_id"            => $imp_data['user_id'],
                         "request_msg"        => '',
                         "response_msg"       => '',
                         "request_url"        => '',
                         "status"         	 => 1,
                         "ip"         		 => $imp_data['client_ip'],
                         "comment"			 => "Processing for update "
                         ];
                     $logModel->fill($log_data)->save();//saving pre log data
                     $resp=array('status'=>1,'message'=>'rate update sucessfully in Booking engine','be'=>'be');
                 }
                 else{
                     $resp=array('status'=>0,'message'=>'rate update unsucessfully in Booking engine','be'=>'be');
                 }
             }
         }
         return $resp;
     }
     public function impDate($data,$request)
     {
         $hotel_id = $data['hotel_id'];
         $ota_id = $data['ota_id'];
         $client_ip=$this->ipService->getIPAddress();//get client ip
         $user_id=0;
         if(isset($request->auth->admin_id)){
             $user_id=$request->auth->admin_id;
         }else if(isset($request->auth->super_admin_id)){
             $user_id=$request->auth->super_admin_id;
         }
         else if(isset($request->auth->id)){
             $user_id=$request->auth->id;
         }
         else{
             if($data['admin_id'] != 0){
                 $user_id = $data['admin_id'];
             }
         }
         return array('hotel_id'=>$hotel_id,'ota_id'=>$ota_id,'client_ip'=>$client_ip,'user_id'=>$user_id);
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
     public function getExtraAdultChildPrice($data,$source){
         $conds = array('hotel_id'=>$data['hotel_id'],'room_type_id'=>$data['room_type_id'],'rate_plan_id'=>$data['rate_plan_id']);
         $getPriceDetails = MasterHotelRatePlan::select('extra_adult_price','extra_child_price')
                             ->where($conds)
                             ->first();
         if($getPriceDetails){
             if($source = 1){
                 return $getPriceDetails->extra_adult_price;
             }
             else{
                 return $getPriceDetails->extra_child_price;
             }
         }
         else{
             return 0;
         }
     }
     public function inventoryUpdateToGoogleAds($hotel_id,$room_type_id,$no_of_rooms,$from_date,$to_date){
        $from_date = date('Y-m-d',strtotime($from_date));
        $to_date = date('Y-m-d',strtotime($to_date));
        $id = uniqid();
        $time = time(); 
        $time = gmdate( "Y-m-d",$time )."T".gmdate( "H:i:s", $time ).'+05:30';
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelInvCountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                            EchoToken="'.$id.'"
                                            TimeStamp="'.$time.'"
                                            Version="3.0">
                <POS>
                    <Source>
                    <RequestorID ID="bookingjini_ari"/>
                    </Source>
                </POS>
                <Inventories HotelCode="'.$hotel_id.'">
                    <Inventory>
                    <StatusApplicationControl Start="'.$from_date.'"
                                                End="'.$to_date.'"
                                                InvTypeCode="'.$room_type_id.'"/>
                    <InvCounts>
                        <InvCount Count="'.$no_of_rooms.'" CountType="2"/>
                    </InvCounts>
                    </Inventory>
                </Inventories>
            </OTA_HotelInvCountNotifRQ>';
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_inv_count_notif';
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url);
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)),true);
        if(isset($google_resp["Success"])){
            if($no_of_rooms == 0){
                $getRatePlans = MasterHotelRatePlan::select('rate_plan_id')
                                ->where('hotel_id',$hotel_id)
                                ->where('room_type_id',$room_type_id)
                                ->get();
                if(!empty($getRatePlans)){
                    $rate_resp = array();
                    foreach($getRatePlans as $rate_id){
                        $rate_resp[] = $this->rateUpdateToGoogleAds($hotel_id,$room_type_id,$rate_id,$from_date,$to_date,-1,-1);
                    }
                }
            }
            $resp = array('status'=>1,'message'=>'inventory updation successfully');
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0,'message'=>'inventory updation fails');
            return response()->json($resp);
        }
     }
     public function rateUpdateToGoogleAds($hotel_id,$room_type_id,$rate_plan_id,$from_date,$to_date,$bar_price,$multiple_days){
         $from_date = date('Y-m-d',strtotime($from_date));
        $to_date = date('Y-m-d',strtotime($to_date));
        $id = uniqid();
        $time = time(); 
        $time = gmdate( "Y-m-d",$time )."T".gmdate( "H:i:s", $time ).'+05:30';
        $getGstprice = $this->gstPrice($bar_price);
        $bar_price = (float)$bar_price;
        $totalprice = (float)($bar_price + $getGstprice);
        // $multiple_days = '{"Sun":1,"Mon":1,"Tue":1,"Wed":0,"Thu":0,"Fri":1,"Sat":1}';
        $multiple_days = json_decode($multiple_days);
        foreach($multiple_days as $key => $days){
            if($key == 'Mon'){
                $Mon = $days;
            }
            if($key == 'Tue'){
               $Tue = $days;
            }
            if($key == 'Wed'){
               $Weds = $days;
            }
            if($key == 'Thu'){
               $Thur = $days;
            }
            if($key == 'Fri'){
               $Fri = $days;
            }
            if($key == 'Sat'){
               $Sat = $days;
            }
            if($key == 'Sun'){
               $Sun = $days;
            }
        }
        $xml_data = 
            '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                EchoToken="'.$id.'"
                                                TimeStamp="'.$time.'"
                                                Version="3.0"
                                                NotifType="Overlay"
                                                NotifScopeType="ProductRate">
            <POS>
                <Source>
                <RequestorID ID="bookingjini_ari"/>
                </Source>
            </POS>
            <RateAmountMessages HotelCode="'.$hotel_id.'">
                <RateAmountMessage>
                    <StatusApplicationControl Start="'.$from_date.'"
                                                    End="'.$to_date.'"
                                                    Mon="'.$Mon.'"
                                                    Tue="'.$Tue.'"
                                                    Weds="'.$Weds.'"
                                                    Thur="'.$Thur.'"
                                                    Fri="'.$Fri.'"
                                                    Sat="'.$Sat.'"
                                                    Sun="'.$Sun.'"
                                                    InvTypeCode="'.$room_type_id.'"
                                                    RatePlanCode="'.$rate_plan_id.'"/>
                    <Rates>
                        <Rate>
                            <BaseByGuestAmts>
                                <BaseByGuestAmt AmountBeforeTax="'.$bar_price.'"
                                                AmountAfterTax="'.$totalprice.'"
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
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)),true);
        if(isset($google_resp["Success"])){
            $resp = array('status'=>1,'message'=>'Rate updation successfully');
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0,'message'=>'Rate updation fails');
            return response()->json($resp);
        }
     }
     public function losUpdateToGoogleAds($hotel_id,$room_type_id,$rate_plan_id,$from_date,$to_date,$los,$multiple_days){
        $from_date = date('Y-m-d',strtotime($from_date));
        $to_date = date('Y-m-d',strtotime($to_date));
        $id = uniqid();
        $time = time(); 
        $time = gmdate( "Y-m-d",$time )."T".gmdate( "H:i:s", $time ).'+05:30';
        // $multiple_days = '{"Sun":1,"Mon":1,"Tue":1,"Wed":0,"Thu":0,"Fri":1,"Sat":1}';
        $multiple_days = json_decode($multiple_days);
        foreach($multiple_days as $key => $days){
            if($key == 'Mon'){
                $Mon = $days;
            }
            if($key == 'Tue'){
               $Tue = $days;
            }
            if($key == 'Wed'){
               $Weds = $days;
            }
            if($key == 'Thu'){
               $Thur = $days;
            }
            if($key == 'Fri'){
               $Fri = $days;
            }
            if($key == 'Sat'){
               $Sat = $days;
            }
            if($key == 'Sun'){
               $Sun = $days;
            }
        }
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
            <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                    EchoToken="'.$id.'"
                                    TimeStamp="'.$time.'"
                                    Version="3.0">
                <POS>
                    <Source>
                    <RequestorID ID="bookingjini_ari"/>
                    </Source>
                </POS>
                <AvailStatusMessages HotelCode="'.$hotel_id.'">
                        <AvailStatusMessage>
                        <StatusApplicationControl Start="'.$from_date.'"
                                                    End="'.$to_date.'"
                                                    Mon="'.$Mon.'"
                                                    Tue="'.$Tue.'"
                                                    Weds="'.$Weds.'"
                                                    Thur="'.$Thur.'"
                                                    Fri="'.$Fri.'"
                                                    Sat="'.$Sat.'"
                                                    Sun="'.$Sun.'"
                                                    InvTypeCode="'.$room_type_id.'"
                                                    RatePlanCode="'.$rate_plan_id.'"/>
                        <LengthsOfStay>
                        <LengthOfStay Time="'.$los.'"
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
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)),true);
        if(isset($google_resp["Success"])){
            $resp = array('status'=>1,'message'=>'los updation successfully');
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0,'message'=>'los updation fails');
            return response()->json($resp);
        }
     }
     public function gstPrice($bar_price){
        $percentage = 0;
        if($bar_price > 7500){
            $percentage = 18;
        }
        else if($bar_price > 1000 && $bar_price <= 7500){
            $percentage = 12;
        }
        $gstprice = $bar_price * $percentage/100;
        return $gstprice;
     }
}
