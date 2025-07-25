<x-menu-item title="Dashboard" icon="o-home" link="{{ route('dashboard') }}" route="dashboard"/>
@role('admin')
<x-menu-sub title="Management" icon="o-cpu-chip">
    <x-menu-item title="Users" icon="o-user-group" link="{{ route('admin.users') }}" route="admin.users"/>
    <x-menu-item title="Permissions" icon="o-pencil-square" link="{{ route('admin.permissions') }}" route="admin.permissions"/>
    <x-menu-item title="Childparts" icon="o-inbox-stack" link="{{ route('admin.childparts') }}" route="admin.childparts"/>
</x-menu-sub>
@endrole
@role('admin|inventory')
<x-menu-sub title="Inventory" icon="o-archive-box">
    <x-menu-item title="Dashboard" icon="o-sparkles" link="{{ route('inventory.dashboard') }}" route="inventory.dashboard"/>
    <x-menu-item title="List Request" icon="o-list-bullet" link="{{ route('inventory.list-requests') }}" route="inventory.list-requests"/>
    <x-menu-item title="Request" icon="o-list-bullet" link="{{ route('inventory.requests') }}" route="inventory.requests"/>
    <x-menu-item title="Receiving" icon="o-plus-circle" link="{{ route('inventory.receivings') }}" route="inventory.receivings"/>
    <x-menu-item title="Supply" icon="o-minus-circle" link="###"/>
</x-menu-sub>
@endrole