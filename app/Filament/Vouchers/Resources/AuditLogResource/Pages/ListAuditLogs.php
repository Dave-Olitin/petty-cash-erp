<?php

namespace App\Filament\Vouchers\Resources\AuditLogResource\Pages;

use App\Filament\Vouchers\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    public ?string $activeTab = 'audit_logs';

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'audit_logs' => Tab::make('Audit Logs')
                ->icon('heroicon-m-clipboard-document-list'),
            'cash_breakdown' => Tab::make('Recent Cash Breakdown Logs')
                ->icon('heroicon-m-circle-stack'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Vouchers\Widgets\DenominationsTableWidget::class,
        ];
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.vouchers.pages.audit-logs-breakdown-style', [
            'activeTab' => $this->activeTab,
        ]);
    }
}
