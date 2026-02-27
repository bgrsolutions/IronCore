<?php

require_once __DIR__ . '/../../app/Domain/Audit/AuditLogger.php';
require_once __DIR__ . '/../../app/Domain/Billing/ExpenseService.php';

use App\Domain\Audit\AuditLogger;
use App\Domain\Billing\ExpenseService;

function test_expense_posting_and_deletion_policy(): void
{
    $audit = new AuditLogger();
    $service = new ExpenseService($audit);

    $expense = ['id' => 22, 'company_id' => 2, 'status' => 'draft', 'lines' => [['net_amount' => 10, 'tax_amount' => 0.7, 'gross_amount' => 10.7]]];

    $expense = $service->approve($expense, 1);
    $expense = $service->post($expense, 1);

    $exceptionThrown = false;
    try {
        $service->update($expense, ['merchant' => 'Changed'], 1);
    } catch (RuntimeException) {
        $exceptionThrown = true;
    }

    assert($exceptionThrown === true);

    $draft = ['id' => 23, 'company_id' => 2, 'status' => 'draft'];
    $service->delete($draft, true, 1);

    assert(count($audit->all()) >= 3);
}
