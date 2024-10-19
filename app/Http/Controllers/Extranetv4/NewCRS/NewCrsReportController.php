<?php

namespace App\Http\Controllers\Extranetv4\NewCRS;

use Illuminate\Http\Request;
use DB;
use App\Invoice;
use App\Http\Controllers\Controller;
use App\NewCrs\PartnerDetails;

class NewCrsReportController extends Controller
{
 
    //====================================Saroj Patel(14-10-22)========================================//

    public function crsBookingReport(Request $request)
    {
        $failure_message = 'Bookings Fetch Failed';
        $data = $request->all();

        if (isset($data['from_date'])) {
            $from_date = date('Y-m-d', strtotime($data['from_date']));
        } else {
            $from_date = date('Y-m-d');
        }

        if (isset($data['to_date'])) {
            $to_date = date('Y-m-d', strtotime($data['to_date']));
        } else {
            $to_date = date('Y-m-d');
        }

        $hotel_id = $data['hotel_id'];
        $payment_options = $data['payment_options'];
        $booking_status = $data['booking_status'];
        $agent = $data['agent_id'];
        $check_be_date = 'invoice_table.booking_date';

        if(isset($data['sales_executive_id'])){
            $sales_executive = $data['sales_executive_id'];
        }else{
            $sales_executive = 0;
        }
        

        $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('crs_booking', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
            ->leftjoin('partner_table', 'partner_table.id', '=', 'crs_booking.partner_id')
            ->leftjoin('crs_sales_executive', 'crs_sales_executive.id', '=', 'crs_booking.sales_executive_id')
            // ->leftjoin('crs_payment_receive', 'crs_payment_receive.invoice_id', '=', 'invoice_table.invoice_id')
            ->select(
                // DB::raw('SUM(crs_payment_receive.receive_amount) as total_received_amount'),
                'user_table.first_name',
                'user_table.last_name',
                'user_table.user_id',
                'invoice_table.booking_date',
                'invoice_table.hotel_id',
                'invoice_table.pay_to_hotel',
                'invoice_table.booking_status',
                'invoice_table.hotel_name',
                'invoice_table.total_amount',
                'invoice_table.paid_amount',
                'invoice_table.booking_source',
                'invoice_table.invoice_id',
                'crs_booking.no_of_rooms',
                'crs_booking.booking_time',
                'crs_booking.payment_type',
                'crs_booking.payment_link_status',
                'crs_booking.payment_status',
                'crs_booking.is_payment_received',
                'crs_booking.valid_hour',
                'crs_booking.check_in',
                'crs_booking.check_out',
                'crs_booking.partner_id',
                'crs_booking.sales_executive_id',
                'crs_sales_executive.name as sales_executive_name',
                'partner_table.partner_name',
                'crs_booking.expiry_time'
            )->where('invoice_table.hotel_id', '=', $hotel_id)
            ->where('invoice_table.booking_source', '=', 'CRS')
            ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date));
        // 0 = all, 1 = Email with payment link, 2 = Email no payment link, 3 = No email no payment
        if ($payment_options != 0) {
            $be_data =  $be_data->where('crs_booking.payment_type', '=', $payment_options);
        }
        // 0 = all, 1 = Confirm, 3 = Cancelled
        if ($booking_status != 0) {
            $be_data = $be_data->where('invoice_table.booking_status', '=', $booking_status);
        }

            if($agent != 0){
                $be_data = $be_data->where('crs_booking.partner_id', '=', $agent);
            }

            if($sales_executive != 0){
                $be_data = $be_data->where('crs_booking.sales_executive_id', '=', $sales_executive);
            }

        
        $be_data =  $be_data->get();


        $all_booking_data = [];
        if (sizeof($be_data) > 0) {
            foreach ($be_data as $be) {

                $date1 = date_create($be->check_in);
                $date2 = date_create($be->check_out);
                $diff = date_diff($date1, $date2);
                $no_of_nights = $diff->format("%a");
                if ($no_of_nights == 0) {
                    $no_of_nights = 1;
                }

                if ($be->booking_status == 3) {
                    $btn_status = 'Cancelled';
                } else {
                    $btn_status = 'Confirmed';
                }

                if ($be->payment_type == 1) {
                    $payment_options = 'Email with Payment Link';
                } elseif ($be->payment_type == 2) {
                    $payment_options = 'Email (no payment link)';
                } else {
                    $payment_options = 'No Email No Payment Link';
                }

                $booking_details['hotel_id'] = $be->hotel_id;
                $booking_details['booking_id'] = date('dmy', strtotime($be->booking_time)) . $be->invoice_id;
                $booking_details['customer_details'] = strtoupper($be->first_name) . ' ' . strtoupper($be->last_name);
                $booking_details['no_of_rooms'] = $be->no_of_rooms;
                $booking_details['nights'] = $no_of_nights;
                $booking_details['booking_date'] = $be->booking_time;
                $booking_details['display_booking_date'] = date('d M Y', strtotime($be->booking_time));
                $booking_details['display_booking_time'] = date('h:i a', strtotime($be->booking_time));

                $payment_received = DB::table('crs_payment_receive')->where('invoice_id',$be->invoice_id)->sum('receive_amount');
                if($payment_received == 0){
                    $received_amount = 0;
                }else{
                    $received_amount = $payment_received;
                }

                if ($be->payment_type == 1) {
                   
                    $expiry_time = strtotime($be->expiry_time);
                    if($expiry_time == ''){
                        $expiry_time = strtotime($be->check_out);
                       
                    }
                    $current_time = strtotime(date('Y-m-d H:i:s'));
                    $booking_time = strtotime($be->booking_time);
                    $expeted_payment = $expiry_time - $booking_time;

                  
                    $expeted_payment = $expeted_payment / 60;
                    $expeted_payment_time = $expeted_payment . ' ' . 'Minutes';
                    if ($expeted_payment_time >= 60) {
                        $expeted_payment_to_hr = $expeted_payment / 60;
                        if ($expeted_payment_to_hr) {
                            $expeted_payment_time = round($expeted_payment_to_hr) . ' ' . 'Hour';
                        } else {
                            $expeted_payment_time = round($expeted_payment_to_hr) . ' ' . 'Hours';
                        }

                        if ($expeted_payment_to_hr >= 24) {  
                            $remaning_times_to_hr = round($expeted_payment_to_hr / 24);
                            if($remaning_times_to_hr>=1){
                                $expeted_payment_time = round($remaning_times_to_hr) . ' ' . 'Days';
                            }else{
                                $expeted_payment_time = round($remaning_times_to_hr) . ' ' . 'Day';
                            }
                        }
                    }
                    
                    

                    if (isset($be->expiry_time) && ($be->expiry_time != 0)  && ($be->payment_link_status == 'valid') && $be->payment_status == 'Confirm') {

                        if (($expiry_time > $current_time) ) {
                            $remaning_time = $expiry_time - $current_time;
                            $remaning_time_to_min = round($remaning_time / 60);
                            $remaning_times = $remaning_time_to_min . ' ' . 'Minutes';
                            if ($remaning_times >= 60) {
                                $remaning_times_to_hr = $remaning_time_to_min / 60;
                                $remaning_times = round($remaning_times_to_hr) . ' ' . 'Hours';
                                if ($remaning_times_to_hr >= 24) {  
                                    $remaning_times_to_hr = round($remaning_times_to_hr / 24);
                                    if($remaning_times_to_hr>=1){
                                        $remaning_times = round($remaning_times_to_hr) . ' ' . 'Days';
                                    }else{
                                        $remaning_times = round($remaning_times_to_hr) . ' ' . 'Day';
                                    }
                                }
                            }
                          
                            $booking_details['recived_amount'] = $received_amount;
                        } else {
                            if(($be->payment_link_status == 'valid') && $be->payment_status == 'Confirm'){
                                $remaning_times = 0;
                                $booking_details['recived_amount'] = $received_amount;
                            }else{
                                $remaning_times = 0;
                                $booking_details['recived_amount'] = $received_amount;
                            }
                            
                        }
                    } else {

                        if(isset($be->expiry_time)){
                            $remaning_times = strtotime($be->expiry_time) - $current_time;
                        }else{
                            $remaning_times = strtotime($be->check_out);
                        }
                        
                        if($remaning_times < $current_time){
                            $remaning_times = 0;
                        }else{
                            $expiry_time = strtotime($be->check_out);
                            $remaning_time = $expiry_time - $current_time;
                            $remaning_time_to_min = round($remaning_time / 60);
                            $remaning_times = $remaning_time_to_min . ' ' . 'Minutes';
                            if ($remaning_times >= 60) {
                                $remaning_times_to_hr = $remaning_time_to_min / 60;
                                $remaning_times = round($remaning_times_to_hr) . ' ' . 'Hours';
                                if ($remaning_times_to_hr >= 24) {  
                                    $remaning_times_to_hr = round($remaning_times_to_hr / 24);
                                    if($remaning_times_to_hr>=1){
                                        $remaning_times = round($remaning_times_to_hr) . ' ' . 'Days';
                                    }else{
                                        $remaning_times = round($remaning_times_to_hr) . ' ' . 'Day';
                                    }
                                }
                            }
                        }
                        
                        $booking_details['recived_amount'] = $received_amount;
                    }
                   
                    $booking_details['payment_expected_in'] = $expeted_payment_time;
                    $booking_details['time_left'] = $remaning_times == 0 ? "Expired" : $remaning_times;
                } else {
                    $booking_details['recived_amount'] = $received_amount;
                    $booking_details['payment_expected_in'] = '-';
                    $booking_details['time_left'] = '-';
                }
                $booking_details['total_amount'] = $be->total_amount;
                $booking_details['requested_amount'] = $be->paid_amount;
                $booking_details['booking_status'] = $btn_status;
                $booking_details['payment_type'] = $be->payment_type;
                $booking_details['payment_options'] = $payment_options;
                $booking_details['currency_symbol'] = '20B9';
                $booking_details['partner_name'] = isset($be->partner_name)?$be->partner_name:'-';
                $booking_details['sales_executive'] = isset($be->sales_executive_name)?$be->sales_executive_name:'-';
                array_push($all_booking_data, $booking_details);
            }
        }

        if ($all_booking_data) {
            $result = array('status' => 1, "message" => 'Bookings Fetch Successfully', 'data' => $all_booking_data);
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => $failure_message);
            return response()->json($result);
        }
    }

    //====================================Saroj Patel(14-10-22)========================================//

    public function crsBookingReportDownload($hotel_id, $from_date, $to_date, $payment_options, $booking_status,$agent)
    {
        $failure_message = 'Bookings Fetch Failed';
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date));
        $check_be_date = 'invoice_table.booking_date';

        $sales_executive = '';

        if(isset($sales_executive)){
            $sales_executive = $sales_executive;
        }else{
            $sales_executive = 0;
        }

        $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('crs_booking', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
            ->leftjoin('partner_table', 'partner_table.id', '=', 'crs_booking.partner_id')
            ->select(
                'user_table.first_name',
                'user_table.last_name',
                'user_table.user_id',
                'invoice_table.booking_date',
                'invoice_table.hotel_id',
                'invoice_table.pay_to_hotel',
                'invoice_table.booking_status',
                'invoice_table.hotel_name',
                'invoice_table.total_amount',
                'invoice_table.paid_amount',
                'invoice_table.booking_source',
                'invoice_table.invoice_id',
                'crs_booking.booking_time',
                'crs_booking.no_of_rooms',
                'crs_booking.payment_type',
                'crs_booking.payment_link_status',
                'crs_booking.payment_status',
                'crs_booking.is_payment_received',
                'crs_booking.valid_hour',
                'crs_booking.check_in',
                'crs_booking.check_out',
                'crs_booking.partner_id',
                'partner_table.partner_name',
                'crs_booking.expiry_time'
            )->where('invoice_table.hotel_id', '=', $hotel_id)
            ->where('invoice_table.booking_source', '=', 'CRS')
            ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date));

        // 0 = all, 1 = Email with payment link, 2 = Email no payment link, 3 = No email no payment
        if ($payment_options != 0) {
            $be_data =  $be_data->where('crs_booking.payment_type', '=', $payment_options);
        }
        // 0 = all, 1 = Confirm, 3 = Cancelled
        if ($booking_status != 0) {
            $be_data = $be_data->where('invoice_table.booking_status', '=', $booking_status);
        }

        if($agent != 0){
            $be_data = $be_data->where('crs_booking.partner_id', '=', $agent);
        }

        if($sales_executive != 0){
            $be_data = $be_data->where('crs_booking.sales_executive_id', '=', $sales_executive);
        }


        $be_data =  $be_data->get();
        $all_booking_data = [];
        if (sizeof($be_data) > 0) {
            foreach ($be_data as $be) {

                $date1 = date_create($be->check_in);
                $date2 = date_create($be->check_out);
                $diff = date_diff($date1, $date2);
                $no_of_nights = $diff->format("%a");
                if ($no_of_nights == 0) {
                    $no_of_nights = 1;
                }

                if ($be->booking_status == 3) {
                    $btn_status = 'Cancelled';
                } else {
                    $btn_status = 'Confirmed';
                }

                if ($be->payment_type == 1) {
                    $payment_options = 'Email with Payment Link';
                } elseif ($be->payment_type == 2) {
                    $payment_options = 'Email (no payment link)';
                } else {
                    $payment_options = 'No Email No Payment Link';
                }

                $booking_details['booking_id'] = date('dmy', strtotime($be->booking_time)) . $be->invoice_id;
                $booking_details['customer_details'] = strtoupper($be->first_name) . ' ' . strtoupper($be->last_name);
                // $booking_details['no_of_rooms'] = $be->no_of_rooms;
                // $booking_details['nights'] = $no_of_nights;
                $booking_details['display_booking_date'] = date('d M Y h:i a', strtotime($be->booking_time));
                $booking_details['total_amount'] = $be->total_amount;
                $booking_details['requested_amount'] = $be->paid_amount;
                // dd($be->payment_type,$be->invoice_id);
                if ($be->payment_type == 1) {
                    $expiry_time = strtotime($be->expiry_time);
                    $current_time = strtotime(date('Y-m-d H:i:s'));
                    $booking_time = strtotime($be->booking_time);

                    $expeted_payment = $expiry_time - $booking_time;
                    $expeted_payment = $expeted_payment / 60;
                    $expeted_payment_time = round($expeted_payment) . ' ' . 'Minutes';
                    if ($expeted_payment_time >= 60) {
                        $expeted_payment_to_hr = $expeted_payment / 60;
                        if ($expeted_payment_to_hr) {
                            $expeted_payment_time = round($expeted_payment_to_hr) . ' ' . 'Hour';
                        } else {
                            $expeted_payment_time = round($expeted_payment_to_hr) . ' ' . 'Hours';
                        }
                    }
                    if (isset($be->expiry_time) && ($be->expiry_time != 0)  && ($be->payment_link_status == 'valid') && $be->payment_status == 'Confirm') {
                        $expiry_time = strtotime($be->expiry_time);
                        $current_time = strtotime(date('Y-m-d H:i:s'));
                        if (($expiry_time > $current_time)) {
                            $remaning_time = $expiry_time - $current_time;
                            $remaning_time_to_min = round($remaning_time / 60);
                            $remaning_times = $remaning_time_to_min . ' ' . 'Minutes';
                            if ($remaning_times > 60) {
                                $remaning_times_to_hr = $remaning_time_to_min / 60;
                                $remaning_times = round($remaning_times_to_hr) . ' ' . 'Hours';
                            }
                            $booking_details['recived_amount'] = $be->paid_amount;
                        } else {
                            if(($be->payment_link_status == 'valid') && $be->payment_status == 'Confirm'){
                                $remaning_times = 0;
                                $booking_details['recived_amount'] = $be->paid_amount;
                            }else{
                                $remaning_times = 0;
                                $booking_details['recived_amount'] = 0;
                            }
                        }
                    } else {
                        $remaning_times = 0;
                        $booking_details['recived_amount'] = $be->paid_amount;
                    }
                    $booking_details['payment_expected_by'] = $expeted_payment_time;
                    $booking_details['time_left'] = $remaning_times == 0 ? "Expired" : $remaning_times;
                } else {
                    $booking_details['recived_amount'] = $be->paid_amount;
                    $booking_details['payment_expected_by'] = '-';
                    $booking_details['time_left'] = '-';
                }
                $booking_details['booking_status'] = $btn_status;
                $booking_details['payment_options'] = $payment_options;
                $booking_details['partner_name'] = isset($be->partner_name)?$be->partner_name:'-';
                $booking_details['sales_executive'] = isset($be->sales_executive_name)?$be->sales_executive_name:'-';


                array_push($all_booking_data, $booking_details);
            }
        }

       
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=crsBookingReport.csv');
            $output = fopen("php://output", "w");
            fputcsv($output, array('Booking ID', 'Guest Name', 'Booking Date', 'Total amount', 'Requested amount', 'Recived Amount', 'Payment Expected By', 'Time left', 'Booking Status', 'Payment Option','Partner Name','Sales Executive Name'));

            foreach ($all_booking_data as $data) {
                fputcsv($output, $data);
            }
            fclose($output);
    }



    public function partnerList($hotel_id){
        $partnerList = PartnerDetails::where('hotel_id',$hotel_id)->where('is_active',1)->get();

        if ($partnerList) {
            $result = array('status' => 1, "message" => 'Partner Fetch Successfully', 'data' => $partnerList);
            return response()->json($result);
        } else {
            $result = array('status' => 0, "message" => 'Partner Fetch Failed');
            return response()->json($result);
        }

    }

    //=======================================================================================================//

}
