<?php
namespace App;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Exception;
use DB;
class CurrentRate extends Model
{
    protected $connection = 'bookingjini_cm';
	protected $table = 'current_rate_table';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    //protected $fillable = array('hotel_id','room_type_id','ota_id','stay_day','no_of_rooms','ota_name');
}
