<?php

namespace App\Policies;

/** Rekening milik pemilik: semua pengguna tenant boleh melihat, hanya pemilik yang mengubah. */
class PaymentAccountPolicy extends OwnerManagedPolicy {}
