<?php

use Livewire\Volt\Component;
use App\Models\Group;

new class extends Component {
    public $name = '';
    public $parentId = '';

    public function with()
    {
        return [
            'groups' => Group::with('parent')->withCount('guests')->orderBy('name')->get(),
            'potentialParents' => Group::whereNull('parent_id')->orderBy('name')->get(),
        ];
    }

    public function createGroup()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'parentId' => 'nullable|exists:groups,id',
        ]);

        Group::create([
            'name' => $this->name,
            'parent_id' => $this->parentId ?: null,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name', 'parentId']);
    }

    public function deleteGroup(Group $group)
    {
        $group->delete();
    }
}
?>

<div class="bg-white dark:bg-stone-800 rounded-2xl p-6 shadow-sm border border-stone-200 dark:border-stone-700">
    <flux:heading size="lg" class="mb-4">Grupos / Familias</flux:heading>

    <form wire:submit="createGroup" class="space-y-3 mb-6">
        <div class="flex gap-2">
            <div class="flex-1">
                <flux:input wire:model="name" placeholder="Nombre del grupo..." dense />
            </div>
            <flux:button type="submit" icon="plus" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0" dense />
        </div>
        <flux:select wire:model="parentId" placeholder="Pertenecer a (opcional)..." dense>
            @foreach($potentialParents as $p)
                <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </form>

    <div class="space-y-3">
        @foreach($groups as $group)
            <div class="flex justify-between items-center p-3 rounded-xl bg-stone-50 dark:bg-stone-900 border border-stone-100 dark:border-stone-700 group">
                <div class="flex items-center gap-3">
                    <flux:icon.user-group class="w-4 h-4 text-stone-400" />
                    <div>
                        <p class="text-sm font-medium">
                            @if($group->parent)
                                <span class="text-xs text-stone-500 font-normal">{{ $group->parent->name }} > </span>
                            @endif
                            {{ $group->name }}
                        </p>
                        <p class="text-xs text-stone-500">{{ $group->guests_count }} invitados</p>
                    </div>
                </div>
                <flux:button wire:click="deleteGroup({{ $group->id }})" wire:confirm="¿Estás seguro?" icon="trash" size="sm" variant="ghost" class="opacity-0 group-hover:opacity-100 text-stone-400 hover:text-red-500 transition-all" />
            </div>
        @endforeach
    </div>
</div>
