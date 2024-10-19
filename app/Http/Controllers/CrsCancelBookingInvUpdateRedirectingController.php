<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;
use App\CmOtaDetails;
use App\Http\Controllers\CmOtaBookingInvStatusService;
use App\Http\Controllers\otacontrollers\InstantBucketController;

class CrsCancelBookingInvUpdateRedirectingController extends Controller
{
    protected $cmOtaBookingInvStatusService;
    protected $instantBucketController;
    protected $bookingEngineController;
    public function __construct(CmOtaBookingInvStatusService $cmOtaBookingInvStatusService,InstantBucketController $instantBucketController)
    {
       $this->cmOtaBookingInvStatusService=$cmOtaBookingInvStatusService;
       $this->instantBucketController=$instantBucketController;
    }

    public function postDetails(Request $request)
    {
        $details = $request->getContent();
        parse_str($details,$data);
        $invoice_id = $data['invoice_id'];
        $ota_id = $data['ota_id'];
        $ota_type_id = $data['ota_type_id'];
        $hotel_id = $data['hotel_id'];
        $check_in = $data['check_in'];
        $check_out = $data['check_out'];
        $ota_room_type = $data['room_type'];
        $booking_status = $data['booking_status'];
        $rooms_qty = $data['room_qty'];
        $ota_hotel_details = new CmOtaDetails(); 

        $cancel_booking = $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($invoice_id,$ota_id,$hotel_id,$check_in,$check_out,$ota_room_type,$booking_status,$rooms_qty);
        if($cancel_booking){
            $ota_hotel_details->ota_id = $ota_type_id;
            $ota_hotel_details->hotel_id = $hotel_id;
            $this->instantBucketController->bucketEngineUpdate($booking_status,'Bookingjini',$ota_hotel_details,$invoice_id);
        }
        return $cancel_booking;
    }   
}
