<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Financial\FundWalletAction;
use App\Actions\Financial\PayInvoiceWithWalletAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Shipper $shipper;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->shipper = Shipper::factory()->create(['user_id' => $user->id]);
        $this->wallet = Wallet::factory()->create(['shipper_id' => $this->shipper->id, 'balance' => 0]);

        PaymentMethod::create([
            'name' => 'Wallet',
            'slug' => 'wallet',
            'is_active' => true,
        ]);
    }

    public function test_can_fund_wallet_successfully(): void
    {
        $action = new FundWalletAction;
        $transaction = $action->execute($this->wallet, 500.00, 'Test Funding', 'REF-123');

        $this->wallet->refresh();

        $this->assertEquals(500.00, $this->wallet->balance);
        $this->assertEquals(TransactionType::Credit, $transaction->type);
        $this->assertEquals(500.00, $transaction->amount);
        $this->assertEquals(500.00, $transaction->balance_after);
        $this->assertEquals('REF-123', $transaction->reference);
    }

    public function test_cannot_fund_with_negative_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $action = new FundWalletAction;
        $action->execute($this->wallet, -50.00);
    }

    public function test_cannot_pay_invoice_with_insufficient_funds(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Sent,
            'total_amount' => 1000.00,
        ]);

        $action = new PayInvoiceWithWalletAction;

        $this->expectException(InsufficientFundsException::class);
        $action->execute($this->wallet, $invoice);
    }

    public function test_can_pay_invoice_strictly_in_full(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Sent,
            'total_amount' => 1000.00,
        ]);

        $fundAction = new FundWalletAction;
        $fundAction->execute($this->wallet, 1500.00); // 1500 in wallet

        $payAction = new PayInvoiceWithWalletAction;
        $payment = $payAction->execute($this->wallet, $invoice);

        $this->wallet->refresh();
        $invoice->refresh();

        // Wallet balance accurately deducted
        $this->assertEquals(500.00, $this->wallet->balance);

        // Transaction correctly modeled
        $transaction = $payment->transaction;
        $this->assertNotNull($transaction);
        $this->assertEquals(TransactionType::Debit, $transaction->type);
        $this->assertEquals(1000.00, $transaction->amount);
        $this->assertEquals(500.00, $transaction->balance_after);

        // Payment correctly modeled
        $this->assertEquals(PaymentStatus::Completed, $payment->status);
        $this->assertEquals(1000.00, $payment->amount);
        $this->assertEquals($transaction->id, $payment->transaction_id);

        // Invoice status updated
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_cannot_repay_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Paid,
            'total_amount' => 500.00,
        ]);

        $fundAction = new FundWalletAction;
        $fundAction->execute($this->wallet, 1000.00);

        $payAction = new PayInvoiceWithWalletAction;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice is already paid.');

        $payAction->execute($this->wallet, $invoice);
    }
}
