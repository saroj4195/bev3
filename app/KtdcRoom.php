<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class KtdcRoom extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'ktdc_room';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','ktdc_hotel_code','room_type_id','ktdc_room_type_code');
	
}