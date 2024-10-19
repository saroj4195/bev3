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
class City extends Model
{
  protected $connection = 'bookingjini_kernel';
  protected $table = 'city_table';
  protected $primaryKey = "city_id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('city_name','state_id');
public function checkStatus($city_name)
{
$condition=array('city_name'=>$city_name);
$cityCheck=City::where($condition)->first();
if($cityCheck)
{
return "exist";
}
else{
return "new";
}
}
}
