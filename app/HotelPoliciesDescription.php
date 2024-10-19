<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class HotelPoliciesDescription extends Model 
{
    protected $table = 'policies';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     * @author subhradip
* @var array
     */
protected $fillable = array('policies_description','hotel_info_id','policy_id','is_trash','is_enable','created_by','updated_by');
	
}