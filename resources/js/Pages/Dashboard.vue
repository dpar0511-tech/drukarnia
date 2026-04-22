<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Form } from '@inertiajs/vue3'
import { useAuth } from '@/Composables/useAuth'
import { ShoppingCart, MessageSquare, Factory, Plus, UserPlus, Calculator } from 'lucide-vue-next'

const { user, fullName, can, hasRole } = useAuth()

defineProps({
    version: {
        type: String,
        default: '1.0.0'
    },
    stats: Object,
})
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <div class="flex flex-col gap-8">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Cześć, {{ fullName }}!</h1>
        <p class="text-muted-foreground mt-1">Oto co dzieje się dzisiaj w Drukarni.</p>
      </div>
      
      <!-- Skeleton Stats while loading -->
      <div v-if="!stats" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card v-for="i in 4" :key="i" class="animate-pulse">
          <CardHeader class="pb-2">
            <div class="h-4 bg-muted rounded w-1/2"></div>
          </CardHeader>
          <CardContent>
            <div class="h-8 bg-muted rounded w-1/4 mb-2"></div>
            <div class="h-3 bg-muted rounded w-2/3"></div>
          </CardContent>
        </Card>
      </div>
      
      <!-- Stats Cards -->
      <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card v-if="can('view_orders')">
          <CardHeader class="flex flex-row items-center justify-between gap-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Aktywne zamówienia</CardTitle>
            <ShoppingCart class="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.active_orders }}</div>
            <p class="text-xs text-green-600 font-medium">+2 od ostatniej godziny</p>
          </CardContent>
        </Card>

        <Card v-if="can('view_communication')">
          <CardHeader class="flex flex-row items-center justify-between gap-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Wiadomości</CardTitle>
            <MessageSquare class="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.unread_emails }}</div>
            <p class="text-xs text-destructive font-medium">1 priorytetowe</p>
          </CardContent>
        </Card>

        <Card v-if="can('view_production')">
          <CardHeader class="flex flex-row items-center justify-between gap-0 pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Postęp produkcji</CardTitle>
            <Factory class="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.production_progress }}%</div>
            <p class="text-xs text-muted-foreground">Realizacja planu dziennego</p>
          </CardContent>
        </Card>

        <Card v-if="hasRole('Admin') || hasRole('Menedżer')">
          <CardHeader class="flex flex-row items-center justify-between pb-2 gap-0">
            <CardTitle class="text-sm font-medium text-muted-foreground">Oczekujące wyceny</CardTitle>
            <Calculator class="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">5 zapytań</div>
            <p class="text-xs text-muted-foreground">Wymagają uwagi</p>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle>Szybkie akcje</CardTitle>
            <CardDescription>Najczęściej wykonywane zadania</CardDescription>
          </CardHeader>
          <CardContent class="flex flex-wrap gap-4">
            <Button v-if="can('create_orders')" class="gap-2 h-11">
              <Plus /> Nowe zamówienie
            </Button>
            <Button v-if="can('create_clients')" variant="outline" class="gap-2 h-11">
              <UserPlus /> Dodaj klienta
            </Button>
            <Button v-if="can('view_production')" variant="secondary" class="gap-2 h-11">
              Kanban Produkcji
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Notatnik (Inertia v3)</CardTitle>
            <CardDescription>Szybka notatka systemowa</CardDescription>
          </CardHeader>
          <CardContent>
            <Form action="/notes" method="post" class="flex flex-col gap-3" #default="{ processing }">
              <Input name="note" placeholder="Treść notatki..." class="bg-muted/30" />
              <Button type="submit" :disabled="processing" class="w-full">
                {{ processing ? 'Zapisywanie...' : 'Zapisz notatkę' }}
              </Button>
            </Form>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>