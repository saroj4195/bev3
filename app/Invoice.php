<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
use Illuminate\Support\Facades\Mail;
class Invoice extends Model
{
protected $table = 'invoice_table';
    protected $primaryKey = "invoice_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('hotel_id','hotel_name','room_type','package_id','ota_id','ref_no','total_amount','paid_amount','check_in_out','booking_date','booking_status',
'user_id','user_id_new','discount_code','extra_details','pay_to_hotel','paid_service_id','visitors_ip','invoice','ref_from','ids_re_id','tax_amount','discount_amount','booking_source','is_cm','agent_code','created_by','winhms_re_id','addon_charges_amount','addon_charges_tax','arrival_time','guest_note','company_name','gstin');

public function sendMail($email,$template,$subject,$supplied_details)
    {
$data=array('email'=>$email,'subject'=>$subject);
        Mail::send(['html'=>$template],['supplied_details'=>$supplied_details],function($message) use($data)
{
            $message->to($data['email'])->cc('reservations@bookingjini.com')->from( env("MAIL_FROM"), env("MAIL_FROM_NAME"))->subject( $data['subject']);
});
        if(Mail::failures())
{
            return false;
}
        return true;
}

}
