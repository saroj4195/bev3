<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Inventory; //class name from model
use App\MasterRoomType; //class name from model
use App\MasterHotelRatePlan; //class name from model
use App\RatePlanLog; //class name from model
use App\HotelInformation;
use App\HotelAmenities;
use App\PaidServices;
use App\Invoice;
use App\User;
use App\Country;
use App\HotelBooking;
use App\CmOtaDetails;
use App\ProductPrice;
use DB;
use App\CancellationPolicy;
use App\CancellationPolicyMaster;

use App\Notifications;
use App\BENotificationSlider;
use App\PmsAccount;
use App\ImageTable;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Extranetv4\InventoryService;
use App\Http\Controllers\Extranetv4\UpdateInventoryService;
use App\Http\Controllers\Extranetv4\CurrencyController;
use App\Http\Controllers\Extranetv4\IpAddressService;
use App\Http\Controllers\Extranetv4\InvRateBookingDisplayController;
use App\Http\Controllers\Extranetv4\UpdateInventoryServiceTest;
use App\Http\Controllers\Extranetv4\BeConfirmBookingInvUpdateRedirectingController;
use App\OnlineTransactionDetail;
use App\CompanyDetails;
use App\TaxDetails;
use App\Coupons;
use App\BeSettings;
use App\MasterRatePlan;
use App\BeBookingDetailsTable;
use App\BillingDetails;
use App\Http\Controllers\Extranetv4\PmsController;
use App\Http\Controllers\Controller;
//create a new class ManageInventoryController

use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;

class BookingEngineBasicSetupController extends Controller
{
    protected $invService, $ipService, $idsService;
    protected $updateInventoryService, $updateInventoryServiceTest;
    protected $otaInvRateService;
    protected $curency;
    protected $beConfBookingInvUpdate;
    protected $gst_arr = array();
    public function __construct(InventoryService $invService, UpdateInventoryService $updateInventoryService, CurrencyController $curency, IpAddressService $ipService, PmsController $idsService, InvRateBookingDisplayController $otaInvRateService, UpdateInventoryServiceTest $updateInventoryServiceTest, BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate)
    {
        $this->invService = $invService;
        $this->updateInventoryService = $updateInventoryService;
        $this->updateInventoryServiceTest = $updateInventoryServiceTest;
        $this->curency = $curency;
        $this->ipService = $ipService;
        $this->idsService = $idsService;
        $this->otaInvRateService = $otaInvRateService;
        $this->beConfBookingInvUpdate = $beConfBookingInvUpdate;
    }
    //validation rules
    private $rules = array(
        'hotel_id' => 'required | numeric',
        'from_date' => 'required ',
        'to_date' => 'required',
        'cart' => 'required'
    );
    //Custom Error Messages
    private $messages = [
        'hotel_id.required' => 'The hotel id field is required.',
        'hotel_id.numeric' => 'The hotel id must be numeric.',
        'from_date.required' => 'Check in date is required.',
        'to_date.required' => 'Check out is required.',
        'cart.required' => 'cart is required.'
    ];


    public function themeSetup($company_id)
    {

        $company_update = CompanyDetails::select('be_theme_color', 'be_header_color', 'be_menu_color')->where('company_id', $company_id)->first();

        if ($company_update) {
            return response()->json(array('status' => 1, 'message' => 'Theme setup details Fetched successfully.', 'data' => $company_update));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Theme setup details Fetched Failed.', 'data' => ''));
        }
    }

    public function themeSetupUpdate(Request $request)
    {

        $details = $request->all();
        $company_id = $request->company_id;

        if (isset($details['be_theme_color'])) {
            $data['be_theme_color'] = $details['be_theme_color'];
        } else {
            $data['be_theme_color'] = '#F5AB35';
        }

        if (isset($details['be_header_color'])) {
            $data['be_header_color'] = $details['be_header_color'];
        } else {
            $data['be_header_color'] = '#262626';
        }

        if (isset($details['be_header_color_text'])) {
            $data['be_menu_color'] = $details['be_header_color_text'];
        } else {
            $data['be_menu_color'] = '#262626';
        }

        $company_update = CompanyDetails::where('company_id', $company_id)->update($data);

        if ($company_update) {
            return response()->json(array('status' => 1, 'message' => UPDATE_THEMESETUP_MESSAGE));
        } else {
            return response()->json(array('status' => 0, 'message' => FAILED_THEMESETUP_MESSAGE));
        }
    }

    public function manageUrl($company_id)
    {

        $company_update = CompanyDetails::select('home_url', 'subdomain_name')->where('company_id', $company_id)->first();

        if ($company_update) {
            return response()->json(array('status' => 1, 'message' => 'Manage URL details Fetched successfully.', 'data' => $company_update));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Manage URL details Fetched Failed.', 'data' => ''));
        }
    }

    public function manageUrlUpdate(Request $request)
    {

        $data = $request->all();
        $company_id = $request->company_id;

        $data['home_url'] = $data['home_url'];
        $data['subdomain_name'] = $data['subdomain_name'];
        $data['company_url'] = $data['subdomain_name'];

        $check_subdomain = CompanyDetails::where('subdomain_name', $data['subdomain_name'])->where('company_id', '!=', $company_id)->orderBy('company_id', 'DESC')->first();

        if (empty($check_subdomain)) {
            $company_update = CompanyDetails::where('company_id', $company_id)->update($data);
            if ($company_update) {
                return response()->json(array('status' => 1, 'message' => UPDATE_URL_MESSAGE));
            } else {
                return response()->json(array('status' => 0, 'message' => FAILED_URL_MESSAGE));
            }
        } else {
            return response()->json(array('status' => 0, 'message' => "Booking Engine URL already exists !"));
        }
    }

    public function otherSetting($hotel_id)
    {

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('prepaid','partial_payment','partial_pay_amt', 'pay_at_hotel', 'advance_booking_days')->first();

        if ($hotel_info) {
            return response()->json(array('status' => 1, 'message' => 'Other Settings details Fetched successfully.', 'data' => $hotel_info));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Other Settings details Fetched Failed.', 'data' => ''));
        }
    }


    public function fetchOtherSetting($hotel_id)
    {
        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('prepaid','partial_payment','partial_pay_amt', 'pay_at_hotel','advance_booking_days','star_of_property','other_info')->first();

        $age_details = DB::table('bookingengine_age_setup')->where('hotel_id', $hotel_id)->first();

        $rating_array = [];
        $booking_payment = [];
        $others = [];

        $rating_array ['googel_rating'] = 0;
        $rating_array ['Tripadvisor_rating'] = 0;
        $rating_array ['hotel_rating'] = 0;
        $others['guest_local_id'] = 0;
        $others['unmarried_couple'] =  0;
        if ($hotel_info->other_info != '') {
            $other_info_details = json_decode($hotel_info->other_info);
            if(isset($other_info_details->rating)){
                foreach($other_info_details->rating as $other_info){
                    if($other_info->name=='Google'){
                        $rating_array ['googel_rating'] = $other_info->rating;
                    }elseif($other_info->name=='Tripadvisor'){
                        $rating_array ['Tripadvisor_rating'] = $other_info->rating;
                    }
                }
            }

            $rating_array ['hotel_rating'] = $hotel_info->star_of_property;

            $hotel_info->rating = $other_info_details->rating;
            $others['guest_local_id'] = $other_info_details->guest_local_id;
            $others['unmarried_couple'] =  $other_info_details->unmarried_couple;
        }
        $booking_payment['prepaid'] = $hotel_info->prepaid;
        $booking_payment['partial_payment'] = $hotel_info->partial_payment;
        $booking_payment['partial_pay_amt'] = $hotel_info->partial_pay_amt;
        $booking_payment['pay_at_hotel'] = $hotel_info->pay_at_hotel;

        $others['advance_booking_days'] = $hotel_info->advance_booking_days;
        $others['infant_age'] = $age_details->infant;
        $others['child_age'] = $age_details->children;

        $other_settings = ['rating'=>$rating_array,'booking_payment'=>$booking_payment,'others'=>$others];
        
        if ($hotel_info) {
            return response()->json(array('status' => 1, 'message' => 'Other Settings details Fetched successfully.', 'data' => $other_settings));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Other Settings details Fetched Failed.', 'data' => ''));
        }
    }

    public function updateOtherSetting(Request $request){

        $data = $request->all();
        $hotel_id = $data['hotel_id'];

        $ratting = [
            array('name'=>'Google','rating'=>$data['google_rating'],"logo" => "uploads/google.png"),
            array('name'=>'Tripadvisor','rating'=>$data['tripadvisor_rating'],"logo" => "uploads/tripadvisor.png")
       ];
      
        $other_hotel_details = [
            'rating' => $ratting,
            'guest_local_id' => $data['guest_local_id'],
            'unmarried_couple' => $data['unmarried_couple'],
        ];

        $other_info = json_encode($other_hotel_details);

        $other_settings_details ['prepaid'] = $data['prepaid'];
        $other_settings_details ['pay_at_hotel'] = $data['pay_at_hotel'];
        $other_settings_details ['partial_payment'] = $data['partial_payment'];
        $other_settings_details ['partial_pay_amt'] = $data['partial_pay_amt'];
        $other_settings_details ['advance_booking_days'] = $data['advance_booking_days'];
        $other_settings_details ['other_info'] = $other_info;
        $other_settings_details ['star_of_property'] = $data['star_of_property'];

        $age_details ['infant'] = $data['infant_age'];
        $age_details ['child'] = $data['child_age'];

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->update($other_settings_details);

        $age_stup = DB::table('bookingengine_age_setup')->updateOrInsert(
            ['hotel_id' => $hotel_id], // Condition to match existing record
            ['hotel_id' => $hotel_id,'infant' => $data['infant_age'],'children'=>$data['child_age'],'rating'=>$data['star_of_property']]
        );

        if($hotel_info){
            return response()->json(array('status' => 1, 'message' => 'Updated'));
        }else{
            return response()->json(array('status' => 1, 'message' => 'Update failed'));
        }
      
    }

    public function otherSettingUpdate(Request $request)
    {
        $data = $request->all();
        $hotel_id = $request->hotel_id;

        $partial_pay_amt = $data['partial_pay_amt'];
        $advance_booking_days = $data['advance_booking_days'];

        $other_hotel_details = [
            'rating' => $data['rating'],
            'guest_local_id' => $data['guest_local_id'],
            'unmarried_couple' => $data['unmarried_couple'],
        ];

        $other_info = json_encode($other_hotel_details);

        if ($partial_pay_amt > 0  && $partial_pay_amt < 100) {
            $partial_payment = 1;
        } else {
            $partial_payment = 0;
        }

        if (isset($data['pay_at_hotel'])) {
            $pay_at_hotel = $data['pay_at_hotel'];
        } else {
            $pay_at_hotel = 0;
        }

        if (isset($data['prepaid'])) {
            $prepaid = $data['prepaid'];
        } else {
            $prepaid = 1;
        }

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)
            ->update(['partial_pay_amt' => $partial_pay_amt, 'pay_at_hotel' => $pay_at_hotel, 'advance_booking_days' => $advance_booking_days, 'partial_payment' => $partial_payment, 'prepaid'=>$prepaid,'other_info'=>$other_info]);

        //This code is use to tracking hotelier Activity
        // $activity_id = "a1";
        // $activity_name = "";
        // if ($data['pay_at_hotel'] == 1) {
        //     $activity_description = "Activated Pay at Hotel";
        // } else {
        //     $activity_description = "DeActivated Pay at Hotel";
        // }
        // $activity_from = "BE";
        // $user_id = 0;
        // captureHotelActivityLog($hotel_id, $user_id, $activity_id, $activity_name, $activity_description, $activity_from);


        if ($hotel_info) {
            return response()->json(array('status' => 1, 'message' => 'success'));
        } else {
            return response()->json(array('status' => 0, 'message' => 'failed'));
        }
    }

    public function getLogoBanner($hotel_id)
    {

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('partial_pay_amt', 'advance_booking_days')->first();

        if ($hotel_info) {
            return response()->json(array('status' => 1, 'message' => 'Other Settings details Fetched successfully.', 'data' => $hotel_info));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Other Settings details Fetched Failed.', 'data' => ''));
        }
    }

    public function logoBannerUpdate(Request $request)
    {

        $data = $request->all();

        $hotel_id = $request->hotel_id;

        $failure_message = "Hotel image uploading failed";
        $image_ids = array();
        if ($request->hasFile('uploadFile')) {
            // Make Validation
            // $file = array('uploadFile' => $request->file('uploadFile'));
            // $validator = Validator::make($file, $this->rules);
            // if ($validator->fails()) {
            //     return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => $validator->errors()));
            // }
            //Initialize Check Trigger
            $cot = 0;
            //dd($request->file('uploadFile'));
            //Check number of images and upload it
            foreach ($request->file('uploadFile') as $media) {

                $getExt = $media->getClientOriginalName();
                $ext = substr($getExt, strpos($getExt, "."));
                $new_name = time() . rand(10, 1000000) . $ext; // Image Rename
                $new_name = str_replace(' ', '', $new_name); //Removeing space between  image name
                // Move Images
                if ($media) {
                    // Insert Details to database
                    $filePath = 'bookingEngine/uploads/' . $new_name;
                    $data['image_name'] = 'uploads/' . $new_name;
                    $file = $request->file('file');

                    Storage::disk('s3')->put($filePath, file_get_contents($media), 'public'); //Upload to Amazon S3

                    $fileUpload = new ImageTable;
                    $fileUpload->image_name = 'uploads/' . $new_name;
                    $fileUpload->hotel_id = $hotel_id;
                    $fileUpload->save();

                    if ($fileUpload->fill($data)->save()) {
                        array_push($image_ids, $fileUpload->image_id);
                        $cot = $cot + 1;
                    } else {
                        $res = array('status' => -1, "message" => $failure_message);
                        $res['errors'][] = "Internal server error";
                        return response()->json($res);
                    }
                } else {
                    $res = array('status' => -1, "message" => $failure_message);
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
            }
            // Check Trigger and return Message
            if ($cot == count($request->file('uploadFile'))) {
                $res = array('status' => 1, "message" => "Images uploaded successfully", "image_ids" => $image_ids);
                return response()->json($res);
            } else {
                $res = array('status' => -1, "message" => $failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        } else {
            return response()->json(array('status' => 0, 'message' => $failure_message, 'errors' => 'No File Choosen.'));
        }
    }

    public function checkAllSetup($hotel_id)
    {

        $count = 0;
        $check_details = HotelInformation::where('hotel_id', $hotel_id)->first();
        if ($check_details) {
            $company_id = $check_details->company_id;
            $count++;
        }
        $check_packages = DB::table('kernel.package_table')->where('hotel_id', $hotel_id)->first();
        if ($check_packages) {
            $count++;
        }
        $check_coupons =  DB::table('coupons')->where('hotel_id', $hotel_id)->first();
        if ($check_coupons) {
            $count++;
        }
        $check_notification_slider = DB::table('notification_slider')->where('hotel_id', $hotel_id)->first();
        if ($check_notification_slider) {
            $count++;
        }
        $check_notifications = DB::table('notifications')->where('hotel_id', $hotel_id)->first();
        if ($check_notifications) {
            $count++;
        }

        $check_paid_service = DB::table('paid_service')->where('hotel_id', $hotel_id)->first();
        if ($check_paid_service) {
            $count++;
        }
        if (isset($company_id)) {
            $check_company_table = DB::table('kernel.company_table')->select('be_theme_color', 'be_header_color', 'be_header_color_text')->where('company_id', $company_id)->first();
            if ($check_company_table) {
                $count++;
            }
        }
        if ($count == 7) {
            return response()->json(array('status' => 1, 'message' => 'Setup Completed.'));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Setup Incomplete.'));
        }
    }

    public function checkAllSetupOld($hotel_id)
    {
        $count = 0;
        $check_details = HotelInformation::where('hotel_id', $hotel_id)->first();
        if ($check_details) {

            // $company_id = $check_details->company_id;
            $count++;
        }
        $check_packages = DB::table('kernel.package_table')->where('hotel_id', $hotel_id)->first();
        if ($check_packages) {
            $count++;
        }
        $check_coupons =  DB::table('coupons')->where('hotel_id', $hotel_id)->first();
        if ($check_coupons) {
            $count++;
        }
        $check_notification_slider = DB::table('notification_slider')->where('hotel_id', $hotel_id)->first();
        if ($check_notification_slider) {
            $count++;
        }
        $check_notifications = DB::table('notifications')->where('hotel_id', $hotel_id)->first();
        if ($check_notifications) {
            $count++;
        }

        $check_paid_service = DB::table('paid_service')->where('hotel_id', $hotel_id)->first();
        if ($check_paid_service) {
            $count++;
        }
        if (isset($company_id)) {

            $check_company_table = DB::table('kernel.company_table')->select('be_theme_color', 'be_header_color', 'be_header_color_text')->where('company_id', $company_id)->first();
            if ($check_company_table) {
                $count++;
            }
        }

        if ($count == 7) {
            return response()->json(array('status' => 1, 'message' => 'All Setup fetched Successfully.'));
        } else {
            return response()->json(array('status' => 0, 'message' => 'All Setup fetched Failed.'));
        }
    }

    public function manuallyUpdateStatus($transection_id)
    {
        $invoice_id = substr($transection_id, 6);

        // $check_status = $this->successBooking($invoice_id,'otdc','NA','NA',$transection_id);
        // if($check_status){
        return response()->json(array('status' => 1, "message" => "Booking successful"));
        // }
        // else{
        //   return response()->json(array('status'=>0,"message"=>"Booking failed"));
        // }
    }

    public function fetchBENotifications($hotel_id, Request $request)
    {
        $be_notifications = Notifications::select('id', 'hotel_id', 'content_html')->where('hotel_id', $hotel_id)->first();
        if ($be_notifications != "") {
            $res = array('status' => 1, 'message' => 'Retrieved successfully', 'be_notifications' => $be_notifications);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => NO_NOTIFICATIONS_MESSAGE);
            return response()->json($res);
        }
    }
}
