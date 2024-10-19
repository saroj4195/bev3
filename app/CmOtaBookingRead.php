<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmOtaBookingRead extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'cm_ota_booking';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array('ota_id','hotel_id','unique_id','customer_details',
                                'booking_status','rooms_qty','room_type', 'checkin_at',
                                'checkout_at','booking_date','rate_code','amount',
                                'payment_status','confirm_status','cancel_status',
                                'response_xml','ip','ids_re_id','special_information','cancel_policy');
}