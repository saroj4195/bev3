<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class CmOtaRoomTypeFetch created
class Booking extends Model 
{
protected $table = 'hotel_booking';
    protected $primaryKey = "hotel_booking_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('room_type_id','rooms','check_in',
'check_out','booking_status','user_id','booking_date','invoice_id','hotel_id');

}	
