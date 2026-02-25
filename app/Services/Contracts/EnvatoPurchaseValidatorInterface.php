<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Data\Domain\ValidationResultDTO;

interface EnvatoPurchaseValidatorInterface
{
    public function validate(string $purchaseCode, ?int $envatoItemId, ?int $productId): ValidationResultDTO;
}
