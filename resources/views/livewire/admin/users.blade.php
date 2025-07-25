<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

new
#[Layout('components.layouts.app')]
#[Title('User Management')]
class extends Component {
    use Toast;

    /* existing */
    public string $search = '';
    public bool $drawer = false;
    public bool $modal = false;
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    /* user fields */
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public ?int $edit_id = null;

    public ?int $selectedRole = null;
    public ?int $selectedPermission = null;
    /* NEW: collections for options */
    public function with(): array
    {
        return [
            'users' => $this->users(),
            'headers' => $this->headers(),
            'allRoles' => Role::orderBy('name')->get(),
            'allPermissions' => Permission::orderBy('name')->get(),
        ];
    }

    /* clear */
    public function clear(): void
    {
        $this->reset('search');
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    /* delete */
    public function delete($id): void
    {
        User::destroy($id);
        $this->success("User #$id deleted.", position: 'toast-bottom');
    }

    /* open create */
    public function openCreateModal(): void
    {
        $this->reset(
            'name',
            'email',
            'password',
            'password_confirmation',
            'edit_id',
            'selectedRole',
            'selectedPermission'
        );
        $this->modal = true;
    }

    /* open edit */
    public function openEditModal($id): void
    {
        $user = User::findOrFail($id);

        $this->name  = $user->name;
        $this->email = $user->email;
        $this->edit_id = $user->id;
        $this->reset('password', 'password_confirmation');

        /* load current roles/permissions */
        $this->selectedRole       = $user->roles()->first()?->id;
        $this->selectedPermission = $user->permissions()->first()?->id;

        $this->modal = true;
    }

    /* save / update */
    public function save(): void
    {
        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->edit_id ? ',' . $this->edit_id : ''),
            'selectedRole'     => 'exists:roles,id',
            'selectedPermission' => 'exists:permissions,id',
        ];

        if (!$this->edit_id) {
            $rules['password'] = 'required|confirmed|min:8';
        } else {
            $rules['password'] = 'nullable|confirmed|min:8';
        }

        $validated = $this->validate($rules);

        /* user data */
        $userData = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ];
        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        if ($this->edit_id) {
            $user = User::findOrFail($this->edit_id);
            $user->update($userData);
            $this->success('User updated.', position: 'toast-bottom');
        } else {
            $user = User::create($userData);
            $this->success('User created.', position: 'toast-bottom');
        }

        $user->syncRoles([$this->selectedRole]);
        $user->syncPermissions([$this->selectedPermission]);
        
        $this->modal = false;
        $this->reset('name', 'email', 'password', 'password_confirmation', 'edit_id', 'selectedRole', 'selectedPermission');
    }

    /* headers now include roles & permissions columns */
    public function headers(): array
    {
        return [
            ['key' => 'id',    'label' => '#', 'class' => 'w-1'],
            ['key' => 'name',  'label' => 'Name', 'class' => 'w-48'],
            ['key' => 'email', 'label' => 'E-mail'],
            ['key' => 'roles', 'label' => 'Roles', 'sortable' => false],
            ['key' => 'permissions', 'label' => 'Direct Permissions', 'sortable' => false],
        ];
    }

    /* query with search */
    public function users()
    {
        return User::query()
            ->with(['roles', 'permissions']) // eager load
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get();
    }
};
?>

<div>
    <!-- HEADER -->
    <x-header title="User Management" icon="o-user-group" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Filters" @click="$wire.drawer = true" responsive icon="o-funnel" />
            <x-button label="Add User" @click="$wire.openCreateModal()" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE -->
    <x-card shadow>
        <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy">
            @scope('cell_roles', $user)
                @foreach($user['roles'] as $role)
                    <x-badge :value="$role['name']" class="badge-sm" />
                @endforeach
            @endscope
            @scope('cell_permissions', $user)
                @foreach($user['permissions'] as $perm)
                    <x-badge :value="$perm['name']" class="badge-sm badge-ghost" />
                @endforeach
            @endscope
            @scope('actions', $user)
                <div class="flex space-x-2">
                    <x-button icon="o-pencil" @click="$wire.openEditModal({{ $user['id'] }})" class="btn-ghost btn-sm text-primary" />
                    <x-button icon="o-trash" wire:click="delete({{ $user['id'] }})" wire:confirm="Are you sure?" spinner class="btn-ghost btn-sm text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <!-- MODAL -->
    <x-modal wire:model="modal" title="{{ $edit_id ? 'Edit User' : 'Create User' }}" separator>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" icon="o-user" placeholder="Enter name" />
            <x-input label="Email" wire:model="email" icon="o-envelope" placeholder="Enter email" type="email" />

            <x-select label="Role"
                    wire:model="selectedRole"
                    :options="$allRoles"
                    option-label="name"
                    option-value="id"
                    placeholder="Choose a role" />

            <x-select label="Direct Permission"
                    wire:model="selectedPermission"
                    :options="$allPermissions"
                    option-label="name"
                    option-value="id"
                    placeholder="Choose a permission" />

            <x-input label="Password" wire:model="password" icon="o-key" placeholder="Enter password" type="password" />
            @if(!$edit_id)
                <x-input label="Confirm Password" wire:model="password_confirmation" icon="o-key" placeholder="Confirm password" type="password" />
            @endif

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" class="btn-ghost" />
                <x-button label="Save" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- FILTER DRAWER (unchanged) -->
    <x-drawer wire:model="drawer" title="Filters" right separator with-close-button class="lg:w-1/3">
        <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" @keydown.enter="$wire.drawer = false" />
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>