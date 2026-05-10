<template>
    <MainLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h2 class="text-2xl font-semibold mb-4">Welcome to Drukarnia ERP Phase 0</h2>
                        <p>This is a scaffolding test page to confirm the stack is working.</p>
                        <p class="mt-4 text-sm text-gray-500">PHP Version: {{ phpVersion }}</p>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '../Layouts/MainLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>
