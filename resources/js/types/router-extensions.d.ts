import 'vue-router'

declare module 'vue-router' {
    interface Router {
        dashboard(): Promise<void>
    }
}
