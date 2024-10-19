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
class HotelBooking extends Model
{
    protected $table = 'hotel_booking';
    protected $primaryKey = "hotel_booking_id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('room_type_id','rooms','check_in','check_out','booking_status','user_id','booking_date','invoice_id','hotel_id');

}
