<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class GoogleHotelCenterIntranet extends Model 
{
     protected $table = 'google_hotel_center_intranet';
     protected $primaryKey = "id";

     protected $fillable = array('id','hotel_id','room_type_rate_plan_status','rate_inv_status','added_on_ghc','room_type_rate_plan_sync','rate_inv_sync','user_id','created_at','updated_at');

}	