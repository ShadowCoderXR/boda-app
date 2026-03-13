<?php

use Livewire\Volt\Component;
use App\Models\Vendor;

new class extends Component {
    public $name = '';
    public $selectedCategories = [];
    public $contact_name = '';
    public $phone = '';
    public $email = '';
    public $website = '';
    public $price = '';
    public $price_quote = '';
    public $newCategoryName = '';
    public $status = 'searching';
    public $sponsor_id = '';
    public $sponsorSearch = '';

    public $filterCategory = '';

    public function with()
    {
        $query = Vendor::where('user_id', auth()->id());

        if (!empty($this->filterCategory)) {
            $query->whereHas('categories', function($q) {
                $q->where('categories.id', $this->filterCategory);
            });
        }

        return [
            'vendors' => $query->with(['categories', 'sponsor.guest'])->orderBy('name', 'asc')->get(),
            'categories' => \App\Models\Category::where('type', 'vendor')->where('user_id', auth()->id())->get(),
            'sponsors' => \App\Models\Sponsor::where('user_id', auth()->id())
                ->with('guest')
                ->whereHas('guest', function($q) {
                    $q->where('name', 'like', '%'.$this->sponsorSearch.'%');
                })
                ->get(),
        ];
    }

    public function addVendor()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'selectedCategories' => 'required_without:newCategoryName|array',
            'newCategoryName' => 'required_without:selectedCategories|nullable|string|max:255',
            'email' => 'nullable|email',
            'status' => 'required|in:searching,contacted,hired',
            'price' => 'nullable|numeric',
        ]);

        $vendor = Vendor::create([
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'price' => $this->price ?: null,
            'price_quote' => $this->price_quote,
            'status' => $this->status,
            'sponsor_id' => $this->handleSponsorCreation(),
            'user_id' => auth()->id(),
        ]);

        $categoryIds = $this->selectedCategories;

        if (!empty($this->newCategoryName)) {
            $newCat = \App\Models\Category::create([
                'name' => $this->newCategoryName,
                'type' => 'vendor',
                'user_id' => auth()->id(),
                'color' => 'stone'
            ]);
            $categoryIds[] = $newCat->id;
        }

        $vendor->categories()->sync($categoryIds);

        $this->reset(['name', 'selectedCategories', 'newCategoryName', 'contact_name', 'phone', 'email', 'website', 'price', 'price_quote', 'status', 'sponsor_id']);
        Flux::toast('Proveedor agregado.');
    }

    public function deleteVendor(Vendor $vendor)
    {
        $vendor->delete();
        Flux::toast('Proveedor eliminado.');
    }

    public function updateStatus(Vendor $vendor, $status)
    {
        $vendor->update(['status' => $status]);
    }

    protected function handleSponsorCreation()
    {
        if (empty($this->sponsor_id)) return null;

        if (Str::isUuid($this->sponsor_id)) {
            return $this->sponsor_id;
        }

        // Create new guest + sponsor
        $guest = \App\Models\Guest::create([
            'name' => $this->sponsor_id,
            'rsvp_status' => 'pending',
            'user_id' => auth()->id(),
        ]);

        $sponsor = \App\Models\Sponsor::create([
            'guest_id' => $guest->id,
            'role' => 'Patrocinador',
            'user_id' => auth()->id(),
        ]);

        return $sponsor->id;
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Directorio de Proveedores</flux:heading>
            <flux:subheading>Gestiona tus contactos, presupuestos y contratos.</flux:subheading>
        </div>
        <flux:modal.trigger name="add-vendor-modal">
            <flux:button icon="plus" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">Agregar Proveedor</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="flex gap-2 bg-white dark:bg-stone-800 p-2 rounded-xl shadow-sm border border-stone-100 dark:border-stone-700 overflow-x-auto">
        <flux:button size="sm" wire:click="$set('filterCategory', '')" 
            variant="{{ $filterCategory === '' ? 'primary' : 'ghost' }}"
            class="{{ $filterCategory === '' ? 'bg-stone-800 text-white border-0' : '' }}">Todos</flux:button>
        @foreach($categories as $cat)
            <flux:button size="sm" wire:click="$set('filterCategory', '{{ $cat->id }}')"
                variant="{{ $filterCategory == $cat->id ? 'primary' : 'ghost' }}"
                class="{{ $filterCategory == $cat->id ? 'bg-stone-800 text-white border-0' : '' }} text-xs capitalize">{{ $cat->name }}</flux:button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($vendors as $vendor)
            <div class="bg-white dark:bg-stone-900 rounded-2xl p-5 shadow-sm border border-stone-200 dark:border-stone-800 flex flex-col group">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex flex-wrap gap-1 max-w-[80%]">
                        @foreach($vendor->categories as $cat)
                            <span class="text-[9px] uppercase font-bold tracking-widest px-2 py-0.5 rounded bg-stone-100 dark:bg-stone-800 text-stone-500">{{ $cat->name }}</span>
                        @endforeach
                        <flux:heading size="md" class="w-full mt-1">{{ $vendor->name }}</flux:heading>
                    </div>
                    <flux:dropdown>
                        <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" class="opacity-0 group-hover:opacity-100 transition-opacity" />
                        <flux:menu>
                            <flux:menu.item icon="trash" variant="danger" wire:click="deleteVendor({{ $vendor->id }})" wire:confirm="¿Eliminar proveedor?">Eliminar</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <div class="space-y-3 flex-1">
                    @if($vendor->contact_name)
                        <div class="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-400">
                            <flux:icon.user class="w-4 h-4 text-stone-300" />
                            <span>{{ $vendor->contact_name }}</span>
                        </div>
                    @endif
                    @if($vendor->phone)
                        <div class="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-400">
                            <flux:icon.phone class="w-4 h-4 text-stone-300" />
                            <a href="tel:{{ $vendor->phone }}" class="hover:text-sage-600 underline decoration-stone-200 underline-offset-4">{{ $vendor->phone }}</a>
                        </div>
                    @endif
                    
                    @if($vendor->price)
                        <div class="flex items-center gap-2 text-sm font-semibold text-sage-700 dark:text-sage-400">
                            <flux:icon.currency-dollar class="w-4 h-4" />
                            <span>${{ number_format($vendor->price, 2) }}</span>
                        </div>
                    @endif

                    @if($vendor->sponsor)
                        <div class="flex items-center gap-2 text-xs text-stone-500 bg-amber-50 dark:bg-amber-900/20 p-2 rounded-lg border border-amber-100 dark:border-amber-900/30">
                            <flux:icon.heart class="w-3.5 h-3.5 text-amber-500" />
                            <span>Patrocinado por: <strong>{{ $vendor->sponsor->guest->name }}</strong></span>
                        </div>
                    @endif

                    @if($vendor->price_quote)
                        <div class="mt-4 p-3 bg-stone-50 dark:bg-stone-800 rounded-xl border border-stone-100 dark:border-stone-700">
                            <span class="text-[10px] uppercase font-bold text-stone-400 block mb-1">Notas / Detalles</span>
                            <span class="text-sm text-stone-700 dark:text-stone-300 line-clamp-3">{{ $vendor->price_quote }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-6 pt-4 border-t border-stone-100 dark:border-stone-800 flex items-center justify-between">
                    <flux:dropdown>
                        <flux:button size="sm" icon-trailing="chevron-down" class="text-xs uppercase font-bold tracking-wider">
                            @if($vendor->status === 'searching') Buscando @elseif($vendor->status === 'contacted') Contactado @else Contratado @endif
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item wire:click="updateStatus({{ $vendor->id }}, 'searching')">Buscando</flux:menu.item>
                            <flux:menu.item wire:click="updateStatus({{ $vendor->id }}, 'contacted')">Contactado</flux:menu.item>
                            <flux:menu.item wire:click="updateStatus({{ $vendor->id }}, 'hired')">Contratado</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                    
                    <div class="w-2 h-2 rounded-full @if($vendor->status === 'searching') bg-stone-400 @elseif($vendor->status === 'contacted') bg-amber-400 @else bg-sage-500 @endif animate-pulse"></div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-20 text-center bg-white dark:bg-stone-900 border-2 border-dashed border-stone-200 dark:border-stone-800 rounded-3xl">
                <flux:icon.building-storefront class="w-16 h-16 mx-auto mb-4 text-stone-200" />
                <flux:heading size="xl" class="text-stone-400 mb-2">Sin proveedores aún</flux:heading>
                <flux:text>Empieza a guardar tus proveedores favoritos.</flux:text>
                <flux:modal.trigger name="add-vendor-modal">
                    <flux:button variant="ghost" class="mt-4 text-sage-600">Agregar Primer Proveedor</flux:button>
                </flux:modal.trigger>
            </div>
        @endforelse
    </div>

    {{-- Add Vendor Modal --}}
    <flux:modal name="add-vendor-modal" class="md:min-w-[32rem]">
        <form wire:submit="addVendor" class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Proveedor</flux:heading>
                <flux:subheading>Registra los datos del proveedor para tenerlos a la mano.</flux:subheading>
            </div>

            <flux:input wire:model="name" label="Nombre comercial" placeholder="Ej: Florería Rosas" required />
            
            <div class="space-y-3">
                <flux:checkbox.group wire:model="selectedCategories" label="Giro / Categorías" description="Selecciona uno o más giros para el proveedor.">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($categories as $cat)
                            <flux:checkbox label="{{ $cat->name }}" value="{{ $cat->id }}" />
                        @endforeach
                    </div>
                </flux:checkbox.group>
                
                {{-- Dynamic category creation --}}
                <div class="pt-2 border-t border-stone-100 dark:border-stone-800">
                    <flux:input wire:model="newCategoryName" label="ó Agregar Nueva Categoría" placeholder="Ej: Transporte, Joyería..." icon="plus" size="sm" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="contact_name" label="Persona de contacto" placeholder="Nombre completo" />
                <flux:input wire:model="phone" label="Teléfono" />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="email" type="email" label="Email" />
                <flux:input wire:model="website" type="url" label="Sitio Web" placeholder="https://..." />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="price" type="number" step="0.01" label="Precio / Costo" icon="currency-dollar" placeholder="0.00" />
                
                <div x-data="{ open: false }" class="relative" @click.away="open = false" wire:ignore.self>
                    <flux:label>¿Patrocinador / Padrino?</flux:label>
                    
                    <div class="relative mt-1" @click="open = true">
                        <input 
                            type="text"
                            wire:model.live.debounce.300ms="sponsorSearch" 
                            @focus="open = true"
                            placeholder="Buscar o crear..."
                            autocomplete="off"
                            class="w-full pl-3 pr-10 py-2 border border-stone-200 dark:border-stone-700 rounded-lg bg-white dark:bg-stone-800 text-sm focus:ring-2 focus:ring-sage-500/20 focus:border-sage-500 transition-all outline-none"
                        />
                        <div @click="open = !open" class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-stone-400 hover:text-stone-600">
                            <flux:icon.chevron-down class="w-4 h-4" />
                        </div>
                    </div>
                    
                    <div x-show="open" 
                         x-transition
                         class="absolute z-[9999] w-full mt-1 bg-white dark:bg-stone-800 border-2 border-sage-500/50 rounded-lg shadow-2xl max-h-60 overflow-y-auto">
                        <div class="p-1">
                            @if(empty($sponsorSearch))
                                <button type="button" wire:click="$set('sponsor_id', ''); sponsorSearch = ''; open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm border-b border-stone-100 dark:border-stone-700 mb-1">
                                    Ninguno
                                </button>
                            @endif

                            @foreach($sponsors as $sponsor)
                                <button type="button" wire:click="$set('sponsor_id', '{{ $sponsor->id }}'); $set('sponsorSearch', '{{ $sponsor->guest->name }}'); open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm mb-1">
                                    {{ $sponsor->guest->name }} ({{ $sponsor->role }})
                                </button>
                            @endforeach
                            
                            @if(!empty($sponsorSearch) && !$sponsors->filter(fn($s) => stripos($s->guest->name, $this->sponsorSearch) !== false)->count())
                                <button type="button" wire:click="$set('sponsor_id', '{{ $sponsorSearch }}'); open = false" class="w-full text-left p-2 bg-sage-50 dark:bg-sage-900/20 text-sage-600 dark:text-sage-400 hover:bg-sage-100 dark:hover:bg-sage-900/40 cursor-pointer rounded-md text-sm font-medium border border-sage-200 dark:border-sage-800 mt-1">
                                    <flux:icon.plus class="inline-block w-3 h-3 mr-1" />
                                    Crear: "{{ $sponsorSearch }}"
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <flux:textarea wire:model="price_quote" label="Notas Adicionales" placeholder="Ej: incluye montaje..." />

            <flux:radio.group wire:model="status" label="Estado inicial" variant="cards">
                <flux:radio value="searching" label="Buscando" />
                <flux:radio value="contacted" label="Contactado" />
                <flux:radio value="hired" label="Contratado" />
            </flux:radio.group>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0">Guardar Proveedor</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
