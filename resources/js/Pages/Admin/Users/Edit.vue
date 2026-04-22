<script setup lang="ts">
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
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/Components/ui/tabs'
import { Switch } from '@/Components/ui/switch'
import { 
  ChevronLeft, 
  Loader2, 
  Save, 
  User as UserIcon, 
  ShieldCheck, 
  Activity 
} from 'lucide-vue-next'
import { 
  FormField, 
  FormItem, 
  FormLabel, 
  FormControl, 
  FormMessage 
} from '@/Components/ui/form'
import { toast } from 'vue-sonner'

const props = defineProps<{
  user: {
    data: any
  }
  roles: {
    data: any[]
  }
  klienci: any[]
}>()

const form = useForm({
  imie: props.user.data.imie,
  nazwisko: props.user.data.nazwisko,
  email: props.user.data.email,
  password: '',
  password_confirmation: '',
  role_id: props.user.data.role_id?.toString() || '',
  klient_id: props.user.data.klient?.id?.toString() || null,
  aktywny: props.user.data.aktywny,
})

const submit = () => {
  form.put(route('admin.users.update', props.user.data.id), {
    onSuccess: () => {
      form.reset('password', 'password_confirmation')
      toast.success('Dane użytkownika zostały zaktualizowane.')
    }
  })
}
</script>

<template>
  <AppLayout title="Edytuj użytkownika">
    <Head title="Edytuj użytkownika" />

    <div class="flex flex-col gap-6 p-6">
      <div class="flex items-center gap-4">
        <Link :href="route('admin.users.index')">
          <Button variant="outline" size="icon">
            <ChevronLeft class="size-4" />
          </Button>
        </Link>
        <div class="flex flex-col gap-1">
          <h1 class="text-3xl font-bold tracking-tight">
            {{ user.data.imie }} {{ user.data.nazwisko }}
          </h1>
          <p class="text-muted-foreground">
            Zarządzaj ustawieniami konta i uprawnieniami.
          </p>
        </div>
      </div>

      <Tabs default-value="profile" class="w-full">
        <TabsList class="mb-4">
          <TabsTrigger value="profile" class="gap-2">
            <UserIcon class="size-4" /> Profil
          </TabsTrigger>
          <TabsTrigger value="roles" class="gap-2">
            <ShieldCheck class="size-4" /> Uprawnienia
          </TabsTrigger>
          <TabsTrigger value="activity" class="gap-2">
            <Activity class="size-4" /> Aktywność
          </TabsTrigger>
        </TabsList>

        <form @submit.prevent="submit">
          <TabsContent value="profile">
            <div class="max-w-2xl">
              <Card>
                <CardHeader>
                  <CardTitle>Dane profilowe</CardTitle>
                  <CardDescription>
                    Podstawowe informacje i zmiana hasła.
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

                  <div class="pt-4 border-t flex flex-col gap-4">
                    <h3 class="text-sm font-medium text-muted-foreground uppercase tracking-wider">Zmiana hasła (opcjonalnie)</h3>
                    <div class="grid grid-cols-2 gap-4">
                      <FormField name="password">
                        <FormItem>
                          <FormLabel>Nowe hasło</FormLabel>
                          <FormControl>
                            <Input type="password" v-model="form.password" />
                          </FormControl>
                          <FormMessage>{{ form.errors.password }}</FormMessage>
                        </FormItem>
                      </FormField>
                      <FormField name="password_confirmation">
                        <FormItem>
                          <FormLabel>Potwierdź nowe hasło</FormLabel>
                          <FormControl>
                            <Input type="password" v-model="form.password_confirmation" />
                          </FormControl>
                        </FormItem>
                      </FormField>
                    </div>
                  </div>

                  <div class="flex justify-end gap-3 mt-4">
                    <Link :href="route('admin.users.index')">
                      <Button variant="ghost">Anuluj</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" class="gap-2">
                      <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                      <Save v-else class="size-4" />
                      Zapisz zmiany
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="roles">
            <div class="max-w-2xl">
              <Card>
                <CardHeader>
                  <CardTitle>Rola i status</CardTitle>
                  <CardDescription>
                    Przypisz rolę systemową i określ dostęp do platformy.
                  </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
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
                        <FormLabel>Powiązany klient</FormLabel>
                        <FormControl>
                          <Select v-model="form.klient_id">
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
                    <Button type="submit" :disabled="form.processing" class="gap-2">
                      <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                      <Save v-else class="size-4" />
                      Zapisz zmiany
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </form>

        <TabsContent value="activity">
          <div class="max-w-2xl">
            <Card>
              <CardHeader>
                <CardTitle>Historia aktywności</CardTitle>
                <CardDescription>
                  Ostatnie logowania i kluczowe akcje użytkownika.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div class="flex flex-col gap-6">
                  <div v-if="user.data.audit_logs && user.data.audit_logs.length > 0" class="flex flex-col gap-4">
                    <div 
                      v-for="log in user.data.audit_logs" 
                      :key="log.id"
                      class="flex items-start gap-4 text-sm p-3 rounded-lg border bg-muted/30"
                    >
                      <div class="size-2 mt-1.5 rounded-full" :class="{
                        'bg-green-500': log.event === 'created',
                        'bg-blue-500': log.event === 'updated',
                        'bg-red-500': log.event === 'deleted'
                      }" />
                      <div class="flex-1 flex flex-col gap-1">
                        <div class="flex items-center justify-between gap-2">
                          <span class="font-semibold capitalize">{{ log.event }}</span>
                          <span class="text-xs text-muted-foreground">{{ new Date(log.created_at).toLocaleString() }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                          Wykonane przez: {{ log.user.name }} ({{ log.ip_address }})
                        </p>
                        
                        <div v-if="log.changes && log.event === 'updated'" class="mt-2 flex flex-col gap-1">
                          <div v-for="(val, field) in log.changes.new" :key="field" class="text-[10px] grid grid-cols-[80px_1fr] gap-2">
                            <span class="font-medium text-muted-foreground">{{ field }}:</span>
                            <span class="truncate">
                              <span class="line-through text-destructive opacity-50 mr-1">{{ log.changes.old[field] }}</span>
                              <span class="text-primary font-medium">{{ val }}</span>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div v-else class="flex flex-col items-center justify-center py-12 text-muted-foreground gap-2">
                    <Activity class="size-8 opacity-20" />
                    <p>Brak zarejestrowanej aktywności.</p>
                  </div>

                  <div class="flex flex-col gap-4 pt-4 border-t">
                    <div class="flex items-center gap-4 text-sm">
                      <div class="size-2 rounded-full bg-primary" />
                      <div class="flex-1">
                        <p class="font-medium">Ostatnie logowanie</p>
                        <p class="text-muted-foreground">
                          {{ user.data.last_login_at ? new Date(user.data.last_login_at).toLocaleString() : 'Brak danych' }}
                        </p>
                      </div>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                      <div class="size-2 rounded-full bg-muted" />
                      <div class="flex-1">
                        <p class="font-medium">Konto utworzone</p>
                        <p class="text-muted-foreground">
                          {{ new Date(user.data.created_at).toLocaleString() }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  </AppLayout>
</template>
