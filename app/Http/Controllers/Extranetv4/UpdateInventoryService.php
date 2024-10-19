<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Validator;
use App\UserCredential;
use App\Invoice;
use App\HotelBooking;
use App\Inventory;//class name from model
use App\MasterRoomType;//class name from model
use App\MasterHotelRatePlan;//class name from model
use App\RatePlanLog;//class name from model
use App\CmOtaRoomTypeSynchronizeRead;
use App\LogTable;
use App\ErrorLog;
use App\DynamicPricingBucket;
use App\HotelInformation;
use App\DynamicPricingCurrentInventory;
use DB;
use App\Http\Controllers\Extranetv4\invrateupdatecontrollers\GetDataForRateController;
use App\Http\Controllers\Extranetv4\BeConfirmBookingInvUpdateRedirectingController;
use App\Http\Controllers\Controller;
use App\DynamicPricingCurrentInventoryBe;

class UpdateInventoryService extends Controller
{
    protected $getdata_curlreq;
    protected $beConfBookingInvUpdate;
    public function __construct(GetDataForRateController $getdata_curlreq,BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate){
       $this->getdata_curlreq                       = $getdata_curlreq;
       $this->beConfBookingInvUpdate                = $beConfBookingInvUpdate;
    }

    public function updateInv($inv_data,$invoice_details){
        $hotel_inventory= new Inventory();
        $result=array();
        $result['ota_status']=array();
        $ota_id=$inv_data['ota_id'];
            try{
                $invoice_id = $inv_data["invoice_id"];
                $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();
                if(isset($invoiceData->booking_status) && $invoiceData->booking_status != 1){
                    if($invoiceData->booking_status == 4){
                        $resp = $this->updateInvBe($inv_data); 
                    
                       
                    }
                    else{
                        return true;
                    }
                }
                if($invoiceData->is_cm!=1){
                    $resp = $this->updateInvBe($inv_data); 
                }
                else{
                    $get_ota_room = CmOtaRoomTypeSynchronizeRead::
                    join("cm_ota_details",function($join){
                        $join->on("cm_ota_details.ota_id","=","cm_ota_room_type_synchronize.ota_type_id")
                            ->on("cm_ota_details.hotel_id","=","cm_ota_room_type_synchronize.hotel_id");
                    })->where('cm_ota_details.is_active',1)->where('cm_ota_room_type_synchronize.hotel_id',$inv_data['hotel_id'])
                    ->where('cm_ota_room_type_synchronize.room_type_id',$inv_data["room_type_id"])->first();
                    if(!$get_ota_room){
                        $resp = $this->updateInvBe($inv_data); 
                    }
                }
                if($invoice_details['ids_re_id'] > 0){
                     $otaDataPush = array('ids_re_id'=>$invoice_details['ids_re_id'],'invoice_id'=>$inv_data['invoice_id']);
                     $url = 'https://cm.bookingjini.com/inv/push-inv-to-ota';
                     $ch = curl_init();
                     curl_setopt( $ch, CURLOPT_URL, $url );
                     curl_setopt( $ch, CURLOPT_POST, true );
                     curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                     curl_setopt( $ch, CURLOPT_POSTFIELDS, $otaDataPush);
                     $inventoryPush = curl_exec($ch);
                     curl_close($ch);
                }
                if($invoice_details['winhms_re_id'] > 0){
                    $otaDataPush = array('winhms_re_id'=>$invoice_details['winhms_re_id'],'invoice_id'=>$inv_data['invoice_id']);
                    $url = 'https://cm.bookingjini.com/inv/push-inv-to-ota-winhms';
                    $ch = curl_init();
                    curl_setopt( $ch, CURLOPT_URL, $url );
                    curl_setopt( $ch, CURLOPT_POST, true );
                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $otaDataPush);
                    $inventoryPush = curl_exec($ch);
                    curl_close($ch);
               }   
            }
            catch(Exception $e){
              $error_log = new ErrorLog();
               $storeError = array(
                  'hotel_id'      => $inv_data['hotel_id'],
                  'function_name' => 'UpdateInventoryService.updateInv',
                  'error_string'  => $e
               );
               $insertError = $error_log->fill($storeError)->save();
            }
    }
     
    public function updateInvBe($inv_data)
    {
        $hotel_inventory = new Inventory();
        $checkin_at = date('Y-m-d', strtotime($inv_data['date_from']));
        $checkout_at = date('Y-m-d', strtotime($inv_data['date_to']));
        $result = array();
        $result['ota_status'] = array();
        if ($hotel_inventory) {
            $hotel_inventory->fill($inv_data)->save();
            $current_inv = array(
                "hotel_id"              => $inv_data['hotel_id'],
                "room_type_id"          => $inv_data['room_type_id'],
                "ota_id"                => -1,
                "stay_day"              => $checkin_at,
                'block_status'          => isset($inv_data['block_status']) ? $inv_data['block_status']:0,
                "los"                   => 0,
                "no_of_rooms"           => $inv_data['no_of_rooms'],
                "ota_name"              => "Bookingjini"
            );
            $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                [
                    'hotel_id'          => $inv_data['hotel_id'],
                    'room_type_id'      =>  $inv_data['room_type_id'],
                    'ota_id'            => -1,
                    'stay_day'          => $checkin_at
                ],
                $current_inv
            );
            
            $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                [
                    'hotel_id'          => $inv_data['hotel_id'],
                    'room_type_id'      =>  $inv_data['room_type_id'],
                    'ota_id'            => -1,
                    'stay_day'          => $checkin_at
                ],
                $current_inv
            );

            try{
            $get_db_status = HotelInformation::select('is_dp')->where('hotel_id', $inv_data['hotel_id'])->first();
            if ($get_db_status) {
                // $checkin_at = date('Y-m-d', strtotime($inv_data['date_from']));
                // $checkout_at = date('Y-m-d', strtotime($inv_data['date_to']));
                $dp_booking_details = array(
                    "hotel_id"          => $inv_data['hotel_id'],
                    "room_type_id"      => $inv_data['room_type_id'],
                    "check_in"          => $checkin_at,
                    "check_out"         => $checkout_at,
                    "no_of_rooms"       => $inv_data['no_of_rooms'],
                );
                $dp_data_insertion = DynamicPricingBucket::insert($dp_booking_details);
            }
            }catch(Exception $e){
            }
            return  "Inventory update successfull";
        } else {
            return false;
        }
    }

    // public function updateInvBe($inv_data){
    //     $hotel_inventory = new Inventory();
    //     $result = array();
    //     $result['ota_status'] = array();
    //     if ($hotel_inventory->fill($inv_data)->save()) {
    //         // try{
    //         // $get_db_status = HotelInformation::select('is_dp')->where('hotel_id', $hotel_id)->first();
    //         // if ($get_db_status) {
    //         //     $checkin_at = date('Y-m-d', strtotime($checkin_at));
    //         //     $checkout_at = date('Y-m-d', strtotime($checkout_at));
    //             // $dp_booking_details = array(
    //             //     "hotel_id" => $hotel_id,
    //             //     "room_type_id" => $room_type,
    //             //     "check_in" => $checkin_at,
    //             //     "check_out" => $checkout_at,
    //             //     "no_of_rooms" => $rooms_qty,
    //             // );
    //             // $dp_data_insertion = DynamicPricingBucket::insert($dp_booking_details);
    //         // }
    //         // }catch(Exception $e){
                
    //         // }
    //         return  "Inventory update successfull"; 
    //     } else {
    //         return false;
            
    //     }
    // }
    public function updateIDSBooking(Request $request){
        $invoice_details = $request->all();
        $otaDataPush = array('ids_re_id'=>$invoice_details['ids_re_id'],'invoice_id'=>$invoice_details['invoice_id']);
        $url = 'https://cm.bookingjini.com/inv/push-inv-to-ota';
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $otaDataPush);
        $inventoryPush = curl_exec($ch);
        curl_close($ch);
    }
}
