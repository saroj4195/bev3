<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class SalesExecutive extends Model
{
    protected $table = 'crs_sales_executive';
    protected $primaryKey = "id";

	protected $fillable = array('id','hotel_id','name','phone','email','whatsapp_number');

}
