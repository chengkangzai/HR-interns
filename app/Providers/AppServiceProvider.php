<?php

namespace App\Providers;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\ServiceProvider;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

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
        Section::configureUsing(fn (Section $section) => $section->columns(2)->compact(true));

        SpatieMediaLibraryFileUpload::configureUsing(fn (SpatieMediaLibraryFileUpload $fileUpload) => $fileUpload
            ->openable()
            ->downloadable()
            ->previewable()
        );

        DatePicker::configureUsing(fn (DatePicker $datePicker) => $datePicker->native(false));

        DateTimePicker::configureUsing(fn (DateTimePicker $dateTimePicker) => $dateTimePicker->native(false));

        PhoneInput::configureUsing(function (PhoneInput $phoneInput) {
            $phoneInput
                ->preferredCountries(['MY'])
                ->defaultCountry('MY')
                ->initialCountry('MY');
        });

        Field::configureUsing(function (Field $field) {
            $excludedFields = [
                Repeater::class,
                Builder::class,
                Textarea::class,
                RichEditor::class,
                SpatieMediaLibraryFileUpload::class,
            ];

            // Skip inline label for excluded field types
            if (in_array(get_class($field), $excludedFields)) {
                return;
            }
            $field->inlineLabel();
        });
    }
}
