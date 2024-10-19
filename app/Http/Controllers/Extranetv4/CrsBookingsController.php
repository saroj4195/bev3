<?php

namespace App\Http\Controllers\Extranetv4;

use App\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Inventory; //class name from model
use App\Invoice;
use App\User;
use App\CmOtaDetails;
use App\CrsBooking;
use App\HotelInformation;
use DB;
use App\Http\Controllers\Extranetv4\InventoryService;
use App\MasterRoomType;
use App\HotelBooking;
use App\ImageTable;
use App\CmOtaBookingRead;
use App\CmOtaRatePlanSynchronizeRead;
use App\CmOtaRoomTypeSynchronizeRead;
use App\Http\Controllers\Extranetv4\BookingEngineController;
use App\CrsBookingInquery;
use Illuminate\Support\Facades\Hash;
// use App\CmOtaBooking;
use App\DynamicPricingCurrentInventory;
use App\Http\Controllers\Controller;
use App\Model\Commonmodel;
use App\NewCrs\CrsCanclePolicy;
use App\DynamicPricingCurrentInventoryBe;

class CrsBookingsController extends Controller
{
    private $rules = array(
        'from_date' => 'required',
        'to_date' => 'required',
        'hotel_id' => 'required'
    );
    private $message = [
        'from_date.required' => 'from date should be required',
        'to_date.required' => 'to date should be required',
        'hotel_id.required' => 'hotel_id should be required'
    ];
    protected $invService;
    protected $cmOtaBookingInvStatusService;
    public function __construct(InventoryService $invService, BookingEngineController $bookingEngineController)
    {
        $this->invService = $invService;
        $this->bookingEngineController = $bookingEngineController;
    }
    public function getHotelDetails($company_id, Request $request)
    {
        $data =  $request->all();
        $getData = HotelInformation::where('company_id', $company_id)
            ->where('status', 1)
            ->get();
        if (sizeof($getData) > 0) {
            $res = array('status' => 1, 'message' => 'Hotel Details Retrived Successful', 'data' => $getData);
            return $res;
        }
    }

    public function crsBookings(Request $request)
    {
        $data = json_decode($request->getcontent());
        $crsBooking = new CrsBooking();
        $invoice = new Invoice();
        $invoice_id = $data[0]->invoice_id;
        $guest_details = $data[0]->guest_details;
        $adult = $data[0]->no_of_adult;
        $child = $data[0]->no_of_child;
        $internal_remark = $data[0]->internal_remark;
        $guest_remark = $data[0]->guest_remark;
        $valid_type = (int)$data[0]->valid_type;
        $valid_hour = (int)$data[0]->valid_hour;
        $payment_type = $data[0]->payment_type;
        $secure_hash = $data[0]->secure_hash;

        if (isset($data[0]->partner_id)) {
            $partner_id = $data[0]->partner_id;
        } else {
            $partner_id = '';
        }

        
        if (isset($data[0]->selected_sales_executive)) {
            $sales_executive = $data[0]->selected_sales_executive;
        } else {
            $sales_executive = '';
        }

        if (isset($data[0]->booking_type)) {
            $booking_type = $data[0]->booking_type;
        } else {
            $booking_type = '';
        }

        

        $package_active = isset($data[0]->package_active) ? $data[0]->package_active : 'no';
        $booking_time = date("Y-m-d H:i:s");
        $expiry_time = date("Y-m-d H:i:s", strtotime('+' . $valid_hour . ' hours'));
        $mail_type = 'Confirmation';

        if ($valid_type == 1) {   // hours
            $expiry_time = date("Y-m-d H:i:s", strtotime('+' . $valid_hour . ' hours'));
        } elseif ($valid_type == 2) { // days
            $expiry_time = date("Y-m-d H:i:s", strtotime('+' . $valid_hour . ' days'));
        } elseif ($valid_type == 3) { //never
            $expiry_time = '';
        } else {
            $expiry_time = '';
        }

        $get_details = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->select(
                'invoice_table.invoice_id',
                'invoice_table.hotel_id',
                'invoice_table.room_type',
                'invoice_table.paid_amount',
                'invoice_table.check_in_out',
                'invoice_table.booking_date',
                'invoice_table.booking_status',
                'invoice_table.invoice',
                'invoice_table.user_id',
                'hotel_booking.room_type_id',
                'hotel_booking.rooms',
                'hotel_booking.check_in',
                'hotel_booking.check_out',
                'invoice_table.total_amount',
                'invoice_table.extra_details'
            )
            ->where('invoice_table.invoice_id', $invoice_id)
            ->first();
        $extra_details = $get_details->extra_details;
        $array = json_decode($extra_details, true);
        $crsData = [
            'hotel_id' => $get_details->hotel_id,
            'invoice_id' => $invoice_id,
            'check_in' => $get_details->check_in,
            'check_out' => $get_details->check_out,
            'no_of_rooms' => $get_details->rooms,
            'room_type_id' => $get_details->room_type_id,
            'guest_name' => $guest_details,
            'adult' => $adult,
            'child' => $child,
            'payment_type' => $payment_type,
            'payment_link_status' => 'valid',
            'payment_status' => 'Confirm',
            'total_amount' => $get_details->total_amount,
            'booking_time' => $booking_time,
            'valid_hour' => $valid_hour,
            'validity_type' => $valid_type,
            'expiry_time' => $expiry_time,
            'booking_status' => 1,
            'updated_status' => 0,
            'secure_hash' => $secure_hash,
            'internal_remark' => $internal_remark,
            'guest_remark' => $guest_remark,
            'partner_id' => $partner_id,
            'sales_executive_id' => $sales_executive,
            'booking_type' => $booking_type,
        ];

        $insertData = $crsBooking::insert($crsData);
        if ($insertData) {
            if ($package_active == 'no') {

                $this->crsBookingMail($invoice_id, $payment_type, $mail_type);
                // if($get_details->hotel_id == '1953'){
                if ($payment_type == 1) {
                    $crsBooking::where('invoice_id', $invoice_id)->update(['is_payment_received' => 2]);
                }

                // }
            }
            $res = array('status' => 1, 'message' => CRS_BOOKING_SUCCESS_MESSAGE);
            return $res;
        }
    }

    public function crsBookingMailTest($invoice_id, $payment_type, $mail_type)
    {
        $failure_message = "Send mail failed";
        $invoice = new Invoice();
        $getInvoice = $invoice::where('invoice_id', $invoice_id)->first();
        $id = $getInvoice->invoice_id;
        $secureHash = 'https://be.bookingjini.com/crs/payment/' . base64_encode($id);
        $booking_date = ('Y-m-d H:i:s');

        // $email=$email_id;//Agent or Corporate EMail id

        $details = Invoice::join('kernel.user_table as a', 'invoice_table.user_id', '=', 'a.user_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('crs_booking', 'crs_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->select(
                'invoice_table.hotel_name',
                'invoice_table.room_type',
                'invoice_table.total_amount',
                'invoice_table.user_id',
                'invoice_table.paid_amount',
                'invoice_table.check_in_out',
                'invoice_table.booking_date',
                'invoice_table.booking_status',
                'a.first_name',
                'invoice_table.invoice',
                'a.last_name',
                'a.email_id',
                'a.address',
                'a.mobile',
                'hotel_booking.check_in',
                'hotel_booking.check_out',
                'hotel_booking.rooms',
                'invoice_table.hotel_id',
                'invoice_table.extra_details',
                'crs_booking.guest_remark',
                'invoice_table.discount_amount',
                'invoice_table.tax_amount',
                'crs_booking.adult',
                'crs_booking.child',
                'crs_booking.guest_name',
                'crs_booking.valid_hour',
                'crs_booking.payment_type',
                'crs_booking.validity_type',
                'crs_booking.expiry_time'
            )
            ->where('invoice_table.invoice_id', $id)
            ->first();
        // $rate = split($details->room_type);
        $hoteldetails = DB::table('kernel.hotels_table')->select('hotels_table.hotel_address', 'hotels_table.mobile', 'hotels_table.exterior_image', 'hotels_table.email_id', 'hotels_table.company_id', 'hotels_table.cancel_policy', 'hotels_table.check_in as check_in_time', 'hotels_table.check_out as check_out_time')->where('hotel_id', $details->hotel_id)->first();
        $image_id = explode(',', $hoteldetails->exterior_image);
        $images = ImageTable::select('image_name')->where('image_id', $image_id[0])->where('hotel_id', $details->hotel_id)->first();
        if ($images) {
            $hotel_image = $images->image_name;
        } else {
            $hotel_image = "";
        }
        $email_id = explode(',', $hoteldetails->email_id);

        if (is_array($email_id)) {
            $hoteldetails->email_id = $email_id[0];
        }
        $mobile = explode(',', $hoteldetails->mobile);
        if (is_array($mobile)) {
            $hoteldetails->mobile = $mobile[0];
        }

        $formated_invoice_id = date('dmy', strtotime($details->booking_date));
        $formated_invoice_id = $formated_invoice_id . $invoice_id;
        $name = explode(',', $details->guest_name);
        $email = $details->email_id;
        $total_adult = array_sum(explode(',', $details->adult));
        $total_child = array_sum(explode(',', $details->child));
        $no_of_nights = ceil(abs(strtotime($details->check_in) - strtotime($details->check_out)) / 86400);
        $booking_id     = date("dmy", strtotime($details->booking_date)) . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);
        $booking_info   = base64_encode($booking_id);
        $room_price     = abs($details->total_amount - $details->tax_amount);
        $room_type = trim($details->room_type, '["');
        $room_type = trim($room_type, '"]');
        // get rate plan
        preg_match_all("/\\((.*?)\\)/", $room_type, $matches);
        $rate = $matches[1][0];
        // $room_type = trim($room_type,'"("'.$rate.'")"');
        $rate_plan = DB::table('kernel.rate_plan_table')->select('plan_name')->where('plan_type', $rate)->where('hotel_id', $details->hotel_id)->first();
        $total = $details->paid_amount + $details->discount_amount;

        $hotel_logo = HotelInformation::join('company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->join('image_table', 'company_table.logo', '=', 'image_table.image_id')
            ->select('image_table.image_name')
            ->where('hotels_table.hotel_id', $details->hotel_id)
            ->first();

        if (isset($hotel_logo->image_name)) {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $hotel_logo->image_name;
        } else {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';
        }

        $bookingjini_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';

        $crs_cancelation_policy = CrsCanclePolicy::where('hotel_id', $details->hotel_id)->first();
        if (!empty($crs_cancelation_policy->cancel_policy)) {
            $cancel_policy = $crs_cancelation_policy->cancel_policy;
        } else {
            $cancel_policy = $hoteldetails->cancel_policy;
        }

        $supplied_details = array(
            'invoice_id' => $formated_invoice_id, 'name' => $name[0], 'check_in' => $details->check_in, 'check_out' => $details->check_out, 'room_type' => $room_type, 'booking_date' => $details->booking_date, 'user_address' => $details->address, 'user_mobile' => $details->mobile, 'user_remark' => $details->guest_remark, 'hotel_display_name' => $details->hotel_name, 'hotel_address' => $hoteldetails->hotel_address, 'hotel_mobile' => $hoteldetails->mobile, 'image_name' => $details->hotel_image, 'total' => $total, 'paid' => $details->paid_amount, 'room_price' => $room_price, 'discount' => $details->discount_amount, 'tax_amount' => $details->tax_amount, 'hotel_email_id' => $hoteldetails->email_id, 'user_email_id' => $details->email_id, 'url' => $secureHash, 'cancel_policy' => $cancel_policy, 'total_adult' => $total_adult,
            'total_child' => $total_child, 'check_in_time' => $hoteldetails->check_in_time, 'check_out_time' => $hoteldetails->check_out_time, 'booking_info' => $booking_info, 'no_of_nights' => $no_of_nights, 'rate_plan' => $rate_plan->plan_name, 'payment_type' => $details->payment_type, 'valid_hour' => $details->valid_hour, 'validity_type' => $details->validity_type, 'hotel_logo' => $hotel_logo, 'bookingjini_logo' => $bookingjini_logo, 'expiry_time' => $details->expiry_time
        );
        $body           = $details->invoice;
        $body           = str_replace("#####", $booking_id, $body);
        if ($payment_type == 1 || $payment_type == 2) {
            if ($mail_type == 'Confirmation') {
                if ($this->sendMail($email, $supplied_details, "Booking Confirmation Voucher", $hoteldetails->email_id, $details->hotel_name, $details->hotel_id, $mail_type)) {
                    $res = array('status' => 1, "message" => 'Mail invoice sent successfully');
                    return response()->json($res);
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Mail invoice sending failed";
                    return response()->json($res);
                }
            }
            if ($mail_type == 'Modification') {
                if ($this->sendMail($email, $supplied_details, "Booking Modification Voucher", $hoteldetails->email_id, $details->hotel_name, $details->hotel_id, $mail_type)) {
                    $res = array('status' => 1, "message" => 'Mail invoice sent successfully');
                    return response()->json($res);
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Mail invoice sending failed";
                    return response()->json($res);
                }
            }
            if ($mail_type == 'Cancellation') {
                if ($this->sendMail($email, $supplied_details, "Booking Cancellation Voucher", $hoteldetails->email_id, $details->hotel_name, $details->hotel_id, $mail_type)) {
                    $res = array('status' => 1, "message" => 'Mail invoice sent successfully');
                    return response()->json($res);
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Mail invoice sending failed";
                    return response()->json($res);
                }
            }
        }
    }

    public function crsBookingMail($invoice_id, $payment_type, $mail_type)
    {
        $failure_message = "Send mail failed";
        $invoice = new Invoice();
        $getInvoice = $invoice::where('invoice_id', $invoice_id)->first();
        $id = $getInvoice->invoice_id;
        $secureHash = 'https://be.bookingjini.com/crs/payment/' . base64_encode($id);
        $booking_date = ('Y-m-d H:i:s');

        // $email=$email_id;//Agent or Corporate EMail id

        $details = Invoice::join('kernel.user_table as a', 'invoice_table.user_id', '=', 'a.user_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('crs_booking', 'crs_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->leftjoin('partner_table', 'partner_table.id', '=', 'crs_booking.partner_id')
            ->select(
                'invoice_table.hotel_name',
                'invoice_table.room_type',
                'invoice_table.total_amount',
                'invoice_table.user_id',
                'invoice_table.paid_amount',
                'invoice_table.check_in_out',
                'invoice_table.booking_date',
                'invoice_table.booking_status',
                'a.first_name',
                'invoice_table.invoice',
                'a.last_name',
                'a.email_id',
                'a.address',
                'a.mobile',
                'hotel_booking.check_in',
                'hotel_booking.check_out',
                'hotel_booking.rooms',
                'invoice_table.hotel_id',
                'invoice_table.extra_details',
                'crs_booking.guest_remark',
                'invoice_table.discount_amount',
                'invoice_table.tax_amount',
                'crs_booking.adult',
                'crs_booking.child',
                'crs_booking.guest_name',
                'crs_booking.valid_hour',
                'crs_booking.payment_type',
                'crs_booking.validity_type',
                'crs_booking.expiry_time',
                'partner_table.partner_name',
                'partner_table.address as partner_address',
                'partner_table.city',
                'partner_table.state',
                'partner_table.country'
            )
            ->where('invoice_table.invoice_id', $id)
            ->first();
        // $rate = split($details->room_type);
        $hoteldetails = DB::table('kernel.hotels_table')->select('hotels_table.hotel_address', 'hotels_table.mobile', 'hotels_table.exterior_image', 'hotels_table.email_id', 'hotels_table.company_id', 'hotels_table.cancel_policy', 'hotels_table.check_in as check_in_time', 'hotels_table.check_out as check_out_time')->where('hotel_id', $details->hotel_id)->first();
        $image_id = explode(',', $hoteldetails->exterior_image);
        $images = ImageTable::select('image_name')->where('image_id', $image_id[0])->where('hotel_id', $details->hotel_id)->first();
        if ($images) {
            $hotel_image = $images->image_name;
        } else {
            $hotel_image = "";
        }
        $email_id = explode(',', $hoteldetails->email_id);

        if (is_array($email_id)) {
            $hoteldetails->email_id = $email_id[0];
        }
        $mobile = explode(',', $hoteldetails->mobile);
        if (is_array($mobile)) {
            $hoteldetails->mobile = $mobile[0];
        }

        $formated_invoice_id = date('dmy', strtotime($details->booking_date));
        $formated_invoice_id = $formated_invoice_id . $invoice_id;
        $name = explode(',', $details->guest_name);
        $email = $details->email_id;
        $total_adult = array_sum(explode(',', $details->adult));
        $total_child = array_sum(explode(',', $details->child));
        $no_of_nights = ceil(abs(strtotime($details->check_in) - strtotime($details->check_out)) / 86400);
        $booking_id     = date("dmy", strtotime($details->booking_date)) . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);
        $booking_info   = base64_encode($booking_id);
        // $room_price     = abs($details->total_amount - $details->tax_amount);
        $room_price     = abs($details->total_amount - $details->tax_amount + $details->discount_amount);
        $room_type = trim($details->room_type, '["');
        $room_type = trim($room_type, '"]');
        // get rate plan
        preg_match_all("/\\((.*?)\\)/", $room_type, $matches);
        $rate = $matches[1][0];
        // $room_type = trim($room_type,'"("'.$rate.'")"');
        $rate_plan = DB::table('kernel.rate_plan_table')->select('plan_name')->where('plan_type', $rate)->where('hotel_id', $details->hotel_id)->first();
        // $total = $details->paid_amount + $details->discount_amount;
        $total = $details->total_amount;
        $total_amount_to_paid = $total - $details->paid_amount;

        $hotel_logo = HotelInformation::join('company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->join('image_table', 'company_table.logo', '=', 'image_table.image_id')
            ->select('image_table.image_name')
            ->where('hotels_table.hotel_id', $details->hotel_id)
            ->first();

        if (isset($hotel_logo->image_name)) {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $hotel_logo->image_name;
        } else {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';
        }

        $bookingjini_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';

        $crs_cancelation_policy = CrsCanclePolicy::where('hotel_id', $details->hotel_id)->first();
        if (!empty($crs_cancelation_policy->cancel_policy)) {
            $cancel_policy = $crs_cancelation_policy->cancel_policy;
        } else {
            $cancel_policy = $hoteldetails->cancel_policy;
        }

        $supplied_details = array(
            'invoice_id' => $formated_invoice_id, 'name' => $name[0], 'check_in' => $details->check_in, 'check_out' => $details->check_out, 'room_type' => $room_type, 'booking_date' => $details->booking_date, 'user_address' => $details->address, 'user_mobile' => $details->mobile, 'user_remark' => $details->guest_remark, 'hotel_display_name' => $details->hotel_name, 'hotel_address' => $hoteldetails->hotel_address, 'hotel_mobile' => $hoteldetails->mobile, 'image_name' => $details->hotel_image, 'total' => $total, 'paid' => $details->paid_amount, 'room_price' => $room_price, 'discount' => $details->discount_amount, 'tax_amount' => $details->tax_amount, 'hotel_email_id' => $hoteldetails->email_id, 'user_email_id' => $details->email_id, 'url' => $secureHash, 'cancel_policy' => $cancel_policy, 'total_adult' => $total_adult,
            'total_child' => $total_child, 'check_in_time' => $hoteldetails->check_in_time, 'check_out_time' => $hoteldetails->check_out_time, 'booking_info' => $booking_info, 'no_of_nights' => $no_of_nights, 'rate_plan' => $rate_plan->plan_name, 'payment_type' => $details->payment_type, 'valid_hour' => $details->valid_hour, 'validity_type' => $details->validity_type, 'hotel_logo' => $hotel_logo, 'bookingjini_logo' => $bookingjini_logo, 'expiry_time' => $details->expiry_time, 'partner_name' => $details->partner_name, 'partner_address' => $details->partner_address, 'city' => $details->city, 'state' => $details->state, 'country' => $details->country, 'total_amount_to_paid' => $total_amount_to_paid
        );

        $body           = $details->invoice;
        $body           = str_replace("#####", $booking_id, $body);
        if ($payment_type == 1 || $payment_type == 2) {
            if ($mail_type == 'Confirmation') {
                if ($this->sendMail($email, $supplied_details, "Booking Confirmation Voucher", $hoteldetails->email_id, $details->hotel_name, $details->hotel_id, $mail_type)) {
                    $res = array('status' => 1, "message" => 'Mail invoice sent successfully');
                    return response()->json($res);
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Mail invoice sending failed";
                    return response()->json($res);
                }
            }
            if ($mail_type == 'Modification') {
                if ($this->sendMail($email, $supplied_details, "Booking Modification Voucher", $hoteldetails->email_id, $details->hotel_name, $details->hotel_id, $mail_type)) {
                    $res = array('status' => 1, "message" => 'Mail invoice sent successfully');
                    return response()->json($res);
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Mail invoice sending failed";
                    return response()->json($res);
                }
            }
            if ($mail_type == 'Cancellation') {
                if ($this->sendMail($email, $supplied_details, "Booking Cancellation Voucher", $hoteldetails->email_id, $details->hotel_name, $details->hotel_id, $mail_type)) {
                    $res = array('status' => 1, "message" => 'Mail invoice sent successfully');
                    return response()->json($res);
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Mail invoice sending failed";
                    return response()->json($res);
                }
            }
        }
    }

    public function sendMail($email, $supplied_details, $subject, $hotel_email, $hotel_name, $hotel_id, $mail_type)
    {
        $data = array('email' => $email, 'subject' => $subject);
        $data['hotel_name'] = $hotel_name;
        if ($hotel_id == 2065  || $hotel_id == 2881  || $hotel_id == 2882  || $hotel_id == 2883  || $hotel_id == 2884  || $hotel_id == 2885  || $hotel_id == 2886) {
            if ($hotel_email == "") {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'otdc@panthanivas.com'];
            } else {
                $data['hotel_email'] = $hotel_email;
                $mail_array = ['trilochan.parida@5elements.co.in', 'otdc@panthanivas.com'];
            }
        } else {
            if ($hotel_email == "") {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in'];
            } else {
                $data['hotel_email'] = $hotel_email;
                $mail_array = ['trilochan.parida@5elements.co.in'];
            }
        }
        $data['mail_array'] = $mail_array;

        if ($mail_type == 'Confirmation') {
            $template = 'emails.crsBookingConfirmationTemplate';
        }
        if ($mail_type == 'Cancellation') {
            $template = 'emails.crsBookingCancellationTemplate';
        }
        if ($mail_type == 'Modification') {
            $template = 'emails.crsBookingModificationTemplate';
        }

        try {
            Mail::send(['html' => $template], $supplied_details, function ($message) use ($data) {
                if ($data['hotel_email'] != "") {
                    $message->to($data['email'])
                        ->cc($data['hotel_email'])
                        ->bcc($data['mail_array'])
                        ->from(env("MAIL_FROM"), $data['hotel_name'])
                        ->subject($data['subject']);
                } else {
                    $message->to($data['email'])
                        ->from(env("MAIL_FROM"), $data['hotel_name'])
                        ->subject($data['subject']);
                }
            });
            if (Mail::failures()) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return true;
        }
    }

    // public function sendMail($email, $supplied_details, $subject, $hotel_email, $hotel_name, $hotel_id, $mail_type)
    // {
    //     $data = array('email' => $email, 'subject' => $subject);
    //     $data['hotel_name'] = $hotel_name;
    //     if ($hotel_id == 2065  || $hotel_id == 2881  || $hotel_id == 2882  || $hotel_id == 2883  || $hotel_id == 2884  || $hotel_id == 2885  || $hotel_id == 2886) {
    //         if ($hotel_email == "") {
    //             $data['hotel_email'] = "";
    //             $mail_array = ['trilochan.parida@5elements.co.in', 'otdc@panthanivas.com'];
    //         } else {
    //             $data['hotel_email'] = $hotel_email;
    //             $mail_array = ['trilochan.parida@5elements.co.in', 'otdc@panthanivas.com'];
    //         }
    //     } else {
    //         if ($hotel_email == "") {
    //             $data['hotel_email'] = "";
    //             $mail_array = ['trilochan.parida@5elements.co.in'];
    //         } else {
    //             $data['hotel_email'] = $hotel_email;
    //             $mail_array = ['trilochan.parida@5elements.co.in'];
    //         }
    //     }

    //     if ($mail_type == 'Confirmation') {
    //         $template = 'emails.crsBookingConfirmationTemplate';
    //     }
    //     if ($mail_type == 'Cancellation') {
    //         $template = 'emails.crsBookingCancellationTemplate';
    //     }
    //     if ($mail_type == 'Modification') {
    //         $template = 'emails.crsBookingModificationTemplate';
    //     }

    //     try {
    //         Mail::send(['html' => $template], $supplied_details, function ($message) use ($data) {
    //             if ($data['hotel_email'] != "") {
    //                 $message->to($data['email'])
    //                     ->cc($data['hotel_email'])
    //                     ->bcc($data['mail_array'])
    //                     ->from(env("MAIL_FROM"), $data['hotel_name'])
    //                     ->subject($data['subject']);
    //             } else {
    //                 $message->to($data['email'])
    //                     ->from(env("MAIL_FROM"), $data['hotel_name'])
    //                     ->subject($data['subject']);
    //             }
    //         });
    //         if (Mail::failures()) {
    //             return false;
    //         }
    //         return true;
    //     } catch (Exception $e) {
    //         return true;
    //     }
    // }

    // Cron Job with Booking Cancellation //
    /**
     * Author @Siri
     * This function is used as cronjob to check payment validity and cancel booking
     */
    public function crsBookingCronJob(Request $request)
    {
        $crsBooking = new CrsBooking();
        $current_date_time = date("Y-m-d h:i:s");

        $getCrsData = $crsBooking::where('updated_status', 0)->where('payment_type', 1)->get();
        if (sizeof($getCrsData) > 0) {
            foreach ($getCrsData as $data) {
                if ($data->paymet_link_status == 'invalid') {
                    return true;
                } else {
                    if (!empty($data->expiry_time)) {
                        if ($current_date_time >= $data->expiry_time) {
                            $update_invalid = $crsBooking::where('invoice_id', $data->invoice_id)->update([
                                'payment_link_status' => 'invalid',
                                'payment_status' => 'cancel', 'updated_status' => 2, 'booking_status' => 3
                            ]);
                            //Cancel Booking
                            $booking_cancel = $this->cancelBooking($data->invoice_id, 'cronjob');
                            return $booking_cancel;
                        }
                    }
                }
            }
        }
    }

    public function crsPayBooking($booking_id)
    {
        $crsBooking = new CrsBooking();
        $current_date = date("Y-m-d h:i:s");
        $invoice_id = substr(base64_decode($booking_id), 6);
        $crs_details = $crsBooking::where('invoice_id', $invoice_id)->first();
        if ($crs_details) {
            if ($crs_details->payment_link_status == 'invalid' || (!empty($crs_details->expiry_time) && $current_date >= $crs_details->expiry_time)) {
                return '<!DOCTYPE html>
                          <html>
                          <head>
                          <title>Check Payment link</title>
                          </head>
                          <body style="background-color : gray; margin-top:200px; margin-left:400px; margin-right:400px;">
                          <div style="border:1px solid black; background-color :white; padding:50px;">
                            <p style="color:red; text-align: center">Sorry! Payment Link Has Expired!</p>
                          </div>
                          </body>
                          </html>';
            } else {
                $invoice_id = base64_encode($invoice_id);
                $payment_link = "https://be.bookingjini.com/payment/$invoice_id/$crs_details->secure_hash";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $payment_link);
                curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $resp = curl_exec($ch);
                curl_close($ch);
                return $resp;
            }
        }
    }
    public function crsReservation(Request $request)
    {
        $failure_message = "Field should not be blank";
        $validator = Validator::make($request->all(), $this->rules, $this->message);
        if ($validator->fails()) {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'error' => $validator->errors()));
        }
        $data = $request->all();
        $crs_reservation = array();
        $room_type_id_info = $data['room_type_id'];
        $hotel_id = $data['hotel_id'];
        $from_date = date('Y-m-d', strtotime($data['from_date']));
        $to_date = date('Y-m-d', strtotime($data['to_date']));
        $p_start = $from_date;
        $p_end = $to_date;
        $period     = new \DatePeriod(
            new \DateTime($p_start),
            new \DateInterval('P1D'),
            new \DateTime($p_end)
        );
        $to_date = date('Y-m-d', strtotime($data['to_date'] . '-1 days'));
        $get_room_no = MasterRoomType::select('total_rooms')
            ->where('room_type_id', $room_type_id_info)
            ->where('hotel_id', $hotel_id)
            ->first();
        if (isset($get_room_no->total_rooms)) {
            $room_number = $get_room_no->total_rooms;
        }
        $internalRemark = 'NA';
        $guestRemark = 'NA';
        $modification_remark = 'NA';
        $p = 1;
        $be_booking = DB::table('booking_engine.invoice_table')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('booking_engine.hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
            ->select(
                'company_table.commission',
                'user_table.first_name',
                'user_table.last_name',
                'user_table.email_id',
                'user_table.address',
                'user_table.company_name',
                'user_table.GSTIN',
                'user_table.mobile',
                'user_table.user_id',
                'invoice_table.room_type',
                'invoice_table.extra_details',
                'invoice_table.total_amount',
                'invoice_table.paid_amount',
                'invoice_table.booking_date',
                'invoice_table.check_in_out',
                'invoice_table.booking_status',
                'invoice_table.tax_amount',
                'invoice_table.booking_source',
                'invoice_table.invoice_id',
                'invoice_table.hotel_name',
                'hotel_booking.hotel_booking_id',
                'hotel_booking.check_in',
                'hotel_booking.check_out',
                'hotel_booking.rooms',
                'hotel_booking.room_type_id'
            )
            ->where('invoice_table.hotel_id', '=', $hotel_id)
            ->whereBetween('hotel_booking.check_in', array($from_date, $to_date))
            ->where('invoice_table.booking_status', '=', 1)
            ->where('hotel_booking.room_type_id', '=', $room_type_id_info)
            ->orWhere(function ($query) use ($hotel_id, $from_date, $to_date, $room_type_id_info) {
                $query->where('invoice_table.hotel_id', '=', $hotel_id)
                    ->where('hotel_booking.check_in', '<', $from_date)
                    ->where('hotel_booking.check_out', '>', $from_date)
                    ->where('hotel_booking.room_type_id', '=', $room_type_id_info)
                    ->where('invoice_table.booking_status', '=', 1);
            })
            ->groupBy('invoice_table.invoice_id')
            ->orderBy('invoice_table.invoice_id', 'DESC')
            ->get();
        $total_room_qty = array();
        foreach ($be_booking as $data_up) {
            array_push($total_room_qty, $data_up->rooms);
            $adult = array();
            $child = array();
            $room_dlt = json_decode($data_up->room_type);
            $modify_status = sizeof($room_dlt);
            if ($modify_status > 1) {
                $is_modify = 1;
            } else {
                $is_modify = 0;
            }
            $room_dlt = substr($room_dlt[0], 2);
            // get rate plan
            preg_match_all("/\\((.*?)\\)/", $room_dlt, $matches);
            if (!empty($matches[1])) {
                $rate = json_encode($matches[1][0]);
                $rate = trim($rate, '"');
                $rate_plan = DB::table('kernel.rate_plan_table')->select('rate_plan_id')->where('plan_type', $rate)->where('hotel_id', $hotel_id)->first();
                $rate_plan_id = $rate_plan->rate_plan_id;
            } else {
                $rate_plan_id = 'NA';
            }
            $data_up->invoice_id_details = $data_up->invoice_id;
            $bk_id = $data_up->invoice_id;
            $booking_id     = date("dmy", strtotime($data_up->booking_date)) . str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
            $data_up->invoice_id     =  $booking_id;
            $payment_type = 0;
            $valid_hour = 0;
            $valid_type = 0;
            $crs_user_info = [];
            if ($data_up->booking_source == 'CRS') {
                $crs_booker_name = CrsBooking::select('*')->where('invoice_id', $bk_id)->where('hotel_id', $hotel_id)->first();
                if ($crs_booker_name) {
                    if (isset($crs_booker_name->guest_name)) {
                        $crs_user_info = explode(',', $crs_booker_name->guest_name);
                    }
                    $internalRemark = $crs_booker_name->internal_remark;
                    $guestRemark = $crs_booker_name->guest_remark;
                    $payment_type = $crs_booker_name->payment_type;
                    $valid_hour = $crs_booker_name->valid_hour;
                    $valid_type = $crs_booker_name->validity_type;
                    $modification_remark = $crs_booker_name->modification_remark;
                }
            }
            if ($data_up->check_in < $from_date) {
                $check_in = date('Y-m-d', strtotime($from_date));
            } else {
                $check_in = date('Y-m-d', strtotime($data_up->check_in));
            }

            $check_out = date('Y-m-d', strtotime($data_up->check_out));

            if ($room_type_id_info == $data_up->room_type_id) {
                for ($i = 0; $i < $data_up->rooms; $i++) {
                    if ($data_up->booking_source == 'CRS') {
                        if (isset($crs_user_info[$i])) {
                            $user_name = $crs_user_info[$i];
                        } else {
                            $user_name = $data_up->first_name . ' ' . $data_up->last_name;
                        }
                    } else {
                        $user_name = $data_up->first_name . ' ' . $data_up->last_name;
                    }
                    $user_name_disp = $data_up->first_name . ' ' . $data_up->last_name;
                    $extra_details = json_decode($data_up->extra_details, true);
                    for ($j = 0; $j < sizeof($extra_details); $j++) {
                        $keys = array_keys($extra_details[$j]);
                        $adult[] = $extra_details[$j][$keys[0]][0];
                        $child[] = $extra_details[$j][$keys[0]][1];
                    }
                    $be_booking_array = array(
                        "id" => $p,
                        "booking_id" => $bk_id,
                        "first_name" => $data_up->first_name,
                        "last_name" => $data_up->last_name,
                        "text" => strtoupper($user_name),
                        "username" => strtoupper($user_name_disp),
                        "bubbleHtml" => "Reservation details: <br/>$user_name",
                        "email" => $data_up->email_id,
                        "address" => $data_up->address,
                        "contact" => $data_up->mobile,
                        "user_id" => $data_up->user_id,
                        "room_type" => $room_dlt,
                        "paid" => $data_up->total_amount,
                        "paid_amount" => $data_up->paid_amount,
                        "booking_date" => date('d M Y', strtotime($data_up->booking_date)),
                        "tax_amount" => $data_up->tax_amount,
                        "start" => $check_in,
                        "end" => $check_out,
                        "check_in" => date('d M Y', strtotime($check_in)),
                        "check_out" => date('d M Y', strtotime($check_out)),
                        "check_in_dis" => date('Y-m-d', strtotime($check_in)),
                        "check_out_dis" => date('Y-m-d', strtotime($check_out)),
                        "status" => 'Confirm',
                        "booking_source" => $data_up->booking_source,
                        "invoice_id" => $data_up->invoice_id,
                        "room_type_id" => $data_up->room_type_id,
                        "hotel_name" => $data_up->hotel_name,
                        "hotel_booking_id" => $data_up->hotel_booking_id,
                        "rooms_qty" => $data_up->rooms,
                        "payment_status" => 'paid',
                        "disp_booking_id" => $data_up->invoice_id,
                        "internal_remark" => $internalRemark,
                        "guest_remark" => $guestRemark,
                        "extra_details" => $data_up->extra_details,
                        "company_name" => $data_up->company_name,
                        "gst" => $data_up->GSTIN,
                        "rate_plan_id" => $rate_plan_id,
                        "address" => $data_up->address,
                        "payment_type" => $payment_type,
                        "user_name" => $crs_user_info,
                        "adult" => $adult,
                        "child" => $child,
                        "valid_hour" => $valid_hour,
                        "valid_type" => $valid_type,
                        "is_modify" => $is_modify,
                        "total_room_qty" => $total_room_qty,
                        "modification_remark" => $modification_remark
                    );
                    array_push($crs_reservation, $be_booking_array);
                    $p++;
                }
            }
        }
        $ota_bookings =  CmOtaBookingRead::select(DB::raw("distinct ota_id,hotel_id,unique_id,customer_details,booking_status,channel_name,payment_status,inclusion,no_of_adult,no_of_child,rooms_qty,room_type,checkin_at,
        tax_amount,checkout_at,booking_date,rate_code,amount,currency,payment_status,confirm_status,cancel_status,ip,ids_re_id"))
            ->where('hotel_id', '=', $hotel_id)
            ->whereBetween('checkin_at', array($from_date, $to_date))
            ->where('cancel_status', '=', 0)
            ->where('confirm_status', '=', 1)
            ->orWhere(function ($query) use ($hotel_id, $from_date, $to_date) {
                $query->where('hotel_id', '=', $hotel_id)
                    ->where('checkin_at', '<', $from_date)
                    ->where('checkout_at', '>', $from_date)
                    ->where('cancel_status', '=', 0)
                    ->where('confirm_status', '=', 1);
            })
            ->orderBy('unique_id', 'DESC')
            ->get();
        foreach ($ota_bookings as $ota_booking_data) {
            $ota_booking_data->inclusion = explode(',', $ota_booking_data->inclusion);
            $customer_data = explode(',', $ota_booking_data->customer_details);
            $ota_booking_data->username = $customer_data[0];
            if (isset($customer_data[1])) {
                $ota_booking_data->email = $customer_data[1];
            } else {
                $ota_booking_data->email = 'NA';
            }
            if (isset($customer_data[2])) {
                $ota_booking_data->contact = $customer_data[2];
            } else {
                $ota_booking_data->contact = 'NA';
            }
            $adult_data = explode(',', $ota_booking_data->no_of_adult);
            $child_data = explode(',', $ota_booking_data->no_of_child);
            $rate_code = $this->getRate_plan($ota_booking_data->room_type, $ota_booking_data->ota_id, $ota_booking_data->rate_code);
            $room_type = $this->getRoom_types($ota_booking_data->room_type, $ota_booking_data->ota_id);
            $room_type_id = $this->getRoom_types_id($ota_booking_data->room_type, $ota_booking_data->ota_id);
            if ($ota_booking_data->checkin_at < $from_date) {
                $check_in = date('Y-m-d', strtotime($from_date));
            } else {
                $check_in = date('Y-m-d', strtotime($ota_booking_data->checkin_at));
            }
            $check_out = date('Y-m-d', strtotime($ota_booking_data->checkout_at));

            foreach ($room_type as $key => $rms) {
                if ($room_type_id_info == $room_type_id[$key]) {
                    for ($i = 0; $i < $ota_booking_data->rooms_qty; $i++) {
                        $ota_booking_array = array(
                            "id" => $p,
                            "booking_id" => $ota_booking_data->unique_id,
                            "customer_details" => $ota_booking_data->customer_details,
                            "status" => $ota_booking_data->booking_status,
                            "booking_source" => $ota_booking_data->channel_name,
                            "no_of_adult" => $adult_data[$key],
                            "no_of_child" => isset($child_data[$key]) ? $child_data[$key] : 0,
                            "rooms_qty" => $ota_booking_data->rooms_qty,
                            "room_type" => $rms,
                            "room_type_id" => $room_type_id[$key],
                            "start" => $check_in,
                            "end" => $check_out,
                            "check_in_dis" => date('Y-m-d', strtotime($check_in)),
                            "check_out_dis" => date('Y-m-d', strtotime($check_out)),
                            "check_in" => date('d M Y', strtotime($check_in)),
                            "check_out" => date('d M Y', strtotime($check_out)),
                            "tax_amount" => $ota_booking_data->tax_amount,
                            "booking_date" => date('d M Y', strtotime($ota_booking_data->booking_date)),
                            "rate_code" => $rate_code[$key],
                            "paid" => $ota_booking_data->amount,
                            "paid_amount" => $ota_booking_data->amount,
                            "text" => strtoupper($ota_booking_data->username),
                            "username" => strtoupper($ota_booking_data->username),
                            "bubbleHtml" => "Reservation details: <br/>$ota_booking_data->username",
                            "email" => $ota_booking_data->email,
                            "contact" => $ota_booking_data->contact,
                            "payment_status" => $ota_booking_data->payment_status,
                            "disp_booking_id" => $ota_booking_data->unique_id,
                            "internal_remark" => 'NA',
                            "guest_remark" => 'NA',
                            "modification_remark" => 'NA'
                        );
                        array_push($crs_reservation, $ota_booking_array);
                        $p++;
                    }
                }
            }
        }
        $crs_reservation = $this->msort($crs_reservation, array('start', 'booking_date'));
        $resource_alocater = array();
        $startArray = array();
        $endArray = array();
        $j = 1;
        foreach ($period as $key1 => $value) {
            $index = $value->format('Y-m-d');
            foreach ($crs_reservation as $key => $val) {
                if ($val['start'] == $index) {
                    if (in_array($val['start'], $endArray)) {
                        foreach ($resource_alocater as $ind => $info) {
                            if ($info['date'] == $val['start']) {
                                $resource_no = $info['resource'];
                                $crs_reservation[$key]['resource'] = $resource_no;
                                $resource_alocater[] = array('date' => $val['end'], 'resource' => $resource_no);
                                unset($resource_alocater[$ind]);
                                $end_ind = array_search($val['start'], $endArray);
                                unset($endArray[$end_ind]);
                                break;
                            }
                        }
                    } else {
                        $getStatus = $this->getEndarrayData($endArray, $val['start']);
                        if ($getStatus) {
                            foreach ($resource_alocater as $ind => $info) {
                                if ($info['date'] <= $val['start']) {
                                    $resource_no = $info['resource'];
                                    $crs_reservation[$key]['resource'] = $resource_no;
                                    $resource_alocater[] = array('date' => $val['end'], 'resource' => $resource_no);
                                    unset($resource_alocater[$ind]);
                                    $end_ind = array_search($info['date'], $endArray);
                                    unset($endArray[$end_ind]);
                                    break;
                                }
                            }
                        } else {
                            if (in_array($val['start'], $startArray)) {
                                $crs_reservation[$key]['resource'] = $j;
                                $resource_alocater[] = array('date' => $val['end'], 'resource' => $j);
                                $j++;
                            } else {
                                $j = 1;
                                $crs_reservation[$key]['resource'] = $j;
                                $resource_alocater[] = array('date' => $val['end'], 'resource' => $j);
                                $j++;
                            }
                        }
                    }
                    $startArray[] = $val['start'];
                    $endArray[]   = $val['end'];
                    $crs_reservation[$key]['start'] = date("Y-m-d\Th:i:s", strtotime($val['start']));
                    $crs_reservation[$key]['end'] = date("Y-m-d\Th:i:s", strtotime($val['end']));
                }
            }
        }
        if ($crs_reservation) {
            $res = array('status' => 1, "message" => 'Bookings retrieve successfully', 'data' => $crs_reservation);
            return response()->json($res);
        } else {
            $res = array('status' => 1, "message" => 'Bookings retrieve fails');
            return response()->json($res);
        }
    }
    public function getEndarrayData($endArray, $start)
    {
        $resp = 0;
        foreach ($endArray as $endvalue) {
            if ($endvalue <= $start) {
                $resp =  1;
            }
        }
        return $resp;
    }
    public function msort($array, $key, $sort_flags = SORT_REGULAR)
    {
        if (is_array($array) && count($array) > 0) {
            if (!empty($key)) {
                $mapping = array();
                foreach ($array as $k => $v) {
                    $sort_key = '';
                    if (!is_array($key)) {
                        $sort_key = $v[$key];
                    } else {
                        foreach ($key as $key_key) {
                            $sort_key .= $v[$key_key];
                        }
                        $sort_flags = SORT_STRING;
                    }
                    $mapping[$k] = $sort_key;
                }
                asort($mapping, $sort_flags);
                $sorted = array();
                foreach ($mapping as $k => $v) {
                    $sorted[] = $array[$k];
                }
                return $sorted;
            }
        }
        return $array;
    }
    public function getRoom_types_id($room_type, $ota_id)
    {
        $cmOtaRoomTypeSynchronize = new CmOtaRoomTypeSynchronizeRead();
        $room_types = explode(',', $room_type);
        $hotel_room_type_id = array();
        foreach ($room_types as $ota_room_type) {
            $room_id = $cmOtaRoomTypeSynchronize->getRoomTypeId($ota_room_type, $ota_id);
            if ($room_id === 0) {
                array_push($hotel_room_type_id, "Room type is not synced with OTA");
            } else {
                array_push($hotel_room_type_id, $room_id);
            }
        }
        return $hotel_room_type_id;
    }
    public function getRoom_types($room_type, $ota_id)
    {
        $cmOtaRoomTypeSynchronize = new CmOtaRoomTypeSynchronizeRead();
        $room_types = explode(',', $room_type);
        $hotel_room_type = array();
        foreach ($room_types as $ota_room_type) {
            $room = $cmOtaRoomTypeSynchronize->getRoomType($ota_room_type, $ota_id);
            if ($room === 0) {
                array_push($hotel_room_type, "Room type is not synced with OTA");
            } else {
                array_push($hotel_room_type, $room);
            }
        }
        return $hotel_room_type;
    }
    public function getRate_plan($ota_room_type, $ota_id, $rate_plan_id)
    {
        $cmOtaRatePlanSynchronize = new CmOtaRatePlanSynchronizeRead();
        $rate_plan_ids = explode(',', $rate_plan_id);
        $hotel_rate_plan = array();
        foreach ($rate_plan_ids as $ota_rate_plan_id) {
            array_push($hotel_rate_plan, $cmOtaRatePlanSynchronize->getRoomRatePlan($ota_id, $ota_rate_plan_id));
        }

        return $hotel_rate_plan;
    }
    public function getTotalInvByHotel($hotel_id, $date_from, $date_to, $mindays, Request $request)
    {
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime($date_to));
        $roomType = new MasterRoomType();
        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
        $from = strtotime($date_from);
        $to = strtotime($date_to);
        $dif_dates = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $j = 0;
        for ($i = $from; $i <= $to; $i += 86400) {
            $dif_dates[$j] = date("Y-m-d", $i);
            $j++;
        }
        $room_types = MasterRoomType::select('room_type', 'room_type_id')->where($conditions)->orderBy('room_type_table.room_type_id', 'ASC')->get();
        if ($room_types) {
            foreach ($room_types as $key => $room) {
                $k = 0;
                $data = $this->invService->getInventeryByRoomTYpe($room['room_type_id'], $date_from, $date_to, $mindays);
                $room['inv'] = $data;
                for ($i = 0; $i < $diff; $i++) {
                    $count[$k] = $room['inv'][$i]['no_of_rooms'];
                    $k++;
                }
                $count_inv[$key] = $count;
            }
            $res = array('status' => 1, 'message' => "Total inventory number retrieved successfully ", 'count' => $count_inv);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => "Total inventory number retrieval failed");
        }
    }
    public function getRoomDetails($hotel_id, $room_type_id_info)
    {
        $get_room_no = MasterRoomType::select('total_rooms')
            ->where('room_type_id', $room_type_id_info)
            ->where('hotel_id', $hotel_id)
            ->first();
        $room_number = $get_room_no->total_rooms;
        $row_wise_room = array();
        for ($k = 1; $k <= $room_number; $k++) {
            $room_details = array(
                "id" => $k,
                "name" => "Room$k"
            );
            array_push($row_wise_room, $room_details);
        }
        if ($row_wise_room) {
            $res = array('status' => 1, "message" => 'Bookings retrieve successfully', 'data' => $row_wise_room);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Bookings retrieve fails');
            return response()->json($res);
        }
    }
    // CRS Cancellation Booking //
    /**
     * @author siri date:
     * This function is used for Booking cancellation by frontend
     */
    public function crsCancelBooking(Request $request)
    {
        $data = $request->all();
        $invoice_id = substr($data['invoice_id'], 6);
        $res = $this->cancelBooking($invoice_id, 'cancel');
        return $res;
    }

    /**
     * Author @Siri
     * This function is used for Booking cancellationa and in Booking expiry i.e by cronjob
     */
    public function cancelBooking($invoiceid, $type)
    {
        $crsBooking = new CrsBooking();
        $invoice =  new Invoice();
        $hotel_booking = new HotelBooking();
        $room_types = array();
        $invoice_id = $invoiceid;
        $mail_type = 'Cancellation';

        $get_booking_details = $hotel_booking::where('invoice_id', $invoice_id)->get();
        if (sizeof($get_booking_details) > 0) {
            foreach ($get_booking_details as $data) {
                $hotel_id = $data['hotel_id'];
                $booking_details['checkin_at'] = $data['check_in'];
                $booking_details['checkout_at'] = $data['check_out'];
                $room_type[] = $data['room_type_id'];
                $rooms_qty[] = $data['rooms'];
                $ids_id = $data['ids_re_id'];
            }
            if ($type == 'cancel') {
                // cancel data update in IDS
                try {
                    $url = 'https://cm.bookingjini.com/crs_cancel_push_to_ids';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $invoice_id);
                    $status = curl_exec($ch);
                    curl_close($ch);
                    $ids = $status;
                } catch (Exception $e) {
                    $error_log = new ErrorLog();
                    $storeError = array(
                        'hotel_id'      => $inv_data['hotel_id'],
                        'function_name' => 'IdsXmlCreationAndExecutionController.pushIdsCrs',
                        'error_string'  => $e
                    );
                    $insertError = $error_log->fill($storeError)->save();
                }

                $getPMS_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'GEMS')->first();
                $hotel_info = explode(',', $getPMS_details->hotels);
                if (in_array($hotel_id, $hotel_info)) {
                    $pushBookignToGems = $this->bookingEngineController->pushBookingToGems($invoice_id, 'cancel');
                }
            } else {
                $ids = $ids_id;
            }
            $update_invoice_booking = $invoice::where('invoice_id', $invoice_id)->update(['booking_status' => 3, 'ids_re_id' => $ids]);
            $update_hotel_booking = $hotel_booking::where('invoice_id', $invoice_id)->update(['booking_status' => 3]);
            // $refund = $this->crsCancelRefund($invoice_id);
            $update_invalid = $crsBooking::where('invoice_id', $invoice_id)->update(['booking_status' => 3, 'payment_link_status' => 'invalid', 'payment_status' => 'cancel', 'updated_status' => 1]);

            //This code is use to tracking hotelier Activity
            if(isset($get_booking_details->invoice_id)){
                $booking_id2= date('dmy').$invoice_id;
                $activity_name = "";
                $activity_id = "a19";
                $activity_from = "CRS";
                $activity_description = "Cancelled CRS Booking received booking id $booking_id2";
                $user_id2=$get_booking_details->user_id;
                $hotel_id2=$get_booking_details->hotel_id;
                captureHotelActivityLog($hotel_id2, $user_id2, $activity_id, $activity_name, $activity_description, $activity_from);
            }
            
            $cancel = $this->bookingCancel($invoice_id, $hotel_id, $booking_details, $room_type, $rooms_qty);
            if ($cancel == 1) {
                if ($type != 'modify') { //mail only for cancellation
                    $this->crsBookingMail($data->invoice_id, 0, $mail_type);
                }
                $res = array('status' => 1, "message" => 'Booking Cancellation Successful');
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => 'Booking Cancellation Failed');
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, "message" => 'Booking Not found');
            return response()->json($res);
        }
    }

    public function bookingCancel($invoice_id, $hotel_id, $booking_details, $room_type, $rooms_qty)
    {
        $booking_status = 'Cancel';
        $ota_id = 0; // Crs as BookingEngine
        $rooms = array();
        $ota_hotel_details = new CmOtaDetails();

        for ($i = 0; $i < sizeof($room_type); $i++) {
            $get_ota_room = CmOtaRoomTypeSynchronizeRead::where('hotel_id', $hotel_id)->where('room_type_id', $room_type[$i])->first();
            if ($get_ota_room) {
                $ota_room_type[] = $get_ota_room->ota_room_type;
                $ota_type_id = $get_ota_room->ota_type_id;
            }
        }

        $is_cm_active = Invoice::where('invoice_id', $invoice_id)->first();

        if (isset($ota_room_type) && sizeof($ota_room_type) >= 0 && $is_cm_active->cm == 1) {

            $ota_room_type = implode(',', $ota_room_type);
            $rooms_qty = implode(',', $rooms_qty);
            // //update inventory in cm_ota_inv_status
            // $cancel_booking = $this->cmOtaBookingInvStatusService->saveCurrentInvStatus($invoice_id,$ota_id,$hotel_id,$booking_details['checkin_at'],$booking_details['checkout_at'],$ota_room_type,$booking_status,$rooms_qty);
            // if($cancel_booking){
            //     $ota_hotel_details['ota_id'] = $ota_type_id;
            //     $ota_hotel_details['hotel_id'] = $hotel_id;

            //     //update in bucket data (cm_ota_booking_push_bucket)
            //     $this->instantBucketController->bucketEngineUpdate($booking_status,'Bookingjini',$ota_hotel_details,$invoice_id);
            // }
            // return $cancel_booking;
            try {
                $cmOtaBookingInvStatusService = array('invoice_id' => $invoice_id, 'ota_id' => $ota_id, 'hotel_id' => $hotel_id, 'check_in' => $booking_details['checkin_at'], 'check_out' => $booking_details['checkout_at'], 'room_type' => $ota_room_type, 'booking_status' => $booking_status, 'room_qty' => $rooms_qty, 'ota_type_id' => $ota_type_id);
                $cmOtaBookingInvStatusPush = http_build_query($cmOtaBookingInvStatusService);
                $url = 'https://cm.bookingjini.com/cm_ota_booking_inv_status';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $cmOtaBookingInvStatusPush);
                $status = curl_exec($ch);
                curl_close($ch);
                return $status;
            } catch (Exception $e) {
                $error_log = new ErrorLog();
                $storeError = array(
                    'hotel_id'      => $inv_data['hotel_id'],
                    'function_name' => 'CmOtaBookingInvStatusService.saveCurrentInvStatus',
                    'error_string'  => $e
                );
                $insertError = $error_log->fill($storeError)->save();
            }
        } else {

            $j = 0;
            for ($i = 0; $i < sizeof($room_type); $i++) {

                $date1 = date_create($booking_details['checkin_at']);
                $date2 = date_create($booking_details['checkout_at']);
                $diff = date_diff($date1, $date2);
                $diff = $diff->format("%a");

                $from_date = date('Y-m-d', strtotime($booking_details['checkin_at']));
                if ($diff == 0) {
                    $to_date = date('Y-m-d', strtotime($booking_details['checkout_at'] . '+1 day'));
                } else {
                    $to_date = date('Y-m-d', strtotime($booking_details['checkout_at']));
                }

                $p_start = $from_date;
                $p_end = $to_date;
                $period     = new \DatePeriod(
                    new \DateTime($p_start),
                    new \DateInterval('P1D'),
                    new \DateTime($p_end)
                );

                foreach ($period as $key => $value) {
                    $inventory = new Inventory();
                    $index = $value->format('Y-m-d');

                    $dp_current_inv = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type[$i])
                        ->where('stay_day', $index)
                        ->where('ota_id', -1)
                        ->orderBy('id', 'DESC')
                        ->first();

                    $total_inv = $dp_current_inv->no_of_rooms + $rooms_qty[$i];

                    $inv_table = Inventory::where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type[$i])
                        ->where('date_from', '<=', $index)
                        ->where('date_to', '>=', $index)
                        ->where('block_status', 0)
                        ->orderBy('inventory_id', 'DESC')
                        ->first();

                    if (!empty($inv_table)) {
                        $update_inv_details = [
                            'hotel_id'              => $hotel_id,
                            'room_type_id'          => $room_type[$i],
                            'no_of_rooms'           => $total_inv,
                            'date_from'             => $from_date,
                            'date_to'               => $to_date,
                            'client_ip'             => $inv_table['client_ip'],
                            'user_id'               => $inv_table['user_id'],
                            'block_status'          => $inv_table->block_status,
                            'los'                   => $inv_table['los'],
                            'multiple_days'         => $inv_table['multiple_days']
                        ];
                        $inventory->fill($update_inv_details)->save();
                    }

                    $current_inv = array(
                        "hotel_id"      => $hotel_id,
                        "room_type_id"  => $room_type[$i],
                        "ota_id"        => -1,
                        "stay_day"      => $index,
                        "block_status"  => 0,
                        "no_of_rooms"  => $total_inv,
                        "ota_name"      => "Bookingjini"
                    );
                    $cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
                        [
                            'hotel_id' => $hotel_id,
                            'room_type_id' => $room_type[$i],
                            'ota_id' => -1,
                            'stay_day' => $index,
                        ],
                        $current_inv
                    );

                    $cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
                        [
                            'hotel_id' => $hotel_id,
                            'room_type_id' => $room_type[$i],
                            'ota_id' => -1,
                            'stay_day' => $index,
                        ],
                        $current_inv
                    );

                    if ($update_inv_details) {
                        $update_inventory = Inventory::where('hotel_id', $hotel_id)->where('room_type_id', $room_type[$i])->insert($update_inv_details);
                        if ($update_inventory) {
                            $j = $j + 1;
                        }
                    }
                }
            }

            if ($j == sizeof($room_type)) {
                return 1;
            } else {
                return 0;
            }
        }
    }
    // CRS Modification Booking //
    /**
     * @author Siri
     * Dt: 30-01-2021
     * This function is used for Modify CRS Booking
     */
    public function crsModifyBooking(Request $request)
    {
        $invoice =  new Invoice();
        $hotel_booking = new HotelBooking();
        $data = $request->all();
        $invoice_id = substr($data['invoice_id'], 6);
        $hotel_id = $data['hotel_id'];
        $room_type_id = $data['room_type_id'];
        $rate_plan_id = $data['rate_plan_id'];
        $modify_check_in = $data['check_in'];
        $modify_check_out = $data['check_out'];
        $modified_date = date('Y-m-d H:i:s');
        $no_of_rooms = $data['rooms'];
        $cart = $data['data']['cart'];
        $cart_data = $data['data']['cart'][0];
        $rooms = $cart_data['rooms'];
        $guest_data = $data['data']['guest_details'][0];
        $total_amount = 0;
        $adult_price = 0;
        $child_price = 0;
        $room_price = 0;
        $modify_date = date('Y-m-d');

        $extra_details_arr = array();
        $mail_type = 'Modification';
        $no_of_nights = abs(strtotime($modify_check_in) - strtotime($modify_check_out)) / 86400;
        $previous_booking = CrsBooking::join('invoice_table', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
            ->select(
                'discount_amount',
                'paid_amount',
                'extra_details',
                'user_id',
                'crs_booking.no_of_rooms',
                'crs_booking.room_type_id',
                'invoice_table.user_id',
                'invoice_table.ref_no',
                'invoice_table.booking_date'
            )->where('invoice_table.invoice_id', $invoice_id)->where('invoice_table.hotel_id', $hotel_id)->first();

        $previous_rooms = $previous_booking->no_of_rooms;
        $roomtype = $previous_booking->room_type_id;
        $booking_date = $previous_booking->booking_date;
        $extra_details = json_decode($previous_booking->extra_details, true);
        for ($i = 0; $i < sizeof($extra_details); $i++) {
            $keys = array_keys($extra_details[$i]);
            $adult[] = $extra_details[$i][$keys[0]][0];
            $child[] = $extra_details[$i][$keys[0]][1];
        }



        //cancel previous booking details with invoice id
        // $cancel_previous_booking = $this->cancelBooking($invoice_id,'modify');
        // if($cancel_previous_booking){
        //get inventory & rates data by check-in and check-out dates
        $hotel_data = HotelInformation::where('hotel_id', $hotel_id)->first();
        $room_rate_details = $this->bookingEngineController->getInvByHotel((string)$hotel_data->company_id, $hotel_id, $modify_check_in, $modify_check_out, 'INR', $request);
        $room_data = $room_rate_details->getData();

        $prepare_room_type_data = [];
        $paid_amount = 0;
        $gst_amount = 0;
        $prepare_room = '';

        HotelBooking::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->delete();

        foreach ($cart as $data) {
            $room_type_name = $data['room_type'];
            $plan_type = $data['plan_type'];
            $gst_amount += $data['tax'][0]['gst_price'];
            $paid_amount += $data['paid_amount'];
            $rooms = $data['rooms'];
            for ($i = 0; $i < sizeof($rooms); $i++) {
                $bar_price = $rooms[$i]['bar_price'];
                $adult = $rooms[$i]['selected_adult'];
                $child = $rooms[$i]['selected_child'];
                $adult_price += $rooms[$i]['extra_adult_price'];
                $child_price += $rooms[$i]['extra_child_price'];
                $room_price = $bar_price;
                array_push($extra_details_arr, array($room_type_id => array($adult, $child)));  //---create array of extra details with modified room type--//
            }
            //prepare room type
            $prepare_room .= '"' . $no_of_rooms . ' ' . $room_type_name . '(' . $plan_type . ')' . '",';


            //---------------------update hotel_booking table------------------------------//

            $update_htl_bkng = HotelBooking::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->insert(['invoice_id' => $invoice_id, 'hotel_id' => $hotel_id, 'rooms' => $no_of_rooms, 'room_type_id' => $data['room_type_id'], 'check_in' => $modify_check_in, 'check_out' => $modify_check_out, 'booking_date' => $booking_date, 'modify_date' => $modify_date, 'modify_status' => '1', 'booking_status' => 1]);
        }

        // $max_adult = $cart_data['max_people'];
        // $max_child = $cart_data['max_child'];
        // $max_room_capacity = $cart_data['max_room_capacity'];
        // $plan_name = $cart_data['plan_name'];
        // $total_amount = $room_price + $adult_price + $child_price; //total amount
        // $total_amount_gst =  $paid_amount + $gst_amount;

        //---------------------update invoice_table-----------------------------------//
        $invoice_room_type = '[' . rtrim($prepare_room, ',') . ']';
        $check_in_out = '[' . $modify_check_in . '-' . $modify_check_out . ']';
        $update_invoice = Invoice::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->update(['check_in_out' => $check_in_out, 'room_type' => $invoice_room_type, 'total_amount' => $paid_amount, 'paid_amount' => $paid_amount, 'tax_amount' => $gst_amount, 'booking_date' => $modified_date, 'booking_status' => 1, 'extra_details' => json_encode($extra_details_arr)]);

        //----------------------update crs_booking table-------------------------------//
        //----calculate valid hour--------//
        $valid_type = $guest_data['valid_type'];
        $valid_hour = (int)$guest_data['valid_hour'];
        if ($valid_type == 1) {   // hours
            $expiry_time = date("Y-m-d H:i:s", strtotime('+' . $valid_hour . ' hours'));
        } elseif ($valid_type == 2) { // days
            $expiry_time = date("Y-m-d H:i:s", strtotime('+' . $valid_hour . ' days'));
        } else {
            $expiry_time = '';
        }

        //-------update secure hash---------//
        $cur_invoice = Invoice::where('ref_no', $previous_booking->ref_no)->first();
        $user_data = User::where('user_id', $previous_booking->user_id)->first();
        $b_invoice_id = base64_encode($cur_invoice->invoice_id);
        $invoice_hashData = $cur_invoice->invoice_id . '|' . $cur_invoice->total_amount . '|' . $cur_invoice->paid_amount . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;
        $invoice_secureHash = hash('sha512', $invoice_hashData);

        $update_crs_bkng = CrsBooking::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->update(['check_in' => $modify_check_in, 'check_out' => $modify_check_out, 'no_of_rooms' => $no_of_rooms, 'room_type_id' => $room_type_id, 'total_amount' => $paid_amount, 'guest_name' => $guest_data['guest_name'], 'adult' => $guest_data['no_of_adult'], 'child' => $guest_data['no_of_child'], 'payment_type' => $guest_data['payment_type'], 'internal_remark' => $guest_data['internal_remark'], 'guest_remark' => $guest_data['guest_remark'], 'validity_type' => $valid_type, 'valid_hour' => $valid_hour, 'expiry_time' => $expiry_time, 'modification_remark' => $guest_data['modify_remark'], 'payment_link_status' => 'valid', 'booking_status' => 1, 'secure_hash' => $invoice_secureHash]);

        if ($update_invoice && $update_htl_bkng && $update_crs_bkng) {
            $all_cart[] = $cart_data;
            $inv_data = $this->bookingEngineController->handleIds($all_cart, $modify_check_in, $modify_check_out, $modified_date, $hotel_id, $previous_booking->user_id, 'Modify');
            //update modified ids_re_id in invoice table
            $update_ids_id = Invoice::where('invoice_id', $invoice_id)->update(['ids_re_id' => $inv_data]);
            $res = $this->bookingEngineController->crsBooking($invoice_id, 'crs', $request)->getData();
            if ($res->status == 1) {
                $this->crsBookingMail($invoice_id, 0, $mail_type);
                $res = array('status' => 1, "message" => 'Booking Modified Successful', "id" => $inv_data);
                return response()->json($res);
            }
        }
        // }
    }

    public function crsRegisterUserModify(Request $request)
    {
        $data = $request->all();
        $user_id = $data['user_id'];
        $update = [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'company_name' => $data['company_name'],
            'email_id'   => $data['email_id'],
            'mobile' => $data['mobile'],
            'GSTIN'  => $data['GST_IN'],
            'address'  => $data['address']
        ];
        $update_user = User::where('user_id', $user_id)->update($update);
        if ($update_user) {
            $res = array('status' => 1, 'message' => 'User Data Modified Successful');
            return response()->json($res);
        }
    }
    // CRS Cancellation Refund Amount //
    /**
     * @author Siri
     * Dt: 02-02-2021
     * This function is used to get the refund amount by cancel booking depend upon the days befor checkin date */
    public function crsCancelRefund($invoice_id)
    {
        $get_today = date("Y-m-d");
        $ref_per = 0;
        $refund_amount = 0;
        $crs_booking_data = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')->select('hotel_booking.check_in', 'invoice_table.total_amount', 'invoice_table.hotel_id')->where('hotel_booking.invoice_id', $invoice_id)->first();
        if ($crs_booking_data) {
            $check_in = $crs_booking_data->check_in;
            $total_amount = $crs_booking_data->total_amount;
            $hotel_id = $crs_booking_data->hotel_id;
        }
        if ($check_in >= $get_today) {
            $days = abs(strtotime($check_in) - strtotime($get_today)) / 86400;
            if ($days >= 0) {
                $get_refund_days = CancellationPolicy::where('hotel_id', $hotel_id)->first();
                if ($get_refund_days) {
                    $closest = null;
                    $refund_days = $get_refund_days->policy_data;
                    $refund_days = trim(trim($refund_days, '['), ']');
                    $refund_days = explode(',', $refund_days);
                    for ($i = 0; $i < sizeof($refund_days); $i++) {
                        $ref_data = explode(':', $refund_days[$i]);
                        $ref_per = $ref_data[1];
                        $daterange = explode('-', $ref_data[0]);
                        if ($days  >= $daterange[1] && $days  <= $daterange[0]) {
                            $refund_amount = $total_amount * ($ref_per / 100);
                        }
                    }
                } else {
                    $refund_amount = 0;
                }
            } else {
                $refund_amount = 0;
            }
        }
        return $refund_amount;
    }
    // CRS Cancel Booking Report //
    /**
     * @author Siri
     * Dt: 02-02-2021
     * This function is used to get the Crs Cancellation Booking Report Data
     */
    public function crsCacelReportData(Request $request)
    {
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $crs_data_array = array();
        $crs_data = array();
        $date = "01-" . $data['date'];
        $date = date('Y-m-d', strtotime($date));
        $last_date = date('Y-m-t', strtotime($date));
        $get_data = CrsBooking::join('hotel_booking', 'crs_booking.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.user_table', 'kernel.user_table.user_id', '=', 'hotel_booking.user_id')
            ->join('kernel.room_type_table', 'kernel.room_type_table.room_type_id', '=', 'hotel_booking.room_type_id')
            ->select(
                DB::raw('SUBSTRING_INDEX(crs_booking.guest_name,",",1) AS guest_name'),
                'kernel.user_table.address',
                'hotel_booking.booking_date',
                'hotel_booking.check_in',
                'hotel_booking.check_out',
                'kernel.room_type_table.room_type',
                'crs_booking.total_amount',
                'crs_booking.updated_at',
                'crs_booking.refund_amount'
            )->where('hotel_booking.booking_date', '>=', $date)->where('hotel_booking.booking_date', '<=', $last_date)
            ->where('crs_booking.hotel_id', $hotel_id)->where('crs_booking.booking_status', 3)->orderBY('hotel_booking.booking_date', 'desc')->paginate(8);
        $res = array('status' => 1, 'message' => 'Data Retrived Successful', 'data' => $get_data);
        return response()->json($res);
    }

    public function crsBookingEnquiry(Request $request)
    {
        $data = $request->all();

        $cart = $data['cart'];
        $checkin_date = $data['from_date'];
        $checkout_date = $data['to_date'];
        $no_of_nights = abs(strtotime($checkin_date) - strtotime($checkout_date)) / 86400;

        if (sizeof($cart) > 0) {
            $count = 0;
            foreach ($cart as $cart_details) {
                $extra_details = array();

                $selected_adult = 0;
                $selected_child = 0;
                foreach ($cart_details['rooms'] as $room) {
                    array_push($extra_details, array($cart_details['room_type_id'] => array($room['selected_adult'], $room['selected_child'])));

                    $selected_adult += $room['selected_adult'];
                    $selected_child += $room['selected_child'];
                }

                $no_of_rooms = sizeof($cart_details['rooms']);
                $room_type_name = $cart_details['room_type'];
                $plan_type = $cart_details['plan_type'];
                $room_type = '["' . $no_of_rooms . ' ' . $room_type_name . '(' . $plan_type . ')' . '"]';
                $check_in_out = '[' . $checkin_date . '-' . $checkout_date . ']';

                $booking_date['hotel_id'] = $data['hotel_id'];
                $booking_date['room_type_id'] =  $cart_details['room_type_id'];
                $booking_date['rate_plan_id'] =  $cart_details['rate_plan_id'];
                $booking_date['from_date'] = date('Y-m-d', strtotime($checkin_date));
                $booking_date['to_date'] = date('Y-m-d', strtotime($checkout_date));
                $booking_date['booking_date'] = date('Y-m-d H:i:s');
                $booking_date['no_of_nights'] = $no_of_nights;
                $booking_date['extra_details'] = json_encode($extra_details);
                $booking_date['no_of_adult'] = $selected_adult;
                $booking_date['no_of_child'] = $selected_child;
                $booking_date['room_type'] = $room_type;
                $booking_date['check_in_out'] = $check_in_out;
                $booking_date['discount_percent'] =  $cart_details['discount_percent'];
                $booking_date['discounted_price'] = $cart_details['discounted_price'];
                $booking_date['paid_amount'] =  $cart_details['paid_amount'];
                $booking_date['paid_amount_per'] =  $cart_details['paid_amount_per'];
                $booking_date['tax_amount'] =  $cart_details['tax'][0]['gst_price'];
                // $booking_date['guest_details'] = $data['guest_details'];

                $booking_details = CrsBookingInquery::insert($booking_date);
                if ($booking_details) {
                    $count += 1;
                } else {
                    CrsBookingInquery::where('invoice_id', $invoice_id)->delete();
                    $res = array('status' => 0, "message" => 'Booking Details saved Failed');
                    return response()->json($res);
                }
            }
            if (sizeof($cart) == $count) {
                $res = array('status' => 1, "message" => "Inquiry details captured successfully");
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => 'Inquiry details captured Failed');
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, "message" => 'Inquiry details captured Failed');
            return response()->json($res);
        }
    }

    public function crsBookingInqueryDetails($hotel_id)
    {
        $inqueryDetails = CrsBookingInquery::join('kernel.room_type_table', 'crs_booking_inquiry.room_type_id', '=', 'room_type_table.room_type_id')
            ->join('kernel.rate_plan_table', 'crs_booking_inquiry.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
            ->select('crs_booking_inquiry.hotel_id', 'crs_booking_inquiry.from_date', 'crs_booking_inquiry.to_date', 'crs_booking_inquiry.paid_amount', 'crs_booking_inquiry.discounted_price', 'crs_booking_inquiry.tax_amount', 'crs_booking_inquiry.guest_details', 'room_type_table.room_type', 'rate_plan_table.plan_type', 'crs_booking_inquiry.no_of_adult', 'crs_booking_inquiry.no_of_child')
            ->where('crs_booking_inquiry.hotel_id', $hotel_id)->get();

        if (sizeof($inqueryDetails) > 0) {
            $res = array('status' => 1, "message" => 'Booking details saved Successfully', 'data' => $inqueryDetails);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Booking details Fetch Failed');
            return response()->json($res);
        }
    }

    public function crsBookingReport(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $date = $request->date;
        $payment_type = $request->payment_type;

        $date = "01-" . $date;
        $checkin = date('Y-m-d', strtotime($date));
        $checkout = date('Y-m-t', strtotime($date));

        $booking_details = CrsBooking::join('invoice_table', 'crs_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->join('kernel.room_type_table', 'crs_booking.room_type_id', '=', 'kernel.room_type_table.room_type_id')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'kernel.user_table.user_id')
            ->select('crs_booking.hotel_id', 'invoice_table.hotel_name', 'crs_booking.guest_name', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_source', 'invoice_table.user_id', 'invoice_table.booking_date', 'crs_booking.check_in', 'crs_booking.check_out', 'crs_booking.no_of_rooms', 'crs_booking.room_type_id', 'crs_booking.adult', 'crs_booking.child', 'crs_booking.payment_type', 'crs_booking.payment_link_status', 'room_type_table.room_type', 'crs_booking.payment_status', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile', 'user_table.address')
            ->where('crs_booking.check_in', '>=', $checkin)
            ->where('crs_booking.check_out', '<=', $checkout)
            ->where('invoice_table.booking_status', 1)
            ->where('invoice_table.hotel_id', $hotel_id)
            ->where('invoice_table.booking_source', 'CRS')
            ->where('crs_booking.payment_type', $payment_type)
            ->paginate(12);

        if (sizeof($booking_details) > 0) {
            $crs_booking_details = [];
            foreach ($booking_details as $booking_detail) {
                if ($booking_detail->payment_type == 1) {
                    $booking_detail->payment_type = 1;
                    $booking_detail->payment_type_name = 'Email with payment link';
                } elseif ($booking_detail->payment_type == 2) {
                    $booking_detail->payment_type = 2;
                    $booking_detail->payment_type_name = 'Email with no payment link';
                } elseif ($booking_detail->payment_type == 3) {
                    $booking_detail->payment_type = 3;
                    $booking_detail->payment_type_name = 'No email , no payment link';
                }
                if (isset($booking_detail->guest_name)) {
                    $guest_name = explode(',', $booking_detail->guest_name);
                    $booking_detail->guest_name = $guest_name[0];
                }
            }


            if (sizeof($booking_details) > 0) {
                $res = array('status' => 1, "message" => 'Booking Report fetch Successfully', 'data' => $booking_details);
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => 'Booking details Fetch Failed');
                return response()->json($res);
            }
        } else {
            $res = array('status' => 0, "message" => 'No Record Found!');
            return response()->json($res);
        }
    }


    public function crsTodayArrivalReport(int $hotel_id)
    {
        $checkin = date('Y-m-d');

        $booking_details = HotelBooking::join('invoice_table', 'hotel_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->select('invoice_table.hotel_name', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_source', 'hotel_booking.check_in', 'hotel_booking.check_out')
            ->where('hotel_booking.check_in', $checkin)
            ->where('invoice_table.booking_status', 1)
            ->where('invoice_table.hotel_id', $hotel_id)
            ->where('invoice_table.booking_source', 'CRS')
            ->get();

        if (sizeof($booking_details) > 0) {
            $res = array('status' => 1, "message" => 'Arrival Report fetch Successfully', 'data' => $booking_details);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Arrival details Fetch Failed');
            return response()->json($res);
        }
    }

    public function crsTodayDispatchReport(int $hotel_id)
    {
        $checkout = date('Y-m-d');

        $booking_details = HotelBooking::join('invoice_table', 'hotel_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->select('invoice_table.hotel_name', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_source', 'hotel_booking.check_in', 'hotel_booking.check_out')
            ->where('hotel_booking.check_out', $checkout)
            ->where('invoice_table.booking_status', 1)
            ->where('invoice_table.hotel_id', $hotel_id)
            ->where('invoice_table.booking_source', 'CRS')
            ->get();

        if (sizeof($booking_details) > 0) {
            $res = array('status' => 1, "message" => 'Dispatch Report fetch Successfully', 'data' => $booking_details);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Dispatch details Fetch Failed');
            return response()->json($res);
        }
    }

    public function crsHoldBookingReport(int $hotel_id, string $checkin, string $checkout)
    {
        $checkin = date('Y-m-d', strtotime($checkin));
        $checkout = date('Y-m-d', strtotime($checkout));

        $current_time = date('Y-m-d h:i:s');

        $booking_details = CrsBooking::join('invoice_table', 'crs_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->select('invoice_table.hotel_name', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_source', 'crs_booking.check_in', 'crs_booking.check_out', 'crs_booking.expiry_time')
            ->where('crs_booking.check_in', '>=', $checkin)
            ->where('crs_booking.check_in', '<=', $checkout)
            ->where('crs_booking.expiry_time', '>', $current_time)
            ->where('invoice_table.booking_status', 1)
            ->where('invoice_table.hotel_id', $hotel_id)
            ->where('invoice_table.booking_source', 'CRS')
            ->get();

        if (sizeof($booking_details) > 0) {
            $res = array('status' => 1, "message" => 'Dispatch Report fetch Successfully', 'data' => $booking_details);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Dispatch details Fetch Failed');
            return response()->json($res);
        }
    }
    public function GstWiseCompanyDetail($gst_in)
    {
        $select_company_detail = User::where('GSTIN', $gst_in)->select('company_name', 'address')->first();
        if ($select_company_detail) {
            return response()->json(array('status' => 1, 'message' => "data fetch suscessfully", 'data' => $select_company_detail));
        } else {
            return response()->json(array('status' => 0, 'message' => "data fetch failed"));
        }
    }
    public function SelectGstIn()
    {
        $select_company_detail = User::select('user_id', 'role_id', 'company_id', 'club_id', 'title', 'first_name', 'last_name', 'company_name', 'email_id', 'address', 'mobile', 'zip_code', 'country', 'state', 'city', 'telephone', 'GSTIN', 'website', 'member_photo', 'company_logo', 'registered_date', 'change_number_status', 'status')->where('GSTIN', '!=', '')->where('GSTIN', '!=', 'NA')->get();
        if (trim($select_company_detail)) {
            return response()->json(array('status' => 1, 'message' => "data fetch suscessfully", 'data:' => $select_company_detail));
        } else {
            return response()->json(array('status' => 0, 'message' => "data fetch failed"));
        }
    }

    public function crsModifiedGuestDetails(Request $request)
    {
        $details = $request->all();
        $guest_name = explode(' ', $details['guest_name']);
        if (count($guest_name) > 1) {
            $lastname = array_pop($guest_name);
            $firstname = implode(" ", $guest_name);
        } else {
            $firstname = $details['guest_name'];
            $lastname = " ";
        }

        $guest_details = [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'email_id' => $details['email_id'],
            'user_address' => $details['guest_address'],
        ];


        $guest_details['company_name'] = $details['company_name'];
        $guest_details['GSTIN'] = $details['gstin'];
        $guest_details['address'] = $details['company_address'];



        $users_details = User::where('mobile', $details['mobile'])->update($guest_details);

        if ($users_details) {
            return response()->json(array('status' => 1, 'message' => "Guest Details Updated Successfully"));
        } else {
            return response()->json(array('status' => 0, 'message' => "Guest Details Updated Failed"));
        }
    }

    public function crsModifyBookingTest(Request $request)
    {
        $invoice =  new Invoice();
        $hotel_booking = new HotelBooking();
        $data = $request->all();
        $invoice_id = substr($data['booking_id'], 6);
        $hotel_id = $data['hotel_id'];
        $modified_date = date('Y-m-d H:i:s');
        $modify_date = date('Y-m-d');
        $room_details = [];
        $cart = [];
        $adult1 = 0;
        $child1 = 0;
        $paid_amount = 0;
        $gst_amount = 0;
        $prepare_room = '';
        $mail_type = 'Modification';
        $check_sourse = Invoice::where('invoice_id', $invoice_id)->first();

        //---------------------Fetched Previous Booking Details------------------------------//
        if ($check_sourse->booking_source == 'CRS') {
            $previous_booking = CrsBooking::join('invoice_table', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
                ->select('discount_amount', 'paid_amount', 'extra_details', 'user_id', 'crs_booking.no_of_rooms', 'crs_booking.room_type_id', 'crs_booking.check_in', 'crs_booking.check_out', 'crs_booking.validity_type', 'crs_booking.valid_hour', 'crs_booking.expiry_time', 'invoice_table.user_id', 'invoice_table.ref_no', 'invoice_table.booking_date', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'invoice_table.room_type')
                ->where('invoice_table.invoice_id', $invoice_id)
                ->where('invoice_table.hotel_id', $hotel_id)
                ->first();
        } else {
            $previous_booking = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                ->select('invoice_table.user_id', 'invoice_table.ref_no', 'invoice_table.booking_date', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'hotel_booking.room_type_id', 'hotel_booking.rooms', 'hotel_booking.check_in', 'hotel_booking.check_out', 'invoice_table.extra_details', 'invoice_table.room_type')
                ->where('invoice_table.invoice_id', $invoice_id)
                ->where('invoice_table.hotel_id', $hotel_id)
                ->first();
        }

        // $cancel_previous_booking = $this->cancelBooking($invoice_id,'modify');
        // if ($cancel_previous_booking) {

        //---------------------Check Modified Type------------------------------//
        if ($data['modify_type'] == 'date') {
            $modify_check_in = $data['check_in'];
            $modify_check_out = $data['check_out'];
            $room_type_id = $previous_booking->room_type_id;
            $no_of_rooms = $previous_booking->no_of_rooms;
            $paid_amount = $previous_booking->paid_amount;
            $gst_amount = $previous_booking->tax_amount;
        } else {
            $modify_check_in = $previous_booking->check_in;
            $modify_check_out = $previous_booking->check_out;
            $room_type_data = $data['room_type_data'];
            $including_tax =  $data['including_tax'];
            $room_type_id = $room_type_data[0]['room_type_id'];

            // $no_of_rooms = 0;
            // if ($room_type_data) {
            //     foreach ($room_type_data as $room_type) {
            //         $no_of_rooms +=  $room_type['no_of_rooms'];
            //     }
            // }
            $check_in = date_create($modify_check_in);
            $check_out = date_create($modify_check_out);
            $diff = date_diff($check_in, $check_out);
            $nights = $diff->format("%a");
        }

        // $hotel_data = HotelInformation::where('hotel_id',$hotel_id)->first();
        // $room_rate_details = $this->bookingEngineController->getInvByHotel((string)$hotel_data->company_id,$hotel_id,$modify_check_in,$modify_check_out,'INR',$request);
        // $room_data=$room_rate_details->getData();

        $extra_details = json_decode($previous_booking->extra_details, true);
        $details = DB::table('rate_plan_log_table')
            ->join('kernel.room_type_table', 'rate_plan_log_table.room_type_id', 'room_type_table.room_type_id')
            ->join('kernel.rate_plan_table', 'rate_plan_log_table.rate_plan_id', 'rate_plan_table.rate_plan_id')
            ->select('room_type_table.room_type', 'rate_plan_table.plan_type', 'rate_plan_table.plan_name', 'rate_plan_log_table.bar_price', 'rate_plan_log_table.extra_adult_price', 'rate_plan_log_table.extra_child_price', 'rate_plan_table.rate_plan_id')
            ->where('rate_plan_log_table.room_type_id', $previous_booking->room_type_id)
            ->where('rate_plan_log_table.hotel_id', $hotel_id)
            ->orderBy('rate_plan_log_table.rate_plan_log_id', 'DESC')
            ->first();

        //---------------------Prepaired Cart------------------------------//
        if ($extra_details) {
            foreach ($extra_details as $extra_detail) {
                foreach ($extra_detail as $key => $extra) {
                    if ($key == $room_type_id) {
                        $adult1 = $extra[0];
                        $child1 = $extra[1];
                    }
                    array_push($room_details, array('selected_adult' => $adult1, 'selected_child' => $child1, 'rate_plan_id' => $details->rate_plan_id, 'extra_adult_price' => $details->extra_adult_price, 'extra_child_price' => $details->extra_child_price, 'bar_price' => $details->bar_price));
                }
            }
        }

        array_push($cart, array('room_type_id' => $room_type_id, 'room_type' => $details->room_type, 'plan_type' => $details->plan_type, 'rooms' => $room_details));

        $extra_details = json_decode($previous_booking->extra_details, true);
        for ($i = 0; $i < sizeof($extra_details); $i++) {
            $keys = array_keys($extra_details[$i]);
            $adult[] = $extra_details[$i][$keys[0]][0];
            $child[] = $extra_details[$i][$keys[0]][1];
        }
        if (isset($room_type_data)) {
            $paid_amount = 0;
            $gst_amount = 0;
            foreach ($room_type_data as $room_type) {
                $prepare_room .= '"' . $room_type['no_of_rooms'] . ' ' . $room_type['room_type'] . '(' . $room_type['meal_plan'] . ')' . '",';

                if ($including_tax == 1) {
                    // $single_room_price = $room_type['room_price'] / $room_type['no_of_rooms']  / $nights;
                    $single_room_price = $room_type['room_price'];
                    if ($single_room_price > 1000 && $single_room_price < 1121.12 || $single_room_price > 8401 && $single_room_price < 8851.18) {
                        $res = array('status' => 0, "message" => 'Invalid Price');
                        return response()->json($res);
                    } else {
                        $gstPercent = $this->bookingEngineController->checkGSTPercent($room_type['room_type_id'], $single_room_price);
                        $gst_price = (($single_room_price) * $gstPercent) / 100;
                        $gst = round($gst_price, 2);

                        $room_price_with_out_gst = $single_room_price - $gst;
                        $gst_amount += $gst * $room_type['no_of_rooms'] * $nights;
                        $paid_amount += $room_price_with_out_gst * $room_type['no_of_rooms'] * $nights;
                    }
                } else {
                    $single_room_price = $room_type['room_price'];
                    $gstPercent = $this->bookingEngineController->checkGSTPercent($room_type['room_type_id'], $single_room_price);
                    $gst_price = (($single_room_price) * $gstPercent) / 100;
                    $gst = round($gst_price, 2);
                    $room_price_with_out_gst = $single_room_price + $gst;
                    $gst_amount += $gst * $room_type['no_of_rooms'] * $nights;
                    $paid_amount += $room_price_with_out_gst * $room_type['no_of_rooms'] * $nights;
                }
            }

            $invoice_room_type = '[' . rtrim($prepare_room, ',') . ']';
        } else {
            $invoice_room_type = $previous_booking->room_type;
        }


        //---------------------update Invoice table------------------------------//
        $check_in_out = '[' . $modify_check_in . '-' . $modify_check_out . ']';
        $update_invoice = Invoice::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->update(['check_in_out' => $check_in_out, 'room_type' => $invoice_room_type, 'total_amount' => $paid_amount, 'paid_amount' => $paid_amount, 'tax_amount' => $gst_amount, 'booking_date' => $modified_date, 'booking_status' => 1,]);

        //-------update secure hash---------//
        $cur_invoice = Invoice::where('ref_no', $previous_booking->ref_no)->first();
        $user_data = User::where('user_id', $previous_booking->user_id)->first();
        $b_invoice_id = base64_encode($cur_invoice->invoice_id);
        $invoice_hashData = $cur_invoice->invoice_id . '|' . $cur_invoice->total_amount . '|' . $cur_invoice->paid_amount . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;
        $invoice_secureHash = hash('sha512', $invoice_hashData);


        //---------------------update hotel_booking table------------------------------//
        $update_htl_bkng = HotelBooking::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->update(['check_in' => $modify_check_in, 'check_out' => $modify_check_out, 'booking_status' => 1]);

        $update_crs_bkng = CrsBooking::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->update(['check_in' => $modify_check_in, 'check_out' => $modify_check_out,   'total_amount' => $paid_amount, 'payment_link_status' => 'valid', 'booking_status' => 1, 'secure_hash' => $invoice_secureHash]);




        if ($update_invoice && $update_htl_bkng && $update_crs_bkng) {
            // dd('Success');
            $inv_data = $this->bookingEngineController->handleIds($cart, $modify_check_in, $modify_check_out, $modified_date, $hotel_id, $previous_booking->user_id, 'Modify');
            //  update modified ids_re_id in invoice table
            $update_ids_id = Invoice::where('invoice_id', $invoice_id)->update(['ids_re_id' => $inv_data]);

            $getPMS_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'GEMS')->first();
            $hotel_info = explode(',', $getPMS_details->hotels);
            if (in_array($hotel_id, $hotel_info)) {
                $pushBookignToGems = $this->bookingEngineController->pushBookingToGems($invoice_id, true);
            }

            $res = $this->bookingEngineController->crsBooking($invoice_id, 'crs', $request)->getData();
            if ($res->status == 1) {
                $this->crsBookingMail($invoice_id, 0, $mail_type);
                $res = array('status' => 1, "message" => 'Booking Modified Successful', "id" => $inv_data);
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => 'Booking Modified Failed');
                return response()->json($res);
            }

            // $res = array('status' => 1, "message" => 'Booking Modified Successful');
            // return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Booking Modified Failed');
            return response()->json($res);
        }
        // }

        // return $previous_booking;
    }
    public function getAvailableRooms(Request $request)
    {
        $inventory = new Inventory();
        $hotel_id = $request->hotel_id;

        $company_details = DB::table('kernel.hotels_table')
            ->join('kernel.company_table', 'kernel.hotels_table.company_id', 'kernel.company_table.company_id')
            ->select('kernel.company_table.api_key', 'kernel.company_table.company_id', 'kernel.company_table.currency')
            ->where('kernel.hotels_table.hotel_id', $hotel_id)
            ->first();

        $api_key = $company_details->api_key;
        $currency_name = $company_details->currency;
        $no_of_rooms = $request->no_of_rooms;
        $date_from = date('Y-m-d', strtotime($request->from_date));
        $date_to  = date('Y-m-d', strtotime($request->to_date));

        $date1 = date_create($request->from_date);
        $date2 = date_create($request->to_date);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");

        // if($hotel_id == 1953){
        $paymentOptionsArrayDetails['valid_type'] =  [
            ['id' => 1, 'name' => 'Hours'],
            ['id' => 2, 'name' => 'Days'],
            ['id' => 3, 'name' => 'Never']
        ];
        // }else{
        //     $paymentOptionsArrayDetails['valid_type'] =  [
        //         ['id' => 1, 'name' => 'Hours'],
        //         ['id' => 2, 'name' => 'Days']
        //     ];
        // }


        $paymentOptionsArrayDetails['days'] =  [
            ['id' => 1, 'name' => '1 day'],
            ['id' => 2, 'name' => '2 day'],
            ['id' => 3, 'name' => '3 day'],
            ['id' => 4, 'name' => '4 day'],
            ['id' => 5, 'name' => '5 day'],
            ['id' => 6, 'name' => '6 day'],
            ['id' => 7, 'name' => '7 day']
        ];
        $paymentOptionsArrayDetails['hours'] = [
            ['id' => 1, 'name' => '1 hr'],
            ['id' => 2, 'name' => '2 hr'],
            ['id' => 4, 'name' => '4 hr'],
            ['id' => 6, 'name' => '6 hr'],
            ['id' => 8, 'name' => '8 hr'],
            ['id' => 12, 'name' => '12 hr'],
            ['id' => 16, 'name' => '16 hr'],
            ['id' => 24, 'name' => '24 hr']
        ];
        $paymentOptionsArrayDetails['payment_percentage'] =  [
            ['id' => 20, 'name' => '20 %'],
            ['id' => 50, 'name' => '50 %'],
            ['id' => 70, 'name' => '70 %'],
            ['id' => 100, 'name' => '100 %']
        ];
        if ($company_details->currency == 'INR') {
            $paymentOptionsArrayDetails['payment_options'] =  [
                ['id' => 1, 'name' => '%'],
                ['id' => 2, 'name' => '₹']
            ];
        } else {
            $paymentOptionsArrayDetails['payment_options'] =  [
                ['id' => 1, 'name' => '%'],
                ['id' => 2, 'name' => '$']
            ];
        }

        $paymentOptionsArrayDetails['currency_name'] =  $company_details->currency;

        $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
        $room_types = MasterRoomType::select('room_type', 'room_type_id')->where($conditions)->get();

        if ($room_types) {
            $available_inventory = [];
            $day_wise_room_details = [];
            $day_wise_total_room = [];
            $available_room_type = [];
            $next_inventory = [];
            $next_inventory_details = [];

            $count = 0;
            //loop for dates 
            for ($i = 1; $i <= $diff; $i++) {
                //loop for room types
                foreach ($room_types as $key => $room) {
                  
                    // if ($hotel_id == 5191) {
                        $inventory_details = DynamicPricingCurrentInventory::where('hotel_id', '=', $hotel_id)
                        ->where('room_type_id', '=', $room->room_type_id)
                        ->where('ota_id', '=', '-1')
                        ->whereDate('stay_day', '=', $date_from)
                        ->orderBy('id', 'desc')
                        ->first();
                        // array_push($next_inventory_details, $inventory_details);

                    // } else {
                    //       $inventory_details = $inventory
                    //     ->join('kernel.room_type_table', 'inventory_table.room_type_id', '=', 'room_type_table.room_type_id')
                    //     ->select('room_type_table.room_type_id', 'room_type_table.room_type', 'inventory_table.inventory_id', 'inventory_table.hotel_id', 'inventory_table.no_of_rooms', 'inventory_table.date_from', 'inventory_table.date_to', 'inventory_table.user_id', 'inventory_table.los', 'inventory_table.multiple_days')
                    //     ->where('inventory_table.hotel_id', '=', $hotel_id)
                    //     ->where('inventory_table.room_type_id', '=', $room->room_type_id)
                    //     ->whereBetween('inventory_table.date_from', [$date_from, $date_from])
                    //     // ->where('inventory_table.date_from', '<=', $date_from)
                    //     // ->where('inventory_table.date_to', '>=', $date_from)
                    //     ->orderBy('inventory_table.inventory_id', 'desc')
                    //     ->first();
                    // }
                    

                    if (isset($inventory_details)) {
                        if (!in_array($inventory_details['room_type_id'], $available_room_type)) {
                            array_push($available_room_type, $inventory_details['room_type_id']);

                            $inventory_data['room_type_id'] = $inventory_details['room_type_id'];
                            $inventory_data['room_type'] = $inventory_details['room_type'];
                            $inventory_data['hotel_id'] = $inventory_details['hotel_id'];
                            $inventory_data['no_of_rooms'] = $inventory_details['no_of_rooms'];
                            $inventory_data['date_from'] = $inventory_details['date_from'];
                            $inventory_data['date_to'] = $inventory_details['date_to'];
                            $inventory_data['user_id'] = $inventory_details['user_id'];

                            $rate_plan_details = DB::table('kernel.room_rate_plan')
                                ->join('kernel.rate_plan_table', 'kernel.room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
                                ->select('rate_plan_table.rate_plan_id', 'rate_plan_table.plan_name', 'room_rate_plan.bar_price', 'room_rate_plan.extra_adult_price', 'room_rate_plan.extra_child_price')
                                ->where('kernel.room_rate_plan.room_type_id', $room->room_type_id)->get();

                            $inventory_data['rate_plan'] = $rate_plan_details;
                            array_push($day_wise_room_details, $inventory_data);
                            //push the no of rooms.
                            array_push($available_inventory, $inventory_details['no_of_rooms']);
                        }


                        if (sizeof($day_wise_room_details) > 0) {
                            $previous_rooms = $day_wise_room_details[0]['no_of_rooms'];
                            if ($previous_rooms > $inventory_details['no_of_rooms']) {
                                $day_wise_room_details[$key]['no_of_rooms'] = $inventory_details['no_of_rooms'];
                            }
                        }
                    }

                
                    if ($available_inventory) {
                        $total_rooms = array_sum($available_inventory);
                        $day_wise_total_room[] = $total_rooms;
                        if ($total_rooms >= $no_of_rooms) {
                            $count++;
                        }
                    } else {
                        $total_rooms = 0;
                    }
                }
                $date_from = date('Y-m-d', strtotime($date_from . '+1 day'));
            }

            // if ($hotel_id == 5191) {
            //     return $next_inventory_details;
            // }



            $total_inv = 0;
            if (sizeof($day_wise_total_room) > 0) {
                $minium_available_inventory = min($day_wise_total_room);
            } else {
                $minium_available_inventory = 0;
            }

            if ($total_rooms >= $no_of_rooms) {
                $details = $this->bookingEngineController->getInvByHotel($api_key, $hotel_id, $request->from_date, $request->to_date, $currency_name, $request);
                $room_type_data = json_decode($details->getContent());
                $room_type_details = $room_type_data->data;
                $room_data = $room_type_data->room_data;

                if ($room_type_details) {
                    foreach ($room_type_details as $key => $room_type_detail) {
                        $inventory = $room_type_detail->inv;
                        foreach ($inventory as $inv) {
                            $total_inv += $inv->no_of_rooms;
                            if ($inv->no_of_rooms == 0 || $inv->block_status == 1) {
                                unset($room_type_details[$key]);
                                unset($room_data[$key]);
                            }
                        }
                        if (isset($room_type_detail->rate_plans)) {

                            foreach ($room_type_detail->rate_plans as $key1 => $rate) {
                                if (empty($rate->rates)) {
                                    unset($room_type_detail->rate_plans[$key1]);
                                }
                                if (!empty($rate->rates)) {
                                    foreach ($rate->rates as $ex_adlt_change) {
                                        $ex_adlt_change->extra_child_price = (float)$ex_adlt_change->extra_child_price;
                                    }
                                }
                            }
                            // var_dump($room_type_detail);
                            if (isset($room_type_detail->rate_plans)) {
                                if (is_array($room_type_detail->rate_plans)) {
                                    $room_type_detail->rate_plans = array_values($room_type_detail->rate_plans);
                                } else {
                                    $room_type_detail->rate_plans = (array)$room_type_detail->rate_plans;
                                    $room_type_detail->rate_plans = array_values($room_type_detail->rate_plans);
                                }
                            }
                        }

                        if (empty($room_type_detail->rate_plans)) {
                            unset($room_type_details[$key]);
                        }
                    }
                }

                $room_type_details = array_values($room_type_details);
                $room_data = array_values($room_data);
                if (!empty($room_type_details)) {
                    return response()->json(array('status' => 1, 'message' => 'Room fetched successfully.', 'data' => $room_type_details, 'room_data' => $room_data, 'paymentOptionsDetails' => $paymentOptionsArrayDetails));
                } else {
                    $minium_available_inventory = 0;
                    $consecutive_date_inv = $this->checkAletnateDatesInv($api_key, $currency_name, $request, $room_types, $hotel_id, $available_inventory, $minium_available_inventory, $diff);

                    return response()->json(array('status' => 1, 'message' => 'Room fetched successfully.', 'min_available_rooms' => $minium_available_inventory, 'alternative_dates' => $consecutive_date_inv, 'paymentOptionsDetails' => $paymentOptionsArrayDetails));
                }
            } else {

                $consecutive_date_inv = $this->checkAletnateDatesInv($api_key, $currency_name, $request, $room_types, $hotel_id, $available_inventory, $minium_available_inventory, $diff);

                return response()->json(array('status' => 1, 'message' => 'Room fetched successfully.', 'min_available_rooms' => $minium_available_inventory, 'alternative_dates' => $consecutive_date_inv, 'paymentOptionsDetails' => $paymentOptionsArrayDetails));
            }
        }
    }

    public function checkAletnateDatesInv($api_key, $currency_name, $request, $room_types, $hotel_id, $available_inventory, $minium_available_inventory, $diff)
    {
        $next_inventory = [];
        $next_inventory_details = [];
        // $inventory = new Inventory();
        $date_wise_available_inventory = [];
        $filter_available_inventory = [];
        $consecutive_date_inv = [];
        $min_rooms = [];

        $date_from = date('Y-m-d', strtotime($request->from_date));
        $to_date = date('Y-m-d', strtotime($date_from . '+60 day'));

        $diff2 = date_diff(date_create($date_from), date_create($to_date));
        $diff2 = $diff2->format("%a");

        //loop for dates  
        for ($i = 1; $i <= $diff2; $i++) {
            //loop for room types

            $details = $this->bookingEngineController->getInvByHotel($api_key, $hotel_id, $request->from_date, $request->to_date, $currency_name, $request);
            $room_type_data = json_decode($details->getContent());
            $room_type_details = $room_type_data->data;
            $room_data = $room_type_data->room_data;
            $total_inv = 0;
            if ($room_type_details) {
                foreach ($room_type_details as $key => $room_type_detail) {
                    $inventory = $room_type_detail->inv;
                    foreach ($inventory as $inv) {
                        $total_inv += $inv->no_of_rooms;
                        if ($inv->no_of_rooms == 0 || $inv->block_status == 1) {
                            unset($room_type_details[$key]);
                            unset($room_data[$key]);
                        }
                    }
                    if (isset($room_type_detail->rate_plans)) {

                        foreach ($room_type_detail->rate_plans as $key1 => $rate) {
                            if (empty($rate->rates)) {
                                unset($room_type_detail->rate_plans[$key1]);
                            }
                        }
                        // var_dump($room_type_detail);
                        if (isset($room_type_detail->rate_plans)) {
                            if (is_array($room_type_detail->rate_plans)) {
                                $room_type_detail->rate_plans = array_values($room_type_detail->rate_plans);
                            } else {
                                $room_type_detail->rate_plans = (array)$room_type_detail->rate_plans;
                                $room_type_detail->rate_plans = array_values($room_type_detail->rate_plans);
                            }
                        }
                    }

                    if (empty($room_type_detail->rate_plans)) {
                        unset($room_type_details[$key]);
                    }
                }
            }

            $room_type_details = array_values($room_type_details);
            $room_data = array_values($room_data);

            // if($hotel_id == 1953){
            foreach ($room_type_details as $room) {
                $inventory = new Inventory();
                $inventory_details = $inventory
                    ->join('kernel.room_type_table', 'inventory_table.room_type_id', '=', 'room_type_table.room_type_id')
                    ->join('kernel.room_rate_plan', 'inventory_table.room_type_id', '=', 'room_rate_plan.room_type_id')
                    ->select('room_type_table.room_type_id', 'room_type_table.room_type', 'inventory_table.inventory_id', 'inventory_table.hotel_id', 'inventory_table.no_of_rooms', 'inventory_table.date_from', 'inventory_table.date_to', 'inventory_table.user_id', 'inventory_table.los', 'inventory_table.multiple_days', 'room_rate_plan.bar_price')
                    ->where('inventory_table.hotel_id', '=', $hotel_id)
                    ->where('inventory_table.room_type_id', '=', $room->room_type_id)
                    ->where('inventory_table.date_from', '<=', $date_from)
                    ->where('inventory_table.date_to', '>=', $date_from)
                    ->orderBy('inventory_table.inventory_id', 'desc')
                    ->first();

                //push the no of rooms and inventory details
                if (isset($inventory_details->bar_price) && ($inventory_details->bar_price != 0)) {
                    array_push($next_inventory, $inventory_details->no_of_rooms);
                    array_push($next_inventory_details, $inventory_details);
                }
            }

            // }else{
            //     foreach ($room_type_details as $room) {
            //         $inventory = new Inventory();
            //       $inventory_details = $inventory
            //           ->join('kernel.room_type_table', 'inventory_table.room_type_id', '=', 'room_type_table.room_type_id')
            //           ->select('room_type_table.room_type_id', 'room_type_table.room_type', 'inventory_table.inventory_id', 'inventory_table.hotel_id', 'inventory_table.no_of_rooms', 'inventory_table.date_from', 'inventory_table.date_to', 'inventory_table.user_id', 'inventory_table.los', 'inventory_table.multiple_days')
            //           ->where('inventory_table.hotel_id', '=', $hotel_id)
            //           ->where('inventory_table.room_type_id', '=', $room->room_type_id)
            //           ->where('inventory_table.date_from', '<=', $date_from)
            //           ->where('inventory_table.date_to', '>=', $date_from)
            //           ->orderBy('inventory_table.inventory_id', 'desc')
            //           ->first();

            //       //push the no of rooms and inventory details
            //       if ($inventory_details) {
            //           array_push($next_inventory, $inventory_details->no_of_rooms);
            //           array_push($next_inventory_details, $inventory_details);
            //       }
            //   }

            // }



            //check no of rooms is available or not
            if ($available_inventory) {
                $total_rooms = array_sum($next_inventory);
                if ($total_rooms >= $request->no_of_rooms) {
                    $inv_details[] = $date_from;

                    $inv_data['hotel_id'] = $hotel_id;
                    $inv_data['no_of_rooms'] = $total_rooms;
                    $inv_data['date_from'] = $date_from;

                    array_push($date_wise_available_inventory, $inv_data);
                }
            }
            $next_inventory = [];
            $date_from = date('Y-m-d', strtotime($date_from . '+1 day'));
        }

        //Insert the available inventory in one array
        if ($date_wise_available_inventory) {
            foreach ($date_wise_available_inventory as $key => $inventory) {
                $date_from = $inventory['date_from'];
                $date_to =  date('Y-m-d', strtotime($date_from . '+1 day'));
                $check_value = in_array($date_to, $inv_details);

                $min_rooms[$inventory['date_from']] = $inventory['no_of_rooms'];

                if ($check_value) {
                    array_push($filter_available_inventory, $date_from);
                    array_push($filter_available_inventory, $date_to);
                }
            }

            $unique_values = array_unique($filter_available_inventory);

            //find the consecutive available rooms and dates.
            if ($unique_values) {
                foreach ($unique_values as $key => $unique_value) {
                    $consecutive_date =  date('Y-m-d', strtotime($unique_value . '+' . ($diff) . ' day'));
                    $check_value = in_array($consecutive_date, $unique_values);

                    if ($check_value) {
                        $available_rooms =  min($min_rooms[$inventory['date_from']], $min_rooms[$consecutive_date]);
                        $inv_data['hotel_id'] = $hotel_id;
                        $inv_data['no_of_rooms'] = $available_rooms;
                        $inv_data['date_from'] = $unique_value;
                        $inv_data['date_to'] = $consecutive_date;

                        array_push($consecutive_date_inv, $inv_data);
                    }
                }
            }
        }
        return $consecutive_date_inv;
    }


    public function businessSource()
    {

        $booking_type = DB::table('booking_type')->select('booking_type_id', 'name')->where('is_active', '1')->get();
        $business_source = DB::table('business_source')->select('business_source_id', 'name')->where('is_active', '1')->get();

        if (sizeof($booking_type) && sizeof($business_source)) {
            return response()->json(array('status' => 1, 'message' => 'Booking type and Business source fetched successfully.', 'booking_type' => $booking_type, 'business_source' => $business_source));
        } else {
            return response()->json(array('status' => 1, 'message' => 'Data fetched Failed.'));
        }
    }
    public function CrsReportDownload(int $hotel_id, $date, $payment_type)
    {
        $date = "01-" . $date;
        $checkin = date('Y-m-d', strtotime($date));
        $checkout = date('Y-m-t', strtotime($date));

        $booking_details = CrsBooking::join('invoice_table', 'crs_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->join('kernel.room_type_table', 'crs_booking.room_type_id', '=', 'kernel.room_type_table.room_type_id')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'kernel.user_table.user_id')
            ->select('crs_booking.hotel_id', 'invoice_table.hotel_name', 'crs_booking.guest_name', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_source', 'invoice_table.user_id', 'crs_booking.check_in', 'crs_booking.check_out', 'crs_booking.no_of_rooms', 'crs_booking.room_type_id', 'crs_booking.adult', 'crs_booking.child', 'crs_booking.payment_type', 'crs_booking.payment_link_status', 'room_type_table.room_type', 'crs_booking.payment_status', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile')
            ->where('crs_booking.check_in', '>=', $checkin)
            ->where('crs_booking.check_in', '<=', $checkout)
            ->where('invoice_table.booking_status', 1)
            ->where('invoice_table.hotel_id', $hotel_id)
            ->where('invoice_table.booking_source', 'CRS')
            ->where('crs_booking.payment_type', $payment_type)
            ->get();

        $row = [];
        foreach ($booking_details as $booking_detail) {

            if ($booking_detail->payment_type == 1) {
                $payment_type_status = 'Email with payment';
            } elseif ($booking_detail->payment_type == 2) {
                $payment_type_status = 'Email with no payment';
            } elseif ($booking_detail->payment_type == 3) {
                $payment_type_status = 'No email , no payment';
            }

            $hotel_id = $booking_detail->hotel_id;
            $hotel_name = $booking_detail->hotel_name;
            $guest_name = $booking_detail->guest_name;
            $total_amount = $booking_detail->total_amount;
            $paid_amount = $booking_detail->paid_amount;
            $tax_amount = $booking_detail->tax_amount;
            $discount_amount = $booking_detail->discount_amount;
            $booking_source = $booking_detail->booking_source;
            $check_in = $booking_detail->check_in;
            $check_out = $booking_detail->check_out;
            $no_of_rooms = $booking_detail->no_of_rooms;
            $room_type_id = $booking_detail->room_type;
            $payment_type = $payment_type_status;
            $payment_link_status = $booking_detail->payment_link_status;
            $payment_status =  $booking_detail->payment_status;
            $booker_name = $booking_detail->first_name . ' ' . $booking_detail->last_name;
            $email_id =  $booking_detail->email_id;
            $mobile =  $booking_detail->mobile;

            $row[] = [
                'Hotel Id' => $hotel_id,
                'Hotel Name' => $hotel_name,
                'Guest Name' => $guest_name,
                'Total Amount' => $total_amount,
                'Paid Amount' => $paid_amount,
                'Tax Amount' => $tax_amount,
                'Discount Amount' => $discount_amount,
                'Booking Source' => $booking_source,
                'Checkin Date' => $check_in,
                'Checkout Date' => $check_out,
                'No Of Rooms' => $no_of_rooms,
                'Room Type Id' => $room_type_id,
                'Payment Type' => $payment_type,
                'Paymentlink Status' => $payment_link_status,
                'Payment Status' => $payment_status,
                'Booker Name' => $booker_name,
                'Email Id' => $email_id,
                'Mobile' => $mobile,
            ];
        }

        if (sizeof($row) > 0) {

            header('Content-Type: text/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=CrsBookingReport.csv');
            $output = fopen("php://output", "w");
            fputcsv($output, array('Hotel Name', 'Hotel Name', 'Guest Name', 'Total Amount', 'Paid Amount', 'Tax Amount', 'Discount Amount', 'Booking Source', 'Checkin Date', 'Checkout Date', 'No Of Rooms', 'Room Type Id', 'Payment Type', 'Paymentlink Status', 'Payment Status', 'Booker Name', 'Email Id', 'Mobile'));
            foreach ($row as $data) {
                fputcsv($output, $data);
            }
            fclose($output);
        }
    }


    public function crsBookingDetails(Request $request)
    {
        $bookingid = $request->booking_id;
        $booking_source = $request->booking_source;
        $current_date = date('Y-m-d');

        //ota logo list
        $ota_logo['MakeMyTrip'] = '1422311435mmt.png';
        $ota_logo['Goibibo'] = '1119412432goibibo.png';
        $ota_logo['Expedia'] = '1137382374expedia.png';
        $ota_logo['Cleartrip'] = '111669874cleartip.png';
        $ota_logo['Agoda'] = '1016869990agoda.png';
        $ota_logo['Travelguru'] = '1071839720travelguru_logo.gif';
        $ota_logo['Booking.com'] = '1519602952booking.png';
        $ota_logo['Via.com'] = '529817383via.png';
        $ota_logo['Goomo'] = 'Goomo.png';
        $ota_logo['Airbnb'] = 'airbnblogo.png';
        $ota_logo['EaseMyTrip'] = 'easemytrip.png';
        $ota_logo['Paytm'] = 'paytm.png';
        $ota_logo['HappyEasyGo'] = 'happyeasygo.png';
        $ota_logo['Hostelworld'] = 'hostelworld.png';
        $ota_logo['MMTGCC'] = 'mmticon.png';
        $ota_logo['Simplotel'] = 'simplotel.png';
        $ota_logo['Onlinevacations'] = 'OnlineVacations.png';
        $ota_logo['Akbar'] = 'akbar.jpeg';
        $ota_logo['IRCTC'] = 'irctc.png';
        $ota_logo['CleartripNew'] = 'cleartrip-new.png';


        if ($booking_source == 'website' || $booking_source == 'GEMS' || $booking_source == 'CRS' || $booking_source == 'QUICKPAYMENT' || $booking_source == 'google' || $booking_source == 'jiniassist') {
            $invoice_id = substr($bookingid, 6);
            $country_id =  Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'kernel.hotels_table.hotel_id')
                ->select('hotels_table.country_id')
                ->where('invoice_table.invoice_id', $invoice_id)
                ->first();
            if ($country_id->country_id == '1') {
                $ota_logo['website'] = 'bookingjini.svg';
                $ota_logo['CRS'] = 'bookingjini.svg';
                $ota_logo['GEMS'] = 'bookingjini.svg';
                $ota_logo['QUICKPAYMENT'] = 'bookingjini.svg';
                $ota_logo['google'] = 'bookingjini.svg';
                $ota_logo['jiniassist'] = 'bookingjini.svg';
            } else {
                $ota_logo['website'] = 'kite.png';
                $ota_logo['CRS'] = 'kite.png';
                $ota_logo['GEMS'] = 'kite.png';
            }

            if ($country_id->country_id == '1') {
                $booking_details['currency_icon'] =  'fa fa-inr';
                $booking_details['currency_name'] =  'INR';
                $booking_details['tax'] =  'GST';
            } else {
                $booking_details['currency_icon'] = 'fa fa-usd';
                $booking_details['currency_name'] = 'USD';
                $booking_details['tax'] = 'VAT';
            }


            if ($booking_source == 'CRS' || $booking_source == 'crs') {
                $payment_options = CrsBooking::where('invoice_id', $invoice_id)->select('payment_type')->first();

                if ($payment_options->payment_type == 1) {
                    $booking_details['payment_options'] = 'Email with Payment link';
                } elseif ($payment_options->payment_type == 2) {
                    $booking_details['payment_options'] = 'Email with no payment link';
                } else {
                    $booking_details['payment_options'] = 'No email no payment link';
                }
            }


            $be_bookings =  Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
                ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                ->join('kernel.hotels_table', 'hotel_booking.hotel_id', '=', 'hotels_table.hotel_id')
                ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
                ->select(
                    'user_table.first_name',
                    'user_table.last_name',
                    'user_table.email_id',
                    'user_table.address',
                    'user_table.mobile',
                    'user_table.user_id',
                    'user_table.mobile',
                    'user_table.user_address',
                    'user_table.company_name',
                    'user_table.GSTIN',
                    'invoice_table.room_type',
                    'invoice_table.total_amount',
                    'invoice_table.tax_amount',
                    'invoice_table.paid_amount',
                    'invoice_table.booking_date',
                    'invoice_table.invoice',
                    'invoice_table.booking_status',
                    'invoice_table.invoice_id',
                    'invoice_table.booking_source',
                    'invoice_table.hotel_name',
                    'invoice_table.hotel_id',
                    'invoice_table.extra_details',
                    'hotel_booking.hotel_booking_id',
                    'hotel_booking.rooms',
                    'hotel_booking.check_in',
                    'hotel_booking.check_out',
                    'hotels_table.state_id',
                    'company_table.logo'
                )
                ->where('invoice_table.invoice_id', '=', $invoice_id)
                ->first();

            if ($be_bookings) {
                $check_in = date_create($be_bookings->check_in);
                $check_out = date_create($be_bookings->check_out);
                $diff = date_diff($check_in, $check_out);
                $diff = (int)$diff->format("%a");
                if ($diff == 0) {
                    $diff = 1;
                }
                $booking_details['invoice_id'] = $invoice_id;
                $booking_details['guest_name'] = $be_bookings->first_name . ' ' . $be_bookings->last_name;
                $booking_details['email_id'] = $be_bookings->email_id;
                $booking_details['mobile'] = $be_bookings->mobile;
                $booking_details['address'] = $be_bookings->address;
                if (isset($be_bookings->user_address)) {
                    $booking_details['user_address'] = $be_bookings->user_address;
                } else {
                    $booking_details['user_address'] = "";
                }
                if (empty($be_bookings->GSTIN) || $be_bookings->GSTIN == '' || $be_bookings->GSTIN == 'NULL' || $be_bookings->GSTIN == null) {
                    $booking_details['business_booking'] = 0;
                } else {
                    $booking_details['business_booking'] = 1;
                }
                $booking_details['company_name'] = $be_bookings->company_name;
                $booking_details['GSTIN'] = $be_bookings->GSTIN;
                $booking_details['nights'] = $diff;
                $booking_details['bookingid'] = $bookingid;
                $booking_details['booking_date'] = date('d M Y', strtotime($be_bookings->booking_date));
                $booking_details['checkin_at'] = date('d M Y', strtotime($be_bookings->check_in));
                $booking_details['checkout_at'] = date('d M Y', strtotime($be_bookings->check_out));
                $booking_details['price'] = $be_bookings->total_amount;
                $booking_details['tax_amount'] = $be_bookings->tax_amount;
                $booking_details['booking_source'] = $booking_source;
                $booking_details['state_id'] = $be_bookings->state_id;

                $fetchHotelLogo = ImageTable::where('image_id', $be_bookings->logo)->select('image_name')->first();
                if (isset($fetchHotelLogo->image_name)) {
                    $booking_details['channel_logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $fetchHotelLogo->image_name;
                } else {
                    $booking_details['channel_logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/" . $ota_logo[$be_bookings->booking_source];
                }

                $get_plan_details = DB::table('kernel.subscriptions')
                    ->join('kernel.plans', 'kernel.subscriptions.plan_id', '=', 'kernel.plans.plan_id')
                    ->where('hotel_id', $be_bookings->hotel_id)
                    ->orderBy('id', 'DESC')
                    ->first();
                //check the hotel have JINI HOST or not.
                $fetch_apps = explode(',', $get_plan_details->apps);
                $check_apps = in_array('6', $fetch_apps);
                if ($be_bookings->booking_status == 3) {
                    $booking_details['download_invoice'] = 0;
                    $booking_details['is_modify'] = 0;
                    $booking_details['is_checkin'] = 0;
                    $booking_details['is_cancel'] = 0;
                } else {

                    if ($request->exists('allocation') && $request->allocation == 4) {
                        $booking_details['download_invoice'] = 1;
                        $booking_details['is_modify'] = 0;
                        $booking_details['is_checkin'] = 0;
                        $booking_details['is_cancel'] = 0;
                    } else {
                        $booking_details['download_invoice'] = 0;
                        // if ($be_bookings->booking_source == 'CRS' || $be_bookings->booking_source == 'GEMS') {
                        if ($be_bookings->booking_source == 'CRS') {
                            if ($current_date <= $be_bookings->check_in) {
                                $booking_details['is_modify'] = 0; //1
                                $booking_details['is_cancel'] = 1; //1
                                $booking_details['is_modify_crs'] = 1; //1
                            } else {
                                $booking_details['is_modify'] = 0;
                                $booking_details['is_cancel'] = 0;
                            }

                            if ($check_apps && $current_date >= $be_bookings->check_in && $current_date <= $be_bookings->check_out) {
                                $booking_details['is_checkin'] = 0; //1
                            } else {
                                $booking_details['is_checkin'] = 0;
                            }
                        } else {
                            $booking_details['is_modify'] = 0;
                            if ($check_apps && $current_date >= $be_bookings->check_in && $current_date <= $be_bookings->check_out) {
                                $booking_details['is_checkin'] = 0; //1
                            } else {
                                $booking_details['is_checkin'] = 0;
                            }
                            $booking_details['is_cancel'] = 0;
                        }
                    }
                }

                $room_type_details = HotelBooking::where('invoice_id', $invoice_id)->get();
                $room_type_array = [];
                $total_room_type_adult = 0;
                $total_room_type_child = 0;
                $occupancy_details = json_decode($be_bookings->extra_details, true);
                foreach ($room_type_details as $key => $room_type) {
                    $details = DB::table('kernel.room_rate_plan')
                        ->join('kernel.room_type_table', 'kernel.room_rate_plan.room_type_id', 'room_type_table.room_type_id')
                        ->join('kernel.rate_plan_table', 'kernel.room_rate_plan.rate_plan_id', 'rate_plan_table.rate_plan_id')
                        ->select('room_type_table.room_type', 'room_type_table.room_type_id', 'room_type_table.image', 'rate_plan_table.plan_type', 'rate_plan_table.plan_name', 'rate_plan_table.rate_plan_id', 'room_rate_plan.bar_price')
                        ->where('room_rate_plan.room_type_id', $room_type->room_type_id)
                        ->first();
                    if (isset($details->image)) {
                        $images = explode(',', $details->image);
                        $image_url = ImageTable::where('image_id', $images['0'])->first();
                        if ($image_url) {
                            $img = $image_url->image_name;
                        } else {
                            $img = '';
                        }
                    } else {
                        $img = '';
                    }

                    // $rate_plan_info = json_decode($be_bookings->room_type);
                    // $rate_info = explode('(', $rate_plan_info[$key]);
                    // if (isset($rate_info[1])) {
                    //     $rate_info_sep = explode(')', $rate_info[1]);
                    //     $rate_plan_dtl = $rate_info_sep[0];
                    // } else {
                    //     $rate_plan_dtl = 'NA';
                    // }

                    $rate_plan_info = json_decode($be_bookings->room_type);
                    $rate_info = explode('(', $rate_plan_info[$key]);
                    if (isset($rate_info[1])) {
                        $rate_plan_end = end($rate_info);
                        if (isset($rate_plan_end)) {
                            $rate_info_sep = explode(')', $rate_plan_end);
                            $rate_plan_dtl = $rate_info_sep[0];
                        } else {
                            $rate_plan_dtl = 'NA';
                        }
                    } else {
                        $rate_plan_dtl = 'NA';
                    }

                    foreach ($occupancy_details as $occupancy_detail) {
                        if (isset($occupancy_detail)) {
                            foreach ($occupancy_detail as $rm_id => $extra) {
                                if ($rm_id == $room_type->room_type_id) {
                                    $total_room_type_adult = $extra[0];
                                    $total_room_type_child = $extra[1];
                                }
                            }
                        }
                    }

                    $room_details = [
                        'no_of_rooms' => $room_type->rooms,
                        'room_type' => $details->room_type,
                        'room_type_id' => $room_type->room_type_id,
                        'rate_plan_id' => $details->rate_plan_id,
                        'plan_type' => $rate_plan_dtl,
                        'plan_name' => $details->plan_name,
                        'room_image' => $img,
                        'adult' => $total_room_type_adult == "" ? 0 : $total_room_type_adult,
                        'child' => $total_room_type_child == "" ? 0 : $total_room_type_child
                    ];

                    array_push($room_type_array, $room_details);
                }

                $booking_details['room_data'] = $room_type_array;
                $booking_details['other_information'] = null;

                $get_all_subscribed_apps_url = GET_SUBSCRIPTION_DETAILS_URL . $be_bookings->hotel_id;
                $get_all_source_list_data = Commonmodel::curlGet($get_all_subscribed_apps_url);

                $results = [];
                $all_results = [];
                foreach ($get_all_source_list_data->apps as $RsGetAllSource) {
                    $results['app_name'] = $RsGetAllSource->app_name;
                    $results['is_subscribed'] = $RsGetAllSource->is_subscribed;
                    $all_results[] = $results;
                }
                if ($booking_details) {
                    return response()->json(array('status' => 1, 'message' => "Booking Details Fetched Successfully", 'data' => $booking_details, 'all_subscribed_lists' => $all_results));
                } else {
                    return response()->json(array('status' => 0, 'message' => "Booking Details Fetched Failed"));
                }
            } else {
                return response()->json(array('status' => 0, 'message' => "Booking Details Fetched Failed"));
            }
        } else {

            // if($bookingid == '1424026621'){
            $post_data = array('booking_id' => $bookingid);
            $url = 'https://cm.bookingjini.com/extranetv4/fetch-booking-lists';

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $post_data,
            ));
            $cm_bookings = curl_exec($curl);
            curl_close($curl);
            $cm_bookings = json_decode($cm_bookings);

            // }else{
            //     $cm_bookings =  CmOtaBookingRead::where('cm_ota_booking.unique_id', '=', $bookingid)
            //     ->join('kernel.hotels_table', 'cm_ota_booking.hotel_id', '=', 'hotels_table.hotel_id')
            //     ->select('cm_ota_booking.customer_details', 'cm_ota_booking.ota_id', 'cm_ota_booking.booking_status', 'cm_ota_booking.rooms_qty', 'cm_ota_booking.room_type', 'cm_ota_booking.rate_code', 'cm_ota_booking.checkin_at', 'cm_ota_booking.checkout_at', 'cm_ota_booking.booking_date', 'cm_ota_booking.amount', 'cm_ota_booking.currency', 'cm_ota_booking.channel_name', 'cm_ota_booking.no_of_adult', 'cm_ota_booking.no_of_child', 'cm_ota_booking.rooms_qty', 'cm_ota_booking.hotel_id', 'cm_ota_booking.tax_amount', 'cm_ota_booking.no_of_adult', 'cm_ota_booking.no_of_child', 'hotels_table.state_id', 'hotels_table.country_id')
            //     ->first();
            // }

            if ($cm_bookings) {

                if ($cm_bookings->country_id == '1') {
                    $ota_logo['website'] = 'bookingjini.svg';
                    $ota_logo['CRS'] = 'bookingjini.svg';
                    $ota_logo['GEMS'] = 'bookingjini.svg';
                } else {
                    $ota_logo['website'] = 'kite.png';
                    $ota_logo['CRS'] = 'kite.png';
                    $ota_logo['GEMS'] = 'kite.png';
                }

                if ($cm_bookings->country_id == '1') {
                    $booking_details['currency_icon'] =  'fa fa-inr';
                    $booking_details['currency_name'] =  'INR';
                    $booking_details['tax'] =  'GST';
                } else {
                    $booking_details['currency_icon'] = 'fa fa-usd';
                    $booking_details['currency_name'] = 'USD';
                    $booking_details['tax'] = 'VAT';
                }

                $check_in = date_create($cm_bookings->checkin_at);
                $check_out = date_create($cm_bookings->checkout_at);
                $diff = date_diff($check_in, $check_out);
                $diff = (int)$diff->format("%a");

                if ($diff == 0) {
                    $diff = 1;
                }
                $guests = explode(',', $cm_bookings->customer_details);
                if (isset($guests[0])) {
                    $booking_details['guest_name'] = $guests[0];
                } else {
                    $booking_details['guest_name'] = 'N/A';
                }
                if (isset($guests[1])) {
                    $booking_details['email_id'] = $guests[1];
                } else {
                    $booking_details['email_id'] = 'N/A';
                }
                if (isset($guests[2])) {
                    $booking_details['mobile'] = $guests[2];
                } else {
                    $booking_details['mobile'] = 'N/A';
                }
                $booking_details['nights'] = $diff;
                $booking_details['bookingid'] = $bookingid;
                $booking_details['booking_date'] = date('d M Y', strtotime($cm_bookings->booking_date));
                $booking_details['checkin_at'] = date('d M Y', strtotime($cm_bookings->checkin_at));
                $booking_details['checkout_at'] = date('d M Y', strtotime($cm_bookings->checkout_at));
                $booking_details['price'] = $cm_bookings->amount;
                $booking_details['tax_amount'] = $cm_bookings->tax_amount;
                $booking_details['booking_source'] = $cm_bookings->channel_name;
                $booking_details['state_id'] = $cm_bookings->state_id;
                $booking_details['channel_logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/" . $ota_logo[$cm_bookings->channel_name];

                $get_plan_details = DB::table('kernel.subscriptions')
                    ->join('kernel.plans', 'kernel.subscriptions.plan_id', '=', 'kernel.plans.plan_id')
                    ->where('hotel_id', $cm_bookings->hotel_id)
                    ->orderBy('id', 'DESC')
                    ->first();
                //check the hotel have JINI HOST or not.
                $fetch_apps = explode(',', $get_plan_details->apps);
                $check_apps = in_array('6', $fetch_apps);

                if ($request->exists('allocation')) {
                    if ($request->allocation == 4) {
                        $booking_details['is_modify'] = 0;
                        $booking_details['is_checkin'] = 0;
                        $booking_details['is_cancel'] = 0;
                        $booking_details['download_invoice'] = 1;
                    } else {
                        $booking_details['is_modify'] = 0;
                        if ($check_apps && $current_date == $cm_bookings->checkin_at && $current_date <= $cm_bookings->checkout_at) {
                            $booking_details['is_checkin'] = 0; //1
                        } else {
                            $booking_details['is_checkin'] = 0;
                        }

                        $booking_details['is_cancel'] = 0;
                        $booking_details['download_invoice'] = 0;
                    }
                } else {
                    $booking_details['download_invoice'] = 0;
                    $booking_details['is_modify'] = 0;
                    if ($check_apps && $current_date == $cm_bookings->checkin_at && $current_date <= $cm_bookings->checkout_at) {
                        $booking_details['is_checkin'] = 0; //1
                    } else {
                        $booking_details['is_checkin'] = 0;
                    }

                    $booking_details['is_cancel'] = 0;
                }

                $other_information = [];
                $other_info = json_decode($cm_bookings->other_information, true);
                // if ($other_info) {
                //     foreach ($other_info as $key => $info) {
                //         array_push($other_information, array('key' => $key, 'value' => $info));
                //     }
                // }

                    if ($other_info) {
                        foreach ($other_info as $key => $info) {
                            $str = [];
                            if(is_array($info)){
                                foreach($info as $k=>$v){
                                    $str[] = $k.' : '.$v.'  ';
                                }
                                $string = implode(',',$str);
                            }else{
                                $string = $info;
                            }
                           
                            array_push($other_information, array('key' => $key, 'value' => $string));
                        }
                    }
               
                $booking_details['other_information'] = $other_information;
                $booking_details['net_price'] = $cm_bookings->net_price;
                // $booking_details['other_information'] = $cm_bookings->other_information;
                $room_type = explode(',', $cm_bookings->room_type);
                $rate_plan = explode(',', $cm_bookings->rate_code);
                $rooms_qty = explode(',', $cm_bookings->rooms_qty);


                    $no_of_adult = explode(',', $cm_bookings->no_of_adult);
                    $no_of_child = explode(',', $cm_bookings->no_of_child);

                    $k = 0;
                    foreach ($rooms_qty as $key => $room) {
                        if ($room > 1) {
                            $sum = 0;
                            $sum1 = 0;
                            for ($i = 0; $i < $room; $i++) {
                                if (!isset($no_of_child[$i])) {
                                    $no_of_child[$i] = 0;
                                }
                                if (!isset($no_of_adult[$i])) {
                                    $no_of_adult[$i] = 0;
                                }
                                $sum += (int)$no_of_adult[$k];
                                $child_info = isset($no_of_child[$k]) ? (int)$no_of_child[$k] : 0;
                                $sum1 += (int)$child_info;
                                $k++;
                            }
                            $guest[] = (string)$sum;
                            $guest1[] = (string)$sum1;
                        } else {
                            $guest[] = $no_of_adult[$k];
                            if (!isset($no_of_child[$k])) {
                                $no_of_child[$k] = 0;
                            }
                            $guest1[] = (int)$no_of_child[$k];
                            $k++;
                        }
                    }

                    $no_of_adult = $guest;
                    $no_of_child = $guest1;


                    // $no_of_adult = $cm_bookings->no_of_adult;
                    // if ($no_of_adult) {
                    //     $no_of_adult = explode(',', $no_of_adult);
                    // } else {
                    //     $no_of_adult = 0;
                    // }
    
                    // $no_of_child = $cm_bookings->no_of_child;
                    // if ($no_of_child) {
                    //     $no_of_child = explode(',', $no_of_child);
                    // } else {
                    //     $no_of_child = 0;
                    // }
    


                //this logic is implement when same room type is multiple times on booking 
                //get unique room type and add room qty of same room.
                if (count($room_type) > 1) {
                    $room_type_key = [];
                    foreach ($room_type as $key => $room) {
                        $room_type_key[$room_type[$key]]['room_qty'] = isset($room_type_key[$room_type[$key]]['room_qty']) ? ($room_type_key[$room_type[$key]]['room_qty'] + $rooms_qty[$key]) : $rooms_qty[$key];
                        $room_type_key[$room_type[$key]]['no_of_adult'] = isset($room_type_key[$room_type[$key]]['no_of_adult']) ? ($room_type_key[$room_type[$key]]['no_of_adult'] + $no_of_adult[$key]) : $no_of_adult[$key];
                        $room_type_key[$room_type[$key]]['no_of_child'] = isset($room_type_key[$room_type[$key]]['no_of_child']) ? ($room_type_key[$room_type[$key]]['no_of_child'] + $no_of_child[$key]) : $no_of_child[$key];
                    }

                    $room_type = [];
                    $rooms_qty = [];
                    $no_of_adult = [];
                    $no_of_child = [];
                    foreach ($room_type_key as $key => $rooms) {
                        $room_type[] = $key;
                        $rooms_qty[] = $rooms['room_qty'];
                        $no_of_adult[] = $rooms['no_of_adult'];
                        $no_of_child[] = $rooms['no_of_child'];
                    }
                }

                $room_type_array = [];

                $details = CmOtaRoomTypeSynchronizeRead::join('kernel.room_type_table', 'cm_ota_room_type_synchronize.room_type_id', 'room_type_table.room_type_id')
                    ->select('room_type_table.room_type', 'room_type_table.room_type_id', 'room_type_table.image')
                    ->whereIn('cm_ota_room_type_synchronize.ota_room_type', $room_type)
                    ->where('cm_ota_room_type_synchronize.hotel_id', $cm_bookings->hotel_id)
                    ->where('cm_ota_room_type_synchronize.ota_type_id', $cm_bookings->ota_id)
                    ->get();

                if ($details) {
                    foreach ($details as $key => $detail) {
                        $rate_plan_details = CmOtaRatePlanSynchronizeRead::join('kernel.rate_plan_table', 'cm_ota_rate_plan_synchronize.hotel_rate_plan_id', 'rate_plan_table.rate_plan_id')
                            ->select('rate_plan_table.plan_type', 'rate_plan_table.plan_name', 'rate_plan_table.rate_plan_id')
                            ->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id', $rate_plan[$key])
                            ->first();

                        if ($rate_plan_details) {
                            $rate_plan_id = $rate_plan_details->rate_plan_id;
                            $plan_type = $rate_plan_details->plan_type;
                            $plan_name = $rate_plan_details->plan_name;
                        } else {
                            $rate_plan_id = 'NA';
                            $plan_type = 'NA';
                            $plan_name = 'NA';
                        }

                        if (isset($detail->image)) {
                            $images = explode(',', $detail->image);
                            $image_url = ImageTable::where('image_id', $images['0'])->first();
                            if ($image_url) {
                                $img = $image_url->image_name;
                            } else {
                                $img = '';
                            }
                        } else {
                            $img = '';
                        }

                        $room_details = [
                            'no_of_rooms' => isset($rooms_qty[$key]) == "" ? 0 : $rooms_qty[$key],
                            'room_type' => $detail->room_type,
                            'room_type_id' => $detail->room_type_id,
                            'rate_plan_id' => $rate_plan_id,
                            'plan_type' => $plan_type,
                            'plan_name' => $plan_name,
                            'room_image' => $img,
                            'adult' => isset($no_of_adult[$key]) == "" ? 0 : $no_of_adult[$key],
                            'child' => isset($no_of_child[$key]) == "" ? 0 : $no_of_child[$key]
                        ];

                        array_push($room_type_array, $room_details);
                    }
                    $booking_details['room_data'] = $room_type_array;
                }

                return response()->json(array('status' => 1, 'message' => "Booking Details Fetched Successfully", 'data' => $booking_details));
            }
        }
    }

    //for testing
    public function crsBookingDetailsTest(Request $request)
    {
        $bookingid = $request->booking_id;
        $booking_source = $request->booking_source;
        $current_date = date('Y-m-d');

        //ota logo list
        $ota_logo['MakeMyTrip'] = '1422311435mmt.png';
        $ota_logo['Goibibo'] = '1119412432goibibo.png';
        $ota_logo['Expedia'] = '1137382374expedia.png';
        $ota_logo['Cleartrip'] = '111669874cleartip.png';
        $ota_logo['Agoda'] = '1016869990agoda.png';
        $ota_logo['Travelguru'] = '1071839720travelguru_logo.gif';
        $ota_logo['Booking.com'] = '1519602952booking.png';
        $ota_logo['Via.com'] = '529817383via.png';
        $ota_logo['Goomo'] = 'Goomo.png';
        $ota_logo['Airbnb'] = 'airbnblogo.png';
        $ota_logo['EaseMyTrip'] = 'easemytrip.png';
        $ota_logo['Paytm'] = 'paytm.png';
        $ota_logo['HappyEasyGo'] = 'happyeasygo.png';
        $ota_logo['Hostelworld'] = 'hostelworld.png';
        $ota_logo['MMTGCC'] = 'mmticon.png';
        $ota_logo['Simplotel'] = '';

        if ($booking_source == 'website' || $booking_source == 'GEMS' || $booking_source == 'CRS' || $booking_source == 'QUICKPAYMENT' || $booking_source == 'google' || $booking_source == 'jiniassist') {
            $invoice_id = substr($bookingid, 6);
            $country_id =  Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'kernel.hotels_table.hotel_id')
                ->select('hotels_table.country_id')
                ->where('invoice_table.invoice_id', $invoice_id)
                ->first();
            if ($country_id->country_id == '1') {
                $ota_logo['website'] = 'bookingjini.svg';
                $ota_logo['CRS'] = 'bookingjini.svg';
                $ota_logo['GEMS'] = 'bookingjini.svg';
                $ota_logo['QUICKPAYMENT'] = 'bookingjini.svg';
                $ota_logo['google'] = 'bookingjini.svg';
                $ota_logo['jiniassist'] = 'bookingjini.svg';
            } else {
                $ota_logo['website'] = 'kite.png';
                $ota_logo['CRS'] = 'kite.png';
                $ota_logo['GEMS'] = 'kite.png';
            }

            if ($country_id->country_id == '1') {
                $booking_details['currency_icon'] =  'fa fa-inr';
                $booking_details['currency_name'] =  'INR';
                $booking_details['tax'] =  'GST';
            } else {
                $booking_details['currency_icon'] = 'fa fa-usd';
                $booking_details['currency_name'] = 'USD';
                $booking_details['tax'] = 'VAT';
            }
            $be_bookings =  Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
                ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                ->join('kernel.hotels_table', 'hotel_booking.hotel_id', '=', 'hotels_table.hotel_id')
                ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
                ->select(
                    'user_table.first_name',
                    'user_table.last_name',
                    'user_table.email_id',
                    'user_table.address',
                    'user_table.mobile',
                    'user_table.user_id',
                    'user_table.mobile',
                    'user_table.user_address',
                    'user_table.company_name',
                    'user_table.GSTIN',
                    'invoice_table.room_type',
                    'invoice_table.total_amount',
                    'invoice_table.tax_amount',
                    'invoice_table.paid_amount',
                    'invoice_table.booking_date',
                    'invoice_table.invoice',
                    'invoice_table.booking_status',
                    'invoice_table.invoice_id',
                    'invoice_table.booking_source',
                    'invoice_table.hotel_name',
                    'invoice_table.hotel_id',
                    'invoice_table.extra_details',
                    'hotel_booking.hotel_booking_id',
                    'hotel_booking.rooms',
                    'hotel_booking.check_in',
                    'hotel_booking.check_out',
                    'hotels_table.state_id',
                    'company_table.logo'
                )
                ->where('invoice_table.invoice_id', '=', $invoice_id)
                ->first();

            if ($be_bookings) {
                $check_in = date_create($be_bookings->check_in);
                $check_out = date_create($be_bookings->check_out);
                $diff = date_diff($check_in, $check_out);
                $diff = (int)$diff->format("%a");
                if ($diff == 0) {
                    $diff = 1;
                }
                $booking_details['invoice_id'] = $invoice_id;
                $booking_details['guest_name'] = $be_bookings->first_name . ' ' . $be_bookings->last_name;
                $booking_details['email_id'] = $be_bookings->email_id;
                $booking_details['mobile'] = $be_bookings->mobile;
                $booking_details['address'] = $be_bookings->address;
                if (isset($be_bookings->user_address)) {
                    $booking_details['user_address'] = $be_bookings->user_address;
                } else {
                    $booking_details['user_address'] = "";
                }
                if (empty($be_bookings->GSTIN) || $be_bookings->GSTIN == '' || $be_bookings->GSTIN == 'NULL' || $be_bookings->GSTIN == null) {
                    $booking_details['business_booking'] = 0;
                } else {
                    $booking_details['business_booking'] = 1;
                }
                $booking_details['company_name'] = $be_bookings->company_name;
                $booking_details['GSTIN'] = $be_bookings->GSTIN;
                $booking_details['nights'] = $diff;
                $booking_details['bookingid'] = $bookingid;
                $booking_details['booking_date'] = date('d M Y', strtotime($be_bookings->booking_date));
                $booking_details['checkin_at'] = date('d M Y', strtotime($be_bookings->check_in));
                $booking_details['checkout_at'] = date('d M Y', strtotime($be_bookings->check_out));
                $booking_details['price'] = $be_bookings->total_amount;
                $booking_details['tax_amount'] = $be_bookings->tax_amount;
                $booking_details['booking_source'] = $booking_source;
                $booking_details['state_id'] = $be_bookings->state_id;

                $fetchHotelLogo = ImageTable::where('image_id', $be_bookings->logo)->select('image_name')->first();
                if (isset($fetchHotelLogo->image_name)) {
                    $booking_details['channel_logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $fetchHotelLogo->image_name;
                } else {
                    $booking_details['channel_logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/" . $ota_logo[$be_bookings->booking_source];
                }

                $get_plan_details = DB::table('kernel.subscriptions')
                    ->join('kernel.plans', 'kernel.subscriptions.plan_id', '=', 'kernel.plans.plan_id')
                    ->where('hotel_id', $be_bookings->hotel_id)
                    ->orderBy('id', 'DESC')
                    ->first();
                //check the hotel have JINI HOST or not.
                $fetch_apps = explode(',', $get_plan_details->apps);
                $check_apps = in_array('6', $fetch_apps);
                if ($be_bookings->booking_status == 3) {
                    $booking_details['download_invoice'] = 0;
                    $booking_details['is_modify'] = 0;
                    $booking_details['is_checkin'] = 0;
                    $booking_details['is_cancel'] = 0;
                } else {

                    if ($request->exists('allocation') && $request->allocation == 4) {
                        $booking_details['download_invoice'] = 1;
                        $booking_details['is_modify'] = 0;
                        $booking_details['is_checkin'] = 0;
                        $booking_details['is_cancel'] = 0;
                    } else {
                        $booking_details['download_invoice'] = 0;
                        if ($be_bookings->booking_source == 'CRS' || $be_bookings->booking_source == 'GEMS') {
                            if ($current_date <= $be_bookings->check_in) {
                                $booking_details['is_modify'] = 0; //1
                                $booking_details['is_cancel'] = 0; //1
                            } else {
                                $booking_details['is_modify'] = 0;
                                $booking_details['is_cancel'] = 0;
                            }

                            if ($check_apps && $current_date >= $be_bookings->check_in && $current_date <= $be_bookings->check_out) {
                                $booking_details['is_checkin'] = 0; //1
                            } else {
                                $booking_details['is_checkin'] = 0;
                            }
                        } else {
                            $booking_details['is_modify'] = 0;
                            if ($check_apps && $current_date >= $be_bookings->check_in && $current_date <= $be_bookings->check_out) {
                                $booking_details['is_checkin'] = 0; //1
                            } else {
                                $booking_details['is_checkin'] = 0;
                            }
                            $booking_details['is_cancel'] = 0;
                        }
                    }
                }

                $room_type_details = HotelBooking::where('invoice_id', $invoice_id)->get();
                $room_type_array = [];
                $total_room_type_adult = 0;
                $total_room_type_child = 0;
                $occupancy_details = json_decode($be_bookings->extra_details, true);
                foreach ($room_type_details as $key => $room_type) {
                    $details = DB::table('kernel.room_rate_plan')
                        ->join('kernel.room_type_table', 'kernel.room_rate_plan.room_type_id', 'room_type_table.room_type_id')
                        ->join('kernel.rate_plan_table', 'kernel.room_rate_plan.rate_plan_id', 'rate_plan_table.rate_plan_id')
                        ->select('room_type_table.room_type', 'room_type_table.room_type_id', 'room_type_table.image', 'rate_plan_table.plan_type', 'rate_plan_table.plan_name', 'rate_plan_table.rate_plan_id', 'room_rate_plan.bar_price')
                        ->where('room_rate_plan.room_type_id', $room_type->room_type_id)
                        ->first();
                    if (isset($details->image)) {
                        $images = explode(',', $details->image);
                        $image_url = ImageTable::where('image_id', $images['0'])->first();
                        if ($image_url) {
                            $img = $image_url->image_name;
                        } else {
                            $img = '';
                        }
                    } else {
                        $img = '';
                    }

                    $rate_plan_info = json_decode($be_bookings->room_type);
                    $rate_info = explode('(', $rate_plan_info[$key]);
                    if (isset($rate_info[1])) {
                        $rate_info_sep = explode(')', $rate_info[1]);
                        $rate_plan_dtl = $rate_info_sep[0];
                    } else {
                        $rate_plan_dtl = 'NA';
                    }

                    foreach ($occupancy_details as $occupancy_detail) {
                        if (isset($occupancy_detail)) {
                            foreach ($occupancy_detail as $rm_id => $extra) {
                                if ($rm_id == $room_type->room_type_id) {
                                    $total_room_type_adult = $extra[0];
                                    $total_room_type_child = $extra[1];
                                }
                            }
                        }
                    }

                    $room_details = [
                        'no_of_rooms' => $room_type->rooms,
                        'room_type' => $details->room_type,
                        'room_type_id' => $room_type->room_type_id,
                        'rate_plan_id' => $details->rate_plan_id,
                        'plan_type' => $rate_plan_dtl,
                        'plan_name' => $details->plan_name,
                        'room_image' => $img,
                        'adult' => $total_room_type_adult == "" ? 0 : $total_room_type_adult,
                        'child' => $total_room_type_child == "" ? 0 : $total_room_type_child
                    ];

                    array_push($room_type_array, $room_details);
                }

                $booking_details['room_data'] = $room_type_array;

                $get_all_subscribed_apps_url = GET_SUBSCRIPTION_DETAILS_URL . $be_bookings->hotel_id;
                $get_all_source_list_data = Commonmodel::curlGet($get_all_subscribed_apps_url);

                $results = [];
                $all_results = [];
                foreach ($get_all_source_list_data->apps as $RsGetAllSource) {
                    $results['app_name'] = $RsGetAllSource->app_name;
                    $results['is_subscribed'] = $RsGetAllSource->is_subscribed;
                    $all_results[] = $results;
                }
                if ($booking_details) {
                    return response()->json(array('status' => 1, 'message' => "Booking Details Fetched Successfully", 'data' => $booking_details, 'all_subscribed_lists' => $all_results));
                } else {
                    return response()->json(array('status' => 0, 'message' => "Booking Details Fetched Failed"));
                }
            } else {
                return response()->json(array('status' => 0, 'message' => "Booking Details Fetched Failed"));
            }
        } else {
            $cm_bookings =  CmOtaBookingRead::where('cm_ota_booking.unique_id', '=', $bookingid)
                ->join('kernel.hotels_table', 'cm_ota_booking.hotel_id', '=', 'hotels_table.hotel_id')
                ->select('cm_ota_booking.customer_details', 'cm_ota_booking.ota_id', 'cm_ota_booking.booking_status', 'cm_ota_booking.rooms_qty', 'cm_ota_booking.room_type', 'cm_ota_booking.rate_code', 'cm_ota_booking.checkin_at', 'cm_ota_booking.checkout_at', 'cm_ota_booking.booking_date', 'cm_ota_booking.amount', 'cm_ota_booking.currency', 'cm_ota_booking.channel_name', 'cm_ota_booking.no_of_adult', 'cm_ota_booking.no_of_child', 'cm_ota_booking.rooms_qty', 'cm_ota_booking.hotel_id', 'cm_ota_booking.tax_amount', 'cm_ota_booking.no_of_adult', 'cm_ota_booking.no_of_child', 'hotels_table.state_id', 'hotels_table.country_id')
                ->first();
            if ($cm_bookings) {

                if ($cm_bookings->country_id == '1') {
                    $ota_logo['website'] = 'bookingjini.svg';
                    $ota_logo['CRS'] = 'bookingjini.svg';
                    $ota_logo['GEMS'] = 'bookingjini.svg';
                } else {
                    $ota_logo['website'] = 'kite.png';
                    $ota_logo['CRS'] = 'kite.png';
                    $ota_logo['GEMS'] = 'kite.png';
                }

                if ($cm_bookings->country_id == '1') {
                    $booking_details['currency_icon'] =  'fa fa-inr';
                    $booking_details['currency_name'] =  'INR';
                    $booking_details['tax'] =  'GST';
                } else {
                    $booking_details['currency_icon'] = 'fa fa-usd';
                    $booking_details['currency_name'] = 'USD';
                    $booking_details['tax'] = 'VAT';
                }

                $check_in = date_create($cm_bookings->checkin_at);
                $check_out = date_create($cm_bookings->checkout_at);
                $diff = date_diff($check_in, $check_out);
                $diff = (int)$diff->format("%a");

                if ($diff == 0) {
                    $diff = 1;
                }
                $guests = explode(',', $cm_bookings->customer_details);
                if (isset($guests[0])) {
                    $booking_details['guest_name'] = $guests[0];
                } else {
                    $booking_details['guest_name'] = 'N/A';
                }
                if (isset($guests[1])) {
                    $booking_details['email_id'] = $guests[1];
                } else {
                    $booking_details['email_id'] = 'N/A';
                }
                if (isset($guests[2])) {
                    $booking_details['mobile'] = $guests[2];
                } else {
                    $booking_details['mobile'] = 'N/A';
                }
                $booking_details['nights'] = $diff;
                $booking_details['bookingid'] = $bookingid;
                $booking_details['booking_date'] = date('d M Y', strtotime($cm_bookings->booking_date));
                $booking_details['checkin_at'] = date('d M Y', strtotime($cm_bookings->checkin_at));
                $booking_details['checkout_at'] = date('d M Y', strtotime($cm_bookings->checkout_at));
                $booking_details['price'] = $cm_bookings->amount;
                $booking_details['tax_amount'] = $cm_bookings->tax_amount;
                $booking_details['booking_source'] = $cm_bookings->channel_name;
                $booking_details['state_id'] = $cm_bookings->state_id;
                $booking_details['channel_logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/" . $ota_logo[$cm_bookings->channel_name];

                $get_plan_details = DB::table('kernel.subscriptions')
                    ->join('kernel.plans', 'kernel.subscriptions.plan_id', '=', 'kernel.plans.plan_id')
                    ->where('hotel_id', $cm_bookings->hotel_id)
                    ->orderBy('id', 'DESC')
                    ->first();
                //check the hotel have JINI HOST or not.
                $fetch_apps = explode(',', $get_plan_details->apps);
                $check_apps = in_array('6', $fetch_apps);

                if ($request->exists('allocation')) {
                    if ($request->allocation == 4) {
                        $booking_details['is_modify'] = 0;
                        $booking_details['is_checkin'] = 0;
                        $booking_details['is_cancel'] = 0;
                        $booking_details['download_invoice'] = 1;
                    } else {
                        $booking_details['is_modify'] = 0;
                        if ($check_apps && $current_date == $cm_bookings->checkin_at && $current_date <= $cm_bookings->checkout_at) {
                            $booking_details['is_checkin'] = 1;
                        } else {
                            $booking_details['is_checkin'] = 0;
                        }

                        $booking_details['is_cancel'] = 0;
                        $booking_details['download_invoice'] = 0;
                    }
                } else {
                    $booking_details['download_invoice'] = 0;
                    $booking_details['is_modify'] = 0;
                    if ($check_apps && $current_date == $cm_bookings->checkin_at && $current_date <= $cm_bookings->checkout_at) {
                        $booking_details['is_checkin'] = 1;
                    } else {
                        $booking_details['is_checkin'] = 0;
                    }

                    $booking_details['is_cancel'] = 0;
                }
                $room_type = explode(',', $cm_bookings->room_type);
                $rate_plan = explode(',', $cm_bookings->rate_code);
                $rooms_qty = explode(',', $cm_bookings->rooms_qty);

                $no_of_adult = $cm_bookings->no_of_adult;
                if ($no_of_adult) {
                    $no_of_adult = explode(',', $no_of_adult);
                } else {
                    $no_of_adult = 0;
                }

                $no_of_child = $cm_bookings->no_of_child;
                if ($no_of_child) {
                    $no_of_child = explode(',', $no_of_child);
                } else {
                    $no_of_child = 0;
                }


                if (count($room_type) > 1) {
                    $room_type_key = [];
                    foreach ($room_type as $key => $room) {
                        $room_type_key[$room_type[$key]]['room_qty'] = isset($room_type_key[$room_type[$key]]['room_qty']) ? ($room_type_key[$room_type[$key]]['room_qty'] + $rooms_qty[$key]) : $rooms_qty[$key];
                        $room_type_key[$room_type[$key]]['no_of_adult'] = isset($room_type_key[$room_type[$key]]['no_of_adult']) ? ($room_type_key[$room_type[$key]]['no_of_adult'] + $no_of_adult[$key]) : $no_of_adult[$key];
                        $room_type_key[$room_type[$key]]['no_of_child'] = isset($room_type_key[$room_type[$key]]['no_of_child']) ? ($room_type_key[$room_type[$key]]['no_of_child'] + $no_of_child[$key]) : $no_of_child[$key];
                    }

                    $room_type = [];
                    $rooms_qty = [];
                    $no_of_adult = [];
                    $no_of_child = [];
                    foreach ($room_type_key as $key => $rooms) {
                        $room_type[] = $key;
                        $rooms_qty[] = $rooms['room_qty'];
                        $no_of_adult[] = $rooms['no_of_adult'];
                        $no_of_child[] = $rooms['no_of_child'];
                    }
                }


                $room_type_array = [];


                // $details = CmOtaBookingRead::join('cm_ota_room_type_synchronize', 'cm_ota_room_type_synchronize.ota_room_type', '=', 'cm_ota_booking.room_type')
                // ->join('kernel.room_type_table', 'cm_ota_room_type_synchronize.room_type_id', 'room_type_table.room_type_id')
                // ->select('room_type_table.room_type', 'room_type_table.room_type_id', 'room_type_table.image','cm_ota_booking.hotel_id')
                // ->whereIn('cm_ota_room_type_synchronize.ota_room_type', $room_type)
                // ->where('cm_ota_booking.unique_id', $bookingid)
                // ->where('cm_ota_booking.hotel_id', $cm_bookings->hotel_id)
                // ->get();

                $details = CmOtaRoomTypeSynchronizeRead::join('kernel.room_type_table', 'cm_ota_room_type_synchronize.room_type_id', 'room_type_table.room_type_id')
                    ->select('room_type_table.room_type', 'room_type_table.room_type_id', 'room_type_table.image')
                    ->whereIn('cm_ota_room_type_synchronize.ota_room_type', $room_type)
                    ->where('cm_ota_room_type_synchronize.hotel_id', $cm_bookings->hotel_id)
                    ->where('cm_ota_room_type_synchronize.ota_type_id', $cm_bookings->ota_id)
                    ->get();

                if ($details) {
                    foreach ($details as $key => $detail) {
                        $rate_plan_details = CmOtaRatePlanSynchronizeRead::join('kernel.rate_plan_table', 'cm_ota_rate_plan_synchronize.hotel_rate_plan_id', 'rate_plan_table.rate_plan_id')
                            ->select('rate_plan_table.plan_type', 'rate_plan_table.plan_name', 'rate_plan_table.rate_plan_id')
                            ->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id', $rate_plan[$key])
                            ->first();

                        if ($rate_plan_details) {
                            $rate_plan_id = $rate_plan_details->rate_plan_id;
                            $plan_type = $rate_plan_details->plan_type;
                            $plan_name = $rate_plan_details->plan_name;
                        } else {
                            $rate_plan_id = 'NA';
                            $plan_type = 'NA';
                            $plan_name = 'NA';
                        }

                        if (isset($detail->image)) {
                            $images = explode(',', $detail->image);
                            $image_url = ImageTable::where('image_id', $images['0'])->first();
                            if ($image_url) {
                                $img = $image_url->image_name;
                            } else {
                                $img = '';
                            }
                        } else {
                            $img = '';
                        }

                        $room_details = [
                            'no_of_rooms' => isset($rooms_qty[$key]) == "" ? 0 : $rooms_qty[$key],
                            'room_type' => $detail->room_type,
                            'room_type_id' => $detail->room_type_id,
                            'rate_plan_id' => $rate_plan_id,
                            'plan_type' => $plan_type,
                            'plan_name' => $plan_name,
                            'room_image' => $img,
                            'adult' => isset($no_of_adult[$key]) == "" ? 0 : $no_of_adult[$key],
                            'child' => isset($no_of_child[$key]) == "" ? 0 : $no_of_child[$key]
                        ];

                        array_push($room_type_array, $room_details);
                    }
                    $booking_details['room_data'] = $room_type_array;
                }

                return response()->json(array('status' => 1, 'message' => "Booking Details Fetched Successfully", 'data' => $booking_details));
            }
        }
    }
}
