<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Pushok\AuthProvider\Token;
use Pushok\Client;
use Pushok\InvalidPayloadException;
use Pushok\Notification;
use Pushok\Payload;
use Ramsey\Uuid\Uuid;

class ApplePushNotificationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {}

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidPayloadException
     * @throws ValidationException
     * @author Phạm Quang Linh <linhpq@getflycrm.com>
     * @since 13/12/2023 2:27 pm
     */
    public function pushNotification(Request $request): JsonResponse
    {
        $this->validate($request, [
            'access_key' => 'required|string',
            'devices' => 'required|array',
            'devices.*' => 'string',
            'apn_data' => 'required|array',
            'sound' => 'string',
            'push_type' => 'string',
        ]);

        if ($request->get('access_key') !== env('APP_KEY')) {
            return response()->json([
                'message' => 'Key không chính xác'
            ], 401);
        }

        $options = [
            'key_id' => env('APN_KEY_ID'), // The Key ID obtained from Apple developer account
            'team_id' => env('APN_TEAM_ID'), // The Team ID obtained from Apple developer account
            'app_bundle_id' => env('APN_BUNDLE_ID'), // The bundle ID for app obtained from Apple developer account
            'private_key_path' => base_path('private_key.p8'), // Path to private key
            'private_key_secret' => null // Private key secret
        ];

        $authProvider = Token::create($options);

        $payload = Payload::create();

        $payload->setSound($request->get('sound') ?? 'default');
        $payload->setPushType($request->get('push_type') ?? 'voip');
        $payload->setContentAvailability(1);

        $payload->setCustomValue('uuid', Uuid::uuid4());

        $apnsData = $request->get('apn_data');

        foreach ($apnsData as $key => $apn_value) {
            $payload->setCustomValue($key, $apn_value);
        }

        $notifications = [];

        foreach ($request->get('devices') as $deviceToken) {
            $notifications[] = new Notification($payload, $deviceToken);
        }

        $client = new Client($authProvider, $production = true, [CURLOPT_SSL_VERIFYPEER => false]);

        $client->addNotifications($notifications);

        $responses = $client->push();

        $responseData = [];
        foreach ($responses as $response) {

            $response->getDeviceToken();

            $response->getApnsId();


            $response->getStatusCode();

            $response->getReasonPhrase();

            $response->getErrorReason();

            $response->getErrorDescription();
            $response->get410Timestamp();

            $responseData[] = [
                'device_token' => $response->getDeviceToken(),
                'apns_id' => $response->getApnsId(),
                'status_code' => $response->getStatusCode(),
                'reason' => $response->getReasonPhrase(),
                'error_reason' => $response->getErrorReason(),
                'error_description' => $response->getErrorDescription(),
            ];
        }

        return response()->json($responseData);
    }
}
