<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OfflineBooking created
class OfflineBooking extends Model 
{
protected $table = 'offline_booking';
    protected $primaryKey = "hotel_booking_id";
/**
     * The attributes that are mass assignable.
* @auther subhradip
     * @var array
*/
    protected $fillable = array('room_type_id','rooms','check_in','check_out','booking_status',
'total_amount','paid_amount','user_id','hotel_id','operator_user_id');
}	
