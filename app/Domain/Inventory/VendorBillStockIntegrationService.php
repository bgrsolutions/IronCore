<?php

namespace App\Domain\Inventory;

use App\Models\VendorBill;
use App\Services\VendorBillIntelligenceService;

final class VendorBillStockIntegrationService
{
    public function __construct(private readonly VendorBillIntelligenceService $vendorBillIntelligenceService) {}

    public function receiveForPostedBill(VendorBill $bill): void
    {
        if ($bill->status !== 'posted') {
            return;
        }

        $this->vendorBillIntelligenceService->processPostedBill($bill);
    }
}
