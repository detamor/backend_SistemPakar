<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\DiagnosisController;
use App\Http\Controllers\EducationalModuleController;
use App\Http\Controllers\ExpertConsultationController;
use App\Http\Controllers\Admin\KnowledgeBaseController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\EducationalModuleController as AdminEducationalModuleController;
use App\Http\Controllers\Admin\DashboardStatsController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth Routes (Public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/password/verify', [AuthController::class, 'resetPassword']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public Routes (tanpa auth)
Route::prefix('public')->group(function () {
    Route::get('/plants', [DiagnosisController::class, 'getPlants']);
    Route::get('/symptoms', [DiagnosisController::class, 'getSymptoms']);
    Route::get('/cf-levels', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'getCFLevels']);
    Route::get('/stats/summary', [\App\Http\Controllers\PublicStatsController::class, 'summary']);
    // Endpoint untuk Python engine (tidak perlu auth)
    Route::get('/diseases/plant/{plantId}', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'getDiseasesByPlant']);
});

// Diagnosis Routes
Route::prefix('diagnosis')->middleware('auth:sanctum')->group(function () {
    Route::post('/', [DiagnosisController::class, 'diagnose']);
    Route::get('/history', [DiagnosisController::class, 'getHistory']);
    Route::get('/{id}', [DiagnosisController::class, 'getDetail']);
    Route::put('/{id}/notes', [DiagnosisController::class, 'updateNotes']);
    Route::delete('/{id}/notes', [DiagnosisController::class, 'deleteNotes']);
    Route::get('/{id}/pdf', [DiagnosisController::class, 'downloadPdf']);
});

// Feedback Routes
Route::prefix('feedback')->middleware('auth:sanctum')->group(function () {
    Route::post('/', [\App\Http\Controllers\FeedbackController::class, 'store']);
    Route::get('/diagnosis/{diagnosisId}', [\App\Http\Controllers\FeedbackController::class, 'show']);
});

// Educational Modules Routes
Route::prefix('education')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [EducationalModuleController::class, 'index']);
    Route::get('/{id}', [EducationalModuleController::class, 'show']);
    Route::post('/{id}/bookmark', [EducationalModuleController::class, 'bookmark']);
    Route::delete('/{id}/bookmark', [EducationalModuleController::class, 'unbookmark']);
    Route::get('/bookmarks/my', [EducationalModuleController::class, 'getBookmarks']);
});

// Expert Consultation Routes
Route::prefix('consultation')->middleware('auth:sanctum')->group(function () {
    Route::post('/', [ExpertConsultationController::class, 'create']);
    Route::get('/', [ExpertConsultationController::class, 'index']);
    Route::post('/upload-pdf', [ExpertConsultationController::class, 'uploadPdf']);
    Route::post('/whatsapp', [ExpertConsultationController::class, 'sendWhatsAppWithPdf']);
});

// Profile Routes
Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Http\Controllers\ProfileController::class, 'show']);
    Route::post('/update', [\App\Http\Controllers\ProfileController::class, 'update']);
    Route::post('/change-password', [\App\Http\Controllers\ProfileController::class, 'changePassword']);
    Route::delete('/photo', [\App\Http\Controllers\ProfileController::class, 'removePhoto']);
});

// Admin Routes - Knowledge Base Management
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // User Management (F#11)
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::get('/users/{id}', [UserManagementController::class, 'show']);
    Route::post('/users', [UserManagementController::class, 'store']);
    Route::put('/users/{id}', [UserManagementController::class, 'update']);
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
    
    // Educational Modules Management (F#10)
    Route::get('/education', [AdminEducationalModuleController::class, 'index']);
    Route::get('/education/{id}', [AdminEducationalModuleController::class, 'show']);
    Route::post('/education', [AdminEducationalModuleController::class, 'store']);
    Route::put('/education/{id}', [AdminEducationalModuleController::class, 'update']);
    Route::delete('/education/{id}', [AdminEducationalModuleController::class, 'destroy']);
    Route::post('/education/upload-image', [AdminEducationalModuleController::class, 'uploadContentImage']);
    
    // Diseases Management (F#12)
    Route::get('/diseases', [KnowledgeBaseController::class, 'getDiseases']);
    Route::get('/diseases/plant/{plantId}', [KnowledgeBaseController::class, 'getDiseasesByPlant']);
    Route::get('/diseases/{id}', [KnowledgeBaseController::class, 'show']);
    Route::post('/diseases', [KnowledgeBaseController::class, 'storeDisease']);
    Route::put('/diseases/{id}', [KnowledgeBaseController::class, 'updateDisease']);
    Route::delete('/diseases/{id}', [KnowledgeBaseController::class, 'destroy']);
    
    // Plants Management (F#12)
    Route::get('/plants', [KnowledgeBaseController::class, 'getPlants']);
    Route::get('/plants/{id}', [KnowledgeBaseController::class, 'showPlant']);
    Route::post('/plants', [KnowledgeBaseController::class, 'storePlant']);
    Route::match(['put', 'post'], '/plants/{id}', [KnowledgeBaseController::class, 'updatePlant']); // Support both PUT and POST for file uploads
    Route::delete('/plants/{id}', [KnowledgeBaseController::class, 'destroyPlant']);
    
    // Symptoms Management (F#12)
    Route::get('/symptoms', [KnowledgeBaseController::class, 'getSymptoms']);
    Route::get('/symptoms/{id}', [KnowledgeBaseController::class, 'showSymptom']);
    Route::post('/symptoms', [KnowledgeBaseController::class, 'storeSymptom']);
    Route::put('/symptoms/{id}', [KnowledgeBaseController::class, 'updateSymptom']);
    Route::delete('/symptoms/{id}', [KnowledgeBaseController::class, 'destroySymptom']);
    
    // Certainty Factor Matrix Management
    Route::get('/cf-matrix', [KnowledgeBaseController::class, 'getCFMatrix']);
    Route::post('/cf-matrix/update', [KnowledgeBaseController::class, 'updateCFValue']);
    
    // Certainty Factor Levels Management
    Route::get('/cf-levels', [KnowledgeBaseController::class, 'getAllCFLevels']);
    Route::get('/cf-levels/{id}', [KnowledgeBaseController::class, 'showCFLevel']);
    Route::post('/cf-levels', [KnowledgeBaseController::class, 'storeCFLevel']);
    Route::put('/cf-levels/{id}', [KnowledgeBaseController::class, 'updateCFLevel']);
    Route::delete('/cf-levels/{id}', [KnowledgeBaseController::class, 'destroyCFLevel']);
    
    // Dashboard Stats
    Route::get('/stats/quick', [DashboardStatsController::class, 'getQuickStats']);
    Route::get('/stats/diagnosis', [DashboardStatsController::class, 'getDiagnosisStats']);
});

// WhatsApp API Routes
Route::prefix('whatsapp')->middleware('auth:sanctum')->group(function () {
    Route::post('/send', [WhatsAppController::class, 'sendMessage']);
    Route::post('/send-template', [WhatsAppController::class, 'sendTemplate']);
    Route::get('/status/{messageId}', [WhatsAppController::class, 'getStatus']);
    Route::post('/webhook', [WhatsAppController::class, 'webhook'])->withoutMiddleware('auth:sanctum');
});

