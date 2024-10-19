<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OtaBlockInventory created
class DayWisePrice extends Model 
{
    protected $table = 'voucher_day_wise_price';
    protected $primaryKey = "day_wise_price_id";
}	