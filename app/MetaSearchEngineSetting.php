<?php
 namespace App;
 use Illuminate\Database\Eloquent\Model;

 class MetaSearchEngineSetting extends Model{
     protected $table = "meta_search_engine_settings";
     protected $primaryKey = "id";

     protected $fillable = array('name','hotels');

 }
