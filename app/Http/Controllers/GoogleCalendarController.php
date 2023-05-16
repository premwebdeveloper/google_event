<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Auth_Exception;

class GoogleCalendarController extends Controller
{
    public function oauthCallback(Request $request)
    {
        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT'));
        $client->addScope(Google_Service_Calendar::CALENDAR);

        if ($request->has('code')) {
            // If the user has authorized the app, exchange the authorization code for an access token and refresh token
            $client->authenticate($request->code);
            $access_token = $client->getAccessToken()['access_token'];
            $refresh_token = $client->getRefreshToken();
            session(['google_access_token' => $access_token, 'google_refresh_token' => $refresh_token]);

            echo 'YES';
            echo '<pre>';
            print_r($client);
            exit;

            return redirect()->to('google/calendar/events');
        } else {
            // If the user has not authorized the app, redirect them to the Google OAuth page
            $auth_url = $client->createAuthUrl();
            return redirect()->to($auth_url);
        }
    }

    public function getEvents()
    {
        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT'));
        $client->addScope(Google_Service_Calendar::CALENDAR);

        // If there is a refresh token in the user session, use it to obtain a new access token
        if (session('google_refresh_token')) {
            $client->setAccessType('offline');
            $client->setAccessToken([
                'access_token' => session('google_access_token'),
                'refresh_token' => session('google_refresh_token'),
                'expires_in' => 3600
            ]);
            if ($client->isAccessTokenExpired()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                session(['google_access_token' => $client->getAccessToken()['access_token']]);
            }
        } else {
            // If there is no refresh token, redirect the user to the Google OAuth page
            $auth_url = $client->createAuthUrl();
            return redirect()->to($auth_url);
        }

        try {
            $calendar = new Google_Service_Calendar($client);
            $events = $calendar->events->listEvents('primary');
            $items = $events->getItems();

            return view('google-calendar', ['events' => $items]);
        } catch (Google_Auth_Exception $e) {
            // Handle authentication errors here
        }
    }
}

