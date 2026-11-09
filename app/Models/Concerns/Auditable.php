<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Mencatat created, updated, dan deleted ke audit_logs dengan nilai lama dan
 * baru. Model dapat mengisi `$auditExclude` untuk kolom yang tidak dicatat.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            app(AuditLogger::class)->record('created', $model, null, $model->auditValues($model->getAttributes()));
        });

        static::updated(function ($model): void {
            $changed = $model->auditValues($model->getChanges());

            if ($changed === []) {
                return;
            }

            $old = array_intersect_key($model->auditValues($model->getRawOriginal()), $changed);

            app(AuditLogger::class)->record('updated', $model, $old, $changed);
        });

        static::deleted(function ($model): void {
            app(AuditLogger::class)->record('deleted', $model, $model->auditValues($model->getRawOriginal()), null);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        $exclude = array_merge(
            ['password', 'remember_token', 'token_hash', 'updated_at'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return array_diff_key($values, array_flip($exclude));
    }
}
