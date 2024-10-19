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


class DayBookingModificationController extends Controller
{

  public function bookingDetails($booking_id)
  {
    $booking_id = $booking_id;

    $day_booking = DayBookings::join('booking_engine.day_package', 'day_bookings.package_id', '=', 'day_package.id')
      ->select(
        'day_package.id as package_id',
        'day_bookings.paid_amount',
        'day_package.package_name',
        'day_package.package_images',
        'day_package.tax_percentage',
        'day_bookings.no_of_guest',
        'day_bookings.booking_id',
        'day_bookings.booking_date',
        'day_bookings.outing_dates',
        'day_bookings.total_amount',
        'day_bookings.tax_amount',
        'day_bookings.company_name',
        'day_bookings.gstin',
        'day_bookings.booking_source',
        'day_bookings.booking_status',
        'day_bookings.hotel_id',
        'day_bookings.selling_price',
        'day_bookings.guest_name',
        'day_bookings.guest_email as email_id',
        'day_bookings.guest_phone as mobile',
       
      )
      ->where('booking_id', $booking_id)
      ->first();

    if (!$day_booking) {
      return response()->json(['status' => 0, 'message' => 'Booking not found']);
    }
    $images_array = [];
    $pack_images = explode(',', $day_booking->package_images);
    $images = ImageTable::whereIn('image_id', $pack_images)->get();
    foreach ($images as $image) {
      $images_array[] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image->image_name;
    }
    $currency_name = HotelInformation::join('state_table', 'hotels_table.state_id', '=', 'state_table.state_id')
      ->join('company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
      ->select(
        'state_table.currency_code',
        'state_table.tax_name',
        'company_table.logo'
      )
      ->where('hotel_id', $day_booking->hotel_id)
      ->first();
    $logos = ImageTable::where('image_id', $currency_name->logo)->first();
    $logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $logos->image_name;


    if ($day_booking->booking_status == 1) {
      $is_cancelable = 1;
      $is_modifiable = 1;
    } else {
      $is_cancelable = 0;
      $is_modifiable = 0;
    }

    $day_booking->price_including_tax = $day_booking->total_amount;
    $day_booking->paid_amount= $day_booking->paid_amount;
    // $day_booking->tax_amount= $day_booking->tax_amount;
    $day_booking->price_excluding_tax = $day_booking->total_amount - $day_booking->tax_amount ;
    $day_booking->pay_at_hotel = $day_booking->total_amount - $day_booking->paid_amount;

    // If the selling price is zero, set pay_at_hotel amounts to zero
    if($day_booking->selling_price == 0 ){
      $day_booking->pay_at_hotel = 0;
    }

    $day_booking->booking_date = date('d M Y', strtotime($day_booking->booking_date));
    $day_booking->outing_dates = date('d M Y', strtotime($day_booking->outing_dates));
    $day_booking->currency_name = $currency_name->currency_code;
    $day_booking->tax_name = $currency_name->tax_name;
    $day_booking->package_images = isset($images_array[0]) ? $images_array[0] : '';
    $day_booking->package_logo = $logo;
    $day_booking->name = $day_booking->guest_name;
    $day_booking->is_cancelable = $is_cancelable;
    $day_booking->is_modifiable = $is_modifiable;
    $day_booking->is_business_booking = $day_booking->gstin === null ? 0 : 1;
    $day_booking->currency_symbol = '20B9';

    return response()->json(['status' => 1, 'message' => 'Booking details fetched  successfully', 'data' => $day_booking]);
  }

  public function modifyDaybooking(Request $request)
  {
    $booking_id = $request->booking_id;
    $modified_outing_dates = date('Y-m-d', strtotime($request->modified_outing_date));
    $modified_no_of_guest = $request->no_of_guest;
    $total_amount = $request->total_amount;
    $tax_amount = $request->tax_amount;

    $day_booking = DayBookings::where('booking_id', $booking_id)
      ->first();
    if (!$day_booking) {
      return response()->json(['status' => 0, 'message' => 'Booking not found']);
    }
    $package_id = $day_booking->package_id;
    $hotel_id = $day_booking->hotel_id;
    $no_of_guest = $day_booking->no_of_guest;
    $outing_dates = $day_booking->outing_dates;

    $day_booking->outing_dates = $modified_outing_dates;
    $day_booking->no_of_guest = $modified_no_of_guest;
    $day_booking->total_amount = number_format($total_amount, 2);
    $day_booking->tax_amount = number_format($tax_amount, 2); 
    // $mail_to_guest = $request->mail_to_guest;

    if (isset($request->paid_amount) && !empty($request->paid_amount)) {
      $day_booking->paid_amount = number_format($request->paid_amount, 2);
    }

    if (isset($request->collected_by) && !empty($request->collected_by)) {
      $day_booking->collected_by = $request->collected_by;
    }

    if (isset($request->payment_mode) && !empty($request->payment_mode)) {
      $day_booking->payment_mode = $request->payment_mode;
    }

    if (isset($request->refference_number) && !empty($request->refference_number)) {
      $day_booking->refference_number = $request->refference_number;
    }
    if (isset($request->arrival_time) && !empty($request->arrival_time)) {
      $day_booking->arrival_time = $request->arrival_time;
    }

    if (isset($request->Internal_remark) && !empty($request->Internal_remark)) {
      $day_booking->Internal_remark = $request->Internal_remark;
    }

    if (isset($request->guest_note) && !empty($request->guest_note)) {
      $day_booking->guest_note = $request->guest_note;
    }
    if (isset($request->company_name) && !empty($request->company_name)) {
      $day_booking->company_name = $request->company_name;
    }
    // if (isset($request->company_address) && !empty($request->company_address)) {
    //   $day_booking->company_address = $request->company_address;
    // }
    if (isset($request->GST_IN) && !empty($request->GST_IN)) {
      $day_booking->gstin = $request->GST_IN;
    }
    $day_booking->save();

    $user_id = $day_booking->user_id;

    $user = User::where('user_id', $user_id)->first();
    if ($user) {
      if ($request->has('mobile')) {
        $user->mobile = $request->mobile;
      }

      if ($request->has('name')) {
        $full_name = $request->name;
        $name_parts = explode(' ', $full_name);

        $user->first_name = $name_parts[0];
        $user->last_name = isset($name_parts[1]) ? $name_parts[1] : '';
      }

      if ($request->has('email_id')) {
        $user->email_id = $request->email_id;
      }

      $user->save();
    }

    $day_package_ari = DayBookingARI::where('hotel_id', $hotel_id)
      ->where('package_id', $package_id)
      ->where('day_outing_dates', $outing_dates)
      ->first();
    $avl_guest = $day_package_ari->no_of_guest;
    $day_package_ari->update(['no_of_guest' => $avl_guest + $no_of_guest]);

    $day_package = DayBookingARI::where('hotel_id', $hotel_id)
      ->where('package_id', $package_id)
      ->where('day_outing_dates', $modified_outing_dates)
      ->first();
    if (!$day_package) {
      return response()->json(['status' => 0, 'message' => 'Package is not available for this date']);
    }
    $avl_guest_m = $day_package->no_of_guest;
    $day_package->update(['no_of_guest' => $avl_guest_m - $modified_no_of_guest]);

    $ari_log = new DayBookingARILog;
    $ari_log->hotel_id = $hotel_id;
    $ari_log->package_id = $package_id;
    $ari_log->from_date = $modified_outing_dates;
    $ari_log->to_date = $modified_outing_dates;
    $ari_log->no_of_guest = $no_of_guest;
    $ari_log->rate = $total_amount;
    //    $ari_log ->log = $day_package->block_status;
    $ari_log->save();

    return response()->json(['status' => 1, 'message' => 'Booking modified  successfully']);
  }

  public function checkPackageAvailablity(Request $request)
  {
    $booking_id = $request->booking_id;
    $modified_outing_dates = date('Y-m-d', strtotime($request->modified_outing_date));

    $day_booking = DayBookings::join('booking_engine.day_package', 'day_bookings.package_id', '=', 'day_package.id')
      ->where('booking_id', $booking_id)
      ->select(
        'day_package.package_name',
        'day_package.price',
        'day_package.discount_price',
        'day_package.tax_percentage',
        'day_bookings.no_of_guest',
        'day_bookings.package_id',
        'day_bookings.hotel_id'
      )
      ->first();

    if (!$day_booking) {
      return response()->json(['status' => 0, 'message' => 'Booking not found']);
    }

    $package_id = $day_booking->package_id;
    $hotel_id = $day_booking->hotel_id;
    $no_of_guest = $day_booking->no_of_guest;

    $day_package = DayBookingARI::where('hotel_id', $hotel_id)
      ->where('package_id', $package_id)
      ->where('day_outing_dates', $modified_outing_dates)
      ->first();
    if (!$day_package) {
      return response()->json(['status' => 0, 'message' => 'Package is not available for this date']);
    }
    $data[] = [
      'package_name' => $day_booking->package_name,
      'no_of_guest' => $day_package->no_of_guest,
      'rate' => $day_package->rate,
      'gst' => $day_booking->price - $day_booking->discount_price,
      'tax_percentage' => $day_booking->tax_percentage
    ];

    return response()->json(['status' => 1, 'message' => 'Booking details fetched  successfully', 'data' => $data]);
  }

  public function cancelBooking($booking_id)
  {
    $booking_id = $booking_id;

    $day_booking = DayBookings::where('booking_id', $booking_id)->first();
    $outing_dates = $day_booking->outing_dates;
    $package_id = $day_booking->package_id;
    $hotel_id = $day_booking->hotel_id;
    $no_of_guest = $day_booking->no_of_guest;
    if (!$day_booking) {
      return response()->json(['status' => 0, 'message' => 'Booking not found']);
    }
    $day_booking->update(['booking_status' => 3]);

    $day_package = DayBookingARI::where('hotel_id', $hotel_id)
      ->where('package_id', $package_id)
      ->where('day_outing_dates', $outing_dates)
      ->first();
    $no_of_guest = $day_package->no_of_guest + $no_of_guest;

    $day_package->update(['no_of_guest' => $no_of_guest]);

    $ari_log = new DayBookingARILog;
    $ari_log->hotel_id = $hotel_id;
    $ari_log->package_id = $package_id;
    $ari_log->from_date = $outing_dates;
    $ari_log->to_date = $outing_dates;
    $ari_log->no_of_guest = $no_of_guest;
    $ari_log->rate = $day_booking->total_amount;
    //    $ari_log ->log = $day_package->block_status;
    $ari_log->save();

    return response()->json(['status' => 1, 'message' => 'Booking cancelled successfully']);
  }
}
