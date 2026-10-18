<?php

namespace App\Enums;

enum ReportStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVISION = 'revision';
    case VERIFIED = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::REVISION => 'Revisi',
            self::VERIFIED => 'Terverifikasi',
        };
    }
}
