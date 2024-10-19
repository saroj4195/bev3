<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class HotelPoliciesName extends Model 
{
    protected $table = 'policy_names';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     * @author subhradip
* @var array
     */
protected $fillable = array('name','icon_class','is_trash','is_enable','created_by','updated_by');
	
/*
    *Check status of the hotel user
*@param $email for user email
	*@author subhradip
*@return hotel user  status
    */
public function checkpoliciesStatus($name)
    {
$conditions=array('name'=> $name);
        $hotel_policies = HotelPoliciesName::where($conditions)->first(['id']);
if($hotel_policies)
        {
return "exist";
        }
else
        {
return "new";
        }
}
   
}