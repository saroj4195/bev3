<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OtaBlockInventory created
class DayBookingARI extends Model 
{
    protected $table = 'booking_engine.day_booking_ari';
    protected $primaryKey = "id";

    protected $fillable = ['hotel_id','package_id','day_outing_dates','rate','no_of_guest','block_status'];
}	