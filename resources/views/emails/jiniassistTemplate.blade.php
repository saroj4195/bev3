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
        .button
        {
        border-radius: 5px;
        border:1px solid #34bfa3;
        color: #34bfa3;
        background-color: white;
        text-align: center;
        font-size: 20px;
        padding: 10px;
        width: 250px;
        transition: all 0.5s;
        cursor: pointer;
        box-shadow: -5px 5px 5px 1px rgba(52,191,163,0.19)!important ;
        float:right;
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
                                                       
                                                    </p>
                                                    <p class="footer-logo" style="float:right;padding-right:20px;padding-top:20px;">
                                                        <img src="https://bookingjini.com/wp-content/uploads/2017/05/download.png"  alt="" >
                                                    </p>
                                                </span>
                                                <font><hr style=" border: 1px solid #f18951;"></font>
                                                <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                                    <tbody style="margin-left:10px;height:200px;">
                                                            <tr>
                                                                    <td width="30"><p style="margin:0; font-size:1px; line-height:1px;">&nbsp;</p></td>
                                                                    <td colspan="3">
                                                                        <p style="font-size:15px; line-height:22px; color:#333333; margin:0 0 5px;">
                                                                            <span style=" text-decoration:none;">
                                                                            <p>This user have some query regarding your hotel,Kindly send your response</p><br/>
                                                                            <p>Contact Details:</p> 
                                                                            Name:{{$name}} <br/>
                                                                            Email:{{$email_id}} <br/>
                                                                            Mobile No:{{$mobile}}
                                                                            </span>
                                                                    </td>
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
                                                                            Website: <a href="//pixelbuddha.net/" style="color:#0795ff;font-family:arial; text-decoration:none; font-weight:bold;">www.bookingjini.com</a>
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
