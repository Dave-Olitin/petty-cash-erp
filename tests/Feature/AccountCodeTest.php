<?php

namespace Tests\Feature;

use App\Models\AccountCode;
use App\Enums\AccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountCodeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_auto_classifies_on_creation(): void
    {
        $account = AccountCode::create([
            'code' => '1001',
            'name' => 'Auto Asset',
        ]);

        $this->assertEquals(AccountType::Asset, $account->type);
        $this->assertEquals('debit', $account->normal_balance);
    }

    #[Test]
    public function it_allows_overriding_type_and_balance_on_update(): void
    {
        $account = AccountCode::create([
            'code' => '5001', // Starts with 5 -> normally Expense
            'name' => 'Manual Override Account',
        ]);

        $this->assertEquals(AccountType::Expense, $account->type);
        $this->assertEquals('debit', $account->normal_balance);

        // Update to Asset and save
        $account->update([
            'type' => AccountType::Asset,
            'normal_balance' => 'credit',
        ]);

        $account = $account->fresh();
        $this->assertEquals(AccountType::Asset, $account->type);
        $this->assertEquals('credit', $account->normal_balance);
    }

    #[Test]
    public function it_reclassifies_on_code_update_if_type_and_balance_not_dirty(): void
    {
        $account = AccountCode::create([
            'code' => '1001', // Starts with 1 -> Asset
            'name' => 'Change Code Account',
        ]);

        $this->assertEquals(AccountType::Asset, $account->type);

        // Update code to start with 2 (Liability)
        $account->update([
            'code' => '2001',
        ]);

        $account = $account->fresh();
        $this->assertEquals(AccountType::Liability, $account->type);
        $this->assertEquals('credit', $account->normal_balance);
    }
}
