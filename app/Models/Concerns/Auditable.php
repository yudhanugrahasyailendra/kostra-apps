<?php

namespace App\Models\Concerns;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\AuditEvent;

/**
 * Mencatat created/updated/deleted ke audit_logs dalam transaksi yang sama dengan modelnya.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(RecordAuditLog::class)(AuditEvent::Created, $model, null, $model->auditableValues($model->attributesToArray()));
        });

        static::updated(function ($model) {
            $new = $model->auditableValues(array_intersect_key($model->attributesToArray(), $model->getChanges()));

            if ($new === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $new);

            app(RecordAuditLog::class)(AuditEvent::Updated, $model, $model->auditableValues($old), $new);
        });

        static::deleted(function ($model) {
            app(RecordAuditLog::class)(AuditEvent::Deleted, $model, $model->auditableValues($model->attributesToArray()), null);
        });
    }

    /**
     * Kolom yang tidak pernah masuk jejak audit (rahasia atau berisik).
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return array_merge(
            ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at', 'last_login_at'],
            property_exists($this, 'auditExtraExclude') ? $this->auditExtraExclude : [],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditableValues(array $values): array
    {
        $values = array_diff_key($values, array_flip($this->auditExclude()));

        // Data pribadi tidak disalin ke jejak audit: kolom tercatat berubah, nilainya disamarkan.
        $masked = property_exists($this, 'auditMasked') ? $this->auditMasked : [];

        foreach ($masked as $column) {
            if (array_key_exists($column, $values)) {
                $values[$column] = '***';
            }
        }

        return $values;
    }
}
