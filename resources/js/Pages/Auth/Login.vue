<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Checkbox } from '@/Components/ui/checkbox'
import { Mail, Lock, LogIn, Loader2 } from 'lucide-vue-next'
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/Components/ui/form'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Logowanie" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-bold tracking-tight text-foreground">Witaj ponownie</h1>
                <p class="text-lg text-muted-foreground">
                    Zaloguj się do swojego konta, aby kontynuować.
                </p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <FormField name="email">
                    <FormItem class="flex flex-col gap-2">
                        <FormLabel>Email</FormLabel>
                        <FormControl>
                            <div class="relative">
                                <Mail class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <Input 
                                    type="email" 
                                    v-model="form.email" 
                                    placeholder="twoj@email.pl" 
                                    class="pl-10"
                                    required 
                                    autofocus 
                                    autocomplete="username"
                                />
                            </div>
                        </FormControl>
                        <FormMessage>{{ form.errors.email }}</FormMessage>
                    </FormItem>
                </FormField>

                <FormField name="password">
                    <FormItem class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <FormLabel>Hasło</FormLabel>
                            <Link 
                                :href="route('password.request')" 
                                class="text-sm text-primary hover:underline font-medium transition-colors"
                            >
                                Zapomniałeś hasła?
                            </Link>
                        </div>
                        <FormControl>
                            <div class="relative">
                                <Lock class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <Input 
                                    type="password" 
                                    v-model="form.password" 
                                    class="pl-10"
                                    required 
                                    autocomplete="current-password"
                                />
                            </div>
                        </FormControl>
                        <FormMessage>{{ form.errors.password }}</FormMessage>
                    </FormItem>
                </FormField>

                <div class="flex items-center gap-2">
                    <Checkbox id="remember" v-model:checked="form.remember" />
                    <label
                        for="remember"
                        class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                    >
                        Zapamiętaj mnie
                    </label>
                </div>

                <Button type="submit" class="w-full h-12 text-lg gap-2" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-5 animate-spin" />
                    <LogIn v-else class="size-5" />
                    {{ form.processing ? 'Logowanie...' : 'Zaloguj się' }}
                </Button>
            </form>

            <div class="pt-4 border-t flex flex-col gap-4">
                <p class="text-sm text-muted-foreground">
                    Nie masz konta? 
                    <a href="mailto:admin@drukarnia.local" class="text-primary hover:underline font-semibold transition-colors">
                        Skontaktuj się z administratorem
                    </a>.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
