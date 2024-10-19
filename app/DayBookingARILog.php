<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OtaBlockInventory created
class DayBookingARILog extends Model 
{
    protected $table = 'booking_engine.day_booking_ari_log';
    protected $primaryKey = "id";
}	