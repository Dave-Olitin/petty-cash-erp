<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountCode extends Model
{
    protected $fillable = ['code', 'name'];

    /** All voucher line items posted to this account code. */
    public function voucherItems()
    {
        return $this->hasMany(VoucherItem::class, 'account_code', 'code');
    }

    /** Debit lines only — used for withSum aggregate in the table. Excludes rejected/voided vouchers. */
    public function debitItems()
    {
        return $this->hasMany(VoucherItem::class, 'account_code', 'code')
                    ->where('debit', '>', 0)
                    ->whereHas('voucher', fn ($q) => $q->whereNotIn('status', ['rejected', 'voided']));
    }

    /** Credit lines only — used for withSum aggregate in the table. Excludes rejected/voided vouchers. */
    public function creditItems()
    {
        return $this->hasMany(VoucherItem::class, 'account_code', 'code')
                    ->where('credit', '>', 0)
                    ->whereHas('voucher', fn ($q) => $q->whereNotIn('status', ['rejected', 'voided']));
    }
}
