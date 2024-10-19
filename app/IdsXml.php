<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class IdsXml extends Model
{
    protected $table = 'ids_reservation';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('ids_xml');

}
