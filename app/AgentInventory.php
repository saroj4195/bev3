<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OtaBlockInventory created
class AgentInventory extends Model 
{
protected $table = 'agent_inventory_table';
    protected $primaryKey = "agent_inventory_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('hotel_id','room_type_id','agent_id',
'date_from','date_to','client_ip',
                                'user_id','block_status','los');
}	