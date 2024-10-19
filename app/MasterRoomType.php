<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class MasterRoomType created
class MasterRoomType extends Model
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'room_type_table';
    protected $primaryKey = "room_type_id";
     /**
     * The attributes that are mass assignable.
     * @auther subhradip
     * @var array
     */
    protected $fillable = array('hotel_id','room_type','total_rooms',
                                'max_people','max_child','rack_price',
                                'description','image',' user_id',
                                'extra_person','extra_child',
                                'client_ip','room_amenities','bed_type','room_size_value',
                                'room_size_unit','room_view_type','max_infant');


    // function checkPaidServiceStatus used for checkng duplicasy
    public function checkRoomType($room_type,$hotel_id)
    {
        //$room_type=strtoupper($room_type);
        $conditions=array('room_type'=> $room_type,'hotel_id'=>$hotel_id);
        $checkRoomType = MasterRoomType::where($conditions)->first(['room_type_id']);
        if($checkRoomType)
        {
            return "exist";
        }
        else
        {
            return "new";
        }
    }
}
