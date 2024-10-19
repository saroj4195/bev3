<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Invoice;
use App\User;
use App\Packages;
use App\AdminUser;
use App\MasterRoomType;
use App\HotelBooking;
use DB;
use App\Http\Controllers\Controller;
class BookingDetailsForGemsController extends Controller{
    public function getBookingDetails(){
        $all_bookings = array();
        $getBookingDetails =DB::select(DB::raw("SELECT DISTINCT (b.user_id), a.room_type, a.ref_no, a.extra_details, a.booking_date, a.invoice_id, a.total_amount, a.paid_amount, a.hotel_name, a.hotel_id FROM invoice_table a, hotel_booking b where a.invoice_id=b.invoice_id AND a.hotel_id = 1698 AND a.booking_status=1 AND  date(a.booking_date) = '2020-01-11' AND a.ref_no!='offline' ORDER BY a.invoice_id asc"));

        foreach($getBookingDetails as $key => $bk_details){
          $rooms          =array();
          $user_id        =$bk_details->user_id;
          $invoice_id     =$bk_details->invoice_id;
          $ref_no         =$bk_details->ref_no;

          $User_Details   = $this->userInfo($user_id);
          $Booked_Rooms   = $this->noOfBookings($invoice_id);



          $date1=date_create($Booked_Rooms[0]['check_out']);
          $date2=date_create($Booked_Rooms[0]['check_in']);
          $diff=date_diff($date1,$date2);
          $no_of_nights=$diff->format("%a");

          $booking_date   =$bk_details->booking_date;
          $booking_id     =date("dmy", strtotime($booking_date)).str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

          if($ref_no=='offline'){
            $mode_of_payment='Offline';
          }
          else{
            $mode_of_payment='Online';
          }
          $room_type_plan=explode(",", $bk_details->room_type);
          $plan= array();
          for($i=0; $i<sizeof($room_type_plan); $i++){
              $plan[]=substr($room_type_plan[$i], -5, -3);
          }
          $extra=json_decode($bk_details->extra_details);

          $k=0;
          $x=0;
          foreach ($Booked_Rooms  as $br) {
            $getRoomDetails = MasterRoomType:: select('room_type')->where('room_type_id',$br['room_type_id'])->first();
            $adult=0;
            $child=0;
            foreach($extra[$x] as $key=>$value){
                if(trim($br['room_type_id'])==trim($key)){
                    for($j=0;$j<$br['rooms'];$j++){
                     $adult=$adult+$value[0];
                     $child=$child+$value[1];
                    }
                  }
              }
              if($child=='NA'){
                  $child=0;
              }
              if($br['rooms'] == 0 || $no_of_nights == 0){
                  continue;
              }
              $rooms[] = array(
              "room_type_id"          => $br['room_type_id'],
              "room_type_name"        => $getRoomDetails->room_type,
              "no_of_rooms"           => $br['rooms'],
              "room_rate"             => ($bk_details->total_amount/$br['rooms'])/$no_of_nights,
              "plan"                  => trim($plan[$k]),
              "adult"                 => $adult,
              "child"                 => $child
              );
              $k++;
          }
          $x++;
          $user_info = array(
              "user_name"             => $User_Details['first_name'].' '.$User_Details['last_name'],
              "mobile"                => $User_Details['mobile'],
              "email"                 => $User_Details['email_id'],
              );

          $Bookings = array(
              "date_of_booking"       => $booking_date,
              "hotel_id"              => $bk_details->hotel_id,
              "hotel_name"            => $bk_details->hotel_name,
              "check_in"              => $Booked_Rooms[0]['check_in'],
              "check_out"             => $Booked_Rooms[0]['check_out'],
              "booking_id"            => $booking_id,
              "mode_of_payment"       => $mode_of_payment,
              "grand_total"           => $bk_details->total_amount,
              "paid_amount"           => $bk_details->paid_amount,
              "channel"               => "Bookingjini",
              "status"                => "confirmed"
              );


            $all_bookings[] = array(
            'UserDetails'               => $user_info,
            'BookingsDetails'           => $Bookings,
            'RoomDetails'               => $rooms
            );
            $k++;
        }
        if(sizeof($all_bookings)>0){
               // $url = 'https://gems.bookingjini.com/api/insertTravellerBookings';
               // $ch = curl_init();
               // curl_setopt( $ch, CURLOPT_URL, $url );
               // curl_setopt( $ch, CURLOPT_POST, true );
               // curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
               // curl_setopt( $ch, CURLOPT_POSTFIELDS, $all_bookings);
               // $booking_result = curl_exec($ch);
               // curl_close($ch);
               return $all_bookings;
        }
    }
    public function userInfo($user_id){
      $UserInformation = User::select('first_name', 'last_name', 'mobile', 'email_id')
                               ->where('user_id', $user_id)
                               ->first();
      return $UserInformation;
    }
    public function noOfBookings($invoice_id)
    {
        $booked_room_details = HotelBooking::select('room_type_id','rooms','check_in','check_out')
                                ->where('invoice_id', $invoice_id)
                                ->get();
        return $booked_room_details;
    }
}
