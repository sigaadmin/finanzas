import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    FileText,
    HandCoins,
    Landmark,
    LayoutGrid,
    ReceiptText,
    TableProperties,
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
import { dashboard } from '@/routes';
import finance from '@/routes/finance';
import * as ownRevenueBudgets from '@/routes/finance/own-revenue/budgets';
import type { NavItem } from '@/types';

const portalItems: NavItem[] = [
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
        title: 'Recibos',
        href: finance.receipts.index(),
        icon: ReceiptText,
    },
];

const budgetItems: NavItem[] = [
    {
        title: 'U300',
        href: finance.u300.programs.index(),
        icon: Landmark,
    },
    {
        title: 'Ingresos propios',
        href: ownRevenueBudgets.index(),
        icon: HandCoins,
    },
];

export function AppSidebar() {
    const { permissions } = usePage().props.auth;

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
                {permissions.ventanilla && <NavMain label="Portal" items={portalItems} />}
                {(permissions.u300 || permissions.own_revenue) && (
                    <NavMain
                        label="Presupuesto"
                        items={budgetItems.filter((item) =>
                            item.title === 'U300' ? permissions.u300 : permissions.own_revenue,
                        )}
                        className="px-2 py-0 mt-4"
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
