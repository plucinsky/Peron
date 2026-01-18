<?php

namespace App\Models;

use App\Jobs\ProcessArchiveDocumentAnalyzeText;
use App\Jobs\ProcessArchiveDocumentOcr;
use App\Jobs\ProcessArchiveDocumentPreview;
use App\Jobs\ProcessArchiveDocumentRag;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Mattiverse\Userstamps\Traits\Userstamps;

class ArchiveDocument extends Model
{
    /** @use HasFactory<\Database\Factories\ArchiveDocumentFactory> */
    use HasFactory, SoftDeletes, Userstamps;

    private const PROCESSING_STATUS_LABELS = [
        'pending' => 'Caka na spracovanie',
        'queued' => 'Vo fronte',
        'processing' => 'Spracovava sa',
        'done' => 'Hotovo',
        'failed' => 'Chyba',
        'complete' => 'Kompletne',
    ];

    private const PROCESSING_STEP_LABELS = [
        'generatePreview' => 'Generovanie nahladu dokumentu',
        'ocr' => 'OCR',
        'analyzeText' => 'Analyza textu',
        'rag' => 'RAG vyhladavanie',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'archive_id',
        'diary_id',
        'relation_type',
        'caption',
        'seq',
        'name',
        'type',
        'mime_type',
        'extension',
        'size',
        'storage_path',
        'original_filename',
        'checksum',
        'meta',
        'ocr_text',
        'processed_diary_data',
        'ocr_status',
        'preview_status',
        'preview_error',
        'preview_page_count',
        'preview_extension',
        'processing_status',
        'processing_step',
        'processing_at',
        'analyze_text_status',
        'rag_status',
        'processing_log',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'processed_diary_data' => 'array',
            'processing_log' => 'array',
            'processing_at' => 'datetime',
        ];
    }

    public function processing(bool $force = false): void
    {
        $this->refresh();

        if (in_array($this->processing_status, ['queued', 'processing'], true)) {
            return;
        }

        foreach ($this->processingSteps() as $step) {
            $status = $this->{$step['status']};
            if ($status === 'done') {
                continue;
            }

            if (in_array($status, ['pending', 'queued', 'processing'], true)) {
                $this->update([
                    'processing_step' => $step['name'],
                    'processing_status' => $status,
                ]);
                $this->appendProcessingLog($step['name'], 'info', 'Krok uz je naplanovany alebo bezi.');
                return;
            }

            if ($status === 'failed' && !$force) {
                $this->update([
                    'processing_step' => $step['name'],
                    'processing_status' => 'failed',
                ]);
                $this->appendProcessingLog($step['name'], 'warning', 'Krok zlyhal, neobnovujem bez force.');
                return;
            }

            $updates = [
                'processing_step' => $step['name'],
                'processing_status' => 'pending',
                $step['status'] => 'pending',
            ];
            if (!empty($step['error'])) {
                $updates[$step['error']] = null;
            }

            $this->update($updates);
            $this->appendProcessingLog($step['name'], 'info', 'Krok zaradeny do spracovania.');
            $step['dispatch']($this->id);
            return;
        }

        $this->update([
            'processing_step' => null,
            'processing_status' => 'complete',
            'processing_at' => null,
        ]);
        $this->appendProcessingLog('complete', 'info', 'Spracovanie je kompletne.');
    }

    public function startProcessingMissing(): void
    {
        $this->appendProcessingLog('manual', 'info', 'Manuálne spustené spracovanie chýbajúcich častí.');
        $this->processing(false);
    }

    public function restartProcessingFull(): void
    {
        $this->update([
            'processing_status' => null,
            'processing_step' => null,
            'processing_at' => null,
            'preview_status' => null,
            'preview_error' => null,
            'preview_page_count' => null,
            'preview_extension' => null,
            'ocr_status' => null,
            'ocr_text' => null,
            'analyze_text_status' => null,
            'processed_diary_data' => null,
            'rag_status' => null,
        ]);
        $this->embeddings()->delete();

        $this->appendProcessingLog('manual', 'info', 'Manuálne spustené kompletné spracovanie od začiatku.');
        $this->processing(true);
    }

    public function processDiaryData(): ?array
    {
        if (!$this->ocr_text) {
            return null;
        }

        $response = Http::withToken((string) env('OPENAI_API_KEY'))
            ->timeout(600)
            ->post('https://api.openai.com/v1/responses', [
                'model' => env('OPENAI_MODEL', 'gpt-4o'),
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Extract data from the OCR text and return ONLY valid JSON. Use this exact structure and map each label to the field below:\n\n- "TECHNICKÝ DENNÍK č." -> report_number\n- "Lokalita" -> locality_name\n- "Poloha lokality" -> locality_position\n- "Krasové územie" -> karst_area\n- "Orografický celok" -> orographic_unit\n- "Dátum" -> action_date (format dd.mm.yyyy)\n- "Pracovná doba" -> work_time\n- "Počasie počas akcie" -> weather\n- "Vedúci akcie" -> leader_name\n- "Ostatní členovia SSS" -> sss_participants (array of names, without leader)\n- "Iní účastníci" (or "PL"/"SK" lists) -> other_participants (array of names)\n- "Popis pracovnej činnosti" -> work_description\n- "Vyhĺbené (hĺbka) [m]" -> excavated_length_m\n- "Objavené (dĺžka) [m]" -> discovered_length_m\n- "Zamerané (dĺžka, hĺbka) [m]" -> surveyed_length_m and surveyed_depth_m (split values if present)\n\nReturn JSON with keys: report_number, locality_name, locality_position, karst_area, orographic_unit, action_date, work_time, weather, leader_name, work_description, excavated_length_m, discovered_length_m, surveyed_length_m, surveyed_depth_m, sss_participants, other_participants. If a value is missing, use an empty string for string fields and an empty array for participant arrays. Return only JSON, no extra text.',
                            ],
                            [
                                'type' => 'input_text',
                                'text' => $this->ocr_text,
                            ],
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            return null;
        }

        $raw = $this->extractText($response->json());
        $parsed = $this->parseJson($raw);

        $this->update([
            'processed_diary_data' => $parsed,
        ]);

        return $parsed;
    }

    private function processingSteps(): array
    {
        return [
            [
                'name' => 'generatePreview',
                'status' => 'preview_status',
                'error' => 'preview_error',
                'dispatch' => fn (int $id) => ProcessArchiveDocumentPreview::dispatch($id),
            ],
            [
                'name' => 'ocr',
                'status' => 'ocr_status',
                'dispatch' => fn (int $id) => ProcessArchiveDocumentOcr::dispatch($id),
            ],
            [
                'name' => 'analyzeText',
                'status' => 'analyze_text_status',
                'dispatch' => fn (int $id) => ProcessArchiveDocumentAnalyzeText::dispatch($id),
            ],
            [
                'name' => 'rag',
                'status' => 'rag_status',
                'dispatch' => fn (int $id) => ProcessArchiveDocumentRag::dispatch($id),
            ],
        ];
    }

    public function appendProcessingLog(string $step, string $type, string $message): void
    {
        $this->refresh();
        $log = $this->processing_log ?? [];
        $log[] = [
            'time' => now()->toIso8601String(),
            'step' => $step,
            'type' => $type,
            'message' => $message,
        ];

        $this->forceFill(['processing_log' => $log])->save();
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(ArchiveDocumentEmbedding::class);
    }

    public function getProcessingStatusLabelAttribute(): string
    {
        return self::processingStatusLabel($this->processing_status);
    }

    public function getProcessingStepLabelAttribute(): string
    {
        return self::processingStepLabel($this->processing_step);
    }

    public function getPreviewStatusLabelAttribute(): string
    {
        return self::processingStatusLabel($this->preview_status);
    }

    public function getOcrStatusLabelAttribute(): string
    {
        return self::processingStatusLabel($this->ocr_status);
    }

    public function getAnalyzeTextStatusLabelAttribute(): string
    {
        return self::processingStatusLabel($this->analyze_text_status);
    }

    public function getRagStatusLabelAttribute(): string
    {
        return self::processingStatusLabel($this->rag_status);
    }

    public static function processingStatusLabel(?string $status): string
    {
        if (!$status) {
            return 'Nezadané';
        }

        return self::PROCESSING_STATUS_LABELS[$status] ?? $status;
    }

    public static function processingStepLabel(?string $step): string
    {
        if (!$step) {
            return '-';
        }

        return self::PROCESSING_STEP_LABELS[$step] ?? $step;
    }

    private function extractText(array $payload): string
    {
        if (!empty($payload['output_text'])) {
            return (string) $payload['output_text'];
        }

        $output = $payload['output'] ?? [];
        $chunks = [];
        foreach ($output as $item) {
            $contents = $item['content'] ?? [];
            foreach ($contents as $content) {
                if (($content['type'] ?? '') === 'output_text') {
                    $chunks[] = $content['text'] ?? '';
                }
            }
        }

        return trim(implode("\n", array_filter($chunks)));
    }

    private function parseJson(string $text): array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $slice = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($slice, true);

        return is_array($decoded) ? $decoded : [];
    }
}
