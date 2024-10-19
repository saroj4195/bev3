<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\CmOtaRoomTypeSynchronizeRead;
use App\Inventory;
use DB;

class BeConfirmBookingInvUpdateRedirectingController extends Controller
{
    public function bookingConfirm($invoice_id,$hotel_id,$booking_details,$room_type,$rooms_qty,$booking_status,$modify_status){
        if($booking_status == 1){
            $booking_status_info = 'Commit';
        }
        else if($booking_status == 1 && $modify_status == 1){
            $booking_status_info = 'Modify';
        }
        $ota_id = 0; // Crs as BookingEngine
        $rooms = array();
        $ota_room_type = array();     
        for($i=0;$i<sizeof($room_type);$i++){
            
            $get_ota_room = CmOtaRoomTypeSynchronizeRead::
            join("cm_ota_details",function($join){
                $join->on("cm_ota_details.ota_id","=","cm_ota_room_type_synchronize.ota_type_id")
                    ->on("cm_ota_details.hotel_id","=","cm_ota_room_type_synchronize.hotel_id");
            })->where('cm_ota_details.is_active',1)->where('cm_ota_room_type_synchronize.hotel_id',$hotel_id)->where('cm_ota_room_type_synchronize.room_type_id',$room_type[$i])->first();
            if($get_ota_room){
                $ota_room_type[] = $get_ota_room->ota_room_type;
                $ota_type_id = $get_ota_room->ota_type_id;
            }
        }
        if(sizeof($ota_room_type) == 0){
            return true;
        }
        $ota_room_type = implode(',',$ota_room_type);
        $rooms_qty = implode(',',$rooms_qty);
        try{
            $cmOtaBookingInvStatusService = array('invoice_id'=>$invoice_id,'ota_id'=>$ota_id,'hotel_id'=>$hotel_id,'check_in'=>$booking_details['checkin_at'],'check_out'=>$booking_details['checkout_at'],'room_type'=>$ota_room_type,'booking_status'=>$booking_status_info,'room_qty'=>$rooms_qty,'ota_type_id'=>$ota_type_id);
            $cmOtaBookingInvStatusPush = http_build_query($cmOtaBookingInvStatusService);
            $url = 'https://cm.bookingjini.com/cm_ota_booking_inv_status';
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $cmOtaBookingInvStatusPush);
            $status = curl_exec($ch);
            curl_close($ch);
            return 1;
        }
        catch(Exception $e){
          $error_log = new ErrorLog();
           $storeError = array(
              'hotel_id'      => $hotel_id,
              'function_name' => 'CmOtaBookingInvStatusService.saveCurrentInvStatus',
              'error_string'  => $e
           );
           $insertError = $error_log->fill($storeError)->save();
        }
    }
}
