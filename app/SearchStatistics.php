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
class SearchStatistics extends Model 
{
    protected $table = 'search_statistics';
    protected $primaryKey = "id";
    protected $fillable = array('item_type','item_id','item_name','search_count');
    
}