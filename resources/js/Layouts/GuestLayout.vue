<script setup>
import { Toaster } from '@/Components/ui/sonner'
</script>

<template>
  <div class="min-h-screen bg-background flex flex-col md:flex-row">
    <!-- Left side: Visuals/Info -->
    <div class="hidden md:flex md:w-1/2 bg-muted items-center justify-center p-12 relative overflow-hidden">
        <div class="absolute inset-0 bg-primary/10 pointer-events-none"></div>
        <div class="z-10 text-center flex flex-col gap-6 max-w-lg">
            <h1 class="text-4xl font-extrabold tracking-tight">System Drukarni ERP</h1>
            <p class="text-xl text-muted-foreground italic">
                "Innowacja w każdym arkuszu. Zarządzaj produkcją, klientami i finansami w jednym miejscu."
            </p>
            <div class="pt-12 border-t border-muted-foreground/20">
                <div class="bg-card p-4 rounded-lg shadow-sm border text-left">
                    <p class="text-sm font-semibold mb-1">Status Systemu:</p>
                    <div class="flex items-center gap-2">
                        <div class="size-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm">Wszystkie systemy działają poprawnie.</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Decorative blobs -->
        <div class="absolute -top-24 -left-24 size-64 bg-primary/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -right-24 size-64 bg-primary/20 rounded-full blur-3xl"></div>
    </div>

    <!-- Right side: Content -->
    <div class="flex-1 flex items-center justify-center p-6 md:p-12 lg:p-24 bg-background">
      <div class="w-full max-w-md flex flex-col gap-8">
        <div class="md:hidden text-center mb-8">
            <h1 class="text-2xl font-bold tracking-tight">DRUKARNIA ERP</h1>
        </div>
        <slot />
      </div>
    </div>
    <Toaster />
  </div>
</template>
