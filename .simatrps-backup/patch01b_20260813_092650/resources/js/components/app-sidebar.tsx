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
    const items =
        auth?.user?.role === 'admin'
            ? [...lecturerNavItems, ...adminNavItems]
            : lecturerNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <div className="px-2 pb-1 text-[10px] text-sidebar-foreground/45 group-data-[collapsible=icon]:hidden">
                    Prodi Matematika · FMIPA UNSULBAR
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
