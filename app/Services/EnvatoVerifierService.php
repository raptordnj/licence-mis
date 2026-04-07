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

    public function verifyPurchaseCode(
        string $purchaseCode,
        ?int $envatoItemId = null,
        ?int $productId = null,
    ): EnvatoVerificationData {
        $validation = $this->purchaseValidator->validate($purchaseCode, $envatoItemId, $productId);

        return new EnvatoVerificationData(
            valid: $validation->valid,
            itemId: $validation->envatoItemId,
            supportedUntil: $validation->supportedUntil,
            itemName: $validation->itemName,
        );
    }
}
