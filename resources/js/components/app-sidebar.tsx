import { Link } from '@inertiajs/react';
import { ClipboardList, FileText, Landmark, LayoutGrid, ReceiptText, TableProperties } from 'lucide-react';
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
import { dashboard } from '@/routes';
import finance from '@/routes/finance';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Trámites',
        href: finance.paymentProcedures.index(),
        icon: ClipboardList,
    },
    {
        title: 'Conceptos',
        href: finance.chargeConcepts.index(),
        icon: FileText,
    },
    {
        title: 'Reporte SEQ',
        href: finance.seqReport.index(),
        icon: TableProperties,
    },
    {
        title: 'Presupuesto U300',
        href: finance.u300.programs.index(),
        icon: Landmark,
    },
    {
        title: 'Recibos',
        href: finance.receipts.index(),
        icon: ReceiptText,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
