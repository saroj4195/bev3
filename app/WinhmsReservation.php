<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class WinhmsReservation extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'winhms_reservation';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','winhms_hotel_code','booking_id','winhms_string','winhms_confirm','winhms_cancellation_string');
	
}