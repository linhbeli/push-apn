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
    public function __construct()
    {}

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

        // Kiểm tra access_key nếu cần
        // if ($request->get('access_key') !== env('APP_KEY')) {
        //     return response()->json(['message' => 'Key không chính xác'], 401);
        // }

        $options = [
            'key_id' => env('APN_KEY_ID'),
            'team_id' => env('APN_TEAM_ID'),
            'app_bundle_id' => env('APN_BUNDLE_ID'),
            'private_key_path' => base_path('private_key.p8'),
            'private_key_secret' => null
        ];

        $authProvider = Token::create($options);

        $payload = Payload::create()
            ->setSound($request->get('sound', 'default'))
            ->setContentAvailability(1)
            ->setCustomValue('uuid', Uuid::uuid4());

        foreach ($request->get('apn_data') as $key => $value) {
            $payload->setCustomValue($key, $value);
        }

        $notifications = [];

        foreach ($request->get('devices') as $deviceToken) {
            $notification = new Notification($payload, $deviceToken);

            // Thiết lập header thủ công (thay vì gọi setPushType)
            $notification->setCustomClientOptions([
                'headers' => [
                    'apns-push-type' => $request->get('push_type', 'voip'),
                    'apns-topic' => env('APN_BUNDLE_ID', 'com.getflycrm.voip'),
                ]
            ]);

            $notifications[] = $notification;
        }

        $client = new Client($authProvider, $production = true, [
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $client->addNotifications($notifications);
        $responses = $client->push();

        $responseData = [];
        foreach ($responses as $response) {
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
