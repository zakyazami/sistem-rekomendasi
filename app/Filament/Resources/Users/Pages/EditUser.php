<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\UserManagementService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (DeleteAction $action, User $record): bool {
                    try {
                        app(UserManagementService::class)->delete($record, auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title(collect($exception->errors())->flatten()->first())
                            ->send();
                        $action->halt();
                    }

                    return true;
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        return app(UserManagementService::class)->update($record, $data);
    }
}
