<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class Currencies extends Model 
{
    protected $table = 'currencies';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('name','code','icon_class',
                                'is_trash','is_enable','created_by','updated_by');
/*
    *Check status of the Currencies
*@auther subhradip
    *@return Currencies
*/
    // function checkCurrencies used for checkng duplicasy
public function checkCurrencies($code)
    {
$conditions=array('code'=> $code);
        $Currencies = Currencies::where($conditions)->first(['id']);
if($Currencies)
        {
return "exist";
        }
else
        {
return "new";
        }
}
}