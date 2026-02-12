<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import appRouter from '@/router';
import { capitalize } from '@/utils/formatUtils';
import useAuthStore from '@/stores/auth';

const auth = useAuthStore();

interface RawRoute {
    path: string;
    name?: string;
    meta?: Record<string, any>;
    children?: RawRoute[];
}

interface SidebarChild {
    key: string;
    label: string;
    path: string;
}

interface SidebarItem {
    key: string;
    label: string;
    path: string;
    children?: SidebarChild[];
}

function buildSidebarItems(routes: RawRoute[]): SidebarItem[] {
    const items: SidebarItem[] = [];

    const isManager = auth.currentRole === 'manager';

    for (const r of routes) {
        const meta = r.meta ?? {};


        if (isManager ? !meta?.managerSidebar : !meta.sidebar) continue;



        const children: SidebarChild[] = (r.children ?? [])
            .filter((c) => {
                if (isManager) {
                    return c.meta?.managerSidebar === true;
                }
                return c.meta?.sidebar
            })
            .map((c) => {
                const childMeta = c.meta ?? {};

                const rawLabel =
                    childMeta.link ??
                    childMeta.title ??
                    String(c.name ?? c.path);

                const label = capitalize(rawLabel);

                const fullPath = c.path.startsWith('/')
                    ? c.path
                    : `${r.path.replace(/\/$/, '')}/${c.path}`;

                return {
                    key: String(c.name ?? c.path),
                    label,
                    path: fullPath,
                };
            });

        const rawLabel =
            meta.title ??
            meta.link ??
            String(r.name ?? r.path);

        const label = capitalize(rawLabel);

        const item: SidebarItem = {
            key: String(meta.sectionRoot ?? r.name ?? r.path),
            label,
            path: r.path,
            children: children.length ? children : undefined,
        };

        items.push(item);
    }

    return items;
}


// use the same hierarchy you defined in router/index.ts
const rawRoutes = appRouter.options.routes as RawRoute[];
// const sidebarItems = ref<SidebarItem[]>(buildSidebarItems(rawRoutes));
const sidebarItems = computed(() => buildSidebarItems(rawRoutes));

// open/close state per section (dose/settings/etc.), persisted
type OpenState = Record<string, boolean>;

const openState = ref<OpenState>(
    JSON.parse(localStorage.getItem('sidebar.openState') ?? '{}'),
);

function isOpen(key: string): boolean {
    return !!openState.value[key];
}

function toggle(key: string) {
    openState.value[key] = !isOpen(key);
}

watch(
    openState,
    (val) => {
        localStorage.setItem('sidebar.openState', JSON.stringify(val));
    },
    { deep: true },
);

</script>

<template>
    <aside class="flex flex-col w-64 h-full bg-tag3 border-0! text-darkgrey! p-4 space-y-1">
        <!-- Loop through all sidebar items built from routes -->
        <template v-for="item in sidebarItems" :key="item.key">
            <RouterLink v-if="!item.children || !item.children.length"
                class="w-full text-left px-3 py-2 rounded-md hover:bg-almostwhite hover:text-accent!" :to="item.path">
                {{ item.label }}
            </RouterLink>

            <div v-else>
                <button
                    class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-almostwhite hover:text-accent!"
                    @click="toggle(item.key)">
                    <span>{{ item.label }}</span>
                    <i :class="isOpen(item.key) ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                </button>

                <ul v-show="isOpen(item.key)" class="mt-1 ml-4 space-y-1 text-mini">
                    <li v-for="child in item.children" :key="child.key">
                        <RouterLink :to="child.path"
                            class="block px-3 py-1 rounded-md hover:bg-almostwhite hover:text-accent!">
                            {{ child.label }}
                        </RouterLink>
                    </li>
                </ul>
            </div>
        </template>
    </aside>
</template>
