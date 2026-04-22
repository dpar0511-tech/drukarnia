<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Badge } from '@/Components/ui/badge'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import { 
  MoreHorizontal, 
  Plus, 
  Search, 
  UserCog, 
  UserMinus, 
  UserCheck,
  Trash2,
  ChevronLeft,
  ChevronRight
} from 'lucide-vue-next'
import { 
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/Components/ui/alert-dialog'
import { ref, watch } from 'vue'
import { debounce } from 'lodash'
import { toast } from 'vue-sonner'

interface User {
  id: number
  imie: string
  nazwisko: string
  email: string
  aktywny: boolean
  role: string
  klient?: {
    id: number
    nazwa: string
  }
  last_login_at?: string
  created_at: string
}

const props = defineProps<{
  users: {
    data: User[]
    links: any[]
    meta: any
  }
  filters: {
    search?: string
    role_id?: string
  }
  roles: any[]
}>()

const search = ref(props.filters.search || '')
const userToDelete = ref<User | null>(null)

watch(search, debounce((value) => {
  router.get(route('admin.users.index'), { search: value }, {
    preserveState: true,
    replace: true
  })
}, 300))

const toggleActive = (user: User) => {
  router.post(route('admin.users.toggle-active', user.id), {}, {
    preserveScroll: true,
    onSuccess: () => toast.success('Status użytkownika został zaktualizowany.')
  })
}

const confirmDelete = (user: User) => {
  userToDelete.value = user
}

const deleteUser = () => {
  if (!userToDelete.value) return

  router.delete(route('admin.users.destroy', userToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Użytkownik został usunięty.')
      userToDelete.value = null
    }
  })
}
</script>

<template>
  <AppLayout title="Użytkownicy">
    <Head title="Użytkownicy" />

    <div class="flex flex-col gap-6 p-6">
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <h1 class="text-3xl font-bold tracking-tight">Użytkownicy</h1>
          <p class="text-muted-foreground">
            Zarządzaj kontami pracowników i klientów w systemie.
          </p>
        </div>
        <Link :href="route('admin.users.create')">
          <Button class="gap-2">
            <Plus class="size-4" /> Dodaj użytkownika
          </Button>
        </Link>
      </div>

      <div class="flex items-center gap-4">
        <div class="relative w-full max-w-sm">
          <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input 
            v-model="search" 
            placeholder="Szukaj użytkownika..." 
            class="pl-10" 
          />
        </div>
      </div>

      <div class="rounded-md border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Użytkownik</TableHead>
              <TableHead>Rola</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Ostatnie logowanie</TableHead>
              <TableHead class="text-right">Akcje</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="user in users.data" :key="user.id">
              <TableCell>
                <div class="flex items-center gap-3">
                  <Avatar class="size-9">
                    <AvatarFallback>{{ user.imie[0] }}{{ user.nazwisko[0] }}</AvatarFallback>
                  </Avatar>
                  <div class="flex flex-col">
                    <span class="font-medium">{{ user.imie }} {{ user.nazwisko }}</span>
                    <span class="text-xs text-muted-foreground">{{ user.email }}</span>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <Badge variant="outline">{{ user.role }}</Badge>
              </TableCell>
              <TableCell>
                <Badge :variant="user.aktywny ? 'default' : 'destructive'">
                  {{ user.aktywny ? 'Aktywny' : 'Zablokowany' }}
                </Badge>
              </TableCell>
              <TableCell>
                <span class="text-sm text-muted-foreground">
                  {{ user.last_login_at ? new Date(user.last_login_at).toLocaleString() : 'Nigdy' }}
                </span>
              </TableCell>
              <TableCell class="text-right">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" class="size-8 p-0">
                      <MoreHorizontal class="size-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Akcje</DropdownMenuLabel>
                    <Link :href="route('admin.users.edit', user.id)">
                      <DropdownMenuItem class="cursor-pointer">
                        <UserCog class="mr-2 size-4" /> Edytuj
                      </DropdownMenuItem>
                    </Link>
                    <DropdownMenuItem @click="toggleActive(user)" class="cursor-pointer">
                      <template v-if="user.aktywny">
                        <UserMinus class="mr-2 size-4" /> Zablokuj
                      </template>
                      <template v-else>
                        <UserCheck class="mr-2 size-4" /> Odblokuj
                      </template>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @click="confirmDelete(user)" class="cursor-pointer text-destructive focus:text-destructive">
                      <Trash2 class="mr-2 size-4" /> Usuń
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
            <TableRow v-if="users.data.length === 0">
              <TableCell colspan="5" class="h-24 text-center">
                Brak użytkowników do wyświetlenia.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Pagination -->
      <div v-if="users.meta && users.meta.last_page > 1" class="flex items-center justify-end space-x-2">
        <Link 
          v-for="link in users.meta.links" 
          :key="link.label"
          :href="link.url || '#'"
          v-html="link.label"
          :class="[
            'px-3 py-1 text-sm border rounded-md transition-colors',
            link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:bg-muted',
            !link.url ? 'opacity-50 cursor-not-allowed' : ''
          ]"
        />
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="!!userToDelete" @update:open="(val) => !val && (userToDelete = null)">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Czy na pewno chcesz usunąć użytkownika?</AlertDialogTitle>
          <AlertDialogDescription>
            Ta akcja jest nieodwracalna. Użytkownik <strong>{{ userToDelete?.imie }} {{ userToDelete?.nazwisko }}</strong> zostanie trwale usunięty z systemu.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Anuluj</AlertDialogCancel>
          <AlertDialogAction @click="deleteUser" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
            Usuń użytkownika
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>
