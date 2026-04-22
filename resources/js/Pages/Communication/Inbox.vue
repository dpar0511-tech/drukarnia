<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'
import { 
  Send, 
  User as UserIcon, 
  MessageSquare, 
  MoreVertical, 
  Search,
  ChevronRight,
  Circle,
  FileText
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ComposeMessageSheet from '@/Components/Messages/ComposeMessageSheet.vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { ScrollArea } from '@/Components/ui/scroll-area'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import { Badge } from '@/Components/ui/badge'
import { Textarea } from '@/Components/ui/textarea'

interface Thread {
  id: number
  temat: string
  status: string
  unread_count: number
  klient?: {
    id: number
    imie_nazwa: string
  }
  zamowienie?: {
    id: number
    status: string
  }
  updated_at: string
}

interface Message {
  id: number
  kierunek: 'przychodzacy' | 'wychodzacy'
  kanal: 'email' | 'sms' | 'system'
  tresc: string
  nadawca_email?: string
  created_at: string
}

interface Props {
  threads: Thread[]
  activeThreadData?: {
    id: number
    temat: string
    klient?: { imie_nazwa: string; email_glowny: string }
    zamowienie?: { id: number; status: string }
    wiadomosci: Message[]
  }
}

const props = defineProps<Props>()

const showComposeSheet = ref(false)

const form = useForm({
  tresc: '',
  kanal: 'email'
})

const submitReply = () => {
  if (!props.activeThreadData) return
  
  form.post(route('inbox.reply', props.activeThreadData.id), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('tresc')
    }
  })
}

const selectThread = (id: number) => {
  router.get(route('inbox.show', id), {}, {
    preserveState: true,
    preserveScroll: true,
    only: ['activeThreadData', 'threads']
  })
}

const createClientFromThread = () => {
  if (!props.activeThreadData) return
  // Redirect to client create with prefilled email from the first incoming message
  const firstIncoming = props.activeThreadData.wiadomosci.find(m => m.kierunek === 'przychodzacy')
  router.get(route('admin.klienci.create', { email: firstIncoming?.nadawca_email }))
}

const createOrderFromThread = () => {
  if (!props.activeThreadData) return
  router.get(route('admin.zamowienia.create', { 
    klient_id: props.activeThreadData.klient?.id,
    watek_id: props.activeThreadData.id,
    temat: props.activeThreadData.temat
  }))
}
</script>

<template>
  <Head title="Inbox" />

  <AppLayout>
    <div class="flex h-[calc(100vh-theme(spacing.32))] gap-4">
      <!-- Master: Threads List -->
      <Card class="flex w-1/3 flex-col overflow-hidden">
        <div class="p-4 border-b">
          <div class="relative">
            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              type="search"
              placeholder="Szukaj wątków..."
              class="pl-8"
            />
          </div>
        </div>
        
        <ScrollArea class="flex-1">
          <div class="flex flex-col">
            <button
              v-for="thread in threads"
              :key="thread.id"
              @click="selectThread(thread.id)"
              :class="cn(
                'flex flex-col items-start gap-2 border-b p-4 text-left transition-all hover:bg-muted',
                activeThreadData?.id === thread.id && 'bg-muted'
              )"
            >
              <div class="flex w-full items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="font-semibold">{{ thread.klient?.imie_nazwa || 'Nieznany klient' }}</span>
                  <Circle v-if="thread.unread_count > 0" class="size-2 fill-red-500 text-red-500" />
                </div>
                <span class="text-xs text-muted-foreground">
                  {{ new Date(thread.updated_at).toLocaleDateString() }}
                </span>
              </div>
              <div class="text-xs font-medium text-muted-foreground">{{ thread.temat }}</div>
              <div class="flex gap-2">
                <Badge v-if="thread.zamowienie" variant="outline" class="text-[10px]">
                  #{{ thread.zamowienie.id }}
                </Badge>
                <Badge variant="secondary" class="text-[10px] uppercase">
                  {{ thread.status }}
                </Badge>
              </div>
            </button>
          </div>
        </ScrollArea>
      </Card>

      <!-- Detail: Conversation -->
      <Card class="flex flex-1 flex-col overflow-hidden">
        <template v-if="activeThreadData">
          <div class="flex items-center justify-between border-b p-4">
            <div class="flex items-center gap-3">
              <Avatar>
                <AvatarFallback>{{ activeThreadData.klient?.imie_nazwa.charAt(0) }}</AvatarFallback>
              </Avatar>
              <div>
                <div class="font-semibold">{{ activeThreadData.klient?.imie_nazwa || 'Nieznany nadawca' }}</div>
                <div class="text-xs text-muted-foreground">{{ activeThreadData.temat }}</div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div v-if="activeThreadData.zamowienie" class="flex items-center gap-2 mr-2">
                <span class="text-sm text-muted-foreground">Zamówienie:</span>
                <Badge variant="outline">#{{ activeThreadData.zamowienie.id }}</Badge>
              </div>
              <div class="flex gap-2">
                <Button v-if="activeThreadData.klient" variant="outline" size="sm" class="gap-2" @click="showComposeSheet = true">
                   <FileText class="size-4" /> Szablony
                </Button>
                <Button v-if="!activeThreadData.klient" variant="secondary" size="sm" @click="createClientFromThread">
                  Dodaj Klienta
                </Button>
                <Button variant="outline" size="sm" @click="createOrderFromThread">
                  Utwórz Zamówienie
                </Button>
              </div>
            </div>
          </div>

          <!-- Compose Message Sheet -->
          <ComposeMessageSheet
            v-if="activeThreadData"
            v-model:open="showComposeSheet"
            :watek-id="activeThreadData.id"
            :klient-email="activeThreadData.klient?.email_glowny"
          />

          <ScrollArea class="flex-1 p-4">
            <div class="flex flex-col gap-4">
              <div
                v-for="message in activeThreadData.wiadomosci"
                :key="message.id"
                :class="cn(
                  'flex max-w-[80%] flex-col gap-1',
                  message.kierunek === 'wychodzacy' ? 'ml-auto items-end' : 'mr-auto items-start'
                )"
              >
                <div
                  :class="cn(
                    'rounded-lg p-3 text-sm',
                    message.kierunek === 'wychodzacy' 
                      ? 'bg-primary text-primary-foreground' 
                      : 'bg-muted'
                  )"
                >
                  <div v-html="message.tresc"></div>
                </div>
                <span class="text-[10px] text-muted-foreground">
                  {{ new Date(message.created_at).toLocaleString() }} via {{ message.kanal }}
                </span>
              </div>
            </div>
          </ScrollArea>

          <div class="border-t p-4">
            <form @submit.prevent="submitReply" class="flex flex-col gap-3">
              <Textarea
                v-model="form.tresc"
                placeholder="Wpisz treść odpowiedzi..."
                class="min-h-[100px] resize-none"
              />
              <div class="flex items-center justify-between">
                <div class="flex gap-2">
                   <Button 
                    type="button" 
                    variant="ghost" 
                    size="sm"
                    @click="form.kanal = 'email'"
                    :class="form.kanal === 'email' && 'bg-muted'"
                   >Email</Button>
                   <Button 
                    type="button" 
                    variant="ghost" 
                    size="sm"
                    @click="form.kanal = 'sms'"
                    :class="form.kanal === 'sms' && 'bg-muted'"
                   >SMS</Button>
                </div>
                <Button type="submit" class="gap-2" :disabled="form.processing">
                  <Send class="size-4" />
                  Wyślij
                </Button>
              </div>
            </form>
          </div>
        </template>
        <template v-else>
          <div class="flex flex-1 items-center justify-center text-muted-foreground">
            <div class="flex flex-col items-center gap-2">
              <MessageSquare class="size-12 opacity-20" />
              <p>Wybierz wątek z listy po lewej, aby zobaczyć wiadomość.</p>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </AppLayout>
</template>
