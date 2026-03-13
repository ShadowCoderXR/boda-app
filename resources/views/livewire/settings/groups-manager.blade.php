<?php

use Livewire\Volt\Component;
use App\Models\Subgroup;
use App\Models\Group;

new class extends Component {
    public $name = '';

    public $editingGroup = null;
    public $editName = '';

    public function with()
    {
        return [
            'groups' => Subgroup::withCount('groups')
                ->where('user_id', auth()->id())
                ->orderBy('name')
                ->get(),
        ];
    }

    public function createGroup()
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        Subgroup::create([
            'name' => $this->name,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name']);
    }

    public function editGroup(Subgroup $group)
    {
        $this->editingGroup = $group;
        $this->editName = $group->name;
        $this->js("Flux.modal('edit-group-modal').show()");
    }

    public function updateGroup()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
        ]);

        $this->editingGroup->update([
            'name' => $this->editName,
        ]);

        $this->reset(['editingGroup', 'editName']);
        $this->js("Flux.modal('edit-group-modal').close()");
    }

    public function deleteGroup(Subgroup $group)
    {
        // Clear parent_id for any children
        Group::where('subgroup_id', $group->id)->update(['subgroup_id' => null]);

        $group->delete();
    }
}
?>

<div class="bg-white dark:bg-stone-800 rounded-2xl p-6 shadow-sm border border-stone-200 dark:border-stone-700">
    <flux:heading size="lg" class="mb-4">Subgrupos y Agrupaciones</flux:heading>
    <flux:subheading class="mb-4 text-xs">Administra las subdivisiones de tus grupos principales (familias, amigos, etc.).</flux:subheading>

    <form wire:submit="createGroup" class="space-y-3 mb-6">
        <div class="flex gap-2">
            <div class="flex-1">
                <flux:input wire:model="name" placeholder="Nombre de la subcategoría/subgrupo..." dense />
            </div>
            <flux:button type="submit" icon="plus" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0" dense />
        </div>
        <p class="text-[10px] text-stone-400 italic">Nota: Los grupos principales se crean automáticamente al agregar invitados.</p>
    </form>

    <div class="space-y-3">
        @foreach($groups as $group)
            <div class="flex justify-between items-center p-3 rounded-xl bg-stone-50 dark:bg-stone-900 border border-stone-100 dark:border-stone-700 group">
                <div class="flex items-center gap-3">
                    <flux:icon.user-group class="w-4 h-4 text-stone-400" />
                    <div>
                        <p class="text-sm font-medium">
                            {{ $group->name }}
                        </p>
                        <p class="text-xs text-stone-500">{{ $group->groups_count }} hogares / grupos internos</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-all">
                    <flux:button wire:click="editGroup({{ $group->id }})" icon="pencil" size="sm" variant="ghost" class="text-stone-400 hover:text-sage-500" />
                    <flux:button wire:click="deleteGroup({{ $group->id }})" wire:confirm="¿Estás seguro? Los hogares internos pasarán a ser libres." icon="trash" size="sm" variant="ghost" class="text-stone-400 hover:text-red-500 transition-all" />
                </div>
            </div>
        @endforeach
    </div>

    <flux:modal name="edit-group-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Grupo</flux:heading>
                <flux:subheading>Cambia el nombre o la jerarquía del grupo.</flux:subheading>
            </div>

            <flux:input wire:model="editName" label="Nombre" />

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button wire:click="updateGroup" variant="primary">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
