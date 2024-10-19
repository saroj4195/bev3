<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class ErrorLog extends Model
{
    protected $connection = 'mysql';
    protected $table = 'error_log_table';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','function_name','error_string');

}
