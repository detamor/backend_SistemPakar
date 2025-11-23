<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppController extends Controller
{
    /**
     * Konfigurasi Fonte API
     */
    private function getFonteConfig()
    {
        return [
            'api_key' => env('FONTE_API_KEY'),
            'base_url' => env('FONTE_BASE_URL', 'https://api.fonnte.com'),
        ];
    }

    /**
     * Mengirim pesan WhatsApp
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'message' => 'required|string|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $config = $this->getFonteConfig();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ])->post($config['base_url'] . '/messages', [
                'to' => $request->phone_number,
                'message' => $request->message,
                'type' => 'text'
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'phone_number' => $request->phone_number,
                    'response' => $response->json()
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 200);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to send message',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp message', [
                'error' => $e->getMessage(),
                'phone_number' => $request->phone_number
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengirim pesan template WhatsApp
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'template_name' => 'required|string',
            'parameters' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $config = $this->getFonteConfig();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ])->post($config['base_url'] . '/messages/template', [
                'to' => $request->phone_number,
                'template' => $request->template_name,
                'parameters' => $request->parameters
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp template message sent successfully', [
                    'phone_number' => $request->phone_number,
                    'template' => $request->template_name
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 200);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to send template message',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp template message', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengecek status pesan
     *
     * @param string $messageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus($messageId)
    {
        $config = $this->getFonteConfig();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
            ])->get($config['base_url'] . '/messages/' . $messageId);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 200);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to get message status',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error getting message status', [
                'error' => $e->getMessage(),
                'message_id' => $messageId
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook untuk menerima callback dari Fonte
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        Log::info('WhatsApp webhook received', [
            'data' => $request->all()
        ]);

        // TODO: Implementasi logika untuk handle webhook
        // Contoh: update status pesan, simpan ke database, dll

        return response()->json([
            'success' => true,
            'message' => 'Webhook received'
        ], 200);
    }
}



