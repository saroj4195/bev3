<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use App\Packages;
use App\ImageTable;
use App\RoomTypeTable;
use DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IpAddressService;
use App\HotelInformation;
use Illuminate\Support\Facades\Hash;
use App\User;
use App\UserNew;
use App\Invoice;
use App\CompanyDetails;
use App\HotelBooking;

class PackageBookingController extends Controller
{
    protected $ipService;
    public function __construct(IpAddressService $ipService)
    {
        $this->ipService = $ipService;
    }

    public function packageList(int $hotel_id, $from_date, $currency_name)
    {
        $from_date = date('Y-m-d', strtotime($from_date));
        if ($hotel_id) {

            $package_inventory = Packages::select('package_table.package_id', 'package_table.room_type_id', 'package_table.hotel_id', 'package_table.package_name', 'package_table.date_from', 'package_table.date_to', 'package_table.adults as base_adult', 'package_table.extra_person as extra_adult', 'package_table.extra_person_price as extra_adult_price', 'package_table.max_child as base_child', 'package_table.extra_child', 'package_table.extra_child_price', 'package_table.nights', 'package_table.amount', 'package_table.discounted_amount', 'package_table.package_description', 'package_table.tax_type', 'package_table.tax_name', 'package_table.tax_percentage', 'package_table.inclusion', 'package_table.exclusion', 'package_table.blackout_dates', 'package_table.package_image')
                ->where('package_table.hotel_id', $hotel_id)
                ->where('package_table.date_from', '<=', $from_date)
                ->where('package_table.date_to', '>', $from_date)  //added by manoj
                ->where('package_table.is_trash', 0)
                ->get();
                

            foreach ($package_inventory as $key => $package) {

                $package_image = explode(',', $package->package_image);
                $package_img = ImageTable::whereIn('image_id', $package_image)
                    ->get();
                $image_array = [];
                foreach ($package_img as $img) {
                    $image_array[] = $img->image_name;
                }
                $package['discount_amount'] = $package->amount - $package->discounted_amount;
                $package['package_description'] = $package->package_description;
                $package['discount_percentage'] = $package['discount_amount'] / $package->amount * 100;
                $package["date_to"] = date('Y-m-d', strtotime($from_date . '+' . $package->nights . ' day'));
                $from_date = date('Y-m-d', strtotime($from_date));

                $package['min_inv'] = 0;
                $package['image'] = $image_array[0];
                $package['package_image'] = $image_array;
                $info = $this->invService->getInventeryByRoomTYpe($package['room_type_id'], $from_date, $package['date_to'], 0);

                $room_details = RoomTypeTable::where('hotel_id', $hotel_id)->where('room_type_id', $package['room_type_id'])->where('is_trash', 0)->first();
                $package['bed_type'] = isset($room_details->bed_type) ? $room_details->bed_type : 'NA';
                $package['room_size'] =  isset($room_details->room_size_value) ? $room_details->room_size_value : 'NA';
                $package['room_size_unit'] = isset($room_details->room_size_unit) ? $room_details->room_size_unit : 'NA';
                $package['room_view'] = isset($room_details->room_view_type) ? $room_details->room_view_type : 'NA';
                $package['total_occupancy'] = isset($package->base_adult) + isset($package->extra_adult) + isset($package->base_child) + isset($package->extra_child);

                $package['min_inv'] = $info[0]['no_of_rooms'];
                $black_out = array_column(json_decode($package["blackout_dates"], true), "date");

                $from_date = date('d-m-Y', strtotime($from_date));
                $end_date = date('d-m-Y', strtotime($package["date_to"]));

                $period     = new \DatePeriod(
                    new \DateTime($from_date),
                    new \DateInterval('P1D'),
                    new \DateTime($end_date)
                );
                foreach ($period as $value) {
                    $index = $value->format('d-m-Y');
                    if (in_array($index, $black_out)) {
                        $package_inventory[$key]['min_inv'] = 0;
                    }
                }
            }

            if (sizeof($package_inventory) > 0) {
                $res = array("status" => 1, "message" => "successfully retrive details", "package_inventory" => $package_inventory);
                return response()->json($res);
            } else {
                $res = array("status" => 0, "message" => "Package not found");
                return response()->json($res);
            }
        } else {
            $res = array("status" => 0, "message" => "fail to retrive details");
            return response()->json($res);
        }
    }

    public function packageBookings(string $api_key, Request $request)
    {
        $data = $request->all();
        //booking details
        $booking_details = $data['booking_details'];
        $hotel_id = $booking_details['hotel_id'];
        $payment_mode = $booking_details['payment_mode'];
        $checkin_date = date('Y-m-d', strtotime($booking_details['checkin_date']));
        $checkout_date = date('Y-m-d', strtotime($booking_details['checkout_date']));
        $amount_to_pay = $booking_details['amount_to_pay'];

        $status = "invalid";
        $status = $this->checkAccess($api_key, $hotel_id);
        if ($status == "invalid") {
            $res = array('status' => 1, 'message' => "Invalid company or Hotel");
            return response()->json($res);
        }
        
        //package details
        $package_details =$data['package_details'];
        $package_id = $package_details['package_id'];
        $no_of_package = $package_details['no_of_package'];
        $occupancy = $package_details['occupancy'];

        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->select('company_id', 'gst_slab', 'is_taxable', 'partial_pay_amt')->first();
        $company_name = DB::table('kernel.company_table')->where('company_id', $hotel_info->company_id)->first();

        //user registration
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

        $package_res = Packages::where('package_id',$package_id)->first();
        $base_adult = $package_res->adults;
        $base_child = $package_res->max_child;
        $package_price = $package_res->discounted_amount;
        $extra_adult_price = $package_res->extra_person_price;
        $extra_child_price = $package_res->extra_child_price;
        $total_amount = 0;

        $total_adult = 0;
        $total_child = 0;

        $room_price = 0;
        foreach ($occupancy as $key => $acc) {
            $selected_adult = $acc['adult'];
            $selected_child = $acc['child'];
            $total_adult += $acc['adult'];
            $total_child += $acc['child'];

            if ($base_child < $selected_child) {
                $extra_child = $selected_child - $base_child;
                $room_price = $package_price + ($extra_child * $extra_child_price);
            }

            if ($base_adult < $selected_adult) {
                $extra_adult = $selected_adult - $base_adult;
                $room_price = $package_price + ($extra_adult * $extra_adult_price);
            }else{
                $room_price = $package_price;
            }

            //gst calculation

            $tax_amount = 0;
            if($hotel_info->is_taxable==1){
                $gst_percentage = $this->checkGSTPercent($room_price);
                $tax_amount = $gst_percentage * $room_price / 100;
                $total_amount += $room_price + $tax_amount;
            }else{
                $total_amount += $room_price + $tax_amount;
            }
        }

        $paid_amount = $total_amount;

        $hotel = $this->getHotelInfo($hotel_id);

       
        
        if($payment_mode == '2'){
            $paid_amount= $total_amount*($hotel_info->partial_pay_amt/100);
        }elseif($payment_mode == '3'){
            $paid_amount = 0;
        }

        if(round($paid_amount) != round($amount_to_pay)){
            $res = array('status' => 0, "message" => 'Booking failed due to data tempering.','data'=>$paid_amount);
                 return response()->json($res);
        }
       
      
        //store the data in invoice table
        $invoice = new Invoice();
        $inv_data = array();
        $pkg_det_array = [];
        $extra_details = array();
        $pkg_det = $no_of_package . ' ' . $package_res['package_name'];
        array_push($pkg_det_array,$pkg_det);

        array_push($extra_details, array($package_res->room_type_id => array($total_adult, $total_child)));

        $inv_data['hotel_id']   = $hotel_id;
        $inv_data['hotel_name'] = $hotel->hotel_name;
        $inv_data['room_type']  = json_encode($pkg_det_array);
        $inv_data['package_id'] = $package_id;
        $inv_data['ref_no'] = rand() . strtotime("now");
        $inv_data['total_amount']  = $total_amount;
        $inv_data['paid_amount']   = $paid_amount;
        $inv_data['tax_amount']   = $tax_amount;
        $inv_data['discounted_amount'] = $package_res['amount'] - $package_res['discounted_amount'];
        $inv_data['check_in_out'] = "[" . $checkin_date . '-' . $checkout_date . "]";
        $inv_data['booking_date'] = date('Y-m-d H:i:s');
        $inv_data['booking_status'] = 2;
        $inv_data['extra_details'] = json_encode($extra_details);
        $inv_data['invoice'] = $this->createInvoice($hotel_id, $data, $checkin_date, $checkout_date, $user_id, $hotel_info);
        // dd($inv_data['invoice']);
        $inv_data['booking_source'] = 'website';
        $inv_data['user_id']    = $user_id;
        $inv_data['user_id_new'] = $user_id_new;
        // $inv_data['ids_re_id'] = $this->booking_engine->handleIds($cart, $from_date, $to_date, $inv_data['booking_date'], $hotel_id, $inv_data['user_id'], $booking_status);

        $failure_message = "Booking create failed";
        if ($invoice->fill($inv_data)->save()) {
            //store the data in hotel table
            $invoice_id = $invoice->invoice_id;
            $hotel_booking_data['room_type_id'] = $package_res['room_type_id'];
            $hotel_booking_data['rooms'] = sizeof($occupancy);
            $hotel_booking_data['check_in'] = $checkin_date;
            $hotel_booking_data['check_out'] = $checkout_date;
            $hotel_booking_data['booking_status'] = 2; //Intially Un Paid
            $hotel_booking_data['user_id'] = 1;
            $hotel_booking_data['booking_date'] = date('Y-m-d');
            $hotel_booking_data['invoice_id'] = $invoice_id;
            $hotel_booking_data['hotel_id'] = $hotel_id;

            $hotel_details = HotelBooking::insert($hotel_booking_data);

            if($hotel_details){
                $user_data = $this->getUserDetails($inv_data['user_id']);
                $b_invoice_id = base64_encode($invoice_id);
                $invoice_hashData = $invoice_id . '|' . $total_amount . '|' . $total_amount . '|' . $user_details['email_id'] . '|' . $user_details['mobile'] . '|' . $b_invoice_id;
                $invoice_secureHash = hash('sha512', $invoice_hashData);
                $res = array("status" => 1, "message" => "Invoice details saved successfully", "invoice_id" => $invoice_id, 'invoice_secureHash' => $invoice_secureHash);
                return response()->json($res);
            }else{
                $res = array('status' => 0, "message" => $failure_message);
                 return response()->json($res);
            }
        }else{
            $res = array('status' => 0, "message" => $failure_message);
            return response()->json($res);
        }

    }

    public function createInvoice($hotel_id, $cart, $check_in, $check_out, $user_id, $hotel_info)
    {
        $booking_id = "#####";
        $booking_date = date('Y-m-d');
        $booking_date = date("jS M, Y", strtotime($booking_date));
        $hotel_details = $this->getHotelInfo($hotel_id);
        $u = $this->getUserDetails($user_id);
        $dsp_check_in = date("jS M, Y", strtotime($check_in));
        $dsp_check_out = date("jS M, Y", strtotime($check_out));
        $all_room_type_name = "";
        $all_rows = "";
        $other_tax_arr = array();
        //Get base currency and currency hex code
        $baseCurrency = $this->getBaseCurrency($hotel_id)->currency;
        $currency_code = $this->getBaseCurrency($hotel_id)->hex_code;

        $package_data = $cart['package_details'];
        $package_id = $package_data['package_id'];
        $no_of_package = $package_data['no_of_package'];
        $occupancy = $package_data['occupancy'];

        $booking_details = $cart['booking_details'];
        $payment_mode = $booking_details['payment_mode'];
        

        $package_res = Packages::where('package_id',$package_id)->first();

        $package_price = $package_res->discounted_amount;
        $base_adult = $package_res->adults;
        $base_child = $package_res->max_child;
        $extra_adult_price = $package_res->extra_person_price;
        $extra_child_price = $package_res->extra_child_price;

        $all_rows .= '<tr><td rowspan="' . $no_of_package .'" colspan="2">'. $package_res['package_name'] .'</td>';
        $total_package_price = 0;
        $total_adult_price = 0;
        $total_child_price = 0;
        $tax_amount = 0;
        $total_selected_adult = 0;
        $total_selected_child = 0;

        foreach ($occupancy as $key => $acc) {
            $selected_adult = $acc['adult'];
            $selected_child = $acc['child'];

            $total_selected_adult += $selected_adult;
            $total_selected_child += $selected_child;

            $child_price = 0;
            if ($base_child < $selected_child) {
                $extra_child = $selected_child - $base_child;
                $child_price = $extra_child * $extra_child_price;
            }
            $total_child_price += $child_price;
            
            $adult_price = 0;
            if ($base_adult < $selected_adult) {
                $extra_adult = $selected_adult - $base_adult;
                $adult_price = $extra_adult * $extra_adult_price;
            }

            $total_adult_price += $adult_price;

            $all_rows = $all_rows . '<td  align="center">' . $key+1 . '</td>
            <td  align="center">' . $adult_price . '</td>
            <td  align="center">' . $child_price . '</td>
            <td  align="center">' . $currency_code . $package_price . '</td>
            <td  align="center">' . $currency_code . $package_price + $adult_price + $child_price. '</td>
            </tr>';
            

            $total_package_price += $package_price;

            //calculate tax price

            $total_package_price_with_out_tax = $package_price + $child_price + $adult_price;

            if($hotel_info->is_taxable==1){
                $gst_percentage = $this->checkGSTPercent($total_package_price_with_out_tax);
                $tax_amount += $gst_percentage * $total_package_price_with_out_tax / 100;
            }else{
                $tax_amount = 0;
            }

        }

        $gst_tax_details = "";
        if ($baseCurrency == 'INR') {
            $gst_tax_details = '<tr>
           <td colspan="6" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
           <td align="center">' . $currency_code . $tax_amount . '</td>
           </tr>';
        }
        $other_tax_details = "";
        if (sizeof($other_tax_arr) > 0) {

            foreach ($other_tax_arr as $other_tax) {
                $other_tax_details = $other_tax_details . '<tr>
              <td colspan="6" style="text-align:right;">' . $other_tax['tax_name'] . '&nbsp;&nbsp;</td>
              <td style="text-align:center;">' . $currency_code . $other_tax['tax_price'] . '</td>
              <tr>';
            }
        }
        $total_amt = $total_package_price + $total_adult_price + $total_child_price;
        $total_amt = number_format((float)$total_amt, 2, '.', '');
        $total = $total_amt + $tax_amount;
        $total = number_format((float)$total, 2, '.', '');
        $pay_at_hotel = 0;

        $paid_amount = $total;
        if($payment_mode == 2){
            $paid_amount = $total*($hotel_info->partial_pay_amt/100);
            // $paid_amount = $total - $paid_amt;
            $pay_at_hotel = $total - $paid_amount;
        }elseif($payment_mode == 3){
            $paid_amount = 0;
            $pay_at_hotel = $total;
        }else{
            $paid_amount = $total;
            $pay_at_hotel = 0;
        }
        
        $paid_amount = number_format((float)$paid_amount, 2, '.', '');
        $due_amount = $total - $paid_amount;
        $due_amount = number_format((float)$due_amount, 2, '.', '');
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
                    <th colspan="2" valign="middle" style="font-size: 23px;"><u>BOOKING CONFIRMATION</u></th>
                </tr>
                <tr>
                <td><b style="color: #ffffff;">*</b></td>
                </tr>
                <tr>
                    <td>
                        <div>
                            <div style="font-weight: bold; font-size: 22px; color:#fff; background-color: #1d99b5; padding: 5px;">' . $hotel_details->hotel_name . '</div>
                        </div>
                    </td>
                    <td style="font-size: 16px;font-weight: bold;" align="right">BOOKING ID : ' . $booking_id . '</td>
                </tr>
                <tr>
                    <td colspan="2"><b style="color: #ffffff;">*</b></td>
                </tr>
                <tr>
                    <td colspan="2"><b>Dear ' . $u->first_name . ' ' . $u->last_name . ',</b></td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size:17px;"><b>Thank  you for choosing to book with ' . $hotel_details->hotel_name . '. Your reservation has been accepted, your booking details can be found below. We look forward to see you soon.</b></td>
                </tr>
                <tr>
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
                    <td width="45%">NAME OF GUEST</td>
                    <td>' . $u->first_name . ' ' . $u->last_name . '</td>
                </tr>
                <tr>
                    <td>GUEST PHONE NUMBER</td>
                    <td>' . $u->mobile . '</td>
                </tr>
                <tr>
            <td>EMAIL ID</td>
            <td>' . $u->email_id . '</td>
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
            <td>TOTAL ADULT</td>
            <td>' . $total_selected_adult . '</td>
        </tr>
                    <tr>
            <td>TOTAL CHILD</td>
            <td>' . $total_selected_child . '</td>
        </tr>
                <tr>
            <td>PACKAGE NAME</td>
            <td>' . substr($all_room_type_name, 1) . '</td>
        </tr>

        </table>

            <table width="100%" border="1" style="border-collapse: collapse;">
                <tr>
        <th colspan="10" valign="middle" height="" style="font-size: 20px;">TARIFF APPLICABLE</th>
        </tr>
                <tr>
            <th colspan="2" bgcolor="#ec8849" align="center">Package Type</th>
            <th bgcolor="#ec8849" align="center">Rooms</th>
            <th bgcolor="#ec8849" align="center">Extra Adult Price</th>
            <th bgcolor="#ec8849" align="center">Extra Child Price</th>
            <th bgcolor="#ec8849" align="center">Price</th>
            <th bgcolor="#ec8849" align="center">Total Price</th>
        </tr>
                ' . $all_rows . '
        <tr>
            <td colspan="6"><p style="color: #ffffff;">*</p></td>
        </tr>
                <tr>
            <td colspan="6" align="right">Total Room Rate&nbsp;&nbsp;</td>
            <td align="center">' . $currency_code . $total_amt . '</td>
        </tr>
        ' . $gst_tax_details . '
        ' . $other_tax_details . '
        <tr>
            <td colspan="6" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
            <td align="center">' . $currency_code . $total . '</td>
        </tr>
        <tr>
            <td colspan="6" align="right">Total Paid Amount&nbsp;&nbsp;</td>
            <td align="center">' . $currency_code . '<span id="pd_amt">' . $paid_amount . '</span></td>
        </tr>
        <tr>
            <td colspan="6" align="right">Pay at hotel&nbsp;&nbsp;</td>
            <td align="center">' . $currency_code . '<span id="pd_amt">' . $pay_at_hotel . '</span></td>
        </tr>
        <tr>
         <td colspan="6"><p style="color: #ffffff;">* </p></td>
     </tr>
        </table>

            <table width="100%" border="0">
                <tr>
        <th colspan="2" style="font-size: 21px; color: #ffffff;"><u>*</u></th>
        </tr>
                <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;">Awaiting For Your Welcome  !!!</span></td>
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
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Terms & Conditions</u> :</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color:#f00"><li>On Any 100% Cancellation Policy there will be a 3% mandatory deduction from the booking amount due to payment gateway charges.</li></span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->terms_and_cond . '</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Cancellation Policy</u> :</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold;">' . $hotel_details->hotel_policy . '</span></td>
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

    public function checkGSTPercent($price)
    {
        if ($price > 0 && $price < 7500) {
            return 12;
        } else if ($price >= 7500) {
            return 18;
        }
    }

    public function getUserDetails($user_id)
    {
        $user = User::select('*')->where('user_id', $user_id)->first();
        return $user;
    }


    public function getHotelInfo($hotel_id)
    {
        $hotel = DB::connection('bookingjini_kernel')->table('hotels_table')->select('*')->where('hotel_id', $hotel_id)->first();
        return $hotel;
    }


    public function checkAccess($api_key, $hotel_id)
    {
        //$hotel=new HotelInformation();
        $cond = array('api_key' => $api_key);
        $comp_info = DB::connection('bookingjini_kernel')->table('company_table')->select('company_id')
            ->where($cond)->first();
        if (!$comp_info->company_id) {
            return "Invalid";
        }
        $conditions = array('hotel_id' => $hotel_id, 'company_id' => $comp_info->company_id);
        $info = DB::connection('bookingjini_kernel')->table('hotels_table')->select('hotel_name')->where($conditions)->first();
        if ($info) {
            return 'valid';
        } else {
            return "invalid";
        }
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

    public function packageBookingInvoiceDetails($invoice_id, Request $request){

        $getBookingDetails = Invoice::join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
        ->select('hotel_booking.check_in', 'hotel_booking.check_out', 'invoice_table.room_type', 'invoice_table.extra_details', 'invoice_table.booking_date','invoice_table.package_id','invoice_table.invoice_id', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.hotel_name', 'invoice_table.hotel_id', 'company_table.currency', 'invoice_table.booking_status')
        ->where('invoice_table.invoice_id', $invoice_id)
        ->first();

        $date1 = date_create($getBookingDetails->check_in);
        $date2 = date_create($getBookingDetails->check_out);
        $diff = date_diff($date1, $date2);
        $no_of_nights = $diff->format("%a");
        if ($no_of_nights == 0) {
            $no_of_nights = 1;
        }

        // $occupancy_details = json_decode($getBookingDetails->extra_details, true);
        

        $package_details = Packages::where('package_id', $getBookingDetails->package_id)->first();

        $booking_details = [
            'paid_amount' => $getBookingDetails->paid_amount,
            'booking_id' => date('dmy',strtotime($getBookingDetails->booking_date)).$getBookingDetails->invoice_id,
            'check_in' => date('d M Y',strtotime($getBookingDetails->check_in)),
            'check_out' => date('d M Y',strtotime($getBookingDetails->check_out)),
            'night' => $getBookingDetails->$no_of_nights,
            'package_name' => $package_details->package_name,
            'adult' => $package_details->adults,
            'child' => isset($package_details->child)?$package_details->child:0,
        ];


        if (sizeof($booking_details) > 0) {
            $res = array("status" => 1, "message" => "successfully retrive details", "Booked_package_details" => $booking_details);
            return response()->json($res);
        } else {
            $res = array("status" => 0, "message" => "Package details not found");
            return response()->json($res);
        }

    }

}
