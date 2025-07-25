<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Validation\Rule;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

new
#[Layout('components.layouts.app')]
#[Title('Roles & Permissions')]
class extends Component
{
    use Toast;

    public string $tab = 'roles'; // roles | permissions
    public string $search = '';
    public bool $modal = false;
    public string $name = '';
    public ?int $edit_id = null;

    /* -------------------------------
     | Lifecycle & Data Preparation
     -------------------------------- */
    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'rows' => $this->rows(),
        ];
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'created_at', 'label' => 'Created', 'class' => 'w-32'],
        ];
    }

    public function rows()
    {
        return $this->getModel()::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
            // ->paginate(10); // optional pagination
    }

    /* -------------------------------
     | Actions
     -------------------------------- */
    public function clear(): void
    {
        $this->reset('search');
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    public function openCreateModal(): void
    {
        $this->reset('name', 'edit_id');
        $this->modal = true;
    }

    public function openEditModal(int $id): void
    {
        $item = $this->getModel()::findOrFail($id);
        $this->name = $item->name;
        $this->edit_id = $id;
        $this->modal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique($this->getTable(), 'name')->ignore($this->edit_id),
            ],
        ], [
            'name.required' => 'Name is required.',
            'name.unique' => ucfirst($this->tabLabel()) . ' with that name already exists.',
        ]);

        $model = $this->getModel();

        if ($this->edit_id) {
            $model::findOrFail($this->edit_id)->update($validated);
            $this->success($this->tabLabel() . ' updated.', position: 'toast-bottom');
        } else {
            $model::create($validated);
            $this->success($this->tabLabel() . ' created.', position: 'toast-bottom');
        }

        $this->modal = false;
        $this->reset('name', 'edit_id');
    }

    public function delete(int $id): void
    {
        $this->getModel()::destroy($id);
        $this->success($this->tabLabel() . " #$id deleted.", position: 'toast-bottom');
    }

    /* -------------------------------
     | Helpers
     -------------------------------- */
    protected function getModel(): string
    {
        return $this->tab === 'roles' ? Role::class : Permission::class;
    }

    protected function getTable(): string
    {
        return $this->tab === 'roles' ? 'roles' : 'permissions';
    }

    protected function tabLabel(): string
    {
        return ucfirst($this->tab === 'roles' ? 'role' : 'permission');
    }
}?>

<div>
    <!-- HEADER -->
    <x-header title="Roles & Permissions" icon="o-shield-check" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Add {{ ucfirst($tab) }}" @click="$wire.openCreateModal()" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- TABS -->
    <x-tabs wire:model="tab" class="mb-4">
        <x-tab name="roles" label="Roles" />
        <x-tab name="permissions" label="Permissions" />
    </x-tabs>

    <!-- TABLE -->
    <x-card shadow>
        <x-table :headers="$headers" :rows="$rows">
            @scope('actions', $row)
                <div class="flex space-x-2">
                    <x-button icon="o-pencil" @click="$wire.openEditModal({{ $row['id'] }})" class="btn-ghost btn-sm text-primary" />
                    <x-button icon="o-trash" wire:click="delete({{ $row['id'] }})" wire:confirm="Are you sure?" spinner class="btn-ghost btn-sm text-error" />
                </div>
            @endscope
        </x-table>
        {{-- {{ $rows->links() }} --}}
    </x-card>

    <!-- MODAL -->
    <x-modal wire:model="modal" title="{{ $edit_id ? 'Edit' : 'Create' }} {{ ucfirst($tab) }}" separator>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" icon="o-tag" placeholder="Enter name" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" class="btn-ghost" />
                <x-button label="Save" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
