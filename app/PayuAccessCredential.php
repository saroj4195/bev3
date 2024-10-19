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
class PayuAccessCredential extends Model
{
protected $table = 'payu_access_credentials';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
*
     * @var array
*/
	protected $fillable = array('client_id','client_secret_key','access_token', 'expairy_time');
}