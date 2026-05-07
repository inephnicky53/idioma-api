<?php

namespace App\Enum;

enum NewsStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::PUBLISHED => 'Publié',
            self::ARCHIVED => 'Archivé',
        };
    }

    public function getBadgeColor(): string
    {
        return match($this) {
            self::DRAFT => 'warning',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'secondary',
        };
    }
}