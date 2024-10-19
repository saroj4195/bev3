<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Invoice;
use App\ImageTable;
use DB;

class B2CController extends Controller
{

    public function bookingList(Request $request, $type)
    {
        $data = $request->all();
        $mobile = $data['mobile'];
        $today = date('Y-m-d');
        $url = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/';

        if ($type == 'upcoming') {
            $booking_status = 1;
        } else if ($type == 'completed') {
            $booking_status = 1;
        } else {
            $booking_status = 3;
        }

        $user_ids = [];
        $user_details = User::select('user_id')->where('mobile', $mobile)->get();

        foreach ($user_details as $user) {
            $user_ids[] = $user->user_id;
        }

        $bookingList = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
            ->select('invoice_table.hotel_name', 'invoice_table.invoice_id', 'hotel_booking.check_in', 'hotel_booking.check_out', 'hotels_table.hotel_address', 'hotels_table.star_of_property', 'hotels_table.exterior_image', 'invoice_table.booking_status', 'hotels_table.mobile')
            ->whereIn('invoice_table.user_id', $user_ids)
            ->where('invoice_table.booking_status', $booking_status)
            ->groupBy('hotel_booking.invoice_id')
            ->orderBy('invoice_table.invoice_id', 'DESC')
            ->get();

        $all_booking_data = [];

        $exterior_hotel_image = '';
        foreach ($bookingList as $booking) {
            $exterior_images = $booking->exterior_image;
            if ($exterior_images) {
                $exterior_image = explode(',', $exterior_images);
                $exterior_hotel_image = ImageTable::where('image_id', $exterior_image[0])->first();
                if ($exterior_hotel_image) {
                    $exterior_hotel_image = $url . $exterior_hotel_image['image_name'];
                } else {
                    $exterior_hotel_image = '';
                }
            }

            $mobile_no = explode(',', $booking->mobile);

            $date1 = date_create($booking->check_in);
            $date2 = date_create($booking->check_out);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            if ($diff == 0) {
                $diff = 1;
            }

            $booking_data["booking_id"] = date('dmy') . $booking->invoice_id;
            $booking_data["hotel_name"] = $booking->hotel_name;
            $booking_data["exterior_image"] = $exterior_hotel_image;
            $booking_data["ratting"] = $booking->star_of_property;
            $booking_data["check_in"] = date('d M', strtotime($booking->check_in));
            $booking_data["check_out"] = date('d M', strtotime($booking->check_out));
            $booking_data["night"] = $diff;
            $booking_data["mobile"] = $mobile_no[0];
            $booking_data["hotel_address"] = $booking->hotel_address;
            // $booking_data["date"] = $booking->check_in;
            // $booking_data["booking_status"] = $booking->booking_status;

            if ($type == 'completed') {
                if ($booking->check_in < $today) {
                    array_push($all_booking_data, $booking_data);
                }
            } else if ($type == 'upcoming') {
                if ($booking->check_in >= $today) {
                    array_push($all_booking_data, $booking_data);
                }
            } else {
                array_push($all_booking_data, $booking_data);
            }
        }

        if (sizeof($all_booking_data) > 0) {

            usort($all_booking_data, function ($a, $b) {
                return strtotime($a['check_in']) - strtotime($b['check_in']);
            });

            $res = array('status' => 1, 'message' => 'Booking list fetched', 'list' => $all_booking_data);
            return response()->json($res);
        } else {
            $res = array('status' => 1, 'message' => 'Booking list Not Found');
            return response()->json($res);
        }
    }

    public function bookingDetails(Request $request, $booking_id)
    {
        $booking_id = substr($booking_id, 6);
        $today = date('Y-m-d');
        $url = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/';

        $bookingList = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
            ->where('invoice_table.invoice_id', $booking_id)
            ->select('invoice_table.hotel_name', 'invoice_table.hotel_id', 'invoice_table.invoice_id', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.room_type as room_rate_plan', 'hotels_table.hotel_address', 'hotels_table.star_of_property', 'hotels_table.exterior_image', 'hotels_table.mobile', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile as guest_mobile_no', 'invoice_table.booking_status', 'hotels_table.latitude', 'hotels_table.longitude')
            ->first();

        if ($bookingList) {

            $room_type_details = DB::table('hotel_booking')
                ->join('kernel.room_type_table', 'hotel_booking.room_type_id', '=', 'room_type_table.room_type_id')
                ->where('hotel_booking.invoice_id', $booking_id)
                ->select('hotel_booking.check_out', 'hotel_booking.check_in', 'hotel_booking.rooms', 'room_type_table.room_type', 'room_type_table.image')
                ->get();
                
            $room_type = $bookingList->room_rate_plan;
            $rate_plans = explode(',', $room_type);

            $room_type_array = [];
            $bookingDetails["check_in"] = '';
            $bookingDetails["check_out"] = '';
            if (sizeof($room_type_details) > 0) {
                foreach ($room_type_details as $key => $room_type) {
                    $room_type_image = explode(',', $room_type->image);
                    $room_type_image = ImageTable::where('image_id', $room_type_image[0])->first();
                    if ($room_type_image) {
                        $room_type_image = $url . $room_type_image['image_name'];
                    } else {
                        $room_type_image = '';
                    }
                    $rate_plan = explode('(', $rate_plans[$key]);
                    $rate_plan =  $rate_plan[1];
                    $rate_plan = explode(')', $rate_plan);

                    $bookingDet["no_of_rooms"] = $room_type->rooms;
                    $bookingDet["room_type"] = $room_type->room_type;
                    $bookingDet["plan_name"] = $rate_plan[0];
                    $bookingDet["room_type_image"] = $room_type_image;
                    array_push($room_type_array, $bookingDet);
                }

                $bookingDetails["check_in"] = date('D, d M Y', strtotime($room_type_details[0]->check_in));
                $bookingDetails["check_out"] = date('D, d M Y', strtotime($room_type_details[0]->check_out));
            }

            $date1 = date_create($bookingList->check_in);
            $date2 = date_create($bookingList->check_out);
            $diff = date_diff($date1, $date2);
            $diff = $diff->format("%a");
            if ($diff == 0) {
                $diff = 1;
            }
            $exterior_image = explode(',', $bookingList->exterior_image);
            $exterior_hotel_image = ImageTable::where('image_id', $exterior_image[0])->first();
            if ($exterior_hotel_image) {
                $exterior_hotel_image = $url . $exterior_hotel_image['image_name'];
            } else {
                $exterior_hotel_image = '';
            }

            $room_type_image = explode(',', $bookingList->image);
            $room_type_image = ImageTable::where('image_id', $room_type_image[0])->first();
            if ($room_type_image) {
                $room_type_image = $url . $room_type_image['image_name'];
            } else {
                $room_type_image = '';
            }

            $mobile_no = explode(',', $bookingList->mobile);

            $bookingDetails["hotel_id"] = $bookingList->hotel_id;
            $bookingDetails["hotel_image"] = $exterior_hotel_image;
            $bookingDetails["hotel_name"] = $bookingList->hotel_name;
            $bookingDetails["star_of_property"] = $bookingList->star_of_property;
            $bookingDetails["hotel_address"] = $bookingList->hotel_address;
            $bookingDetails["night"] = $diff;
            $bookingDetails["mobile"] = $mobile_no[0];
            $bookingDetails["total_amount"] = $bookingList->total_amount;
            $bookingDetails["paid_amount"] = $bookingList->paid_amount;
            $bookingDetails["tax_amount"] = $bookingList->tax_amount;
            $bookingDetails["discount_amount"] = $bookingList->discount_amount;
            $bookingDetails["guest_name"] = $bookingList->first_name . ' ' . $bookingList->last_name;
            $bookingDetails["email_id"] = $bookingList->email_id;
            $bookingDetails["guest_mobile_number"] = $bookingList->guest_mobile_no;
            $bookingDetails["hotel_info_url"] = "https://pms.bookingjini.com/hotel-info/" . base64_encode($bookingList->hotel_id);
            $bookingDetails["rooms"] = $room_type_array;

            if ($bookingList->booking_status == 1 && $today > $bookingList->check_in) {
                $booking_status = 'Completed';
                $color_code = '#AEC4FF';
            } elseif ($bookingList->booking_status == 1 && $today <= $bookingList->check_in) {
                $booking_status = 'Upcoming';
                $color_code = '#CCFDC8';
            } elseif ($bookingList->booking_status == 3) {
                $booking_status = 'Cancelled';
                $color_code = '#FFD0D2';
            }
            $bookingDetails["booking_status"] = $booking_status;
            $bookingDetails["color_code"] = $color_code;
            $bookingDetails["latitude"] = $bookingList->latitude;
            $bookingDetails["longitude"] = $bookingList->longitude;
            // $bookingDetails["guest_name"] = $bookingList->first_name .' '. $bookingList->last_name;

            $res = array('status' => 1, 'message' => 'Booking details fetched', 'list' => $bookingDetails);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Booking details fetch failed');
            return response()->json($res);
        }
    }

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
            ->select('invoice_table.hotel_name', 'invoice_table.hotel_id', 'invoice_table.invoice_id', 'invoice_table.booking_date', DB::raw('Min(hotel_booking.check_in) as check_in'), 'hotel_booking.check_out', 'hotels_table.hotel_address', 'hotels_table.star_of_property', 'hotels_table.exterior_image', 'hotels_table.mobile', 'hotels_table.latitude', 'hotels_table.longitude', 'room_type_table.room_type', 'room_type_table.image', 'invoice_table.room_type as room_rate_plan')
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
            $rate_plan = explode('(', $room_type);
            $rate_plan =  $rate_plan[1];
            $rate_plan = explode(')', $rate_plan);

            $exterior_image = explode(',', $bookingList->exterior_image);
            $exterior_hotel_image = ImageTable::whereIn('image_id', $exterior_image)->get();

            $all_images = [];
            foreach ($exterior_hotel_image as $image) {
                $all_images[] = $image['image_name'];
            }

            // if ($exterior_hotel_image) {
            //     $exterior_hotel_image = $url . $exterior_hotel_image['image_name'];
            // } else {
            //     $exterior_hotel_image = '';
            // }

            // $room_type_img = explode(',', $bookingList->image);
            // $room_type_image = ImageTable::where('image_id', $room_type_img[0])->first();

            // if ($room_type_image) {
            //     $room_type_image = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/'.$room_type_image['image_name'];
            // } else {
            //     $room_type_image = '';
            // }

            $mobile_no = explode(',', $bookingList->mobile);
            $bookingDetails["invoice_id"] = $bookingList->invoice_id;
            $bookingDetails["hotel_name"] = $bookingList->hotel_name;
            $bookingDetails["check_in"] = date('d M Y', strtotime($bookingList->check_in));
            $bookingDetails["check_out"] = date('d M Y', strtotime($bookingList->check_out));
            $bookingDetails["booking_date"] = date('d M Y', strtotime($bookingList->booking_date));
            $bookingDetails["night"] = $diff;
            $bookingDetails["mobile"] = $mobile_no[0];
            $bookingDetails["star_of_property"] = $bookingList->star_of_property;
            $bookingDetails["exterior_image"] = $all_images;
            $bookingDetails["hotel_address"] = $bookingList->hotel_address;
            $bookingDetails["room_type"] = $bookingList->room_type;
            $bookingDetails["plan_name"] = $rate_plan[0];
            $bookingDetails["latitude"] = $bookingList->latitude;
            $bookingDetails["longitude"] = $bookingList->longitude;
            $bookingDetails["hotel_info_url"] = "https://pms.bookingjini.com/hotel-info/" . base64_encode($bookingList->hotel_id);

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
