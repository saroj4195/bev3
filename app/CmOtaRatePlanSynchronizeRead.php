<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmOtaRatePlanSynchronizeRead extends Model
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'cm_ota_rate_plan_synchronize';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','hotel_room_type_id','ota_type_id','ota_room_type_id','hotel_rate_plan_id','ota_rate_plan_id','ota_rate_plan_name','ota_rate_type');

    public function getOtaRoomIdFromRatePlanSynch($room_type,$ota_id,$rate_plan_id)
    {
            return  CmOtaRatePlanSynchronizeRead::select('*')
                    ->where('hotel_room_type_id','=' ,$room_type)
                    ->where('ota_type_id','=', $ota_id)
                    ->where('hotel_rate_plan_id','=', $rate_plan_id)
                    ->orderBy('id', 'DESC')
                    ->get()->toArray();
    }

    public function get_ota_rate_plans($ota_room_type,$ota_id)
    {
        return  CmOtaRatePlanSynchronizeRead::select('*')
        ->where('ota_room_type_id','=' ,$ota_room_type)
        ->where('ota_type_id','=', $ota_id)
        ->get()->toArray();

    }
    public function get_room_rate_plan($ota_rate_plan_id)
    {
        $cmOtaRatePlanSynchronize=CmOtaRatePlanSynchronizeRead::select('*')
        ->where('ota_rate_plan_id','=', $ota_rate_plan_id)
        ->first();
        if($cmOtaRatePlanSynchronize)
        {
            return  $cmOtaRatePlanSynchronize->hotel_rate_plan_id;
        }

    }
    public function getRoomRatePlan($ota_id,$ota_rate_plan_id)
    {
        $cmOtaRatePlanSynchronize=CmOtaRatePlanSynchronizeRead::select('*')
            ->join('kernel.rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','rate_plan_table.rate_plan_id')
            ->where('ota_type_id','=', $ota_id)
            ->where('ota_rate_plan_id','=', $ota_rate_plan_id)
            ->first();
        if($cmOtaRatePlanSynchronize)
        {
            return  $cmOtaRatePlanSynchronize->plan_name .'('.$cmOtaRatePlanSynchronize->ota_rate_plan_name.')';
        }
        else{
            return "No rate plan available";
        }
    }
}
