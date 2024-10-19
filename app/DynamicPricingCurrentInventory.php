<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class DynamicPricingCurrentInventory extends Model
{
    protected $connection = 'bookingjini_cm';
	protected $table = 'dp_cur_inv_table';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array('hotel_id','room_type_id','ota_id','stay_day','no_of_rooms','ota_name');
}
