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
class Country extends Model 
{
    protected $connection = 'bookingjini_kernel';
	protected $table = 'country_table';
    protected $primaryKey = "country_id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array('country_id','country_name','country_code','country_dial_code');
    // public function checkStatus($country_name)
    // {
    //     $condition=array('country_name'=>$country_name);
    //     $countryCheck=Country::where($condition)->first();
    //     if($countryCheck)
    //     {
    //         return "exist";
    //     }
    //     else{
    //         return "new";
    //     }
    // }
}