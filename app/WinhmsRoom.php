<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class WinhmsRoom extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'winhms_room';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','winhms_hotel_code','room_type_id','winhms_room_type_code');
	
}