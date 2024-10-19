<?php

namespace App\Http\Controllers\Extranetv4\NewCRS;

use Illuminate\Http\Request;
use DB;
use App\Invoice;
use App\RatePlanLog;
use App\Http\Controllers\Controller;
use App\DynamicPricingCurrentInventory;
use App\MasterRatePlan;
use App\MasterRoomType;


class NewCrsDashboardController extends Controller
{

    //====================================Saroj Patel(15-10-22)=========================================//

    public function bookingRevenueReport($hotel_id, $duration)
    {
        $today_date = date('Y-m-d');

        if ($duration == 'MTD') {

            $cur_month = date('m', strtotime($today_date));
            $cur_year = date('Y', strtotime($today_date));
            $from_date = "$cur_year-$cur_month-01";
            $to_date = $today_date;
        } else if ($duration == 'YTD') {

            $cur_month = date('m', strtotime($today_date));
            if ($cur_month <= 3) {
                $fin_year = date('Y', strtotime($today_date)) - 1;
                $from_date = "$fin_year-04-01";
                $to_date = $today_date;
            } else {
                $fin_year = date('Y', strtotime($today_date));
                $from_date = "$fin_year-04-01";
                $to_date = $today_date;
            }
        } else {
            $from_date = date('Y-m-d');
            $to_date = date('Y-m-d');
        }

        $check_be_date = 'invoice_table.booking_date';
        $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('crs_booking', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
            ->select(
                'invoice_table.booking_date',
                'invoice_table.hotel_id',
                'invoice_table.booking_status',
                'invoice_table.total_amount',
                'invoice_table.paid_amount',
                'invoice_table.invoice_id',
                'crs_booking.payment_type',
                'crs_booking.payment_status',
                'crs_booking.is_payment_received',
                'crs_booking.expiry_time'
            )->where('invoice_table.hotel_id', '=', $hotel_id)
            ->where('invoice_table.booking_source', '=', 'CRS')
            ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date))
            ->get();

        $bookings = [];
        $revenue = [];
        $confirm_booking = 0;
        $cancelled_booking = 0;
        $wating_payment_booking = 0;
        $paid_amount = 0;
        $cancelled_amount = 0;
        $wating_payment = 0;

        if (sizeof($be_data) > 0) {
            foreach ($be_data as $be) {
                if ($be->payment_type == 1) {
                    if (isset($be->expiry_time) && ($be->expiry_time != 0)  && ($be->payment_link_status == 'valid') && $be->payment_status == 'Confirm') {
                        $expiry_time = strtotime($be->expiry_time);
                        $current_time = strtotime(date('Y-m-d H:i:s'));
                        if (($be->booking_status == 1) && ($be->is_payment_received == 1)) {
                            $confirm_booking = $confirm_booking + 1;
                            $paid_amount = $paid_amount + $be->paid_amount;
                        } else {
                            $wating_payment_booking = $wating_payment_booking + 1;
                            $wating_payment = $wating_payment + $be->paid_amount;
                        }
                    } else {
                        $cancelled_booking = $cancelled_booking + 1;
                        $cancelled_amount = $cancelled_amount + $be->total_amount;
                    }
                } else {
                    if ($be->booking_status == 3) {
                        $cancelled_booking = $cancelled_booking + 1;
                        $cancelled_amount = $cancelled_amount + $be->total_amount;
                    } else {
                        $confirm_booking = $confirm_booking + 1;
                        $paid_amount = $paid_amount + $be->paid_amount;
                    }
                }
            }
        }

        if ($confirm_booking == 0 && $cancelled_booking == 0 && $wating_payment_booking == 0 && $paid_amount == 0 && $cancelled_amount == 0 && $wating_payment == 0) {

            $bookings = [];
            $revenue = [];
        } else {

            $bookings[] = array("id" => "Confirm Booking", "label" => "Confirm Booking", "value" => $confirm_booking);
            $bookings[] = array("id" => "Cancelled Booking", "label" => "Cancelled Booking", "value" => $cancelled_booking);
            $bookings[] = array("id" => "Wating for payment", "label" => "Wating for payment", "value" => $wating_payment_booking);
            $revenue[] = array("id" => "Paid Amount", "label" => "Paid Amount", "value" => round($paid_amount));
            $revenue[] = array("id" => "Cancelled Amount", "label" => "Cancelled Amount", "value" => round($cancelled_amount));
            $revenue[] = array("id" => "Wating for payment", "label" => "wating for payment", "value" => round($wating_payment));
        }

        $total_bookings = (int) $confirm_booking + (int) $cancelled_booking + (int) $wating_payment_booking;
        $total_revenue = (int) $paid_amount + (int)$cancelled_amount + (int) $wating_payment;
        $color_code = ['#21e353', '#ed3535', '#eaf02c']; //confirm - green, cancelled - red, watting for payment - yellow; 

        $bookings_revenue = array('bookings' => $bookings, 'revenue' => $revenue, 'total_bookings' => $total_bookings, 'total_revenue' => $total_revenue, 'color_code' => $color_code);

        if ($bookings_revenue) {
            $result = array('status' => 1, "message" => 'Bookings and Revenue details fetched Successfully', 'data' => $bookings_revenue);
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Bookings and Revenue details fetched Failed');
            return response()->json($result);
        }
    }

    //======================================Saroj Patel(14-11-22)==========================================//

    public function agentCorporateCount($hotel_id)
    {

        $total_corporate = DB::table('partner_table')->where('hotel_id', $hotel_id)->where('partner_type', 'corporate')->where('is_active',1)->count();
        $total_agent = DB::table('partner_table')->where('hotel_id', $hotel_id)->where('partner_type', 'agent')->where('is_active',1)->count();
        $counts = array('total_corporate' => $total_corporate, 'total_agent' => $total_agent);

        $result = array('status' => 1, "message" => 'Bookings and Revenue details fetched Successfully', 'data' => $counts);
        return response()->json($result);
    }

    //======================================Saroj Patel(18-10-22)==========================================//

    public function crsRecentBookings($hotel_id)
    {
        $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('crs_booking', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
            ->leftJoin('partner_table', 'partner_table.id', '=', 'crs_booking.partner_id')
            ->select(
                'user_table.first_name',
                'user_table.last_name',
                'user_table.email_id',
                'user_table.mobile',
                'invoice_table.invoice_id',
                'invoice_table.booking_date',
                'invoice_table.check_in_out',
                'invoice_table.booking_source',
                'invoice_table.total_amount',
                'partner_table.partner_name'
            )->where('invoice_table.hotel_id', '=', $hotel_id)
            ->where('invoice_table.booking_status', '=', 1)
            ->orderBy('invoice_table.invoice_id', 'DESC')
            ->take(10)
            ->get();

        $all_booking_data = [];
        if (sizeof($be_data) > 0) {
            foreach ($be_data as $be) {

                $check_in_out = $be->check_in_out;
                $remove_brakets = substr($check_in_out, 1);
                $remove_brakets1 = substr($remove_brakets, 0, -1);
                $explode_check_in_out = explode('-', $remove_brakets1);

                $check_in = $explode_check_in_out[0] . '-' . $explode_check_in_out[1] . '-' . $explode_check_in_out[2];
                $check_out = $explode_check_in_out[3] . '-' . $explode_check_in_out[4] . '-' . $explode_check_in_out[5];

                $date1 = date_create($check_in);
                $date2 = date_create($check_out);
                $diff = date_diff($date1, $date2);
                $no_of_nights = $diff->format("%a");
                if ($no_of_nights == 0) {
                    $no_of_nights = 1;
                }

                $booking_date =  date_create(date('Y-m-d', strtotime($be->booking_date)));
                $current_date = date_create(date('Y-m-d'));
                $booking_date_diff = date_diff($booking_date, $current_date);
                $booking_date_diff = $booking_date_diff->format("%a");
                if ($booking_date_diff == 0) {
                    $booking_details['booking_days'] = 'Today';
                } else if ($booking_date_diff == 1) {
                    $booking_details['booking_days'] = 'Yesterday';
                } else {
                    $booking_details['booking_days'] = $booking_date_diff . ' days ago.';
                }
                $booking_details['partner_name'] = isset($be->partner_name) ? $be->partner_name : 'Direct Bookings';
                $booking_details['customer_name'] = $be->first_name . ' ' . $be->last_name;
                $booking_details['customer_phone'] = isset($be->mobile) ? $be->mobile : 'NA';
                $booking_details['total_amount'] = $be->total_amount;
                $booking_details['display_booking_date'] = date('d M Y', strtotime($be->booking_date));
                $booking_details['check_in_out_at'] = date("d M", strtotime($check_in)) . " - " . date("d M", strtotime($check_out));

                array_push($all_booking_data, $booking_details);
            }
        }

        if ($all_booking_data) {
            $result = array('status' => 1, "message" => 'Bookings fetched', 'data' => $all_booking_data);
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Bookings fetched Failed');
            return response()->json($result);
        }
    }


    public function occupancyPercentage($hotel_id, $from_date, $to_date)
    {
        $date_from = date('Y-m-d', strtotime($from_date));
        $date_to = date('Y-m-d', strtotime($to_date));

        $str_dates = '';
        for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
            $str_dates .= "'$dt' between A.from_date and A.to_date OR";
            $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
        }
        $str_dates = substr($str_dates, 0, strlen($str_dates) - 3);

        $be_query = "select 'Bookingjini' as channel, -1 as ota_id, 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg' as ota_logo_path, 0 as sl_no,
        D.rate_plan_id,  D.plan_type, D.plan_name,
        E.room_type, A.room_type_id,
        A.bar_price, A.multiple_days,
        A.multiple_occupancy, A.from_date, A.to_date,
        A.block_status, A.los, A.extra_adult_price, A.extra_child_price, A.created_at, 1 as rate_block_status
        from 
        booking_engine.rate_plan_log_table A
        LEFT JOIN kernel.rate_plan_table D ON A.hotel_id = D.hotel_id AND A.rate_plan_id = D.rate_plan_id 
        LEFT JOIN kernel.room_type_table E ON A.hotel_id = E.hotel_id AND A.room_type_id = E.room_type_id 
        where A.hotel_id = $hotel_id
        and (
            $str_dates
        )
        order by  A.rate_plan_id ";
        $ota_rates = DB::select(DB::raw($be_query));

        if ($ota_rates) {
            $room_types = [];
            $room_type_record = [];

            foreach ($ota_rates as $key => $ota_rate) {
                if (!in_array($ota_rate->room_type_id, $room_types) && $ota_rate->room_type != '') {
                    $room_types[] = $ota_rate->room_type_id;
                    $room_type_record['room_type'] = $ota_rate->room_type;
                    $room_type_record['room_type_id'] = $ota_rate->room_type_id;
                    $all_room_types[] = $room_type_record;
                } else {
                    continue;
                }
            }

            $rate_plan_info = [];
            foreach ($all_room_types as $key => $room_type) {
                $rate_plan_details = MasterRatePlan::join('room_rate_plan', 'room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
                    ->select('rate_plan_table.plan_type', 'rate_plan_table.rate_plan_id')
                    ->where('room_rate_plan.room_type_id', $room_type['room_type_id'])
                    ->where('room_rate_plan.hotel_id', $hotel_id)
                    ->where('room_rate_plan.is_trash', 0)
                    ->get()->toArray();

                $rate_plan_rt = [];
                $rate_plan_ids = [];
                foreach ($rate_plan_details as $plan_type) {
                    $rate_plan_rt[] = $plan_type['plan_type'];
                    $rate_plan_ids[] = $plan_type['rate_plan_id'];
                }

                $rate_plan_info[$key]['room_type_id'] = $room_type['room_type_id'];
                foreach ($rate_plan_details as $rate_plan) {
                    if (in_array('EP', $rate_plan_rt)) {
                        $index = array_search('EP', $rate_plan_rt);
                        $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                    } elseif (in_array('CP', $rate_plan_rt)) {
                        $index = array_search('CP', $rate_plan_rt);
                        $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                    } elseif (in_array('MAP', $rate_plan_rt)) {
                        $index = array_search('MAP', $rate_plan_rt);
                        $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                    } else {
                        $index = array_search('AP', $rate_plan_rt);
                        $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                    }
                }
            }

            $rate_date = array();
            $rate_record = [];
            //populate the rate date
            for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
                $short_day = date('D', strtotime($dt));
                foreach ($all_room_types as $room_type_record) {
                    $room_type_id = $room_type_record['room_type_id'];
                    $rate_date[$dt][$room_type_id]['created_at'] = '1970-01-01 00:00:00';
                    foreach ($rate_plan_info as $rate_plan) {
                        foreach ($ota_rates as $ota_rate) {
                            if ($ota_rate->room_type_id == $room_type_id && $rate_plan['rate_plan_id'] == $ota_rate->rate_plan_id && $dt >= $ota_rate->from_date && $dt <= $ota_rate->to_date) {
                                if (isset($rate_date[$dt][$room_type_id]['created_at']) && date('Y-m-d H:i:s', strtotime($ota_rate->created_at)) > $rate_date[$dt][$room_type_id]['created_at']) {
                                    $multiple_days = $ota_rate->multiple_days;
                                    $arr_days = json_decode($multiple_days);
                                    if ($arr_days->$short_day == 1) {
                                        if (!$ota_rate->created_at > $rate_date[$dt][$room_type_id]['created_at']) {
                                            $rate_record['bar_price'] = $ota_rate->bar_price;
                                            $rate_record['created_at'] = $ota_rate->created_at;
                                            $rate_date[$dt][$room_type_id] = $rate_record;
                                        }

                                        if ($ota_rate->created_at > $rate_date[$dt][$room_type_id]['created_at']) {
                                            $rate_record['bar_price'] = $ota_rate->bar_price;
                                            $rate_record['created_at'] = $ota_rate->created_at;
                                            $rate_date[$dt][$room_type_id] = $rate_record;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
            }

            //Find the min price
            foreach ($rate_date as $rt_dt => $value) {
                $rate_plan_min_price = 1000000;
                foreach ($all_room_types as $rate_plan_record) {
                    $room_type_id = $rate_plan_record['room_type_id'];
                    if (isset($value[$room_type_id]['bar_price']) && $value[$room_type_id]['bar_price'] < $rate_plan_min_price) {
                        $rate_plan_min_price = $value[$room_type_id]['bar_price'];
                    }
                }
                if ($rate_plan_min_price != 1000000)
                    $rate_date[$rt_dt]['min_price'] = $rate_plan_min_price;
                else
                    $rate_date[$rt_dt]['min_price'] = 'NA';
            }
            $to_date = date('Y-m-d', strtotime($to_date . '+1 day'));
            $room_type_details = DB::table('kernel.room_type_table')->select('total_rooms', 'room_type_id')
                ->where('hotel_id', $hotel_id)
                ->where('is_trash', '0')
                ->get();

            $total_rooms = 0;
            $total_room_type = [];
            foreach ($room_type_details as $rooms) {
                $total_rooms = $total_rooms + $rooms->total_rooms;
                $total_room_type[$rooms->room_type_id] = $rooms->total_rooms;
            }

            $availableRoomsDateWise = [];
            $dpCurInvDetails = DynamicPricingCurrentInventory::where('stay_day', '>=', $from_date)
                ->where('stay_day', '<=', $to_date)
                ->where('hotel_id', $hotel_id)
                ->where('ota_id', '-1')
                ->groupBy('stay_day', 'room_type_id')
                ->orderBy('stay_day', 'ASC')
                ->get();

            $minprice_array = array();
            foreach ($rate_date as $rt_dt => $value) {
                if ($value['min_price'] != 'NA') {
                    $minprice_array[] = $value['min_price'];
                } else {
                    $minprice_array[] = 'NA';
                }
                $min_price =  min($minprice_array);
                $minprice_array = [];
                $avlInv = [];
                $total_available_rooms = 0;
                foreach ($dpCurInvDetails as $details) {
                    if ($details->stay_day == $rt_dt) {
                        $total_available_rooms = $total_available_rooms + $details->no_of_rooms;
                        $avlInv[$details->room_type_id] = $details->no_of_rooms;
                    } else {
                        $avlInv[$details->room_type_id] = 0;
                    }
                }

                $available_room_type = array_diff_key($total_room_type, $avlInv);
                if ($available_room_type) {
                    foreach ($available_room_type as $key => $room_type) {
                        $total_available_rooms = (int)$total_available_rooms + (int)$room_type;
                    }
                }
                $occupancy = round($total_available_rooms / $total_rooms * 100);
                // $occupancy_percentage = $occupancy . '%'; 
                if ($occupancy >= 50) {
                    $color_code = '#FFFFFF';
                } else if ($occupancy >= 30 && $occupancy <= 49) {
                    $color_code = '#FFBDCB';
                } else if ($occupancy >= 10 && $occupancy < 29) {
                    $color_code = '#FF839E';
                } else {
                    $color_code = '#E64467';
                }
                $availableRoomsDateWise[] = array('currentDate' => $rt_dt, 'minimum_rates' => $min_price, 'currency_symbol' => '20B9', 'total_rooms' => $total_rooms, 'available_rooms' => $total_available_rooms, 'occupancy' => $occupancy, 'color_code' => $color_code);
            }
        } else {
            $to_date = date('Y-m-d', strtotime($to_date . '+1 day'));
            $room_type_details = DB::table('kernel.room_type_table')->select('total_rooms', 'room_type_id')
                ->where('hotel_id', $hotel_id)
                ->where('is_trash', '0')
                ->get();

            $total_rooms = 0;
            $total_room_type = [];
            foreach ($room_type_details as $rooms) {
                $total_rooms = $total_rooms + $rooms->total_rooms;
                $total_room_type[$rooms->room_type_id] = $rooms->total_rooms;
            }

            $dpCurInvDetails = DynamicPricingCurrentInventory::where('stay_day', '>=', $from_date)
                ->where('stay_day', '<=', $to_date)
                ->where('hotel_id', $hotel_id)
                ->where('ota_id', '-1')
                ->groupBy('stay_day', 'room_type_id')
                ->orderBy('stay_day', 'ASC')
                ->get();

            $dateRange  = new \DatePeriod(
                new \DateTime($from_date),
                new \DateInterval('P1D'),
                new \DateTime($to_date)
            );

            foreach ($dateRange as $value) {

                $min_price = 'NA';
                $currentDate = $value->format('Y-m-d');
                $avlInv = [];
                $total_available_rooms = 0;
                foreach ($dpCurInvDetails as $details) {
                    if ($details->stay_day == $currentDate) {
                        $total_available_rooms = $total_available_rooms + $details->no_of_rooms;
                        $avlInv[$details->room_type_id] = $details->no_of_rooms;
                    } else {
                        $avlInv[$details->room_type_id] = 0;
                    }
                }

                $available_room_type = array_diff_key($total_room_type, $avlInv);
                if ($available_room_type) {
                    foreach ($available_room_type as $key => $room_type) {
                        $total_available_rooms = (int)$total_available_rooms + (int)$room_type;
                    }
                }

                if ($total_available_rooms == 0 || $total_rooms == 0) {
                    $occupancy = 0;
                } else {
                    $occupancy = round($total_available_rooms / $total_rooms * 100);
                }

                // $occupancy_percentage = $occupancy . '%'; 
                if ($occupancy >= 50) {
                    $color_code = '#FFFFFF';
                } else if ($occupancy >= 30 && $occupancy <= 49) {
                    $color_code = '#FFBDCB';
                } else if ($occupancy >= 10 && $occupancy < 29) {
                    $color_code = '#FF839E';
                } else {
                    $color_code = '#E64467';
                }

                $availableRoomsDateWise[] = array('currentDate' => $currentDate, 'minimum_rates' => $min_price, 'currency_symbol' => '20B9', 'total_rooms' => $total_rooms, 'available_rooms' => $total_available_rooms, 'occupancy' => $occupancy, 'color_code' => $color_code);
            }
        }

        if ($availableRoomsDateWise) {
            $result = array(
                'status' => 1, "message" => 'Bookings fetched', 'data' => $availableRoomsDateWise
            );
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Bookings fetched Failed');
            return response()->json($result);
        }
    }

    
    public function occupancyPercentageTest($hotel_id, $from_date, $to_date)
    {
        $date_from = date('Y-m-d', strtotime($from_date));
        $date_to = date('Y-m-d', strtotime($to_date));
        $room_types = MasterRoomType::where('hotel_id', $hotel_id)->where('is_trash', 0)->get();

        $str_dates = '';
        for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
            $str_dates .= "'$dt' between A.from_date and A.to_date OR";
            $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
        }
        $str_dates = substr($str_dates, 0, strlen($str_dates) - 3);
        $room_type_wise_min_rate = [];
        foreach ($room_types as $room_type) {
            $be_query = "
            select 'Bookingjini' as channel, -1 as ota_id, 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg' as ota_logo_path, 0 as sl_no,
            D.rate_plan_id,  D.plan_type, D.plan_name,
            A.bar_price, A.multiple_days,
            A.multiple_occupancy, A.from_date, A.to_date,
            A.block_status, A.los, A.extra_adult_price, A.extra_child_price, A.created_at, 1 as rate_block_status
            from 
            booking_engine.rate_plan_log_table A
            LEFT JOIN kernel.rate_plan_table D ON A.hotel_id = D.hotel_id AND A.rate_plan_id = D.rate_plan_id 
            where A.hotel_id = $hotel_id
            and A.room_type_id = $room_type->room_type_id
            and (
                $str_dates
            )
            order by  A.rate_plan_id ";
            $be_rates = DB::select(DB::raw($be_query));

            $all_rate_plans = array();
            $rate_plan_names = array();
            foreach ($be_rates as $ota_rate) {
                if (!in_array($ota_rate->plan_type, $rate_plan_names) && $ota_rate->plan_type != '') {
                    $rate_plan_record['plan_type'] = $ota_rate->plan_type;
                    $rate_plan_record['plan_name'] = $ota_rate->plan_name;
                    $rate_plan_record['rate_plan_id'] = $ota_rate->rate_plan_id;
                    $all_rate_plans[] = $rate_plan_record;
                } else {
                    continue;
                }
                $rate_plan_names[] = $ota_rate->plan_type;
            }

            $rate_date = array();
            //populate the rate date
            for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
                $short_day = date('D', strtotime($dt));
                foreach ($all_rate_plans as $rate_plan_record) {
                    $rate_plan_id = $rate_plan_record['rate_plan_id'];
                    $rate_date[$dt][$rate_plan_id]['created_at'] = '1970-01-01 00:00:00';
                    foreach ($be_rates as $ota_rate) {
                        if ($ota_rate->rate_plan_id == $rate_plan_id && $dt >= $ota_rate->from_date && $dt <= $ota_rate->to_date) {
                            if (isset($rate_date[$dt][$rate_plan_id]['created_at']) && date('Y-m-d H:i:s', strtotime($ota_rate->created_at)) > $rate_date[$dt][$rate_plan_id]['created_at']) {
                                $multiple_days = $ota_rate->multiple_days;
                                $arr_days = json_decode($multiple_days);
                                if ($arr_days->$short_day == 1) {
                                    $rate_record['rate_plan_id'] = $ota_rate->rate_plan_id;
                                    $rate_record['bar_price'] = $ota_rate->bar_price;
                                    $rate_record['created_at'] = $ota_rate->created_at;
                                    $rate_date[$dt][$rate_plan_id] = $rate_record;
                                }
                            }
                        }
                    }
                }
                $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
            }

            //Find the min price
            foreach ($rate_date as $rt_dt => $value) {
                $rate_plan_min_price = 1000000;
                foreach ($all_rate_plans as $rate_plan_record) {
                    // return $rate_plan_record;
                    $rate_plan_id = $rate_plan_record['rate_plan_id'];
                    if (isset($value[$rate_plan_id]['bar_price']) && $value[$rate_plan_id]['bar_price'] < $rate_plan_min_price) {
                        $rate_plan_min_price = $value[$rate_plan_id]['bar_price'];
                    }
                }
                if ($rate_plan_min_price != 1000000)
                {
                    $rate_date[$rt_dt]['min_price'] = $rate_plan_min_price;
                    $rate_plan_min_price = $rate_plan_min_price;
                }else{
                    $rate_date[$rt_dt]['min_price'] = 'NA';
                    $rate_plan_min_price = 'NA';
                }
                    
                $room_type_wise_min_rate[$rt_dt][$room_type->room_type_id] = $rate_plan_min_price;
            }
        }

        $to_date = date('Y-m-d', strtotime($to_date . '+1 day'));
        $room_type_details = DB::table('kernel.room_type_table')->select('total_rooms', 'room_type_id')
            ->where('hotel_id', $hotel_id)
            ->where('is_trash', '0')
            ->get();

        $total_rooms = 0;
        $total_room_type = [];
        foreach ($room_type_details as $rooms) {
            $total_rooms = $total_rooms + $rooms->total_rooms;
            $total_room_type[$rooms->room_type_id] = $rooms->total_rooms;
        }

        $availableRoomsDateWise = [];
        $dpCurInvDetails = DynamicPricingCurrentInventory::where('stay_day', '>=', $from_date)
            ->where('stay_day', '<=', $to_date)
            ->where('hotel_id', $hotel_id)
            ->where('ota_id', '-1')
            ->groupBy('stay_day', 'room_type_id')
            ->orderBy('stay_day', 'ASC')
            ->get();

        if(!empty($room_type_wise_min_rate)){
            $min_price = array();
            foreach($room_type_wise_min_rate as $rt_dt => $value){
                if ($value) {
                    $min_price = min($value);
                } else {
                    $min_price = 'NA';
                }
                $avlInv = [];
                $total_available_rooms = 0;
                foreach ($dpCurInvDetails as $details) {
                    if ($details->stay_day == $rt_dt) {
                        $total_available_rooms = $total_available_rooms + $details->no_of_rooms;
                        $avlInv[$details->room_type_id] = $details->no_of_rooms;
                    } else {
                        $avlInv[$details->room_type_id] = 0;
                    }
                }

                $available_room_type = array_diff_key($total_room_type, $avlInv);
                if ($available_room_type) {
                    foreach ($available_room_type as $key => $room_type) {
                        $total_available_rooms = (int)$total_available_rooms + (int)$room_type;
                    }
                }
                $occupancy = round($total_available_rooms / $total_rooms * 100);
                // $occupancy_percentage = $occupancy . '%'; 
                if ($occupancy >= 50) {
                    $color_code = '#FFFFFF';
                } else if ($occupancy >= 30 && $occupancy <= 49) {
                    $color_code = '#FFBDCB';
                } else if ($occupancy >= 10 && $occupancy < 29) {
                    $color_code = '#FF839E';
                } else {
                    $color_code = '#E64467';
                }
                $availableRoomsDateWise[] = array('currentDate' => $rt_dt, 'minimum_rates' => $min_price, 'currency_symbol' => '20B9', 'total_rooms' => $total_rooms, 'available_rooms' => $total_available_rooms, 'occupancy' => $occupancy, 'color_code' => $color_code);
            }
        }else{
            $dateRange  = new \DatePeriod(
                new \DateTime($from_date),
                new \DateInterval('P1D'),
                new \DateTime($to_date)
            );
    
            foreach ($dateRange as $value) {
                $min_price = 'NA';
                $currentDate = $value->format('Y-m-d');
                $avlInv = [];
                $total_available_rooms = 0;
                foreach ($dpCurInvDetails as $details) {
                    if ($details->stay_day == $currentDate) {
                        $total_available_rooms = $total_available_rooms + $details->no_of_rooms;
                        $avlInv[$details->room_type_id] = $details->no_of_rooms;
                    } else {
                        $avlInv[$details->room_type_id] = 0;
                    }
                }

                $available_room_type = array_diff_key($total_room_type, $avlInv);
                if ($available_room_type) {
                    foreach ($available_room_type as $key => $room_type) {
                        $total_available_rooms = (int)$total_available_rooms + (int)$room_type;
                    }
                }
    
                if ($total_available_rooms == 0 || $total_rooms == 0) {
                    $occupancy = 0;
                } else {
                    $occupancy = round($total_available_rooms / $total_rooms * 100);
                }
    
                if ($occupancy >= 50) {
                    $color_code = '#FFFFFF';
                } else if ($occupancy >= 30 && $occupancy <= 49) {
                    $color_code = '#FFBDCB';
                } else if ($occupancy >= 10 && $occupancy < 29) {
                    $color_code = '#FF839E';
                } else {
                    $color_code = '#E64467';
                }
    
                $availableRoomsDateWise[] = array('currentDate' => $currentDate, 'minimum_rates' => $min_price, 'currency_symbol' => '20B9', 'total_rooms' => $total_rooms, 'available_rooms' => $total_available_rooms, 'occupancy' => $occupancy, 'color_code' => $color_code);
            }
        }

        if ($availableRoomsDateWise) {
            $result = array(
                'status' => 1, "message" => 'Bookings fetched', 'data' => $availableRoomsDateWise
            );
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Bookings fetched Failed');
            return response()->json($result);
        }
    }

    //=======================================================================================================//



    public function minHotelPrice(Request $request)
    {
        $hotel_ids = $request->hotel_id;
        $from_date = $request->from_date;
        $to_date = $request->from_date;
    
        $date_from = date('Y-m-d', strtotime($from_date));
        $date_to = date('Y-m-d', strtotime($to_date));
        $to_date1 = date('Y-m-d', strtotime($to_date . '+1 day'));
        $availableRoomsDateWise = [];
        $rate_plan_info = [];
        foreach ($hotel_ids as $hotel_id) {
            $str_dates = '';
            for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
                $str_dates .= "'$dt' between A.from_date and A.to_date OR";
                $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
            }
            $str_dates = substr($str_dates, 0, strlen($str_dates) - 3);
    
            $be_query = "select 'Bookingjini' as channel, -1 as ota_id, 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg' as ota_logo_path, 0 as sl_no,
                D.rate_plan_id,  D.plan_type, D.plan_name,
                E.room_type, A.room_type_id,
                A.bar_price, A.multiple_days,
                A.multiple_occupancy, A.from_date, A.to_date,
                A.block_status, A.los, A.extra_adult_price, A.extra_child_price, A.created_at, 1 as rate_block_status
                from 
                booking_engine.rate_plan_log_table A
                LEFT JOIN kernel.rate_plan_table D ON A.hotel_id = D.hotel_id AND A.rate_plan_id = D.rate_plan_id 
                LEFT JOIN kernel.room_type_table E ON A.hotel_id = E.hotel_id AND A.room_type_id = E.room_type_id 
                where A.hotel_id = $hotel_id
                and (
                    $str_dates
                )
                order by  A.rate_plan_id ";
            $ota_rates = DB::select(DB::raw($be_query));
    
            // print_r($ota_rates);
    
            if ($ota_rates) {
                $room_types = [];
                $room_type_record = [];
    
                foreach ($ota_rates as $key => $ota_rate) {
                    if (!in_array($ota_rate->room_type_id, $room_types) && $ota_rate->room_type != '') {
                        $room_types[] = $ota_rate->room_type_id;
                        $room_type_record['room_type'] = $ota_rate->room_type;
                        $room_type_record['room_type_id'] = $ota_rate->room_type_id;
                        $all_room_types[] = $room_type_record;
                    } else {
                        continue;
                    }
                }
    
                
                foreach ($all_room_types as $key => $room_type) {
                    $rate_plan_details = MasterRatePlan::join('room_rate_plan', 'room_rate_plan.rate_plan_id', '=', 'rate_plan_table.rate_plan_id')
                    ->select('rate_plan_table.plan_type', 'rate_plan_table.rate_plan_id')
                    ->where('room_rate_plan.room_type_id', $room_type['room_type_id'])
                        ->where('room_rate_plan.hotel_id', $hotel_id)
                        ->where('room_rate_plan.is_trash', 0)
                        ->get()->toArray();
    
                    $rate_plan_rt = [];
                    $rate_plan_ids = [];
                    foreach ($rate_plan_details as $plan_type) {
                        $rate_plan_rt[] = $plan_type['plan_type'];
                        $rate_plan_ids[] = $plan_type['rate_plan_id'];
                    }
    
                    $rate_plan_info[$key]['room_type_id'] = $room_type['room_type_id'];
                    foreach ($rate_plan_details as $rate_plan) {
                        if (in_array('EP', $rate_plan_rt)) {
                            $index = array_search('EP', $rate_plan_rt);
                            $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                        } elseif (in_array('CP', $rate_plan_rt)) {
                            $index = array_search('CP', $rate_plan_rt);
                            $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                        } elseif (in_array('MAP', $rate_plan_rt)) {
                            $index = array_search('MAP', $rate_plan_rt);
                            $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                        } else {
                            $index = array_search('AP', $rate_plan_rt);
                            $rate_plan_info[$key]['rate_plan_id'] =  $rate_plan_ids[$index];
                        }
                    }
                }
    
                $rate_date = array();
                $rate_record = [];
                //populate the rate date
                for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
                    $short_day = date('D', strtotime($dt));
                    foreach ($all_room_types as $room_type_record) {
                        $room_type_id = $room_type_record['room_type_id'];
                        $rate_date[$dt][$room_type_id]['created_at'] = '1970-01-01 00:00:00';
                        foreach ($rate_plan_info as $rate_plan) {
                            foreach ($ota_rates as $ota_rate) {
                                if ($ota_rate->room_type_id == $room_type_id && $rate_plan['rate_plan_id'] == $ota_rate->rate_plan_id && $dt >= $ota_rate->from_date && $dt <= $ota_rate->to_date) {
                                    if (isset($rate_date[$dt][$room_type_id]['created_at']) && date('Y-m-d H:i:s', strtotime($ota_rate->created_at)) > $rate_date[$dt][$room_type_id]['created_at']) {
                                        $multiple_days = $ota_rate->multiple_days;
                                        $arr_days = json_decode($multiple_days);
                                        if ($arr_days->$short_day == 1) {
                                            if (!$ota_rate->created_at > $rate_date[$dt][$room_type_id]['created_at']) {
                                                $rate_record['bar_price'] = $ota_rate->bar_price;
                                                $rate_record['created_at'] = $ota_rate->created_at;
                                                $rate_date[$dt][$room_type_id] = $rate_record;
                                            }
    
                                            if ($ota_rate->created_at > $rate_date[$dt][$room_type_id]['created_at']) {
                                                $rate_record['bar_price'] = $ota_rate->bar_price;
                                                $rate_record['created_at'] = $ota_rate->created_at;
                                                $rate_date[$dt][$room_type_id] = $rate_record;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
                }
    
                //Find the min price
                foreach ($rate_date as $rt_dt => $value) {
                    $rate_plan_min_price = 1000000;
                    foreach ($all_room_types as $rate_plan_record) {
                        $room_type_id = $rate_plan_record['room_type_id'];
                        if (isset($value[$room_type_id]['bar_price']) && $value[$room_type_id]['bar_price'] < $rate_plan_min_price) {
                            $rate_plan_min_price = $value[$room_type_id]['bar_price'];
                        }
                    }
                    if ($rate_plan_min_price != 1000000)
                        $rate_date[$rt_dt]['min_price'] = $rate_plan_min_price;
                    else
                        $rate_date[$rt_dt]['min_price'] = 'NA';
                }

                $minprice_array = array();
                foreach ($rate_date as $rt_dt => $value) {
                    if ($value['min_price'] != 'NA') {
                        $minprice_array[] = $value['min_price'];
                    } else {
                        $minprice_array[] = 'NA';
                    }
                    $min_price =  min($minprice_array);
                    $availableRoomsDateWise[] = array('Date' => $rt_dt, 'minimum_rates' => $min_price, 'hotel_id' => $hotel_id);
                }
            }else {
               
                $dateRange  = new \DatePeriod(
                    new \DateTime($from_date),
                    new \DateInterval('P1D'),
                    new \DateTime($to_date1)
                );
                
                foreach ($dateRange as $value) {
                    $min_price = 'NA';
                    $currentDate = $value->format('Y-m-d');
                    $availableRoomsDateWise[] = array('Date' => $currentDate, 'minimum_rates' => $min_price,'hotel_id' => $hotel_id);
                }
            }
        }
    
        if ($availableRoomsDateWise) {
            $result = array(
                'status' => 1, "message" => 'Bookings fetched', 'data' => $availableRoomsDateWise
            );
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Bookings fetched Failed');
            return response()->json($result);
        }
    }


}
