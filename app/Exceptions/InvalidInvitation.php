<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidInvitation extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Undangan tidak valid, sudah dipakai, atau sudah kedaluwarsa.');
    }
}
