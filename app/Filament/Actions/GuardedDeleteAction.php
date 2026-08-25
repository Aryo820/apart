<?php

namespace App\Filament\Actions;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

final class GuardedDeleteAction
{
    public static function make(string $message): DeleteAction
    {
        return DeleteAction::make()
            ->databaseTransaction()
            ->using(function (Model $record) use ($message): ?bool {
                try {
                    return $record->delete();
                } catch (QueryException $exception) {
                    if (! self::isConstraintViolation($exception)) {
                        throw $exception;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Penghapusan ditolak')
                        ->body($message)
                        ->persistent()
                        ->send();
                    throw (new Halt)->rollBackDatabaseTransaction();
                }
            });
    }

    private static function isConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? (string) $exception->getCode(), ['23000', '23503'], true);
    }
}
