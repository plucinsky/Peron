<?php

namespace App\Http\Controllers;

use App\Models\Cave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CavesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Caves', [
            'caves' => Cave::query()
                ->select('id', 'name', 'total_length', 'total_drop', 'description')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'total_length' => ['nullable', 'integer', 'min:0'],
            'total_drop' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        Cave::create($data);

        return to_route('caves.index');
    }

    public function update(Request $request, Cave $cave): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'total_length' => ['nullable', 'integer', 'min:0'],
            'total_drop' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $cave->update($data);

        return to_route('caves.index');
    }
}
