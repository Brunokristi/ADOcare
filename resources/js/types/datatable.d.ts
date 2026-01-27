// Types for UniversalDataTable

import type { useRemoteTable } from "@/composables/useRemoteTable";

// loaders or plain components.
type VueComponent = Component | (() => Promise<Component>);
type ValueFormatter<T = any> = (value: any, row: T) => string | number | null;

export type RemoteTableReturn = ReturnType<typeof useRemoteTable>;

interface ColumnDef<T = any> {
    // key on the row object; optional if using custom slot/component
    field?: string;
    // header label
    header?: string;
    // sortable and filterable flags
    sortable?: boolean;
    filterable?: boolean;
    // width/style
    width?: string;
    style?: string;
    align?: 'left' | 'center' | 'right';
    // a Vue component (sync) or an async component loader
    component?: VueComponent;
    componentOptions?: Record<string, any>;
    // optional scoped slot name to use (slot receives { row, value })
    slot?: string;
    // fallback value formatter if no component/slot
    render?: ValueFormatter<T>;
    // exportable flag
    exportable?: boolean;

}

interface ActionDef<T = any> {
    key: string;
    label?: string;
    class?: string;
    tooltip?: string;
    disabled?: boolean | ((params: { rows: T[], selectedRows: T[], remote: RemoteTableReturn }) => boolean);
    icon?: string | ((params: { rows: T[], selectedRows: T[], remote: RemoteTableReturn }) => string);
    // optional confirm text or boolean
    confirm?: string | boolean;
    // handler may be provided here or via component prop mapping
    handler?: (params: { rows: T[], selectedRows: T[], remote: RemoteTableReturn }) => Promise<void> | void;
}

interface DataTableOptions<T = any> {
    endpointUrl: string;
    columns: ColumnDef<T>[];
    afterInit?: function({ remote: RemoteTableReturn }): void;
    rowKey?: string;
    selectable?: boolean;
    actions?: ActionDef<T>[];
    defaultPageSize?: number;
    pageSizeOptions?: number[];
    // extra params appended to each request (filters, fixed scope)
    extraParams?: Record<string, any>;
}

interface RemoteLoadResult<T = any> {
    items: T[];
    total: number;
}
