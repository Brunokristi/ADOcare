// Types for UniversalDataTable
type ValueFormatter<T = any> = (value: any, row: T) => string | number | null;

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
    component?: Component;
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
    disabled?: boolean | ((params: { rows: T[], selectedRows: T[], remote: any }) => boolean);
    icon?: string;
    // optional confirm text or boolean
    confirm?: string | boolean;
    // handler may be provided here or via component prop mapping
    handler?: (params: { rows: T[], selectedRows: T[], remote: any }) => Promise<void> | void;
}

interface DataTableOptions<T = any> {
    endpointUrl: string;
    columns: ColumnDef<T>[];
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
