<?php

use Livewire\Volt\Component;
use App\Models\Category;
use App\Models\InspirationItem;

new class extends Component {
    public $name = '';
    public $color = 'sage';
    public $type = 'guest'; // 'guest', 'inspiration', 'vendor', 'sponsor', 'task'

    public $editingCategory = null;
    public $editName = '';
    public $editColor = '';

    public function with()
    {
        return [
            'categories' => Category::where('type', $this->type)
                ->withCount(['guests', 'inspirationItems', 'vendors', 'sponsors', 'tasks'])
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get(),
            'availableColors' => ['sage', 'gold', 'sky', 'rose', 'zinc', 'amber', 'emerald'],
        ];
    }

    public function createCategory()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string',
        ]);

        Category::create([
            'name' => $this->name,
            'color' => $this->color,
            'type' => $this->type,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name', 'color']);
        $this->dispatch('category-created');
    }

    public function editCategory(Category $category)
    {
        $this->editingCategory = $category;
        $this->editName = $category->name;
        $this->editColor = $category->color;
        $this->js("Flux.modal('edit-category-modal').show()");
    }

    public function updateCategory()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editColor' => 'required|string',
        ]);

        $this->editingCategory->update([
            'name' => $this->editName,
            'color' => $this->editColor,
        ]);

        $this->reset(['editingCategory', 'editName', 'editColor']);
        $this->js("Flux.modal('edit-category-modal').close()");
    }

    public function deleteCategory(Category $category)
    {
        if ($category->is_default) {
            return;
        }

        $default = Category::getDefault($this->type, auth()->id());

        if ($this->type === 'guest') {
            foreach ($category->guests as $guest) {
                $guest->categories()->detach($category->id);
                if ($guest->categories()->count() === 0) {
                    $guest->categories()->attach($default->id);
                }
            }
        } elseif ($this->type === 'inspiration') {
            InspirationItem::where('category_id', $category->id)->update(['category_id' => $default->id]);
        } elseif ($this->type === 'vendor') {
            foreach ($category->vendors as $vendor) {
                $vendor->categories()->detach($category->id);
                if ($vendor->categories()->count() === 0) {
                    $vendor->categories()->attach($default->id);
                }
            }
        } elseif ($this->type === 'sponsor') {
            \App\Models\Sponsor::where('role_category_id', $category->id)->update(['role_category_id' => $default->id]);
        } elseif ($this->type === 'task') {
            \App\Models\Task::where('category_id', $category->id)->update(['category_id' => $default->id]);
        }

        $category->delete();
    }
}
?>

<div class="bg-white dark:bg-stone-800 rounded-2xl p-6 shadow-sm border border-stone-200 dark:border-stone-700">
    <div class="flex flex-col gap-4 mb-6">
        <flux:heading size="lg">Categorías</flux:heading>
        <div class="overflow-x-auto no-scrollbar pb-1">
            <flux:radio.group wire:model.live="type" variant="segmented" size="sm" class="flex-nowrap min-w-max">
                <flux:radio value="guest" label="Invitados" />
                <flux:radio value="inspiration" label="Inspiración" />
                <flux:radio value="vendor" label="Proveedores" />
                <flux:radio value="sponsor" label="Padrinos" />
                <flux:radio value="task" label="Pendientes" />
            </flux:radio.group>
        </div>
    </div>

    <form wire:submit="createCategory" class="flex gap-2 mb-6">
        <div class="flex-1">
            <flux:input wire:model="name" placeholder="Nueva categoría..." dense />
        </div>
        <flux:select wire:model="color" dense class="w-24">
            @foreach($availableColors as $c)
                <flux:select.option value="{{ $c }}">{{ Str::title($c) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:button type="submit" icon="plus" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0" dense />
    </form>

    <div class="space-y-2">
        @foreach($categories as $category)
            <div class="flex justify-between items-center p-3 rounded-xl bg-stone-50 dark:bg-stone-900 border border-stone-100 dark:border-stone-700 group hover:border-sage-200 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-{{ $category->color }}-500 shadow-sm shadow-{{ $category->color }}-500/50"></div>
                    <div>
                        <p class="text-sm font-semibold text-stone-700 dark:text-stone-200 flex items-center gap-2">
                            {{ $category->name }}
                            @if($category->is_default)
                                <flux:badge size="sm" variant="subtle" color="zinc" class="text-[9px] uppercase tracking-tighter">Default</flux:badge>
                            @endif
                        </p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[10px] text-stone-500 font-medium">
                                @if($type === 'guest')
                                    {{ $category->guests_count }} invitados
                                @elseif($type === 'inspiration')
                                    {{ $category->inspiration_items_count }} ideas
                                @elseif($type === 'vendor')
                                    {{ $category->vendors_count }} proveedores
                                @elseif($type === 'sponsor')
                                    {{ $category->sponsors_count }} roles
                                @elseif($type === 'task')
                                    {{ $category->tasks_count }} tareas
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-all">
                    <flux:button wire:click="editCategory({{ $category->id }})" icon="pencil" size="sm" variant="ghost" class="text-stone-400 hover:text-sage-500" />
                    @if(!$category->is_default)
                        <flux:button wire:click="deleteCategory({{ $category->id }})" wire:confirm="¿Estás seguro? Los elementos se moverán a 'General'." icon="trash" size="sm" variant="ghost" class="text-stone-400 hover:text-red-500" />
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <flux:modal name="edit-category-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Categoría</flux:heading>
                <flux:subheading>Cambia el nombre o color de la categoría.</flux:subheading>
            </div>

            <flux:input wire:model="editName" label="Nombre" />

            <flux:select wire:model="editColor" label="Color">
                @foreach($availableColors as $c)
                    <flux:select.option value="{{ $c }}">{{ Str::title($c) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button wire:click="updateCategory" variant="primary">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
