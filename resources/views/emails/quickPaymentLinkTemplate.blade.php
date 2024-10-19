<html>
<head>
  <title></title>
</head>
<body>
  <table width="600" border="0" cellspacing="0" cellpadding="0" align="center">
    <tbody>
      <tr>
        <td valign="top" height="68">
          <p style="background:#ed9322;float:left;width:100%;text-align:center;width:100%;color:white;margin:0;font-size:30px;font-weight:bold;line-height:68px;font-family:Arial,Helvetica,sans-serif">Quick Payment Link</p>
        </td>
      </tr>
      <tr>
        <td>
          <table width="100%" cellspacing="0" cellpadding="0" style="border:1px #a1a1a1 solid;background:#f7f5f5">
            <tbody>
              <tr>
                <td>
                  <p style="height:10px;line-height:10px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                </td>
              </tr>
              <tr>
                <td>
                  <p style="height:10px;line-height:10px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                </td>
              </tr>
              <tr>
                <td valign="top">
                  <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tbody>
                      <tr>
                        <td width="10">&nbsp;</td>
                        <td>
                          <table width="578" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff">
                            <tbody>
                              <tr>
                                <td colspan="4">
                                  <p style="height:12px;line-height:12px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                                </td>
                              </tr>
                              <tr>
                                <td width="10">
                                </td>
                                <td valign="top" width="279">
                                  <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif;font-weight:bold">Invoice Date:
                                  </td>
                                  <td valign="top">
                                    <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif"><?php echo date('Y-m-d H:i:s'); ?>
                                    </p>
                                  </td>
                                    <td width="10"></td>
                                  </tr>
                                  <tr>
                                    <td colspan="4">
                                      <p style="height:16px;line-height:16px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td width="10"></td>
                                    <td valign="top" width="229">
                                      <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif">Hotel Name: </p>
                                    </td>
                                    <td valign="top">{{$hotel_name}} </td>
                                    <td width="10"></td>
                                  </tr>
                                  <tr>
                                    <td width="10"></td>
                                    <td valign="top" width="229">
                                      <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif">Room Type: </p>
                                    </td>
                                    <td valign="top">{{$room_type}} </td>
                                    <td width="10"></td>
                                  </tr>
                                  <tr>
                                    <td width="10"></td>
                                    <td valign="top" width="229">
                                      <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif">rooms: </p>
                                    </td>
                                    <td valign="top">{{$number_of_rooms}} </td>
                                    <td width="10"></td>
                                  </tr>
                                  <tr>
                                    <td width="10"></td>
                                    <td valign="top" width="229">
                                      <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif">Check In: </p>
                                    </td>
                                    <td valign="top">{{$check_in}} </td>
                                    <td width="10"></td>
                                  </tr>
                                  <tr>
                                    <td width="10"></td>
                                    <td valign="top" width="229">
                                      <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif">Check Out: </p>
                                    </td>
                                    <td valign="top">{{$check_out}} </td>
                                    <td width="10"></td>
                                  </tr>
                                  <tr>
                                    <td width="10"></td>
                                    <td valign="top" width="229">
                                      <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif">Comments: </p>
                                    </td>
                                    <td valign="top">{{$comment}} </td>
                                    <td width="10"></td>
                                  </tr>
                                  <tr>
                                    <td colspan="4">
                                      <p style="height:12px;line-height:12px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </td>
                            <td width="10">&nbsp;</td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p style="height:10px;line-height:10px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                    </td>
                  </tr>
                  <tr>
                    <td valign="top">
                      <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tbody>
                          <tr>
                            <td width="10">&nbsp;</td>
                            <td>
                              <table width="578" border="0" cellspacing="0" cellpadding="0" style="border:1px #a1a1a1 solid;background:#ffffff;border-bottom:none">
                                <tbody>
                                  <tr style="background:#a1a1a1">
                                    <td width="10"></td>
                                    <td height="25" width="323" style="border-right:1px #fff solid">
                                      <p style="color:#fff;font-size:12px;line-height:14px;font-family:Arial,Helvetica,sans-serif;margin:0">Description</p>
                                    </td>
                                    <td width="15"></td>
                                    <td width="127">
                                      <p style="color:#fff;font-size:12px;line-height:14px;font-family:Arial,Helvetica,sans-serif;margin:0">Total</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td width="10" style="border-bottom:1px #a1a1a1 solid"></td>
                                    <td height="25" width="326" style="border-right:1px #a1a1a1 solid;border-bottom:1px #a1a1a1 solid">
                                      <p style="color:#333;font-size:12px;line-height:14px;font-family:Arial,Helvetica,sans-serif;margin:0">Quick Payment Amount (*)</p>
                                    </td>
                                    <td width="15" style="border-bottom:1px #a1a1a1 solid"></td>
                                    <td width="127" style="border-bottom:1px #a1a1a1 solid">
                                      <p style="color:#333333;font-size:12px;line-height:14px;font-family:Arial,Helvetica,sans-serif;margin:0"><?=$amount ?></p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td width="10" style="border-bottom:1px #a1a1a1 solid"></td>
                                    <td height="25" width="326" align="right" style="border-right:1px #a1a1a1 solid;border-bottom:1px #a1a1a1 solid">
                                      <p style="color:#333;font-size:12px;font-weight:bold;line-height:14px;font-family:Arial,Helvetica,sans-serif;margin:0">Total INR (Indian Rupees)&nbsp;&nbsp;</p>
                                    </td>
                                    <td width="15" style="border-bottom:1px #a1a1a1 solid"></td>
                                    <td width="127" style="border-bottom:1px #a1a1a1 solid"><a href="#m_7555620040032668379_" style="color:#333333!important;text-decoration:underline;font-size:12px;line-height:14px;font-family:Arial,Helvetica,sans-serif;margin:0"><?=$amount ?></a></td>
                                  </tr>
                                </tbody>
                              </table>
                            </td>
                            <td width="10">&nbsp;</td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p style="height:10px;line-height:10px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <table width="600" border="0" cellspacing="0" cellpadding="0">
                        <tbody>
                          <tr>
                            <tr>
                              <td width="19">&nbsp;</td>
                              <td width="471">
                                <center><a href="<?= $payment_url ?>" style="display: block; padding: 20px; width: 300px; background: rgb(1, 158, 63); color: #fff; text-decoration: none; text-align: center; font-size: 20px; font-weight: bold;">
                                  CLICK HERE TO PAY NOW
                                </a></center>

                              </td>
                              <td width="10"></td>
                            </tr>

                            <tr><td colspan="3"><center>[OR]</center></td></tr>

                            <td width="19">&nbsp;</td>
                            <td width="471">
                              <p style="color:#000000;font-size:13px;margin:0;line-height:15px;font-family:Arial,Helvetica,sans-serif">Click on the link below to intiate transaction <br><br> <a href="{{$payment_url }}" style="color:#000000!important;text-decoration:underline;font-size:13px;line-height:19px" target="_blank">{{$payment_url}} <wbr>c87d&amp;type=1</a></p>
                              </td>
                              <td width="10"></td>
                            </tr>
                          </tbody></table>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <p style="height:10px;line-height:10px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <p style="height:10px;line-height:10px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                        </td>
                      </tr>
                      <tr>
                        <td align="center">
                          <table width="500" border="0" cellspacing="0" cellpadding="0">
                            <tbody><tr>
                              <td width="10">&nbsp;</td>
                              <td align="center">
                                <p style="font-size:13px;font-family:Arial,Helvetica,sans-serif;margin:0;color:#000;line-height:19px;font-style:italic">Click or Copy Paste the above link to <span class="il">pay</span> this Bill/Invoice using Credit Cards(Master/Visa) and Debit Cards(Master/Visa/Maestro). <br> Indian bank account holders can also <span class="il">pay</span> by debiting any of their following Internet Bank Accounts, </p>
                              </td>
                              <td width="10">&nbsp;</td>
                            </tr>
                          </tbody></table>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <p style="height:10px;line-height:10px;font-size:1px;margin:0;float:left;width:100%">&nbsp;</p>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tbody><tr>
                              <td width="10">&nbsp;</td>
                              <td>
                                <table width="100%" cellspacing="0" cellpadding="0" style="border:1px #bcbcbc solid;border-right:none" bgcolor="#fff">
                                  <tbody><tr>
                                    <td colspan="3" bgcolor="#a1a1a1" valign="middle" align="center" height="26">
                                      <p style="color:#fff;font-size:16px;margin:0;line-height:18px;font-family:Arial,Helvetica,sans-serif">Banks</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td width="50%" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Corporation Bank</p>
                                    </td>
                                    <td width="50%" height="30" valign="middle" style="border-right:1px #bcbcbc solid;padding-left:5px;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px">Union Bank of India</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">AXIS Bank NetBanking</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Citibank Netbanking</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Andhra Bank</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Bank of Baroda - Corporate Banking</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Bank of Baroda - Retail Banking</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Bank of India</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">City Union Bank</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Federal Bank</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Federal Bank</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Indian Overseas Bank</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">ING Vyasa Bank</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Karur Vysya Bank</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Kotak Mahindra Bank</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Lakshmi Vilas Bank</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Punjab National Bank - Corporate Banking</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Punjab National Bank - Retail Banking</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">South Indian Bank</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">State Bank of Hyderabad</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Syndicate Bank</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Tamilnad Mercantile Bank</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Jammu and Kashmir Bank</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Standard Chartered Bank</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">State Bank of Mysore</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">State Bank of Travancore</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Bank of Maharashtra</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Bank of Bahrain and Kuwait</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Oriental Bank of Commerce</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Yes Bank</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">HDFC Bank</p>
                                    </td>
                                    <td height="30" valign="middle" bgcolor="#f7f5f5" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">ICICI NetBanking</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">State Bank of India</p>
                                    </td>
                                    <td height="30" valign="middle" style="border-right:1px #bcbcbc solid;border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Industrial Development Bank of India</p>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td height="30" bgcolor="#f7f5f5" valign="middle" style="border-right:1px #bcbcbc solid; border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Deutsche Bank</p>
                                    </td>
                                    <td height="30" bgcolor="#f7f5f5" valign="middle" style="border-right:1px #bcbcbc solid; border-bottom:1px #bcbcbc solid">
                                      <p style="font-family:Arial,Helvetica,sans-serif;margin:0;font-size:12px;line-height:14px;padding-left:5px">Karnataka Bank Ltd</p>
                                    </td>
                                  </tr>
                                  <tr><td colspan="2" height="30" bgcolor="#f7f5f5" valign="middle" style="border-right:1px #bcbcbc solid"><center><a href="https://bookingjini.com/"><img src="https://bookingjini.com/images/logo.png" width="120px" alt="" border="0" style="border:0" class="CToWUd"> </a></center></td></tr>
                                </tbody></table>
                              </td>
                              <td width="10">&nbsp;</td>
                            </tr>
                          </tbody></table>
                        </td>
                      </tr>
                      <tr>
                        <td height="10"></td>
                      </tr>
                    </tbody></table>
                  </td>
                </tr>
                <tr><td></td>
                </tr></tbody></table>
              </body>
              </html>
