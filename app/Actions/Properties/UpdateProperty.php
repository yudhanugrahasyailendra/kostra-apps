<?php

namespace App\Actions\Properties;

use App\Models\Property;

class UpdateProperty
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Property $property, array $data): Property
    {
        $property->update($data);

        return $property;
    }
}
