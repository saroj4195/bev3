<?php

	$uri = explode('/',$_SERVER['REQUEST_URI']);//Request URL
	
	//Common Error Messages
	define('INTERNAL_SERVER_ERR_MESSAGE', 'Internal server error !!');
	
	//Messaes For Paid Services
	define('NO_PAID_SERVICE_MESSAGE', "You don't have any paid services yet");
	define('RETRIEVED_PAID_SERVICE_MESSAGE', "Hotel's paid service retrieved successfully !!");
	define('FAILED_PAID_SERVICE_MESSAGE', 'Failed');
	define('SAVED_PAID_SERVICE_MESSAGE', 'Saved');
	define('UPDATED_PAID_SERVICE_MESSAGE', 'Updated');
	define('ALREADY_EXIST_PAID_SERVICE_MESSAGE', 'This paid service already registered !!');
	define('DELETED_PAID_SERVICE_MESSAGE', 'Deleted');

	//Messaes For Coupons
	define('NO_COUPONS_MESSAGE', "You don’t have any coupon yet");
	define('RETRIEVED_COUPONS_MESSAGE', "Coupons details retrieved successfully !!");
	define('RETRIEVE_COUPONS_TYPE_MESSAGE', "Coupon type fetched Successfully !!");
	define('FAILED_RETRIEVE_COUPONS_TYPE_MESSAGE', "Coupon type fetched Failed !!");
	define('FAILED_FETCHING_COUPONS_MESSAGE', "Coupons fetching failed !!");
	define('FAILED_COUPONS_MESSAGE', 'Failed');
	define('SAVED_COUPONS_MESSAGE', 'Saved');
	define('PROVIDE_HOTELINFO_COUPONS_MESSAGE', 'Please provide hotel info !!');
	define('FAILED_UPDATE_COUPONS_MESSAGE', 'Failed');
	define('UPDATE_COUPONS_MESSAGE', 'Updated');
	define('DELETED_COUPONS_MESSAGE', 'Deleted');
	define('FAILED_DELETE_COUPONS_MESSAGE', 'Failed');
	
	//Messaes For PaymentGateway
	define('NO_PAYMENTGATEWAY_MESSAGE', 'No Payment Gateway Selected');
	define('ADD_PAYMENTGATEWAY_MESSAGE', 'Added');
	define('UPDATE_PAYMENTGATEWAY_MESSAGE', 'Updated');
	define('FAILED_PAYMENTGATEWAY_MESSAGE', 'Failed');

	//Messaes For Theme SetUp
	define('UPDATE_THEMESETUP_MESSAGE', 'Theme Updated');
	define('FAILED_THEMESETUP_MESSAGE', 'Failed');

	//Messaes For URL Details
	define('UPDATE_URL_MESSAGE', 'Url Updated');
	define('FAILED_URL_MESSAGE', 'Failed');

	//Messaes For Other Setting Details
	define('UPDATE_OTHER_SETTING_MESSAGE', 'Updated');
	define('FAILED_OTHER_SETTING_MESSAGE', 'Failed');

	//Messaes For Notifications
	define('NO_NOTIFICATIONS_MESSAGE', "You don’t have any notifications yet");

	//Messaes For BE Cancellation Policy
	define('UPDATE_CANCELLATION_POLICY_MESSAGE', "Saved");
	define('FAIL_CANCELLATION_POLICY_MESSAGE', "Failed");

	//Messaes For CRS Bookings
	define('CRS_BOOKING_SUCCESS_MESSAGE', "Booked Successfully");

	?>