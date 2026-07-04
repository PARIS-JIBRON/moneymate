<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Transaction;
use App\Models\Budget;
use App\Models\SavingGoal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component 
{
    // Properti untuk menyimpan data grafik agar bisa dibaca oleh Chart.js
    public array $chartData = [];

    public function mount()
    {
        $this->prepareChartData();
    }

    // Fungsi yang otomatis dipanggil setiap kali polling berjalan untuk memperbarui grafik
    public function updateDashboardData()
    {
        $this->prepareChartData();
        // Emit event ke browser agar Chart.js menggambar ulang grafik dengan data baru
        $this->dispatch('update-chart', data: $this->chartData);
    }

    private function prepareChartData()
    {
        $userId = Auth::id();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Ambil akumulasi transaksi 7 hari terakhir untuk grafik tren
        $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));
        
        $rawTransactions = Transaction::where('user_id', $userId)
            ->where('transaction_date', '>=', now()->subDays(6)->startOfDay())
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense")
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $this->chartData = [
            'labels' => $days->map(fn($d) => date('d M', strtotime($d)))->toArray(),
            'income' => $days->map(fn($d) => (float) ($rawTransactions[$d]->income ?? 0))->toArray(),
            'expense' => $days->map(fn($d) => (float) ($rawTransactions[$d]->expense ?? 0))->toArray(),
        ];
    }

    // Menggunakan Computed Properties Livewire agar data ditarik secara efisien saat polling
    #[Computed]
    public function stats()
    {
        $userId = Auth::id();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // 1. Hitung Total Pemasukan & Pengeluaran sepanjang masa (Saldo Bersih)
        $totalIncome = Transaction::where('user_id', $userId)->where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('user_id', $userId)->where('type', 'expense')->sum('amount');
        $netBalance = $totalIncome - $totalExpense;

        // 2. Hitung Pengeluaran Bulan Ini vs Limit Anggaran (Budgets) Bulan Ini
        $expenseThisMonth = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->sum('amount');

        $totalBudgetLimit = Budget::where('user_id', $userId)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->sum('limit_amount');

        // 3. Hitung Total Tabungan Terkumpul dari Saving Goals
        $totalSavings = SavingGoal::where('user_id', $userId)->sum('current_amount');

        return [
            'net_balance' => $netBalance,
            'month_expense' => $expenseThisMonth,
            'budget_limit' => $totalBudgetLimit,
            'total_savings' => $totalSavings,
            'budget_percentage' => $totalBudgetLimit > 0 ? min(round(($expenseThisMonth / $totalBudgetLimit) * 100), 100) : 0
        ];
    }
};
?>

{{-- Polling Realtime berjalan di latar belakang setiap 5 detik untuk sinkronisasi data --}}
<div class="max-w-7xl mx-auto space-y-6" wire:poll.5s="updateDashboardData">
    
    <div>
        <flux:heading size="xl" class="text-zinc-800 dark:text-white">Dashboard Keuangan</flux:heading>
        <flux:subheading size="lg" class="text-zinc-500 dark:text-zinc-400">Ringkasan saldo, kepatuhan anggaran, dan target tabungan Anda secara realtime.</flux:subheading>
    </div>

    <flux:separator variant="subtle" />

    {{-- KELOMPOK KOMPONEN STATISTIK RINGKASAN --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        {{-- Card 1: Saldo Bersih --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border-t-4 border-blue-600 bg-white dark:bg-zinc-900 shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center">   
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Saldo Bersih</span>
                <flux:icon icon="wallet" variant="outline" class="text-blue-600" />
            </div>
            <div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">
                    Rp {{ number_format($this->stats['net_balance'], 0, ',', '.') }}
                </div>
                <p class="text-xs text-zinc-400 mt-1">Akumulasi seluruh Pemasukan - Pengeluaran</p>
            </div>
        </div>

        {{-- Card 2: Pemakaian Anggaran Bulanan --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border-t-4 border-blue-600 bg-white dark:bg-zinc-900 shadow-sm flex flex-col justify-between">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Anggaran Bulan Ini</span>
                <flux:icon icon="credit-card" variant="outline" class="text-blue-600" />
                <svg class="w-5 h-5" ...></svg>
            </span>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white">
                    Rp {{ number_format($this->stats['month_expense'], 0, ',', '.') }}
                </div>
                <div class="flex items-center justify-between text-xs text-zinc-400 mt-1">
                    <span>Limit: Rp {{ number_format($this->stats['budget_limit'], 0, ',', '.') }}</span>
                    <span class="font-semibold text-zinc-700 dark:text-slate-300">{{ $this->stats['budget_percentage'] }}%</span>
                </div>
                {{-- Progress Bar Anggaran --}}
                <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 {{ $this->stats['budget_percentage'] >= 90 ? 'bg-red-500' : 'bg-primary-500' }}" 
                         style="width: {{ $this->stats['budget_percentage'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Card 3: Total Tabungan Terkumpul --}}
        <div class="p-5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Celengan Tabungan</span>
                <flux:icon icon="trophy" variant="outline" class="text-zinc-400" />
            </div>
            <div>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    Rp {{ number_format($this->stats['total_savings'], 0, ',', '.') }}
                </div>
                <p class="text-xs text-zinc-400 mt-1">Total alokasi dana aman di dalam Saving Goals</p>
            </div>
        </div>

    </div>

    {{-- KOMPONEN GRAFIK (CHART) --}}
    <div class="p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
        <div class="mb-4">
            <flux:heading size="lg">Tren Arus Kas (7 Hari Terakhir)</flux:heading>
            <flux:subheading size="sm">Perbandingan grafik pemasukan vs pengeluaran harian Anda.</flux:subheading>
        </div>
        
        {{-- Canvas Tempat Chart.js Digambar --}}
        <div class="h-72 w-full" id="chart-container" wire:ignore>
            <canvas id="financialChart"></canvas>
        </div>
    </div>

    {{-- INTEGRASI SCRIPT INTERAKSI CHART.JS DENGAN LIVEWIRE --}}
    @script
    <script>
        // Ambil pustaka Chart.js secara dinamis via CDN jika belum ter-install via NPM
        if (typeof Chart === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = () => initChart();
            document.head.appendChild(script);
        } else {
            initChart();
        }

        let financialChart;

        function initChart() {
            const ctx = document.getElementById('financialChart').getContext('2d');
            const data = $wire.chartData;

            financialChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Pemasukan',
                            data: data.income,
                            borderColor: '#10b981', // emerald-500
                            backgroundColor: 'rgba(0, 63, 251, 0.99)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Pengeluaran',
                            data: data.expense,
                            borderColor: '#f43f5e', // rose-500
                            backgroundColor: 'rgba(244, 63, 94, 0.05)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Mendengarkan event dari backend Livewire ketika polling berhasil memperbarui data
        $wire.on('update-chart', (event) => {
            if (financialChart) {
                financialChart.data.labels = event.data.labels;
                financialChart.data.datasets[0].data = event.data.income;
                financialChart.data.datasets[1].data = event.data.expense;
                financialChart.update('none'); // Update grafik secara halus tanpa animasi hentakan
            }
        });
    </script>
    @endscript
</div>