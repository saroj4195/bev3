<?php
namespace App\NewCrs;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Exception;
use DB;
class RoomRatePlan extends Model 
{
    protected $connection = 'bookingjini_kernel' ;
protected $table = 'room_rate_plan';
protected $primaryKey = "room_rate_plan_id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
//protected $fillable = array('hotel_id','room_type_id','rate_plan_id','agent_price','corporate_price','extra_adult_price','extra_child_price','multiple_occupancy','min_price','max_price','from_date','to_date','client_ip','user_id');
}