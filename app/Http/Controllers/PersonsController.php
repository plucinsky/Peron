<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Persons', [
            'persons' => Person::query()
                ->select('id', 'first_name', 'last_name', 'city', 'email', 'phone', 'country')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
        ]);

        Person::create($data);

        return to_route('persons.index');
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
        ]);

        $person->update($data);

        return to_route('persons.index');
    }
}
