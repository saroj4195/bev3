<html>
    <head>
       <title></title>
       <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
       <style>
        *{
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
            font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
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
        font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
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
        content: '\00bb';
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
       <table cellspacing="0" cellpadding="0" border="0" bgcolor="#F3F3F3" width="100%">
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
                                                     <td colspan="4" height="20"></td>
                                                 </tr>
                                                 <tr>
                                                     <td style="text-align: center;">
                                                          <font size="5" face="Arial" color="#EB4E16" class="penindconfrm">
                                                          <b>Booking Confirmation</b> 
                                                          </font>
                                                     </td>
                                                     
                                                 </tr>
                                                <tr>
                                                    <td>
                                                        @if($payment_type == '1')
                                                            <a href="https://be.bookingjini.com/crs/crs_pay/'<?=$booking_info?>'"><button class="button"><span><b> <i style="font-size:18px;color:#EB4E16"></i>Pay Now<span>&#8377;<?=$paid?></span></b> </span></button></a>
                                                        @endif
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
                                                            <section style="float:left;color:EB4E16;background-color:#fff3e0;padding-left: 20px;">
                                                                <font style="font-size:25px;"><b><?=$hotel_display_name?></b></font>
                                                            </section> 
                                                            <section style="float:right;padding-right: 15px;margin-top: 4px;">
                                                                <font style="font-size:18px;color:#EB4E16;"><b>BOOKING ID :</b></font> <font class="legend-content" style="font-weight: bold;"><?=$invoice_id?></font>
                                                            </section>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="4" height="20"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <section style="float:left;padding-left:15px;">
                                                                <h4>Dear <span><?=$name?></span>,</h4><br>
                                                                <h4 >We hope this email finds you well. Thank you for choosing <span><?=$hotel_display_name?></span> as your property of choice for your visit and booking through our hotel's website. Your booking confirmation details have been provided below:</h4>
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
                                                                        <font class="legend-content"><?=$booking_date?></font>
                                                                    </section>  
                                                                    <section style="float:right; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>ACCOMMODATION :</b></font> 
                                                                        <font class="legend-content"><?=$room_type?></font>
                                                                    </section> 
                                                                           
                                                                    <section style="float:left; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>CHECK IN :</b></font> 
                                                                        <font class="legend-content"><?=$check_in?></font>
                                                                    </section>
                                                                    <section style="float:right; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>CHECK OUT :</b></font> 
                                                                        <font class="legend-content"><?=$check_out?></font>
                                                                    </section>
                                                                     
                                                                    <section style="float:left; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>CHECK IN TIME :</b></font> <font class="legend-content"><?=$check_in_time?></font>
                                                                    </section>
                                                                    <section style="float: right; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>CHECK OUT TIME :</b></font> <font class="legend-content"><?=$check_out_time?></font>
                                                                    </section>
                                                                     
                                                                    <section style="float:left; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>TOTAL ADULT :</b></font> <font class="legend-content"><?=$total_adult?></font>
                                                                    </section>        
                                                                    <section style="float:right; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>TOTAL CHILD :</b></font> <font class="legend-content"><?=$total_child?></font>
                                                                    </section>
                                                                     
                                                                    <section style="float:left; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>NUMBER OF NIGHTS :</b></font> <font class="legend-content"><?=$no_of_nights?></font>
                                                                    </section>
                                                                    <section style="float:right; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>BOOKING AMOUNT :</b></font> 
                                                                        <font class="legend-content">&#8377;<?=$total?></font>
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
                                                                        <font class="legend-content"><?=$name ?></font>
                                                                    </section>        
                                                                    <section style="float:right;width:50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>EMAIL-ID :</b></font> 
                                                                        <font class="legend-content"><?=$user_email_id?></font>
                                                                    </section>
                                                                    <section style="float:left;width:50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>CONTACT NO. :</b></font> 
                                                                        <font class="legend-content"><?=$user_mobile?></font>
                                                                    </section>
                                                                    <section style="float:right;width:50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>ADDRESS :</b></font> 
                                                                        <font class="legend-content"><?=$user_address?></font>
                                                                    </section>
                                                                    <section style="float:left;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>COMMENT :</b></font> 
                                                                        <font class="legend-content"><?=$user_remark?></font>
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
                                                                        <font class="legend-content">&#8377;<?=$total?></font>
                                                                    </section>        
                                                                    <section style="float:right; width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>GST :</b></font> 
                                                                        <font class="legend-content">&#8377;<?=$tax_amount?></font>
                                                                    </section>
                                                                    <section style="float:left;width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> 
                                                                        <font class="legend-font"><b>DISCOUNT PRICE :</b></font> 
                                                                        <font class="legend-content">&#8377;<?=$discount?></font>
                                                                    </section>
                                                                    @if($payment_type == '1')
                                                                    <section style="float:right;width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>PAY NOW :</b></font> <font class="legend-content">&#8377;<?=$paid?></font>
                                                                    </section>
                                                                    @endif
                                                                    @if($payment_type == '1')
                                                                    <?php 
                                                                    $pay_at_hotel = $total_amount_to_paid;
                                                                    ?>
                                                                    @else
                                                                    <?php
                                                                    $pay_at_hotel = $paid;
                                                                    ?>
                                                                    @endif
                                                                    <section style="float:right;width: 50%;">
                                                                        <i style="font-size:15px;color:#434343;"></i> <font class="legend-font"><b>PAY AT HOTEL :</b></font>
                                                                        <font class="legend-content">&#8377;<?=$pay_at_hotel?></font>
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
                                                                <h4>Regards,</h4>
                                                                <h4 class="alignment"><?=$hotel_display_name?></h4>
                                                                <h4 class="alignment">Mobile no.: <span style="font-weight:normal;"><?=$hotel_mobile?></span></h4>
                                                                <h4 class="alignment">Email: <span style="font-weight:normal;"><?=$hotel_email_id?>
                                                                <h4 class="alignment">Address: <span style="font-weight:normal;"><?=$hotel_address?></span></h4>
                                                                <br>

                                                                @if($partner_name !='')
                                                                <strong>Agent Info: </strong><br>
                                                                <span><?= $partner_name ?></span><br>
                                                                <span><?= $partner_address ?></span><br>
                                                                <span><?= $city ?>,<?=$state?>,<?=$country?></span><br><br>
                                                                @endif
                                                                
                                                                @if($payment_type == '1')
                                                                <strong>NOTE: </strong><br>
                                                                <span style="color:red;">
                                                                    - Booking Confirmation is subjective to completion of the payment.
                                                                    <br>
                                                                    @if(!empty($expiry_time))
                                                                    @if($validity_type == '1')
                                                                        - Payment link is valid upto <span><?php echo date('h:i a (d M Y)',strtotime($expiry_time))?></span>.
                                                                    @else
                                                                        - Payment link is valid upto <span><?php echo date('h:i a (d M Y)',strtotime($expiry_time))?></span>.
                                                                    @endif
                                                                    @endif
                                                                </span>
                                                                <br><br>
                                                                @endif
                                                                <strong>Cancellation Policy: </strong><br>
                                                                <span style="font-weight:normal;">
                                                                <?=$cancel_policy?>
                                                                </span>
                                                            </section>
                                                        </td>
                                                    </tr>     
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
 </html>