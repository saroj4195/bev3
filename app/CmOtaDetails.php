<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class MasterFloorType created
class CmOtaDetails extends Model
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'cm_ota_details';
    protected $primaryKey = "ota_id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','ota_hotel_code','ota_name',
                                'auth_parameter','url','commision','is_active');

    public function checkCmOtaDetails($ota_name,$hotel_id)
    {
        $conditions=array('ota_name'=> $ota_name,'hotel_id'=>$hotel_id);
        $cmotadetails = CmOtaDetails::where($conditions)->first(['ota_id']);
        if($cmotadetails)
        {
            return "exist";
        }
        else
        {
            return "new";
        }
    }


}
