<?php

namespace App\Http\Controllers\Extranetv4;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\BeExpair;
use App\Invoice;
use App\HotelBooking;
use App\Http\Controllers\Extranetv4\CrsBookingsController;
use App\Http\Controllers\Extranetv4\BookingEngineController;
use App\Http\Controllers\Extranetv4\UpdateInventoryService;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{
    protected $crsbookings;
    protected $bookingEngineController;
    protected $beConfBookingInvUpdate;
    public function __construct(
        CrsBookingsController $crsbookings,
        BookingEngineController $bookingEngineController,
        BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate
    ) {
        $this->crsbookings = $crsbookings;
        $this->bookingEngineController = $bookingEngineController;
        $this->beConfBookingInvUpdate = $beConfBookingInvUpdate;
    }
    public function RoomBlock($invoice_id, $hotel_id, $check_in, $check_out, $no_of_rooms)
    {
        $check_in = date('Y-m-d', strtotime($check_in));
        $check_out = date('Y-m-d', strtotime($check_out));
        $current_date = date('Y-m-d  h:i:s');
        if ($current_date) {
            $update_inv_cm = array();
            $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
            $hotel_booking_model = HotelBooking::where('invoice_id', $invoice_id)->get();
            foreach ($hotel_booking_model as $hbm) {
                if ($invoice_details->is_cm == 1) {
                    $update_inv_cm[] = $this->bookingEngineController->updateInventoryCM($hbm, $invoice_details);
                    $resp = $this->bookingEngineController->updateInventory($hbm, $invoice_details);
                } else {
                    $resp = $this->bookingEngineController->updateInventory($hbm, $invoice_details);
                }
            }
            if ($invoice_details->is_cm == 1) {
                if ($update_inv_cm) {
                    foreach ($update_inv_cm as $rm_val) {
                        $invoice_id = $rm_val['invoice_id'];
                        $hotel_id = $rm_val['hotel_id'];
                        $booking_details = $rm_val['booking_details'];
                        $room_type[] = $rm_val['room_type'];
                        $rooms_qty[] = $rm_val['rooms_qty'];
                        $booking_status = $rm_val['booking_status'];
                        $modify_status = $rm_val['modify_status'];
                    }
                    $bucketupdate = $this->beConfBookingInvUpdate->bookingConfirm($invoice_id, $hotel_id, $booking_details, $room_type, $rooms_qty, $booking_status, $modify_status);
                }
            }
        }

        $ist_time =  date('Y-m-d H:i:s', strtotime('+5 hour 30 minutes ', strtotime($current_date)));
        //$endTime = date('Y-m-d H:i:s', strtotime('+7 minutes', strtotime($ist_time)));
        $endTime = date('Y-m-d H:i:s', strtotime($ist_time . '+7 minutes'));
        if ($endTime) {
            $array = [
                'invoice_id' => $invoice_id,
                'hotel_id' => $hotel_id,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'no_of_rooms' => $no_of_rooms,
                'payment_link_status' => "valid",
                'payment_status' => 'confirm',
                'expiry_time' => $endTime,
                'payment_type' => 1,
                'booking_status' => 4
            ];
            if ($array) {
                $insert_data = BeExpair::insert($array);
                $ins =  array('status' => 1, "message" => 'Insert Suscessfully');
                return response()->json($ins);
            } else {
                $ins1 =  array('status' => 0, "message" => 'Insertion Failed');
                return response()->json($ins1);
            }
        }
    }


    public function BeCronJob(Request $request)
    {
       
        $data = $request->all();
        $current_date_time = date("Y-m-d H:i:s");
        $getBeData = BeExpair::where('updated_status', 0)
            ->where('payment_type', 1)
            ->get();
            
        if (sizeof($getBeData) > 0) {
            foreach ($getBeData as $data) {
                if ($data->paymet_link_status == 'invalid') {
                    return true;
                } else {
                    if ($current_date_time >= $data->expiry_time) {
                        $update_invalid = BeExpair::where('invoice_id', $data->invoice_id)->update([
                            'payment_link_status' => 'invalid',
                            'payment_status' => 'cancel', 'updated_status' => 2, 'booking_status' => 3
                        ]);
                        $booking_id = date('ymd') . $data['booking_id'];
                        $bookingDetails =  [
                            'booking_id' => $booking_id,
                            'booking_status' => 4
                        ];
                      
                        //check if the hotel takes IDS                                              
                        try {
                            $url = 'https://dev.be.bookingjini.com/cancell-booking';
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $bookingDetails);
                            $pms = curl_exec($ch);
                            curl_close($ch);
                        } catch (Exception $e) {
                            return 0;
                        }

                        // Cancel Booking
                        $booking_cancel = $this->crsbookings->cancelBooking($data->invoice_id, 'cronjob');
                        return $booking_cancel;
                    }
                }
            }
        }
    }
}
