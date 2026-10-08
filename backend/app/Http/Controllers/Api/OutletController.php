<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 50;

        $query = Outlet::query()->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('branch', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $request->query('page', 1));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Outlet $outlet) => [
                'id' => $outlet->code,
                'name' => $outlet->name,
                'chain' => $outlet->chain,
                'branch' => $outlet->branch,
                'city' => $outlet->city,
                'gps_lat' => $outlet->gps_lat !== null ? (float) $outlet->gps_lat : null,
                'gps_lng' => $outlet->gps_lng !== null ? (float) $outlet->gps_lng : null,
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
