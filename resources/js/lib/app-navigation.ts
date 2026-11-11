import {
    Bell,
    BookOpen,
    Building2,
    Database,
    FileText,
    LayoutGrid,
    Package,
    Settings,
    ShieldCheck,
    Sparkles,
    Tag,
    Truck,
    Users,
} from 'lucide-vue-next';
import { index as fabaMastersIndex } from '@/actions/App/Http/Controllers/WasteManagement/MasterData/FabaMastersController';
import { index as auditIndex } from '@/actions/App/Http/Controllers/WasteManagement/OperationalAuditController';
import { index as inboxIndex } from '@/actions/App/Http/Controllers/WasteManagement/OperationalInboxController';
import { index as haulingPlansIndex } from '@/actions/App/Http/Controllers/WasteManagement/WasteHaulingPlansController';
import { dashboard as dashboardRoute, guide } from '@/routes';
import adminRoutes from '@/routes/admin';
import wasteManagementRoutes from '@/routes/waste-management';
import type { NavItem } from '@/types';

export const appNavigationItems: NavItem[] = [
    { title: 'Panduan', href: guide.url(), icon: BookOpen },
    {
        title: 'Riwayat Audit B3',
        href: auditIndex.url(),
        icon: FileText,
        permission: 'operational_audit.view',
    },
    {
        title: 'Notifikasi Operasional',
        href: inboxIndex.url(),
        icon: Bell,
        capability: 'operational_inbox_available',
    },
    {
        title: 'Dashboard',
        href: dashboardRoute.url(),
        icon: LayoutGrid,
    },
    {
        title: 'Catatan Limbah',
        href: wasteManagementRoutes.records.index.url(),
        icon: FileText,
        permissions: ['waste_records.view_all', 'waste_records.view_own'],
    },
    {
        title: 'Neraca Operasional Limbah',
        href: wasteManagementRoutes.balance.index.url(),
        icon: Database,
        permissions: ['waste_records.view_all', 'waste_records.view_own'],
    },
    {
        title: 'Rencana Angkut',
        href: haulingPlansIndex.url(),
        icon: Truck,
        capability: 'hauling_plans_available',
        permissions: [
            'waste_hauling.view_all',
            'waste_hauling.view_own',
            'waste_hauling.approve',
            'waste_hauling.reject',
        ],
    },
    {
        title: 'Pengangkutan Limbah',
        href: wasteManagementRoutes.haulings.index.url(),
        icon: Truck,
        permissions: ['waste_hauling.view_all', 'waste_hauling.view_own'],
    },
    {
        title: 'FABA',
        icon: Package,
        labelOnly: true,
        items: [
            {
                title: 'Dashboard FABA',
                href: wasteManagementRoutes.faba.dashboard.url(),
                icon: LayoutGrid,
                permission: 'faba_dashboard.view',
            },
            {
                title: 'Produksi',
                href: wasteManagementRoutes.faba.production.index.url(),
                icon: FileText,
                permission: 'faba_production.view',
            },
            {
                title: 'Pemanfaatan',
                href: wasteManagementRoutes.faba.utilization.index.url(),
                icon: Truck,
                permission: 'faba_utilization.view',
            },
            {
                title: 'Adjustment / Koreksi',
                href: wasteManagementRoutes.faba.adjustments.index.url(),
                icon: Sparkles,
                permission: 'faba_adjustments.view',
            },
            {
                title: 'Rekap',
                href: wasteManagementRoutes.faba.recaps.monthly.url(),
                icon: Database,
                permission: 'faba_recaps.view',
            },
            {
                title: 'Closing & Approval',
                href: wasteManagementRoutes.faba.approvals.index.url(),
                icon: ShieldCheck,
                permission: 'faba_approvals.view',
            },
            {
                title: 'Laporan',
                href: wasteManagementRoutes.faba.reports.index.url(),
                icon: Building2,
                permission: 'faba_reports.export',
            },
        ],
    },
    {
        title: 'Data Master',
        icon: Database,
        labelOnly: true,
        items: [
            {
                title: 'Jenis Limbah',
                href: wasteManagementRoutes.masterData.types.index.url(),
                icon: Package,
                permission: 'waste_types.view',
            },
            {
                title: 'Kategori Limbah',
                href: wasteManagementRoutes.masterData.categories.index.url(),
                icon: Tag,
                permission: 'waste_categories.view',
            },
            {
                title: 'Karakteristik',
                href: wasteManagementRoutes.masterData.characteristics.index.url(),
                icon: Sparkles,
                permission: 'waste_characteristics.view',
            },
            {
                title: 'Tujuan Internal FABA',
                href: fabaMastersIndex.url('destinations'),
                icon: Database,
                permission: 'faba_master.view',
            },
            {
                title: 'Peruntukan FABA',
                href: fabaMastersIndex.url('purposes'),
                icon: Database,
                permission: 'faba_master.view',
            },
            {
                title: 'Vendors',
                href: wasteManagementRoutes.masterData.vendors.index.url(),
                icon: Building2,
                permission: 'vendors.view',
            },
        ],
    },
    {
        title: 'Administration',
        icon: Settings,
        labelOnly: true,
        items: [
            {
                title: 'Organisasi',
                href: adminRoutes.organizations.index.url(),
                icon: Building2,
                permission: 'super.admin',
            },
            {
                title: 'Pengguna',
                href: adminRoutes.users.index.url(),
                icon: Users,
                permission: 'users.view',
            },
            {
                title: 'Hak Akses',
                href: adminRoutes.roles.index.url(),
                icon: ShieldCheck,
                permission: 'roles.view',
            },
        ],
    },
];
