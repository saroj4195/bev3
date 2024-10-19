<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class ManageBooking created
class ManageBooking extends Model 
{
protected $table = 'invoice_table';
    protected $primaryKey = "invoice_id";
/**
     * The attributes that are mass assignable.
* @auther subhradip
     * @var array
*/
    protected $fillable = array('hotel_id','hotel_name','room_type','package_id','ref_no',
'total_amount','paid_amount','check_in','check_out','booking_date','discount_code',
                                'extra_details','pay_to_hotel','paid_service_id','visitors_ip','invoice','agent_code',
'user_id');

}	

