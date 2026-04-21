<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryWebController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $lowStockItems = Service::query()
            ->where('is_active', true)
            ->inventoryDashboard()
            ->lowStock()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->get();

        $items = Service::query()
            ->where('is_active', true)
            ->inventoryDashboard()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderByRaw('CASE WHEN track_inventory = 1 AND stock_quantity <= reorder_level THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        return view('inventory.index', [
            'items' => $items,
            'lowStockItems' => $lowStockItems,
            'search' => $search,
        ]);
    }
}
