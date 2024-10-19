<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class GoogleHotelCenter extends Model 
{
     protected $table = 'google_hotel_center';
     protected $primaryKey = "google_hotel_center_id";

     protected $fillable = array('google_hotel_center_id','hotel_id','added_on','present_in_ghc','map_matched_status','live_ghc_status','room_type_status','rate_plan_status','user_id','client_ip');

}	