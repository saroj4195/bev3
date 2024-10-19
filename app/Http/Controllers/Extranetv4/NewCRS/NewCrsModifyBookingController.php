<?php

namespace App\Http\Controllers\Extranetv4\NewCRS;

use Illuminate\Http\Request;
use App\Invoice;
use App\ImageTable;
use App\Http\Controllers\InventoryService;
use App\Http\Controllers\BookingEngineController;
use App\Http\Controllers\CrsBookingsController;
use App\Inventory;
use DB;
use App\MasterRoomType;
use App\MasterRatePlan;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaBooking;
use App\DynamicPricingCurrentInventory;
use App\RoomRatePlan;
use App\HotelBooking;
use App\User;
use App\CrsBooking;
use App\PmsAccount;


class NewCrsModifyBookingController extends Controller
{

    protected $invService;
    protected $cmOtaBookingInvStatusService;
    public function __construct(InventoryService $invService, BookingEngineController $bookingEngineController, CrsBookingsController $crsBookingsController)
    {
        $this->invService = $invService;
        $this->bookingEngineController = $bookingEngineController;
        $this->crsBookingsController = $crsBookingsController;
    }

    /**
     * @author Saroj Patel
     * Dt: 25-11-2022
     * This function is used for Fetch Modify CRS Booking Dates.
     */
    public function modifyBookingDates($hotel_id, $booking_id)
    {
        $invoice_id = substr($booking_id, 6);
        $bookingDetails =  Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            // ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->join('kernel.image_table', 'image_table.image_id', '=', 'company_table.logo')
            ->select(
                'user_table.first_name',
                'user_table.last_name',
                'user_table.email_id',
                'user_table.mobile',
                'invoice_table.booking_date',
                'invoice_table.total_amount',
                'invoice_table.booking_status',
                'invoice_table.invoice_id',
                'invoice_table.hotel_id',
                'invoice_table.extra_details',
                // 'hotel_booking.check_in',
                // 'hotel_booking.check_out',
                'image_table.image_name'
            )
            ->where('invoice_table.invoice_id', '=', $invoice_id)
            ->where('invoice_table.hotel_id', '=', $hotel_id)
            ->where('invoice_table.booking_status', '=', 1)
            ->first();

        $room_rates =  HotelBooking::join('kernel.room_type_table', 'room_type_table.room_type_id', '=', 'hotel_booking.room_type_id')
            ->select('room_type_table.room_type', 'hotel_booking.room_rate', 'hotel_booking.room_type_id', 'hotel_booking.check_in', 'hotel_booking.check_out')
            ->where('hotel_booking.invoice_id', $invoice_id)
            ->where('hotel_booking.hotel_id', $hotel_id)
            ->get();
        $room_rate = [];
        if ($room_rates) {
            foreach ($room_rates as $key => $rooms) {
                $check_in = $rooms->check_in;
                $check_out = $rooms->check_out;
                array_push($room_rate, array('room_type_name' => $rooms->room_type, 'room_type_id' => $rooms->room_type_id, 'room_rate' => $rooms->room_rate,));
            }
        }

        $date1 = date_create($check_in);
        $date2 = date_create($check_out);
        $diff = date_diff($date1, $date2);
        $night = $diff->format("%a");

        if ($night == 0) {
            $night = 1;
        }

        $extra_details = json_decode($bookingDetails->extra_details, true);
        for ($i = 0; $i < sizeof($extra_details); $i++) {
            $keys = array_keys($extra_details[$i]);
            $adult[] = $extra_details[$i][$keys[0]][0];
            $child[] = $extra_details[$i][$keys[0]][1];
        }

        $total_adult = array_sum($adult);
        $total_child = array_sum($child);
        $total = $total_adult + $total_child;

        if ($total > 1) {
            $total = $total - 1;
        }

        $booking_details['full_name'] = $bookingDetails->first_name . ' ' . $bookingDetails->last_name . ' + ' . $total;
        $booking_details['booking_date'] = date('d M Y', strtotime($bookingDetails->booking_date));
        $booking_details['email_id'] = $bookingDetails->email_id;
        $booking_details['mobile'] = '(+91)' . $bookingDetails->mobile; // add (+91)
        $booking_details['booking_id'] = $booking_id; //booking id
        $booking_details['check_in'] = date('d M Y', strtotime($check_in)); //format 20 0ct 2022
        $booking_details['check_out'] = date('d M Y', strtotime($check_out));
        $booking_details['nights'] = $night;
        $booking_details['total_amount'] = $bookingDetails->total_amount;
        $booking_details['room_rates'] = $room_rate;
        $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $bookingDetails->image_name; //default hotel logo else bookingjini logo

        if ($booking_details) {
            return response()->json(array('status' => 1, 'message' => 'Fecthed.', 'booking_type' => $booking_details));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Fetched Failed.'));
        }
    }

    /**
     * @author Saroj Patel
     * Dt: 26-11-2022
     * This function is used for check inv availability.
     */
    public function fetchAvailableRooms(Request $request)
    {
        $data = $request->all();
        $hotel_id = $data['hotel_id'];
        $invoice_id = substr($data['booking_id'], 6);
        $date_from = date('Y-m-d', strtotime($data['from_date']));
        $date_to = date('Y-m-d', strtotime($data['to_date']));
        $today_date = date('Y-m-d');
        $date1 = date_create($date_from);
        $date2 = date_create($today_date);

        if ($date1 < $date2) {
            return response()->json(['status' => 0, "message" => 'Date range should not be past date']);
        }

        $room_type_details =  HotelBooking::join('kernel.room_type_table', 'room_type_table.room_type_id', '=', 'hotel_booking.room_type_id')
            ->select('room_type_table.room_type', 'hotel_booking.room_rate', 'hotel_booking.room_type_id', 'hotel_booking.check_in', 'hotel_booking.check_out', 'hotel_booking.rooms')
            ->where('hotel_booking.invoice_id', '=', $invoice_id)
            ->where('hotel_booking.hotel_id', '=', $hotel_id)
            ->get();

        $room_rate = [];
        if ($room_type_details) {
            foreach ($room_type_details as $key => $rooms) {
                array_push($room_rate, array('room_type_name' => $rooms->room_type, 'room_type_id' => $rooms->room_type_id, 'room_rate' => $rooms->room_rate,));
            }
        }

        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime("-1 days", strtotime($date_to)));
        $datefrom = $date_from;
        $filtered_inventory = array();

        while (strtotime($datefrom) <= strtotime($date_to)) {
            $array = $datefrom;
            $timestamp = strtotime($array);
            $day = date('D', $timestamp);
            array_push($filtered_inventory, array("date" => $array));
            $datefrom = date("Y-m-d", strtotime("+1 days", strtotime($datefrom)));
        }

        if (sizeof($room_type_details) > 0) {
            foreach ($room_type_details as $room_type) {
                $inventories = [];
                $block_status = [];
                foreach ($filtered_inventory as $date_range) {
                    $inventory_details = DynamicPricingCurrentInventory::select('room_type_id', 'stay_day as date', 'no_of_rooms', 'block_status')
                        ->where('room_type_id', $room_type->room_type_id)
                        ->where('hotel_id', $hotel_id)
                        ->where('ota_id', '-1')
                        ->where('stay_day', $date_range)
                        ->orderBy('id', 'ASC')
                        ->first();

                    $inventories[] = !empty($inventory_details['no_of_rooms']) ? $inventory_details['no_of_rooms'] : 0;
                    $block_status[] = $inventory_details['block_status'];
                }

                $str_dates = '';
                for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
                    $str_dates .= "'$dt' between A.from_date and A.to_date OR";
                    $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
                }
                $str_dates = substr($str_dates, 0, strlen($str_dates) - 3);

                $be_query = "select 'Bookingjini' as channel, -1 as ota_id, 0 as sl_no,
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
                $be_rates = DB::select(DB::raw($be_query));

                $rate_block_status = [];
                for ($dt = $date_from; strtotime($dt) <= strtotime($date_to);) {
                    $short_day = date('D', strtotime($dt));
                    $rate_date[$dt]['created_at'] = '1970-01-01 00:00:00';
                    foreach ($be_rates as $ota_rate) {
                        if ($dt >= $ota_rate->from_date && $dt <= $ota_rate->to_date) {
                            if (isset($rate_date[$dt]['created_at']) && date('Y-m-d H:i:s', strtotime($ota_rate->created_at)) > $rate_date[$dt]['created_at']) {
                                $multiple_days = $ota_rate->multiple_days;
                                $arr_days = json_decode($multiple_days);
                                if ($arr_days->$short_day == 1) {
                                    $rate_record['room_type_id'] = $ota_rate->room_type_id;
                                    $rate_record['rate_plan_id'] = $ota_rate->rate_plan_id;
                                    $rate_record['plan_type'] = $ota_rate->plan_type;
                                    $rate_record['bar_price'] = $ota_rate->bar_price;
                                    $rate_record['from_date'] = $ota_rate->from_date;
                                    $rate_record['to_date'] = $ota_rate->to_date;
                                    $rate_record['block_status'] = $ota_rate->block_status;
                                    $rate_record['created_at'] = $ota_rate->created_at;
                                    $rate_date[$dt] = $rate_record;
                                    $rate_block_status[$dt] = $ota_rate->block_status;
                                }
                            }
                        }
                    }

                    $dt = date('Y-m-d', strtotime($dt . ' + 1 day'));
                }

                $rate_block_status = in_array(1, $rate_block_status);
                $check_block_status = in_array(1, $block_status);

                if ($rate_block_status || $check_block_status) {
                    return response()->json(['status' => 0, "message" => 'Bookings fetched', 'data' => 'Not available']);
                } else {
                    foreach ($inventories as $key => $inventory) {
                        if ($inventory >= $room_type['rooms']) {
                            continue;
                        } else {
                            return response()->json(['status' => 0, "message" => 'Bookings fetched', 'data' => 'Not available']);
                        }
                    }
                }
            }
            return response()->json(['status' => 1, "message" => 'Bookings fetched', 'data' => 'Available', 'room_rates' => $room_type_details]);
        }
    }

    /**
     * @author Saroj Patel
     * Dt: 29-11-2022
     * This function is used for Modify CRS Booking
     */
    public function saveModifyBookingDates(Request $request)
    {
        $data = $request->all();
        $invoice_id = substr($data['booking_id'], 6);
        $hotel_id = $data['hotel_id'];
        $modify_check_in = date('Y-m-d', strtotime($data['check_in']));
        $modify_check_out = date('Y-m-d', strtotime($data['check_out']));
        $modified_date = date('Y-m-d H:i:s');
        $cart_data = $data;
        $mail_type = 'Modification';
        $room_details = $request->room_rates;

        $cancel_previous_booking = $this->crsBookingsController->cancelBooking($invoice_id, 'modify');
        if ($cancel_previous_booking) {

            $delete_booking_allocation = DB::table('cmlive.bookings_allocation_log')->where('booking_id', $data['booking_id'])->delete();

            if ($room_details) {
                $total_price = 0;
                $total_price_with_out_gst = 0;
                $total_gst_price = 0;
                foreach ($room_details as $room_detail) {
                    $room_rates = $room_detail['room_rate'];
                    $room_type_id = $room_detail['room_type_id'];
                    if ($room_rates > 7500) {
                        $gst_price = (18 / 100) * $room_rates;
                    } elseif ($room_rates > 1000 && $room_rates < 7500) {
                        $gst_price = (12 / 100) * $room_rates;
                    } else {
                        $gst_price = 0;
                    }
                    $total_price_with_out_gst = $total_price_with_out_gst + $room_rates;
                    $total_gst_price = $total_gst_price + $gst_price;
                    $total_price = $total_price_with_out_gst + $total_gst_price;

                    //---------------------update hotel_booking table------------------------------//
                    $update_htl_bkng = HotelBooking::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->where('room_type_id', $room_type_id)->update(['room_rate' => $room_rates, 'check_in' => $modify_check_in, 'check_out' => $modify_check_out, 'modify_date' => $modified_date, 'modify_status' => '1', 'booking_status' => 1]);
                }

                //---------------------update invoice_table-----------------------------------//
                $check_in_out = '[' . $modify_check_in . '-' . $modify_check_out . ']';
                $update_invoice = Invoice::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->update(['check_in_out' => $check_in_out, 'modify_date' => $modified_date, 'booking_status' => 1, 'total_amount' => $total_price, 'tax_amount' => $total_gst_price]); //add modify status

                $update_crs_bkng = CrsBooking::where('invoice_id', $invoice_id)->where('hotel_id', $hotel_id)->update(['total_amount' => $total_price, 'check_in' => $modify_check_in, 'check_out' => $modify_check_out, 'payment_status' => 'Confirm', 'payment_link_status' => 'valid', 'booking_status' => 1]);
            }

            $previous_booking = CrsBooking::join('invoice_table', 'invoice_table.invoice_id', '=', 'crs_booking.invoice_id')
                ->select(
                    'discount_amount',
                    'paid_amount',
                    'extra_details',
                    'user_id',
                    'crs_booking.no_of_rooms',
                    'crs_booking.room_type_id',
                    'invoice_table.user_id',
                    'invoice_table.ref_no'
                )->where('invoice_table.invoice_id', $invoice_id)->where('invoice_table.hotel_id', $hotel_id)->first();
            // dd($update_invoice,$update_htl_bkng,$update_crs_bkng);
            if ($update_invoice && $update_htl_bkng && $update_crs_bkng) {

                $res = $this->bookingEngineController->crsBooking($invoice_id, 'crs', $request)->getData();
                if ($res->status == 1) {
                    $this->crsBookingsController->crsBookingMail($invoice_id, 0, $mail_type); //check the payment option
                    $is_ids = PmsAccount::where('name', 'IDS NEXT')->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')->first();

                    if ($is_ids) {
                        $inv_data = $this->bookingEngineController->handleIds($cart_data, $modify_check_in, $modify_check_out, $modified_date, $hotel_id, $previous_booking->user_id, 'Modify');
                        $update_ids_id = Invoice::where('invoice_id', $invoice_id)->update(['ids_re_id' => $inv_data]);
                    }

                    $res = array('status' => 1, "message" => 'Date Modified');
                    return response()->json($res);
                }
            } else {
                return response()->json(array('status' => 0, 'message' => 'Date Modified Failed.'));
            }
        } else {
            return response()->json(array('status' => 0, 'message' => 'Date Modified Failed.'));
        }
    }

    /**
     * @author Saroj Patel
     * Dt: 12-12-2022
     * This function is used for Fetch rate plan of a room type.
     */

    public function guestDetails($hotel_id, $booking_id)
    {

        $invoice_id = substr($booking_id, 6);
        $guestDetails =  Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('crs_booking', 'crs_booking.invoice_id', '=', 'invoice_table.invoice_id')
            ->select('user_table.first_name', 'user_table.last_name', 'user_table.company_name', 'user_table.email_id', 'user_table.address', 'user_table.mobile', 'user_table.GSTIN', 'crs_booking.payment_type', 'crs_booking.guest_name', 'crs_booking.internal_remark', 'crs_booking.guest_remark', 'crs_booking.modification_remark')
            ->where('invoice_table.invoice_id', $invoice_id)
            ->where('invoice_table.hotel_id', $hotel_id) //booking status 
            ->first();

        if (isset($guestDetails['guest_name']) && !empty($guestDetails['guest_name'])) {
            $guest = explode(',', $guestDetails['guest_name']);
        } else {
            $guest = '';
        }

        if (isset($guestDetails->GSTIN)) {
            $business_booking = 1;
        } else {
            $business_booking = 0;
        }


        $room_details = HotelBooking::join('kernel.room_type_table', 'room_type_table.room_type_id', '=', 'hotel_booking.room_type_id')
            ->select('hotel_booking.room_type_id', 'hotel_booking.rooms', 'room_type_table.room_type')
            ->where('hotel_booking.invoice_id', $invoice_id)
            ->where('hotel_booking.hotel_id', $hotel_id)
            ->get();

        if (sizeof($room_details) > 1 && sizeof($guest) == 1) {
            $booking_type_name = 'GROUP';
        } else {
            $booking_type_name = 'FIT';
        }

        $rooms = [];
        $i = 0;
        $j = 0;
        foreach ($room_details as $key => $room_detail) {
            $guest_det = [];
            for ($i = 0; $i < $room_detail->rooms; $i++) {
                $guest_det[] = isset($guest[$j]) ? $guest[$j] : 'NA';
                $j++;
            }
            array_push($rooms, array('room_type_id' => $room_detail->room_type_id, 'room_type' => $room_detail->room_type, 'rooms' => $room_detail->rooms, 'guest_details' => $guest_det));
        }

        $guest_details['full_name'] = $guestDetails['first_name'] . ' ' . $guestDetails['last_name'];
        $guest_details['mobile'] = $guestDetails['mobile'];
        $guest_details['email'] = $guestDetails['email_id'];
        $guest_details['address'] = $guestDetails['address'];
        $guest_details['business_booking'] = $business_booking;
        $guest_details['company_name'] = $guestDetails['company_name'];
        $guest_details['company_address'] = $guestDetails['address'];
        $guest_details['GSTIN'] = $guestDetails['GSTIN'];
        $guest_details['booking_type_name'] = $booking_type_name;
        // $guest_details['guest_name'] = $guest;
        $guest_details['room_details'] = $rooms;
        $guest_details['internal_remark'] = $guestDetails['internal_remark'];
        $guest_details['guest_remark'] = $guestDetails['guest_remark'];
        $guest_details['modification_remark'] = $guestDetails['modification_remark'];
        $guest_details['group_guest_name'] = isset($guest[0]) ? $guest[0] : 'NA';


        if ($guest_details) {
            $res = array('status' => 1, "message" => 'Guest Details Fetched', 'data' => $guest_details);
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Guest Details Fetched Failed');
            return response()->json($res);
        }
    }

    /**
     * @author Saroj Patel
     * Dt: 12-12-2022
     * This function is used for Fetch rate plan of a room type.
     */


    public function saveGuestDetails(Request $request)
    {

        $data = $request->all();
        $modified_date = date('Y-m-d');
        $hotel_id = $data['hotel_id'];
        $invoice_id = substr($data['booking_id'], 6);
        $parts = explode(" ", $data['full_name']);
        $lastname = array_pop($parts);
        $firstname = implode(" ", $parts);
        $room_details = $data['room_details'];

        $booking_type_name =  $data['booking_type_name'];
        if ($booking_type_name == 'FIT') {
            $all_guest_details = [];
            foreach ($room_details as $room) {
                $guests = $room['guest_details'];
                foreach ($guests as $guest) {
                    $all_guest_details[] = $guest;
                }
            }
            $guest_details = implode(',', $all_guest_details);
        } else {
            $guest_details = $data['group_guest_name'];
        }


        $userdetails['first_name'] = $firstname;
        $userdetails['last_name'] = $lastname;
        $userdetails['mobile'] = $data['mobile'];
        $userdetails['email_id'] = $data['email_id'];
        $userdetails['address'] = $data['address'];

        if ($data['business_booking'] == 1) {
            $userdetails['company_name'] = $data['company_name'];
            $userdetails['GSTIN'] = $data['GSTIN'];
        }

        $users = Invoice::where('invoice_id', $invoice_id)->select('check_in_out', 'user_id')->first();

        $remove_left_br = substr($users->check_in_out, 1);
        $remove_right_br = substr($remove_left_br, 0, -1);
        $check_in_out = explode('-', $remove_right_br);
        $check_in = $check_in_out[0] . '-' . $check_in_out[1] . '-' . $check_in_out[2];
        $check_out = $check_in_out[3] . '-' . $check_in_out[4] . '-' . $check_in_out[5];

        $updatedetails = User::where('user_id', $users->user_id)->update($userdetails);


        if ($updatedetails) {

            $bookingDetails['guest_name'] = $guest_details;
            $bookingDetails['internal_remark'] = $data['internal_remark'];
            $bookingDetails['guest_remark'] = $data['guest_remark'];
            $bookingDetails['modification_remark'] = $data['modification_remark'];

            $bookingRes = CrsBooking::where('invoice_id', $invoice_id)->update($bookingDetails);
        }

        if ($updatedetails && $bookingRes) {

            $is_ids = PmsAccount::where('name', 'IDS NEXT')->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')->first();
            if ($is_ids) {
                $inv_data = $this->bookingEngineController->handleIds($data, $check_in, $check_out, $modified_date, $hotel_id, $users->user_id, 'Modify');
            }

            $getPMS_details = DB::connection('bookingjini_cm')->table('pms_account')->where('name', 'GEMS')->first();
            $hotel_info = explode(',', $getPMS_details->hotels);
            if (in_array($hotel_id, $hotel_info)) {
                $pushBookignToGems = $this->bookingEngineController->pushBookingToGems($invoice_id, true);
            }

            $res = array('status' => 1, "message" => 'Updated');
            return response()->json($res);
        } else {
            $res = array('status' => 0, "message" => 'Updated Failed');
            return response()->json($res);
        }
    }





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
            } else {

                $dateRange  = new \DatePeriod(
                    new \DateTime($from_date),
                    new \DateInterval('P1D'),
                    new \DateTime($to_date1)
                );

                foreach ($dateRange as $value) {
                    $min_price = 'NA';
                    $currentDate = $value->format('Y-m-d');
                    $availableRoomsDateWise[] = array('Date' => $currentDate, 'minimum_rates' => $min_price, 'hotel_id' => $hotel_id);
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
