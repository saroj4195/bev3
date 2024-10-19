<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Invoice;
use App\User;
Use App\Booking;
use App\CmOtaDetails;
Use App\CrsBooking;
use App\CrsReservation;
use App\HotelInformation;
use DB;
use App\MailNotificationCrs;
use App\Http\Controllers\UpdateInventoryService;
use App\Http\Controllers\ManageCrsRatePlanController;
use App\Http\Controllers\InventoryService;
use App\MasterRoomType;
use App\MasterHotelRatePlan;
use App\IdsReservation;
use App\HotelBooking;
use App\RoomRateSettings;
use App\AdminUser;
use App\CompanyDetails;
use App\ImageTable;
use App\CmOtaBookingRead;
use App\CmOtaRatePlanSynchronizeRead;
use App\CmOtaRoomTypeSynchronizeRead;
use App\Http\Controllers\BookingEngineController;


class CrsBookingsTestController extends Controller
{
    private $rules=array(
        'from_date'=>'required',
        'to_date'=>'required',
        'hotel_id'=>'required'
    );
    private $message=[
        'from_date.required'=>'from date should be required',
        'to_date.required'=>'to date should be required',
        'hotel_id.required'=>'hotel_id should be required'
    ];
    protected $invService;
    protected $cmOtaBookingInvStatusService;
    public function __construct(InventoryService $invService,BookingEngineController $bookingEngineController)
    {
       $this->invService = $invService;
       $this->bookingEngineController = $bookingEngineController;
    }
    public function getHotelDetails($company_id, Request $request){
        $data =  $request->all();
        $getData = HotelInformation::where('company_id',$company_id)
        ->where('status',1)
        ->get();
        if(sizeof($getData)>0){
            $res = array('status'=>1,'message'=>'Hotel Details Retrived Successful','data'=>$getData);
            return $res;
        }
    }

    public function crsBookings(Request $request){
        $data = json_decode($request->getcontent());
        $crsBooking = new CrsBooking();
        $invoice = new Invoice();
        $invoice_id = $data[0]->invoice_id;
        $guest_details = $data[0]->guest_details;
        $adult = $data[0]->no_of_adult;
        $child = $data[0]->no_of_child;
        $internal_remark = $data[0]->internal_remark;
        $guest_remark = $data[0]->guest_remark;
        $valid_type = (int)$data[0]->valid_type;
        
        $valid_hour = (int)$data[0]->valid_hour;
        $payment_type = $data[0]->payment_type;
        $secure_hash = $data[0]->secure_hash;
        $booking_time = date("Y-m-d H:i:s");
        $expiry_time = date("Y-m-d H:i:s", strtotime('+'.$valid_hour. ' hours'));
        $mail_type = 'Confirmation';
        if($valid_type == 1){   // hours
            $expiry_time = date("Y-m-d H:i:s", strtotime('+'.$valid_hour. ' hours'));
        }elseif($valid_type == 2){ // days
            $expiry_time = date("Y-m-d H:i:s", strtotime('+'.$valid_hour. ' days'));
        }else{
            $expiry_time = '';
        }

        $get_details=Invoice::
        join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
        ->select('invoice_table.invoice_id','invoice_table.hotel_id', 'invoice_table.room_type','invoice_table.paid_amount',
        'invoice_table.check_in_out','invoice_table.booking_date','invoice_table.booking_status','invoice_table.invoice',
        'invoice_table.user_id', 'hotel_booking.room_type_id', 'hotel_booking.rooms', 'hotel_booking.check_in',
        'hotel_booking.check_out','invoice_table.total_amount','invoice_table.extra_details')
        ->where('invoice_table.invoice_id',$invoice_id)
        ->first();
        $extra_details = $get_details->extra_details;
        $array = json_decode($extra_details,true);
        $crsData = [
            'hotel_id' => $get_details->hotel_id,
            'invoice_id' => $invoice_id,
            'check_in' => $get_details->check_in,
            'check_out' => $get_details->check_out,
            'no_of_rooms' => $get_details->rooms,
            'room_type_id' => $get_details->room_type_id,
            'guest_name' => $guest_details,
            'adult' => $adult,
            'child' => $child,
            'payment_type' => $payment_type,
            'payment_link_status' => 'valid',
            'payment_status' => 'Confirm',
            'total_amount' => $get_details->total_amount,
            'booking_time' => $booking_time,
            'valid_hour' => $valid_hour,
            'validity_type' => $valid_type,
            'expiry_time' => $expiry_time,
            'booking_status' => 1,
            'updated_status' => 0,
            'secure_hash' => $secure_hash,
            'internal_remark' => $internal_remark,
            'guest_remark' => $guest_remark
        ];

        $insertData = $crsBooking::insert($crsData);
        if($insertData){
            $this->crsBookingMail($invoice_id,$payment_type,$mail_type);
            $res = array('status'=>1,'message'=>'CRS Bookings Data inserted successfully');
            return $res;
        }
    }

    public function crsBookingMail($invoice_id,$payment_type,$mail_type){
        $invoice= new Invoice();
        $getInvoice = $invoice::where('invoice_id',$invoice_id)->first();
        $id = $getInvoice->invoice_id;
        $secureHash='https://be.bookingjini.com/crs/payment/'.base64_encode($id);
        $booking_date = ('Y-m-d H:i:s');
       
        // $email=$email_id;//Agent or Corporate EMail id

        $details=Invoice::
        join('kernel.user_table as a','invoice_table.user_id','=','a.user_id')
        ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
        ->join('crs_booking','crs_booking.invoice_id','=','invoice_table.invoice_id')
        ->select('invoice_table.hotel_name','invoice_table.room_type','invoice_table.total_amount',
        'invoice_table.user_id','invoice_table.paid_amount','invoice_table.check_in_out',
        'invoice_table.booking_date','invoice_table.booking_status','a.first_name','invoice_table.invoice',
        'a.last_name','a.email_id','a.address','a.mobile','hotel_booking.check_in','hotel_booking.check_out',
        'hotel_booking.rooms','invoice_table.hotel_id','invoice_table.extra_details','crs_booking.guest_remark','invoice_table.discount_amount','invoice_table.tax_amount','crs_booking.adult','crs_booking.child','crs_booking.guest_name','crs_booking.valid_hour')
        ->where('invoice_table.invoice_id',$id)
        ->first();
        // $rate = split($details->room_type); 
        $hoteldetails=DB::table('kernel.hotels_table')->select('hotels_table.hotel_address','hotels_table.mobile','hotels_table.exterior_image','hotels_table.email_id','hotels_table.company_id','hotels_table.cancel_policy','hotels_table.check_in as check_in_time','hotels_table.check_out as check_out_time')->where('hotel_id',$details->hotel_id)->first();
        $image_id=explode(',', $hoteldetails->exterior_image);
        $images=ImageTable::select('image_name')->where('image_id',$image_id[0])->where('hotel_id',$details->hotel_id)->first();
        if($images)
        {
            $hotel_image=$images->image_name;
        }
        else
        {
            $hotel_image="";
        }
        $email_id=explode(',',$hoteldetails->email_id);

        if(is_array($email_id))
        {
            $hoteldetails->email_id=$email_id[0];
        }
        $mobile=explode(',',$hoteldetails->mobile);
        if(is_array($mobile))
        {
            $hoteldetails->mobile=$mobile[0];
        }

        $formated_invoice_id = date('dmy',strtotime($details->booking_date));
        $formated_invoice_id = $formated_invoice_id.$invoice_id;
        $name=explode(',',$details->guest_name);
        $email=$details->email_id;
        $total_adult = array_sum(explode(',',$details->adult));
        $total_child = array_sum(explode(',',$details->child));
        $no_of_nights = ceil(abs(strtotime($details->check_in) - strtotime($details->check_out)) / 86400) ;
        $booking_id     = date("dmy", strtotime($details->booking_date)).str_pad($invoice_id, 4, '0', STR_PAD_LEFT);
        $booking_info   = base64_encode($booking_id);
        $room_price     = abs($details->total_amount - $details->tax_amount);
        $room_type = trim($details->room_type,'["');
        $room_type = trim($room_type,'"]');
        // get rate plan
        preg_match_all("/\\((.*?)\\)/", $room_type, $matches); 
        $rate = $matches[1][0];
        // $room_type = trim($room_type,'"("'.$rate.'")"');
        $rate_plan = DB::table('kernel.rate_plan_table')->select('plan_name')->where('plan_type',$rate)->where('hotel_id',$details->hotel_id)->first();
        
        $supplied_details=array('invoice_id'=>$formated_invoice_id,'name'=>$name[0],'check_in'=>$details->check_in,'check_out'=>$details->check_out,'room_type'=>$room_type,'booking_date'=>$details->booking_date,'user_address'=>$details->address,'user_mobile'=>$details->mobile,'user_remark'=>$details->guest_remark,'hotel_display_name'=>$details->hotel_name,'hotel_address'=>$hoteldetails->hotel_address,'hotel_mobile'=>$hoteldetails->mobile,'image_name'=>$details->hotel_image,'total'=>$details->total_amount,'paid'=>$details->paid_amount,'room_price'=>$room_price,'discount'=>$details->discount_amount,'tax_amount'=>$details->tax_amount,'hotel_email_id'=>$hoteldetails->email_id,'user_email_id'=>$details->email_id,'url'=>$secureHash,'cancel_policy'=>$hoteldetails->cancel_policy,'total_adult'=>$total_adult,
        'total_child'=>$total_child,'check_in_time'=>$hoteldetails->check_in_time,'check_out_time'=>$hoteldetails->check_out_time,'booking_info'=>$booking_info,'no_of_nights'=>$no_of_nights,'rate_plan'=>$rate_plan->plan_name,'payment_type'=>$payment_type,'valid_hour'=>$details->valid_hour);
        $body           = $details->invoice;
        $body           = str_replace("#####", $booking_id, $body);
        
        if($mail_type == 'Confirmation'){
            if($this->sendMail($email,$supplied_details, "Booking Confirmation Voucher",$hoteldetails->email_id,$details->hotel_name,$mail_type)){
                $res=array('status'=>1,"message"=>'Mail invoice sent successfully');
                return response()->json($res);
            }
            else{
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Mail invoice sending failed";
                return response()->json($res);
            }
        }
        if($mail_type == 'Modification'){
            if($this->sendMail($email,$supplied_details, "Booking Modification Voucher",$hoteldetails->email_id,$details->hotel_name,$mail_type )){
                $res=array('status'=>1,"message"=>'Mail invoice sent successfully');
                return response()->json($res);
            }
            else{
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Mail invoice sending failed";
                return response()->json($res);
            }
        }
        if($mail_type == 'Cancellation'){
            if($this->sendMail($email,$supplied_details, "Booking Cancellation Voucher",$hoteldetails->email_id,$details->hotel_name,$mail_type )){
                $res=array('status'=>1,"message"=>'Mail invoice sent successfully');
                return response()->json($res);
            }
            else{
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Mail invoice sending failed";
                return response()->json($res);
            }
        }
    }

    public function sendMail($email,$supplied_details, $subject,$hotel_email,$hotel_name,$mail_type)
    {
        $data = array('email' =>$email,'subject'=>$subject);
        $data['hotel_name']=$hotel_name;
        if($hotel_email==""){
            $data['hotel_email']="";
            $mail_array=['ranjit.dash@5elements.co.in'];
        }
        else{
            $data['hotel_email']=$hotel_email;
            $mail_array=['ranjit.dash@5elements.co.in'];
        }
        $data['mail_array']=$mail_array;

        if($mail_type == 'Confirmation'){
            $template = 'emails.crsBookingConfirmationTemplate';
        }
        if($mail_type == 'Cancellation'){
            $template = 'emails.crsBookingCancellationTemplate';
        }
        if($mail_type == 'Modification'){
            $template = 'emails.crsBookingModificationTemplate';
        }
        
        try{
            Mail::send(['html' => $template],$supplied_details,function ($message) use ($data)
            {
                if($data['hotel_email']!="")
                {
                    $message->to($data['email'])
                    ->cc($data['hotel_email'])
                    ->bcc($data['mail_array'])
                    ->from( env("MAIL_FROM"), $data['hotel_name'])
                    ->subject( $data['subject']);
                }
                else
                {
                    $message->to($data['email'])
                    ->from( env("MAIL_FROM"), $data['hotel_name'])
                    ->subject( $data['subject']);
                }
            });
            if(Mail::failures())
            {
                return false;
            }
            return true;
        }
        catch(Exception $e){
        return true;
        }
    }

   // Cron Job with Booking Cancellation //
    /**
     * Author @Siri
     * This function is used as cronjob to check payment validity and cancel booking
     */
    public function crsBookingCronJob(Request $request){
        $crsBooking = new CrsBooking();
        $current_date_time = date("Y-m-d h:i:s");

        $getCrsData = $crsBooking::where('updated_status',0)->where('payment_type',1)->get();
        if(sizeof($getCrsData)>0){
            foreach($getCrsData as $data){
                if($data->paymet_link_status == 'invalid'){
                    return true;
                }else{
                    if($current_date_time >= $data->expiry_time){
                        $update_invalid = $crsBooking::where('invoice_id',$data->invoice_id)->update(['payment_link_status'=>'invalid',
                        'payment_status'=>'cancel','updated_status'=>2,'booking_status'=>3]);
                        //Cancel Booking
                        $booking_cancel = $this->cancelBooking($data->invoice_id,'cronjob');
                        return $booking_cancel;
                    }
                }
            }
        }
    }

    public function crsPayBooking($booking_id){
        $crsBooking = new CrsBooking();
        $current_date = date("Y-m-d h:i:s");
        $invoice_id = substr(base64_decode($booking_id), 6);
        $crs_details = $crsBooking::where('invoice_id',$invoice_id)->first();
        if($crs_details){
            if($crs_details->payment_link_status == 'invalid' || $current_date >= $crs_details->expiry_time){
                return '<!DOCTYPE html>
                          <html>
                          <head>
                          <title>Check Payment link</title>
                          </head>
                          <body style="background-color : gray; margin-top:200px; margin-left:400px; margin-right:400px;">
                          <div style="border:1px solid black; background-color :white; padding:50px;">
                            <p style="color:red; text-align: center">Sorry! Payment Link Has Expired!</p>
                          </div>
                          </body>
                          </html>';
            }
            else{
              $invoice_id = base64_encode($invoice_id);
              $payment_link = "https://be.bookingjini.com/payment/$invoice_id/$crs_details->secure_hash";
              $ch = curl_init();
              curl_setopt($ch, CURLOPT_URL, $payment_link);
              curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              $resp = curl_exec($ch);
              curl_close($ch);
              return $resp;
            }
        }
    }
    public function crsReservation(Request $request){
        $failure_message="Field should not be blank";
      
        $validator=Validator::make($request->all(),$this->rules,$this->message);
        if($validator->fails()){
           return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
        }
        $data=$request->all();
        $crs_reservation = array();
        $room_type_id_info = $data['room_type_id'];
        $hotel_id = $data['hotel_id'];
        $from_date = date('Y-m-d',strtotime($data['from_date'])); 
        $to_date = date('Y-m-d',strtotime($data['to_date']));
        $p_start = $from_date;
        $p_end = $to_date;
        $period     = new \DatePeriod(
            new \DateTime($p_start),
            new \DateInterval('P1D'),
            new \DateTime($p_end)
        );
        $get_room_no = MasterRoomType::select('total_rooms')
                       ->where('room_type_id',$room_type_id_info)
                       ->where('hotel_id',$hotel_id)
                       ->first();
        if(isset($get_room_no->total_rooms)){
            $room_number = $get_room_no->total_rooms;
        }
        $internalRemark ='NA';
        $guestRemark ='NA';
        $p = 1;
        $be_booking= DB::table('booking_engine.invoice_table')
        ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->join('booking_engine.hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
        ->select('company_table.commission','user_table.first_name', 'user_table.last_name',
                    'user_table.email_id','user_table.address',
                    'user_table.mobile','user_table.user_id','invoice_table.room_type',
                    'invoice_table.total_amount','invoice_table.paid_amount',
                    'invoice_table.booking_date','invoice_table.check_in_out',
                    'invoice_table.booking_status','invoice_table.tax_amount','invoice_table.booking_source','invoice_table.invoice_id','invoice_table.hotel_name','hotel_booking.hotel_booking_id','hotel_booking.check_in','hotel_booking.check_out','hotel_booking.rooms','hotel_booking.room_type_id'
                )
                ->where('invoice_table.hotel_id', '=', $hotel_id)
                ->whereBetween('hotel_booking.check_in', array($from_date, $to_date))
                ->where('invoice_table.booking_status', '=', 1)
                ->orWhere(function($query) use ($hotel_id,$from_date,$to_date){
                    $query->where('invoice_table.hotel_id', '=', $hotel_id)
                    ->where('hotel_booking.check_in','<',$from_date)
                    ->where('hotel_booking.check_out','>',$from_date)
                    ->where('invoice_table.booking_status', '=', 1);
                    })
                ->groupBy('invoice_table.invoice_id')
                ->orderBy('invoice_table.invoice_id','DESC')
                ->get();
                
        foreach($be_booking as $data_up){
          $room_dlt = json_decode($data_up->room_type);
          $room_dlt = substr($room_dlt[0],2);
          $data_up->invoice_id_details=$data_up->invoice_id;
          $bk_id = $data_up->invoice_id;
          $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
          $data_up->invoice_id     =  $booking_id;
          if($data_up->booking_source == 'CRS'){
            $crs_booker_name = CrsBooking::select('*')->where('invoice_id',$bk_id)->where('hotel_id',$hotel_id)->first();
            if($crs_booker_name){
                if(isset($crs_booker_name->guest_name)){
                    $crs_user_info = explode(',',$crs_booker_name->guest_name);
                }
                $internalRemark = $crs_booker_name->internal_remark;
                $guestRemark = $crs_booker_name->guest_remark;
            }
          }
          if($data_up->check_in < $from_date){
            $check_in = date('Y-m-d',strtotime($from_date));
          }
          else{
            $check_in = date('Y-m-d',strtotime($data_up->check_in));
          }
          
          $check_out = date('Y-m-d',strtotime($data_up->check_out));
      
          for($i = 0; $i < $data_up->rooms; $i++){
            if($data_up->booking_source == 'CRS'){
                if(isset($crs_user_info[$i])){
                    $user_name = $crs_user_info[$i];
                }
                else{
                    $user_name = $data_up->first_name.' '.$data_up->last_name;
                } 
            }
            else{
                $user_name = $data_up->first_name.' '.$data_up->last_name;
            }
            if($room_type_id_info == $data_up->room_type_id){
                $user_name_disp = $data_up->first_name.' '.$data_up->last_name;
                $be_booking_array = array(
                    "id" => $p,
                    "booking_id"=>$bk_id,
                    "first_name"=>$data_up->first_name,
                    "last_name"=>$data_up->last_name,
                    "text"=>strtoupper($user_name),
                    "username"=>strtoupper($user_name_disp),
                    "bubbleHtml"=> "Reservation details: <br/>$user_name",
                    "email"=>$data_up->email_id,
                    "address"=>$data_up->address,
                    "contact"=>$data_up->mobile,
                    "user_id"=>$data_up->user_id,
                    "room_type"=>$room_dlt,
                    "paid"=>$data_up->total_amount,
                    "paid_amount"=>$data_up->paid_amount,
                    "booking_date"=>date('d M Y',strtotime($data_up->booking_date)),
                    "tax_amount"=>$data_up->tax_amount,
                    "start"=>$check_in,
                    "end"=>$check_out,
                    "check_in"=>date('d M Y',strtotime($check_in)),
                    "check_out"=>date('d M Y',strtotime($check_out)),
                    "check_in_dis"=>date('Y-m-d',strtotime($check_in)),
                    "check_out_dis"=>date('Y-m-d',strtotime($check_out)),
                    "status"=>'Confirm',
                    "booking_source"=>$data_up->booking_source,
                    "invoice_id"=>$data_up->invoice_id,
                    "room_type_id"=>$data_up->room_type_id,
                    "hotel_name"=>$data_up->hotel_name,
                    "hotel_booking_id"=>$data_up->hotel_booking_id,
                    "rooms_qty"=>1,
                    "payment_status"=>'paid',
                    "disp_booking_id"=>$data_up->invoice_id,
                    "internal_remark"=>$internalRemark,
                    "guest_remark"=>$guestRemark
                    );
                    array_push($crs_reservation,$be_booking_array);
                    $p++;
                }
            }
        }
        $ota_bookings=  CmOtaBookingRead::select(DB::raw("distinct ota_id,hotel_id,unique_id,customer_details,booking_status,channel_name,payment_status,inclusion,no_of_adult,no_of_child,rooms_qty,room_type,checkin_at,
        tax_amount,checkout_at,booking_date,rate_code,amount,currency,payment_status,confirm_status,cancel_status,ip,ids_re_id"))
        ->where('hotel_id', '=', $hotel_id)
        ->whereBetween('checkin_at', array($from_date, $to_date))
        ->where('cancel_status', '=', 0) 
        ->where('confirm_status', '=', 1) 
        ->orWhere(function($query) use ($hotel_id,$from_date,$to_date){
            $query->where('hotel_id', '=', $hotel_id)
            ->where('checkin_at','<',$from_date)
            ->where('checkout_at','>',$from_date)
            ->where('cancel_status', '=', 0) 
            ->where('confirm_status', '=', 1);
            })
        ->orderBy('unique_id','DESC')
        ->get();
        foreach($ota_bookings as $ota_booking_data){
            $ota_booking_data->inclusion = explode(',',$ota_booking_data->inclusion);
            $customer_data=explode(',',$ota_booking_data->customer_details);
            $ota_booking_data->username=$customer_data[0];
            if(isset($customer_data[1])){
                $ota_booking_data->email=$customer_data[1];
            }
            else{
                $ota_booking_data->email='NA';
            }
            if(isset($customer_data[2])){
                $ota_booking_data->contact=$customer_data[2];
            }
            else{
                $ota_booking_data->contact='NA';
            }
            $adult_data=explode(',',$ota_booking_data->no_of_adult);
            $child_data=explode(',',$ota_booking_data->no_of_child);
            $rate_code=$this->getRate_plan($ota_booking_data->room_type,$ota_booking_data->ota_id,$ota_booking_data->rate_code);
            $room_type=$this->getRoom_types($ota_booking_data->room_type,$ota_booking_data->ota_id);
            $room_type_id=$this->getRoom_types_id($ota_booking_data->room_type,$ota_booking_data->ota_id);
            if($ota_booking_data->checkin_at < $from_date){
                $check_in = date('Y-m-d',strtotime($from_date));
              }
              else{
                $check_in = date('Y-m-d',strtotime($ota_booking_data->checkin_at));
              }
            $check_out = date('Y-m-d',strtotime($ota_booking_data->checkout_at));
           
            foreach($room_type as $key => $rms){
                if($room_type_id_info == $room_type_id[$key]){
                    $ota_booking_array = array(
                        "id" => $p,
                        "booking_id"=>$ota_booking_data->unique_id,
                        "customer_details"=>$ota_booking_data->customer_details,
                        "status"=>$ota_booking_data->booking_status,
                        "booking_source"=>$ota_booking_data->channel_name,
                        "no_of_adult"=>$adult_data[$key],
                        "no_of_child"=>$child_data[$key],
                        "rooms_qty"=>$ota_booking_data->rooms_qty,
                        "room_type"=>$rms,
                        "room_type_id"=>$room_type_id[$key],
                        "start"=>$check_in,
                        "end"=>$check_out,
                        "check_in_dis"=>date('Y-m-d',strtotime($check_in)),
                        "check_out_dis"=>date('Y-m-d',strtotime($check_out)),
                        "check_in"=>date('d M Y',strtotime($check_in)),
                        "check_out"=>date('d M Y',strtotime($check_out)),
                        "tax_amount"=>$ota_booking_data->tax_amount,
                        "booking_date"=>date('d M Y',strtotime($ota_booking_data->booking_date)),
                        "rate_code"=>$rate_code[$key],
                        "paid"=> $ota_booking_data->amount, 
                        "paid_amount"=>$ota_booking_data->amount,     
                        "text"=>strtoupper($ota_booking_data->username),
                        "username"=>strtoupper($ota_booking_data->username),
                        "bubbleHtml"=> "Reservation details: <br/>$ota_booking_data->username",
                        "email"=>$ota_booking_data->email,
                        "contact"=>$ota_booking_data->contact,
                        "payment_status"=> $ota_booking_data->payment_status,
                        "disp_booking_id"=>$ota_booking_data->unique_id,
                        "internal_remark"=>'NA',
                        "guest_remark"=>'NA'
                    );
                    array_push($crs_reservation,$ota_booking_array);
                    $p++;
                }
                    
            }
        }
        $crs_reservation = $this->msort($crs_reservation, array('start','booking_date'));
        $resource_alocater = array();
        $startArray = array();
        $endArray = array();
        $j = 1;
        foreach($period as $key1 => $value ){
            $index = $value->format('Y-m-d');
            foreach($crs_reservation as $key => $val){
                if($val['start'] == $index){
                    if(in_array($val['start'],$endArray)){
                        foreach($resource_alocater as $ind => $info){
                            if($info['date'] == $val['start']){
                                $resource_no = $info['resource'];
                                $crs_reservation[$key]['resource'] = $resource_no;
                                $resource_alocater[] = array('date'=>$val['end'],'resource'=>$resource_no);
                                unset($resource_alocater[$ind]);
                                $end_ind = array_search($val['start'],$endArray);
                                unset($endArray[$end_ind]);
                                break;
                            }
                        } 
                    }
                    else{
                        if(in_array($val['start'],$startArray)){
                            $crs_reservation[$key]['resource'] = $j;
                            $resource_alocater[] = array('date'=>$val['end'],'resource'=>$j);
                            $j++;
                        }
                        else{
                            $j = 1;
                            $crs_reservation[$key]['resource'] = $j;
                            $resource_alocater[] = array('date'=>$val['end'],'resource'=>$j);
                            $j++;

                        }
                    }
                    $startArray[] = $val['start'];
                    $endArray[]   = $val['end'];
                    $crs_reservation[$key]['start'] = date("Y-m-d\Th:i:s",strtotime($val['start']));
                    $crs_reservation[$key]['end'] =date("Y-m-d\Th:i:s",strtotime($val['end']));
                }
            }
        }
        if($crs_reservation){
            $res=array('status'=>1,"message"=>'Bookings retrieve successfully','data'=>$crs_reservation);
            return response()->json($res);
        }
        else{
            $res=array('status'=>1,"message"=>'Bookings retrieve fails');
            return response()->json($res);
        }
    }
    public function msort($array, $key, $sort_flags = SORT_REGULAR) {
        if (is_array($array) && count($array) > 0) {
            if (!empty($key)) {
                $mapping = array();
                foreach ($array as $k => $v) {
                    $sort_key = '';
                    if (!is_array($key)) {
                        $sort_key = $v[$key];
                    } else {
                        foreach ($key as $key_key) {
                            $sort_key .= $v[$key_key];
                        }
                        $sort_flags = SORT_STRING;
                    }
                    $mapping[$k] = $sort_key;
                }
                asort($mapping, $sort_flags);
                $sorted = array();
                foreach ($mapping as $k => $v) {
                    $sorted[] = $array[$k];
                }
                return $sorted;
            }
        }
        return $array;
      } 
    public function getRoom_types_id($room_type,$ota_id)
    {
    $cmOtaRoomTypeSynchronize= new CmOtaRoomTypeSynchronizeRead();
    $room_types=explode(',',$room_type);
    $hotel_room_type_id=array();
    foreach($room_types as $ota_room_type)
    {
        $room_id=$cmOtaRoomTypeSynchronize->getRoomTypeId($ota_room_type,$ota_id);
        if($room_id === 0)
        {
        array_push($hotel_room_type_id,"Room type is not synced with OTA");
        }
        else
        {
        array_push($hotel_room_type_id,$room_id);
        }
    }
    return $hotel_room_type_id;
    }
    public function getRoom_types($room_type,$ota_id)
  {
    $cmOtaRoomTypeSynchronize= new CmOtaRoomTypeSynchronizeRead();
    $room_types=explode(',',$room_type);
    $hotel_room_type=array();
    foreach($room_types as $ota_room_type)
    {
      $room=$cmOtaRoomTypeSynchronize->getRoomType($ota_room_type,$ota_id);
      if($room === 0)
      {
        array_push($hotel_room_type,"Room type is not synced with OTA");
      }
      else
      {
        array_push($hotel_room_type,$room);
      }
    }
    return $hotel_room_type;
  }
  public function getRate_plan($ota_room_type,$ota_id,$rate_plan_id)
  {
    $cmOtaRatePlanSynchronize= new CmOtaRatePlanSynchronizeRead();
    $rate_plan_ids=explode(',',$rate_plan_id);
    $hotel_rate_plan=array();
    foreach($rate_plan_ids as $ota_rate_plan_id)
    {
     array_push($hotel_rate_plan,$cmOtaRatePlanSynchronize->getRoomRatePlan($ota_id,$ota_rate_plan_id));
    }

    return $hotel_rate_plan;
  }
  public function getTotalInvByHotel($hotel_id ,$date_from ,$date_to,$mindays,Request $request)
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
    for ($i=$from; $i<=$to; $i+=86400) {
        $dif_dates[$j]= date("Y-m-d", $i);
        $j++;
    }
    $room_types=MasterRoomType::select('room_type','room_type_id')->where($conditions)->orderBy('room_type_table.room_type_id','ASC')->get();
    if($room_types)
    {
        foreach($room_types as $key => $room)
        {
            $k=0;
            $data=$this->invService->getInventeryByRoomTYpe($room['room_type_id'],$date_from, $date_to, $mindays);
            $room['inv']=$data;
            for($i=0;$i<$diff;$i++)
            {
                $count[$k]=$room['inv'][$i]['no_of_rooms'];
                $k++;
            }
            $count_inv[$key] = $count;
        }
        $res=array('status'=>1,'message'=>"Total inventory number retrieved successfully ",'count'=>$count_inv);
        return response()->json($res);
    }
    else
    {
        $res=array('status'=>0,'message'=>"Total inventory number retrieval failed");
    }
  }
  public function getRoomDetails($hotel_id,$room_type_id_info){
        $get_room_no = MasterRoomType::select('total_rooms')
        ->where('room_type_id',$room_type_id_info)
        ->where('hotel_id',$hotel_id)
        ->first();
        $room_number = $get_room_no->total_rooms;
        $row_wise_room = array();
        for($k = 1; $k <= $room_number; $k++){
            $room_details = array(
                "id"=>$k,
                "name"=>"Room$k"
            );
            array_push($row_wise_room,$room_details);
        }
        if($row_wise_room){
            $res=array('status'=>1,"message"=>'Bookings retrieve successfully','data'=>$row_wise_room);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,"message"=>'Bookings retrieve fails');
            return response()->json($res);
        }
  }
   // CRS Cancellation Booking //
    /**
     * @author siri date:
     * This function is used for Booking cancellation by frontend
     */
    public function crsCancelBooking(Request $request){
        $data = $request->all();
        $invoice_id = substr($data['invoice_id'],6);
        $res = $this->cancelBooking($invoice_id,'cancel');
        return $res;
    }

    /**
     * Author @Siri
     * This function is used for Booking cancellationa and in Booking expiry i.e by cronjob
     */
    public function cancelBooking($invoiceid,$type){
        $crsBooking = new CrsBooking();
        $invoice =  new Invoice();
        $hotel_booking = new HotelBooking();
        $room_types = array();
        $invoice_id = $invoiceid;
        $mail_type = 'Cancellation';
        
        $get_booking_details = $hotel_booking::where('invoice_id',$invoice_id)->get();
        if(sizeof($get_booking_details)>0){
            foreach($get_booking_details as $data){
                $hotel_id = $data['hotel_id'];
                $booking_details['checkin_at'] = $data['check_in'];
                $booking_details['checkout_at'] = $data['check_out'];
                $room_type[] = $data['room_type_id'];
                $rooms_qty[] = $data['rooms'];
                $ids_id = $data['ids_re_id'];
            }
            if($type == 'cancel')
            { 
                // cancel data update in IDS
                try{
                    $url = 'https://cm.bookingjini.com/crs_cancel_push_to_ids';
                    $ch = curl_init();
                    curl_setopt( $ch, CURLOPT_URL, $url );
                    curl_setopt( $ch, CURLOPT_POST, true );
                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $invoice_id);
                    $status = curl_exec($ch);
                    curl_close($ch);
                    $ids = $status;
                }
                catch(Exception $e){
                $error_log = new ErrorLog();
                $storeError = array(
                    'hotel_id'      => $inv_data['hotel_id'],
                    'function_name' => 'IdsXmlCreationAndExecutionController.pushIdsCrs',
                    'error_string'  => $e
                );
                $insertError = $error_log->fill($storeError)->save();
                }
            }else{
                $ids = $ids_id;
            }
            $update_invoice_booking = $invoice::where('invoice_id',$invoice_id)->update(['booking_status'=>3,'ids_re_id'=>$ids]);
            $update_hotel_booking = $hotel_booking::where('invoice_id',$invoice_id)->update(['booking_status'=>3]);
            // $refund = $this->crsCancelRefund($invoice_id);
            $update_invalid = $crsBooking::where('invoice_id',$invoice_id)->update(['booking_status'=>3,'payment_link_status'=>'invalid','payment_status'=>'cancel','updated_status'=>1]);
            $cancel = $this->bookingCancel($invoice_id,$hotel_id,$booking_details,$room_type,$rooms_qty); 
            if($cancel == 1){
                if($type != 'modify') { //mail only for cancellation
                    $this->crsBookingMail($data->invoice_id,0,$mail_type);
                } 
                $res=array('status'=>1,"message"=>'Booking Cancellation Successful');
                return response()->json($res); 
            }else{
                $res=array('status'=>1,"message"=>'Booking Cancellation Failed');
                return response()->json($res);
            }
        }
    }

    public function bookingCancel($invoice_id,$hotel_id,$booking_details,$room_type,$rooms_qty){
        $booking_status = 'Cancel';
        $ota_id = 0; // Crs as BookingEngine
        $rooms = array();
        $ota_hotel_details = new CmOtaDetails();
     
        for($i=0;$i<sizeof($room_type);$i++){
            $get_ota_room = CmOtaRoomTypeSynchronizeRead::where('hotel_id',$hotel_id)->where('room_type_id',$room_type[$i])->first();
           
            $ota_room_type[] = $get_ota_room->ota_room_type;
            $ota_type_id = $get_ota_room->ota_type_id;
        }
        $ota_room_type = implode(',',$ota_room_type);
        $rooms_qty = implode(',',$rooms_qty);
        
        // //update inventory in cm_ota_inv_status
        // $cancel_booking = $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($invoice_id,$ota_id,$hotel_id,$booking_details['checkin_at'],$booking_details['checkout_at'],$ota_room_type,$booking_status,$rooms_qty);
        // if($cancel_booking){
        //     $ota_hotel_details['ota_id'] = $ota_type_id;
        //     $ota_hotel_details['hotel_id'] = $hotel_id;
            
        //     //update in bucket data (cm_ota_booking_push_bucket)
        //     $this->instantBucketController->bucketEngineUpdate($booking_status,'Bookingjini',$ota_hotel_details,$invoice_id);
        // }
        // return $cancel_booking;

        try{
            $cmOtaBookingInvStatusService = array('invoice_id'=>$invoice_id,'ota_id'=>$ota_id,'hotel_id'=>$hotel_id,'check_in'=>$booking_details['checkin_at'],'check_out'=>$booking_details['checkout_at'],'room_type'=>$ota_room_type,'booking_status'=>$booking_status,'room_qty'=>$rooms_qty,'ota_type_id'=>$ota_type_id);
            $cmOtaBookingInvStatusPush = http_build_query($cmOtaBookingInvStatusService);
            $url = 'https://cm.bookingjini.com/cm_ota_booking_inv_status';
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $cmOtaBookingInvStatusPush);
            $status = curl_exec($ch);
            curl_close($ch);
            return $status;
        }
        catch(Exception $e){
          $error_log = new ErrorLog();
           $storeError = array(
              'hotel_id'      => $inv_data['hotel_id'],
              'function_name' => 'CmOtaBookingInvStatusService.saveCurrentInvStatus',
              'error_string'  => $e
           );
           $insertError = $error_log->fill($storeError)->save();
        }
    }
     // CRS Modification Booking //
    /**
     * @author Siri
     * Dt: 30-01-2021
     * This function is used for Modify CRS Booking
     */
    public function crsModifyBooking(Request $request)
    {
        $invoice =  new Invoice();
        $hotel_booking = new HotelBooking();
        $data = $request->all();
        $invoice_id = substr($data['invoice_id'], 6);
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $rate_plan_id = $data['rate_plan_id'];
        $modify_check_in = $data['check_in'];
        $modify_check_out = $data['check_out'];
        $modified_date = date('Y-m-d H:i:s');
        $guest_name = $data['guest_name'];
        $no_of_rooms = $data['rooms'];
        $gust_info_arr = explode(',',$guest_name);
        if(sizeof($gust_info_arr) != $no_of_rooms){
            $res=array('status'=>0,"message"=>"Please provide guest details same as number of room");
            return $res;
        }
        $total_room_price = 0;
        $total_amount = 0;
        $total_extra_adult = 0;
        $total_extra_child = 0;
        $ext_adult_price = 0;
        $ext_child_price = 0;
        $extra_details_arr=array();
        $mail_type = 'Modification';
        $no_of_nights = abs(strtotime($modify_check_in) - strtotime($modify_check_out)) / 86400 ;
        
        $previous_booking = CrsBooking::join('invoice_table','invoice_table.invoice_id','=','crs_booking.invoice_id')
        ->select('discount_amount','paid_amount','extra_details','user_id','crs_booking.no_of_rooms','crs_booking.room_type_id')
        ->where('invoice_table.invoice_id',$invoice_id)->where('invoice_table.hotel_id',$hotel_id)->first();
        $previous_rooms = $previous_booking->no_of_rooms;
        $roomtype = $previous_booking->room_type_id;
        $extra_details = json_decode($previous_booking->extra_details, true);
        for($i=0;$i<sizeof($extra_details);$i++){
            $keys=array_keys($extra_details[$i]);
            $adult[] = (int)$extra_details[$i][$keys[0]][0];
            $child[] = (int)$extra_details[$i][$keys[0]][1]; 
        }
        
        //cancel previous booking details with invoice id
        $cancel_previous_booking = $this->cancelBooking($invoice_id,'modify');
        if($cancel_previous_booking){

            //get inventory & rates data by check-in and check-out dates
            $hotel_data = DB::table('kernel.hotels_table')->where('hotel_id',$hotel_id)->first();
            $room_rate_details = $this->bookingEngineController->getInvByHotel((string)$hotel_data->company_id,$hotel_id,$modify_check_in,$modify_check_out,'INR',$request);
            $room_data=$room_rate_details->getData();
            
            for($i=0;$i<sizeof($room_data->data);$i++){ // for each room type array

                if($room_type_id == $room_data->data[$i]->room_type_id){ // check selected room type
                   $room_type_name  = $room_data->data[$i]->room_type;
                   $max_adult = $room_data->data[$i]->max_people;
                   $max_child = $room_data->data[$i]->max_child;
                   $max_room_capacity = $room_data->data[$i]->max_room_capacity;
                    for($j=0;$j<sizeof($room_data->data[$i]->rate_plans);$j++){ // for each rate plans of selected room
                       
                        if($rate_plan_id == $room_data->data[$i]->rate_plans[$j]->rate_plan_id){   // check selected rate plan 
                            $plan_type = $room_data->data[$i]->rate_plans[$j]->plan_type;
                            $plan_name = $room_data->data[$i]->rate_plans[$j]->plan_name;
                            for($k=0;$k<sizeof($room_data->data[$i]->rate_plans[$j]->rates);$k++){
                                $date[] = $room_data->data[$i]->rate_plans[$j]->rates[$k]->date;
                                $bar_price[] = $room_data->data[$i]->rate_plans[$j]->rates[$k]->bar_price;
                                $extra_adult_price = $room_data->data[$i]->rate_plans[$j]->rates[$k]->extra_adult_price;
                                $extra_child_price = $room_data->data[$i]->rate_plans[$j]->rates[$k]->extra_child_price;
                            }
                        }
                   }
                }
            }

            //-------concat previous extra deatils to modified extra details--------//
            if($no_of_rooms > $previous_rooms){
                for($j=0;$j<$no_of_rooms-$previous_rooms;$j++){
                    $adult[] = $max_adult;
                    $child[] = $max_child;  
                }
            }
            
            //-------Calculate Extra Child & Extra Adult if any present on previous booking----------//
            for($i=0;$i<sizeof($extra_details);$i++){
                $keys=array_keys($extra_details[$i]);
                $ext_adult_price += $extra_adult_price * ($extra_details[$i][$keys[0]][0] - $max_adult);
                $ext_child_price += $extra_child_price * ($extra_details[$i][$keys[0]][1] - $max_child); 
            }
            $ext_adult_price = $no_of_nights * $ext_adult_price;
            $ext_child_price = $no_of_nights * $ext_child_price;
           
            //---------Calculate Total Amount-------//
            for($i=0;$i<sizeof($date);$i++){
                $total_room_price += $no_of_rooms * $bar_price[$i];
            }
            
            $total_amount = $total_room_price + $ext_adult_price + $ext_child_price; //total amount
            
            // get gst percent
            if ($total_amount >= 0 && $total_amount <= 1000) {
                $gst_percent =  0;
            }
            else if ($total_amount > 1000 && $total_amount <= 7500) {
                $gst_percent = 12;
            }
            else if ($total_amount > 7500) {
                $gst_percent = 18;
            }
            //-----calculate total amount with gst--------//
            $gst_amount = ($total_amount *  $gst_percent) / 100 ;
            $total_amount_gst =  $total_amount + $gst_amount;
            
            //--------------update invoice_table-------------//
            $invoice_room_type = $invoice_room_type = '["'.$no_of_rooms.' '.$room_type_name.'('.$plan_type.')'.'"]';
            $check_in_out = '['.$modify_check_in.'-'.$modify_check_out.']';
            for($i=0;$i<$no_of_rooms;$i++){
                array_push($extra_details_arr,array( $room_type_id=>array($adult[$i],$child[$i])));  //---create array of extra details with modified room type--//
            }
            $update_invoice = Invoice::where('invoice_id',$invoice_id)->where('hotel_id',$hotel_id)->update(['check_in_out'=>$check_in_out,'room_type'=>$invoice_room_type,'total_amount'=>$total_amount_gst,'tax_amount'=>$gst_amount,'booking_date'=>$modified_date,'booking_status'=>1,'extra_details'=>json_encode($extra_details_arr)]);
            
            //-------------update hotel_booking table---------//
            $update_htl_bkng = HotelBooking::where('invoice_id',$invoice_id)->where('hotel_id',$hotel_id)->update(['rooms'=>$no_of_rooms,'room_type_id'=>$room_type_id,'check_in'=>$modify_check_in,'check_out'=>$modify_check_out,'booking_status'=>1]);

            //-------------update crs_booking table-----------//
            if($guest_name){
                $gest_info = $guest_name;
                $update_crs_bkng = CrsBooking::where('invoice_id',$invoice_id)->where('hotel_id',$hotel_id)->update(['check_in'=>$modify_check_in,'check_out'=>$modify_check_out,'no_of_rooms'=>$no_of_rooms,'room_type_id'=>$room_type_id,'total_amount'=>$total_amount_gst,'booking_status'=>1,'adult'=>array_sum($adult),'child'=>array_sum($child),'guest_name'=>$gest_info]);
            }
            else{
                 $update_crs_bkng = CrsBooking::where('invoice_id',$invoice_id)->where('hotel_id',$hotel_id)->update(['check_in'=>$modify_check_in,'check_out'=>$modify_check_out,'no_of_rooms'=>$no_of_rooms,'room_type_id'=>$room_type_id,'total_amount'=>$total_amount_gst,'booking_status'=>1,'adult'=>array_sum($adult),'child'=>array_sum($child)]);
            }
            
           if($update_invoice && $update_htl_bkng && $update_crs_bkng)
           {
                $cart_rooms = array();
                $carts = array(
                    "room_type"     => $room_type_name,
                    "plan_type"     => $plan_type,
                    "room_type_id"  => $room_type_id,
                    "discounted_price"  => (int)$previous_booking->discount_amount,
                    "paid_amount"   => (int)$previous_booking->paid_amount,
                    "rate_plan_id"  => $rate_plan_id,
                    "plan_name"     =>  $plan_name,
                    "display_price" => $total_room_price,
                    "price"         => $total_room_price,
                    "paid_amount_per"   =>"",
                    "partial_amount_per"=>100,
                    "rates_for_coupons" =>[],
                    "added_to_cart" => true,
                    "add_room"      => false,
                    "max_room_capacity" => $max_room_capacity,
                    "max_child"     => $max_child,
                    "max_people"    => $max_adult,
                    "extra_person"  => 0);

                    for($i=0;$i<$no_of_rooms;$i++){
                        $extadult = $extra_adult_price * ($adult[$i] - $max_adult);
                        $extchild = $extra_child_price * ($child[$i] - $max_child);
                        $cart_room[] = [
                                "room" => "Room".$i,
                                "selected_adult" => $adult[$i],
                                "selected_child" => $child[$i],
                                "rate_plan_id" => $rate_plan_id,
                                "extra_adult_price" => $extadult,
                                "extra_child_price" => $extchild,
                                "bar_price" => $total_room_price,
                                "selected_infant" => 0
                        ];
                        $cart_rooms['rooms'] =  $cart_room;
                    }
                    
                    $cart_tax = [
                        "tax" => [
                            "gst_price" => $gst_amount,
                            "other_tax" => []
                        ]];
                    $all_cart[] = array_merge($carts,$cart_rooms,$cart_tax);
                    $inv_data=$this->bookingEngineController->handleIds($all_cart,$modify_check_in,$modify_check_out,$modified_date,$hotel_id,$previous_booking->user_id,'Modify');
                    $update_ids_id = Invoice::where('hotel_id',$hotel_id)->where('invoice_id',$invoice_id)->update(['ids_re_id'=>$inv_data]);
                    $res = $this->bookingEngineController->gemsBooking($invoice_id,'crs',$request)->getData();
                    if($res->status == 1){
                        $this->crsBookingMail($invoice_id,0,$mail_type);
                        $res=array('status'=>1,"message"=>'Booking Modified Successful',"id"=>$inv_data);
                        return response()->json($res); 
                    }
           } 
        }
    }
    public function crsRegisterUserModify(Request $request){
        $data = $request->all();
        $user_id = $data['user_id'];
        $update = [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'company_name' => $data['company_name'],
            'email_id'   => $data['email_id'],
            'mobile' => $data['mobile'],
            'GSTIN'  => $data['GST_IN'],
            'address'  => $data['address']
        ];
        $update_user = User::where('user_id',$user_id)->update($update);
        if($update_user){
            $res = array('status'=>1,'message'=>'User Data Modified Successful');
            return response()->json($res); 
        }
    }
      // CRS Cancellation Refund Amount //
    /**
     * @author Siri
     * Dt: 02-02-2021
     * This function is used to get the refund amount by cancel booking depend upon the days befor checkin date */    
    public function crsCancelRefund($invoice_id){
        $get_today = date("Y-m-d");
        $ref_per = 0;
        $refund_amount = 0;
        $crs_booking_data = Invoice::join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')->select('hotel_booking.check_in','invoice_table.total_amount','invoice_table.hotel_id')->where('hotel_booking.invoice_id',$invoice_id)->first();
        if($crs_booking_data){
            $check_in = $crs_booking_data->check_in;
            $total_amount = $crs_booking_data->total_amount;
            $hotel_id = $crs_booking_data->hotel_id;
        }
        if($check_in >= $get_today){
            $days = abs(strtotime($check_in) - strtotime($get_today)) / 86400;
            if($days >= 0){
                $get_refund_days = CancellationPolicy::where('hotel_id',$hotel_id)->first();
                if($get_refund_days){
                    $closest = null;
                    $refund_days = $get_refund_days->policy_data;
                    $refund_days = trim(trim($refund_days,'['),']');
                    $refund_days = explode(',',$refund_days);
                    for($i=0;$i<sizeof($refund_days);$i++){
                        $ref_data = explode(':',$refund_days[$i]);
                        $ref_per = $ref_data[1];
                        $daterange = explode('-',$ref_data[0]);
                        if($days  >= $daterange[1] && $days  <= $daterange[0]){
                            $refund_amount = $total_amount * ( $ref_per / 100);
                        }
                    }
                }else{
                    $refund_amount = 0;
                }
            }else{
                $refund_amount = 0;
            }
        }
        return $refund_amount;
    }
    // CRS Cancel Booking Report //
    /**
     * @author Siri
     * Dt: 02-02-2021
     * This function is used to get the Crs Cancellation Booking Report Data 
     */
    public function crsCacelReportData(Request $request){
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $crs_data_array = array();
        $crs_data = array();
        $date = "01-".$data['date'];
        $date=date('Y-m-d',strtotime($date));
        $last_date=date('Y-m-t',strtotime($date));
        $get_data = CrsBooking::join('hotel_booking','crs_booking.invoice_id','=','hotel_booking.invoice_id')
        ->join('kernel.user_table','kernel.user_table.user_id','=','hotel_booking.user_id')
        ->join('kernel.room_type_table','kernel.room_type_table.room_type_id','=','hotel_booking.room_type_id')
        ->select(DB::raw('SUBSTRING_INDEX(crs_booking.guest_name,",",1) AS guest_name'),'kernel.user_table.address','hotel_booking.booking_date','hotel_booking.check_in',
        'hotel_booking.check_out','kernel.room_type_table.room_type','crs_booking.total_amount','crs_booking.updated_at','crs_booking.refund_amount')->where('hotel_booking.booking_date','>=',$date)->where('hotel_booking.booking_date','<=',$last_date)
        ->where('crs_booking.hotel_id',$hotel_id)->where('crs_booking.booking_status',3)->orderBY('hotel_booking.booking_date','desc')->paginate(8);
        $res = array('status'=>1,'message'=>'Data Retrived Successful','data'=>$get_data);
        return response()->json($res);
    }
    /**
     * CRS number wise user information retrival
     * @author Ranjit date: 21-10-2021
     */

     Public function getNumberWiseUser(Request $request){
        $data = $request->all();
        $mobile_number = $data['mobile_number'];
        $mobile_number1 = str_replace('+91','',$data['mobile_number']);
        $company_id = $data['company_id'];
        $getUserInfo = User::select('first_name','last_name','email_id','company_name','address','GSTIN')
                        ->where('mobile',$mobile_number)
                        ->orWhere('mobile',$mobile_number1)
                        ->where('company_id',$company_id)
                        ->orderBy('user_id','DESC')
                        ->first();
        if(!empty($getUserInfo)){
            return response()->json(array('status'=>1,'message'=>'user information retrieve successfully','data'=>$getUserInfo));
        }
        else{
            return response()->json(array('status'=>0,'message'=>'user information not present'));
        }
     }
}
