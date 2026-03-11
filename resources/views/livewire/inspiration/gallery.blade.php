<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\InspirationItem;

new class extends Component {
    use WithFileUploads;

    public $selectedCategory = '';
    public $categories = []; // Computed dynamically from DB

    // Modal Form State
    public $newType = 'image';
    public $newCategory = '';
    public $newDescription = '';
    public $newLink = '';
    public $newColor = '#A3B18A';
    public $newImage;

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

    public function toggleFavorite(InspirationItem $item)
    {
        $item->update(['is_favorite' => !$item->is_favorite]);
    }

    public function saveIdea()
    {
        $this->validate([
            'newType' => 'required|in:image,color,link',
            'newCategory' => 'nullable|string|max:100',
            'newDescription' => 'nullable|string|max:500',
            // Conditional validations
            'newLink' => 'required_if:newType,link|nullable|url',
            'newColor' => 'required_if:newType,color|nullable|string',
            'newImage' => 'required_if:newType,image|nullable|image|max:10240', // 10MB max
        ]);

        $content = '';

        if ($this->newType === 'color') {
            $content = $this->newColor;
        } elseif ($this->newType === 'link') {
            $content = $this->newLink;
        } elseif ($this->newType === 'image' && $this->newImage) {
            $path = $this->newImage->store('inspiration', 'public');
            $content = '/storage/' . $path;
        }

        InspirationItem::create([
            'type' => $this->newType,
            'category' => $this->newCategory ?: 'Sin Categoría',
            'content' => $content,
            'description' => $this->newDescription,
            // 'user_id' => auth()->id() // Not using auth in MVP tests necessarily but good practice
        ]);

        // Reset form
        $this->reset(['newType', 'newCategory', 'newDescription', 'newLink', 'newColor', 'newImage']);
        
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
                    <div class="w-full aspect-square flex items-center justify-center p-6" style="background-color: {{ $item->content }}">
                        <div class="bg-white/90 dark:bg-black/80 backdrop-blur px-3 py-1.5 rounded-lg shadow-sm font-mono text-sm tracking-widest text-stone-800 dark:text-stone-200">
                            {{ strtoupper($item->content) }}
                        </div>
                    </div>
                @elseif($item->type === 'link')
                    <a href="{{ $item->content }}" target="_blank" class="block w-full aspect-[4/3] bg-stone-50 dark:bg-stone-800 hover:bg-stone-100 transition-colors flex flex-col items-center justify-center p-6 text-center">
                        <flux:icon.link class="w-10 h-10 text-stone-400 mb-2" />
                        <span class="text-sm font-medium text-stone-600 dark:text-stone-300 break-all line-clamp-2">{{ str_replace(['http://', 'https://'], '', $item->content) }}</span>
                    </a>
                @endif

                {{-- Overlay & Info --}}
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/40 to-transparent p-4 translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 flex justify-between items-end">
                    <div class="text-white pr-2">
                        @if($item->description)
                            <p class="text-sm font-medium line-clamp-2 leading-tight drop-shadow-md">{{ $item->description }}</p>
                        @endif
                        <span class="text-xs text-stone-300 mt-1 inline-block drop-shadow-md">{{ $item->category }}</span>
                    </div>
                    
                    <button wire:click="toggleFavorite({{ $item->id }})" class="shrink-0 transition-transform active:scale-95 p-1">
                        @if($item->is_favorite)
                            <flux:icon.heart class="w-6 h-6 fill-red-500 text-red-500 drop-shadow-md" />
                        @else
                            <flux:icon.heart class="w-6 h-6 text-white hover:fill-white/30 drop-shadow-md" />
                        @endif
                    </button>
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
                <flux:radio value="color" label="Color" icon="swatch" />
                <flux:radio value="link" label="Link" icon="link" />
            </flux:radio.group>

            <div class="space-y-4 pt-4 border-t border-stone-100 dark:border-stone-800">
                <flux:input wire:model="newCategory" label="Categoría" placeholder="Ej: Centros de mesa, Vestido..." />
                <flux:input wire:model="newDescription" label="Descripción (opcional)" />

                @if($newType === 'color')
                    <div class="pt-2">
                        <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-2">Selecciona un Color</label>
                        <div class="flex items-center gap-4">
                            <input type="color" wire:model.live="newColor" class="h-12 w-24 rounded cursor-pointer border-0 bg-stone-100 p-1">
                            <div class="text-sm font-mono text-stone-500">{{ strtoupper($newColor) }}</div>
                        </div>
                    </div>
                @elseif($newType === 'link')
                    <flux:input wire:model="newLink" type="url" label="URL del enlace" placeholder="https://pinterest.com/..." />
                @elseif($newType === 'image')
                    <div class="pt-2 flex flex-col items-start gap-4">
                        <input type="file" wire:model="newImage" accept="image/*" class="text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sage-50 file:text-sage-700 hover:file:bg-sage-100 cursor-pointer">
                        
                        {{-- Loading spinner feedback --}}
                        <div wire:loading wire:target="newImage" class="flex items-center gap-2 text-sage-600 text-sm">
                            <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                            <span>Procesando imagen...</span>
                        </div>
                        
                        @if ($newImage)
                            <div class="mt-2 text-sm text-stone-500 dark:text-stone-400">
                                Previsualización lista.
                            </div>
                        @endif
                        @error('newImage') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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
</div>
