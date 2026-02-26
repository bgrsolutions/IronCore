<?php

namespace App\Domain\Billing;

final class DashboardMetrics
{
    /**
     * @param array<int, array<string, mixed>> $vendorBills
     * @return array<string, float|int>
     */
    public function summarize(int $companyId, array $vendorBills, string $month): array
    {
        $spend = 0.0;
        $unpaid = 0;
        $awaitingApproval = 0;

        foreach ($vendorBills as $bill) {
            if ((int) $bill['company_id'] !== $companyId) {
                continue;
            }

            $billMonth = substr((string) $bill['invoice_date'], 0, 7);
            if ($billMonth === $month) {
                $spend += (float) $bill['gross_total'];
            }

            $status = (string) ($bill['status'] ?? 'draft');
            if ($status !== 'posted') {
                $unpaid++;
            }

            if ($status === 'draft') {
                $awaitingApproval++;
            }
        }

        return [
            'spend_this_month' => round($spend, 2),
            'unpaid_bills' => $unpaid,
            'bills_awaiting_approval' => $awaitingApproval,
        ];
    }
}
