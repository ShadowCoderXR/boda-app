<?php

use Livewire\Volt\Component;
use App\Models\Guest;
use App\Models\Category;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public int $totalGuests = 0;
    public int $confirmedGuests = 0;
    public string $topCategoryName = '-';
    public int $topCategoryCount = 0;

    public $upcomingTasks = [];
    public $randomPendingGuests = [];
    public $recentInspirations = [];
    public $calendarTaskDays = [];

    public function with()
    {
        return [
            'groups' => App\Models\Group::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        // Total guests (including representatives and their extra spots)
        $individualCount = Guest::count();
        $extraSpotsCount = (int) Guest::where('is_representative', true)->sum('extra_spots');

        $this->totalGuests = $individualCount + $extraSpotsCount;

        // Confirmed guests
        $confirmedIndividual = Guest::where('rsvp_status', 'confirmed')->count();
        $confirmedExtra = (int) Guest::where('is_representative', true)
            ->where('rsvp_status', 'confirmed')
            ->sum('extra_spots');

        $this->confirmedGuests = $confirmedIndividual + $confirmedExtra;
        
        // Top category globally
        $topCategory = Category::withCount('guests')
            ->orderByDesc('guests_count')
            ->first();

        if ($topCategory) {
            $this->topCategoryName = $topCategory->name;
            $this->topCategoryCount = $topCategory->guests_count;
        }

        // Tasks filtered by user (Admin/User specific)
        $this->upcomingTasks = \App\Models\Task::where('user_id', auth()->id())
            ->where('is_completed', false)
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get();

        // Random pending guests (global)
        $this->randomPendingGuests = Guest::where('rsvp_status', 'pending')
            ->inRandomOrder()
            ->limit(3)
            ->get();

        // Recent inspirations (global/visible)
        $this->recentInspirations = \App\Models\InspirationItem::with('category')
            ->latest()
            ->limit(4)
            ->get();

        // Calendar tasks still filtered by user
        $this->calendarTaskDays = \App\Models\Task::where('user_id', auth()->id())
            ->whereMonth('due_date', now()->month)
            ->whereYear('due_date', now()->year)
            ->pluck('due_date')
            ->map(fn($date) => $date->format('j'))
            ->unique()
            ->toArray();
    }
};
?>

<div class="mt-6 space-y-8">
    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <flux:card class="bg-white dark:bg-stone-800 border-none shadow-sm flex flex-col justify-between overflow-hidden relative">
            <div class="absolute top-0 right-0 p-3 opacity-10">
                <flux:icon.users class="w-12 h-12" />
            </div>
            <div>
                <flux:heading size="sm" class="text-stone-500 uppercase tracking-wider font-bold">Invitados</flux:heading>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-black text-stone-800 dark:text-stone-100">{{ $confirmedGuests }}</span>
                    <span class="text-sm text-stone-400">/ {{ $totalGuests }} confirmados</span>
                </div>
            </div>
            @php $percentage = $totalGuests > 0 ? round(($confirmedGuests / $totalGuests) * 100) : 0; @endphp
            <div class="mt-4 w-full bg-stone-100 dark:bg-stone-700 rounded-full h-1.5 overflow-hidden">
                <div class="bg-sage-500 h-full rounded-full transition-all duration-1000" style="width: {{ $percentage }}%"></div>
            </div>
        </flux:card>

        <flux:card class="bg-white dark:bg-stone-800 border-none shadow-sm flex flex-col justify-between overflow-hidden relative">
             <div class="absolute top-0 right-0 p-3 opacity-10">
                <flux:icon.chart-pie class="w-12 h-12" />
            </div>
            <div>
                <flux:heading size="sm" class="text-stone-500 uppercase tracking-wider font-bold">Top Categoría</flux:heading>
                <div class="mt-2 text-2xl font-black text-stone-800 dark:text-stone-100 truncate">{{ $topCategoryName }}</div>
                <p class="text-xs text-stone-400 mt-1">{{ $topCategoryCount }} invitados aquí</p>
            </div>
        </flux:card>

        <div class="flex flex-col gap-2 h-full justify-center">
            <flux:button icon="plus" href="/invitados" wire:navigate class="w-full bg-sage-600 hover:bg-sage-700 text-white border-0 shadow-lg shadow-sage-200 dark:shadow-none py-6">Gestionar Invitados</flux:button>
            <flux:button variant="ghost" icon="light-bulb" href="/inspiracion" wire:navigate class="w-full text-stone-600 text-xs uppercase tracking-tighter">Nueva Idea rápida</flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left Column: Tasks & Calendar --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Upcoming Tasks --}}
            <section>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon.clock class="w-5 h-5 text-amber-500" />
                        Próximos Pendientes
                    </flux:heading>
                    <flux:button variant="ghost" size="sm" href="/pendientes" wire:navigate class="text-xs">Ver todos</flux:button>
                </div>
                
                <div class="space-y-3">
                    @forelse($upcomingTasks as $task)
                        <div class="flex items-center gap-4 p-4 bg-white dark:bg-stone-800 rounded-2xl border border-stone-100 dark:border-stone-700 shadow-sm hover:translate-x-1 transition-transform cursor-pointer">
                            <div class="w-10 h-10 rounded-xl bg-stone-50 dark:bg-stone-900 flex items-center justify-center shrink-0 border border-stone-100 dark:border-stone-800">
                                @if($task->priority === 'high')
                                    <flux:icon.exclamation-circle class="w-5 h-5 text-rose-500" />
                                @else
                                    <flux:icon.check-circle class="w-5 h-5 text-stone-300" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-stone-800 dark:text-stone-100 truncate text-sm">{{ $task->title }}</div>
                                <div class="text-xs text-stone-400 flex items-center gap-1 mt-0.5 capitalize">
                                    <flux:icon.calendar class="w-3 h-3" />
                                    {{ $task->due_date?->format('d M, Y') ?? 'Sin fecha' }}
                                </div>
                            </div>
                            <flux:badge size="sm" color="{{ $task->priority === 'high' ? 'red' : ($task->priority === 'medium' ? 'amber' : 'zinc') }}" inset="top" class="text-[10px] uppercase font-bold tracking-tighter">
                                {{ $task->priority }}
                            </flux:badge>
                        </div>
                    @empty
                        <div class="py-12 text-center bg-stone-50 dark:bg-stone-900/50 rounded-3xl border border-dashed border-stone-200 dark:border-stone-800">
                            <flux:icon.clipboard-document-check class="w-10 h-10 mx-auto text-stone-200 mb-2" />
                            <p class="text-sm text-stone-500 italic">Todo al día por ahora 🙌</p>
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- Monthly Calendar View (Basic Grid) --}}
            <section>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon.calendar-days class="w-5 h-5 text-sage-500" />
                        Calendario de Tareas
                    </flux:heading>
                    <span class="text-sm font-bold text-stone-400 uppercase tracking-widest">{{ now()->translatedFormat('F Y') }}</span>
                </div>

                <div class="bg-white dark:bg-stone-800 p-6 rounded-3xl shadow-sm border border-stone-100 dark:border-stone-700">
                    <div class="grid grid-cols-7 gap-px mb-2 text-center text-[10px] items-center py-2 uppercase font-bold text-stone-400 tracking-widest border-b border-stone-50 dark:border-stone-700">
                        <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
                    </div>
                    <div class="grid grid-cols-7 gap-2">
                        @php
                            $startOfMonth = now()->startOfMonth();
                            $daysInMonth = now()->daysInMonth;
                            $dayOfWeek = $startOfMonth->dayOfWeek;
                        @endphp

                        @for ($i = 0; $i < $dayOfWeek; $i++)
                            <div class="aspect-square"></div>
                        @endfor

                        @for ($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $date = now()->setDay($day);
                                $isToday = $date->isToday();
                                $hasTask = in_array((string)$day, $this->calendarTaskDays);
                                $dateStr = $date->format('Y-m-d');
                            @endphp
                            <a href="/pendientes?date={{ $dateStr }}" wire:navigate class="aspect-square flex flex-col items-center justify-center rounded-xl relative group {{ $isToday ? 'bg-sage-600 text-white shadow-lg shadow-sage-200' : 'hover:bg-stone-50 dark:hover:bg-stone-900 border border-stone-50 dark:border-stone-800' }}">
                                <span class="text-xs font-semibold">{{ $day }}</span>
                                @if($hasTask)
                                    <div class="mt-1 w-1.5 h-1.5 rounded-full {{ $isToday ? 'bg-white' : 'bg-sage-500' }}"></div>
                                @endif
                                
                                @if($hasTask)
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-20">
                                        <div class="bg-stone-900 text-white text-[10px] px-2 py-1 rounded shadow-xl whitespace-nowrap">
                                            ¡Tienes tareas este día!
                                        </div>
                                    </div>
                                @endif
                            </a>
                        @endfor
                    </div>
                </div>
            </section>
        </div>

        {{-- Right Column: Side Widgets --}}
        <div class="space-y-8">
            {{-- Random RSVP Widget --}}
            <section class="bg-gradient-to-br from-rose-50 to-rose-100/50 dark:from-rose-900/10 dark:to-stone-900 p-6 rounded-3xl border border-rose-100 dark:border-stone-700 relative overflow-hidden">
                <div class="absolute -bottom-4 -right-4 opacity-10">
                    <flux:icon.heart class="w-32 h-32 text-rose-500" />
                </div>
                <flux:heading size="lg" class="text-rose-900 dark:text-stone-100 mb-1">¿Quién falta?</flux:heading>
                <flux:subheading class="text-rose-700 dark:text-stone-400">Recordatorio para confirmar:</flux:subheading>
                
                <div class="mt-6 space-y-3 relative z-10">
                    @forelse($randomPendingGuests as $guest)
                        <div class="flex items-center gap-3 p-3 bg-white/60 dark:bg-stone-800/60 backdrop-blur-sm rounded-2xl border border-white dark:border-stone-700">
                            <div class="w-8 h-8 rounded-full bg-rose-200 dark:bg-rose-900 flex items-center justify-center text-rose-700 dark:text-rose-300 font-bold text-xs">
                                {{ substr($guest->name, 0, 1) }}
                            </div>
                            <span class="text-sm font-medium text-stone-800 dark:text-stone-200 truncate">{{ $guest->name }}</span>
                            <flux:spacer />
                            <flux:button variant="ghost" icon="paper-airplane" size="sm" class="!p-1 text-rose-500" href="/invitados/{{ $guest->slug }}" wire:navigate />
                        </div>
                    @empty
                        <p class="text-center text-xs text-rose-700 italic py-4">¡Todos han confirmado! 🎉</p>
                    @endforelse
                </div>
            </section>

            {{-- Recent Inspirations --}}
            <section>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon.sparkles class="w-5 h-5 text-gold-500" />
                        Inspiración reciente
                    </flux:heading>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @forelse($recentInspirations as $item)
                        <a href="/inspiracion" wire:navigate class="group relative aspect-square rounded-2xl overflow-hidden bg-stone-100 dark:bg-stone-800 border border-stone-100 dark:border-stone-700">
                            @if($item->isImage())
                                <img src="{{ $item->content }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                            @elseif($item->isColor())
                                <div class="w-full h-full" style="background-color: {{ $item->content }}"></div>
                            @elseif($item->isLink() && $item->getThumbnail())
                                <img src="{{ $item->getThumbnail() }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <flux:icon.link class="w-6 h-6 text-stone-300" />
                                </div>
                            @endif
                            
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-2">
                                <span class="text-[9px] text-white truncate font-medium">{{ $item->category?->name ?? 'General' }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="col-span-2 py-8 text-center bg-stone-50 dark:bg-stone-900/50 rounded-2xl border border-dashed border-stone-200 dark:border-stone-800">
                            <flux:icon.plus class="w-6 h-6 mx-auto text-stone-200 mb-1" />
                            <p class="text-[10px] text-stone-400">Aún sin ideas</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
