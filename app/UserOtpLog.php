<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class UserOtpLog extends Model 
{
     protected $table = 'user_otp_log';

     protected $fillable = array('id','mobile','otp','generated_on','send_on','expire_at','status');

}