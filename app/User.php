<?php
namespace App;
use Illuminate\Auth\Authenticatable;
// use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
// use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Exception;
use DB;
class User extends Model implements AuthenticatableContract
{
use Authenticatable;
protected $connection = 'bookingjini_kernel';
protected $table = 'user_table';
    protected $primaryKey = "user_id";
/**
     * The attributes that are mass assignable.
*
     * @var array
*/
	protected $fillable = array('company_id','email_id','first_name','last_name','password','mobile','is_trash','status','company_name','GSTIN','address','zip_code','country','state','city');
/**
     * The attributes excluded from the model's JSON form.
*
     * @var array
*/
    protected $hidden = [
'password',
    ];
/*
    *Account verifcation link will be fired to email account of the user by this function
*@param $email for user email
	*@param $template is the email template
*@param $subject for email subject
    *@param $verifyCode is the Verification link
*/
	public function sendMail($email,$template, $subject, $pass_code)
{
		$data = array('email' =>$email,'subject'=>$subject);
Mail::send(['html' => $template], ['pass_code'=>$pass_code],function ($message) use ($data)
		{
$message->to($data['email'])->from( env("MAIL_FROM"), env("MAIL_FROM_NAME"))->subject( $data['subject']);
		});
if(Mail::failures())
		{
return false;
		}
return true;
	}
/*
*Check status of the hotel user
*@param $email for user email
*
*@return hotel user  status
*/
public function checkStatus($email,$company_id)
{
$hotel_user_data = User::select('status', 'user_id')
->where('company_id', '=', $company_id)
->where('email_id', '=', $email)
->first();
if($hotel_user_data)
{
return "exist";
}
else
{
return "new";
}
}
public function checkMobileStatus($mobile,$company_id)
{
$hotel_user_data = User::select('status', 'user_id')
->where('company_id', '=', $company_id)
->where('mobile', '=', $mobile)
->first();
if($hotel_user_data)
{
return "exist";
}
else
{
return "new";
}
}
/*
*@auther : Shankar Bag
*Check the Availibility of Users
*@param $email for user email
*@return  count
*/
public function checkEmailDuplicacy($email,$company_id)
{
$email = strtoupper(trim($email));
$user_data=User::where(DB::raw('upper(email_id)'),$email)->where('is_trash' ,0)->where('company_id',$company_id)->first();
if($user_data)
{
if($user_data->status==1)
{
return "exist";
}
else
{
return "pending";
}
}
else
{
return 'new';
}
}
}
