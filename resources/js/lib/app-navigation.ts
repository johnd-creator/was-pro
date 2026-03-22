import {
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
import { dashboard as dashboardRoute } from '@/routes';
import adminRoutes from '@/routes/admin';
import wasteManagementRoutes from '@/routes/waste-management';
import type { NavItem } from '@/types';

export const appNavigationItems: NavItem[] = [
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
        title: 'Transportasi',
        href: wasteManagementRoutes.transportations.index.url(),
        icon: Truck,
        permissions: ['transportation.view_all', 'transportation.view_own'],
    },
    {
        title: 'FABA',
        icon: Package,
        labelOnly: true,
        items: [
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
                title: 'Rekap',
                href: wasteManagementRoutes.faba.recaps.monthly.url(),
                icon: Database,
                permission: 'faba_recaps.view',
            },
            {
                title: 'Approval',
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
