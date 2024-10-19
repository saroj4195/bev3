<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class AgentCredit extends Model 
{
    protected $table = 'agent_credit';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     * @author Godti Vinod
* @var array
     */
protected $fillable = array('agent_id','commission','code',
                                'credit_limit','due_date');
}	