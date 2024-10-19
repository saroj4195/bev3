<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class KtdcAgentCode extends Model 
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'ktdc_agent_code';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('ota_name','agent_code');
	
}