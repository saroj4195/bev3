<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
use Illuminate\Support\Facades\Mail;
class Notifications extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = "id";
     
    protected $fillable = array('id','hotel_id','content_html','is_active');

}
