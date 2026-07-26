<?php

namespace App\Enums;

enum HousekeepingAssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
            self::Skipped => 'Skipped',
        };
    }
}
