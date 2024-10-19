<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class CmOtaRoomTypeSynchronizeRead extends Model
{
    protected $connection = 'bookingjini_cm';
    protected $table = 'cm_ota_room_type_synchronize';
    protected $primaryKey = "id";
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('hotel_id','room_type_id','ota_type_id','ota_room_type','ota_room_type_name');


    public function getOtaRoomType($room_type,$ota_id)
    {
        $ota_room_type_id="";
        $cmOtaRoomTypeSynchDetails= CmOtaRoomTypeSynchronizeRead::select('ota_room_type')
            ->where('room_type_id','=' ,$room_type)
            ->where('ota_type_id','=', $ota_id)
            ->orderBy('id', 'DESC')
            ->first();
        if($cmOtaRoomTypeSynchDetails)
        {
            $ota_room_type_id             =  $cmOtaRoomTypeSynchDetails->ota_room_type;
        }
        return $ota_room_type_id ;
    }
    public  function getSingleHotelRoomIdFromRoomSynch($ota_room_type,$hotel_id)
    {

        $cmOtaRoomTypeSynchDetails =  CmOtaRoomTypeSynchronizeRead::select('*')
                                    ->where('ota_room_type' ,'=', $ota_room_type)
                                    ->where('hotel_id' ,'=', $hotel_id)
                                    ->first();

        if($cmOtaRoomTypeSynchDetails)
        {
            $hotel_room_id             =  $cmOtaRoomTypeSynchDetails->room_type_id;

        }
        else
        {
            $hotel_room_id=0;
        }

        return $hotel_room_id ;

    }
    public function getRoomTypeId($ota_room_type,$ota_id){
        $cmOtaRoomTypeSynchDetails=CmOtaRoomTypeSynchronizeRead::select('room_type_id')
        ->where('ota_room_type','=' ,$ota_room_type)
        ->where('ota_type_id','=', $ota_id)
        ->orderBy('cm_ota_room_type_synchronize.created_at','=','DESC')
        ->first();
        if($cmOtaRoomTypeSynchDetails)
            {
                $room_type_id            =  $cmOtaRoomTypeSynchDetails->room_type_id;

            }
            else
            {
                $room_type_id=0;
            }
            return  $room_type_id;
    }
    public function getRoomType($ota_room_type,$ota_id)
    {
        $cmOtaRoomTypeSynchDetails=CmOtaRoomTypeSynchronizeRead::select('room_type')
            ->join('kernel.room_type_table','cm_ota_room_type_synchronize.room_type_id','room_type_table.room_type_id')
            ->where('ota_room_type','=' ,$ota_room_type)
            ->where('ota_type_id','=', $ota_id)
            ->orderBy('cm_ota_room_type_synchronize.created_at','=','DESC')
            ->first();

            if($cmOtaRoomTypeSynchDetails)
            {
                $room_type             =  $cmOtaRoomTypeSynchDetails->room_type;

            }
            else
            {
                $room_type=0;
            }
            return  $room_type;
    }
}
