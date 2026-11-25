<?php

namespace App\Services\Payments;

use RuntimeException;

/** A gateway callback whose signature or merchant does not verify; it must never change an order. */
class InvalidPaymentNotification extends RuntimeException {}
