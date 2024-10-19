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
/**
* this module keep the billing table
* @auther ranjit
*/
class BillingDetails extends Model 
{
protected $connection = 'bookingjini_kernel';
protected $table = 'billing_table';
protected $primaryKey = "id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('trial_period','due_date','product_price','product_name','extension_period','company_id');
public function sendMail($mail_to,$template,$subject,$details)
{
// $mail_array=['gourab.nandy@5elements.co.in','accounts@bookingjini.com'];
$details['email']=$mail_to;
$details['url']="api.bookingjini.com/api";
$details['billing_id']=base64_encode($details['id']);
$data=array('email'=>$mail_to,'subject'=>$subject);
Mail::send(['html'=>$template],['details'=>$details],function($message) use($data)
{
$message ->to($data['email'])
->replyTo('accounts@bookingjini.com')
->from( env("MAIL_FROM"), env("MAIL_FROM_NAME"))
->subject($data['subject']);
});
if(Mail::failures())
{
return false;
} 
return true;
} 
}