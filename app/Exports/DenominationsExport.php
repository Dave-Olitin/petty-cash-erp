<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DenominationsExport implements WithMultipleSheets
{
    use Exportable;

    protected $query;
    protected $dateFrom;
    protected $dateTo;

    public function __construct($query, $dateFrom, $dateTo)
    {
        // $query is a builder of Voucher models completely filtered by the report page
        $this->query = $query;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function sheets(): array
    {
        return [
            new Sheets\DenominationsSummarySheet($this->query, $this->dateFrom, $this->dateTo),
            new Sheets\DenominationsDetailedSheet($this->query, $this->dateFrom, $this->dateTo),
        ];
    }
}
