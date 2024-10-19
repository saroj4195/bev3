<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class HotelAmenities extends Model 
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'hotel_amenities';
    protected $primaryKey = "hotel_amenities_id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('hotel_amenities_name');
	
}