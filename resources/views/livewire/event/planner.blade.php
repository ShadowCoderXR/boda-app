<?php

use Livewire\Volt\Component;
use App\Models\EventConfig;

new class extends Component {
    public $wedding_date = '';
    public $wedding_time = '';
    public $venue_name = '';
    public $venue_address = '';
    public $venue_map_link = '';
    public $reception_details = '';
    public $dress_code = '';
    public $registry_info = '';

    public function mount()
    {
        $config = EventConfig::firstOrCreate(['user_id' => auth()->id()]);
        
        $this->wedding_date = $config->wedding_date?->format('Y-m-d') ?? '';
        $this->wedding_time = $config->wedding_time ?? '';
        $this->venue_name = $config->venue_name ?? '';
        $this->venue_address = $config->venue_address ?? '';
        $this->venue_map_link = $config->venue_map_link ?? '';
        $this->reception_details = $config->reception_details ?? '';
        $this->dress_code = $config->dress_code ?? '';
        $this->registry_info = $config->registry_info ?? '';
    }

    public function saveConfig()
    {
        $config = EventConfig::where('user_id', auth()->id())->first();
        
        $config->update([
            'wedding_date' => $this->wedding_date ?: null,
            'wedding_time' => $this->wedding_time ?: null,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'venue_map_link' => $this->venue_map_link,
            'reception_details' => $this->reception_details,
            'dress_code' => $this->dress_code,
            'registry_info' => $this->registry_info,
        ]);

        Flux::toast('Configuración guardada correctamente.');
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Configuración de la Invitación</flux:heading>
            <flux:subheading>Define los detalles principales que verán tus invitados.</flux:subheading>
        </div>
        <flux:button wire:click="saveConfig" variant="primary" icon="check" class="bg-sage-600 hover:bg-sage-700 border-0">Guardar Cambios</flux:button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Section: When --}}
        <div class="bg-white dark:bg-stone-800 p-6 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-700 space-y-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon.calendar class="w-5 h-5 text-sage-600" />
                <flux:heading size="lg">¿Cuándo?</flux:heading>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="wedding_date" type="date" label="Fecha de la boda" />
                <flux:input wire:model="wedding_time" type="time" label="Hora de inicio" />
            </div>
            <flux:input wire:model="dress_code" label="Código de Vestimenta" placeholder="Ej: Formal / Guayabera / Etiqueta" />
        </div>

        {{-- Section: Where --}}
        <div class="bg-white dark:bg-stone-800 p-6 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-700 space-y-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon.map-pin class="w-5 h-5 text-sage-600" />
                <flux:heading size="lg">¿Dónde?</flux:heading>
            </div>
            <flux:input wire:model="venue_name" label="Nombre del lugar" placeholder="Ej: Hacienda Santa Cruz" />
            <flux:textarea wire:model="venue_address" label="Dirección completa" rows="2" />
            <flux:input wire:model="venue_map_link" label="Google Maps Link" placeholder="https://maps.app.goo.gl/..." />
        </div>

        {{-- Section: Reception --}}
        <div class="md:col-span-2 bg-white dark:bg-stone-800 p-6 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-700 space-y-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon.sparkles class="w-5 h-5 text-sage-600" />
                <flux:heading size="lg">Detalles Adicionales</flux:heading>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:textarea wire:model="reception_details" label="Información de la Recepción" placeholder="Ej: El brindis comenzará a las 7 PM..." rows="4" />
                <flux:textarea wire:model="registry_info" label="Mesa de Regalos / Datos Bancarios" placeholder="Ej: Amazon Wedding Registry ID: 123..." rows="4" />
            </div>
        </div>
    </div>

    <div class="bg-stone-50 dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-8 rounded-3xl text-center">
        <flux:icon.eye class="w-12 h-12 mx-auto mb-4 text-sage-300" />
        <flux:heading size="lg">Vista Previa de la Invitación</flux:heading>
        <flux:text class="max-w-md mx-auto">Visualiza cómo luce tu invitación digital con el diseño premium y los datos actuales.</flux:text>
        
        <div class="mt-6 flex justify-center gap-4">
            <flux:button href="{{ url('/invitacion/' . auth()->user()->guest?->slug ?? 'demo') }}" target="_blank" variant="primary" class="bg-sage-600 hover:bg-sage-700">
                Ver Mi Invitación (Demo)
            </flux:button>
        </div>
    </div>
</div>
