<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//created a new class HotelFacilitiesDetails 
class HotelCancellationPolicy extends Model 
{
protected $table = 'hotel_cancellation_policy';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
*@auther subhradip
     * @var array
*/
    protected $fillable = array('hotel_id','room_type_id','from_date','to_date','cancellation_before_days','percentage_refund','is_trash','is_enable','user_id');
// function checkPaidServiceStatus used for checkng duplicasy
public function checkCancellationPolicy($room_type_id,$hotel_id,$from_date,$to_date)
{
$conditions=array('room_type_id'=> $room_type_id,'hotel_id'=>$hotel_id,'from_date'=>$from_date,'to_date'=>$to_date);
$Paid_Services = HotelCancellationPolicy::where($conditions)->first(['id']);
if($Paid_Services)
{
return "exist";
}
else
{
return "new";
}
}
}