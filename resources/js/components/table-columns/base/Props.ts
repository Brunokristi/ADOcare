import type { PropType } from 'vue';





export interface BaseColumnProps {
    row: Record<string, any>;
    value?: any;
    column?: Record<string, any>;
    index?: number;
    customOptions?: Record<string, any>;
}

// Runtime props object (for Options API / defineComponent)
export const baseColumnProps = {
    row: { type: Object as PropType<Record<string, any>>, required: true },
    value: { required: false },
    column: { type: Object as PropType<Record<string, any>>, required: false },
    index: { type: Number as PropType<number>, required: false },
    customOptions: { type: Object as PropType<Record<string, any>>, required: false },
};
