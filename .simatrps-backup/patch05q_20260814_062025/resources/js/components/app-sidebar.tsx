import { Link, usePage } from '@inertiajs/react';
import {
    BookOpenCheck,
    FilePlus2,
    Files,
    LayoutDashboard,
    LibraryBig,
    Users,
} from 'lucide-react';

import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

type SimatRpsPageProps = {
    auth: {
        user: {
            role?: string;
        };
    };
};

const lecturerNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { title: 'Buat RPS', href: '/rps/baru', icon: FilePlus2 },
    { title: 'RPS Saya', href: '/rps', icon: Files },
];

const adminNavItems: NavItem[] = [
    { title: 'Kurikulum', href: '/admin/kurikulum', icon: LibraryBig },
    { title: 'Template RPS', href: '/admin/template-rps', icon: BookOpenCheck },
    { title: 'Pengguna', href: '/admin/pengguna', icon: Users },
];

export function AppSidebar() {
    const { auth } = usePage<SimatRpsPageProps>().props;
    const isAdmin = auth?.user?.role === 'admin';
    const items = isAdmin ? [...lecturerNavItems, ...adminNavItems] : lecturerNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset" className="border-r border-teal-100/80">
            <SidebarHeader className="border-b border-teal-100/80">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-teal-50">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="px-1.5 pt-2">
                <div className="px-2 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 group-data-[collapsible=icon]:hidden">
                    Platform
                </div>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter className="border-t border-teal-100/80">
                <div className="px-2 pb-2 text-[10px] leading-4 text-slate-400 group-data-[collapsible=icon]:hidden">
                    Program Studi Matematika<br />
                    FMIPA Universitas Sulawesi Barat
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
