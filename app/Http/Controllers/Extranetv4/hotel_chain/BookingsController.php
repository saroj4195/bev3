<?php
namespace App\Http\Controllers\Extranetv4\hotel_chain;
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
use App\Http\Controllers\Controller;

class BookingsController extends Controller
{
    public function getOTDCAgentBookings()
    {
        
        $be_sql = "select T.hotel_name, T.coupon_name, sum(T.room_nights) AS room_nights from
        (
            select A.hotel_name, C.coupon_name, (B.rooms * DATEDIFF(B.check_out, B.check_in)) as room_nights   from
            booking_engine.invoice_table A
            inner join booking_engine.hotel_booking B ON A.invoice_id = B.invoice_id
            left join booking_engine.coupons C on A.agent_code = C.coupon_code AND A.hotel_id = C.hotel_id
            where A.hotel_id in (
            select hotel_id from kernel.hotels_table where hotel_name like 'Eco Retreat%')
            and A.booking_status = 1
            and A.agent_code not in ('','0','NA')
            and C.valid_to > '2022-03-01'
            and C.is_trash = 0
            order by hotel_name, coupon_name
        ) T
        group by T.hotel_name, T.coupon_name";
        $be_bookings = DB::select("$be_sql");
        $html = '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>Unit</th>';
        $html .= '<th>Agent</th>';
        $html .= '<th>Room Nights</th>';
        $html .= '</tr>';
        $total_agent_bookings = 0;
        $unit_name = $be_bookings[0]->hotel_name;
        $unit_total = 0;
        foreach($be_bookings as $be_booking)
        {
            if($unit_name != $be_booking->hotel_name)
            {
                $html .= '<tr>';
                $html .= '<th colspan="2">UNIT TOTAL</th>';
                $html .= '<th style="text-align:right">'.$unit_total.'</th>';
                $html .= '</tr>';
                $unit_total = 0;
                $unit_name = $be_booking->hotel_name;
            }
            $html .= '<tr>';
            $html .= '<td>'.$be_booking->hotel_name.'</td>';
            $html .= '<td>'.$be_booking->coupon_name.'</td>';
            $html .= '<td style="text-align:right">'.$be_booking->room_nights.'</td>';
            $html .= '</tr>';
            $unit_total += $be_booking->room_nights;
            
            $total_agent_bookings += $be_booking->room_nights;
        }
        $html .= '<tr>';
        $html .= '<th colspan="2">UNIT TOTAL</th>';
        $html .= '<th style="text-align:right">'.$unit_total.'</th>';
        $html .= '</tr>';
        $unit_total = 0;
        $unit_name = $be_booking->hotel_name;
        $html .= '<tr>';
        $html .= '<th colspan="2">GRAND TOTAL</th>';
        $html .= '<th style="text-align:right">'.$total_agent_bookings.'</th>';
        $html .= '</tr>';
        $html .= '</table>';
        echo $html;

    }
    //================================================================================================================================
    public function getOTDCGroupBookings()
    {
        
        $be_sql = "select T.hotel_name, T.coupon_name, sum(T.room_nights) AS room_nights from
        (
            select A.hotel_name, C.coupon_name, (B.rooms * DATEDIFF(B.check_out, B.check_in)) as room_nights   from
            booking_engine.invoice_table A
            inner join booking_engine.hotel_booking B ON A.invoice_id = B.invoice_id
            left join booking_engine.coupons C on A.agent_code = C.coupon_code AND A.hotel_id = C.hotel_id
            where A.hotel_id in (
            select hotel_id from kernel.hotels_table where hotel_name like 'Eco Retreat%')
            and A.booking_status = 1
            and A.agent_code not in ('','0','NA')
            and C.discount = 20
            order by hotel_name, coupon_name
        ) T
        group by T.hotel_name, T.coupon_name";
        $be_bookings = DB::select("$be_sql");
        $html = '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>Unit</th>';
        $html .= '<th>Agent</th>';
        $html .= '<th>Room Nights</th>';
        $html .= '</tr>';
        $total_agent_bookings = 0;
        $unit_name = $be_bookings[0]->hotel_name;
        $unit_total = 0;
        foreach($be_bookings as $be_booking)
        {
            if($unit_name != $be_booking->hotel_name)
            {
                $html .= '<tr>';
                $html .= '<th colspan="2">UNIT TOTAL</th>';
                $html .= '<th style="text-align:right">'.$unit_total.'</th>';
                $html .= '</tr>';
                $unit_total = 0;
                $unit_name = $be_booking->hotel_name;
            }
            $html .= '<tr>';
            $html .= '<td>'.$be_booking->hotel_name.'</td>';
            $html .= '<td>'.$be_booking->coupon_name.'</td>';
            $html .= '<td style="text-align:right">'.$be_booking->room_nights.'</td>';
            $html .= '</tr>';
            $unit_total += $be_booking->room_nights;
            
            $total_agent_bookings += $be_booking->room_nights;
        }
        $html .= '<tr>';
        $html .= '<th colspan="2">UNIT TOTAL</th>';
        $html .= '<th style="text-align:right">'.$unit_total.'</th>';
        $html .= '</tr>';
        $unit_total = 0;
        $unit_name = $be_booking->hotel_name;
        $html .= '<tr>';
        $html .= '<th colspan="2">GRAND TOTAL</th>';
        $html .= '<th style="text-align:right">'.$total_agent_bookings.'</th>';
        $html .= '</tr>';
        $html .= '</table>';
        echo $html;

    }
    //================================================================================================================================
    
    public function getOTDCOTABookings()
    {
        //bookingjini_cm connection to CM
        $be_sql = "select T.hotel_name, T.channel_name, sum(T.room_nights) as room_nights
            from (
            select B.hotel_name, 
            A.rooms_qty * DATEDIFF(A.checkout_at, A.checkin_at) as room_nights,
            A.channel_name  from
            cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            where  
            B.hotel_name like 'Eco Retreat%'
            and A.confirm_status = 1
            and A.cancel_status = 0) T
            group by T.hotel_name, T.channel_name
            order by T.hotel_name, T.channel_name";
        $be_bookings = DB::connection('bookingjini_cm')->select("$be_sql");
        $html = '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>Unit</th>';
        $html .= '<th>OTA</th>';
        $html .= '<th>Room Nights</th>';
        $html .= '</tr>';
        $total_agent_bookings = 0;
        $unit_name = $be_bookings[0]->hotel_name;
        $unit_total = 0;
        foreach($be_bookings as $be_booking)
        {
            if($unit_name != $be_booking->hotel_name)
            {
                $html .= '<tr>';
                $html .= '<th colspan="2">UNIT TOTAL</th>';
                $html .= '<th style="text-align:right">'.$unit_total.'</th>';
                $html .= '</tr>';
                $unit_total = 0;
                $unit_name = $be_booking->hotel_name;
            }
            $html .= '<tr>';
            $html .= '<td>'.$be_booking->hotel_name.'</td>';
            $html .= '<td>'.$be_booking->channel_name.'</td>';
            $html .= '<td style="text-align:right">'.$be_booking->room_nights.'</td>';
            $html .= '</tr>';
            $unit_total += $be_booking->room_nights;
            
            $total_agent_bookings += $be_booking->room_nights;
        }
        $html .= '<tr>';
        $html .= '<th colspan="2">UNIT TOTAL</th>';
        $html .= '<th style="text-align:right">'.$unit_total.'</th>';
        $html .= '</tr>';
       
        $html .= '<tr>';
        $html .= '<th colspan="2">GRAND TOTAL</th>';
        $html .= '<th style="text-align:right">'.$total_agent_bookings.'</th>';
        $html .= '</tr>';
        $html .= '</table>';
        echo $html;

    }
    //================================================================================================================================
    public function getOTDCOccupancy()
    {
        
        $hotel_sql = "
            select T.hotel_id, T.hotel_name, sum(T.total_rooms) AS total_rooms
            from (
            select A.hotel_id, A.hotel_name, B.room_type_id, B.room_type, B.total_rooms
            from kernel.hotels_table A
            inner join kernel.room_type_table B ON A.hotel_id = B.hotel_id
            where A.hotel_name like 'Eco Retreat%'
            order by A.hotel_name, B.room_type_id) T
            group by T.hotel_id, T.hotel_name
            order by T.hotel_name
        ";
        $hotels = DB::select("$hotel_sql");
        //ROOM NIGHTS
        $be_sql = "select T.hotel_id, T.hotel_name, sum(T.room_nights) AS booked_room_nights from
        (
            select A.hotel_id, A.hotel_name, (B.rooms * DATEDIFF(B.check_out, B.check_in)) as room_nights 
            from
            booking_engine.invoice_table A
            inner join booking_engine.hotel_booking B ON A.invoice_id = B.invoice_id
            where A.hotel_id in (
            select hotel_id from kernel.hotels_table where hotel_name like 'Eco Retreat%')
            and A.booking_status = 1
            and A.booking_source = 'WEBSITE'
            and A.invoice_id != 23840
        ) T
        group by T.hotel_id, T.hotel_name";
        $be_bookings = DB::select("$be_sql");
        
        foreach($be_bookings as $be_booking)
        {
            $booked_room_nights[$be_booking->hotel_id] = $be_booking->booked_room_nights;
        }
        //AMOUNT
        $be_sql = "
            select A.hotel_id, sum(total_amount) as total_amount, sum(paid_amount) as paid_amount, sum(tax_amount) as tax_amount, sum(discount_amount) as discount_amount
            from
            booking_engine.invoice_table A
            where A.hotel_id in (
            select hotel_id from kernel.hotels_table where hotel_name like 'Eco Retreat%')
            and A.booking_status = 1
            and A.booking_source = 'WEBSITE'
            group by A.hotel_id";
        $be_bookings = DB::select("$be_sql");
        
        foreach($be_bookings as $be_booking)
        {
            $total_amount[$be_booking->hotel_id] = $be_booking->total_amount;
            $gst_amount[$be_booking->hotel_id] = $be_booking->tax_amount;
        }

        $ota_sql = "select T.hotel_id, T.hotel_name, sum(T.room_nights) as booked_room_nights, 
            sum(amount) as total_amount, sum(tax_amount) as tax_amount
            from (
            select B.hotel_id, B.hotel_name, 
            A.rooms_qty * DATEDIFF(A.checkout_at, A.checkin_at) as room_nights,
            A.channel_name, A.amount, A.tax_amount
            from
            cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            where  
            B.hotel_name like 'Eco Retreat%'
            and A.confirm_status = 1
            and A.cancel_status = 0) T
            group by T.hotel_id, T.hotel_name";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
       
        foreach($ota_bookings as $ota_booking)
        {
            $ota_booked_room_nights[$ota_booking->hotel_id] = $ota_booking->booked_room_nights;
            $ota_total_amount[$ota_booking->hotel_id] = $ota_booking->total_amount;
            $ota_gst_amount[$ota_booking->hotel_id] = $ota_booking->tax_amount;
        }

        $startdate = date_create('2021-12-15');
        $enddate = date_create('2022-02-28');
        $no_of_nights = date_diff($startdate,$enddate)->format('%a');
        $html = '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>Unit</th>';
        $html .= '<th>Rooms</th>';
        $html .= '<th>Nights</th>';
        $html .= '<th>Total Room Nights</th>';
        $html .= '<th>Booked Room Nights</th>';
        $html .= '<th>Occupancy(%)</th>';
        $html .= '<th>Amount without GST</th>';
        $html .= '<th>GST Amount</th>';
        $html .= '<th>Total Amount</th>';
        
        $html .= '</tr>';
        $total_agent_bookings = 0;
        $unit_total = 0;
        $total_amount_without_gst = 0;
        $total_gst_amount = 0;
        $grand_total = 0;
        foreach($hotels as $hotel)
        {
            $unit_booked_room_nights = 0;
            $unit_total_amount = 0;
            $unit_tax_amount = 0;
            if($hotel->hotel_id == 2881)
                $hotel->total_rooms = 65;
            $html .= '<tr>';
            $html .= '<td>'.$hotel->hotel_name.'</td>';
            $html .= '<td>'.$hotel->total_rooms.'</td>';
            $html .= '<td>'.$no_of_nights.'</td>';
            $html .= '<td>'.$no_of_nights * $hotel->total_rooms.'</td>';

            if(isset($booked_room_nights[$hotel->hotel_id]))
                $unit_booked_room_nights += $booked_room_nights[$hotel->hotel_id];
            if(isset($ota_booked_room_nights[$hotel->hotel_id]))
                $unit_booked_room_nights += $ota_booked_room_nights[$hotel->hotel_id];
            $html .= '<td>'.$unit_booked_room_nights.'</td>';

            $html .= '<td>'.round($unit_booked_room_nights/($no_of_nights * $hotel->total_rooms)*100,2).'</td>';

            if(isset($total_amount[$hotel->hotel_id]))
                $unit_total_amount += $total_amount[$hotel->hotel_id];
            if(isset($ota_total_amount[$hotel->hotel_id]))
                $unit_total_amount += $ota_total_amount[$hotel->hotel_id];

            if(isset($gst_amount[$hotel->hotel_id]))
                $unit_tax_amount += $gst_amount[$hotel->hotel_id];
            if(isset($ota_gst_amount[$hotel->hotel_id]))
                $unit_tax_amount += $ota_gst_amount[$hotel->hotel_id];

            $html .= '<td>'.($unit_total_amount-$unit_tax_amount).'</td>';
            $html .= '<td>'.$unit_tax_amount.'</td>';
            $html .= '<td>'.$unit_total_amount.'</td>';
            $total_amount_without_gst += ($unit_total_amount-$unit_tax_amount);
            $total_gst_amount += $unit_tax_amount;
            $grand_total += $unit_total_amount;

        }
        $html .= '<tr>';
        $html .= '<td colspan="6">GRAND TOTAL</td>';
        $html .= '<td>'.$total_amount_without_gst.'</td>';
        $html .= '<td>'.$total_gst_amount.'</td>';
        $html .= '<td>'.$grand_total.'</td>';
        $html .= '</tr>';
        $html .= '</table>';
        echo $html;

    }
    
    //============================================================================================================================
    public function ecoretreatBookings()
    {
        $arr_hotel_names = [2881=>'Konark',2065=>'Bhitarkanika',2882=>'Satkosia',2883=>'Daringbadi',2884=>'Hirakud',2885=>'Pati Sonapur',2886=>'Koraput'];
        $arr_room_types = [
            2881=>array('Deluxe Cottage'=>20,'Premium Swiss Cottage'=>17,'Royal Luxury Swiss Cottage'=>9,'Presidential Suites'=>7),
            2065=>array('Deluxe Swiss Cottage'=>20,'Premium Swiss Cottage'=>5),
            2882=>array('Deluxe Swiss Cottage'=>20,'Premium Cottage'=>5),
            2883=>array('Deluxe Swiss Cottage'=>20,'Premium Swiss Cottage'=>5),
            2884=>array('Premium Swiss Cottage'=>25),
            2885=>array('Deluxe Swiss Cottage'=>20,'Premium Swiss Cottage'=>5),
            2886=>array('Deluxe Swiss Cottage'=>20,'Premium Swiss Cottage'=>5)
        ];
        $arr_hotel_ids = [2881,2065,2882,2883,2884,2885,2886];
        $arr_total_rooms = [
            2881=>53,
            2065=>25,
            2882=>25,
            2883=>25,
            2884=>25,
            2885=>25,
            2886=>25
        ];

        //BE BOOKING
        $be_bookings = Invoice::
        join('kernel.hotels_table','kernel.hotels_table.hotel_id','=','invoice_table.hotel_id')
        ->join('hotel_booking','hotel_booking.invoice_id','=','invoice_table.invoice_id')
        ->join('kernel.room_type_table','hotel_booking.room_type_id','=','kernel.room_type_table.room_type_id')
        ->join('kernel.user_table','kernel.user_table.user_id','=','invoice_table.user_id')
        ->whereIn('invoice_table.hotel_id',$arr_hotel_ids)
        ->where('invoice_table.booking_status',1)
        ->select('invoice_table.invoice_id','kernel.hotels_table.hotel_id','kernel.hotels_table.hotel_name',
        'hotel_booking.booking_date','hotel_booking.check_in','hotel_booking.check_out',
        'hotel_booking.room_type_id','room_type_table.room_type','hotel_booking.rooms',
        'total_amount','paid_amount','invoice_table.tax_amount','user_table.first_name','user_table.last_name',
        'user_table.email_id','user_table.mobile','invoice_table.booking_status','extra_details'
        )
        ->get();
        
        foreach($be_bookings as $be_booking)
        {
            $checkin_date = $be_booking->check_in;
            $checkout_date = $be_booking->check_out;
            $no_of_rooms = $be_booking->rooms;
            $hotel_id = $be_booking->hotel_id;
            $room_type_name = $be_booking->room_type;
            for($od = strtotime($checkin_date); $od < strtotime($checkout_date);)
            {
                $stay_date = date('d-m-y',$od);
                if(!isset($bookings_count[$hotel_id][$stay_date][$room_type_name]))
                    $bookings_count[$hotel_id][$stay_date][$room_type_name] = 0;
                $bookings_count[$hotel_id][$stay_date][$room_type_name] += $no_of_rooms;
                $od = strtotime('+1 day',$od);
            }
        }

        $ota_sql = "
            select A.unique_id, B.hotel_id, B.hotel_name, A.customer_details,
            A.rooms_qty, A.checkout_at, A.checkin_at,A.room_type, C.ota_room_type_name,
            A.channel_name, A.amount, A.tax_amount, C.room_type_id, D.room_type AS be_room_type
            from
            cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            left join cmlive.cm_ota_room_type_synchronize C ON A.room_type = C.ota_room_type AND A.hotel_id = C.hotel_id
            left join kernel.room_type_table D ON C.room_type_id = D.room_type_id AND C.hotel_id = D.hotel_id
            where  
            B.hotel_name like 'Eco Retreat%'
            and A.confirm_status = 1
            and A.cancel_status = 0";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
        foreach($ota_bookings as $ota_booking)
        {
            $checkin_date = $ota_booking->checkin_at;
            $checkout_date = $ota_booking->checkout_at;
            $no_of_rooms = $ota_booking->rooms_qty;
            $hotel_id = $ota_booking->hotel_id;
            $room_type_name = $ota_booking->be_room_type;
            for($od = strtotime($checkin_date); $od < strtotime($checkout_date);)
            {
                $stay_date = date('d-m-y',$od);
                if(!isset($bookings_count[$hotel_id][$stay_date][$room_type_name]))
                    $bookings_count[$hotel_id][$stay_date][$room_type_name] = 0;
                $bookings_count[$hotel_id][$stay_date][$room_type_name] += $no_of_rooms;
                $od = strtotime('+1 day',$od);
            }
        }
        $start_date = '2021-12-15';
        $end_date = '2022-02-28';

        $html = '';
        foreach($arr_hotel_names as $hotel_id => $hotel_name)
        {
            $html .= '<table width="100%" border="1">';
            if($hotel_id == 2881)
            {
                $html .= '<tr><th colspan="7">'.$hotel_name.'</th></tr>';
                $html .= '<tr>';
                $html .= '<td>DATE</td>';
                foreach($arr_room_types as $hotel_id2=>$room_types)
                {
                    if($hotel_id2 == $hotel_id)
                    {
                        foreach($room_types as $room_type=>$room_count)
                        {
                            $html .= '<td>'.$room_type.' ('.$room_count.')</td>';
                        }
                        $html .= '<td>TOTAL</td>';
                    }
                }
                $html .= '</tr>';
            }
            else if($hotel_id == 2884)
            {
                $html .= '<tr><th colspan="3">'.$hotel_name.'</th></tr>';
                $html .= '<tr>';
                $html .= '<td>DATE</td>';
                foreach($arr_room_types as $hotel_id2=>$room_types)
                {
                    if($hotel_id2 == $hotel_id)
                    {
                        foreach($room_types as $room_type=>$room_count)
                        {
                            $html .= '<td>'.$room_type.' ('.$room_count.')</td>';
                        }
                        $html .= '<td>TOTAL</td>';
                    }
                }
                $html .= '</tr>';
            }
            else
            {
                $html .= '<tr><th colspan="4">'.$hotel_name.'</th></tr>';
                $html .= '<tr>';
                $html .= '<td>DATE</td>';
                foreach($arr_room_types as $hotel_id2=>$room_types)
                {
                    if($hotel_id2 == $hotel_id)
                    {
                        foreach($room_types as $room_type=>$room_count)
                        {
                            $html .= '<td>'.$room_type.' ('.$room_count.')</td>';
                        }
                        $html .= '<td>TOTAL</td>';
                    }
                }
                $html .= '</tr>';
            }

            for($od = strtotime($start_date); $od <= strtotime($end_date);)
            {
                $day_total_inventory = 0;
                $day_total_booked = 0;
                $stay_date = date('d-m-y',$od);
                $stay_date2 = date('Y-m-d',$od);
                $html .= '<tr>';
                $html .= '<td><a href="https://be.bookingjini.com/bookingsbyunitanddate/'.$stay_date2.'/'.$hotel_id.'" target="_new">'.$stay_date.' ('.date('l',$od).')</a></td>';
                foreach($arr_room_types as $hotel_id2=>$room_types)
                {
                    if($hotel_id2 == $hotel_id)
                    {
                        foreach($room_types as $room_type=>$room_count)
                        {
                            if(isset($bookings_count[$hotel_id][$stay_date][$room_type]))
                            {
                                $booked_rooms = $bookings_count[$hotel_id][$stay_date][$room_type];
                                $available_rooms = $room_count - $booked_rooms;
                                $day_total_booked += $booked_rooms;
                                if($available_rooms == 0)
                                    $cell_color = '#f2725e';
                                else if($available_rooms < 0)
                                    $cell_color = 'yellow';
                                else
                                    $cell_color = 'white';

                            }
                            else
                            {
                                $booked_rooms = 0;
                                $available_rooms = $room_count;
                                $cell_color = '#03fca5';
                            }
                            $html .= '<td bgcolor="'.$cell_color.'">'.$booked_rooms.'/'.$room_count.'&nbsp;&nbsp;Avail: '.$available_rooms.'</td>';
                            
                            
                        }
                    }
                }
                $html .= '<td>'.$day_total_booked.'/'.$arr_total_rooms[$hotel_id].' Avail: '.($arr_total_rooms[$hotel_id] - $day_total_booked).'</td>';
                $html .= '</tr>';
                $od = strtotime('+1 day',$od);
            }


            
            $html .= '</table>';
        }
        
        echo $html;
        

    }
    //============================================================================================================================
    public function getInvoice($invoice_id)
    {
        $invoice_id = (int)$invoice_id;
        $be_sql = "select invoice  from
            booking_engine.invoice_table 
            where invoice_id = $invoice_id
        ";
        $be_bookings = DB::select("$be_sql");
        
        echo $be_bookings[0]->invoice;

    }
    //============================================================================================================================
    public function getOTDCBookingsByDate($booking_date)
    {
        $booking_date = addslashes(date('Y-m-d',strtotime($booking_date)));
        $hotel_sql = "
            select T.hotel_id, T.hotel_name, sum(T.total_rooms) AS total_rooms
            from (
            select A.hotel_id, A.hotel_name, B.room_type_id, B.room_type, B.total_rooms
            from kernel.hotels_table A
            inner join kernel.room_type_table B ON A.hotel_id = B.hotel_id
            where A.hotel_name like 'Eco Retreat%'
            order by A.hotel_name, B.room_type_id) T
            group by T.hotel_id, T.hotel_name
            order by T.hotel_name
        ";
        $hotels = DB::select("$hotel_sql");

        $be_sql = "
            select A.hotel_id, A.hotel_name, A.invoice_id, A.booking_date, 
            A.room_type, A.check_in_out, A.user_id, A.paid_amount, A.booking_source, B.first_name, B.last_name, A.updated_at, A.booking_status
            from
            booking_engine.invoice_table A
            inner join kernel.user_table B ON A.user_id = B.user_id
            where A.hotel_id in (
            select hotel_id from kernel.hotels_table where hotel_name like 'Eco Retreat%')
            and A.booking_source = 'WEBSITE'
            and A.booking_status IN (1,3)
            and date(A.booking_date) = '$booking_date'
            order by A.invoice_id desc
        ";
        $be_bookings = DB::select("$be_sql");
        
        
        $ota_sql = "
            select A.unique_id, B.hotel_id, B.hotel_name, A.customer_details,
            A.rooms_qty, A.checkout_at, A.checkin_at,A.room_type, C.ota_room_type_name,
            A.channel_name, A.amount, A.tax_amount, A.booking_date, A.booking_status
            from
            cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            left join cmlive.cm_ota_room_type_synchronize C ON A.room_type = C.ota_room_type AND A.hotel_id = C.hotel_id
            where  
            B.hotel_name like 'Eco Retreat%'
            and A.confirm_status = 1
            and A.cancel_status = 0
            and date(A.booking_date) = '$booking_date'";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
        
        
        $html = '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>Booking ID</th>';
        $html .= '<th>Booking Date</th>';
        $html .= '<th>Guest</th>';
        $html .= '<th>Unit</th>';
        $html .= '<th>Room Details</th>';
        $html .= '<th>Check In</th>';
        $html .= '<th>Check Out</th>';
        $html .= '<th>Nights</th>';
        $html .= '<th>Total Amount</th>';
        $html .= '<th>Booking Source</th>';
        $html .= '<th>Booking Status</th>';
        $html .= '</tr>';
        
        foreach($be_bookings as $be_booking)
        {
            $booking_status = '';
            $cancelled_on = '';
            if($be_booking->booking_status == 1)
            {
                $booking_status = 'Confirmed';
            }
            else if($be_booking->booking_status == 3)
            {
                $booking_status = 'Cancelled';
                $cancelled_on = date('d-m-Y',strtotime($be_booking->updated_at));
            }
            $booking_id = date('dmy',strtotime($be_booking->booking_date)).$be_booking->invoice_id;
            $check_in_out = str_replace('[','',$be_booking->check_in_out);
            $check_in_out = str_replace(']','',$check_in_out);
            $arr_check_in_out = explode('-',$check_in_out);
            $check_in = $arr_check_in_out[0].'-'.$arr_check_in_out[1].'-'.$arr_check_in_out[2];
            $check_out = $arr_check_in_out[3].'-'.$arr_check_in_out[4].'-'.$arr_check_in_out[5];

            $startdate = date_create($check_in);
            $enddate = date_create($check_out);
            $no_of_nights = date_diff($startdate,$enddate)->format('%a');

            $html .= '<tr>';
            $html .= '<td>'.$booking_id.'</td>';
            $html .= '<td>'.date('d-m-Y',strtotime($booking_date)).'</td>';
            $html .= '<td>'.$be_booking->first_name.' '.$be_booking->last_name.'</td>';
            $html .= '<td>'.$be_booking->hotel_name.'</td>';
            $html .= '<td>'.$be_booking->room_type.'</td>';
            $html .= '<td>'.$check_in.'</td>';
            $html .= '<td>'.$check_out.'</td>';
            $html .= '<td>'.$no_of_nights.'</td>';
            $html .= '<td>'.$be_booking->paid_amount.'</td>';
            $html .= '<td>'.$be_booking->booking_source.'</td>';
            if($booking_status == 'Confirmed')
                $html .= '<td>Confirmed</td>';
            else if($booking_status == 'Cancelled')
                $html .= '<td>Cancelled ('.$cancelled_on.')</td>';
            $html .= '</tr>';


        }
        foreach($ota_bookings as $ota_booking)
        {
            $booking_status = $ota_booking->booking_status;
            $cancelled_on = '';
            $booking_id = $ota_booking->unique_id;
            
            $check_in = $ota_booking->checkin_at;
            $check_out = $ota_booking->checkout_at;

            $startdate = date_create($check_in);
            $enddate = date_create($check_out);
            $no_of_nights = date_diff($startdate,$enddate)->format('%a');

            $html .= '<tr>';
            $html .= '<td>'.$booking_id.'</td>';
            $html .= '<td>'.date('d-m-Y',strtotime($booking_date)).'</td>';
            $html .= '<td>'.$ota_booking->customer_details.'</td>';
            $html .= '<td>'.$ota_booking->hotel_name.'</td>';
            $html .= '<td>'.$ota_booking->rooms_qty.' '.$ota_booking->ota_room_type_name.'</td>';
            $html .= '<td>'.$check_in.'</td>';
            $html .= '<td>'.$check_out.'</td>';
            $html .= '<td>'.$no_of_nights.'</td>';
            $html .= '<td>'.$ota_booking->amount.'</td>';
            $html .= '<td>'.$ota_booking->channel_name.'</td>';
            $html .= '<td>'.$booking_status.'</td>';
            $html .= '</tr>';

        }
        $html .= '</table>';
        echo $html;

    }
    //============================================================================================================================
    public function getOTDCBookingsByHotelIDAndDate($booking_date,$hotel_id)
    {
        $booking_date = addslashes(date('Y-m-d',strtotime($booking_date)));
        $hotel_id = (int)$hotel_id;
        $hotel_sql = "
            select T.hotel_id, T.hotel_name, sum(T.total_rooms) AS total_rooms
            from (
            select A.hotel_id, A.hotel_name, B.room_type_id, B.room_type, B.total_rooms
            from kernel.hotels_table A
            inner join kernel.room_type_table B ON A.hotel_id = B.hotel_id
            where A.hotel_id = $hotel_id
            order by A.hotel_name, B.room_type_id) T
            group by T.hotel_id, T.hotel_name
            order by T.hotel_name
        ";
        $hotels = DB::select("$hotel_sql");
        
        $be_sql = "
            select A.hotel_id, A.hotel_name, A.invoice_id, A.booking_date, 
            A.room_type, A.check_in_out, A.user_id, A.paid_amount, A.booking_source, B.first_name, B.last_name
            from
            booking_engine.invoice_table A
            inner join kernel.user_table B ON A.user_id = B.user_id
            inner join booking_engine.hotel_booking C ON A.invoice_id = C.invoice_id
            where A.hotel_id = $hotel_id
            and A.booking_status = 1
            and ('$booking_date' >= C.check_in AND '$booking_date' < C.check_out)
            order by A.room_type asc
        ";
        $be_bookings = DB::select("$be_sql");
        
        $ota_sql = "
            select A.unique_id, B.hotel_id, B.hotel_name, A.customer_details,
            A.rooms_qty, A.checkout_at, A.checkin_at,A.room_type, C.ota_room_type_name,
            A.channel_name, A.amount, A.tax_amount, A.booking_date
            from
            cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            left join cmlive.cm_ota_room_type_synchronize C ON A.room_type = C.ota_room_type AND A.hotel_id = C.hotel_id
            where  
            B.hotel_id = $hotel_id
            and A.confirm_status = 1
            and A.cancel_status = 0
            and ('$booking_date' >= A.checkin_at AND '$booking_date' < A.checkout_at)";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");

        $html = '<h3>'.date('d-M-Y l',strtotime($booking_date)).'</h3>';
        
        $html .= '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>Booking ID</th>';
        $html .= '<th>Booking Date</th>';
        $html .= '<th>Guest</th>';
        $html .= '<th>Unit</th>';
        $html .= '<th>Room Details</th>';
        $html .= '<th>Check In</th>';
        $html .= '<th>Check Out</th>';
        $html .= '<th>Nights</th>';
        $html .= '<th>Total Amount</th>';
        $html .= '<th>Booking Source</th>';
        $html .= '</tr>';
        
        foreach($be_bookings as $be_booking)
        {
            $booking_id = date('dmy',strtotime($be_booking->booking_date)).$be_booking->invoice_id;
            $check_in_out = str_replace('[','',$be_booking->check_in_out);
            $check_in_out = str_replace(']','',$check_in_out);
            $arr_check_in_out = explode('-',$check_in_out);
            $check_in = $arr_check_in_out[0].'-'.$arr_check_in_out[1].'-'.$arr_check_in_out[2];
            $check_out = $arr_check_in_out[3].'-'.$arr_check_in_out[4].'-'.$arr_check_in_out[5];

            $startdate = date_create($check_in);
            $enddate = date_create($check_out);
            $no_of_nights = date_diff($startdate,$enddate)->format('%a');

            $html .= '<tr>';
            $html .= '<td><a href="https://be.bookingjini.com/viewinvoice/'.$be_booking->invoice_id.'" target="_blank">'.$booking_id.'</a></td>';
            $html .= '<td>'.date('d-m-Y',strtotime($be_booking->booking_date)).'</td>';
            $html .= '<td>'.$be_booking->first_name.' '.$be_booking->last_name.'</td>';
            $html .= '<td>'.$be_booking->hotel_name.'</td>';
            $html .= '<td>'.$be_booking->room_type.'</td>';
            $html .= '<td>'.$check_in.'</td>';
            $html .= '<td>'.$check_out.'</td>';
            $html .= '<td>'.$no_of_nights.'</td>';
            $html .= '<td>'.$be_booking->paid_amount.'</td>';
            $html .= '<td>'.$be_booking->booking_source.'</td>';
            $html .= '</tr>';


        }
        foreach($ota_bookings as $ota_booking)
        {
            $booking_id = $ota_booking->unique_id;
            
            $check_in = $ota_booking->checkin_at;
            $check_out = $ota_booking->checkout_at;

            $startdate = date_create($check_in);
            $enddate = date_create($check_out);
            $no_of_nights = date_diff($startdate,$enddate)->format('%a');

            $html .= '<tr>';
            $html .= '<td>'.$booking_id.'</td>';
            $html .= '<td>'.date('d-m-Y',strtotime($ota_booking->booking_date)).'</td>';
            $html .= '<td>'.$ota_booking->customer_details.'</td>';
            $html .= '<td>'.$ota_booking->hotel_name.'</td>';
            $html .= '<td>'.$ota_booking->rooms_qty.' '.$ota_booking->ota_room_type_name.'</td>';
            $html .= '<td>'.$check_in.'</td>';
            $html .= '<td>'.$check_out.'</td>';
            $html .= '<td>'.$no_of_nights.'</td>';
            $html .= '<td>'.$ota_booking->amount.'</td>';
            $html .= '<td>'.$ota_booking->channel_name.'</td>';
            $html .= '</tr>';

        }
        $html .= '</table>';
        echo $html;

    }
    //============================================================================================================================
    public function getOTDCBookingsByStayDate($stay_date)
    {
        if(strpos($stay_date,"'"))
        {
            echo "Invalid input";
            return;
        }
        
        $stay_date = addslashes(date('Y-m-d',strtotime($stay_date)));
        
        $hotel_sql = "
            select T.hotel_id, T.hotel_name, sum(T.total_rooms) AS total_rooms
            from (
            select A.hotel_id, A.hotel_name, B.room_type_id, B.room_type, B.total_rooms
            from kernel.hotels_table A
            inner join kernel.room_type_table B ON A.hotel_id = B.hotel_id
            where A.hotel_id IN (SELECT hotel_id FROM kernel.hotels_table WHERE hotel_name LIKE 'Eco Retreat %')
            order by A.hotel_name, B.room_type_id) T
            group by T.hotel_id, T.hotel_name
            order by T.hotel_name
        ";
        $hotels = DB::select("$hotel_sql");
        
        $be_sql = "
            select A.hotel_id, A.hotel_name, A.invoice_id, A.booking_date, 
            A.room_type, A.check_in_out, A.user_id, A.paid_amount, A.booking_source, B.first_name, B.last_name, A.tax_amount, 
            A.paid_service_id, B.mobile, A.extra_details, B.email_id
            from
            booking_engine.invoice_table A
            inner join kernel.user_table B ON A.user_id = B.user_id
            inner join 
            (SELECT DISTINCT invoice_id, check_in, check_out FROM booking_engine.hotel_booking where hotel_id IN 
            (SELECT hotel_id FROM kernel.hotels_table WHERE hotel_name LIKE 'Eco Retreat %') ) C 
            ON A.invoice_id = C.invoice_id
            where A.hotel_id IN (SELECT hotel_id FROM kernel.hotels_table WHERE hotel_name LIKE 'Eco Retreat %')
            and A.booking_status = 1
            and ('$stay_date' >= C.check_in AND '$stay_date' < C.check_out)
            order by A.invoice_id DESC
        ";
        $be_bookings = DB::select("$be_sql");

        $be_room_sql = "
            SELECT  A.hotel_id, A.room_type_id, B.room_type,  sum(A.rooms) AS booked_rooms
            FROM booking_engine.hotel_booking A
            inner join kernel.room_type_table B ON A.room_type_id = B.room_type_id AND A.hotel_id = B.hotel_id
            where A.hotel_id IN 
                (SELECT hotel_id FROM kernel.hotels_table WHERE hotel_name LIKE 'Eco Retreat %')
            and A.booking_status = 1
            and ('$stay_date' >= A.check_in AND '$stay_date' < A.check_out)
            group by A.hotel_id, A.room_type_id, B.room_type
            order by A.hotel_id
        ";
        $be_room_details = DB::select("$be_room_sql");
        
        $ota_sql = "
            select A.unique_id, B.hotel_id, B.hotel_name, A.customer_details,
            A.rooms_qty, A.checkout_at, A.checkin_at,A.room_type, C.ota_room_type_name,
            A.channel_name, A.amount, A.tax_amount, A.no_of_adult, A.no_of_child
            from
            cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            left join cmlive.cm_ota_room_type_synchronize C ON A.room_type = C.ota_room_type AND A.hotel_id = C.hotel_id
            where  
            B.hotel_id IN (SELECT hotel_id FROM kernel.hotels_table WHERE hotel_name LIKE 'Eco Retreat %')
            and A.confirm_status = 1
            and A.cancel_status = 0
            and ('$stay_date' >= A.checkin_at AND '$stay_date' < A.checkout_at)";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");

        $ota_room_sql = "
            select  A.hotel_id, C.room_type_id, B.room_type, sum(A.rooms_qty) AS booked_rooms
            from
            cmlive.cm_ota_booking A
        
            left join cmlive.cm_ota_room_type_synchronize C ON A.room_type = C.ota_room_type AND A.hotel_id = C.hotel_id
            inner join kernel.room_type_table B ON C.room_type_id = B.room_type_id AND A.hotel_id = B.hotel_id
            where  
            B.hotel_id IN (SELECT hotel_id FROM kernel.hotels_table WHERE hotel_name LIKE 'Eco Retreat %')
            and A.confirm_status = 1
            and A.cancel_status = 0
            and ('$stay_date' >= A.checkin_at AND '$stay_date' < A.checkout_at)
            group by A.hotel_id, C.room_type_id, B.room_type
            order by A.hotel_id";
        $ota_room_details = DB::connection('bookingjini_cm')->select("$ota_room_sql");

        
        

        $html = '<h3>'.date('d-M-Y l',strtotime($stay_date)).'</h3>';

        foreach($hotels as $hotel)
        {
            $invoice_sql = '';
            if($hotel->hotel_id == 2881)//konark
            {
                $hotel_code = '01';
                $invoice_sql = "select id, invoice_id, transaction_id from booking_engine.eco_retreat_konark";
            }
            else if($hotel->hotel_id == 2065)//bhitarkanika
            {
                $hotel_code = '02';
                $invoice_sql = "select id, invoice_id, transaction_id from booking_engine.eco_retreat_bhitarkanika";
            }
            else if($hotel->hotel_id == 2882)//satkosia
            {
                $hotel_code = '03';
                $invoice_sql = "select id, invoice_id, transaction_id from booking_engine.eco_retreat_satkosia";
            }
            else if($hotel->hotel_id == 2884)//hirakud
            {
                $hotel_code = '04';
                $invoice_sql = "select id, invoice_id, transaction_id from booking_engine.eco_retreat_hirakud";
            }
            else if($hotel->hotel_id == 2883)//daringbadi
            {
                $hotel_code = '05';
                $invoice_sql = "select id, invoice_id, transaction_id from booking_engine.eco_retreat_daringbadi";
            }
            else if($hotel->hotel_id == 2885)//pati sonapur
            {
                $hotel_code = '06';
                $invoice_sql = "select id, invoice_id, transaction_id from booking_engine.eco_retreat_sonapur";
            }
            else if($hotel->hotel_id == 2886)//koraput
            {
                $hotel_code = '07';
                $invoice_sql = "select id, invoice_id, transaction_id from booking_engine.eco_retreat_koraput";
            }
            
            if($invoice_sql != '')
            {
                $be_invoices = DB::select("$invoice_sql");
                foreach($be_invoices as $be_invoice)
                {
                    $inv_sl_no = $hotel_code.str_pad($be_invoice->id,4,'0',STR_PAD_LEFT);
                    $invoice_sl_no[$be_invoice->invoice_id] = $inv_sl_no;
                }
            }

            $roomtype_count[$hotel->hotel_id] = array();
            foreach($be_room_details as $be_room_detail)
            {
                if($hotel->hotel_id == $be_room_detail->hotel_id)
                {
                    $roomtype_count[$hotel->hotel_id][$be_room_detail->room_type] = $be_room_detail->booked_rooms;
                }
            }
            //print_r($roomtype_count);
            foreach($ota_room_details as $ota_room_detail)
            {
                if($hotel->hotel_id == $ota_room_detail->hotel_id)
                {
                    if(isset($roomtype_count[$hotel->hotel_id][$ota_room_detail->room_type]))
                        $roomtype_count[$hotel->hotel_id][$ota_room_detail->room_type] += $ota_room_detail->booked_rooms;
                    else
                        $roomtype_count[$hotel->hotel_id][$ota_room_detail->room_type] = $ota_room_detail->booked_rooms;
                }
            }
            $unit_total_booked_rooms = 0;
            $str_room_details = '';
            foreach($roomtype_count[$hotel->hotel_id] as $room_type_name=>$room_count)
            {
                $str_room_details .= $room_type_name.':'.$room_count.' | ';
                $unit_total_booked_rooms += $room_count;
            }
            $str_room_details .= 'Total Booked rooms: '.$unit_total_booked_rooms;
                
            $unit_total_booking = 0;
            $unit_total_occupancy = 0;
            $html .= '<h4>'.$hotel->hotel_name.'</h4>';
            $html .= '<table border="1" style="width:99%">';
            $html .= '<tr>';
            $html .= '<th>Booking ID</th>';
            $html .= '<th style="width:20%">Guest</th>';
            $html .= '<th>Unit</th>';
            $html .= '<th>Room Details</th>';
            $html .= '<th style="width:10%">Check In</th>';
            $html .= '<th style="width:10%">Check Out</th>';
            $html .= '<th>Nights</th>';
            $html .= '<th>Occupancy</th>';
            $html .= '<th>Amount Before Tax</th>';
            $html .= '<th>GST Amount</th>';
            $html .= '<th>Total Amount</th>';
            $html .= '<th>Booking Source</th>';
            if($hotel->hotel_id == 2881)
                $html .= '<th>Pickup</th>';
            $html .= '</tr>';
            
            foreach($be_bookings as $be_booking)
            {
                if($be_booking->hotel_id == $hotel->hotel_id)
                {
                    $extra_details = $be_booking->extra_details;
                    $extras = json_decode($extra_details);
                    $occupancy = 0;
                    foreach($extras as $extra)
                    {
                        foreach($extra as $room_type=>$p)
                        {
                            $occupancy+= $p[0]+$p[1];
                        }
                    }

                    $booking_id = date('dmy',strtotime($be_booking->booking_date)).$be_booking->invoice_id;
                    $check_in_out = str_replace('[','',$be_booking->check_in_out);
                    $check_in_out = str_replace(']','',$check_in_out);
                    $arr_check_in_out = explode('-',$check_in_out);
                    $check_in = $arr_check_in_out[0].'-'.$arr_check_in_out[1].'-'.$arr_check_in_out[2];
                    $check_out = $arr_check_in_out[3].'-'.$arr_check_in_out[4].'-'.$arr_check_in_out[5];
    
                    $startdate = date_create($check_in);
                    $enddate = date_create($check_out);
                    $no_of_nights = date_diff($startdate,$enddate)->format('%a');
    
                    $html .= '<tr>';
                    $html .= '<td>'.$booking_id.'</a></td>';
                    if($be_booking->mobile == '+919078885520')
                        $html .= '<td> CMO Guest </td>';
                    else
                        $html .= '<td>'.$be_booking->first_name.' '.$be_booking->last_name.' ('.$be_booking->mobile.') ('.$be_booking->email_id.')</td>';
                    $html .= '<td>'.$be_booking->hotel_name.'</td>';
                    $html .= '<td>'.$be_booking->room_type.'</td>';
                    $html .= '<td>'.$check_in.'</td>';
                    $html .= '<td>'.$check_out.'</td>';
                    $html .= '<td>'.$no_of_nights.'</td>';
                    $html .= '<td>'.$occupancy.'</td>';
                    $html .= '<td>'.($be_booking->paid_amount - $be_booking->tax_amount).'</td>';
                    $html .= '<td>'.$be_booking->tax_amount.'</td>';
                    $html .= '<td>'.$be_booking->paid_amount.'</td>';
                    if(isset($invoice_sl_no[$be_booking->invoice_id]))
                        $html .= '<td>'.$be_booking->booking_source.'/<a href="https://be.bookingjini.com/viewinvoice/'.$be_booking->invoice_id.'" target="_blank">'.$invoice_sl_no[$be_booking->invoice_id].'</a></td>';
                    else
                        $html .= '<td>'.$be_booking->booking_source.'</td>';
                    if($hotel->hotel_id == 2881)
                    {
                        if($be_booking->paid_service_id != '')
                            $html .= '<td>YES</td>';
                        else
                            $html .= '<td>&nbsp;</td>';
                    }
                    
                    $html .= '</tr>';
                    $unit_total_booking ++;
                    $unit_total_occupancy += $occupancy;

                }
            }
            foreach($ota_bookings as $ota_booking)
            {
                $occupancy = 0;
                if($ota_booking->hotel_id == $hotel->hotel_id)
                {
                    $arr_adult = explode(',',$ota_booking->no_of_adult);
                    foreach($arr_adult as $no_of_adult)
                    {
                        $occupancy += (int)$no_of_adult;
                    }
                    $arr_child = explode(',',$ota_booking->no_of_child);
                    foreach($arr_child as $no_of_child)
                    {
                        $occupancy += (int)$no_of_child;
                    }
                    
                    $booking_id = $ota_booking->unique_id;
                
                    $check_in = $ota_booking->checkin_at;
                    $check_out = $ota_booking->checkout_at;
    
                    $startdate = date_create($check_in);
                    $enddate = date_create($check_out);
                    $no_of_nights = date_diff($startdate,$enddate)->format('%a');
    
                    $html .= '<tr>';
                    $html .= '<td>'.$booking_id.'</td>';
                    $html .= '<td>'.$ota_booking->customer_details.'</td>';
                    $html .= '<td>'.$ota_booking->hotel_name.'</td>';
                    $html .= '<td>'.$ota_booking->rooms_qty.' '.$ota_booking->ota_room_type_name.'</td>';
                    $html .= '<td>'.$check_in.'</td>';
                    $html .= '<td>'.$check_out.'</td>';
                    $html .= '<td>'.$no_of_nights.'</td>';
                    $html .= '<td>'.$occupancy.'</td>';
                    $html .= '<td>'.($ota_booking->amount - $ota_booking->tax_amount).'</td>';
                    $html .= '<td>'.$ota_booking->tax_amount.'</td>';
                    $html .= '<td>'.$ota_booking->amount.'</td>';
                    $html .= '<td>'.$ota_booking->channel_name.'</td>';
                    if($hotel->hotel_id == 2881)
                    {
                        $html .= '<td>&nbsp;</td>';
                    }
                    $html .= '</tr>';
                    $unit_total_booking ++;
                    $unit_total_occupancy += $occupancy;
                }
            }
            $html .= '<tr><td colspan="7" style="text-align:center">Total Bookings '.$unit_total_booking.' | '.$str_room_details.'</td><td>'.$unit_total_occupancy.'</td><td colspan="5">&nbsp;</td></tr>';
            $html .= '</table>';
        }
        echo $html;
    }
    //============================================================================================================================
    public function getOTDCBookingsInvoices($unit_code)
    {
        if($unit_code == 'konark')
        {
            $hotel_id = 2881;
            $hotel_code = '01';
            $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_konark WHERE status = 1 ORDER BY id asc ";
        }
        else if($unit_code == 'bhitarkanika')
        {
            $hotel_id = 2065;
            $hotel_code = '02';
            $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_bhitarkanika WHERE status = 1 ORDER BY id asc ";
        }
        else if($unit_code == 'satkosia')
        {
            $hotel_id = 2882;
            $hotel_code = '03';
            $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_satkosia WHERE status = 1 ORDER BY id asc ";
        }
        else if($unit_code == 'hirakud')
        {
            $hotel_id = 2884;
            $hotel_code = '04';
            $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_hirakud WHERE status = 1 ORDER BY id asc ";
        }
        else if($unit_code == 'daringbadi')
        {
            $hotel_id = 2883;
            $hotel_code = '05';
            $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_daringbadi WHERE status = 1 ORDER BY id asc ";
        }
        else if($unit_code == 'sonapur')
        {
            $hotel_id = 2885;
            $hotel_code = '06';
            $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_sonapur WHERE status = 1 ORDER BY id asc ";
        }
        else if($unit_code == 'koraput')
        {
            $hotel_id = 2886;
            $hotel_code = '07';
            $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_koraput WHERE status = 1 ORDER BY id asc ";
        }
        
        
        $be_invoices = DB::select("$invoice_sql");
        $be_sql = "
            select A.hotel_id, A.hotel_name, A.invoice_id, A.booking_date, 
            A.room_type, A.check_in_out, A.user_id, A.paid_amount, A.booking_source, B.first_name, B.last_name, A.tax_amount, 
            A.paid_service_id, B.mobile, A.extra_details, A.booking_status, B.GSTIN, B.company_name
            FROM
            booking_engine.invoice_table A
            inner join kernel.user_table B ON A.user_id = B.user_id
            inner join (SELECT DISTINCT invoice_id, check_in, check_out FROM booking_engine.hotel_booking where hotel_id IN (SELECT hotel_id FROM kernel.hotels_table WHERE hotel_name LIKE 'Eco Retreat %') ) C 
            ON A.invoice_id = C.invoice_id
            where A.hotel_id = $hotel_id
            AND A.booking_status IN (1,3)
            order by A.invoice_id ASC
        ";
        $be_bookings = DB::select("$be_sql");
        $html = '';
        $html .= '<h4>'.$unit_code.'</h4>';
        $html .= '<table border="1" style="width:99%">';
        $html .= '<tr>';
        $html .= '<th>Invoice Sl No</th>';
        $html .= '<th>Booking ID</th>';
        $html .= '<th>Unit</th>';
        $html .= '<th>Guest</th>';
        $html .= '<th>GST</th>';
        $html .= '<th>Company</th>';
        $html .= '<th>Room Details</th>';
        $html .= '<th style="width:10%">Check In</th>';
        $html .= '<th style="width:10%">Check Out</th>';
        $html .= '<th>Nights</th>';
        $html .= '<th>Occupancy</th>';
        $html .= '<th>Amount Before Tax</th>';
        $html .= '<th>GST Amount</th>';
        $html .= '<th>Total Amount</th>';
        $html .= '<th>Booking Status</th>';
        $html .= '<th>Booking Source</th>';
        $html .= '</tr>';
        foreach($be_invoices as $be_invoice)
        {

            foreach($be_bookings as $be_booking)
            {
                if($be_booking->invoice_id == $be_invoice->invoice_id)
                {
                    $inv_sl_no = $hotel_code.str_pad($be_invoice->id,4,'0',STR_PAD_LEFT);
                    if($be_booking->booking_status == 1)
                        $booking_status = 'Confirmed';
                    else if($be_booking->booking_status == 3)
                        $booking_status = 'Cancelled';
                    $extra_details = $be_booking->extra_details;
                    $extras = json_decode($extra_details);
                    $occupancy = 0;
                    foreach($extras as $extra)
                    {
                        foreach($extra as $room_type=>$p)
                        {
                            $occupancy+= $p[0]+$p[1];
                        }
                        
                    }
                    $booking_id = date('dmy',strtotime($be_booking->booking_date)).$be_booking->invoice_id;
                    $check_in_out = str_replace('[','',$be_booking->check_in_out);
                    $check_in_out = str_replace(']','',$check_in_out);
                    $arr_check_in_out = explode('-',$check_in_out);
                    $check_in = $arr_check_in_out[0].'-'.$arr_check_in_out[1].'-'.$arr_check_in_out[2];
                    $check_out = $arr_check_in_out[3].'-'.$arr_check_in_out[4].'-'.$arr_check_in_out[5];
    
                    $startdate = date_create($check_in);
                    $enddate = date_create($check_out);
                    $no_of_nights = date_diff($startdate,$enddate)->format('%a');
    
                    $html .= '<tr>';
                    $html .= '<td><a href="https://be.bookingjini.com/viewinvoice/'.$be_booking->invoice_id.'" target="_blank">'.$inv_sl_no.'</a></td>';
                    $html .= '<td>'.$booking_id.'</td>';
                    $html .= '<td>'.$be_booking->hotel_name.'</td>';
                    if($be_booking->mobile == '+919078885520')
                        $html .= '<td> CMO Guest </td>';
                    else
                        $html .= '<td>'.$be_booking->first_name.' '.$be_booking->last_name.' ('.$be_booking->mobile.')</td>';
                    
                    $html .= '<td>'.$be_booking->GSTIN.'</td>';
                    $html .= '<td>'.$be_booking->company_name.'</td>';
                    $html .= '<td>'.$be_booking->room_type.'</td>';
                    $html .= '<td>'.$check_in.'</td>';
                    $html .= '<td>'.$check_out.'</td>';
                    $html .= '<td>'.$no_of_nights.'</td>';
                    $html .= '<td>'.$occupancy.'</td>';
                    $html .= '<td>'.($be_booking->paid_amount - $be_booking->tax_amount).'</td>';
                    $html .= '<td>'.$be_booking->tax_amount.'</td>';
                    $html .= '<td>'.$be_booking->paid_amount.'</td>';
                    $html .= '<td>'.$be_booking->booking_source.'</td>';
                    $html .= '<td>'.$booking_status.'</td>';
                    $html .= '</tr>';

                }
            }
        }
       $html .= '</table>';
        
        
        echo $html;

    }
    //============================================================================================================================
    public function getOTDCCRSBookings()
    {
        $html = '';
      
        $hotel_sql = "
            select T.hotel_id, T.hotel_name, sum(T.total_rooms) AS total_rooms
            from (
            select A.hotel_id, A.hotel_name, B.room_type_id, B.room_type, B.total_rooms
            from kernel.hotels_table A
            inner join kernel.room_type_table B ON A.hotel_id = B.hotel_id
            where A.hotel_name like 'Eco Retreat%'
            order by A.hotel_name, B.room_type_id) T
            group by T.hotel_id, T.hotel_name
            order by T.hotel_name
        ";
        $hotels = DB::select("$hotel_sql");

        $be_sql = "
            select A.hotel_id, A.hotel_name, A.invoice_id, A.booking_date, 
            A.room_type, A.check_in_out, A.user_id, A.paid_amount, A.booking_source, B.first_name, B.last_name
            from
            booking_engine.invoice_table A
            inner join kernel.user_table B ON A.user_id = B.user_id
            where A.hotel_id in (
            select hotel_id from kernel.hotels_table where hotel_name like 'Eco Retreat%')
            and A.booking_status = 1
            and A.booking_source = 'CRS'
            order by A.invoice_id desc
        ";
        $be_bookings = DB::select("$be_sql");


        foreach($hotels as $hotel)
        {
            $html .= '<h4>'.$hotel->hotel_name.'</h4>';
            $hotel_id = $hotel->hotel_id;

            if($hotel_id == 2881)
            {
                $hotel_code = '01';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_konark ORDER BY id asc ";
            }
            else if($hotel_id == 2065)
            {
                $hotel_code = '02';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_bhitarkanika ORDER BY id asc ";
            }
            else if($hotel_id == 2882)
            {
                $hotel_code = '03';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_satkosia ORDER BY id asc ";
            }
            else if($hotel_id == 2884)
            {
                $hotel_code = '04';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_hirakud ORDER BY id asc ";
            }
            else if($hotel_id == 2883)
            {
                $hotel_code = '05';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_daringbadi ORDER BY id asc ";
            }
            else if($hotel_id == 2885)
            {
                $hotel_code = '06';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_sonapur ORDER BY id asc ";
            }
            else if($hotel_id == 2886)
            {
                $hotel_code = '07';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_koraput ORDER BY id asc ";
            }
            $be_invoices = DB::select("$invoice_sql");
            
            foreach($be_invoices as $be_invoice)
            {
                $str_invoice_id = str_pad($be_invoice->id,4,'0');
                $beinvoices[$be_invoice->invoice_id] = $hotel_code.$str_invoice_id;
            }
            
            $html .= '<table border="1">';
            $html .= '<tr>';
            $html .= '<th>Booking ID</th>';
            $html .= '<th>Booking Date</th>';
            $html .= '<th>Guest</th>';
            $html .= '<th>Unit</th>';
            $html .= '<th>Room Details</th>';
            $html .= '<th>Check In</th>';
            $html .= '<th>Check Out</th>';
            $html .= '<th>Nights</th>';
            $html .= '<th>Total Amount</th>';
            $html .= '<th>Booking Source</th>';
            $html .= '<th>Invoice Slno</th>';
            $html .= '</tr>';
            foreach($be_bookings as $be_booking)
            {
                if($be_booking->hotel_id == $hotel_id)
                {
                    $booking_id = date('dmy',strtotime($be_booking->booking_date)).$be_booking->invoice_id;
                    $check_in_out = str_replace('[','',$be_booking->check_in_out);
                    $check_in_out = str_replace(']','',$check_in_out);
                    $arr_check_in_out = explode('-',$check_in_out);
                    $check_in = $arr_check_in_out[0].'-'.$arr_check_in_out[1].'-'.$arr_check_in_out[2];
                    $check_out = $arr_check_in_out[3].'-'.$arr_check_in_out[4].'-'.$arr_check_in_out[5];

                    $startdate = date_create($check_in);
                    $enddate = date_create($check_out);
                    $no_of_nights = date_diff($startdate,$enddate)->format('%a');

                    $html .= '<tr>';
                    $html .= '<td>'.$booking_id.'</td>';
                    $html .= '<td>'.date('d-m-Y',strtotime($be_booking->booking_date)).'</td>';
                    $html .= '<td>'.$be_booking->first_name.' '.$be_booking->last_name.'</td>';
                    $html .= '<td>'.$be_booking->hotel_name.'</td>';
                    $html .= '<td>'.$be_booking->room_type.'</td>';
                    $html .= '<td>'.$check_in.'</td>';
                    $html .= '<td>'.$check_out.'</td>';
                    $html .= '<td>'.$no_of_nights.'</td>';
                    $html .= '<td>'.$be_booking->paid_amount.'</td>';
                    $html .= '<td>'.$be_booking->booking_source.'</td>';
                    if(isset($beinvoices[$be_booking->invoice_id]))
                        $html .= '<td>'.$beinvoices[$be_booking->invoice_id].'</td>';
                    else
                        $html .= '<td>&nbsp;</td>';
                    $html .= '</tr>';
                }

            }
            $html .= '</table>';

        }


    


    
        
        
        echo $html;

    }
    //============================================================================================================================
    public function getOTABookingDetails()
    {
        $html = '';
        $hotel_sql = "
            select T.hotel_id, T.hotel_name, sum(T.total_rooms) AS total_rooms
            from (
            select A.hotel_id, A.hotel_name, B.room_type_id, B.room_type, B.total_rooms
            from kernel.hotels_table A
            inner join kernel.room_type_table B ON A.hotel_id = B.hotel_id
            where A.hotel_name like 'Eco Retreat%'
            order by A.hotel_name, B.room_type_id) T
            group by T.hotel_id, T.hotel_name
            order by T.hotel_name
        ";
        $hotels = DB::select("$hotel_sql");

        $ota_sql = "
            select A.unique_id, B.hotel_id, B.hotel_name, A.customer_details,
            A.rooms_qty, A.checkout_at, A.checkin_at,A.room_type, C.ota_room_type_name,
            A.channel_name, A.amount, A.tax_amount, A.booking_date, A.booking_status, A.confirm_status, A.cancel_status
            from
            cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            left join cmlive.cm_ota_room_type_synchronize C ON A.room_type = C.ota_room_type AND A.hotel_id = C.hotel_id
            where  
            B.hotel_name like 'Eco Retreat%'
            ORDER BY A.booking_date DESC";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");


        foreach($hotels as $hotel)
        {
            $html .= '<h4>'.$hotel->hotel_name.'</h4>';
            $hotel_id = $hotel->hotel_id;
           
            $html .= '<table border="1" width="100%">';
            $html .= '<tr>';
            $html .= '<th>Booking ID</th>';
            $html .= '<th>Booking Date</th>';
            $html .= '<th>Guest</th>';
            $html .= '<th>Unit</th>';
            $html .= '<th>Room Details</th>';
            $html .= '<th>Check In</th>';
            $html .= '<th>Check Out</th>';
            $html .= '<th>Nights</th>';
            $html .= '<th>Total Amount</th>';
            $html .= '<th>Booking Source</th>';
            $html .= '<th>Booking Status</th>';
            $html .= '</tr>';
            foreach($ota_bookings as $ota_booking)
            {
                if($ota_booking->hotel_id == $hotel_id)
                {
                    if($ota_booking->cancel_status == 1)
                    {
                        $booking_status = 'Cancelled';
                    }
                    else if($ota_booking->confirm_status == 1 && $ota_booking->cancel_status == 0)
                    {
                        $booking_status = 'Confirmed';
                    }

                    $booking_id = date('dmy',strtotime($ota_booking->booking_date));
                    $check_in = date('d-m-Y',strtotime($ota_booking->checkin_at));
                    $check_out = date('d-m-Y',strtotime($ota_booking->checkout_at));

                    $startdate = date_create($check_in);
                    $enddate = date_create($check_out);
                    $no_of_nights = date_diff($startdate,$enddate)->format('%a');

                    $html .= '<tr>';
                    $html .= '<td>'.$booking_id.'</td>';
                    $html .= '<td>'.date('d-m-Y',strtotime($ota_booking->booking_date)).'</td>';
                    $html .= '<td>'.$ota_booking->customer_details.'</td>';
                    $html .= '<td>'.$ota_booking->hotel_name.'</td>';
                    $html .= '<td>'.$ota_booking->rooms_qty.' '.$ota_booking->ota_room_type_name.'</td>';
                    $html .= '<td>'.$check_in.'</td>';
                    $html .= '<td>'.$check_out.'</td>';
                    $html .= '<td>'.$no_of_nights.'</td>';
                    $html .= '<td>'.$ota_booking->amount.'</td>';
                    $html .= '<td>'.$ota_booking->channel_name.'</td>';
                    $html .= '<td>'.$booking_status.'</td>';
                    $html .= '</tr>';
                }

            }
            $html .= '</table>';

        }
        echo $html;
    }
    //============================================================================================================================
    public function getWebsiteCancelledBookings()
    {
        $html = '';
      
        $hotel_sql = "
            select T.hotel_id, T.hotel_name, sum(T.total_rooms) AS total_rooms
            from (
            select A.hotel_id, A.hotel_name, B.room_type_id, B.room_type, B.total_rooms
            from kernel.hotels_table A
            inner join kernel.room_type_table B ON A.hotel_id = B.hotel_id
            where A.hotel_name like 'Eco Retreat%'
            order by A.hotel_name, B.room_type_id) T
            group by T.hotel_id, T.hotel_name
            order by T.hotel_name
        ";
        $hotels = DB::select("$hotel_sql");

        $be_sql = "
            select A.hotel_id, A.hotel_name, A.invoice_id, A.booking_date, 
            A.room_type, A.check_in_out, A.user_id, A.paid_amount, A.booking_source, B.first_name, B.last_name, A.updated_at
            from
            booking_engine.invoice_table A
            inner join kernel.user_table B ON A.user_id = B.user_id
            where A.hotel_id in (
            select hotel_id from kernel.hotels_table where hotel_name like 'Eco Retreat%')
            and A.booking_status = 3
            and A.booking_source = 'WEBSITE'
            order by A.invoice_id desc
        ";
        $be_bookings = DB::select("$be_sql");


        foreach($hotels as $hotel)
        {
            $html .= '<h4>'.$hotel->hotel_name.'</h4>';
            $hotel_id = $hotel->hotel_id;

            if($hotel_id == 2881)
            {
                $hotel_code = '01';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_konark ORDER BY id asc ";
            }
            else if($hotel_id == 2065)
            {
                $hotel_code = '02';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_bhitarkanika ORDER BY id asc ";
            }
            else if($hotel_id == 2882)
            {
                $hotel_code = '03';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_satkosia ORDER BY id asc ";
            }
            else if($hotel_id == 2884)
            {
                $hotel_code = '04';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_hirakud ORDER BY id asc ";
            }
            else if($hotel_id == 2883)
            {
                $hotel_code = '05';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_daringbadi ORDER BY id asc ";
            }
            else if($hotel_id == 2885)
            {
                $hotel_code = '06';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_sonapur ORDER BY id asc ";
            }
            else if($hotel_id == 2886)
            {
                $hotel_code = '07';
                $invoice_sql = "SELECT id, invoice_id, transaction_id from booking_engine.eco_retreat_koraput ORDER BY id asc ";
            }
            $be_invoices = DB::select("$invoice_sql");
            
            foreach($be_invoices as $be_invoice)
            {
                $str_invoice_id = str_pad($be_invoice->id,4,'0');
                $beinvoices[$be_invoice->invoice_id] = $hotel_code.$str_invoice_id;
            }
            
            $html .= '<table border="1">';
            $html .= '<tr>';
            $html .= '<th>Booking ID</th>';
            $html .= '<th>Booking Date</th>';
            $html .= '<th>Cancelled Date</th>';
            $html .= '<th>Guest</th>';
            $html .= '<th>Unit</th>';
            $html .= '<th>Room Details</th>';
            $html .= '<th>Check In</th>';
            $html .= '<th>Check Out</th>';
            $html .= '<th>Nights</th>';
            $html .= '<th>Total Amount</th>';
            $html .= '<th>Booking Source</th>';
            $html .= '<th>Invoice Slno</th>';
            $html .= '</tr>';
            foreach($be_bookings as $be_booking)
            {
                if($be_booking->hotel_id == $hotel_id)
                {
                    $booking_id = date('dmy',strtotime($be_booking->booking_date)).$be_booking->invoice_id;
                    $check_in_out = str_replace('[','',$be_booking->check_in_out);
                    $check_in_out = str_replace(']','',$check_in_out);
                    $arr_check_in_out = explode('-',$check_in_out);
                    $check_in = $arr_check_in_out[0].'-'.$arr_check_in_out[1].'-'.$arr_check_in_out[2];
                    $check_out = $arr_check_in_out[3].'-'.$arr_check_in_out[4].'-'.$arr_check_in_out[5];

                    $startdate = date_create($check_in);
                    $enddate = date_create($check_out);
                    $no_of_nights = date_diff($startdate,$enddate)->format('%a');

                    $html .= '<tr>';
                    $html .= '<td>'.$booking_id.'</td>';
                    $html .= '<td>'.date('d-m-Y',strtotime($be_booking->booking_date)).'</td>';
                    $html .= '<td>'.date('d-m-Y',strtotime($be_booking->updated_at)).'</td>';
                    $html .= '<td>'.$be_booking->first_name.' '.$be_booking->last_name.'</td>';
                    $html .= '<td>'.$be_booking->hotel_name.'</td>';
                    $html .= '<td>'.$be_booking->room_type.'</td>';
                    $html .= '<td>'.$check_in.'</td>';
                    $html .= '<td>'.$check_out.'</td>';
                    $html .= '<td>'.$no_of_nights.'</td>';
                    $html .= '<td>'.$be_booking->paid_amount.'</td>';
                    $html .= '<td>'.$be_booking->booking_source.'</td>';
                    if(isset($beinvoices[$be_booking->invoice_id]))
                        $html .= '<td>'.$beinvoices[$be_booking->invoice_id].'</td>';
                    else
                        $html .= '<td>&nbsp;</td>';
                    $html .= '</tr>';
                }

            }
            $html .= '</table>';

        }
        
        echo $html;

    }
    //============================================================================================================================
    public function getOTDCBookingAmountByCheckinDate($date_from, $date_to)
    {
        $html = '';
      
        

        $html .= '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>Unit</th>';
        $html .= '<th>Direct Booking</th>';
        $html .= '<th>OTA Booking</th>';
        $html .= '<th>Total Amount</th>';
        $html .= '</tr>';
        //KONARK
        $be_sql = "
            select B.hotel_name, sum(A.total_amount - A.tax_amount) AS be_amount
            from 
            booking_engine.invoice_table A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            inner join booking_engine.eco_retreat_konark F ON A.invoice_id = F.invoice_id AND F.status = 1
            where A.booking_status in (1)
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) >= '$date_from'
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) <= '$date_to'
            and B.hotel_name = 'Eco Retreat Konark'
        ";
        $be_bookings = DB::select("$be_sql");
        $be_amount = (int)$be_bookings[0]->be_amount;

        $ota_sql = "
            select B.hotel_name, sum(A.amount - A.tax_amount) as ota_amount
            from cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            where B.hotel_name  = 'Eco retreat Konark'
            and A.checkin_at >= '$date_from'
            and A.checkin_at <= '$date_to'
            and A.confirm_status = 1
            and A.cancel_status = 0
        ";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
        $ota_amount = (int)$ota_bookings[0]->ota_amount;
        $html .= '<tr>';
        $html .= '<td>Konark</td>';
        $html .= '<td>'.$be_amount.'</td>';
        $html .= '<td>'.$ota_amount.'</td>';
        $html .= '<td>'. ($be_amount+$ota_amount) .'</td>';
        $html .= '</tr>';

        //BHITARKANIKA
        $be_sql = "
            select B.hotel_name, sum(A.total_amount - A.tax_amount) AS be_amount
            from 
            booking_engine.invoice_table A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            inner join booking_engine.eco_retreat_bhitarkanika F ON A.invoice_id = F.invoice_id AND F.status = 1
            where A.booking_status in (1)
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) >= '$date_from'
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) <= '$date_to'
            and B.hotel_name like 'Eco Retreat Bhitarkanika'
        ";
        $be_bookings = DB::select("$be_sql");
        $be_amount = (int)$be_bookings[0]->be_amount;
        $ota_sql = "
            select B.hotel_name, sum(A.amount - A.tax_amount) as ota_amount
            from cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            where B.hotel_name  = 'Eco retreat bhitarkanika'
            and A.checkin_at >= '$date_from'
            and A.checkin_at <= '$date_to'
            and A.confirm_status = 1
            and A.cancel_status = 0
        ";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
        $ota_amount = (int)$ota_bookings[0]->ota_amount;
        $html .= '<tr>';
        $html .= '<td>Bhitarkanika</td>';
        $html .= '<td>'.$be_amount.'</td>';
        $html .= '<td>'.$ota_amount.'</td>';
        $html .= '<td>'. ($be_amount+$ota_amount) .'</td>';
        $html .= '</tr>';

        //SATKOSIA
        $be_sql = "
            select B.hotel_name, sum(A.total_amount - A.tax_amount) AS be_amount
            from 
            booking_engine.invoice_table A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            inner join booking_engine.eco_retreat_satkosia F ON A.invoice_id = F.invoice_id AND F.status = 1
            where A.booking_status in (1)
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) >= '$date_from'
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) <= '$date_to'
            and B.hotel_name like 'Eco Retreat Satkosia'
        ";
        $be_bookings = DB::select("$be_sql");
        $be_amount = (int)$be_bookings[0]->be_amount;
        $ota_sql = "
            select B.hotel_name, sum(A.amount - A.tax_amount) as ota_amount
            from cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            where B.hotel_name  = 'Eco retreat satkosia'
            and A.checkin_at >= '$date_from'
            and A.checkin_at <= '$date_to'
            and A.confirm_status = 1
            and A.cancel_status = 0
        ";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
        $ota_amount = (int)$ota_bookings[0]->ota_amount;
        $html .= '<tr>';
        $html .= '<td>Satkosia</td>';
        $html .= '<td>'.$be_amount.'</td>';
        $html .= '<td>'.$ota_amount.'</td>';
        $html .= '<td>'. ($be_amount+$ota_amount) .'</td>';
        $html .= '</tr>';

        //HIRAKUD
        $be_sql = "
            select B.hotel_name, sum(A.total_amount - A.tax_amount) AS be_amount
            from 
            booking_engine.invoice_table A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            inner join booking_engine.eco_retreat_hirakud F ON A.invoice_id = F.invoice_id AND F.status = 1
            where A.booking_status in (1)
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) >= '$date_from'
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) <= '$date_to'
            and B.hotel_name like 'Eco Retreat hirakud'
        ";
        $be_bookings = DB::select("$be_sql");
        $be_amount = (int)$be_bookings[0]->be_amount;
        $ota_sql = "
            select B.hotel_name, sum(A.amount - A.tax_amount) as ota_amount
            from cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            where B.hotel_name  = 'Eco retreat hirakud'
            and A.checkin_at >= '$date_from'
            and A.checkin_at <= '$date_to'
            and A.confirm_status = 1
            and A.cancel_status = 0
        ";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
        $ota_amount = (int)$ota_bookings[0]->ota_amount;
        $html .= '<tr>';
        $html .= '<td>Hirakud</td>';
        $html .= '<td>'.$be_amount.'</td>';
        $html .= '<td>'.$ota_amount.'</td>';
        $html .= '<td>'. ($be_amount+$ota_amount) .'</td>';
        $html .= '</tr>';

        //Daringbadi
        $be_sql = "
            select B.hotel_name, sum(A.total_amount - A.tax_amount) AS be_amount
            from 
            booking_engine.invoice_table A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            inner join booking_engine.eco_retreat_daringbadi F ON A.invoice_id = F.invoice_id AND F.status = 1
            where A.booking_status in (1)
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) >= '$date_from'
            and SUBSTRING_INDEX(REPLACE(left(A.check_in_out,11),'[',''),'-',3) <= '$date_to'
            and B.hotel_name like 'Eco Retreat daringbadi'
        ";
        $be_bookings = DB::select("$be_sql");
        $be_amount = (int)$be_bookings[0]->be_amount;
        $ota_sql = "
            select B.hotel_name, sum(A.amount - A.tax_amount) as ota_amount
            from cmlive.cm_ota_booking A
            inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
            where B.hotel_name  = 'Eco retreat daringbadi'
            and A.checkin_at >= '$date_from'
            and A.checkin_at <= '$date_to'
            and A.confirm_status = 1
            and A.cancel_status = 0
        ";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");
        $ota_amount = (int)$ota_bookings[0]->ota_amount;
        $html .= '<tr>';
        $html .= '<td>Daringbadi</td>';
        $html .= '<td>'.$be_amount.'</td>';
        $html .= '<td>'.$ota_amount.'</td>';
        $html .= '<td>'. ($be_amount+$ota_amount) .'</td>';
        $html .= '</tr>';

        
        $html .= '</table>';

        
        
        echo $html;

    }
    //================================================================================================================
    public function cancellationPercentage()
    {

        $start_date = '2021-01-01';
        $end_date = '2021-12-31';
        //BE BOOKING
        $be_sql = "SELECT COUNT(invoice_id) AS booking_count,sum(total_amount) AS booking_amount 
            FROM invoice_table WHERE booking_status IN (1,3)
            AND booking_date BETWEEN '$start_date' AND '$end_date'";
        $be_total_bookings = DB::select($be_sql);
        $be_total_booking_count = $be_total_bookings[0]->booking_count;
        $be_total_booking_amount = $be_total_bookings[0]->booking_amount;

        $be_sql = "SELECT COUNT(invoice_id) AS booking_count,sum(total_amount) AS booking_amount 
            FROM invoice_table WHERE booking_status IN (3)
            AND booking_date BETWEEN '$start_date' AND '$end_date'";
        $be_cancelled_bookings = DB::select($be_sql);
        $be_cancelled_booking_count = $be_cancelled_bookings[0]->booking_count;
        $be_cancelled_booking_amount = $be_cancelled_bookings[0]->booking_amount;


        //OTA BOOKING
        $ota_sql = "SELECT COUNT(unique_id) AS booking_count,sum(amount) AS booking_amount 
            FROM cmlive.cm_ota_booking WHERE confirm_status = 1
            AND booking_date BETWEEN '$start_date' AND '$end_date'";
        $ota_total_bookings = DB::connection('bookingjini_cm')->select($ota_sql);
        $ota_total_booking_count = $ota_total_bookings[0]->booking_count;
        $ota_total_booking_amount = $ota_total_bookings[0]->booking_amount;

        $ota_sql = "SELECT COUNT(unique_id) AS booking_count,sum(amount) AS booking_amount 
            FROM cmlive.cm_ota_booking WHERE cancel_status = 1
            AND booking_date BETWEEN '$start_date' AND '$end_date'";
        $ota_cancelled_bookings = DB::connection('bookingjini_cm')->select($ota_sql);
        $ota_cancelled_booking_count = $ota_cancelled_bookings[0]->booking_count;
        $ota_cancelled_booking_amount = $ota_cancelled_bookings[0]->booking_amount;
        
        $total_booking_count = $be_total_booking_count + $ota_total_booking_count;
        $cancelled_booking_count = $be_cancelled_booking_count + $ota_cancelled_booking_count;
        
        $html = "<h2>Cancellation percentage between $start_date and $end_date </h2>";
        $html .= '<table border="1">';
        $html .= '<tr>';
        $html .= '<th>&nbsp;</th>';
        $html .= '<th>BOOKINGS</th>';
        $html .= '<th>CANCELLED</th>';
        $html .= '<th>CANCELLATION PERCENTAGE</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>DIRECT</th>';
        $html .= '<th>'.$be_total_booking_count.'</th>';
        $html .= '<th>'.$be_cancelled_booking_count.'</th>';
        $html .= '<th>'.round($be_cancelled_booking_count/$be_total_booking_count*100,2).'</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>OTA</th>';
        $html .= '<th>'.$ota_total_booking_count.'</th>';
        $html .= '<th>'.$ota_cancelled_booking_count.'</th>';
        $html .= '<th>'.round($ota_cancelled_booking_count/$ota_total_booking_count*100,2).'</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>TOTAL</th>';
        $html .= '<th>'.$total_booking_count.'</th>';
        $html .= '<th>'.$cancelled_booking_count.'</th>';
        $html .= '<th>'.round($cancelled_booking_count/$total_booking_count*100,2).'</th>';
        $html .= '</tr>';
        $html .= '</table>';
        
        echo $html;
    }
    //================================================================================================================
    public function getUserBookings(Request $request)
    {
        
        $mobile_no = $request['mobile_no'];
        $type = $request['type']; //upcoming/completed/cancelled
        $group_id = $request['company_id']; 
        $be_bookings = Invoice::
            join('kernel.hotels_table','kernel.hotels_table.hotel_id','=','invoice_table.hotel_id')
            ->join('hotel_booking','hotel_booking.invoice_id','=','invoice_table.invoice_id')
            ->join('kernel.room_type_table','hotel_booking.room_type_id','=','kernel.room_type_table.room_type_id')
            ->join('kernel.user_table','kernel.user_table.user_id','=','invoice_table.user_id')
            ->join('kernel.city_table','kernel.city_table.city_id','=','hotels_table.city_id')
            ->where('user_table.mobile',$mobile_no)
            ->where('invoice_table.booking_status',1)
            ->where('hotels_table.company_id',$group_id)
            ->select('invoice_table.invoice_id','kernel.hotels_table.hotel_id','kernel.hotels_table.hotel_name',
            'hotel_booking.booking_date','hotel_booking.check_in','hotel_booking.check_out',
            'hotel_booking.room_type_id','room_type_table.room_type','hotel_booking.rooms',
            'total_amount','paid_amount','invoice_table.tax_amount','user_table.first_name','user_table.last_name',
            'user_table.email_id','user_table.mobile','invoice_table.booking_status','extra_details','city_table.city_name'
            )
            ->get();
        if($type == 'UPCOMING')
        {
            $today = date('Y-m-d H:i:s');
            $upcoming_bookings = array();
            foreach($be_bookings as $be_booking)
            {
                if($be_booking['check_in'] >= $today)
                {
                    $upcoming_booking['invoice_id'] = $be_booking['invoice_id'];
                    $upcoming_booking['hotel_id'] = $be_booking['hotel_id'];
                    $upcoming_booking['hotel_name'] = $be_booking['hotel_name'];
                    $upcoming_booking['booking_date'] = $be_booking['booking_date'];
                    $upcoming_booking['check_in'] = $be_booking['check_in'];
                    $upcoming_booking['check_out'] = $be_booking['check_out'];
                    $startdate = date_create($be_booking['check_in']);
                    $enddate = date_create($be_booking['check_out']);
                    $no_of_nights = date_diff($startdate,$enddate)->format('%a');
                    $upcoming_booking['nights'] = $no_of_nights;
                    $upcoming_booking['city_name'] = $be_booking['city_name'];
                    $upcoming_bookings[] = $upcoming_booking;
                }
                

            }
            $msg = array('status'=>1,'message'=>'Booking fetched','bookings'=>$upcoming_bookings);
            return response()->json($msg);
        }
        
    }
    //================================================================================================================
    public function bookingsByFinYear($duration)
    {
        $today_date = date('Y-m-d');
        //$today_date = '2021-03-06';
        
        $arr_hotel_ids = array();
        $arr_hotel_names = [2881=>'Konark',2065=>'Bhitarkanika',2882=>'Satkosia',2883=>'Daringbadi',2884=>'Hirakud',2885=>'Pati Sonapur',2886=>'Koraput'];
        $all_hotels = CompanyDetails::
            join('hotels_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->where('hotels_table.status', 1)
            ->whereNotIn('hotels_table.hotel_id',array(2881,2065,2882,2883,2884,2885,2886,1698))
            ->select('hotels_table.hotel_id', 'hotels_table.company_id')
            ->get();
        
        $all_hotels = CompanyDetails::
            join('hotels_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->where('hotels_table.status', 1)
            ->select('hotels_table.hotel_id', 'hotels_table.company_id')
            ->get();
        
        foreach($all_hotels as $all_hotel)
        {
            $arr_hotel_ids[] = $all_hotel['hotel_id'];
        }
        

        $be_cancelled_booking_count = 0;
        $be_confirmed_booking_count = 0;
        $be_checkin_count = 0;
        $be_checkout_count = 0;
        $be_revenue = 0;
        $be_occupied_rooms = 0;

        $ota_cancelled_booking_count = 0;
        $ota_booking_count = 0;
        $ota_checkin_count = 0;
        $ota_checkout_count = 0;
        $ota_revenue = 0;
        $ota_occupied_rooms = 0;

        $total_available_rooms = 0;

        $from_date = '';
        $to_date = '';
        $nights = 0;
        $be_occupied_nights = 0;
        $ota_occupied_nights = 0;
        if($duration == 'TODAY')
        {
            $from_date = $today_date;
            $to_date = $today_date;
            $nights = 1;
        }
        else if($duration == 'MTD')
        {
            $cur_month = date('m',strtotime($today_date));
            $cur_year = date('Y',strtotime($today_date));
            $from_date = "$cur_year-$cur_month-01";
            $to_date = $today_date;

            $checkin = date_create($from_date);
            $checkout = date_create($to_date);
            $nights = (int)date_diff($checkin,$checkout)->format('%a');
            if ($nights == 0)
                $nights = 1;

        }
        else if($duration == 'YTD')
        {
            $cur_month = date('m',strtotime($today_date));
            if($cur_month <= 3)
            {
                $fin_year = date('Y',strtotime($today_date)) - 1;
                $from_date = "$fin_year-04-01";
                $to_date = $today_date;
            }
            else 
            {
                $fin_year = date('Y',strtotime($today_date));
                $from_date = "$fin_year-04-01";
                $to_date = $today_date;
            }
            $checkin = date_create($from_date);
            $checkout = date_create($to_date);
            $nights = (int)date_diff($checkin,$checkout)->format('%a');
        }
        else if($duration == 'FINYEAR')
        {
            
            $fin_year = date('Y',strtotime($today_date));
            $from_date = "2020-04-01";
            $to_date = '2021-03-31';
            $checkin = date_create($from_date);
            $checkout = date_create($to_date);
            $nights = (int)date_diff($checkin,$checkout)->format('%a');
        }
        //BE CONFIRMED BOOKING
        $be_bookings = Invoice::
        whereIn('hotel_id',$arr_hotel_ids)
        ->whereBetween('booking_date',[$from_date,$to_date])
        ->where('booking_status',1)
        ->select(DB::raw('count(invoice_id) AS booking_count, sum(total_amount) AS revenue'))
        ->first();


        $be_confirmed_booking_count = $be_bookings->booking_count;
        $be_revenue = $be_bookings->revenue;

        //BE CANCELLED BOOKING
        $be_bookings = Invoice::
        whereIn('hotel_id',$arr_hotel_ids)
        ->whereBetween('updated_at',[$from_date,$to_date])
        ->where('booking_status',3)
        ->select(DB::raw('count(invoice_id) AS booking_count, sum(total_amount) AS revenue'))
        ->first();

        $be_cancelled_booking_count = $be_bookings->booking_count;

        //OTA CONFIRMED BOOKINGS
        $ota_bookings = DB::connection('bookingjini_cm')->table('cm_ota_booking')
        ->whereIn('hotel_id',$arr_hotel_ids)
        ->whereBetween(DB::raw('date(booking_date)'),[$from_date,$to_date])
        ->where('confirm_status',1)
        ->where('cancel_status',0)
        ->select(DB::raw('count(unique_id) AS booking_count, sum(amount) AS revenue, sum(DATEDIFF(checkout_at, checkin_at) * rooms_qty) AS booked_room_nights'))
        ->first();

        $ota_confirmed_booking_count = $ota_bookings->booking_count;
        $ota_revenue = $ota_bookings->revenue;
        $ota_booked_room_nights = $ota_bookings->booked_room_nights;

        //OTA CANCELLED BOOKINGS
        $ota_bookings = DB::connection('bookingjini_cm')->table('cm_ota_booking')
        ->whereIn('hotel_id',$arr_hotel_ids)
        ->whereBetween(DB::raw('date(updated_at)'),[$from_date,$to_date])
        ->where('cancel_status',1)
        ->select(DB::raw('count(unique_id) AS booking_count, sum(amount) AS revenue'))
        ->first();
        
        $ota_cancelled_booking_count = $ota_bookings->booking_count;


        
        //revenue
        $total_revenue = round($be_revenue + $ota_revenue,2);
        $total_confirmed_bookings = $be_confirmed_booking_count + $ota_confirmed_booking_count;
        $total_cancelled_bookings = $be_cancelled_booking_count + $ota_cancelled_booking_count;

        
    
        
        $res = array(
            'status'=>1,
            'message'=>'Data Retrived successfullly',
            'be_confirmed_bookings'=>$be_confirmed_booking_count,
            'ota_confirmed_bookings'=>$ota_confirmed_booking_count,
            'total_confirmed_bookings'=>$total_confirmed_bookings,
            'be_confirmed_revenue'=>$be_revenue,
            'ota_confirmed_revenue'=>$ota_revenue,
            'total_confirmed_revenue'=>$total_revenue
            
        );
        return response()->json($res);
    }
    //================================================================================================================
   
}
