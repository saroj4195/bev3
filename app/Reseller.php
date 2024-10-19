<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class Reseller extends Model 
{
protected $table = 'reseller';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('name','username','password','url','logo','status');
public function checkEmailDuplicacy($email)
    {
$email = strtoupper(trim($email));
        $hotel_user_data=Reseller::where(DB::raw('upper(username)'),$email)->first();
if($hotel_user_data)
{
return "Exist";
}
else
{
return 'New';
}
}
public function checkEmailDuplicacyWithType($email)
{
$email = strtoupper(trim($email));
$hotel_user_data=Reseller::where(DB::raw('upper(username)'),$email)->first();
if($hotel_user_data)
{
return "Exist";
}
else
{
return 'New';
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
public function checkStatus($username)
{
$condition=array('username'=>$username);
$adminCheck=Reseller::where($condition)->first();
if($adminCheck)
{
return "exist";
}
else{
return "new";
}
}
}