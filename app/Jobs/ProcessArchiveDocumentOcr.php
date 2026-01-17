<?php

namespace App\Jobs;

use App\Models\ArchiveDocument;
use App\Jobs\ProcessArchiveDocumentPreview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessArchiveDocumentOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 900;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    public function __construct(private readonly int $archiveDocumentId)
    {
    }

    public function handle(): void
    {
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if (!$document || !$document->storage_path || !Storage::exists($document->storage_path)) {
            return;
        }

        $document->update([
            'ocr_status' => 'processing',
            'ocr_error' => null,
        ]);

        $images = $this->buildImagePayloads($document);
        if (count($images) === 0) {
            $document->update([
                'ocr_status' => 'failed',
                'ocr_error' => 'Nepodarilo sa pripraviť obrázky pre OCR.',
            ]);
            return;
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
                                'text' => 'Extract the raw text from this document. Return only the text, no commentary.',
                            ],
                            ...$images,
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $document->update([
                'ocr_status' => 'failed',
                'ocr_error' => $response->body(),
            ]);
            return;
        }

        $text = $this->extractText($response->json());

        $document->update([
            'ocr_text' => $text,
            'ocr_status' => 'done',
            'ocr_processed_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        ArchiveDocument::whereKey($this->archiveDocumentId)->update([
            'ocr_status' => 'failed',
            'ocr_error' => $exception->getMessage(),
        ]);
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

    private function buildImagePayloads(ArchiveDocument $document): array
    {
        $extension = strtolower($document->extension ?? '');
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'];

        if (in_array($extension, $imageExtensions, true)) {
            return [$this->imagePayload($document->storage_path, $document->mime_type)];
        }

        if ($document->preview_status !== 'done') {
            (new ProcessArchiveDocumentPreview($document->id))->handle();
            $document->refresh();
        }

        if ($document->preview_status !== 'done' || !$document->preview_page_count) {
            return [];
        }

        $payloads = [];
        $previewExtension = $document->preview_extension ?: 'png';
        for ($page = 1; $page <= $document->preview_page_count; $page++) {
            $path = "previews/{$document->id}/page-{$page}.{$previewExtension}";
            if (!Storage::exists($path)) {
                continue;
            }
            $payloads[] = $this->imagePayload($path, Storage::mimeType($path));
        }

        return $payloads;
    }

    private function imagePayload(string $path, ?string $mimeType): array
    {
        $fileContents = Storage::get($path);
        $base64 = base64_encode($fileContents);
        $mimeType = $mimeType ?: 'image/png';

        return [
            'type' => 'input_image',
            'image_url' => "data:{$mimeType};base64,{$base64}",
        ];
    }
}
