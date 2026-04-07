<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Data\Domain\EnvatoVerificationData;

interface EnvatoVerifierInterface
{
    public function verifyPurchaseCode(
        string $purchaseCode,
        ?int $envatoItemId = null,
        ?int $productId = null,
    ): EnvatoVerificationData;
}
