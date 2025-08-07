<?php

use App\Http\Controllers\Api\ChatbotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('chatbot')->controller(ChatbotController::class)->group(function () {
    Route::get('/session-detail'   , 'getSessionDetail');
    Route::post('/start-session'   , 'startSession');
    Route::get('/next-question'    , 'getNextQuestion');
    Route::post('/answer'          , 'submitAnswer');
    Route::get('/session-summary'  , 'getSessionSummary');
    Route::post('/restart-session' , 'restartSession');
});
