<?php

namespace App\Console\Commands;

use App\Services\FabaDemoDataService;
use Illuminate\Console\Command;
use Throwable;

class SeedFabaDemoData extends Command
{
    protected $signature = 'faba:seed-demo
        {--tenant=TWMSDEMO : Kode organisasi demo}
        {--schema= : Nama schema tenant demo}
        {--fresh-tenant : Hapus schema tenant demo dan isi ulang dari awal}';

    protected $description = 'Siapkan tenant demo FABA dengan data dummy 3 bulan terakhir';

    public function __construct(protected FabaDemoDataService $fabaDemoDataService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $summary = $this->fabaDemoDataService->seedDemoData(
                (string) $this->option('tenant'),
                $this->option('schema') ? (string) $this->option('schema') : null,
                (bool) $this->option('fresh-tenant'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Tenant demo FABA berhasil disiapkan.');
        $this->newLine();
        $this->line('Organisasi: '.$summary['organization']->name.' ('.$summary['organization']->code.')');
        $this->line('Schema: '.$summary['schema_name']);
        $this->line('Periode: '.implode(', ', $summary['periods']));
        $this->line('Vendor aktif: '.$summary['vendors_count']);
        $this->line('Transaksi produksi: '.$summary['production_count']);
        $this->line('Transaksi pemanfaatan: '.$summary['utilization_count']);
        $this->line('Approval bulanan: '.$summary['approvals_count']);
        $this->line('Opening balance eksplisit: '.$summary['opening_balances_count']);
        $this->newLine();
        $this->comment('Login demo:');
        $this->line('- Supervisor: faba.supervisor.demo@local.test / password');
        $this->line('- Operator: faba.operator.demo@local.test / password');

        return self::SUCCESS;
    }
}
