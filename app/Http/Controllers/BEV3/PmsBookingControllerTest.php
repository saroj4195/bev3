<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use App\Packages;
use App\ImageTable;
use App\RoomTypeTable;
use App\CompanyDetails;
use App\HotelInformation;
use App\Invoice;
use App\MasterRoomType;
use App\MasterRatePlan;
use App\BillingDetails;
use App\BeBookingDetailsTable;
use App\User;
use App\UserNew;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\IpAddressService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CurrencyController;


class PmsBookingController extends Controller
{
    protected $invService;
    protected $ipService;
    protected $curency;
    protected $beConfBookingInvUpdate;
    public function __construct(IpAddressService $ipService, CurrencyController $curency, BeConfirmBookingInvUpdateRedirectingController $beConfBookingInvUpdate)
    {
        $this->ipService = $ipService;
        $this->curency = $curency;
        $this->beConfBookingInvUpdate = $beConfBookingInvUpdate;
    }

    public function bookings(string $api_key, Request $request)
    {
        //store the log
        $logpath = storage_path("logs/pms-cart.log" . date("Y-m-d"));
        $logfile = fopen($logpath, "a+");
        fclose($logfile);
        $data = $request->all();
        $booking_details = $data['booking_details'];
        $hotel_id = $booking_details['hotel_id'];
        $cart_info = json_encode($data);
        $logfile = fopen($logpath, "a+");
        fwrite($logfile, "cart data: " . $hotel_id . $cart_info . "\n");
        fclose($logfile);

        $check_in = date('Y-m-d', strtotime($booking_details['checkin_date']));
        $check_out = date('Y-m-d', strtotime($booking_details['checkout_date']));
        $checkin = date_create($check_in);
        $checkout = date_create($check_out);
        $date = date('Y-m-d');
        $diff = date_diff($checkin, $checkout);
        $diff = $diff->format("%a");
        if ($diff == 0) {
            $diff = 1;
        }

        //Checkin date validation
        if ($date > $check_in) {
            $res = array('status' => 0, 'message' => "Check-in date must not be past date.");
            return response()->json($res);
        }

        //Hotel Validation 
        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('company_id', 'gst_slab', 'is_taxable', 'partial_pay_amt')->first();
        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 0, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }

        //User registration
        $company_name = CompanyDetails::where('company_id', $hotel_info->company_id)->first();
        $user_details = $data['user_details'];

        $user_data['first_name'] = $user_details['first_name'];
        $user_data['last_name'] = $user_details['last_name'];
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
        $user_data['company_name'] = $company_name->company_full_name;
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

        //Store invoice details
        $invoice = new Invoice();
        $room_details = $data['room_details'];
        $inv_data = array();
        $inv_data['hotel_id']   = $hotel_id;
        $hotel = $this->getHotelInfo($hotel_id);
        $inv_data['hotel_name'] = $hotel->hotel_name;
        $inv_data['room_type']  = json_encode($this->prepareRoomTypes($room_details));
        $inv_data['ref_no'] = rand() . strtotime("now");
        $inv_data['check_in_out'] = "[" . $check_in . '-' . $check_out . "]";
        $inv_data['booking_date'] = date('Y-m-d H:i:s');
        $inv_data['booking_status'] = 2;
        $inv_data['user_id'] = $user_id;
        $inv_data['user_id_new'] = $user_id_new;
        $company_id = $hotel_info->company_id;
        $getBillingsDetails = BillingDetails::select('product_name')->where('company_id', $company_id)->first();
        $product_info = json_decode($getBillingsDetails->product_name);
        if (in_array('Channel Manager', $product_info)) {
            $inv_data['is_cm'] = 1;
        } else {
            $inv_data['is_cm'] = 0;
        }
        $visitors_ip = $this->ipService->getIPAddress();
        $inv_data['visitors_ip'] = $visitors_ip;
        $inv_data['booking_source'] = "website";
        $inv_data['guest_note'] = $user_details['guest_note'];
        $inv_data['arrival_time'] = $user_details['arrival_time'];
        $inv_data['company_name'] = $user_details['company_name'];
        $inv_data['gstin'] = $user_details['GST_IN'];
        $inv_data['agent_code'] = '';

        //Room price calculation
        $gst_price = 0;
        $total_gst_price = 0;
        $total_price = 0;
        $total_discount = 0;
        $extra_details = [];
        $hotel_booking_details = [];
        $invoice_details_array = [];
        $paid_amount = $booking_details['paid_amount'];
        foreach ($room_details as $details) {
            $room_type_id = $details['room_type_id'];
            $rate_plan_id = $details['rate_plan_id'];
            $total_room_type_price = $details['total_room_type_price'];
            $total_room_type_discount_amount = $details['total_room_type_discount_amount'];
            $total_price += $total_room_type_price - $total_room_type_discount_amount;
            $total_discount += $total_room_type_discount_amount;
            $rooms = $details['rooms'];
            $no_of_rooms = count($rooms);
            foreach ($rooms as $key => $room) {
                $room_rate_per_night = $room['room_rate_per_night'];
                $discount_amount_per_night = $room['discount_amount_per_night'];
                $room_rate_per_night_after_discount = $room_rate_per_night - $discount_amount_per_night;
                //Gst calculation
                if ($hotel_info->is_taxable == 1) {
                    $gst_percentage = $this->checkGSTPercent($room_rate_per_night_after_discount);
                    $per_night_gst_price = $room_rate_per_night_after_discount * $gst_percentage / 100;
                    $total_gst_price += $per_night_gst_price * $diff;
                }
                array_push($extra_details, array($details['room_type_id'] => array($room['adult'], $room['child'])));

                $check_in_data = $check_in;
                for ($i = 0; $i < $diff; $i++) {
                    $d = $check_in_data;

                    //Store the details in invoice details
                    $invoice_details['hotel_id'] = $hotel_id;
                    $invoice_details['user_id'] = $user_id;
                    $invoice_details['room_type_id'] = $details['room_type_id'];
                    $invoice_details['rate_plan_id'] = $details['rate_plan_id'];
                    $invoice_details['date'] = $d;
                    $invoice_details['room_rate'] = $room_rate_per_night;
                    $invoice_details['extra_adult'] = '';
                    $invoice_details['extra_child'] = '';
                    $invoice_details['extra_adult_price'] = '';
                    $invoice_details['extra_child_price'] = '';
                    $invoice_details['discount_price'] = $discount_amount_per_night;
                    $invoice_details['price_after_discount'] = $room_rate_per_night_after_discount;
                    $invoice_details['rooms'] = $key + 1;
                    $invoice_details['gst_price'] = $per_night_gst_price;
                    $invoice_details['total_price'] = $room_rate_per_night + $per_night_gst_price;
                    array_push($invoice_details_array, $invoice_details);

                    $check_in_data = date('Y-m-d', strtotime($d . ' +1 day'));
                }
            }

            //store the data in hotel booking table
            $hotel_booking_data['room_type_id'] = $room_type_id;
            $hotel_booking_data['rooms'] = $no_of_rooms;
            $hotel_booking_data['check_in'] = $check_in;
            $hotel_booking_data['check_out'] = $check_out;
            $hotel_booking_data['booking_status'] = 2; //Intially Un Paid
            $hotel_booking_data['user_id'] = $user_id;
            $hotel_booking_data['booking_date'] = date('Y-m-d');
            $hotel_booking_data['hotel_id'] = $hotel_id;
            array_push($hotel_booking_details, $hotel_booking_data);
        }

        $inv_data['total_amount'] = $total_price + $total_gst_price;
        $inv_data['tax_amount'] = $total_gst_price;
        $inv_data['paid_amount'] = $paid_amount;
        $inv_data['discount_amount'] = $total_discount;
        $inv_data['extra_details'] = json_encode($extra_details);
        $inv_data['paid_service_id'] = '';
        $inv_data['invoice'] = '';

        $result = $invoice->fill($inv_data)->save();
        if ($result) {
            $invoice_id = $invoice->invoice_id;

            foreach ($hotel_booking_details as &$hotel_booking_detail) {
                $hotel_booking_detail['invoice_id'] = $invoice_id;
            }
            HotelBooking::insert($hotel_booking_details);

            foreach ($invoice_details_array as &$invoice_detail) {
                $invoice_detail['invoice_id'] = $invoice_id;
            }
            DayWisePrice::insert($invoice_details_array);

            $inv_data['invoice'] = $this->createInvoice($hotel_id, $room_details, $check_in, $check_out, $inv_data['user_id'], $inv_data['paid_amount'], $inv_data['ref_no']);

            $update_vouchere = invoice::where('invoice_id', $invoice_id)->update(['invoice' => $inv_data['invoice']]);

            $user_data = $this->getUserDetails($inv_data['user_id']);
            $b_invoice_id = base64_encode($invoice_id);
            $invoice_hashData = $invoice_id . '|' . $inv_data['total_amount'] . '|' . $inv_data['paid_amount'] . '|' . $user_data->email_id . '|' . $user_data->mobile . '|' . $b_invoice_id;

            $invoice_secureHash = hash('sha512', $invoice_hashData);
            $res = array("status" => 1, "message" => "Invoice details saved successfully.$invoice_hashData", "invoice_id" => $invoice_id, 'invoice_secureHash' => $invoice_secureHash);

            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://be.bookingjini.com/gems-booking/'.$inv_data['invoice'].'/true/'.$booking_details['send_email'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));
            $response = curl_exec($curl);
            curl_close($curl);

            // $successBooking = $this->successBooking($invoice_id, 'true', $booking_details['send_email'], 'NA', '12345');

            return response()->json($response);
        } else {
            $res = array('status' => -1, "message" => 'Booking Failed');
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }

    public function createInvoice($hotel_id, $room_details, $check_in, $check_out, $user_id, $paid_amount_info, $ref_no)
    {
        $booking_id = "#####";
        $transaction_id = ">>>>>";
        $booking_date = date('Y-m-d');
        $booking_date = date("jS M Y", strtotime($booking_date));
        $hotel_details = $this->getHotelInfo($hotel_id);
        $u = $this->getUserDetails($user_id);
        $dsp_check_in = date("jS M, Y", strtotime($check_in));
        $dsp_check_out = date("jS M, Y", strtotime($check_out));
        $date1 = date("Y-m-d", strtotime($check_in));
        $date2 = date("Y-m-d", strtotime($check_out));
        $diff = abs(strtotime($check_out) - strtotime($check_in));
        if ($diff == 0) {
            $diff = 1;
        }
        $years = floor($diff / (365 * 60 * 60 * 24));
        $months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
        $day = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));
        if ($day == 0) {
            $day = 1;
        }
        $total_adult = 0;
        $total_child = 0;
        $total_cost = 0;
        $all_room_type_name = "";
        $paid_service_details = "";
        $all_rows = "";
        $total_discount_price = 0;
        $total_price_after_discount = 0;
        $total_tax = 0;
        $display_discount = 0.00;
        $other_tax_arr = array();
        $arrival_time = '';
        $guest_note = '';

        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;
        $currency_code = $this->getBaseCurrency($hotel_id)->hex_code;


        $hotel_info = HotelInformation::join('company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->leftjoin('image_table', 'company_table.logo', '=', 'image_table.image_id')
            ->select('image_table.image_name', 'hotels_table.is_taxable')
            ->where('hotels_table.hotel_id', $hotel_id)
            ->first();
        if (isset($hotel_info->image_name)) {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $hotel_info->image_name;
        } else {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';
        }
        $bookingjini_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.svg';

        $extra_adult_price = 0;
        $extra_child_price = 0;
        foreach ($room_details as $cartItem) {
            $room_type_id = $cartItem['room_type_id'];
            $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
            $room_type_array = MasterRoomType::select('room_type')->where($conditions)->first();
            $room_type = $room_type_array['room_type'];
            $rate_plan_id = $cartItem['rate_plan_id'];
            $conditions = array('rate_plan_id' => $rate_plan_id, 'is_trash' => 0);
            $rate_plan_id_array = MasterRatePlan::select('plan_name')->where($conditions)->first();
            $rate_plan = $rate_plan_id_array['plan_name'];

            $i = 1;
            $total_price = 0;
            $all_room_type_name .= ',' . sizeof($cartItem['rooms']) . ' ' . $room_type;
            $every_room_type = "";
            $all_rows .= '<tr><td rowspan="' . sizeof($cartItem['rooms']) . '" colspan="2">' . $room_type . '(' . $rate_plan . ')</td>';

            $booking_details_room_rate = array();
            $booking_details_extra_adult = array();
            $booking_details_extra_child = array();
            $booking_details_adult = array();
            $booking_details_child = array();
            $booking_details_tax_amount = array();
            $booking_details_discount_price = array();
            $sizeof_rooms = sizeof($cartItem['rooms']);
            foreach ($cartItem['rooms'] as $rooms) {
                $ind_total_price = 0;
                $total_adult += $rooms['adult'];
                $total_child += $rooms['child'];
                $ind_total_price = $rooms['room_rate_per_night'] * $sizeof_rooms;

                if ($i == 1) {
                    $all_rows = $all_rows . '<td  align="center">' . $i . '</td>
                <td  align="center"> ' . $currency_code . $rooms['room_rate_per_night'] . '</td>
                <td  align="center">' . $extra_adult_price . '</td>
                <td  align="center">' . $extra_child_price . '</td>
                <td  align="center">' . $day . '</td>
                <td  align="center">' . $currency_code . $ind_total_price . '</td>
                 </tr>';
                } else {
                    $all_rows = $all_rows . '<tr><td  align="center">' . $i . '</td>
                <td  align="center">' . $currency_code . $rooms['room_rate_per_night'] . '</td>
                <td  align="center">' . $extra_adult_price . '</td>
                <td  align="center">' . $extra_child_price . '</td>
                <td  align="center">' . $day . '</td>
                <td  align="center">' . $currency_code . $ind_total_price . '</td>
                 </tr>';
                }

                $i++;
                $total_price += $ind_total_price;
                // if($hotel_id == 2319){
                $booking_details_rooms = sizeof($cartItem['rooms']);
                $booking_details_room_rate_dlt  = $rooms['room_rate_per_night'];
                $booking_details_room_rate[] = $rooms['room_rate_per_night'];
                $booking_details_extra_adult[] = $extra_adult_price;
                $booking_details_extra_child[] = $extra_child_price;
                $booking_details_adult[] = $rooms['adult'];
                $booking_details_child[] = $rooms['child'];
                $booking_details_discount_price_info = $rooms['discount_amount_per_night'];
                $booking_details_discount_price[] = $rooms['discount_amount_per_night'];

                if ($hotel_info->is_taxable == 1) {
                    $getGstPrice = $this->getGstPricePerRoom($booking_details_room_rate_dlt, $booking_details_discount_price_info);
                } else {
                    $getGstPrice = 0;
                }

                $booking_details_tax_amount[] = round($getGstPrice, 2);
            }
            // if($hotel_id == 2319){
            $condition = array('hotel_id' => $hotel_id, 'room_type_id' => $cartItem['room_type_id'], 'rate_plan_id' => $cartItem['rate_plan_id'], 'ref_no' => $ref_no);
            $check_existance = BeBookingDetailsTable::select('id')->where($condition)->first();
            if (!$check_existance) {
                $be_bookings = new BeBookingDetailsTable();
                $room_price_dtl = implode(',', $booking_details_room_rate);
                $extra_adult_dtl = implode(',', $booking_details_extra_adult);
                $extra_child_dtl = implode(',', $booking_details_extra_child);
                $gst_dtl = implode(',', $booking_details_tax_amount);
                $adult_dtl = implode(',', $booking_details_adult);
                $child_dtl = implode(',', $booking_details_child);
                $discount_price = implode(',', $booking_details_discount_price);
                $booking_details['hotel_id'] = $hotel_id;
                $booking_details['ref_no'] = $ref_no;
                $booking_details['room_type'] = $room_type_array['room_type'];
                $booking_details['rooms'] = $booking_details_rooms;
                $booking_details['room_rate'] = $room_price_dtl;
                $booking_details['extra_adult'] = $extra_adult_dtl;
                $booking_details['extra_child'] =  $extra_child_dtl;
                $booking_details['discount_price'] =  $discount_price;
                $booking_details['adult'] = $adult_dtl;
                $booking_details['child'] = $child_dtl;
                $booking_details['room_type_id'] = $cartItem['room_type_id'];
                $booking_details['tax_amount'] = $gst_dtl;
                $booking_details['rate_plan_name'] = $rate_plan_id_array['plan_name'];
                $booking_details['rate_plan_id'] = $cartItem['rate_plan_id'];
                if ($be_bookings->fill($booking_details)->save()) {
                    $res = array('status' => 1, 'message' => 'booking details save successfully');
                }
            }
            // }
            $total_cost += $total_price;
            $total_discount_price += $cartItem['total_room_type_discount_amount'];
            $total_discount_price = number_format((float)$total_discount_price, 2, '.', '');
            $total_price_after_discount += ($total_price - $cartItem['total_room_type_discount_amount']);
            $total_price_after_discount = number_format((float)$total_price_after_discount, 2, '.', '');
            if ($cartItem['tax'][0]['total_room_type_gst_price'] != 0) {
                $total_tax  += $cartItem['tax'][0]['total_room_type_gst_price'];
            } else {
                foreach ($cartItem['tax'][0]['other_tax'] as $key => $other_tax) {
                    $total_tax += $other_tax['tax_price'];
                    $other_tax_arr[$key]['tax_name'] = $other_tax['tax_name'];
                    if (!isset($other_tax_arr[$key]['tax_price'])) {
                        $other_tax_arr[$key]['tax_price'] = 0;
                        $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                    } else {
                        $other_tax_arr[$key]['tax_price'] += $other_tax['tax_price'];
                    }
                }
            }
            $total_tax = number_format((float)$total_tax, 2, '.', '');
            $every_room_type = $every_room_type . '
        <tr>
            <td colspan="7" align="right" style="font-weight: bold;">Total &nbsp;</td>
            <td align="center" style="font-weight: bold;">' . $currency_code . $total_price . '</td>
        </tr>';

            $all_rows = $all_rows . $every_room_type;
        }
        if ($total_discount_price > 0) {
            $display_discount = $total_discount_price;
        }

        $check_tax_applicable = HotelInformation::select('hotels_table.is_taxable', 'hotels_table.pay_at_hotel', 'hotels_table.state_id', 'state_table.tax_name')
            ->leftjoin('kernel.state_table', 'kernel.hotels_table.state_id', '=', 'state_table.state_id')
            ->where('hotels_table.hotel_id', $hotel_id)->first();
        $gst_tax_details = "";
        if ($baseCurrency == 'INR') {
            if ($check_tax_applicable->is_taxable == 1) {
                $gst_tax_details = '<tr>
            <td colspan="7" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
            <td align="center">' . $currency_code . $total_tax . '</td>
            </tr>';
            }
        } else {
            if ($check_tax_applicable->is_taxable == 1) {
                if (isset($check_tax_applicable->tax_name) && $check_tax_applicable->tax_name != '') {
                    $gst_tax_details = '<tr>
                <td colspan="7" align="right">' . $check_tax_applicable->tax_name . '&nbsp;&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . $total_tax . '</td>
                </tr>';
                }
            }
        }

        $other_tax_details = "";
        if (sizeof($other_tax_arr) > 0) {
            foreach ($other_tax_arr as $other_tax) {
                $other_tax_details = $other_tax_details . '<tr>
           <td colspan="7" style="text-align:right;">' . $other_tax['tax_name'] . '&nbsp;&nbsp;</td>
           <td style="text-align:center;">' . $currency_code . $other_tax['tax_price'] . '</td>
           <tr>';
            }
        }
        $total_amt = $total_price_after_discount;
        $total_amt = number_format((float)$total_amt, 2, '.', '');
        if ($baseCurrency == 'INR') {
            if ($check_tax_applicable->is_taxable == 1) {
                $total = $total_amt + $total_tax;
            } else {
                $total = $total_amt;
            }
        } else {
            if ($check_tax_applicable->is_taxable == 1) {
                $total = $total_amt + $total_tax;
            } else {
                $total = $total_amt;
            }
        }

        $total = number_format((float)$total, 2, '.', '');
        $paid_amount = $paid_amount_info;


        $due_amount_info = $total - $paid_amount;
        $due_amount_info = number_format((float)$due_amount_info, 2, '.', '');
        $body = '<html>
        <head>
        <style>
            html{
                font-family: Arial, Helvetica, sans-serif;
            }
            table, td {
                height: 26px;
            }
            table, th {
                height: 35px;
            }
            p{
                color: #000000;
            }
        </style>
        </head>
        <body style="color:#000;">
        <div style="margin: 0px auto;">
            <table width="100%" align="center">
        <tr>
        <td style="border: 1px #0; padding: 4%; border-style: double;">
            <table width="100%" border="0">
                <tr>
                    <th><img src="' . $hotel_logo . '" style="float:left;height:80px;weight:100px"></th>
                </tr>
                <tr>
                    <th colspan="2" valign="middle" style="font-size: 23px;"><u>BOOKING CONFIRMATION</u></th>
                </tr>
                <tr>
                <td><b style="color: #ffffff;">*</b></td>
                </tr>
                <tr>
                    <td>
                        <div>
                            <div style="font-weight: bold; font-size: 22px; color:#fff; background-color: #1d99b5; padding: 5px;">' . $hotel_details['hotel_name'] . '</div>
                        </div>
                    </td>
                    <td style="font-size: 16px;font-weight: bold;" align="right">BOOKING ID : ' . $booking_id . '</td>
                </tr>
                <tr>
                    <td colspan="2"><b style="color: #ffffff;">*</b></td>
                </tr>';
        $body .=
            '<tr>
                    <td colspan="2"><b>Dear ' . $u->first_name . ' ' . $u->last_name . ',</b></td>
                </tr>';

        $body .= ' <tr>
                        <td colspan="2" style="font-size:17px;"><b>We hope this email finds you well. Thank you for choosing ' . $hotel_details->hotel_name . ' as your property of choice for your visit and booking through our hotel\'s website. Your booking confirmation details have been provided below:</b></td>
                    </tr>';
        $body .= '<tr>
                    <td colspan="2"><b style="color: #ffffff;">*</b></td>
                </tr>
        </table>

         <table width="100%" border="1" style="border-collapse: collapse;">
             <th colspan="2" bgcolor="#ec8849">BOOKING DETAILS</th>
             <tr>
                 <td >PROPERTY & PLACE</td>
                 <td>' . $hotel_details->hotel_name . '</td>
             </tr>
             <tr>
                 <td width="45%">NAME OF PRIMARY GUEST</td>
                 <td>' . $u->first_name . ' ' . $u->last_name . '</td>
             </tr>
             <tr>
                 <td>PHONE NUMBER</td>
                 <td>' . $u->mobile . '</td>
             </tr>
             <tr>
                 <td>EMAIL ID</td>
                 <td>' . $u->email_id . '</td>
             </tr>
             <tr>
                 <td>ADDRESS</td>
                 <td>' . $u->address . ',' . $u->city . ',' . $u->state . ',' . $u->country . '</td>
            </tr>
             <tr>
         <td>BOOKING DATE</td>
         <td>' . $booking_date . '</td>
            </tr>
                    <tr>
                <td>CHECK IN DATE</td>
                <td>' . $dsp_check_in . '</td>
            </tr>
        
                    <tr>
                <td>CHECK OUT DATE</td>
                <td>' . $dsp_check_out . '</td>
            </tr>
                    <tr>
                <td>CHECK IN TIME</td>
                <td>' . $hotel_details->check_in . '</td>
            </tr>
                    <tr>
                <td>CHECK OUT TIME</td>
                <td>' . $hotel_details->check_out . '</td>
            </tr>
            <tr>
            <td>EXPECTED ARRIVAL TIME</td>
            <td>' . $arrival_time . '</td>
            </tr>
                        <tr>
                <td>TOTAL ADULT</td>
                <td>' . $total_adult . '</td>
            </tr>
                        <tr>
                <td>TOTAL CHILDREN</td>
                <td>' . $total_child . '</td>
            </tr>
                    <tr>
                <td>NUMBER OF NIGHTS</td>
                <td>' . $day . '</td>
            </tr>
                    <tr>
                <td>NO. & TYPES OF ACCOMMODATIONS BOOKED</td>
                <td>' . substr($all_room_type_name, 1) . '</td>
            </tr>

            </table>

                <table width="100%" border="1" style="border-collapse: collapse;">
                    <tr>
            <th colspan="8" valign="middle" height="" style="font-size: 20px;">TARIFF APPLICABLE</th>
            </tr>
                    <tr>
                <th colspan="2" bgcolor="#ec8849" align="center">Room Type</th>
                <th bgcolor="#ec8849" align="center">Rooms</th>
                <th bgcolor="#ec8849" align="center">Room Rate</th>
                <th bgcolor="#ec8849" align="center">Extra Adult Price</th>
                <th bgcolor="#ec8849" align="center">Extra Child Price</th>
                <th bgcolor="#ec8849" align="center">Days</th>
                <th bgcolor="#ec8849" align="center">Total Price</th>
            </tr>
                    ' . $all_rows . '
            <tr>
                <td colspan="8"><p style="color: #ffffff;">*</p></td>
            </tr>
                    <tr>
                <td colspan="7" align="right">Total Room Rate&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . $total_cost . '</td>
            </tr>
            
            <tr>
                <td colspan="7" align="right">Discount Coupon &nbsp;&nbsp;' . $total_discount_price . '</td>
                <td align="center">' . $currency_code . $display_discount . '</td>
            </tr>

            <tr>
                <td colspan="7" align="right">After Discount&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . $total_price_after_discount . '</td>
            </tr>
            ' . $paid_service_details . '
            <tr>
                <td colspan="7" align="right">Total room rate with paid services &nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . $total_amt . '</td>
            </tr>
                ' . $gst_tax_details . '
                ' . $other_tax_details . '
            <tr>
                <td colspan="7" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
                <td align="center">' . $currency_code . $total . '</td>
            </tr>';
        $body .= '<tr>
                <td colspan="7" align="right">Total Paid Amount&nbsp;&nbsp;</td>
                <td align="center">' . $currency_code . '<span id="pd_amt">' . $paid_amount . '</span></td>
                </tr>
                <tr>
                    <td colspan="7" align="right">Pay at hotel&nbsp;&nbsp;</td>
                    <td align="center">' . $currency_code . '<span id="du_amt">' . $due_amount_info . '</span></td>
                </tr>
                <tr>
                    <td colspan="8"><p style="color: #ffffff;">* </p></td>
                </tr>
                <tr>
                    <td colspan="11" style="font-weight:bold;">Guest Note : ' . $guest_note . '</td>
                </tr>';


        $body .= '</table>

                <table width="100%" border="0">
                    <tr>
            <th colspan="2" style="font-size: 21px; color: #ffffff;"><u>*</u></th>
            </tr>
                    <tr>
                <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;">We are looking forward to hosting you at ' . $hotel_details->hotel_name . '.</span></td>
            </tr>
            <tr>
                <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
            </tr>

                    <tr>
                <td colspan="2"><span style="color: #000; font-weight: bold;">Regards,<br />
                    Reservation Team<br />
                    ' . $hotel_details->hotel_name . '<br />
                    ' . $hotel_details->hotel_address . '<br />
                    Mob   : ' . $hotel_details->mobile . '<br />
                    Email : ' . $hotel_details->email_id . '</span>
                </td>
            </tr>
            <tr>
                <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
            </tr>
            <tr>
                <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Note</u> :</span> Taxes applicable may change subject to govt policy at the time of check in.</td>
            </tr>
            <tr>
                <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
            </tr>
            <!--tr>
                <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Terms & Conditions</u> :</span></td>
            </tr>
            <tr>
                <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
            </tr-->
            <!--<tr>
                <td colspan="2"><span style="color:#f00"><li>On Any 100% Cancellation Policy there will be a 3% mandatory deduction from the booking amount due to payment gateway charges.</li></span></td>
            </tr>-->
            <tr>
                <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->terms_and_cond . '</span></td>
            </tr>
            <tr>
                <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
            </tr>
            <!--tr>
                <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Cancellation Policy</u> :</span></td>
            </tr>
            <tr>
                <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
            </tr-->';
        if ($hotel_details->cancel_policy != "") {
            $body .= '<tr>
                <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->cancel_policy . '</span></td>
            </tr>';
        } else {
            $body .= '<tr>
                <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->hotel_policy . '</span></td>
            </tr>';
        }
        '<tr>
                <td style="width:8%"><span>Powered by : </span>
                </td>
                <td style="width:92%">
                    <img style="width:13%" src="' . $bookingjini_logo . '">
                </td>
            </tr>
            </table>
            </td>
            </tr>
            </table>
            </div>
            </body>
            </html>';
        return $body;
    }

    public function successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid)
    {
        $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
        $invoice_details->booking_status = 1;
        $invoice_details->booking_source = 'GEMS';

        if ($invoice_details->save()) {
            $hotel_booking_model = HotelBooking::where('invoice_id', $invoice_id)->get();

            foreach ($hotel_booking_model as $hbm) {
                $period     = new \DatePeriod(
                    new \DateTime($hbm->check_in),
                    new \DateInterval('P1D'),
                    new \DateTime($hbm->check_out)
                );
                foreach ($period as $value) {
                    $index = $value->format('Y-m-d');
                    $check = DynamicPricingCurrentInventory::select('no_of_rooms')
                        ->where('hotel_id', $hbm->hotel_id)
                        ->where('room_type_id', $hbm->room_type_id)
                        ->where('ota_id', -1)
                        ->where('stay_day', $index)
                        ->orderBy('id', 'DESC')
                        ->first();

                    if ($check->no_of_rooms <= 0) {
                        $update = Invoice::where('invoice_id', $invoice_id)->where('booking_status', 1)->update(['booking_status' => 2]);
                        $res = array('status' => 0, "message" => "Booking Failed");
                        return response()->json($res);
                    }
                }
            }

            $update_inv_cm = array();
            foreach ($hotel_booking_model as $hbm) {
                $hbm->booking_status = 1;
                $hbm->save();

                if ($invoice_details->is_cm == 1) {
                    $update_inv_cm[] = $this->updateInventoryCM($hbm, $invoice_details);
                    $resp = $this->updateInventory($hbm, $invoice_details);
                } else {
                    $resp = $this->updateInventory($hbm, $invoice_details);
                }
            }
            if ($invoice_details->is_cm == 1) {
                if ($update_inv_cm) {
                    foreach ($update_inv_cm as $rm_val) {
                        $invoice_id = $rm_val['invoice_id'];
                        $hotel_id = $rm_val['hotel_id'];
                        $booking_details = $rm_val['booking_details'];
                        $room_type[] = $rm_val['room_type'];
                        $rooms_qty[] = $rm_val['rooms_qty'];
                        $booking_status = $rm_val['booking_status'];
                        $modify_status = $rm_val['modify_status'];
                    }
                    $bucketupdate = $this->beConfBookingInvUpdate->bookingConfirm($invoice_id, $hotel_id, $booking_details, $room_type, $rooms_qty, $booking_status, $modify_status);
                }
            }

            if ($payment_mode == "true") {
                $this->invoiceMail($invoice_id);
            }

            $getPMS_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'GEMS')->first();
            $hotel_info = explode(',', $getPMS_details->hotels);
            if (in_array($invoice_details->hotel_id, $hotel_info)) {
                $pushBookignToGems = $this->pushBookingToGems($invoice_id, $mihpayid);
            }
        }

        $hotel_id = $invoice_details->hotel_id;
        $user_id = $invoice_details->user_id;
        $user_data = User::where('user_id', $user_id)->orderBy('user_id', 'DESC')->first();

        $transaction_det['payment_mode'] = $payment_mode;
        $transaction_det['invoice_id'] = $invoice_id;
        $transaction_det['email_id'] = isset($user_data->email_id) ? $user_data->email_id : 'test@gmail.com';
        $transaction_det['transaction_id'] = $txnid;
        $transaction_det['payment_id'] = $mihpayid;
        $transaction_det['secure_hash'] = $hash;

        $result = OnlineTransactionDetail::updateOrInsert(
            ['invoice_id' => $invoice_id],
            $transaction_det
        );

        if ($result) {
            $hotel_details   = HotelInformation::where('hotel_id', $hotel_id)->first();

            $booking_id = date('dmy') . $invoice_id;
            if (isset($hotel_details->whatsapp_notification_enabled) && $hotel_details->whatsapp_notification_enabled == 1) {
                $user_details = User::where('user_id', $invoice_details->user_id)->select('first_name', 'mobile')->first();

                $message = array(
                    "hotel_name" => $hotel_details->hotel_name,
                    "guest_name" => $user_details->first_name,
                    "phone_number" => $user_details->mobile,
                    "booking_id" => $booking_id,
                    "booking_voucher_url" => 'https://pms.bookingjini.com/booking-voucher/' . $booking_id . '/' . $invoice_details->booking_source
                );

                $to1 = '8073196221';
                $to2 = '91' . $hotel_details->whatsapp_no;
                $whatsapp_sent = $this->sendWhatsAppBookingNotificationToHoteler($to1, $message);
                $whatsapp_sent = $this->sendWhatsAppBookingNotificationToHoteler($to2, $message);
            }
        }

        $res = array('status' => 1, "message" => "Booking Succesfull");
        return response()->json($res);
    }

    public  function invoiceMail($id)
    {
        $hotel_info = new HotelInformation();
        $invoice        = $this->successInvoice($id);
        $invoice = $invoice[0];
        $booking_id     = date("dmy", strtotime($invoice->booking_date)) . str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
        $u = $this->getUserDetails($invoice->user_id);
        $get_transection_details = OnlineTransactionDetail::select('payment_id')->where('invoice_id', $id)->orderBy('tr_id', 'DESC')->first();
        $user_email_id = $u['email_id'];
        $hotel_contact = $hotel_info->getEmailId($invoice->hotel_id);
        $hotel_email_id = $hotel_contact['email_id'];
        $hotel_mobile = $hotel_contact['mobile'];
        $body           = $invoice->invoice;
        if ($id == 80661) {
            $subject        = $invoice->hotel_name . " Booking Cancelation";
            $body           = str_replace("Confirmation", 'Cancelation', $body);
            $body           = str_replace("CONFIRMATION", 'CANCELLATION', $body);
        } else {
            $subject        = $invoice->hotel_name . " Booking Confirmation";
        }

        $body           = str_replace("#####", $booking_id, $body);
        if (isset($get_transection_details->payment_id)) {
            $body           = str_replace(">>>>>", $get_transection_details->payment_id, $body);
        } else {
            $body           = str_replace(">>>>>", $booking_id, $body);
        }
        $hotel_id       = $invoice->hotel_id;
        $invoice_id     = $invoice->invoice_id;
        if ($hotel_id == 2065 || $hotel_id == 2881 || $hotel_id == 2882 || $hotel_id == 2883 || $hotel_id == 2884 || $hotel_id == 2885 || $hotel_id == 2886) {
            $coupon_code    = isset($invoice->agent_code) ? $invoice->agent_code : 'NA';
            if ($coupon_code != 'NA') {
                $get_email_id =  Coupons::select('agent_email_id')
                    ->where('hotel_id', $hotel_id)
                    ->where('coupon_code', '=', $coupon_code)
                    ->first();
                if (isset($get_email_id->agent_email_id) && $get_email_id->agent_email_id) {
                    $user_email_id = $get_email_id->agent_email_id;
                }
            }
        }
        $serial_no      = 0;
        $transection_id = 0;
        if ($hotel_id == 2065) {
            $serial_code = '02';
            $save_info = DB::table('eco_retreat_bhitarkanika')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = isset($save_info->id) ? $save_info->id : 0;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = isset($save_info->transaction_id) ? $save_info->transaction_id : 0;
        }
        if ($hotel_id == 2881) {
            $serial_code = '01';
            $save_info = DB::table('eco_retreat_konark')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2882) {
            $serial_code = '03';
            $save_info = DB::table('eco_retreat_satkosia')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2883) {
            $serial_code = '05';
            $save_info = DB::table('eco_retreat_daringbadi')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2884) {
            $serial_code = '04';
            $save_info = DB::table('eco_retreat_hirakud')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2885) {
            $serial_code = '06';
            $save_info = DB::table('eco_retreat_sonapur')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        if ($hotel_id == 2886) {
            $serial_code = '07';
            $save_info = DB::table('eco_retreat_koraput')->select('id', 'transaction_id')->where('invoice_id', $invoice_id)->first();
            $id_info         = $save_info->id;
            $make_four_digit = str_pad($id_info, 4, "0", STR_PAD_LEFT);
            $serial_no       = $serial_code . $make_four_digit;
            $transection_id  = $save_info->transaction_id;
        }
        $body           = str_replace("*****", $serial_no, $body);
        $body           = str_replace("@@@@@", $transection_id, $body);
        $body_info      = array('invoice' => $body);
        $updated_inv_info = Invoice::where('invoice_id', $invoice_id)->update($body_info);

        if ($this->sendMail($user_email_id, $body, $subject, $hotel_email_id, $invoice->hotel_name, $invoice->hotel_id)) {
            $to = $u['mobile'];
            $hotel_id = $invoice->hotel_id;
            if ($hotel_id == 2065 || $hotel_id == 2881 || $hotel_id == 2882 || $hotel_id == 2883 || $hotel_id == 2884 || $hotel_id == 2885 || $hotel_id == 2886) {
                $hotelName = $invoice->hotel_name;
                $bookingDate = date('d M Y', strtotime($invoice->booking_date));
                $guestName = $u['first_name'];
                $bookingID = $booking_id;
                $messageToSend = "Hi $guestName, Successfully booked for $hotelName on $bookingDate with Booking Id: $bookingID. Regards, OTDCHO";
                $otdc_mob = '7008839041';
                $messageToSend = urlencode($messageToSend);
                $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?userid=531&password=oYSeaxIVK9UPgvG0&sender=OTDCHO&to=$to,$otdc_mob&message=$messageToSend&reqid=1&format={json|text}&route_id=3";
                // $ch = curl_init($smsURL);
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // $res = curl_exec($ch);
                // curl_close($ch);
            } else {
                $msg = "Your transaction has been successful(Booking ID- " . $booking_id . "). For more details kindly check your mail ID given at the time of booking.";
                if ($this->sendSMS($to, $msg)) {
                    $to = $hotel_mobile;
                    $msg = "You have got new booking From Bookingjini(Booking ID- " . $booking_id . "). For more details kindly check registered email ID.";
                    $this->sendSMS($to, $msg);
                }
                if ($invoice->hotel_id == 1602) {
                    $sendMailtoAgent = $this->agentMail($id, $hotel_email_id, $invoice->hotel_name, $booking_id);
                }
            }
            return true;
        }
    }

    public function pushBookingToGems($invoice_id, $gems)
    {
        $all_bookings = array();
        $getBookingDetails = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->select('hotel_booking.user_id', 'invoice_table.room_type', 'invoice_table.ref_no', 'invoice_table.extra_details', 'invoice_table.booking_date', 'invoice_table.invoice_id', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.hotel_name', 'invoice_table.hotel_id', 'company_table.currency', 'invoice_table.tax_amount', 'invoice_table.discount_amount', 'invoice_table.booking_status', 'invoice_table.modify_status', 'invoice_table.booking_source', 'invoice_table.arrival_time', 'invoice_table.guest_note')
            ->distinct('hotel_booking.user_id')
            ->where('invoice_table.invoice_id', $invoice_id)
            ->where('invoice_table.ref_no', '!=', 'offline')
            ->orderBy('invoice_table.invoice_id', 'ASC')
            ->get();
        $otdc_room_type_id = array();
        $otdc_active_status = 0;
        $otdc_info_count = 0;
        $otdc_modify_status = '';
        foreach ($getBookingDetails as $key => $bk_details) {
            $rooms          = array();
            $user_id        = $bk_details->user_id;
            $ref_no         = $bk_details->ref_no;

            $User_Details   = $this->userInfo($user_id);
            $Booked_Rooms   = $this->noOfBookings($invoice_id);

            $date1 = date_create($Booked_Rooms[0]['check_out']);
            $date2 = date_create($Booked_Rooms[0]['check_in']);
            $diff = date_diff($date1, $date2);
            $no_of_nights = $diff->format("%a");

            $booking_date   = $bk_details->booking_date;
            $booking_id     = date("dmy", strtotime($booking_date)) . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

            if ($ref_no == 'offline') {
                $mode_of_payment = 'Offline';
            } else {
                $mode_of_payment = 'Online';
            }
            $room_type_plan = explode(",", $bk_details->room_type);
            $plan = array();
            for ($i = 0; $i < sizeof($room_type_plan); $i++) {
                $plan[] = substr($room_type_plan[$i], -5, -3);
            }
            $extra = json_decode($bk_details->extra_details);

            $k = 0;
            $getRoomDetails = BeBookingDetailsTable::select('*')->where('ref_no', $bk_details->ref_no)->get();
            if ($otdc_info_count == 0) {
                $get_hotel_code = CmOtaDetails::where('hotel_id', $bk_details->hotel_id)->where('ota_name', 'OTDC')->first();
                if ($get_hotel_code) {
                    $otdc_active_status = 1;
                    $otdc_info_count = 1;
                }
            }
            foreach ($getRoomDetails as $rm_key => $getRoom) {
                // if($bk_details->hotel_id  == 2319){
                $room_info          = array();
                $total_rooms        = $getRoom->rooms;
                $adult_info         = explode(',', $getRoom->adult);
                $child_info         = explode(',', $getRoom->child);
                $room_rate_info     = explode(',', $getRoom->room_rate);
                $tax_amount_info    = explode(',', $getRoom->tax_amount);
                $extra_adult_info   = explode(',', $getRoom->extra_adult);
                $extra_child_info   = explode(',', $getRoom->extra_child);
                $discount_price     = explode(',', $getRoom->discount_price);

                $total_room_rate = 0;
                $total_gst_amount = 0;
                $extra_adult_amount = 0;
                $extra_child_amount = 0;
                $total_adult = 0;
                $total_child = 0;
                for ($i = 0; $i < $total_rooms; $i++) {
                    if (isset($room_rate_info) && sizeof($room_rate_info) == 1 && $total_rooms > 1) {
                        continue;
                    }
                    if (isset($discount_price[$i]) && $discount_price[$i] != "") {
                        $room_rate_info_dlt = $room_rate_info[$i] - $discount_price[$i];
                    } else {
                        $room_rate_info_dlt = $room_rate_info[$i];
                    }
                    $room_info[] = array(
                        "ind_room_rate" => $room_rate_info_dlt,
                        "ind_tax_amount" => $tax_amount_info[$i],
                        "ind_extra_adult" => $extra_adult_info[$i],
                        "ind_extra_child" => $extra_child_info[$i],
                        "ind_adult_no" => $adult_info[$i],
                        "ind_child_no" => $child_info[$i]
                    );
                    $total_room_rate += (float)$room_rate_info_dlt;
                    $total_gst_amount += (float)$tax_amount_info[$i];
                    $extra_adult_amount += (float)$extra_adult_info[$i];
                    $extra_child_amount += (float)$extra_child_info[$i];
                    $total_adult += (int)$adult_info[$i];
                    $total_child += (int)$child_info[$i];
                }
                $rooms[] = array(
                    "room_type_id"          => $getRoom->room_type_id,
                    "room_type_name"        => $getRoom->room_type,
                    "no_of_rooms"           => $getRoom->rooms,
                    "room_rate"             => $total_room_rate,
                    "tax_amount"            => $total_gst_amount,
                    "plan"                  => $getRoom->rate_plan_name,
                    "adult"                 => $total_adult,
                    "child"                 => $total_child,
                    "extra_adult_rate"      => $extra_adult_amount,
                    "extra_child_rate"      => $extra_child_amount,
                    "rooms"                 => $room_info
                );
                if ($otdc_active_status == 1) {
                    $get_otdc_room_id = CmOtaRoomTypeSynchronizeRead::where('hotel_id', $bk_details->hotel_id)
                        ->where('room_type_id', $getRoom->room_type_id)
                        ->where('ota_type_id', $get_hotel_code->ota_id)
                        ->first();
                    if (in_array($get_otdc_room_id->ota_room_type, $otdc_room_type_id)) {
                        $otdc_index = array_search($get_otdc_room_id->ota_room_type, $otdc_room_type_id);
                        $otdc_rooms[$otdc_index] = $otdc_rooms[$otdc_index] + $getRoom->rooms;
                    } else {
                        $otdc_room_type[] = $getRoom->rooms . ' ' . $get_otdc_room_id->ota_room_type_name;
                        $otdc_room_type_id[] = $get_otdc_room_id->ota_room_type;
                        $otdc_rooms[] = $getRoom->rooms;
                    }
                }
            }
            $user_info = array(
                "user_name"             => $User_Details['first_name'] . ' ' . $User_Details['last_name'],
                "mobile"                => $User_Details['mobile'],
                "email"                 => $User_Details['email_id'],
                "address"               => $User_Details['address'],
                "zip_code"              => $User_Details['zip_code'],
                "country"               => $User_Details['country'],
                "state"                 => $User_Details['state'],
                "city"                  => $User_Details['city'],
                "GSTIN"                 => $User_Details['GSTIN'],
                "company_name"          => $User_Details['company_name'],
                "arrival_time"          => $bk_details->arrival_time,
                "guest_note"            => $bk_details->guest_note
            );
            if ($bk_details->booking_status == 1) {
                $booking_status = 'confirmed';
            } else if ($bk_details->booking_status == 1 && $bk_details->modify_status == 1) {
                $booking_status = 'modified';
            } else if ($bk_details->booking_status == 3) {
                $booking_status = 'cancelled';
            }
            if (isset($bk_details->booking_source) && $bk_details->booking_source == 'CRS') {
                $discount = 0;
            } else {
                $discount = $bk_details->discount_amount;
            }
            if ($gems == 'true' || $gems == 'crs') {
                $Bookings = array(
                    "date_of_booking"       => $booking_date,
                    "hotel_id"              => $bk_details->hotel_id,
                    "hotel_name"            => $bk_details->hotel_name,
                    "check_in"              => $Booked_Rooms[0]['check_in'],
                    "check_out"             => $Booked_Rooms[0]['check_out'],
                    "booking_id"            => $booking_id,
                    "mode_of_payment"       => $mode_of_payment,
                    "grand_total"           => $bk_details->total_amount,
                    "collection_amount"     => 0,
                    "currency"              => $bk_details->currency,
                    "paid_amount"           => 0,
                    "tax_amount"            => $bk_details->tax_amount,
                    "discount_amount"       => 0,
                    "channel"               => "Bookingjini",
                    // "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1016869990agoda.png",
                    "status"                => $booking_status
                );
            } else {
                $Bookings = array(
                    "date_of_booking"       => $booking_date,
                    "hotel_id"              => $bk_details->hotel_id,
                    "hotel_name"            => $bk_details->hotel_name,
                    "check_in"              => $Booked_Rooms[0]['check_in'],
                    "check_out"             => $Booked_Rooms[0]['check_out'],
                    "booking_id"            => $booking_id,
                    "mode_of_payment"       => $mode_of_payment,
                    "grand_total"           => $bk_details->total_amount,
                    "collection_amount"     => 0,
                    "paid_amount"           => $bk_details->paid_amount,
                    "tax_amount"            => $bk_details->tax_amount,
                    "discount_amount"       => 0,
                    "channel"               => "Bookingjini",
                    // "channel_logo"          => "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1016869990agoda.png",
                    "status"                => $booking_status
                );
            }
            if ($otdc_active_status == 1) {
                $otdc_check_in = $Booked_Rooms[0]['check_in'];
                $otdc_check_out = $Booked_Rooms[0]['check_out'];
                $otdc_hotel_id = $get_hotel_code->ota_hotel_code;
                $otdc_hotel_name = $bk_details->hotel_name;
                $otdc_total_amount = $bk_details->total_amount;
                $otdc_booking_date = $booking_date;
                $otdc_booking_status = $bk_details->booking_status;
                $otdc_user_info = $user_info;
                $otdc_booking_id = $booking_id;
            }

            $all_bookings[] = array(
                'UserDetails'               => $user_info,
                'BookingsDetails'           => $Bookings,
                'RoomDetails'               => $rooms
            );
            $k++;
        }
        if ($otdc_active_status == 1) {
            $otdc_user_info = json_encode($otdc_user_info);
            $otdc_check_in_out = "[" . $otdc_check_in . '-' . $otdc_check_out . "]";
            $otdc_room_type = implode(',', $otdc_room_type);
            $otdc_room_type = "[" . $otdc_room_type . "]";
            $otdc_room_type_id = implode(',', $otdc_room_type_id);
            $otdc_rooms = implode(',', $otdc_rooms);
            $otdc_bookings = array(
                "hotel_id" => $otdc_hotel_id,
                "hotel_name" => $otdc_hotel_name,
                "room_type" => $otdc_room_type,
                "total_amount" => $otdc_total_amount,
                "check_in_out" => $otdc_check_in_out,
                "booking_date" => $otdc_booking_date,
                "booking_status" => $otdc_booking_status,
                "room_type_id" => $otdc_room_type_id,
                "rooms" => $otdc_rooms,
                "check_in" => $otdc_check_in,
                "check_out" => $otdc_check_out,
                "user_info" => $otdc_user_info,
                "otdc_booking_id" => $otdc_booking_id,
                "otdc_modify_status" => $bk_details->modify_status
            );
            $push_booking_to_otdc = $this->pushBookingToOTDC($otdc_bookings);
        }

        if (sizeof($all_bookings) > 0) {
            $all_bookings = http_build_query($all_bookings);
            $url = "https://gems.bookingjini.com/api/insertTravellerBookings";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $all_bookings);
            $rlt = curl_exec($ch);
            curl_close($ch);
            return $rlt;
        }
    }

    public function updateInventoryCM($bookingDeatil, $invoice_details)
    {
        $mindays = 0;
        $updated_inv = array();
        $inventory = new Inventory();
        $inv_update = array();
        $inv_data = $this->invService->getInventeryByRoomTYpe($bookingDeatil->room_type_id, $bookingDeatil->check_in, $bookingDeatil->check_out, $mindays);

        if ($inv_data) {
            foreach ($inv_data as $inv) {
                $room_type = $inv['room_type_id'];
            }
            $rooms_qty = $bookingDeatil->rooms;
            $index = sizeof($inv_data);
            $booking_details = [];
            $booking_details['checkin_at'] = date('Y-m-d', strtotime($inv_data[0]['date']));
            $booking_details['checkout_at'] = date('Y-m-d', strtotime($inv_data[$index - 1]['date'] . '+1 day'));
            $invoice_id = $bookingDeatil->invoice_id;
            $booking_status = $invoice_details['booking_status'];
            $modify_status = $invoice_details['modify_status'];
            $invoiceData = Invoice::where('invoice_id', $invoice_id)->first();
            $update_inv_cm_info = array(
                "invoice_id"      => $invoice_id,
                "hotel_id"        => $bookingDeatil->hotel_id,
                "booking_details" => $booking_details,
                "room_type"       => $room_type,
                "rooms_qty"       => $rooms_qty,
                "booking_status"  => $booking_status,
                "modify_status"   => $modify_status
            );
            return $update_inv_cm_info;
        }
    }

    public  function updateInventory($bookingDeatil, $invoice_details)
    {
        $mindays = 0;
        $updated_inv = array();
        $inventory = new Inventory();
        $inv_update = array();
        $inv_data = $this->invService->getInventeryByRoomTYpe($bookingDeatil->room_type_id, $bookingDeatil->check_in, $bookingDeatil->check_out, $mindays);

        if ($inv_data) {
            foreach ($inv_data as $inv) {
                $updated_inv["invoice_id"] = $bookingDeatil->invoice_id;
                $updated_inv["hotel_id"] = $bookingDeatil->hotel_id;
                $updated_inv["room_type_id"] = $inv['room_type_id'];
                $updated_inv["user_id"] = 0; //User id set CM
                $updated_inv["date_from"] = $inv['date'];
                $updated_inv["date_to"] = date('Y-m-d', strtotime($inv['date']));
                $updated_inv["no_of_rooms"] = $inv['no_of_rooms'] - $bookingDeatil->rooms; //Deduct inventory
                $updated_inv["room_qty"] = $bookingDeatil->rooms;
                $room_type[] = $inv['room_type_id'];
                $rooms_qty[] = $bookingDeatil->rooms;
                $updated_inv["client_ip"] = '1.1.1.1'; //\Illuminate\Http\Request::ip();//As server is updating inventory automatically afetr succ booking
                $updated_inv["ota_id"] = 0; //Don't Remove this
                $resp = $this->updateInventoryService->updateInv($updated_inv, $invoice_details);
                if ($resp['be_status'] = "Inventory update successfull") {
                    array_push($inv_update, 1);
                }
            }
        }
        $inv_update_status = true;
        foreach ($inv_update as $up) {
            if (!$up == 1) {
                $inv_update_status = false;
            }
        }
        return  $inv_update_status;
    }

    public function successInvoice($id)
    {

        $query      = "Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice,a.ids_re_id, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond,a.agent_code,a.booking_status,a.modify_status from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$id";
        $result    = DB::select($query);
        return $result;
    }

    public function sendMail($email, $template, $subject, $hotel_email, $hotel_name, $hotel_id)
    {
        $data = array('email' => $email, 'subject' => $subject);
        $data['template'] = $template;
        $data['hotel_name'] = $hotel_name;
        if ($hotel_id == 2065  || $hotel_id == 2881  || $hotel_id == 2882  || $hotel_id == 2883  || $hotel_id == 2884  || $hotel_id == 2885  || $hotel_id == 2886) {
            if ($hotel_email == "") {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com'];
            } else if (sizeof($hotel_email) > 1) {
                $data['hotel_email'] = $hotel_email[0];
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com', $hotel_email[1]];
            } else if (sizeof($hotel_email) == 1) {
                $data['hotel_email'] = $hotel_email[0];
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com'];
            } else {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'odishaecoretreat@gmail.com', 'pkchand65@gmail.com', 'otdc@panthanivas.com'];
            }
        } else {
            if ($hotel_email == "") {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            } else if (sizeof($hotel_email) > 1) {
                $data['hotel_email'] = $hotel_email;
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            } else if (sizeof($hotel_email) == 1) {
                $data['hotel_email'] = $hotel_email[0];
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            } else {
                $data['hotel_email'] = "";
                $mail_array = ['trilochan.parida@5elements.co.in', 'reservations@bookingjini.com', 'accounts@bookingjini.com'];
            }
        }
        $data['mail_array'] = $mail_array;
        //dd($data);
        try {
            Mail::send([], [], function ($message) use ($data) {
                if ($data['hotel_email'] != "") {
                    $message->to($data['email'])
                        ->cc($data['hotel_email'])
                        ->bcc($data['mail_array'])
                        ->from(env("MAIL_FROM"), $data['hotel_name'])
                        ->subject($data['subject'])
                        ->setBody($data['template'], 'text/html');
                } else {
                    $message->to($data['email'])
                        ->from(env("MAIL_FROM"), $data['hotel_name'])
                        //->cc('gourab.nandy@bookingjini.com')
                        ->subject($data['subject'])
                        ->setBody($data['template'], 'text/html');
                }
            });
            if (Mail::failures()) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return true;
        }
    }

    public function agentMail($invoice_id, $hotel_email_id, $hotel_name, $booking_id)
    {
        return true;
        $subject = $hotel_name . " Booking Confirmation";
        $rateLogs = DB::select("CALL getAgentDetails('" . $invoice_id . "')");
        if (isset($rateLogs[0]->username) && sizeof($rateLogs[0]) > 0) {
            $email = $rateLogs[0]->username;
            $template = $rateLogs[0]->invoice;
            $template  = str_replace("#####", $booking_id, $template);
            $data = array('email' => $email, 'subject' => $subject, 'template' => $template, 'hotel_name' => $hotel_name, 'hotel_email' => $hotel_email_id);
            Mail::send([], [], function ($message) use ($data) {
                $message->to($data['email'])
                    ->cc($data['hotel_email'])
                    ->from(env("MAIL_FROM"), 'Agent Bookings')
                    ->subject($data['subject'])
                    ->setBody($data['template'], 'text/html');
            });
            if (Mail::failures()) {
                return false;
            }
            return true;
        }
    }

    public function sendSMS($to, $msg)
    {
        $messageToSend = $msg;
        $messageToSend = urlencode($messageToSend);
        $date = Date('d-m-Y\TH:i:s');
        $smsURL = "https://apps.sandeshlive.com/API/WebSMS/Http/v1.0a/index.php?username=1135&password=oYSeaxIVK9UPgvG0&sender=BKJINI&to=" . $to . "&message=" . $messageToSend . "&reqid=1&format={json|text}&route_id=TRANS-OPT-IN&sendondate=" . $date;
        $ch = curl_init();
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set the url
        curl_setopt($ch, CURLOPT_URL, $smsURL);
        // Execute
        $result = curl_exec($ch);
        // Closing
        curl_close($ch);

        return true;
    }

    public function userInfo($user_id)
    {
        $UserInformation = User::select('first_name', 'last_name', 'mobile', 'email_id', 'address', 'zip_code', 'country', 'state', 'city', 'GSTIN', 'company_name')
            ->where('user_id', $user_id)
            ->first();
        return $UserInformation;
    }

    public function noOfBookings($invoice_id)
    {
        $booked_room_details = HotelBooking::select('room_type_id', 'rooms', 'check_in', 'check_out')
            ->where('invoice_id', $invoice_id)
            ->get();
        return $booked_room_details;
    }

    public function pushBookingToOTDC($otdc_bookings)
    {
        $otdc_bookings['api_key'] = 'de1cb34fddda83c3153d79d46b24cd50';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://panthanivas.com/reservations_from_gems.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $otdc_bookings,
        ));
        $response = curl_exec($curl);
        curl_close($curl);
    }

    public function getGstPricePerRoom($room_rate, $discount)
    {
        $gstPrice = 0;
        $price = $room_rate - $discount;
        if ($room_rate > 0 && $room_rate <= 7500) {
            $gstPrice = ($price * 12) / 100;
        } else if ($room_rate > 7500) {
            $gstPrice = ($price * 18) / 100;
        }
        return $gstPrice;
    }

    public function getBaseCurrency($hotel_id)
    {
        $company = CompanyDetails::join('hotels_table', 'company_table.company_id', 'hotels_table.company_id')
            ->where('hotels_table.hotel_id', $hotel_id)
            ->select('currency', 'hex_code')
            ->first();
        if ($company) {
            return $company;
        }
    }

    public function getUserDetails($user_id)
    {
        $user = User::select('*')->where('user_id', $user_id)->first();
        return $user;
    }

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

    public function getHotelInfo($hotel_id)
    {
        $hotel = HotelInformation::select('*')->where('hotel_id', $hotel_id)->first();
        return $hotel;
    }

    public function prepareRoomTypes($room_details)
    {
        $room_types = array();
        foreach ($room_details as $room_detail) {
            $room_type_id = $room_detail['room_type_id'];
            $rate_plan_id = $room_detail['rate_plan_id'];
            $conditions = array('room_type_id' => $room_type_id, 'is_trash' => 0);
            $room_type_array = MasterRoomType::select('room_type')->where($conditions)->first();
            $room_type = $room_type_array['room_type'];
            $rate_plan = MasterRatePlan::where('rate_plan_id', $rate_plan_id)->where('is_trash', 0)->first();
            $plan_type = $rate_plan['plan_type'];
            $rooms = count($room_detail['rooms']);
            array_push($room_types, $rooms . ' ' . $room_type . '(' . $plan_type . ')');
        }
        return $room_types;
    }

    public function checkGSTPercent($price)
    {
        if ($price > 0 && $price < 7500) {
            return 12;
        } else if ($price >= 7500) {
            return 18;
        }
    }
}
