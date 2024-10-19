<!doctype html>
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
<body style="font-family:Gotham, 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color:#f0f2ea; margin:0; padding:0; color:#333333;">
<table width="100%" bgcolor="#f0f2ea" cellpadding="0" cellspacing="0" border="0">
<tbody>
<tr>
<td style="padding:40px 0;">
<table cellpadding="0" cellspacing="0" width="608" border="0" align="center">
<tbody>
<tr>
<td>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="box-shadow: 5px 8px 10px 0px lightgray!important ;">
<tbody>
<tr>
<td width="4" height="4" style="background:url(shadow-left-top.png) no-repeat 100% 0;"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
<td colspan="3" rowspan="3" bgcolor="#FFFFFF" style="padding:0 0 30px;">
<span style="width:604px;height:80px;display:block;">
<p style="float:left;padding-left:20px;font-size:24px; font-weight:bold; color:#f18951;">
Booking Details
</p>
<p class="footer-logo" style="float:right;padding-right:20px;padding-top:20px;">
<img src="https://bookingjini.com/wp-content/uploads/2017/05/download.png"  alt="" >
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
Dear <?=$hotel_name?>,<br/>
We have received a <?= $booking_status ?> booking for a hotel on <?=$ota_name?>. You might have already received this directly from channel through an Email/SMS.</span></p>
</td>
<td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
</tr>
<tr>
<td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
<td>
<hr  class="style18">
<span style="width:100%;height:50px;">
<p style="font-family:cursive;font-size:18px;color:#e55201;float:left;"><font class="legend-font" style="font-weight:bold;color:#e55201;border:1px solid #e55201;padding:2px;"> Booking Id : <?=$booking_id?></font></p><br/>
</span>
<div style="float:left;">
<i style="font-family:cursive;font-size:18px;color:#e55201;float:left;">Hotel Details </i><br/><br/>
<i id="icon-font" class=""></i> <font class="legend-font" style="float:left;" >Hotel Name : </font> <font class="legend-content"><?=$hotel_name?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font">Address : </font> <font class="legend-content"><?=$hotel_address?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font"> City : </font> <font class="legend-content"><?=$city_name?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font"> State : </font> <font class="legend-content"><?=$state_name?></font><br/>     
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
<i id="icon-font"  class=""></i><font class="legend-font">Channel Name : </font> <font class="legend-content"><?=$ota_name?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font">Booking Date : </font> <font class="legend-content"><?= $booking_date ?></font><br/>
<i id="icon-font" class=""></i><font class="legend-font">No of Nights : </font> <font class="legend-content"><?= $number_of_nights?></font><br/>    
</section>
<section style="float:right;">
<i id="icon-font" class=""></i> <font class="legend-font">Checkin Date : </font> <font class="legend-content"><?= $check_in ?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font">Checkout Date : </font> <font class="legend-content"><?= $check_out ?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font">Booking Status : </font> <font class="legend-content"><?= $booking_status ?></font><br/>
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
<i id="icon-font" class=""></i> <font class="legend-font"> Guest Name : </font> <font class="legend-content"><?= $customer_details ?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font"> Guest Email : </font> <font class="legend-content"><?= $customer_email ?></font><br/>
<i id="icon-font" class=""></i> <font class="legend-font"> Guest Number : </font> <font class="legend-content"><?= $customer_number ?></font><br/>
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
</tr> 
<?php
if($ota_name == 'Agoda.com' || $ota_name == 'Expedia' || $ota_name == 'Yatra.Com 1101-03' || $ota_name == 'Travelguru')
{
?>
<tr class="row">
<td class="col-md-3"><font class="legend-content"><?=$room_type?></font></td>
<td class="col-md-3"><font class="legend-content"><?=$rate_plan?></font></td>
<td class="col-md-2"><font class="legend-content"><?=$rooms[0]?></font></td>
<td class="col-md-2"><font class="legend-content"><?=$no_of_adult[0]?></font></td>
<td class="col-md-2"><font class="legend-content"><?=$no_of_child[0]?></font></td> 
</tr>
<?php
}
else{
$room_type= explode(",", $room_type);
$rate_plan= explode(",",$rate_plan);
for($i=0; $i < sizeof($rooms);$i++) 
{?>
<tr class="row">
<td class="col-md-3"><font class="legend-content"><?=$room_type[$i]?></font></td>
<td class="col-md-3"><font class="legend-content"><?=sizeof($rooms)>sizeof($rate_plan)?$rate_plan[0]:$rate_plan[$i]?></font></td>
<td class="col-md-2"><font class="legend-content"><?=$rooms[$i]?></font></td>
<td class="col-md-2"><font class="legend-content"><?=$no_of_adult[$i]?></font></td>
<td class="col-md-2"><font class="legend-content"><?=$no_of_child[$i]?></font></td> 
</tr>
<?php }
}?>
</tbody>
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
<section>
                                                                                    <?php if($ota_name=='Booking.com' || $ota_name == 'Expedia'){?>
<i id="icon-font" class=""></i> <font class="legend-font">Amount before tax : </font> <font class="legend-content"><?= $total_amount.' '.strtoupper($currency)?></font><br/>
                                                                                    <?php }else{?>
<i id="icon-font" class=""></i> <font class="legend-font">Amount before tax : </font> <font class="legend-content"<?= ((int)$total_amount-(int)$tax_amount).' '.strtoupper($currency)?></font><br/>
                                                                                    <?php }?>
</section>
                                                                            <section>
<i id="icon-font" class=""></i><font class="legend-font"> Tax Amount : </font> <font class="legend-content"><?=$tax_amount.' '.strtoupper($currency)?></font><br/>
                                                                            </section>
<section>
<?php if($ota_name=='Booking.com' || $ota_name == 'Expedia'){?>
<i id="icon-font" class=""></i><font class="legend-font"> Total Amount : </font> <font class="legend-content"><?=($total_amount+$tax_amount).' '.strtoupper($currency)?></font><br/>
<?php }else{?>
<i id="icon-font" class=""></i><font class="legend-font"> Total Amount : </font> <font class="legend-content"><?=($total_amount).' '.strtoupper($currency)?></font><br/>
<?php }?>
</section>
<?php if($ota_name == 'Booking.com'){?>
<section>
<i id="icon-font" class=""></i><font class="legend-font"> OTA Commission: </font> <font class="legend-content"><?=round(($total_amount * 15)/100).' '.strtoupper($currency) ?></font><br/>
</section>
<?php } ?>
<section>
<i id="icon-font" class=""></i><font class="legend-font"> Payment Status : </font> <font class="legend-content"><?=$payment_status?></font><br/>
</section>

</td>
                                                                    <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
<tr>
                                                                    <td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
<td>
                                                                        <hr  class="style18">
<p style="font-family:cursive;font-size:18px;color:#e55201">Inclusion </p>
                                                                            <section style="float:left;">
<i id="icon-font" class=""></i> <font class="legend-font">Inclusion : </font> <font class="legend-content"><?=$inclusion?></font><br/>
                                                                            </section>
</td>
                                                                    <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
</tr>
                                                            <tr>
<td width="30"><p style="margin:0; font-size:1px;">&nbsp;</p></td>
                                                                    <?php if($cancel_policy || $special_info){ ?>
<td>
                                                                        <hr  class="style18">
<p style="font-family:cursive;font-size:18px;color:#e55201">More Information </p>
                                                                        <?php if($special_info !=""){ ?>
<section style="float:left;">
                                                                                    <i id="icon-font" class=""></i> <font class="legend-font">Special Information : </font> <font class="legend-content"><?=$special_info?></font><br/>
</section>
                                                                        <?php } ?>
<?php if($cancel_policy !=""){ ?>
                                                                            <section style="float:left;">
<i id="icon-font" class=""></i> <font class="legend-font">Cancellation Policy : </font> <font class="legend-content"><?=$cancel_policy?></font><br/>
                                                                            </section>
<?php } ?>
                                                                    </td>
<?php } ?>
                                                                    <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
</tr>
                                                    </tbody>
</table>
                                                <table cellpadding="0" cellspacing="0" border="0" width="100%">
<tbody>
                                                            <tr>
<td>
                                                                        <p><hr style=" border: 1px solid #f18951;"></p>
<p class="footer-logo"><img src="https://bookingjini.com/wp-content/uploads/2017/05/download.png"  alt="" ></p>
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
</html>
