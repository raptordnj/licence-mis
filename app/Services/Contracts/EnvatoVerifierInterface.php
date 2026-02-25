<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Data\Domain\EnvatoVerificationData;

interface EnvatoVerifierInterface
{
    public function verifyPurchaseCode(string $purchaseCode): EnvatoVerificationData;
}
