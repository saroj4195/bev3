<?php
namespace App\Http\Controllers\bharatstay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\HotelInformation;
use App\CompanyDetails;
use App\ImageTable;
use App\Invoice;
use App\HotelBooking;
use App\HotelFAQ;
use App\HotelStatistics;
use App\User;
use App\Http\Controllers\Controller;

class BookingController extends Controller 
{
    public function upcomingBookingDetails(Request $request)
    {
        $data = $request->all();
        $mobile = $data['mobile'];
        $today = date('Y-m-d');
        $todate = strtotime("+15 day", strtotime($today));
        $todate = date('Y-m-d', $todate);
  
        $url = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/';

        $user_ids = [];
        $user_details = User::select('user_id')->where('mobile', $mobile)->get();

        foreach ($user_details as $user) {
            $user_ids[] = $user->user_id;
        }

        $bookingList = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
            ->join('kernel.room_type_table', 'hotel_booking.room_type_id', '=', 'room_type_table.room_type_id')
            ->where('hotel_booking.check_in', '>=', $today)
            ->where('hotel_booking.check_in', '<', $todate)
            ->whereIn('invoice_table.user_id', $user_ids)
            ->where('invoice_table.booking_status', 1)
            ->select('invoice_table.hotel_name','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.booking_date', DB::raw('Min(hotel_booking.check_in) as check_in'), 'hotel_booking.check_out', 'hotels_table.hotel_address', 'hotels_table.star_of_property', 'hotels_table.exterior_image','hotels_table.mobile','hotels_table.latitude','hotels_table.longitude','room_type_table.room_type','room_type_table.image','invoice_table.room_type as room_rate_plan')
            ->groupBy('hotel_booking.check_in')
            ->first();


        if ($bookingList) {
            $date1 = date_create($bookingList->check_in);
            $date2 = date_create($bookingList->check_out);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            if ($diff == 0) {
                $diff = 1;
            }

            $room_type = $bookingList->room_rate_plan;
            $rate_plan = explode('(',$room_type);
            $rate_plan =  $rate_plan[1];
            $rate_plan = explode(')',$rate_plan);

            $exterior_image = explode(',', $bookingList->exterior_image);
            $exterior_hotel_image = ImageTable::whereIn('image_id',$exterior_image)->get();

            $all_images = [];
            foreach($exterior_hotel_image as $image){
                $all_images[] = $image['image_name'];
            }

           

            $mobile_no = explode(',',$bookingList->mobile);
            $bookingDetails["invoice_id"] = $bookingList->invoice_id;
            $bookingDetails["booking_id"] = date('dmy',strtotime($bookingList->booking_date)).$bookingList->invoice_id;
            $bookingDetails["hotel_name"] = $bookingList->hotel_name;
            $bookingDetails["check_in"] = date('d M Y',strtotime($bookingList->check_in));
            $bookingDetails["check_out"] = date('d M Y',strtotime($bookingList->check_out));
            $bookingDetails["booking_date"] = date('d M Y',strtotime($bookingList->booking_date));
            $bookingDetails["night"] = $diff;
            $bookingDetails["mobile"] = $mobile_no[0];
            $bookingDetails["star_of_property"] = $bookingList->star_of_property;
            $bookingDetails["exterior_image"] = $all_images;
            $bookingDetails["hotel_address"] = $bookingList->hotel_address;
            $bookingDetails["room_type"] = $bookingList->room_type;
            $bookingDetails["plan_name"] = $rate_plan[0];
            $bookingDetails["latitude"] = $bookingList->latitude;
            $bookingDetails["longitude"] = $bookingList->longitude;
            $bookingDetails["hotel_info_url"] = "https://pms.bookingjini.com/hotel-info/".base64_encode($bookingList->hotel_id);

            // $bookingDetails["room_type"] = $bookingList->room_type;
            // $bookingDetails["room_type_image"] = $room_type_image;

            $res = array('status' => 1, 'message' => 'Booking details fetched', 'list' => $bookingDetails);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Booking Not Found');
            return response()->json($res);
        }
    }
}
