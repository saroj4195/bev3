<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class OfflineBooking created
class CrsReservation extends Model
{
protected $table = 'crs_reservation';
    protected $primaryKey = "crs_reserve_id";
/**
     * The attributes that are mass assignable.
* @auther subhradip
     * @var array
*/
    protected $fillable = array('adjusted_amount','comments','for_user_id','invoice_id','pay_status','operator_user_id','invoice');
}

