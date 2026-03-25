<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipper;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ShipperController extends Controller
{
    public function show(Request $request, Shipper $shipper): View
    {
        $this->authorize('view', $shipper);

        if ($request->filled('notification')) {
            $request->user()
                ?->notifications()
                ->whereKey($request->query('notification'))
                ->first()
                ?->markAsRead();
        }

        $shipper->load(['user', 'country', 'state', 'city']);

        return view('pages.shippers.show', [
            'shipper' => $shipper,
        ]);
    }
}
