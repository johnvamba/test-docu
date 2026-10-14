<?php

namespace App\Enums;

enum DocumentRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /** Roles that can be granted through sharing (ownership is never shared). */
    public static function shareable(): array
    {
        return [self::Editor->value, self::Viewer->value];
    }

    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }
}
