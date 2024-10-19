<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class MasterFloorType created
class CmOta extends Model
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'cm_ota';
    protected $primaryKey = "ota_id";
     /**
     * The attributes that are mass assignable.
     * @author subhradip
     * @var array
     */
    protected $fillable = array('ota_name','ota_logo_path','ota_icon_path',
                                'is_status','rate_block_status');
}