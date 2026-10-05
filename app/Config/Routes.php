<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (file_exists(SYSTEMPATH . 'Config/Routes.php')) {
	require SYSTEMPATH . 'Config/Routes.php';
}

/**
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('dbg', 'Auth::index');
$routes->get('logout', 'Auth::logout');
$routes->get('dashboard', 'User::index');

$routes->match(['get','post'], '/',        'Auth::login');
$routes->match(['get','post'], 'login',    'Auth::login');
$routes->match(['get','post'], 'register', 'Auth::register');

// NEW: Forgot Password Routes
$routes->match(['get','post'], 'forgot-password', 'Auth::forgot_password');
$routes->match(['get','post'], 'reset-password', 'Auth::reset_password');

// Add Balance page (GET shows form, POST processes it)
$routes->match(['get','post'], 'balance',  'User::balance');
$routes->match(['get','post'], 'license', 'User::license');
$routes->match(['get','post'], 'price', 'Price::index');

//
$routes->match(['get', 'post'], 'settings', 'User::settings');
$routes->match(['get', 'post'], 'user/(:num)', 'User::user_edit/$1');
$routes->post('settingsx/updateSiteName', 'User::updateSiteName');
$routes->post('settings/updateSiteName', 'User::updateSiteName');
$routes->match(['get', 'post'], 'lib', 'User::lib');
$routes->match(['get', 'post'], 'ExtendDuration', 'User::ExtendDuration');
$routes->match(['get', 'post'], 'settingsx', 'User::settingsx');
$routes->match(['get', 'post'], 'Server', 'User::Server');
$routes->post('settings/update-username', 'User::updateUsername');
$routes->match(['get','post'], 'keys/generate-random', 'Keys::generate_random');
$routes->match(['get','post'], 'device-reset', 'Auth::devicereset');

// ✅ New page for pending approvals
$routes->get('account_approve', 'User::account_approve');

// (existing
$routes->get('user/approve/(:num)', 'User::approve/$1');
$routes->get('user/decline/(:num)', 'User::decline/$1');
//
//testing
$routes->match(['get', 'post'], 'New', 'Home::index');
//$routes->get('server', 'User::server');
//

$routes->group('keys', function ($routes) {
	$routes->match(['get', 'post'], '/', 'Keys::index');
	$routes->match(['get', 'post'], 'generate', 'Keys::generate');
	$routes->match(['get', 'post'], 'deleteUnused', 'Keys::deleteUnused');
	$routes->get('(:num)', 'Keys::edit_key/$1');
	$routes->get('reset', 'Keys::api_key_reset');
	$routes->post('edit', 'Keys::edit_key');
	$routes->match(['get', 'post'], 'api', 'Keys::api_get_keys');
	$routes->match(['get'],'deleteExp','Keys::deleteExpired');
	$routes->match(['get'],'DelblockKeys','Keys::deleteBLOCKKeys');
	$routes->match(['get'],'resetAll','Keys::resetAllKeys');
	$routes->match(['get'],'resetAl','Keys::resetAlldevice'); 
	$routes->get('delete/all', 'Keys::delete_all_keys');
});

$routes->group('admin', ['filter' => 'admin'], function ($routes) {
	$routes->match(['get', 'post'], 'create-referral', 'User::ref_index');
	$routes->match(['get', 'post'], 'manage-users', 'User::manage_users');
	$routes->match(['get', 'post'], 'user/(:num)', 'User::user_edit/$1');
	/* --------------------------- Admin API Grouping -------------------------- */
	$routes->group('api', function ($routes) {
		$routes->match(['get', 'post'], 'users', 'User::api_get_users');
	});
});

$routes->match(['get', 'post'], 'connect', 'Connect::index');

/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
	require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}

$routes->get('u/(:num)', 'User::user/$1');
$routes->post('d/(:num)', 'User::user_delete/$1');

$routes->post('user/delete/(:num)', 'User::user_delete/$1');
$routes->delete('user/delete/(:num)', 'User::user_delete/$1');
$routes->post('user/update-telegram-chat-id', 'User::updateTelegramChatId');
$routes->post('user/toggle-twofa', 'User::toggleTwoFA');
$routes->match(['get','post'], 'verify-otp', 'Auth::verify_otp');
$routes->get('twofa', 'User::twofa'); // 2FA page

$routes->group('price', function ($routes) {
    $routes->get('/', 'Price::index');
    // Mutating routes require admin level 1/2 (same `admin` filter as the
    // /admin group below) - a reseller must not be able to rewrite pricing.
    $routes->post('add', 'Price::addDuration', ['filter' => 'admin']);
    $routes->post('update', 'Price::updatePrice', ['filter' => 'admin']);
    $routes->post('delete/(:num)', 'Price::delete/$1', ['filter' => 'admin']);
});