<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/Components/ui/button'
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
import { 
  MoreHorizontal, 
  Plus, 
  Shield, 
  Edit, 
  Trash2,
  Users
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
} from '@/Components/ui/alert-dialog'
import { ref } from 'vue'
import { toast } from 'vue-sonner'

interface Role {
  id: number
  name: string
  permissions: string[]
  users_count: number
  created_at: string
}

const props = defineProps<{
  roles: {
    data: Role[]
  }
}>()

const roleToDelete = ref<Role | null>(null)

const confirmDelete = (role: Role) => {
  if (role.users_count > 0) {
    toast.error('Nie można usunąć roli, która jest przypisana do użytkowników.')
    return
  }
  roleToDelete.value = role
}

const deleteRole = () => {
  if (!roleToDelete.value) return

  router.delete(route('admin.roles.destroy', roleToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Rola została usunięta.')
      roleToDelete.value = null
    }
  })
}
</script>

<template>
  <AppLayout title="Role i Uprawnienia">
    <Head title="Role i Uprawnienia" />

    <div class="flex flex-col gap-6 p-6">
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <h1 class="text-3xl font-bold tracking-tight">Role i Uprawnienia</h1>
          <p class="text-muted-foreground">
            Definiuj role systemowe i przypisuj do nich uprawnienia.
          </p>
        </div>
        <Link :href="route('admin.roles.create')">
          <Button class="gap-2">
            <Plus class="size-4" /> Dodaj rolę
          </Button>
        </Link>
      </div>

      <div class="rounded-md border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nazwa roli</TableHead>
              <TableHead>Liczba uprawnień</TableHead>
              <TableHead>Użytkownicy</TableHead>
              <TableHead>Data utworzenia</TableHead>
              <TableHead class="text-right">Akcje</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="role in roles.data" :key="role.id">
              <TableCell>
                <div class="flex items-center gap-2 font-medium">
                  <Shield class="size-4 text-primary" />
                  {{ role.name }}
                </div>
              </TableCell>
              <TableCell>
                <Badge variant="secondary">
                  {{ role.permissions ? role.permissions.length : 0 }}
                </Badge>
              </TableCell>
              <TableCell>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <Users class="size-4" />
                  {{ role.users_count }}
                </div>
              </TableCell>
              <TableCell>
                <span class="text-sm text-muted-foreground">
                  {{ new Date(role.created_at).toLocaleDateString() }}
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
                    <Link :href="route('admin.roles.edit', role.id)">
                      <DropdownMenuItem class="cursor-pointer">
                        <Edit class="mr-2 size-4" /> Edytuj uprawnienia
                      </DropdownMenuItem>
                    </Link>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem 
                      @click="confirmDelete(role)" 
                      class="cursor-pointer text-destructive focus:text-destructive"
                      :disabled="role.users_count > 0"
                    >
                      <Trash2 class="mr-2 size-4" /> Usuń rolę
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="!!roleToDelete" @update:open="(val) => !val && (roleToDelete = null)">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Czy na pewno chcesz usunąć rolę?</AlertDialogTitle>
          <AlertDialogDescription>
            Ta akcja jest nieodwracalna. Rola <strong>{{ roleToDelete?.name }}</strong> zostanie trwale usunięta z systemu.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Anuluj</AlertDialogCancel>
          <AlertDialogAction @click="deleteRole" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
            Usuń rolę
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>
