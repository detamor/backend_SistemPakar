<?php

namespace App\Http\Controllers;

use App\Models\ExpertConsultation;
use App\Models\Diagnosis;
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

        return response()->json([
            'success' => true,
            'message' => 'Konsultasi berhasil dibuat. Pakar akan menghubungi Anda via WhatsApp.',
            'data' => $consultation->load(['diagnosis'])
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

        // Get diagnosis if provided
        $diagnosis = $consultation->diagnosis_id 
            ? Diagnosis::with(['plant', 'disease'])->find($consultation->diagnosis_id)
            : null;

        // Format message (gunakan message dari consultation atau request)
        $messageText = $request->message ?? $consultation->message;
        $message = $this->formatConsultationMessage($user, $consultation, $diagnosis, $messageText);

        try {
            $pdfSent = false;
            $pdfError = null;
            $pdfPath = null;
            
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
                        
                        // Generate PDF langsung dari diagnosis
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('diagnosis.pdf', [
                            'diagnosis' => $diagnosis,
                            'user' => $user
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
            
            // Kirim PDF sebagai document attachment menggunakan file lokal (CURLFile)
            // Jika ada PDF, kirim langsung dengan file attachment (termasuk message di caption)
            // Jika tidak ada PDF, kirim message biasa
            // Cek apakah ada diagnosis untuk menambahkan info ke pesan
            if ($diagnosis) {
                // Pastikan folder pdfs ada (untuk mendukung pengunduhan manual di frontend)
                if (!Storage::disk('public')->exists('pdfs')) {
                    Storage::disk('public')->makeDirectory('pdfs');
                }
                
                // Jika PDF belum pernah di-generate, kita generate sekarang (agar siap diunduh user)
                if (!$diagnosis->pdf_path || !Storage::disk('public')->exists($diagnosis->pdf_path)) {
                    try {
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('diagnosis.pdf', [
                            'diagnosis' => $diagnosis,
                            'user' => $user
                        ]);
                        $pdfPath = 'pdfs/diagnosis-' . $diagnosis->id . '.pdf';
                        Storage::disk('public')->put($pdfPath, $pdf->output());
                        $diagnosis->update(['pdf_path' => $pdfPath]);
                    } catch (\Exception $pdfError) {
                        Log::warning('Gagal pre-generate PDF: ' . $pdfError->getMessage());
                    }
                }
            }

            // Kirim pesan teks standar ke Pakar
            $whatsappResult = $this->whatsappService->sendMessage(
                $expertWhatsapp,
                $message
            );
            
            $pdfSent = false;
            
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

            $responseMessage = 'Konsultasi berhasil dikirim ke pakar via WhatsApp';
            if ($pdfSent) {
                $responseMessage .= ' beserta PDF laporan diagnosis sebagai attachment';
            } elseif ($pdfPath && $pdfError) {
                $responseMessage .= '. Catatan: PDF gagal dikirim sebagai attachment (' . $pdfError . ').';
            } elseif ($diagnosis && !$pdfPath) {
                $responseMessage .= '. Catatan: PDF gagal di-generate. Silakan coba lagi.';
            }

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
                'data' => $consultation->load(['diagnosis']),
                'pdf_sent' => $pdfSent
            ], 201);

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
        $message .= "📱 *WhatsApp:* {$user->whatsapp_number}\n\n";
        
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
}

