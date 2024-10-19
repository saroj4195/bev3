<?php
namespace App;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Exception;
use DB;
class RoomRateSettings extends Model 
{
protected $table = 'roomrate_settings';
protected $primaryKey = "room_rateplan_id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('hotel_id','room_type_id','rate_plan_id','agent_price','corporate_price','extra_adult_price','extra_child_price','multiple_occupancy','min_price','max_price','from_date','to_date','client_ip','user_id');
}