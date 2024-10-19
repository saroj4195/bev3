<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class IdsRoom extends Model
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'ids_room';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','ids_hotel_code','room_type_id','ids_room_type_code');

}
