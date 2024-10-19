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

$router->group(['prefix' => 'extranetv4'], function ($router) {
     $router->group(['prefix' => 'day-booking'], function ($router) {

          $router->post('menu-setup/{hotel_id}',['uses'=>'DayBooking\DayBookingSetupController@menuSetup']);
          $router->get('menu-setup/{hotel_id}',['uses'=>'DayBooking\DayBookingSetupController@getMenuSetup']);

          $router->get('active-packages/{hotel_id}',['uses'=>'DayBooking\DayBookingSetupController@activePackages']);
          $router->get('inactive-packages/{hotel_id}',['uses'=>'DayBooking\DayBookingSetupController@inactivePackages']);

          $router->post('package-add',['uses'=>'DayBooking\DayBookingSetupController@addDayBookingPackage']);
          $router->post('package-update/{package_id}',['uses'=>'DayBooking\DayBookingSetupController@updateDayBookingPackage']);
          $router->get('package-details/{package_id}',['uses'=>'DayBooking\DayBookingSetupController@dayBookingPackageDetails']);
          $router->get('package-status/{package_id}/{status}',['uses'=>'DayBooking\DayBookingSetupController@packageStatus']);

          $router->post('update-special-price/{package_id}',['uses'=>'DayBooking\DayBookingSetupController@updateSpecialPrice']);
          $router->get('delete-special-price/{id}',['uses'=>'DayBooking\DayBookingSetupController@deleteSpecialPrice']);

          $router->post('update-blackout-dates/{package_id}',['uses'=>'DayBooking\DayBookingSetupController@updateBlackoutDates']);

          $router->post('delete-blackout-dates/{package_id}',['uses'=>'DayBooking\DayBookingSetupController@deleteBlackoutDates']);

          $router->get('availability-calendar/{package_id}/{form_date}/{to_date}',['uses'=>'DayBooking\DayBookingSetupController@avabilityCalendar']);

          $router->post('booking-list/{hotel_id}',['uses'=>'DayBooking\DayBookingSetupController@bookingList']);
          $router->get('booking-details/{booking_id}',['uses'=>'DayBooking\DayBookingSetupController@bookingDetails']);


          $router->get('/cancel-booking/{booking_id}', ['uses' => 'DayBooking\DayBookingModificationController@cancelBooking']);
          $router->get('/modify-details/{booking_id}', ['uses' => 'DayBooking\DayBookingModificationController@bookingDetails']);
          $router->post('/package-availablity', ['uses' => 'DayBooking\DayBookingModificationController@checkPackageAvailablity']);
          $router->post('/modify-booking', ['uses' => 'DayBooking\DayBookingModificationController@modifyDaybooking']);
         
          $router->post('/add-basic-promotion',['uses'=>'DayBooking\DayBookingPromotionsController@addBasisPromotion']);
          $router->post('/update-basic-promotion/{pro_id}',['uses'=>'DayBooking\DayBookingPromotionsController@updateBasicPromotion']);
          $router->get('/get-day-packages/{hotel_id}',['uses'=>'DayBooking\DayBookingPromotionsController@getDayPackages']);
          $router->get('/active-basic-promotions/{hotel_id}',['uses'=>'DayBooking\DayBookingPromotionsController@activeBasicPromotions']);
          $router->get('/basic-promotions-details/{pro_id}',['uses'=>'DayBooking\DayBookingPromotionsController@basicPromotionsDetails']);
          $router->delete('/delete-basic-promotions/{pro_id}',['uses'=>'DayBooking\DayBookingPromotionsController@deleteBasicPromotions']);
     });

    
});

$router->group(['prefix' => 'day-booking'], function ($router) {

     $router->get('easebuzz/access-key/{invoice_id}',['uses'=>'DayBooking\DayBookingPaymentGatewayController@dayBookingEasebuzzAccesskey']);
     $router->get('easebuzz-response',['uses'=>'DayBooking\DayBookingPaymentGatewayController@dayBookingEasebuzzAccesskey']);

     $router->get('razorpay-orderid/{invoice_id}',['uses'=>'DayBooking\DayBookingPaymentGatewayController@RazorpayOrderId']);
     $router->post('onpage-razorpay-response',['uses'=>'DayBooking\DayBookingPaymentGatewayController@onpageRazorpayResponse']);

     $router->get('booking-voucher/{invoice_id}',['uses'=>'DayBooking\DayBookingsController@bookingVoucher']);

     $router->get('invoice-details/{invoice_id}', ['uses' => 'DayBooking\DayBookingsController@bookingVoucher']);

     $router->get('fetch-booking-details/{booking_id}', ['uses' => 'DayBooking\DayBookingsController@fetchBookingDetails']);
     $router->get('hotels_by_company/{company_id}',['uses'=>'DayBooking\DayBookingSetupController@dayBookingsHotelListByCompany']);


});

$router->post('day-outing-package-list', ['uses'=>'DayBooking\DayBookingSetupController@dayOutingPackageList']);
$router->post('/booking/day-outing-package-list', ['uses'=>'DayBooking\DayBookingSetupController@dayOutingPackageListCrs']);
$router->post('day-bookings/{api_key}',['uses'=>'DayBooking\DayBookingsController@dayBookings']);
$router->post('day-bookings-test/{api_key}',['uses'=>'DayBooking\DayBookingsController@dayBookingsTest']);

$router->get('day-booking/testing-fun-sar',['uses'=>'DayBooking\DayBookingsController@testingfun']);
// $router->get('day-booking-voucher/{invoice_id}',['uses'=>'BookingEngineController@bookingVoucher']);

$router->post('/day-outing-package-list-test',['uses'=>'TestController@dayOutingPackageListTest']);
$router->get('invoice-details-test/{invoice_id}', ['uses' => 'TestController@bookingVoucherTest']);



