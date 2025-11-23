<?php

namespace App\Http\Controllers;

use App\Models\ExpertConsultation;
use App\Models\Diagnosis;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpertConsultationController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Membuat konsultasi baru dengan pakar
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diagnosis_id' => 'nullable|exists:diagnoses,id',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        // Ambil nomor WhatsApp expert dari config (bisa dari .env atau database)
        $expertWhatsapp = env('EXPERT_WHATSAPP_NUMBER', null);

        $consultation = ExpertConsultation::create([
            'user_id' => $user->id,
            'diagnosis_id' => $request->diagnosis_id,
            'expert_whatsapp' => $expertWhatsapp,
            'message' => $request->message,
            'status' => 'pending',
        ]);

        // Kirim notifikasi ke expert via WhatsApp
        if ($expertWhatsapp) {
            $diagnosis = $request->diagnosis_id 
                ? Diagnosis::with(['plant', 'disease'])->find($request->diagnosis_id)
                : null;

            $message = $this->formatConsultationMessage($user, $consultation, $diagnosis);
            
            $whatsappResult = $this->whatsappService->sendMessage(
                $expertWhatsapp,
                $message
            );

            if ($whatsappResult['success']) {
                $consultation->update([
                    'whatsapp_message_id' => $whatsappResult['data']['message_id'] ?? null
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Konsultasi berhasil dibuat. Pakar akan menghubungi Anda via WhatsApp.',
            'data' => $consultation->load(['diagnosis'])
        ], 201);
    }

    /**
     * Mendapatkan daftar konsultasi user
     */
    public function index()
    {
        $user = auth()->user();
        
        $consultations = ExpertConsultation::where('user_id', $user->id)
            ->with(['diagnosis.plant', 'diagnosis.disease'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $consultations
        ]);
    }

    /**
     * Format pesan konsultasi untuk WhatsApp
     */
    private function formatConsultationMessage($user, $consultation, $diagnosis = null)
    {
        $message = "🔔 *Konsultasi Baru dari System Pakar*\n\n";
        $message .= "👤 *Pengguna:* {$user->name}\n";
        $message .= "📱 *WhatsApp:* {$user->whatsapp_number}\n\n";
        
        if ($diagnosis) {
            $message .= "🌱 *Tanaman:* {$diagnosis->plant->name}\n";
            if ($diagnosis->disease) {
                $message .= "🦠 *Diagnosis:* {$diagnosis->disease->name}\n";
                $message .= "📊 *CF Value:* " . ($diagnosis->certainty_value * 100) . "%\n";
            }
            $message .= "\n";
        }
        
        $message .= "💬 *Pesan Konsultasi:*\n{$consultation->message}\n\n";
        $message .= "Silakan balas konsultasi ini via WhatsApp atau melalui sistem.";

        return $message;
    }
}

