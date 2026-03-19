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
    Trash2,
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
        description: 'Pantau prioritas, persetujuan, dan risiko operasional.',
    },
    {
        title: 'Operasional Limbah',
        icon: Trash2,
        defaultOpen: true,
        labelOnly: true,
        description: 'Akses tugas harian pencatatan dan pengangkutan limbah.',
        items: [
            {
                title: 'Catatan Limbah',
                href: wasteManagementRoutes.records.index.url(),
                icon: FileText,
                description:
                    'Lihat status catatan, pengajuan, dan tindak lanjut.',
                permissions: [
                    'waste_records.view_all',
                    'waste_records.view_own',
                ],
            },
            {
                title: 'Transportasi',
                href: wasteManagementRoutes.transportations.index.url(),
                icon: Truck,
                description: 'Pantau jadwal, pengiriman, dan penyelesaian.',
                permissions: [
                    'transportation.view_all',
                    'transportation.view_own',
                ],
            },
        ],
    },
    {
        title: 'Data Master',
        icon: Database,
        labelOnly: true,
        description: 'Kelola referensi inti untuk pencatatan limbah.',
        items: [
            {
                title: 'Jenis Limbah',
                href: wasteManagementRoutes.masterData.types.index.url(),
                icon: Package,
                description: 'Definisikan jenis limbah yang dapat dicatat.',
                permission: 'waste_types.view',
            },
            {
                title: 'Kategori Limbah',
                href: wasteManagementRoutes.masterData.categories.index.url(),
                icon: Tag,
                description:
                    'Atur kelompok limbah untuk pelaporan dan analisis.',
                permission: 'waste_categories.view',
            },
            {
                title: 'Karakteristik',
                href: wasteManagementRoutes.masterData.characteristics.index.url(),
                icon: Sparkles,
                description: 'Kelola karakteristik limbah untuk klasifikasi.',
                permission: 'waste_characteristics.view',
            },
            {
                title: 'Vendors',
                href: wasteManagementRoutes.masterData.vendors.index.url(),
                icon: Building2,
                description: 'Simpan mitra pengangkutan dan pengelolaan.',
                permission: 'vendors.view',
            },
        ],
    },
    {
        title: 'Administration',
        icon: Settings,
        labelOnly: true,
        description: 'Kelola organisasi, pengguna, dan hak akses sistem.',
        items: [
            {
                title: 'Organisasi',
                href: adminRoutes.organizations.index.url(),
                icon: Building2,
                description: 'Atur organisasi dan cakupan tenancy.',
                permission: 'super.admin',
            },
            {
                title: 'Pengguna',
                href: adminRoutes.users.index.url(),
                icon: Users,
                description: 'Kelola akun, peran, dan otorisasi operasional.',
                permission: 'users.view',
            },
            {
                title: 'Hak Akses',
                href: adminRoutes.roles.index.url(),
                icon: ShieldCheck,
                description: 'Atur izin dan peran untuk tiap fungsi kerja.',
                permission: 'roles.view',
            },
        ],
    },
];
