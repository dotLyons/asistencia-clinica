<?php

namespace App\Livewire\Admin;

use App\Models\Section;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Secciones')]
class Sections extends Component
{
    use WithPagination;

    public string $name = '';

    public string $search = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('administrador'), 403);
    }

    /**
     * Reset pagination when searching.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Validation rules.
     *
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:sections,name',
        ];
    }

    /**
     * Create a new section.
     */
    public function createSection(): void
    {
        $validated = $this->validate();

        Section::create($validated);

        $this->reset('name');

        Flux::toast(variant: 'success', text: __('Sección creada con éxito.'));
    }

    /**
     * Delete a section.
     */
    public function deleteSection(int $id): void
    {
        abort_unless(auth()->user()->hasRole('administrador'), 403);

        $section = Section::findOrFail($id);
        $section->delete();

        Flux::toast(variant: 'success', text: __('Sección eliminada con éxito.'));
    }

    /**
     * Generate and download the QR code for a section in JPG 1000x1000 format.
     */
    public function downloadQr(int $id)
    {
        abort_unless(auth()->user()->hasRole('administrador'), 403);

        $section = Section::findOrFail($id);

        $renderer = new \BaconQrCode\Renderer\GDLibRenderer(1000, 4, 'jpg', 90);
        $writer = new \BaconQrCode\Writer($renderer);

        $url = route('attendance.scan', ['section' => $section->uuid]);
        $qrData = $writer->writeString($url);

        $filename = \Illuminate\Support\Str::slug($section->name) ?: 'qr';

        return response()->streamDownload(function () use ($qrData) {
            echo $qrData;
        }, "qr-{$filename}.jpg", [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => "attachment; filename=\"qr-{$filename}.jpg\"",
        ]);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        $sections = Section::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->withCount('attendances')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.sections', [
            'sections' => $sections,
        ]);
    }
}
