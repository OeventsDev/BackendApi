<?php

use App\Http\Controllers\Api\V1\LogActivityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\Api\V1\AuthentificationController;
use \App\Http\Controllers\Api\V1\PaysController;
use \App\Http\Controllers\Api\V1\VilleController;
use \App\Http\Controllers\Api\V1\RegionController;
use \App\Http\Controllers\Api\V1\QuartierController;
use Illuminate\Support\Facades\Hash;

Route::controller(AuthentificationController::class)->group(function () {
    Route::post('register', 'register');
    Route::get('email/verify/{id}', 'verify')->name('verification.verify'); // Make sure to keep this as your route name
    Route::post('code/verify/{id}', 'confirmeTelephone')->name('verification.confirmeTelephone'); // Make sure to keep this as your route name
    Route::post('code/otp/verify', 'confirmeTelephonePassRenitialise')->name('verification.confirmeTelephonePassRenitialise'); // Make sure to keep this as your route name

    Route::post('login', 'login');
    Route::middleware('auth:sanctum')->prefix("user")->group(function () {
        Route::get('email/resend', 'resend')->name('verification.resend');
        Route::get('code/resend', 'resendOTP')->name('verification.resendOTP');
        Route::get('info', 'userInfo');
        Route::post('edit', 'EditUser');
        Route::post('logout', 'logout');
        Route::post('remove', 'remove');
        Route::get('activation/{id}/{status}', 'ActiveDesactiveUser');

    });
    //Route::get('auth/google', 'redirectToGoogle')->name('auth.google');
    //Route::get('auth/google/callback', 'handleGoogleCallback');
    // Route::get('auth/facebook', 'facebookRedirect');
    //Route::get('auth/facebook/callback', 'loginWithFacebook');

    /*Route::post('/otp/generate', 'generate')->name('otp.generate');
    Route::get('/otp/verification/{user_id}', 'verification')->name('otp.verification');
    Route::post('/otp/login', 'loginWithOtp')->name('otp.getlogin');*/

    Route::post('password/telephone', 'ForgotPasswordOTP');
    Route::post('password/email', 'ForgotPassword');
    Route::post('password/code/check', 'CodeCheck');
    Route::post('password/reset', 'NewPasswordSend');
});
Route::middleware('auth:sanctum')->group(function () {

    Route::controller(LogActivityController::class)->prefix("logs")->group(function () {
        Route::get('all', 'logActivity');
        Route::get('user/{id}', 'logActivityByUser');
    });
    Route::prefix('adresse')->group(function () {
        //Pays route
        Route::controller(PaysController::class)->prefix("pays")->group(function () {
            Route::get('liste', 'index')->withoutMiddleware(['auth:sanctum']);
            Route::post('create', 'store');
            Route::get('show/{id}', 'show')->withoutMiddleware(['auth:sanctum']);;
            Route::post('update/{id}', 'update');
            Route::post('destroy/{id}', 'destroy');
        });
        //Region route
        Route::controller(RegionController::class)->prefix("region")->group(function () {
            Route::get('liste', 'index')->withoutMiddleware(['auth:sanctum']);;
            Route::post('create', 'store');
            Route::get('show/{id}', 'show')->withoutMiddleware(['auth:sanctum']);;
            Route::post('update/{id}', 'update');
            Route::post('destroy/{id}', 'destroy');
        });
        //Ville route
        Route::controller(VilleController::class)->prefix("ville")->group(function () {
            Route::get('liste', 'index')->withoutMiddleware(['auth:sanctum']);;
            Route::post('create', 'store');
            Route::get('show/{id}', 'show')->withoutMiddleware(['auth:sanctum']);;
            Route::post('update/{id}', 'update');
            Route::post('destroy/{id}', 'destroy');
        });


        //Quartier route
        Route::controller(QuartierController::class)->prefix("quartier")->group(function () {
            Route::get('liste', 'index')->withoutMiddleware(['auth:sanctum']);;
            Route::post('create', 'store');
            Route::get('show/{id}', 'show')->withoutMiddleware(['auth:sanctum']);;
            Route::post('update/{id}', 'update');
            Route::post('destroy/{id}', 'destroy');
        });
    });

});




