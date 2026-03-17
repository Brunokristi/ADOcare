import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiOverlayStore = defineStore('ui', () => {
    const contentLoading = ref(false)

    function setContentLoading(value: boolean) {
        contentLoading.value = value
    }

    return {
        contentLoading,
        setContentLoading,
    }
})