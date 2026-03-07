<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

class Settings extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $slug = 'settings';
    
    // Position the cluster navigation as tabs at the top instead of a sidebar
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
