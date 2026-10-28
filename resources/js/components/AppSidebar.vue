<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Map,
    ShieldCheck,
    AlertTriangle,
    Bell,
    BellRing,
    MessagesSquare,
    ShieldAlert,
    SlidersHorizontal,
    PhoneCall,
    Camera,
    HandHelping,
    Search,
    Sparkles,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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

const page = usePage();
const user = computed(() => page.props.auth?.user);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Mapa de Riesgo',
            href: '/map',
            icon: Map,
        },
        {
            title: 'Zonas Seguras',
            href: '/safe-zones',
            icon: ShieldCheck,
        },
        {
            title: 'Reportes Ciudadanos',
            href: '/reports',
            icon: AlertTriangle,
        },
        {
            title: 'Evidencias Ciudadanas',
            href: '/evidence',
            icon: Camera,
        },
        {
            title: 'Objetos perdidos y encontrados',
            href: '/lost-found',
            icon: Search,
        },
        {
            title: 'Necesito ayuda',
            href: '/help',
            icon: HandHelping,
        },
        {
            title: 'Alertas Activas',
            href: '/alerts',
            icon: Bell,
        },
        {
            title: 'Centro de comunicación',
            href: '/communication',
            icon: MessagesSquare,
        },
        {
            title: 'Notificaciones',
            href: '/notifications',
            icon: BellRing,
        },
        {
            title: 'Modo RESGUARDA',
            href: '/resguarda-mode',
            icon: ShieldAlert,
        },
        {
            title: 'Asistente inteligente',
            href: '/assistant',
            icon: Sparkles,
        },
    ];

    if (user.value?.role === 'admin') {
        items.push({
            title: 'Panel Administrativo',
            href: '/admin',
            icon: SlidersHorizontal,
        });
    }

    return items;
});

const emergencyNavItems: NavItem[] = [
    {
        title: 'Línea de Emergencia',
        href: '/emergency',
        icon: PhoneCall,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/map">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="emergencyNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
