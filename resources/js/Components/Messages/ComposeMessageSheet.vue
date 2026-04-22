<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { 
  Sheet, 
  SheetContent, 
  SheetHeader, 
  SheetTitle, 
  SheetDescription,
  SheetFooter,
} from '@/Components/ui'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { 
  Select, 
  SelectContent, 
  SelectItem, 
  SelectTrigger, 
  SelectValue 
} from '@/Components/ui/select'
import { ScrollArea } from '@/Components/ui/scroll-area'
import { Checkbox } from '@/Components/ui/checkbox'
import { toast } from 'vue-sonner'
import { Loader2, Send, Eye, FileText, Paperclip, FileIcon } from 'lucide-vue-next'
import axios from 'axios'
import { debounce } from 'lodash'

const props = defineProps<{
  watekId: number
  klientEmail?: string
}>()

const isOpen = defineModel<boolean>('open', { default: false })
const templates = ref<any[]>([])
const availableAttachments = ref<any[]>([])
const previewHtml = ref('')
const isPreviewLoading = ref(false)

const form = useForm({
  watek_id: props.watekId,
  szablon_id: null as number | null,
  temat: '',
  tresc: '',
  recipient: props.klientEmail || '',
  attachments: [] as number[],
})

const fetchTemplates = async () => {
  try {
    const response = await axios.get(route('messages.templates'))
    templates.value = response.data
  } catch (error) {
    console.error('Failed to fetch templates', error)
  }
}

const fetchAttachments = async () => {
  try {
    const response = await axios.get(route('messages.attachments', props.watekId))
    availableAttachments.value = response.data
  } catch (error) {
    console.error('Failed to fetch attachments', error)
  }
}

const updatePreview = debounce(async () => {
  if (!form.tresc) {
    previewHtml.value = ''
    return
  }

  isPreviewLoading.value = true
  try {
    const response = await axios.post(route('messages.preview'), {
      content: form.tresc,
      watek_id: props.watekId
    })
    previewHtml.value = response.data.html
  } catch (error) {
    console.error('Preview failed', error)
  } finally {
    isPreviewLoading.value = false
  }
}, 500)

const onTemplateChange = (value: string) => {
  const template = templates.value.find(t => t.id === parseInt(value))
  if (template) {
    form.szablon_id = template.id
    form.temat = template.temat
    form.tresc = template.tresc_html || template.tresc_template
    updatePreview()
  }
}

const toggleAttachment = (id: number) => {
  const index = form.attachments.indexOf(id)
  if (index > -1) {
    form.attachments.splice(index, 1)
  } else {
    form.attachments.push(id)
  }
}

const submit = () => {
  form.post(route('messages.send'), {
    onSuccess: () => {
      isOpen.value = false
      toast.success('Wiadomość została zakolejkowana.')
      form.reset()
    },
    onError: (errors) => {
      toast.error('Błąd podczas wysyłania wiadomości.')
    }
  })
}

onMounted(() => {
  fetchTemplates()
  fetchAttachments()
})

watch(() => props.watekId, () => {
  form.watek_id = props.watekId
  fetchAttachments()
})

watch(() => form.tresc, () => {
  updatePreview()
})
</script>

<template>
  <Sheet v-model:open="isOpen">
    <SheetContent side="right" class="w-[90%] sm:max-w-[1000px] flex flex-col p-0">
      <SheetHeader class="p-6 border-b">
        <SheetTitle class="flex items-center gap-2">
          <FileText class="size-5 text-primary" />
          Komponowanie nowej wiadomości
        </SheetTitle>
        <SheetDescription>
          Wybierz szablon lub napisz własną treść. Podgląd aktualizuje się na żywo.
        </SheetDescription>
      </SheetHeader>

      <div class="flex-1 flex overflow-hidden">
        <!-- Editor Section -->
        <ScrollArea class="w-1/2 border-r p-6">
          <div class="space-y-6">
            <div class="space-y-2">
              <Label>Wybor szablonu</Label>
              <Select @update:model-value="onTemplateChange">
                <SelectTrigger>
                  <SelectValue placeholder="Wybierz szablon..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="t in templates" :key="t.id" :value="t.id.toString()">
                    {{ t.nazwa }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="recipient">Odbiorca</Label>
              <Input id="recipient" v-model="form.recipient" placeholder="email@example.com" />
            </div>

            <div class="space-y-2">
              <Label for="subject">Temat</Label>
              <Input id="subject" v-model="form.temat" placeholder="Temat wiadomości..." />
            </div>

            <div class="space-y-2">
              <Label for="content">Treść (Blade / HTML)</Label>
              <Textarea 
                id="content" 
                v-model="form.tresc" 
                placeholder="Napisz treść wiadomości... Możesz używać tagów Blade np. {{ $klient->name }}"
                class="min-h-[300px] font-mono text-sm"
              />
            </div>

            <div class="space-y-2">
              <Label class="flex items-center gap-2">
                <Paperclip class="size-4" /> Załączniki (z DAMS)
              </Label>
              <div v-if="availableAttachments.length > 0" class="border rounded-md divide-y">
                <div 
                  v-for="plik in availableAttachments" 
                  :key="plik.id"
                  class="flex items-center gap-3 p-3 hover:bg-muted/50 transition-colors"
                >
                  <Checkbox 
                    :id="`plik-${plik.id}`" 
                    :checked="form.attachments.includes(plik.id)"
                    @update:checked="toggleAttachment(plik.id)"
                  />
                  <div class="flex-1 flex items-center gap-2 min-w-0">
                    <FileIcon class="size-4 text-muted-foreground shrink-0" />
                    <label 
                      :for="`plik-${plik.id}`" 
                      class="text-sm font-medium truncate cursor-pointer"
                    >
                      {{ plik.nazwa_oryginalna }}
                    </label>
                  </div>
                  <span class="text-[10px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded uppercase">
                    {{ plik.mime_type.split('/')[1] }}
                  </span>
                </div>
              </div>
              <div v-else class="p-4 border rounded-md border-dashed text-center text-muted-foreground text-sm">
                Brak plików przypisanych do tego zamówienia.
              </div>
            </div>
          </div>
        </ScrollArea>

        <!-- Preview Section -->
        <div class="w-1/2 bg-muted/30 flex flex-col">
          <div class="p-4 border-b bg-muted/50 flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
              <Eye class="size-4" /> Podgląd na żywo
            </span>
            <Loader2 v-if="isPreviewLoading" class="size-4 animate-spin text-primary" />
          </div>
          <ScrollArea class="flex-1 p-6">
            <div 
              class="bg-white shadow-sm border rounded-lg p-8 min-h-[500px] text-sm"
              v-html="previewHtml || '<p class=\'text-muted-foreground text-center pt-20\'>Wpisz treść, aby zobaczyć podgląd...</p>'"
            ></div>
          </ScrollArea>
        </div>
      </div>

      <SheetFooter class="p-6 border-t bg-muted/10">
        <Button variant="outline" @click="isOpen = false">Anuluj</Button>
        <Button :disabled="form.processing" @click="submit" class="gap-2">
          <Loader2 v-if="form.processing" class="size-4 animate-spin" />
          <Send v-else class="size-4" />
          Wyślij wiadomość
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
