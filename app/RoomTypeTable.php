<?php
 namespace App;
 use Illuminate\Database\Eloquent\Model;
 use DB;

 class RoomTypeTable extends Model{
     protected $connection = "bookingjini_kernel";
     protected $table = "room_type_table";
     protected $primaryKey = "room_type_id";

    //  protected $fillable = array('room_type_id','hotel_id','room_type','total_rooms','max_people','max_child',
    // 'max_room_capacity','rack_price','description','image');
 }
