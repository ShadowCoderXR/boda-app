<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\InspirationItem;

new class extends Component {
    use WithFileUploads;

    public $selectedCategory = '';
    public $categories = []; // Computed dynamically from DB

    public $newImage;

    // Modal Form State
    public $newType = 'image';
    public $newCategory = '';
    public $newDescription = '';
    public $newLink = '';
    public $newColors = [];
    public $currentColor = '#A3B18A';

    // Editing State
    public $editingItem = null;
    public $editType = 'image';
    public $editCategory = '';
    public $editDescription = '';
    public $editLink = '';
    public $editColors = [];
    public $editImage;

    public function with()
    {
        // Fetch all categories used so far for the filter nav
        $this->categories = InspirationItem::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();

        $query = InspirationItem::orderBy('created_at', 'desc');

        if (!empty($this->selectedCategory)) {
            $query->where('category', $this->selectedCategory);
        }

        return [
            'items' => $query->get()
        ];
    }

    public function addColor()
    {
        if (count($this->newColors) < 5) {
            $this->newColors[] = $this->currentColor;
        }
    }

    public function removeColor($index)
    {
        unset($this->newColors[$index]);
        $this->newColors = array_values($this->newColors);
    }

    public function toggleFavorite(InspirationItem $item)
    {
        $item->update(['is_favorite' => !$item->is_favorite]);
    }

    public function deleteItem(InspirationItem $item)
    {
        if ($item->user_id === auth()->id() || auth()->user()->role === 'admin') {
            if ($item->type === 'image') {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $item->content));
            }
            $item->delete();
        }
    }

    public function editItem(InspirationItem $item)
    {
        $this->editingItem = $item;
        $this->editType = $item->type;
        $this->editCategory = $item->category;
        $this->editDescription = $item->description;
        
        if ($item->type === 'color') {
            $this->editColors = json_decode($item->content, true) ?: [$item->content];
        } elseif ($item->type === 'link') {
            $this->editLink = $item->content;
        }

        $this->js("Flux.modal('edit-idea').show()");
    }

    public function updateIdea()
    {
        $this->validate([
            'editType' => 'required|in:image,color,link',
            'editCategory' => 'nullable|string|max:100',
            'editDescription' => 'nullable|string|max:500',
            'editLink' => 'required_if:editType,link|nullable|url',
            'editColors' => 'exclude_unless:editType,color|array|min:1',
            'editImage' => 'nullable|image|max:10240',
        ]);

        $content = $this->editingItem->content;

        if ($this->editType === 'color') {
            $content = json_encode($this->editColors);
        } elseif ($this->editType === 'link') {
            $content = $this->editLink;
        } elseif ($this->editType === 'image' && $this->editImage) {
            // Delete old image if exists
            if ($this->editingItem->type === 'image') {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $this->editingItem->content));
            }
            $path = $this->editImage->store('inspiration', 'public');
            $content = '/storage/' . $path;
        }

        $this->editingItem->update([
            'type' => $this->editType,
            'category' => $this->editCategory ?: 'Sin Categoría',
            'content' => $content,
            'description' => $this->editDescription,
        ]);

        $this->reset(['editingItem', 'editType', 'editCategory', 'editDescription', 'editLink', 'editColors', 'editImage']);
        $this->js("Flux.modal('edit-idea').close()");
    }

    public function saveIdea()
    {
        $this->validate([
            'newType' => 'required|in:image,color,link',
            'newCategory' => 'nullable|string|max:100',
            'newDescription' => 'nullable|string|max:500',
            // Conditional validations
            'newLink' => 'required_if:newType,link|nullable|url',
            'newColors' => 'exclude_unless:newType,color|array|min:1',
            'newImage' => 'required_if:newType,image|nullable|image|max:10240',
        ]);

        $content = '';
        $effectiveCategory = $this->newCategory ?: 'Sin Categoría';

        if ($this->newType === 'color') {
            $content = json_encode($this->newColors);
        } elseif ($this->newType === 'link') {
            $content = $this->newLink;
        } elseif ($this->newType === 'image' && $this->newImage) {
            $path = $this->newImage->store('inspiration', 'public');
            $content = '/storage/' . $path;
        }

        InspirationItem::create([
            'type' => $this->newType,
            'category' => $effectiveCategory,
            'content' => $content,
            'description' => $this->newDescription,
            'user_id' => auth()->id(),
        ]);

        // Reset form
        $this->reset(['newType', 'newCategory', 'newDescription', 'newLink', 'newColors', 'newImage']);
        
        $this->js("Flux.modal('new-idea').close()");
    }
}
?>

<div>
    {{-- Filtering & Actions --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" wire:click="$set('selectedCategory', '')" 
                variant="{{ $selectedCategory === '' ? 'primary' : 'ghost' }}"
                class="{{ $selectedCategory === '' ? 'bg-stone-800 text-white hover:bg-stone-900 border-0' : 'text-stone-600' }}">Todos</flux:button>
            
            @foreach($categories as $cat)
                <flux:button size="sm" wire:click="$set('selectedCategory', '{{ $cat }}')"
                    variant="{{ $selectedCategory === $cat ? 'primary' : 'ghost' }}"
                    class="{{ $selectedCategory === $cat ? 'bg-stone-800 text-white hover:bg-stone-900 border-0' : 'text-stone-600' }}">
                    {{ $cat }}
                </flux:button>
            @endforeach
        </div>

        <flux:modal.trigger name="new-idea">
            <flux:button icon="plus" class="bg-sage-600 hover:bg-sage-700 text-white shrink-0 shadow-sm border-0">Nueva Idea</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Masonry Grid --}}
    <div class="columns-2 md:columns-3 lg:columns-4 gap-4 space-y-4">
        @forelse($items as $item)
            <div class="break-inside-avoid relative group rounded-2xl overflow-hidden shadow-sm border border-stone-100 dark:border-stone-800 bg-white dark:bg-zinc-900">
                
                {{-- Content Type Rendering --}}
                @if($item->type === 'image')
                    <img src="{{ $item->content }}" alt="{{ $item->description }}" class="w-full h-auto object-cover">
                @elseif($item->type === 'color')
                    @php
                        $colors = json_decode($item->content, true) ?: [$item->content];
                    @endphp
                    <div class="w-full flex">
                        @foreach($colors as $color)
                            <div class="flex-1 min-h-[120px]" style="background-color: {{ $color }}"></div>
                        @endforeach
                    </div>
                @elseif($item->type === 'link')
                    <a href="{{ $item->content }}" target="_blank" class="block w-full aspect-[4/3] bg-stone-50 dark:bg-stone-800 hover:bg-stone-100 transition-colors flex flex-col items-center justify-center p-6 text-center">
                        @if(Str::contains($item->content, ['youtube.com', 'youtu.be']))
                            <flux:icon.play class="w-10 h-10 text-red-500 mb-2" />
                        @elseif(Str::contains($item->content, 'pinterest.com'))
                            <flux:icon.hashtag class="w-10 h-10 text-red-600 mb-2" />
                        @else
                            <flux:icon.link class="w-10 h-10 text-stone-400 mb-2" />
                        @endif
                        <span class="text-sm font-medium text-stone-600 dark:text-stone-300 break-all line-clamp-2 uppercase tracking-wide text-xs">{{ parse_url($item->content, PHP_URL_HOST) }}</span>
                        <span class="text-xs text-stone-400 mt-1 truncate w-full">{{ $item->content }}</span>
                    </a>
                @endif

                {{-- Overlay & Info --}}
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-4 translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300">
                    <div class="flex justify-between items-end gap-2">
                        <div class="text-white min-w-0 flex-1">
                            @if($item->description)
                                <p class="text-sm font-medium line-clamp-2 leading-tight drop-shadow-md mb-2">{{ $item->description }}</p>
                            @endif
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] bg-white/20 backdrop-blur-md px-2 py-0.5 rounded-full drop-shadow-md uppercase tracking-wider font-bold">{{ $item->category }}</span>
                                <button wire:click="toggleFavorite({{ $item->id }})" class="shrink-0 transition-transform active:scale-95">
                                    @if($item->is_favorite)
                                        <flux:icon.heart class="w-5 h-5 fill-red-500 text-red-500" />
                                    @else
                                        <flux:icon.heart class="w-5 h-5 text-white hover:fill-white/30" />
                                    @endif
                                </button>
                            </div>
                        </div>
                        
                        {{-- Edit/Delete Button --}}
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="text-white hover:bg-white/20 p-1 rounded-full border-0" />
                            <flux:menu>
                                <flux:menu.item icon="pencil" wire:click="editItem({{ $item->id }})">Editar</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="deleteItem({{ $item->id }})" wire:confirm="¿Estás seguro de eliminar esta idea?">Eliminar</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
                
                {{-- Always show heart if favorite, even without hover --}}
                @if($item->is_favorite)
                    <div class="absolute top-3 right-3 opacity-100 group-hover:opacity-0 transition-opacity">
                        <flux:icon.heart class="w-5 h-5 fill-red-500 text-red-500 drop-shadow-sm" />
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-16 text-center text-stone-500 border-2 border-dashed border-stone-200 dark:border-stone-800 rounded-2xl">
                <flux:icon.photo class="w-12 h-12 mx-auto mb-4 text-stone-300" />
                <h3 class="text-lg font-medium text-stone-900 dark:text-white mb-1">Aún no hay inspiración</h3>
                <p>Sube imágenes, colores o links para armar tu muro.</p>
            </div>
        @endforelse
    </div>

    {{-- Upload Modal --}}
    <flux:modal name="new-idea" class="min-w-[24rem]">
        <form wire:submit="saveIdea" class="space-y-6">
            <div>
                <flux:heading size="lg">Añadir al Muro</flux:heading>
                <flux:subheading>Guarda una nueva idea para la boda.</flux:subheading>
            </div>

            <flux:radio.group wire:model.live="newType" variant="segmented" class="w-full">
                <flux:radio value="image" label="Imagen" icon="photo" />
                <flux:radio value="color" label="Paleta" icon="swatch" />
                <flux:radio value="link" label="Link" icon="link" />
            </flux:radio.group>

            <div class="space-y-4 pt-4 border-t border-stone-100 dark:border-stone-800">
                <flux:select wire:model="newCategory" label="Categoría" placeholder="Elegir existente...">
                    @foreach($categories as $cat)
                        <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="newCategory" label="O Escribe Nueva" placeholder="Ej: Zapatos..." />
                
                <flux:input wire:model="newDescription" label="Descripción (opcional)" />

                @if($newType === 'color')
                    <div class="pt-2">
                        <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-2">Construye tu Paleta (Max 5)</label>
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            @foreach($newColors as $index => $color)
                                <div class="relative group">
                                    <div class="w-10 h-10 rounded-lg shadow-sm border border-stone-200 dark:border-stone-700" style="background-color: {{ $color }}"></div>
                                    <button type="button" wire:click="removeColor({{ $index }})" class="absolute -top-1 -right-1 bg-white dark:bg-stone-800 rounded-full shadow-md text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <flux:icon.x-mark class="w-3 h-3" />
                                    </button>
                                </div>
                            @endforeach
                            
                            @if(count($newColors) < 5)
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="currentColor" class="h-10 w-10 rounded cursor-pointer border-0 bg-stone-100 p-0.5">
                                    <flux:button type="button" wire:click="addColor" size="sm" variant="ghost" icon="plus" class="text-sage-600" />
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($newType === 'link')
                    <flux:input wire:model="newLink" type="url" label="URL del enlace" placeholder="https://pinterest.com/..." />
                @elseif($newType === 'image')
                    <div class="pt-2 flex flex-col items-start gap-4">
                        <input type="file" wire:model="newImage" accept="image/*" class="text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sage-50 file:text-sage-700 hover:file:bg-sage-100 cursor-pointer">
                        
                        <div wire:loading wire:target="newImage" class="flex items-center gap-2 text-sage-600 text-sm">
                            <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                            <span>Procesando imagen...</span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">
                    <span wire:loading.remove wire:target="saveIdea">Guardar Idea</span>
                    <span wire:loading wire:target="saveIdea">Guardando...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
    {{-- Edit Modal --}}
    <flux:modal name="edit-idea" class="min-w-[24rem]">
        <form wire:submit="updateIdea" class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Idea</flux:heading>
                <flux:subheading>Actualiza la información de tu idea.</flux:subheading>
            </div>

            <flux:radio.group wire:model.live="editType" variant="segmented" class="w-full">
                <flux:radio value="image" label="Imagen" icon="photo" />
                <flux:radio value="color" label="Paleta" icon="swatch" />
                <flux:radio value="link" label="Link" icon="link" />
            </flux:radio.group>

            <div class="space-y-4 pt-4 border-t border-stone-100 dark:border-stone-800">
                <flux:select wire:model="editCategory" label="Categoría" placeholder="Elegir existente...">
                    @foreach($categories as $cat)
                        <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="editCategory" label="O Escribe Nueva" placeholder="Ej: Zapatos..." />
                
                <flux:input wire:model="editDescription" label="Descripción (opcional)" />

                @if($editType === 'color')
                    <div class="pt-2">
                        <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-2">Edita tu Paleta (Max 5)</label>
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            @foreach($editColors as $index => $color)
                                <div class="relative group">
                                    <div class="w-10 h-10 rounded-lg shadow-sm border border-stone-200 dark:border-stone-700" style="background-color: {{ $color }}"></div>
                                    <button type="button" wire:click="removeColor({{ $index }})" class="absolute -top-1 -right-1 bg-white dark:bg-stone-800 rounded-full shadow-md text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <flux:icon.x-mark class="w-3 h-3" />
                                    </button>
                                </div>
                            @endforeach
                            
                            @if(count($editColors) < 5)
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="currentColor" class="h-10 w-10 rounded cursor-pointer border-0 bg-stone-100 p-0.5">
                                    <flux:button type="button" wire:click="addColor" size="sm" variant="ghost" icon="plus" class="text-sage-600" />
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($editType === 'link')
                    <flux:input wire:model="editLink" type="url" label="URL del enlace" placeholder="https://pinterest.com/..." />
                @elseif($editType === 'image')
                    <div class="pt-2 flex flex-col items-start gap-4">
                        <input type="file" wire:model="editImage" accept="image/*" class="text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sage-50 file:text-sage-700 hover:file:bg-sage-100 cursor-pointer">
                        
                        <div wire:loading wire:target="editImage" class="flex items-center gap-2 text-sage-600 text-sm">
                            <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                            <span>Procesando imagen...</span>
                        </div>

                        @if($editingItem && $editingItem->type === 'image' && !$editImage)
                            <div class="mt-2 text-xs text-stone-400 italic">Imagen actual: {{ basename($editingItem->content) }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">
                    <span wire:loading.remove wire:target="updateIdea">Actualizar Idea</span>
                    <span wire:loading wire:target="updateIdea">Actualizando...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
