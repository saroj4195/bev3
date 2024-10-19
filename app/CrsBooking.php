<?php
 namespace App;
 use Illuminate\Database\Eloquent\Model;
 use DB;

 class CrsBooking extends Model{
     protected $table = "crs_booking";
     protected $primaryKey = "menu_id";

     protected $fillable = array('crs_reserve_id','user_id','invoice_id','check_in','check_out','no_of_rooms','room_type_id','rate_plan_id','room_price','guest_name','adult','child','payment_type','valid_hour','actual_amount','adjust_amount','gst','total_amount','booking_status','payment_status','secure_hash','updated_status','created_by');

 }
