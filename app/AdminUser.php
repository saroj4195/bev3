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
class AdminUser extends Model 
{
protected $connection = 'bookingjini_kernel';
protected $table = 'admin_table';
protected $primaryKey = "id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('username','password','role_id','company_id','first_name','last_name','client_ip');
/*
*@auther : Shankar Bag
    *Check the Availibility of Users
*@param $email for user email

*@return  count
    */
public function checkEmailDuplicacy($email)
	{
$email = strtoupper(trim($email));
		$hotel_user_data=AdminUser::where(DB::raw('upper(username)'),$email)->where('is_trash' ,0)->first();
if($hotel_user_data)
{
return "Exist";
}
else
{
return 'New';
}
}
/*
*@auther : Shankar Bag
    *Check the Availibility of Users with specific type
*@param $email for user email

*@return  count
    */
public function checkEmailDuplicacyWithType($email)
	{
$email = strtoupper(trim($email));
		$hotel_user_data=AdminUser::where(DB::raw('upper(username)'),$email)->where('is_trash' ,0)->first();
if($hotel_user_data)
		{
return "Exist";
}
else
		{
return 'New';
		}
}

/*
	*@auther : Shankar Bag
* This is function is used for get the ID of Hotel Admin For refrences Purpose.
	*@param $email for user email
*@return  count
*/
public function getHotelAdminID($email,$tp)
{
$sp_conditions=array("user_email"=>$email,"user_type"=>$tp);
$hotel_user_data = HotelUserCredential::where($sp_conditions)->first(['id']);
$c = 0;
if($hotel_user_data)
{
return $hotel_user_data->id;
}
else
{
return $c;
}
}
public function sendMail($email,$template,$subject,$verificationCode)
{
$data=array('email'=>$email,'subject'=>$subject);
Mail::send(['html'=>$template],['verify_code'=>$verificationCode],function($message) use($data)
{
$message->to($data['email'])->from( env("MAIL_FROM"), env("MAIL_FROM_NAME"))->subject( $data['subject']);
});
if(Mail::failures())
{
return false;
}
return true;
}
}