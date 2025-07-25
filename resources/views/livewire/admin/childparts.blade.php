<?php

use App\Models\MasterChildpart;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Livewire\WithFileUploads;

new
#[Layout('components.layouts.app')]
#[Title('Child-Part Management')]
class extends Component {
    use Toast, WithFileUploads;

    // Listing
    public string $search = '';
    public bool $modal = false;
    public bool $importModal = false;
    public string $importStep = 'upload';

    // Filters
    public string $filter_part_number = '';
    public string $filter_model = '';
    public string $filter_variant = '';

    // Form
    public ?int $edit_id = null;
    public string $part_number = '';
    public string $part_name = '';
    public string $model = '';
    public string $variant = '';
    public string $homeline = '';
    public string $address = '';

    // Import
    #[Rule('required|max:10240')]
    public $file;
    public int $createdCount = 0;
    public int $updatedCount = 0;
    public array $errors = [];
    
    public int $perPage = 10;
    public $data;

    public function mount()
    {
        $this->data = MasterChildpart::all();
    }

    // Headers definition
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-8', 'hidden' => true],
            ['key' => 'part_number', 'label' => 'Part #', 'class' => 'w-32'],
            ['key' => 'part_name', 'label' => 'Name', 'class' => 'w-48'],
            ['key' => 'model', 'label' => 'Model'],
            ['key' => 'variant', 'label' => 'Variant'],
            ['key' => 'homeline', 'label' => 'Homeline'],
        ];
    }

    // Query with filters
    public function rows()
    {
        return MasterChildpart::query()
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('part_number', 'like', "{$this->search}%")
                        ->orWhere('part_name', 'like', "{$this->search}%");
                });
            })
            ->when($this->filter_part_number, fn($q) => $q
                ->where('part_number', 'like', "{$this->filter_part_number}%")
            )
            ->when($this->filter_model, fn($q) => $q
                ->where('model', 'like', "{$this->filter_model}%")
            )
            ->when($this->filter_variant, fn($q) => $q
                ->where('variant', 'like', "{$this->filter_variant}%")
            )
            ->orderBy('part_number')
            ->paginate($this->perPage);
    }

    // Data passing
    public function with(): array
    {
        return [
            'rows' => $this->rows(),
            'headers' => $this->headers(),
        ];
    }

    // Actions
    public function openCreateModal(): void
    {
        $this->reset('part_number', 'part_name', 'model', 'variant', 'homeline', 'address', 'edit_id');
        $this->modal = true;
    }

    public function openEditModal(int $id): void
    {
        $childPart = MasterChildpart::findOrFail($id);
        $this->edit_id = $childPart->id;
        $this->part_number = $childPart->part_number;
        $this->part_name = $childPart->part_name;
        $this->model = $childPart->model;
        $this->variant = $childPart->variant;
        $this->homeline = $childPart->homeline;
        $this->address = $childPart->address;
        $this->modal = true;
    }

    public function openImportModal(): void
    {
        $this->importModal = true;
        $this->importStep = 'upload';
        $this->reset('createdCount', 'updatedCount', 'errors', 'file');
    }

    public function delete(int $id): void
    {
        MasterChildpart::destroy($id);
        $this->success("Child-Part #$id deleted.");
    }

    public function save(): void
    {
        $validated = $this->validate([
            'part_number' => 'required|string|max:50|unique:master_childparts,part_number,'.$this->edit_id,
            'part_name'   => 'required|string|max:255',
            'model'       => 'nullable|string|max:100',
            'variant'     => 'nullable|string|max:100',
            'homeline'    => 'nullable|string|max:100',
            'address'     => 'nullable|string|max:255',
        ]);

        if ($this->edit_id) {
            MasterChildpart::find($this->edit_id)->update($validated);
            $this->success('Child-Part updated.');
        } else {
            MasterChildpart::create($validated);
            $this->success('Child-Part created.');
        }

        $this->modal = false;
        $this->reset('part_number', 'part_name', 'model', 'variant', 'homeline', 'address', 'edit_id');
    }

    public function importExcel(): void
    {
        $this->validate();
        
        if ($this->file->extension() !== 'xlsx') {
            $this->error('Only XLSX files are allowed');
            return;
        }

        try {
            $spreadsheet = IOFactory::load($this->file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Validate headers
            $headers = array_map('trim', $rows[0]);
            $expectedHeaders = ['part_number', 'part_name', 'model', 'variant', 'homeline', 'address'];
            if (array_diff($expectedHeaders, $headers) !== []) {
                $this->error('Invalid file format. Required columns: ' . implode(', ', $expectedHeaders));
                return;
            }

            array_shift($rows); // Remove header row
            
            foreach ($rows as $index => $row) {
                $rowData = array_combine($headers, $row);
                $rowData = array_map('trim', $rowData);
                
                if (empty($rowData['part_number']) || empty($rowData['part_name'])) {
                    $this->errors[] = "Row " . ($index + 2) . ": Missing required fields";
                    continue;
                }

                try {
                    $existing = MasterChildpart::where('part_number', $rowData['part_number'])->first();
                    
                    $data = [
                        'part_number' => $rowData['part_number'],
                        'part_name'   => $rowData['part_name'],
                        'model'       => $rowData['model'] ?? null,
                        'variant'     => $rowData['variant'] ?? null,
                        'homeline'    => $rowData['homeline'] ?? null,
                        'address'     => $rowData['address'] ?? null,
                    ];

                    if ($existing) {
                        $existing->update($data);
                        $this->updatedCount++;
                    } else {
                        MasterChildpart::create($data);
                        $this->createdCount++;
                    }
                } catch (\Exception $e) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            $this->importStep = 'summary';
            
        } catch (\Exception $e) {
            $this->error("Error processing file: " . $e->getMessage());
        }
    }

    public function resetFilters(): void
    {
        $this->reset('filter_part_number', 'filter_model', 'filter_variant');
    }
};
?>

<div class="max-w-6xl mx-auto px-4">
    <!-- Statistics Section -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-stat 
            title="Total Parts" 
            value="{{ $data->count() }}" 
            icon="o-cube" 
            tooltip="Total Child-Parts" 
            color="text-primary" 
        />
        
        <x-stat 
            title="Unique Models" 
            value="{{ $data->pluck('model')->unique()->count() }}" 
            icon="o-tag" 
            description="Distinct models" 
            class="text-blue-500" 
        />
        
        <x-stat 
            title="Unique Variants" 
            value="{{ $data->pluck('variant')->unique()->count() }}" 
            icon="o-variable" 
            description="Distinct variants" 
            class="text-green-500" 
        />
        
        <x-stat 
            title="Unique Homelines" 
            value="{{ $data->pluck('homeline')->unique()->count() }}" 
            icon="o-home" 
            description="Distinct homelines" 
            class="text-orange-500" 
        />
    </div>

    <!-- Header with Search -->
    <x-header title="Child-Parts" icon="o-cube" separator>
        <x-slot:middle class="!justify-end">
            <x-input 
                placeholder="Search…" 
                wire:model.live.debounce="search" 
                clearable 
                icon="o-magnifying-glass" 
                class="w-full max-w-md"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button 
                label="Add" 
                @click="$wire.openCreateModal()" 
                icon="o-plus" 
                class="btn-primary hover:scale-105 transition-transform" 
            />
            <x-button 
                label="Import" 
                @click="$wire.openImportModal()" 
                icon="o-arrow-up-tray" 
                class="btn-secondary hover:scale-105 transition-transform" 
            />
        </x-slot:actions>
    </x-header>

    <!-- Filter Card -->
    <x-card shadow class="mb-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Filters</h3>
            <x-button 
                label="Reset" 
                @click="$wire.resetFilters()" 
                class="btn-ghost btn-sm" 
                icon="o-arrow-path"
            />
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-input 
                label="Part Number" 
                wire:model.live.debounce="filter_part_number" 
                placeholder="Filter by part number..."
                class="w-full"
            />
            
            <x-input 
                label="Model" 
                wire:model.live.debounce="filter_model" 
                placeholder="Filter by model..."
                class="w-full"
            />
            
            <x-input 
                label="Variant" 
                wire:model.live.debounce="filter_variant" 
                placeholder="Filter by variant..."
                class="w-full"
            />
        </div>
    </x-card>

    <!-- Data Table -->
    <x-card shadow>
        <div class="overflow-x-auto">
            <x-table 
                :headers="$headers" 
                :rows="$rows"
                with-pagination
                per-page="perPage"
                :per-page-values="[10, 25, 50, 100]"
                class="min-w-full divide-y divide-gray-200"
            >
                @scope('actions', $row)
                    <div class="flex gap-2">
                        <x-button 
                            icon="o-pencil" 
                            @click="$wire.openEditModal({{ $row['id'] }})" 
                            class="btn-ghost btn-sm text-primary hover:text-blue-700" 
                        />
                        <x-button 
                            icon="o-trash"  
                            wire:click="delete({{ $row['id'] }})" 
                            wire:confirm="Are you sure?" 
                            spinner 
                            class="btn-ghost btn-sm text-error hover:text-red-700" 
                        />
                    </div>
                @endscope
            </x-table>
        </div>
    </x-card>

    <!-- Create/Edit Drawer -->
    <x-drawer
        wire:model="modal"
        title="{{ $edit_id ? 'Edit' : 'Create' }} Child-Part"
        subtitle="Child-Part Management"
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
    >
        <x-form wire:submit="save" class="space-y-4">
            <x-input 
                label="Part #"   
                wire:model="part_number" 
                placeholder="e.g. 123-ABC"
                required
                class="w-full"
            />
            <x-input 
                label="Name"     
                wire:model="part_name"   
                placeholder="e.g. Housing Top"
                required
                class="w-full"
            />
            <x-input 
                label="Model"    
                wire:model="model"       
                placeholder="e.g. X200"
                class="w-full"
            />
            <x-input 
                label="Variant"  
                wire:model="variant"     
                placeholder="e.g. EU"
                class="w-full"
            />
            <x-input 
                label="Homeline" 
                wire:model="homeline"    
                placeholder="e.g. A1"
                class="w-full"
            />
            <x-textarea 
                label="Address" 
                wire:model="address" 
                rows="2" 
                placeholder="Optional location / address"
                class="w-full"
            />
            <x-slot:actions>
                <x-button 
                    label="Cancel" 
                    @click="$wire.modal = false" 
                    class="btn-ghost hover:bg-gray-100" 
                />
                <x-button 
                    label="Save" 
                    type="submit" 
                    icon="o-check" 
                    class="btn-primary hover:scale-105 transition-transform" 
                    spinner="save" 
                />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    <!-- Import Drawer -->
    <x-drawer
        wire:model="importModal"
        title="Import Child-Parts"
        subtitle="{{ $importStep === 'summary' ? 'Summary' : 'Upload File' }}"
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
    >
        @if($importStep === 'upload')
            <x-form wire:submit="importExcel" class="space-y-4">
                <x-file 
                    wire:model="file" 
                    label="Excel File" 
                    hint="Only .xlsx files" 
                    accept=".xlsx" 
                />
                <x-slot:actions>
                    <x-button 
                        label="Cancel" 
                        @click="$wire.importModal = false" 
                        class="btn-ghost" 
                    />
                    <x-button 
                        label="Import" 
                        type="submit" 
                        icon="o-arrow-up-tray" 
                        class="btn-primary" 
                        spinner="importExcel" 
                    />
                </x-slot:actions>
            </x-form>
        @else
            <div class="p-4 space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-bold">Import Results</h3>
                    <x-button 
                        icon="o-x-mark" 
                        @click="$wire.importModal = false" 
                        class="btn-ghost btn-sm" 
                    />
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg">
                    <p class="text-green-700">Created: <span class="font-bold">{{ $createdCount }}</span></p>
                    <p class="text-blue-700">Updated: <span class="font-bold">{{ $updatedCount }}</span></p>
                </div>

                @if(count($errors) > 0)
                    <div class="bg-red-50 p-4 rounded-lg">
                        <h4 class="text-red-700 font-bold mb-2">Errors:</h4>
                        <ul>
                            @foreach($errors as $error)
                                <li class="text-red-600">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex justify-end">
                    <x-button 
                        label="Close" 
                        @click="$wire.importModal = false" 
                        class="btn-primary" 
                    />
                </div>
            </div>
        @endif
    </x-drawer>
</div>