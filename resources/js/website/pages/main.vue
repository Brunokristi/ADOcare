<script setup lang="ts">
// Your website homepage content here
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 py-12">
        <section class="text-center mb-16">
            <h1 class="text-5xl font-bold text-gray-900 mb-4">
                Welcome to ADOcare
            </h1>
            <p class="text-xl text-gray-600 mb-8">
                Your professional healthcare management solution
            </p>
            <router-link
                to="/"
                class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 inline-block"
            >
                Get Started
            </router-link>
        </section>

        <!-- Add your website content sections here -->
        <section class="grid md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="text-4xl mb-4">🏥</div>
                <h2 class="text-2xl font-bold mb-2">Healthcare</h2>
                <p class="text-gray-600">Professional healthcare management tools</p>
            </div>
            <div class="text-center">
                <div class="text-4xl mb-4">📊</div>
                <h2 class="text-2xl font-bold mb-2">Analytics</h2>
                <p class="text-gray-600">Comprehensive data analytics and reporting</p>
            </div>
            <div class="text-center">
                <div class="text-4xl mb-4">🔒</div>
                <h2 class="text-2xl font-bold mb-2">Security</h2>
                <p class="text-gray-600">Enterprise-grade security and compliance</p>
            </div>
        </section>
    </div>
</template>
