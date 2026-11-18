<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function permitter(Request $request): RedirectResponse
    {
        return redirect()->route('fjp.index');
    }

    public function tim(): RedirectResponse
    {
        return redirect()->route('team.index');
    }

    public function gudang(): RedirectResponse
    {
        return redirect()->route('warehouse.mrv.index');
    }

    public function dapur(): RedirectResponse
    {
        return redirect()->route('kitchen.index');
    }

    public function pic(): RedirectResponse
    {
        return redirect()->route('fjp.index');
    }

    public function admin(): RedirectResponse
    {
        return redirect()->route('reports.by-event');
    }

    public function adminEvent(): RedirectResponse
    {
        return redirect()->route('reports.by-event');
    }
}
