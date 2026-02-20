<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionObserverTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name'            => 'Test Branch',
            'current_balance' => 1000.00,
            'max_limit'       => 5000.00,
            'transaction_limit' => 500.00,
            'is_active'       => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    // -----------------------------------------------------------------
    // EXPENSE tests
    // -----------------------------------------------------------------

    #[Test]
    public function creating_an_expense_decrements_branch_balance(): void
    {
        Transaction::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => $this->user->id,
            'type'         => 'EXPENSE',
            'amount'       => 200.00,
            'status'       => 'pending',
            'receipt_path' => 'receipts/test.pdf',
        ]);

        $this->assertEquals(800.00, $this->branch->fresh()->current_balance);
    }

    #[Test]
    public function creating_a_replenishment_increments_branch_balance(): void
    {
        Transaction::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => $this->user->id,
            'type'         => 'REPLENISHMENT',
            'amount'       => 500.00,
            'status'       => 'pending',
            'receipt_path' => 'receipts/test.pdf',
        ]);

        $this->assertEquals(1500.00, $this->branch->fresh()->current_balance);
    }

    #[Test]
    public function voiding_a_pending_expense_restores_balance(): void
    {
        $transaction = Transaction::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => $this->user->id,
            'type'         => 'EXPENSE',
            'amount'       => 200.00,
            'status'       => 'pending',
            'receipt_path' => 'receipts/test.pdf',
        ]);

        $transaction->delete(); // Void it

        $this->assertEquals(1000.00, $this->branch->fresh()->current_balance);
    }

    #[Test]
    public function voiding_a_rejected_expense_does_not_change_balance(): void
    {
        $transaction = Transaction::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => $this->user->id,
            'type'         => 'EXPENSE',
            'amount'       => 200.00,
            'status'       => 'pending',
            'receipt_path' => 'receipts/test.pdf',
        ]);

        // Rejection reverses the balance (800 → 1000)
        $transaction->update(['status' => 'rejected']);
        $this->assertEquals(1000.00, $this->branch->fresh()->current_balance);

        // Voiding an already-rejected transaction should NOT double-restore
        $transaction->delete();

        $this->assertEquals(1000.00, $this->branch->fresh()->current_balance);
    }

    #[Test]
    public function rejecting_a_transaction_reverses_balance(): void
    {
        $transaction = Transaction::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => $this->user->id,
            'type'         => 'EXPENSE',
            'amount'       => 200.00,
            'status'       => 'pending',
            'receipt_path' => 'receipts/test.pdf',
        ]);

        $this->assertEquals(800.00, $this->branch->fresh()->current_balance);

        $transaction->update(['status' => 'rejected']);

        $this->assertEquals(1000.00, $this->branch->fresh()->current_balance);
    }

    #[Test]
    public function restoring_a_voided_transaction_re_applies_balance(): void
    {
        $transaction = Transaction::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => $this->user->id,
            'type'         => 'EXPENSE',
            'amount'       => 200.00,
            'status'       => 'pending',
            'receipt_path' => 'receipts/test.pdf',
        ]);

        $transaction->delete(); // Void — balance restored to 1000
        $this->assertEquals(1000.00, $this->branch->fresh()->current_balance);

        $transaction->restore(); // Un-void — balance decremented again
        $this->assertEquals(800.00, $this->branch->fresh()->current_balance);
    }

    #[Test]
    public function editing_amount_of_pending_expense_correctly_adjusts_balance(): void
    {
        $transaction = Transaction::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => $this->user->id,
            'type'         => 'EXPENSE',
            'amount'       => 200.00,
            'status'       => 'pending',
            'receipt_path' => 'receipts/test.pdf',
        ]);

        // Balance is 800. Update amount to 300.
        $transaction->update(['amount' => 300.00]);

        // Observer reverts 200 and applies 300 → 1000 - 300 = 700
        $this->assertEquals(700.00, $this->branch->fresh()->current_balance);
    }
}
