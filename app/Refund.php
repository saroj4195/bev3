<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class Refund extends Model 
{
    protected $table = 'refund_table';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     * @author Godti Vinod
* @var array
     */
protected $fillable = array('id','booking_id','message');
}	