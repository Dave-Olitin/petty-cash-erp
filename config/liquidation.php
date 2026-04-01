<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Liquidation Minimum Threshold (AED)
    |--------------------------------------------------------------------------
    | Only paid Petty Cash Vouchers (PCVs) with an amount >= this value will
    | automatically require formal liquidation. Set to 0 to require ALL PCVs.
    |
    */
    'minimum_amount' => env('LIQUIDATION_MINIMUM_AMOUNT', 0),

    /*
    |--------------------------------------------------------------------------
    | Liquidation Deadline (Working Days)
    |--------------------------------------------------------------------------
    | The number of calendar days after a PCV is paid within which the employee
    | must submit their liquidation. Set to null to disable deadline tracking.
    |
    */
    'deadline_days' => env('LIQUIDATION_DEADLINE_DAYS', 5),
];
