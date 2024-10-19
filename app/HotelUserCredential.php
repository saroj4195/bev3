<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
use Illuminate\Support\Facades\Mail;
class HotelUserCredential extends Model
{
protected $table = 'admin_table';
protected $primaryKey = "admin_id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('company_id', 'first_name', 'last_name', 'username', 'mobile', 'password', 'created_by');
/*
*@auther : Shankar Bag
    *Check the Availibility of Users
*@param $email for user email

*@return  count
    */
public function checkEmailDuplicacy($email)
	{
$email = strtoupper(trim($email));
		$hotel_user_data=HotelUserCredential::where(DB::raw('upper(username)'),$email)->where('is_trash' ,0)->first();
if($hotel_user_data)
{
return "exist";
}
else
{
return 'new';
}
}
/*
*@auther : Shankar Bag
* This is function is used for get the ID of Hotel Admin For refrences Purpose.
*@param $email for user email
*@return  count
*/
	public function getHotelAdminID($email)
{

$sp_conditions=array("username"=>$email);
		$hotel_user_data = HotelUserCredential::where($sp_conditions)->first(['admin_id']);
$c = 0;
		if($hotel_user_data)
{
            return $hotel_user_data->admin_id;
}
		else
{
			return $c;
}
	}
/*
    *Account verifcation link will be fired to email account of the user by this function
*@param $email for user email
	*@param $template is the email template
*@param $subject for email subject
    *@param $verifyCode is the Verification link
*/
	public function sendMail($email,$template, $subject, $details) 
{
		$data = array('email' =>$email,'subject'=>$subject);
Mail::send(['html' => $template], ['details'=>$details],function ($message) use ($data)
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
