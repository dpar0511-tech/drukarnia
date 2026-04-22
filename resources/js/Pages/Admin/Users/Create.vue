<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/Components/ui/card'
import { 
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage 
} from '@/Components/ui/form'
import { Switch } from '@/Components/ui/switch'
import { ChevronLeft, Loader2, Save } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  roles: any[]
  klienci: any[]
}>()

const form = useForm({
  imie: '',
  nazwisko: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: '',
  klient_id: null as number | null,
  aktywny: true,
})


const isKlientRole = computed(() => {
  const klientRole = props.roles.find((r) => r.name === 'Klient')
  return form.role_id !== klientRole?.id.toString()
})

const submit = () => {
  form.post(route('admin.users.store'), {
    onSuccess: () => {
      form.reset()
      toast.success('Użytkownik został utworzony.')
    }
  })
}
</script>

<template>
  <AppLayout title="Dodaj użytkownika">
    <Head title="Dodaj użytkownika" />

    <div class="flex flex-col gap-6 p-6">
      <div class="flex items-center gap-4">
        <Link :href="route('admin.users.index')">
          <Button variant="outline" size="icon">
            <ChevronLeft class="size-4" />
          </Button>
        </Link>
        <div class="flex flex-col gap-1">
          <h1 class="text-3xl font-bold tracking-tight">Dodaj użytkownika</h1>
          <p class="text-muted-foreground">
            Utwórz nowe konto użytkownika w systemie.
          </p>
        </div>
      </div>

      <div class="max-w-2xl">
        <form @submit.prevent="submit">
          <Card>
            <CardHeader>
              <CardTitle>Dane użytkownika</CardTitle>
              <CardDescription>
                Podstawowe informacje i poświadczenia.
              </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <div class="grid grid-cols-2 gap-4">
                <FormField name="imie">
                  <FormItem>
                    <FormLabel>Imię</FormLabel>
                    <FormControl>
                      <Input v-model="form.imie" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.imie }}</FormMessage>
                  </FormItem>
                </FormField>
                <FormField name="nazwisko">
                  <FormItem>
                    <FormLabel>Nazwisko</FormLabel>
                    <FormControl>
                      <Input v-model="form.nazwisko" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.nazwisko }}</FormMessage>
                  </FormItem>
                </FormField>
              </div>

              <FormField name="email">
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input type="email" v-model="form.email" required />
                  </FormControl>
                  <FormMessage>{{ form.errors.email }}</FormMessage>
                </FormItem>
              </FormField>

              <div class="grid grid-cols-2 gap-4">
                <FormField name="password">
                  <FormItem>
                    <FormLabel>Hasło</FormLabel>
                    <FormControl>
                      <Input type="password" v-model="form.password" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.password }}</FormMessage>
                  </FormItem>
                </FormField>
                <FormField name="password_confirmation">
                  <FormItem>
                    <FormLabel>Potwierdź hasło</FormLabel>
                    <FormControl>
                      <Input type="password" v-model="form.password_confirmation" required />
                    </FormControl>
                  </FormItem>
                </FormField>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <FormField name="role_id">
                  <FormItem>
                    <FormLabel>Rola</FormLabel>
                    <FormControl>
                      <Select v-model="form.role_id">
                        <SelectTrigger>
                          <SelectValue placeholder="Wybierz rolę" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="role in roles.data" :key="role.id" :value="role.id.toString()">
                            {{ role.name }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </FormControl>
                    <FormMessage>{{ form.errors.role_id }}</FormMessage>
                  </FormItem>
                </FormField>

                <FormField name="klient_id">
                  <FormItem>
                    <FormLabel>Klient (Opcjonalnie)</FormLabel>
                    <FormControl>
                      <Select v-model="form.klient_id" :disabled="isKlientRole">
                        <SelectTrigger>
                          <SelectValue placeholder="Wybierz klienta" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="klient in klienci" :key="klient.id" :value="klient.id.toString()">
                            {{ klient.imie_nazwa }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </FormControl>
                    <FormMessage>{{ form.errors.klient_id }}</FormMessage>
                  </FormItem>
                </FormField>
              </div>

              <div class="flex items-center justify-between gap-4 p-4 rounded-lg border bg-muted/50">
                <div class="flex flex-col gap-1">
                  <Label for="aktywny" class="font-semibold">Konto aktywne</Label>
                  <span class="text-xs text-muted-foreground">
                    Zablokowani użytkownicy nie mogą się logować do systemu.
                  </span>
                </div>
                <Switch id="aktywny" v-model:checked="form.aktywny" />
              </div>

              <div class="flex justify-end gap-3 mt-4">
                <Link :href="route('admin.users.index')">
                  <Button variant="ghost">Anuluj</Button>
                </Link>
                <Button type="submit" :disabled="form.processing" class="gap-2">
                  <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                  <Save v-else class="size-4" />
                  Zapisz użytkownika
                </Button>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
