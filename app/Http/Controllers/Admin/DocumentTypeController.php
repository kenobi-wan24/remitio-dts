<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-only. Types in use can't be deleted — deactivate them instead
 * (they disappear from the "new document" dropdown but old records keep them).
 */
class DocumentTypeController extends Controller
{
    public function index(): View
    {
        $types = DocumentType::query()
            ->withCount('documents')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.document-types.index', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $type = DocumentType::create([...$data, 'is_active' => true]);

        return back()->with('success', "Document type \"{$type->name}\" added.");
    }

    public function edit(DocumentType $documentType): View
    {
        return view('admin.document-types.edit', ['type' => $documentType]);
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->update($request->validate($this->rules($documentType)));

        return redirect()->route('admin.document-types.index')->with('success', 'Document type updated.');
    }

    public function toggle(DocumentType $documentType): RedirectResponse
    {
        $documentType->update(['is_active' => ! $documentType->is_active]);

        return back()->with('success', $documentType->is_active
            ? "\"{$documentType->name}\" is available again."
            : "\"{$documentType->name}\" is hidden from new documents. Existing documents keep it.");
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        if (Document::withTrashed()->where('document_type_id', $documentType->id)->exists()) {
            return back()->with('error', "\"{$documentType->name}\" is used by documents and cannot be deleted. Deactivate it instead.");
        }

        $documentType->delete();

        return back()->with('success', "Document type \"{$documentType->name}\" deleted.");
    }

    private function rules(?DocumentType $type = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('document_types', 'name')->ignore($type)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
