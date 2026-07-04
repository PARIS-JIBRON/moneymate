<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Transaction;

new class extends Component
{
    use WithPagination;

    public $sortBy = 'transaction_date';
    public $sortDirection = 'desc';
    public $search = '';

    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::query()
            ->with('category')
            ->where('user_id', auth()->id())
            ->when($this->search, function($query) {
                $query->where('description', 'like', '%' . $this->search . '%')
                      ->orWhereHas('category', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    public function edit($id)
    {
        $this->dispatch('edit-transaction', id: $id);
    }
};
?>

<div class="max-w-7xl mx-auto space-y-4 p-6 bg-blue-800">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-zinc-800 dark:text-white">Transaksi Keuangan</flux:heading>
            <flux:subheading size="lg" class="text-zinc-600 dark:text-zinc-400">Catat dan pantau mutasi keuangan harian Anda</flux:subheading>
        </div>

        <flux:modal.trigger name="create-transaction">
            <flux:button variant="primary" icon="plus" color="emerald">Tambah Transaksi</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:separator variant="subtle" />

    <livewire:transaction.create />
    <livewire:transaction.edit />

    <div class="flex flex-col md:flex-row gap-3">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari transaksi berdasarkan catatan atau kategori..." icon="magnifying-glass" />
        </div>
    </div>

    <div class="overflow-x-auto bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-800">
        <flux:table :paginate="$this->transactions">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'transaction_date'" :direction="$sortDirection" wire:click="sort('transaction_date')">Tanggal</flux:table.column>
                <flux:table.column>Kategori</flux:table.column>
                <flux:table.column>Deskripsi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">Tipe</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'amount'" :direction="$sortDirection" wire:click="sort('amount')">Jumlah</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->transactions as $transaction)
                    <flux:table.row :key="$transaction->id">
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $transaction->transaction_date->format('d M Y, H:i') }}
                        </flux:table.cell>

                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">
                            {{ $transaction->category->name }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400 max-w-xs truncate">
                            {{ $transaction->description ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($transaction->type === 'expense')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                    Pengeluaran
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    Pemasukan
                                </span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="font-bold text-zinc-900 dark:text-white">
                            Rp {{ number_format($transaction->amount, 2, ',', '.') }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $transaction->id }})">Edit</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="trash" wire:click="$dispatch('confirm-delete', {id: {{ $transaction->id }}})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>