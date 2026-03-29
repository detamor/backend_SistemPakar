<?php

namespace App\Http\Controllers;

use App\Models\ExpertConsultation;
use App\Models\Diagnosis;
use App\Models\Disease;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Ambil nomor WhatsApp expert dari config (bisa dari .env atau database)
        $expertWhatsapp = env('EXPERT_WHATSAPP_NUMBER', null);

        $consultation = ExpertConsultation::create([
            'user_id' => $user->id,
            'diagnosis_id' => $request->diagnosis_id,
            'expert_whatsapp' => $expertWhatsapp,
            'message' => $request->message,
            'status' => 'pending',
        ]);

        // Pengiriman WhatsApp dihapus dari sini agar tidak dobel.
        // Pengiriman pesan hanya dilakukan melalui method sendWhatsAppWithPdf
        // yang dipanggil oleh frontend setelah proses create.

        $whatsappUrl = null;
        if ($expertWhatsapp) {
            $whatsappUrl = $this->buildWhatsAppDeepLink($expertWhatsapp, $consultation->message);
        }

        return response()->json([
            'success' => true,
            'message' => 'Konsultasi berhasil dibuat. Pakar akan menghubungi Anda via WhatsApp.',
            'data' => $consultation->load(['diagnosis']),
            'whatsapp_url' => $whatsappUrl,
        ], 201);
    }

    /**
     * Mendapatkan daftar konsultasi user
     */
    public function index(Request $request)
    {
        try {
        /** @var \App\Models\User $user */
        $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }
        
        $consultations = ExpertConsultation::where('user_id', $user->id)
            ->with(['diagnosis.plant', 'diagnosis.disease'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $consultations
        ]);
        } catch (\Exception $e) {
            Log::error('Error getting consultations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data konsultasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload PDF manual untuk konsultasi
     */
    public function uploadPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'consultation_id' => 'required|exists:expert_consultations,id',
            'pdf_file' => 'required|file|mimes:pdf|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        
        $consultation = ExpertConsultation::where('user_id', $user->id)
            ->findOrFail($request->consultation_id);

        try {
            // Upload PDF file
            $file = $request->file('pdf_file');
            $filename = 'consultation-' . $consultation->id . '-' . time() . '.pdf';
            $pdfPath = 'consultations/' . $filename;
            
            // Store file
            Storage::disk('public')->put($pdfPath, file_get_contents($file));
            
            // Update consultation dengan PDF path
            $consultation->update([
                'pdf_path' => $pdfPath
            ]);

            Log::info('PDF uploaded for consultation', [
                'consultation_id' => $consultation->id,
                'pdf_path' => $pdfPath,
                'file_size' => $file->getSize()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PDF berhasil diupload',
                'data' => [
                    'pdf_path' => $pdfPath,
                    'pdf_url' => Storage::disk('public')->url($pdfPath)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading PDF', [
                'error' => $e->getMessage(),
                'consultation_id' => $request->consultation_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengirim konsultasi via WhatsApp dengan PDF (manual upload)
     */
    public function sendWhatsAppWithPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'consultation_id' => 'required|exists:expert_consultations,id',
            'message' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $expertWhatsapp = env('EXPERT_WHATSAPP_NUMBER', null);

        if (!$expertWhatsapp) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp pakar belum dikonfigurasi'
            ], 400);
        }

        // Get existing consultation
        $consultation = ExpertConsultation::where('user_id', $user->id)
            ->findOrFail($request->consultation_id);

        $diagnosis = $consultation->diagnosis_id
            ? Diagnosis::with(['plant', 'disease', 'symptoms'])->find($consultation->diagnosis_id)
            : null;

        // Format message (gunakan message dari consultation atau request)
        $messageText = $request->message ?? $consultation->message;
        $message = $this->formatConsultationMessage($user, $consultation, $diagnosis, $messageText);

        try {
            $pdfSent = false;
            $pdfError = null;
            $pdfPath = null;
            $pdfUrl = null;
            
            // Prioritas 1: Cek PDF manual yang sudah diupload (dari consultation.pdf_path)
            if ($consultation->pdf_path && Storage::disk('public')->exists($consultation->pdf_path)) {
                $pdfPath = $consultation->pdf_path;
                Log::info('Using manually uploaded PDF', [
                    'consultation_id' => $consultation->id,
                    'pdf_path' => $pdfPath,
                    'file_exists' => Storage::disk('public')->exists($pdfPath),
                    'file_size' => Storage::disk('public')->size($pdfPath)
                ]);
            }
            // Prioritas 2: Generate PDF dari diagnosis jika ada
            else if ($diagnosis) {
                try {
                    // Cek apakah PDF sudah ada di database
                    $existingPdfPath = $diagnosis->pdf_path;
                    $filename = null;
                    
                    if ($existingPdfPath && Storage::disk('public')->exists($existingPdfPath)) {
                        // PDF sudah ada, gunakan yang sudah ada
                        $pdfPath = $existingPdfPath;
                        $filename = basename($pdfPath);
                        
                        Log::info('Using existing PDF from database', [
                            'pdf_path' => $pdfPath,
                            'diagnosis_id' => $diagnosis->id,
                            'consultation_id' => $consultation->id
                        ]);
                    } else {
                        // PDF belum ada, generate baru
                        Log::info('Generating new PDF for diagnosis', [
                            'diagnosis_id' => $diagnosis->id,
                            'consultation_id' => $consultation->id
                        ]);
                        
                        $tiedTopDiseases = $this->buildTiedTopDiseasesFromPossibilities(
                            is_array($diagnosis->all_possibilities_json ?? null) ? $diagnosis->all_possibilities_json : [],
                            (int) $diagnosis->plant_id
                        );

                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('diagnosis.pdf', [
                            'diagnosis' => $diagnosis,
                            'user' => $user,
                            'tiedTopDiseases' => $tiedTopDiseases,
                        ]);
                        
                        // Simpan PDF ke storage/public agar bisa diakses via URL
                        // Format: pdfs/diagnosis-{id}.pdf (satu PDF per diagnosis)
                        $filename = 'diagnosis-' . $diagnosis->id . '.pdf';
                        $pdfPath = 'pdfs/' . $filename;
                        $pdfContent = $pdf->output();
                        
                        // Simpan ke storage/public/pdfs
                        Storage::disk('public')->put($pdfPath, $pdfContent);
                        
                        // Simpan path PDF ke database
                        $diagnosis->update([
                            'pdf_path' => $pdfPath
                        ]);
                        
                        Log::info('PDF generated and saved to database', [
                            'filename' => $filename,
                            'pdf_path' => $pdfPath,
                            'diagnosis_id' => $diagnosis->id,
                            'consultation_id' => $consultation->id
                        ]);
                    }
                    
                    // Verify file exists
                    $fileExists = Storage::disk('public')->exists($pdfPath);
                    
                    if (!$fileExists) {
                        throw new \Exception('PDF file tidak berhasil disimpan ke storage');
                    }
                    
                } catch (\Exception $e) {
                    $pdfError = $e->getMessage();
                    Log::error('Error generating PDF', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'consultation_id' => $consultation->id,
                        'diagnosis_id' => $diagnosis->id
                    ]);
                }
            }

            if ($pdfPath) {
                $pdfUrl = $this->buildPublicFileUrl($request, Storage::url($pdfPath));
            }

            // Kirim pesan teks standar ke Pakar
            $whatsappResult = $this->whatsappService->sendMessage(
                $expertWhatsapp,
                $message
            );
            
            if ($pdfPath) {
                if ($pdfUrl) {
                    $documentResult = $this->whatsappService->sendDocumentByUrl(
                        $expertWhatsapp,
                        $pdfUrl,
                        basename($pdfPath),
                        '📎 Laporan diagnosis terlampir'
                    );
                    $pdfSent = (bool) ($documentResult['success'] ?? false);
                    if (! $pdfSent) {
                        $pdfError = $pdfError ?: ($documentResult['error'] ?? 'Gagal mengirim PDF');
                    }
                } else {
                    $documentResult = $this->whatsappService->sendDocumentByFile(
                        $expertWhatsapp,
                        $pdfPath,
                        basename($pdfPath),
                        '📎 Laporan diagnosis terlampir'
                    );
                    $pdfSent = (bool) ($documentResult['success'] ?? false);
                    if (! $pdfSent) {
                        $pdfError = $pdfError ?: ($documentResult['error'] ?? 'Gagal mengirim PDF');
                    }
                }
            }
            
            // Update consultation dengan PDF path dan WhatsApp message ID
            $updateData = [
                'whatsapp_message_id' => $whatsappResult['success'] ? ($whatsappResult['data']['message_id'] ?? null) : null
            ];
            
            // Simpan PDF path ke consultation jika PDF berhasil dibuat
            if ($pdfPath) {
                $updateData['pdf_path'] = $pdfPath;
                Log::info('Saving PDF path to consultation', [
                    'consultation_id' => $consultation->id,
                    'pdf_path' => $pdfPath
                ]);
            }
            
            $consultation->update($updateData);

            $whatsappUrl = $this->buildWhatsAppDeepLink(
                $expertWhatsapp,
                $pdfUrl ? ($message . "\n\nLaporan PDF: " . $pdfUrl) : $message
            );

            return response()->json([
                'success' => true,
                'message' => 'Siap untuk menghubungi pakar via WhatsApp.',
                'data' => $consultation->load(['diagnosis']),
                'whatsapp_url' => $whatsappUrl,
                'pdf_url' => $pdfUrl,
                'send_status' => [
                    'message_sent' => (bool) ($whatsappResult['success'] ?? false),
                    'pdf_sent' => (bool) $pdfSent,
                    'pdf_error' => $pdfError,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp consultation', [
                'error' => $e->getMessage(),
                'consultation_id' => $consultation->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim via WhatsApp. Silakan coba lagi atau hubungi pakar langsung.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format pesan konsultasi untuk WhatsApp
     */
    private function formatConsultationMessage($user, $consultation, $diagnosis = null, $messageText = null)
    {
        $message = "🔔 *Konsultasi Baru dari System Pakar*\n\n";
        $message .= "👤 *Pengguna:* {$user->name}\n";
        $message .= "📧 *Email:* {$user->email}\n\n";
        
        if ($diagnosis) {
            $message .= "🌱 *Tanaman:* {$diagnosis->plant->name}\n";
            if ($diagnosis->disease) {
                $message .= "🦠 *Diagnosis:* {$diagnosis->disease->name}\n";
                $message .= "📊 *CF Value:* " . ($diagnosis->certainty_value * 100) . "%\n";
            }
            $message .= "\n";
        }
        
        $consultationMessage = $messageText ?? $consultation->message;
        $message .= "💬 *Pesan Konsultasi:*\n{$consultationMessage}\n\n";
        
        if ($diagnosis) {
            $message .= "*(Pesan: Saya akan melampirkan file Laporan PDF setelah pesan ini)*\n\n";
        }
        
        $message .= "Silakan balas konsultasi ini via WhatsApp atau melalui sistem.";

        return $message;
    }

    private function buildPublicFileUrl(Request $request, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            if ($parsedPath && str_contains($parsedPath, '/storage/')) {
                return rtrim($request->getSchemeAndHttpHost(), '/') . $parsedPath;
            }
            return $path;
        }

        $cleanPath = ltrim($path, '/');
        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . $cleanPath;
    }

    private function buildWhatsAppDeepLink(string $phoneNumber, string $message): string
    {
        $digitsOnly = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (str_starts_with($digitsOnly, '0')) {
            $digitsOnly = '62' . substr($digitsOnly, 1);
        } elseif (!str_starts_with($digitsOnly, '62')) {
            $digitsOnly = '62' . $digitsOnly;
        }
        return 'https://wa.me/' . $digitsOnly . '?text=' . rawurlencode($message);
    }

    private function buildTiedTopDiseasesFromPossibilities(?array $possibilities, int $plantId): array
    {
        if (empty($possibilities)) {
            return [];
        }

        $maxCf = -1.0;
        foreach ($possibilities as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cf = (float) ($row['certainty_value'] ?? 0);
            if ($cf > $maxCf) {
                $maxCf = $cf;
            }
        }

        if ($maxCf < 0) {
            return [];
        }

        $eps = 1e-4;
        $orderedIds = [];
        $rowById = [];
        foreach ($possibilities as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cf = (float) ($row['certainty_value'] ?? 0);
            if (abs($cf - $maxCf) > $eps) {
                continue;
            }
            $did = $row['disease_id'] ?? null;
            if ($did === null) {
                continue;
            }
            $did = (int) $did;
            if (isset($rowById[$did])) {
                continue;
            }
            $rowById[$did] = $row;
            $orderedIds[] = $did;
        }

        if ($orderedIds === []) {
            return [];
        }

        $models = Disease::whereIn('id', $orderedIds)
            ->where('plant_id', $plantId)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($orderedIds as $did) {
            if (! $models->has($did)) {
                continue;
            }
            $dis = $models->get($did);
            $row = $rowById[$did];
            $out[] = [
                'id' => $dis->id,
                'name' => $dis->name,
                'code' => $dis->code,
                'description' => $dis->description,
                'cause' => $dis->cause,
                'solution' => $dis->solution ?: (string) ($row['solution'] ?? ''),
                'prevention' => $dis->prevention ?: (string) ($row['prevention'] ?? ''),
                'certainty_value' => (float) ($row['certainty_value'] ?? 0),
                'matched_symptoms_count' => (int) ($row['matched_count'] ?? 0),
            ];
        }

        return $out;
    }
}
