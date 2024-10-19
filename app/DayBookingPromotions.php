<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class DayBookingPromotions extends Model 
{
protected $table = 'day_booking_promotions';
protected $primaryKey = "id";
/**
* @author Dibyajyoti
*/
    protected $fillable = array('id','hotel_id','day_package_id','day_package_name','promotions_name','promotions_code','valid_from','valid_to','discount_percentage','blackout_dates',
'is_active');

}   