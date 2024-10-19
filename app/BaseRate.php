<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class CmOtaRoomTypeFetch created
class BaseRate extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'base_rate';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     * @author ranjit
     * @var array
     */
    protected $fillable = array('hotel_id','room_type_id','rate_plan_id',
                                'bar_price','multiple_occupancy','multiple_days','los','from_date','to_date',
                                'block_status','client_ip','user_id','extra_adult_price','extra_child_price','channel');
}	