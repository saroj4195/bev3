<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class HotelInformation extends Model
{
     protected $connection = 'bookingjini_kernel';
     protected $table = 'hotels_table';
     protected $primaryKey = "hotel_id";
     /**
* The attributes that are mass assignable.
     * @AUTHOR : Shankar Bag
* @var array
     */
// Data Filling
	protected $fillable = array('company_id','user_id','hotel_name','hotel_description','country_id','state_id','city_id','hotel_address','email_id','mobile','reservation_manager_no','gm_contact_no','land_line','latitude','longitude','pin','sac_number','advance_booking_days','partial_payment','partial_pay_amt','pay_at_hotel','star_of_property','check_in','check_out','round_clock_check_in_out','whatsapp_no','facebook_link','twitter_link','linked_in_link','instagram_link','tripadvisor_link','holiday_iq_link','logo','exterior_image','is_taxable','google_tag_manager','whatsapp_notification_enabled');
public function getEmailId($hotel_id)
{
$hotel_info=HotelInformation::where("hotel_id",$hotel_id)->first();
if($hotel_info)
{
$email_id=explode(',',$hotel_info['email_id']);
$mobile=explode(',',$hotel_info['mobile']);
}
$result=array("email_id"=>$email_id, "mobile"=>$mobile[0]);
return $result;
}
}
