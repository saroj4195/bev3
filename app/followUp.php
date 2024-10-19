<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class followUp extends Model 
{
    protected $table = 'followup_table';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('contact_details_id','comment','status');
	
}