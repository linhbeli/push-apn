<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Pushok\AuthProvider\Token;
use Pushok\Client;
use Pushok\InvalidPayloadException;
use Pushok\Notification;
use Pushok\Payload;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Log;

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
            'sound' => 'sometimes|string',
            'push_type' => 'sometimes|string', // alert, voip, background
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

        $pushType = $request->get('push_type', 'alert');

        // 🔄 Chọn đúng topic theo push_type
        $apnsTopic = $pushType === 'voip'
            ? env('APN_VOIP_TOPIC', env('APN_BUNDLE_ID'))
            : env('APN_BUNDLE_ID');

        $notifications = [];

        foreach ($request->get('devices') as $deviceToken) {
            Log::info('Preparing push notification', [
                'device_token' => $deviceToken,
                'push_type' => $pushType,
                'apns_topic' => $apnsTopic,
                'payload' => json_encode($payload, JSON_PRETTY_PRINT)
            ]);

            $notification = new Notification(
                $payload,
                $deviceToken,
                [
                    'apns-push-type' => $pushType,
                    'apns-topic' => $apnsTopic
                ]
            );

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
