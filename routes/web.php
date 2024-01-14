<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::fallback(function () {
    return redirect()->to(filament()->getUrl());
});
