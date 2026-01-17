<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiariesController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'report_number' => $request->string('report_number')->trim()->toString(),
            'locality_name' => $request->string('locality_name')->trim()->toString(),
            'leader_person_id' => $request->string('leader_person_id')->trim()->toString(),
            'date_from' => $request->string('date_from')->trim()->toString(),
            'date_to' => $request->string('date_to')->trim()->toString(),
        ];

        $query = Diary::query()
            ->select(
                'id',
                'report_number',
                'locality_name',
                'action_date',
                'leader_person_id',
                'work_time'
            )
            ->orderByDesc('action_date')
            ->orderByDesc('id');

        if ($filters['report_number'] !== '') {
            $query->where('report_number', 'ILIKE', "%{$filters['report_number']}%");
        }

        if ($filters['locality_name'] !== '') {
            $query->where('locality_name', 'ILIKE', "%{$filters['locality_name']}%");
        }

        if ($filters['leader_person_id'] !== '') {
            $query->where('leader_person_id', (int) $filters['leader_person_id']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('action_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('action_date', '<=', $filters['date_to']);
        }

        return Inertia::render('Diaries/Index', [
            'diaries' => $query->get(),
            'persons' => Person::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Diaries/Form', [
            'diary' => null,
            'persons' => Person::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        Diary::create($data);

        return to_route('diaries.index');
    }

    public function edit(Diary $diary): Response
    {
        return Inertia::render('Diaries/Form', [
            'diary' => $diary->only([
                'id',
                'report_number',
                'locality_name',
                'locality_position',
                'karst_area',
                'orographic_unit',
                'action_date',
                'work_time',
                'weather',
                'leader_person_id',
                'member_person_ids',
                'other_participants',
                'work_description',
                'excavated_length_m',
                'discovered_length_m',
                'surveyed_length_m',
                'surveyed_depth_m',
                'leader_signed_person_id',
                'leader_signed_at',
                'club_signed_person_id',
                'club_signed_at',
            ]),
            'persons' => Person::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(Request $request, Diary $diary): RedirectResponse
    {
        $data = $this->validatedData($request);

        $diary->update($data);

        return to_route('diaries.index');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'report_number' => ['nullable', 'string', 'max:255'],
            'locality_name' => ['nullable', 'string', 'max:255'],
            'locality_position' => ['nullable', 'string', 'max:255'],
            'karst_area' => ['nullable', 'string', 'max:255'],
            'orographic_unit' => ['nullable', 'string', 'max:255'],
            'action_date' => ['nullable', 'date'],
            'work_time' => ['nullable', 'string', 'max:255'],
            'weather' => ['nullable', 'string'],
            'leader_person_id' => ['nullable', 'integer'],
            'member_person_ids' => ['nullable', 'array'],
            'member_person_ids.*' => ['integer'],
            'other_participants' => ['nullable', 'string'],
            'work_description' => ['nullable', 'string'],
            'excavated_length_m' => ['nullable', 'numeric', 'min:0'],
            'discovered_length_m' => ['nullable', 'numeric', 'min:0'],
            'surveyed_length_m' => ['nullable', 'numeric', 'min:0'],
            'surveyed_depth_m' => ['nullable', 'numeric', 'min:0'],
            'leader_signed_person_id' => ['nullable', 'integer'],
            'leader_signed_at' => ['nullable', 'date'],
            'club_signed_person_id' => ['nullable', 'integer'],
            'club_signed_at' => ['nullable', 'date'],
        ]);

        $memberIds = $data['member_person_ids'] ?? [];
        $data['member_person_ids'] = array_values(array_unique(array_filter($memberIds)));

        return $data;
    }
}
