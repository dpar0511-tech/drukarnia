<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/Components/ui/card'
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/Components/ui/accordion'
import { Checkbox } from '@/Components/ui/checkbox'
import { ChevronLeft, Loader2, Save, Shield } from 'lucide-vue-next'
import { 
  FormField, 
  FormItem, 
  FormLabel, 
  FormControl, 
  FormMessage 
} from '@/Components/ui/form'
import { toast } from 'vue-sonner'

const props = defineProps<{
  availablePermissions: Record<string, Record<string, string>>
}>()

const form = useForm({
  name: '',
  permissions: [] as string[],
})

const togglePermission = (permission: string) => {
  const index = form.permissions.indexOf(permission)
  if (index > -1) {
    form.permissions.splice(index, 1)
  } else {
    form.permissions.push(permission)
  }
}

const submit = () => {
  form.post(route('admin.roles.store'), {
    onSuccess: () => {
      form.reset()
      toast.success('Rola została utworzona.')
    }
  })
}
</script>

<template>
  <AppLayout title="Dodaj rolę">
    <Head title="Dodaj rolę" />

    <div class="flex flex-col gap-6 p-6">
      <div class="flex items-center gap-4">
        <Link :href="route('admin.roles.index')">
          <Button variant="outline" size="icon">
            <ChevronLeft class="size-4" />
          </Button>
        </Link>
        <div class="flex flex-col gap-1">
          <h1 class="text-3xl font-bold tracking-tight">Dodaj nową rolę</h1>
          <p class="text-muted-foreground">
            Zdefiniuj nazwę roli i wybierz przypisane do niej uprawnienia.
          </p>
        </div>
      </div>

      <div class="max-w-3xl">
        <form @submit.prevent="submit">
          <Card>
            <CardHeader>
              <CardTitle>Konfiguracja roli</CardTitle>
              <CardDescription>
                Nazwa roli powinna być unikalna i opisowa.
              </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <FormField name="name">
                <FormItem>
                  <FormLabel>Nazwa roli</FormLabel>
                  <FormControl>
                    <div class="relative">
                      <Shield class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                      <Input v-model="form.name" placeholder="np. Menadżer Sprzedaży" class="pl-10" required />
                    </div>
                  </FormControl>
                  <FormMessage>{{ form.errors.name }}</FormMessage>
                </FormItem>
              </FormField>

              <div class="flex flex-col gap-4">
                <Label class="text-base">Uprawnienia (Kategorie)</Label>
                <Accordion type="multiple" class="w-full">
                  <AccordionItem 
                    v-for="(permissions, category) in availablePermissions" 
                    :key="category" 
                    :value="category"
                    class="border rounded-lg px-4 mb-2"
                  >
                    <AccordionTrigger class="hover:no-underline py-3">
                      <span class="font-semibold">{{ category }}</span>
                    </AccordionTrigger>
                    <AccordionContent>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-2">
                        <div 
                          v-for="(label, key) in permissions" 
                          :key="key" 
                          class="flex items-start gap-3"
                        >
                          <Checkbox 
                            :id="key" 
                            :checked="form.permissions.includes(key)"
                            @update:checked="() => togglePermission(key)"
                          />
                          <div class="grid gap-1.5 leading-none">
                            <Label 
                              :for="key" 
                              class="text-sm font-medium leading-none cursor-pointer peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                            >
                              {{ label }}
                            </Label>
                            <p class="text-xs text-muted-foreground">
                              Klucz: {{ key }}
                            </p>
                          </div>
                        </div>
                      </div>
                    </AccordionContent>
                  </AccordionItem>
                </Accordion>
                <p v-if="form.errors.permissions" class="text-sm text-destructive">{{ form.errors.permissions }}</p>
              </div>

              <div class="flex justify-end gap-3 mt-4">
                <Link :href="route('admin.roles.index')">
                  <Button variant="ghost">Anuluj</Button>
                </Link>
                <Button type="submit" :disabled="form.processing" class="gap-2">
                  <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                  <Save v-else class="size-4" />
                  Zapisz rolę
                </Button>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
