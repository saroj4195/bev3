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
class SearchLog extends Model 
{
    protected $table = 'search_log';
    protected $primaryKey = "id";
    protected $fillable = array('user_id','search_date','search_type','search_id','search_value');
    
}