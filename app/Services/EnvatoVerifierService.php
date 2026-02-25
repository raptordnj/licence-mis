<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Domain\EnvatoVerificationData;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use App\Services\Contracts\EnvatoVerifierInterface;

readonly class EnvatoVerifierService implements EnvatoVerifierInterface
{
    public function __construct(private EnvatoPurchaseValidatorInterface $purchaseValidator)
    {
    }

    public function verifyPurchaseCode(string $purchaseCode): EnvatoVerificationData
    {
        $validation = $this->purchaseValidator->validate($purchaseCode, null, null);

        return new EnvatoVerificationData(
            valid: $validation->valid,
            itemId: $validation->envatoItemId,
            supportedUntil: $validation->supportedUntil,
            itemName: $validation->itemName,
        );
    }
}
