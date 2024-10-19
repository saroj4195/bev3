<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Inventory;//class name from model
use App\Invoice;
use App\User;
use App\Coupons;
Use App\HotelBooking;
use App\CmOtaDetails;
use DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\CmOtaRoomTypeSynchronizeRead;
use App\Http\Controllers\BookingEngineController;
use App\PmsAccount;
//create a new class ManageInventoryController

class BookingEngineCancellationController extends Controller
{
    protected $bookingEngineController;
    public function __construct(BookingEngineController $bookingEngineController)
    {
       $this->bookingEngineController = $bookingEngineController;
    }

    public function cancelBooking(Request $request)
    {
        $data = $request->all();
        $hotelInventory = "BE";
        $bookingId = substr($data['booking_id'], 6, strlen($data['booking_id']));
        $modify_status = isset($data['modify_status']) ? $data['modify_status'] : 0;
        $invoiceData = Invoice::where('invoice_id', $bookingId)->first();
        $hotelBookingData = HotelBooking::where('invoice_id', $bookingId)->get();
        if (!$invoiceData) {
            return response()->json(array('status' => 0, 'message' => 'Invalid Booking Id.'));
        }
        if ($invoiceData->booking_status == 3) {
            return response()->json(array('status' => 0, 'message' => 'Already Cancelled.'));
        }
        $is_ids = PmsAccount::where('name', 'IDS NEXT')->whereRaw('FIND_IN_SET(' . $invoiceData->hotel_id . ',hotels)')->first();
        $is_gems = PmsAccount::where('name', 'GEMS')->whereRaw('FIND_IN_SET(' . $invoiceData->hotel_id . ',hotels)')->first();

        $today_date = date('Y-m-d');
        $check_in = $hotelBookingData[0]->check_in;
        if ($check_in < $today_date) {
            return response()->json(array('status' => 0, 'message' => 'Cannot cancel this booking (exceeded checkout date)'));
        }



        //check if the hotel takes IDS 
        // try{
        //     $url = 'https://cm.bookingjini.com/check_ids_hotel';
        //     $ch = curl_init();
        //     curl_setopt( $ch, CURLOPT_URL, $url );
        //     curl_setopt( $ch, CURLOPT_POST, true );
        //     curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        //     curl_setopt( $ch, CURLOPT_POSTFIELDS, $invoiceData->hotel_id);
        //     $pms = curl_exec($ch);
        //     $pms_arr = explode('","',trim(trim($pms,'"['),']"'));
        //     curl_close($ch);
        // }catch(Exception $e){
        //     return 0;
        // }

        if ($is_ids) {    // If hotel takes IDS  //cancel booking update to IDS 
            try {
                $url = 'https://cm.bookingjini.com/crs_cancel_push_to_ids';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $invoiceData->hotel_id);
                $status = curl_exec($ch);
                curl_close($ch);

                $ids = $status;
            } catch (Exception $e) {
                $error_log = new ErrorLog();
                $storeError = array(
                    'hotel_id'      => $inv_data['hotel_id'],
                    'function_name' => 'IdsXmlCreationAndExecutionController.pushIdsCrs',
                    'error_string'  => $e
                );
                $insertError = $error_log->fill($storeError)->save();
            }
        } else {
            $ids = isset($invoiceData->ids_re_id) ? $invoiceData->ids_re_id : 0;
        }
        $cancel_status = Invoice::where('invoice_id', $bookingId)->update(['booking_status' => 3, 'ids_re_id' => $ids]);
        $hotel_booking_cancel_status = HotelBooking::where('invoice_id', $bookingId)->update(['booking_status' => 3]);
        // Check if the hotel takes GEMS
        if ($is_gems) {
            $gems_res = $this->bookingEngineController->pushBookingToGems($bookingId, 'true');
        }
        if ($modify_status == 0) {
            $this->beBookingMail($bookingId, 0, 'Cancellation');
        }

        //This code is use to tracking hotelier Activity
        // if(isset($invoiceData->invoice_id)){
        //     $booking_id2= date('dmy') . $bookingId;
        //     $activity_from = "BE";
        //     if($invoiceData->booking_source == 'GEMS'){
        //         $activity_id = "a17";
        //         $activity_description = "Cancelled PMS Booking received booking id $booking_id2";
        //     }elseif($invoiceData->booking_source == 'CRS'){
        //         $activity_id = "a19";
        //         $activity_from = "CRS";
        //         $activity_description = "Cancelled CRS Booking received booking id $booking_id2";
        //     }elseif($invoiceData->booking_source == 'google'){
        //         $activity_id = "a18";
        //         $activity_description = "Cancelled Google Booking received booking id $booking_id2";
        //     }elseif($invoiceData->booking_source == 'QUICKPAYMENT'){
        //         $activity_id = "a20";
        //         $activity_description = "Cancelled QUICKPAYMENT Booking received booking id $booking_id2";
        //     }else{
        //         $activity_id = "a16";
        //         $activity_description = "Cancelled Website Booking received booking id $booking_id2";
        //     }
        //     $activity_name = "";
        //     $user_id2=$invoiceData->user_id;
        //     $hotel_id2=$invoiceData->hotel_id;
        //     captureHotelActivityLog($hotel_id2, $user_id2, $activity_id, $activity_name, $activity_description, $activity_from);
        // }

        if ($invoiceData->is_cm == 1) {
            $cancelBookingProcess = 0;
            foreach ($hotelBookingData as $key => $hotelBooking) {
                $booking_details = [];
                $booking_details['checkin_at'] = $hotelBooking->check_in;
                $booking_details['checkout_at'] = $hotelBooking->check_out;

                $room_type[] = $hotelBooking->room_type_id;
                $rooms_qty[] = $hotelBooking->rooms;
            }
            $cancelBookingProcess = $this->bookingCancel($bookingId, $hotelBooking->hotel_id, $booking_details, $room_type, $rooms_qty, $modify_status);
            if ($cancelBookingProcess == 1) {
                return response()->json(['status' => 1, 'message' => 'Booking is successfully cancelled.']);
            } else {
                return response()->json(['status' => 0, 'message' => 'Booking cancellation is failed. Please try again.']);
            }
        } else {
            return response()->json(['status' => 1, 'message' => 'Booking is successfully cancelled.']);
        }
    }
	public function bookingCancel($invoice_id,$hotel_id,$booking_details,$room_type,$rooms_qty,$modify_status){

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

        try{
           
            $cmOtaBookingInvStatusService = array('invoice_id'=>$invoice_id,'ota_id'=>$ota_id,'hotel_id'=>$hotel_id,'check_in'=>$booking_details['checkin_at'],'check_out'=>$booking_details['checkout_at'],'room_type'=>$ota_room_type,'booking_status'=>$booking_status,'room_qty'=>$rooms_qty,'ota_type_id'=>$ota_type_id,'modify_status'=>$modify_status);
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

    public function beBookingMail($invoice_id,$payment_type,$mail_type){
        $invoice= new Invoice();
        $getInvoice = $invoice::where('invoice_id',$invoice_id)->first();
        $id = $getInvoice->invoice_id;
        $booking_date = ('Y-m-d H:i:s');
        $details=Invoice::join('kernel.user_table as a','invoice_table.user_id','=','a.user_id')
        ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
        ->select('invoice_table.hotel_name','invoice_table.room_type','invoice_table.total_amount',
        'invoice_table.user_id','invoice_table.paid_amount','invoice_table.check_in_out',
        'invoice_table.booking_date','invoice_table.booking_status','a.first_name','invoice_table.invoice',
        'a.last_name','a.email_id','a.address','a.mobile','hotel_booking.check_in','hotel_booking.check_out',
        'hotel_booking.rooms','invoice_table.hotel_id','invoice_table.extra_details','invoice_table.discount_amount','invoice_table.tax_amount','invoice_table.agent_code')
        ->where('invoice_table.invoice_id',$id)
        ->first();
        $hoteldetails=DB::table('kernel.hotels_table')->select('hotels_table.hotel_address','hotels_table.mobile','hotels_table.exterior_image','hotels_table.email_id','hotels_table.company_id','hotels_table.cancel_policy','hotels_table.check_in as check_in_time','hotels_table.check_out as check_out_time')->where('hotel_id',$details->hotel_id)->first();

        $email_id=explode(',',$hoteldetails->email_id);
        $email_info = $details->email_id;
        $hotel_id = $details->hotel_id;
        if($hotel_id == 2065 || $hotel_id == 2881 || $hotel_id == 2882 || $hotel_id == 2883 || $hotel_id == 2884 || $hotel_id == 2885 || $hotel_id == 2886){
        $coupon_code    = isset($details->agent_code)?$details->agent_code:'NA';
            if($coupon_code != 'NA'){
                $get_email_id =  Coupons::select('agent_email_id')
                                ->where('hotel_id',$hotel_id)
                                ->where('coupon_code','=',$coupon_code)
                                ->first();
                if(isset($get_email_id->agent_email_id) && $get_email_id->agent_email_id){
                    $email_info = $get_email_id->agent_email_id;
                }
            }
        }

        if(is_array($email_id))
        {
            $hoteldetails->email_id=$email_id[0];
        }
        
        $body           = $details->invoice;
        $body           = str_replace("#####", $invoice_id, $body);
        $body           = str_replace("BOOKING CONFIRMATION", "BOOKING CANCELLATION", $body);
        $body           = str_replace("BOOKING MODIFICATION", "BOOKING CANCELLATION", $body);
        if($this->sendMail($email_info,$body, "Booking Cancellation",$hoteldetails->email_id,$details->hotel_name,$details->hotel_id)){
            $res=array('status'=>1,"message"=>'Mail invoice sent successfully');
            return response()->json($res);
        }
        else{
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Mail invoice sending failed";
            return response()->json($res);
        }
    }
    public function sendMail($email,$template, $subject,$hotel_email,$hotel_name,$hotel_id)
    {
        $data = array('email' =>$email,'subject'=>$subject);
        $data['template']=$template;
        $data['hotel_name']=$hotel_name;
        if($hotel_id == 2065 || $hotel_id == 2881 || $hotel_id == 2882 || $hotel_id == 2883 || $hotel_id == 2884 || $hotel_id == 2885 || $hotel_id == 2886){
            if($hotel_email=="")
            {
                $data['hotel_email']="";
                $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com','odishaecoretreat@gmail.com','pkchand65@gmail.com','otdc@panthanivas.com'];
            }
            else if($hotel_email != ""){
                $data['hotel_email']=$hotel_email;
                $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com','odishaecoretreat@gmail.com','pkchand65@gmail.com','otdc@panthanivas.com'];
            }
            else
            {
                $data['hotel_email']="";
                $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com','odishaecoretreat@gmail.com','pkchand65@gmail.com','otdc@panthanivas.com'];
            }
        }
        else{
            if($hotel_email==""){
                $data['hotel_email']="";
                $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com'];
                // $mail_array=['trilochan.parida@5elements.co.in'];
            }
            else if($hotel_email != ""){
                $data['hotel_email']=$hotel_email;
                $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com'];
                // $mail_array=['trilochan.parida@5elements.co.in'];
            }
            else{
                $data['hotel_email']="";
                $mail_array=['trilochan.parida@5elements.co.in','reservations@bookingjini.com'];
                // $mail_array=['trilochan.parida@5elements.co.in'];
            }
        }
        $data['mail_array']=$mail_array;
        try{
            Mail::send([],[],function ($message) use ($data)
            {
                if($data['hotel_email']!="")
                {
                    $message->to($data['email'])
                    ->cc($data['hotel_email'])
                    ->bcc($data['mail_array'])
                    ->from( env("MAIL_FROM"), $data['hotel_name'])
                    ->subject( $data['subject'])
                    ->setBody($data['template'], 'text/html');
                }
                else
                {
                    $message->to($data['email'])
                    ->from( env("MAIL_FROM"), $data['hotel_name'])
                    //->cc('gourab.nandy@bookingjini.com')
                    ->subject( $data['subject'])
                    ->setBody($data['template'], 'text/html');
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
}
