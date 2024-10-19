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
font-family:Courier New, Courier, monospace;
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
font-size:22px;
            padding-left:10px;
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
        border:1px solid #34bfa3;
color: #34bfa3;
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
background-color:#34bfa3;
        box-shadow:none;
}

.button:hover span:after
        {
opacity: 1;
        right: 0;
}

</style>
    </head>
<body>
<table cellspacing="0" cellpadding="0" border="0" bgcolor="#F3F3F3" width="100%">
<tbody>
<tr >
<td width="15"></td>
<td>
<div style="display:block;max-width:600px;margin:0 auto;">
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
<td>
<font size="3" face="Arial" color="#e55201" class="penindconfrm">
<b>Pending Confirmation</b>
</font>
</td>
<td>
<a href="<?=$supplied_details['url']?>"><button class="button"><span><b> <i style="font-size:18px;color:#34bfa3" class="">&#8377;</i> Pay Now</b> </span></button></a>
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
<fieldset style="margin:10px;">
<legend class="legend-header">Hotel Deatils</legend>
<section style="float:left;">
<i style="font-size:15px;color:#434343;" class=""></i> <font class="legend-font"><b>Hotel Name :</b></font> <font class="legend-content"><?=$supplied_details['hotel_display_name']?></font><br/><br/>
<i style="font-size:15px;color:#434343" class=""></i> <font class="legend-font"><b>Mobile No :</b></font> <font class="legend-content"><?=$supplied_details['hotel_mobile']?></font><br/><br/>
<i style="font-size:15p;color:#434343" class=""></i> <span class="glyphicon glyphicon-envelope" style="color:red;"></span> <font class="legend-font"><b>Email :</b></font> <font class="legend-content"><?=$supplied_details['hotel_email_id']?></font><br/><br/>
<i style="font-size:15px;color:#434343;" class=""></i> <font class="legend-font"><b>Address :</b></font> <font class="legend-content"><?=$supplied_details['hotel_address']?></font>
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
<legend class="legend-header">User Information</legend>
<section style="float:left;">
<i style="font-size:15p;color:#434343" class=""></i> <span class="legend-font"><b>Guest Name :</b></span> <span class="legend-content"><?=$supplied_details['name']?></span><br/><br/>
<i style="font-size:15p;color:#434343" class=""></i>  <span class="legend-font"><b>Email :</b></span> <span class="legend-content"><?=$supplied_details['user_email_id']?></span>
</section>
<section style="float:right;">
<i style="font-size:15px;color:#434343" class=""></i> <span class="legend-font"><b>Mobile No :</b></span> <span class="legend-content"><?=$supplied_details['user_mobile']?> </span>
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
<legend class="legend-header">Invoice Details</legend>
<section style="float:left;">
<i style="font-size:15px;color:#434343" class=""></i> <span class="legend-font"><b>Check In:</b></span> <span class="legend-content"><?=$supplied_details['check_in']?></span><br/><br/>
<i style="font-size:15px;color:#434343" class=""></i> <span class="legend-font"><b>Check Out :</b></span> <span class="legend-content"><?=$supplied_details['check_out']?></span><br/><br/>
<i style="font-size:15px;color:#434343" class=""></i> <span class="legend-font"><b>Booking Date :</b></span> <span class="legend-content"><?=$supplied_details['booking_date']?></span><br/><br/>
<i style="font-size:15px;color:#434343;" class=""></i> <span class="legend-font"><b>Room Type :</b></span> <span class="legend-content"><?=$supplied_details['room_type']?></span>
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
<legend class="legend-header">Summary</legend>
<section style="float:left;">
<span class="legend-font"><b>Total Paid Amount:</b></span>
</section>
<section style="float:right;">
<span class="legend-font"><b><i style="font-size:15px;color:#434343" class=""></i> </b></span> <span class="legend-content">&#8377;<?=$supplied_details['paid']?></span>
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
<p class="footer-logo"><img src="https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/logo.jpg"  alt="" ></p>
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
</div>
</td>
</tr>
</tbody>
</table>
</body>
 </html>
