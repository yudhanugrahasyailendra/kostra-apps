<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class TenantExportRegistry
{
    /** @var list<class-string<Model&TenantExportable>> */
    private array $models = [];

    /** @param  class-string<Model&TenantExportable>  $model */
    public function register(string $model): void
    {
        if (! is_subclass_of($model, TenantExportable::class) || ! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException("$model harus model Eloquent yang mengimplementasikan TenantExportable.");
        }

        if (! in_array($model, $this->models, true)) {
            $this->models[] = $model;
        }
    }

    /** @return list<class-string<Model&TenantExportable>> */
    public function all(): array
    {
        return $this->models;
    }
}
