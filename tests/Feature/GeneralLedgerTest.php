<?php

namespace Tests\Feature;

use App\Models\AccountCode;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Models\User;
use App\Filament\Vouchers\Pages\GeneralLedgerPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function general_ledger_flags_journalised_vouchers_and_prevents_double_counting(): void
    {
        // 1. Create a user, assign Super Admin role, and authenticate
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('vouchers')
        );

        // 2. Create an AccountCode
        $account = AccountCode::create([
            'code' => '5000',
            'name' => 'Test Expense Account',
            'type' => 'expense',
            'normal_balance' => 'debit',
        ]);

        // 3. Create a paid Voucher with a VoucherItem (Debit: 500.00)
        $voucher = Voucher::create([
            'voucher_number' => 'VCH-001',
            'type' => 'petty_cash',
            'amount' => 500.00,
            'status' => 'paid',
            'payee' => 'Test Supplier',
            'user_id' => $user->id,
        ]);

        VoucherItem::create([
            'voucher_id' => $voucher->id,
            'account_code' => $account->code,
            'debit' => 500.00,
            'credit' => 0.00,
            'branch_code' => 'HQ',
            'description' => 'Voucher item description',
        ]);

        // 4. Create a JournalEntry linked to the Voucher, with a JournalEntryLine (Debit: 500.00)
        $je = JournalEntry::create([
            'entry_no' => 'JE-001',
            'voucher_id' => $voucher->id,
            'date' => now()->toDateString(),
            'reference' => 'Journalized entry',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_code_id' => $account->id,
            'debit' => 500.00,
            'credit' => 0.00,
            'branch' => 'HQ',
            'remarks' => 'JE line remarks',
        ]);

        // 5. Instantiate the GeneralLedgerPage Livewire component and filter for the account
        $component = Livewire::test(GeneralLedgerPage::class);

        // Fill form fields
        $component->set('data', [
            'from_date' => now()->subDays(1)->toDateString(),
            'to_date' => now()->addDays(1)->toDateString(),
            'account_id' => [$account->id],
            'branch' => [],
            'basis' => [],
            'payee' => null,
            'only_with_je' => false,
            'data_source' => 'both',
        ]);

        // Trigger computing the ledger groups
        $ledgerGroups = $component->get('ledgerGroups');

        // 6. Assertions
        $this->assertCount(1, $ledgerGroups, 'Should have one account group');

        $group = $ledgerGroups->first();
        $this->assertEquals($account->id, $group['account']->id);

        $rows = $group['rows'];
        $this->assertCount(2, $rows, 'Should contain both the voucher item and the JE line');

        // Find the voucher row and the JE row
        $voucherRow = $rows->first(fn($r) => $r->source === 'voucher');
        $jeRow = $rows->first(fn($r) => $r->source === 'je');

        $this->assertNotNull($voucherRow);
        $this->assertNotNull($jeRow);

        // Assert info_only flags
        $this->assertTrue($voucherRow->is_info_only, 'Voucher row should be marked as informational only');
        $this->assertFalse($jeRow->is_info_only, 'JE row should NOT be marked as informational only');

        // Assert running balance and totals do not double-count (should be 500.00, not 1000.00)
        $this->assertEquals(500.00, $group['total_debit'], 'Total debit should only include the JE line (500.00)');
        $this->assertEquals(0.00, $group['total_credit']);
        $this->assertEquals(500.00, $group['closing_balance'], 'Closing balance should only include the JE line (500.00)');

        // Assert grand totals are also not double-counted
        $totals = $component->get('totals');
        $this->assertEquals(500.00, $totals['debit']);
        $this->assertEquals(0.00, $totals['credit']);
    }
}
