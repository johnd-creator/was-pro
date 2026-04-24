<?php

namespace App\Console\Commands;

use App\Services\FabaDemoDataService;
use App\Services\WasteManagementDemoDataService;
use Illuminate\Console\Command;
use Throwable;

class SeedDemoData extends Command
{
    protected $signature = 'demo:seed
        {--tenant=TWMSDEMO : Kode organisasi demo}
        {--schema= : Nama schema tenant demo}
        {--fresh-tenant : Hapus schema tenant demo dan isi ulang dari awal}';

    protected $description = 'Siapkan tenant demo terintegrasi untuk modul limbah umum dan FABA dalam satu command';

    public function __construct(
        protected WasteManagementDemoDataService $wasteManagementDemoDataService,
        protected FabaDemoDataService $fabaDemoDataService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantCode = (string) $this->option('tenant');
        $schemaName = $this->option('schema') ? (string) $this->option('schema') : null;
        $freshTenant = (bool) $this->option('fresh-tenant');

        try {
            $wasteSummary = $this->wasteManagementDemoDataService->seedDemoData(
                $tenantCode,
                $schemaName,
                $freshTenant,
            );

            $fabaSummary = $this->fabaDemoDataService->seedDemoData(
                $tenantCode,
                $schemaName ?? $wasteSummary['schema_name'],
                false,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Tenant demo terintegrasi berhasil disiapkan.');
        $this->newLine();
        $this->line('Organisasi: '.$wasteSummary['organization']->name.' ('.$wasteSummary['organization']->code.')');
        $this->line('Schema: '.$wasteSummary['schema_name']);
        $this->line('Periode: '.implode(', ', $wasteSummary['periods']));
        $this->newLine();
        $this->comment('Waste Management');
        $this->line('- Kategori limbah: '.$wasteSummary['categories_count']);
        $this->line('- Karakteristik limbah: '.$wasteSummary['characteristics_count']);
        $this->line('- Jenis limbah: '.$wasteSummary['waste_types_count']);
        $this->line('- Vendor aktif: '.$wasteSummary['vendors_count']);
        $this->line('- Catatan limbah: '.$wasteSummary['waste_records_count']);
        $this->line('- Pengangkutan limbah: '.$wasteSummary['haulings_count']);
        $this->newLine();
        $this->comment('FABA');
        $this->line('- Vendor aktif: '.$fabaSummary['vendors_count']);
        $this->line('- Tujuan internal aktif: '.$fabaSummary['internal_destinations_count']);
        $this->line('- Purpose aktif: '.$fabaSummary['purposes_count']);
        $this->line('- Movement FABA: '.$fabaSummary['movements_count']);
        $this->line('- Approval bulanan: '.$fabaSummary['approvals_count']);
        $this->line('- Opening balance eksplisit: '.$fabaSummary['opening_balances_count']);
        $this->line('- Closing snapshot: '.$fabaSummary['snapshots_count']);
        $this->newLine();
        $this->comment('Login demo:');
        $this->line('- Super Admin: john@d.co / password');
        $this->line('- Waste Supervisor: wm.supervisor.demo@local.test / password');
        $this->line('- Waste Operator: wm.operator.demo@local.test / password');
        $this->line('- FABA Supervisor: faba.supervisor.demo@local.test / password');
        $this->line('- FABA Operator: faba.operator.demo@local.test / password');

        return self::SUCCESS;
    }
}
