<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import appRouter from '@/router';

const router = useRouter();

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

// Build sidebar items from your router config
function buildSidebarItems(routes: RawRoute[]): SidebarItem[] {
  const items: SidebarItem[] = [];

  for (const r of routes) {
    const meta = r.meta ?? {};

    // only consider routes explicitly marked for sidebar
    if (!meta.sidebar) continue;

    // children-based “section” (like /settings + its children)
    const children: SidebarChild[] = (r.children ?? [])
      .filter((c) => c.meta?.sidebar)
      .map((c) => {
        const childMeta = c.meta ?? {};
        const label =
          childMeta.link ??
          childMeta.title ??
          String(c.name ?? c.path);

        // normalize full path: if child path is relative, prepend parent
        const fullPath = c.path.startsWith('/')
          ? c.path
          : `${r.path.replace(/\/$/, '')}/${c.path}`;

        return {
          key: String(c.name ?? c.path),
          label,
          path: fullPath,
        };
      });

    const label =
      meta.title ??
      meta.link ??
      String(r.name ?? r.path);

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
const sidebarItems = ref<SidebarItem[]>(buildSidebarItems(rawRoutes));

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

function go(path: string) {
  router.push(path);
}
</script>

<template>
  <aside class="w-64 h-full bg-tag3 !border-0 !text-darkgrey p-4 space-y-1">
    <!-- Loop through all sidebar items built from routes -->
    <div
      v-for="item in sidebarItems"
      :key="item.key"
    >
      <!-- Simple item (no children) -> behaves like your Pacienti button -->
      <button
        v-if="!item.children || !item.children.length"
        class="w-full text-left px-3 py-2 rounded-md hover:bg-almostwhite hover:!text-accent"
        @click="go(item.path)"
      >
        {{ item.label }}
      </button>

      <!-- Section with children -> behaves like your Dávka / Nastavenia blocks -->
      <div v-else>
        <button
          class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-almostwhite hover:!text-accent"
          @click="toggle(item.key)"
        >
          <span>{{ item.label }}</span>
          <i :class="isOpen(item.key) ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
        </button>

        <ul
          v-show="isOpen(item.key)"
          class="mt-1 ml-4 space-y-1 text-mini"
        >
          <li
            v-for="child in item.children"
            :key="child.key"
          >
            <RouterLink
              :to="child.path"
              class="block px-3 py-1 rounded-md hover:bg-almostwhite hover:!text-accent"
            >
              {{ child.label }}
            </RouterLink>
          </li>
        </ul>
      </div>
    </div>
  </aside>
</template>
