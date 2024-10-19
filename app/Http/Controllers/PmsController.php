<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\IdsReservation;
use App\IdsRoom;
use App\PmsAccount;
use App\IdsXml;
use App\MasterRatePlan;
use App\KtdcRoom;
use App\KtdcAgentCode;
use App\KtdcReservation;
use App\WinhmsRoom;
use App\WinhmsReservation;
use App\HotelInformation;
use App\WinhmsRatePlan;

class PmsController extends Controller
{
    public function idsBookings($hotel_id, $type, $booking_data, $customer_data, $booking_status)
    {
        $ids_hotel_code = $this->getIdsHotel($hotel_id);
        $push_bookings_xml = '<OTA_HotelResNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="f65b2d0b-b772-4496-a138-45267d1c3ae9" TimeStamp="' . date('Y-m-d') . 'T00:00:00.00+05:30" Version="3.002" ResStatus="' . $booking_status . '">
                      <POS>
                        <Source>
                          <RequestorID Type="22" ID="Bookingjini" />
                          <BookingChannel Type="CHANNEL">
                            <CompanyName Code="BKNG">' . $type . '</CompanyName>
                          </BookingChannel>
                        </Source>
                      </POS>
                      <HotelReservations>
                        <HotelReservation CreateDateTime="' . date('Y-m-d') . 'T00:00:00.00+05:30">
                          <UniqueID Type="14" ID="' . $booking_data['booking_id'] . '" ID_Context="Bookingjini" />
                          <RoomStays>';
        $tot_amt = 0;
        $tax_amt = 0;
        foreach ($booking_data['room_stay'] as $room_data) {
            $ids_room_type = $this->idsRoomCode($room_data['room_type_id']);
            $ids_plan_type = $this->RatePlan($room_data['rate_plan_id']);
            $push_bookings_xml .= '<RoomStay>
                                  <RoomTypes>
                                      <RoomType NumberOfUnits="1" RoomTypeCode="' . $ids_room_type . '" />
                                  </RoomTypes>
                                  <RatePlans>
                                  <RatePlan RatePlanCode="1" MealPlanCode="' . $ids_plan_type . '" />
                                  </RatePlans>
                                  <RoomRates>
                                      <RoomRate RoomTypeCode="' . $ids_room_type . '" RatePlanCode="1">';
            $tot_amt = 0;
            $tax_amt = 0;
            foreach ($room_data['rates'] as $book_data) {

                $tot_amt += $book_data['amount'];
                $tax_amt += $book_data['tax_amount'];
                $push_bookings_xml .= '<Rates>
                                              <Rate EffectiveDate="' . $book_data["from_date"] . '" ExpireDate="' . $book_data["to_date"] . '" RateTimeUnit="Day" UnitMultiplier="1">
                                                  <Base AmountAfterTax="' . ($book_data["amount"] + $book_data["tax_amount"]) . '" AmountBeforeTax="' . $book_data["amount"] . '" CurrencyCode="INR">
                                                  <Taxes Amount="' . $book_data["tax_amount"] . '" CurrencyCode="INR" />
                                                  </Base>
                                              </Rate>
                                          </Rates>';
            }
            $push_bookings_xml .= '</RoomRate>
                                  </RoomRates>
                              <GuestCounts IsPerRoom="true">
                                <GuestCount AgeQualifyingCode="10" Count="' . $room_data["adults"] . '" />
                              </GuestCounts>
                              <TimeSpan Start="' . $room_data["from_date"] . '" End="' . $room_data["to_date"] . '" />
                              <Total AmountIncludingMarkup="' . ($tot_amt + $tax_amt) . '" AmountAfterTax="' . ($tot_amt + $tax_amt) . '" AmountBeforeTax="' . $tot_amt . '" CurrencyCode="INR">
                                <Taxes Amount="' . $tax_amt . '" CurrencyCode="INR" />
                              </Total>
                              <BasicPropertyInfo HotelCode="' . $ids_hotel_code . '" />
                              <ResGuestRPHs>
                                <ResGuestRPH RPH="1" />
                              </ResGuestRPHs>
                            </RoomStay>';
        }
        $push_bookings_xml .= '</RoomStays>
                          <ResGuests>
                            <ResGuest ResGuestRPH="1">
                              <Profiles>
                                <ProfileInfo>
                                  <Profile ProfileType="1">
                                    <Customer>
                                      <PersonName>
                                        <GivenName>' . $customer_data['first_name'] . '</GivenName>
                                        <Surname>' . $customer_data['last_name'] . '</Surname>
                                      </PersonName>
                                      <Telephone PhoneTechType="1" PhoneNumber="' . $customer_data['mobile'] . '" FormattedInd="false" DefaultInd="true" />
                                      <Email EmailType="1">' . $customer_data['email_id'] . '</Email>
                                      <Address>
                                        <AddressLine>NA</AddressLine>
                                        <CityName>NA</CityName>
                                        <CountryName Code="na">NA</CountryName>
                                      </Address>
                                    </Customer>
                                  </Profile>
                                </ProfileInfo>
                              </Profiles>
                            </ResGuest>
                          </ResGuests>
                        </HotelReservation>
                      </HotelReservations>
                    </OTA_HotelResNotifRQ>';
        $ids = new IdsReservation();
        // $ids_test=new IdsXml();
        $data['hotel_id'] = $hotel_id;
        $data['ids_string'] = $push_bookings_xml;
        $data['ids_xml'] = $push_bookings_xml;
        // $data_test['ids_xml']=$push_bookings_xml;
        if ($ids->fill($data)->save()) {
            // $resp =  $ids_test->fill($data_test)->save();
            return $ids->id;
        } else {
            return false;
        }
    }
    public function getIdsHotel($hotel_id)
    {
        $resp = IdsRoom::where('hotel_id', $hotel_id)->select('ids_hotel_code')->first();
        if ($resp) {
            return $resp->ids_hotel_code;
        } else {
            return false;
        }
    }
    public function idsRoomCode($room_type_id)
    {
        $ids_room = IdsRoom::select('ids_room_type_code')
            ->where('room_type_id', '=', $room_type_id)
            ->first();
        return  $ids_room['ids_room_type_code'];
    }
    public function ratePlan($rate_plan_id)
    {
        $plan = MasterRatePlan::select('plan_type')
            ->where('rate_plan_id', '=', $rate_plan_id)
            ->first();
        return  $plan['plan_type'];
    }
    public function getIdsStatus($hotel_id)
    {
        $resp = PmsAccount::where('name', 'IDS NEXT')->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')->first();
        if ($resp) {
            return true;
        } else {
            return false;
        }
    }
    public function getKtdcHotel($hotel_id)
    {
        $resp = KtdcRoom::where('hotel_id', $hotel_id)->select('ktdc_hotel_code')->first();
        if ($resp) {
            return $resp->ktdc_hotel_code;
        } else {
            return false;
        }
    }
    public function ktdcRoomCode($room_type_id)
    {
        $ktdc_room = KtdcRoom::select('ktdc_room_type_code')
            ->where('room_type_id', '=', $room_type_id)
            ->first();
        return  $ktdc_room['ktdc_room_type_code'];
    }
    public function getKtdcStatus($hotel_id)
    {
        $resp = PmsAccount::where('name', 'KTDC')->select('hotels')->first();
        if (strpos($resp->hotels, "$hotel_id") > 0) {
            return true;
        } else {
            return false;
        }
    }
    public function ktdcBookings($hotel_id, $booking_data, $customer_data, $booking_status, $from_date, $to_date)
    {
        $change_from_date = date('d-m-Y', strtotime($from_date));
        $change_to_date = date('d-m-Y', strtotime($to_date));
        $from_date = str_replace('-', '/', $change_from_date);
        $to_date = str_replace('-', '/', $change_to_date);
        $ktdc_hotel_code = $this->getKtdcHotel($hotel_id);
        $agent_code_details = KtdcAgentCode::where('ota_name', 'BookingEngine')->first();
        if ($agent_code_details) {
            $agent_code = $agent_code_details->agent_code;
        } else {
            $agent_code = '';
        }
        if ($customer_data['email_id'] != 'NA') {
            $email = $customer_data['email_id'];
        } else {
            $email = '';
        }
        if ($customer_data['mobile'] != 'NA') {
            $mobile = $customer_data['mobile'];
        } else {
            $mobile = '';
        }
        $customer_name = $customer_data['first_name'] . ' ' . $customer_data['last_name'];
        $total_booking_amount = round($booking_data['total_booking_amount']);
        $total_gst = round($booking_data['booking_tax_amount']);
        $total_amount = $total_booking_amount + $total_gst;
        $get_rate_plan_info = MasterRatePlan::select('plan_type')->where('rate_plan_id', $booking_data['rate_plan_id_info'])->first();
        $plan_id_ktdc =  $get_rate_plan_info->plan_type;
        $ktdc_response_xml = '<?xml version="1.0" encoding="utf-8"?>
              <SoftBookRequest Key="418367c9-d295-b842-b6a5-60539369811b" UserID="Bookingjini">
                  <GuestDetails Title="Mr." Name="' . $customer_name . '" Address1="" Address2=""
                  Address3="" Country="India" Pin="" EmailId="' . $email . '"
                  LandPhoneNo="" MobileNo="' . $mobile . '" AgentCode="' . $agent_code . '" Instructions=""
                  AllInclusiveRates="Yes"/>
                  <Property ID="' . $ktdc_hotel_code . '" CheckInDate="' . $from_date . '" CheckInTime="" CheckOutDate="' . $to_date . '"
                  CheckOutTime="" PlanId="' . $plan_id_ktdc . '" TotalPax="' . $booking_data["display_pax"] . '" Female="0" Children="0" Infants="0"
                  Foreigner="0" TotalAmount="' . $total_amount . '">';
        foreach ($booking_data["room_stay"] as $room_details) {
            $room_type_id = $room_details["room_type_id"];
            $get_room_code = KtdcRoom::select('ktdc_room_type_code')->where('hotel_id', $hotel_id)->where('room_type_id', $room_type_id)->first();
            $ktdc_room_type_code = $get_room_code->ktdc_room_type_code;
            $rate_plan_id = $room_details["rate_plan_id"];
            $get_rate_plan = MasterRatePlan::select('plan_type')->where('rate_plan_id', $rate_plan_id)->first();
            $ktdc_plan_type = $get_rate_plan->plan_type;
            $total_adult  = $room_details["adults"];
            $total_child  = $room_details["children"];
            $total_infants  = $room_details["infants"];
            $no_of_nights = $room_details["no_of_nights"];
            if ($total_adult == 1) {
                $single = $total_adult;
                $ktdc_response_xml .= '<RoomType ID="' . $ktdc_room_type_code . '" Single="' . $single . '" Double="0" Twin ="0" Adult="0" Child1="' . $total_child . '" Child2="0" Infant="' . $total_infants . '"
                        NoOfRoomNights ="' . $no_of_nights . '" >';
            } else if ($total_adult > 1) {
                $get_per = $total_adult % 2;
                $adult = 0;
                $double = 0;
                if ($get_per > 0) {
                    $adult = $get_per;
                    $adult_no = $total_adult - $adult;
                    $double = $adult_no / 2;
                } else {
                    $double = $total_adult / 2;
                }
                $ktdc_response_xml .= '<RoomType ID="' . $ktdc_room_type_code . '" Single="0" Double="' . $double . '" Twin ="0" Adult="' . $adult . '" Child1="' . $total_child . '" Child2="0" Infant="' . $total_infants . '"
                        NoOfRoomNights ="' . $no_of_nights . '" >';
            }
            foreach ($room_details["rates"] as $rates) {
                $room_amount = round($rates["amount"]);
                $gst = round($rates["tax_amount"]);
                $change_date = date('d-m-Y', strtotime($rates["from_date"]));
                $check_in_date = str_replace('-', '/', $change_date);
                $ktdc_response_xml .= '<Rates Date="' . $check_in_date . '" MealPlan="' . $ktdc_plan_type . '" RoomRate="' . $room_amount . '" Taxes="' . $gst . '" />';
            }
            $ktdc_response_xml .= '</RoomType>';
        }
        if ($booking_status == 'Commit') {
            $ktdc_response_xml .= '</Property>
                    <Action Action="N" ConfirmId="" Remarks=""
                    CMId="' . $booking_data['booking_id'] . '"/>
                    </SoftBookRequest>';
        } else if ($booking_status == 'Modify') {
            $getId  = DB::table('ktdc_softbooking')
                ->select('soft_booking_id')
                ->where('booking_id', $booking_data['booking_id'])
                ->first();
            $ConfirmId = $getId->soft_booking_id;

            $ktdc_response_xml .= '</Property>
                    <Action Action="M" ConfirmId="' . $ConfirmId . '" Remarks=""
                    CMId="' . $booking_data['booking_id'] . '"/>
                    </SoftBookRequest>';
        }

        $ktdc = new KtdcReservation();
        $data['hotel_id'] = $hotel_id;
        $data['ktdc_hotel_code'] = $ktdc_hotel_code;
        $data['booking_id'] = $booking_data['booking_id'];
        $data['ktdc_string'] = $ktdc_response_xml;
        if ($ktdc->fill($data)->save()) {
            return $ktdc->id;
        } else {
            return false;
        }
    }
    public function getWinhmsStatus($hotel_id)
    {
        $resp = PmsAccount::where('name', 'WINHMS')->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')->first();

        if ($resp) {
            return true;
        } else {
            return false;
        }
    }
    public function getWinhmsHotel($hotel_id)
    {
        $resp = WinhmsRoom::where('hotel_id', $hotel_id)->select('winhms_hotel_code')->first();
        if ($resp) {
            return $resp->winhms_hotel_code;
        } else {
            return false;
        }
    }
    public function winhmsRoomCode($room_type_id, $hotel_code)
    {
        $winhms_room = WinhmsRoom::select('winhms_room_type_code')->where(['room_type_id' => $room_type_id, 'winhms_hotel_code' => $hotel_code])->first();
        return $winhms_room['winhms_room_type_code'];
    }
    public function winhmsBookings($hotel_id, $type, $booking_data, $customer_data, $booking_status)
    {
        $winhms_hotel_code = $this->getWinhmsHotel($hotel_id);
        $data["hotel_name"] = HotelInformation::select('hotel_name', 'hotel_id')->where('hotel_id', $hotel_id)->first();
        $hotel_name = $data["hotel_name"]->hotel_name;
        if ($booking_status == 'Commit') {
            $status = 'Commit';
            $type_of_book = 'OTA_HotelResNotifRQ'; //for new booking
            $hotel_reservations = 'HotelReservations';
            $hotel_reservation = 'HotelReservation';
            $send_status = 'new';
        } else if ($booking_status == 'Modify') {
            $status = 'Modify';
            $type_of_book = 'OTA_HotelResModifyNotifRQ'; //for modified booking 
            $hotel_reservations = 'HotelResModifies';
            $hotel_reservation = 'HotelResModifie';
            $send_status = 'modified';
        }
        // name should be proper
        $push_bookings_xml = '<?xml version="1.0" encoding="UTF-8"?> 
                                <' . $type_of_book . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="3ac8497f-3a21-4925-b5de-08af72cdc5fd" TimeStamp="' . date('Y-m-d') . 'T00:00:00.00+05:30" Version="1.0" ResStatus="' . $status . '">
                                    <POS>
                                        <Source>
                                        <RequestorID ID="CHANNEL MANAGER" />
                                        <BookingChannel Type="' . $type . '">
                                            <CompanyName>' . $type . '</CompanyName>
                                        </BookingChannel>
                                        </Source>
                                    </POS>
                                    <' . $hotel_reservations . '>
                                        <' . $hotel_reservation . ' CreateDateTime="' . date('Y-m-d H:i:s') . '">
                                            <UniqueID ID="' . $booking_data["booking_id"] . '" Type="14" ID_Context="' . $booking_data["booking_id"] . '" />
                                            <RoomStays>';
        $tot_amt = 0;
        $tax_amt = 0;
        $i = 0;
        foreach ($booking_data['room_stay'] as $room_data) {
            $rate_plan_id = $room_data['rate_plan_id'];
            $winhms_room_type_db = $this->winhmsRoomCode($room_data['room_type_id'], $winhms_hotel_code);
            $winhms_room_type = (empty($winhms_room_type_db)) ? 'NA' : $winhms_room_type_db;
            $cond = ['rate_plan_id' => $rate_plan_id, 'hotel_id' => $hotel_id];
            $get_rate_plan = WinhmsRatePlan::select('plan_type', 'plan_name')->where($cond)->first();
            $wimhms_plan_type = (empty($get_rate_plan->plan_type)) ? 'NA' : $get_rate_plan->plan_type;
            $wimhms_plan_name = (empty($get_rate_plan->plan_name)) ? 'NA' : $get_rate_plan->plan_name;
            $book_id_array[] = $booking_data['booking_id'] . $i;
            $push_bookings_xml .= '<RoomStay roomstaystatus="' . $send_status . '" roomreservation_id="' . $booking_data['booking_id'] . $i . '" MarketCode="FIT" SourceOfBusiness="OTA">
                    <BasicPropertyInfo HotelCode="' . $winhms_hotel_code . '" HotelName="' . $hotel_name . '" />
                    <RatePlans>
                        <RatePlan RatePlanCode="' . $wimhms_plan_type . '" RatePlanName="' . $wimhms_plan_name . '" />
                    </RatePlans>
                    <RoomTypes>
                        <RoomType NumberOfUnits="' . $room_data["no_of_rooms"] . '" RoomTypeCode="' . $winhms_room_type . '">
                            <RoomDescription Name="" /> 
                        </RoomType>
                    </RoomTypes>
                    <GuestCounts>
                        <GuestCount AgeQualifyingCode="10" Count="' . $room_data['adults'] . '" />
                    </GuestCounts>
                    <ResGuestRPHs>
                        <ResGuestRPH RPH="0" />
                    </ResGuestRPHs>
                    <TimeSpan Start="' . $room_data['from_date'] . '" End="' . $room_data['to_date'] . '" />
                    <RoomRates><RoomRate><Rates>';
            $tot_amt = 0;
            $tax_amt = 0;
            foreach ($room_data['rates'] as $book_data) {
                if ($type == "Booking.com" || $type == "Expedia") {
                    $before_paid_amount = $book_data['amount'];
                    $paid_amount = $book_data['amount'] + $book_data['tax_amount'];
                } else {
                    $paid_amount = $book_data['amount'];
                    $before_paid_amount = $book_data['amount'] - $book_data['tax_amount']; //before tax
                }
                $tot_amt += $paid_amount;
                $tax_amt += $book_data['tax_amount'];
                // need to put +/- for booking.com and expedia the amount is not containing gst but for goibibo and rest amount contain gst also they do not provide room type wise price you have to calculate.
                $push_bookings_xml .= '<Rate EffectiveDate="' . $book_data['from_date'] . '">
                                        <Base AmountBeforeTax="' . $before_paid_amount . '"
                                            AmountAfterTax="' . $paid_amount . '"
                                            CurrencyCode="INR" />
                                    </Rate>';
            }
            $amt_before_tax = $tot_amt - $tax_amt;
            // need to put +/- for booking.com and expedia the amount is not containing gst but for goibibo and rest amount contain gst 

            $push_bookings_xml .= '</Rates></RoomRate></RoomRates>
                                                    <Total AmountBeforeTax="' . $amt_before_tax . '" AmountAfterTax="' . $tot_amt . '" CurrencyCode="INR" />
                                                    <DepositPayments>
                                                        <GuaranteePayment>
                                                            <AmountPercent Amount="" CurrencyCode="INR" />
                                                        </GuaranteePayment>
                                                    </DepositPayments>
                                                    <Comments>
                                                        <Comment Name="">
                                                            <Text></Text>
                                                        </Comment>
                                                    </Comments>
                                                </RoomStay>';
            $i++;
        }
        $push_bookings_xml .= '</RoomStays>
                        <ResGuests>
                            <ResGuest ResGuestRPH="0">
                                <Profiles>
                                    <ProfileInfo>
                                        <UniqueID ID="1" ID_Context="' . $type . '" />
                                        <Profile ProfileType="1">
                                            <Customer>
                                                <PersonName>
                                                    <NameTitle></NameTitle>
                                                    <GivenName>' . $customer_data['first_name'] . '</GivenName>
                                                    <Surname>' . $customer_data['last_name'] . '</Surname>
                                                </PersonName>
                                                <Telephone PhoneNumber="' . $customer_data['mobile'] . '" PhoneTechType="1" />
                                                <Email>' . $customer_data['email_id'] . '</Email>
                                                <Address>
                                                    <AddressLine></AddressLine>
                                                    <CityName></CityName>
                                                    <CountryName Code=""></CountryName>
                                                    <PostalCode></PostalCode>
                                                </Address>
                                                <PaymentForm>
                                                    <PaymentCard CardCode="" ExpireDate="">
                                                        <CardHolderName></CardHolderName>
                                                        <CardType></CardType>
                                                        <CardNumber></CardNumber>
                                                    </PaymentCard>
                                                </PaymentForm>
                                            </Customer>
                                        </Profile>
                                    </ProfileInfo>
                                </Profiles>
                            </ResGuest>
                        </ResGuests>
                        <ResGlobalInfo>
                            <Comments>
                                <Comment>
                                    <Text></Text>
                                </Comment>
                            </Comments>
                            <Total AmountBeforeTax="' . $amt_before_tax . '" AmountAfterTax="' . $tot_amt . '" CurrencyCode="INR" >
                                <Taxes>
                                    <Tax Amount="' . $tot_amt . '" CurrencyCode="INR" />
                                </Taxes>
                            </Total>
                        </ResGlobalInfo>
                    </' . $hotel_reservation . '>
                    </' . $hotel_reservations . '>
                    </' . $type_of_book . '>';
        $winhms = new WinhmsReservation();
        $data['winhms_hotel_code'] = $winhms_hotel_code;
        $data['booking_id'] = $booking_data['booking_id'];
        $data['hotel_id'] = $hotel_id;
        $data['winhms_string'] = $push_bookings_xml;
        $data['winhms_cancellation_string'] = '';
        $data['winhms_confirm'] = 0;
        if ($winhms->fill($data)->save()) {
            return $winhms->id;
        } else {
            return false;
        }
    }
    public function winhmsCancelBooking($hotel_id, $type, $booking_data, $customer_data, $booking_status)
    {
        $winhms_hotel_code = $this->getWinhmsHotel($hotel_id);
        $data["hotel_name"] = HotelInformation::select('hotel_name', 'hotel_id')->where('hotel_id', $hotel_id)->first();
        $hotel_name = $data["hotel_name"]->hotel_name;
        $booking_id = $booking_data['booking_id'];
        $token = '3ac8497f-3a21-4925-b5de-08af72cdc5fd';
        $timestamp = date('Y-m-d\TH:i:sP');
        foreach ($booking_data['room_stay'] as $room_data) {
            $from_date = $room_data['from_date'];
            $to_date = $room_data['to_date'];
        }
        $push_cancell_xml = '<?xml version="1.0" encoding="UTF-8" ?>
                    <OTA_CancelRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05"
                    EchoToken="' . $token . '" TimeStamp="' . $timestamp . '" Version="0" CancelType="Cancel">
                    <POS>
                    <Source>
                    <RequestorID ID="' . $booking_id . '" Type="CRSConfirmNumber" />
                    <BookingChannel Type="Winhms">
                    <CompanyName>' . $type . '</CompanyName>
                    </BookingChannel>
                    </Source>
                    </POS>
                    <UniqueID ID="' . $booking_id . '" Type="14" ID_Context="CRSConfirmNumber" />
                    <Verification>
                    <PersonName>
                    <GivenName>' . $customer_data['first_name'] . '</GivenName>
                    <Surname>' . $customer_data['last_name'] . '</Surname>
                    </PersonName>
                    <ReservationTimeSpan Start="' . $from_date . '" End="' . $to_date . '"/>
                    <TPA_Extensions>
                    <BasicPropertyInfo HotelCode="' . $winhms_hotel_code . '" HotelName="' . $hotel_name . '" />
                    </TPA_Extensions>
                    </Verification>
                    </OTA_CancelRQ>';
        $winhms = new WinhmsReservation();
        $data['winhms_hotel_code'] = $winhms_hotel_code;
        $data['booking_id'] = $booking_id;
        $data['hotel_id'] = $hotel_id;
        $data['winhms_string'] = '';
        $data['winhms_confirm'] = 0;
        $data['winhms_cancellation_string'] = $push_cancell_xml;
        if ($winhms->fill($data)->save()) {
            return $winhms->id;
        } else {
            return false;
        }
    }


    public function idsBookingsTest($hotel_id, $type, $booking_data, $customer_data, $booking_status)
    {
        $ids_hotel_code = $this->getIdsHotel($hotel_id);
        $push_bookings_xml = '<OTA_HotelResNotifRQ xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="f65b2d0b-b772-4496-a138-45267d1c3ae9" TimeStamp="' . date('Y-m-d') . 'T00:00:00.00+05:30" Version="3.002" ResStatus="' . $booking_status . '">
                      <POS>
                        <Source>
                          <RequestorID Type="22" ID="Bookingjini" />
                          <BookingChannel Type="CHANNEL">
                            <CompanyName Code="BKNG">' . $type . '</CompanyName>
                          </BookingChannel>
                        </Source>
                      </POS>
                      <HotelReservations>
                        <HotelReservation CreateDateTime="' . date('Y-m-d') . 'T00:00:00.00+05:30">
                          <UniqueID Type="14" ID="' . $booking_data['booking_id'] . '" ID_Context="Bookingjini" />
                          <RoomStays>';
        $tot_amt = 0;
        $tax_amt = 0;
        foreach ($booking_data['room_stay'] as $room_data) {
            $ids_room_type = $this->idsRoomCode($room_data['room_type_id']);
            $ids_plan_type = $this->RatePlan($room_data['rate_plan_id']);
            $push_bookings_xml .= '<RoomStay>
                                  <RoomTypes>
                                      <RoomType NumberOfUnits="1" RoomTypeCode="' . $ids_room_type . '" />
                                  </RoomTypes>
                                  <RatePlans>
                                  <RatePlan RatePlanCode="1" MealPlanCode="' . $ids_plan_type . '" />
                                  </RatePlans>
                                  <RoomRates>
                                      <RoomRate RoomTypeCode="' . $ids_room_type . '" RatePlanCode="1">';
            $tot_amt = 0;
            $tax_amt = 0;
            foreach ($room_data['rates'] as $book_data) {

                $tot_amt += $book_data['amount'];
                $tax_amt += $book_data['tax_amount'];
                $push_bookings_xml .= '<Rates>
                                              <Rate EffectiveDate="' . $book_data["from_date"] . '" ExpireDate="' . $book_data["to_date"] . '" RateTimeUnit="Day" UnitMultiplier="1">
                                                  <Base AmountAfterTax="' . ($book_data["amount"] + $book_data["tax_amount"]) . '" AmountBeforeTax="' . $book_data["amount"] . '" CurrencyCode="INR">
                                                  <Taxes Amount="' . $book_data["tax_amount"] . '" CurrencyCode="INR" />
                                                  </Base>
                                              </Rate>
                                          </Rates>';
            }
            $push_bookings_xml .= '</RoomRate>
                                  </RoomRates>
                              <GuestCounts IsPerRoom="true">
                                <GuestCount AgeQualifyingCode="10" Count="' . $room_data["adults"] . '" />
                              </GuestCounts>
                              <TimeSpan Start="' . $room_data["from_date"] . '" End="' . $room_data["to_date"] . '" />
                              <Total AmountIncludingMarkup="' . ($tot_amt + $tax_amt) . '" AmountAfterTax="' . ($tot_amt + $tax_amt) . '" AmountBeforeTax="' . $tot_amt . '" CurrencyCode="INR">
                                <Taxes Amount="' . $tax_amt . '" CurrencyCode="INR" />
                              </Total>
                              <BasicPropertyInfo HotelCode="' . $ids_hotel_code . '" />
                              <ResGuestRPHs>
                                <ResGuestRPH RPH="1" />
                              </ResGuestRPHs>
                            </RoomStay>';
        }
        $push_bookings_xml .= '</RoomStays>
                          <ResGuests>
                            <ResGuest ResGuestRPH="1">
                              <Profiles>
                                <ProfileInfo>
                                  <Profile ProfileType="1">
                                    <Customer>
                                      <PersonName>
                                        <GivenName>' . $customer_data['first_name'] . '</GivenName>
                                        <Surname>' . $customer_data['last_name'] . '</Surname>
                                      </PersonName>
                                      <Telephone PhoneTechType="1" PhoneNumber="' . $customer_data['mobile'] . '" FormattedInd="false" DefaultInd="true" />
                                      <Email EmailType="1">' . $customer_data['email_id'] . '</Email>
                                      <Address>
                                        <AddressLine>NA</AddressLine>
                                        <CityName>NA</CityName>
                                        <CountryName Code="na">NA</CountryName>
                                      </Address>
                                    </Customer>
                                  </Profile>
                                </ProfileInfo>
                              </Profiles>
                            </ResGuest>
                          </ResGuests>
                        </HotelReservation>
                      </HotelReservations>
                    </OTA_HotelResNotifRQ>';
        $ids = new IdsReservation();
        // $ids_test=new IdsXml();
        $data['hotel_id'] = $hotel_id;
        $data['ids_string'] = $push_bookings_xml;
        $data['ids_xml'] = $push_bookings_xml;

        return $data;
        // $data_test['ids_xml']=$push_bookings_xml;
        // if ($ids->fill($data)->save()) {
        //     // $resp =  $ids_test->fill($data_test)->save();
        //     return $ids->id;
        // } else {
        //     return false;
        // }
    }
}
