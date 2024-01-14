<?php

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->setupFilamentDefault();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function setupFilamentDefault(): void
    {
        Section::configureUsing(fn(Section $section) => $section->columns(2)->compact(true));

        SpatieMediaLibraryFileUpload::configureUsing(fn(SpatieMediaLibraryFileUpload $fileUpload) => $fileUpload
            ->openable()
            ->downloadable()
            ->previewable()
        );

        DatePicker::configureUsing(fn(DatePicker $datePicker) => $datePicker->native(false));

        DateTimePicker::configureUsing(fn(DateTimePicker $dateTimePicker) => $dateTimePicker->native(false));
    }
}
