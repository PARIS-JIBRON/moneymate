<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\SavingGoal;

new class extends Component
{
    use WithPagination;

    public $sortBy = 'deadline';
    public $sortDirection = 'asc';
    
    // Properti untuk fitur Quick Top Up
    public $selectedGoalId;
    public $topUpAmount;

    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }
    
    #[Computed]
    public function savingGoals()
    {
        return SavingGoal::query()
            ->where('user_id', auth()->id())
            ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(10);
    }

    public function openTopUp($id)
    {
        $this->selectedGoalId = $id;
        $this->topUpAmount = '';
        Flux::modal('quick-topup')->show();
    }

    public function saveTopUp()
    {
        $this->validate([
            'topUpAmount' => 'required|numeric|min:1000'
        ]);

        $goal = SavingGoal::findOrFail($this->selectedGoalId);
        $goal->increment('current_amount', $this->topUpAmount);

        Flux::modal('quick-topup')->close();
        session()->flash('success', "Berhasil menambah tabungan untuk \"{$goal->title}\"!");
        
        $this->reset(['selectedGoalId', 'topUpAmount']);
    }

    public function edit($id){
        $this->dispatch('edit-saving-goal', id: $id);
    }
};
?>

<div class="max-w-7xl mx-auto space-y-4 bg-green-100 dark:bg-zinc-800 p-6">
    <flux:heading size="xl" class="text-zinc-800 dark:text-white">Tabungan</flux:heading>
    <flux:subheading size="lg" class="text-zinc-600 dark:text-zinc-400">Pantau dan Kelola Impian Keuangan Anda</flux:subheading>
    <flux:separator variant="subtle" />

    <div class="flex items-center justify-between">
        <flux:modal.trigger name="create-saving-goal">
            <flux:button variant="primary" icon="plus" color="primary">Tambah Tabungan</flux:button>
        </flux:modal.trigger>
    </div>

    <livewire:saving-goal.create />
    <livewire:saving-goal.edit />


    {{-- Tabel Utama --}}
    <div class="overflow-x-auto">
        <flux:table :paginate="$this->savingGoals">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Goal / Title</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'target_amount'" :direction="$sortDirection" wire:click="sort('target_amount')">Target & Progress</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'deadline'" :direction="$sortDirection" wire:click="sort('deadline')">Deadline & Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->savingGoals as $goal)
                    @php
                        // Hitung persentase progress
                        $percentage = $goal->target_amount > 0 ? round(($goal->current_amount / $goal->target_amount) * 100) : 0;
                        $percentage = min($percentage, 100); // Batasi maksimal 100%

                        // Tentukan status badge
                        $isAchieved = $goal->current_amount >= $goal->target_amount;
                        $isOverdue = !$isAchieved && $goal->deadline->isPast();
                    @endphp

                    <flux:table.row :key="$goal->id">
                        {{-- Judul Goal --}}
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">
                            <div>{{ $goal->title }}</div>
                            <div class="text-xs text-zinc-400 mt-1">
                                Sisa: Rp {{ number_format(max(0, $goal->target_amount - $goal->current_amount), 0, ',', '.') }}
                            </div>
                        </flux:table.cell>

                        {{-- Target & Progress Bar --}}
                        <flux:table.cell class="w-1/3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-zinc-600 dark:text-zinc-400">
                                    <strong class="text-zinc-800 dark:text-white">Rp {{ number_format($goal->current_amount, 0, ',', '.') }}</strong> 
                                    / Rp {{ number_format($goal->target_amount, 0, ',', '.') }}
                                </span>
                                <span class="font-bold {{ $isAchieved ? 'text-emerald-600' : 'text-zinc-600 dark:text-zinc-300' }}">{{ $percentage }}%</span>
                            </div>
                            {{-- Native/Tailwind Progress Bar --}}
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500 {{ $isAchieved ? 'bg-emerald-500' : 'bg-primary-500' }}" 
                                     style="width: {{ $percentage }}%"></div>
                            </div>
                        </flux:table.cell>

                        {{-- Deadline & Status Badge --}}
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-sm text-zinc-800 dark:text-zinc-200">{{ $goal->deadline->format('d M Y') }}</div>
                            <div class="mt-1">
                                @if($isAchieved)
                                    <flux:badge color="emerald" size="sm" inset="top bottom">Achieved</flux:badge>
                                @elseif($isOverdue)
                                    <flux:badge color="red" size="sm" inset="top bottom">Overdue</flux:badge>
                                @else
                                    <flux:badge color="orange" size="sm" inset="top bottom">On Track</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        {{-- Menu Aksi --}}
                        <flux:table.cell>
                            <div class="flex items-center gap-2 justify-end">
                                @if(!$isAchieved)
                                    <flux:button size="sm" variant="outline" icon="banknotes" wire:click="openTopUp({{ $goal->id }})">
                                        Top Up
                                    </flux:button>
                                @endif

                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="edit({{ $goal->id }})">Edit Details</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="$dispatch('confirm-delete', {id: {{ $goal->id }}})">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Modal Quick Top Up --}}
    <flux:modal name="quick-topup" class="md:w-100">
        <form class="space-y-6" wire:submit.prevent="saveTopUp">
            <div>
                <flux:heading size="lg">Top Up Tabungan</flux:heading>
                <flux:text>Masukkan jumlah uang yang ingin Anda tabung ke target ini.</flux:text>
            </div>

            <flux:input label="Jumlah Top Up (Rp)" type="number" placeholder="Contoh: 50000" wire:model="topUpAmount" />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="outline">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>