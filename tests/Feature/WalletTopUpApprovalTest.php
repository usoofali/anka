<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Financial\ApproveWalletTopUpAction;
use App\Actions\Financial\FundWalletAction;
use App\Actions\Financial\RejectWalletTopUpAction;
use App\Actions\Financial\RequestWalletTopUpAction;
use App\Enums\TransactionType;
use App\Enums\WalletTopUpStatus;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class WalletTopUpApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Shipper $shipper;

    private Wallet $wallet;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->shipper = Shipper::factory()->create(['user_id' => $user->id]);
        $this->wallet = Wallet::factory()->create(['shipper_id' => $this->shipper->id, 'balance' => 0]);

        $this->admin = User::factory()->create();
    }

    public function test_shipper_can_request_top_up_with_receipt(): void
    {
        Storage::fake('public');

        $action = new RequestWalletTopUpAction;
        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $topUp = $action->execute($this->shipper, 1000.00, $file, 'BANK-123');

        $this->assertDatabaseHas('wallet_top_ups', [
            'id' => $topUp->id,
            'wallet_id' => $this->wallet->id,
            'amount' => 1000.00,
            'status' => WalletTopUpStatus::Pending->value,
            'reference' => 'BANK-123',
        ]);

        Storage::disk('public')->assertExists($topUp->receipt_path);

        // Wallet balance MUST NOT increment yet
        $this->wallet->refresh();
        $this->assertEquals(0, $this->wallet->balance);
    }

    public function test_admin_can_approve_pending_top_up_and_fund_wallet(): void
    {
        Storage::fake('public');

        // Setup pending top-up
        $requestAction = new RequestWalletTopUpAction;
        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
        $topUp = $requestAction->execute($this->shipper, 500.00, $file, 'BANK-456');

        // Approve it
        $approveAction = new ApproveWalletTopUpAction(new FundWalletAction);
        $approvedTopUp = $approveAction->execute($this->admin, $topUp);

        // Assert Top up status changed
        $this->assertEquals(WalletTopUpStatus::Approved, $approvedTopUp->status);
        $this->assertEquals($this->admin->id, $approvedTopUp->approved_by);

        // Assert Wallet balance officially increased
        $this->wallet->refresh();
        $this->assertEquals(500.00, $this->wallet->balance);

        // Assert Ledger Transaction created via FundWalletAction
        $transaction = $this->wallet->transactions()->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(TransactionType::Credit, $transaction->type);
        $this->assertEquals(500.00, $transaction->amount);
    }

    public function test_admin_can_reject_top_up(): void
    {
        Storage::fake('public');

        $requestAction = new RequestWalletTopUpAction;
        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
        $topUp = $requestAction->execute($this->shipper, 500.00, $file);

        $rejectAction = new RejectWalletTopUpAction;
        $rejectedTopUp = $rejectAction->execute($this->admin, $topUp, 'Illegible receipt photo');

        $this->assertEquals(WalletTopUpStatus::Rejected, $rejectedTopUp->status);
        $this->assertEquals($this->admin->id, $rejectedTopUp->approved_by);
        $this->assertEquals('Illegible receipt photo', $rejectedTopUp->rejection_reason);

        // Wallet balance MUST NOT increment
        $this->wallet->refresh();
        $this->assertEquals(0, $this->wallet->balance);
    }

    public function test_cannot_approve_already_approved_top_up(): void
    {
        Storage::fake('public');

        $requestAction = new RequestWalletTopUpAction;
        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
        $topUp = $requestAction->execute($this->shipper, 500.00, $file);

        $approveAction = new ApproveWalletTopUpAction(new FundWalletAction);
        $approveAction->execute($this->admin, $topUp);

        // Try second approval
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only pending top-ups can be approved.');

        $approveAction->execute($this->admin, $topUp);
    }
}
