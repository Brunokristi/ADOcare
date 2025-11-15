<script setup lang="ts">
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-white">
        <div class="text-center">
            <svg class="centerpiece mx-auto" viewBox="0 0 24 24" width="160" height="160" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M12 2C13.1046 2 14 2.89543 14 4C14 5.10457 13.1046 6 12 6C10.8954 6 10 5.10457 10 4C10 2.89543 10.8954 2 12 2Z"
                    fill="#5C9EAD" />
                <path
                    d="M4 12C5.10457 12 6 12.8954 6 14C6 15.1046 5.10457 16 4 16C2.89543 16 2 15.1046 2 14C2 12.8954 2.89543 12 4 12Z"
                    fill="#5C9EAD" />
                <path
                    d="M20 12C21.1046 12 22 12.8954 22 14C22 15.1046 21.1046 16 20 16C18.8954 16 18 15.1046 18 14C18 12.8954 18.8954 12 20 12Z"
                    fill="#5C9EAD" />
                <path
                    d="M12 20C13.1046 20 14 20.8954 14 22C14 23.1046 13.1046 24 12 24C10.8954 24 10 23.1046 10 22C10 20.8954 10.8954 20 12 20Z"
                    fill="#5C9EAD" />
            </svg>

            <p class="title mt-6 text-2xl text-[#5C9EAD]">AdoCare</p>
        </div>
    </div>
</template>

<style scoped>
.centerpiece {
    animation: spin 50s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}
</style>
