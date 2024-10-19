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
class State extends Model 
{
protected $connection = 'bookingjini_kernel';
protected $table = 'state_table';
protected $primaryKey = "state_id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('country_id','state_name');
public function checkStatus($state_name)
{
$conditions=array('state_name'=> $state_name);
$stateDetails = State::where($conditions)->first();
if($stateDetails)
{
return "exist";
}
else
{
return "new";
}
}

}