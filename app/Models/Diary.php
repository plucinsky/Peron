<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class Diary extends Model
{
    /** @use HasFactory<\Database\Factories\DiaryFactory> */
    use HasFactory, SoftDeletes, Userstamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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
        'other_person_ids',
        'sss_participants_note',
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'member_person_ids' => 'array',
        'other_person_ids' => 'array',
        'action_date' => 'date',
        'leader_signed_at' => 'date',
        'club_signed_at' => 'date',
    ];
}
