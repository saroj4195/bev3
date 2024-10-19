<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Invoice;
use App\User;
use App\Booking;
use App\CmOtaDetails;
use App\CrsBooking;
use App\CrsReservation;
use App\CancellationPolicy;
use App\HotelInformation;
use DB;
use App\MailNotificationCrs;
use App\Http\Controllers\UpdateInventoryService;
use App\Http\Controllers\ManageCrsRatePlanController;
use App\Http\Controllers\InventoryService;
use App\MasterRoomType;
use App\MasterHotelRatePlan;
use App\IdsReservation;
use App\HotelBooking;
use App\RoomRateSettings;
use App\AdminUser;
use App\CompanyDetails;
use App\ImageTable;
use App\CmOtaBookingRead;
use App\CmOtaRatePlanSynchronizeRead;
use App\CmOtaRoomTypeSynchronizeRead;
// use App\CrsCanclePolicy;
use App\CmOta;
use App\City;
use App\State;
// use App\CmOtaBookingRead;
use App\NewCrs\CrsCanclePolicy;
use App\Voucher;

use App\Http\Controllers\BookingEngineController;
use App\Models\Invoice as ModelsInvoice;
use PhpParser\Node\Expr\Isset_;

/**
 * This controller is used for CRS(confirm, modification & cancellation) and CM voucher creation.
 * @auther Swatishree
 * created date 17/11/22.
 */

class VoucherDisplayController extends Controller
{

    public function crsBookingVoucher($crs_data, $voucher_name, $booking_status)
    {


        $agent_info = '';

        if($crs_data['partner_name'] !=''){
           $agent_info .=  '<strong>Agent Info: </strong><br>
                            <span>'.$crs_data['partner_name'].'</span><br>
                            <span>'.$crs_data['partner_address'].'</span><br>
                            <span>'.$crs_data['city'].','.$crs_data['state'].','.$crs_data['country'].'</span><br><br>';
        }


        $crs_booking_voucher = '
        <html>
        <head>
        <style>
            html{
                margin:0px;
                padding:0px;
            }

            .legend-header
            {
                font-family: Sans-serif;
                font-size:16px;
                color:#e55201;
                background-color:#fff3e0;  
            }
            fieldset 
            {
                border:1px solid gray;
                border-radius:5px;
                padding-left:20px;
                padding:10px;
                } 

                .legend-font
                {
                    font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
                    font-size:14px;
                    color:#434343;
                }

                .legend-content
                {
                    font-family:Arial, Helvetica, sans-serif;
                    font-size:15px;
                }

                .bookingDetails
                {
                    font-size:22px;
                    line-height:26px;
                    color:#e55201;
                    font-family:Courier New,
                    Courier, monospace
                }

                hr
                {
                    border:1px solid #e55201;
                }

                .penindconfrm
                {
                    font-size:28px;
                    padding-left:15px;
                    line-height:32px;
                    color:#e78048;
                    font-family:arial;
                }

                .footer-address
                {
                    margin:0; 
                    color:#333333;
                    font-size:11px;
                    font-family:arial; 
                    line-height:18px;
                    padding-left:15px;
                }

                .footer-logo
                {
                    margin:0 0 4px;
                    font-weight:bold;
                    color:#333333; 
                    font-size:14px;
                    padding-left:15px;
                }
                .button
                {
                border-radius: 5px;
                border:1px solid #EB4E16;
                color: #EB4E16;
                background-color: white;
                text-align: center;
                font-size: 16px;
                padding: 10px;
                width: 130px;
                transition: all 0.5s;
                cursor: pointer;
                box-shadow: -5px 5px 5px 1px rgba(207, 140, 52, 0.19)!important ;
                float: right;
                margin-right:10px;
                font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
                
                }

                .button span 
                {
                    cursor: pointer;
                    display: inline-block;
                    position: relative;
                    transition: 0.5s;
                    }
            
                    .button span:after 
                    {
                content: \00bb;
                position: absolute;
                opacity: 0;
                top: 0;
                right: -20px;
                transition: 0.5s;
                }

                .button:hover span 
                {
                    padding-right: 25px;
                    color: white;
                    }
            
                    .button:hover  
                {
                    background-color:#EB4E16; 
                    box-shadow:none;
                    }
                
                .button:hover span:after 
                {
                    opacity: 1;
                    right: 0;
                }
                    .alignment{
                    margin-top: 3px;
                }        
                        
        </style>
        </head>

        <body>
        <table id="voucher_display" cellspacing="0" cellpadding="0" border="0" bgcolor="#F3F3F3" width="100%">
        <tbody>
        <tr >
            <td width="15"></td>
            <td>
                <div style="display:block;max-width:800px;margin:0 auto;">
                        <table cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="#F3F3F3" align="center" style="min-width:20px; box-shadow: 0px 0px 10px 0px lightgray!important ;">
                            <tbody>
                                <tr>
                                    <td>
                                    <table cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="#fff3e0" style="border-bottom:2px solid #e78048">
                                        <tbody>
                                            
                                            <tr>
                                            <td style="text-align: center;padding-top: 1.5rem;">
                                                    <font size="5" face="Arial" color="#EB4E16" class="penindconfrm">
                                                    <b>' . $voucher_name . '</b> 
                                                    </font>
                                                </td></tr><tr>
                                                <td colspan="4" height="20"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="white">
                                    <tbody>
                                        <tr>
                                            <td colspan="4" height="20"></td>
                                        </tr><tr>
                                            <td>
                                                <section style="float:left;color:EB4E16;background-color:#fff3e0;padding-left: 20px;">
                                                    <font style="font-size:25px;"><b>' . $crs_data['hotel_display_name'] . '</b></font>
                                                </section> 
                                                <section style="float:right;padding-right: 15px;margin-top: 4px;">
                                                                <font style="font-size:18px;color:#EB4E16;"><b>BOOKING ID :</b></font> <font class="legend-content" style="font-weight: bold;">' . $crs_data['invoice_id'] . '</font>
                                                            </section>
                                                            </td>
                                                            </tr>
                                                        <tr>
                                                            <td colspan="4" height="20"></td>
                                                        </tr>
                                                        <tr>
                                                        <td>
                                                            <section style="float:left;padding-left:15px;">
                                                                <h4>Dear <span>' . $crs_data['name'] . '</span>,</h4><br>
                                                                <h4 >We hope this email finds you well. Thank you for choosing <span>' . $crs_data['hotel_display_name'] . '</span> as your property of choice for your visit and booking through our hotels website. Your ' . $voucher_name . ' details have been provided below:</h4>
                                                            </section>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    <td colspan="4" height="20"></td>
                                                </tr>
                                                <tr>
                                                        <td>
                                                            <fieldset style="margin:10px;">
                                                           <legend class="legend-header">BOOKING DETAILS</legend>
                                                               <section style="float:left; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>BOOKING DATE :</b></font> 
                                                                   <font class="legend-content">' . $crs_data['booking_date'] . '</font>
                                                               </section>  
                                                               <section style="float:right; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>ACCOMMODATION :</b></font> 
                                                                   <font class="legend-content">' . $crs_data['room_type'] . '</font>
                                                               </section> 
                                                                      
                                                               <section style="float:left; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>CHECK IN :</b></font> 
                                                                   <font class="legend-content">' . $crs_data['check_in'] . '</font>
                                                               </section>
                                                               <section style="float:right; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>CHECK OUT :</b></font> 
                                                                   <font class="legend-content">' . $crs_data['check_out'] . '</font>
                                                               </section>
                                                                
                                                               <section style="float:left; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>CHECK IN TIME :</b></font> <font class="legend-content">' . $crs_data['check_in_time'] . '</font>
                                                               </section>
                                                               <section style="float: right; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>CHECK OUT TIME :</b></font> <font class="legend-content">' . $crs_data['check_out_time'] . '</font>
                                                               </section>
                                                                
                                                               <section style="float:left; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>TOTAL ADULT :</b></font> <font class="legend-content">' . $crs_data['total_adult'] . '</font>
                                                               </section>        
                                                               <section style="float:right; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>TOTAL CHILD :</b></font> <font class="legend-content">' . $crs_data['total_child'] . '</font>
                                                               </section>
                                                                
                                                               <section style="float:left; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>NUMBER OF NIGHTS :</b></font> <font class="legend-content">' . $crs_data['no_of_nights'] . '</font>
                                                               </section>
                                                               <section style="float:right; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>ROOM PRICE :</b></font> 
                                                                   <font class="legend-content">&#8377;' . $crs_data['room_price'] . '</font>
                                                               </section>
                                                       </fieldset>
                                                   </td>
                                               </tr>
                                               <tr>
                                               <td colspan="4" height="20"></td>
                                           </tr>
                                           <tr>
                                           <td>
                                               <fieldset style="margin:10px;">
                                                   <legend class="legend-header">GUEST DETAILS</legend>
                                                       <section style="float:left;width:50%;">
                                                           <i style="font-size:15px;color:#434343;"></i> 
                                                           <font class="legend-font"><b>NAME :</b></font> 
                                                           <font class="legend-content">' . $crs_data['name'] . '</font>
                                                       </section>        
                                                       <section style="float:right;width:50%;">
                                                           <i style="font-size:15px;color:#434343;"></i> 
                                                           <font class="legend-font"><b>EMAIL-ID :</b></font> 
                                                           <font class="legend-content">' . $crs_data['user_email_id'] . '</font>
                                                       </section>
                                                       <section style="float:left;width:50%;">
                                                           <i style="font-size:15px;color:#434343;"></i> 
                                                           <font class="legend-font"><b>CONTACT NO. :</b></font> 
                                                           <font class="legend-content">' . $crs_data['user_mobile'] . '</font>
                                                       </section>
                                                       <section style="float:right;width:50%;">
                                                           <i style="font-size:15px;color:#434343;"></i> 
                                                           <font class="legend-font"><b>ADDRESS :</b></font> 
                                                           <font class="legend-content">' . $crs_data['user_address'] . '</font>
                                                       </section>
                                                       <section style="float:left;">
                                                           <i style="font-size:15px;color:#434343;"></i> 
                                                           <font class="legend-font"><b>COMMENT :</b></font> 
                                                           <font class="legend-content">' . $crs_data['user_remark'] . '</font>
                                                       </section>
                                               </fieldset>
                                           </td>
                                       </tr>   
                                       <tr>
                                       <td colspan="4" height="20"></td>
                                   </tr>
                                   <tr>
                                                   <td>
                                                       <fieldset style="margin:10px;">
                                                           <legend class="legend-header">PRICE DETAILS</legend>
                                                               <section style="float:left; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>TOTAL AMOUNT :</b></font> 
                                                                   <font class="legend-content">&#8377;' . $crs_data['total'] . '</font>
                                                               </section>        
                                                               <section style="float:right; width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>GST :</b></font> 
                                                                   <font class="legend-content">&#8377;' . $crs_data['tax_amount'] . '</font>
                                                               </section>
                                                               <section style="float:left;width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> 
                                                                   <font class="legend-font"><b>DISCOUNT PRICE :</b></font> 
                                                                   <font class="legend-content">&#8377;' . $crs_data['discount'] . '</font>
                                                               </section>
                                                               <section style="float:right;width: 50%;">
                                                                   <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>TOTAL AMOUNT TO BE PAID :</b></font> <font class="legend-content">&#8377;' . $crs_data['paid'] . '</font>
                                                               </section>
                                                       </fieldset>
                                                   </td>
                                               </tr>         
                                               <tr>
                                                   <td colspan="4" height="15"></td>
                                               </tr>
                                               <tr bgcolor="#fff3e0">
                                                   <td>
                                                       <p><hr class="hr"></p><br/>
                                                       <section style="float:left;padding-left:15px;">
                                                           <strong>Regards,</strong><br>
                                                           <strong class="alignment">' . $crs_data['hotel_display_name'] . '</strong><br>
                                                           <strong class="alignment">Mobile no.: <span style="font-weight:normal;">' . $crs_data['hotel_mobile'] . '</span></strong><br>
                                                           <strong class="alignment">Email: <span style="font-weight:normal;">' . $crs_data['hotel_email_id'] . '</strong><br>
                                                           <strong class="alignment">Address: <span style="font-weight:normal;">;' . $crs_data['hotel_address'] . '</span></strong><br>
                                            
                                                           '.$agent_info.'
                                                           <br><strong>Cancellation Policy: </strong><br>
                                                           <span style="font-weight:normal;">
                                                           ' . $crs_data['cancellation_policy'] . '
                                                           </span>
                                                       </section>
                                                   </td>
                                                   
                                               </tr>
                                               <tr>         
                                           </tr>  
                                           <table>
                                            <tbody>
                                
                                                </tr>   
                                            </tbody>
                                        </table>
                                       </tbody>
                                   </table>
                              
                                </td>
                            </tr>
                        </tbody>
                    </table>
            </div>
        </td>
        </tr>
        </tbody>
        </table>
        </body>
        </html>';

        $crs_voucher_arr = [
            'invoice_id' => $crs_data['invoice_id'],
            'hotel_name' => $crs_data['hotel_display_name'],
            'booking_source' => 'CRS',
            'voucher' => $crs_booking_voucher,
            'booking_status' => $booking_status
        ];

        $ota_detail = Voucher::where('invoice_id', $crs_data['invoice_id'])->first();

        if ($booking_status == 'Confirm') {
            if (empty($ota_detail->invoice_id)) {
                $insert_voucher = Voucher::insert($crs_voucher_arr);
            }else{
                $update_voucher = Voucher::where('invoice_id', $crs_data['invoice_id'])->update(['voucher' => $crs_booking_voucher, 'booking_status' => $booking_status]);
            }
        } else {
            if (empty($ota_detail->invoice_id)) {
                $insert_voucher = Voucher::insert($crs_voucher_arr);
            } else {
                $update_voucher = Voucher::where('invoice_id', $crs_data['invoice_id'])->update(['voucher' => $crs_booking_voucher, 'booking_status' => $booking_status]);
            }
        }

        return $crs_booking_voucher;
    }

    public function crsCancellatonVoucher($crs_data, $booking_status)
    {
        $agent_info = '';

        if($crs_data['partner_name'] !=''){
           $agent_info .=  '<strong>Agent Info: </strong><br>
                            <span>'.$crs_data['partner_name'].'</span><br>
                            <span>'.$crs_data['partner_address'].'</span><br>
                            <span>'.$crs_data['city'].','.$crs_data['state'].','.$crs_data['country'].'</span><br><br>';
        }

        $crs_cancle_data = '
        <html>
        <head>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <style>
        html{
            margin:0px;
            padding:0px;
        }

        .legend-header
        {
            font-family: Sans-serif;
            font-size:16px;
            color:#e55201;
            background-color:#fff3e0; 
        }
        fieldset 
        {
        border:1px solid gray;
        border-radius:5px;
        padding-left:20px;
        padding:10px;
        } 

        .legend-font
        {
            font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            font-size:14px;
            color:#434343;
        }

        .legend-content
        {
            font-family:Arial, Helvetica, sans-serif;
            font-size:15px;
        }

        .bookingDetails
        {
            font-size:22px;
            line-height:26px;
            color:#e55201;
            font-family:Courier New,
            Courier, monospace
        }

        hr
        {
            border:1px solid #e55201;
        }

        .penindconfrm
        {
            font-size:28px;
            padding-left:15px;
            line-height:32px;
            color:#e78048;
            font-family:arial;
        }

        .footer-address
        {
            margin:0; 
            color:#333333;
            font-size:11px;
            font-family:arial; 
            line-height:18px;
            padding-left:15px;
        }

        .footer-logo
        {
            margin:0 0 4px;
            font-weight:bold;
            color:#333333; 
            font-size:14px;
            padding-left:15px;
        }

        .button
        {
        border-radius: 5px;
        border:1px solid #EB4E16;
        color: #EB4E16;
        background-color: white;
        text-align: center;
        font-size: 20px;
        padding: 10px;
        width: 160px;
        transition: all 0.5s;
        cursor: pointer;
        box-shadow: -5px 5px 5px 1px rgba(52,191,163,0.19)!important ;
        float: right;
        margin-right:10px;
        font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
        }

        .button span 
        {
        cursor: pointer;
        display: inline-block;
        position: relative;
        transition: 0.5s;
        }

        .button span:after 
        {
        content: \00bb;
        position: absolute;
        opacity: 0;
        top: 0;
        right: -20px;
        transition: 0.5s;
        }

        .button:hover span 
        {
        padding-right: 25px;
        color: white;
        }

        .button:hover  
        {
        background-color:#EB4E16; 
        box-shadow:none;
        }

        .button:hover span:after 
        {
        opacity: 1;
        right: 0;
        }
        .alignment{
            margin-top: 3px;
        }
        
        </style>
        </head>

        <body>
        <table id="voucher_display" cellspacing="0" cellpadding="0" border="0" bgcolor="#F3F3F3" width="100%">
        <tbody>
            <tr >
                <td width="15"></td>
                <td>
                    <div style="display:block;max-width:800px;margin:0 auto;">
                            <table cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="#F3F3F3" align="center" style="min-width:20px; box-shadow: 0px 0px 10px 0px lightgray!important ;">
                                <tbody>
                                    <tr>
                                        <td>
                                        <table cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="#fff3e0" style="border-bottom:2px solid #e78048">
                                            <tbody>
                                               
                                                <tr>
                                                <td style="text-align: center;padding-top: 1.5rem;">
                                                        <font size="5" face="Arial" color="#EB4E16" class="penindconfrm">
                                                        <b>Booking Cancellation</b> 
                                                        </font>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" height="20"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <table cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="white">
                                                <tbody>
                                                    <tr>
                                                        <td colspan="4" height="20"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <section style="float:left;color:#EB4E16;background-color:#fff3e0;padding-left: 20px;">
                                                                <font style="font-size:25px;"><b>' . $crs_data['hotel_display_name'] . '</b></font>
                                                            </section> 
                                                            <section style="float:right;padding-right: 15px;margin-top: 4px;">
                                                                <font style="font-size:18px;color:#EB4E16;"><b>BOOKING ID :</b></font> <font class="legend-content" style="font-weight: bold;">' . $crs_data['invoice_id'] . '</font>
                                                            </section>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="4" height="20"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <fieldset style="margin:10px;">
                                                                <legend class="legend-header">Booking Details</legend>
                                                                    <section style="float:left; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>GUEST NAME :</b></font> <font class="legend-content">' . $crs_data['name'] . '</font>
                                                                    </section>  
                                                                    <section style="float:right; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>ACCOMMODATION :</b></font> <font class="legend-content">' . $crs_data['room_type'] . '</font>
                                                                    </section> 
                                                                <br><br>      
                                                                    <section style="float:left; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>CHECK IN :</b></font> <font class="legend-content">' . $crs_data['check_in'] . '</font>
                                                                    </section>
                                                                    <section style="float:right;width: 50%">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>CHECK OUT :</b></font> <font class="legend-content">' . $crs_data['check_out'] . '</font>
                                                                    </section>
                                                                <br><br>            
                                                                    <section style="float:left;width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>AMOUNT:</b></font> <font class="legend-content">&#8377;' . $crs_data['total'] . '</font>
                                                                    </section>
                                                                    <section style="float:right;width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>GUEST COMMENT :</b></font> <font class="legend-content">' . $crs_data['user_remark'] . '</font>
                                                                    </section>
                                                            </fieldset>
                                                        </td>
                                                    </tr>        
                                                    <tr>
                                                        <td colspan="4" height="15"></td>
                                                    </tr>
                                                    <tr bgcolor="#fff3e0">
                                                        <td>
                                                            <p><hr class="hr"></p><br/>
                                                            <section style="float:left;padding-left:15px;">
                                                                <strong>Regards,</strong><br>
                                                                <strong class="alignment">' . $crs_data['hotel_display_name'] . '</strong><br>
                                                                <strong class="alignment">Mobile no.: <span style="font-weight:normal;">' . $crs_data['hotel_mobile'] . '</span></strong><br>
                                                                <strong class="alignment">Email: <span style="font-weight:normal;">' . $crs_data['hotel_email_id'] . '</span></strong><br>
                                                                <strong class="alignment">Address: <span style="font-weight:normal;">' . $crs_data['hotel_address'] . '</span></strong><br>
                                                                '.$agent_info.'
                                                            </section>
                                                        </td>
                                                    </tr>
                                                         
                                                </tbody>
                                            </table>
                                            <table>
                                            <tbody>
                                                
                                            </tbody>
                                        </table>  
                                        </td>
                                    </tr>
                                    
                                   
                                </tbody>
                            </table>
                    </div>
                </td>
            </tr>
        </tbody>
        </table>

        </body>
        </html>';
        

        $crs_cancel_voucher_arr = [
            'invoice_id' => $crs_data['invoice_id'],
            'hotel_name' => $crs_data['hotel_display_name'],
            'booking_source' => 'CRS',
            'voucher' => $crs_cancle_data,
            'booking_status' => $booking_status
        ];

        $cancel_details = Voucher::where('invoice_id', $crs_data['invoice_id'])->first();
        if (empty($cancel_details->invoice_id)) {
            $insert_cancel_data = Voucher::insert($crs_cancel_voucher_arr);
        } else {
            $insert_voucher = Voucher::where('invoice_id', $crs_data['invoice_id'])->update(['voucher' => $crs_cancle_data, 'booking_status' => $booking_status]);
        }
        return $crs_cancle_data;
    }

    public function otaVoucher($template, $voucher_name, $ota_status)
    {
        $ota_html_data = '<!doctype html>
            <html>
            <head>
            <meta charset="utf-8">
            <title></title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

            <style>
                    .footer-address
                    {
                        margin:0; 
                        color:#333333;
                        font-size:11px;
                        font-family:arial; 
                        line-height:18px;
                        padding-left:15px;
                    }

                    .footer-logo
                    {
                        margin:0 0 4px;
                        font-weight:bold;
                        color:#333333; 
                        font-size:14px;
                        padding-left:15px;
                    }

                

                    .legend-font
                    {
                        font-family:monospace;
                        font-size:14px;
                        color:#333333;
                        font-weight:bold;
                    }

                    .legend-content
                    {
                        font-family:Arial, Helvetica, sans-serif;
                        font-size:14px;
                    }
                    
                    #icon-font
                    {
                    font-size:15px;
                    color:#333333;
                    }
                    hr.style18 
                    { 
                        border: 0;
                        height: 1px;
                        background-image: linear-gradient(to right, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0));
                    } 

            </style>

            </head>
            <body style="font-family:Gotham, "Helvetica Neue", Helvetica, Arial, sans-serif; background-color:#f0f2ea; margin:0; padding:0; color:#333333;">
            <table id="voucher_display" width="100%" bgcolor="#f0f2ea" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                    <tr>
                        <td style="">
                            <table cellpadding="0" cellspacing="0" width="100%" border="0" align="center">
                                <tbody>
                                    <tr>
                                        <td>
                                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="box-shadow: 5px 8px 10px 0px lightgray!important ;">
                                                <tbody>
                                                    <tr>
                                                        <td width="4" height="4" style="background:url(shadow-left-top.png) no-repeat 100% 0;"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                        <td colspan="3" rowspan="3" bgcolor="#FFFFFF" style="padding:0 0 30px;">
                                                        <span style="height:80px;display: flex;justify-content: space-between;align-items: center;">
                                                                <p style="float:left;padding-left:20px;font-size:24px; font-weight:bold; color:#f18951;">
                                                                    Booking Details
                                                                </p>
                                                                <p class="footer-logo" style="float:right;padding-right:20px;padding-top:20px;">
                                                                    <img style="width:150px;height:50px" src="https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/bookingjini.jpg"  alt="" >
                                                                </p>
                                                            </span>
                                                            <font><hr style=" border: 1px solid #f18951;"></font>
                                                            <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                                                <tbody>
                                                                
                                                                        <tr>
                                                                                <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                                <td colspan="3">
                                                                                    <p style="font-size:15px; line-height:22px; color:#333333; margin:0 0 5px;">
                                                                                        <span style="color:#333333; text-decoration:none;">
                                                                                            Dear ' . $template['hotel_name'] . ',<br/>
                                                                                            We have received a ' . $voucher_name . ' booking for a hotel on ' . $template['ota_name'] . '. You might have already received this directly from channel through an Email/SMS.</span></p>
                                                                                </td>
                                                                                <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                        </tr>
                                                                        <tr>
                                                                                <td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
                                                                                <td>
                                                                                        <hr  class="style18">
                                                                                        <span style="width:100%;height:50px;">
                                                                                                <p style="font-family:cursive;font-size:18px;color:#e55201;float:left;"><font class="legend-font" style="font-weight:bold;color:#e55201;border:1px solid #e55201;padding:2px;"> Booking Id : ' . $template['booking_id'] . '</font></p><br/>
                                                                                        </span><br>
                                                                                        <div >
                                                                                        <i style="font-family:cursive;font-size:18px;color:#e55201;float:left;    
                                                                                        display: inline-block;
                                                                                        text-align: left;
                                                                                        width: 100%;">Hotel Details </i><br/><br/>
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font" style="float:left;" >Hotel Name : </font> <font class="legend-content">' . $template['hotel_name'] . '</font><br/>
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font">Address : </font> <font class="legend-content">' . $template['hotel_address'] . '</font><br/>
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font"> City : </font> <font class="legend-content">' . $template['city_name'] . '</font><br/>
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font"> State : </font> <font class="legend-content">' . $template['state_name'] . '</font><br/>     
                                                                                            </div>
                                                                                </td>
                                                                                <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                            </tr>
                                                                        <tr>
                                                                        <td width="30"><p style="margin:0; font-size:1px;line-height:1px;">&nbsp;</p></td>
                                                                        <td>
                                                                            <hr  class="style18">
                                                                            <p style="font-family:cursive;font-size:18px;color:#e55201">Booking Details </p>
                                                                                <section style="float:left;">
                                                                                        <i id="icon-font"  class=""></i><font class="legend-font">Channel Name : </font> <font class="legend-content">' . $template['ota_name'] . '</font><br/>
                                                                                        <i id="icon-font" class=""></i> <font class="legend-font">Booking Date : </font> <font class="legend-content">' . $template['booking_date'] . '</font><br/>
                                                                                        <i id="icon-font" class=""></i><font class="legend-font">No of Nights : </font> <font class="legend-content">' . $template['number_of_nights'] . '</font><br/>    
                                                                                </section>
                                                                                <section style="float:right;">
                                                                                        <i id="icon-font" class=""></i> <font class="legend-font">Checkin Date : </font> <font class="legend-content">' . $template['check_in'] . '</font><br/>
                                                                                        <i id="icon-font" class=""></i> <font class="legend-font">Checkout Date : </font> <font class="legend-content">' . $template['check_out'] . '</font><br/>
                                                                                        <i id="icon-font" class=""></i> <font class="legend-font">Booking Status : </font> <font class="legend-content">' . $template['booking_status'] . '</font><br/>
                                                                                </section> 
                                                                        </td>
                                                                        <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                    </tr>
                                                                        <tr>
                                                                                <td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
                                                                                <td>
                                                                                    <hr  class="style18">
                                                                                    <p style="font-family:cursive;font-size:18px;color:#e55201">Guest Details </p>
                                                                                        <section style="float:left;">
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font"> Guest Name : </font> <font class="legend-content">' . $template['customer_details'] . '</font><br/>
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font"> Guest Email : </font> <font class="legend-content">' . $template['customer_email'] . '</font><br/>
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font"> Guest Number : </font> <font class="legend-content">' . $template['customer_number'] . '</font><br/>
                                                                                        </section>   
                                                                                </td>
                                                                                <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                        </tr>
                                                                        <tr>
                                                                                <td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
                                                                                <td>
                                                                                    <hr  class="style18">
                                                                                    <p style="font-family:cursive;font-size:18px;color:#e55201">Room Details </p>
                                                                                        <section style="float:left;">
                                                                                            <table cellpadding="0" cellspacing="0" border="1" width="100%">
                                                                                                <tbody >
                                                                                                    <tr class="row">
                                                                                                        <th class="col-md-3"><i id="icon-font" class=""></i> <font class="legend-font">Room Type</font></th> 
                                                                                                        <th class="col-md-3"><i id="icon-font" class=""></i> <font class="legend-font">Rate plan</font></th>                          
                                                                                                        <th class="col-md-2"><i id="icon-font" class=""></i> <font class="legend-font">Rooms</font></th>
                                                                                                        <th class="col-md-2"><i id="icon-font" class=""></i> <font class="legend-font">Adult(s)</font></th>
                                                                                                        <th class="col-md-2"><i id="icon-font" class=""></i> <font class="legend-font">Child</font></th>
                                                                                                    </tr> ';


        if ($template['ota_name'] == 'Agoda.com' || $template['ota_name'] == 'Expedia' || $template['ota_name'] == 'Yatra.Com 1101-03' || $template['ota_name'] == 'Travelguru') {

            $ota_html_data .= ' <tr class="row">
                                                 <td class="col-md-3"><font class="legend-content">' . $template['room_type'] . '</font></td>
                                                <td class="col-md-3"><font class="legend-content">' . $template['rate_plan'] . '</font></td>
                                                <td class="col-md-2"><font class="legend-content">' . $template['rooms'][0] . '</font></td>
                                                <td class="col-md-2"><font class="legend-content">' . $template['no_of_adult'][0] . '</font></td>
                                                <td class="col-md-2"><font class="legend-content">' . $template['no_of_child'][0] . '</font></td> 
                              </tr>';
        } else {
            $ota = "";
            $room_type = explode(",", $template['room_type']);
            $rate_plan = explode(",", $template['rate_plan']);
            for ($i = 0; $i < sizeof($room_type); $i++) {
                $rate_plan_info = sizeof($room_type) > sizeof($rate_plan) ? $rate_plan[0] : $rate_plan[$i];
                $ota_html_data .= '<tr class="row"><td class="col-md-3"><font class="legend-content">' . $room_type[$i] . '</font></td>
                    <td class="col-md-3"><font class="legend-content">' . $rate_plan_info . '</font></td>
                    <td class="col-md-2"><font class="legend-content">' . $template['rooms'][$i] . '</font></td>
                    <td class="col-md-2"><font class="legend-content">' . $template['no_of_adult'][$i] . '</font></td>
                    <td class="col-md-2"><font class="legend-content">' . $template['no_of_child'][$i] . '</font></td> 
                    </tr>';
            }
        }
        $ota_html_data .= ' </tbody>
                                                                                            </table>
                                                                                        </section> 
                                                                                </td>
                                                                                <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                        </tr>
                                                                        <tr>
                                                                                <td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
                                                                                <td>
                                                                                    <hr  class="style18">
                                                                                    
                                                                                    <p style="font-family:cursive;font-size:18px;color:#e55201">Pricing Details </p>
                                                                                        <section>';
        if ($template['ota_name'] == "Booking.com" || $template['ota_name'] == "Expedia") {
            $ota_html_data .= '<i id="icon-font" class=""></i> <font class="legend-font">Amount before tax : </font> <font class="legend-content">' . $template['total_amount'] . ' (' . strtoupper($template['currency']) . ')' . '</font><br/>';
        } else if ($template['ota_name'] == 'Akbar') {
            $ota_html_data .= '<i id="icon-font" class=""></i> <font class="legend-font">Amount before taxs : </font> <font class="legend-content">' . ($template['total_amount'] - $template['tax_amount']) . ' (' . strtoupper($template['currency']) . ')' . '</font><br/>';
        } else {
            $ota_html_data .= '<i id="icon-font" class=""></i> <font class="legend-font">Amount before tax : </font> <font class="legend-content">' . ($template['total_amount'] - $template['tax_amount']) . ' (' . strtoupper($template['currency']) . ')' . '</font><br/>';
        }
        $ota_html_data .= '</section>
                                                                                        <section>
                                                                                            <i id="icon-font" class=""></i><font class="legend-font"> Tax Amount : </font> <font class="legend-content">' . $template['tax_amount'] . ' (' . strtoupper($template['currency']) . ')' . '</font><br/>
                                                                                        </section>
                                                                                        
                                                                                        <section>';
        if ($template['ota_name'] == 'Booking.com' || $template['ota_name'] == 'Expedia') {
            $ota_html_data .= '<i id="icon-font" class=""></i><font class="legend-font"> Total Amount : </font> <font class="legend-content">' . ($template['total_amount'] + $template['tax_amount']) . ' (' . strtoupper($template['currency']) . ')' . '</font><br/>';
        } else {
            $ota_html_data .= ' <i id="icon-font" class=""></i><font class="legend-font"> Total Amount : </font> <font class="legend-content">' . ($template['total_amount']) . ' (' . strtoupper($template['currency']) . ')' . '</font><br/>';
        }
        $ota_html_data .= ' </section>';
        if ($template['ota_name'] == 'Booking.com') {
            $ota_html_data .= '<section>
                                                                                            <i id="icon-font" class=""></i><font class="legend-font"> OTA Commission: </font> <font class="legend-content">' . round(($template['total_amount'] * 15) / 100) . ' (' . strtoupper($template['currency']) . ')' . '</font><br/>
                                                                                        </section>';
        }
        $ota_html_data .= ' <section>
                                                                                            <i id="icon-font" class=""></i><font class="legend-font"> Payment Status : </font> <font class="legend-content">' . $template['payment_status'] . '</font><br/>
                                                                                        </section>
                                                                                    
                                                                                        
                                                                                </td>
                                                                                <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                        <tr>
                                                                                <td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
                                                                                <td>
                                                                                    <hr  class="style18">
                                                                                    <p style="font-family:cursive;font-size:18px;color:#e55201">Inclusion </p>
                                                                                        <section style="float:left;">
                                                                                                <i id="icon-font" class=""></i> <font class="legend-font">Inclusion : </font> <font class="legend-content">' . $template['inclusion'] . '</font><br/>
                                                                                        </section>
                                                                                </td>
                                                                                <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                        </tr>
                                                                        <tr>
                                                                                <td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>';
        if ($template['inclusion'] || $template['special_info']) {
            $ota_html_data .= '<td>
                                                                                    <hr  class="style18">
                                                                                    <p style="font-family:cursive;font-size:18px;color:#e55201">More Information </p>';
            if ($template['special_info'] != "") {
                $ota_html_data .= '<section style="float:left;">
                <i id="icon-font" class=""></i> <font class="legend-font">Special Information : </font> <font class="legend-content">' . $template['special_info'] . '</font><br/>
            </section> <br>';
            }
            if ($template['cancel_policy'] != "") {
                $ota_html_data .= '<section style="float:left;">
                                                <i id="icon-font" class=""></i> <font class="legend-font">Cancellation Policy : </font> <font class="legend-content">' . $template['cancel_policy'] . '</font><br/>
                                                                                        </section>';
            }
            $ota_html_data .= '</td>';
        }
        $ota_html_data .= '<td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                        </tr>
                                                                </tbody>
                                                            </table>
                                                            <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                                                <tbody>
                                                                        <tr>
                                                                                <td>
                                                                                    <p><hr style=" border: 1px solid #f18951;"></p>
                                                                                    <p class="footer-logo"><img style="width:150px;height:50px" src="https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/logo_old_2.jpg"  alt="" ></p>
                                                                                    <p class="footer-address">
                                                                                            301 Star Tower adjacent to Star Mall on NH8, Exit 8, Sector 30, <br>
                                                                                            Gurugram, Haryana , E-mail: support@bookingjini.com<br>
                                                                                        Website: <a href="https://bookingjini.com/" style="color:#0795ff;font-family:arial; text-decoration:none; font-weight:bold;">Bookingjini Labs Pvt. Ltd.</a>
                                                                                    </p>
                                                                                </td>
                                                                            </tr> 
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
            </body>
            </html>';

            $ota_details_arr = [
                'invoice_id' => $template['booking_id'],
                'hotel_name' => $template['hotel_name'],
                'booking_source' => $template['ota_name'],
                'voucher' => $ota_html_data,
                'booking_status' => $ota_status
    
            ];
            $ota_detail = Voucher::where('invoice_id', $template['booking_id'])->first();
    
            if ($ota_status == 'Confirm') {
                if (empty($ota_detail->invoice_id)) {
                    $insert_confirm_vou_details = Voucher::insert($ota_details_arr);
                }
            } elseif ($ota_status == 'Modify') {
                if (empty($ota_detail->invoice_id)) {
                    $insert_confirm_vou_details = Voucher::insert($ota_details_arr);
                } else {
                    $update_modify_vou_details = Voucher::where('invoice_id', $template['booking_id'])->update(['voucher' => $ota_html_data, 'booking_status' => $ota_status]);
                }
            } elseif ($ota_status == 'Cancel') {
                if (empty($ota_detail->invoice_id)) {
                    $insert_confirm_vou_details = Voucher::insert($ota_details_arr);
                } else {
                    $update_cancel_vou_details = Voucher::where('invoice_id', $template['booking_id'])->update(['voucher' => $ota_html_data, 'booking_status' => $ota_status]);
                }
            } else {
                return response()->json(array('status' => 0, 'msg' => 'failed'));
            }
            return $ota_html_data;
    }

    public function voucherDisplay($booking_id, $source)
    {
        $source = strtolower($source);
        $booking_engine = [
            'website',
            'quickpayment',
            'gems',
            'google'
        ];


        if ($source == 'crs') {
            $invoice_id = (int)substr($booking_id, 6);

            $details = Invoice::join('kernel.user_table as a', 'invoice_table.user_id', '=', 'a.user_id')
                ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                ->join('crs_booking', 'crs_booking.invoice_id', '=', 'invoice_table.invoice_id')
                ->leftjoin('partner_table', 'partner_table.id', '=', 'crs_booking.partner_id')
                ->select(
                    'invoice_table.hotel_name',
                    'invoice_table.room_type',
                    'invoice_table.total_amount',
                    'invoice_table.user_id',
                    'invoice_table.paid_amount',
                    'invoice_table.check_in_out',
                    'invoice_table.booking_date',
                    'invoice_table.booking_status',
                    'invoice_table.modify_status',
                    'a.first_name',
                    'invoice_table.invoice',
                    'a.last_name',
                    'a.email_id',
                    'a.address',
                    'a.mobile',
                    'hotel_booking.check_in',
                    'hotel_booking.check_out',
                    'hotel_booking.rooms',
                    'invoice_table.hotel_id',
                    'invoice_table.extra_details',
                    'crs_booking.guest_remark',
                    'invoice_table.discount_amount',
                    'invoice_table.tax_amount',
                    'crs_booking.adult',
                    'crs_booking.child',
                    'crs_booking.guest_name',
                    'crs_booking.valid_hour',
                    'crs_booking.payment_type',
                    'crs_booking.validity_type',
                    'partner_table.partner_name',
                    'partner_table.address as partner_address',
                    'partner_table.city',
                    'partner_table.state',
                    'partner_table.country'
                )
                ->where('invoice_table.invoice_id', $invoice_id)
                ->first();
            if (!empty($details)) {
                $hoteldetails = HotelInformation::select('hotels_table.hotel_address', 'hotels_table.mobile', 'hotels_table.exterior_image', 'hotels_table.email_id', 'hotels_table.company_id', 'hotels_table.cancel_policy', 'hotels_table.check_in as check_in_time', 'hotels_table.check_out as check_out_time')->where('hotel_id', $details->hotel_id)->first();

                $email_id = explode(',', $hoteldetails->email_id);

                if (is_array($email_id)) {
                    $hoteldetails->email_id = $email_id[0];
                }
                $mobile = explode(',', $hoteldetails->mobile);
                if (is_array($mobile)) {
                    $hoteldetails->mobile = $mobile[0];
                }

                $name = explode(',', $details->guest_name);
                $email = $details->email_id;
                $total_adult = array_sum(explode(',', $details->adult));
                $total_child = array_sum(explode(',', $details->child));
                $no_of_nights = ceil(abs(strtotime($details->check_in) - strtotime($details->check_out)) / 86400);
                $booking_id     = date("dmy", strtotime($details->booking_date)) . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);
                $booking_info   = base64_encode($booking_id);
                $room_price     = abs($details->total_amount - $details->tax_amount);
                $room_type = trim($details->room_type, '["');
                $room_type = trim($room_type, '"]');
                // get rate plan
                preg_match_all("/\\((.*?)\\)/", $room_type, $matches);
                $rate = $matches[1][0];
                $rate_plan = DB::table('kernel.rate_plan_table')->select('plan_name')->where('plan_type', $rate)->where('hotel_id', $details->hotel_id)->first();
                // $total = $details->paid_amount + $details->discount_amount;
                $total = $details->paid_amount;

                $hotel_logo = HotelInformation::join('company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
                    ->join('image_table', 'company_table.logo', '=', 'image_table.image_id')
                    ->select('image_table.image_name')
                    ->where('hotels_table.hotel_id', $details->hotel_id)
                    ->first();

                $crs_policy = CrsCanclePolicy::where('hotel_id', $details->hotel_id)->first();
                if (empty($crs_policy)) {
                    $crs_policy = HotelInformation::where('hotel_id', $details->hotel_id)->first();
                }

                $paid_amount = $details->paid_amount;

                // if($details->hotel_id==2600){
                    $received_amount = DB::table('crs_payment_receive')->where('invoice_id',$invoice_id)->sum('receive_amount');
                    $paid_amount = $total - $received_amount;
                // }
                
                
                $crs_data = array(
                    'invoice_id' => $booking_id, 'name' => $name[0], 'check_in' => $details->check_in, 'check_out' => $details->check_out, 'room_type' => $room_type, 'booking_date' => $details->booking_date, 'user_address' => $details->address, 'user_mobile' => $details->mobile, 'user_remark' => $details->guest_remark, 'hotel_display_name' => $details->hotel_name, 'hotel_address' => $hoteldetails->hotel_address, 'hotel_mobile' => $hoteldetails->mobile, 'image_name' => $details->hotel_image, 'total' => $total, 'paid' => $paid_amount, 'room_price' => $room_price, 'discount' => $details->discount_amount, 'tax_amount' => $details->tax_amount, 'hotel_email_id' => $hoteldetails->email_id, 'user_email_id' => $details->email_id, 'cancellation_policy' => $crs_policy['cancel_policy'], 'total_adult' => $total_adult,
                    'total_child' => $total_child, 'check_in_time' => $hoteldetails->check_in_time, 'check_out_time' => $hoteldetails->check_out_time, 'booking_info' => $booking_info, 'no_of_nights' => $no_of_nights, 'rate_plan' => $rate_plan->plan_name, 'payment_type' => $details->payment_type, 'valid_hour' => $details->valid_hour, 'validity_type' => $details->validity_type, 'hotel_logo' => $hotel_logo,'partner_name' => $details->partner_name,'partner_address' => $details->partner_address,'city' => $details->city,'state' => $details->state,'country' => $details->country
                );
                if ($details->booking_status == 1 && $details->modify_status == 0) {       //Confirma booking
                    $voucher_name = "Booking Confirmation";

                    $voucher = $this->crsBookingVoucher($crs_data, $voucher_name, 'Confirm');
                    return $voucher;
                } elseif ($details->booking_status == 1 && $details->modify_status == 1) { //Modify booking
                    $voucher_name = "Modify Booking";
                    $voucher = $this->crsBookingVoucher($crs_data, $voucher_name, 'Modify');
                    return $voucher;
                } elseif ($details->booking_status == 3) {                                 //cancle booking
                    $voucher = $this->crsCancellatonVoucher($crs_data, 'Cancel');
                    return $voucher;
                } else {
                    return response()->json(array('status' => 0, 'msg' => 'failed'));
                }
            } else {
                return response()->json(array('status' => 0, 'msg' => 'No data found'));
            }
        } elseif (in_array($source, $booking_engine)) {  //Bookingengine voucher
            $invoice_id = (int)substr($booking_id, 6);

            $be_voucher_details = Invoice::where('invoice_id', $invoice_id)->where('booking_source', $source)->first();
            if (!empty($be_voucher_details)) {
                $body = $be_voucher_details->invoice;
                $booking_status = $be_voucher_details->booking_status;
                $modify_status = $be_voucher_details->modify_status;

                if ($booking_status == 1 && $modify_status == 0) {
                    $body            = str_replace(">>>>>", $invoice_id, $body);
                    $body            = str_replace("#####", $invoice_id, $body);
                    return $body;
                } elseif ($booking_status == 1 && $modify_status == 1) {
                    $body            = str_replace(">>>>>", $invoice_id, $body);
                    $body            = str_replace("#####", $invoice_id, $body);
                    $body            = str_replace("BOOKING CONFIRMATION", "BOOKING MODIFICATION", $body);
                    return $body;
                } elseif ($booking_status == 3) {
                    $body            = str_replace(">>>>>", $invoice_id, $body);
                    $body            = str_replace("#####", $invoice_id, $body);
                    $body            = str_replace("BOOKING CONFIRMATION", "BOOKING CANCELLATION", $body);
                    $body            = str_replace("BOOKING MODIFICATION", "BOOKING CANCELLATION", $body);

                    return $body;
                } else {
                    return response()->json(array('status' => 0, 'msg' => 'failed'));
                }
            } else {
                return response()->json(array('status' => 0, 'msg' => 'No data found'));
            }
        } elseif ($source) {
            
            $ota__details = Cmota::Where('ota_name', 'like', '%' . $source . '%')->first();
            $ota_name = $ota__details['ota_name'];
            if($source == 'makemytrip'){
                $ota_name = 'MakeMyTrip';
            }
            
            if (isset($source)) {
                $cmOtaRoomTypeSynchronizeModel  = new CmOtaRoomTypeSynchronizeRead();
                $cmOtaRatePlanSynchronizeModel     = new CmOtaRatePlanSynchronizeRead();
                $cm_ota_booking_model = new CmOtaBookingRead();
                $ota_booking_data =    $cm_ota_booking_model->where('unique_id', $booking_id)->where('channel_name', $ota_name)->first();
                if (!empty($ota_booking_data)) {
                    $ota_id =    $ota_booking_data->ota_id;
                    $room_types =    $ota_booking_data->room_type;
                    $rate_plans =    $ota_booking_data->rate_code;
                    $hotel_id =    $ota_booking_data->hotel_id;
                    $from_date =    strtotime($ota_booking_data->checkout_at);
                    $to_date =    strtotime($ota_booking_data->checkin_at);
                    $booking_date =    date('d M Y H:i:s',strtotime($ota_booking_data->booking_date.'-5 hour'.'-30 minutes'));
                    $channel_name =    $ota_booking_data->channel_name;
                    $tax_amount =    $ota_booking_data->tax_amount;
                    $no_of_adult =    explode(',', $ota_booking_data->no_of_adult);
                    $no_of_child =    explode(',', $ota_booking_data->no_of_child);
                    $inclusion =    explode(',', $ota_booking_data->inclusion);
                    $inclusion    =    $inclusion[0];
                    $special_info    =    $ota_booking_data->special_information;
                    $cancel_policy    =    $ota_booking_data->cancel_policy;
                    $customer_details =    explode(',', $ota_booking_data->customer_details);
                    $customer_name =    $customer_details[0];
                    $customer_email =    $customer_details[1];
                    $customer_number =    isset($customer_details[2]) ? $customer_details[2] : '';
                    $currency    =    $ota_booking_data->currency;
                    $ota_booking_status = $ota_booking_data->booking_status;
                    $confirm_status = $ota_booking_data->confirm_status;
                    $cancel_status = $ota_booking_data->cancel_status;

                    if ($customer_email == null) {
                        $customer_email = 'NA';
                    }
                    $difference = $from_date - $to_date;
                    $number_of_nights = round($difference / (60 * 60 * 24));
                    $rooms = explode(',', $ota_booking_data->rooms_qty);
                    $room_types                     = explode(",", $room_types);
                    $rate_plans                     = explode(",", $rate_plans);
                    $hotel_room_type = "";
                    $hotel_rate_plan = "";
                    $guest = array();
                    $guest1 = array();
                    $rate_plan_details = array();
                    foreach ($rate_plans as $rates) {
                        if (sizeof($rate_plan_details) > 0) {
                            if (in_array($rates, $rate_plan_details)) {
                            } else {
                                $rate_plan_details[] = $rates;
                            }
                        } else {
                            $rate_plan_details[] = $rates;
                        }
                    }
                   
                   
                    $k = 0;
                    foreach ($rooms as $key => $room) {
                        if ($room > 1) {
                            $sum = 0;
                            $sum1 = 0;
                            for ($i = 0; $i < $room; $i++) {
                                if (!isset($no_of_child[$i])) {
                                    $no_of_child[$i] = 0;
                                }
                                if (!isset($no_of_adult[$i])) {
                                    $no_of_adult[$i] = 0;
                                }
                                $sum += (int)$no_of_adult[$k];
                                $child_info = isset($no_of_child[$k]) ? (int)$no_of_child[$k] : 0;
                                $sum1 += (int)$child_info;
                                $k++;
                            }
                            $guest[] = (string)$sum;
                            $guest1[] = (string)$sum1;
                        } else {
                            $guest[] = $no_of_adult[$k];
                            if (!isset($no_of_child[$k])) {
                                $no_of_child[$k] = 0;
                            }
                            $guest1[] = (int)$no_of_child[$k];
                            $k++;
                        }
                    }
                    foreach ($room_types as $key => $room_type) {
                        $hotel_room_type_id = '';
                        if (sizeof($room_types) == 1) {
                            $hotel_room_type = $cmOtaRoomTypeSynchronizeModel->getRoomType($room_type, $ota_id);
                            $hotel_room_type_id = $cmOtaRoomTypeSynchronizeModel->getRoomTypeID($room_type, $ota_id);
                        } else {
                            if ($key == 0) {
                                $hotel_room_type .= $cmOtaRoomTypeSynchronizeModel->getRoomType($room_type, $ota_id);
                                $hotel_room_type_id .= $cmOtaRoomTypeSynchronizeModel->getRoomTypeID($room_type, $ota_id);
                            } else {
                                $hotel_room_type .= ',' . $cmOtaRoomTypeSynchronizeModel->getRoomType($room_type, $ota_id);
                                $hotel_room_type_id .= '`' . $cmOtaRoomTypeSynchronizeModel->getRoomTypeID($room_type, $ota_id);
                            }
                        }
                    }

                    foreach ($rate_plan_details as $key => $rate_plan) {
                        if (sizeof($rate_plans) == 1) {
                            $hotel_rate_plan = $cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id, $rate_plan);
                        } else {
                            if ($key == 0) {
                                $hotel_rate_plan .= $cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id, $rate_plan);
                            } else {
                                $hotel_rate_plan .= ',' . $cmOtaRatePlanSynchronizeModel->getRoomRatePlan($ota_id, $rate_plan);
                            }
                        }
                    }
                    $hotel = HotelInformation::where('hotel_id', $hotel_id)->select('hotel_name', 'email_id', 'hotel_address', 'city_id', 'state_id', 'is_dp')->first();
                    $city_name = City::where('city_id', $hotel->city_id)->first();
                    $state_name = State::where('state_id', $hotel->state_id)->first();
                    $hotel_name = $hotel->hotel_name;
                    $email_id = explode(',', $hotel->email_id);


                    $city_name_info = isset($city_name->city_name) ? $city_name->city_nam : 'NA';
                    $state_name_info = isset($state_name->state_name) ? $state_name->state_name : 'NA';
                    $template = array(
                        "ota_name" => $channel_name, "hotel_name" => $hotel_name, "hotel_address" => $hotel->hotel_address, "city_name" => $city_name_info, "booking_id" => $ota_booking_data->unique_id, "check_in" => $ota_booking_data->checkin_at, "check_out" => $ota_booking_data->checkout_at, 'booking_date' => $booking_date, "room_type" => $hotel_room_type, "rate_plan" => $hotel_rate_plan, "rooms" => $rooms, "total_amount" => $ota_booking_data->amount, "customer_details" => $customer_name, "booking_status" => $ota_booking_status, "payment_status" => $ota_booking_data->payment_status, "number_of_nights" => $number_of_nights, "state_name" => $state_name_info, "customer_number" => $customer_number, "tax_amount" => $tax_amount, "inclusion" => $inclusion,
                        "special_info" => $special_info, "cancel_policy" => $cancel_policy,
                        "no_of_adult" => $guest, "no_of_child" => $guest1, "customer_email" => $customer_email,
                        "currency" => $currency, "col_amount" => $ota_booking_data->collection_amount
                    );

                
                    
                    if ($ota_booking_status == 'Commit' && $confirm_status == 1 && $cancel_status == 0) {
                        $voucher_name = "Confirmed";
                        $voucher = $this->otaVoucher($template, $voucher_name, 'Confirm');
                        return $voucher;
                    } elseif ($ota_booking_status == "Modify" && $confirm_status == 1 && $cancel_status == 0) {
                        $voucher_name = "modify";
                        $voucher = $this->otaVoucher($template, $voucher_name, 'Modify');
                        return $voucher;
                    } elseif ($ota_booking_status == 'Cancel' && $confirm_status == 1 && $cancel_status == 1) {
                        $voucher_name = "cancel";
                        $voucher = $this->otaVoucher($template, $voucher_name, 'Cancel');
                        return $voucher;
                    } else {
                        return response()->json(array('status' => 0, 'msg' => 'failed'));
                    }
                } else {
                    return response()->json(array('status' => 0, 'msg' => 'No data found'));
                }
            } else {
                return response()->json(array('status' => 0, 'msg' => 'failed'));
            }
        }
    }

    public function voucherSendMail(Request $request)
    {
        $booking_id = $request->booking_id;
        $email = trim($request->email);
        $source = $request->source;
      
        if($source == 'GEMS' || $source == 'google' ||$source == 'website' ||$source == 'QUICKPAYMENT' || $source == 'WEBSITE'){
            $invoice_id = substr($booking_id, '6');
            // $invoice        = $this->successInvoice($invoice_id);
            $query      = "Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice,a.ids_re_id, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount,  a.booking_status, a.modify_status, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond,a.agent_code from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$invoice_id";
            $invoice    = DB::select($query);

            $invoice = $invoice[0];
            $body           = $invoice->invoice;
            if ($invoice->booking_status == 1 && $invoice->modify_status == 1) {
                $subject        = $invoice->hotel_name . " Booking Modified";
                $body           = str_replace("#####", $booking_id, $body);
                $body           = str_replace("BOOKING CONFIRMATION", "BOOKING MODIFIED", $body);
            } elseif ($invoice->booking_status == 3) {
                $subject        = $invoice->hotel_name . " Booking Cancel";
                $body           = str_replace("#####", $booking_id, $body);
                $body           = str_replace("BOOKING CONFIRMATION", "BOOKING CANCEL", $body);
            } else {
                $subject        = $invoice->hotel_name . " Booking Confirmation";
                $body           = str_replace("#####", $booking_id, $body);
            }
    
            $body           = str_replace(">>>>>", $booking_id, $body);
            $invoice_id     = $invoice->invoice_id;
    
            $serial_no      = 'NA';
            $transection_id = 'NA';
            $body           = str_replace("*****", $serial_no, $body);
            $body           = str_replace("@@@@@", $transection_id, $body);
            $body_info      = array('invoice' => $body);
            $data['hotel_name'] = $invoice->hotel_name;
            // dd($invoice->hotel_name);
            $hotel_name = $invoice->hotel_name;
        }else{
            // if($source == 'crs' || $source == 'CRS'){
            //     $invoice_id = substr($booking_id, '6');
            // }else{
            //     $invoice_id = $booking_id;
            // }

            $voucher_details = Voucher::where('invoice_id',$booking_id)->first();
            if(empty($voucher_details)){
                $res = array('status' => 0, "message" => 'Mail send failed');
                return response()->json($res);
            }
            
            $body = $voucher_details['voucher'];
            $booking_status = $voucher_details['booking_status'];
            $hotel_name = $voucher_details['hotel_name'];
           
            $subject        = $hotel_name . " Booking " . $booking_status;
         
        }

        $data = array('email' => $email, 'subject' => $subject, 'hotel_name'=>$hotel_name);
        $data['template'] = $body;


        try {
            Mail::send([], [], function ($message) use ($data) {

                $message->to($data['email'])
                ->from(env("MAIL_FROM"), $data['hotel_name'])
                ->subject($data['subject'])
                ->setBody($data['template'], 'text/html');
            });
            if (Mail::failures()) {
                $res = array('status' => 0, "message" => 'Mail send failed');
                return response()->json($res);
            }
            $res = array('status' => 1, "message" => 'Mail sent');
            return response()->json($res);
        } catch (Exception $e) {
            return true;
        }
    }
}
