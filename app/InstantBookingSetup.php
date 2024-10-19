<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class InstantBookingSetup extends Model 
{
    protected $table = 'instant_booking_setup';
    protected $primaryKey = "id";
    protected $fillable = array('id','hotel_id','theme_color','widget_positation','is_active');

}   