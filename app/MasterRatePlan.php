<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class MasterRatePlan created
class MasterRatePlan extends Model
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'rate_plan_table';
    protected $primaryKey = "rate_plan_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
	protected $fillable = array('hotel_id','plan_type','plan_name','rate_amenities','is_trash','user_id','client_ip');
// checking for duplicacy
    public function CheckMasterRatePlanStatus($plan_type,$hotel_id)
{
        $conditions=array('plan_type'=> $plan_type,'hotel_id'=>$hotel_id,'is_trash'=>0);
$master_rate_plan = MasterRatePlan::where($conditions)->first(['rate_plan_id']);
        if($master_rate_plan)
{
            return "exist";
}
        else
{
            return "new";
}
    }
}
