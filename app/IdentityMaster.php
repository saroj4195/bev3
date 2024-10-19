<?php
 namespace App;
use Illuminate\Database\Eloquent\Model;
 use DB;
class IdentityMaster extends Model{
protected $table = "identity_master";
protected $primaryKey = "id";
protected $fillable = array('id','template_id','company_id','theme_color','theme_font','logo');
}