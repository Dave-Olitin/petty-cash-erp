<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW bank_payment_lines AS
            SELECT 
                CONCAT(v.id, '-', numbers.idx) AS id,
                v.id AS voucher_id,
                numbers.idx AS payment_index,
                v.voucher_number,
                v.type,
                v.status,
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.multiple_payments, CONCAT('$[', numbers.idx, '].cheque_date'))), 'null'),
                    v.cheque_date,
                    v.date
                ) AS cheque_date,
                v.date AS voucher_date,
                v.payee,
                v.department,
                v.description,
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.multiple_payments, CONCAT('$[', numbers.idx, '].bank'))), 'null'),
                    v.bank
                ) AS bank,
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.multiple_payments, CONCAT('$[', numbers.idx, '].cheque_no'))), 'null'),
                    v.cheque_no
                ) AS cheque_no,
                CAST(COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.multiple_payments, CONCAT('$[', numbers.idx, '].amount'))), 'null'),
                    v.amount
                ) AS DECIMAL(15, 2)) AS amount,
                (CASE 
                    WHEN v.multiple_payments IS NOT NULL AND JSON_VALID(v.multiple_payments) AND JSON_LENGTH(v.multiple_payments) > 1 
                    THEN 1 
                    ELSE 0 
                END) AS is_split,
                (CASE 
                    WHEN v.multiple_payments IS NOT NULL AND JSON_VALID(v.multiple_payments) AND JSON_LENGTH(v.multiple_payments) > 0 
                    THEN JSON_LENGTH(v.multiple_payments) 
                    ELSE 1 
                END) AS total_splits,
                v.created_at,
                v.updated_at
            FROM vouchers v
            JOIN (
                SELECT 0 AS idx UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
                UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14
                UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
            ) numbers ON numbers.idx < CASE 
                WHEN v.multiple_payments IS NOT NULL AND JSON_VALID(v.multiple_payments) AND JSON_LENGTH(v.multiple_payments) > 0 
                THEN JSON_LENGTH(v.multiple_payments) 
                ELSE 1 
            END
            WHERE v.deleted_at IS NULL
              AND v.type IN ('payment', 'bank_encashment')
              AND (
                  (v.bank IS NOT NULL AND v.bank != '')
                  OR v.multiple_payments IS NOT NULL
              )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS bank_payment_lines");
    }
};
