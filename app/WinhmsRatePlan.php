<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class WinhmsRatePlan extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'winhms_rate_plan';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','rate_plan_id','plan_type','plan_name');
	
}