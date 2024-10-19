<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class PmsAccount extends Model
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'pms_account';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('name','api_key','hotels','push_url','auth_key');

}
