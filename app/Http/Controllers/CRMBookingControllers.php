<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
/**
*This controllers is used for fetching booking details for CRM
*/
class CRMBookingControllers extends Controller
{
    public function getBooking(Request $request)
    {
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $email_id = $data['email_id'];
        $contact = $data['contact'];
        if(strlen($email_id) > 0 && $email_id != "NA"){
            $beBookingData = array();
            $otaBookingData = array();
            $getBeBooking =  DB::table('kernel.user_table')
                            ->join('booking_engine.invoice_table','user_table.user_id','=','invoice_table.user_id')
                            ->join('booking_engine.hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                            ->select('hotel_booking.check_in','hotel_booking.check_out','hotel_booking.room_type_id','invoice_table.room_type','hotel_booking.rooms',
                            'hotel_booking.hotel_booking_id','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.hotel_name','invoice_table.total_amount','invoice_table.extra_details','invoice_table.paid_amount','invoice_table.booking_date','invoice_table.booking_status','user_table.first_name','user_table.last_name','user_table.user_id','user_table.email_id','user_table.address','user_table.mobile')
                            ->where('invoice_table.hotel_id',$hotel_id)
                            ->where('invoice_table.booking_status',1)
                            ->where('user_table.email_id',$email_id)
                            ->get();
            foreach($getBeBooking as $be_booking){
                if(!isset($be_booking->room_type)){
                    continue;
                }
                $rateplan = substr($be_booking->room_type,-5,-3);
                $get_room_type = DB::table("kernel.room_type_table")
                                ->select('room_type')
                                ->where('room_type_id',$be_booking->room_type_id)
                                ->first();
                $booking_id     =date("dmy", strtotime($be_booking->booking_date)).str_pad($be_booking->invoice_id, 4, '0', STR_PAD_LEFT);
                $get_extra_details = json_decode($be_booking->extra_details);
                $adult=0;
                $child=0;
                foreach($get_extra_details as $key => $value){
                    if(trim($be_booking->room_type_id)==trim($key))
                    {
                        for($j=0;$j<$be_booking->rooms;$j++){
                            $adult=$adult+$value[$j][0];
                            $child=$child+$value[$j][1];
                        }
                    }
                }
                if($child=='NA'){
                    $child=0;
                }
                $room_info = array(
                    "room_type_id"      => $be_booking->room_type_id,
                    "room_type"         => $get_room_type->room_type,
                    "no_of_rooms"       => $be_booking->rooms,
                    "rate_plan_type"    => $rateplan,
                    "plan_name"         => $rateplan,
                    "adult"             => $adult,
                    "child"             => $child
                );
                $user_info = array(
                    "user_name"         => $be_booking->first_name.' '.$be_booking->last_name,
                    "user_mail_id"      => $be_booking->email_id,
                    "user_address"      => $be_booking->address,
                    "user_mobile"       => $be_booking->mobile
                );
                $booking_info = array(
                    "booking_date"      => $be_booking->booking_date,
                    "hotel_id"          => $be_booking->hotel_id,
                    "hotel_name"        => $be_booking->hotel_name,
                    "check_in"          => $be_booking->check_in,
                    "check_out"         => $be_booking->check_out,
                    "booking_id"        => $be_booking->hotel_booking_id,
                    "mode_of_payment"   => 'online',
                    "total_amount"      => $be_booking->total_amount,
                    "paid_amount"       => $be_booking->paid_amount,
                    "channel"           => 'Bookingjini',
                    "status"            => "confirmed"
                );
                $beBookingData[] = array(
                    'UserDetails'               => $user_info,
                    'BookingsDetails'           => $booking_info,
                    'RoomDetails'               => $room_info
                    );

            }
            // $geOTABooking = DB::select(DB::raw("select hotel_id,customer_details,booking_status,rooms_qty,room_type,checkin_at,checkout_at,booking_date,rate_code,amount,channel_name,no_of_adult,no_of_child,payment_status,unique_id from cmlive.cm_ota_booking where hotel_id = $hotel_id and confirm_status = 1 and cancel_status = 0 and customer_details LIKE '%$email_id%'"));
            $geOTABooking = DB::connection('bookingjini_cm')->table('cm_ota_booking')
                            ->select('hotel_id','customer_details','booking_status','rooms_qty','room_type','checkin_at','checkout_at','booking_date','rate_code','amount','channel_name','no_of_adult','no_of_child','payment_status','unique_id')
                            ->where('hotel_id', $hotel_id)
                            ->where('confirm_status',1)
                            ->where('cancel_status',0)
                            ->where('customer_details', 'like', '%'.$email_id.'%')
                            ->get();

            foreach($geOTABooking as $ota_data){
                if(!isset($ota_data->room_type)){
                    continue;
                }
                $room_type_id = explode(',',$ota_data->room_type);
                $rate_plan_id = explode(',',$ota_data->rate_code);
                $room_data = array();
                $rate_data = array();
                $rate_plan_data = array();
                $hotel_id = $ota_data->hotel_id;
                $hotel_name = DB::table('hotels_table')->select('hotel_name')->where('hotel_id',$hotel_id)->first();
                $customer_details = explode(',',$ota_data->customer_details);
                foreach($room_type_id as $room){
                    $getRoom_name  = DB::connection('bookingjini_cm')->table('cm_ota_room_type_synchronize')
                                    ->join('kernel.room_type_table','cm_ota_room_type_synchronize.room_type_id','=','room_type_table.room_type_id')
                                    ->select('room_type_table.room_type')
                                    ->where('cm_ota_room_type_synchronize.hotel_id',$hotel_id)
                                    ->where('cm_ota_room_type_synchronize.ota_room_type',$room)
                                    ->first();
                    if(sizeof($getRoom_name)>0){
                        $room_data[] = $getRoom_name->room_type;
                    }
                }
                foreach($rate_plan_id as $rate){
                    $get_rate_plan_name  = DB::connection('bookingjini_cm')->table('cm_ota_rate_plan_synchronize')
                                    ->join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')
                                    ->select('rate_plan_table.plan_name','rate_plan_table.plan_type')
                                    ->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)
                                    ->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$rate)
                                    ->first();
                    if(sizeof($get_rate_plan_name) > 0){
                        $rate_data[] = $get_rate_plan_name->plan_name;
                        $rate_plan_data[] = $get_rate_plan_name->plan_type;
                    }
                }

                $room_type_data = implode(',',$room_data);
                $rate_plan_info = implode(',',$rate_data);
                $rate_type_info = implode(',',$rate_plan_data);
                $ota_room_info = array(
                    "room_type_id"      => $ota_data->room_type,
                    "room_type"         => $room_type_data,
                    "no_of_rooms"       => $ota_data->rooms_qty,
                    "rate_plan_type"    => $rate_type_info,
                    "plan_name"         => $rate_plan_info,
                    "adult"             => $ota_data->no_of_adult,
                    "child"             => $ota_data->no_of_child
                );
                $ota_user_info = array(
                    "user_name"         => isset($customer_details[0])? $customer_details[0]:'NA',
                    "user_mail_id"      => isset($customer_details[1])? $customer_details[1]:'NA',
                    "user_address"      => 'NA',
                    "user_mobile"       => isset($customer_details[2])? $customer_details[2]: 'NA'
                );
                $ota_booking_info = array(
                    "booking_date"      => $ota_data->booking_date,
                    "hotel_id"          => $ota_data->hotel_id,
                    "hotel_name"        => $hotel_name->hotel_name,
                    "check_in"          => $ota_data->checkin_at,
                    "check_out"         => $ota_data->checkout_at,
                    "booking_id"        => $ota_data->unique_id,
                    "mode_of_payment"   => $ota_data->payment_status,
                    "total_amount"      => $ota_data->amount,
                    "paid_amount"       => $ota_data->amount,
                    "channel"           => $ota_data->channel_name,
                    "status"            => $ota_data->booking_status
                );
                $otaBookingData[] = array(
                    'UserDetails'               => $ota_user_info,
                    'BookingsDetails'           => $ota_booking_info,
                    'RoomDetails'               => $ota_room_info
                    );
            }
            if(sizeof($beBookingData) > 0 || sizeof($otaBookingData) > 0){
                return response() -> json(array('status'=>1,'message'=>'retrieve successfully','be_booking'=>$beBookingData,'ota_booking'=>$otaBookingData));
            }
            else{
                return response() -> json(array('status'=>0,'message'=>'retrieve fails'));
            }
        }
        else{
            $beBookingData = array();
            $otaBookingData = array();
            $getBeBooking =  DB::table('kernel.user_table')
                            ->join('booking_engine.invoice_table','user_table.user_id','=','invoice_table.user_id')
                            ->join('booking_engine.hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                            ->select('hotel_booking.check_in','hotel_booking.check_out','hotel_booking.room_type_id','invoice_table.room_type','hotel_booking.rooms',
                            'hotel_booking.hotel_booking_id','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.hotel_name','invoice_table.total_amount','invoice_table.extra_details','invoice_table.paid_amount','invoice_table.booking_date','invoice_table.booking_status','user_table.first_name','user_table.last_name','user_table.user_id','user_table.email_id','user_table.address','user_table.mobile')
                            ->where('invoice_table.hotel_id',$hotel_id)
                            ->where('invoice_table.booking_status',1)
                            ->where('user_table.mobile',$contact)
                            ->get();
            foreach($getBeBooking as $be_booking){
                if(!isset($be_booking->room_type)){
                    continue;
                }
                $rateplan = substr($be_booking->room_type,-5,-3);
                $get_room_type = DB::table("kernel.room_type_table")
                                ->select('room_type')
                                ->where('room_type_id',$be_booking->room_type_id)
                                ->first();
                $booking_id     =date("dmy", strtotime($be_booking->booking_date)).str_pad($be_booking->invoice_id, 4, '0', STR_PAD_LEFT);
                $get_extra_details = json_decode($be_booking->extra_details);
                $adult=0;
                $child=0;
                foreach($get_extra_details as $key => $value){
                    if(trim($be_booking->room_type_id)==trim($key))
                    {

                        for($j=0;$j<$be_booking->rooms;$j++){
                            $adult=$adult+$value[$j][0];
                            $child=$child+$value[$j][1];
                        }
                    }
                }
                if($child=='NA'){
                    $child=0;
                }
                $room_info = array(
                    "room_type_id"      => $be_booking->room_type_id,
                    "room_type"         => $get_room_type->room_type,
                    "no_of_rooms"       => $be_booking->rooms,
                    "rate_plan_type"    => $rateplan,
                    "plan_name"         => $rateplan,
                    "adult"             => $adult,
                    "child"             => $child
                );
                $user_info = array(
                    "user_name"         => $be_booking->first_name.' '.$be_booking->last_name,
                    "user_mail_id"      => $be_booking->email_id,
                    "user_address"      => $be_booking->address,
                    "user_mobile"       => $be_booking->mobile
                );
                $booking_info = array(
                    "booking_date"      => $be_booking->booking_date,
                    "hotel_id"          => $be_booking->hotel_id,
                    "hotel_name"        => $be_booking->hotel_name,
                    "check_in"          => $be_booking->check_in,
                    "check_out"         => $be_booking->check_out,
                    "booking_id"        => $be_booking->hotel_booking_id,
                    "mode_of_payment"   => 'online',
                    "total_amount"      => $be_booking->total_amount,
                    "paid_amount"       => $be_booking->paid_amount,
                    "channel"           => 'Bookingjini',
                    "status"            => "confirmed"
                );
                $beBookingData[] = array(
                    'UserDetails'               => $user_info,
                    'BookingsDetails'           => $booking_info,
                    'RoomDetails'               => $room_info
                    );
            }
            // $geOTABooking = DB::select(DB::raw("select hotel_id,customer_details,booking_status,rooms_qty,room_type,checkin_at,checkout_at,booking_date,rate_code,amount,channel_name,no_of_adult,no_of_child,payment_status,unique_id from cmlive.cm_ota_booking where hotel_id = $hotel_id and confirm_status = 1 and cancel_status = 0 and customer_details LIKE '%$contact%'"));
            $geOTABooking = DB::connection('bookingjini_cm')->table('cm_ota_booking')
            ->select('hotel_id','customer_details','booking_status','rooms_qty','room_type','checkin_at','checkout_at','booking_date','rate_code','amount','channel_name','no_of_adult','no_of_child','payment_status','unique_id')
            ->where('hotel_id', $hotel_id)
            ->where('confirm_status',1)
            ->where('cancel_status',0)
            ->where('customer_details', 'like', '%'.$contact.'%')
            ->get();

            foreach($geOTABooking as $ota_data){
                if(!isset($ota_data->room_type)){
                    continue;
                }
                $room_type_id = explode(',',$ota_data->room_type);
                $rate_plan_id = explode(',',$ota_data->rate_code);
                $room_data = array();
                $rate_data = array();
                $rate_plan_data = array();
                $hotel_id = $ota_data->hotel_id;
                $hotel_name = DB::table('hotels_table')->where('hotel_id',$hotel_id)->first();
                $customer_details = explode(',',$ota_data->customer_details);
                foreach($room_type_id as $room){
                    $getRoom_name  = DB::connection('bookingjini_cm')->table('cm_ota_room_type_synchronize')
                                    ->join('kernel.room_type_table','cm_ota_room_type_synchronize.room_type_id','=','room_type_table.room_type_id')
                                    ->select('room_type_table.room_type')
                                    ->where('cm_ota_room_type_synchronize.hotel_id',$hotel_id)
                                    ->where('cm_ota_room_type_synchronize.ota_room_type',$room)
                                    ->first();
                    if(sizeof($getRoom_name)>0){
                        $room_data[] = $getRoom_name->room_type;
                    }
                }
                foreach($rate_plan_id as $rate){
                    $get_rate_plan_name  = DB::connection('bookingjini_cm')->table('cm_ota_rate_plan_synchronize')
                                            ->join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')
                                            ->select('rate_plan_table.plan_name','rate_plan_table.plan_type')
                                            ->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)
                                            ->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$rate)
                                            ->first();
                    if(sizeof($get_rate_plan_name) > 0){
                        $rate_data[] = $get_rate_plan_name->plan_name;
                        $rate_plan_data[] = $get_rate_plan_name->plan_type;
                    }
                }
                $room_type_data = implode(',',$room_data);
                $rate_plan_info = implode(',',$rate_data);
                $rate_type_info = implode(',',$rate_plan_data);
                $ota_room_info = array(
                    "room_type_id"      => $ota_data->room_type,
                    "room_type"         => $room_type_data,
                    "no_of_rooms"       => $ota_data->rooms_qty,
                    "rate_plan_type"    => $rate_type_info,
                    "plan_name"         => $rate_plan_info,
                    "adult"             => $ota_data->no_of_adult,
                    "child"             => $ota_data->no_of_child
                );
                $ota_user_info = array(
                    "user_name"         => isset($customer_details[0])? $customer_details[0]:'NA',
                    "user_mail_id"      => isset($customer_details[1])? $customer_details[1]:'NA',
                    "user_address"      => 'NA',
                    "user_mobile"       => isset($customer_details[2])? $customer_details[2]: 'NA'
                );
                $ota_booking_info = array(
                    "booking_date"      => $ota_data->booking_date,
                    "hotel_id"          => $ota_data->hotel_id,
                    "hotel_name"        => $hotel_name->hotel_name,
                    "check_in"          => $ota_data->checkin_at,
                    "check_out"         => $ota_data->checkout_at,
                    "booking_id"        => $ota_data->unique_id,
                    "mode_of_payment"   => $ota_data->payment_status,
                    "total_amount"      => $ota_data->amount,
                    "paid_amount"       => $ota_data->amount,
                    "channel"           => $ota_data->channel_name,
                    "status"            => $ota_data->booking_status
                );
                $otaBookingData[] = array(
                    'UserDetails'               => $ota_user_info,
                    'BookingsDetails'           => $ota_booking_info,
                    'RoomDetails'               => $ota_room_info
                    );
            }
            if(sizeof($beBookingData) > 0 || sizeof($otaBookingData) > 0){
                return response() -> json(array('status'=>1,'message'=>'retrieve successfully','be_booking'=>$beBookingData,'ota_booking'=>$otaBookingData));
            }
            else{
                return response() -> json(array('status'=>0,'message'=>'retrieve fails'));
            }
        }
    }

    public function getBookingBE(Request $request)
    {
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $email_id = $data['email_id'];
        $contact = $data['contact'];
        if(strlen($email_id) > 0 && $email_id != "NA"){
            $beBookingData = array();
            $getBeBooking =  DB::table('kernel.user_table')
                            ->join('booking_engine.invoice_table','user_table.user_id','=','invoice_table.user_id')
                            ->join('booking_engine.hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                            ->select('hotel_booking.check_in','hotel_booking.check_out','hotel_booking.room_type_id','invoice_table.room_type','hotel_booking.rooms',
                            'hotel_booking.hotel_booking_id','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.hotel_name','invoice_table.total_amount','invoice_table.extra_details','invoice_table.paid_amount','invoice_table.booking_date','invoice_table.booking_status','user_table.first_name','user_table.last_name','user_table.user_id','user_table.email_id','user_table.address','user_table.mobile')
                            ->where('invoice_table.hotel_id',$hotel_id)
                            ->where('invoice_table.booking_status',1)
                            ->where('user_table.email_id',$email_id)
                            ->get();
            foreach($getBeBooking as $be_booking){
                if(!isset($be_booking->room_type)){
                    continue;
                }
                $rateplan = substr($be_booking->room_type,-5,-3);
                $get_room_type = DB::table("kernel.room_type_table")
                                ->select('room_type')
                                ->where('room_type_id',$be_booking->room_type_id)
                                ->first();
                $booking_id     =date("dmy", strtotime($be_booking->booking_date)).str_pad($be_booking->invoice_id, 4, '0', STR_PAD_LEFT);
                $get_extra_details = json_decode($be_booking->extra_details);
                $adult=0;
                $child=0;
                foreach($get_extra_details as $key => $value){
                    if(trim($be_booking->room_type_id)==trim($key))
                    {
                        for($j=0;$j<$be_booking->rooms;$j++){
                            $adult=$adult+$value[$j][0];
                            $child=$child+$value[$j][1];
                        }
                    }
                }
                if($child=='NA'){
                    $child=0;
                }
                $room_info = array(
                    "room_type_id"      => $be_booking->room_type_id,
                    "room_type"         => $get_room_type->room_type,
                    "no_of_rooms"       => $be_booking->rooms,
                    "rate_plan_type"    => $rateplan,
                    "plan_name"         => $rateplan,
                    "adult"             => $adult,
                    "child"             => $child
                );
                $user_info = array(
                    "user_name"         => $be_booking->first_name.' '.$be_booking->last_name,
                    "user_mail_id"      => $be_booking->email_id,
                    "user_address"      => $be_booking->address,
                    "user_mobile"       => $be_booking->mobile
                );
                $booking_info = array(
                    "booking_date"      => $be_booking->booking_date,
                    "hotel_id"          => $be_booking->hotel_id,
                    "hotel_name"        => $be_booking->hotel_name,
                    "check_in"          => $be_booking->check_in,
                    "check_out"         => $be_booking->check_out,
                    "booking_id"        => $be_booking->hotel_booking_id,
                    "mode_of_payment"   => 'online',
                    "total_amount"      => $be_booking->total_amount,
                    "paid_amount"       => $be_booking->paid_amount,
                    "channel"           => 'Bookingjini',
                    "status"            => "confirmed"
                );
                $beBookingData[] = array(
                    'UserDetails'               => $user_info,
                    'BookingsDetails'           => $booking_info,
                    'RoomDetails'               => $room_info
                    );

            }

            if(sizeof($beBookingData) > 0){
                return response() -> json(array('status'=>1,'message'=>'retrieve successfully','be_booking'=>$beBookingData));
            }
            else{
                return response() -> json(array('status'=>0,'message'=>'retrieve fails'));
            }
        }
        else{
            $beBookingData = array();
            $getBeBooking =  DB::table('kernel.user_table')
                            ->join('booking_engine.invoice_table','user_table.user_id','=','invoice_table.user_id')
                            ->join('booking_engine.hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                            ->select('hotel_booking.check_in','hotel_booking.check_out','hotel_booking.room_type_id','invoice_table.room_type','hotel_booking.rooms',
                            'hotel_booking.hotel_booking_id','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.hotel_name','invoice_table.total_amount','invoice_table.extra_details','invoice_table.paid_amount','invoice_table.booking_date','invoice_table.booking_status','user_table.first_name','user_table.last_name','user_table.user_id','user_table.email_id','user_table.address','user_table.mobile')
                            ->where('invoice_table.hotel_id',$hotel_id)
                            ->where('invoice_table.booking_status',1)
                            ->where('user_table.mobile',$contact)
                            ->get();
            foreach($getBeBooking as $be_booking){
                if(!isset($be_booking->room_type)){
                    continue;
                }
                $rateplan = substr($be_booking->room_type,-5,-3);
                $get_room_type = DB::table("kernel.room_type_table")
                                ->select('room_type')
                                ->where('room_type_id',$be_booking->room_type_id)
                                ->first();
                $booking_id     =date("dmy", strtotime($be_booking->booking_date)).str_pad($be_booking->invoice_id, 4, '0', STR_PAD_LEFT);
                $get_extra_details = json_decode($be_booking->extra_details);
                $adult=0;
                $child=0;
                foreach($get_extra_details as $key => $value){
                    if(trim($be_booking->room_type_id)==trim($key))
                    {

                        for($j=0;$j<$be_booking->rooms;$j++){
                            $adult=$adult+$value[$j][0];
                            $child=$child+$value[$j][1];
                        }
                    }
                }
                if($child=='NA'){
                    $child=0;
                }
                $room_info = array(
                    "room_type_id"      => $be_booking->room_type_id,
                    "room_type"         => $get_room_type->room_type,
                    "no_of_rooms"       => $be_booking->rooms,
                    "rate_plan_type"    => $rateplan,
                    "plan_name"         => $rateplan,
                    "adult"             => $adult,
                    "child"             => $child
                );
                $user_info = array(
                    "user_name"         => $be_booking->first_name.' '.$be_booking->last_name,
                    "user_mail_id"      => $be_booking->email_id,
                    "user_address"      => $be_booking->address,
                    "user_mobile"       => $be_booking->mobile
                );
                $booking_info = array(
                    "booking_date"      => $be_booking->booking_date,
                    "hotel_id"          => $be_booking->hotel_id,
                    "hotel_name"        => $be_booking->hotel_name,
                    "check_in"          => $be_booking->check_in,
                    "check_out"         => $be_booking->check_out,
                    "booking_id"        => $be_booking->hotel_booking_id,
                    "mode_of_payment"   => 'online',
                    "total_amount"      => $be_booking->total_amount,
                    "paid_amount"       => $be_booking->paid_amount,
                    "channel"           => 'Bookingjini',
                    "status"            => "confirmed"
                );
                $beBookingData[] = array(
                    'UserDetails'               => $user_info,
                    'BookingsDetails'           => $booking_info,
                    'RoomDetails'               => $room_info
                    );
            }
            
            if(sizeof($beBookingData) > 0){
                return response() -> json(array('status'=>1,'message'=>'retrieve successfully','be_booking'=>$beBookingData));
            }
            else{
                return response() -> json(array('status'=>0,'message'=>'retrieve fails'));
            }
        }
    }

    public function getBookingCM(Request $request)
    {
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $email_id = $data['email_id'];
        $contact = $data['contact'];
        if(strlen($email_id) > 0 && $email_id != "NA"){
            $otaBookingData = array();
            // $geOTABooking = DB::select(DB::raw("select hotel_id,customer_details,booking_status,rooms_qty,room_type,checkin_at,checkout_at,booking_date,rate_code,amount,channel_name,no_of_adult,no_of_child,payment_status,unique_id from cmlive.cm_ota_booking where hotel_id = $hotel_id and confirm_status = 1 and cancel_status = 0 and customer_details LIKE '%$email_id%'"));
            $geOTABooking = DB::connection('bookingjini_cm')->table('cm_ota_booking')
                            ->select('id','hotel_id','customer_details','booking_status','rooms_qty','room_type','checkin_at','checkout_at','booking_date','rate_code','amount','channel_name','no_of_adult','no_of_child','payment_status','unique_id')
                            ->where('hotel_id', $hotel_id)
                            ->where('confirm_status',1)
                            ->where('cancel_status',0)
                            ->where('customer_details', 'like', '%'.$email_id.'%')
                            ->get();

            foreach($geOTABooking as $ota_data){
                if(!isset($ota_data->room_type)){
                    continue;
                }
                $room_type_id = explode(',',$ota_data->room_type);
                $rate_plan_id = explode(',',$ota_data->rate_code);
                $room_data = array();
                $rate_data = array();
                $rate_plan_data = array();
                $hotel_id = $ota_data->hotel_id;
                $hotel_name = DB::table('kernel.hotels_table')->select('hotel_name')->where('hotel_id',$hotel_id)->first();
                $customer_details = explode(',',$ota_data->customer_details);
                foreach($room_type_id as $room){
                    $getRoom_name  = DB::connection('bookingjini_cm')->table('cm_ota_room_type_synchronize')
                                    ->join('kernel.room_type_table','cm_ota_room_type_synchronize.room_type_id','=','room_type_table.room_type_id')
                                    ->select('room_type_table.room_type')
                                    ->where('cm_ota_room_type_synchronize.hotel_id',$hotel_id)
                                    ->where('cm_ota_room_type_synchronize.ota_room_type',$room)
                                    ->first();
                    if($getRoom_name != ""){
                        $room_data[] = $getRoom_name->room_type;
                    }
                }
                foreach($rate_plan_id as $rate){
                    $get_rate_plan_name  = DB::connection('bookingjini_cm')->table('cm_ota_rate_plan_synchronize')
                                    ->join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')
                                    ->select('rate_plan_table.plan_name','rate_plan_table.plan_type')
                                    ->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)
                                    ->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$rate)
                                    ->first();
                    if($get_rate_plan_name !=""){
                        $rate_data[] = $get_rate_plan_name->plan_name;
                        $rate_plan_data[] = $get_rate_plan_name->plan_type;
                    }
                }

                $room_type_data = implode(',',$room_data);
                $rate_plan_info = implode(',',$rate_data);
                $rate_type_info = implode(',',$rate_plan_data);
                $ota_room_info = array(
                    "room_type_id"      => $ota_data->room_type,
                    "room_type"         => $room_type_data,
                    "no_of_rooms"       => $ota_data->rooms_qty,
                    "rate_plan_type"    => $rate_type_info,
                    "plan_name"         => $rate_plan_info,
                    "adult"             => $ota_data->no_of_adult,
                    "child"             => $ota_data->no_of_child
                );
                $ota_user_info = array(
                    "user_name"         => isset($customer_details[0])? $customer_details[0]:'NA',
                    "user_mail_id"      => isset($customer_details[1])? $customer_details[1]:'NA',
                    "user_address"      => 'NA',
                    "user_mobile"       => isset($customer_details[2])? $customer_details[2]: 'NA'
                );
                $ota_booking_info = array(
                    "id"                => $ota_data->id,
                    "booking_date"      => $ota_data->booking_date,
                    "hotel_id"          => $ota_data->hotel_id,
                    "hotel_name"        => $hotel_name->hotel_name,
                    "check_in"          => $ota_data->checkin_at,
                    "check_out"         => $ota_data->checkout_at,
                    "booking_id"        => $ota_data->unique_id,
                    "mode_of_payment"   => $ota_data->payment_status,
                    "total_amount"      => $ota_data->amount,
                    "paid_amount"       => $ota_data->amount,
                    "channel"           => $ota_data->channel_name,
                    "status"            => $ota_data->booking_status
                );
                $otaBookingData[] = array(
                    'UserDetails'               => $ota_user_info,
                    'BookingsDetails'           => $ota_booking_info,
                    'RoomDetails'               => $ota_room_info
                    );
            }
            if(sizeof($otaBookingData) > 0){
                return response() -> json(array('status'=>1,'message'=>'retrieve successfully','ota_booking'=>$otaBookingData));
            }
            else{
                return response() -> json(array('status'=>0,'message'=>'retrieve fails'));
            }
        }
        else{
            $otaBookingData = array();
            // $geOTABooking = DB::select(DB::raw("select hotel_id,customer_details,booking_status,rooms_qty,room_type,checkin_at,checkout_at,booking_date,rate_code,amount,channel_name,no_of_adult,no_of_child,payment_status,unique_id from cmlive.cm_ota_booking where hotel_id = $hotel_id and confirm_status = 1 and cancel_status = 0 and customer_details LIKE '%$contact%'"));
            $geOTABooking = DB::connection('bookingjini_cm')->table('cm_ota_booking')
            ->select('id','hotel_id','customer_details','booking_status','rooms_qty','room_type','checkin_at','checkout_at','booking_date','rate_code','amount','channel_name','no_of_adult','no_of_child','payment_status','unique_id')
            ->where('hotel_id', $hotel_id)
            ->where('confirm_status',1)
            ->where('cancel_status',0)
            ->where('customer_details', 'like', '%'.$contact.'%')
            ->get();
            foreach($geOTABooking as $ota_data){
                if(!isset($ota_data->room_type)){
                    continue;
                }
                $room_type_id = explode(',',$ota_data->room_type);
                $rate_plan_id = explode(',',$ota_data->rate_code);
                $room_data = array();
                $rate_data = array();
                $rate_plan_data = array();
                $hotel_id = $ota_data->hotel_id;
                $hotel_name = DB::table('kernel.hotels_table')->where('hotel_id',$hotel_id)->first();
                $customer_details = explode(',',$ota_data->customer_details);
                foreach($room_type_id as $room){
                    $getRoom_name  = DB::connection('bookingjini_cm')->table('cm_ota_room_type_synchronize')
                                    ->join('kernel.room_type_table','cm_ota_room_type_synchronize.room_type_id','=','room_type_table.room_type_id')
                                    ->select('room_type_table.room_type')
                                    ->where('cm_ota_room_type_synchronize.hotel_id',$hotel_id)
                                    ->where('cm_ota_room_type_synchronize.ota_room_type',$room)
                                    ->first();
                    if($getRoom_name !=""){
                        $room_data[] = $getRoom_name->room_type;
                    }
                }
                foreach($rate_plan_id as $rate){
                    $get_rate_plan_name  = DB::connection('bookingjini_cm')->table('cm_ota_rate_plan_synchronize')
                                            ->join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')
                                            ->select('rate_plan_table.plan_name','rate_plan_table.plan_type')
                                            ->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)
                                            ->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$rate)
                                            ->first();
                    if($get_rate_plan_name != ""){
                        $rate_data[] = $get_rate_plan_name->plan_name;
                        $rate_plan_data[] = $get_rate_plan_name->plan_type;
                    }
                }
                $room_type_data = implode(',',$room_data);
                $rate_plan_info = implode(',',$rate_data);
                $rate_type_info = implode(',',$rate_plan_data);
                $ota_room_info = array(
                    "room_type_id"      => $ota_data->room_type,
                    "room_type"         => $room_type_data,
                    "no_of_rooms"       => $ota_data->rooms_qty,
                    "rate_plan_type"    => $rate_type_info,
                    "plan_name"         => $rate_plan_info,
                    "adult"             => $ota_data->no_of_adult,
                    "child"             => $ota_data->no_of_child
                );
                $ota_user_info = array(
                    "user_name"         => isset($customer_details[0])? $customer_details[0]:'NA',
                    "user_mail_id"      => isset($customer_details[1])? $customer_details[1]:'NA',
                    "user_address"      => 'NA',
                    "user_mobile"       => isset($customer_details[2])? $customer_details[2]: 'NA'
                );
                $ota_booking_info = array(
                    "id"                => $ota_data->id,
                    "booking_date"      => $ota_data->booking_date,
                    "hotel_id"          => $ota_data->hotel_id,
                    "hotel_name"        => $hotel_name->hotel_name,
                    "check_in"          => $ota_data->checkin_at,
                    "check_out"         => $ota_data->checkout_at,
                    "booking_id"        => $ota_data->unique_id,
                    "mode_of_payment"   => $ota_data->payment_status,
                    "total_amount"      => $ota_data->amount,
                    "paid_amount"       => $ota_data->amount,
                    "channel"           => $ota_data->channel_name,
                    "status"            => $ota_data->booking_status
                );
                $otaBookingData[] = array(
                    'UserDetails'               => $ota_user_info,
                    'BookingsDetails'           => $ota_booking_info,
                    'RoomDetails'               => $ota_room_info
                    );
            }
            if(sizeof($otaBookingData) > 0){
                return response() -> json(array('status'=>1,'message'=>'retrieve successfully','ota_booking'=>$otaBookingData));
            }
            else{
                return response() -> json(array('status'=>0,'message'=>'retrieve fails'));
            }
        }
    }

    
}
