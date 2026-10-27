<?php

namespace App\Enums;

enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Voided = 'voided';
    case ViewedSensitive = 'viewed_sensitive';
    case Impersonated = 'impersonated';
    case Exported = 'exported';
}
