<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class HotelUserProfile extends Model 
{
    protected $table = 'hotel_user_profiles';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('first_name','last_name','contact_no','profile_pic','address','user_credential_id');
	
}