<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\FjpAttachment;
use App\Models\PermitDocument;
use App\Services\AuditLogger;
use App\Support\EventAccess;
use App\Support\ReportAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /** Allow-listed morph map for polymorphic uploads. */
    private const TYPES = [
        'event' => Event::class,
        'report' => EventReport::class,
    ];

    /** Categories accepted for an uploaded document. */
    private const CATEGORIES = ['photo', 'document', 'supporting', 'evidence', 'other'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'documentable_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'documentable_id' => ['required', 'integer'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        /** @var class-string<Model> $modelClass */
        $modelClass = self::TYPES[$data['documentable_type']];
        $model = $modelClass::findOrFail($data['documentable_id']);

        $this->authorizeUpload($request, $model);

        $file = $request->file('file');
        $path = $file->store('documents/'.$data['documentable_type']);

        $document = $model->documents()->create([
            'category' => $data['category'] ?? 'other',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLogger::log('UPLOAD_DOCUMENT', 'documents', $document, null, ['name' => $document->original_name]);

        return back()->with('success', 'Dokumen diunggah.');
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorizeDownload(request(), $document->documentable);

        return $this->streamDownload($document->path, $document->original_name);
    }

    public function downloadFjp(FjpAttachment $attachment): StreamedResponse
    {
        abort_unless(request()->user()->hasPermission('fjp.view'), 403);

        return $this->streamDownload($attachment->path, $attachment->original_name);
    }

    public function downloadPermit(PermitDocument $document): StreamedResponse
    {
        abort_unless(request()->user()->hasPermission('permits.view'), 403);

        return $this->streamDownload($document->path, $document->original_name);
    }

    public function destroy(Document $document)
    {
        $user = request()->user();

        // Only the uploader or a system administrator may delete a document.
        abort_unless($document->uploaded_by === $user->id || $user->isSystemAdmin(), 403);

        $this->authorizeDownload(request(), $document->documentable);

        AuditLogger::log('DELETE_DOCUMENT', 'documents', $document, ['name' => $document->original_name]);

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($document->path)) {
                Storage::disk($disk)->delete($document->path);
            }
        }
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    /**
     * Stream a stored document from the private disk, falling back to the
     * legacy public location for files uploaded before the private-storage
     * migration. The migration command copies everything to the private disk;
     * once complete the public fallback can be removed.
     */
    private function streamDownload(string $path, string $name): StreamedResponse
    {
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $name);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $name);
        }

        abort(404);
    }

    /**
     * Authorize uploading a document against its parent record.
     */
    private function authorizeUpload(Request $request, Model $model): void
    {
        $user = $request->user();

        if ($model instanceof Event) {
            abort_unless(
                $user->hasPermission('events.view') && EventAccess::allows($user, $model),
                403,
            );

            return;
        }

        if ($model instanceof EventReport) {
            abort_unless(
                $user->hasPermission('reports.view') && ReportAccess::allows($user, $model),
                403,
            );

            return;
        }

        abort(403);
    }

    /**
     * Authorize reading streamed bytes of a stored document by parent record.
     */
    private function authorizeDownload(Request $request, ?Model $parent): void
    {
        if ($parent === null) {
            abort(404);
        }

        $user = $request->user();

        if ($parent instanceof Event) {
            abort_unless(EventAccess::allows($user, $parent), 403);

            return;
        }

        if ($parent instanceof EventReport) {
            abort_unless(ReportAccess::allows($user, $parent), 403);

            return;
        }

        abort(403);
    }
}
