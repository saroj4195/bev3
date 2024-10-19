<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class Apps extends Model 
{
     protected $connection = 'bookingjini_kernel';
     protected $table = 'apps';
     protected $primaryKey = "id";

     protected $fillable = array('id','app_code','app_name','app_logo','app_image','app_screen_shots','app_version','app_rating','app_description','app_info','subscribers','developed_by','developer_website','developer_website');

}	