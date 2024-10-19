<?php

namespace App\Http\Controllers\Extranetv4;

use App\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use DB;
use App\MasterRatePlan;
use App\DynamicPricingCurrentInventory;
use App\Http\Controllers\Controller;
use App\CompanyDetails;
use App\HotelInformation;
use App\InstantBookingSetup;
use App\ImageTable;

class InstantBookingController extends Controller
{
    protected $curency;
    public function __construct(CurrencyController $curency)
    {
        $this->curency = $curency;
    }

    public function setupSave(Request $request){
        $ib_setup = $request->all();
        $details = [
            'hotel_id' => $ib_setup['hotel_id'],
            'theme_color' => $ib_setup['theme_color'],
            'widget_positation' => $ib_setup['widget_position'],
            'is_active' => $ib_setup['is_active']
        ];
        if($ib_setup['is_active']==0){
            $save_setup_details = InstantBookingSetup::updateOrInsert(
                ['hotel_id' => $ib_setup['hotel_id']],
                ['is_active'=>$ib_setup['is_active']]
            );
        }else{
            $save_setup_details = InstantBookingSetup::updateOrInsert(
                ['hotel_id' => $ib_setup['hotel_id']],
                $details
            );
        }
        

        if($save_setup_details){
            $result = array('status' => 1, "message" => 'Setup Updated');
            return response()->json($result);
        }else{
            $result = array('status' => 0, "message" => 'Setup Failed');
            return response()->json($result);
        }
    }

    public function setupFetch($hotel_id){

        $fetch_setup_details = InstantBookingSetup::leftjoin('kernel.hotels_table','instant_booking_setup.hotel_id','=','hotels_table.hotel_id')
        ->leftjoin('kernel.company_table','kernel.hotels_table.company_id','=','company_table.company_id')
        ->where('hotels_table.hotel_id',$hotel_id)
        ->select('instant_booking_setup.*','company_table.api_key','company_table.logo')
        ->first(); 

        // return $fetch_setup_details;
            
        if($fetch_setup_details){

            $images = ImageTable::where('image_id', $fetch_setup_details->logo)
            ->select('image_name')
            ->first();

            $details['theme_color'] = $fetch_setup_details->theme_color ? $fetch_setup_details->theme_color : '#223189';
            $details['widget_position'] = ($fetch_setup_details->widget_positation)?$fetch_setup_details->widget_positation:'right';
            $details['is_active'] = $fetch_setup_details->is_active;
            $details['api_key'] = $fetch_setup_details->api_key;
            // $details['logo'] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/'.$images->image_name;
            $details['logo'] = 'https://instant.bookingjini.com/logo.png';

            $result = array('status' => 1, "data" => $details);
            return response()->json($result);

        }else{

            $details['theme_color'] = '#223189';
            $details['widget_position'] = 'right';
            $details['is_active'] = 0;
            $details['api_key'] = '';
            $details['logo'] = '';

            $result = array('status' => 0, "data" => $details, "message" => 'Instant booking Setup is Inactive');
            return response()->json($result);
        }

    }



}
