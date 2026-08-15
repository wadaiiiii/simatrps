import { Link, usePage } from '@inertiajs/react';

import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();

    return (
        <SidebarGroup className="px-2 py-1">
            <SidebarGroupContent>
                <SidebarMenu className="gap-1">
                    {items.map((item) => {
                        const href = String(item.href);
                        const active =
                            page.url === href ||
                            page.url.startsWith(`${href}/`);

                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={active}
                                    tooltip={{ children: item.title }}
                                    className="h-10 rounded-xl text-white/90
                                        hover:bg-white/10 hover:text-white
                                        data-[active=true]:bg-white/15
                                        data-[active=true]:font-semibold
                                        data-[active=true]:text-white
                                        [&_svg]:text-cyan-100
                                        group-data-[collapsible=icon]:mx-auto
                                        group-data-[collapsible=icon]:justify-center"
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
