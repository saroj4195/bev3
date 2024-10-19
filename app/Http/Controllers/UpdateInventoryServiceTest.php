<?php
namespace App\Http\Controllers;
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
use App\LogTable;
use App\ErrorLog;
use DB;
use App\Http\Controllers\invrateupdatecontrollers\GetDataForRateController;

class UpdateInventoryServiceTest extends Controller
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
                $invoice_id = $inv_data['invoice_id'];
                $booking_details=[];
                $booking_details['checkin_at'] =$inv_data['date_from'];
                $booking_details['checkout_at'] =date('Y-m-d', strtotime($inv_data['date_to'].'+1 day'));
                $room_type[]=$inv_data['room_type_id'];
			    $rooms_qty[]=$inv_data['room_qty'];
                $booking_status = $invoice_details['booking_status'];
                $modify_status = $invoice_details['modify_status'];
                $invoiceData=Invoice::where('invoice_id',$invoice_id)->first();
			    if($invoiceData->is_cm==1){
				      $bucketupdate=$this->beConfBookingInvUpdate->bookingConfirm($invoice_id,$inv_data['hotel_id'],$booking_details,$room_type,$rooms_qty,$booking_status,$modify_status);
				}
                else{
                    $resp = $this->updateInvBe($inv_data);
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
                if($invoice_details['ktdc_id'] > 0){
                    $otaDataPushKtdc = array('ktdc_id'=>$invoice_details['ktdc_id'],'invoice_id'=>$inv_data['invoice_id']);
                     $url = 'https://cm.bookingjini.com/inv/push-inv-to-ota-ktdc';
                     $ch = curl_init();
                     curl_setopt( $ch, CURLOPT_URL, $url );
                     curl_setopt( $ch, CURLOPT_POST, true );
                     curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                     curl_setopt( $ch, CURLOPT_POSTFIELDS, $otaDataPushKtdc);
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
    public function updateInvBe($inv_data){
        $hotel_inventory= new Inventory();
        $result=array();
        $result['ota_status']=array();
        if($hotel_inventory->fill($inv_data)->save()){
            return  "Inventory update successfull";
        }
        else{
            return false;
        }
    }
}
