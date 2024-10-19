<?php
namespace App\Http\Controllers\Extranetv4\invrateupdatecontrollers;
use Exception;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use App\Inventory;//class name from model
use App\MasterRoomType;//class name from model
use App\MasterHotelRatePlan;//class name from model
use App\Http\Controllers\Controller;
use App\CmOtaDetails;
use App\OtaInventory;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaBooking;
use App\Http\Controllers\Extranetv4\InventoryService;
use DB;

/**
 * This controller add, edit and update inventorys
 * @author Jigyans Singh
 */
//ini_set('memory_limit', '1512M');
class InventoryController extends Controller
{
    protected $invService;
    public function __construct(InventoryService $invService)
    {
        $this->invService = $invService;
    }

    public function getInventory(int $hotel_id, string $date_from, string $date_to, int $room_type_id)
    {
        $date_from=date('Y-m-d', strtotime($date_from));
        $date_to=date('Y-m-d', strtotime($date_to));
        $from = strtotime($date_from);
        $to = strtotime($date_to);
        $dif_dates=array();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $diff=date_diff($date1, $date2);
        $diff=$diff->format("%a");
        $j=0;
        $k=0;
        $mindays = 0;
        for ($i=$from; $i<=$to; $i+=86400) {
            $dif_dates[$j]= date("Y-m-d", $i);
            $j++;
        }
      
        $ota_details = CmOtaDetails::select("cmlive.cm_ota_details.ota_id", "hotel_id", "cmlive.cm_ota.ota_name", "cmlive.cm_ota_details.commision", "cmlive.cm_ota.ota_logo_path", "cmlive.cm_ota.ota_icon_path")->where('hotel_id', $hotel_id)->where('cmlive.cm_ota.is_status', 1) ->join('cmlive.cm_ota', 'cmlive.cm_ota_details.ota_name', '=', 'cmlive.cm_ota.ota_name')->get();
        if ($ota_details) {
            foreach ($ota_details as $RsOta) {
                $data = $this->getInventoryByRoomType($room_type_id, $date_from, $date_to, $RsOta->ota_id, $hotel_id, $mindays);
                $RsOta['inv']=$data;
                $data2=$this->getBookingByRoomType($hotel_id, $room_type_id, $date_from, $date_to, $RsOta->ota_id);
                $RsOta['bookings']=$data2;
            }
            $bookingengine_data_roomtype =$this->invService->getInventeryByRoomTYpe($room_type_id, $date_from, $date_to, $mindays);
            $bookingengine_data_booking=$this->invService->getBookingByRoomtype($hotel_id, $room_type_id, $date_from, $date_to);
         
            for ($i=0;$i<$diff;$i++) {
                $sum=0;
                foreach ($ota_details as $RsOtaDtl) {
                    if ($RsOtaDtl['inv'][$i]['date']==$dif_dates[$i] && $RsOtaDtl['inv'][$i]['block_status']==0) {
                        $sum+=$RsOtaDtl['inv'][$i]['no_of_rooms'];
                    }
                }
                $count[$k]=$sum;
                $k++;
            }
            //print_r($ota_details);exit;
            $res=array('status'=>1,'message'=>"Hotel inventory retrieved successfully ",'bookingengine_inventory'=>$bookingengine_data_roomtype,'bookingengine_data_booking'=>$bookingengine_data_booking,'data'=>$ota_details,'count'=>$count);
            return response()->json($res);
        } else {
            $res=array('status'=>1,'message'=>"Hotel inventory retrieval failed");
        }
    }

    public function getInventoryByRoomType($room_type_id, $date_from, $date_to, $ota_id, $hotel_id, $mindays)
    {
        $filtered_inventory=array();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1, $date2);
        $diff=$diff->format("%a");
        $diff1=date_diff($date1, $date3);
        $diff1=$diff1->format("%a");
        $inv_rooms='';
        $los=1;
        $j=0;
        $k=0;
        if ($diff1<=$mindays && $mindays!=0) {
            $d=$date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);
            $array=array('no_of_rooms'=>0,'block_status'=>1,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
            array_push($filtered_inventory, $array);
        } else {
            $get_ota_name = CmOtaDetails::select('ota_name')->where('ota_id', $ota_id)->first();
            for ($i=1;$i<=$diff; $i++) {
                $d=$date_from;
                $timestamp = strtotime($d);
                $day = date('D', $timestamp);
                $blk_status=0;
                $inventory_details = OtaInventory::select('*')
                                    ->where('room_type_id', '=', $room_type_id)
                                    ->where('hotel_id', $hotel_id)
                                    ->where('channel', $get_ota_name->ota_name)
                                    ->where('date_from', '<=', $d)
                                    ->where('date_to', '>=', $d)
                                    ->orderBy('inventory_id', 'desc')
                                    ->first();
                if (empty($inventory_details)) {
                    $array=array('no_of_rooms'=>0,'block_status'=>0,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                    array_push($filtered_inventory, $array);
                } else {
                    if ($inventory_details->multiple_days == "sync") {
                        $inv_rooms = OtaInventory::select('*')
                                ->where('room_type_id', '=', $room_type_id)
                                ->where('hotel_id', '=', $hotel_id)
                                ->where('date_from', '<=', $date_from)
                                ->where('date_to', '>=', $date_from)
                                ->orderBy('inventory_id', 'desc')
                                ->first();

                        if ($inv_rooms) {
                            if ($inv_rooms->block_status == 0) {
                                $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                array_push($filtered_inventory, $array);
                            } else {
                                $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                array_push($filtered_inventory, $array);
                            }
                        }
                    } else {
                        $block_status           = trim($inventory_details->block_status);
                        $los                    = trim($inventory_details->los);
                        if ($block_status==1) {
                            $blk_status=1;
                            $inv_rooms = OtaInventory::select('no_of_rooms', 'multiple_days')
                                        ->where('room_type_id', '=', $room_type_id)
                                        ->where('hotel_id', $hotel_id)
                                        ->where('channel', $get_ota_name->ota_name)
                                        ->where('date_from', '<=', $date_from)
                                        ->where('date_to', '>=', $date_from)
                                        ->orderBy('inventory_id', 'desc')
                                        ->first();
                            if (empty($inv_rooms)) {
                                $array=array('no_of_rooms'=>0,'block_status'=>1,'los'=>1,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                array_push($filtered_inventory, $array);
                            } elseif (!empty($inv_rooms)) {
                                $multiple_days=json_decode($inv_rooms->multiple_days);
                                if ($multiple_days != null) {
                                    if ($multiple_days->$day == 0) {
                                        $inv_rooms1 = OtaInventory::select('no_of_rooms', 'multiple_days')
                                                    ->where('room_type_id', '=', $room_type_id)
                                                    ->where('hotel_id', $hotel_id)
                                                    ->where('channel', $get_ota_name->ota_name)
                                                    ->where('date_from', '<=', $date_from)
                                                    ->where('date_to', '>=', $date_from)
                                                    ->orderBy('inventory_id', 'desc')
                                                    ->skip(1)
                                                    ->take(2)
                                                    ->get();
                                        if (empty($inv_rooms1[0])) {
                                            $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                            array_push($filtered_inventory, $array);
                                        } else {
                                            $multiple_days1=json_decode($inv_rooms1[0]->multiple_days);
                                            if ($multiple_days1->$day==0) {
                                                $inv_rooms2 = OtaInventory::select('no_of_rooms', 'multiple_days')
                                                                ->where('room_type_id', '=', $room_type_id)
                                                                ->where('hotel_id', $hotel_id)
                                                                ->where('channel', $get_ota_name->ota_name)
                                                                ->where('date_from', '<=', $date_from)
                                                                ->where('date_to', '>=', $date_from)
                                                                ->orderBy('inventory_id', 'desc')
                                                                ->skip(2)
                                                                ->take(3)
                                                                ->get();
                                                if (empty($inv_rooms2[0])) {
                                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory, $array);
                                                } else {
                                                    $array=array('no_of_rooms'=>$inv_rooms2[0]['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory, $array);
                                                }
                                            } else {
                                                $array=array('no_of_rooms'=>$inv_rooms1[0]['no_of_rooms'],'block_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                array_push($filtered_inventory, $array);
                                            }
                                        }
                                    } else {
                                        $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'action_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                        array_push($filtered_inventory, $array);
                                    }
                                } else {
                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>1,'action_status'=>1,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                    array_push($filtered_inventory, $array);
                                }
                            }
                        } else {
                            $inv_rooms =OtaInventory::select('no_of_rooms', 'multiple_days')
                                        ->where('room_type_id', '=', $room_type_id)
                                        ->where('hotel_id', $hotel_id)
                                        ->where('channel', $get_ota_name->ota_name)
                                        ->where('date_from', '<=', $date_from)
                                        ->where('date_to', '>=', $date_from)
                                        ->orderBy('inventory_id', 'desc')
                                        ->first();
                            if (!empty($inv_rooms)) {
                                $multiple_days=json_decode($inv_rooms->multiple_days);
                                if ($multiple_days != null) {
                                    if ($multiple_days->$day == 0) {
                                        $inv_rooms1 = OtaInventory::select('no_of_rooms', 'multiple_days')
                                                    ->where('room_type_id', '=', $room_type_id)
                                                    ->where('hotel_id', $hotel_id)
                                                    ->where('channel', $get_ota_name->ota_name)
                                                    ->where('date_from', '<=', $date_from)
                                                    ->where('date_to', '>=', $date_from)
                                                    ->orderBy('inventory_id', 'desc')
                                                    ->skip(1)
                                                    ->take(2)
                                                    ->get();

                                        if (empty($inv_rooms1[0])) {
                                            $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                            array_push($filtered_inventory, $array);
                                        } else {
                                            $multiple_days1=json_decode($inv_rooms1[0]->multiple_days);
                                            if ($multiple_days1->$day==0) {
                                                $inv_rooms2 = OtaInventory::select('no_of_rooms', 'multiple_days')
                                                                ->where('room_type_id', '=', $room_type_id)
                                                                ->where('hotel_id', $hotel_id)
                                                                ->where('channel', $get_ota_name->ota_name)
                                                                ->where('date_from', '<=', $date_from)
                                                                ->where('date_to', '>=', $date_from)
                                                                ->orderBy('inventory_id', 'desc')
                                                                ->skip(2)
                                                                ->take(3)
                                                                ->get();

                                                if (empty($inv_rooms2[0])) {
                                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory, $array);
                                                } else {
                                                    $array=array('no_of_rooms'=>$inv_rooms2[0]['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                    array_push($filtered_inventory, $array);
                                                }
                                            } else {
                                                $array=array('no_of_rooms'=>$inv_rooms1[0]['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                                array_push($filtered_inventory, $array);
                                            }
                                        }
                                    } else {
                                        $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                        array_push($filtered_inventory, $array);
                                    }
                                } else {
                                    $array=array('no_of_rooms'=>$inv_rooms['no_of_rooms'],'block_status'=>0,'los'=>$los,'room_type_id'=>$room_type_id,'date'=>$date_from,'day'=>$day);
                                    array_push($filtered_inventory, $array);
                                }
                            }
                        }
                    }
                }
                $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }
        return $filtered_inventory;
    }
    public function getBookingByRoomType($hotel_id, $room_type_id, $date_from, $date_to, $ota_id)
    {
        $filtered_booking=array();
        $cmort=new CmOtaRoomTypeSynchronize();
        $cm_booking=new CmOtaBooking();
        $date1=date_create($date_from);
        $date2=date_create($date_to);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1, $date2);
        $diff=$diff->format("%a");
        for ($i=1;$i<=$diff; $i++) {
            $ota_booking=0;
            $d=$date_from;
            $cm_booking_details= $cm_booking
                                ->select('rooms_qty', 'room_type', 'channel_name')
                                ->where('hotel_id', '=', $hotel_id)
                                ->where('checkin_at', '<=', $d)
                                ->where('checkout_at', '>', $d)
                                ->where('confirm_status', '=', 1)
                                ->where('cancel_status', '=', 0)
                                ->get();
            foreach ($cm_booking_details as $cmb) {
                $room_type=explode(',', $cmb['room_type']);
                $room_qty=explode(',', $cmb['rooms_qty']);
                if (sizeof($room_type)!=sizeof($room_qty)) {
                    $ota_booking=0;
                } else {
                    for ($r=0;$r<sizeof($room_type);$r++) {
                        $ota_room_type=$room_type[$r];
                        $hotelroom_id=$cmort->getSingleHotelRoomIdFromRoomSynch($ota_room_type, $hotel_id);
                        if ($hotelroom_id==$room_type_id) {
                            $ota_booking=$ota_booking+$room_qty[$r];
                        }
                    }
                }
            }
            $array=array(
                'booking'=>$ota_booking,
                'date'=> $d
            );
            array_push($filtered_booking, $array);
            $date_from=date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $filtered_booking;
    }
}
