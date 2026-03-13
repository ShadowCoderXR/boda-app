<?php

use Livewire\Volt\Component;
use App\Models\Guest;
use App\Models\EventConfig;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public Guest $guest;
    public $event;
    public $groupMembers;
    public $responses = []; // id => status
    public $names = []; // id => name (for unnamed)

    public function mount(Guest $guest)
    {
        $this->guest = $guest;
        $this->event = EventConfig::first();
        $this->loadData();
    }

    public function loadData()
    {
        $this->guest->refresh();
        
        // If guest is not representative, get representative
        $representative = $this->guest->is_representative ? $this->guest : $this->guest->representative;
        
        $this->groupMembers = $representative->members()
            ->get()
            ->prepend($representative);

        foreach ($this->groupMembers as $member) {
            // Only set if not already set or if explicitly refreshing
            $this->responses[$member->id] = $member->rsvp_status ?? 'pending';
            $this->names[$member->id] = $member->name;
        }

        // Load extra spots placeholders
        for ($i = 0; $i < $this->guest->extra_spots; $i++) {
            $tempId = "extra_{$i}";
            // Only initialize if not already touched to keep unsaved input if reload happens accidentally
            if (!isset($this->responses[$tempId])) {
                $this->responses[$tempId] = 'pending';
            }
            if (!isset($this->names[$tempId])) {
                $this->names[$tempId] = "Acompañante " . ($i + 1);
            }
        }
    }

    public function updateStatus($memberId, $status)
    {
        $this->responses[$memberId] = $status;
    }

    public function saveRsvp()
    {
        DB::transaction(function () {
            foreach ($this->groupMembers as $member) {
                $member->update([
                    'rsvp_status' => $this->responses[$member->id],
                    'name' => $this->names[$member->id],
                ]);
            }

            // Save extra spots that were interacted with
            $usedExtra = 0;
            for ($i = 0; $i < $this->guest->extra_spots; $i++) {
                $tempId = "extra_{$i}";
                if ($this->responses[$tempId] !== 'pending') {
                    Guest::create([
                        'name' => $this->names[$tempId],
                        'group_id' => $this->guest->group_id,
                        'representative_id' => $this->guest->is_representative ? $this->guest->id : $this->guest->representative_id,
                        'is_representative' => false,
                        'rsvp_status' => $this->responses[$tempId],
                        'user_id' => $this->guest->user_id,
                    ]);
                    $usedExtra++;
                    
                    // Cleanup the temporary state for this extra spot
                    unset($this->responses[$tempId]);
                    unset($this->names[$tempId]);
                }
            }

            if ($usedExtra > 0) {
                $this->guest->decrement('extra_spots', $usedExtra);
            }
        });

        $this->loadData();
        $this->dispatch('rsvp-saved');
        Flux::toast('Asistencia actualizada correctamente.');
    }
}; ?>

<div class="max-w-4xl mx-auto px-4 py-12 md:py-24 space-y-24">
    {{-- Hero Section --}}
    <section class="text-center space-y-8 fade-up" style="animation-delay: 0.1s">
        <div class="inline-block px-4 py-1 border-y border-gold-300 transform -rotate-1">
            <span class="text-sm tracking-[0.3em] uppercase text-gold-600 font-medium italic">Nuestra Boda</span>
        </div>
        
        <h1 class="text-6xl md:text-8xl font-handwritten text-sage-900 leading-tight">
            Raúl & Lucia
        </h1>
        
        <p class="text-lg md:text-2xl font-serif-premium italic text-stone-600 max-w-2xl mx-auto leading-relaxed">
            "Hay momentos que merecen ser recordados para siempre, y el más importante es aquel que compartiremos contigo."
        </p>
    </section>

    {{-- Invitation Content --}}
    <div class="relative bg-white/40 backdrop-blur-md border border-white/60 rounded-[3rem] p-8 md:p-16 shadow-2xl shadow-sage-900/5 fade-up" style="animation-delay: 0.4s">
        {{-- Ornament --}}
        <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white p-4 rounded-full border border-stone-100 shadow-sm">
            <flux:icon.heart class="w-8 h-8 text-gold-400" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            {{-- Wedding Info --}}
            <div class="space-y-12">
                <div class="space-y-4">
                    <flux:heading size="xl" class="font-serif-premium tracking-wide text-sage-900">Hola, {{ $guest->name }}</flux:heading>
                    <p class="text-stone-600 leading-relaxed">
                        Tenemos el honor de invitarte a celebrar nuestra unión matrimonial. Tu presencia es el mejor regalo que podemos recibir.
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="flex items-start gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-sage-50 flex items-center justify-center shrink-0">
                            <flux:icon.calendar class="w-6 h-6 text-sage-600" />
                        </div>
                        <div>
                            <flux:heading size="lg" class="font-serif-premium">{{ \Carbon\Carbon::parse($event?->wedding_date)->translatedFormat('l d \d\e F, Y') }}</flux:heading>
                            <flux:subheading>{{ $event?->wedding_time }} HRS</flux:subheading>
                        </div>
                    </div>

                    <div class="flex items-start gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-sage-50 flex items-center justify-center shrink-0">
                            <flux:icon.map-pin class="w-6 h-6 text-sage-600" />
                        </div>
                        <div>
                            <flux:heading size="lg" class="font-serif-premium">{{ $event?->venue_name }}</flux:heading>
                            <flux:subheading>{{ $event?->venue_address }}</flux:subheading>
                            <flux:button href="{{ $event?->venue_map_link }}" target="_blank" variant="ghost" size="sm" class="mt-2 -ml-2 text-sage-600">
                                Ver ubicación en el mapa
                            </flux:button>
                        </div>
                    </div>

                    <div class="flex items-start gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-sage-50 flex items-center justify-center shrink-0">
                            <flux:icon.sparkles class="w-6 h-6 text-sage-600" />
                        </div>
                        <div>
                            <flux:heading size="lg" class="font-serif-premium">Código de Vestimenta</flux:heading>
                            <flux:subheading>{{ $event?->dress_code }}</flux:subheading>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RSVP Area --}}
            <div class="bg-stone-50/80 rounded-[2rem] p-8 space-y-8 border border-stone-100 shadow-inner">
                <div class="text-center space-y-2">
                    <flux:heading size="lg" class="font-serif-premium tracking-wider">Confirmar Asistencia</flux:heading>
                    <p class="text-[10px] text-stone-500 uppercase tracking-[0.2em] font-bold">
                        {{ __('Por favor, confirma antes del') }} 
                        {{ \Carbon\Carbon::parse($event?->wedding_date)->subDays(30)->format('d/m/Y') }}
                    </p>
                </div>

                <div class="space-y-6">
                    @foreach($groupMembers as $member)
                        <div class="p-4 bg-white rounded-2xl border border-stone-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                @if(Str::contains(strtolower($member->name), ['acompañante', 'extra', 'invitado', 'libre']))
                                    <flux:input wire:model="names.{{ $member->id }}" size="sm" class="flex-1 mr-4" placeholder="Nombre del acompañante..." />
                                @else
                                    <span class="font-medium text-stone-800">{{ $member->name }}</span>
                                @endif
                                
                                <div class="flex gap-1">
                                    <button 
                                        wire:click="updateStatus('{{ $member->id }}', 'confirmed')" 
                                        class="p-2 rounded-lg transition-all {{ $responses[$member->id] === 'confirmed' ? 'bg-sage-600 text-white shadow-md' : 'bg-stone-100 text-stone-400 hover:bg-stone-200' }}"
                                        title="Confirmar"
                                    >
                                        <flux:icon.check class="w-4 h-4" />
                                    </button>
                                    <button 
                                        wire:click="updateStatus('{{ $member->id }}', 'declined')" 
                                        class="p-2 rounded-lg transition-all {{ $responses[$member->id] === 'declined' ? 'bg-red-500 text-white shadow-md' : 'bg-stone-100 text-stone-400 hover:bg-stone-200' }}"
                                        title="Declinar"
                                    >
                                        <flux:icon.x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    {{-- Extra Spots --}}
                    @for($i = 0; $i < $guest->extra_spots; $i++)
                        @php $tempId = "extra_{$i}"; @endphp
                        <div class="p-4 bg-white rounded-2xl border border-stone-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <flux:input wire:model="names.{{ $tempId }}" size="sm" class="flex-1 mr-4" placeholder="Nombre del acompañante..." />
                                
                                <div class="flex gap-1">
                                    <button 
                                        wire:click="updateStatus('{{ $tempId }}', 'confirmed')" 
                                        class="p-2 rounded-lg transition-all {{ $responses[$tempId] === 'confirmed' ? 'bg-sage-600 text-white shadow-md' : 'bg-stone-100 text-stone-400 hover:bg-stone-200' }}"
                                        title="Confirmar"
                                    >
                                        <flux:icon.check class="w-4 h-4" />
                                    </button>
                                    <button 
                                        wire:click="updateStatus('{{ $tempId }}', 'declined')" 
                                        class="p-2 rounded-lg transition-all {{ $responses[$tempId] === 'declined' ? 'bg-red-500 text-white shadow-md' : 'bg-stone-100 text-stone-400 hover:bg-stone-200' }}"
                                        title="Declinar"
                                    >
                                        <flux:icon.x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endfor

                    <flux:button wire:click="saveRsvp" variant="primary" class="w-full bg-sage-600 hover:bg-sage-700 mt-4 shadow-lg shadow-sage-900/10">
                        {{ __('Enviar Confirmación') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer/Message --}}
    <footer class="text-center py-12 space-y-6 fade-up" style="animation-delay: 0.8s">
        <p class="font-handwritten text-4xl text-sage-600">¡Te esperamos!</p>
        <div class="flex justify-center gap-1">
            <div class="w-1 h-1 rounded-full bg-gold-400"></div>
            <div class="w-16 h-px self-center bg-gold-200"></div>
            <div class="w-1 h-1 rounded-full bg-gold-400"></div>
        </div>
    </footer>
</div>
