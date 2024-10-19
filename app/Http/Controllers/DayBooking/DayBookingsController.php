<?php

namespace App\Http\Controllers\DayBooking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\DayPackage;
use App\DayBookingARI;
use App\DayBookingARILog;
use App\DayBookings;
use App\CompanyDetails;
use App\HotelInformation;
use App\ImageTable;
use App\Http\Controllers\Controller;
use App\User;
use App\UserNew;
use App\DayBookingPromotions;
use App\Http\Controllers\BookingEngineController;
use App\SalesExecutive;

class DayBookingsController extends Controller
{
    protected $booking_engine;

    public function __construct(BookingEngineController $booking_engine)
    {
        $this->booking_engine = $booking_engine;
    }

    /**
    * Author: Saroj Patel
    * Date: 05-2024
    * Description: This api is develop to Book experience bookings.
    */
    public function dayBookings(Request $request, $api_key)
    {
        $data = $request->all();
        $user_details = $data['user_details'];
        $booking_details = $data['booking_details'];
        $package_details = $data['package_details'];
        $hotel_id = $booking_details['hotel_id'];
        $package_id = $package_details['package_id'];
        $checkin_date = $booking_details['checkin_date'];
        $no_of_package = $package_details['no_of_package'];
        $amount_to_pay = $booking_details['amount_to_pay'];
        $mail_to_guest = isset($user_details['mail_to_guest']) ? $user_details['mail_to_guest'] : 1 ;

        if (array_key_exists('collected_by', $booking_details)) {
            $booking_source = 'CRS';
        } else {
            $booking_source = 'WEBSITE';
        }

        if(isset($booking_details['admin_id'])){
            $admin_id = $booking_details['admin_id'];
        }else{
            $admin_id = null;
        }

        $sales_executive_details = SalesExecutive::where('admin_id',$admin_id)->first();

        if(isset($sales_executive_details)){
          $sales_executive_id = $sales_executive_details->id;
        }else{
          $sales_executive_id = 0;
        }
       
        $email_id = $user_details['email_id'];
        $mobile = $user_details['mobile'];

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('company_id', 'gst_slab', 'is_taxable', 'partial_pay_amt')->first();

        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 0, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }

        if($booking_source == 'WEBSITE'){
            if (strlen($user_details['mobile']) != '10') {
                $res = array('status' => 0, 'message' => "Mobile number should be 10 digits");
                return response()->json($res);
            }
        }

        //User registration
        $user_data['first_name'] = $user_details['first_name'];
        $user_data['last_name'] = isset($user_details['last_name']) ? $user_details['last_name'] : '';
        $user_data['email_id'] = $user_details['email_id'];
        $user_data['mobile'] = $user_details['mobile'];
        if (isset($user_details['mobile'])) {
            $user_name = $user_details['mobile'];
        } else {
            $user_name = $user_details['email_id'];
        }

        $user_data['password'] = uniqid(); //To generate unique rsndom number
        $user_data['password'] = Hash::make($user_data['password']); //Password encryption
        $user_data['country'] = $user_details['country'];
        $user_data['state'] = $user_details['state'];
        $user_data['city'] = $user_details['city'];
        $user_data['zip_code'] = $user_details['zip_code'];
        $user_data['company_name'] = $user_details['company_name'];
        $user_data['GSTIN'] = $user_details['GST_IN'];

        $res = User::updateOrCreate(
            [
                'mobile' => $user_details['mobile'],
                'company_id' => $hotel_info->company_id
            ],
            $user_data
        );
        $user_id = $res->user_id;

        $user_data['locality'] = $user_details['address'];
        $user_data['user_name'] = $user_name;
        $user_data['bookings'] = '';

        $user_res = UserNew::updateOrCreate(['mobile' => $user_details['mobile']], $user_data);

        $dop_details = DayPackage::where('id', $package_id)
                                   ->where('is_trash','0')
                                   ->first();
        
        $checkin_date = date('Y-m-d', strtotime($checkin_date));

        $ari_details = DayBookingARI::where('hotel_id',$hotel_id)
                    ->where('day_outing_dates',$checkin_date)
                    ->first();

        $day_promotion_per = 0;
        $discounted_price = 0;
        $price_after_discount = $ari_details->rate;

        if($booking_source == 'WEBSITE'){
            $day_promotions = DayBookingPromotions::where('hotel_id', $hotel_id)
            ->where('is_active', '1')
            ->where(function($query) use ($checkin_date) {
                $query->where('valid_from', '<=', $checkin_date)
                      ->where('valid_to', '>=', $checkin_date);
            })
            ->whereRaw('NOT FIND_IN_SET(?, blackout_dates)', [$checkin_date])
            ->get();
          
            $max_discount = 0;

            foreach ($day_promotions as $promotion) {
                $day_package_ids = explode(',', $promotion->day_package_id);
                if (in_array($package_id, $day_package_ids)) {
                    $max_discount = max($max_discount, $promotion->discount_percentage);
                }
            }
          
            $day_promotion_per = $max_discount;
        
            if ($day_promotion_per) {
                $price_after_discount = $dop_details->price - ($dop_details->price * ($day_promotion_per / 100));
                $discounted_price = $dop_details->price * $day_promotion_per / 100;
            }

            $package_price = $price_after_discount;
            $tax_price = $package_price * $dop_details->tax_percentage / 100;
            $total_tax_price = $tax_price * $no_of_package;
            $total_package_price_excluding_gst =  $package_price * $no_of_package;
            $total_price_including_tax = $total_package_price_excluding_gst + $total_tax_price;
        }
        
        if($booking_source == 'CRS'){
            $total_tax_price = $booking_details['tax_amount'];
            $total_price_including_tax = $booking_details['amount_to_pay'];
            $total_price_excluding_tax = $total_price_including_tax - $total_tax_price;
            $room_per_guest_price = $total_price_excluding_tax / $no_of_package;
            $discounted_price = $dop_details->price - $room_per_guest_price;
        }

        if($booking_source == 'WEBSITE'){
            if (round($amount_to_pay) != round($total_price_including_tax)) {
                return response()->json(['status' => 0, 'data' => $total_price_including_tax, 'msg' => 'Booking Failed Due to data tempering']);
            }
        }

        $bookings_data = [
            'hotel_id' => $hotel_id,
            'package_id' => $package_id,
            'package_name' => $dop_details['package_name'],
            'outing_dates' => date('Y-m-d', strtotime($checkin_date)),
            'booking_date' => date('Y-m-d'),
            'no_of_guest' => $no_of_package,
            'total_amount' => $total_price_including_tax,
            'paid_amount' => isset($booking_details['advance_collect_amt']) ? $booking_details['advance_collect_amt'] : $total_price_including_tax,
            'collected_by' => isset($booking_details['collected_by']) ? $booking_details['collected_by'] : '',
            'discount_amount' => isset($discounted_price) ? $discounted_price : 0,
            'tax_amount' => $total_tax_price,
            'payment_mode' => 1,
            'booking_status' => 2,
            'user_id' => $user_id,
            'arrival_time' => $user_details['arrival_time'],
            'guest_note' => $user_details['guest_note'],
            'internal_remark' => isset($user_details['Internal_remark']) ? $user_details['Internal_remark'] : '',
            'booking_source' =>$booking_source,
            'created_by' =>$admin_id,
            'sales_executive_id' => $sales_executive_id,
            'selling_price' => $amount_to_pay,
            'guest_name' => $user_details['first_name'] . ' ' . $user_details['last_name'],
            'guest_email' => $user_details['email_id'],
            'guest_phone' =>$user_details['mobile'],
            'gstin' =>isset($user_details['GST_IN']) ? $user_details['GST_IN'] : null,
        ];


        $bookings_details = DayBookings::insertGetId($bookings_data);
        $nbooking_id = 'DB-' . $bookings_details;
        $bookingDet = DayBookings::where('id', $bookings_details)->update(['booking_id' => $nbooking_id]);

        if (isset($booking_details['advance_collect_amt'])) {
            $url = $this->booking_engine->daySuccessBooking($nbooking_id, '12345', 'NA', 'NA', $nbooking_id, $mail_to_guest);
            if ($url != '') {
                $final_data = ["status" => 1, "message" => "Booking successfull"];
            } else {
                $final_data = ['status' => 0, 'message' => 'Booking Failed'];
            }
            return response()->json($final_data);
        }

        $invoice_id = $nbooking_id;
        $b_invoice_id = base64_encode($invoice_id);
        $invoice_hashData = $invoice_id . '|' . $total_price_including_tax . '|' . $total_price_including_tax . '|' . $email_id . '|' . $mobile . '|' . $b_invoice_id;

        $invoice_secureHash = hash('sha512', $invoice_hashData);

        if ($bookings_details) {
            $final_data = ["status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $invoice_id, 'invoice_secureHash' => $invoice_secureHash];
        } else {
            $final_data = ['status' => 0, 'message' => 'No package available for the selected date'];
        }
        return response()->json($final_data);
    }

    public function dayBookingsold(Request $request, $api_key)
    {
        $data = $request->all();
        $user_details = $data['user_details'];
        $booking_details = $data['booking_details'];
        $package_details = $data['package_details'];
        $hotel_id = $booking_details['hotel_id'];
        $package_id = $package_details['package_id'];
        $checkin_date = $booking_details['checkin_date'];
        $no_of_package = $package_details['no_of_package'];
        $amount_to_pay = $booking_details['amount_to_pay'];

        $mail_to_guest = isset($user_details['mail_to_guest']) ? $user_details['mail_to_guest'] : 1 ;
        // if($hotel_id == 2600){
        //     dd($mail_to_guest);

        // }

        if (array_key_exists('collected_by', $booking_details)) {
            // Key exists in the $booking_details array
            $booking_source = 'CRS';
        } else {
            // Key does not exist in the $booking_details array
            $booking_source = 'WEBSITE';

        }

        if(isset($booking_details['admin_id'])){
            $admin_id = $booking_details['admin_id'];
        }else{
            $admin_id = null;
        }

   
        $sales_executive_details = SalesExecutive::where('admin_id',$admin_id)->first();

        if(isset($sales_executive_details)){
          $sales_executive_id = $sales_executive_details->id;
        }else{
          $sales_executive_id = 0;
        }
       

        $email_id = $user_details['email_id'];
        $mobile = $user_details['email_id'];

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('company_id', 'gst_slab', 'is_taxable', 'partial_pay_amt')->first();

        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 0, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }

        if($booking_source == 'WEBSITE'){

        if (strlen($user_details['mobile']) != '10') {
            $res = array('status' => 0, 'message' => "Mobile number should be 10 digits");
            return response()->json($res);
        }
        
        }

        $company_name = DB::table('kernel.company_table')->where('company_id', $hotel_info->company_id)->first();


        //User registration
        $user_data['first_name'] = $user_details['first_name'];
        $user_data['last_name'] = isset($user_details['last_name']) ? $user_details['last_name'] : '';
        $user_data['email_id'] = $user_details['email_id'];
        $user_data['mobile'] = $user_details['mobile'];
        if (isset($user_details['mobile'])) {
            $user_name = $user_details['mobile'];
        } else {
            $user_name = $user_details['email_id'];
        }

        $user_data['password'] = uniqid(); //To generate unique rsndom number
        $user_data['password'] = Hash::make($user_data['password']); //Password encryption
        $user_data['country'] = $user_details['country'];
        $user_data['state'] = $user_details['state'];
        $user_data['city'] = $user_details['city'];
        $user_data['zip_code'] = $user_details['zip_code'];
        $user_data['company_name'] = $user_details['company_name'];
        $user_data['GSTIN'] = $user_details['GST_IN'];

        $res = User::updateOrCreate(
            [
                'mobile' => $user_details['mobile'],
                'company_id' => $hotel_info->company_id
            ],
            $user_data
        );
        $user_id = $res->user_id;

        $user_data['locality'] = $user_details['address'];
        $user_data['user_name'] = $user_name;
        $user_data['bookings'] = '';

        $user_res = UserNew::updateOrCreate(['mobile' => $user_details['mobile']], $user_data);
        $user_id_new = $user_res->user_id;


        $dop_details = DayPackage::where('id', $package_id)
                                   ->where('is_trash','0')
                                   ->first();

        // if($hotel_id == 2600){
            $day_promotion_per = 0;
            $discounted_price = 0;
            $price_after_discount = $dop_details->price;

            $checkin_date= date('Y-m-d', strtotime($checkin_date));

            $day_promotions = DayBookingPromotions::where('hotel_id', $hotel_id)
            ->where('is_active', '1')
            ->where(function($query) use ($checkin_date) {
                $query->where('valid_from', '<=', $checkin_date)
                      ->where('valid_to', '>=', $checkin_date);
            })
            ->whereRaw('NOT FIND_IN_SET(?, blackout_dates)', [$checkin_date])
            ->get();
            // dd($day_promotions);
            $max_discount = 0;

            foreach ($day_promotions as $promotion) {
              $day_package_ids = explode(',', $promotion->day_package_id);
              if (in_array($package_id, $day_package_ids)) {
                  $max_discount = max($max_discount, $promotion->discount_percentage);
              }
          }
          
          $day_promotion_per = $max_discount;
        
           if ($day_promotion_per) {
            $price_after_discount = $dop_details->price - ($dop_details->price * ($day_promotion_per / 100));
            $discounted_price = $dop_details->price * $day_promotion_per / 100;
           }

        $package_price = $price_after_discount;
        $tax_price = $package_price * $dop_details->tax_percentage / 100;
        $total_tax_price = $tax_price * $no_of_package;
        $total_package_price_excluding_gst =  $package_price * $no_of_package;
        $total_price_including_tax = $total_package_price_excluding_gst + $total_tax_price;

        //    dd($total_price_including_tax);

        // }

        // $package_price = $dop_details['discount_price'];
        // $tax_price = $package_price * $dop_details->tax_percentage / 100;
        // $total_tax_price = $tax_price * $no_of_package;
        // $total_package_price_excluding_gst =  $package_price * $no_of_package;
        // $total_price_including_tax = $total_package_price_excluding_gst + $total_tax_price;

        // if (array_key_exists('collected_by', $booking_details)) {
        //     // Key exists in the $booking_details array
        //     $booking_source = 'CRS';
        // } else {
        //     // Key does not exist in the $booking_details array
        //     $booking_source = 'WEBSITE';

        // }

        if($booking_source == 'WEBSITE'){

        if (round($amount_to_pay) != round($total_price_including_tax)) {
            return response()->json(['status' => 0, 'data' => $total_price_including_tax, 'msg' => 'Booking Failed Due to data tempering']);
        }

        }

      

        $bookings_data = [
            'hotel_id' => $hotel_id,
            'package_id' => $package_id,
            'package_name' => $dop_details['package_name'],
            'outing_dates' => date('Y-m-d', strtotime($checkin_date)),
            'booking_date' => date('Y-m-d'),
            'no_of_guest' => $no_of_package,
            'total_amount' => $total_price_including_tax,
            'paid_amount' => isset($booking_details['advance_collect_amt']) ? $booking_details['advance_collect_amt'] : $total_price_including_tax,
            'collected_by' => isset($booking_details['collected_by']) ? $booking_details['collected_by'] : '',
            'discount_amount' => isset($discounted_price) ? $discounted_price : 0,
            'tax_amount' => $total_tax_price,
            'payment_mode' => 1,
            'booking_status' => 2,
            'user_id' => $user_id,
            'arrival_time' => $user_details['arrival_time'],
            'guest_note' => $user_details['guest_note'],
            'internal_remark' => isset($user_details['Internal_remark']) ? $user_details['Internal_remark'] : '',
            'booking_source' =>$booking_source,
            'created_by' =>$admin_id,
            'sales_executive_id' => $sales_executive_id,
            'selling_price' => $amount_to_pay,
            'guest_name' => $user_details['first_name'] . ' ' . $user_details['last_name'],
            'guest_email' => $user_details['email_id'],
            'guest_phone' =>$user_details['mobile'],
            'gstin' =>isset($user_details['GST_IN']) ? $user_details['GST_IN'] : null,

        ];



        $bookings_details = DayBookings::insertGetId($bookings_data);

        $nbooking_id = 'DB-' . $bookings_details;

        $bookingDet = DayBookings::where('id', $bookings_details)->update(['booking_id' => $nbooking_id]);

        if (isset($booking_details['advance_collect_amt'])) {

            $url = $this->booking_engine->daySuccessBooking($nbooking_id, '12345', 'NA', 'NA', $nbooking_id, $mail_to_guest);

            if ($url != '') {
                $final_data = ["status" => 1, "message" => "Booking successfull"];
            } else {
                $final_data = ['status' => 0, 'message' => 'Booking Failed'];
            }
            return response()->json($final_data);
        }

        $invoice_id = $nbooking_id;

        $b_invoice_id = base64_encode($invoice_id);
        $invoice_hashData = $invoice_id . '|' . $total_price_including_tax . '|' . $total_price_including_tax . '|' . $email_id . '|' . $mobile . '|' . $b_invoice_id;

        $invoice_secureHash = hash('sha512', $invoice_hashData);

        if ($bookings_details) {
            $final_data = ["status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $invoice_id, 'invoice_secureHash' => $invoice_secureHash];
        } else {
            $final_data = ['status' => 0, 'message' => 'No package available for the selected date'];
        }
        return response()->json($final_data);
    }

     /**
    * Author: Saroj Patel
    * Date: 05-2024
    * Description: 
    */
    public function checkAccess($api_key, $hotel_id)
    {
        //$hotel=new HotelInformation();
        $cond = array('api_key' => $api_key);
        $comp_info = CompanyDetails::select('company_id')
            ->where($cond)->first();
        if (!$comp_info['company_id']) {
            return "Invalid";
        }
        $conditions = array('hotel_id' => $hotel_id, 'company_id' => $comp_info['company_id']);
        $info = HotelInformation::select('hotel_name')->where($conditions)->first();
        if ($info) {
            return 'valid';
        } else {
            return "invalid";
        }
    }

     /**
    * Author: Saroj Patel
    * Date: 05-2024
    * Description: 
    */
    public function successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid)
    {
        $DayOutingBookings =  DayBookings::where('booking_id', $invoice_id)->first();

        if ($DayOutingBookings) {

            $package_date = $DayOutingBookings->outing_dates;
            $no_of_guest = $DayOutingBookings->no_of_guest;
            $hotel_id = $DayOutingBookings->hotel_id;
            $package_id = $DayOutingBookings->package_id;

            $check_inventory = DayBookingARI::where('hotel_id', $hotel_id)
                ->where('package_id', $package_id)
                ->where('day_outing_dates', $package_date)
                ->first();

            if ($check_inventory->no_of_guest < $no_of_guest) {
                return response()->json(['status' => 0, 'msg' => 'No room available']);
            }

            $updated_guest = $check_inventory->no_of_guest - $no_of_guest;

            $update_guest_ari = DayBookingARI::where('hotel_id', $hotel_id)
                ->where('package_id', $package_id)
                ->where('day_outing_dates', $package_date)
                ->update(['no_of_guest' => $updated_guest]);

            $ari_log_data = [
                "hotel_id" => $hotel_id,
                "package_id" => $package_id,
                "from_date" => $package_date,
                "to_date" => $package_date,
                "no_of_guest" => $updated_guest,
                "rate" => $check_inventory->rate,
            ];

            $update_guest_log = DayBookingARILog::insert($ari_log_data);


            //success the booking
            $DayOutingBookings =  DayBookings::where('booking_id', $invoice_id)->update(['booking_status' => 1]);
            if ($DayOutingBookings) {
                $this->invoiceMailNew($invoice_id);
            }

            $company_dtls   = HotelInformation::where('hotel_id', $hotel_id)->first();
            $company_id     = $company_dtls->company_id;

            $CompanyDetaiils   = CompanyDetails::where('company_id', $company_id)->first();

            if ($CompanyDetaiils) {
                return $CompanyDetaiils->company_url;
            } else {
                return '';
            }
        }
    }

     /**
    * Author: Saroj Patel
    * Date: 05-2024
    * Description: 
    */
    public  function invoiceMailNew($id)
    {
        $booking_details =  DayOutingBookings::where('booking_id', $id)->first();
        $invoice        = $this->bookingVoucher($id);
        $hotel_details   = HotelInformation::where('hotel_id', $booking_details->hotel_id)->first();
        $body           = $invoice;
        $subject        = $hotel_details->hotel_name . " Booking Confirmation";

        $email_ids = 'sarojkumarpatel12@gmail.com';

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://tools.bookingjini.com/mailduck/raw',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('mail_from' => 'do-not-reply@bookingjini.com', 'mail_to' => $email_ids, 'subject' => $subject, 'html' => $body),
            CURLOPT_HTTPHEADER => array(
                'Authorization: hRv8FpLbN7'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
    }

     /**
    * Author: Saroj Patel
    * Date: 05-2024
    * Description: 
    */
    public function bookingVoucher($invoice_id)
    {

        $dayOutingBookings =  DayBookings::leftjoin('kernel.user_table', 'user_table.user_id', '=', 'day_bookings.user_id')
            ->where('day_bookings.booking_id', $invoice_id)
            ->select('day_bookings.*','user_table.address')
            ->first();

        $dayBookingDetails = DayPackage::where('id', $dayOutingBookings->package_id)->first();

        $package_price = $dayBookingDetails->price;
        // $price_after_discount = $dayBookingDetails->discount_price;
        // $discount_price = $package_price - $price_after_discount;
        $tax_percentage = $dayBookingDetails->tax_percentage;

        $discount_price = $dayOutingBookings->discount_amount;
        $price_after_discount = $package_price - $discount_price;



        $guest_note = $dayOutingBookings->guest_note;


        $hotel_details = HotelInformation::where('hotel_id', $dayOutingBookings->hotel_id)->first();

        $get_logo_info = CompanyDetails::select('logo')->where('company_id', $hotel_details->company_id)->first();
        $get_logo = ImageTable::select('image_name')->where('image_id', $get_logo_info->logo)->first();

        if (isset($get_logo->image_name)) {
            // $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $get_logo->image_name;
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        } else {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        }

        $booking_id = $dayOutingBookings->booking_id . date('dmy');
        $guest_name = $dayOutingBookings->guest_name;
        $mobile =  $dayOutingBookings->guest_phone;
        $email_id =  $dayOutingBookings->guest_email;
        $address =  $dayOutingBookings->address;

        $booking_date = date('d M Y', strtotime($dayOutingBookings->booking_date));
        $outing_date = date('d M Y', strtotime($dayOutingBookings->outing_dates));
        $package_name = $dayOutingBookings->package_name;
        $hotel_name = $hotel_details->hotel_name;

        $hotel_address = $hotel_details->hotel_address;
        $hotel_mobile = $hotel_details->mobile;
        $hotel_email_id = $hotel_details->email_id;
        $hotel_terms_and_cond = $hotel_details->terms_and_cond;



        $no_of_guest = $dayOutingBookings->no_of_guest;
        $tax_amount = $dayOutingBookings->tax_amount;

        $total_guest_price_excluding_tax = $price_after_discount * $no_of_guest;
        $total_guest_price_including_tax = $total_guest_price_excluding_tax + $tax_amount;

        $paid_amount = $dayOutingBookings->paid_amount;
        $selling_price = $dayOutingBookings->selling_price;
        $pay_at_hotel = $total_guest_price_including_tax - $paid_amount;

        // If the selling price is zero, set all related amounts to zero
        if($selling_price == 0){
            $total_guest_price_excluding_tax = 0;
            $tax_amount = 0;
            $tax_percentage = 0;
            $total_guest_price_including_tax = 0;
            $paid_amount = 0;
            $pay_at_hotel = 0;

        }



        $body = '<html>
        <head>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@700" />
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Urbanist:wght@600;700" />
            <title></title>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <meta name="x-apple-disable-message-reformatting" />
            <style></style>
        </head>
        
        <body>
            <div
                style="font-size: 0px; line-height: 1px; mso-line-height-rule: exactly; display: none; max-width: 0px; max-height: 0px; opacity: 0; overflow: hidden; mso-hide: all">
            </div>
            <center lang="und" dir="auto"
                style="width: 100%; table-layout: fixed; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%">
                <table cellpadding="0" cellspacing="0" border="0" role="presentation" bgcolor="white" width="1157"
                    style="background-color: white; width: 1157px; border-spacing: 0; font-family: Urbanist">
                    <tr>
                        <td valign="top" class="force-w100" width="100.00%"
                            style="padding-top: 36px; padding-bottom: 60px; width: 100%">
                            <table class="force-w100" cellpadding="0" cellspacing="0" border="0" role="presentation"
                                width="100.00%" style="width: 100%; border-spacing: 0">
                                <tr>
                                    <td align="center" style="padding-bottom: 15.5px">
                                        <p
                                            style="font-family: Inter; font-size: 30px; font-weight: 700; color: #3d3d3d; margin: 0; padding: 0; line-height: 36px; mso-line-height-rule: exactly">
                                            Booking Confirmation</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 15.5px; padding-bottom: 11.5px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0; font-family: Proxima Nova">
                                            <tr>
                                                <td valign="middle" width="700" style="width: 700px">
                                                    <img src="' . $hotel_logo . '"
                                                        width="139" style="max-width: initial; width: 139px; display: block" />
                                                </td>
                                                <td align="right" valign="middle" width="377" style="width: 377px">
                                                    <table class="force-w100" cellpadding="0" cellspacing="0" border="0"
                                                        role="presentation" width="377" style="width: 377px; border-spacing: 0">
                                                        <tr>
                                                            <td align="right" style="padding-bottom: 8px">
                                                                <p
                                                                    style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 400">Booking
                                                                        No.</span>
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_id . '
                                                                    </span>
                                                                </p>
                                                            </td>
                                                        </tr>
        
                                                        <tr>
                                                            <td align="right" style="padding-bottom: 8px">
                                                                <p
                                                                    style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 400">Payment
                                                                        Reference Number: </span>
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_id . '
                                                                    </span>
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 11.5px; padding-bottom: 11px">
                                        <div width="1077" style="width: 1077px; border-top: 2px solid #e7e7e7"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 11px; padding-bottom: 6px; padding-left: 40px">
                                        <p
                                            style="font-size: 18px; font-weight: 600; color: #616161; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Dear ' . $guest_name . ',</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 6px; padding-bottom: 11px; padding-left: 40px">
                                        <p width="1077" height="66"
                                            style="font-size: 18px; font-weight: 600; text-align: left; color: #616161; margin: 0; padding: 0; width: 1077px; height: 66px">
                                            <span>Thank you for choosing </span>
                                            <span
                                                style="font-size: 18px; font-weight: 700; color: #1C5EAA; text-align: left">' . $hotel_name . '</span>
                                            <span>, as your property of choice for your visit and booking through our hotels website. Your booking confirmation details have been provided below: </span>
                                            
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 11px; padding-bottom: 4px; padding-left: 40px">
                                        <p
                                            style="font-size: 20px; font-weight: 600; color: #616161; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                            Property name</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 4px;">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="top" width="790" style="width: 790px">
                                                    <p
                                                        style="font-size: 34px; font-weight: 700; color: #1C5EAA; margin: 0; padding: 0; line-height: 41px; mso-line-height-rule: exactly">
                                                        <span>' . $hotel_name . ' </span>
                                                    </p>
                                                </td>
                                                <td align="right" valign="top" width="287"
                                                    style="padding-bottom: 4px; width: 287px">
                                                    <p style="font-family: Inter; font-size: 16px; font-weight: 400">
                                                        Booked on:
                                                        <span
                                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_date . '
                                                    </span>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 12px; padding-bottom: 8px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td width="533" style="padding-right: 7px; width: 533px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="100.00%" height="261"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 100%; height: 261px; border-spacing: 0">
                                                        <tr>
                                                            <td valign="top" style="padding-top: 26px; padding-bottom: 22px;  padding-left: 22px;">
                                                                <table align="left" cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" style="border-spacing: 0">
                                                                    <tr>
                                                                        <td align="center" valign="top" height="107"
                                                                            style="height: 107px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <p
                                                                                        style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                        Package Date and Time</p>
        
                                                                                        <p
                                                                                            style="margin-top: 4px; margin-bottom: 0px;">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e;">
                                                                                                ' . $outing_date . '
                                                                                            </span>
                                                                                        </p>
        
                                                                                        <p
                                                                                            style="margin-top: 6px; margin-bottom: 0px;">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 400">11:30
                                                                                                am</span>
                                                                                        </p>
                                                                                    </td>
                                                                                    
                                                                                    
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="center" style="padding-top: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation"
                                                                                style="width: 100%; border-spacing: 0">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="padding-bottom: 6px">
                                                                                            <p
                                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                                Package Name</p>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td valign="middle"
                                                                                            style="padding-bottom: 6px;">
                                                                                            <p
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                ' . $package_name . '</p>
                                                                                        </td>

                                                                                    </tr>
                                                                                   
                                                                                </tbody>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td width="533" style="padding-left: 8px; width: 533px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="100.00%" height="261"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 100%; height: 261px; border-spacing: 0">
                                                        <tr>
                                                            <td align="left" valign="top"
                                                                style="padding-top: 26px; padding-bottom: 22px; padding-left: 24px">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" style="border-spacing: 0">
                                                                    <tr>
                                                                        <td style="padding-bottom: 6px">
                                                                            <p
                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                Primary Guest Details</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 6px; padding-bottom: 4px">
                                                                            <p
                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                                                                ' . $guest_name . '</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="left"
                                                                            style="padding-top: 4px; padding-bottom: 4.5px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/call_b.png"
                                                                                        
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $mobile . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="left"
                                                                            style="padding-top: 4.5px; padding-bottom: 4.5px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/email_b.png"
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $email_id . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 4.5px; padding-bottom: 6px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/location_b.png"
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $address . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 10px; padding-bottom: 6px">
                                                                            <p
                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                Expected Arrival</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 2px">
                                                                            <p
                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                11:30 am</p>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" valign="top" height="96"
                                        style="padding-top: 8px; padding-left: 40px; height: 96px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="1077"
                                            height="67"
                                            style="border-radius: 12px; border: 2px solid #c7c6c6; width: 1077px; height: 67px; border-spacing: 0">
                                            <tr>
                                                <td align="left" valign="middle" style="padding-left: 24px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        style="border-spacing: 0">
                                                        <tr>
                                                            <td>
                                                                <p
                                                                    style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                    Guest Note:</p>
                                                            </td>
                                                            <td style="padding-left: 6px">
                                                                <p
                                                                    style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                                                    ' . $guest_note . '</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top: 16px; padding-bottom: 12px; padding-right: 40px; padding-left: 589px;">
                                        <p
                                            style="font-family: Inter; font-size: 18px; font-weight: 700; text-align: left; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                            Price Breakup</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td align="right" width="100.00%" style="padding-top: 10px; width: 100%">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="100.00%"
                                            style="width: 100%; border-spacing: 0">
                                            
                                            <tr>
                                                <td style="padding-left: 589px; padding-right: 36px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="530" height="222"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 530px; height: 222px; border-spacing: 0">
                                                        <tr>
                                                            <td width="100.00%" style="width: 100%">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" width="100.00%" height="129"
                                                                    style="border-bottom: 1px solid #c7c6c6; width: 100%; height: 129px; border-spacing: 0">
                                                                    <tr>
                                                                        <td align="left" valign="top"
                                                                            style="padding-top: 15px; padding-bottom: 12px; padding-left: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td style="padding-bottom: 3.5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Date</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $outing_date . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 3.5px; padding-bottom: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p height="19"
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; height: 19px; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                       Package Price</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $package_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 5px; padding-bottom: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Discount Price
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width:215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $discount_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Price after discount</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $price_after_discount . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Selling Price</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $selling_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    No of guest</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ' . $no_of_guest . '</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    Amount Excl. Tax</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ₹' . $total_guest_price_excluding_tax . '</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                             </tr>
                                                                             <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    Tax Price</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ₹' . $tax_amount . '(' . $tax_percentage . '%)</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                             </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td width="100.00%" style="width: 100%">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" width="100.00%" height="97"
                                                                    style="width: 100%; height: 97px; border-spacing: 0">
                                                                    <tr>
                                                                        <td align="left" valign="top"
                                                                            style="padding-top: 12px; padding-bottom: 14px; padding-left: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td style="padding-bottom: 3.5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Amount Incl. tax
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $total_guest_price_including_tax . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 3.5px; padding-bottom: 4px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Paid Amount</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $paid_amount . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 4px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Pay at hotel</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $pay_at_hotel . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-bottom: 6.5px; padding-left: 40px">
                                        <p
                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Regards</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 6.5px; padding-bottom: 5.5px; padding-left: 40px">
                                        <p
                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Reservation Team</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 5.5px; padding-bottom: 7.5px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/location_bl.png"
                                                        width="15.00" height="16.00"
                                                        style="width: 15px; height: 16px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                        ' . $hotel_address . '</p>
                                                </td>
                                            </tr>
                                            
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 7.5px; padding-bottom: 4.5px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/call_bl.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        ' . $hotel_mobile . '</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" valign="top" height="40"
                                        style="padding-top: 4.5px; padding-left: 40px; height: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/email_bl.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        ' . $hotel_email_id . '</p>
                                                        
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_terms_and_cond . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_details->cancel_policy . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_details->hotel_policy . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                
                                                <td valign="middle" width="700" style="width: 700px">
                                                
                                                <span valign="middle" style="padding-left:50px;">Powered by</span>
                                                    <img src="' . $hotel_logo . '"
                                                        width="139" style="max-width: initial; width: 139px; display: block;padding-left: 35px;" />
                                                </td>
                                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </center>
        </body>
        
        </html>';


        if ($body) {
            $html_voucher = array('html' => $body);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://tools.bookingjini.com/pdfmonkey',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $html_voucher,
            ));
            $response = curl_exec($curl);
            curl_close($curl);

            $res = array('status' => 1, "message" => "Invoice feteched sucesssfully!", 'data' => $response);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => "Invoice details not found");
            return response()->json($res);
        }
    }

    /**
    * Author: Saroj Patel
    * Date: 05-2024
    * Description: 
    */
    public function fetchBookingDetails($booking_id)
    {

        $booking_details = DayBookings::where('booking_id', $booking_id)
            ->leftjoin('day_package', 'day_package.id', 'day_bookings.package_id')
            ->select('day_bookings.*', 'day_package.package_images')
            ->first();


        $hotel_details = HotelInformation::where('hotel_id', $booking_details->hotel_id)->first();
        $get_logo_info = CompanyDetails::select('logo')->where('company_id', $hotel_details->company_id)->first();
        $get_logo = ImageTable::select('image_name')->where('image_id', $get_logo_info->logo)->first();

        $pack_images = explode(',', $booking_details->package_images);
        $images = ImageTable::where('image_id', $pack_images[0])->first();
        $package_image = isset($images->image_name) ? $images->image_name:'';

        $booking_detais = [
            'paid_amount' => $booking_details->paid_amount,
            'outing_date' => date('d M Y', strtotime($booking_details->outing_dates)),
            'package_name' => $booking_details->package_name,
            'no_of_guest' => $booking_details->no_of_guest,
            'booking_id' => $booking_id . date('dmy'),
            'paid_amount' => $booking_details->paid_amount,
            'logo' => 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $get_logo->image_name,
            'package_image' => 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $package_image,

        ];


        if ($booking_detais) {
            $final_data = ['status' => 1, 'data' => $booking_detais];
        } else {
            $final_data = ['status' => 0, 'msg' => 'No package available.'];
        }


        return response()->json($final_data);
    }

     /**
    * Author: Saroj Patel
    * Date: 05-2024
    * Description: 
    */
    public function testingfun()
    {
        $order_id = 'order_NhRvTIZDJUoiAN';
        $row = DB::table('online_transaction_details')
            ->select('invoice_id')
            ->where('transaction_id', '=', $order_id)
            ->first();

        return (int)$row->invoice_id;
    }



  

}
