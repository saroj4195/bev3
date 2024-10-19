<?php
 namespace App;
use Illuminate\Database\Eloquent\Model;
 use DB;
class PageMaster extends Model{
protected $table = "page_master";
protected $primaryKey = "page_id";
protected $fillable = array('template_id','company_id','page_name','page_url','assigned_section');
 }
