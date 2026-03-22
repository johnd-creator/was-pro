<?php

namespace App\Console\Commands;

use App\Services\WasteManagementDemoDataService;
use Illuminate\Console\Command;
use Throwable;

class SeedWasteManagementDemoData extends Command
{
    protected $signature = 'waste-management:seed-demo
        {--tenant=TWMSDEMO : Kode organisasi demo}
        {--schema= : Nama schema tenant demo}
        {--fresh-tenant : Hapus schema tenant demo dan isi ulang dari awal}';

    protected $description = 'Siapkan tenant demo limbah umum dengan data dummy 3 bulan terakhir';

    public function __construct(protected WasteManagementDemoDataService $wasteManagementDemoDataService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $summary = $this->wasteManagementDemoDataService->seedDemoData(
                (string) $this->option('tenant'),
                $this->option('schema') ? (string) $this->option('schema') : null,
                (bool) $this->option('fresh-tenant'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Tenant demo limbah umum berhasil disiapkan.');
        $this->newLine();
        $this->line('Organisasi: '.$summary['organization']->name.' ('.$summary['organization']->code.')');
        $this->line('Schema: '.$summary['schema_name']);
        $this->line('Periode: '.implode(', ', $summary['periods']));
        $this->line('Kategori limbah: '.$summary['categories_count']);
        $this->line('Karakteristik limbah: '.$summary['characteristics_count']);
        $this->line('Jenis limbah: '.$summary['waste_types_count']);
        $this->line('Vendor aktif: '.$summary['vendors_count']);
        $this->line('Catatan limbah: '.$summary['waste_records_count']);
        $this->line('Pengangkutan limbah: '.$summary['transportations_count']);
        $this->newLine();
        $this->comment('Login demo:');
        $this->line('- Supervisor: wm.supervisor.demo@local.test / password');
        $this->line('- Operator: wm.operator.demo@local.test / password');

        return self::SUCCESS;
    }
}
