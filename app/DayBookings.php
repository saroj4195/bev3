<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OtaBlockInventory created
class DayBookings extends Model 
{
    protected $table = 'booking_engine.day_bookings';
    protected $primaryKey = "id";

    protected $fillable = ['hotel_id','package_id','package_name','booking_date','outing_dates','no_of_guest','total_amount','paid_amount','discount_amount','tax_amount','payment_mode','booking_status','user_id'];
}	