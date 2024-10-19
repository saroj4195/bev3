<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class KtdcReservation extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'ktdc_reservation';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','ktdc_string','ktdc_confirm','ktdc_hotel_code','booking_id');
	
}