import { reactive, toRefs, watch } from 'vue'

type ValidatorFn = (value: any, values?: Record<string, any>) => string | null

export function required(msg = 'Toto pole je povinné'): ValidatorFn {
    return (v) => (v === null || v === undefined || String(v).trim() === '') ? msg : null
}

export function email(msg = 'Neplatný email'): ValidatorFn {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return (v) => (!v || re.test(v)) ? null : msg
}

export function minLength(n: number, msg?: string): ValidatorFn {
    return (v) => (v && String(v).length >= n) ? null : (msg ?? `Minimálna dĺžka je ${n}`)
}

export default function useFormValidator(schema: Record<string, ValidatorFn[]>, getValues: () => Record<string, any>) {
    const state = reactive({ errors: {} as Record<string, string | null>, touched: {} as Record<string, boolean> })

    function validateField(name: string) {
        const validators = schema[name] ?? []
        const values = getValues()
        const value = values[name]
        for (const v of validators) {
            const res = v(value, values)
            if (res) {
                state.errors[name] = res
                return false
            }
        }
        state.errors[name] = null
        return true
    }

    function validateAll() {
        let ok = true
        for (const name of Object.keys(schema)) {
            const res = validateField(name)
            ok = ok && res
        }
        return ok
    }

    function setTouched(name: string, val = true) {
        state.touched[name] = val
    }

    // auto-validate touched fields when values change
    watch(getValues, () => {
        for (const name of Object.keys(state.touched)) {
            if (state.touched[name]) validateField(name)
        }
    }, { deep: true })

    function reset() {
        state.errors = {}
        state.touched = {}
    }

    return {
        ...toRefs(state),
        getError: (name: string) => state.errors[name] ?? null,
        validateField,
        validateAll,
        setTouched,
        reset,
    }
}
