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
class PublicUserCredential extends Model 
{
protected $table = 'public.public_users_details';
protected $primaryKey = "id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('public_users_details_uuid','user_email','user_password','user_first_name','user_last_name','contact_number','country_name','state_name','city_name','zip_code','address','profile_image_url','hotel_url_details_id','is_trash','is_enable','created_at','updated_at');
/*
*@auther : Shankar Bag
    *Check the Availibility of Users
*@param $email for user email

*@return  count
    */
public function checkEmailDuplicacy($email,$hot_id)
	{
$email = strtoupper(trim($email));
$hotel_user_data=PublicUserCredential::where(DB::raw('upper(user_email)'),$email)
->where('hotel_url_details_id' ,$hot_id)->where('is_trash' ,0)->first();
if($hotel_user_data)
{
if($hotel_user_data->is_enable=="1")
{
return "Exist";
}
else
{
return "Disable";
}
}
else
{
return 'New';
}
}	
}