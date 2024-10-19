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
class HotelUrlDetails extends Model 
{
protected $table = 'admin.hotel_url_details';
protected $primaryKey = "id";
/**@auther : Shankar Bag
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('hotel_url','hotel_group_name','hotel_admin_credential_id','is_trash','is_enable','created_on','updated_on');
/*
@auther : Shankar Bag
@Story : This function will check the existance of URL
*@return URL Entry  status
*/
public function checkStatus($url)
{
$hotel_url_data = HotelUrlDetails::where('hotel_url', '=', $url)->first(['id']);
if($hotel_url_data)
{
return "exist";
}
else
{
return "new";
}
}
/*
@auther : Shankar Bag
@Story : This function will return the id of  of URL
*@return URL Entry  status
*/
public function getId($url)
{
$hotel_url_data = HotelUrlDetails::where('hotel_url', '=', $url)->first(['id']);
if($hotel_url_data)
{
return $hotel_url_data->id;
}
else
{
return "NOT FOUND";
}
}
}