<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Invoice;
use App\User;
Use App\Booking;
use App\CrsReservation; //class name from model
use App\HotelInformation;
use DB;
use App\Http\Controllers\Extranetv4\UpdateInventoryService;
use App\Http\Controllers\Extranetv4\ManageCrsRatePlanController;
use App\Http\Controllers\Extranetv4\InventoryService;
use App\Http\Controllers\Extranetv4\IpAddressService;
use App\OnlineTransactionDetail;
use App\MasterRoomType; //class name from model
use App\AgentCredit; //class name from model
use App\MasterHotelRatePlan; //class name from model\
use App\RoomRateSettings;
use App\AdminUser;
use App\ImageTable;
use App\CompanyDetails;
use App\CmOtaDetails;
use App\Http\Controllers\Controller;

//create a new class OfflineBookingController
class CrsReservationController extends Controller
{
    protected $roomRateService, $ipService;
    protected $updateInventoryService;
    protected $invService;
    public function __construct(InventoryService $invService, ManageCrsRatePlanController $roomRateService, UpdateInventoryService $updateInventoryService, IpAddressService $ipService)
    {
        $this->invService = $invService;
        $this->roomRateService = $roomRateService;
        $this->updateInventoryService = $updateInventoryService;
        $this->ipService = $ipService;
    }
    //validation rules
    private $fetch_reservation_rules = array(
        'hotel_id' => 'required',
        'for_user_id' => 'required'
    );
    //Custom Error Messages
    private $fetch_reservation_messages = ['hotel_id.required' => 'Hotel id is required.', 'for_user_id.required' => 'User id required'];
    //validation rules
    private $room_rate_rules = array(
        'hotel_id' => 'required',
        'for_user_id' => 'required',
        "from_date" => 'required',
        "to_date" => 'required'
    );
    //Custom Error Messages
    private $room_rate_messages = ['hotel_id.required' => 'Hotel id is required.', 'for_user_id.required' => 'User id required', "from_date" => 'From date is required', "to_date" => 'To date is required'];
    //validation rules
    private $rules = array(
        'hotel_id' => 'required | numeric',
        'from_date' => 'required ',
        'to_date' => 'required',
        'cart' => 'required',
        //'adjusted_amount'=>'required',
        //'comments'=>'required',
        'user_data' => 'required'
    );
    //Custom Error Messages
    private $messages = ['hotel_id.required' => 'The hotel id field is required.', 'hotel_id.numeric' => 'The hotel id must be numeric.', 'from_date.required' => 'Check in date is required.', 'to_date.required' => 'Check out is required.',
    //'adjusted_amount.required' => 'Adjusted amount is required.',
    //'comments.required' => 'comments is required.',
    'user_data.required' => 'user_data is required.'];
    /**
     * Get all pending reservation list
     * @author Godti Vinod
     * function getBookedReservations to get the pending reservation list
     *
     */
    public function getBookedReservations(int $hotel_id, $for_user_id, $from_date, $to_date, $type, Request $request)
    {
        $failure_message = 'Reservation details saving failed';
        $validator = Validator::make(array(
            'hotel_id' => $hotel_id,
            'for_user_id' => $for_user_id
        ) , $this->fetch_reservation_rules, $this->fetch_reservation_messages);
        if ($validator->fails())
        {
            return response()
                ->json(array(
                'status' => 0,
                'message' => $failure_message,
                'errors' => $validator->errors()
            ));
        }
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date . ' +1 day'));
        if ($type == 2)
        {
            $bet_date = 'check_in';
        }
        else if ($type == 1)
        {
            $bet_date = 'booking_date';
        }
        else
        {
            $bet_date = 'check_in';
        }
        $cond = 'hotel_booking.' . $bet_date;
        $data = DB::table('crs_reservation')->join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('company_table', 'user_table.company_id', '=', 'company_table.company_id')
            ->select('company_table.commission', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.address', 'user_table.mobile', 'invoice_table.room_type', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.booking_date', 'invoice_table.check_in_out', 'invoice_table.invoice', 'invoice_table.booking_status', 'invoice_table.invoice_id', 'invoice_table.hotel_name', 'crs_reservation.*')
            ->where('invoice_table.hotel_id', '=', $hotel_id)->where('crs_reservation.pay_status', '=', '0')
            ->where('crs_reservation.for_user_id', $for_user_id)->whereBetween($cond, array(
            $from_date,
            $to_date
        ))->distinct('invoice_table.invoice_id')
            ->orderBy('invoice_table.invoice_id', 'DESC')
            ->get();
        foreach ($data as $data_up)
        {
            $c_amt = $data_up->total_amount * ($data_up->commission / 100);
            $c_amt = $c_amt + ($c_amt * 0.18);
            $h_amt = $data_up->total_amount - $c_amt;
            $booking_id = date("dmy", strtotime($data_up->booking_date)) . str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
            $data_up->invoice = str_replace("#####", $booking_id, $data_up->invoice);
            $data_up->commission_amount = round($c_amt, 2);
            $data_up->hotelier_amount = round($h_amt, 2);
            $data_up->invoice_id = $booking_id;
        }
        if (sizeof($data) > 0)
        {
            $res = array(
                'status' => 1,
                "message" => "Internal reservations retrieved successfully",
                "data" => $data
            );
            return response()->json($res);
        }
        else
        {
            $res = array(
                'status' => - 1,
                "message" => "No internal reservations"
            );
            return response()->json($res);
        }
    }
    public function getcreditTransactions(int $hotel_id, $for_user_id, $from_date, $to_date, Request $request)
    {
        $failure_message = 'Credit Transaction details saving failed';
        $validator = Validator::make(array(
            'hotel_id' => $hotel_id,
            'for_user_id' => $for_user_id
        ) , $this->fetch_reservation_rules, $this->fetch_reservation_messages);
        if ($validator->fails())
        {
            return response()
                ->json(array(
                'status' => 0,
                'message' => $failure_message,
                'errors' => $validator->errors()
            ));
        }
        else
        {
            $bet_date = 'check_in';
        }
        $cond = 'hotel_booking.' . $bet_date;
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date . ' +1 day'));
        $data = DB::table('crs_reservation')->join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')
            ->join('kernel.hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
            ->select('invoice_table.paid_amount', 'invoice_table.check_in_out', 'invoice_table.invoice_id', 'crs_reservation.*')
            ->where('invoice_table.hotel_id', '=', $hotel_id)->where('crs_reservation.pay_status', '=', 1)
            ->where('crs_reservation.for_user_id', $for_user_id)->whereBetween($cond, array(
            $from_date,
            $to_date
        ))->orderBy('invoice_table.invoice_id', 'DESC')
            ->get();
        if (sizeof($data) > 0)
        {
            $res = array(
                'status' => 1,
                "message" => "Internal credit transaction retrieved successfully",
                "data" => $data
            );
            return response()->json($res);
        }
        else
        {
            $res = array(
                'status' => - 1,
                "message" => "No internal reservations"
            );
            return response()->json($res);
        }
    }
    /**
     * Get all Confirmed reservation list
     * @author Godti Vinod
     * function getConfirmedReservations to get the confirmed reservation list
     *
     */
    public function getConfirmedReservations(int $hotel_id, $for_user_id, $from_date, $to_date, $type, Request $request)
    {
        $failure_message = 'Reservation details saving failed';
        $validator = Validator::make(array(
            'hotel_id' => $hotel_id,
            'for_user_id' => $for_user_id
        ) , $this->fetch_reservation_rules, $this->fetch_reservation_messages);
        if ($validator->fails())
        {
            return response()
                ->json(array(
                'status' => 0,
                'message' => $failure_message,
                'errors' => $validator->errors()
            ));
        }
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date . ' +1 day'));
        if ($type == 2)
        {
            $bet_date = 'check_in';
        }
        else if ($type == 1)
        {
            $bet_date = 'booking_date';
        }
        else
        {
            $bet_date = 'check_in';
        }
        $cond = 'hotel_booking.' . $bet_date;
        $data = DB::table('crs_reservation')->join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')
            ->join('kernel.hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
            ->select('company_table.commission', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.address', 'user_table.mobile', 'invoice_table.room_type', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.booking_date', 'invoice_table.check_in_out', 'invoice_table.invoice', 'invoice_table.booking_status', 'invoice_table.invoice_id', 'invoice_table.hotel_name', 'crs_reservation.*')
            ->where('invoice_table.hotel_id', '=', $hotel_id)->where('invoice_table.booking_status', '=', 1)
            ->where('crs_reservation.for_user_id', $for_user_id)->whereBetween($cond, array(
            $from_date,
            $to_date
        ))->distinct('invoice_table.invoice_id')
            ->orderBy('invoice_table.invoice_id', 'DESC')
            ->get();
        foreach ($data as $data_up)
        {
            $c_amt = $data_up->total_amount * ($data_up->commission / 100);
            $c_amt = $c_amt + ($c_amt * 0.18);
            $h_amt = $data_up->total_amount - $c_amt;
            $booking_id = date("dmy", strtotime($data_up->booking_date)) . str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
            $data_up->invoice = str_replace("#####", $booking_id, $data_up->invoice);
            $data_up->commission_amount = round($c_amt, 2);
            $data_up->hotelier_amount = round($h_amt, 2);
            $data_up->invoice_id = $booking_id;
        }
        if (sizeof($data) > 0)
        {
            $res = array(
                'status' => 1,
                "message" => "Internal reservations retrieved successfully",
                "data" => $data
            );
            return response()->json($res);
        }
        else
        {
            $res = array(
                'status' => - 1,
                "message" => "No internal reservations"
            );
            return response()->json($res);
        }
    }
    /**
     * Get all Confirmed reservation list
     * @author Godti Vinod
     * function deleteReservation to get the hard delete reservation
     *
     */
    public function deleteReservation(int $crs_reserve_id, Request $request)
    {
        $failure_message = 'Reservation details deletion failed';
        $crs_reservation = DB::table('crs_reservation')->where('crs_reserve_id', $crs_reserve_id)->first();
        $invoice_id = $crs_reservation->invoice_id;
        $hotel_booking_model = Booking::where('invoice_id', $invoice_id)->get();
        foreach ($hotel_booking_model as $hbm)
        {
            $this->updateInventory($hbm, 'release');
        }
        if (DB::table('hotel_booking')->where('invoice_id', $invoice_id)->delete())
        {
            if (DB::table('invoice_table')
                ->where('invoice_id', $invoice_id)->delete())
            {
                if (DB::table('crs_reservation')
                    ->where('crs_reserve_id', $crs_reserve_id)->delete())
                {
                    $res = array(
                        'status' => 1,
                        "message" => "Reservations deleted successfully"
                    );
                    return response()->json($res);
                }
                else
                {
                    $res = array(
                        'status' => - 1,
                        "message" => "No internal reservations"
                    );
                    return response()->json($res);
                }
            }
        }
    }
    /**
     * Cancel reservation
     * @author Godti Vinod
     * function cancelReservation to cancel the reservation
     *
     */
    public function getCanceledReservations(int $hotel_id, $for_user_id, $from_date, $to_date, $type, Request $request)
    {
        $failure_message = 'Reservation details saving failed';
        $validator = Validator::make(array(
            'hotel_id' => $hotel_id,
            'for_user_id' => $for_user_id
        ) , $this->fetch_reservation_rules, $this->fetch_reservation_messages);
        if ($validator->fails())
        {
            return response()
                ->json(array(
                'status' => 0,
                'message' => $failure_message,
                'errors' => $validator->errors()
            ));
        }
        $from_date = date('Y-m-d', strtotime($from_date));
        $to_date = date('Y-m-d', strtotime($to_date . ' +1 day'));
        if ($type == 2)
        {
            $bet_date = 'check_in';
        }
        else if ($type == 1)
        {
            $bet_date = 'booking_date';
        }
        else
        {
            $bet_date = 'check_in';
        }
        $cond = 'hotel_booking.' . $bet_date;
        $data = DB::table('crs_reservation')->join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
            ->select('company_table.commission', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.address', 'user_table.mobile', 'invoice_table.room_type', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.booking_date', 'invoice_table.check_in_out', 'invoice_table.invoice', 'invoice_table.booking_status', 'invoice_table.invoice_id', 'invoice_table.hotel_name', 'crs_reservation.adjusted_amount', 'crs_reservation.comments')
            ->where('invoice_table.hotel_id', '=', $hotel_id)->where('invoice_table.booking_status', '=', 0)
            ->where('crs_reservation.pay_status', '=', 1)
            ->where('crs_reservation.for_user_id', $for_user_id)->whereBetween($cond, array(
            $from_date,
            $to_date
        ))->orderBy('invoice_table.invoice_id', 'DESC')
            ->get();
        if (sizeof($data) > 0)
        {
            $res = array(
                'status' => 1,
                "message" => "Internal reservations retrieved successfully",
                "data" => $data
            );
            return response()->json($res);
        }
        else
        {
            $res = array(
                'status' => - 1,
                "message" => "No internal reservations"
            );
            return response()->json($res);
        }
    }
    /**
     * Get all Confirmed reservation list
     * @author Godti Vinod
     * function GetAllReservation to get the reservation list
     *
     */
    public function cancelReservation(int $crs_reserve_id, Request $request)
    {
        $cancel_comments = $request->input('cancel_comments');
        $failure_message = 'Reservation cancellation failed';
        $crs_reservation = DB::table('crs_reservation')->where('crs_reserve_id', $crs_reserve_id)->first();
        $invoice_id = $crs_reservation->invoice_id;
        $invoice = Invoice::where('invoice_id', $invoice_id)->first();
        if ($invoice->booking_status == 1)
        {
            $hotel_booking_model = Booking::where('invoice_id', $invoice_id)->get();
            foreach ($hotel_booking_model as $hbm)
            {
                $this->updateInventory($hbm, 'release');
            }
            //update the cancel status
            if (DB::table('invoice_table')->where('invoice_id', $invoice_id)->update(['booking_status' => 0]))
            {
                //Update cancellation comments
                if (DB::table('crs_reservation')
                    ->where('crs_reserve_id', $crs_reserve_id)->update(['cancel_comments' => $cancel_comments]))
                {
                    $res = array(
                        'status' => 1,
                        "message" => "Reservation canceled successfully"
                    );
                    return response()->json($res);
                }
                else
                {
                    $res = array(
                        'status' => - 1,
                        "message" => "No internal reservations found"
                    );
                    return response()->json($res);
                }
            }
        }
        else
        {
            $res = array(
                'status' => 0,
                "message" => "Confirmed reservations only can be cancelled"
            );
            return response()->json($res);
        }
    }
    /*------------------------------------- UPDATE INVENTORY (START) ------------------------------------*/
    public function updateInventory($bookingDeatil, $type)
    {
        $mindays = 0;
        $updated_inv = array();
        $inv_update = array();
        $inv_data = $this
            ->invService
            ->getInventeryByRoomTYpe($bookingDeatil->room_type_id, $bookingDeatil->check_in, $bookingDeatil->check_out, $mindays);
        $ota_datas = CmOtaDetails::select('ota_id')->where('hotel_id', '=', $bookingDeatil->hotel_id)
            ->where('is_active', '=', 1)
            ->get();
        $ota_ids = array(
            0
        ); //0 for BE update
        foreach ($ota_datas as $ota_data)
        {
            array_push($ota_ids, $ota_data->ota_id);
        }
        if ($inv_data)
        {
            foreach ($inv_data as $inv)
            {
                $updated_inv["hotel_id"] = $bookingDeatil->hotel_id;
                $updated_inv["room_type_id"] = $inv['room_type_id'];
                $updated_inv["user_id"] = 0; //User id set CM
                $updated_inv["ota_id"] = $ota_ids;
                $updated_inv["date_from"] = $inv['date'];
                $updated_inv["date_to"] = date('Y-m-d', strtotime($inv['date']));
                if ($type == "consume")
                {
                    $updated_inv["no_of_rooms"] = $inv['no_of_rooms'] - $bookingDeatil->rooms; //Deduct inventory
                    
                }
                if ($type == "release")
                {
                    $updated_inv["no_of_rooms"] = $inv['no_of_rooms'] + $bookingDeatil->rooms; //Release inventory
                    
                }
                $updated_inv["client_ip"] = '1.1.1.1'; //\Illuminate\Http\Request::ip();//As server is updating inventory automatically afetr succ booking
                $resp = $this
                    ->updateInventoryService
                    ->updateInv($updated_inv);
                if ($resp['be_status'] = "Inventory update successfull")
                {
                    array_push($inv_update, 1);
                }
            }
        }
        $inv_update_status = true;
        foreach ($inv_update as $up)
        {
            if (!$up == 1)
            {
                $inv_update_status = false;
            }
        }
        return $inv_update_status;
    }
    //Get Inventory
    
    /**
     * Get Inventory By Hotel id
     * get all record of Inventory by following params
     * @params for_user_id(M)
     * @params hotel_id(M)
     * @params date_from (Checkin date)
     * @params date_to (Checkout date)
     * @auther Godti Vinod
     * function getInventory for fetching data
     *
     */
    public function getInventory(int $for_user_id, int $hotel_id, string $date_from, string $date_to, Request $request)
    {
        $failure_message = 'Inventory not available to reserve';
        $validator = Validator::make(array(
            "for_user_id" => $for_user_id,
            "hotel_id" => $hotel_id,
            "from_date" => $date_from,
            "to_date" => $date_to
        ) , $this->room_rate_rules, $this->room_rate_messages);
        if ($validator->fails())
        {
            return response()
                ->json(array(
                'status' => 0,
                'message' => $failure_message,
                'errors' => $validator->errors()
            ));
        }
        $status = "invalid";
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_to = date('Y-m-d', strtotime($date_to));
        $conditions = array(
            'hotel_id' => $hotel_id,
            'is_trash' => 0
        );
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'image', 'rack_price', 'extra_person', 'extra_child')->where($conditions)->get();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $info_id = AdminUser::select('role_id')->where('admin_id', $for_user_id)->first();
        if ($room_types)
        {
            foreach ($room_types as $room)
            {
                $room['min_inv'] = 0;
                $room['image'] = explode(',', $room['image']); //Converting string to array
                $room['image'] = $this->getImages(array(
                    $room['image'][0]
                )); //Getting actual amenity names
                if (is_object($room['image']) && sizeof($room['image']) > 0)
                {
                    $room['image'] = $room['image'][0]->image_name;
                }
                else
                {
                    $room['image'] = $this->getImages(array(
                        1
                    ));
                    $room['image'] = $room['image'][0]->image_name;
                }
                $data = $this
                    ->roomRateService
                    ->getInventeryByRoomType($for_user_id, $room['room_type_id'], $date_from, $date_to);
                foreach ($data as $inv_room)
                {
                    if ($room['min_inv'] == 0)
                    {
                        $room['min_inv'] = $inv_room['no_of_rooms'];
                    }
                    if ($inv_room['no_of_rooms'] <= $room['min_inv'])
                    {
                        $room['min_inv'] = $inv_room['no_of_rooms'];
                    }
                }
                $room['inv'] = $data;
                if ($info_id->role_id == 4)
                {
                    $room_type_n_rate_plans = RoomRateSettings::join('rate_plan_table as a', function ($join)
                    {
                        $join->on('roomrate_settings.rate_plan_id', '=', 'a.rate_plan_id');
                    })
                        ->select('a.rate_plan_id', 'plan_type', 'plan_name', 'agent_price')
                        ->where('roomrate_settings.hotel_id', $hotel_id)->where('roomrate_settings.is_trash', 0)
                        ->where('roomrate_settings.room_type_id', $room['room_type_id'])->orderBy('roomrate_settings.created_at', '=', SORT_DESC)
                        ->distinct()
                        ->get();
                }
                else if ($info_id->role_id == 7)
                {
                    $room_type_n_rate_plans = RoomRateSettings::join('rate_plan_table as a', function ($join)
                    {
                        $join->on('roomrate_settings.rate_plan_id', '=', 'a.rate_plan_id');
                    })
                        ->select('a.rate_plan_id', 'plan_type', 'plan_name', 'corporate_price')
                        ->where('roomrate_settings.hotel_id', $hotel_id)->where('roomrate_settings.is_trash', 0)
                        ->where('roomrate_settings.room_type_id', $room['room_type_id'])->orderBy('roomrate_settings.created_at', '=', SORT_DESC)
                        ->distinct()
                        ->get();
                }
                $room['min_room_price'] = 0;
                foreach ($room_type_n_rate_plans as $key => $all_types)
                {
                    $rate_plan_id = (int)$all_types['rate_plan_id'];
                    $data = $this
                        ->roomRateService
                        ->getRatesByRoomnRatePlan($for_user_id, $room['room_type_id'], $rate_plan_id, $date_from, $date_to);
                    if ($data)
                    {
                        foreach ($data as $info)
                        {
                            if ($room['min_room_price'] == 0)
                            {
                                $room['min_room_price'] = $info['bar_price'];
                            }
                            if ($info['bar_price'] <= $room['min_room_price'] && $info['bar_price'] != null)
                            {
                                $room['min_room_price'] = $info['bar_price'];
                            }
                            if ($info['bar_price'] != 0 && $info['bar_price'] != null)
                            {
                                $all_types['bar_price'] = $info['bar_price'];
                                $all_types['rates'] = $data;
                            }
                        }
                    }
                }
                if ($room['min_room_price'] != 0 && $room['min_room_price'] != null)
                {
                    $room['rate_plans'] = $room_type_n_rate_plans;
                }
            }
            $res = array(
                'status' => 1,
                'message' => "Hotel inventory retrieved successfully ",
                'data' => $room_types
            );
            return response()->json($res);
        }
        else
        {
            $res = array(
                'status' => 1,
                'message' => "Hotel inventory not avaviable due to invalid information"
            );
            return response()->json($res);
        }
    }
    /*
    Get HOTEL Images  of the hotel By Hotel id
    * @auther Godti Vinod
    * function getHotelAmen for fetching data
    **/
    public function getImages($imgs)
    {
        $images = DB::table('image_table')->whereIn('image_id', $imgs)->select('image_name')
            ->get();
        if ($images)
        {
            return $images;
        }
        else
        {
            return array();
        }
    }
    //Bookings save starts here
    public function newReservation(Request $request)
    {
        $hotel_id = $request->input('hotel_id');
        $percentage = 0; //THis shoud be set from Mail invoice
        $invoice = new Invoice();
        $booking = new Booking();
        $crs_reservation = new CrsReservation();
        $failure_message = 'Booking failed due to insuffcient booking details';
        $validator = Validator::make($request->all() , $this->rules, $this->messages);
        if ($validator->fails())
        {
            return response()
                ->json(array(
                'status' => 0,
                'message' => $failure_message,
                'errors' => $validator->errors()
            ));
        }
        $cart = $request->input('cart');
        $public_user_data = $request->input('user_data');
        $from_date = date('Y-m-d', strtotime($request->input('from_date')));
        $to_date = date('Y-m-d', strtotime($request->input('to_date')));
        $client_ip = $this
            ->ipService
            ->getIPAddress();
        $comments = $request->input('comments');
        $for_user_id = $request->input('for_user_id');
        $operator_user_id = $request
            ->auth->admin_id;
        $adjusted_amount = $request->input('adjusted_amount');
        $chkPrice = false;
        //Check room price && Check Qty && Check Extra adult prce && Check Extra child price && Check GSt && Discounted Price
        $validCart = $this->checkRoomPrice($for_user_id, $cart, $from_date, $to_date, $hotel_id);
        if ($validCart)
        {
            $inv_data = array();
            $hotel = $this->getHotelInfo($hotel_id);
            $booking_data = array();
            $inv_data['check_in_out'] = "[" . $from_date . '-' . $to_date . "]";
            $inv_data['room_type'] = json_encode($this->prepareRoomType($cart));
            $inv_data['hotel_id'] = $hotel_id;
            $inv_data['hotel_name'] = $hotel->hotel_name;
            $inv_data['user_id'] = $this->saveGuestDetails($public_user_data);
            $inv_data['total_amount'] = number_format((float)$this->getTotal($cart) , 2, '.', '');
            $inv_data['paid_amount'] = $inv_data['total_amount'] - $adjusted_amount;
            $inv_data['paid_amount'] = number_format((float)$inv_data['paid_amount'], 2, '.', '');
            $inv_data['discount_code'] = "";
            $inv_data['paid_service_id'] = 0;
            $inv_data['extra_details'] = json_encode($this->getExtraDetails($cart));
            $inv_data['booking_date'] = date('Y-m-d H:i:s');
            $inv_data['visitors_ip'] = $client_ip;
            $inv_data['ref_no'] = rand() . strtotime("now");
            $inv_data['booking_status'] = 2; //Initially Booking status set 2 ,For the pending status
            $inv_data['invoice'] = $this->createInvoice($hotel_id, $cart, $from_date, $to_date, $inv_data['user_id'], $percentage, $adjusted_amount);
            $failure_message = "Invoice details saving failed";
            if ($invoice->fill($inv_data)->save())
            {
                $cur_invoice = Invoice::where('ref_no', $inv_data['ref_no'])->first();
                $booking_data = $this->prepare_booking_data($cart, $cur_invoice->invoice_id, $from_date, $to_date, $inv_data['user_id'], $inv_data['booking_date'], $hotel_id);
                $crs_data['adjusted_amount'] = $adjusted_amount;
                $crs_data['comments'] = $comments;
                $crs_data['for_user_id'] = $for_user_id;
                $crs_data['invoice_id'] = $cur_invoice->invoice_id;
                $crs_data['operator_user_id'] = $operator_user_id;
                if (DB::table('hotel_booking')->insert($booking_data))
                {
                    if ($crs_reservation->fill($crs_data)->save())
                    {
                        if ($this->preinvoiceMail($cur_invoice->invoice_id))
                        {
                            $hotel_booking_model = Booking::where('invoice_id', $cur_invoice->invoice_id)
                                ->get();
                            foreach ($hotel_booking_model as $hbm)
                            {
                                $this->updateInventory($hbm, 'consume');
                            }
                            $res = array(
                                "status" => 1,
                                "message" => "New bookings done successfully"
                            );
                            return response()->json($res);
                        }
                        else
                        {
                            $res = array(
                                'status' => - 1,
                                "message" => $failure_message
                            );
                            $res['errors'][] = "Internal server error";
                            return response()->json($res);
                        }
                    }
                    else
                    {
                        $res = array(
                            'status' => - 1,
                            "message" => $failure_message
                        );
                        $res['errors'][] = "Internal server error";
                        return response()->json($res);
                    }
                }
                else
                {
                    $res = array(
                        'status' => - 1,
                        "message" => $failure_message
                    );
                    $res['errors'][] = "Internal server error";
                    return response()->json($res);
                }
            }
            else
            {
                $res = array(
                    'status' => - 1,
                    "message" => $failure_message
                );
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        }
        else
        {
            $res = array(
                'status' => - 1,
                "message" => "Booking failed due to data tempering,Please try again later"
            );
            return response()->json($res);
        }
    }
    //Save Guest Details
    public function saveGuestDetails($user_data)
    {
        $user = new User();
        $user_data['password'] = uniqid(); //To generate unique rsndom number
        $password = $user_data['password'];
        $user_data['password'] = Hash::make($user_data['password']); //Password encryption
        $user_data['status'] = 1;
        $user_data['registered_date'] = date('Y-m-d');
        if ($user->checkStatus($user_data['mobile'], $user_data['email_id'], $user_data['company_id']) == "new")
        {
            if ($user->fill($user_data)->save())
            {
                return $user->user_id;
            }
        }
        else if ($user->checkStatus($user_data['mobile'], $user_data['email_id'], $user_data['company_id']) == "exist")
        {
            $user_data = $user->where("email_id", $user_data['email_id'])->where("company_id", $user_data['company_id'])->first();
            return $user_data->user_id;
        }
    }
    //Get Hotel info from the Hotel id
    public function getHotelInfo($hotel_id)
    {
        $hotel = HotelInformation::select('*')->where('hotel_id', $hotel_id)->first();
        return $hotel;
    }
    //Prepare the Room Types TO insert into database
    public function prepareRoomType($cart)
    {
        $room_types = array();
        foreach ($cart as $cartItem)
        {
            $room_type = $cartItem['room_type'];
            $plan_type = $cartItem['plan_type'];
            $rooms = $cartItem['rooms'];
            array_push($room_types, sizeof($rooms) . ' ' . $room_type . '(' . $plan_type . ')');
        }
        return $room_types;
    }
    //Get total price in the cart
    public function getTotal($cart)
    {
        $total_price = 0;
        foreach ($cart as $cartItem)
        {
            $total_extra_price = 0;
            foreach ($cartItem['rooms'] as $cart_room)
            {
                $total_extra_price += $cart_room['extra_child_price'] + $cart_room['extra_adult_price'];
            }
            $total_price += $cartItem['price'] + $cartItem['gst_price'] + $total_extra_price;
        }
        $total_price = round($total_price, 2);
        return $total_price;
    }
    //Get the getExtra details such as Room Type id and Selected Child and Adults
    public function getExtraDetails($cart)
    {
        $extra_details = array();
        foreach ($cart as $cartItem)
        {
            foreach ($cartItem['rooms'] as $room)
            {
                array_push($extra_details, array(
                    $cartItem['room_type_id'] => array(
                        $room['selected_adult'],
                        $room['selected_child']
                    )
                ));
            }
        }
        return $extra_details;
    }
    //Prepare the booking table row to Insert
    public function prepare_booking_data($cart, int $invoice_id, $from_date, $to_date, $user_id, $booking_date, $hotel_id)
    {
        $booking_data = array();
        $booking_data_arr = array();
        foreach ($cart as $cartItem)
        {
            $booking_data['room_type_id'] = $cartItem['room_type_id'];
            $booking_data['rooms'] = sizeof($cartItem['rooms']);
            $booking_data['check_in'] = $from_date;
            $booking_data['check_out'] = $to_date;
            $booking_data['booking_status'] = 2; //Intially Un Paid
            $booking_data['user_id'] = $user_id;
            $booking_data['booking_date'] = $booking_date;
            $booking_data['invoice_id'] = $invoice_id;
            $booking_data['hotel_id'] = $hotel_id;
            array_push($booking_data_arr, $booking_data);
        }
        return $booking_data_arr;
    }
    /********============Pre Check Room Price,Qty,GST,Discounts===========********* */
    public function checkRoomPrice($for_user_id, $cart, $from_date, $to_date, $hotel_id)
    {
        $qty = 0;
        $chkQty = array(); //Initially chkQty set to false
        $chkRmRate = array();
        foreach ($cart as $cartItem)
        {
            $room_type_id = $cartItem['room_type_id'];
            $rooms = $cartItem['rooms'];
            $room_price = $cartItem['price'];
            $gst_price = $cartItem['gst_price'];
            $qty = sizeof($rooms); //No of rooms is size of the rooms array
            array_push($chkQty, $this->CheckQty($for_user_id, $room_type_id, $qty, $from_date, $to_date));
            array_push($chkRmRate, $this->CheckRoomRate($for_user_id, $room_type_id, $rooms, $from_date, $to_date, $room_price, $gst_price));
        }
        $rmStatus = true;
        $qty_status = true;
        //Check all the room rates status
        foreach ($chkQty as $chk_qty)
        {
            if ($chk_qty != 1)
            {
                $qty_status = false;
            }
        }
        foreach ($chkRmRate as $chkRm)
        {
            if ($chkRm != 1)
            {
                $rmStatus = false;
            }
        }
        ///Check all the status
        if ($qty_status && $rmStatus)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    //*************Pre Check qunatity of the rooms*************************/
    public function CheckQty($for_user_id, $room_type_id, $qty, $from_date, $to_date)
    {
        $min_inv = 0;
        $data = $this
            ->roomRateService
            ->getInventeryByRoomType($for_user_id, $room_type_id, $from_date, $to_date, 0);
        foreach ($data as $inv_room)
        {
            if ($min_inv == 0)
            {
                $min_inv = $inv_room['no_of_rooms'];
            }
            if ($inv_room['no_of_rooms'] <= $min_inv)
            {
                $min_inv = $inv_room['no_of_rooms'];
            }
        }
        //Check qty
        if ($qty <= $min_inv)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    //*************Pre Check ROOM rate of the rooms*************************/
    public function CheckRoomRate($for_user_id, $room_type_id, $rooms, $from_date, $to_date, $curr_room_price, $curr_gst_price)
    {
        $date1 = date_create($from_date);
        $date2 = date_create($to_date);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a"); //Diffrence betwen checkin and checkout date
        $diff1 = date_diff($date3, $date1); ///Diffrence betwen booking date that is today and checkin date
        $diff1 = $diff1->format("%a");
        $room_price = 0;
        $extra_adult_ok = false;
        $extra_child_ok = false;
        $multiple_occ = array();
        $conditions = array(
            'room_type_id' => $room_type_id,
            'is_trash' => 0
        );
        $room_types = MasterRoomType::select('room_type', 'room_type_id', 'max_people', 'max_child', 'image', 'rack_price', 'extra_person', 'extra_child')->where($conditions)->first();
        $tot_extra_adult_price = 0;
        $tot_extra_child_price = 0;
        $max_adult = $room_types->max_people;
        $max_child = $room_types->max_child;
        foreach ($rooms as $room)
        {
            $curr_extra_adult_price = $room['extra_adult_price'];
            $curr_extra_child_price = $room['extra_child_price'];
            $selected_adult = $room['selected_adult'];
            $selected_child = $room['selected_child'];
            $extra_adult_price = 0;
            $extra_child_price = 0;
            $room_rates = $this
                ->roomRateService
                ->getRatesByRoomnRatePlan($for_user_id, $room_type_id, $room['rate_plan_id'], $from_date, $to_date);
            foreach ($room_rates as $room_rate)
            {
                $extra_adult_price += $room_rate['extra_adult_price'];
                $extra_child_price += $room_rate['extra_child_price'];
                if ($selected_adult < $max_adult)
                {
                    $adult = $selected_adult - 1; //Array
                    if ($room_rate['multiple_occupancy'][$adult] > 0)
                    {
                        $room_price += $room_rate['multiple_occupancy'][$adult];
                    }
                    else
                    {
                        $room_price += $room_rate['bar_price'];
                    }
                }
                else
                {
                    $room_price += $room_rate['bar_price'];
                }
                //array_push($multiple_occ,$room_rate['multiple_occupancy']);
                
            }
            //Check extra adult price
            $total_extra_child_price = 0;
            $total_extra_adult_price = 0;
            if ($selected_adult > $max_adult)
            {
                $no_of_extra_adult = $selected_adult - $max_adult;
                $total_extra_adult_price = $no_of_extra_adult * $extra_adult_price;
                if ($curr_extra_adult_price == $total_extra_adult_price)
                {
                    $extra_adult_ok = true;
                }
            }
            else if ($selected_adult == $max_adult)
            {
                $extra_adult_ok = true;
            }
            else
            {
                $extra_adult_ok = true; //This case covered inside loop
                
            }
            //Check extra child price
            if ($selected_child > $max_child)
            {
                $no_of_extra_child = $selected_child - $max_child;
                $total_extra_child_price = $no_of_extra_child * $extra_child_price;
                if ($curr_extra_child_price == $total_extra_child_price)
                {
                    $extra_child_ok = true;
                }
            }
            else if ($selected_child == $max_child)
            {
                $extra_child_ok = true;
            }
            else
            {
                $extra_child_ok = true;
            }
            $tot_extra_adult_price += $total_extra_adult_price;
            $tot_extra_child_price += $total_extra_child_price;
        }
        ///To check the discounted price
        $chk_gst_ok = false;
        //TO check the GST
        $price = $room_price + $tot_extra_child_price + $tot_extra_adult_price;
        $gst_price = $this->getGstPrice($diff, sizeof($rooms) , $room_type_id, $price); //TO get the GSt price
        if (round($gst_price, 2) == round($curr_gst_price, 2))
        {
            $chk_gst_ok = true;
        }
        ///Check all the conditions
        if (round($curr_room_price, 2) == round($room_price, 2) && $extra_child_ok && $extra_adult_ok && $chk_gst_ok)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    //*************GET the GST of the Price of a Individual room*****************/
    public function getGstPrice($no_of_nights, $no_of_rooms, $room_type_id, $price)
    {
        $chek_price = ($price / $no_of_nights) / $no_of_rooms;
        $gstPercent = $this->checkGSTPercent($room_type_id, $chek_price);
        $gstPrice = (($price) * $gstPercent) / 100;
        $gstPrice = round($gstPrice, 2);
        return $gstPrice;
    }
    //*************Update the GST % of the Individual room*****************/
    public function checkGSTPercent($room_type_id, $price)
    {
        $conditions = array(
            'room_type_id' => $room_type_id,
            'is_trash' => 0
        );
        $room_types = MasterRoomType::select('rack_price')->where($conditions)->first();
        $rackPrice = $room_types->rack_price;
        /*if($price>$rackPrice)
        {
        $rackPrice=$price;
        }*/
        if ($rackPrice < 1000)
        {
            return 0;
        }
        else if ($rackPrice >= 1000 && $rackPrice < 2500)
        {
            return 12;
        }
        else if ($rackPrice >= 2500 && $rackPrice < 7500)
        {
            return 18;
        }
        else if ($rackPrice >= 7500)
        {
            return 28;
        }
    }
    //Get User details
    public function getUserDetails($user_id)
    {
        $user = User::select('*')->where('user_id', $user_id)->first();
        return $user;
    }
    public function createInvoice($hotel_id, $cart, $check_in, $check_out, $user_id, $percentage)
    {
        $booking_id = "#####";
        $booking_date = date('Y-m-d');
        $booking_date = date("jS M, Y", strtotime($booking_date));
        $hotel_details = $this->getHotelInfo($hotel_id);
        $u = $this->getUserDetails($user_id);
        $dsp_check_in = date("jS M, Y", strtotime($check_in));
        $dsp_check_out = date("jS M, Y", strtotime($check_out));
        $date1 = date("Y-m-d", strtotime($check_in));
        $date2 = date("Y-m-d", strtotime($check_out));
        $diff = abs(strtotime($check_out) - strtotime($check_in));
        $years = floor($diff / (365 * 60 * 60 * 24));
        $months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
        $day = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));
        $total_adult = 0;
        $total_child = 0;
        $total_cost = 0;
        $all_room_type_name = "";
        $paid_service_details = "";
        $all_rows = "";
        $total_discount_price = 0;
        $total_price_after_discount = 0;
        $total_tax = 0;
        foreach ($cart as $cartItem)
        {
            $i = 1;
            $total_price = 0;
            $all_room_type_name .= ',' . sizeof($cartItem['rooms']) . ' ' . $cartItem['room_type'];
            $every_room_type = "";
            $all_rows .= '<tr><td rowspan="' . sizeof($cartItem['rooms']) . '" colspan="2">' . $cartItem['room_type'] . '(' . $cartItem['plan_type'] . ')</td>';
            foreach ($cartItem['rooms'] as $rooms)
            {
                $ind_total_price = 0;
                $total_adult += $rooms['selected_adult'];
                $total_child += $rooms['selected_child'];
                $ind_total_price = $rooms['bar_price'] + $rooms['extra_adult_price'] + $rooms['extra_child_price'];
                if ($i == 1)
                {
                    $all_rows = $all_rows . '<td  align="center">' . $i . '</td>
<td  align="center"> Rs.' . round($rooms['bar_price'] / $day) . '</td>
<td  align="center">' . round($rooms['extra_adult_price'] / $day) . '</td>
<td  align="center">' . round($rooms['extra_child_price'] / $day) . '</td>
<td  align="center">' . $day . '</td>
<td  align="center">Rs.' . $ind_total_price . '</td>
</tr>';
                }
                else
                {
                    $all_rows = $all_rows . '<tr><td  align="center">' . $i . '</td>
<td  align="center"> Rs.' . round($rooms['bar_price'] / $day) . '</td>
<td  align="center">' . round($rooms['extra_adult_price'] / $day) . '</td>
<td  align="center">' . round($rooms['extra_child_price'] / $day) . '</td>
<td  align="center">' . $day . '</td>
<td  align="center">Rs.' . $ind_total_price . '</td>
</tr>';
                }
                $i++;
                $total_price += $ind_total_price;
            }
            $total_cost += $total_price;
            $total_tax += $cartItem['gst_price'];
            $total_tax = number_format((float)$total_tax, 2, '.', '');
            $every_room_type = $every_room_type . '
<tr>
<td colspan="7" align="right" style="font-weight: bold;">Total &nbsp;</td>
<td align="center" style="font-weight: bold;">Rs. ' . $total_price . '</td>
</tr>
';
            $all_rows = $all_rows . $every_room_type;
        }
        $total_amt = $total_price;
        $total_amt = number_format((float)$total_amt, 2, '.', '');
        $total = $total_amt + $total_tax;
        $total = number_format((float)$total, 2, '.', '');
        if ($percentage == 0 || $percentage == 100)
        {
            /*if($adjusted_amount>0)
            {
                $paid_amount=$total-$adjusted_amount;
            }
            else
            {
                $paid_amount=$total;
            }
            $paid_amount = number_format((float)$paid_amount, 2, '.', '');*/
            $paid_amount = $total;
            $paid_amount = number_format((float)$paid_amount, 2, '.', '');
        }
        else
        {
            $paid_amount = $total * $percentage / 100;
            $paid_amount = number_format((float)$paid_amount, 2, '.', '');
        }
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
<div style="font-weight: bold; font-size: 22px; color:#fff; background-color: #1d99b5; padding: 5px;">' . $hotel_details['hotel_name'] . '</div>
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
<td>' . $total_adult . '</td>
</tr>
<tr>
<td>TOTAL CHILD</td>
<td>' . $total_child . '</td>
</tr>
<tr>
<td>NUMBER OF NIGHTS</td>
<td>' . $day . '</td>
</tr>
<tr>
<td>NUMBER OF ROOMS & TYPE OF ROOM</td>
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
<td class="stripe" colspan="7" align="right">Total Room Rate&nbsp;&nbsp;</td>
<td class="stripe" align="center">Rs. ' . $total_cost . '</td>
</tr>
<tr>
<td class="stripe" colspan="7" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
<td class="stripe" align="center">Rs. ' . $total_tax . '</td>
</tr>
<tr>
<td class="stripe" colspan="7" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
<td class="stripe" align="center">Rs. ' . $total . '</td>
</tr>
<tr>
<td colspan="7" align="right">Total Paid Amount&nbsp;&nbsp;</td>
            <td align="center">Rs. <span id="pd_amt">' . $paid_amount . '</span></td>
</tr>
        <tr>
<td colspan="7" align="right">Pay at hotel&nbsp;&nbsp;</td>
            <td align="center">Rs. <span id="du_amt">' . $due_amount . '</span></td>
</tr>
        <tr>
<td colspan="8"><p style="color: #ffffff;">* </p></td>
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
    /*------------------------------------- INVOICE MAIL (START) ------------------------------------*/
    public function preinvoiceMail($id)
    {
        $company_details = new CompanyDetails();
        //$invoice        = $this->successInvoice($id);
        $details = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')->join('hotel_booking', 'invoice_table.user_id', '=', 'hotel_booking.user_id')
            ->select('invoice_table.hotel_name', 'invoice_table.room_type', 'invoice_table.total_amount', 'invoice_table.user_id', 'invoice_table.paid_amount', 'invoice_table.check_in_out', 'invoice_table.booking_date', 'invoice_table.booking_status', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.address', 'user_table.mobile', 'hotel_booking.rooms', 'hotel_booking.check_in', 'hotel_booking.check_out', 'invoice_table.hotel_id')
            ->where('invoice_table.invoice_id', $id)->first();
        $crs_reservation = CrsReservation::join('admin_table', 'crs_reservation.for_user_id', '=', 'admin_table.admin_id')->where('crs_reservation.invoice_id', $id)->first();
        if ($crs_reservation->role_id == 4) //Agent Commision
        
        {
            $agent_commision = ($details->paid_amount * $crs_reservation->agent_commission) / 100;
            $details->paid_amount = $details->paid_amount - $agent_commision;
        }
        $hoteldetails = DB::table('kernel.hotels_table')->select('hotels_table.hotel_address', 'hotels_table.mobile', 'hotels_table.exterior_image', 'hotels_table.email_id', 'hotels_table.company_id')
            ->where('hotel_id', $details->hotel_id)
            ->first();
        $image_id = explode(',', $hoteldetails->exterior_image);
        $images = ImageTable::select('image_name')->where('image_id', $image_id[0])->where('hotel_id', $details->hotel_id)
            ->first();
        if ($images)
        {
            $hotel_image = $images->image_name;
        }
        else
        {
            $hotel_image = "";
        }
        $email_id = explode(',', $hoteldetails->email_id);

        if (is_array($email_id))
        {
            $hoteldetails->email_id = $email_id[0];
        }
        $mobile = explode(',', $hoteldetails->mobile);
        if (is_array($mobile))
        {
            $hoteldetails->mobile = $mobile[0];
        }
        $companyDetails = $company_details->select('company_url')
            ->where('company_id', $hoteldetails->company_id)
            ->first();
        $secureHash = 'https://be.bookingjini.com/crs/payment/' . base64_encode($id);
        $email = $crs_reservation->username; //Agent or Corporate EMail id
        $name = $details->first_name . " " . $details->last_name;
        $formated_invoice_id = date("dmy", strtotime($details->booking_date)) . str_pad($id, 4, '0', STR_PAD_LEFT);
        $supplied_details = array(
            'invoice_id1' => $formated_invoice_id,
            'name' => $name,
            'check_in' => $details->check_in,
            'check_out' => $details->check_out,
            'room_type' => $details->room_type,
            'booking_date' => $details->booking_date,
            'user_mobile' => $details->mobile,
            'hotel_display_name' => $details->hotel_name,
            'hotel_address' => $hoteldetails->hotel_address,
            'hotel_mobile' => $hoteldetails->mobile,
            'image_name' => $hotel_image,
            'total' => $details->total_amount,
            'paid' => $details->paid_amount,
            'hotel_email_id' => $hoteldetails->email_id,
            'user_email_id' => $email,
            'url' => $secureHash
        );
        if ($details->sendMail($email, 'emails.mailInvoiceTemplate', "Pay now to confirm booking", $supplied_details))
        {
            $res = array(
                'status' => 1,
                "message" => 'Mail invoice sent successfully'
            );
            return response()->json($res);
        }
        else
        {
            $res = array(
                'status' => - 1,
                "message" => $failure_message
            );
            $res['errors'][] = "Mail invoice sending failed";
            return response()->json($res);
        }
    }
    /*------------------------------------ SUCCESS BOOKING (END) ------------------------------------*/
    public function successInvoice($id)
    {
        $query = "Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$id";
        $result = DB::select($query);
        return $result;
    }
    /*----------------------------------- SUCCESS BOOOKING (START) ----------------------------------*/
    public function successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid)
    {
        $invoice_model = new Invoice();
        $invoice_details = Invoice::where('invoice_id', $invoice_id)->first();
        $invoice_details->booking_status = 1;
        if ($invoice_details->save())
        {
            $hotel_booking_model = Booking::where('invoice_id', $invoice_id)->get();
            $crs_reservation_model = CrsReservation::where('invoice_id', $invoice_id)->first();
            $crs_reservation_model->pay_status = 1;
            $crs_reservation_model->save();
            foreach ($hotel_booking_model as $hbm)
            {
                $hbm->booking_status = 1;
                $hbm->save();
                $this->updateInventory($hbm, 'consume');
            }
        }
        $transaction_model = new OnlineTransactionDetail();

        $transaction_model->payment_mode = $payment_mode;
        $transaction_model->invoice_id = $invoice_id;
        $transaction_model->transaction_id = $txnid;
        $transaction_model->payment_id = $mihpayid;
        $transaction_model->secure_hash = $hash;

        if ($transaction_model->save())
        {
            $failure_message = "Mail Not sent";
            if ($this->invoiceMail($invoice_id))
            {
                return true;
            }
            else
            {
                return false;
            }
        }
    }
    /*------------------------------------- INVOICE MAIL (START) ------------------------------------*/
    public function invoiceMail($id)
    {
        $hotel_info = new HotelInformation();
        $details = new Invoice();
        $invoice = $this->successInvoice($id);
        $invoice = $invoice[0];
        $booking_id = date("dmy", strtotime($invoice->booking_date)) . str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
        $u = $this->getUserDetails($invoice->user_id);
        $crs_reservation = CrsReservation::join('admin_table', 'crs_reservation.for_user_id', '=', 'admin_table.admin_id')->where('crs_reservation.invoice_id', $id)->first();
        $agent_email = $crs_reservation->username;
        $user_email_id = $u['email_id'];
        $hotel_contact = $hotel_info->getEmailId($invoice->hotel_id);
        $hotel_email_id = $hotel_contact['email_id'];
        $hotel_mobile = $hotel_contact['mobile'];
        $subject = "Booking Confirmation From " . $invoice->hotel_name;
        $body = $invoice->invoice;
        $body = str_replace("#####", $booking_id, $body);
        if ($crs_reservation->role_id == 4) //FOr Agent role id
        
        {
            $agent_commision = ($invoice->paid_amount * $crs_reservation->agent_commission) / 100;
            $dom = new \DOMDocument();
            @$dom->loadHTML($invoice->invoice); //To remove the error of soem in valid html
            $xpath = new \DOMXpath($dom);
            $nodes = $xpath->query('//td[@class="stripe"]'); //this catches all elements with itemprop attribute
            $html = array();
            foreach ($nodes as $key => $node)
            {
                array_push($html, $node->textContent);
            }
            $final_amount = $invoice->paid_amount - $agent_commision;
            $html['8'] = "Agent Discount";
            $html['9'] = "Rs. <span id='du_amt'>$agent_commision</span>";
            $html['10'] = "Total Paid Amount";
            $html['11'] = "Rs. <span id='du_amt'>$final_amount</span>";
            $supplied_details = array();
            $supplied_details['html'] = $html;
            $supplied_details['invoice_id'] = $booking_id;
            $supplied_details['agent'] = $crs_reservation->first_name . ' ' . $crs_reservation->last_name;
            $details->sendMail($agent_email, 'emails.agentInvoiceTemplate', "Your Payout for Booking", $supplied_details);
        }
        if ($this->sendMail($user_email_id, $body, $subject, $hotel_email_id, $agent_email, $invoice->hotel_name))
        {
            $to = $u['mobile'];
            $msg = "Your transaction has been successful(Booking ID- " . $booking_id . "). For more details kindly check your mail ID given at the time of booking.";
            /*if($this->sendSMS($to, $msg))
            {
            $to=$hotel_mobile;
            $msg="You have got new booking From Bookingjini(Booking ID- ".$booking_id."). For more details kindly check registered email ID.";
            $this->sendSMS($to, $msg);
            }*/
            return true;
        }
    }
    /*--------------------------------------- INVOICE MAIL (END) ------------------------------------*/
    /*
     *Email Invoice
     *@param $email for to email
     *@param $template is the email template
     *@param $subject for email subject
    */
    public function sendMail($email, $template, $subject, $hotel_email, $agent_email, $hotel_name)
    {
        $mail_array = ['trilochan.parida@5elements.co.in', 'gourab.nandy@5elements.co.in', 'reservations@bookingjini.com'];
        // $mail_array=['satya.godti@bookingjini.com',$agent_email];
        $data = array(
            'email' => $email,
            'subject' => $subject,
            'mail_array' => $mail_array
        );
        $data['template'] = $template;
        $data['hotel_name'] = $hotel_name;
        if ($hotel_email)
        {
            $data['hotel_email'] = $hotel_email;
        }
        else
        {
            $data['hotel_email'] = "";
        }
        Mail::send([], [], function ($message) use ($data)
        {
            if ($data['hotel_email'] != "")
            {
                $message->to($data['email'])->cc($data['hotel_email'])->bcc($data['mail_array'])->from(env("MAIL_FROM") , $data['hotel_name'])->subject($data['subject'])->setBody($data['template'], 'text/html');
            }
            else
            {
                $message->to($data['email'])->from(env("MAIL_FROM") , $data['hotel_name'])->cc('gourab.nandy@bookingjini.com')
                    ->subject($data['subject'])->setBody($data['template'], 'text/html');
            }
        });
        if (Mail::failures())
        {
            return false;
        }
        return true;
    }
    /**
     * To Send followup emails to agent Or Corporate bookings payment
     */
    public function sendFollowUpEmails(Request $request)
    {
        $today = date('Y-m-d');
        $crs_reservations = DB::table('crs_reservation')->join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')
            ->join('admin_table', 'crs_reservation.for_user_id', '=', 'admin_table.admin_id')
            ->where('pay_status', '=', 0)
            ->where('invoice_table.checkin_at', '>=', $today)->get();
        foreach ($crs_reservations as $crs_reservation)
        {
            $this->preinvoiceMail($crs_reservation->invoice_id);
        }
    }
    /**
     * To calcalulate and get the agent credit limit
     */
    public function getAgentCredit($agent_id, Request $request)
    {
        $agentCreditModel = new AgentCredit();
        $agentCredit = $agentCreditModel->where('agent_id', $agent_id)->first();
        $total_credit = (int)$agentCredit->credit_limit;
        $agentCredit = (int)$agentCredit->credit_limit;
        $credit_amount = CrsReservation::join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')->where('credit_status', 'credit')
            ->where('pay_status', '=', 1)
            ->where('for_user_id', '=', $agent_id)->sum('invoice_table.paid_amount');
        if ($credit_amount > 0)
        {
            $agentCredit = $agentCredit - $credit_amount;
        }
        $res = array(
            'status' => 1,
            "message" => 'Agent credit fetch successfull!',
            'agent_credit' => $agentCredit,
            'total_credit' => $total_credit
        );
        return response()->json($res);
    }
}

