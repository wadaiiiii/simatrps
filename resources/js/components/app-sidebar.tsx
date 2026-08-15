import { Link, usePage } from '@inertiajs/react';
import {
    BookOpenCheck,
    FilePlus2,
    Files,
    LayoutDashboard,
    LibraryBig,
    Users,
} from 'lucide-react';

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
    SidebarTrigger,
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
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="border-r border-cyan-950/30 text-white
                [&_[data-sidebar=sidebar]]:bg-gradient-to-b
                [&_[data-sidebar=sidebar]]:from-[#04182d]
                [&_[data-sidebar=sidebar]]:via-[#07384b]
                [&_[data-sidebar=sidebar]]:to-[#075a56]
                [&_[data-sidebar=menu-button]]:text-white
                [&_[data-sidebar=menu-button]:hover]:bg-white/10
                [&_[data-sidebar=menu-button]:hover]:text-white"
        >
            <SidebarHeader className="border-b border-white/10 bg-transparent px-3 py-3 group-data-[collapsible=icon]:px-1.5">
                <SidebarMenu className="group-data-[collapsible=icon]:items-center">
                    <SidebarMenuItem className="group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center">
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto rounded-xl bg-transparent px-1.5 py-1.5 hover:bg-white/5
                                group-data-[collapsible=icon]:mx-auto
                                group-data-[collapsible=icon]:size-11
                                group-data-[collapsible=icon]:justify-center
                                group-data-[collapsible=icon]:p-0"
                        >
                            <Link href="/dashboard" prefetch>
                                <div className="relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-300 to-teal-400 text-lg font-black text-slate-950 shadow-md shadow-black/15">
                                    S
                                    <span className="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-[#04182d] bg-amber-400" />
                                </div>

                                <div className="ml-1.5 grid flex-1 text-left group-data-[collapsible=icon]:hidden">
                                    <span className="truncate text-[15px] font-extrabold leading-tight tracking-tight text-white">
                                        SiMatRPS
                                    </span>
                                    <span className="truncate text-[10px] font-medium leading-tight text-cyan-100/90">
                                        RPS Berbasis OBE
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <div className="px-2 pt-1 text-[10px] font-medium leading-4 text-white/80 group-data-[collapsible=icon]:hidden">
                    <div className="font-semibold text-white/95">Program Studi Matematika</div>
                    <div>FMIPA Universitas Sulawesi Barat</div>
                </div>
            </SidebarHeader>

            <SidebarContent className="bg-transparent px-1.5 pt-3">
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter className="border-t border-white/10 bg-transparent text-white">
                <div className="mb-1 flex items-center justify-end px-1 group-data-[collapsible=icon]:justify-center">
                    <SidebarTrigger
                        className="size-8 rounded-lg border border-white/15 bg-white/10 text-white shadow-sm hover:bg-white/20 hover:text-white"
                        title="Tampilkan / minimalkan menu"
                    />
                </div>

                <div className="[&_[data-sidebar=menu-button]]:text-white [&_[data-sidebar=menu-button]:hover]:bg-white/10 [&_[data-sidebar=menu-button]:hover]:text-white">
                    <NavUser />
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}
