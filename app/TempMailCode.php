<?php
namespace App;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Exception;
use DB;
class TempMailCode extends Model 
{
    
protected $table = 'admin.temp_email_code';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
*
     * @var array
*/
	protected $fillable = array('user_email','code','user_type','hotel_id','is_trash','expired_on');
/*
*@auther : Shankar Bag
*Check the count of attempted
*@param $email for user email
*@param $u_type for user type
*@param $hotel_id for hotel ID
*@return  count
*/
public function getCountTry($email,$u_type,$hotel_id)
{
$email = strtoupper(trim($email));
$cur_time = Carbon::now()->toDateTimeString();
       
$hotel_user_data=TempMailCode::where(DB::raw('upper(user_email)'),$email)
		->where('user_type' ,$u_type)
->where('hotel_id' ,$hotel_id)
		->where('expired_on','>' ,$cur_time)
->where('is_trash' ,0)->get();
		
$c = 0;
if($hotel_user_data)
{
$c = $hotel_user_data->count();
//$c = count($hotel_user_data);
			return $c;
}
		else
{
			return $c;
}
	}
/*
	*@auther : Shankar Bag
*Verify the Code sent to email ID
	*@param $email for user email
*@param $u_type for user type
	*@param $hotel_id for hotel ID
*@param $code for Code sent to email
	*@return verify  status
*/
	public function verifyCode($email,$u_type,$hotel_id,$code)
{
		$is_trash = 0;
$ret_email = $email;
		$email = strtoupper(trim($email));
$cur_time = Carbon::now()->toDateTimeString();
       
$hotel_user_data=TempMailCode::where(DB::raw('upper(user_email)'),$email)
		->where('user_type' ,$u_type)
->where('hotel_id' ,$hotel_id)
		->where('is_trash' ,0)
->where('expired_on','>' ,$cur_time)
		->where('code' ,$code)->first();
if($hotel_user_data)
{
return $ret_email;
}
else
{
return 'NOT FOUND';
}
}
/*@auther : Shankar Bag
*Account verifcation code will be fired to email account of the user by this function
	*@param $email for user email
*@param $template is the email template
    *@param $subject for email subject
*/
public function sendMail($email,$template, $subject, $verificationCode) 
{
$data = array('email' =>$email,'subject'=>$subject);
Mail::send(['html' => $template], ['verify_code'=>$verificationCode],function ($message) use ($data)
{
$message->to($data['email'])->from( env("MAIL_FROM"), env("MAIL_FROM_NAME"))->subject( $data['subject']);
});	
if(Mail::failures())
{
return false;
}	
return true;    
}
public function SendQuickPaymentLink($mail_deatils,$template,$hotel_details,$payment_details)
{
$to 				= $mail_deatils['to'];
$bcc 				= $mail_deatils['bcc'];
$subject 			= $mail_deatils['subject'];
$hotel_name  		= $hotel_details['hotel_name'];
$room_type 			= $hotel_details['room_type'];
$number_of_rooms 	= $hotel_details['number_of_rooms'];
$check_in 			= date('d M Y',strtotime($hotel_details['check_in']));
$check_out		 	= date('d M Y',strtotime($hotel_details['check_out']));
$comments 			= $hotel_details['comments'];
$amount 			= $payment_details['amount'];
$payment_url 		= $payment_details['payment_url'];
$data = array('email' =>$to ,'subject'=>$subject,'bcc'=>$bcc);
Mail::send(['html' => $template], ['hotel_name'=>$hotel_name,'room_type'=>$room_type,'number_of_rooms'=>$number_of_rooms,'check_in'=>$check_in,'check_out'=>$check_out,'comment'=>$comments,'amount'=>$amount,'payment_url'=>$payment_url],function ($message) use ($data)
{
$message->to($data['email'])->from( env("MAIL_FROM"), env("MAIL_FROM_NAME"))->bcc($data['bcc'])->subject( $data['subject']);
});	
if(Mail::failures())
{
return false;
}	
return true; 	
}
}