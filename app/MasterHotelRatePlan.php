<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class MasterHotelRatePlan created
class MasterHotelRatePlan extends Model 
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'room_rate_plan';
    protected $primaryKey = "room_rate_plan_id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','room_type_id','rate_plan_id','bar_price','bookingjini_price',
                                'extra_adult_price','extra_child_price','multiple_occupancy',
                                'from_date','to_date','before_days_offer','stay_duration_offer','lastminute_offer',
                                'client_ip','user_id','min_price','max_price','is_trash','is_enable','created_at','updated_at','be_rate_status','master_plan_status');
   
}	


   