<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//created a new class HotelFacilitiesDetails 
class HotelChildPolicy extends Model 
{
protected $table = 'hotel_child_policies';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
*@author subhradip
     * @var array
*/
	protected $fillable = array('hotel_id','child_age_for_no_charges','child_age_for_charges_applicabe','is_trash','is_enable','created_by','updated_by');
public function checkStatus($hotel_id)
{
$conditions=array('hotel_id'=> $hotel_id);
$hotel_bank_data = HotelChildPolicy::where($conditions)->first(['id']);
if($hotel_bank_data)
{
return "exist";
}
else
{
return "new";
}
}
}