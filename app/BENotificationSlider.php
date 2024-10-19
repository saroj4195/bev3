<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
use Illuminate\Support\Facades\Mail;
class BENotificationSlider extends Model
{
    protected $table = 'notification_slider';
    protected $primaryKey = "id";
     
    protected $fillable = array('id','hotel_id','images');

}
