<?php

namespace App\Services\Plans;

use RuntimeException;

/** Melewati batas paket. Dirender sebagai galat formulir `limit` dengan tawaran naik paket. */
class PlanLimitExceededException extends RuntimeException {}
