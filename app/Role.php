<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class Role extends Model 
{
    protected $table = 'role_table';
protected $primaryKey = "role_id";
     /**
* The attributes that are mass assignable.
     * @author Godti Vinod
* @var array
     */
protected $fillable = array('role_name');
}	