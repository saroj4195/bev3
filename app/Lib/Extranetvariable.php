<?php

	$uri = explode('/',$_SERVER['REQUEST_URI']);//Request URL
	
	//Define Global Variable for Tables
	define('be_url', 'https://be.bookingjini.com/extranetv4');
	define('gems_url', 'https://gems.bookingjini.com/api/');
	define('CRM_URL', 'https://crm.bookingjini.com/');
	define('KERNEL_URL', 'https://kernel.bookingjini.com/extranetv4/');
	define('CRM_INSERT_FROM_DIFFERENT_SOURCE_URL', CRM_URL.'InsertFromDifferentSource');
	define('get_inventory_be', be_url.'/be_getInventory/');
	define('get_booking_be', be_url.'/be_getBooking/');
	define('GET_SUBSCRIPTION_DETAILS_URL', KERNEL_URL.'subscription-details/');

	//Default values 
	define('not_available', '0');


	//For Whatsapp
	//{{Version}}/{{Phone-Number-ID}}/messages

	define('WHATSAPP_API_VERSION', 'v14.0');
	define('WHATSAPP_PHONENUMBER_ID', '105052612466981');
	define('WHATSAPP_AUTHORIZATION', 'EAARmrL8ZAn2kBAO8PIwzHJdIrAEKkLTEhyC2JioF4ObpAxBZBRo3JWAmV3ZBzZBUcsEpYQwYMjlIuzp2cYNoJBh68uJmxZCmWmxb7O8vChAjrX1FrznZCoX4QoJqcUWHut45XCbX6XExWyrUBXfBQDrHGCGJuw59ZC3UaucRQT8apHcSZC7VuHXaUFPKdnquoaJU4ZBZCOFrg89wZDZD');
	define('WHATSAPP_API_URL', 'https://graph.facebook.com/'.WHATSAPP_API_VERSION.'/'.WHATSAPP_PHONENUMBER_ID);
	define('WHATSAPP_API_MESSAGE_URL', WHATSAPP_API_URL.'/messages');
	
	
	?>