<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected static ?string $breadcrumb = 'Clock In';

    protected ?string $heading = 'Clock In';

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Clock In'),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['date'] = now()->toDateString();
        $data['time_in'] = now()->toTimeString();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Clocked in successfully';
    }
}
