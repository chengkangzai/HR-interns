<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    $candidate = App\Models\Candidate::find(1);
    $email = App\Models\Email::find(1);

    Mail::to($candidate->email)->send(new \App\Mail\DefaultMail($candidate,$email));

    return view('welcome');
});
