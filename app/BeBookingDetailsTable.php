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
class BeBookingDetailsTable extends Model
{

	protected $table = 'be_booking_details_table';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','booking_id', 'room_type','rooms','room_rate','extra_adult','extra_child','room_type_id','rate_plan_id','rate_plan_name','adult','child','ref_no','tax_amount','discount_price');
}
