<?php

declare(strict_types=1);

use Gabrieldav1108\ServerAnalytics\Facades\Analytics;
use Gabrieldav1108\ServerAnalytics\Tests\Support\Invoice;

it('records a business action with the affected record but not its content', function (): void {
    $invoice = new Invoice;
    $invoice->id = 10;
    $invoice->customer_name = 'Jane Doe';

    Analytics::action('invoice.cancelled', $invoice);

    $event = $this->queuedEvents()[0];

    expect($event)
        ->type->toBe('action')
        ->name->toBe('invoice.cancelled')
        ->subject_type->toBe(Invoice::class)
        ->subject_id->toBe('10')
        ->and(json_encode($event))->not->toContain('Jane');
});

it('records a business action without subject', function (): void {
    Analytics::action('report.exported');

    expect($this->queuedEvents()[0])
        ->name->toBe('report.exported')
        ->subject_type->toBeNull()
        ->subject_id->toBeNull();
});
