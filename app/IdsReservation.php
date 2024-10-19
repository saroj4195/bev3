<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class IdsReservation extends Model
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'ids_reservation';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','ids_string','ids_confirm','ids_xml');

}
