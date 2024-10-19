<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Invoice;
use App\HotelBooking;
use App\Inventory;
/**
 * This controller is created to process the modify booking obtain from gems and inventory update after modification of booking.
 * @author Ranjit Date: 16-04-2022
 */

class ModifyBookingGemsController extends Controller
{
    /**
     * This function capture the invoice id and modified dates to process the booking modification
     */
    private $rules = array(
        "invoice_id" => "required",
        "check_in"=>"required",
        "check_out"=>"required"
    );
    private $messages = [
        'invoice_id.required' => 'Please provide invoice id',
        'check_in.required' => 'Please provide check in date',
        'check_out.required' => 'Please provide check out date'
    ];
    public function processModifyBookingFromGems(Request $request){
        $failure_message='Fields are missing';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $invoice_id  = $data['invoice_id'];
        $new_check_in = date("Y-m-d",strtotime($data['check_in']));
        $new_check_out = date("Y-m-d",strtotime($data['check_out']));
       
        $get_cm_status = Invoice::where('invoice_id',$invoice_id)->first();
        $is_cm = $get_cm_status->is_cm;
        $get_old_booking_details = HotelBooking::where('invoice_id',$invoice_id)->get();
        $check_from_date = date('Y-m-d',strtotime($new_check_in)); 
        $check_to_date = date('Y-m-d',strtotime($new_check_out));
        $period     = new \DatePeriod(
            new \DateTime($check_from_date),
            new \DateInterval('P1D'),
            new \DateTime($check_to_date)
        );
        $check_status = 1;
        foreach($get_old_booking_details as $old_bk){
            $hotel_id = $old_bk->hotel_id;
            $room_type_id = $old_bk->room_type_id;
            $check_dates = [];
            foreach($period as $key1 => $value ){
                $check_index = $value->format('Y-m-d');
                $check_dates[] = $check_index;
                $get_current_inv = Inventory::where('hotel_id',$hotel_id)
                                ->where('room_type_id',$room_type_id)
                                ->where('date_from','<=',$check_index)
                                ->where('date_to','>=',$check_index)
                                ->orderBy('inventory_id','DESC')
                                ->first();
                if($get_current_inv->block_status == 1 || $get_current_inv->no_of_rooms == 0){
                    $check_status = 0;
                }
            }
        }
        if($check_status == 0){
            return response()->json(array('status'=>0,"message"=>"Sorry! room not available for the date range.Please choose different dates.",'period'=>$check_dates));
        }
        foreach($get_old_booking_details as $old_bk){
            $hotel_id = $old_bk->hotel_id;
            $room_type_id = $old_bk->room_type_id;
            $rooms = $old_bk->rooms;
            $old_check_in = $old_bk->check_in;
            $old_check_out = $old_bk->check_out;
            $old_from_date = date('Y-m-d',strtotime($old_check_in)); 
            $old_to_date = date('Y-m-d',strtotime($old_check_out));
            $period     = new \DatePeriod(
                new \DateTime($old_from_date),
                new \DateInterval('P1D'),
                new \DateTime($old_to_date)
            );
            foreach($period as $key1 => $value ){
                $old_index = $value->format('Y-m-d');
                $get_current_inv = Inventory::where('hotel_id',$hotel_id)
                                ->where('room_type_id',$room_type_id)
                                ->where('date_from','<=',$old_index)
                                ->where('date_to','>=',$old_index)
                                ->orderBy('inventory_id','DESC')
                                ->first();
                if($get_current_inv){
                    $no_of_rooms = $get_current_inv->no_of_rooms;
                    $update_to = $no_of_rooms + $rooms;
                    $current_inv = array(
                        "hotel_id" => $hotel_id,
                        "room_type_id"=>$room_type_id,
                        "no_of_rooms"=>$update_to,
                        "date_from"=>$old_index,
                        "date_to"=>$old_index,
                        "client_ip"=>'GEMS',
                        "user_id"=>0,
                        "block_status"=>0,
                        "los"=>$get_current_inv->los,
                        "multiple_days"=>$get_current_inv->multiple_days,
                        "action_status"=>$get_current_inv->action_status,
                        "restriction_status"=>$get_current_inv->restriction_status
                    );
                    $insert_data = Inventory::insert($current_inv);
                }
            }
            if($is_cm == 1){
                /**
                 * Call to cm function for cancellation.
                 */
                $url = "https://cm.bookingjini.com/inventory-update-to-cm";
                $cm_array = array(
                    "hotel_id"=>$hotel_id,
                    "room_type_id"=>$room_type_id,
                    "rooms"=>$rooms,
                    "check_in"=>$old_check_in,
                    "check_out"=>$old_check_out,
                    "status"=>"Cancelled"
                );
                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL, $url );
                curl_setopt( $ch, CURLOPT_POST, true );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $cm_array);
                $response = curl_exec($ch);
                curl_close($ch);
            }
        }
        $new_checkin_out_date = $new_check_in."-".$new_check_out;
        $update_invoice_table = Invoice::where('invoice_id',$invoice_id)->update(['check_in_out'=>$new_checkin_out_date]);
        $update_hotel_bookings_table = HotelBooking::where('invoice_id',$invoice_id)->update(['check_in'=>$new_check_in,'check_out'=>$new_check_out]);
        if($update_hotel_bookings_table){
            $get_new_booking_details = HotelBooking::where('invoice_id',$invoice_id)->get();
            foreach($get_new_booking_details as $new_bk){
                $hotel_id = $new_bk->hotel_id;
                $room_type_id = $new_bk->room_type_id;
                $rooms = $new_bk->rooms;
                $new_check_in = $new_bk->check_in;
                $new_check_out = $new_bk->check_out;
                $new_from_date = date('Y-m-d',strtotime($new_check_in)); 
                $new_to_date = date('Y-m-d',strtotime($new_check_out));
                $period     = new \DatePeriod(
                    new \DateTime($new_from_date),
                    new \DateInterval('P1D'),
                    new \DateTime($new_to_date)
                );
                foreach($period as $key1 => $value ){
                    $new_index = $value->format('Y-m-d');
                    $get_current_inv = Inventory::where('hotel_id',$hotel_id)
                                    ->where('room_type_id',$room_type_id)
                                    ->where('date_from','<=',$new_index)
                                    ->where('date_to','>=',$new_index)
                                    ->orderBy('inventory_id','DESC')
                                    ->first();
                    if($get_current_inv){
                        $no_of_rooms = $get_current_inv->no_of_rooms;
                        $update_to = $no_of_rooms - $rooms;
                        $current_inv = array(
                            "hotel_id" => $hotel_id,
                            "room_type_id"=>$room_type_id,
                            "no_of_rooms"=>$update_to,
                            "date_from"=>$new_index,
                            "date_to"=>$new_index,
                            "client_ip"=>'GEMS',
                            "user_id"=>0,
                            "block_status"=>0,
                            "los"=>$get_current_inv->los,
                            "multiple_days"=>$get_current_inv->multiple_days,
                            "action_status"=>$get_current_inv->action_status,
                            "restriction_status"=>$get_current_inv->restriction_status
                        );
                        $insert_data = Inventory::insert($current_inv);
                    }
                }
                if($is_cm == 1){
                    /**
                     * Call to cm function for modification.
                     */
                    $url = "https://cm.bookingjini.com/inventory-update-to-cm";
                    $cm_array = array(
                        "hotel_id"=>$hotel_id,
                        "room_type_id"=>$room_type_id,
                        "rooms"=>$rooms,
                        "check_in"=>$new_check_in,
                        "check_out"=>$new_check_out,
                        "status"=>"Modified"
                    );
                    $ch = curl_init();
                    curl_setopt( $ch, CURLOPT_URL, $url );
                    curl_setopt( $ch, CURLOPT_POST, true );
                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $cm_array);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    return response()->json(array('status'=>1,"message"=>"Modification booking made successfully"));
                }
                else{
                    return response()->json(array('status'=>1,"message"=>"Modification booking made successfully"));
                }
            }
        }
    }
}
