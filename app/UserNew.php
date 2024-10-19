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
class UserNew extends Model implements AuthenticatableContract
{
use Authenticatable;
protected $connection = 'bookingjini_kernel';
protected $table = 'user_table_new';
protected $primaryKey = "user_id";
/**
     * The attributes that are mass assignable.
*
     * @var array
*/
	protected $fillable = array('prip_id','first_name','last_name','mobile','email_id','user_name','password','is_trash','bookings','company_name','GSTIN','locality','zip_code','country','state','city');
/**
     * The attributes excluded from the model's JSON form.
*
     * @var array
*/
    protected $hidden = [
'password',
    ];
}
