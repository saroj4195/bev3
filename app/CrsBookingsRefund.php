<?php
 namespace App;
 use Illuminate\Database\Eloquent\Model;
 use DB;

 class CrsBookingsRefund extends Model{
     protected $table = "crs_bookings_refund";
     protected $primaryKey = "id";

     protected $fillable = array('id','hotel_id','days_refund_percent','status');

 }
