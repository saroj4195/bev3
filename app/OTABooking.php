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
class OTABooking extends Model 
{
protected $table = 'cm_ota_booking';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
*
     * @var array
*/
	protected $fillable = array('ota_id','unique_id','hotel_id','customer_details','booking_date');
}
