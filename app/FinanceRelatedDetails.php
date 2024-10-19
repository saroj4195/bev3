<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class FinanceRelatedDetails extends Model 
{
    protected $table = 'pan_tax_titles';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('account_no_title','country_id','tax_no_title','is_trash','is_enable','user_id');
 
/*
    *Check status of the hotel paid services
*
    *@auther subhradip
*@return hotel paid services
    */
// function checkFinanceRelatedStatus used for checkng duplicasy
    public function checkFinanceRelatedStatus($account_no_title,$tax_no_tilte)
{
        $conditions=array('account_no_title'=> $account_no_title,'tax_no_title'=> $tax_no_tilte);
$Paid_Services = FinanceRelatedDetails::where($conditions)->first(['id']);
        if($Paid_Services)
{
            return "exist";
}
        else
{
            return "new";
}
    }
}