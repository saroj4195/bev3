<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
//////////////////////////////////////////////////////////////////////////////////
/////*===================CRS Routes==================*////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
$router->group(['prefix' => 'crs','middleware' => 'jwt.auth'], function($router) {
    $router->get('/get_room_rates/{user_id}/{hotel_id}/{date_from}/{date_to}',['uses'=>'crs\ManageCrsRatePlanController@getRates']);
    $router->post('/room_rate_update',['uses'=>'crs\CrsRoomRatePlanController@roomRateUpdate']);
    $router->post('/inline-update-rates',['uses'=>'crs\CrsRoomRatePlanController@inlineUpdateRates']);
    $router->get('/get-inventory/{for_user_id}/{hotel_id}/{date_from}/{date_to}',['uses'=>'crs\CrsReservationController@getInventory']);
    $router->post('/reservation',['uses'=>'crs\CrsReservationController@newReservation']);
    $router->get('/booked-reservations/{hotel_id}/{for_user_id}/{from_date}/{to_date}/{type}',['uses'=>'crs\CrsReservationController@getBookedReservations']);
    $router->get('/booking-transactions/{hotel_id}/{for_user_id}/{from_date}/{to_date}',['uses'=>'crs\CrsReservationController@getcreditTransactions']);
    $router->get('/confirmed-reservations/{hotel_id}/{for_user_id}/{from_date}/{to_date}/{type}',['uses'=>'crs\CrsReservationController@getConfirmedReservations']);
    $router->get('/canceled-reservations/{hotel_id}/{for_user_id}/{from_date}/{to_date}/{type}',['uses'=>'crs\CrsReservationController@getCanceledReservations']);
    $router->delete('/reservation/{crs_reserve_id}',['uses'=>'crs\CrsReservationController@deleteReservation']);
    $router->post('/cancel-reservation/{crs_reserve_id}',['uses'=>'crs\CrsReservationController@cancelReservation']);
    $router->get('/agent-credit/{agent_id}',['uses'=>'crs\CrsReservationController@getAgentCredit']);
    $router->post('/agent-reservation',['uses'=>'crs\AgentReservationController@newReservation']);
    $router->post('/unblock-inventory',['uses'=>'crs\ManageCrsRatePlanController@unBlockInventoryByAgent']);
    $router->post('/block-inventory',['uses'=>'crs\ManageCrsRatePlanController@blockInventoryByAgent']);
    $router->get('/agent-credit-hotel/{hotel_id}',['uses'=>'crs\CrsReservationController@getAgentCreditByHotel']);

});

$router->get('/crs/payment/{invoice_id}/{pay_status}',['uses'=>'crs\CrsPaymentGatewayController@actionIndex']);
$router->get('/crs/payment-details/{invoice_id}',['uses'=>'crs\CrsPaymentGatewayController@actionData']);
$router->post('/crs/payu-response',['uses'=>'crs\CrsPaymentGatewayController@payuResponse']);
$router->get('/crs/check-payment-status',['uses'=>'crs\CrsReservationController@sendFollowUpEmails']);
$router->get('/crs/testHtml',['uses'=>'crs\CrsReservationController@testHtml']);
$router->get('/crs/pending_payment/{invoice_id}/{pay_status}',['uses'=>'crs\CrsPaymentGatewayController@actionIndex']);

$router->group(['prefix' => 'rate_plan_settings','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'crs\RoomRateSettingsController@addRoomRate']);
    $router->post('/update/{room_rateplan_id}',['uses'=>'crs\RoomRateSettingsController@updateRoomRate']);
    $router->get('/get/{hotel_id}',['uses'=>'crs\RoomRateSettingsController@getRoomRate']);
    $router->get('/get_byid/{room_rateplan_id}',['uses'=>'crs\RoomRateSettingsController@getRoomRateById']);
    $router->delete('/delete/{room_rateplan_id}',['uses'=>'crs\RoomRateSettingsController@deleteRoomRate']);
    $router->get('/getall_hotels/{company_id}',['uses'=>'crs\RoomRateSettingsController@getAllHotels']);
});

$router->get('/test',['uses'=>'crs\CrsReservationController@testPushIds']);

$router->group(['prefix' => 'crs'],function($router){
    $router->get('/hotel_details/{company_id}',['uses'=>'CrsBookingsController@getHotelDetails']);
    $router->post('/crs_bookings',['uses'=>'CrsBookingsController@crsBookings']);
    // $router->post('/crs_bookings-test',['uses'=>'CrsBookingsTest2Controller@crsBookings']);
    $router->get('/crs_mail/{invoice_id}/{payment_type}',['uses'=>'CrsBookingsController@crsBookingMail']);
    $router->get('/crs_cronjob',['uses'=>'CrsBookingsController@crsBookingCronJob']);
    $router->get('/crs_pay/{booking_id}',['uses'=>'CrsBookingsController@crsPayBooking']);
    $router->post('/crs_cancel_booking',['uses'=>'CrsBookingsController@crsCancelBooking']);
    $router->post('/crs_modify_bookings',['uses'=>'CrsBookingsController@crsModifyBooking']);
    // $router->post('/crs_modify_bookings_test',['uses'=>'CrsBookingsTest2Controller@crsModifyBooking']);
    $router->get('/crs_cancel_refund/{invoice_id}',['uses'=>'CrsBookingsTest2Controller@crsCancelRefund']);
    $router->post('/crs_register_user_modify',['uses'=>'CrsBookingsTest2Controller@crsRegisterUserModify']);
    $router->post('/crs_cancel_details',['uses'=>'CrsBookingsTest2Controller@crsCacelReportData']);

    $router->get('/crs_cronjob_old',['uses'=>'CrsBookingsController@crsBookingCronJobold']);
});

//new Api for crs reservation
$router->post('/crs-reservation-info',['uses'=>'CrsBookingsTest2Controller@crsReservation']);
$router->post('/crs-reservation-info-test',['uses'=>'CrsBookingsController@crsReservation']);
$router->get('/crs-room-type-count/{hotel_id}/{date_from}/{date_to}/{mindays}',['uses'=>'CrsBookingsController@getTotalInvByHotel']);
$router->get('/crs-room-details/{hotel_id}/{room_type_id_info}',['uses'=>'CrsBookingsController@getRoomDetails']);

//new API for crs cancellation booking inv update redirecting controller
$router->post('/cm_ota_booking_inv_status',['uses'=>'CrsCancelBookingInvUpdateRedirectingController@postDetails']);

//fetch the user details from mobile number for crs
$router->post('/get-user-info-crs',['uses'=>'CrsBookingsTestController@getNumberWiseUser']);

//crs confirm report with download option @author swati date: 08-07-2022
$router->get('/crs-repoprt-download/{hotel_id}/{date}/{payment_type}','CrsBookingsController@CrsReportDownload');
$router->post('/crs-confirm-booking-report','CrsBookingsController@crsBookingReport');

// CRS Partner login by Dibyajyoti date:14-10-2024

$router->group(['prefix' => 'partner'],function($router){
    $router->post('/available-rooms', ['uses' => 'PartnerLogin\CrsPartnerBookingsController@getPartnerAvailableRooms']);
    $router->post('/check-available-rooms', ['uses' => 'PartnerLogin\CrsPartnerBookingsController@CheckPartnerAvalableRooms']);
    $router->post('/list-view-bookings', 'PartnerLogin\CrsPartnerBookingsController@partnerListViewBookings');
    $router->post('/bookings/{api_key}', ['uses' => 'PartnerLogin\CrsPartnerBookingsController@bookings']);
    $router->post('/city-list', ['uses' => 'PartnerLogin\CrsPartnerBookingsController@cityList']);
    $router->post('/hotel-list', ['uses' => 'PartnerLogin\CrsPartnerBookingsController@hotelList']);
    $router->post('/booking-report', ['uses' => 'PartnerLogin\CrsPartnerReportsController@partnerBookingReport']);
    $router->get('/bookingVoucher/{invoice_id}', ['uses' => 'PartnerLogin\CrsPartnerBookingsController@bookingVoucher']);
});

$router->post('/add-partner', ['uses' => 'Extranetv4\NewCRS\CrsPartnerBookingsController@addPartner']);


$router->get('/get-partner-recent-bookings/{hotel_id}/{partner_id}', ['uses' => 'Extranetv4\NewCRS\CrsPartnerBookingsController@crsPartnerRecentBookings']);

