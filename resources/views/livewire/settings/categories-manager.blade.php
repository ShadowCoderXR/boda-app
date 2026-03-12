<?php

use Livewire\Volt\Component;
use App\Models\Category;

new class extends Component {
    public $name = '';
    public $color = 'sage';

    public function with()
    {
        return [
            'categories' => Category::withCount('guests')->orderBy('name')->get(),
            'availableColors' => ['sage', 'gold', 'sky', 'rose', 'zinc', 'amber', 'emerald'],
        ];
    }

    public function createCategory()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'color' => 'required|string|in:sage,gold,sky,rose,zinc,amber,emerald',
        ]);

        Category::create([
            'name' => $this->name,
            'color' => $this->color,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name', 'color']);
    }

    public function deleteCategory(Category $category)
    {
        $category->delete();
    }
}
?>

<div class="bg-white dark:bg-stone-800 rounded-2xl p-6 shadow-sm border border-stone-200 dark:border-stone-700">
    <flux:heading size="lg" class="mb-4">Categorías de Invitados</flux:heading>

    <form wire:submit="createCategory" class="flex gap-2 mb-6">
        <div class="flex-1">
            <flux:input wire:model="name" placeholder="Nueva categoría..." dense />
        </div>
        <flux:select wire:model="color" dense class="w-24">
            <flux:select.option value="sage">Sage</flux:select.option>
            <flux:select.option value="gold">Gold</flux:select.option>
            <flux:select.option value="sky">Sky</flux:select.option>
            <flux:select.option value="rose">Rose</flux:select.option>
        </flux:select>
        <flux:button type="submit" icon="plus" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0" dense />
    </form>

    <div class="space-y-3">
        @foreach($categories as $category)
            <div class="flex justify-between items-center p-3 rounded-xl bg-stone-50 dark:bg-stone-900 border border-stone-100 dark:border-stone-700 group">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-{{ $category->color }}-500"></div>
                    <div>
                        <p class="text-sm font-medium">{{ $category->name }}</p>
                        <p class="text-xs text-stone-500">{{ $category->guests_count }} invitados</p>
                    </div>
                </div>
                <flux:button wire:click="deleteCategory({{ $category->id }})" wire:confirm="¿Estás seguro?" icon="trash" size="sm" variant="ghost" class="opacity-0 group-hover:opacity-100 text-stone-400 hover:text-red-500 transition-all" />
            </div>
        @endforeach
    </div>
</div>
