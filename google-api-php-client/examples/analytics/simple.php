<?php

require_once '../../src/Google_Client.php';
require_once '../../src/contrib/Google_AnalyticsService.php';

session_start();

$client = new Google_Client();

$client->setApplicationName("WP Top Posts by GA");

// Visit https://code.google.com/apis/console?api=analytics to generate your
// client id, client secret, and to register your redirect uri.
$client->setClientId('6382807459.apps.googleusercontent.com');
$client->setClientSecret('4spDtOoNgvqCJKbO1xOOIx5x');
$client->setRedirectUri('http://matth.eu/');
$client->setDeveloperKey('AIzaSyAti-s1WQIiNdxssT33k3zBjdD4RqHvd1Y');

$service = new Google_AnalyticsService($client);

if ( isset($_GET['logout'] ) ) {
  unset($_SESSION['token']);
}

if (isset($_GET['code'])) {
  $client->authenticate();
  $_SESSION['token'] = $client->getAccessToken();
  $redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
  header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
}

if (isset($_SESSION['token'])) {
  $client->setAccessToken($_SESSION['token']);
}

if ($client->getAccessToken()) {
  $props = $service->management_webproperties->listManagementWebproperties("~all");
  print "<h1>Web Properties</h1><pre>" . print_r($props, true) . "</pre>";

  $accounts = $service->management_accounts->listManagementAccounts();
  print "<h1>Accounts</h1><pre>" . print_r($accounts, true) . "</pre>";

  $segments = $service->management_segments->listManagementSegments();
  print "<h1>Segments</h1><pre>" . print_r($segments, true) . "</pre>";

  $goals = $service->management_goals->listManagementGoals("~all", "~all", "~all");
  print "<h1>Segments</h1><pre>" . print_r($goals, true) . "</pre>";

  $_SESSION['token'] = $client->getAccessToken();
} else {
  $authUrl = $client->createAuthUrl();
  print "<a class='login' href='$authUrl'>Connect Me!</a>";
}