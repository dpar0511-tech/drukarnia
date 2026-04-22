<script setup>
import { Toaster } from '@/Components/ui/sonner'
import { Input } from '@/Components/ui/input'
import { Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { Bell, LayoutDashboard, ShoppingCart, MessageSquare, Users, Factory, UserCircle, LogOut, Settings, Search, Loader2, ShieldCheck } from 'lucide-vue-next'
import { useAuth } from '@/Composables/useAuth'
import { ref, watch, computed } from 'vue'
import debounce from 'lodash/debounce'
import { route } from 'ziggy-js'
import { onClickOutside } from '@vueuse/core'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'

const { user, can, canAny, initials, fullName } = useAuth()
const page = usePage()
const notificationsCount = computed(() => page.props.notificationsCount)

const searchQuery = ref('')
const searchResults = ref(null)
const isSearching = ref(false)
const searchContainer = ref(null)

let abortController = null

onClickOutside(searchContainer, () => {
  searchResults.value = null
})

const performSearch = debounce(async (query) => {
  if (abortController) abortController.abort()

  if (!query || query.length < 2) {
    searchResults.value = null
    isSearching.value = false
    return
  }
  
  isSearching.value = true
  abortController = new AbortController()

  axios.get(route('global.search'), {
    params: { query },
    signal: abortController.signal
  })
  .then(response => {
    searchResults.value = response.data
  })
  .catch(error => {
    if (!axios.isCancel(error)) {
      console.error('Search error:', error)
    }
  })
  .finally(() => {
    isSearching.value = false
  })
}, 300)

watch(searchQuery, (newQuery) => {
  performSearch(newQuery)
})
</script>

<template>
  <div class="min-h-screen bg-background flex text-foreground">
    <!-- Sidebar Navigation -->
    <aside class="w-64 border-r bg-card flex flex-col sticky top-0 h-screen">
      <div class="p-6 border-b">
        <h1 class="text-xl font-bold tracking-tight text-primary">DRUKARNIA ERP</h1>
      </div>
      
      <nav class="flex-1 p-4 flex flex-col gap-1 overflow-y-auto">
        <Link :href="route('dashboard')" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted transition-colors text-sm font-medium" :class="{ 'bg-muted text-primary': $page.component === 'Dashboard' }">
          <LayoutDashboard class="size-5" />
          Dashboard
        </Link>
        
        <Link v-if="can('manage_orders') || can('view_own_orders')" href="/orders" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted transition-colors text-sm font-medium">
          <ShoppingCart class="size-5" />
          Zamówienia
        </Link>
        
        <Link v-if="can('view_communication')" :href="route('inbox.index')" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted transition-colors text-sm font-medium" :class="{ 'bg-muted text-primary': $page.component.startsWith('Communication/Inbox') }">
          <MessageSquare class="size-5" />
          Komunikacja
          <span v-if="notificationsCount > 0" class="ml-auto bg-primary text-primary-foreground text-[10px] font-bold px-1.5 py-0.5 rounded-full">
            {{ notificationsCount }}
          </span>
        </Link>
        
        <Link v-if="can('manage_clients')" href="/clients" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted transition-colors text-sm font-medium">
          <Users class="size-5" />
          Klienci
        </Link>
        
        <Link v-if="can('view_production')" href="/production" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted transition-colors text-sm font-medium">
          <Factory class="size-5" />
          Produkcja
        </Link>

        <!-- Administracja -->
        <template v-if="canAny(['manage_users', 'manage_roles'])">
          <div class="pt-3 pb-1 px-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Administracja</p>
          </div>
          <Link v-if="can('manage_users')" :href="route('admin.users.index')" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted transition-colors text-sm font-medium" :class="{ 'bg-muted text-primary': $page.component.startsWith('Admin/Users') }">
            <Users class="size-5" />
            Użytkownicy
          </Link>
          <Link v-if="can('manage_roles')" :href="route('admin.roles.index')" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted transition-colors text-sm font-medium" :class="{ 'bg-muted text-primary': $page.component.startsWith('Admin/Roles') }">
            <ShieldCheck class="size-5" />
            Role
          </Link>
        </template>
      </nav>
      
      <div class="p-4 border-t">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button class="flex items-center gap-3 w-full p-2 rounded-md hover:bg-muted transition-colors text-left outline-none">
              <div class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs border border-primary/20">
                {{ initials }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate">{{ fullName }}</p>
                <p class="text-xs text-muted-foreground truncate">{{ user?.role?.name || 'Użytkownik' }}</p>
              </div>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel>Moje konto</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem class="gap-2">
              <UserCircle class="size-4" /> Profil
            </DropdownMenuItem>
            <DropdownMenuItem class="gap-2">
              <Settings class="size-4" /> Ustawienia
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-2 text-destructive focus:text-destructive">
                <LogOut class="size-4" /> Wyloguj się
              </Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Topbar -->
      <header class="h-16 border-b bg-card flex items-center justify-between px-6 sticky top-0 z-10">
        <div class="flex-1 flex items-center">
            <div class="relative w-96" ref="searchContainer">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Szukaj (klient, użytkownik)..." 
                    class="bg-muted/50 border-none h-10 pl-10" 
                />
                <Loader2 v-if="isSearching" class="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground animate-spin" />
                
                <div v-if="searchResults && searchQuery" class="absolute top-full left-0 w-full mt-1 bg-card border rounded-md shadow-lg z-50 max-h-96 overflow-y-auto">
                    <div v-for="(items, type) in searchResults" :key="type">
                        <div v-if="items.length > 0">
                            <div class="px-3 py-2 text-xs font-semibold text-muted-foreground bg-muted/30 border-y first:border-t-0">{{ type }}</div>
                            <Link 
                                v-for="item in items" 
                                :key="item.id" 
                                :href="item.url"
                                class="block px-3 py-2 hover:bg-muted transition-colors border-b last:border-b-0"
                                @click="searchQuery = ''; searchResults = null"
                            >
                                <div class="text-sm font-medium">{{ item.title }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.subtitle }}</div>
                            </Link>
                        </div>
                    </div>
                    <div v-if="Object.values(searchResults).every(arr => arr.length === 0) && !isSearching" class="px-3 py-4 text-sm text-center text-muted-foreground">
                        Brak wyników dla "{{ searchQuery }}"
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-4">
          <Link :href="route('notifications.index')" class="relative p-2 rounded-full hover:bg-muted transition-colors">
            <Bell class="size-5 text-muted-foreground" />
            <span v-if="notificationsCount > 0" class="absolute top-1.5 right-1.5 size-2 bg-destructive rounded-full border-2 border-background"></span>
          </Link>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 overflow-y-auto p-8 bg-background/50">
        <slot />
      </main>
    </div>

    <!-- Global Toaster from shadcn -->
    <Toaster />
  </div>
</template>