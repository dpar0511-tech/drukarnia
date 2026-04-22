<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'
import { 
  Bell, 
  CheckCheck, 
  ExternalLink,
  Info,
  AlertCircle
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card'
import { ScrollArea } from '@/Components/ui/scroll-area'
import { Badge } from '@/Components/ui/badge'

interface Notification {
  id: number
  typ: string
  tytul: string
  tresc: string
  link: string | null
  przeczytane: boolean
  created_at: string
}

const props = defineProps<{
  notifications: Notification[]
}>()

const markAsRead = (id: number) => {
  router.post(route('notifications.mark-as-read', id), {}, {
    preserveScroll: true
  })
}

const markAllAsRead = () => {
  router.post(route('notifications.mark-all-as-read'), {}, {
    preserveScroll: true
  })
}
</script>

<template>
  <Head title="Powiadomienia" />

  <AppLayout>
    <div class="mx-auto max-w-4xl space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold tracking-tight">Powiadomienia</h1>
        <Button 
          variant="outline" 
          size="sm" 
          class="gap-2"
          @click="markAllAsRead"
          :disabled="!notifications.some(n => !n.przeczytane)"
        >
          <CheckCheck class="size-4" />
          Oznacz wszystkie jako przeczytane
        </Button>
      </div>

      <Card>
        <CardContent class="p-0">
          <div v-if="notifications.length === 0" class="flex flex-col items-center justify-center py-12 text-muted-foreground">
            <Bell class="mb-4 size-12 opacity-20" />
            <p>Brak powiadomień.</p>
          </div>
          <div v-else class="divide-y">
            <div 
              v-for="notification in notifications" 
              :key="notification.id"
              :class="cn(
                'flex items-start gap-4 p-4 transition-colors',
                !notification.przeczytane && 'bg-muted/50'
              )"
            >
              <div class="mt-1">
                <Badge variant="secondary" v-if="notification.typ === 'info'" class="p-1">
                  <Info class="size-4" />
                </Badge>
                <Badge variant="destructive" v-else-if="notification.typ === 'alert'" class="p-1">
                  <AlertCircle class="size-4" />
                </Badge>
                <Badge variant="outline" v-else class="p-1">
                  <Bell class="size-4" />
                </Badge>
              </div>
              <div class="flex-1 space-y-1">
                <div class="flex items-center justify-between">
                  <p :class="cn('text-sm font-medium', !notification.przeczytane && 'font-bold')">
                    {{ notification.tytul }}
                  </p>
                  <span class="text-xs text-muted-foreground">
                    {{ new Date(notification.created_at).toLocaleString() }}
                  </span>
                </div>
                <p class="text-sm text-muted-foreground">
                  {{ notification.tresc }}
                </p>
                <div class="flex items-center gap-2 pt-2">
                  <Button 
                    v-if="!notification.przeczytane" 
                    variant="ghost" 
                    size="xs" 
                    @click="markAsRead(notification.id)"
                  >
                    Oznacz jako przeczytane
                  </Button>
                  <Button 
                    v-if="notification.link" 
                    variant="link" 
                    size="xs" 
                    as-child
                  >
                    <Link :href="notification.link" class="gap-1">
                      Zobacz szczegóły
                      <ExternalLink class="size-3" />
                    </Link>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Custom size for buttons in notifications */
:deep(.size-xs) {
  height: 1.5rem;
  padding-left: 0.5rem;
  padding-right: 0.5rem;
  font-size: 0.75rem;
}
</style>
