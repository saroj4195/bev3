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
class BlockIp extends Model
{
protected $table = 'wrong_attempt';
    protected $primaryKey = "wrong_attempt_id";
/**
     * The attributes that are mass assignable.
*
     * @var array
*/
	protected $fillable = array('ip_address','user_name','password');
}